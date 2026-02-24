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
 * HearMed Admin — Audit Log & Data Export
 * Shortcodes: [hearmed_audit_log], [hearmed_data_export]
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_AuditLog {

    public function __construct() {
        add_shortcode('hearmed_audit_log', [$this, 'render_audit']);
        add_shortcode('hearmed_data_export', [$this, 'render_export']);
        add_action('wp_ajax_hm_admin_get_audit_log', [$this, 'ajax_get_log']);
        add_action('wp_ajax_hm_admin_export_patient', [$this, 'ajax_export_patient']);
    }

    public function render_audit() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        ob_start(); ?>
        <div class="hm-admin">
            <div class="hm-admin-hd">
                <h2>Audit Log</h2>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="text" id="hmal-search" placeholder="Search actions..." class="hm-search-input" style="width:180px">
                    <select id="hmal-entity" class="hm-filter-select">
                        <option value="">All Entities</option>
                        <option value="appointment">Appointments</option>
                        <option value="order">Orders</option>
                        <option value="invoice">Invoices</option>
                        <option value="patient">Patients</option>
                        <option value="payment">Payments</option>
                        <option value="user">Users</option>
                        <option value="clinic">Clinics</option>
                        <option value="product">Products</option>
                    </select>
                    <input type="date" id="hmal-from" class="hm-filter-select">
                    <input type="date" id="hmal-to" class="hm-filter-select">
                    <button class="hm-btn" onclick="hmAudit.load()">Filter</button>
                </div>
            </div>

            <div id="hmal-results">
                <p style="text-align:center;color:var(--hm-text-light);padding:40px;">Click "Filter" or adjust filters to load audit entries.</p>
            </div>
        </div>


        <script>
        var hmAudit = {
            load: function() {
                var el = document.getElementById('hmal-results');
                el.innerHTML = '<p style="text-align:center;padding:40px;color:var(--hm-text-light)">Loading...</p>';
                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_get_audit_log',
                    nonce: HM.nonce,
                    search: document.getElementById('hmal-search').value,
                    entity: document.getElementById('hmal-entity').value,
                    from: document.getElementById('hmal-from').value,
                    to: document.getElementById('hmal-to').value
                }, function(r) {
                    if (!r.success) { el.innerHTML = '<p style="text-align:center;padding:40px;color:var(--hm-red)">Error loading log.</p>'; return; }
                    var rows = r.data;
                    if (!rows.length) { el.innerHTML = '<p style="text-align:center;padding:40px;color:var(--hm-text-light)">No entries found.</p>'; return; }
                    var html = '<div class="hm-audit-wrap">';
                    rows.forEach(function(row) {
                        html += '<div class="hm-audit-row">' +
                            '<span class="hm-audit-time">' + (row.created_at || '') + '</span>' +
                            '<span class="hm-audit-user">' + (row.user_name || 'System') + '</span>' +
                            '<span class="hm-audit-action">' + (row.action || '') + '</span>' +
                            '<span class="hm-audit-detail">' + (row.entity_type || '') + ' #' + (row.entity_id || '') +
                            (row.details ? ' — ' + row.details.substring(0, 200) : '') + '</span>' +
                        '</div>';
                    });
                    html += '</div><p style="padding:8px 16px;font-size:12px;color:var(--hm-text-light)">' + rows.length + ' entries (max 500)</p>';
                    el.innerHTML = html;
                });
            }
        };
        </script>
        <?php return ob_get_clean();
    }

    public function ajax_get_log() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }
        // PostgreSQL only - no $wpdb needed
        $t = HearMed_DB::table('audit_log');
        if (HearMed_DB::get_var("SELECT to_regclass($1)", [$t]) === null) { wp_send_json_success([]); return; }

        $where = [];
        $params = [];
        $i = 1;
        $entity = sanitize_text_field($_POST['entity'] ?? '');
        $search = sanitize_text_field($_POST['search'] ?? '');
        $from = sanitize_text_field($_POST['from'] ?? '');
        $to = sanitize_text_field($_POST['to'] ?? '');

        if ($entity) { $where[] = "entity_type = $$i"; $params[] = $entity; $i++; }
        if ($search) {
            $where[] = "(action ILIKE $$i OR details::text ILIKE $$i)";
            $params[] = '%' . $search . '%';
            $i++;
        }
        if ($from) { $where[] = "created_at >= $$i"; $params[] = $from . ' 00:00:00'; $i++; }
        if ($to) { $where[] = "created_at <= $$i"; $params[] = $to . ' 23:59:59'; $i++; }

        $sql = "SELECT * FROM {$t}";
        if (!empty($where)) {
            $sql .= " WHERE " . implode(' AND ', $where);
        }
        $sql .= " ORDER BY id DESC LIMIT 500";
        $rows = HearMed_DB::get_results($sql, $params) ?: [];

        // Attach user names
        $out = [];
        foreach ($rows as $row) {
            $r = (array) $row;
            $u = get_user_by('id', intval($r['user_id'] ?? 0));
            $r['user_name'] = $u ? $u->display_name : 'System';
            $out[] = $r;
        }
        $rows = $out;

        wp_send_json_success($rows);
    }

    public function render_export() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        ob_start(); ?>
        <div class="hm-admin">
            <div class="hm-admin-hd"><h2>Data Export</h2></div>

            <div class="hm-settings-panel">
                <p style="color:var(--hm-text-light);margin-bottom:20px;">Export all data for a specific patient (GDPR Right of Access / Right to Portability). Search for a patient and click Export to generate a full data package.</p>

                <div class="hm-form-group">
                    <label>Patient Search</label>
                    <input type="text" id="hmde-search" placeholder="Type patient name or H-number..." oninput="hmExport.search(this.value)">
                    <div id="hmde-results" style="margin-top:8px"></div>
                </div>

                <div id="hmde-selected" style="display:none;margin-top:16px;">
                    <div class="hm-form-group">
                        <strong id="hmde-patient-name"></strong>
                        <input type="hidden" id="hmde-patient-id">
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button class="hm-btn hm-btn-teal" onclick="hmExport.exportData('json')">Export as JSON</button>
                        <button class="hm-btn" onclick="hmExport.exportData('csv')">Export as CSV</button>
                    </div>
                </div>

                <hr style="margin:30px 0;border:none;border-top:1px solid var(--hm-border-light);">

                <h3 style="font-size:15px;margin-bottom:12px;">Patient Anonymisation (Right to Erasure)</h3>
                <p style="color:var(--hm-text-light);font-size:13px;margin-bottom:12px;">Anonymises personal data while preserving financial and appointment records for Revenue/HSE compliance. This action cannot be undone.</p>
                <button class="hm-btn hm-btn-red" disabled>Anonymise Patient (Select patient first)</button>
            </div>
        </div>


        <script>
        var hmExport = {
            timer: null,
            search: function(q) {
                clearTimeout(this.timer);
                if (q.length < 2) { document.getElementById('hmde-results').innerHTML = ''; return; }
                this.timer = setTimeout(function() {
                    jQuery.post(HM.ajax_url, { action:'hm_search_patients', nonce:HM.nonce, q:q }, function(r) {
                        if (!r.success) return;
                        var html = '';
                        (r.data || []).forEach(function(p) {
                            var safe = (p.label || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                            var encoded = encodeURIComponent(p.label || '');
                            html += '<div class="hm-export-item" data-id="' + p.id + '" data-label="' + encoded + '" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid var(--hm-border-light);font-size:13px">' + safe + '</div>';
                        });
                        var container = html ? '<div style="border:1px solid var(--hm-border-light);border-radius:6px;max-height:200px;overflow-y:auto">' + html + '</div>' : '';
                        document.getElementById('hmde-results').innerHTML = container;
                        document.querySelectorAll('.hm-export-item').forEach(function(el){
                            el.onclick = function(){
                                hmExport.select(el.getAttribute('data-id'), decodeURIComponent(el.getAttribute('data-label') || ''));
                            };
                        });
                    });
                }, 300);
            },
            select: function(id, name) {
                document.getElementById('hmde-patient-id').value = id;
                document.getElementById('hmde-patient-name').textContent = name;
                document.getElementById('hmde-selected').style.display = 'block';
                document.getElementById('hmde-results').innerHTML = '';
                document.getElementById('hmde-search').value = name;
            },
            exportData: function(format) {
                var pid = document.getElementById('hmde-patient-id').value;
                if (!pid) { alert('Select a patient first.'); return; }
                jQuery.post(HM.ajax_url, { action:'hm_admin_export_patient', nonce:HM.nonce, patient_id:pid, format:format }, function(r) {
                    if (!r.success) { alert(r.data || 'Export failed'); return; }
                    var filename = r.data.filename || ('patient-export.' + format);
                    var mime = r.data.mime || 'application/octet-stream';
                    var blob = new Blob([r.data.payload || ''], { type: mime });
                    var url = window.URL.createObjectURL(blob);
                    var a = document.createElement('a');
                    a.href = url;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    window.URL.revokeObjectURL(url);
                });
            }
        };
        </script>
        <?php return ob_get_clean();
    }

    public function ajax_export_patient() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }
        $patient_id = intval($_POST['patient_id'] ?? 0);
        $format = sanitize_text_field($_POST['format'] ?? 'json');
        if (!$patient_id) { wp_send_json_error('Invalid patient'); return; }

        $patient = HearMed_DB::get_row(
            "SELECT * FROM hearmed_core.patients WHERE id = $1",
            [$patient_id]
        );
        if (!$patient) { wp_send_json_error('Patient not found'); return; }

        $data = [
            'patient' => (array) $patient,
            'appointments' => HearMed_DB::get_results("SELECT * FROM hearmed_core.appointments WHERE patient_id = $1 ORDER BY appointment_date DESC", [$patient_id]) ?: [],
            'orders' => HearMed_DB::get_results("SELECT * FROM hearmed_core.orders WHERE patient_id = $1 ORDER BY created_at DESC", [$patient_id]) ?: [],
            'invoices' => HearMed_DB::get_results("SELECT * FROM hearmed_core.invoices WHERE patient_id = $1 ORDER BY created_at DESC", [$patient_id]) ?: [],
            'payments' => HearMed_DB::get_results("SELECT * FROM hearmed_core.payments WHERE patient_id = $1 ORDER BY created_at DESC", [$patient_id]) ?: [],
            'notes' => HearMed_DB::get_results("SELECT * FROM hearmed_core.patient_notes WHERE patient_id = $1 ORDER BY created_at DESC", [$patient_id]) ?: [],
            'forms' => HearMed_DB::get_results("SELECT * FROM hearmed_core.patient_forms WHERE patient_id = $1 ORDER BY created_at DESC", [$patient_id]) ?: [],
            'devices' => HearMed_DB::get_results("SELECT * FROM hearmed_core.patient_devices WHERE patient_id = $1 ORDER BY created_at DESC", [$patient_id]) ?: [],
        ];

        $exported_by = get_current_user_id();
        HearMed_DB::insert('hearmed_admin.gdpr_exports', [
            'patient_id' => $patient_id,
            'exported_by' => $exported_by,
            'export_type' => $format,
            'exported_at' => current_time('mysql'),
            'file_url' => null,
        ]);

        if ($format === 'csv') {
            $csv = $this->patient_to_csv((array) $patient);
            wp_send_json_success([
                'payload' => $csv,
                'mime' => 'text/csv',
                'filename' => 'patient-' . $patient_id . '.csv',
            ]);
        }

        wp_send_json_success([
            'payload' => wp_json_encode($data, JSON_PRETTY_PRINT),
            'mime' => 'application/json',
            'filename' => 'patient-' . $patient_id . '.json',
        ]);
    }

    private function patient_to_csv($patient) {
        $headers = array_keys($patient);
        $values = array_map(function($v) {
            if (is_bool($v)) return $v ? 'true' : 'false';
            if ($v === null) return '';
            return (string) $v;
        }, array_values($patient));

        $out = fopen('php://temp', 'r+');
        fputcsv($out, $headers);
        fputcsv($out, $values);
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $csv;
    }
}

new HearMed_Admin_AuditLog();
