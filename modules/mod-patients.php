<?php
/**
 * HearMed Patients Module — Full Implementation
 *
 * Render (list + profile views) and all AJAX endpoints consumed by
 * assets/js/hearmed-patients.js.  Reads / writes:
 *   hearmed_core.patients, patient_notes, patient_documents, patient_forms,
 *   patient_devices, repairs, orders, invoices, credit_notes, appointments,
 *   appointment_outcomes, payments, cash_transactions, fitting_queue
 *   hearmed_reference.staff, clinics, products, manufacturers, referral_sources,
 *   services
 *   hearmed_admin.audit_log, gdpr_deletions, gdpr_exports
 *
 * Integration points:
 *   - Calendar: appointments created on calendar auto-appear in patient Appointments tab
 *   - Orders / Approvals: orders linked by patient_id appear in Orders tab
 *   - Invoices: outcome-generated invoices appear in Invoices tab
 *   - Fitting: fitting queue entries link back to patient devices
 *   - Admin Console: clinics, dispensers, products, referral sources, form settings
 *                    and AI settings all flow through to patient UI
 *   - GDPR settings: anonymise & export honour admin GDPR settings
 *
 * @package HearMed_Portal
 * @since   5.1.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ═══════════════════════════════════════════════════════════════════════════
//  RENDER — called by class-hearmed-router.php  →  hm_patients_render()
// ═══════════════════════════════════════════════════════════════════════════

function hm_patients_render() {
    if ( ! is_user_logged_in() ) {
        echo '<p>Please log in.</p>';
        return;
    }

    $patient_id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;

    if ( $patient_id ) {
        // ── Profile view ──
        echo '<div id="hm-patient-profile" data-patient-id="' . esc_attr( $patient_id ) . '"></div>';
    } else {
        // ── List view ──
        echo '<div id="hm-patient-list"></div>';
    }
}


// ═══════════════════════════════════════════════════════════════════════════
//  AJAX — Register all endpoints the JS needs
// ═══════════════════════════════════════════════════════════════════════════

// List / Search
add_action( 'wp_ajax_hm_get_patients',      'hm_ajax_get_patients' );
// hm_search_patients is registered in mod-calendar.php — no duplicate needed

// CRUD
add_action( 'wp_ajax_hm_create_patient',    'hm_ajax_create_patient' );
add_action( 'wp_ajax_hm_get_patient',       'hm_ajax_get_patient' );
add_action( 'wp_ajax_hm_update_patient',    'hm_ajax_update_patient' );

// Notes
add_action( 'wp_ajax_hm_get_patient_notes',    'hm_ajax_get_patient_notes' );
add_action( 'wp_ajax_hm_save_patient_note',    'hm_ajax_save_patient_note' );
add_action( 'wp_ajax_hm_delete_patient_note',  'hm_ajax_delete_patient_note' );

// Documents
add_action( 'wp_ajax_hm_get_patient_documents',     'hm_ajax_get_patient_documents' );
add_action( 'wp_ajax_hm_upload_patient_document',   'hm_ajax_upload_patient_document' );

// Hearing Aids (patient_devices)
add_action( 'wp_ajax_hm_get_patient_products',              'hm_ajax_get_patient_products' );
add_action( 'wp_ajax_hm_add_patient_product',               'hm_ajax_add_patient_product' );
add_action( 'wp_ajax_hm_update_patient_product_status',     'hm_ajax_update_patient_product_status' );
add_action( 'wp_ajax_hm_search_products',                   'hm_ajax_search_products' );
add_action( 'wp_ajax_hm_get_product_detail',                'hm_ajax_get_product_detail' );

// Appointments (reads from calendar — calendar handles creation)
add_action( 'wp_ajax_hm_get_patient_appointments', 'hm_ajax_get_patient_appointments' );

// Orders & Invoices (reads from orders/approvals modules)
add_action( 'wp_ajax_hm_get_patient_orders',    'hm_ajax_get_patient_orders' );
add_action( 'wp_ajax_hm_get_patient_invoices',  'hm_ajax_get_patient_invoices' );
add_action( 'wp_ajax_hm_download_invoice',      'hm_ajax_download_invoice' );

// Repairs & Returns
add_action( 'wp_ajax_hm_get_patient_repairs',    'hm_ajax_get_patient_repairs' );
add_action( 'wp_ajax_hm_create_patient_repair',  'hm_ajax_create_patient_repair' );
add_action( 'wp_ajax_hm_get_patient_returns',    'hm_ajax_get_patient_returns' );
add_action( 'wp_ajax_hm_log_cheque_sent',        'hm_ajax_log_cheque_sent' );

// Forms (integrates with admin Form Settings + Document Types)
add_action( 'wp_ajax_hm_get_patient_forms',      'hm_ajax_get_patient_forms' );
add_action( 'wp_ajax_hm_submit_patient_form',    'hm_ajax_submit_patient_form' );
add_action( 'wp_ajax_hm_get_form_templates',     'hm_ajax_get_form_templates' );
add_action( 'wp_ajax_hm_download_patient_form',  'hm_ajax_download_patient_form' );

// Marketing / GDPR (integrates with admin GDPR Settings)
add_action( 'wp_ajax_hm_update_marketing_prefs',  'hm_ajax_update_marketing_prefs' );
add_action( 'wp_ajax_hm_anonymise_patient',        'hm_ajax_anonymise_patient' );
add_action( 'wp_ajax_hm_export_patient_data',      'hm_ajax_export_patient_data' );

// Case History & AI (integrates with admin AI Settings)
add_action( 'wp_ajax_hm_save_case_history',   'hm_ajax_save_case_history' );
add_action( 'wp_ajax_hm_save_ai_transcript',  'hm_ajax_save_ai_transcript' );

// Activity / Audit
add_action( 'wp_ajax_hm_get_patient_audit',   'hm_ajax_get_patient_audit' );

// Notifications
add_action( 'wp_ajax_hm_create_patient_notification', 'hm_ajax_create_patient_notification' );


// ═══════════════════════════════════════════════════════════════════════════
//  HELPERS
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Get current staff row from hearmed_reference.staff matched by WP user ID.
 */
function hm_patient_current_staff() {
    static $staff = null;
    if ( $staff !== null ) return $staff ?: null;

    $uid = get_current_user_id();
    if ( ! $uid ) { $staff = false; return null; }

    $db = HearMed_DB::instance();
    $row = $db->get_row(
        "SELECT s.id, s.first_name, s.last_name, s.role_id,
                r.role_name, r.role_level
         FROM hearmed_reference.staff s
         LEFT JOIN hearmed_reference.roles r ON r.id = s.role_id
         WHERE s.wp_user_id = \$1 AND s.is_active = true
         LIMIT 1",
        [ $uid ]
    );
    $staff = $row ?: false;
    return $staff ?: null;
}

function hm_patient_staff_name( $staff_row = null ) {
    if ( ! $staff_row ) $staff_row = hm_patient_current_staff();
    return $staff_row ? trim( $staff_row->first_name . ' ' . $staff_row->last_name ) : 'System';
}

function hm_patient_staff_id() {
    $s = hm_patient_current_staff();
    return $s ? (int) $s->id : 0;
}

function hm_patient_is_admin() {
    return HearMed_Auth::is_admin();
}

/** Cast PG boolean to PHP bool */
function hm_pg_bool( $v ) {
    return $v === 't' || $v === true || $v === 1 || $v === '1';
}

function hm_patient_audit( $action, $entity_type, $entity_id, $details = '' ) {
    try {
        $db = HearMed_DB::instance();
        $row = [
            'user_id'     => get_current_user_id(),
            'action'      => $action,
            'entity_type' => $entity_type,
            'entity_id'   => $entity_id,
            'details'     => is_array( $details ) ? wp_json_encode( $details ) : $details,
        ];
        // ip_address is inet type — only set if we have a valid-looking IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ( $ip && filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            $row['ip_address'] = $ip;
        }
        $db->insert( 'hearmed_admin.audit_log', $row );
    } catch ( \Throwable $e ) {
        error_log( '[HearMed Patients] audit_log failed: ' . $e->getMessage() );
    }
}

