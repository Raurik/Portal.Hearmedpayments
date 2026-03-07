<?php
/**
 * HearMed Finance — centralised financial transaction recording & credit handling.
 *
 * Provides a single source of truth for every money movement in the portal:
 * deposits, payments, credit notes, refunds, and credit applications.
 *
 * Usage:
 *   HearMed_Finance::record( 'payment', 1500.00, [ 'patient_id' => 42, 'order_id' => 7 ] );
 *   HearMed_Finance::get_patient_credit_balance( 42 );
 *   HearMed_Finance::apply_credit( 42, 200.00, $invoice_id );
 *
 * All queries use HearMed_DB with $1 $2 parameterised syntax — never $wpdb.
 *
 * @package HearMed_Portal
 * @since   6.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_Finance {

    /* =====================================================================
     * Allowed transaction types.
     * 'refund', 'credit_note' and 'credit_applied' are stored as negative
     * amounts at DB level.
     * ===================================================================== */
    private static $credit_types = [ 'refund', 'credit_note', 'credit_applied' ];

    private static $valid_types = [
        'deposit',        // patient paid deposit → goes to patient_credits
        'payment',        // payment taken at fitting
        'credit_note',    // credit note issued
        'refund',         // cash/cheque refund sent
        'credit_applied', // patient credit used at fitting
    ];

    /* =====================================================================
     * Internal flags — ensures each CREATE TABLE runs at most once per
     * request (avoids redundant DDL on every call).
     * ===================================================================== */
    private static $ft_table_ensured = false;
    private static $ca_table_ensured = false;
    private static $ca_columns = null;

    // ─────────────────────────────────────────────────────────────────────
    // METHOD 1: record()
    // ─────────────────────────────────────────────────────────────────────
    /**
     * Insert one row into hearmed_core.financial_transactions.
     *
     * @param  string $type   One of the $valid_types above.
     * @param  float  $amount Always pass a positive number — negation is
     *                        handled internally for credits/refunds.
     * @param  array  $args {
     *     @type int    $patient_id      (required)
     *     @type int    $order_id        (optional)
     *     @type int    $invoice_id      (optional)
     *     @type int    $credit_note_id  (optional)
     *     @type string $payment_method  'card'|'cash'|'cheque' (optional)
     *     @type int    $staff_id        (optional)
     *     @type int    $clinic_id       (optional)
     *     @type string $notes           (optional)
     *     @type string $transaction_date Y-m-d, defaults to today (optional)
     *     @type string $reference       invoice number, order number etc (optional)
     * }
     * @return int|false New transaction ID on success, false on failure.
     */
    public static function record( $type, $amount, $args = [] ) {
        try {
            // Validate type
            if ( ! in_array( $type, self::$valid_types, true ) ) {
                error_log( '[HearMed Finance] record() failed: invalid type "' . $type . '"' );
                return false;
            }

            // patient_id is required
            $patient_id = (int) ( $args['patient_id'] ?? 0 );
            if ( ! $patient_id ) {
                error_log( '[HearMed Finance] record() failed: patient_id is required' );
                return false;
            }

            // Ensure the table exists
            self::ensure_financial_transactions_table();

            // Store credits / refunds as negative
            $stored_amount = (float) $amount;
            if ( in_array( $type, self::$credit_types, true ) ) {
                $stored_amount = -abs( $stored_amount );
            }

            $data = [
                'transaction_type' => $type,
                'amount'           => $stored_amount,
                'patient_id'       => $patient_id,
                'transaction_date' => $args['transaction_date'] ?? date( 'Y-m-d' ),
            ];

            // Optional columns
            if ( ! empty( $args['order_id'] ) )        $data['order_id']       = (int) $args['order_id'];
            if ( ! empty( $args['invoice_id'] ) )      $data['invoice_id']     = (int) $args['invoice_id'];
            if ( ! empty( $args['credit_note_id'] ) )  $data['credit_note_id'] = (int) $args['credit_note_id'];
            if ( ! empty( $args['payment_method'] ) )  $data['payment_method'] = $args['payment_method'];
            if ( ! empty( $args['staff_id'] ) )        $data['staff_id']       = (int) $args['staff_id'];
            if ( ! empty( $args['clinic_id'] ) )       $data['clinic_id']      = (int) $args['clinic_id'];
            if ( isset( $args['notes'] ) && $args['notes'] !== '' ) $data['notes'] = $args['notes'];
            if ( ! empty( $args['reference'] ) )       $data['reference']      = $args['reference'];

            $id = HearMed_DB::insert( 'financial_transactions', $data );

            if ( $id === false ) {
                error_log( '[HearMed Finance] record() failed: ' . HearMed_DB::last_error() );
                return false;
            }

            return (int) $id;

        } catch ( \Exception $e ) {
            error_log( '[HearMed Finance] record() failed: ' . $e->getMessage() );
            return false;
        }
    }

    // ─────────────────────────────────────────────────────────────────────
    // METHOD 2: get_patient_credit_balance()
    // ─────────────────────────────────────────────────────────────────────
    /**
     * Total of all active patient_credits for this patient.
     *
     * @param  int   $patient_id
     * @return float
     */
    public static function get_patient_credit_balance( $patient_id ) {
        $credits = self::get_patient_credits( $patient_id, 'active' );
        $balance = 0.0;

        foreach ( $credits as $credit ) {
            $balance += (float) ( $credit->remaining_amount ?? 0 );
        }

        return round( $balance, 2 );
    }

    // ─────────────────────────────────────────────────────────────────────
    // METHOD 3: get_patient_credits()
    // ─────────────────────────────────────────────────────────────────────
    /**
     * Rows from patient_credits for a patient.
     *
     * @param  int    $patient_id
     * @param  string $status  'active' | 'used' | 'expired' | 'all'
     * @return array  Array of row objects.
     */
    public static function get_patient_credits( $patient_id, $status = 'active' ) {
        $rows = HearMed_DB::get_results(
            "SELECT pc.*, o.order_number,
                    GREATEST(pc.amount - COALESCE(pc.used_amount, 0), 0) AS remaining_amount
               FROM hearmed_core.patient_credits pc
               LEFT JOIN hearmed_core.orders o ON o.id = pc.order_id
              WHERE pc.patient_id = $1
                AND (pc.notes IS NULL OR pc.notes NOT ILIKE 'Deposit at order creation%')
              ORDER BY pc.created_at DESC",
            [ (int) $patient_id ]
        );

        if ( empty( $rows ) ) {
            return [];
        }

        $applied_by_credit = self::get_credit_application_totals_by_credit( $patient_id );
        $out = [];

        foreach ( $rows as $row ) {
            $amount = (float) ( $row->amount ?? 0 );
            $stored_used = (float) ( $row->used_amount ?? 0 );
            $applied_used = (float) ( $applied_by_credit[ (int) $row->id ] ?? 0 );
            $effective_used = min( $amount, max( $stored_used, $applied_used ) );
            $remaining = max( 0, $amount - $effective_used );
            $raw_status = strtolower( trim( (string) ( $row->status ?? 'active' ) ) );

            if ( $raw_status === 'expired' ) {
                $effective_status = 'expired';
            } else {
                $effective_status = $remaining <= 0.009 ? 'used' : 'active';
            }

            $row->used_amount = round( $effective_used, 2 );
            $row->remaining_amount = round( $remaining, 2 );
            $row->status = $effective_status;

            if ( $status !== 'all' && $effective_status !== $status ) {
                continue;
            }

            $out[] = $row;
        }

        return $out;
    }

    // ─────────────────────────────────────────────────────────────────────
    // METHOD 4: get_patient_transactions()
    // ─────────────────────────────────────────────────────────────────────
    /**
     * Financial transaction history for a patient.
     *
     * @param  int $patient_id
     * @param  int $limit      Max rows to return, default 50.
     * @return array
     */
    public static function get_patient_transactions( $patient_id, $limit = 50 ) {
        return HearMed_DB::get_results(
            "SELECT ft.*,
                    s.first_name || ' ' || s.last_name AS staff_name,
                    cl.clinic_name,
                    inv.invoice_number
               FROM hearmed_core.financial_transactions ft
               LEFT JOIN hearmed_reference.staff s   ON s.id  = ft.staff_id
               LEFT JOIN hearmed_reference.clinics cl ON cl.id = ft.clinic_id
               LEFT JOIN hearmed_core.invoices inv ON inv.id = ft.invoice_id
              WHERE ft.patient_id = $1
                            ORDER BY ft.transaction_date DESC, ft.created_at DESC
              LIMIT $2",
            [ (int) $patient_id, (int) $limit ]
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // METHOD 5: get_order_transactions()
    // ─────────────────────────────────────────────────────────────────────
    /**
     * Full financial story for a single order (chronological).
     *
     * @param  int   $order_id
     * @return array
     */
    public static function get_order_transactions( $order_id ) {
        return HearMed_DB::get_results(
            "SELECT ft.*,
                    s.first_name || ' ' || s.last_name AS staff_name
               FROM hearmed_core.financial_transactions ft
               LEFT JOIN hearmed_reference.staff s ON s.id = ft.staff_id
              WHERE ft.order_id = $1
              ORDER BY ft.created_at ASC",
            [ (int) $order_id ]
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // METHOD 6: apply_credit()
    // ─────────────────────────────────────────────────────────────────────
    /**
     * Consume patient credits against an invoice (FIFO — oldest first).
     *
     * Called at fitting time when a patient's existing credit balance is
     * applied to a new invoice.
     *
     * @param  int        $patient_id
     * @param  float      $amount_to_apply  Positive amount to consume.
     * @param  int        $invoice_id       Invoice the credit is applied to.
     * @param  int|null   $order_id         Optional order context.
     * @param  int|null   $staff_id         Staff member performing the action.
     * @return float      Total amount actually applied (may be less than
     *                    requested if the patient's balance was insufficient).
     */
    public static function apply_credit( $patient_id, $amount_to_apply, $invoice_id, $order_id = null, $staff_id = null, $transaction_date = null ) {
        // Ensure supporting tables exist
        self::ensure_credit_applications_table();
        self::ensure_financial_transactions_table();

        $patient_id      = (int) $patient_id;
        $amount_to_apply = (float) $amount_to_apply;
        $invoice_id      = (int) $invoice_id;
        $remaining       = $amount_to_apply;
        $total_applied   = 0.0;

        if ( $remaining <= 0 ) return 0.0;

                // 1. Fetch active credits oldest-first (FIFO)
                $credits = HearMed_DB::get_results(
                        "SELECT id, amount, COALESCE(used_amount, 0) AS used_amount
                             FROM hearmed_core.patient_credits
                            WHERE patient_id = $1
                                AND status = 'active'
                                AND (notes IS NULL OR notes NOT ILIKE 'Deposit at order creation%')
                            ORDER BY created_at ASC",
            [ $patient_id ]
        );

        if ( empty( $credits ) ) return 0.0;

        HearMed_DB::begin_transaction();

        try {
            foreach ( $credits as $credit ) {
                if ( $remaining <= 0 ) break;

                $credit_amount = (float) $credit->amount;
                $used_amount   = (float) ( $credit->used_amount ?? 0 );
                $available     = max( 0, $credit_amount - $used_amount );
                if ( $available <= 0 ) {
                    continue;
                }

                $apply_now     = min( $available, $remaining );
                $new_used      = $used_amount + $apply_now;
                $is_fully_used = ( $new_used >= ( $credit_amount - 0.009 ) );

                // Mark the credit as used
                HearMed_DB::update(
                    'patient_credits',
                    [
                        'used_amount' => round( $new_used, 2 ),
                        'status'      => $is_fully_used ? 'used' : 'active',
                        'updated_at'  => date( 'Y-m-d H:i:s' ),
                    ],
                    [ 'id' => (int) $credit->id ]
                );

                // Record in credit_applications (schema differs between environments)
                $ca_inserted = HearMed_DB::insert( 'credit_applications', [
                    'credit_id'      => (int) $credit->id,
                    'invoice_id'     => $invoice_id,
                    'amount_applied' => $apply_now,
                    'applied_by'     => $staff_id ? (int) $staff_id : null,
                ] );
                if ( ! $ca_inserted ) {
                    HearMed_DB::insert( 'credit_applications', [
                        'patient_credit_id' => (int) $credit->id,
                        'invoice_id'        => $invoice_id,
                        'amount'            => $apply_now,
                        'applied_by'        => $staff_id ? (int) $staff_id : null,
                    ] );
                }

                // Record the financial transaction
                self::record( 'credit_applied', $apply_now, [
                    'patient_id'       => $patient_id,
                    'invoice_id'       => $invoice_id,
                    'order_id'         => $order_id,
                    'staff_id'         => $staff_id,
                    'transaction_date' => $transaction_date ?: date( 'Y-m-d' ),
                    'notes'            => 'Credit #' . $credit->id . ' applied to invoice',
                ] );

                $remaining     -= $apply_now;
                $total_applied += $apply_now;
            }

            // Keep invoice balance/paid status in sync after credit applications
            if ( $total_applied > 0 ) {
                $inv = HearMed_DB::get_row(
                    "SELECT COALESCE(grand_total, 0) AS grand_total, COALESCE(credit_applied, 0) AS credit_applied
                     FROM hearmed_core.invoices
                     WHERE id = $1",
                    [ $invoice_id ]
                );
                $paid_total = (float) HearMed_DB::get_var(
                    "SELECT COALESCE(SUM(amount), 0)
                     FROM hearmed_core.payments
                     WHERE invoice_id = $1 AND is_refund = false",
                    [ $invoice_id ]
                );

                $new_credit_applied = (float) ( $inv->credit_applied ?? 0 ) + $total_applied;
                $new_balance = max( 0, (float) ( $inv->grand_total ?? 0 ) - $paid_total - $new_credit_applied );

                HearMed_DB::update( 'invoices', [
                    'credit_applied'    => round( $new_credit_applied, 2 ),
                    'balance_remaining' => round( $new_balance, 2 ),
                    'payment_status'    => $new_balance <= 0.009 ? 'Paid' : 'Partial',
                    'updated_at'        => date( 'Y-m-d H:i:s' ),
                ], [ 'id' => $invoice_id ] );
            }

            HearMed_DB::commit();

        } catch ( \Exception $e ) {
            HearMed_DB::rollback();
            error_log( '[HearMed Finance] apply_credit() failed: ' . $e->getMessage() );
            return 0.0;
        }

        return $total_applied;
    }

    // =====================================================================
    // Internal helpers
    // =====================================================================

    /**
     * CREATE TABLE IF NOT EXISTS for financial_transactions.
     */
    private static function ensure_financial_transactions_table() {
        if ( self::$ft_table_ensured ) return;

        HearMed_DB::query(
            "CREATE TABLE IF NOT EXISTS hearmed_core.financial_transactions (
                id                BIGSERIAL PRIMARY KEY,
                transaction_type  VARCHAR(50)  NOT NULL,
                amount            NUMERIC(10,2) NOT NULL,
                patient_id        BIGINT REFERENCES hearmed_core.patients(id),
                order_id          BIGINT REFERENCES hearmed_core.orders(id),
                invoice_id        BIGINT REFERENCES hearmed_core.invoices(id),
                credit_note_id    BIGINT REFERENCES hearmed_core.credit_notes(id),
                payment_method    VARCHAR(50),
                staff_id          BIGINT REFERENCES hearmed_reference.staff(id),
                clinic_id         BIGINT REFERENCES hearmed_reference.clinics(id),
                notes             TEXT,
                reference         VARCHAR(100),
                transaction_date  DATE         NOT NULL DEFAULT CURRENT_DATE,
                created_at        TIMESTAMPTZ  NOT NULL DEFAULT NOW()
            )"
        );

        self::$ft_table_ensured = true;
    }

    /**
     * CREATE TABLE IF NOT EXISTS for credit_applications.
     */
    private static function ensure_credit_applications_table() {
        if ( self::$ca_table_ensured ) return;

        HearMed_DB::query(
            "CREATE TABLE IF NOT EXISTS hearmed_core.credit_applications (
                id              BIGSERIAL PRIMARY KEY,
                credit_id       BIGINT NOT NULL REFERENCES hearmed_core.patient_credits(id),
                invoice_id      BIGINT NOT NULL REFERENCES hearmed_core.invoices(id),
                amount_applied  NUMERIC(10,2) NOT NULL,
                applied_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                applied_by      BIGINT REFERENCES hearmed_reference.staff(id)
            )"
        );

        self::$ca_table_ensured = true;
    }

    private static function get_credit_application_totals_by_credit( $patient_id ) {
        $patient_id = (int) $patient_id;
        if ( $patient_id <= 0 ) {
            return [];
        }

        self::ensure_credit_applications_table();

        $columns = self::get_credit_application_columns();
        if ( empty( $columns['credit'] ) || empty( $columns['amount'] ) ) {
            return [];
        }

        $credit_col = $columns['credit'];
        $amount_col = $columns['amount'];

        $rows = HearMed_DB::get_results(
            "SELECT ca.{$credit_col} AS credit_id,
                    COALESCE(SUM(ca.{$amount_col}), 0) AS applied_total
               FROM hearmed_core.credit_applications ca
               JOIN hearmed_core.patient_credits pc ON pc.id = ca.{$credit_col}
              WHERE pc.patient_id = $1
              GROUP BY ca.{$credit_col}",
            [ $patient_id ]
        );

        $totals = [];
        foreach ( $rows as $row ) {
            $totals[ (int) $row->credit_id ] = (float) $row->applied_total;
        }

        return $totals;
    }

    private static function get_credit_application_columns() {
        if ( is_array( self::$ca_columns ) ) {
            return self::$ca_columns;
        }

        $cols = HearMed_DB::get_results(
            "SELECT column_name
               FROM information_schema.columns
              WHERE table_schema = 'hearmed_core'
                AND table_name = 'credit_applications'"
        );

        $names = [];
        foreach ( $cols as $col ) {
            $names[] = (string) ( $col->column_name ?? '' );
        }

        self::$ca_columns = [
            'credit' => in_array( 'credit_id', $names, true ) ? 'credit_id' : ( in_array( 'patient_credit_id', $names, true ) ? 'patient_credit_id' : null ),
            'amount' => in_array( 'amount_applied', $names, true ) ? 'amount_applied' : ( in_array( 'amount', $names, true ) ? 'amount' : null ),
        ];

        return self::$ca_columns;
    }
}