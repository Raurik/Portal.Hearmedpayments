<?php
/**
 * HearMed Reporting Module
 *
 * ⚠️  SCAFFOLD - TODO: Implement business reporting and analytics
 *
 * Planned features:
 * - Sales reports (daily, weekly, monthly)
 * - Revenue analysis by product/service
 * - Staff performance dashboards
 * - Patient acquisition reports
 * - Appointment utilization analysis
 * - Financial summaries
 * - KPI tracking
 *
 * @package HearMed_Portal
 * @since   5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Standalone render function called by router
function hm_reports_render() {
    ?>
    <div class="hm-content">
        <div class="hm-page-header">
            <h1 class="hm-page-title">Reports</h1>
        </div>
        <div class="hm-placeholder" style="padding:3rem;text-align:center;color:#94a3b8;">
            <p>Reporting module — coming soon</p>
            <p style="font-size:0.875rem;margin-top:0.5rem;">Business analytics and performance dashboards</p>
        </div>
    </div>
    <?php
}

// TODO: Implement sales reporting
// TODO: Implement performance dashboards
// TODO: Implement data exports
