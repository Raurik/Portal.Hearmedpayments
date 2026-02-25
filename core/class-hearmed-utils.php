<?php
/**
 * HearMed Utilities
 * 
 * Provides formatting and helper functions.
 * 
 * @package HearMed_Portal
 * @since 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HearMed_Utils {

    /**
     * Cache of module → page URL lookups.
     * @var array
     */
    private static $page_url_cache = [];

    /**
     * Get the front-end page URL for a given portal module.
     *
     * Searches for a published WP page whose content contains the
     * corresponding [hearmed_*] shortcode.  Results are cached per-request.
     *
     * Usage: HearMed_Utils::page_url('orders')   → https://…/orders/
     *        HearMed_Utils::page_url('accounting') → https://…/accounting/
     *
     * @param string $module Module slug (orders, accounting, patients, calendar, etc.)
     * @return string Page URL (with trailing slash). Falls back to home_url('/module/').
     */
    public static function page_url( $module ) {
        $module = sanitize_key( $module );

        if ( isset( self::$page_url_cache[ $module ] ) ) {
            return self::$page_url_cache[ $module ];
        }

        // Map module slug → shortcode tag(s) to search for
        $shortcode_map = [
            'orders'     => 'hearmed_orders',
            'accounting' => 'hearmed_accounting',
            'patients'   => 'hearmed_patients',
            'calendar'   => 'hearmed_calendar',
            'repairs'    => 'hearmed_repairs',
            'kpi'        => 'hearmed_kpi',
            'reports'    => 'hearmed_reporting',
            'team-chat'  => 'hearmed_team_chat',
        ];

        $shortcode = $shortcode_map[ $module ] ?? 'hearmed_' . $module;

        global $wpdb;

        $page_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_type = 'page'
                   AND post_status = 'publish'
                   AND post_content LIKE %s
                 ORDER BY ID ASC
                 LIMIT 1",
                '%[' . $shortcode . '%'
            )
        );

        if ( $page_id ) {
            $url = get_permalink( $page_id );
        } else {
            // Fallback: assume page slug matches the module name
            $url = home_url( '/' . $module . '/' );
        }

        self::$page_url_cache[ $module ] = $url;

        return $url;
    }

    /**
     * Format money amount
     * 
     * @param float $amount Amount
     * @param string $currency Currency symbol (default: €)
     * @return string Formatted money
     */
    public static function money( $amount, $currency = '€' ) {
        return $currency . number_format( (float) $amount, 2 );
    }
    
    /**
     * Get clinic name by ID
     * 
     * @param int $clinic_id Clinic ID
     * @return string Clinic name
     */
    public static function clinic_name( $clinic_id ) {
        if ( ! $clinic_id ) {
            return 'Unknown Clinic';
        }
        
        $clinic = get_post( $clinic_id );
        
        return $clinic ? $clinic->post_title : 'Unknown Clinic';
    }
    
    /**
     * Get dispenser name by ID
     * 
     * @param int $dispenser_id Dispenser ID
     * @return string Dispenser name
     */
    public static function dispenser_name( $dispenser_id ) {
        if ( ! $dispenser_id ) {
            return 'Unknown Dispenser';
        }
        
        $dispenser = get_post( $dispenser_id );
        
        return $dispenser ? $dispenser->post_title : 'Unknown Dispenser';
    }
    
    /**
     * Get patient C-number
     * 
     * @param int $patient_id Patient ID
     * @return string C-number
     */
    public static function patient_c_number( $patient_id ) {
        if ( ! $patient_id ) {
            return 'C-000';
        }
        
        // OPTION 1: WordPress post meta (CURRENT - WORKING)
        $c_number = get_post_meta( $patient_id, 'patient_number', true );
        
        // OPTION 2: PostgreSQL (UNCOMMENT WHEN READY)
        // global $wpdb;
        // $table = HearMed_Core::table( 'patients' );
        // $c_number = $wpdb->get_var( 
        //     $wpdb->prepare( 
        //         "SELECT patient_number FROM {$table} WHERE id = %d", 
        //         $patient_id 
        //     ) 
        // );
        
        return $c_number ?: 'C-' . str_pad( $patient_id, 3, '0', STR_PAD_LEFT );
    }
    
    /**
     * Format date for display
     * 
     * @param string $date Date string
     * @param string $format Date format (default: d/m/Y)
     * @return string Formatted date
     */
    public static function format_date( $date, $format = 'd/m/Y' ) {
        if ( ! $date ) {
            return '—';
        }
        
        $timestamp = strtotime( $date );
        
        return $timestamp ? date( $format, $timestamp ) : '—';
    }
    
    /**
     * Format datetime for display
     * 
     * @param string $datetime Datetime string
     * @param string $format Datetime format (default: d/m/Y H:i)
     * @return string Formatted datetime
     */
    public static function format_datetime( $datetime, $format = 'd/m/Y H:i' ) {
        if ( ! $datetime ) {
            return '—';
        }
        
        $timestamp = strtotime( $datetime );
        
        return $timestamp ? date( $format, $timestamp ) : '—';
    }
    
    /**
     * Get human-readable time difference
     * 
     * @param string $datetime Datetime string
     * @return string Time ago string
     */
    public static function time_ago( $datetime ) {
        if ( ! $datetime ) {
            return '—';
        }
        
        $timestamp = strtotime( $datetime );
        
        if ( ! $timestamp ) {
            return '—';
        }
        
        $diff = time() - $timestamp;
        
        if ( $diff < 60 ) {
            return 'Just now';
        }
        
        if ( $diff < 3600 ) {
            $mins = floor( $diff / 60 );
            return $mins . ' min' . ( $mins > 1 ? 's' : '' ) . ' ago';
        }
        
        if ( $diff < 86400 ) {
            $hours = floor( $diff / 3600 );
            return $hours . ' hour' . ( $hours > 1 ? 's' : '' ) . ' ago';
        }
        
        if ( $diff < 604800 ) {
            $days = floor( $diff / 86400 );
            return $days . ' day' . ( $days > 1 ? 's' : '' ) . ' ago';
        }
        
        return date( 'd/m/Y', $timestamp );
    }
    
    /**
     * Generate a unique order number
     * 
     * @param string $prefix Prefix (default: ORD)
     * @return string Order number
     */
    public static function generate_order_number( $prefix = 'ORD' ) {
        return $prefix . '-' . date( 'Ymd' ) . '-' . strtoupper( substr( uniqid(), -6 ) );
    }
    
    /**
     * Generate a unique invoice number
     * 
     * @param string $prefix Prefix (default: INV)
     * @return string Invoice number
     */
    public static function generate_invoice_number( $prefix = 'INV' ) {
        return $prefix . '-' . date( 'Ymd' ) . '-' . strtoupper( substr( uniqid(), -6 ) );
    }
    
    /**
     * Sanitize phone number
     * 
     * @param string $phone Phone number
     * @return string Sanitized phone
     */
    public static function sanitize_phone( $phone ) {
        return preg_replace( '/[^0-9+]/', '', $phone );
    }
    
    /**
     * Format phone number for display
     * 
     * @param string $phone Phone number
     * @return string Formatted phone
     */
    public static function format_phone( $phone ) {
        $phone = self::sanitize_phone( $phone );
        
        if ( empty( $phone ) ) {
            return '—';
        }
        
        // Irish format: +353 XX XXX XXXX
        if ( strpos( $phone, '+353' ) === 0 ) {
            return '+353 ' . substr( $phone, 4, 2 ) . ' ' . 
                   substr( $phone, 6, 3 ) . ' ' . substr( $phone, 9 );
        }
        
        return $phone;
    }
    
    /**
     * Get user initials
     * 
     * @param int|null $user_id User ID (null for current user)
     * @return string Initials
     */
    public static function user_initials( $user_id = null ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        
        $user = get_user_by( 'id', $user_id );
        
        if ( ! $user ) {
            return '?';
        }
        
        $name = $user->display_name;
        $parts = explode( ' ', $name );
        
        if ( count( $parts ) >= 2 ) {
            return strtoupper( substr( $parts[0], 0, 1 ) . substr( $parts[1], 0, 1 ) );
        }
        
        return strtoupper( substr( $name, 0, 2 ) );
    }
    
    /**
     * Get VAT rate for category
     * 
     * @param string $category Product category
     * @return float VAT rate (0, 0.135, 0.23)
     */
    public static function get_vat_rate( $category ) {
        $vat_rates = [
            'hearing_aids' => 0,      // Exempt
            'accessories' => 0.23,    // Standard
            'wax_removal' => 0.135,   // Reduced
            'batteries' => 0.23,      // Standard
            'hearing_tests' => 0,     // Exempt
        ];
        
        return $vat_rates[ $category ] ?? 0.23;
    }
    
    /**
     * Calculate VAT amount
     * 
     * @param float $amount Net amount
     * @param float $rate VAT rate
     * @return float VAT amount
     */
    public static function calculate_vat( $amount, $rate ) {
        return round( $amount * $rate, 2 );
    }

    /**
     * Detect whether the current request is inside the Elementor editor or preview.
     *
     * @return bool
     */
    public static function is_elementor_editor() {
        // Preview iframe: ?elementor-preview=POST_ID
        if ( null !== filter_input( INPUT_GET, 'elementor-preview' ) ) {
            return true;
        }
        // Editor mode when Elementor plugin is active
        if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
            return true;
        }
        return false;
    }

    /**
     * Decide whether HearMed module JS/CSS should boot inside Elementor preview/editor.
     *
     * Default is false to avoid conflicts with Elementor iframe editing.
     * Enable deliberately via filter for controlled staging previews:
     * add_filter( 'hm_allow_elementor_preview_boot', '__return_true' );
     *
     * @return bool
     */
    public static function allow_elementor_preview_boot() {
        return (bool) apply_filters( 'hm_allow_elementor_preview_boot', false );
    }
}