<?php
/**
 * HearMed QBO Webhook Sender
 * 
 * Fires when an invoice is created in the HearMed Portal.
 * Sends invoice data to Make.com, which forwards it to QuickBooks Online.
 * 
 * Usage:
 *   HearMed_QBO::send_invoice( $order_id );
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_QBO {

    // -------------------------------------------------------------------------
    // Paste your Make.com webhook URL here
    // -------------------------------------------------------------------------
    const WEBHOOK_URL = 'https://hook.eu1.make.com/du287oukiq6ccttcf0cq8t6hs735ak7d';

    // -------------------------------------------------------------------------
    // Call this whenever an invoice is created
    // -------------------------------------------------------------------------
    public static function send_invoice( $order_id ) {

        $payload = self::build_payload( $order_id );

        if ( is_wp_error( $payload ) ) {
            self::log( 'Could not build payload for order ' . $order_id . ': ' . $payload->get_error_message() );
            return false;
        }

        $response = wp_remote_post( self::WEBHOOK_URL, [
            'method'      => 'POST',
            'timeout'     => 15,
            'headers'     => [ 'Content-Type' => 'application/json' ],
            'body'        => wp_json_encode( $payload ),
        ]);

        if ( is_wp_error( $response ) ) {
            self::log( 'Webhook failed for order ' . $order_id . ': ' . $response->get_error_message() );
            return false;
        }

        $status = wp_remote_retrieve_response_code( $response );

        if ( $status !== 200 ) {
            self::log( 'Webhook returned HTTP ' . $status . ' for order ' . $order_id );
            return false;
        }

        self::log( 'Invoice synced to QBO for order ' . $order_id );
        return true;
    }

    // -------------------------------------------------------------------------
    // Build the JSON payload from the order in PostgreSQL
    // -------------------------------------------------------------------------
    private static function build_payload( $order_id ) {

        $db = HearMed_DB::instance();

        // Fetch the order
        $order = $db->get_row(
            'SELECT o.*, p.first_name, p.last_name, p.email, p.phone,
                    p.address_line1, p.address_line2, p.city, p.county, p.eircode,
                    c.name AS clinic_name
             FROM orders o
             JOIN patients p ON p.id = o.patient_id
             JOIN clinics  c ON c.id = o.clinic_id
             WHERE o.id = $1',
            [ $order_id ]
        );

        if ( ! $order ) {
            return new WP_Error( 'not_found', 'Order not found: ' . $order_id );
        }

        // Fetch the line items for this order
        $items = $db->get_results(
            'SELECT oi.quantity, oi.unit_price, oi.line_total, oi.vat_amount,
                    pr.manufacturer, pr.model, pr.style
             FROM order_items oi
             JOIN products pr ON pr.id = oi.product_id
             WHERE oi.order_id = $1',
            [ $order_id ]
        );

        // Build line items array for QBO
        $line_items = [];
        foreach ( $items as $item ) {
            $line_items[] = [
                'description' => trim( $item->manufacturer . ' ' . $item->model . ' ' . $item->style ),
                'quantity'    => (int) $item->quantity,
                'unit_price'  => (float) $item->unit_price,
                'line_total'  => (float) $item->line_total,
                'vat_amount'  => (float) $item->vat_amount,
            ];
        }

        // Assemble the full payload
        return [
            'source'          => 'hearmed_portal',
            'event'           => 'invoice_created',
            'invoice_ref'     => $order->invoice_number ?? ( 'HM-' . $order_id ),
            'order_id'        => $order_id,
            'invoice_date'    => $order->created_at ?? date( 'Y-m-d' ),
            'due_date'        => $order->due_date ?? date( 'Y-m-d', strtotime( '+30 days' ) ),
            'clinic'          => $order->clinic_name,
            'currency'        => 'EUR',
            'customer'        => [
                'first_name'  => $order->first_name,
                'last_name'   => $order->last_name,
                'email'       => $order->email,
                'phone'       => $order->phone,
                'address'     => array_filter([
                    $order->address_line1,
                    $order->address_line2,
                    $order->city,
                    $order->county,
                    $order->eircode,
                ]),
            ],
            'line_items'      => $line_items,
            'subtotal'        => (float) $order->subtotal,
            'vat_total'       => (float) $order->vat_total,
            'total'           => (float) $order->total_amount,
            'payment_method'  => $order->payment_method ?? null,
            'prsi_grant'      => (float) ( $order->prsi_grant ?? 0 ),
            'notes'           => $order->notes ?? null,
        ];
    }

    // -------------------------------------------------------------------------
    // Simple error log — appears in admin-debug.php log viewer
    // -------------------------------------------------------------------------
    private static function log( $message ) {
        error_log( '[HearMed_QBO] ' . $message );
    }
}