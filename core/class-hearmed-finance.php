<?php
/**
 * HearMed Finance — Central financial transaction recorder.
 *
 * Every financial event (payment, credit note, refund, deposit, credit application)
 * MUST call HearMed_Finance::record() to create an audit trail in financial_transactions.
 *
 * @package HearMed_Portal
 * @since   5.5.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Finance {

    /**
     * Record a financial transaction.
     *
     * @param string   $type           Transaction type: deposit, payment, credit_note,
     *                                 refund, credit_applied, prsi_claim
     * @param float    $amount         Always positive. The type determines direction.
     * @param string   $debit_account  Account debited: cash, card, bank, bank_transfer,
     *                                 patient_credit, revenue, prsi_receivable
     * @param string   $credit_account Account credited (same options as debit)
     * @param string   $reference_type What this links to: order, invoice, credit_note, payment
     * @param int      $reference_id   ID of the referenced record
     * @param int      $patient_id
     * @param int|null $clinic_id
     * @param int|null $staff_id       Who performed the action
     * @param string   $notes          Optional description
     * @return int|false               The transaction ID, or false on failure
     */
    public static function record(
        string $type,
        float  $amount,
        string $debit_account,
        string $credit_account,
        string $reference_type,
        int    $reference_id,
        int    $patient_id,
        ?int   $clinic_id = null,
        ?int   $staff_id  = null,
        string $notes     = ''
    ) {
        if ($amount <= 0) {
            error_log('[HearMed Finance] Attempted to record transaction with zero/negative amount. Type: ' . $type);
            return false;
        }

        $db = HearMed_DB::instance();

        $tx_id = $db->insert('hearmed_core.financial_transactions', [
            'transaction_date' => date('Y-m-d'),
            'transaction_type' => $type,
            'amount'           => abs($amount),
            'debit_account'    => $debit_account,
            'credit_account'   => $credit_account,
            'reference_type'   => $reference_type,
            'reference_id'     => $reference_id,
            'patient_id'       => $patient_id,
            'clinic_id'        => $clinic_id,
            'staff_id'         => $staff_id,
            'notes'            => $notes,
            'created_by'       => $staff_id,
        ]);

        if (!$tx_id) {
            error_log('[HearMed Finance] Failed to record transaction. Type: ' . $type . ', Ref: ' . $reference_type . '#' . $reference_id);
        }

        return $tx_id;
    }

    /**
     * Get all financial transactions for a patient (newest first).
     *
     * @param int $patient_id
     * @return array
     */
    public static function get_patient_transactions(int $patient_id): array {
        $db = HearMed_DB::instance();
        return $db->get_results(
            "SELECT ft.*,
                    CONCAT(s.first_name, ' ', s.last_name) AS staff_name
             FROM hearmed_core.financial_transactions ft
             LEFT JOIN hearmed_reference.staff s ON s.id = ft.staff_id
             WHERE ft.patient_id = $1
             ORDER BY ft.transaction_date DESC, ft.created_at DESC",
            [$patient_id]
        ) ?: [];
    }

    /**
     * Get all financial transactions related to a specific order.
     * Follows references through invoices and credit notes linked to the order.
     *
     * @param int $order_id
     * @return array
     */
    public static function get_order_transactions(int $order_id): array {
        $db = HearMed_DB::instance();
        return $db->get_results(
            "SELECT ft.*
             FROM hearmed_core.financial_transactions ft
             WHERE (ft.reference_type = 'order' AND ft.reference_id = $1)
                OR (ft.reference_type = 'invoice' AND ft.reference_id IN (
                    SELECT id FROM hearmed_core.invoices WHERE order_id = $1
                ))
                OR (ft.reference_type = 'credit_note' AND ft.reference_id IN (
                    SELECT id FROM hearmed_core.credit_notes WHERE order_id = $1
                ))
             ORDER BY ft.transaction_date ASC, ft.created_at ASC",
            [$order_id]
        ) ?: [];
    }

    /**
     * Get total available credit balance for a patient.
     *
     * @param int $patient_id
     * @return float
     */
    public static function get_patient_credit_balance(int $patient_id): float {
        $db = HearMed_DB::instance();
        $val = $db->get_var(
            "SELECT COALESCE(SUM(remaining_amount), 0)
             FROM hearmed_core.patient_credits
             WHERE patient_id = $1 AND status = 'active' AND remaining_amount > 0",
            [$patient_id]
        );
        return floatval($val);
    }

    /**
     * Get all active credit rows for a patient (for display).
     *
     * @param int $patient_id
     * @return array
     */
    public static function get_patient_credits(int $patient_id): array {
        $db = HearMed_DB::instance();
        return $db->get_results(
            "SELECT pc.*, cn.credit_note_number,
                    (pc.amount - pc.used_amount) AS remaining_amount
             FROM hearmed_core.patient_credits pc
             LEFT JOIN hearmed_core.credit_notes cn ON cn.id = pc.credit_note_id
             WHERE pc.patient_id = $1
             ORDER BY pc.created_at DESC",
            [$patient_id]
        ) ?: [];
    }

    /**
     * Apply patient credit to an invoice/order.
     *
     * Draws down from oldest credits first (FIFO).
     * Returns the total amount actually applied (may be less than requested
     * if insufficient credit).
     *
     * @param int   $patient_id
     * @param int   $invoice_id   The invoice being paid
     * @param float $amount       Max amount to apply from credit
     * @param int   $applied_by   Staff ID
     * @return float              Actual amount applied
     */
    public static function apply_credit(
        int   $patient_id,
        int   $invoice_id,
        float $amount,
        int   $applied_by
    ): float {
        if ($amount <= 0) return 0.0;

        $db = HearMed_DB::instance();

        // Get active credits ordered oldest first (FIFO)
        $credits = $db->get_results(
            "SELECT id, remaining_amount
             FROM hearmed_core.patient_credits
             WHERE patient_id = $1 AND status = 'active' AND remaining_amount > 0
             ORDER BY created_at ASC",
            [$patient_id]
        );

        if (empty($credits)) return 0.0;

        $remaining_to_apply = $amount;
        $total_applied = 0.0;

        foreach ($credits as $credit) {
            if ($remaining_to_apply <= 0) break;

            $draw = min(floatval($credit->remaining_amount), $remaining_to_apply);

            // Record the application
            $db->insert('hearmed_core.credit_applications', [
                'patient_credit_id' => $credit->id,
                'invoice_id'        => $invoice_id,
                'amount'            => $draw,
                'applied_by'        => $applied_by,
            ]);

            // Update the credit's used_amount (remaining_amount auto-recalculates via GENERATED column)
            $db->query(
                "UPDATE hearmed_core.patient_credits
                 SET used_amount = used_amount + $1,
                     status = CASE
                         WHEN (amount - (used_amount + $1)) <= 0 THEN 'exhausted'
                         ELSE 'active'
                     END,
                     updated_at = NOW()
                 WHERE id = $2",
                [$draw, $credit->id]
            );

            $total_applied += $draw;
            $remaining_to_apply -= $draw;
        }

        return $total_applied;
    }
}