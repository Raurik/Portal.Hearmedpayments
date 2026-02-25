<?php
/**
 * HearMed Admin — Products & Services
 * Shortcode: [hearmed_products]
 * 3 primary tabs: Hearing Aids, Services, Bundled Items
 * All stored in hearmed_reference.products with item_type discriminator.
 *
 * @package HearMed_Portal
 * @since   5.2.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Products {

    private static $item_types = [
        'product' => 'Hearing Aids',
        'service' => 'Services',
        'bundled' => 'Bundled Items',
    ];

    private static $ha_styles = ['BTE','RIC','ITE','ITC','CIC','IIC','CROS','BiCROS','Other'];

    private static $speaker_powers = [
        '60'  => 'Small (60)',
        '85'  => 'Medium (85)',
        '100' => 'Power (100)',
        '105' => 'Power 105',
        'mould' => 'Mould',
    ];

    public function __construct() {
        add_shortcode('hearmed_products', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_product',    [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_product',  [$this, 'ajax_delete']);
        add_action('wp_ajax_hm_admin_import_products', [$this, 'ajax_import']);
        add_action('wp_ajax_hm_admin_add_bundled_category', [$this, 'ajax_add_bundled_category']);
    }

    private function get_products($type = null) {
        $sql = "SELECT p.*, m.name AS manufacturer_name
                FROM hearmed_reference.products p
                LEFT JOIN hearmed_reference.manufacturers m ON p.manufacturer_id = m.id
                WHERE p.is_active = true";
        $params = [];
        if ($type) {
            $sql .= " AND p.item_type = $1";
            $params[] = $type;
        }
        $sql .= " ORDER BY m.name, p.product_name";
        return HearMed_DB::get_results($sql, $params) ?: [];
    }

    private function get_manufacturers() {
        return HearMed_DB::get_results(
            "SELECT id, name FROM hearmed_reference.manufacturers WHERE is_active = true ORDER BY name"
        ) ?: [];
    }

    private function get_bundled_categories() {
        $check = HearMed_DB::get_var("SELECT to_regclass('hearmed_reference.bundled_categories')");
        if ($check === null) return ['Speaker','Dome','Other'];
        $rows = HearMed_DB::get_results(
            "SELECT category_name FROM hearmed_reference.bundled_categories WHERE is_active = true ORDER BY sort_order, category_name"
        ) ?: [];
        $cats = array_map(function($r) { return $r->category_name; }, $rows);
        return !empty($cats) ? $cats : ['Speaker','Dome','Other'];
    }

    /**
     * Auto-generate product code based on item type
     */
    private static function generate_code($type, $data) {
        if ($type === 'product') {
            // Hearing Aid: first 2 of manufacturer + first letter of model + tech level numbers + (style)
            $mfr   = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $data['manufacturer_name'] ?? ''), 0, 2));
            $model  = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $data['model'] ?? ''), 0, 1));
            $tech   = preg_replace('/[^0-9]/', '', $data['tech_level'] ?? '');
            $style  = strtoupper($data['style'] ?? '');
            $code   = $mfr . $model . $tech;
            if ($style) $code .= ' (' . $style . ')';
            return $code;
        }
        if ($type === 'service' || $type === 'bundled') {
            // First letters of each word in name
            $words = preg_split('/\s+/', trim($data['name'] ?? ''));
            $code = '';
            foreach ($words as $w) {
                $letter = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $w), 0, 1));
                if ($letter) $code .= $letter;
            }
            return $code;
        }
        return '';
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $active_tab = sanitize_text_field($_GET['tab'] ?? 'product');
        if (!isset(self::$item_types[$active_tab])) $active_tab = 'product';

        $products       = $this->get_products($active_tab);
        $manufacturers  = $this->get_manufacturers();
        $bundled_cats   = $this->get_bundled_categories();

        ob_start(); ?>
        <div class="hm-admin">
            <div class="hm-admin-hd">
                <h2>Products &amp; Services</h2>
                <div style="display:flex;gap:8px;">
                    <button class="hm-btn" onclick="hmProd.showImport()">Import CSV</button>
                    <button class="hm-btn" onclick="hmProd.downloadTemplate()">Download Template</button>
                    <button class="hm-btn hm-btn-teal" onclick="hmProd.open()">+ Add <?php echo esc_html(rtrim(self::$item_types[$active_tab], 's')); ?></button>
                </div>
            </div>

            <!-- Category Tabs -->
            <div class="hm-tab-bar">
                <?php foreach (self::$item_types as $key => $label):
                    $active = ($key === $active_tab);
                ?>
                <a href="?tab=<?php echo esc_attr($key); ?>"
                   class="hm-tab<?php echo $active ? ' active' : ''; ?>">
                    <?php echo esc_html($label); ?>
                </a>
                <?php endforeach; ?>
            </div>

            <?php if (empty($products)): ?>
                <div class="hm-empty-state"><p>No <?php echo esc_html(strtolower(self::$item_types[$active_tab])); ?> yet.</p></div>
            <?php else: ?>
            <table class="hm-table">
                <thead>
                    <tr>
                        <?php if ($active_tab === 'product'): ?>
                            <th>Model</th><th>Brand</th><th>Code</th><th>Style</th><th>Tech Level</th>
                            <th class="hm-num">Cost</th><th class="hm-num">Retail</th>
                        <?php elseif ($active_tab === 'service'): ?>
                            <th>Name</th><th>Code</th><th class="hm-num">Retail</th>
                        <?php elseif ($active_tab === 'bundled'): ?>
                            <th>Name</th><th>Brand</th><th>Code</th><th>Category</th>
                            <th class="hm-num">Cost</th><th class="hm-num">Retail</th>
                        <?php endif; ?>
                        <th style="width:100px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p):
                    $row = json_encode((array) $p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                ?>
                    <tr>
                        <?php if ($active_tab === 'product'): ?>
                            <td><strong><?php echo esc_html($p->product_name); ?></strong></td>
                            <td><?php echo esc_html($p->manufacturer_name ?: '—'); ?></td>
                            <td><code style="font-size:11px;"><?php echo esc_html($p->product_code ?: '—'); ?></code></td>
                            <td><?php echo esc_html($p->category ?: '—'); ?></td>
                            <td><?php echo esc_html($p->tech_level ?: '—'); ?></td>
                            <td class="hm-num"><?php echo $p->cost_price !== null ? '€' . number_format((float) $p->cost_price, 2) : '—'; ?></td>
                            <td class="hm-num"><?php echo $p->retail_price !== null ? '€' . number_format((float) $p->retail_price, 2) : '—'; ?></td>
                        <?php elseif ($active_tab === 'service'): ?>
                            <td><strong><?php echo esc_html($p->product_name); ?></strong></td>
                            <td><code style="font-size:11px;"><?php echo esc_html($p->product_code ?: '—'); ?></code></td>
                            <td class="hm-num"><?php echo $p->retail_price !== null ? '€' . number_format((float) $p->retail_price, 2) : '—'; ?></td>
                        <?php elseif ($active_tab === 'bundled'): ?>
                            <td><strong><?php echo esc_html($p->product_name); ?></strong></td>
                            <td><?php echo esc_html($p->manufacturer_name ?: '—'); ?></td>
                            <td><code style="font-size:11px;"><?php echo esc_html($p->product_code ?: '—'); ?></code></td>
                            <td>
                                <?php
                                    $bcat = $p->bundled_category ?? ($p->style ?? '—');
                                    echo esc_html($bcat);
                                    if (strtolower($bcat) === 'speaker'):
                                        $len = $p->speaker_length ?? '';
                                        $pwr = $p->speaker_power ?? '';
                                        if ($len !== '' || $pwr !== ''):
                                ?>
                                    <span style="font-size:11px;color:var(--hm-text-light);margin-left:4px;">(<?php
                                        $parts = [];
                                        if ($len !== '') $parts[] = 'L' . $len;
                                        if ($pwr !== '') $parts[] = $pwr;
                                        echo esc_html(implode(' / ', $parts));
                                    ?>)</span>
                                <?php endif; endif; ?>
                            </td>
                            <td class="hm-num"><?php echo $p->cost_price !== null ? '€' . number_format((float) $p->cost_price, 2) : '—'; ?></td>
                            <td class="hm-num"><?php echo $p->retail_price !== null ? '€' . number_format((float) $p->retail_price, 2) : '—'; ?></td>
                        <?php endif; ?>
                        <td class="hm-table-acts">
                            <button class="hm-btn hm-btn-sm" onclick='hmProd.open(<?php echo $row; ?>)'>Edit</button>
                            <button class="hm-btn hm-btn-sm hm-btn-red" onclick="hmProd.del(<?php echo (int) $p->id; ?>,'<?php echo esc_js($p->product_name); ?>')">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- ========== ADD/EDIT MODAL ========== -->
            <div class="hm-modal-bg" id="hm-prod-modal">
                <div class="hm-modal" style="width:700px">
                    <div class="hm-modal-hd">
                        <h3 id="hm-prod-modal-title">Add Item</h3>
                        <button class="hm-modal-x" onclick="hmProd.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hmp-id">
                        <input type="hidden" id="hmp-item-type" value="<?php echo esc_attr($active_tab); ?>">

                        <!-- ===== HEARING AID FIELDS ===== -->
                        <div id="hmp-product-fields" class="hmp-type-fields" style="display:<?php echo $active_tab === 'product' ? 'block' : 'none'; ?>">
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Manufacturer *</label>
                                    <div style="display:flex;gap:4px;">
                                        <select id="hmp-manufacturer" style="flex:1" onchange="hmProd.genCode()">
                                            <option value="">Select brand</option>
                                            <?php foreach ($manufacturers as $m): ?>
                                                <option value="<?php echo (int) $m->id; ?>" data-name="<?php echo esc_attr($m->name); ?>"><?php echo esc_html($m->name); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="hm-form-group">
                                    <label>Model * <span style="font-size:11px;color:var(--hm-text-light);">(e.g. Allure M)</span></label>
                                    <input type="text" id="hmp-model" placeholder="e.g. Allure M" oninput="hmProd.genCode()">
                                </div>
                            </div>
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Style *</label>
                                    <select id="hmp-style" onchange="hmProd.genCode()">
                                        <option value="">Select</option>
                                        <?php foreach (self::$ha_styles as $s): ?>
                                            <option value="<?php echo esc_attr($s); ?>"><?php echo esc_html($s); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="hm-form-group">
                                    <label>Tech Level *</label>
                                    <input type="text" id="hmp-tech" placeholder="e.g. 330" oninput="hmProd.genCode()">
                                </div>
                            </div>
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Auto Code</label>
                                    <input type="text" id="hmp-code-ha" readonly style="background:#f1f5f9;font-family:monospace;font-weight:600;">
                                </div>
                            </div>
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Cost Price (€)</label>
                                    <input type="number" id="hmp-cost-ha" step="0.01" min="0" placeholder="0.00">
                                </div>
                                <div class="hm-form-group">
                                    <label>Retail Price (€)</label>
                                    <input type="number" id="hmp-retail-ha" step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <!-- ===== SERVICE FIELDS ===== -->
                        <div id="hmp-service-fields" class="hmp-type-fields" style="display:<?php echo $active_tab === 'service' ? 'block' : 'none'; ?>">
                            <div class="hm-form-row">
                                <div class="hm-form-group" style="flex:2">
                                    <label>Service Name *</label>
                                    <input type="text" id="hmp-svc-name" placeholder="e.g. Wax Removal" oninput="hmProd.genSvcCode()">
                                </div>
                                <div class="hm-form-group" style="flex:1">
                                    <label>Auto Code</label>
                                    <input type="text" id="hmp-code-svc" readonly style="background:#f1f5f9;font-family:monospace;font-weight:600;">
                                </div>
                            </div>
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Retail Price (€)</label>
                                    <input type="number" id="hmp-retail-svc" step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <!-- ===== BUNDLED ITEM FIELDS ===== -->
                        <div id="hmp-bundled-fields" class="hmp-type-fields" style="display:<?php echo $active_tab === 'bundled' ? 'block' : 'none'; ?>">
                            <div class="hm-form-row">
                                <div class="hm-form-group" style="flex:2">
                                    <label>Item Name *</label>
                                    <input type="text" id="hmp-bnd-name" placeholder="e.g. miniFit Speaker" oninput="hmProd.genBndCode()">
                                </div>
                                <div class="hm-form-group" style="flex:1">
                                    <label>Auto Code</label>
                                    <input type="text" id="hmp-code-bnd" readonly style="background:#f1f5f9;font-family:monospace;font-weight:600;">
                                </div>
                            </div>
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Manufacturer</label>
                                    <select id="hmp-bnd-mfr">
                                        <option value="">Select brand</option>
                                        <?php foreach ($manufacturers as $m): ?>
                                            <option value="<?php echo (int) $m->id; ?>"><?php echo esc_html($m->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="hm-form-group">
                                    <label>Category *</label>
                                    <div style="display:flex;gap:4px;">
                                        <select id="hmp-bnd-cat" style="flex:1" onchange="hmProd.toggleSpeaker()">
                                            <option value="">Select</option>
                                            <?php foreach ($bundled_cats as $bc): ?>
                                                <option value="<?php echo esc_attr($bc); ?>"><?php echo esc_html($bc); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="hm-btn hm-btn-sm" onclick="hmProd.addBundledCat()" title="Add new category" style="padding:4px 10px;">+</button>
                                    </div>
                                </div>
                            </div>
                            <!-- Speaker sub-fields (shown when category = Speaker) -->
                            <div id="hmp-speaker-fields" style="display:none;">
                                <div class="hm-form-row">
                                    <div class="hm-form-group">
                                        <label>Length</label>
                                        <select id="hmp-spk-length">
                                            <option value="">Select</option>
                                            <option value="0">0</option>
                                            <option value="1">1</option>
                                            <option value="2">2</option>
                                            <option value="3">3</option>
                                            <option value="4">4</option>
                                            <option value="5">5</option>
                                        </select>
                                    </div>
                                    <div class="hm-form-group">
                                        <label>Power</label>
                                        <select id="hmp-spk-power">
                                            <option value="">Select</option>
                                            <?php foreach (self::$speaker_powers as $val => $lbl): ?>
                                                <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($lbl); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Cost Price (€)</label>
                                    <input type="number" id="hmp-cost-bnd" step="0.01" min="0" placeholder="0.00">
                                </div>
                                <div class="hm-form-group">
                                    <label>Retail Price (€)</label>
                                    <input type="number" id="hmp-retail-bnd" step="0.01" min="0" placeholder="0.00">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmProd.close()">Cancel</button>
                        <button class="hm-btn hm-btn-teal" onclick="hmProd.save()" id="hmp-save-btn">Save</button>
                    </div>
                </div>
            </div>

            <!-- ========== IMPORT MODAL ========== -->
            <div class="hm-modal-bg" id="hm-import-modal">
                <div class="hm-modal" style="width:560px">
                    <div class="hm-modal-hd">
                        <h3>Import from CSV</h3>
                        <button class="hm-modal-x" onclick="document.getElementById('hm-import-modal').classList.remove('open')">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <p style="font-size:13px;color:var(--hm-text-light);margin-bottom:12px;">
                            Upload a CSV file matching the template for <strong><?php echo esc_html(self::$item_types[$active_tab]); ?></strong>.
                            Download the template first to see the expected columns.
                        </p>
                        <div class="hm-form-group">
                            <label>CSV File</label>
                            <input type="file" id="hmp-csv-file" accept=".csv,.txt">
                        </div>
                        <div id="hmp-import-preview" style="display:none;margin-top:12px;">
                            <p style="font-size:12px;"><strong id="hmp-import-count">0</strong> rows detected</p>
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="document.getElementById('hm-import-modal').classList.remove('open')">Cancel</button>
                        <button class="hm-btn hm-btn-teal" onclick="hmProd.doImport()" id="hmp-import-btn">Import</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        var hmProd = {
            activeTab: '<?php echo esc_js($active_tab); ?>',
            manufacturers: <?php echo json_encode(array_map(function($m) { return ['id'=>(int)$m->id,'name'=>$m->name]; }, $manufacturers)); ?>,

            // === Auto code generators ===
            genCode: function() {
                var sel = document.getElementById('hmp-manufacturer');
                var mfr = sel.options[sel.selectedIndex] ? (sel.options[sel.selectedIndex].getAttribute('data-name') || '') : '';
                var model = document.getElementById('hmp-model').value.trim();
                var tech = document.getElementById('hmp-tech').value.trim();
                var style = document.getElementById('hmp-style').value;
                var m2 = mfr.replace(/[^A-Za-z]/g, '').substring(0, 2).toUpperCase();
                var m1 = model.replace(/[^A-Za-z]/g, '').substring(0, 1).toUpperCase();
                var tn = tech.replace(/[^0-9]/g, '');
                var code = m2 + m1 + tn;
                if (style) code += ' (' + style + ')';
                document.getElementById('hmp-code-ha').value = code;
            },
            genSvcCode: function() {
                var name = document.getElementById('hmp-svc-name').value.trim();
                var words = name.split(/\s+/);
                var code = '';
                words.forEach(function(w) { var l = w.replace(/[^A-Za-z0-9]/g,'').substring(0,1).toUpperCase(); if(l) code += l; });
                document.getElementById('hmp-code-svc').value = code;
            },
            genBndCode: function() {
                var name = document.getElementById('hmp-bnd-name').value.trim();
                var words = name.split(/\s+/);
                var code = '';
                words.forEach(function(w) { var l = w.replace(/[^A-Za-z0-9]/g,'').substring(0,1).toUpperCase(); if(l) code += l; });
                document.getElementById('hmp-code-bnd').value = code;
            },

            toggleSpeaker: function() {
                var cat = document.getElementById('hmp-bnd-cat').value;
                document.getElementById('hmp-speaker-fields').style.display = (cat.toLowerCase() === 'speaker') ? 'block' : 'none';
            },

            addBundledCat: function() {
                var name = prompt('Enter new category name:');
                if (!name || !name.trim()) return;
                name = name.trim();
                jQuery.post(HM.ajax_url, { action:'hm_admin_add_bundled_category', nonce:HM.nonce, category_name:name }, function(r) {
                    if (r.success) {
                        var sel = document.getElementById('hmp-bnd-cat');
                        var opt = document.createElement('option');
                        opt.value = name; opt.textContent = name;
                        sel.insertBefore(opt, sel.lastElementChild);
                        sel.value = name;
                        hmProd.toggleSpeaker();
                    } else { alert(r.data || 'Error'); }
                });
            },

            // === Modal open/close ===
            open: function(data) {
                var isEdit = !!(data && data.id);
                var type = this.activeTab;
                var titles = {product:'Hearing Aid', service:'Service', bundled:'Bundled Item'};
                document.getElementById('hm-prod-modal-title').textContent = (isEdit ? 'Edit ' : 'Add ') + (titles[type] || 'Item');
                document.getElementById('hmp-id').value = isEdit ? data.id : '';
                document.getElementById('hmp-item-type').value = type;

                if (type === 'product') {
                    document.getElementById('hmp-manufacturer').value = isEdit && data.manufacturer_id ? data.manufacturer_id : '';
                    document.getElementById('hmp-model').value        = isEdit ? (data.product_name || '') : '';
                    document.getElementById('hmp-style').value        = isEdit ? (data.category || '') : '';
                    document.getElementById('hmp-tech').value         = isEdit ? (data.tech_level || '') : '';
                    document.getElementById('hmp-code-ha').value      = isEdit ? (data.product_code || '') : '';
                    document.getElementById('hmp-cost-ha').value      = isEdit && data.cost_price != null ? data.cost_price : '';
                    document.getElementById('hmp-retail-ha').value    = isEdit && data.retail_price != null ? data.retail_price : '';
                } else if (type === 'service') {
                    document.getElementById('hmp-svc-name').value     = isEdit ? (data.product_name || '') : '';
                    document.getElementById('hmp-code-svc').value     = isEdit ? (data.product_code || '') : '';
                    document.getElementById('hmp-retail-svc').value   = isEdit && data.retail_price != null ? data.retail_price : '';
                } else if (type === 'bundled') {
                    document.getElementById('hmp-bnd-name').value     = isEdit ? (data.product_name || '') : '';
                    document.getElementById('hmp-code-bnd').value     = isEdit ? (data.product_code || '') : '';
                    document.getElementById('hmp-bnd-mfr').value      = isEdit && data.manufacturer_id ? data.manufacturer_id : '';
                    document.getElementById('hmp-bnd-cat').value      = isEdit ? (data.bundled_category || data.style || '') : '';
                    document.getElementById('hmp-spk-length').value   = isEdit ? (data.speaker_length || '') : '';
                    document.getElementById('hmp-spk-power').value    = isEdit ? (data.speaker_power || '') : '';
                    document.getElementById('hmp-cost-bnd').value     = isEdit && data.cost_price != null ? data.cost_price : '';
                    document.getElementById('hmp-retail-bnd').value   = isEdit && data.retail_price != null ? data.retail_price : '';
                    hmProd.toggleSpeaker();
                }

                document.getElementById('hm-prod-modal').classList.add('open');
            },
            close: function() { document.getElementById('hm-prod-modal').classList.remove('open'); },

            // === Save ===
            save: function() {
                var type = document.getElementById('hmp-item-type').value;
                var payload = {
                    action: 'hm_admin_save_product',
                    nonce: HM.nonce,
                    id: document.getElementById('hmp-id').value,
                    item_type: type
                };

                if (type === 'product') {
                    var model = document.getElementById('hmp-model').value.trim();
                    if (!model) { alert('Model is required.'); return; }
                    if (!document.getElementById('hmp-manufacturer').value) { alert('Manufacturer is required.'); return; }
                    if (!document.getElementById('hmp-style').value) { alert('Style is required.'); return; }
                    payload.product_name    = model;
                    payload.manufacturer_id = document.getElementById('hmp-manufacturer').value;
                    payload.category        = document.getElementById('hmp-style').value;
                    payload.tech_level      = document.getElementById('hmp-tech').value;
                    payload.product_code    = document.getElementById('hmp-code-ha').value;
                    payload.cost_price      = document.getElementById('hmp-cost-ha').value;
                    payload.retail_price    = document.getElementById('hmp-retail-ha').value;
                } else if (type === 'service') {
                    var svcName = document.getElementById('hmp-svc-name').value.trim();
                    if (!svcName) { alert('Service name is required.'); return; }
                    payload.product_name  = svcName;
                    payload.product_code  = document.getElementById('hmp-code-svc').value;
                    payload.retail_price  = document.getElementById('hmp-retail-svc').value;
                } else if (type === 'bundled') {
                    var bndName = document.getElementById('hmp-bnd-name').value.trim();
                    if (!bndName) { alert('Item name is required.'); return; }
                    payload.product_name     = bndName;
                    payload.product_code     = document.getElementById('hmp-code-bnd').value;
                    payload.manufacturer_id  = document.getElementById('hmp-bnd-mfr').value;
                    payload.bundled_category = document.getElementById('hmp-bnd-cat').value;
                    payload.speaker_length   = document.getElementById('hmp-spk-length').value;
                    payload.speaker_power    = document.getElementById('hmp-spk-power').value;
                    payload.cost_price       = document.getElementById('hmp-cost-bnd').value;
                    payload.retail_price     = document.getElementById('hmp-retail-bnd').value;
                }

                var btn = document.getElementById('hmp-save-btn');
                btn.textContent = 'Saving...'; btn.disabled = true;
                jQuery.post(HM.ajax_url, payload, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error'); btn.textContent = 'Save'; btn.disabled = false; }
                });
            },

            del: function(id, name) {
                if (!confirm('Delete "' + name + '"?')) return;
                jQuery.post(HM.ajax_url, { action:'hm_admin_delete_product', nonce:HM.nonce, id:id }, function(r) {
                    if (r.success) location.reload();
                    else alert(r.data || 'Error');
                });
            },

            // === Import ===
            showImport: function() { document.getElementById('hm-import-modal').classList.add('open'); },
            doImport: function() {
                var fileInput = document.getElementById('hmp-csv-file');
                if (!fileInput.files.length) { alert('Please select a CSV file.'); return; }
                var fd = new FormData();
                fd.append('action', 'hm_admin_import_products');
                fd.append('nonce', HM.nonce);
                fd.append('item_type', hmProd.activeTab);
                fd.append('csv_file', fileInput.files[0]);
                var btn = document.getElementById('hmp-import-btn');
                btn.textContent = 'Importing...'; btn.disabled = true;
                jQuery.ajax({ url: HM.ajax_url, type:'POST', data:fd, processData:false, contentType:false,
                    success: function(r) {
                        if (r.success) { alert('Imported ' + (r.data.count || 0) + ' items.'); location.reload(); }
                        else { alert(r.data || 'Import error'); btn.textContent = 'Import'; btn.disabled = false; }
                    }
                });
            },
            downloadTemplate: function() {
                var type = hmProd.activeTab;
                var headers, filename;
                if (type === 'product') {
                    headers = 'manufacturer,model,style,tech_level,cost_price,retail_price';
                    filename = 'hearing_aids_template.csv';
                } else if (type === 'service') {
                    headers = 'service_name,retail_price';
                    filename = 'services_template.csv';
                } else {
                    headers = 'item_name,manufacturer,category,speaker_length,speaker_power,cost_price,retail_price';
                    filename = 'bundled_items_template.csv';
                }
                var blob = new Blob([headers + '\n'], {type:'text/csv'});
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob); a.download = filename; a.click();
            }
        };

        // CSV preview
        document.getElementById('hmp-csv-file').addEventListener('change', function() {
            var file = this.files[0];
            if (!file) return;
            var reader = new FileReader();
            reader.onload = function(e) {
                var lines = e.target.result.split('\n').filter(function(l) { return l.trim(); });
                var count = Math.max(0, lines.length - 1); // minus header
                document.getElementById('hmp-import-count').textContent = count;
                document.getElementById('hmp-import-preview').style.display = 'block';
            };
            reader.readAsText(file);
        });
        </script>
        <?php return ob_get_clean();
    }

    public function ajax_save() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        $id   = intval($_POST['id'] ?? 0);
        $name = sanitize_text_field($_POST['product_name'] ?? '');
        $type = sanitize_text_field($_POST['item_type'] ?? 'product');

        if (empty($name)) { wp_send_json_error('Name is required'); return; }

        $data = [
            'product_name' => $name,
            'product_code' => sanitize_text_field($_POST['product_code'] ?? ''),
            'item_type'    => $type,
            'is_active'    => true,
            'updated_at'   => current_time('mysql'),
        ];

        if ($type === 'product') {
            $data['manufacturer_id'] = !empty($_POST['manufacturer_id']) ? intval($_POST['manufacturer_id']) : null;
            $data['category']        = sanitize_text_field($_POST['category'] ?? '');   // Style (BTE/RIC/etc)
            $data['tech_level']      = sanitize_text_field($_POST['tech_level'] ?? ''); // Free text now
            $data['cost_price']      = $_POST['cost_price'] !== '' ? floatval($_POST['cost_price']) : null;
            $data['retail_price']    = $_POST['retail_price'] !== '' ? floatval($_POST['retail_price']) : null;
        } elseif ($type === 'service') {
            $data['retail_price'] = $_POST['retail_price'] !== '' ? floatval($_POST['retail_price']) : null;
        } elseif ($type === 'bundled') {
            $data['manufacturer_id']  = !empty($_POST['manufacturer_id']) ? intval($_POST['manufacturer_id']) : null;
            $data['bundled_category'] = sanitize_text_field($_POST['bundled_category'] ?? '');
            $data['speaker_length']   = sanitize_text_field($_POST['speaker_length'] ?? '');
            $data['speaker_power']    = sanitize_text_field($_POST['speaker_power'] ?? '');
            $data['cost_price']       = $_POST['cost_price'] !== '' ? floatval($_POST['cost_price']) : null;
            $data['retail_price']     = $_POST['retail_price'] !== '' ? floatval($_POST['retail_price']) : null;
        }

        $table = 'hearmed_reference.products';
        if ($id) {
            $result = HearMed_DB::update($table, $data, ['id' => $id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $result = HearMed_DB::insert($table, $data);
            $id = $result ?: 0;
        }

        if ($result === false) {
            wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
        } else {
            wp_send_json_success(['id' => $id]);
        }
    }

    public function ajax_delete() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        $id = intval($_POST['id'] ?? 0);
        if (!$id) { wp_send_json_error('Invalid ID'); return; }

        $result = HearMed_DB::update(
            'hearmed_reference.products',
            ['is_active' => false, 'updated_at' => current_time('mysql')],
            ['id' => $id]
        );

        if ($result === false) {
            wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
        } else {
            wp_send_json_success();
        }
    }

    public function ajax_import() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        if (empty($_FILES['csv_file'])) { wp_send_json_error('No file uploaded'); return; }

        $type = sanitize_text_field($_POST['item_type'] ?? 'product');
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');
        if (!$handle) { wp_send_json_error('Could not read file'); return; }

        $headers = fgetcsv($handle);
        if (!$headers) { fclose($handle); wp_send_json_error('Empty file'); return; }
        $headers = array_map('trim', array_map('strtolower', $headers));

        // Build manufacturer name→id map
        $mfrs = $this->get_manufacturers();
        $mfr_map = [];
        foreach ($mfrs as $m) $mfr_map[strtolower($m->name)] = (int)$m->id;

        $count = 0;
        $table = 'hearmed_reference.products';
        $now = current_time('mysql');

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 2) continue;
            $r = array_combine($headers, array_pad($row, count($headers), ''));

            $data = [
                'item_type'  => $type,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($type === 'product') {
                $mfr_name = trim($r['manufacturer'] ?? '');
                $mfr_id   = $mfr_map[strtolower($mfr_name)] ?? null;
                $data['manufacturer_id'] = $mfr_id;
                $data['product_name']    = trim($r['model'] ?? '');
                $data['category']        = trim($r['style'] ?? '');
                $data['tech_level']      = trim($r['tech_level'] ?? '');
                $data['cost_price']      = ($r['cost_price'] ?? '') !== '' ? floatval($r['cost_price']) : null;
                $data['retail_price']    = ($r['retail_price'] ?? '') !== '' ? floatval($r['retail_price']) : null;
                // Auto-gen code
                $data['product_code'] = self::generate_code('product', [
                    'manufacturer_name' => $mfr_name,
                    'model'             => $data['product_name'],
                    'tech_level'        => $data['tech_level'],
                    'style'             => $data['category'],
                ]);
            } elseif ($type === 'service') {
                $data['product_name']  = trim($r['service_name'] ?? '');
                $data['retail_price']  = ($r['retail_price'] ?? '') !== '' ? floatval($r['retail_price']) : null;
                $data['product_code']  = self::generate_code('service', ['name' => $data['product_name']]);
            } elseif ($type === 'bundled') {
                $mfr_name = trim($r['manufacturer'] ?? '');
                $mfr_id   = $mfr_map[strtolower($mfr_name)] ?? null;
                $data['manufacturer_id']  = $mfr_id;
                $data['product_name']     = trim($r['item_name'] ?? '');
                $data['bundled_category'] = trim($r['category'] ?? '');
                $data['speaker_length']   = trim($r['speaker_length'] ?? '');
                $data['speaker_power']    = trim($r['speaker_power'] ?? '');
                $data['cost_price']       = ($r['cost_price'] ?? '') !== '' ? floatval($r['cost_price']) : null;
                $data['retail_price']     = ($r['retail_price'] ?? '') !== '' ? floatval($r['retail_price']) : null;
                $data['product_code']     = self::generate_code('bundled', ['name' => $data['product_name']]);
            }

            if (!empty($data['product_name'])) {
                $result = HearMed_DB::insert($table, $data);
                if ($result) $count++;
            }
        }

        fclose($handle);
        wp_send_json_success(['count' => $count]);
    }

    public function ajax_add_bundled_category() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }

        $name = sanitize_text_field($_POST['category_name'] ?? '');
        if (!$name) { wp_send_json_error('Name required'); return; }

        // Try to insert (table may not exist yet)
        $check = HearMed_DB::get_var("SELECT to_regclass('hearmed_reference.bundled_categories')");
        if ($check === null) {
            // Create table on the fly
            HearMed_DB::get_results("CREATE TABLE IF NOT EXISTS hearmed_reference.bundled_categories (
                id BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
                category_name VARCHAR(100) NOT NULL UNIQUE,
                is_active BOOLEAN DEFAULT TRUE,
                sort_order INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            // Seed defaults
            HearMed_DB::get_results("INSERT INTO hearmed_reference.bundled_categories (category_name, sort_order) VALUES ('Speaker',1),('Dome',2),('Other',99) ON CONFLICT DO NOTHING");
        }

        $id = HearMed_DB::insert('hearmed_reference.bundled_categories', [
            'category_name' => $name,
            'created_at'    => current_time('mysql'),
        ]);

        if ($id) wp_send_json_success(['id' => $id]);
        else wp_send_json_error(HearMed_DB::last_error() ?: 'Error');
    }
}

new HearMed_Admin_Products();