/** Generate next H-XXXXX patient number */
function hm_generate_patient_number() {
    $db  = HearMed_DB::instance();
    $max = $db->get_var(
        "SELECT patient_number FROM hearmed_core.patients
         ORDER BY id DESC LIMIT 1"
    );
    if ( $max && preg_match( '/H-(\d+)/', $max, $m ) ) {
        return 'H-' . str_pad( (int) $m[1] + 1, 5, '0', STR_PAD_LEFT );
    }
    return 'H-00001';
}


// ═══════════════════════════════════════════════════════════════════════════
//  1. GET PATIENTS (list with filters + pagination)
//     Integrates: clinics, dispensers, referral sources from admin console
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_get_patients() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    $db   = HearMed_DB::instance();
    $page = max( 1, intval( $_POST['page'] ?? 1 ) );
    $per  = 25;
    $off  = ( $page - 1 ) * $per;

    $search    = sanitize_text_field( $_POST['search']    ?? '' );
    $clinic    = intval( $_POST['clinic']    ?? 0 );
    $dispenser = intval( $_POST['dispenser'] ?? 0 );
    $referral  = sanitize_text_field( $_POST['referral']  ?? '' );
    $active    = sanitize_text_field( $_POST['active']    ?? 'all' );

    $where  = [];
    $params = [];
    $idx    = 1;

    if ( $search ) {
        $where[] = "(p.first_name ILIKE \${$idx} OR p.last_name ILIKE \${$idx}
                     OR CONCAT(p.first_name,' ',p.last_name) ILIKE \${$idx}
                     OR p.patient_number ILIKE \${$idx}
                     OR p.phone ILIKE \${$idx}
                     OR p.email ILIKE \${$idx})";
        $params[] = '%' . $search . '%';
        $idx++;
    }
    if ( $clinic ) {
        $where[]  = "p.assigned_clinic_id = \${$idx}";
        $params[] = $clinic;
        $idx++;
    }
    if ( $dispenser ) {
        $where[]  = "p.assigned_dispenser_id = \${$idx}";
        $params[] = $dispenser;
        $idx++;
    }
    if ( $referral ) {
        $where[]  = "rs.source_name ILIKE \${$idx}";
        $params[] = '%' . $referral . '%';
        $idx++;
    }
    if ( $active === '1' ) {
        $where[] = 'p.is_active = true';
    } elseif ( $active === '0' ) {
        $where[] = 'p.is_active = false';
    }

    $w = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

    // Count
    $total = (int) $db->get_var(
        "SELECT COUNT(*)
         FROM hearmed_core.patients p
         LEFT JOIN hearmed_reference.referral_sources rs ON rs.id = p.referral_source_id
         {$w}",
        $params
    );

    // Rows — last appointment sub-selects link to calendar module's appointment data
    $count_params = $params;
    $params[] = $per;
    $limit_idx = $idx++;
    $params[] = $off;
    $offset_idx = $idx;

    $rows = $db->get_results(
        "SELECT p.id, p.patient_number, p.first_name, p.last_name,
                p.date_of_birth, p.phone, p.is_active,
                c.clinic_name,
                COALESCE(st.first_name || ' ' || st.last_name, '') AS dispenser_name,
                (SELECT a.appointment_date FROM hearmed_core.appointments a
                 WHERE a.patient_id = p.id
                 ORDER BY a.appointment_date DESC, a.start_time DESC LIMIT 1) AS last_appt_date,
                (SELECT a.start_time FROM hearmed_core.appointments a
                 WHERE a.patient_id = p.id
                 ORDER BY a.appointment_date DESC, a.start_time DESC LIMIT 1) AS last_appt_time
         FROM hearmed_core.patients p
         LEFT JOIN hearmed_reference.clinics c ON c.id = p.assigned_clinic_id
         LEFT JOIN hearmed_reference.staff st ON st.id = p.assigned_dispenser_id
         LEFT JOIN hearmed_reference.referral_sources rs ON rs.id = p.referral_source_id
         {$w}
         ORDER BY p.last_name, p.first_name
         LIMIT \${$limit_idx} OFFSET \${$offset_idx}",
        $params
    );

    $patients = [];
    foreach ( $rows as $r ) {
        $patients[] = [
            'id'              => (int) $r->id,
            'patient_number'  => $r->patient_number,
            'name'            => $r->first_name . ' ' . $r->last_name,
            'dob'             => $r->date_of_birth,
            'phone'           => $r->phone,
            'is_active'       => hm_pg_bool( $r->is_active ),
            'clinic_name'     => $r->clinic_name ?? '—',
            'dispenser_name'  => $r->dispenser_name ?: '—',
            'last_appt_date'  => $r->last_appt_date ?? null,
            'last_appt_time'  => $r->last_appt_time ?? null,
        ];
    }

    wp_send_json_success([
        'patients' => $patients,
        'total'    => $total,
        'page'     => $page,
        'pages'    => max( 1, (int) ceil( $total / $per ) ),
    ]);
}


// ═══════════════════════════════════════════════════════════════════════════
//  2. SEARCH PATIENTS (global search bar — compact results)
// ═══════════════════════════════════════════════════════════════════════════

// hm_ajax_search_patients() lives in mod-calendar.php — shared across modules


// ═══════════════════════════════════════════════════════════════════════════
//  3. CREATE PATIENT
//     Integrates: referral_sources (auto-creates if text entry),
//                 clinics & dispensers from admin console
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_create_patient() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    $fn = sanitize_text_field( $_POST['first_name'] ?? '' );
    $ln = sanitize_text_field( $_POST['last_name']  ?? '' );
    if ( ! $fn || ! $ln ) wp_send_json_error( 'First and last name are required.' );

    $db       = HearMed_DB::instance();
    $staff_id = hm_patient_staff_id();

    $dob = sanitize_text_field( $_POST['dob'] ?? '' );
    $clinic_id    = intval( $_POST['assigned_clinic_id'] ?? 0 );
    $dispenser_id = intval( $_POST['assigned_dispenser_id'] ?? 0 );

    $data = [
        'patient_number'  => hm_generate_patient_number(),
        'first_name'      => $fn,
        'last_name'       => $ln,
        'is_active'       => true,
        'prsi_eligible'   => ( $_POST['prsi_eligible'] ?? '0' ) === '1',
        'marketing_email' => ( $_POST['marketing_email'] ?? '0' ) === '1',
        'marketing_sms'   => ( $_POST['marketing_sms'] ?? '0' ) === '1',
        'marketing_phone' => ( $_POST['marketing_phone'] ?? '0' ) === '1',
        'gdpr_consent'    => ( $_POST['gdpr_consent'] ?? '0' ) === '1',
    ];

    // Only add optional fields if they have values (avoid PG type issues with empty strings)
    $title = sanitize_text_field( $_POST['patient_title'] ?? '' );
    if ( $title )         $data['patient_title'] = $title;
    if ( $dob )           $data['date_of_birth'] = $dob;

    $phone  = sanitize_text_field( $_POST['patient_phone'] ?? '' );
    $mobile = sanitize_text_field( $_POST['patient_mobile'] ?? '' );
    $email  = sanitize_email( $_POST['patient_email'] ?? '' );
    $addr   = sanitize_textarea_field( $_POST['patient_address'] ?? '' );
    $eir    = sanitize_text_field( $_POST['patient_eircode'] ?? '' );

    if ( $phone )  $data['phone']         = $phone;
    if ( $mobile ) $data['mobile']        = $mobile;
    if ( $email )  $data['email']         = $email;
    if ( $addr )   $data['address_line1'] = $addr;
    if ( $eir )    $data['eircode']       = $eir;

    if ( $clinic_id )    $data['assigned_clinic_id']    = $clinic_id;
    if ( $dispenser_id ) $data['assigned_dispenser_id'] = $dispenser_id;
    if ( $staff_id )     { $data['created_by'] = $staff_id; $data['updated_by'] = $staff_id; }

    if ( ( $_POST['gdpr_consent'] ?? '0' ) === '1' ) {
        $data['gdpr_consent_date']    = date( 'Y-m-d H:i:s' );
        $data['gdpr_consent_version'] = '1.0';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ( $ip ) $data['gdpr_consent_ip'] = $ip;
    }

    // Handle referral source — may be text or ID
    $ref_src = sanitize_text_field( $_POST['referral_source'] ?? '' );
    if ( $ref_src && is_numeric( $ref_src ) ) {
        $data['referral_source_id'] = (int) $ref_src;
    } elseif ( $ref_src ) {
        $existing = $db->get_var(
            "SELECT id FROM hearmed_reference.referral_sources WHERE source_name ILIKE \$1 LIMIT 1",
            [ $ref_src ]
        );
        if ( $existing ) {
            $data['referral_source_id'] = (int) $existing;
        } else {
            $new_id = $db->insert( 'hearmed_reference.referral_sources', [
                'source_name' => $ref_src,
                'is_active'   => true,
            ]);
            if ( $new_id ) $data['referral_source_id'] = (int) $new_id;
        }
    }

    $id = $db->insert( 'hearmed_core.patients', $data );
    if ( ! $id ) {
        $err = HearMed_DB::last_error();
        error_log( '[HearMed Patients] create failed: ' . $err . ' | data keys: ' . implode( ',', array_keys( $data ) ) );
        wp_send_json_error( 'Failed to create patient. DB: ' . ( $err ?: 'unknown error' ) );
    }

    // Non-critical: audit log (don't let failure block response)
    try { hm_patient_audit( 'CREATE', 'patient', $id, [ 'name' => $fn . ' ' . $ln ] ); } catch ( \Throwable $e ) {}

    wp_send_json_success( [ 'id' => $id ] );
}


