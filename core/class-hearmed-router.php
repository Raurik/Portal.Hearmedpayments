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
        'administrator' => [ '*' ], // All capabilities
        'hm_clevel' => [ '*' ], // All capabilities
        'hm_admin' => [ '*' ], // All capabilities
        'hm_finance' => [ 
            'view_accounting', 'edit_accounting', 'view_reports', 
            'view_commissions', 'view_kpi', 'view_all_clinics' 
        ],
        'hm_dispenser' => [ 
            'view_calendar', 'edit_calendar', 'view_patients', 'edit_patients',
            'view_orders', 'edit_orders', 'view_repairs', 'edit_repairs',
            'view_own_stats' 
        ],
        'hm_reception' => [ 
            'view_calendar', 'edit_calendar', 'view_patients', 
            'view_orders' 
        ],
        'hm_ca' => [ 
            'view_calendar', 'view_patients', 'view_orders' 
        ],
        'hm_scheme' => [ 
            'view_calendar', 'view_patients', 'view_orders' 
        ],
    ];
    
    /**
     * Check if current user has a capability
     * 
     * @param string $capability Capability to check
     * @param int|null $user_id User ID (null for current user)
     * @return bool True if user has capability
     */
    public function can( $capability, $user_id = null ) {
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
        
        // Check each role
        foreach ( $user->roles as $role ) {
            if ( ! isset( $this->role_caps[ $role ] ) ) {
                continue;
            }
            
            $caps = $this->role_caps[ $role ];
            
            // Wildcard means all capabilities
            if ( in_array( '*', $caps, true ) ) {
                return true;
            }
            
            // Check specific capability
            if ( in_array( $capability, $caps, true ) ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if user is admin (C-Level, Admin, Finance, or WP Administrator)
     * 
     * @param int|null $user_id User ID (null for current user)
     * @return bool True if admin
     */
    public function is_admin( $user_id = null ) {
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
        
        // Admins see all clinics
        if ( $this->is_admin( $user_id ) ) {
            return $this->get_all_clinic_ids();
        }
        
        // Get user's dispenser profile
        $dispenser_id = $this->get_user_dispenser_id( $user_id );
        
        if ( ! $dispenser_id ) {
            return [];
        }
        
        // Get clinics from dispenser meta
        // TODO: USE PostgreSQL: Get from table columns
        $clinic_ids = get_post_meta( $dispenser_id, 'clinic_ids', true );
        
        if ( ! $clinic_ids || ! is_array( $clinic_ids ) ) {
            // Fallback: single clinic_id
            // TODO: USE PostgreSQL: Get from table columns
            $clinic_id = get_post_meta( $dispenser_id, 'clinic_id', true );
            return $clinic_id ? [ intval( $clinic_id ) ] : [];
        }
        
        return array_map( 'intval', $clinic_ids );
    }
    
    /**
     * Get user's dispenser profile ID
     * 
     * @param int|null $user_id User ID (null for current user)
     * @return int|false Dispenser ID or false
     */
    public function get_user_dispenser_id( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        
        $user = get_user_by( 'id', $user_id );
        
        if ( ! $user ) {
            return false;
        }
        
        // Try matching by display name, first name, or user login
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
            
            // TODO: USE PostgreSQL: HearMed_DB::get_results()
            $dispensers = get_posts([
                'post_type' => 'dispenser',
                'posts_per_page' => 1,
                'post_status' => 'publish',
                'meta_query' => [
                    [
                        'key' => 'user_account',
                        'value' => $value,
                        'compare' => '=',
                    ],
                ],
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
     * @return array Clinic IDs
     */
    private function get_all_clinic_ids() {
        // TODO: USE PostgreSQL: HearMed_DB::get_results()
        $clinics = get_posts([
            'post_type' => 'clinic',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'fields' => 'ids',
        ]);
        
        return array_map( 'intval', $clinics );
    }
    
    /**
     * Check if user can access a specific clinic
     * 
     * @param int $clinic_id Clinic ID
     * @param int|null $user_id User ID (null for current user)
     * @return bool True if can access
     */
    public function can_access_clinic( $clinic_id, $user_id = null ) {
        $user_clinics = $this->get_user_clinics( $user_id );
        
        return in_array( intval( $clinic_id ), $user_clinics, true );
    }
    
    /**
     * Get SQL WHERE clause for clinic filtering
     * 
     * @param string $column Column name for clinic_id
     * @param int|null $user_id User ID (null for current user)
     * @return string SQL WHERE clause (without WHERE keyword)
     */
    public function get_clinic_sql_filter( $column = 'clinic_id', $user_id = null ) {
        // PostgreSQL only - no $wpdb needed
        
        $clinic_ids = $this->get_user_clinics( $user_id );
        
        if ( empty( $clinic_ids ) ) {
            return '1=0'; // No access
        }
        
        // Admin sees all
        if ( $this->is_admin( $user_id ) ) {
            return '1=1';
        }
        
        $ids = implode( ',', array_map( 'intval', $clinic_ids ) );
        
        return "{$column} IN ({$ids})";
    }
}
