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
 * HearMed Portal — Calendar Module
 *
 * Contains all calendar AJAX handlers as standalone functions.
 * Auto-loaded by the modules/ glob in hearmed-calendar.php.
 *
 * ----------------------------------------------------------------
 * All 19 wp_ajax_hm_* actions are self-registered at the bottom.
 * Uses HearMed_Portal::table()   instead of $this->cct()
 * Uses HearMed_Portal::setting() instead of $this->stg()
 * ----------------------------------------------------------------
 * DO NOT add shortcode logic here — AJAX handlers only.
 * DO NOT rename action strings — JS depends on exact names.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// ================================================================
// AJAX ACTION REGISTRATIONS — all 19 calendar actions
// ================================================================
add_action( 'wp_ajax_hm_get_settings',         'hm_ajax_get_settings' );
add_action( 'wp_ajax_hm_save_settings',        'hm_ajax_save_settings' );
add_action( 'wp_ajax_hm_get_clinics',          'hm_ajax_get_clinics' );
add_action( 'wp_ajax_hm_get_dispensers',       'hm_ajax_get_dispensers' );
add_action( 'wp_ajax_hm_get_clinic_coverage',  'hm_ajax_get_clinic_coverage' );
add_action( 'wp_ajax_hm_get_services',         'hm_ajax_get_services' );
add_action( 'wp_ajax_hm_search_patients',      'hm_ajax_search_patients' );
add_action( 'wp_ajax_hm_get_appointments',     'hm_ajax_get_appointments' );
add_action( 'wp_ajax_hm_create_appointment',   'hm_ajax_create_appointment' );
add_action( 'wp_ajax_hm_update_appointment',   'hm_ajax_update_appointment' );
add_action( 'wp_ajax_hm_delete_appointment',   'hm_ajax_delete_appointment' );
add_action( 'wp_ajax_hm_get_holidays',         'hm_ajax_get_holidays' );
add_action( 'wp_ajax_hm_save_holiday',         'hm_ajax_save_holiday' );
add_action( 'wp_ajax_hm_delete_holiday',       'hm_ajax_delete_holiday' );
add_action( 'wp_ajax_hm_get_blockouts',        'hm_ajax_get_blockouts' );
add_action( 'wp_ajax_hm_save_blockout',        'hm_ajax_save_blockout' );
add_action( 'wp_ajax_hm_delete_blockout',      'hm_ajax_delete_blockout' );
add_action( 'wp_ajax_hm_save_service',         'hm_ajax_save_service' );
add_action( 'wp_ajax_hm_delete_service',       'hm_ajax_delete_service' );
add_action( 'wp_ajax_hm_save_dispenser_order', 'hm_ajax_save_dispenser_order' );
add_action( 'wp_ajax_hm_get_exclusion_types',  'hm_ajax_get_exclusion_types' );
add_action( 'wp_ajax_hm_create_patient_calendar', 'hm_ajax_create_patient_from_calendar' );
add_action( 'wp_ajax_hm_get_outcome_templates', 'hm_ajax_get_outcome_templates' );
add_action( 'wp_ajax_hm_save_appointment_outcome', 'hm_ajax_save_appointment_outcome' );
add_action( 'wp_ajax_hm_create_outcome_order',  'hm_ajax_create_outcome_order' );
add_action( 'wp_ajax_hm_get_order_products',    'hm_ajax_get_order_products' );
add_action( 'wp_ajax_hm_record_order_payment',  'hm_ajax_record_order_payment' );
add_action( 'wp_ajax_hm_save_order_serials_from_payment',  'hm_ajax_save_order_serials_from_payment' );
add_action( 'wp_ajax_hm_get_patient_pipeline_orders',    'hm_ajax_get_patient_pipeline_orders' );
add_action( 'wp_ajax_hm_create_prsi_form_reminder',      'hm_ajax_create_prsi_form_reminder' );
add_action( 'wp_ajax_hm_update_appointment_status', 'hm_ajax_update_appointment_status' );
add_action( 'wp_ajax_hm_save_exclusion',           'hm_ajax_save_exclusion' );
add_action( 'wp_ajax_hm_delete_exclusion',         'hm_ajax_delete_exclusion' );
add_action( 'wp_ajax_hm_get_exclusions',           'hm_ajax_get_exclusions' );
add_action( 'wp_ajax_hm_purge_appointment',        'hm_ajax_purge_appointment' );

