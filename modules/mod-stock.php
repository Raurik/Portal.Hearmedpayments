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

// Standalone render function used by router module loader.
function hm_stock_render() {
    if ( ! PortalAuth::is_logged_in() ) {
        return;
    }
    echo HearMed_Stock::render();
}

class HearMed_Stock {

    public static function init() {
        add_shortcode( 'hearmed_stock', [ __CLASS__, 'render' ] );

        // AJAX endpoints (also registered centrally in class-hearmed-ajax.php)
        add_action( 'wp_ajax_hm_stock_hearing_aids',  [ __CLASS__, 'ajax_load_hearing_aids' ] );
        add_action( 'wp_ajax_hm_stock_consumables',   [ __CLASS__, 'ajax_load_consumables' ] );
        add_action( 'wp_ajax_hm_stock_movements',     [ __CLASS__, 'ajax_load_movements' ] );
        add_action( 'wp_ajax_hm_stock_category',      [ __CLASS__, 'ajax_load_category' ] );
        add_action( 'wp_ajax_hm_stock_transfer',      [ __CLASS__, 'ajax_transfer' ] );
        add_action( 'wp_ajax_hm_stock_add',           [ __CLASS__, 'ajax_add_stock' ] );
        add_action( 'wp_ajax_hm_stock_import_csv',    [ __CLASS__, 'ajax_import_csv' ] );
        add_action( 'wp_ajax_hm_stock_adjust_qty',    [ __CLASS__, 'ajax_adjust_quantity' ] );
        add_action( 'wp_ajax_hm_stock_reserve',       [ __CLASS__, 'ajax_reserve' ] );
        add_action( 'wp_ajax_hm_stock_get_products', [ __CLASS__, 'ajax_get_products' ] );
        add_action( 'wp_ajax_hm_stock_request_from_clinic', [ __CLASS__, 'ajax_request_from_clinic' ] );
        add_action( 'wp_ajax_hm_stock_use_from_stock',      [ __CLASS__, 'ajax_use_from_stock' ] );
        add_action( 'wp_ajax_hm_stock_return_to_manufacturer', [ __CLASS__, 'ajax_return_to_manufacturer' ] );
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
        .hm-stock-actions{display:flex;gap:8px}
        .hm-stock-link-btn{background:transparent !important;border:none !important;color:var(--hm-teal,#0BB4C4) !important;box-shadow:none !important;padding:4px 6px !important;font-weight:700;font-size:13px;cursor:pointer}
        .hm-stock-link-btn:hover{text-decoration:underline;color:var(--hm-navy,#151B33) !important;background:transparent !important}
        .hm-stock-top-row{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:12px}
        .hm-stock-top-row .hm-stats{margin:0;flex:1;min-width:0}
        .hm-stock-filters{display:flex;gap:6px;flex-wrap:nowrap;align-items:center;margin:0;flex:0 0 auto}
        .hm-stock-filters select,.hm-stock-filters input{height:32px;font-size:12px;padding:4px 8px;border:1px solid var(--hm-border,#e2e8f0);border-radius:6px;font-family:var(--hm-font,'Source Sans 3',sans-serif)}
        #hm-stock-clinic,#hm-stock-mfr{width:140px;min-width:140px}
        #hm-stock-status{width:120px;min-width:120px}
        #hm-stock-search{width:170px}
        @media (max-width: 1200px){
            .hm-stock-top-row{flex-direction:column;align-items:stretch}
            .hm-stock-filters{flex-wrap:wrap}
        }
        .hm-low-stock{background:var(--hm-error-light,#fef2f2);color:var(--hm-error,#dc2626);font-weight:600;padding:2px 8px;border-radius:4px;font-size:12px}
        .hm-serial{font-family:'SF Mono',monospace;font-size:12px;letter-spacing:.5px;color:var(--hm-navy,#151B33)}
        </style>

        <div id="hm-stock-app" class="hm-content">
            <div class="hm-page-header">
                <h1 class="hm-page-title">Stock &amp; Inventory</h1>
                <div class="hm-stock-actions">
                    <button class="hm-btn hm-stock-link-btn" id="hm-stock-template-btn">+ CSV Template</button>
                    <button class="hm-btn hm-stock-link-btn" id="hm-stock-import-btn">+ Import Stock</button>
                    <button class="hm-btn hm-stock-link-btn" id="hm-stock-add-btn">+ Add Stock</button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="hm-stock-tabs" id="hm-stock-tabs">
                <div class="hm-stock-tab active" data-tab="hearing_aids">Hearing Aids <span class="pill" id="ha-count">0</span></div>
                <div class="hm-stock-tab" data-tab="consumables">Consumables <span class="pill" id="cons-count">0</span></div>
                <div class="hm-stock-tab" data-tab="domes_filters">Domes / Filters <span class="pill" id="dome-count">0</span></div>
                <div class="hm-stock-tab" data-tab="speakers">Speakers <span class="pill" id="speaker-count">0</span></div>
                <div class="hm-stock-tab" data-tab="chargers_accessories">Chargers / Accessories <span class="pill" id="charger-count">0</span></div>
                <div class="hm-stock-tab" data-tab="movements">Movements Log</div>
            </div>

            <div class="hm-stock-top-row">
                <!-- Stats -->
                <div class="hm-stats" id="hm-stock-stats"></div>

                <!-- Filters -->
                <div class="hm-stock-filters" id="hm-stock-filters">
                    <select id="hm-stock-clinic" class="hm-dd"><option value="">All clinics</option></select>
                    <select id="hm-stock-mfr" class="hm-dd"><option value="">All manufacturers</option></select>
                    <select id="hm-stock-status" class="hm-dd">
                        <option value="">All statuses</option>
                        <option value="Available">Available</option>
                        <option value="Reserved">Reserved</option>
                        <option value="Fitted">Fitted</option>
                        <option value="Returned">Returned</option>
                        <option value="Requested">Requested</option>
                    </select>
                    <input type="text" id="hm-stock-search" class="hm-inp" placeholder="Search serial / model…">
                </div>
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
                                <option value="dome_filter">Domes / Filters</option>
                                <option value="speaker">Speakers</option>
                                <option value="charger_accessory">Chargers / Accessories</option>
                            </select></div>
                        <div class="hm-form-group" style="flex:1"><label class="hm-label">Clinic *</label>
                            <select class="hm-input" id="add-clinic"><option value="">— Select —</option></select></div>
                    </div>
                    <div class="hm-form-row" style="display:flex;gap:12px">
                        <div class="hm-form-group" style="flex:1"><label class="hm-label">Manufacturer</label>
                            <select class="hm-input" id="add-manufacturer"><option value="">— Select —</option></select></div>
                        <div class="hm-form-group" style="flex:1"><label class="hm-label">Product *</label>
                            <select class="hm-input" id="add-product"><option value="">— Select manufacturer first —</option></select></div>
                    </div>
                    <div class="hm-form-row" style="display:flex;gap:12px">
                        <div class="hm-form-group" style="flex:1"><label class="hm-label">Style</label>
                            <input type="text" class="hm-input" id="add-style" readonly style="background:#f1f5f9" placeholder="Auto-filled from product"></div>
                        <div class="hm-form-group" style="flex:1"><label class="hm-label">Technology Level</label>
                            <input type="text" class="hm-input" id="add-tech-level" readonly style="background:#f1f5f9" placeholder="Auto-filled from product"></div>
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

        <div class="hm-modal-bg" id="hm-request-modal" style="display:none">
            <div class="hm-modal hm-modal--sm">
                <div class="hm-modal-hd"><h3>Request from Clinic</h3><button class="hm-close hm-modal-close">&times;</button></div>
                <div class="hm-modal-body">
                    <input type="hidden" id="request-stock-id">
                    <div class="hm-form-group"><label class="hm-label">Request to clinic *</label>
                        <select class="hm-input" id="request-to-clinic"><option value="">-- Select clinic --</option></select></div>
                    <div class="hm-form-group"><label class="hm-label">Notes</label>
                        <textarea class="hm-input" id="request-notes" rows="2"></textarea></div>
                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn hm-btn--secondary hm-modal-close">Cancel</button>
                    <button class="hm-btn hm-btn--primary" id="request-save">Send Request</button>
                </div>
            </div>
        </div>

        <div class="hm-modal-bg" id="hm-use-stock-modal" style="display:none">
            <div class="hm-modal hm-modal--sm">
                <div class="hm-modal-hd"><h3>Use from Stock</h3><button class="hm-close hm-modal-close">&times;</button></div>
                <div class="hm-modal-body">
                    <input type="hidden" id="use-stock-id">
                    <input type="hidden" id="use-patient-id">
                    <div class="hm-form-group"><label class="hm-label">Find patient *</label>
                        <input type="text" class="hm-input" id="use-patient-search" placeholder="Type patient name or number"></div>
                    <div id="use-patient-results" style="max-height:160px;overflow:auto;border:1px solid #e2e8f0;border-radius:8px;display:none"></div>
                    <div class="hm-form-group"><label class="hm-label">Selected patient</label>
                        <input type="text" class="hm-input" id="use-selected-patient" readonly></div>
                    <div class="hm-form-group"><label class="hm-label">Notes</label>
                        <textarea class="hm-input" id="use-notes" rows="2"></textarea></div>
                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn hm-btn--secondary hm-modal-close">Cancel</button>
                    <button class="hm-btn hm-btn--primary" id="use-stock-save">Use from Stock</button>
                </div>
            </div>
        </div>

        <div class="hm-modal-bg" id="hm-mfr-return-modal" style="display:none">
            <div class="hm-modal hm-modal--sm">
                <div class="hm-modal-hd"><h3>Return to Manufacturer</h3><button class="hm-close hm-modal-close">&times;</button></div>
                <div class="hm-modal-body">
                    <input type="hidden" id="mfr-stock-id">
                    <div id="mfr-docket-summary" style="font-size:13px;color:#334155;line-height:1.6;margin-bottom:10px"></div>
                    <div class="hm-form-group"><label class="hm-label">Return notes</label>
                        <textarea class="hm-input" id="mfr-return-notes" rows="2"></textarea></div>
                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn hm-btn--secondary hm-modal-close">Cancel</button>
                    <button class="hm-btn hm-btn--primary" id="mfr-return-save">Print Docket & Return</button>
                </div>
            </div>
        </div>

        <div class="hm-modal-bg" id="hm-stock-csv-modal" style="display:none">
            <div class="hm-modal hm-modal--sm">
                <div class="hm-modal-hd"><h3>Stock CSV Tools</h3><button class="hm-close hm-modal-close">&times;</button></div>
                <div class="hm-modal-body">
                    <div class="hm-form-group">
                        <label class="hm-label">Choose stock template</label>
                        <select class="hm-input" id="hm-stock-template-type">
                            <option value="hearing_aids">Hearing Aids</option>
                            <option value="consumables">Consumables</option>
                            <option value="domes_filters">Domes / Filters</option>
                            <option value="speakers">Speakers</option>
                            <option value="chargers_accessories">Chargers / Accessories</option>
                        </select>
                    </div>
                    <div class="hm-form-group" id="hm-stock-import-file-wrap" style="display:none">
                        <label class="hm-label">CSV file</label>
                        <input type="file" class="hm-input" id="hm-stock-import-file" accept=".csv,text/csv">
                        <small style="color:var(--hm-grey,#64748b)">CSV headers must match the selected template.</small>
                    </div>
                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn hm-btn--secondary hm-modal-close">Close</button>
                    <button class="hm-btn hm-btn--secondary" id="hm-stock-download-template">Download Template</button>
                    <button class="hm-btn hm-btn--primary" id="hm-stock-run-import">Import CSV</button>
                </div>
            </div>
        </div>

        <script>
        (function($){
        var _hm = window._hm || {ajax:'<?php echo admin_url("admin-ajax.php"); ?>',nonce:'<?php echo esc_js($nonce); ?>'};
        var currentTab = 'hearing_aids';
        var clinicCache = [], mfrCache = [];
        var categoryMap = {
            hearing_aids:{item_category:'hearing_aid',count:'#ha-count',label:'Hearing Aids'},
            consumables:{item_category:'consumable',count:'#cons-count',label:'Consumables'},
            domes_filters:{item_category:'dome_filter',count:'#dome-count',label:'Domes / Filters'},
            speakers:{item_category:'speaker',count:'#speaker-count',label:'Speakers'},
            chargers_accessories:{item_category:'charger_accessory',count:'#charger-count',label:'Chargers / Accessories'}
        };
        var csvTemplates = {
            hearing_aids:{
                filename:'stock_template_hearing_aids.csv',
                headers:['clinic_name','manufacturer_name','model_name','style','technology_level','serial_number','specification','status'],
                sample:['Dublin City','Phonak','Audeo Lumity 70','RIC','Advanced','PH123456','Demo device','Available']
            },
            consumables:{
                filename:'stock_template_consumables.csv',
                headers:['clinic_name','manufacturer_name','model_name','specification','quantity','status'],
                sample:['Dublin City','Oticon','Wax Guard Pack','10 pack',50,'Available']
            },
            domes_filters:{
                filename:'stock_template_domes_filters.csv',
                headers:['clinic_name','manufacturer_name','model_name','dome_type','dome_size','quantity','status'],
                sample:['Dublin City','Phonak','CeruShield','Open','M',30,'Available']
            },
            speakers:{
                filename:'stock_template_speakers.csv',
                headers:['clinic_name','manufacturer_name','model_name','speaker_power','speaker_length','quantity','status'],
                sample:['Dublin City','Phonak','Receiver 2xM','M','2',10,'Available']
            },
            chargers_accessories:{
                filename:'stock_template_chargers_accessories.csv',
                headers:['clinic_name','manufacturer_name','model_name','specification','quantity','status'],
                sample:['Dublin City','Phonak','Charger Case Go','Travel charger',8,'Available']
            }
        };

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
                $('#transfer-clinic,#add-clinic,#request-to-clinic').html(mOpts);
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
            else if(tab==='domes_filters' || tab==='speakers' || tab==='chargers_accessories') loadCategory(tab);
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
                        (canAct?'<button class="hm-btn hm-btn--secondary hm-btn--sm hm-stock-request" data-id="'+x._ID+'">Request from Clinic</button>':'')+
                        (canAct?'<button class="hm-btn hm-btn--secondary hm-btn--sm hm-stock-use" data-id="'+x._ID+'">Use from Stock</button>':'')+
                        (canAct?'<button class="hm-btn hm-btn--secondary hm-btn--sm hm-stock-transfer" data-id="'+x._ID+'">Transfer</button>':'')+
                        (canAct?'<button class="hm-btn hm-btn--secondary hm-btn--sm hm-stock-mfr-return" data-id="'+x._ID+'" data-mfr="'+esc(x.manufacturer_name)+'" data-model="'+esc(x.model_name)+'" data-serial="'+esc(x.serial_number||'')+'">Return to Manufacturer</button>':'')+
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

        function loadCategory(tab){
            var c = categoryMap[tab];
            if(!c) return;
            var params = {
                action:'hm_stock_category', nonce:_hm.nonce,
                item_category:c.item_category,
                clinic_id:$('#hm-stock-clinic').val(),
                manufacturer_id:$('#hm-stock-mfr').val(),
                search:$('#hm-stock-search').val()
            };
            $.post(_hm.ajax, params, function(r){
                if(!r||!r.success){ $('#hm-stock-table').html('<div class="hm-empty"><div class="hm-empty-text">Failed to load</div></div>'); return; }
                var rows = r.data.rows||[];
                var stats = r.data.stats||{};
                $(c.count).text(stats.total||0);
                var h='<div class="hm-stat"><div class="hm-stat-val">'+(stats.total||0)+'</div><div class="hm-stat-label">Total Lines</div></div>'+
                      '<div class="hm-stat"><div class="hm-stat-val">'+(stats.total_qty||0)+'</div><div class="hm-stat-label">Total Units</div></div>';
                $('#hm-stock-stats').html(h);
                renderCategoryTable(rows,c.label);
            });
        }

        function renderCategoryTable(rows,label){
            if(!rows.length){ $('#hm-stock-table').html('<div class="hm-empty"><div class="hm-empty-text">No '+esc(label)+' match filters</div></div>'); return; }
            var h='<table class="hm-table"><thead><tr><th>Manufacturer</th><th>Item</th><th>Style</th><th>Tech</th><th>Specification</th><th>Clinic</th><th style="text-align:center">Qty</th><th></th></tr></thead><tbody>';
            rows.forEach(function(x){
                var low = parseInt(x.quantity||0) <= 5;
                h+='<tr>'+
                    '<td>'+esc(x.manufacturer_name)+'</td>'+
                    '<td style="font-weight:500">'+esc(x.model_name)+'</td>'+
                    '<td>'+esc(x.style||'—')+'</td>'+
                    '<td>'+esc(x.technology_level||'—')+'</td>'+
                    '<td>'+esc(x.specification||'—')+'</td>'+
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

        // ── Request from clinic (hearing aids) ───────────────────
        $(document).on('click','.hm-stock-request',function(){
            $('#request-stock-id').val($(this).data('id'));
            $('#request-to-clinic').val('');
            $('#request-notes').val('');
            $('#hm-request-modal').fadeIn(150);
        });
        $('#request-save').on('click',function(){
            var btn=$(this), id=$('#request-stock-id').val(), toClinic=$('#request-to-clinic').val();
            if(!toClinic){ alert('Select a clinic'); return; }
            btn.prop('disabled',true).text('Sending…');
            $.post(_hm.ajax,{action:'hm_stock_request_from_clinic',nonce:_hm.nonce,stock_id:id,to_clinic_id:toClinic,notes:$('#request-notes').val()},function(r){
                btn.prop('disabled',false).text('Send Request');
                $('#hm-request-modal').fadeOut(150);
                if(r&&r.success) loadTab(currentTab); else alert(r.data?.message||r.data||'Request failed');
            });
        });

        // ── Use from stock (hearing aids) ────────────────────────
        var useSearchTimer = null;
        $(document).on('click','.hm-stock-use',function(){
            $('#use-stock-id').val($(this).data('id'));
            $('#use-patient-id').val('');
            $('#use-patient-search').val('');
            $('#use-selected-patient').val('');
            $('#use-notes').val('');
            $('#use-patient-results').hide().html('');
            $('#hm-use-stock-modal').fadeIn(150);
        });
        $('#use-patient-search').on('input',function(){
            var q=$.trim($(this).val()||'');
            clearTimeout(useSearchTimer);
            if(q.length<2){ $('#use-patient-results').hide().html(''); return; }
            useSearchTimer=setTimeout(function(){
                $.post(_hm.ajax,{action:'hm_search_patients',nonce:_hm.nonce,q:q},function(r){
                    if(!r||!r.success||!r.data||!r.data.length){ $('#use-patient-results').show().html('<div style="padding:8px 10px;color:#94a3b8;font-size:12px">No patients found</div>'); return; }
                    var h='';
                    r.data.forEach(function(p){
                        h+='<div class="hm-use-pick" data-id="'+p.id+'" data-label="'+esc(p.label||p.name||'')+'" style="padding:8px 10px;border-bottom:1px solid #f1f5f9;cursor:pointer">'+esc(p.label||p.name||'')+'</div>';
                    });
                    $('#use-patient-results').show().html(h);
                });
            },220);
        });
        $(document).on('click','.hm-use-pick',function(){
            $('#use-patient-id').val($(this).data('id'));
            $('#use-selected-patient').val($(this).data('label'));
            $('#use-patient-results').hide().html('');
        });
        $('#use-stock-save').on('click',function(){
            var btn=$(this), sid=$('#use-stock-id').val(), pid=$('#use-patient-id').val();
            if(!pid){ alert('Select a patient'); return; }
            btn.prop('disabled',true).text('Saving…');
            $.post(_hm.ajax,{action:'hm_stock_use_from_stock',nonce:_hm.nonce,stock_id:sid,patient_id:pid,notes:$('#use-notes').val()},function(r){
                btn.prop('disabled',false).text('Use from Stock');
                $('#hm-use-stock-modal').fadeOut(150);
                if(r&&r.success) loadTab(currentTab); else alert(r.data?.message||r.data||'Use from stock failed');
            });
        });

        // ── Return to manufacturer (hearing aids) ────────────────
        function printManufacturerDocket(mfr,model,serial,notes){
            var w = window.open('', '_blank');
            if(!w) return;
            var now = new Date();
            var dt = now.toLocaleDateString() + ' ' + now.toLocaleTimeString();
            var html = '<html><head><title>Manufacturer Return Docket</title><style>@page{size:A4 portrait;margin:0;}html,body{margin:0;padding:0;width:100%;background:#fff;font-family:Arial,sans-serif;color:#0f172a;-webkit-print-color-adjust:exact;print-color-adjust:exact}.sheet{width:210mm;min-height:297mm;margin:0 auto;padding:12mm;box-sizing:border-box}h1{font-size:20px;margin:0 0 12px}table{border-collapse:collapse;width:100%;margin-top:12px}td,th{border:1px solid #cbd5e1;padding:8px;text-align:left}small{color:#64748b}</style></head><body><div class="sheet">'+
                '<h1>Manufacturer Return Docket</h1><small>Generated: '+esc(dt)+'</small>'+
                '<table><tr><th>Manufacturer</th><td>'+esc(mfr||'')+'</td></tr><tr><th>Model</th><td>'+esc(model||'')+'</td></tr><tr><th>Serial</th><td>'+esc(serial||'N/A')+'</td></tr><tr><th>Notes</th><td>'+esc(notes||'')+'</td></tr></table>'+
                '</div></body></html>';
            w.document.open(); w.document.write(html); w.document.close();
            w.focus();
            setTimeout(function(){ w.print(); }, 150);
        }
        $(document).on('click','.hm-stock-mfr-return',function(){
            var $b=$(this);
            $('#mfr-stock-id').val($b.data('id'));
            $('#hm-mfr-return-modal').data('mfr',$b.data('mfr')||'').data('model',$b.data('model')||'').data('serial',$b.data('serial')||'');
            $('#mfr-return-notes').val('');
            $('#mfr-docket-summary').html('<strong>Manufacturer:</strong> '+esc($b.data('mfr')||'')+'<br><strong>Model:</strong> '+esc($b.data('model')||'')+'<br><strong>Serial:</strong> '+esc($b.data('serial')||'N/A'));
            $('#hm-mfr-return-modal').fadeIn(150);
        });
        $('#mfr-return-save').on('click',function(){
            var btn=$(this), sid=$('#mfr-stock-id').val(), notes=$('#mfr-return-notes').val();
            printManufacturerDocket($('#hm-mfr-return-modal').data('mfr'), $('#hm-mfr-return-modal').data('model'), $('#hm-mfr-return-modal').data('serial'), notes);
            btn.prop('disabled',true).text('Saving…');
            $.post(_hm.ajax,{action:'hm_stock_return_to_manufacturer',nonce:_hm.nonce,stock_id:sid,notes:notes},function(r){
                btn.prop('disabled',false).text('Print Docket & Return');
                $('#hm-mfr-return-modal').fadeOut(150);
                if(r&&r.success) loadTab(currentTab); else alert(r.data?.message||r.data||'Return to manufacturer failed');
            });
        });

        // ── Add Stock modal ────────────────────────────────────────
        var productCache = []; // cached product list for current manufacturer+category

        $('#hm-stock-add-btn').on('click',function(){
            $('#add-category').val('hearing_aid');
            $('#add-clinic,#add-manufacturer').val('');
            $('#add-product').html('<option value="">— Select manufacturer first —</option>');
            $('#add-style,#add-tech-level').val('');
            $('#add-serial').val(''); $('#add-notes').val('');
            $('#add-qty').val(1);
            productCache = [];
            // Show/hide serial vs qty
            $('#add-serial-row').show(); $('#add-qty-row').hide();
            $('#hm-add-stock-modal').fadeIn(150);
        });

        // Category change: toggle serial/qty rows AND reload products
        $(document).on('change','#add-category',function(){
            if($(this).val()==='hearing_aid'){
                $('#add-serial-row').show(); $('#add-qty-row').hide();
            } else {
                $('#add-serial-row').hide(); $('#add-qty-row').show();
            }
            // reset product selection
            $('#add-style,#add-tech-level').val('');
            loadProductsForStock();
        });

        // Manufacturer change: reload products
        $(document).on('change','#add-manufacturer',function(){
            $('#add-style,#add-tech-level').val('');
            loadProductsForStock();
        });

        // Product selected: auto-fill style + tech level
        $(document).on('change','#add-product',function(){
            var pid = $(this).val();
            if(!pid){ $('#add-style,#add-tech-level').val(''); return; }
            var p = null;
            for(var i=0;i<productCache.length;i++){
                if(String(productCache[i].id)==String(pid)){ p=productCache[i]; break; }
            }
            if(p){
                $('#add-style').val(p.style||'');
                $('#add-tech-level').val(p.tech_level||'');
            }
        });

        function loadProductsForStock(){
            var mfr = $('#add-manufacturer').val();
            var cat = $('#add-category').val();
            if(!mfr){
                $('#add-product').html('<option value="">— Select manufacturer first —</option>');
                productCache = [];
                return;
            }
            $('#add-product').html('<option value="">Loading…</option>');
            $.post(_hm.ajax,{
                action:'hm_stock_get_products', nonce:_hm.nonce,
                manufacturer_id:mfr, stock_category:cat
            },function(r){
                productCache = (r && r.success && r.data) ? r.data : [];
                var opts = '<option value="">— Select product —</option>';
                productCache.forEach(function(p){
                    var label = esc(p.product_name);
                    if(p.style) label += ' — ' + esc(p.style);
                    if(p.tech_level) label += ' (' + esc(p.tech_level) + ')';
                    opts += '<option value="'+p.id+'">'+label+'</option>';
                });
                if(!productCache.length) opts = '<option value="">No products found for this manufacturer</option>';
                $('#add-product').html(opts);
            });
        }

        $('#add-stock-save').on('click',function(){
            var btn=$(this);
            var cat=$('#add-category').val(), clinic=$('#add-clinic').val();
            var productId = $('#add-product').val();
            if(!clinic){ alert('Clinic is required'); return; }
            if(!productId){ alert('Please select a product'); return; }

            // Find product name from cache
            var model = '';
            for(var i=0;i<productCache.length;i++){
                if(String(productCache[i].id)==String(productId)){ model=productCache[i].product_name; break; }
            }

            btn.prop('disabled',true).text('Adding…');
            $.post(_hm.ajax,{
                action:'hm_stock_add', nonce:_hm.nonce,
                item_category:cat, clinic_id:clinic,
                manufacturer_id:$('#add-manufacturer').val(),
                product_id:productId,
                model_name:model, style:$('#add-style').val(),
                technology_level:$('#add-tech-level').val(),
                serial_number:$('#add-serial').val(),
                quantity:$('#add-qty').val(),
                specification:$('#add-notes').val()
            },function(r){
                btn.prop('disabled',false).text('Add Stock');
                if(r&&r.success){
                    $('#hm-add-stock-modal').fadeOut(150);
                    loadTab(currentTab);
                } else {
                    alert(r.data?.message||r.data||'Failed');
                }
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

        function toCsvLine(arr){
            return arr.map(function(v){
                var s=String(v==null?'':v);
                if(/[",\n]/.test(s)) s='"'+s.replace(/"/g,'""')+'"';
                return s;
            }).join(',');
        }

        function parseCsv(text){
            var lines=text.replace(/\r\n/g,'\n').replace(/\r/g,'\n').split('\n').filter(function(l){return $.trim(l).length>0;});
            if(!lines.length) return [];
            function parseLine(line){
                var out=[],cur='',inQ=false;
                for(var i=0;i<line.length;i++){
                    var ch=line.charAt(i),nx=line.charAt(i+1);
                    if(ch==='"'){
                        if(inQ&&nx==='"'){cur+='"';i++;}
                        else inQ=!inQ;
                    } else if(ch===','&&!inQ){ out.push(cur); cur=''; }
                    else cur+=ch;
                }
                out.push(cur);
                return out;
            }
            var headers=parseLine(lines[0]).map(function(h){return $.trim(h);});
            var rows=[];
            for(var r=1;r<lines.length;r++){
                var vals=parseLine(lines[r]);
                var row={};
                headers.forEach(function(h,idx){row[h]=vals[idx]!=null?$.trim(vals[idx]):'';});
                rows.push(row);
            }
            return rows;
        }

        function openCsvModal(forImport){
            $('#hm-stock-import-file').val('');
            $('#hm-stock-import-file-wrap').toggle(!!forImport);
            $('#hm-stock-run-import').toggle(!!forImport);
            $('#hm-stock-csv-modal').fadeIn(150);
        }

        $('#hm-stock-template-btn').on('click',function(){ openCsvModal(false); });
        $('#hm-stock-import-btn').on('click',function(){ openCsvModal(true); });

        $('#hm-stock-download-template').on('click',function(){
            var t=$('#hm-stock-template-type').val();
            var cfg=csvTemplates[t];
            if(!cfg) return;
            var csv=toCsvLine(cfg.headers)+'\n'+toCsvLine(cfg.sample)+'\n';
            var blob=new Blob([csv],{type:'text/csv;charset=utf-8;'});
            var url=URL.createObjectURL(blob);
            var a=document.createElement('a');
            a.href=url; a.download=cfg.filename; document.body.appendChild(a); a.click();
            setTimeout(function(){URL.revokeObjectURL(url);a.remove();},0);
        });

        $('#hm-stock-run-import').on('click',function(){
            var btn=$(this);
            var templateType=$('#hm-stock-template-type').val();
            var file=$('#hm-stock-import-file')[0].files[0];
            if(!file){ alert('Please choose a CSV file'); return; }
            var reader=new FileReader();
            btn.prop('disabled',true).text('Importing…');
            reader.onload=function(){
                try{
                    var rows=parseCsv(String(reader.result||''));
                    if(!rows.length){ btn.prop('disabled',false).text('Import CSV'); alert('CSV has no rows'); return; }
                    $.post(_hm.ajax,{action:'hm_stock_import_csv',nonce:_hm.nonce,template_type:templateType,rows_json:JSON.stringify(rows)},function(r){
                        btn.prop('disabled',false).text('Import CSV');
                        if(r&&r.success){
                            $('#hm-stock-csv-modal').fadeOut(150);
                            alert('Imported '+(r.data.imported||0)+' rows'+((r.data.failed||0)>0?' ('+r.data.failed+' failed)':''));
                            loadTab(currentTab);
                        } else {
                            var msg=(r&&r.data&&r.data.message)?r.data.message:(r&&r.data?r.data:'Import failed');
                            alert(msg);
                        }
                    });
                } catch(e){
                    btn.prop('disabled',false).text('Import CSV');
                    alert('Unable to parse CSV');
                }
            };
            reader.readAsText(file);
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

        $where = [ "s.item_category = 'hearing_aid'", "COALESCE(s.status,'Available') <> 'Inactive'" ];
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
                    s.clinic_id, s.manufacturer_id,
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
                                            WHERE s.item_category = 'hearing_aid'
                                                AND COALESCE(s.status,'Available') <> 'Inactive'";
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

    public static function ajax_load_category() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        self::ensure_tables();
        $db = HearMed_DB::instance();

        $allowed = [ 'consumable', 'dome_filter', 'speaker', 'charger_accessory' ];
        $category = sanitize_key( $_POST['item_category'] ?? '' );
        if ( ! in_array( $category, $allowed, true ) ) {
            wp_send_json_error( 'Invalid category' );
        }

        $where = [ "s.item_category = '{$category}'" ];
        $params = [];
        $i = 1;

        $clinic = intval( $_POST['clinic_id'] ?? 0 );
        if ( $clinic ) { $where[] = "s.clinic_id = \${$i}"; $params[] = $clinic; $i++; }

        $mfr = intval( $_POST['manufacturer_id'] ?? 0 );
        if ( $mfr ) { $where[] = "s.manufacturer_id = \${$i}"; $params[] = $mfr; $i++; }

        $search = sanitize_text_field( $_POST['search'] ?? '' );
        if ( $search ) {
            $where[] = "(s.model_name ILIKE \${$i} OR s.specification ILIKE \${$i} OR m.name ILIKE \${$i})";
            $params[] = '%' . $search . '%';
            $i++;
        }

        $w = implode( ' AND ', $where );

        $rows = $db->get_results(
            "SELECT s.id AS \"_ID\", s.model_name, s.style, s.technology_level, s.specification, s.quantity,
                    COALESCE(m.name, '') AS manufacturer_name,
                    COALESCE(c.clinic_name, 'Unassigned') AS clinic_name
             FROM hearmed_reference.inventory_stock s
             LEFT JOIN hearmed_reference.manufacturers m ON m.id = s.manufacturer_id
             LEFT JOIN hearmed_reference.clinics c ON c.id = s.clinic_id
             WHERE {$w}
             ORDER BY m.name, s.model_name",
            $params
        );

        $stats = $db->get_row(
            "SELECT COUNT(*) AS total, SUM(quantity) AS total_qty
             FROM hearmed_reference.inventory_stock
             WHERE item_category = $1",
            [ $category ]
        );

        wp_send_json_success([
            'rows'  => $rows ?: [],
            'stats' => [
                'total'     => (int) ( $stats->total ?? 0 ),
                'total_qty' => (int) ( $stats->total_qty ?? 0 ),
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

    public static function ajax_request_from_clinic() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        self::ensure_tables();

        $stock_id   = intval( $_POST['stock_id'] ?? 0 );
        $to_clinic  = intval( $_POST['to_clinic_id'] ?? 0 );
        $notes      = sanitize_text_field( $_POST['notes'] ?? '' );
        if ( ! $stock_id || ! $to_clinic ) wp_send_json_error( 'Missing fields' );

        $db = HearMed_DB::instance();
        $user = HearMed_Auth::current_user();
        $stock = $db->get_row( "SELECT * FROM hearmed_reference.inventory_stock WHERE id = $1", [ $stock_id ] );
        if ( ! $stock ) wp_send_json_error( 'Stock not found' );
        if ( ( $stock->item_category ?? '' ) !== 'hearing_aid' ) wp_send_json_error( 'Request from clinic is only available for hearing aids.' );

        $db->query(
            "UPDATE hearmed_reference.inventory_stock SET status = 'Requested', updated_at = NOW() WHERE id = $1",
            [ $stock_id ]
        );

        $db->insert( 'hearmed_reference.stock_movements', [
            'stock_id'       => $stock_id,
            'movement_type'  => 'request',
            'from_clinic_id' => intval( $stock->clinic_id ?? 0 ) ?: null,
            'to_clinic_id'   => $to_clinic,
            'quantity'       => intval( $stock->quantity ?? 1 ),
            'notes'          => $notes ?: 'Requested from clinic',
            'created_by'     => trim( ( $user->first_name ?? '' ) . ' ' . ( $user->last_name ?? '' ) ),
            'created_at'     => date( 'Y-m-d H:i:s' ),
        ] );

        wp_send_json_success( [ 'message' => 'Request logged' ] );
    }

    public static function ajax_use_from_stock() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        self::ensure_tables();

        $stock_id    = intval( $_POST['stock_id'] ?? 0 );
        $patient_id  = intval( $_POST['patient_id'] ?? 0 );
        $notes       = sanitize_text_field( $_POST['notes'] ?? '' );
        if ( ! $stock_id || ! $patient_id ) wp_send_json_error( 'Missing fields' );

        $db = HearMed_DB::instance();
        $user = HearMed_Auth::current_user();

        $stock = $db->get_row( "SELECT * FROM hearmed_reference.inventory_stock WHERE id = $1", [ $stock_id ] );
        if ( ! $stock ) wp_send_json_error( 'Stock not found' );
        if ( ( $stock->item_category ?? '' ) !== 'hearing_aid' ) wp_send_json_error( 'Use from stock is only available for hearing aids.' );

        $patient = $db->get_row(
            "SELECT id, assigned_clinic_id FROM hearmed_core.patients WHERE id = $1",
            [ $patient_id ]
        );
        if ( ! $patient ) wp_send_json_error( 'Patient not found' );

        $db->query(
            "UPDATE hearmed_reference.inventory_stock
             SET status = 'Fitted', fitted_to_patient_id = $1, reserved_for_patient_id = NULL,
                 clinic_id = COALESCE($2, clinic_id), updated_at = NOW()
             WHERE id = $3",
            [ $patient_id, intval( $patient->assigned_clinic_id ?? 0 ) ?: null, $stock_id ]
        );

        $db->insert( 'hearmed_reference.stock_movements', [
            'stock_id'       => $stock_id,
            'movement_type'  => 'fitted',
            'to_clinic_id'   => intval( $patient->assigned_clinic_id ?? 0 ) ?: null,
            'quantity'       => 1,
            'notes'          => $notes ?: ( 'Used from stock for patient #' . $patient_id ),
            'created_by'     => trim( ( $user->first_name ?? '' ) . ' ' . ( $user->last_name ?? '' ) ),
            'created_at'     => date( 'Y-m-d H:i:s' ),
        ] );

        wp_send_json_success( [ 'message' => 'Stock item marked as fitted' ] );
    }

    public static function ajax_return_to_manufacturer() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        self::ensure_tables();

        $stock_id = intval( $_POST['stock_id'] ?? 0 );
        $notes    = sanitize_text_field( $_POST['notes'] ?? '' );
        if ( ! $stock_id ) wp_send_json_error( 'Missing stock ID' );

        $db = HearMed_DB::instance();
        $user = HearMed_Auth::current_user();
        $stock = $db->get_row( "SELECT * FROM hearmed_reference.inventory_stock WHERE id = $1", [ $stock_id ] );
        if ( ! $stock ) wp_send_json_error( 'Stock not found' );
        if ( ( $stock->item_category ?? '' ) !== 'hearing_aid' ) wp_send_json_error( 'Return to manufacturer is only available for hearing aids.' );

        $db->query(
            "UPDATE hearmed_reference.inventory_stock
             SET status = 'Inactive', updated_at = NOW(), return_reason = $1
             WHERE id = $2",
            [ $notes ?: 'Returned to manufacturer', $stock_id ]
        );

        $db->insert( 'hearmed_reference.stock_movements', [
            'stock_id'       => $stock_id,
            'movement_type'  => 'manufacturer_return',
            'from_clinic_id' => intval( $stock->clinic_id ?? 0 ) ?: null,
            'quantity'       => 1,
            'notes'          => $notes ?: 'Returned to manufacturer',
            'created_by'     => trim( ( $user->first_name ?? '' ) . ' ' . ( $user->last_name ?? '' ) ),
            'created_at'     => date( 'Y-m-d H:i:s' ),
        ] );

        wp_send_json_success( [ 'message' => 'Returned to manufacturer and marked inactive' ] );
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
            'quantity'         => ( $category === 'hearing_aid' ) ? 1 : max( 1, $qty ),
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

    public static function ajax_import_csv() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        self::ensure_tables();

        $template_type = sanitize_key( $_POST['template_type'] ?? '' );
        $rows_json = wp_unslash( $_POST['rows_json'] ?? '[]' );
        $rows = json_decode( $rows_json, true );

        $template_to_category = [
            'hearing_aids' => 'hearing_aid',
            'consumables' => 'consumable',
            'domes_filters' => 'dome_filter',
            'speakers' => 'speaker',
            'chargers_accessories' => 'charger_accessory',
        ];

        if ( ! isset( $template_to_category[ $template_type ] ) ) {
            wp_send_json_error( [ 'message' => 'Invalid template type' ] );
        }
        if ( ! is_array( $rows ) || empty( $rows ) ) {
            wp_send_json_error( [ 'message' => 'No CSV rows provided' ] );
        }

        $category = $template_to_category[ $template_type ];
        $db = HearMed_DB::instance();
        $user = HearMed_Auth::current_user();
        $created_by = trim( ( $user->first_name ?? '' ) . ' ' . ( $user->last_name ?? '' ) );

        $clinic_rows = $db->get_results( "SELECT id, clinic_name FROM hearmed_reference.clinics" ) ?: [];
        $mfr_rows = $db->get_results( "SELECT id, name FROM hearmed_reference.manufacturers" ) ?: [];

        $clinics = [];
        foreach ( $clinic_rows as $c ) { $clinics[ strtolower( trim( $c->clinic_name ) ) ] = intval( $c->id ); }
        $mfrs = [];
        foreach ( $mfr_rows as $m ) { $mfrs[ strtolower( trim( $m->name ) ) ] = intval( $m->id ); }

        $imported = 0;
        $failed = 0;
        $errors = [];

        foreach ( $rows as $idx => $row ) {
            $line = $idx + 2;
            $clinic_name = strtolower( trim( strval( $row['clinic_name'] ?? '' ) ) );
            $model_name  = trim( strval( $row['model_name'] ?? '' ) );
            $mfr_name    = strtolower( trim( strval( $row['manufacturer_name'] ?? '' ) ) );
            $status      = trim( strval( $row['status'] ?? 'Available' ) );
            $allowed_statuses = [ 'Available', 'Reserved', 'Fitted', 'Returned' ];
            if ( ! in_array( $status, $allowed_statuses, true ) ) $status = 'Available';

            if ( empty( $clinic_name ) || empty( $clinics[ $clinic_name ] ) ) {
                $failed++; $errors[] = 'Line ' . $line . ': Clinic not found.'; continue;
            }
            if ( $model_name === '' ) {
                $failed++; $errors[] = 'Line ' . $line . ': model_name is required.'; continue;
            }

            $clinic_id = $clinics[ $clinic_name ];
            $mfr_id = $mfr_name !== '' && isset( $mfrs[ $mfr_name ] ) ? $mfrs[ $mfr_name ] : null;
            $serial = trim( strval( $row['serial_number'] ?? '' ) );

            if ( $category === 'hearing_aid' && $serial === '' ) {
                $failed++; $errors[] = 'Line ' . $line . ': serial_number is required for hearing aids.'; continue;
            }
            if ( $category === 'hearing_aid' && $serial !== '' ) {
                $dup = $db->get_var( "SELECT id FROM hearmed_reference.inventory_stock WHERE serial_number = $1", [ $serial ] );
                if ( $dup ) {
                    $failed++; $errors[] = 'Line ' . $line . ': serial_number already exists.'; continue;
                }
            }

            $qty = intval( $row['quantity'] ?? 1 );
            if ( $category === 'hearing_aid' ) $qty = 1;
            if ( $qty < 1 ) $qty = 1;

            $style_val = trim( strval( $row['style'] ?? '' ) );
            $tech_val  = trim( strval( $row['technology_level'] ?? '' ) );
            $spec_val  = trim( strval( $row['specification'] ?? '' ) );

            if ( $template_type === 'domes_filters' ) {
                $style_val = trim( strval( $row['dome_type'] ?? '' ) );
                $spec_val  = trim( strval( $row['dome_size'] ?? '' ) );
                $tech_val  = '';
            } elseif ( $template_type === 'speakers' ) {
                $style_val = trim( strval( $row['speaker_power'] ?? '' ) );
                $tech_val  = trim( strval( $row['speaker_length'] ?? '' ) );
                $spec_val  = '';
            }

            $data = [
                'item_category'    => $category,
                'clinic_id'        => $clinic_id,
                'manufacturer_id'  => $mfr_id,
                'model_name'       => $model_name,
                'style'            => $style_val ?: null,
                'technology_level' => $tech_val ?: null,
                'serial_number'    => $serial ?: null,
                'specification'    => $spec_val ?: null,
                'quantity'         => $qty,
                'status'           => $status,
            ];

            $id = $db->insert( 'hearmed_reference.inventory_stock', $data );
            if ( ! $id ) {
                $failed++; $errors[] = 'Line ' . $line . ': insert failed.'; continue;
            }

            $db->insert( 'hearmed_reference.stock_movements', [
                'stock_id'       => $id,
                'movement_type'  => 'imported',
                'to_clinic_id'   => $clinic_id,
                'quantity'       => $qty,
                'notes'          => 'Imported via CSV (' . $template_type . ')',
                'created_by'     => $created_by ?: 'System',
                'created_at'     => date( 'Y-m-d H:i:s' ),
            ] );

            $imported++;
        }

        wp_send_json_success([
            'imported' => $imported,
            'failed' => $failed,
            'errors' => array_slice( $errors, 0, 20 ),
        ]);
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
    // AJAX: Get products by manufacturer + stock category
    // ═══════════════════════════════════════════════════════════════════════

    public static function ajax_get_products() {
        check_ajax_referer( 'hm_nonce', 'nonce' );

        $mfr_id   = intval( $_POST['manufacturer_id'] ?? 0 );
        $stk_cat  = sanitize_key( $_POST['stock_category'] ?? '' );

        if ( ! $mfr_id ) wp_send_json_error( 'Manufacturer is required' );

        $db = HearMed_DB::instance();

        // Map stock category to products.item_type filter
        // hearing_aid     → item_type = 'product'
        // consumable      → item_type = 'consumable'
        // dome_filter     → item_type = 'bundled' AND bundled_category ILIKE 'dome%'
        // speaker         → item_type = 'bundled' AND bundled_category ILIKE 'speaker%'
        // charger_accessory → item_type IN ('charger','accessory')

        $where  = "p.is_active = true AND p.manufacturer_id = $1";
        $params = [ $mfr_id ];
        $i = 2;

        switch ( $stk_cat ) {
            case 'hearing_aid':
                $where .= " AND p.item_type = 'product'";
                break;
            case 'consumable':
                $where .= " AND p.item_type = 'consumable'";
                break;
            case 'dome_filter':
                $where .= " AND p.item_type = 'bundled' AND (p.bundled_category ILIKE 'dome%' OR p.dome_type IS NOT NULL AND p.dome_type != '')";
                break;
            case 'speaker':
                $where .= " AND p.item_type = 'bundled' AND (p.bundled_category ILIKE 'speaker%' OR p.speaker_power IS NOT NULL AND p.speaker_power != '')";
                break;
            case 'charger_accessory':
                $where .= " AND p.item_type IN ('charger','accessory')";
                break;
            default:
                // fallback: return all for this manufacturer
                break;
        }

        $rows = $db->get_results(
            "SELECT p.id, p.product_name, p.style, p.tech_level, p.hearing_aid_class,
                    p.dome_type, p.dome_size, p.speaker_power, p.speaker_length,
                    p.product_code, p.item_type, p.bundled_category
             FROM hearmed_reference.products p
             WHERE {$where}
             ORDER BY p.product_name",
            $params
        );

        $out = [];
        foreach ( ( $rows ?: [] ) as $r ) {
            $out[] = [
                'id'              => (int) $r->id,
                'product_name'    => $r->product_name ?? '',
                'style'           => $r->style ?? '',
                'tech_level'      => $r->tech_level ?? '',
                'hearing_aid_class' => $r->hearing_aid_class ?? '',
                'dome_type'       => $r->dome_type ?? '',
                'dome_size'       => $r->dome_size ?? '',
                'speaker_power'   => $r->speaker_power ?? '',
                'speaker_length'  => $r->speaker_length ?? '',
                'product_code'    => $r->product_code ?? '',
            ];
        }

        wp_send_json_success( $out );
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
