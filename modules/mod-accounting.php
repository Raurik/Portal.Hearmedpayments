<?php
/**
 * HearMed Accounting Module
 *
 * ⚠️  SCAFFOLD - TODO: Implement accounting and financial management
 *
 * Planned features:
 * - Invoice generation and management
 * - Payment processing and tracking
 * - Credit notes and adjustments
 * - Financial transaction logging
 * - Tax calculation (VAT handling for Ireland)
 * - Reconciliation reports
 * - QuickBooks Online integration for sync
 *
 * @package HearMed_Portal
 * @since   5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Standalone render function called by router
function hm_accounting_render() {
    ?>
    <div class="hm-content">
        <div class="hm-page-header">
            <h1 class="hm-page-title">Accounting</h1>
        </div>
        <div class="hm-placeholder" style="padding:3rem;text-align:center;color:#94a3b8;">
            <p>Accounting module — coming soon</p>
            <p style="font-size:0.875rem;margin-top:0.5rem;">Invoices, payments, and financial tracking</p>
        </div>
    </div>
    <?php
}

// TODO: Implement invoice CRUD operations
// TODO: Implement payment processing
// TODO: Implement financial reporting
// TODO: Implement QBO integration