// ================================================================
// CALENDAR SETTINGS
// ================================================================
function hm_ajax_get_settings() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    try {
        $t = HearMed_Portal::table( 'calendar_settings' );
        // Check table exists
        $exists = HearMed_DB::get_var( "SELECT to_regclass('hearmed_core.calendar_settings')" );
        if ( ! $exists ) { wp_send_json_success( [] ); return; }
        $row = HearMed_DB::get_row( "SELECT * FROM {$t} LIMIT 1" );
        if ( $row ) {
            $arr = (array) $row;
            // Decode jsonb columns — PostgreSQL returns them with JSON encoding (quoted strings)
            foreach ( ['working_days', 'enabled_days', 'calendar_order', 'appointment_statuses'] as $jf ) {
                if ( isset( $arr[ $jf ] ) && is_string( $arr[ $jf ] ) ) {
                    $decoded = json_decode( $arr[ $jf ], true );
                    if ( $decoded !== null ) {
                        $arr[ $jf ] = is_array( $decoded ) ? implode( ',', $decoded ) : $decoded;
                    }
                }
            }
            // Decode status_badge_colours as object (keep as assoc array for JS)
            if ( isset( $arr['status_badge_colours'] ) && is_string( $arr['status_badge_colours'] ) ) {
                $decoded = json_decode( $arr['status_badge_colours'], true );
                if ( is_array( $decoded ) ) {
                    $arr['status_badge_colours'] = $decoded;
                }
            }
            // Decode status_card_styles
            if ( isset( $arr['status_card_styles'] ) && is_string( $arr['status_card_styles'] ) ) {
                $decoded = json_decode( $arr['status_card_styles'], true );
                if ( is_array( $decoded ) ) {
                    $arr['status_card_styles'] = $decoded;
                }
            }
            wp_send_json_success( $arr );
        } else {
            wp_send_json_success( [] );
        }
    } catch ( Throwable $e ) {
        error_log( '[HearMed] get_settings error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

function hm_ajax_save_settings() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! PortalAuth::is_logged_in() ) { 
        wp_send_json_error( 'Denied' ); 
        return; 
    }
    
    try {
        $t      = HearMed_Portal::table( 'calendar_settings' );
        
        // Regular fields
        $text_fields = [
            'start_time', 'end_time', 'slot_height', 'default_view', 'default_mode',
            'outcome_style',
            'appt_bg_color', 'appt_font_color', 'appt_name_color', 'appt_time_color',
            'appt_badge_color', 'appt_badge_font_color', 'appt_meta_color',
            'border_color', 'tint_opacity',
            'card_style', 'banner_style', 'banner_size', 'indicator_color', 'today_highlight_color',
            'grid_line_color', 'cal_bg_color',
            'card_font_family', 'card_font_size', 'card_font_weight',
            'outcome_font_family', 'outcome_font_size', 'outcome_font_weight',
        ];

        // JSONB fields — must be stored as valid JSON strings, not sanitized as text
        $json_fields = [
            'enabled_days', 'calendar_order', 'appointment_statuses', 'working_days',
            'status_badge_colours',
            'status_card_styles',
        ];
        
        // Checkbox fields — JS sends explicit '1' or '0'
        $checkbox_fields = [
            'show_time_inline', 'hide_end_time', 'require_cancel_reason',
            'hide_cancelled', 'require_reschedule_note', 'apply_clinic_colour', 'display_full_name',
            'prevent_location_mismatch', 'double_booking_warning', 'show_patient',
            'show_service', 'show_initials', 'show_status',
            'show_clinic', 'show_time', 'show_dispenser_initials', 'show_status_badge', 'show_appointment_type',
            'show_badges',
        ];
        
        $data = [];
        
        // Map form field name → DB column name where they differ
        if ( isset( $_POST['time_interval'] ) ) {
            $data['time_interval_minutes'] = intval( $_POST['time_interval'] );
        }
        
        foreach ( $text_fields as $f ) {
            if ( isset( $_POST[ $f ] ) ) {
                $data[ $f ] = sanitize_text_field( $_POST[ $f ] );
            }
        }

        // JSONB columns — validate as JSON, pass through as-is
        foreach ( $json_fields as $f ) {
            if ( isset( $_POST[ $f ] ) ) {
                $raw = wp_unslash( $_POST[ $f ] );
                // If it's already valid JSON, use it; otherwise wrap as JSON string
                if ( is_array( $raw ) ) {
                    $data[ $f ] = wp_json_encode( $raw );
                } else {
                    $decoded = json_decode( $raw );
                    $data[ $f ] = ( $decoded !== null || $raw === 'null' ) ? $raw : wp_json_encode( $raw );
                }
            }
        }

        foreach ( $checkbox_fields as $f ) {
            if ( isset( $_POST[ $f ] ) ) {
                $val = $_POST[ $f ];
                $data[ $f ] = ( $val === '1' || $val === 1 || $val === 'yes' || $val === true || $val === 'true' || $val === 't' );
            }
            // If not sent, leave unchanged — avoids overwriting checkboxes
            // that don't exist in the current form.
        }
        
        // Check if record exists
        $ex = HearMed_DB::get_var( "SELECT id FROM {$t} LIMIT 1" );
        
        if ( $ex ) {
            $result = HearMed_DB::update( $t, $data, [ 'id' => $ex ] );
        } else {
            $result = HearMed_DB::insert( $t, $data );
        }
        
        if ( $result === false ) {
            $err = HearMed_DB::last_error();
            error_log( '[HearMed] Calendar settings save error: ' . $err );
            wp_send_json_error( [ 'message' => 'Database error: ' . $err ] );
            return;
        }
        
        wp_send_json_success();
        
    } catch ( Throwable $e ) {
        error_log( '[HearMed] Calendar settings save exception: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => 'Server error during save' ] );
    }
}

// ================================================================
// CLINICS, DISPENSERS, SERVICES
// ================================================================
function hm_ajax_get_clinics() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    try {
    $ps = HearMed_DB::get_results(
        "SELECT id, clinic_name, clinic_color, is_active, opening_hours
         FROM hearmed_reference.clinics
         WHERE is_active = true
         ORDER BY clinic_name"
    );
    $d = [];
    foreach ( $ps as $p ) {
        $extra = [
            'days_available' => '1,2,3,4,5',
            'text_colour'    => '#FFFFFF',
        ];

        if ( ! empty( $p->opening_hours ) ) {
            $decoded = json_decode( $p->opening_hours, true );
            if ( is_array( $decoded ) ) {
                if ( ! empty( $decoded['days_available'] ) ) {
                    $extra['days_available'] = (string) $decoded['days_available'];
                }
                if ( ! empty( $decoded['text_colour'] ) ) {
                    $extra['text_colour'] = (string) $decoded['text_colour'];
                }
            }
        }

        $d[] = [
            'id'             => (int) $p->id,
            'name'           => $p->clinic_name,
            'color'          => $p->clinic_color ?: '#0BB4C4',
            'clinic_colour'  => $p->clinic_color ?: '#0BB4C4',
            'text_colour'    => $extra['text_colour'],
            'is_active'      => (bool) $p->is_active,
        ];
    }
    wp_send_json_success( $d );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] get_clinics error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

function hm_ajax_get_dispensers() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    try {
    $clinic_id = intval( $_POST['clinic'] ?? 0 );
    $date = sanitize_text_field( $_POST['date'] ?? '' );

    // Build a map of staff_id => [scheduled day-of-week numbers] for selected clinic
    $staff_sched_days = [];
    $clinic_has_schedules = false;
    $system_uses_schedules = false; // true if ANY clinic has schedule entries
    if ( $clinic_id ) {
        $schedule_table = 'hearmed_reference.dispenser_schedules';
        $has_schedule_table = HearMed_DB::get_var( HearMed_DB::prepare( "SELECT to_regclass(%s)", $schedule_table ) ) !== null;
        if ( $has_schedule_table ) {
            // Check if the scheduling system is in use at all (any active rows in any clinic)
            $any_sched = HearMed_DB::get_var(
                "SELECT 1 FROM hearmed_reference.dispenser_schedules WHERE is_active = true LIMIT 1"
            );
            $system_uses_schedules = ! empty( $any_sched );

            $sched_rows = HearMed_DB::get_results(
                "SELECT staff_id, day_of_week
                 FROM hearmed_reference.dispenser_schedules
                 WHERE clinic_id = $1
                   AND is_active = true
                   AND (effective_from IS NULL OR effective_from <= CURRENT_DATE)
                   AND (effective_to   IS NULL OR effective_to   >= CURRENT_DATE)",
                [ $clinic_id ]
            );
            if ( $sched_rows && count( $sched_rows ) > 0 ) {
                $clinic_has_schedules = true;
                foreach ( $sched_rows as $sr ) {
                    $sid = (int) $sr->staff_id;
                    if ( ! isset( $staff_sched_days[ $sid ] ) ) {
                        $staff_sched_days[ $sid ] = [];
                    }
                    $staff_sched_days[ $sid ][] = (int) $sr->day_of_week;
                }
            }
        }
    }

    // When the scheduling system is in use and a clinic is selected,
    // ONLY show dispensers with active schedules for that clinic.
    // If the clinic has zero schedule entries, show NO dispensers (empty calendar).
    // Only fall back to staff_clinics if no clinic selected or scheduling not in use.
    if ( $clinic_id && $system_uses_schedules ) {
        if ( $clinic_has_schedules ) {
            // Show only dispensers with active schedules for this clinic
            $scheduled_ids = array_keys( $staff_sched_days );
            $id_list = implode( ',', array_map( 'intval', $scheduled_ids ) );
            $ps = HearMed_DB::get_results(
                "SELECT s.id, s.first_name, s.last_name,
                       (s.first_name || ' ' || s.last_name) AS full_name,
                       s.role, s.is_active, s.staff_color,
                       ARRAY_AGG(sc.clinic_id) as clinic_ids
                FROM hearmed_reference.staff s
                LEFT JOIN hearmed_reference.staff_clinics sc ON s.id = sc.staff_id
                WHERE s.is_active = true
                  AND s.id IN ({$id_list})
                GROUP BY s.id, s.first_name, s.last_name, s.role, s.is_active, s.staff_color
                ORDER BY s.first_name, s.last_name"
            );
        } else {
            // Clinic has no schedule entries — no one is scheduled here
            $ps = [];
            $clinic_has_schedules = true; // Signal JS to show "no staff" message
        }
    } else {
        $sql = "SELECT s.id, s.first_name, s.last_name,
                       (s.first_name || ' ' || s.last_name) AS full_name,
                       s.role, s.is_active, s.staff_color,
                       ARRAY_AGG(sc.clinic_id) as clinic_ids
                FROM hearmed_reference.staff s
                LEFT JOIN hearmed_reference.staff_clinics sc ON s.id = sc.staff_id
                WHERE s.is_active = true
                  AND LOWER(s.role) IN ('dispenser','audiologist','c_level','hm_clevel')";
        if ( $clinic_id ) {
            $sql .= " AND (sc.clinic_id = $clinic_id OR sc.clinic_id IS NULL)";
        }
        $sql .= " GROUP BY s.id, s.first_name, s.last_name, s.role, s.is_active, s.staff_color ORDER BY s.first_name, s.last_name";
        $ps = HearMed_DB::get_results( $sql );
    }
    $d = [];
    foreach ( ($ps ?: []) as $p ) {
        if ( !$p->is_active ) continue;
        $fname = $p->full_name ?: trim( $p->first_name . ' ' . $p->last_name );
        $initials = strtoupper( substr( $p->first_name, 0, 1 ) . substr( $p->last_name, 0, 1 ) );
        // Parse PostgreSQL array string {1,2,3} into PHP array
        $cids = [];
        if ( ! empty( $p->clinic_ids ) && is_string( $p->clinic_ids ) ) {
            $raw = array_map( 'trim', explode( ',', trim( $p->clinic_ids, '{}' ) ) );
            foreach ( $raw as $rc ) {
                if ( $rc !== '' && $rc !== 'NULL' && is_numeric( $rc ) ) {
                    $cids[] = (int) $rc;
                }
            }
        }
        $sid = (int) $p->id;
        $d[] = [
            'id'             => $sid,
            'name'           => $fname,
            'initials'       => $initials,
            'clinic_id'      => $clinic_id ?: ( ! empty( $cids ) ? $cids[0] : null ),
            'clinic_ids'     => $cids,
            'scheduled_days' => isset( $staff_sched_days[ $sid ] ) ? array_values( array_unique( $staff_sched_days[ $sid ] ) ) : [],
            'calendar_order' => 99,
            'role_type'      => $p->role,
            'color'          => $p->staff_color ?: '#0BB4C4',
            'staff_color'    => $p->staff_color ?: '#0BB4C4',
        ];
    }
    // Tell JS whether this clinic has schedule-based filtering active
    wp_send_json_success( [ 'dispensers' => $d, 'has_schedules' => $clinic_has_schedules ] );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] get_dispensers error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

/**
 * Get clinic schedule coverage — for each clinic, which dispensers are scheduled on which days.
 * Used by the Clinic View in the calendar.
 */
function hm_ajax_get_clinic_coverage() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    try {
        $coverage = [];
        $schedule_table = 'hearmed_reference.dispenser_schedules';
        $has_schedule_table = HearMed_DB::get_var(
            HearMed_DB::prepare( "SELECT to_regclass(%s)", $schedule_table )
        ) !== null;

        if ( $has_schedule_table ) {
            // Check if effective_from/effective_to columns exist
            $has_eff = (bool) HearMed_DB::get_var(
                "SELECT 1 FROM information_schema.columns
                 WHERE table_schema='hearmed_reference' AND table_name='dispenser_schedules'
                   AND column_name='effective_from'"
            );
            $date_filter = $has_eff
                ? " AND (ds.effective_from IS NULL OR ds.effective_from <= CURRENT_DATE)
                     AND (ds.effective_to IS NULL OR ds.effective_to >= CURRENT_DATE)"
                : "";

            $rows = HearMed_DB::get_results(
                "SELECT ds.clinic_id, ds.day_of_week, ds.staff_id,
                        s.first_name, s.last_name, s.staff_color
                 FROM hearmed_reference.dispenser_schedules ds
                 JOIN hearmed_reference.staff s ON ds.staff_id = s.id
                 WHERE ds.is_active = true AND s.is_active = true
                   {$date_filter}
                 ORDER BY ds.clinic_id, ds.day_of_week, s.last_name"
            );

            foreach ( ( $rows ?: [] ) as $r ) {
                $cid = (string) (int) $r->clinic_id;
                $dow = (string) (int) $r->day_of_week;
                if ( ! isset( $coverage[ $cid ] ) ) $coverage[ $cid ] = [];
                if ( ! isset( $coverage[ $cid ][ $dow ] ) ) $coverage[ $cid ][ $dow ] = [];
                $coverage[ $cid ][ $dow ][] = [
                    'id'       => (int) $r->staff_id,
                    'name'     => trim( $r->first_name . ' ' . $r->last_name ),
                    'initials' => strtoupper( substr( $r->first_name, 0, 1 ) . substr( $r->last_name, 0, 1 ) ),
                    'color'    => $r->staff_color ?: '#0BB4C4',
                ];
            }
        }

        wp_send_json_success( [ 'coverage' => $coverage ] );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] get_clinic_coverage error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

function hm_ajax_get_services() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    try {
    // Check if tint_opacity column exists (added by MIGRATION_SERVICE_CARD_COLOURS)
    $has_tint = (bool) HearMed_DB::get_var(
        "SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_reference' AND table_name='appointment_types' AND column_name='tint_opacity'"
    );
    $tint_col = $has_tint ? "COALESCE(tint_opacity, 12) AS tint_opacity," : "12 AS tint_opacity,";
    $ps = HearMed_DB::get_results(
        "SELECT id, service_name,
                COALESCE(service_color, '#3B82F6') AS service_color,
                COALESCE(text_color, '#FFFFFF') AS text_color,
                COALESCE(duration_minutes, 30) AS duration_minutes,
                {$tint_col}
                is_active
         FROM hearmed_reference.appointment_types WHERE is_active = true ORDER BY service_name"
    );
    $d = [];
    foreach ( $ps as $p ) {
        $d[] = [
            'id'       => (int) $p->id,
            'name'     => $p->service_name,
            'colour'   => $p->service_color ?: '#3B82F6',
            'duration' => (int) ($p->duration_minutes ?: 30),
            'tint_opacity' => (int) ($p->tint_opacity ?: 12),
        ];
    }
    wp_send_json_success( $d );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] get_services error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

// ================================================================
// PATIENT SEARCH
// ================================================================
function hm_ajax_search_patients() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $q = sanitize_text_field( $_POST['q'] ?? $_POST['query'] ?? '' );
    if ( strlen( $q ) < 2 ) { wp_send_json_success( [] ); return; }

    try {
    // PostgreSQL source of truth: hearmed_core.patients
    $search_term = '%' . $q . '%';
    $results = HearMed_DB::get_results(
        "SELECT p.id, p.patient_number, p.first_name, p.last_name, p.phone, p.mobile, p.email,
                p.address_line1, p.city, p.eircode,
                p.referral_source_id, COALESCE(rs.source_name, '') AS referral_source_name,
                p.marketing_sms
         FROM hearmed_core.patients p
         LEFT JOIN hearmed_reference.referral_sources rs ON rs.id = p.referral_source_id
         WHERE p.is_active = true
           AND (p.first_name ILIKE \$1 OR p.last_name ILIKE \$2
                OR (p.first_name || ' ' || p.last_name) ILIKE \$3
                OR p.patient_number ILIKE \$4
                OR p.phone ILIKE \$5 OR p.mobile ILIKE \$6)
         ORDER BY p.last_name, p.first_name
         LIMIT 20",
        [ $search_term, $search_term, $search_term, $search_term, $search_term, $search_term ]
    );

    $d = [];
    if ( $results ) {
        foreach ( $results as $p ) {
            $addr_parts = array_filter([ $p->address_line1 ?? '', $p->city ?? '', $p->eircode ?? '' ]);
            $d[] = [
                'id'                    => (int) $p->id,
                'name'                  => "{$p->first_name} {$p->last_name}",
                'label'                 => ( $p->patient_number ? "{$p->patient_number} \xe2\x80\x94 " : '' ) . "{$p->first_name} {$p->last_name}",
                'phone'                 => $p->phone ?? $p->mobile ?? '',
                'mobile'                => $p->mobile ?? '',
                'address'               => implode( ', ', $addr_parts ),
                'patient_number'        => $p->patient_number ?? '',
                'referral_source_id'    => (int) ($p->referral_source_id ?? 0),
                'referral_source_name'  => $p->referral_source_name ?? '',
                'marketing_sms'         => ! empty( $p->marketing_sms ) && $p->marketing_sms !== 'f' && $p->marketing_sms !== '0',
            ];
        }
    }
    wp_send_json_success( $d );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] search_patients error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

/**
 * Advanced patient search — multi-field AND logic.
 * Searches: name, phone/mobile, serial numbers, PPS, H-number, DOB, email.
 */
add_action( 'wp_ajax_hm_advanced_search_patients', 'hm_ajax_advanced_search_patients' );
function hm_ajax_advanced_search_patients() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    $name   = sanitize_text_field( $_POST['name']   ?? '' );
    $phone  = sanitize_text_field( $_POST['phone']  ?? '' );
    $serial = sanitize_text_field( $_POST['serial'] ?? '' );
    $pps    = sanitize_text_field( $_POST['pps']    ?? '' );
    $hnum   = sanitize_text_field( $_POST['hnum']   ?? '' );
    $dob    = sanitize_text_field( $_POST['dob']    ?? '' );
    $email  = sanitize_text_field( $_POST['email']  ?? '' );

    // At least one field must have a value
    if ( ! $name && ! $phone && ! $serial && ! $pps && ! $hnum && ! $dob && ! $email ) {
        wp_send_json_success( [] );
        return;
    }

    try {
        $clauses = [];
        $params  = [];
        $idx     = 1;

        if ( $name ) {
            $term = '%' . $name . '%';
            $clauses[] = "(p.first_name ILIKE \${$idx} OR p.last_name ILIKE \$" . ($idx+1) . " OR (p.first_name || ' ' || p.last_name) ILIKE \$" . ($idx+2) . ")";
            $params[] = $term; $params[] = $term; $params[] = $term;
            $idx += 3;
        }

        if ( $phone ) {
            $term = '%' . $phone . '%';
            $clauses[] = "(p.phone ILIKE \${$idx} OR p.mobile ILIKE \$" . ($idx+1) . ")";
            $params[] = $term; $params[] = $term;
            $idx += 2;
        }

        if ( $pps ) {
            $term = '%' . $pps . '%';
            $clauses[] = "p.prsi_number ILIKE \${$idx}";
            $params[] = $term;
            $idx++;
        }

        if ( $hnum ) {
            $term = '%' . $hnum . '%';
            $clauses[] = "p.patient_number ILIKE \${$idx}";
            $params[] = $term;
            $idx++;
        }

        if ( $dob ) {
            $clauses[] = "p.date_of_birth = \${$idx}";
            $params[] = $dob;
            $idx++;
        }

        if ( $email ) {
            $term = '%' . $email . '%';
            $clauses[] = "p.email ILIKE \${$idx}";
            $params[] = $term;
            $idx++;
        }

        // Serial number search — joins to patient_devices
        $serial_join = '';
        if ( $serial ) {
            $term = '%' . $serial . '%';
            $serial_join = " LEFT JOIN hearmed_core.patient_devices pd ON pd.patient_id = p.id";
            $clauses[] = "(pd.serial_number_left ILIKE \${$idx} OR pd.serial_number_right ILIKE \$" . ($idx+1) . ")";
            $params[] = $term; $params[] = $term;
            $idx += 2;
        }

        $where = implode( ' AND ', $clauses );

        $sql = "SELECT DISTINCT p.id, p.patient_number, p.first_name, p.last_name,
                       p.phone, p.mobile, p.email, p.address_line1, p.city, p.eircode,
                       p.is_active
                FROM hearmed_core.patients p
                {$serial_join}
                WHERE {$where}
                ORDER BY p.last_name, p.first_name
                LIMIT 30";

        $results = HearMed_DB::get_results( $sql, $params );

        $d = [];
        if ( $results ) {
            foreach ( $results as $p ) {
                $addr_parts = array_filter([ $p->address_line1 ?? '', $p->city ?? '', $p->eircode ?? '' ]);
                $d[] = [
                    'id'             => (int) $p->id,
                    'name'           => "{$p->first_name} {$p->last_name}",
                    'phone'          => $p->phone ?? $p->mobile ?? '',
                    'mobile'         => $p->mobile ?? '',
                    'address'        => implode( ', ', $addr_parts ),
                    'patient_number' => $p->patient_number ?? '',
                    'is_active'      => ! empty( $p->is_active ) && $p->is_active !== 'f',
                ];
            }
        }
        wp_send_json_success( $d );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] advanced_search_patients error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

// ================================================================
// AUTO-MIGRATE: referral_source_id, sms_reminder_hours, sms_reminder_sent
// ================================================================
function hm_ensure_appointment_referral_sms_columns() {
    static $done = false;
    if ( $done ) return;
    $done = true;
    try {
        $cols = HearMed_DB::get_results(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = 'hearmed_core' AND table_name = 'appointments'
               AND column_name IN ('referral_source_id','sms_reminder_hours','sms_reminder_sent')"
        );
        $existing = [];
        if ( $cols ) {
            foreach ( $cols as $c ) {
                $existing[] = $c->column_name;
            }
        }
        if ( ! in_array( 'referral_source_id', $existing ) ) {
            HearMed_DB::query( "ALTER TABLE hearmed_core.appointments ADD COLUMN referral_source_id INT" );
        }
        if ( ! in_array( 'sms_reminder_hours', $existing ) ) {
            HearMed_DB::query( "ALTER TABLE hearmed_core.appointments ADD COLUMN sms_reminder_hours INT" );
        }
        if ( ! in_array( 'sms_reminder_sent', $existing ) ) {
            HearMed_DB::query( "ALTER TABLE hearmed_core.appointments ADD COLUMN sms_reminder_sent BOOLEAN DEFAULT FALSE" );
        }
    } catch ( Throwable $e ) {
        error_log( '[HearMed] auto-migrate appointment referral/sms cols: ' . $e->getMessage() );
    }
}

// ================================================================
// SMS REMINDER CRON — sends reminders, checking GDPR consent
// ================================================================
add_action( 'hm_send_sms_reminders', 'hm_cron_send_sms_reminders' );

// Schedule the cron on init if not already scheduled
add_action( 'init', function() {
    if ( ! wp_next_scheduled( 'hm_send_sms_reminders' ) ) {
        wp_schedule_event( time(), 'hourly', 'hm_send_sms_reminders' );
    }
});

function hm_cron_send_sms_reminders() {
    try {
        // Find appointments that have sms_reminder_hours set, are not yet sent, and fall within the window
        $rows = HearMed_DB::get_results(
            "SELECT a.id, a.patient_id, a.appointment_date, a.start_time,
                    a.sms_reminder_hours, a.service_id,
                    p.first_name, p.last_name, p.patient_mobile, p.patient_phone,
                    p.marketing_sms, p.gdpr_consent,
                    sv.service_name,
                    COALESCE(sv.reminder_sms_template_id, 0) AS sms_template_id
             FROM hearmed_core.appointments a
             JOIN hearmed_core.patients p ON p.id = a.patient_id
             LEFT JOIN hearmed_reference.appointment_types sv ON sv.id = a.service_id
             WHERE a.sms_reminder_hours IS NOT NULL
               AND a.sms_reminder_hours > 0
               AND (a.sms_reminder_sent IS NULL OR a.sms_reminder_sent = false)
               AND a.appointment_status NOT IN ('Cancelled','Rescheduled','Completed')
               AND a.appointment_date >= CURRENT_DATE
               AND (a.appointment_date || ' ' || a.start_time)::TIMESTAMP
                   <= (NOW() + (a.sms_reminder_hours || ' hours')::INTERVAL)
               AND (a.appointment_date || ' ' || a.start_time)::TIMESTAMP > NOW()"
        );

        if ( empty( $rows ) ) return;

        foreach ( $rows as $r ) {
            // ── GDPR CHECK ──
            // Only send if patient has marketing_sms consent ticked
            $sms_ok = ! empty( $r->marketing_sms ) && $r->marketing_sms !== 'f' && $r->marketing_sms !== '0';
            if ( ! $sms_ok ) {
                // Mark as sent so we don't keep checking — patient hasn't consented
                HearMed_DB::update( 'hearmed_core.appointments',
                    [ 'sms_reminder_sent' => true ],
                    [ 'id' => (int) $r->id ]
                );
                error_log( '[HearMed] SMS reminder skipped for appointment #' . $r->id
                    . ' — patient #' . $r->patient_id . ' has not consented to SMS (marketing_sms).' );
                continue;
            }

            // Get the mobile number
            $mobile = $r->patient_mobile ?: $r->patient_phone;
            if ( empty( $mobile ) ) {
                HearMed_DB::update( 'hearmed_core.appointments',
                    [ 'sms_reminder_sent' => true ],
                    [ 'id' => (int) $r->id ]
                );
                error_log( '[HearMed] SMS reminder skipped for appointment #' . $r->id
                    . ' — patient #' . $r->patient_id . ' has no mobile number.' );
                continue;
            }

            // Build SMS message — use template if set, otherwise default
            $sms_body = '';
            $tpl_id = (int) $r->sms_template_id;
            if ( $tpl_id ) {
                $tpl = HearMed_DB::get_row(
                    "SELECT template_content FROM hearmed_communication.sms_templates WHERE id = $1",
                    [ $tpl_id ]
                );
                if ( $tpl && $tpl->template_content ) {
                    $sms_body = $tpl->template_content;
                    // Replace placeholders
                    $sms_body = str_replace( '{first_name}', $r->first_name, $sms_body );
                    $sms_body = str_replace( '{last_name}', $r->last_name, $sms_body );
                    $sms_body = str_replace( '{service_name}', $r->service_name ?? '', $sms_body );
                    $sms_body = str_replace( '{appointment_date}', $r->appointment_date, $sms_body );
                    $sms_body = str_replace( '{start_time}', substr( $r->start_time, 0, 5 ), $sms_body );
                }
            }
            if ( empty( $sms_body ) ) {
                $sms_body = 'Hi ' . $r->first_name . ', this is a reminder of your appointment on '
                    . $r->appointment_date . ' at ' . substr( $r->start_time, 0, 5 )
                    . ( $r->service_name ? ' (' . $r->service_name . ')' : '' )
                    . '. Please call us if you need to reschedule.';
            }

            // Send via HearMed SMS gateway (if class exists)
            $sent = false;
            if ( class_exists( 'HearMed_SMS' ) && method_exists( 'HearMed_SMS', 'send' ) ) {
                $sent = HearMed_SMS::send( $mobile, $sms_body );
            } else {
                // Fallback: fire an action so external integrations can hook in
                do_action( 'hm_send_sms', $mobile, $sms_body, [
                    'appointment_id' => (int) $r->id,
                    'patient_id'     => (int) $r->patient_id,
                    'type'           => 'appointment_reminder',
                ] );
                $sent = true; // assume sent by hook consumers
            }

            if ( $sent ) {
                HearMed_DB::update( 'hearmed_core.appointments',
                    [ 'sms_reminder_sent' => true ],
                    [ 'id' => (int) $r->id ]
                );
                error_log( '[HearMed] SMS reminder sent for appointment #' . $r->id
                    . ' to patient #' . $r->patient_id . ' (' . $mobile . ').' );
            }
        }
    } catch ( Throwable $e ) {
        error_log( '[HearMed] SMS reminder cron error: ' . $e->getMessage() );
    }
}

// ================================================================
// APPOINTMENTS — GET, CREATE, UPDATE, DELETE
// ================================================================
function hm_ajax_get_appointments() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    try {
    $start = sanitize_text_field( $_POST['start'] ?? date( 'Y-m-d' ) );
    $end   = sanitize_text_field( $_POST['end']   ?? $start );
    $cl    = intval( $_POST['clinic']    ?? 0 );
    $dp    = intval( $_POST['dispenser'] ?? 0 );

    // Direct column references — known schema: hearmed_core.appointments
    // Columns: staff_id, service_id, appointment_type_id, duration_minutes, appointment_status
    // Services table: service_color (NOT colour), duration_minutes (NOT duration)
    $sql = "SELECT a.id, a.patient_id, a.staff_id, a.clinic_id,
                   a.service_id AS resolved_service_id,
                   a.appointment_date, a.start_time, a.end_time,
                   a.appointment_status, a.location_type, a.notes, a.outcome,
                   a.duration_minutes,
                   a.created_by,
                   a.referral_source_id, a.sms_reminder_hours,
                   p.first_name AS patient_first, p.last_name AS patient_last, p.patient_number,
                   (st.first_name || ' ' || st.last_name) AS dispenser_name,
                   c.clinic_name,
                   sv.service_name,
                   COALESCE(sv.service_color, '#3B82F6') AS service_colour,
                   COALESCE(sv.text_color, '#FFFFFF') AS service_text_color,
                   COALESCE(a.duration_minutes, sv.duration_minutes, 30) AS service_duration,
                   COALESCE(sv.sales_opportunity, false) AS sales_opportunity,
                   COALESCE(sv.income_bearing, false) AS income_bearing,
                   COALESCE(ao.outcome_color, '') AS outcome_banner_colour,
                   COALESCE(ao.outcome_name, a.outcome, '') AS resolved_outcome_name
            FROM hearmed_core.appointments a
            LEFT JOIN hearmed_core.patients p ON a.patient_id = p.id
            LEFT JOIN hearmed_reference.staff st ON a.staff_id = st.id
            LEFT JOIN hearmed_reference.clinics c ON a.clinic_id = c.id
            LEFT JOIN hearmed_reference.appointment_types sv ON sv.id = a.service_id
            LEFT JOIN LATERAL (
                SELECT outcome_color, outcome_name FROM hearmed_core.appointment_outcomes
                WHERE appointment_id = a.id ORDER BY created_at DESC LIMIT 1
            ) ao ON true
            WHERE a.appointment_date >= \$1 AND a.appointment_date <= \$2";
    $params = [ $start, $end ];
    $pi = 3;
    if ( $cl ) {
        $sql .= " AND a.clinic_id = \${$pi}";
        $params[] = $cl;
        $pi++;
    }
    if ( $dp ) {
        $sql .= " AND a.staff_id = \${$pi}";
        $params[] = $dp;
        $pi++;
    }
    $sql .= " ORDER BY a.appointment_date, a.start_time";
    $rows = HearMed_DB::get_results( $sql, $params );

    if ( empty( $rows ) ) {
        $db_err = HearMed_DB::last_error();
        if ( $db_err ) {
            error_log( '[HearMed] get_appointments QUERY FAILED: ' . $db_err );
            error_log( '[HearMed] get_appointments SQL: ' . $sql );
        }
    }

    $d = [];

    foreach ( ($rows ?: []) as $r ) {
        $pname = 'Walk-in';
        if ( $r->patient_first && $r->patient_last ) {
            $pname = $r->patient_first . ' ' . $r->patient_last;
        }
        $d[] = [
            'id'                    => (int) $r->id,
            '_ID'                   => (int) $r->id,
            'patient_id'            => (int) ($r->patient_id ?: 0),
            'patient_name'          => $pname,
            'patient_number'        => $r->patient_number ?? '',
            'dispenser_id'          => (int) ($r->staff_id ?: 0),
            'dispenser_name'        => $r->dispenser_name ?? '',
            'clinic_id'             => (int) ($r->clinic_id ?: 0),
            'clinic_name'           => $r->clinic_name ?? '',
            'service_id'            => (int) ($r->resolved_service_id ?: 0),
            'service_name'          => $r->service_name ?? '',
            'service_colour'        => $r->service_colour ?: '#3B82F6',
            'text_color'            => $r->service_text_color ?: '#FFFFFF',
            'appointment_date'      => $r->appointment_date,
            'start_time'            => $r->start_time,
            'end_time'              => $r->end_time ?? '',
            'duration'              => (int) ($r->service_duration ?: 30),
            'status'                => $r->appointment_status ?: 'Not Confirmed',
            'location_type'         => $r->location_type ?? 'Clinic',
            'notes'                 => $r->notes ?? '',
            'outcome'               => $r->outcome ?? '',
            'outcome_id'            => 0,
            'outcome_name'          => $r->resolved_outcome_name ?: ($r->outcome ?? ''),
            'outcome_banner_colour' => $r->outcome_banner_colour ?: '',
            'created_by'            => (int) ($r->created_by ?? 0),
            'sales_opportunity'     => !empty($r->sales_opportunity) && $r->sales_opportunity !== 'f',
            'income_bearing'        => !empty($r->income_bearing) && $r->income_bearing !== 'f',
            'referral_source_id'    => (int) ($r->referral_source_id ?? 0),
            'sms_reminder_hours'    => (int) ($r->sms_reminder_hours ?? 0),
        ];

        // Fallback: if outcome name exists but no banner colour, look up from templates
        $last = &$d[count($d) - 1];
        if ( $last['outcome_name'] && ! $last['outcome_banner_colour'] ) {
            $tpl_color = HearMed_DB::get_var(
                "SELECT outcome_color FROM hearmed_core.outcome_templates WHERE outcome_name = $1 LIMIT 1",
                [ $last['outcome_name'] ]
            );
            if ( $tpl_color ) {
                $last['outcome_banner_colour'] = $tpl_color;
            }
        }
        unset( $last );
    }
    wp_send_json_success( $d );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] get_appointments error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

function hm_ajax_create_appointment() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( 'Denied' ); return; }
    try {
    $t   = HearMed_Portal::table( 'appointments' );
    $sid = intval( $_POST['service_id'] );

    // Get duration: from POST (user override) or from services table default
    $dur = intval( $_POST['duration'] ?? 0 );
    if ( ! $dur && $sid ) {
        $svc_dur = HearMed_DB::get_var(
            "SELECT COALESCE(duration_minutes, 30) FROM hearmed_reference.appointment_types WHERE id = $1", [ $sid ]
        );
        $dur = intval( $svc_dur ) ?: 30;
    }
    if ( ! $dur ) $dur = 30;

    // Calculate end_time from start_time + duration
    $st  = sanitize_text_field( $_POST['start_time'] );
    $sp  = explode( ':', $st );
    $em  = intval( $sp[0] ) * 60 + intval( $sp[1] ) + $dur;
    $et  = sprintf( '%02d:%02d', floor( $em / 60 ), $em % 60 );

    $patient_id = intval( $_POST['patient_id'] ?? 0 );
    $appt_date  = sanitize_text_field( $_POST['appointment_date'] );
    $staff_id   = intval( $_POST['dispenser_id'] ?? 0 );
    $skip_dbl   = ! empty( $_POST['skip_double_book_check'] );

    // ── SAME-PATIENT BLOCK ──
    // Never allow the same patient to be double-booked at overlapping times
    if ( $patient_id ) {
        $same_patient = HearMed_DB::get_results(
            "SELECT a.id, a.start_time, a.end_time, c.clinic_name
             FROM hearmed_core.appointments a
             LEFT JOIN hearmed_reference.clinics c ON c.id = a.clinic_id
             WHERE a.patient_id = $1 AND a.appointment_date = $2
               AND a.appointment_status NOT IN ('Cancelled','Rescheduled')
               AND a.start_time < $3 AND a.end_time > $4",
            [ $patient_id, $appt_date, $et, $st ]
        );
        if ( ! empty( $same_patient ) ) {
            $sp_row = $same_patient[0];
            $clinic_name = $sp_row->clinic_name ?: 'Unknown clinic';
            $sp_time = substr( $sp_row->start_time, 0, 5 );
            wp_send_json_error( [
                'code'    => 'same_patient_conflict',
                'message' => "This patient is already booked into {$clinic_name} at {$sp_time}. Cannot create a new appointment.",
            ] );
            return;
        }
    }

    // ── EXCLUSION OVERLAP CHECK ──
    // Warn (but allow override) if booking into an exclusion window for this dispenser or clinic
    $skip_excl = ! empty( $_POST['skip_exclusion_check'] );
    if ( ! $skip_excl ) {
        $excl_table = 'hearmed_core.exclusion_instances';
        $excl_exists = HearMed_DB::get_var( "SELECT to_regclass('{$excl_table}')" );
        if ( $excl_exists !== null ) {
            // Check non-repeating exclusions that overlap this date/time + dispenser (or all-staff)
            $excl_hits = HearMed_DB::get_results(
                "SELECT ei.id, et.type_name, ei.scope, ei.start_time AS ex_start, ei.end_time AS ex_end,
                        ei.staff_id, ei.reason
                 FROM {$excl_table} ei
                 LEFT JOIN hearmed_reference.exclusion_types et ON et.id = ei.exclusion_type_id
                 WHERE ei.is_active = true
                   AND ei.repeat_type = 'none'
                   AND ei.start_date <= $1 AND ei.end_date >= $1
                   AND (ei.staff_id IS NULL OR ei.staff_id = 0 OR ei.staff_id = $2)
                   AND (
                       ei.scope = 'full_day'
                       OR (ei.start_time < $3 AND ei.end_time > $4)
                   )",
                [ $appt_date, $staff_id, $et, $st ]
            );
            if ( ! empty( $excl_hits ) ) {
                $ex = $excl_hits[0];
                $who = ( $ex->staff_id && intval( $ex->staff_id ) ) ? 'this dispenser' : 'this clinic';
                $excl_name = $ex->type_name ?: 'Exclusion';
                $excl_msg  = "{$excl_name} is active for {$who} on this date";
                if ( $ex->scope !== 'full_day' && $ex->ex_start && $ex->ex_end ) {
                    $excl_msg .= ' (' . substr( $ex->ex_start, 0, 5 ) . ' – ' . substr( $ex->ex_end, 0, 5 ) . ')';
                }
                $excl_msg .= ".\n\nAre you sure you want to book this appointment?";
                wp_send_json_error( [
                    'code'    => 'exclusion_conflict',
                    'message' => $excl_msg,
                ] );
                return;
            }
        }
    }

    // ── DISPENSER DOUBLE-BOOK CHECK ──
    // If not explicitly confirmed, return conflict info so JS can prompt
    if ( ! $skip_dbl && $staff_id ) {
        $conflicts = HearMed_DB::get_results(
            "SELECT a.id, a.start_time, a.end_time, c.clinic_name,
                    (p.first_name || ' ' || p.last_name) AS patient_name
             FROM hearmed_core.appointments a
             LEFT JOIN hearmed_reference.clinics c ON c.id = a.clinic_id
             LEFT JOIN hearmed_core.patients p ON p.id = a.patient_id
             WHERE a.staff_id = $1 AND a.appointment_date = $2
               AND a.appointment_status NOT IN ('Cancelled','Rescheduled')
               AND a.start_time < $3 AND a.end_time > $4",
            [ $staff_id, $appt_date, $et, $st ]
        );
        if ( ! empty( $conflicts ) ) {
            $cdata = [];
            foreach ( $conflicts as $cf ) {
                $cdata[] = [
                    'clinic'  => $cf->clinic_name ?: '',
                    'time'    => substr( $cf->start_time, 0, 5 ) . '–' . substr( $cf->end_time, 0, 5 ),
                    'patient' => $cf->patient_name ?: 'Walk-in',
                ];
            }
            wp_send_json_error( [
                'code'      => 'double_book_conflict',
                'message'   => 'Double booking detected',
                'conflicts' => $cdata,
            ] );
            return;
        }
    }

    // Direct insert — known schema columns
    $insert_data = [
        'patient_id'         => $patient_id,
        'staff_id'           => $staff_id,
        'clinic_id'          => intval( $_POST['clinic_id']  ?? 0 ),
        'service_id'         => $sid,
        'appointment_date'   => $appt_date,
        'start_time'         => $st,
        'end_time'           => $et,
        'duration_minutes'   => $dur,
        'appointment_status' => sanitize_text_field( $_POST['status'] ?? 'Not Confirmed' ),
        'location_type'      => sanitize_text_field( $_POST['location_type'] ?? 'Clinic' ),
        'referring_source'   => sanitize_text_field( $_POST['referring_source'] ?? '' ),
        'referral_source_id' => intval( $_POST['referral_source_id'] ?? 0 ) ?: null,
        'sms_reminder_hours' => intval( $_POST['sms_reminder_hours'] ?? 0 ) ?: null,
        'sms_reminder_sent'  => false,
        'notes'              => sanitize_textarea_field( $_POST['notes'] ?? '' ),
        'created_by'         => PortalAuth::staff_id(),
        'created_at'         => current_time( 'mysql' ),
        'updated_at'         => current_time( 'mysql' ),
    ];

    // Auto-migrate: ensure referral_source_id, sms_reminder_hours, sms_reminder_sent columns exist
    hm_ensure_appointment_referral_sms_columns();

    $new_id = HearMed_DB::insert( $t, $insert_data );

    if ( $new_id === false ) {
        $db_err = HearMed_DB::last_error();
        error_log( '[HearMed] create_appointment INSERT FAILED — table: ' . $t . ' | error: ' . $db_err );
        error_log( '[HearMed] create_appointment INSERT DATA: ' . wp_json_encode( $insert_data ) );
        wp_send_json_error( [ 'message' => 'Database insert failed: ' . $db_err ] );
        return;
    }

    HearMed_Portal::log( 'created', 'appointment', $new_id, [
        'patient_id' => intval( $_POST['patient_id'] ?? 0 ),
        'date'       => sanitize_text_field( $_POST['appointment_date'] ),
    ] );
    wp_send_json_success( [ 'id' => $new_id ] );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] create_appointment error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

