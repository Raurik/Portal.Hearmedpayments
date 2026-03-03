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
    if ( ! PortalAuth::is_logged_in() ) {
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
add_action( 'wp_ajax_hm_get_patient_alerts',   'hm_ajax_get_patient_alerts' );

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
add_action( 'wp_ajax_hm_download_order_pdf',    'hm_ajax_download_order_pdf' );

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
add_action( 'wp_ajax_hm_save_case_history',        'hm_ajax_save_case_history' );
add_action( 'wp_ajax_hm_save_ai_transcript',       'hm_ajax_save_ai_transcript' );
add_action( 'wp_ajax_hm_transcribe_audio',         'hm_ajax_transcribe_audio' );
add_action( 'wp_ajax_hm_get_ai_document_types',    'hm_ajax_get_ai_document_types' );
add_action( 'wp_ajax_hm_get_patient_transcripts',  'hm_ajax_get_patient_transcripts' );

// Activity / Audit
add_action( 'wp_ajax_hm_get_patient_audit',   'hm_ajax_get_patient_audit' );

// Notifications
add_action( 'wp_ajax_hm_create_patient_notification', 'hm_ajax_create_patient_notification' );

// Lookup helpers (referral sources / lead types)
add_action( 'wp_ajax_hm_get_referral_sources', 'hm_ajax_get_referral_sources' );
add_action( 'wp_ajax_hm_get_staff_list',        'hm_ajax_get_staff_list' );

// Notes — pinned
add_action( 'wp_ajax_hm_toggle_note_pin',  'hm_ajax_toggle_note_pin' );

// Manufacturers lookup
add_action( 'wp_ajax_hm_get_manufacturers',  'hm_ajax_get_manufacturers' );

// Hearing aid catalog for cascading dropdowns
add_action( 'wp_ajax_hm_get_ha_catalog',  'hm_ajax_get_ha_catalog' );

// Repair enhancements
add_action( 'wp_ajax_hm_update_repair_status', 'hm_ajax_update_repair_status' );

// Exchange flow
add_action( 'wp_ajax_hm_create_exchange',  'hm_ajax_create_exchange' );
add_action( 'wp_ajax_hm_get_exchange_item_amount', 'hm_ajax_get_exchange_item_amount' );
add_action( 'wp_ajax_hm_get_patient_exchanges', 'hm_ajax_get_patient_exchanges' );
add_action( 'wp_ajax_hm_initiate_exchange', 'hm_ajax_initiate_exchange' );
add_action( 'wp_ajax_hm_get_exchange_details', 'hm_ajax_get_exchange_details' );
add_action( 'wp_ajax_hm_complete_exchange', 'hm_ajax_complete_exchange' );

// Return flow (full return with PRSI tracking)
add_action( 'wp_ajax_hm_create_return',  'hm_ajax_create_return' );

// Patient credits
add_action( 'wp_ajax_hm_get_patient_credits',  'hm_ajax_get_patient_credits' );
add_action( 'wp_ajax_hm_apply_credit_to_invoice', 'hm_ajax_apply_credit_to_invoice' );

// PRSI claim tracking
add_action( 'wp_ajax_hm_get_prsi_info',  'hm_ajax_get_prsi_info' );


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
    // Try with role_id FK first, fall back to role column if it fails
    $row = $db->get_row(
        "SELECT s.id, s.first_name, s.last_name, s.role,
                r.role_name
         FROM hearmed_reference.staff s
         LEFT JOIN hearmed_reference.roles r ON r.role_name = s.role
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

    // ── Duplicate patient check: same name + DOB + address ──
    $addr_check = sanitize_textarea_field( $_POST['patient_address'] ?? '' );
    $dup_params = [ $fn, $ln ];
    $dup_sql = "SELECT id, first_name, last_name, date_of_birth, address_line1
                  FROM hearmed_core.patients
                 WHERE LOWER(TRIM(first_name)) = LOWER(TRIM(\$1))
                   AND LOWER(TRIM(last_name))  = LOWER(TRIM(\$2))";
    if ( $dob ) {
        $dup_sql .= " AND date_of_birth = \$" . ( count( $dup_params ) + 1 );
        $dup_params[] = $dob;
    }
    if ( $addr_check ) {
        $dup_sql .= " AND LOWER(TRIM(COALESCE(address_line1,''))) = LOWER(TRIM(\$" . ( count( $dup_params ) + 1 ) . "))";
        $dup_params[] = $addr_check;
    }
    $dup_sql .= " LIMIT 1";
    $existing = $db->get_row( $dup_sql, $dup_params );
    if ( $existing ) {
        wp_send_json_error( 'A patient with the same name' . ( $dob ? ', date of birth' : '' ) . ( $addr_check ? ' and address' : '' ) . ' already exists (ID: ' . $existing->id . '). Please check for duplicates before adding a new patient.' );
        return;
    }

    $data = [
        'patient_number'  => hm_generate_patient_number(),
        'first_name'      => $fn,
        'last_name'       => $ln,
        'is_active'       => true,
        'marketing_email' => ( $_POST['marketing_email'] ?? '0' ) === '1',
        'marketing_sms'   => ( $_POST['marketing_sms'] ?? '0' ) === '1',
        'marketing_phone' => ( $_POST['marketing_phone'] ?? '0' ) === '1',
        'gdpr_consent'    => ( $_POST['gdpr_consent'] ?? '0' ) === '1',
    ];

    // PPS number (stored in prsi_number column)
    $pps = sanitize_text_field( $_POST['pps_number'] ?? '' );
    if ( $pps ) $data['prsi_number'] = $pps;

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

    // Handle referral source (dropdown sends ID)
    $ref_src = intval( $_POST['referral_source_id'] ?? 0 );
    if ( $ref_src ) {
        $data['referral_source_id'] = $ref_src;
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
//  3b. LOOKUP HELPERS — referral sources (lead types) & staff list
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_get_referral_sources() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $rows = HearMed_DB::get_results(
        "SELECT id, source_name FROM hearmed_reference.referral_sources WHERE is_active = true ORDER BY sort_order, source_name"
    );
    $out = [];
    if ( $rows ) {
        foreach ( $rows as $r ) {
            $out[] = [ 'id' => (int) $r->id, 'name' => $r->source_name ];
        }
    }
    wp_send_json_success( $out );
}

function hm_ajax_get_staff_list() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $rows = HearMed_DB::get_results(
        "SELECT id, first_name, last_name, role
         FROM hearmed_reference.staff
         WHERE is_active = true
           AND LOWER(role) = 'dispenser'
         ORDER BY first_name, last_name"
    );
    $out = [];
    if ( $rows ) {
        foreach ( $rows as $r ) {
            $out[] = [
                'id'   => (int) $r->id,
                'name' => trim( $r->first_name . ' ' . $r->last_name ),
            ];
        }
    }
    wp_send_json_success( $out );
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
    $stats = [ 'revenue' => 0, 'payments' => 0, 'balance' => 0, 'credit' => 0 ];
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

    // Pending credits — credit notes where cheque not yet sent (patient refund portion)
    $has_prsi_col = $db->get_var(
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema = 'hearmed_core' AND table_name = 'credit_notes' AND column_name = 'patient_refund_amount'"
    );
    $credit_col = $has_prsi_col ? 'patient_refund_amount' : 'amount';
    $credit_row = $db->get_row(
        "SELECT COALESCE(SUM({$credit_col}), 0) AS pending_credit
         FROM hearmed_core.credit_notes
         WHERE patient_id = \$1 AND (cheque_sent = false OR cheque_sent IS NULL)",
        [ $pid ]
    );
    if ( $credit_row ) {
        $stats['credit'] = (float) $credit_row->pending_credit;
    }

    // Admin settings integration
    $show_balance = HearMed_Settings::get( 'hm_patient_show_balance', '1' );
    $show_prsi    = HearMed_Settings::get( 'hm_patient_show_prsi', '1' );
    $is_admin     = hm_patient_is_admin();

    // Warranty status for active devices (green/yellow/red)
    $warranty_status = 'none';
    $warranty_days   = null;
    $active_dev = $db->get_row(
        "SELECT MIN(warranty_expiry) AS earliest_expiry
         FROM hearmed_core.patient_devices
         WHERE patient_id = \$1 AND device_status = 'Active' AND warranty_expiry IS NOT NULL",
        [ $pid ]
    );
    if ( $active_dev && $active_dev->earliest_expiry ) {
        $wexp  = new DateTime( $active_dev->earliest_expiry );
        $now   = new DateTime();
        $wdiff = $now->diff( $wexp );
        $wdays = (int) $wdiff->format( '%r%a' );
        $warranty_days = $wdays;
        if ( $wdays < 0 )        $warranty_status = 'expired';
        elseif ( $wdays <= 90 )  $warranty_status = 'expiring';
        else                      $warranty_status = 'active';
    }

    // PRSI last claimed date (from invoices where prsi_applicable = true)
    $prsi_claim = $db->get_row(
        "SELECT MAX(invoice_date) AS last_claim_date
         FROM hearmed_core.invoices
         WHERE patient_id = \$1 AND prsi_applicable = true AND payment_status != 'Void'",
        [ $pid ]
    );
    $last_prsi_claim  = $prsi_claim && $prsi_claim->last_claim_date ? $prsi_claim->last_claim_date : ($p->last_prsi_claim_date ?? '');
    $next_prsi_date   = '';
    if ( $last_prsi_claim ) {
        $lcd = new DateTime( $last_prsi_claim );
        $lcd->modify( '+4 years' );
        $next_prsi_date = $lcd->format( 'Y-m-d' );
    }

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
        'address_line1'         => $p->address_line1 ?: '',
        'address_line2'         => $p->address_line2 ?: '',
        'city'                  => $p->city ?: '',
        'county'                => $p->county ?: '',
        'eircode'               => $p->eircode ?: '',
        'assigned_clinic_id'    => $p->assigned_clinic_id ? (int) $p->assigned_clinic_id : null,
        'assigned_dispenser_id' => $p->assigned_dispenser_id ? (int) $p->assigned_dispenser_id : null,
        'clinic_name'           => $p->clinic_name ?: '—',
        'dispenser_name'        => $p->dispenser_name ?: '—',
        'prsi_eligible'         => hm_pg_bool( $p->prsi_eligible ),
        'prsi_number'           => $p->prsi_number ?: '',
        'last_prsi_claim_date'  => $p->last_prsi_claim_date ?? '',
        'next_prsi_eligible_date' => $p->next_prsi_eligible_date ?? '',
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
        'gp_name'               => $p->gp_name ?? '',
        'gp_address'            => $p->gp_address ?? '',
        'nok_name'              => $p->nok_name ?? '',
        'nok_phone'             => $p->nok_phone ?? '',
        'stats'                 => $stats,
        'has_finance'           => $show_balance === '1',
        'show_prsi'             => $show_prsi === '1',
        'is_admin'              => $is_admin,
        'can_export'            => $is_admin,
        'show_audit'            => $is_admin,
        'warranty_status'       => $warranty_status,
        'warranty_days'         => $warranty_days,
        'last_prsi_claim_date'  => $last_prsi_claim,
        'next_prsi_eligible_date' => $next_prsi_date,
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

    try {
    $db       = HearMed_DB::instance();
    $staff_id = hm_patient_staff_id();

    // Check if GP/NOK columns exist (added after initial schema)
    $has_gp = $db->get_var(
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema='hearmed_core' AND table_name='patients' AND column_name='gp_name'"
    );
    if ( ! $has_gp ) {
        // Try to add them
        @$db->get_results( "ALTER TABLE hearmed_core.patients
            ADD COLUMN IF NOT EXISTS gp_name VARCHAR(200),
            ADD COLUMN IF NOT EXISTS gp_address TEXT,
            ADD COLUMN IF NOT EXISTS nok_name VARCHAR(200),
            ADD COLUMN IF NOT EXISTS nok_phone VARCHAR(30)" );
        // Re-check
        $has_gp = $db->get_var(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema='hearmed_core' AND table_name='patients' AND column_name='gp_name'"
        );
    }

    $data = [
        'patient_title'       => sanitize_text_field( $_POST['patient_title'] ?? '' ),
        'first_name'          => sanitize_text_field( $_POST['first_name'] ?? '' ),
        'last_name'           => sanitize_text_field( $_POST['last_name'] ?? '' ),
        'date_of_birth'       => sanitize_text_field( $_POST['dob'] ?? '' ) ?: null,
        'phone'               => sanitize_text_field( $_POST['patient_phone'] ?? '' ),
        'mobile'              => sanitize_text_field( $_POST['patient_mobile'] ?? '' ),
        'email'               => sanitize_email( $_POST['patient_email'] ?? '' ),
        'address_line1'       => sanitize_text_field( $_POST['address_line1'] ?? sanitize_textarea_field( $_POST['patient_address'] ?? '' ) ),
        'address_line2'       => sanitize_text_field( $_POST['address_line2'] ?? '' ),
        'city'                => sanitize_text_field( $_POST['city'] ?? '' ),
        'county'              => sanitize_text_field( $_POST['county'] ?? '' ),
        'eircode'             => sanitize_text_field( $_POST['patient_eircode'] ?? '' ),
        'prsi_number'         => sanitize_text_field( $_POST['prsi_number'] ?? '' ),
        'prsi_eligible'       => ( $_POST['prsi_eligible'] ?? '0' ) === '1',
        'is_active'           => ( $_POST['is_active'] ?? '1' ) === '1',
        'annual_review_date'  => sanitize_text_field( $_POST['annual_review_date'] ?? '' ) ?: null,
        'assigned_clinic_id'  => intval( $_POST['assigned_clinic_id'] ?? 0 ) ?: null,
        'assigned_dispenser_id' => intval( $_POST['assigned_dispenser_id'] ?? 0 ) ?: null,
        'updated_by'          => $staff_id ?: null,
        'updated_at'          => date( 'c' ),
    ];

    // Only include GP/NOK fields if columns exist in the database
    if ( $has_gp ) {
        $data['gp_name']    = sanitize_text_field( $_POST['gp_name'] ?? '' );
        $data['gp_address'] = sanitize_textarea_field( $_POST['gp_address'] ?? '' );
        $data['nok_name']   = sanitize_text_field( $_POST['nok_name'] ?? '' );
        $data['nok_phone']  = sanitize_text_field( $_POST['nok_phone'] ?? '' );
    }

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
    if ( $result === false ) {
        $db_err = HearMed_DB::last_error();
        error_log( '[HearMed] update_patient FAILED for pid=' . $pid . ' — ' . $db_err );
        error_log( '[HearMed] update_patient data: ' . wp_json_encode( $data ) );
        wp_send_json_error( 'Update failed: ' . $db_err );
    }

    hm_patient_audit( 'UPDATE', 'patient', $pid, $data );

    wp_send_json_success( true );

    } catch ( \Throwable $e ) {
        error_log( '[HearMed] update_patient exception: ' . $e->getMessage() );
        wp_send_json_error( 'Save error: ' . $e->getMessage() );
    }
}


// ═══════════════════════════════════════════════════════════════════════════
//  6. NOTES — CRUD
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Auto-migrate: ensure note_category and is_alert columns exist.
 * Returns true if the new columns are available, false if migration failed.
 */
function hm_ensure_notes_columns() {
    static $result = null;
    if ( $result !== null ) return $result;

    $db = HearMed_DB::instance();

    // Check if note_category column already exists
    $has_category = $db->get_var(
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema = 'hearmed_core' AND table_name = 'patient_notes' AND column_name = 'note_category'"
    );

    if ( $has_category ) {
        $result = true;
        return true;
    }

    // Columns don't exist — try to add them
    error_log( '[HearMed Notes] Auto-migrating: adding note_category and is_alert columns...' );

    $ok1 = $db->query( "ALTER TABLE hearmed_core.patient_notes ADD COLUMN IF NOT EXISTS note_category VARCHAR(20) NOT NULL DEFAULT 'general'" );
    $ok2 = $db->query( "ALTER TABLE hearmed_core.patient_notes ADD COLUMN IF NOT EXISTS is_alert BOOLEAN NOT NULL DEFAULT false" );

    if ( $ok1 && $ok2 ) {
        // Back-fill existing Clinical notes
        $db->query( "UPDATE hearmed_core.patient_notes SET note_category = 'clinical' WHERE LOWER(note_type) = 'clinical'" );
        error_log( '[HearMed Notes] Auto-migration SUCCESS' );
        $result = true;
    } else {
        error_log( '[HearMed Notes] Auto-migration FAILED: ' . HearMed_DB::last_error() );
        $result = false;
    }

    return $result;
}

function hm_ajax_get_patient_notes() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );

    $has_new_cols = hm_ensure_notes_columns();

    $db       = HearMed_DB::instance();
    $category = sanitize_text_field( $_POST['note_category'] ?? '' );

    // Build query — use new columns only if they exist
    if ( $has_new_cols ) {
        $sql = "SELECT n.id, n.note_type, n.note_text, n.created_by, n.created_at,
                       COALESCE(n.is_pinned, false) AS is_pinned,
                       COALESCE(n.note_category, 'general') AS note_category,
                       COALESCE(n.is_alert, false) AS is_alert,
                       COALESCE(s.first_name || ' ' || s.last_name, 'System') AS created_by_name
                FROM hearmed_core.patient_notes n
                LEFT JOIN hearmed_reference.staff s ON s.id = n.created_by
                WHERE n.patient_id = \$1 AND n.note_type != 'AI Transcript'";
    } else {
        $sql = "SELECT n.id, n.note_type, n.note_text, n.created_by, n.created_at,
                       COALESCE(n.is_pinned, false) AS is_pinned,
                       'general' AS note_category,
                       false AS is_alert,
                       COALESCE(s.first_name || ' ' || s.last_name, 'System') AS created_by_name
                FROM hearmed_core.patient_notes n
                LEFT JOIN hearmed_reference.staff s ON s.id = n.created_by
                WHERE n.patient_id = \$1 AND n.note_type != 'AI Transcript'";
    }
    $params = [ $pid ];

    if ( $has_new_cols && $category && in_array( $category, [ 'general', 'appointment', 'clinical' ], true ) ) {
        $sql .= " AND COALESCE(n.note_category, 'general') = \$2";
        $params[] = $category;
    }

    $sql .= " ORDER BY COALESCE(n.is_pinned, false) DESC, n.created_at DESC";

    $rows = $db->get_results( $sql, $params );

    $staff_id = hm_patient_staff_id();
    $is_admin = hm_patient_is_admin();

    $out = [];
    $counts = [ 'general' => 0, 'appointment' => 0, 'clinical' => 0 ];
    foreach ( $rows as $r ) {
        $cat = $r->note_category ?? 'general';
        if ( isset( $counts[ $cat ] ) ) $counts[ $cat ]++;
        $out[] = [
            '_ID'           => (int) $r->id,
            'note_type'     => $r->note_type,
            'note_text'     => $r->note_text,
            'note_category' => $cat,
            'is_pinned'     => hm_pg_bool( $r->is_pinned ),
            'is_alert'      => hm_pg_bool( $r->is_alert ?? false ),
            'created_by'    => $r->created_by_name,
            'created_at'    => $r->created_at,
            'can_edit'      => $is_admin || (int) $r->created_by === $staff_id,
        ];
    }
    wp_send_json_success( [ 'notes' => $out, 'counts' => $counts ] );
}

