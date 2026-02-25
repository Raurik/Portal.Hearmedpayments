<?php
/**
 * HearMed Patient Forms Module
 *
 * Manages consent forms, clinical forms, and Wacom signature capture.
 * Shortcode: [hearmed_forms patient_id="123"]
 *
 * Tables used:
 *   hearmed_core.patient_forms     — submitted forms
 *   hearmed_admin.form_templates   — editable form templates
 *   hearmed_core.patients          — patient record (consent flags synced back)
 *
 * @package HearMed_Portal
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ---------------------------------------------------------------------------
// Register shortcode
// ---------------------------------------------------------------------------
add_shortcode( 'hearmed_forms', 'hm_forms_render' );

function hm_forms_render( $atts ) {
    if ( ! HearMed_Auth::is_logged_in() ) {
        return '<div class="hm-notice hm-notice--error">Please log in to access forms.</div>';
    }

    $atts       = shortcode_atts( [ 'patient_id' => 0 ], $atts );
    $patient_id = intval( $atts['patient_id'] ?: ( $_GET['patient_id'] ?? 0 ) );

    if ( ! $patient_id ) {
        return '<div class="hm-notice hm-notice--error">No patient specified.</div>';
    }

    return HearMed_Forms::render( $patient_id );
}

// ---------------------------------------------------------------------------
// Register AJAX handlers
// ---------------------------------------------------------------------------
add_action( 'wp_ajax_hm_get_patient_forms',    [ 'HearMed_Forms', 'ajax_get_forms' ] );
add_action( 'wp_ajax_hm_load_form_template',   [ 'HearMed_Forms', 'ajax_load_template' ] );
add_action( 'wp_ajax_hm_submit_form',          [ 'HearMed_Forms', 'ajax_submit_form' ] );
add_action( 'wp_ajax_hm_get_form_view',        [ 'HearMed_Forms', 'ajax_get_form_view' ] );
add_action( 'wp_ajax_hm_invalidate_form',      [ 'HearMed_Forms', 'ajax_invalidate_form' ] );

// ---------------------------------------------------------------------------
// Enqueue assets when shortcode is present
// ---------------------------------------------------------------------------
add_action( 'wp_enqueue_scripts', function () {
    if ( is_page() && has_shortcode( get_post()->post_content, 'hearmed_forms' ) ) {
        wp_enqueue_style(
            'hearmed-forms',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/css/hearmed-forms.css',
            [], '2.0.0'
        );
        wp_enqueue_script(
            'hearmed-forms',
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/hearmed-forms.js',
            [ 'jquery' ], '2.0.0', true
        );
        wp_localize_script( 'hearmed-forms', 'hmFormsConfig', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'hearmed_nonce' ),
        ] );
    }
} );

// ===========================================================================
class HearMed_Forms {

    // ═══════════════════════════════════════════════════════════════════════
    // MAIN RENDER — embedded in patient record
    // ═══════════════════════════════════════════════════════════════════════

    public static function render( $patient_id ) {
        $db      = HearMed_DB::instance();
        $nonce   = wp_create_nonce( 'hearmed_nonce' );
        $role    = HearMed_Auth::current_role();
        $can_inv = in_array( $role, [ 'c_level', 'admin' ] );

        // Patient consent status summary
        $patient = $db->get_row(
            "SELECT id, first_name, last_name, gdpr_consent, marketing_email,
                    marketing_phone, marketing_sms
             FROM hearmed_core.patients WHERE id = $1",
            [ $patient_id ]
        );
        if ( ! $patient ) return '<div class="hm-notice hm-notice--error">Patient not found.</div>';

        // Check which core forms have been completed
        $gdpr_done      = (bool) $db->get_var(
            "SELECT id FROM hearmed_core.patient_forms
             WHERE patient_id=$1 AND form_type='consent' AND form_title LIKE '%GDPR%' AND is_valid=TRUE
             LIMIT 1", [ $patient_id ]
        );
        $treatment_done = (bool) $db->get_var(
            "SELECT id FROM hearmed_core.patient_forms
             WHERE patient_id=$1 AND form_type='consent' AND form_title LIKE '%Treatment%' AND is_valid=TRUE
             LIMIT 1", [ $patient_id ]
        );

        // Active templates for the picker
        $templates = $db->get_results(
            "SELECT id, name, form_type, description, requires_signature
             FROM hearmed_admin.form_templates
             WHERE is_active=TRUE ORDER BY sort_order, name"
        );

        // Submitted forms list
        $forms = $db->get_results(
            "SELECT pf.id, pf.form_title, pf.form_type, pf.is_valid,
                    pf.gdpr_consent, pf.treatment_consent, pf.data_processing_consent,
                    pf.signature_image_url, pf.created_at, pf.invalidated_at,
                    CONCAT(s.first_name,' ',s.last_name) AS created_by_name
             FROM hearmed_core.patient_forms pf
             LEFT JOIN hearmed_reference.staff s ON s.id = pf.created_by
             WHERE pf.patient_id=$1
             ORDER BY pf.created_at DESC",
            [ $patient_id ]
        );

        ob_start(); ?>
        <div id="hm-forms-wrap" class="hearmed-forms" data-patient-id="<?php echo $patient_id; ?>">

            <!-- Consent status bar -->
            <div class="hm-forms-status">
                <div class="hm-forms-status__item <?php echo $gdpr_done ? 'hm-done' : 'hm-missing'; ?>">
                    <?php echo $gdpr_done ? '✓' : '!'; ?> GDPR Consent
                </div>
                <div class="hm-forms-status__item <?php echo $treatment_done ? 'hm-done' : 'hm-missing'; ?>">
                    <?php echo $treatment_done ? '✓' : '!'; ?> Treatment Consent
                </div>
            </div>

            <!-- Add form button -->
            <div class="hm-forms-toolbar">
                <button class="hm-btn hm-btn--primary" onclick="hmForms.openPicker()">
                    + New Form / Consent
                </button>
            </div>

            <!-- Forms history table -->
            <div class="hm-forms-list">
                <?php if ( empty( $forms ) ) : ?>
                <div class="hm-forms-empty">
                    <p>No forms on file yet. Click <strong>+ New Form / Consent</strong> to begin.</p>
                </div>
                <?php else : ?>
                <table class="hm-table hm-forms-table">
                    <thead>
                        <tr>
                            <th>Form</th><th>Type</th><th>Consents</th>
                            <th>Signed</th><th>Date</th><th>By</th><th>Status</th><th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $forms as $form ) : ?>
                    <tr class="<?php echo ! $form->is_valid ? 'hm-row--invalid' : ''; ?>">
                        <td><?php echo esc_html( $form->form_title ?: $form->form_type ); ?></td>
                        <td class="hm-muted"><?php echo esc_html( ucfirst( $form->form_type ) ); ?></td>
                        <td>
                            <?php if ( $form->gdpr_consent      ) echo '<span class="hm-consent-tag hm-consent-tag--yes">GDPR</span> '; ?>
                            <?php if ( $form->treatment_consent  ) echo '<span class="hm-consent-tag hm-consent-tag--yes">Treatment</span> '; ?>
                        </td>
                        <td><?php echo $form->signature_image_url ? '<span class="hm-badge hm-badge--green">Signed</span>' : '<span class="hm-badge hm-badge--grey">No sig</span>'; ?></td>
                        <td class="hm-muted"><?php echo date( 'd M Y', strtotime( $form->created_at ) ); ?></td>
                        <td class="hm-muted"><?php echo esc_html( $form->created_by_name ?: '—' ); ?></td>
                        <td>
                            <?php if ( ! $form->is_valid ) : ?>
                                <span class="hm-badge hm-badge--red">Superseded</span>
                            <?php else : ?>
                                <span class="hm-badge hm-badge--green">Valid</span>
                            <?php endif; ?>
                        </td>
                        <td class="hm-table__actions">
                            <button class="hm-btn hm-btn--sm hm-btn--ghost"
                                    onclick="hmForms.viewForm(<?php echo $form->id; ?>)">View</button>
                            <?php if ( $can_inv && $form->is_valid ) : ?>
                            <button class="hm-btn hm-btn--sm hm-btn--danger"
                                    onclick="hmForms.invalidateForm(<?php echo $form->id; ?>)">Invalidate</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <!-- Template Picker Modal -->
            <div id="hm-picker-modal" class="hm-modal hm-modal--sm" style="display:none;">
                <div class="hm-modal__backdrop" onclick="hmForms.closePicker()"></div>
                <div class="hm-modal__box">
                    <div class="hm-modal__header">
                        <h3>Select Form</h3>
                        <button class="hm-modal__close" onclick="hmForms.closePicker()">✕</button>
                    </div>
                    <div class="hm-modal__body">
                        <?php if ( empty( $templates ) ) : ?>
                        <p class="hm-muted">No form templates configured. Add them in Admin → Form Templates.</p>
                        <?php else : ?>
                        <div class="hm-template-list">
                            <?php foreach ( $templates as $tpl ) : ?>
                            <div class="hm-template-item" onclick="hmForms.loadForm(<?php echo $tpl->id; ?>)">
                                <div class="hm-template-item__name"><?php echo esc_html( $tpl->name ); ?></div>
                                <?php if ( $tpl->description ) : ?>
                                <div class="hm-template-item__desc"><?php echo esc_html( $tpl->description ); ?></div>
                                <?php endif; ?>
                                <?php if ( $tpl->requires_signature ) : ?>
                                <span class="hm-badge hm-badge--blue" style="font-size:0.7rem;">Requires Signature</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Form Fill Modal -->
            <div id="hm-form-modal" class="hm-modal hm-modal--lg" style="display:none;">
                <div class="hm-modal__backdrop"></div>
                <div class="hm-modal__box">
                    <div class="hm-modal__header">
                        <h3 id="hm-form-modal-title">Loading…</h3>
                        <button class="hm-modal__close" onclick="hmForms.closeForm()">✕</button>
                    </div>
                    <div class="hm-modal__body" id="hm-form-modal-body">
                        <div class="hm-loading">Loading form…</div>
                    </div>
                    <div class="hm-modal__footer" id="hm-form-modal-footer" style="display:none;">
                        <button class="hm-btn hm-btn--ghost" onclick="hmForms.closeForm()">Cancel</button>
                        <button class="hm-btn hm-btn--primary" id="hm-submit-form-btn" disabled
                                onclick="hmForms.submitForm()">
                            Submit &amp; Save
                        </button>
                    </div>
                    <div id="hm-form-msg" class="hm-notice" style="display:none;margin:1rem;"></div>
                </div>
            </div>

            <!-- View Submitted Form Modal -->
            <div id="hm-view-modal" class="hm-modal hm-modal--lg" style="display:none;">
                <div class="hm-modal__backdrop" onclick="hmForms.closeView()"></div>
                <div class="hm-modal__box">
                    <div class="hm-modal__header">
                        <h3>Form Record</h3>
                        <div style="display:flex;gap:8px;">
                            <button class="hm-btn hm-btn--ghost hm-btn--sm" onclick="hmForms.printForm()">🖨 Print</button>
                            <button class="hm-modal__close" onclick="hmForms.closeView()">✕</button>
                        </div>
                    </div>
                    <div class="hm-modal__body hm-document-view" id="hm-view-modal-body">
                        <div class="hm-loading">Loading…</div>
                    </div>
                </div>
            </div>

        </div><!-- #hm-forms-wrap -->

        <script>
        // Inline config passed to hearmed-forms.js
        window._hmFormsPatientId = <?php echo $patient_id; ?>;
        window._hmFormsNonce     = '<?php echo esc_js( $nonce ); ?>';
        window._hmFormsAjax      = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
        </script>
        <?php return ob_get_clean();
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX — Get forms list (for refreshing after submit)
    // ═══════════════════════════════════════════════════════════════════════

    public static function ajax_get_forms() {
        check_ajax_referer( 'hearmed_nonce', 'nonce' );
        $patient_id = intval( $_POST['patient_id'] ?? 0 );
        if ( ! $patient_id ) wp_send_json_error( 'No patient.' );

        $db    = HearMed_DB::instance();
        $forms = $db->get_results(
            "SELECT pf.id, pf.form_title, pf.form_type, pf.is_valid,
                    pf.gdpr_consent, pf.treatment_consent, pf.created_at,
                    pf.signature_image_url,
                    CONCAT(s.first_name,' ',s.last_name) AS created_by_name
             FROM hearmed_core.patient_forms pf
             LEFT JOIN hearmed_reference.staff s ON s.id = pf.created_by
             WHERE pf.patient_id=$1 ORDER BY pf.created_at DESC",
            [ $patient_id ]
        );

        $out = [];
        foreach ( $forms as $f ) {
            $out[] = [
                'id'           => $f->id,
                'title'        => $f->form_title ?: $f->form_type,
                'type'         => $f->form_type,
                'is_valid'     => (bool) $f->is_valid,
                'gdpr'         => (bool) $f->gdpr_consent,
                'treatment'    => (bool) $f->treatment_consent,
                'signed'       => ! empty( $f->signature_image_url ),
                'date'         => date( 'd M Y', strtotime( $f->created_at ) ),
                'created_by'   => $f->created_by_name,
            ];
        }

        wp_send_json_success( $out );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX — Load template with placeholders resolved
    // ═══════════════════════════════════════════════════════════════════════

    public static function ajax_load_template() {
        check_ajax_referer( 'hearmed_nonce', 'nonce' );
        $template_id = intval( $_POST['template_id'] ?? 0 );
        $patient_id  = intval( $_POST['patient_id']  ?? 0 );
        if ( ! $template_id ) wp_send_json_error( 'No template.' );

        $db      = HearMed_DB::instance();
        $tpl     = $db->get_row(
            "SELECT * FROM hearmed_admin.form_templates WHERE id=$1 AND is_active=TRUE",
            [ $template_id ]
        );
        if ( ! $tpl ) wp_send_json_error( 'Template not found.' );

        $patient = $patient_id ? $db->get_row(
            "SELECT p.*, c.clinic_name, c.phone AS clinic_phone, c.email AS clinic_email,
                    c.address_line1 AS clinic_addr, c.vat_number AS clinic_vat
             FROM hearmed_core.patients p
             LEFT JOIN hearmed_reference.clinics c ON c.id = p.assigned_clinic_id
             WHERE p.id = $1",
            [ $patient_id ]
        ) : null;

        $clinic = $db->get_row(
            "SELECT * FROM hearmed_reference.clinics LIMIT 1"
        );

        $html          = self::resolve_placeholders( $tpl->body_html, $patient, $clinic );
        $fields_schema = $tpl->fields_schema ? json_decode( $tpl->fields_schema, true ) : [];

        wp_send_json_success( [
            'id'                => $tpl->id,
            'name'              => $tpl->name,
            'form_type'         => $tpl->form_type,
            'html'              => $html,
            'fields_schema'     => $fields_schema,
            'requires_signature'=> (bool) $tpl->requires_signature,
        ] );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX — Submit / save a completed form
    // ═══════════════════════════════════════════════════════════════════════

    public static function ajax_submit_form() {
        check_ajax_referer( 'hearmed_nonce', 'nonce' );
        if ( ! HearMed_Auth::can( 'edit_patients' ) ) wp_send_json_error( 'Access denied.' );

        $patient_id         = intval( $_POST['patient_id']           ?? 0 );
        $template_id        = intval( $_POST['template_id']          ?? 0 );
        $form_type          = sanitize_key( $_POST['form_type']      ?? 'consent' );
        $form_title         = sanitize_text_field( $_POST['form_title'] ?? '' );
        $form_data_raw      = sanitize_text_field( $_POST['form_data']  ?? '{}' );
        $signature_b64      = $_POST['signature_image'] ?? '';     // base64 PNG from canvas
        $signature_biometric= $_POST['signature_biometric'] ?? ''; // JSON from Wacom
        $gdpr_consent       = ! empty( $_POST['gdpr_consent'] );
        $treatment_consent  = ! empty( $_POST['treatment_consent'] );
        $marketing_email    = ! empty( $_POST['marketing_email'] );
        $marketing_phone    = ! empty( $_POST['marketing_phone'] );
        $marketing_sms      = ! empty( $_POST['marketing_sms'] );
        $rendered_html      = $_POST['rendered_html'] ?? '';

        if ( ! $patient_id ) wp_send_json_error( 'No patient.' );

        $db   = HearMed_DB::instance();
        $user = HearMed_Auth::current_user();

        // Save signature image if provided
        $sig_url = null;
        if ( $signature_b64 && str_starts_with( $signature_b64, 'data:image/png;base64,' ) ) {
            $sig_url = self::save_signature_image( $signature_b64, $patient_id );
        }

        // Supersede any previous valid forms of same type + title
        if ( $form_title ) {
            $prev = $db->get_results(
                "SELECT id FROM hearmed_core.patient_forms
                 WHERE patient_id=$1 AND form_title=$2 AND is_valid=TRUE",
                [ $patient_id, $form_title ]
            );
            foreach ( $prev as $p ) {
                $db->update( 'patient_forms', [
                    'is_valid'       => false,
                    'invalidated_at' => date( 'Y-m-d H:i:s' ),
                ], [ 'id' => $p->id ] );
            }
        }

        // Validate and decode form_data JSON
        $form_data_decoded = json_decode( $form_data_raw, true );
        $form_data_json    = json_encode( $form_data_decoded ?: [] );

        // Build signature biometric JSON
        $biometric_json = null;
        if ( $signature_biometric ) {
            $bio_decoded = json_decode( $signature_biometric, true );
            if ( $bio_decoded ) $biometric_json = json_encode( $bio_decoded );
        }

        // Insert the new form record
        $form_id = $db->insert( 'patient_forms', [
            'patient_id'           => $patient_id,
            'template_id'          => $template_id ?: null,
            'form_type'            => $form_type,
            'form_title'           => $form_title,
            'form_data'            => $form_data_json,
            'signature_image_url'  => $sig_url,
            'signature_biometric'  => $biometric_json,
            'gdpr_consent'         => $gdpr_consent,
            'treatment_consent'    => $treatment_consent,
            'data_processing_consent' => $gdpr_consent, // GDPR consent implies data processing consent
            'marketing_email'      => $marketing_email,
            'marketing_phone'      => $marketing_phone,
            'marketing_sms'        => $marketing_sms,
            'is_valid'             => true,
            'rendered_html'        => $rendered_html ?: null,
            'created_by'           => $user->ID ?? null,
        ] );

        if ( ! $form_id ) {
            wp_send_json_error( 'Failed to save form. Please try again.' );
        }

        // Sync consent flags back to patient record
        $patient_updates = [];
        if ( $gdpr_consent   ) $patient_updates['gdpr_consent']    = true;
        if ( $marketing_email ) $patient_updates['marketing_email'] = true;
        if ( $marketing_phone ) $patient_updates['marketing_phone'] = true;
        if ( $marketing_sms  ) $patient_updates['marketing_sms']   = true;
        if ( ! empty( $patient_updates ) ) {
            $db->update( 'patients', $patient_updates, [ 'id' => $patient_id ] );
        }

        // Audit log
        if ( class_exists( 'HearMed_Logger' ) ) {
            HearMed_Logger::log( 'form_submitted', 'patient_forms', $form_id, [
                'form_title'    => $form_title,
                'gdpr_consent'  => $gdpr_consent,
                'signed'        => ! empty( $sig_url ),
            ] );
        }

        wp_send_json_success( [
            'message' => 'Form saved successfully.',
            'form_id' => $form_id,
        ] );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX — View a submitted form
    // ═══════════════════════════════════════════════════════════════════════

    public static function ajax_get_form_view() {
        check_ajax_referer( 'hearmed_nonce', 'nonce' );
        $form_id = intval( $_POST['form_id'] ?? 0 );
        if ( ! $form_id ) wp_send_json_error( 'No form specified.' );

        $db   = HearMed_DB::instance();
        $form = $db->get_row(
            "SELECT pf.*,
                    p.first_name, p.last_name, p.date_of_birth,
                    CONCAT(s.first_name,' ',s.last_name) AS signed_by_name
             FROM hearmed_core.patient_forms pf
             JOIN hearmed_core.patients p ON p.id = pf.patient_id
             LEFT JOIN hearmed_reference.staff s ON s.id = pf.created_by
             WHERE pf.id = $1",
            [ $form_id ]
        );
        if ( ! $form ) wp_send_json_error( 'Form not found.' );

        $form_data = $form->form_data ? json_decode( $form->form_data, true ) : [];

        wp_send_json_success( [
            'id'           => $form->id,
            'title'        => $form->form_title,
            'type'         => $form->form_type,
            'rendered_html'=> $form->rendered_html,
            'form_data'    => $form_data,
            'signature_url'=> $form->signature_image_url,
            'gdpr_consent' => (bool) $form->gdpr_consent,
            'treatment'    => (bool) $form->treatment_consent,
            'is_valid'     => (bool) $form->is_valid,
            'patient_name' => $form->first_name . ' ' . $form->last_name,
            'date'         => date( 'd M Y', strtotime( $form->created_at ) ),
            'signed_by'    => $form->signed_by_name,
        ] );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX — Invalidate a form (C-Level / Admin only)
    // ═══════════════════════════════════════════════════════════════════════

    public static function ajax_invalidate_form() {
        check_ajax_referer( 'hearmed_nonce', 'nonce' );
        $role = HearMed_Auth::current_role();
        if ( ! in_array( $role, [ 'c_level', 'admin' ] ) ) wp_send_json_error( 'Access denied.' );

        $form_id = intval( $_POST['form_id'] ?? 0 );
        $reason  = sanitize_textarea_field( $_POST['reason'] ?? '' );

        if ( ! $form_id ) wp_send_json_error( 'No form specified.' );

        HearMed_DB::instance()->update( 'patient_forms', [
            'is_valid'       => false,
            'invalidated_at' => date( 'Y-m-d H:i:s' ),
        ], [ 'id' => $form_id ] );

        wp_send_json_success( 'Form invalidated.' );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Resolve {{placeholder}} tags in a template body.
     */
    private static function resolve_placeholders( $html, $patient, $clinic ) {
        $today         = date( 'd M Y' );
        $patient_name  = $patient ? trim( $patient->first_name . ' ' . $patient->last_name ) : '';
        $patient_dob   = ( $patient && $patient->date_of_birth )
                          ? date( 'd/m/Y', strtotime( $patient->date_of_birth ) ) : '';
        $clinic_name   = $patient->clinic_name ?? $clinic->clinic_name ?? '';
        $clinic_email  = $patient->clinic_email ?? $clinic->email ?? '';
        $clinic_phone  = $patient->clinic_phone ?? $clinic->phone ?? '';
        $clinic_addr   = $patient->clinic_addr ?? $clinic->address_line1 ?? '';

        $replacements = [
            '{{today_date}}'       => esc_html( $today ),
            '{{patient_full_name}}'=> esc_html( $patient_name ),
            '{{patient_dob}}'      => esc_html( $patient_dob ),
            '{{patient_number}}'   => esc_html( $patient->patient_number ?? '' ),
            '{{clinic_name}}'      => esc_html( $clinic_name ),
            '{{clinic_email}}'     => esc_html( $clinic_email ),
            '{{clinic_phone}}'     => esc_html( $clinic_phone ),
            '{{clinic_address}}'   => esc_html( $clinic_addr ),
            // Signature placeholder becomes the canvas UI block
            '{{signature}}'        => '<div class="hm-signature-section" id="hm-sig-section">
                <div class="hm-sig-label">Patient Signature</div>
                <div class="hm-sig-device-status" id="hm-sig-device">
                    <span class="hm-sig-dot hm-sig-dot--checking"></span> Checking signature device…
                </div>
                <div class="hm-sig-canvas-wrap">
                    <canvas id="hm-sig-canvas" width="560" height="180"></canvas>
                    <div class="hm-sig-placeholder" id="hm-sig-placeholder">Sign here</div>
                </div>
                <div class="hm-sig-toolbar">
                    <button type="button" class="hm-btn hm-btn--sm hm-btn--ghost" onclick="hmForms.clearSignature()">Clear</button>
                </div>
            </div>',
        ];

        return str_replace( array_keys( $replacements ), array_values( $replacements ), $html );
    }

    /**
     * Save a base64 PNG signature to the WordPress uploads folder.
     * Returns the public URL, or null on failure.
     */
    private static function save_signature_image( $base64_data, $patient_id ) {
        $upload_dir = wp_upload_dir();
        $sig_dir    = $upload_dir['basedir'] . '/hearmed-signatures/';
        $sig_url    = $upload_dir['baseurl'] . '/hearmed-signatures/';

        if ( ! file_exists( $sig_dir ) ) {
            wp_mkdir_p( $sig_dir );
            // Prevent directory browsing
            file_put_contents( $sig_dir . 'index.php', '<?php // Silence.' );
        }

        $img_data = base64_decode( str_replace( 'data:image/png;base64,', '', $base64_data ) );
        if ( ! $img_data ) return null;

        $filename = 'sig-' . $patient_id . '-' . time() . '.png';
        if ( file_put_contents( $sig_dir . $filename, $img_data ) === false ) return null;

        return $sig_url . $filename;
    }
}