function hm_ajax_update_appointment() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( 'Denied' ); return; }
    try {
    $t   = HearMed_Portal::table( 'appointments' );
    $id  = intval( $_POST['appointment_id'] );

    $user = wp_get_current_user();
    $is_admin_role = ! empty( array_intersect( [ 'administrator', 'hm_clevel', 'hm_admin', 'hm_finance' ], (array) ( $user->roles ?? [] ) ) );

    $existing = HearMed_DB::get_row(
        "SELECT appointment_status, outcome FROM hearmed_core.appointments WHERE id = $1",
        [ $id ]
    );
    if ( ! $existing ) {
        wp_send_json_error( 'Appointment not found' );
        return;
    }

    $is_closed = ( ( $existing->appointment_status ?? '' ) === 'Completed' ) || ! empty( trim( (string) ( $existing->outcome ?? '' ) ) );
    $data = [ 'updated_at' => current_time( 'mysql' ) ];

    // Closed appointments: only admin/c-level/finance can fully edit/reopen.
    // All users may still add/update notes after closure.
    if ( $is_closed && ! $is_admin_role ) {
        if ( isset( $_POST['notes'] ) ) {
            $data['notes'] = sanitize_textarea_field( $_POST['notes'] );
        } else {
            wp_send_json_error( 'Permission denied — only notes can be updated on closed appointments.' );
            return;
        }

        // Block all non-note edits for non-admin roles on closed appointments.
        foreach ( [ 'status', 'appointment_date', 'location_type', 'patient_id', 'clinic_id', 'dispenser_id', 'start_time', 'service_id', 'duration', 'outcome' ] as $blocked_key ) {
            if ( isset( $_POST[ $blocked_key ] ) ) {
                wp_send_json_error( 'Permission denied — only notes can be updated on closed appointments.' );
                return;
            }
        }

        HearMed_DB::update( $t, $data, [ 'id' => $id ] );
        HearMed_Portal::log( 'updated_note', 'appointment', $id );
        wp_send_json_success( [ 'id' => $id, 'notes_only' => true ] );
        return;
    }

    // Status update
    if ( isset( $_POST['status'] ) ) {
        $data['appointment_status'] = sanitize_text_field( $_POST['status'] );
    }

    // Full edit fields
    if ( isset( $_POST['appointment_date'] ) ) $data['appointment_date'] = sanitize_text_field( $_POST['appointment_date'] );
    if ( isset( $_POST['location_type'] ) )    $data['location_type'] = sanitize_text_field( $_POST['location_type'] );
    if ( isset( $_POST['notes'] ) )            $data['notes'] = sanitize_textarea_field( $_POST['notes'] );
    if ( isset( $_POST['patient_id'] ) )       $data['patient_id'] = intval( $_POST['patient_id'] );
    if ( isset( $_POST['clinic_id'] ) )        $data['clinic_id'] = intval( $_POST['clinic_id'] );

    // Staff
    if ( isset( $_POST['dispenser_id'] ) ) {
        $data['staff_id'] = intval( $_POST['dispenser_id'] );
    }

    // Service + duration + time recalculation
    if ( isset( $_POST['start_time'] ) ) {
        $sid = intval( $_POST['service_id'] ?? 0 );
        if ( ! $sid ) {
            $sid = (int) HearMed_DB::get_var(
                "SELECT service_id FROM hearmed_core.appointments WHERE id = $1", [ $id ]
            );
        }
        $dur = intval( $_POST['duration'] ?? 0 );
        if ( ! $dur && $sid ) {
            $svc_dur = HearMed_DB::get_var(
                "SELECT COALESCE(duration_minutes, 30) FROM hearmed_reference.appointment_types WHERE id = $1", [ $sid ]
            );
            $dur = intval( $svc_dur ) ?: 30;
        }
        if ( ! $dur ) $dur = 30;
        $st  = sanitize_text_field( $_POST['start_time'] );
        $sp  = explode( ':', $st );
        $em  = intval( $sp[0] ) * 60 + intval( $sp[1] ) + $dur;
        $et  = sprintf( '%02d:%02d', floor( $em / 60 ), $em % 60 );

        $data['service_id']          = $sid;
        $data['duration_minutes']    = $dur;
        $data['start_time']          = $st;
        $data['end_time']            = $et;
    }

    // Outcome (varchar column)
    if ( isset( $_POST['outcome'] ) ) {
        $data['outcome'] = sanitize_text_field( $_POST['outcome'] );
    }

    // Referral source
    if ( isset( $_POST['referral_source_id'] ) ) {
        $data['referral_source_id'] = intval( $_POST['referral_source_id'] ) ?: null;
    }

    // SMS reminder hours
    if ( isset( $_POST['sms_reminder_hours'] ) ) {
        $data['sms_reminder_hours'] = intval( $_POST['sms_reminder_hours'] ) ?: null;
        $data['sms_reminder_sent']  = false; // reset if timing changed
    }

    HearMed_DB::update( $t, $data, [ 'id' => $id ] );
    HearMed_Portal::log( 'updated', 'appointment', $id );
    wp_send_json_success( [ 'id' => $id ] );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] update_appointment error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

