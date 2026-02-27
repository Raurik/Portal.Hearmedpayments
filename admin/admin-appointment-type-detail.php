<?php
/**
 * HearMed Admin — Appointment Type Detail
 * Shortcode: [hearmed_appointment_type_detail]
 *
 * Individual appointment-type view with: details editing, outcome templates,
 * assignable staff, and reminder settings.
 *
 * @package HearMed_Portal
 * @since   5.2.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Appointment_Type_Detail {

    private $svc_table  = 'hearmed_reference.services';
    private $out_table  = 'hearmed_core.outcome_templates';
    private $sas_table  = 'hearmed_reference.service_assignable_staff';

    public function __construct() {
        add_shortcode('hearmed_appointment_type_detail', [$this, 'render']);
        // AJAX endpoints
        add_action('wp_ajax_hm_admin_get_service_detail',       [$this, 'ajax_get_detail']);
        add_action('wp_ajax_hm_admin_save_service_detail',      [$this, 'ajax_save_detail']);
        add_action('wp_ajax_hm_admin_save_outcome_template',    [$this, 'ajax_save_outcome']);
        add_action('wp_ajax_hm_admin_delete_outcome_template',  [$this, 'ajax_delete_outcome']);
        add_action('wp_ajax_hm_admin_save_assignable_staff',    [$this, 'ajax_save_staff']);
    }

    /* ═══════════════════════════════════════════════════════
       RENDER
       ═══════════════════════════════════════════════════════ */
    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $service_id = intval($_GET['id'] ?? 0);
        if (!$service_id) return '<p>No appointment type specified.</p>';

        $svc = HearMed_DB::get_row("SELECT * FROM {$this->svc_table} WHERE id = $service_id");
        if (!$svc) return '<p>Appointment type not found.</p>';

        // Outcomes
        $outcomes = HearMed_DB::get_results(
            "SELECT * FROM {$this->out_table} WHERE service_id = $service_id ORDER BY outcome_name"
        ) ?: [];

        // Assignable staff
        $assigned = HearMed_DB::get_results(
            "SELECT sas.staff_id, s.first_name, s.last_name, s.role
             FROM {$this->sas_table} sas
             JOIN hearmed_reference.staff s ON s.id = sas.staff_id
             WHERE sas.service_id = $service_id
             ORDER BY s.first_name, s.last_name"
        ) ?: [];

        // All active staff
        $all_staff = HearMed_DB::get_results(
            "SELECT id, first_name, last_name, role
             FROM hearmed_reference.staff
             WHERE is_active = true
             ORDER BY first_name, last_name"
        ) ?: [];

        // All services (for follow-up multi-select)
        $all_services = HearMed_DB::get_results(
            "SELECT id, service_name FROM {$this->svc_table} WHERE is_active = true ORDER BY service_name"
        ) ?: [];

        // SMS templates
        $sms_templates = HearMed_DB::get_results(
            "SELECT id, template_name FROM hearmed_communication.sms_templates ORDER BY template_name"
        ) ?: [];

        $colour      = $svc->service_color ?? '#3B82F6';
        $text_colour = $svc->text_color ?? '#FFFFFF';
        $name_colour = $svc->name_color ?? '#FFFFFF';
        $time_colour = $svc->time_color ?? '#38bdf8';
        $meta_colour = $svc->meta_color ?? '#38bdf8';
        $badge_bg    = $svc->badge_bg_color ?? '#ffffff33';
        $badge_text  = $svc->badge_text_color ?? '#FFFFFF';
        $border_colour = $svc->border_color ?? '';
        $assigned_ids = array_map(function($a) { return (int)$a->staff_id; }, $assigned);

        ob_start();
        ?>
        <style>
        /* ── Appointment-type-detail — page-specific ── */
        #hm-app .hm-card-grid      { display:grid; gap:16px; margin-bottom:16px; }
        #hm-app .hm-card-grid--3   { grid-template-columns:1fr 1fr 1fr; }
        /* Settings rows */
        #hm-app .hm-srow  { display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; }
        #hm-app .hm-slbl  { font-size:13px; color:#334155; flex:1; }
        #hm-app .hm-sval  { width:150px; flex-shrink:0; }
        /* Inputs */
        #hm-app .hm-inp,
        #hm-app .hm-dd    { width:100%; padding:5px 8px; font-size:12px; border:1px solid #e2e8f0; border-radius:5px; color:#0f172a; background:#fff; box-sizing:border-box; }
        /* Colour picker */
        #hm-app .hm-color-box         { width:44px; height:28px; border:1px solid #e2e8f0; border-radius:5px; padding:1px; cursor:pointer; }
        #hm-app .hm-color-row .hm-sval { width:auto; }
        /* Checkboxes */
        #hm-app .hm-day-check       { display:inline-flex; align-items:center; gap:8px; font-size:13px; color:#334155; cursor:pointer; }
        #hm-app .hm-day-check input  { display:none !important; }
        #hm-app .hm-check            { width:16px; height:16px; border-radius:4px; border:1.5px solid #cbd5e1; background:#fff; position:relative; flex-shrink:0; transition:all .15s ease; }
        #hm-app .hm-check::after     { content:none !important; display:none !important; }
        #hm-app .hm-day-check input:checked + .hm-check { background:var(--hm-teal); border-color:var(--hm-teal); }
        #hm-app .hm-day-check input:checked + .hm-check::after {
            content:"" !important; display:block !important; position:absolute; left:4px; top:1px; width:5px; height:9px;
            border:solid #fff; border-width:0 2px 2px 0; transform:rotate(45deg);
        }

        /* ── Colour section groups ── */
        #hm-app .hm-colour-group     { margin-bottom:16px; }
        #hm-app .hm-colour-group-hd  { font-size:11px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.5px; margin-bottom:8px; padding-bottom:4px; border-bottom:1px solid #f1f5f9; }

        /* ── Preview card — matches calendar card exactly ── */
        #hm-app .hm-appt-preview-wrap { display:flex; flex-direction:column; align-items:center; gap:16px; padding:8px 0; }
        #hm-app .hm-preview-style-bar { display:flex; gap:4px; background:#f1f5f9; border-radius:6px; padding:3px; }
        #hm-app .hm-preview-style-btn { padding:4px 10px; font-size:11px; font-weight:600; border:none; border-radius:4px; cursor:pointer; background:transparent; color:#64748b; transition:all .15s; }
        #hm-app .hm-preview-style-btn.active { background:#fff; color:#0f172a; box-shadow:0 1px 3px rgba(0,0,0,.1); }
        #hm-app .hm-preview-card-wrap { width:100%; max-width:260px; }
        #hm-app .hm-prev-card { border-radius:8px; overflow:hidden; box-shadow:0 2px 12px rgba(15,23,42,.08); display:flex; flex-direction:column; min-height:110px; position:relative; transition:all .2s; }
        #hm-app .hm-prev-banner { padding:4px 10px; font-size:10px; font-weight:700; color:#fff; transition:background .2s; display:flex; align-items:center; }
        #hm-app .hm-prev-inner { padding:8px 10px; display:flex; flex-direction:column; gap:3px; }
        #hm-app .hm-prev-svc { font-size:11px; font-weight:700; opacity:.85; }
        #hm-app .hm-prev-pt { font-size:13px; font-weight:700; }
        #hm-app .hm-prev-tm { font-size:12px; font-weight:600; }
        #hm-app .hm-prev-badges { display:flex; gap:4px; margin:1px 0; }
        #hm-app .hm-prev-badge { height:17px; min-width:17px; padding:0 5px; border-radius:4px; font-size:9px; font-weight:700; display:inline-flex; align-items:center; justify-content:center; }
        #hm-app .hm-prev-meta { font-size:11px; }
        #hm-app .hm-prev-label { font-size:10px; color:#94a3b8; text-align:center; margin-top:4px; }

        /* Appointment preview */
        #hm-app .hm-appt-preview-card { width:100%; max-width:280px; border-radius:8px; overflow:hidden; box-shadow:0 2px 12px rgba(15,23,42,.08); display:flex; flex-direction:column; }
        #hm-app .hm-appt-outcome-banner { padding:8px 14px; font-size:13px; font-weight:600; color:#fff; background:#22c55e; transition:background .2s; }
        #hm-app .hm-appt-body { padding:10px 14px; display:flex; flex-direction:column; gap:6px; }
        #hm-app .hm-appt-name { font-size:13px; font-weight:700; }
        #hm-app .hm-appt-badges { display:flex; gap:5px; margin:2px 0; }
        #hm-app .hm-appt-badges .hm-badge { height:18px; min-width:18px; padding:0 5px; border-radius:4px; font-size:9px; font-weight:700; color:#fff; display:inline-flex; align-items:center; justify-content:center; }
        #hm-app .hm-badge-c { background:#3b82f6; }
        #hm-app .hm-badge-r { background:#6366f1; }
        #hm-app .hm-badge-v { background:#8b5cf6; }
        #hm-app .hm-appt-time { font-size:13px; font-weight:600; color:#0ea5a4; }
        #hm-app .hm-appt-meta { font-size:12px; color:var(--hm-text-light); }
        /* Outcome row hover */
        #hm-app .hm-outcome-row { cursor:pointer; transition:background .15s; }
        #hm-app .hm-outcome-row:hover { background:#f8fafc; }
        .hm-days-grid            { display:flex; flex-wrap:wrap; gap:8px; }
        </style>
        <div id="hm-app" class="hm-calendar" data-module="calendar" data-view="settings">
        <div class="hm-page" data-service-id="<?php echo $service_id; ?>">

            <a href="<?php echo esc_url(home_url('/appointment-types/')); ?>" class="hm-back">← Back</a>

            <div class="hm-page-header">
                <h1 class="hm-page-title"><?php echo esc_html($svc->service_name); ?></h1>
                <div class="hm-page-subtitle">Configure this appointment type's details, outcomes, and staff assignments.</div>
            </div>

            <!-- ═══ ROW 1: Details + Colours + Preview ═══ -->
            <div class="hm-card-grid hm-card-grid--3">

                <!-- Card 1: Details -->
                <div class="hm-card">
                    <div class="hm-card-hd">
                        Details
                        <button type="button" class="hm-btn hm-btn--primary" id="hm-svc-save-details">Save Details</button>
                    </div>
                    <div class="hm-card-body">
                        <div class="hm-srow"><span class="hm-slbl">Name</span><span class="hm-sval"><input type="text" class="hm-inp" id="hm-svc-name" value="<?php echo esc_attr($svc->service_name); ?>"></span></div>
                        <div class="hm-srow"><span class="hm-slbl">Duration (min)</span><span class="hm-sval"><input type="number" class="hm-inp" id="hm-svc-duration" value="<?php echo intval($svc->duration_minutes ?? 30); ?>" min="5" step="5"></span></div>
                        <div class="hm-srow"><span class="hm-slbl">Category</span><span class="hm-sval"><select class="hm-dd" id="hm-svc-category"><option value="">— None —</option><?php
                            $cats = ['consultation'=>'Consultation','service'=>'Service','review'=>'Review','diagnostic'=>'Diagnostic','fitting'=>'Fitting','repair'=>'Repair'];
                            $cur = $svc->appointment_category ?? '';
                            foreach ($cats as $ck => $cv):
                        ?><option value="<?php echo esc_attr($ck); ?>" <?php selected($cur, $ck); ?>><?php echo esc_html($cv); ?></option><?php endforeach; ?></select></span></div>
                        <div class="hm-srow">
                            <label class="hm-day-check">
                                <input type="checkbox" id="hm-svc-sales" <?php checked(!empty($svc->sales_opportunity)); ?>>
                                <span class="hm-check"></span>
                                Sales opportunity
                            </label>
                        </div>
                        <div class="hm-srow">
                            <label class="hm-day-check">
                                <input type="checkbox" id="hm-svc-income" <?php checked($svc->income_bearing !== false && $svc->income_bearing !== 'f'); ?>>
                                <span class="hm-check"></span>
                                Income bearing
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Colours -->
                <div class="hm-card">
                    <div class="hm-card-hd">Card Colours</div>
                    <div class="hm-card-body">
                        <!-- Background & Border -->
                        <div class="hm-colour-group">
                            <div class="hm-colour-group-hd">Card Background</div>
                            <div class="hm-srow hm-color-row">
                                <span class="hm-slbl">Block colour</span>
                                <span class="hm-sval"><input type="color" id="hm-svc-colour" value="<?php echo esc_attr($colour); ?>" class="hm-color-box"></span>
                            </div>
                            <div class="hm-srow hm-color-row">
                                <span class="hm-slbl">Border colour</span>
                                <span class="hm-sval"><input type="color" id="hm-svc-border-colour" value="<?php echo esc_attr($border_colour ?: $colour); ?>" class="hm-color-box"></span>
                            </div>
                        </div>

                        <!-- Text colours -->
                        <div class="hm-colour-group">
                            <div class="hm-colour-group-hd">Text</div>
                            <div class="hm-srow hm-color-row">
                                <span class="hm-slbl">Patient name</span>
                                <span class="hm-sval"><input type="color" id="hm-svc-text-colour" value="<?php echo esc_attr($text_colour); ?>" class="hm-color-box"></span>
                            </div>
                            <div class="hm-srow hm-color-row">
                                <span class="hm-slbl">Appointment type label</span>
                                <span class="hm-sval"><input type="color" id="hm-svc-name-colour" value="<?php echo esc_attr($name_colour); ?>" class="hm-color-box"></span>
                            </div>
                        </div>

                        <!-- Time & Meta -->
                        <div class="hm-colour-group">
                            <div class="hm-colour-group-hd">Time &amp; Meta</div>
                            <div class="hm-srow hm-color-row">
                                <span class="hm-slbl">Time colour</span>
                                <span class="hm-sval"><input type="color" id="hm-svc-time-colour" value="<?php echo esc_attr($time_colour); ?>" class="hm-color-box"></span>
                            </div>
                            <div class="hm-srow hm-color-row">
                                <span class="hm-slbl">Meta colour</span>
                                <span class="hm-sval"><input type="color" id="hm-svc-meta-colour" value="<?php echo esc_attr($meta_colour); ?>" class="hm-color-box"></span>
                            </div>
                        </div>

                        <!-- Badges -->
                        <div class="hm-colour-group">
                            <div class="hm-colour-group-hd">Badges</div>
                            <div class="hm-srow hm-color-row">
                                <span class="hm-slbl">Badge background</span>
                                <span class="hm-sval"><input type="color" id="hm-svc-badge-bg" value="<?php echo esc_attr($badge_bg ?: '#ffffff'); ?>" class="hm-color-box"></span>
                            </div>
                            <div class="hm-srow hm-color-row">
                                <span class="hm-slbl">Badge text</span>
                                <span class="hm-sval"><input type="color" id="hm-svc-badge-text" value="<?php echo esc_attr($badge_text); ?>" class="hm-color-box"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Preview -->
                <div class="hm-card">
                    <div class="hm-card-hd">Appointment Preview</div>
                    <div class="hm-card-body">
                        <div class="hm-appt-preview-wrap">
                            <!-- Card style tabs -->
                            <div class="hm-preview-style-bar">
                                <button class="hm-preview-style-btn active" data-style="solid">Solid</button>
                                <button class="hm-preview-style-btn" data-style="tinted">Tinted</button>
                                <button class="hm-preview-style-btn" data-style="outline">Outline</button>
                                <button class="hm-preview-style-btn" data-style="minimal">Minimal</button>
                            </div>

                            <!-- Live preview card -->
                            <div class="hm-preview-card-wrap">
                                <div class="hm-prev-card" id="hm-prev-card">
                                    <div class="hm-prev-banner" id="hm-prev-banner" style="background:#22c55e;">Completed</div>
                                    <div class="hm-prev-inner">
                                        <div class="hm-prev-svc" id="hm-prev-svc"><?php echo esc_html($svc->service_name); ?></div>
                                        <div class="hm-prev-pt" id="hm-prev-pt">Sarah Johnson</div>
                                        <div class="hm-prev-tm" id="hm-prev-tm">09:00 – 09:30</div>
                                        <div class="hm-prev-badges" id="hm-prev-badges">
                                            <span class="hm-prev-badge" id="hm-prev-status-badge" style="background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe">Confirmed</span>
                                            <span class="hm-prev-badge hm-prev-badge--ini" id="hm-prev-ini-badge">SJ</span>
                                        </div>
                                        <div class="hm-prev-meta" id="hm-prev-meta"><?php echo esc_html($svc->service_name); ?> · Sample Clinic</div>
                                    </div>
                                </div>
                            </div>
                            <div class="hm-prev-label">Click an outcome below to preview its banner</div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ═══ ROW 2: Outcomes ═══ -->
            <div class="hm-card" style="margin-bottom:16px;">
                <div class="hm-card-hd">
                    Outcomes
                    <button class="hm-btn hm-btn--primary" id="hm-add-outcome">+ Add Outcome</button>
                </div>
                <div class="hm-card-body">
                    <?php if (empty($outcomes)): ?>
                        <p style="color:var(--hm-text-muted);font-size:13px;margin:0;">No outcomes defined yet.</p>
                    <?php else: ?>
                        <?php foreach ($outcomes as $o):
                            $oc = $o->outcome_color ?: '#cccccc';
                            $fu_ids = $o->followup_service_ids ? json_decode($o->followup_service_ids, true) : [];
                        ?>
                        <div class="hm-srow hm-outcome-row" style="padding:8px 0;border-bottom:1px solid #f1f5f9;" data-id="<?php echo (int)$o->id; ?>" data-color="<?php echo esc_attr($oc); ?>" data-name="<?php echo esc_attr($o->outcome_name); ?>">
                            <span style="width:16px;height:16px;border-radius:4px;background:<?php echo esc_attr($oc); ?>;flex-shrink:0;display:inline-block;"></span>
                            <span class="hm-slbl" style="font-weight:600;color:#0f172a;"><?php echo esc_html($o->outcome_name); ?></span>
                            <span style="display:flex;gap:6px;align-items:center;">
                                <?php if (!empty($o->is_invoiceable) && $o->is_invoiceable): ?>
                                    <span class="hm-badge hm-badge--sm hm-badge--amber">Invoiceable</span>
                                <?php endif; ?>
                                <?php if (!empty($o->requires_note) && $o->requires_note): ?>
                                    <span class="hm-badge hm-badge--sm hm-badge--blue">Note</span>
                                <?php endif; ?>
                                <?php if (!empty($o->triggers_followup) && $o->triggers_followup): ?>
                                    <span class="hm-badge hm-badge--sm hm-badge--green">Follow-up</span>
                                <?php endif; ?>
                                <?php if (!empty($o->triggers_reminder) && $o->triggers_reminder): ?>
                                    <span class="hm-badge hm-badge--sm hm-badge--purple">SMS</span>
                                <?php endif; ?>
                                <?php if (!empty($o->triggers_followup_call) && $o->triggers_followup_call): ?>
                                    <span class="hm-badge hm-badge--sm hm-badge--orange">Call <?php echo intval($o->followup_call_days ?? 7); ?>d</span>
                                <?php endif; ?>
                                <button class="hm-btn hm-btn--sm hm-outcome-edit" data-row='<?php echo json_encode([
                                    'id'                    => (int)$o->id,
                                    'outcome_name'          => $o->outcome_name,
                                    'outcome_color'         => $oc,
                                    'is_invoiceable'        => !empty($o->is_invoiceable) && $o->is_invoiceable,
                                    'requires_note'         => !empty($o->requires_note) && $o->requires_note,
                                    'triggers_followup'     => !empty($o->triggers_followup) && $o->triggers_followup,
                                    'followup_service_ids'  => $fu_ids,
                                    'triggers_reminder'     => !empty($o->triggers_reminder) && $o->triggers_reminder,
                                    'triggers_followup_call'=> !empty($o->triggers_followup_call) && $o->triggers_followup_call,
                                    'followup_call_days'    => intval($o->followup_call_days ?? 7),
                                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>'>Edit</button>
                                <button class="hm-btn hm-btn--sm hm-btn--danger hm-outcome-del" data-id="<?php echo (int)$o->id; ?>" data-name="<?php echo esc_attr($o->outcome_name); ?>">Delete</button>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ═══ ROW 3: Assignable Staff + Reminders ═══ -->
            <div class="hm-card-grid" style="grid-template-columns:1fr 1fr;">

                <!-- Assignable Staff -->
                <div class="hm-card">
                    <div class="hm-card-hd">
                        Assignable Staff
                        <button class="hm-btn hm-btn--primary" id="hm-save-staff">Save</button>
                    </div>
                    <div class="hm-card-body">
                        <?php foreach ($all_staff as $st):
                            $checked = in_array((int)$st->id, $assigned_ids) ? 'checked' : '';
                            $sname   = trim($st->first_name . ' ' . $st->last_name);
                            $srole   = ucfirst($st->role ?? '');
                        ?>
                        <div class="hm-srow" style="margin-bottom:6px;">
                            <label class="hm-day-check">
                                <input type="checkbox" class="hm-staff-cb" value="<?php echo (int)$st->id; ?>" <?php echo $checked; ?>>
                                <span class="hm-check"></span>
                                <?php echo esc_html($sname); ?>
                                <span style="color:var(--hm-text-muted);font-size:11px;margin-left:4px;"><?php echo esc_html($srole); ?></span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Reminders -->
                <div class="hm-card">
                    <div class="hm-card-hd">Confirmation &amp; Reminders</div>
                    <div class="hm-card-body">
                        <div class="hm-srow">
                            <label class="hm-day-check">
                                <input type="checkbox" id="hm-svc-reminder" <?php checked(!empty($svc->reminder_enabled)); ?>>
                                <span class="hm-check"></span>
                                Send SMS reminder for this type
                            </label>
                        </div>
                        <div class="hm-srow">
                            <span class="hm-slbl">SMS Template</span>
                            <span class="hm-sval">
                                <select class="hm-dd" id="hm-svc-sms-tpl">
                                    <option value="">— None —</option>
                                    <?php foreach ($sms_templates as $tpl): ?>
                                        <option value="<?php echo (int)$tpl->id; ?>" <?php selected(($svc->reminder_sms_template_id ?? ''), $tpl->id); ?>>
                                            <?php echo esc_html($tpl->template_name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ═══ Outcome Modal ═══ -->
            <div class="hm-modal-bg" id="hm-outcome-modal">
                <div class="hm-modal hm-modal--md">
                    <div class="hm-modal-hd">
                        <h3 id="hm-outcome-title">Add Outcome</h3>
                        <button class="hm-close" onclick="hmOutcome.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body" style="padding:20px 24px;">
                        <input type="hidden" id="hmo-id">
                        <div class="hm-srow"><span class="hm-slbl">Outcome Name *</span><span class="hm-sval" style="width:220px;"><input type="text" class="hm-inp" id="hmo-name" placeholder="e.g. Completed"></span></div>
                        <div class="hm-srow hm-color-row"><span class="hm-slbl">Banner Colour</span><span class="hm-sval"><input type="color" id="hmo-colour" value="#22c55e" class="hm-color-box"></span></div>
                        <div class="hm-srow">
                            <label class="hm-day-check">
                                <input type="checkbox" id="hmo-invoiceable">
                                <span class="hm-check"></span>
                                Invoiceable (triggers order flow)
                            </label>
                        </div>
                        <div class="hm-srow">
                            <label class="hm-day-check">
                                <input type="checkbox" id="hmo-note">
                                <span class="hm-check"></span>
                                Requires note
                            </label>
                        </div>
                        <div class="hm-srow">
                            <label class="hm-day-check">
                                <input type="checkbox" id="hmo-followup">
                                <span class="hm-check"></span>
                                Triggers follow-up appointment
                            </label>
                        </div>
                        <div class="hm-srow">
                            <label class="hm-day-check">
                                <input type="checkbox" id="hmo-reminder">
                                <span class="hm-check"></span>
                                Triggers SMS reminder
                            </label>
                        </div>
                        <div class="hm-srow">
                            <label class="hm-day-check">
                                <input type="checkbox" id="hmo-followup-call">
                                <span class="hm-check"></span>
                                Triggers follow-up call
                            </label>
                        </div>
                        <div id="hmo-followup-call-wrap" style="margin-top:4px;display:none;margin-left:24px;margin-bottom:8px;">
                            <div class="hm-srow">
                                <span class="hm-slbl">Call patient after</span>
                                <span class="hm-sval" style="width:120px;display:flex;align-items:center;gap:6px;"><input type="number" class="hm-inp" id="hmo-call-days" value="7" min="1" max="365" style="width:60px;"> days</span>
                            </div>
                            <p style="font-size:11px;color:var(--hm-text-muted);margin:4px 0 0;">Creates a reminder in the patient file for the dispenser to phone the patient.</p>
                        </div>
                        <div id="hmo-followup-wrap" style="margin-top:4px;display:none;">
                            <span class="hm-slbl" style="display:block;margin-bottom:8px;">Follow-up appointment type(s)</span>
                            <div class="hm-days-grid" id="hmo-followup-opts">
                                <?php foreach ($all_services as $as): ?>
                                <label class="hm-day-check">
                                    <input type="checkbox" class="hmo-fu-svc" value="<?php echo (int)$as->id; ?>">
                                    <span class="hm-check"></span>
                                    <?php echo esc_html($as->service_name); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="hm-modal-ft">
                        <button class="hm-btn" onclick="hmOutcome.close()">Cancel</button>
                        <button class="hm-btn hm-btn--primary" id="hmo-save">Save Outcome</button>
                    </div>
                </div>
            </div>

        </div><!-- .hm-page -->
        </div><!-- #hm-app -->

        <script>
        jQuery(function($){
            if (typeof HM === 'undefined') {
                console.error('[HM ServiceDetail] HM global not found — hearmed-core.js may not be loaded');
                return;
            }
            var SVC_ID = <?php echo $service_id; ?>;
            var ajaxUrl = HM.ajax_url || HM.ajax;
            var nonce   = HM.nonce;
            var previewStyle = 'solid';

            /* ── Live preview — matches calendar renderAppts exactly ── */
            function updatePreview() {
                var bg      = $('#hm-svc-colour').val();
                var border  = $('#hm-svc-border-colour').val() || bg;
                var ptColor = $('#hm-svc-text-colour').val();
                var svcColor= $('#hm-svc-name-colour').val();
                var tmColor = $('#hm-svc-time-colour').val();
                var metaCol = $('#hm-svc-meta-colour').val();
                var badgeBg = $('#hm-svc-badge-bg').val();
                var badgeTx = $('#hm-svc-badge-text').val();
                var name    = $('#hm-svc-name').val() || 'Preview';
                var dur     = parseInt($('#hm-svc-duration').val()) || 30;
                var cs      = previewStyle;

                var $card   = $('#hm-prev-card');
                var $svc    = $('#hm-prev-svc');
                var $pt     = $('#hm-prev-pt');
                var $tm     = $('#hm-prev-tm');
                var $meta   = $('#hm-prev-meta');
                var $iniBadge = $('#hm-prev-ini-badge');

                // Card style — mirroring hearmed-calendar.js renderAppts logic
                var bgStyle = '', fontColor = ptColor;
                if (cs === 'solid') {
                    bgStyle = 'background:' + bg + ';color:' + ptColor;
                    $svc.css('color', svcColor);
                    $tm.css('color', tmColor);
                    $meta.css('color', metaCol);
                    $iniBadge.css({ background: badgeBg, color: badgeTx });
                } else if (cs === 'tinted') {
                    var r = parseInt(bg.slice(1,3),16), g = parseInt(bg.slice(3,5),16), b = parseInt(bg.slice(5,7),16);
                    bgStyle = 'background:rgba('+r+','+g+','+b+',0.12);border-left:3.5px solid '+bg + ';color:' + bg;
                    fontColor = bg;
                    $svc.css('color', bg);
                    $tm.css('color', bg);
                    $meta.css('color', 'var(--hm-text-muted)');
                    $iniBadge.css({ background: 'rgba('+r+','+g+','+b+',0.15)', color: bg });
                } else if (cs === 'outline') {
                    bgStyle = 'background:#fff;border:1.5px solid '+bg+';border-left:3.5px solid '+bg+';color:'+bg;
                    fontColor = bg;
                    $svc.css('color', bg);
                    $tm.css('color', bg);
                    $meta.css('color', 'var(--hm-text-muted)');
                    $iniBadge.css({ background: bg+'1a', color: bg });
                } else if (cs === 'minimal') {
                    bgStyle = 'background:transparent;border-left:3px solid '+bg+';color:var(--hm-text)';
                    fontColor = 'var(--hm-text)';
                    $svc.css('color', bg);
                    $tm.css('color', 'var(--hm-text-muted)');
                    $meta.css('color', 'var(--hm-text-muted)');
                    $iniBadge.css({ background: bg+'1a', color: bg });
                }

                $card.attr('style', bgStyle);
                $pt.css('color', fontColor);
                $svc.text(name);
                $meta.text(name + ' · Sample Clinic');

                // Time label with duration
                var end_h = 9 + Math.floor(dur / 60);
                var end_m = dur % 60;
                var endTime = (end_h < 10 ? '0' : '') + end_h + ':' + (end_m < 10 ? '0' : '') + end_m;
                $tm.text('09:00 – ' + endTime);
            }

            // Bind all colour pickers and inputs to live preview
            $('#hm-svc-colour, #hm-svc-border-colour, #hm-svc-text-colour, #hm-svc-name-colour, #hm-svc-time-colour, #hm-svc-meta-colour, #hm-svc-badge-bg, #hm-svc-badge-text').on('input', updatePreview);
            $('#hm-svc-name, #hm-svc-duration').on('input', updatePreview);

            // Card style tabs
            $(document).on('click', '.hm-preview-style-btn', function() {
                $('.hm-preview-style-btn').removeClass('active');
                $(this).addClass('active');
                previewStyle = $(this).data('style');
                updatePreview();
            });

            // Initial render
            updatePreview();

            /* ── Outcome row click → update banner preview ── */
            $(document).on('click', '.hm-outcome-row', function(e) {
                if ($(e.target).closest('.hm-btn').length) return;
                var color = $(this).data('color') || '#22c55e';
                var name  = $(this).data('name') || 'Outcome';
                $('#hm-prev-banner').css('background', color).text(name).show();
            });

            /* ── Save details ── */
            $('#hm-svc-save-details').on('click', function(){
                var btn = $(this); btn.text('Saving…').prop('disabled', true);
                $.post(ajaxUrl, {
                    action: 'hm_admin_save_service_detail',
                    nonce: nonce,
                    id: SVC_ID,
                    service_name:        $('#hm-svc-name').val(),
                    colour:              $('#hm-svc-colour').val(),
                    text_color:          $('#hm-svc-text-colour').val(),
                    name_color:          $('#hm-svc-name-colour').val(),
                    time_color:          $('#hm-svc-time-colour').val(),
                    meta_color:          $('#hm-svc-meta-colour').val(),
                    badge_bg_color:      $('#hm-svc-badge-bg').val(),
                    badge_text_color:    $('#hm-svc-badge-text').val(),
                    border_color:        $('#hm-svc-border-colour').val(),
                    duration:            $('#hm-svc-duration').val(),
                    appointment_category:$('#hm-svc-category').val(),
                    sales_opportunity:   $('#hm-svc-sales').is(':checked') ? 1 : 0,
                    income_bearing:      $('#hm-svc-income').is(':checked') ? 1 : 0,
                    reminder_enabled:    $('#hm-svc-reminder').is(':checked') ? 1 : 0,
                    reminder_sms_template_id: $('#hm-svc-sms-tpl').val()
                }, function(r){
                    btn.text('Save Details').prop('disabled', false);
                    if (r.success) { btn.text('✓ Saved'); setTimeout(function(){ btn.text('Save Details'); }, 1500); }
                    else alert(r.data || 'Error saving');
                });
            });

            /* ── Outcome modal ── */
            var hmOutcome = window.hmOutcome = {
                open: function(data) {
                    var isEdit = !!(data && data.id);
                    $('#hm-outcome-title').text(isEdit ? 'Edit Outcome' : 'Add Outcome');
                    $('#hmo-id').val(isEdit ? data.id : '');
                    $('#hmo-name').val(isEdit ? data.outcome_name : '');
                    $('#hmo-colour').val(isEdit ? (data.outcome_color || '#22c55e') : '#22c55e');
                    $('#hmo-invoiceable').prop('checked', isEdit ? !!data.is_invoiceable : false);
                    $('#hmo-note').prop('checked', isEdit ? !!data.requires_note : false);
                    $('#hmo-followup').prop('checked', isEdit ? !!data.triggers_followup : false);
                    $('#hmo-reminder').prop('checked', isEdit ? !!data.triggers_reminder : false);
                    $('#hmo-followup-call').prop('checked', isEdit ? !!data.triggers_followup_call : false);
                    $('#hmo-call-days').val(isEdit && data.followup_call_days ? data.followup_call_days : 7);
                    // Follow-up services
                    $('.hmo-fu-svc').prop('checked', false);
                    if (isEdit && data.followup_service_ids && data.followup_service_ids.length) {
                        data.followup_service_ids.forEach(function(sid) {
                            $('.hmo-fu-svc[value="' + sid + '"]').prop('checked', true);
                        });
                    }
                    $('#hmo-followup-wrap').toggle($('#hmo-followup').is(':checked'));
                    $('#hmo-followup-call-wrap').toggle($('#hmo-followup-call').is(':checked'));
                    $('#hm-outcome-modal').addClass('open');
                    setTimeout(function(){ $('#hmo-name').focus(); }, 100);
                },
                close: function() {
                    $('#hm-outcome-modal').removeClass('open');
                }
            };

            $('#hmo-followup').on('change', function(){
                $('#hmo-followup-wrap').toggle(this.checked);
            });

            $('#hmo-followup-call').on('change', function(){
                $('#hmo-followup-call-wrap').toggle(this.checked);
            });

            $('#hm-add-outcome').on('click', function(){ hmOutcome.open(); });

            $(document).on('click', '.hm-outcome-edit', function(){
                hmOutcome.open($(this).data('row'));
            });

            $('#hmo-save').on('click', function(){
                var name = $.trim($('#hmo-name').val());
                if (!name) { alert('Outcome name is required.'); return; }
                var btn = $(this); btn.text('Saving…').prop('disabled', true);
                var fuIds = [];
                if ($('#hmo-followup').is(':checked')) {
                    $('.hmo-fu-svc:checked').each(function(){ fuIds.push(parseInt($(this).val())); });
                }
                $.post(ajaxUrl, {
                    action: 'hm_admin_save_outcome_template',
                    nonce: nonce,
                    id: $('#hmo-id').val(),
                    service_id: SVC_ID,
                    outcome_name: name,
                    outcome_color: $('#hmo-colour').val(),
                    is_invoiceable: $('#hmo-invoiceable').is(':checked') ? 1 : 0,
                    requires_note: $('#hmo-note').is(':checked') ? 1 : 0,
                    triggers_followup: $('#hmo-followup').is(':checked') ? 1 : 0,
                    followup_service_ids: JSON.stringify(fuIds),
                    triggers_reminder: $('#hmo-reminder').is(':checked') ? 1 : 0,
                    triggers_followup_call: $('#hmo-followup-call').is(':checked') ? 1 : 0,
                    followup_call_days: $('#hmo-call-days').val()
                }, function(r){
                    btn.text('Save Outcome').prop('disabled', false);
                    if (r.success) location.reload();
                    else alert(r.data || 'Error saving outcome');
                });
            });

            $(document).on('click', '.hm-outcome-del', function(){
                var id = $(this).data('id'), name = $(this).data('name');
                if (!confirm('Delete outcome "' + name + '"?')) return;
                $.post(ajaxUrl, {
                    action: 'hm_admin_delete_outcome_template',
                    nonce: nonce,
                    id: id
                }, function(r){
                    if (r.success) location.reload();
                    else alert(r.data || 'Error deleting outcome');
                });
            });

            /* ── Save assignable staff ── */
            $('#hm-save-staff').on('click', function(){
                var btn = $(this); btn.text('Saving…').prop('disabled', true);
                var ids = [];
                $('.hm-staff-cb:checked').each(function(){ ids.push(parseInt($(this).val())); });
                $.post(ajaxUrl, {
                    action: 'hm_admin_save_assignable_staff',
                    nonce: nonce,
                    service_id: SVC_ID,
                    staff_ids: JSON.stringify(ids)
                }, function(r){
                    btn.text('Save').prop('disabled', false);
                    if (r.success) { btn.text('✓ Saved'); setTimeout(function(){ btn.text('Save'); }, 1500); }
                    else alert(r.data || 'Error saving staff assignments');
                });
            });

        });
        </script>
        <?php
        return ob_get_clean();
    }

    /* ═══════════════════════════════════════════════════════
       AJAX: Get service detail (JSON)
       ═══════════════════════════════════════════════════════ */
    public function ajax_get_detail() {
        check_ajax_referer('hm_nonce', 'nonce');
        $id = intval($_POST['id'] ?? 0);
        if (!$id) { wp_send_json_error('Invalid ID'); return; }
        $svc = HearMed_DB::get_row("SELECT * FROM {$this->svc_table} WHERE id = $id");
        if (!$svc) { wp_send_json_error('Not found'); return; }
        wp_send_json_success((array)$svc);
    }

    /* ═══════════════════════════════════════════════════════
       AJAX: Save service details
       ═══════════════════════════════════════════════════════ */
    public function ajax_save_detail() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }

        $id = intval($_POST['id'] ?? 0);
        if (!$id) { wp_send_json_error('Invalid ID'); return; }

        $colour = sanitize_hex_color($_POST['colour'] ?? '#3B82F6') ?: '#3B82F6';
        $dur    = intval($_POST['duration'] ?? 30);

        $data = [
            'service_name'        => sanitize_text_field($_POST['service_name'] ?? ''),
            'service_color'       => $colour,
            'colour'              => $colour,
            'text_color'          => sanitize_hex_color($_POST['text_color'] ?? '#FFFFFF') ?: '#FFFFFF',
            'name_color'          => sanitize_hex_color($_POST['name_color'] ?? '#FFFFFF') ?: '#FFFFFF',
            'time_color'          => sanitize_hex_color($_POST['time_color'] ?? '#38bdf8') ?: '#38bdf8',
            'meta_color'          => sanitize_hex_color($_POST['meta_color'] ?? '#38bdf8') ?: '#38bdf8',
            'badge_bg_color'      => sanitize_hex_color($_POST['badge_bg_color'] ?? '#ffffff') ?: '#ffffff',
            'badge_text_color'    => sanitize_hex_color($_POST['badge_text_color'] ?? '#FFFFFF') ?: '#FFFFFF',
            'border_color'        => sanitize_hex_color($_POST['border_color'] ?? '') ?: '',
            'duration_minutes'    => $dur,
            'duration'            => $dur,
            'appointment_category'=> sanitize_text_field($_POST['appointment_category'] ?? ''),
            'sales_opportunity'   => !empty($_POST['sales_opportunity']),
            'income_bearing'      => !empty($_POST['income_bearing']),
            'reminder_enabled'    => !empty($_POST['reminder_enabled']),
            'reminder_sms_template_id' => intval($_POST['reminder_sms_template_id'] ?? 0) ?: null,
            'updated_at'          => current_time('mysql'),
        ];

        $result = HearMed_DB::update($this->svc_table, $data, ['id' => $id]);
        if ($result === false) {
            wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
        } else {
            wp_send_json_success(['id' => $id]);
        }
    }

    /* ═══════════════════════════════════════════════════════
       AJAX: Save outcome template
       ═══════════════════════════════════════════════════════ */
    public function ajax_save_outcome() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }

        $id         = intval($_POST['id'] ?? 0);
        $service_id = intval($_POST['service_id'] ?? 0);
        $name       = sanitize_text_field($_POST['outcome_name'] ?? '');
        if (!$name) { wp_send_json_error('Name is required'); return; }
        if (!$service_id) { wp_send_json_error('No service specified'); return; }

        $fu_ids = json_decode(stripslashes($_POST['followup_service_ids'] ?? '[]'), true);
        if (!is_array($fu_ids)) $fu_ids = [];

        $data = [
            'service_id'           => $service_id,
            'outcome_name'         => $name,
            'outcome_color'        => sanitize_hex_color($_POST['outcome_color'] ?? '#cccccc') ?: '#cccccc',
            'is_invoiceable'       => !empty($_POST['is_invoiceable']),
            'requires_note'        => !empty($_POST['requires_note']),
            'triggers_followup'    => !empty($_POST['triggers_followup']),
            'followup_service_ids' => wp_json_encode($fu_ids),
            'triggers_reminder'    => !empty($_POST['triggers_reminder']),
            'triggers_followup_call'=> !empty($_POST['triggers_followup_call']),
            'followup_call_days'   => max(1, intval($_POST['followup_call_days'] ?? 7)),
            'updated_at'           => current_time('mysql'),
        ];

        if ($id) {
            $result = HearMed_DB::update($this->out_table, $data, ['id' => $id]);
        } else {
            $data['created_at'] = current_time('mysql');
            $id = HearMed_DB::insert($this->out_table, $data);
            $result = $id ? 1 : false;
        }

        if ($result === false) {
            wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
        } else {
            wp_send_json_success(['id' => $id]);
        }
    }

    /* ═══════════════════════════════════════════════════════
       AJAX: Delete outcome template
       ═══════════════════════════════════════════════════════ */
    public function ajax_delete_outcome() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }

        $id = intval($_POST['id'] ?? 0);
        if (!$id) { wp_send_json_error('Invalid ID'); return; }

        $result = HearMed_DB::get_var(
            HearMed_DB::prepare("DELETE FROM {$this->out_table} WHERE id = %d RETURNING id", $id)
        );
        if ($result === null) {
            // Fallback: try the update method to soft-delete or just delete
            HearMed_DB::get_results("DELETE FROM {$this->out_table} WHERE id = $id");
        }
        wp_send_json_success();
    }

    /* ═══════════════════════════════════════════════════════
       AJAX: Save assignable staff
       ═══════════════════════════════════════════════════════ */
    public function ajax_save_staff() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }

        $service_id = intval($_POST['service_id'] ?? 0);
        if (!$service_id) { wp_send_json_error('No service specified'); return; }

        $staff_ids = json_decode(stripslashes($_POST['staff_ids'] ?? '[]'), true);
        if (!is_array($staff_ids)) $staff_ids = [];

        // Delete existing assignments
        HearMed_DB::get_results("DELETE FROM {$this->sas_table} WHERE service_id = $service_id");

        // Insert new assignments
        foreach ($staff_ids as $sid) {
            $sid = intval($sid);
            if ($sid) {
                HearMed_DB::insert($this->sas_table, [
                    'service_id' => $service_id,
                    'staff_id'   => $sid,
                    'created_at' => current_time('mysql'),
                ]);
            }
        }

        wp_send_json_success(['count' => count($staff_ids)]);
    }
}

new HearMed_Admin_Appointment_Type_Detail();
