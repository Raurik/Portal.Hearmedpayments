<?php
/**
 * HearMed Admin — Staff
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
        // Simple query - proven to work by diagnostic
        $result = HearMed_DB::get_results(
            "SELECT s.id, s.first_name, s.last_name, s.email, s.phone, s.role, 
                    s.employee_number, s.hire_date, s.is_active, s.wp_user_id,
                    a.username, a.two_factor_enabled, a.temp_password, a.totp_secret
             FROM hearmed_reference.staff s
             LEFT JOIN hearmed_reference.staff_auth a ON s.id = a.staff_id
             WHERE s.is_active = true
             ORDER BY s.last_name, s.first_name"
        );
        
        return $result ?: [];
    }

    private function get_clinics() {
        return HearMed_DB::get_results(
            "SELECT id, clinic_name
             FROM hearmed_reference.clinics
             WHERE is_active = true
             ORDER BY clinic_name"
        ) ?: [];
    }

    private function get_roles() {
        return HearMed_DB::get_results(
            "SELECT id, role_name, display_name
             FROM hearmed_reference.roles
             WHERE is_active = true
             ORDER BY display_name"
        ) ?: [];
    }

    private function get_available_wp_user_ids() {
        global $wpdb;
        // Get all WP user IDs that are already assigned to staff
        $assigned = HearMed_DB::get_results(
            "SELECT wp_user_id FROM hearmed_reference.staff WHERE wp_user_id IS NOT NULL"
        ) ?: [];
        $assigned_ids = array_map(function($s) { return (int)$s->wp_user_id; }, $assigned);

        // Get all WP users
        $all_users = $wpdb->get_results("SELECT ID FROM $wpdb->users ORDER BY ID");
        
        // Find first available WP user not assigned to staff
        foreach ($all_users as $user) {
            if (!in_array((int)$user->ID, $assigned_ids)) {
                return (int)$user->ID;
            }
        }
        
        return null; // No available users
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
        $roles = $this->get_roles();
        $staff_clinics = $this->get_staff_clinics();
        $first_available_wp_user_id = $this->get_available_wp_user_ids();

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
                'first_name' => $s->first_name ?? '',
                'last_name' => $s->last_name ?? '',
                'email' => $s->email ?? '',
                'phone' => $s->phone ?? '',
                'role' => $s->role ?? '',
                'employee_number' => $s->employee_number ?? '',
                'hire_date' => $s->hire_date ?? '',
                'is_active' => (bool) ($s->is_active ?? false),
                'wp_user_id' => $s->wp_user_id ?? null,
                'username' => $s->username ?? '',
                'two_factor_enabled' => (bool) ($s->two_factor_enabled ?? false),
                'temp_password' => (bool) ($s->temp_password ?? false),
                'totp_secret' => $s->totp_secret ?? '',
                'clinic_ids' => $clinic_ids,
                'primary_clinic_id' => $primary_id,
                'clinic_labels' => $clinic_labels,
            ];
        }

        ob_start(); ?>
        <div class="hm-admin" id="hm-users-admin">
            <div style="margin-bottom:16px;"><a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-btn">&larr; Back</a></div>
            <div class="hm-admin-hd">
                <h2>Staff</h2>
                <button class="hm-btn hm-btn-teal" onclick="hmUsers.open()">+ Add Staff</button>
            </div>

            <?php if (empty($staff_payload)): ?>
                <div class="hm-empty-state"><p>No staff added yet.</p></div>
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
                        <h3 id="hm-user-title">Add Staff</h3>
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
                                <div style="display:flex;gap:4px;">
                                    <select id="hmu-role" style="flex:1">
                                        <option value="">— Select Role —</option>
                                        <?php foreach ($roles as $r): ?>
                                            <option value="<?php echo esc_attr($r->role_name); ?>"><?php echo esc_html($r->display_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="button" class="hm-btn hm-btn-sm" onclick="hmQuickAdd('role','Role','hmu-role',{useRoleName:true})" title="Add new role" style="padding:4px 10px;">+</button>
                                </div>
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

                        <div style="height:1px;background:#e2e8f0;margin:10px 0 6px;"></div>

                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Login Username</label>
                                <input type="text" id="hmu-username" placeholder="Uses staff email if blank">
                            </div>
                            <div class="hm-form-group">
                                <label class="hm-toggle-label">
                                    <input type="checkbox" id="hmu-2fa">
                                    Two-Factor Enabled
                                </label>
                            </div>
                        </div>
                        <div class="hm-form-row" id="hmu-secret-row" style="display:none;">
                            <div class="hm-form-group" style="flex:1;">
                                <label>2FA Secret</label>
                                <input type="text" id="hmu-secret" readonly>
                            </div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Set New Password <span id="hmu-pass-req" style="color:#ef4444;">*</span></label>
                                <input type="password" id="hmu-pass" placeholder="Required for new staff" onkeyup="hmUsers.checkPasswordStrength(); hmUsers.checkPasswordMatch()">
                                <div id="hmu-pass-strength" style="font-size:12px;color:#dc2626;margin-top:4px;font-weight:500;"></div>
                            </div>
                            <div class="hm-form-group">
                                <label>Confirm Password <span id="hmu-pass2-req" style="color:#ef4444;display:none;">*</span></label>
                                <input type="password" id="hmu-pass2" onkeyup="hmUsers.checkPasswordMatch()">
                                <div id="hmu-pass-match" style="font-size:12px;margin-top:4px;font-weight:500;"></div>
                            </div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group" style="font-size:12px;color:#64748b;" id="hmu-passes-help">
                                <strong>New staff:</strong> Password required. Must have 8+ chars, 1 uppercase, 1 special char. Will be marked temporary so user must change on first login.<br>
                                <strong>Edit staff:</strong> Password optional. Leave blank to keep current password.
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
            roles: <?php echo json_encode(array_map(function($r){ return ['name'=>$r->role_name,'display'=>$r->display_name]; }, $roles), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
            firstAvailableWpUserId: <?php echo $first_available_wp_user_id ? json_encode((int)$first_available_wp_user_id) : 'null'; ?>,
            isNewStaff: false,
            validatePassword: function(pass) {
                // At least 8 characters
                if (pass.length < 8) return 'At least 8 characters';
                // At least one uppercase letter
                if (!/[A-Z]/.test(pass)) return 'At least one uppercase letter';
                // At least one special character
                if (!/[!@#$%^&*()_\-=+\[\]{}|;:'",.<>?/\\]/.test(pass)) return 'At least one special character (!@#$%^&* etc)';
                return null; // Valid
            },
            checkPasswordMatch: function() {
                var pass = document.getElementById('hmu-pass').value;
                var pass2 = document.getElementById('hmu-pass2').value;
                var el = document.getElementById('hmu-pass-match');
                if (!pass2) { el.textContent = ''; return; }
                if (pass === pass2) {
                    el.textContent = '\u2713 Passwords match';
                    el.style.color = '#16a34a';
                } else {
                    el.textContent = '\u2717 Passwords do not match';
                    el.style.color = '#dc2626';
                }
            },
            checkPasswordStrength: function() {
                var pass = document.getElementById('hmu-pass').value;
                var feedback = document.getElementById('hmu-pass-strength');
                
                if (!pass) {
                    feedback.textContent = '';
                    return;
                }
                
                var error = this.validatePassword(pass);
                if (error) {
                    feedback.textContent = '⚠ ' + error;
                    feedback.style.color = '#dc2626';
                } else {
                    feedback.textContent = '✓ Strong password';
                    feedback.style.color = '#16a34a';
                }
            },
            open: function(data) {
                var isEdit = !!(data && data.id);
                this.isNewStaff = !isEdit;
                document.getElementById('hm-user-title').textContent = isEdit ? 'Edit Staff' : 'Add Staff';

                document.getElementById('hmu-id').value = isEdit ? data.id : '';
                document.getElementById('hmu-first').value = isEdit ? data.first_name : '';
                document.getElementById('hmu-last').value = isEdit ? data.last_name : '';
                document.getElementById('hmu-email').value = isEdit ? data.email : '';
                document.getElementById('hmu-phone').value = isEdit ? (data.phone || '') : '';
                document.getElementById('hmu-role').value = isEdit ? (data.role || '') : '';
                document.getElementById('hmu-emp').value = isEdit ? (data.employee_number || '') : '';
                document.getElementById('hmu-hire').value = isEdit ? (data.hire_date || '') : '';
                // Auto-populate WP user ID only for new staff
                document.getElementById('hmu-wp').value = isEdit ? (data.wp_user_id || '') : (this.firstAvailableWpUserId || '');
                document.getElementById('hmu-active').checked = isEdit ? !!data.is_active : true;
                document.getElementById('hmu-username').value = isEdit ? (data.username || data.email || '') : '';
                document.getElementById('hmu-2fa').checked = isEdit ? !!data.two_factor_enabled : false;
                document.getElementById('hmu-secret').value = isEdit ? (data.totp_secret || '') : '';
                document.getElementById('hmu-pass').value = '';
                document.getElementById('hmu-pass2').value = '';
                document.getElementById('hmu-pass').placeholder = this.isNewStaff ? 'Required' : 'Leave blank to keep current';
                document.getElementById('hmu-pass-req').style.display = this.isNewStaff ? 'inline' : 'none';
                document.getElementById('hmu-pass2-req').style.display = this.isNewStaff ? 'inline' : 'none';
                document.getElementById('hmu-pass-strength').textContent = '';
                hmUsers.toggleSecret();

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
            toggleSecret: function() {
                var show = document.getElementById('hmu-2fa').checked;
                document.getElementById('hmu-secret-row').style.display = show ? 'flex' : 'none';
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

                var pass = document.getElementById('hmu-pass').value;
                var pass2 = document.getElementById('hmu-pass2').value;
                
                // Password required for new staff
                if (this.isNewStaff && !pass) {
                    alert('Password is required when creating new staff.');
                    return;
                }
                
                // Password validation if provided
                if (pass || pass2) {
                    // Check password meets requirements
                    var passError = this.validatePassword(pass);
                    if (passError) {
                        alert('Password error: ' + passError);
                        return;
                    }
                    if (pass !== pass2) {
                        alert('Passwords do not match.');
                        return;
                    }
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
                    username: document.getElementById('hmu-username').value,
                    two_factor_enabled: document.getElementById('hmu-2fa').checked ? 1 : 0,
                    new_password: pass,
                    is_new_staff: this.isNewStaff ? 1 : 0,
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

        document.addEventListener('change', function(e) {
            if (e.target && e.target.id === 'hmu-2fa') {
                hmUsers.toggleSecret();
            }
        });
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

        // Validate password if provided (backend check)
        $new_password = (string) ($_POST['new_password'] ?? '');
        $is_new_staff = intval($_POST['is_new_staff'] ?? 0) === 1;
        
        if ($new_password !== '') {
            if (strlen($new_password) < 8) {
                wp_send_json_error('Password must be at least 8 characters');
                return;
            }
            if (!preg_match('/[A-Z]/', $new_password)) {
                wp_send_json_error('Password must contain at least one uppercase letter');
                return;
            }
            if (!preg_match('/[!@#$%^&*()_\-=+\[\]{}|;:\'",.><?\\/\\\\]/', $new_password)) {
                wp_send_json_error('Password must contain at least one special character');
                return;
            }
        } elseif ($is_new_staff) {
            wp_send_json_error('Password is required when creating new staff');
            return;
        }

        $data = [
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'phone' => sanitize_text_field($_POST['phone'] ?? ''),
            'role' => $role,
            'wp_user_id' => ($_POST['wp_user_id'] ?? '') !== '' ? intval($_POST['wp_user_id']) : null,
            'is_active' => intval($_POST['is_active'] ?? 1),
            'updated_at' => current_time('mysql'),
        ];
        
        // Add optional columns only if data provided (they may not exist in DB yet)
        $employee_number = sanitize_text_field($_POST['employee_number'] ?? '');
        $hire_date = sanitize_text_field($_POST['hire_date'] ?? '');
        
        if ($employee_number !== '') {
            $data['employee_number'] = $employee_number;
        }
        if ($hire_date !== '') {
            $data['hire_date'] = $hire_date;
        }

        if ($id) {
            $result = HearMed_DB::update('hearmed_reference.staff', $data, ['id' => $id]);
            // If update fails (columns might not exist), try without optional columns
            if ($result === false) {
                unset($data['employee_number'], $data['hire_date']);
                $result = HearMed_DB::update('hearmed_reference.staff', $data, ['id' => $id]);
            }
        } else {
            $data['created_at'] = current_time('mysql');
            $id = HearMed_DB::insert('hearmed_reference.staff', $data);
            // If insert fails (columns might not exist), try without optional columns
            if (!$id) {
                unset($data['employee_number'], $data['hire_date']);
                $id = HearMed_DB::insert('hearmed_reference.staff', $data);
            }
            $result = $id ? 1 : false;
        }

        if ($result === false) { wp_send_json_error(HearMed_DB::last_error() ?: 'Database error'); return; }

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

        $username = sanitize_text_field($_POST['username'] ?? $email);
        $auth = HearMed_Staff_Auth::ensure_auth_for_staff($id, $email, $username);
        
        if (!$auth) {
            error_log('[HearMed] Warning: Failed to create staff_auth for staff_id=' . $id . ', error: ' . HearMed_DB::last_error());
            // Don't block the save — staff record is already created
        }

        // Password handling: set if provided (only if auth record exists)
        if ($new_password !== '' && $auth) {
            // When admin sets password on creation, mark as temp so user must change on first login
            $is_temp = $is_new_staff ? true : false;
            HearMed_Staff_Auth::set_password($id, $new_password, $is_temp);
        }

        $enable_2fa = intval($_POST['two_factor_enabled'] ?? 0) === 1;
        $secret = $auth ? HearMed_Staff_Auth::set_two_factor($id, $enable_2fa) : null;

        wp_send_json_success(['id' => $id, 'totp_secret' => $secret]);
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
            wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
        } else {
            wp_send_json_success();
        }
    }
}

new HearMed_Admin_Manage_Users();
