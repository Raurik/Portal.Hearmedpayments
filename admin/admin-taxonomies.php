<?php
/**
 * HearMed Admin — Reference Data Managers
 * Shortcodes: [hearmed_brands], [hearmed_range_settings], [hearmed_lead_types]
 * PostgreSQL CRUD for reference tables
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Taxonomies {

    private $configs = [
        'hearmed_brands' => [
            'table' => 'hearmed_reference.manufacturers',
            'title' => 'Brands / Manufacturers',
            'singular' => 'Brand',
        ],
        'hearmed_range_settings' => [
            'table' => 'hearmed_reference.hearmed_range',
            'title' => 'HearMed Range',
            'singular' => 'Range',
        ],
        'hearmed_lead_types' => [
            'table' => 'hearmed_reference.referral_sources',
            'title' => 'Lead Types / Referral Sources',
            'singular' => 'Source',
        ],
    ];

    public function __construct() {
        foreach (array_keys($this->configs) as $sc) {
            add_shortcode($sc, [$this, 'render']);
        }
        add_action('wp_ajax_hm_admin_save_term', [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_term', [$this, 'ajax_delete']);
    }

    public function render($atts, $content, $tag) {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';
        $cfg = $this->configs[$tag] ?? null;
        if (!$cfg) return '<p>Unknown reference table.</p>';

        $rows = [];
        $parents = [];

        if ($tag === 'hearmed_brands') {
            $rows = HearMed_DB::get_results(
                "SELECT id, name, country, website, support_phone, support_email, is_active
                 FROM hearmed_reference.manufacturers
                 WHERE is_active = true
                 ORDER BY name"
            ) ?: [];
        } elseif ($tag === 'hearmed_range_settings') {
            $rows = HearMed_DB::get_results(
                "SELECT id, range_name, price_total, price_ex_prsi, is_active
                 FROM hearmed_reference.hearmed_range
                 WHERE is_active = true
                 ORDER BY range_name"
            ) ?: [];
        } elseif ($tag === 'hearmed_lead_types') {
            $rows = HearMed_DB::get_results(
                "SELECT r.id, r.source_name, r.parent_id, r.sort_order, r.is_active,
                        p.source_name as parent_name
                 FROM hearmed_reference.referral_sources r
                 LEFT JOIN hearmed_reference.referral_sources p ON r.parent_id = p.id
                 WHERE r.is_active = true
                 ORDER BY r.source_name"
            ) ?: [];
            $parents = HearMed_DB::get_results(
                "SELECT id, source_name FROM hearmed_reference.referral_sources WHERE is_active = true ORDER BY source_name"
            ) ?: [];
        }

        ob_start(); ?>
        <div class="hm-admin">
            <div style="margin-bottom:16px;"><a href="javascript:history.back()" class="hm-btn">&larr; Back</a></div>
            <div class="hm-admin-hd">
                <h2><?php echo esc_html($cfg['title']); ?></h2>
                <button class="hm-btn hm-btn-teal" onclick="hmTax.open('<?php echo esc_attr($tag); ?>')">+ Add <?php echo esc_html($cfg['singular']); ?></button>
            </div>

            <?php if (empty($rows)): ?>
                <div class="hm-empty-state"><p>No <?php echo esc_html(strtolower($cfg['title'])); ?> yet.</p></div>
            <?php else: ?>
            <table class="hm-table">
                <thead>
                    <?php if ($tag === 'hearmed_brands'): ?>
                    <tr>
                        <th>Name</th>
                        <th>Country</th>
                        <th>Website</th>
                        <th>Support</th>
                        <th>Status</th>
                        <th style="width:100px"></th>
                    </tr>
                    <?php elseif ($tag === 'hearmed_range_settings'): ?>
                    <tr>
                        <th>Range</th>
                        <th class="hm-num">Price Total (€)</th>
                        <th class="hm-num">Price ex PRSI (€)</th>
                        <th>Status</th>
                        <th style="width:100px"></th>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <th>Source</th>
                        <th>Parent</th>
                        <th class="hm-num">Sort</th>
                        <th>Status</th>
                        <th style="width:100px"></th>
                    </tr>
                    <?php endif; ?>
                </thead>
                <tbody>
                <?php foreach ($rows as $r):
                    $row = (array) $r;
                    $payload = json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                ?>
                    <tr>
                        <?php if ($tag === 'hearmed_brands'): ?>
                            <td><strong><?php echo esc_html($r->name); ?></strong></td>
                            <td><?php echo esc_html($r->country ?: '—'); ?></td>
                            <td><?php echo esc_html($r->website ?: '—'); ?></td>
                            <td>
                                <?php echo esc_html($r->support_email ?: '—'); ?>
                                <?php if (!empty($r->support_phone)): ?> / <?php echo esc_html($r->support_phone); ?><?php endif; ?>
                            </td>
                            <td><?php echo $r->is_active ? '<span class="hm-badge hm-badge-green">Active</span>' : '<span class="hm-badge hm-badge-red">Inactive</span>'; ?></td>
                        <?php elseif ($tag === 'hearmed_range_settings'): ?>
                            <td><strong><?php echo esc_html($r->range_name); ?></strong></td>
                            <td class="hm-num"><?php echo $r->price_total !== null ? '€' . esc_html(number_format((float) $r->price_total, 2)) : '—'; ?></td>
                            <td class="hm-num"><?php echo $r->price_ex_prsi !== null ? '€' . esc_html(number_format((float) $r->price_ex_prsi, 2)) : '—'; ?></td>
                            <td><?php echo $r->is_active ? '<span class="hm-badge hm-badge-green">Active</span>' : '<span class="hm-badge hm-badge-red">Inactive</span>'; ?></td>
                        <?php else: ?>
                            <td><strong><?php echo esc_html($r->source_name); ?></strong></td>
                            <td><?php echo esc_html($r->parent_name ?: '—'); ?></td>
                            <td class="hm-num"><?php echo esc_html((string) ($r->sort_order ?? '0')); ?></td>
                            <td><?php echo $r->is_active ? '<span class="hm-badge hm-badge-green">Active</span>' : '<span class="hm-badge hm-badge-red">Inactive</span>'; ?></td>
                        <?php endif; ?>
                        <td class="hm-table-acts">
                            <button class="hm-btn hm-btn-sm" onclick='hmTax.open("<?php echo esc_attr($tag); ?>", <?php echo $payload; ?>)'>Edit</button>
                            <button class="hm-btn hm-btn-sm hm-btn-red" onclick="hmTax.del('<?php echo esc_attr($tag); ?>',<?php echo (int) $r->id; ?>,'<?php echo esc_js($tag === 'hearmed_range_settings' ? $r->range_name : ($tag === 'hearmed_lead_types' ? $r->source_name : $r->name)); ?>')">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- Modal -->
            <div class="hm-modal-bg" id="hm-tax-modal">
                <div class="hm-modal" style="width:520px">
                    <div class="hm-modal-hd">
                        <h3 id="hm-tax-modal-title">Add <?php echo esc_html($cfg['singular']); ?></h3>
                        <button class="hm-modal-x" onclick="hmTax.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hmt-tag" value="">
                        <input type="hidden" id="hmt-id" value="">

                        <div class="hm-form-group">
                            <label>Name *</label>
                            <input type="text" id="hmt-name" placeholder="<?php echo esc_attr($cfg['singular']); ?> name">
                        </div>

                        <div class="hm-tax-fields" data-tag="hearmed_brands">
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Country</label>
                                    <input type="text" id="hmt-country" placeholder="e.g. Denmark">
                                </div>
                                <div class="hm-form-group">
                                    <label>Website</label>
                                    <input type="text" id="hmt-website" placeholder="https://">
                                </div>
                            </div>
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Support Email</label>
                                    <input type="text" id="hmt-support-email" placeholder="support@brand.com">
                                </div>
                                <div class="hm-form-group">
                                    <label>Support Phone</label>
                                    <input type="text" id="hmt-support-phone" placeholder="+353 ...">
                                </div>
                            </div>
                        </div>

                        <div class="hm-tax-fields" data-tag="hearmed_range_settings">
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Price Total (€)</label>
                                    <input type="number" id="hmt-price-total" step="0.01" placeholder="0.00">
                                </div>
                                <div class="hm-form-group">
                                    <label>Price ex PRSI (€)</label>
                                    <input type="number" id="hmt-price-ex-prsi" step="0.01" placeholder="0.00">
                                </div>
                            </div>
                        </div>

                        <div class="hm-tax-fields" data-tag="hearmed_lead_types">
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>Parent Source</label>
                                    <select id="hmt-parent">
                                        <option value="">— None —</option>
                                        <?php foreach ($parents as $p): ?>
                                            <option value="<?php echo (int) $p->id; ?>"><?php echo esc_html($p->source_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="hm-form-group">
                                    <label>Sort Order</label>
                                    <input type="number" id="hmt-sort" step="1" min="0" placeholder="0">
                                </div>
                            </div>
                        </div>

                        <div class="hm-form-group">
                            <label class="hm-toggle-label">
                                <input type="checkbox" id="hmt-active" checked>
                                Active
                            </label>
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmTax.close()">Cancel</button>
                        <button class="hm-btn hm-btn-teal" onclick="hmTax.save()" id="hmt-save-btn">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        var hmTax = {
            open: function(tag, data) {
                var isEdit = !!(data && data.id);
                document.getElementById('hm-tax-modal-title').textContent = isEdit ? 'Edit' : 'Add';
                document.getElementById('hmt-tag').value = tag;
                document.getElementById('hmt-id').value = isEdit ? data.id : '';

                document.getElementById('hmt-name').value = data && (data.name || data.range_name || data.source_name) ? (data.name || data.range_name || data.source_name) : '';
                document.getElementById('hmt-country').value = data && data.country ? data.country : '';
                document.getElementById('hmt-website').value = data && data.website ? data.website : '';
                document.getElementById('hmt-support-email').value = data && data.support_email ? data.support_email : '';
                document.getElementById('hmt-support-phone').value = data && data.support_phone ? data.support_phone : '';
                document.getElementById('hmt-price-total').value = data && data.price_total !== null && data.price_total !== undefined ? data.price_total : '';
                document.getElementById('hmt-price-ex-prsi').value = data && data.price_ex_prsi !== null && data.price_ex_prsi !== undefined ? data.price_ex_prsi : '';
                document.getElementById('hmt-parent').value = data && data.parent_id ? data.parent_id : '';
                document.getElementById('hmt-sort').value = data && (data.sort_order !== null && data.sort_order !== undefined) ? data.sort_order : '0';
                document.getElementById('hmt-active').checked = data && data.is_active === false ? false : true;

                document.querySelectorAll('.hm-tax-fields').forEach(function(el) {
                    el.style.display = (el.getAttribute('data-tag') === tag) ? 'block' : 'none';
                });

                document.getElementById('hm-tax-modal').classList.add('open');
                document.getElementById('hmt-name').focus();
            },
            close: function() { document.getElementById('hm-tax-modal').classList.remove('open'); },
            save: function() {
                var tag = document.getElementById('hmt-tag').value;
                var name = document.getElementById('hmt-name').value.trim();
                if (!name) { alert('Name is required.'); return; }

                var payload = {
                    action: 'hm_admin_save_term',
                    nonce: HM.nonce,
                    tag: tag,
                    term_id: document.getElementById('hmt-id').value,
                    name: name,
                    is_active: document.getElementById('hmt-active').checked ? 1 : 0
                };

                if (tag === 'hearmed_brands') {
                    payload.country = document.getElementById('hmt-country').value;
                    payload.website = document.getElementById('hmt-website').value;
                    payload.support_email = document.getElementById('hmt-support-email').value;
                    payload.support_phone = document.getElementById('hmt-support-phone').value;
                } else if (tag === 'hearmed_range_settings') {
                    payload.price_total = document.getElementById('hmt-price-total').value;
                    payload.price_ex_prsi = document.getElementById('hmt-price-ex-prsi').value;
                } else if (tag === 'hearmed_lead_types') {
                    payload.parent_id = document.getElementById('hmt-parent').value;
                    payload.sort_order = document.getElementById('hmt-sort').value;
                }

                var btn = document.getElementById('hmt-save-btn');
                btn.textContent = 'Saving...'; btn.disabled = true;
                jQuery.post(HM.ajax_url, payload, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error'); btn.textContent = 'Save'; btn.disabled = false; }
                });
            },
            del: function(tag, id, name) {
                if (!confirm('Delete "' + name + '"?')) return;
                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_delete_term',
                    nonce: HM.nonce,
                    tag: tag,
                    term_id: id
                }, function(r) {
                    if (r.success) location.reload();
                    else alert(r.data || 'Error');
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

        $tag = sanitize_text_field($_POST['tag'] ?? '');
        $name = sanitize_text_field($_POST['name'] ?? '');
        $term_id = intval($_POST['term_id'] ?? 0);
        $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;

        if (empty($tag) || empty($name)) { wp_send_json_error('Missing fields'); return; }

        $cfg = $this->configs[$tag] ?? null;
        if (!$cfg) { wp_send_json_error('Unknown reference table'); return; }

        $table = $cfg['table'];
        $data = ['updated_at' => current_time('mysql')];

        if ($tag === 'hearmed_brands') {
            $data['name'] = $name;
            $data['country'] = sanitize_text_field($_POST['country'] ?? '');
            $data['website'] = sanitize_text_field($_POST['website'] ?? '');
            $data['support_email'] = sanitize_text_field($_POST['support_email'] ?? '');
            $data['support_phone'] = sanitize_text_field($_POST['support_phone'] ?? '');
            $data['is_active'] = $is_active;
        } elseif ($tag === 'hearmed_range_settings') {
            $data['range_name'] = $name;
            $data['price_total'] = $_POST['price_total'] !== '' ? floatval($_POST['price_total']) : null;
            $data['price_ex_prsi'] = $_POST['price_ex_prsi'] !== '' ? floatval($_POST['price_ex_prsi']) : null;
            $data['is_active'] = $is_active;
        } elseif ($tag === 'hearmed_lead_types') {
            $data['source_name'] = $name;
            $data['parent_id'] = $_POST['parent_id'] !== '' ? intval($_POST['parent_id']) : null;
            $data['sort_order'] = $_POST['sort_order'] !== '' ? intval($_POST['sort_order']) : 0;
            $data['is_active'] = $is_active;
        }

        if ($term_id) {
            $result = HearMed_DB::update(
                $table,
                $data,
                ['id' => $term_id]
            );
        } else {
            $data['created_at'] = current_time('mysql');
            $result = HearMed_DB::insert(
                $table,
                $data
            );
            $term_id = $result ?: 0;
        }

            if ($result === false) {
                wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
        } else {
            wp_send_json_success(['id' => $term_id]);
        }
    }

    public function ajax_delete() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        $tag = sanitize_text_field($_POST['tag'] ?? '');
        $term_id = intval($_POST['term_id'] ?? 0);

        if (empty($tag) || !$term_id) { wp_send_json_error('Missing parameters'); return; }

        $cfg = $this->configs[$tag] ?? null;
        if (!$cfg) { wp_send_json_error('Unknown reference table'); return; }

        $table = $cfg['table'];

        // Soft delete by setting is_active = false
        $result = HearMed_DB::update(
            $table,
            ['is_active' => false, 'updated_at' => current_time('mysql')],
            ['id' => $term_id]
        );

            if ($result === false) {
                wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
        } else {
            wp_send_json_success();
        }
    }
}

new HearMed_Admin_Taxonomies();
