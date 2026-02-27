<?php
/**
 * Repairs overview — tracking, status, manufacturer returns
 * 
 * Shortcode: [hearmed_repairs]
 * Page: /repairs/
 */
if (!defined("ABSPATH")) exit;

//Standalone render function called by router
function hm_repairs_render() {
    if (!is_user_logged_in()) return;
    ?>
    <div id="hm-repairs-app" class="hm-page">
        <div class="hm-page-header">
            <h1 class="hm-page-title">Repairs</h1>
        </div>

        <div class="hm-repairs-toolbar">
            <div id="hm-repairs-stats" class="hm-stats"></div>
            <div class="hm-repairs-filters">
                <input type="text" id="hm-repair-search" class="hm-tf-search" placeholder="Search patient or HMREP…">
                <select id="hm-repair-filter-status" class="hm-tf-perpage">
                    <option value="">All statuses</option>
                    <option value="Booked">Booked</option>
                    <option value="Sent">Sent</option>
                    <option value="Received">Received</option>
                </select>
                <select id="hm-repair-filter-clinic" class="hm-tf-perpage">
                    <option value="">All clinics</option>
                </select>
            </div>
        </div>

        <div class="hm-card">
            <div id="hm-repairs-table">
                <div class="hm-empty"><div class="hm-empty-text">Loading repairs…</div></div>
            </div>
        </div>
    </div>
    <?php
}

class HearMed_Repairs {

    public static function init() {
        add_shortcode("hearmed_repairs", [__CLASS__, "render"]);
        add_action("wp_ajax_hm_get_all_repairs", [__CLASS__, "ajax_get_all"]);
        add_action("wp_ajax_hm_get_repair_docket", [__CLASS__, "ajax_repair_docket"]);
    }

    public static function render($atts = []): string {
        if (!is_user_logged_in()) return "";
        
        ob_start();
        hm_repairs_render();
        return ob_get_clean();
    }

    /**
     * Get ALL repairs across all patients (for standalone page)
     */
    public static function ajax_get_all() {
        check_ajax_referer('hm_nonce', 'nonce');
        
        $db = HearMed_DB::instance();

        // Diagnostic: verify DB connection and table existence
        $diag = [];
        $conn_test = HearMed_DB::get_var("SELECT 1");
        $diag['db_connected'] = $conn_test ? true : false;
        
        $count = HearMed_DB::get_var("SELECT COUNT(*) FROM hearmed_core.repairs");
        $diag['total_repairs_in_table'] = $count;
        $diag['count_error'] = HearMed_DB::last_error() ?: null;

        // If DB is not connected or table is empty, return early with diagnostics
        if (!$conn_test) {
            wp_send_json_success(['_diag' => $diag, '_error' => 'Database connection failed']);
            return;
        }

        // Full query with JOINs for patient, clinic, dispenser, device, manufacturer
        $rows = $db->get_results(
            "SELECT r.id, r.repair_number, r.serial_number, r.date_booked, r.date_sent,
                    r.date_received, r.repair_status, r.warranty_status, r.repair_notes,
                    r.repair_reason, r.under_warranty, r.sent_to,
                    r.patient_id, r.clinic_id AS repair_clinic_id,
                    COALESCE(pr.product_name, 'Unknown') AS product_name,
                    COALESCE(m.name, '') AS manufacturer_name,
                    CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                    p.patient_number,
                    p.assigned_clinic_id AS patient_clinic_id,
                    COALESCE(c.clinic_name, '') AS clinic_name,
                    COALESCE(CONCAT(s.first_name, ' ', s.last_name), '') AS dispenser_name
             FROM hearmed_core.repairs r
             LEFT JOIN hearmed_core.patient_devices pd ON pd.id = r.patient_device_id
             LEFT JOIN hearmed_reference.products pr ON pr.id = COALESCE(r.product_id, pd.product_id)
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = COALESCE(r.manufacturer_id, pr.manufacturer_id)
             LEFT JOIN hearmed_core.patients p ON p.id = r.patient_id
             LEFT JOIN hearmed_reference.clinics c ON c.id = COALESCE(r.clinic_id, p.assigned_clinic_id)
             LEFT JOIN hearmed_reference.staff s ON s.id = COALESCE(r.staff_id, p.assigned_dispenser_id)
             ORDER BY
                CASE r.repair_status
                    WHEN 'Booked' THEN 1
                    WHEN 'Sent' THEN 2
                    ELSE 3
                END,
                r.date_booked DESC"
        );

        // If failed (likely missing columns), try minimal query
        $last_err = HearMed_DB::last_error();
        $diag['full_query_error'] = $last_err ?: null;
        $diag['full_query_row_count'] = is_array($rows) ? count($rows) : 'not_array';
        if (empty($rows) && $last_err) {
            error_log('[HM Repairs] Full query failed: ' . $last_err . ' — trying minimal');
            $rows = $db->get_results(
                "SELECT r.id, r.serial_number, r.date_booked, r.date_sent,
                        r.date_received, r.repair_status, r.warranty_status, r.repair_notes,
                        r.patient_id,
                        COALESCE(pr.product_name, 'Unknown') AS product_name,
                        COALESCE(m.name, '') AS manufacturer_name,
                        CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                        p.patient_number,
                        p.assigned_clinic_id AS patient_clinic_id,
                        COALESCE(c.clinic_name, '') AS clinic_name
                 FROM hearmed_core.repairs r
                 LEFT JOIN hearmed_core.patient_devices pd ON pd.id = r.patient_device_id
                 LEFT JOIN hearmed_reference.products pr ON pr.id = COALESCE(r.product_id, pd.product_id)
                 LEFT JOIN hearmed_reference.manufacturers m ON m.id = COALESCE(r.manufacturer_id, pr.manufacturer_id)
                 LEFT JOIN hearmed_core.patients p ON p.id = r.patient_id
                 LEFT JOIN hearmed_reference.clinics c ON c.id = p.assigned_clinic_id
                 ORDER BY
                    CASE r.repair_status
                        WHEN 'Booked' THEN 1
                        WHEN 'Sent' THEN 2
                        ELSE 3
                    END,
                    r.date_booked DESC"
            );
        }

        $out = [];
        if ($rows) {
            foreach ($rows as $r) {
                $days_open = 0;
                if ($r->date_booked && !$r->date_received) {
                    $booked = new \DateTime($r->date_booked);
                    $now    = new \DateTime();
                    $days_open = (int) $booked->diff($now)->days;
                }
                $clinic_id = $r->repair_clinic_id ?? $r->patient_clinic_id ?? null;
                $out[] = [
                    '_ID'              => (int) $r->id,
                    'repair_number'    => $r->repair_number ?? '',
                    'product_name'     => $r->product_name ?? 'Unknown',
                    'manufacturer_name'=> $r->manufacturer_name ?? '',
                    'serial_number'    => $r->serial_number ?? '',
                    'date_booked'      => $r->date_booked,
                    'date_sent'        => $r->date_sent ?? null,
                    'status'           => $r->repair_status ?: 'Booked',
                    'warranty_status'  => $r->warranty_status ?? '',
                    'under_warranty'   => isset($r->under_warranty) ? hm_pg_bool($r->under_warranty) : false,
                    'repair_reason'    => $r->repair_reason ?? '',
                    'repair_notes'     => $r->repair_notes ?? '',
                    'sent_to'          => $r->sent_to ?? '',
                    'patient_name'     => $r->patient_name ?? 'Unknown',
                    'patient_id'       => (int) $r->patient_id,
                    'patient_number'   => $r->patient_number ?? '',
                    'clinic_id'        => $clinic_id ? (int) $clinic_id : null,
                    'clinic_name'      => $r->clinic_name ?? '',
                    'dispenser_name'   => $r->dispenser_name ?? '',
                    'days_open'        => $days_open,
                ];
            }
        }

        $diag['output_count'] = count($out);
        error_log('[HM Repairs] ajax_get_all diagnostic: ' . json_encode($diag));

        // Include diagnostics in response for debugging (temporary)
        wp_send_json_success(['repairs' => $out, '_diag' => $diag]);
    }

