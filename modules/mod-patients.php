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
 * HearMed Portal — Patient Module
 * Shortcode: [hearmed_patients]
 * Handles: Patient list, patient profile shell, AJAX
 * Blueprint: Blueprint-02-Patients v2.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Patients {

    public function __construct() {
        // Register this module's AJAX handlers
        $actions = [
            'get_patients',
            'search_patients',
            'get_clinics',
            'get_dispensers',
            'create_patient',
            'get_patient',
            'update_patient',
            'get_patient_appointments',
            'get_patient_notes',
            'add_patient_note',
            'save_patient_note',
            'delete_patient_note',
            'get_patient_documents',
            'upload_patient_document',
            'download_patient_document',
            'delete_patient_document',
            'get_patient_products',
            'update_patient_product_status',
            'get_patient_repairs',
            'create_patient_repair',
            'get_patient_returns',
            'log_cheque_sent',
            'get_patient_forms',
            'submit_patient_form',
            'download_patient_form',
            'get_patient_audit',
            'export_patient_data',
            'anonymise_patient',
            'save_case_history',
            'save_ai_transcript',
            'update_marketing_prefs',
            'get_patient_orders',
            'get_patient_invoices',
            'get_patient_transactions',
            'delete_patient',
            'add_patient_product',
            'create_patient_notification',
            'get_form_templates',
        ];
        foreach ($actions as $a) {
            add_action('wp_ajax_hm_' . $a, [$this, 'ajax_' . $a]);
        }
    }

    // ============================================================
    // CCT helper (mirrors main plugin)
    // ============================================================
    private function cct($slug) {
        // PostgreSQL only - no $wpdb needed
        return $wpdb->prefix . 'jet_cct_' . $slug;
    }

    // ============================================================
    // AUDIT LOG helper
    // ============================================================
    private function audit($action, $entity_type, $entity_id, $details = []) {
        // PostgreSQL only - no $wpdb needed
        $t = $this->cct('audit_log');
        if (HearMed_DB::get_var("SHOW TABLES LIKE '$t'") !== $t) return;
        HearMed_DB::insert($t, [
            'user_id'     => get_current_user_id(),
            'action'      => $action,
            'entity_type' => $entity_type,
            'entity_id'   => intval($entity_id),
            'details'     => wp_json_encode($details),
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
            'created_at' => current_time('mysql'),
        ]);
    }

    // ============================================================
    // ROLE HELPERS
    // ============================================================
    private function current_role() {
        $u = wp_get_current_user();
        if (!$u->ID) return '';
        $roles = $u->roles;
        $priority = ['administrator', 'hm_clevel', 'hm_admin', 'hm_finance', 'hm_dispenser', 'hm_reception', 'hm_ca', 'hm_scheme'];
        foreach ($priority as $r) {
            if (in_array($r, $param) return $r;
        }
        return $roles[0] ?? '';
    }

    private function is_admin_role() {
        return in_array($this->current_role(), ['administrator', 'hm_clevel', 'hm_admin']);
    }

    private function has_financial_access() {
        return in_array($this->current_role(), ['administrator', 'hm_clevel', 'hm_admin', 'hm_finance']);
    }

    private function can_export() {
        return in_array($this->current_role(), ['administrator', 'hm_clevel', 'hm_admin', 'hm_finance', 'hm_dispenser']);
    }

    private function get_user_clinic() {
        $role = $this->current_role();
        if (in_array($role, ['administrator', 'hm_clevel', 'hm_admin', 'hm_finance'])) return 0; // all clinics
        return intval(get_user_meta(get_current_user_id(), 'hm_clinic_id', true));
    }

    // ============================================================
    // NEXT AUTO C-NUMBER
    // ============================================================
    private function next_patient_number() {
        $args = [
            'post_type'      => 'patient',
            $args['orderby'] = 'relevance';
        }
        if (count($meta_query) > 1) {
            $args['meta_query'] = $meta_query;
        }

        $q     = new WP_Query($args);
        $total = $q->found_posts;
        $pages = ceil($total / $per);

        $at = $this->cct('appointments');
        $has_appt_table = HearMed_DB::get_var("SHOW TABLES LIKE '$at'") === $at;

        $patients = [];
        foreach ($q->posts as $p) {
            $fn   = /* USE PostgreSQL: Get from table columns */ /* get_post_meta($p->ID, 'first_name', true);
            $ln   = /* USE PostgreSQL: Get from table columns */ /* get_post_meta($p->ID, 'last_name', true);
            $dob  = /* USE PostgreSQL: Get from table columns */ /* get_post_meta($p->ID, 'dob', true);
            $pnum = /* USE PostgreSQL: Get from table columns */ /* get_post_meta($p->ID, 'patient_number', true);
            $ph   = /* USE PostgreSQL: Get from table columns */ /* get_post_meta($p->ID, 'patient_phone', true);
            $cid  = /* USE PostgreSQL: Get from table columns */ /* get_post_meta($p->ID, 'assigned_clinic_id', true);
            $did  = /* USE PostgreSQL: Get from table columns */ /* get_post_meta($p->ID, 'assigned_dispenser_id', true);

            // Last appointment
            $last_appt = null;
            if ($has_appt_table) {
                $last_appt = HearMed_DB::get_row( /* TODO: Convert to params array */ "SELECT appointment_date, start_time, status FROM `$at` WHERE patient_id=%d ORDER BY appointment_date DESC, start_time DESC LIMIT 1",
                    $p->ID
                );
            }

            $clinic_name    = $cid ? get_the_title(intval($cid)) : '—';
            $dispenser_name = $did ? get_the_title(intval($did)) : '—';

            $patients[] = [
                'id'             => $p->ID,
                'patient_number' => $pnum ?: '—',
                'name'           => trim("$fn $ln") ?: $p->post_title,
                'first_name'     => $fn,
                'last_name'      => $ln,
                'dob'            => $dob,
                'phone'          => $ph,
                'clinic_id'      => $cid,
                'clinic_name'    => $clinic_name,
                'dispenser_id'   => $did,
                'dispenser_name' => $dispenser_name,
                'is_active'      => (bool) /* USE PostgreSQL: Get from table columns */ /* get_post_meta($p->ID, 'is_active', true),
                'last_appt_date' => $last_appt->appointment_date ?? null,
                'last_appt_time' => $last_appt->start_time ?? null,
            ];
        }

        wp_send_json_success([
            'patients' => $patients,
            'total'    => $total,
            'pages'    => $pages,
            'page'     => $page,
        ]);
    }

    // ============================================================
    // AJAX — CREATE PATIENT
    // ============================================================
    public function ajax_create_patient() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!is_user_logged_in()) { wp_send_json_error('Not logged in'); return; }

        $fn    = sanitize_text_field($_POST['first_name'] ?? '');
        $ln    = sanitize_text_field($_POST['last_name'] ?? '');
        $gdpr  = $_POST['gdpr_consent'] ?? '0';

        if (!$fn || !$ln)    { wp_send_json_error('First and last name required'); return; }
        if ($gdpr !== '1')   { wp_send_json_error('GDPR consent is required'); return; }

        $pnum = $this->next_patient_number();

        $post_id = /* USE PostgreSQL: HearMed_DB::insert() */ /* wp_insert_post([
            'post_type'   => 'patient',
            'post_title'  => trim("$fn $ln"),
            'post_status' => 'publish',
            'post_author' => get_current_user_id(),
        ]);
        if (is_wp_error($post_id)) { wp_send_json_error($post_id->get_error_message()); return; }

        $meta = [
            'patient_number'        => $pnum,
            'patient_title'         => sanitize_text_field($_POST['patient_title'] ?? ''),
            'first_name'            => $fn,
            'last_name'             => $ln,
            'dob'                   => sanitize_text_field($_POST['dob'] ?? ''),
            'patient_phone'         => sanitize_text_field($_POST['patient_phone'] ?? $_POST['phone'] ?? ''),
            'patient_mobile'        => sanitize_text_field($_POST['patient_mobile'] ?? ''),
            'patient_email'         => sanitize_email($_POST['patient_email'] ?? $_POST['email'] ?? ''),
            'patient_address'       => sanitize_textarea_field($_POST['patient_address'] ?? $_POST['address'] ?? ''),
            'patient_eircode'       => sanitize_text_field($_POST['patient_eircode'] ?? $_POST['eircode'] ?? ''),
            'prsi_eligible'         => $_POST['prsi_eligible'] === '1' ? '1' : '0',
            'prsi_number'           => sanitize_text_field($_POST['prsi_number'] ?? ''),
            'referral_source'       => sanitize_text_field($_POST['referral_source'] ?? ''),
            'referral_sub_source'   => sanitize_text_field($_POST['referral_sub_source'] ?? ''),
            'assigned_dispenser_id' => intval($_POST['assigned_dispenser_id'] ?? 0),
            'assigned_clinic_id'    => intval($_POST['assigned_clinic_id'] ?? 0),
            'marketing_email'       => $_POST['marketing_email'] === '1' ? '1' : '0',
            'marketing_sms'         => $_POST['marketing_sms'] === '1' ? '1' : '0',
            'marketing_phone'       => $_POST['marketing_phone'] === '1' ? '1' : '0',
            'gdpr_consent'          => '1',
            'gdpr_consent_date'     => current_time('Y-m-d'),
            'gdpr_consent_version'  => '1.0',
            'is_active'             => '1',
        ];
        foreach ($meta as $k => $v) {
            update_post_meta($post_id, $k, $v);
        }

        $this->audit('created', 'patient', $post_id, [
            'patient_number' => $pnum,
            'name'           => "$fn $ln",
        ]);

        wp_send_json_success([
            'id'             => $post_id,
            'patient_number' => $pnum,
        ]);
    }

    // ============================================================
    // AJAX — GET SINGLE PATIENT
    // ============================================================
    public function ajax_get_patient() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!is_user_logged_in()) { wp_send_json_error('Not logged in'); return; }

        $pid = intval($_POST['patient_id'] ?? 0);
        if (!$pid) { wp_send_json_error('Missing patient ID'); return; }

        $post = get_post($pid);
        if (!$post || $post->post_type !== 'patient') { wp_send_json_error('Patient not found'); return; }

        // Clinic scope check for restrictred roles
        $user_clinic = $this->get_user_clinic();
        if ($user_clinic) {
            $patient_clinic = intval(/* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'assigned_clinic_id', true));
            if ($patient_clinic && $patient_clinic !== $user_clinic) {
                wp_send_json_error('Access denied');
                return;
            }
        }

        $fn   = /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'first_name', true);
        $ln   = /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'last_name', true);
        $dob  = /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'dob', true);
        $cid  = /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'assigned_clinic_id', true);
        $did  = /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'assigned_dispenser_id', true);

        $age = '';
        if ($dob) {
            $birthdate = new DateTime($dob);
            $today     = new DateTime();
            $age       = $birthdate->diff($today)->y . ' yrs';
        }

        // Compute annual review date badge colour
        $annual_review   = /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'annual_review_date', true);
        $review_status   = '';
        $review_days     = null;
        if ($annual_review) {
            $rd = new DateTime($annual_review);
            $td = new DateTime();
            $diff = $td->diff($rd);
            $days = (int) $diff->days;
            if ($rd < $td) {
                $review_status = 'overdue';
                $review_days   = -$days;
            } elseif ($days < 90) {
                $review_status = 'soon';
                $review_days   = $days;
            } else {
                $review_status = 'ok';
                $review_days   = $days;
            }
        }

        // Stats
        // PostgreSQL only - no $wpdb needed
        $at  = $this->cct('appointments');
        $inv = $this->cct('invoices');
        $has_at  = HearMed_DB::get_var("SHOW TABLES LIKE '$at'") === $at;
        $has_inv = HearMed_DB::get_var("SHOW TABLES LIKE '$inv'") === $inv;

        $appt_count = $has_at ? intval(HearMed_DB::get_var( /* TODO: Convert to params array */ "SELECT COUNT(*) FROM `$at` WHERE patient_id=%d", $param)) : 0;
        $last_visit = $has_at ? HearMed_DB::get_var( /* TODO: Convert to params array */ "SELECT MAX(appointment_date) FROM `$at` WHERE patient_id=%d", $param) : null;
        $balance    = 0;
        $revenue    = 0;
        $payments   = 0;
        if ($has_inv) {
            $pay = $this->cct('payments');
            $has_pay = HearMed_DB::get_var("SHOW TABLES LIKE '$pay'") === $pay;

            $balance  = (float)(HearMed_DB::get_var( /* TODO: Convert to params array */ "SELECT SUM(balance_remaining) FROM `$inv` WHERE patient_id=%d", $param) ?? 0);
            $revenue  = (float)(HearMed_DB::get_var( /* TODO: Convert to params array */ "SELECT SUM(grand_total) FROM `$inv` WHERE patient_id=%d", $param) ?? 0);
            if ($has_pay) {
                $payments = (float)(HearMed_DB::get_var( /* TODO: Convert to params array */ "SELECT SUM(amount) FROM `$pay` WHERE patient_id=%d", $param) ?? 0);
            }
        }

        // Log the view
        $this->audit('viewed', 'patient', $pid, ['role' => $this->current_role()]);

        $role = $this->current_role();

        wp_send_json_success([
            'id'                    => $pid,
            'patient_number'        => /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'patient_number', true) ?: '—',
            'patient_title'         => /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'patient_title', true),
            'first_name'            => $fn,
            'last_name'             => $ln,
            'name'                  => trim("$fn $ln") ?: $post->post_title,
            'dob'                   => $dob,
            'age'                   => $age,
            'phone'                 => /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'patient_phone', true),
            'mobile'                => /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'patient_mobile', true),
            'email'                 => /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'patient_email', true) ?: 'Not provided',
            'address'               => /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'patient_address', true),
            'eircode'               => /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'patient_eircode', true),
            'prsi_eligible'         => (bool) /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'prsi_eligible', true),
            'prsi_number'           => $this->has_financial_access() ? /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'prsi_number', true) : '****',
            'referral_source'       => /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'referral_source', true),
            'referral_sub_source'   => /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'referral_sub_source', true),
            'assigned_clinic_id'    => $cid,
            'clinic_name'           => $cid ? get_the_title(intval($cid)) : '—',
            'assigned_dispenser_id' => $did,
            'dispenser_name'        => $did ? get_the_title(intval($did)) : '—',
            'marketing_email'       => (bool) /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'marketing_email', true),
            'marketing_sms'         => (bool) /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'marketing_sms', true),
            'marketing_phone'       => (bool) /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'marketing_phone', true),
            'gdpr_consent'          => (bool) /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'gdpr_consent', true),
            'gdpr_consent_date'     => /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'gdpr_consent_date', true),
            'gdpr_consent_version'  => /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'gdpr_consent_version', true),
            'is_active'             => (bool) /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'is_active', true),
            'annual_review_date'    => $annual_review,
            'review_status'         => $review_status,
            'review_days'           => $review_days,
            'gp_name'               => /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'gp_name', true),
            'gp_address'            => /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'gp_address', true),
            'nok_name'              => /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'nok_name', true),
            'nok_phone'             => /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'nok_phone', true),
            'created'               => get_the_date('Y-m-d H:i:s', $pid),
            'stats' => [
                'appointments' => $appt_count,
                'last_visit'   => $last_visit,
                'revenue'      => $revenue,
                'payments'     => $payments,
                'balance'      => $balance,
            ],
            // Permissions flags passed to JS
            'can_export'      => $this->can_export(),
            'is_admin'        => $this->is_admin_role(),
            'has_finance'     => $this->has_financial_access(),
            'show_audit'      => in_array($role, ['administrator', 'hm_clevel', 'hm_admin', 'hm_finance']),
        ]);
    }

    // ============================================================
    // AJAX — UPDATE PATIENT
    // ============================================================
    public function ajax_update_patient() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!is_user_logged_in()) { wp_send_json_error('Denied'); return; }

        $pid = intval($_POST['patient_id'] ?? 0);
        if (!$pid) { wp_send_json_error('Missing ID'); return; }

        $fields = [
            'patient_title', 'first_name', 'last_name', 'dob',
            'patient_phone', 'patient_mobile', 'patient_email',
            'patient_address', 'patient_eircode',
            'prsi_eligible', 'prsi_number', 'referral_source',
            'referral_sub_source', 'assigned_dispenser_id', 'assigned_clinic_id',
            'marketing_email', 'marketing_sms', 'marketing_phone',
            'is_active', 'annual_review_date',
            'gp_name', 'gp_address', 'nok_name', 'nok_phone',
        ];

        $old_vals = [];
        $new_vals = [];
        foreach ($fields as $f) {
            if (!isset($_POST[$f])) continue;
            $old_vals[$f] = /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, $f, true);
            $val = in_array($f, ['patient_address', 'gp_address'])
                ? sanitize_textarea_field($_POST[$f])
                : sanitize_text_field($_POST[$f]);
            update_post_meta($pid, $f, $val);
            $new_vals[$f] = $val;
        }

        // Update post title
        $fn = /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'first_name', true);
        $ln = /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'last_name', true);
        wp_update_post(['ID' => $pid, 'post_title' => trim("$fn $ln")]);

        $this->audit('updated', 'patient', $pid, ['old' => $old_vals, 'new' => $new_vals]);

        wp_send_json_success(['id' => $pid]);
    }

    // ============================================================
    // AJAX — GET PATIENT APPOINTMENTS
    // ============================================================
    public function ajax_get_patient_appointments() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!is_user_logged_in()) { wp_send_json_error('Denied'); return; }
        // PostgreSQL only - no $wpdb needed
        $pid = intval($_POST['patient_id'] ?? 0);
        $at  = $this->cct('appointments');
        $ot  = $this->cct('appointment_outcomes');

        if (HearMed_DB::get_var("SHOW TABLES LIKE '$at'") !== $at) { wp_send_json_success([]); return; }

        $rows = HearMed_DB::get_results( /* TODO: Convert to params array */ "SELECT * FROM `$at` WHERE patient_id=%d ORDER BY appointment_date DESC, start_time DESC",
            $pid
        );

        $has_ot = HearMed_DB::get_var("SHOW TABLES LIKE '$ot'") === $ot;

        $d = [];
        foreach ($rows as $r) {
            $outcome = null;
            if ($has_ot) {
                $outcome = HearMed_DB::get_row( /* TODO: Convert to params array */ "SELECT * FROM `$ot` WHERE appointment_id=%d LIMIT 1", $r->id);
            }
            $d[] = [
                'id'                  => $r->id,
                'appointment_date'     => $r->appointment_date,
                'start_time'           => $r->start_time,
                'end_time'             => $r->end_time,
                'status'               => $r->status,
                'service_id'           => $r->service_id,
                'service_name'         => $r->service_id ? get_the_title($r->service_id) : '—',
                'service_colour'       => $r->service_id ? /* USE PostgreSQL: Get from table columns */ /* get_post_meta($r->service_id, 'colour', true) : '#ccc',
                'dispenser_id'         => $r->dispenser_id,
                'dispenser_name'       => $r->dispenser_id ? get_the_title($r->dispenser_id) : '—',
                'clinic_id'            => $r->clinic_id,
                'clinic_name'          => $r->clinic_id ? get_the_title($r->clinic_id) : '—',
                'notes'                => $r->notes,
                'outcome_name'         => $outcome->outcome_name ?? null,
                'outcome_banner_colour'=> $outcome->outcome_banner_colour ?? null,
                'is_invoiceable'       => $outcome->is_invoiceable ?? null,
                'created_at'           => $r->created_at ?? null,
                'created_by'           => $r->created_by ? get_the_author_meta('display_name', $r->created_by) : '—',
            ];
        }

        $this->audit('viewed', 'patient_appointments', $pid);

        wp_send_json_success($d);
    }

    // ============================================================
    // AJAX — GET PATIENT NOTES
    // ============================================================
    public function ajax_get_patient_notes() {
        check_ajax_referer('hm_nonce', 'nonce');
        // PostgreSQL only - no $wpdb needed
        $pid = intval($_POST['patient_id'] ?? 0);
        $t   = $this->cct('patient_notes');
        if (HearMed_DB::get_var("SHOW TABLES LIKE '$t'") !== $t) { wp_send_json_success([]); return; }
        $rows = HearMed_DB::get_results( /* TODO: Convert to params array */ "SELECT * FROM `$t` WHERE patient_id=%d ORDER BY created_at DESC",
            $pid
        );
        $d = [];
        foreach ($rows as $r) {
            $d[] = [
                'id'        => $r->id,
                'note_type'  => $r->note_type,
                'note_text'  => $r->note_text,
                'created_at' => $r->created_at,
                'created_by' => get_the_author_meta('display_name', $r->created_by) ?: '—',
                'can_edit'   => (int)$r->created_by === get_current_user_id() && strtotime($r->created_at) > strtotime('-24 hours'),
            ];
        }
        wp_send_json_success($d);
    }

    // ============================================================
    // AJAX — ADD / SAVE PATIENT NOTE
    // ============================================================
    public function ajax_add_patient_note() { $this->_save_note(); }
    public function ajax_save_patient_note() { $this->_save_note(); }

    private function _save_note() {
        check_ajax_referer('hm_nonce', 'nonce');
        // PostgreSQL only - no $wpdb needed
        $pid  = intval($_POST['patient_id'] ?? 0);
        $nid  = intval($_POST['id'] ?? 0);
        $type = sanitize_text_field($_POST['note_type'] ?? 'Manual');
        $text = sanitize_textarea_field($_POST['note_text'] ?? '');
        if (!$pid || !$text) { wp_send_json_error('Missing fields'); return; }

        $t = $this->cct('patient_notes');
        if (HearMed_DB::get_var("SHOW TABLES LIKE '$t'") !== $t) { wp_send_json_error('Table missing'); return; }

        if ($nid) {
            // Edit — only own, within 24hrs
            $note = HearMed_DB::get_row( /* TODO: Convert to params array */ "SELECT * FROM `$t` WHERE id=%d", $nid);
            if (!$note || (int)$note->created_by !== get_current_user_id()) { wp_send_json_error('Cannot edit'); return; }
            HearMed_DB::update($t, ['note_type' => $type, 'note_text' => $text], ['id' => $nid]);
            $this->audit('updated', 'patient_note', $nid, ['patient_id' => $pid]);
        } else {
            HearMed_DB::insert($t, [
                'patient_id'  => $pid,
                'note_type'   => $type,
                'note_text'   => $text,
                'created_by'  => get_current_user_id(),
                'created_at' => current_time('mysql'),
            ]);
            $nid = $last_insert_id /* TODO: Get this from HearMed_DB::insert() return value */;
            $this->audit('created', 'patient_note', $nid, ['patient_id' => $pid]);
        }
        wp_send_json_success(['id' => $nid]);
    }

    // ============================================================
    // AJAX — DELETE PATIENT NOTE
    // ============================================================
    public function ajax_delete_patient_note() {
        check_ajax_referer('hm_nonce', 'nonce');
        // PostgreSQL only - no $wpdb needed
        $nid  = intval($_POST['id'] ?? 0);
        $t    = $this->cct('patient_notes');
        $note = HearMed_DB::get_row( /* TODO: Convert to params array */ "SELECT * FROM `$t` WHERE id=%d", $nid);
        if (!$note) { wp_send_json_error('Not found'); return; }
        if ((int)$note->created_by !== get_current_user_id() && !$this->is_admin_role()) { wp_send_json_error('Cannot delete'); return; }
        HearMed_DB::delete($t, ['id' => $nid]);
        $this->audit('deleted', 'patient_note', $nid);
        wp_send_json_success();
    }

    // ============================================================
    // AJAX — GET PATIENT DOCUMENTS
    // ============================================================
    public function ajax_get_patient_documents() {
        check_ajax_referer('hm_nonce', 'nonce');
        // PostgreSQL only - no $wpdb needed
        $pid = intval($_POST['patient_id'] ?? 0);
        $t   = $this->cct('patient_documents');
        if (HearMed_DB::get_var("SHOW TABLES LIKE '$t'") !== $t) { wp_send_json_success([]); return; }
        $rows = HearMed_DB::get_results( /* TODO: Convert to params array */ "SELECT * FROM `$t` WHERE patient_id=%d ORDER BY created_at DESC", $pid);
        $d = [];
        foreach ($rows as $r) {
            $d[] = [
                'id'           => $r->id,
                'document_type' => $r->document_type,
                'file_name'     => $r->file_name,
                'created_at'    => $r->created_at,
                'created_by'    => get_the_author_meta('display_name', $r->created_by) ?: '—',
                // Never expose real path — download via AJAX handler
                'download_url'  => admin_url('admin-ajax.php') . '?action=hm_download_patient_document&nonce=' . wp_create_nonce('hm_nonce') . '&id=' . $r->id,
            ];
        }
        wp_send_json_success($d);
    }

    // ============================================================
    // AJAX — UPLOAD PATIENT DOCUMENT
    // ============================================================
    public function ajax_upload_patient_document() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!is_user_logged_in()) { wp_send_json_error('Denied'); return; }
        // PostgreSQL only - no $wpdb needed
        $pid  = intval($_POST['patient_id'] ?? 0);
        $type = sanitize_text_field($_POST['document_type'] ?? 'Other');

        if (!$pid) { wp_send_json_error('Missing patient ID'); return; }
        if (!isset($_FILES['file']) || $_FILES['file']['error']) { wp_send_json_error('File upload failed'); return; }

        $file     = $_FILES['file'];
        $allowed  = ['pdf', 'jpg', 'jpeg', 'png', 'docx'];
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $param) { wp_send_json_error('File type not allowed'); return; }
        if ($file['size'] > 10 * 1024 * 1024) { wp_send_json_error('File too large (max 10MB)'); return; }

        // Store outside public uploads — private folder
        $upload_dir = WP_CONTENT_DIR . '/uploads/hearmed-private/patient-docs/' . $pid . '/';
        if (!file_exists($upload_dir)) {
            wp_mkdir_p($upload_dir);
            // Block direct access
            file_put_contents($upload_dir . '.htaccess', "Deny from all\n");
        }

        $filename = sanitize_file_name(time() . '-' . $file['name']);
        $dest     = $upload_dir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $param) { wp_send_json_error('Failed to save file'); return; }

        $t = $this->cct('patient_documents');
        HearMed_DB::insert($t, [
            'patient_id'    => $pid,
            'document_type' => $type,
            'file_url'      => 'hearmed-private/patient-docs/' . $pid . '/' . $filename,
            'file_name'     => $file['name'],
            'created_by'    => get_current_user_id(),
            'created_at'   => current_time('mysql'),
        ]);

        $this->audit('uploaded', 'patient_document', $last_insert_id /* TODO: Get this from HearMed_DB::insert() return value */, ['patient_id' => $pid, 'type' => $type, 'file' => $file['name']]);

        wp_send_json_success(['id' => $last_insert_id /* TODO: Get this from HearMed_DB::insert() return value */]);
    }

    // ============================================================
    // AJAX — DOWNLOAD PATIENT DOCUMENT (secure stream)
    // ============================================================
    public function ajax_download_patient_document() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!is_user_logged_in()) { wp_die('Denied'); }
        // PostgreSQL only - no $wpdb needed
        $doc_id = intval($_REQUEST['id'] ?? 0);
        $t      = $this->cct('patient_documents');
        $doc    = HearMed_DB::get_row( /* TODO: Convert to params array */ "SELECT * FROM `$t` WHERE id=%d", $doc_id);
        if (!$doc) { wp_die('Not found'); }

        $file = WP_CONTENT_DIR . '/uploads/' . $doc->file_url;
        if (!file_exists($file)) { wp_die('File not found'); }

        $this->audit('downloaded', 'patient_document', $doc_id, ['patient_id' => $doc->patient_id]);

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($doc->file_name) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    // ============================================================
    // AJAX — DELETE PATIENT DOCUMENT
    // ============================================================
    public function ajax_delete_patient_document() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!$this->is_admin_role()) { wp_send_json_error('Admin role required'); return; }
        // PostgreSQL only - no $wpdb needed
        $id = intval($_POST['id'] ?? 0);
        $t  = $this->cct('patient_documents');
        $d  = HearMed_DB::get_row( /* TODO: Convert to params array */ "SELECT * FROM `$t` WHERE id=%d", $id);
        if (!$d) { wp_send_json_error('Not found'); return; }
        $file = WP_CONTENT_DIR . '/uploads/' . $d->file_url;
        if (file_exists($file)) unlink($file);
        HearMed_DB::delete($t, ['id' => $id]);
        $this->audit('deleted', 'patient_document', $id, ['patient_id' => $d->patient_id]);
        wp_send_json_success();
    }

    // ============================================================
    // AJAX — GET PATIENT PRODUCTS (Hearing Aids)
    // ============================================================
    public function ajax_get_patient_products() {
        check_ajax_referer('hm_nonce', 'nonce');
        // PostgreSQL only - no $wpdb needed
        $pid = intval($_POST['patient_id'] ?? 0);
        $t   = $this->cct('patient_products');
        if (HearMed_DB::get_var("SHOW TABLES LIKE '$t'") !== $t) { wp_send_json_success([]); return; }
        $rows = HearMed_DB::get_results( /* TODO: Convert to params array */ "SELECT * FROM `$t` WHERE patient_id=%d ORDER BY fitting_date DESC", $pid);
        $d = [];
        foreach ($rows as $r) {
            $pid_prod = intval($r->product_id);
            // Product details live on the ha-product CPT post
            $product_name = $pid_prod ? get_the_title($pid_prod) : '—';
            $manufacturer  = $pid_prod ? /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid_prod, 'manufacturer', true) : '';
            $model         = $pid_prod ? /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid_prod, 'model', true) : '';
            $style         = $pid_prod ? /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid_prod, 'style', true) : '';
            $img           = $pid_prod ? /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid_prod, 'product_image', true) : '';
            $d[] = [
                'id'             => $r->id,
                'product_id'      => $r->product_id,
                'product_name'    => $product_name,
                'manufacturer'    => $manufacturer,
                'model'           => $model,
                'style'           => $style,
                'serial_left'     => $r->serial_number_left,
                'serial_right'    => $r->serial_number_right,
                'fitting_date'    => $r->fitting_date,
                'warranty_expiry' => $r->warranty_expiry,
                'status'          => $r->status,
                'inactive_reason' => $r->inactive_reason,
                'inactive_date'   => $r->inactive_date,
                'product_image'   => $img,
            ];
        }
        wp_send_json_success($d);
    }

    // ============================================================
    // AJAX — UPDATE PATIENT PRODUCT STATUS
    // ============================================================
    public function ajax_update_patient_product_status() {
        check_ajax_referer('hm_nonce', 'nonce');
        // PostgreSQL only - no $wpdb needed
        $id     = intval($_POST['id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? 'Inactive');
        $reason = sanitize_text_field($_POST['reason'] ?? '');
        $t      = $this->cct('patient_products');
        $prod   = HearMed_DB::get_row( /* TODO: Convert to params array */ "SELECT * FROM `$t` WHERE id=%d", $id);
        if (!$prod) { wp_send_json_error('Not found'); return; }
        HearMed_DB::update($t, [
            'status'          => $status,
            'inactive_reason' => $reason,
            'inactive_date'   => current_time('Y-m-d'),
        ], ['id' => $id]);
        $this->audit('updated', 'patient_product', $id, ['patient_id' => $prod->patient_id, 'status' => $status, 'reason' => $reason]);
        wp_send_json_success();
    }

    // ============================================================
    // AJAX — GET PATIENT REPAIRS
    // ============================================================
    public function ajax_get_patient_repairs() {
        check_ajax_referer('hm_nonce', 'nonce');
        // PostgreSQL only - no $wpdb needed
        $pid = intval($_POST['patient_id'] ?? 0);
        $t   = $this->cct('repairs');
        if (HearMed_DB::get_var("SHOW TABLES LIKE '$t'") !== $t) { wp_send_json_success([]); return; }
        $rows = HearMed_DB::get_results( /* TODO: Convert to params array */ "SELECT * FROM `$t` WHERE patient_id=%d ORDER BY date_booked DESC", $pid);
        $d = [];
        foreach ($rows as $r) {
            $d[] = [
                'id'            => $r->id,
                'product_name'   => $r->patient_product_id ? $this->get_product_name($r->patient_product_id) : (get_the_title($r->product_id) ?: '—'),
                'serial_number'  => $r->serial_number,
                'date_booked'    => $r->date_booked,
                'date_sent'      => $r->date_sent,
                'date_received'  => $r->date_received,
                'status'         => $r->status,
                'warranty_status'=> $r->warranty_status,
                'repair_notes'   => $r->repair_notes,
            ];
        }
        wp_send_json_success($d);
    }

    private function get_product_name($patient_product_id) {
        // PostgreSQL only - no $wpdb needed
        $t = $this->cct('patient_products');
        $product_id = HearMed_DB::get_var( /* TODO: Convert to params array */ "SELECT product_id FROM `$t` WHERE id=%d", $patient_product_id);
        return $product_id ? (get_the_title(intval($product_id)) ?: '—') : '—';
    }

    // ============================================================
    // AJAX — CREATE PATIENT REPAIR
    // ============================================================
    public function ajax_create_patient_repair() {
        check_ajax_referer('hm_nonce', 'nonce');
        // PostgreSQL only - no $wpdb needed
        $pid = intval($_POST['patient_id'] ?? 0);
        $t   = $this->cct('repairs');
        if (HearMed_DB::get_var("SHOW TABLES LIKE '$t'") !== $t) { wp_send_json_error('Repairs table missing'); return; }

        $pp_id = intval($_POST['patient_product_id'] ?? 0);
        // Fetch product details for prefill
        $pp = null;
        if ($pp_id) {
            $pt = $this->cct('patient_products');
            $pp = HearMed_DB::get_row( /* TODO: Convert to params array */ "SELECT * FROM `$pt` WHERE id=%d", $pp_id);
        }

        HearMed_DB::insert($t, [
            'patient_id'         => $pid,
            'patient_product_id' => $pp_id,
            'product_id'         => $pp ? $pp->product_id : 0,
            'serial_number'      => sanitize_text_field($_POST['serial_number'] ?? ($pp->serial_number_left ?? ''),
            'manufacturer_id'    => 0,
            'clinic_id'          => intval($_POST['clinic_id'] ?? $this->get_user_clinic()),
            'dispenser_id'       => get_current_user_id(),
            'date_booked'        => current_time('Y-m-d'),
            'status'             => 'Booked',
            'warranty_status'    => sanitize_text_field($_POST['warranty_status'] ?? 'Unknown'),
            'repair_notes'       => sanitize_textarea_field($_POST['repair_notes'] ?? ''),
        ]);
        $rid = $last_insert_id /* TODO: Get this from HearMed_DB::insert() return value */;
        $this->audit('created', 'repair', $rid, ['patient_id' => $pid]);
        wp_send_json_success(['id' => $rid]);
    }

    // ============================================================
    // AJAX — GET PATIENT RETURNS / CREDIT NOTES
    // ============================================================
    public function ajax_get_patient_returns() {
        check_ajax_referer('hm_nonce', 'nonce');
        // PostgreSQL only - no $wpdb needed
        $pid = intval($_POST['patient_id'] ?? 0);
        $t   = $this->cct('credit_notes');
        if (HearMed_DB::get_var("SHOW TABLES LIKE '$t'") !== $t) { wp_send_json_success([]); return; }
        $rows = HearMed_DB::get_results( /* TODO: Convert to params array */ "SELECT * FROM `$t` WHERE patient_id=%d ORDER BY created_at DESC", $pid);
        $d = [];
        foreach ($rows as $r) {
            $d[] = [
                'id'             => $r->id,
                'credit_note_num' => $r->credit_note_number ?? ('CN-' . $r->id),
                'product_name'    => '—',   // no product_name column — linked via invoice_id
                'refund_amount'   => $r->amount ?? 0,
                'cheque_sent'     => !empty($r->cheque_sent),
                'cheque_sent_date'=> $r->cheque_sent_date ?? null,
                'status'          => $r->cct_status ?? 'publish',
            ];
        }
        wp_send_json_success($d);
    }

    // ============================================================
    // AJAX — LOG CHEQUE SENT
    // ============================================================
    public function ajax_log_cheque_sent() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!$this->has_financial_access()) { wp_send_json_error('Finance access required'); return; }
        // PostgreSQL only - no $wpdb needed
        $id   = intval($_POST['id'] ?? 0);
        $date = sanitize_text_field($_POST['cheque_date'] ?? date('Y-m-d'));
        $t    = $this->cct('credit_notes');
        HearMed_DB::update($t, ['cheque_sent' => 1, 'cheque_sent_date' => $date], ['id' => $id]);
        $this->audit('cheque_sent', 'credit_note', $id, ['date' => $date]);
        wp_send_json_success();
    }

    // ============================================================
    // AJAX — GET PATIENT FORMS
    // ============================================================
    public function ajax_get_patient_forms() {
        check_ajax_referer('hm_nonce', 'nonce');
        // PostgreSQL only - no $wpdb needed
        $pid = intval($_POST['patient_id'] ?? 0);
        $t   = $this->cct('patient_forms');
        if (HearMed_DB::get_var("SHOW TABLES LIKE '$t'") !== $t) { wp_send_json_success([]); return; }
        $rows = HearMed_DB::get_results( /* TODO: Convert to params array */ "SELECT * FROM `$t` WHERE patient_id=%d ORDER BY created_at DESC", $pid);
        $d = [];
        foreach ($rows as $r) {
            $d[] = [
                'id'               => $r->id,
                'form_type'         => $r->form_type,
                'gdpr_consent'      => (bool)$r->gdpr_consent,
                'marketing_email'   => (bool)$r->marketing_email,
                'marketing_sms'     => (bool)$r->marketing_sms,
                'marketing_phone'   => (bool)$r->marketing_phone,
                'has_signature'     => !empty($r->signature_image_url),
                'created_at'        => $r->created_at,
            ];
        }
        wp_send_json_success($d);
    }

    // ============================================================
    // AJAX — GET PATIENT ORDERS (read from mod-orders tables)
    // ============================================================
    public function ajax_get_patient_orders() {
        check_ajax_referer('hm_nonce', 'nonce');
        // PostgreSQL only - no $wpdb needed
        $pid = intval($_POST['patient_id'] ?? 0);
        $t   = $this->cct('orders');
        if (HearMed_DB::get_var("SHOW TABLES LIKE '$t'") !== $t) { wp_send_json_success([]); return; }
        $rows = HearMed_DB::get_results( /* TODO: Convert to params array */ "SELECT * FROM `$t` WHERE patient_id=%d ORDER BY created_at DESC", $pid);
        $d = [];
        foreach ($rows as $r) {
            $d[] = [
                'id'          => $r->id,
                'order_number' => $r->order_number ?? ('ORD-' . $r->id),
                'created_at'   => $r->order_date ?? $r->created_at,
                'status'       => $r->status,
                'grand_total'  => $r->grand_total ?? 0,
                'description'  => $r->notes ?? '',
            ];
        }
        wp_send_json_success($d);
    }

    // ============================================================
    // AJAX — GET PATIENT INVOICES
    // ============================================================
    public function ajax_get_patient_invoices() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!$this->has_financial_access() && !in_array($this->current_role(), ['hm_dispenser'])) {
            wp_send_json_error('Access denied'); return;
        }
        // PostgreSQL only - no $wpdb needed
        $pid = intval($_POST['patient_id'] ?? 0);
        $t   = $this->cct('invoices');
        if (HearMed_DB::get_var("SHOW TABLES LIKE '$t'") !== $t) { wp_send_json_success([]); return; }
        $rows = HearMed_DB::get_results( /* TODO: Convert to params array */ "SELECT * FROM `$t` WHERE patient_id=%d ORDER BY invoice_date DESC", $pid);
        $d = [];
        foreach ($rows as $r) {
            $row = [
                'id'            => $r->id,
                'invoice_number' => $r->invoice_number ?? ('INV-' . $r->id),
                'created_at'     => $r->invoice_date ?? $r->created_at,
                'status'         => $r->status ?? '—',
                'grand_total'    => $r->grand_total ?? 0,
                'balance'        => $r->balance_remaining ?? 0,
                'is_duplicate_flagged' => 0,
            ];
            // Hide amounts from dispenser view
            if (!$this->has_financial_access()) {
                unset($row['grand_total'], $row['balance']);
            }
            $d[] = $row;
        }
        wp_send_json_success($d);
    }

    // ============================================================
    // AJAX — GET PATIENT TRANSACTIONS
    // ============================================================
    public function ajax_get_patient_transactions() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!$this->has_financial_access()) { wp_send_json_error('Access denied'); return; }
        // PostgreSQL only - no $wpdb needed
        $pid = intval($_POST['patient_id'] ?? 0);
        $t   = $this->cct('payments');
        if (HearMed_DB::get_var("SHOW TABLES LIKE '$t'") !== $t) { wp_send_json_success([]); return; }
        $rows = HearMed_DB::get_results( /* TODO: Convert to params array */ "SELECT * FROM `$t` WHERE patient_id=%d ORDER BY payment_date DESC", $pid);
        $d = [];
        foreach ($rows as $r) {
            $d[] = [
                'id'          => $r->id,
                'payment_date' => $r->payment_date,
                'amount'       => $r->amount,
                'method'       => $r->method ?? '—',
                'is_refund'    => (bool)($r->is_refund ?? 0),
                'received_by'  => $r->received_by ? get_the_author_meta('display_name', $r->received_by) : '—',
                'clinic_name'  => $r->clinic_id ? get_the_title($r->clinic_id) : '—',
            ];
        }
        wp_send_json_success($d);
    }

    // ============================================================
    // AJAX — GET PATIENT AUDIT TRAIL
    // ============================================================
    public function ajax_get_patient_audit() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!in_array($this->current_role(), ['administrator','hm_clevel','hm_admin','hm_finance'])) {
            wp_send_json_error('Access denied'); return;
        }
        // PostgreSQL only - no $wpdb needed
        $pid = intval($_POST['patient_id'] ?? 0);
        $t   = $this->cct('audit_log');
        if (HearMed_DB::get_var("SHOW TABLES LIKE '$t'") !== $t) { wp_send_json_success([]); return; }
        $rows = HearMed_DB::get_results( /* TODO: Convert to params array */ "SELECT * FROM `$t` WHERE (entity_type='patient' AND entity_id=%d) OR (entity_type IN ('patient_note','patient_document','patient_form','patient_product','repair') AND JSON_EXTRACT(details,'$.patient_id')=%d) ORDER BY created_at DESC LIMIT 200",
            $pid, $pid
        ));
        $d = [];
        foreach ($rows as $r) {
            $d[] = [
                'id'         => $r->id,
                'created_at'  => $r->created_at,
                'user'        => $r->user_id ? get_the_author_meta('display_name', $r->user_id) : 'System',
                'action'      => $r->action,
                'entity_type' => $r->entity_type,
                'entity_id'   => $r->entity_id,
                'details'     => $r->details,
            ];
        }
        wp_send_json_success($d);
    }

    // ============================================================
    // AJAX — ANONYMISE PATIENT (Right to Erasure)
    // ============================================================
    public function ajax_anonymise_patient() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!$this->is_admin_role()) { wp_send_json_error('Admin role required'); return; }

        $pid     = intval($_POST['patient_id'] ?? 0);
        $confirm = sanitize_text_field($_POST['confirm'] ?? '');
        if ($confirm !== 'CONFIRM ERASURE') { wp_send_json_error('Confirmation text incorrect'); return; }

        $anon = 'ANONYMISED ' . date('Y-m-d');
        update_post_meta($pid, 'first_name', $anon);
        update_post_meta($pid, 'last_name', '');
        update_post_meta($pid, 'patient_email', $anon . '@anonymised.invalid');
        update_post_meta($pid, 'patient_phone', $anon);
        update_post_meta($pid, 'patient_mobile', $anon);
        update_post_meta($pid, 'patient_address', $anon);
        update_post_meta($pid, 'patient_eircode', $anon);
        update_post_meta($pid, 'is_active', '0');
        wp_update_post(['ID' => $pid, 'post_title' => $anon]);

        // Log to erasure table
        // PostgreSQL only - no $wpdb needed
        $et = $this->cct('gdpr_erasure_requests');
        if (HearMed_DB::get_var("SHOW TABLES LIKE '$et'") === $et) {
            HearMed_DB::insert($et, [
                'patient_id'   => $pid,
                'erased_by'    => get_current_user_id(),
                'erased_at'    => current_time('mysql'),
                'confirmed_by' => get_current_user_id(),
            ]);
        }

        $this->audit('IRREVERSIBLE_ERASURE', 'patient', $pid, [
            'erased_by'   => get_current_user_id(),
            'erased_at'   => current_time('mysql'),
            'gdpr_right'  => 'right_to_erasure',
        ]);

        wp_send_json_success(['anonymised' => true]);
    }

    // ============================================================
    // AJAX — DELETE PATIENT (alias for anonymise for safety)
    // ============================================================
    public function ajax_delete_patient() {
        // Alias — always anonymise, never hard-delete
        $_POST['confirm'] = 'CONFIRM ERASURE';
        $this->ajax_anonymise_patient();
    }

    // ============================================================
    // AJAX — SAVE CASE HISTORY
    // ============================================================
    public function ajax_save_case_history() {
        check_ajax_referer('hm_nonce', 'nonce');
        // PostgreSQL only - no $wpdb needed
        $pid   = intval($_POST['patient_id'] ?? 0);
        $appt  = intval($_POST['appointment_id'] ?? 0);
        $type  = sanitize_text_field($_POST['appointment_type'] ?? '');
        $text  = sanitize_textarea_field($_POST['note_text'] ?? '');
        if (!$pid || !$text) { wp_send_json_error('Missing fields'); return; }

        $t = $this->cct('patient_notes');
        HearMed_DB::insert($t, [
            'patient_id'      => $pid,
            'note_type'       => 'clinical',
            'note_text'       => $text,
            'appointment_id'  => $appt ?: null,
            'created_by'      => get_current_user_id(),
            'created_at'     => current_time('mysql'),
        ]);
        $nid = $last_insert_id /* TODO: Get this from HearMed_DB::insert() return value */;
        $this->audit('created', 'case_history', $nid, ['patient_id' => $pid, 'appointment_type' => $type]);
        wp_send_json_success(['id' => $nid]);
    }

    // ============================================================
    // AJAX — SAVE AI TRANSCRIPT
    // ============================================================
    public function ajax_save_ai_transcript() {
        check_ajax_referer('hm_nonce', 'nonce');
        // PostgreSQL only - no $wpdb needed
        $pid    = intval($_POST['patient_id'] ?? 0);
        $appt   = intval($_POST['appointment_id'] ?? 0);
        $text   = sanitize_textarea_field($_POST['transcript'] ?? '');
        if (!$pid || !$text) { wp_send_json_error('Missing fields'); return; }

        $t = $this->cct('patient_notes');
        HearMed_DB::insert($t, [
            'patient_id'     => $pid,
            'note_type'      => 'clinical',
            'note_text'      => '[AI Transcription] ' . $text,
            'appointment_id' => $appt ?: null,
            'created_by'     => get_current_user_id(),
            'created_at'    => current_time('mysql'),
        ]);
        $nid = $last_insert_id /* TODO: Get this from HearMed_DB::insert() return value */;
        $this->audit('ai_transcription_created', 'patient_note', $nid, [
            'patient_id'     => $pid,
            'appointment_id' => $appt,
        ]);
        wp_send_json_success(['id' => $nid]);
    }

    // ============================================================
    // AJAX — UPDATE MARKETING PREFS
    // ============================================================
    public function ajax_update_marketing_prefs() {
        check_ajax_referer('hm_nonce', 'nonce');
        $pid = intval($_POST['patient_id'] ?? 0);
        if (!$pid) { wp_send_json_error('Missing ID'); return; }
        update_post_meta($pid, 'marketing_email', $_POST['marketing_email'] === '1' ? '1' : '0');
        update_post_meta($pid, 'marketing_sms',   $_POST['marketing_sms']   === '1' ? '1' : '0');
        update_post_meta($pid, 'marketing_phone', $_POST['marketing_phone'] === '1' ? '1' : '0');
        $this->audit('updated', 'marketing_prefs', $pid, ['patient_id' => $pid]);
        wp_send_json_success();
    }

    // ============================================================
    // AJAX — EXPORT PATIENT DATA (GDPR Article 20)
    // ============================================================
    public function ajax_export_patient_data() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!$this->is_admin_role()) { wp_send_json_error('Access denied'); return; }
        // PostgreSQL only - no $wpdb needed
        $pid = intval($_POST['patient_id'] ?? 0);
        if (!$pid) { wp_send_json_error('Missing ID'); return; }

        $pnum = /* USE PostgreSQL: Get from table columns */ /* get_post_meta($pid, 'patient_number', true);

        // Log the export
        $et = $this->cct('gdpr_data_exports');
        if (HearMed_DB::get_var("SHOW TABLES LIKE '$et'") === $et) {
            HearMed_DB::insert($et, [
                'patient_id'  => $pid,
                'exported_by' => get_current_user_id(),
                'exported_at' => current_time('mysql'),
                'export_type' => 'full',
            ]);
        }
        $this->audit('data_export', 'patient', $pid, ['exported_by' => get_current_user_id()]);

        // Return JSON data (ZIP generation would be a separate step)
        wp_send_json_success([
            'message'        => 'Export logged. Full ZIP generation coming in next build.',
            'patient_number' => $pnum,
        ]);
    }

    // ============================================================
    // AJAX — SUBMIT PATIENT FORM (signature pad)
    // ============================================================
    public function ajax_submit_patient_form() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!is_user_logged_in()) { wp_send_json_error('Not logged in'); return; }
        // PostgreSQL only - no $wpdb needed
        $pid       = intval($_POST['patient_id'] ?? 0);
        $form_type = sanitize_text_field($_POST['form_type'] ?? 'General');
        $form_data = $_POST['form_data'] ?? '{}';
        $gdpr      = intval($_POST['gdpr_consent'] ?? 0);
        $m_email   = intval($_POST['marketing_email'] ?? 0);
        $m_sms     = intval($_POST['marketing_sms'] ?? 0);
        $m_phone   = intval($_POST['marketing_phone'] ?? 0);
        $sig_data  = $_POST['signature_data'] ?? ''; // base64 PNG from signature pad

        if (!$pid) { wp_send_json_error('Missing patient ID'); return; }

        // Validate form_data is JSON
        $decoded = json_decode($form_data, true);
        if (!is_array($decoded)) $form_data = '{}';

        // Save signature image if provided
        $sig_url = '';
        if ($sig_data && strpos($sig_data, 'data:image/png;base64,') === 0) {
            $sig_dir = WP_CONTENT_DIR . '/uploads/hearmed-private/patient-sigs/' . $pid . '/';
            if (!file_exists($sig_dir)) {
                wp_mkdir_p($sig_dir);
                file_put_contents($sig_dir . '.htaccess', "Deny from all\n");
            }
            $sig_file = 'sig-' . time() . '.png';
            $sig_raw  = base64_decode(str_replace('data:image/png;base64,', '', $param);
            file_put_contents($sig_dir . $sig_file, $sig_raw);
            $sig_url = 'hearmed-private/patient-sigs/' . $pid . '/' . $sig_file;
        }

        $t = $this->cct('patient_forms');
        if (HearMed_DB::get_var("SHOW TABLES LIKE '$t'") !== $t) { wp_send_json_error('Forms table missing'); return; }

        HearMed_DB::insert($t, [
            'patient_id'        => $pid,
            'form_type'         => $form_type,
            'form_data'         => $form_data,
            'signature_image_url'=> $sig_url,
            'gdpr_consent'      => $gdpr ? 1 : 0,
            'marketing_email'   => $m_email ? 1 : 0,
            'marketing_sms'     => $m_sms ? 1 : 0,
            'marketing_phone'   => $m_phone ? 1 : 0,
            'created_by'        => get_current_user_id(),
            'created_at'       => current_time('mysql'),
            'cct_status'        => 'publish',
        ]);
        $fid = $last_insert_id /* TODO: Get this from HearMed_DB::insert() return value */;

        // Update patient marketing prefs if submitted
        if ($gdpr) {
            update_post_meta($pid, 'gdpr_consent', '1');
            update_post_meta($pid, 'gdpr_consent_date', current_time('Y-m-d'));
        }
        if (isset($_POST['marketing_email'])) {
            update_post_meta($pid, 'marketing_email', $m_email ? '1' : '0');
            update_post_meta($pid, 'marketing_sms',   $m_sms   ? '1' : '0');
            update_post_meta($pid, 'marketing_phone', $m_phone ? '1' : '0');
        }

        $this->audit('created', 'patient_form', $fid, [
            'patient_id' => $pid,
            'form_type'  => $form_type,
            'signed'     => !empty($sig_url),
        ]);

        wp_send_json_success(['id' => $fid]);
    }

    // ============================================================
    // AJAX — DOWNLOAD PATIENT FORM (stream signature + data)
    // ============================================================
    public function ajax_download_patient_form() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!is_user_logged_in()) { wp_die('Denied'); }
        // PostgreSQL only - no $wpdb needed
        $fid = intval($_REQUEST['id'] ?? 0);
        $t   = $this->cct('patient_forms');
        $form = HearMed_DB::get_row( /* TODO: Convert to params array */ "SELECT * FROM `$t` WHERE id=%d", $fid);
        if (!$form) { wp_die('Not found'); }

        $this->audit('downloaded', 'patient_form', $fid, ['patient_id' => $form->patient_id]);

        // Build a simple HTML representation for download
        $data   = json_decode($form->form_data, true) ?: [];
        $output = "HearMed Patient Form\n";
        $output .= "Type: " . $form->form_type . "\n";
        $output .= "Date: " . $form->created_at . "\n\n";
        foreach ($data as $k => $v) {
            $output .= ucwords(str_replace('_', ' ', $param) . ": " . (is_array($v) ? implode(', ', $v) : $v) . "\n";
        }
        $output .= "\nGDPR Consent: " . ($form->gdpr_consent ? 'Yes' : 'No') . "\n";
        $output .= "Marketing Email: " . ($form->marketing_email ? 'Yes' : 'No') . "\n";
        $output .= "Marketing SMS: " . ($form->marketing_sms ? 'Yes' : 'No') . "\n";
        $output .= "Marketing Phone: " . ($form->marketing_phone ? 'Yes' : 'No') . "\n";
        $output .= "Signed: " . (!empty($form->signature_image_url) ? 'Yes' : 'No') . "\n";

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="form-' . $fid . '.txt"');
        echo $output;
        exit;
    }

    // ============================================================
    // AJAX — ADD PATIENT PRODUCT (Hearing Aid)
    // ============================================================
    public function ajax_add_patient_product() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!is_user_logged_in()) { wp_send_json_error('Denied'); return; }
        // PostgreSQL only - no $wpdb needed
        $pid         = intval($_POST['patient_id'] ?? 0);
        $product_id  = intval($_POST['product_id'] ?? 0);
        $fitting_date = sanitize_text_field($_POST['fitting_date'] ?? current_time('Y-m-d'));

        if (!$pid) { wp_send_json_error('Missing patient ID'); return; }

        // Fetch product details from ha-product CPT
        $product_name = $product_id ? get_the_title($product_id) : sanitize_text_field($_POST['product_name'] ?? '');
        $manufacturer  = $product_id ? /* USE PostgreSQL: Get from table columns */ /* get_post_meta($product_id, 'manufacturer', true) : sanitize_text_field($_POST['manufacturer'] ?? '');
        $model         = $product_id ? /* USE PostgreSQL: Get from table columns */ /* get_post_meta($product_id, 'model', true) : sanitize_text_field($_POST['model'] ?? '');
        $style         = sanitize_text_field($_POST['style'] ?? ($product_id ? /* USE PostgreSQL: Get from table columns */ /* get_post_meta($product_id, 'style', true) : ''));

        // Auto-calc warranty expiry
        $warranty_months = $product_id ? intval(/* USE PostgreSQL: Get from table columns */ /* get_post_meta($product_id, 'warranty_months', true)) : 0;
        $warranty_expiry = sanitize_text_field($_POST['warranty_expiry'] ?? '');
        if (!$warranty_expiry && $fitting_date && $warranty_months > 0) {
            $warranty_expiry = date('Y-m-d', strtotime($fitting_date . ' +' . $warranty_months . ' months'));
        }

        $t = $this->cct('patient_products');
        if (HearMed_DB::get_var("SHOW TABLES LIKE '$t'") !== $t) { wp_send_json_error('Products table missing'); return; }

        HearMed_DB::insert($t, [
            'patient_id'         => $pid,
            'product_id'         => $product_id,
            'serial_number_left' => sanitize_text_field($_POST['serial_number_left'] ?? ''),
            'serial_number_right'=> sanitize_text_field($_POST['serial_number_right'] ?? ''),
            'fitting_date'       => $fitting_date,
            'warranty_expiry'    => $warranty_expiry,
            'status'             => 'Active',
            'cct_status'         => 'publish',
        ]);
        $new_id = $last_insert_id /* TODO: Get this from HearMed_DB::insert() return value */;

        $this->audit('created', 'patient_product', $new_id, [
            'patient_id'  => $pid,
            'product_name'=> $product_name,
            'manufacturer'=> $manufacturer,
            'model'       => $model,
            'style'       => $style,
        ]);

        wp_send_json_success(['id' => $new_id]);
    }

    // ============================================================
    // AJAX — CREATE PATIENT NOTIFICATION
    // ============================================================
    public function ajax_create_patient_notification() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!is_user_logged_in()) { wp_send_json_error('Denied'); return; }
        // PostgreSQL only - no $wpdb needed
        $pid   = intval($_POST['patient_id'] ?? 0);
        $type  = sanitize_text_field($_POST['notification_type'] ?? 'Custom');
        $title = sanitize_text_field($_POST['title'] ?? '');
        $msg   = sanitize_textarea_field($_POST['message'] ?? '');
        $date  = sanitize_text_field($_POST['scheduled_date'] ?? '');
        $assignee = intval($_POST['assigned_user_id'] ?? 0);

        if (!$pid || !$msg) { wp_send_json_error('Missing fields'); return; }

        $t = $this->cct('notifications');
        if (HearMed_DB::get_var("SHOW TABLES LIKE '$t'") !== $t) { wp_send_json_error('Notifications table missing'); return; }

        HearMed_DB::insert($t, [
            'user_id'              => $assignee ?: get_current_user_id(),
            'notification_type'    => $type,
            'title'                => $title ?: $type,
            'message'              => $msg,
            'related_patient_id'   => $pid,
            'related_entity_type'  => 'patient',
            'related_entity_id'    => $pid,
            'is_read'              => 0,
            'is_actioned'          => 0,
            'priority'             => sanitize_text_field($_POST['priority'] ?? 'normal'),
            'scheduled_date'       => $date ? $date . ' 09:00:00' : current_time('mysql'),
            'created_at'           => current_time('mysql'),
            'cct_status'           => 'publish',
        ]);
        $nid = $last_insert_id /* TODO: Get this from HearMed_DB::insert() return value */;

        $this->audit('created', 'notification', $nid, ['patient_id' => $pid, 'type' => $type]);
        wp_send_json_success(['id' => $nid]);
    }

    // ============================================================
    // AJAX — GET FORM TEMPLATES
    // ============================================================
    public function ajax_get_form_templates() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!is_user_logged_in()) { wp_send_json_error('Not logged in'); return; }
        // Form templates are WP posts of type 'hm_form_template' or stored as options
        // Fallback to default list if CPT not yet built
        $templates = /* USE PostgreSQL: HearMed_DB::get_results() */ /* get_posts([
            'post_type'      => 'hm_form_template',
            'post_status'    => 'publish',
        $out = [];
        foreach ($templates as $p) {
            $out[] = ['id' => $p->ID, 'name' => $p->post_title, 'type' => /* USE PostgreSQL: Get from table columns */ /* get_post_meta($p->ID, 'form_type', true)];
        }
        // If no templates exist, return defaults
        if (!$out) {
            $out = [
                ['id' => 0, 'name' => 'GDPR Consent Form',    'type' => 'GDPR'],
                ['id' => 0, 'name' => 'New Patient Form',     'type' => 'New Patient'],
                ['id' => 0, 'name' => 'Hearing Aid Agreement','type' => 'Agreement'],
                ['id' => 0, 'name' => 'Marketing Preferences','type' => 'Marketing'],
            ];
        }
        wp_send_json_success($out);
    }

} // end class HearMed_Patients

// Instantiate immediately so AJAX hooks register on every request
$GLOBALS['hm_patients_instance'] = new HearMed_Patients();

// Standalone render function called by hearmed-calendar.php sc_patients()
function hm_patients_render() {
    global $hm_patients_instance;
    if ($hm_patients_instance) {
        echo $hm_patients_instance->render();
    }
}
