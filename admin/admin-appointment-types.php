<?php
/**
 * HearMed Admin — Appointment Types
 * Shortcode: [hearmed_appointment_types]
 * Manages appointment type / service definitions stored in hearmed_reference.services.
 *
 * @package HearMed_Portal
 * @since   5.1.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Appointment_Types {

    private $table = 'hearmed_reference.services';

    public function __construct() {
        add_shortcode('hearmed_appointment_types', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_appointment_type',   [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_appointment_type', [$this, 'ajax_delete']);
    }

    /**
     * Fetch all appointment types / services from PostgreSQL
     */
    private function get_types() {
        $check = HearMed_DB::get_var("SELECT to_regclass('{$this->table}')");
        if ($check === null) return [];
        return HearMed_DB::get_results(
            "SELECT * FROM {$this->table} WHERE is_active = true ORDER BY service_name"
        ) ?: [];
    }

    /**
     * Render the appointment types admin page
     */
    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $types = $this->get_types();

        ob_start(); ?>
        <div class="hm-admin">
            <a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-back">← Back</a>
            <div class="hm-page-header">
                <h1 class="hm-page-title">Appointment Types</h1>
                <div class="hm-page-header__actions">
                    <button class="hm-btn hm-btn--primary" onclick="hmApptType.open()">+ Add Type</button>
                </div>
            </div>

            <p style="color:var(--hm-text-light);font-size:13px;margin-bottom:20px;">
                Define the appointment types available in the calendar. Each type has a colour, duration, and category settings.
            </p>

            <?php if (empty($types)): ?>
                <div class="hm-empty-state"><p>No appointment types defined yet.</p></div>
            <?php else: ?>
            <table class="hm-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Preview</th>
                        <th>Duration</th>
                        <th>Category</th>
                        <th>Sales Opp.</th>
                        <th>Income Bearing</th>
                        <th style="width:100px"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($types as $t):
                        $row = json_encode((array) $t, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                        $colour = $t->service_color ?? '#3B82F6';
                        $text_colour = $t->text_color ?? '#FFFFFF';
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <span style="width:12px;height:12px;border-radius:3px;background:<?php echo esc_attr($colour); ?>;display:inline-block;flex-shrink:0;"></span>
                                <strong><?php echo esc_html($t->service_name); ?></strong>
                            </div>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:6px;">
                                <span style="display:inline-block;width:40px;height:20px;border-radius:3px;background:<?php echo esc_attr($colour); ?>;color:<?php echo esc_attr($text_colour); ?>;font-size:9px;line-height:20px;text-align:center;font-weight:600;">Abc</span>
                            </div>
                        </td>
                        <td><?php echo intval($t->duration_minutes ?? 30); ?> min</td>
                        <td><?php echo esc_html($t->appointment_category ?? '—'); ?></td>
                        <td>
                            <?php if (!empty($t->sales_opportunity) && $t->sales_opportunity): ?>
                                <span style="color:var(--hm-teal);font-weight:600;">Yes</span>
                            <?php else: ?>
                                No
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($t->income_bearing) && $t->income_bearing): ?>
                                <span style="color:var(--hm-teal);font-weight:600;">Yes</span>
                            <?php else: ?>
                                No
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;align-items:center;">
                                <a href="<?php echo esc_url(home_url('/appointment-type-detail/?id=' . (int)$t->id)); ?>" class="hm-btn hm-btn--sm" title="View Details" style="padding:4px 8px;font-size:14px;">👁</a>
                                <button class="hm-btn hm-btn--sm" onclick='hmApptType.open(<?php echo $row; ?>)'>Edit</button>
                                <button class="hm-btn hm-btn--sm hm-btn--danger" onclick="hmApptType.del(<?php echo (int)$t->id; ?>,'<?php echo esc_js($t->service_name); ?>')">Delete</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- Modal -->
            <div class="hm-modal-bg" id="hm-appt-modal">
                <div class="hm-modal hm-modal--md">
                    <div class="hm-modal-hd">
                        <h3 id="hm-appt-title">Add Appointment Type</h3>
                        <button class="hm-close" onclick="hmApptType.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hma-id">
                        <div class="hm-form-row">
                            <div class="hm-form-group" style="flex:2">
                                <label>Name *</label>
                                <input type="text" class="hm-inp" id="hma-name" placeholder="e.g. Hearing Test">
                            </div>
                            <div class="hm-form-group" style="flex:1">
                                <label>Block Colour</label>
                                <input type="color" id="hma-colour" value="#3B82F6" class="hm-color-box" style="width:100%;height:38px;">
                            </div>
                            <div class="hm-form-group" style="flex:1">
                                <label>Text Colour</label>
                                <input type="color" id="hma-text-colour" value="#FFFFFF" class="hm-color-box" style="width:100%;height:38px;">
                            </div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label>Duration (minutes)</label>
                                <input type="number" class="hm-inp" id="hma-duration" value="30" min="5" step="5">
                            </div>
                            <div class="hm-form-group">
                                <label>Category</label>
                                <?php $cat_defaults = ['consultation'=>'Consultation','service'=>'Service','review'=>'Review','diagnostic'=>'Diagnostic','fitting'=>'Fitting','repair'=>'Repair']; ?>
                                <select class="hm-inp" id="hma-category" data-entity="appt_category" data-label="Category">
                                    <option value="">— None —</option>
                                    <?php foreach ($cat_defaults as $ck => $cv): ?>
                                        <option value="<?php echo esc_attr($ck); ?>"><?php echo esc_html($cv); ?></option>
                                    <?php endforeach; ?>
                                    <?php foreach (hm_get_dropdown_options('appt_category') as $custom): ?>
                                        <?php if (!array_key_exists($custom, $cat_defaults)): ?>
                                        <option value="<?php echo esc_attr($custom); ?>"><?php echo esc_html(ucfirst($custom)); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <option value="__add_new__">+ Add New…</option>
                                </select>
                            </div>
                        </div>
                        <div class="hm-form-row">
                            <div class="hm-form-group">
                                <label class="hm-toggle">
                                    <input type="checkbox" id="hma-sales">
                                    <span class="hm-toggle-track"></span>
                                    Sales opportunity
                                </label>
                            </div>
                            <div class="hm-form-group">
                                <label class="hm-toggle">
                                    <input type="checkbox" id="hma-income" checked>
                                    <span class="hm-toggle-track"></span>
                                    Income bearing
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmApptType.close()">Cancel</button>
                        <button class="hm-btn hm-btn--primary" onclick="hmApptType.save()" id="hma-save">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        var hmApptType = {
            open: function(data) {
                var isEdit = !!(data && data.id);
                document.getElementById('hm-appt-title').textContent = isEdit ? 'Edit Appointment Type' : 'Add Appointment Type';
                document.getElementById('hma-id').value       = isEdit ? data.id : '';
                document.getElementById('hma-name').value     = isEdit ? (data.service_name || '') : '';
                document.getElementById('hma-colour').value   = isEdit ? (data.service_color || '#3B82F6') : '#3B82F6';
                document.getElementById('hma-text-colour').value = isEdit ? (data.text_color || '#FFFFFF') : '#FFFFFF';
                document.getElementById('hma-duration').value = isEdit ? (data.duration_minutes || 30) : 30;
                document.getElementById('hma-category').value = isEdit ? (data.appointment_category || '') : '';
                document.getElementById('hma-sales').checked  = isEdit ? !!data.sales_opportunity : false;
                document.getElementById('hma-income').checked = isEdit ? (data.income_bearing !== false && data.income_bearing !== 'f') : true;
                document.getElementById('hm-appt-modal').classList.add('open');
                document.getElementById('hma-name').focus();
            },
            close: function() {
                document.getElementById('hm-appt-modal').classList.remove('open');
            },
            save: function() {
                var name = document.getElementById('hma-name').value.trim();
                if (!name) { alert('Name is required.'); return; }

                var btn = document.getElementById('hma-save');
                btn.textContent = 'Saving...'; btn.disabled = true;

                jQuery.post(HM.ajax_url, {
                    action:             'hm_admin_save_appointment_type',
                    nonce:              HM.nonce,
                    id:                 document.getElementById('hma-id').value,
                    service_name:       name,
                    colour:             document.getElementById('hma-colour').value,
                    text_color:         document.getElementById('hma-text-colour').value,
                    duration:           document.getElementById('hma-duration').value,
                    appointment_category: document.getElementById('hma-category').value,
                    sales_opportunity:  document.getElementById('hma-sales').checked ? 1 : 0,
                    income_bearing:     document.getElementById('hma-income').checked ? 1 : 0
                }, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error saving appointment type'); btn.textContent = 'Save'; btn.disabled = false; }
                });
            },
            del: function(id, name) {
                if (!confirm('Delete appointment type "' + name + '"?')) return;
                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_delete_appointment_type',
                    nonce:  HM.nonce,
                    id:     id
                }, function(r) {
                    if (r.success) location.reload();
                    else alert(r.data || 'Error deleting appointment type');
                });
            }
        };
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX: Save (insert or update) an appointment type
     */
    public function ajax_save() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }

        $id   = intval($_POST['id'] ?? 0);
        $name = sanitize_text_field($_POST['service_name'] ?? '');
        if (!$name) { wp_send_json_error('Name is required'); return; }

        $colour = sanitize_hex_color($_POST['colour'] ?? '#3B82F6') ?: '#3B82F6';
        $dur    = intval($_POST['duration'] ?? 30);

        $data = [
            'service_name'        => $name,
            'service_color'       => $colour,
            'colour'              => $colour,
            'text_color'          => sanitize_hex_color($_POST['text_color'] ?? '#FFFFFF') ?: '#FFFFFF',
            'duration_minutes'    => $dur,
            'duration'            => $dur,
            'appointment_category'=> sanitize_text_field($_POST['appointment_category'] ?? ''),
            'sales_opportunity'   => !empty($_POST['sales_opportunity']),
            'income_bearing'      => !empty($_POST['income_bearing']),
            'is_active'           => true,
            'updated_at'          => current_time('mysql'),
        ];

        if ($id) {
            $result = HearMed_DB::update($this->table, $data, ['id' => $id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $id = HearMed_DB::insert($this->table, $data);
            $result = $id ? 1 : false;
        }

        if ($result === false) {
            wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
        } else {
            wp_send_json_success(['id' => $id]);
        }
    }

    /**
     * AJAX: Soft-delete an appointment type (set is_active = false)
     */
    public function ajax_delete() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }

        $id = intval($_POST['id'] ?? 0);
        if (!$id) { wp_send_json_error('Invalid ID'); return; }

        $result = HearMed_DB::update(
            $this->table,
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

new HearMed_Admin_Appointment_Types();