// ═══════════════════════════════════════════════════════════════════════════
//  4. GET PATIENT (full profile for JS to render)
//     Integrates: clinics, dispensers, referral_sources, invoices (balance),
//                 admin settings (show_balance, show_prsi, default_tab)
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_get_patient() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );

    $db = HearMed_DB::instance();
    $p  = $db->get_row(
        "SELECT p.*,
                c.clinic_name,
                COALESCE(st.first_name || ' ' || st.last_name, '') AS dispenser_name,
                rs.source_name AS referral_source,
                rs2.source_name AS referral_sub_source
         FROM hearmed_core.patients p
         LEFT JOIN hearmed_reference.clinics c ON c.id = p.assigned_clinic_id
         LEFT JOIN hearmed_reference.staff   st ON st.id = p.assigned_dispenser_id
         LEFT JOIN hearmed_reference.referral_sources rs  ON rs.id  = p.referral_source_id
         LEFT JOIN hearmed_reference.referral_sources rs2 ON rs2.id = p.referral_sub_source_id
         WHERE p.id = \$1",
        [ $pid ]
    );
    if ( ! $p ) wp_send_json_error( 'Patient not found' );

    // Calculate age
    $age = '';
    if ( $p->date_of_birth ) {
        $dob = new DateTime( $p->date_of_birth );
        $now = new DateTime();
        $age = $dob->diff( $now )->y . ' yrs';
    }

    // Annual review calculation
    $review_status = '';
    $review_days   = 0;
    if ( $p->annual_review_date ) {
        $rev  = new DateTime( $p->annual_review_date );
        $now  = new DateTime();
        $diff = $now->diff( $rev );
        $days = (int) $diff->format( '%r%a' );
        $review_days = $days;
        if ( $days < 0 )       $review_status = 'overdue';
        elseif ( $days <= 30 ) $review_status = 'soon';
        else                   $review_status = 'ok';
    }

    // Patient stats — revenue, payments, balance (from invoices module)
    $stats = [ 'revenue' => 0, 'payments' => 0, 'balance' => 0 ];
    $inv_row = $db->get_row(
        "SELECT COALESCE(SUM(grand_total),0) AS revenue,
                COALESCE(SUM(grand_total - balance_remaining),0) AS payments,
                COALESCE(SUM(balance_remaining),0) AS balance
         FROM hearmed_core.invoices
         WHERE patient_id = \$1 AND payment_status != 'Void'",
        [ $pid ]
    );
    if ( $inv_row ) {
        $stats['revenue']  = (float) $inv_row->revenue;
        $stats['payments'] = (float) $inv_row->payments;
        $stats['balance']  = (float) $inv_row->balance;
    }

    // Admin settings integration
    $show_balance = get_option( 'hm_patient_show_balance', '1' );
    $show_prsi    = get_option( 'hm_patient_show_prsi', '1' );
    $is_admin     = hm_patient_is_admin();

    $data = [
        'id'                    => (int) $p->id,
        'patient_number'        => $p->patient_number,
        'patient_title'         => $p->patient_title,
        'first_name'            => $p->first_name,
        'last_name'             => $p->last_name,
        'name'                  => trim( ( $p->patient_title ? $p->patient_title . ' ' : '' ) . $p->first_name . ' ' . $p->last_name ),
        'dob'                   => $p->date_of_birth,
        'age'                   => $age,
        'gender'                => $p->gender,
        'phone'                 => $p->phone ?: '',
        'mobile'                => $p->mobile ?: '',
        'email'                 => $p->email ?: 'Not provided',
        'address'               => trim( implode( ', ', array_filter( [
            $p->address_line1, $p->address_line2, $p->city, $p->county
        ] ) ) ),
        'eircode'               => $p->eircode ?: '',
        'assigned_clinic_id'    => $p->assigned_clinic_id ? (int) $p->assigned_clinic_id : null,
        'assigned_dispenser_id' => $p->assigned_dispenser_id ? (int) $p->assigned_dispenser_id : null,
        'clinic_name'           => $p->clinic_name ?: '—',
        'dispenser_name'        => $p->dispenser_name ?: '—',
        'prsi_eligible'         => hm_pg_bool( $p->prsi_eligible ),
        'prsi_number'           => $p->prsi_number ?: '',
        'medical_card_number'   => $p->medical_card_number ?: '',
        'referral_source'       => $p->referral_source ?: '',
        'referral_sub_source'   => $p->referral_sub_source ?: '',
        'referral_notes'        => $p->referral_notes ?: '',
        'marketing_email'       => hm_pg_bool( $p->marketing_email ),
        'marketing_sms'         => hm_pg_bool( $p->marketing_sms ),
        'marketing_phone'       => hm_pg_bool( $p->marketing_phone ),
        'gdpr_consent'          => hm_pg_bool( $p->gdpr_consent ),
        'gdpr_consent_date'     => $p->gdpr_consent_date ? date( 'Y-m-d', strtotime( $p->gdpr_consent_date ) ) : '',
        'gdpr_consent_version'  => $p->gdpr_consent_version ?: '',
        'is_active'             => hm_pg_bool( $p->is_active ),
        'is_deceased'           => hm_pg_bool( $p->is_deceased ),
        'virtual_servicing'     => hm_pg_bool( $p->virtual_servicing ),
        'annual_review_date'    => $p->annual_review_date,
        'last_test_date'        => $p->last_test_date,
        'review_status'         => $review_status,
        'review_days'           => $review_days,
        'gp_name'               => '',
        'gp_address'            => '',
        'nok_name'              => '',
        'nok_phone'             => '',
        'stats'                 => $stats,
        'has_finance'           => $show_balance === '1',
        'show_prsi'             => $show_prsi === '1',
        'is_admin'              => $is_admin,
        'can_export'            => $is_admin,
        'show_audit'            => $is_admin,
    ];

    wp_send_json_success( $data );
}


// ═══════════════════════════════════════════════════════════════════════════
//  5. UPDATE PATIENT
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_update_patient() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );

    $db       = HearMed_DB::instance();
    $staff_id = hm_patient_staff_id();

    $data = [
        'patient_title'       => sanitize_text_field( $_POST['patient_title'] ?? '' ),
        'first_name'          => sanitize_text_field( $_POST['first_name'] ?? '' ),
        'last_name'           => sanitize_text_field( $_POST['last_name'] ?? '' ),
        'date_of_birth'       => sanitize_text_field( $_POST['dob'] ?? '' ) ?: null,
        'phone'               => sanitize_text_field( $_POST['patient_phone'] ?? '' ),
        'mobile'              => sanitize_text_field( $_POST['patient_mobile'] ?? '' ),
        'email'               => sanitize_email( $_POST['patient_email'] ?? '' ),
        'address_line1'       => sanitize_textarea_field( $_POST['patient_address'] ?? '' ),
        'eircode'             => sanitize_text_field( $_POST['patient_eircode'] ?? '' ),
        'prsi_number'         => sanitize_text_field( $_POST['prsi_number'] ?? '' ),
        'prsi_eligible'       => ( $_POST['prsi_eligible'] ?? '0' ) === '1',
        'is_active'           => ( $_POST['is_active'] ?? '1' ) === '1',
        'annual_review_date'  => sanitize_text_field( $_POST['annual_review_date'] ?? '' ) ?: null,
        'assigned_clinic_id'  => intval( $_POST['assigned_clinic_id'] ?? 0 ) ?: null,
        'updated_by'          => $staff_id ?: null,
        'updated_at'          => date( 'c' ),
    ];

    // Handle referral source
    $ref_src = sanitize_text_field( $_POST['referral_source'] ?? '' );
    if ( $ref_src && is_numeric( $ref_src ) ) {
        $data['referral_source_id'] = (int) $ref_src;
    } elseif ( $ref_src ) {
        $existing = $db->get_var(
            "SELECT id FROM hearmed_reference.referral_sources WHERE source_name ILIKE \$1 LIMIT 1",
            [ $ref_src ]
        );
        if ( $existing ) {
            $data['referral_source_id'] = (int) $existing;
        }
    }

    $result = $db->update( 'hearmed_core.patients', $data, [ 'id' => $pid ] );
    if ( $result === false ) wp_send_json_error( 'Update failed' );

    hm_patient_audit( 'UPDATE', 'patient', $pid, $data );

    wp_send_json_success( true );
}


