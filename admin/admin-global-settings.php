<?php
/**
 * HearMed Admin — Global Settings
 * Shortcode: [hearmed_global_settings]
 *
 * Company-wide settings that appear on all printed documents,
 * reports, forms, and case histories.
 *
 * @package HearMed_Portal
 * @since   5.5.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Global_Settings {

    public function __construct() {
        add_shortcode('hearmed_global_settings', [$this, 'render']);
        add_action('wp_ajax_hm_upload_global_logo', [$this, 'ajax_upload_logo']);
        add_action('wp_ajax_hm_save_global_settings', [$this, 'ajax_save']);
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';
        if (!HearMed_Auth::can('manage_settings')) return '<p>Permission denied.</p>';

        $logo      = HearMed_Settings::get('hm_report_logo_url', '');
        $company   = HearMed_Settings::get('hm_global_company_name', 'HearMed Acoustic Health Care Ltd');
        $vat       = HearMed_Settings::get('hm_global_vat_number', '');
        $cro       = HearMed_Settings::get('hm_global_cro_number', '');
        $address   = HearMed_Settings::get('hm_global_registered_address', '');
        $gdpr      = HearMed_Settings::get('hm_global_gdpr_statement', '');
        $bk_freq   = HearMed_Settings::get('hm_backup_frequency_days', '7');

        ob_start(); ?>
        <div id="hm-app" class="hm-gs">
            <div class="hm-page-header">
                <a href="<?php echo esc_url(home_url('/admin-console/')); ?>" class="hm-back">← Back</a>
                <h2 class="hm-page-title">Global Settings</h2>
                <p class="hm-muted" style="margin-top:4px;">Company-wide settings used across all printed documents, reports, forms and case histories.</p>
            </div>

            <div class="hm-gs-grid">
                <!-- ═══ Left Column ═══ -->
                <div class="hm-gs-col">

                    <!-- Company Branding -->
                    <div class="hm-gs-card">
                        <div class="hm-gs-card-header">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z"/><path d="M8 12h8M12 8v8"/></svg>
                            Company Branding
                        </div>

                        <div class="hm-gs-field">
                            <label>Company Logo</label>
                            <div class="hm-gs-logo-area">
                                <?php if ($logo): ?>
                                <div id="hm-gs-logo-wrap">
                                    <img src="<?php echo esc_url($logo); ?>" alt="Logo" class="hm-gs-logo-img" id="hm-gs-logo-img">
                                </div>
                                <?php else: ?>
                                <div id="hm-gs-logo-wrap" style="display:none;">
                                    <img src="" alt="Logo" class="hm-gs-logo-img" id="hm-gs-logo-img">
                                </div>
                                <?php endif; ?>
                                <div class="hm-gs-logo-actions">
                                    <label class="hm-btn hm-btn--sm" style="cursor:pointer;">
                                        <?php echo $logo ? 'Change Logo' : 'Upload Logo'; ?>
                                        <input type="file" id="hm-gs-logo-file" accept=".png,.jpg,.jpeg,.svg,image/png,image/jpeg,image/svg+xml" style="display:none;">
                                    </label>
                                    <button type="button" class="hm-btn hm-btn--danger hm-btn--sm" id="hm-gs-logo-remove" <?php echo $logo ? '' : 'style="display:none;"'; ?>>Remove</button>
                                </div>
                                <p class="hm-gs-hint">PNG recommended with transparent background. Max 400 × 120 px.</p>
                            </div>
                        </div>

                        <div class="hm-gs-field">
                            <label>Company Name</label>
                            <input type="text" class="hm-input" id="hm-gs-company" value="<?php echo esc_attr($company); ?>">
                        </div>

                        <div class="hm-gs-field">
                            <label>Registered Address</label>
                            <textarea class="hm-input" id="hm-gs-address" rows="3" style="resize:vertical;"><?php echo esc_textarea($address); ?></textarea>
                        </div>
                    </div>

                    <!-- Legal & Compliance -->
                    <div class="hm-gs-card">
                        <div class="hm-gs-card-header">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2Z"/></svg>
                            Legal &amp; Compliance
                        </div>

                        <div class="hm-gs-row">
                            <div class="hm-gs-field">
                                <label>VAT Number</label>
                                <input type="text" class="hm-input" id="hm-gs-vat" value="<?php echo esc_attr($vat); ?>" placeholder="e.g. IE1234567T">
                            </div>
                            <div class="hm-gs-field">
                                <label>CRO Number</label>
                                <input type="text" class="hm-input" id="hm-gs-cro" value="<?php echo esc_attr($cro); ?>" placeholder="e.g. 123456">
                            </div>
                        </div>

                        <div class="hm-gs-field">
                            <label>GDPR / Privacy Statement</label>
                            <textarea class="hm-input" id="hm-gs-gdpr" rows="6" style="resize:vertical;" placeholder="Your data protection and GDPR privacy statement text…"><?php echo esc_textarea($gdpr); ?></textarea>
                            <p class="hm-gs-hint">This text can be included on consent forms, patient intake documents, and printed footer areas.</p>
                        </div>
                    </div>
                </div>

                <!-- ═══ Right Column ═══ -->
                <div class="hm-gs-col">

                    <!-- Backups -->
                    <div class="hm-gs-card">
                        <div class="hm-gs-card-header">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1m-4-8-4-4m0 0L8 8m4-4v12"/></svg>
                            Database Backups
                        </div>

                        <div class="hm-gs-backup-row">
                            <div class="hm-gs-backup-left">
                                <button class="hm-btn hm-btn--primary" id="hm-gs-backup-btn" disabled style="opacity:.6;">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="vertical-align:-2px;margin-right:4px;"><path d="M4 16v1a3 3 0 0 0 3 3h10a3 3 0 0 0 3-3v-1m-4-8-4-4m0 0L8 8m4-4v12"/></svg>
                                    Run Backup Now
                                </button>
                                <p class="hm-gs-hint" style="margin-top:6px;">Manual backup — coming soon.</p>
                            </div>
                            <div class="hm-gs-backup-right">
                                <label style="font-size:12px;font-weight:600;color:var(--hm-text);margin-bottom:6px;display:block;">Auto Backups</label>
                                <div class="hm-gs-freq-row">
                                    <span style="font-size:12px;color:var(--hm-text);">Every</span>
                                    <select class="hm-input" id="hm-gs-backup-freq" style="width:80px;">
                                        <?php foreach ([1,3,7,14,30] as $d): ?>
                                        <option value="<?php echo $d; ?>" <?php selected($bk_freq, $d); ?>><?php echo $d; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span style="font-size:12px;color:var(--hm-text);">days</span>
                                </div>
                                <p class="hm-gs-hint" style="margin-top:4px;">Automatic scheduled backups — coming soon.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Preview -->
                    <div class="hm-gs-card hm-gs-preview-card">
                        <div class="hm-gs-card-header">
                            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                            Document Header Preview
                        </div>
                        <div class="hm-gs-doc-preview" id="hm-gs-doc-preview">
                            <!-- Rendered by JS -->
                        </div>
                    </div>

                </div>
            </div>

            <!-- Save bar -->
            <div class="hm-gs-save-bar">
                <button class="hm-btn hm-btn--primary" id="hm-gs-save-btn" style="min-width:180px;">Save Global Settings</button>
                <span class="hm-gs-save-msg" id="hm-gs-save-msg"></span>
            </div>
        </div>

        <style>
            .hm-gs .hm-page-header{margin-bottom:20px;}
            .hm-gs-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
            @media(max-width:860px){.hm-gs-grid{grid-template-columns:1fr;}}
            .hm-gs-col{display:flex;flex-direction:column;gap:16px;}
            .hm-gs-card{background:var(--hm-surface);border:1px solid var(--hm-border);border-radius:var(--hm-radius);padding:18px 20px;}
            .hm-gs-card-header{font-size:13px;font-weight:700;color:var(--hm-text);display:flex;align-items:center;gap:8px;margin-bottom:14px;padding-bottom:10px;border-bottom:1px solid var(--hm-border);}
            .hm-gs-card-header svg{color:var(--hm-teal);}
            .hm-gs-field{margin-bottom:14px;}
            .hm-gs-field:last-child{margin-bottom:0;}
            .hm-gs-field label{display:block;font-size:11px;font-weight:600;color:var(--hm-muted);text-transform:uppercase;letter-spacing:.3px;margin-bottom:4px;}
            .hm-gs-row{display:flex;gap:12px;}
            .hm-gs-row .hm-gs-field{flex:1;}
            .hm-gs-hint{font-size:11px;color:var(--hm-muted);margin-top:4px;}
            /* Logo */
            .hm-gs-logo-area{display:flex;flex-direction:column;gap:8px;}
            .hm-gs-logo-img{max-width:200px;max-height:80px;border:1px solid var(--hm-border);border-radius:6px;padding:6px;background:#fff;object-fit:contain;}
            .hm-gs-logo-actions{display:flex;gap:8px;align-items:center;}
            /* Backup */
            .hm-gs-backup-row{display:flex;gap:20px;align-items:flex-start;}
            .hm-gs-backup-left{flex:0 0 auto;}
            .hm-gs-backup-right{flex:1;}
            .hm-gs-freq-row{display:flex;align-items:center;gap:8px;}
            /* Preview */
            .hm-gs-doc-preview{background:#fff;border:1px solid var(--hm-border);border-radius:6px;padding:18px 20px;min-height:100px;}
            .hm-gs-dp-header{display:flex;justify-content:space-between;align-items:flex-start;padding-bottom:8px;border-bottom:3px solid var(--hm-teal);margin-bottom:6px;}
            .hm-gs-dp-logo{max-width:42px;max-height:42px;border-radius:6px;object-fit:contain;margin-bottom:4px;}
            .hm-gs-dp-placeholder{width:42px;height:42px;border-radius:8px;background:var(--hm-teal);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:16px;margin-bottom:4px;}
            .hm-gs-dp-company{font-size:16px;font-weight:700;color:var(--hm-teal);}
            .hm-gs-dp-address{font-size:9px;color:#94a3b8;margin-top:2px;line-height:1.4;}
            .hm-gs-dp-meta{text-align:right;font-size:9px;color:#64748b;line-height:1.5;}
            .hm-gs-dp-meta strong{display:block;color:#1e293b;}
            .hm-gs-dp-legal{font-size:8px;color:#94a3b8;text-align:center;margin-top:10px;padding-top:6px;border-top:1px solid #e2e8f0;}
            /* Save */
            .hm-gs-save-bar{margin-top:20px;display:flex;align-items:center;gap:12px;}
            .hm-gs-save-msg{font-size:12px;}
        </style>

        <script>
        (function(){
            var nonce = '<?php echo wp_create_nonce("hm_nonce"); ?>';
            var ajaxUrl = '<?php echo admin_url("admin-ajax.php"); ?>';
            var currentLogo = '<?php echo esc_js($logo); ?>';

            /* ── Logo upload ── */
            var fileInput = document.getElementById('hm-gs-logo-file');
            fileInput.addEventListener('change', function(){
                var file = fileInput.files[0];
                if (!file) return;
                var fd = new FormData();
                fd.append('action', 'hm_upload_global_logo');
                fd.append('nonce', nonce);
                fd.append('file', file);
                fetch(ajaxUrl, {method:'POST', body:fd})
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        if (d.success && d.data && d.data.url) {
                            currentLogo = d.data.url;
                            var img = document.getElementById('hm-gs-logo-img');
                            img.src = currentLogo;
                            document.getElementById('hm-gs-logo-wrap').style.display = '';
                            document.getElementById('hm-gs-logo-remove').style.display = '';
                            renderPreview();
                        } else {
                            alert(d.data || 'Upload failed');
                        }
                    });
                fileInput.value = '';
            });

            document.getElementById('hm-gs-logo-remove').addEventListener('click', function(){
                if (!confirm('Remove company logo?')) return;
                currentLogo = '';
                document.getElementById('hm-gs-logo-wrap').style.display = 'none';
                this.style.display = 'none';
                renderPreview();
            });

            /* ── Live preview ── */
            function renderPreview() {
                var el = document.getElementById('hm-gs-doc-preview');
                var company = document.getElementById('hm-gs-company').value || 'Company Name';
                var address = document.getElementById('hm-gs-address').value || '';
                var vat = document.getElementById('hm-gs-vat').value || '';
                var cro = document.getElementById('hm-gs-cro').value || '';

                var html = '<div class="hm-gs-dp-header"><div>';
                if (currentLogo) {
                    html += '<img src="'+esc(currentLogo)+'" class="hm-gs-dp-logo" alt="Logo">';
                } else {
                    html += '<div class="hm-gs-dp-placeholder">HM</div>';
                }
                html += '<div class="hm-gs-dp-company">'+esc(company)+'</div>';
                if (address) {
                    var lines = address.split('\n');
                    html += '<div class="hm-gs-dp-address">';
                    lines.forEach(function(l){ if(l.trim()) html += esc(l.trim()) + '<br>'; });
                    html += '</div>';
                }
                html += '</div>';
                html += '<div class="hm-gs-dp-meta"><strong>INV-2025-0042</strong>15 Jan 2025<br>Tullamore Clinic</div>';
                html += '</div>';

                // Legal footer
                var parts = [];
                if (vat) parts.push('VAT: ' + vat);
                if (cro) parts.push('CRO: ' + cro);
                if (parts.length) {
                    html += '<div class="hm-gs-dp-legal">' + esc(parts.join('  ·  ')) + '</div>';
                }

                el.innerHTML = html;
            }

            function esc(v) { if (v == null) return ''; var d = document.createElement('div'); d.textContent = String(v); return d.innerHTML; }

            /* Bind live inputs */
            ['hm-gs-company','hm-gs-address','hm-gs-vat','hm-gs-cro'].forEach(function(id){
                var el = document.getElementById(id);
                if (el) el.addEventListener('input', renderPreview);
            });
            renderPreview();

            /* ── Save ── */
            document.getElementById('hm-gs-save-btn').addEventListener('click', function(){
                var btn = this;
                var msg = document.getElementById('hm-gs-save-msg');
                btn.disabled = true;
                btn.textContent = 'Saving…';

                var fd = new FormData();
                fd.append('action', 'hm_save_global_settings');
                fd.append('nonce', nonce);
                fd.append('logo_url', currentLogo);
                fd.append('company_name', document.getElementById('hm-gs-company').value);
                fd.append('vat_number', document.getElementById('hm-gs-vat').value);
                fd.append('cro_number', document.getElementById('hm-gs-cro').value);
                fd.append('registered_address', document.getElementById('hm-gs-address').value);
                fd.append('gdpr_statement', document.getElementById('hm-gs-gdpr').value);
                fd.append('backup_frequency', document.getElementById('hm-gs-backup-freq').value);

                fetch(ajaxUrl, {method:'POST', body:fd})
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        btn.disabled = false;
                        btn.textContent = 'Save Global Settings';
                        if (d.success) {
                            msg.style.color = '#059669';
                            msg.textContent = '✓ Saved successfully';
                        } else {
                            msg.style.color = '#dc2626';
                            msg.textContent = d.data || 'Save failed';
                        }
                        setTimeout(function(){ msg.textContent = ''; }, 3000);
                    })
                    .catch(function(){
                        btn.disabled = false;
                        btn.textContent = 'Save Global Settings';
                        msg.style.color = '#dc2626';
                        msg.textContent = 'Network error';
                    });
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /* ── AJAX: Upload Logo ── */
    public function ajax_upload_logo() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        if (empty($_FILES['file'])) { wp_send_json_error('No file uploaded'); return; }
        $file = $_FILES['file'];

        $allowed = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'svg' => 'image/svg+xml'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!isset($allowed[$ext])) {
            wp_send_json_error('Only PNG, JPG, or SVG files are allowed');
            return;
        }

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $upload_overrides = ['test_form' => false, 'mimes' => $allowed];
        $movefile = wp_handle_upload($file, $upload_overrides);

        if (!$movefile || isset($movefile['error'])) {
            wp_send_json_error($movefile['error'] ?? 'Upload failed');
            return;
        }

        HearMed_Settings::set('hm_report_logo_url', $movefile['url']);
        wp_send_json_success(['url' => $movefile['url']]);
    }

    /* ── AJAX: Save Settings ── */
    public function ajax_save() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        $fields = [
            'logo_url'           => 'hm_report_logo_url',
            'company_name'       => 'hm_global_company_name',
            'vat_number'         => 'hm_global_vat_number',
            'cro_number'         => 'hm_global_cro_number',
            'registered_address' => 'hm_global_registered_address',
            'gdpr_statement'     => 'hm_global_gdpr_statement',
            'backup_frequency'   => 'hm_backup_frequency_days',
        ];

        foreach ($fields as $post_key => $setting_key) {
            $value = isset($_POST[$post_key]) ? sanitize_textarea_field($_POST[$post_key]) : '';
            HearMed_Settings::set($setting_key, $value);
        }

        // Also sync the report company name so it stays consistent
        $company = sanitize_text_field($_POST['company_name'] ?? '');
        if ($company) {
            HearMed_Settings::set('hm_report_company_name', $company);
        }

        wp_send_json_success();
    }
}

new HearMed_Admin_Global_Settings();
