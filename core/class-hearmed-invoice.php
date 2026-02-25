<?php
/**
 * HearMed Invoice Engine
 *
 * Creates invoices from paid orders, calculates multi-rate VAT, generates
 * printable HTML, handles credit notes, and queues to QuickBooks.
 *
 * Add to hearmed-calendar.php:
 *   require_once plugin_dir_path(__FILE__) . 'core/class-hearmed-invoice.php';
 *   add_action('init', ['HearMed_Invoice', 'init']);
 *
 * @package HearMed_Portal
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_Invoice {

    // ═══════════════════════════════════════════════════════════════════════
    // SETTINGS — all read from wp_options (set in Finance Settings page)
    // ═══════════════════════════════════════════════════════════════════════

    private static function get_invoice_prefix() {
        return get_option( 'hm_invoice_prefix', 'HMIN' );
    }

    private static function get_credit_note_prefix() {
        return get_option( 'hm_credit_note_prefix', 'HMCN' );
    }

    private static function get_prsi_per_ear() {
        return floatval( get_option( 'hm_prsi_amount_per_ear', 500 ) );
    }

    /**
     * Map a product vat_category to the configured VAT rate.
     * Categories match exactly what's stored in products.vat_category.
     */
    private static function vat_rate_for_category( $category ) {
        $map = [
            'Hearing Aids'       => floatval( get_option( 'hm_vat_hearing_aids', 0 ) ),
            'Accessories'        => floatval( get_option( 'hm_vat_accessories',  0 ) ),
            'Services'           => floatval( get_option( 'hm_vat_services',    13.5 ) ),
            'Consumables'        => floatval( get_option( 'hm_vat_consumables', 23 ) ),
            'Bundled Items'      => floatval( get_option( 'hm_vat_bundled',     0 ) ),
            'Other Audiological' => floatval( get_option( 'hm_vat_other_aud',  13.5 ) ),
        ];
        return $map[ $category ] ?? floatval( get_option( 'hm_vat_consumables', 23 ) );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // INIT
    // ═══════════════════════════════════════════════════════════════════════

    public static function init() {
        add_action( 'wp_ajax_hm_get_invoice',       [__CLASS__, 'ajax_get_invoice'] );
        add_action( 'wp_ajax_hm_print_invoice',     [__CLASS__, 'ajax_print_invoice'] );
        add_action( 'wp_ajax_hm_create_credit_note',[__CLASS__, 'ajax_create_credit_note'] );
        add_action( 'wp_ajax_hm_mark_cn_cheque',    [__CLASS__, 'ajax_mark_cheque_sent'] );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // INVOICE CREATION — called from mod-orders.php ajax_complete_order()
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Create (or update) the invoice for a completed order.
     * Called after payment is received at fitting.
     *
     * @param  int   $order_id
     * @param  array $payment_data  ['amount', 'payment_date', 'payment_method', 'received_by']
     * @return int|false  Invoice ID or false on failure
     */
    public static function create_from_order( $order_id, $payment_data ) {
        $db = HearMed_DB::instance();

        // Load the order with patient and clinic data
        $order = $db->get_row(
            "SELECT o.*,
                    p.first_name, p.last_name, p.email, p.phone,
                    p.date_of_birth, p.pps_number,
                    p.address_line1, p.address_line2, p.city, p.county, p.eircode,
                    c.clinic_name, c.address_line1 AS clinic_addr1,
                    c.address_line2 AS clinic_addr2, c.city AS clinic_city,
                    c.phone AS clinic_phone, c.email AS clinic_email,
                    c.vat_number AS clinic_vat,
                    CONCAT(s.first_name,' ',s.last_name) AS dispenser_name
             FROM hearmed_core.orders o
             JOIN hearmed_core.patients p        ON p.id = o.patient_id
             JOIN hearmed_reference.clinics c    ON c.id = o.clinic_id
             LEFT JOIN hearmed_reference.staff s ON s.id = o.staff_id
             WHERE o.id = $1",
            [ $order_id ]
        );
        if ( ! $order ) {
            error_log( "[HearMed Invoice] Order {$order_id} not found." );
            return false;
        }

        // If invoice already exists for this order, just mark it paid
        if ( $order->invoice_id ) {
            $db->update( 'invoices', [
                'payment_status'   => 'Paid',
                'balance_remaining'=> 0,
                'updated_at'       => date( 'Y-m-d H:i:s' ),
            ], [ 'id' => $order->invoice_id ] );
            self::record_payment( $order->invoice_id, $order, $payment_data );
            return $order->invoice_id;
        }

        // Load order items with VAT category
        $items = $db->get_results(
            "SELECT oi.*,
                    CASE
                        WHEN oi.item_type = 'product'
                            THEN COALESCE(pr.vat_category, 'Hearing Aids')
                        ELSE COALESCE(svc.vat_category, 'Services')
                    END AS vat_category,
                    CASE
                        WHEN oi.item_type = 'product'
                            THEN CONCAT(m.name,' ',pr.product_name,' ',pr.style)
                        ELSE svc.service_name
                    END AS item_name
             FROM hearmed_core.order_items oi
             LEFT JOIN hearmed_reference.products pr      ON pr.id = oi.item_id AND oi.item_type='product'
             LEFT JOIN hearmed_reference.manufacturers m  ON m.id = pr.manufacturer_id
             LEFT JOIN hearmed_reference.services svc     ON svc.id = oi.item_id AND oi.item_type='service'
             WHERE oi.order_id = $1
             ORDER BY oi.line_number",
            [ $order_id ]
        );

        if ( empty( $items ) ) {
            error_log( "[HearMed Invoice] No items for order {$order_id}." );
            return false;
        }

        // Calculate VAT breakdown
        $vat_breakdown = self::calculate_vat( $items );

        // Generate invoice number (atomic — lock row first)
        $invoice_number = self::next_invoice_number();

        HearMed_DB::begin_transaction();

        // Create invoice record
        $invoice_id = $db->insert( 'invoices', [
            'invoice_number'    => $invoice_number,
            'patient_id'        => $order->patient_id,
            'order_id'          => $order_id,
            'staff_id'          => $order->staff_id,
            'clinic_id'         => $order->clinic_id,
            'invoice_date'      => $payment_data['payment_date'] ?? date( 'Y-m-d' ),
            'subtotal'          => $order->subtotal,
            'discount_total'    => $order->discount_total ?? 0,
            'net_total'         => $vat_breakdown['net_total'],
            'vat_total'         => $vat_breakdown['vat_total'],
            'vat_breakdown'     => json_encode( $vat_breakdown['by_rate'] ),
            'grand_total'       => $order->grand_total,
            'balance_remaining' => 0,
            'payment_status'    => 'Paid',
            'prsi_applicable'   => $order->prsi_applicable,
            'prsi_amount'       => $order->prsi_amount ?? 0,
            'created_by'        => $payment_data['received_by'] ?? null,
        ] );

        if ( ! $invoice_id ) {
            HearMed_DB::rollback();
            error_log( "[HearMed Invoice] Failed to insert invoice for order {$order_id}." );
            return false;
        }

        // Create invoice line items (mirrors order items with correct VAT)
        $line = 1;
        foreach ( $items as $item ) {
            $vat_rate   = self::vat_rate_for_category( $item->vat_category );
            $net        = floatval( $item->unit_retail_price ) * intval( $item->quantity );
            $vat_amount = round( $net * ( $vat_rate / 100 ), 2 );
            $line_total = $net + $vat_amount;

            $db->insert( 'invoice_items', [
                'invoice_id'       => $invoice_id,
                'line_number'      => $line++,
                'item_type'        => $item->item_type,
                'item_id'          => $item->item_id,
                'item_description' => $item->item_name ?? $item->item_description,
                'ear_side'         => $item->ear_side ?? null,
                'quantity'         => intval( $item->quantity ),
                'unit_price'       => floatval( $item->unit_retail_price ),
                'vat_category'     => $item->vat_category,
                'vat_rate'         => $vat_rate,
                'vat_amount'       => $vat_amount,
                'line_total'       => $line_total,
            ] );
        }

        // Link invoice back to order
        $db->update( 'orders', [ 'invoice_id' => $invoice_id ], [ 'id' => $order_id ] );

        // Record payment
        self::record_payment( $invoice_id, $order, $payment_data );

        // Generate and store HTML snapshot for re-printing
        $invoice_data = self::get_invoice_data( $invoice_id );
        if ( $invoice_data ) {
            $html = self::render_invoice_html( $invoice_data );
            $db->update( 'invoices', [ 'invoice_template' => $html ], [ 'id' => $invoice_id ] );
        }

        HearMed_DB::commit();

        // Fire notification to finance team
        if ( class_exists( 'HearMed_Notifications' ) && method_exists( 'HearMed_Notifications', 'create_for_role' ) ) {
            HearMed_Notifications::create_for_role( 'finance', 'invoice_created', [
                'invoice_id'     => $invoice_id,
                'invoice_number' => $invoice_number,
                'patient_name'   => $order->first_name . ' ' . $order->last_name,
                'amount'         => $order->grand_total,
            ] );
        }

        // Queue QBO sync (fires asynchronously via existing QBO class)
        if ( class_exists( 'HearMed_QBO' ) && method_exists( 'HearMed_QBO', 'queue_invoice_sync' ) ) {
            HearMed_QBO::queue_invoice_sync( $invoice_id );
        }

        return $invoice_id;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // VAT CALCULATION
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Calculate VAT breakdown from an array of order item objects.
     * Groups by VAT rate, returns totals per rate plus overall totals.
     */
    public static function calculate_vat( $items ) {
        $by_rate   = [];  // keyed by vat_rate as string
        $net_total = 0;
        $vat_total = 0;

        foreach ( $items as $item ) {
            $vat_category = $item->vat_category ?? 'Hearing Aids';
            $vat_rate     = self::vat_rate_for_category( $vat_category );
            $net          = floatval( $item->unit_retail_price ?? $item->unit_price ?? 0 ) * intval( $item->quantity ?? 1 );
            $vat          = round( $net * ( $vat_rate / 100 ), 2 );

            $rate_key = number_format( $vat_rate, 1 );
            if ( ! isset( $by_rate[ $rate_key ] ) ) {
                $by_rate[ $rate_key ] = [
                    'rate'       => $vat_rate,
                    'label'      => $vat_rate . '%',
                    'net'        => 0,
                    'vat'        => 0,
                    'gross'      => 0,
                ];
            }
            $by_rate[ $rate_key ]['net']   += $net;
            $by_rate[ $rate_key ]['vat']   += $vat;
            $by_rate[ $rate_key ]['gross'] += $net + $vat;
            $net_total += $net;
            $vat_total += $vat;
        }

        return [
            'net_total'  => round( $net_total, 2 ),
            'vat_total'  => round( $vat_total, 2 ),
            'gross_total'=> round( $net_total + $vat_total, 2 ),
            'by_rate'    => array_values( $by_rate ),
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // INVOICE NUMBER GENERATION
    // ═══════════════════════════════════════════════════════════════════════

    private static function next_invoice_number() {
        $prefix = self::get_invoice_prefix();
        // Use DB function to get next sequential number (avoids race conditions)
        $next = HearMed_DB::get_var(
            "SELECT public.hearmed_next_invoice_seq($1)",
            [ $prefix ]
        );
        return $prefix . str_pad( (int) $next, 5, '0', STR_PAD_LEFT );
    }

    private static function next_credit_note_number() {
        $prefix = self::get_credit_note_prefix();
        $next   = HearMed_DB::get_var(
            "SELECT public.hearmed_next_credit_note_seq($1)",
            [ $prefix ]
        );
        return $prefix . str_pad( (int) $next, 5, '0', STR_PAD_LEFT );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // CREDIT NOTES
    // ═══════════════════════════════════════════════════════════════════════

    public static function create_credit_note( $invoice_id, $reason, $refund_type = 'cheque', $created_by = null ) {
        $db = HearMed_DB::instance();

        $invoice = $db->get_row(
            "SELECT inv.*, p.first_name, p.last_name
             FROM hearmed_core.invoices inv
             JOIN hearmed_core.patients p ON p.id = inv.patient_id
             WHERE inv.id = $1",
            [ $invoice_id ]
        );
        if ( ! $invoice ) return false;

        $cn_number = self::next_credit_note_number();

        $cn_id = $db->insert( 'credit_notes', [
            'credit_note_number' => $cn_number,
            'invoice_id'         => $invoice_id,
            'patient_id'         => $invoice->patient_id,
            'order_id'           => $invoice->order_id,
            'amount'             => $invoice->grand_total,
            'reason'             => $reason,
            'credit_date'        => date( 'Y-m-d' ),
            'refund_type'        => $refund_type,
            'created_by'         => $created_by,
        ] );

        if ( ! $cn_id ) return false;

        // Notify C-Level and Finance
        if ( class_exists( 'HearMed_Notifications' ) && method_exists( 'HearMed_Notifications', 'create_for_role' ) ) {
            $payload = [
                'cn_id'        => $cn_id,
                'cn_number'    => $cn_number,
                'patient_name' => $invoice->first_name . ' ' . $invoice->last_name,
                'amount'       => $invoice->grand_total,
                'reason'       => $reason,
            ];
            HearMed_Notifications::create_for_role( 'c_level', 'credit_note_created', $payload );
            HearMed_Notifications::create_for_role( 'finance', 'credit_note_created', $payload );
        }

        return $cn_id;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DATA LOADER
    // ═══════════════════════════════════════════════════════════════════════

    public static function get_invoice_data( $invoice_id ) {
        $db = HearMed_DB::instance();

        $invoice = $db->get_row(
            "SELECT inv.*,
                    p.first_name, p.last_name, p.email, p.phone,
                    p.address_line1, p.address_line2, p.city AS p_city, p.county, p.eircode,
                    c.clinic_name, c.address_line1 AS c_addr1, c.address_line2 AS c_addr2,
                    c.city AS c_city, c.phone AS c_phone, c.email AS c_email, c.vat_number,
                    CONCAT(s.first_name,' ',s.last_name) AS dispenser_name,
                    pay.payment_method, pay.payment_date AS paid_date
             FROM hearmed_core.invoices inv
             JOIN hearmed_core.patients p        ON p.id = inv.patient_id
             JOIN hearmed_reference.clinics c    ON c.id = inv.clinic_id
             LEFT JOIN hearmed_reference.staff s ON s.id = inv.staff_id
             LEFT JOIN hearmed_core.payments pay ON pay.invoice_id = inv.id AND pay.is_refund = false
             WHERE inv.id = $1
             LIMIT 1",
            [ $invoice_id ]
        );
        if ( ! $invoice ) return null;

        $items = $db->get_results(
            "SELECT * FROM hearmed_core.invoice_items WHERE invoice_id = $1 ORDER BY line_number",
            [ $invoice_id ]
        );

        $invoice->items        = $items;
        $invoice->vat_breakdown = $invoice->vat_breakdown
            ? json_decode( $invoice->vat_breakdown, true )
            : null;

        return $invoice;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PDF / PRINT RENDER
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Render a print-ready HTML invoice. Opens in new tab for browser-to-PDF.
     * Also stored as invoice_template snapshot for re-printing.
     */
    public static function render_invoice_html( $invoice ) {
        $is_paid  = $invoice->payment_status === 'Paid';
        $prsi     = floatval( $invoice->prsi_amount );
        $vat_bd   = $invoice->vat_breakdown ?? [];
        $company  = get_option( 'hm_report_company_name', 'HearMed Acoustic Health Care Ltd' );
        $vat_num  = $invoice->vat_number ?? get_option( 'hm_vat_number', '' );

        ob_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?php echo esc_html( $invoice->invoice_number ); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 13px; color: #151B33; background: #fff; padding: 2rem; max-width: 860px; margin: 0 auto; }
        .hm-inv__header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem; border-bottom: 3px solid #151B33; padding-bottom: 1.25rem; }
        .hm-inv__logo { font-size: 1.5rem; font-weight: 700; color: #151B33; }
        .hm-inv__logo span { color: #0BB4C4; }
        .hm-inv__meta { text-align: right; font-size: 0.85rem; }
        .hm-inv__meta strong { font-size: 1.1rem; display: block; color: #151B33; }
        .hm-inv__parties { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem; }
        .hm-inv__from, .hm-inv__to { font-size: 0.85rem; line-height: 1.6; }
        .hm-inv__section-label { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: #94a3b8; margin-bottom: 0.3rem; }
        .hm-inv__from strong, .hm-inv__to strong { font-size: 0.9rem; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; font-size: 0.85rem; }
        thead th { background: #151B33; color: #fff; padding: 8px 10px; text-align: left; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.04em; }
        tbody td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; }
        tbody tr:last-child td { border-bottom: none; }
        .hm-inv__vat-badge { display: inline-block; font-size: 0.7rem; padding: 1px 6px; border-radius: 3px; font-weight: 600; background: #f0fdf4; color: #166534; margin-left: 4px; }
        .hm-inv__vat-badge--r { background: #fff7ed; color: #9a3412; }
        .hm-inv__vat-badge--s { background: #faf5ff; color: #6b21a8; }
        .text-right { text-align: right; }
        .hm-inv__totals { width: 320px; margin-left: auto; font-size: 0.875rem; }
        .hm-inv__totals td { padding: 5px 10px; }
        .hm-inv__totals .hm-inv__grand td { font-size: 1rem; font-weight: 700; border-top: 2px solid #151B33; padding-top: 10px; }
        .hm-inv__prsi { color: #059669; }
        .hm-inv__vat-table { margin-top: 1.5rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 1rem; font-size: 0.8rem; }
        .hm-inv__vat-table h4 { margin-bottom: 0.5rem; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; }
        .hm-inv__vat-table table { margin: 0; }
        .hm-inv__vat-table thead th { background: #e2e8f0; color: #151B33; font-size: 0.7rem; }
        .hm-inv__payment { margin-top: 1.5rem; padding: 1rem; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; }
        .hm-inv__paid-stamp { font-size: 1.4rem; font-weight: 900; color: #059669; border: 3px solid #059669; padding: 4px 14px; border-radius: 4px; transform: rotate(-5deg); display: inline-block; }
        .hm-inv__footer { margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #e2e8f0; font-size: 0.75rem; color: #94a3b8; display: flex; justify-content: space-between; }
        .no-print { display: block; margin-bottom: 1rem; }
        @media print { .no-print { display: none !important; } body { padding: 0.5cm; } }
    </style>
</head>
<body>

<div class="no-print">
    <button onclick="window.print()" style="padding:8px 20px;background:#0BB4C4;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:14px;">
        🖨 Print / Save as PDF
    </button>
    <button onclick="window.close()" style="padding:8px 16px;background:#f1f5f9;color:#151B33;border:none;border-radius:4px;cursor:pointer;font-size:14px;margin-left:8px;">
        ✕ Close
    </button>
</div>

<!-- Header -->
<div class="hm-inv__header">
    <div class="hm-inv__logo">Hear<span>Med</span></div>
    <div class="hm-inv__meta">
        <strong>INVOICE</strong>
        <?php echo esc_html( $invoice->invoice_number ); ?><br>
        Date: <?php echo date( 'd M Y', strtotime( $invoice->invoice_date ) ); ?><br>
        <?php if ( $invoice->dispenser_name ) : ?>
        Audiologist: <?php echo esc_html( $invoice->dispenser_name ); ?>
        <?php endif; ?>
    </div>
</div>

<!-- From / To -->
<div class="hm-inv__parties">
    <div class="hm-inv__from">
        <div class="hm-inv__section-label">From</div>
        <strong><?php echo esc_html( $company ); ?></strong><br>
        <?php echo esc_html( $invoice->clinic_name ); ?><br>
        <?php if ( $invoice->c_addr1 ) echo esc_html( $invoice->c_addr1 ) . '<br>'; ?>
        <?php if ( $invoice->c_addr2 ) echo esc_html( $invoice->c_addr2 ) . '<br>'; ?>
        <?php if ( $invoice->c_city  ) echo esc_html( $invoice->c_city  ) . '<br>'; ?>
        <?php if ( $invoice->c_phone ) echo 'Tel: ' . esc_html( $invoice->c_phone ); ?>
        <?php if ( $vat_num          ) echo '<br>VAT: ' . esc_html( $vat_num ); ?>
    </div>
    <div class="hm-inv__to">
        <div class="hm-inv__section-label">Bill To</div>
        <strong><?php echo esc_html( $invoice->first_name . ' ' . $invoice->last_name ); ?></strong><br>
        <?php if ( $invoice->address_line1 ) echo esc_html( $invoice->address_line1 ) . '<br>'; ?>
        <?php if ( $invoice->address_line2 ) echo esc_html( $invoice->address_line2 ) . '<br>'; ?>
        <?php if ( $invoice->p_city       ) echo esc_html( $invoice->p_city        ) . '<br>'; ?>
        <?php if ( $invoice->eircode      ) echo esc_html( $invoice->eircode       ) . '<br>'; ?>
        <?php if ( $invoice->phone        ) echo esc_html( $invoice->phone         ) . '<br>'; ?>
        <?php if ( $invoice->email        ) echo esc_html( $invoice->email         ); ?>
    </div>
</div>

<!-- Line items -->
<table>
    <thead>
        <tr>
            <th>Description</th>
            <th>Ear</th>
            <th style="text-align:center;">Qty</th>
            <th class="text-right">Unit Price</th>
            <th class="text-right">VAT</th>
            <th class="text-right">Total (inc VAT)</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ( $invoice->items as $item ) :
        $rate    = floatval( $item->vat_rate );
        $badge   = '';
        if     ( $rate === 0.0  ) $badge = '<span class="hm-inv__vat-badge">0%</span>';
        elseif ( $rate === 13.5 ) $badge = '<span class="hm-inv__vat-badge hm-inv__vat-badge--r">13.5%</span>';
        else                      $badge = '<span class="hm-inv__vat-badge hm-inv__vat-badge--s">23%</span>';
    ?>
    <tr>
        <td><?php echo esc_html( $item->item_description ); ?><?php echo $badge; ?></td>
        <td><?php echo esc_html( $item->ear_side ?: '—' ); ?></td>
        <td style="text-align:center;"><?php echo intval( $item->quantity ); ?></td>
        <td class="text-right">€<?php echo number_format( $item->unit_price, 2 ); ?></td>
        <td class="text-right">€<?php echo number_format( $item->vat_amount, 2 ); ?></td>
        <td class="text-right">€<?php echo number_format( $item->line_total, 2 ); ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<!-- Totals -->
<table class="hm-inv__totals">
    <tbody>
        <tr>
            <td class="text-right">Net (ex VAT)</td>
            <td class="text-right">€<?php echo number_format( $invoice->net_total ?? ( $invoice->subtotal - $invoice->vat_total ), 2 ); ?></td>
        </tr>
        <tr>
            <td class="text-right">VAT</td>
            <td class="text-right">€<?php echo number_format( $invoice->vat_total, 2 ); ?></td>
        </tr>
        <?php if ( $prsi > 0 ) : ?>
        <tr class="hm-inv__prsi">
            <td class="text-right">PRSI Grant (DSP)</td>
            <td class="text-right">−€<?php echo number_format( $prsi, 2 ); ?></td>
        </tr>
        <?php endif; ?>
        <tr class="hm-inv__grand">
            <td class="text-right">Total Due</td>
            <td class="text-right">€<?php echo number_format( $invoice->grand_total, 2 ); ?></td>
        </tr>
    </tbody>
</table>

<!-- VAT analysis table (Irish Revenue format) -->
<?php if ( ! empty( $vat_bd ) ) : ?>
<div class="hm-inv__vat-table">
    <h4>VAT Analysis</h4>
    <table>
        <thead>
            <tr><th>VAT Rate</th><th class="text-right">Net Amount</th><th class="text-right">VAT Amount</th><th class="text-right">Gross</th></tr>
        </thead>
        <tbody>
        <?php foreach ( $vat_bd as $vr ) : ?>
        <tr>
            <td><?php echo number_format( $vr['rate'], 1 ); ?>%</td>
            <td class="text-right">€<?php echo number_format( $vr['net'], 2 ); ?></td>
            <td class="text-right">€<?php echo number_format( $vr['vat'], 2 ); ?></td>
            <td class="text-right">€<?php echo number_format( $vr['gross'], 2 ); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Payment confirmation -->
<?php if ( $is_paid ) : ?>
<div class="hm-inv__payment">
    <div>
        <strong>Payment received</strong><br>
        <?php if ( $invoice->paid_date ) echo 'Date: ' . date( 'd M Y', strtotime( $invoice->paid_date ) ) . '<br>'; ?>
        <?php if ( $invoice->payment_method ) echo 'Method: ' . esc_html( $invoice->payment_method ); ?>
    </div>
    <div class="hm-inv__paid-stamp">PAID</div>
</div>
<?php endif; ?>

<!-- Footer -->
<div class="hm-inv__footer">
    <span><?php echo esc_html( $company ); ?><?php if ( $vat_num ) echo ' — VAT Reg: ' . esc_html( $vat_num ); ?></span>
    <span>Invoice <?php echo esc_html( $invoice->invoice_number ); ?></span>
</div>

</body>
</html>
        <?php return ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    private static function record_payment( $invoice_id, $order, $payment_data ) {
        $db = HearMed_DB::instance();

        // Check constraint on payments.payment_method only allows:
        // Card, Cash, Bank Transfer, Cheque, PRSI
        $allowed_methods = [ 'Card', 'Cash', 'Bank Transfer', 'Cheque', 'PRSI' ];
        $method = $payment_data['payment_method'] ?? $order->payment_method ?? 'Card';
        if ( ! in_array( $method, $allowed_methods ) ) $method = 'Card';

        // Don't double-insert if payment already recorded
        $existing = $db->get_row(
            "SELECT id FROM hearmed_core.payments WHERE invoice_id = $1 AND is_refund = false LIMIT 1",
            [ $invoice_id ]
        );
        if ( $existing ) return;

        $db->insert( 'payments', [
            'invoice_id'     => $invoice_id,
            'patient_id'     => $order->patient_id,
            'amount'         => $payment_data['amount']        ?? $order->grand_total,
            'payment_date'   => $payment_data['payment_date']  ?? date( 'Y-m-d' ),
            'payment_method' => $method,
            'received_by'    => $payment_data['received_by']   ?? null,
            'clinic_id'      => $order->clinic_id,
            'created_by'     => $payment_data['received_by']   ?? null,
        ] );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX HANDLERS
    // ═══════════════════════════════════════════════════════════════════════

    public static function ajax_get_invoice() {
        check_ajax_referer( 'hearmed_nonce', 'nonce' );
        if ( ! HearMed_Auth::can( 'view_accounting' ) ) wp_send_json_error( 'Access denied.' );

        $invoice_id = intval( $_POST['invoice_id'] ?? 0 );
        $data       = self::get_invoice_data( $invoice_id );
        if ( ! $data ) wp_send_json_error( 'Invoice not found.' );

        wp_send_json_success( $data );
    }

    public static function ajax_print_invoice() {
        check_ajax_referer( 'hearmed_nonce', 'nonce' );

        $invoice_id = intval( $_GET['invoice_id'] ?? 0 );
        $data       = self::get_invoice_data( $invoice_id );
        if ( ! $data ) wp_die( 'Invoice not found.' );

        // Try stored snapshot first (preserves original formatting)
        if ( ! empty( $data->invoice_template ) ) {
            echo $data->invoice_template;
        } else {
            echo self::render_invoice_html( $data );
        }
        exit;
    }

    public static function ajax_create_credit_note() {
        check_ajax_referer( 'hearmed_nonce', 'nonce' );
        if ( ! HearMed_Auth::can( 'edit_accounting' ) ) wp_send_json_error( 'Access denied.' );

        $invoice_id  = intval( $_POST['invoice_id'] ?? 0 );
        $reason      = sanitize_textarea_field( $_POST['reason'] ?? '' );
        $refund_type = sanitize_key( $_POST['refund_type'] ?? 'cheque' );
        $user        = HearMed_Auth::current_user();

        if ( ! $invoice_id ) wp_send_json_error( 'No invoice specified.' );
        if ( ! $reason      ) wp_send_json_error( 'Please provide a reason.' );

        $cn_id = self::create_credit_note( $invoice_id, $reason, $refund_type, $user->ID ?? null );
        if ( ! $cn_id ) wp_send_json_error( 'Failed to create credit note.' );

        wp_send_json_success( [ 'message' => 'Credit note created.', 'cn_id' => $cn_id ] );
    }

    public static function ajax_mark_cheque_sent() {
        check_ajax_referer( 'hearmed_nonce', 'nonce' );
        if ( ! HearMed_Auth::can( 'edit_accounting' ) ) wp_send_json_error( 'Access denied.' );

        $cn_id = intval( $_POST['cn_id'] ?? 0 );
        $num   = sanitize_text_field( $_POST['cheque_number'] ?? '' );

        HearMed_DB::instance()->update( 'credit_notes', [
            'cheque_sent'      => true,
            'cheque_sent_date' => date( 'Y-m-d' ),
            'cheque_number'    => $num,
        ], [ 'id' => $cn_id ] );

        wp_send_json_success( 'Cheque marked as sent.' );
    }
}