function hm_ajax_save_patient_note() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    $has_new_cols = hm_ensure_notes_columns();

    $pid      = intval( $_POST['patient_id'] ?? 0 );
    $nid      = intval( $_POST['_ID'] ?? 0 );
    $type     = sanitize_text_field( $_POST['note_type'] ?? 'Manual' );
    $text     = sanitize_textarea_field( $_POST['note_text'] ?? '' );
    $category = sanitize_text_field( $_POST['note_category'] ?? 'general' );
    $is_alert = ! empty( $_POST['is_alert'] ) && $_POST['is_alert'] !== '0';

    if ( ! in_array( $category, [ 'general', 'appointment', 'clinical' ], true ) ) {
        $category = 'general';
    }

    if ( ! $pid || ! $text ) wp_send_json_error( 'Patient ID and note text required' );

    $db       = HearMed_DB::instance();
    $staff_id = hm_patient_staff_id();

    if ( $nid ) {
        $update_data = [
            'note_type'  => $type,
            'note_text'  => $text,
            'updated_at' => date( 'c' ),
        ];
        if ( $has_new_cols ) {
            $update_data['note_category'] = $category;
            $update_data['is_alert']      = $is_alert;
        }
        $db->update( 'hearmed_core.patient_notes', $update_data, [ 'id' => $nid ] );
        hm_patient_audit( 'UPDATE_NOTE', 'patient_note', $nid, [ 'patient_id' => $pid ] );
    } else {
        $insert_data = [
            'patient_id' => $pid,
            'note_type'  => $type,
            'note_text'  => $text,
            'created_by' => $staff_id ?: null,
        ];
        if ( $has_new_cols ) {
            $insert_data['note_category'] = $category;
            $insert_data['is_alert']      = $is_alert;
        }
        $nid = $db->insert( 'hearmed_core.patient_notes', $insert_data );
        if ( ! $nid ) {
            error_log( '[HearMed Notes] INSERT failed: ' . HearMed_DB::last_error() );
            wp_send_json_error( 'Failed to save note' );
        }
        hm_patient_audit( 'CREATE_NOTE', 'patient_note', $nid, [ 'patient_id' => $pid ] );
    }

    wp_send_json_success( [ 'id' => $nid ] );
}

/**
 * Get alert notes for a patient — used for the popup on profile load.
 */
function hm_ajax_get_patient_alerts() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );

    $has_new_cols = hm_ensure_notes_columns();

    // If columns don't exist, there can be no alerts
    if ( ! $has_new_cols ) {
        wp_send_json_success( [] );
    }

    $db   = HearMed_DB::instance();
    $rows = $db->get_results(
        "SELECT n.id, n.note_type, n.note_text, n.note_category, n.created_at,
                COALESCE(s.first_name || ' ' || s.last_name, 'System') AS created_by_name
         FROM hearmed_core.patient_notes n
         LEFT JOIN hearmed_reference.staff s ON s.id = n.created_by
         WHERE n.patient_id = \$1 AND COALESCE(n.is_alert, false) = true
         ORDER BY n.created_at DESC",
        [ $pid ]
    );

    $out = [];
    foreach ( $rows as $r ) {
        $out[] = [
            '_ID'        => (int) $r->id,
            'note_text'  => $r->note_text,
            'note_type'  => $r->note_type,
            'category'   => $r->note_category ?? 'general',
            'created_by' => $r->created_by_name,
            'created_at' => $r->created_at,
        ];
    }
    wp_send_json_success( $out );
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

    // Check if model/tech_level columns exist in products table
    $has_model = $db->get_var(
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema = 'hearmed_reference' AND table_name = 'products' AND column_name = 'model'"
    );
    $has_tech = $db->get_var(
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema = 'hearmed_reference' AND table_name = 'products' AND column_name = 'tech_level'"
    );
    $model_col = $has_model ? "COALESCE(pr.model, '') AS model," : "'' AS model,";
    $tech_col  = $has_tech  ? "COALESCE(pr.tech_level, '') AS tech_level," : "'' AS tech_level,";

    $rows = $db->get_results(
        "SELECT pd.id, pd.product_id, pd.serial_number_left, pd.serial_number_right,
                pd.fitting_date, pd.device_status, pd.inactive_reason, pd.warranty_expiry,
                COALESCE(pr.product_name, 'Unknown') AS product_name,
                COALESCE(m.name, '') AS manufacturer,
                pr.style,
                $model_col
                $tech_col
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
            'model'          => $r->model ?: '',
            'tech_level'     => $r->tech_level ?: '',
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

    // Serial number uniqueness check
    $serial_left  = sanitize_text_field( $_POST['serial_number_left'] ?? '' );
    $serial_right = sanitize_text_field( $_POST['serial_number_right'] ?? '' );

    if ( $serial_left ) {
        $dup = $db->get_var(
            "SELECT id FROM hearmed_core.patient_devices
             WHERE (serial_number_left = \$1 OR serial_number_right = \$1)
               AND device_status = 'Active'",
            [ $serial_left ]
        );
        if ( $dup ) wp_send_json_error( 'Serial number (Left) "' . $serial_left . '" is already assigned to an active device.' );
    }
    if ( $serial_right ) {
        $dup = $db->get_var(
            "SELECT id FROM hearmed_core.patient_devices
             WHERE (serial_number_left = \$1 OR serial_number_right = \$1)
               AND device_status = 'Active'",
            [ $serial_right ]
        );
        if ( $dup ) wp_send_json_error( 'Serial number (Right) "' . $serial_right . '" is already assigned to an active device.' );
    }

    $id = $db->insert( 'hearmed_core.patient_devices', [
        'patient_id'          => $pid,
        'product_id'          => $product_id,
        'serial_number_left'  => $serial_left,
        'serial_number_right' => $serial_right,
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

    $db = HearMed_DB::instance();

    // Direct query — known schema columns
    // appointments: staff_id, service_id, appointment_type_id, appointment_status
    // services: service_color (NOT colour), duration_minutes (NOT duration)
    $rows = $db->get_results(
        "SELECT a.id, a.appointment_date, a.start_time, a.end_time,
                a.appointment_status AS status, a.notes, a.outcome,
                COALESCE(sv.service_name, '') AS service_name,
                COALESCE(sv.service_color, '#3B82F6') AS service_colour,
                COALESCE(c.clinic_name, '') AS clinic_name,
                COALESCE(st.first_name || ' ' || st.last_name, '') AS dispenser_name
         FROM hearmed_core.appointments a
         LEFT JOIN hearmed_reference.appointment_types sv ON sv.id = a.service_id
         LEFT JOIN hearmed_reference.clinics c ON c.id = a.clinic_id
         LEFT JOIN hearmed_reference.staff st ON st.id = a.staff_id
         WHERE a.patient_id = \$1
         ORDER BY a.appointment_date DESC, a.start_time DESC",
        [ $pid ]
    );

    $out = [];
    foreach ( ($rows ?: []) as $r ) {
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
            'outcome_name'          => $r->outcome ?: '',
            'outcome_banner_colour' => '',
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
                     AND LOWER(TRIM(COALESCE(o.current_status, ''))) <> 'awaiting approval'
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

    $show_balance = HearMed_Settings::get( 'hm_patient_show_balance', '1' );
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

    // Log the download
    $db  = HearMed_DB::instance();
    $inv = $db->get_row( "SELECT invoice_number FROM hearmed_core.invoices WHERE id = \$1", [ $id ] );
    if ( ! $inv ) wp_die( 'Invoice not found' );
    hm_patient_audit( 'DOWNLOAD_INVOICE', 'invoice', $id, [ 'invoice' => $inv->invoice_number ] );

    // Render via the configurable template engine (same as fitting receipt)
    $data = HearMed_Invoice::get_invoice_data( $id );
    if ( ! $data ) wp_die( 'Invoice data not found' );

    $tpl = HearMed_Invoice::build_template_data( $data );
    header( 'Content-Type: text/html; charset=utf-8' );
    echo HearMed_Print_Templates::render( 'invoice', $tpl );
    exit;
}

/**
 * Download order PDF from patient file.
 * Mirrors the invoice download pattern exactly — same nonce, same flow.
 */
function hm_ajax_download_order_pdf() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $order_id = intval( $_GET['order_id'] ?? 0 );
    if ( ! $order_id ) wp_die( 'Missing order ID' );

    // Log the download
    $db    = HearMed_DB::instance();
    $order = $db->get_row(
        "SELECT order_number, patient_id FROM hearmed_core.orders WHERE id = \$1",
        [ $order_id ]
    );
    if ( ! $order ) wp_die( 'Order not found' );
    hm_patient_audit( 'DOWNLOAD_ORDER_PDF', 'order', $order_id, [ 'order' => $order->order_number ] );

    // Delegate to existing render_order_sheet method
    $html = HearMed_Orders::render_order_sheet( $order_id );
    header( 'Content-Type: text/html; charset=utf-8' );
    echo $html;
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
        "SELECT r.id, r.repair_number, r.serial_number, r.date_booked, r.date_sent, r.date_received,
                r.repair_status AS status, r.warranty_status, r.repair_notes,
                r.repair_reason, r.under_warranty, r.sent_to,
                COALESCE(pr.product_name, 'Unknown') AS product_name,
                COALESCE(m.name, '') AS manufacturer_name,
                r.created_at
         FROM hearmed_core.repairs r
         LEFT JOIN hearmed_core.patient_devices pd ON pd.id = r.patient_device_id
         LEFT JOIN hearmed_reference.products pr ON pr.id = COALESCE(r.product_id, pd.product_id)
         LEFT JOIN hearmed_reference.manufacturers m ON m.id = COALESCE(r.manufacturer_id, pr.manufacturer_id)
         WHERE r.patient_id = \$1
         ORDER BY r.date_booked DESC",
        [ $pid ]
    );

    $out = [];
    foreach ( $rows as $r ) {
        // Calculate days since booked for status colouring
        $days_open = 0;
        if ( $r->date_booked && ! $r->date_received ) {
            $booked = new DateTime( $r->date_booked );
            $now    = new DateTime();
            $days_open = (int) $booked->diff( $now )->days;
        }
        $out[] = [
            '_ID'              => (int) $r->id,
            'repair_number'    => $r->repair_number ?: '',
            'product_name'     => $r->product_name,
            'manufacturer'     => $r->manufacturer_name,
            'serial_number'    => $r->serial_number ?: '',
            'date_booked'      => $r->date_booked,
            'date_sent'        => $r->date_sent,
            'date_received'    => $r->date_received,
            'status'           => $r->status ?: 'Booked',
            'warranty_status'  => $r->warranty_status ?: '',
            'under_warranty'   => hm_pg_bool( $r->under_warranty ?? false ),
            'repair_notes'     => $r->repair_notes ?: '',
            'repair_reason'    => $r->repair_reason ?: '',
            'sent_to'          => $r->sent_to ?: '',
            'days_open'        => $days_open,
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

    // Get clinic_id from the patient record
    $clinic_id = null;
    $patient = $db->get_row( "SELECT assigned_clinic_id FROM hearmed_core.patients WHERE id = \$1", [ $pid ] );
    if ( $patient && $patient->assigned_clinic_id ) {
        $clinic_id = (int) $patient->assigned_clinic_id;
    }

    // Resolve manufacturer_id — from form, or from product record
    $mfr_id = intval( $_POST['manufacturer_id'] ?? 0 ) ?: null;
    if ( ! $mfr_id && $product_id ) {
        $prod = $db->get_row( "SELECT manufacturer_id FROM hearmed_reference.products WHERE id = \$1", [ $product_id ] );
        if ( $prod && $prod->manufacturer_id ) $mfr_id = (int) $prod->manufacturer_id;
    }

    // Build insert data — start with columns guaranteed to exist in schema
    $data = [
        'patient_id'       => $pid,
        'patient_device_id'=> $pp_id,
        'product_id'       => $product_id,
        'serial_number'    => sanitize_text_field( $_POST['serial_number'] ?? '' ),
        'warranty_status'  => sanitize_text_field( $_POST['warranty_status'] ?? 'Unknown' ),
        'repair_notes'     => sanitize_textarea_field( $_POST['repair_notes'] ?? '' ),
        'manufacturer_id'  => $mfr_id,
        'clinic_id'        => $clinic_id,
        'date_booked'      => date( 'Y-m-d' ),
        'repair_status'    => 'Booked',
        'staff_id'         => $staff_id ?: null,
        'created_by'       => $staff_id ?: null,
    ];

    // Add optional columns (may not exist yet — migration adds them)
    $data['under_warranty'] = ( $_POST['under_warranty'] ?? '0' ) === '1';
    $data['repair_reason']  = sanitize_textarea_field( $_POST['repair_reason'] ?? '' );

    $id = $db->insert( 'hearmed_core.repairs', $data );

    // If insert failed, retry without optional columns
    if ( ! $id ) {
        error_log( '[HM Repairs] Insert with extended columns failed: ' . HearMed_DB::last_error() . ' — retrying with base columns' );
        unset( $data['under_warranty'], $data['repair_reason'] );
        $id = $db->insert( 'hearmed_core.repairs', $data );
    }

    if ( ! $id ) wp_send_json_error( 'Failed to create repair — ' . HearMed_DB::last_error() );

    // Generate HMREP number — try sequence, fallback to MAX+1
    $prefix = HearMed_Settings::get( 'hm_repair_prefix', 'HMREP' );
    $seq    = $db->get_var( "SELECT nextval('hearmed_core.repair_number_seq')" );
    if ( ! $seq ) {
        // Sequence may not exist yet — fallback
        $seq = $db->get_var( "SELECT COALESCE(MAX(id), 0) FROM hearmed_core.repairs" );
    }
    $repair_number = $prefix . '-' . str_pad( $seq, 4, '0', STR_PAD_LEFT );

    // Try to update repair_number — may fail if column doesn't exist yet
    $db->update( 'hearmed_core.repairs', [ 'repair_number' => $repair_number ], [ 'id' => $id ] );

    hm_patient_audit( 'CREATE_REPAIR', 'repair', $id, [ 'patient_id' => $pid, 'repair_number' => $repair_number ] );
    wp_send_json_success( [ 'id' => $id, 'repair_number' => $repair_number ] );
}


// ═══════════════════════════════════════════════════════════════════════════
//  13. RETURNS / CREDIT NOTES
//      Reads from hearmed_core.credit_notes, integrates with orders
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_get_patient_returns() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );

    $db = HearMed_DB::instance();

    // Check if returns table exists for join
    $has_returns = $db->get_var(
        "SELECT 1 FROM information_schema.tables WHERE table_schema = 'hearmed_core' AND table_name = 'returns'"
    );

    $return_join  = $has_returns ? "LEFT JOIN hearmed_core.returns ret ON ret.credit_note_id = cn.id" : "";
    $return_cols  = $has_returns ? "ret.return_date, ret.side AS return_side, ret.patient_refund_amount AS ret_patient_amt, ret.prsi_refund_amount AS ret_prsi_amt," : "cn.credit_date AS return_date, 'both' AS return_side, cn.amount AS ret_patient_amt, 0::numeric AS ret_prsi_amt,";

    // Check if prsi columns exist
    $has_prsi = $db->get_var(
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema = 'hearmed_core' AND table_name = 'credit_notes' AND column_name = 'prsi_amount'"
    );
    $prsi_cols = $has_prsi ? "cn.prsi_amount, cn.patient_refund_amount," : "0 AS prsi_amount, cn.amount AS patient_refund_amount,";

    $rows = $db->get_results(
        "SELECT cn.id, cn.credit_note_number, cn.amount AS refund_amount,
                cn.reason, cn.credit_date, cn.cheque_sent,
                cn.cheque_sent_date,
                {$prsi_cols}
                {$return_cols}
                COALESCE(
                    (SELECT string_agg(pr.product_name, ', ')
                     FROM hearmed_core.order_items oi
                     JOIN hearmed_reference.products pr ON pr.id = oi.item_id
                     WHERE oi.order_id = cn.order_id AND oi.item_type = 'product'),
                    'N/A'
                ) AS product_name
         FROM hearmed_core.credit_notes cn
         {$return_join}
         WHERE cn.patient_id = \$1
         ORDER BY cn.credit_date DESC",
        [ $pid ]
    );

    $out = [];
    foreach ( $rows as $r ) {
        $out[] = [
            '_ID'                  => (int) $r->id,
            'credit_note_id'       => (int) $r->id,
            'credit_note_num'      => $r->credit_note_number,
            'refund_amount'        => (float) $r->refund_amount,
            'patient_refund_amount'=> (float) ($r->patient_refund_amount ?? $r->refund_amount),
            'prsi_amount'          => (float) ($r->prsi_amount ?? 0),
            'product_name'         => $r->product_name,
            'return_date'          => $r->return_date ?? $r->credit_date,
            'return_side'          => $r->return_side ?? 'both',
            'credit_date'          => $r->credit_date,
            'cheque_sent'          => hm_pg_bool( $r->cheque_sent ),
            'cheque_sent_date'     => $r->cheque_sent_date,
            'reason'               => $r->reason ?? '',
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
    $templates = HearMed_Settings::get( 'hm_form_templates', '' );
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
       . 'h1{font-size:20px;border-bottom:2px solid var(--hm-teal);padding-bottom:8px;}'
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

/**
 * Receive base64-encoded audio from browser, send to Groq Whisper,
 * return transcript text. Audio never touches disk.
 */
function hm_ajax_transcribe_audio() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    $audio_b64 = $_POST['audio'] ?? '';
    if ( empty( $audio_b64 ) ) {
        wp_send_json_error( 'No audio data received' );
    }

    // Get Groq settings from Postgres
    $api_key = HearMed_Settings::get( 'hm_groq_api_key', '' );
    $model   = HearMed_Settings::get( 'hm_groq_whisper_model', 'whisper-large-v3-turbo' );

    if ( empty( $api_key ) ) {
        wp_send_json_error( 'Groq API key not configured. Go to Admin Settings → AI.' );
    }

    // Decode base64 audio
    $audio_binary = base64_decode( $audio_b64 );
    if ( $audio_binary === false || strlen( $audio_binary ) < 100 ) {
        wp_send_json_error( 'Invalid audio data' );
    }

    // Check size — Groq limit is 25MB
    $size_mb = strlen( $audio_binary ) / ( 1024 * 1024 );
    if ( $size_mb > 25 ) {
        wp_send_json_error( 'Audio too large (' . round( $size_mb, 1 ) . 'MB). Max 25MB.' );
    }

    // Build multipart form data for Groq API
    $boundary = wp_generate_password( 24, false );
    $body  = '';
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Disposition: form-data; name=\"file\"; filename=\"recording.webm\"\r\n";
    $body .= "Content-Type: audio/webm\r\n\r\n";
    $body .= $audio_binary . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Disposition: form-data; name=\"model\"\r\n\r\n";
    $body .= $model . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Disposition: form-data; name=\"language\"\r\n\r\n";
    $body .= "en\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Disposition: form-data; name=\"response_format\"\r\n\r\n";
    $body .= "verbose_json\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Disposition: form-data; name=\"temperature\"\r\n\r\n";
    $body .= "0.0\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Disposition: form-data; name=\"prompt\"\r\n\r\n";
    $body .= "Audiology consultation. Terms: audiogram, tympanometry, otoscopy, hearing aid, " .
             "tinnitus, sensorineural, conductive, presbycusis, cerumen, otovent, " .
             "microsuction, REM, real ear measurement, PRSI, ENT, GP referral.\r\n";
    $body .= "--{$boundary}--\r\n";

    $start_time = microtime( true );

    $response = wp_remote_post( 'https://api.groq.com/openai/v1/audio/transcriptions', [
        'headers' => [
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
        ],
        'body'    => $body,
        'timeout' => 120,
    ] );

    $duration_ms = round( ( microtime( true ) - $start_time ) * 1000 );

    if ( is_wp_error( $response ) ) {
        HearMed_Logger::log( 'transcription', 'Groq error: ' . $response->get_error_message() );
        wp_send_json_error( 'Transcription failed: ' . $response->get_error_message() );
    }

    $status_code = wp_remote_retrieve_response_code( $response );
    $resp_body   = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $status_code !== 200 ) {
        $err = $resp_body['error']['message'] ?? "HTTP {$status_code}";
        HearMed_Logger::log( 'transcription', "Groq HTTP error: {$err}" );
        wp_send_json_error( 'Transcription failed: ' . $err );
    }

    $transcript    = $resp_body['text'] ?? '';
    $duration_secs = isset( $resp_body['duration'] ) ? round( $resp_body['duration'] ) : 0;

    if ( empty( trim( $transcript ) ) ) {
        wp_send_json_error( 'No speech detected in audio' );
    }

    // Log success
    HearMed_Logger::log( 'transcription', sprintf(
        'Groq transcription: model=%s, audio_duration=%ds, api_time=%dms, words=%d',
        $model, $duration_secs, $duration_ms, str_word_count( $transcript )
    ) );

    wp_send_json_success( [
        'transcript'     => $transcript,
        'duration_secs'  => $duration_secs,
        'word_count'     => str_word_count( $transcript ),
    ] );
}

/**
 * Ensure appointment_transcripts has ALL columns the code expects.
 * The original migration used staff_id / transcript_hash / duration_secs / word_count.
 * Later code introduced aliases: created_by / checksum_hash / duration_seconds.
 * This adds BOTH sets so SELECTs and INSERTs always work regardless of which
 * migration has been run.
 */
function hm_ensure_transcript_columns() {
    static $done = false;
    if ( $done ) return;
    $done = true;

    $exists = HearMed_DB::get_var( "SELECT to_regclass('hearmed_admin.appointment_transcripts')" );
    if ( $exists === null ) return; // table doesn't exist yet

    @HearMed_DB::query(
        "ALTER TABLE hearmed_admin.appointment_transcripts
         ADD COLUMN IF NOT EXISTS staff_id         INTEGER,
         ADD COLUMN IF NOT EXISTS transcript_hash  VARCHAR(64),
         ADD COLUMN IF NOT EXISTS duration_secs    INTEGER DEFAULT 0,
         ADD COLUMN IF NOT EXISTS word_count       INTEGER DEFAULT 0,
         ADD COLUMN IF NOT EXISTS created_by       INTEGER,
         ADD COLUMN IF NOT EXISTS checksum_hash    VARCHAR(64),
         ADD COLUMN IF NOT EXISTS duration_seconds INTEGER DEFAULT 0"
    );
}

function hm_ajax_save_ai_transcript() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid  = intval( $_POST['patient_id'] ?? 0 );
    $text = sanitize_textarea_field( $_POST['transcript'] ?? '' );

    if ( ! $pid || ! $text ) wp_send_json_error( 'Patient ID and transcript required' );

    /* Auto-migrate transcript table columns (runs once per request) */
    hm_ensure_transcript_columns();

    $db             = HearMed_DB::instance();
    $staff_id       = hm_patient_staff_id();
    $appointment_id = intval( $_POST['appointment_id'] ?? 0 );
    $template_id    = intval( $_POST['template_id'] ?? 0 );
    $word_count     = str_word_count( $text );
    $duration_secs  = intval( $_POST['duration_secs'] ?? 0 );
    $hash           = hash( 'sha256', $text );

    /* ── 1. Save to appointment_transcripts (clinical pipeline — case history tab) ── */
    /*  Populate BOTH original (staff_id, transcript_hash, duration_secs,
        word_count) AND alias (created_by, checksum_hash, duration_seconds)
        columns so the INSERT works regardless of which migration has run. */
    $transcript_id = null;
    $transcript_id = $db->insert( 'hearmed_admin.appointment_transcripts', [
        'appointment_id'   => $appointment_id ?: null,
        'patient_id'       => $pid,
        'staff_id'         => $staff_id ?: null,
        'created_by'       => $staff_id ?: null,
        'transcript_text'  => $text,
        'transcript_hash'  => $hash,
        'checksum_hash'    => $hash,
        'word_count'       => $word_count,
        'duration_secs'    => $duration_secs ?: 0,
        'duration_seconds' => $duration_secs ?: 0,
        'source'           => 'whisper',
        'created_at'       => current_time( 'mysql' ),
    ]);

    hm_patient_audit( 'SAVE_AI_TRANSCRIPT', 'appointment_transcript', $transcript_id, [
        'patient_id'     => $pid,
        'transcript_id'  => $transcript_id,
        'appointment_id' => $appointment_id,
        'template_id'    => $template_id,
        'word_count'     => $word_count,
    ]);

    /* ── 2. Trigger AI extraction ── */
    $clinical_doc_id = null;
    if ( $transcript_id ) {
        $clinical_doc_id = hm_trigger_ai_extraction( $pid, $appointment_id ?: 0, $transcript_id, $text, $template_id ?: 0 );
    }

    wp_send_json_success( [
        'transcript_id'   => $transcript_id,
        'clinical_doc_id' => $clinical_doc_id,
    ] );
}


// ═══════════════════════════════════════════════════════════════════════════
//  18b. AI DOCUMENT TYPES + PATIENT TRANSCRIPTS
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Return active, AI-enabled document templates for the dropdown picker.
 */
function hm_ajax_get_ai_document_types() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    $rows = HearMed_DB::get_results(
        "SELECT id, name, category
         FROM hearmed_admin.document_templates
         WHERE is_active = true AND ai_enabled = true
         ORDER BY sort_order, name"
    );

    $data = [];
    foreach ( ( $rows ?: [] ) as $r ) {
        $data[] = [
            'id'       => (int) $r->id,
            'name'     => $r->name,
            'category' => $r->category ?? 'clinical',
        ];
    }

    wp_send_json_success( $data );
}

