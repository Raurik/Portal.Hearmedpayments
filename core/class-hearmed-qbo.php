<?php
/**
 * HearMed QBO — QuickBooks Online Direct API Integration
 * OAuth 2.0 + full bidirectional sync
 *
 * Replaces the old Make.com webhook approach entirely.
 *
 * ═══════════════════════════════════════════════════════════
 * REQUIRES IN wp-config.php
 * ─────────────────────────────────────────────────────────
 *  define( 'HEARMED_QBO_CLIENT_ID',     '...' );
 *  define( 'HEARMED_QBO_CLIENT_SECRET', '...' );
 *  define( 'HEARMED_QBO_REDIRECT_URI',  'https://portal.hearmedpayments.net/?qbo_callback=1' );
 *  define( 'HEARMED_QBO_ENVIRONMENT',   'sandbox' );
 *
 * @package HearMed_Portal
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_QBO {

    const AUTH_URL  = 'https://appcenter.intuit.com/connect/oauth2';
    const TOKEN_URL = 'https://oauth.platform.intuit.com/oauth2/v1/tokens/bearer';
    const REVOKE_URL= 'https://developer.api.intuit.com/v2/oauth2/tokens/revoke';
    const SCOPE     = 'com.intuit.quickbooks.accounting';

    private static function api_base() {
        $env  = defined('HEARMED_QBO_ENVIRONMENT') ? HEARMED_QBO_ENVIRONMENT : 'sandbox';
        $host = $env === 'production'
            ? 'https://quickbooks.api.intuit.com'
            : 'https://sandbox-quickbooks.api.intuit.com';
        return $host . '/v3/company/' . self::get_realm_id();
    }

    // =========================================================================
    // OAUTH
    // =========================================================================

    public static function get_auth_url() {
        $state = wp_create_nonce('qbo_oauth_state');
        update_option('hearmed_qbo_oauth_state', $state);
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'     => HEARMED_QBO_CLIENT_ID,
            'response_type' => 'code',
            'scope'         => self::SCOPE,
            'redirect_uri'  => HEARMED_QBO_REDIRECT_URI,
            'state'         => $state,
        ]);
    }

    public static function handle_callback() {
        $state = sanitize_text_field($_GET['state']   ?? '');
        $code  = sanitize_text_field($_GET['code']    ?? '');
        $realm = sanitize_text_field($_GET['realmId'] ?? '');

        if ( $state !== get_option('hearmed_qbo_oauth_state','') ) {
            wp_die('QuickBooks connection failed: invalid state.');
        }
        if ( ! $code || ! $realm ) {
            wp_die('QuickBooks connection failed: missing code.');
        }

        $resp = wp_remote_post(self::TOKEN_URL, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode(HEARMED_QBO_CLIENT_ID.':'.HEARMED_QBO_CLIENT_SECRET),
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'Accept'        => 'application/json',
            ],
            'body' => [
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => HEARMED_QBO_REDIRECT_URI,
            ],
        ]);

        if ( is_wp_error($resp) ) wp_die('QBO token exchange failed: ' . $resp->get_error_message());

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ( empty($body['access_token']) ) wp_die('QBO token exchange: no access_token returned.');

        self::save_tokens([
            'realm_id'           => $realm,
            'access_token'       => $body['access_token'],
            'refresh_token'      => $body['refresh_token'],
            'expires_at'         => date('Y-m-d H:i:s', time() + intval($body['expires_in'] ?? 3600)),
            'refresh_expires_at' => date('Y-m-d H:i:s', time() + intval($body['x_refresh_token_expires_in'] ?? 8726400)),
        ]);

        delete_option('hearmed_qbo_oauth_state');
        wp_redirect(HearMed_Utils::page_url('accounting') . '?qbo_connected=1');
        exit;
    }

    public static function disconnect() {
        $t = self::get_tokens();
        if ( $t ) {
            wp_remote_post(self::REVOKE_URL, [
                'timeout' => 10,
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode(HEARMED_QBO_CLIENT_ID.':'.HEARMED_QBO_CLIENT_SECRET),
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ],
                'body' => ['token' => $t->refresh_token],
            ]);
        }
        HearMed_DB::query("DELETE FROM hearmed_admin.qbo_tokens WHERE id > 0", []);
        return true;
    }

    public static function is_connected() {
        $t = self::get_tokens();
        return $t && strtotime($t->refresh_expires_at) > time();
    }

    public static function connection_status() {
        if ( ! self::is_connected() ) return ['connected' => false, 'auth_url' => self::get_auth_url()];
        $t = self::get_tokens();
        return ['connected' => true, 'realm_id' => $t->realm_id, 'expires_at' => $t->expires_at];
    }

    // =========================================================================
    // TOKEN MANAGEMENT
    // =========================================================================

    private static function get_tokens() {
        return HearMed_DB::get_row("SELECT * FROM hearmed_admin.qbo_tokens ORDER BY id DESC LIMIT 1", []);
    }

    private static function get_realm_id() {
        $t = self::get_tokens();
        return $t->realm_id ?? '';
    }

    private static function save_tokens($data) {
        HearMed_DB::query("DELETE FROM hearmed_admin.qbo_tokens WHERE id > 0", []);
        HearMed_DB::insert('hearmed_admin.qbo_tokens', array_merge($data, ['created_at' => date('Y-m-d H:i:s')]));
    }

    private static function get_access_token() {
        $t = self::get_tokens();
        if ( ! $t ) return false;

        // Still valid
        if ( strtotime($t->expires_at) > time() + 60 ) return $t->access_token;

        // Refresh
        $resp = wp_remote_post(self::TOKEN_URL, [
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode(HEARMED_QBO_CLIENT_ID.':'.HEARMED_QBO_CLIENT_SECRET),
                'Content-Type'  => 'application/x-www-form-urlencoded',
                'Accept'        => 'application/json',
            ],
            'body' => ['grant_type' => 'refresh_token', 'refresh_token' => $t->refresh_token],
        ]);

        if ( is_wp_error($resp) ) { error_log('[QBO] Token refresh failed: ' . $resp->get_error_message()); return false; }

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ( empty($body['access_token']) ) { error_log('[QBO] Token refresh: no access_token'); return false; }

        self::save_tokens([
            'realm_id'           => $t->realm_id,
            'access_token'       => $body['access_token'],
            'refresh_token'      => $body['refresh_token'] ?? $t->refresh_token,
            'expires_at'         => date('Y-m-d H:i:s', time() + intval($body['expires_in'] ?? 3600)),
            'refresh_expires_at' => date('Y-m-d H:i:s', time() + intval($body['x_refresh_token_expires_in'] ?? 8726400)),
        ]);

        return $body['access_token'];
    }

    // =========================================================================
    // API HELPER
    // =========================================================================

    private static function api($method, $endpoint, $body = null, $query = []) {
        $token = self::get_access_token();
        if ( ! $token ) return ['error' => 'Not connected to QuickBooks'];

        $query['minorversion'] = 65;
        $url = self::api_base() . $endpoint . '?' . http_build_query($query);

        $args = [
            'method'  => $method,
            'timeout' => 20,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
        ];
        if ( $body ) $args['body'] = wp_json_encode($body);

        $resp   = wp_remote_request($url, $args);
        if ( is_wp_error($resp) ) return ['error' => $resp->get_error_message()];

        $status = wp_remote_retrieve_response_code($resp);
        $data   = json_decode(wp_remote_retrieve_body($resp), true);

        if ( $status >= 400 ) {
            $msg = $data['Fault']['Error'][0]['Message'] ?? "HTTP {$status}";
            error_log("[QBO] {$method} {$endpoint} → {$status}: {$msg}");
            return ['error' => $msg];
        }

        return $data;
    }

    // =========================================================================
    // PUSH — CUSTOMER INVOICE (called from mod-orders on Complete)
    // =========================================================================

    public static function on_invoice_created($order_id) {
        if ( ! self::is_connected() ) {
            self::log_sync($order_id, 'invoice', 'skipped', 'QBO not connected');
            return false;
        }
        $customer_id = self::sync_customer($order_id);
        if ( ! $customer_id ) {
            self::log_sync($order_id, 'invoice', 'failed', 'Could not sync customer');
            return false;
        }
        return self::push_invoice($order_id, $customer_id);
    }

    private static function sync_customer($order_id) {
        $db = HearMed_DB::instance();
        $row = $db->get_row(
            "SELECT o.patient_id, p.first_name, p.last_name, p.email, p.phone,
                    p.address_line1, p.address_line2, p.city, p.county, p.eircode,
                    p.qbo_customer_id
             FROM hearmed_core.orders o
             JOIN hearmed_core.patients p ON p.id = o.patient_id
             WHERE o.id = \$1", [$order_id]
        );
        if ( ! $row ) return false;

        // Already have a QBO customer ID
        if ( ! empty($row->qbo_customer_id) ) return $row->qbo_customer_id;

        $name = $row->first_name . ' ' . $row->last_name;

        // Search QBO first
        $s = self::api('GET', '/query', null, ['query' => "SELECT * FROM Customer WHERE DisplayName = '{$name}'"]);
        if ( ! empty($s['QueryResponse']['Customer'][0]['Id']) ) {
            $id = $s['QueryResponse']['Customer'][0]['Id'];
            $db->query("UPDATE hearmed_core.patients SET qbo_customer_id = \$1 WHERE id = \$2", [$id, $row->patient_id]);
            return $id;
        }

        // Create
        $r = self::api('POST', '/customer', [
            'DisplayName'      => $name . ' (HM-' . $row->patient_id . ')',
            'GivenName'        => $row->first_name,
            'FamilyName'       => $row->last_name,
            'PrimaryEmailAddr' => $row->email ? ['Address' => $row->email] : null,
            'PrimaryPhone'     => $row->phone  ? ['FreeFormNumber' => $row->phone] : null,
            'BillAddr'         => [
                'Line1'   => $row->address_line1 ?? '',
                'City'    => $row->city ?? '',
                'Country' => 'Ireland',
            ],
            'CurrencyRef' => ['value' => 'EUR'],
        ]);

        if ( ! empty($r['Customer']['Id']) ) {
            $id = $r['Customer']['Id'];
            $db->query("UPDATE hearmed_core.patients SET qbo_customer_id = \$1 WHERE id = \$2", [$id, $row->patient_id]);
            return $id;
        }

        return false;
    }

    private static function push_invoice($order_id, $qbo_customer_id) {
        $db = HearMed_DB::instance();

        $order = $db->get_row(
            "SELECT o.*, inv.invoice_number, inv.id AS invoice_db_id
             FROM hearmed_core.orders o
             LEFT JOIN hearmed_core.invoices inv ON inv.id = o.invoice_id
             WHERE o.id = \$1", [$order_id]
        );

        $items = $db->get_results(
            "SELECT oi.quantity, oi.unit_retail_price, oi.line_total,
                    oi.vat_rate, oi.item_type, oi.item_description, oi.ear_side,
                    COALESCE(CONCAT(m.name,' ',p.product_name,' ',p.style), s.service_name, oi.item_description) AS display_name
             FROM hearmed_core.order_items oi
             LEFT JOIN hearmed_reference.products p      ON p.id = oi.item_id AND oi.item_type='product'
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = p.manufacturer_id
             LEFT JOIN hearmed_reference.services s      ON s.id = oi.item_id AND oi.item_type='service'
             WHERE oi.order_id = \$1 ORDER BY oi.line_number", [$order_id]
        );

        $lines = [];
        $n = 1;
        foreach ($items as $item) {
            $desc = trim($item->display_name) . ($item->ear_side ? ' ('.$item->ear_side.')' : '');
            $lines[] = [
                'LineNum'     => $n++,
                'Description' => $desc,
                'Amount'      => floatval($item->line_total),
                'DetailType'  => 'SalesItemLineDetail',
                'SalesItemLineDetail' => [
                    'Qty'       => intval($item->quantity),
                    'UnitPrice' => floatval($item->unit_retail_price),
                    'TaxCodeRef'=> ['value' => floatval($item->vat_rate) > 0 ? 'TAX' : 'NON'],
                ],
            ];
        }

        if ($order->prsi_applicable && $order->prsi_amount > 0) {
            $lines[] = [
                'LineNum'     => $n++,
                'Description' => 'PRSI Grant Deduction',
                'Amount'      => -floatval($order->prsi_amount),
                'DetailType'  => 'SalesItemLineDetail',
                'SalesItemLineDetail' => ['Qty' => 1, 'UnitPrice' => -floatval($order->prsi_amount), 'TaxCodeRef' => ['value' => 'NON']],
            ];
        }

        $result = self::api('POST', '/invoice', [
            'CustomerRef'  => ['value' => $qbo_customer_id],
            'DocNumber'    => $order->invoice_number ?? ('HM-'.$order_id),
            'TxnDate'      => $order->fitted_at ? date('Y-m-d', strtotime($order->fitted_at)) : date('Y-m-d'),
            'CurrencyRef'  => ['value' => 'EUR'],
            'Line'         => $lines,
            'CustomerMemo' => ['value' => 'HearMed Portal — '.($order->order_number ?? '')],
        ]);

        if ( ! empty($result['Invoice']['Id']) ) {
            $qbo_id = $result['Invoice']['Id'];
            if ($order->invoice_db_id) {
                HearMed_DB::update('hearmed_core.invoices', [
                    'quickbooks_id'   => $qbo_id,
                    'qbo_sync_status' => 'synced',
                    'qbo_synced_at'   => date('Y-m-d H:i:s'),
                ], ['id' => $order->invoice_db_id]);
            }
            self::log_sync($order_id, 'invoice', 'success', 'QBO Invoice ID: '.$qbo_id);
            return true;
        }

        self::log_sync($order_id, 'invoice', 'failed', $result['error'] ?? 'Unknown');
        return false;
    }

    // =========================================================================
    // PUSH — SUPPLIER BILL
    // =========================================================================

    public static function push_supplier_bill($supplier_invoice_id) {
        if ( ! self::is_connected() ) return false;

        $db   = HearMed_DB::instance();
        $bill = $db->get_row(
            "SELECT * FROM hearmed_core.supplier_invoices WHERE id = \$1", [$supplier_invoice_id]
        );
        if ( ! $bill ) return false;

        // Get or create vendor
        $vendor_id = self::get_or_create_vendor($bill->supplier_name ?? 'Unknown');
        if ( ! $vendor_id ) return false;

        $items = $db->get_results(
            "SELECT * FROM hearmed_core.supplier_invoice_items WHERE supplier_invoice_id = \$1",
            [$supplier_invoice_id]
        );

        $lines = [];
        $n = 1;
        foreach ($items as $item) {
            $lines[] = [
                'LineNum'     => $n++,
                'Description' => $item->description,
                'Amount'      => floatval($item->line_total),
                'DetailType'  => 'AccountBasedExpenseLineDetail',
                'AccountBasedExpenseLineDetail' => [
                    'AccountRef' => ['value' => $item->qbo_account_id ?? '1'],
                ],
            ];
        }

        $result = self::api('POST', '/bill', [
            'VendorRef'   => ['value' => $vendor_id],
            'DocNumber'   => $bill->supplier_invoice_ref,
            'TxnDate'     => $bill->invoice_date,
            'DueDate'     => $bill->due_date,
            'CurrencyRef' => ['value' => 'EUR'],
            'Line'        => $lines,
        ]);

        if ( ! empty($result['Bill']['Id']) ) {
            HearMed_DB::update('hearmed_core.supplier_invoices', [
                'qbo_bill_id'     => $result['Bill']['Id'],
                'qbo_sync_status' => 'synced',
                'qbo_synced_at'   => date('Y-m-d H:i:s'),
            ], ['id' => $supplier_invoice_id]);
            return true;
        }

        HearMed_DB::update('hearmed_core.supplier_invoices', ['qbo_sync_status' => 'failed'], ['id' => $supplier_invoice_id]);
        return false;
    }

    private static function get_or_create_vendor($name) {
        $s = self::api('GET', '/query', null, ['query' => "SELECT * FROM Vendor WHERE DisplayName = '{$name}'"]);
        if ( ! empty($s['QueryResponse']['Vendor'][0]['Id']) ) return $s['QueryResponse']['Vendor'][0]['Id'];

        $r = self::api('POST', '/vendor', ['DisplayName' => $name, 'CurrencyRef' => ['value' => 'EUR']]);
        return $r['Vendor']['Id'] ?? false;
    }

    // =========================================================================
    // PULL — BANK FEED
    // =========================================================================

    public static function pull_bank_transactions($days_back = 30) {
        if ( ! self::is_connected() ) return [];

        $since  = date('Y-m-d', strtotime("-{$days_back} days"));
        $result = self::api('GET', '/query', null, [
            'query' => "SELECT * FROM Purchase WHERE TxnDate >= '{$since}' MAXRESULTS 200",
        ]);

        $txns = $result['QueryResponse']['Purchase'] ?? [];
        $db   = HearMed_DB::instance();

        foreach ($txns as $t) {
            $exists = $db->get_var(
                "SELECT id FROM hearmed_admin.qbo_bank_transactions WHERE qbo_txn_id = \$1",
                [$t['Id']]
            );
            if ( ! $exists ) {
                $db->insert('hearmed_admin.qbo_bank_transactions', [
                    'qbo_txn_id'     => $t['Id'],
                    'txn_date'       => $t['TxnDate'],
                    'amount'         => floatval($t['TotalAmt'] ?? 0),
                    'description'    => $t['PrivateNote'] ?? ($t['PaymentType'] ?? ''),
                    'account_name'   => $t['AccountRef']['name'] ?? '',
                    'qbo_account_id' => $t['AccountRef']['value'] ?? '',
                    'assigned'       => false,
                    'raw_json'       => json_encode($t),
                    'created_at'     => date('Y-m-d H:i:s'),
                ]);
            }
        }

        return $txns;
    }

    // =========================================================================
    // PULL — CHART OF ACCOUNTS
    // =========================================================================

    public static function pull_chart_of_accounts() {
        if ( ! self::is_connected() ) return [];

        $result   = self::api('GET', '/query', null, ['query' => "SELECT * FROM Account WHERE Active = true MAXRESULTS 200"]);
        $accounts = $result['QueryResponse']['Account'] ?? [];
        $db       = HearMed_DB::instance();

        $db->query("DELETE FROM hearmed_admin.qbo_accounts WHERE id > 0", []);
        foreach ($accounts as $a) {
            $db->insert('hearmed_admin.qbo_accounts', [
                'qbo_account_id'   => $a['Id'],
                'account_name'     => $a['Name'],
                'account_type'     => $a['AccountType'],
                'account_sub_type' => $a['AccountSubType'] ?? '',
                'is_active'        => true,
                'synced_at'        => date('Y-m-d H:i:s'),
            ]);
        }

        return $accounts;
    }

    // =========================================================================
    // RETRY
    // =========================================================================

    public static function retry_failed_invoice($order_id) {
        $cid = self::sync_customer($order_id);
        return $cid ? self::push_invoice($order_id, $cid) : false;
    }

    // =========================================================================
    // SYNC LOG
    // =========================================================================

    private static function log_sync($ref_id, $type, $status, $message) {
        HearMed_DB::insert('hearmed_admin.qbo_sync_log', [
            'reference_id' => $ref_id,
            'sync_type'    => $type,
            'status'       => $status,
            'message'      => $message,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
        if ($status === 'failed') error_log("[HearMed QBO] FAILED #{$ref_id}: {$message}");
    }
}