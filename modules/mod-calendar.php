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
add_action( 'wp_ajax_hm_create_patient',       'hm_ajax_create_patient_from_calendar' );
add_action( 'wp_ajax_hm_get_outcome_templates', 'hm_ajax_get_outcome_templates' );
add_action( 'wp_ajax_hm_save_appointment_outcome', 'hm_ajax_save_appointment_outcome' );
add_action( 'wp_ajax_hm_update_appointment_status', 'hm_ajax_update_appointment_status' );

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
    if ( ! current_user_can( 'edit_posts' ) ) { 
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
        ];

        // JSONB fields — must be stored as valid JSON strings, not sanitized as text
        $json_fields = [
            'enabled_days', 'calendar_order', 'appointment_statuses', 'working_days',
            'status_badge_colours',
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
            } else {
                $data[ $f ] = false;
            }
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
    $scheduled_ids = [];
    if ( $clinic_id && $date ) {
        $schedule_table = 'hearmed_reference.dispenser_schedules';
        $has_schedule_table = HearMed_DB::get_var( HearMed_DB::prepare( "SELECT to_regclass(%s)", $schedule_table ) ) !== null;
        if ( ! $has_schedule_table ) {
            $date = '';
        }
    }
    if ( $clinic_id && $date ) {
        $ts = strtotime( $date );
        if ( $ts ) {
            $day = (int) date( 'w', $ts );
            $week = (int) date( 'W', $ts );
            $week_index = ( $week % 2 ) ? 1 : 2;
            $scheduled = HearMed_DB::get_results(
                "SELECT staff_id
                 FROM hearmed_reference.dispenser_schedules
                 WHERE clinic_id = $1
                   AND day_of_week = $2
                   AND is_active = true
                   AND (rotation_weeks = 1 OR (rotation_weeks = 2 AND week_number = $3))",
                [ $clinic_id, $day, $week_index ]
            );
            if ( $scheduled ) {
                foreach ( $scheduled as $row ) {
                    $scheduled_ids[] = (int) $row->staff_id;
                }
            }
        }
    }
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
    if ( ! empty( $scheduled_ids ) ) {
        $sql .= " AND s.id IN (" . implode( ',', array_map( 'intval', $scheduled_ids ) ) . ")";
    }
    $sql .= " GROUP BY s.id, s.first_name, s.last_name, s.role, s.is_active, s.staff_color ORDER BY s.first_name, s.last_name";
    $ps = HearMed_DB::get_results( $sql );
    $d = [];
    foreach ( $ps as $p ) {
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
        $d[] = [
            'id'             => (int) $p->id,
            'name'           => $fname,
            'initials'       => $initials,
            'clinic_id'      => $clinic_id ?: ( ! empty( $cids ) ? $cids[0] : null ),
            'calendar_order' => 99,
            'role_type'      => $p->role,
            'color'          => $p->staff_color ?: '#0BB4C4',
            'staff_color'    => $p->staff_color ?: '#0BB4C4',
        ];
    }
    wp_send_json_success( $d );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] get_dispensers error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}
