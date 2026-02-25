<?php
/**
 * HearMed Admin — Products & Services
 * Shortcode: [hearmed_products]
 * 5 categories: Products (Hearing Aids), Services, Bundled Items, Accessories, Consumables
 * All stored in hearmed_reference.products with item_type discriminator.
 *
 * @package HearMed_Portal
 * @since   5.1.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Products {

    private static $item_types = [
        'product'    => 'Products (Hearing Aids)',
        'service'    => 'Services',
        'bundled'    => 'Bundled Items',
        'accessory'  => 'Accessories',
        'consumable' => 'Consumables',
    ];

    private static $ha_categories  = ['BTE', 'RIC', 'ITE', 'ITC', 'CIC', 'IIC', 'CROS', 'BiCROS', 'Other'];
    private static $tech_levels    = ['Essential', 'Standard', 'Advanced', 'Premium'];

    public function __construct() {
        add_shortcode('hearmed_products', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_product', [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_product', [$this, 'ajax_delete']);
    }

    private function get_products($type = null) {
        $sql = "SELECT p.*, m.name AS manufacturer_name
                FROM hearmed_reference.products p
                LEFT JOIN hearmed_reference.manufacturers m ON p.manufacturer_id = m.id";
        $params = [];
        if ($type) {
            $sql .= " WHERE p.item_type = $1";
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

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $active_tab = sanitize_text_field($_GET['tab'] ?? 'product');
        if (!isset(self::$item_types[$active_tab])) $active_tab = 'product';

        $products      = $this->get_products($active_tab);
        $manufacturers = $this->get_manufacturers();

        ob_start(); ?>
        <div class="hm-admin">
            <div class="hm-admin-hd">
                <h2>Products &amp; Services</h2>
                <button class="hm-btn hm-btn-teal" onclick="hmProd.open()">+ Add Item</button>
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
                        <th>Name</th>
                        <?php if ($active_tab === 'product'): ?>
                            <th>Brand</th><th>Code</th><th>Category</th><th>Tech Level</th>
                        <?php elseif ($active_tab === 'service'): ?>
                            <th>Duration</th><th>Code</th>
                        <?php elseif ($active_tab === 'bundled'): ?>
                            <th>Brand</th><th>Code</th><th>Free with HA</th>
                        <?php else: ?>
                            <th>Brand</th><th>Code</th>
                        <?php endif; ?>
                        <th class="hm-num">Cost</th>
                        <th class="hm-num">Retail</th>
                        <th>Status</th>
                        <th style="width:100px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p):
                    $row = json_encode((array) $p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($p->product_name); ?></strong></td>
                        <?php if ($active_tab === 'product'): ?>
                            <td><?php echo esc_html($p->manufacturer_name ?: '—'); ?></td>
                            <td><?php echo esc_html($p->product_code ?: '—'); ?></td>
                            <td><?php echo esc_html($p->category ?: '—'); ?></td>
                            <td><?php echo esc_html($p->tech_level ?: '—'); ?></td>
                        <?php elseif ($active_tab === 'service'): ?>
                            <td><?php echo esc_html($p->style ?: '—'); ?> min</td>
                            <td><?php echo esc_html($p->product_code ?: '—'); ?></td>
                        <?php elseif ($active_tab === 'bundled'): ?>
                            <td><?php echo esc_html($p->manufacturer_name ?: '—'); ?></td>
                            <td><?php echo esc_html($p->product_code ?: '—'); ?></td>
                            <td><?php echo $p->tech_level === 'free' ? '<span class="hm-badge hm-badge-green">Yes</span>' : '<span class="hm-badge hm-badge-blue">No</span>'; ?></td>
                        <?php else: ?>
                            <td><?php echo esc_html($p->manufacturer_name ?: '—'); ?></td>
                            <td><?php echo esc_html($p->product_code ?: '—'); ?></td>
                        <?php endif; ?>
                        <td class="hm-num"><?php echo $p->cost_price !== null ? '€' . number_format((float) $p->cost_price, 2) : '—'; ?></td>
                        <td class="hm-num"><?php echo $p->retail_price !== null ? '€' . number_format((float) $p->retail_price, 2) : '—'; ?></td>
                        <td>
                            <?php if ($p->is_active): ?>
                                <span class="hm-badge hm-badge-green">Active</span>
                            <?php else: ?>
                                <span class="hm-badge hm-badge-red">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="hm-table-acts">
                            <button class="hm-btn hm-btn-sm" onclick='hmProd.open(<?php echo $row; ?>)'>Edit</button>
                            <button class="hm-btn hm-btn-sm hm-btn-red" onclick="hmProd.del(<?php echo (int) $p->id; ?>,'<?php echo esc_js($p->product_name); ?>')">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- Modal -->
            <div class="hm-modal-bg" id="hm-prod-modal">
                <div class="hm-modal" style="width:680px">
                    <div class="hm-modal-hd">
                        <h3 id="hm-prod-modal-title">Add Item</h3>
                        <button class="hm-modal-x" onclick="hmProd.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hmp-id">
                        <input type="hidden" id="hmp-item-type" value="<?php echo esc_attr($active_tab); ?>">

                        <div class="hm-form-row">
                            <div class="hm-form-group" style="flex:2">
                                <label>Name *</label>
                                <input type="text" id="hmp-name" placeholder="<?php
                                    echo $active_tab === 'product' ? 'e.g. Oticon Real 1' :
                                        ($active_tab === 'service' ? 'e.g. Hearing Test' :
                                        ($active_tab === 'bundled' ? 'e.g. miniFit Speaker' : 'e.g. TV Adapter'));
                                ?>">
                            </div>
                            <div class="hm-form-group" style="flex:1">
                                <label>Code</label>
                                <input type="text" id="hmp-code" placeholder="e.g. OT-R1">
                            </div>
                        </div>

                        <!-- Product-specific fields -->
                        <div id="hmp-product-fields" class="hmp-type-fields" style="display:<?php echo $active_tab === 'product' ? 'block' : 'none'; ?>">
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Manufacturer *</label>
                                    <select id="hmp-manufacturer">
                                        <option value="">Select brand</option>
                                        <?php foreach ($manufacturers as $m): ?>
                                            <option value="<?php echo (int) $m->id; ?>"><?php echo esc_html($m->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="hm-form-group">
                                    <label>Category *</label>
                                    <select id="hmp-category">
                                        <option value="">Select</option>
                                        <?php foreach (self::$ha_categories as $cat): ?>
                                            <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Style</label>
                                    <input type="text" id="hmp-style" placeholder="e.g. miniRITE T">
                                </div>
                                <div class="hm-form-group">
                                    <label>Tech Level</label>
                                    <select id="hmp-tech-level">
                                        <option value="">Select</option>
                                        <?php foreach (self::$tech_levels as $tl): ?>
                                            <option value="<?php echo esc_attr($tl); ?>"><?php echo esc_html($tl); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Service-specific fields -->
                        <div id="hmp-service-fields" class="hmp-type-fields" style="display:<?php echo $active_tab === 'service' ? 'block' : 'none'; ?>">
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Duration (minutes)</label>
                                    <input type="number" id="hmp-duration" min="5" step="5" placeholder="30">
                                </div>
                            </div>
                        </div>

                        <!-- Bundled-specific fields -->
                        <div id="hmp-bundled-fields" class="hmp-type-fields" style="display:<?php echo $active_tab === 'bundled' ? 'block' : 'none'; ?>">
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Manufacturer</label>
                                    <select id="hmp-bundled-mfr">
                                        <option value="">Select brand</option>
                                        <?php foreach ($manufacturers as $m): ?>
                                            <option value="<?php echo (int) $m->id; ?>"><?php echo esc_html($m->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="hm-form-group">
                                    <label class="hm-toggle-label" style="margin-top:22px;">
                                        <input type="checkbox" id="hmp-free-with-ha">
                                        Free with hearing aid
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Accessory/Consumable fields -->
                        <div id="hmp-acc-fields" class="hmp-type-fields" style="display:<?php echo in_array($active_tab, ['accessory','consumable']) ? 'block' : 'none'; ?>">
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Manufacturer</label>
                                    <select id="hmp-acc-mfr">
                                        <option value="">Select brand</option>
                                        <?php foreach ($manufacturers as $m): ?>
                                            <option value="<?php echo (int) $m->id; ?>"><?php echo esc_html($m->name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Common price fields -->
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Cost Price (€)</label>
                                <input type="number" id="hmp-cost" step="0.01" min="0" placeholder="0.00">
                            </div>
                            <div class="hm-form-group">
                                <label>Retail Price (€)</label>
                                <input type="number" id="hmp-retail" step="0.01" min="0" placeholder="0.00">
                            </div>
                        </div>

                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label class="hm-toggle-label">
                                    <input type="checkbox" id="hmp-active" checked>
                                    Active
                                </label>
                            </div>
                            <div class="hm-form-group">
                                <label>Discontinued Date</label>
                                <input type="date" id="hmp-discontinued">
                            </div>
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmProd.close()">Cancel</button>
                        <button class="hm-btn hm-btn-teal" onclick="hmProd.save()" id="hmp-save-btn">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        var hmProd = {
            activeTab: '<?php echo esc_js($active_tab); ?>',
            open: function(data) {
                var isEdit = !!(data && data.id);
                var type = this.activeTab;
                document.getElementById('hm-prod-modal-title').textContent = isEdit ? 'Edit Item' : 'Add Item';
                document.getElementById('hmp-id').value            = isEdit ? data.id : '';
                document.getElementById('hmp-item-type').value     = isEdit ? (data.item_type || type) : type;
                document.getElementById('hmp-name').value          = data && data.product_name ? data.product_name : '';
                document.getElementById('hmp-code').value          = data && data.product_code ? data.product_code : '';
                document.getElementById('hmp-cost').value          = data && data.cost_price != null ? data.cost_price : '';
                document.getElementById('hmp-retail').value        = data && data.retail_price != null ? data.retail_price : '';
                document.getElementById('hmp-active').checked      = data ? !!data.is_active : true;
                document.getElementById('hmp-discontinued').value  = data && data.discontinued_date ? data.discontinued_date : '';

                // Product fields
                document.getElementById('hmp-manufacturer').value  = data && data.manufacturer_id ? data.manufacturer_id : '';
                document.getElementById('hmp-category').value      = data && data.category ? data.category : '';
                document.getElementById('hmp-style').value         = data && data.style ? data.style : '';
                document.getElementById('hmp-tech-level').value    = data && data.tech_level ? data.tech_level : '';

                // Service fields
                document.getElementById('hmp-duration').value      = data && data.style ? data.style : '';

                // Bundled fields
                document.getElementById('hmp-bundled-mfr').value   = data && data.manufacturer_id ? data.manufacturer_id : '';
                document.getElementById('hmp-free-with-ha').checked = data && data.tech_level === 'free';

                // Acc/Consumable fields
                document.getElementById('hmp-acc-mfr').value       = data && data.manufacturer_id ? data.manufacturer_id : '';

                document.getElementById('hm-prod-modal').classList.add('open');
                document.getElementById('hmp-name').focus();
            },
            close: function() { document.getElementById('hm-prod-modal').classList.remove('open'); },
            save: function() {
                var name = document.getElementById('hmp-name').value.trim();
                var type = document.getElementById('hmp-item-type').value;
                if (!name) { alert('Name is required.'); return; }

                var payload = {
                    action: 'hm_admin_save_product',
                    nonce: HM.nonce,
                    id: document.getElementById('hmp-id').value,
                    item_type: type,
                    product_name: name,
                    product_code: document.getElementById('hmp-code').value,
                    cost_price: document.getElementById('hmp-cost').value,
                    retail_price: document.getElementById('hmp-retail').value,
                    is_active: document.getElementById('hmp-active').checked ? 1 : 0,
                    discontinued_date: document.getElementById('hmp-discontinued').value
                };

                if (type === 'product') {
                    payload.manufacturer_id = document.getElementById('hmp-manufacturer').value;
                    payload.category = document.getElementById('hmp-category').value;
                    payload.style = document.getElementById('hmp-style').value;
                    payload.tech_level = document.getElementById('hmp-tech-level').value;
                    if (!payload.manufacturer_id) { alert('Manufacturer is required.'); return; }
                    if (!payload.category) { alert('Category is required.'); return; }
                } else if (type === 'service') {
                    payload.style = document.getElementById('hmp-duration').value;
                } else if (type === 'bundled') {
                    payload.manufacturer_id = document.getElementById('hmp-bundled-mfr').value;
                    payload.tech_level = document.getElementById('hmp-free-with-ha').checked ? 'free' : '';
                } else {
                    payload.manufacturer_id = document.getElementById('hmp-acc-mfr').value;
                }

                var btn = document.getElementById('hmp-save-btn');
                btn.textContent = 'Saving...'; btn.disabled = true;

                jQuery.post(HM.ajax_url, payload, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error'); btn.textContent = 'Save'; btn.disabled = false; }
                });
            },
            del: function(id, name) {
                if (!confirm('Deactivate "' + name + '"?')) return;
                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_delete_product',
                    nonce: HM.nonce,
                    id: id
                }, function(r) {
                    if (r.success) location.reload();
                    else alert(r.data || 'Error');
                });
            }
        };
        </script>
        <?php return ob_get_clean();
    }

    public function ajax_save() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        $id   = intval($_POST['id'] ?? 0);
        $name = sanitize_text_field($_POST['product_name'] ?? '');
        $type = sanitize_text_field($_POST['item_type'] ?? 'product');

        if (empty($name)) {
            wp_send_json_error('Name is required');
            return;
        }

        $data = [
            'product_name'      => $name,
            'product_code'      => sanitize_text_field($_POST['product_code'] ?? ''),
            'item_type'         => $type,
            'manufacturer_id'   => !empty($_POST['manufacturer_id']) ? intval($_POST['manufacturer_id']) : null,
            'category'          => sanitize_text_field($_POST['category'] ?? ''),
            'style'             => sanitize_text_field($_POST['style'] ?? ''),
            'tech_level'        => sanitize_text_field($_POST['tech_level'] ?? ''),
            'cost_price'        => $_POST['cost_price'] !== '' ? floatval($_POST['cost_price']) : null,
            'retail_price'      => $_POST['retail_price'] !== '' ? floatval($_POST['retail_price']) : null,
            'is_active'         => intval($_POST['is_active'] ?? 1),
            'discontinued_date' => !empty($_POST['discontinued_date']) ? sanitize_text_field($_POST['discontinued_date']) : null,
            'updated_at'        => current_time('mysql'),
        ];

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
}

new HearMed_Admin_Products();