/**
 * Return past transcripts + clinical docs for a patient.
 */
function hm_ajax_get_patient_transcripts() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );

    /* Ensure alias columns exist before SELECT references them */
    hm_ensure_transcript_columns();

    $db   = HearMed_DB::instance();
    $rows = $db->get_results(
        "SELECT t.id, t.appointment_id, t.transcript_text,
                COALESCE(t.duration_seconds, t.duration_secs, 0) AS duration_seconds,
                t.source, t.created_at,
                COALESCE(CONCAT(s.first_name, ' ', s.last_name), 'System') AS created_by_name,
                cd.id AS clinical_doc_id, cd.status AS doc_status,
                dt.name AS template_name
         FROM hearmed_admin.appointment_transcripts t
         LEFT JOIN hearmed_reference.staff s ON s.id = COALESCE(t.created_by, t.staff_id)
         LEFT JOIN hearmed_admin.appointment_clinical_docs cd ON cd.transcript_id = t.id
         LEFT JOIN hearmed_admin.document_templates dt ON dt.id = cd.template_id
         WHERE t.patient_id = \$1
         ORDER BY t.created_at DESC
         LIMIT 50",
        [ $pid ]
    );

    $data = [];
    foreach ( ( $rows ?: [] ) as $r ) {
        $data[] = [
            'id'               => (int) $r->id,
            'appointment_id'   => (int) ( $r->appointment_id ?? 0 ),
            'transcript_text'  => $r->transcript_text,
            'duration_seconds' => (int) ( $r->duration_seconds ?? 0 ),
            'source'           => $r->source ?? 'whisper',
            'created_at'       => $r->created_at,
            'created_by'       => $r->created_by_name ?? 'System',
            'clinical_doc_id'  => $r->clinical_doc_id ? (int) $r->clinical_doc_id : null,
            'doc_status'       => $r->doc_status ?? null,
            'template_name'    => $r->template_name ?? null,
        ];
    }

    wp_send_json_success( $data );
}


// ═══════════════════════════════════════════════════════════════════════════
//  19. ACTIVITY / AUDIT LOG
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_get_patient_audit() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );

    $db   = HearMed_DB::instance();
    $rows = $db->get_results(
        "SELECT al.action, al.entity_type, al.entity_id, al.details,
                al.created_at,
                COALESCE(CONCAT(s.first_name, ' ', s.last_name), 'System') AS user_display
         FROM hearmed_admin.audit_log al
         LEFT JOIN hearmed_reference.staff s ON s.wp_user_id = al.user_id
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


// ═══════════════════════════════════════════════════════════════════════════
//  21. TOGGLE NOTE PIN
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_toggle_note_pin() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $nid = intval( $_POST['_ID'] ?? 0 );
    if ( ! $nid ) wp_send_json_error( 'Missing note ID' );

    $db   = HearMed_DB::instance();
    $note = $db->get_row( "SELECT is_pinned, patient_id FROM hearmed_core.patient_notes WHERE id = \$1", [ $nid ] );
    if ( ! $note ) wp_send_json_error( 'Note not found' );

    $new_val = ! hm_pg_bool( $note->is_pinned ?? false );
    $db->update( 'hearmed_core.patient_notes', [
        'is_pinned'  => $new_val,
        'updated_at' => date( 'c' ),
    ], [ 'id' => $nid ] );

    hm_patient_audit( $new_val ? 'PIN_NOTE' : 'UNPIN_NOTE', 'patient_note', $nid, [
        'patient_id' => $note->patient_id,
    ]);

    wp_send_json_success( [ 'pinned' => $new_val ] );
}


// ═══════════════════════════════════════════════════════════════════════════
//  22. MANUFACTURERS LOOKUP
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_get_manufacturers() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $rows = HearMed_DB::get_results(
        "SELECT id, name, warranty_terms FROM hearmed_reference.manufacturers WHERE COALESCE(is_active::text,'true') NOT IN ('false','f','0') ORDER BY name"
    );
    $out = [];
    if ( $rows ) {
        foreach ( $rows as $r ) {
            $out[] = [
                'id'             => (int) $r->id,
                'name'           => $r->name,
                'warranty_terms' => $r->warranty_terms ?: '',
            ];
        }
    }
    wp_send_json_success( $out );
}


// ═══════════════════════════════════════════════════════════════════════════
//  22b. HEARING AID CATALOG — cascading dropdown data
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_get_ha_catalog() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $rows = HearMed_DB::get_results(
        "SELECT p.id, p.product_name, p.style, p.tech_level, p.manufacturer_id,
                m.name AS manufacturer_name, m.warranty_terms
         FROM hearmed_reference.products p
         LEFT JOIN hearmed_reference.manufacturers m ON m.id = p.manufacturer_id
         WHERE COALESCE(p.is_active::text,'true') NOT IN ('false','f','0')
           AND (p.item_type = 'product' OR p.category = 'Hearing Aid')
         ORDER BY m.name, p.product_name"
    );
    $out = [];
    if ( $rows ) {
        foreach ( $rows as $r ) {
            // Extract warranty years from warranty_terms (e.g. "4 years" → 4)
            $wy = 4;
            if ( ! empty( $r->warranty_terms ) && preg_match( '/(\d+)/', $r->warranty_terms, $wm ) ) {
                $wy = (int) $wm[1];
            }
            $out[] = [
                'id'                => (int) $r->id,
                'product_name'      => $r->product_name,
                'style'             => $r->style ?: '',
                'tech_level'        => $r->tech_level ?: '',
                'manufacturer_id'   => (int) $r->manufacturer_id,
                'manufacturer_name' => $r->manufacturer_name ?: '',
                'warranty_years'    => $wy,
            ];
        }
    }
    wp_send_json_success( $out );
}


