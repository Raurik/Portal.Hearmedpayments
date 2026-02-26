<?php
/**
 * Stock / Inventory — manufacturer, model, tech level, clinic location, transfer, fit
 * 
 * Shortcode: [hearmed_stock]
 * Page: /stock/
 */
if (!defined("ABSPATH")) exit;

function hm_stock_render() {
    if (!is_user_logged_in()) return;
    ?>
    <div id="hm-stock-app" class="hm-content">
        <div class="hm-page-header">
            <h1 class="hm-page-title">Stock &amp; Inventory</h1>
            <div style="display:flex;gap:10px;align-items:center;">
                <select id="hm-stock-mfr" class="hm-dd" style="width:auto;min-width:160px;">
                    <option value="">All manufacturers</option>
                </select>
                <select id="hm-stock-clinic" class="hm-dd" style="width:auto;min-width:160px;">
                    <option value="">All clinics</option>
                </select>
                <select id="hm-stock-tech" class="hm-dd" style="width:auto;min-width:130px;">
                    <option value="">All levels</option>
                    <option>Entry</option><option>Essential</option><option>Standard</option><option>Advanced</option><option>Premium</option>
                </select>
                <input type="text" id="hm-stock-search" class="hm-inp" style="width:200px;" placeholder="Search model…">
            </div>
        </div>
        <div id="hm-stock-stats" style="display:flex;gap:16px;margin-bottom:20px;flex-wrap:wrap;"></div>
        <div id="hm-stock-table" style="margin-top:12px;">
            <div class="hm-empty"><div class="hm-empty-text">Loading stock…</div></div>
        </div>
    </div>
    <script>
    (function($){
        var _hm=window._hm||{ajax:'<?php echo admin_url("admin-ajax.php"); ?>',nonce:'<?php echo wp_create_nonce("hm_nonce"); ?>'};
        var allStock=[];

        function esc(s){return $('<span>').text(s||'').html();}

        function loadFilters(){
            $.post(_hm.ajax,{action:'hm_get_manufacturers',nonce:_hm.nonce},function(r){
                if(r&&r.success&&r.data){r.data.forEach(function(m){$('#hm-stock-mfr').append('<option value="'+m._ID+'">'+esc(m.name)+'</option>');});}
            });
            $.post(_hm.ajax,{action:'hm_get_clinics',nonce:_hm.nonce},function(r){
                if(r&&r.success&&r.data){r.data.forEach(function(c){$('#hm-stock-clinic').append('<option value="'+c._ID+'">'+esc(c.name)+'</option>');});}
            });
        }

        function loadStock(){
            $.post(_hm.ajax,{action:'hm_get_stock',nonce:_hm.nonce},function(r){
                if(!r||!r.success){$('#hm-stock-table').html('<div class="hm-empty"><div class="hm-empty-text">Failed to load</div></div>');return;}
                allStock=r.data||[];
                renderStats();
                renderTable();
            });
        }

        function renderStats(){
            var total=0,clinics={};
            allStock.forEach(function(x){
                total+=parseInt(x.quantity||0);
                var cn=x.clinic_name||'Unassigned';
                clinics[cn]=(clinics[cn]||0)+parseInt(x.quantity||0);
            });
            var statsHtml='<div class="hm-stat"><div class="hm-stat-val">'+total+'</div><div class="hm-stat-label">Total Units</div></div>';
            Object.keys(clinics).forEach(function(cn){
                statsHtml+='<div class="hm-stat"><div class="hm-stat-val">'+clinics[cn]+'</div><div class="hm-stat-label">'+esc(cn)+'</div></div>';
            });
            $('#hm-stock-stats').html(statsHtml);
        }

        function renderTable(){
            var mfr=$('#hm-stock-mfr').val();
            var clinic=$('#hm-stock-clinic').val();
            var tech=$('#hm-stock-tech').val();
            var q=$.trim($('#hm-stock-search').val()).toLowerCase();
            var filtered=allStock.filter(function(x){
                if(mfr&&x.manufacturer_id!=mfr)return false;
                if(clinic&&x.clinic_id!=clinic)return false;
                if(tech&&x.technology_level!==tech)return false;
                if(q){
                    var hay=(x.model_name||'')+' '+(x.manufacturer_name||'')+' '+(x.serial_number||'');
                    if(hay.toLowerCase().indexOf(q)===-1)return false;
                }
                return true;
            });

            if(!filtered.length){$('#hm-stock-table').html('<div class="hm-empty"><div class="hm-empty-text">No stock matches filters</div></div>');return;}

            var h='<table class="hm-table"><thead><tr><th>Manufacturer</th><th>Model</th><th>Style</th><th>Tech Level</th><th>Serial #</th><th>Clinic</th><th>Qty</th><th>Status</th><th></th></tr></thead><tbody>';
            filtered.forEach(function(x){
                var sc=x.status==='Available'?'hm-badge--green':x.status==='Reserved'?'hm-badge--amber':'hm-badge--grey';
                h+='<tr>'+
                    '<td>'+esc(x.manufacturer_name||'—')+'</td>'+
                    '<td style="font-weight:500;">'+esc(x.model_name||'—')+'</td>'+
                    '<td>'+esc(x.style||'—')+'</td>'+
                    '<td>'+esc(x.technology_level||'—')+'</td>'+
                    '<td><code class="hm-pt-hnum">'+esc(x.serial_number||'—')+'</code></td>'+
                    '<td>'+esc(x.clinic_name||'—')+'</td>'+
                    '<td style="text-align:center;">'+esc(x.quantity||0)+'</td>'+
                    '<td><span class="hm-badge hm-badge--sm '+sc+'">'+esc(x.status||'Available')+'</span></td>'+
                    '<td style="display:flex;gap:4px;">'+
                        (x.status==='Available'?'<button class="hm-btn hm-btn--secondary hm-btn--sm hm-stock-transfer" data-id="'+x._ID+'">Transfer</button>'+
                        '<button class="hm-btn hm-btn--primary hm-btn--sm hm-stock-fit" data-id="'+x._ID+'" data-name="'+esc(x.manufacturer_name+' '+x.model_name)+'">Fit</button>':'')+
                    '</td>'+
                '</tr>';
            });
            h+='</tbody></table>';
            $('#hm-stock-table').html(h);
        }

        // Filters
        $(document).on('change','#hm-stock-mfr,#hm-stock-clinic,#hm-stock-tech',renderTable);
        $(document).on('input','#hm-stock-search',renderTable);

        // Transfer modal
        $(document).on('click','.hm-stock-transfer',function(){
            var sid=$(this).data('id');
            if($('#hm-modal-overlay').length)return;
            $('body').append('<div id="hm-modal-overlay" class="hm-modal-bg"><div class="hm-modal hm-modal--sm"><div class="hm-modal-hd"><span>Transfer Stock</span><button class="hm-close">&times;</button></div><div class="hm-modal-body">'+
                '<div class="hm-form-group"><label class="hm-label">Transfer to clinic *</label><select class="hm-dd" id="transfer-clinic"><option value="">— Select clinic —</option></select></div>'+
                '<div class="hm-form-group"><label class="hm-label">Quantity</label><input type="number" class="hm-inp" id="transfer-qty" value="1" min="1"></div>'+
                '<div class="hm-form-group"><label class="hm-label">Notes</label><textarea class="hm-textarea" id="transfer-notes" rows="2"></textarea></div>'+
            '</div><div class="hm-modal-ft"><button class="hm-btn hm-btn--secondary hm-close">Cancel</button><button class="hm-btn hm-btn--primary" id="transfer-save">Transfer</button></div></div></div>');
            // Load clinics into transfer dropdown
            $.post(_hm.ajax,{action:'hm_get_clinics',nonce:_hm.nonce},function(r){
                if(r&&r.success&&r.data){r.data.forEach(function(c){$('#transfer-clinic').append('<option value="'+c._ID+'">'+esc(c.name)+'</option>');});}
            });
            $('.hm-close').on('click',function(){$('#hm-modal-overlay').remove();});
            $('#hm-modal-overlay').on('click',function(e){if(e.target===this)$(this).remove();});
            $('#transfer-save').on('click',function(){
                var toClinic=$('#transfer-clinic').val();
                if(!toClinic){alert('Select a clinic');return;}
                $(this).prop('disabled',true).text('Transferring…');
                $.post(_hm.ajax,{action:'hm_transfer_stock',nonce:_hm.nonce,stock_id:sid,to_clinic_id:toClinic,quantity:$('#transfer-qty').val(),notes:$('#transfer-notes').val()},function(r){
                    $('#hm-modal-overlay').remove();
                    if(r.success)loadStock();else alert(r.data||'Error');
                });
            });
        });

        // Fit — redirect to patient with product pre-selected
        $(document).on('click','.hm-stock-fit',function(){
            var sid=$(this).data('id'),name=$(this).data('name');
            var patientId=prompt('Enter patient C-number or ID to fit "'+name+'" to:');
            if(!patientId)return;
            $(this).prop('disabled',true).text('…');
            $.post(_hm.ajax,{action:'hm_fit_stock',nonce:_hm.nonce,stock_id:sid,patient_identifier:patientId},function(r){
                if(r.success){
                    alert('Stock fitted to patient. Redirecting…');
                    window.location='/patients/?id='+r.data.patient_id;
                }else alert(r.data||'Error — check patient ID');
            });
        });

        loadFilters();
        loadStock();
    })(jQuery);
    </script>
    <?php
}

