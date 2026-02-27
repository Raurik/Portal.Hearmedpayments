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
            wp_send_json_success( (array) $row );
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
            'outcome_style', 'enabled_days', 'calendar_order', 'appointment_statuses',
            'appt_bg_color', 'appt_font_color', 'appt_badge_color', 'appt_badge_font_color', 'appt_meta_color',
            'card_style', 'banner_style', 'banner_size', 'indicator_color', 'today_highlight_color',
            'grid_line_color', 'cal_bg_color', 'working_days',
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
    // Use COALESCE to support both old (colour/duration) and new (service_color/duration_minutes) column names
    $ps = HearMed_DB::get_results(
        "SELECT id, service_name,
                COALESCE(service_color, colour, '#3B82F6') AS service_color,
                COALESCE(text_color, '#FFFFFF') AS text_color,
                COALESCE(duration_minutes, duration, 30) AS duration_minutes,
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
        "SELECT id, patient_number, first_name, last_name, phone, mobile, email
         FROM hearmed_core.patients
         WHERE is_active = true
           AND (first_name ILIKE $1 OR last_name ILIKE $2
                OR (first_name || ' ' || last_name) ILIKE $3
                OR patient_number ILIKE $4
                OR phone ILIKE $5 OR mobile ILIKE $6)
         ORDER BY last_name, first_name
         LIMIT 20",
        [ $search_term, $search_term, $search_term, $search_term, $search_term, $search_term ]
    );

    $d = [];
    if ( $results ) {
        foreach ( $results as $p ) {
            $d[] = [
                'id'             => (int) $p->id,
                'name'           => "{$p->first_name} {$p->last_name}",
                'label'          => ( $p->patient_number ? "{$p->patient_number} — " : '' ) . "{$p->first_name} {$p->last_name}",
                'phone'          => $p->phone ?? $p->mobile ?? '',
                'patient_number' => $p->patient_number ?? '',
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

    // Build query with JOINs — fully PostgreSQL, no get_post_meta
    // Real column names: staff_id, appointment_type_id, appointment_status
    $sql = "SELECT a.id, a.patient_id, a.staff_id, a.clinic_id, a.appointment_type_id,
                   a.appointment_date, a.start_time, a.end_time,
                   a.appointment_status, a.location_type, a.notes,
                   a.created_by,
                   p.first_name AS patient_first, p.last_name AS patient_last, p.patient_number,
                   (st.first_name || ' ' || st.last_name) AS dispenser_name,
                   c.clinic_name,
                   sv.service_name,
                   COALESCE(sv.service_color, sv.colour, '#3B82F6') AS service_colour,
                   COALESCE(sv.duration_minutes, sv.duration, 30) AS service_duration
            FROM hearmed_core.appointments a
            LEFT JOIN hearmed_core.patients p ON a.patient_id = p.id
            LEFT JOIN hearmed_reference.staff st ON a.staff_id = st.id
            LEFT JOIN hearmed_reference.clinics c ON a.clinic_id = c.id
            LEFT JOIN hearmed_reference.services sv ON sv.id = a.appointment_type_id
            WHERE a.appointment_date >= $1 AND a.appointment_date <= $2";
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
    $d = [];

    // Try to get outcome data — columns may not exist on appointments table yet
    $has_outcomes = false;
    try {
        $test = HearMed_DB::get_var( "SELECT column_name FROM information_schema.columns WHERE table_schema = 'hearmed_core' AND table_name = 'appointments' AND column_name = 'outcome_id'" );
        $has_outcomes = !empty( $test );
    } catch ( Throwable $ignored ) {}

    // Build outcome lookup if we have the columns
    $outcome_map = [];
    if ( $has_outcomes && !empty( $rows ) ) {
        // Get outcome data for all appointments that have outcome_id set
        try {
            $outcome_rows = HearMed_DB::get_results(
                "SELECT a.id AS appt_id, a.outcome_id, a.outcome_banner_colour,
                        ot.outcome_name, ot.outcome_color
                 FROM hearmed_core.appointments a
                 LEFT JOIN hearmed_core.outcome_templates ot ON a.outcome_id = ot.id
                 WHERE a.outcome_id IS NOT NULL AND a.outcome_id > 0
                   AND a.appointment_date >= $1 AND a.appointment_date <= $2",
                [ $start, $end ]
            );
            foreach ( ($outcome_rows ?: []) as $or ) {
                $outcome_map[(int)$or->appt_id] = $or;
            }
        } catch ( Throwable $ignored ) {}
    }

    foreach ( $rows as $r ) {
        $pname = 'Walk-in';
        if ( $r->patient_first && $r->patient_last ) {
            $pname = $r->patient_first . ' ' . $r->patient_last;
        }
        $om = $outcome_map[(int)$r->id] ?? null;
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
            'service_id'            => (int) ($r->appointment_type_id ?: 0),
            'service_name'          => $r->service_name ?? '',
            'service_colour'        => $r->service_colour ?: '#3B82F6',
            'appointment_date'      => $r->appointment_date,
            'start_time'            => $r->start_time,
            'end_time'              => $r->end_time ?? '',
            'duration'              => (int) ($r->service_duration ?: 30),
            'status'                => $r->appointment_status ?: 'Confirmed',
            'location_type'         => $r->location_type ?? 'Clinic',
            'notes'                 => $r->notes ?? '',
            'outcome_id'            => $om ? (int) $om->outcome_id : 0,
            'outcome_name'          => $om ? ($om->outcome_name ?? '') : '',
            'outcome_banner_colour' => $om ? ($om->outcome_banner_colour ?: ($om->outcome_color ?? '')) : '',
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
    // Get duration from PostgreSQL services table
    $dur = intval( $_POST['duration'] ?? 0 );
    if ( ! $dur && $sid ) {
        $svc_dur = HearMed_DB::get_var( "SELECT COALESCE(duration_minutes, duration, 30) FROM hearmed_reference.services WHERE id = $1", [ $sid ] );
        $dur = intval( $svc_dur ) ?: 30;
    }
    if ( ! $dur ) $dur = 30;
    $st  = sanitize_text_field( $_POST['start_time'] );
    $sp  = explode( ':', $st );
    $em  = intval( $sp[0] ) * 60 + intval( $sp[1] ) + $dur;
    $et  = sprintf( '%02d:%02d', floor( $em / 60 ), $em % 60 );
    $new_id = HearMed_DB::insert( $t, [
        'created_at'          => current_time( 'mysql' ),
        'patient_id'          => intval( $_POST['patient_id']   ?? 0 ),
        'staff_id'            => intval( $_POST['dispenser_id'] ),
        'clinic_id'           => intval( $_POST['clinic_id']    ?? 0 ),
        'appointment_type_id' => $sid,
        'appointment_date'    => sanitize_text_field( $_POST['appointment_date'] ),
        'start_time'          => $st,
        'end_time'            => $et,
        'appointment_status'  => sanitize_text_field( $_POST['status'] ?? 'Confirmed' ),
        'location_type'       => sanitize_text_field( $_POST['location_type'] ?? 'Clinic' ),
        'notes'               => sanitize_textarea_field( $_POST['notes']     ?? '' ),
        'created_by'          => get_current_user_id(),
    ] );
    HearMed_Portal::log( 'created', 'appointment', $new_id ?: 0, [
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

    // Status update (from popover buttons or edit modal)
    if ( isset( $_POST['status'] ) ) {
        $data['appointment_status'] = sanitize_text_field( $_POST['status'] );
    }

    // Full edit fields — only set if actually sent
    if ( isset( $_POST['appointment_date'] ) ) $data['appointment_date'] = sanitize_text_field( $_POST['appointment_date'] );
    if ( isset( $_POST['location_type'] ) )    $data['location_type'] = sanitize_text_field( $_POST['location_type'] );
    if ( isset( $_POST['notes'] ) )            $data['notes'] = sanitize_textarea_field( $_POST['notes'] );
    if ( isset( $_POST['patient_id'] ) )       $data['patient_id'] = intval( $_POST['patient_id'] );
    if ( isset( $_POST['dispenser_id'] ) )     $data['staff_id'] = intval( $_POST['dispenser_id'] );
    if ( isset( $_POST['clinic_id'] ) )        $data['clinic_id'] = intval( $_POST['clinic_id'] );

    // Service + duration + time recalculation (only when start_time or service changes)
    if ( isset( $_POST['start_time'] ) ) {
        $sid = intval( $_POST['service_id'] ?? 0 );
        // If no service_id sent, look up from existing appointment
        if ( ! $sid ) {
            $sid = (int) HearMed_DB::get_var( "SELECT appointment_type_id FROM hearmed_core.appointments WHERE id = $1", [ $id ] );
        }
        $dur = intval( $_POST['duration'] ?? 0 );
        if ( ! $dur && $sid ) {
            $svc_dur = HearMed_DB::get_var( "SELECT COALESCE(duration_minutes, duration, 30) FROM hearmed_reference.services WHERE id = $1", [ $sid ] );
            $dur = intval( $svc_dur ) ?: 30;
        }
        if ( ! $dur ) $dur = 30;
        $st  = sanitize_text_field( $_POST['start_time'] );
        $sp  = explode( ':', $st );
        $em  = intval( $sp[0] ) * 60 + intval( $sp[1] ) + $dur;
        $et  = sprintf( '%02d:%02d', floor( $em / 60 ), $em % 60 );
        $data['appointment_type_id'] = $sid;
        $data['start_time']  = $st;
        $data['end_time']    = $et;
    }

    // outcome_id / outcome_banner_colour — only if columns exist
    if ( isset( $_POST['outcome_id'] ) || isset( $_POST['outcome_banner_colour'] ) ) {
        try {
            $has_oid = HearMed_DB::get_var(
                "SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='appointments' AND column_name='outcome_id'"
            );
            if ( $has_oid ) {
                if ( isset( $_POST['outcome_id'] ) )            $data['outcome_id']            = intval( $_POST['outcome_id'] );
                if ( isset( $_POST['outcome_banner_colour'] ) ) $data['outcome_banner_colour'] = sanitize_text_field( $_POST['outcome_banner_colour'] );
            }
        } catch ( Throwable $ignored ) {}
    }
    HearMed_DB::update( $t, $data, [ 'id' => $id ] );
    HearMed_Portal::log( 'updated', 'appointment', $id );
    wp_send_json_success( [ 'id' => $id ] );
    } catch ( Throwable $e ) {
        error_log( '[HearMed] update_appointment error: ' . $e->getMessage() );
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
        $ot = HearMed_DB::get_row(
            "SELECT outcome_name, outcome_color FROM hearmed_core.outcome_templates WHERE id = $1",
            [ $outcome_id ]
        );
    }

    // Update the appointment with outcome
    $data = [
        'appointment_status' => 'Completed',
        'updated_at'         => current_time( 'mysql' ),
    ];
    // outcome_id / outcome_banner_colour columns may not exist yet — add safely
    try {
        $has_oid = HearMed_DB::get_var(
            "SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='appointments' AND column_name='outcome_id'"
        );
        if ( $has_oid ) {
            $data['outcome_id']            = $outcome_id;
            $data['outcome_banner_colour'] = $ot ? $ot->outcome_color : '';
        }
    } catch ( Throwable $ignored ) {}
    HearMed_DB::update( HearMed_Portal::table( 'appointments' ), $data, [ 'id' => $appt_id ] );

    // Also save to appointment_outcomes table for history
    $ao_data = [
        'appointment_id' => $appt_id,
        'outcome_name'   => $ot ? $ot->outcome_name : 'Completed',
        'outcome_color'  => $ot ? $ot->outcome_color : '#6b7280',
        'notes'          => $note,
        'created_at'     => current_time( 'mysql' ),
        'created_by'     => get_current_user_id(),
    ];
    // Try insert into appointment_outcomes (table may not exist yet)
    try {
        HearMed_DB::insert( 'hearmed_core.appointment_outcomes', $ao_data );
    } catch ( Throwable $ignored ) {
        // Table might not exist — that's OK, appointment is still updated
    }

    HearMed_Portal::log( 'outcome_set', 'appointment', $appt_id, [
        'outcome_id'   => $outcome_id,
        'outcome_name' => $ot ? $ot->outcome_name : 'Completed',
    ] );

    wp_send_json_success( [
        'id'                    => $appt_id,
        'outcome_name'          => $ot ? $ot->outcome_name : 'Completed',
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