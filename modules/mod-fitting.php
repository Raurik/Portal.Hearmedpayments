<?php
/**
 * HearMed Portal — Awaiting Fitting (Order Fulfilment Tracker)
 *
 * Shortcode: [hearmed_fitting]
 *
 * Shows hearing aid orders from Approved through to fitted + paid.
 *   Approved        → "Awaiting Order"
 *   Ordered         → "Awaiting Delivery"   + Receive in Branch
 *   Awaiting Fitting → "Awaiting Fitting"   + Fitted button
 *
 * Flow:
 *  1. C-Level approves → appears here as "Awaiting Order"
 *  2. Finance clicks Ordered in Approvals → "Awaiting Delivery"
 *  3. Staff clicks Receive in Branch → serial dialog → appointment check
 *  4. Staff clicks Fitted → payment dialog → receipt
 *
 * @package HearMed_Portal
 * @since   4.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'hearmed_fitting', 'hm_render_fitting_page' );
add_shortcode( 'hearmed_awaiting_fitting', 'hm_render_fitting_page' ); // alias

// ═══════════════════════════════════════════════════════════════
// RENDER
// ═══════════════════════════════════════════════════════════════
function hm_render_fitting_page() {
    if ( ! is_user_logged_in() ) return '<p>Please log in.</p>';

    $db = HearMed_DB::instance();

    // Counts by status
    $counts = $db->get_results(
        "SELECT o.current_status, COUNT(*) AS cnt
         FROM hearmed_core.orders o
         WHERE o.current_status IN ('Approved','Ordered','Awaiting Fitting')
           AND EXISTS (SELECT 1 FROM hearmed_core.order_items oi WHERE oi.order_id = o.id AND oi.item_type = 'product')
         GROUP BY o.current_status"
    );
    $count_map = [];
    $total = 0;
    if ($counts) {
        foreach ($counts as $c) {
            $count_map[$c->current_status] = (int)$c->cnt;
            $total += (int)$c->cnt;
        }
    }

    ob_start(); ?>
    <style>
    /* ── Awaiting Fitting — hmf- namespace ── */
    .hm-stats{display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;}
    .hm-stat{background:#fff;border-radius:10px;padding:14px 20px;flex:1;min-width:140px;border:1px solid #f1f5f9;box-shadow:0 1px 4px rgba(15,23,42,.03);}
    .hm-stat-label{font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:#94a3b8;font-weight:600;}
    .hm-stat-val{font-size:22px;font-weight:700;color:#0f172a;margin-top:2px;}
    .hm-stat-val.teal{color:var(--hm-teal);}
    .hm-stat-val.amber{color:#d97706;}
    .hm-stat-val.green{color:#059669;}

    .hm-tbl-wrap{background:#fff;border-radius:12px;border:1px solid #f1f5f9;box-shadow:0 2px 8px rgba(15,23,42,.04);overflow-x:auto;}
    .hm-table{width:100%;border-collapse:collapse;font-size:12px;}
    .hm-table th{text-align:left;padding:10px 14px;background:#f8fafc;color:#64748b;font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.3px;border-bottom:1px solid #e2e8f0;white-space:nowrap;}
    .hm-table td{padding:10px 14px;border-bottom:1px solid #f1f5f9;color:#334155;vertical-align:middle;}
    .hm-table tbody tr:hover{background:#f8fafc;}
    .hm-table tbody tr:last-child td{border-bottom:none;}

    .hmf-patient-num{font-weight:700;color:#0f172a;font-size:12px;}
    .hmf-patient-name{font-size:12px;color:#334155;}
    .hmf-order-num{font-weight:600;color:#0f172a;font-size:12px;font-family:monospace;}
    .hmf-product{font-size:12px;color:#334155;max-width:200px;}

    .hmf-prsi{display:inline-flex;align-items:center;gap:5px;font-size:11px;font-weight:600;}
    .hmf-prsi-dot{width:10px;height:10px;border-radius:50%;display:inline-block;}
    .hmf-prsi-dot.green{background:#10b981;}
    .hmf-prsi-dot.red{background:#ef4444;}

    .hm-status{display:inline-flex;align-items:center;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:600;white-space:nowrap;}
    .hm-status-awaiting-order{background:#fef3cd;color:#92400e;}
    .hm-status-awaiting-delivery{background:#dbeafe;color:#1e40af;}
    .hm-status-awaiting-fitting{background:#d1fae5;color:#065f46;}

    .hmf-fitting-date{font-size:12px;color:#334155;}
    .hmf-fitting-date.none{color:#94a3b8;font-style:italic;}

    .hm-btn{padding:6px 14px;border-radius:8px;font-size:11px;font-weight:600;cursor:pointer;border:none;transition:all .15s;white-space:nowrap;}
    .hm-btn-receive{background:var(--hm-teal);color:#fff;}
    .hm-btn-receive:hover{background:#0a9aa8;}
    .hm-btn-fitted{background:#059669;color:#fff;}
    .hm-btn-fitted:hover{background:#047857;}
    .hm-btn-receipt{background:#fff;color:#475569;border:1px solid #e2e8f0;}
    .hm-btn-receipt:hover{background:#f8fafc;border-color:var(--hm-teal);color:var(--hm-teal);}

    .hm-empty{text-align:center;padding:60px 20px;color:#94a3b8;font-size:13px;}
    .hm-empty-icon{font-size:32px;margin-bottom:8px;}

    /* Modals */
    .hm-modal-bg{display:none;position:fixed;inset:0;align-items:center;justify-content:center;padding:24px;background:radial-gradient(circle at top left,rgba(148,163,184,.45),rgba(15,23,42,.75));backdrop-filter:blur(8px);z-index:9000;}
    .hm-modal-bg.open{display:flex;}
    .hm-modal{background:#fff;border-radius:14px;box-shadow:0 25px 50px rgba(0,0,0,.15);width:100%;overflow:hidden;}
    .hm-modal-hd{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid #f1f5f9;}
    .hm-modal-hd h3{margin:0;font-size:14px;font-weight:600;color:#0f172a;}
    .hm-modal-body{padding:20px;}
    .hm-modal-ft{display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;border-top:1px solid #f1f5f9;background:#f8fafc;}

    .hm-form-group{margin-bottom:14px;}
    .hm-form-group label{display:block;font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.3px;margin-bottom:4px;}
    .hm-form-group input,.hm-form-group select,.hm-form-group textarea{width:100%;padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;color:#1e293b;transition:border-color .15s;}
    .hm-form-group input:focus,.hm-form-group select:focus{outline:none;border-color:var(--hm-teal);box-shadow:0 0 0 3px rgba(11,180,196,.1);}
    .hm-form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}

    .hmf-serial-item{background:#f8fafc;border-radius:8px;padding:12px;margin-bottom:10px;border:1px solid #f1f5f9;}
    .hmf-serial-item-title{font-weight:600;font-size:12px;color:#0f172a;margin-bottom:8px;}
    .hmf-serial-ear{display:inline-flex;padding:2px 8px;border-radius:4px;font-size:10px;font-weight:600;margin-left:6px;}
    .hmf-serial-ear-left{background:#dbeafe;color:#1e40af;}
    .hmf-serial-ear-right{background:#fce7f3;color:#9d174d;}
    .hmf-serial-ear-binaural{background:#d1fae5;color:#065f46;}

    .hmf-invoice-summary{background:#f8fafc;border-radius:8px;padding:14px;margin-bottom:14px;border:1px solid #f1f5f9;}
    .hmf-invoice-row{display:flex;justify-content:space-between;padding:3px 0;font-size:12px;}
    .hmf-invoice-row span:first-child{color:#64748b;}
    .hmf-invoice-row span:last-child{color:#0f172a;font-weight:500;}
    .hmf-invoice-total{font-size:14px;font-weight:700;border-top:1px solid #e2e8f0;padding-top:6px;margin-top:4px;}
    .hmf-invoice-total span:last-child{color:var(--hm-teal);}

    .hmf-prev-payments{margin:14px 0;}
    .hmf-prev-payments h4{font-size:11px;font-weight:600;color:#64748b;text-transform:uppercase;margin:0 0 6px;}
    .hmf-prev-pay-row{display:flex;justify-content:space-between;font-size:12px;padding:4px 0;border-bottom:1px solid #f1f5f9;}

    /* Filter bar — integrated toolbar */
    .hmf-toolbar{display:flex;align-items:center;gap:16px;padding:10px 16px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:16px;}
    .hmf-toolbar-label{font-size:11px;font-weight:600;color:#475569;white-space:nowrap;}
    .hmf-toolbar select{padding:7px 30px 7px 12px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;color:#1e293b;background:#fff;min-width:170px;appearance:none;-webkit-appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%2394a3b8'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 10px center;transition:border-color .15s;}
    .hmf-toolbar select:focus{outline:none;border-color:var(--hm-teal);box-shadow:0 0 0 2px rgba(11,180,196,.12);}
    .hmf-toolbar-sep{width:1px;height:24px;background:#e2e8f0;}
    .hmf-toolbar-reset{padding:6px 14px;border:none;border-radius:6px;font-size:11px;font-weight:600;color:#64748b;background:#e2e8f0;cursor:pointer;transition:all .15s;}
    .hmf-toolbar-reset:hover{background:var(--hm-teal);color:#fff;}

    /* Totals footer — clean summary strip */
    .hmf-summary{margin-top:20px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;}
    .hmf-summary-row{display:flex;align-items:center;}
    .hmf-summary-cell{flex:1;padding:14px 20px;text-align:center;position:relative;}
    .hmf-summary-cell + .hmf-summary-cell{border-left:1px solid #e2e8f0;}
    .hmf-summary-cell-label{font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:#64748b;font-weight:600;margin-bottom:2px;}
    .hmf-summary-cell-val{font-size:20px;font-weight:700;color:#0f172a;}
    .hmf-summary-cell-val.green{color:#059669;}
    .hmf-summary-cell-val.blue{color:#2563eb;}
    .hmf-summary-cell-val.purple{color:#7c3aed;}
    .hmf-summary-cell-sub{font-size:10px;color:#94a3b8;margin-top:2px;}
    </style>

    <div id="hm-app" class="hm-calendar" data-module="admin" data-view="fitting">
        <div class="hm-page">

            <!-- Stats -->
            <div class="hm-stats">
                <div class="hm-stat">
                    <div class="hm-stat-label">Awaiting Order</div>
                    <div class="hm-stat-val amber"><?php echo $count_map['Approved'] ?? 0; ?></div>
                </div>
                <div class="hm-stat">
                    <div class="hm-stat-label">Awaiting Delivery</div>
                    <div class="hm-stat-val teal"><?php echo $count_map['Ordered'] ?? 0; ?></div>
                </div>
                <div class="hm-stat">
                    <div class="hm-stat-label">Awaiting Fitting</div>
                    <div class="hm-stat-val green"><?php echo $count_map['Awaiting Fitting'] ?? 0; ?></div>
                </div>
                <div class="hm-stat">
                    <div class="hm-stat-label">Total Pipeline</div>
                    <div class="hm-stat-val"><?php echo $total; ?></div>
                </div>
            </div>

            <!-- Toolbar: Filters -->
            <div class="hmf-toolbar">
                <span class="hmf-toolbar-label">Filter by</span>
                <select id="hmf-filter-clinic" onchange="hmFitting.applyFilters()">
                    <option value="">All Clinics</option>
                </select>
                <select id="hmf-filter-dispenser" onchange="hmFitting.applyFilters()">
                    <option value="">All Dispensers</option>
                </select>
                <div class="hmf-toolbar-sep"></div>
                <button class="hmf-toolbar-reset" onclick="hmFitting.resetFilters()">Reset Filters</button>
            </div>

            <!-- Table loaded via AJAX -->
            <div id="hmf-content">
                <div class="hm-loading"><div class="hm-loading-dots"><div class="hm-loading-dot"></div><div class="hm-loading-dot"></div><div class="hm-loading-dot"></div></div><div class="hm-loading-text">Loading&hellip;</div></div>
            </div>

            <!-- Summary (populated by JS) -->
            <div id="hmf-totals" class="hmf-summary" style="display:none;"></div>
        </div>
    </div>

    <!-- ═════════ SERIAL NUMBER MODAL ═════════ -->
    <div id="hmf-serial-modal" class="hm-modal-bg">
        <div class="hm-modal hm-modal--md">
            <div class="hm-modal-hd">
                <h3>Receive in Branch — Enter Serial Numbers</h3>
                <button class="hm-close" onclick="hmFitting.closeSerial()">&times;</button>
            </div>
            <div class="hm-modal-body">
                <input type="hidden" id="hmf-serial-order-id">
                <div id="hmf-serial-items"><!-- JS populates --></div>
            </div>
            <div class="hm-modal-ft">
                <button class="hm-btn" onclick="hmFitting.closeSerial()">Cancel</button>
                <button class="hm-btn hm-btn--primary" id="hmf-serial-save" onclick="hmFitting.saveSerials()">Save &amp; Receive</button>
            </div>
        </div>
    </div>

    <!-- ═════════ NO FITTING APPOINTMENT MODAL ═════════ -->
    <div id="hmf-no-appt-modal" class="hm-modal-bg">
        <div class="hm-modal hm-modal--sm">
            <div class="hm-modal-hd">
                <h3>No Fitting Appointment Found</h3>
                <button class="hm-close" onclick="hmFitting.closeNoAppt()">&times;</button>
            </div>
            <div class="hm-modal-body">
                <div class="hm-notice hm-notice--warning" style="margin:0 0 14px;"><div class="hm-notice-body"><span class="hm-notice-icon">⚠</span>
                    This patient does not have a <strong>Fitting</strong> appointment booked.
                </div></div>
                <input type="hidden" id="hmf-no-appt-order-id">
                <div class="hm-form-group">
                    <label>Why is there no fitting appointment? <span class="hm-text--danger">*</span></label>
                    <textarea id="hmf-no-appt-reason" rows="3" placeholder="Please explain..."></textarea>
                </div>
            </div>
            <div class="hm-modal-ft">
                <button class="hm-btn" onclick="hmFitting.closeNoAppt()">Cancel</button>
                <button class="hm-btn hm-btn--primary" onclick="hmFitting.saveNoApptReason()">Save Reason &amp; Continue</button>
            </div>
        </div>
    </div>

    <!-- ═════════ PAYMENT / FITTED MODAL ═════════ -->
    <div id="hmf-payment-modal" class="hm-modal-bg">
        <div class="hm-modal hm-modal--md">
            <div class="hm-modal-hd">
                <h3>Record Fitting &amp; Payment</h3>
                <button class="hm-close" onclick="hmFitting.closePayment()">&times;</button>
            </div>
            <div class="hm-modal-body">
                <input type="hidden" id="hmf-pay-order-id">
                <input type="hidden" id="hmf-pay-invoice-id">

                <div id="hmf-invoice-info"><!-- JS populates --></div>

                <div class="hm-form-row">
                    <div class="hm-form-group">
                        <label>Payment Method</label>
                        <select id="hmf-pay-method">
                            <option value="Card">Card</option>
                            <option value="Cash">Cash</option>
                            <option value="Cheque">Cheque</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="hm-form-group">
                        <label>Amount (€)</label>
                        <input type="number" id="hmf-pay-amount" step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>
                <div id="hmf-pay-result"></div>
            </div>
            <div class="hm-modal-ft">
                <button class="hm-btn" onclick="hmFitting.closePayment()">Cancel</button>
                <button class="hm-btn hm-btn--primary" id="hmf-pay-save" onclick="hmFitting.recordPayment()">Record Payment</button>
            </div>
        </div>
    </div>

    <!-- ═════════ RECEIPT SUCCESS MODAL ═════════ -->
    <div id="hmf-receipt-modal" class="hm-modal-bg">
        <div class="hm-modal hm-modal--sm">
            <div class="hm-modal-hd">
                <h3>Payment Complete</h3>
                <button class="hm-close" onclick="hmFitting.closeReceipt()">&times;</button>
            </div>
            <div class="hm-modal-body" style="text-align:center;">
                <div style="font-size:40px;margin-bottom:8px;">&#10004;</div>
                <p style="font-size:14px;font-weight:600;color:#065f46;margin:0 0 6px;">Paid in Full</p>
                <p style="font-size:12px;color:#64748b;margin:0 0 16px;" id="hmf-receipt-summary"></p>
                <button class="hm-btn hm-btn-receipt" onclick="hmFitting.printReceipt()" style="padding:10px 24px;font-size:13px;">
                    &#128424; Print Receipt
                </button>
            </div>
            <div class="hm-modal-ft">
                <button class="hm-btn hm-btn--primary" onclick="hmFitting.closeReceipt()">Done</button>
            </div>
        </div>
    </div>

    <script>
    var hmFitting = {

        // ── DATA ──
        orders: [],
        filtered: [],

        // ── PRSI CYCLE DATE HELPERS ──
        // Returns the Nth weekday (0=Sun..6=Sat) of a given month/year
        nthWeekday: function(year, month, weekday, n) {
            var d = new Date(year, month, 1);
            var count = 0;
            while (d.getMonth() === month) {
                if (d.getDay() === weekday) {
                    count++;
                    if (count === n) return new Date(d);
                }
                d.setDate(d.getDate() + 1);
            }
            return null;
        },

        // PRSI claiming runs from 2nd Tuesday of current month
        // to 1st Monday of next month (inclusive)
        getPRSICycleDates: function() {
            var now = new Date();
            var y = now.getFullYear();
            var m = now.getMonth();

            // 2nd Tuesday of this month (weekday 2 = Tuesday)
            var secondTuesday = this.nthWeekday(y, m, 2, 2);

            // 1st Monday of next month (weekday 1 = Monday)
            var nextM = m + 1;
            var nextY = y;
            if (nextM > 11) { nextM = 0; nextY++; }
            var firstMondayNext = this.nthWeekday(nextY, nextM, 1, 1);

            return { start: secondTuesday, end: firstMondayNext };
        },

        // ── LOAD TABLE ──
        load: function() {
            var el = document.getElementById('hmf-content');
            el.innerHTML = '<div class="hm-loading"><div class="hm-loading-dots"><div class="hm-loading-dot"></div><div class="hm-loading-dot"></div><div class="hm-loading-dot"></div></div><div class="hm-loading-text">Loading&hellip;</div></div>';
            var self = this;
            jQuery.post(HM.ajax_url, {
                action: 'hm_fitting_load',
                nonce: HM.nonce
            }, function(r) {
                if (r.success) {
                    self.orders = r.data.orders || [];
                    self.populateFilters();
                    self.applyFilters();
                } else {
                    el.innerHTML = '<div class="hm-empty"><div class="hm-empty-icon">&#9888;</div>Error loading data.</div>';
                }
            });
        },

        populateFilters: function() {
            var clinics = {};
            var dispensers = {};
            this.orders.forEach(function(o) {
                if (o.clinic_id && o.clinic_name) clinics[o.clinic_id] = o.clinic_name;
                if (o.staff_id && o.dispenser_name) dispensers[o.staff_id] = o.dispenser_name;
            });

            var cSel = document.getElementById('hmf-filter-clinic');
            var cVal = cSel.value;
            cSel.innerHTML = '<option value="">All Clinics</option>';
            Object.keys(clinics).sort(function(a,b){ return clinics[a].localeCompare(clinics[b]); }).forEach(function(id) {
                cSel.innerHTML += '<option value="' + id + '">' + hmFE(clinics[id]) + '</option>';
            });
            cSel.value = cVal;

            var dSel = document.getElementById('hmf-filter-dispenser');
            var dVal = dSel.value;
            dSel.innerHTML = '<option value="">All Dispensers</option>';
            Object.keys(dispensers).sort(function(a,b){ return dispensers[a].localeCompare(dispensers[b]); }).forEach(function(id) {
                dSel.innerHTML += '<option value="' + id + '">' + hmFE(dispensers[id]) + '</option>';
            });
            dSel.value = dVal;
        },

        applyFilters: function() {
            var clinicId = document.getElementById('hmf-filter-clinic').value;
            var staffId  = document.getElementById('hmf-filter-dispenser').value;

            this.filtered = this.orders.filter(function(o) {
                if (clinicId && String(o.clinic_id) !== clinicId) return false;
                if (staffId  && String(o.staff_id)  !== staffId)  return false;
                return true;
            });

            this.renderTable({ orders: this.filtered });
            this.renderTotals(this.filtered);
        },

        resetFilters: function() {
            document.getElementById('hmf-filter-clinic').value = '';
            document.getElementById('hmf-filter-dispenser').value = '';
            this.applyFilters();
        },

        renderTable: function(data) {
            var el = document.getElementById('hmf-content');
            var orders = data.orders || [];

            if (!orders.length) {
                el.innerHTML = '<div class="hm-empty"><div class="hm-empty-icon">&#128588;</div>No hearing aid orders in the pipeline — all clear!</div>';
                document.getElementById('hmf-totals').style.display = 'none';
                return;
            }

            var html = '<div class="hm-tbl-wrap"><table class="hm-table"><thead><tr>';
            html += '<th>Patient #</th>';
            html += '<th>Patient Name</th>';
            html += '<th>Order #</th>';
            html += '<th>Hearing Aid</th>';
            html += '<th>Clinic</th>';
            html += '<th>Dispenser</th>';
            html += '<th>PRSI</th>';
            html += '<th>Order Status</th>';
            html += '<th>Fitting Appt</th>';
            html += '<th style="width:150px;">Actions</th>';
            html += '</tr></thead><tbody>';

            orders.forEach(function(o) {
                var statusClass = '';
                var statusLabel = '';
                if (o.current_status === 'Approved') {
                    statusClass = 'hm-status-awaiting-order';
                    statusLabel = 'Awaiting Order';
                } else if (o.current_status === 'Ordered') {
                    statusClass = 'hm-status-awaiting-delivery';
                    statusLabel = 'Awaiting Delivery';
                } else {
                    statusClass = 'hm-status-awaiting-fitting';
                    statusLabel = 'Awaiting Fitting';
                }

                var prsiDot = o.prsi_applicable
                    ? '<span class="hmf-prsi"><span class="hmf-prsi-dot green"></span> Yes</span>'
                    : '<span class="hmf-prsi"><span class="hmf-prsi-dot red"></span> No</span>';

                var fittingDate = o.fitting_date
                    ? '<span class="hmf-fitting-date">' + hmFE(o.fitting_date) + '</span>'
                    : '<span class="hmf-fitting-date none">Not booked</span>';

                var actions = '';
                if (o.current_status === 'Ordered') {
                    actions = '<button class="hm-btn hm-btn-receive" onclick="hmFitting.openSerial(' + o.id + ')">&#128230; Receive in Branch</button>';
                } else if (o.current_status === 'Awaiting Fitting') {
                    actions = '<button class="hm-btn hm-btn-fitted" onclick="hmFitting.openPayment(' + o.id + ')">&#9989; Fitted</button>';
                } else {
                    actions = '<span style="color:#94a3b8;font-size:11px;">Awaiting finance</span>';
                }

                html += '<tr>';
                html += '<td><span class="hmf-patient-num">' + hmFE(o.patient_number) + '</span></td>';
                html += '<td><span class="hmf-patient-name">' + hmFE(o.patient_name) + '</span></td>';
                html += '<td><span class="hmf-order-num">' + hmFE(o.order_number) + '</span></td>';
                html += '<td><span class="hmf-product">' + hmFE(o.product_names) + '</span></td>';
                html += '<td>' + hmFE(o.clinic_name) + '</td>';
                html += '<td>' + hmFE(o.dispenser_name) + '</td>';
                html += '<td>' + prsiDot + '</td>';
                html += '<td><span class="hm-status ' + statusClass + '">' + statusLabel + '</span></td>';
                html += '<td>' + fittingDate + '</td>';
                html += '<td>' + actions + '</td>';
                html += '</tr>';
            });

            html += '</tbody></table></div>';
            el.innerHTML = html;
        },

        renderTotals: function(orders) {
            var container = document.getElementById('hmf-totals');

            // 1. Total Awaiting Fitting (status = 'Awaiting Fitting')
            var awaitingFitting = orders.filter(function(o) { return o.current_status === 'Awaiting Fitting'; });
            var totalAwaiting = awaitingFitting.length;

            // 2. Fitting before end of month
            var now = new Date();
            var endOfMonth = new Date(now.getFullYear(), now.getMonth() + 1, 0, 23, 59, 59);
            var fittingBeforeEOM = awaitingFitting.filter(function(o) {
                if (!o.fitting_date_raw) return false;
                var fd = new Date(o.fitting_date_raw);
                return fd <= endOfMonth;
            }).length;

            // 3. PRSI to claim — orders fitted (PRSI applicable) within the current PRSI cycle
            // PRSI cycle: 2nd Tuesday of this month → 1st Monday of next month
            var cycle = this.getPRSICycleDates();
            var prsiCount = 0;
            if (cycle.start && cycle.end) {
                // Count Awaiting Fitting orders that are PRSI applicable
                // and have a fitting date within the PRSI cycle window
                prsiCount = awaitingFitting.filter(function(o) {
                    if (!o.prsi_applicable) return false;
                    if (!o.fitting_date_raw) return true; // PRSI applicable but no date = still in window
                    var fd = new Date(o.fitting_date_raw);
                    return fd >= cycle.start && fd <= cycle.end;
                }).length;
            }

            var cycleLabel = '';
            if (cycle.start && cycle.end) {
                cycleLabel = hmFD(cycle.start) + ' – ' + hmFD(cycle.end);
            }

            var monthName = now.toLocaleString('default', { month: 'long' });

            container.innerHTML =
                '<div class="hmf-summary-row">' +
                    '<div class="hmf-summary-cell">' +
                        '<div class="hmf-summary-cell-label">Total Awaiting Fitting</div>' +
                        '<div class="hmf-summary-cell-val green">' + totalAwaiting + '</div>' +
                    '</div>' +
                    '<div class="hmf-summary-cell">' +
                        '<div class="hmf-summary-cell-label">Fitting Before End of ' + hmFE(monthName) + '</div>' +
                        '<div class="hmf-summary-cell-val blue">' + fittingBeforeEOM + '</div>' +
                        '<div class="hmf-summary-cell-sub">Scheduled before ' + hmFD(endOfMonth) + '</div>' +
                    '</div>' +
                    '<div class="hmf-summary-cell">' +
                        '<div class="hmf-summary-cell-label">PRSI to Claim</div>' +
                        '<div class="hmf-summary-cell-val purple">' + prsiCount + '</div>' +
                        '<div class="hmf-summary-cell-sub">' + hmFE(cycleLabel) + '</div>' +
                    '</div>' +
                '</div>';

            container.style.display = 'block';
        },

        // ── SERIAL NUMBER MODAL ──
        openSerial: function(orderId) {
            var order = this.orders.find(function(o) { return o.id === orderId; });
            if (!order) return;

            document.getElementById('hmf-serial-order-id').value = orderId;
            var container = document.getElementById('hmf-serial-items');
            var html = '';

            var items = order.items || [];
            if (!items.length) {
                html = '<p style="color:#94a3b8;font-size:12px;">No hearing aid items found on this order.</p>';
            } else {
                items.forEach(function(it, idx) {
                    var earLabel = it.ear_side || 'Unknown';
                    var earClass = earLabel === 'Left' ? 'hmf-serial-ear-left' : (earLabel === 'Right' ? 'hmf-serial-ear-right' : 'hmf-serial-ear-binaural');

                    html += '<div class="hmf-serial-item">';
                    html += '<div class="hmf-serial-item-title">' + hmFE(it.product_name) + ' <span class="hmf-serial-ear ' + earClass + '">' + hmFE(earLabel) + '</span></div>';

                    if (earLabel === 'Left' || earLabel === 'Binaural') {
                        html += '<div class="hm-form-group">';
                        html += '<label>Left Ear Serial Number</label>';
                        html += '<input type="text" class="hmf-serial-input" data-item-id="' + it.id + '" data-ear="left" data-product-id="' + it.product_id + '" placeholder="Enter serial number...">';
                        html += '</div>';
                    }
                    if (earLabel === 'Right' || earLabel === 'Binaural') {
                        html += '<div class="hm-form-group">';
                        html += '<label>Right Ear Serial Number</label>';
                        html += '<input type="text" class="hmf-serial-input" data-item-id="' + it.id + '" data-ear="right" data-product-id="' + it.product_id + '" placeholder="Enter serial number...">';
                        html += '</div>';
                    }
                    // If ear_side not set, ask for serial + ear choice
                    if (!earLabel || earLabel === 'Unknown') {
                        html += '<div class="hm-form-row">';
                        html += '<div class="hm-form-group"><label>Serial Number</label>';
                        html += '<input type="text" class="hmf-serial-input" data-item-id="' + it.id + '" data-ear="single" data-product-id="' + it.product_id + '" placeholder="Enter serial number..."></div>';
                        html += '<div class="hm-form-group"><label>Which Ear?</label>';
                        html += '<select class="hmf-serial-ear-select" data-item-id="' + it.id + '"><option value="Left">Left</option><option value="Right">Right</option></select></div>';
                        html += '</div>';
                    }

                    html += '</div>';
                });
            }

            container.innerHTML = html;
            document.getElementById('hmf-serial-modal').classList.add('open');
        },

        closeSerial: function() {
            document.getElementById('hmf-serial-modal').classList.remove('open');
        },

        saveSerials: function() {
            var orderId = document.getElementById('hmf-serial-order-id').value;
            var inputs = document.querySelectorAll('.hmf-serial-input');
            var serials = [];

            inputs.forEach(function(inp) {
                var val = inp.value.trim();
                if (!val) return;
                var ear = inp.getAttribute('data-ear');
                var itemId = inp.getAttribute('data-item-id');
                var productId = inp.getAttribute('data-product-id');

                // For 'single' ear items, get ear from dropdown
                if (ear === 'single') {
                    var sel = document.querySelector('.hmf-serial-ear-select[data-item-id="' + itemId + '"]');
                    ear = sel ? sel.value.toLowerCase() : 'left';
                }

                serials.push({
                    item_id: itemId,
                    product_id: productId,
                    ear: ear,
                    serial: val
                });
            });

            if (!serials.length) {
                alert('Please enter at least one serial number.');
                return;
            }

            var btn = document.getElementById('hmf-serial-save');
            btn.textContent = 'Saving...';
            btn.disabled = true;
            var self = this;

            jQuery.post(HM.ajax_url, {
                action: 'hm_fitting_receive',
                nonce: HM.nonce,
                order_id: orderId,
                serials: JSON.stringify(serials)
            }, function(r) {
                btn.textContent = 'Save & Receive';
                btn.disabled = false;

                if (r.success) {
                    self.closeSerial();

                    if (!r.data.has_fitting_appointment) {
                        // No fitting appointment — ask why
                        document.getElementById('hmf-no-appt-order-id').value = orderId;
                        document.getElementById('hmf-no-appt-reason').value = '';
                        document.getElementById('hmf-no-appt-modal').classList.add('open');
                    } else {
                        self.load(); // Refresh table
                    }
                } else {
                    alert(r.data && r.data.msg ? r.data.msg : 'Error receiving order');
                }
            });
        },

        // ── NO APPOINTMENT MODAL ──
        closeNoAppt: function() {
            document.getElementById('hmf-no-appt-modal').classList.remove('open');
            this.load();
        },

        saveNoApptReason: function() {
            var orderId = document.getElementById('hmf-no-appt-order-id').value;
            var reason = document.getElementById('hmf-no-appt-reason').value.trim();
            if (!reason) { alert('Please provide a reason.'); return; }

            var self = this;
            jQuery.post(HM.ajax_url, {
                action: 'hm_fitting_no_appt_reason',
                nonce: HM.nonce,
                order_id: orderId,
                reason: reason
            }, function(r) {
                self.closeNoAppt();
                self.load();
            });
        },

        // ── PAYMENT / FITTED MODAL ──
        openPayment: function(orderId) {
            document.getElementById('hmf-pay-order-id').value = orderId;
            document.getElementById('hmf-pay-result').innerHTML = '';
            document.getElementById('hmf-pay-save').style.display = '';
            document.getElementById('hmf-invoice-info').innerHTML = '<div class="hm-loading"><div class="hm-loading-dots"><div class="hm-loading-dot"></div><div class="hm-loading-dot"></div><div class="hm-loading-dot"></div></div><div class="hm-loading-text">Loading&hellip;</div></div>';
            document.getElementById('hmf-payment-modal').classList.add('open');

            var self = this;
            jQuery.post(HM.ajax_url, {
                action: 'hm_fitting_load_invoice',
                nonce: HM.nonce,
                order_id: orderId
            }, function(r) {
                if (r.success) {
                    self.renderInvoiceInfo(r.data);
                } else {
                    document.getElementById('hmf-invoice-info').innerHTML = '<div class="hm-notice hm-notice--warning"><div class="hm-notice-body"><span class="hm-notice-icon">⚠</span> Could not load invoice details.</div></div>';
                }
            });
        },

        renderInvoiceInfo: function(data) {
            var html = '';
            html += '<div class="hmf-invoice-summary">';
            html += '<div class="hmf-invoice-row"><span>Patient</span><span>' + hmFE(data.patient_name) + '</span></div>';
            html += '<div class="hmf-invoice-row"><span>Invoice</span><span>' + hmFE(data.invoice_number || 'Auto-generated') + '</span></div>';
            html += '<div class="hmf-invoice-row"><span>Order</span><span>' + hmFE(data.order_number) + '</span></div>';
            html += '<div class="hmf-invoice-row"><span>Subtotal</span><span>&euro;' + hmFN(data.subtotal) + '</span></div>';
            if (data.discount_total > 0) {
                html += '<div class="hmf-invoice-row"><span>Discount</span><span class="hm-text--danger">-&euro;' + hmFN(data.discount_total) + '</span></div>';
            }
            html += '<div class="hmf-invoice-row"><span>VAT</span><span>&euro;' + hmFN(data.vat_total) + '</span></div>';
            if (data.prsi_applicable) {
                html += '<div class="hmf-invoice-row"><span>PRSI Grant</span><span class="hm-text--teal">-&euro;' + hmFN(data.prsi_amount) + '</span></div>';
            }
            html += '<div class="hmf-invoice-row hmf-invoice-total"><span>Grand Total</span><span>&euro;' + hmFN(data.grand_total) + '</span></div>';
            html += '<div class="hmf-invoice-row hmf-invoice-total"><span>Balance Remaining</span><span>&euro;' + hmFN(data.balance_remaining) + '</span></div>';
            html += '</div>';

            if (data.payments && data.payments.length) {
                html += '<div class="hmf-prev-payments"><h4>Previous Payments</h4>';
                data.payments.forEach(function(pm) {
                    html += '<div class="hmf-prev-pay-row"><span>' + hmFE(pm.payment_date) + ' — ' + hmFE(pm.payment_method) + '</span><span>&euro;' + hmFN(pm.amount) + '</span></div>';
                });
                html += '</div>';
            }

            document.getElementById('hmf-invoice-info').innerHTML = html;
            document.getElementById('hmf-pay-invoice-id').value = data.invoice_id || '';
            document.getElementById('hmf-pay-amount').value = parseFloat(data.balance_remaining || 0).toFixed(2);
        },

        closePayment: function() {
            document.getElementById('hmf-payment-modal').classList.remove('open');
        },

        recordPayment: function() {
            var orderId = document.getElementById('hmf-pay-order-id').value;
            var invoiceId = document.getElementById('hmf-pay-invoice-id').value;
            var method = document.getElementById('hmf-pay-method').value;
            var amount = document.getElementById('hmf-pay-amount').value;

            if (!amount || parseFloat(amount) <= 0) {
                alert('Please enter a valid payment amount.');
                return;
            }

            var btn = document.getElementById('hmf-pay-save');
            btn.textContent = 'Processing...';
            btn.disabled = true;
            var self = this;

            jQuery.post(HM.ajax_url, {
                action: 'hm_fitting_record_payment',
                nonce: HM.nonce,
                order_id: orderId,
                invoice_id: invoiceId,
                payment_method: method,
                amount: amount
            }, function(r) {
                btn.textContent = 'Record Payment';
                btn.disabled = false;

                if (r.success) {
                    if (r.data.paid_in_full) {
                        // Show receipt modal
                        self.closePayment();
                        self._currentReceiptOrderId = orderId;
                        document.getElementById('hmf-receipt-summary').textContent =
                            'Order ' + r.data.order_number + ' — €' + hmFN(r.data.grand_total) + ' paid in full.';
                        document.getElementById('hmf-receipt-modal').classList.add('open');
                    } else {
                        // Partial payment — update the invoice info
                        document.getElementById('hmf-pay-result').innerHTML =
                            '<div class="hm-notice hm-notice--success"><div class="hm-notice-body"><span class="hm-notice-icon">✓</span> Payment of &euro;' + hmFN(amount) +
                            ' recorded. Balance remaining: &euro;' + hmFN(r.data.balance_remaining) + '</div></div>';
                        // Refresh invoice info
                        self.openPayment(orderId);
                    }
                } else {
                    document.getElementById('hmf-pay-result').innerHTML =
                        '<div class="hm-notice hm-notice--warning"><div class="hm-notice-body"><span class="hm-notice-icon">⚠</span> ' + (r.data && r.data.msg ? hmFE(r.data.msg) : 'Error recording payment') + '</div></div>';
                }
            });
        },

        // ── RECEIPT ──
        closeReceipt: function() {
            document.getElementById('hmf-receipt-modal').classList.remove('open');
            this.load();
        },

        printReceipt: function() {
            var orderId = this._currentReceiptOrderId;
            if (orderId) {
                window.open(HM.ajax_url + '?action=hm_fitting_receipt&nonce=' + HM.nonce + '&order_id=' + orderId, '_blank');
            }
        }
    };

    // Helpers
    function hmFE(s) { if (!s) return ''; var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
    function hmFN(n) { return parseFloat(n || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
    function hmFD(dt) {
        if (!dt) return '';
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return dt.getDate() + ' ' + months[dt.getMonth()] + ' ' + dt.getFullYear();
    }

    jQuery(function() { hmFitting.load(); });
    </script>
    <?php
    return ob_get_clean();
}

// ═══════════════════════════════════════════════════════════════
// AJAX: Load fitting pipeline
// ═══════════════════════════════════════════════════════════════
add_action('wp_ajax_hm_fitting_load', 'hm_ajax_fitting_load');

function hm_ajax_fitting_load() {
    check_ajax_referer('hm_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['msg' => 'Access denied']); return; }

    $db = HearMed_DB::instance();

    $orders = $db->get_results(
        "SELECT o.id, o.order_number, o.patient_id, o.current_status,
                o.prsi_applicable, o.grand_total, o.order_date, o.invoice_id,
                o.clinic_id, o.staff_id,
                p.patient_number, p.first_name AS p_first, p.last_name AS p_last,
                c.clinic_name,
                CONCAT(s.first_name, ' ', s.last_name) AS dispenser_name,
                (SELECT string_agg(DISTINCT pr.product_name, ', ')
                 FROM hearmed_core.order_items oi
                 JOIN hearmed_reference.products pr ON pr.id = oi.item_id AND oi.item_type = 'product'
                 WHERE oi.order_id = o.id
                ) AS product_names,
                (SELECT a.appointment_date
                 FROM hearmed_core.appointments a
                 JOIN hearmed_reference.appointment_types at2 ON at2.id = a.appointment_type_id
                 WHERE a.patient_id = o.patient_id
                   AND at2.type_name ILIKE '%fitting%'
                   AND a.appointment_status NOT IN ('Cancelled','No Show')
                   AND a.appointment_date >= CURRENT_DATE
                 ORDER BY a.appointment_date ASC
                 LIMIT 1
                ) AS fitting_date
         FROM hearmed_core.orders o
         JOIN hearmed_core.patients p ON p.id = o.patient_id
         JOIN hearmed_reference.clinics c ON c.id = o.clinic_id
         LEFT JOIN hearmed_reference.staff s ON s.id = o.staff_id
         WHERE o.current_status IN ('Approved','Ordered','Awaiting Fitting')
           AND EXISTS (
               SELECT 1 FROM hearmed_core.order_items oi
               WHERE oi.order_id = o.id AND oi.item_type = 'product'
           )
         ORDER BY
             CASE o.current_status
                 WHEN 'Awaiting Fitting' THEN 1
                 WHEN 'Ordered' THEN 2
                 WHEN 'Approved' THEN 3
             END,
             o.created_at ASC"
    );

    $result = [];
    foreach ($orders ?: [] as $o) {
        // Get product items for serial dialog (only for Ordered status)
        $items = [];
        if ($o->current_status === 'Ordered') {
            $items_raw = $db->get_results(
                "SELECT oi.id, oi.item_id AS product_id, oi.ear_side,
                        COALESCE(pr.product_name, oi.item_description) AS product_name
                 FROM hearmed_core.order_items oi
                 LEFT JOIN hearmed_reference.products pr ON pr.id = oi.item_id AND oi.item_type = 'product'
                 WHERE oi.order_id = \$1 AND oi.item_type = 'product'
                 ORDER BY oi.line_number",
                [$o->id]
            );
            foreach ($items_raw ?: [] as $it) {
                $items[] = [
                    'id'           => (int)$it->id,
                    'product_id'   => (int)$it->product_id,
                    'ear_side'     => $it->ear_side ?? '',
                    'product_name' => $it->product_name ?? '',
                ];
            }
        }

        $result[] = [
            'id'              => (int)$o->id,
            'order_number'    => $o->order_number,
            'patient_number'  => $o->patient_number ?? '',
            'patient_name'    => trim(($o->p_first ?? '') . ' ' . ($o->p_last ?? '')),
            'patient_id'      => (int)$o->patient_id,
            'dispenser_name'  => $o->dispenser_name ?? '',
            'clinic_name'     => $o->clinic_name ?? '',
            'clinic_id'       => isset($o->clinic_id) ? (int)$o->clinic_id : null,
            'staff_id'        => isset($o->staff_id) ? (int)$o->staff_id : null,
            'current_status'  => $o->current_status,
            'prsi_applicable' => (bool)$o->prsi_applicable,
            'product_names'   => $o->product_names ?? '',
            'fitting_date'    => $o->fitting_date ? date('d M Y', strtotime($o->fitting_date)) : '',
            'fitting_date_raw'=> $o->fitting_date ?? '',
            'grand_total'     => (float)($o->grand_total ?? 0),
            'invoice_id'      => $o->invoice_id ? (int)$o->invoice_id : null,
            'items'           => $items,
        ];
    }

    wp_send_json_success(['orders' => $result]);
}

// ═══════════════════════════════════════════════════════════════
// AJAX: Receive in Branch (serial numbers + status update)
// ═══════════════════════════════════════════════════════════════
add_action('wp_ajax_hm_fitting_receive', 'hm_ajax_fitting_receive');

function hm_ajax_fitting_receive() {
    check_ajax_referer('hm_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['msg' => 'Access denied']); return; }

    $db       = HearMed_DB::instance();
    $order_id = intval($_POST['order_id'] ?? 0);
    $serials  = json_decode(stripslashes($_POST['serials'] ?? '[]'), true);
    $uid      = get_current_user_id();
    $now      = current_time('Y-m-d H:i:s');

    $order = $db->get_row("SELECT * FROM hearmed_core.orders WHERE id = \$1", [$order_id]);
    if (!$order || $order->current_status !== 'Ordered') {
        wp_send_json_error(['msg' => 'Order not found or not in Ordered state']);
        return;
    }

    $staff_id = $db->get_var("SELECT id FROM hearmed_reference.staff WHERE wp_user_id = \$1", [$uid]);

    // Group serials by product_id to create patient_devices records
    $by_product = [];
    foreach ($serials as $s) {
        $pid = intval($s['product_id']);
        if (!isset($by_product[$pid])) {
            $by_product[$pid] = ['left' => null, 'right' => null];
        }
        $ear = strtolower($s['ear']);
        if ($ear === 'left') {
            $by_product[$pid]['left'] = sanitize_text_field($s['serial']);
        } elseif ($ear === 'right') {
            $by_product[$pid]['right'] = sanitize_text_field($s['serial']);
        }
    }

    // Insert patient_devices records
    foreach ($by_product as $product_id => $sr) {
        $device_id = $db->insert('hearmed_core.patient_devices', [
            'patient_id'          => $order->patient_id,
            'product_id'          => $product_id,
            'serial_number_left'  => $sr['left'],
            'serial_number_right' => $sr['right'],
            'device_status'       => 'Active',
            'created_by'          => $staff_id ?: $uid,
        ]);
    }

    // Update order status → Awaiting Fitting
    $db->query(
        "UPDATE hearmed_core.orders SET current_status = 'Awaiting Fitting', received_date = \$1, received_by = \$2, updated_at = \$1 WHERE id = \$3",
        [$now, $staff_id ?: $uid, $order_id]
    );

    // Status history: Ordered → Received → Awaiting Fitting
    $db->query(
        "INSERT INTO hearmed_core.order_status_history (order_id, from_status, to_status, changed_by, changed_at, notes) VALUES (\$1, \$2, \$3, \$4, \$5, \$6)",
        [$order_id, 'Ordered', 'Awaiting Fitting', $staff_id ?: $uid, $now, 'Received in branch — serial numbers entered']
    );

    // Create/update fitting_queue entry
    $existing_fq = $db->get_row("SELECT id FROM hearmed_core.fitting_queue WHERE order_id = \$1", [$order_id]);
    if (!$existing_fq) {
        $db->insert('hearmed_core.fitting_queue', [
            'patient_id'    => $order->patient_id,
            'order_id'      => $order_id,
            'invoice_id'    => $order->invoice_id,
            'clinic_id'     => $order->clinic_id,
            'staff_id'      => $order->staff_id,
            'queue_status'  => 'Awaiting',
            'prsi_applicable' => $order->prsi_applicable,
            'received_date' => $now,
            'received_by'   => $staff_id ?: $uid,
            'created_by'    => $staff_id ?: $uid,
        ]);
    } else {
        $db->query(
            "UPDATE hearmed_core.fitting_queue SET queue_status = 'Awaiting', received_date = \$1, received_by = \$2, updated_at = \$1 WHERE order_id = \$3",
            [$now, $staff_id ?: $uid, $order_id]
        );
    }

    // Check for fitting appointment
    $fitting_appt = $db->get_row(
        "SELECT a.id, a.appointment_date
         FROM hearmed_core.appointments a
         JOIN hearmed_reference.appointment_types at2 ON at2.id = a.appointment_type_id
         WHERE a.patient_id = \$1
           AND at2.type_name ILIKE '%fitting%'
           AND a.appointment_status NOT IN ('Cancelled','No Show')
           AND a.appointment_date >= CURRENT_DATE
         ORDER BY a.appointment_date ASC
         LIMIT 1",
        [$order->patient_id]
    );

    // Update fitting_queue with appointment info if found
    if ($fitting_appt) {
        $db->query(
            "UPDATE hearmed_core.fitting_queue SET fitting_appointment_id = \$1, fitting_date = \$2 WHERE order_id = \$3",
            [$fitting_appt->id, $fitting_appt->appointment_date, $order_id]
        );
    }

    if (function_exists('hm_audit_log')) {
        hm_audit_log($uid, 'receive_in_branch', 'order', $order_id, ['status' => 'Ordered'], ['status' => 'Awaiting Fitting']);
    }

    wp_send_json_success([
        'order_id'               => $order_id,
        'has_fitting_appointment'=> !empty($fitting_appt),
        'fitting_date'           => $fitting_appt ? date('d M Y', strtotime($fitting_appt->appointment_date)) : null,
    ]);
}

// ═══════════════════════════════════════════════════════════════
// AJAX: Save no-appointment reason
// ═══════════════════════════════════════════════════════════════
add_action('wp_ajax_hm_fitting_no_appt_reason', 'hm_ajax_fitting_no_appt_reason');

function hm_ajax_fitting_no_appt_reason() {
    check_ajax_referer('hm_nonce', 'nonce');

    $db       = HearMed_DB::instance();
    $order_id = intval($_POST['order_id'] ?? 0);
    $reason   = sanitize_textarea_field($_POST['reason'] ?? '');

    $db->query(
        "UPDATE hearmed_core.fitting_queue SET no_fitting_reason = \$1, updated_at = NOW() WHERE order_id = \$2",
        [$reason, $order_id]
    );

    wp_send_json_success();
}

// ═══════════════════════════════════════════════════════════════
// AJAX: Load invoice for payment dialog
// ═══════════════════════════════════════════════════════════════
add_action('wp_ajax_hm_fitting_load_invoice', 'hm_ajax_fitting_load_invoice');

function hm_ajax_fitting_load_invoice() {
    check_ajax_referer('hm_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['msg' => 'Access denied']); return; }

    $db       = HearMed_DB::instance();
    $order_id = intval($_POST['order_id'] ?? 0);

    $order = $db->get_row(
        "SELECT o.*, p.first_name AS p_first, p.last_name AS p_last, p.patient_number
         FROM hearmed_core.orders o
         JOIN hearmed_core.patients p ON p.id = o.patient_id
         WHERE o.id = \$1",
        [$order_id]
    );
    if (!$order) { wp_send_json_error(['msg' => 'Order not found']); return; }

    $invoice = null;
    $payments = [];

    if ($order->invoice_id) {
        $invoice = $db->get_row("SELECT * FROM hearmed_core.invoices WHERE id = \$1", [$order->invoice_id]);
        $payments_raw = $db->get_results(
            "SELECT amount, payment_method, payment_date FROM hearmed_core.payments WHERE invoice_id = \$1 ORDER BY payment_date",
            [$order->invoice_id]
        );
        foreach ($payments_raw ?: [] as $pm) {
            $payments[] = [
                'amount'         => (float)$pm->amount,
                'payment_method' => $pm->payment_method,
                'payment_date'   => date('d M Y', strtotime($pm->payment_date)),
            ];
        }
    }

    // If no invoice exists, create one automatically
    if (!$invoice) {
        $inv_number = 'INV-' . date('Ymd') . '-' . str_pad(rand(1,9999), 4, '0', STR_PAD_LEFT);
        $uid = get_current_user_id();
        $staff_id = $db->get_var("SELECT id FROM hearmed_reference.staff WHERE wp_user_id = \$1", [$uid]);

        $inv_id = $db->insert('hearmed_core.invoices', [
            'invoice_number'    => $inv_number,
            'patient_id'        => $order->patient_id,
            'order_id'          => $order_id,
            'staff_id'          => $order->staff_id,
            'clinic_id'         => $order->clinic_id,
            'invoice_date'      => date('Y-m-d'),
            'subtotal'          => $order->subtotal,
            'discount_total'    => $order->discount_total,
            'vat_total'         => $order->vat_total,
            'grand_total'       => $order->grand_total,
            'balance_remaining' => $order->grand_total,
            'payment_status'    => 'Unpaid',
            'prsi_applicable'   => $order->prsi_applicable,
            'prsi_amount'       => $order->prsi_amount ?? 0,
            'created_by'        => $staff_id ?: $uid,
        ]);

        // Copy order items to invoice items
        $items = $db->get_results(
            "SELECT * FROM hearmed_core.order_items WHERE order_id = \$1 ORDER BY line_number",
            [$order_id]
        );
        foreach ($items ?: [] as $it) {
            $db->insert('hearmed_core.invoice_items', [
                'invoice_id'       => $inv_id,
                'line_number'      => $it->line_number,
                'item_type'        => $it->item_type,
                'item_id'          => $it->item_id,
                'item_description' => $it->item_description,
                'ear_side'         => $it->ear_side,
                'quantity'         => $it->quantity,
                'unit_price'       => $it->unit_retail_price,
                'discount_percent' => $it->discount_percent,
                'discount_amount'  => $it->discount_amount,
                'vat_rate'         => 23,
                'line_total'       => $it->line_total,
            ]);
        }

        // Link invoice to order
        $db->query("UPDATE hearmed_core.orders SET invoice_id = \$1 WHERE id = \$2", [$inv_id, $order_id]);

        $invoice = $db->get_row("SELECT * FROM hearmed_core.invoices WHERE id = \$1", [$inv_id]);
    }

    wp_send_json_success([
        'invoice_id'        => (int)$invoice->id,
        'invoice_number'    => $invoice->invoice_number,
        'order_number'      => $order->order_number,
        'patient_name'      => trim($order->p_first . ' ' . $order->p_last),
        'patient_number'    => $order->patient_number,
        'subtotal'          => (float)($invoice->subtotal ?? $order->subtotal),
        'discount_total'    => (float)($invoice->discount_total ?? $order->discount_total),
        'vat_total'         => (float)($invoice->vat_total ?? $order->vat_total),
        'grand_total'       => (float)($invoice->grand_total ?? $order->grand_total),
        'balance_remaining' => (float)($invoice->balance_remaining ?? $invoice->grand_total),
        'prsi_applicable'   => (bool)($invoice->prsi_applicable ?? $order->prsi_applicable),
        'prsi_amount'       => (float)($invoice->prsi_amount ?? $order->prsi_amount ?? 0),
        'payments'          => $payments,
    ]);
}

// ═══════════════════════════════════════════════════════════════
// AJAX: Record payment + mark fitted if paid in full
// ═══════════════════════════════════════════════════════════════
add_action('wp_ajax_hm_fitting_record_payment', 'hm_ajax_fitting_record_payment');

function hm_ajax_fitting_record_payment() {
    check_ajax_referer('hm_nonce', 'nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['msg' => 'Access denied']); return; }

    $db         = HearMed_DB::instance();
    $order_id   = intval($_POST['order_id'] ?? 0);
    $invoice_id = intval($_POST['invoice_id'] ?? 0);
    $method     = sanitize_text_field($_POST['payment_method'] ?? 'Card');
    $amount     = floatval($_POST['amount'] ?? 0);
    $uid        = get_current_user_id();
    $now        = current_time('Y-m-d H:i:s');
    $today      = date('Y-m-d');

    if ($amount <= 0) { wp_send_json_error(['msg' => 'Amount must be greater than zero']); return; }

    $order = $db->get_row("SELECT * FROM hearmed_core.orders WHERE id = \$1", [$order_id]);
    if (!$order) { wp_send_json_error(['msg' => 'Order not found']); return; }

    $staff_id = $db->get_var("SELECT id FROM hearmed_reference.staff WHERE wp_user_id = \$1", [$uid]);

    // Use invoice from order if not provided
    if (!$invoice_id && $order->invoice_id) {
        $invoice_id = (int)$order->invoice_id;
    }

    $invoice = $db->get_row("SELECT * FROM hearmed_core.invoices WHERE id = \$1", [$invoice_id]);
    if (!$invoice) { wp_send_json_error(['msg' => 'Invoice not found']); return; }

    // Record payment
    $db->insert('hearmed_core.payments', [
        'invoice_id'     => $invoice_id,
        'patient_id'     => $order->patient_id,
        'amount'         => $amount,
        'payment_date'   => $today,
        'payment_method' => $method,
        'received_by'    => $staff_id ?: $uid,
        'clinic_id'      => $order->clinic_id,
        'created_by'     => $staff_id ?: $uid,
    ]);

    // Update invoice balance
    $new_balance = max(0, (float)$invoice->balance_remaining - $amount);
    $payment_status = $new_balance <= 0 ? 'Paid' : 'Partial';

    $db->query(
        "UPDATE hearmed_core.invoices SET balance_remaining = \$1, payment_status = \$2, updated_at = \$3 WHERE id = \$4",
        [$new_balance, $payment_status, $now, $invoice_id]
    );

    $paid_in_full = ($new_balance <= 0);

    if ($paid_in_full) {
        // Mark order as Complete
        $db->query(
            "UPDATE hearmed_core.orders SET current_status = 'Complete', fitted_date = \$1, updated_at = \$2 WHERE id = \$3",
            [$now, $now, $order_id]
        );

        // Update fitting_queue
        $db->query(
            "UPDATE hearmed_core.fitting_queue SET queue_status = 'Fitted', fitted_date = \$1, fitted_by = \$2, updated_at = \$1 WHERE order_id = \$3",
            [$now, $staff_id ?: $uid, $order_id]
        );

        // Update patient_devices with fitting_date
        $db->query(
            "UPDATE hearmed_core.patient_devices SET fitting_date = \$1 WHERE patient_id = \$2 AND fitting_date IS NULL",
            [$today, $order->patient_id]
        );

        // Status history
        $db->query(
            "INSERT INTO hearmed_core.order_status_history (order_id, from_status, to_status, changed_by, changed_at, notes) VALUES (\$1, \$2, \$3, \$4, \$5, \$6)",
            [$order_id, 'Awaiting Fitting', 'Complete', $staff_id ?: $uid, $now, 'Fitted and paid in full — ' . $method . ' €' . number_format($amount, 2)]
        );

        // Log in patient timeline if table exists
        $db->query(
            "INSERT INTO hearmed_core.patient_timeline (patient_id, event_type, event_date, staff_id, description, order_id)
             SELECT \$1, 'fitting_complete', \$2, \$3, \$4, \$5
             WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = 'hearmed_core' AND table_name = 'patient_timeline')",
            [$order->patient_id, $today, $staff_id ?: $uid,
             'Hearing aids fitted. Order ' . $order->order_number . '. Payment: €' . number_format($amount, 2) . ' via ' . $method,
             $order_id]
        );

        if (function_exists('hm_audit_log')) {
            hm_audit_log($uid, 'fitted_and_paid', 'order', $order_id, ['status' => 'Awaiting Fitting'], ['status' => 'Complete']);
        }
    }

    wp_send_json_success([
        'paid_in_full'      => $paid_in_full,
        'balance_remaining' => $new_balance,
        'order_number'      => $order->order_number,
        'grand_total'       => (float)$invoice->grand_total,
    ]);
}

// ═══════════════════════════════════════════════════════════════
// AJAX: Print Receipt (HTML for new window)
// ═══════════════════════════════════════════════════════════════
add_action('wp_ajax_hm_fitting_receipt', 'hm_ajax_fitting_receipt');

function hm_ajax_fitting_receipt() {
    if (!wp_verify_nonce($_GET['nonce'] ?? '', 'hm_nonce')) { wp_die('Invalid nonce'); }
    if (!is_user_logged_in()) { wp_die('Access denied'); }

    $db       = HearMed_DB::instance();
    $order_id = intval($_GET['order_id'] ?? 0);

    $order = $db->get_row(
        "SELECT o.*,
                p.first_name AS p_first, p.last_name AS p_last, p.patient_number,
                p.address_line1, p.address_line2, p.city, p.county, p.eircode,
                c.clinic_name, c.address AS clinic_address, c.phone AS clinic_phone,
                CONCAT(s.first_name, ' ', s.last_name) AS dispenser_name
         FROM hearmed_core.orders o
         JOIN hearmed_core.patients p ON p.id = o.patient_id
         JOIN hearmed_reference.clinics c ON c.id = o.clinic_id
         LEFT JOIN hearmed_reference.staff s ON s.id = o.staff_id
         WHERE o.id = \$1",
        [$order_id]
    );
    if (!$order) wp_die('Order not found');

    $invoice = null;
    if ($order->invoice_id) {
        $invoice = $db->get_row("SELECT * FROM hearmed_core.invoices WHERE id = \$1", [$order->invoice_id]);
    }
    $inv_number = $invoice ? $invoice->invoice_number : $order->order_number;

    $items = $db->get_results(
        "SELECT ii.*, COALESCE(pr.product_name, ii.item_description) AS product_name
         FROM hearmed_core.invoice_items ii
         LEFT JOIN hearmed_reference.products pr ON pr.id = ii.item_id AND ii.item_type = 'product'
         WHERE ii.invoice_id = \$1
         ORDER BY ii.line_number",
        [$invoice ? $invoice->id : 0]
    );
    // Fallback to order items if no invoice items
    if (empty($items) && $order->invoice_id) {
        $items = $db->get_results(
            "SELECT oi.line_number, oi.item_description, oi.quantity, oi.unit_retail_price AS unit_price,
                    oi.line_total, oi.ear_side, oi.item_type, oi.item_id,
                    COALESCE(pr.product_name, oi.item_description) AS product_name
             FROM hearmed_core.order_items oi
             LEFT JOIN hearmed_reference.products pr ON pr.id = oi.item_id AND oi.item_type = 'product'
             WHERE oi.order_id = \$1
             ORDER BY oi.line_number",
            [$order_id]
        );
    }

    $payments = $db->get_results(
        "SELECT * FROM hearmed_core.payments WHERE invoice_id = \$1 ORDER BY payment_date",
        [$invoice ? $invoice->id : 0]
    );

    // Patient serials
    $devices = $db->get_results(
        "SELECT pd.serial_number_left, pd.serial_number_right, pr.product_name
         FROM hearmed_core.patient_devices pd
         LEFT JOIN hearmed_reference.products pr ON pr.id = pd.product_id
         WHERE pd.patient_id = \$1 AND pd.fitting_date IS NOT NULL
         ORDER BY pd.created_at DESC LIMIT 4",
        [$order->patient_id]
    );

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html>
<head>
<title>Receipt — <?php echo esc_html($inv_number); ?></title>
<style>
@page { size: A4; margin: 15mm; }
@media print { body { -webkit-print-color-adjust: exact; } .no-print { display: none !important; } }
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 12px; color: #1e293b; line-height: 1.5; padding: 30px; max-width: 700px; margin: 0 auto; }
.header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 3px solid var(--hm-teal); }
.header h1 { font-size: 24px; color: var(--hm-teal); }
.header-sub { font-size: 11px; color: #64748b; }
.header-meta { text-align: right; font-size: 11px; color: #64748b; }
.header-meta strong { color: #1e293b; display: block; }
.section { margin-bottom: 20px; }
.section-title { font-size: 12px; font-weight: 700; color: #0f172a; margin-bottom: 8px; text-transform: uppercase; letter-spacing: .5px; }
.patient-info { background: #f8fafc; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; }
.patient-info strong { font-size: 14px; }
.patient-info div { font-size: 11px; color: #64748b; margin-top: 2px; }
table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
th { background: #f8fafc; text-align: left; padding: 8px 10px; font-size: 10px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .3px; border-bottom: 2px solid #e2e8f0; }
td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; font-size: 12px; }
.money { text-align: right; }
tfoot td { font-weight: 600; border-bottom: none; }
tfoot tr:last-child td { font-size: 14px; color: var(--hm-teal); border-top: 2px solid #e2e8f0; }
.payments-section { background: #f0fdfa; border-radius: 6px; padding: 14px 16px; margin-bottom: 16px; }
.payments-section h3 { font-size: 11px; font-weight: 600; color: #065f46; text-transform: uppercase; margin-bottom: 8px; }
.pay-row { display: flex; justify-content: space-between; font-size: 12px; padding: 4px 0; border-bottom: 1px solid #d1fae5; }
.pay-row:last-child { border-bottom: none; font-weight: 700; }
.serials { font-size: 11px; color: #475569; margin-bottom: 16px; }
.serials span { font-family: monospace; background: #f1f5f9; padding: 1px 6px; border-radius: 3px; }
.footer { margin-top: 30px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 10px; color: #94a3b8; text-align: center; }
.status-paid { display: inline-block; background: #d1fae5; color: #065f46; padding: 4px 14px; border-radius: 6px; font-size: 12px; font-weight: 700; margin-top: 4px; }
.print-btn { position: fixed; top: 10px; right: 10px; padding: 10px 20px; background: var(--hm-teal); color: #fff; border: none; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 600; z-index: 100; }
.print-btn:hover { background: #0a9aa8; }
</style>
</head>
<body>
<button class="print-btn no-print" onclick="window.print()">Print Receipt</button>

<div class="header">
    <div>
        <h1>HearMed</h1>
        <div class="header-sub">Payment Receipt</div>
    </div>
    <div class="header-meta">
        <strong>Invoice: <?php echo esc_html($inv_number); ?></strong>
        <div>Date: <?php echo date('d M Y'); ?></div>
        <div>Clinic: <?php echo esc_html($order->clinic_name); ?></div>
        <?php if (!empty($order->clinic_phone)): ?><div>Phone: <?php echo esc_html($order->clinic_phone); ?></div><?php endif; ?>
        <div class="status-paid">PAID</div>
    </div>
</div>

<div class="patient-info">
    <strong><?php echo esc_html($order->p_first . ' ' . $order->p_last); ?></strong>
    <div>Patient #: <?php echo esc_html($order->patient_number); ?></div>
    <?php
    $addr = array_filter([
        $order->address_line1 ?? '',
        $order->address_line2 ?? '',
        $order->city ?? '',
        $order->county ?? '',
        $order->eircode ?? '',
    ]);
    if ($addr): ?>
    <div><?php echo esc_html(implode(', ', $addr)); ?></div>
    <?php endif; ?>
</div>

<div class="section">
    <div class="section-title">Items</div>
    <table>
        <thead>
            <tr><th>Description</th><th>Ear</th><th>Qty</th><th class="hm-money">Unit Price</th><th class="hm-money">Total</th></tr>
        </thead>
        <tbody>
        <?php foreach ($items ?: [] as $it): ?>
        <tr>
            <td><?php echo esc_html($it->product_name ?: $it->item_description); ?></td>
            <td><?php echo esc_html($it->ear_side ?: '—'); ?></td>
            <td><?php echo esc_html($it->quantity); ?></td>
            <td class="hm-money">€<?php echo number_format((float)($it->unit_price ?? $it->unit_retail_price ?? 0), 2); ?></td>
            <td class="hm-money">€<?php echo number_format((float)$it->line_total, 2); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr><td colspan="4" class="hm-money">Subtotal</td><td class="hm-money">€<?php echo number_format((float)($invoice ? $invoice->subtotal : $order->subtotal), 2); ?></td></tr>
            <?php if ((float)($invoice ? $invoice->discount_total : $order->discount_total) > 0): ?>
            <tr><td colspan="4" class="hm-money">Discount</td><td class="hm-money">-€<?php echo number_format((float)($invoice ? $invoice->discount_total : $order->discount_total), 2); ?></td></tr>
            <?php endif; ?>
            <tr><td colspan="4" class="hm-money">VAT</td><td class="hm-money">€<?php echo number_format((float)($invoice ? $invoice->vat_total : $order->vat_total), 2); ?></td></tr>
            <?php if ($order->prsi_applicable): ?>
            <tr class="hm-text--teal"><td colspan="4" class="hm-money">PRSI Grant</td><td class="hm-money">-€<?php echo number_format((float)($order->prsi_amount ?? 0), 2); ?></td></tr>
            <?php endif; ?>
            <tr><td colspan="4" class="hm-money">Total Paid</td><td class="hm-money">€<?php echo number_format((float)($invoice ? $invoice->grand_total : $order->grand_total), 2); ?></td></tr>
        </tfoot>
    </table>
</div>

<?php if (!empty($payments)): ?>
<div class="payments-section">
    <h3>Payment Details</h3>
    <?php $pay_total = 0; ?>
    <?php foreach ($payments as $pm):
        $pay_total += (float)$pm->amount;
    ?>
    <div class="pay-row">
        <span><?php echo date('d M Y', strtotime($pm->payment_date)); ?> — <?php echo esc_html($pm->payment_method); ?></span>
        <span>€<?php echo number_format((float)$pm->amount, 2); ?></span>
    </div>
    <?php endforeach; ?>
    <div class="pay-row">
        <span>Total Payments</span>
        <span>€<?php echo number_format($pay_total, 2); ?></span>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($devices)): ?>
<div class="serials">
    <strong>Device Serial Numbers:</strong><br>
    <?php foreach ($devices as $dev): ?>
        <?php echo esc_html($dev->product_name ?? 'Device'); ?>:
        <?php if ($dev->serial_number_left): ?> L: <span><?php echo esc_html($dev->serial_number_left); ?></span><?php endif; ?>
        <?php if ($dev->serial_number_right): ?> R: <span><?php echo esc_html($dev->serial_number_right); ?></span><?php endif; ?>
        <br>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="footer">
    <p>HearMed Acoustic Health Care Ltd — <?php echo esc_html($order->clinic_name); ?></p>
    <p>Receipt generated <?php echo date('d M Y \a\t H:i'); ?> | Order: <?php echo esc_html($order->order_number); ?> | Invoice: <?php echo esc_html($inv_number); ?></p>
    <p style="margin-top:4px;">Thank you for choosing HearMed.</p>
</div>

</body>
</html>
    <?php
    exit;
}