function hm_ajax_get_services() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    try {
    // Check if tint_opacity column exists (added by MIGRATION_SERVICE_CARD_COLOURS)
    $has_tint = (bool) HearMed_DB::get_var(
        "SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_reference' AND table_name='services' AND column_name='tint_opacity'"
    );
    $tint_col = $has_tint ? "COALESCE(tint_opacity, 12) AS tint_opacity," : "12 AS tint_opacity,";
    $ps = HearMed_DB::get_results(
        "SELECT id, service_name,
                COALESCE(service_color, '#3B82F6') AS service_color,
                COALESCE(text_color, '#FFFFFF') AS text_color,
                COALESCE(duration_minutes, 30) AS duration_minutes,
                {$tint_col}
                is_active
         FROM hearmed_reference.services WHERE is_active = true ORDER BY service_name"
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
                p.referral_source_id, COALESCE(rs.source_name, '') AS referral_source_name
         FROM hearmed_core.patients p
         LEFT JOIN hearmed_reference.referral_sources rs ON rs.id = p.referral_source_id
         WHERE p.is_active = true
           AND (p.first_name ILIKE $1 OR p.last_name ILIKE $2
                OR (p.first_name || ' ' || p.last_name) ILIKE $3
                OR p.patient_number ILIKE $4
                OR p.phone ILIKE $5 OR p.mobile ILIKE $6)
         ORDER BY p.last_name, p.first_name
         LIMIT 20",
        [ $search_term, $search_term, $search_term, $search_term, $search_term, $search_term ]
    );

    $d = [];
    if ( $results ) {
        foreach ( $results as $p ) {
            $d[] = [
                'id'                    => (int) $p->id,
                'name'                  => "{$p->first_name} {$p->last_name}",
                'label'                 => ( $p->patient_number ? "{$p->patient_number} — " : '' ) . "{$p->first_name} {$p->last_name}",
                'phone'                 => $p->phone ?? $p->mobile ?? '',
                'patient_number'        => $p->patient_number ?? '',
                'referral_source_id'    => (int) ($p->referral_source_id ?? 0),
                'referral_source_name'  => $p->referral_source_name ?? '',
            ];
        }
    }
    wp_send_json_success( $d );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] search_patients error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
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
                   p.first_name AS patient_first, p.last_name AS patient_last, p.patient_number,
                   (st.first_name || ' ' || st.last_name) AS dispenser_name,
                   c.clinic_name,
                   sv.service_name,
                   COALESCE(sv.service_color, '#3B82F6') AS service_colour,
                   COALESCE(sv.text_color, '#FFFFFF') AS service_text_color,
                   COALESCE(a.duration_minutes, sv.duration_minutes, 30) AS service_duration
            FROM hearmed_core.appointments a
            LEFT JOIN hearmed_core.patients p ON a.patient_id = p.id
            LEFT JOIN hearmed_reference.staff st ON a.staff_id = st.id
            LEFT JOIN hearmed_reference.clinics c ON a.clinic_id = c.id
            LEFT JOIN hearmed_reference.services sv ON sv.id = a.service_id
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
            'outcome_name'          => $r->outcome ?? '',
            'outcome_banner_colour' => '',
            'created_by'            => (int) ($r->created_by ?? 0),
        ];
    }
    wp_send_json_success( $d );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] get_appointments error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

function hm_ajax_create_appointment() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
    try {
    $t   = HearMed_Portal::table( 'appointments' );
    $sid = intval( $_POST['service_id'] );

    // Get duration: from POST (user override) or from services table default
    $dur = intval( $_POST['duration'] ?? 0 );
    if ( ! $dur && $sid ) {
        $svc_dur = HearMed_DB::get_var(
            "SELECT COALESCE(duration_minutes, 30) FROM hearmed_reference.services WHERE id = $1", [ $sid ]
        );
        $dur = intval( $svc_dur ) ?: 30;
    }
    if ( ! $dur ) $dur = 30;

    // Calculate end_time from start_time + duration
    $st  = sanitize_text_field( $_POST['start_time'] );
    $sp  = explode( ':', $st );
    $em  = intval( $sp[0] ) * 60 + intval( $sp[1] ) + $dur;
    $et  = sprintf( '%02d:%02d', floor( $em / 60 ), $em % 60 );

    // Direct insert — known schema columns
    $insert_data = [
        'patient_id'         => intval( $_POST['patient_id'] ?? 0 ),
        'staff_id'           => intval( $_POST['dispenser_id'] ?? 0 ),
        'clinic_id'          => intval( $_POST['clinic_id']  ?? 0 ),
        'service_id'         => $sid,
        'appointment_date'   => sanitize_text_field( $_POST['appointment_date'] ),
        'start_time'         => $st,
        'end_time'           => $et,
        'duration_minutes'   => $dur,
        'appointment_status' => sanitize_text_field( $_POST['status'] ?? 'Not Confirmed' ),
        'location_type'      => sanitize_text_field( $_POST['location_type'] ?? 'Clinic' ),
        'referring_source'   => sanitize_text_field( $_POST['referring_source'] ?? '' ),
        'notes'              => sanitize_textarea_field( $_POST['notes'] ?? '' ),
        'created_by'         => get_current_user_id(),
        'created_at'         => current_time( 'mysql' ),
        'updated_at'         => current_time( 'mysql' ),
    ];

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
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
    try {
    $t   = HearMed_Portal::table( 'appointments' );
    $id  = intval( $_POST['appointment_id'] );
    $data = [ 'updated_at' => current_time( 'mysql' ) ];

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
                "SELECT COALESCE(duration_minutes, 30) FROM hearmed_reference.services WHERE id = $1", [ $sid ]
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
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
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
             LEFT JOIN hearmed_reference.services sv ON sv.id = a.service_id
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
                        'notes'              => '',
                        'created_by'         => get_current_user_id(),
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
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
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
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
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
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
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
         LEFT JOIN hearmed_reference.services sv ON b.service_id = sv.id
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
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
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
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
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
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
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
        HearMed_DB::update( 'services', $d, [ 'id' => $id ] );
    } else {
        $d['created_at'] = current_time( 'mysql' );
        $d['is_active'] = true;
        $id = HearMed_DB::insert( 'services', $d );
    }
    wp_send_json_success( [ 'id' => $id ] );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] save_service error: ' . $e->getMessage() );
        wp_send_json_error( [ 'message' => $e->getMessage() ] );
    }
}

