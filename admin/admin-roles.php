<?php
/**
 * HearMed Admin — Roles & Permissions
 * Shortcode: [hearmed_admin_roles]
 *
 * CRUD for hearmed_reference.roles table.
 * Allows adding/editing/deactivating roles and assigning permissions.
 *
 * @package HearMed_Portal
 * @since   5.2.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Roles {

    private static $available_permissions = [
        'view_all'             => 'View All Data',
        'create_all'           => 'Create All Records',
        'edit_all'             => 'Edit All Records',
        'delete_all'           => 'Delete All Records',
        'manage_staff'         => 'Manage Staff',
        'manage_roles'         => 'Manage Roles',
        'view_own_clinic'      => 'View Own Clinic',
        'edit_own_clinic'      => 'Edit Own Clinic',
        'view_patients'        => 'View Patients',
        'create_patients'      => 'Create Patients',
        'edit_patients'        => 'Edit Patients',
        'view_appointments'    => 'View Appointments',
        'create_appointments'  => 'Create Appointments',
        'manage_calendar'      => 'Manage Calendar',
        'view_orders'          => 'View Orders',
        'create_orders'        => 'Create Orders',
        'edit_orders'          => 'Edit Orders',
        'dispense_products'    => 'Dispense Products',
        'view_invoices'        => 'View Invoices',
        'edit_invoices'        => 'Edit Invoices',
        'record_payments'      => 'Record Payments',
        'generate_reports'     => 'Generate Reports',
        'create_notes'         => 'Create Notes',
        'order_tests'          => 'Order Tests',
        'record_assessments'   => 'Record Assessments',
        'record_outcomes'      => 'Record Outcomes',
        'view_chat'            => 'View Team Chat',
        'manage_settings'      => 'Manage Settings',
    ];

    public function __construct() {
        add_shortcode('hearmed_admin_roles', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_role',   [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_role',  [$this, 'ajax_delete']);
    }

    private function get_roles() {
        return HearMed_DB::get_results(
            "SELECT * FROM hearmed_reference.roles WHERE is_active = true ORDER BY display_name"
        ) ?: [];
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $roles = $this->get_roles();
        $perms = self::$available_permissions;

        // Group permissions for display
        $perm_groups = [
            'System' => ['view_all','create_all','edit_all','delete_all','manage_staff','manage_roles','manage_settings'],
            'Clinic' => ['view_own_clinic','edit_own_clinic'],
            'Patients' => ['view_patients','create_patients','edit_patients'],
            'Calendar' => ['view_appointments','create_appointments','manage_calendar'],
            'Orders' => ['view_orders','create_orders','edit_orders','dispense_products'],
            'Finance' => ['view_invoices','edit_invoices','record_payments'],
            'Other' => ['generate_reports','create_notes','order_tests','record_assessments','record_outcomes','view_chat'],
        ];

        ob_start(); ?>
        <style>
        .hmr-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;}
        @media(max-width:900px){.hmr-grid{grid-template-columns:1fr;}}
        .hmr-card{background:#fff;border-radius:12px;padding:20px;box-shadow:0 2px 8px rgba(15,23,42,.04);}
        .hmr-card-hd{font-size:15px;font-weight:600;color:#0f172a;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid rgba(15,23,42,.04);}
        .hmr-role-row{display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f1f5f9;}
        .hmr-role-row:last-child{border-bottom:none;}
        .hmr-role-info{flex:1;min-width:0;}
        .hmr-role-name{font-size:13px;font-weight:600;color:#1e293b;}
        .hmr-role-slug{font-size:11px;color:#94a3b8;font-family:monospace;}
        .hmr-role-desc{font-size:11px;color:#64748b;margin-top:2px;}
        .hmr-role-meta{display:flex;align-items:center;gap:12px;flex-shrink:0;}
        .hmr-perm-count{font-size:11px;color:#64748b;white-space:nowrap;}
        .hmr-acts{display:flex;gap:4px;}
        .hmr-acts button{border:none;background:none;cursor:pointer;font-size:12px;padding:4px 8px;border-radius:6px;color:#64748b;transition:all .15s;}
        .hmr-acts button:hover{background:#f1f5f9;color:var(--hm-primary,#0BB4C4);}
        .hmr-acts button.hmr-del:hover{color:#ef4444;}
        .hm-perms-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:2px 16px;max-height:300px;overflow-y:auto;padding:8px 12px;border:1px solid var(--hm-border,#e2e8f0);border-radius:8px;background:#f8fafc;}
        .hm-perms-grid label{display:flex;align-items:center;gap:5px;font-size:12px;cursor:pointer;padding:4px 0;color:#334155;}
        .hm-perms-grid label input{accent-color:var(--hm-primary,#0BB4C4);width:14px;height:14px;margin:0;}
        .hm-perm-group-label{font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;grid-column:1/-1;margin-top:6px;padding-bottom:2px;border-bottom:1px solid #e2e8f0;}
        .hm-perm-group-label:first-child{margin-top:0;}
        @media(max-width:700px){.hm-perms-grid{grid-template-columns:1fr 1fr;}}
        </style>
        <div id="hm-app" class="hm-calendar" data-module="admin" data-view="roles">
            <div class="hm-page">
                <div style="margin-bottom:16px;"><a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-btn">&larr; Back</a></div>
                <div class="hm-page-header" style="display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <h1 class="hm-page-title" style="font-size:22px;font-weight:700;color:#0f172a;margin:0;">Roles &amp; Permissions</h1>
                        <div style="color:#94a3b8;font-size:12px;margin-top:4px;">Define roles and their permissions. Each staff member is assigned a role that controls what they can see and do.</div>
                    </div>
                    <button class="hm-btn hm-btn-teal" onclick="hmRoles.open()">+ Add Role</button>
                </div>

                <div class="hmr-grid" style="margin-top:20px;">
                    <!-- Roles List -->
                    <div class="hmr-card">
                        <div class="hmr-card-hd">Defined Roles</div>
                        <?php if (empty($roles)): ?>
                            <p style="color:#94a3b8;font-size:12px;text-align:center;padding:20px 0;">No roles defined yet. Click "+ Add Role" to get started.</p>
                        <?php else: ?>
                            <?php foreach ($roles as $r):
                                $role_perms = json_decode($r->permissions ?: '[]', true) ?: [];
                                $payload = json_encode([
                                    'id'           => (int)$r->id,
                                    'role_name'    => $r->role_name,
                                    'display_name' => $r->display_name,
                                    'description'  => $r->description,
                                    'permissions'  => $role_perms,
                                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                            ?>
                            <div class="hmr-role-row">
                                <div class="hmr-role-info">
                                    <div class="hmr-role-name"><?php echo esc_html($r->display_name); ?> <span class="hmr-role-slug"><?php echo esc_html($r->role_name); ?></span></div>
                                    <?php if ($r->description): ?>
                                        <div class="hmr-role-desc"><?php echo esc_html($r->description); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="hmr-role-meta">
                                    <span class="hmr-perm-count"><?php echo count($role_perms); ?> permission<?php echo count($role_perms) !== 1 ? 's' : ''; ?></span>
                                    <div class="hmr-acts">
                                        <button onclick='hmRoles.open(<?php echo $payload; ?>)' title="Edit">&#9998;</button>
                                        <button class="hmr-del" onclick="hmRoles.del(<?php echo (int) $r->id; ?>,'<?php echo esc_js($r->display_name); ?>')" title="Delete">&times;</button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Permissions Reference -->
                    <div class="hmr-card">
                        <div class="hmr-card-hd">Available Permissions</div>
                        <p style="color:#94a3b8;font-size:11px;margin-bottom:10px;">These permissions can be assigned to any role.</p>
                        <?php foreach ($perm_groups as $group => $keys): ?>
                            <div style="margin-bottom:10px;">
                                <div style="font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;margin-bottom:4px;"><?php echo esc_html($group); ?></div>
                                <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                    <?php foreach ($keys as $key):
                                        if (!isset($perms[$key])) continue;
                                    ?>
                                        <span style="font-size:11px;color:#475569;padding:2px 8px;background:#f1f5f9;border-radius:4px;"><?php echo esc_html($perms[$key]); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- Modal – outside #hm-app so it only shows on open -->
        <div class="hm-modal-bg" id="hm-role-modal" style="display:none;position:fixed;inset:0;align-items:center;justify-content:center;padding:24px;background:radial-gradient(circle at top left,rgba(148,163,184,.45),rgba(15,23,42,.75));backdrop-filter:blur(8px);z-index:9000;">
            <div class="hm-modal" style="width:640px">
                <div class="hm-modal-hd">
                    <h3 id="hm-role-title">Add Role</h3>
                    <button class="hm-modal-x" onclick="hmRoles.close()">&times;</button>
                </div>
                <div class="hm-modal-body">
                    <input type="hidden" id="hmrl-id">
                    <div class="hm-form-row">
                        <div class="hm-form-group">
                            <label>Display Name *</label>
                            <input type="text" id="hmrl-display" placeholder="e.g. Senior Dispenser">
                        </div>
                        <div class="hm-form-group">
                            <label>Internal Name *</label>
                            <input type="text" id="hmrl-name" placeholder="e.g. senior_dispenser">
                        </div>
                    </div>
                    <div class="hm-form-group">
                        <label>Description</label>
                        <textarea id="hmrl-desc" rows="2" placeholder="What this role does..."></textarea>
                    </div>
                    <div class="hm-form-group">
                        <label style="margin-bottom:6px;display:block;">Permissions</label>
                        <div class="hm-perms-grid" id="hmrl-perms">
                            <?php foreach ($perm_groups as $group => $keys): ?>
                                <div class="hm-perm-group-label"><?php echo esc_html($group); ?></div>
                                <?php foreach ($keys as $key):
                                    if (!isset($perms[$key])) continue;
                                ?>
                                    <label>
                                        <input type="checkbox" class="hm-role-perm" value="<?php echo esc_attr($key); ?>">
                                        <?php echo esc_html($perms[$key]); ?>
                                    </label>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn" onclick="hmRoles.close()">Cancel</button>
                    <button class="hm-btn hm-btn-teal" onclick="hmRoles.save()" id="hmrl-save">Save</button>
                </div>
            </div>
        </div>

        <script>
        var hmRoles = {
            open: function(data) {
                var edit = !!(data && data.id);
                document.getElementById('hm-role-title').textContent = edit ? 'Edit Role' : 'Add Role';
                document.getElementById('hmrl-id').value      = edit ? data.id : '';
                document.getElementById('hmrl-display').value = edit ? data.display_name : '';
                document.getElementById('hmrl-name').value    = edit ? data.role_name : '';
                document.getElementById('hmrl-desc').value    = edit ? (data.description || '') : '';

                var perms = edit && data.permissions ? data.permissions : [];
                document.querySelectorAll('.hm-role-perm').forEach(function(cb) {
                    cb.checked = perms.indexOf(cb.value) !== -1;
                });

                document.getElementById('hm-role-modal').style.display = 'flex';
            },
            close: function() { document.getElementById('hm-role-modal').style.display = 'none'; },
            save: function() {
                var display = document.getElementById('hmrl-display').value.trim();
                var name    = document.getElementById('hmrl-name').value.trim();
                if (!display || !name) { alert('Display name and internal name are required.'); return; }

                var perms = [];
                document.querySelectorAll('.hm-role-perm:checked').forEach(function(cb) {
                    perms.push(cb.value);
                });

                var btn = document.getElementById('hmrl-save');
                btn.textContent = 'Saving...'; btn.disabled = true;

                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_save_role',
                    nonce: HM.nonce,
                    id: document.getElementById('hmrl-id').value,
                    role_name: name,
                    display_name: display,
                    description: document.getElementById('hmrl-desc').value,
                    permissions: JSON.stringify(perms)
                }, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error'); btn.textContent = 'Save'; btn.disabled = false; }
                });
            },
            del: function(id, name) {
                if (!confirm('Delete role "' + name + '"?')) return;
                jQuery.post(HM.ajax_url, { action:'hm_admin_delete_role', nonce:HM.nonce, id:id }, function(r) {
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

        $id      = intval($_POST['id'] ?? 0);
        $name    = sanitize_text_field($_POST['role_name'] ?? '');
        $display = sanitize_text_field($_POST['display_name'] ?? '');
        if (!$name || !$display) { wp_send_json_error('Name and display name required'); return; }

        $perms = json_decode(stripslashes($_POST['permissions'] ?? '[]'), true);
        if (!is_array($perms)) $perms = [];

        $data = [
            'role_name'    => $name,
            'display_name' => $display,
            'description'  => sanitize_text_field($_POST['description'] ?? ''),
            'permissions'  => wp_json_encode($perms),
            'updated_at'   => current_time('mysql'),
        ];

        if ($id) {
            $result = HearMed_DB::update('hearmed_reference.roles', $data, ['id' => $id]);
        } else {
            $data['is_active']  = true;
            $data['created_at'] = current_time('mysql');
            $id = HearMed_DB::insert('hearmed_reference.roles', $data);
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
            'hearmed_reference.roles',
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

new HearMed_Admin_Roles();
