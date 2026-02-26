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
            'custom_render' => 'render_finance',
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
                ['key' => 'hm_credit_note_prefix', 'label' => 'Credit Note Prefix', 'type' => 'text', 'default' => 'HMCN'],
                ['key' => 'hm_repair_prefix', 'label' => 'Repair Number Prefix', 'type' => 'text', 'default' => 'HMREP'],
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
        /* Document Types shortcode now handled by admin-document-templates.php */
        'hearmed_form_settings' => [
            'title' => 'Form & Input Settings',
            'custom_render' => 'render_form_settings',
            'fields' => [
                ['key' => 'hm_signature_method', 'label' => 'Signature Capture Method', 'type' => 'select', 'options' => ['wacom' => 'Wacom Signature Pad', 'touch' => 'Touch/Mouse Canvas', 'none' => 'No Signature'], 'default' => 'wacom'],
                ['key' => 'hm_require_gdpr_consent', 'label' => 'Require GDPR Consent on Forms', 'type' => 'toggle', 'default' => '1'],
                ['key' => 'hm_form_types', 'label' => 'Form Types', 'type' => 'text', 'default' => "New Digital Consent Form\nGeneral Consent Form\nAudiogram\nGP/ENT Referral\nHearing Test\nRepair Form\nCase History\nFitting Receipt\nPhone Call Log"],
            ],
        ],
        'hearmed_cash_settings' => [
            'title' => 'Cash Management Settings',
            'max_width' => '480px',
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
            'custom_render' => 'render_report_layout',
            'fields' => [
                ['key' => 'hm_report_logo_url', 'label' => 'Report Logo', 'type' => 'text', 'default' => ''],
                ['key' => 'hm_report_company_name', 'label' => 'Company Name on Reports', 'type' => 'text', 'default' => 'HearMed Acoustic Health Care Ltd'],
                ['key' => 'hm_report_footer_text', 'label' => 'Report Footer Text', 'type' => 'textarea', 'default' => ''],
                ['key' => 'hm_report_terms', 'label' => 'Terms & Conditions', 'type' => 'textarea', 'default' => ''],
                ['key' => 'hm_report_show_logo', 'label' => 'Show Logo on Reports', 'type' => 'toggle', 'default' => '1'],
                ['key' => 'hm_report_show_company', 'label' => 'Show Company Name', 'type' => 'toggle', 'default' => '1'],
                ['key' => 'hm_report_show_footer', 'label' => 'Show Footer', 'type' => 'toggle', 'default' => '1'],
                ['key' => 'hm_report_show_terms', 'label' => 'Show Terms & Conditions Page', 'type' => 'toggle', 'default' => '0'],
                ['key' => 'hm_report_show_patient_details', 'label' => 'Show Patient Details Section', 'type' => 'toggle', 'default' => '1'],
                ['key' => 'hm_report_show_audiogram', 'label' => 'Show Audiogram Section', 'type' => 'toggle', 'default' => '1'],
                ['key' => 'hm_report_show_recommendations', 'label' => 'Show Recommendations Section', 'type' => 'toggle', 'default' => '1'],
                ['key' => 'hm_report_show_pricing', 'label' => 'Show Pricing Section', 'type' => 'toggle', 'default' => '1'],
                ['key' => 'hm_report_show_signature', 'label' => 'Show Signature Section', 'type' => 'toggle', 'default' => '1'],
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
        add_action('wp_ajax_hm_admin_upload_report_logo', [$this, 'ajax_upload_report_logo']);
    }

    public function render($atts, $content, $tag) {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';
        $page = $this->pages[$tag] ?? null;
        if (!$page) return '<p>Unknown settings page.</p>';

        /* --- Custom render dispatch --- */
        if (!empty($page['custom_render']) && method_exists($this, $page['custom_render'])) {
            return $this->{$page['custom_render']}($tag, $page);
        }

        ob_start(); ?>
        <style>.hm-secret-wrap{display:flex;gap:8px;align-items:center;}.hm-secret-wrap input{flex:1;}</style>
        <div class="hm-admin">
            <div style="margin-bottom:16px;"><a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-btn">&larr; Back</a></div>
            <div class="hm-admin-hd">
                <h2><?php echo esc_html($page['title']); ?></h2>
                <?php if (!empty($page['fields'])): ?>
                <button class="hm-btn hm-btn--primary" onclick="hmSettings.save('<?php echo esc_attr($tag); ?>')" id="hms-save-btn">Save Settings</button>
                <?php endif; ?>
            </div>

            <?php if (!empty($page['note'])): ?>
                <div class="hm-empty-state"><p><?php echo esc_html($page['note']); ?></p></div>
            <?php endif; ?>

            <?php if (!empty($page['fields'])): ?>
            <div class="hm-settings-panel"<?php if (!empty($page['max_width'])): ?> style="max-width:<?php echo esc_attr($page['max_width']); ?>"<?php endif; ?>>
                <?php foreach ($page['fields'] as $f):
                    $val = $this->get_setting_value($tag, $f['key'], $f['default']);
                ?>
                <div class="hm-form-group">
                    <?php if ($f['type'] === 'toggle'): ?>
                        <label class="hm-toggle-label">
                            <input type="checkbox" class="hm-form-group" data-key="<?php echo esc_attr($f['key']); ?>" <?php checked($val, '1'); ?>>
                            <?php echo esc_html($f['label']); ?>
                        </label>
                    <?php else: ?>
                        <label><?php echo esc_html($f['label']); ?></label>
                        <?php if ($f['type'] === 'textarea'): ?>
                            <textarea class="hm-form-group" data-key="<?php echo esc_attr($f['key']); ?>" rows="5"><?php echo esc_textarea($val); ?></textarea>
                        <?php elseif ($f['type'] === 'select'): ?>
                            <select class="hm-form-group" data-key="<?php echo esc_attr($f['key']); ?>">
                                <?php foreach ($f['options'] as $k => $v): ?>
                                <option value="<?php echo esc_attr($k); ?>" <?php selected($val, $k); ?>><?php echo esc_html($v); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($f['type'] === 'password'): ?>
                            <?php if ($val !== '' && $val !== $f['default']): ?>
                            <div class="hm-secret-wrap" data-key="<?php echo esc_attr($f['key']); ?>">
                                <input type="text" class="hm-form-group" data-key="<?php echo esc_attr($f['key']); ?>" value="••••••••" readonly style="color:#94a3b8;letter-spacing:2px;">
                                <button type="button" class="hm-btn hm-btn--sm" onclick="hmSettings.editSecret(this)">Change</button>
                            </div>
                            <?php else: ?>
                            <input type="password" class="hm-form-group" data-key="<?php echo esc_attr($f['key']); ?>" value="" placeholder="Enter value...">
                            <?php endif; ?>
                        <?php else: ?>
                            <input type="<?php echo $f['type'] === 'number' ? 'number' : 'text'; ?>" class="hm-form-group" data-key="<?php echo esc_attr($f['key']); ?>" value="<?php echo esc_attr($val); ?>" <?php echo $f['type'] === 'number' ? 'step="0.1" min="0"' : ''; ?>>
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
                    <label class="hm-btn hm-btn--primary" style="cursor:pointer;margin:0;">
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
                        <td><span class="hm-badge hm-badge--blue"><?php echo esc_html($type_labels[$doc->doc_type] ?? $doc->doc_type); ?></span></td>
                        <td style="font-size:12px;color:var(--hm-text-light);"><?php echo esc_html(date('d M Y', strtotime($doc->created_at))); ?></td>
                        <td class="hm-table-acts">
                            <a href="<?php echo esc_url($doc->file_url); ?>" target="_blank" class="hm-btn hm-btn--sm">View</a>
                            <button class="hm-btn hm-btn--sm hm-btn--danger" onclick="hmGdpr.del(<?php echo (int) $doc->id; ?>,'<?php echo esc_js($doc->doc_name); ?>')">Delete</button>
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
                document.querySelectorAll('.hm-form-group').forEach(function(el) {
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

    /* =============================
       CUSTOM RENDER: Form & Input Settings
       ============================= */
    public function render_form_settings($tag, $page) {
        $v = function($key, $default = '') {
            return get_option($key, $default);
        };

        $sig_method = $v('hm_signature_method', 'wacom');
        $gdpr_req   = $v('hm_require_gdpr_consent', '1');
        $ft_raw     = $v('hm_form_types', "New Digital Consent Form\nGeneral Consent Form\nAudiogram\nGP/ENT Referral\nHearing Test\nRepair Form\nCase History\nFitting Receipt\nPhone Call Log");
        $form_types = array_filter(array_map('trim', explode("\n", $ft_raw)));

        ob_start(); ?>
        <style>
        .hm-ft-blocks{display:flex;flex-wrap:wrap;gap:4px 12px;margin-bottom:10px;}
        .hm-ft-block{display:inline-flex;align-items:center;gap:4px;font-size:12px;font-weight:500;color:var(--hm-primary,#0BB4C4);}
        .hm-ft-block .hm-ft-x{cursor:pointer;opacity:.5;font-size:13px;line-height:1;color:var(--hm-primary,#0BB4C4);}
        .hm-ft-block .hm-ft-x:hover{opacity:1;}
        .hm-ft-add-wrap{display:flex;gap:6px;align-items:center;}
        .hm-ft-add-wrap input{flex:1;}
        </style>
        <div class="hm-admin">
            <div style="margin-bottom:16px;"><a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-btn">&larr; Back</a></div>
            <div class="hm-admin-hd">
                <h2>Form &amp; Input Settings</h2>
                <button class="hm-btn hm-btn--primary" onclick="hmSettings.save('<?php echo esc_attr($tag); ?>')" id="hms-save-btn">Save Settings</button>
            </div>

            <div class="hm-settings-panel" style="max-width:560px;">
                <div class="hm-form-group">
                    <label>Signature Capture Method</label>
                    <select class="hm-form-group" data-key="hm_signature_method">
                        <option value="wacom" <?php selected($sig_method, 'wacom'); ?>>Wacom Signature Pad</option>
                        <option value="touch" <?php selected($sig_method, 'touch'); ?>>Touch/Mouse Canvas</option>
                        <option value="none" <?php selected($sig_method, 'none'); ?>>No Signature</option>
                    </select>
                </div>

                <div class="hm-form-group">
                    <label class="hm-toggle-label">
                        <input type="checkbox" class="hm-form-group" data-key="hm_require_gdpr_consent" <?php checked($gdpr_req, '1'); ?>>
                        Require GDPR Consent on Forms
                    </label>
                </div>
            </div>

            <div class="hm-settings-panel" style="max-width:560px;margin-top:16px;">
                <h3 style="font-size:14px;margin-bottom:12px;">Form Types</h3>
                <p style="color:var(--hm-text-light);font-size:11px;margin-bottom:10px;">These appear in patient form dropdowns across the portal.</p>

                <div class="hm-ft-blocks" id="hm-ft-blocks">
                    <?php foreach ($form_types as $ft): ?>
                    <span class="hm-ft-block">
                        <span class="hm-ft-name"><?php echo esc_html($ft); ?></span>
                        <span class="hm-ft-x" onclick="hmFormTypes.remove(this)" title="Remove">&times;</span>
                    </span>
                    <?php endforeach; ?>
                </div>
                <div class="hm-ft-add-wrap">
                    <input type="text" id="hm-ft-new" placeholder="New form type..." class="hm-search-input" style="font-size:12px;padding:5px 8px;" onkeydown="if(event.key==='Enter'){event.preventDefault();hmFormTypes.add();}">
                    <button type="button" class="hm-btn hm-btn--primary hm-btn--sm" onclick="hmFormTypes.add()">+ Add</button>
                </div>
                <input type="hidden" class="hm-form-group" data-key="hm_form_types" id="hm-ft-hidden" value="<?php echo esc_attr($ft_raw); ?>">
            </div>
        </div>

        <script>
        var hmFormTypes = {
            syncHidden: function() {
                var names = [];
                document.querySelectorAll('#hm-ft-blocks .hm-ft-name').forEach(function(el) {
                    names.push(el.textContent.trim());
                });
                document.getElementById('hm-ft-hidden').value = names.join('\n');
            },
            add: function() {
                var inp = document.getElementById('hm-ft-new');
                var name = inp.value.trim();
                if (!name) return;
                // Duplicate check
                var existing = [];
                document.querySelectorAll('#hm-ft-blocks .hm-ft-name').forEach(function(el) { existing.push(el.textContent.trim().toLowerCase()); });
                if (existing.indexOf(name.toLowerCase()) !== -1) { inp.value = ''; return; }

                var block = document.createElement('span');
                block.className = 'hm-ft-block';
                block.innerHTML = '<span class="hm-ft-name">' + name.replace(/</g,'&lt;') + '</span><span class="hm-ft-x" onclick="hmFormTypes.remove(this)" title="Remove">&times;</span>';
                document.getElementById('hm-ft-blocks').appendChild(block);
                inp.value = '';
                hmFormTypes.syncHidden();
            },
            remove: function(x) {
                if (!confirm('Remove "' + x.previousElementSibling.textContent.trim() + '"?')) return;
                x.closest('.hm-ft-block').remove();
                hmFormTypes.syncHidden();
            }
        };

        var hmSettings = {
            save: function(tag) {
                hmFormTypes.syncHidden();
                var data = { action:'hm_admin_save_settings_page', nonce:HM.nonce, page_tag:tag, settings:{} };
                document.querySelectorAll('.hm-form-group').forEach(function(el) {
                    var key = el.dataset.key;
                    if (el.type === 'checkbox') data.settings[key] = el.checked ? '1' : '0';
                    else data.settings[key] = el.value;
                });
                data.settings = JSON.stringify(data.settings);
                var btn = document.getElementById('hms-save-btn');
                btn.textContent = 'Saving...'; btn.disabled = true;
                jQuery.post(HM.ajax_url, data, function(r) {
                    if (r.success) { btn.textContent = '\u2713 Saved'; setTimeout(function(){ btn.textContent = 'Save Settings'; btn.disabled = false; }, 1500); }
                    else { alert(r.data || 'Error'); btn.textContent = 'Save Settings'; btn.disabled = false; }
                });
            },
            editSecret: function(btn) {
                var wrap = btn.closest('.hm-secret-wrap');
                var inp  = wrap.querySelector('input');
                inp.readOnly = false; inp.type = 'password'; inp.value = ''; inp.style.color = ''; inp.style.letterSpacing = ''; inp.placeholder = 'Enter new value...'; inp.focus();
                btn.textContent = 'Cancel';
                btn.onclick = function() { inp.readOnly = true; inp.type = 'text'; inp.value = '\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022'; inp.style.color = '#94a3b8'; inp.style.letterSpacing = '2px'; inp.placeholder = ''; btn.textContent = 'Change'; btn.onclick = function() { hmSettings.editSecret(btn); }; };
            }
        };
        </script>
        <?php return ob_get_clean();
    }

    /* =============================
       CUSTOM RENDER: Finance Settings
       ============================= */
    public function render_finance($tag, $page) {
        $v = function($key, $default = '') {
            return get_option($key, $default);
        };

        ob_start(); ?>
        <style>
        .hm-secret-wrap{display:flex;gap:8px;align-items:center;}.hm-secret-wrap input{flex:1;}
        .hm-finance-grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;align-items:start;}
        @media(max-width:900px){.hm-finance-grid{grid-template-columns:1fr;}}
        .hm-pm-blocks{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px;}
        .hm-pm-block{display:inline-flex;align-items:center;gap:5px;background:var(--hm-primary,#3b82f6);color:#fff;padding:4px 10px;border-radius:6px;font-size:12px;font-weight:500;}
        .hm-pm-block .hm-pm-x{cursor:pointer;opacity:.7;font-size:14px;line-height:1;}
        .hm-pm-block .hm-pm-x:hover{opacity:1;}
        .hm-pm-add-wrap{display:flex;gap:6px;align-items:center;}
        .hm-pm-add-wrap input{flex:1;}
        .hm-inv-row{display:flex;align-items:center;gap:10px;margin-bottom:10px;}
        .hm-inv-row .hm-form-group{flex:1;margin-bottom:0;}
        .hm-inv-last{font-size:11px;color:var(--hm-text-light,#94a3b8);white-space:nowrap;min-width:100px;}
        </style>
        <div class="hm-admin">
            <div style="margin-bottom:16px;"><a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-btn">&larr; Back</a></div>
            <div class="hm-admin-hd">
                <h2>Finance Settings</h2>
                <button class="hm-btn hm-btn--primary" onclick="hmSettings.save('<?php echo esc_attr($tag); ?>')" id="hms-save-btn">Save Settings</button>
            </div>

            <div class="hm-finance-grid">
            <!-- VAT Settings -->
            <div class="hm-settings-panel">
                <h3 style="font-size:14px;margin-bottom:12px;">VAT Settings</h3>
                <?php
                $vat_fields = [
                    ['key' => 'hm_vat_hearing_aids', 'label' => 'Hearing Aids (%)', 'default' => '0'],
                    ['key' => 'hm_vat_accessories',  'label' => 'Accessories (%)',  'default' => '0'],
                    ['key' => 'hm_vat_services',     'label' => 'Services (%)',     'default' => '13.5'],
                    ['key' => 'hm_vat_consumables',  'label' => 'Consumables (%)',  'default' => '23'],
                    ['key' => 'hm_vat_bundled',      'label' => 'Bundled Items (%)', 'default' => '0'],
                    ['key' => 'hm_vat_other_aud',    'label' => 'Other Audiological (%)', 'default' => '13.5'],
                ];
                foreach ($vat_fields as $f): ?>
                <div class="hm-form-group">
                    <label><?php echo esc_html($f['label']); ?></label>
                    <input type="number" class="hm-form-group" data-key="<?php echo esc_attr($f['key']); ?>" value="<?php echo esc_attr($v($f['key'], $f['default'])); ?>" step="1" min="0">
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Payment & DSP -->
            <div class="hm-settings-panel">
                <h3 style="font-size:14px;margin-bottom:12px;">Payment &amp; DSP</h3>

                <label style="font-weight:500;margin-bottom:6px;display:block;font-size:11px;color:#475569;">Payment Methods</label>
                <?php
                $methods_raw = $v('hm_payment_methods', 'Card,Cash,Cheque,Bank Transfer,Finance,PRSI');
                $methods     = array_filter(array_map('trim', explode(',', $methods_raw)));
                ?>
                <div class="hm-pm-blocks" id="hm-pm-blocks">
                    <?php foreach ($methods as $m): ?>
                    <span class="hm-pm-block">
                        <span class="hm-pm-name"><?php echo esc_html($m); ?></span>
                        <span class="hm-pm-x" onclick="hmFinance.removeMethod(this)" title="Remove">&times;</span>
                    </span>
                    <?php endforeach; ?>
                </div>
                <div class="hm-pm-add-wrap">
                    <input type="text" id="hm-pm-new" placeholder="New method..." class="hm-search-input" style="font-size:12px;padding:5px 8px;">
                    <button type="button" class="hm-btn hm-btn--primary hm-btn--sm" onclick="hmFinance.addMethod()">+ Add</button>
                </div>
                <input type="hidden" class="hm-form-group" data-key="hm_payment_methods" id="hm-pm-hidden" value="<?php echo esc_attr($methods_raw); ?>">

                <div class="hm-form-group" style="margin-top:14px;">
                    <label>PRSI Amount Per Ear (€)</label>
                    <input type="number" class="hm-form-group" data-key="hm_prsi_amount_per_ear" value="<?php echo esc_attr($v('hm_prsi_amount_per_ear', '500')); ?>" step="1" min="0">
                </div>
            </div>

            <!-- Invoice Settings -->
            <div class="hm-settings-panel">
                <h3 style="font-size:14px;margin-bottom:12px;">Invoice Settings</h3>
                <p style="color:var(--hm-text-light);font-size:11px;margin-bottom:12px;">Numbers auto-increment from the last used value.</p>
                <?php
                $inv_fields = [
                    ['key' => 'hm_invoice_prefix',    'label' => 'Invoice Prefix',     'default' => 'INV-', 'counter' => 'hm_invoice_last_number'],
                    ['key' => 'hm_order_prefix',      'label' => 'Order Prefix',       'default' => 'ORD-', 'counter' => 'hm_order_last_number'],
                    ['key' => 'hm_credit_note_prefix', 'label' => 'Credit Note Prefix', 'default' => 'HMCN',  'counter' => 'hm_credit_note_last_number'],
                    ['key' => 'hm_repair_prefix',     'label' => 'Repair Prefix',      'default' => 'HMREP', 'counter' => 'hm_repair_last_number'],
                ];
                foreach ($inv_fields as $f):
                    $last_num = intval($v($f['counter'], '0'));
                    $prefix   = $v($f['key'], $f['default']);
                    $next_num = $last_num + 1;
                ?>
                <div class="hm-inv-row">
                    <div class="hm-form-group">
                        <label><?php echo esc_html($f['label']); ?></label>
                        <input type="text" class="hm-form-group" data-key="<?php echo esc_attr($f['key']); ?>" value="<?php echo esc_attr($prefix); ?>">
                    </div>
                    <div class="hm-inv-last">
                        <?php if ($last_num > 0): ?>
                            Last: <strong><?php echo esc_html($prefix . $last_num); ?></strong><br>
                            Next: <?php echo esc_html($prefix . $next_num); ?>
                        <?php else: ?>
                            Next: <?php echo esc_html($prefix . '1'); ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            </div><!-- /hm-finance-grid -->
        </div>

        <script>
        var hmFinance = {
            syncHidden: function() {
                var names = [];
                document.querySelectorAll('#hm-pm-blocks .hm-pm-name').forEach(function(el) {
                    names.push(el.textContent.trim());
                });
                document.getElementById('hm-pm-hidden').value = names.join(',');
            },
            addMethod: function() {
                var inp = document.getElementById('hm-pm-new');
                var name = inp.value.trim();
                if (!name) return;
                // Duplicate check
                var existing = [];
                document.querySelectorAll('#hm-pm-blocks .hm-pm-name').forEach(function(el) { existing.push(el.textContent.trim().toLowerCase()); });
                if (existing.indexOf(name.toLowerCase()) !== -1) { inp.value = ''; return; }

                var block = document.createElement('span');
                block.className = 'hm-pm-block';
                block.innerHTML = '<span class="hm-pm-name">' + name + '</span><span class="hm-pm-x" onclick="hmFinance.removeMethod(this)" title="Remove">&times;</span>';
                document.getElementById('hm-pm-blocks').appendChild(block);
                inp.value = '';
                hmFinance.syncHidden();
            },
            removeMethod: function(x) {
                if (!confirm('Remove "' + x.previousElementSibling.textContent.trim() + '"?')) return;
                x.closest('.hm-pm-block').remove();
                hmFinance.syncHidden();
            }
        };

        var hmSettings = {
            save: function(tag) {
                hmFinance.syncHidden();
                var data = { action:'hm_admin_save_settings_page', nonce:HM.nonce, page_tag:tag, settings:{} };
                document.querySelectorAll('.hm-form-group').forEach(function(el) {
                    var key = el.dataset.key;
                    if (el.type === 'checkbox') data.settings[key] = el.checked ? '1' : '0';
                    else data.settings[key] = el.value;
                });
                data.settings = JSON.stringify(data.settings);
                var btn = document.getElementById('hms-save-btn');
                btn.textContent = 'Saving...'; btn.disabled = true;
                jQuery.post(HM.ajax_url, data, function(r) {
                    if (r.success) { btn.textContent = '\u2713 Saved'; setTimeout(function(){ btn.textContent = 'Save Settings'; btn.disabled = false; }, 1500); }
                    else { alert(r.data || 'Error'); btn.textContent = 'Save Settings'; btn.disabled = false; }
                });
            },
            editSecret: function(btn) {
                var wrap = btn.closest('.hm-secret-wrap');
                var inp  = wrap.querySelector('input');
                inp.readOnly = false; inp.type = 'password'; inp.value = ''; inp.style.color = ''; inp.style.letterSpacing = ''; inp.placeholder = 'Enter new value...'; inp.focus();
                btn.textContent = 'Cancel';
                btn.onclick = function() { inp.readOnly = true; inp.type = 'text'; inp.value = '\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022'; inp.style.color = '#94a3b8'; inp.style.letterSpacing = '2px'; inp.placeholder = ''; btn.textContent = 'Change'; btn.onclick = function() { hmSettings.editSecret(btn); }; };
            }
        };
        </script>
        <?php return ob_get_clean();
    }

    /* =============================
       CUSTOM RENDER: Report Layout
       ============================= */
    public function render_report_layout($tag, $page) {
        $v = function($key, $default = '') {
            return get_option($key, $default);
        };

        $logo_url = $v('hm_report_logo_url', '');

        ob_start(); ?>
        <style>
        .hm-secret-wrap{display:flex;gap:8px;align-items:center;}.hm-secret-wrap input{flex:1;}
        .hm-logo-preview{max-width:200px;max-height:80px;border:1px solid var(--hm-border,#e2e8f0);border-radius:6px;padding:4px;background:#fff;margin-bottom:8px;}
        .hm-logo-upload-area{display:flex;align-items:center;gap:12px;margin-bottom:4px;}
        .hm-logo-hint{font-size:12px;color:var(--hm-text-light,#94a3b8);margin-top:4px;}
        .hm-section-toggles{display:grid;grid-template-columns:1fr 1fr;gap:8px 20px;}
        @media(max-width:600px){.hm-section-toggles{grid-template-columns:1fr;}}
        </style>
        <div class="hm-admin">
            <div style="margin-bottom:16px;"><a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-btn">&larr; Back</a></div>
            <div class="hm-admin-hd">
                <h2>Report Layout</h2>
                <button class="hm-btn hm-btn--primary" onclick="hmSettings.save('<?php echo esc_attr($tag); ?>')" id="hms-save-btn">Save Settings</button>
            </div>

            <!-- Report Branding -->
            <div class="hm-settings-panel" style="margin-bottom:20px;">
                <h3 style="font-size:15px;margin-bottom:16px;">Report Branding</h3>

                <div class="hm-form-group">
                    <label>Report Logo</label>
                    <?php if ($logo_url): ?>
                    <div id="hm-logo-preview-wrap">
                        <img src="<?php echo esc_url($logo_url); ?>" class="hm-logo-preview" id="hm-logo-preview">
                    </div>
                    <?php else: ?>
                    <div id="hm-logo-preview-wrap" style="display:none;">
                        <img src="" class="hm-logo-preview" id="hm-logo-preview">
                    </div>
                    <?php endif; ?>
                    <div class="hm-logo-upload-area">
                        <label class="hm-btn" style="cursor:pointer;margin:0;">
                            Choose PNG
                            <input type="file" id="hm-logo-file" accept=".png,image/png" style="display:none;" onchange="hmReport.uploadLogo()">
                        </label>
                        <?php if ($logo_url): ?>
                        <button type="button" class="hm-btn hm-btn--danger hm-btn--sm" id="hm-logo-remove-btn" onclick="hmReport.removeLogo()">Remove</button>
                        <?php else: ?>
                        <button type="button" class="hm-btn hm-btn--danger hm-btn--sm" id="hm-logo-remove-btn" onclick="hmReport.removeLogo()" style="display:none;">Remove</button>
                        <?php endif; ?>
                    </div>
                    <p class="hm-logo-hint">Recommended: PNG with transparent background, max 400 &times; 120 px.</p>
                    <input type="hidden" class="hm-form-group" data-key="hm_report_logo_url" id="hm-logo-url-field" value="<?php echo esc_attr($logo_url); ?>">
                </div>

                <div class="hm-form-group">
                    <label>Company Name on Reports</label>
                    <input type="text" class="hm-form-group" data-key="hm_report_company_name" value="<?php echo esc_attr($v('hm_report_company_name', 'HearMed Acoustic Health Care Ltd')); ?>">
                </div>
            </div>

            <!-- Report Footer -->
            <div class="hm-settings-panel" style="margin-bottom:20px;">
                <h3 style="font-size:15px;margin-bottom:16px;">Report Footer</h3>
                <div class="hm-form-group">
                    <label>Footer Text</label>
                    <textarea class="hm-form-group" data-key="hm_report_footer_text" rows="6" placeholder="Enter footer text that appears at the bottom of reports..."><?php echo esc_textarea($v('hm_report_footer_text', '')); ?></textarea>
                </div>
            </div>

            <!-- Terms & Conditions -->
            <div class="hm-settings-panel" style="margin-bottom:20px;">
                <h3 style="font-size:15px;margin-bottom:16px;">Company Terms &amp; Conditions</h3>
                <p style="color:var(--hm-text-light);font-size:13px;margin-bottom:12px;">This text will be included as a separate Terms &amp; Conditions page at the end of reports when enabled.</p>
                <div class="hm-form-group">
                    <textarea class="hm-form-group" data-key="hm_report_terms" rows="10" placeholder="Enter your company terms and conditions..."><?php echo esc_textarea($v('hm_report_terms', '')); ?></textarea>
                </div>
            </div>

            <!-- Report Sections -->
            <div class="hm-settings-panel">
                <h3 style="font-size:15px;margin-bottom:16px;">Report Sections</h3>
                <p style="color:var(--hm-text-light);font-size:13px;margin-bottom:16px;">Toggle which sections appear on generated reports.</p>
                <div class="hm-section-toggles">
                    <?php
                    $toggles = [
                        ['key' => 'hm_report_show_logo',            'label' => 'Show Logo on Reports',       'default' => '1'],
                        ['key' => 'hm_report_show_company',         'label' => 'Show Company Name',          'default' => '1'],
                        ['key' => 'hm_report_show_patient_details', 'label' => 'Show Patient Details',       'default' => '1'],
                        ['key' => 'hm_report_show_audiogram',       'label' => 'Show Audiogram Section',     'default' => '1'],
                        ['key' => 'hm_report_show_recommendations', 'label' => 'Show Recommendations',      'default' => '1'],
                        ['key' => 'hm_report_show_pricing',         'label' => 'Show Pricing Section',       'default' => '1'],
                        ['key' => 'hm_report_show_signature',       'label' => 'Show Signature Section',     'default' => '1'],
                        ['key' => 'hm_report_show_footer',          'label' => 'Show Footer',                'default' => '1'],
                        ['key' => 'hm_report_show_terms',           'label' => 'Show Terms & Conditions Page', 'default' => '0'],
                    ];
                    foreach ($toggles as $t): ?>
                    <label class="hm-toggle-label">
                        <input type="checkbox" class="hm-form-group" data-key="<?php echo esc_attr($t['key']); ?>" <?php checked($v($t['key'], $t['default']), '1'); ?>>
                        <?php echo esc_html($t['label']); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <script>
        var hmReport = {
            uploadLogo: function() {
                var fileInput = document.getElementById('hm-logo-file');
                var file = fileInput.files[0];
                if (!file) return;
                var fd = new FormData();
                fd.append('action', 'hm_admin_upload_report_logo');
                fd.append('nonce', HM.nonce);
                fd.append('file', file);
                jQuery.ajax({
                    url: HM.ajax_url, type: 'POST', data: fd,
                    processData: false, contentType: false,
                    success: function(r) {
                        if (r.success && r.data && r.data.url) {
                            document.getElementById('hm-logo-url-field').value = r.data.url;
                            var img = document.getElementById('hm-logo-preview');
                            img.src = r.data.url;
                            document.getElementById('hm-logo-preview-wrap').style.display = '';
                            document.getElementById('hm-logo-remove-btn').style.display = '';
                        } else {
                            alert(r.data || 'Upload failed');
                        }
                    }
                });
                fileInput.value = '';
            },
            removeLogo: function() {
                if (!confirm('Remove report logo?')) return;
                document.getElementById('hm-logo-url-field').value = '';
                document.getElementById('hm-logo-preview-wrap').style.display = 'none';
                document.getElementById('hm-logo-remove-btn').style.display = 'none';
            }
        };

        var hmSettings = {
            save: function(tag) {
                var data = { action:'hm_admin_save_settings_page', nonce:HM.nonce, page_tag:tag, settings:{} };
                document.querySelectorAll('.hm-form-group').forEach(function(el) {
                    var key = el.dataset.key;
                    if (el.type === 'checkbox') data.settings[key] = el.checked ? '1' : '0';
                    else data.settings[key] = el.value;
                });
                data.settings = JSON.stringify(data.settings);
                var btn = document.getElementById('hms-save-btn');
                btn.textContent = 'Saving...'; btn.disabled = true;
                jQuery.post(HM.ajax_url, data, function(r) {
                    if (r.success) { btn.textContent = '\u2713 Saved'; setTimeout(function(){ btn.textContent = 'Save Settings'; btn.disabled = false; }, 1500); }
                    else { alert(r.data || 'Error'); btn.textContent = 'Save Settings'; btn.disabled = false; }
                });
            },
            editSecret: function(btn) {
                var wrap = btn.closest('.hm-secret-wrap');
                var inp  = wrap.querySelector('input');
                inp.readOnly = false; inp.type = 'password'; inp.value = ''; inp.style.color = ''; inp.style.letterSpacing = ''; inp.placeholder = 'Enter new value...'; inp.focus();
                btn.textContent = 'Cancel';
                btn.onclick = function() { inp.readOnly = true; inp.type = 'text'; inp.value = '\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022'; inp.style.color = '#94a3b8'; inp.style.letterSpacing = '2px'; inp.placeholder = ''; btn.textContent = 'Change'; btn.onclick = function() { hmSettings.editSecret(btn); }; };
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

    public function ajax_upload_report_logo() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        if (empty($_FILES['file'])) { wp_send_json_error('No file uploaded'); return; }
        $file = $_FILES['file'];

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'png') {
            wp_send_json_error('Only PNG files are allowed');
            return;
        }

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $upload_overrides = ['test_form' => false, 'mimes' => ['png' => 'image/png']];
        $movefile = wp_handle_upload($file, $upload_overrides);

        if (!$movefile || isset($movefile['error'])) {
            wp_send_json_error($movefile['error'] ?? 'Upload failed');
            return;
        }

        // Save the URL immediately
        update_option('hm_report_logo_url', $movefile['url']);
        wp_send_json_success(['url' => $movefile['url']]);
    }
}

new HearMed_Admin_Settings();