// ================================================================
// APPOINTMENT STATUS CHANGE — with notes + notifications
// ================================================================
function hm_ajax_update_appointment_status() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( 'Denied' ); return; }
    try {
        $id     = intval( $_POST['appointment_id'] );
        $status = sanitize_text_field( $_POST['status'] ?? '' );
        $note   = sanitize_textarea_field( $_POST['note'] ?? '' );

        if ( ! $id || ! $status ) {
            wp_send_json_error( 'Missing appointment_id or status' );
            return;
        }

        $t   = HearMed_Portal::table( 'appointments' );

        // Fetch current appointment for context
        $appt = HearMed_DB::get_row(
            "SELECT a.*, p.first_name AS patient_first, p.last_name AS patient_last,
                    p.patient_number,
                    sv.service_name,
                    COALESCE(sv.service_color, '#3B82F6') AS service_colour,
                    (st.first_name || ' ' || st.last_name) AS dispenser_name
             FROM hearmed_core.appointments a
             LEFT JOIN hearmed_core.patients p ON a.patient_id = p.id
             LEFT JOIN hearmed_reference.staff st ON a.staff_id = st.id
             LEFT JOIN hearmed_reference.appointment_types sv ON sv.id = a.service_id
             WHERE a.id = \$1",
            [ $id ]
        );
        if ( ! $appt ) { wp_send_json_error( 'Appointment not found' ); return; }

        $patient_name = trim( ( $appt->patient_first ?? '' ) . ' ' . ( $appt->patient_last ?? '' ) ) ?: 'Walk-in';
        $patient_id   = (int) ( $appt->patient_id ?? 0 );
        $staff_id     = (int) ( $appt->staff_id ?? 0 );
        $svc_name     = $appt->service_name ?? '';
        $appt_date    = $appt->appointment_date ?? '';
        $appt_time    = substr( $appt->start_time ?? '', 0, 5 );
        $staff_user   = hm_notif_staff_id() ?: null;

        $result = [ 'id' => $id, 'new_status' => $status ];

        // --- Update status ---
        $update = [ 'appointment_status' => $status, 'updated_at' => current_time( 'mysql' ) ];
        $affected = HearMed_DB::update( $t, $update, [ 'id' => $id ] );

        if ( $affected === false ) {
            $db_err = HearMed_DB::last_error();
            error_log( '[HearMed] update_appointment_status UPDATE FAILED — id: ' . $id . ' status: ' . $status . ' | error: ' . $db_err );
            wp_send_json_error( [ 'message' => 'Database update failed: ' . $db_err ] );
            return;
        }

        HearMed_Portal::log( 'status_change', 'appointment', $id, [ 'status' => $status ] );

        // --- Per-status side-effects ---
        switch ( $status ) {

            case 'Confirmed':
                // Add note to patient file
                if ( $patient_id ) {
                    HearMed_DB::insert( 'patient_notes', [
                        'patient_id' => $patient_id,
                        'note_type'  => 'Appointment',
                        'note_text'  => "Appointment confirmed — {$svc_name} on {$appt_date} at {$appt_time}",
                        'created_by' => $staff_user,
                    ]);
                }
                break;

            case 'Arrived':
                // Send notification to dispenser
                if ( $staff_id && class_exists( 'HearMed_Notifications' ) ) {
                    HearMed_Notifications::create( $staff_id, 'appointment_arrived', [
                        'subject'      => "Patient arrived — {$patient_name}",
                        'message'      => "{$patient_name} has arrived for their {$appt_time} {$svc_name} appointment.",
                        'patient_name' => $patient_name,
                        'entity_type'  => 'appointment',
                        'entity_id'    => $id,
                        'created_by'   => $staff_user,
                    ]);
                }
                break;

            case 'Late':
                // Send notification to dispenser with note
                if ( $staff_id && class_exists( 'HearMed_Notifications' ) ) {
                    $msg = "{$patient_name} is running late for their {$appt_time} {$svc_name} appointment.";
                    if ( $note ) $msg .= "\nNote: {$note}";
                    HearMed_Notifications::create( $staff_id, 'appointment_late', [
                        'subject'      => "Patient running late — {$patient_name}",
                        'message'      => $msg,
                        'patient_name' => $patient_name,
                        'entity_type'  => 'appointment',
                        'entity_id'    => $id,
                        'created_by'   => $staff_user,
                    ]);
                }
                // Add note to patient file
                if ( $patient_id && $note ) {
                    HearMed_DB::insert( 'patient_notes', [
                        'patient_id' => $patient_id,
                        'note_type'  => 'Appointment',
                        'note_text'  => "Running late — {$svc_name} on {$appt_date} at {$appt_time}. {$note}",
                        'created_by' => $staff_user,
                    ]);
                }
                break;

            case 'Rescheduled':
                // Notify dispenser
                if ( $staff_id && class_exists( 'HearMed_Notifications' ) ) {
                    $new_date = sanitize_text_field( $_POST['new_date'] ?? '' );
                    $new_time = sanitize_text_field( $_POST['new_time'] ?? '' );
                    $msg = "Appointment rescheduled — {$patient_name} ({$svc_name}, was {$appt_date} {$appt_time}).";
                    if ( $new_date && $new_time ) $msg .= "\nNew: {$new_date} at {$new_time}.";
                    if ( $note ) $msg .= "\nNote: {$note}";
                    HearMed_Notifications::create( $staff_id, 'appointment_rescheduled', [
                        'subject'      => "Appointment rescheduled — {$patient_name}",
                        'message'      => $msg,
                        'patient_name' => $patient_name,
                        'entity_type'  => 'appointment',
                        'entity_id'    => $id,
                        'created_by'   => $staff_user,
                    ]);
                }
                // Add note to patient file
                if ( $patient_id ) {
                    $note_text = "Appointment rescheduled — {$svc_name} on {$appt_date} at {$appt_time}.";
                    if ( $note ) $note_text .= " {$note}";
                    HearMed_DB::insert( 'patient_notes', [
                        'patient_id' => $patient_id,
                        'note_type'  => 'Appointment',
                        'note_text'  => $note_text,
                        'created_by' => $staff_user,
                    ]);
                }
                // Create new appointment if date/time provided
                $new_date = sanitize_text_field( $_POST['new_date'] ?? '' );
                $new_time = sanitize_text_field( $_POST['new_time'] ?? '' );
                if ( $new_date && $new_time ) {
                    $dur = (int) ( $appt->duration_minutes ?: 30 );
                    $sp  = explode( ':', $new_time );
                    $em  = intval( $sp[0] ) * 60 + intval( $sp[1] ?? 0 ) + $dur;
                    $et  = sprintf( '%02d:%02d', floor( $em / 60 ), $em % 60 );
                    $new_id = HearMed_DB::insert( $t, [
                        'patient_id'         => $patient_id,
                        'staff_id'           => $staff_id,
                        'clinic_id'          => (int) ( $appt->clinic_id ?? 0 ),
                        'service_id'         => (int) ( $appt->service_id ?? 0 ),
                        'appointment_date'   => $new_date,
                        'start_time'         => $new_time,
                        'end_time'           => $et,
                        'duration_minutes'   => $dur,
                        'appointment_status' => 'Not Confirmed',
                        'location_type'      => $appt->location_type ?? 'Clinic',
                        'referral_source_id' => $appt->referral_source_id ?? null,
                        'sms_reminder_hours' => $appt->sms_reminder_hours ?? null,
                        'sms_reminder_sent'  => false,
                        'notes'              => '',
                        'created_by'         => PortalAuth::staff_id(),
                        'created_at'         => current_time( 'mysql' ),
                        'updated_at'         => current_time( 'mysql' ),
                    ]);
                    $result['new_appointment_id'] = $new_id;
                }
                break;

            case 'Cancelled':
                // Add note to patient file
                if ( $patient_id ) {
                    $note_text = "Appointment cancelled — {$svc_name} on {$appt_date} at {$appt_time}.";
                    if ( $note ) $note_text .= " {$note}";
                    HearMed_DB::insert( 'patient_notes', [
                        'patient_id' => $patient_id,
                        'note_type'  => 'Appointment',
                        'note_text'  => $note_text,
                        'created_by' => $staff_user,
                    ]);
                }
                break;
        }

        wp_send_json_success( $result );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] update_appointment_status error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

