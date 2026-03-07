<?php
/**
 * HearMed Admin — Staff Management
 * Shortcode: [hearmed_manage_users]
 *
 * Rebuilt for Auth V2. On save:
 *  1. Creates / updates hearmed_reference.staff
 *  2. Auto-creates staff_auth (PG login) with temp password
 *  3. Auto-creates a shadow WP user (for sidebar visibility only — WP login is blocked)
 *  4. Maps PG role → WP role for sidebar menu scoping
 *
 * @package HearMed_Portal
 * @since   5.1.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Manage_Users {

    /** Default temporary password assigned to new staff */
    const DEFAULT_TEMP_PASSWORD = 'Hearmed1674!';

    public function __construct() {
        add_shortcode('hearmed_manage_users', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_staff',   [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_staff',  [$this, 'ajax_delete']);
        add_action('wp_ajax_hm_admin_reset_password', [$this, 'ajax_reset_password']);

        // Suppress WP welcome emails for programmatically-created users
        if ( ! has_filter( 'wp_new_user_notification_email', '__return_false' ) ) {
            add_filter( 'wp_new_user_notification_email',       '__return_false' );
            add_filter( 'wp_new_user_notification_email_admin', '__return_false' );
        }
    }

    /* ------------------------------------------------------------------ */
    /*  Data helpers                                                       */
    /* ------------------------------------------------------------------ */

    private function get_staff() {
        return HearMed_DB::get_results(
            "SELECT s.id, s.first_name, s.last_name, s.email, s.phone, s.role,
                    s.employee_number, s.hire_date, s.is_active, s.wp_user_id,
                    a.username   AS auth_username,
                    a.temp_password,
                    a.two_factor_enabled,
                    a.last_login,
                    a.staff_id   AS has_auth
             FROM hearmed_reference.staff s
             LEFT JOIN hearmed_reference.staff_auth a ON s.id = a.staff_id
             ORDER BY s.is_active DESC, s.last_name, s.first_name"
        ) ?: [];
    }

    private function get_clinics() {
        return HearMed_DB::get_results(
            "SELECT id, clinic_name FROM hearmed_reference.clinics WHERE is_active = true ORDER BY clinic_name"
        ) ?: [];
    }

    private function get_roles() {
        return HearMed_DB::get_results(
            "SELECT id, role_name, display_name FROM hearmed_reference.roles WHERE is_active = true ORDER BY display_name"
        ) ?: [];
    }

    private function get_staff_clinics() {
        return HearMed_DB::get_results(
            "SELECT staff_id, clinic_id, is_primary_clinic FROM hearmed_reference.staff_clinics"
        ) ?: [];
    }

    /* ------------------------------------------------------------------ */
    /*  Render                                                             */
    /* ------------------------------------------------------------------ */

    public function render() {
        if ( ! PortalAuth::is_logged_in() ) return '<p>Please log in.</p>';

        $staff          = $this->get_staff();
        $clinics        = $this->get_clinics();
        $roles          = $this->get_roles();
        $staff_clinics  = $this->get_staff_clinics();

        // Build clinic map
        $clinic_map = [];
        foreach ($clinics as $c) $clinic_map[$c->id] = $c->clinic_name;

        // Build staff → clinic map
        $sc_map = [];
        foreach ($staff_clinics as $sc) {
            $sid = (int) $sc->staff_id;
            if (!isset($sc_map[$sid])) $sc_map[$sid] = ['clinics' => [], 'primary' => null];
            $sc_map[$sid]['clinics'][] = (int) $sc->clinic_id;
            if ($sc->is_primary_clinic) $sc_map[$sid]['primary'] = (int) $sc->clinic_id;
        }

        // Build payload for JS
        $staff_payload = [];
        foreach ($staff as $s) {
            $sid         = (int) $s->id;
            $clinic_ids  = $sc_map[$sid]['clinics'] ?? [];
            $primary_id  = $sc_map[$sid]['primary'] ?? null;
            $clinic_labels = [];
            foreach ($clinic_ids as $cid) {
                if (isset($clinic_map[$cid])) $clinic_labels[] = $clinic_map[$cid];
            }

            $staff_payload[] = [
                'id'               => $sid,
                'first_name'       => $s->first_name ?? '',
                'last_name'        => $s->last_name ?? '',
                'email'            => $s->email ?? '',
                'phone'            => $s->phone ?? '',
                'role'             => $s->role ?? '',
                'employee_number'  => $s->employee_number ?? '',
                'hire_date'        => $s->hire_date ?? '',
                'is_active'        => (bool) ($s->is_active ?? false),
                'wp_user_id'       => $s->wp_user_id ?? null,
                'auth_username'    => $s->auth_username ?? '',
                'has_auth'         => !empty($s->has_auth),
                'temp_password'    => (bool) ($s->temp_password ?? false),
                'two_factor'       => (bool) ($s->two_factor_enabled ?? false),
                'last_login'       => $s->last_login ?? null,
                'clinic_ids'       => $clinic_ids,
                'primary_clinic_id'=> $primary_id,
                'clinic_labels'    => $clinic_labels,
            ];
        }

        ob_start(); ?>
        <style>
        /* Staff page specific styles */
        .hm-staff-table th, .hm-staff-table td { vertical-align:middle; }
        .hm-staff-meta { font-size:12px; color:var(--hm-text-light,#64748b); line-height:1.4; }
        .hm-staff-meta strong { color:var(--hm-text,#1e293b); }
        .hm-auth-badges { display:flex; gap:4px; flex-wrap:wrap; }
        .hm-auth-badge { display:inline-flex; align-items:center; gap:3px; font-size:11px; font-weight:600;
                         padding:2px 8px; border-radius:10px; white-space:nowrap; }
        .hm-auth-badge--green  { background:#dcfce7; color:#166534; }
        .hm-auth-badge--amber  { background:#fef3c7; color:#92400e; }
        .hm-auth-badge--red    { background:#fee2e2; color:#991b1b; }
        .hm-auth-badge--blue   { background:#dbeafe; color:#1e40af; }
        .hm-auth-badge--gray   { background:#f1f5f9; color:#475569; }
        .hm-staff-row--inactive td { opacity:0.55; }
        .hm-staff-row--inactive td:last-child { opacity:1; }
        .hm-login-info { margin-top:12px; padding:14px; background:#f0f9ff; border:1px solid #bae6fd;
                         border-radius:8px; font-size:13px; line-height:1.6; }
        .hm-login-info code { background:#e0f2fe; padding:1px 6px; border-radius:4px; font-size:12px; }
        .hm-login-info strong { color:#0c4a6e; }
        .hm-section-divider { height:1px; background:#e2e8f0; margin:14px 0 10px; }
        .hm-modal-section-title { font-size:13px; font-weight:700; color:#334155; margin:0 0 10px;
                                  text-transform:uppercase; letter-spacing:0.5px; }

        /* Password section */
        .hm-pass-section { margin-top:4px; padding:14px; background:#fffbeb; border:1px solid #fde68a;
                           border-radius:8px; }
        .hm-pass-section.hm-pass-section--edit { background:#f8fafc; border-color:#e2e8f0; }
        .hm-pass-hint { font-size:12px; color:var(--hm-text-light,#64748b); margin-top:6px; }
        </style>

        <div class="hm-admin" id="hm-users-admin">
            <a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-back">← Back</a>
            <div class="hm-page-header">
                <h1 class="hm-page-title">Staff Management</h1>
                <div class="hm-page-header__actions">
                    <button class="hm-btn hm-btn--primary" onclick="hmUsers.open()">+ Add Staff</button>
                </div>
            </div>

            <?php if (empty($staff_payload)): ?>
                <div class="hm-empty-state"><p>No staff added yet.</p></div>
            <?php else: ?>
            <table class="hm-table hm-staff-table">
                <thead>
                    <tr>
                        <th>Staff Member</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Employee #</th>
                        <th>Role</th>
                        <th>Clinics</th>
                        <th>Primary Clinic</th>
                        <th>Auth Status</th>
                        <th style="width:130px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($staff_payload as $u):
                    $payload_json = json_encode($u, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                    $name = trim($u['first_name'] . ' ' . $u['last_name']);
                    $row_class = $u['is_active'] ? '' : ' hm-staff-row--inactive';
                    $primary_clinic_name = '—';
                    if (!empty($u['primary_clinic_id']) && isset($clinic_map[(int)$u['primary_clinic_id']])) {
                        $primary_clinic_name = $clinic_map[(int)$u['primary_clinic_id']];
                    }
                ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td>
                            <strong><?php echo esc_html($name); ?></strong>
                        </td>
                        <td><?php echo !empty($u['email']) ? esc_html($u['email']) : '<span style="color:var(--hm-text-light)">—</span>'; ?></td>
                        <td><?php echo !empty($u['phone']) ? esc_html($u['phone']) : '<span style="color:var(--hm-text-light)">—</span>'; ?></td>
                        <td><?php echo !empty($u['employee_number']) ? ('Emp #' . esc_html($u['employee_number'])) : '<span style="color:var(--hm-text-light)">—</span>'; ?></td>
                        <td>
                            <?php
                            // Find display name from roles
                            $role_display = $u['role'] ?: '—';
                            foreach ($roles as $r) {
                                if ($r->role_name === $u['role']) { $role_display = $r->display_name; break; }
                            }
                            echo esc_html($role_display);
                            ?>
                        </td>
                        <td>
                            <?php if (!empty($u['clinic_labels'])): ?>
                                <?php echo esc_html(implode(', ', $u['clinic_labels'])); ?>
                            <?php else: ?>
                                <span style="color:var(--hm-text-light)">—</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($primary_clinic_name); ?></td>
                        <td>
                            <div class="hm-auth-badges">
                                <?php if (!$u['is_active']): ?>
                                    <span class="hm-auth-badge hm-auth-badge--red">Deactivated</span>
                                <?php elseif (!$u['has_auth']): ?>
                                    <span class="hm-auth-badge hm-auth-badge--red">No Login</span>
                                <?php else: ?>
                                    <?php if ($u['temp_password']): ?>
                                        <span class="hm-auth-badge hm-auth-badge--amber" title="Must change password on next login">Temp Pass</span>
                                    <?php else: ?>
                                        <span class="hm-auth-badge hm-auth-badge--green">Active</span>
                                    <?php endif; ?>
                                    <?php if ($u['two_factor']): ?>
                                        <span class="hm-auth-badge hm-auth-badge--blue">2FA</span>
                                    <?php endif; ?>
                                    <?php if ($u['last_login']): ?>
                                        <span class="hm-auth-badge hm-auth-badge--gray" title="<?php echo esc_attr($u['last_login']); ?>">
                                            Last: <?php echo esc_html(date('j M', strtotime($u['last_login']))); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="hm-auth-badge hm-auth-badge--gray">Never logged in</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="hm-table-acts">
                            <button class="hm-btn hm-btn--sm" onclick='hmUsers.open(<?php echo $payload_json; ?>)'>Edit</button>
                            <?php if ($u['is_active']): ?>
                                <button class="hm-btn hm-btn--sm hm-btn--danger" onclick="hmUsers.deactivate(<?php echo (int) $u['id']; ?>,'<?php echo esc_js($name); ?>')">Deactivate</button>
                            <?php else: ?>
                                <button class="hm-btn hm-btn--sm hm-btn--success" onclick="hmUsers.reactivate(<?php echo (int) $u['id']; ?>)">Reactivate</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- ===== Add / Edit Modal ===== -->
            <div class="hm-modal-bg" id="hm-user-modal">
                <div class="hm-modal hm-modal--lg">
                    <div class="hm-modal-hd">
                        <h3 id="hm-user-title">Add Staff</h3>
                        <button class="hm-close" onclick="hmUsers.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hmu-id">

                        <p class="hm-modal-section-title">Personal Details</p>
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
                                <input type="email" id="hmu-email">
                            </div>
                            <div class="hm-form-group">
                                <label>Phone</label>
                                <input type="text" id="hmu-phone">
                            </div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Employee Number</label>
                                <input type="text" id="hmu-emp">
                            </div>
                            <div class="hm-form-group">
                                <label>Hire Date</label>
                                <input type="date" id="hmu-hire">
                            </div>
                        </div>

                        <div class="hm-section-divider"></div>
                        <p class="hm-modal-section-title">Role & Clinics</p>

                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Role *</label>
                                <select id="hmu-role">
                                    <option value="">— Select Role —</option>
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?php echo esc_attr($r->role_name); ?>"><?php echo esc_html($r->display_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="hm-form-group">
                                <label class="hm-toggle">
                                    <input type="checkbox" id="hmu-active" checked>
                                    <span class="hm-toggle-track"></span>
                                    Active
                                </label>
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
                        </div>

                        <div class="hm-section-divider"></div>
                        <p class="hm-modal-section-title">Login Settings</p>

                        <div class="hm-form-row">
                            <div class="hm-form-group" style="flex:1">
                                <label>Login Username</label>
                                <input type="text" id="hmu-username" placeholder="Defaults to email address">
                            </div>
                        </div>

                        <!-- New staff info -->
                        <div id="hmu-new-info" class="hm-login-info" style="display:none;">
                            <strong>New staff login will be created automatically:</strong><br>
                            • Username = email (or custom above)<br>
                            • Temporary password: <code><?php echo esc_html(self::DEFAULT_TEMP_PASSWORD); ?></code><br>
                            • Staff must change password on first login<br>
                            • 2FA setup will be prompted on first login<br>
                            • A WordPress account is auto-created for sidebar menu scoping (login is blocked)
                        </div>

                        <!-- Edit staff info -->
                        <div id="hmu-edit-info" class="hm-login-info" style="display:none;">
                            <strong>Current auth:</strong>
                            <span id="hmu-auth-status"></span>
                        </div>

                        <!-- Password section for edit mode -->
                        <div id="hmu-pass-section" class="hm-pass-section hm-pass-section--edit" style="display:none;">
                            <div class="hm-form-row">
                                <div class="hm-form-group">
                                    <label>New Password</label>
                                    <input type="password" id="hmu-pass" placeholder="Leave blank to keep current" onkeyup="hmUsers.checkPassStrength()">
                                    <div id="hmu-pass-strength" style="font-size:12px;margin-top:4px;font-weight:500;"></div>
                                </div>
                                <div class="hm-form-group">
                                    <label>Confirm Password</label>
                                    <input type="password" id="hmu-pass2" onkeyup="hmUsers.checkPassMatch()">
                                    <div id="hmu-pass-match" style="font-size:12px;margin-top:4px;font-weight:500;"></div>
                                </div>
                            </div>
                            <div class="hm-pass-hint">
                                Min 8 chars, 1 uppercase, 1 special character. Will be marked as temporary (forced change on next login).
                            </div>
                            <div style="margin-top:8px;">
                                <button type="button" class="hm-btn hm-btn--sm" onclick="hmUsers.resetPassword()" id="hmu-reset-btn"
                                        title="Reset to default temporary password">
                                    Reset to Default Password
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmUsers.close()">Cancel</button>
                        <button class="hm-btn hm-btn--primary" onclick="hmUsers.save()" id="hmu-save">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        var hmUsers = {
            clinics: <?php echo json_encode(array_map(function($c){ return ['id'=>(int)$c->id,'name'=>$c->clinic_name]; }, $clinics), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
            roles: <?php echo json_encode(array_map(function($r){ return ['name'=>$r->role_name,'display'=>$r->display_name]; }, $roles), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
            editing: null,

            /* ---------- open / close ---------- */
            open: function(data) {
                var isEdit = !!(data && data.id);
                this.editing = isEdit ? data : null;

                document.getElementById('hm-user-title').textContent = isEdit ? 'Edit Staff' : 'Add Staff';
                document.getElementById('hmu-id').value     = isEdit ? data.id : '';
                document.getElementById('hmu-first').value  = isEdit ? data.first_name : '';
                document.getElementById('hmu-last').value   = isEdit ? data.last_name : '';
                document.getElementById('hmu-email').value  = isEdit ? data.email : '';
                document.getElementById('hmu-phone').value  = isEdit ? (data.phone || '') : '';
                document.getElementById('hmu-role').value   = isEdit ? (data.role || '') : '';
                document.getElementById('hmu-emp').value    = isEdit ? (data.employee_number || '') : '';
                document.getElementById('hmu-hire').value   = isEdit ? (data.hire_date || '') : '';
                document.getElementById('hmu-active').checked = isEdit ? !!data.is_active : true;
                document.getElementById('hmu-username').value = isEdit ? (data.auth_username || '') : '';
                document.getElementById('hmu-pass').value   = '';
                document.getElementById('hmu-pass2').value  = '';
                document.getElementById('hmu-pass-strength').textContent = '';
                document.getElementById('hmu-pass-match').textContent = '';

                // Toggle info panels
                document.getElementById('hmu-new-info').style.display  = isEdit ? 'none' : 'block';
                document.getElementById('hmu-edit-info').style.display = isEdit ? 'block' : 'none';
                document.getElementById('hmu-pass-section').style.display = isEdit ? 'block' : 'none';

                if (isEdit) {
                    var parts = [];
                    if (data.has_auth) {
                        parts.push(data.temp_password ? '⚠️ Temp password (not yet changed)' : '✅ Password set');
                        parts.push(data.two_factor ? '🔒 2FA enabled' : '2FA not yet set up');
                        parts.push(data.last_login ? 'Last login: ' + data.last_login : 'Never logged in');
                    } else {
                        parts.push('❌ No auth record — will be created on save');
                    }
                    if (data.wp_user_id) {
                        parts.push('WP User #' + data.wp_user_id);
                    } else {
                        parts.push('WP user will be auto-created on save');
                    }
                    document.getElementById('hmu-auth-status').innerHTML = '<br>' + parts.map(function(p){ return '• ' + p; }).join('<br>');
                }

                // Clinics
                document.querySelectorAll('.hm-staff-clinic').forEach(function(cb) {
                    cb.checked = isEdit && data.clinic_ids ? data.clinic_ids.indexOf(parseInt(cb.value,10)) !== -1 : false;
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
                this.editing = null;
            },

            /* ---------- clinic selection ---------- */
            refreshPrimary: function() {
                var sel = document.getElementById('hmu-primary');
                var selected = [];
                document.querySelectorAll('.hm-staff-clinic:checked').forEach(function(cb) {
                    selected.push(parseInt(cb.value,10));
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
                if (current && selected.indexOf(parseInt(current,10)) !== -1) sel.value = current;
            },

            /* ---------- password helpers ---------- */
            validatePass: function(p) {
                if (p.length < 8) return 'At least 8 characters';
                if (!/[A-Z]/.test(p)) return 'At least one uppercase letter';
                if (!/[!@#$%^&*()_\-=+\[\]{}|;:\'",.<>?\/\\]/.test(p)) return 'At least one special character';
                return null;
            },
            checkPassStrength: function() {
                var p = document.getElementById('hmu-pass').value;
                var el = document.getElementById('hmu-pass-strength');
                if (!p) { el.textContent = ''; return; }
                var err = this.validatePass(p);
                el.textContent = err ? ('⚠ ' + err) : '✓ Strong';
                el.style.color = err ? '#dc2626' : '#16a34a';
            },
            checkPassMatch: function() {
                var p1 = document.getElementById('hmu-pass').value;
                var p2 = document.getElementById('hmu-pass2').value;
                var el = document.getElementById('hmu-pass-match');
                if (!p2) { el.textContent = ''; return; }
                el.textContent = p1 === p2 ? '✓ Match' : '✗ No match';
                el.style.color = p1 === p2 ? '#16a34a' : '#dc2626';
            },

            /* ---------- save ---------- */
            save: function() {
                var first = document.getElementById('hmu-first').value.trim();
                var last  = document.getElementById('hmu-last').value.trim();
                var email = document.getElementById('hmu-email').value.trim();
                var role  = document.getElementById('hmu-role').value.trim();
                if (!first || !last || !email || !role) {
                    alert('First name, last name, email and role are required.');
                    return;
                }

                // Password validation (edit mode only — new staff gets default temp)
                var pass = document.getElementById('hmu-pass').value;
                var pass2 = document.getElementById('hmu-pass2').value;
                if (pass) {
                    var err = this.validatePass(pass);
                    if (err) { alert('Password: ' + err); return; }
                    if (pass !== pass2) { alert('Passwords do not match.'); return; }
                }

                var clinicIds = [];
                document.querySelectorAll('.hm-staff-clinic:checked').forEach(function(cb) {
                    clinicIds.push(parseInt(cb.value,10));
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
                    is_active: document.getElementById('hmu-active').checked ? 1 : 0,
                    username: document.getElementById('hmu-username').value,
                    new_password: pass,
                    clinics: JSON.stringify(clinicIds),
                    primary_clinic_id: document.getElementById('hmu-primary').value
                };

                var btn = document.getElementById('hmu-save');
                btn.textContent = 'Saving…'; btn.disabled = true;
                jQuery.post(HM.ajax_url, payload, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error'); btn.textContent = 'Save'; btn.disabled = false; }
                });
            },

            /* ---------- deactivate / reactivate ---------- */
            deactivate: function(id, name) {
                if (!confirm('Deactivate "' + name + '"? They will lose login access immediately.')) return;
                jQuery.post(HM.ajax_url, { action:'hm_admin_delete_staff', nonce:HM.nonce, id:id }, function(r) {
                    if (r.success) location.reload(); else alert(r.data || 'Error');
                });
            },
            reactivate: function(id) {
                if (!confirm('Reactivate this staff member?')) return;
                jQuery.post(HM.ajax_url, { action:'hm_admin_save_staff', nonce:HM.nonce, id:id, reactivate:1 }, function(r) {
                    if (r.success) location.reload(); else alert(r.data || 'Error');
                });
            },

            /* ---------- reset password to temp ---------- */
            resetPassword: function() {
                if (!this.editing || !this.editing.id) return;
                if (!confirm('Reset password to default temporary? Staff will need to change it on next login.')) return;
                var btn = document.getElementById('hmu-reset-btn');
                btn.textContent = 'Resetting…'; btn.disabled = true;
                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_reset_password',
                    nonce: HM.nonce,
                    staff_id: this.editing.id
                }, function(r) {
                    btn.textContent = 'Reset to Default Password'; btn.disabled = false;
                    if (r.success) { alert('Password reset. Staff must change it on next login.'); location.reload(); }
                    else alert(r.data || 'Error');
                });
            }
        };
        </script>
        <?php
        return ob_get_clean();
    }

    /* ================================================================== */
    /*  AJAX: Save Staff                                                   */
    /* ================================================================== */

    public function ajax_save() {
        check_ajax_referer('hm_nonce', 'nonce');
        if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error('Not authenticated'); return; }

        // Quick reactivate path
        if ( ! empty($_POST['reactivate']) ) {
            $rid = intval($_POST['id'] ?? 0);
            if ($rid) {
                HearMed_DB::update('hearmed_reference.staff',
                    ['is_active' => true, 'updated_at' => current_time('mysql')],
                    ['id' => $rid]
                );
            }
            wp_send_json_success();
            return;
        }

        $id    = intval($_POST['id'] ?? 0);
        $first = sanitize_text_field($_POST['first_name'] ?? '');
        $last  = sanitize_text_field($_POST['last_name'] ?? '');
        $email = sanitize_email($_POST['email'] ?? '');
        $role  = sanitize_text_field($_POST['role'] ?? '');

        if (!$first || !$last || !$email || !$role) {
            wp_send_json_error('First name, last name, email and role are required.');
            return;
        }

        // Password validation (only when explicitly provided — new staff uses default)
        $new_password = trim((string) ($_POST['new_password'] ?? ''));
        if ($new_password !== '') {
            if (strlen($new_password) < 8)               { wp_send_json_error('Password must be at least 8 characters'); return; }
            if (!preg_match('/[A-Z]/', $new_password))    { wp_send_json_error('Password needs at least one uppercase letter'); return; }
            if (!preg_match('/[!@#$%^&*()_\-=+\[\]{}|;:\'",.><?\\/\\\\]/', $new_password)) {
                wp_send_json_error('Password needs at least one special character'); return;
            }
        }

        // Build staff record
        $data = [
            'first_name' => $first,
            'last_name'  => $last,
            'email'      => $email,
            'phone'      => sanitize_text_field($_POST['phone'] ?? ''),
            'role'       => $role,
            'is_active'  => intval($_POST['is_active'] ?? 1) ? true : false,
            'updated_at' => current_time('mysql'),
        ];

        $emp = sanitize_text_field($_POST['employee_number'] ?? '');
        if ($emp !== '') $data['employee_number'] = intval($emp);

        $hire = sanitize_text_field($_POST['hire_date'] ?? '');
        if ($hire !== '') $data['hire_date'] = $hire;

        $is_new = !$id;

        if ($id) {
            $result = HearMed_DB::update('hearmed_reference.staff', $data, ['id' => $id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $id = HearMed_DB::insert('hearmed_reference.staff', $data);
            $result = $id ? 1 : false;
        }

        if ($result === false) {
            $err = HearMed_DB::last_error() ?: 'Database error';
            error_log('[HearMed Staff Save] Failed: ' . $err);
            wp_send_json_error($err);
            return;
        }

        /* --- Clinics --- */
        $clinic_ids = json_decode(stripslashes($_POST['clinics'] ?? '[]'), true);
        if (!is_array($clinic_ids)) $clinic_ids = [];
        $primary_id = ($_POST['primary_clinic_id'] ?? '') !== '' ? intval($_POST['primary_clinic_id']) : null;

        HearMed_DB::query("DELETE FROM hearmed_reference.staff_clinics WHERE staff_id = $1", [$id]);

        foreach ($clinic_ids as $cid) {
            $cid = intval($cid);
            if (!$cid) continue;
            HearMed_DB::insert('hearmed_reference.staff_clinics', [
                'staff_id'          => $id,
                'clinic_id'         => $cid,
                'is_primary_clinic' => $primary_id === $cid,
                'created_at'        => current_time('mysql'),
            ]);
        }

        /* --- Auth V2: ensure staff_auth record --- */
        $username = sanitize_text_field($_POST['username'] ?? '');
        if ($username === '') $username = $email;

        $auth = HearMed_Staff_Auth::ensure_auth_for_staff($id, $email, $username);
        if (!$auth) {
            error_log('[HearMed Staff] Warning: ensure_auth_for_staff failed for id=' . $id);
        }

        /* --- Password --- */
        if ($is_new && $new_password === '') {
            // New staff → set default temp password
            HearMed_Staff_Auth::set_password($id, self::DEFAULT_TEMP_PASSWORD, true);
        } elseif ($new_password !== '') {
            // Explicit password from admin → mark as temp so user must change
            HearMed_Staff_Auth::set_password($id, $new_password, true);
        }

        /* --- Auto-create WP user for sidebar scoping --- */
        $wp_id = HearMed_Staff_Auth::ensure_wp_user_for_staff($id, $email, $username, $role);
        if ($wp_id && !$is_new) {
            // Update WP role if role changed
            $wp_role = self::pg_role_to_wp_role($role);
            $user = get_user_by('id', $wp_id);
            if ($user) $user->set_role($wp_role);
        }

        wp_send_json_success(['id' => $id, 'wp_user_id' => $wp_id]);
    }

    /* ================================================================== */
    /*  AJAX: Deactivate Staff                                             */
    /* ================================================================== */

    public function ajax_delete() {
        check_ajax_referer('hm_nonce', 'nonce');
        if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error('Not authenticated'); return; }

        $id = intval($_POST['id'] ?? 0);
        if (!$id) { wp_send_json_error('Invalid ID'); return; }

        // Soft-delete: deactivate staff
        $result = HearMed_DB::update(
            'hearmed_reference.staff',
            ['is_active' => false, 'updated_at' => current_time('mysql')],
            ['id' => $id]
        );

        if ($result === false) {
            wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
            return;
        }

        // Also deactivate the WP user so they lose sidebar access
        $staff = HearMed_DB::get_row("SELECT wp_user_id FROM hearmed_reference.staff WHERE id = $1", [$id]);
        if ($staff && !empty($staff->wp_user_id)) {
            $wp_user = get_user_by('id', (int) $staff->wp_user_id);
            if ($wp_user && !user_can($wp_user, 'manage_options')) {
                // Set role to subscriber (no sidebar access)
                $wp_user->set_role('subscriber');
            }
        }

        wp_send_json_success();
    }

    /* ================================================================== */
    /*  AJAX: Reset Password to Default                                    */
    /* ================================================================== */

    public function ajax_reset_password() {
        check_ajax_referer('hm_nonce', 'nonce');
        if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error('Not authenticated'); return; }

        $staff_id = intval($_POST['staff_id'] ?? 0);
        if (!$staff_id) { wp_send_json_error('Invalid staff ID'); return; }

        HearMed_Staff_Auth::set_password($staff_id, self::DEFAULT_TEMP_PASSWORD, true);
        wp_send_json_success();
    }

    /* ================================================================== */
    /*  Helpers                                                            */
    /* ================================================================== */

    /**
     * Map a PG role_name to a WP role slug for sidebar scoping.
     */
    private static function pg_role_to_wp_role( $pg_role ) {
        $map = [
            'c_level'            => 'hm_clevel',
            'administrator'      => 'hm_admin',
            'admin'              => 'hm_admin',
            'finance'            => 'hm_finance',
            'dispenser'          => 'hm_dispenser',
            'reception'          => 'hm_reception',
            'clinical_assistant' => 'hm_ca',
            'ca'                 => 'hm_ca',
            'scheme_other'       => 'hm_scheme',
        ];
        $key = strtolower(trim($pg_role));
        return $map[$key] ?? 'hm_dispenser';
    }
}

new HearMed_Admin_Manage_Users();
