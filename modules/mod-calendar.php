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

// ================================================================
// CALENDAR SETTINGS
// ================================================================
function hm_ajax_get_settings() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    try {
        // PostgreSQL only - no $wpdb needed
        $t = HearMed_Portal::table( 'calendar_settings' );
        if ( HearMed_DB::get_var( HearMed_DB::prepare( "SELECT to_regclass(%s)", $t ) ) === null ) { wp_send_json_success( [] ); return; }
        wp_send_json_success( HearMed_DB::get_row( "SELECT * FROM {$t} LIMIT 1", ARRAY_A ) ?: [] );
    } catch ( Throwable $e ) {
        // In debug mode return detailed error to AJAX caller; otherwise generic message
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            wp_send_json_error( [ 'message' => $e->getMessage(), 'trace' => $e->getTraceAsString() ] );
        } else {
            wp_send_json_error( 'Server error' );
        }
    }
}

function hm_ajax_save_settings() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
        // PostgreSQL only - no $wpdb needed
    $t      = HearMed_Portal::table( 'calendar_settings' );
    $fields = [
        'start_time', 'end_time', 'time_interval', 'slot_height', 'default_view', 'default_mode',
        'show_time_inline', 'hide_end_time', 'outcome_style', 'require_cancel_reason',
        'hide_cancelled', 'require_reschedule_note', 'apply_clinic_colour', 'display_full_name',
        'prevent_location_mismatch', 'enabled_days', 'calendar_order', 'appointment_statuses',
        'double_booking_warning', 'show_patient', 'show_service', 'show_initials', 'show_status',
        'appt_bg_color', 'appt_font_color', 'appt_badge_color', 'appt_meta_color',
    ];
    $data = [];
    foreach ( $fields as $f ) {
        if ( isset( $_POST[ $f ] ) ) $data[ $f ] = sanitize_text_field( $_POST[ $f ] );
    }
    $ex = HearMed_DB::get_var( "SELECT id FROM `$t` LIMIT 1" );
    if ( $ex ) HearMed_DB::update( $t, $data, [ 'id' => $ex ] );
    else        HearMed_DB::insert( $t, $data );
    wp_send_json_success();
}

// ================================================================
// CLINICS, DISPENSERS, SERVICES
// ================================================================
function hm_ajax_get_clinics() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
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
            'clinic_colour'  => $p->clinic_color ?: '#0BB4C4',
            'text_colour'    => $extra['text_colour'],
            'is_active'      => (bool) $p->is_active,
        ];
    }
    wp_send_json_success( $d );
}

function hm_ajax_get_dispensers() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
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
    $sql = "SELECT s.id, s.full_name, s.initials, s.role_type, s.is_active, s.user_account,
                   ARRAY_AGG(sc.clinic_id) as clinic_ids
            FROM hearmed_reference.staff s
            LEFT JOIN hearmed_reference.staff_clinics sc ON s.id = sc.staff_id
            WHERE s.is_active = true";
    if ( $clinic_id ) {
        $sql .= " AND (sc.clinic_id = $clinic_id OR sc.clinic_id IS NULL)";
    }
    if ( ! empty( $scheduled_ids ) ) {
        $sql .= " AND s.id IN (" . implode( ',', array_map( 'intval', $scheduled_ids ) ) . ")";
    }
    $sql .= " GROUP BY s.id, s.full_name, s.initials, s.role_type, s.is_active, s.user_account ORDER BY s.full_name";
    $ps = HearMed_DB::get_results( $sql );
    $d = [];
    foreach ( $ps as $p ) {
        if ( !$p->is_active ) continue;
        $d[] = [
            'id'             => (int) $p->id,
            'name'           => $p->full_name,
            'initials'       => $p->initials ?: strtoupper( substr( $p->full_name, 0, 2 ) ),
            'clinic_id'      => $clinic_id ?: ( $p->clinic_ids ? $p->clinic_ids[0] : null ),
            'calendar_order' => 99,
            'user_account'   => $p->user_account,
            'role_type'      => $p->role_type,
        ];
    }
    wp_send_json_success( $d );
}