function hm_ajax_delete_service() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
    try {
    HearMed_DB::update( 'services', [ 'is_active' => false ], [ 'id' => intval( $_POST['id'] ) ] );
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
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
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
            "SELECT id, type_name, color, description
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
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
    try {
        $fn = sanitize_text_field( $_POST['first_name'] ?? '' );
        $ln = sanitize_text_field( $_POST['last_name']  ?? '' );
        if ( ! $fn || ! $ln ) { wp_send_json_error( [ 'message' => 'First and last name are required.' ] ); return; }

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
    $sid = intval( $_POST['service_id'] ?? 0 );
    if ( ! $sid ) { wp_send_json_success( [] ); return; }
    $rows = HearMed_DB::get_results(
        "SELECT id, outcome_name, outcome_color, is_invoiceable, requires_note,
                triggers_followup, triggers_reminder
         FROM hearmed_core.outcome_templates
         WHERE service_id = $1
         ORDER BY outcome_name",
        [ $sid ]
    );
    $d = [];
    foreach ( ($rows ?: []) as $r ) {
        $d[] = [
            'id'              => (int) $r->id,
            'outcome_name'    => $r->outcome_name,
            'outcome_color'   => $r->outcome_color ?: '#6b7280',
            'is_invoiceable'  => (bool) $r->is_invoiceable,
            'requires_note'   => (bool) $r->requires_note,
            'triggers_followup'=> (bool) $r->triggers_followup,
            'triggers_reminder'=> (bool) $r->triggers_reminder,
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
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
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

    // Update the appointment — known schema: appointment_status + outcome (varchar)
    $outcome_text = $ot ? $ot->outcome_name : 'Completed';
    $data = [
        'appointment_status' => 'Completed',
        'outcome'            => $outcome_text,
        'updated_at'         => current_time( 'mysql' ),
    ];

    HearMed_DB::update( HearMed_Portal::table( 'appointments' ), $data, [ 'id' => $appt_id ] );

    // Also save to appointment_outcomes table for history (may not exist)
    try {
        HearMed_DB::insert( 'hearmed_core.appointment_outcomes', [
            'appointment_id' => $appt_id,
            'outcome_name'   => $outcome_text,
            'outcome_color'  => $ot ? $ot->outcome_color : '#6b7280',
            'notes'          => $note,
            'created_at'     => current_time( 'mysql' ),
            'created_by'     => get_current_user_id(),
        ] );
    } catch ( Throwable $ignored ) {}

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