<?php
/**
 * Stock & Inventory — 3-tab rebuild
 *   Tab 1: Hearing Aids   (serial-tracked, per-unit)
 *   Tab 2: Consumables    (quantity-tracked, low-stock alerts)
 *   Tab 3: Movements Log  (audit trail)
 *
 * Shortcode: [hearmed_stock]
 * Page:      /stock/
 *
 * @package HearMed_Portal
 * @since   5.1.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_Stock {

    public static function init() {
        add_shortcode( 'hearmed_stock', [ __CLASS__, 'render' ] );

        // AJAX endpoints (also registered centrally in class-hearmed-ajax.php)
        add_action( 'wp_ajax_hm_stock_hearing_aids',  [ __CLASS__, 'ajax_load_hearing_aids' ] );
        add_action( 'wp_ajax_hm_stock_consumables',   [ __CLASS__, 'ajax_load_consumables' ] );
        add_action( 'wp_ajax_hm_stock_movements',     [ __CLASS__, 'ajax_load_movements' ] );
        add_action( 'wp_ajax_hm_stock_transfer',      [ __CLASS__, 'ajax_transfer' ] );
        add_action( 'wp_ajax_hm_stock_add',           [ __CLASS__, 'ajax_add_stock' ] );
        add_action( 'wp_ajax_hm_stock_adjust_qty',    [ __CLASS__, 'ajax_adjust_quantity' ] );
        add_action( 'wp_ajax_hm_stock_reserve',       [ __CLASS__, 'ajax_reserve' ] );
        // Legacy compat
        add_action( 'wp_ajax_hm_get_stock',           [ __CLASS__, 'ajax_load_hearing_aids' ] );
        add_action( 'wp_ajax_hm_transfer_stock',      [ __CLASS__, 'ajax_transfer' ] );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RENDER
    // ═══════════════════════════════════════════════════════════════════════

    public static function render( $atts = [] ): string {
        if ( ! PortalAuth::is_logged_in() ) return '';
        self::ensure_tables();
        ob_start();
        self::output();
        return ob_get_clean();
    }

    /**
     * Auto-migration: ensure inventory_stock + stock_movements tables exist.
     */
    private static function ensure_tables() {
        static $done = false;
        if ( $done ) return;
        $done = true;

        $db = HearMed_DB::instance();

        $db->query(
            "CREATE TABLE IF NOT EXISTS hearmed_reference.inventory_stock (
                id                      BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
                item_category           VARCHAR(30) NOT NULL DEFAULT 'hearing_aid',
                clinic_id               BIGINT,
                manufacturer_id         BIGINT,
                model_name              VARCHAR(255),
                style                   VARCHAR(100),
                technology_level        VARCHAR(50),
                serial_number           VARCHAR(100),
                specification           TEXT,
                quantity                INT NOT NULL DEFAULT 1,
                status                  VARCHAR(30) NOT NULL DEFAULT 'Available',
                reserved_for_patient_id BIGINT,
                fitted_to_patient_id    BIGINT,
                return_reason           TEXT,
                created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        );

        // Ensure return_reason column exists (for older tables)
        $has_col = $db->get_var(
            "SELECT column_name FROM information_schema.columns
             WHERE table_schema = 'hearmed_reference' AND table_name = 'inventory_stock' AND column_name = 'return_reason'"
        );
        if ( ! $has_col ) {
            $db->query( "ALTER TABLE hearmed_reference.inventory_stock ADD COLUMN return_reason TEXT" );
        }

        $db->query(
            "CREATE TABLE IF NOT EXISTS hearmed_reference.stock_movements (
                id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
                stock_id        BIGINT,
                movement_type   VARCHAR(30),
                from_clinic_id  BIGINT,
                to_clinic_id    BIGINT,
                quantity        INT DEFAULT 1,
                notes           TEXT,
                created_by      VARCHAR(255),
                created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        );
    }

    private static function output() {
        $nonce = wp_create_nonce( 'hm_nonce' );
        ?>
        <style>
        .hm-stock-tabs{display:flex;gap:4px;margin-bottom:16px}
        .hm-stock-tab{padding:8px 18px;border:1px solid var(--hm-border,#e2e8f0);border-radius:8px;background:var(--hm-white,#fff);cursor:pointer;font-size:13px;font-weight:600;color:var(--hm-grey,#64748b);transition:all .15s;font-family:var(--hm-font,'Source Sans 3',sans-serif)}
        .hm-stock-tab.active{background:var(--hm-navy,#151B33);color:#fff;border-color:var(--hm-navy,#151B33)}
        .hm-stock-tab .pill{display:inline-flex;align-items:center;justify-content:center;min-width:20px;height:20px;padding:0 6px;border-radius:10px;font-size:11px;font-weight:600;margin-left:6px;background:rgba(0,0,0,.08);color:inherit}
        .hm-stock-tab.active .pill{background:rgba(255,255,255,.2);color:#fff}
        .hm-stock-filters{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:12px}
        .hm-stock-filters select,.hm-stock-filters input{font-size:13px;padding:6px 10px;border:1px solid var(--hm-border,#e2e8f0);border-radius:6px;font-family:var(--hm-font,'Source Sans 3',sans-serif)}
        .hm-low-stock{background:var(--hm-error-light,#fef2f2);color:var(--hm-error,#dc2626);font-weight:600;padding:2px 8px;border-radius:4px;font-size:12px}
        .hm-serial{font-family:'SF Mono',monospace;font-size:12px;letter-spacing:.5px;color:var(--hm-navy,#151B33)}
        </style>

        <div id="hm-stock-app" class="hm-content">
            <div class="hm-page-header">
                <h1 class="hm-page-title">Stock &amp; Inventory</h1>
                <div style="display:flex;gap:8px;">
                    <button class="hm-btn hm-btn--primary" id="hm-stock-add-btn">+ Add Stock</button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="hm-stock-tabs" id="hm-stock-tabs">
                <div class="hm-stock-tab active" data-tab="hearing_aids">Hearing Aids <span class="pill" id="ha-count">0</span></div>
                <div class="hm-stock-tab" data-tab="consumables">Consumables <span class="pill" id="cons-count">0</span></div>
                <div class="hm-stock-tab" data-tab="movements">Movements Log</div>
            </div>

            <!-- Stats -->
            <div class="hm-stats" id="hm-stock-stats"></div>

            <!-- Filters -->
            <div class="hm-stock-filters" id="hm-stock-filters">
                <select id="hm-stock-clinic" class="hm-dd" style="min-width:160px"><option value="">All clinics</option></select>
                <select id="hm-stock-mfr" class="hm-dd" style="min-width:160px"><option value="">All manufacturers</option></select>
                <select id="hm-stock-status" class="hm-dd" style="min-width:130px">
                    <option value="">All statuses</option>
                    <option value="Available">Available</option>
                    <option value="Reserved">Reserved</option>
                    <option value="Fitted">Fitted</option>
                    <option value="Returned">Returned</option>
                </select>
                <input type="text" id="hm-stock-search" class="hm-inp" style="width:200px" placeholder="Search serial / model…">
            </div>

            <!-- Table area -->
            <div class="hm-card">
                <div id="hm-stock-table"><div class="hm-empty"><div class="hm-empty-text">Loading…</div></div></div>
            </div>
        </div>

        <!-- ══ TRANSFER MODAL ═════════════════════════════════════════════ -->
        <div class="hm-modal-bg" id="hm-transfer-modal" style="display:none">
            <div class="hm-modal hm-modal--sm">
                <div class="hm-modal-hd"><h3>Transfer Stock</h3><button class="hm-close hm-modal-close">&times;</button></div>
                <div class="hm-modal-body">
                    <input type="hidden" id="transfer-stock-id">
                    <div class="hm-form-group"><label class="hm-label">Transfer to clinic *</label>
                        <select class="hm-input" id="transfer-clinic"><option value="">— Select clinic —</option></select></div>
                    <div class="hm-form-group"><label class="hm-label">Notes</label>
                        <textarea class="hm-input" id="transfer-notes" rows="2"></textarea></div>
                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn hm-btn--secondary hm-modal-close">Cancel</button>
                    <button class="hm-btn hm-btn--primary" id="transfer-save">Transfer</button>
                </div>
            </div>
        </div>

        <!-- ══ ADD STOCK MODAL ════════════════════════════════════════════ -->
        <div class="hm-modal-bg" id="hm-add-stock-modal" style="display:none">
            <div class="hm-modal hm-modal--md">
                <div class="hm-modal-hd"><h3>Add Stock</h3><button class="hm-close hm-modal-close">&times;</button></div>
                <div class="hm-modal-body">
                    <div class="hm-form-row" style="display:flex;gap:12px">
                        <div class="hm-form-group" style="flex:1"><label class="hm-label">Category *</label>
                            <select class="hm-input" id="add-category">
                                <option value="hearing_aid">Hearing Aid</option>
                                <option value="consumable">Consumable</option>
                            </select></div>
                        <div class="hm-form-group" style="flex:1"><label class="hm-label">Clinic *</label>
                            <select class="hm-input" id="add-clinic"><option value="">— Select —</option></select></div>
                    </div>
                    <div class="hm-form-row" style="display:flex;gap:12px">
                        <div class="hm-form-group" style="flex:1"><label class="hm-label">Manufacturer</label>
                            <select class="hm-input" id="add-manufacturer"><option value="">— Select —</option></select></div>
                        <div class="hm-form-group" style="flex:1"><label class="hm-label">Model / Product Name *</label>
                            <input type="text" class="hm-input" id="add-model" placeholder="e.g. Evolv AI 2400"></div>
                    </div>
                    <div class="hm-form-row" style="display:flex;gap:12px">
                        <div class="hm-form-group" style="flex:1"><label class="hm-label">Style</label>
                            <input type="text" class="hm-input" id="add-style" placeholder="e.g. RIC, BTE, ITC"></div>
                        <div class="hm-form-group" style="flex:1"><label class="hm-label">Technology Level</label>
                            <select class="hm-input" id="add-tech-level">
                                <option value="">— Select —</option>
                                <option>Entry</option><option>Essential</option><option>Standard</option><option>Advanced</option><option>Premium</option>
                            </select></div>
                    </div>
                    <div id="add-serial-row" class="hm-form-group"><label class="hm-label">Serial Number</label>
                        <input type="text" class="hm-input" id="add-serial" placeholder="Serial #"></div>
                    <div id="add-qty-row" class="hm-form-group" style="display:none"><label class="hm-label">Quantity *</label>
                        <input type="number" class="hm-input" id="add-qty" value="1" min="1"></div>
                    <div class="hm-form-group"><label class="hm-label">Specification / Notes</label>
                        <textarea class="hm-input" id="add-notes" rows="2"></textarea></div>
                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn hm-btn--secondary hm-modal-close">Cancel</button>
                    <button class="hm-btn hm-btn--primary" id="add-stock-save">Add Stock</button>
                </div>
            </div>
        </div>

        <!-- ══ ADJUST QTY MODAL (consumables only) ════════════════════════ -->
        <div class="hm-modal-bg" id="hm-adjust-modal" style="display:none">
            <div class="hm-modal hm-modal--sm">
                <div class="hm-modal-hd"><h3>Adjust Quantity</h3><button class="hm-close hm-modal-close">&times;</button></div>
                <div class="hm-modal-body">
                    <input type="hidden" id="adjust-stock-id">
                    <p id="adjust-current" style="margin:0 0 12px;font-size:14px;color:var(--hm-grey,#64748b)"></p>
                    <div class="hm-form-group"><label class="hm-label">New Quantity *</label>
                        <input type="number" class="hm-input" id="adjust-qty" min="0"></div>
                    <div class="hm-form-group"><label class="hm-label">Reason</label>
                        <input type="text" class="hm-input" id="adjust-reason" placeholder="e.g. stocktake correction"></div>
                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn hm-btn--secondary hm-modal-close">Cancel</button>
                    <button class="hm-btn hm-btn--primary" id="adjust-save">Update Qty</button>
                </div>
            </div>
        </div>

        <script>
        (function($){
        var _hm = window._hm || {ajax:'<?php echo admin_url("admin-ajax.php"); ?>',nonce:'<?php echo esc_js($nonce); ?>'};
        var currentTab = 'hearing_aids';
        var clinicCache = [], mfrCache = [];

        function esc(s){ return $('<span>').text(s||'').html(); }

        // ── Bootstrap ──────────────────────────────────────────────
        function boot(){
            loadClinics();
            loadManufacturers();
            loadTab('hearing_aids');
        }

        function loadClinics(){
            $.post(_hm.ajax,{action:'hm_get_clinics',nonce:_hm.nonce},function(r){
                if(!r||!r.success) return;
                clinicCache = r.data||[];
                var opts='<option value="">All clinics</option>';
                var mOpts='<option value="">— Select —</option>';
                clinicCache.forEach(function(c){
                    var cid = c._ID || c.id;
                    opts+='<option value="'+cid+'">'+esc(c.name||c.clinic_name)+'</option>';
                    mOpts+='<option value="'+cid+'">'+esc(c.name||c.clinic_name)+'</option>';
                });
                $('#hm-stock-clinic').html(opts);
                $('#transfer-clinic,#add-clinic').html(mOpts);
            });
        }
        function loadManufacturers(){
            $.post(_hm.ajax,{action:'hm_get_manufacturers',nonce:_hm.nonce},function(r){
                if(!r||!r.success) return;
                mfrCache = r.data||[];
                var opts='<option value="">All manufacturers</option>';
                var mOpts='<option value="">— Select —</option>';
                mfrCache.forEach(function(m){
                    var mid = m._ID || m.id;
                    opts+='<option value="'+mid+'">'+esc(m.name)+'</option>';
                    mOpts+='<option value="'+mid+'">'+esc(m.name)+'</option>';
                });
                $('#hm-stock-mfr').html(opts);
                $('#add-manufacturer').html(mOpts);
            });
        }

        // ── Tab switching ──────────────────────────────────────────
        $(document).on('click','.hm-stock-tab',function(){
            var tab=$(this).data('tab');
            if(tab===currentTab) return;
            $('.hm-stock-tab').removeClass('active');
            $(this).addClass('active');
            currentTab=tab;
            loadTab(tab);
        });

        function loadTab(tab){
            if(tab==='hearing_aids') loadHearingAids();
            else if(tab==='consumables') loadConsumables();
            else loadMovements();

            // Show/hide filters
            if(tab==='movements'){
                $('#hm-stock-filters').hide();
            } else {
                $('#hm-stock-filters').show();
                if(tab==='hearing_aids'){
                    $('#hm-stock-status').show();
                } else {
                    $('#hm-stock-status').hide();
                }
            }
        }

        // ── Filter events ──────────────────────────────────────────
        $(document).on('change','#hm-stock-clinic,#hm-stock-mfr,#hm-stock-status',function(){ loadTab(currentTab); });
        $(document).on('input','#hm-stock-search',function(){ loadTab(currentTab); });

        // ── TAB 1: Hearing Aids ────────────────────────────────────
        function loadHearingAids(){
            var params = {
                action:'hm_stock_hearing_aids', nonce:_hm.nonce,
                clinic_id:$('#hm-stock-clinic').val(),
                manufacturer_id:$('#hm-stock-mfr').val(),
                status:$('#hm-stock-status').val(),
                search:$('#hm-stock-search').val()
            };
            $.post(_hm.ajax, params, function(r){
                if(!r||!r.success){ $('#hm-stock-table').html('<div class="hm-empty"><div class="hm-empty-text">Failed to load</div></div>'); return; }
                var rows = r.data.rows||[];
                var stats = r.data.stats||{};
                $('#ha-count').text(stats.total||0);
                renderHAStats(stats);
                renderHATable(rows);
            });
        }
        function renderHAStats(s){
            var h='<div class="hm-stat"><div class="hm-stat-val">'+(s.total||0)+'</div><div class="hm-stat-label">Total Units</div></div>'+
                  '<div class="hm-stat"><div class="hm-stat-val">'+(s.available||0)+'</div><div class="hm-stat-label">Available</div></div>'+
                  '<div class="hm-stat"><div class="hm-stat-val">'+(s.reserved||0)+'</div><div class="hm-stat-label">Reserved</div></div>'+
                  '<div class="hm-stat"><div class="hm-stat-val">'+(s.fitted||0)+'</div><div class="hm-stat-label">Fitted</div></div>';
            $('#hm-stock-stats').html(h);
        }
        function renderHATable(rows){
            if(!rows.length){ $('#hm-stock-table').html('<div class="hm-empty"><div class="hm-empty-text">No hearing aids match filters</div></div>'); return; }
            var h='<table class="hm-table"><thead><tr><th>Manufacturer</th><th>Model</th><th>Style</th><th>Tech Level</th><th>Serial #</th><th>Clinic</th><th>Status</th><th></th></tr></thead><tbody>';
            rows.forEach(function(x){
                var sc = x.status==='Available'?'hm-badge--green':x.status==='Reserved'?'hm-badge--amber':x.status==='Fitted'?'hm-badge--blue':x.status==='Returned'?'hm-badge--red':'hm-badge--grey';
                var canAct = (x.status==='Available'||x.status==='Returned');
                h+='<tr>'+
                    '<td>'+esc(x.manufacturer_name)+'</td>'+
                    '<td style="font-weight:500">'+esc(x.model_name)+'</td>'+
                    '<td>'+esc(x.style)+'</td>'+
                    '<td>'+esc(x.technology_level)+'</td>'+
                    '<td><span class="hm-serial">'+esc(x.serial_number||'—')+'</span></td>'+
                    '<td>'+esc(x.clinic_name)+'</td>'+
                    '<td><span class="hm-badge hm-badge--sm '+sc+'">'+esc(x.status)+'</span></td>'+
                    '<td style="display:flex;gap:4px">'+
                        (canAct?'<button class="hm-btn hm-btn--secondary hm-btn--sm hm-stock-transfer" data-id="'+x._ID+'">Transfer</button>':'')+
                    '</td></tr>';
            });
            h+='</tbody></table>';
            $('#hm-stock-table').html(h);
        }

        // ── TAB 2: Consumables ─────────────────────────────────────
        function loadConsumables(){
            var params = {
                action:'hm_stock_consumables', nonce:_hm.nonce,
                clinic_id:$('#hm-stock-clinic').val(),
                manufacturer_id:$('#hm-stock-mfr').val(),
                search:$('#hm-stock-search').val()
            };
            $.post(_hm.ajax, params, function(r){
                if(!r||!r.success){ $('#hm-stock-table').html('<div class="hm-empty"><div class="hm-empty-text">Failed to load</div></div>'); return; }
                var rows = r.data.rows||[];
                var stats = r.data.stats||{};
                $('#cons-count').text(stats.total||0);
                renderConsStats(stats);
                renderConsTable(rows);
            });
        }
        function renderConsStats(s){
            var h='<div class="hm-stat"><div class="hm-stat-val">'+(s.total||0)+'</div><div class="hm-stat-label">Total Lines</div></div>'+
                  '<div class="hm-stat"><div class="hm-stat-val">'+(s.total_qty||0)+'</div><div class="hm-stat-label">Total Units</div></div>';
            if(s.low_stock>0) h+='<div class="hm-stat"><div class="hm-stat-val hm-low-stock">'+s.low_stock+'</div><div class="hm-stat-label">Low Stock</div></div>';
            $('#hm-stock-stats').html(h);
        }
        function renderConsTable(rows){
            if(!rows.length){ $('#hm-stock-table').html('<div class="hm-empty"><div class="hm-empty-text">No consumables match filters</div></div>'); return; }
            var h='<table class="hm-table"><thead><tr><th>Manufacturer</th><th>Product</th><th>Specification</th><th>Clinic</th><th style="text-align:center">Qty</th><th></th></tr></thead><tbody>';
            rows.forEach(function(x){
                var low = parseInt(x.quantity||0) <= 5;
                h+='<tr>'+
                    '<td>'+esc(x.manufacturer_name)+'</td>'+
                    '<td style="font-weight:500">'+esc(x.model_name)+'</td>'+
                    '<td>'+esc(x.specification)+'</td>'+
                    '<td>'+esc(x.clinic_name)+'</td>'+
                    '<td style="text-align:center">'+(low?'<span class="hm-low-stock">'+esc(x.quantity)+'</span>':esc(x.quantity))+'</td>'+
                    '<td style="display:flex;gap:4px">'+
                        '<button class="hm-btn hm-btn--secondary hm-btn--sm hm-stock-adjust" data-id="'+x._ID+'" data-qty="'+esc(x.quantity)+'" data-name="'+esc(x.model_name)+'">Adjust Qty</button>'+
                        '<button class="hm-btn hm-btn--secondary hm-btn--sm hm-stock-transfer" data-id="'+x._ID+'">Transfer</button>'+
                    '</td></tr>';
            });
            h+='</tbody></table>';
            $('#hm-stock-table').html(h);
        }

        // ── TAB 3: Movements Log ──────────────────────────────────
        function loadMovements(){
            $.post(_hm.ajax,{action:'hm_stock_movements',nonce:_hm.nonce},function(r){
                if(!r||!r.success){ $('#hm-stock-table').html('<div class="hm-empty"><div class="hm-empty-text">Failed to load</div></div>'); return; }
                var rows=r.data||[];
                $('#hm-stock-stats').html('');
                if(!rows.length){ $('#hm-stock-table').html('<div class="hm-empty"><div class="hm-empty-text">No movements recorded yet</div></div>'); return; }
                var h='<table class="hm-table"><thead><tr><th>Date</th><th>Type</th><th>Item</th><th>From</th><th>To</th><th>Qty</th><th>By</th><th>Notes</th></tr></thead><tbody>';
                rows.forEach(function(x){
                    var tc=x.movement_type==='transfer'?'hm-badge--blue':x.movement_type==='fitted'?'hm-badge--green':x.movement_type==='returned'?'hm-badge--red':x.movement_type==='adjustment'?'hm-badge--amber':'hm-badge--grey';
                    h+='<tr>'+
                        '<td>'+esc(x.created_at_fmt)+'</td>'+
                        '<td><span class="hm-badge hm-badge--sm '+tc+'">'+esc(x.movement_type)+'</span></td>'+
                        '<td>'+esc(x.item_name)+'</td>'+
                        '<td>'+esc(x.from_clinic)+'</td>'+
                        '<td>'+esc(x.to_clinic)+'</td>'+
                        '<td style="text-align:center">'+esc(x.quantity)+'</td>'+
                        '<td>'+esc(x.created_by)+'</td>'+
                        '<td>'+esc(x.notes)+'</td></tr>';
                });
                h+='</tbody></table>';
                $('#hm-stock-table').html(h);
            });
        }

        // ── Transfer modal ─────────────────────────────────────────
        $(document).on('click','.hm-stock-transfer',function(){
            var id=$(this).data('id');
            $('#transfer-stock-id').val(id);
            $('#transfer-notes').val('');
            $('#hm-transfer-modal').fadeIn(150);
        });
        $('#transfer-save').on('click',function(){
            var btn=$(this), id=$('#transfer-stock-id').val(), clinic=$('#transfer-clinic').val();
            if(!clinic){ alert('Select a clinic'); return; }
            btn.prop('disabled',true).text('Transferring…');
            $.post(_hm.ajax,{action:'hm_stock_transfer',nonce:_hm.nonce,stock_id:id,to_clinic_id:clinic,notes:$('#transfer-notes').val()},function(r){
                btn.prop('disabled',false).text('Transfer');
                $('#hm-transfer-modal').fadeOut(150);
                if(r.success) loadTab(currentTab); else alert(r.data?.message||r.data||'Transfer failed');
            });
        });

        // ── Add Stock modal ────────────────────────────────────────
        $('#hm-stock-add-btn').on('click',function(){
            $('#add-category').val('hearing_aid').trigger('change');
            $('#add-clinic,#add-manufacturer').val('');
            $('#add-model,#add-style,#add-serial,#add-notes').val('');
            $('#add-tech-level').val('');
            $('#add-qty').val(1);
            $('#hm-add-stock-modal').fadeIn(150);
        });
        $(document).on('change','#add-category',function(){
            if($(this).val()==='hearing_aid'){
                $('#add-serial-row').show(); $('#add-qty-row').hide();
            } else {
                $('#add-serial-row').hide(); $('#add-qty-row').show();
            }
        });
        $('#add-stock-save').on('click',function(){
            var btn=$(this);
            var cat=$('#add-category').val(), clinic=$('#add-clinic').val(), model=$.trim($('#add-model').val());
            if(!clinic||!model){ alert('Clinic and model/product name are required'); return; }
            btn.prop('disabled',true).text('Adding…');
            $.post(_hm.ajax,{
                action:'hm_stock_add', nonce:_hm.nonce,
                item_category:cat, clinic_id:clinic,
                manufacturer_id:$('#add-manufacturer').val(),
                model_name:model, style:$('#add-style').val(),
                technology_level:$('#add-tech-level').val(),
                serial_number:$('#add-serial').val(),
                quantity:$('#add-qty').val(),
                specification:$('#add-notes').val()
            },function(r){
                btn.prop('disabled',false).text('Add Stock');
                $('#hm-add-stock-modal').fadeOut(150);
                if(r.success) loadTab(currentTab); else alert(r.data?.message||r.data||'Failed');
            });
        });

        // ── Adjust Qty modal (consumables) ─────────────────────────
        $(document).on('click','.hm-stock-adjust',function(){
            var id=$(this).data('id'), qty=$(this).data('qty'), name=$(this).data('name');
            $('#adjust-stock-id').val(id);
            $('#adjust-qty').val(qty);
            $('#adjust-reason').val('');
            $('#adjust-current').text('Current qty for "'+name+'": '+qty);
            $('#hm-adjust-modal').fadeIn(150);
        });
        $('#adjust-save').on('click',function(){
            var btn=$(this), id=$('#adjust-stock-id').val(), qty=$('#adjust-qty').val(), reason=$('#adjust-reason').val();
            btn.prop('disabled',true).text('Updating…');
            $.post(_hm.ajax,{action:'hm_stock_adjust_qty',nonce:_hm.nonce,stock_id:id,new_quantity:qty,reason:reason},function(r){
                btn.prop('disabled',false).text('Update Qty');
                $('#hm-adjust-modal').fadeOut(150);
                if(r.success) loadTab(currentTab); else alert(r.data?.message||r.data||'Failed');
            });
        });

        // ── Modal close ────────────────────────────────────────────
        $(document).on('click','.hm-modal-close',function(){ $(this).closest('.hm-modal-bg').fadeOut(150); });
        $(document).on('click','.hm-modal-bg',function(e){ if(e.target===this) $(this).fadeOut(150); });

        boot();
        })(jQuery);
        </script>
        <?php
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX: Load Hearing Aids (serial-tracked)
    // ═══════════════════════════════════════════════════════════════════════

    public static function ajax_load_hearing_aids() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        self::ensure_tables();
        $db = HearMed_DB::instance();

        $where = [ "s.item_category = 'hearing_aid'" ];
        $params = [];
        $i = 1;

        $clinic = intval( $_POST['clinic_id'] ?? 0 );
        if ( $clinic ) { $where[] = "s.clinic_id = \${$i}"; $params[] = $clinic; $i++; }

        $mfr = intval( $_POST['manufacturer_id'] ?? 0 );
        if ( $mfr ) { $where[] = "s.manufacturer_id = \${$i}"; $params[] = $mfr; $i++; }

        $status = sanitize_text_field( $_POST['status'] ?? '' );
        if ( $status ) { $where[] = "s.status = \${$i}"; $params[] = $status; $i++; }

        $search = sanitize_text_field( $_POST['search'] ?? '' );
        if ( $search ) {
            $where[] = "(s.serial_number ILIKE \${$i} OR s.model_name ILIKE \${$i} OR m.name ILIKE \${$i})";
            $params[] = '%' . $search . '%';
            $i++;
        }

        $w = implode( ' AND ', $where );

        $rows = $db->get_results(
            "SELECT s.id AS \"_ID\", s.serial_number, s.model_name, s.style,
                    s.technology_level, s.status, s.quantity,
                    COALESCE(m.name, '') AS manufacturer_name,
                    COALESCE(c.clinic_name, 'Unassigned') AS clinic_name
             FROM hearmed_reference.inventory_stock s
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = s.manufacturer_id
             LEFT JOIN hearmed_reference.clinics c ON c.id = s.clinic_id
             WHERE {$w}
             ORDER BY m.name, s.model_name, s.serial_number",
            $params
        );

        // Stats
        $stats_sql = "SELECT
                        COUNT(*) AS total,
                        COUNT(*) FILTER (WHERE s.status = 'Available') AS available,
                        COUNT(*) FILTER (WHERE s.status = 'Reserved')  AS reserved,
                        COUNT(*) FILTER (WHERE s.status = 'Fitted')    AS fitted
                      FROM hearmed_reference.inventory_stock s
                      WHERE s.item_category = 'hearing_aid'";
        $stats = $db->get_row( $stats_sql );

        wp_send_json_success([
            'rows'  => $rows ?: [],
            'stats' => [
                'total'     => (int) ( $stats->total ?? 0 ),
                'available' => (int) ( $stats->available ?? 0 ),
                'reserved'  => (int) ( $stats->reserved ?? 0 ),
                'fitted'    => (int) ( $stats->fitted ?? 0 ),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX: Load Consumables (quantity-tracked)
    // ═══════════════════════════════════════════════════════════════════════

    public static function ajax_load_consumables() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        self::ensure_tables();
        $db = HearMed_DB::instance();

        $where = [ "s.item_category = 'consumable'" ];
        $params = [];
        $i = 1;

        $clinic = intval( $_POST['clinic_id'] ?? 0 );
        if ( $clinic ) { $where[] = "s.clinic_id = \${$i}"; $params[] = $clinic; $i++; }

        $mfr = intval( $_POST['manufacturer_id'] ?? 0 );
        if ( $mfr ) { $where[] = "s.manufacturer_id = \${$i}"; $params[] = $mfr; $i++; }

        $search = sanitize_text_field( $_POST['search'] ?? '' );
        if ( $search ) {
            $where[] = "(s.model_name ILIKE \${$i} OR m.name ILIKE \${$i})";
            $params[] = '%' . $search . '%';
            $i++;
        }

        $w = implode( ' AND ', $where );

        $rows = $db->get_results(
            "SELECT s.id AS \"_ID\", s.model_name, s.specification, s.quantity,
                    COALESCE(m.name, '') AS manufacturer_name,
                    COALESCE(c.clinic_name, 'Unassigned') AS clinic_name
             FROM hearmed_reference.inventory_stock s
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = s.manufacturer_id
             LEFT JOIN hearmed_reference.clinics c ON c.id = s.clinic_id
             WHERE {$w}
             ORDER BY m.name, s.model_name",
            $params
        );

        $stats_sql = "SELECT COUNT(*) AS total,
                             SUM(s.quantity) AS total_qty,
                             COUNT(*) FILTER (WHERE s.quantity <= 5) AS low_stock
                      FROM hearmed_reference.inventory_stock s
                      WHERE s.item_category = 'consumable'";
        $stats = $db->get_row( $stats_sql );

        wp_send_json_success([
            'rows'  => $rows ?: [],
            'stats' => [
                'total'     => (int) ( $stats->total ?? 0 ),
                'total_qty' => (int) ( $stats->total_qty ?? 0 ),
                'low_stock' => (int) ( $stats->low_stock ?? 0 ),
            ],
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX: Load Movements Log
    // ═══════════════════════════════════════════════════════════════════════

    public static function ajax_load_movements() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        self::ensure_tables();
        $db = HearMed_DB::instance();

        $rows = $db->get_results(
            "SELECT sm.id, sm.movement_type, sm.quantity, sm.notes,
                    sm.created_by, TO_CHAR(sm.created_at, 'DD/MM/YYYY HH24:MI') AS created_at_fmt,
                    COALESCE(s.model_name, 'Unknown') AS item_name,
                    COALESCE(fc.clinic_name, '—') AS from_clinic,
                    COALESCE(tc.clinic_name, '—') AS to_clinic
             FROM hearmed_reference.stock_movements sm
             LEFT JOIN hearmed_reference.inventory_stock s ON s.id = sm.stock_id
             LEFT JOIN hearmed_reference.clinics fc ON fc.id = sm.from_clinic_id
             LEFT JOIN hearmed_reference.clinics tc ON tc.id = sm.to_clinic_id
             ORDER BY sm.created_at DESC
             LIMIT 200"
        );

        wp_send_json_success( $rows ?: [] );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX: Transfer stock between clinics
    // ═══════════════════════════════════════════════════════════════════════

    public static function ajax_transfer() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        self::ensure_tables();

        $stock_id  = intval( $_POST['stock_id'] ?? 0 );
        $to_clinic = intval( $_POST['to_clinic_id'] ?? 0 );
        $notes     = sanitize_text_field( $_POST['notes'] ?? '' );

        if ( ! $stock_id || ! $to_clinic ) wp_send_json_error( 'Missing fields' );

        $db   = HearMed_DB::instance();
        $user = HearMed_Auth::current_user();

        $stock = $db->get_row(
            "SELECT * FROM hearmed_reference.inventory_stock WHERE id = $1",
            [ $stock_id ]
        );
        if ( ! $stock ) wp_send_json_error( 'Stock not found' );

        $from_clinic = $stock->clinic_id;

        HearMed_DB::begin_transaction();
        try {
            $db->query(
                "UPDATE hearmed_reference.inventory_stock SET clinic_id = $1 WHERE id = $2",
                [ $to_clinic, $stock_id ]
            );

            $db->insert( 'hearmed_reference.stock_movements', [
                'stock_id'       => $stock_id,
                'movement_type'  => 'transfer',
                'from_clinic_id' => $from_clinic,
                'to_clinic_id'   => $to_clinic,
                'quantity'       => intval( $stock->quantity ?? 1 ),
                'notes'          => $notes,
                'created_by'     => ( $user->first_name ?? '' ) . ' ' . ( $user->last_name ?? '' ),
                'created_at'     => date( 'Y-m-d H:i:s' ),
            ] );

            HearMed_DB::commit();
            wp_send_json_success( [ 'message' => 'Transferred successfully' ] );
        } catch ( \Exception $e ) {
            HearMed_DB::rollback();
            wp_send_json_error( 'Transfer failed: ' . $e->getMessage() );
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX: Add new stock item
    // ═══════════════════════════════════════════════════════════════════════

    public static function ajax_add_stock() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        self::ensure_tables();

        $category   = sanitize_key( $_POST['item_category'] ?? 'hearing_aid' );
        $clinic_id  = intval( $_POST['clinic_id'] ?? 0 );
        $mfr_id     = intval( $_POST['manufacturer_id'] ?? 0 ) ?: null;
        $model      = sanitize_text_field( $_POST['model_name'] ?? '' );
        $style      = sanitize_text_field( $_POST['style'] ?? '' );
        $tech       = sanitize_text_field( $_POST['technology_level'] ?? '' );
        $serial     = sanitize_text_field( $_POST['serial_number'] ?? '' );
        $qty        = intval( $_POST['quantity'] ?? 1 );
        $spec       = sanitize_text_field( $_POST['specification'] ?? '' );

        if ( ! $clinic_id || ! $model ) wp_send_json_error( 'Clinic and model are required' );

        // Serial uniqueness check for hearing aids
        if ( $category === 'hearing_aid' && $serial ) {
            $dup = HearMed_DB::instance()->get_var(
                "SELECT id FROM hearmed_reference.inventory_stock WHERE serial_number = $1",
                [ $serial ]
            );
            if ( $dup ) wp_send_json_error( 'Serial number "' . $serial . '" already exists in stock.' );
        }

        $user = HearMed_Auth::current_user();
        $db   = HearMed_DB::instance();

        $data = [
            'item_category'    => $category,
            'clinic_id'        => $clinic_id,
            'manufacturer_id'  => $mfr_id,
            'model_name'       => $model,
            'style'            => $style ?: null,
            'technology_level' => $tech ?: null,
            'serial_number'    => ( $category === 'hearing_aid' && $serial ) ? $serial : null,
            'specification'    => $spec ?: null,
            'quantity'         => ( $category === 'consumable' ) ? max( 1, $qty ) : 1,
            'status'           => 'Available',
        ];

        $id = $db->insert( 'hearmed_reference.inventory_stock', $data );
        if ( ! $id ) wp_send_json_error( 'Insert failed' );

        // Log movement
        $db->insert( 'hearmed_reference.stock_movements', [
            'stock_id'       => $id,
            'movement_type'  => 'added',
            'to_clinic_id'   => $clinic_id,
            'quantity'       => $data['quantity'],
            'notes'          => 'Initial stock entry',
            'created_by'     => ( $user->first_name ?? '' ) . ' ' . ( $user->last_name ?? '' ),
            'created_at'     => date( 'Y-m-d H:i:s' ),
        ] );

        wp_send_json_success( [ 'message' => 'Stock added', 'id' => $id ] );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX: Adjust quantity (consumables)
    // ═══════════════════════════════════════════════════════════════════════

    public static function ajax_adjust_quantity() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        self::ensure_tables();

        $stock_id = intval( $_POST['stock_id'] ?? 0 );
        $new_qty  = intval( $_POST['new_quantity'] ?? 0 );
        $reason   = sanitize_text_field( $_POST['reason'] ?? '' );

        if ( ! $stock_id ) wp_send_json_error( 'Missing stock ID' );
        if ( $new_qty < 0 ) wp_send_json_error( 'Quantity cannot be negative' );

        $db   = HearMed_DB::instance();
        $user = HearMed_Auth::current_user();

        $stock = $db->get_row(
            "SELECT * FROM hearmed_reference.inventory_stock WHERE id = $1",
            [ $stock_id ]
        );
        if ( ! $stock ) wp_send_json_error( 'Stock not found' );

        $old_qty = intval( $stock->quantity ?? 0 );
        $diff    = $new_qty - $old_qty;

        $db->query(
            "UPDATE hearmed_reference.inventory_stock SET quantity = $1 WHERE id = $2",
            [ $new_qty, $stock_id ]
        );

        $db->insert( 'hearmed_reference.stock_movements', [
            'stock_id'       => $stock_id,
            'movement_type'  => 'adjustment',
            'quantity'       => $diff,
            'notes'          => $reason ?: ( 'Adjusted from ' . $old_qty . ' to ' . $new_qty ),
            'created_by'     => ( $user->first_name ?? '' ) . ' ' . ( $user->last_name ?? '' ),
            'created_at'     => date( 'Y-m-d H:i:s' ),
        ] );

        wp_send_json_success( [ 'message' => 'Quantity updated' ] );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX: Reserve stock for a patient
    // ═══════════════════════════════════════════════════════════════════════

    public static function ajax_reserve() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        self::ensure_tables();

        $stock_id   = intval( $_POST['stock_id'] ?? 0 );
        $patient_id = intval( $_POST['patient_id'] ?? 0 );

        if ( ! $stock_id || ! $patient_id ) wp_send_json_error( 'Missing fields' );

        $db   = HearMed_DB::instance();
        $user = HearMed_Auth::current_user();

        $db->query(
            "UPDATE hearmed_reference.inventory_stock SET status = 'Reserved', reserved_for_patient_id = $1 WHERE id = $2",
            [ $patient_id, $stock_id ]
        );

        $db->insert( 'hearmed_reference.stock_movements', [
            'stock_id'       => $stock_id,
            'movement_type'  => 'reserved',
            'quantity'       => 1,
            'notes'          => 'Reserved for patient #' . $patient_id,
            'created_by'     => ( $user->first_name ?? '' ) . ' ' . ( $user->last_name ?? '' ),
            'created_at'     => date( 'Y-m-d H:i:s' ),
        ] );

        wp_send_json_success( [ 'message' => 'Reserved' ] );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HELPER: Return stock from an order (called by mod-refunds.php)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Return stock items to inventory when a credit note (exchange/cancellation) is raised.
     *
     * @param int    $order_id   Original order ID
     * @param string $reason     Return reason (e.g. "Exchange — CN HMCN-2026-0012")
     * @param string $created_by Staff name
     */
    public static function return_stock_from_order( $order_id, $reason = '', $created_by = 'System' ) {
        $db = HearMed_DB::instance();

        // Find stock items that were fitted/reserved for this order
        $items = $db->get_results(
            "SELECT oi.item_id, oi.item_description, oi.ear_side
             FROM hearmed_core.order_items oi
             WHERE oi.order_id = $1 AND oi.item_type = 'product'",
            [ $order_id ]
        );

        if ( ! $items ) return;

        // Find matching inventory_stock rows that are fitted
        foreach ( $items as $item ) {
            // Try to match by serial or product on this order's patient
            $order = $db->get_row( "SELECT patient_id, clinic_id FROM hearmed_core.orders WHERE id = $1", [ $order_id ] );
            if ( ! $order ) continue;

            $stock_row = $db->get_row(
                "SELECT id FROM hearmed_reference.inventory_stock
                 WHERE fitted_to_patient_id = $1
                   AND status = 'Fitted'
                 ORDER BY id DESC LIMIT 1",
                [ $order->patient_id ]
            );

            if ( $stock_row ) {
                $db->query(
                    "UPDATE hearmed_reference.inventory_stock
                     SET status = 'Returned', fitted_to_patient_id = NULL, return_reason = $1
                     WHERE id = $2",
                    [ $reason, $stock_row->id ]
                );

                $db->insert( 'hearmed_reference.stock_movements', [
                    'stock_id'       => $stock_row->id,
                    'movement_type'  => 'returned',
                    'to_clinic_id'   => $order->clinic_id,
                    'quantity'       => 1,
                    'notes'          => $reason,
                    'created_by'     => $created_by,
                    'created_at'     => date( 'Y-m-d H:i:s' ),
                ] );
            }
        }
    }
}

HearMed_Stock::init();
