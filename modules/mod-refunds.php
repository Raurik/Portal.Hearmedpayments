<?php
/**
 * Refunds / Credit Notes — pending cheques, processed refunds
 * 
 * Shortcode: [hearmed_refunds]
 * Page: /refunds/
 */
if (!defined("ABSPATH")) exit;

function hm_refunds_render() {
    if (!is_user_logged_in()) return;
    ?>
    <div id="hm-refunds-app" class="hm-content">
        <div class="hm-page-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
            <h1 class="hm-page-title">Refunds &amp; Credit Notes</h1>
            <div style="display:flex;gap:10px;align-items:center;">
                <select id="hm-refund-filter" class="hm-dd" style="width:auto;min-width:160px;">
                    <option value="pending">Pending (Cheque Outstanding)</option>
                    <option value="all">All Credit Notes</option>
                    <option value="processed">Processed</option>
                </select>
                <input type="text" id="hm-refund-search" class="hm-inp" style="width:220px;" placeholder="Search patient or HMCN…">
            </div>
        </div>
        <div id="hm-refunds-stats" style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;"></div>
        <div id="hm-refunds-table" style="margin-top:12px;">
            <div class="hm-empty"><div class="hm-empty-text">Loading…</div></div>
        </div>
    </div>
    <script>
    (function($){
        var _hm=window._hm||{ajax:'<?php echo admin_url("admin-ajax.php"); ?>',nonce:'<?php echo wp_create_nonce("hm_nonce"); ?>'};
        var allNotes=[];

        function esc(s){return $('<span>').text(s||'').html();}
        function fmtDate(d){if(!d||d==='null')return '—';var p=d.split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:d;}
        function euro(v){return '€'+parseFloat(v||0).toFixed(2);}

        function loadNotes(){
            $.post(_hm.ajax,{action:'hm_get_all_credit_notes',nonce:_hm.nonce},function(r){
                if(!r||!r.success){$('#hm-refunds-table').html('<div class="hm-empty"><div class="hm-empty-text">Failed to load</div></div>');return;}
                allNotes=r.data||[];
                renderStats();
                renderTable();
            });
        }

        function renderStats(){
            var pending=0,pendingAmt=0,processed=0,processedAmt=0;
            allNotes.forEach(function(x){
                if(x.cheque_sent){processed++;processedAmt+=parseFloat(x.refund_amount||0);}
                else{pending++;pendingAmt+=parseFloat(x.refund_amount||0);}
            });
            $('#hm-refunds-stats').html(
                '<div class="hm-stat-card"><div class="hm-stat-val" style="color:#dc2626;">'+pending+'</div><div class="hm-stat-label">Pending</div></div>'+
                '<div class="hm-stat-card"><div class="hm-stat-val" style="color:#dc2626;">'+euro(pendingAmt)+'</div><div class="hm-stat-label">Pending Amount</div></div>'+
                '<div class="hm-stat-card"><div class="hm-stat-val" style="color:#16a34a;">'+processed+'</div><div class="hm-stat-label">Processed</div></div>'+
                '<div class="hm-stat-card"><div class="hm-stat-val" style="color:#16a34a;">'+euro(processedAmt)+'</div><div class="hm-stat-label">Processed Amount</div></div>'
            );
        }

        function renderTable(){
            var filter=$('#hm-refund-filter').val();
            var q=$.trim($('#hm-refund-search').val()).toLowerCase();
            var filtered=allNotes.filter(function(x){
                if(filter==='pending'&&x.cheque_sent)return false;
                if(filter==='processed'&&!x.cheque_sent)return false;
                if(q){
                    var hay=(x.credit_note_num||'')+' '+(x.patient_name||'')+' '+(x.product_name||'');
                    if(hay.toLowerCase().indexOf(q)===-1)return false;
                }
                return true;
            });

            if(!filtered.length){$('#hm-refunds-table').html('<div class="hm-empty"><div class="hm-empty-text">No credit notes match filters</div></div>');return;}

            var h='<table class="hm-table"><thead><tr><th>Credit Note #</th><th>Patient</th><th>Product</th><th>Refund Type</th><th>Amount</th><th>Date Created</th><th>Cheque Status</th><th></th></tr></thead><tbody>';
            filtered.forEach(function(x){
                var chequeHtml=x.cheque_sent
                    ?'<span class="hm-badge hm-badge-sm hm-badge-green">Sent '+fmtDate(x.cheque_sent_date)+'</span>'
                    :'<span class="hm-badge hm-badge-sm hm-badge-red">Outstanding</span>';
                var actions=(!x.cheque_sent)?'<button class="hm-btn hm-btn-outline hm-btn-sm hm-process-refund" data-id="'+x._ID+'">Process</button>':'';
                h+='<tr>'+
                    '<td><code class="hm-pt-hnum">'+esc(x.credit_note_num)+'</code></td>'+
                    '<td><a href="/patients/?id='+x.patient_id+'" style="color:#0BB4C4;">'+esc(x.patient_name)+'</a></td>'+
                    '<td>'+esc(x.product_name||'—')+'</td>'+
                    '<td>'+esc(x.refund_type||'cheque')+'</td>'+
                    '<td style="font-weight:500;">'+euro(x.refund_amount)+'</td>'+
                    '<td>'+fmtDate((x.created_at||'').split(' ')[0])+'</td>'+
                    '<td>'+chequeHtml+'</td>'+
                    '<td>'+actions+'</td>'+
                '</tr>';
            });
            h+='</tbody></table>';
            $('#hm-refunds-table').html(h);
        }

        // Filters
        $(document).on('change','#hm-refund-filter',renderTable);
        $(document).on('input','#hm-refund-search',renderTable);

        // Process refund modal
        $(document).on('click','.hm-process-refund',function(){
            var cnId=$(this).data('id');
            if($('#hm-modal-overlay').length)return;
            $('body').append('<div id="hm-modal-overlay" class="hm-modal-bg"><div class="hm-modal" style="max-width:400px;"><div class="hm-modal-hd"><span>Process Refund</span><button class="hm-modal-x">&times;</button></div><div class="hm-modal-body">'+
                '<div class="hm-form-group"><label class="hm-label">Cheque number</label><input type="text" class="hm-inp" id="refund-cheque-num" placeholder="e.g. CHQ-001234"></div>'+
                '<div class="hm-form-group"><label class="hm-label">Date sent</label><input type="date" class="hm-inp" id="refund-date" value="'+new Date().toISOString().split('T')[0]+'"></div>'+
            '</div><div class="hm-modal-ft"><button class="hm-btn hm-btn-outline hm-modal-x">Cancel</button><button class="hm-btn hm-btn-teal" id="refund-save">Process Refund</button></div></div></div>');
            $('.hm-modal-x').on('click',function(){$('#hm-modal-overlay').remove();});
            $('#hm-modal-overlay').on('click',function(e){if(e.target===this)$(this).remove();});
            $('#refund-save').on('click',function(){
                var chequeNum=$('#refund-cheque-num').val(),chequeDate=$('#refund-date').val();
                if(!chequeNum){alert('Enter cheque number');return;}
                $(this).prop('disabled',true).text('Processing…');
                $.post(_hm.ajax,{action:'hm_process_refund',nonce:_hm.nonce,credit_note_id:cnId,cheque_number:chequeNum,cheque_date:chequeDate},function(r){
                    $('#hm-modal-overlay').remove();
                    if(r.success)loadNotes();else alert(r.data||'Error');
                });
            });
        });

        loadNotes();
    })(jQuery);
    </script>
    <style>
    .hm-stat-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px 24px;min-width:120px;text-align:center;}
    .hm-stat-val{font-size:28px;font-weight:700;color:#151B33;line-height:1.2;}
    .hm-stat-label{font-size:12px;color:#94a3b8;margin-top:4px;text-transform:uppercase;letter-spacing:0.5px;}
    </style>
    <?php
}

class HearMed_Refunds {

    public static function init() {
        add_shortcode("hearmed_refunds", [__CLASS__, "render"]);
        add_action("wp_ajax_hm_get_all_credit_notes", [__CLASS__, "ajax_get_all"]);
        add_action("wp_ajax_hm_process_refund", [__CLASS__, "ajax_process"]);
    }

    public static function render($atts = []): string {
        if (!is_user_logged_in()) return "";
        ob_start();
        hm_refunds_render();
        return ob_get_clean();
    }

    /**
     * Get ALL credit notes across patients
     */
    public static function ajax_get_all() {
        check_ajax_referer('hm_nonce', 'nonce');

        $sql = "SELECT cn.*, 
                    pd.product_name,
                    p.first_name || ' ' || p.last_name AS patient_name, 
                    p._ID AS patient_id
                FROM hearmed_core.credit_notes cn
                LEFT JOIN hearmed_core.patient_devices pd ON pd._ID = cn.patient_product_id
                LEFT JOIN hearmed_core.patients p ON p._ID = cn.patient_id
                ORDER BY cn.cheque_sent ASC, cn.created_at DESC";

        $rows = HearMed_DB::get_results($sql);
        wp_send_json_success($rows ?: []);
    }

    /**
     * Process a refund — mark cheque as sent
     */
    public static function ajax_process() {
        check_ajax_referer('hm_nonce', 'nonce');

        $cn_id   = intval($_POST['credit_note_id'] ?? 0);
        $cheque  = sanitize_text_field($_POST['cheque_number'] ?? '');
        $date    = sanitize_text_field($_POST['cheque_date'] ?? date('Y-m-d'));

        if (!$cn_id || !$cheque) {
            wp_send_json_error('Missing fields');
        }

        $current_user = wp_get_current_user();

        HearMed_DB::update(
            HearMed_DB::table('credit_notes'),
            [
                'cheque_sent'      => true,
                'cheque_sent_date' => $date,
                'cheque_number'    => $cheque,
                'processed_by'    => $current_user->display_name,
                'processed_at'    => date('Y-m-d H:i:s'),
            ],
            ['_ID' => $cn_id]
        );

        wp_send_json_success(['message' => 'Refund processed']);
    }
}

HearMed_Refunds::init();