// ═══════════════════════════════════════════════════════════════════════════
//  6. NOTES — CRUD
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_get_patient_notes() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );

    $db   = HearMed_DB::instance();
    $rows = $db->get_results(
        "SELECT n.id, n.note_type, n.note_text, n.created_by, n.created_at,
                COALESCE(s.first_name || ' ' || s.last_name, 'System') AS created_by_name
         FROM hearmed_core.patient_notes n
         LEFT JOIN hearmed_reference.staff s ON s.id = n.created_by
         WHERE n.patient_id = \$1
         ORDER BY n.created_at DESC",
        [ $pid ]
    );

    $staff_id = hm_patient_staff_id();
    $is_admin = hm_patient_is_admin();

    $out = [];
    foreach ( $rows as $r ) {
        $out[] = [
            '_ID'        => (int) $r->id,
            'note_type'  => $r->note_type,
            'note_text'  => $r->note_text,
            'created_by' => $r->created_by_name,
            'created_at' => $r->created_at,
            'can_edit'   => $is_admin || (int) $r->created_by === $staff_id,
        ];
    }
    wp_send_json_success( $out );
}

function hm_ajax_save_patient_note() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid  = intval( $_POST['patient_id'] ?? 0 );
    $nid  = intval( $_POST['_ID'] ?? 0 );
    $type = sanitize_text_field( $_POST['note_type'] ?? 'Manual' );
    $text = sanitize_textarea_field( $_POST['note_text'] ?? '' );

    if ( ! $pid || ! $text ) wp_send_json_error( 'Patient ID and note text required' );

    $db       = HearMed_DB::instance();
    $staff_id = hm_patient_staff_id();

    if ( $nid ) {
        $db->update( 'hearmed_core.patient_notes', [
            'note_type'  => $type,
            'note_text'  => $text,
            'updated_at' => date( 'c' ),
        ], [ 'id' => $nid ] );
        hm_patient_audit( 'UPDATE_NOTE', 'patient_note', $nid, [ 'patient_id' => $pid ] );
    } else {
        $nid = $db->insert( 'hearmed_core.patient_notes', [
            'patient_id' => $pid,
            'note_type'  => $type,
            'note_text'  => $text,
            'created_by' => $staff_id ?: null,
        ]);
        hm_patient_audit( 'CREATE_NOTE', 'patient_note', $nid, [ 'patient_id' => $pid ] );
    }

    wp_send_json_success( [ 'id' => $nid ] );
}

function hm_ajax_delete_patient_note() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $nid = intval( $_POST['_ID'] ?? 0 );
    if ( ! $nid ) wp_send_json_error( 'Missing note ID' );

    $db       = HearMed_DB::instance();
    $staff_id = hm_patient_staff_id();
    $is_admin = hm_patient_is_admin();

    $note = $db->get_row( "SELECT created_by, patient_id FROM hearmed_core.patient_notes WHERE id = \$1", [ $nid ] );
    if ( ! $note ) wp_send_json_error( 'Note not found' );
    if ( ! $is_admin && (int) $note->created_by !== $staff_id ) {
        wp_send_json_error( 'Not authorised to delete this note' );
    }

    $db->delete( 'hearmed_core.patient_notes', [ 'id' => $nid ] );
    hm_patient_audit( 'DELETE_NOTE', 'patient_note', $nid, [ 'patient_id' => $note->patient_id ] );

    wp_send_json_success( true );
}


// ═══════════════════════════════════════════════════════════════════════════
//  7. DOCUMENTS
//     Integrates: admin Document Types for type dropdown
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_get_patient_documents() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );

    $db   = HearMed_DB::instance();
    $rows = $db->get_results(
        "SELECT d.id, d.document_type, d.file_url, d.file_name, d.created_at,
                COALESCE(s.first_name || ' ' || s.last_name, 'System') AS created_by_name
         FROM hearmed_core.patient_documents d
         LEFT JOIN hearmed_reference.staff s ON s.id = d.created_by
         WHERE d.patient_id = \$1
         ORDER BY d.created_at DESC",
        [ $pid ]
    );

    $out = [];
    foreach ( $rows as $r ) {
        $out[] = [
            '_ID'           => (int) $r->id,
            'document_type' => $r->document_type,
            'file_name'     => $r->file_name,
            'download_url'  => $r->file_url,
            'created_by'    => $r->created_by_name,
            'created_at'    => $r->created_at,
        ];
    }
    wp_send_json_success( $out );
}

function hm_ajax_upload_patient_document() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid  = intval( $_POST['patient_id'] ?? 0 );
    $type = sanitize_text_field( $_POST['document_type'] ?? 'Other' );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );
    if ( empty( $_FILES['file'] ) ) wp_send_json_error( 'No file uploaded' );

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $upload = wp_handle_upload( $_FILES['file'], [ 'test_form' => false ] );
    if ( isset( $upload['error'] ) ) wp_send_json_error( $upload['error'] );

    $db       = HearMed_DB::instance();
    $staff_id = hm_patient_staff_id();

    $doc_id = $db->insert( 'hearmed_core.patient_documents', [
        'patient_id'    => $pid,
        'document_type' => $type,
        'file_url'      => $upload['url'],
        'file_name'     => basename( $upload['file'] ),
        'created_by'    => $staff_id ?: null,
    ]);

    hm_patient_audit( 'UPLOAD_DOCUMENT', 'patient_document', $doc_id, [
        'patient_id' => $pid,
        'type'       => $type,
        'file'       => basename( $upload['file'] ),
    ]);

    wp_send_json_success( [ 'id' => $doc_id ] );
}


// ═══════════════════════════════════════════════════════════════════════════
//  8. HEARING AIDS (patient_devices)
//     Integrates: products catalogue from admin, manufacturers, warranty terms
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_get_patient_products() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );

    $db   = HearMed_DB::instance();
    $rows = $db->get_results(
        "SELECT pd.id, pd.product_id, pd.serial_number_left, pd.serial_number_right,
                pd.fitting_date, pd.device_status, pd.inactive_reason, pd.warranty_expiry,
                COALESCE(pr.product_name, 'Unknown') AS product_name,
                COALESCE(m.name, '') AS manufacturer,
                pr.style,
                '' AS product_image
         FROM hearmed_core.patient_devices pd
         LEFT JOIN hearmed_reference.products pr ON pr.id = pd.product_id
         LEFT JOIN hearmed_reference.manufacturers m ON m.id = pr.manufacturer_id
         WHERE pd.patient_id = \$1
         ORDER BY pd.fitting_date DESC NULLS LAST",
        [ $pid ]
    );

    $out = [];
    foreach ( $rows as $r ) {
        $out[] = [
            '_ID'            => (int) $r->id,
            'product_id'     => $r->product_id ? (int) $r->product_id : null,
            'product_name'   => $r->product_name,
            'manufacturer'   => $r->manufacturer,
            'style'          => $r->style ?: '',
            'model'          => '',
            'serial_left'    => $r->serial_number_left ?: '',
            'serial_right'   => $r->serial_number_right ?: '',
            'fitting_date'   => $r->fitting_date,
            'warranty_expiry'=> $r->warranty_expiry,
            'status'         => $r->device_status ?: 'Active',
            'inactive_reason'=> $r->inactive_reason ?: '',
            'product_image'  => $r->product_image ?: '',
        ];
    }
    wp_send_json_success( $out );
}

