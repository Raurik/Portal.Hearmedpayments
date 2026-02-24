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
             ORDER BY c.clinic_name, s.last_name, s.first_name, ds.day_of_week"
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

        // Group schedules by staff_id
        $staff_schedules = [];
        $all_schedules = [];
        foreach ( $rows as $r ) {
            $sid = (int) $r->staff_id;
            if ( ! isset( $staff_schedules[ $sid ] ) ) {
                $staff_schedules[ $sid ] = [
                    'staff_id' => $sid,
                    'staff_name' => trim( $r->first_name . ' ' . $r->last_name ),
                    'staff_role' => $r->role,
                    'schedules' => [],
                ];
            }
            $schedule_entry = [
                'id' => (int) $r->id,
                'clinic_id' => (int) $r->clinic_id,
                'clinic_name' => $r->clinic_name,
                'day_of_week' => (int) $r->day_of_week,
                'day_label' => $days[ (int) $r->day_of_week ] ?? 'Unknown',
                'rotation_weeks' => (int) $r->rotation_weeks,
                'week_number' => (int) $r->week_number,
                'is_active' => (bool) $r->is_active,
            ];
            $staff_schedules[ $sid ]['schedules'][] = $schedule_entry;
            $all_schedules[] = $schedule_entry;
        }

        // Build payload for unique staff members only
        $payload = [];
        foreach ( $staff_schedules as $staff_data ) {
            $payload[] = $staff_data;
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
                        <th>Clinics Assigned</th>
                        <th>Status</th>
                        <th style="width:120px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $payload as $staff_data ):
                    $staff_json = json_encode( $staff_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
                    
                    // Count unique clinics
                    $clinic_ids = [];
                    $clinic_names = [];
                    foreach ( $staff_data['schedules'] as $schedule ) {
                        if ( ! in_array( $schedule['clinic_id'], $clinic_ids ) ) {
                            $clinic_ids[] = $schedule['clinic_id'];
                            $clinic_names[] = $schedule['clinic_name'];
                        }
                    }
                    
                    // Check if all schedules are active
                    $all_active = true;
                    foreach ( $staff_data['schedules'] as $schedule ) {
                        if ( ! $schedule['is_active'] ) {
                            $all_active = false;
                            break;
                        }
                    }
                ?>
                    <tr>
                        <td><strong><?php echo esc_html( $staff_data['staff_name'] ); ?></strong></td>
                        <td><?php echo esc_html( $staff_data['staff_role'] ?: '—' ); ?></td>
                        <td><?php echo esc_html( implode( ', ', $clinic_names ) ?: '—' ); ?></td>
                        <td><?php echo $all_active ? '<span class="hm-badge hm-badge-green">Active</span>' : '<span class="hm-badge hm-badge-red">Inactive</span>'; ?></td>
                        <td class="hm-table-acts">
                            <button class="hm-btn hm-btn-sm" onclick='hmSchedules.viewDetails(<?php echo $staff_json; ?>)'>View Details</button>
                            <button class="hm-btn hm-btn-sm hm-btn-teal" onclick="hmSchedules.openAdd()">+ Add</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- Detail View Modal -->
            <div class="hm-modal-bg" id="hm-schedule-detail-modal">
                <div class="hm-modal" style="width:700px">
                    <div class="hm-modal-hd">
                        <h3 id="hm-detail-title">Schedule Details</h3>
                        <button class="hm-modal-x" onclick="hmSchedules.closeDetail()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <div id="hm-detail-content" style="max-height:500px;overflow-y:auto;">
                            <!-- Content filled by JavaScript -->
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn hm-btn-teal" onclick="hmSchedules.openAdd()">+ Add Schedule</button>
                        <button class="hm-btn" onclick="hmSchedules.closeDetail()">Close</button>
                    </div>
                </div>
            </div>

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
                            <div class="hm-form-group" style="flex:1;">
                                <label>Rotation *</label>
                                <select id="hms-rotation">
                                    <option value="1">Weekly</option>
                                    <option value="2">Every 2 weeks</option>
                                    <option value="3">Every 3 weeks</option>
                                    <option value="4">Once a month</option>
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

                        <div class="hm-form-group">
                            <label>Days *</label>
                            <div style="display:flex;flex-wrap:wrap;gap:12px;padding:8px 0;">
                                <?php foreach ( $days as $idx => $label ): ?>
                                    <label class="hm-day-check">
                                        <input type="checkbox" class="hms-day-check" value="<?php echo (int) $idx; ?>">
                                        <?php echo esc_html( $label ); ?>
                                    </label>
                                <?php endforeach; ?>
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
                            <strong>Select one or more days.</strong> A separate schedule entry will be created for each day selected.
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
            currentStaffData: null,
            formatRotation: function(rotation, week) {
                if (rotation === 2) return 'Every 2 weeks (Week ' + week + ')';
                if (rotation === 3) return 'Every 3 weeks';
                if (rotation === 4) return 'Once a month';
                return 'Weekly';
            },
            viewDetails: function(staffData) {
                this.currentStaffData = staffData;
                document.getElementById('hm-detail-title').textContent = staffData.staff_name + ' - ' + staffData.staff_role;
                
                var content = document.getElementById('hm-detail-content');
                var html = '';
                
                // Group schedules by clinic
                var clinicGroups = {};
                staffData.schedules.forEach(function(schedule) {
                    var cid = schedule.clinic_id;
                    if (!clinicGroups[cid]) {
                        clinicGroups[cid] = {
                            name: schedule.clinic_name,
                            days: []
                        };
                    }
                    clinicGroups[cid].days.push(schedule);
                });
                
                // Build HTML for each clinic
                Object.keys(clinicGroups).forEach(function(clinicId) {
                    var clinic = clinicGroups[clinicId];
                    html += '<div style="margin-bottom:20px;padding-bottom:15px;border-bottom:1px solid #e2e8f0;">';
                    html += '<div style="font-weight:600;margin-bottom:10px;font-size:15px;">' + clinic.name + '</div>';
                    
                    clinic.days.forEach(function(schedule) {
                        var badge = schedule.is_active ? '<span class="hm-badge hm-badge-green">Active</span>' : '<span class="hm-badge hm-badge-red">Inactive</span>';
                        var rotation = hmSchedules.formatRotation(schedule.rotation_weeks, schedule.week_number);
                        
                        html += '<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;font-size:14px;">';
                        html += '<div>';
                        html += '<strong>' + schedule.day_label + '</strong> • ' + rotation + ' • ' + badge;
                        html += '</div>';
                        html += '<button class="hm-btn hm-btn-sm hm-btn-red" onclick="hmSchedules.deleteSchedule(' + schedule.id + ', \'' + hmSchedules.escapeJs(clinic.name + ' - ' + schedule.day_label) + '\')">Delete</button>';
                        html += '</div>';
                    });
                    
                    html += '</div>';
                });
                
                content.innerHTML = html;
                document.getElementById('hm-schedule-detail-modal').classList.add('open');
            },
            closeDetail: function() {
                document.getElementById('hm-schedule-detail-modal').classList.remove('open');
            },
            openAdd: function() {
                var isEdit = !!(this.currentStaffData && this.currentStaffData.staff_id);
                document.getElementById('hm-schedule-title').textContent = 'Add Schedule';
                document.getElementById('hms-id').value = '';
                document.getElementById('hms-staff').value = isEdit ? this.currentStaffData.staff_id : '';
                document.getElementById('hms-clinic').value = '';
                document.getElementById('hms-rotation').value = '1';
                document.getElementById('hms-week').value = '1';
                document.getElementById('hms-active').checked = true;
                
                // Uncheck all days
                document.querySelectorAll('.hms-day-check').forEach(function(cb) {
                    cb.checked = false;
                });
                
                hmSchedules.syncRotation();
                
                // Close detail modal if open
                document.getElementById('hm-schedule-detail-modal').classList.remove('open');
                document.getElementById('hm-schedule-modal').classList.add('open');
            },
            open: function(data) {
                var isEdit = !!(data && data.id);
                document.getElementById('hm-schedule-title').textContent = isEdit ? 'Edit Schedule' : 'Add Schedule';
                document.getElementById('hms-id').value = isEdit ? data.id : '';
                document.getElementById('hms-staff').value = isEdit ? data.staff_id : '';
                document.getElementById('hms-clinic').value = isEdit ? data.clinic_id : '';
                document.getElementById('hms-rotation').value = isEdit ? data.rotation_weeks : '1';
                document.getElementById('hms-week').value = isEdit ? data.week_number : '1';
                document.getElementById('hms-active').checked = isEdit ? !!data.is_active : true;
                
                // Uncheck all days first
                document.querySelectorAll('.hms-day-check').forEach(function(cb) {
                    cb.checked = false;
                });
                
                // If editing, check the day that corresponds to this record
                if (isEdit && data.day_of_week >= 0) {
                    var dayCheckbox = document.querySelector('.hms-day-check[value="' + data.day_of_week + '"]');
                    if (dayCheckbox) dayCheckbox.checked = true;
                }
                
                hmSchedules.syncRotation();
                document.getElementById('hm-schedule-modal').classList.add('open');
            },
            close: function() {
                document.getElementById('hm-schedule-modal').classList.remove('open');
            },
            escapeJs: function(str) {
                return str.replace(/\\/g, '\\\\').replace(/'/g, "\\'");
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

                // Get all checked days
                var days = [];
                document.querySelectorAll('.hms-day-check:checked').forEach(function(cb) {
                    days.push(parseInt(cb.value, 10));
                });
                
                if (days.length === 0) {
                    alert('Please select at least one day.');
                    return;
                }

                var rotation = document.getElementById('hms-rotation').value;
                var isEdit = document.getElementById('hms-id').value !== '';
                
                var btn = document.getElementById('hms-save');
                btn.textContent = 'Saving...'; btn.disabled = true;
                
                // For each selected day, create a save payload
                var saveCount = days.length;
                var completed = 0;
                var hasError = false;
                
                days.forEach(function(dayOfWeek, idx) {
                    var payload = {
                        action: 'hm_admin_save_schedule',
                        nonce: HM.nonce,
                        id: isEdit ? document.getElementById('hms-id').value : '',
                        staff_id: staffId,
                        clinic_id: clinicId,
                        day_of_week: dayOfWeek,
                        rotation_weeks: rotation,
                        week_number: rotation === '2' ? document.getElementById('hms-week').value : 1,
                        is_active: document.getElementById('hms-active').checked ? 1 : 0,
                        is_multi_day: saveCount > 1 ? 1 : 0,
                        day_index: idx + 1,
                        total_days: saveCount
                    };

                    jQuery.post(HM.ajax_url, payload, function(r) {
                        completed++;
                        if (!r.success) {
                            hasError = true;
                        }
                        if (completed === saveCount) {
                            if (hasError) {
                                alert('Error saving some schedules. Please try again.');
                                btn.textContent = 'Save'; 
                                btn.disabled = false;
                            } else {
                                location.reload();
                            }
                        }
                    });
                });
            },
            deleteSchedule: function(id, label) {
                if (!confirm('Delete "' + label + '"?')) return;
                jQuery.post(HM.ajax_url, { action:'hm_admin_delete_schedule', nonce:HM.nonce, id:id }, function(r) {
                    if (r.success) location.reload();
                    else alert(r.data || 'Error');
                });
            },
            del: function(id, name) {
                if (!confirm('Delete all schedules for "' + name + '"?')) return;
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
        $is_multi_day = intval( $_POST['is_multi_day'] ?? 0 ) === 1;
        $day_index = intval( $_POST['day_index'] ?? 1 );
        $is_active = intval( $_POST['is_active'] ?? 1 );

        if ( ! $staff_id || ! $clinic_id || $day < 0 || $day > 6 ) {
            wp_send_json_error( 'Missing fields' );
            return;
        }

        // Validate and normalize rotation
        if ( ! in_array( $rotation, [ 1, 2, 3, 4 ] ) ) {
            $rotation = 1;
        }

        // Only show week selector for 2-week rotation
        if ( $rotation !== 2 ) {
            $week = 1;
        } else {
            $week = $week === 2 ? 2 : 1;
        }

        // If this is a multi-day save and it's the first day, delete old schedules for this staff/clinic combo
        if ( $is_multi_day && $day_index === 1 && $id ) {
            HearMed_DB::get_results(
                "DELETE FROM hearmed_reference.dispenser_schedules WHERE staff_id = $1 AND clinic_id = $2",
                [ $staff_id, $clinic_id ]
            );
            $id = 0; // Force insert instead of update
        }

        $data = [
            'staff_id' => $staff_id,
            'clinic_id' => $clinic_id,
            'day_of_week' => $day,
            'rotation_weeks' => $rotation,
            'week_number' => $week,
            'is_active' => $is_active,
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
