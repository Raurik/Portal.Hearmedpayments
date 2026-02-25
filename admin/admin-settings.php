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
                ['key' => 'hm_vat_bundled', 'label' => 'VAT Rate — Bundled Items (%)', 'type' => 'number', 'default' => '0'],
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
        'hearmed_pusher_settings' => [
            'title' => 'Pusher Settings (Team Chat)',
            'fields' => [
                 ['key' => 'hm_pusher_app_id',     'label' => 'Pusher App ID',     'type' => 'password', 'default' => ''],
                 ['key' => 'hm_pusher_app_key',    'label' => 'Pusher App Key',    'type' => 'password', 'default' => ''],
                 ['key' => 'hm_pusher_app_secret', 'label' => 'Pusher App Secret', 'type' => 'password', 'default' => ''],
                 ['key' => 'hm_pusher_cluster',    'label' => 'Pusher Cluster',    'type' => 'text',     'default' => 'eu'],
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
        add_action('wp_ajax_hm_admin_upload_gdpr_doc', [$this, 'ajax_upload_gdpr_doc']);
        add_action('wp_ajax_hm_admin_delete_gdpr_doc', [$this, 'ajax_delete_gdpr_doc']);
    }

    public function render($atts, $content, $tag) {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';
        $page = $this->pages[$tag] ?? null;
        if (!$page) return '<p>Unknown settings page.</p>';

        ob_start(); ?>
        <style>.hm-secret-wrap{display:flex;gap:8px;align-items:center;}.hm-secret-wrap input{flex:1;}</style>
        <div class="hm-admin">
            <div style="margin-bottom:16px;"><a href="javascript:history.back()" class="hm-btn">&larr; Back</a></div>
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
                            <?php if ($val !== '' && $val !== $f['default']): ?>
                            <div class="hm-secret-wrap" data-key="<?php echo esc_attr($f['key']); ?>">
                                <input type="text" class="hm-stg-field" data-key="<?php echo esc_attr($f['key']); ?>" value="••••••••" readonly style="color:#94a3b8;letter-spacing:2px;">
                                <button type="button" class="hm-btn hm-btn-sm" onclick="hmSettings.editSecret(this)">Change</button>
                            </div>
                            <?php else: ?>
                            <input type="password" class="hm-stg-field" data-key="<?php echo esc_attr($f['key']); ?>" value="" placeholder="Enter value...">
                            <?php endif; ?>
                        <?php else: ?>
                            <input type="<?php echo $f['type'] === 'number' ? 'number' : 'text'; ?>" class="hm-stg-field" data-key="<?php echo esc_attr($f['key']); ?>" value="<?php echo esc_attr($val); ?>" <?php echo $f['type'] === 'number' ? 'step="0.1" min="0"' : ''; ?>>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($tag === 'hearmed_gdpr_settings'): ?>
            <!-- GDPR Policies & Forms Upload -->
            <div class="hm-settings-panel" style="margin-top:20px;">
                <h3 style="font-size:15px;margin-bottom:16px;">GDPR Policies & Forms</h3>
                <p style="color:var(--hm-text-light);font-size:13px;margin-bottom:16px;">Upload policy documents and consent forms (PDF). These are stored locally and can be referenced by staff.</p>

                <div style="display:flex;gap:8px;margin-bottom:16px;align-items:center;">
                    <select id="hm-gdpr-doc-type" class="hm-filter-select">
                        <option value="policy">Policy Document</option>
                        <option value="consent_form">Consent Form</option>
                        <option value="data_processing">Data Processing Agreement</option>
                        <option value="privacy_notice">Privacy Notice</option>
                        <option value="other">Other</option>
                    </select>
                    <input type="text" id="hm-gdpr-doc-name" class="hm-search-input" placeholder="Document name..." style="flex:1;">
                    <label class="hm-btn hm-btn-teal" style="cursor:pointer;margin:0;">
                        Upload PDF
                        <input type="file" id="hm-gdpr-doc-file" accept=".pdf" style="display:none;" onchange="hmGdpr.upload()">
                    </label>
                </div>

                <?php
                $docs = HearMed_DB::get_results(
                    "SELECT * FROM hearmed_admin.gdpr_documents WHERE is_active = true ORDER BY doc_type, created_at DESC"
                ) ?: [];
                $type_labels = ['policy' => 'Policy', 'consent_form' => 'Consent Form', 'data_processing' => 'DPA', 'privacy_notice' => 'Privacy Notice', 'other' => 'Other'];
                ?>
                <?php if (empty($docs)): ?>
                    <p style="color:var(--hm-text-light);font-size:13px;">No documents uploaded yet.</p>
                <?php else: ?>
                <table class="hm-table" id="hm-gdpr-docs-table">
                    <thead><tr><th>Document</th><th>Type</th><th>Uploaded</th><th style="width:120px;"></th></tr></thead>
                    <tbody>
                    <?php foreach ($docs as $doc): ?>
                    <tr data-id="<?php echo (int) $doc->id; ?>">
                        <td><strong><?php echo esc_html($doc->doc_name); ?></strong></td>
                        <td><span class="hm-badge hm-badge-blue"><?php echo esc_html($type_labels[$doc->doc_type] ?? $doc->doc_type); ?></span></td>
                        <td style="font-size:12px;color:var(--hm-text-light);"><?php echo esc_html(date('d M Y', strtotime($doc->created_at))); ?></td>
                        <td class="hm-table-acts">
                            <a href="<?php echo esc_url($doc->file_url); ?>" target="_blank" class="hm-btn hm-btn-sm">View</a>
                            <button class="hm-btn hm-btn-sm hm-btn-red" onclick="hmGdpr.del(<?php echo (int) $doc->id; ?>,'<?php echo esc_js($doc->doc_name); ?>')">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <script>
            var hmGdpr = {
                upload: function() {
                    var fileInput = document.getElementById('hm-gdpr-doc-file');
                    var file = fileInput.files[0];
                    if (!file) return;
                    var name = document.getElementById('hm-gdpr-doc-name').value.trim();
                    if (!name) name = file.name.replace(/\.pdf$/i, '');
                    var docType = document.getElementById('hm-gdpr-doc-type').value;
                    var fd = new FormData();
                    fd.append('action', 'hm_admin_upload_gdpr_doc');
                    fd.append('nonce', HM.nonce);
                    fd.append('doc_name', name);
                    fd.append('doc_type', docType);
                    fd.append('file', file);
                    jQuery.ajax({
                        url: HM.ajax_url, type: 'POST', data: fd,
                        processData: false, contentType: false,
                        success: function(r) {
                            if (r.success) location.reload();
                            else alert(r.data || 'Upload failed');
                        }
                    });
                    fileInput.value = '';
                },
                del: function(id, name) {
                    if (!confirm('Delete "' + name + '"?')) return;
                    jQuery.post(HM.ajax_url, { action:'hm_admin_delete_gdpr_doc', nonce:HM.nonce, doc_id:id }, function(r) {
                        if (r.success) location.reload();
                        else alert(r.data || 'Error');
                    });
                }
            };
            </script>
            <?php endif; ?>
        </div>


        <script>
        var hmSettings = {
            save: function(tag) {
                var data = { action:'hm_admin_save_settings_page', nonce:HM.nonce, page_tag:tag, settings:{} };
                document.querySelectorAll('.hm-stg-field').forEach(function(el) {
                    var key = el.dataset.key;
                    if (el.type === 'checkbox') data.settings[key] = el.checked ? '1' : '0';
                    else if (el.readOnly && el.value === '\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022') return; // skip masked secrets
                    else data.settings[key] = el.value;
                });
                data.settings = JSON.stringify(data.settings);

                var btn = document.getElementById('hms-save-btn');
                btn.textContent = 'Saving...'; btn.disabled = true;
                jQuery.post(HM.ajax_url, data, function(r) {
                    if (r.success) { btn.textContent = '✓ Saved'; setTimeout(function(){ btn.textContent = 'Save Settings'; btn.disabled = false; }, 1500); }
                    else { alert(r.data || 'Error'); btn.textContent = 'Save Settings'; btn.disabled = false; }
                });
            },
            editSecret: function(btn) {
                var wrap = btn.closest('.hm-secret-wrap');
                var inp  = wrap.querySelector('input');
                inp.readOnly = false;
                inp.type  = 'password';
                inp.value = '';
                inp.style.color = '';
                inp.style.letterSpacing = '';
                inp.placeholder = 'Enter new value...';
                inp.focus();
                btn.textContent = 'Cancel';
                btn.onclick = function() {
                    inp.readOnly = true;
                    inp.type  = 'text';
                    inp.value = '\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022';
                    inp.style.color = '#94a3b8';
                    inp.style.letterSpacing = '2px';
                    inp.placeholder = '';
                    btn.textContent = 'Change';
                    btn.onclick = function() { hmSettings.editSecret(btn); };
                };
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

    public function ajax_upload_gdpr_doc() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        if (empty($_FILES['file'])) { wp_send_json_error('No file uploaded'); return; }
        $file = $_FILES['file'];
        if ($file['type'] !== 'application/pdf' && strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf') {
            wp_send_json_error('Only PDF files are allowed');
            return;
        }

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $upload_overrides = ['test_form' => false, 'mimes' => ['pdf' => 'application/pdf']];
        $movefile = wp_handle_upload($file, $upload_overrides);

        if (!$movefile || isset($movefile['error'])) {
            wp_send_json_error($movefile['error'] ?? 'Upload failed');
            return;
        }

        $doc_name = sanitize_text_field($_POST['doc_name'] ?? $file['name']);
        $doc_type = sanitize_text_field($_POST['doc_type'] ?? 'policy');

        $id = HearMed_DB::insert('hearmed_admin.gdpr_documents', [
            'doc_name'   => $doc_name,
            'doc_type'   => $doc_type,
            'file_url'   => $movefile['url'],
            'file_path'  => $movefile['file'],
            'uploaded_by' => get_current_user_id(),
            'is_active'  => true,
            'created_at' => current_time('mysql'),
        ]);

        if ($id) {
            wp_send_json_success(['id' => $id]);
        } else {
            wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
        }
    }

    public function ajax_delete_gdpr_doc() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        $doc_id = intval($_POST['doc_id'] ?? 0);
        if (!$doc_id) { wp_send_json_error('Invalid document'); return; }

        // Soft delete
        $result = HearMed_DB::update('hearmed_admin.gdpr_documents', [
            'is_active' => false,
            'updated_at' => current_time('mysql'),
        ], ['id' => $doc_id]);

        if ($result === false) {
            wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
        } else {
            wp_send_json_success();
        }
    }
}

new HearMed_Admin_Settings();