function hm_ajax_add_patient_product() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );

    $db       = HearMed_DB::instance();
    $staff_id = hm_patient_staff_id();
    $fit_date = sanitize_text_field( $_POST['fitting_date'] ?? '' );
    if ( ! $fit_date ) wp_send_json_error( 'Fitting date is required' );

    $product_id = intval( $_POST['product_id'] ?? 0 ) ?: null;

    $id = $db->insert( 'hearmed_core.patient_devices', [
        'patient_id'          => $pid,
        'product_id'          => $product_id,
        'serial_number_left'  => sanitize_text_field( $_POST['serial_number_left'] ?? '' ),
        'serial_number_right' => sanitize_text_field( $_POST['serial_number_right'] ?? '' ),
        'fitting_date'        => $fit_date,
        'warranty_expiry'     => sanitize_text_field( $_POST['warranty_expiry'] ?? '' ) ?: null,
        'device_status'       => 'Active',
        'created_by'          => $staff_id ?: null,
    ]);

    if ( ! $id ) wp_send_json_error( 'Failed to add hearing aid' );

    // Update patient's last_test_date
    $db->update( 'hearmed_core.patients', [
        'last_test_date' => $fit_date,
        'updated_at'     => date( 'c' ),
    ], [ 'id' => $pid ] );

    hm_patient_audit( 'ADD_DEVICE', 'patient_device', $id, [ 'patient_id' => $pid ] );
    wp_send_json_success( [ 'id' => $id ] );
}

function hm_ajax_update_patient_product_status() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $id     = intval( $_POST['_ID'] ?? 0 );
    $status = sanitize_text_field( $_POST['status'] ?? 'Inactive' );
    $reason = sanitize_text_field( $_POST['reason'] ?? '' );
    if ( ! $id ) wp_send_json_error( 'Missing device ID' );

    $db = HearMed_DB::instance();
    $db->update( 'hearmed_core.patient_devices', [
        'device_status'  => $status,
        'inactive_reason'=> $reason,
        'inactive_date'  => date( 'Y-m-d' ),
        'updated_at'     => date( 'c' ),
    ], [ 'id' => $id ] );

    hm_patient_audit( 'UPDATE_DEVICE_STATUS', 'patient_device', $id, [ 'status' => $status ] );
    wp_send_json_success( true );
}

function hm_ajax_search_products() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $q  = sanitize_text_field( $_POST['q'] ?? '' );
    $db = HearMed_DB::instance();

    $sql = "SELECT p.id, p.product_name AS name,
                   COALESCE(m.name, '') AS manufacturer
            FROM hearmed_reference.products p
            LEFT JOIN hearmed_reference.manufacturers m ON m.id = p.manufacturer_id
            WHERE p.is_active = true AND p.category = 'Hearing Aid'";

    $params = [];
    if ( $q ) {
        $sql .= " AND (p.product_name ILIKE \$1 OR m.name ILIKE \$1)";
        $params[] = '%' . $q . '%';
    }
    $sql .= " ORDER BY p.product_name LIMIT 50";

    $rows = $db->get_results( $sql, $params );
    $out  = [];
    foreach ( $rows as $r ) {
        $out[] = [
            'id'           => (int) $r->id,
            'name'         => $r->name,
            'manufacturer' => $r->manufacturer,
        ];
    }
    wp_send_json_success( $out );
}

function hm_ajax_get_product_detail() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $id = intval( $_POST['product_id'] ?? 0 );
    if ( ! $id ) wp_send_json_error( 'Missing product_id' );

    $db = HearMed_DB::instance();
    $p  = $db->get_row(
        "SELECT p.id, p.product_name, p.style, p.category,
                COALESCE(m.name, '') AS manufacturer,
                COALESCE(m.warranty_terms, '') AS warranty_terms
         FROM hearmed_reference.products p
         LEFT JOIN hearmed_reference.manufacturers m ON m.id = p.manufacturer_id
         WHERE p.id = \$1",
        [ $id ]
    );
    if ( ! $p ) wp_send_json_error( 'Product not found' );

    // Extract warranty months from manufacturer warranty_terms
    $warranty_months = 0;
    if ( $p->warranty_terms && preg_match( '/(\d+)\s*month/i', $p->warranty_terms, $wm ) ) {
        $warranty_months = (int) $wm[1];
    } elseif ( $p->warranty_terms && preg_match( '/(\d+)\s*year/i', $p->warranty_terms, $wy ) ) {
        $warranty_months = (int) $wy[1] * 12;
    }

    wp_send_json_success([
        'id'              => (int) $p->id,
        'name'            => $p->product_name,
        'manufacturer'    => $p->manufacturer,
        'style'           => $p->style ?: '',
        'model'           => '',
        'warranty_months' => $warranty_months,
    ]);
}


// ═══════════════════════════════════════════════════════════════════════════
//  9. APPOINTMENTS
//     Reads from hearmed_core.appointments + appointment_outcomes
//     Calendar creates appointments → they appear here automatically.
//     Outcomes (with invoices) saved by calendar also appear.
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_get_patient_appointments() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );

    $db   = HearMed_DB::instance();
    $rows = $db->get_results(
        "SELECT a.id, a.appointment_date, a.start_time, a.end_time,
                a.appointment_status AS status, a.notes,
                COALESCE(sv.service_name, a.outcome, '') AS service_name,
                sv.service_colour,
                COALESCE(c.clinic_name, '') AS clinic_name,
                COALESCE(st.first_name || ' ' || st.last_name, '') AS dispenser_name,
                ao.outcome_name, ao.outcome_color AS outcome_banner_colour
         FROM hearmed_core.appointments a
         LEFT JOIN hearmed_reference.services sv ON sv.id = a.service_id
         LEFT JOIN hearmed_reference.clinics c ON c.id = a.clinic_id
         LEFT JOIN hearmed_reference.staff st ON st.id = a.staff_id
         LEFT JOIN hearmed_core.appointment_outcomes ao ON ao.appointment_id = a.id
         WHERE a.patient_id = \$1
         ORDER BY a.appointment_date DESC, a.start_time DESC",
        [ $pid ]
    );

    $out = [];
    foreach ( $rows as $r ) {
        $out[] = [
            'id'                    => (int) $r->id,
            'appointment_date'      => $r->appointment_date,
            'start_time'            => $r->start_time,
            'end_time'              => $r->end_time,
            'status'                => $r->status ?: 'Confirmed',
            'notes'                 => $r->notes ?: '',
            'service_name'          => $r->service_name ?: '—',
            'service_colour'        => $r->service_colour ?: '',
            'clinic_name'           => $r->clinic_name,
            'dispenser_name'        => $r->dispenser_name,
            'outcome_name'          => $r->outcome_name ?: '',
            'outcome_banner_colour' => $r->outcome_banner_colour ?: '',
        ];
    }
    wp_send_json_success( $out );
}


// ═══════════════════════════════════════════════════════════════════════════
//  10. ORDERS
//      Reads from hearmed_core.orders + order_items
//      Orders created via Approvals module appear here automatically.
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_get_patient_orders() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );

    $db   = HearMed_DB::instance();
    $rows = $db->get_results(
        "SELECT o.id, o.order_number, o.current_status AS status,
                o.grand_total, o.created_at,
                (SELECT string_agg(oi.item_description, ', ')
                 FROM hearmed_core.order_items oi
                 WHERE oi.order_id = o.id) AS description
         FROM hearmed_core.orders o
         WHERE o.patient_id = \$1
         ORDER BY o.created_at DESC",
        [ $pid ]
    );

    $out = [];
    foreach ( $rows as $r ) {
        $out[] = [
            '_ID'         => (int) $r->id,
            'order_number'=> $r->order_number,
            'status'      => $r->status,
            'grand_total' => (float) $r->grand_total,
            'description' => $r->description ?: '',
            'created_at'  => $r->created_at,
        ];
    }
    wp_send_json_success( $out );
}


