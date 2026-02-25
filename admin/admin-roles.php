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

        ob_start(); ?>
        <div class="hm-admin" id="hm-roles-admin">
            <div class="hm-admin-hd">
                <h2>Roles &amp; Permissions</h2>
                <button class="hm-btn hm-btn-teal" onclick="hmRoles.open()">+ Add Role</button>
            </div>

            <p style="color:var(--hm-text-light);font-size:13px;margin-bottom:20px;">
                Define roles and their permissions. Each staff member is assigned a role that controls what they can see and do.
            </p>

            <?php if (empty($roles)): ?>
                <div class="hm-empty-state"><p>No roles defined yet. Click "+ Add Role" to get started.</p></div>
            <?php else: ?>
                <table class="hm-table">
                    <thead>
                        <tr>
                            <th>Role</th>
                            <th>Internal Name</th>
                            <th>Description</th>
                            <th class="hm-num">Permissions</th>
                            <th style="width:100px"></th>
                        </tr>
                    </thead>
                    <tbody>
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
                        <tr>
                            <td><strong><?php echo esc_html($r->display_name); ?></strong></td>
                            <td><code style="font-size:11px;color:#64748b;"><?php echo esc_html($r->role_name); ?></code></td>
                            <td style="font-size:12px;color:var(--hm-text-light);"><?php echo esc_html($r->description ?: '—'); ?></td>
                            <td class="hm-num"><?php echo count($role_perms); ?></td>
                            <td class="hm-table-acts">
                                <button class="hm-btn hm-btn-sm" onclick='hmRoles.open(<?php echo $payload; ?>)'>Edit</button>
                                <button class="hm-btn hm-btn-sm hm-btn-red" onclick="hmRoles.del(<?php echo (int) $r->id; ?>,'<?php echo esc_js($r->display_name); ?>')">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- Modal -->
            <div class="hm-modal-bg" id="hm-role-modal">
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
                            <label>Permissions</label>
                            <div id="hmrl-perms" style="display:flex;flex-wrap:wrap;gap:10px;max-height:260px;overflow-y:auto;padding:4px 0;">
                                <?php foreach ($perms as $key => $label): ?>
                                    <label class="hm-day-check">
                                        <input type="checkbox" class="hm-role-perm" value="<?php echo esc_attr($key); ?>">
                                        <?php echo esc_html($label); ?>
                                    </label>
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

                document.getElementById('hm-role-modal').classList.add('open');
            },
            close: function() { document.getElementById('hm-role-modal').classList.remove('open'); },
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
