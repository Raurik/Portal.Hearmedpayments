<?php
/**
 * HearMed Admin — Clinic Resources & Equipment
 * Shortcode: [hearmed_admin_resources]
 *
 * Two resource classes:
 *   Room      → Clinic + Room Name
 *   Equipment → Clinic + Room (dropdown) + Resource Type (dynamic) + Serial/Model
 *
 * @package HearMed_Portal
 * @since   5.2.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Resources {

    public function __construct() {
        add_shortcode('hearmed_admin_resources', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_resource',  [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_resource', [$this, 'ajax_delete']);
        add_action('wp_ajax_hm_admin_add_resource_type', [$this, 'ajax_add_type']);
    }

    /* ── data helpers ─────────────────────────────────────────── */

    private function get_clinics() {
        return HearMed_DB::get_results(
            "SELECT id, clinic_name FROM hearmed_reference.clinics WHERE is_active = true ORDER BY clinic_name"
        ) ?: [];
    }

    private function get_rooms() {
        return HearMed_DB::get_results(
            "SELECT r.id, r.title, r.clinic_id, c.clinic_name
             FROM hearmed_reference.resources r
             LEFT JOIN hearmed_reference.clinics c ON c.id = r.clinic_id
             WHERE r.is_active = true AND r.resource_class = 'room'
             ORDER BY c.clinic_name, r.title"
        ) ?: [];
    }

    private function get_resource_types() {
        return HearMed_DB::get_results(
            "SELECT id, type_name FROM hearmed_reference.resource_types WHERE is_active = true ORDER BY sort_order, type_name"
        ) ?: [];
    }

    private function get_resources() {
        return HearMed_DB::get_results(
            "SELECT r.*, c.clinic_name,
                    rm.title AS room_name
             FROM hearmed_reference.resources r
             LEFT JOIN hearmed_reference.clinics c ON c.id = r.clinic_id
             LEFT JOIN hearmed_reference.resources rm ON rm.id = r.room_id
             WHERE r.is_active = true
             ORDER BY r.resource_class DESC, c.clinic_name, r.title"
        ) ?: [];
    }

    /* ── render ────────────────────────────────────────────────── */

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $resources      = $this->get_resources();
        $clinics        = $this->get_clinics();
        $rooms          = $this->get_rooms();
        $resource_types = $this->get_resource_types();

        $room_list = [];
        $equip_list = [];
        foreach ($resources as $r) {
            $rc = $r->resource_class ?? 'equipment';
            if ($rc === 'room') {
                $room_list[] = $r;
            } else {
                $equip_list[] = $r;
            }
        }

        // Group rooms by clinic
        $rooms_by_clinic = [];
        foreach ($room_list as $r) {
            $cn = $r->clinic_name ?: 'Unassigned';
            $rooms_by_clinic[$cn][] = $r;
        }

        // Group equipment by clinic
        $equip_by_clinic = [];
        foreach ($equip_list as $r) {
            $cn = $r->clinic_name ?: 'Unassigned';
            $equip_by_clinic[$cn][] = $r;
        }

        ob_start(); ?>
        <div class="hm-admin" id="hm-resources-admin">
            <a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-back">← Back</a>
            <div class="hm-admin-hd">
                <h2>Resources &amp; Equipment</h2>
                <div style="display:flex;gap:8px;">
                    <button class="hm-btn hm-btn--primary" onclick="hmRes.openRoom()">+ Add Room</button>
                    <button class="hm-btn hm-btn--primary" onclick="hmRes.openEquip()">+ Add Equipment</button>
                </div>
            </div>

            <p style="color:var(--hm-text-light);font-size:13px;margin-bottom:20px;">
                Manage clinic rooms and equipment. Rooms must be created first so equipment can be assigned to them.
            </p>

            <!-- ── ROOMS ──────────────────────────────────── -->
            <h3 style="font-size:14px;font-weight:600;margin:0 0 10px;color:#334155;">Room Resources</h3>
            <?php if (empty($room_list)): ?>
                <div class="hm-empty-state" style="margin-bottom:24px;"><p>No rooms yet. Click "+ Add Room" to get started.</p></div>
            <?php else: ?>
                <?php foreach ($rooms_by_clinic as $clinic_name => $items): ?>
                <div class="hm-settings-panel" style="margin-bottom:16px;">
                    <h4 style="font-size:13px;margin-bottom:10px;color:#475569;"><?php echo esc_html($clinic_name); ?> <span style="color:var(--hm-text-light);font-weight:400;">(<?php echo count($items); ?>)</span></h4>
                    <table class="hm-table">
                        <thead><tr><th>Room Name</th><th style="width:100px"></th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $r):
                            $row = json_encode([
                                'id' => (int)$r->id,
                                'title' => $r->title,
                                'clinic_id' => $r->clinic_id,
                                'resource_class' => 'room',
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html($r->title); ?></strong></td>
                                <td class="hm-table-acts">
                                    <button class="hm-btn hm-btn--sm" onclick='hmRes.openRoom(<?php echo $row; ?>)'>Edit</button>
                                    <button class="hm-btn hm-btn--sm hm-btn--danger" onclick="hmRes.del(<?php echo (int) $r->id; ?>,'<?php echo esc_js($r->title); ?>')">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- ── EQUIPMENT ──────────────────────────────── -->
            <h3 style="font-size:14px;font-weight:600;margin:20px 0 10px;color:#334155;">Equipment Resources</h3>
            <?php if (empty($equip_list)): ?>
                <div class="hm-empty-state"><p>No equipment yet. Click "+ Add Equipment" to get started.</p></div>
            <?php else: ?>
                <?php foreach ($equip_by_clinic as $clinic_name => $items): ?>
                <div class="hm-settings-panel" style="margin-bottom:16px;">
                    <h4 style="font-size:13px;margin-bottom:10px;color:#475569;"><?php echo esc_html($clinic_name); ?> <span style="color:var(--hm-text-light);font-weight:400;">(<?php echo count($items); ?>)</span></h4>
                    <table class="hm-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Type</th>
                                <th>Room</th>
                                <th>Serial / Model</th>
                                <th style="width:100px"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($items as $r):
                            $row = json_encode([
                                'id' => (int)$r->id,
                                'title' => $r->title,
                                'clinic_id' => $r->clinic_id,
                                'room_id' => $r->room_id,
                                'category' => $r->category,
                                'description' => $r->description,
                                'resource_class' => 'equipment',
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                        ?>
                            <tr>
                                <td><strong><?php echo esc_html($r->title); ?></strong></td>
                                <td><span class="hm-badge hm-badge--blue"><?php echo esc_html($r->category ?: '—'); ?></span></td>
                                <td><?php echo esc_html($r->room_name ?: '—'); ?></td>
                                <td><?php echo esc_html($r->description ?: '—'); ?></td>
                                <td class="hm-table-acts">
                                    <button class="hm-btn hm-btn--sm" onclick='hmRes.openEquip(<?php echo $row; ?>)'>Edit</button>
                                    <button class="hm-btn hm-btn--sm hm-btn--danger" onclick="hmRes.del(<?php echo (int) $r->id; ?>,'<?php echo esc_js($r->title); ?>')">Delete</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- ── ROOM MODAL ─────────────────────────────── -->
            <div class="hm-modal-bg" id="hm-room-modal">
                <div class="hm-modal hm-modal--md">
                    <div class="hm-modal-hd">
                        <h3 id="hm-room-title">Add Room</h3>
                        <button class="hm-close" onclick="hmRes.closeRoom()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hmr-room-id">
                        <div class="hm-form-group">
                            <label>Clinic *</label>
                                <select id="hmr-room-clinic" data-entity="clinic" data-label="Clinic">
                                    <option value="">Select clinic</option>
                                    <?php foreach ($clinics as $c): ?>
                                        <option value="<?php echo (int) $c->id; ?>"><?php echo esc_html($c->clinic_name); ?></option>
                                    <?php endforeach; ?>
                                    <option value="__add_new__">+ Add New…</option>
                                </select>
                        </div>
                        <div class="hm-form-group">
                            <label>Room Name *</label>
                            <input type="text" id="hmr-room-name" placeholder="e.g. Room 1, Testing Booth">
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmRes.closeRoom()">Cancel</button>
                        <button class="hm-btn hm-btn--primary" onclick="hmRes.saveRoom()" id="hmr-room-save">Save</button>
                    </div>
                </div>
            </div>

            <!-- ── EQUIPMENT MODAL ────────────────────────── -->
            <div class="hm-modal-bg" id="hm-equip-modal">
                <div class="hm-modal hm-modal--lg">
                    <div class="hm-modal-hd">
                        <h3 id="hm-equip-title">Add Equipment</h3>
                        <button class="hm-close" onclick="hmRes.closeEquip()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hmr-equip-id">
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Clinic *</label>
                                    <select id="hmr-equip-clinic" data-entity="clinic" data-label="Clinic" onchange="hmRes.filterRooms()">
                                        <option value="">Select clinic</option>
                                        <?php foreach ($clinics as $c): ?>
                                            <option value="<?php echo (int) $c->id; ?>"><?php echo esc_html($c->clinic_name); ?></option>
                                        <?php endforeach; ?>
                                        <option value="__add_new__">+ Add New…</option>
                                    </select>
                            </div>
                            <div class="hm-form-group">
                                <label>Room</label>
                                <select id="hmr-equip-room">
                                    <option value="">Select room</option>
                                </select>
                            </div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group" style="flex:2">
                                <label>Equipment Name *</label>
                                <input type="text" id="hmr-equip-name" placeholder="e.g. MA41 Audiometer">
                            </div>
                            <div class="hm-form-group" style="flex:1">
                                <label>Resource Type *</label>
                                    <select id="hmr-equip-type" data-entity="resource_type" data-label="Resource Type">
                                        <option value="">Select type</option>
                                        <?php foreach ($resource_types as $t): ?>
                                            <option value="<?php echo esc_attr($t->type_name); ?>"><?php echo esc_html($t->type_name); ?></option>
                                        <?php endforeach; ?>
                                        <option value="__add_new__">+ Add New…</option>
                                    </select>
                            </div>
                        </div>
                        <div class="hm-form-group">
                            <label>Serial Number / Model</label>
                            <input type="text" id="hmr-equip-desc" placeholder="e.g. SN-12345 or MA41">
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmRes.closeEquip()">Cancel</button>
                        <button class="hm-btn hm-btn--primary" onclick="hmRes.saveEquip()" id="hmr-equip-save">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        var hmRes = {
            rooms: <?php echo json_encode(array_map(function($r) {
                return ['id' => (int)$r->id, 'title' => $r->title, 'clinic_id' => (int)$r->clinic_id];
            }, $rooms), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,

            /* ── Room ─────────────────── */
            openRoom: function(data) {
                var edit = !!(data && data.id);
                document.getElementById('hm-room-title').textContent = edit ? 'Edit Room' : 'Add Room';
                document.getElementById('hmr-room-id').value    = edit ? data.id : '';
                document.getElementById('hmr-room-clinic').value = edit ? (data.clinic_id || '') : '';
                document.getElementById('hmr-room-name').value  = edit ? (data.title || '') : '';
                document.getElementById('hm-room-modal').classList.add('open');
                document.getElementById('hmr-room-name').focus();
            },
            closeRoom: function() { document.getElementById('hm-room-modal').classList.remove('open'); },
            saveRoom: function() {
                var clinic = document.getElementById('hmr-room-clinic').value;
                var name   = document.getElementById('hmr-room-name').value.trim();
                if (!clinic) { alert('Please select a clinic.'); return; }
                if (!name)   { alert('Room name is required.'); return; }

                var btn = document.getElementById('hmr-room-save');
                btn.textContent = 'Saving...'; btn.disabled = true;

                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_save_resource',
                    nonce: HM.nonce,
                    id: document.getElementById('hmr-room-id').value,
                    title: name,
                    clinic_id: clinic,
                    resource_class: 'room',
                    category: 'Room',
                    description: '',
                    room_id: ''
                }, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error'); btn.textContent = 'Save'; btn.disabled = false; }
                });
            },

            /* ── Equipment ─────────────── */
            openEquip: function(data) {
                var edit = !!(data && data.id);
                document.getElementById('hm-equip-title').textContent = edit ? 'Edit Equipment' : 'Add Equipment';
                document.getElementById('hmr-equip-id').value     = edit ? data.id : '';
                document.getElementById('hmr-equip-clinic').value = edit ? (data.clinic_id || '') : '';
                this.filterRooms();
                if (edit && data.room_id) {
                    setTimeout(function() {
                        document.getElementById('hmr-equip-room').value = data.room_id;
                    }, 50);
                }
                document.getElementById('hmr-equip-name').value = edit ? (data.title || '') : '';
                document.getElementById('hmr-equip-type').value = edit ? (data.category || '') : '';
                document.getElementById('hmr-equip-desc').value = edit ? (data.description || '') : '';
                document.getElementById('hm-equip-modal').classList.add('open');
                document.getElementById('hmr-equip-name').focus();
            },
            closeEquip: function() { document.getElementById('hm-equip-modal').classList.remove('open'); },
            filterRooms: function() {
                var clinicId = parseInt(document.getElementById('hmr-equip-clinic').value, 10) || 0;
                var sel = document.getElementById('hmr-equip-room');
                sel.innerHTML = '<option value="">Select room</option>';
                this.rooms.forEach(function(rm) {
                    if (rm.clinic_id === clinicId) {
                        var opt = document.createElement('option');
                        opt.value = rm.id;
                        opt.textContent = rm.title;
                        sel.appendChild(opt);
                    }
                });
            },
            saveEquip: function() {
                var clinic = document.getElementById('hmr-equip-clinic').value;
                var name   = document.getElementById('hmr-equip-name').value.trim();
                var type   = document.getElementById('hmr-equip-type').value;
                if (!clinic) { alert('Please select a clinic.'); return; }
                if (!name)   { alert('Equipment name is required.'); return; }
                if (!type)   { alert('Resource type is required.'); return; }

                var btn = document.getElementById('hmr-equip-save');
                btn.textContent = 'Saving...'; btn.disabled = true;

                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_save_resource',
                    nonce: HM.nonce,
                    id: document.getElementById('hmr-equip-id').value,
                    title: name,
                    clinic_id: clinic,
                    resource_class: 'equipment',
                    category: type,
                    description: document.getElementById('hmr-equip-desc').value,
                    room_id: document.getElementById('hmr-equip-room').value
                }, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error'); btn.textContent = 'Save'; btn.disabled = false; }
                });
            },

            /* ── Add new resource type ── */
            addType: function() {
                var name = prompt('Enter new resource type name:');
                if (!name || !name.trim()) return;
                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_add_resource_type',
                    nonce: HM.nonce,
                    type_name: name.trim()
                }, function(r) {
                    if (r.success) {
                        var sel = document.getElementById('hmr-equip-type');
                        var opt = document.createElement('option');
                        opt.value = r.data.type_name;
                        opt.textContent = r.data.type_name;
                        sel.appendChild(opt);
                        sel.value = r.data.type_name;
                    } else {
                        alert(r.data || 'Error adding type');
                    }
                });
            },

            /* ── Delete ─────────────────── */
            del: function(id, name) {
                if (!confirm('Delete "' + name + '"?')) return;
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

    /* ── AJAX: save ─────────────────────────────────────────── */

    public function ajax_save() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }

        $id     = intval($_POST['id'] ?? 0);
        $title  = sanitize_text_field($_POST['title'] ?? '');
        $clinic = intval($_POST['clinic_id'] ?? 0);
        $rc     = sanitize_text_field($_POST['resource_class'] ?? 'equipment');
        if (!$title || !$clinic) {
            wp_send_json_error('Name and clinic are required');
            return;
        }

        $data = [
            'title'          => $title,
            'clinic_id'      => $clinic,
            'resource_class' => $rc,
            'category'       => sanitize_text_field($_POST['category'] ?? ''),
            'description'    => sanitize_text_field($_POST['description'] ?? ''),
            'room_id'        => !empty($_POST['room_id']) ? intval($_POST['room_id']) : null,
            'is_active'      => true,
            'sort_order'     => 0,
            'updated_at'     => current_time('mysql'),
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

    /* ── AJAX: delete (soft) ────────────────────────────────── */

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

    /* ── AJAX: add resource type ────────────────────────────── */

    public function ajax_add_type() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }

        $name = sanitize_text_field($_POST['type_name'] ?? '');
        if (!$name) { wp_send_json_error('Type name required'); return; }

        $existing = HearMed_DB::get_row(
            "SELECT id FROM hearmed_reference.resource_types WHERE type_name = $1",
            [$name]
        );
        if ($existing) {
            wp_send_json_success(['id' => (int)$existing->id, 'type_name' => $name]);
            return;
        }

        $id = HearMed_DB::insert('hearmed_reference.resource_types', [
            'type_name'  => $name,
            'is_active'  => true,
            'sort_order' => 0,
            'created_at' => current_time('mysql'),
        ]);

        if ($id) {
            wp_send_json_success(['id' => $id, 'type_name' => $name]);
        } else {
            wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
        }
    }
}

new HearMed_Admin_Resources();
