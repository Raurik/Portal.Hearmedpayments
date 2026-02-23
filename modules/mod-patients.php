<?php
/**
 * HearMed Patients Module
 *
 * ⚠️  SCAFFOLD - TODO: Implement patient management features
 *
 * Planned features:
 * - Patient profile management
 * - Medical history tracking
 * - Consent management (GDPR)
 * - Patient communication preferences
 * - Document storage and retrieval
 * - Insurance information management
 * - Patient portal access
 *
 * @package HearMed_Portal
 * @since   5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Standalone render function called by router
function hm_patients_render() {
    ?>
    <div class="hm-content">
        <div class="hm-page-header">
            <h1 class="hm-page-title">Patients</h1>
            <button class="hm-btn hm-btn-primary" id="hm-add-patient-btn">
                <span>+</span> New Patient
            </button>
        </div>
        <div id="hm-patients-search" class="hm-search-box">
            <input type="text" id="hm-patient-search-input" placeholder="Search patients by name, email, or patient number..." class="hm-input" />
        </div>
        <div id="hm-patients-list"></div>
        <div class="hm-placeholder" style="padding:3rem;text-align:center;color:#94a3b8;">
            <p>Patient management module — coming soon</p>
            <p style="font-size:0.875rem;margin-top:0.5rem;">Search, view, and manage patient records</p>
        </div>
    </div>
    <?php
}

// TODO: Implement patient profile management
// TODO: Implement document tracking
// TODO: Implement consent workflow