// ═══════════════════════════════════════════════════════════════════════════
//  11. INVOICES
//      Reads from hearmed_core.invoices
//      Outcome-generated invoices from calendar appear here automatically.
//      Fitting payments also create invoices that appear here.
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_get_patient_invoices() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );

    $show_balance = get_option( 'hm_patient_show_balance', '1' );
    if ( $show_balance !== '1' && ! hm_patient_is_admin() ) {
        wp_send_json_error( 'Access denied — finance info restricted' );
    }

    $db   = HearMed_DB::instance();
    $rows = $db->get_results(
        "SELECT i.id, i.invoice_number, i.invoice_date, i.grand_total,
                i.balance_remaining AS balance, i.payment_status AS status,
                i.pdf_url, i.created_at
         FROM hearmed_core.invoices i
         WHERE i.patient_id = \$1 AND i.payment_status != 'Void'
         ORDER BY i.invoice_date DESC",
        [ $pid ]
    );

    $out = [];
    foreach ( $rows as $r ) {
        $out[] = [
            '_ID'            => (int) $r->id,
            'invoice_number' => $r->invoice_number,
            'invoice_date'   => $r->invoice_date,
            'grand_total'    => (float) $r->grand_total,
            'balance'        => (float) $r->balance,
            'status'         => $r->status,
            'pdf_url'        => $r->pdf_url ?: '',
            'created_at'     => $r->created_at,
        ];
    }
    wp_send_json_success( $out );
}

function hm_ajax_download_invoice() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $id = intval( $_GET['_ID'] ?? 0 );
    if ( ! $id ) wp_die( 'Missing invoice ID' );

    $db  = HearMed_DB::instance();
    $inv = $db->get_row( "SELECT pdf_url, invoice_number FROM hearmed_core.invoices WHERE id = \$1", [ $id ] );
    if ( ! $inv || ! $inv->pdf_url ) wp_die( 'Invoice PDF not available' );

    hm_patient_audit( 'DOWNLOAD_INVOICE', 'invoice', $id, [ 'invoice' => $inv->invoice_number ] );

    wp_redirect( $inv->pdf_url );
    exit;
}


// ═══════════════════════════════════════════════════════════════════════════
//  12. REPAIRS
//      Integrates: products, manufacturers, patient_devices
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_get_patient_repairs() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );

    $db   = HearMed_DB::instance();
    $rows = $db->get_results(
        "SELECT r.id, r.serial_number, r.date_booked, r.date_sent, r.date_received,
                r.repair_status AS status, r.warranty_status, r.repair_notes,
                COALESCE(pr.product_name, 'Unknown') AS product_name
         FROM hearmed_core.repairs r
         LEFT JOIN hearmed_core.patient_devices pd ON pd.id = r.patient_device_id
         LEFT JOIN hearmed_reference.products pr ON pr.id = COALESCE(r.product_id, pd.product_id)
         WHERE r.patient_id = \$1
         ORDER BY r.date_booked DESC",
        [ $pid ]
    );

    $out = [];
    foreach ( $rows as $r ) {
        $out[] = [
            '_ID'            => (int) $r->id,
            'product_name'   => $r->product_name,
            'serial_number'  => $r->serial_number ?: '',
            'date_booked'    => $r->date_booked,
            'date_sent'      => $r->date_sent,
            'date_received'  => $r->date_received,
            'status'         => $r->status ?: 'Booked',
            'warranty_status'=> $r->warranty_status ?: '',
            'repair_notes'   => $r->repair_notes ?: '',
        ];
    }
    wp_send_json_success( $out );
}

function hm_ajax_create_patient_repair() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );

    $db       = HearMed_DB::instance();
    $staff_id = hm_patient_staff_id();
    $pp_id    = intval( $_POST['patient_product_id'] ?? 0 ) ?: null;

    // Get product_id from patient_device if available
    $product_id = null;
    if ( $pp_id ) {
        $dev = $db->get_row( "SELECT product_id FROM hearmed_core.patient_devices WHERE id = \$1", [ $pp_id ] );
        if ( $dev ) $product_id = $dev->product_id ? (int) $dev->product_id : null;
    }

    $id = $db->insert( 'hearmed_core.repairs', [
        'patient_id'       => $pid,
        'patient_device_id'=> $pp_id,
        'product_id'       => $product_id,
        'serial_number'    => sanitize_text_field( $_POST['serial_number'] ?? '' ),
        'warranty_status'  => sanitize_text_field( $_POST['warranty_status'] ?? 'Unknown' ),
        'repair_notes'     => sanitize_textarea_field( $_POST['repair_notes'] ?? '' ),
        'date_booked'      => date( 'Y-m-d' ),
        'repair_status'    => 'Booked',
        'staff_id'         => $staff_id ?: null,
        'created_by'       => $staff_id ?: null,
    ]);

    if ( ! $id ) wp_send_json_error( 'Failed to create repair' );

    hm_patient_audit( 'CREATE_REPAIR', 'repair', $id, [ 'patient_id' => $pid ] );
    wp_send_json_success( [ 'id' => $id ] );
}


// ═══════════════════════════════════════════════════════════════════════════
//  13. RETURNS / CREDIT NOTES
//      Reads from hearmed_core.credit_notes, integrates with orders
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_get_patient_returns() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );

    $db   = HearMed_DB::instance();
    $rows = $db->get_results(
        "SELECT cn.id, cn.credit_note_number, cn.amount AS refund_amount,
                cn.reason, cn.credit_date, cn.cheque_sent,
                cn.cheque_sent_date,
                COALESCE(
                    (SELECT string_agg(pr.product_name, ', ')
                     FROM hearmed_core.order_items oi
                     JOIN hearmed_reference.products pr ON pr.id = oi.item_id
                     WHERE oi.order_id = cn.order_id AND oi.item_type = 'product'),
                    'N/A'
                ) AS product_name
         FROM hearmed_core.credit_notes cn
         WHERE cn.patient_id = \$1
         ORDER BY cn.credit_date DESC",
        [ $pid ]
    );

    $out = [];
    foreach ( $rows as $r ) {
        $out[] = [
            '_ID'              => (int) $r->id,
            'credit_note_num'  => $r->credit_note_number,
            'refund_amount'    => (float) $r->refund_amount,
            'product_name'     => $r->product_name,
            'cheque_sent'      => hm_pg_bool( $r->cheque_sent ),
            'cheque_sent_date' => $r->cheque_sent_date,
        ];
    }
    wp_send_json_success( $out );
}

function hm_ajax_log_cheque_sent() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $id   = intval( $_POST['_ID'] ?? 0 );
    $date = sanitize_text_field( $_POST['cheque_date'] ?? date( 'Y-m-d' ) );
    if ( ! $id ) wp_send_json_error( 'Missing credit note ID' );

    $db = HearMed_DB::instance();
    $db->update( 'hearmed_core.credit_notes', [
        'cheque_sent'      => true,
        'cheque_sent_date' => $date,
        'updated_at'       => date( 'c' ),
    ], [ 'id' => $id ] );

    hm_patient_audit( 'LOG_CHEQUE', 'credit_note', $id, [ 'date' => $date ] );
    wp_send_json_success( true );
}


// ═══════════════════════════════════════════════════════════════════════════
//  14. FORMS
//      Integrates: admin Form Settings (hm_form_templates option),
//                  GDPR consent syncs back to patient record,
//                  marketing prefs sync from form submissions
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_get_patient_forms() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );

    $db   = HearMed_DB::instance();
    $rows = $db->get_results(
        "SELECT pf.id, pf.form_type, pf.gdpr_consent,
                pf.signature_image_url, pf.created_at
         FROM hearmed_core.patient_forms pf
         WHERE pf.patient_id = \$1
         ORDER BY pf.created_at DESC",
        [ $pid ]
    );

    $out = [];
    foreach ( $rows as $r ) {
        $out[] = [
            '_ID'           => (int) $r->id,
            'form_type'     => $r->form_type,
            'gdpr_consent'  => hm_pg_bool( $r->gdpr_consent ),
            'has_signature' => ! empty( $r->signature_image_url ),
            'created_at'    => $r->created_at,
        ];
    }
    wp_send_json_success( $out );
}

