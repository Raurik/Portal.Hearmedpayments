<?php
/**
 * HearMed Admin — Settings Pages
 * Multiple simple config shortcodes that store options in wp_options
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Settings {

    private $pg_settings_cache = null;

    private $pages = [
        'hearmed_finance_settings' => [
            'title' => 'Finance Settings',
            'fields' => [
                ['key' => 'hm_vat_hearing_aids', 'label' => 'VAT Rate — Hearing Aids (%)', 'type' => 'number', 'default' => '0'],
                ['key' => 'hm_vat_accessories', 'label' => 'VAT Rate — Accessories (%)', 'type' => 'number', 'default' => '0'],
                ['key' => 'hm_vat_services', 'label' => 'VAT Rate — Services (%)', 'type' => 'number', 'default' => '13.5'],
                ['key' => 'hm_vat_consumables', 'label' => 'VAT Rate — Consumables (%)', 'type' => 'number', 'default' => '23'],
                ['key' => 'hm_vat_other_aud', 'label' => 'VAT Rate — Other Audiological (%)', 'type' => 'number', 'default' => '13.5'],
                ['key' => 'hm_payment_methods', 'label' => 'Payment Methods (comma-separated)', 'type' => 'text', 'default' => 'Card,Cash,Cheque,Bank Transfer,Finance,PRSI'],
                ['key' => 'hm_prsi_amount_per_ear', 'label' => 'PRSI Amount Per Ear (€)', 'type' => 'number', 'default' => '500'],
                ['key' => 'hm_invoice_prefix', 'label' => 'Invoice Number Prefix', 'type' => 'text', 'default' => 'INV-'],
                ['key' => 'hm_order_prefix', 'label' => 'Order Number Prefix', 'type' => 'text', 'default' => 'ORD-'],
                ['key' => 'hm_credit_note_prefix', 'label' => 'Credit Note Prefix', 'type' => 'text', 'default' => 'CN-'],
            ],
        ],
        'hearmed_comms_settings' => [
            'title' => 'Communication Settings',
            'fields' => [
                ['key' => 'hm_sms_enabled', 'label' => 'SMS Enabled', 'type' => 'toggle', 'default' => '0'],
                ['key' => 'hm_sms_provider', 'label' => 'SMS Provider', 'type' => 'select', 'options' => ['twilio' => 'Twilio', 'bulksms' => 'BulkSMS.ie'], 'default' => 'twilio'],
                ['key' => 'hm_twilio_sid', 'label' => 'Twilio Account SID', 'type' => 'text', 'default' => ''],
                ['key' => 'hm_twilio_token', 'label' => 'Twilio Auth Token', 'type' => 'password', 'default' => ''],
                ['key' => 'hm_twilio_from', 'label' => 'Twilio From Number', 'type' => 'text', 'default' => ''],
                ['key' => 'hm_email_enabled', 'label' => 'Email Reminders Enabled', 'type' => 'toggle', 'default' => '0'],
                ['key' => 'hm_email_from_name', 'label' => 'Email From Name', 'type' => 'text', 'default' => 'HearMed'],
                ['key' => 'hm_email_from_address', 'label' => 'Email From Address', 'type' => 'text', 'default' => ''],
            ],
        ],
        'hearmed_document_types' => [
            'title' => 'Document Types',
            'fields' => [
                ['key' => 'hm_document_types', 'label' => 'Document Types (one per line)', 'type' => 'textarea', 'default' => "Case History\nSales Order\nConsent Form\nAudiogram\nGP Referral\nHearing Test\nRepair Form\nFitting Receipt\nPhone Call Log\nENT Referral\nOther"],
            ],
        ],
        'hearmed_form_settings' => [
            'title' => 'Form & Input Settings',
            'fields' => [
                ['key' => 'hm_signature_method', 'label' => 'Signature Capture Method', 'type' => 'select', 'options' => ['wacom' => 'Wacom Signature Pad', 'touch' => 'Touch/Mouse Canvas', 'none' => 'No Signature'], 'default' => 'wacom'],
                ['key' => 'hm_require_gdpr_consent', 'label' => 'Require GDPR Consent on Forms', 'type' => 'toggle', 'default' => '1'],
                ['key' => 'hm_form_types', 'label' => 'Form Types (one per line)', 'type' => 'textarea', 'default' => "New Digital Consent Form\nGeneral Consent Form\nAudiogram\nGP/ENT Referral\nHearing Test\nRepair Form\nCase History\nFitting Receipt\nPhone Call Log"],
            ],
        ],
        'hearmed_cash_settings' => [
            'title' => 'Cash Management Settings',
            'fields' => [
                ['key' => 'hm_cash_morning_prompt', 'label' => 'Morning Till Prompt Enabled', 'type' => 'toggle', 'default' => '1'],
                ['key' => 'hm_cash_evening_prompt', 'label' => 'Evening Till Prompt Enabled', 'type' => 'toggle', 'default' => '1'],
                ['key' => 'hm_cash_evening_time', 'label' => 'Evening Prompt Time', 'type' => 'text', 'default' => '16:55'],
                ['key' => 'hm_cash_track_card', 'label' => 'Track Card Payments in Tills', 'type' => 'toggle', 'default' => '0'],
            ],
        ],
        'hearmed_ai_settings' => [
            'title' => 'AI Settings',
            'fields' => [
                ['key' => 'hm_ai_enabled', 'label' => 'AI Features Enabled', 'type' => 'toggle', 'default' => '0'],
                ['key' => 'hm_make_webhook_transcription', 'label' => 'Make.com Webhook — AI Transcription', 'type' => 'text', 'default' => ''],
                ['key' => 'hm_make_webhook_summary', 'label' => 'Make.com Webhook — Smart Summary', 'type' => 'text', 'default' => ''],
                ['key' => 'hm_make_webhook_brief', 'label' => 'Make.com Webhook — Appointment Brief', 'type' => 'text', 'default' => ''],
                ['key' => 'hm_make_webhook_flagging', 'label' => 'Make.com Webhook — Intelligent Flagging', 'type' => 'text', 'default' => ''],
                ['key' => 'hm_ai_auto_save', 'label' => 'Auto-save Transcriptions (skip review)', 'type' => 'toggle', 'default' => '0'],
            ],
        ],
        'hearmed_gdpr_settings' => [
            'title' => 'GDPR Settings',
            'fields' => [
                ['key' => 'hm_privacy_policy_url', 'label' => 'Privacy Policy URL', 'type' => 'text', 'default' => ''],
                ['key' => 'hm_retention_patient_years', 'label' => 'Patient Record Retention (years)', 'type' => 'number', 'default' => '8'],
                ['key' => 'hm_retention_financial_years', 'label' => 'Financial Record Retention (years)', 'type' => 'number', 'default' => '6'],
                ['key' => 'hm_retention_sms_years', 'label' => 'SMS Log Retention (years)', 'type' => 'number', 'default' => '2'],
                ['key' => 'hm_data_processors', 'label' => 'Third-Party Data Processors (one per line)', 'type' => 'textarea', 'default' => "WordPress.com (Automattic) — Hosting\nMake.com — Automations\nOpenRouter — AI Processing\nTwilio — SMS\nQuickBooks (Intuit) — Accounting"],
            ],
        ],
        'hearmed_admin_alerts' => [
            'title' => 'Alerts & Notification Settings',
            'fields' => [
                ['key' => 'hm_notify_order_status', 'label' => 'Notify on Order Status Change', 'type' => 'toggle', 'default' => '1'],
                ['key' => 'hm_notify_fitting_overdue', 'label' => 'Notify on Overdue Fittings', 'type' => 'toggle', 'default' => '1'],
                ['key' => 'hm_notify_double_booking', 'label' => 'Notify on Double Booking', 'type' => 'toggle', 'default' => '1'],
                ['key' => 'hm_notify_cheque_reminder', 'label' => 'Cheque Not Sent Reminders', 'type' => 'toggle', 'default' => '1'],
                ['key' => 'hm_notify_duplicate_order', 'label' => 'Duplicate Order Warnings', 'type' => 'toggle', 'default' => '1'],
                ['key' => 'hm_review_reminder_months', 'label' => 'Annual Review Reminder (months)', 'type' => 'number', 'default' => '11'],
            ],
        ],
        'hearmed_admin_report_layout' => [
            'title' => 'Report Layout',
            'fields' => [
                ['key' => 'hm_report_logo_url', 'label' => 'Report Logo URL', 'type' => 'text', 'default' => ''],
                ['key' => 'hm_report_company_name', 'label' => 'Company Name on Reports', 'type' => 'text', 'default' => 'HearMed Acoustic Health Care Ltd'],
                ['key' => 'hm_report_footer_text', 'label' => 'Report Footer Text', 'type' => 'text', 'default' => ''],
            ],
        ],
        'hearmed_admin_patient_overview' => [
            'title' => 'Patient Overview Settings',
            'fields' => [
                ['key' => 'hm_patient_default_tab', 'label' => 'Default Patient Tab', 'type' => 'select', 'options' => ['overview' => 'Overview', 'appointments' => 'Appointments', 'orders' => 'Orders', 'notes' => 'Notes'], 'default' => 'overview'],
                ['key' => 'hm_patient_show_balance', 'label' => 'Show Balance on Patient Header', 'type' => 'toggle', 'default' => '1'],
                ['key' => 'hm_patient_show_prsi', 'label' => 'Show PRSI Status on Header', 'type' => 'toggle', 'default' => '1'],
            ],
        ],
    ];

    public function __construct() {
        foreach (array_keys($this->pages) as $sc) {
            add_shortcode($sc, [$this, 'render']);
        }
        add_action('wp_ajax_hm_admin_save_settings_page', [$this, 'ajax_save']);
    }

    public function render($atts, $content, $tag) {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';
        $page = $this->pages[$tag] ?? null;
        if (!$page) return '<p>Unknown settings page.</p>';

        ob_start(); ?>
        <div class="hm-admin">
            <div class="hm-admin-hd">
                <h2><?php echo esc_html($page['title']); ?></h2>
                <?php if (!empty($page['fields'])): ?>
                <button class="hm-btn hm-btn-teal" onclick="hmSettings.save('<?php echo esc_attr($tag); ?>')" id="hms-save-btn">Save Settings</button>
                <?php endif; ?>
            </div>

            <?php if (!empty($page['note'])): ?>
                <div class="hm-empty-state"><p><?php echo esc_html($page['note']); ?></p></div>
            <?php endif; ?>

            <?php if (!empty($page['fields'])): ?>
            <div class="hm-settings-panel">
                <?php foreach ($page['fields'] as $f):
                    $val = $this->get_setting_value($tag, $f['key'], $f['default']);
                ?>
                <div class="hm-form-group">
                    <?php if ($f['type'] === 'toggle'): ?>
                        <label class="hm-toggle-label">
                            <input type="checkbox" class="hm-stg-field" data-key="<?php echo esc_attr($f['key']); ?>" <?php checked($val, '1'); ?>>
                            <?php echo esc_html($f['label']); ?>
                        </label>
                    <?php else: ?>
                        <label><?php echo esc_html($f['label']); ?></label>
                        <?php if ($f['type'] === 'textarea'): ?>
                            <textarea class="hm-stg-field" data-key="<?php echo esc_attr($f['key']); ?>" rows="5"><?php echo esc_textarea($val); ?></textarea>
                        <?php elseif ($f['type'] === 'select'): ?>
                            <select class="hm-stg-field" data-key="<?php echo esc_attr($f['key']); ?>">
                                <?php foreach ($f['options'] as $k => $v): ?>
                                <option value="<?php echo esc_attr($k); ?>" <?php selected($val, $k); ?>><?php echo esc_html($v); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($f['type'] === 'password'): ?>
                            <input type="password" class="hm-stg-field" data-key="<?php echo esc_attr($f['key']); ?>" value="<?php echo esc_attr($val); ?>">
                        <?php else: ?>
                            <input type="<?php echo $f['type'] === 'number' ? 'number' : 'text'; ?>" class="hm-stg-field" data-key="<?php echo esc_attr($f['key']); ?>" value="<?php echo esc_attr($val); ?>" <?php echo $f['type'] === 'number' ? 'step="0.1" min="0"' : ''; ?>>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>


        <script>
        var hmSettings = {
            save: function(tag) {
                var data = { action:'hm_admin_save_settings_page', nonce:HM.nonce, page_tag:tag, settings:{} };
                document.querySelectorAll('.hm-stg-field').forEach(function(el) {
                    var key = el.dataset.key;
                    if (el.type === 'checkbox') data.settings[key] = el.checked ? '1' : '0';
                    else data.settings[key] = el.value;
                });
                data.settings = JSON.stringify(data.settings);

                var btn = document.getElementById('hms-save-btn');
                btn.textContent = 'Saving...'; btn.disabled = true;
                jQuery.post(HM.ajax_url, data, function(r) {
                    if (r.success) { btn.textContent = '✓ Saved'; setTimeout(function(){ btn.textContent = 'Save Settings'; btn.disabled = false; }, 1500); }
                    else { alert(r.data || 'Error'); btn.textContent = 'Save Settings'; btn.disabled = false; }
                });
            }
        };
        </script>
        <?php return ob_get_clean();
    }

    public function ajax_save() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        $tag = sanitize_text_field($_POST['page_tag'] ?? '');
        $page = $this->pages[$tag] ?? null;
        if (!$page) { wp_send_json_error('Unknown page'); return; }

        $settings = json_decode(stripslashes($_POST['settings'] ?? '{}'), true);
        if (!is_array($settings)) { wp_send_json_error('Invalid data'); return; }

        $valid_keys = array_column($page['fields'], 'key');
        if ($tag === 'hearmed_gdpr_settings') {
            $this->save_gdpr_settings($settings, $valid_keys);
            wp_send_json_success();
            return;
        }

        foreach ($settings as $key => $val) {
            if (in_array($key, $valid_keys)) {
                update_option($key, sanitize_text_field($val));
            }
        }

            wp_send_json_success();
    }

    private function get_setting_value($tag, $key, $default) {
        if ($tag === 'hearmed_gdpr_settings') {
            $settings = $this->get_gdpr_settings();
            return $settings[$key] ?? $default;
        }
        return get_option($key, $default);
    }

    private function get_gdpr_settings() {
        if (is_array($this->pg_settings_cache)) return $this->pg_settings_cache;
        $row = HearMed_DB::get_row("SELECT * FROM hearmed_admin.gdpr_settings LIMIT 1");
        $this->pg_settings_cache = $row ? (array) $row : [];
        return $this->pg_settings_cache;
    }

    private function save_gdpr_settings($settings, $valid_keys) {
        $data = [];
        foreach ($settings as $key => $val) {
            if (in_array($key, $valid_keys)) {
                $data[$key] = sanitize_text_field($val);
            }
        }
        if (empty($data)) return;

        $data['updated_at'] = current_time('mysql');
        $existing_id = HearMed_DB::get_var("SELECT id FROM hearmed_admin.gdpr_settings LIMIT 1");
        if ($existing_id) {
            HearMed_DB::update('hearmed_admin.gdpr_settings', $data, ['id' => $existing_id]);
        } else {
            $data['created_at'] = current_time('mysql');
            HearMed_DB::insert('hearmed_admin.gdpr_settings', $data);
        }
    }
}

new HearMed_Admin_Settings();
