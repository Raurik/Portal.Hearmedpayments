<?php
/**
 * HearMed — Refunds & Credit Notes
 * Shortcode: [hearmed_refunds]
 *
 * Handles two credit note types:
 *   cheque   — cash refund, logged as cheque outstanding until processed
 *   exchange — HA returned, new order triggered from the credit note
 *
 * @package HearMed_Portal
 * @since   2.1.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ─── Shortcode render function (keeps original shortcode name) ────────────────
function hm_refunds_render() {
    if ( ! is_user_logged_in() ) return;
    echo HearMed_Refunds::render();
}
add_shortcode( 'hearmed_refunds', 'hm_refunds_render' );

class HearMed_Refunds {

    // ─── Render ──────────────────────────────────────────────────────────────

    public static function render(): string {
        if ( ! is_user_logged_in() ) return '';
        if ( ! HearMed_Auth::can('view_accounting') ) {
            return '<div class="hm-notice hm-notice--error">Access denied.</div>';
        }

        $nonce = wp_create_nonce('hm_nonce');
        ob_start(); ?>

        <style>
        .hm-cn-stats { display:flex; gap:14px; flex-wrap:wrap; margin-bottom:20px; }
        .hm-cn-stat  { background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:14px 22px; min-width:120px; text-align:center; }
        .hm-cn-stat__val   { font-size:26px; font-weight:700; color:#151B33; line-height:1.2; }
        .hm-cn-stat__label { font-size:11px; color:#94a3b8; margin-top:3px; text-transform:uppercase; letter-spacing:.05em; }
        .hm-cn-type-cheque   { background:#eff6ff; color:#2563eb; }
        .hm-cn-type-exchange { background:#f0fdf4; color:#16a34a; }
        </style>

        <div class="hm-content hm-refunds-page">
            <div class="hm-page-header">
                <h1 class="hm-page-title">Refunds &amp; Credit Notes</h1>
                <div style="display:flex;gap:10px;align-items:center;">
                    <?php if ( HearMed_Auth::can('create_credit_note') ) : ?>
                    <button class="hm-btn hm-btn--primary" id="hm-cn-new-btn">+ New Credit Note</button>
                    <?php endif; ?>
                    <select id="hm-cn-filter" class="hm-input hm-input--sm" style="width:auto;min-width:160px;">
                        <option value="pending">Pending (Outstanding)</option>
                        <option value="all">All Credit Notes</option>
                        <option value="processed">Processed / Exchanged</option>
                        <option value="cheque">Cheque Refunds Only</option>
                        <option value="exchange">Exchanges Only</option>
                    </select>
                    <input type="text" id="hm-cn-search" class="hm-input hm-input--sm"
                           style="width:200px;" placeholder="Search patient or CN#…">
                </div>
            </div>

            <div class="hm-cn-stats" id="hm-cn-stats"></div>

            <div id="hm-cn-table">
                <div class="hm-empty"><div class="hm-empty-text">Loading…</div></div>
            </div>
        </div>

        <!-- ══ NEW CREDIT NOTE MODAL ══════════════════════════════════════════ -->
        <div class="hm-modal-bg" id="hm-cn-modal" style="display:none;">
            <div class="hm-modal hm-modal--md">
                <div class="hm-modal-hd">
                    <h3 id="hm-cn-modal-title">New Credit Note</h3>
                    <button class="hm-modal-x" id="hm-cn-close">&times;</button>
                </div>
                <div class="hm-modal-body">

                    <!-- Step 1: Patient + invoice search -->
                    <div id="hm-cn-step1">
                        <div class="hm-form-group">
                            <label class="hm-label">Patient <span class="hm-required">*</span></label>
                            <input type="text" id="hm-cn-patient-search" class="hm-input"
                                   placeholder="Type patient name…" autocomplete="off">
                            <div id="hm-cn-patient-results" class="hm-autocomplete" style="display:none;"></div>
                            <input type="hidden" id="hm-cn-patient-id">
                        </div>
                        <div class="hm-form-group" style="margin-top:12px;" id="hm-cn-inv-wrap" style="display:none;">
                            <label class="hm-label">Invoice to Credit</label>
                            <select id="hm-cn-invoice-id" class="hm-input">
                                <option value="">— Select an invoice —</option>
                            </select>
                        </div>
                    </div>

                    <!-- Step 2: Credit note details -->
                    <div id="hm-cn-step2" style="display:none;">
                        <div class="hm-form-group">
                            <label class="hm-label">Credit Note Type <span class="hm-required">*</span></label>
                            <div style="display:flex;gap:12px;margin-top:6px;">
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;
                                              padding:12px 16px;border:2px solid #e2e8f0;border-radius:8px;flex:1;
                                              transition:border-color .15s;" id="hm-type-cheque-lbl">
                                    <input type="radio" name="hm-cn-type" value="cheque" checked
                                           onchange="hmCN.typeChange()">
                                    <div>
                                        <div style="font-weight:600;font-size:13px;">Cheque Refund</div>
                                        <div style="font-size:11px;color:#64748b;">Cash returned to patient by cheque</div>
                                    </div>
                                </label>
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;
                                              padding:12px 16px;border:2px solid #e2e8f0;border-radius:8px;flex:1;
                                              transition:border-color .15s;" id="hm-type-exchange-lbl">
                                    <input type="radio" name="hm-cn-type" value="exchange"
                                           onchange="hmCN.typeChange()">
                                    <div>
                                        <div style="font-weight:600;font-size:13px;">Exchange</div>
                                        <div style="font-size:11px;color:#64748b;">Return HAs, create new order</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="hm-form-group" style="margin-top:14px;">
                            <label class="hm-label">Credit Amount (€) <span class="hm-required">*</span></label>
                            <input type="number" id="hm-cn-amount" class="hm-input" step="0.01" min="0.01" placeholder="0.00">
                        </div>

                        <div class="hm-form-group" style="margin-top:14px;">
                            <label class="hm-label">Reason <span class="hm-required">*</span></label>
                            <textarea id="hm-cn-reason" class="hm-input hm-input--textarea" rows="2"
                                      placeholder="e.g. Patient returning Oticon More 1 — uncomfortable fit"></textarea>
                        </div>

                        <!-- Cheque-only fields -->
                        <div id="hm-cn-cheque-fields" style="margin-top:14px;">
                            <div class="hm-notice hm-notice--info" style="font-size:13px;">
                                A credit note will be raised. The cheque will appear in the pending queue until marked as sent.
                            </div>
                        </div>

                        <!-- Exchange-only fields -->
                        <div id="hm-cn-exchange-fields" style="display:none;margin-top:14px;">
                            <div class="hm-notice hm-notice--info" style="font-size:13px;">
                                A credit note will be raised and a new draft order will be opened automatically
                                so you can select the replacement hearing aids.
                            </div>
                        </div>
                    </div>

                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn hm-btn--ghost" id="hm-cn-cancel">Cancel</button>
                    <button class="hm-btn hm-btn--primary" id="hm-cn-next" onclick="hmCN.next()">
                        Next: Credit Details →
                    </button>
                    <button class="hm-btn hm-btn--primary" id="hm-cn-submit" style="display:none;"
                            onclick="hmCN.submit()">
                        Raise Credit Note
                    </button>
                </div>
                <div id="hm-cn-msg" class="hm-notice" style="display:none;margin:0 1.5rem 1rem;"></div>
            </div>
        </div>

        <!-- ══ PROCESS CHEQUE REFUND MODAL ═══════════════════════════════════ -->
        <div class="hm-modal-bg" id="hm-process-modal" style="display:none;">
            <div class="hm-modal hm-modal--sm">
                <div class="hm-modal-hd">
                    <h3>Process Cheque Refund</h3>
                    <button class="hm-modal-x" id="hm-proc-close">&times;</button>
                </div>
                <div class="hm-modal-body">
                    <div class="hm-form-group">
                        <label class="hm-label">Cheque Number <span class="hm-required">*</span></label>
                        <input type="text" id="hm-proc-cheque" class="hm-input" placeholder="e.g. CHQ-001234">
                    </div>
                    <div class="hm-form-group" style="margin-top:12px;">
                        <label class="hm-label">Date Sent</label>
                        <input type="date" id="hm-proc-date" class="hm-input"
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn hm-btn--ghost" id="hm-proc-cancel">Cancel</button>
                    <button class="hm-btn hm-btn--primary" id="hm-proc-save">Mark Cheque Sent</button>
                </div>
                <div id="hm-proc-msg" class="hm-notice" style="display:none;margin:0 1.5rem 1rem;"></div>
            </div>
        </div>

        <script>
        (function($){
            var ajax  = '<?php echo admin_url('admin-ajax.php'); ?>';
            var nonce = '<?php echo esc_js($nonce); ?>';
            var allNotes = [];
            var processingId = null;

            // ── Load ──────────────────────────────────────────────────────────

            function load() {
                $.post(ajax, {action:'hm_get_all_credit_notes', nonce:nonce}, function(r){
                    if (!r || !r.success) { $('#hm-cn-table').html('<div class="hm-empty"><div class="hm-empty-text">Failed to load</div></div>'); return; }
                    allNotes = r.data || [];
                    renderStats();
                    renderTable();
                });
            }

            // ── Stats ─────────────────────────────────────────────────────────

            function renderStats() {
                var pCheque=0, pChequeAmt=0, pExch=0, done=0, doneAmt=0;
                allNotes.forEach(function(x){
                    var amt = parseFloat(x.amount||0);
                    if (x.refund_type==='exchange') {
                        if (!x.processed_at) pExch++;
                        else done++;
                    } else {
                        if (!x.cheque_sent) { pCheque++; pChequeAmt+=amt; }
                        else { done++; doneAmt+=amt; }
                    }
                });
                $('#hm-cn-stats').html(
                    stat(pCheque,  'Cheques Outstanding', '#dc2626') +
                    stat('€'+pChequeAmt.toFixed(2), 'Pending Amount', '#dc2626') +
                    stat(pExch,    'Exchanges Pending',  '#ea580c') +
                    stat(done,     'Processed',          '#16a34a')
                );
            }

            function stat(v,l,c){
                return '<div class="hm-cn-stat"><div class="hm-cn-stat__val" style="color:'+c+';">'+v+'</div><div class="hm-cn-stat__label">'+l+'</div></div>';
            }

            // ── Table ─────────────────────────────────────────────────────────

            function renderTable() {
                var filter = $('#hm-cn-filter').val();
                var q      = $.trim($('#hm-cn-search').val()).toLowerCase();

                var filtered = allNotes.filter(function(x){
                    var type = x.refund_type || 'cheque';
                    var done = type==='exchange' ? !!x.processed_at : !!x.cheque_sent;

                    if (filter==='pending'  && done)               return false;
                    if (filter==='processed'&& !done)              return false;
                    if (filter==='cheque'   && type!=='cheque')    return false;
                    if (filter==='exchange' && type!=='exchange')  return false;

                    if (q) {
                        var hay = ((x.credit_note_number||'')+' '+(x.patient_name||'')).toLowerCase();
                        if (hay.indexOf(q)===-1) return false;
                    }
                    return true;
                });

                if (!filtered.length) {
                    $('#hm-cn-table').html('<div class="hm-empty"><div class="hm-empty-text">No credit notes match.</div></div>');
                    return;
                }

                var h = '<table class="hm-table"><thead><tr>' +
                    '<th>Credit Note #</th><th>Patient</th><th>Type</th><th>Amount</th>' +
                    '<th>Reason</th><th>Date</th><th>Status</th><th></th>' +
                    '</tr></thead><tbody>';

                filtered.forEach(function(x){
                    var type  = x.refund_type || 'cheque';
                    var amt   = '€'+parseFloat(x.amount||0).toFixed(2);
                    var typeBadge = type==='exchange'
                        ? '<span class="hm-badge hm-cn-type-exchange">Exchange</span>'
                        : '<span class="hm-badge hm-cn-type-cheque">Cheque</span>';

                    var statusHtml, actHtml = '';
                    if (type==='exchange') {
                        if (x.exchange_order_id) {
                            statusHtml = '<span class="hm-badge hm-badge--green">Exchange Order Created</span>';
                            actHtml    = '<a href="' + hmOrderUrl(x.exchange_order_id) + '" class="hm-btn hm-btn--sm hm-btn--ghost">View Order →</a>';
                        } else {
                            statusHtml = '<span class="hm-badge hm-badge--orange">Pending Exchange</span>';
                        }
                    } else {
                        if (x.cheque_sent) {
                            statusHtml = '<span class="hm-badge hm-badge--green">Cheque Sent ' + fmtDate(x.cheque_sent_date) + '</span>';
                        } else {
                            statusHtml = '<span class="hm-badge hm-badge--red">Cheque Outstanding</span>';
                            actHtml    = '<button class="hm-btn hm-btn--sm hm-btn--secondary hm-proc-btn" data-id="'+x.id+'">Process</button>';
                        }
                    }

                    h += '<tr>' +
                        '<td><code class="hm-mono">'+esc(x.credit_note_number)+'</code></td>' +
                        '<td><a href="/patients/?id='+x.patient_id+'" style="color:var(--hm-teal);">'+esc(x.patient_name)+'</a></td>' +
                        '<td>'+typeBadge+'</td>' +
                        '<td style="font-weight:600;">'+amt+'</td>' +
                        '<td class="hm-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="'+esc(x.reason)+'">'+esc(x.reason||'—')+'</td>' +
                        '<td class="hm-mono hm-muted" style="font-size:12px;">'+fmtDate((x.credit_date||'').split(' ')[0])+'</td>' +
                        '<td>'+statusHtml+'</td>' +
                        '<td>'+actHtml+'</td>' +
                    '</tr>';
                });

                h += '</tbody></table>';
                $('#hm-cn-table').html(h);
            }

            function hmOrderUrl(id){ return '/orders/?hm_action=view&order_id='+id; }
            function esc(s){ return $('<span>').text(s||'').html(); }
            function fmtDate(d){ if(!d||d==='null')return '—'; var p=d.split('-'); return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:d; }

            // ── Filters ───────────────────────────────────────────────────────
            $(document).on('change','#hm-cn-filter', renderTable);
            $(document).on('input','#hm-cn-search',  renderTable);

            // ── New credit note modal ─────────────────────────────────────────

            window.hmCN = {
                step: 1,

                open: function(){
                    this.step = 1;
                    $('#hm-cn-step1').show();
                    $('#hm-cn-step2').hide();
                    $('#hm-cn-next').show();
                    $('#hm-cn-submit').hide();
                    $('#hm-cn-msg').hide();
                    $('#hm-cn-patient-search').val('');
                    $('#hm-cn-patient-id').val('');
                    $('#hm-cn-invoice-id').html('<option value="">— Select an invoice —</option>');
                    $('#hm-cn-inv-wrap').hide();
                    $('#hm-cn-modal').fadeIn(150);
                },

                close: function(){
                    $('#hm-cn-modal').fadeOut(150);
                },

                typeChange: function(){
                    var t = $('input[name="hm-cn-type"]:checked').val();
                    $('#hm-cn-cheque-fields').toggle(t==='cheque');
                    $('#hm-cn-exchange-fields').toggle(t==='exchange');
                    $('#hm-type-cheque-lbl').css('border-color', t==='cheque' ? '#2563eb' : '#e2e8f0');
                    $('#hm-type-exchange-lbl').css('border-color', t==='exchange' ? '#16a34a' : '#e2e8f0');
                },

                next: function(){
                    var pid = $('#hm-cn-patient-id').val();
                    if (!pid) { hmCN.msg('Please search and select a patient.','error'); return; }
                    $('#hm-cn-step1').hide();
                    $('#hm-cn-step2').show();
                    $('#hm-cn-next').hide();
                    $('#hm-cn-submit').show();
                    hmCN.typeChange();
                },

                submit: function(){
                    var pid    = $('#hm-cn-patient-id').val();
                    var invId  = $('#hm-cn-invoice-id').val();
                    var type   = $('input[name="hm-cn-type"]:checked').val();
                    var amount = parseFloat($('#hm-cn-amount').val());
                    var reason = $.trim($('#hm-cn-reason').val());

                    if (!amount || amount <= 0) { hmCN.msg('Please enter a valid credit amount.','error'); return; }
                    if (!reason)                { hmCN.msg('Please enter a reason.','error'); return; }

                    var btn = $('#hm-cn-submit').prop('disabled',true).text('Saving…');
                    $.post(ajax, {
                        action:     'hm_create_credit_note',
                        nonce:      nonce,
                        patient_id: pid,
                        invoice_id: invId,
                        refund_type:type,
                        amount:     amount,
                        reason:     reason,
                    }, function(r){
                        btn.prop('disabled',false).text('Raise Credit Note');
                        if (r.success) {
                            hmCN.msg(r.data.message, 'success');
                            setTimeout(function(){
                                hmCN.close();
                                if (r.data.exchange_order_id) {
                                    window.location = hmOrderUrl(r.data.exchange_order_id);
                                } else {
                                    load();
                                }
                            }, 1600);
                        } else {
                            hmCN.msg(r.data || 'Error.', 'error');
                        }
                    });
                },

                msg: function(text, type){
                    $('#hm-cn-msg')
                        .removeClass('hm-notice--success hm-notice--error')
                        .addClass(type==='error' ? 'hm-notice--error' : 'hm-notice--success')
                        .text(text).show();
                }
            };

            $('#hm-cn-new-btn').on('click', function(){ hmCN.open(); });
            $('#hm-cn-close,#hm-cn-cancel').on('click', function(){ hmCN.close(); });

            // Patient autocomplete
            var searchTimer;
            $(document).on('input','#hm-cn-patient-search', function(){
                clearTimeout(searchTimer);
                var q = $(this).val();
                if (q.length < 2) { $('#hm-cn-patient-results').hide(); return; }
                searchTimer = setTimeout(function(){
                    $.post(ajax, {action:'hm_patient_search', nonce:nonce, q:q}, function(r){
                        if (!r.success || !r.data.length) { $('#hm-cn-patient-results').hide(); return; }
                        var h = '';
                        r.data.forEach(function(p){
                            h += '<div class="hm-autocomplete__item" data-id="'+p.id+'" data-name="'+esc(p.first_name+' '+p.last_name)+'">' +
                                 esc(p.first_name+' '+p.last_name)+'</div>';
                        });
                        $('#hm-cn-patient-results').html(h).show();
                    });
                }, 280);
            });

            $(document).on('click','.hm-autocomplete__item', function(){
                var id   = $(this).data('id');
                var name = $(this).data('name');
                $('#hm-cn-patient-id').val(id);
                $('#hm-cn-patient-search').val(name);
                $('#hm-cn-patient-results').hide();
                // Load invoices for this patient
                $.post(ajax, {action:'hm_get_patient_invoices', nonce:nonce, patient_id:id}, function(r){
                    if (!r.success) return;
                    var opts = '<option value="">— No specific invoice —</option>';
                    (r.data||[]).forEach(function(inv){
                        opts += '<option value="'+inv.id+'">'+esc(inv.invoice_number)+' — €'+parseFloat(inv.grand_total).toFixed(2)+' ('+fmtDate(inv.invoice_date)+')</option>';
                    });
                    $('#hm-cn-invoice-id').html(opts);
                    $('#hm-cn-inv-wrap').show();
                });
            });

            $(document).on('click', function(e){
                if (!$(e.target).closest('#hm-cn-patient-search,#hm-cn-patient-results').length) {
                    $('#hm-cn-patient-results').hide();
                }
            });

            // ── Process cheque modal ──────────────────────────────────────────

            $(document).on('click', '.hm-proc-btn', function(){
                processingId = $(this).data('id');
                $('#hm-proc-cheque').val('');
                $('#hm-proc-date').val(new Date().toISOString().split('T')[0]);
                $('#hm-proc-msg').hide();
                $('#hm-process-modal').fadeIn(150);
            });

            $('#hm-proc-close,#hm-proc-cancel').on('click', function(){
                $('#hm-process-modal').fadeOut(150);
                processingId = null;
            });

            $('#hm-proc-save').on('click', function(){
                if (!processingId) return;
                var cheque = $.trim($('#hm-proc-cheque').val());
                var date   = $('#hm-proc-date').val();
                if (!cheque) { $('#hm-proc-msg').removeClass('hm-notice--success').addClass('hm-notice--error').text('Enter cheque number.').show(); return; }

                var btn = $(this).prop('disabled',true).text('Saving…');
                $.post(ajax, {
                    action:          'hm_process_refund',
                    nonce:           nonce,
                    credit_note_id:  processingId,
                    cheque_number:   cheque,
                    cheque_date:     date,
                }, function(r){
                    btn.prop('disabled',false).text('Mark Cheque Sent');
                    if (r.success) {
                        $('#hm-proc-msg').removeClass('hm-notice--error').addClass('hm-notice--success').text('✓ Refund processed.').show();
                        setTimeout(function(){ $('#hm-process-modal').fadeOut(150); processingId=null; load(); }, 1400);
                    } else {
                        $('#hm-proc-msg').removeClass('hm-notice--success').addClass('hm-notice--error').text(r.data||'Error.').show();
                    }
                });
            });

            // ── Boot ──────────────────────────────────────────────────────────
            load();

        })(jQuery);
        </script>
        <?php
        return ob_get_clean();
    }

    // ─── AJAX: get all credit notes ───────────────────────────────────────────

    public static function ajax_get_all() {
        check_ajax_referer('hm_nonce','nonce');
        if ( ! is_user_logged_in() ) wp_send_json_error('Not logged in.');

        $db  = HearMed_DB::instance();
        $rows = $db->get_results(
            "SELECT cn.id, cn.credit_note_number, cn.amount, cn.reason, cn.credit_date,
                    cn.refund_type, cn.exchange_order_id, cn.cheque_sent, cn.cheque_sent_date,
                    cn.processed_at,
                    CONCAT(p.first_name,' ',p.last_name) AS patient_name,
                    p.id AS patient_id
             FROM hearmed_core.credit_notes cn
             JOIN hearmed_core.patients p ON p.id = cn.patient_id
             ORDER BY cn.cheque_sent ASC, cn.created_at DESC",
            []
        );
        wp_send_json_success( $rows ?: [] );
    }

    // ─── AJAX: create credit note ─────────────────────────────────────────────

    public static function ajax_create_credit_note() {
        check_ajax_referer('hm_nonce','nonce');
        if ( ! HearMed_Auth::can('create_credit_note') ) wp_send_json_error('Access denied.');

        $patient_id = intval( $_POST['patient_id'] ?? 0 );
        $invoice_id = intval( $_POST['invoice_id'] ?? 0 ) ?: null;
        $type       = sanitize_key( $_POST['refund_type'] ?? 'cheque' );
        $amount     = floatval( $_POST['amount'] ?? 0 );
        $reason     = sanitize_textarea_field( $_POST['reason'] ?? '' );

        if ( ! $patient_id ) wp_send_json_error('No patient selected.');
        if ( $amount <= 0 )  wp_send_json_error('Invalid amount.');
        if ( ! $reason )     wp_send_json_error('Reason is required.');
        if ( ! in_array( $type, ['cheque','exchange'] ) ) $type = 'cheque';

        $db   = HearMed_DB::instance();
        $user = HearMed_Auth::current_user();

        // Generate credit note number
        $count  = (int) $db->get_var( "SELECT COUNT(*) FROM hearmed_core.credit_notes" );
        $cn_num = 'HMCN-' . date('Y') . '-' . str_pad( $count + 1, 4, '0', STR_PAD_LEFT );

        $cn_id = $db->insert( 'hearmed_core.credit_notes', [
            'credit_note_number' => $cn_num,
            'patient_id'         => $patient_id,
            'invoice_id'         => $invoice_id,
            'amount'             => $amount,
            'reason'             => $reason,
            'refund_type'        => $type,
            'credit_date'        => date('Y-m-d'),
            'cheque_sent'        => false,
            'created_by'         => $user->id ?? null,
            'created_at'         => date('Y-m-d H:i:s'),
        ] );

        if ( ! $cn_id ) wp_send_json_error('Failed to create credit note. Please try again.');

        // Queue for QBO batch sync
        $db->insert( 'hearmed_admin.qbo_batch_queue', [
            'entity_type' => 'credit_note',
            'entity_id'   => $cn_id,
            'status'      => 'pending',
            'queued_at'   => date('Y-m-d H:i:s'),
            'created_by'  => $user->id ?? null,
        ] );

        // Log on original invoice if linked
        if ( $invoice_id ) {
            $db->insert( 'hearmed_core.patient_timeline', [
                'patient_id'  => $patient_id,
                'event_type'  => 'credit_note_raised',
                'event_date'  => date('Y-m-d'),
                'staff_id'    => $user->id ?? null,
                'description' => "Credit note {$cn_num} raised for €" . number_format($amount,2) . " — {$reason}",
            ] );
        }

        // For exchanges: create a draft order and return its ID
        $exchange_order_id = null;
        if ( $type === 'exchange' ) {
            $order_num = 'ORD-' . date('Ymd') . '-' . str_pad( rand(1,9999), 4, '0', STR_PAD_LEFT );

            // Get patient's clinic
            $patient = $db->get_row( "SELECT assigned_clinic_id FROM hearmed_core.patients WHERE id = \$1", [$patient_id] );
            $clinic  = $patient->assigned_clinic_id ?? HearMed_Auth::current_clinic();

            $exchange_order_id = $db->insert( 'hearmed_core.orders', [
                'order_number'   => $order_num,
                'patient_id'     => $patient_id,
                'staff_id'       => $user->id ?? null,
                'clinic_id'      => $clinic,
                'order_date'     => date('Y-m-d'),
                'current_status' => 'Awaiting Approval',
                'subtotal'       => 0,
                'vat_total'      => 0,
                'grand_total'    => 0,
                'notes'          => "Exchange order — Credit Note {$cn_num}. Original reason: {$reason}",
                'created_by'     => $user->id ?? null,
                'created_at'     => date('Y-m-d H:i:s'),
            ] );

            if ( $exchange_order_id ) {
                // Link the exchange order back to the credit note
                $db->update( 'hearmed_core.credit_notes', [
                    'exchange_order_id' => $exchange_order_id,
                    'processed_at'      => date('Y-m-d H:i:s'),
                    'processed_by'      => $user->id ?? null,
                ], [ 'id' => $cn_id ] );
            }
        }

        wp_send_json_success([
            'message'           => $type === 'exchange'
                ? "Credit note {$cn_num} raised. Exchange order created — fill in the replacement items."
                : "Credit note {$cn_num} raised. Cheque added to pending queue.",
            'credit_note_id'    => $cn_id,
            'credit_note_number'=> $cn_num,
            'exchange_order_id' => $exchange_order_id,
        ]);
    }

    // ─── AJAX: process cheque refund ──────────────────────────────────────────

    public static function ajax_process() {
        check_ajax_referer('hm_nonce','nonce');
        if ( ! HearMed_Auth::can('process_refund') ) wp_send_json_error('Access denied.');

        $cn_id  = intval( $_POST['credit_note_id'] ?? 0 );
        $cheque = sanitize_text_field( $_POST['cheque_number'] ?? '' );
        $date   = sanitize_text_field( $_POST['cheque_date']   ?? date('Y-m-d') );
        $user   = HearMed_Auth::current_user();

        if ( ! $cn_id )  wp_send_json_error('Invalid credit note.');
        if ( ! $cheque ) wp_send_json_error('Please enter a cheque number.');

        HearMed_DB::instance()->update( 'hearmed_core.credit_notes', [
            'cheque_sent'      => true,
            'cheque_sent_date' => $date,
            'cheque_number'    => $cheque,
            'processed_by'     => $user->id ?? null,
            'processed_at'     => date('Y-m-d H:i:s'),
        ], [ 'id' => $cn_id ] );

        wp_send_json_success(['message' => 'Refund marked as cheque sent.']);
    }

    // ─── AJAX: get patient invoices (for credit note modal dropdown) ──────────

    public static function ajax_get_patient_invoices() {
        check_ajax_referer('hm_nonce','nonce');
        if ( ! is_user_logged_in() ) wp_send_json_error('Not logged in.');

        $patient_id = intval( $_POST['patient_id'] ?? 0 );
        if ( ! $patient_id ) wp_send_json_error('No patient.');

        $rows = HearMed_DB::instance()->get_results(
            "SELECT id, invoice_number, grand_total, invoice_date, payment_status
             FROM hearmed_core.invoices
             WHERE patient_id = \$1
             ORDER BY invoice_date DESC
             LIMIT 20",
            [ $patient_id ]
        );
        wp_send_json_success( $rows ?: [] );
    }
}

// Register AJAX actions directly here (supplementary to class-hearmed-ajax.php)
add_action( 'wp_ajax_hm_get_all_credit_notes',    ['HearMed_Refunds', 'ajax_get_all'] );
add_action( 'wp_ajax_hm_create_credit_note',       ['HearMed_Refunds', 'ajax_create_credit_note'] );
add_action( 'wp_ajax_hm_process_refund',           ['HearMed_Refunds', 'ajax_process'] );
add_action( 'wp_ajax_hm_get_patient_invoices',     ['HearMed_Refunds', 'ajax_get_patient_invoices'] );