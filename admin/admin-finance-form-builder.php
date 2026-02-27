<?php
/**
 * HearMed Admin — Finance Form Builder
 * Shortcode: [hearmed_finance_form_builder]
 *
 * Configures print templates for Invoice, Order, Repair Docket, Credit Note.
 * Settings saved via AJAX to hearmed_admin.settings (hm_print_template_{type}).
 *
 * @package HearMed_Portal
 * @since   5.4.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Finance_Form_Builder {

    public function __construct() {
        add_shortcode('hearmed_finance_form_builder', [$this, 'render']);
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';
        if (!HearMed_Auth::can('manage_settings')) return '<p>Permission denied.</p>';

        // Load current settings for all 4 types
        $types = ['invoice', 'order', 'repair', 'creditnote'];
        $all_settings = [];
        foreach ($types as $t) {
            $all_settings[$t] = HearMed_Print_Templates::get_settings($t);
        }

        $global_logo_url = HearMed_Settings::get('hm_report_logo_url', '');

        ob_start(); ?>
        <div id="hm-app" class="hm-ffb">
            <div class="hm-page-header">
                <h2 class="hm-page-title">Finance Form Builder</h2>
                <p class="hm-muted" style="margin-top:4px;">Configure print templates for invoices, orders, repair dockets, and credit notes.</p>
            </div>

            <!-- Global Logo -->
            <div class="hm-ffb-logo-panel">
                <div class="hm-ffb-logo-left">
                    <?php if ($global_logo_url): ?>
                        <img src="<?php echo esc_url($global_logo_url); ?>" alt="Company Logo" class="hm-ffb-logo-img" id="hm-ffb-logo-img">
                    <?php else: ?>
                        <div class="hm-ffb-logo-placeholder" id="hm-ffb-logo-placeholder">
                            <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>
                            <span>No logo uploaded</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="hm-ffb-logo-right">
                    <div style="font-size:11px;font-weight:600;color:var(--hm-text);margin-bottom:4px;">Company Logo</div>
                    <p style="font-size:11px;color:var(--hm-muted);margin-bottom:8px;">This logo appears on all printed documents — invoices, orders, repair dockets, credit notes, reports and case histories.</p>
                    <a href="<?php echo esc_url(home_url('/admin-settings/?tab=report_layout')); ?>" class="hm-btn hm-btn--sm">
                        <?php echo $global_logo_url ? 'Change Logo in Settings' : 'Upload Logo in Settings'; ?> →
                    </a>
                </div>
            </div>

            <input type="hidden" id="hm-ffb-global-logo" value="<?php echo esc_attr($global_logo_url); ?>">

            <!-- Tab bar -->
            <div class="hm-ffb-tabs">
                <button class="hm-ffb-tab active" data-type="invoice">Invoice / Receipt</button>
                <button class="hm-ffb-tab" data-type="order">Order</button>
                <button class="hm-ffb-tab" data-type="repair">Repair Docket</button>
                <button class="hm-ffb-tab" data-type="creditnote">Credit Note</button>
            </div>

            <!-- Panels -->
            <?php foreach ($types as $type): $s = $all_settings[$type]; ?>
            <div class="hm-ffb-panel" data-type="<?php echo $type; ?>" style="<?php echo $type !== 'invoice' ? 'display:none;' : ''; ?>">
                <div class="hm-ffb-layout">
                    <!-- Builder controls -->
                    <div class="hm-ffb-controls">
                        <div class="hm-ffb-section-title">Template Settings</div>

                        <div class="hm-ffb-field">
                            <label>Company Name</label>
                            <input type="text" class="hm-input hm-ffb-input" data-key="companyName" value="<?php echo esc_attr($s['companyName'] ?? 'HearMed Acoustic Health Care Ltd'); ?>">
                        </div>
                        <div class="hm-ffb-field">
                            <label>Subtitle</label>
                            <input type="text" class="hm-input hm-ffb-input" data-key="tagline" value="<?php echo esc_attr($s['tagline'] ?? ''); ?>">
                        </div>
                        <div class="hm-ffb-row">
                            <div class="hm-ffb-field">
                                <label>Header Font</label>
                                <select class="hm-input hm-ffb-input" data-key="headerFont">
                                    <?php foreach (['Cormorant Garamond','Bricolage Grotesque','Source Sans 3','Plus Jakarta Sans','DM Sans','Outfit','Lora','Playfair Display','Nunito Sans','Work Sans'] as $f): ?>
                                    <option value="<?php echo $f; ?>" <?php selected($s['headerFont'] ?? 'Cormorant Garamond', $f); ?>><?php echo $f; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="hm-ffb-field" style="max-width:80px;">
                                <label>Size</label>
                                <select class="hm-input hm-ffb-input" data-key="headerSize">
                                    <?php foreach ([9,10,11,12,13,14,16,18,20,24] as $sz): ?>
                                    <option value="<?php echo $sz; ?>" <?php selected($s['headerSize'] ?? 18, $sz); ?>><?php echo $sz; ?>px</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="hm-ffb-row">
                            <div class="hm-ffb-field">
                                <label>Header Colour</label>
                                <input type="color" class="hm-ffb-input" data-key="headerColor" value="<?php echo esc_attr($s['headerColor'] ?? '#0BB4C4'); ?>">
                            </div>
                            <div class="hm-ffb-field">
                                <label>Accent Colour</label>
                                <input type="color" class="hm-ffb-input" data-key="accentColor" value="<?php echo esc_attr($s['accentColor'] ?? '#0BB4C4'); ?>">
                            </div>
                        </div>

                        <div class="hm-ffb-section-title" style="margin-top:16px;">Toggles</div>
                        <?php
                        $toggles = self::get_toggles($type, $s);
                        foreach ($toggles as $key => $info): ?>
                        <div class="hm-ffb-toggle-row">
                            <label><?php echo esc_html($info['label']); ?></label>
                            <label class="hm-switch">
                                <input type="checkbox" class="hm-ffb-input" data-key="<?php echo $key; ?>" <?php checked($info['value']); ?>>
                                <span class="hm-switch-slider"></span>
                            </label>
                        </div>
                        <?php endforeach; ?>

                        <div class="hm-ffb-section-title" style="margin-top:16px;">Footer</div>
                        <div class="hm-ffb-field">
                            <label>Line 1</label>
                            <input type="text" class="hm-input hm-ffb-input" data-key="footerLine1" value="<?php echo esc_attr($s['footerLine1'] ?? ''); ?>">
                        </div>
                        <div class="hm-ffb-field">
                            <label>Line 2 / Terms</label>
                            <textarea class="hm-input hm-ffb-input" data-key="footerLine2" rows="4" style="resize:vertical;font-size:11px;"><?php echo esc_textarea($s['footerLine2'] ?? ''); ?></textarea>
                        </div>

                        <?php if ($type === 'repair'): ?>
                        <div class="hm-ffb-section-title" style="margin-top:16px;">Return Address</div>
                        <div class="hm-ffb-field">
                            <label>Return Clinic</label>
                            <select class="hm-input hm-ffb-input" data-key="returnClinic">
                                <?php foreach (['Tullamore','Portlaoise','Newbridge','Portumna'] as $c): ?>
                                <option value="<?php echo $c; ?>" <?php selected($s['returnClinic'] ?? 'Tullamore', $c); ?>><?php echo $c; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <button class="hm-btn hm-btn--primary hm-ffb-save" data-type="<?php echo $type; ?>" style="margin-top:16px;width:100%;">Save Template Settings</button>
                        <div class="hm-ffb-msg" style="display:none;margin-top:8px;"></div>
                    </div>

                    <!-- Live preview -->
                    <div class="hm-ffb-preview">
                        <div class="hm-ffb-paper" data-type="<?php echo $type; ?>"></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <style>
            .hm-ffb-tabs{display:flex;gap:0;border-bottom:1px solid var(--hm-border);margin-bottom:20px;}
            .hm-ffb-tab{padding:10px 18px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:500;color:var(--hm-muted);border-bottom:3px solid transparent;font-family:inherit;}
            .hm-ffb-tab.active{color:var(--hm-teal);font-weight:700;border-bottom-color:var(--hm-teal);}
            .hm-ffb-layout{display:grid;grid-template-columns:340px 1fr;gap:20px;}
            .hm-ffb-controls{background:var(--hm-surface);border:1px solid var(--hm-border);border-radius:var(--hm-radius);padding:16px;}
            .hm-ffb-preview{background:var(--hm-surface);border:1px solid var(--hm-border);border-radius:var(--hm-radius);padding:20px;min-height:300px;}
            .hm-ffb-section-title{font-size:11px;font-weight:700;color:var(--hm-teal);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;}
            .hm-ffb-field{margin-bottom:10px;}
            .hm-ffb-field label{display:block;font-size:11px;color:var(--hm-muted);margin-bottom:3px;}
            .hm-ffb-row{display:flex;gap:10px;}
            .hm-ffb-row .hm-ffb-field{flex:1;}
            .hm-ffb-toggle-row{display:flex;align-items:center;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--hm-border);}
            .hm-ffb-toggle-row label:first-child{font-size:12px;color:var(--hm-text);}
            .hm-switch{position:relative;display:inline-block;width:36px;height:20px;}
            .hm-switch input{opacity:0;width:0;height:0;}
            .hm-switch-slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#cbd5e1;border-radius:10px;transition:.2s;}
            .hm-switch-slider:before{content:'';position:absolute;height:14px;width:14px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:.2s;}
            .hm-switch input:checked+.hm-switch-slider{background:var(--hm-teal);}
            .hm-switch input:checked+.hm-switch-slider:before{transform:translateX(16px);}
            input[type=color].hm-ffb-input{width:40px;height:28px;padding:2px;border:1px solid var(--hm-border);border-radius:4px;cursor:pointer;}

            /* Paper preview */
            .hm-ffb-preview{overflow-y:auto;max-height:calc(100vh - 160px);display:flex;justify-content:center;padding:24px 16px;}
            .hm-ffb-paper{background:#fff;width:100%;max-width:560px;min-height:700px;border-radius:6px;box-shadow:0 1px 8px rgba(0,0,0,.12);padding:28px 32px;font-size:11px;line-height:1.5;color:#1e293b;}
            .hm-ffb-paper *{box-sizing:border-box;}
            .p-header{display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:10px;border-bottom:3px solid var(--p-accent, #0BB4C4);margin-bottom:14px;}
            .p-logo{width:42px;height:42px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:16px;margin-bottom:4px;}
            .p-company{font-weight:700;}
            .p-tagline{font-size:9px;color:#94a3b8;}
            .p-meta{text-align:right;font-size:9px;color:#64748b;line-height:1.6;}
            .p-meta strong{color:#1e293b;display:block;}
            .p-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;}
            .p-box{background:#f8fafc;border-radius:6px;padding:8px 12px;}
            .p-box-label{font-size:8px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.3px;margin-bottom:3px;}
            .p-box strong{font-size:11px;display:block;}
            .p-box .sub{font-size:9px;color:#64748b;margin-top:1px;}
            .p-section-title{font-size:8px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.3px;margin-bottom:4px;margin-top:10px;}
            .p-table{width:100%;border-collapse:collapse;margin-bottom:10px;}
            .p-table th{background:#f8fafc;text-align:left;padding:4px 8px;font-size:8px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.3px;border-bottom:2px solid #e2e8f0;}
            .p-table td{padding:4px 8px;border-bottom:1px solid #f1f5f9;font-size:10px;}
            .p-table .money{text-align:right;}
            .p-table tfoot td{font-weight:600;border-bottom:none;}
            .p-table .total-row td{font-weight:700;border-top:2px solid #e2e8f0;padding-top:5px;}
            .p-badge{display:inline-block;padding:2px 10px;border-radius:4px;font-size:9px;font-weight:700;margin-top:3px;}
            .p-badge-paid{background:#d1fae5;color:#065f46;}
            .p-badge-credit{background:#fef3cd;color:#92400e;}
            .p-badge-approved{background:#dbeafe;color:#1e40af;}
            .p-badge-warranty-in{background:#dbeafe;color:#1e40af;}
            .p-badge-warranty-out{background:#fee2e2;color:#991b1b;}
            .p-payments{background:#f0fdfa;border-radius:6px;padding:8px 12px;margin-bottom:10px;}
            .p-payments h4{font-size:8px;font-weight:600;color:#065f46;text-transform:uppercase;margin-bottom:4px;}
            .p-pay-row{display:flex;justify-content:space-between;font-size:9px;padding:2px 0;border-bottom:1px solid #d1fae5;}
            .p-pay-row:last-child{border-bottom:none;font-weight:700;}
            .p-serials{font-size:9px;color:#475569;margin-bottom:10px;}
            .p-serials code{font-family:monospace;background:#f1f5f9;padding:0 3px;border-radius:2px;}
            .p-fault{border:1px solid #fde68a;background:#fffbeb;border-radius:6px;padding:8px 12px;margin-bottom:10px;}
            .p-fault-label{font-size:8px;font-weight:600;color:#92400e;text-transform:uppercase;margin-bottom:2px;}
            .p-fault p{font-size:9px;color:#78350f;}
            .p-credit-reason{background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:8px 12px;margin-bottom:10px;}
            .p-credit-reason-label{font-size:8px;font-weight:600;color:#991b1b;text-transform:uppercase;margin-bottom:2px;}
            .p-credit-reason p{font-size:9px;color:#7f1d1d;}
            .p-return{background:#f0f9ff;border:1px solid #bae6fd;border-radius:6px;padding:8px 12px;margin-bottom:10px;}
            .p-return-label{font-size:8px;font-weight:600;color:#0369a1;text-transform:uppercase;margin-bottom:3px;}
            .p-dates{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;margin-bottom:10px;}
            .p-date-box{background:#f8fafc;border-radius:6px;padding:6px 10px;text-align:center;}
            .p-date-label{font-size:8px;font-weight:600;color:#64748b;text-transform:uppercase;}
            .p-date-val{font-size:11px;font-weight:700;}
            .p-notes{border:1px dashed #e2e8f0;border-radius:6px;padding:8px 12px;margin-bottom:10px;}
            .p-notes-label{font-size:8px;font-weight:600;color:#64748b;text-transform:uppercase;margin-bottom:3px;}
            .p-notes p{font-size:9px;color:#64748b;}
            .p-approval{background:#f0fdfa;border:1px solid rgba(11,180,196,.2);border-radius:6px;padding:8px 12px;margin-bottom:10px;}
            .p-approval-label{font-size:8px;font-weight:600;color:#0BB4C4;text-transform:uppercase;margin-bottom:2px;}
            .p-exchange{background:#f0fdfa;border:1px solid rgba(11,180,196,.2);border-radius:6px;padding:8px 12px;margin-bottom:10px;}
            .p-moulds{background:#f8fafc;border-radius:6px;padding:8px 12px;margin-bottom:10px;}
            .p-moulds-label{font-size:8px;font-weight:600;color:#64748b;text-transform:uppercase;margin-bottom:3px;}
            .p-signature{margin-top:20px;border-top:1px solid #1e293b;padding-top:4px;width:60%;}
            .p-signature span{font-size:8px;color:#64748b;}
            .p-footer{margin-top:14px;padding-top:6px;border-top:1px solid #e2e8f0;text-align:center;font-size:9px;color:#94a3b8;}
            .p-hidden{display:none!important;}
            .p-divider{height:1px;background:#e2e8f0;margin:10px 0;}

            /* Logo panel */
            .hm-ffb-logo-panel{display:flex;align-items:center;gap:16px;background:var(--hm-surface);border:1px solid var(--hm-border);border-radius:var(--hm-radius);padding:14px 18px;margin-bottom:16px;}
            .hm-ffb-logo-left{flex-shrink:0;}
            .hm-ffb-logo-img{max-width:140px;max-height:56px;border:1px solid var(--hm-border);border-radius:6px;padding:4px;background:#fff;object-fit:contain;}
            .hm-ffb-logo-placeholder{display:flex;align-items:center;gap:8px;color:var(--hm-muted);font-size:11px;padding:10px 16px;border:2px dashed var(--hm-border);border-radius:8px;background:var(--hm-bg);}
            .hm-ffb-logo-right{flex:1;}
            .p-logo-img{max-width:48px;max-height:48px;border-radius:6px;object-fit:contain;margin-bottom:4px;}
        </style>

        <script>
        (function(){
            var nonce = '<?php echo wp_create_nonce("hm_nonce"); ?>';
            var ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';

            /* ── Sample data for live preview ── */
            var sampleData = {
                invoice: {
                    inv_number: 'INV-2025-0042',
                    inv_date: '15 Jan 2025',
                    status: 'Paid',
                    patient_name: 'Mary O\'Sullivan',
                    patient_dob: '14/03/1952',
                    patient_address: '12 Main Street, Tullamore, Co. Offaly',
                    patient_phone: '087 123 4567',
                    clinic_name: 'HearMed — Tullamore',
                    clinic_phone: '057 932 1234',
                    clinic_address: 'Bridge Centre, Tullamore, Co. Offaly',
                    audiologist: 'Dr. Sarah Byrne',
                    items: [
                        {desc:'Phonak Audéo L90-R (Right)',qty:1,price:'€2,150.00'},
                        {desc:'Phonak Audéo L90-R (Left)',qty:1,price:'€2,150.00'},
                        {desc:'Custom Ear Moulds (Pair)',qty:1,price:'€120.00'},
                        {desc:'Fitting & Programming',qty:1,price:'€0.00'}
                    ],
                    subtotal: '€4,420.00',
                    prsi_grant: '−€1,000.00',
                    total: '€3,420.00',
                    serial_left: 'SN-44820193',
                    serial_right: 'SN-44820194',
                    payments: [
                        {method:'Visa ****4821', amount:'€2,420.00', date:'15 Jan 2025'},
                        {method:'PRSI Grant', amount:'€1,000.00', date:'Pending'}
                    ],
                    balance: '€0.00'
                },
                order: {
                    order_number: 'ORD-2025-0018',
                    order_date: '12 Jan 2025',
                    status: 'Approved',
                    patient_name: 'John Murphy',
                    patient_dob: '22/08/1965',
                    patient_phone: '086 555 7890',
                    patient_pps: '1234567T',
                    patient_address: '5 Castle View, Portlaoise, Co. Laois',
                    clinic_name: 'HearMed — Portlaoise',
                    clinic_phone: '057 868 1234',
                    audiologist: 'Dr. Eoin Kelly',
                    items: [
                        {desc:'Oticon Real 1 miniRITE (Right)',qty:1,price:'€2,450.00'},
                        {desc:'Oticon Real 1 miniRITE (Left)',qty:1,price:'€2,450.00'},
                        {desc:'Accessories Pack',qty:1,price:'€89.00'}
                    ],
                    subtotal: '€4,989.00',
                    discount: '−€489.00',
                    total: '€4,500.00',
                    ear_mould_left: 'Open dome',
                    ear_mould_right: 'Custom mould — soft silicone',
                    special_instructions: 'Patient prefers left-start fitting. Telecoil required for church loop system.',
                    approval_by: 'Finance Dept',
                    approval_date: '13 Jan 2025'
                },
                repair: {
                    repair_ref: 'REP-2025-0007',
                    repair_date: '10 Jan 2025',
                    status: 'In Repair',
                    patient_name: 'Breda Walsh',
                    patient_phone: '085 432 1098',
                    patient_address: '8 River Walk, Newbridge, Co. Kildare',
                    clinic_name: 'HearMed — Newbridge',
                    device_make: 'Phonak',
                    device_model: 'Audéo P90-R',
                    device_serial: 'SN-33190274',
                    device_side: 'Right',
                    warranty: true,
                    warranty_exp: '15 Aug 2025',
                    fault_description: 'Intermittent feedback when volume above 60%. Receiver may need replacement. Patient reports issue started 2 weeks ago after moisture exposure.',
                    date_received: '10 Jan 2025',
                    date_sent: '11 Jan 2025',
                    date_returned: '—',
                    return_clinic: 'Tullamore'
                },
                creditnote: {
                    credit_number: 'CN-2025-0003',
                    credit_date: '20 Jan 2025',
                    original_invoice: 'INV-2025-0029',
                    original_date: '05 Dec 2024',
                    status: 'Issued',
                    patient_name: 'Patrick Dunne',
                    patient_address: '3 Abbey Road, Portumna, Co. Galway',
                    patient_phone: '091 456 7890',
                    clinic_name: 'HearMed — Portumna',
                    items: [
                        {desc:'Phonak Audéo L70-R (Right) — Returned',qty:1,price:'€1,850.00'},
                        {desc:'Restocking Fee',qty:1,price:'−€185.00'}
                    ],
                    credit_total: '€1,665.00',
                    reason: 'Patient unable to adapt to RIC style within trial period. Exchanging for custom ITE device.',
                    refund_method: 'Original payment method (Visa ****3312)',
                    exchange_model: 'Phonak Virto P90-312 Custom ITE',
                    exchange_value: '€2,100.00',
                    balance_due: '€435.00'
                }
            };

            /* ── Gather current settings from a panel ── */
            function getSettings(type) {
                var panel = document.querySelector('.hm-ffb-panel[data-type="'+type+'"]');
                if (!panel) return {};
                var s = {};
                panel.querySelectorAll('.hm-ffb-input').forEach(function(inp){
                    var k = inp.dataset.key;
                    if (inp.type === 'checkbox') s[k] = inp.checked;
                    else s[k] = inp.value;
                });
                return s;
            }

            var globalLogoUrl = (document.getElementById('hm-ffb-global-logo') || {}).value || '';

            /* ── Render preview into paper div ── */
            function renderPreview(type) {
                var paper = document.querySelector('.hm-ffb-paper[data-type="'+type+'"]');
                if (!paper) return;
                var s = getSettings(type);
                var d = sampleData[type];
                var accent   = s.accentColor || '#0BB4C4';
                var hFont    = s.headerFont  || 'Cormorant Garamond';
                var hSize    = s.headerSize  || 18;
                var hColor   = s.headerColor || '#0BB4C4';
                var company  = s.companyName || 'HearMed Acoustic Health Care Ltd';
                var tagline  = s.tagline     || '';

                paper.style.setProperty('--p-accent', accent);

                // Load Google font
                var fontId = 'gf-' + hFont.replace(/\s+/g, '-');
                if (!document.getElementById(fontId)) {
                    var lk = document.createElement('link');
                    lk.id = fontId; lk.rel = 'stylesheet';
                    lk.href = 'https://fonts.googleapis.com/css2?family=' + encodeURIComponent(hFont) + ':wght@400;600;700&display=swap';
                    document.head.appendChild(lk);
                }

                var html = '';

                /* ── HEADER ── */
                var showLogo = s.logo !== false && s.logo !== 'false' && s.logo !== undefined ? true : (s.logo === undefined);
                html += '<div class="p-header">';
                html += '<div>';
                if (showLogo) {
                    if (globalLogoUrl) {
                        html += '<img src="'+esc(globalLogoUrl)+'" class="p-logo-img" alt="Logo">';
                    } else {
                        html += '<div class="p-logo" style="background:'+accent+';">HM</div>';
                    }
                }
                html += '<div class="p-company" style="font-family:\''+hFont+'\',serif;font-size:'+hSize+'px;color:'+hColor+';">'+esc(company)+'</div>';
                if (tagline) html += '<div class="p-tagline">'+esc(tagline)+'</div>';
                html += '</div>';

                /* Meta block (right side) — type specific */
                html += '<div class="p-meta">';
                if (type === 'invoice') {
                    html += (s.invoiceMeta !== false ? '<strong>'+esc(d.inv_number)+'</strong>'+esc(d.inv_date)+'<br>' : '');
                    if (s.clinicPhone !== false) html += esc(d.clinic_phone);
                    html += '<div style="margin-top:4px;"><span class="p-badge p-badge-paid">'+esc(d.status)+'</span></div>';
                } else if (type === 'order') {
                    html += (s.orderMeta !== false ? '<strong>'+esc(d.order_number)+'</strong>'+esc(d.order_date)+'<br>' : '');
                    html += '<div style="margin-top:4px;"><span class="p-badge p-badge-approved">'+esc(d.status)+'</span></div>';
                } else if (type === 'repair') {
                    html += (s.repairRef !== false ? '<strong>'+esc(d.repair_ref)+'</strong>'+esc(d.repair_date) : '');
                } else if (type === 'creditnote') {
                    html += (s.creditMeta !== false ? '<strong>'+esc(d.credit_number)+'</strong>'+esc(d.credit_date)+'<br>' : '');
                    html += '<div style="margin-top:4px;"><span class="p-badge p-badge-credit">'+esc(d.status)+'</span></div>';
                }
                html += '</div></div>';

                /* ── PATIENT / CLINIC ROW ── */
                if (type === 'invoice') {
                    var showPat = s.patient !== false;
                    html += '<div class="p-row">';
                    html += '<div class="p-box'+(showPat?'':' p-hidden')+'"><div class="p-box-label">Patient</div><strong>'+esc(d.patient_name)+'</strong><div class="sub">DOB: '+esc(d.patient_dob)+'</div>';
                    if (s.patientAddress !== false) html += '<div class="sub">'+esc(d.patient_address)+'</div>';
                    html += '</div>';
                    html += '<div class="p-box"><div class="p-box-label">Clinic</div><strong>'+esc(d.clinic_name)+'</strong><div class="sub">'+esc(d.audiologist)+'</div>';
                    if (s.clinicPhone !== false) html += '<div class="sub">'+esc(d.clinic_phone)+'</div>';
                    html += '</div></div>';
                } else if (type === 'order') {
                    html += '<div class="p-row">';
                    html += '<div class="p-box"><div class="p-box-label">Patient</div><strong>'+esc(d.patient_name)+'</strong>';
                    if (s.showPatientDOB !== false) html += '<div class="sub">DOB: '+esc(d.patient_dob)+'</div>';
                    if (s.showPatientPhone !== false) html += '<div class="sub">'+esc(d.patient_phone)+'</div>';
                    if (s.showPatientPPS) html += '<div class="sub">PPS: '+esc(d.patient_pps)+'</div>';
                    html += '</div>';
                    html += '<div class="p-box'+(s.clinicInfo !== false ? '' : ' p-hidden')+'"><div class="p-box-label">Clinic</div><strong>'+esc(d.clinic_name)+'</strong><div class="sub">'+esc(d.audiologist)+'</div></div>';
                    html += '</div>';
                } else if (type === 'repair') {
                    html += '<div class="p-row">';
                    html += '<div class="p-box"><div class="p-box-label">Patient</div><strong>'+esc(d.patient_name)+'</strong>';
                    if (s.showPatientPhone !== false) html += '<div class="sub">'+esc(d.patient_phone)+'</div>';
                    if (s.showPatientAddress) html += '<div class="sub">'+esc(d.patient_address)+'</div>';
                    html += '</div>';
                    html += '<div class="p-box"><div class="p-box-label">Clinic</div><strong>'+esc(d.clinic_name)+'</strong></div>';
                    html += '</div>';
                } else if (type === 'creditnote') {
                    var showP = s.patient !== false;
                    html += '<div class="p-row">';
                    html += '<div class="p-box'+(showP?'':' p-hidden')+'"><div class="p-box-label">Patient</div><strong>'+esc(d.patient_name)+'</strong>';
                    if (s.patientAddress !== false) html += '<div class="sub">'+esc(d.patient_address)+'</div>';
                    html += '</div>';
                    if (s.originalInvoice !== false) html += '<div class="p-box"><div class="p-box-label">Original Invoice</div><strong>'+esc(d.original_invoice)+'</strong><div class="sub">'+esc(d.original_date)+'</div></div>';
                    html += '</div>';
                }

                /* ── TYPE-SPECIFIC CONTENT ── */
                if (type === 'invoice') {
                    /* Items table */
                    html += '<div class="p-section-title">Items</div>';
                    html += '<table class="p-table"><thead><tr><th>Description</th><th>Qty</th><th class="money">Amount</th></tr></thead><tbody>';
                    d.items.forEach(function(it){
                        html += '<tr><td>'+esc(it.desc)+'</td><td>'+it.qty+'</td><td class="money">'+esc(it.price)+'</td></tr>';
                    });
                    html += '</tbody><tfoot>';
                    html += '<tr><td colspan="2">Subtotal</td><td class="money">'+esc(d.subtotal)+'</td></tr>';
                    if (s.prsi !== false) html += '<tr><td colspan="2">PRSI Grant</td><td class="money" style="color:#059669;">'+esc(d.prsi_grant)+'</td></tr>';
                    html += '<tr class="total-row"><td colspan="2">Total Due</td><td class="money" style="color:'+accent+';">'+esc(d.total)+'</td></tr>';
                    html += '</tfoot></table>';

                    /* Serial numbers */
                    if (s.serials !== false) {
                        html += '<div class="p-serials"><strong>Serial Numbers:</strong> Left: <code>'+esc(d.serial_left)+'</code> &nbsp; Right: <code>'+esc(d.serial_right)+'</code></div>';
                    }

                    /* Payments */
                    if (s.payments !== false) {
                        html += '<div class="p-payments"><h4>Payment Breakdown</h4>';
                        d.payments.forEach(function(p){
                            html += '<div class="p-pay-row"><span>'+esc(p.method)+' — '+esc(p.date)+'</span><span>'+esc(p.amount)+'</span></div>';
                        });
                        html += '<div class="p-pay-row"><span>Balance</span><span>'+esc(d.balance)+'</span></div>';
                        html += '</div>';
                    }
                } else if (type === 'order') {
                    /* Items table */
                    html += '<div class="p-section-title">Order Items</div>';
                    html += '<table class="p-table"><thead><tr><th>Description</th><th>Qty</th><th class="money">Price</th></tr></thead><tbody>';
                    d.items.forEach(function(it){
                        html += '<tr><td>'+esc(it.desc)+'</td><td>'+it.qty+'</td><td class="money">'+esc(it.price)+'</td></tr>';
                    });
                    html += '</tbody>';
                    if (s.pricing !== false) {
                        html += '<tfoot><tr><td colspan="2">Subtotal</td><td class="money">'+esc(d.subtotal)+'</td></tr>';
                        html += '<tr><td colspan="2">Discount</td><td class="money" style="color:#059669;">'+esc(d.discount)+'</td></tr>';
                        html += '<tr class="total-row"><td colspan="2">Order Total</td><td class="money" style="color:'+accent+';">'+esc(d.total)+'</td></tr></tfoot>';
                    }
                    html += '</table>';

                    /* Ear moulds */
                    if (s.earMoulds !== false) {
                        html += '<div class="p-moulds"><div class="p-moulds-label">Ear Moulds</div>';
                        html += '<div style="font-size:10px;">Left: '+esc(d.ear_mould_left)+' &nbsp;|&nbsp; Right: '+esc(d.ear_mould_right)+'</div></div>';
                    }

                    /* Notes */
                    if (s.notes !== false) {
                        html += '<div class="p-notes"><div class="p-notes-label">Special Instructions</div><p>'+esc(d.special_instructions)+'</p></div>';
                    }

                    /* Approval */
                    if (s.approvalInfo !== false) {
                        html += '<div class="p-approval"><div class="p-approval-label">Approval</div><div style="font-size:10px;">Approved by: '+esc(d.approval_by)+' — '+esc(d.approval_date)+'</div></div>';
                    }
                } else if (type === 'repair') {
                    /* Device details */
                    if (s.device !== false) {
                        html += '<div class="p-section-title">Device</div>';
                        html += '<table class="p-table"><thead><tr><th>Make</th><th>Model</th><th>Serial</th><th>Side</th></tr></thead>';
                        html += '<tbody><tr><td>'+esc(d.device_make)+'</td><td>'+esc(d.device_model)+'</td><td>'+esc(d.device_serial)+'</td><td>'+esc(d.device_side)+'</td></tr></tbody></table>';
                    }

                    /* Warranty */
                    if (s.warranty !== false) {
                        var wClass = d.warranty ? 'p-badge-warranty-in' : 'p-badge-warranty-out';
                        var wText  = d.warranty ? 'In Warranty — Exp: '+esc(d.warranty_exp) : 'Out of Warranty';
                        html += '<div style="margin-bottom:10px;"><span class="p-badge '+wClass+'">'+wText+'</span></div>';
                    }

                    /* Fault description */
                    if (s.faultDesc !== false) {
                        html += '<div class="p-fault"><div class="p-fault-label">Fault Description</div><p>'+esc(d.fault_description)+'</p></div>';
                    }

                    /* Date tracking */
                    if (s.dateTracking !== false) {
                        html += '<div class="p-dates">';
                        html += '<div class="p-date-box"><div class="p-date-label">Received</div><div class="p-date-val">'+esc(d.date_received)+'</div></div>';
                        html += '<div class="p-date-box"><div class="p-date-label">Sent to Lab</div><div class="p-date-val">'+esc(d.date_sent)+'</div></div>';
                        html += '<div class="p-date-box"><div class="p-date-label">Returned</div><div class="p-date-val">'+esc(d.date_returned)+'</div></div>';
                        html += '</div>';
                    }

                    /* Return address */
                    if (s.showReturnAddress !== false) {
                        var rc = s.returnClinic || 'Tullamore';
                        html += '<div class="p-return"><div class="p-return-label">Return To</div><div style="font-size:10px;">HearMed — '+esc(rc)+'</div></div>';
                    }

                    /* Signature */
                    if (s.signature) {
                        html += '<div class="p-signature"><span>Patient Signature</span></div>';
                    }
                } else if (type === 'creditnote') {
                    /* Credit items */
                    html += '<div class="p-section-title">Credit Items</div>';
                    html += '<table class="p-table"><thead><tr><th>Description</th><th>Qty</th><th class="money">Amount</th></tr></thead><tbody>';
                    d.items.forEach(function(it){
                        html += '<tr><td>'+esc(it.desc)+'</td><td>'+it.qty+'</td><td class="money">'+esc(it.price)+'</td></tr>';
                    });
                    html += '</tbody><tfoot><tr class="total-row"><td colspan="2">Credit Total</td><td class="money" style="color:'+accent+';">'+esc(d.credit_total)+'</td></tr></tfoot></table>';

                    /* Reason */
                    if (s.creditReason !== false) {
                        html += '<div class="p-credit-reason"><div class="p-credit-reason-label">Reason for Credit</div><p>'+esc(d.reason)+'</p></div>';
                    }

                    /* Refund method */
                    if (s.refundMethod !== false) {
                        html += '<div class="p-notes"><div class="p-notes-label">Refund Method</div><p>'+esc(d.refund_method)+'</p></div>';
                    }

                    /* Exchange details */
                    if (s.exchangeDetails) {
                        html += '<div class="p-exchange"><div class="p-approval-label">Exchange Details</div>';
                        html += '<div style="font-size:10px;">New device: '+esc(d.exchange_model)+' — '+esc(d.exchange_value)+'</div>';
                        html += '<div style="font-size:10px;font-weight:700;margin-top:4px;">Balance due: '+esc(d.balance_due)+'</div></div>';
                    }
                }

                /* ── FOOTER ── */
                var f1 = s.footerLine1 || '';
                var f2 = s.footerLine2 || '';
                if (f1 || f2) {
                    html += '<div class="p-footer">';
                    if (f1) html += '<div>'+esc(f1)+'</div>';
                    if (f2) {
                        var lines = f2.split(/\n/);
                        lines.forEach(function(ln){
                            if (ln.trim()) html += '<div style="font-size:8px;margin-top:1px;">'+esc(ln)+'</div>';
                        });
                    }
                    html += '</div>';
                }

                paper.innerHTML = html;
            }

            function esc(v) { if (v == null) return ''; var d = document.createElement('div'); d.textContent = String(v); return d.innerHTML; }

            /* ── Tab switching ── */
            document.querySelectorAll('.hm-ffb-tab').forEach(function(tab){
                tab.addEventListener('click', function(){
                    document.querySelectorAll('.hm-ffb-tab').forEach(function(t){ t.classList.remove('active'); });
                    document.querySelectorAll('.hm-ffb-panel').forEach(function(p){ p.style.display = 'none'; });
                    tab.classList.add('active');
                    var type = tab.dataset.type;
                    document.querySelector('.hm-ffb-panel[data-type="'+type+'"]').style.display = '';
                    renderPreview(type);
                });
            });

            /* ── Live input binding ── */
            document.querySelectorAll('.hm-ffb-input').forEach(function(inp){
                var evts = ['input', 'change'];
                evts.forEach(function(evt){
                    inp.addEventListener(evt, function(){
                        var panel = inp.closest('.hm-ffb-panel');
                        if (panel) renderPreview(panel.dataset.type);
                    });
                });
            });

            /* ── Save ── */
            document.querySelectorAll('.hm-ffb-save').forEach(function(btn){
                btn.addEventListener('click', function(){
                    var type = btn.dataset.type;
                    var panel = document.querySelector('.hm-ffb-panel[data-type="'+type+'"]');
                    var settings = {};
                    panel.querySelectorAll('.hm-ffb-input').forEach(function(inp){
                        var key = inp.dataset.key;
                        if (inp.type === 'checkbox') {
                            settings[key] = inp.checked;
                        } else if (inp.type === 'number' || inp.tagName === 'SELECT' && /Size/.test(inp.dataset.key)) {
                            settings[key] = parseInt(inp.value) || inp.value;
                        } else {
                            settings[key] = inp.value;
                        }
                    });

                    var msg = panel.querySelector('.hm-ffb-msg');
                    btn.disabled = true;
                    btn.textContent = 'Saving…';

                    var fd = new FormData();
                    fd.append('action', 'hm_save_print_template');
                    fd.append('nonce', nonce);
                    fd.append('template_type', type);
                    fd.append('settings', JSON.stringify(settings));

                    fetch(ajaxUrl, {method:'POST', body:fd})
                        .then(function(r){ return r.json(); })
                        .then(function(d){
                            btn.disabled = false;
                            btn.textContent = 'Save Template Settings';
                            msg.style.display = '';
                            if (d.success) {
                                msg.className = 'hm-ffb-msg hm-notice hm-notice--success';
                                msg.textContent = 'Saved successfully.';
                            } else {
                                msg.className = 'hm-ffb-msg hm-notice hm-notice--error';
                                msg.textContent = d.data || 'Save failed.';
                            }
                            setTimeout(function(){ msg.style.display = 'none'; }, 3000);
                        })
                        .catch(function(){
                            btn.disabled = false;
                            btn.textContent = 'Save Template Settings';
                        });
                });
            });

            /* ── Initial render ── */
            ['invoice','order','repair','creditnote'].forEach(function(t){ renderPreview(t); });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    private static function get_toggles($type, $s) {
        $t = [];
        switch ($type) {
            case 'invoice':
                $t['logo']           = ['label' => 'Show Logo',            'value' => $s['logo'] ?? true];
                $t['invoiceMeta']    = ['label' => 'Invoice Details',      'value' => $s['invoiceMeta'] ?? true];
                $t['clinicPhone']    = ['label' => 'Clinic Phone',         'value' => $s['clinicPhone'] ?? true];
                $t['patient']        = ['label' => 'Patient Info',         'value' => $s['patient'] ?? true];
                $t['patientAddress'] = ['label' => 'Patient Address',      'value' => $s['patientAddress'] ?? true];
                $t['prsi']           = ['label' => 'PRSI Grant Line',      'value' => $s['prsi'] ?? true];
                $t['serials']        = ['label' => 'Serial Numbers',       'value' => $s['serials'] ?? true];
                $t['payments']       = ['label' => 'Payment Breakdown',    'value' => $s['payments'] ?? true];
                break;
            case 'order':
                $t['logo']            = ['label' => 'Show Logo',           'value' => $s['logo'] ?? true];
                $t['orderMeta']       = ['label' => 'Order Details',       'value' => $s['orderMeta'] ?? true];
                $t['showPatientDOB']  = ['label' => 'Patient DOB',         'value' => $s['showPatientDOB'] ?? true];
                $t['showPatientPhone']= ['label' => 'Patient Phone',       'value' => $s['showPatientPhone'] ?? true];
                $t['showPatientPPS']  = ['label' => 'Patient PPS',         'value' => $s['showPatientPPS'] ?? false];
                $t['clinicInfo']      = ['label' => 'Clinic Info',         'value' => $s['clinicInfo'] ?? true];
                $t['pricing']         = ['label' => 'Pricing Summary',     'value' => $s['pricing'] ?? true];
                $t['earMoulds']       = ['label' => 'Ear Mould Details',   'value' => $s['earMoulds'] ?? true];
                $t['notes']           = ['label' => 'Special Instructions','value' => $s['notes'] ?? true];
                $t['approvalInfo']    = ['label' => 'Approval Info',       'value' => $s['approvalInfo'] ?? true];
                break;
            case 'repair':
                $t['logo']              = ['label' => 'Show Logo',         'value' => $s['logo'] ?? true];
                $t['repairRef']         = ['label' => 'Repair Reference',  'value' => $s['repairRef'] ?? true];
                $t['showPatientPhone']  = ['label' => 'Patient Phone',     'value' => $s['showPatientPhone'] ?? true];
                $t['showPatientAddress']= ['label' => 'Patient Address',   'value' => $s['showPatientAddress'] ?? false];
                $t['device']            = ['label' => 'Device Details',    'value' => $s['device'] ?? true];
                $t['warranty']          = ['label' => 'Warranty Status',   'value' => $s['warranty'] ?? true];
                $t['faultDesc']         = ['label' => 'Fault Description', 'value' => $s['faultDesc'] ?? true];
                $t['dateTracking']      = ['label' => 'Date Tracking',     'value' => $s['dateTracking'] ?? true];
                $t['showReturnAddress'] = ['label' => 'Return Address',    'value' => $s['showReturnAddress'] ?? true];
                $t['signature']         = ['label' => 'Signature Line',    'value' => $s['signature'] ?? false];
                break;
            case 'creditnote':
                $t['logo']            = ['label' => 'Show Logo',          'value' => $s['logo'] ?? true];
                $t['creditMeta']      = ['label' => 'Credit Note Details','value' => $s['creditMeta'] ?? true];
                $t['originalInvoice'] = ['label' => 'Original Invoice',   'value' => $s['originalInvoice'] ?? true];
                $t['patient']         = ['label' => 'Patient Info',       'value' => $s['patient'] ?? true];
                $t['patientAddress']  = ['label' => 'Patient Address',    'value' => $s['patientAddress'] ?? true];
                $t['creditReason']    = ['label' => 'Credit Reason',      'value' => $s['creditReason'] ?? true];
                $t['refundMethod']    = ['label' => 'Refund Method',      'value' => $s['refundMethod'] ?? true];
                $t['exchangeDetails'] = ['label' => 'Exchange Details',   'value' => $s['exchangeDetails'] ?? false];
                break;
        }
        return $t;
    }
}

new HearMed_Admin_Finance_Form_Builder();
