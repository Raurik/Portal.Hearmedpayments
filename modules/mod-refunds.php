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
        .hm-cn-type-cheque   { background:#eff6ff; color:#2563eb; }
        .hm-cn-type-exchange { background:#f0fdf4; color:#16a34a; }
        .hm-cn-type-return   { background:#fef3c7; color:#d97706; }
        .hm-prsi-section { margin-top:24px; }
        .hm-prsi-card { background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:16px; }
        .hm-prsi-card h3 { margin:0 0 12px; color:#92400e; font-size:15px; }
        .hm-tab-pills { display:flex; gap:4px; margin-bottom:16px; }
        .hm-tab-pill { padding:8px 16px; border:1px solid #e2e8f0; border-radius:8px; background:white; cursor:pointer; font-size:13px; font-weight:500; color:#64748b; transition: all .15s; }
        .hm-tab-pill.active { background:#151B33; color:white; border-color:#151B33; }
        .hm-tab-pill .hm-pill-count { display:inline-flex; align-items:center; justify-content:center; min-width:20px; height:20px; padding:0 6px; border-radius:10px; font-size:11px; font-weight:600; margin-left:6px; }
        .hm-tab-pill.active .hm-pill-count { background:rgba(255,255,255,.2); color:white; }
        .hm-pill-red { background:#fef2f2; color:#dc2626; }
        .hm-pill-amber { background:#fffbeb; color:#d97706; }
        </style>

        <div class="hm-content hm-refunds-page">
            <div class="hm-page-header">
                <h1 class="hm-page-title">Refunds &amp; Credit Notes</h1>
                <div style="display:flex;gap:10px;align-items:center;">
                    <?php if ( HearMed_Auth::can('create_credit_note') ) : ?>
                    <button class="hm-btn hm-btn--primary" id="hm-cn-new-btn">+ New Credit Note</button>
                    <?php endif; ?>
                    <input type="text" id="hm-cn-search" class="hm-input hm-input--sm"
                           style="width:200px;" placeholder="Search patient or CN#…">
                </div>
            </div>

            <!-- Tab pills -->
            <div class="hm-tab-pills" id="hm-refund-tabs">
                <div class="hm-tab-pill active" data-tab="pending">Pending <span class="hm-pill-count hm-pill-red" id="hm-count-pending">0</span></div>
                <div class="hm-tab-pill" data-tab="prsi">PRSI Notifications <span class="hm-pill-count hm-pill-amber" id="hm-count-prsi">0</span></div>
                <div class="hm-tab-pill" data-tab="all">All</div>
                <div class="hm-tab-pill" data-tab="processed">Processed</div>
                <div class="hm-tab-pill" data-tab="exchange">Exchanges</div>
            </div>

            <div class="hm-stats" id="hm-stats"></div>

            <div id="hm-cn-table">
                <div class="hm-empty"><div class="hm-empty-text">Loading…</div></div>
            </div>
        </div>

        <!-- ══ NEW CREDIT NOTE MODAL ══════════════════════════════════════════ -->
        <div class="hm-modal-bg" id="hm-cn-modal" style="display:none;">
            <div class="hm-modal hm-modal--md">
                <div class="hm-modal-hd">
                    <h3 id="hm-cn-modal-title">New Credit Note</h3>
                    <button class="hm-close" id="hm-cn-close">&times;</button>
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
                                        <div style="font-size:11px;color:var(--hm-text-light);">Cash returned to patient by cheque</div>
                                    </div>
                                </label>
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;
                                              padding:12px 16px;border:2px solid #e2e8f0;border-radius:8px;flex:1;
                                              transition:border-color .15s;" id="hm-type-exchange-lbl">
                                    <input type="radio" name="hm-cn-type" value="exchange"
                                           onchange="hmCN.typeChange()">
                                    <div>
                                        <div style="font-weight:600;font-size:13px;">Exchange</div>
                                        <div style="font-size:11px;color:var(--hm-text-light);">Return HAs, create new order</div>
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
                    <button class="hm-close" id="hm-proc-close">&times;</button>
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
            var currentTab = 'pending';

            // ── Load ──────────────────────────────────────────────────────────

            function load() {
                $.post(ajax, {action:'hm_get_all_credit_notes', nonce:nonce}, function(r){
                    if (!r || !r.success) { $('#hm-cn-table').html('<div class="hm-empty"><div class="hm-empty-text">Failed to load</div></div>'); return; }
                    allNotes = r.data || [];
                    renderStats();
                    updateTabCounts();
                    renderTable();
                });
            }

            // ── Tab switching ─────────────────────────────────────────────────
            $(document).on('click', '.hm-tab-pill', function(){
                currentTab = $(this).data('tab');
                $('.hm-tab-pill').removeClass('active');
                $(this).addClass('active');
                renderTable();
            });

            // ── Stats ─────────────────────────────────────────────────────────

            function renderStats() {
                var pCheque=0, pChequeAmt=0, pExch=0, pPrsi=0, pPrsiAmt=0, done=0, doneAmt=0;
                allNotes.forEach(function(x){
                    var amt = parseFloat(x.amount||0);
                    var prsi = parseFloat(x.prsi_amount||0);
                    var type = x.refund_type || 'cheque';

                    if (type==='exchange') {
                        if (!x.processed_at) pExch++;
                        else done++;
                    } else {
                        if (!x.cheque_sent) { pCheque++; pChequeAmt+=parseFloat(x.patient_refund_amount||amt); }
                        else { done++; doneAmt+=parseFloat(x.patient_refund_amount||amt); }
                    }
                    if (prsi > 0 && !x.prsi_notified) { pPrsi++; pPrsiAmt += prsi; }
                });
                $('#hm-stats').html(
                    stat(pCheque,  'Refunds Pending', '#dc2626') +
                    stat('€'+pChequeAmt.toFixed(2), 'Pending Amount', '#dc2626') +
                    stat(pExch,    'Exchanges Pending',  '#ea580c') +
                    stat(pPrsi,    'PRSI to Notify',     '#d97706') +
                    stat('€'+pPrsiAmt.toFixed(2), 'PRSI Amount', '#d97706') +
                    stat(done,     'Processed',          '#16a34a')
                );
            }

            function updateTabCounts() {
                var pending=0, prsi=0;
                allNotes.forEach(function(x){
                    var type = x.refund_type || 'cheque';
                    var done = type==='exchange' ? !!x.processed_at : !!x.cheque_sent;
                    if (!done) pending++;
                    if (parseFloat(x.prsi_amount||0) > 0 && !x.prsi_notified) prsi++;
                });
                $('#hm-count-pending').text(pending);
                $('#hm-count-prsi').text(prsi);
            }

            function stat(v,l,c){
                return '<div class="hm-stat"><div class="hm-stat-val" style="color:'+c+';">'+v+'</div><div class="hm-stat-label">'+l+'</div></div>';
            }

            // ── Table ─────────────────────────────────────────────────────────

            function renderTable() {
                var filter = currentTab;
                var q      = $.trim($('#hm-cn-search').val()).toLowerCase();

                // PRSI tab — special render
                if (filter === 'prsi') { renderPrsiTable(q); return; }

                var filtered = allNotes.filter(function(x){
                    var type = x.refund_type || 'cheque';
                    var done = type==='exchange' ? !!x.processed_at : !!x.cheque_sent;

                    if (filter==='pending'  && done)               return false;
                    if (filter==='processed'&& !done)              return false;
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
                    '<th>Credit Note #</th><th>Patient</th><th>Type</th><th>Patient Amount</th>' +
                    '<th>PRSI</th><th>Reason</th><th>Date</th><th>Status</th><th></th>' +
                    '</tr></thead><tbody>';

                filtered.forEach(function(x){
                    var type  = x.refund_type || 'cheque';
                    var patAmt = parseFloat(x.patient_refund_amount || x.amount || 0);
                    var prsiAmt = parseFloat(x.prsi_amount || 0);
                    var typeBadge;
                    if (type==='exchange') typeBadge='<span class="hm-badge hm-cn-type-exchange">Exchange</span>';
                    else typeBadge='<span class="hm-badge hm-cn-type-cheque">Refund</span>';

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
                            statusHtml = '<span class="hm-badge hm-badge--green">Refund Sent ' + fmtDate(x.cheque_sent_date) + '</span>';
                        } else {
                            statusHtml = '<span class="hm-badge hm-badge--red">Refund Pending</span>';
                            actHtml    = '<button class="hm-btn hm-btn--sm hm-btn--secondary hm-proc-btn" data-id="'+x.id+'">Mark Sent</button>';
                        }
                    }

                    var prsiHtml = '—';
                    if (prsiAmt > 0) {
                        prsiHtml = '€'+prsiAmt.toFixed(2);
                        if (x.prsi_notified) prsiHtml += ' <span class="hm-badge hm-badge--sm hm-badge--green">Notified</span>';
                        else prsiHtml += ' <span class="hm-badge hm-badge--sm hm-badge--amber">Pending</span>';
                    }

                    var printBtn = '<button class="hm-btn hm-btn--sm hm-btn--ghost" onclick="window.open(HM.ajax_url+\'?action=hm_print_credit_note&nonce=\'+HM.nonce+\'&credit_note_id='+x.id+'\',\'_blank\')" style="margin-left:4px;">Print</button>';

                    h += '<tr>' +
                        '<td><code class="hm-mono">'+esc(x.credit_note_number)+'</code></td>' +
                        '<td><a href="/patients/?id='+x.patient_id+'" style="color:var(--hm-teal);">'+esc(x.patient_name)+'</a></td>' +
                        '<td>'+typeBadge+'</td>' +
                        '<td style="font-weight:600;">€'+patAmt.toFixed(2)+'</td>' +
                        '<td>'+prsiHtml+'</td>' +
                        '<td class="hm-muted" style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="'+esc(x.reason)+'">'+esc(x.reason||'\u2014')+'</td>' +
                        '<td class="hm-mono hm-muted" style="font-size:12px;">'+fmtDate((x.credit_date||'').split(' ')[0])+'</td>' +
                        '<td>'+statusHtml+'</td>' +
                        '<td>'+actHtml+printBtn+'</td>' +
                    '</tr>';
                });

                h += '</tbody></table>';
                $('#hm-cn-table').html(h);
            }

            // ── PRSI Table (separate view) ────────────────────────────────────

            function renderPrsiTable(q) {
                var prsi = allNotes.filter(function(x){
                    if (parseFloat(x.prsi_amount||0) <= 0) return false;
                    if (q) {
                        var hay = ((x.credit_note_number||'')+' '+(x.patient_name||'')).toLowerCase();
                        if (hay.indexOf(q)===-1) return false;
                    }
                    return true;
                });

                if (!prsi.length) {
                    $('#hm-cn-table').html('<div class="hm-empty"><div class="hm-empty-text">No PRSI refunds to track.</div></div>');
                    return;
                }

                var pending = prsi.filter(function(x){ return !x.prsi_notified; });
                var notified = prsi.filter(function(x){ return !!x.prsi_notified; });

                var h = '';
                if (pending.length) {
                    h += '<div class="hm-prsi-card" style="margin-bottom:16px;">'+
                         '<h3>⚠ PRSI Department Notifications Pending ('+pending.length+')</h3>'+
                         '<p style="font-size:13px;color:#92400e;margin-bottom:12px;">These patients had PRSI grant applied to their original order. The PRSI department needs to be notified of the return/exchange.</p>';
                    if (pending.length > 1) {
                        h += '<button class="hm-btn hm-btn--sm hm-btn--primary" id="hm-prsi-notify-all" style="margin-bottom:12px;">Mark All as Notified</button>';
                    }
                    h += '<table class="hm-table"><thead><tr><th>Patient</th><th>Credit Note</th><th>PRSI Amount</th><th>Type</th><th>Date</th><th></th></tr></thead><tbody>';
                    pending.forEach(function(x){
                        h += '<tr>'+
                            '<td><a href="/patients/?id='+x.patient_id+'" style="color:var(--hm-teal);">'+esc(x.patient_name)+'</a></td>'+
                            '<td><code class="hm-mono">'+esc(x.credit_note_number)+'</code></td>'+
                            '<td style="font-weight:600;">€'+parseFloat(x.prsi_amount).toFixed(2)+'</td>'+
                            '<td><span class="hm-badge hm-cn-type-'+esc(x.refund_type||'cheque')+'">'+esc(ucfirst(x.refund_type||'cheque'))+'</span></td>'+
                            '<td class="hm-mono hm-muted" style="font-size:12px;">'+fmtDate((x.credit_date||'').split(' ')[0])+'</td>'+
                            '<td><button class="hm-btn hm-btn--sm hm-btn--secondary hm-prsi-notify" data-id="'+x.id+'">Mark Notified</button></td>'+
                        '</tr>';
                    });
                    h += '</tbody></table></div>';
                }

                if (notified.length) {
                    h += '<details style="margin-top:16px;"><summary style="font-size:14px;font-weight:600;color:#94a3b8;cursor:pointer;">PRSI Department Notified ('+notified.length+')</summary>';
                    h += '<table class="hm-table" style="margin-top:8px;"><thead><tr><th>Patient</th><th>Credit Note</th><th>PRSI Amount</th><th>Notified Date</th></tr></thead><tbody>';
                    notified.forEach(function(x){
                        h += '<tr>'+
                            '<td><a href="/patients/?id='+x.patient_id+'" style="color:var(--hm-teal);">'+esc(x.patient_name)+'</a></td>'+
                            '<td><code class="hm-mono">'+esc(x.credit_note_number)+'</code></td>'+
                            '<td>€'+parseFloat(x.prsi_amount).toFixed(2)+'</td>'+
                            '<td class="hm-mono hm-muted" style="font-size:12px;">'+fmtDate(x.prsi_notified_date||'')+'</td>'+
                        '</tr>';
                    });
                    h += '</tbody></table></details>';
                }

                $('#hm-cn-table').html(h);
            }

            function hmOrderUrl(id){ return '/orders/?hm_action=view&order_id='+id; }
            function esc(s){ return $('<span>').text(s||'').html(); }
            function ucfirst(s){ return s.charAt(0).toUpperCase()+s.slice(1); }
            function fmtDate(d){ if(!d||d==='null')return '—'; var p=d.split('-'); return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:d; }

            // ── Filters ───────────────────────────────────────────────────────
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
                    var icon = type==='error' ? '×' : '✓';
                    $('#hm-cn-msg')
                        .removeClass('hm-notice--success hm-notice--error')
                        .addClass(type==='error' ? 'hm-notice--error' : 'hm-notice--success')
                        .html('<div class="hm-notice-body"><span class="hm-notice-icon">'+icon+'</span> '+text+'</div>').show();
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
                if (!cheque) { $('#hm-proc-msg').removeClass('hm-notice--success').addClass('hm-notice--error').html('<div class="hm-notice-body"><span class="hm-notice-icon">×</span> Enter cheque number.</div>').show(); return; }

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
                        $('#hm-proc-msg').removeClass('hm-notice--error').addClass('hm-notice--success').html('<div class="hm-notice-body"><span class="hm-notice-icon">✓</span> Refund processed.</div>').show();
                        setTimeout(function(){ $('#hm-process-modal').fadeOut(150); processingId=null; load(); }, 1400);
                    } else {
                        $('#hm-proc-msg').removeClass('hm-notice--success').addClass('hm-notice--error').html('<div class="hm-notice-body"><span class="hm-notice-icon">×</span> '+(r.data||'Error.')+'</div>').show();
                    }
                });
            });

            // ── PRSI notification handlers ───────────────────────────────────

            $(document).on('click', '.hm-prsi-notify', function(){
                var $btn = $(this);
                var cnId = $btn.data('id');
                if (!confirm('Mark this PRSI notification as sent to the department?')) return;
                $btn.prop('disabled',true).text('Saving…');
                $.post(ajax, {action:'hm_mark_prsi_notified', nonce:nonce, credit_note_id:cnId}, function(r){
                    if (r.success) { load(); }
                    else { $btn.prop('disabled',false).text('Mark Notified'); alert(r.data||'Error'); }
                });
            });

            $(document).on('click', '#hm-prsi-notify-all', function(){
                if (!confirm('Mark ALL pending PRSI notifications as sent to the department?')) return;
                var $btn = $(this).prop('disabled',true).text('Saving…');
                var pending = allNotes.filter(function(x){ return parseFloat(x.prsi_amount||0) > 0 && !x.prsi_notified; });
                var done = 0;
                pending.forEach(function(x){
                    $.post(ajax, {action:'hm_mark_prsi_notified', nonce:nonce, credit_note_id:x.id}, function(){
                        done++;
                        if (done >= pending.length) load();
                    });
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

        // Auto-migrate: check for new columns
        $has_prsi = $db->get_var(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = 'hearmed_core' AND table_name = 'credit_notes' AND column_name = 'prsi_amount'"
        );
        $prsi_cols = $has_prsi
            ? "cn.prsi_amount, cn.patient_refund_amount, cn.prsi_notified, cn.prsi_notified_date,"
            : "0 AS prsi_amount, cn.amount AS patient_refund_amount, false AS prsi_notified, NULL AS prsi_notified_date,";

        $rows = $db->get_results(
            "SELECT cn.id, cn.credit_note_number, cn.amount, cn.reason, cn.credit_date,
                    cn.refund_type, cn.exchange_order_id, cn.cheque_sent, cn.cheque_sent_date,
                    cn.processed_at,
                    {$prsi_cols}
                    CONCAT(p.first_name,' ',p.last_name) AS patient_name,
                    p.id AS patient_id
             FROM hearmed_core.credit_notes cn
             JOIN hearmed_core.patients p ON p.id = cn.patient_id
             ORDER BY cn.cheque_sent ASC, cn.created_at DESC",
            []
        );

        // Convert PG booleans ('t'/'f') to proper PHP booleans for JSON
        $out = [];
        if ( $rows ) {
            foreach ( $rows as $r ) {
                $item = (array) $r;
                if ( isset( $item['cheque_sent'] ) )    $item['cheque_sent']    = hm_pg_bool( $item['cheque_sent'] );
                if ( isset( $item['prsi_notified'] ) )  $item['prsi_notified']  = hm_pg_bool( $item['prsi_notified'] );
                $out[] = $item;
            }
        }
        wp_send_json_success( $out );
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
            $order_num = HearMed_Utils::generate_order_number();

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

    /**
     * Print a credit note using the template engine
     */
    public static function ajax_print_credit_note() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!is_user_logged_in()) wp_die('Access denied');

        $db = HearMed_DB::instance();
        $cn_id = intval($_GET['credit_note_id'] ?? 0);
        if (!$cn_id) wp_die('Missing credit note ID');

        $cn = $db->get_row(
            "SELECT cn.*,
                    p.first_name AS p_first, p.last_name AS p_last, p.patient_number,
                    p.address_line1, p.address_line2, p.city, p.county, p.eircode,
                    c.clinic_name
             FROM hearmed_core.credit_notes cn
             JOIN hearmed_core.patients p ON p.id = cn.patient_id
             LEFT JOIN hearmed_core.orders o ON o.id = cn.order_id
             LEFT JOIN hearmed_reference.clinics c ON c.id = o.clinic_id
             WHERE cn.id = \$1",
            [$cn_id]
        );
        if (!$cn) wp_die('Credit note not found');

        $items = [];
        if (!empty($cn->order_id)) {
            $items = $db->get_results(
                "SELECT oi.*, COALESCE(pr.product_name, oi.item_description) AS product_name
                 FROM hearmed_core.order_items oi
                 LEFT JOIN hearmed_reference.products pr ON pr.id = oi.item_id AND oi.item_type = 'product'
                 WHERE oi.order_id = \$1
                 ORDER BY oi.line_number",
                [$cn->order_id]
            );
        }

        $orig_inv = '';
        if (!empty($cn->invoice_id)) {
            $inv = $db->get_row(
                "SELECT invoice_number FROM hearmed_core.invoices WHERE id = \$1",
                [$cn->invoice_id]
            );
            $orig_inv = $inv ? $inv->invoice_number : '';
        }

        $tpl_data = clone $cn;
        $tpl_data->items                   = $items;
        $tpl_data->original_invoice_number = $orig_inv;
        $tpl_data->subtotal                = $cn->amount ?? 0;
        $tpl_data->vat_total               = 0;
        $tpl_data->grand_total             = $cn->amount ?? 0;

        if (!empty($cn->exchange_order_id)) {
            $ex = $db->get_row("SELECT order_number FROM hearmed_core.orders WHERE id = \$1", [$cn->exchange_order_id]);
            $tpl_data->exchange_order_number = $ex ? $ex->order_number : '';
        }

        header('Content-Type: text/html; charset=utf-8');
        echo HearMed_Print_Templates::render('creditnote', $tpl_data);
        exit;
    }

    // ─── AJAX: mark PRSI department notified ──────────────────────────────────

    public static function ajax_mark_prsi_notified() {
        check_ajax_referer('hm_nonce','nonce');
        if ( ! is_user_logged_in() ) wp_send_json_error('Not logged in.');

        $cn_id = intval( $_POST['credit_note_id'] ?? 0 );
        if ( ! $cn_id ) wp_send_json_error('Invalid credit note.');

        $user = HearMed_Auth::current_user();

        // Update credit note
        HearMed_DB::instance()->update( 'hearmed_core.credit_notes', [
            'prsi_notified'      => true,
            'prsi_notified_date' => date('Y-m-d'),
            'prsi_notified_by'   => $user->id ?? null,
        ], [ 'id' => $cn_id ] );

        // Also update return record if one exists
        $has_returns = HearMed_DB::instance()->get_var(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = 'hearmed_core' AND table_name = 'returns'"
        );
        if ( $has_returns ) {
            HearMed_DB::instance()->query(
                "UPDATE hearmed_core.returns SET prsi_notified = true, prsi_notified_date = \$1, prsi_notified_by = \$2 WHERE credit_note_id = \$3",
                [ date('Y-m-d'), $user->id ?? null, $cn_id ]
            );
        }

        wp_send_json_success('PRSI notification marked.');
    }
}

// Register AJAX actions directly here (supplementary to class-hearmed-ajax.php)
add_action( 'wp_ajax_hm_get_all_credit_notes',    ['HearMed_Refunds', 'ajax_get_all'] );
add_action( 'wp_ajax_hm_create_credit_note',       ['HearMed_Refunds', 'ajax_create_credit_note'] );
add_action( 'wp_ajax_hm_process_refund',           ['HearMed_Refunds', 'ajax_process'] );
add_action( 'wp_ajax_hm_get_patient_invoices',     ['HearMed_Refunds', 'ajax_get_patient_invoices'] );
add_action( 'wp_ajax_hm_print_credit_note',        ['HearMed_Refunds', 'ajax_print_credit_note'] );
add_action( 'wp_ajax_hm_mark_prsi_notified',       ['HearMed_Refunds', 'ajax_mark_prsi_notified'] );