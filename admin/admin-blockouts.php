<?php
/**
 * HearMed Admin — Calendar Blockouts
 * Shortcode: [hearmed_admin_blockouts]
 * Time-based appointment type restrictions in the calendar.
 * e.g. "4-5pm fittings only", "No new patients after 3pm".
 * Default = no blockouts (everything allowed).
 * Uses hearmed_core.calendar_blockouts table.
 *
 * @package HearMed_Portal
 * @since   5.1.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Blockouts {

    private static $days = [
        'monday'    => 'Monday',
        'tuesday'   => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday'  => 'Thursday',
        'friday'    => 'Friday',
        'saturday'  => 'Saturday',
        'sunday'    => 'Sunday',
    ];

    public function __construct() {
        add_shortcode('hearmed_admin_blockouts', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_blockout', [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_blockout', [$this, 'ajax_delete']);
    }

    private function get_appointment_types() {
        return HearMed_DB::get_results(
            "SELECT id, type_name, color FROM hearmed_reference.appointment_types WHERE is_active = true ORDER BY type_name"
        ) ?: [];
    }

    private function get_clinics() {
        return HearMed_DB::get_results(
            "SELECT id, clinic_name FROM hearmed_reference.clinics WHERE is_active = true ORDER BY clinic_name"
        ) ?: [];
    }

    private function get_blockouts() {
        $t = HearMed_DB::table('calendar_blockouts');
        $check = HearMed_DB::get_var("SELECT to_regclass('{$t}')");
        if ($check === null) return [];
        return HearMed_DB::get_results(
            "SELECT b.*, c.clinic_name, at.type_name
             FROM {$t} b
             LEFT JOIN hearmed_reference.clinics c ON b.clinic_id = c.id
             LEFT JOIN hearmed_reference.appointment_types at ON b.appointment_type_id = at.id
             WHERE b.is_active = true
             ORDER BY b.day_of_week, b.start_time"
        ) ?: [];
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $blockouts = $this->get_blockouts();
        $appt_types = $this->get_appointment_types();
        $clinics = $this->get_clinics();

        ob_start(); ?>
        <div class="hm-admin">
            <a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-back">← Back</a>
            <div class="hm-page-header">
                <h1 class="hm-page-title">Calendar Blockouts</h1>
                <div class="hm-page-header__actions">
                    <button class="hm-btn hm-btn--primary" onclick="hmBlock.open()">+ Add Blockout Rule</button>
                </div>
            </div>

            <p style="color:var(--hm-text-light);font-size:13px;margin-bottom:20px;">
                Restrict appointment types to specific time windows. By default, all appointment types are allowed at all times.
                Add rules to limit when certain types can be booked. E.g. "Fittings only between 4-5pm on Mondays".
            </p>

            <?php if (empty($blockouts)): ?>
                <div class="hm-empty-state">
                    <p>No blockout rules. All appointment types are available at all times.</p>
                </div>
            <?php else: ?>
            <table class="hm-table">
                <thead>
                    <tr>
                        <th>Rule Name</th>
                        <th>Day</th>
                        <th>Time Window</th>
                        <th>Appointment Type</th>
                        <th>Clinic</th>
                        <th>Mode</th>
                        <th>Status</th>
                        <th style="width:100px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($blockouts as $b):
                    $row = json_encode((array) $b, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($b->rule_name ?: '—'); ?></strong></td>
                        <td><?php echo esc_html(ucfirst($b->day_of_week ?: 'All')); ?></td>
                        <td>
                            <span class="hm-badge hm-badge--blue">
                                <?php echo esc_html(($b->start_time ?: '??') . ' – ' . ($b->end_time ?: '??')); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($b->type_name ?: 'All Types'); ?></td>
                        <td><?php echo esc_html($b->clinic_name ?: 'All Clinics'); ?></td>
                        <td>
                            <?php if ($b->block_mode === 'only'): ?>
                                <span class="hm-badge hm-badge--green">Only Allow</span>
                            <?php else: ?>
                                <span class="hm-badge hm-badge--red">Block</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $b->is_active ? '<span class="hm-badge hm-badge--green">Active</span>' : '<span class="hm-badge hm-badge--red">Off</span>'; ?></td>
                        <td class="hm-table-acts">
                            <button class="hm-btn hm-btn--sm" onclick='hmBlock.open(<?php echo $row; ?>)'>Edit</button>
                            <button class="hm-btn hm-btn--sm hm-btn--danger" onclick="hmBlock.del(<?php echo (int) $b->id; ?>,'<?php echo esc_js($b->rule_name ?: 'this rule'); ?>')">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- Modal -->
            <div class="hm-modal-bg" id="hm-block-modal">
                <div class="hm-modal hm-modal--lg">
                    <div class="hm-modal-hd">
                        <h3 id="hm-block-title">Add Blockout Rule</h3>
                        <button class="hm-close" onclick="hmBlock.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hmb-id">
                        <div class="hm-form-group">
                            <label>Rule Name *</label>
                            <input type="text" id="hmb-name" placeholder="e.g. Fittings Only 4-5pm Monday">
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Day of Week</label>
                                <select id="hmb-day">
                                    <option value="">All Days</option>
                                    <?php foreach (self::$days as $k => $v): ?>
                                        <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="hm-form-group">
                                <label>Mode *</label>
                                <select id="hmb-mode">
                                    <option value="only">Only Allow (this type in this window)</option>
                                    <option value="block">Block (prevent this type in this window)</option>
                                </select>
                            </div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Start Time *</label>
                                <input type="time" id="hmb-start" value="16:00">
                            </div>
                            <div class="hm-form-group">
                                <label>End Time *</label>
                                <input type="time" id="hmb-end" value="17:00">
                            </div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Appointment Type</label>
                                <select id="hmb-appt-type" data-entity="appointment_type" data-label="Appointment Type">
                                    <option value="">All Types</option>
                                    <?php foreach ($appt_types as $at): ?>
                                        <option value="<?php echo (int) $at->id; ?>"><?php echo esc_html($at->type_name); ?></option>
                                    <?php endforeach; ?>
                                    <option value="__add_new__">+ Add New…</option>
                                </select>
                            </div>
                            <div class="hm-form-group">
                                <label>Clinic</label>
                                <select id="hmb-clinic" data-entity="clinic" data-label="Clinic">
                                    <option value="">All Clinics</option>
                                    <?php foreach ($clinics as $c): ?>
                                        <option value="<?php echo (int) $c->id; ?>"><?php echo esc_html($c->clinic_name); ?></option>
                                    <?php endforeach; ?>
                                    <option value="__add_new__">+ Add New…</option>
                                </select>
                            </div>
                        </div>
                        <div class="hm-form-group">
                            <label class="hm-toggle">
                                <input type="checkbox" id="hmb-active" checked>
                                <span class="hm-toggle-track"></span>
                                Active
                            </label>
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmBlock.close()">Cancel</button>
                        <button class="hm-btn hm-btn--primary" onclick="hmBlock.save()" id="hmb-save">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        var hmBlock = {
            open: function(data) {
                var isEdit = !!(data && data.id);
                document.getElementById('hm-block-title').textContent = isEdit ? 'Edit Blockout Rule' : 'Add Blockout Rule';
                document.getElementById('hmb-id').value        = isEdit ? data.id : '';
                document.getElementById('hmb-name').value      = isEdit ? (data.rule_name || '') : '';
                document.getElementById('hmb-day').value       = isEdit ? (data.day_of_week || '') : '';
                document.getElementById('hmb-mode').value      = isEdit ? (data.block_mode || 'only') : 'only';
                document.getElementById('hmb-start').value     = isEdit ? (data.start_time || '16:00') : '16:00';
                document.getElementById('hmb-end').value       = isEdit ? (data.end_time || '17:00') : '17:00';
                document.getElementById('hmb-appt-type').value = isEdit ? (data.appointment_type_id || '') : '';
                document.getElementById('hmb-clinic').value    = isEdit ? (data.clinic_id || '') : '';
                document.getElementById('hmb-active').checked  = isEdit ? !!data.is_active : true;
                document.getElementById('hm-block-modal').classList.add('open');
                document.getElementById('hmb-name').focus();
            },
            close: function() { document.getElementById('hm-block-modal').classList.remove('open'); },
            save: function() {
                var name = document.getElementById('hmb-name').value.trim();
                if (!name) { alert('Rule name is required.'); return; }

                var btn = document.getElementById('hmb-save');
                btn.textContent = 'Saving...'; btn.disabled = true;

                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_save_blockout',
                    nonce: HM.nonce,
                    id: document.getElementById('hmb-id').value,
                    rule_name: name,
                    day_of_week: document.getElementById('hmb-day').value,
                    block_mode: document.getElementById('hmb-mode').value,
                    start_time: document.getElementById('hmb-start').value,
                    end_time: document.getElementById('hmb-end').value,
                    appointment_type_id: document.getElementById('hmb-appt-type').value,
                    clinic_id: document.getElementById('hmb-clinic').value,
                    is_active: document.getElementById('hmb-active').checked ? 1 : 0
                }, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error'); btn.textContent = 'Save'; btn.disabled = false; }
                });
            },
            del: function(id, name) {
                if (!confirm('Delete "' + name + '"?')) return;
                jQuery.post(HM.ajax_url, { action:'hm_admin_delete_blockout', nonce:HM.nonce, id:id }, function(r) {
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

        $id   = intval($_POST['id'] ?? 0);
        $name = sanitize_text_field($_POST['rule_name'] ?? '');
        if (!$name) { wp_send_json_error('Rule name required'); return; }

        $data = [
            'rule_name'           => $name,
            'day_of_week'         => sanitize_text_field($_POST['day_of_week'] ?? '') ?: null,
            'block_mode'          => sanitize_text_field($_POST['block_mode'] ?? 'only'),
            'start_time'          => sanitize_text_field($_POST['start_time'] ?? ''),
            'end_time'            => sanitize_text_field($_POST['end_time'] ?? ''),
            'appointment_type_id' => !empty($_POST['appointment_type_id']) ? intval($_POST['appointment_type_id']) : null,
            'clinic_id'           => !empty($_POST['clinic_id']) ? intval($_POST['clinic_id']) : null,
            'is_active'           => intval($_POST['is_active'] ?? 1),
            'updated_at'          => current_time('mysql'),
        ];

        $table = HearMed_DB::table('calendar_blockouts');

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

        $table = HearMed_DB::table('calendar_blockouts');
        $result = HearMed_DB::update($table, ['is_active' => false, 'updated_at' => current_time('mysql')], ['id' => $id]);

        if ($result === false) {
            wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
        } else {
            wp_send_json_success();
        }
    }
}

new HearMed_Admin_Blockouts();