function hm_ajax_get_services() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $ps = HearMed_DB::get_results(
        "SELECT id, service_name, colour, duration, is_active, sales_opportunity, income_bearing, appointment_category FROM hearmed_reference.services WHERE is_active = true ORDER BY service_name"
    );
    $d = [];
    foreach ( $ps as $p ) {
        $d[] = [
            'id'                   => (int) $p->id,
            'name'                 => $p->service_name,
            'colour'               => $p->colour ?: '#3B82F6',
            'duration'             => (int) ($p->duration ?: 30),
            'sales_opportunity'    => (bool) $p->sales_opportunity,
            'income_bearing'       => (bool) $p->income_bearing,
            'appointment_category' => $p->appointment_category,
            'send_reminders'       => true,
            'send_confirmation'    => true,
        ];
    }
    wp_send_json_success( $d );
}

// ================================================================
// PATIENT SEARCH
// ================================================================
function hm_ajax_search_patients() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $q = sanitize_text_field( $_POST['q'] ?? $_POST['query'] ?? '' );
    if ( strlen( $q ) < 2 ) { wp_send_json_success( [] ); return; }
    
    // PostgreSQL source of truth: hearmed_core.patients
    $search_term = '%' . $q . '%';
    $results = HearMed_DB::get_results(
        "SELECT id, patient_number, first_name, last_name, phone
         FROM hearmed_core.patients
         WHERE first_name ILIKE %s OR last_name ILIKE %s OR patient_number ILIKE %s
         ORDER BY last_name, first_name
         LIMIT 20",
        $search_term, $search_term, $search_term
    );
    
    $d = [];
    foreach ( $results as $p ) {
        $d[] = [
            'id'             => $p->id,
            'name'           => "{$p->first_name} {$p->last_name}",
            'label'          => ( $p->patient_number ? "{$p->patient_number} — " : '' ) . "{$p->first_name} {$p->last_name}",
            'phone'          => $p->phone ?? '',
            'patient_number' => $p->patient_number,
        ];
    }
    wp_send_json_success( $d );
}

// ================================================================
// APPOINTMENTS — GET, CREATE, UPDATE, DELETE
// ================================================================
function hm_ajax_get_appointments() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
        // PostgreSQL only - no $wpdb needed
    $t = HearMed_Portal::table( 'appointments' );
    if ( HearMed_DB::get_var( HearMed_DB::prepare( "SELECT to_regclass(%s)", $t ) ) === null ) { wp_send_json_success( [] ); return; }
    $start = sanitize_text_field( $_POST['start'] ?? date( 'Y-m-d' ) );
    $end   = sanitize_text_field( $_POST['end']   ?? $start );
    $cl    = intval( $_POST['clinic']    ?? 0 );
    $dp    = intval( $_POST['dispenser'] ?? 0 );
    $w     = HearMed_DB::get_results(   "WHERE appointment_date BETWEEN %s AND %s", $start, $end );
    if ( $cl ) $w .= HearMed_DB::get_results(   " AND clinic_id=%d",    $cl );
    if ( $dp ) $w .= HearMed_DB::get_results(   " AND dispenser_id=%d", $dp );
    $rows = HearMed_DB::get_results( "SELECT * FROM `$t` $w ORDER BY appointment_date, start_time" );
    $d = [];
    foreach ( $rows as $r ) {
        $pname = $r->patient_id ? get_the_title( $r->patient_id ) : 'Walk-in';
        if ( $r->patient_id ) {
            $fn = // TODO: USE PostgreSQL: Get from table columns
    get_post_meta( $r->patient_id, 'first_name', true );
            $ln = // TODO: USE PostgreSQL: Get from table columns
    get_post_meta( $r->patient_id, 'last_name',  true );
            if ( $fn && $ln ) $pname = "$fn $ln";
        }
        $d[] = [
            'id'                   => $r->id,
            'patient_id'            => $r->patient_id,
            'patient_name'          => $pname,
            'patient_number'        => $r->patient_id ? // TODO: USE PostgreSQL: Get from table columns
    get_post_meta( $r->patient_id, 'patient_number', true ) : '',
            'dispenser_id'          => $r->dispenser_id,
            'dispenser_name'        => get_the_title( $r->dispenser_id ),
            'clinic_id'             => $r->clinic_id,
            'clinic_name'           => get_the_title( $r->clinic_id ),
            'service_id'            => $r->service_id,
            'service_name'          => get_the_title( $r->service_id ),
            'service_colour'        => // TODO: USE PostgreSQL: Get from table columns
    get_post_meta( $r->service_id, 'colour', true ) ?: // TODO: USE PostgreSQL: Get from table columns
    get_post_meta( $r->service_id, 'service_colour', true ) ?: '#3B82F6',
            'appointment_date'      => $r->appointment_date,
            'start_time'            => $r->start_time,
            'end_time'              => $r->end_time,
            'duration'              => intval( $r->duration ?: // TODO: USE PostgreSQL: Get from table columns
    get_post_meta( $r->service_id, 'duration', true ) ?: 30 ),
            'status'                => $r->status ?: 'Confirmed',
            'location_type'         => $r->location_type ?? 'Clinic',
            'notes'                 => $r->notes ?? '',
            'outcome_id'            => $r->outcome_id ?? 0,
            'outcome_banner_colour' => $r->outcome_banner_colour ?? '',
            'created_by'            => $r->created_by ?? 0,
        ];
    }
    wp_send_json_success( $d );
}

