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

        ob_start();
        ?>
        <div id="hm-svc-detail" class="hm-admin" data-service-id="<?php echo $service_id; ?>">

            <!-- Back + Title -->
            <div class="hm-admin-hd">
                <h2><?php echo esc_html($svc->service_name); ?></h2>
            </div>
            <div style="margin-bottom:20px;">
                <a href="<?php echo esc_url(home_url('/appointment-types/')); ?>" class="hm-btn">&larr; Back</a>
            </div>

            <!-- ════ Details Card ════ -->
            <div class="hm-card" style="padding:24px;margin-bottom:20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h3 style="margin:0;font-size:15px;font-weight:600;color:#0f172a;">Details</h3>
                    <button class="hm-btn hm-btn-teal hm-btn-sm" id="hm-svc-save-details">Save Changes</button>
                </div>
                <div class="hm-form-row" style="display:flex;gap:16px;flex-wrap:wrap;">
                    <div class="hm-form-group" style="flex:2;min-width:200px;">
                        <label class="hm-label">Name</label>
                        <input type="text" class="hm-inp" id="hm-svc-name" value="<?php echo esc_attr($svc->service_name); ?>">
                    </div>
                    <div class="hm-form-group" style="flex:0 0 100px;">
                        <label class="hm-label">Block Colour</label>
                        <input type="color" id="hm-svc-colour" value="<?php echo esc_attr($colour); ?>" style="width:100%;height:38px;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer;">
                    </div>
                    <div class="hm-form-group" style="flex:0 0 100px;">
                        <label class="hm-label">Text Colour</label>
                        <input type="color" id="hm-svc-text-colour" value="<?php echo esc_attr($text_colour); ?>" style="width:100%;height:38px;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer;">
                    </div>
                </div>
                <div class="hm-form-row" style="display:flex;gap:16px;flex-wrap:wrap;margin-top:12px;">
                    <div class="hm-form-group" style="flex:1;min-width:120px;">
                        <label class="hm-label">Duration (minutes)</label>
                        <input type="number" class="hm-inp" id="hm-svc-duration" value="<?php echo intval($svc->duration_minutes ?? 30); ?>" min="5" step="5">
                    </div>
                    <div class="hm-form-group" style="flex:1;min-width:120px;">
                        <label class="hm-label">Category</label>
                        <select class="hm-inp" id="hm-svc-category">
                            <option value="">— None —</option>
                            <?php
                            $cats = ['consultation'=>'Consultation','service'=>'Service','review'=>'Review','diagnostic'=>'Diagnostic','fitting'=>'Fitting','repair'=>'Repair'];
                            $cur = $svc->appointment_category ?? '';
                            foreach ($cats as $ck => $cv):
                            ?>
                                <option value="<?php echo esc_attr($ck); ?>" <?php selected($cur, $ck); ?>><?php echo esc_html($cv); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="hm-form-group" style="flex:1;min-width:120px;">
                        <label class="hm-label">&nbsp;</label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:#334155;">
                            <input type="checkbox" id="hm-svc-sales" <?php checked(!empty($svc->sales_opportunity)); ?>>
                            Sales opportunity
                        </label>
                    </div>
                    <div class="hm-form-group" style="flex:1;min-width:120px;">
                        <label class="hm-label">&nbsp;</label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:#334155;">
                            <input type="checkbox" id="hm-svc-income" <?php checked($svc->income_bearing !== false && $svc->income_bearing !== 'f'); ?>>
                            Income bearing
                        </label>
                    </div>
                </div>
                <div style="margin-top:14px;">
                    <label class="hm-label">Preview</label>
                    <div id="hm-svc-preview" style="display:inline-block;padding:6px 16px;border-radius:4px;font-size:13px;font-weight:600;background:<?php echo esc_attr($colour); ?>;color:<?php echo esc_attr($text_colour); ?>;">
                        <?php echo esc_html($svc->service_name); ?>
                    </div>
                </div>
            </div>

            <!-- ════ Outcomes Card ════ -->
            <div class="hm-card" style="padding:24px;margin-bottom:20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h3 style="margin:0;font-size:15px;font-weight:600;color:#0f172a;">Outcomes</h3>
                    <button class="hm-btn hm-btn-teal hm-btn-sm" id="hm-add-outcome">+ Add Outcome</button>
                </div>
                <div id="hm-outcomes-list">
                    <?php if (empty($outcomes)): ?>
                        <p style="color:#94a3b8;font-size:13px;">No outcomes defined yet.</p>
                    <?php else: ?>
                        <?php foreach ($outcomes as $o):
                            $oc = $o->outcome_color ?: '#cccccc';
                            $fu_ids = $o->followup_service_ids ? json_decode($o->followup_service_ids, true) : [];
                        ?>
                        <div class="hm-outcome-row" data-id="<?php echo (int)$o->id; ?>" style="display:flex;align-items:center;gap:12px;padding:10px 14px;border:1px solid #e2e8f0;border-radius:8px;margin-bottom:8px;background:#fff;">
                            <span style="width:20px;height:20px;border-radius:4px;background:<?php echo esc_attr($oc); ?>;flex-shrink:0;"></span>
                            <strong style="flex:1;font-size:13px;color:#0f172a;"><?php echo esc_html($o->outcome_name); ?></strong>
                            <?php if (!empty($o->is_invoiceable) && $o->is_invoiceable): ?>
                                <span class="hm-badge hm-badge-sm hm-badge-amber" title="Triggers order flow">Invoiceable</span>
                            <?php endif; ?>
                            <?php if (!empty($o->requires_note) && $o->requires_note): ?>
                                <span class="hm-badge hm-badge-sm hm-badge-blue" title="Note required">Note</span>
                            <?php endif; ?>
                            <?php if (!empty($o->triggers_followup) && $o->triggers_followup): ?>
                                <span class="hm-badge hm-badge-sm hm-badge-green" title="Follow-up appointment">Follow-up</span>
                            <?php endif; ?>
                            <?php if (!empty($o->triggers_reminder) && $o->triggers_reminder): ?>
                                <span class="hm-badge hm-badge-sm hm-badge-purple" title="SMS reminder">SMS</span>
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
                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>'>✏ Edit</button>
                            <button class="hm-btn hm-btn-sm hm-btn-red hm-outcome-del" data-id="<?php echo (int)$o->id; ?>" data-name="<?php echo esc_attr($o->outcome_name); ?>">✕</button>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ════ Assignable Staff Card ════ -->
            <div class="hm-card" style="padding:24px;margin-bottom:20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h3 style="margin:0;font-size:15px;font-weight:600;color:#0f172a;">Assignable Staff</h3>
                    <button class="hm-btn hm-btn-teal hm-btn-sm" id="hm-save-staff">Save</button>
                </div>
                <p style="color:#94a3b8;font-size:13px;margin-bottom:12px;">Select which staff members can be assigned to this appointment type.</p>
                <div id="hm-staff-checkboxes" style="display:flex;flex-wrap:wrap;gap:10px;">
                    <?php
                    $assigned_ids = array_map(function($a) { return (int)$a->staff_id; }, $assigned);
                    foreach ($all_staff as $st):
                        $checked = in_array((int)$st->id, $assigned_ids) ? 'checked' : '';
                        $sname   = trim($st->first_name . ' ' . $st->last_name);
                        $srole   = ucfirst($st->role ?? '');
                    ?>
                    <label style="display:flex;align-items:center;gap:6px;padding:6px 12px;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer;font-size:13px;color:#334155;background:#fff;min-width:160px;">
                        <input type="checkbox" class="hm-staff-cb" value="<?php echo (int)$st->id; ?>" <?php echo $checked; ?>>
                        <?php echo esc_html($sname); ?>
                        <span style="color:#94a3b8;font-size:11px;">(<?php echo esc_html($srole); ?>)</span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ════ Reminders Card ════ -->
            <div class="hm-card" style="padding:24px;margin-bottom:20px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                    <h3 style="margin:0;font-size:15px;font-weight:600;color:#0f172a;">Confirmation &amp; Reminders</h3>
                </div>
                <div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;color:#334155;">
                        <input type="checkbox" id="hm-svc-reminder" <?php checked(!empty($svc->reminder_enabled)); ?>>
                        Send SMS reminder for this type
                    </label>
                    <div class="hm-form-group" style="min-width:200px;">
                        <label class="hm-label">SMS Template</label>
                        <select class="hm-inp" id="hm-svc-sms-tpl">
                            <option value="">— None —</option>
                            <?php foreach ($sms_templates as $tpl): ?>
                                <option value="<?php echo (int)$tpl->id; ?>" <?php selected(($svc->reminder_sms_template_id ?? ''), $tpl->id); ?>>
                                    <?php echo esc_html($tpl->template_name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ════ Outcome Modal ════ -->
            <div class="hm-modal-bg" id="hm-outcome-modal">
                <div class="hm-modal" style="width:580px;">
                    <div class="hm-modal-hd">
                        <h3 id="hm-outcome-title">Add Outcome</h3>
                        <button class="hm-modal-x" onclick="hmOutcome.close()">&times;</button>
                    </div>
                    <div class="hm-modal-body" style="padding:20px 24px;">
                        <input type="hidden" id="hmo-id">
                        <div class="hm-form-row" style="display:flex;gap:12px;">
                            <div class="hm-form-group" style="flex:2;">
                                <label class="hm-label">Outcome Name *</label>
                                <input type="text" class="hm-inp" id="hmo-name" placeholder="e.g. Completed">
                            </div>
                            <div class="hm-form-group" style="flex:0 0 80px;">
                                <label class="hm-label">Colour</label>
                                <input type="color" id="hmo-colour" value="#22c55e" style="width:100%;height:38px;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer;">
                            </div>
                        </div>
                        <div class="hm-form-row" style="display:flex;gap:24px;margin-top:14px;">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;color:#334155;">
                                <input type="checkbox" id="hmo-invoiceable"> Invoiceable (triggers order flow)
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;color:#334155;">
                                <input type="checkbox" id="hmo-note"> Requires note
                            </label>
                        </div>
                        <div class="hm-form-row" style="display:flex;gap:24px;margin-top:12px;">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;color:#334155;">
                                <input type="checkbox" id="hmo-followup"> Triggers follow-up
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px;color:#334155;">
                                <input type="checkbox" id="hmo-reminder"> Triggers SMS reminder
                            </label>
                        </div>
                        <div id="hmo-followup-wrap" style="margin-top:14px;display:none;">
                            <label class="hm-label">Follow-up appointment type(s)</label>
                            <div id="hmo-followup-opts" style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;">
                                <?php foreach ($all_services as $as): ?>
                                <label style="display:flex;align-items:center;gap:5px;padding:4px 10px;border:1px solid #e2e8f0;border-radius:5px;cursor:pointer;font-size:12px;color:#334155;background:#fff;">
                                    <input type="checkbox" class="hmo-fu-svc" value="<?php echo (int)$as->id; ?>">
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

        </div><!-- #hm-svc-detail -->

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
                    btn.text('Save Changes').prop('disabled', false);
                    if (r.success) { btn.text('✓ Saved'); setTimeout(function(){ btn.text('Save Changes'); }, 1500); }
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
