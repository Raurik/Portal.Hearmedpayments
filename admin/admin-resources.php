<?php
/**
 * HearMed Admin — Clinic Resources & Equipment
 * Shortcode: [hearmed_admin_resources]
 * Tracks rooms, audiometers, wax machines, endoscopes, diagnostics per clinic.
 * Uses hearmed_reference.resources table (repurposed: category = resource_type, url = calibration_due, description = serial/model).
 *
 * @package HearMed_Portal
 * @since   5.1.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Resources {

    private static $resource_types = [
        'Room'        => 'Room',
        'Audiometer'  => 'Audiometer',
        'Wax Machine' => 'Wax Machine',
        'Endoscope'   => 'Endoscope',
        'Diagnostics' => 'Diagnostics',
        'Other'       => 'Other',
    ];

    public function __construct() {
        add_shortcode('hearmed_admin_resources', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_resource', [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_resource', [$this, 'ajax_delete']);
    }

    private function get_clinics() {
        return HearMed_DB::get_results(
            "SELECT id, clinic_name FROM hearmed_reference.clinics WHERE is_active = true ORDER BY clinic_name"
        ) ?: [];
    }

    private function get_resources() {
        return HearMed_DB::get_results(
            "SELECT r.*, c.clinic_name
             FROM hearmed_reference.resources r
             LEFT JOIN hearmed_reference.clinics c ON c.id = r.clinic_id
             ORDER BY c.clinic_name, r.category, r.title"
        ) ?: [];
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $resources = $this->get_resources();
        $clinics   = $this->get_clinics();
        $types     = self::$resource_types;

        $by_clinic = [];
        foreach ($resources as $r) {
            $cname = $r->clinic_name ?: 'Unassigned';
            if (!isset($by_clinic[$cname])) $by_clinic[$cname] = [];
            $by_clinic[$cname][] = $r;
        }

        ob_start(); ?>
        <div class="hm-admin" id="hm-resources-admin">
            <div class="hm-admin-hd">
                <h2>Clinic Resources &amp; Equipment</h2>
                <button class="hm-btn hm-btn-teal" onclick="hmRes.open()">+ Add Resource</button>
            </div>

            <p style="color:var(--hm-text-light);font-size:13px;margin-bottom:20px;">
                Track rooms, audiometers, wax machines, endoscopes and diagnostics equipment per clinic.
            </p>

            <?php if (empty($resources)): ?>
                <div class="hm-empty-state"><p>No resources yet. Click "+ Add Resource" to get started.</p></div>
            <?php else: ?>
                <?php foreach ($by_clinic as $clinic_name => $items): ?>
                <div class="hm-settings-panel" style="margin-bottom:16px;">
                    <h3 style="font-size:15px;margin-bottom:12px;"><?php echo esc_html($clinic_name); ?> <span style="color:var(--hm-text-light);font-weight:400;font-size:13px;">(<?php echo count($items); ?> items)</span></h3>
                    <table class="hm-table">
                        <thead>
                            <tr>
                                <th>Resource Name</th>
                                <th>Type</th>
                                <th>Serial / Model</th>
                                <th>Calibration Due</th>
                                <th>Status</th>
                                <th style="width:100px"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $r):
                            $row = json_encode((array) $r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                            $cal_class = '';
                            if (!empty($r->url)) {
                                $due = strtotime($r->url);
                                if ($due && $due < time()) $cal_class = 'hm-badge-red';
                                elseif ($due && $due < strtotime('+30 days')) $cal_class = 'hm-badge-orange';
                                else $cal_class = 'hm-badge-green';
                            }
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html($r->title); ?></strong></td>
                                <td><span class="hm-badge hm-badge-blue"><?php echo esc_html($r->category ?: '—'); ?></span></td>
                                <td><?php echo esc_html($r->description ?: '—'); ?></td>
                                <td>
                                    <?php if (!empty($r->url)): ?>
                                        <span class="hm-badge <?php echo $cal_class; ?>"><?php echo esc_html($r->url); ?></span>
                                    <?php else: echo '—'; endif; ?>
                                </td>
                                <td><?php echo $r->is_active ? '<span class="hm-badge hm-badge-green">Active</span>' : '<span class="hm-badge hm-badge-red">Inactive</span>'; ?></td>
                                <td class="hm-table-acts">
                                    <button class="hm-btn hm-btn-sm" onclick='hmRes.open(<?php echo $row; ?>)'>Edit</button>
                                    <button class="hm-btn hm-btn-sm hm-btn-red" onclick="hmRes.del(<?php echo (int) $r->id; ?>,'<?php echo esc_js($r->title); ?>')">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Modal -->
            <div class="hm-modal-bg" id="hm-res-modal">
                <div class="hm-modal" style="width:640px">
                    <div class="hm-modal-hd">
                        <h3 id="hm-res-title">Add Resource</h3>
                        <button class="hm-modal-x" onclick="hmRes.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hmr-id">
                        <div class="hm-form-row">
                            <div class="hm-form-group" style="flex:2">
                                <label>Resource Name *</label>
                                <input type="text" id="hmr-title" placeholder="e.g. Room 1, MA41 Audiometer">
                            </div>
                            <div class="hm-form-group" style="flex:1">
                                <label>Type *</label>
                                <select id="hmr-category">
                                    <option value="">Select type</option>
                                    <?php foreach ($types as $k => $v): ?>
                                        <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Clinic *</label>
                                <select id="hmr-clinic">
                                    <option value="">Select clinic</option>
                                    <?php foreach ($clinics as $c): ?>
                                        <option value="<?php echo (int) $c->id; ?>"><?php echo esc_html($c->clinic_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="hm-form-group">
                                <label>Calibration Due Date</label>
                                <input type="date" id="hmr-cal-due">
                            </div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group" style="flex:2">
                                <label>Serial Number / Model</label>
                                <input type="text" id="hmr-desc" placeholder="e.g. SN-12345 or MA41">
                            </div>
                            <div class="hm-form-group" style="flex:1">
                                <label class="hm-toggle-label">
                                    <input type="checkbox" id="hmr-active" checked>
                                    Active
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmRes.close()">Cancel</button>
                        <button class="hm-btn hm-btn-teal" onclick="hmRes.save()" id="hmr-save">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        var hmRes = {
            open: function(data) {
                var isEdit = !!(data && data.id);
                document.getElementById('hm-res-title').textContent = isEdit ? 'Edit Resource' : 'Add Resource';
                document.getElementById('hmr-id').value       = isEdit ? data.id : '';
                document.getElementById('hmr-title').value    = isEdit ? (data.title || '') : '';
                document.getElementById('hmr-category').value = isEdit ? (data.category || '') : '';
                document.getElementById('hmr-clinic').value   = isEdit ? (data.clinic_id || '') : '';
                document.getElementById('hmr-cal-due').value  = isEdit ? (data.url || '') : '';
                document.getElementById('hmr-desc').value     = isEdit ? (data.description || '') : '';
                document.getElementById('hmr-active').checked = isEdit ? !!data.is_active : true;
                document.getElementById('hm-res-modal').classList.add('open');
                document.getElementById('hmr-title').focus();
            },
            close: function() { document.getElementById('hm-res-modal').classList.remove('open'); },
            save: function() {
                var title  = document.getElementById('hmr-title').value.trim();
                var cat    = document.getElementById('hmr-category').value;
                var clinic = document.getElementById('hmr-clinic').value;
                if (!title)  { alert('Resource name is required.'); return; }
                if (!cat)    { alert('Type is required.'); return; }
                if (!clinic) { alert('Clinic is required.'); return; }

                var btn = document.getElementById('hmr-save');
                btn.textContent = 'Saving...'; btn.disabled = true;

                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_save_resource',
                    nonce: HM.nonce,
                    id: document.getElementById('hmr-id').value,
                    title: title,
                    category: cat,
                    clinic_id: clinic,
                    url: document.getElementById('hmr-cal-due').value,
                    description: document.getElementById('hmr-desc').value,
                    is_active: document.getElementById('hmr-active').checked ? 1 : 0
                }, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error'); btn.textContent = 'Save'; btn.disabled = false; }
                });
            },
            del: function(id, name) {
                if (!confirm('Deactivate "' + name + '"?')) return;
                jQuery.post(HM.ajax_url, { action:'hm_admin_delete_resource', nonce:HM.nonce, id:id }, function(r) {
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

        $id     = intval($_POST['id'] ?? 0);
        $title  = sanitize_text_field($_POST['title'] ?? '');
        $cat    = sanitize_text_field($_POST['category'] ?? '');
        $clinic = intval($_POST['clinic_id'] ?? 0);
        if (!$title || !$cat || !$clinic) {
            wp_send_json_error('Name, type and clinic are required');
            return;
        }

        $data = [
            'title'       => $title,
            'category'    => $cat,
            'clinic_id'   => $clinic,
            'url'         => sanitize_text_field($_POST['url'] ?? ''),
            'description' => sanitize_text_field($_POST['description'] ?? ''),
            'is_active'   => intval($_POST['is_active'] ?? 1),
            'sort_order'  => 0,
            'updated_at'  => current_time('mysql'),
        ];

        if ($id) {
            $result = HearMed_DB::update('hearmed_reference.resources', $data, ['id' => $id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $id = HearMed_DB::insert('hearmed_reference.resources', $data);
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
            'hearmed_reference.resources',
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

new HearMed_Admin_Resources();
