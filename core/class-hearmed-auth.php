<?php
/**
 * HearMed Authentication & Authorization
 *
 * Handles user roles, permissions, and clinic scoping.
 *
 * @package HearMed_Portal
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HearMed_Auth {

    /**
     * Portal role capabilities map
     */
    private $role_caps = [
        'administrator' => [ '*' ],
        'hm_clevel'     => [ '*' ],
        'hm_admin'      => [ '*' ],
        'hm_finance'    => [
            'view_accounting', 'edit_accounting', 'view_reports',
            'view_commissions', 'view_kpi', 'view_all_clinics',
        ],
        'hm_dispenser'  => [
            'view_calendar', 'edit_calendar', 'view_patients', 'edit_patients',
            'view_orders', 'edit_orders', 'view_repairs', 'edit_repairs',
            'view_own_stats',
        ],
        'hm_reception'  => [
            'view_calendar', 'edit_calendar', 'view_patients',
            'view_orders',
        ],
        'hm_ca'         => [ 'view_calendar', 'view_patients', 'view_orders' ],
        'hm_scheme'     => [ 'view_calendar', 'view_patients', 'view_orders' ],
    ];

    // =========================================================================
    // STATIC HELPERS — used by all modules
    // =========================================================================

    /**
     * Is a WordPress user currently logged in?
     */
    public static function is_logged_in() {
        return is_user_logged_in();
    }

    /**
     * Get current WordPress user object, or null if not logged in
     */
    public static function current_user() {
        $user = wp_get_current_user();
        return ( $user && $user->ID ) ? $user : null;
    }

    /**
     * Static capability check — wraps the instance can() method
     */
    public static function can( $capability, $user_id = null ) {
        static $instance = null;
        if ( $instance === null ) {
            $instance = new self();
        }
        return $instance->can_instance( $capability, $user_id );
    }

    /**
     * Static admin check — wraps the instance is_admin() method
     */
    public static function is_admin( $user_id = null ) {
        static $instance = null;
        if ( $instance === null ) {
            $instance = new self();
        }
        return $instance->is_admin_instance( $user_id );
    }

    // =========================================================================
    // INSTANCE METHODS
    // =========================================================================

    /**
     * Check if current user has a capability (instance version)
     *
     * @param string   $capability Capability to check
     * @param int|null $user_id    User ID (null for current user)
     * @return bool
     */
    public function can_instance( $capability, $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! $user_id ) {
            return false;
        }

        $user = get_user_by( 'id', $user_id );

        if ( ! $user ) {
            return false;
        }

        foreach ( $user->roles as $role ) {
            if ( ! isset( $this->role_caps[ $role ] ) ) {
                continue;
            }

            $caps = $this->role_caps[ $role ];

            if ( in_array( '*', $caps, true ) ) {
                return true;
            }

            if ( in_array( $capability, $caps, true ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user is admin level (instance version)
     *
     * @param int|null $user_id User ID (null for current user)
     * @return bool
     */
    public function is_admin_instance( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( ! $user_id ) {
            return false;
        }

        $user = get_user_by( 'id', $user_id );

        if ( ! $user ) {
            return false;
        }

        $admin_roles = [ 'administrator', 'hm_clevel', 'hm_admin', 'hm_finance' ];

        return ! empty( array_intersect( $admin_roles, (array) $user->roles ) );
    }

    /**
     * Get user's assigned clinic IDs
     *
     * @param int|null $user_id User ID (null for current user)
     * @return array Clinic IDs
     */
    public function get_user_clinics( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        if ( $this->is_admin_instance( $user_id ) ) {
            return $this->get_all_clinic_ids();
        }

        $dispenser_id = $this->get_user_dispenser_id( $user_id );

        if ( ! $dispenser_id ) {
            return [];
        }

        $clinic_ids = get_post_meta( $dispenser_id, 'clinic_ids', true );

        if ( ! $clinic_ids || ! is_array( $clinic_ids ) ) {
            $clinic_id = get_post_meta( $dispenser_id, 'clinic_id', true );
            return $clinic_id ? [ intval( $clinic_id ) ] : [];
        }

        return array_map( 'intval', $clinic_ids );
    }

    /**
     * Get user's dispenser profile ID
     *
     * @param int|null $user_id User ID (null for current user)
     * @return int|false
     */
    public function get_user_dispenser_id( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }

        $user = get_user_by( 'id', $user_id );

        if ( ! $user ) {
            return false;
        }

        $search_values = [
            $user->display_name,
            $user->first_name . ' ' . $user->last_name,
            $user->user_login,
            $user_id,
        ];

        foreach ( $search_values as $value ) {
            if ( ! $value ) {
                continue;
            }

            $dispensers = get_posts([
                'post_type'      => 'dispenser',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'meta_query'     => [[
                    'key'     => 'user_account',
                    'value'   => $value,
                    'compare' => '=',
                ]],
            ]);

            if ( $dispensers ) {
                return $dispensers[0]->ID;
            }
        }

        return false;
    }

    /**
     * Get all clinic IDs
     *
     * @return array
     */
    private function get_all_clinic_ids() {
        $clinics = get_posts([
            'post_type'      => 'clinic',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'fields'         => 'ids',
        ]);

        return array_map( 'intval', $clinics );
    }

    /**
     * Check if user can access a specific clinic
     *
     * @param int      $clinic_id Clinic ID
     * @param int|null $user_id   User ID (null for current user)
     * @return bool
     */
    public function can_access_clinic( $clinic_id, $user_id = null ) {
        return in_array( intval( $clinic_id ), $this->get_user_clinics( $user_id ), true );
    }

    // =========================================================================
    // CONVENIENCE STATICS — current_role / current_clinic
    // =========================================================================

    /**
     * Map from WordPress role slug to portal role name used in modules.
     */
    private static $role_map = [
        'administrator' => 'admin',
        'hm_clevel'     => 'c_level',
        'hm_admin'      => 'admin',
        'hm_finance'    => 'finance',
        'hm_dispenser'  => 'dispenser',
        'hm_reception'  => 'reception',
        'hm_ca'         => 'ca',
        'hm_scheme'     => 'scheme',
    ];

    /**
     * Get the current user's portal role name.
     *
     * Returns a simplified string such as 'c_level', 'admin', 'dispenser', etc.
     * Returns null when no user is logged in or role is unrecognised.
     *
     * @return string|null
     */
    public static function current_role() {
        $user = wp_get_current_user();

        if ( ! $user || ! $user->ID ) {
            return null;
        }

        foreach ( self::$role_map as $wp_role => $portal_role ) {
            if ( in_array( $wp_role, (array) $user->roles, true ) ) {
                return $portal_role;
            }
        }

        return null;
    }

    /**
     * Get the current user's primary clinic ID.
     *
     * Admins / C-Level / Finance see all clinics so this returns null (no filter).
     * Clinic-scoped roles return the first clinic they are assigned to.
     *
     * @return int|null  Clinic ID, or null when user should see all clinics.
     */
    public static function current_clinic() {
        $user = wp_get_current_user();

        if ( ! $user || ! $user->ID ) {
            return null;
        }

        // Roles that should see everything — no clinic filter
        $global_roles = [ 'administrator', 'hm_clevel', 'hm_admin', 'hm_finance' ];
        if ( ! empty( array_intersect( $global_roles, (array) $user->roles ) ) ) {
            return null;
        }

        $instance   = new self();
        $clinic_ids = $instance->get_user_clinics( $user->ID );

        return ! empty( $clinic_ids ) ? $clinic_ids[0] : null;
    }

    /**
     * Get SQL WHERE clause for clinic filtering
     *
     * @param string   $column  Column name for clinic_id
     * @param int|null $user_id User ID (null for current user)
     * @return string SQL WHERE clause (without WHERE keyword)
     */
    public function get_clinic_sql_filter( $column = 'clinic_id', $user_id = null ) {
        $clinic_ids = $this->get_user_clinics( $user_id );

        if ( empty( $clinic_ids ) ) {
            return '1=0';
        }

        if ( $this->is_admin_instance( $user_id ) ) {
            return '1=1';
        }

        $ids = implode( ',', array_map( 'intval', $clinic_ids ) );

        return "{$column} IN ({$ids})";
    }
}