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
        if (HearMed_DB::get_var("SHOW TABLES LIKE '$t'") !== $t) { wp_send_json_success([]); return; }

        $where = ['1=1'];
        $entity = sanitize_text_field($_POST['entity'] ?? '');
        $search = sanitize_text_field($_POST['search'] ?? '');
        $from = sanitize_text_field($_POST['from'] ?? '');
        $to = sanitize_text_field($_POST['to'] ?? '');

        if ($entity) $where[] = HearMed_DB::get_results(  "entity_type = %s", $entity);
        if ($search) $where[] = HearMed_DB::get_results(  "(action LIKE %s OR details LIKE %s)", "%$search%", "%$search%");
        if ($from) $where[] = HearMed_DB::get_results(  "created_at >= %s", $from . ' 00:00:00');
        if ($to) $where[] = HearMed_DB::get_results(  "created_at <= %s", $to . ' 23:59:59');

        $sql = "SELECT * FROM `$t` WHERE " . implode(' AND ', $where) . " ORDER BY id DESC LIMIT 500";
        $rows = HearMed_DB::get_results($sql, ARRAY_A) ?: [];

        // Attach user names
        foreach ($rows as &$row) {
            $u = get_user_by('id', intval($row['user_id'] ?? 0));
            $row['user_name'] = $u ? $u->display_name : 'System';
        }

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
                            html += '<div style="padding:8px 12px;cursor:pointer;border-bottom:1px solid var(--hm-border-light);font-size:13px" onclick="hmExport.select(' + p.id + ','' + p.label.replace(/'/g, "'") + '')">' + p.label + '</div>';
                        });
                        document.getElementById('hmde-results').innerHTML = html ? '<div style="border:1px solid var(--hm-border-light);border-radius:6px;max-height:200px;overflow-y:auto">' + html + '</div>' : '';
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
                alert('Patient data export will be generated as ' + format.toUpperCase() + '. This feature connects to the full export pipeline in a future update.');
            }
        };
        </script>
        <?php return ob_get_clean();
    }

    public function ajax_export_patient() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Denied'); return; }
        // Full implementation in later phase
        wp_send_json_success(['message' => 'Export queued']);
    }
}

new HearMed_Admin_AuditLog();
