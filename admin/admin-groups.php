<?php
/**
 * HearMed Admin — Groups
 * Shortcode: [hearmed_admin_groups]
 * PostgreSQL CRUD for staff_groups and staff_group_members
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
            "SELECT g.id, g.group_name, g.description, g.is_active,
                    COUNT(m.staff_id) AS member_count
             FROM hearmed_reference.staff_groups g
             LEFT JOIN hearmed_reference.staff_group_members m ON g.id = m.group_id
             GROUP BY g.id
             ORDER BY g.group_name"
        ) ?: [];
    }

    private function get_memberships() {
        return HearMed_DB::get_results(
            "SELECT group_id, staff_id
             FROM hearmed_reference.staff_group_members"
        ) ?: [];
    }

    private function get_staff() {
        return HearMed_DB::get_results(
            "SELECT id, first_name, last_name
             FROM hearmed_reference.staff
             WHERE is_active = true
             ORDER BY last_name, first_name"
        ) ?: [];
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $groups = $this->get_groups();
        $memberships = $this->get_memberships();
        $staff = $this->get_staff();

        $group_members = [];
        foreach ($memberships as $m) {
            $gid = (int) $m->group_id;
            if (!isset($group_members[$gid])) $group_members[$gid] = [];
            $group_members[$gid][] = (int) $m->staff_id;
        }

        $staff_map = [];
        foreach ($staff as $s) {
            $staff_map[$s->id] = trim($s->first_name . ' ' . $s->last_name);
        }

        $payload = [];
        foreach ($groups as $g) {
            $gid = (int) $g->id;
            $payload[] = [
                'id' => $gid,
                'group_name' => $g->group_name,
                'description' => $g->description,
                'is_active' => (bool) $g->is_active,
                'member_ids' => $group_members[$gid] ?? [],
                'member_count' => (int) $g->member_count,
            ];
        }

        ob_start(); ?>
        <div class="hm-admin" id="hm-groups-admin">
            <div class="hm-admin-hd">
                <h2>Groups</h2>
                <button class="hm-btn hm-btn-teal" onclick="hmGroups.open()">+ Add Group</button>
            </div>

            <?php if (empty($payload)): ?>
                <div class="hm-empty-state"><p>No groups yet.</p></div>
            <?php else: ?>
            <table class="hm-table">
                <thead>
                    <tr>
                        <th>Group</th>
                        <th>Description</th>
                        <th class="hm-num">Members</th>
                        <th>Status</th>
                        <th style="width:100px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($payload as $g):
                    $row = json_encode($g, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($g['group_name']); ?></strong></td>
                        <td><?php echo esc_html($g['description'] ?: '—'); ?></td>
                        <td class="hm-num"><?php echo esc_html((string) $g['member_count']); ?></td>
                        <td><?php echo $g['is_active'] ? '<span class="hm-badge hm-badge-green">Active</span>' : '<span class="hm-badge hm-badge-red">Inactive</span>'; ?></td>
                        <td class="hm-table-acts">
                            <button class="hm-btn hm-btn-sm" onclick='hmGroups.open(<?php echo $row; ?>)'>Edit</button>
                            <button class="hm-btn hm-btn-sm hm-btn-red" onclick="hmGroups.del(<?php echo (int) $g['id']; ?>,'<?php echo esc_js($g['group_name']); ?>')">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <div class="hm-modal-bg" id="hm-group-modal">
                <div class="hm-modal" style="width:640px">
                    <div class="hm-modal-hd">
                        <h3 id="hm-group-title">Add Group</h3>
                        <button class="hm-modal-x" onclick="hmGroups.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hmg-id">
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Group Name *</label>
                                <input type="text" id="hmg-name">
                            </div>
                            <div class="hm-form-group">
                                <label class="hm-toggle-label">
                                    <input type="checkbox" id="hmg-active" checked>
                                    Active
                                </label>
                            </div>
                        </div>
                        <div class="hm-form-group">
                            <label>Description</label>
                            <textarea id="hmg-desc" rows="3"></textarea>
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
                        <button class="hm-btn hm-btn-teal" onclick="hmGroups.save()" id="hmg-save">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        var hmGroups = {
            open: function(data) {
                var isEdit = !!(data && data.id);
                document.getElementById('hm-group-title').textContent = isEdit ? 'Edit Group' : 'Add Group';
                document.getElementById('hmg-id').value = isEdit ? data.id : '';
                document.getElementById('hmg-name').value = isEdit ? data.group_name : '';
                document.getElementById('hmg-desc').value = isEdit ? (data.description || '') : '';
                document.getElementById('hmg-active').checked = isEdit ? !!data.is_active : true;

                document.querySelectorAll('.hm-group-member').forEach(function(cb) {
                    cb.checked = isEdit && data.member_ids ? data.member_ids.indexOf(parseInt(cb.value, 10)) !== -1 : false;
                });

                document.getElementById('hm-group-modal').classList.add('open');
            },
            close: function() {
                document.getElementById('hm-group-modal').classList.remove('open');
            },
            save: function() {
                var name = document.getElementById('hmg-name').value.trim();
                if (!name) { alert('Group name is required.'); return; }

                var members = [];
                document.querySelectorAll('.hm-group-member:checked').forEach(function(cb) {
                    members.push(parseInt(cb.value, 10));
                });

                var payload = {
                    action: 'hm_admin_save_group',
                    nonce: HM.nonce,
                    id: document.getElementById('hmg-id').value,
                    group_name: name,
                    description: document.getElementById('hmg-desc').value,
                    is_active: document.getElementById('hmg-active').checked ? 1 : 0,
                    members: JSON.stringify(members)
                };

                var btn = document.getElementById('hmg-save');
                btn.textContent = 'Saving...'; btn.disabled = true;
                jQuery.post(HM.ajax_url, payload, function(r) {
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
        if (!$name) { wp_send_json_error('Missing fields'); return; }

        $data = [
            'group_name' => $name,
            'description' => sanitize_text_field($_POST['description'] ?? ''),
            'is_active' => intval($_POST['is_active'] ?? 1),
            'updated_at' => current_time('mysql'),
        ];

        if ($id) {
            $result = HearMed_DB::update('hearmed_reference.staff_groups', $data, ['id' => $id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $id = HearMed_DB::insert('hearmed_reference.staff_groups', $data);
            $result = $id ? 1 : false;
        }

        if ($result === false) { wp_send_json_error('Database error'); return; }

        $member_ids = json_decode(stripslashes($_POST['members'] ?? '[]'), true);
        if (!is_array($member_ids)) $member_ids = [];

        HearMed_DB::get_results(
            "DELETE FROM hearmed_reference.staff_group_members WHERE group_id = $1",
            [$id]
        );

        foreach ($member_ids as $sid) {
            $sid = intval($sid);
            if (!$sid) continue;
            HearMed_DB::insert(
                'hearmed_reference.staff_group_members',
                [
                    'group_id' => $id,
                    'staff_id' => $sid,
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
            'hearmed_reference.staff_groups',
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

new HearMed_Admin_Groups();