class HearMed_Stock {

    public static function init() {
        add_shortcode("hearmed_stock", [__CLASS__, "render"]);
        add_action("wp_ajax_hm_get_stock", [__CLASS__, "ajax_get_stock"]);
        add_action("wp_ajax_hm_transfer_stock", [__CLASS__, "ajax_transfer"]);
        add_action("wp_ajax_hm_fit_stock", [__CLASS__, "ajax_fit"]);
    }

    public static function render($atts = []): string {
        if (!is_user_logged_in()) return "";
        ob_start();
        hm_stock_render();
        return ob_get_clean();
    }

    /**
     * Get all inventory stock
     */
    public static function ajax_get_stock() {
        check_ajax_referer('hm_nonce', 'nonce');

        $sql = "SELECT s.*, 
                    m.name AS manufacturer_name,
                    c.name AS clinic_name
                FROM hearmed_core.inventory_stock s
                LEFT JOIN hearmed_reference.manufacturers m ON m._ID = s.manufacturer_id
                LEFT JOIN hearmed_admin.clinics c ON c._ID = s.clinic_id
                ORDER BY m.name, s.model_name, s.technology_level";

        $rows = HearMed_DB::get_results($sql);
        wp_send_json_success($rows ?: []);
    }

    /**
     * Transfer stock between clinics
     */
    public static function ajax_transfer() {
        check_ajax_referer('hm_nonce', 'nonce');

        $stock_id   = intval($_POST['stock_id'] ?? 0);
        $to_clinic  = intval($_POST['to_clinic_id'] ?? 0);
        $qty        = intval($_POST['quantity'] ?? 1);
        $notes      = sanitize_text_field($_POST['notes'] ?? '');

        if (!$stock_id || !$to_clinic) {
            wp_send_json_error('Missing fields');
        }

        $current_user = wp_get_current_user();

        // Get current stock record
        $stock = HearMed_DB::get_row(
            "SELECT * FROM hearmed_core.inventory_stock WHERE _ID = $1",
            [$stock_id]
        );
        if (!$stock) wp_send_json_error('Stock not found');

        $from_clinic = $stock->clinic_id;

        HearMed_DB::begin_transaction();
        try {
            // Update clinic_id on stock
            HearMed_DB::update(
                HearMed_DB::table('inventory_stock'),
                ['clinic_id' => $to_clinic],
                ['_ID' => $stock_id]
            );

            // Log the movement
            HearMed_DB::insert(
                HearMed_DB::table('stock_movements'),
                [
                    'stock_id'       => $stock_id,
                    'movement_type'  => 'transfer',
                    'from_clinic_id' => $from_clinic,
                    'to_clinic_id'   => $to_clinic,
                    'quantity'       => $qty,
                    'notes'          => $notes,
                    'created_by'     => $current_user->display_name,
                    'created_at'     => date('Y-m-d H:i:s'),
                ]
            );

            HearMed_DB::commit();
            wp_send_json_success(['message' => 'Transferred']);
        } catch (\Exception $e) {
            HearMed_DB::rollback();
            wp_send_json_error('Transfer failed: ' . $e->getMessage());
        }
    }

