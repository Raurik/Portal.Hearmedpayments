<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_Admin_Dispenser_Schedules {

    public function __construct() {
        add_shortcode('hearmed_dispenser_schedules', [ $this, 'render' ]);
        add_action('wp_ajax_hm_admin_save_dispenser_schedule', [ $this, 'ajax_save' ]);
        add_action('wp_ajax_hm_admin_delete_dispenser_schedule', [ $this, 'ajax_delete' ]);
        add_action('wp_ajax_hm_admin_bulk_delete_schedules', [ $this, 'ajax_bulk_delete' ]);

        // Auto-migrate: ensure effective_from / effective_to columns exist
        $this->ensure_schedule_columns();
    }

    /**
     * Auto-add effective_from and effective_to columns if missing.
     */
    private function ensure_schedule_columns() {
        static $done = false;
        if ( $done ) return;
        $done = true;

        $cols = HearMed_DB::get_results(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = 'hearmed_reference'
               AND table_name   = 'dispenser_schedules'
               AND column_name IN ('effective_from','effective_to')"
        );
        $existing = [];
        if ( $cols ) {
            foreach ( $cols as $c ) { $existing[] = $c->column_name; }
        }
        $alters = [];
        if ( ! in_array( 'effective_from', $existing ) ) {
            $alters[] = 'ADD COLUMN effective_from DATE';
        }
        if ( ! in_array( 'effective_to', $existing ) ) {
            $alters[] = 'ADD COLUMN effective_to DATE';
        }
        if ( ! empty( $alters ) ) {
            $sql = 'ALTER TABLE hearmed_reference.dispenser_schedules ' . implode( ', ', $alters );
            HearMed_DB::query( $sql );
            // Add performance index
            HearMed_DB::query(
                "CREATE INDEX IF NOT EXISTS idx_dispenser_schedules_dates
                 ON hearmed_reference.dispenser_schedules (staff_id, clinic_id, effective_from, effective_to)"
            );
            error_log( '[HearMed] Auto-migrated dispenser_schedules: added ' . implode( ', ', $alters ) );
        }
    }

    // Fetch current (active) schedules — effective_to IS NULL or >= today
    private function get_schedules() {
        return HearMed_DB::get_results(
            "SELECT ds.id, ds.staff_id, s.first_name, s.last_name, s.role,
                    ds.clinic_id, c.clinic_name, ds.day_of_week,
                    ds.rotation_weeks, ds.week_number, ds.is_active,
                    ds.effective_from, ds.effective_to
             FROM hearmed_reference.dispenser_schedules ds
             JOIN hearmed_reference.staff s ON ds.staff_id = s.id
             JOIN hearmed_reference.clinics c ON ds.clinic_id = c.id
             WHERE ds.is_active = true AND s.is_active = true AND c.is_active = true
               AND (ds.effective_to IS NULL OR ds.effective_to >= CURRENT_DATE)
             ORDER BY s.last_name, s.first_name, c.clinic_name, ds.day_of_week"
        ) ?: [];
    }

    // Fetch historical (ended) schedules for a specific staff member
    private function get_history( $staff_id ) {
        return HearMed_DB::get_results(
            "SELECT ds.id, ds.staff_id, ds.clinic_id, c.clinic_name,
                    ds.day_of_week, ds.rotation_weeks, ds.week_number,
                    ds.effective_from, ds.effective_to, ds.is_active
             FROM hearmed_reference.dispenser_schedules ds
             JOIN hearmed_reference.clinics c ON ds.clinic_id = c.id
             WHERE ds.staff_id = $1
               AND (ds.is_active = false OR (ds.effective_to IS NOT NULL AND ds.effective_to < CURRENT_DATE))
             ORDER BY ds.effective_to DESC NULLS LAST, c.clinic_name, ds.day_of_week",
            [ $staff_id ]
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

    private function format_date( $d ) {
        if ( ! $d ) return '';
        return date( 'j M Y', strtotime( $d ) );
    }

    // ─── DETAIL PAGE ───────────────────────────────────────────────
    private function render_detail_page($staff_data, $clinics, $days) {
        $history = $this->get_history( (int) $staff_data['staff_id'] );
        ob_start();
        ?>
        <style>
        .hmsd-bulk-bar{display:none;align-items:center;gap:12px;padding:10px 16px;background:#fef3c7;border:1px solid #f59e0b;border-radius:8px;margin-bottom:16px;}
        .hmsd-bulk-bar.visible{display:flex;}
        .hmsd-bulk-bar .hmsd-bulk-count{font-weight:600;font-size:13px;color:#92400e;}
        .hmsd-cb{width:16px;height:16px;cursor:pointer;accent-color:var(--hm-teal,#0d9488);}
        .hmsd-dates{font-size:11px;color:#64748b;margin-top:2px;}
        .hmsd-history{margin-top:40px;}
        .hmsd-history h3{font-size:15px;font-weight:600;color:#475569;margin-bottom:12px;display:flex;align-items:center;gap:8px;cursor:pointer;user-select:none;}
        .hmsd-history h3 .hmsd-arrow{transition:transform .2s;display:inline-block;}
        .hmsd-history h3 .hmsd-arrow.open{transform:rotate(90deg);}
        .hmsd-history-body{display:none;}
        .hmsd-history-body.open{display:block;}
        .hmsd-end-date-row{display:flex;align-items:center;gap:12px;margin-top:8px;}
        </style>

        <div class="hm-admin" id="hm-schedule-detail-page">
            <a href="<?php echo esc_attr( strtok($_SERVER['REQUEST_URI'], '?') ); ?>" class="hm-back">← Back</a>
            <div class="hm-page-header">
                <h1 class="hm-page-title">Dispenser Schedule: <?php echo esc_html($staff_data['staff_name']); ?> <span style="font-size:16px;color:var(--hm-text-light);">(<?php echo esc_html($staff_data['staff_role']); ?>)</span></h1>
                <div class="hm-page-header__actions">
                    <button class="hm-btn hm-btn--primary" onclick="hmSchedEdit.open({staff_id:<?php echo (int)$staff_data['staff_id']; ?>})">+ Add Schedule</button>
                </div>
            </div>

            <!-- Bulk action bar -->
            <div class="hmsd-bulk-bar" id="hmsd-bulk-bar">
                <span class="hmsd-bulk-count"><span id="hmsd-bulk-count">0</span> selected</span>
                <div style="flex:1;"></div>
                <div class="hmsd-end-date-row">
                    <label style="font-size:12px;font-weight:500;color:#475569;white-space:nowrap;">End date:</label>
                    <input type="date" id="hmsd-bulk-end-date" value="<?php echo date('Y-m-d'); ?>" style="font-size:12px;padding:4px 8px;border:1px solid #cbd5e1;border-radius:6px;">
                </div>
                <button class="hm-btn hm-btn--sm hm-btn--danger" onclick="hmSchedBulk.deleteSelected()">End Selected</button>
                <button class="hm-btn hm-btn--sm" onclick="hmSchedBulk.clearAll()">Clear</button>
            </div>

            <div style="display:flex;flex-wrap:wrap;gap:32px;">
                <?php
                // Group current schedules by clinic
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

                if ( empty($clinicGroups) ): ?>
                    <div class="hm-empty-state" style="width:100%;"><p>No current schedules.</p></div>
                <?php else:
                foreach ($clinicGroups as $clinic) {
                ?>
                <div style="min-width:260px;flex:1 1 320px;">
                    <div style="font-weight:600;font-size:15px;margin-bottom:10px;color:#151B33;">
                        <?php echo esc_html($clinic['name']); ?>
                    </div>
                    <table class="hm-table" style="width:100%;">
                        <thead>
                            <tr>
                                <th style="width:30px;"><input type="checkbox" class="hmsd-cb hmsd-select-all" data-clinic="<?php echo esc_attr($clinic['name']); ?>"></th>
                                <th>Day</th>
                                <th>Rotation</th>
                                <th>Effective From</th>
                                <th style="width:60px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($clinic['days'] as $d):
                            if ($d['rotation_weeks'] === 2) $rotation = 'Every 2 weeks (Wk ' . $d['week_number'] . ')';
                            elseif ($d['rotation_weeks'] === 3) $rotation = 'Every 3 weeks';
                            elseif ($d['rotation_weeks'] === 4) $rotation = 'Once a month';
                            else $rotation = 'Weekly';
                        ?>
                            <tr>
                                <td><input type="checkbox" class="hmsd-cb hmsd-row-cb" value="<?php echo (int)$d['id']; ?>"></td>
                                <td><strong><?php echo esc_html($d['day_label']); ?></strong></td>
                                <td><?php echo esc_html($rotation); ?></td>
                                <td>
                                    <?php if (!empty($d['effective_from'])): ?>
                                        <?php echo esc_html($this->format_date($d['effective_from'])); ?>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <button class="hm-btn hm-btn--sm" onclick='hmSchedEdit.open(<?php echo json_encode($d, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)'>Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php }
                endif; ?>
            </div>

            <!-- Historical Schedules -->
            <?php if ( !empty($history) ): ?>
            <div class="hmsd-history">
                <h3 onclick="hmSchedHistory.toggle()">
                    <span class="hmsd-arrow" id="hmsd-history-arrow">▶</span>
                    Schedule History (<?php echo count($history); ?>)
                </h3>
                <div class="hmsd-history-body" id="hmsd-history-body">
                    <?php
                    // Group history by clinic
                    $histGroups = [];
                    foreach ($history as $h) {
                        $cid = (int) $h->clinic_id;
                        if (!isset($histGroups[$cid])) {
                            $histGroups[$cid] = [
                                'name' => $h->clinic_name,
                                'rows' => []
                            ];
                        }
                        $histGroups[$cid]['rows'][] = $h;
                    }
                    foreach ($histGroups as $hg):
                    ?>
                    <div style="margin-bottom:20px;">
                        <div style="font-weight:600;font-size:14px;margin-bottom:8px;color:#64748b;">
                            <?php echo esc_html($hg['name']); ?>
                        </div>
                        <table class="hm-table" style="width:100%;opacity:0.75;">
                            <thead>
                                <tr>
                                    <th>Day</th>
                                    <th>Rotation</th>
                                    <th>From</th>
                                    <th>To</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($hg['rows'] as $hr):
                                $dayLabel = $days[(int)$hr->day_of_week] ?? 'Unknown';
                                $rot = (int) $hr->rotation_weeks;
                                if ($rot === 2) $rotLabel = 'Every 2 wks (Wk ' . (int)$hr->week_number . ')';
                                elseif ($rot === 3) $rotLabel = 'Every 3 wks';
                                elseif ($rot === 4) $rotLabel = 'Monthly';
                                else $rotLabel = 'Weekly';
                            ?>
                                <tr>
                                    <td><?php echo esc_html($dayLabel); ?></td>
                                    <td><?php echo esc_html($rotLabel); ?></td>
                                    <td><?php echo $hr->effective_from ? esc_html($this->format_date($hr->effective_from)) : '<span style="color:#94a3b8;">—</span>'; ?></td>
                                    <td><?php echo $hr->effective_to ? esc_html($this->format_date($hr->effective_to)) : '<span style="color:#94a3b8;">—</span>'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Edit/Add Modal (detail page) -->
            <div class="hm-modal-bg" id="hm-sched-edit-modal">
                <div class="hm-modal hm-modal--md">
                    <div class="hm-modal-hd">
                        <h3 id="hm-sched-edit-title">Edit Schedule</h3>
                        <button class="hm-close" onclick="hmSchedEdit.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hmse-id">
                        <input type="hidden" id="hmse-staff" value="<?php echo (int)$staff_data['staff_id']; ?>">
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Clinic *</label>
                                <select id="hmse-clinic" data-entity="clinic" data-label="Clinic">
                                    <option value="">Select clinic</option>
                                    <?php foreach ($clinics as $c): ?>
                                        <option value="<?php echo (int) $c->id; ?>"><?php echo esc_html($c->clinic_name); ?></option>
                                    <?php endforeach; ?>
                                    <option value="__add_new__">+ Add New…</option>
                                </select>
                            </div>
                            <div class="hm-form-group">
                                <label>Day(s) *</label>
                                <div style="display:flex;flex-wrap:wrap;gap:12px;padding:8px 0;">
                                    <?php foreach ($days as $idx => $label): ?>
                                        <label class="hm-day-check">
                                            <input type="checkbox" class="hmse-day-check" value="<?php echo (int)$idx; ?>">
                                            <?php echo esc_html($label); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Rotation</label>
                                <select id="hmse-rotation" onchange="document.getElementById('hmse-week-row').style.display=this.value==='2'?'':'none'">
                                    <option value="1">Weekly</option>
                                    <option value="2">Every 2 weeks</option>
                                    <option value="3">Every 3 weeks</option>
                                    <option value="4">Once a month</option>
                                </select>
                            </div>
                            <div class="hm-form-group" id="hmse-week-row" style="display:none">
                                <label>Week</label>
                                <select id="hmse-week">
                                    <option value="1">Week 1</option>
                                    <option value="2">Week 2</option>
                                </select>
                            </div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Effective From</label>
                                <input type="date" id="hmse-effective-from" style="width:100%;">
                                <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Leave blank for immediate / no start date</div>
                            </div>
                        </div>
                        <div class="hm-form-group">
                            <label class="hm-toggle">
                                <input type="checkbox" id="hmse-active" checked>
                                <span class="hm-toggle-track"></span>
                                Active
                            </label>
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmSchedEdit.close()">Cancel</button>
                        <button class="hm-btn hm-btn--primary" onclick="hmSchedEdit.save()" id="hmse-save">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        /* ── Bulk selection ── */
        var hmSchedBulk = {
            update: function() {
                var checked = document.querySelectorAll('.hmsd-row-cb:checked');
                var bar = document.getElementById('hmsd-bulk-bar');
                document.getElementById('hmsd-bulk-count').textContent = checked.length;
                bar.classList.toggle('visible', checked.length > 0);
            },
            clearAll: function() {
                document.querySelectorAll('.hmsd-row-cb, .hmsd-select-all').forEach(function(cb) { cb.checked = false; });
                this.update();
            },
            deleteSelected: function() {
                var ids = [];
                document.querySelectorAll('.hmsd-row-cb:checked').forEach(function(cb) { ids.push(parseInt(cb.value)); });
                if (!ids.length) return;
                var endDate = document.getElementById('hmsd-bulk-end-date').value;
                if (!confirm('End ' + ids.length + ' schedule(s)' + (endDate ? ' effective ' + endDate : '') + '?')) return;
                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_bulk_delete_schedules',
                    nonce: HM.nonce,
                    ids: ids.join(','),
                    effective_to: endDate
                }, function(r) {
                    if (r.success) location.reload();
                    else alert(r.data || 'Error');
                });
            }
        };
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('hmsd-row-cb')) hmSchedBulk.update();
            if (e.target.classList.contains('hmsd-select-all')) {
                var table = e.target.closest('table');
                table.querySelectorAll('.hmsd-row-cb').forEach(function(cb) { cb.checked = e.target.checked; });
                hmSchedBulk.update();
            }
        });

        /* ── History toggle ── */
        var hmSchedHistory = {
            toggle: function() {
                var body = document.getElementById('hmsd-history-body');
                var arrow = document.getElementById('hmsd-history-arrow');
                if (!body) return;
                body.classList.toggle('open');
                arrow.classList.toggle('open');
            }
        };

        /* ── Edit / Add modal ── */
        var hmSchedEdit = {
            open: function(data) {
                var isEdit = !!(data && data.id);
                document.getElementById('hm-sched-edit-title').textContent = isEdit ? 'Edit Schedule' : 'Add Schedule';
                document.getElementById('hmse-id').value       = isEdit ? data.id : '';
                document.getElementById('hmse-clinic').value   = data.clinic_id || '';
                document.querySelectorAll('.hmse-day-check').forEach(function(cb) { cb.checked = false; });
                if (data.day_of_week !== undefined) {
                    var dayCheck = document.querySelector('.hmse-day-check[value="'+data.day_of_week+'"]');
                    if (dayCheck) dayCheck.checked = true;
                }
                document.getElementById('hmse-rotation').value = data.rotation_weeks || '1';
                document.getElementById('hmse-week').value     = data.week_number || '1';
                document.getElementById('hmse-active').checked = data.is_active !== false;
                document.getElementById('hmse-effective-from').value = data.effective_from || '';
                document.getElementById('hmse-week-row').style.display = (data.rotation_weeks == 2) ? '' : 'none';
                document.getElementById('hm-sched-edit-modal').classList.add('open');
            },
            close: function() { document.getElementById('hm-sched-edit-modal').classList.remove('open'); },
            save: function() {
                var clinic = document.getElementById('hmse-clinic').value;
                if (!clinic) { alert('Please select a clinic.'); return; }
                var days = [];
                document.querySelectorAll('.hmse-day-check:checked').forEach(function(cb) { days.push(parseInt(cb.value)); });
                if (!days.length) { alert('Please select at least one day.'); return; }
                var btn = document.getElementById('hmse-save');
                btn.textContent = 'Saving...'; btn.disabled = true;
                var schedId  = document.getElementById('hmse-id').value;
                var staffId  = document.getElementById('hmse-staff').value;
                var rotation = document.getElementById('hmse-rotation').value;
                var week     = document.getElementById('hmse-week').value;
                var isActive = document.getElementById('hmse-active').checked ? 1 : 0;
                var effectiveFrom = document.getElementById('hmse-effective-from').value;
                var total = days.length, done = 0, errors = [];
                days.forEach(function(day, idx) {
                    jQuery.post(HM.ajax_url, {
                        action: 'hm_admin_save_dispenser_schedule',
                        nonce: HM.nonce,
                        id: days.length === 1 ? schedId : '',
                        staff_id: staffId,
                        clinic_id: clinic,
                        day_of_week: day,
                        rotation_weeks: rotation,
                        week_number: week,
                        is_active: isActive,
                        effective_from: effectiveFrom,
                        is_multi_day: days.length > 1 ? 1 : 0,
                        day_index: idx + 1
                    }, function(r) {
                        done++;
                        if (!r.success) errors.push(r.data || 'Error');
                        if (done === total) {
                            if (errors.length) { alert(errors.join('\n')); btn.textContent = 'Save'; btn.disabled = false; }
                            else location.reload();
                        }
                    });
                });
            }
        };
        </script>
        <?php
        return ob_get_clean();
    }

    // ─── MAIN LISTING PAGE ────────────────────────────────────────────
    public function render() {
        if ( ! is_user_logged_in() ) return '<p>Please log in.</p>';

        $rows = $this->get_schedules();
        $staff = $this->get_staff();
        $clinics = $this->get_clinics();
        $days = $this->day_labels();

        // Group schedules by staff_id
        $staff_schedules = [];
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
            $staff_schedules[ $sid ]['schedules'][] = [
                'id' => (int) $r->id,
                'clinic_id' => (int) $r->clinic_id,
                'clinic_name' => $r->clinic_name,
                'day_of_week' => (int) $r->day_of_week,
                'day_label' => $days[ (int) $r->day_of_week ] ?? 'Unknown',
                'rotation_weeks' => (int) $r->rotation_weeks,
                'week_number' => (int) $r->week_number,
                'is_active' => true,
                'effective_from' => $r->effective_from ?? null,
                'effective_to' => $r->effective_to ?? null,
            ];
        }

        $payload = array_values( $staff_schedules );

        // Detail page for a staff member
        $detail_id = isset($_GET['staff']) ? intval($_GET['staff']) : 0;
        if ($detail_id && isset($staff_schedules[$detail_id])) {
            echo $this->render_detail_page($staff_schedules[$detail_id], $clinics, $days);
            return;
        }

        ob_start(); ?>
        <div class="hm-admin" id="hm-schedules-admin">
            <a href="<?php echo esc_url(home_url('/admin-console/')); ?>" class="hm-back">← Back</a>
            <div class="hm-page-header">
                <h1 class="hm-page-title">Dispenser Schedules</h1>
                <div class="hm-page-header__actions">
                    <button class="hm-btn hm-btn--primary" onclick="hmSchedules.open()">+ Add Schedule</button>
                </div>
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
                    $clinic_names = [];
                    foreach ( $staff_data['schedules'] as $schedule ) {
                        $cn = $schedule['clinic_name'];
                        if ( ! in_array( $cn, $clinic_names ) ) $clinic_names[] = $cn;
                    }
                ?>
                    <tr class="hm-disp-row" data-staff="<?php echo (int)$staff_data['staff_id']; ?>">
                        <td><strong><?php echo esc_html( $staff_data['staff_name'] ); ?></strong></td>
                        <td><?php echo esc_html( $staff_data['staff_role'] ?: '—' ); ?></td>
                        <td><?php echo esc_html( implode( ', ', $clinic_names ) ?: '—' ); ?></td>
                        <td><span class="hm-badge hm-badge--green">Active</span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- Add Schedule Modal -->
            <div class="hm-modal-bg" id="hm-schedule-modal">
                <div class="hm-modal hm-modal--lg">
                    <div class="hm-modal-hd">
                        <h3 id="hm-schedule-title">Add Schedule</h3>
                        <button class="hm-close" onclick="hmSchedules.close()">&times;</button>
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
                                <select id="hms-clinic" data-entity="clinic" data-label="Clinic">
                                    <option value="">Select clinic</option>
                                    <?php foreach ( $clinics as $c ): ?>
                                        <option value="<?php echo (int) $c->id; ?>"><?php echo esc_html( $c->clinic_name ); ?></option>
                                    <?php endforeach; ?>
                                    <option value="__add_new__">+ Add New…</option>
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
                                <label>Effective From</label>
                                <input type="date" id="hms-effective-from" style="width:100%;">
                                <div style="font-size:11px;color:#94a3b8;margin-top:2px;">Leave blank for immediate / no start date</div>
                            </div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label class="hm-toggle">
                                    <input type="checkbox" id="hms-active" checked>
                                    <span class="hm-toggle-track"></span>
                                    Active
                                </label>
                            </div>
                        </div>
                        <div class="hm-form-group" style="font-size:12px;color:var(--hm-text-light);">
                            <strong>Select one or more days.</strong> A separate schedule entry will be created for each day selected.
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmSchedules.close()">Cancel</button>
                        <button class="hm-btn hm-btn--primary" onclick="hmSchedules.save()" id="hms-save">Save</button>
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
                if (data) {
                    document.getElementById('hms-staff').value = data.staff_id || '';
                    document.getElementById('hms-clinic').value = data.clinic_id || '';
                    document.getElementById('hms-rotation').value = data.rotation_weeks || '1';
                    document.getElementById('hms-week').value = data.week_number || '1';
                    document.getElementById('hms-active').checked = data.is_active !== false;
                    document.getElementById('hms-effective-from').value = data.effective_from || '';
                    document.querySelectorAll('.hms-day-check').forEach(function(cb) { cb.checked = false; });
                    if (data.day_of_week !== undefined) {
                        var dayCheck = document.querySelector('.hms-day-check[value="'+data.day_of_week+'"]');
                        if (dayCheck) dayCheck.checked = true;
                    }
                } else {
                    document.getElementById('hms-staff').value = '';
                    document.getElementById('hms-clinic').value = '';
                    document.getElementById('hms-rotation').value = '1';
                    document.getElementById('hms-week').value = '1';
                    document.getElementById('hms-active').checked = true;
                    document.getElementById('hms-effective-from').value = '';
                    document.querySelectorAll('.hms-day-check').forEach(function(cb) { cb.checked = false; });
                }
                hmSchedules.toggleWeekRow();
                document.getElementById('hm-schedule-modal').classList.add('open');
            },
            openAdd: function() { hmSchedules.open(); },
            close: function() { document.getElementById('hm-schedule-modal').classList.remove('open'); },
            toggleWeekRow: function() {
                var rot = document.getElementById('hms-rotation').value;
                document.getElementById('hms-week-row').style.display = (rot === '2') ? '' : 'none';
            },
            save: function() {
                var staffId  = document.getElementById('hms-staff').value;
                var clinicId = document.getElementById('hms-clinic').value;
                var rotation = document.getElementById('hms-rotation').value;
                var week     = document.getElementById('hms-week').value;
                var isActive = document.getElementById('hms-active').checked ? 1 : 0;
                var schedId  = document.getElementById('hms-id').value;
                var effectiveFrom = document.getElementById('hms-effective-from').value;

                if (!staffId)  { alert('Please select a staff member.'); return; }
                if (!clinicId) { alert('Please select a clinic.'); return; }

                var days = [];
                document.querySelectorAll('.hms-day-check:checked').forEach(function(cb) { days.push(parseInt(cb.value)); });
                if (!days.length) { alert('Please select at least one day.'); return; }

                var btn = document.getElementById('hms-save');
                btn.textContent = 'Saving...'; btn.disabled = true;
                var total = days.length, done = 0, errors = [];

                days.forEach(function(day, idx) {
                    jQuery.post(HM.ajax_url, {
                        action: 'hm_admin_save_dispenser_schedule',
                        nonce: HM.nonce,
                        id: days.length === 1 ? schedId : '',
                        staff_id: staffId,
                        clinic_id: clinicId,
                        day_of_week: day,
                        rotation_weeks: rotation,
                        week_number: week,
                        is_active: isActive,
                        effective_from: effectiveFrom,
                        is_multi_day: days.length > 1 ? 1 : 0,
                        day_index: idx + 1
                    }, function(r) {
                        done++;
                        if (!r.success) errors.push(r.data || 'Error');
                        if (done === total) {
                            if (errors.length) { alert(errors.join('\n')); btn.textContent = 'Save'; btn.disabled = false; }
                            else location.reload();
                        }
                    });
                });
            }
        };

        document.getElementById('hms-rotation').addEventListener('change', hmSchedules.toggleWeekRow);

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.hm-disp-row').forEach(function(row) {
                row.style.cursor = 'pointer';
                row.addEventListener('mouseenter', function() { row.style.background = '#f0f9ff'; });
                row.addEventListener('mouseleave', function() { row.style.background = ''; });
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

    // ─── AJAX: Save ────────────────────────────────────────────────
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
        $effective_from = ! empty( $_POST['effective_from'] ) ? sanitize_text_field( $_POST['effective_from'] ) : null;

        if ( ! $staff_id || ! $clinic_id || $day < 0 || $day > 6 ) {
            wp_send_json_error( 'Missing fields' );
            return;
        }

        if ( ! in_array( $rotation, [ 1, 2, 3, 4 ] ) ) $rotation = 1;
        if ( $rotation !== 2 ) { $week = 1; } else { $week = $week === 2 ? 2 : 1; }

        // Multi-day save: delete old entries for this staff/clinic on first day
        if ( $is_multi_day && $day_index === 1 && $id ) {
            HearMed_DB::get_results(
                "DELETE FROM hearmed_reference.dispenser_schedules WHERE staff_id = $1 AND clinic_id = $2",
                [ $staff_id, $clinic_id ]
            );
            $id = 0;
        }

        $data = [
            'staff_id' => $staff_id,
            'clinic_id' => $clinic_id,
            'day_of_week' => $day,
            'rotation_weeks' => $rotation,
            'week_number' => $week,
            'is_active' => $is_active,
            'effective_from' => $effective_from,
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

    // ─── AJAX: Delete (single — sets effective_to) ────────────────
    public function ajax_delete() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }

        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) { wp_send_json_error( 'Invalid ID' ); return; }

        $effective_to = ! empty( $_POST['effective_to'] ) ? sanitize_text_field( $_POST['effective_to'] ) : date( 'Y-m-d' );

        $result = HearMed_DB::update(
            'hearmed_reference.dispenser_schedules',
            [
                'is_active' => false,
                'effective_to' => $effective_to,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $id ]
        );

        if ( $result === false ) {
            wp_send_json_error( HearMed_DB::last_error() ?: 'Database error' );
        } else {
            wp_send_json_success();
        }
    }

    // ─── AJAX: Bulk delete ────────────────────────────────────────
    public function ajax_bulk_delete() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }

        $ids_raw = sanitize_text_field( $_POST['ids'] ?? '' );
        $effective_to = ! empty( $_POST['effective_to'] ) ? sanitize_text_field( $_POST['effective_to'] ) : date( 'Y-m-d' );

        if ( ! $ids_raw ) { wp_send_json_error( 'No IDs provided' ); return; }

        $ids = array_filter( array_map( 'intval', explode( ',', $ids_raw ) ) );
        if ( empty( $ids ) ) { wp_send_json_error( 'Invalid IDs' ); return; }

        $placeholders = [];
        $params = [ $effective_to, current_time( 'mysql' ) ];
        foreach ( $ids as $i => $id ) {
            $placeholders[] = '$' . ( $i + 3 );
            $params[] = $id;
        }

        $sql = "UPDATE hearmed_reference.dispenser_schedules
                SET is_active = false, effective_to = $1, updated_at = $2
                WHERE id IN (" . implode( ',', $placeholders ) . ")";

        $result = HearMed_DB::get_results( $sql, $params );

        wp_send_json_success( [ 'count' => count( $ids ) ] );
    }
}

new HearMed_Admin_Dispenser_Schedules();
