<?php
/**
 * HearMed Admin — Products (Hearing Aids & Accessories)
 * Shortcode: [hearmed_products]
 * CRUD for hearmed_reference.products with manufacturer lookup
 *
 * @package HearMed_Portal
 * @since   5.0.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Products {

    public function __construct() {
        add_shortcode('hearmed_products', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_product', [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_product', [$this, 'ajax_delete']);
    }

    private function get_products() {
        return HearMed_DB::get_results(
            "SELECT p.*, m.name AS manufacturer_name
             FROM hearmed_reference.products p
             LEFT JOIN hearmed_reference.manufacturers m ON p.manufacturer_id = m.id
             ORDER BY m.name, p.product_name"
        ) ?: [];
    }

    private function get_manufacturers() {
        return HearMed_DB::get_results(
            "SELECT id, name FROM hearmed_reference.manufacturers WHERE is_active = true ORDER BY name"
        ) ?: [];
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $products = $this->get_products();
        $manufacturers = $this->get_manufacturers();

        $categories  = ['BTE', 'RIC', 'ITE', 'ITC', 'CIC', 'IIC', 'CROS', 'BiCROS', 'Accessory', 'Other'];
        $tech_levels = ['Essential', 'Standard', 'Advanced', 'Premium'];

        ob_start(); ?>
        <div class="hm-admin">
            <div class="hm-admin-hd">
                <h2>Products</h2>
                <button class="hm-btn hm-btn-teal" onclick="hmProd.open()">+ Add Product</button>
            </div>

            <?php if (empty($products)): ?>
                <div class="hm-empty-state"><p>No products yet. Click "+ Add Product" to get started.</p></div>
            <?php else: ?>
            <table class="hm-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Brand</th>
                        <th>Code</th>
                        <th>Category</th>
                        <th>Tech Level</th>
                        <th class="hm-num">Cost</th>
                        <th class="hm-num">Retail</th>
                        <th class="hm-num">Margin</th>
                        <th>Status</th>
                        <th style="width:100px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($products as $p):
                    $margin = '';
                    if ($p->cost_price > 0 && $p->retail_price > 0) {
                        $margin = round((($p->retail_price - $p->cost_price) / $p->retail_price) * 100, 1) . '%';
                    }
                    $row     = (array) $p;
                    $payload = json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($p->product_name); ?></strong></td>
                        <td><?php echo esc_html($p->manufacturer_name ?: '—'); ?></td>
                        <td><?php echo esc_html($p->product_code ?: '—'); ?></td>
                        <td><?php echo esc_html($p->category ?: '—'); ?></td>
                        <td><?php echo esc_html($p->tech_level ?: '—'); ?></td>
                        <td class="hm-num"><?php echo $p->cost_price !== null ? '€' . number_format((float) $p->cost_price, 2) : '—'; ?></td>
                        <td class="hm-num"><?php echo $p->retail_price !== null ? '€' . number_format((float) $p->retail_price, 2) : '—'; ?></td>
                        <td class="hm-num">
                            <?php if ($margin): ?>
                                <span class="hm-margin-display"><?php echo esc_html($margin); ?></span>
                            <?php else: echo '—'; endif; ?>
                        </td>
                        <td>
                            <?php if ($p->is_active): ?>
                                <span class="hm-badge hm-badge-green">Active</span>
                            <?php elseif ($p->discontinued_date): ?>
                                <span class="hm-badge hm-badge-red">Discontinued</span>
                            <?php else: ?>
                                <span class="hm-badge hm-badge-red">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="hm-table-acts">
                            <button class="hm-btn hm-btn-sm" onclick='hmProd.open(<?php echo $payload; ?>)'>Edit</button>
                            <button class="hm-btn hm-btn-sm hm-btn-red" onclick="hmProd.del(<?php echo (int) $p->id; ?>,'<?php echo esc_js($p->product_name); ?>')">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- Modal -->
            <div class="hm-modal-bg" id="hm-prod-modal">
                <div class="hm-modal" style="width:640px">
                    <div class="hm-modal-hd">
                        <h3 id="hm-prod-modal-title">Add Product</h3>
                        <button class="hm-modal-x" onclick="hmProd.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hmp-id">

                        <div class="hm-form-row">
                            <div class="hm-form-group" style="flex:2">
                                <label>Product Name *</label>
                                <input type="text" id="hmp-name" placeholder="e.g. Oticon Real 1">
                            </div>
                            <div class="hm-form-group" style="flex:1">
                                <label>Product Code</label>
                                <input type="text" id="hmp-code" placeholder="e.g. OT-R1-BTE">
                            </div>
                        </div>

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
                                    <option value="">Select category</option>
                                    <?php foreach ($categories as $cat): ?>
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
                                    <option value="">Select level</option>
                                    <?php foreach ($tech_levels as $tl): ?>
                                        <option value="<?php echo esc_attr($tl); ?>"><?php echo esc_html($tl); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

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
            open: function(data) {
                var isEdit = !!(data && data.id);
                document.getElementById('hm-prod-modal-title').textContent = isEdit ? 'Edit Product' : 'Add Product';
                document.getElementById('hmp-id').value           = isEdit ? data.id : '';
                document.getElementById('hmp-name').value         = data && data.product_name ? data.product_name : '';
                document.getElementById('hmp-code').value         = data && data.product_code ? data.product_code : '';
                document.getElementById('hmp-manufacturer').value = data && data.manufacturer_id ? data.manufacturer_id : '';
                document.getElementById('hmp-category').value     = data && data.category ? data.category : '';
                document.getElementById('hmp-style').value        = data && data.style ? data.style : '';
                document.getElementById('hmp-tech-level').value   = data && data.tech_level ? data.tech_level : '';
                document.getElementById('hmp-cost').value         = data && data.cost_price != null ? data.cost_price : '';
                document.getElementById('hmp-retail').value       = data && data.retail_price != null ? data.retail_price : '';
                document.getElementById('hmp-active').checked     = data ? !!data.is_active : true;
                document.getElementById('hmp-discontinued').value = data && data.discontinued_date ? data.discontinued_date : '';

                document.getElementById('hm-prod-modal').classList.add('open');
                document.getElementById('hmp-name').focus();
            },
            close: function() { document.getElementById('hm-prod-modal').classList.remove('open'); },
            save: function() {
                var name = document.getElementById('hmp-name').value.trim();
                var mfr  = document.getElementById('hmp-manufacturer').value;
                var cat  = document.getElementById('hmp-category').value;
                if (!name) { alert('Product name is required.'); return; }
                if (!mfr)  { alert('Manufacturer is required.'); return; }
                if (!cat)  { alert('Category is required.'); return; }

                var btn = document.getElementById('hmp-save-btn');
                btn.textContent = 'Saving...'; btn.disabled = true;

                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_save_product',
                    nonce: HM.nonce,
                    id: document.getElementById('hmp-id').value,
                    product_name: name,
                    product_code: document.getElementById('hmp-code').value,
                    manufacturer_id: mfr,
                    category: cat,
                    style: document.getElementById('hmp-style').value,
                    tech_level: document.getElementById('hmp-tech-level').value,
                    cost_price: document.getElementById('hmp-cost').value,
                    retail_price: document.getElementById('hmp-retail').value,
                    is_active: document.getElementById('hmp-active').checked ? 1 : 0,
                    discontinued_date: document.getElementById('hmp-discontinued').value
                }, function(r) {
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
        $mfr  = intval($_POST['manufacturer_id'] ?? 0);
        $cat  = sanitize_text_field($_POST['category'] ?? '');

        if (empty($name) || !$mfr || empty($cat)) {
            wp_send_json_error('Product name, manufacturer and category are required');
            return;
        }

        $data = [
            'product_name'      => $name,
            'product_code'      => sanitize_text_field($_POST['product_code'] ?? ''),
            'manufacturer_id'   => $mfr,
            'category'          => $cat,
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

        // Soft delete
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