function hm_ajax_delete_appointment() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( 'Denied' ); return; }
    try {
    if ( HearMed_Portal::setting( 'require_cancel_reason', '1' ) === '1' ) {
        if ( empty( $_POST['reason'] ) ) { wp_send_json_error( 'Reason required' ); return; }
    }
    $id = intval( $_POST['appointment_id'] );
    HearMed_Portal::log( 'cancelled', 'appointment', $id, [ 'reason' => sanitize_text_field( $_POST['reason'] ?? '' ) ] );
    HearMed_DB::delete( HearMed_Portal::table( 'appointments' ), [ 'id' => $id ] );
    wp_send_json_success();
    } catch ( Throwable $e ) {
        error_log( '[HearMed] delete_appointment error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

// ================================================================
// PURGE APPOINTMENT — permanent delete, no audit trail
// Admin / C-Level / Finance only
// ================================================================
function hm_ajax_purge_appointment() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    // Strict role check — only admin, c-level, finance, hm_admin
    $user  = wp_get_current_user();
    $allowed = array_intersect( [ 'administrator', 'hm_clevel', 'hm_admin', 'hm_finance' ], (array) $user->roles );
    if ( empty( $allowed ) ) {
        wp_send_json_error( 'Permission denied — admin role required' );
        return;
    }

    $id = intval( $_POST['appointment_id'] ?? 0 );
    if ( ! $id ) {
        wp_send_json_error( 'Missing appointment ID' );
        return;
    }

    try {
        // Delete related outcome records first
        HearMed_DB::get_results(
            "DELETE FROM hearmed_core.appointment_outcomes WHERE appointment_id = $1",
            [ $id ]
        );
        // Delete the appointment — no log, no record
        HearMed_DB::delete( HearMed_Portal::table( 'appointments' ), [ 'id' => $id ] );
        wp_send_json_success();
    } catch ( Throwable $e ) {
        error_log( '[HearMed] purge_appointment error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

// ================================================================
// HOLIDAYS — GET, SAVE, DELETE
// ================================================================
function hm_ajax_get_holidays() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    try {
    $dp = intval( $_POST['dispenser_id'] ?? 0 );
    $sql = "SELECT h.*, (s.first_name || ' ' || s.last_name) AS dispenser_name
            FROM hearmed_core.staff_absences h
            LEFT JOIN hearmed_reference.staff s ON h.dispenser_id = s.id";
    $params = [];
    if ( $dp ) {
        $sql .= " WHERE h.dispenser_id = $1";
        $params[] = $dp;
    }
    $sql .= " ORDER BY h.start_date DESC";
    $rows = HearMed_DB::get_results( $sql, $params );
    wp_send_json_success( $rows );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] get_holidays error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

function hm_ajax_save_holiday() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( 'Denied' ); return; }
    try {
    $t  = HearMed_Portal::table( 'staff_absences' );
    $id = intval( $_POST['id'] ?? 0 );
    $d  = [
        'dispenser_id' => intval( $_POST['dispenser_id'] ),
        'reason'       => sanitize_text_field( $_POST['reason'] ),
        'repeats'      => sanitize_text_field( $_POST['repeats']    ?? 'no' ),
        'start_date'   => sanitize_text_field( $_POST['start_date'] ),
        'end_date'     => sanitize_text_field( $_POST['end_date'] ),
        'start_time'   => sanitize_text_field( $_POST['start_time'] ?? '09:00' ),
        'end_time'     => sanitize_text_field( $_POST['end_time']   ?? '17:00' ),
        'updated_at'   => current_time( 'mysql' ),
    ];
    // New exclusion fields (optional — columns may not exist yet)
    $excl_id = intval( $_POST['exclusion_type_id'] ?? 0 );
    if ( $excl_id ) $d['exclusion_type_id'] = $excl_id;
    if ( isset( $_POST['is_full_day'] ) ) $d['is_full_day'] = ( $_POST['is_full_day'] === '1' );
    $rd = sanitize_text_field( $_POST['repeat_days'] ?? '' );
    if ( $rd ) $d['repeat_days'] = $rd;
    $red = sanitize_text_field( $_POST['repeat_end_date'] ?? '' );
    if ( $red ) $d['repeat_end_date'] = $red;
    if ( $id ) {
        HearMed_DB::update( $t, $d, [ 'id' => $id ] );
    } else {
        $d['created_at'] = current_time( 'mysql' );
        $id = HearMed_DB::insert( $t, $d );
    }
    wp_send_json_success( [ 'id' => $id ] );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] save_holiday error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

function hm_ajax_delete_holiday() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( 'Denied' ); return; }
    try {
    HearMed_DB::delete( HearMed_Portal::table( 'staff_absences' ), [ 'id' => intval( $_POST['id'] ) ] );
    wp_send_json_success();
    } catch ( Throwable $e ) {
        error_log( '[HearMed] delete_holiday error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

// ================================================================
// BLOCKOUTS — GET, SAVE, DELETE
// ================================================================
function hm_ajax_get_blockouts() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    try {
    $rows = HearMed_DB::get_results(
        "SELECT b.*, sv.service_name,
                CASE WHEN b.dispenser_id > 0 THEN (s.first_name || ' ' || s.last_name) ELSE 'All' END AS dispenser_name
         FROM hearmed_core.calendar_blockouts b
         LEFT JOIN hearmed_reference.appointment_types sv ON b.service_id = sv.id
         LEFT JOIN hearmed_reference.staff s ON b.dispenser_id = s.id
         ORDER BY b.start_date DESC"
    );
    wp_send_json_success( $rows );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] get_blockouts error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

function hm_ajax_save_blockout() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( 'Denied' ); return; }
    try {
    $t  = HearMed_Portal::table( 'calendar_blockouts' );
    $id = intval( $_POST['id'] ?? 0 );
    $d  = [
        'service_id'   => intval( $_POST['service_id'] ),
        'dispenser_id' => intval( $_POST['dispenser_id'] ?? 0 ),
        'start_date'   => sanitize_text_field( $_POST['start_date'] ),
        'end_date'     => sanitize_text_field( $_POST['end_date'] ),
        'start_time'   => sanitize_text_field( $_POST['start_time'] ?? '09:00' ),
        'end_time'     => sanitize_text_field( $_POST['end_time']   ?? '17:00' ),
        'updated_at'   => current_time( 'mysql' ),
    ];
    if ( $id ) {
        HearMed_DB::update( $t, $d, [ 'id' => $id ] );
    } else {
        $d['created_at'] = current_time( 'mysql' );
        $id = HearMed_DB::insert( $t, $d );
    }
    wp_send_json_success( [ 'id' => $id ] );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] save_blockout error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

function hm_ajax_delete_blockout() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( 'Denied' ); return; }
    try {
    HearMed_DB::delete( HearMed_Portal::table( 'calendar_blockouts' ), [ 'id' => intval( $_POST['id'] ) ] );
    wp_send_json_success();
    } catch ( Throwable $e ) {
        error_log( '[HearMed] delete_blockout error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

// ================================================================
// SERVICES — SAVE, DELETE
// ================================================================
function hm_ajax_save_service() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( 'Denied' ); return; }
    try {
    $id   = intval( $_POST['id'] ?? 0 );
    $name = sanitize_text_field( $_POST['name'] );
    $colour   = sanitize_text_field( $_POST['colour'] ?? '#3B82F6' );
    $dur      = intval( $_POST['duration'] ?? 30 );
    $d = [
        'service_name'         => $name,
        'service_color'        => $colour,
        'colour'               => $colour,
        'text_color'           => sanitize_text_field( $_POST['text_color'] ?? '#FFFFFF' ),
        'duration_minutes'     => $dur,
        'duration'             => $dur,
        'sales_opportunity'    => ( $_POST['sales_opportunity'] ?? 'no' ) === 'yes',
        'income_bearing'       => ( $_POST['income_bearing'] ?? 'no' ) === 'yes',
        'appointment_category' => sanitize_text_field( $_POST['appointment_category'] ?? 'Normal' ),
        'updated_at'           => current_time( 'mysql' ),
    ];
    if ( $id ) {
        HearMed_DB::update( 'appointment_types', $d, [ 'id' => $id ] );
    } else {
        $d['created_at'] = current_time( 'mysql' );
        $d['is_active'] = true;
        $id = HearMed_DB::insert( 'appointment_types', $d );
    }
    wp_send_json_success( [ 'id' => $id ] );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] save_service error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

function hm_ajax_delete_service() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( 'Denied' ); return; }
    try {
    HearMed_DB::update( 'appointment_types', [ 'is_active' => false ], [ 'id' => intval( $_POST['id'] ) ] );
    wp_send_json_success();
    } catch ( Throwable $e ) {
        error_log( '[HearMed] delete_service error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

// ================================================================
// DISPENSER ORDER
// ================================================================
function hm_ajax_save_dispenser_order() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( 'Denied' ); return; }
    try {
    $order = json_decode( stripslashes( $_POST['order'] ), true );
    if ( is_array( $order ) ) {
        foreach ( $order as $i => $did ) {
            HearMed_DB::update( 'staff', [ 'calendar_order' => $i + 1 ], [ 'id' => intval( $did ) ] );
        }
    }
    wp_send_json_success();
    } catch ( Throwable $e ) {
        error_log( '[HearMed] save_dispenser_order error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

// ================================================================
// CALENDAR RENDER FUNCTION (called by router)
// ================================================================

// ================================================================
// EXCLUSION TYPES — GET (for calendar modal)
// ================================================================
function hm_ajax_get_exclusion_types() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    try {
        $check = HearMed_DB::get_var( "SELECT to_regclass('hearmed_reference.exclusion_types')" );
        if ( $check === null ) { wp_send_json_success( [] ); return; }
        $rows = HearMed_DB::get_results(
            "SELECT id, type_name, color, text_color, description
             FROM hearmed_reference.exclusion_types
             WHERE is_active = true
             ORDER BY sort_order, type_name"
        );
        wp_send_json_success( $rows ?: [] );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] get_exclusion_types error: ' . $e->getMessage() );
        wp_send_json_success( [] ); // graceful fallback
    }
}

// ================================================================
// CREATE PATIENT (calendar quick-add proxy)
// ================================================================
function hm_ajax_create_patient_from_calendar() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( 'Denied' ); return; }
    try {
        $fn = sanitize_text_field( $_POST['first_name'] ?? '' );
        $ln = sanitize_text_field( $_POST['last_name']  ?? '' );
        if ( ! $fn || ! $ln ) { wp_send_json_error( [ 'message' => 'First and last name are required.' ] ); return; }

        // ── Duplicate patient check: same name + DOB + address ──
        $dob_check  = sanitize_text_field( $_POST['dob'] ?? '' );
        $addr_check = sanitize_textarea_field( $_POST['patient_address'] ?? '' );
        $dup_params = [ $fn, $ln ];
        $dup_sql = "SELECT id FROM hearmed_core.patients
                     WHERE LOWER(TRIM(first_name)) = LOWER(TRIM(\$1))
                       AND LOWER(TRIM(last_name))  = LOWER(TRIM(\$2))";
        if ( $dob_check ) {
            $dup_sql .= " AND date_of_birth = \$" . ( count( $dup_params ) + 1 );
            $dup_params[] = $dob_check;
        }
        if ( $addr_check ) {
            $dup_sql .= " AND LOWER(TRIM(COALESCE(address_line1,''))) = LOWER(TRIM(\$" . ( count( $dup_params ) + 1 ) . "))";
            $dup_params[] = $addr_check;
        }
        $dup_sql .= " LIMIT 1";
        $existing = HearMed_DB::get_row( $dup_sql, $dup_params );
        if ( $existing ) {
            wp_send_json_error( [ 'message' => 'A patient with the same name' . ( $dob_check ? ', date of birth' : '' ) . ( $addr_check ? ' and address' : '' ) . ' already exists (ID: ' . $existing->id . '). Please check for duplicates.' ] );
            return;
        }

        // Generate patient number
        $data = [
            'patient_number' => 'P' . str_pad( mt_rand( 1, 999999 ), 6, '0', STR_PAD_LEFT ),
            'first_name'     => $fn,
            'last_name'      => $ln,
            'is_active'      => true,
            'created_at'     => current_time( 'mysql' ),
            'marketing_email' => ( $_POST['marketing_email'] ?? '0' ) === '1',
            'marketing_sms'   => ( $_POST['marketing_sms'] ?? '0' ) === '1',
            'marketing_phone' => ( $_POST['marketing_phone'] ?? '0' ) === '1',
            'gdpr_consent'    => ( $_POST['gdpr_consent'] ?? '0' ) === '1',
        ];

        // Optional fields — only add if provided
        $title = sanitize_text_field( $_POST['patient_title'] ?? '' );
        $dob   = sanitize_text_field( $_POST['dob'] ?? '' );
        $phone = sanitize_text_field( $_POST['patient_phone'] ?? '' );
        $mobile = sanitize_text_field( $_POST['patient_mobile'] ?? '' );
        $email = sanitize_email( $_POST['patient_email'] ?? '' );
        $addr  = sanitize_textarea_field( $_POST['patient_address'] ?? '' );
        $eir   = sanitize_text_field( $_POST['patient_eircode'] ?? '' );
        $pps   = sanitize_text_field( $_POST['pps_number'] ?? '' );
        $ref   = intval( $_POST['referral_source_id'] ?? 0 );
        $disp  = intval( $_POST['assigned_dispenser_id'] ?? 0 );
        $clinic = intval( $_POST['assigned_clinic_id'] ?? 0 );

        if ( $title )  $data['patient_title'] = $title;
        if ( $dob )    $data['date_of_birth'] = $dob;
        if ( $phone )  $data['phone']         = $phone;
        if ( $mobile ) $data['mobile']        = $mobile;
        if ( $email )  $data['email']         = $email;
        if ( $addr )   $data['address_line1'] = $addr;
        if ( $eir )    $data['eircode']       = $eir;
        if ( $pps )    $data['prsi_number']   = $pps;
        if ( $ref )    $data['referral_source_id']     = $ref;
        if ( $disp )   $data['assigned_dispenser_id']  = $disp;
        if ( $clinic ) $data['assigned_clinic_id']     = $clinic;

        // GDPR consent metadata
        if ( ( $_POST['gdpr_consent'] ?? '0' ) === '1' ) {
            $data['gdpr_consent_date']    = date( 'Y-m-d H:i:s' );
            $data['gdpr_consent_version'] = '1.0';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            if ( $ip ) $data['gdpr_consent_ip'] = $ip;
        }

        $id = HearMed_DB::insert( 'hearmed_core.patients', $data );
        if ( ! $id ) {
            $err = HearMed_DB::last_error();
            error_log( '[HearMed] create_patient_from_calendar failed: ' . $err );
            wp_send_json_error( [ 'message' => 'Failed to create patient.' . ( $err ? ' DB: ' . $err : '' ) ] );
            return;
        }
        wp_send_json_success( [ 'id' => $id ] );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] create_patient_from_calendar error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

// ================================================================
// OUTCOME TEMPLATES — GET for a specific service
// ================================================================
function hm_ajax_get_outcome_templates() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    try {
    $to_bool = static function ( $value ): bool {
        if ( is_bool( $value ) ) return $value;
        if ( is_int( $value ) || is_float( $value ) ) return ((int) $value) === 1;
        if ( is_string( $value ) ) {
            $v = strtolower( trim( $value ) );
            return in_array( $v, [ '1', 'true', 't', 'yes', 'y', 'on' ], true );
        }
        return ! empty( $value );
    };

    $sid = intval( $_POST['service_id'] ?? 0 );
    if ( ! $sid ) { wp_send_json_success( [] ); return; }

    // ── Auto-migrate: ensure triggers_order / triggers_invoice columns exist ──
    $has_col = HearMed_DB::get_var(
        "SELECT COUNT(*) FROM information_schema.columns
         WHERE table_schema = 'hearmed_core' AND table_name = 'outcome_templates' AND column_name = 'triggers_order'"
    );
    if ( ! $has_col ) {
        // Add triggers_order
        HearMed_DB::query( "ALTER TABLE hearmed_core.outcome_templates ADD COLUMN IF NOT EXISTS triggers_order BOOLEAN NOT NULL DEFAULT false" );
        // Rename is_invoiceable → triggers_invoice if applicable
        $has_old = HearMed_DB::get_var(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = 'hearmed_core' AND table_name = 'outcome_templates' AND column_name = 'is_invoiceable'"
        );
        $has_new = HearMed_DB::get_var(
            "SELECT COUNT(*) FROM information_schema.columns
             WHERE table_schema = 'hearmed_core' AND table_name = 'outcome_templates' AND column_name = 'triggers_invoice'"
        );
        if ( $has_old && ! $has_new ) {
            HearMed_DB::query( "ALTER TABLE hearmed_core.outcome_templates RENAME COLUMN is_invoiceable TO triggers_invoice" );
        } elseif ( ! $has_new ) {
            HearMed_DB::query( "ALTER TABLE hearmed_core.outcome_templates ADD COLUMN IF NOT EXISTS triggers_invoice BOOLEAN NOT NULL DEFAULT false" );
        }
        error_log( '[HearMed] Auto-migrated outcome_templates: added triggers_order / triggers_invoice columns' );
    }

    $rows = HearMed_DB::get_results(
        "SELECT *
         FROM hearmed_core.outcome_templates
         WHERE service_id = $1
         ORDER BY outcome_name",
        [ $sid ]
    );
    $d = [];
    foreach ( ($rows ?: []) as $r ) {
        $fu_ids = [];
        if ( ! empty( $r->followup_service_ids ) ) {
            $decoded = json_decode( $r->followup_service_ids, true );
            if ( is_array( $decoded ) ) $fu_ids = array_map( 'intval', $decoded );
        }
        $d[] = [
            'id'                  => (int) $r->id,
            'outcome_name'        => $r->outcome_name,
            'outcome_color'       => $r->outcome_color ?: '#6b7280',
            'triggers_order'      => $to_bool( $r->triggers_order ?? false ),
            'triggers_invoice'    => $to_bool( $r->triggers_invoice ?? false ),
            'requires_note'       => $to_bool( $r->requires_note ?? false ),
            'triggers_followup'   => $to_bool( $r->triggers_followup ?? false ),
            'triggers_reminder'   => $to_bool( $r->triggers_reminder ?? false ),
            'followup_service_ids'=> $fu_ids,
        ];
    }
    wp_send_json_success( $d );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] get_outcome_templates error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

// ================================================================
// SAVE APPOINTMENT OUTCOME — sets outcome + banner on appointment
// ================================================================
function hm_ajax_save_appointment_outcome() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( 'Denied' ); return; }
    try {
    $appt_id    = intval( $_POST['appointment_id'] );
    $outcome_id = intval( $_POST['outcome_id'] ?? 0 );
    $note       = sanitize_textarea_field( $_POST['outcome_note'] ?? '' );
    if ( ! $appt_id ) { wp_send_json_error( 'Invalid appointment' ); return; }

    // Get outcome template details
    $ot = null;
    if ( $outcome_id ) {
        try {
            $ot = HearMed_DB::get_row(
                "SELECT outcome_name, outcome_color FROM hearmed_core.outcome_templates WHERE id = $1",
                [ $outcome_id ]
            );
        } catch ( Throwable $ignored ) {}
    }

    // Get appointment details for patient_id and report_category
    $appt_row = HearMed_DB::get_row(
        "SELECT a.patient_id, a.service_id, COALESCE(sv.report_category, '') AS report_category
         FROM hearmed_core.appointments a
         LEFT JOIN hearmed_reference.services sv ON sv.id = a.service_id
         WHERE a.id = $1",
        [ $appt_id ]
    );
    $patient_id      = $appt_row ? (int) $appt_row->patient_id : null;
    $report_category = $appt_row ? ($appt_row->report_category ?: null) : null;

    // Update the appointment — known schema: appointment_status + outcome (varchar)
    $outcome_text = $ot ? $ot->outcome_name : 'Completed';
    $data = [
        'appointment_status' => 'Completed',
        'outcome'            => $outcome_text,
        'updated_at'         => current_time( 'mysql' ),
    ];

    HearMed_DB::update( HearMed_Portal::table( 'appointments' ), $data, [ 'id' => $appt_id ] );

    // Also save to appointment_outcomes table for history
    try {
        $ao_data = [
            'appointment_id'     => $appt_id,
            'patient_id'         => $patient_id,
            'outcome_template_id'=> $outcome_id ?: null,
            'outcome_name'       => $outcome_text,
            'outcome_color'      => $ot ? $ot->outcome_color : '#6b7280',
            'report_category'    => $report_category,
            'notes'              => $note,
            'created_at'         => current_time( 'mysql' ),
            'created_by'         => PortalAuth::staff_id(),
        ];

        // Auto-create table if it doesn't exist
        HearMed_DB::query(
            "CREATE TABLE IF NOT EXISTS hearmed_core.appointment_outcomes (
                id SERIAL PRIMARY KEY,
                appointment_id INT NOT NULL,
                patient_id INT,
                outcome_template_id INT,
                outcome_name VARCHAR(255),
                outcome_color VARCHAR(20),
                report_category VARCHAR(100),
                notes TEXT,
                created_at TIMESTAMP DEFAULT NOW(),
                created_by INT
            )"
        );

        // Ensure new columns exist on older installs
        HearMed_DB::query(
            "ALTER TABLE hearmed_core.appointment_outcomes
                ADD COLUMN IF NOT EXISTS outcome_template_id INT,
                ADD COLUMN IF NOT EXISTS patient_id INT,
                ADD COLUMN IF NOT EXISTS report_category VARCHAR(100)"
        );

        HearMed_DB::insert( 'hearmed_core.appointment_outcomes', $ao_data );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] appointment_outcomes insert failed: ' . $e->getMessage() );
    }

    HearMed_Portal::log( 'outcome_set', 'appointment', $appt_id, [
        'outcome_id'   => $outcome_id,
        'outcome_name' => $outcome_text,
    ] );

    wp_send_json_success( [
        'id'                    => $appt_id,
        'outcome_name'          => $outcome_text,
        'outcome_banner_colour' => $ot ? $ot->outcome_color : '',
    ] );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] save_appointment_outcome error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

