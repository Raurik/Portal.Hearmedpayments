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

        ob_start(); ?>
        <div id="hm-app" class="hm-ffb">
            <div class="hm-page-header">
                <h2 class="hm-page-title">Finance Form Builder</h2>
                <p class="hm-muted" style="margin-top:4px;">Configure print templates for invoices, orders, repair dockets, and credit notes.</p>
            </div>

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
                            <label>Line 2</label>
                            <input type="text" class="hm-input hm-ffb-input" data-key="footerLine2" value="<?php echo esc_attr($s['footerLine2'] ?? ''); ?>">
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

                    <!-- Preview placeholder -->
                    <div class="hm-ffb-preview">
                        <div class="hm-muted" style="text-align:center;padding:40px;">
                            Save settings, then use the Print button on any <?php echo $type === 'creditnote' ? 'credit note' : $type; ?> to see the live output.
                        </div>
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
        </style>

        <script>
        (function(){
            var nonce = '<?php echo wp_create_nonce("hm_nonce"); ?>';
            var ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';

            // Tab switching
            document.querySelectorAll('.hm-ffb-tab').forEach(function(tab){
                tab.addEventListener('click', function(){
                    document.querySelectorAll('.hm-ffb-tab').forEach(function(t){ t.classList.remove('active'); });
                    document.querySelectorAll('.hm-ffb-panel').forEach(function(p){ p.style.display = 'none'; });
                    tab.classList.add('active');
                    document.querySelector('.hm-ffb-panel[data-type="'+tab.dataset.type+'"]').style.display = '';
                });
            });

            // Save
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
