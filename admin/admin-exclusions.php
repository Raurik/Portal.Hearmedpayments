<?php
/**
 * HearMed Admin — Exclusion Types
 * Shortcode: [hearmed_admin_exclusions]
 * Manages calendar exclusion types (lunch, holiday, meeting, training, etc.) with assigned colors.
 * Uses hearmed_reference.exclusion_types table (new).
 *
 * @package HearMed_Portal
 * @since   5.1.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Exclusions {

    public function __construct() {
        add_shortcode('hearmed_admin_exclusions', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_exclusion', [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_exclusion', [$this, 'ajax_delete']);
    }

    private function get_exclusions() {
        $t = 'hearmed_reference.exclusion_types';
        $check = HearMed_DB::get_var("SELECT to_regclass('{$t}')");
        if ($check === null) return [];
        return HearMed_DB::get_results("SELECT * FROM {$t} WHERE is_active = true ORDER BY sort_order, type_name") ?: [];
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $exclusions = $this->get_exclusions();

        ob_start(); ?>
        <div class="hm-admin">
            <div class="hm-admin-hd">
                <h2>Exclusion Types</h2>
                <button class="hm-btn hm-btn-teal" onclick="hmExcl.open()">+ Add Exclusion Type</button>
            </div>

            <p style="color:var(--hm-text-light);font-size:13px;margin-bottom:20px;">
                Define the types of exclusions that can block calendar time slots (lunch breaks, holidays, meetings, training, etc.).
                Each type gets a colour that appears on the calendar.
            </p>

            <?php if (empty($exclusions)): ?>
                <div class="hm-empty-state"><p>No exclusion types defined yet. Add some to use in the calendar.</p></div>
            <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
                <?php foreach ($exclusions as $e):
                    $row = json_encode((array) $e, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                    $color = $e->color ?: '#6b7280';
                ?>
                <div class="hm-settings-panel" style="border-left:4px solid <?php echo esc_attr($color); ?>;padding:16px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span style="width:16px;height:16px;border-radius:4px;background:<?php echo esc_attr($color); ?>;display:inline-block;"></span>
                            <strong style="font-size:15px;"><?php echo esc_html($e->type_name); ?></strong>
                        </div>
                        <?php echo $e->is_active ? '<span class="hm-badge hm-badge-green">Active</span>' : '<span class="hm-badge hm-badge-red">Inactive</span>'; ?>
                    </div>
                    <p style="font-size:12px;color:var(--hm-text-light);margin:0 0 12px;"><?php echo esc_html($e->description ?: 'No description'); ?></p>
                    <div style="display:flex;gap:6px;">
                        <button class="hm-btn hm-btn-sm" onclick='hmExcl.open(<?php echo $row; ?>)'>Edit</button>
                        <button class="hm-btn hm-btn-sm hm-btn-red" onclick="hmExcl.del(<?php echo (int) $e->id; ?>,'<?php echo esc_js($e->type_name); ?>')">Delete</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Modal -->
            <div class="hm-modal-bg" id="hm-excl-modal">
                <div class="hm-modal" style="width:520px">
                    <div class="hm-modal-hd">
                        <h3 id="hm-excl-title">Add Exclusion Type</h3>
                        <button class="hm-modal-x" onclick="hmExcl.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hme-id">
                        <div class="hm-form-row">
                            <div class="hm-form-group" style="flex:2">
                                <label>Type Name *</label>
                                <input type="text" id="hme-name" placeholder="e.g. Lunch Break">
                            </div>
                            <div class="hm-form-group" style="flex:1">
                                <label>Colour *</label>
                                <input type="color" id="hme-color" value="#6b7280" class="hm-color-box" style="width:100%;height:38px;">
                            </div>
                        </div>
                        <div class="hm-form-group">
                            <label>Description</label>
                            <textarea id="hme-desc" rows="2" placeholder="Optional description"></textarea>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Sort Order</label>
                                <input type="number" id="hme-sort" min="0" value="0">
                            </div>
                            <div class="hm-form-group">
                                <label class="hm-toggle-label" style="margin-top:22px;">
                                    <input type="checkbox" id="hme-active" checked>
                                    Active
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmExcl.close()">Cancel</button>
                        <button class="hm-btn hm-btn-teal" onclick="hmExcl.save()" id="hme-save">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        var hmExcl = {
            open: function(data) {
                var isEdit = !!(data && data.id);
                document.getElementById('hm-excl-title').textContent = isEdit ? 'Edit Exclusion Type' : 'Add Exclusion Type';
                document.getElementById('hme-id').value    = isEdit ? data.id : '';
                document.getElementById('hme-name').value  = isEdit ? (data.type_name || '') : '';
                document.getElementById('hme-color').value = isEdit ? (data.color || '#6b7280') : '#6b7280';
                document.getElementById('hme-desc').value  = isEdit ? (data.description || '') : '';
                document.getElementById('hme-sort').value  = isEdit ? (data.sort_order || 0) : 0;
                document.getElementById('hme-active').checked = isEdit ? !!data.is_active : true;
                document.getElementById('hm-excl-modal').classList.add('open');
                document.getElementById('hme-name').focus();
            },
            close: function() { document.getElementById('hm-excl-modal').classList.remove('open'); },
            save: function() {
                var name = document.getElementById('hme-name').value.trim();
                if (!name) { alert('Type name is required.'); return; }

                var btn = document.getElementById('hme-save');
                btn.textContent = 'Saving...'; btn.disabled = true;

                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_save_exclusion',
                    nonce: HM.nonce,
                    id: document.getElementById('hme-id').value,
                    type_name: name,
                    color: document.getElementById('hme-color').value,
                    description: document.getElementById('hme-desc').value,
                    sort_order: document.getElementById('hme-sort').value,
                    is_active: document.getElementById('hme-active').checked ? 1 : 0
                }, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error'); btn.textContent = 'Save'; btn.disabled = false; }
                });
            },
            del: function(id, name) {
                if (!confirm('Delete exclusion type "' + name + '"?')) return;
                jQuery.post(HM.ajax_url, { action:'hm_admin_delete_exclusion', nonce:HM.nonce, id:id }, function(r) {
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
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }

        $id   = intval($_POST['id'] ?? 0);
        $name = sanitize_text_field($_POST['type_name'] ?? '');
        if (!$name) { wp_send_json_error('Type name required'); return; }

        $data = [
            'type_name'   => $name,
            'color'       => sanitize_hex_color($_POST['color'] ?? '#6b7280') ?: '#6b7280',
            'description' => sanitize_text_field($_POST['description'] ?? ''),
            'sort_order'  => intval($_POST['sort_order'] ?? 0),
            'is_active'   => intval($_POST['is_active'] ?? 1),
            'updated_at'  => current_time('mysql'),
        ];

        $table = 'hearmed_reference.exclusion_types';

        if ($id) {
            $result = HearMed_DB::update($table, $data, ['id' => $id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $id = HearMed_DB::insert($table, $data);
            $result = $id ? 1 : false;
        }

        if ($result === false) {
            wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
        } else {
            wp_send_json_success(['id' => $id]);
        }
    }

    public function ajax_delete() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }

        $id = intval($_POST['id'] ?? 0);
        if (!$id) { wp_send_json_error('Invalid ID'); return; }

        $result = HearMed_DB::update(
            'hearmed_reference.exclusion_types',
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

new HearMed_Admin_Exclusions();
