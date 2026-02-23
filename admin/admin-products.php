<?php
/**
 * HearMed Admin — Manage Products
 * Shortcode: [hearmed_manage_products]
 * CRUD for ha-product CPT
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Products {

    private $meta_fields = [
        'item_code','manufacturer','model','style','tech_level',
        'hearmed_range','cost_price','retail_price','vat_rate',
        'product_category','receivers','gain_options','earbud_size',
        'earbud_type','power','product_image','style_icon',
    ];

    private $categories = [
        'Hearing Aids' => '0',
        'Accessories' => '0',
        'Services' => '13.5',
        'Consumables' => '23',
        'Other Audiological Services' => '13.5',
    ];

    private $styles = ['RIC','CIC','ITE','BTE','IIC','CROS','BiCROS','Body Worn','Other'];

    public function __construct() {
        add_shortcode('hearmed_manage_products', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_product', [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_product', [$this, 'ajax_delete']);
    }

    private function get_products() {
        $posts = /* USE PostgreSQL: HearMed_DB::get_results() */ /* get_posts([
            'post_type' => 'ha-product',
        $products = [];
        foreach ($posts as $p) {
            $d = ['id' => $p->ID, 'name' => $p->post_title];
            foreach ($this->meta_fields as $f) {
                $d[$f] = /* USE PostgreSQL: Get from table columns */ /* get_post_meta($p->ID, $f, true);
            }
            $products[] = $d;
        }
        return $products;
    }

    private function get_taxonomy_terms($taxonomy) {
        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false, 'orderby' => 'name']);
        if (is_wp_error($terms)) return [];
        return array_map(function($t) { return ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug]; }, $terms);
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $products = $this->get_products();
        $manufacturers = $this->get_taxonomy_terms('manufacturer');
        $ranges = $this->get_taxonomy_terms('hearmed-range');

        ob_start(); ?>
        <div class="hm-admin" id="hm-products-app">
            <div class="hm-admin-hd">
                <h2>Products & Services</h2>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="text" id="hmp-search" placeholder="Search products..." class="hm-search-input" oninput="hmProd.filter()">
                    <select id="hmp-cat-filter" onchange="hmProd.filter()" class="hm-filter-select">
                        <option value="">All Categories</option>
                        <?php foreach (array_keys($this->categories) as $cat): ?>
                        <option value="<?php echo esc_attr($cat); ?>"><?php echo esc_html($cat); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="hm-btn hm-btn-teal" onclick="hmProd.open()">+ Add Product</button>
                </div>
            </div>

            <?php if (empty($products)): ?>
                <div class="hm-empty-state"><p>No products yet. Add your first product to get started.</p></div>
            <?php else: ?>
            <table class="hm-table" id="hmp-table">
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Manufacturer</th>
                        <th>Style</th>
                        <th>Range</th>
                        <th class="hm-num">Cost</th>
                        <th class="hm-num">Retail</th>
                        <th class="hm-num">VAT</th>
                        <th class="hm-num">Margin</th>
                        <th style="width:100px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p):
                    $cost = floatval($p['cost_price'] ?? 0);
                    $retail = floatval($p['retail_price'] ?? 0);
                    $margin = $retail > 0 ? round((($retail - $cost) / $retail) * 100, 1) : 0;
                    $margin_cls = $margin >= 70 ? 'green' : ($margin >= 50 ? 'blue' : ($margin >= 30 ? 'amber' : 'red'));
                ?>
                    <tr data-cat="<?php echo esc_attr($p['product_category'] ?? ''); ?>" data-search="<?php echo esc_attr(strtolower($p['name'] . ' ' . ($p['item_code'] ?? '') . ' ' . ($p['manufacturer'] ?? ''))); ?>">
                        <td><code><?php echo esc_html($p['item_code'] ?? '—'); ?></code></td>
                        <td><strong><?php echo esc_html($p['name']); ?></strong></td>
                        <td><?php echo esc_html($p['product_category'] ?? '—'); ?></td>
                        <td><?php echo esc_html($p['manufacturer'] ?? '—'); ?></td>
                        <td><?php echo esc_html($p['style'] ?? '—'); ?></td>
                        <td><?php echo esc_html($p['hearmed_range'] ?? '—'); ?></td>
                        <td class="hm-num"><?php echo $cost ? '€' . number_format($cost, 2) : '—'; ?></td>
                        <td class="hm-num"><?php echo $retail ? '€' . number_format($retail, 2) : '—'; ?></td>
                        <td class="hm-num"><?php echo esc_html(($p['vat_rate'] ?? '0') . '%'); ?></td>
                        <td class="hm-num"><span class="hm-badge hm-badge-<?php echo $margin_cls; ?>"><?php echo $margin; ?>%</span></td>
                        <td class="hm-table-acts">
                            <button class="hm-btn hm-btn-sm" onclick='hmProd.open(<?php echo json_encode($p); ?>)'>Edit</button>
                            <button class="hm-btn hm-btn-sm hm-btn-red" onclick="hmProd.del(<?php echo $p['id']; ?>,'<?php echo esc_js($p['name']); ?>')">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div class="hm-table-count" id="hmp-count"><?php echo count($products); ?> products</div>
            <?php endif; ?>

            <!-- Modal -->
            <div class="hm-modal-bg" id="hm-product-modal">
                <div class="hm-modal" style="width:680px">
                    <div class="hm-modal-hd">
                        <h3 id="hm-product-modal-title">Add Product</h3>
                        <button class="hm-modal-x" onclick="hmProd.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hmpf-id" value="">

                        <div class="hm-form-row">
                            <div class="hm-form-group" style="flex:2">
                                <label>Product Name *</label>
                                <input type="text" id="hmpf-name" placeholder="e.g. Widex Moment 440 RIC">
                            </div>
                            <div class="hm-form-group">
                                <label>Item Code</label>
                                <input type="text" id="hmpf-item-code" placeholder="SKU / Code">
                            </div>
                        </div>

                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Category *</label>
                                <select id="hmpf-category" onchange="hmProd.catChange()">
                                    <?php foreach ($this->categories as $cat => $vat): ?>
                                    <option value="<?php echo esc_attr($cat); ?>" data-vat="<?php echo $vat; ?>"><?php echo esc_html($cat); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="hm-form-group">
                                <label>Manufacturer</label>
                                <select id="hmpf-manufacturer">
                                    <option value="">— None —</option>
                                    <?php foreach ($manufacturers as $m): ?>
                                    <option value="<?php echo esc_attr($m['name']); ?>"><?php echo esc_html($m['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Style</label>
                                <select id="hmpf-style">
                                    <option value="">— None —</option>
                                    <?php foreach ($this->styles as $s): ?>
                                    <option value="<?php echo esc_attr($s); ?>"><?php echo esc_html($s); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="hm-form-group">
                                <label>Tech Level</label>
                                <input type="text" id="hmpf-tech-level" placeholder="e.g. 440, 330, Premium">
                            </div>
                            <div class="hm-form-group">
                                <label>HearMed Range</label>
                                <select id="hmpf-range">
                                    <option value="">— None —</option>
                                    <?php foreach ($ranges as $r): ?>
                                    <option value="<?php echo esc_attr($r['name']); ?>"><?php echo esc_html($r['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Cost Price (€)</label>
                                <input type="number" id="hmpf-cost" step="0.01" min="0" placeholder="0.00" oninput="hmProd.calcMargin()">
                            </div>
                            <div class="hm-form-group">
                                <label>Retail Price (€)</label>
                                <input type="number" id="hmpf-retail" step="0.01" min="0" placeholder="0.00" oninput="hmProd.calcMargin()">
                            </div>
                            <div class="hm-form-group hm-form-sm">
                                <label>VAT Rate (%)</label>
                                <input type="number" id="hmpf-vat" step="0.5" min="0" placeholder="0">
                            </div>
                            <div class="hm-form-group hm-form-sm">
                                <label>Margin</label>
                                <div id="hmpf-margin" class="hm-margin-display">—</div>
                            </div>
                        </div>

                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Model</label>
                                <input type="text" id="hmpf-model" placeholder="Model name">
                            </div>
                            <div class="hm-form-group">
                                <label>Receivers</label>
                                <input type="text" id="hmpf-receivers" placeholder="e.g. S, M, P, UP">
                            </div>
                        </div>

                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Earbud Size</label>
                                <input type="text" id="hmpf-earbud-size" placeholder="e.g. Small, Medium">
                            </div>
                            <div class="hm-form-group">
                                <label>Earbud Type</label>
                                <input type="text" id="hmpf-earbud-type" placeholder="e.g. Open, Closed, Power">
                            </div>
                            <div class="hm-form-group">
                                <label>Power</label>
                                <input type="text" id="hmpf-power" placeholder="e.g. 312, 13, Rechargeable">
                            </div>
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmProd.close()">Cancel</button>
                        <button class="hm-btn hm-btn-teal" onclick="hmProd.save()" id="hmpf-save-btn">Save Product</button>
                    </div>
                </div>
            </div>
        </div>


        <script>
        var hmProd = {
            open: function(data) {
                var isEdit = data && data.id;
                document.getElementById('hm-product-modal-title').textContent = isEdit ? 'Edit Product' : 'Add Product';
                document.getElementById('hmpf-id').value = isEdit ? data.id : '';
                document.getElementById('hmpf-name').value = isEdit ? data.name : '';
                document.getElementById('hmpf-item-code').value = isEdit ? (data.item_code || '') : '';
                document.getElementById('hmpf-category').value = isEdit ? (data.product_category || 'Hearing Aids') : 'Hearing Aids';
                document.getElementById('hmpf-manufacturer').value = isEdit ? (data.manufacturer || '') : '';
                document.getElementById('hmpf-style').value = isEdit ? (data.style || '') : '';
                document.getElementById('hmpf-tech-level').value = isEdit ? (data.tech_level || '') : '';
                document.getElementById('hmpf-range').value = isEdit ? (data.hearmed_range || '') : '';
                document.getElementById('hmpf-cost').value = isEdit ? (data.cost_price || '') : '';
                document.getElementById('hmpf-retail').value = isEdit ? (data.retail_price || '') : '';
                document.getElementById('hmpf-vat').value = isEdit ? (data.vat_rate || '0') : '0';
                document.getElementById('hmpf-model').value = isEdit ? (data.model || '') : '';
                document.getElementById('hmpf-receivers').value = isEdit ? (data.receivers || '') : '';
                document.getElementById('hmpf-earbud-size').value = isEdit ? (data.earbud_size || '') : '';
                document.getElementById('hmpf-earbud-type').value = isEdit ? (data.earbud_type || '') : '';
                document.getElementById('hmpf-power').value = isEdit ? (data.power || '') : '';

                if (!isEdit) this.catChange();
                this.calcMargin();
                document.getElementById('hm-product-modal').classList.add('open');
            },

            close: function() {
                document.getElementById('hm-product-modal').classList.remove('open');
            },

            catChange: function() {
                var sel = document.getElementById('hmpf-category');
                var opt = sel.options[sel.selectedIndex];
                if (opt && opt.dataset.vat !== undefined) {
                    document.getElementById('hmpf-vat').value = opt.dataset.vat;
                }
            },

            calcMargin: function() {
                var cost = parseFloat(document.getElementById('hmpf-cost').value) || 0;
                var retail = parseFloat(document.getElementById('hmpf-retail').value) || 0;
                var el = document.getElementById('hmpf-margin');
                if (retail > 0 && cost > 0) {
                    var m = ((retail - cost) / retail * 100).toFixed(1);
                    el.textContent = m + '%';
                    el.style.color = m >= 70 ? '#059669' : m >= 50 ? '#3b82f6' : m >= 30 ? '#d97706' : '#ef4444';
                } else {
                    el.textContent = '—';
                    el.style.color = '';
                }
            },

            filter: function() {
                var q = (document.getElementById('hmp-search').value || '').toLowerCase();
                var cat = document.getElementById('hmp-cat-filter').value;
                var rows = document.querySelectorAll('#hmp-table tbody tr');
                var vis = 0;
                rows.forEach(function(tr) {
                    var matchSearch = !q || (tr.dataset.search || '').indexOf(q) !== -1;
                    var matchCat = !cat || tr.dataset.cat === cat;
                    tr.style.display = (matchSearch && matchCat) ? '' : 'none';
                    if (matchSearch && matchCat) vis++;
                });
                document.getElementById('hmp-count').textContent = vis + ' products';
            },

            save: function() {
                var name = document.getElementById('hmpf-name').value.trim();
                if (!name) { alert('Product name is required.'); return; }

                var btn = document.getElementById('hmpf-save-btn');
                btn.textContent = 'Saving...';
                btn.disabled = true;

                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_save_product',
                    nonce: HM.nonce,
                    id: document.getElementById('hmpf-id').value,
                    name: name,
                    item_code: document.getElementById('hmpf-item-code').value,
                    product_category: document.getElementById('hmpf-category').value,
                    manufacturer: document.getElementById('hmpf-manufacturer').value,
                    style: document.getElementById('hmpf-style').value,
                    tech_level: document.getElementById('hmpf-tech-level').value,
                    hearmed_range: document.getElementById('hmpf-range').value,
                    cost_price: document.getElementById('hmpf-cost').value,
                    retail_price: document.getElementById('hmpf-retail').value,
                    vat_rate: document.getElementById('hmpf-vat').value,
                    model: document.getElementById('hmpf-model').value,
                    receivers: document.getElementById('hmpf-receivers').value,
                    earbud_size: document.getElementById('hmpf-earbud-size').value,
                    earbud_type: document.getElementById('hmpf-earbud-type').value,
                    power: document.getElementById('hmpf-power').value
                }, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error saving.'); btn.textContent = 'Save Product'; btn.disabled = false; }
                });
            },

            del: function(id, name) {
                if (!confirm('Delete "' + name + '"? This cannot be undone.')) return;
                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_delete_product',
                    nonce: HM.nonce,
                    id: id
                }, function(r) {
                    if (r.success) location.reload();
                    else alert(r.data || 'Error deleting.');
                });
            }
        };
        </script>
        <?php
        return ob_get_clean();
    }

    public function ajax_save() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        $id = intval($_POST['id'] ?? 0);
        $name = sanitize_text_field($_POST['name'] ?? '');
        if (empty($name)) { wp_send_json_error('Name required'); return; }

        if ($id) {
            wp_update_post(['ID' => $id, 'post_title' => $name]);
        } else {
            $id = /* USE PostgreSQL: HearMed_DB::insert() */ /* wp_insert_post([
                'post_type' => 'ha-product',
                'post_title' => $name,
                'post_status' => 'publish',
            ]);
            if (is_wp_error($id)) { wp_send_json_error('Failed to create product'); return; }
        }

        foreach ($this->meta_fields as $f) {
            $post_key = $f;
            if (isset($_POST[$f])) {
                update_post_meta($id, $f, sanitize_text_field($_POST[$f]));
            }
        }

        wp_send_json_success(['id' => $id]);
    }

    public function ajax_delete() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        $id = intval($_POST['id'] ?? 0);
        if ($id) wp_delete_post($id, true);
        wp_send_json_success();
    }
}

new HearMed_Admin_Products();
