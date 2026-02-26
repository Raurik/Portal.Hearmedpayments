<?php
/**
 * HearMed Admin — SMS Templates
 * Shortcode: [hearmed_sms_templates]
 * CRUD for hearmed_communication.sms_templates
 *
 * Supports placeholders: {patient_name}, {appointment_date}, {appointment_time},
 * {clinic_name}, {dispenser_name}, {patient_number}, {service_name}
 *
 * @package HearMed_Portal
 * @since   5.0.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_SMS_Templates {

    private $placeholders = [
        '{patient_name}'      => 'Patient full name',
        '{first_name}'        => 'Patient first name',
        '{appointment_date}'  => 'Appointment date (e.g. 24 Feb 2026)',
        '{appointment_time}'  => 'Appointment time (e.g. 10:30)',
        '{clinic_name}'       => 'Clinic name',
        '{clinic_phone}'      => 'Clinic phone number',
        '{dispenser_name}'    => 'Dispenser/audiologist name',
        '{patient_number}'    => 'Patient H-number',
        '{service_name}'      => 'Service / appointment type',
    ];

    private $categories = [
        'appointment_reminder'  => 'Appointment Reminder',
        'appointment_confirm'   => 'Appointment Confirmation',
        'appointment_cancel'    => 'Appointment Cancellation',
        'appointment_reschedule'=> 'Reschedule Notice',
        'order_ready'           => 'Order Ready for Collection',
        'order_update'          => 'Order Status Update',
        'recall'                => 'Recall / Follow-up',
        'birthday'              => 'Birthday Greeting',
        'general'               => 'General',
    ];

    public function __construct() {
        add_shortcode('hearmed_sms_templates', [$this, 'render']);
        add_action('wp_ajax_hm_admin_save_sms_template', [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_sms_template', [$this, 'ajax_delete']);
    }

    private function get_templates() {
        $t = HearMed_DB::table('sms_templates');
        $check = HearMed_DB::get_var( HearMed_DB::prepare( "SELECT to_regclass(%s)", $t ) );
        if ($check === null) return [];
        return HearMed_DB::get_results("SELECT * FROM {$t} WHERE is_active = true ORDER BY category, template_name") ?: [];
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $templates = $this->get_templates();

        ob_start(); ?>
        <div class="hm-admin">
            <div style="margin-bottom:16px;"><a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-btn">&larr; Back</a></div>
            <div class="hm-admin-hd">
                <h2>SMS Templates</h2>
                <button class="hm-btn hm-btn--primary" onclick="hmSms.open()">+ Add Template</button>
            </div>

            <div class="hm-sms-vars" style="margin-bottom:20px;">
                <p style="font-size:13px;color:var(--hm-text-light);margin-bottom:8px;"><strong>Available Placeholders</strong> — click to copy:</p>
                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                    <?php foreach ($this->placeholders as $tag => $desc): ?>
                        <span class="hm-badge hm-badge--blue" style="cursor:pointer;" onclick="navigator.clipboard.writeText('<?php echo esc_js($tag); ?>');this.textContent='Copied!';var el=this;setTimeout(function(){el.textContent='<?php echo esc_js($tag); ?>';},1000);" title="<?php echo esc_attr($desc); ?>"><?php echo esc_html($tag); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (empty($templates)): ?>
                <div class="hm-empty-state"><p>No SMS templates yet. Click "+ Add Template" to get started.</p></div>
            <?php else: ?>
            <table class="hm-table">
                <thead>
                    <tr>
                        <th>Template Name</th>
                        <th>Category</th>
                        <th>Preview</th>
                        <th>Status</th>
                        <th style="width:100px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($templates as $t):
                    $row     = (array) $t;
                    $payload = json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                    $cat_label = $this->categories[$t->category] ?? ucfirst($t->category ?: 'General');
                    $preview = mb_substr($t->template_content, 0, 80);
                    if (mb_strlen($t->template_content) > 80) $preview .= '…';
                ?>
                    <tr>
                        <td><strong><?php echo esc_html($t->template_name); ?></strong></td>
                        <td><span class="hm-badge hm-badge--blue"><?php echo esc_html($cat_label); ?></span></td>
                        <td style="font-size:13px;color:var(--hm-text-light);"><?php echo esc_html($preview); ?></td>
                        <td><?php echo $t->is_active ? '<span class="hm-badge hm-badge--green">Active</span>' : '<span class="hm-badge hm-badge--red">Inactive</span>'; ?></td>
                        <td class="hm-table-acts">
                            <button class="hm-btn hm-btn--sm" onclick='hmSms.open(<?php echo $payload; ?>)'>Edit</button>
                            <button class="hm-btn hm-btn--sm hm-btn--danger" onclick="hmSms.del(<?php echo (int) $t->id; ?>,'<?php echo esc_js($t->template_name); ?>')">Delete</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <!-- Modal -->
            <div class="hm-modal-bg" id="hm-sms-modal">
                <div class="hm-modal hm-modal--lg">
                    <div class="hm-modal-hd">
                        <h3 id="hm-sms-modal-title">Add SMS Template</h3>
                        <button class="hm-modal-x" onclick="hmSms.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body">
                        <input type="hidden" id="hmsms-id">

                        <div class="hm-form-row">
                            <div class="hm-form-group" style="flex:2">
                                <label>Template Name *</label>
                                <input type="text" id="hmsms-name" placeholder="e.g. Appointment Reminder - 24hr">
                            </div>
                            <div class="hm-form-group" style="flex:1">
                                <label>Category</label>
                                <select id="hmsms-category" data-entity="sms_category" data-label="Category">
                                    <?php foreach ($this->categories as $val => $label): ?>
                                        <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                    <?php foreach (hm_get_dropdown_options('sms_category') as $custom): ?>
                                        <?php if (!array_key_exists($custom, $this->categories)): ?>
                                        <option value="<?php echo esc_attr($custom); ?>"><?php echo esc_html(ucfirst($custom)); ?></option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <option value="__add_new__">+ Add New…</option>
                                </select>
                            </div>
                        </div>

                        <div class="hm-form-group">
                            <label>Message Content *</label>
                            <textarea id="hmsms-content" rows="5" placeholder="Hi {first_name}, this is a reminder of your appointment at {clinic_name} on {appointment_date} at {appointment_time}. Reply STOP to opt out." style="font-family:inherit;"></textarea>
                            <div style="display:flex;justify-content:space-between;margin-top:4px;">
                                <span style="font-size:11px;color:var(--hm-text-light);">Use placeholders above for dynamic content.</span>
                                <span id="hmsms-chars" style="font-size:11px;color:var(--hm-text-light);">0 / 160 chars</span>
                            </div>
                        </div>

                        <div class="hm-form-group">
                            <label class="hm-toggle-label">
                                <input type="checkbox" id="hmsms-active" checked>
                                Active
                            </label>
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmSms.close()">Cancel</button>
                        <button class="hm-btn hm-btn--primary" onclick="hmSms.save()" id="hmsms-save-btn">Save</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        var hmSms = {
            open: function(data) {
                var isEdit = !!(data && data.id);
                document.getElementById('hm-sms-modal-title').textContent = isEdit ? 'Edit SMS Template' : 'Add SMS Template';
                document.getElementById('hmsms-id').value       = isEdit ? data.id : '';
                document.getElementById('hmsms-name').value     = data && data.template_name ? data.template_name : '';
                document.getElementById('hmsms-category').value = data && data.category ? data.category : 'general';
                document.getElementById('hmsms-content').value  = data && data.template_content ? data.template_content : '';
                document.getElementById('hmsms-active').checked = data ? !!data.is_active : true;
                hmSms.updateChars();
                document.getElementById('hm-sms-modal').classList.add('open');
                document.getElementById('hmsms-name').focus();
            },
            close: function() { document.getElementById('hm-sms-modal').classList.remove('open'); },
            updateChars: function() {
                var len = (document.getElementById('hmsms-content').value || '').length;
                var parts = Math.ceil(len / 160) || 1;
                var label = len + ' / 160 chars';
                if (len > 160) label += ' (' + parts + ' SMS parts)';
                document.getElementById('hmsms-chars').textContent = label;
                document.getElementById('hmsms-chars').style.color = len > 160 ? 'var(--hm-red, #e74c3c)' : 'var(--hm-text-light)';
            },
            save: function() {
                var name    = document.getElementById('hmsms-name').value.trim();
                var content = document.getElementById('hmsms-content').value.trim();
                if (!name)    { alert('Template name is required.'); return; }
                if (!content) { alert('Message content is required.'); return; }

                var btn = document.getElementById('hmsms-save-btn');
                btn.textContent = 'Saving...'; btn.disabled = true;

                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_save_sms_template',
                    nonce: HM.nonce,
                    id: document.getElementById('hmsms-id').value,
                    template_name: name,
                    category: document.getElementById('hmsms-category').value,
                    template_content: content,
                    is_active: document.getElementById('hmsms-active').checked ? 1 : 0
                }, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error'); btn.textContent = 'Save'; btn.disabled = false; }
                });
            },
            del: function(id, name) {
                if (!confirm('Delete template "' + name + '"?')) return;
                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_delete_sms_template',
                    nonce: HM.nonce,
                    id: id
                }, function(r) {
                    if (r.success) location.reload();
                    else alert(r.data || 'Error');
                });
            }
        };

        // Live char counter
        document.getElementById('hmsms-content').addEventListener('input', hmSms.updateChars);
        </script>
        <?php return ob_get_clean();
    }

    public function ajax_save() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        $id       = intval($_POST['id'] ?? 0);
        $name     = sanitize_text_field($_POST['template_name'] ?? '');
        $content  = sanitize_textarea_field($_POST['template_content'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? 'general');

        if (empty($name) || empty($content)) {
            wp_send_json_error('Template name and content are required');
            return;
        }

        $table = HearMed_DB::table('sms_templates');

        $data = [
            'template_name'    => $name,
            'template_content' => $content,
            'category'         => $category,
            'is_active'        => intval($_POST['is_active'] ?? 1),
            'updated_at'       => current_time('mysql'),
        ];

        if ($id) {
            $result = HearMed_DB::update($table, $data, ['id' => $id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $result = HearMed_DB::insert($table, $data);
            $id = $result ?: 0;
        }

        if ($result === false) {
            wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
        } else {
            wp_send_json_success(['id' => $id]);
        }
    }

    public function ajax_delete() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        $id = intval($_POST['id'] ?? 0);
        if (!$id) { wp_send_json_error('Invalid ID'); return; }

        // Soft delete
        $result = HearMed_DB::update(
            HearMed_DB::table('sms_templates'),
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

new HearMed_Admin_SMS_Templates();
