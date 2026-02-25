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
        $assigned_ids = array_map(function($a) { return (int)$a->staff_id; }, $assigned);

        ob_start();
        ?>
        <style>
        /* Appointment-type-detail supplements (extends calendar-settings.css) */
        #hm-app .hm-page-header { margin-bottom:24px; }
        #hm-app .hm-page-title  { font-size:22px; font-weight:700; color:#0f172a; margin:0 0 4px; }
        #hm-app .hm-page-subtitle { font-size:13px; color:#64748b; }
        #hm-app .hm-color-box   { width:48px; height:32px; border:1px solid #e2e8f0; border-radius:6px; padding:2px; cursor:pointer; }
        #hm-app .hm-color-row .hm-sval { width:auto; }
        #hm-app .hm-card-grid   { margin-bottom:20px; }
        #hm-app .hm-badge       { display:inline-block; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; line-height:1.5; }
        #hm-app .hm-badge-sm    { font-size:10px; padding:1px 6px; }
        #hm-app .hm-badge-amber { background:#fef3c7; color:#92400e; }
        #hm-app .hm-badge-blue  { background:#dbeafe; color:#1e40af; }
        #hm-app .hm-badge-green { background:#dcfce7; color:#166534; }
        #hm-app .hm-inp, #hm-app .hm-dd { width:100%; padding:6px 10px; font-size:13px; border:1px solid #e2e8f0; border-radius:6px; color:#0f172a; background:#fff; box-sizing:border-box; }
        /* Modal */
        .hm-modal-bg             { display:none; position:fixed; inset:0; background:rgba(0,0,0,.35); z-index:9999; align-items:center; justify-content:center; }
        .hm-modal-bg.open        { display:flex; }
        .hm-modal                { background:#fff; border-radius:12px; box-shadow:0 10px 40px rgba(0,0,0,.18); max-height:90vh; overflow-y:auto; }
        .hm-modal-hd             { display:flex; justify-content:space-between; align-items:center; padding:16px 24px; border-bottom:1px solid #e2e8f0; }
        .hm-modal-hd h3          { margin:0; font-size:16px; font-weight:600; color:#0f172a; }
        .hm-modal-x              { background:none; border:none; font-size:22px; color:#94a3b8; cursor:pointer; line-height:1; }
        .hm-modal-ft             { display:flex; justify-content:flex-end; gap:10px; padding:14px 24px; border-top:1px solid #e2e8f0; }
        .hm-days-grid            { display:flex; flex-wrap:wrap; gap:8px; }
        </style>
        <div id="hm-app" class="hm-calendar" data-module="calendar" data-view="settings">
        <div class="hm-page" data-service-id="<?php echo $service_id; ?>">

            <div style="margin-bottom:16px;"><a href="<?php echo esc_url(home_url('/appointment-types/')); ?>" class="hm-btn">&larr; Back</a></div>

            <div class="hm-page-header">
                <h1 class="hm-page-title"><?php echo esc_html($svc->service_name); ?></h1>
                <div class="hm-page-subtitle">Configure this appointment type's details, outcomes, and staff assignments.</div>
            </div>

            <!-- ═══ ROW 1: Details + Preview ═══ -->
            <div class="hm-card-grid hm-card-grid--3" style="grid-template-columns:1fr 1fr 300px;">

                <!-- Card 1: Details -->
                <div class="hm-card">
                    <div class="hm-card-hd">Details</div>
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
                    <div class="hm-card-hd">Colours</div>
                    <div class="hm-card-body">
                        <div class="hm-srow hm-color-row">
                            <span class="hm-slbl">Block colour</span>
                            <span class="hm-sval"><input type="color" id="hm-svc-colour" value="<?php echo esc_attr($colour); ?>" class="hm-color-box"></span>
                        </div>
                        <div class="hm-srow hm-color-row">
                            <span class="hm-slbl">Text colour</span>
                            <span class="hm-sval"><input type="color" id="hm-svc-text-colour" value="<?php echo esc_attr($text_colour); ?>" class="hm-color-box"></span>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Preview -->
                <div class="hm-card">
                    <div class="hm-card-hd">Preview</div>
                    <div class="hm-card-body" style="display:flex;align-items:center;justify-content:center;min-height:80px;">
                        <div id="hm-svc-preview" style="display:inline-block;padding:8px 20px;border-radius:6px;font-size:14px;font-weight:600;background:<?php echo esc_attr($colour); ?>;color:<?php echo esc_attr($text_colour); ?>;">
                            <?php echo esc_html($svc->service_name); ?>
                        </div>
                    </div>
                </div>

            </div>

            <div style="text-align:right;margin-bottom:20px;">
                <button type="button" class="hm-btn hm-btn-teal" id="hm-svc-save-details">Save Details</button>
            </div>

            <!-- ═══ ROW 2: Outcomes ═══ -->
            <div class="hm-card">
                <div class="hm-card-hd" style="display:flex;justify-content:space-between;align-items:center;">
                    Outcomes
                    <button class="hm-btn hm-btn-teal" id="hm-add-outcome">+ Add Outcome</button>
                </div>
                <div class="hm-card-body">
                    <?php if (empty($outcomes)): ?>
                        <p style="color:#94a3b8;font-size:13px;margin:0;">No outcomes defined yet.</p>
                    <?php else: ?>
                        <?php foreach ($outcomes as $o):
                            $oc = $o->outcome_color ?: '#cccccc';
                            $fu_ids = $o->followup_service_ids ? json_decode($o->followup_service_ids, true) : [];
                        ?>
                        <div class="hm-srow" style="padding:8px 0;border-bottom:1px solid #f1f5f9;" data-id="<?php echo (int)$o->id; ?>">
                            <span style="width:16px;height:16px;border-radius:4px;background:<?php echo esc_attr($oc); ?>;flex-shrink:0;display:inline-block;"></span>
                            <span class="hm-slbl" style="font-weight:600;color:#0f172a;"><?php echo esc_html($o->outcome_name); ?></span>
                            <span style="display:flex;gap:6px;align-items:center;">
                                <?php if (!empty($o->is_invoiceable) && $o->is_invoiceable): ?>
                                    <span class="hm-badge hm-badge-sm hm-badge-amber">Invoiceable</span>
                                <?php endif; ?>
                                <?php if (!empty($o->requires_note) && $o->requires_note): ?>
                                    <span class="hm-badge hm-badge-sm hm-badge-blue">Note</span>
                                <?php endif; ?>
                                <?php if (!empty($o->triggers_followup) && $o->triggers_followup): ?>
                                    <span class="hm-badge hm-badge-sm hm-badge-green">Follow-up</span>
                                <?php endif; ?>
                                <?php if (!empty($o->triggers_reminder) && $o->triggers_reminder): ?>
                                    <span class="hm-badge hm-badge-sm" style="background:#a855f7;color:#fff;">SMS</span>
                                <?php endif; ?>
                                <button class="hm-btn hm-btn-sm hm-outcome-edit" data-row='<?php echo json_encode([
                                    'id'                   => (int)$o->id,
                                    'outcome_name'         => $o->outcome_name,
                                    'outcome_color'        => $oc,
                                    'is_invoiceable'       => !empty($o->is_invoiceable) && $o->is_invoiceable,
                                    'requires_note'        => !empty($o->requires_note) && $o->requires_note,
                                    'triggers_followup'    => !empty($o->triggers_followup) && $o->triggers_followup,
                                    'followup_service_ids' => $fu_ids,
                                    'triggers_reminder'    => !empty($o->triggers_reminder) && $o->triggers_reminder,
                                ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>'>Edit</button>
                                <button class="hm-btn hm-btn-sm hm-btn-red hm-outcome-del" data-id="<?php echo (int)$o->id; ?>" data-name="<?php echo esc_attr($o->outcome_name); ?>">Delete</button>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ═══ ROW 3: Assignable Staff + Reminders ═══ -->
            <div class="hm-card-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:0;">

                <!-- Assignable Staff -->
                <div class="hm-card">
                    <div class="hm-card-hd" style="display:flex;justify-content:space-between;align-items:center;">
                        Assignable Staff
                        <button class="hm-btn hm-btn-teal" id="hm-save-staff">Save</button>
                    </div>
                    <div class="hm-card-body">
                        <?php foreach ($all_staff as $st):
                            $checked = in_array((int)$st->id, $assigned_ids) ? 'checked' : '';
                            $sname   = trim($st->first_name . ' ' . $st->last_name);
                            $srole   = ucfirst($st->role ?? '');
                        ?>
                        <div class="hm-srow" style="margin-bottom:10px;">
                            <label class="hm-day-check">
                                <input type="checkbox" class="hm-staff-cb" value="<?php echo (int)$st->id; ?>" <?php echo $checked; ?>>
                                <span class="hm-check"></span>
                                <?php echo esc_html($sname); ?>
                                <span style="color:#94a3b8;font-size:11px;margin-left:4px;"><?php echo esc_html($srole); ?></span>
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
                <div class="hm-modal" style="width:560px;">
                    <div class="hm-modal-hd">
                        <h3 id="hm-outcome-title">Add Outcome</h3>
                        <button class="hm-modal-x" onclick="hmOutcome.close()">&times;</button>
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
                        <button class="hm-btn hm-btn-teal" id="hmo-save">Save Outcome</button>
                    </div>
                </div>
            </div>

        </div><!-- .hm-page -->
        </div><!-- #hm-app -->

        <script>
        (function($){
            var SVC_ID = <?php echo $service_id; ?>;
            var ajaxUrl = HM.ajax_url || HM.ajax;
            var nonce   = HM.nonce;

            /* ── Live preview ── */
            function updatePreview() {
                var $p = $('#hm-svc-preview');
                $p.css({ background: $('#hm-svc-colour').val(), color: $('#hm-svc-text-colour').val() });
                $p.text($('#hm-svc-name').val() || 'Preview');
            }
            $('#hm-svc-colour, #hm-svc-text-colour').on('input', updatePreview);
            $('#hm-svc-name').on('input', updatePreview);

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
                    // Follow-up services
                    $('.hmo-fu-svc').prop('checked', false);
                    if (isEdit && data.followup_service_ids && data.followup_service_ids.length) {
                        data.followup_service_ids.forEach(function(sid) {
                            $('.hmo-fu-svc[value="' + sid + '"]').prop('checked', true);
                        });
                    }
                    $('#hmo-followup-wrap').toggle($('#hmo-followup').is(':checked'));
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
                    triggers_reminder: $('#hmo-reminder').is(':checked') ? 1 : 0
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

        })(jQuery);
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

        $data = [
            'service_name'        => sanitize_text_field($_POST['service_name'] ?? ''),
            'service_color'       => sanitize_hex_color($_POST['colour'] ?? '#3B82F6') ?: '#3B82F6',
            'text_color'          => sanitize_hex_color($_POST['text_color'] ?? '#FFFFFF') ?: '#FFFFFF',
            'duration_minutes'    => intval($_POST['duration'] ?? 30),
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
