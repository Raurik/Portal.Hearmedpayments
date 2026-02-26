<?php
/**
 * HearMed Admin — Form Templates Editor
 * Shortcode: [hearmed_form_templates]
 *
 * @package HearMed_Portal
 * @since   2.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_Admin_Form_Templates {

    private $table = 'hearmed_admin.form_templates';

    public function __construct() {
        add_shortcode( 'hearmed_form_templates', [ $this, 'render' ] );
        add_action( 'wp_ajax_hm_ft_save',   [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_hm_ft_delete', [ $this, 'ajax_delete' ] );
        add_action( 'wp_ajax_hm_ft_toggle', [ $this, 'ajax_toggle' ] );
        add_action( 'wp_ajax_hm_ft_get',    [ $this, 'ajax_get' ] );
    }

    private function get_templates() {
        return HearMed_DB::get_results(
            "SELECT id, name, form_type, description,
                    requires_signature, is_active, sort_order,
                    LENGTH(COALESCE(body_html,'')) AS html_length,
                    json_array_length(COALESCE(fields_schema,'[]')::json) AS field_count
             FROM {$this->table}
             ORDER BY sort_order, name"
        ) ?: [];
    }

    private function type_label( $t ) {
        return [
            'consent'     => 'Consent',
            'clinical'    => 'Clinical',
            'intake'      => 'Intake',
            'invoice'     => 'Invoice',
            'credit_note' => 'Credit Note',
            'letter'      => 'Letter',
            'report'      => 'Report',
            'other'       => 'Other',
        ][$t] ?? ucfirst($t);
    }

    private function type_color( $t ) {
        return [
            'consent'     => '#7c3aed',
            'clinical'    => 'var(--hm-teal)',
            'intake'      => '#0e7490',
            'invoice'     => '#16a34a',
            'credit_note' => '#dc2626',
            'letter'      => '#d97706',
            'report'      => '#2563eb',
            'other'       => '#64748b',
        ][$t] ?? '#64748b';
    }

    // ─── Render ───────────────────────────────────────────────────────────

    public function render() {
        if ( ! is_user_logged_in() ) return '<p>Please log in.</p>';

        $role = HearMed_Auth::current_role();
        if ( ! in_array( $role, [ 'c_level', 'admin' ] ) ) {
            return '<div style="padding:24px;color:#ef4444;">Access restricted to admin users.</div>';
        }

        $templates = $this->get_templates();
        $nonce     = wp_create_nonce( 'hearmed_nonce' );

        ob_start(); ?>

        <style>
        /* ── Page chrome ─────────────────────────────────────────────────── */
        #hm-ft-page {
            padding: 24px 28px;
            max-width: 100%;
            font-family: inherit;
            color: #151B33;
        }
        #hm-ft-page .hm-back {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #64748b;
            text-decoration: none;
            margin-bottom: 20px;
            transition: color .15s;
        }
        #hm-ft-page .hm-back:hover { color: var(--hm-teal); }
        .ft-page-hd {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 6px;
        }
        .hm-page-title {
            font-size: 22px;
            font-weight: 600;
            color: #151B33;
            margin: 0;
            padding-left: 14px;
            border-left: 3px solid var(--hm-teal);
            line-height: 1.2;
        }
        .hm-page-subtitle {
            font-size: 13px;
            color: #64748b;
            padding-left: 17px;
            margin: 4px 0 24px;
        }

        /* ── Cards ───────────────────────────────────────────────────────── */
        .ft-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }
        .ft-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            transition: border-color .15s, box-shadow .15s, transform .1s;
            position: relative;
        }
        .ft-card:hover {
            border-color: var(--hm-teal);
            box-shadow: 0 4px 16px rgba(11,180,196,.12);
            transform: translateY(-1px);
        }
        .ft-card.inactive { opacity: .5; }
        .ft-card-top {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 12px;
        }
        .ft-card-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .ft-card-icon svg { width: 20px; height: 20px; }
        .ft-card-name {
            font-size: 15px;
            font-weight: 600;
            color: #151B33;
            margin: 0 0 3px;
        }
        .ft-card-desc {
            font-size: 12px;
            color: #64748b;
            line-height: 1.4;
        }
        .ft-card-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 12px;
        }
        .hm-badge {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 9px;
            border-radius: 20px;
        }
        .ft-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 12px;
            border-top: 1px solid #f1f5f9;
        }
        .ft-card-stats { font-size: 11px; color: #94a3b8; }
        .ft-card-acts { display: flex; gap: 4px; }
        .hm-icon-btn {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            background: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 13px;
            transition: all .15s;
        }
        .hm-icon-btn:hover { border-color: var(--hm-teal); color: var(--hm-teal); background: #f0fdfe; }
        .hm-icon-btn.hm-icon-btn--danger:hover { border-color: #fca5a5; color: #ef4444; background: #fff5f5; }
        .ft-add-card {
            background: #fafafa;
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 150px;
            transition: border-color .15s, background .15s;
            color: #94a3b8;
            font-size: 13px;
        }
        .ft-add-card:hover { border-color: var(--hm-teal); color: var(--hm-teal); background: #f0fdfe; }
        .ft-add-card .plus { font-size: 30px; line-height: 1; }

        /* ── Full-page overlay ───────────────────────────────────────────── */
        #hm-ft-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: #f8fafc;
            z-index: 99999;
            flex-direction: column;
            overflow: hidden;
        }
        #hm-ft-overlay.open { display: flex; }

        .ft-ov-topbar {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0 24px;
            height: 58px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-shrink: 0;
            box-shadow: 0 1px 4px rgba(0,0,0,.06);
        }
        .ft-ov-topbar-left { display: flex; align-items: center; gap: 14px; }
        .ft-ov-close {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: #64748b;
            cursor: pointer;
            background: none;
            border: none;
            padding: 6px 10px;
            border-radius: 6px;
            transition: background .15s, color .15s;
        }
        .ft-ov-close:hover { background: #f1f5f9; color: #151B33; }
        .ft-divider { color: #e2e8f0; font-size: 20px; }
        .ft-ov-title { font-size: 16px; font-weight: 600; color: #151B33; }
        .ft-ov-topbar-right { display: flex; align-items: center; gap: 10px; }
        .ft-saved-label { font-size: 12px; color: #059669; display: none; }

        /* ft-primary-btn styles now in hearmed-core.css as .hm-btn--add */

        .hm-tab-bar {
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0 24px;
            display: flex;
            flex-shrink: 0;
        }

        .ft-ov-body { flex: 1; overflow-y: auto; padding: 28px; }
        .ft-pane { display: none; }
        .ft-pane.active { display: block; }

        /* HTML editor */
        .ft-editor-layout {
            display: grid;
            grid-template-columns: 210px 1fr;
            gap: 20px;
            height: calc(100vh - 205px);
        }
        .ft-sidebar {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 16px;
            overflow-y: auto;
        }
        .ft-sidebar-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #94a3b8;
            margin: 0 0 10px;
        }
        .ft-ph-chip {
            display: block;
            width: 100%;
            text-align: left;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 6px 10px;
            font-size: 11px;
            font-family: monospace;
            color: #0e7490;
            cursor: pointer;
            margin-bottom: 5px;
            transition: all .15s;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .ft-ph-chip:hover { background: var(--hm-teal); color: #fff; border-color: var(--hm-teal); }
        .ft-sidebar-hint { font-size: 11px; color: #94a3b8; margin-top: 12px; line-height: 1.5; }

        .ft-editor-card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .ft-editor-bar {
            padding: 10px 16px;
            border-bottom: 1px solid #f1f5f9;
            background: #fafafa;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #64748b;
            flex-shrink: 0;
        }
        #hm-ft-html-ta {
            flex: 1;
            width: 100%;
            border: none;
            outline: none;
            resize: none;
            padding: 18px;
            font-family: 'Cascadia Code', 'Fira Code', 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.65;
            color: #151B33;
            background: #fff;
        }

        /* Fields */
        .ft-fields-card {
            max-width: 700px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 24px;
        }
        .ft-fields-card h3 { font-size: 15px; font-weight: 600; margin: 0 0 6px; }
        .ft-fields-card p { font-size: 13px; color: #64748b; margin: 0 0 20px; line-height: 1.5; }
        .ft-field-hd {
            display: grid;
            grid-template-columns: 1fr 130px 100px 36px;
            gap: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f1f5f9;
            margin-bottom: 8px;
        }
        .ft-field-hd span { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; }
        .ft-field-row {
            display: grid;
            grid-template-columns: 1fr 130px 100px 36px;
            gap: 8px;
            align-items: center;
            margin-bottom: 8px;
        }
        .ft-field-row input, .ft-field-row select {
            font-size: 13px;
            padding: 7px 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            color: #151B33;
            width: 100%;
            outline: none;
        }
        .ft-field-row input:focus, .ft-field-row select:focus {
            border-color: var(--hm-teal);
            box-shadow: 0 0 0 3px rgba(11,180,196,.08);
        }
        .hm-icon-btn--danger {
            width: 32px; height: 32px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            background: none;
            cursor: pointer;
            color: #94a3b8;
            font-size: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all .15s;
        }
        .hm-icon-btn--danger:hover { color: #ef4444; border-color: #fca5a5; background: #fff5f5; }
        .ft-add-field-btn {
            margin-top: 10px;
            width: 100%;
            background: none;
            border: 1px dashed #e2e8f0;
            border-radius: 6px;
            padding: 8px;
            font-size: 13px;
            color: #64748b;
            cursor: pointer;
            transition: all .15s;
        }
        .ft-add-field-btn:hover { border-color: var(--hm-teal); color: var(--hm-teal); background: #f0fdfe; }

        /* Preview */
        .ft-preview-card {
            max-width: 800px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 36px 44px;
            font-size: 14px;
            line-height: 1.7;
            min-height: 400px;
        }

        /* New modal form fields */
        .hm-form-group { margin-bottom: 16px; }
        .hm-form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .hm-form-group input[type=text],
        .hm-form-group input[type=number],
        .hm-form-group select {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 13px;
            color: #151B33;
            outline: none;
        }
        .hm-form-group input:focus,
        .hm-form-group select:focus {
            border-color: var(--hm-teal);
            box-shadow: 0 0 0 3px rgba(11,180,196,.08);
        }
        .ft-check-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            cursor: pointer;
        }
        .ft-check-row input { width: 16px; height: 16px; accent-color: var(--hm-teal); cursor: pointer; }
        </style>

        <!-- ═══ PAGE ═════════════════════════════════════════════════════ -->
        <div id="hm-ft-page">

            <a href="<?php echo esc_url( home_url('/admin-console/') ); ?>" class="hm-back">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                Back to Admin Console
            </a>

            <div class="ft-page-hd">
                <div>
                    <h2 class="hm-page-title">Form Templates</h2>
                    <p class="hm-page-subtitle">Click any card to edit its layout, HTML and fields</p>
                </div>
                <button class="hm-btn hm-btn--add" onclick="hmFT.openNew()">+ New Template</button>
            </div>

            <div class="ft-grid">
                <?php foreach ( $templates as $t ) :
                    $color  = $this->type_color( $t->form_type );
                    $label  = $this->type_label( $t->form_type );
                    $fields = intval( $t->field_count ?? 0 );
                    $chars  = intval( $t->html_length ?? 0 );
                    $bg     = $color . '18';
                ?>
                <div class="ft-card<?php echo ! $t->is_active ? ' inactive' : ''; ?>"
                     onclick="hmFT.openEditor(<?php echo (int)$t->id; ?>)">
                    <div class="ft-card-top">
                        <div class="ft-card-icon" style="background:<?php echo esc_attr($bg); ?>;">
                            <?php if ( $t->form_type === 'consent' ) : ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="<?php echo esc_attr($color); ?>" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <?php elseif ( $t->form_type === 'clinical' ) : ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="<?php echo esc_attr($color); ?>" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                            <?php else : ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="<?php echo esc_attr($color); ?>" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                            <?php endif; ?>
                        </div>
                        <div>
                            <div class="ft-card-name"><?php echo esc_html( $t->name ); ?></div>
                            <div class="ft-card-desc"><?php echo esc_html( $t->description ?: '—' ); ?></div>
                        </div>
                    </div>
                    <div class="ft-card-badges">
                        <span class="hm-badge" style="background:<?php echo esc_attr($bg); ?>;color:<?php echo esc_attr($color); ?>;"><?php echo esc_html($label); ?></span>
                        <?php if ( $t->requires_signature ) : ?><span class="hm-badge" style="background:#fef3c7;color:#d97706;">✍ Signature</span><?php endif; ?>
                        <?php if ( ! $t->is_active ) : ?><span class="hm-badge" style="background:#f1f5f9;color:#64748b;">Inactive</span><?php endif; ?>
                    </div>
                    <div class="ft-card-footer">
                        <span class="ft-card-stats"><?php echo $fields; ?> field<?php echo $fields !== 1 ? 's' : ''; ?> &nbsp;·&nbsp; <?php echo number_format($chars); ?> chars</span>
                        <div class="ft-card-acts" onclick="event.stopPropagation()">
                            <button class="hm-icon-btn" onclick="hmFT.openEditor(<?php echo (int)$t->id; ?>)" title="Edit">✏</button>
                            <button class="hm-icon-btn" onclick="hmFT.toggle(<?php echo (int)$t->id; ?>,<?php echo $t->is_active?'0':'1'; ?>)" title="Toggle"><?php echo $t->is_active ? '⏸' : '▶'; ?></button>
                            <button class="hm-icon-btn hm-icon-btn--danger" onclick="hmFT.del(<?php echo (int)$t->id; ?>,'<?php echo esc_js($t->name); ?>')" title="Delete">×</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="ft-add-card" onclick="hmFT.openNew()">
                    <div class="plus">+</div>
                    <div>Add New Template</div>
                </div>
            </div>
        </div>

        <!-- ═══ FULL-PAGE EDITOR OVERLAY ════════════════════════════════ -->
        <div id="hm-ft-overlay">

            <div class="ft-ov-topbar">
                <div class="ft-ov-topbar-left">
                    <button class="ft-ov-close" onclick="hmFT.closeEditor()">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 5l-7 7 7 7"/></svg>
                        All Templates
                    </button>
                    <span class="ft-divider">|</span>
                    <span class="ft-ov-title" id="hm-ft-ov-title">Loading…</span>
                </div>
                <div class="ft-ov-topbar-right">
                    <span class="ft-saved-label" id="hm-ft-saved-lbl">✓ Saved</span>
                    <button class="hm-btn hm-btn--add" id="hm-ft-save-btn" onclick="hmFT.save()">Save Template</button>
                </div>
            </div>

            <div class="hm-tab-bar">
                <button class="hm-tab hm-tab--active" onclick="hmFT.tab('html',this)">HTML Body</button>
                <button class="hm-tab" onclick="hmFT.tab('fields',this)">Fields</button>
                <button class="hm-tab" onclick="hmFT.tab('preview',this)">Preview</button>
            </div>

            <div class="ft-ov-body">
                <input type="hidden" id="hm-ft-cur-id">

                <!-- HTML Pane -->
                <div class="ft-pane active" id="hm-ft-pane-html">
                    <div class="ft-editor-layout">
                        <div class="ft-sidebar">
                            <div class="ft-sidebar-title">Placeholders</div>
                            <?php
                            $phs = [
                                '{{patient_full_name}}' => 'Patient full name',
                                '{{patient_dob}}'       => 'Date of birth',
                                '{{patient_number}}'    => 'H-number',
                                '{{today_date}}'        => "Today's date",
                                '{{clinic_name}}'       => 'Clinic name',
                                '{{clinic_email}}'      => 'Clinic email',
                                '{{clinic_phone}}'      => 'Clinic phone',
                                '{{clinic_address}}'    => 'Clinic address',
                                '{{invoice_number}}'    => 'Invoice number',
                                '{{invoice_date}}'      => 'Invoice date',
                                '{{invoice_total}}'     => 'Invoice total €',
                                '{{invoice_items}}'     => 'Line items table',
                                '{{prsi_amount}}'       => 'PRSI grant amount',
                                '{{balance_due}}'       => 'Balance due',
                                '{{payment_method}}'    => 'Payment method',
                                '{{credit_note_number}}'=> 'Credit note number',
                                '{{credit_amount}}'     => 'Credit amount €',
                                '{{credit_reason}}'     => 'Reason for credit',
                                '{{order_number}}'      => 'Order number',
                                '{{signature}}'         => '✍ Signature pad',
                            ];
                            foreach ( $phs as $ph => $hint ) : ?>
                            <button class="ft-ph-chip" onclick="hmFT.insertPH('<?php echo esc_js($ph); ?>')" title="<?php echo esc_attr($hint); ?>"><?php echo esc_html($ph); ?></button>
                            <?php endforeach; ?>
                            <div class="ft-sidebar-hint">Click any placeholder to insert it at your cursor position in the editor.</div>
                        </div>
                        <div class="ft-editor-card">
                            <div class="ft-editor-bar">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                                HTML — write the form layout and content here
                            </div>
                            <textarea id="hm-ft-html-ta" spellcheck="false" placeholder="<h2>Form Title</h2>&#10;<p>Patient: {{patient_full_name}}</p>&#10;&#10;<p>Your form content here...</p>&#10;&#10;{{signature}}"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Fields Pane -->
                <div class="ft-pane" id="hm-ft-pane-fields">
                    <div class="ft-fields-card">
                        <h3>Form Fields</h3>
                        <p>Structured fields appended below the HTML. Use for case history questions, checkboxes, etc. Consent and signature go directly in the HTML above.</p>
                        <div class="ft-field-hd">
                            <span>Label</span><span>Type</span><span>Required</span><span></span>
                        </div>
                        <div id="hm-ft-fields-list"></div>
                        <button class="ft-add-field-btn" onclick="hmFT.addField()">+ Add Field</button>
                    </div>
                </div>

                <!-- Preview Pane -->
                <div class="ft-pane" id="hm-ft-pane-preview">
                    <p style="font-size:12px;color:#94a3b8;margin-bottom:14px;">Sample data substituted for placeholders. Signature pad shown as placeholder box.</p>
                    <div class="ft-preview-card" id="hm-ft-preview-out"></div>
                </div>

            </div>
        </div>

        <!-- ═══ NEW TEMPLATE MODAL ═══════════════════════════════════════ -->
        <div class="hm-modal-bg" id="hm-ft-new-modal">
            <div class="hm-modal hm-modal--md">
                <div class="hm-modal-hd">
                    <h3>New Form Template</h3>
                    <button class="hm-close" onclick="hmFT.closeNew()">&times;</button>
                </div>
                <div class="hm-modal-body">
                    <div class="hm-form-group">
                        <label>Template Name *</label>
                        <input type="text" id="hm-ft-nw-name" placeholder="e.g. GDPR Consent Form">
                    </div>
                    <div class="hm-form-group">
                        <label>Type</label>
                        <select id="hm-ft-nw-type">
                            <option value="consent">Consent</option>
                            <option value="clinical">Clinical</option>
                            <option value="intake">Intake</option>
                            <option value="invoice">Invoice</option>
                            <option value="credit_note">Credit Note</option>
                            <option value="letter">Letter</option>
                            <option value="report">Report</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="hm-form-group">
                        <label>Description <span style="font-weight:400;text-transform:none;">(shown in picker)</span></label>
                        <input type="text" id="hm-ft-nw-desc" placeholder="Short description for staff">
                    </div>
                    <div class="hm-form-group">
                        <label class="ft-check-row">
                            <input type="checkbox" id="hm-ft-nw-sig" checked>
                            Requires patient signature
                        </label>
                    </div>
                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn" onclick="hmFT.closeNew()">Cancel</button>
                    <button class="hm-btn hm-btn--add" onclick="hmFT.createNew()" id="hm-ft-nw-btn">Create &amp; Edit →</button>
                </div>
            </div>
        </div>

        <script>
        var hmFT = (function(){
            var url    = '<?php echo admin_url('admin-ajax.php'); ?>';
            var nonce  = '<?php echo esc_js($nonce); ?>';
            var fields = [];

            function openEditor(id) {
                document.getElementById('hm-ft-cur-id').value = id;
                document.getElementById('hm-ft-ov-title').textContent = 'Loading…';
                document.getElementById('hm-ft-overlay').classList.add('open');
                document.body.style.overflow = 'hidden';
                tab('html', document.querySelector('.hm-tab'));

                jQuery.post(url, {action:'hm_ft_get',nonce:nonce,id:id}, function(r){
                    if (!r.success) { alert('Could not load: ' + r.data); return; }
                    var t = r.data;
                    document.getElementById('hm-ft-ov-title').textContent = t.name;
                    document.getElementById('hm-ft-html-ta').value = t.body_html || '';
                    fields = t.fields_schema || [];
                    renderFields();
                });
            }

            function closeEditor() {
                document.getElementById('hm-ft-overlay').classList.remove('open');
                document.body.style.overflow = '';
                location.reload();
            }

            function tab(name, btn) {
                document.querySelectorAll('.ft-pane').forEach(function(p){p.classList.remove('active');});
                document.querySelectorAll('.hm-tab').forEach(function(b){b.classList.remove('hm-tab--active');});
                document.getElementById('hm-ft-pane-'+name).classList.add('active');
                if (btn) btn.classList.add('hm-tab--active');
                if (name==='preview') renderPreview();
            }

            function save() {
                var id   = document.getElementById('hm-ft-cur-id').value;
                var html = document.getElementById('hm-ft-html-ta').value;
                var btn  = document.getElementById('hm-ft-save-btn');
                var lbl  = document.getElementById('hm-ft-saved-lbl');
                btn.textContent = 'Saving…'; btn.disabled = true;
                collectFields();
                jQuery.post(url, {
                    action:'hm_ft_save', nonce:nonce, id:id,
                    body_html:html, fields_schema:JSON.stringify(fields)
                }, function(r){
                    btn.textContent = 'Save Template'; btn.disabled = false;
                    if (r.success) { lbl.style.display='inline'; setTimeout(function(){lbl.style.display='none';},2500); }
                    else alert(r.data||'Error saving.');
                });
            }

            function insertPH(ph) {
                var ta = document.getElementById('hm-ft-html-ta');
                var s = ta.selectionStart, e = ta.selectionEnd;
                ta.value = ta.value.slice(0,s) + ph + ta.value.slice(e);
                ta.selectionStart = ta.selectionEnd = s + ph.length;
                ta.focus();
            }

            function renderPreview() {
                var html = document.getElementById('hm-ft-html-ta').value;
                var d = new Date();
                var ds = d.getDate()+' '+['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][d.getMonth()]+' '+d.getFullYear();
                var map = {
                    '{{patient_full_name}}':'Mary Murphy',
                    '{{patient_dob}}':'15/03/1958',
                    '{{patient_number}}':'C-0042',
                    '{{today_date}}':ds,
                    '{{clinic_name}}':'HearMed Tullamore',
                    '{{clinic_email}}':'tullamore@hearmed.ie',
                    '{{clinic_phone}}':'057 123 4567',
                    '{{clinic_address}}':'12 Main Street, Tullamore, Co. Offaly',
                    '{{signature}}':'<div style="border:2px solid #e2e8f0;border-radius:8px;padding:16px;text-align:center;color:#94a3b8;font-size:13px;margin:16px 0;background:#fafafa;">[Patient signature pad]</div>'
                };
                Object.entries(map).forEach(function(e){ html = html.split(e[0]).join(e[1]); });
                document.getElementById('hm-ft-preview-out').innerHTML = html;
            }

            function renderFields() {
                var list = document.getElementById('hm-ft-fields-list');
                list.innerHTML = '';
                fields.forEach(function(f,i){
                    var row = document.createElement('div');
                    row.className = 'ft-field-row';
                    row.dataset.idx = i;
                    var types = ['text','textarea','select','checkbox','number','date'];
                    row.innerHTML =
                        '<input type="text" class="ffl" value="'+_e(f.label)+'" placeholder="Field label">' +
                        '<select class="fft">'+types.map(function(t){return '<option'+(f.type===t?' selected':'')+'>'+t+'</option>';}).join('')+'</select>' +
                        '<select class="ffr"><option value="0"'+(!f.required?' selected':'')+'>Optional</option><option value="1"'+(f.required?' selected':'')+'>Required</option></select>' +
                        '<button class="hm-icon-btn hm-icon-btn--danger" onclick="hmFT.removeField('+i+')">×</button>';
                    list.appendChild(row);
                });
            }

            function collectFields() {
                fields = [];
                document.querySelectorAll('#hm-ft-fields-list .ft-field-row').forEach(function(row,i){
                    var lbl = row.querySelector('.ffl').value.trim();
                    if (!lbl) return;
                    fields.push({
                        id: lbl.toLowerCase().replace(/[^a-z0-9]+/g,'_')+'_'+i,
                        label: lbl,
                        type: row.querySelector('.fft').value,
                        required: row.querySelector('.ffr').value==='1'
                    });
                });
            }

            function addField() {
                collectFields();
                fields.push({id:'field_'+fields.length,label:'',type:'text',required:false});
                renderFields();
                var rows = document.querySelectorAll('#hm-ft-fields-list .ft-field-row');
                if (rows.length) rows[rows.length-1].querySelector('.ffl').focus();
            }

            function removeField(i) { collectFields(); fields.splice(i,1); renderFields(); }

            function openNew() {
                document.getElementById('hm-ft-nw-name').value = '';
                document.getElementById('hm-ft-nw-type').value = 'consent';
                document.getElementById('hm-ft-nw-desc').value = '';
                document.getElementById('hm-ft-nw-sig').checked = true;
                document.getElementById('hm-ft-new-modal').classList.add('open');
                document.getElementById('hm-ft-nw-name').focus();
            }

            function closeNew() { document.getElementById('hm-ft-new-modal').classList.remove('open'); }

            function createNew() {
                var name = document.getElementById('hm-ft-nw-name').value.trim();
                if (!name) { alert('Name is required.'); return; }
                var btn = document.getElementById('hm-ft-nw-btn');
                btn.textContent = 'Creating…'; btn.disabled = true;
                jQuery.post(url, {
                    action:'hm_ft_save', nonce:nonce, id:'', meta_only:1,
                    name:name,
                    form_type:document.getElementById('hm-ft-nw-type').value,
                    description:document.getElementById('hm-ft-nw-desc').value,
                    requires_signature:document.getElementById('hm-ft-nw-sig').checked?1:0,
                }, function(r){
                    btn.textContent = 'Create & Edit →'; btn.disabled = false;
                    if (r.success) { closeNew(); openEditor(r.data.id); }
                    else alert(r.data||'Error.');
                });
            }

            function toggle(id, active) {
                jQuery.post(url, {action:'hm_ft_toggle',nonce:nonce,id:id,active:active}, function(r){
                    if (r.success) location.reload(); else alert(r.data||'Error.');
                });
            }

            function del(id, name) {
                if (!confirm('Delete "'+name+'"?\nThis cannot be undone.')) return;
                jQuery.post(url, {action:'hm_ft_delete',nonce:nonce,id:id}, function(r){
                    if (r.success) location.reload(); else alert(r.data||'Error.');
                });
            }

            function _e(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

            return {openEditor,closeEditor,tab,save,insertPH,addField,removeField,openNew,closeNew,createNew,toggle,del};
        })();
        </script>
        <?php return ob_get_clean();
    }

    // ─── AJAX: get ────────────────────────────────────────────────────────

    public function ajax_get() {
        check_ajax_referer( 'hearmed_nonce', 'nonce' );
        $id  = intval( $_POST['id'] ?? 0 );
        $row = HearMed_DB::get_row( "SELECT * FROM {$this->table} WHERE id = \$1", [ $id ] );
        if ( ! $row ) wp_send_json_error( 'Not found.' );
        wp_send_json_success([
            'id'            => (int) $row->id,
            'name'          => $row->name,
            'body_html'     => $row->body_html,
            'fields_schema' => json_decode( $row->fields_schema ?: '[]', true ),
            'requires_signature' => (bool) $row->requires_signature,
        ]);
    }

    // ─── AJAX: save ───────────────────────────────────────────────────────

    public function ajax_save() {
        check_ajax_referer( 'hearmed_nonce', 'nonce' );
        if ( ! in_array( HearMed_Auth::current_role(), [ 'c_level', 'admin' ] ) ) wp_send_json_error( 'Access denied.' );

        $id        = intval( $_POST['id'] ?? 0 );
        $meta_only = ! empty( $_POST['meta_only'] );
        $data      = [ 'updated_at' => date('Y-m-d H:i:s') ];

        if ( isset($_POST['name']) )              $data['name']               = sanitize_text_field( $_POST['name'] );
        if ( isset($_POST['form_type']) )         $data['form_type']           = sanitize_key( $_POST['form_type'] );
        if ( isset($_POST['description']) )       $data['description']         = sanitize_text_field( $_POST['description'] );
        if ( isset($_POST['requires_signature'])) $data['requires_signature']  = ! empty( $_POST['requires_signature'] );

        if ( ! $meta_only ) {
            if ( isset($_POST['body_html']) )
                $data['body_html'] = wp_kses_post( stripslashes( $_POST['body_html'] ) );
            if ( isset($_POST['fields_schema']) ) {
                $raw = json_decode( stripslashes( $_POST['fields_schema'] ), true );
                if ( is_array($raw) ) {
                    $clean = [];
                    foreach ($raw as $f) {
                        $clean[] = [
                            'id'       => sanitize_key( $f['id'] ?? '' ),
                            'label'    => sanitize_text_field( $f['label'] ?? '' ),
                            'type'     => sanitize_key( $f['type'] ?? 'text' ),
                            'required' => ! empty( $f['required'] ),
                        ];
                    }
                    $data['fields_schema'] = json_encode($clean);
                }
            }
        }

        if ( $id ) {
            HearMed_DB::update( $this->table, $data, [ 'id' => $id ] );
            wp_send_json_success( [ 'id' => $id ] );
        } else {
            $data['body_html']     = $data['body_html']     ?? '<p>Edit your form content here.</p>{{signature}}';
            $data['fields_schema'] = $data['fields_schema'] ?? '[]';
            $data['is_active']     = true;
            $data['created_at']    = date('Y-m-d H:i:s');
            $new_id = HearMed_DB::insert( $this->table, $data );
            if ( ! $new_id ) wp_send_json_error( 'Failed to create.' );
            wp_send_json_success( [ 'id' => $new_id ] );
        }
    }

    // ─── AJAX: toggle ─────────────────────────────────────────────────────

    public function ajax_toggle() {
        check_ajax_referer( 'hearmed_nonce', 'nonce' );
        if ( ! in_array( HearMed_Auth::current_role(), [ 'c_level', 'admin' ] ) ) wp_send_json_error('Access denied.');
        HearMed_DB::update( $this->table, ['is_active' => !empty($_POST['active'])], ['id' => intval($_POST['id'])] );
        wp_send_json_success();
    }

    // ─── AJAX: delete ─────────────────────────────────────────────────────

    public function ajax_delete() {
        check_ajax_referer( 'hearmed_nonce', 'nonce' );
        if ( ! in_array( HearMed_Auth::current_role(), [ 'c_level', 'admin' ] ) ) wp_send_json_error('Access denied.');
        $id = intval( $_POST['id'] ?? 0 );
        if ( $id ) HearMed_DB::delete( $this->table, ['id' => $id] );
        wp_send_json_success();
    }
}

new HearMed_Admin_Form_Templates();