    /**
     * Generate a printable repair docket (HTML print page)
     */
    public static function ajax_repair_docket() {
        check_ajax_referer('hm_nonce', 'nonce');
        $rid = intval($_POST['repair_id'] ?? $_GET['repair_id'] ?? 0);
        if (!$rid) wp_send_json_error('Missing repair ID');

        $db = HearMed_DB::instance();
        $r = $db->get_row(
            "SELECT r.id, r.repair_number, r.serial_number, r.date_booked,
                    r.date_sent, r.date_received,
                    r.repair_status, r.warranty_status, r.under_warranty,
                    r.repair_reason, r.repair_notes, r.sent_to,
                    COALESCE(pr.product_name, 'Unknown') AS product_name,
                    COALESCE(m.name, '') AS manufacturer_name,
                    m.warranty_terms,
                    CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                    p.date_of_birth, p.phone, p.mobile,
                    p.address_line1 AS patient_address,
                    c.clinic_name,
                    CONCAT_WS(', ', c.address_line1, c.city, c.county, c.postcode) AS clinic_address,
                    CONCAT(s.first_name, ' ', s.last_name) AS dispenser_name
             FROM hearmed_core.repairs r
             LEFT JOIN hearmed_core.patient_devices pd ON pd.id = r.patient_device_id
             LEFT JOIN hearmed_reference.products pr ON pr.id = COALESCE(r.product_id, pd.product_id)
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = COALESCE(r.manufacturer_id, pr.manufacturer_id)
             LEFT JOIN hearmed_core.patients p ON p.id = r.patient_id
             LEFT JOIN hearmed_reference.clinics c ON c.id = p.assigned_clinic_id
             LEFT JOIN hearmed_reference.staff s ON s.id = COALESCE(r.staff_id, p.assigned_dispenser_id)
             WHERE r.id = \$1",
            [$rid]
        );

        if (!$r) wp_send_json_error('Repair not found');

        // Use template engine
        $tpl_html = HearMed_Print_Templates::render('repair', $r);
        wp_send_json_success(['html' => $tpl_html]);
    }
}

HearMed_Repairs::init();