// ═══════════════════════════════════════════════════════════════════════════
//  23. UPDATE REPAIR STATUS (sent / received back)
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_update_repair_status() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $id     = intval( $_POST['_ID'] ?? 0 );
    $status = sanitize_text_field( $_POST['status'] ?? '' );
    if ( ! $id || ! $status ) wp_send_json_error( 'Missing repair ID or status' );

    $db       = HearMed_DB::instance();
    $staff_id = hm_patient_staff_id();

    $update = [
        'repair_status' => $status,
        'updated_at'    => date( 'c' ),
    ];

    if ( $status === 'Sent' ) {
        $update['date_sent'] = sanitize_text_field( $_POST['date_sent'] ?? date( 'Y-m-d' ) );
        $update['sent_to']   = sanitize_text_field( $_POST['sent_to'] ?? '' );
        $update['tracking_number'] = sanitize_text_field( $_POST['tracking_number'] ?? '' );
    } elseif ( $status === 'Received' ) {
        $update['date_received'] = sanitize_text_field( $_POST['date_received'] ?? date( 'Y-m-d' ) );
        $update['received_by']   = $staff_id ?: null;
    }

    $db->update( 'hearmed_core.repairs', $update, [ 'id' => $id ] );

    // Notify dispenser when repair received back
    if ( $status === 'Received' ) {
        $repair = $db->get_row( "SELECT patient_id, staff_id, repair_number FROM hearmed_core.repairs WHERE id = \$1", [ $id ] );
        if ( $repair && $repair->staff_id ) {
            try {
                $db->insert( 'hearmed_communication.internal_notifications', [
                    'notification_type' => 'Repair Received',
                    'title'             => 'Repair ' . ( $repair->repair_number ?: '#' . $id ) . ' received back',
                    'message'           => 'Repair ' . ( $repair->repair_number ?: '#' . $id ) . ' has been received back and is ready for the patient.',
                    'priority'          => 'normal',
                    'reference_type'    => 'repair',
                    'reference_id'      => $id,
                    'created_by'        => $staff_id ?: null,
                ]);
            } catch ( \Throwable $e ) {}
        }
    }

    hm_patient_audit( 'UPDATE_REPAIR_STATUS', 'repair', $id, [ 'status' => $status ] );
    wp_send_json_success( true );
}


// ═══════════════════════════════════════════════════════════════════════════
//  24-A. GET EXCHANGE ITEM AMOUNT
//       Calculates per-item amount for the specific side being exchanged
//       NOT the entire invoice — just the items matching the ear side
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_get_exchange_item_amount() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $device_id = intval( $_POST['device_id'] ?? 0 );
    $side      = sanitize_text_field( $_POST['side'] ?? 'both' );

    if ( ! $device_id ) wp_send_json_error( 'Device required' );

    $db = HearMed_DB::instance();
    hm_ensure_returns_tables();

    $device = $db->get_row( "SELECT * FROM hearmed_core.patient_devices WHERE id = \$1", [ $device_id ] );
    if ( ! $device ) wp_send_json_error( 'Device not found' );

    // ── Find the order: direct link or fallback by product match ──
    $order_id_resolved = $device->order_id ?: null;

    if ( ! $order_id_resolved && $device->product_id ) {
        $fallback = $db->get_row(
            "SELECT o.id
             FROM hearmed_core.orders o
             JOIN hearmed_core.order_items oi ON oi.order_id = o.id
             WHERE o.patient_id = \$1 AND oi.item_id = \$2 AND oi.item_type = 'product'
             ORDER BY o.id DESC LIMIT 1",
            [ $device->patient_id, $device->product_id ]
        );
        if ( $fallback ) {
            $db->update( 'hearmed_core.patient_devices', [ 'order_id' => $fallback->id ], [ 'id' => $device_id ] );
            $order_id_resolved = $fallback->id;
        }
    }

    // Broader fallback: search by patient + product name match
    if ( ! $order_id_resolved && $device->product_id ) {
        $prod = $db->get_row( "SELECT product_name FROM hearmed_reference.products WHERE id = \$1", [ $device->product_id ] );
        if ( $prod ) {
            $fallback2 = $db->get_row(
                "SELECT o.id
                 FROM hearmed_core.orders o
                 JOIN hearmed_core.order_items oi ON oi.order_id = o.id
                 WHERE o.patient_id = \$1
                   AND LOWER(oi.item_description) LIKE LOWER(\$2)
                 ORDER BY o.id DESC LIMIT 1",
                [ $device->patient_id, '%' . $prod->product_name . '%' ]
            );
            if ( $fallback2 ) {
                $db->update( 'hearmed_core.patient_devices', [ 'order_id' => $fallback2->id ], [ 'id' => $device_id ] );
                $order_id_resolved = $fallback2->id;
            }
        }
    }

    if ( ! $order_id_resolved ) {
        wp_send_json_success( [
            'item_amount'    => 0,
            'prsi_amount'    => 0,
            'patient_amount' => 0,
            'has_order'      => false,
            'message'        => 'No original order found',
        ] );
        return;
    }

    // Get order
    $order = $db->get_row(
        "SELECT o.*, inv.id AS inv_id
         FROM hearmed_core.orders o
         LEFT JOIN hearmed_core.invoices inv ON inv.id = o.invoice_id
         WHERE o.id = \$1",
        [ $order_id_resolved ]
    );
    if ( ! $order ) {
        wp_send_json_success( [
            'item_amount'    => 0,
            'prsi_amount'    => 0,
            'patient_amount' => 0,
            'has_order'      => false,
            'message'        => 'Order record not found',
        ] );
        return;
    }

    // ── Calculate per-item amount ──
    // Try detailed match first, fallback to grand_total / sides
    $items = $db->get_results(
        "SELECT oi.ear_side, oi.line_total, oi.item_type, oi.item_description, oi.quantity
         FROM hearmed_core.order_items oi
         WHERE oi.order_id = \$1
         ORDER BY oi.line_number",
        [ $order_id_resolved ]
    );

    $all_items_total  = 0;
    $side_items_total = 0;
    $ha_count         = 0; // count of hearing aid sides in the order

    foreach ( $items ?: [] as $item ) {
        $ear = strtolower( trim( $item->ear_side ?? '' ) );
        $lt  = floatval( $item->line_total );
        $all_items_total += $lt;

        // Count HA sides
        if ( strtolower( $item->item_type ?? '' ) === 'product' ) {
            if ( in_array( $ear, [ 'left', 'right' ] ) ) {
                $ha_count++;
            } elseif ( in_array( $ear, [ 'both', 'binaural', '' ] ) ) {
                $ha_count += intval( $item->quantity ?? 1 ) > 1 ? 2 : 2;
            }
        }

        if ( $side === 'both' ) {
            $side_items_total += $lt;
        } elseif ( $ear === $side ) {
            $side_items_total += $lt;
        } elseif ( in_array( $ear, [ 'both', 'binaural', '' ] ) ) {
            $side_items_total += $lt / 2;
        }
    }

    // Apply proportional discount
    $discount_total = floatval( $order->discount_total ?? 0 );
    if ( $discount_total > 0 && $all_items_total > 0 ) {
        $proportion      = $side_items_total / $all_items_total;
        $side_items_total = max( 0, $side_items_total - round( $discount_total * $proportion, 2 ) );
    }

    // ── FALLBACK: if per-item calc returned 0, use order grand_total ──
    if ( $side_items_total < 0.01 ) {
        $grand = floatval( $order->grand_total ?? 0 );
        if ( $ha_count < 1 ) $ha_count = ( $side === 'both' ? 1 : 2 );
        if ( $side === 'both' ) {
            $side_items_total = $grand;
        } else {
            $side_items_total = round( $grand / max( $ha_count, 1 ), 2 );
        }
    }

    // PRSI per side
    $prsi_per_side = 0;
    if ( $side === 'both' ) {
        $prsi_per_side = floatval( $order->prsi_amount ?? 0 );
    } else {
        $prsi_field    = 'prsi_' . $side;
        $prsi_per_side = ! empty( $order->$prsi_field ) ? 500 : 0;
    }

    $patient_amount = round( max( 0, $side_items_total - $prsi_per_side ), 2 );

    wp_send_json_success( [
        'item_amount'    => round( $side_items_total, 2 ),
        'prsi_amount'    => $prsi_per_side,
        'patient_amount' => $patient_amount,
        'has_order'      => true,
        'order_number'   => $order->order_number ?? '',
        'order_total'    => floatval( $order->grand_total ?? 0 ),
    ] );
}


// ═══════════════════════════════════════════════════════════════════════════
//  24-B. EXCHANGE FLOW
//       Creates credit note + marks old devices inactive
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_create_exchange() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid           = intval( $_POST['patient_id'] ?? 0 );
    $device_id     = intval( $_POST['device_id'] ?? 0 );
    $reason        = sanitize_textarea_field( $_POST['reason'] ?? '' );
    $side          = sanitize_text_field( $_POST['side'] ?? 'both' );
    $refund_type   = sanitize_text_field( $_POST['refund_type'] ?? 'credit' ); // credit | refund | same_tech
    $refund_amount = floatval( $_POST['refund_amount'] ?? 0 );
    $credit_amount = floatval( $_POST['credit_amount'] ?? 0 );
    $notes         = sanitize_textarea_field( $_POST['notes'] ?? '' );

    if ( ! $pid || ! $device_id ) wp_send_json_error( 'Patient and device required' );
    if ( ! $reason ) wp_send_json_error( 'Reason is required' );

    $db       = HearMed_DB::instance();
    $staff_id = hm_patient_staff_id();

    hm_ensure_returns_tables();

    $device = $db->get_row( "SELECT * FROM hearmed_core.patient_devices WHERE id = \$1", [ $device_id ] );
    if ( ! $device ) wp_send_json_error( 'Device not found' );

    // Fallback order lookup (same logic as get_exchange_item_amount)
    if ( ! $device->order_id && $device->product_id ) {
        $fallback = $db->get_row(
            "SELECT o.id FROM hearmed_core.orders o
             JOIN hearmed_core.order_items oi ON oi.order_id = o.id
             WHERE o.patient_id = \$1 AND oi.item_id = \$2 AND oi.item_type = 'product'
             ORDER BY o.id DESC LIMIT 1",
            [ $pid, $device->product_id ]
        );
        if ( ! $fallback ) {
            $prod = $db->get_row( "SELECT product_name FROM hearmed_reference.products WHERE id = \$1", [ $device->product_id ] );
            if ( $prod ) {
                $fallback = $db->get_row(
                    "SELECT o.id FROM hearmed_core.orders o
                     JOIN hearmed_core.order_items oi ON oi.order_id = o.id
                     WHERE o.patient_id = \$1 AND LOWER(oi.item_description) LIKE LOWER(\$2)
                     ORDER BY o.id DESC LIMIT 1",
                    [ $pid, '%' . $prod->product_name . '%' ]
                );
            }
        }
        if ( $fallback ) {
            $db->update( 'hearmed_core.patient_devices', [ 'order_id' => $fallback->id ], [ 'id' => $device_id ] );
            $device->order_id = $fallback->id;
        }
    }

    $order = null;
    $prsi_amount = 0;
    if ( $device->order_id ) {
        $order = $db->get_row(
            "SELECT o.*, inv.grand_total AS inv_total, inv.id AS inv_id
             FROM hearmed_core.orders o
             LEFT JOIN hearmed_core.invoices inv ON inv.id = o.invoice_id
             WHERE o.id = \$1",
            [ $device->order_id ]
        );

        if ( $order ) {
            if ( $side === 'both' ) {
                $prsi_amount = floatval( $order->prsi_amount ?? 0 );
            } else {
                $prsi_field  = 'prsi_' . $side;
                $prsi_amount = ! empty( $order->$prsi_field ) ? 500 : 0;
            }
        }
    }

    // ── SAME TECH EXCHANGE — no money, just swap device ──
    if ( $refund_type === 'same_tech' ) {
        $db->begin_transaction();
        try {
            $db->update( 'hearmed_core.patient_devices', [
                'device_status'  => 'Replaced',
                'inactive_reason'=> 'Exchange (same tech): ' . $reason,
                'inactive_date'  => date( 'Y-m-d' ),
                'updated_at'     => date( 'c' ),
            ], [ 'id' => $device_id ] );

            $exchange_number = HearMed_Utils::generate_exchange_number();

            $exchange_id = $db->insert( 'hearmed_core.exchanges', [
                'patient_id'          => $pid,
                'original_order_id'   => $device->order_id ?: null,
                'original_invoice_id' => $order->inv_id ?? null,
                'device_id'           => $device_id,
                'exchange_type'       => 'same_tech',
                'exchange_number'     => $exchange_number,
                'original_amount'     => $credit_amount,
                'credit_amount'       => 0,
                'refund_amount'       => 0,
                'prsi_amount'         => 0,
                'reason'              => $reason,
                'status'              => 'completed',
                'created_by'          => $staff_id ?: null,
            ]);

            hm_return_device_to_stock( $device, $side, 'Exchange (same tech)', $staff_id );

            $db->insert( 'hearmed_core.patient_timeline', [
                'patient_id'  => $pid,
                'event_type'  => 'exchange_created',
                'event_date'  => date('Y-m-d'),
                'staff_id'    => $staff_id ?: null,
                'description' => "Same-tech exchange. Old device returned to stock. Reason: {$reason}." . ( $notes ? " Notes: {$notes}" : '' ),
            ]);

            $db->commit();

            hm_patient_audit( 'CREATE_EXCHANGE', 'exchange', $exchange_id, [
                'patient_id' => $pid, 'device_id' => $device_id, 'type' => 'same_tech'
            ]);

            wp_send_json_success( [
                'exchange_id'        => $exchange_id,
                'exchange_number'    => $exchange_number,
                'refund_type'        => 'same_tech',
                'patient_credit'     => 0,
                'cash_refund'        => 0,
            ]);
        } catch ( \Exception $e ) {
            $db->rollback();
            wp_send_json_error( 'Exchange failed: ' . $e->getMessage() );
        }
        return;
    }

    // ── CREDIT / REFUND paths ──
    $patient_refund = max( 0, $credit_amount - $prsi_amount );

    // For refund path: refund_amount is the cash refund, remainder goes to patient credit
    $cash_refund     = 0;
    $credit_to_acct  = $patient_refund;
    if ( $refund_type === 'refund' ) {
        $cash_refund    = min( $refund_amount, $patient_refund );
        $credit_to_acct = max( 0, $patient_refund - $cash_refund );
    }

    $db->begin_transaction();

    try {
        // Mark device as Replaced
        $db->update( 'hearmed_core.patient_devices', [
            'device_status'  => 'Replaced',
            'inactive_reason'=> 'Exchange: ' . $reason,
            'inactive_date'  => date( 'Y-m-d' ),
            'updated_at'     => date( 'c' ),
        ], [ 'id' => $device_id ] );

        // Generate credit note number
        $cn_prefix = HearMed_Settings::get( 'hm_credit_note_prefix', 'HMCN' );
        $cn_seq    = $db->get_var( "SELECT COALESCE(MAX(id), 0) + 1 FROM hearmed_core.credit_notes" );
        $cn_number = $cn_prefix . '-' . str_pad( $cn_seq, 4, '0', STR_PAD_LEFT );

        // Create credit note
        $cn_id = $db->insert( 'hearmed_core.credit_notes', [
            'credit_note_number'    => $cn_number,
            'patient_id'            => $pid,
            'invoice_id'            => $order->inv_id ?? null,
            'order_id'              => $device->order_id ?: null,
            'device_id'             => $device_id,
            'amount'                => $credit_amount,
            'prsi_amount'           => $prsi_amount,
            'patient_refund_amount' => $patient_refund,
            'reason'                => 'Exchange: ' . $reason,
            'credit_date'           => date( 'Y-m-d' ),
            'refund_type'           => $refund_type,
            'exchange_type'         => $refund_type === 'refund' ? 'refund' : 'credit',
            'cheque_sent'           => false,
            'prsi_notified'         => false,
            'created_by'            => $staff_id ?: null,
        ]);

        // Create patient credit (credit to account, not the cash refund part)
        $credit_id = null;
        if ( $credit_to_acct > 0 ) {
            $credit_notes_text = $refund_type === 'refund'
                ? "Exchange credit from CN {$cn_number}. Item: €" . number_format($credit_amount, 2) . ", PRSI: €" . number_format($prsi_amount, 2) . ", Cash refund: €" . number_format($cash_refund, 2) . ", Credit on account: €" . number_format($credit_to_acct, 2)
                : "Exchange credit from CN {$cn_number}. Item: €" . number_format($credit_amount, 2) . ", PRSI: €" . number_format($prsi_amount, 2) . ", Patient credit: €" . number_format($credit_to_acct, 2);

            $credit_id = $db->insert( 'hearmed_core.patient_credits', [
                'patient_id'         => $pid,
                'credit_note_id'     => $cn_id,
                'original_invoice_id'=> $order->inv_id ?? null,
                'original_order_id'  => $device->order_id ?: null,
                'amount'             => $credit_to_acct,
                'used_amount'        => 0,
                'status'             => 'active',
                'notes'              => $credit_notes_text,
                'created_by'         => $staff_id ?: null,
            ]);
        }

        // Create exchange record
        $exchange_number = HearMed_Utils::generate_exchange_number();

        $exchange_id = $db->insert( 'hearmed_core.exchanges', [
            'patient_id'          => $pid,
            'original_order_id'   => $device->order_id ?: null,
            'original_invoice_id' => $order->inv_id ?? null,
            'credit_note_id'      => $cn_id,
            'patient_credit_id'   => $credit_id,
            'device_id'           => $device_id,
            'exchange_type'       => $refund_type,
            'exchange_number'     => $exchange_number,
            'original_amount'     => $credit_amount,
            'credit_amount'       => $credit_to_acct,
            'refund_amount'       => $cash_refund,
            'prsi_amount'         => $prsi_amount,
            'reason'              => $reason,
            'status'              => 'pending',
            'created_by'          => $staff_id ?: null,
        ]);

        // Queue credit note for QBO
        $db->insert( 'hearmed_admin.qbo_batch_queue', [
            'entity_type' => 'credit_note',
            'entity_id'   => $cn_id,
            'status'      => 'pending',
            'queued_at'   => date('Y-m-d H:i:s'),
            'created_by'  => $staff_id ?: null,
        ]);

        // Return exchanged hearing aid(s) to stock
        hm_return_device_to_stock( $device, $side, 'Exchange', $staff_id );

        // Timeline entry
        $timeline_desc = "Exchange initiated. Credit note {$cn_number} for item amount \xe2\x82\xac" . number_format($credit_amount, 2);
        if ( $prsi_amount > 0 ) $timeline_desc .= ". PRSI: \xe2\x82\xac" . number_format($prsi_amount, 2);
        if ( $refund_type === 'refund' && $cash_refund > 0 ) {
            $timeline_desc .= ". Cash refund: \xe2\x82\xac" . number_format($cash_refund, 2);
        }
        if ( $credit_to_acct > 0 ) {
            $timeline_desc .= ". Credit on account: \xe2\x82\xac" . number_format($credit_to_acct, 2);
        }
        $timeline_desc .= ". Reason: {$reason}. Original device added back to stock.";
        if ( $notes ) $timeline_desc .= " Notes: {$notes}";

        $db->insert( 'hearmed_core.patient_timeline', [
            'patient_id'  => $pid,
            'event_type'  => 'exchange_created',
            'event_date'  => date('Y-m-d'),
            'staff_id'    => $staff_id ?: null,
            'description' => $timeline_desc,
        ]);

        $db->commit();

        hm_patient_audit( 'CREATE_EXCHANGE', 'exchange', $exchange_id, [
            'patient_id'     => $pid,
            'device_id'      => $device_id,
            'credit_note'    => $cn_number,
            'item_amount'    => $credit_amount,
            'prsi_amount'    => $prsi_amount,
            'refund_type'    => $refund_type,
            'cash_refund'    => $cash_refund,
            'credit_on_acct' => $credit_to_acct,
        ]);

        wp_send_json_success( [
            'credit_note_id'     => $cn_id,
            'credit_note_number' => $cn_number,
            'exchange_id'        => $exchange_id,
            'exchange_number'    => $exchange_number,
            'patient_credit_id'  => $credit_id,
            'item_amount'        => $credit_amount,
            'patient_credit'     => $credit_to_acct,
            'cash_refund'        => $cash_refund,
            'prsi_amount'        => $prsi_amount,
            'refund_type'        => $refund_type,
        ]);
    } catch ( \Exception $e ) {
        $db->rollback();
        wp_send_json_error( 'Exchange failed: ' . $e->getMessage() );
    }
}


