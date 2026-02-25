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
    <div id="hm-repairs-app" class="hm-content">
        <div class="hm-page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <h1 class="hm-page-title">Repairs</h1>
            <div style="display:flex;gap:10px;align-items:center;">
                <select id="hm-repair-filter-status" class="hm-dd" style="width:auto;min-width:140px;">
                    <option value="">All statuses</option>
                    <option value="Booked">Booked</option>
                    <option value="Sent">Sent</option>
                    <option value="Received">Received</option>
                </select>
                <select id="hm-repair-filter-clinic" class="hm-dd" style="width:auto;min-width:160px;">
                    <option value="">All clinics</option>
                </select>
                <input type="text" id="hm-repair-search" class="hm-inp" style="width:220px;" placeholder="Search patient or HMREP…">
            </div>
        </div>
        <div id="hm-repairs-stats" style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;"></div>
        <div id="hm-repairs-table" style="margin-top:12px;">
            <div class="hm-empty"><div class="hm-empty-text">Loading repairs…</div></div>
        </div>
    </div>
    <script>
    (function($){
        var _hm=window._hm||{ajax:'<?php echo admin_url("admin-ajax.php"); ?>',nonce:'<?php echo wp_create_nonce("hm_nonce"); ?>'};
        var allRepairs=[];

        function esc(s){return $('<span>').text(s||'').html();}
        function fmtDate(d){if(!d||d==='null')return '—';var p=d.split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:d;}
        function euro(v){return '€'+parseFloat(v||0).toFixed(2);}

        function loadClinics(){
            $.post(_hm.ajax,{action:'hm_get_clinics',nonce:_hm.nonce},function(r){
                if(r&&r.success&&r.data){
                    r.data.forEach(function(c){$('#hm-repair-filter-clinic').append('<option value="'+(c.id||c._ID)+'">'+esc(c.name)+'</option>');});
                }
            });
        }

        function loadRepairs(){
            $.post(_hm.ajax,{action:'hm_get_all_repairs',nonce:_hm.nonce},function(r){
                console.log('[HM Repairs] response:', r);
                if(!r||!r.success){
                    var errMsg=(r&&r.data)?r.data:'Failed to load repairs';
                    console.error('[HM Repairs] Error:', errMsg);
                    $('#hm-repairs-table').html('<div class="hm-empty"><div class="hm-empty-text">'+esc(String(errMsg))+'</div></div>');
                    return;
                }
                allRepairs=r.data||[];
                renderStats();
                renderTable();
            }).fail(function(xhr,status,err){
                console.error('[HM Repairs] AJAX failed:', status, err);
                $('#hm-repairs-table').html('<div class="hm-empty"><div class="hm-empty-text">Network error — check console</div></div>');
            });
        }

        function renderStats(){
            var booked=0,sent=0,received=0,overdue=0;
            allRepairs.forEach(function(x){
                if(x.status==='Booked')booked++;
                else if(x.status==='Sent')sent++;
                else if(x.status==='Received')received++;
                if(x.status!=='Received'&&x.days_open&&x.days_open>14)overdue++;
            });
            $('#hm-repairs-stats').html(
                '<div class="hm-stat-card"><div class="hm-stat-val">'+booked+'</div><div class="hm-stat-label">Booked</div></div>'+
                '<div class="hm-stat-card"><div class="hm-stat-val" style="color:#2563eb;">'+sent+'</div><div class="hm-stat-label">Sent</div></div>'+
                '<div class="hm-stat-card"><div class="hm-stat-val" style="color:#16a34a;">'+received+'</div><div class="hm-stat-label">Received</div></div>'+
                '<div class="hm-stat-card"><div class="hm-stat-val" style="color:#dc2626;">'+overdue+'</div><div class="hm-stat-label">Overdue (14d+)</div></div>'
            );
        }

        function renderTable(){
            var status=$('#hm-repair-filter-status').val();
            var clinic=$('#hm-repair-filter-clinic').val();
            var q=$.trim($('#hm-repair-search').val()).toLowerCase();
            var filtered=allRepairs.filter(function(x){
                if(status&&x.status!==status)return false;
                if(clinic&&x.clinic_id!=clinic)return false;
                if(q){
                    var hay=(x.repair_number||'')+' '+(x.patient_name||'')+' '+(x.product_name||'')+' '+(x.manufacturer_name||'');
                    if(hay.toLowerCase().indexOf(q)===-1)return false;
                }
                return true;
            });

            if(!filtered.length){$('#hm-repairs-table').html('<div class="hm-empty"><div class="hm-empty-text">No repairs match filters</div></div>');return;}

            var h='<table class="hm-table"><thead><tr><th>Repair #</th><th>Patient</th><th>Hearing Aid</th><th>Manufacturer</th><th>Reason</th><th>Booked</th><th>Status</th><th>Days</th><th>Warranty</th><th></th></tr></thead><tbody>';
            filtered.forEach(function(x){
                var sc=x.status==='Booked'?'hm-badge-amber':x.status==='Sent'?'hm-badge-blue':'hm-badge-green';
                var rowClass='';
                if(x.status!=='Received'&&x.days_open){
                    if(x.days_open>14)rowClass=' class="hm-repair-overdue"';
                    else if(x.days_open>10)rowClass=' class="hm-repair-warning"';
                }
                var actions='';
                if(x.status==='Booked')actions='<button class="hm-btn hm-btn-outline hm-btn-sm hm-r-send" data-id="'+x._ID+'">Send</button>';
                else if(x.status==='Sent')actions='<button class="hm-btn hm-btn-outline hm-btn-sm hm-r-recv" data-id="'+x._ID+'">Received</button>';
                h+='<tr'+rowClass+'>'+
                    '<td><code class="hm-pt-hnum">'+esc(x.repair_number||'—')+'</code></td>'+
                    '<td><a href="/patients/?id='+x.patient_id+'" style="color:#0BB4C4;">'+esc(x.patient_name)+'</a></td>'+
                    '<td>'+esc(x.product_name||'—')+'</td>'+
                    '<td>'+esc(x.manufacturer_name||'—')+'</td>'+
                    '<td style="font-size:13px;">'+esc(x.repair_reason||'—')+'</td>'+
                    '<td>'+fmtDate(x.date_booked)+'</td>'+
                    '<td><span class="hm-badge hm-badge-sm '+sc+'">'+esc(x.status)+'</span></td>'+
                    '<td style="text-align:center;">'+(x.days_open||'—')+'</td>'+
                    '<td>'+(x.under_warranty?'<span class="hm-badge hm-badge-sm hm-badge-green">Yes</span>':'<span class="hm-badge hm-badge-sm hm-badge-gray">No</span>')+'</td>'+
                    '<td>'+actions+'</td>'+
                '</tr>';
            });
            h+='</tbody></table>';
            $('#hm-repairs-table').html(h);
        }

        // Event handlers
        $(document).on('change','#hm-repair-filter-status,#hm-repair-filter-clinic',renderTable);
        $(document).on('input','#hm-repair-search',renderTable);

        $(document).on('click','.hm-r-send',function(){
            var $b=$(this),rid=$b.data('id');$b.prop('disabled',true).text('…');
            $.post(_hm.ajax,{action:'hm_update_repair_status',nonce:_hm.nonce,_ID:rid,status:'Sent'},function(r){if(r.success)loadRepairs();else{alert('Error');$b.prop('disabled',false).text('Send');}});
        });
        $(document).on('click','.hm-r-recv',function(){
            var $b=$(this),rid=$b.data('id');$b.prop('disabled',true).text('…');
            $.post(_hm.ajax,{action:'hm_update_repair_status',nonce:_hm.nonce,_ID:rid,status:'Received'},function(r){if(r.success)loadRepairs();else{alert('Error');$b.prop('disabled',false).text('Received');}});
        });

        loadClinics();
        loadRepairs();
    })(jQuery);
    </script>
    <style>
    .hm-stat-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px 24px;min-width:120px;text-align:center;}
    .hm-stat-val{font-size:28px;font-weight:700;color:#151B33;line-height:1.2;}
    .hm-stat-label{font-size:12px;color:#94a3b8;margin-top:4px;text-transform:uppercase;letter-spacing:0.5px;}
    </style>
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

        // First try the full query with all expected columns
        $rows = $db->get_results(
            "SELECT r.id, r.repair_number, r.serial_number, r.date_booked, r.date_sent,
                    r.date_received, r.repair_status, r.warranty_status, r.repair_notes,
                    r.repair_reason, r.under_warranty, r.sent_to,
                    r.patient_id,
                    COALESCE(pr.product_name, 'Unknown') AS product_name,
                    COALESCE(m.name, '') AS manufacturer_name,
                    CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                    p.clinic_id
             FROM hearmed_core.repairs r
             LEFT JOIN hearmed_core.patient_devices pd ON pd.id = r.patient_device_id
             LEFT JOIN hearmed_reference.products pr ON pr.id = COALESCE(r.product_id, pd.product_id)
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = COALESCE(r.manufacturer_id, pr.manufacturer_id)
             LEFT JOIN hearmed_core.patients p ON p.id = r.patient_id
             ORDER BY
                CASE r.repair_status
                    WHEN 'Booked' THEN 1
                    WHEN 'Sent' THEN 2
                    ELSE 3
                END,
                r.date_booked DESC"
        );

        // If failed (likely missing columns), try minimal query with only schema columns
        $last_err = HearMed_DB::last_error();
        if (empty($rows) && $last_err) {
            error_log('[HM Repairs] Full query failed, trying minimal: ' . $last_err);
            $rows = $db->get_results(
                "SELECT r.id, r.serial_number, r.date_booked, r.date_sent,
                        r.date_received, r.repair_status, r.warranty_status, r.repair_notes,
                        r.patient_id,
                        COALESCE(pr.product_name, 'Unknown') AS product_name,
                        COALESCE(m.name, '') AS manufacturer_name,
                        CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                        p.clinic_id
                 FROM hearmed_core.repairs r
                 LEFT JOIN hearmed_core.patient_devices pd ON pd.id = r.patient_device_id
                 LEFT JOIN hearmed_reference.products pr ON pr.id = COALESCE(r.product_id, pd.product_id)
                 LEFT JOIN hearmed_reference.manufacturers m ON m.id = COALESCE(r.manufacturer_id, pr.manufacturer_id)
                 LEFT JOIN hearmed_core.patients p ON p.id = r.patient_id
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
                $out[] = [
                    '_ID'              => (int) $r->id,
                    'repair_number'    => $r->repair_number ?? '',
                    'product_name'     => $r->product_name ?? 'Unknown',
                    'manufacturer_name'=> $r->manufacturer_name ?? '',
                    'serial_number'    => $r->serial_number ?? '',
                    'date_booked'      => $r->date_booked,
                    'status'           => $r->repair_status ?: 'Booked',
                    'warranty_status'  => $r->warranty_status ?? '',
                    'under_warranty'   => isset($r->under_warranty) ? hm_pg_bool($r->under_warranty) : false,
                    'repair_reason'    => $r->repair_reason ?? '',
                    'repair_notes'     => $r->repair_notes ?? '',
                    'patient_name'     => $r->patient_name ?? 'Unknown',
                    'patient_id'       => (int) $r->patient_id,
                    'clinic_id'        => $r->clinic_id ? (int) $r->clinic_id : null,
                    'days_open'        => $days_open,
                ];
            }
        }
        wp_send_json_success($out);
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
                    r.repair_status, r.warranty_status, r.under_warranty,
                    r.repair_reason, r.repair_notes, r.sent_to,
                    COALESCE(pr.product_name, 'Unknown') AS product_name,
                    COALESCE(m.name, '') AS manufacturer_name,
                    m.warranty_terms,
                    CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                    p.date_of_birth, p.phone, p.mobile,
                    c.clinic_name,
                    CONCAT(s.first_name, ' ', s.last_name) AS dispenser_name
             FROM hearmed_core.repairs r
             LEFT JOIN hearmed_core.patient_devices pd ON pd.id = r.patient_device_id
             LEFT JOIN hearmed_reference.products pr ON pr.id = COALESCE(r.product_id, pd.product_id)
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = COALESCE(r.manufacturer_id, pr.manufacturer_id)
             LEFT JOIN hearmed_core.patients p ON p.id = r.patient_id
             LEFT JOIN hearmed_reference.clinics c ON c.id = p.clinic_id
             LEFT JOIN hearmed_reference.staff s ON s.id = r.staff_id
             WHERE r.id = \$1",
            [$rid]
        );

        if (!$r) wp_send_json_error('Repair not found');

        $dob_fmt = $r->date_of_birth ? date('d/m/Y', strtotime($r->date_of_birth)) : '—';
        $booked_fmt = $r->date_booked ? date('d M Y', strtotime($r->date_booked)) : '—';
        $warranty_label = !empty($r->under_warranty) && hm_pg_bool($r->under_warranty) ? 'IN WARRANTY' : 'OUT OF WARRANTY';
        $warranty_class = !empty($r->under_warranty) && hm_pg_bool($r->under_warranty) ? 'color:#16a34a' : 'color:#dc2626';

        $html = '<!DOCTYPE html><html><head>
        <meta charset="UTF-8">
        <title>Repair Docket — ' . esc_html($r->repair_number ?: 'HMREP-' . $rid) . '</title>
        <style>
            *{box-sizing:border-box}
            body{font-family:Arial,sans-serif;max-width:820px;margin:2rem auto;color:#151B33;font-size:13px}
            h1{color:#151B33;margin-bottom:0.25rem} .teal{color:#0BB4C4}
            .sub{color:#64748b;font-size:12px;margin-bottom:2rem}
            .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:0.75rem 1.5rem;margin-bottom:1.5rem}
            .grid div strong{display:block;font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px}
            table{width:100%;border-collapse:collapse;margin-top:1rem}
            th{background:#151B33;color:#fff;padding:7px 10px;text-align:left;font-size:11px;text-transform:uppercase}
            td{padding:8px 10px;border-bottom:1px solid #e2e8f0}
            .badge{padding:2px 8px;border-radius:3px;font-size:11px;font-weight:bold}
            .section{margin-top:1.5rem;padding:12px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:4px}
            .section h3{margin:0 0 8px;font-size:13px;color:#151B33}
            .sign{margin-top:2.5rem;border-top:2px solid #151B33;padding-top:1.25rem}
            .sign-row{display:flex;gap:3rem;margin-top:0.75rem}
            .sign-field{flex:1}
            .sign-field span{display:block;font-size:10px;color:#94a3b8;text-transform:uppercase;margin-bottom:0.5rem}
            .sign-line{border-bottom:1px solid #94a3b8;min-height:28px}
            .footer{margin-top:2rem;font-size:10px;color:#94a3b8}
            @media print{body{margin:1cm}}
        </style>
        </head><body>

        <h1>HearMed <span class="teal">Repair Docket</span></h1>
        <p class="sub">Internal repair tracking document. One copy to manufacturer, one filed.</p>

        <div class="grid">
            <div><strong>Repair Ref</strong>' . esc_html($r->repair_number ?: 'HMREP-' . $rid) . '</div>
            <div><strong>Date Booked</strong>' . $booked_fmt . '</div>
            <div><strong>Warranty</strong><span style="' . $warranty_class . ';font-weight:bold">' . $warranty_label . '</span></div>
            <div><strong>Patient</strong>' . esc_html($r->patient_name ?: '—') . '</div>
            <div><strong>DOB</strong>' . $dob_fmt . '</div>
            <div><strong>Phone</strong>' . esc_html($r->phone ?: $r->mobile ?: '—') . '</div>
            <div><strong>Clinic</strong>' . esc_html($r->clinic_name ?: '—') . '</div>
            <div><strong>Dispenser</strong>' . esc_html($r->dispenser_name ?: '—') . '</div>
            <div><strong>Status</strong>' . esc_html($r->repair_status ?: 'Booked') . '</div>
        </div>

        <table>
            <thead><tr><th>Manufacturer</th><th>Model</th><th>Serial Number</th><th>Reason for Repair</th></tr></thead>
            <tbody>
                <tr>
                    <td>' . esc_html($r->manufacturer_name ?: '—') . '</td>
                    <td>' . esc_html($r->product_name) . '</td>
                    <td><strong>' . esc_html($r->serial_number ?: '—') . '</strong></td>
                    <td>' . esc_html($r->repair_reason ?: '—') . '</td>
                </tr>
            </tbody>
        </table>';

        if ($r->repair_notes) {
            $html .= '<div class="section"><h3>Repair Notes / Fault Description</h3><p>' . esc_html($r->repair_notes) . '</p></div>';
        }

        if ($r->warranty_terms) {
            $html .= '<div class="section"><h3>Manufacturer Warranty Terms</h3><p>' . esc_html($r->warranty_terms) . '</p></div>';
        }

        $html .= '
        <div class="section">
            <h3>Checklist</h3>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;font-size:12px;">
                <label>☐ Hearing aid cleaned before sending</label>
                <label>☐ Battery / charger included</label>
                <label>☐ RMA number obtained from manufacturer</label>
                <label>☐ Return postage label attached</label>
                <label>☐ Patient informed of turnaround time</label>
                <label>☐ Loaner device issued (if applicable)</label>
            </div>
        </div>

        <div class="sign">
            <div class="sign-row">
                <div class="sign-field"><span>Sent by (staff)</span><div class="sign-line"></div></div>
                <div class="sign-field"><span>Date sent to manufacturer</span><div class="sign-line"></div></div>
                <div class="sign-field"><span>RMA / Tracking Number</span><div class="sign-line"></div></div>
            </div>
            <div class="sign-row" style="margin-top:1.25rem;">
                <div class="sign-field"><span>Date received back</span><div class="sign-line"></div></div>
                <div class="sign-field"><span>Received by</span><div class="sign-line"></div></div>
                <div class="sign-field"><span>Repair outcome / notes</span><div class="sign-line"></div></div>
            </div>
        </div>

        <p class="footer">HearMed Acoustic Health Care Ltd — Confidential — ' . esc_html($r->clinic_name ?: '') . '</p>
        <script>window.print();</script>
        </body></html>';

        // Return as HTML for opening in new tab
        wp_send_json_success(['html' => $html]);
    }
}

HearMed_Repairs::init();
