<?php
/**
 * HearMed Admin — Document Types & Templates
 * Shortcodes: [hearmed_document_types]  – list page
 *             [hearmed_document_template_editor] – form builder
 *
 * Jobs 1-3: Upgraded list, rebuilt editor, save & version logic.
 *
 * @package HearMed_Portal
 * @since   5.4.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_Admin_Document_Templates {

    /* ──────────────────────────────────────────────
       Default template sections (new rich format)
       ────────────────────────────────────────────── */
    private static $default_sections = [
        [
            'key'     => 'patient_details',
            'label'   => 'Patient Details',
            'type'    => 'auto',
            'enabled' => true,
            'sort'    => 1,
            'fields'  => [
                [ 'key' => 'patient_name',   'label' => 'Patient Name',   'required' => true,  'format' => 'text' ],
                [ 'key' => 'h_number',       'label' => 'H-Number',       'required' => true,  'format' => 'text' ],
                [ 'key' => 'date_of_birth',  'label' => 'Date of Birth',  'required' => true,  'format' => 'date' ],
                [ 'key' => 'contact_phone',  'label' => 'Phone',          'required' => false, 'format' => 'phone' ],
                [ 'key' => 'contact_email',  'label' => 'Email',          'required' => false, 'format' => 'email' ],
                [ 'key' => 'address',        'label' => 'Address',        'required' => false, 'format' => 'text' ],
            ],
        ],
        [
            'key'     => 'medical_history',
            'label'   => 'Medical History',
            'type'    => 'ai_extract',
            'enabled' => true,
            'sort'    => 2,
            'fields'  => [
                [ 'key' => 'noise_exposure',   'label' => 'Noise Exposure',    'required' => false, 'format' => 'text',     'ai_instruction' => 'Extract any mention of noise exposure, occupational or recreational.' ],
                [ 'key' => 'tinnitus',         'label' => 'Tinnitus',          'required' => false, 'format' => 'text',     'ai_instruction' => 'Note any tinnitus symptoms, laterality, duration and severity.' ],
                [ 'key' => 'dizziness',        'label' => 'Dizziness/Vertigo', 'required' => false, 'format' => 'text',     'ai_instruction' => 'Extract dizziness, vertigo or balance issues.' ],
                [ 'key' => 'ear_history',      'label' => 'Ear History',       'required' => false, 'format' => 'textarea', 'ai_instruction' => 'Note infections, surgery, discharge, or pain history.' ],
                [ 'key' => 'family_history',   'label' => 'Family History',    'required' => false, 'format' => 'text',     'ai_instruction' => 'Extract family history of hearing loss.' ],
                [ 'key' => 'medications',      'label' => 'Medications',       'required' => false, 'format' => 'textarea', 'ai_instruction' => 'List medications, especially ototoxic drugs.' ],
            ],
        ],
        [
            'key'     => 'hearing_results',
            'label'   => 'Hearing Test Results',
            'type'    => 'ai_extract',
            'enabled' => true,
            'sort'    => 3,
            'fields'  => [
                [ 'key' => 'right_ear',     'label' => 'Right Ear',     'required' => false, 'format' => 'text',     'ai_instruction' => 'Summarise right ear hearing thresholds and type of loss.' ],
                [ 'key' => 'left_ear',      'label' => 'Left Ear',      'required' => false, 'format' => 'text',     'ai_instruction' => 'Summarise left ear hearing thresholds and type of loss.' ],
                [ 'key' => 'classification','label' => 'Classification', 'required' => false, 'format' => 'select',  'ai_instruction' => 'Classify: mild, moderate, severe, profound, sensorineural, conductive, mixed.' ],
            ],
        ],
        [
            'key'     => 'recommendations',
            'label'   => 'Recommendations',
            'type'    => 'ai_extract',
            'enabled' => true,
            'sort'    => 4,
            'fields'  => [
                [ 'key' => 'recommendation', 'label' => 'Recommendation', 'required' => false, 'format' => 'textarea', 'ai_instruction' => 'Summarise recommendations: hearing aids, referral, follow-up.' ],
            ],
        ],
        [
            'key'     => 'audiogram',
            'label'   => 'Audiogram',
            'type'    => 'chart',
            'enabled' => true,
            'sort'    => 5,
            'fields'  => [],
        ],
        [
            'key'     => 'dispenser_notes',
            'label'   => 'Dispenser Notes',
            'type'    => 'text',
            'enabled' => true,
            'sort'    => 6,
            'fields'  => [],
        ],
        [
            'key'     => 'consent',
            'label'   => 'Consent & Signature',
            'type'    => 'signature',
            'enabled' => true,
            'sort'    => 7,
            'fields'  => [],
        ],
    ];

    /* Default document types to seed */
    private static $default_types = [
        [ 'name' => 'Case History',    'category' => 'clinical',      'ai_enabled' => true  ],
        [ 'name' => 'Audiogram',       'category' => 'clinical',      'ai_enabled' => false ],
        [ 'name' => 'Hearing Test',    'category' => 'clinical',      'ai_enabled' => true  ],
        [ 'name' => 'Sales Order',     'category' => 'financial',     'ai_enabled' => false ],
        [ 'name' => 'Consent Form',    'category' => 'consent',       'ai_enabled' => false ],
        [ 'name' => 'GP Referral',     'category' => 'referral',      'ai_enabled' => true  ],
        [ 'name' => 'ENT Referral',    'category' => 'referral',      'ai_enabled' => true  ],
        [ 'name' => 'Repair Form',     'category' => 'service',       'ai_enabled' => false ],
        [ 'name' => 'Fitting Receipt', 'category' => 'financial',     'ai_enabled' => false ],
        [ 'name' => 'Phone Call Log',  'category' => 'communication', 'ai_enabled' => true  ],
    ];

    private $table          = 'hearmed_admin.document_templates';
    private $versions_table = 'hearmed_admin.document_template_versions';

    public function __construct() {
        add_shortcode( 'hearmed_document_types',           [ $this, 'render' ] );
        add_shortcode( 'hearmed_document_template_editor', [ $this, 'render_editor' ] );
        add_action( 'wp_ajax_hm_admin_save_document_type',      [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_hm_admin_delete_document_type',    [ $this, 'ajax_delete' ] );
        add_action( 'wp_ajax_hm_admin_save_template_sections',  [ $this, 'ajax_save_sections' ] );
        add_action( 'wp_ajax_hm_admin_get_template_versions',   [ $this, 'ajax_get_versions' ] );
    }

    /* ──────────────────────────────────────────────
       Auto-migrate: create table if not exists
       ────────────────────────────────────────────── */
    private function ensure_table() {
        $exists = HearMed_DB::get_var( "SELECT to_regclass('{$this->table}')" );
        if ( $exists !== null ) {
            // Ensure new columns exist
            $col = HearMed_DB::get_var(
                "SELECT column_name FROM information_schema.columns 
                 WHERE table_schema='hearmed_admin' AND table_name='document_templates' AND column_name='current_version'"
            );
            if ( ! $col ) {
                HearMed_DB::query( "ALTER TABLE {$this->table} ADD COLUMN IF NOT EXISTS ai_system_prompt TEXT DEFAULT ''" );
                HearMed_DB::query( "ALTER TABLE {$this->table} ADD COLUMN IF NOT EXISTS current_version INTEGER DEFAULT 1" );
            }
            // Ensure versions table
            $v_exists = HearMed_DB::get_var( "SELECT to_regclass('{$this->versions_table}')" );
            if ( ! $v_exists ) {
                HearMed_DB::query( "CREATE TABLE {$this->versions_table} (
                    id              SERIAL PRIMARY KEY,
                    template_id     INTEGER NOT NULL REFERENCES {$this->table}(id) ON DELETE CASCADE,
                    version         INTEGER NOT NULL DEFAULT 1,
                    sections_json   JSONB   NOT NULL DEFAULT '[]'::jsonb,
                    ai_system_prompt TEXT   DEFAULT '',
                    created_by      INTEGER,
                    created_at      TIMESTAMP DEFAULT NOW(),
                    UNIQUE (template_id, version)
                )" );
            }
            return;
        }

        HearMed_DB::query( "CREATE TABLE {$this->table} (
            id               SERIAL PRIMARY KEY,
            name             VARCHAR(100) NOT NULL,
            category         VARCHAR(50)  DEFAULT 'clinical',
            ai_enabled       BOOLEAN      DEFAULT false,
            password_protect BOOLEAN      DEFAULT true,
            sections_json    JSONB        DEFAULT '[]'::jsonb,
            ai_system_prompt TEXT         DEFAULT '',
            current_version  INTEGER      DEFAULT 1,
            is_active        BOOLEAN      DEFAULT true,
            sort_order       INTEGER      DEFAULT 0,
            created_at       TIMESTAMP    DEFAULT NOW(),
            updated_at       TIMESTAMP    DEFAULT NOW()
        )" );

        // Seed defaults
        foreach ( self::$default_types as $i => $dt ) {
            HearMed_DB::insert( $this->table, [
                'name'             => $dt['name'],
                'category'         => $dt['category'],
                'ai_enabled'       => $dt['ai_enabled'] ? 'true' : 'false',
                'password_protect' => 'true',
                'sections_json'    => wp_json_encode( self::$default_sections ),
                'sort_order'       => ( $i + 1 ) * 10,
                'created_at'       => current_time( 'mysql' ),
                'updated_at'       => current_time( 'mysql' ),
            ] );
        }

        // Create versions table
        HearMed_DB::query( "CREATE TABLE IF NOT EXISTS {$this->versions_table} (
            id              SERIAL PRIMARY KEY,
            template_id     INTEGER NOT NULL REFERENCES {$this->table}(id) ON DELETE CASCADE,
            version         INTEGER NOT NULL DEFAULT 1,
            sections_json   JSONB   NOT NULL DEFAULT '[]'::jsonb,
            ai_system_prompt TEXT   DEFAULT '',
            created_by      INTEGER,
            created_at      TIMESTAMP DEFAULT NOW(),
            UNIQUE (template_id, version)
        )" );
    }

    private function get_types() {
        $this->ensure_table();
        return HearMed_DB::get_results(
            "SELECT * FROM {$this->table} WHERE is_active = true ORDER BY sort_order, name"
        ) ?: [];
    }

    /* ════════════════════════════════════════════════════════════════════════
       JOB 1 — Render: Document Types listing (upgraded)
       ════════════════════════════════════════════════════════════════════════ */
    public function render() {
        if ( ! is_user_logged_in() ) return '<p>Please log in.</p>';
        $types = $this->get_types();

        $category_labels = [
            'clinical'      => 'Clinical',
            'financial'     => 'Financial',
            'consent'       => 'Consent',
            'referral'      => 'Referral',
            'service'       => 'Service',
            'communication' => 'Communication',
            'other'         => 'Other',
        ];
        $category_badge = [
            'clinical'      => 'blue',
            'financial'     => 'green',
            'consent'       => 'purple',
            'referral'      => 'orange',
            'service'       => 'grey',
            'communication' => 'amber',
            'other'         => 'grey',
        ];

        ob_start(); ?>
        <div class="hm-admin" id="hm-dt-app">
            <a href="<?php echo esc_url( home_url( '/admin-console/' ) ); ?>" class="hm-back">&larr; Back</a>
            <div class="hm-page-header">
                <h1 class="hm-page-title">Document Templates</h1>
                <div class="hm-page-header__actions">
                    <button class="hm-btn hm-btn--primary" onclick="hmDocTypes.openAdd()">+ Add Template</button>
                </div>
            </div>

            <!-- GDPR Note -->
            <div class="hm-dt-gdpr">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                <div>
                    <strong>GDPR Compliance:</strong> All generated documents are password-protected.
                    AI transcription uses patient H-numbers only — no names in audio processing.
                    A consent checkbox with privacy policy link is required before any PDF download.
                </div>
            </div>

            <p class="hm-dt-subtitle">Click a template to configure sections, fields and AI extraction rules.</p>

            <!-- Template Cards Grid -->
            <div class="hm-dt-grid">
                <?php foreach ( $types as $t ):
                    $sections = json_decode( $t->sections_json ?: '[]', true );
                    $enabled_count = 0;
                    $field_count   = 0;
                    $ai_count      = 0;
                    if ( is_array( $sections ) ) {
                        foreach ( $sections as $s ) {
                            if ( ! empty( $s['enabled'] ) ) {
                                $enabled_count++;
                                if ( ! empty( $s['fields'] ) && is_array( $s['fields'] ) ) {
                                    $field_count += count( $s['fields'] );
                                }
                                if ( ( $s['type'] ?? '' ) === 'ai_extract' || ( $s['type'] ?? '' ) === 'ai_detect' ) {
                                    $ai_count++;
                                }
                            }
                        }
                    }
                    $cat   = $t->category ?: 'other';
                    $color = $category_badge[ $cat ] ?? 'grey';
                    $ver   = $t->current_version ?? 1;
                ?>
                <div class="hm-dt-card" onclick="hmDocTypes.configure(<?php echo (int) $t->id; ?>)">
                    <div class="hm-dt-card__actions">
                        <span class="hm-dt-card__act" onclick="event.stopPropagation();hmDocTypes.openEdit(<?php echo htmlspecialchars( wp_json_encode( (array) $t ), ENT_QUOTES ); ?>)" title="Edit">&varr;</span>
                        <span class="hm-dt-card__act hm-dt-card__act--del" onclick="event.stopPropagation();hmDocTypes.del(<?php echo (int) $t->id; ?>,'<?php echo esc_js( $t->name ); ?>')" title="Delete">&times;</span>
                    </div>
                    <h4 class="hm-dt-card__name"><?php echo esc_html( $t->name ); ?></h4>
                    <div class="hm-dt-card__badges">
                        <span class="hm-badge hm-badge--<?php echo esc_attr( $color ); ?>"><?php echo esc_html( $category_labels[ $cat ] ?? ucfirst( $cat ) ); ?></span>
                        <?php if ( $t->ai_enabled ): ?>
                            <span class="hm-badge hm-badge--purple">&#9733; AI</span>
                        <?php endif; ?>
                        <span class="hm-badge hm-badge--grey">v<?php echo (int) $ver; ?></span>
                    </div>
                    <div class="hm-dt-card__stats">
                        <span><?php echo $enabled_count; ?> section<?php echo $enabled_count !== 1 ? 's' : ''; ?></span>
                        <span class="hm-dt-dot">&middot;</span>
                        <span><?php echo $field_count; ?> field<?php echo $field_count !== 1 ? 's' : ''; ?></span>
                        <?php if ( $ai_count ): ?>
                        <span class="hm-dt-dot">&middot;</span>
                        <span class="hm-dt-ai-count"><?php echo $ai_count; ?> AI section<?php echo $ai_count !== 1 ? 's' : ''; ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if ( $t->password_protect ): ?>
                    <div class="hm-dt-card__lock">&#128274; Password Protected</div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Add/Edit Modal -->
        <div class="hm-modal-bg" id="hm-dt-modal">
            <div class="hm-modal hm-modal--md">
                <div class="hm-modal-hd">
                    <h3 id="hm-dt-modal-title">Add Document Type</h3>
                    <button class="hm-close" onclick="hmDocTypes.closeModal()">&times;</button>
                </div>
                <div class="hm-modal-body">
                    <input type="hidden" id="hm-dt-id" value="">
                    <div class="hm-form-group">
                        <label>Document Name *</label>
                        <input type="text" id="hm-dt-name" placeholder="e.g. Case History">
                    </div>
                    <div class="hm-form-group">
                        <label>Category</label>
                        <select id="hm-dt-category">
                            <?php foreach ( $category_labels as $k => $v ): ?>
                            <option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $v ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="hm-form-group">
                        <label class="hm-toggle">
                            <input type="checkbox" id="hm-dt-ai"><span class="hm-toggle-track"></span> AI Transcription Formatting
                        </label>
                        <p style="font-size:11px;color:var(--hm-text-muted);margin-top:4px;">When enabled, AI will detect content sections and apply formatting rules automatically.</p>
                    </div>
                    <div class="hm-form-group">
                        <label class="hm-toggle">
                            <input type="checkbox" id="hm-dt-pwd" checked><span class="hm-toggle-track"></span> Password-Protected PDF
                        </label>
                    </div>
                    <div class="hm-form-group">
                        <label>Sort Order</label>
                        <input type="number" id="hm-dt-sort" value="0" min="0" step="1" style="max-width:100px;">
                    </div>
                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn" onclick="hmDocTypes.closeModal()">Cancel</button>
                    <button class="hm-btn hm-btn--primary" onclick="hmDocTypes.save()" id="hm-dt-save-btn">Save</button>
                </div>
            </div>
        </div>

        <script>
        var hmDocTypes = {
            openAdd: function() {
                document.getElementById('hm-dt-modal-title').textContent = 'Add Document Type';
                document.getElementById('hm-dt-id').value = '';
                document.getElementById('hm-dt-name').value = '';
                document.getElementById('hm-dt-category').value = 'clinical';
                document.getElementById('hm-dt-ai').checked = false;
                document.getElementById('hm-dt-pwd').checked = true;
                document.getElementById('hm-dt-sort').value = '0';
                document.getElementById('hm-dt-modal').classList.add('open');
                document.getElementById('hm-dt-name').focus();
            },
            openEdit: function(data) {
                document.getElementById('hm-dt-modal-title').textContent = 'Edit Document Type';
                document.getElementById('hm-dt-id').value = data.id;
                document.getElementById('hm-dt-name').value = data.name;
                document.getElementById('hm-dt-category').value = data.category || 'clinical';
                document.getElementById('hm-dt-ai').checked = !!data.ai_enabled;
                document.getElementById('hm-dt-pwd').checked = data.password_protect !== false;
                document.getElementById('hm-dt-sort').value = data.sort_order || '0';
                document.getElementById('hm-dt-modal').classList.add('open');
                document.getElementById('hm-dt-name').focus();
            },
            closeModal: function() {
                document.getElementById('hm-dt-modal').classList.remove('open');
            },
            save: function() {
                var name = document.getElementById('hm-dt-name').value.trim();
                if (!name) { alert('Name is required.'); return; }
                var btn = document.getElementById('hm-dt-save-btn');
                btn.textContent = 'Saving\u2026'; btn.disabled = true;
                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_save_document_type',
                    nonce:  HM.nonce,
                    doc_id:           document.getElementById('hm-dt-id').value,
                    name:             name,
                    category:         document.getElementById('hm-dt-category').value,
                    ai_enabled:       document.getElementById('hm-dt-ai').checked ? 1 : 0,
                    password_protect: document.getElementById('hm-dt-pwd').checked ? 1 : 0,
                    sort_order:       document.getElementById('hm-dt-sort').value
                }, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error'); btn.textContent = 'Save'; btn.disabled = false; }
                });
            },
            del: function(id, name) {
                if (!confirm('Delete "' + name + '"?')) return;
                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_delete_document_type',
                    nonce:  HM.nonce,
                    doc_id: id
                }, function(r) {
                    if (r.success) location.reload();
                    else alert(r.data || 'Error');
                });
            },
            configure: function(id) {
                window.location.href = '<?php echo esc_url( home_url( "/document-template-editor/" ) ); ?>?doc_type_id=' + id;
            }
        };
        </script>
        <?php return ob_get_clean();
    }

    /* ════════════════════════════════════════════════════════════════════════
       JOB 2 — Render: Template Section & Field Builder (rebuilt)
       ════════════════════════════════════════════════════════════════════════ */
    public function render_editor() {
        if ( ! is_user_logged_in() ) return '<p>Please log in.</p>';
        $this->ensure_table();

        $doc_id = intval( $_GET['doc_type_id'] ?? 0 );
        if ( ! $doc_id ) return '<p>No document type selected.</p>';

        $doc = HearMed_DB::get_row(
            "SELECT * FROM {$this->table} WHERE id = $1 AND is_active = true",
            [ $doc_id ]
        );
        if ( ! $doc ) return '<p>Document type not found.</p>';

        $sections = json_decode( $doc->sections_json ?: '[]', true );
        if ( ! is_array( $sections ) || empty( $sections ) ) {
            $sections = self::$default_sections;
        }
        usort( $sections, function( $a, $b ) { return ( $a['sort'] ?? 0 ) - ( $b['sort'] ?? 0 ); } );

        $section_types = [
            'auto'       => 'Auto-fill (Patient Data)',
            'ai_extract' => 'AI Extraction',
            'ai_detect'  => 'AI Keyword Detection (Legacy)',
            'text'       => 'Free Text',
            'chart'      => 'Chart / Image',
            'signature'  => 'Consent & Signature',
            'table'      => 'Table / Grid',
        ];

        $field_formats = [ 'text', 'textarea', 'date', 'email', 'phone', 'number', 'select', 'boolean' ];

        $privacy_url       = get_option( 'hm_privacy_policy_url', '' );
        $ai_system_prompt  = $doc->ai_system_prompt ?? '';
        $current_version   = (int) ( $doc->current_version ?? 1 );

        ob_start(); ?>
        <div class="hm-admin" id="hm-te-app">
            <a href="<?php echo esc_url( home_url( '/document-types/' ) ); ?>" class="hm-back">&larr; Back to Templates</a>

            <!-- Meta Bar -->
            <div class="hm-page-header">
                <div>
                    <h1 class="hm-page-title"><?php echo esc_html( $doc->name ); ?></h1>
                    <div class="hm-te-meta-badges">
                        <span class="hm-badge hm-badge--grey">v<?php echo $current_version; ?></span>
                        <?php if ( $doc->ai_enabled ): ?>
                        <span class="hm-badge hm-badge--purple">&#9733; AI Enabled</span>
                        <?php endif; ?>
                        <?php if ( $doc->password_protect ): ?>
                        <span class="hm-badge hm-badge--grey">&#128274; Protected</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="hm-page-header__actions" style="gap:8px;display:flex;">
                    <button class="hm-btn" onclick="hmTE.showVersions()">Version History</button>
                    <button class="hm-btn" onclick="hmTE.previewSchema()">Preview Schema</button>
                    <button class="hm-btn hm-btn--primary" onclick="hmTE.saveAll()" id="hm-te-save">Save &amp; Version</button>
                </div>
            </div>

            <!-- Two-column layout -->
            <div class="hm-te-layout">

                <!-- LEFT: Sections & Fields Builder -->
                <div class="hm-te-main">

                    <?php if ( $doc->ai_enabled ): ?>
                    <!-- AI System Prompt Card -->
                    <div class="hm-card hm-te-ai-card">
                        <div class="hm-card-hd">
                            <span style="display:flex;align-items:center;gap:6px;">
                                <span style="color:#8b5cf6;">&#9733;</span>
                                <strong>AI System Prompt</strong>
                            </span>
                            <span class="hm-badge hm-badge--purple" style="font-size:10px;">AI</span>
                        </div>
                        <div class="hm-card-body">
                            <p style="font-size:12px;color:var(--hm-text-muted);margin:0 0 8px;">
                                This prompt is sent to the AI model when extracting data from a transcript. 
                                It defines the role, tone and output format.
                            </p>
                            <textarea id="hm-te-ai-prompt" class="hm-te-ai-textarea" rows="5" placeholder="You are a clinical audiologist assistant. Extract structured data from the consultation transcript. Output JSON matching the template schema. Be concise and clinical. If information is not mentioned, use null."><?php echo esc_textarea( $ai_system_prompt ); ?></textarea>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Sections Accordion -->
                    <div id="hm-te-sections">
                    <?php foreach ( $sections as $idx => $sec ): ?>
                        <?php $this->render_section_html( $sec, $idx, $section_types, $field_formats ); ?>
                    <?php endforeach; ?>
                    </div>

                    <!-- Add Section -->
                    <div class="hm-te-add-section" onclick="hmTE.addSection()">
                        + Add Section
                    </div>
                </div>

                <!-- RIGHT: Schema Preview -->
                <div class="hm-te-sidebar">
                    <div class="hm-card">
                        <div class="hm-card-hd">
                            <strong>Schema Preview</strong>
                        </div>
                        <div class="hm-card-body">
                            <pre id="hm-te-schema-pre" class="hm-te-schema-code"></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Section Modal -->
        <div class="hm-modal-bg" id="hm-te-add-modal">
            <div class="hm-modal hm-modal--sm">
                <div class="hm-modal-hd">
                    <h3>Add Section</h3>
                    <button class="hm-close" onclick="document.getElementById('hm-te-add-modal').classList.remove('open')">&times;</button>
                </div>
                <div class="hm-modal-body">
                    <div class="hm-form-group">
                        <label>Section Label *</label>
                        <input type="text" id="hm-te-new-label" placeholder="e.g. Lifestyle Assessment">
                    </div>
                    <div class="hm-form-group">
                        <label>Section Type</label>
                        <select id="hm-te-new-type">
                            <?php foreach ( $section_types as $k => $v ):
                                if ( $k === 'ai_detect' ) continue; // hide legacy
                            ?>
                            <option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $v ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn" onclick="document.getElementById('hm-te-add-modal').classList.remove('open')">Cancel</button>
                    <button class="hm-btn hm-btn--primary" onclick="hmTE.confirmAddSection()">Add Section</button>
                </div>
            </div>
        </div>

        <!-- Add Field Modal -->
        <div class="hm-modal-bg" id="hm-te-field-modal">
            <div class="hm-modal hm-modal--md">
                <div class="hm-modal-hd">
                    <h3>Add Field</h3>
                    <button class="hm-close" onclick="document.getElementById('hm-te-field-modal').classList.remove('open')">&times;</button>
                </div>
                <div class="hm-modal-body">
                    <input type="hidden" id="hm-te-field-sec-idx" value="">
                    <div class="hm-form-group">
                        <label>Field Label *</label>
                        <input type="text" id="hm-te-field-label" placeholder="e.g. Noise Exposure">
                    </div>
                    <div class="hm-form-group">
                        <label>Format</label>
                        <select id="hm-te-field-format">
                            <?php foreach ( $field_formats as $ff ): ?>
                            <option value="<?php echo esc_attr( $ff ); ?>"><?php echo esc_html( ucfirst( $ff ) ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="hm-form-group">
                        <label class="hm-toggle">
                            <input type="checkbox" id="hm-te-field-req"><span class="hm-toggle-track"></span> Required
                        </label>
                    </div>
                    <div class="hm-form-group">
                        <label>AI Instruction <span style="font-size:10px;color:var(--hm-text-muted);">(optional)</span></label>
                        <textarea id="hm-te-field-ai" rows="3" placeholder="e.g. Extract any mention of noise exposure, occupational or recreational."></textarea>
                    </div>
                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn" onclick="document.getElementById('hm-te-field-modal').classList.remove('open')">Cancel</button>
                    <button class="hm-btn hm-btn--primary" onclick="hmTE.confirmAddField()">Add Field</button>
                </div>
            </div>
        </div>

        <!-- Version History Modal -->
        <div class="hm-modal-bg" id="hm-te-versions-modal">
            <div class="hm-modal hm-modal--lg">
                <div class="hm-modal-hd">
                    <h3>Version History</h3>
                    <button class="hm-close" onclick="document.getElementById('hm-te-versions-modal').classList.remove('open')">&times;</button>
                </div>
                <div class="hm-modal-body" id="hm-te-versions-body">
                    <p style="color:var(--hm-text-muted);font-size:13px;">Loading&hellip;</p>
                </div>
            </div>
        </div>

        <!-- GDPR Consent Modal -->
        <div class="hm-modal-bg" id="hm-gdpr-consent-modal">
            <div class="hm-modal hm-modal--sm">
                <div class="hm-modal-hd">
                    <h3>GDPR Consent Required</h3>
                    <button class="hm-close" onclick="hmGdprConsent.close()">&times;</button>
                </div>
                <div class="hm-modal-body">
                    <p style="font-size:13px;margin-bottom:12px;">Before downloading or generating this document, you must confirm GDPR compliance.</p>
                    <label class="hm-toggle" style="font-size:13px;">
                        <input type="checkbox" id="hm-gdpr-consent-cb">
                        <span class="hm-toggle-track"></span>
                        I confirm this data access / download is necessary for patient care and complies with our
                        <a href="<?php echo esc_url( $privacy_url ?: '#' ); ?>" target="_blank" style="color:var(--hm-teal);">GDPR Privacy Policy</a>.
                    </label>
                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn" onclick="hmGdprConsent.close()">Cancel</button>
                    <button class="hm-btn hm-btn--primary" onclick="hmGdprConsent.proceed()" id="hm-gdpr-proceed-btn" disabled>Proceed</button>
                </div>
            </div>
        </div>

        <script>
        /**
         * Template Editor – hmTE
         */
        (function() {
            var _sections = <?php echo wp_json_encode( array_values( $sections ) ); ?>;
            var _docId    = <?php echo (int) $doc_id; ?>;
            var _sectionTypes = <?php echo wp_json_encode( $section_types ); ?>;
            var _fieldFormats = <?php echo wp_json_encode( $field_formats ); ?>;

            function el(id) { return document.getElementById(id); }

            /* ── Rebuild a single section's field list in the DOM ── */
            function renderFields(secIdx) {
                var sec = _sections[secIdx];
                if (!sec || !sec.fields) return;
                var wrap = document.querySelector('.hm-te-fields-list[data-sec="'+secIdx+'"]');
                if (!wrap) return;
                var html = '';
                sec.fields.forEach(function(f, fi) {
                    html += '<div class="hm-te-field-row" data-fi="'+fi+'">';
                    html += '<span class="hm-te-field-drag" title="Drag">&#9776;</span>';
                    html += '<span class="hm-te-field-label">'+_esc(f.label)+'</span>';
                    html += '<span class="hm-te-field-format hm-badge hm-badge--grey">'+_esc(f.format || 'text')+'</span>';
                    if (f.required) html += '<span class="hm-badge hm-badge--red" style="font-size:10px;">Required</span>';
                    if (f.ai_instruction) html += '<span class="hm-badge hm-badge--purple" style="font-size:10px;" title="'+_esc(f.ai_instruction)+'">AI</span>';
                    html += '<span class="hm-te-field-del" onclick="hmTE.removeField('+secIdx+','+fi+')" title="Remove">&times;</span>';
                    html += '</div>';
                });
                wrap.innerHTML = html;
            }

            function _esc(s) {
                if (!s) return '';
                var d = document.createElement('div');
                d.textContent = s;
                return d.innerHTML;
            }

            /* ── Update the schema preview panel ── */
            function updateSchema() {
                var schema = {};
                _sections.forEach(function(sec) {
                    if (!sec.enabled) return;
                    if (sec.fields && sec.fields.length) {
                        var obj = {};
                        sec.fields.forEach(function(f) {
                            obj[f.key] = { type: f.format || 'text', required: !!f.required };
                            if (f.ai_instruction) obj[f.key].ai = f.ai_instruction;
                        });
                        schema[sec.key] = obj;
                    } else {
                        schema[sec.key] = sec.type;
                    }
                });
                var pre = el('hm-te-schema-pre');
                if (pre) pre.textContent = JSON.stringify(schema, null, 2);
            }

            window.hmTE = {
                /* ── Toggle section enabled ── */
                toggleSection: function(idx, enabled) {
                    if (_sections[idx]) _sections[idx].enabled = enabled;
                    var card = document.querySelector('.hm-te-sec[data-idx="'+idx+'"]');
                    if (card) card.classList.toggle('hm-te-sec--disabled', !enabled);
                    updateSchema();
                },

                /* ── Expand / collapse section ── */
                toggleAccordion: function(idx) {
                    var body = document.querySelector('.hm-te-sec-body[data-idx="'+idx+'"]');
                    if (body) body.classList.toggle('open');
                    var chevron = document.querySelector('.hm-te-sec-chevron[data-idx="'+idx+'"]');
                    if (chevron) chevron.classList.toggle('open');
                },

                /* ── Delete section ── */
                deleteSection: function(idx) {
                    if (!confirm('Remove this section?')) return;
                    _sections.splice(idx, 1);
                    hmTE.saveAll(true);
                },

                /* ── Add section modal ── */
                addSection: function() {
                    el('hm-te-new-label').value = '';
                    el('hm-te-new-type').value = 'text';
                    el('hm-te-add-modal').classList.add('open');
                    el('hm-te-new-label').focus();
                },
                confirmAddSection: function() {
                    var label = el('hm-te-new-label').value.trim();
                    if (!label) { alert('Label is required.'); return; }
                    var type = el('hm-te-new-type').value;
                    var key  = label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
                    _sections.push({
                        key: key, label: label, type: type,
                        enabled: true, sort: _sections.length + 1, fields: []
                    });
                    el('hm-te-add-modal').classList.remove('open');
                    hmTE.saveAll(true);
                },

                /* ── Add field modal ── */
                addField: function(secIdx) {
                    el('hm-te-field-sec-idx').value = secIdx;
                    el('hm-te-field-label').value = '';
                    el('hm-te-field-format').value = 'text';
                    el('hm-te-field-req').checked = false;
                    el('hm-te-field-ai').value = '';
                    el('hm-te-field-modal').classList.add('open');
                    el('hm-te-field-label').focus();
                },
                confirmAddField: function() {
                    var secIdx = parseInt(el('hm-te-field-sec-idx').value);
                    var label  = el('hm-te-field-label').value.trim();
                    if (!label) { alert('Label is required.'); return; }
                    var key = label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
                    var field = {
                        key:     key,
                        label:   label,
                        required: el('hm-te-field-req').checked,
                        format:  el('hm-te-field-format').value,
                    };
                    var ai = el('hm-te-field-ai').value.trim();
                    if (ai) field.ai_instruction = ai;
                    if (!_sections[secIdx].fields) _sections[secIdx].fields = [];
                    _sections[secIdx].fields.push(field);
                    el('hm-te-field-modal').classList.remove('open');
                    renderFields(secIdx);
                    updateSchema();
                },

                /* ── Remove field ── */
                removeField: function(secIdx, fieldIdx) {
                    if (!confirm('Remove this field?')) return;
                    _sections[secIdx].fields.splice(fieldIdx, 1);
                    renderFields(secIdx);
                    updateSchema();
                },

                /* ── Schema preview ── */
                previewSchema: function() {
                    updateSchema();
                    /* Scroll the sidebar into view on mobile */
                    var pre = el('hm-te-schema-pre');
                    if (pre) pre.scrollIntoView({ behavior: 'smooth', block: 'start' });
                },

                /* ── Version history ── */
                showVersions: function() {
                    var body = el('hm-te-versions-body');
                    body.innerHTML = '<p style="color:var(--hm-text-muted);font-size:13px;">Loading\u2026</p>';
                    el('hm-te-versions-modal').classList.add('open');
                    jQuery.post(HM.ajax_url, {
                        action: 'hm_admin_get_template_versions',
                        nonce: HM.nonce,
                        doc_id: _docId
                    }, function(r) {
                        if (!r.success || !r.data.length) {
                            body.innerHTML = '<p style="color:var(--hm-text-muted);font-size:13px;">No version history yet. Versions are created each time you save.</p>';
                            return;
                        }
                        var html = '<table class="hm-table" style="width:100%;font-size:12px;">';
                        html += '<thead><tr><th>Version</th><th>Sections</th><th>Created</th></tr></thead><tbody>';
                        r.data.forEach(function(v) {
                            var secs = [];
                            try { secs = JSON.parse(v.sections_json); } catch(e){}
                            html += '<tr><td><strong>v'+v.version+'</strong></td>';
                            html += '<td>'+secs.length+' sections</td>';
                            html += '<td>'+v.created_at+'</td></tr>';
                        });
                        html += '</tbody></table>';
                        body.innerHTML = html;
                    });
                },

                /* ── Save & version ── */
                saveAll: function(reload) {
                    /* Update sort order */
                    var els = document.querySelectorAll('.hm-te-sec');
                    els.forEach(function(a, i) {
                        var idx = parseInt(a.getAttribute('data-idx'));
                        if (_sections[idx]) _sections[idx].sort = i + 1;
                    });

                    var btn = el('hm-te-save');
                    btn.textContent = 'Saving\u2026'; btn.disabled = true;

                    var postData = {
                        action: 'hm_admin_save_template_sections',
                        nonce:  HM.nonce,
                        doc_id: _docId,
                        sections: JSON.stringify(_sections)
                    };

                    var promptEl = el('hm-te-ai-prompt');
                    if (promptEl) postData.ai_system_prompt = promptEl.value;

                    jQuery.post(HM.ajax_url, postData, function(r) {
                        if (r.success) {
                            if (reload) { location.reload(); return; }
                            btn.textContent = '\u2713 Saved (v' + r.data.version + ')';
                            setTimeout(function() { btn.textContent = 'Save & Version'; btn.disabled = false; }, 2000);
                            updateSchema();
                        } else {
                            alert(r.data || 'Error');
                            btn.textContent = 'Save & Version'; btn.disabled = false;
                        }
                    });
                }
            };

            /* ── GDPR consent ── */
            window.hmGdprConsent = {
                _callback: null,
                require: function(cb) {
                    hmGdprConsent._callback = cb;
                    el('hm-gdpr-consent-cb').checked = false;
                    el('hm-gdpr-proceed-btn').disabled = true;
                    el('hm-gdpr-consent-modal').classList.add('open');
                },
                close: function() {
                    el('hm-gdpr-consent-modal').classList.remove('open');
                    hmGdprConsent._callback = null;
                },
                proceed: function() {
                    if (!el('hm-gdpr-consent-cb').checked) return;
                    el('hm-gdpr-consent-modal').classList.remove('open');
                    if (typeof hmGdprConsent._callback === 'function') hmGdprConsent._callback();
                    hmGdprConsent._callback = null;
                }
            };
            el('hm-gdpr-consent-cb').addEventListener('change', function() {
                el('hm-gdpr-proceed-btn').disabled = !this.checked;
            });

            /* ── Drag-reorder sections ── */
            (function() {
                var container = el('hm-te-sections');
                if (!container) return;
                var dragEl = null, placeholder = null;
                container.addEventListener('mousedown', function(e) {
                    var handle = e.target.closest('.hm-te-sec-drag');
                    if (!handle) return;
                    dragEl = handle.closest('.hm-te-sec');
                    if (!dragEl) return;
                    e.preventDefault();
                    placeholder = document.createElement('div');
                    placeholder.style.cssText = 'height:4px;background:var(--hm-teal);border-radius:2px;margin:4px 0;';
                    dragEl.style.opacity = '0.4';
                    var move = function(ev) {
                        var y = ev.clientY, inserted = false;
                        container.querySelectorAll('.hm-te-sec').forEach(function(sec) {
                            if (sec === dragEl) return;
                            var r = sec.getBoundingClientRect();
                            if (y < r.top + r.height/2 && !inserted) { container.insertBefore(placeholder, sec); inserted = true; }
                        });
                        if (!inserted) container.appendChild(placeholder);
                    };
                    var up = function() {
                        if (placeholder.parentNode) container.insertBefore(dragEl, placeholder);
                        if (placeholder.parentNode) placeholder.remove();
                        dragEl.style.opacity = '';
                        dragEl = null;
                        document.removeEventListener('mousemove', move);
                        document.removeEventListener('mouseup', up);
                    };
                    document.addEventListener('mousemove', move);
                    document.addEventListener('mouseup', up);
                });
            })();

            /* Initial schema render */
            updateSchema();
        })();
        </script>
        <?php return ob_get_clean();
    }

    /* ── Helper: render a single section card (PHP) ── */
    private function render_section_html( $sec, $idx, $section_types, $field_formats ) {
        $fields      = $sec['fields'] ?? [];
        $is_ai       = in_array( $sec['type'] ?? '', [ 'ai_extract', 'ai_detect' ] );
        $type_label  = $section_types[ $sec['type'] ?? '' ] ?? ucfirst( $sec['type'] ?? 'Unknown' );
        $disabled_cl = empty( $sec['enabled'] ) ? ' hm-te-sec--disabled' : '';
        ?>
        <div class="hm-te-sec<?php echo $disabled_cl; ?>" data-idx="<?php echo $idx; ?>">
            <!-- Header -->
            <div class="hm-te-sec-hd" onclick="hmTE.toggleAccordion(<?php echo $idx; ?>)">
                <span class="hm-te-sec-drag" title="Drag to reorder">&#9776;</span>
                <span class="hm-te-sec-chevron" data-idx="<?php echo $idx; ?>">&#9660;</span>
                <span class="hm-te-sec-title"><?php echo esc_html( $sec['label'] ); ?></span>
                <span class="hm-te-sec-type hm-badge hm-badge--grey"><?php echo esc_html( $type_label ); ?></span>
                <?php if ( $is_ai ): ?>
                <span class="hm-badge hm-badge--purple" style="font-size:10px;">AI</span>
                <?php endif; ?>
                <span class="hm-te-sec-count"><?php echo count( $fields ); ?> field<?php echo count( $fields ) !== 1 ? 's' : ''; ?></span>
                <label class="hm-toggle hm-te-sec-toggle" onclick="event.stopPropagation()">
                    <input type="checkbox" <?php checked( ! empty( $sec['enabled'] ) ); ?> onchange="hmTE.toggleSection(<?php echo $idx; ?>,this.checked)">
                    <span class="hm-toggle-track"></span>
                </label>
                <span class="hm-te-sec-del" onclick="event.stopPropagation();hmTE.deleteSection(<?php echo $idx; ?>)" title="Delete section">&times;</span>
            </div>

            <!-- Body (accordion) -->
            <div class="hm-te-sec-body" data-idx="<?php echo $idx; ?>">
                <!-- Fields list -->
                <div class="hm-te-fields-list" data-sec="<?php echo $idx; ?>">
                    <?php foreach ( $fields as $fi => $f ): ?>
                    <div class="hm-te-field-row" data-fi="<?php echo $fi; ?>">
                        <span class="hm-te-field-drag" title="Drag">&#9776;</span>
                        <span class="hm-te-field-label"><?php echo esc_html( $f['label'] ?? $f ); ?></span>
                        <span class="hm-te-field-format hm-badge hm-badge--grey"><?php echo esc_html( $f['format'] ?? 'text' ); ?></span>
                        <?php if ( ! empty( $f['required'] ) ): ?>
                        <span class="hm-badge hm-badge--red" style="font-size:10px;">Required</span>
                        <?php endif; ?>
                        <?php if ( ! empty( $f['ai_instruction'] ) ): ?>
                        <span class="hm-badge hm-badge--purple" style="font-size:10px;" title="<?php echo esc_attr( $f['ai_instruction'] ); ?>">AI</span>
                        <?php endif; ?>
                        <span class="hm-te-field-del" onclick="hmTE.removeField(<?php echo $idx; ?>,<?php echo $fi; ?>)" title="Remove">&times;</span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button class="hm-btn hm-btn--sm" style="margin-top:8px;" onclick="hmTE.addField(<?php echo $idx; ?>)">+ Add Field</button>
            </div>
        </div>
        <?php
    }

    /* ════════════════════════════════════════════════════════════════════════
       AJAX Handlers
       ════════════════════════════════════════════════════════════════════════ */

    /* ── Save document type metadata (unchanged logic) ── */
    public function ajax_save() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Permission denied' ); return; }
        $this->ensure_table();

        $doc_id = intval( $_POST['doc_id'] ?? 0 );
        $name   = sanitize_text_field( $_POST['name'] ?? '' );
        if ( ! $name ) { wp_send_json_error( 'Name is required' ); return; }

        $data = [
            'name'             => $name,
            'category'         => sanitize_text_field( $_POST['category'] ?? 'clinical' ),
            'ai_enabled'       => intval( $_POST['ai_enabled'] ?? 0 ) ? true : false,
            'password_protect' => intval( $_POST['password_protect'] ?? 1 ) ? true : false,
            'sort_order'       => intval( $_POST['sort_order'] ?? 0 ),
            'updated_at'       => current_time( 'mysql' ),
        ];

        if ( $doc_id ) {
            $result = HearMed_DB::update( $this->table, $data, [ 'id' => $doc_id ] );
        } else {
            $data['sections_json']    = wp_json_encode( self::$default_sections );
            $data['ai_system_prompt'] = '';
            $data['current_version']  = 1;
            $data['created_at']       = current_time( 'mysql' );
            $result = HearMed_DB::insert( $this->table, $data );
            $doc_id = $result ?: 0;
        }

        if ( $result === false ) {
            wp_send_json_error( HearMed_DB::last_error() ?: 'Database error' );
        } else {
            wp_send_json_success( [ 'id' => $doc_id ] );
        }
    }

    /* ── Soft-delete ── */
    public function ajax_delete() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Permission denied' ); return; }

        $doc_id = intval( $_POST['doc_id'] ?? 0 );
        if ( ! $doc_id ) { wp_send_json_error( 'Invalid document type' ); return; }

        $result = HearMed_DB::update( $this->table, [
            'is_active'  => false,
            'updated_at' => current_time( 'mysql' ),
        ], [ 'id' => $doc_id ] );

        if ( $result === false ) {
            wp_send_json_error( HearMed_DB::last_error() ?: 'Database error' );
        } else {
            wp_send_json_success();
        }
    }

    /* ════════════════════════════════════════════════════════════════════════
       JOB 3 — Save sections + AI prompt, create version snapshot
       ════════════════════════════════════════════════════════════════════════ */
    public function ajax_save_sections() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( 'Permission denied' ); return; }

        $doc_id = intval( $_POST['doc_id'] ?? 0 );
        if ( ! $doc_id ) { wp_send_json_error( 'Invalid document type' ); return; }

        $this->ensure_table();

        $sections = json_decode( stripslashes( $_POST['sections'] ?? '[]' ), true );
        if ( ! is_array( $sections ) ) { wp_send_json_error( 'Invalid sections data' ); return; }

        $ai_prompt = sanitize_textarea_field( $_POST['ai_system_prompt'] ?? '' );

        /* ── Sanitize sections (new rich format) ── */
        $clean = [];
        foreach ( $sections as $s ) {
            $sec = [
                'key'     => sanitize_key( $s['key'] ?? '' ),
                'label'   => sanitize_text_field( $s['label'] ?? '' ),
                'type'    => sanitize_text_field( $s['type'] ?? 'text' ),
                'enabled' => ! empty( $s['enabled'] ),
                'sort'    => intval( $s['sort'] ?? 0 ),
                'fields'  => [],
            ];

            /* Sanitize fields (rich objects) */
            if ( ! empty( $s['fields'] ) && is_array( $s['fields'] ) ) {
                foreach ( $s['fields'] as $f ) {
                    if ( is_string( $f ) ) {
                        // Legacy simple field string — upgrade
                        $sec['fields'][] = [
                            'key'      => sanitize_key( $f ),
                            'label'    => sanitize_text_field( ucwords( str_replace( '_', ' ', $f ) ) ),
                            'required' => false,
                            'format'   => 'text',
                        ];
                    } elseif ( is_array( $f ) ) {
                        $field = [
                            'key'      => sanitize_key( $f['key'] ?? '' ),
                            'label'    => sanitize_text_field( $f['label'] ?? '' ),
                            'required' => ! empty( $f['required'] ),
                            'format'   => sanitize_text_field( $f['format'] ?? 'text' ),
                        ];
                        if ( ! empty( $f['ai_instruction'] ) ) {
                            $field['ai_instruction'] = sanitize_textarea_field( $f['ai_instruction'] );
                        }
                        $sec['fields'][] = $field;
                    }
                }
            }

            /* Keep legacy ai_keywords if type=ai_detect */
            if ( ( $sec['type'] === 'ai_detect' ) && ! empty( $s['ai_keywords'] ) && is_array( $s['ai_keywords'] ) ) {
                $sec['ai_keywords'] = array_map( 'sanitize_text_field', $s['ai_keywords'] );
            }

            $clean[] = $sec;
        }

        /* ── Get current version & increment ── */
        $doc = HearMed_DB::get_row(
            "SELECT current_version FROM {$this->table} WHERE id = $1",
            [ $doc_id ]
        );
        $prev_version = (int) ( $doc->current_version ?? 0 );
        $new_version  = $prev_version + 1;

        /* ── Update template ── */
        $sections_json = wp_json_encode( $clean );
        $result = HearMed_DB::update( $this->table, [
            'sections_json'    => $sections_json,
            'ai_system_prompt' => $ai_prompt,
            'current_version'  => $new_version,
            'updated_at'       => current_time( 'mysql' ),
        ], [ 'id' => $doc_id ] );

        if ( $result === false ) {
            wp_send_json_error( HearMed_DB::last_error() ?: 'Database error' );
            return;
        }

        /* ── Insert version snapshot ── */
        $staff_id = get_current_user_id();
        HearMed_DB::insert( $this->versions_table, [
            'template_id'     => $doc_id,
            'version'         => $new_version,
            'sections_json'   => $sections_json,
            'ai_system_prompt'=> $ai_prompt,
            'created_by'      => $staff_id ?: null,
            'created_at'      => current_time( 'mysql' ),
        ] );

        wp_send_json_success( [ 'version' => $new_version ] );
    }

    /* ── Get version history ── */
    public function ajax_get_versions() {
        check_ajax_referer( 'hm_nonce', 'nonce' );
        $doc_id = intval( $_POST['doc_id'] ?? 0 );
        if ( ! $doc_id ) { wp_send_json_error( 'Invalid document type' ); return; }

        $this->ensure_table();

        $rows = HearMed_DB::get_results(
            "SELECT version, sections_json, ai_system_prompt, created_at
             FROM {$this->versions_table}
             WHERE template_id = $1
             ORDER BY version DESC
             LIMIT 50",
            [ $doc_id ]
        ) ?: [];

        $out = [];
        foreach ( $rows as $r ) {
            $out[] = [
                'version'          => (int) $r->version,
                'sections_json'    => $r->sections_json,
                'ai_system_prompt' => $r->ai_system_prompt,
                'created_at'       => $r->created_at,
            ];
        }

        wp_send_json_success( $out );
    }
}

new HearMed_Admin_Document_Templates();