// ═══════════════════════════════════════════════════════════════════════════
//  24-C. GET PATIENT EXCHANGES
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_get_patient_exchanges() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );

    $db = HearMed_DB::instance();

    // Check exchanges table exists
    $has_table = $db->get_var(
        "SELECT 1 FROM information_schema.tables WHERE table_schema = 'hearmed_core' AND table_name = 'exchanges'"
    );
    if ( ! $has_table ) { wp_send_json_success( [] ); return; }

    // Check if exchange_number column exists
    $has_exch_num = $db->get_var(
        "SELECT column_name FROM information_schema.columns
         WHERE table_schema = 'hearmed_core' AND table_name = 'exchanges' AND column_name = 'exchange_number'"
    );
    $exch_num_col = $has_exch_num ? "e.exchange_number," : "NULL AS exchange_number,";

    $rows = $db->get_results(
        "SELECT e.id, {$exch_num_col}
                e.exchange_type, e.original_amount, e.new_amount,
                e.credit_amount, e.balance_due, e.refund_amount,
                e.prsi_amount, e.reason, e.status,
                e.created_at,
                cn.credit_note_number,
                COALESCE(p.product_name, 'Unknown Device') AS device_name,
                COALESCE(s.first_name || ' ' || s.last_name, 'System') AS created_by_name
         FROM hearmed_core.exchanges e
         LEFT JOIN hearmed_core.credit_notes cn ON cn.id = e.credit_note_id
         LEFT JOIN hearmed_core.patient_devices pd ON pd.id = e.device_id
         LEFT JOIN hearmed_reference.products p ON p.id = pd.product_id
         LEFT JOIN hearmed_reference.staff s ON s.id = e.created_by
         WHERE e.patient_id = \$1
         ORDER BY e.created_at DESC",
        [ $pid ]
    );

    $out = [];
    foreach ( $rows as $r ) {
        $out[] = [
            'id'                  => (int) $r->id,
            'exchange_number'     => $r->exchange_number ?? '',
            'exchange_type'       => $r->exchange_type,
            'device_name'         => $r->device_name,
            'original_amount'     => (float) $r->original_amount,
            'new_amount'          => (float) $r->new_amount,
            'credit_amount'       => (float) $r->credit_amount,
            'balance_due'         => (float) $r->balance_due,
            'refund_amount'       => (float) $r->refund_amount,
            'prsi_amount'         => (float) $r->prsi_amount,
            'credit_note_number'  => $r->credit_note_number ?? '',
            'reason'              => $r->reason ?? '',
            'status'              => $r->status,
            'created_at'          => $r->created_at,
            'created_by_name'     => $r->created_by_name,
        ];
    }
    wp_send_json_success( $out );
}


// ═══════════════════════════════════════════════════════════════════════════
//  24-D. INITIATE EXCHANGE
//       Creates a pending exchange record, returns exchange_id for
//       redirect to the calendar exchange order page.
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_initiate_exchange() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid       = intval( $_POST['patient_id'] ?? 0 );
    $device_id = intval( $_POST['device_id'] ?? 0 );
    $reason    = sanitize_textarea_field( $_POST['reason'] ?? '' );
    $side      = sanitize_text_field( $_POST['side'] ?? 'both' );
    $credit_amount = floatval( $_POST['credit_amount'] ?? 0 );
    $notes     = sanitize_textarea_field( $_POST['notes'] ?? '' );

    if ( ! $pid || ! $device_id ) wp_send_json_error( 'Patient and device required' );
    if ( ! $reason ) wp_send_json_error( 'Reason is required' );

    $db       = HearMed_DB::instance();
    $staff_id = hm_patient_staff_id();

    hm_ensure_returns_tables();

    $device = $db->get_row( "SELECT * FROM hearmed_core.patient_devices WHERE id = \$1", [ $device_id ] );
    if ( ! $device ) wp_send_json_error( 'Device not found' );

    // Lookup linked order
    if ( ! $device->order_id && $device->product_id ) {
        $fallback = $db->get_row(
            "SELECT o.id FROM hearmed_core.orders o
             JOIN hearmed_core.order_items oi ON oi.order_id = o.id
             WHERE o.patient_id = \$1 AND oi.item_id = \$2 AND oi.item_type = 'product'
             ORDER BY o.id DESC LIMIT 1",
            [ $pid, $device->product_id ]
        );
        if ( $fallback ) {
            $db->update( 'hearmed_core.patient_devices', [ 'order_id' => $fallback->id ], [ 'id' => $device_id ] );
            $device->order_id = $fallback->id;
        }
    }

    $order = null;
    $prsi_amount = 0;
    $inv_id = null;
    if ( $device->order_id ) {
        $order = $db->get_row(
            "SELECT o.*, inv.grand_total AS inv_total, inv.id AS inv_id
             FROM hearmed_core.orders o
             LEFT JOIN hearmed_core.invoices inv ON inv.id = o.invoice_id
             WHERE o.id = \$1",
            [ $device->order_id ]
        );
        if ( $order ) {
            $inv_id = $order->inv_id ?? null;
            if ( $side === 'both' ) {
                $prsi_amount = floatval( $order->prsi_amount ?? 0 );
            } else {
                $prsi_field  = 'prsi_' . $side;
                $prsi_amount = ! empty( $order->$prsi_field ) ? 500 : 0;
            }
        }
    }

    // Generate exchange number
    $exchange_number = HearMed_Utils::generate_exchange_number();

    // Create exchange record (status = initiated)
    $exchange_id = $db->insert( 'hearmed_core.exchanges', [
        'patient_id'          => $pid,
        'original_order_id'   => $device->order_id ?: null,
        'original_invoice_id' => $inv_id,
        'device_id'           => $device_id,
        'exchange_number'     => $exchange_number,
        'exchange_type'       => 'pending',
        'original_amount'     => $credit_amount ?: 0,
        'prsi_amount'         => $prsi_amount,
        'reason'              => $reason,
        'notes'               => $notes ?: null,
        'status'              => 'initiated',
        'created_by'          => $staff_id ?: null,
    ]);

    hm_patient_audit( 'INITIATE_EXCHANGE', 'exchange', $exchange_id, [
        'patient_id' => $pid, 'device_id' => $device_id, 'reason' => $reason
    ]);

    wp_send_json_success( [
        'exchange_id'     => $exchange_id,
        'exchange_number' => $exchange_number,
    ]);
}


// ═══════════════════════════════════════════════════════════════════════════
//  24-E. GET EXCHANGE DETAILS
//       Returns full exchange data for the calendar exchange order page.
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_get_exchange_details() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $exchange_id = intval( $_POST['exchange_id'] ?? $_GET['exchange_id'] ?? 0 );
    if ( ! $exchange_id ) wp_send_json_error( 'Missing exchange_id' );

    $db = HearMed_DB::instance();

    $ex = $db->get_row(
        "SELECT e.*,
                pd.product_id, pd.product_name AS device_name, pd.manufacturer,
                pd.serial_left, pd.serial_right, pd.fitting_date,
                p.first_name AS p_first, p.last_name AS p_last, p.patient_number,
                p.prsi_eligible, p.date_of_birth
         FROM hearmed_core.exchanges e
         LEFT JOIN hearmed_core.patient_devices pd ON pd.id = e.device_id
         LEFT JOIN hearmed_core.patients p ON p.id = e.patient_id
         WHERE e.id = \$1",
        [ $exchange_id ]
    );
    if ( ! $ex ) wp_send_json_error( 'Exchange not found' );

    // Determine how many aids were on original order (1 or 2)
    $original_qty = 1;
    if ( $ex->original_order_id ) {
        $item_count = $db->get_var(
            "SELECT COUNT(*) FROM hearmed_core.order_items
             WHERE order_id = \$1 AND item_type = 'product'",
            [ $ex->original_order_id ]
        );
        $original_qty = max( 1, intval( $item_count ) );
    } else {
        // If both serials present, it's binaural
        if ( ! empty( $ex->serial_left ) && ! empty( $ex->serial_right ) ) {
            $original_qty = 2;
        }
    }

    // Get original order items for display
    $original_items = [];
    if ( $ex->original_order_id ) {
        $original_items = $db->get_results(
            "SELECT oi.*, COALESCE(p.product_name, oi.item_description) AS product_name
             FROM hearmed_core.order_items oi
             LEFT JOIN hearmed_reference.products p ON p.id = oi.item_id AND oi.item_type = 'product'
             WHERE oi.order_id = \$1
             ORDER BY oi.id",
            [ $ex->original_order_id ]
        );
    }

    // Get PRSI info from original order
    $prsi_left  = false;
    $prsi_right = false;
    if ( $ex->original_order_id ) {
        $ord = $db->get_row(
            "SELECT prsi_left, prsi_right, prsi_amount FROM hearmed_core.orders WHERE id = \$1",
            [ $ex->original_order_id ]
        );
        if ( $ord ) {
            $prsi_left  = ! empty( $ord->prsi_left );
            $prsi_right = ! empty( $ord->prsi_right );
        }
    }

    $out_items = [];
    foreach ( $original_items as $it ) {
        $out_items[] = [
            'id'          => (int) $it->id,
            'product_name'=> $it->product_name,
            'item_type'   => $it->item_type,
            'ear_side'    => $it->ear_side ?? '',
            'quantity'    => (int) ($it->quantity ?? 1),
            'unit_price'  => (float) ($it->unit_retail_price ?? $it->unit_price ?? 0),
            'line_total'  => (float) ($it->line_total ?? 0),
        ];
    }

    wp_send_json_success( [
        'exchange_id'      => (int) $ex->id,
        'exchange_number'  => $ex->exchange_number ?? '',
        'patient_id'       => (int) $ex->patient_id,
        'patient_name'     => trim(($ex->p_first ?? '') . ' ' . ($ex->p_last ?? '')),
        'patient_number'   => $ex->patient_number ?? '',
        'patient_dob'      => $ex->date_of_birth ?? '',
        'prsi_eligible'    => ! empty( $ex->prsi_eligible ),
        'device_id'        => (int) $ex->device_id,
        'device_name'      => $ex->device_name ?? 'Unknown Device',
        'manufacturer'     => $ex->manufacturer ?? '',
        'serial_left'      => $ex->serial_left ?? '',
        'serial_right'     => $ex->serial_right ?? '',
        'fitting_date'     => $ex->fitting_date ?? '',
        'original_order_id'=> (int) ($ex->original_order_id ?? 0),
        'original_invoice_id'=> (int) ($ex->original_invoice_id ?? 0),
        'original_amount'  => (float) $ex->original_amount,
        'prsi_amount'      => (float) $ex->prsi_amount,
        'prsi_left'        => $prsi_left,
        'prsi_right'       => $prsi_right,
        'reason'           => $ex->reason ?? '',
        'notes'            => $ex->notes ?? '',
        'status'           => $ex->status,
        'original_qty'     => $original_qty,
        'original_items'   => $out_items,
        'side'             => '', // side is captured in device serial context
    ]);
}