function hm_ajax_create_appointment() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
        // PostgreSQL only - no $wpdb needed
    $t   = HearMed_Portal::table( 'appointments' );
    $sid = intval( $_POST['service_id'] );
    $dur = intval( // TODO: USE PostgreSQL: Get from table columns
    get_post_meta( $sid, 'duration', true ) ) ?: 30;
    $st  = sanitize_text_field( $_POST['start_time'] );
    $sp  = explode( ':', $st );
    $em  = intval( $sp[0] ) * 60 + intval( $sp[1] ) + $dur;
    $et  = sprintf( '%02d:%02d', floor( $em / 60 ), $em % 60 );
    HearMed_DB::insert( $t, [
        'cct_status'       => 'publish',
        'cct_author_id'    => get_current_user_id(),
        'created_at'      => current_time( 'mysql' ),
        'patient_id'       => intval( $_POST['patient_id']   ?? 0 ),
        'dispenser_id'     => intval( $_POST['dispenser_id'] ),
        'clinic_id'        => intval( $_POST['clinic_id']    ?? 0 ),
        'service_id'       => $sid,
        'appointment_date' => sanitize_text_field( $_POST['appointment_date'] ),
        'start_time'       => $st, 'end_time' => $et, 'duration' => $dur,
        'status'           => sanitize_text_field( $_POST['status']        ?? 'Confirmed' ),
        'location_type'    => sanitize_text_field( $_POST['location_type'] ?? 'Clinic' ),
        'notes'            => sanitize_textarea_field( $_POST['notes']     ?? '' ),
        'created_by'       => get_current_user_id(),
    ] );
    $new_id = $last_insert_id /* TODO: Get this from HearMed_DB::insert() return value */;
    HearMed_Portal::log( 'created', 'appointment', $new_id, [
        'patient_id' => intval( $_POST['patient_id'] ?? 0 ),
        'date'       => sanitize_text_field( $_POST['appointment_date'] ),
    ] );
    wp_send_json_success( [ 'id' => $new_id ] );
}

function hm_ajax_update_appointment() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
        // PostgreSQL only - no $wpdb needed
    $t   = HearMed_Portal::table( 'appointments' );
    $id  = intval( $_POST['appointment_id'] );
    $sid = intval( $_POST['service_id'] );
    $dur = intval( // TODO: USE PostgreSQL: Get from table columns
    get_post_meta( $sid, 'duration', true ) ) ?: 30;
    $st  = sanitize_text_field( $_POST['start_time'] );
    $sp  = explode( ':', $st );
    $em  = intval( $sp[0] ) * 60 + intval( $sp[1] ) + $dur;
    $et  = sprintf( '%02d:%02d', floor( $em / 60 ), $em % 60 );
    $data = [
        'updated_at'     => current_time( 'mysql' ),
        'patient_id'       => intval( $_POST['patient_id']   ?? 0 ),
        'dispenser_id'     => intval( $_POST['dispenser_id'] ),
        'clinic_id'        => intval( $_POST['clinic_id']    ?? 0 ),
        'service_id'       => $sid,
        'appointment_date' => sanitize_text_field( $_POST['appointment_date'] ),
        'start_time'       => $st, 'end_time' => $et, 'duration' => $dur,
        'status'           => sanitize_text_field( $_POST['status']        ?? 'Confirmed' ),
        'location_type'    => sanitize_text_field( $_POST['location_type'] ?? 'Clinic' ),
        'notes'            => sanitize_textarea_field( $_POST['notes']     ?? '' ),
    ];
    if ( isset( $_POST['outcome_id'] ) )            $data['outcome_id']            = intval( $_POST['outcome_id'] );
    if ( isset( $_POST['outcome_banner_colour'] ) ) $data['outcome_banner_colour'] = sanitize_text_field( $_POST['outcome_banner_colour'] );
    HearMed_DB::update( $t, $data, [ 'id' => $id ] );
    HearMed_Portal::log( 'updated', 'appointment', $id );
    wp_send_json_success( [ 'id' => $id ] );
}

