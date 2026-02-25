<?php
/**
 * HearMed Admin — Staff Holidays
 * Shortcode: [hearmed_admin_holidays]
 * Staff holiday tracker with red/green status lights.
 * Uses hearmed_core.staff_absences table.
 *
 * @package HearMed_Portal
 * @since   5.1.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Holidays {

    public function __construct() {
        add_shortcode('hearmed_admin_holidays', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_holiday', [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_holiday', [$this, 'ajax_delete']);
    }

    private function get_staff() {
        return HearMed_DB::get_results(
            "SELECT id, first_name, last_name FROM hearmed_reference.staff WHERE is_active = true ORDER BY last_name, first_name"
        ) ?: [];
    }

    private function get_holidays() {
        $t = HearMed_DB::table('staff_absences');
        $check = HearMed_DB::get_var("SELECT to_regclass('{$t}')");
        if ($check === null) return [];
        return HearMed_DB::get_results(
            "SELECT a.*, s.first_name, s.last_name
             FROM {$t} a
             LEFT JOIN hearmed_reference.staff s ON a.staff_id = s.id
             ORDER BY a.start_date DESC"
        ) ?: [];
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $holidays = $this->get_holidays();
        $staff = $this->get_staff();
        $today = date('Y-m-d');

        // Summary: who's on holiday today
        $on_holiday_today = [];
        foreach ($holidays as $h) {
            if ($h->start_date <= $today && $h->end_date >= $today && $h->status === 'approved') {
                $on_holiday_today[] = trim($h->first_name . ' ' . $h->last_name);
            }
        }

        ob_start(); ?>
        <div class="hm-admin">
            <div style="margin-bottom:16px;"><a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-btn">&larr; Back</a></div>
            <div class="hm-admin-hd">
                <h2>Staff Holidays</h2>
                <button class="hm-btn hm-btn-teal" onclick="hmHol.open()">+ Add Holiday</button>
            </div>

            <!-- Status lights summary -->
            <div class="hm-settings-panel" style="margin-bottom:20px;">
                <h3 style="font-size:14px;margin-bottom:12px;">Today's Status</h3>
                <div style="display:flex;flex-wrap:wrap;gap:12px;">
                    <?php foreach ($staff as $s):
                        $name = trim($s->first_name . ' ' . $s->last_name);
                        $is_away = in_array($name, $on_holiday_today);
                    ?>
                    <div style="display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;background:var(--hm-bg-muted);font-size:13px;">
                        <span style="width:10px;height:10px;border-radius:50%;background:<?php echo $is_away ? '#ef4444' : '#22c55e'; ?>;display:inline-block;"></span>
                        <span><?php echo esc_html($name); ?></span>
                        <?php if ($is_away): ?>
                            <span style="font-size:11px;color:#ef4444;font-weight:600;">Away</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (empty($holidays)): ?>
                <div class="hm-empty-state"><p>No holiday records yet.</p></div>
            <?php else: ?>
            <table class="hm-table">
                <thead>
                    <tr>
                        <th>Staff Member</th>
                        <th>Start Date</th>
                        <th>End Date</th>
                        <th>Days</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Notes</th>
                        <th style="width:100px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($holidays as $h):
                    $row = json_encode((array) $h, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                    $name = trim($h->first_name . ' ' . $h->last_name);
                    $days = 1;
                    if ($h->start_date && $h->end_date) {
                        $d1 = new DateTime($h->start_date);
                        $d2 = new DateTime($h->end_date);
                        $days = max(1, $d2->diff($d1)->days + 1);
                    }
                    $is_current = ($h->start_date <= $today && $h->end_date >= $today);
                    $is_future = ($h->start_date > $today);
                ?>
                    <tr>
                        <td>
                            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?php echo $is_current ? '#ef4444' : '#22c55e'; ?>;margin-right:6px;"></span>
                            <strong><?php echo esc_html($name); ?></strong>
                        </td>
                        <td><?php echo esc_html($h->start_date); ?></td>
                        <td><?php echo esc_html($h->end_date); ?></td>
                        <td class="hm-num"><?php echo $days; ?></td>
                        <td><span class="hm-badge hm-badge-blue"><?php echo esc_html($h->absence_type ?: 'Holiday'); ?></span></td>
                        <td>
                            <?php if ($h->status === 'approved'): ?>
                                <span class="hm-badge hm-badge-green">Approved</span>
                            <?php elseif ($h->status === 'pending'): ?>
                                <span class="hm-badge hm-badge-orange">Pending</span>
                            <?php elseif ($h->status === 'rejected'): ?>
                                <span class="hm-badge hm-badge-red">Rejected</span>
                            <?php else: ?>
                                <span class="hm-badge"><?php echo esc_html($h->status ?: '—'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:12px;color:var(--hm-text-light);"><?php echo esc_html($h->notes ?: '—'); ?></td>
                        <td class="hm-table-acts">
                            <button class="hm-btn hm-btn-sm" onclick='hmHol.open(<?php echo $row; ?>)'>Edit</button>
                            <button class="hm-btn hm-btn-sm hm-btn-red" onclick="hmHol.del(<?php echo (int) $h->id; ?>)">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- Modal -->
            <div class="hm-modal-bg" id="hm-hol-modal">
                <div class="hm-modal" style="width:640px">
                    <div class="hm-modal-hd">
                        <h3 id="hm-hol-title">Add Holiday</h3>
                        <button class="hm-modal-x" onclick="hmHol.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hmh-id">
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Staff Member *</label>
                                <select id="hmh-staff">
                                    <option value="">Select staff</option>
                                    <?php foreach ($staff as $s): ?>
                                        <option value="<?php echo (int) $s->id; ?>"><?php echo esc_html(trim($s->first_name . ' ' . $s->last_name)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="hm-form-group">
                                <label>Type</label>
                                <select id="hmh-type">
                                    <option value="holiday">Holiday</option>
                                    <option value="sick">Sick Leave</option>
                                    <option value="personal">Personal Day</option>
                                    <option value="training">Training</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Start Date *</label>
                                <input type="date" id="hmh-start">
                            </div>
                            <div class="hm-form-group">
                                <label>End Date *</label>
                                <input type="date" id="hmh-end">
                            </div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Status</label>
                                <select id="hmh-status">
                                    <option value="approved">Approved</option>
                                    <option value="pending">Pending</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                        </div>
                        <div class="hm-form-group">
                            <label>Notes</label>
                            <textarea id="hmh-notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmHol.close()">Cancel</button>
                        <button class="hm-btn hm-btn-teal" onclick="hmHol.save()" id="hmh-save">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        var hmHol = {
            open: function(data) {
                var isEdit = !!(data && data.id);
                document.getElementById('hm-hol-title').textContent = isEdit ? 'Edit Holiday' : 'Add Holiday';
                document.getElementById('hmh-id').value     = isEdit ? data.id : '';
                document.getElementById('hmh-staff').value  = isEdit ? (data.staff_id || '') : '';
                document.getElementById('hmh-type').value   = isEdit ? (data.absence_type || 'holiday') : 'holiday';
                document.getElementById('hmh-start').value  = isEdit ? (data.start_date || '') : '';
                document.getElementById('hmh-end').value    = isEdit ? (data.end_date || '') : '';
                document.getElementById('hmh-status').value = isEdit ? (data.status || 'approved') : 'approved';
                document.getElementById('hmh-notes').value  = isEdit ? (data.notes || '') : '';
                document.getElementById('hm-hol-modal').classList.add('open');
            },
            close: function() { document.getElementById('hm-hol-modal').classList.remove('open'); },
            save: function() {
                var staff = document.getElementById('hmh-staff').value;
                var start = document.getElementById('hmh-start').value;
                var end   = document.getElementById('hmh-end').value;
                if (!staff) { alert('Staff member is required.'); return; }
                if (!start || !end) { alert('Start and end dates are required.'); return; }

                var btn = document.getElementById('hmh-save');
                btn.textContent = 'Saving...'; btn.disabled = true;

                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_save_holiday',
                    nonce: HM.nonce,
                    id: document.getElementById('hmh-id').value,
                    staff_id: staff,
                    absence_type: document.getElementById('hmh-type').value,
                    start_date: start,
                    end_date: end,
                    status: document.getElementById('hmh-status').value,
                    notes: document.getElementById('hmh-notes').value
                }, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error'); btn.textContent = 'Save'; btn.disabled = false; }
                });
            },
            del: function(id) {
                if (!confirm('Delete this holiday record?')) return;
                jQuery.post(HM.ajax_url, { action:'hm_admin_delete_holiday', nonce:HM.nonce, id:id }, function(r) {
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
        $staff_id = intval($_POST['staff_id'] ?? 0);
        $start = sanitize_text_field($_POST['start_date'] ?? '');
        $end = sanitize_text_field($_POST['end_date'] ?? '');

        if (!$staff_id || !$start || !$end) {
            wp_send_json_error('Staff, start date and end date are required');
            return;
        }

        $data = [
            'staff_id'     => $staff_id,
            'absence_type' => sanitize_text_field($_POST['absence_type'] ?? 'holiday'),
            'start_date'   => $start,
            'end_date'     => $end,
            'status'       => sanitize_text_field($_POST['status'] ?? 'approved'),
            'notes'        => sanitize_text_field($_POST['notes'] ?? ''),
            'updated_at'   => current_time('mysql'),
        ];

        $table = HearMed_DB::table('staff_absences');

        if ($id) {
            $result = HearMed_DB::update($table, $data, ['id' => $id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $id = HearMed_DB::insert($table, $data);
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

        $table = HearMed_DB::table('staff_absences');
        // Hard delete holiday records
        HearMed_DB::get_results("DELETE FROM {$table} WHERE id = $1", [$id]);
        wp_send_json_success();
    }
}

new HearMed_Admin_Holidays();