// ═══════════════════════════════════════════════════════════════════════════
//  24-F. COMPLETE EXCHANGE
//       Called from calendar exchange page after new device selected.
//       Creates: new order, credit note on old, new invoice, applies credit,
//       handles PRSI carryover, handles balance difference.
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_complete_exchange() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $exchange_id    = intval( $_POST['exchange_id'] ?? 0 );
    $items_json     = stripslashes( $_POST['items_json'] ?? '[]' );
    $notes          = sanitize_textarea_field( $_POST['notes'] ?? '' );
    $prsi_left      = intval( $_POST['prsi_left'] ?? 0 );
    $prsi_right     = intval( $_POST['prsi_right'] ?? 0 );
    $discount_pct   = floatval( $_POST['discount_pct'] ?? 0 );
    $discount_euro  = floatval( $_POST['discount_euro'] ?? 0 );
    $from_stock     = intval( $_POST['from_stock'] ?? 0 );

    if ( ! $exchange_id ) wp_send_json_error( 'Missing exchange_id' );

    $new_items = json_decode( $items_json, true );
    if ( ! is_array( $new_items ) || empty( $new_items ) ) wp_send_json_error( 'No items selected' );

    $db       = HearMed_DB::instance();
    $staff_id = hm_patient_staff_id();

    $ex = $db->get_row( "SELECT * FROM hearmed_core.exchanges WHERE id = \$1", [ $exchange_id ] );
    if ( ! $ex ) wp_send_json_error( 'Exchange not found' );
    if ( $ex->status !== 'initiated' ) wp_send_json_error( 'Exchange already processed' );

    $pid            = (int) $ex->patient_id;
    $device_id      = (int) $ex->device_id;
    $original_amount= (float) $ex->original_amount;
    $prsi_amount    = (float) $ex->prsi_amount;
    $side_info      = '';

    // Load device
    $device = $db->get_row( "SELECT * FROM hearmed_core.patient_devices WHERE id = \$1", [ $device_id ] );

    // Calculate new order totals
    $new_subtotal = 0;
    $new_vat      = 0;
    foreach ( $new_items as $it ) {
        $qty   = intval( $it['qty'] ?? 1 );
        $price = floatval( $it['unit_price'] ?? 0 );
        $vat   = floatval( $it['vat_amount'] ?? 0 );
        $new_subtotal += $price * $qty;
        $new_vat      += $vat;
    }

    // Apply discount
    $discount_total = 0;
    if ( $discount_pct > 0 ) {
        $discount_total = round( $new_subtotal * ( min( $discount_pct, 100 ) / 100 ), 2 );
    } elseif ( $discount_euro > 0 ) {
        $discount_total = min( $discount_euro, $new_subtotal );
    }

    // PRSI carryover
    $new_prsi = ( $prsi_left ? 500 : 0 ) + ( $prsi_right ? 500 : 0 );

    $new_grand_total = max( 0, $new_subtotal - $discount_total - $new_prsi );

    // Determine exchange type based on price difference
    $credit_amount = $original_amount; // amount to credit from old
    $balance_due   = $new_grand_total; // what new invoice will be
    $patient_credit_amount = max( 0, $credit_amount - $balance_due ); // surplus if step-down
    $exchange_type = 'same_value';
    if ( $new_grand_total > $credit_amount ) {
        $exchange_type = 'step_up';
    } elseif ( $new_grand_total < $credit_amount ) {
        $exchange_type = 'step_down';
    }

    $db->begin_transaction();

    try {
        // 1. Mark old device as Replaced
        $db->update( 'hearmed_core.patient_devices', [
            'device_status'  => 'Replaced',
            'inactive_reason'=> 'Exchange: ' . ( $ex->reason ?? '' ),
            'inactive_date'  => date( 'Y-m-d' ),
            'updated_at'     => date( 'c' ),
        ], [ 'id' => $device_id ] );

        // 2. Return old device to stock
        if ( $device ) {
            hm_return_device_to_stock( $device, 'both', 'Exchange', $staff_id );
        }

        // 3. Create credit note on old invoice
        $cn_prefix = HearMed_Settings::get( 'hm_credit_note_prefix', 'HMCN' );
        $cn_seq    = $db->get_var( "SELECT COALESCE(MAX(id), 0) + 1 FROM hearmed_core.credit_notes" );
        $cn_number = $cn_prefix . '-' . str_pad( $cn_seq, 4, '0', STR_PAD_LEFT );

        $cn_id = $db->insert( 'hearmed_core.credit_notes', [
            'credit_note_number'    => $cn_number,
            'patient_id'            => $pid,
            'invoice_id'            => $ex->original_invoice_id ?: null,
            'order_id'              => $ex->original_order_id ?: null,
            'device_id'             => $device_id,
            'amount'                => $credit_amount,
            'prsi_amount'           => $prsi_amount,
            'patient_refund_amount' => max( 0, $credit_amount - $prsi_amount ),
            'reason'                => 'Exchange: ' . ( $ex->reason ?? '' ),
            'credit_date'           => date( 'Y-m-d' ),
            'refund_type'           => 'exchange',
            'exchange_type'         => $exchange_type,
            'cheque_sent'           => false,
            'prsi_notified'         => false,
            'created_by'            => $staff_id ?: null,
        ]);

        // 4. Create new order
        $order_number = HearMed_Utils::generate_order_number();

        $new_order_id = $db->insert( 'hearmed_core.orders', [
            'patient_id'     => $pid,
            'order_number'   => $order_number,
            'order_date'     => date( 'Y-m-d' ),
            'subtotal'       => $new_subtotal,
            'discount_total' => $discount_total,
            'vat_total'      => $new_vat,
            'prsi_amount'    => $new_prsi,
            'prsi_left'      => $prsi_left ? true : false,
            'prsi_right'     => $prsi_right ? true : false,
            'grand_total'    => $new_grand_total,
            'deposit'        => 0,
            'balance_due'    => $new_grand_total,
            'notes'          => 'Exchange from ' . ( $ex->exchange_number ?? '' ) . '. ' . ( $notes ?: '' ),
            'status'         => $from_stock ? 'Approved' : 'Pending Approval',
            'is_exchange'    => true,
            'exchange_id'    => $exchange_id,
            'created_by'     => $staff_id ?: null,
        ]);

        // 5. Insert new order items
        foreach ( $new_items as $it ) {
            $db->insert( 'hearmed_core.order_items', [
                'order_id'          => $new_order_id,
                'item_id'           => intval( $it['product_id'] ?? 0 ) ?: null,
                'item_type'         => $it['type'] ?? 'product',
                'item_description'  => $it['name'] ?? '',
                'ear_side'          => $it['ear'] ?? null,
                'quantity'          => intval( $it['qty'] ?? 1 ),
                'unit_retail_price' => floatval( $it['unit_price'] ?? 0 ),
                'unit_price'        => floatval( $it['unit_price'] ?? 0 ),
                'vat_rate'          => floatval( $it['vat_rate'] ?? 0 ),
                'vat_amount'        => floatval( $it['vat_amount'] ?? 0 ),
                'line_total'        => floatval( $it['unit_price'] ?? 0 ) * intval( $it['qty'] ?? 1 ),
            ]);
        }

        // 6. Create patient credit from old invoice amount
        $credit_id = $db->insert( 'hearmed_core.patient_credits', [
            'patient_id'          => $pid,
            'credit_note_id'      => $cn_id,
            'original_invoice_id' => $ex->original_invoice_id ?: null,
            'original_order_id'   => $ex->original_order_id ?: null,
            'amount'              => $credit_amount,
            'used_amount'         => min( $credit_amount, $new_grand_total ),
            'status'              => $credit_amount <= $new_grand_total ? 'used' : 'active',
            'notes'               => "Exchange credit from CN {$cn_number}",
            'created_by'          => $staff_id ?: null,
        ]);

        // 7. Create new invoice
        $inv_prefix = HearMed_Settings::get( 'hm_invoice_prefix', 'INV' );
        $inv_seq    = HearMed_Settings::get( 'hm_invoice_last_number', 0 ) + 1;
        HearMed_Settings::set( 'hm_invoice_last_number', $inv_seq );
        $inv_number = $inv_prefix . '-' . date( 'Y' ) . '-' . str_pad( $inv_seq, 4, '0', STR_PAD_LEFT );

        // Amount still to pay after applying credit
        $amount_after_credit = max( 0, $new_grand_total - $credit_amount );

        $new_invoice_id = $db->insert( 'hearmed_core.invoices', [
            'patient_id'     => $pid,
            'order_id'       => $new_order_id,
            'invoice_number' => $inv_number,
            'invoice_date'   => date( 'Y-m-d' ),
            'subtotal'       => $new_subtotal,
            'discount_total' => $discount_total,
            'vat_total'      => $new_vat,
            'prsi_amount'    => $new_prsi,
            'grand_total'    => $new_grand_total,
            'amount_paid'    => min( $credit_amount, $new_grand_total ),
            'balance_due'    => $amount_after_credit,
            'status'         => $amount_after_credit <= 0.01 ? 'paid' : 'unpaid',
            'created_by'     => $staff_id ?: null,
        ]);

        // Link order to invoice
        $db->update( 'hearmed_core.orders', [
            'invoice_id' => $new_invoice_id,
        ], [ 'id' => $new_order_id ] );

        // 8. Apply credit to new invoice
        $apply_amount = min( $credit_amount, $new_grand_total );
        if ( $apply_amount > 0 ) {
            $db->insert( 'hearmed_core.credit_applications', [
                'patient_credit_id' => $credit_id,
                'invoice_id'        => $new_invoice_id,
                'amount'            => $apply_amount,
                'applied_by'        => $staff_id ?: null,
            ]);

            // Record as payment on invoice
            $db->insert( 'hearmed_core.payments', [
                'patient_id'     => $pid,
                'invoice_id'     => $new_invoice_id,
                'order_id'       => $new_order_id,
                'amount'         => $apply_amount,
                'payment_method' => 'Credit Applied',
                'payment_date'   => date( 'Y-m-d' ),
                'notes'          => "Exchange credit from CN {$cn_number}",
                'created_by'     => $staff_id ?: null,
            ]);
        }

        // 9. Update exchange record
        $db->update( 'hearmed_core.exchanges', [
            'new_order_id'       => $new_order_id,
            'new_invoice_id'     => $new_invoice_id,
            'credit_note_id'     => $cn_id,
            'patient_credit_id'  => $credit_id,
            'exchange_type'      => $exchange_type,
            'new_amount'         => $new_grand_total,
            'credit_amount'      => $apply_amount,
            'balance_due'        => $amount_after_credit,
            'refund_amount'      => $exchange_type === 'step_down' ? max( 0, $credit_amount - $new_grand_total ) : 0,
            'status'             => $amount_after_credit <= 0.01 ? 'completed' : 'pending_payment',
            'completed_at'       => $amount_after_credit <= 0.01 ? date( 'c' ) : null,
            'completed_by'       => $staff_id ?: null,
            'updated_at'         => date( 'c' ),
        ], [ 'id' => $exchange_id ] );

        // 10. Queue credit note for QBO
        $db->insert( 'hearmed_admin.qbo_batch_queue', [
            'entity_type' => 'credit_note',
            'entity_id'   => $cn_id,
            'status'      => 'pending',
            'queued_at'   => date( 'Y-m-d H:i:s' ),
            'created_by'  => $staff_id ?: null,
        ]);

        // 11. Timeline
        $desc = "Exchange {$ex->exchange_number} completed. CN {$cn_number} issued. New order {$order_number} created.";
        if ( $amount_after_credit > 0 ) {
            $desc .= " Balance due: €" . number_format( $amount_after_credit, 2 );
        } else {
            $desc .= " Fully covered by credit.";
        }
        if ( $exchange_type === 'step_down' ) {
            $surplus = $credit_amount - $new_grand_total;
            $desc .= " Surplus credit: €" . number_format( $surplus, 2 );
        }
        $db->insert( 'hearmed_core.patient_timeline', [
            'patient_id'  => $pid,
            'event_type'  => 'exchange_completed',
            'event_date'  => date( 'Y-m-d' ),
            'staff_id'    => $staff_id ?: null,
            'description' => $desc,
        ]);

        $db->commit();

        hm_patient_audit( 'COMPLETE_EXCHANGE', 'exchange', $exchange_id, [
            'patient_id'     => $pid,
            'new_order_id'   => $new_order_id,
            'new_invoice_id' => $new_invoice_id,
            'credit_note'    => $cn_number,
            'exchange_type'  => $exchange_type,
            'balance_due'    => $amount_after_credit,
        ]);

        wp_send_json_success( [
            'exchange_id'        => $exchange_id,
            'exchange_number'    => $ex->exchange_number ?? '',
            'new_order_id'       => $new_order_id,
            'order_number'       => $order_number,
            'new_invoice_id'     => $new_invoice_id,
            'invoice_number'     => $inv_number,
            'credit_note_id'     => $cn_id,
            'credit_note_number' => $cn_number,
            'exchange_type'      => $exchange_type,
            'credit_applied'     => $apply_amount,
            'balance_due'        => $amount_after_credit,
            'new_grand_total'    => $new_grand_total,
            'from_stock'         => $from_stock,
        ]);
    } catch ( \Exception $e ) {
        $db->rollback();
        wp_send_json_error( 'Exchange failed: ' . $e->getMessage() );
    }
}


// ═══════════════════════════════════════════════════════════════════════════
//  RETURN FLOW — Full hearing aid return with PRSI tracking
//  Creates: credit note, return record, PRSI notification if applicable
//  Refund amount = patient-paid amount ONLY (excludes PRSI)
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_create_return() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid       = intval( $_POST['patient_id'] ?? 0 );
    $device_id = intval( $_POST['device_id'] ?? 0 );
    $reason    = sanitize_textarea_field( $_POST['reason'] ?? '' );
    $side      = sanitize_text_field( $_POST['side'] ?? 'both' );
    $notes     = sanitize_textarea_field( $_POST['notes'] ?? '' );

    if ( ! $pid || ! $device_id ) wp_send_json_error( 'Patient and device required' );
    if ( ! $reason ) wp_send_json_error( 'Reason for return is required' );

    $db       = HearMed_DB::instance();
    $staff_id = hm_patient_staff_id();

    hm_ensure_returns_tables();

    $device = $db->get_row( "SELECT * FROM hearmed_core.patient_devices WHERE id = \$1", [ $device_id ] );
    if ( ! $device ) wp_send_json_error( 'Device not found' );

    // Get original order/invoice info for amounts + PRSI
    $order       = null;
    $total_amount = 0;
    $prsi_amount  = 0;
    $patient_paid = 0;
    $per_ear      = floatval( HearMed_Settings::get( 'hm_prsi_amount_per_ear', '500' ) );

    if ( $device->order_id ) {
        $order = $db->get_row(
            "SELECT o.*, inv.grand_total AS inv_total, inv.id AS inv_id
             FROM hearmed_core.orders o
             LEFT JOIN hearmed_core.invoices inv ON inv.id = o.invoice_id
             WHERE o.id = \$1",
            [ $device->order_id ]
        );
        if ( $order ) {
            $total_amount   = floatval( $order->grand_total ?? 0 );
            $order_prsi     = floatval( $order->prsi_amount ?? 0 );
            $prsi_left_flag = hm_pg_bool( $order->prsi_left ?? false );
            $prsi_right_flag= hm_pg_bool( $order->prsi_right ?? false );
            $patient_paid   = $total_amount; // grand_total already has PRSI deducted
        }
    }

    // ── Side-aware PRSI calculation ──
    if ( $side === 'left' ) {
        $prsi_amount  = $prsi_left_flag ? $per_ear : 0;
        // Half the patient-paid amount for single-side return
        $patient_paid = $patient_paid / 2;
    } elseif ( $side === 'right' ) {
        $prsi_amount  = $prsi_right_flag ? $per_ear : 0;
        $patient_paid = $patient_paid / 2;
    } else {
        // Both sides — full PRSI
        $prsi_amount = $order_prsi ?? 0;
    }

    // Allow override of refund amount from POST
    $refund_override = isset( $_POST['refund_amount'] ) ? floatval( $_POST['refund_amount'] ) : null;
    $patient_refund  = $refund_override !== null ? $refund_override : $patient_paid;

    $db->begin_transaction();

    try {
        // 1. Side-aware device update
        if ( $side === 'both' ) {
            // Return both — mark entire device as Returned
            $db->update( 'hearmed_core.patient_devices', [
                'device_status'  => 'Returned',
                'inactive_reason'=> 'Return: ' . $reason,
                'inactive_date'  => date( 'Y-m-d' ),
                'updated_at'     => date( 'c' ),
            ], [ 'id' => $device_id ] );
        } else {
            // Single-side return: clear that side's serial
            $clear_col = ( $side === 'left' ) ? 'serial_left' : 'serial_right';
            $other_col = ( $side === 'left' ) ? 'serial_right' : 'serial_left';
            $other_serial = $device->$other_col ?? null;

            $upd = [
                $clear_col      => null,
                'inactive_reason'=> ( $device->inactive_reason ? $device->inactive_reason . '; ' : '' ) . ucfirst($side) . ' returned: ' . $reason,
                'updated_at'     => date( 'c' ),
            ];

            // If other side has no serial left, mark whole device as Returned
            if ( ! $other_serial ) {
                $upd['device_status'] = 'Returned';
                $upd['inactive_date'] = date( 'Y-m-d' );
            }

            $db->update( 'hearmed_core.patient_devices', $upd, [ 'id' => $device_id ] );
        }

        // 2. Generate credit note — aligned with existing HMCN-YYYY-NNNN format
        $cn_prefix = HearMed_Settings::get( 'hm_credit_note_prefix', 'HMCN' );
        $cn_count  = (int) $db->get_var( "SELECT COUNT(*) FROM hearmed_core.credit_notes" );
        $cn_number = $cn_prefix . '-' . date('Y') . '-' . str_pad( $cn_count + 1, 4, '0', STR_PAD_LEFT );

        $cn_id = $db->insert( 'hearmed_core.credit_notes', [
            'credit_note_number'    => $cn_number,
            'patient_id'            => $pid,
            'invoice_id'            => $order->inv_id ?? null,
            'order_id'              => $device->order_id ?: null,
            'device_id'             => $device_id,
            'amount'                => $patient_refund + $prsi_amount,
            'prsi_amount'           => $prsi_amount,
            'patient_refund_amount' => $patient_refund,
            'reason'                => 'Return (' . $side . '): ' . $reason,
            'credit_date'           => date( 'Y-m-d' ),
            'refund_type'           => 'cheque',
            'cheque_sent'           => false,
            'prsi_notified'         => false,
            'created_by'            => $staff_id ?: null,
            'created_at'            => date( 'Y-m-d H:i:s' ),
        ]);

        // 3. Create return record (store serials BEFORE they get cleared)
        $return_id = $db->insert( 'hearmed_core.returns', [
            'patient_id'            => $pid,
            'order_id'              => $device->order_id ?: null,
            'invoice_id'            => $order->inv_id ?? null,
            'credit_note_id'        => $cn_id,
            'device_id'             => $device_id,
            'return_date'           => date( 'Y-m-d' ),
            'reason'                => $reason,
            'side'                  => $side,
            'serial_left'           => $device->serial_left ?? null,
            'serial_right'          => $device->serial_right ?? null,
            'total_refund_amount'   => $patient_refund + $prsi_amount,
            'patient_refund_amount' => $patient_refund,
            'prsi_refund_amount'    => $prsi_amount,
            'refund_status'         => 'pending',
            'prsi_notified'         => false,
            'notes'                 => $notes,
            'created_by'            => $staff_id ?: null,
        ]);

        // 4. Queue credit note for QBO
        $db->insert( 'hearmed_admin.qbo_batch_queue', [
            'entity_type' => 'credit_note',
            'entity_id'   => $cn_id,
            'status'      => 'pending',
            'queued_at'   => date('Y-m-d H:i:s'),
            'created_by'  => $staff_id ?: null,
        ]);

        // 5. Return hearing aid(s) to stock
        hm_return_device_to_stock( $device, $side, 'Return', $staff_id );

        // 6. Timeline entry
        $side_label = $side === 'both' ? 'both sides' : $side . ' side';
        $db->insert( 'hearmed_core.patient_timeline', [
            'patient_id'  => $pid,
            'event_type'  => 'return_created',
            'event_date'  => date('Y-m-d'),
            'staff_id'    => $staff_id ?: null,
            'description' => "Hearing aid returned ({$side_label}). Credit note {$cn_number}. Patient refund: \xe2\x82\xac" . number_format($patient_refund, 2) . ($prsi_amount > 0 ? ". PRSI to reclaim: \xe2\x82\xac" . number_format($prsi_amount, 2) : '') . ". Reason: {$reason}. Added back to stock.",
        ]);

        $db->commit();

        hm_patient_audit( 'CREATE_RETURN', 'return', $return_id, [
            'patient_id'      => $pid,
            'device_id'       => $device_id,
            'side'            => $side,
            'credit_note'     => $cn_number,
            'patient_refund'  => $patient_refund,
            'prsi_amount'     => $prsi_amount,
        ]);

        wp_send_json_success( [
            'return_id'          => $return_id,
            'credit_note_id'     => $cn_id,
            'credit_note_number' => $cn_number,
            'patient_refund'     => $patient_refund,
            'prsi_amount'        => $prsi_amount,
            'side'               => $side,
        ]);
    } catch ( \Exception $e ) {
        $db->rollback();
        wp_send_json_error( 'Return failed: ' . $e->getMessage() );
    }
}


