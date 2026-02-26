<?php
/**
 * HearMed Admin — Document Types & Templates
 * Shortcode: [hearmed_document_types]
 *
 * Replaces the simple textarea with a full CRUD management page.
 * Each document type can be clicked to configure its PDF template layout.
 *
 * @package HearMed_Portal
 * @since   5.2.0
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Document_Templates {

    /* ──────────────────────────────────────────────
       Default template sections with AI keyword rules
       ────────────────────────────────────────────── */
    private static $default_sections = [
        ['key' => 'patient_details',  'label' => 'Patient Details',   'type' => 'auto',     'enabled' => true, 'sort' => 1,
         'fields' => ['patient_name','h_number','date_of_birth','contact_phone','contact_email','address']],
        ['key' => 'medical_history',  'label' => 'Medical History',   'type' => 'ai_detect', 'enabled' => true, 'sort' => 2,
         'ai_keywords' => ['worked in noise','noise exposure','tinnitus','pain','dizziness','vertigo','ear infection','surgery','family history','medication','diabetes','blood pressure','ototoxic']],
        ['key' => 'hearing_results',  'label' => 'Hearing Test Result', 'type' => 'ai_detect', 'enabled' => true, 'sort' => 3,
         'ai_keywords' => ['mild','moderate','severe','profound','sensorineural','conductive','mixed','normal hearing','high frequency','low frequency']],
        ['key' => 'recommendations',  'label' => 'Recommendations',   'type' => 'ai_detect', 'enabled' => true, 'sort' => 4,
         'ai_keywords' => ['recommend','hearing aid','amplification','referral','ENT','review','follow up','fitting']],
        ['key' => 'audiogram',        'label' => 'Audiogram',         'type' => 'chart',     'enabled' => true, 'sort' => 5, 'fields' => []],
        ['key' => 'dispenser_notes',  'label' => 'Dispenser Notes',   'type' => 'text',      'enabled' => true, 'sort' => 6, 'fields' => []],
        ['key' => 'consent',          'label' => 'Consent & Signature', 'type' => 'signature', 'enabled' => true, 'sort' => 7, 'fields' => []],
    ];

    /* Default document types to seed */
    private static $default_types = [
        ['name' => 'Case History',       'category' => 'clinical',      'ai_enabled' => true],
        ['name' => 'Audiogram',          'category' => 'clinical',      'ai_enabled' => false],
        ['name' => 'Hearing Test',       'category' => 'clinical',      'ai_enabled' => true],
        ['name' => 'Sales Order',        'category' => 'financial',     'ai_enabled' => false],
        ['name' => 'Consent Form',       'category' => 'consent',       'ai_enabled' => false],
        ['name' => 'GP Referral',        'category' => 'referral',      'ai_enabled' => true],
        ['name' => 'ENT Referral',       'category' => 'referral',      'ai_enabled' => true],
        ['name' => 'Repair Form',        'category' => 'service',       'ai_enabled' => false],
        ['name' => 'Fitting Receipt',    'category' => 'financial',     'ai_enabled' => false],
        ['name' => 'Phone Call Log',     'category' => 'communication', 'ai_enabled' => true],
    ];

    private $table = 'hearmed_admin.document_templates';

    public function __construct() {
        add_shortcode('hearmed_document_types', [$this, 'render']);
        add_shortcode('hearmed_document_template_editor', [$this, 'render_editor']);
        add_action('wp_ajax_hm_admin_save_document_type', [$this, 'ajax_save']);
        add_action('wp_ajax_hm_admin_delete_document_type', [$this, 'ajax_delete']);
        add_action('wp_ajax_hm_admin_save_template_sections', [$this, 'ajax_save_sections']);
    }

    /* ──────────────────────────────────────────────
       Auto-migrate: create table if not exists
       ────────────────────────────────────────────── */
    private function ensure_table() {
        $exists = HearMed_DB::get_var(
            "SELECT to_regclass('{$this->table}')"
        );
        if ($exists !== null) return;

        HearMed_DB::query("CREATE TABLE {$this->table} (
            id              SERIAL PRIMARY KEY,
            name            VARCHAR(100) NOT NULL,
            category        VARCHAR(50)  DEFAULT 'clinical',
            ai_enabled      BOOLEAN      DEFAULT false,
            password_protect BOOLEAN     DEFAULT true,
            sections_json   JSONB        DEFAULT '[]'::jsonb,
            is_active       BOOLEAN      DEFAULT true,
            sort_order      INTEGER      DEFAULT 0,
            created_at      TIMESTAMP    DEFAULT NOW(),
            updated_at      TIMESTAMP    DEFAULT NOW()
        )");

        // Seed default types
        foreach (self::$default_types as $i => $dt) {
            HearMed_DB::insert($this->table, [
                'name'          => $dt['name'],
                'category'      => $dt['category'],
                'ai_enabled'    => $dt['ai_enabled'] ? 'true' : 'false',
                'password_protect' => 'true',
                'sections_json' => wp_json_encode(self::$default_sections),
                'sort_order'    => ($i + 1) * 10,
                'created_at'    => current_time('mysql'),
                'updated_at'    => current_time('mysql'),
            ]);
        }
    }

    private function get_types() {
        $this->ensure_table();
        return HearMed_DB::get_results(
            "SELECT * FROM {$this->table} WHERE is_active = true ORDER BY sort_order, name"
        ) ?: [];
    }

    /* ──────────────────────────────────────────────
       Render: Document Types listing
       ────────────────────────────────────────────── */
    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';
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
        $category_colors = [
            'clinical'      => 'blue',
            'financial'     => 'green',
            'consent'       => 'purple',
            'referral'      => 'orange',
            'service'       => 'gray',
            'communication' => 'teal',
            'other'         => 'gray',
        ];

        ob_start(); ?>
        <style>
        .hm-dt-card{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px;margin-top:16px;}
        .hm-dt-item{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px;cursor:pointer;transition:border-color .15s,box-shadow .15s;position:relative;}
        .hm-dt-item:hover{border-color:var(--hm-primary,#3b82f6);box-shadow:0 2px 8px rgba(59,130,246,.12);}
        .hm-dt-item h4{font-size:14px;font-weight:600;margin:0 0 6px 0;color:#1e293b;}
        .hm-dt-meta{display:flex;gap:6px;align-items:center;flex-wrap:wrap;}
        .hm-dt-ai{font-size:11px;color:#8b5cf6;font-weight:500;}
        .hm-dt-sections{font-size:11px;color:var(--hm-text-light);margin-top:8px;}
        .hm-dt-actions{position:absolute;top:10px;right:10px;display:flex;gap:4px;}
        .hm-dt-edit-inline{font-size:11px;color:var(--hm-text-light);cursor:pointer;padding:2px 6px;border-radius:4px;border:1px solid transparent;}
        .hm-dt-edit-inline:hover{border-color:#e2e8f0;background:#f8fafc;}
        .hm-gdpr-note{background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:12px;color:#92400e;display:flex;align-items:flex-start;gap:8px;}
        .hm-gdpr-note svg{flex-shrink:0;margin-top:1px;}
        </style>

        <div class="hm-admin">
            <div style="margin-bottom:16px;"><a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-btn">&larr; Back</a></div>
            <div class="hm-admin-hd">
                <h2>Document Types & Templates</h2>
                <button class="hm-btn hm-btn--primary" onclick="hmDocTypes.openAdd()">+ Add Document Type</button>
            </div>

            <div class="hm-gdpr-note">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 9v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                <div>
                    <strong>GDPR Compliance:</strong> All generated documents are password-protected.
                    AI transcription uses patient H-numbers only — no names in audio processing.
                    A consent checkbox with privacy policy link is required before any PDF download.
                </div>
            </div>

            <p style="color:var(--hm-text-light);font-size:12px;margin-bottom:8px;">Click a document type to configure its PDF template sections and layout.</p>

            <div class="hm-dt-card">
                <?php foreach ($types as $t):
                    $sections = json_decode($t->sections_json ?: '[]', true);
                    $enabled_count = 0;
                    if (is_array($sections)) {
                        foreach ($sections as $s) {
                            if (!empty($s['enabled'])) $enabled_count++;
                        }
                    }
                    $cat = $t->category ?: 'other';
                    $color = $category_colors[$cat] ?? 'gray';
                ?>
                <div class="hm-dt-item" onclick="hmDocTypes.configure(<?php echo (int) $t->id; ?>,'<?php echo esc_js($t->name); ?>')">
                    <div class="hm-dt-actions">
                        <span class="hm-dt-edit-inline" onclick="event.stopPropagation();hmDocTypes.openEdit(<?php echo htmlspecialchars(wp_json_encode((array) $t), ENT_QUOTES); ?>)" title="Edit">&#9998;</span>
                        <span class="hm-dt-edit-inline" onclick="event.stopPropagation();hmDocTypes.del(<?php echo (int) $t->id; ?>,'<?php echo esc_js($t->name); ?>')" title="Delete" style="color:#ef4444;">&times;</span>
                    </div>
                    <h4><?php echo esc_html($t->name); ?></h4>
                    <div class="hm-dt-meta">
                        <span class="hm-badge hm-badge-<?php echo esc_attr($color); ?>"><?php echo esc_html($category_labels[$cat] ?? ucfirst($cat)); ?></span>
                        <?php if ($t->ai_enabled): ?>
                            <span class="hm-dt-ai">&#9733; AI Formatting</span>
                        <?php endif; ?>
                        <?php if ($t->password_protect): ?>
                            <span style="font-size:11px;color:#64748b;">&#128274;</span>
                        <?php endif; ?>
                    </div>
                    <div class="hm-dt-sections"><?php echo $enabled_count; ?> section<?php echo $enabled_count !== 1 ? 's' : ''; ?> configured</div>
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
                            <?php foreach ($category_labels as $k => $v): ?>
                            <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="hm-form-group">
                        <label class="hm-toggle-label">
                            <input type="checkbox" id="hm-dt-ai"> AI Transcription Formatting
                        </label>
                        <p style="font-size:11px;color:var(--hm-text-light);margin-top:4px;">When enabled, AI will detect content sections and apply formatting rules automatically.</p>
                    </div>
                    <div class="hm-form-group">
                        <label class="hm-toggle-label">
                            <input type="checkbox" id="hm-dt-pwd" checked> Password-Protected PDF
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
                btn.textContent = 'Saving...'; btn.disabled = true;
                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_save_document_type',
                    nonce: HM.nonce,
                    doc_id: document.getElementById('hm-dt-id').value,
                    name: name,
                    category: document.getElementById('hm-dt-category').value,
                    ai_enabled: document.getElementById('hm-dt-ai').checked ? 1 : 0,
                    password_protect: document.getElementById('hm-dt-pwd').checked ? 1 : 0,
                    sort_order: document.getElementById('hm-dt-sort').value
                }, function(r) {
                    if (r.success) location.reload();
                    else { alert(r.data || 'Error'); btn.textContent = 'Save'; btn.disabled = false; }
                });
            },
            del: function(id, name) {
                if (!confirm('Delete "' + name + '"?')) return;
                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_delete_document_type',
                    nonce: HM.nonce,
                    doc_id: id
                }, function(r) {
                    if (r.success) location.reload();
                    else alert(r.data || 'Error');
                });
            },
            configure: function(id, name) {
                window.location.href = HM.home_url + '/document-template-editor/?doc_type_id=' + id;
            }
        };
        </script>
        <?php return ob_get_clean();
    }

    /* ──────────────────────────────────────────────
       Render: Template Section Editor (per doc type)
       ────────────────────────────────────────────── */
    public function render_editor() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';
        $this->ensure_table();

        $doc_id = intval($_GET['doc_type_id'] ?? 0);
        if (!$doc_id) return '<p>No document type selected.</p>';

        $doc = HearMed_DB::get_row(
            "SELECT * FROM {$this->table} WHERE id = $1 AND is_active = true",
            [$doc_id]
        );
        if (!$doc) return '<p>Document type not found.</p>';

        $sections = json_decode($doc->sections_json ?: '[]', true);
        if (!is_array($sections) || empty($sections)) {
            $sections = self::$default_sections;
        }

        usort($sections, function($a, $b) { return ($a['sort'] ?? 0) - ($b['sort'] ?? 0); });

        $section_types = [
            'auto'      => 'Auto-fill (Patient Data)',
            'ai_detect' => 'AI Keyword Detection',
            'text'      => 'Free Text',
            'chart'     => 'Chart / Image',
            'signature' => 'Consent & Signature',
            'table'     => 'Table / Grid',
        ];

        $privacy_url = get_option('hm_privacy_policy_url', '');

        ob_start(); ?>
        <style>
        .hm-te-section{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:14px 18px;margin-bottom:10px;transition:border-color .15s;}
        .hm-te-section:hover{border-color:#cbd5e1;}
        .hm-te-section.disabled{opacity:.5;}
        .hm-te-hd{display:flex;align-items:center;gap:10px;margin-bottom:8px;}
        .hm-te-drag{cursor:grab;color:#94a3b8;font-size:16px;user-select:none;}
        .hm-te-name{font-weight:600;font-size:13px;flex:1;}
        .hm-te-type{font-size:11px;color:var(--hm-text-light);background:#f1f5f9;padding:2px 8px;border-radius:4px;}
        .hm-te-body{margin-left:26px;}
        .hm-te-keywords{display:flex;flex-wrap:wrap;gap:4px;margin-top:6px;}
        .hm-te-kw{background:#ede9fe;color:#7c3aed;font-size:11px;padding:2px 8px;border-radius:12px;display:inline-flex;align-items:center;gap:4px;}
        .hm-te-kw .kw-x{cursor:pointer;opacity:.6;font-size:13px;}
        .hm-te-kw .kw-x:hover{opacity:1;}
        .hm-te-fields{font-size:11px;color:var(--hm-text-light);margin-top:4px;}
        .hm-te-add-section{border:2px dashed #e2e8f0;border-radius:10px;padding:14px;text-align:center;cursor:pointer;color:var(--hm-text-light);font-size:13px;transition:border-color .15s;}
        .hm-te-add-section:hover{border-color:var(--hm-primary);color:var(--hm-primary);}
        .hm-te-kw-add{display:flex;gap:4px;margin-top:6px;align-items:center;}
        .hm-te-kw-add input{font-size:11px;padding:3px 8px;border:1px solid #e2e8f0;border-radius:4px;width:140px;}
        </style>

        <div class="hm-admin">
            <div style="margin-bottom:16px;"><a href="<?php echo esc_url(home_url("/document-types/")); ?>" class="hm-btn">&larr; Back to Document Types</a></div>
            <div class="hm-admin-hd">
                <h2>Template: <?php echo esc_html($doc->name); ?></h2>
                <button class="hm-btn hm-btn--primary" onclick="hmTemplateEditor.saveAll()" id="hm-te-save">Save Template</button>
            </div>

            <div style="display:flex;gap:6px;margin-bottom:16px;align-items:center;">
                <?php if ($doc->ai_enabled): ?>
                    <span class="hm-badge hm-badge-purple">&#9733; AI Formatting</span>
                <?php endif; ?>
                <?php if ($doc->password_protect): ?>
                    <span class="hm-badge hm-badge--grey">&#128274; Password Protected</span>
                <?php endif; ?>
                <span style="font-size:12px;color:var(--hm-text-light);">Drag sections to reorder. Toggle sections on/off. Add AI keywords for auto-detection.</span>
            </div>

            <div id="hm-te-sections">
            <?php foreach ($sections as $idx => $sec): ?>
                <div class="hm-te-section<?php echo empty($sec['enabled']) ? ' disabled' : ''; ?>" data-idx="<?php echo $idx; ?>">
                    <div class="hm-te-hd">
                        <span class="hm-te-drag" title="Drag to reorder">&#9776;</span>
                        <span class="hm-te-name"><?php echo esc_html($sec['label']); ?></span>
                        <span class="hm-te-type"><?php echo esc_html($section_types[$sec['type']] ?? $sec['type']); ?></span>
                        <label class="hm-toggle-label" style="font-size:12px;" onclick="event.stopPropagation()">
                            <input type="checkbox" class="hm-te-toggle" data-idx="<?php echo $idx; ?>" <?php checked(!empty($sec['enabled'])); ?> onchange="hmTemplateEditor.toggleSection(<?php echo $idx; ?>,this.checked)">
                            Visible
                        </label>
                    </div>
                    <div class="hm-te-body">
                        <?php if (!empty($sec['fields'])): ?>
                        <div class="hm-te-fields">
                            Fields: <?php echo esc_html(implode(', ', array_map(function($f) {
                                return ucwords(str_replace('_', ' ', $f));
                            }, $sec['fields']))); ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($sec['type'] === 'ai_detect' && !empty($sec['ai_keywords'])): ?>
                        <div class="hm-te-keywords" id="hm-te-kw-<?php echo $idx; ?>">
                            <?php foreach ($sec['ai_keywords'] as $kw): ?>
                            <span class="hm-te-kw">
                                <?php echo esc_html($kw); ?>
                                <span class="kw-x" onclick="hmTemplateEditor.removeKeyword(<?php echo $idx; ?>,'<?php echo esc_js($kw); ?>')">&times;</span>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <div class="hm-te-kw-add">
                            <input type="text" placeholder="Add keyword..." id="hm-te-kw-input-<?php echo $idx; ?>" onkeydown="if(event.key==='Enter')hmTemplateEditor.addKeyword(<?php echo $idx; ?>)">
                            <button class="hm-btn hm-btn--sm" onclick="hmTemplateEditor.addKeyword(<?php echo $idx; ?>)">+</button>
                        </div>
                        <?php elseif ($sec['type'] === 'ai_detect'): ?>
                        <div class="hm-te-keywords" id="hm-te-kw-<?php echo $idx; ?>"></div>
                        <div class="hm-te-kw-add">
                            <input type="text" placeholder="Add keyword..." id="hm-te-kw-input-<?php echo $idx; ?>" onkeydown="if(event.key==='Enter')hmTemplateEditor.addKeyword(<?php echo $idx; ?>)">
                            <button class="hm-btn hm-btn--sm" onclick="hmTemplateEditor.addKeyword(<?php echo $idx; ?>)">+</button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>

            <!-- Add new section -->
            <div class="hm-te-add-section" onclick="hmTemplateEditor.addSection()">+ Add Section</div>
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
                            <?php foreach ($section_types as $k => $v): ?>
                            <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($v); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn" onclick="document.getElementById('hm-te-add-modal').classList.remove('open')">Cancel</button>
                    <button class="hm-btn hm-btn--primary" onclick="hmTemplateEditor.confirmAddSection()">Add Section</button>
                </div>
            </div>
        </div>

        <!-- GDPR Consent Download Modal (reusable component) -->
        <div class="hm-modal-bg" id="hm-gdpr-consent-modal">
            <div class="hm-modal hm-modal--sm">
                <div class="hm-modal-hd">
                    <h3>GDPR Consent Required</h3>
                    <button class="hm-close" onclick="hmGdprConsent.close()">&times;</button>
                </div>
                <div class="hm-modal-body">
                    <p style="font-size:13px;margin-bottom:12px;">Before downloading or generating this document, you must confirm GDPR compliance.</p>
                    <label class="hm-toggle-label" style="font-size:13px;">
                        <input type="checkbox" id="hm-gdpr-consent-cb">
                        I confirm this data access/download is necessary for patient care and complies with our
                        <a href="<?php echo esc_url($privacy_url ?: '#'); ?>" target="_blank" style="color:var(--hm-primary);">GDPR Privacy Policy</a>.
                    </label>
                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn" onclick="hmGdprConsent.close()">Cancel</button>
                    <button class="hm-btn hm-btn--primary" onclick="hmGdprConsent.proceed()" id="hm-gdpr-proceed-btn" disabled>Proceed</button>
                </div>
            </div>
        </div>

        <script>
        /* ── Sections data in JS ── */
        var _teSections = <?php echo wp_json_encode(array_values($sections)); ?>;
        var _teDocId = <?php echo (int) $doc_id; ?>;

        var hmTemplateEditor = {
            toggleSection: function(idx, enabled) {
                if (_teSections[idx]) _teSections[idx].enabled = enabled;
                var el = document.querySelector('.hm-te-section[data-idx="'+idx+'"]');
                if (el) { el.classList.toggle('disabled', !enabled); }
            },
            removeKeyword: function(idx, kw) {
                if (!_teSections[idx] || !_teSections[idx].ai_keywords) return;
                _teSections[idx].ai_keywords = _teSections[idx].ai_keywords.filter(function(k){ return k !== kw; });
                // Re-render keywords
                var container = document.getElementById('hm-te-kw-'+idx);
                if (container) {
                    var html = '';
                    _teSections[idx].ai_keywords.forEach(function(k) {
                        html += '<span class="hm-te-kw">' + k + '<span class="kw-x" onclick="hmTemplateEditor.removeKeyword('+idx+',\''+k.replace(/'/g,"\\'")+'\')">&times;</span></span>';
                    });
                    container.innerHTML = html;
                }
            },
            addKeyword: function(idx) {
                var inp = document.getElementById('hm-te-kw-input-'+idx);
                if (!inp) return;
                var kw = inp.value.trim().toLowerCase();
                if (!kw) return;
                if (!_teSections[idx].ai_keywords) _teSections[idx].ai_keywords = [];
                if (_teSections[idx].ai_keywords.indexOf(kw) !== -1) { inp.value = ''; return; }
                _teSections[idx].ai_keywords.push(kw);
                inp.value = '';
                // Re-render
                var container = document.getElementById('hm-te-kw-'+idx);
                if (container) {
                    var span = document.createElement('span');
                    span.className = 'hm-te-kw';
                    span.innerHTML = kw + '<span class="kw-x" onclick="hmTemplateEditor.removeKeyword('+idx+',\''+kw.replace(/'/g,"\\'")+'\')">&times;</span>';
                    container.appendChild(span);
                }
            },
            addSection: function() {
                document.getElementById('hm-te-new-label').value = '';
                document.getElementById('hm-te-new-type').value = 'text';
                document.getElementById('hm-te-add-modal').classList.add('open');
                document.getElementById('hm-te-new-label').focus();
            },
            confirmAddSection: function() {
                var label = document.getElementById('hm-te-new-label').value.trim();
                if (!label) { alert('Label is required.'); return; }
                var type = document.getElementById('hm-te-new-type').value;
                var key = label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_|_$/g, '');
                var newSec = {
                    key: key,
                    label: label,
                    type: type,
                    enabled: true,
                    sort: _teSections.length + 1,
                    fields: [],
                    ai_keywords: type === 'ai_detect' ? [] : undefined
                };
                _teSections.push(newSec);
                document.getElementById('hm-te-add-modal').classList.remove('open');
                // Simple reload after save to render new section
                hmTemplateEditor.saveAll(true);
            },
            saveAll: function(reload) {
                // Update sort order from DOM order
                var sectionEls = document.querySelectorAll('.hm-te-section');
                sectionEls.forEach(function(el, i) {
                    var idx = parseInt(el.getAttribute('data-idx'));
                    if (_teSections[idx]) _teSections[idx].sort = i + 1;
                });
                var btn = document.getElementById('hm-te-save');
                btn.textContent = 'Saving...'; btn.disabled = true;
                jQuery.post(HM.ajax_url, {
                    action: 'hm_admin_save_template_sections',
                    nonce: HM.nonce,
                    doc_id: _teDocId,
                    sections: JSON.stringify(_teSections)
                }, function(r) {
                    if (r.success) {
                        if (reload) { location.reload(); return; }
                        btn.textContent = '\u2713 Saved';
                        setTimeout(function(){ btn.textContent = 'Save Template'; btn.disabled = false; }, 1500);
                    } else {
                        alert(r.data || 'Error');
                        btn.textContent = 'Save Template'; btn.disabled = false;
                    }
                });
            }
        };

        /* ── GDPR Consent Modal (reusable) ── */
        var hmGdprConsent = {
            _callback: null,
            require: function(callback) {
                hmGdprConsent._callback = callback;
                document.getElementById('hm-gdpr-consent-cb').checked = false;
                document.getElementById('hm-gdpr-proceed-btn').disabled = true;
                document.getElementById('hm-gdpr-consent-modal').classList.add('open');
            },
            close: function() {
                document.getElementById('hm-gdpr-consent-modal').classList.remove('open');
                hmGdprConsent._callback = null;
            },
            proceed: function() {
                if (!document.getElementById('hm-gdpr-consent-cb').checked) return;
                document.getElementById('hm-gdpr-consent-modal').classList.remove('open');
                if (typeof hmGdprConsent._callback === 'function') hmGdprConsent._callback();
                hmGdprConsent._callback = null;
            }
        };
        // Toggle proceed button based on checkbox
        document.getElementById('hm-gdpr-consent-cb').addEventListener('change', function() {
            document.getElementById('hm-gdpr-proceed-btn').disabled = !this.checked;
        });

        /* ── Simple drag-to-reorder via mousedown/mousemove ── */
        (function() {
            var container = document.getElementById('hm-te-sections');
            if (!container) return;
            var dragEl = null, placeholder = null;

            container.addEventListener('mousedown', function(e) {
                if (!e.target.classList.contains('hm-te-drag')) return;
                dragEl = e.target.closest('.hm-te-section');
                if (!dragEl) return;
                e.preventDefault();
                placeholder = document.createElement('div');
                placeholder.style.cssText = 'height:4px;background:var(--hm-primary,#3b82f6);border-radius:2px;margin:4px 0;';
                dragEl.style.opacity = '0.5';

                var moveHandler = function(ev) {
                    var y = ev.clientY;
                    var sections = container.querySelectorAll('.hm-te-section');
                    var inserted = false;
                    sections.forEach(function(sec) {
                        if (sec === dragEl) return;
                        var rect = sec.getBoundingClientRect();
                        if (y < rect.top + rect.height / 2 && !inserted) {
                            container.insertBefore(placeholder, sec);
                            inserted = true;
                        }
                    });
                    if (!inserted) container.appendChild(placeholder);
                };

                var upHandler = function() {
                    if (placeholder.parentNode) {
                        container.insertBefore(dragEl, placeholder);
                        placeholder.remove();
                    }
                    dragEl.style.opacity = '';
                    dragEl = null;
                    document.removeEventListener('mousemove', moveHandler);
                    document.removeEventListener('mouseup', upHandler);
                };

                document.addEventListener('mousemove', moveHandler);
                document.addEventListener('mouseup', upHandler);
            });
        })();
        </script>
        <?php return ob_get_clean();
    }

    /* ──────────────────────────────────────────────
       AJAX Handlers
       ────────────────────────────────────────────── */
    public function ajax_save() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        $this->ensure_table();

        $doc_id  = intval($_POST['doc_id'] ?? 0);
        $name    = sanitize_text_field($_POST['name'] ?? '');
        if (!$name) { wp_send_json_error('Name is required'); return; }

        $data = [
            'name'             => $name,
            'category'         => sanitize_text_field($_POST['category'] ?? 'clinical'),
            'ai_enabled'       => intval($_POST['ai_enabled'] ?? 0) ? true : false,
            'password_protect' => intval($_POST['password_protect'] ?? 1) ? true : false,
            'sort_order'       => intval($_POST['sort_order'] ?? 0),
            'updated_at'       => current_time('mysql'),
        ];

        if ($doc_id) {
            $result = HearMed_DB::update($this->table, $data, ['id' => $doc_id]);
        } else {
            $data['sections_json'] = wp_json_encode(self::$default_sections);
            $data['created_at']    = current_time('mysql');
            $result = HearMed_DB::insert($this->table, $data);
            $doc_id = $result ?: 0;
        }

        if ($result === false) {
            wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
        } else {
            wp_send_json_success(['id' => $doc_id]);
        }
    }

    public function ajax_delete() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        $doc_id = intval($_POST['doc_id'] ?? 0);
        if (!$doc_id) { wp_send_json_error('Invalid document type'); return; }

        $result = HearMed_DB::update($this->table, [
            'is_active'  => false,
            'updated_at' => current_time('mysql'),
        ], ['id' => $doc_id]);

        if ($result === false) {
            wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
        } else {
            wp_send_json_success();
        }
    }

    public function ajax_save_sections() {
        check_ajax_referer('hm_nonce', 'nonce');
        if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

        $doc_id  = intval($_POST['doc_id'] ?? 0);
        if (!$doc_id) { wp_send_json_error('Invalid document type'); return; }

        $sections = json_decode(stripslashes($_POST['sections'] ?? '[]'), true);
        if (!is_array($sections)) { wp_send_json_error('Invalid sections data'); return; }

        // Sanitize sections
        $clean = [];
        foreach ($sections as $s) {
            $clean[] = [
                'key'         => sanitize_key($s['key'] ?? ''),
                'label'       => sanitize_text_field($s['label'] ?? ''),
                'type'        => sanitize_text_field($s['type'] ?? 'text'),
                'enabled'     => !empty($s['enabled']),
                'sort'        => intval($s['sort'] ?? 0),
                'fields'      => isset($s['fields']) && is_array($s['fields']) ? array_map('sanitize_text_field', $s['fields']) : [],
                'ai_keywords' => isset($s['ai_keywords']) && is_array($s['ai_keywords']) ? array_map('sanitize_text_field', $s['ai_keywords']) : [],
            ];
        }

        $result = HearMed_DB::update($this->table, [
            'sections_json' => wp_json_encode($clean),
            'updated_at'    => current_time('mysql'),
        ], ['id' => $doc_id]);

        if ($result === false) {
            wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
        } else {
            wp_send_json_success();
        }
    }
}

new HearMed_Admin_Document_Templates();