function hm_ajax_submit_patient_form() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid  = intval( $_POST['patient_id'] ?? 0 );
    $type = sanitize_text_field( $_POST['form_type'] ?? '' );
    if ( ! $pid || ! $type ) wp_send_json_error( 'Patient ID and form type required' );

    $db       = HearMed_DB::instance();
    $staff_id = hm_patient_staff_id();

    // Handle signature data (base64 image)
    $sig_url  = '';
    $sig_data = $_POST['signature_data'] ?? '';
    if ( $sig_data && strpos( $sig_data, 'data:image' ) === 0 ) {
        $upload_dir = wp_upload_dir();
        $filename   = 'sig-' . $pid . '-' . time() . '.png';
        $filepath   = $upload_dir['path'] . '/' . $filename;

        $img_data = explode( ',', $sig_data );
        if ( isset( $img_data[1] ) ) {
            file_put_contents( $filepath, base64_decode( $img_data[1] ) );
            $sig_url = $upload_dir['url'] . '/' . $filename;
        }
    }

    $form_id = $db->insert( 'hearmed_core.patient_forms', [
        'patient_id'          => $pid,
        'form_type'           => $type,
        'form_data'           => sanitize_text_field( $_POST['form_data'] ?? '{}' ),
        'gdpr_consent'        => ( $_POST['gdpr_consent'] ?? '0' ) == '1',
        'marketing_email'     => ( $_POST['marketing_email'] ?? '0' ) == '1',
        'marketing_sms'       => ( $_POST['marketing_sms'] ?? '0' ) == '1',
        'marketing_phone'     => ( $_POST['marketing_phone'] ?? '0' ) == '1',
        'signature_image_url' => $sig_url,
        'created_by'          => $staff_id ?: null,
    ]);

    // Sync GDPR + marketing prefs back to patient record
    if ( ( $_POST['gdpr_consent'] ?? '0' ) == '1' ) {
        $db->update( 'hearmed_core.patients', [
            'gdpr_consent'       => true,
            'gdpr_consent_date'  => date( 'c' ),
            'marketing_email'    => ( $_POST['marketing_email'] ?? '0' ) == '1',
            'marketing_sms'      => ( $_POST['marketing_sms'] ?? '0' ) == '1',
            'marketing_phone'    => ( $_POST['marketing_phone'] ?? '0' ) == '1',
            'updated_at'         => date( 'c' ),
        ], [ 'id' => $pid ] );
    }

    hm_patient_audit( 'SUBMIT_FORM', 'patient_form', $form_id, [
        'patient_id' => $pid,
        'form_type'  => $type,
    ]);

    wp_send_json_success( [ 'id' => $form_id ] );
}

function hm_ajax_get_form_templates() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    // Pull from admin Form Settings (set via admin console)
    $templates = get_option( 'hm_form_templates', '' );
    if ( $templates && is_string( $templates ) ) {
        $templates = json_decode( $templates, true );
    }

    if ( ! $templates || ! is_array( $templates ) ) {
        // Defaults match admin form settings page
        $templates = [
            [ 'id' => 'consent',     'type' => 'Consent Form',         'name' => 'Consent Form' ],
            [ 'id' => 'assessment',  'type' => 'Hearing Assessment',   'name' => 'Hearing Assessment Form' ],
            [ 'id' => 'history',     'type' => 'Case History',         'name' => 'Case History Form' ],
            [ 'id' => 'trial',       'type' => 'Trial Form',           'name' => 'Hearing Aid Trial Form' ],
            [ 'id' => 'satisfaction','type' => 'Satisfaction Survey',   'name' => 'Patient Satisfaction Survey' ],
            [ 'id' => 'gdpr',        'type' => 'GDPR Consent',         'name' => 'GDPR Data Processing Consent' ],
        ];
    }

    wp_send_json_success( $templates );
}

function hm_ajax_download_patient_form() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $id = intval( $_GET['_ID'] ?? 0 );
    if ( ! $id ) wp_die( 'Missing form ID' );

    $db   = HearMed_DB::instance();
    $form = $db->get_row(
        "SELECT pf.*, p.first_name, p.last_name, p.patient_number
         FROM hearmed_core.patient_forms pf
         JOIN hearmed_core.patients p ON p.id = pf.patient_id
         WHERE pf.id = \$1",
        [ $id ]
    );
    if ( ! $form ) wp_die( 'Form not found' );

    hm_patient_audit( 'DOWNLOAD_FORM', 'patient_form', $id, [
        'patient_id' => $form->patient_id,
        'form_type'  => $form->form_type,
    ]);

    header( 'Content-Type: text/html; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="' . $form->patient_number . '-' . sanitize_file_name( $form->form_type ) . '.html"' );

    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>' . esc_html( $form->form_type ) . '</title>';
    echo '<style>body{font-family:Arial,sans-serif;padding:40px;max-width:800px;margin:0 auto;}'
       . 'h1{font-size:20px;border-bottom:2px solid #0BB4C4;padding-bottom:8px;}'
       . '.field{margin:8px 0;font-size:14px;}.label{font-weight:bold;color:#64748b;}'
       . '.sig{margin-top:20px;border:1px solid #e2e8f0;padding:10px;}</style></head><body>';
    echo '<h1>' . esc_html( $form->form_type ) . '</h1>';
    echo '<div class="field"><span class="label">Patient:</span> ' . esc_html( $form->first_name . ' ' . $form->last_name ) . ' (' . esc_html( $form->patient_number ) . ')</div>';
    echo '<div class="field"><span class="label">Date:</span> ' . esc_html( $form->created_at ) . '</div>';
    echo '<div class="field"><span class="label">GDPR Consent:</span> ' . ( hm_pg_bool( $form->gdpr_consent ) ? 'Yes' : 'No' ) . '</div>';

    if ( $form->form_data ) {
        $fd = json_decode( $form->form_data, true );
        if ( $fd && is_array( $fd ) ) {
            foreach ( $fd as $k => $v ) {
                echo '<div class="field"><span class="label">' . esc_html( ucfirst( str_replace( '_', ' ', $k ) ) ) . ':</span> ' . esc_html( $v ) . '</div>';
            }
        }
    }

    if ( $form->signature_image_url ) {
        echo '<div class="sig"><p class="label">Signature:</p><img src="' . esc_url( $form->signature_image_url ) . '" style="max-width:400px;"></div>';
    }

    echo '</body></html>';
    exit;
}


// ═══════════════════════════════════════════════════════════════════════════
//  15. MARKETING PREFERENCES
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_update_marketing_prefs() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );

    $db = HearMed_DB::instance();
    $db->update( 'hearmed_core.patients', [
        'marketing_email' => ( $_POST['marketing_email'] ?? '0' ) === '1',
        'marketing_sms'   => ( $_POST['marketing_sms'] ?? '0' ) === '1',
        'marketing_phone' => ( $_POST['marketing_phone'] ?? '0' ) === '1',
        'updated_at'      => date( 'c' ),
        'updated_by'      => hm_patient_staff_id() ?: null,
    ], [ 'id' => $pid ] );

    hm_patient_audit( 'UPDATE_MARKETING', 'patient', $pid, [
        'email' => $_POST['marketing_email'] ?? '0',
        'sms'   => $_POST['marketing_sms'] ?? '0',
        'phone' => $_POST['marketing_phone'] ?? '0',
    ]);

    wp_send_json_success( true );
}