// ================================================================
// GET ALL PRODUCTS FOR ORDER FORM — cascading dropdowns
// ================================================================
function hm_ajax_get_order_products() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( 'Denied' ); return; }

    try {
        $db = HearMed_DB::instance();

        // All active products with manufacturer info + range pricing fallback
        $products = $db->get_results(
            "SELECT p.id, p.product_name, p.item_type, p.style, p.tech_level,
                    COALESCE(p.retail_price, hr.price_total::numeric, 0) AS retail_price,
                    p.cost_price, p.vat_category, p.hearing_aid_class,
                    p.power_type, p.product_code,
                    p.bundled_category, p.speaker_length, p.speaker_power,
                    p.dome_type, p.dome_size,
                    m.id AS manufacturer_id, m.name AS manufacturer_name
             FROM hearmed_reference.products p
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = p.manufacturer_id
             LEFT JOIN hearmed_reference.hearmed_range hr ON hr.id = p.hearmed_range_id
             WHERE p.is_active = true
             ORDER BY m.name, p.product_name"
        ) ?: [];

        // All active services (from services table)
        $services = $db->get_results(
            "SELECT id, service_name, retail_price AS default_price
             FROM hearmed_reference.services
             WHERE is_active = true
             ORDER BY service_name"
        ) ?: [];

        // HearMed ranges for PRSI pricing
        $ranges = $db->get_results(
            "SELECT id, range_name, price_total::numeric AS price_total, price_ex_prsi::numeric AS price_ex_prsi
             FROM hearmed_reference.hearmed_range
             WHERE COALESCE(is_active::text,'true') NOT IN ('false','f','0')
             ORDER BY range_name"
        ) ?: [];

        wp_send_json_success([
            'products' => $products,
            'services' => $services,
            'ranges'   => $ranges,
        ]);
    } catch ( Throwable $e ) {
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

// ================================================================
// RECORD PAYMENT ON ORDER — deposit or full payment from calendar
// ================================================================
function hm_ajax_record_order_payment() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( 'Denied' ); return; }

    try {
        $order_id       = intval( $_POST['order_id'] ?? 0 );
        $order_number   = sanitize_text_field( $_POST['order_number'] ?? '' );
        $amount         = floatval( $_POST['amount'] ?? 0 );
        $payment_method = sanitize_text_field( $_POST['payment_method'] ?? '' );
        $split_raw      = wp_unslash( $_POST['split_payments_json'] ?? '[]' );
        $split_payments = json_decode( $split_raw, true );

        $debug = [
            'received_order_id'      => $order_id,
            'received_order_number'  => $order_number,
            'used_lookup'            => '',
        ];

        if ( ! $order_id && ! $order_number ) { wp_send_json_error( [ 'message' => 'No order specified.' ] ); return; }
        if ( $amount <= 0 ) { wp_send_json_error( [ 'message' => 'Invalid amount.' ] ); return; }
        if ( ! $payment_method && empty( $split_payments ) ) { wp_send_json_error( [ 'message' => 'Select a payment method.' ] ); return; }

        $db = HearMed_DB::instance();

        $staff_id = PortalAuth::staff_id();

        $order = null;
        if ( $order_id > 0 ) {
            $debug['used_lookup'] = 'id';
            $order = $db->get_row(
                "SELECT o.id, o.order_number, o.patient_id, o.clinic_id, o.staff_id, o.invoice_id,
                    o.current_status, o.received_date,
                        o.subtotal, o.discount_total, o.vat_total,
                        o.grand_total,
                        (COALESCE(o.grand_total, 0) - COALESCE(o.deposit_amount, 0)) AS balance_due,
                        o.prsi_applicable, o.prsi_amount,
                        o.deposit_amount
                 FROM hearmed_core.orders o WHERE o.id = \$1",
                [ $order_id ]
            );
        }

        if ( ! $order && $order_number ) {
            $debug['used_lookup'] = $debug['used_lookup'] ? ( $debug['used_lookup'] . '+number_exact' ) : 'number_exact';
            $order = $db->get_row(
                "SELECT o.id, o.order_number, o.patient_id, o.clinic_id, o.staff_id, o.invoice_id,
                    o.current_status, o.received_date,
                        o.subtotal, o.discount_total, o.vat_total,
                        o.grand_total,
                        (COALESCE(o.grand_total, 0) - COALESCE(o.deposit_amount, 0)) AS balance_due,
                        o.prsi_applicable, o.prsi_amount,
                        o.deposit_amount
                 FROM hearmed_core.orders o
                 WHERE UPPER(TRIM(COALESCE(o.order_number, ''))) = UPPER(TRIM(\$1))
                 ORDER BY o.id DESC
                 LIMIT 1",
                [ $order_number ]
            );
            if ( $order ) {
                $order_id = intval( $order->id );
            }
        }

        if ( ! $order && $order_number ) {
            $debug['used_lookup'] = $debug['used_lookup'] ? ( $debug['used_lookup'] . '+number_normalized' ) : 'number_normalized';
            $order = $db->get_row(
                "SELECT o.id, o.order_number, o.patient_id, o.clinic_id, o.staff_id, o.invoice_id,
                    o.current_status, o.received_date,
                        o.subtotal, o.discount_total, o.vat_total,
                        o.grand_total,
                        (COALESCE(o.grand_total, 0) - COALESCE(o.deposit_amount, 0)) AS balance_due,
                        o.prsi_applicable, o.prsi_amount,
                        o.deposit_amount
                 FROM hearmed_core.orders o
                 WHERE UPPER(regexp_replace(COALESCE(o.order_number, ''), '[^A-Za-z0-9]', '', 'g'))
                     = UPPER(regexp_replace(\$1, '[^A-Za-z0-9]', '', 'g'))
                 ORDER BY o.id DESC
                 LIMIT 1",
                [ $order_number ]
            );
            if ( $order ) {
                $order_id = intval( $order->id );
            }
        }

        if ( ! $order ) {
            $debug['recent_orders'] = $db->get_results(
                "SELECT id, order_number
                   FROM hearmed_core.orders
                  ORDER BY id DESC
                  LIMIT 5"
            ) ?: [];
            wp_send_json_error( [ 'message' => 'Order not found.', 'debug' => $debug ] );
            return;
        }

        // ── Serial number / received-in-branch gate ─────────────────────
        // Block payment on ANY non-complete/non-cancelled order that has
        // hearing-aid products without serial numbers entered.
        $current_status_norm = strtolower( trim( (string) ( $order->current_status ?? '' ) ) );
        if ( ! in_array( $current_status_norm, [ 'complete', 'cancelled' ], true ) ) {
            $serial_items = $db->get_results(
                "SELECT oi.id AS order_item_id,
                        oi.item_id AS product_id,
                        COALESCE(oi.item_description, p.product_name, 'Product') AS item_description,
                        COALESCE(NULLIF(TRIM(oi.ear_side), ''), 'Unknown') AS ear_side
                   FROM hearmed_core.order_items oi
                   LEFT JOIN hearmed_reference.products p ON p.id = oi.item_id
                  WHERE oi.order_id = \$1
                    AND oi.item_type = 'product'
                  ORDER BY oi.line_number, oi.id",
                [ $order_id ]
            ) ?: [];

            $missing = [];
            foreach ( $serial_items as $it ) {
                $ear = strtolower( trim( (string) ( $it->ear_side ?? '' ) ) );
                $check = $db->get_row(
                    "SELECT COALESCE(TRIM(serial_number_left), '') AS serial_left,
                            COALESCE(TRIM(serial_number_right), '') AS serial_right
                       FROM hearmed_core.patient_devices
                      WHERE patient_id = \$1
                        AND product_id = \$2
                      ORDER BY id DESC
                      LIMIT 1",
                    [ intval( $order->patient_id ), intval( $it->product_id ?? 0 ) ]
                );
                $left_ok = ! empty( $check->serial_left );
                $right_ok = ! empty( $check->serial_right );

                $is_missing = false;
                if ( $ear === 'left' ) {
                    $is_missing = ! $left_ok;
                } elseif ( $ear === 'right' ) {
                    $is_missing = ! $right_ok;
                } elseif ( $ear === 'binaural' ) {
                    $is_missing = ! ( $left_ok && $right_ok );
                } else {
                    $is_missing = ! ( $left_ok || $right_ok );
                }

                if ( $is_missing ) {
                    $missing[] = [
                        'product_id'      => intval( $it->product_id ?? 0 ),
                        'item_description'=> (string) ( $it->item_description ?? 'Product' ),
                        'ear_side'        => (string) ( $it->ear_side ?? 'Unknown' ),
                    ];
                }
            }

            // Block if hearing aids not received or serials missing
            if ( ! empty( $missing ) || ( ! empty( $serial_items ) && empty( $order->received_date ) ) ) {
                $status_label = ucwords( $current_status_norm );
                wp_send_json_error( [
                    'code'          => 'serials_required',
                    'message'       => 'Hearing aids have not been received in branch and/or serial numbers are missing. Enter delivery date and serial numbers before payment. (Order status: ' . $status_label . ')',
                    'received_date' => date( 'Y-m-d' ),
                    'serial_items'  => $missing,
                ] );
                return;
            }
        }

        $has_balance_remaining = (bool) $db->get_var(
            "SELECT 1
               FROM information_schema.columns
              WHERE table_schema = 'hearmed_core'
                AND table_name = 'invoices'
                AND column_name = 'balance_remaining'
              LIMIT 1"
        );

        $invoice_select_sql = $has_balance_remaining
            ? "SELECT id, invoice_number, grand_total, balance_remaining
                 FROM hearmed_core.invoices WHERE id = \$1"
            : "SELECT id, invoice_number, grand_total, grand_total AS balance_remaining
                 FROM hearmed_core.invoices WHERE id = \$1";

        // Ensure invoice exists and is linked to order
        $invoice = null;
        if ( ! empty( $order->invoice_id ) ) {
            $invoice = $db->get_row( $invoice_select_sql, [ intval( $order->invoice_id ) ] );
        }

        if ( ! $invoice ) {
            $inv_id = false;
            if ( class_exists( 'HearMed_Invoice' ) && method_exists( 'HearMed_Invoice', 'ensure_invoice_for_order' ) ) {
                $inv_id = HearMed_Invoice::ensure_invoice_for_order( $order_id, $staff_id ?: null );
            }

            if ( ! $inv_id ) {
                HearMed_DB::rollback();
                wp_send_json_error( [ 'message' => 'Failed to create invoice. ' . HearMed_DB::last_error() ] );
                return;
            }

            $invoice = $db->get_row( $invoice_select_sql, [ $inv_id ] );
        }

        if ( ! $invoice ) {
            wp_send_json_error( [ 'message' => 'Invoice could not be loaded after creation. ' . HearMed_DB::last_error() ] );
            return;
        }

        // Update deposit on order
        $new_deposit = floatval( $order->deposit_amount ?? 0 ) + $amount;
        $db->update( 'hearmed_core.orders', [
            'deposit_amount'  => $new_deposit,
            'deposit_method'  => $payment_method,
            'deposit_paid_at' => date( 'Y-m-d H:i:s' ),
        ], [ 'id' => $order_id ] );

        $payment_rows = [];
        if ( is_array( $split_payments ) && ! empty( $split_payments ) ) {
            foreach ( $split_payments as $sp ) {
                $sp_method = sanitize_text_field( $sp['method'] ?? '' );
                $sp_amount = floatval( $sp['amount'] ?? 0 );
                if ( $sp_method && $sp_amount > 0 ) {
                    $payment_rows[] = [
                        'invoice_id'     => intval( $invoice->id ),
                        'patient_id'     => $order->patient_id,
                        'amount'         => $sp_amount,
                        'payment_date'   => date( 'Y-m-d' ),
                        'payment_method' => $sp_method,
                        'received_by'    => $staff_id ?: null,
                        'clinic_id'      => $order->clinic_id,
                        'created_by'     => $staff_id ?: null,
                    ];
                }
            }
        }
        if ( empty( $payment_rows ) ) {
            $payment_rows[] = [
                'invoice_id'     => intval( $invoice->id ),
                'patient_id'     => $order->patient_id,
                'amount'         => $amount,
                'payment_date'   => date( 'Y-m-d' ),
                'payment_method' => $payment_method,
                'received_by'    => $staff_id ?: null,
                'clinic_id'      => $order->clinic_id,
                'created_by'     => $staff_id ?: null,
            ];
        }

        $sum = 0.0;
        foreach ( $payment_rows as $row ) {
            $sum += floatval( $row['amount'] );
        }
        if ( abs( $sum - $amount ) > 0.01 ) {
            wp_send_json_error( [ 'message' => 'Split payment amounts must equal the entered amount.' ] );
            return;
        }

        foreach ( $payment_rows as $row ) {
            $pay_id = $db->insert( 'hearmed_core.payments', $row );
            if ( ! $pay_id ) {
                wp_send_json_error( [ 'message' => 'Payment failed to save. ' . HearMed_DB::last_error() ] );
                return;
            }
        }

        $new_balance = max( 0, floatval( $invoice->balance_remaining ?? $invoice->grand_total ?? $order->grand_total ) - $amount );
        $payment_status = $new_balance <= 0 ? 'Paid' : 'Partial';
        if ( $has_balance_remaining ) {
            $db->query(
                "UPDATE hearmed_core.invoices
                    SET balance_remaining = \$1,
                        payment_status = \$2,
                        updated_at = NOW()
                  WHERE id = \$3",
                [ $new_balance, $payment_status, intval( $invoice->id ) ]
            );
        } else {
            $db->query(
                "UPDATE hearmed_core.invoices
                    SET payment_status = \$1,
                        updated_at = NOW()
                  WHERE id = \$2",
                [ $payment_status, intval( $invoice->id ) ]
            );
        }

        if ( $new_balance <= 0 ) {
            $db->query(
                "UPDATE hearmed_core.orders
                    SET current_status = 'Complete',
                        fitted_date = NOW(),
                        updated_at = NOW()
                  WHERE id = \$1",
                [ $order_id ]
            );

            $db->query(
                "UPDATE hearmed_core.fitting_queue
                    SET queue_status = 'Fitted',
                        fitted_date = NOW(),
                        fitted_by = \$1,
                        updated_at = NOW()
                  WHERE order_id = \$2",
                [ $staff_id ?: null, $order_id ]
            );

            $db->query(
                "INSERT INTO hearmed_core.order_status_history (order_id, from_status, to_status, changed_by, changed_at, notes)
                 SELECT \$1, \$2, 'Complete', \$3, NOW(), \$4
                 WHERE EXISTS (
                     SELECT 1 FROM information_schema.tables
                     WHERE table_schema = 'hearmed_core' AND table_name = 'order_status_history'
                 )",
                [ $order_id, (string) ( $order->current_status ?? '' ), $staff_id ?: null, 'Closed from calendar payment flow — paid in full' ]
            );
        }

        // Log in patient timeline
        $db->insert( 'hearmed_core.patient_timeline', [
            'patient_id'  => $order->patient_id,
            'event_type'  => 'payment_received',
            'event_date'  => date( 'Y-m-d' ),
            'staff_id'    => $staff_id ?: null,
            'description' => 'Payment of €' . number_format( $amount, 2 ) . ' received via ' . $payment_method,
            'order_id'    => $order_id,
        ]);

        $balance = max( 0, floatval( $order->grand_total ) - $new_deposit );

        wp_send_json_success([
            'message' => 'Payment of €' . number_format( $amount, 2 ) . ' recorded.',
            'balance' => $balance,
            'invoice_id' => intval( $invoice->id ),
            'invoice_number' => $invoice->invoice_number,
        ]);
    } catch ( Throwable $e ) {
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

// ================================================================
// SAVE ORDER SERIALS FROM CALENDAR PAYMENT FLOW
// ================================================================
function hm_ajax_save_order_serials_from_payment() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( [ 'message' => 'Denied' ] ); return; }

    try {
        $db = HearMed_DB::instance();
        $order_id = intval( $_POST['order_id'] ?? 0 );
        $received_date = sanitize_text_field( $_POST['received_date'] ?? '' );
        $serials_raw = wp_unslash( $_POST['serials_json'] ?? '[]' );
        $serials = json_decode( $serials_raw, true );

        if ( ! $order_id ) { wp_send_json_error( [ 'message' => 'Missing order.' ] ); return; }
        if ( ! is_array( $serials ) || empty( $serials ) ) { wp_send_json_error( [ 'message' => 'No serials provided.' ] ); return; }

        $order = $db->get_row(
            "SELECT id, patient_id, clinic_id, staff_id, current_status, received_date
               FROM hearmed_core.orders WHERE id = \$1",
            [ $order_id ]
        );
        if ( ! $order ) { wp_send_json_error( [ 'message' => 'Order not found.' ] ); return; }

        $wp_uid = PortalAuth::staff_id();
        $staff_id = $wp_uid;

        $by_product = [];
        foreach ( $serials as $entry ) {
            $product_id = intval( $entry['product_id'] ?? 0 );
            $ear = strtolower( trim( (string) ( $entry['ear'] ?? '' ) ) );
            $serial = sanitize_text_field( $entry['serial'] ?? '' );
            if ( ! $product_id || ! $serial || ! in_array( $ear, [ 'left', 'right' ], true ) ) {
                continue;
            }
            if ( ! isset( $by_product[ $product_id ] ) ) {
                $by_product[ $product_id ] = [ 'left' => '', 'right' => '' ];
            }
            $by_product[ $product_id ][ $ear ] = $serial;
        }

        if ( empty( $by_product ) ) {
            wp_send_json_error( [ 'message' => 'No valid serial entries.' ] );
            return;
        }

        foreach ( $by_product as $product_id => $vals ) {
            // ── Serial uniqueness check ──
            foreach ( [ 'left', 'right' ] as $side ) {
                $sn = trim( (string) ( $vals[ $side ] ?? '' ) );
                if ( $sn ) {
                    $dup = $db->get_var(
                        "SELECT id FROM hearmed_core.patient_devices
                         WHERE (serial_number_left = \$1 OR serial_number_right = \$1)
                           AND NOT (patient_id = \$2 AND product_id = \$3)",
                        [ $sn, intval( $order->patient_id ), intval( $product_id ) ]
                    );
                    if ( $dup ) {
                        wp_send_json_error( [ 'message' => 'Serial number \"' . $sn . '\" (' . ucfirst( $side ) . ') is already assigned to another device (device #' . $dup . ').' ] );
                        return;
                    }
                }
            }

            $existing = $db->get_row(
                "SELECT id, serial_number_left, serial_number_right
                   FROM hearmed_core.patient_devices
                  WHERE patient_id = \$1 AND product_id = \$2
                  ORDER BY id DESC
                  LIMIT 1",
                [ intval( $order->patient_id ), intval( $product_id ) ]
            );

            $left = $vals['left'] ?: (string) ( $existing->serial_number_left ?? '' );
            $right = $vals['right'] ?: (string) ( $existing->serial_number_right ?? '' );

            if ( $existing ) {
                $db->update( 'hearmed_core.patient_devices', [
                    'serial_number_left'  => $left,
                    'serial_number_right' => $right,
                    'order_id'            => $order_id,
                ], [ 'id' => intval( $existing->id ) ] );
            } else {
                $db->insert( 'hearmed_core.patient_devices', [
                    'patient_id'          => intval( $order->patient_id ),
                    'product_id'          => intval( $product_id ),
                    'order_id'            => $order_id,
                    'serial_number_left'  => $left,
                    'serial_number_right' => $right,
                    'device_status'       => 'Active',
                    'created_by'          => $staff_id ?: null,
                ] );
            }
        }

        if ( $received_date ) {
            // Advance order status to Awaiting Fitting regardless of current pre-complete status
            $db->query(
                "UPDATE hearmed_core.orders
                    SET received_date = COALESCE(received_date, \$1),
                        received_by = COALESCE(received_by, \$2),
                        arrived_at = COALESCE(arrived_at, \$1),
                        serials_at = COALESCE(serials_at, \$1),
                        current_status = CASE
                            WHEN current_status IN ('Approved','Ordered','Received') THEN 'Awaiting Fitting'
                            ELSE current_status
                        END,
                        updated_at = NOW()
                  WHERE id = \$3",
                [ $received_date . ' ' . date( 'H:i:s' ), $staff_id ?: null, $order_id ]
            );

            // Ensure fitting_queue entry exists
            $fq = $db->get_row( "SELECT id FROM hearmed_core.fitting_queue WHERE order_id = \$1", [ $order_id ] );
            if ( ! $fq ) {
                $db->insert( 'hearmed_core.fitting_queue', [
                    'patient_id'   => intval( $order->patient_id ),
                    'order_id'     => $order_id,
                    'queue_status' => 'Awaiting',
                    'created_by'   => $staff_id ?: null,
                ] );
            }
        }

        wp_send_json_success( [ 'message' => 'Serials saved.' ] );
    } catch ( Throwable $e ) {
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

// ================================================================
// CREATE PRSI FORM REMINDER — notification to dispenser
// ================================================================
function hm_ajax_create_prsi_form_reminder() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( 'Denied' ); return; }

    try {
        $order_id     = intval( $_POST['order_id'] ?? 0 );
        $order_number = sanitize_text_field( $_POST['order_number'] ?? '' );
        $dispenser_id = intval( $_POST['dispenser_id'] ?? 0 );
        $patient_name = sanitize_text_field( $_POST['patient_name'] ?? 'Patient' );

        if ( ! $dispenser_id ) {
            wp_send_json_error( [ 'message' => 'No dispenser specified.' ] );
            return;
        }

        $subject = 'PRSI form not yet received — ' . $patient_name . ' (' . $order_number . ')';
        $message = 'The signed PRSI grant form has not been received from ' . $patient_name
                 . ' for order ' . $order_number . '. Please follow up with the patient to collect the signed form.';

        if ( class_exists( 'HM_Notifications' ) ) {
            $notif_id = HM_Notifications::create( $dispenser_id, 'reminder', [
                'subject'        => $subject,
                'message'        => $message,
                'priority'       => 'Normal',
                'entity_type'    => 'order',
                'entity_id'      => $order_id,
            ] );
        } else {
            // Fallback: direct insert
            $db = HearMed_DB::instance();
            $staff_id = 0;
            if ( function_exists( 'hm_notif_staff_id' ) ) {
                $staff_id = hm_notif_staff_id();
            }
            $notif_id = $db->insert( 'hearmed_communication.internal_notifications', [
                'notification_type'   => 'reminder',
                'subject'             => $subject,
                'message'             => $message,
                'created_by'          => $staff_id ?: null,
                'priority'            => 'Normal',
                'related_entity_type' => 'order',
                'related_entity_id'   => $order_id,
                'is_active'           => true,
            ] );
            if ( $notif_id ) {
                $db->insert( 'hearmed_communication.notification_recipients', [
                    'notification_id' => $notif_id,
                    'recipient_type'  => 'staff',
                    'recipient_id'    => $dispenser_id,
                    'is_read'         => false,
                ] );
            }
        }

        wp_send_json_success( [ 'notification_id' => $notif_id ?? 0 ] );
    } catch ( Throwable $e ) {
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

// ================================================================
// GET PATIENT ORDERS — for income-bearing appointment order picker
// ================================================================
function hm_ajax_get_patient_pipeline_orders() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( 'Denied' ); return; }

    try {
        $patient_id = intval( $_POST['patient_id'] ?? 0 );
        $appointment_id = intval( $_POST['appointment_id'] ?? 0 );
        if ( ! $patient_id ) { wp_send_json_error( [ 'message' => 'No patient specified.' ] ); return; }

        $db = HearMed_DB::instance();

        $debug = [
            'input_patient_id'      => $patient_id,
            'input_appointment_id'  => $appointment_id,
            'appointment_order_id'  => 0,
            'candidate_patient_ids' => [],
            'linked_order_ids'      => [],
            'matched_order_ids'     => [],
            'matched_count'         => 0,
        ];

        if ( $appointment_id > 0 ) {
            $debug['appointment_order_id'] = intval(
                $db->get_var(
                    "SELECT COALESCE(order_id, 0) FROM hearmed_core.appointments WHERE id = \$1",
                    [ $appointment_id ]
                ) ?: 0
            );
        }

        $base_select =
            "SELECT o.id, o.order_number, o.order_date, o.current_status, o.grand_total,
                    o.deposit_amount, o.prsi_amount, o.discount_total,
                    COALESCE(o.grand_total, 0) - COALESCE(o.deposit_amount, 0) AS balance_due,
                (SELECT string_agg(COALESCE(oi.item_description, oi.item_type || ' #' || oi.item_id::text), ', ' ORDER BY oi.id)
                     FROM hearmed_core.order_items oi WHERE oi.order_id = o.id) AS items_summary
             FROM hearmed_core.orders o";

        // Build candidate patient IDs to survive duplicate/misaligned patient records.
        $candidate_patient_ids = [ $patient_id ];

        $patient = $db->get_row(
            "SELECT first_name, last_name, patient_number
               FROM hearmed_core.patients
              WHERE id = \$1
              LIMIT 1",
            [ $patient_id ]
        );

        if ( $patient ) {
            if ( ! empty( $patient->patient_number ) ) {
                $same_number = $db->get_results(
                    "SELECT id FROM hearmed_core.patients WHERE patient_number = \$1",
                    [ $patient->patient_number ]
                );
                foreach ( $same_number ?: [] as $row ) {
                    $candidate_patient_ids[] = intval( $row->id );
                }
            }

            if ( ! empty( $patient->first_name ) && ! empty( $patient->last_name ) ) {
                $same_name = $db->get_results(
                    "SELECT id
                       FROM hearmed_core.patients
                      WHERE LOWER(TRIM(COALESCE(first_name, ''))) = LOWER(TRIM(\$1))
                        AND LOWER(TRIM(COALESCE(last_name,  ''))) = LOWER(TRIM(\$2))",
                    [ $patient->first_name, $patient->last_name ]
                );
                foreach ( $same_name ?: [] as $row ) {
                    $candidate_patient_ids[] = intval( $row->id );
                }
            }
        }

        $candidate_patient_ids = array_values( array_unique( array_filter( array_map( 'intval', $candidate_patient_ids ) ) ) );
        if ( empty( $candidate_patient_ids ) ) {
            $candidate_patient_ids = [ $patient_id ];
        }

        $debug['candidate_patient_ids'] = $candidate_patient_ids;

        $patient_ids_sql = implode( ',', $candidate_patient_ids );

        $linked_order_ids = [];

        $appt_linked_rows = $db->get_results(
            "SELECT DISTINCT order_id
               FROM hearmed_core.appointments
              WHERE patient_id IN ({$patient_ids_sql})
                AND order_id IS NOT NULL"
        );
        foreach ( $appt_linked_rows ?: [] as $row ) {
            $linked_order_ids[] = intval( $row->order_id ?? 0 );
        }

        $invoice_linked_rows = $db->get_results(
            "SELECT DISTINCT order_id
               FROM hearmed_core.invoices
              WHERE patient_id IN ({$patient_ids_sql})
                AND order_id IS NOT NULL"
        );
        foreach ( $invoice_linked_rows ?: [] as $row ) {
            $linked_order_ids[] = intval( $row->order_id ?? 0 );
        }

        $timeline_linked_rows = $db->get_results(
            "SELECT DISTINCT order_id
               FROM hearmed_core.patient_timeline
              WHERE patient_id IN ({$patient_ids_sql})
                AND order_id IS NOT NULL"
        );
        foreach ( $timeline_linked_rows ?: [] as $row ) {
            $linked_order_ids[] = intval( $row->order_id ?? 0 );
        }

        if ( $debug['appointment_order_id'] > 0 ) {
            $linked_order_ids[] = intval( $debug['appointment_order_id'] );
        }

        $linked_order_ids = array_values( array_unique( array_filter( array_map( 'intval', $linked_order_ids ) ) ) );
        $debug['linked_order_ids'] = $linked_order_ids;

        $where_parts = [ "o.patient_id IN ({$patient_ids_sql})" ];
        if ( ! empty( $linked_order_ids ) ) {
            $where_parts[] = 'o.id IN (' . implode( ',', $linked_order_ids ) . ')';
        }
        $where_sql = implode( ' OR ', $where_parts );

        $rows = $db->get_results(
            $base_select .
            " WHERE ({$where_sql})
                                AND LOWER(TRIM(COALESCE(o.current_status, ''))) NOT IN ('cancelled', 'awaiting approval', 'complete', 'closed')
              ORDER BY o.order_date DESC"
        );

        $list = [];
        foreach ( ( $rows ?: [] ) as $r ) {
            $list[] = [
                'id'            => (int) $r->id,
                'order_number'  => $r->order_number,
                'order_date'    => $r->order_date,
                'status'        => $r->current_status,
                'grand_total'   => (float) $r->grand_total,
                'deposit'       => (float) ( $r->deposit_amount ?? 0 ),
                'balance_due'   => (float) max( 0, $r->balance_due ?? $r->grand_total ),
                'items_summary' => $r->items_summary ?: '—',
                'has_prsi'      => (float) ( $r->prsi_amount ?? 0 ) > 0,
            ];
        }

        $debug['matched_order_ids'] = array_values( array_map( static function( $row ) {
            return intval( $row->id ?? 0 );
        }, ( $rows ?: [] ) ) );
        $debug['matched_count'] = count( $list );

        wp_send_json_success( [
            'orders' => $list,
            'debug'  => $debug,
        ] );
    } catch ( Throwable $e ) {
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

// ================================================================
// CREATE ORDER FROM OUTCOME — lightweight order creation from calendar
// ================================================================
function hm_ajax_create_outcome_order() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( 'Denied' ); return; }
    try {
        $patient_id     = intval( $_POST['patient_id'] ?? 0 );
        $appointment_id = intval( $_POST['appointment_id'] ?? 0 );
        $notes          = sanitize_textarea_field( $_POST['notes'] ?? '' );
        $items          = json_decode( stripslashes( $_POST['items_json'] ?? '[]' ), true );
        $prsi_left      = !empty( $_POST['prsi_left'] );
        $prsi_right     = !empty( $_POST['prsi_right'] );
        $discount_pct   = floatval( $_POST['discount_pct'] ?? 0 );
        $payment_method = sanitize_text_field( $_POST['payment_method'] ?? '' );

        if ( ! $patient_id ) { wp_send_json_error( [ 'message' => 'No patient specified.' ] ); return; }
        if ( empty( $items ) ) { wp_send_json_error( [ 'message' => 'No items added.' ] ); return; }

        $db = HearMed_DB::instance();

        // Get clinic from appointment or current user
        $clinic_id = null;
        if ( $appointment_id ) {
            $appt = $db->get_row(
                "SELECT clinic_id FROM hearmed_core.appointments WHERE id = \$1", [$appointment_id]
            );
            $clinic_id = $appt ? $appt->clinic_id : null;
        }

        // Calculate totals
        $subtotal = 0;
        $vat_total = 0;
        $line_num = 1;
        $order_items_data = [];
        foreach ( $items as $item ) {
            $qty        = intval( $item['qty'] ?? 1 );
            $unit_price = floatval( $item['unit_price'] ?? $item['price'] ?? 0 );
            $vat_rate   = floatval( $item['vat_rate'] ?? 0 );
            $gross      = $unit_price * $qty;
            // Prices are VAT-inclusive — extract VAT from gross
            $vat        = $vat_rate > 0 ? round( $gross - ( $gross / ( 1 + $vat_rate / 100 ) ), 2 ) : 0;
            $net        = $gross - $vat;
            $subtotal  += $net;
            $vat_total += $vat;

            $order_items_data[] = [
                'line_number'       => $line_num++,
                'item_type'         => sanitize_key( $item['type'] ?? 'product' ),
                'item_id'           => intval( $item['id'] ?? 0 ),
                'item_description'  => sanitize_text_field( $item['name'] ?? '' ),
                'ear_side'          => sanitize_text_field( $item['ear'] ?? '' ),
                'quantity'          => $qty,
                'unit_retail_price' => round( $net / max( $qty, 1 ), 2 ),
                'vat_rate'          => $vat_rate,
                'vat_amount'        => $vat,
                'line_total'        => $net + $vat,
            ];
        }

        // Apply discount (% or fixed €)
        $discount_total = 0;
        $discount_euro = floatval( $_POST['discount_euro'] ?? 0 );
        if ( $discount_euro > 0 ) {
            $discount_total = min( $discount_euro, $subtotal + $vat_total );
        } elseif ( $discount_pct > 0 && $discount_pct <= 100 ) {
            $discount_total = round( $subtotal * ( $discount_pct / 100 ), 2 );
        }

        // PRSI
        $prsi_applicable = $prsi_left || $prsi_right;
        $prsi_amount     = ( $prsi_left ? 500 : 0 ) + ( $prsi_right ? 500 : 0 );

        $grand_total = max( 0, $subtotal + $vat_total - $discount_total - $prsi_amount );

        // ── Quickpay detection: service-only orders bypass approval ──
        $is_quickpay = ! empty( $items );
        foreach ( $items as $item ) {
            if ( ( $item['type'] ?? '' ) !== 'service' ) {
                $is_quickpay = false;
                break;
            }
        }
        $initial_status = $is_quickpay ? 'Complete' : 'Awaiting Approval';

        $order_num = HearMed_Utils::generate_order_number();

        // Resolve staff table ID
        $staff_id = PortalAuth::staff_id();

        $order_id = $db->insert( 'hearmed_core.orders', [
            'order_number'    => $order_num,
            'patient_id'      => $patient_id,
            'staff_id'        => $staff_id,
            'clinic_id'       => $clinic_id,
            'order_date'      => date( 'Y-m-d' ),
            'current_status'  => $initial_status,
            'subtotal'        => $subtotal,
            'discount_total'  => $discount_total,
            'vat_total'       => $vat_total,
            'grand_total'     => $grand_total,
            'prsi_applicable' => $prsi_applicable,
            'prsi_amount'     => $prsi_amount,
            'prsi_left'       => $prsi_left,
            'prsi_right'      => $prsi_right,
            'payment_method'  => $payment_method,
            'notes'           => $notes,
            'created_at'      => current_time( 'mysql' ),
            'created_by'      => $staff_id ?: null,
            'fitted_date'     => $is_quickpay ? date( 'Y-m-d H:i:s' ) : null,
        ] );

        if ( ! $order_id ) {
            wp_send_json_error( [ 'message' => 'Failed to create order. ' . HearMed_DB::last_error() ] );
            return;
        }

        // Insert line items
        foreach ( $order_items_data as $oi ) {
            try {
                $oi['order_id'] = $order_id;
                $db->insert( 'hearmed_core.order_items', $oi );
            } catch ( Throwable $ignored ) {}
        }

        // Link order to appointment
        if ( $appointment_id ) {
            try {
                $db->query(
                    "UPDATE hearmed_core.appointments SET order_id = \$1, updated_at = NOW() WHERE id = \$2",
                    [ $order_id, $appointment_id ]
                );
            } catch ( Throwable $ignored ) {}
        }

        // Log in patient timeline
        try {
            $db->insert( 'hearmed_core.patient_timeline', [
                'patient_id'  => $patient_id,
                'event_type'  => 'order_created',
                'event_date'  => date( 'Y-m-d' ),
                'staff_id'    => $staff_id,
                'description' => 'Order ' . $order_num . ' created. Total: €' . number_format( $grand_total, 2 ),
                'order_id'    => $order_id,
            ]);
        } catch ( Throwable $ignored ) {}

        // Status history
        try {
            $db->insert( 'hearmed_core.order_status_history', [
                'order_id'   => $order_id,
                'from_status'=> null,
                'to_status'  => $initial_status,
                'changed_by' => $staff_id,
                'notes'      => $is_quickpay
                    ? 'Service quickpay — approval not required'
                    : 'Order created from appointment outcome',
            ]);
        } catch ( Throwable $ignored ) {}

        // ── Quickpay: auto-create invoice for service-only orders ──
        $invoice_id = null;
        if ( $is_quickpay && class_exists( 'HearMed_Invoice' ) ) {
            try {
                $invoice_id = HearMed_Invoice::ensure_invoice_for_order( $order_id, $staff_id ?: null );
            } catch ( Throwable $e ) {
                error_log( '[HearMed] Quickpay auto-invoice failed: ' . $e->getMessage() );
            }
        }

        wp_send_json_success( [
            'order_id'     => $order_id,
            'order_number' => $order_num,
            'grand_total'  => $grand_total,
            'is_quickpay'  => $is_quickpay,
            'invoice_id'   => $invoice_id,
        ] );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] create_outcome_order error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

