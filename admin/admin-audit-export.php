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
        add_action('wp_ajax_hm_admin_export_all_patients', [$this, 'ajax_export_all_patients']);
    }

    public function render_audit() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        ob_start(); ?>
        <div class="hm-admin">
            <div style="margin-bottom:16px;"><a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-btn">&larr; Back</a></div>
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
        <style>
        .hm-export-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:start;}
        @media(max-width:800px){.hm-export-grid{grid-template-columns:1fr;}}
        .hm-export-checks{display:flex;flex-wrap:wrap;gap:4px 8px;}
        .hm-export-checks label{display:inline-flex;align-items:center;gap:4px;font-size:12px;cursor:pointer;padding:3px 0;}
        .hm-export-checks label input{accent-color:var(--hm-primary,#0BB4C4);width:14px;height:14px;margin:0;}
        </style>
        <div class="hm-admin">
            <div style="margin-bottom:16px;"><a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-btn">&larr; Back</a></div>
            <div class="hm-admin-hd"><h2>Data Export</h2></div>

            <div class="hm-export-grid">
            <!-- ===== SECTION 1: Single Patient Export ===== -->
            <div class="hm-settings-panel">
                <h3 style="font-size:14px;margin-bottom:6px;">Single Patient Export</h3>
                <p style="color:var(--hm-text-light);font-size:12px;margin-bottom:14px;">Export all data for a specific patient (GDPR Right of Access / Right to Portability).</p>

                <div class="hm-form-group">
                    <label>Patient Search</label>
                    <input type="text" id="hmde-search" placeholder="Type patient name or H-number..." oninput="hmExport.search(this.value)">
                    <div id="hmde-results" style="margin-top:6px"></div>
                </div>

                <div id="hmde-selected" style="display:none;margin-top:12px;">
                    <div class="hm-form-group">
                        <strong id="hmde-patient-name" style="font-size:13px;"></strong>
                        <input type="hidden" id="hmde-patient-id">
                    </div>

                    <div class="hm-form-group">
                        <label style="font-weight:500;margin-bottom:6px;display:block;font-size:11px;color:#475569;">Data Sections to Export</label>
                        <div class="hm-export-checks">
                            <label><input type="checkbox" class="hmde-section" value="patient" checked> Patient Details</label>
                            <label><input type="checkbox" class="hmde-section" value="appointments" checked> Appointments</label>
                            <label><input type="checkbox" class="hmde-section" value="orders" checked> Orders</label>
                            <label><input type="checkbox" class="hmde-section" value="invoices" checked> Invoices</label>
                            <label><input type="checkbox" class="hmde-section" value="payments" checked> Payments</label>
                            <label><input type="checkbox" class="hmde-section" value="notes" checked> Notes</label>
                            <label><input type="checkbox" class="hmde-section" value="forms" checked> Forms</label>
                            <label><input type="checkbox" class="hmde-section" value="devices" checked> Devices</label>
                        </div>
                    </div>

                    <div style="display:flex;gap:8px;margin-top:10px;">
                        <button class="hm-btn hm-btn-teal hm-btn-sm" onclick="hmExport.exportData('json')">Export JSON</button>
                        <button class="hm-btn hm-btn-sm" onclick="hmExport.exportData('csv')">Export CSV</button>
                    </div>
                </div>
            </div>

            <!-- ===== SECTION 2: All Patients Bulk Export ===== -->
            <div class="hm-settings-panel">
                <h3 style="font-size:14px;margin-bottom:6px;">All Patients Export</h3>
                <p style="color:var(--hm-text-light);font-size:12px;margin-bottom:14px;">Bulk export data for ALL patients. Large exports may take a moment.</p>

                <div class="hm-form-group">
                    <label style="font-weight:500;margin-bottom:6px;display:block;font-size:11px;color:#475569;">Modules to Include</label>
                    <div class="hm-export-checks">
                        <label><input type="checkbox" class="hmde-bulk-section" value="patient" checked> Patient Details</label>
                        <label><input type="checkbox" class="hmde-bulk-section" value="appointments"> Appointments</label>
                        <label><input type="checkbox" class="hmde-bulk-section" value="orders"> Orders</label>
                        <label><input type="checkbox" class="hmde-bulk-section" value="invoices"> Invoices</label>
                        <label><input type="checkbox" class="hmde-bulk-section" value="payments"> Payments</label>
                        <label><input type="checkbox" class="hmde-bulk-section" value="notes"> Notes</label>
                        <label><input type="checkbox" class="hmde-bulk-section" value="forms"> Forms</label>
                        <label><input type="checkbox" class="hmde-bulk-section" value="devices"> Devices</label>
                    </div>
                </div>

                <div class="hm-form-group" style="margin-top:6px;">
                    <label style="font-weight:500;margin-bottom:6px;display:block;font-size:11px;color:#475569;">Export Format</label>
                    <div class="hm-export-checks">
                        <label><input type="radio" name="hmde-bulk-fmt" value="csv" checked> CSV (one file per module)</label>
                        <label><input type="radio" name="hmde-bulk-fmt" value="json"> JSON</label>
                    </div>
                </div>

                <div style="display:flex;gap:8px;margin-top:12px;align-items:center;">
                    <button class="hm-btn hm-btn-teal hm-btn-sm" onclick="hmExport.bulkExport()" id="hmde-bulk-btn">Export All Patients</button>
                    <span id="hmde-bulk-status" style="font-size:12px;color:var(--hm-text-light);"></span>
                </div>
            </div>
            </div><!-- /hm-export-grid -->

            <!-- ===== SECTION 3: Anonymisation ===== -->
            <div class="hm-settings-panel" style="margin-top:16px;max-width:calc(50% - 8px);">
                <h3 style="font-size:14px;margin-bottom:6px;">Patient Anonymisation (Right to Erasure)</h3>
                <p style="color:var(--hm-text-light);font-size:12px;margin-bottom:10px;">Anonymises personal data while preserving financial and appointment records for Revenue/HSE compliance. This action cannot be undone.</p>
                <button class="hm-btn hm-btn-red hm-btn-sm" disabled>Anonymise Patient (Select patient first)</button>
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
                var sections = [];
                document.querySelectorAll('.hmde-section:checked').forEach(function(cb) { sections.push(cb.value); });
                if (!sections.length) { alert('Select at least one data section.'); return; }
                jQuery.post(HM.ajax_url, { action:'hm_admin_export_patient', nonce:HM.nonce, patient_id:pid, format:format, sections:JSON.stringify(sections) }, function(r) {
                    if (!r.success) { alert(r.data || 'Export failed'); return; }
                    hmExport.download(r.data.payload, r.data.filename, r.data.mime);
                });
            },
            bulkExport: function() {
                var sections = [];
                document.querySelectorAll('.hmde-bulk-section:checked').forEach(function(cb) { sections.push(cb.value); });
                if (!sections.length) { alert('Select at least one module.'); return; }
                var format = document.querySelector('input[name="hmde-bulk-fmt"]:checked').value;
                var btn = document.getElementById('hmde-bulk-btn');
                var status = document.getElementById('hmde-bulk-status');
                btn.disabled = true; btn.textContent = 'Exporting...';
                status.textContent = 'This may take a moment for large datasets...';
                jQuery.post(HM.ajax_url, { action:'hm_admin_export_all_patients', nonce:HM.nonce, format:format, sections:JSON.stringify(sections) }, function(r) {
                    btn.disabled = false; btn.textContent = 'Export All Patients';
                    if (!r.success) { status.textContent = r.data || 'Export failed'; return; }
                    status.textContent = 'Export complete — ' + (r.data.count || 0) + ' patients exported.';
                    hmExport.download(r.data.payload, r.data.filename, r.data.mime);
                }).fail(function(){ btn.disabled = false; btn.textContent = 'Export All Patients'; status.textContent = 'Request failed.'; });
            },
            download: function(payload, filename, mime) {
                var blob = new Blob([payload || ''], { type: mime || 'application/octet-stream' });
                var url = window.URL.createObjectURL(blob);
                var a = document.createElement('a');
                a.href = url; a.download = filename || 'export';
                document.body.appendChild(a); a.click(); a.remove();
                window.URL.revokeObjectURL(url);
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

        $sections = json_decode(stripslashes($_POST['sections'] ?? '[]'), true);
        if (!is_array($sections) || empty($sections)) {
            $sections = ['patient','appointments','orders','invoices','payments','notes','forms','devices'];
        }

        $data = $this->gather_patient_data($patient_id, $patient, $sections);

        $exported_by = get_current_user_id();
        HearMed_DB::insert('hearmed_admin.gdpr_exports', [
            'patient_id' => $patient_id,
            'exported_by' => $exported_by,
            'export_type' => $format,
            'exported_at' => current_time('mysql'),
            'file_url' => null,
        ]);

        if ($format === 'csv') {
            $csv = $this->data_to_csv($data);
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

    public function ajax_export_all_patients() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }

        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $sections = json_decode(stripslashes($_POST['sections'] ?? '[]'), true);
        if (!is_array($sections) || empty($sections)) {
            $sections = ['patient'];
        }

        $patients = HearMed_DB::get_results(
            "SELECT * FROM hearmed_core.patients ORDER BY id"
        ) ?: [];

        if (empty($patients)) { wp_send_json_error('No patients found'); return; }

        $all_data = [];
        foreach ($patients as $p) {
            $all_data[] = $this->gather_patient_data((int) $p->id, $p, $sections);
        }

        if ($format === 'csv') {
            $csv = $this->bulk_data_to_csv($all_data, $sections);
            wp_send_json_success([
                'payload' => $csv,
                'mime' => 'text/csv',
                'filename' => 'all-patients-export-' . date('Y-m-d') . '.csv',
                'count' => count($patients),
            ]);
        }

        wp_send_json_success([
            'payload' => wp_json_encode($all_data, JSON_PRETTY_PRINT),
            'mime' => 'application/json',
            'filename' => 'all-patients-export-' . date('Y-m-d') . '.json',
            'count' => count($patients),
        ]);
    }

    private function gather_patient_data($patient_id, $patient, $sections) {
        $data = [];
        if (in_array('patient', $sections))      $data['patient']      = (array) $patient;
        if (in_array('appointments', $sections))  $data['appointments'] = HearMed_DB::get_results("SELECT * FROM hearmed_core.appointments WHERE patient_id = $1 ORDER BY appointment_date DESC", [$patient_id]) ?: [];
        if (in_array('orders', $sections))        $data['orders']       = HearMed_DB::get_results("SELECT * FROM hearmed_core.orders WHERE patient_id = $1 ORDER BY created_at DESC", [$patient_id]) ?: [];
        if (in_array('invoices', $sections))      $data['invoices']     = HearMed_DB::get_results("SELECT * FROM hearmed_core.invoices WHERE patient_id = $1 ORDER BY created_at DESC", [$patient_id]) ?: [];
        if (in_array('payments', $sections))      $data['payments']     = HearMed_DB::get_results("SELECT * FROM hearmed_core.payments WHERE patient_id = $1 ORDER BY created_at DESC", [$patient_id]) ?: [];
        if (in_array('notes', $sections))         $data['notes']        = HearMed_DB::get_results("SELECT * FROM hearmed_core.patient_notes WHERE patient_id = $1 ORDER BY created_at DESC", [$patient_id]) ?: [];
        if (in_array('forms', $sections))         $data['forms']        = HearMed_DB::get_results("SELECT * FROM hearmed_core.patient_forms WHERE patient_id = $1 ORDER BY created_at DESC", [$patient_id]) ?: [];
        if (in_array('devices', $sections))       $data['devices']      = HearMed_DB::get_results("SELECT * FROM hearmed_core.patient_devices WHERE patient_id = $1 ORDER BY created_at DESC", [$patient_id]) ?: [];
        return $data;
    }

    private function data_to_csv($data) {
        $out = fopen('php://temp', 'r+');
        foreach ($data as $section => $rows) {
            fputcsv($out, ["=== {$section} ==="]);
            if ($section === 'patient') {
                $row = (array) $rows;
                fputcsv($out, array_keys($row));
                fputcsv($out, array_map(function($v) {
                    if (is_bool($v)) return $v ? 'true' : 'false';
                    if ($v === null) return '';
                    return (string) $v;
                }, array_values($row)));
            } else {
                $arr = array_map(function($r) { return (array) $r; }, (array) $rows);
                if (!empty($arr)) {
                    fputcsv($out, array_keys($arr[0]));
                    foreach ($arr as $row) {
                        fputcsv($out, array_map(function($v) {
                            if (is_bool($v)) return $v ? 'true' : 'false';
                            if ($v === null) return '';
                            return (string) $v;
                        }, array_values($row)));
                    }
                } else {
                    fputcsv($out, ['(no data)']);
                }
            }
            fputcsv($out, []);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $csv;
    }

    private function bulk_data_to_csv($all_data, $sections) {
        $out = fopen('php://temp', 'r+');
        // For bulk CSV, write each section as a flat table
        foreach ($sections as $section) {
            fputcsv($out, ["=== {$section} ==="]);
            $all_rows = [];
            foreach ($all_data as $pdata) {
                if (!isset($pdata[$section])) continue;
                if ($section === 'patient') {
                    $all_rows[] = (array) $pdata[$section];
                } else {
                    foreach ((array) $pdata[$section] as $r) {
                        $all_rows[] = (array) $r;
                    }
                }
            }
            if (!empty($all_rows)) {
                fputcsv($out, array_keys($all_rows[0]));
                foreach ($all_rows as $row) {
                    fputcsv($out, array_map(function($v) {
                        if (is_bool($v)) return $v ? 'true' : 'false';
                        if ($v === null) return '';
                        return (string) $v;
                    }, array_values($row)));
                }
            } else {
                fputcsv($out, ['(no data)']);
            }
            fputcsv($out, []);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        return $csv;
    }
}

new HearMed_Admin_AuditLog();
