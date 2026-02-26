<?php
/**
 * HearMed Accounting Module
 * Shortcode: [hearmed_accounting]
 * Pages: dashboard | invoices | supplier | supplier-new | bank-feed | qbo
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if ( isset($_GET['qbo_callback']) && $_GET['qbo_callback'] == 1 ) {
    add_action('init', function() {
        if ( class_exists('HearMed_QBO') ) HearMed_QBO::handle_callback();
    });
}

function hm_accounting_render() {
    if ( ! HearMed_Auth::is_logged_in() )        return '<div class="hm-notice hm-notice--error">Please log in.</div>';
    if ( ! HearMed_Auth::can('view_accounting') ) return '<div class="hm-notice hm-notice--error">Access denied.</div>';
    $action = sanitize_key($_GET['hm_action'] ?? 'dashboard');
    switch ($action) {
        case 'invoices':     return HearMed_Accounting::render_invoices();
        case 'supplier':     return HearMed_Accounting::render_supplier_list();
        case 'supplier-new': return HearMed_Accounting::render_supplier_form();
        case 'bank-feed':    return HearMed_Accounting::render_bank_feed();
        case 'qbo':          return HearMed_Accounting::render_qbo_manager();
        default:             return HearMed_Accounting::render_dashboard();
    }
}
add_shortcode('hearmed_accounting', 'hm_accounting_render');

class HearMed_Accounting {

    // =========================================================================
    // DASHBOARD
    // =========================================================================

    public static function render_dashboard() {
        $db   = HearMed_DB::instance();
        $base = HearMed_Utils::page_url('accounting');
        $today = date('Y-m-d'); $ms = date('Y-m-01'); $ys = date('Y-01-01');
        $tr  = (float)$db->get_var("SELECT COALESCE(SUM(amount),0) FROM hearmed_core.payments WHERE payment_date=\$1", [$today]);
        $mr  = (float)$db->get_var("SELECT COALESCE(SUM(amount),0) FROM hearmed_core.payments WHERE payment_date>=\$1", [$ms]);
        $yr  = (float)$db->get_var("SELECT COALESCE(SUM(amount),0) FROM hearmed_core.payments WHERE payment_date>=\$1", [$ys]);
        $out = (float)$db->get_var("SELECT COALESCE(SUM(balance_remaining),0) FROM hearmed_core.invoices WHERE payment_status!='Paid'");
        $ow  = (int)$db->get_var("SELECT COUNT(*) FROM hearmed_core.orders WHERE current_status='Awaiting Approval'");
        $fq  = (int)$db->get_var("SELECT COUNT(*) FROM hearmed_core.fitting_queue WHERE queue_status='Awaiting'");
        $su  = (int)$db->get_var("SELECT COUNT(*) FROM hearmed_core.supplier_invoices WHERE payment_status!='paid'");
        $qf  = (int)$db->get_var("SELECT COUNT(*) FROM hearmed_admin.qbo_sync_log WHERE status='failed' AND created_at>=NOW()-INTERVAL '7 days'");
        $qp  = (int)$db->get_var("SELECT COUNT(*) FROM hearmed_admin.qbo_batch_queue WHERE status='pending'");

        $monthly   = $db->get_results("SELECT TO_CHAR(payment_date,'Mon YY') AS month, DATE_TRUNC('month',payment_date) AS month_start, COALESCE(SUM(amount),0) AS total FROM hearmed_core.payments WHERE payment_date>=NOW()-INTERVAL '6 months' GROUP BY month,month_start ORDER BY month_start ASC", []);
        $by_clinic = $db->get_results("SELECT c.clinic_name, COALESCE(SUM(pay.amount),0) AS total FROM hearmed_core.payments pay JOIN hearmed_core.invoices inv ON inv.id=pay.invoice_id JOIN hearmed_reference.clinics c ON c.id=inv.clinic_id WHERE pay.payment_date>=\$1 GROUP BY c.clinic_name ORDER BY total DESC", [$ms]);
        $recent    = $db->get_results("SELECT pay.amount,pay.payment_date,pay.payment_method,p.first_name,p.last_name,inv.invoice_number,c.clinic_name FROM hearmed_core.payments pay JOIN hearmed_core.patients p ON p.id=pay.patient_id JOIN hearmed_core.invoices inv ON inv.id=pay.invoice_id JOIN hearmed_reference.clinics c ON c.id=inv.clinic_id ORDER BY pay.payment_date DESC,pay.id DESC LIMIT 10", []);
        $qbo = HearMed_QBO::connection_status();

        ob_start(); ?>
        <div class="hm-content hm-accounting-dashboard">
        <div class="hm-page-header">
            <h1 class="hm-page-title">Accounting</h1>
            <div class="hm-page-header__actions">
                <a href="<?php echo esc_url($base.'?hm_action=supplier-new'); ?>" class="hm-btn hm-btn--secondary">+ Supplier Invoice</a>
                <a href="<?php echo esc_url($base.'?hm_action=qbo'); ?>" class="hm-btn <?php echo $qbo['connected'] ? 'hm-btn--ghost' : 'hm-btn--primary'; ?>">
                    <?php echo $qbo['connected'] ? '✓ QuickBooks Connected' : '⚡ Connect QuickBooks'; ?>
                </a>
            </div>
        </div>

        <?php if (isset($_GET['qbo_connected'])) : ?>
        <div class="hm-notice hm-notice--success" style="margin-bottom:1.5rem;">✓ QuickBooks connected! Invoices and bills will now sync automatically.</div>
        <?php endif; ?>
        <?php if ($qf > 0) : ?>
        <div class="hm-notice hm-notice--warning" style="margin-bottom:1.5rem;">⚠ <?php echo $qf; ?> QuickBooks sync failure(s) this week. <a href="<?php echo esc_url($base.'?hm_action=qbo'); ?>">View and retry →</a></div>
        <?php endif; ?>
        <?php if ($qp > 0) : ?>
        <div class="hm-notice hm-notice--info" style="margin-bottom:1.5rem;">📋 <?php echo $qp; ?> invoice(s) queued for QuickBooks — batch runs Friday night. <a href="<?php echo esc_url($base.'?hm_action=qbo'); ?>">Push now →</a></div>
        <?php endif; ?>

        <div class="hm-kpi-grid">
            <?php $cards = [
                ["Today's Revenue",   '€'.number_format($tr,2), '', ''],
                ['This Month',        '€'.number_format($mr,2), '', ''],
                ['This Year',         '€'.number_format($yr,2), '', ''],
                ['Outstanding',       '€'.number_format($out,2), $out>0 ? 'hm-kpi-card--warning' : '', $base.'?hm_action=invoices&status=unpaid'],
                ['Awaiting Approval', $ow, $ow>0 ? 'hm-kpi-card--alert' : '', HearMed_Utils::page_url('orders').'?status=Awaiting+Approval'],
                ['Awaiting Fitting',  $fq, $fq>0 ? 'hm-kpi-card--info'  : '', HearMed_Utils::page_url('orders').'?status=Awaiting+Fitting'],
                ['Unpaid Supplier Bills', $su, $su>0 ? 'hm-kpi-card--warning' : '', $base.'?hm_action=supplier'],
                ['QBO Queue',         $qp, $qp>0 ? 'hm-kpi-card--info'  : '', $base.'?hm_action=qbo'],
            ];
            foreach ($cards as [$label, $value, $cls, $link]) : ?>
            <div class="hm-kpi-card <?php echo esc_attr($cls); ?>">
                <span class="hm-kpi-card__label"><?php echo esc_html($label); ?></span>
                <span class="hm-kpi-card__value"><?php echo esc_html($value); ?></span>
                <?php if ($link) : ?><a href="<?php echo esc_url($link); ?>" class="hm-kpi-card__link">View →</a><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="hm-accounting-grid">
            <div class="hm-card">
                <h2 class="hm-card-title">Monthly Revenue — Last 6 Months</h2>
                <?php if (!empty($monthly)) : $max = max(array_map(fn($r) => floatval($r->total), $monthly)) ?: 1; ?>
                <div class="hm-bar-chart">
                    <?php foreach ($monthly as $m) : $pct = round((floatval($m->total) / $max) * 100); ?>
                    <div class="hm-bar-chart__item">
                        <div class="hm-bar-chart__bar-wrap">
                            <div class="hm-bar-chart__bar" style="height:<?php echo $pct; ?>%" title="€<?php echo number_format($m->total,2); ?>">
                                <span class="hm-bar-chart__value">€<?php echo number_format($m->total/1000,1); ?>k</span>
                            </div>
                        </div>
                        <span class="hm-bar-chart__label"><?php echo esc_html($m->month); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else : ?><p class="hm-muted">No payment data yet.</p><?php endif; ?>
            </div>
            <div class="hm-card">
                <h2 class="hm-card-title">This Month by Clinic</h2>
                <?php if (!empty($by_clinic)) : ?>
                <table class="hm-table"><thead><tr><th>Clinic</th><th>Revenue</th></tr></thead><tbody>
                <?php foreach ($by_clinic as $r) : ?>
                <tr><td><?php echo esc_html($r->clinic_name); ?></td><td class="hm-money">€<?php echo number_format($r->total,2); ?></td></tr>
                <?php endforeach; ?>
                </tbody></table>
                <?php else : ?><p class="hm-muted">No data this month.</p><?php endif; ?>
            </div>
        </div>

        <div class="hm-card" style="margin-top:1.5rem;">
            <div class="hm-card__header">
                <h2 class="hm-card-title">Recent Payments</h2>
                <a href="<?php echo esc_url($base.'?hm_action=invoices'); ?>" class="hm-btn hm-btn--ghost hm-btn--sm">All Invoices →</a>
            </div>
            <table class="hm-table">
                <thead><tr><th>Date</th><th>Patient</th><th>Invoice</th><th>Clinic</th><th>Method</th><th>Amount</th></tr></thead>
                <tbody>
                <?php if (empty($recent)) : ?>
                <tr><td colspan="6" class="hm-table__empty">No payments yet.</td></tr>
                <?php else : foreach ($recent as $p) : ?>
                <tr>
                    <td class="hm-muted"><?php echo date('d M Y', strtotime($p->payment_date)); ?></td>
                    <td><?php echo esc_html($p->first_name.' '.$p->last_name); ?></td>
                    <td class="hm-mono"><?php echo esc_html($p->invoice_number); ?></td>
                    <td class="hm-muted"><?php echo esc_html($p->clinic_name); ?></td>
                    <td><?php echo esc_html($p->payment_method); ?></td>
                    <td class="hm-money">€<?php echo number_format($p->amount, 2); ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        </div>
        <?php return ob_get_clean();
    }

    // =========================================================================
    // INVOICES
    // =========================================================================

    public static function render_invoices() {
        $db     = HearMed_DB::instance();
        $base   = HearMed_Utils::page_url('accounting');
        $status = sanitize_text_field($_GET['status'] ?? 'all');
        $where  = $status !== 'all' ? "WHERE inv.payment_status ILIKE \$1" : '';
        $params = $status !== 'all' ? ['%'.$status.'%'] : [];
        $invoices = $db->get_results(
            "SELECT inv.id, inv.invoice_number, inv.invoice_date, inv.grand_total, inv.balance_remaining,
                    inv.payment_status, inv.quickbooks_id, inv.qbo_sync_status,
                    p.first_name, p.last_name, c.clinic_name, o.order_number
             FROM hearmed_core.invoices inv
             JOIN hearmed_core.patients p       ON p.id = inv.patient_id
             JOIN hearmed_reference.clinics c   ON c.id = inv.clinic_id
             LEFT JOIN hearmed_core.orders o    ON o.invoice_id = inv.id
             {$where}
             ORDER BY inv.invoice_date DESC LIMIT 200",
            $params
        );
        ob_start(); ?>
        <div class="hm-content">
        <div class="hm-page-header">
            <a href="<?php echo esc_url($base); ?>" class="hm-back-btn">← Accounting</a>
            <h1 class="hm-page-title">Invoices</h1>
        </div>
        <div class="hm-tabs">
            <?php foreach (['all' => 'All', 'unpaid' => 'Unpaid', 'Paid' => 'Paid', 'Partially Paid' => 'Part Paid'] as $k => $l) : ?>
            <a href="<?php echo esc_url($base.'?hm_action=invoices&status='.$k); ?>"
               class="hm-tab <?php echo $status === $k ? 'hm-tab--active' : ''; ?>">
                <?php echo esc_html($l); ?>
            </a>
            <?php endforeach; ?>
        </div>
        <table class="hm-table">
            <thead><tr><th>Invoice #</th><th>Order #</th><th>Patient</th><th>Clinic</th><th>Date</th><th>Total</th><th>Balance</th><th>Status</th><th>QBO</th></tr></thead>
            <tbody>
            <?php if (empty($invoices)) : ?>
            <tr><td colspan="9" class="hm-table__empty">No invoices found.</td></tr>
            <?php else : foreach ($invoices as $inv) : ?>
            <tr>
                <td class="hm-mono"><?php echo esc_html($inv->invoice_number); ?></td>
                <td class="hm-mono hm-muted"><?php echo esc_html($inv->order_number ?? '—'); ?></td>
                <td><?php echo esc_html($inv->first_name.' '.$inv->last_name); ?></td>
                <td class="hm-muted"><?php echo esc_html($inv->clinic_name); ?></td>
                <td class="hm-muted"><?php echo date('d M Y', strtotime($inv->invoice_date)); ?></td>
                <td class="hm-money">€<?php echo number_format($inv->grand_total, 2); ?></td>
                <td class="hm-money <?php echo floatval($inv->balance_remaining) > 0 ? 'hm-text--orange' : 'hm-text--green'; ?>">
                    €<?php echo number_format($inv->balance_remaining, 2); ?>
                </td>
                <td><?php echo self::payment_badge($inv->payment_status); ?></td>
                <td>
                    <?php if ($inv->quickbooks_id) : ?>
                    <span class="hm-badge hm-badge--green">Synced</span>
                    <?php elseif ($inv->qbo_sync_status === 'failed') : ?>
                    <span class="hm-badge hm-badge--red">Failed</span>
                    <button class="hm-btn hm-btn--sm hm-btn--ghost hm-retry-qbo"
                            data-invoice-id="<?php echo $inv->id; ?>"
                            data-nonce="<?php echo wp_create_nonce('hearmed_nonce'); ?>">Retry</button>
                    <?php elseif ($inv->qbo_sync_status === 'queued') : ?>
                    <span class="hm-badge hm-badge--orange">Queued</span>
                    <?php else : ?>
                    <span class="hm-badge hm-badge--grey">Pending</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
        <script>
        document.querySelectorAll('.hm-retry-qbo').forEach(btn => {
            btn.addEventListener('click', function() {
                this.textContent = '...';
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({action:'hm_retry_qbo_sync', invoice_id:this.dataset.invoiceId, nonce:this.dataset.nonce})
                }).then(r=>r.json()).then(d=>{
                    if (d.success) this.closest('td').innerHTML = '<span class="hm-badge hm-badge--green">Synced</span>';
                    else this.textContent = 'Failed';
                });
            });
        });
        </script>
        <?php return ob_get_clean();
    }

    // =========================================================================
    // SUPPLIER LIST
    // =========================================================================

    public static function render_supplier_list() {
        $db   = HearMed_DB::instance();
        $base = HearMed_Utils::page_url('accounting');
        $bills = $db->get_results("SELECT * FROM hearmed_core.supplier_invoices ORDER BY invoice_date DESC LIMIT 100", []);
        ob_start(); ?>
        <div class="hm-content">
        <div class="hm-page-header">
            <a href="<?php echo esc_url($base); ?>" class="hm-back-btn">← Accounting</a>
            <h1 class="hm-page-title">Supplier Invoices</h1>
            <a href="<?php echo esc_url($base.'?hm_action=supplier-new'); ?>" class="hm-btn hm-btn--primary">+ Add Invoice</a>
        </div>
        <table class="hm-table">
            <thead><tr><th>Ref</th><th>Supplier</th><th>Date</th><th>Due</th><th>Total</th><th>Paid?</th><th>QBO</th></tr></thead>
            <tbody>
            <?php if (empty($bills)) : ?>
            <tr><td colspan="7" class="hm-table__empty">No supplier invoices yet.</td></tr>
            <?php else : foreach ($bills as $b) : $od = strtotime($b->due_date) < time() && ($b->payment_status ?? '') !== 'paid'; ?>
            <tr>
                <td class="hm-mono"><?php echo esc_html($b->supplier_invoice_ref); ?></td>
                <td><?php echo esc_html($b->supplier_name); ?></td>
                <td class="hm-muted"><?php echo date('d M Y', strtotime($b->invoice_date)); ?></td>
                <td class="<?php echo $od ? 'hm-text--red' : 'hm-muted'; ?>">
                    <?php echo date('d M Y', strtotime($b->due_date)); ?><?php echo $od ? ' ⚠' : ''; ?>
                </td>
                <td class="hm-money">€<?php echo number_format($b->total_amount, 2); ?></td>
                <td><?php echo self::payment_badge($b->payment_status ?? 'unpaid'); ?></td>
                <td>
                    <?php if (!empty($b->qbo_bill_id)) : ?>
                    <span class="hm-badge hm-badge--green">Synced</span>
                    <?php elseif (($b->qbo_sync_status ?? '') === 'failed') : ?>
                    <span class="hm-badge hm-badge--red">Failed</span>
                    <?php else : ?>
                    <span class="hm-badge hm-badge--grey">Pending</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
        </div>
        <?php return ob_get_clean();
    }

    // =========================================================================
    // SUPPLIER FORM
    // =========================================================================

    public static function render_supplier_form() {
        $base  = HearMed_Utils::page_url('accounting');
        $nonce = wp_create_nonce('hearmed_nonce');
        $accounts = HearMed_DB::get_results(
            "SELECT qbo_account_id, account_name FROM hearmed_admin.qbo_accounts
             WHERE account_type IN ('Expense','Cost of Goods Sold','Other Expense')
             ORDER BY account_name", []
        );
        $opts = '<option value="">— Account —</option>';
        foreach ($accounts as $a) {
            $opts .= '<option value="'.esc_attr($a->qbo_account_id).'">'.esc_html($a->account_name).'</option>';
        }
        ob_start(); ?>
        <div class="hm-content">
        <div class="hm-page-header">
            <a href="<?php echo esc_url($base.'?hm_action=supplier'); ?>" class="hm-back-btn">← Supplier Invoices</a>
            <h1 class="hm-page-title">New Supplier Invoice</h1>
            <span class="hm-workflow-hint">Saves to portal + pushes to QuickBooks as a Bill.</span>
        </div>
        <form id="hm-sup-form" class="hm-form hm-card" style="max-width:820px;">
            <input type="hidden" name="action" value="hm_save_supplier_invoice">
            <input type="hidden" name="nonce"  value="<?php echo esc_attr($nonce); ?>">
            <div class="hm-form__row">
                <div class="hm-form-group">
                    <label class="hm-label">Supplier Name <span class="hm-required">*</span></label>
                    <input type="text" name="supplier_name" class="hm-input" required placeholder="e.g. Phonak Ireland">
                </div>
                <div class="hm-form-group">
                    <label class="hm-label">Invoice Reference <span class="hm-required">*</span></label>
                    <input type="text" name="supplier_invoice_ref" class="hm-input" required placeholder="e.g. INV-2026-0042">
                </div>
            </div>
            <div class="hm-form__row">
                <div class="hm-form-group">
                    <label class="hm-label">Invoice Date</label>
                    <input type="date" name="invoice_date" class="hm-input" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="hm-form-group">
                    <label class="hm-label">Due Date</label>
                    <input type="date" name="due_date" class="hm-input" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                </div>
                <div class="hm-form-group">
                    <label class="hm-label">Currency</label>
                    <select name="currency" class="hm-input">
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                        <option value="USD">USD</option>
                    </select>
                </div>
            </div>
            <h3 class="hm-form__section-title" style="margin-top:1.5rem;">Line Items</h3>
            <table class="hm-table" style="font-size:.875rem;">
                <thead><tr><th>Description</th><th>QBO Account</th><th style="width:60px">Qty</th><th style="width:100px">Unit €</th><th style="width:65px">VAT %</th><th style="width:90px">Total</th><th></th></tr></thead>
                <tbody id="hm-sup-body">
                    <tr>
                        <td><input type="text" name="items[0][description]" class="hm-input hm-input--sm" required placeholder="Description"></td>
                        <td><select name="items[0][qbo_account_id]" class="hm-input hm-input--sm"><?php echo $opts; ?></select></td>
                        <td><input type="number" name="items[0][qty]" class="hm-input hm-input--sm sup-qty" min="1" value="1"></td>
                        <td><input type="number" name="items[0][unit_price]" class="hm-input hm-input--sm sup-price" step="0.01" value="0.00"></td>
                        <td><input type="number" name="items[0][vat_rate]" class="hm-input hm-input--sm sup-vat" step="0.1" value="23"></td>
                        <td class="hm-money sup-line-total">€0.00</td>
                        <td><button type="button" class="hm-btn hm-btn--sm hm-btn--danger hm-remove-sup">✕</button></td>
                    </tr>
                </tbody>
            </table>
            <button type="button" id="hm-add-sup-row" class="hm-btn hm-btn--secondary hm-btn--sm" style="margin-top:.5rem;">+ Add Line</button>
            <div style="display:flex;justify-content:flex-end;margin-top:1rem;">
                <div class="hm-order-totals hm-card hm-card--inset" style="min-width:250px;">
                    <div class="hm-order-totals__row"><span>Subtotal</span><span id="hm-sup-sub">€0.00</span></div>
                    <div class="hm-order-totals__row"><span>VAT</span><span id="hm-sup-vat">€0.00</span></div>
                    <div class="hm-order-totals__row hm-order-totals__row--total"><span>Total</span><span id="hm-sup-tot">€0.00</span></div>
                </div>
            </div>
            <input type="hidden" name="subtotal"     id="hm-sub-h" value="0">
            <input type="hidden" name="vat_total"    id="hm-vat-h" value="0">
            <input type="hidden" name="total_amount" id="hm-tot-h" value="0">
            <div class="hm-form-group" style="margin-top:1rem;">
                <label class="hm-label">Notes</label>
                <textarea name="notes" class="hm-input hm-input--textarea" rows="2"></textarea>
            </div>
            <div class="hm-form__actions">
                <a href="<?php echo esc_url($base.'?hm_action=supplier'); ?>" class="hm-btn hm-btn--ghost">Cancel</a>
                <button type="submit" class="hm-btn hm-btn--primary">Save &amp; Push to QuickBooks →</button>
            </div>
            <div id="hm-sup-msg" class="hm-notice" style="display:none;margin-top:1rem;"></div>
        </form>
        </div>
        <script>
        (function() {
            const body     = document.getElementById('hm-sup-body');
            const acctOpts = <?php echo json_encode($opts); ?>;
            let idx = 1;

            function newRow() {
                const tr = document.createElement('tr');
                tr.innerHTML = `<td><input type="text" name="items[${idx}][description]" class="hm-input hm-input--sm" required placeholder="Description"></td>
                    <td><select name="items[${idx}][qbo_account_id]" class="hm-input hm-input--sm">${acctOpts}</select></td>
                    <td><input type="number" name="items[${idx}][qty]" class="hm-input hm-input--sm sup-qty" min="1" value="1"></td>
                    <td><input type="number" name="items[${idx}][unit_price]" class="hm-input hm-input--sm sup-price" step="0.01" value="0.00"></td>
                    <td><input type="number" name="items[${idx}][vat_rate]" class="hm-input hm-input--sm sup-vat" step="0.1" value="23"></td>
                    <td class="hm-money sup-line-total">€0.00</td>
                    <td><button type="button" class="hm-btn hm-btn--sm hm-btn--danger hm-remove-sup">✕</button></td>`;
                idx++;
                body.appendChild(tr);
                bind(tr);
            }

            function bind(tr) {
                tr.querySelector('.hm-remove-sup').addEventListener('click', () => {
                    if (body.querySelectorAll('tr').length > 1) { tr.remove(); recalc(); }
                });
                tr.querySelectorAll('input[type=number]').forEach(i => i.addEventListener('input', recalc));
            }

            function recalc() {
                let s = 0, v = 0;
                body.querySelectorAll('tr').forEach(tr => {
                    const q  = parseFloat(tr.querySelector('.sup-qty')?.value   || 1);
                    const p  = parseFloat(tr.querySelector('.sup-price')?.value || 0);
                    const vr = parseFloat(tr.querySelector('.sup-vat')?.value   || 0);
                    const ex = q * p, vat = ex * (vr / 100), tot = ex + vat;
                    const c = tr.querySelector('.sup-line-total');
                    if (c) c.textContent = '€' + tot.toFixed(2);
                    s += ex; v += vat;
                });
                const t = s + v;
                document.getElementById('hm-sup-sub').textContent = '€' + s.toFixed(2);
                document.getElementById('hm-sup-vat').textContent = '€' + v.toFixed(2);
                document.getElementById('hm-sup-tot').textContent = '€' + t.toFixed(2);
                document.getElementById('hm-sub-h').value = s.toFixed(2);
                document.getElementById('hm-vat-h').value = v.toFixed(2);
                document.getElementById('hm-tot-h').value = t.toFixed(2);
            }

            document.getElementById('hm-add-sup-row').addEventListener('click', newRow);
            body.querySelectorAll('tr').forEach(bind);

            document.getElementById('hm-sup-form').addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = this.querySelector('[type=submit]');
                btn.disabled = true; btn.textContent = 'Saving...';
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST', body: new URLSearchParams(new FormData(this))
                }).then(r => r.json()).then(d => {
                    const m = document.getElementById('hm-sup-msg');
                    m.style.display = 'block';
                    if (d.success) {
                        m.className = 'hm-notice hm-notice--success';
                        m.textContent = d.data.message;
                        setTimeout(() => window.location = '<?php echo esc_url($base.'?hm_action=supplier'); ?>', 1500);
                    } else {
                        m.className = 'hm-notice hm-notice--error';
                        m.textContent = d.data;
                        btn.disabled = false; btn.textContent = 'Save & Push to QuickBooks →';
                    }
                });
            });
        })();
        </script>
        <?php return ob_get_clean();
    }

    // =========================================================================
    // BANK FEED
    // =========================================================================

    public static function render_bank_feed() {
        $db    = HearMed_DB::instance();
        $base  = HearMed_Utils::page_url('accounting');
        $nonce = wp_create_nonce('hearmed_nonce');

        if (isset($_GET['refresh'])) {
            HearMed_QBO::pull_bank_transactions(30);
            wp_redirect($base.'?hm_action=bank-feed');
            exit;
        }

        $txns     = $db->get_results("SELECT * FROM hearmed_admin.qbo_bank_transactions ORDER BY txn_date DESC LIMIT 100", []);
        $accounts = $db->get_results("SELECT qbo_account_id, account_name FROM hearmed_admin.qbo_accounts ORDER BY account_name", []);
        $last     = $db->get_var("SELECT MAX(created_at) FROM hearmed_admin.qbo_bank_transactions", []);

        ob_start(); ?>
        <div class="hm-content">
        <div class="hm-page-header">
            <a href="<?php echo esc_url($base); ?>" class="hm-back-btn">← Accounting</a>
            <h1 class="hm-page-title">Bank Feed</h1>
            <div class="hm-page-header__actions">
                <?php if ($last) : ?>
                <span class="hm-muted" style="font-size:.875rem;">Synced: <?php echo date('d M Y H:i', strtotime($last)); ?></span>
                <?php endif; ?>
                <a href="<?php echo esc_url($base.'?hm_action=bank-feed&refresh=1'); ?>" class="hm-btn hm-btn--secondary">↻ Pull from QuickBooks</a>
            </div>
        </div>

        <?php if (!HearMed_QBO::is_connected()) : ?>
        <div class="hm-notice hm-notice--warning">QuickBooks not connected. <a href="<?php echo esc_url($base.'?hm_action=qbo'); ?>">Connect now →</a></div>
        <?php elseif (empty($txns)) : ?>
        <div class="hm-notice hm-notice--info">No transactions yet. <a href="<?php echo esc_url($base.'?hm_action=bank-feed&refresh=1'); ?>">Pull from QuickBooks →</a></div>
        <?php else : ?>
        <table class="hm-table">
            <thead><tr><th>Date</th><th>Description</th><th>Amount</th><th>QBO Account</th><th>Assign To</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($txns as $t) : ?>
            <tr class="<?php echo $t->assigned ? 'hm-muted' : ''; ?>">
                <td><?php echo date('d M Y', strtotime($t->txn_date)); ?></td>
                <td><?php echo esc_html($t->description ?: '—'); ?></td>
                <td class="hm-money">€<?php echo number_format($t->amount, 2); ?></td>
                <td class="hm-muted"><?php echo esc_html($t->account_name); ?></td>
                <td>
                    <?php if (!$t->assigned) : ?>
                    <select class="hm-input hm-input--sm hm-bank-acct" data-id="<?php echo $t->id; ?>">
                        <option value="">— Account —</option>
                        <?php foreach ($accounts as $a) : ?>
                        <option value="<?php echo esc_attr($a->qbo_account_id); ?>" <?php echo $t->qbo_account_id === $a->qbo_account_id ? 'selected' : ''; ?>>
                            <?php echo esc_html($a->account_name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php else : ?>
                    <span class="hm-badge hm-badge--green">✓ Assigned</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!$t->assigned) : ?>
                    <button class="hm-btn hm-btn--sm hm-btn--primary hm-save-txn"
                            data-id="<?php echo $t->id; ?>"
                            data-nonce="<?php echo esc_attr($nonce); ?>">Save</button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <script>
        document.querySelectorAll('.hm-save-txn').forEach(btn => {
            btn.addEventListener('click', function() {
                const row  = this.closest('tr');
                const acct = row.querySelector('.hm-bank-acct').value;
                if (!acct) { alert('Select an account first.'); return; }
                this.disabled = true; this.textContent = '...';
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({action:'hm_assign_bank_txn', txn_id:this.dataset.id, account_id:acct, nonce:this.dataset.nonce})
                }).then(r => r.json()).then(d => {
                    if (d.success) {
                        row.classList.add('hm-muted');
                        row.cells[4].innerHTML = '<span class="hm-badge hm-badge--green">✓ Assigned</span>';
                        row.cells[5].innerHTML = '';
                    } else {
                        alert('Error: ' + d.data);
                        this.disabled = false; this.textContent = 'Save';
                    }
                });
            });
        });
        </script>
        <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }

    // =========================================================================
    // QBO MANAGER (with batch queue section)
    // =========================================================================

    public static function render_qbo_manager() {
        $base   = HearMed_Utils::page_url('accounting');
        $status = HearMed_QBO::connection_status();
        $nonce  = wp_create_nonce('hearmed_nonce');
        $log    = HearMed_DB::get_results("SELECT * FROM hearmed_admin.qbo_sync_log ORDER BY created_at DESC LIMIT 20", []);

        ob_start(); ?>
        <div class="hm-content">
        <div class="hm-page-header">
            <a href="<?php echo esc_url($base); ?>" class="hm-back-btn">← Accounting</a>
            <h1 class="hm-page-title">QuickBooks Connection</h1>
        </div>

        <div class="hm-card" style="max-width:580px;">
            <?php if ($status['connected']) : ?>
            <div class="hm-qbo-status hm-qbo-status--connected">
                <span class="hm-qbo-status__dot"></span>
                <div>
                    <strong>Connected</strong>
                    <p class="hm-muted" style="margin:.25rem 0 0;">
                        Company ID: <?php echo esc_html($status['realm_id']); ?><br>
                        Token valid until: <?php echo date('d M Y H:i', strtotime($status['expires_at'])); ?>
                    </p>
                </div>
            </div>
            <div class="hm-form__actions" style="margin-top:1.5rem;">
                <button id="hm-sync-accts" data-nonce="<?php echo esc_attr($nonce); ?>" class="hm-btn hm-btn--secondary">↻ Sync Chart of Accounts</button>
                <button id="hm-qbo-disc"   data-nonce="<?php echo esc_attr($nonce); ?>" class="hm-btn hm-btn--danger">Disconnect</button>
            </div>
            <div id="hm-qbo-msg" class="hm-notice" style="display:none;margin-top:1rem;"></div>
            <?php else : ?>
            <div class="hm-qbo-status hm-qbo-status--disconnected">
                <span class="hm-qbo-status__dot"></span>
                <div>
                    <strong>Not Connected</strong>
                    <p class="hm-muted">Connect to enable invoice sync, supplier bills, and bank feed.</p>
                </div>
            </div>
            <div style="margin-top:1.5rem;">
                <a href="<?php echo esc_url($status['auth_url']); ?>" class="hm-btn hm-btn--primary hm-btn--block">Connect to QuickBooks Online →</a>
            </div>
            <?php endif; ?>
        </div>

        <?php echo self::render_batch_section($nonce); ?>

        <div class="hm-card" style="margin-top:1.5rem;">
            <h2 class="hm-card-title">Sync Log</h2>
            <table class="hm-table">
                <thead><tr><th>Time</th><th>Type</th><th>Ref</th><th>Status</th><th>Message</th></tr></thead>
                <tbody>
                <?php if (empty($log)) : ?>
                <tr><td colspan="5" class="hm-table__empty">No sync activity yet.</td></tr>
                <?php else : foreach ($log as $l) :
                    $bc = match($l->status) { 'success' => 'hm-badge--green', 'failed', 'error' => 'hm-badge--red', default => 'hm-badge--grey' };
                ?>
                <tr>
                    <td class="hm-muted"><?php echo date('d M H:i', strtotime($l->created_at)); ?></td>
                    <td><?php echo esc_html($l->sync_type); ?></td>
                    <td class="hm-mono"><?php echo $l->reference_id ? '#'.$l->reference_id : '—'; ?></td>
                    <td><span class="hm-badge <?php echo $bc; ?>"><?php echo esc_html($l->status); ?></span></td>
                    <td class="hm-muted" style="font-size:.8rem;"><?php echo esc_html($l->message); ?></td>
                </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        </div>

        <script>
        (function() {
            const a    = '<?php echo admin_url('admin-ajax.php'); ?>';
            const sa   = document.getElementById('hm-sync-accts');
            const disc = document.getElementById('hm-qbo-disc');

            if (sa) {
                sa.addEventListener('click', function() {
                    this.disabled = true; this.textContent = 'Syncing...';
                    fetch(a, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({action:'hm_qbo_sync_accounts', nonce:this.dataset.nonce})
                    }).then(r=>r.json()).then(d=>{
                        const m = document.getElementById('hm-qbo-msg');
                        m.style.display = 'block';
                        m.className = 'hm-notice ' + (d.success ? 'hm-notice--success' : 'hm-notice--error');
                        m.textContent = d.success ? '✓ Accounts synced.' : 'Failed: ' + d.data;
                        this.disabled = false; this.textContent = '↻ Sync Chart of Accounts';
                    });
                });
            }

            if (disc) {
                disc.addEventListener('click', function() {
                    if (!confirm('Disconnect QuickBooks?')) return;
                    fetch(a, {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({action:'hm_qbo_disconnect', nonce:this.dataset.nonce})
                    }).then(r=>r.json()).then(d=>{ if (d.success) location.reload(); });
                });
            }
        })();
        </script>
        <?php return ob_get_clean();
    }

    // =========================================================================
    // BATCH QUEUE SECTION (rendered inside QBO Manager page)
    // =========================================================================

    private static function render_batch_section($nonce) {
        $db      = HearMed_DB::instance();
        $pending = (int) $db->get_var("SELECT COUNT(*) FROM hearmed_admin.qbo_batch_queue WHERE status = 'pending'");
        $failed  = (int) $db->get_var("SELECT COUNT(*) FROM hearmed_admin.qbo_batch_queue WHERE status = 'failed'");
        $synced  = (int) $db->get_var("SELECT COUNT(*) FROM hearmed_admin.qbo_batch_queue WHERE status = 'synced' AND synced_at >= NOW() - INTERVAL '7 days'");

        $queue = $db->get_results(
            "SELECT q.id, q.entity_type, q.entity_id, q.status, q.attempts,
                    q.queued_at, q.synced_at, q.last_error,
                    CASE WHEN q.entity_type='invoice'
                         THEN inv.invoice_number
                         ELSE cn.credit_note_number
                    END AS ref_number,
                    CONCAT(p.first_name,' ',p.last_name) AS patient_name
             FROM hearmed_admin.qbo_batch_queue q
             LEFT JOIN hearmed_core.invoices inv    ON inv.id = q.entity_id AND q.entity_type = 'invoice'
             LEFT JOIN hearmed_core.credit_notes cn ON cn.id = q.entity_id AND q.entity_type = 'credit_note'
             LEFT JOIN hearmed_core.patients p      ON p.id = COALESCE(inv.patient_id, cn.patient_id)
             ORDER BY q.queued_at DESC
             LIMIT 100",
            []
        );

        ob_start(); ?>
        <div class="hm-card" style="margin-top:1.5rem;">
            <div class="hm-card__header" style="display:flex;justify-content:space-between;align-items:center;">
                <h2 class="hm-card-title">QuickBooks Batch Sync Queue</h2>
                <div style="display:flex;gap:10px;align-items:center;">
                    <span class="hm-badge <?php echo $pending ? 'hm-badge--orange' : 'hm-badge--grey'; ?>">
                        <?php echo $pending; ?> pending
                    </span>
                    <?php if ($failed > 0) : ?>
                    <span class="hm-badge hm-badge--red"><?php echo $failed; ?> failed</span>
                    <?php endif; ?>
                    <span class="hm-badge hm-badge--green"><?php echo $synced; ?> synced this week</span>
                    <?php if ($pending > 0) : ?>
                    <button class="hm-btn hm-btn--primary hm-btn--sm" id="hm-run-batch"
                            data-nonce="<?php echo esc_attr($nonce); ?>">
                        ↑ Push All to QuickBooks Now
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="hm-notice hm-notice--info" style="margin:0 1.5rem 1rem;font-size:13px;">
                Invoices are queued here when a fitting is completed. The batch runs automatically
                every <strong>Friday at 11 PM</strong>. You can also push manually at any time.
            </div>

            <?php if (empty($queue)) : ?>
            <p class="hm-muted" style="padding:1rem 1.5rem;">No items in queue.</p>
            <?php else : ?>
            <table class="hm-table" style="margin:0;">
                <thead>
                    <tr>
                        <th>Reference</th><th>Patient</th><th>Type</th>
                        <th>Status</th><th>Queued</th><th>Synced</th><th>Tries</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($queue as $q) :
                    $badge = match($q->status) {
                        'synced'  => 'hm-badge--green',
                        'failed'  => 'hm-badge--red',
                        'pending' => 'hm-badge--orange',
                        default   => 'hm-badge--grey',
                    };
                ?>
                <tr>
                    <td><code class="hm-mono"><?php echo esc_html($q->ref_number ?? '—'); ?></code></td>
                    <td><?php echo esc_html($q->patient_name ?? '—'); ?></td>
                    <td><?php echo esc_html(ucfirst($q->entity_type)); ?></td>
                    <td>
                        <span class="hm-badge <?php echo $badge; ?>"><?php echo esc_html(ucfirst($q->status)); ?></span>
                        <?php if ($q->last_error) : ?>
                        <span title="<?php echo esc_attr($q->last_error); ?>" style="cursor:help;color:#94a3b8;font-size:11px;">⚠</span>
                        <?php endif; ?>
                    </td>
                    <td class="hm-muted hm-mono" style="font-size:12px;"><?php echo esc_html(date('d/m/Y', strtotime($q->queued_at))); ?></td>
                    <td class="hm-muted hm-mono" style="font-size:12px;"><?php echo $q->synced_at ? esc_html(date('d/m/Y', strtotime($q->synced_at))) : '—'; ?></td>
                    <td class="hm-muted" style="font-size:12px;"><?php echo intval($q->attempts); ?>/3</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <div id="hm-batch-msg" class="hm-notice" style="display:none;margin:1rem 1.5rem;"></div>
        </div>

        <script>
        (function() {
            const btn = document.getElementById('hm-run-batch');
            if (!btn) return;
            btn.addEventListener('click', function() {
                if (!confirm('Push all pending invoices and credit notes to QuickBooks now?')) return;
                btn.disabled = true; btn.textContent = 'Syncing…';
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: {'Content-Type':'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({action:'hm_run_qbo_batch', nonce:btn.dataset.nonce})
                }).then(r=>r.json()).then(d=>{
                    const msg = document.getElementById('hm-batch-msg');
                    msg.style.display = 'block';
                    if (d.success) {
                        msg.className = 'hm-notice hm-notice--success';
                        msg.textContent = '✓ Done — Synced: ' + d.data.synced + '   Failed: ' + d.data.failed;
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        msg.className = 'hm-notice hm-notice--error';
                        msg.textContent = d.data || 'Sync failed.';
                        btn.disabled = false; btn.textContent = '↑ Push All to QuickBooks Now';
                    }
                });
            });
        })();
        </script>
        <?php return ob_get_clean();
    }

    // =========================================================================
    // AJAX HANDLERS
    // =========================================================================

    public static function ajax_save_supplier_invoice() {
        check_ajax_referer('hearmed_nonce','nonce');
        if (!HearMed_Auth::can('view_accounting')) wp_send_json_error('Access denied.');

        $sn    = sanitize_text_field($_POST['supplier_name'] ?? '');
        $sr    = sanitize_text_field($_POST['supplier_invoice_ref'] ?? '');
        $items = $_POST['items'] ?? [];

        if (!$sn)          wp_send_json_error('Supplier name required.');
        if (!$sr)          wp_send_json_error('Invoice reference required.');
        if (empty($items)) wp_send_json_error('At least one line item required.');

        $id = HearMed_DB::insert('hearmed_core.supplier_invoices', [
            'supplier_name'        => $sn,
            'supplier_invoice_ref' => $sr,
            'invoice_date'         => sanitize_text_field($_POST['invoice_date'] ?? date('Y-m-d')),
            'due_date'             => sanitize_text_field($_POST['due_date'] ?? date('Y-m-d', strtotime('+30 days'))),
            'currency'             => sanitize_text_field($_POST['currency'] ?? 'EUR'),
            'notes'                => sanitize_textarea_field($_POST['notes'] ?? ''),
            'subtotal'             => floatval($_POST['subtotal'] ?? 0),
            'vat_total'            => floatval($_POST['vat_total'] ?? 0),
            'total_amount'         => floatval($_POST['total_amount'] ?? 0),
            'payment_status'       => 'unpaid',
            'qbo_sync_status'      => 'pending',
            'created_by'           => HearMed_Auth::current_user()->id ?? null,
            'created_at'           => date('Y-m-d H:i:s'),
        ]);

        if (!$id) wp_send_json_error('Failed to save. Please try again.');

        foreach ($items as $item) {
            $qty   = intval($item['qty'] ?? 1);
            $price = floatval($item['unit_price'] ?? 0);
            $vatR  = floatval($item['vat_rate'] ?? 0);
            $ex    = $qty * $price;
            $vat   = $ex * ($vatR / 100);
            HearMed_DB::insert('hearmed_core.supplier_invoice_items', [
                'supplier_invoice_id' => $id,
                'description'         => sanitize_text_field($item['description'] ?? ''),
                'qbo_account_id'      => sanitize_text_field($item['qbo_account_id'] ?? ''),
                'quantity'            => $qty,
                'unit_price'          => $price,
                'vat_rate'            => $vatR,
                'vat_amount'          => $vat,
                'line_total'          => $ex + $vat,
            ]);
        }

        $qbo = HearMed_QBO::push_supplier_bill($id);
        wp_send_json_success(['message' => 'Saved' . ($qbo ? ' and pushed to QuickBooks.' : '. QBO sync pending.'), 'id' => $id]);
    }

    public static function ajax_retry_qbo_sync() {
        check_ajax_referer('hearmed_nonce','nonce');
        $inv_id = intval($_POST['invoice_id'] ?? 0);
        $order  = HearMed_DB::get_row("SELECT id FROM hearmed_core.orders WHERE invoice_id=\$1", [$inv_id]);
        if (!$order) wp_send_json_error('Order not found.');
        HearMed_QBO::retry_failed_invoice($order->id)
            ? wp_send_json_success('Synced.')
            : wp_send_json_error('Failed. Check QBO connection.');
    }

    public static function ajax_run_qbo_batch() {
        check_ajax_referer('hearmed_nonce','nonce');
        if (!in_array(HearMed_Auth::current_role(), ['c_level','finance'])) {
            wp_send_json_error('Access denied.');
        }
        $result = HearMed_QBO::run_batch_sync();
        wp_send_json_success($result);
    }

    public static function ajax_assign_bank_txn() {
        check_ajax_referer('hearmed_nonce','nonce');
        $id  = intval($_POST['txn_id'] ?? 0);
        $acc = sanitize_text_field($_POST['account_id'] ?? '');
        if (!$id || !$acc) wp_send_json_error('Missing data.');
        HearMed_DB::update('hearmed_admin.qbo_bank_transactions', ['assigned' => true, 'qbo_account_id' => $acc], ['id' => $id]);
        wp_send_json_success('Assigned.');
    }

    public static function ajax_qbo_sync_accounts() {
        check_ajax_referer('hearmed_nonce','nonce');
        $a = HearMed_QBO::pull_chart_of_accounts();
        $a ? wp_send_json_success(count($a).' accounts synced.') : wp_send_json_error('Failed. Check connection.');
    }

    public static function ajax_qbo_disconnect() {
        check_ajax_referer('hearmed_nonce','nonce');
        HearMed_QBO::disconnect();
        wp_send_json_success('Disconnected.');
    }

    // =========================================================================
    // CRON — weekly auto-batch (registered in class-hearmed-ajax.php)
    // =========================================================================

    public static function cron_weekly_qbo_batch() {
        HearMed_QBO::run_batch_sync();
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private static function payment_badge($status) {
        $map = [
            'Paid'          => 'hm-badge--green',
            'paid'          => 'hm-badge--green',
            'Partially Paid'=> 'hm-badge--orange',
            'Unpaid'        => 'hm-badge--red',
            'unpaid'        => 'hm-badge--red',
            'Refunded'      => 'hm-badge--grey',
        ];
        return '<span class="hm-badge '.($map[$status] ?? 'hm-badge--grey').'">'.esc_html(ucfirst($status)).'</span>';
    }
}