// ================================================================
// EXCLUSION INSTANCES — save / delete / get
// ================================================================

function hm_ajax_save_exclusion() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( 'Denied' ); return; }

    try {
        $t = 'hearmed_core.exclusion_instances';

        // Ensure table exists
        $exists = HearMed_DB::get_var( "SELECT to_regclass('{$t}')" );
        if ( $exists === null ) {
            wp_send_json_error( [ 'message' => 'Exclusion instances table does not exist. Please run the migration.' ] );
            return;
        }

        $id = intval( $_POST['id'] ?? 0 );

        $scope = sanitize_text_field( $_POST['scope'] ?? 'full_day' );
        $repeat_type = sanitize_text_field( $_POST['repeat_type'] ?? 'none' );

        $d = [
            'exclusion_type_id' => intval( $_POST['exclusion_type_id'] ?? 0 ),
            'staff_id'          => intval( $_POST['staff_id'] ?? 0 ) ?: null,
            'scope'             => $scope,
            'start_date'        => sanitize_text_field( $_POST['start_date'] ?? '' ),
            'end_date'          => sanitize_text_field( $_POST['end_date'] ?? $_POST['start_date'] ?? '' ),
            'start_time'        => $scope === 'custom_hours' ? sanitize_text_field( $_POST['start_time'] ?? null ) : null,
            'end_time'          => $scope === 'custom_hours' ? sanitize_text_field( $_POST['end_time'] ?? null ) : null,
            'reason'            => sanitize_text_field( $_POST['reason'] ?? '' ),
            'repeat_type'       => $repeat_type,
            'repeat_days'       => sanitize_text_field( $_POST['repeat_days'] ?? '' ) ?: null,
            'repeat_until'      => sanitize_text_field( $_POST['repeat_until'] ?? '' ) ?: null,
            'updated_at'        => current_time( 'mysql' ),
        ];

        if ( ! $d['start_date'] ) {
            wp_send_json_error( [ 'message' => 'Start date is required.' ] );
            return;
        }

        if ( $id ) {
            HearMed_DB::update( $t, $d, [ 'id' => $id ] );
        } else {
            $user = wp_get_current_user();
            $d['created_by'] = $user->ID;
            $d['created_at'] = current_time( 'mysql' );
            $id = HearMed_DB::insert( $t, $d );
        }

        wp_send_json_success( [ 'id' => $id ] );

    } catch ( Throwable $e ) {
        error_log( '[HearMed] save_exclusion error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

function hm_ajax_delete_exclusion() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! PortalAuth::is_logged_in() ) { wp_send_json_error( 'Denied' ); return; }

    try {
        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) { wp_send_json_error( 'Invalid ID' ); return; }

        HearMed_DB::update(
            'hearmed_core.exclusion_instances',
            [ 'is_active' => false, 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $id ]
        );

        wp_send_json_success();

    } catch ( Throwable $e ) {
        error_log( '[HearMed] delete_exclusion error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

function hm_ajax_get_exclusions() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    try {
        $t = 'hearmed_core.exclusion_instances';
        $exists = HearMed_DB::get_var( "SELECT to_regclass('{$t}')" );
        if ( $exists === null ) { wp_send_json_success( [] ); return; }

        $range_start = sanitize_text_field( $_POST['start_date'] ?? $_GET['start_date'] ?? '' );
        $range_end   = sanitize_text_field( $_POST['end_date']   ?? $_GET['end_date']   ?? '' );

        if ( ! $range_start || ! $range_end ) {
            wp_send_json_error( 'start_date and end_date are required' );
            return;
        }

        // Fetch non-repeating exclusions that overlap the date range
        $rows = HearMed_DB::get_results( HearMed_DB::prepare(
            "SELECT ei.id, ei.exclusion_type_id, et.type_name, et.color, et.text_color,
                    ei.staff_id, ei.scope, ei.start_date, ei.end_date,
                    ei.start_time, ei.end_time, ei.reason, ei.repeat_type, ei.repeat_days, ei.repeat_until
             FROM {$t} ei
             LEFT JOIN hearmed_reference.exclusion_types et ON et.id = ei.exclusion_type_id
             WHERE ei.is_active = true
               AND (
                   (ei.repeat_type = 'none' AND ei.start_date <= %s AND ei.end_date >= %s)
                   OR ei.repeat_type != 'none'
               )",
            $range_end, $range_start
        ) );

        $results = [];

        // Get dispensers for staff_name lookup
        $dispensers = HearMed_DB::get_results(
            "SELECT id, name FROM hearmed_core.dispensers WHERE is_active = true"
        ) ?: [];
        $disp_map = [];
        foreach ( $dispensers as $dd ) {
            $disp_map[ $dd->id ] = $dd->name;
        }

        $range_s = new DateTime( $range_start );
        $range_e = new DateTime( $range_end );

        foreach ( $rows ?: [] as $row ) {
            $r = (array) $row;
            $r['staff_name'] = $r['staff_id'] ? ( $disp_map[ $r['staff_id'] ] ?? 'Unknown' ) : 'All';

            if ( $r['repeat_type'] === 'none' ) {
                $results[] = $r;
            } else {
                // Expand recurring exclusions into virtual instances
                $excl_start = new DateTime( $r['start_date'] );
                $excl_end   = ! empty( $r['repeat_until'] ) ? new DateTime( $r['repeat_until'] ) : clone $range_e;

                // Clamp to visible range
                $iter_start = max( $range_s, $excl_start );
                $iter_end   = min( $range_e, $excl_end );

                $repeat_days_arr = [];
                if ( $r['repeat_type'] === 'days' && ! empty( $r['repeat_days'] ) ) {
                    $repeat_days_arr = array_map( 'intval', explode( ',', $r['repeat_days'] ) );
                }

                $current = clone $iter_start;
                while ( $current <= $iter_end ) {
                    $dow = (int) $current->format( 'w' ); // 0=Sun, 1=Mon, ...

                    $include = false;
                    if ( $r['repeat_type'] === 'days' ) {
                        $include = in_array( $dow, $repeat_days_arr, true );
                    } elseif ( $r['repeat_type'] === 'indefinite' || $r['repeat_type'] === 'until_date' ) {
                        // For indefinite/until_date without specific days, include every day
                        $include = true;
                    }

                    if ( $include ) {
                        $virtual = $r;
                        $virtual['start_date'] = $current->format( 'Y-m-d' );
                        $virtual['end_date']   = $current->format( 'Y-m-d' );
                        $virtual['_virtual']   = true;
                        $results[] = $virtual;
                    }

                    $current->modify( '+1 day' );
                }
            }
        }

        wp_send_json_success( $results );

    } catch ( Throwable $e ) {
        error_log( '[HearMed] get_exclusions error: ' . $e->getMessage() );
        wp_send_json_success( [] ); // graceful fallback
    }
}

function hm_calendar_render() {
    ?>
    <script>
    /* Ensure data-view is set — Elementor shortcode widgets may strip data attributes from the router wrapper */
    (function(){
        var app = document.getElementById('hm-app');
        if(app) {
            if(!app.getAttribute('data-view'))  app.setAttribute('data-view',  'calendar');
            if(!app.getAttribute('data-module'))app.setAttribute('data-module', 'calendar');
            if(!app.classList.contains('hm-calendar')) app.classList.add('hm-calendar');
        }
    })();
    </script>
    <div class="hm-content">
        <div class="hm-page-header">
            <h1 class="hm-page-title">Calendar</h1>
        </div>
        <div id="hm-calendar-container"></div>
    </div>
    <?php
}