function hm_ajax_delete_appointment() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
        // PostgreSQL only - no $wpdb needed
    if ( HearMed_Portal::setting( 'require_cancel_reason', '1' ) === '1' ) {
        if ( empty( $_POST['reason'] ) ) { wp_send_json_error( 'Reason required' ); return; }
    }
    $id = intval( $_POST['appointment_id'] );
    HearMed_Portal::log( 'cancelled', 'appointment', $id, [ 'reason' => sanitize_text_field( $_POST['reason'] ?? '' ) ] );
    HearMed_DB::delete( HearMed_Portal::table( 'appointments' ), [ 'id' => $id ] );
    wp_send_json_success();
}

// ================================================================
// HOLIDAYS — GET, SAVE, DELETE
// ================================================================
function hm_ajax_get_holidays() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
        // PostgreSQL only - no $wpdb needed
    $t = HearMed_Portal::table( 'holidays' );
    if ( HearMed_DB::get_var( HearMed_DB::prepare( "SELECT to_regclass(%s)", $t ) ) === null ) { wp_send_json_success( [] ); return; }
    $dp   = intval( $_POST['dispenser_id'] ?? 0 );
    $w    = $dp ? HearMed_DB::get_results(   "WHERE dispenser_id=%d", $dp ) : "";
    $rows = HearMed_DB::get_results( "SELECT * FROM `$t` $w ORDER BY start_date DESC" );
    foreach ( $rows as &$r ) $r->dispenser_name = get_the_title( $r->dispenser_id );
    wp_send_json_success( $rows );
}

function hm_ajax_save_holiday() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
        // PostgreSQL only - no $wpdb needed
    $t  = HearMed_Portal::table( 'holidays' );
    $id = intval( $_POST['id'] ?? 0 );
    $d  = [
        'dispenser_id' => intval( $_POST['dispenser_id'] ),
        'reason'       => sanitize_text_field( $_POST['reason'] ),
        'repeats'      => sanitize_text_field( $_POST['repeats']    ?? 'no' ),
        'start_date'   => sanitize_text_field( $_POST['start_date'] ),
        'end_date'     => sanitize_text_field( $_POST['end_date'] ),
        'start_time'   => sanitize_text_field( $_POST['start_time'] ?? '09:00' ),
        'end_time'     => sanitize_text_field( $_POST['end_time']   ?? '17:00' ),
        'updated_at' => current_time( 'mysql' ),
    ];
    if ( $id ) { HearMed_DB::update( $t, $d, [ 'id' => $id ] ); }
    else { $d['created_at'] = current_time( 'mysql' ); $d['cct_status'] = 'publish'; HearMed_DB::insert( $t, $d ); $id = $last_insert_id /* TODO: Get this from HearMed_DB::insert() return value */; }
    wp_send_json_success( [ 'id' => $id ] );
}

function hm_ajax_delete_holiday() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
        // PostgreSQL only - no $wpdb needed
    HearMed_DB::delete( HearMed_Portal::table( 'holidays' ), [ 'id' => intval( $_POST['id'] ) ] );
    wp_send_json_success();
}

