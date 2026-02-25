<?php
/**
 * HearMed Admin — Staff Availability
 * Shortcode: [hearmed_admin_availability]
 *
 * Read-only reference page showing each staff member's current availability
 * based on holidays (staff_absences) and dispenser schedules.
 * Red = unavailable / on leave, Green = available / working today.
 *
 * @package HearMed_Portal
 * @since   5.2.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Availability {

    public function __construct() {
        add_shortcode('hearmed_admin_availability', [$this, 'render']);
    }

    private function get_staff() {
        return HearMed_DB::get_results(
            "SELECT id, first_name, last_name, role
             FROM hearmed_reference.staff
             WHERE is_active = true
             ORDER BY last_name, first_name"
        ) ?: [];
    }

    private function get_absences() {
        $t = HearMed_DB::table('staff_absences');
        $check = HearMed_DB::get_var("SELECT to_regclass('{$t}')");
        if ($check === null) return [];
        return HearMed_DB::get_results(
            "SELECT a.staff_id, a.absence_type, a.start_date, a.end_date, a.status, a.notes
             FROM {$t} a
             WHERE a.status = 'approved'
             ORDER BY a.start_date"
        ) ?: [];
    }

    private function get_schedules() {
        return HearMed_DB::get_results(
            "SELECT ds.staff_id, ds.day_of_week, ds.rotation_weeks, ds.week_number, c.clinic_name
             FROM hearmed_reference.dispenser_schedules ds
             JOIN hearmed_reference.clinics c ON ds.clinic_id = c.id
             WHERE ds.is_active = true AND c.is_active = true
             ORDER BY ds.day_of_week"
        ) ?: [];
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $staff     = $this->get_staff();
        $absences  = $this->get_absences();
        $schedules = $this->get_schedules();

        $today     = date('Y-m-d');
        $today_dow = (int) date('w'); // 0=Sun
        $days_map  = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

        // Index absences by staff_id
        $abs_by_staff = [];
        foreach ($absences as $a) {
            $abs_by_staff[(int)$a->staff_id][] = $a;
        }

        // Index schedules by staff_id
        $sched_by_staff = [];
        foreach ($schedules as $s) {
            $sched_by_staff[(int)$s->staff_id][] = $s;
        }

        // Build availability info per staff member
        $rows = [];
        foreach ($staff as $s) {
            $sid  = (int) $s->id;
            $name = trim($s->first_name . ' ' . $s->last_name);

            // Check if on leave today
            $on_leave = false;
            $leave_type = '';
            $leave_end = '';
            if (!empty($abs_by_staff[$sid])) {
                foreach ($abs_by_staff[$sid] as $a) {
                    if ($a->start_date <= $today && $a->end_date >= $today) {
                        $on_leave   = true;
                        $leave_type = $a->absence_type ?: 'Holiday';
                        $leave_end  = $a->end_date;
                        break;
                    }
                }
            }

            // Get upcoming leave (next 30 days)
            $upcoming = [];
            if (!empty($abs_by_staff[$sid])) {
                $future_limit = date('Y-m-d', strtotime('+30 days'));
                foreach ($abs_by_staff[$sid] as $a) {
                    if ($a->start_date > $today && $a->start_date <= $future_limit) {
                        $upcoming[] = $a;
                    }
                }
            }

            // Check schedule for today
            $today_clinics = [];
            if (!empty($sched_by_staff[$sid])) {
                foreach ($sched_by_staff[$sid] as $sc) {
                    if ((int)$sc->day_of_week === $today_dow) {
                        $today_clinics[] = $sc->clinic_name;
                    }
                }
            }

            $rows[] = [
                'name'          => $name,
                'role'          => $s->role ?: '—',
                'on_leave'      => $on_leave,
                'leave_type'    => $leave_type,
                'leave_end'     => $leave_end,
                'today_clinics' => $today_clinics,
                'upcoming'      => $upcoming,
                'has_schedule'  => !empty($sched_by_staff[$sid]),
            ];
        }

        $available_count = 0;
        $away_count = 0;
        foreach ($rows as $r) {
            if ($r['on_leave']) $away_count++;
            else $available_count++;
        }

        ob_start(); ?>
        <div class="hm-admin" id="hm-availability-admin">
            <div style="margin-bottom:16px;"><a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-btn">&larr; Back</a></div>
            <div class="hm-admin-hd">
                <h2>Staff Availability</h2>
            </div>

            <p style="color:var(--hm-text-light);font-size:13px;margin-bottom:20px;">
                Read-only view of current staff availability. Data is sourced from Staff Holidays and Dispenser Schedules.
                <strong>Today: <?php echo esc_html($days_map[$today_dow] . ', ' . date('j M Y')); ?></strong>
            </p>

            <!-- Summary bar -->
            <div style="display:flex;gap:16px;margin-bottom:24px;">
                <div class="hm-settings-panel" style="flex:1;text-align:center;padding:16px;">
                    <div style="font-size:28px;font-weight:700;color:#22c55e;"><?php echo $available_count; ?></div>
                    <div style="font-size:12px;color:var(--hm-text-light);">Available</div>
                </div>
                <div class="hm-settings-panel" style="flex:1;text-align:center;padding:16px;">
                    <div style="font-size:28px;font-weight:700;color:#ef4444;"><?php echo $away_count; ?></div>
                    <div style="font-size:12px;color:var(--hm-text-light);">On Leave</div>
                </div>
                <div class="hm-settings-panel" style="flex:1;text-align:center;padding:16px;">
                    <div style="font-size:28px;font-weight:700;color:var(--hm-navy);"><?php echo count($rows); ?></div>
                    <div style="font-size:12px;color:var(--hm-text-light);">Total Staff</div>
                </div>
            </div>

            <?php if (empty($rows)): ?>
                <div class="hm-empty-state"><p>No staff members found.</p></div>
            <?php else: ?>
            <table class="hm-table">
                <thead>
                    <tr>
                        <th style="width:40px">Status</th>
                        <th>Staff Member</th>
                        <th>Role</th>
                        <th>Today's Clinic(s)</th>
                        <th>Leave Info</th>
                        <th>Upcoming Leave</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr style="<?php echo $r['on_leave'] ? 'background:#fef2f2;' : ''; ?>">
                        <td style="text-align:center;">
                            <span style="display:inline-block;width:14px;height:14px;border-radius:50%;background:<?php echo $r['on_leave'] ? '#ef4444' : '#22c55e'; ?>;box-shadow:0 0 0 3px <?php echo $r['on_leave'] ? 'rgba(239,68,68,0.2)' : 'rgba(34,197,94,0.2)'; ?>;"></span>
                        </td>
                        <td><strong><?php echo esc_html($r['name']); ?></strong></td>
                        <td style="font-size:13px;color:var(--hm-text-light);"><?php echo esc_html($r['role']); ?></td>
                        <td>
                            <?php if ($r['on_leave']): ?>
                                <span style="color:#ef4444;font-size:12px;">On leave</span>
                            <?php elseif (!empty($r['today_clinics'])): ?>
                                <?php foreach ($r['today_clinics'] as $cn): ?>
                                    <span class="hm-badge hm-badge-blue" style="margin-right:4px;"><?php echo esc_html($cn); ?></span>
                                <?php endforeach; ?>
                            <?php elseif ($r['has_schedule']): ?>
                                <span style="color:var(--hm-text-light);font-size:12px;">Not scheduled today</span>
                            <?php else: ?>
                                <span style="color:var(--hm-text-light);font-size:12px;">No schedule set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($r['on_leave']): ?>
                                <span class="hm-badge hm-badge-red"><?php echo esc_html(ucfirst($r['leave_type'])); ?></span>
                                <span style="font-size:11px;color:var(--hm-text-light);margin-left:4px;">until <?php echo esc_html($r['leave_end']); ?></span>
                            <?php else: ?>
                                <span style="color:var(--hm-text-light);font-size:12px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;">
                            <?php if (!empty($r['upcoming'])):
                                foreach ($r['upcoming'] as $u): ?>
                                    <div style="margin-bottom:2px;">
                                        <span class="hm-badge hm-badge-orange"><?php echo esc_html(ucfirst($u->absence_type ?: 'Holiday')); ?></span>
                                        <span style="color:var(--hm-text-light);"><?php echo esc_html($u->start_date . ' → ' . $u->end_date); ?></span>
                                    </div>
                                <?php endforeach;
                            else: ?>
                                <span style="color:var(--hm-text-light);">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

new HearMed_Admin_Availability();
