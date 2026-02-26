<?php
/**
 * HearMed Admin — Staff Groups (by Clinic & Role)
 * Shortcode: [hearmed_admin_groups]
 * Organises staff by clinic and role assignment.
 *
 * @package HearMed_Portal
 * @since   5.1.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Groups {

    public function __construct() {
        add_shortcode('hearmed_admin_groups', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_group', [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_group', [$this, 'ajax_delete']);
    }

    private function get_groups() {
        return HearMed_DB::get_results(
            "SELECT g.id, g.group_name, g.description, g.is_active, g.clinic_id, g.role_id,
                    c.clinic_name, r.role_name,
                    COUNT(m.staff_id) AS member_count
             FROM hearmed_reference.staff_groups g
             LEFT JOIN hearmed_reference.staff_group_members m ON g.id = m.group_id
             LEFT JOIN hearmed_reference.clinics c ON g.clinic_id = c.id
             LEFT JOIN hearmed_reference.roles r ON g.role_id = r.id
             WHERE g.is_active = true
             GROUP BY g.id, c.clinic_name, r.role_name
             ORDER BY c.clinic_name, r.role_name, g.group_name"
        ) ?: [];
    }

    private function get_memberships() {
        return HearMed_DB::get_results(
            "SELECT group_id, staff_id FROM hearmed_reference.staff_group_members"
        ) ?: [];
    }

    private function get_staff() {
        return HearMed_DB::get_results(
            "SELECT id, first_name, last_name FROM hearmed_reference.staff WHERE is_active = true ORDER BY last_name, first_name"
        ) ?: [];
    }

    private function get_clinics() {
        return HearMed_DB::get_results(
            "SELECT id, clinic_name FROM hearmed_reference.clinics WHERE is_active = true ORDER BY clinic_name"
        ) ?: [];
    }

    private function get_roles() {
        return HearMed_DB::get_results(
            "SELECT id, role_name FROM hearmed_reference.roles WHERE is_active = true ORDER BY role_name"
        ) ?: [];
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $groups = $this->get_groups();
        $memberships = $this->get_memberships();
        $staff = $this->get_staff();
        $clinics = $this->get_clinics();
        $roles = $this->get_roles();

        $group_members = [];
        foreach ($memberships as $m) {
            $gid = (int) $m->group_id;
            if (!isset($group_members[$gid])) $group_members[$gid] = [];
            $group_members[$gid][] = (int) $m->staff_id;
        }

        $payload = [];
        foreach ($groups as $g) {
            $gid = (int) $g->id;
            $payload[] = [
                'id' => $gid,
                'group_name'   => $g->group_name,
                'description'  => $g->description,
                'is_active'    => (bool) $g->is_active,
                'clinic_id'    => $g->clinic_id,
                'clinic_name'  => $g->clinic_name,
                'role_id'      => $g->role_id,
                'role_name'    => $g->role_name,
                'member_ids'   => $group_members[$gid] ?? [],
                'member_count' => (int) $g->member_count,
            ];
        }

        // Group by clinic for display
        $by_clinic = [];
        foreach ($payload as $g) {
            $cname = $g['clinic_name'] ?: $g['group_name'];
            if (!isset($by_clinic[$cname])) $by_clinic[$cname] = [];
            $by_clinic[$cname][] = $g;
        }

        $staff_map = [];
        foreach ($staff as $s) {
            $staff_map[$s->id] = trim($s->first_name . ' ' . $s->last_name);
        }

        ob_start(); ?>
        <div class="hm-admin" id="hm-groups-admin">
            <a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-back">← Back</a>
            <div class="hm-admin-hd">
                <h2>Staff Groups</h2>
                <button class="hm-btn hm-btn--primary" onclick="hmGroups.open()">+ Add Group</button>
            </div>

            <p style="color:var(--hm-text-light);font-size:13px;margin-bottom:20px;">
                Organise staff by clinic and role. Groups define who works where and in what capacity.
            </p>

            <?php if (empty($payload)): ?>
                <div class="hm-empty-state"><p>No groups yet. Click "+ Add Group" to get started.</p></div>
            <?php else: ?>
                <?php foreach ($by_clinic as $clinic_name => $clinic_groups): ?>
                <div class="hm-settings-panel" style="margin-bottom:16px;">
                    <h3 style="font-size:15px;margin-bottom:12px;"><?php echo esc_html($clinic_name); ?></h3>
                    <table class="hm-table">
                        <thead>
                            <tr>
                                <th>Group</th>
                                <th>Role</th>
                                <th>Description</th>
                                <th class="hm-num">Members</th>
                                <th>Staff</th>
                                <th>Status</th>
                                <th style="width:100px"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($clinic_groups as $g):
                            $row = json_encode($g, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                            $member_names = [];
                            foreach ($g['member_ids'] as $sid) {
                                if (isset($staff_map[$sid])) $member_names[] = $staff_map[$sid];
                            }
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html($g['group_name']); ?></strong></td>
                                <td><?php echo $g['role_name'] ? '<span class="hm-badge hm-badge--blue">' . esc_html($g['role_name']) . '</span>' : '—'; ?></td>
                                <td><?php echo esc_html($g['description'] ?: '—'); ?></td>
                                <td class="hm-num"><?php echo (int) $g['member_count']; ?></td>
                                <td style="font-size:12px;color:var(--hm-text-light);"><?php echo esc_html(implode(', ', $member_names) ?: '—'); ?></td>
                                <td><?php echo $g['is_active'] ? '<span class="hm-badge hm-badge--green">Active</span>' : '<span class="hm-badge hm-badge--red">Inactive</span>'; ?></td>
                                <td class="hm-table-acts">
                                    <button class="hm-btn hm-btn--sm" onclick='hmGroups.open(<?php echo $row; ?>)'>Edit</button>
                                    <button class="hm-btn hm-btn--sm hm-btn--danger" onclick="hmGroups.del(<?php echo (int) $g['id']; ?>,'<?php echo esc_js($g['group_name']); ?>')">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Modal -->
            <div class="hm-modal-bg" id="hm-group-modal">
                <div class="hm-modal hm-modal--lg">
                    <div class="hm-modal-hd">
                        <h3 id="hm-group-title">Add Group</h3>
                        <button class="hm-close" onclick="hmGroups.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hmg-id">
                        <div class="hm-form-row">
                            <div class="hm-form-group" style="flex:2">
                                <label>Group Name *</label>
                                <input type="text" id="hmg-name" placeholder="e.g. Dispensers - Dublin">
                            </div>
                            <div class="hm-form-group" style="flex:1">
                                <label class="hm-toggle-label">
                                    <input type="checkbox" id="hmg-active" checked>
                                    Active
                                </label>
                            </div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Clinic</label>
                                <select id="hmg-clinic" data-entity="clinic" data-label="Clinic">
                                    <option value="">All Clinics</option>
                                    <?php foreach ($clinics as $c): ?>
                                        <option value="<?php echo (int) $c->id; ?>"><?php echo esc_html($c->clinic_name); ?></option>
                                    <?php endforeach; ?>
                                    <option value="__add_new__">+ Add New…</option>
                                </select>
                            </div>
                            <div class="hm-form-group">
                                <label>Role</label>
                                <select id="hmg-role" data-entity="role" data-label="Role">
                                    <option value="">No Role</option>
                                    <?php foreach ($roles as $r): ?>
                                        <option value="<?php echo (int) $r->id; ?>"><?php echo esc_html($r->role_name); ?></option>
                                    <?php endforeach; ?>
                                    <option value="__add_new__">+ Add New…</option>
                                </select>
                            </div>
                        </div>
                        <div class="hm-form-group">
                            <label>Description</label>
                            <textarea id="hmg-desc" rows="2"></textarea>
                        </div>
                        <div class="hm-form-group">
                            <label>Members</label>
                            <div id="hmg-members" style="display:flex;flex-wrap:wrap;gap:10px;">
                                <?php foreach ($staff as $s):
                                    $label = trim($s->first_name . ' ' . $s->last_name);
                                ?>
                                    <label class="hm-day-check">
                                        <input type="checkbox" class="hm-group-member" value="<?php echo (int) $s->id; ?>">
                                        <?php echo esc_html($label); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmGroups.close()">Cancel</button>
                        <button class="hm-btn hm-btn--primary" onclick="hmGroups.save()" id="hmg-save">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        var hmGroups = {
            open: function(data) {
                var isEdit = !!(data && data.id);
                document.getElementById('hm-group-title').textContent = isEdit ? 'Edit Group' : 'Add Group';
                document.getElementById('hmg-id').value     = isEdit ? data.id : '';
                document.getElementById('hmg-name').value   = isEdit ? data.group_name : '';
                document.getElementById('hmg-desc').value   = isEdit ? (data.description || '') : '';
                document.getElementById('hmg-active').checked = isEdit ? !!data.is_active : true;
                document.getElementById('hmg-clinic').value = isEdit ? (data.clinic_id || '') : '';
                document.getElementById('hmg-role').value   = isEdit ? (data.role_id || '') : '';

                document.querySelectorAll('.hm-group-member').forEach(function(cb) {
                    cb.checked = isEdit && data.member_ids ? data.member_ids.indexOf(parseInt(cb.value, 10)) !== -1 : false;
                });

                document.getElementById('hm-group-modal').classList.add('open');
            },
            close: function() { document.getElementById('hm-group-modal').classList.remove('open'); },
            save: function() {
                var name = document.getElementById('hmg-name').value.trim();
                if (!name) { alert('Group name is required.'); return; }

                var members = [];
                document.querySelectorAll('.hm-group-member:checked').forEach(function(cb) {
                    members.push(parseInt(cb.value, 10));
                });

                var btn = document.getElementById('hmg-save');
                btn.textContent = 'Saving...'; btn.disabled = true;

                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_save_group',
                    nonce: HM.nonce,
                    id: document.getElementById('hmg-id').value,
                    group_name: name,
                    description: document.getElementById('hmg-desc').value,
                    is_active: document.getElementById('hmg-active').checked ? 1 : 0,
                    clinic_id: document.getElementById('hmg-clinic').value,
                    role_id: document.getElementById('hmg-role').value,
                    members: JSON.stringify(members)
                }, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error'); btn.textContent = 'Save'; btn.disabled = false; }
                });
            },
            del: function(id, name) {
                if (!confirm('Delete "' + name + '"?')) return;
                jQuery.post(HM.ajax_url, { action:'hm_admin_delete_group', nonce:HM.nonce, id:id }, function(r) {
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
        $name = sanitize_text_field($_POST['group_name'] ?? '');
        if (!$name) { wp_send_json_error('Group name required'); return; }

        $data = [
            'group_name'  => $name,
            'description' => sanitize_text_field($_POST['description'] ?? ''),
            'is_active'   => intval($_POST['is_active'] ?? 1),
            'clinic_id'   => !empty($_POST['clinic_id']) ? intval($_POST['clinic_id']) : null,
            'role_id'     => !empty($_POST['role_id']) ? intval($_POST['role_id']) : null,
            'updated_at'  => current_time('mysql'),
        ];

        if ($id) {
            $result = HearMed_DB::update('hearmed_reference.staff_groups', $data, ['id' => $id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $id = HearMed_DB::insert('hearmed_reference.staff_groups', $data);
            $result = $id ? 1 : false;
        }

        if ($result === false) { wp_send_json_error(HearMed_DB::last_error() ?: 'Database error'); return; }

        $member_ids = json_decode(stripslashes($_POST['members'] ?? '[]'), true);
        if (!is_array($member_ids)) $member_ids = [];

        HearMed_DB::get_results("DELETE FROM hearmed_reference.staff_group_members WHERE group_id = $1", [$id]);

        foreach ($member_ids as $sid) {
            $sid = intval($sid);
            if (!$sid) continue;
            HearMed_DB::insert('hearmed_reference.staff_group_members', [
                'group_id'   => $id,
                'staff_id'   => $sid,
                'created_at' => current_time('mysql'),
            ]);
        }

        wp_send_json_success(['id' => $id]);
    }

    public function ajax_delete() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }

        $id = intval($_POST['id'] ?? 0);
        if (!$id) { wp_send_json_error('Invalid ID'); return; }

        $result = HearMed_DB::update(
            'hearmed_reference.staff_groups',
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

new HearMed_Admin_Groups();