// ═══════════════════════════════════════════════════════════════════════════
//  PATIENT CREDITS — get credits for a patient
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_get_patient_credits() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );

    hm_ensure_returns_tables();
    $db = HearMed_DB::instance();

    $rows = $db->get_results(
        "SELECT pc.id, pc.amount, pc.used_amount,
                (pc.amount - pc.used_amount) AS remaining,
                pc.status, pc.notes, pc.created_at,
                cn.credit_note_number
         FROM hearmed_core.patient_credits pc
         LEFT JOIN hearmed_core.credit_notes cn ON cn.id = pc.credit_note_id
         WHERE pc.patient_id = \$1
         ORDER BY pc.created_at DESC",
        [ $pid ]
    );

    wp_send_json_success( $rows ?: [] );
}


// ═══════════════════════════════════════════════════════════════════════════
//  APPLY CREDIT — apply patient credit to an invoice
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_apply_credit_to_invoice() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $credit_id  = intval( $_POST['credit_id'] ?? 0 );
    $invoice_id = intval( $_POST['invoice_id'] ?? 0 );
    $order_id   = intval( $_POST['order_id'] ?? 0 );
    $amount     = floatval( $_POST['amount'] ?? 0 );

    if ( ! $credit_id ) wp_send_json_error( 'Credit ID required' );
    if ( ! $invoice_id && ! $order_id ) wp_send_json_error( 'Invoice or order required' );
    if ( $amount <= 0 ) wp_send_json_error( 'Amount must be positive' );

    hm_ensure_returns_tables();
    $db       = HearMed_DB::instance();
    $staff_id = hm_patient_staff_id();

    // If only order_id provided, look up or create invoice
    if ( ! $invoice_id && $order_id ) {
        $order = $db->get_row( "SELECT invoice_id FROM hearmed_core.orders WHERE id = \$1", [ $order_id ] );
        if ( $order && ! empty( $order->invoice_id ) ) {
            $invoice_id = intval( $order->invoice_id );
        } else {
            // Create invoice from order
            if ( class_exists( 'HearMed_Invoice' ) && method_exists( 'HearMed_Invoice', 'ensure_invoice_for_order' ) ) {
                $inv_id = HearMed_Invoice::ensure_invoice_for_order( $order_id, $staff_id ?: null );
                if ( $inv_id ) $invoice_id = intval( $inv_id );
            }
        }
        if ( ! $invoice_id ) wp_send_json_error( 'Could not find or create invoice for this order' );
    }

    $credit = $db->get_row( "SELECT * FROM hearmed_core.patient_credits WHERE id = \$1", [ $credit_id ] );
    if ( ! $credit ) wp_send_json_error( 'Credit not found' );
    if ( $credit->status !== 'active' ) wp_send_json_error( 'Credit is no longer active' );

    $remaining = floatval( $credit->amount ) - floatval( $credit->used_amount );
    if ( $amount > $remaining ) wp_send_json_error( 'Amount exceeds available credit (€' . number_format($remaining, 2) . ')' );

    $invoice = $db->get_row( "SELECT * FROM hearmed_core.invoices WHERE id = \$1", [ $invoice_id ] );
    if ( ! $invoice ) wp_send_json_error( 'Invoice not found' );

    $balance = floatval( $invoice->balance_remaining );
    $apply   = min( $amount, $balance );

    $db->begin_transaction();
    try {
        // Record application
        $db->insert( 'hearmed_core.credit_applications', [
            'patient_credit_id' => $credit_id,
            'invoice_id'        => $invoice_id,
            'amount'            => $apply,
            'applied_by'        => $staff_id ?: null,
        ]);

        // Update credit used amount
        $new_used = floatval( $credit->used_amount ) + $apply;
        $new_status = $new_used >= floatval( $credit->amount ) ? 'used' : 'active';
        $db->update( 'hearmed_core.patient_credits', [
            'used_amount' => $new_used,
            'status'      => $new_status,
            'updated_at'  => date( 'c' ),
        ], [ 'id' => $credit_id ] );

        // Update invoice balance
        $new_balance = max( 0, $balance - $apply );
        $credit_applied = floatval( $invoice->credit_applied ?? 0 ) + $apply;
        $payment_status = $new_balance <= 0 ? 'Paid' : ( $credit_applied > 0 ? 'Partially Paid' : $invoice->payment_status );
        $db->update( 'hearmed_core.invoices', [
            'balance_remaining' => $new_balance,
            'credit_applied'    => $credit_applied,
            'payment_status'    => $payment_status,
        ], [ 'id' => $invoice_id ] );

        // Update order deposit too
        if ( $order_id ) {
            $ord = $db->get_row( "SELECT deposit_amount FROM hearmed_core.orders WHERE id = \$1", [ $order_id ] );
            if ( $ord ) {
                $db->update( 'hearmed_core.orders', [
                    'deposit_amount' => floatval( $ord->deposit_amount ?? 0 ) + $apply,
                ], [ 'id' => $order_id ] );
            }
        }

        $db->commit();

        wp_send_json_success( [
            'applied'            => $apply,
            'credit_remaining'   => floatval( $credit->amount ) - $new_used,
            'invoice_balance'    => $new_balance,
        ]);
    } catch ( \Exception $e ) {
        $db->rollback();
        wp_send_json_error( 'Failed: ' . $e->getMessage() );
    }
}


// ═══════════════════════════════════════════════════════════════════════════
//  RETURN TO STOCK — adds hearing aids back to inventory on return/exchange
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Add a returned/exchanged hearing aid back to inventory_stock.
 *
 * @param object $device   The patient_devices row (pre-update, so serials intact).
 * @param string $side     'left', 'right', or 'both'.
 * @param string $reason   'Return' or 'Exchange'.
 * @param int|null $staff_id  Staff performing the action.
 */
function hm_return_device_to_stock( $device, $side, $reason, $staff_id = null ) {
    $db = HearMed_DB::instance();

    // Lookup product + manufacturer info from the device's product_id
    $product = null;
    $manufacturer_id = null;
    if ( ! empty( $device->product_id ) ) {
        $product = $db->get_row(
            "SELECT p.product_name, p.model, p.style, p.tech_level, p.manufacturer_id,
                    m.name AS manufacturer_name
             FROM hearmed_reference.products p
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = p.manufacturer_id
             WHERE p.id = \$1",
            [ $device->product_id ]
        );
        if ( $product ) {
            // Stock table join uses manufacturers._ID, so try to get that
            $mfr_id_row = $db->get_row(
                "SELECT id, _ID FROM hearmed_reference.manufacturers WHERE id = \$1",
                [ $product->manufacturer_id ]
            );
            // Use _ID if available (for stock system compatibility), fallback to id
            $manufacturer_id = $mfr_id_row->_ID ?? $mfr_id_row->id ?? $product->manufacturer_id;
        }
    }

    // Determine clinic from original order if available
    // Stock system uses hearmed_admin.clinics._ID, orders use hearmed_reference.clinics.id
    $clinic_id = null;
    if ( ! empty( $device->order_id ) ) {
        $order_clinic = $db->get_var(
            "SELECT clinic_id FROM hearmed_core.orders WHERE id = \$1",
            [ $device->order_id ]
        );
        if ( $order_clinic ) {
            // Try to find matching clinic in hearmed_admin.clinics by name
            $ref_clinic = $db->get_row(
                "SELECT clinic_name FROM hearmed_reference.clinics WHERE id = \$1",
                [ $order_clinic ]
            );
            if ( $ref_clinic ) {
                $admin_clinic_id = $db->get_var(
                    "SELECT _ID FROM hearmed_admin.clinics WHERE name = \$1 LIMIT 1",
                    [ $ref_clinic->clinic_name ]
                );
                $clinic_id = $admin_clinic_id ?: $order_clinic;
            } else {
                $clinic_id = $order_clinic;
            }
        }
    }

    $model_name = $product->model ?? $product->product_name ?? 'Unknown';
    $style      = $product->style ?? '';
    $tech_level = $product->tech_level ?? '';
    $mfr_name   = $product->manufacturer_name ?? '';
    $today      = date( 'Y-m-d H:i:s' );

    $serials_to_add = [];
    $sl = $device->serial_number_left ?? $device->serial_left ?? '';
    $sr = $device->serial_number_right ?? $device->serial_right ?? '';

    if ( $side === 'left' || $side === 'both' ) {
        if ( $sl ) $serials_to_add[] = [ 'serial' => $sl, 'side' => 'Left' ];
    }
    if ( $side === 'right' || $side === 'both' ) {
        if ( $sr ) $serials_to_add[] = [ 'serial' => $sr, 'side' => 'Right' ];
    }
    // If no serials at all, add a single entry without serial
    if ( empty( $serials_to_add ) ) {
        $serials_to_add[] = [ 'serial' => '', 'side' => ucfirst( $side ) ];
    }

    foreach ( $serials_to_add as $entry ) {
        $stock_id = $db->insert( 'hearmed_reference.inventory_stock', [
            'item_category'    => 'hearing_aid',
            'manufacturer_id'  => $manufacturer_id,
            'model_name'       => $model_name,
            'style'            => $style ?: null,
            'technology_level' => $tech_level ?: null,
            'serial_number'    => $entry['serial'] ?: null,
            'clinic_id'        => $clinic_id,
            'quantity'         => 1,
            'status'           => 'Returned',
        ]);

        // Log stock movement
        if ( $stock_id ) {
            $staff_name = '';
            if ( $staff_id ) {
                $sn_row = $db->get_row( "SELECT first_name, last_name FROM hearmed_reference.staff WHERE id = \$1", [ $staff_id ] );
                $staff_name = $sn_row ? trim( $sn_row->first_name . ' ' . $sn_row->last_name ) : '';
            }
            $db->insert( 'hearmed_reference.stock_movements', [
                'stock_id'       => $stock_id,
                'movement_type'  => strtolower( $reason ),
                'to_clinic_id'   => $clinic_id,
                'quantity'       => 1,
                'notes'          => "{$reason}: {$mfr_name} {$model_name} ({$entry['side']}) — Serial: " . ( $entry['serial'] ?: 'N/A' ),
                'created_by'     => $staff_name ?: null,
                'created_at'     => $today,
            ]);
        }
    }
}


// ═══════════════════════════════════════════════════════════════════════════
//  AUTO-MIGRATE — ensures returns/exchanges/credits tables exist
// ═══════════════════════════════════════════════════════════════════════════

function hm_ensure_returns_tables() {
    static $done = false;
    if ( $done ) return;
    $done = true;

    $db = HearMed_DB::instance();

    // Ensure credit_notes has new columns
    $cols = [
        'prsi_amount'           => 'DECIMAL(10,2) DEFAULT 0',
        'patient_refund_amount' => 'DECIMAL(10,2) DEFAULT 0',
        'prsi_notified'         => 'BOOLEAN DEFAULT FALSE',
        'prsi_notified_date'    => 'DATE',
        'prsi_notified_by'      => 'BIGINT',
        'device_id'             => 'BIGINT',
        'exchange_type'         => 'VARCHAR(20)',
    ];
    foreach ( $cols as $col => $type ) {
        $exists = $db->get_var(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = 'hearmed_core' AND table_name = 'credit_notes' AND column_name = \$1",
            [ $col ]
        );
        if ( ! $exists ) {
            $db->query( "ALTER TABLE hearmed_core.credit_notes ADD COLUMN {$col} {$type}" );
        }
    }

    // Ensure invoices has credit columns
    $inv_cols = [
        'credit_applied' => 'DECIMAL(10,2) DEFAULT 0',
        'is_exchange'    => 'BOOLEAN DEFAULT FALSE',
        'exchange_id'    => 'BIGINT',
    ];
    foreach ( $inv_cols as $col => $type ) {
        $exists = $db->get_var(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = 'hearmed_core' AND table_name = 'invoices' AND column_name = \$1",
            [ $col ]
        );
        if ( ! $exists ) {
            $db->query( "ALTER TABLE hearmed_core.invoices ADD COLUMN {$col} {$type}" );
        }
    }

    // Create patient_credits table
    $db->query(
        "CREATE TABLE IF NOT EXISTS hearmed_core.patient_credits (
            id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
            patient_id      BIGINT NOT NULL,
            credit_note_id  BIGINT,
            original_invoice_id BIGINT,
            original_order_id   BIGINT,
            amount          DECIMAL(10,2) NOT NULL DEFAULT 0,
            used_amount     DECIMAL(10,2) NOT NULL DEFAULT 0,
            status          VARCHAR(20) NOT NULL DEFAULT 'active',
            notes           TEXT,
            created_by      BIGINT,
            created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    );

    // Create credit_applications table
    $db->query(
        "CREATE TABLE IF NOT EXISTS hearmed_core.credit_applications (
            id                  BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
            patient_credit_id   BIGINT NOT NULL,
            invoice_id          BIGINT NOT NULL,
            amount              DECIMAL(10,2) NOT NULL,
            applied_by          BIGINT,
            applied_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    );

    // Create exchanges table
    $db->query(
        "CREATE TABLE IF NOT EXISTS hearmed_core.exchanges (
            id                  BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
            patient_id          BIGINT NOT NULL,
            original_order_id   BIGINT,
            original_invoice_id BIGINT,
            new_order_id        BIGINT,
            new_invoice_id      BIGINT,
            credit_note_id      BIGINT,
            patient_credit_id   BIGINT,
            device_id           BIGINT,
            exchange_type       VARCHAR(20) NOT NULL DEFAULT 'same_value',
            original_amount     DECIMAL(10,2) DEFAULT 0,
            new_amount          DECIMAL(10,2) DEFAULT 0,
            credit_amount       DECIMAL(10,2) DEFAULT 0,
            balance_due         DECIMAL(10,2) DEFAULT 0,
            refund_amount       DECIMAL(10,2) DEFAULT 0,
            prsi_amount         DECIMAL(10,2) DEFAULT 0,
            reason              TEXT,
            notes               TEXT,
            exchange_number     VARCHAR(30),
            status              VARCHAR(20) NOT NULL DEFAULT 'pending',
            completed_at        TIMESTAMP,
            completed_by        BIGINT,
            created_by          BIGINT,
            created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    );

    // Ensure exchange_number + notes columns exist (for existing tables)
    foreach ( ['exchange_number' => 'VARCHAR(30)', 'notes' => 'TEXT'] as $col => $type ) {
        $exists = $db->get_var(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = 'hearmed_core' AND table_name = 'exchanges' AND column_name = \$1",
            [ $col ]
        );
        if ( ! $exists ) {
            $db->query( "ALTER TABLE hearmed_core.exchanges ADD COLUMN {$col} {$type}" );
        }
    }

    // Create returns table
    $db->query(
        "CREATE TABLE IF NOT EXISTS hearmed_core.returns (
            id                    BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
            patient_id            BIGINT NOT NULL,
            order_id              BIGINT,
            invoice_id            BIGINT,
            credit_note_id        BIGINT,
            device_id             BIGINT,
            return_date           DATE NOT NULL DEFAULT CURRENT_DATE,
            reason                TEXT,
            side                  VARCHAR(10) DEFAULT 'both',
            serial_left           VARCHAR(100),
            serial_right          VARCHAR(100),
            total_refund_amount   DECIMAL(10,2) DEFAULT 0,
            patient_refund_amount DECIMAL(10,2) DEFAULT 0,
            prsi_refund_amount    DECIMAL(10,2) DEFAULT 0,
            refund_status         VARCHAR(20) NOT NULL DEFAULT 'pending',
            refund_sent_date      DATE,
            prsi_notified         BOOLEAN DEFAULT FALSE,
            prsi_notified_date    DATE,
            prsi_notified_by      BIGINT,
            notes                 TEXT,
            created_by            BIGINT,
            created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )"
    );

    // Ensure serial columns exist on returns (for existing tables)
    foreach ( ['serial_left' => 'VARCHAR(100)', 'serial_right' => 'VARCHAR(100)'] as $col => $type ) {
        $exists = $db->get_var(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = 'hearmed_core' AND table_name = 'returns' AND column_name = \$1",
            [ $col ]
        );
        if ( ! $exists ) {
            $db->query( "ALTER TABLE hearmed_core.returns ADD COLUMN {$col} {$type}" );
        }
    }
}


// ═══════════════════════════════════════════════════════════════════════════
//  JOB 7 — AI EXTRACTION TRIGGER
//  Called after transcript save. Finds matching template, anonymises
//  transcript, builds AI prompt, calls API (MOCK mode available),
//  creates clinical_docs record with extracted data.
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Trigger AI extraction for a new transcript.
 *
 * @param int    $patient_id
 * @param int    $appointment_id
 * @param int    $transcript_id
 * @param string $transcript_text
 * @param int    $template_id  Optional — explicit template chosen by user
 */
