<?php
/**
 * HearMed Admin — Dispenser Schedules
 * Shortcode: [hearmed_dispenser_schedules]
 * PostgreSQL CRUD for hearmed_reference.dispenser_schedules
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_Admin_Dispenser_Schedules {

    public function __construct() {
        add_shortcode( 'hearmed_dispenser_schedules', [ $this, 'render' ] );
        add_action( 'wp_ajax_hm_admin_save_schedule', [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_hm_admin_delete_schedule', [ $this, 'ajax_delete' ] );
    }

    private function get_schedules() {
        return HearMed_DB::get_results(
            "SELECT ds.id, ds.staff_id, ds.clinic_id, ds.day_of_week, ds.rotation_weeks, ds.week_number, ds.is_active,
                    s.first_name, s.last_name, s.role, c.clinic_name
             FROM hearmed_reference.dispenser_schedules ds
             JOIN hearmed_reference.staff s ON s.id = ds.staff_id
             JOIN hearmed_reference.clinics c ON c.id = ds.clinic_id
             ORDER BY c.clinic_name, ds.day_of_week, s.last_name, s.first_name"
        ) ?: [];
    }

    private function get_staff() {
        return HearMed_DB::get_results(
            "SELECT id, first_name, last_name, role
             FROM hearmed_reference.staff
             WHERE is_active = true
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

    private function day_labels() {
        return [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ];
    }

    public function render() {
        if ( ! is_user_logged_in() ) return '<p>Please log in.</p>';

        $rows = $this->get_schedules();
        $staff = $this->get_staff();
        $clinics = $this->get_clinics();
        $days = $this->day_labels();

        $payload = [];
        foreach ( $rows as $r ) {
            $payload[] = [
                'id' => (int) $r->id,
                'staff_id' => (int) $r->staff_id,
                'clinic_id' => (int) $r->clinic_id,
                'day_of_week' => (int) $r->day_of_week,
                'rotation_weeks' => (int) $r->rotation_weeks,
                'week_number' => (int) $r->week_number,
                'is_active' => (bool) $r->is_active,
                'staff_name' => trim( $r->first_name . ' ' . $r->last_name ),
                'staff_role' => $r->role,
                'clinic_name' => $r->clinic_name,
            ];
        }

        ob_start(); ?>
        <div class="hm-admin" id="hm-schedules-admin">
            <div class="hm-admin-hd">
                <h2>Dispenser Schedules</h2>
                <button class="hm-btn hm-btn-teal" onclick="hmSchedules.open()">+ Add Schedule</button>
            </div>

            <?php if ( empty( $payload ) ): ?>
                <div class="hm-empty-state"><p>No schedules yet.</p></div>
            <?php else: ?>
            <table class="hm-table">
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Role</th>
                        <th>Clinic</th>
                        <th>Day</th>
                        <th>Rotation</th>
                        <th>Status</th>
                        <th style="width:100px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $payload as $row ):
                    $json = json_encode( $row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
                    $rotation = $row['rotation_weeks'] === 2 ? '2-week (Week ' . $row['week_number'] . ')' : 'Weekly';
                    $day_label = $days[ $row['day_of_week'] ] ?? 'Unknown';
                ?>
                    <tr>
                        <td><strong><?php echo esc_html( $row['staff_name'] ); ?></strong></td>
                        <td><?php echo esc_html( $row['staff_role'] ?: '—' ); ?></td>
                        <td><?php echo esc_html( $row['clinic_name'] ); ?></td>
                        <td><?php echo esc_html( $day_label ); ?></td>
                        <td><?php echo esc_html( $rotation ); ?></td>
                        <td><?php echo $row['is_active'] ? '<span class="hm-badge hm-badge-green">Active</span>' : '<span class="hm-badge hm-badge-red">Inactive</span>'; ?></td>
                        <td class="hm-table-acts">
                            <button class="hm-btn hm-btn-sm" onclick='hmSchedules.open(<?php echo $json; ?>)'>Edit</button>
                            <button class="hm-btn hm-btn-sm hm-btn-red" onclick="hmSchedules.del(<?php echo (int) $row['id']; ?>,'<?php echo esc_js( $row['staff_name'] ); ?>')">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <div class="hm-modal-bg" id="hm-schedule-modal">
                <div class="hm-modal" style="width:640px">
                    <div class="hm-modal-hd">
                        <h3 id="hm-schedule-title">Add Schedule</h3>
                        <button class="hm-modal-x" onclick="hmSchedules.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hms-id">
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Staff *</label>
                                <select id="hms-staff">
                                    <option value="">Select staff</option>
                                    <?php foreach ( $staff as $s ):
                                        $label = trim( $s->first_name . ' ' . $s->last_name );
                                    ?>
                                        <option value="<?php echo (int) $s->id; ?>"><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="hm-form-group">
                                <label>Clinic *</label>
                                <select id="hms-clinic">
                                    <option value="">Select clinic</option>
                                    <?php foreach ( $clinics as $c ): ?>
                                        <option value="<?php echo (int) $c->id; ?>"><?php echo esc_html( $c->clinic_name ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Day *</label>
                                <select id="hms-day">
                                    <?php foreach ( $days as $idx => $label ): ?>
                                        <option value="<?php echo (int) $idx; ?>"><?php echo esc_html( $label ); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="hm-form-group">
                                <label>Rotation</label>
                                <select id="hms-rotation">
                                    <option value="1">Weekly</option>
                                    <option value="2">2-week rotation</option>
                                </select>
                            </div>
                        </div>
                        <div class="hm-form-row" id="hms-week-row" style="display:none;">
                            <div class="hm-form-group">
                                <label>Week</label>
                                <select id="hms-week">
                                    <option value="1">Week 1</option>
                                    <option value="2">Week 2</option>
                                </select>
                            </div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label class="hm-toggle-label">
                                    <input type="checkbox" id="hms-active" checked>
                                    Active
                                </label>
                            </div>
                        </div>
                        <div class="hm-form-group" style="font-size:12px;color:#64748b;">
                            Week 1 uses odd calendar weeks (ISO week number), Week 2 uses even weeks.
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmSchedules.close()">Cancel</button>
                        <button class="hm-btn hm-btn-teal" onclick="hmSchedules.save()" id="hms-save">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        var hmSchedules = {
            open: function(data) {
                var isEdit = !!(data && data.id);
                document.getElementById('hm-schedule-title').textContent = isEdit ? 'Edit Schedule' : 'Add Schedule';
                document.getElementById('hms-id').value = isEdit ? data.id : '';
                document.getElementById('hms-staff').value = isEdit ? data.staff_id : '';
                document.getElementById('hms-clinic').value = isEdit ? data.clinic_id : '';
                document.getElementById('hms-day').value = isEdit ? data.day_of_week : '1';
                document.getElementById('hms-rotation').value = isEdit ? data.rotation_weeks : '1';
                document.getElementById('hms-week').value = isEdit ? data.week_number : '1';
                document.getElementById('hms-active').checked = isEdit ? !!data.is_active : true;
                hmSchedules.syncRotation();
                document.getElementById('hm-schedule-modal').classList.add('open');
            },
            close: function() {
                document.getElementById('hm-schedule-modal').classList.remove('open');
            },
            syncRotation: function() {
                var rotation = document.getElementById('hms-rotation').value;
                var weekRow = document.getElementById('hms-week-row');
                if (rotation === '2') {
                    weekRow.style.display = 'flex';
                } else {
                    weekRow.style.display = 'none';
                }
            },
            save: function() {
                var staffId = document.getElementById('hms-staff').value;
                var clinicId = document.getElementById('hms-clinic').value;
                if (!staffId || !clinicId) {
                    alert('Staff and clinic are required.');
                    return;
                }

                var rotation = document.getElementById('hms-rotation').value;
                var payload = {
                    action: 'hm_admin_save_schedule',
                    nonce: HM.nonce,
                    id: document.getElementById('hms-id').value,
                    staff_id: staffId,
                    clinic_id: clinicId,
                    day_of_week: document.getElementById('hms-day').value,
                    rotation_weeks: rotation,
                    week_number: rotation === '2' ? document.getElementById('hms-week').value : 1,
                    is_active: document.getElementById('hms-active').checked ? 1 : 0
                };

                var btn = document.getElementById('hms-save');
                btn.textContent = 'Saving...'; btn.disabled = true;
                jQuery.post(HM.ajax_url, payload, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error'); btn.textContent = 'Save'; btn.disabled = false; }
                });
            },
            del: function(id, name) {
                if (!confirm('Delete schedule for "' + name + '"?')) return;
                jQuery.post(HM.ajax_url, { action:'hm_admin_delete_schedule', nonce:HM.nonce, id:id }, function(r) {
                    if (r.success) location.reload();
                    else alert(r.data || 'Error');
                });
            }
        };

        document.addEventListener('change', function(e) {
            if (e.target && e.target.id === 'hms-rotation') {
                hmSchedules.syncRotation();
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }

    public function ajax_save() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }

        $id = intval( $_POST['id'] ?? 0 );
        $staff_id = intval( $_POST['staff_id'] ?? 0 );
        $clinic_id = intval( $_POST['clinic_id'] ?? 0 );
        $day = intval( $_POST['day_of_week'] ?? -1 );
        $rotation = intval( $_POST['rotation_weeks'] ?? 1 );
        $week = intval( $_POST['week_number'] ?? 1 );

        if ( ! $staff_id || ! $clinic_id || $day < 0 || $day > 6 ) {
            wp_send_json_error( 'Missing fields' );
            return;
        }

        if ( $rotation !== 2 ) {
            $rotation = 1;
            $week = 1;
        } else {
            $week = $week === 2 ? 2 : 1;
        }

        $data = [
            'staff_id' => $staff_id,
            'clinic_id' => $clinic_id,
            'day_of_week' => $day,
            'rotation_weeks' => $rotation,
            'week_number' => $week,
            'is_active' => intval( $_POST['is_active'] ?? 1 ),
            'updated_at' => current_time( 'mysql' ),
        ];

        if ( $id ) {
            $result = HearMed_DB::update( 'hearmed_reference.dispenser_schedules', $data, [ 'id' => $id ] );
        } else {
            $data['created_at'] = current_time( 'mysql' );
            $id = HearMed_DB::insert( 'hearmed_reference.dispenser_schedules', $data );
            $result = $id ? 1 : false;
        }

        if ( $result === false ) {
            wp_send_json_error( HearMed_DB::last_error() ?: 'Database error' );
        } else {
            wp_send_json_success( [ 'id' => $id ] );
        }
    }

    public function ajax_delete() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) { wp_send_json_error( 'Invalid ID' ); return; }

        $result = HearMed_DB::update(
            'hearmed_reference.dispenser_schedules',
            [ 'is_active' => false, 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $id ]
        );

        if ( $result === false ) {
            wp_send_json_error( HearMed_DB::last_error() ?: 'Database error' );
        } else {
            wp_send_json_success();
        }
    }
}

new HearMed_Admin_Dispenser_Schedules();
