<?php
/**
 * HearMed Admin — Clinical Document Review
 * Shortcode: [hearmed_clinical_review]
 *
 * 50/50 split screen: transcript (left) | editable form (right)
 * Bottom bar: Save Draft / Mark Reviewed / Approve & Generate PDF
 *
 * @package HearMed_Portal
 * @since   5.4.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_Admin_Clinical_Review {

    public function __construct() {
        add_shortcode( 'hearmed_clinical_review', [ $this, 'render' ] );
    }

    public function render() {
        if ( ! is_user_logged_in() ) return '<p>Please log in.</p>';

        $doc_id = intval( $_GET['doc_id'] ?? 0 );
        if ( ! $doc_id ) return '<p>No clinical document selected.</p>';

        // Fetch clinical doc
        $doc = HearMed_DB::get_row(
            "SELECT cd.*, dt.name as template_name, dt.sections_json, dt.ai_enabled
             FROM hearmed_admin.appointment_clinical_docs cd
             LEFT JOIN hearmed_admin.document_templates dt ON dt.id = cd.template_id
             WHERE cd.id = $1",
            [ $doc_id ]
        );
        if ( ! $doc ) return '<p>Clinical document not found.</p>';

        // Fetch transcript
        $transcript = null;
        if ( $doc->transcript_id ) {
            $transcript = HearMed_DB::get_row(
                "SELECT transcript_text, word_count, created_at FROM hearmed_admin.appointment_transcripts WHERE id = $1",
                [ $doc->transcript_id ]
            );
        }

        // Fetch patient
        $patient = HearMed_DB::get_row(
            "SELECT first_name, last_name, h_number, dob FROM hearmed_core.patients WHERE id = $1",
            [ $doc->patient_id ]
        );

        $sections       = json_decode( $doc->sections_json ?: '[]', true );
        $extracted_data = json_decode( $doc->extracted_json ?: '{}', true );
        $reviewed_data  = json_decode( $doc->reviewed_json ?: '{}', true );
        $form_data      = ! empty( $reviewed_data ) ? $reviewed_data : $extracted_data;

        $p_name = $patient ? trim( ( $patient->first_name ?? '' ) . ' ' . ( $patient->last_name ?? '' ) ) : 'Unknown';

        $status_badges = [
            'draft'     => 'grey',
            'extracted' => 'blue',
            'reviewed'  => 'amber',
            'approved'  => 'green',
            'generated' => 'green',
        ];

        ob_start(); ?>
        <div class="hm-admin" id="hm-cr-app">
            <a href="<?php echo esc_url( home_url( '/admin-console/' ) ); ?>" class="hm-back">&larr; Back</a>

            <!-- Header -->
            <div class="hm-page-header">
                <div>
                    <h1 class="hm-page-title">Clinical Review</h1>
                    <div style="display:flex;gap:6px;align-items:center;margin-top:4px;">
                        <span class="hm-badge hm-badge--<?php echo esc_attr( $status_badges[ $doc->status ] ?? 'grey' ); ?>">
                            <?php echo esc_html( ucfirst( $doc->status ) ); ?>
                        </span>
                        <span style="font-size:12px;color:var(--hm-text-muted);">
                            <?php echo esc_html( $doc->template_name ?? 'Document' ); ?>
                            &middot;
                            <?php echo esc_html( $p_name ); ?>
                            (<?php echo esc_html( $patient->h_number ?? '' ); ?>)
                        </span>
                    </div>
                </div>
            </div>

            <!-- Split layout -->
            <div class="hm-cr-split">

                <!-- LEFT: Transcript -->
                <div class="hm-cr-left">
                    <div class="hm-card">
                        <div class="hm-card-hd">
                            <strong>Transcript</strong>
                            <?php if ( $transcript ): ?>
                            <span class="hm-badge hm-badge--grey" style="font-size:10px;">
                                <?php echo (int) $transcript->word_count; ?> words
                            </span>
                            <?php endif; ?>
                        </div>
                        <div class="hm-card-body hm-cr-transcript-body">
                            <?php if ( $transcript ): ?>
                                <pre class="hm-cr-transcript-text"><?php echo esc_html( $transcript->transcript_text ); ?></pre>
                            <?php else: ?>
                                <p style="color:var(--hm-text-muted);font-size:13px;">No transcript available for this document.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Editable Form -->
                <div class="hm-cr-right">
                    <div class="hm-card">
                        <div class="hm-card-hd">
                            <strong>Extracted Data</strong>
                            <span class="hm-badge hm-badge--purple" style="font-size:10px;">Editable</span>
                        </div>
                        <div class="hm-card-body">
                            <form id="hm-cr-form">
                            <?php
                            if ( is_array( $sections ) ) {
                                foreach ( $sections as $sec ) {
                                    if ( empty( $sec['enabled'] ) ) continue;
                                    $sec_key  = $sec['key'] ?? '';
                                    $sec_data = $form_data[ $sec_key ] ?? [];
                                    $sec_type = $sec['type'] ?? 'text';

                                    // Skip auto-fill and chart sections in review form
                                    if ( in_array( $sec_type, [ 'auto', 'chart', 'signature' ] ) ) continue;

                                    echo '<div class="hm-cr-section">';
                                    echo '<h4 class="hm-cr-section-title">' . esc_html( $sec['label'] ?? '' ) . '</h4>';

                                    $fields = $sec['fields'] ?? [];
                                    if ( ! empty( $fields ) && is_array( $fields ) ) {
                                        foreach ( $fields as $f ) {
                                            $fkey   = is_array( $f ) ? ( $f['key'] ?? '' ) : $f;
                                            $flabel = is_array( $f ) ? ( $f['label'] ?? ucwords( str_replace( '_', ' ', $fkey ) ) ) : ucwords( str_replace( '_', ' ', $f ) );
                                            $format = is_array( $f ) ? ( $f['format'] ?? 'text' ) : 'text';
                                            $req    = is_array( $f ) && ! empty( $f['required'] );
                                            $ai_ins = is_array( $f ) ? ( $f['ai_instruction'] ?? '' ) : '';
                                            $val    = '';
                                            if ( is_array( $sec_data ) && isset( $sec_data[ $fkey ] ) ) {
                                                $val = $sec_data[ $fkey ];
                                            }

                                            echo '<div class="hm-form-group">';
                                            echo '<label>' . esc_html( $flabel );
                                            if ( $req ) echo ' <span style="color:var(--hm-red);">*</span>';
                                            if ( $ai_ins ) echo ' <span class="hm-badge hm-badge--purple" style="font-size:9px;vertical-align:middle;" title="' . esc_attr( $ai_ins ) . '">AI</span>';
                                            echo '</label>';

                                            if ( $format === 'textarea' ) {
                                                echo '<textarea name="' . esc_attr( $sec_key . '__' . $fkey ) . '" rows="3"' . ( $req ? ' required' : '' ) . '>' . esc_textarea( $val ) . '</textarea>';
                                            } elseif ( $format === 'boolean' ) {
                                                echo '<select name="' . esc_attr( $sec_key . '__' . $fkey ) . '">';
                                                echo '<option value="">— Select —</option>';
                                                echo '<option value="Yes"' . selected( $val, 'Yes', false ) . '>Yes</option>';
                                                echo '<option value="No"' . selected( $val, 'No', false ) . '>No</option>';
                                                echo '</select>';
                                            } else {
                                                $input_type = 'text';
                                                if ( $format === 'date' ) $input_type = 'date';
                                                if ( $format === 'email' ) $input_type = 'email';
                                                if ( $format === 'number' ) $input_type = 'number';
                                                echo '<input type="' . esc_attr( $input_type ) . '" name="' . esc_attr( $sec_key . '__' . $fkey ) . '" value="' . esc_attr( $val ) . '"' . ( $req ? ' required' : '' ) . '>';
                                            }

                                            echo '</div>';
                                        }
                                    } elseif ( is_string( $sec_data ) ) {
                                        echo '<div class="hm-form-group">';
                                        echo '<label>' . esc_html( $sec['label'] ?? 'Notes' ) . '</label>';
                                        echo '<textarea name="' . esc_attr( $sec_key . '__content' ) . '" rows="4">' . esc_textarea( $sec_data ) . '</textarea>';
                                        echo '</div>';
                                    }

                                    echo '</div>';
                                }
                            }
                            ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Bar -->
            <div class="hm-cr-bottom-bar">
                <div class="hm-cr-bottom-left">
                    <span style="font-size:12px;color:var(--hm-text-muted);">
                        Status: <strong><?php echo esc_html( ucfirst( $doc->status ) ); ?></strong>
                    </span>
                </div>
                <div class="hm-cr-bottom-actions">
                    <button class="hm-btn" onclick="hmCR.saveDraft()" id="hm-cr-save-btn">Save Draft</button>
                    <button class="hm-btn" onclick="hmCR.markReviewed()" style="border-color:#f59e0b;color:#92400e;">Mark Reviewed</button>
                    <button class="hm-btn hm-btn--primary" onclick="hmCR.approveAndGenerate()">Approve &amp; Generate PDF</button>
                </div>
            </div>
        </div>

        <!-- PDF Preview Modal -->
        <div class="hm-modal-bg" id="hm-cr-preview-modal">
            <div class="hm-modal hm-modal--xl" style="max-height:90vh;">
                <div class="hm-modal-hd">
                    <h3>PDF Preview</h3>
                    <button class="hm-close" onclick="document.getElementById('hm-cr-preview-modal').classList.remove('open')">&times;</button>
                </div>
                <div class="hm-modal-body" id="hm-cr-preview-body" style="padding:0;">
                    <iframe id="hm-cr-preview-iframe" style="width:100%;height:70vh;border:none;"></iframe>
                </div>
                <div class="hm-modal-ft">
                    <button class="hm-btn" onclick="document.getElementById('hm-cr-preview-modal').classList.remove('open')">Close</button>
                    <a id="hm-cr-download-link" href="#" class="hm-btn hm-btn--primary" download>Download PDF</a>
                </div>
            </div>
        </div>

        <script>
        (function() {
            var _docId = <?php echo (int) $doc_id; ?>;

            function collectFormData() {
                var form = document.getElementById('hm-cr-form');
                var inputs = form.querySelectorAll('input, textarea, select');
                var data = {};
                inputs.forEach(function(inp) {
                    var name = inp.name;
                    if (!name) return;
                    var parts = name.split('__');
                    if (parts.length !== 2) return;
                    var sec = parts[0], field = parts[1];
                    if (!data[sec]) data[sec] = {};
                    data[sec][field] = inp.value;
                });
                return data;
            }

            window.hmCR = {
                saveDraft: function() {
                    var btn = document.getElementById('hm-cr-save-btn');
                    btn.textContent = 'Saving\u2026'; btn.disabled = true;
                    jQuery.post(HM.ajax_url, {
                        action: 'hm_save_clinical_doc',
                        nonce: HM.nonce,
                        doc_id: _docId,
                        reviewed_json: JSON.stringify(collectFormData())
                    }, function(r) {
                        if (r.success) {
                            btn.textContent = '\u2713 Saved';
                            setTimeout(function(){ btn.textContent = 'Save Draft'; btn.disabled = false; }, 1500);
                        } else {
                            alert(r.data || 'Error');
                            btn.textContent = 'Save Draft'; btn.disabled = false;
                        }
                    });
                },

                markReviewed: function() {
                    // Save first, then update status
                    jQuery.post(HM.ajax_url, {
                        action: 'hm_save_clinical_doc',
                        nonce: HM.nonce,
                        doc_id: _docId,
                        reviewed_json: JSON.stringify(collectFormData())
                    }, function(r) {
                        if (!r.success) { alert(r.data || 'Save error'); return; }
                        jQuery.post(HM.ajax_url, {
                            action: 'hm_update_clinical_doc_status',
                            nonce: HM.nonce,
                            doc_id: _docId,
                            status: 'reviewed'
                        }, function(r2) {
                            if (r2.success) location.reload();
                            else alert(r2.data || 'Status error');
                        });
                    });
                },

                approveAndGenerate: function() {
                    if (!confirm('Approve this document and generate the PDF?')) return;
                    // Save, approve, then generate
                    jQuery.post(HM.ajax_url, {
                        action: 'hm_save_clinical_doc',
                        nonce: HM.nonce,
                        doc_id: _docId,
                        reviewed_json: JSON.stringify(collectFormData())
                    }, function(r) {
                        if (!r.success) { alert(r.data || 'Save error'); return; }
                        jQuery.post(HM.ajax_url, {
                            action: 'hm_update_clinical_doc_status',
                            nonce: HM.nonce,
                            doc_id: _docId,
                            status: 'approved'
                        }, function(r2) {
                            if (!r2.success) { alert(r2.data || 'Approve error'); return; }
                            // Generate PDF
                            jQuery.post(HM.ajax_url, {
                                action: 'hm_generate_clinical_pdf',
                                nonce: HM.nonce,
                                doc_id: _docId
                            }, function(r3) {
                                if (r3.success) {
                                    // Show preview or download link
                                    if (r3.data.pdf_url) {
                                        var dl = document.getElementById('hm-cr-download-link');
                                        dl.href = r3.data.pdf_url;
                                        document.getElementById('hm-cr-preview-modal').classList.add('open');
                                        var iframe = document.getElementById('hm-cr-preview-iframe');
                                        iframe.src = r3.data.pdf_url;
                                        if (r3.data.password) {
                                            alert('PDF generated! Password: ' + r3.data.password);
                                        }
                                    }
                                } else {
                                    alert(r3.data || 'PDF generation error');
                                }
                            });
                        });
                    });
                }
            };
        })();
        </script>
        <?php return ob_get_clean();
    }
}

new HearMed_Admin_Clinical_Review();
