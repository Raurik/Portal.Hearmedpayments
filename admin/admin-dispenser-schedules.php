<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_Admin_Dispenser_Schedules {

    public function __construct() {
        add_shortcode('hearmed_dispenser_schedules', [ $this, 'render' ]);
        add_action('wp_ajax_hm_admin_save_dispenser_schedule', [ $this, 'ajax_save' ]);
        add_action('wp_ajax_hm_admin_delete_dispenser_schedule', [ $this, 'ajax_delete' ]);
    }

    // Fetch all dispenser schedules with staff and clinic info
    private function get_schedules() {
        return HearMed_DB::get_results(
            "SELECT ds.id, ds.staff_id, s.first_name, s.last_name, s.role, ds.clinic_id, c.clinic_name, ds.day_of_week, ds.rotation_weeks, ds.week_number, ds.is_active
             FROM hearmed_reference.dispenser_schedules ds
             JOIN hearmed_reference.staff s ON ds.staff_id = s.id
             JOIN hearmed_reference.clinics c ON ds.clinic_id = c.id
             WHERE ds.is_active = true AND s.is_active = true AND c.is_active = true
             ORDER BY s.last_name, s.first_name, c.clinic_name, ds.day_of_week"
        ) ?: [];
    }


    // Render a calendar-style detail page for a staff member
    private function render_detail_page($staff_data, $clinics, $days) {
        ob_start();
        ?>
        <div class="hm-admin" id="hm-schedule-detail-page">
            <div class="hm-admin-hd" style="display:flex;align-items:center;gap:20px;">
                <a href="<?php echo esc_attr( strtok($_SERVER['REQUEST_URI'], '?') ); ?>" class="hm-btn">&larr; Back</a>
                <h2 style="margin:0;">Dispenser Schedule: <?php echo esc_html($staff_data['staff_name']); ?> <span style="font-size:16px;color:#64748b;">(<?php echo esc_html($staff_data['staff_role']); ?>)</span></h2>
            </div>
            <div style="margin:20px 0 30px 0;">
                <button class="hm-btn hm-btn-teal" onclick="window.location.href='<?php echo esc_attr( strtok($_SERVER['REQUEST_URI'], '?') ); ?>#add'">+ Add Schedule</button>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:32px;">
                <?php
                // Group schedules by clinic
                $clinicGroups = [];
                foreach ($staff_data['schedules'] as $s) {
                    $cid = $s['clinic_id'];
                    if (!isset($clinicGroups[$cid])) {
                        $clinicGroups[$cid] = [
                            'name' => $s['clinic_name'],
                            'days' => []
                        ];
                    }
                    $clinicGroups[$cid]['days'][] = $s;
                }
                foreach ($clinicGroups as $clinic) {
                ?>
                <div style="min-width:260px;flex:1 1 320px;background:#f8fafc;border-radius:10px;padding:18px 18px 10px 18px;box-shadow:0 2px 8px #e0e7ef;">
                    <div style="font-weight:600;font-size:17px;margin-bottom:10px;letter-spacing:0.5px;">
                        <?php echo esc_html($clinic['name']); ?>
                    </div>
                    <table style="width:100%;border-collapse:collapse;">
                        <thead>
                            <tr style="background:#e0e7ef;font-size:14px;">
                                <th style="padding:6px 4px;">Day</th>
                                <th style="padding:6px 4px;">Rotation</th>
                                <th style="padding:6px 4px;">Status</th>
                                <th style="padding:6px 4px;width:60px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($clinic['days'] as $d):
                            $badge = $d['is_active'] ? '<span class="hm-badge hm-badge-green">Active</span>' : '<span class="hm-badge hm-badge-red">Inactive</span>';
                            if ($d['rotation_weeks'] === 2) $rotation = 'Every 2 weeks (Week ' . $d['week_number'] . ')';
                            elseif ($d['rotation_weeks'] === 3) $rotation = 'Every 3 weeks';
                            elseif ($d['rotation_weeks'] === 4) $rotation = 'Once a month';
                            else $rotation = 'Weekly';
                        ?>
                            <tr style="border-bottom:1px solid #e5e7eb;">
                                <td style="padding:6px 4px;"><strong><?php echo esc_html($d['day_label']); ?></strong></td>
                                <td style="padding:6px 4px;"><?php echo esc_html($rotation); ?></td>
                                <td style="padding:6px 4px;"><?php echo $badge; ?></td>
                                <td style="padding:6px 4px;text-align:right;">
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Delete this schedule?');">
                                        <input type="hidden" name="delete_schedule_id" value="<?php echo (int)$d['id']; ?>">
                                        <button class="hm-btn hm-btn-sm hm-btn-red" type="submit">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php } ?>
            </div>
        </div>
        <style>
        #hm-schedule-detail-page .hm-btn { font-size:14px; }
        #hm-schedule-detail-page table th, #hm-schedule-detail-page table td { font-size:14px; }
        #hm-schedule-detail-page h2 { font-size:22px; }
        </style>
        <?php
        // Handle delete
        if (!empty($_POST['delete_schedule_id'])) {
            $del_id = intval($_POST['delete_schedule_id']);
            HearMed_DB::update('hearmed_reference.dispenser_schedules', ['is_active'=>false,'updated_at'=>current_time('mysql')], ['id'=>$del_id]);
            echo "<script>window.location.href='" . strtok($_SERVER['REQUEST_URI'], '?') . "?staff=" . (int)$staff_data['staff_id'] . "';</script>";
        }
        return ob_get_clean();
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

        // If viewing details for a staff member
        $detail_id = isset($_GET['staff']) ? intval($_GET['staff']) : 0;
        if ($detail_id && isset($staff_schedules[$detail_id])) {
            // Only output the detail page, nothing else
            echo $this->render_detail_page($staff_schedules[$detail_id], $clinics, $days);
            return;
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
            <table class="hm-table" id="hm-disp-table">
                <thead>
                    <tr>
                        <th>Staff</th>
                        <th>Role</th>
                        <th>Clinics Assigned</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ( $payload as $staff_data ):
                    $clinic_ids = [];
                    $clinic_names = [];
                    foreach ( $staff_data['schedules'] as $schedule ) {
                        if ( ! in_array( $schedule['clinic_id'], $clinic_ids ) ) {
                            $clinic_ids[] = $schedule['clinic_id'];
                            $clinic_names[] = $schedule['clinic_name'];
                        }
                    }
                    $all_active = true;
                    foreach ( $staff_data['schedules'] as $schedule ) {
                        if ( ! $schedule['is_active'] ) {
                            $all_active = false;
                            break;
                        }
                    }
                ?>
                    <tr class="hm-disp-row" data-staff="<?php echo (int)$staff_data['staff_id']; ?>">
                        <td><strong><?php echo esc_html( $staff_data['staff_name'] ); ?></strong></td>
                        <td><?php echo esc_html( $staff_data['staff_role'] ?: '—' ); ?></td>
                        <td><?php echo esc_html( implode( ', ', $clinic_names ) ?: '—' ); ?></td>
                        <td><?php echo $all_active ? '<span class="hm-badge hm-badge-green">Active</span>' : '<span class="hm-badge hm-badge-red">Inactive</span>'; ?></td>
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
        // Make each row clickable and highlight on hover
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.hm-disp-row').forEach(function(row) {
                row.style.cursor = 'pointer';
                row.addEventListener('mouseenter', function() {
                    row.style.background = '#f0f9ff';
                });
                row.addEventListener('mouseleave', function() {
                    row.style.background = '';
                });
                row.addEventListener('click', function() {
                    var staffId = row.getAttribute('data-staff');
                    window.location = window.location.pathname + '?staff=' + staffId;
                });
            });
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
