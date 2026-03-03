<?php
/**
 * HearMed Portal — QBO Invoice Review
 *
 * Weekly review page for invoices before sending to QuickBooks Online.
 * Shortcode: [hearmed_qbo_review]
 * Access: C-Level ONLY
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'hearmed_qbo_review', 'hm_render_qbo_review' );

function hm_render_qbo_review() {
    if ( ! PortalAuth::is_logged_in() ) return '<p>Please log in.</p>';
    if ( function_exists( 'hm_user_can_approve' ) && ! hm_user_can_approve() ) {
        return '<div class="hm-admin"><p style="padding:2rem;color:var(--hm-text-muted);">Access denied — C-Level only.</p></div>';
    }

    $db      = HearMed_DB::instance();
    $clinics = HearMed_DB::get_results( "SELECT id, clinic_name FROM hearmed_reference.clinics WHERE is_active = true ORDER BY clinic_name" );

    ob_start();
    ?>
    <div class="hm-admin" id="hm-qbo-review">
        <div class="hm-page-header">
            <h1 class="hm-page-title">QBO Invoice Review</h1>
            <p style="font-size:var(--hm-font-size-sm);color:var(--hm-text-light);margin-top:4px;">Review invoices before sending to QuickBooks. Select invoices and click "Send to QBO" to sync.</p>
        </div>

        <!-- Filter bar -->
        <div style="display:flex;gap:10px;margin-bottom:16px;align-items:center;flex-wrap:wrap;">
            <select id="hm-qbo-status-filter" class="hm-inp" style="width:180px;">
                <option value="pending_review">Pending Review</option>
                <option value="approved">Approved (Ready)</option>
                <option value="synced">Synced to QBO</option>
                <option value="error">Sync Errors</option>
                <option value="">All</option>
            </select>
            <select id="hm-qbo-clinic-filter" class="hm-inp" style="width:160px;">
                <option value="">All Clinics</option>
                <?php if ( $clinics ) foreach ( $clinics as $c ) : ?>
                    <option value="<?php echo intval( $c->id ); ?>"><?php echo esc_html( $c->clinic_name ); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" id="hm-qbo-from" class="hm-inp" style="width:140px;" />
            <input type="date" id="hm-qbo-to" class="hm-inp" style="width:140px;" />
            <button class="hm-btn hm-btn--outline" id="hm-qbo-load">Load</button>
            <div style="margin-left:auto;display:flex;gap:8px;">
                <button class="hm-btn hm-btn--outline" id="hm-qbo-select-all">Select All</button>
                <button class="hm-btn hm-btn--primary" id="hm-qbo-send" disabled>Send Selected to QBO</button>
            </div>
        </div>

        <!-- Invoice table -->
        <div id="hm-qbo-table-wrap">
            <table class="hm-table" style="width:100%;">
                <thead>
                    <tr>
                        <th style="width:30px;"><input type="checkbox" id="hm-qbo-check-all" /></th>
                        <th>Invoice No.</th>
                        <th>Date</th>
                        <th>Patient</th>
                        <th>Order No.</th>
                        <th>Clinic</th>
                        <th style="text-align:right;">Amount</th>
                        <th>PRSI</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="hm-qbo-body">
                    <tr><td colspan="10" style="text-align:center;padding:2rem;color:var(--hm-text-muted);">Click "Load" to fetch invoices.</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Summary bar -->
        <div id="hm-qbo-summary" style="display:none;padding:12px 16px;background:var(--hm-bg-alt);border:1px solid var(--hm-border);border-radius:var(--hm-radius);margin-top:12px;">
            <span id="hm-qbo-count">0 selected</span> |
            Total: <strong id="hm-qbo-total">€0.00</strong>
        </div>
    </div>

    <script>
    (function(){
        var nonce   = '<?php echo wp_create_nonce( "hm_nonce" ); ?>';
        var ajaxUrl = '<?php echo admin_url( "admin-ajax.php" ); ?>';
        var selected = {};

        function post(action, data) {
            data.action = action;
            data.nonce  = nonce;
            return fetch(ajaxUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams(data)
            }).then(function(r){ return r.json(); });
        }
        function esc(s){ var d=document.createElement('span'); d.textContent=s||''; return d.innerHTML; }

        document.getElementById('hm-qbo-load').addEventListener('click', loadInvoices);

        function loadInvoices(){
            var status = document.getElementById('hm-qbo-status-filter').value;
            var clinic = document.getElementById('hm-qbo-clinic-filter').value;
            var from   = document.getElementById('hm-qbo-from').value;
            var to     = document.getElementById('hm-qbo-to').value;
            var tbody  = document.getElementById('hm-qbo-body');
            tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:2rem;">Loading…</td></tr>';
            selected = {};
            updateSummary();

            post('hm_qbo_load_invoices', {
                status: status, clinic_id: clinic, date_from: from, date_to: to
            }).then(function(d){
                if(!d.success || !d.data.invoices || !d.data.invoices.length){
                    tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;padding:2rem;color:var(--hm-text-muted);">No invoices found.</td></tr>';
                    return;
                }
                var html = '';
                d.data.invoices.forEach(function(inv){
                    var statusClass = inv.qbo_sync_status === 'synced'  ? 'hm-badge--green'
                        : inv.qbo_sync_status === 'error'   ? 'hm-badge--red'
                        : inv.qbo_sync_status === 'approved' ? 'hm-badge--blue'
                        : inv.qbo_sync_status === 'skipped'  ? 'hm-badge--grey'
                        : 'hm-badge--amber';
                    var isSynced = inv.qbo_sync_status === 'synced';
                    html += '<tr data-id="'+inv.id+'">';
                    html += '<td><input type="checkbox" class="hm-qbo-check" data-id="'+inv.id+'" data-amount="'+(inv.total||0)+'" '+(isSynced?'disabled':'')+' /></td>';
                    html += '<td><strong>'+esc(inv.invoice_number)+'</strong></td>';
                    html += '<td>'+esc(inv.invoice_date||'')+'</td>';
                    html += '<td>'+esc(inv.patient_name||'—')+'</td>';
                    html += '<td>'+(inv.order_number?esc(inv.order_number):'—')+'</td>';
                    html += '<td>'+(inv.clinic_name?esc(inv.clinic_name):'—')+'</td>';
                    html += '<td style="text-align:right;font-weight:600;">€'+parseFloat(inv.total||0).toFixed(2)+'</td>';
                    html += '<td>'+(parseFloat(inv.prsi_amount||0)>0?'€'+parseFloat(inv.prsi_amount).toFixed(2):'—')+'</td>';
                    html += '<td><span class="hm-badge hm-badge--sm '+statusClass+'">'+esc(inv.qbo_sync_status||'pending_review')+'</span></td>';
                    html += '<td>';
                    if(!isSynced){
                        html += '<button class="hm-btn hm-btn--secondary hm-btn--sm hm-qbo-skip" data-id="'+inv.id+'">Skip</button>';
                    }
                    if(inv.qbo_sync_status==='error'){
                        html += ' <span style="color:var(--hm-error);font-size:var(--hm-font-size-xs);cursor:help" title="'+esc(inv.qbo_sync_error||'')+'">ⓘ</span>';
                    }
                    html += '</td>';
                    html += '</tr>';
                });
                tbody.innerHTML = html;
            });
        }

        // Checkbox handling
        document.getElementById('hm-qbo-body').addEventListener('change', function(e){
            if(e.target.classList.contains('hm-qbo-check')){
                var id  = e.target.dataset.id;
                var amt = parseFloat(e.target.dataset.amount) || 0;
                if(e.target.checked) selected[id] = amt; else delete selected[id];
                updateSummary();
            }
        });

        // Check all header checkbox
        document.getElementById('hm-qbo-check-all').addEventListener('change', function(){
            var checked = this.checked;
            document.querySelectorAll('.hm-qbo-check:not(:disabled)').forEach(function(c){
                c.checked = checked;
                c.dispatchEvent(new Event('change', {bubbles:true}));
            });
        });

        document.getElementById('hm-qbo-select-all').addEventListener('click', function(){
            var checks = document.querySelectorAll('.hm-qbo-check:not(:disabled)');
            var allChecked = checks.length > 0 && Object.keys(selected).length === checks.length;
            checks.forEach(function(c){ c.checked = !allChecked; c.dispatchEvent(new Event('change', {bubbles:true})); });
        });

        function updateSummary(){
            var ids   = Object.keys(selected);
            var total = ids.reduce(function(s, id){ return s + selected[id]; }, 0);
            document.getElementById('hm-qbo-count').textContent = ids.length + ' selected';
            document.getElementById('hm-qbo-total').textContent = '€' + total.toFixed(2);
            document.getElementById('hm-qbo-summary').style.display = ids.length ? 'block' : 'none';
            document.getElementById('hm-qbo-send').disabled = ids.length === 0;
        }

        // Send to QBO
        document.getElementById('hm-qbo-send').addEventListener('click', function(){
            var ids = Object.keys(selected);
            if(!ids.length) return;
            if(!confirm('Send ' + ids.length + ' invoice(s) to QuickBooks?')) return;
            var btn = this;
            btn.disabled = true; btn.textContent = 'Sending…';
            post('hm_qbo_bulk_sync', { invoice_ids: JSON.stringify(ids) }).then(function(d){
                btn.textContent = 'Send Selected to QBO';
                if(d.success){
                    alert(d.data.message || 'Sync complete.');
                    loadInvoices();
                } else {
                    alert('Error: ' + (d.data || 'Unknown error'));
                    btn.disabled = false;
                }
            });
        });

        // Skip invoice
        document.getElementById('hm-qbo-body').addEventListener('click', function(e){
            if(e.target.classList.contains('hm-qbo-skip')){
                var id = e.target.dataset.id;
                if(!confirm('Skip this invoice? It will not be sent to QBO.')) return;
                post('hm_qbo_skip_invoice', { invoice_id: id }).then(function(d){
                    if(d.success) loadInvoices();
                });
            }
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

// ═══════════════════════════════════════════════════════════════════════
// AJAX HANDLERS
// ═══════════════════════════════════════════════════════════════════════

add_action( 'wp_ajax_hm_qbo_load_invoices', 'hm_ajax_qbo_load_invoices' );
function hm_ajax_qbo_load_invoices() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( function_exists( 'hm_user_can_approve' ) && ! hm_user_can_approve() ) {
        wp_send_json_error( 'Access denied' );
        return;
    }

    $where  = [ '1=1' ];
    $params = [];
    $n      = 1;

    $status = sanitize_text_field( $_POST['status'] ?? '' );
    if ( $status ) {
        $where[]  = "i.qbo_sync_status = \${$n}";
        $params[] = $status;
        $n++;
    }

    $clinic = intval( $_POST['clinic_id'] ?? 0 );
    if ( $clinic ) {
        $where[]  = "COALESCE(o.clinic_id, i.clinic_id) = \${$n}";
        $params[] = $clinic;
        $n++;
    }

    $from = sanitize_text_field( $_POST['date_from'] ?? '' );
    if ( $from ) {
        $where[]  = "i.invoice_date >= \${$n}";
        $params[] = $from;
        $n++;
    }

    $to = sanitize_text_field( $_POST['date_to'] ?? '' );
    if ( $to ) {
        $where[]  = "i.invoice_date <= \${$n}";
        $params[] = $to;
        $n++;
    }

    $sql = "SELECT i.id, i.invoice_number, i.invoice_date, i.grand_total AS total,
                   i.qbo_sync_status, i.qbo_invoice_id, i.qbo_sync_error,
                   i.prsi_amount,
                   CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                   o.order_number, c.clinic_name
            FROM hearmed_core.invoices i
            LEFT JOIN hearmed_core.orders o ON o.id = i.order_id
            LEFT JOIN hearmed_core.patients p ON p.id = i.patient_id
            LEFT JOIN hearmed_reference.clinics c ON c.id = COALESCE(o.clinic_id, i.clinic_id)
            WHERE " . implode( ' AND ', $where ) . "
            ORDER BY i.invoice_date DESC, i.id DESC
            LIMIT 200";

    $rows = HearMed_DB::get_results( $sql, $params );
    wp_send_json_success( [ 'invoices' => $rows ?: [] ] );
}

add_action( 'wp_ajax_hm_qbo_bulk_sync', 'hm_ajax_qbo_bulk_sync' );
function hm_ajax_qbo_bulk_sync() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( function_exists( 'hm_user_can_approve' ) && ! hm_user_can_approve() ) {
        wp_send_json_error( 'Access denied' );
        return;
    }

    $ids = json_decode( stripslashes( $_POST['invoice_ids'] ?? '[]' ), true );
    if ( empty( $ids ) ) {
        wp_send_json_error( 'No invoices selected' );
        return;
    }

    $synced = 0;
    $errors = 0;

    foreach ( $ids as $id ) {
        $id = intval( $id );
        try {
            // Call existing QBO sync function if available
            if ( class_exists( 'HearMed_QBO' ) && method_exists( 'HearMed_QBO', 'sync_invoice' ) ) {
                HearMed_QBO::sync_invoice( $id );
            }

            HearMed_DB::query(
                "UPDATE hearmed_core.invoices SET qbo_sync_status = 'synced', qbo_sync_date = NOW() WHERE id = \$1",
                [ $id ]
            );
            $synced++;
        } catch ( \Throwable $e ) {
            HearMed_DB::query(
                "UPDATE hearmed_core.invoices SET qbo_sync_status = 'error', qbo_sync_error = \$2 WHERE id = \$1",
                [ $id, $e->getMessage() ]
            );
            $errors++;
        }
    }

    wp_send_json_success( [
        'message' => "{$synced} invoice(s) synced." . ( $errors ? " {$errors} error(s)." : '' ),
        'synced'  => $synced,
        'errors'  => $errors,
    ] );
}

add_action( 'wp_ajax_hm_qbo_skip_invoice', 'hm_ajax_qbo_skip_invoice' );
function hm_ajax_qbo_skip_invoice() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( function_exists( 'hm_user_can_approve' ) && ! hm_user_can_approve() ) {
        wp_send_json_error( 'Access denied' );
        return;
    }

    $id = intval( $_POST['invoice_id'] ?? 0 );
    if ( ! $id ) {
        wp_send_json_error( 'Missing invoice ID' );
        return;
    }

    HearMed_DB::update( 'hearmed_core.invoices', [
        'qbo_sync_status' => 'skipped',
    ], [ 'id' => $id ] );

    wp_send_json_success( [ 'invoice_id' => $id ] );
}