// ================================================================
// BLOCKOUTS — GET, SAVE, DELETE
// ================================================================
function hm_ajax_get_blockouts() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
        // PostgreSQL only - no $wpdb needed
    $t = HearMed_Portal::table( 'blockouts' );
    if ( HearMed_DB::get_var( HearMed_DB::prepare( "SELECT to_regclass(%s)", $t ) ) === null ) { wp_send_json_success( [] ); return; }
    $rows = HearMed_DB::get_results( "SELECT * FROM `$t` ORDER BY start_date DESC" );
    foreach ( $rows as &$r ) {
        $r->service_name   = get_the_title( $r->service_id );
        $r->dispenser_name = $r->dispenser_id ? get_the_title( $r->dispenser_id ) : 'All';
    }
    wp_send_json_success( $rows );
}

function hm_ajax_save_blockout() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
        // PostgreSQL only - no $wpdb needed
    $t  = HearMed_Portal::table( 'blockouts' );
    $id = intval( $_POST['id'] ?? 0 );
    $d  = [
        'service_id'   => intval( $_POST['service_id'] ),
        'dispenser_id' => intval( $_POST['dispenser_id'] ?? 0 ),
        'start_date'   => sanitize_text_field( $_POST['start_date'] ),
        'end_date'     => sanitize_text_field( $_POST['end_date'] ),
        'start_time'   => sanitize_text_field( $_POST['start_time'] ?? '09:00' ),
        'end_time'     => sanitize_text_field( $_POST['end_time']   ?? '17:00' ),
        'updated_at' => current_time( 'mysql' ),
    ];
    if ( $id ) { HearMed_DB::update( $t, $d, [ 'id' => $id ] ); }
    else { $d['created_at'] = current_time( 'mysql' ); $d['cct_status'] = 'publish'; HearMed_DB::insert( $t, $d ); $id = $last_insert_id /* TODO: Get this from HearMed_DB::insert() return value */; }
    wp_send_json_success( [ 'id' => $id ] );
}

function hm_ajax_delete_blockout() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
        // PostgreSQL only - no $wpdb needed
    HearMed_DB::delete( HearMed_Portal::table( 'blockouts' ), [ 'id' => intval( $_POST['id'] ) ] );
    wp_send_json_success();
}

// ================================================================
// SERVICES — SAVE, DELETE
// ================================================================
function hm_ajax_save_service() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
    $id   = intval( $_POST['id'] ?? 0 );
    $name = sanitize_text_field( $_POST['name'] );
    if ( $id ) wp_update_post( [ 'ID' => $id, 'post_title' => $name ] );
    else        $id = // TODO: USE PostgreSQL: HearMed_DB::insert()
    wp_insert_post( [ 'post_type' => 'service', 'post_title' => $name, 'post_status' => 'publish' ] );
    $meta = [
        'colour'               => $_POST['colour']               ?? '#3B82F6',
        'service_colour'       => $_POST['colour']               ?? '#3B82F6',
        'duration'             => intval( $_POST['duration']       ?? 30 ),
        'sales_opportunity'    => $_POST['sales_opportunity']    ?? 'no',
        'income_bearing'       => $_POST['income_bearing']       ?? 'no',
        'appointment_category' => $_POST['appointment_category'] ?? 'Normal',
        'send_reminders'       => $_POST['reminders']            ?? '0',
        'send_confirmation'    => $_POST['confirmation']         ?? 'no',
        'reminders'            => $_POST['reminders']            ?? '0',
        'confirmation'         => $_POST['confirmation']         ?? 'no',
    ];
    foreach ( $meta as $k => $v ) update_post_meta( $id, $k, sanitize_text_field( $v ) );
    wp_send_json_success( [ 'id' => $id ] );
}

function hm_ajax_delete_service() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
    wp_trash_post( intval( $_POST['id'] ) );
    wp_send_json_success();
}

// ================================================================
// DISPENSER ORDER
// ================================================================
function hm_ajax_save_dispenser_order() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Denied' ); return; }
    $order = json_decode( stripslashes( $_POST['order'] ), true );
    if ( is_array( $order ) ) {
        foreach ( $order as $i => $did ) update_post_meta( intval( $did ), 'calendar_order', $i + 1 );
    }
    wp_send_json_success();
}

// ================================================================
// CALENDAR RENDER FUNCTION (called by router)
// ================================================================
function hm_calendar_render() {
    ?>
    <div class="hm-content">
        <div class="hm-page-header">
            <h1 class="hm-page-title">Calendar</h1>
        </div>
        <div id="hm-calendar-container"></div>
    </div>
    <?php
}
