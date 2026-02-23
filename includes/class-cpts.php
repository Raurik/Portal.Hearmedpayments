<?php
/**
 * HearMed Custom Post Types
 * 
 * ============================================================
 * DISABLED - ALL DATA NOW IN POSTGRESQL!
 * ============================================================
 * 
 * Custom Post Types are NO LONGER USED.
 * All business data is now in PostgreSQL tables:
 * - Patients → hearmed_reference.patients
 * - Clinics → hearmed_reference.clinics
 * - Services → hearmed_reference.services
 * - Products → hearmed_reference.products
 * - Staff → hearmed_reference.staff
 * 
 * WordPress is now UI + Auth ONLY!
 * ============================================================
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// CPT registration DISABLED
// All register_post_type() calls removed
// Data now in PostgreSQL tables

// This file kept for compatibility but does nothing

/**
 * HearMed Portal — Custom Post Types & Taxonomies
 *
 * Registers all CPTs and taxonomies for the portal.
 * Auto-loaded by the includes/ glob in hearmed-calendar.php.
 *
 * ----------------------------------------------------------------
 * CPTs:        clinic, patient, service, dispenser, ha-product, audiometer
 * Taxonomies:  manufacturer, hearmed-range, referral-source, ha-style
 * ----------------------------------------------------------------
 * DO NOT add business logic here — CPT registration only.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_CPTs {

    public static function register() {

        register_post_type( 'clinic', [
            'labels'      => self::labels( 'Clinic', 'Clinics' ),
            'public'      => false, 'show_ui' => true, 'show_in_menu' => true,
            'menu_icon'   => 'dashicons-building',
            'supports'    => [ 'title' ], 'has_archive' => false,
        ] );
        self::meta( 'clinic', [
            'address', 'clinic_email', 'clinic_phone', 'eircode',
            'clinic_colour', 'text_colour', 'days_available', 'is_active',
        ] );

        register_post_type( 'patient', [
            'labels'      => self::labels( 'Patient', 'Patients' ),
            'public'      => false, 'show_ui' => true, 'show_in_menu' => true,
            'menu_icon'   => 'dashicons-id',
            'supports'    => [ 'title' ], 'has_archive' => false,
        ] );
        self::meta( 'patient', [
            'patient_number', 'patient_title', 'first_name', 'last_name', 'dob',
            'patient_phone', 'patient_email', 'patient_address', 'patient_eircode',
            'marketing_email', 'marketing_phone', 'marketing_sms',
            'is_active', 'virtual_servicing', 'prsi_eligible', 'prsi_number',
            'referral_source', 'referral_sub_source',
            'gdpr_consent', 'gdpr_consent_date', 'gdpr_consent_version',
            'assigned_dispenser_id', 'assigned_clinic_id',
            'annual_review_date', 'marketing_consent',
        ] );

        register_post_type( 'service', [
            'labels'      => self::labels( 'Service', 'Services' ),
            'public'      => false, 'show_ui' => true, 'show_in_menu' => true,
            'menu_icon'   => 'dashicons-calendar-alt',
            'supports'    => [ 'title' ], 'has_archive' => false,
        ] );
        self::meta( 'service', [
            'service_colour', 'colour', 'duration', 'appointment_category',
            'sales_opportunity', 'income_bearing', 'send_reminders', 'send_confirmation',
            'reminders', 'confirmation',
        ] );

        register_post_type( 'dispenser', [
            'labels'      => self::labels( 'Dispenser', 'Dispensers' ),
            'public'      => false, 'show_ui' => true, 'show_in_menu' => true,
            'menu_icon'   => 'dashicons-businessperson',
            'supports'    => [ 'title' ], 'has_archive' => false,
        ] );
        self::meta( 'dispenser', [
            'user_account', 'initials', 'clinic_id', 'clinic_ids', 'is_active',
            'calendar_order', 'role_type', 'primary_clinic',
            'schedule', 'allowed_appointment_types',
        ] );

        register_post_type( 'ha-product', [
            'labels'      => self::labels( 'Product', 'Products' ),
            'public'      => false, 'show_ui' => true, 'show_in_menu' => true,
            'menu_icon'   => 'dashicons-products',
            'supports'    => [ 'title' ], 'has_archive' => false,
        ] );
        self::meta( 'ha-product', [
            'item_code', 'manufacturer', 'model', 'style', 'tech_level',
            'hearmed_range', 'hearmed_range_id', 'cost_price', 'retail_price',
            'vat_rate', 'product_category', 'receivers', 'gain_options',
            'earbud_size', 'earbud_type', 'power', 'product_image', 'style_icon',
        ] );

        register_post_type( 'audiometer', [
            'labels'      => self::labels( 'Audiometer', 'Audiometers' ),
            'public'      => false, 'show_ui' => true, 'show_in_menu' => true,
            'menu_icon'   => 'dashicons-megaphone',
            'supports'    => [ 'title' ], 'has_archive' => false,
        ] );
        self::meta( 'audiometer', [
            'audiometer_make', 'audiometer_model', 'serial_number',
            'calibration_date', 'clinic_id', 'is_active',
        ] );

        register_taxonomy( 'manufacturer', 'ha-product', [
            'labels'       => [ 'name' => 'Manufacturers', 'singular_name' => 'Manufacturer' ],
            'public'       => false, 'show_ui' => true, 'hierarchical' => false, 'show_in_rest' => true,
        ] );
        register_taxonomy( 'hearmed-range', 'ha-product', [
            'labels'       => [ 'name' => 'HearMed Ranges', 'singular_name' => 'Range' ],
            'public'       => false, 'show_ui' => true, 'hierarchical' => false, 'show_in_rest' => true,
        ] );
        register_taxonomy( 'referral-source', 'patient', [
            'labels'       => [ 'name' => 'Referral Sources', 'singular_name' => 'Referral Source' ],
            'public'       => false, 'show_ui' => true, 'hierarchical' => true, 'show_in_rest' => true,
        ] );
        register_taxonomy( 'ha-style', 'ha-product', [
            'labels'       => [ 'name' => 'Styles', 'singular_name' => 'Style' ],
            'public'       => false, 'show_ui' => true, 'hierarchical' => false, 'show_in_rest' => true,
        ] );
    }

    private static function labels( $s, $p ) {
        return [
            'name'          => $p, 'singular_name' => $s,
            'add_new'       => "Add New $s", 'add_new_item' => "Add New $s",
            'edit_item'     => "Edit $s",    'new_item'     => "New $s",
            'view_item'     => "View $s",    'search_items' => "Search $p",
            'not_found'     => "No $p found",
        ];
    }

    private static function meta( $post_type, $keys ) {
        foreach ( $keys as $key ) {
            register_post_meta( $post_type, $key, [
                'show_in_rest' => true, 'single' => true, 'type' => 'string',
            ] );
        }
    }
}
