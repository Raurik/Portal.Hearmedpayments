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
                    r.data.forEach(function(c){$('#hm-repair-filter-clinic').append('<option value="'+c._ID+'">'+esc(c.name)+'</option>');});
                }
            });
        }

        function loadRepairs(){
            $.post(_hm.ajax,{action:'hm_get_all_repairs',nonce:_hm.nonce},function(r){
                if(!r||!r.success){$('#hm-repairs-table').html('<div class="hm-empty"><div class="hm-empty-text">Failed to load repairs</div></div>');return;}
                allRepairs=r.data||[];
                renderStats();
                renderTable();
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
            $.post(_hm.ajax,{action:'hm_update_repair_status',nonce:_hm.nonce,repair_id:rid,status:'Sent'},function(r){if(r.success)loadRepairs();else{alert('Error');$b.prop('disabled',false).text('Send');}});
        });
        $(document).on('click','.hm-r-recv',function(){
            var $b=$(this),rid=$b.data('id');$b.prop('disabled',true).text('…');
            $.post(_hm.ajax,{action:'hm_update_repair_status',nonce:_hm.nonce,repair_id:rid,status:'Received'},function(r){if(r.success)loadRepairs();else{alert('Error');$b.prop('disabled',false).text('Received');}});
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
        
        $sql = "SELECT r.*, 
                    pd.product_name, pd.manufacturer, pd.serial_number_left, pd.serial_number_right,
                    p.first_name || ' ' || p.last_name AS patient_name, p._ID AS patient_id,
                    p.clinic_id,
                    m.name AS manufacturer_name,
                    EXTRACT(DAY FROM NOW() - r.date_booked)::int AS days_open
                FROM hearmed_core.repairs r
                LEFT JOIN hearmed_core.patient_devices pd ON pd._ID = r.patient_product_id
                LEFT JOIN hearmed_core.patients p ON p._ID = r.patient_id
                LEFT JOIN hearmed_reference.manufacturers m ON m._ID = r.manufacturer_id
                ORDER BY 
                    CASE r.status 
                        WHEN 'Booked' THEN 1 
                        WHEN 'Sent' THEN 2 
                        ELSE 3 
                    END,
                    r.date_booked DESC";
        
        $rows = HearMed_DB::get_results($sql);
        wp_send_json_success($rows ?: []);
    }
}

HearMed_Repairs::init();
