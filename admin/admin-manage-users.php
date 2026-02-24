<?php
/**
 * HearMed Admin — Users (Staff)
 * Shortcode: [hearmed_manage_users]
 * PostgreSQL CRUD for hearmed_reference.staff and staff_clinics
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Manage_Users {

    public function __construct() {
        add_shortcode('hearmed_manage_users', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_staff', [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_staff', [$this, 'ajax_delete']);
    }

    private function get_staff() {
        return HearMed_DB::get_results(
            "SELECT id, first_name, last_name, email, phone, role, employee_number, hire_date, is_active, wp_user_id
             FROM hearmed_reference.staff
             ORDER BY last_name, first_name"
        ) ?: [];
    }

    private function get_clinics() {
        return HearMed_DB::get_results(
            "SELECT id, clinic_name
             FROM hearmed_reference.clinics
             WHERE is_active = true
             ORDER BY clinic_name"
        ) ?: [];
    }

    private function get_staff_clinics() {
        return HearMed_DB::get_results(
            "SELECT staff_id, clinic_id, is_primary_clinic
             FROM hearmed_reference.staff_clinics"
        ) ?: [];
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $staff = $this->get_staff();
        $clinics = $this->get_clinics();
        $staff_clinics = $this->get_staff_clinics();

        $clinic_map = [];
        foreach ($clinics as $c) {
            $clinic_map[$c->id] = $c->clinic_name;
        }

        $staff_clinic_map = [];
        foreach ($staff_clinics as $sc) {
            $sid = (int) $sc->staff_id;
            if (!isset($staff_clinic_map[$sid])) {
                $staff_clinic_map[$sid] = [
                    'clinics' => [],
                    'primary' => null,
                ];
            }
            $staff_clinic_map[$sid]['clinics'][] = (int) $sc->clinic_id;
            if ($sc->is_primary_clinic) {
                $staff_clinic_map[$sid]['primary'] = (int) $sc->clinic_id;
            }
        }

        $staff_payload = [];
        foreach ($staff as $s) {
            $sid = (int) $s->id;
            $clinic_ids = $staff_clinic_map[$sid]['clinics'] ?? [];
            $primary_id = $staff_clinic_map[$sid]['primary'] ?? null;
            $clinic_labels = [];
            foreach ($clinic_ids as $cid) {
                if (isset($clinic_map[$cid])) $clinic_labels[] = $clinic_map[$cid];
            }

            $staff_payload[] = [
                'id' => $sid,
                'first_name' => $s->first_name,
                'last_name' => $s->last_name,
                'email' => $s->email,
                'phone' => $s->phone,
                'role' => $s->role,
                'employee_number' => $s->employee_number,
                'hire_date' => $s->hire_date,
                'is_active' => (bool) $s->is_active,
                'wp_user_id' => $s->wp_user_id,
                'clinic_ids' => $clinic_ids,
                'primary_clinic_id' => $primary_id,
                'clinic_labels' => $clinic_labels,
            ];
        }

        ob_start(); ?>
        <div class="hm-admin" id="hm-users-admin">
            <div class="hm-admin-hd">
                <h2>Users</h2>
                <button class="hm-btn hm-btn-teal" onclick="hmUsers.open()">+ Add User</button>
            </div>

            <?php if (empty($staff_payload)): ?>
                <div class="hm-empty-state"><p>No users added yet.</p></div>
            <?php else: ?>
            <table class="hm-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Clinics</th>
                        <th>Status</th>
                        <th style="width:100px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($staff_payload as $u):
                    $payload = json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                    $name = trim($u['first_name'] . ' ' . $u['last_name']);
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($name); ?></strong></td>
                        <td><?php echo esc_html($u['role'] ?: '—'); ?></td>
                        <td><?php echo esc_html($u['email'] ?: '—'); ?></td>
                        <td><?php echo esc_html(!empty($u['clinic_labels']) ? implode(', ', $u['clinic_labels']) : '—'); ?></td>
                        <td><?php echo $u['is_active'] ? '<span class="hm-badge hm-badge-green">Active</span>' : '<span class="hm-badge hm-badge-red">Inactive</span>'; ?></td>
                        <td class="hm-table-acts">
                            <button class="hm-btn hm-btn-sm" onclick='hmUsers.open(<?php echo $payload; ?>)'>Edit</button>
                            <button class="hm-btn hm-btn-sm hm-btn-red" onclick="hmUsers.del(<?php echo (int) $u['id']; ?>,'<?php echo esc_js($name); ?>')">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <div class="hm-modal-bg" id="hm-user-modal">
                <div class="hm-modal" style="width:640px">
                    <div class="hm-modal-hd">
                        <h3 id="hm-user-title">Add User</h3>
                        <button class="hm-modal-x" onclick="hmUsers.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hmu-id">
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>First Name *</label>
                                <input type="text" id="hmu-first">
                            </div>
                            <div class="hm-form-group">
                                <label>Last Name *</label>
                                <input type="text" id="hmu-last">
                            </div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Email *</label>
                                <input type="text" id="hmu-email">
                            </div>
                            <div class="hm-form-group">
                                <label>Phone</label>
                                <input type="text" id="hmu-phone">
                            </div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Role *</label>
                                <input type="text" id="hmu-role" placeholder="e.g. Dispenser, Admin">
                            </div>
                            <div class="hm-form-group">
                                <label>Employee Number</label>
                                <input type="text" id="hmu-emp">
                            </div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Hire Date</label>
                                <input type="date" id="hmu-hire">
                            </div>
                            <div class="hm-form-group">
                                <label>WP User ID (optional)</label>
                                <input type="number" id="hmu-wp" min="1">
                            </div>
                        </div>

                        <div class="hm-form-group">
                            <label>Clinics</label>
                            <div id="hmu-clinics" style="display:flex;flex-wrap:wrap;gap:10px;">
                                <?php foreach ($clinics as $c): ?>
                                    <label class="hm-day-check">
                                        <input type="checkbox" class="hm-staff-clinic" value="<?php echo (int) $c->id; ?>">
                                        <?php echo esc_html($c->clinic_name); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Primary Clinic</label>
                                <select id="hmu-primary">
                                    <option value="">— None —</option>
                                </select>
                            </div>
                            <div class="hm-form-group">
                                <label class="hm-toggle-label">
                                    <input type="checkbox" id="hmu-active" checked>
                                    Active
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmUsers.close()">Cancel</button>
                        <button class="hm-btn hm-btn-teal" onclick="hmUsers.save()" id="hmu-save">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        var hmUsers = {
            clinics: <?php echo json_encode(array_map(function($c){ return ['id'=>(int)$c->id,'name'=>$c->clinic_name]; }, $clinics), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
            open: function(data) {
                var isEdit = !!(data && data.id);
                document.getElementById('hm-user-title').textContent = isEdit ? 'Edit User' : 'Add User';

                document.getElementById('hmu-id').value = isEdit ? data.id : '';
                document.getElementById('hmu-first').value = isEdit ? data.first_name : '';
                document.getElementById('hmu-last').value = isEdit ? data.last_name : '';
                document.getElementById('hmu-email').value = isEdit ? data.email : '';
                document.getElementById('hmu-phone').value = isEdit ? (data.phone || '') : '';
                document.getElementById('hmu-role').value = isEdit ? (data.role || '') : '';
                document.getElementById('hmu-emp').value = isEdit ? (data.employee_number || '') : '';
                document.getElementById('hmu-hire').value = isEdit ? (data.hire_date || '') : '';
                document.getElementById('hmu-wp').value = isEdit ? (data.wp_user_id || '') : '';
                document.getElementById('hmu-active').checked = isEdit ? !!data.is_active : true;

                document.querySelectorAll('.hm-staff-clinic').forEach(function(cb) {
                    cb.checked = isEdit && data.clinic_ids ? data.clinic_ids.indexOf(parseInt(cb.value, 10)) !== -1 : false;
                    cb.onchange = hmUsers.refreshPrimary;
                });

                hmUsers.refreshPrimary();
                if (isEdit && data.primary_clinic_id) {
                    document.getElementById('hmu-primary').value = data.primary_clinic_id;
                }

                document.getElementById('hm-user-modal').classList.add('open');
            },
            close: function() {
                document.getElementById('hm-user-modal').classList.remove('open');
            },
            refreshPrimary: function() {
                var sel = document.getElementById('hmu-primary');
                var selected = [];
                document.querySelectorAll('.hm-staff-clinic:checked').forEach(function(cb) {
                    selected.push(parseInt(cb.value, 10));
                });

                var current = sel.value;
                sel.innerHTML = '<option value="">— None —</option>';
                selected.forEach(function(id) {
                    var clinic = hmUsers.clinics.find(function(c){ return c.id === id; });
                    if (clinic) {
                        var opt = document.createElement('option');
                        opt.value = clinic.id;
                        opt.textContent = clinic.name;
                        sel.appendChild(opt);
                    }
                });
                if (current && selected.indexOf(parseInt(current, 10)) !== -1) {
                    sel.value = current;
                }
            },
            save: function() {
                var first = document.getElementById('hmu-first').value.trim();
                var last = document.getElementById('hmu-last').value.trim();
                var email = document.getElementById('hmu-email').value.trim();
                var role = document.getElementById('hmu-role').value.trim();
                if (!first || !last || !email || !role) {
                    alert('First name, last name, email and role are required.');
                    return;
                }

                var clinicIds = [];
                document.querySelectorAll('.hm-staff-clinic:checked').forEach(function(cb) {
                    clinicIds.push(parseInt(cb.value, 10));
                });

                var payload = {
                    action: 'hm_admin_save_staff',
                    nonce: HM.nonce,
                    id: document.getElementById('hmu-id').value,
                    first_name: first,
                    last_name: last,
                    email: email,
                    phone: document.getElementById('hmu-phone').value,
                    role: role,
                    employee_number: document.getElementById('hmu-emp').value,
                    hire_date: document.getElementById('hmu-hire').value,
                    wp_user_id: document.getElementById('hmu-wp').value,
                    is_active: document.getElementById('hmu-active').checked ? 1 : 0,
                    clinics: JSON.stringify(clinicIds),
                    primary_clinic_id: document.getElementById('hmu-primary').value
                };

                var btn = document.getElementById('hmu-save');
                btn.textContent = 'Saving...'; btn.disabled = true;
                jQuery.post(HM.ajax_url, payload, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error'); btn.textContent = 'Save'; btn.disabled = false; }
                });
            },
            del: function(id, name) {
                if (!confirm('Delete "' + name + '"?')) return;
                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_delete_staff',
                    nonce: HM.nonce,
                    id: id
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
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }

        $id = intval($_POST['id'] ?? 0);
        $first = sanitize_text_field($_POST['first_name'] ?? '');
        $last = sanitize_text_field($_POST['last_name'] ?? '');
        $email = sanitize_text_field($_POST['email'] ?? '');
        $role = sanitize_text_field($_POST['role'] ?? '');

        if (!$first || !$last || !$email || !$role) { wp_send_json_error('Missing fields'); return; }

        $data = [
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'role' => $role,
            'employee_number' => sanitize_text_field($_POST['employee_number'] ?? ''),
            'hire_date' => sanitize_text_field($_POST['hire_date'] ?? ''),
            'wp_user_id' => ($_POST['wp_user_id'] ?? '') !== '' ? intval($_POST['wp_user_id']) : null,
            'is_active' => intval($_POST['is_active'] ?? 1),
            'updated_at' => current_time('mysql'),
        ];

        if ($id) {
            $result = HearMed_DB::update('hearmed_reference.staff', $data, ['id' => $id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $id = HearMed_DB::insert('hearmed_reference.staff', $data);
            $result = $id ? 1 : false;
        }

        if ($result === false) { wp_send_json_error('Database error'); return; }

        $clinic_ids = json_decode(stripslashes($_POST['clinics'] ?? '[]'), true);
        if (!is_array($clinic_ids)) $clinic_ids = [];
        $primary_id = ($_POST['primary_clinic_id'] ?? '') !== '' ? intval($_POST['primary_clinic_id']) : null;

        HearMed_DB::get_results(
            "DELETE FROM hearmed_reference.staff_clinics WHERE staff_id = $1",
            [$id]
        );

        foreach ($clinic_ids as $cid) {
            $cid = intval($cid);
            if (!$cid) continue;
            HearMed_DB::insert(
                'hearmed_reference.staff_clinics',
                [
                    'staff_id' => $id,
                    'clinic_id' => $cid,
                    'is_primary_clinic' => $primary_id === $cid,
                    'created_at' => current_time('mysql')
                ]
            );
        }

        wp_send_json_success(['id' => $id]);
    }

    public function ajax_delete() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }

        $id = intval($_POST['id'] ?? 0);
        if (!$id) { wp_send_json_error('Invalid ID'); return; }

        $result = HearMed_DB::update(
            'hearmed_reference.staff',
            ['is_active' => false, 'updated_at' => current_time('mysql')],
            ['id' => $id]
        );

        if ($result === false) {
            wp_send_json_error('Database error');
        } else {
            wp_send_json_success();
        }
    }
}

new HearMed_Admin_Manage_Users();
