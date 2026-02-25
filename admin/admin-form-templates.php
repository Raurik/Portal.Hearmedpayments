<?php
/**
 * HearMed Admin — Form Templates Editor
 * Shortcode: [hearmed_form_templates]
 *
 * Manage the consent and clinical form templates stored in
 * hearmed_admin.form_templates. Staff can edit the HTML body,
 * add/remove fields, toggle forms on/off, and add new templates.
 *
 * Add to Admin Console → Documents & Forms → Form Templates
 * WordPress page slug: /admin-console/form-templates/
 *
 * @package HearMed_Portal
 * @since   2.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_Admin_Form_Templates {

    private $table = 'hearmed_admin.form_templates';

    public function __construct() {
        add_shortcode( 'hearmed_form_templates', [ $this, 'render' ] );
        add_action( 'wp_ajax_hm_ft_save',        [ $this, 'ajax_save' ] );
        add_action( 'wp_ajax_hm_ft_delete',      [ $this, 'ajax_delete' ] );
        add_action( 'wp_ajax_hm_ft_toggle',      [ $this, 'ajax_toggle' ] );
        add_action( 'wp_ajax_hm_ft_get',         [ $this, 'ajax_get' ] );
        add_action( 'wp_ajax_hm_ft_save_fields', [ $this, 'ajax_save_fields' ] );
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function get_templates() {
        return HearMed_DB::get_results(
            "SELECT id, name, form_type, description, requires_signature,
                    is_active, sort_order,
                    LENGTH(body_html) AS html_length,
                    json_array_length(fields_schema::json) AS field_count
             FROM {$this->table}
             ORDER BY sort_order, name"
        ) ?: [];
    }

    private function type_label( $type ) {
        $map = [
            'consent'  => 'Consent',
            'clinical' => 'Clinical',
            'intake'   => 'Intake',
            'other'    => 'Other',
        ];
        return $map[ $type ] ?? ucfirst( $type );
    }

    private function type_color( $type ) {
        $map = [
            'consent'  => 'purple',
            'clinical' => 'blue',
            'intake'   => 'teal',
            'other'    => 'gray',
        ];
        return $map[ $type ] ?? 'gray';
    }

    // ─── Main list render ─────────────────────────────────────────────────

    public function render() {
        if ( ! is_user_logged_in() ) return '<p>Please log in.</p>';
        if ( ! HearMed_Auth::can( 'edit_accounting' ) && ! in_array( HearMed_Auth::current_role(), [ 'c_level', 'admin' ] ) ) {
            return '<div class="hm-notice hm-notice--error">Access denied.</div>';
        }

        $templates = $this->get_templates();
        $nonce     = wp_create_nonce( 'hearmed_nonce' );

        ob_start(); ?>
        <style>
        .hm-ft-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px;margin-top:16px;}
        .hm-ft-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:16px;position:relative;transition:border-color .15s,box-shadow .15s;}
        .hm-ft-card:hover{border-color:#0BB4C4;box-shadow:0 2px 8px rgba(11,180,196,.12);}
        .hm-ft-card.inactive{opacity:.55;}
        .hm-ft-card h4{font-size:14px;font-weight:600;margin:0 0 6px;color:#151B33;}
        .hm-ft-meta{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:8px;}
        .hm-ft-desc{font-size:12px;color:#64748b;margin-bottom:8px;line-height:1.4;}
        .hm-ft-stats{font-size:11px;color:#94a3b8;}
        .hm-ft-actions{position:absolute;top:10px;right:10px;display:flex;gap:4px;}
        .hm-ft-btn{font-size:11px;color:#64748b;cursor:pointer;padding:2px 7px;border-radius:4px;border:1px solid transparent;background:none;}
        .hm-ft-btn:hover{border-color:#e2e8f0;background:#f8fafc;color:#151B33;}
        .hm-ft-btn--danger:hover{color:#ef4444;border-color:#fecaca;}
        .hm-ft-add{border:2px dashed #e2e8f0;border-radius:10px;padding:24px;text-align:center;cursor:pointer;color:#94a3b8;font-size:13px;transition:border-color .15s;}
        .hm-ft-add:hover{border-color:#0BB4C4;color:#0BB4C4;}
        /* Editor modal */
        #hm-ft-editor-modal .hm-modal__box{max-width:860px;}
        .hm-ft-editor-tabs{display:flex;gap:0;border-bottom:1px solid #e2e8f0;margin-bottom:1rem;}
        .hm-ft-editor-tab{padding:8px 18px;font-size:13px;cursor:pointer;border-bottom:2px solid transparent;color:#64748b;background:none;border-top:none;border-left:none;border-right:none;}
        .hm-ft-editor-tab.active{border-bottom-color:#0BB4C4;color:#0BB4C4;font-weight:600;}
        .hm-ft-editor-pane{display:none;}
        .hm-ft-editor-pane.active{display:block;}
        #hm-ft-html-editor{width:100%;min-height:380px;font-family:monospace;font-size:12px;line-height:1.5;border:1px solid #e2e8f0;border-radius:6px;padding:12px;resize:vertical;color:#151B33;}
        .hm-ft-placeholder-list{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;}
        .hm-ft-ph{font-size:11px;background:#f0fdfe;color:#0e7490;border:1px solid #a5f3fc;border-radius:4px;padding:2px 8px;cursor:pointer;font-family:monospace;}
        .hm-ft-ph:hover{background:#0BB4C4;color:#fff;}
        /* Fields editor */
        .hm-ft-field-row{display:grid;grid-template-columns:1fr 120px 80px 32px;gap:8px;align-items:center;margin-bottom:6px;}
        .hm-ft-field-row input,.hm-ft-field-row select{font-size:12px;}
        .hm-ft-field-hd{display:grid;grid-template-columns:1fr 120px 80px 32px;gap:8px;margin-bottom:6px;}
        .hm-ft-field-hd span{font-size:11px;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;}
        .hm-ft-preview-wrap{border:1px solid #e2e8f0;border-radius:6px;padding:1rem;background:#fafafa;max-height:460px;overflow-y:auto;font-size:13px;line-height:1.65;}
        </style>

        <div class="hm-admin">
            <div style="margin-bottom:16px;">
                <a href="<?php echo esc_url( home_url( '/admin-console/' ) ); ?>" class="hm-btn">&larr; Back</a>
            </div>
            <div class="hm-admin-hd">
                <h2>Form Templates</h2>
                <button class="hm-btn hm-btn-teal" onclick="hmFT.openAdd()">+ New Template</button>
            </div>
            <p style="font-size:12px;color:#64748b;margin-bottom:4px;">
                These are the forms staff can open for patients — GDPR consent, treatment consent, case history, etc.
                Click a card to edit the HTML and fields. Changes take effect immediately.
            </p>
            <p style="font-size:12px;color:#64748b;margin-bottom:16px;">
                <strong>Available placeholders:</strong>
                <code>{{patient_full_name}}</code> <code>{{patient_dob}}</code> <code>{{today_date}}</code>
                <code>{{clinic_name}}</code> <code>{{clinic_email}}</code> <code>{{signature}}</code>
            </p>

            <div class="hm-ft-grid">
                <?php foreach ( $templates as $t ) :
                    $color  = $this->type_color( $t->form_type );
                    $label  = $this->type_label( $t->form_type );
                    $fields = intval( $t->field_count ?? 0 );
                    $chars  = intval( $t->html_length ?? 0 );
                ?>
                <div class="hm-ft-card <?php echo ! $t->is_active ? 'inactive' : ''; ?>">
                    <div class="hm-ft-actions">
                        <button class="hm-ft-btn" onclick="hmFT.openEdit(<?php echo (int) $t->id; ?>)" title="Edit">✏</button>
                        <button class="hm-ft-btn" onclick="hmFT.toggle(<?php echo (int) $t->id; ?>, <?php echo $t->is_active ? '0' : '1'; ?>)"
                                title="<?php echo $t->is_active ? 'Deactivate' : 'Activate'; ?>">
                            <?php echo $t->is_active ? '⏸' : '▶'; ?>
                        </button>
                        <button class="hm-ft-btn hm-ft-btn--danger" onclick="hmFT.del(<?php echo (int) $t->id; ?>, '<?php echo esc_js( $t->name ); ?>')" title="Delete">✕</button>
                    </div>
                    <h4><?php echo esc_html( $t->name ); ?></h4>
                    <div class="hm-ft-meta">
                        <span class="hm-badge hm-badge-<?php echo esc_attr( $color ); ?>"><?php echo esc_html( $label ); ?></span>
                        <?php if ( $t->requires_signature ) : ?>
                            <span style="font-size:11px;color:#64748b;">✍ Signature</span>
                        <?php endif; ?>
                        <?php if ( ! $t->is_active ) : ?>
                            <span class="hm-badge hm-badge-gray">Inactive</span>
                        <?php endif; ?>
                    </div>
                    <?php if ( $t->description ) : ?>
                    <div class="hm-ft-desc"><?php echo esc_html( $t->description ); ?></div>
                    <?php endif; ?>
                    <div class="hm-ft-stats">
                        <?php echo $fields; ?> field<?php echo $fields !== 1 ? 's' : ''; ?>
                        &nbsp;·&nbsp; <?php echo number_format( $chars ); ?> chars of HTML
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="hm-ft-add" onclick="hmFT.openAdd()">
                    <div style="font-size:1.5rem;margin-bottom:6px;">+</div>
                    Add New Template
                </div>
            </div>
        </div>

        <!-- ── Add / Meta Modal ───────────────────────────────────────── -->
        <div class="hm-modal-bg" id="hm-ft-meta-modal">
            <div class="hm-modal" style="width:480px;">
                <div class="hm-modal-hd">
                    <h3 id="hm-ft-meta-title">New Template</h3>
                    <button class="hm-modal-x" onclick="hmFT.closeMeta()">&times;</button>
                </div>
                <div class="hm-modal-body">
                    <input type="hidden" id="hm-ft-meta-id" value="">
                    <div class="hm-form-group">
                        <label>Template Name *</label>
                        <input type="text" id="hm-ft-meta-name" placeholder="e.g. GDPR Consent Form">
                    </div>
                    <div class="hm-form-group">
                        <label>Type</label>
                        <select id="hm-ft-meta-type">
                            <option value="consent">Consent</option>
                            <option value="clinical">Clinical</option>
                            <option value="intake">Intake</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="hm-form-group">
                        <label>Description (shown in picker)</label>
                        <input type="text" id="hm-ft-meta-desc" placeholder="Short description for staff">
                    </div>
                    <div class="hm-form-group">
                        <label class="hm-toggle-label">
                            <input type="checkbox" id="hm-ft-meta-sig" checked> Requires patient signature
                        </label>
                    </div>
                    <div class="hm-form-group">
                        <label>Sort Order</label>
                        <input type="number" id="hm-ft-meta-sort" value="0" min="0" style="max-width:80px;">
                    </div>
                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn" onclick="hmFT.closeMeta()">Cancel</button>
                    <button class="hm-btn hm-btn-teal" onclick="hmFT.saveMeta()" id="hm-ft-meta-save">Save &amp; Edit Template →</button>
                </div>
            </div>
        </div>

        <!-- ── Full Editor Modal ──────────────────────────────────────── -->
        <div class="hm-modal-bg" id="hm-ft-editor-modal">
            <div class="hm-modal hm-modal--xl" style="width:860px;max-height:92vh;display:flex;flex-direction:column;">
                <div class="hm-modal-hd" style="flex-shrink:0;">
                    <h3 id="hm-ft-editor-title">Edit Template</h3>
                    <div style="display:flex;gap:8px;">
                        <button class="hm-btn hm-btn-sm hm-btn-teal" onclick="hmFT.saveEditor()" id="hm-ft-editor-save">Save Template</button>
                        <button class="hm-modal-x" onclick="hmFT.closeEditor()">&times;</button>
                    </div>
                </div>
                <input type="hidden" id="hm-ft-editor-id" value="">

                <div class="hm-ft-editor-tabs" style="flex-shrink:0;padding:0 1rem;">
                    <button class="hm-ft-editor-tab active" onclick="hmFT.switchTab('html',this)">HTML Body</button>
                    <button class="hm-ft-editor-tab" onclick="hmFT.switchTab('fields',this)">Fields</button>
                    <button class="hm-ft-editor-tab" onclick="hmFT.switchTab('preview',this)">Preview</button>
                </div>

                <div style="overflow-y:auto;flex:1;padding:1rem;">

                    <!-- HTML Tab -->
                    <div class="hm-ft-editor-pane active" id="hm-ft-pane-html">
                        <p style="font-size:12px;color:#64748b;margin-bottom:8px;">
                            Write plain HTML. Click any placeholder below to insert it at the cursor.
                        </p>
                        <div class="hm-ft-placeholder-list">
                            <?php
                            $placeholders = [
                                '{{patient_full_name}}', '{{patient_dob}}', '{{patient_number}}',
                                '{{today_date}}', '{{clinic_name}}', '{{clinic_email}}',
                                '{{clinic_phone}}', '{{clinic_address}}', '{{signature}}',
                            ];
                            foreach ( $placeholders as $ph ) :
                            ?>
                            <span class="hm-ft-ph" onclick="hmFT.insertPlaceholder('<?php echo esc_js( $ph ); ?>')"><?php echo esc_html( $ph ); ?></span>
                            <?php endforeach; ?>
                        </div>
                        <textarea id="hm-ft-html-editor" spellcheck="false"></textarea>
                        <p style="font-size:11px;color:#94a3b8;margin-top:6px;">
                            Use <code>{{signature}}</code> where you want the signature pad to appear.
                            Use <code>&lt;div data-field="marketing_email"&gt;</code> etc. for inline consent checkboxes.
                        </p>
                    </div>

                    <!-- Fields Tab -->
                    <div class="hm-ft-editor-pane" id="hm-ft-pane-fields">
                        <p style="font-size:12px;color:#64748b;margin-bottom:12px;">
                            Fields appear below the HTML body. Use for structured data entry (case history questions, etc.).
                            Consent checkboxes go directly in the HTML — no need to add them here.
                        </p>
                        <div class="hm-ft-field-hd">
                            <span>Label</span><span>Type</span><span>Required</span><span></span>
                        </div>
                        <div id="hm-ft-fields-list"></div>
                        <button class="hm-btn hm-btn-sm" style="margin-top:8px;" onclick="hmFT.addField()">+ Add Field</button>
                    </div>

                    <!-- Preview Tab -->
                    <div class="hm-ft-editor-pane" id="hm-ft-pane-preview">
                        <p style="font-size:12px;color:#64748b;margin-bottom:10px;">
                            Approximate preview — placeholders shown as sample values. Signature pad not rendered here.
                        </p>
                        <div class="hm-ft-preview-wrap" id="hm-ft-preview-content"></div>
                    </div>

                </div>

                <div style="padding:0.75rem 1rem;border-top:1px solid #e2e8f0;flex-shrink:0;display:flex;justify-content:space-between;align-items:center;">
                    <span id="hm-ft-editor-msg" style="font-size:12px;color:#059669;display:none;">✓ Saved</span>
                    <div style="display:flex;gap:8px;margin-left:auto;">
                        <button class="hm-btn" onclick="hmFT.closeEditor()">Close</button>
                        <button class="hm-btn hm-btn-teal" onclick="hmFT.saveEditor()">Save Template</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
        var hmFT = (function() {
            var ajax  = '<?php echo admin_url('admin-ajax.php'); ?>';
            var nonce = '<?php echo esc_js($nonce); ?>';
            var fields = [];  // current template's field schema

            // ── Meta modal ──────────────────────────────────────────────

            function openAdd() {
                document.getElementById('hm-ft-meta-title').textContent = 'New Template';
                document.getElementById('hm-ft-meta-id').value    = '';
                document.getElementById('hm-ft-meta-name').value  = '';
                document.getElementById('hm-ft-meta-type').value  = 'consent';
                document.getElementById('hm-ft-meta-desc').value  = '';
                document.getElementById('hm-ft-meta-sig').checked = true;
                document.getElementById('hm-ft-meta-sort').value  = '0';
                document.getElementById('hm-ft-meta-save').textContent = 'Save & Edit Template →';
                document.getElementById('hm-ft-meta-modal').classList.add('open');
                document.getElementById('hm-ft-meta-name').focus();
            }

            function closeMeta() {
                document.getElementById('hm-ft-meta-modal').classList.remove('open');
            }

            function saveMeta() {
                var name = document.getElementById('hm-ft-meta-name').value.trim();
                if (!name) { alert('Name is required.'); return; }
                var btn = document.getElementById('hm-ft-meta-save');
                btn.textContent = 'Saving…'; btn.disabled = true;

                var id = document.getElementById('hm-ft-meta-id').value;
                jQuery.post(ajax, {
                    action: 'hm_ft_save',
                    nonce:  nonce,
                    id:     id,
                    name:   name,
                    form_type:          document.getElementById('hm-ft-meta-type').value,
                    description:        document.getElementById('hm-ft-meta-desc').value,
                    requires_signature: document.getElementById('hm-ft-meta-sig').checked ? 1 : 0,
                    sort_order:         document.getElementById('hm-ft-meta-sort').value,
                    meta_only: 1,
                }, function(r) {
                    if (r.success) {
                        closeMeta();
                        openEdit(r.data.id);
                    } else {
                        alert(r.data || 'Error saving.');
                        btn.textContent = 'Save & Edit Template →';
                        btn.disabled = false;
                    }
                });
            }

            // ── Editor modal ─────────────────────────────────────────────

            function openEdit(id) {
                document.getElementById('hm-ft-editor-id').value = id;
                document.getElementById('hm-ft-editor-title').textContent = 'Loading…';
                document.getElementById('hm-ft-editor-modal').classList.add('open');
                switchTab('html', document.querySelector('.hm-ft-editor-tab'));

                jQuery.post(ajax, { action: 'hm_ft_get', nonce: nonce, id: id }, function(r) {
                    if (!r.success) { alert('Could not load template: ' + r.data); return; }
                    var t = r.data;
                    document.getElementById('hm-ft-editor-title').textContent = 'Edit: ' + t.name;
                    document.getElementById('hm-ft-html-editor').value = t.body_html || '';
                    fields = t.fields_schema || [];
                    renderFields();
                });
            }

            function closeEditor() {
                document.getElementById('hm-ft-editor-modal').classList.remove('open');
                location.reload();
            }

            function saveEditor() {
                var id   = document.getElementById('hm-ft-editor-id').value;
                var html = document.getElementById('hm-ft-html-editor').value;
                var btn  = document.getElementById('hm-ft-editor-save');
                var msg  = document.getElementById('hm-ft-editor-msg');
                btn.textContent = 'Saving…'; btn.disabled = true;
                msg.style.display = 'none';

                // Collect fields from DOM
                collectFields();

                jQuery.post(ajax, {
                    action:       'hm_ft_save',
                    nonce:        nonce,
                    id:           id,
                    body_html:    html,
                    fields_schema: JSON.stringify(fields),
                }, function(r) {
                    btn.textContent = 'Save Template'; btn.disabled = false;
                    if (r.success) {
                        msg.style.display = 'inline';
                        setTimeout(function(){ msg.style.display='none'; }, 2500);
                    } else {
                        alert(r.data || 'Error saving.');
                    }
                });
            }

            // ── Tabs ────────────────────────────────────────────────────

            function switchTab(name, btnEl) {
                document.querySelectorAll('.hm-ft-editor-pane').forEach(function(p){ p.classList.remove('active'); });
                document.querySelectorAll('.hm-ft-editor-tab').forEach(function(b){ b.classList.remove('active'); });
                document.getElementById('hm-ft-pane-' + name).classList.add('active');
                if (btnEl) btnEl.classList.add('active');
                if (name === 'preview') renderPreview();
            }

            // ── Placeholder insert ───────────────────────────────────────

            function insertPlaceholder(ph) {
                var ta = document.getElementById('hm-ft-html-editor');
                var start = ta.selectionStart, end = ta.selectionEnd;
                ta.value = ta.value.substring(0, start) + ph + ta.value.substring(end);
                ta.selectionStart = ta.selectionEnd = start + ph.length;
                ta.focus();
            }

            // ── Preview ─────────────────────────────────────────────────

            function renderPreview() {
                var html = document.getElementById('hm-ft-html-editor').value;
                var sample = {
                    '{{patient_full_name}}': 'Mary Murphy',
                    '{{patient_dob}}':       '15/03/1958',
                    '{{patient_number}}':    'C-0042',
                    '{{today_date}}':        new Date().toLocaleDateString('en-IE',{day:'2-digit',month:'short',year:'numeric'}),
                    '{{clinic_name}}':       'HearMed Tullamore',
                    '{{clinic_email}}':      'tullamore@hearmed.ie',
                    '{{clinic_phone}}':      '057 123 4567',
                    '{{clinic_address}}':    '12 Main Street, Tullamore',
                    '{{signature}}':         '<div style="border:2px solid #e2e8f0;border-radius:6px;padding:10px;background:#fafafa;text-align:center;color:#94a3b8;font-size:12px;">[Signature pad appears here]</div>',
                };
                Object.entries(sample).forEach(function(e){ html = html.split(e[0]).join(e[1]); });
                document.getElementById('hm-ft-preview-content').innerHTML = html;
            }

            // ── Fields editor ────────────────────────────────────────────

            function renderFields() {
                var list = document.getElementById('hm-ft-fields-list');
                list.innerHTML = '';
                fields.forEach(function(f, i) {
                    var row = document.createElement('div');
                    row.className = 'hm-ft-field-row';
                    row.dataset.idx = i;
                    row.innerHTML =
                        '<input type="text" class="hm-input hm-input-sm ft-flabel" value="' + esc(f.label) + '" placeholder="Field label">' +
                        '<select class="hm-input hm-input-sm ft-ftype">' +
                            ['text','textarea','select','checkbox','number','date'].map(function(t){
                                return '<option value="' + t + '"' + (f.type===t?' selected':'') + '>' + t + '</option>';
                            }).join('') +
                        '</select>' +
                        '<select class="hm-input hm-input-sm ft-freq">' +
                            '<option value="0"' + (!f.required?' selected':'') + '>Optional</option>' +
                            '<option value="1"' + (f.required?' selected':'') + '>Required</option>' +
                        '</select>' +
                        '<button type="button" class="hm-btn hm-btn-sm" style="color:#ef4444;padding:0 6px;" onclick="hmFT.removeField(' + i + ')">✕</button>';
                    list.appendChild(row);
                });
            }

            function collectFields() {
                var rows = document.querySelectorAll('#hm-ft-fields-list .hm-ft-field-row');
                fields = [];
                rows.forEach(function(row, i) {
                    var label = row.querySelector('.ft-flabel').value.trim();
                    if (!label) return;
                    var id = label.toLowerCase().replace(/[^a-z0-9]+/g,'_').replace(/^_|_$/g,'');
                    fields.push({
                        id:       id + '_' + i,
                        label:    label,
                        type:     row.querySelector('.ft-ftype').value,
                        required: row.querySelector('.ft-freq').value === '1',
                    });
                });
            }

            function addField() {
                collectFields();
                fields.push({ id: 'field_' + fields.length, label: '', type: 'text', required: false });
                renderFields();
                // Focus the new label input
                var rows = document.querySelectorAll('#hm-ft-fields-list .hm-ft-field-row');
                if (rows.length) rows[rows.length-1].querySelector('.ft-flabel').focus();
            }

            function removeField(idx) {
                collectFields();
                fields.splice(idx, 1);
                renderFields();
            }

            // ── Toggle / Delete ──────────────────────────────────────────

            function toggle(id, active) {
                jQuery.post(ajax, { action:'hm_ft_toggle', nonce:nonce, id:id, active:active }, function(r){
                    if (r.success) location.reload();
                    else alert(r.data || 'Error.');
                });
            }

            function del(id, name) {
                if (!confirm('Delete template "' + name + '"?\nThis cannot be undone.')) return;
                jQuery.post(ajax, { action:'hm_ft_delete', nonce:nonce, id:id }, function(r){
                    if (r.success) location.reload();
                    else alert(r.data || 'Error.');
                });
            }

            function esc(s) {
                return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }

            return { openAdd, closeMeta, saveMeta, openEdit, closeEditor, saveEditor,
                     switchTab, insertPlaceholder, addField, removeField, toggle, del };
        })();
        </script>
        <?php return ob_get_clean();
    }

    // ─── AJAX: get single template ────────────────────────────────────────

    public function ajax_get() {
        check_ajax_referer( 'hearmed_nonce', 'nonce' );
        $id  = intval( $_POST['id'] ?? 0 );
        $row = HearMed_DB::get_row(
            "SELECT * FROM {$this->table} WHERE id = \$1", [ $id ]
        );
        if ( ! $row ) wp_send_json_error( 'Not found.' );

        wp_send_json_success( [
            'id'            => $row->id,
            'name'          => $row->name,
            'form_type'     => $row->form_type,
            'description'   => $row->description,
            'body_html'     => $row->body_html,
            'fields_schema' => json_decode( $row->fields_schema ?: '[]', true ),
            'requires_signature' => (bool) $row->requires_signature,
        ] );
    }

    // ─── AJAX: save (meta + HTML + fields) ───────────────────────────────

    public function ajax_save() {
        check_ajax_referer( 'hearmed_nonce', 'nonce' );
        if ( ! in_array( HearMed_Auth::current_role(), [ 'c_level', 'admin' ] ) ) {
            wp_send_json_error( 'Access denied.' );
        }

        $id        = intval( $_POST['id'] ?? 0 );
        $meta_only = ! empty( $_POST['meta_only'] );

        $data = [ 'updated_at' => date( 'Y-m-d H:i:s' ) ];

        // Meta fields (name, type, description, signature, sort)
        if ( isset( $_POST['name'] ) ) {
            $data['name'] = sanitize_text_field( $_POST['name'] );
        }
        if ( isset( $_POST['form_type'] ) ) {
            $data['form_type'] = sanitize_key( $_POST['form_type'] );
        }
        if ( isset( $_POST['description'] ) ) {
            $data['description'] = sanitize_text_field( $_POST['description'] );
        }
        if ( isset( $_POST['requires_signature'] ) ) {
            $data['requires_signature'] = intval( $_POST['requires_signature'] ) ? true : false;
        }
        if ( isset( $_POST['sort_order'] ) ) {
            $data['sort_order'] = intval( $_POST['sort_order'] );
        }

        // HTML body (only on full save)
        if ( ! $meta_only && isset( $_POST['body_html'] ) ) {
            // Allow all HTML — this is admin-only and intentional
            $data['body_html'] = wp_kses_post( stripslashes( $_POST['body_html'] ) );
        }

        // Fields schema
        if ( ! $meta_only && isset( $_POST['fields_schema'] ) ) {
            $raw = json_decode( stripslashes( $_POST['fields_schema'] ), true );
            if ( is_array( $raw ) ) {
                $clean = [];
                foreach ( $raw as $f ) {
                    $clean[] = [
                        'id'       => sanitize_key( $f['id']    ?? '' ),
                        'label'    => sanitize_text_field( $f['label'] ?? '' ),
                        'type'     => sanitize_key( $f['type']   ?? 'text' ),
                        'required' => ! empty( $f['required'] ),
                    ];
                }
                $data['fields_schema'] = json_encode( $clean );
            }
        }

        if ( $id ) {
            $result = HearMed_DB::update( $this->table, $data, [ 'id' => $id ] );
            wp_send_json_success( [ 'id' => $id ] );
        } else {
            // New template — seed with blank body
            $data['body_html']     = $data['body_html']     ?? '<p>Edit this template content.</p>{{signature}}';
            $data['fields_schema'] = $data['fields_schema'] ?? '[]';
            $data['is_active']     = true;
            $data['created_at']    = date( 'Y-m-d H:i:s' );
            $new_id = HearMed_DB::insert( $this->table, $data );
            if ( ! $new_id ) wp_send_json_error( 'Failed to create template.' );
            wp_send_json_success( [ 'id' => $new_id ] );
        }
    }

    // ─── AJAX: toggle active ──────────────────────────────────────────────

    public function ajax_toggle() {
        check_ajax_referer( 'hearmed_nonce', 'nonce' );
        if ( ! in_array( HearMed_Auth::current_role(), [ 'c_level', 'admin' ] ) ) {
            wp_send_json_error( 'Access denied.' );
        }
        $id     = intval( $_POST['id'] ?? 0 );
        $active = ! empty( $_POST['active'] );
        HearMed_DB::update( $this->table, [ 'is_active' => $active ], [ 'id' => $id ] );
        wp_send_json_success();
    }

    // ─── AJAX: delete ─────────────────────────────────────────────────────

    public function ajax_delete() {
        check_ajax_referer( 'hearmed_nonce', 'nonce' );
        if ( ! in_array( HearMed_Auth::current_role(), [ 'c_level', 'admin' ] ) ) {
            wp_send_json_error( 'Access denied.' );
        }
        $id = intval( $_POST['id'] ?? 0 );
        if ( ! $id ) wp_send_json_error( 'Invalid ID.' );
        HearMed_DB::delete( $this->table, [ 'id' => $id ] );
        wp_send_json_success();
    }
}

new HearMed_Admin_Form_Templates();