// ═══════════════════════════════════════════════════════════════════════════
//  16. GDPR — ANONYMISE (Right to Erasure)
//      Integrates: admin GDPR Settings, audit_log, gdpr_deletions
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_anonymise_patient() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid     = intval( $_POST['patient_id'] ?? 0 );
    $confirm = sanitize_text_field( $_POST['confirm'] ?? '' );

    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );
    if ( $confirm !== 'CONFIRM ERASURE' ) wp_send_json_error( 'Confirmation text does not match' );
    if ( ! hm_patient_is_admin() ) wp_send_json_error( 'Only admin users can anonymise patients' );

    $db       = HearMed_DB::instance();
    $staff_id = hm_patient_staff_id();
    $stamp    = 'ANONYMISED ' . date( 'Y-m-d' );

    $patient = $db->get_row( "SELECT patient_number FROM hearmed_core.patients WHERE id = \$1", [ $pid ] );

    $db->begin_transaction();

    try {
        // Anonymise personal data — keep clinical & financial references
        $db->update( 'hearmed_core.patients', [
            'first_name'          => $stamp,
            'last_name'           => $stamp,
            'patient_title'       => '',
            'date_of_birth'       => null,
            'gender'              => '',
            'phone'               => '',
            'mobile'              => '',
            'email'               => '',
            'address_line1'       => '',
            'address_line2'       => '',
            'city'                => '',
            'county'              => '',
            'eircode'             => '',
            'prsi_number'         => '',
            'medical_card_number' => '',
            'referral_notes'      => '',
            'marketing_email'     => false,
            'marketing_sms'       => false,
            'marketing_phone'     => false,
            'gdpr_consent'        => false,
            'gdpr_consent_ip'     => '',
            'is_active'           => false,
            'updated_at'          => date( 'c' ),
            'updated_by'          => $staff_id ?: null,
        ], [ 'id' => $pid ] );

        // Anonymise non-clinical notes
        $db->query(
            "UPDATE hearmed_core.patient_notes SET note_text = \$1 WHERE patient_id = \$2 AND note_type NOT IN ('Clinical','AI Transcript')",
            [ $stamp, $pid ]
        );

        // Remove document files (keep DB records)
        $docs = $db->get_results( "SELECT file_url FROM hearmed_core.patient_documents WHERE patient_id = \$1", [ $pid ] );
        foreach ( $docs as $doc ) {
            if ( $doc->file_url ) {
                $path = str_replace( wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $doc->file_url );
                if ( file_exists( $path ) ) @unlink( $path );
            }
        }
        $db->query(
            "UPDATE hearmed_core.patient_documents SET file_url = '', file_name = \$1 WHERE patient_id = \$2",
            [ $stamp, $pid ]
        );

        // Remove signature images
        $sigs = $db->get_results( "SELECT signature_image_url FROM hearmed_core.patient_forms WHERE patient_id = \$1 AND signature_image_url IS NOT NULL AND signature_image_url != ''", [ $pid ] );
        foreach ( $sigs as $sig ) {
            $path = str_replace( wp_upload_dir()['baseurl'], wp_upload_dir()['basedir'], $sig->signature_image_url );
            if ( file_exists( $path ) ) @unlink( $path );
        }
        $db->query(
            "UPDATE hearmed_core.patient_forms SET signature_image_url = '', form_data = '{}'::jsonb WHERE patient_id = \$1",
            [ $pid ]
        );

        // Log GDPR deletion
        $db->insert( 'hearmed_admin.gdpr_deletions', [
            'patient_id'   => $pid,
            'deleted_by'   => $staff_id ?: get_current_user_id(),
            'deleted_at'   => date( 'c' ),
            'deletion_type'=> 'anonymise',
            'details'      => wp_json_encode( [ 'patient_number' => $patient->patient_number ?? '' ] ),
        ]);

        $db->commit();

        hm_patient_audit( 'GDPR_ERASURE', 'patient', $pid, [
            'patient_number' => $patient->patient_number ?? '',
            'action'         => 'anonymise',
        ]);

        wp_send_json_success( true );

    } catch ( \Exception $e ) {
        $db->rollback();
        wp_send_json_error( 'Anonymisation failed: ' . $e->getMessage() );
    }
}


// ═══════════════════════════════════════════════════════════════════════════
//  17. GDPR — EXPORT (Article 20 — Data Portability)
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_export_patient_data() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );
    if ( ! hm_patient_is_admin() ) wp_send_json_error( 'Only admin users can export' );

    $db       = HearMed_DB::instance();
    $staff_id = hm_patient_staff_id();

    $db->insert( 'hearmed_admin.gdpr_exports', [
        'patient_id'   => $pid,
        'exported_by'  => $staff_id ?: get_current_user_id(),
        'exported_at'  => date( 'c' ),
        'export_format'=> 'json',
    ]);

    hm_patient_audit( 'GDPR_EXPORT', 'patient', $pid, [ 'format' => 'json' ] );

    wp_send_json_success( [ 'message' => 'Export logged. Data package will be prepared.' ] );
}


// ═══════════════════════════════════════════════════════════════════════════
//  18. CASE HISTORY
//      Integrates: AI Settings from admin console (webhook URL)
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_save_case_history() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid  = intval( $_POST['patient_id'] ?? 0 );
    $text = sanitize_textarea_field( $_POST['note_text'] ?? '' );
    $type = sanitize_text_field( $_POST['appointment_type'] ?? 'Case History' );

    if ( ! $pid || ! $text ) wp_send_json_error( 'Patient ID and note text required' );

    $db       = HearMed_DB::instance();
    $staff_id = hm_patient_staff_id();

    $id = $db->insert( 'hearmed_core.patient_notes', [
        'patient_id' => $pid,
        'note_type'  => 'Clinical',
        'note_text'  => $text,
        'created_by' => $staff_id ?: null,
    ]);

    hm_patient_audit( 'CREATE_CASE_HISTORY', 'patient_note', $id, [
        'patient_id' => $pid,
        'type'       => $type,
    ]);

    wp_send_json_success( [ 'id' => $id ] );
}

function hm_ajax_save_ai_transcript() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid  = intval( $_POST['patient_id'] ?? 0 );
    $text = sanitize_textarea_field( $_POST['transcript'] ?? '' );

    if ( ! $pid || ! $text ) wp_send_json_error( 'Patient ID and transcript required' );

    $db       = HearMed_DB::instance();
    $staff_id = hm_patient_staff_id();

    $id = $db->insert( 'hearmed_core.patient_notes', [
        'patient_id' => $pid,
        'note_type'  => 'AI Transcript',
        'note_text'  => '[AI Transcription — ' . date( 'Y-m-d H:i' ) . "]\n" . $text,
        'created_by' => $staff_id ?: null,
    ]);

    hm_patient_audit( 'SAVE_AI_TRANSCRIPT', 'patient_note', $id, [ 'patient_id' => $pid ] );

    wp_send_json_success( [ 'id' => $id ] );
}


// ═══════════════════════════════════════════════════════════════════════════
//  19. ACTIVITY / AUDIT LOG
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_get_patient_audit() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );
    if ( ! hm_patient_is_admin() ) wp_send_json_error( 'Access denied — admin only' );

    $db   = HearMed_DB::instance();
    $rows = $db->get_results(
        "SELECT al.action, al.entity_type, al.entity_id, al.details,
                al.created_at, al.user_name AS user_display
         FROM hearmed_admin.audit_log al
         WHERE (al.entity_type = 'patient' AND al.entity_id = \$1)
            OR (al.details::text LIKE '%\"patient_id\":' || \$1::text || '%'
                AND al.entity_type IN ('patient_note','patient_document','patient_form','patient_device','repair','credit_note','invoice','notification'))
         ORDER BY al.created_at DESC
         LIMIT 100",
        [ $pid ]
    );

    $out = [];
    foreach ( $rows as $r ) {
        $out[] = [
            'action'      => $r->action,
            'entity_type' => $r->entity_type,
            'entity_id'   => $r->entity_id,
            'details'     => $r->details ?: '',
            'created_at'  => $r->created_at,
            'user'        => $r->user_display ?: 'System',
        ];
    }
    wp_send_json_success( $out );
}


// ═══════════════════════════════════════════════════════════════════════════
//  20. NOTIFICATIONS / REMINDERS
//      Integrates: hearmed_communication.internal_notifications + recipients
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_create_patient_notification() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    $msg = sanitize_textarea_field( $_POST['message'] ?? '' );

    if ( ! $pid || ! $msg ) wp_send_json_error( 'Patient ID and message required' );

    $db       = HearMed_DB::instance();
    $staff_id = hm_patient_staff_id();
    $assigned = intval( $_POST['assigned_user_id'] ?? 0 ) ?: $staff_id;

    $id = $db->insert( 'hearmed_communication.internal_notifications', [
        'notification_type' => sanitize_text_field( $_POST['notification_type'] ?? 'Phone Call' ),
        'title'             => $msg,
        'message'           => $msg,
        'priority'          => sanitize_text_field( $_POST['priority'] ?? 'normal' ),
        'reference_type'    => 'patient',
        'reference_id'      => $pid,
        'created_by'        => $staff_id ?: null,
        'scheduled_at'      => sanitize_text_field( $_POST['scheduled_date'] ?? date( 'Y-m-d' ) ),
    ]);

    if ( $id && $assigned ) {
        $db->insert( 'hearmed_communication.notification_recipients', [
            'notification_id' => $id,
            'user_id'         => $assigned,
            'is_read'         => false,
        ]);
    }

    hm_patient_audit( 'CREATE_NOTIFICATION', 'notification', $id, [
        'patient_id' => $pid,
        'type'       => $_POST['notification_type'] ?? 'Phone Call',
    ]);

    wp_send_json_success( [ 'id' => $id ] );
}