    /**
     * Fit stock item to a patient (mark as fitted, create patient_device)
     */
    public static function ajax_fit() {
        check_ajax_referer('hm_nonce', 'nonce');

        $stock_id = intval($_POST['stock_id'] ?? 0);
        $ident    = sanitize_text_field($_POST['patient_identifier'] ?? '');

        if (!$stock_id || !$ident) {
            wp_send_json_error('Missing fields');
        }

        // Find patient by C-number or ID
        $patient = null;
        if (is_numeric($ident)) {
            $patient = HearMed_DB::get_row(
                "SELECT _ID FROM hearmed_core.patients WHERE _ID = $1",
                [$ident]
            );
        }
        if (!$patient) {
            $patient = HearMed_DB::get_row(
                "SELECT _ID FROM hearmed_core.patients WHERE customer_number = $1",
                [$ident]
            );
        }
        if (!$patient) wp_send_json_error('Patient not found');

        $stock = HearMed_DB::get_row(
            "SELECT * FROM hearmed_core.inventory_stock WHERE _ID = $1",
            [$stock_id]
        );
        if (!$stock) wp_send_json_error('Stock not found');

        $current_user = wp_get_current_user();
        $today = date('Y-m-d');
        $warranty_expiry = date('Y-m-d', strtotime('+4 years'));

        HearMed_DB::begin_transaction();
        try {
            // Create patient device
            HearMed_DB::insert(
                HearMed_DB::table('patient_devices'),
                [
                    'patient_id'     => $patient->_ID,
                    'product_name'   => ($stock->manufacturer_name ?? '') . ' ' . ($stock->model_name ?? ''),
                    'manufacturer'   => $stock->manufacturer_name ?? '',
                    'model'          => $stock->model_name ?? '',
                    'style'          => $stock->style ?? '',
                    'serial_number_left'  => $stock->serial_number ?? '',
                    'fitting_date'   => $today,
                    'warranty_expiry'=> $warranty_expiry,
                    'purchase_date'  => $today,
                    'status'         => 'Active',
                    'created_by'     => $current_user->display_name,
                    'created_at'     => date('Y-m-d H:i:s'),
                ]
            );

            // Mark stock as fitted
            HearMed_DB::update(
                HearMed_DB::table('inventory_stock'),
                ['status' => 'Fitted', 'fitted_to_patient_id' => $patient->_ID],
                ['_ID' => $stock_id]
            );

            // Log movement
            HearMed_DB::insert(
                HearMed_DB::table('stock_movements'),
                [
                    'stock_id'       => $stock_id,
                    'movement_type'  => 'fitted',
                    'quantity'       => 1,
                    'notes'          => 'Fitted to patient #' . $patient->_ID,
                    'created_by'     => $current_user->display_name,
                    'created_at'     => date('Y-m-d H:i:s'),
                ]
            );

            HearMed_DB::commit();
            wp_send_json_success(['patient_id' => $patient->_ID]);
        } catch (\Exception $e) {
            HearMed_DB::rollback();
            wp_send_json_error('Fit failed: ' . $e->getMessage());
        }
    }
}

HearMed_Stock::init();
