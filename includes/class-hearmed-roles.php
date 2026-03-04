<?php

// ============================================================
// AUTO-CONVERTED TO POSTGRESQL
// ============================================================
// All database operations converted from WordPress to PostgreSQL
// - $wpdb → HearMed_DB
// - wp_posts/wp_postmeta → PostgreSQL tables
// - Column names updated (_ID → id, etc.)
// 
// REVIEW REQUIRED:
// - Check all queries use correct table names
// - Verify all AJAX handlers work
// - Test all CRUD operations
// ============================================================

/**
 * HearMed Role Helpers
 * Permission checks used across all modules.
 */
if (!defined("ABSPATH")) exit;

class HearMed_Roles {

    const C_LEVEL   = "c_level";
    const ADMIN     = "administrator";
    const FINANCE   = "finance";
    const DISPENSER = "dispenser";
    const CA        = "clinical_assistant";
    const RECEPTION = "reception";
    const SCHEME    = "scheme_other";

    /**
     * Ensure all HearMed WP roles exist.
     * Safe to call on every init — only adds roles that are missing.
     */
    public static function register_wp_roles(): void {
        $roles = [
            'hm_clevel'    => 'HearMed C-Level',
            'hm_admin'     => 'HearMed Admin',
            'hm_finance'   => 'HearMed Finance',
            'hm_dispenser' => 'HearMed Dispenser',
            'hm_reception' => 'HearMed Reception',
            'hm_ca'        => 'HearMed Clinical Assistant',
            'hm_scheme'    => 'HearMed Scheme/Other',
        ];

        foreach ( $roles as $slug => $label ) {
            if ( ! get_role( $slug ) ) {
                // All roles get read capability; sidebar visibility is controlled
                // by the WP role slug, not by WP capabilities.
                add_role( $slug, $label, [ 'read' => true ] );
            }
        }
    }

    /**
     * Check if current user has one of the given roles.
     * Usage: HearMed_Roles::can(["c_level", "finance"])
     */
    public static function can(array $roles): bool {
        // Use PortalAuth as source of truth
        if ( ! PortalAuth::is_logged_in() ) return false;
        $portal_role = PortalAuth::current_role();
        if ( ! $portal_role ) return false;
        // Map PortalAuth role constants to the role labels used here
        return in_array( $portal_role, $roles, true );
    }

    /** Admin-level access (C-Level, Admin, Finance) */
    public static function is_admin_level(): bool {
        return self::can([self::C_LEVEL, self::ADMIN, self::FINANCE]);
    }

    /** Can view reports */
    public static function can_view_reports(): bool {
        return self::can([self::C_LEVEL, self::ADMIN, self::FINANCE, self::DISPENSER, self::CA, self::RECEPTION]);
    }

    /** Can approve orders */
    public static function can_approve(): bool {
        return self::can([self::C_LEVEL, self::FINANCE]);
    }

    /** Get current user's dispenser record */
    public static function get_dispenser_id(): ?int {
        $staff_id = PortalAuth::staff_id();
        return $staff_id ?: null;
    }

    /** Get current user's primary clinic */
    public static function get_primary_clinic(): ?int {
        $staff_id = self::get_dispenser_id();
        if (!$staff_id) return null;

        return (int) HearMed_DB::get_var(
            "SELECT clinic_id FROM hearmed_reference.staff_clinics WHERE staff_id = $1 AND is_primary_clinic = true LIMIT 1",
            [$staff_id]
        ) ?: null;
    }
}
