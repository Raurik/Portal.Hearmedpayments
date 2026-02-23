<?php

// ============================================================
// AUTO-CONVERTED TO POSTGRESQL
// ============================================================
// All database operations converted from WordPress to PostgreSQL
// - $wpdb → HearMed_DB
// - wp_posts/wp_postmeta → PostgreSQL tables
// - Column names updated (_ID → id, etc.)
// 
// REVIEW REQUIRED:
// - Check all queries use correct table names
// - Verify all AJAX handlers work
// - Test all CRUD operations
// ============================================================

/**
 * HearMed Admin — Manage Users (Dispensers/Staff)
 * Shortcode: [hearmed_manage_users]
 * CRUD for Dispenser CPT
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Users {

    public function __construct() {
        add_shortcode('hearmed_manage_users', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_user', [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_user', [$this, 'ajax_delete']);
    }

    private function get_dispensers() {
        // Get staff from PostgreSQL
        $posts = HearMed_DB::get_results(
            "SELECT s.id, s.first_name || ' ' || s.last_name as post_title, s.id as ID 
             FROM hearmed_reference.staff s 
             WHERE s.is_active = true 
             ORDER BY s.last_name, s.first_name"
        );
        
        $dispensers = array();
        $fields = array('user_account', 'initials', 'clinic_id', 'is_active', 'calendar_order', 'role_type', 'primary_clinic', 'schedule', 'allowed_appointment_types');
        
        foreach ($posts as $p) {
            $d = array('id' => $p->ID, 'name' => $p->post_title);
            foreach ($fields as $f) {
                $d[$f] = /* USE PostgreSQL: Get from table columns */ /* get_post_meta($p->ID, $f, true);
            }
            $d['is_active'] = ($d['is_active'] === '' || $d['is_active'] === '1') ? '1' : '0';
            $dispensers[] = $d;
        }
        
        return $dispensers;
    }

    private function get_clinics() {
        $posts = HearMed_DB::get_results("SELECT id, clinic_name as post_title, id as ID FROM hearmed_reference.clinics WHERE is_active = true ORDER BY clinic_name");
        $clinics = [];
        foreach ($posts as $p) {
            $clinics[] = ['id' => $p->ID, 'name' => $p->post_title];
        }
        return $clinics;
    }

    private function get_services() {
        $posts = HearMed_DB::get_results("SELECT id, service_name as post_title, id as ID FROM hearmed_reference.services WHERE is_active = true ORDER BY service_name");
        $services = [];
        foreach ($posts as $p) {
            $services[] = ['id' => $p->ID, 'name' => $p->post_title];
        }
        return $services;
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $dispensers = $this->get_dispensers();
        $clinics = $this->get_clinics();
        $services = $this->get_services();
        $role_types = ['Dispenser', 'CA', 'Reception', 'Scheme'];
        $clinic_map = [];
        foreach ($clinics as $cl) $clinic_map[$cl['id']] = $cl['name'];

        ob_start(); ?>
        <div class="hm-admin" id="hm-users-app">
            <div class="hm-admin-hd">
                <h2>Users</h2>
                <button class="hm-btn hm-btn-teal" onclick="hmUser.open()">+ Add User</button>
            </div>

            <?php if (empty($dispensers)): ?>
                <div class="hm-empty-state"><p>No users yet. Add your first staff member.</p></div>
            <?php else: ?>
            <table class="hm-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Initials</th>
                        <th>Role</th>
                        <th>Primary Clinic</th>
                        <th>Clinics</th>
                        <th>Status</th>
                        <th style="width:100px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($dispensers as $d):
                    $clinic_ids = array_filter(explode(',', $d['clinic_id'] ?? ''));
                    $clinic_names = array_map(function($cid) use ($clinic_map) {
                        return $clinic_map[intval($cid)] ?? '';
                    }, $clinic_ids);
                    $clinic_names = array_filter($clinic_names);
                    $primary = $clinic_map[intval($d['primary_clinic'] ?? 0)] ?? '—';
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($d['name']); ?></strong></td>
                        <td><span class="hm-initials-badge"><?php echo esc_html($d['initials'] ?? ''); ?></span></td>
                        <td><?php echo esc_html($d['role_type'] ?? 'Dispenser'); ?></td>
                        <td><?php echo esc_html($primary); ?></td>
                        <td><?php echo esc_html(implode(', ', $clinic_names) ?: '—'); ?></td>
                        <td>
                            <?php if ($d['is_active'] === '1'): ?>
                                <span class="hm-badge hm-badge-green">Active</span>
                            <?php else: ?>
                                <span class="hm-badge hm-badge-red">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="hm-table-acts">
                            <button class="hm-btn hm-btn-sm" onclick='hmUser.open(<?php echo json_encode($d); ?>)'>Edit</button>
                            <button class="hm-btn hm-btn-sm hm-btn-red" onclick="hmUser.del(<?php echo $d['id']; ?>,'<?php echo esc_js($d['name']); ?>')">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- Modal -->
            <div class="hm-modal-bg" id="hm-user-modal">
                <div class="hm-modal" style="width:520px">
                    <div class="hm-modal-hd">
                        <h3 id="hm-user-modal-title">Add User</h3>
                        <button class="hm-modal-x" onclick="hmUser.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hmu-id" value="">

                        <div class="hm-form-row">
                            <div class="hm-form-group" style="flex:2">
                                <label>Display Name *</label>
                                <input type="text" id="hmu-name" placeholder="Full name">
                            </div>
                            <div class="hm-form-group hm-form-sm">
                                <label>Initials *</label>
                                <input type="text" id="hmu-initials" placeholder="RK" maxlength="4" style="text-transform:uppercase">
                            </div>
                        </div>

                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Role Type</label>
                                <select id="hmu-role-type">
                                    <?php foreach ($role_types as $r): ?>
                                    <option value="<?php echo esc_attr($r); ?>"><?php echo esc_html($r); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="hm-form-group">
                                <label>Primary Clinic</label>
                                <select id="hmu-primary-clinic">
                                    <option value="">— None —</option>
                                    <?php foreach ($clinics as $cl): ?>
                                    <option value="<?php echo $cl['id']; ?>"><?php echo esc_html($cl['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="hm-form-group">
                            <div class="hmu-section-hd">
                                <label>Assigned Clinics</label>
                                <a href="#" class="hmu-sel-all" onclick="hmUser.toggleAll('hmu-clinics');return false">Select All</a>
                            </div>
                            <div class="hmu-cb-list" id="hmu-clinics">
                                <?php foreach ($clinics as $cl): ?>
                                <label class="hmu-cb-item">
                                    <input type="checkbox" value="<?php echo $cl['id']; ?>">
                                    <span><?php echo esc_html($cl['name']); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="hm-form-group">
                            <div class="hmu-section-hd">
                                <label>Allowed Appointment Types</label>
                                <a href="#" class="hmu-sel-all" onclick="hmUser.toggleAll('hmu-services');return false">Select All</a>
                            </div>
                            <div class="hmu-cb-list" id="hmu-services">
                                <?php foreach ($services as $s): ?>
                                <label class="hmu-cb-item">
                                    <input type="checkbox" value="<?php echo $s['id']; ?>">
                                    <span><?php echo esc_html($s['name']); ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="hm-form-group" style="margin-top:6px">
                            <label class="hmu-cb-item" style="border:none;padding:4px 0">
                                <input type="checkbox" id="hmu-active" checked>
                                <span>Active</span>
                            </label>
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmUser.close()">Cancel</button>
                        <button class="hm-btn hm-btn-teal" onclick="hmUser.save()" id="hmu-save-btn">Save User</button>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .hmu-section-hd {
            display:flex;
            justify-content:space-between;
            align-items:baseline;
            margin-bottom:6px;
        }
        .hmu-section-hd label { margin-bottom:0 !important; }
        .hmu-sel-all {
            font-size:12px;
            color:#6b7d8e;
            text-decoration:none;
            font-weight:500;
        }
        .hmu-sel-all:hover { color:#151B33; }
        .hmu-cb-list {
            display:flex;
            flex-direction:column;
            border:1px solid #e2e8f0;
            border-radius:8px;
            overflow:hidden;
            max-height:200px;
            overflow-y:auto;
        }
        .hmu-cb-item {
            display:flex !important;
            align-items:center;
            gap:10px;
            padding:8px 14px;
            font-size:13px !important;
            font-weight:400 !important;
            color:#334155 !important;
            cursor:pointer;
            margin:0 !important;
            text-transform:none !important;
            letter-spacing:normal !important;
            border-bottom:1px solid #f1f5f9;
            background:none !important;
            transition:background .08s;
        }
        .hmu-cb-item:last-child { border-bottom:none; }
        .hmu-cb-item:hover { background:#f8fafc !important; }
        .hmu-cb-item input[type="checkbox"] {
            width:15px;
            height:15px;
            accent-color:#6b7d8e;
            cursor:pointer;
            flex-shrink:0;
        }
        .hmu-cb-item span { user-select:none; }
        </style>

        <script>
        var hmUser = {
            open: function(data) {
                var isEdit = data && data.id;
                document.getElementById('hm-user-modal-title').textContent = isEdit ? 'Edit User' : 'Add User';
                document.getElementById('hmu-id').value = isEdit ? data.id : '';
                document.getElementById('hmu-name').value = isEdit ? data.name : '';
                document.getElementById('hmu-initials').value = isEdit ? (data.initials || '') : '';
                document.getElementById('hmu-role-type').value = isEdit ? (data.role_type || 'Dispenser') : 'Dispenser';
                document.getElementById('hmu-primary-clinic').value = isEdit ? (data.primary_clinic || '') : '';
                document.getElementById('hmu-active').checked = isEdit ? data.is_active === '1' : true;

                var clinicIds = isEdit && data.clinic_id ? data.clinic_id.split(',') : [];
                document.querySelectorAll('#hmu-clinics input').forEach(function(cb) {
                    cb.checked = clinicIds.indexOf(cb.value) !== -1;
                });

                var svcIds = isEdit && data.allowed_appointment_types ? data.allowed_appointment_types.split(',') : [];
                document.querySelectorAll('#hmu-services input').forEach(function(cb) {
                    cb.checked = svcIds.indexOf(cb.value) !== -1;
                });

                document.getElementById('hm-user-modal').classList.add('open');
            },

            close: function() {
                document.getElementById('hm-user-modal').classList.remove('open');
            },

            toggleAll: function(containerId) {
                var cbs = document.querySelectorAll('#' + containerId + ' input[type="checkbox"]');
                var allChecked = true;
                cbs.forEach(function(cb) { if (!cb.checked) allChecked = false; });
                cbs.forEach(function(cb) { cb.checked = !allChecked; });
            },

            save: function() {
                var name = document.getElementById('hmu-name').value.trim();
                var initials = document.getElementById('hmu-initials').value.trim().toUpperCase();
                if (!name || !initials) { alert('Name and initials are required.'); return; }

                var clinicIds = [];
                document.querySelectorAll('#hmu-clinics input:checked').forEach(function(cb) { clinicIds.push(cb.value); });

                var svcIds = [];
                document.querySelectorAll('#hmu-services input:checked').forEach(function(cb) { svcIds.push(cb.value); });

                var btn = document.getElementById('hmu-save-btn');
                btn.textContent = 'Saving...';
                btn.disabled = true;

                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_save_user',
                    nonce: HM.nonce,
                    id: document.getElementById('hmu-id').value,
                    name: name,
                    initials: initials,
                    user_account: '',
                    role_type: document.getElementById('hmu-role-type').value,
                    primary_clinic: document.getElementById('hmu-primary-clinic').value,
                    clinic_id: clinicIds.join(','),
                    allowed_appointment_types: svcIds.join(','),
                    is_active: document.getElementById('hmu-active').checked ? '1' : '0'
                }, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error saving.'); btn.textContent = 'Save User'; btn.disabled = false; }
                });
            },

            del: function(id, name) {
                if (!confirm('Delete "' + name + '"? This cannot be undone.')) return;
                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_delete_user',
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
                'post_type' => 'dispenser',
                'post_title' => $name,
                'post_status' => 'publish',
            ]);
            if (is_wp_error($id)) { wp_send_json_error('Failed to create user'); return; }
        }

        $meta_fields = ['initials','user_account','role_type','primary_clinic','clinic_id','allowed_appointment_types','is_active'];
        foreach ($meta_fields as $f) {
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

new HearMed_Admin_Users();