/**
 * Normalize AI-extracted JSON so section data is flat: { section_key: { field_key: value } }
 * Handles cases where the AI nests values under 'fields' or 'label' wrappers.
 */
function hm_normalize_ai_extracted( $data ) {
    if ( ! is_array( $data ) ) return $data;

    $normalised = [];
    foreach ( $data as $sec_key => $sec_val ) {
        if ( ! is_array( $sec_val ) ) {
            // Scalar section value — keep as-is (e.g. a string summary)
            $normalised[ $sec_key ] = $sec_val;
            continue;
        }
        // If the AI nested under 'fields', unwrap it
        if ( isset( $sec_val['fields'] ) && is_array( $sec_val['fields'] ) ) {
            $normalised[ $sec_key ] = $sec_val['fields'];
        } else {
            // Remove any 'label' key the AI echoed back — it's metadata, not data
            $clean = $sec_val;
            unset( $clean['label'] );
            $normalised[ $sec_key ] = $clean;
        }
    }
    return $normalised;
}

function hm_trigger_ai_extraction( $patient_id, $appointment_id, $transcript_id, $transcript_text, $template_id = 0 ) {
    $db = HearMed_DB::instance();

    /* Ensure clinical docs table has all required columns */
    if ( function_exists( 'hm_clinical_docs_ensure_table' ) ) {
        hm_clinical_docs_ensure_table();
    }

    /* ── 1. Determine template ── */
    $template = null;

    // a) Explicit template_id from user selection
    if ( $template_id ) {
        $template = $db->get_row(
            "SELECT * FROM hearmed_admin.document_templates
             WHERE id = $1 AND is_active = true AND ai_enabled = true",
            [ $template_id ]
        );
    }

    // b) Fallback: match by appointment type name
    if ( ! $template && $appointment_id ) {
        $appt = $db->get_row(
            "SELECT appointment_type_id FROM hearmed_core.appointments WHERE id = $1",
            [ $appointment_id ]
        );
        if ( $appt && $appt->appointment_type_id ) {
            $apt_type = $db->get_row(
                "SELECT type_name FROM hearmed_reference.appointment_types WHERE id = $1",
                [ $appt->appointment_type_id ]
            );
            if ( $apt_type ) {
                $template = $db->get_row(
                    "SELECT * FROM hearmed_admin.document_templates 
                     WHERE LOWER(name) = LOWER($1) AND is_active = true AND ai_enabled = true
                     LIMIT 1",
                    [ $apt_type->type_name ]
                );
            }
        }
    }

    // c) Fallback: first active AI-enabled template
    if ( ! $template ) {
        $template = $db->get_row(
            "SELECT * FROM hearmed_admin.document_templates 
             WHERE is_active = true AND ai_enabled = true
             ORDER BY sort_order LIMIT 1"
        );
    }

    if ( ! $template ) {
        // No AI template found — skip extraction
        HearMed_Logger::log( 'ai_extraction', 'No AI template found for appointment ' . $appointment_id );
        return null;
    }

    /* ── 2. Anonymise transcript ── */
    $patient = $db->get_row(
        "SELECT first_name, last_name, patient_number FROM hearmed_core.patients WHERE id = $1",
        [ $patient_id ]
    );

    $anon_text = $transcript_text;
    if ( $patient ) {
        // Replace patient name with placeholder
        $full_name = trim( ( $patient->first_name ?? '' ) . ' ' . ( $patient->last_name ?? '' ) );
        if ( strlen( $full_name ) > 2 ) {
            $anon_text = str_ireplace( $full_name, '[PATIENT]', $anon_text );
        }
        $names = array_filter( [ $patient->first_name ?? '', $patient->last_name ?? '' ] );
        foreach ( $names as $name ) {
            if ( strlen( $name ) > 1 ) {
                $anon_text = str_ireplace( $name, '[PATIENT]', $anon_text );
            }
        }
    }

    // Strip DOB patterns (DD/MM/YYYY, DD-MM-YYYY, D Month YYYY)
    $anon_text = preg_replace( '/\b\d{1,2}[\/-]\d{1,2}[\/-]\d{2,4}\b/', '[DOB removed]', $anon_text );
    $anon_text = preg_replace( '/\b\d{1,2}\s+(?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{4}\b/i', '[DOB removed]', $anon_text );

    // Strip Irish phone numbers (08x, 01x, landlines)
    $anon_text = preg_replace( '/\b0[1-9]\d[\s-]?\d{3}[\s-]?\d{3,4}\b/', '[phone removed]', $anon_text );

    // Strip email addresses
    $anon_text = preg_replace( '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', '[email removed]', $anon_text );

    // Strip Irish eircodes (letter+digit+digit + space + 4 chars)
    $anon_text = preg_replace( '/\b[A-Za-z]\d{2}\s?[A-Za-z0-9]{4}\b/', '[eircode removed]', $anon_text );

    /* ── 3. Build extraction schema from template sections ── */
    $sections = json_decode( $template->sections_json ?: '[]', true );
    $schema   = [];        // Internal schema with metadata
    $output_example = [];  // Flat output format for the AI
    $field_instructions = []; // Per-field instructions
    if ( is_array( $sections ) ) {
        foreach ( $sections as $sec ) {
            if ( empty( $sec['enabled'] ) ) continue;
            $sec_type = $sec['type'] ?? '';
            // Skip auto-fill, chart, signature — extract from all other section types that have fields
            if ( in_array( $sec_type, [ 'auto', 'chart', 'signature' ] ) ) continue;

            $sec_key = $sec['key'] ?? '';
            $fields_schema = [];
            $output_fields = [];
            if ( ! empty( $sec['fields'] ) && is_array( $sec['fields'] ) ) {
                foreach ( $sec['fields'] as $f ) {
                    if ( ! is_array( $f ) ) continue;
                    $fkey   = $f['key'] ?? '';
                    $flabel = $f['label'] ?? ucwords( str_replace( '_', ' ', $fkey ) );
                    $format = $f['format'] ?? 'text';
                    $fs = [ 'type' => $format ];
                    if ( ! empty( $f['required'] ) ) {
                        $fs['required'] = true;
                    }

                    // Build format-aware instruction
                    $base_instr = ! empty( $f['ai_instruction'] ) ? $f['ai_instruction'] : 'Extract information about ' . strtolower( $flabel ) . ' from the transcript.';
                    $format_hint = '';
                    switch ( $format ) {
                        case 'number':
                            $format_hint = ' Return a numeric value only (e.g. "3", "45", "0.5").';
                            break;
                        case 'boolean':
                            $format_hint = ' Return "Yes" or "No" only.';
                            break;
                        case 'date':
                            $format_hint = ' Return a date in YYYY-MM-DD format if found.';
                            break;
                        case 'textarea':
                            $format_hint = ' Provide a detailed, thorough response. Include all relevant information mentioned in the transcript.';
                            break;
                        case 'select':
                            $format_hint = ' Pick the single best matching option from the ai_instruction.';
                            break;
                    }
                    $instr = $base_instr . $format_hint;
                    $fs['instruction'] = $instr;
                    $field_instructions[] = "{$sec_key}.{$fkey} ({$flabel}): {$instr}";
                    $fields_schema[ $fkey ] = $fs;
                    $output_fields[ $fkey ] = '';
                }
            }
            // Only include sections that actually have fields to extract
            if ( ! empty( $output_fields ) ) {
                $schema[ $sec_key ] = [
                    'label'  => $sec['label'] ?? '',
                    'fields' => $fields_schema,
                ];
                $output_example[ $sec_key ] = $output_fields;
            }
        }
    }

    /* ── 4. Build AI prompt ── */
    $system_prompt = $template->ai_system_prompt ?? '';
    if ( empty( $system_prompt ) ) {
        $system_prompt = 'You are a clinical audiologist assistant. Extract structured data from the consultation transcript. Be concise and clinical. If information is not mentioned in the transcript, use null for that field.';
    }
    $system_prompt .= "\n\nCRITICAL OUTPUT RULES:\n";
    $system_prompt .= "1. Return a flat JSON object: { \"section_key\": { \"field_key\": \"extracted value\" } }\n";
    $system_prompt .= "2. Each field value must be a plain text string with the extracted information, or null if not found.\n";
    $system_prompt .= "3. Do NOT prefix values with type labels like 'Text:', 'Boolean:', 'Date:', 'Number:' etc.\n";
    $system_prompt .= "4. Do NOT nest values under 'fields', 'label', 'type' or any wrapper key.\n";
    $system_prompt .= "5. Write naturally — e.g. \"Patient reports bilateral tinnitus for 3 years\" not \"Text: Patient reports...\"\n";
    $system_prompt .= "6. For number fields, return just the number as a string (e.g. \"3\", \"45\").\n";
    $system_prompt .= "7. For boolean fields, return \"Yes\" or \"No\".\n";
    $system_prompt .= "8. For textarea/long text fields, provide detailed thorough responses with all relevant information from the transcript.\n";
    $system_prompt .= "9. Search the ENTIRE transcript thoroughly for ALL relevant information for each field.\n";

    $user_prompt  = "Extract clinical data from the following audiology consultation transcript.\n\n";
    $user_prompt .= "REQUIRED OUTPUT FORMAT (respond with JSON exactly like this, replacing null with extracted values):\n";
    $user_prompt .= json_encode( $output_example, JSON_PRETTY_PRINT ) . "\n\n";
    if ( ! empty( $field_instructions ) ) {
        $user_prompt .= "FIELD INSTRUCTIONS:\n" . implode( "\n", $field_instructions ) . "\n\n";
    }
    $user_prompt .= "TRANSCRIPT:\n" . $anon_text;

    /* ── 5. Call AI API or use MOCK mode ── */
    $mock_mode  = HearMed_Settings::get( 'hm_ai_mock_mode', '0' );
    $enabled    = HearMed_Settings::get( 'hm_ai_extraction_enabled', '1' );
    $extracted  = [];
    $ai_model   = 'mock';
    $ai_tokens  = 0;

    if ( $enabled !== '1' ) {
        return null; // Extraction disabled
    }

    if ( $mock_mode === '1' ) {
        /* MOCK mode — generate placeholder data from schema */
        foreach ( $schema as $sec_key => $sec_info ) {
            $extracted[ $sec_key ] = [];
            if ( ! empty( $sec_info['fields'] ) ) {
                foreach ( $sec_info['fields'] as $fkey => $finfo ) {
                    $extracted[ $sec_key ][ $fkey ] = '[Mock: ' . ( $finfo['instruction'] ?? $fkey ) . ']';
                }
            }
        }
        $ai_model  = 'mock';
        $ai_tokens = 0;
    } else {
        /* LIVE mode — call OpenRouter */
        $api_key = HearMed_Settings::get( 'hm_openrouter_api_key', '' );
        $model   = HearMed_Settings::get( 'hm_openrouter_model', 'anthropic/claude-3.5-sonnet' );

        if ( empty( $api_key ) ) {
            HearMed_Logger::log( 'ai_extraction', 'OpenRouter API key not configured' );
            return null;
        }

        $max_retries = intval( HearMed_Settings::get( 'hm_ai_max_retries', '2' ) );
        $attempt     = 0;
        $success     = false;
        $last_error  = '';

        while ( $attempt <= $max_retries && ! $success ) {
            $attempt++;
            $start_time = microtime( true );

            $response = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', [
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                    'HTTP-Referer'  => home_url(),
                    'X-Title'       => 'HearMed Clinical Extraction',
                ],
                'body' => wp_json_encode( [
                    'model'       => $model,
                    'messages'    => [
                        [ 'role' => 'system', 'content' => $system_prompt ],
                        [ 'role' => 'user',   'content' => $user_prompt ],
                    ],
                    'temperature'    => 0.1,
                    'max_tokens'     => 4000,
                    'response_format' => [ 'type' => 'json_object' ],
                ] ),
                'timeout' => 60,
            ] );

            $duration_ms = round( ( microtime( true ) - $start_time ) * 1000 );

            if ( is_wp_error( $response ) ) {
                $last_error = 'Network/cURL error: ' . $response->get_error_message();
                HearMed_Logger::log( 'ai_extraction', "OpenRouter error (attempt {$attempt}): " . $response->get_error_message() );
                continue;
            }

            $status_code = wp_remote_retrieve_response_code( $response );
            $raw_body    = wp_remote_retrieve_body( $response );
            $body        = json_decode( $raw_body, true );

            if ( $status_code !== 200 ) {
                $err_msg = $body['error']['message'] ?? ( $body['error']['code'] ?? "HTTP {$status_code}" );
                $last_error = "OpenRouter HTTP {$status_code}: {$err_msg}";
                HearMed_Logger::log( 'ai_extraction', "OpenRouter HTTP error (attempt {$attempt}): {$err_msg}", [
                    'status'   => $status_code,
                    'model'    => $model,
                    'raw_body' => substr( $raw_body, 0, 500 ),
                ] );
                continue;
            }

            $content = $body['choices'][0]['message']['content'] ?? '';
            $ai_tokens = $body['usage']['total_tokens'] ?? 0;
            $ai_model  = $model;

            // Strip markdown code fences if present
            $content = preg_replace( '/^```(?:json)?\s*/m', '', $content );
            $content = preg_replace( '/```\s*$/m', '', $content );
            $parsed  = json_decode( trim( $content ), true );

            if ( is_array( $parsed ) ) {
                $extracted = hm_normalize_ai_extracted( $parsed );
                $success   = true;

                HearMed_Logger::log( 'ai_extraction', "OpenRouter success: model={$model}, tokens={$ai_tokens}, duration={$duration_ms}ms, attempt={$attempt}", [
                    'extracted_preview' => substr( json_encode( $extracted ), 0, 500 ),
                ] );
            } else {
                $last_error = 'AI returned non-JSON response (len=' . strlen( $content ) . ')';
                HearMed_Logger::log( 'ai_extraction', "OpenRouter JSON parse failed (attempt {$attempt})", [
                    'content_preview' => substr( $content, 0, 300 ),
                ] );
            }
        }

        if ( ! $success ) {
            HearMed_Logger::log( 'ai_extraction', "AI extraction failed after {$max_retries} retries for appointment #{$appointment_id}: {$last_error}" );
            // Still create a draft doc with empty extraction so the review page works
        }
    }

    /* ── 6. Detect missing required fields ── */
    $missing = [];
    foreach ( $schema as $sec_key => $sec_info ) {
        if ( ! empty( $sec_info['fields'] ) ) {
            foreach ( $sec_info['fields'] as $fkey => $finfo ) {
                $is_required = ! empty( $finfo['required'] );
                $value = $extracted[ $sec_key ][ $fkey ] ?? null;
                if ( $is_required && ( $value === null || $value === '' ) ) {
                    $missing[] = $sec_key . '.' . $fkey;
                }
            }
        }
    }

    /* ── 7. Create clinical_docs record ── */
    $doc_id = $db->insert( 'hearmed_admin.appointment_clinical_docs', [
        'appointment_id'   => $appointment_id,
        'patient_id'       => $patient_id,
        'transcript_id'    => $transcript_id,
        'template_id'      => $template->id,
        'template_version' => $template->current_version ?? 1,
        'schema_snapshot'  => wp_json_encode( $sections ),
        'extracted_json'   => wp_json_encode( $extracted ),
        'missing_fields'   => wp_json_encode( $missing ),
        'anonymised_text'  => $anon_text,
        'ai_model'         => $ai_model,
        'ai_tokens_used'   => $ai_tokens,
        'status'           => 'draft',
        'created_at'       => current_time( 'mysql' ),
        'updated_at'       => current_time( 'mysql' ),
    ] );

    HearMed_Logger::log( 'ai_extraction', "Created clinical doc #{$doc_id} for appointment #{$appointment_id}, model={$ai_model}" );

    return $doc_id;
}


// ═══════════════════════════════════════════════════════════════════════════
//  25. PRSI INFO
// ═══════════════════════════════════════════════════════════════════════════

function hm_ajax_get_prsi_info() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $pid = intval( $_POST['patient_id'] ?? 0 );
    if ( ! $pid ) wp_send_json_error( 'Missing patient_id' );

    $db = HearMed_DB::instance();

    $claim = $db->get_row(
        "SELECT MAX(invoice_date) AS last_claim_date, SUM(prsi_amount) AS total_claimed
         FROM hearmed_core.invoices
         WHERE patient_id = \$1 AND prsi_applicable = true AND payment_status != 'Void'",
        [ $pid ]
    );

    $last_claim = $claim && $claim->last_claim_date ? $claim->last_claim_date : '';
    $next_eligible = '';
    if ( $last_claim ) {
        $lcd = new DateTime( $last_claim );
        $lcd->modify( '+4 years' );
        $next_eligible = $lcd->format( 'Y-m-d' );
    }

    wp_send_json_success( [
        'last_claim_date'  => $last_claim,
        'total_claimed'    => (float) ( $claim->total_claimed ?? 0 ),
        'next_eligible'    => $next_eligible,
        'is_eligible_now'  => ! $last_claim || ( new DateTime( $next_eligible ) <= new DateTime() ),
    ]);
}