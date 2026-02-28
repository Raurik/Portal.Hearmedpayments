<?php
/**
 * HearMed Module — Clinical PDF Generator
 *
 * Generates branded A4 PDF documents from reviewed clinical data.
 * Uses TCPDF if available, falls back to basic HTML→PDF via DomPDF,
 * or produces an HTML preview suitable for browser print.
 *
 * AJAX endpoints:
 *   hm_generate_clinical_pdf   – build & return PDF
 *   hm_preview_clinical_pdf    – return HTML preview
 *   hm_get_clinical_doc        – fetch extracted/reviewed JSON
 *   hm_save_clinical_doc       – save draft/reviewed edits
 *   hm_update_clinical_doc_status – status transitions
 *
 * @package HearMed_Portal
 * @since   5.4.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ── AJAX registrations ── */
add_action( 'wp_ajax_hm_generate_clinical_pdf',        'hm_ajax_generate_clinical_pdf' );
add_action( 'wp_ajax_hm_preview_clinical_pdf',         'hm_ajax_preview_clinical_pdf' );
add_action( 'wp_ajax_hm_get_clinical_doc',             'hm_ajax_get_clinical_doc' );
add_action( 'wp_ajax_hm_save_clinical_doc',            'hm_ajax_save_clinical_doc' );
add_action( 'wp_ajax_hm_update_clinical_doc_status',   'hm_ajax_update_clinical_doc_status' );

/* ════════════════════════════════════════════════════════════════════════
   Tables
   ════════════════════════════════════════════════════════════════════════ */
function hm_clinical_docs_ensure_table() {
    static $done = false;
    if ( $done ) return;
    $done = true;

    $exists = HearMed_DB::get_var( "SELECT to_regclass('hearmed_admin.appointment_clinical_docs')" );
    if ( $exists === null ) {
        HearMed_DB::query( "CREATE TABLE IF NOT EXISTS hearmed_admin.appointment_clinical_docs (
            id               SERIAL PRIMARY KEY,
            appointment_id   INTEGER,
            patient_id       INTEGER NOT NULL,
            template_id      INTEGER REFERENCES hearmed_admin.document_templates(id),
            template_version INTEGER DEFAULT 1,
            transcript_id    INTEGER,
            status           VARCHAR(20) DEFAULT 'draft',
            extracted_json   JSONB   DEFAULT '{}'::jsonb,
            reviewed_json    JSONB   DEFAULT '{}'::jsonb,
            schema_snapshot  JSONB   DEFAULT '[]'::jsonb,
            missing_fields   JSONB   DEFAULT '[]'::jsonb,
            anonymised_text  TEXT    DEFAULT '',
            ai_model         VARCHAR(100) DEFAULT 'mock',
            ai_tokens_used   INTEGER DEFAULT 0,
            pdf_path         VARCHAR(500),
            reviewed_by      INTEGER,
            reviewed_at      TIMESTAMP,
            created_by       INTEGER,
            created_at       TIMESTAMP DEFAULT NOW(),
            updated_at       TIMESTAMP DEFAULT NOW()
        )" );
    } else {
        /* Auto-add ALL columns that may be missing from older schema */
        HearMed_DB::query( "ALTER TABLE hearmed_admin.appointment_clinical_docs
            ADD COLUMN IF NOT EXISTS template_version INTEGER DEFAULT 1,
            ADD COLUMN IF NOT EXISTS transcript_id    INTEGER,
            ADD COLUMN IF NOT EXISTS extracted_json   JSONB   DEFAULT '{}'::jsonb,
            ADD COLUMN IF NOT EXISTS reviewed_json    JSONB   DEFAULT '{}'::jsonb,
            ADD COLUMN IF NOT EXISTS schema_snapshot  JSONB   DEFAULT '[]'::jsonb,
            ADD COLUMN IF NOT EXISTS missing_fields   JSONB   DEFAULT '[]'::jsonb,
            ADD COLUMN IF NOT EXISTS anonymised_text  TEXT    DEFAULT '',
            ADD COLUMN IF NOT EXISTS ai_model         VARCHAR(100) DEFAULT 'mock',
            ADD COLUMN IF NOT EXISTS ai_tokens_used   INTEGER DEFAULT 0,
            ADD COLUMN IF NOT EXISTS pdf_path         VARCHAR(500),
            ADD COLUMN IF NOT EXISTS reviewed_by      INTEGER,
            ADD COLUMN IF NOT EXISTS reviewed_at      TIMESTAMP,
            ADD COLUMN IF NOT EXISTS created_by       INTEGER" );
    }
}

/* ════════════════════════════════════════════════════════════════════════
   Get clinical doc
   ════════════════════════════════════════════════════════════════════════ */
function hm_ajax_get_clinical_doc() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    $id = intval( $_POST['doc_id'] ?? $_GET['doc_id'] ?? 0 );
    if ( ! $id ) wp_send_json_error( 'Missing doc_id' );

    hm_clinical_docs_ensure_table();

    $doc = HearMed_DB::get_row(
        "SELECT cd.*, dt.name as template_name, dt.sections_json, dt.ai_system_prompt
         FROM hearmed_admin.appointment_clinical_docs cd
         LEFT JOIN hearmed_admin.document_templates dt ON dt.id = cd.template_id
         WHERE cd.id = $1",
        [ $id ]
    );

    if ( ! $doc ) wp_send_json_error( 'Document not found' );

    // Get transcript if linked
    $transcript = null;
    if ( $doc->transcript_id ) {
        $transcript = HearMed_DB::get_row(
            "SELECT transcript_text, created_at, word_count FROM hearmed_admin.appointment_transcripts WHERE id = $1",
            [ $doc->transcript_id ]
        );
    }

    // Get patient info
    $patient = HearMed_DB::get_row(
        "SELECT first_name, last_name, dob, email, phone, address_line1, address_line2, city, postcode, h_number
         FROM hearmed_core.patients WHERE id = $1",
        [ $doc->patient_id ]
    );

    wp_send_json_success( [
        'doc'        => (array) $doc,
        'transcript' => $transcript ? (array) $transcript : null,
        'patient'    => $patient ? (array) $patient : null,
    ] );
}

/* ════════════════════════════════════════════════════════════════════════
   Save clinical doc (draft / reviewed edits)
   ════════════════════════════════════════════════════════════════════════ */
function hm_ajax_save_clinical_doc() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Permission denied' );

    $id   = intval( $_POST['doc_id'] ?? 0 );
    $json = stripslashes( $_POST['reviewed_json'] ?? '{}' );
    if ( ! $id ) wp_send_json_error( 'Missing doc_id' );

    hm_clinical_docs_ensure_table();

    $decoded = json_decode( $json, true );
    if ( ! is_array( $decoded ) ) wp_send_json_error( 'Invalid JSON' );

    $result = HearMed_DB::update( 'hearmed_admin.appointment_clinical_docs', [
        'reviewed_json' => wp_json_encode( $decoded ),
        'updated_at'    => current_time( 'mysql' ),
    ], [ 'id' => $id ] );

    if ( $result === false ) wp_send_json_error( HearMed_DB::last_error() ?: 'Database error' );

    wp_send_json_success( [ 'id' => $id ] );
}

/* ════════════════════════════════════════════════════════════════════════
   Update clinical doc status
   ════════════════════════════════════════════════════════════════════════ */
function hm_ajax_update_clinical_doc_status() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Permission denied' );

    $id     = intval( $_POST['doc_id'] ?? 0 );
    $status = sanitize_text_field( $_POST['status'] ?? '' );
    $valid  = [ 'draft', 'extracted', 'reviewed', 'approved', 'generated' ];
    if ( ! $id || ! in_array( $status, $valid ) ) wp_send_json_error( 'Invalid parameters' );

    hm_clinical_docs_ensure_table();

    $data = [
        'status'     => $status,
        'updated_at' => current_time( 'mysql' ),
    ];

    if ( $status === 'reviewed' || $status === 'approved' ) {
        $data['reviewed_by'] = get_current_user_id();
        $data['reviewed_at'] = current_time( 'mysql' );
    }

    $result = HearMed_DB::update( 'hearmed_admin.appointment_clinical_docs', $data, [ 'id' => $id ] );

    if ( $result === false ) wp_send_json_error( HearMed_DB::last_error() ?: 'Database error' );

    wp_send_json_success( [ 'status' => $status ] );
}

/* ════════════════════════════════════════════════════════════════════════
   Preview clinical PDF (HTML output for browser print)
   ════════════════════════════════════════════════════════════════════════ */
function hm_ajax_preview_clinical_pdf() {
    check_ajax_referer( 'hm_nonce', 'nonce' );

    $id = intval( $_POST['doc_id'] ?? $_GET['doc_id'] ?? 0 );
    if ( ! $id ) wp_send_json_error( 'Missing doc_id' );

    hm_clinical_docs_ensure_table();

    $doc = HearMed_DB::get_row(
        "SELECT cd.*, dt.name as template_name, dt.sections_json
         FROM hearmed_admin.appointment_clinical_docs cd
         LEFT JOIN hearmed_admin.document_templates dt ON dt.id = cd.template_id
         WHERE cd.id = $1",
        [ $id ]
    );
    if ( ! $doc ) wp_send_json_error( 'Document not found' );

    $patient = HearMed_DB::get_row(
        "SELECT * FROM hearmed_core.patients WHERE id = $1",
        [ $doc->patient_id ]
    );

    $html = hm_build_clinical_pdf_html( $doc, $patient );
    wp_send_json_success( [ 'html' => $html ] );
}

/* ════════════════════════════════════════════════════════════════════════
   Generate clinical PDF (file download or save)
   ════════════════════════════════════════════════════════════════════════ */
function hm_ajax_generate_clinical_pdf() {
    check_ajax_referer( 'hm_nonce', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Permission denied' );

    $id = intval( $_POST['doc_id'] ?? 0 );
    if ( ! $id ) wp_send_json_error( 'Missing doc_id' );

    hm_clinical_docs_ensure_table();

    $doc = HearMed_DB::get_row(
        "SELECT cd.*, dt.name as template_name, dt.sections_json, dt.password_protect
         FROM hearmed_admin.appointment_clinical_docs cd
         LEFT JOIN hearmed_admin.document_templates dt ON dt.id = cd.template_id
         WHERE cd.id = $1",
        [ $id ]
    );
    if ( ! $doc ) wp_send_json_error( 'Document not found' );

    $patient = HearMed_DB::get_row(
        "SELECT * FROM hearmed_core.patients WHERE id = $1",
        [ $doc->patient_id ]
    );

    $html = hm_build_clinical_pdf_html( $doc, $patient );

    // Attempt TCPDF → DomPDF → fallback HTML
    $pdf_path = null;
    $upload   = wp_upload_dir();
    $dir      = $upload['basedir'] . '/hearmed-clinical-docs/' . date( 'Y/m' );
    if ( ! file_exists( $dir ) ) wp_mkdir_p( $dir );

    $filename = sanitize_file_name(
        'clinical-' . $doc->patient_id . '-' . $id . '-' . date( 'Ymd-His' ) . '.pdf'
    );
    $filepath = $dir . '/' . $filename;

    $password = '';
    if ( $doc->password_protect && $patient ) {
        $dob = $patient->dob ?? '';
        $password = str_replace( '-', '', substr( $dob, 0, 10 ) ); // YYYYMMDD
    }

    $generated = false;

    /* ── Try TCPDF ── */
    if ( class_exists( 'TCPDF' ) ) {
        $pdf = new TCPDF( 'P', 'mm', 'A4', true, 'UTF-8', false );
        $pdf->SetCreator( 'HearMed Portal' );
        $pdf->SetTitle( $doc->template_name ?? 'Clinical Document' );
        $pdf->SetAutoPageBreak( true, 25 );
        $pdf->setPrintHeader( false );
        $pdf->setPrintFooter( false );
        $pdf->AddPage();
        $pdf->writeHTML( $html, true, false, true, false, '' );

        if ( $doc->status !== 'approved' && $doc->status !== 'generated' ) {
            // DRAFT watermark
            $pdf->SetAlpha( 0.1 );
            $pdf->SetFont( 'helvetica', 'B', 60 );
            $pdf->SetTextColor( 200, 200, 200 );
            $pdf->RotatedText( 35, 190, 'DRAFT', 45 );
            $pdf->SetAlpha( 1 );
        }

        if ( $password ) {
            $pdf->SetProtection( [ 'print', 'copy' ], $password, null, 0, null );
        }

        $pdf->Output( $filepath, 'F' );
        $generated = true;
    }

    /* ── Try DomPDF ── */
    if ( ! $generated && class_exists( '\\Dompdf\\Dompdf' ) ) {
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml( $html );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();
        file_put_contents( $filepath, $dompdf->output() );
        $generated = true;
    }

    /* ── Fallback: save HTML as file ── */
    if ( ! $generated ) {
        $filepath = str_replace( '.pdf', '.html', $filepath );
        $filename = str_replace( '.pdf', '.html', $filename );
        file_put_contents( $filepath, $html );
        $generated = true;
    }

    $rel_path = str_replace( $upload['basedir'], '', $filepath );

    // Update doc record
    HearMed_DB::update( 'hearmed_admin.appointment_clinical_docs', [
        'pdf_path'   => $rel_path,
        'status'     => 'generated',
        'updated_at' => current_time( 'mysql' ),
    ], [ 'id' => $id ] );

    wp_send_json_success( [
        'pdf_url'  => $upload['baseurl'] . $rel_path,
        'filename' => $filename,
        'password' => $password ? 'DOB (' . substr( $password, 6, 2 ) . '/' . substr( $password, 4, 2 ) . '/' . substr( $password, 0, 4 ) . ')' : '',
    ] );
}

/* ════════════════════════════════════════════════════════════════════════
   Build the clinical PDF HTML (shared between preview & generate)
   ════════════════════════════════════════════════════════════════════════ */
function hm_build_clinical_pdf_html( $doc, $patient ) {
    $sections       = json_decode( $doc->sections_json ?: '[]', true );
    $reviewed_data  = json_decode( $doc->reviewed_json ?: '{}', true );
    $extracted_data = json_decode( $doc->extracted_json ?: '{}', true );
    $data           = ! empty( $reviewed_data ) ? $reviewed_data : $extracted_data;
    $is_draft       = ! in_array( $doc->status, [ 'approved', 'generated' ] );

    // Patient info
    $p_name = $patient ? trim( ( $patient->first_name ?? '' ) . ' ' . ( $patient->last_name ?? '' ) ) : 'Unknown';
    $p_h    = $patient->h_number ?? '';
    $p_dob  = $patient->dob ?? '';
    $p_phone = $patient->phone ?? '';
    $p_email = $patient->email ?? '';
    $p_addr  = trim( implode( ', ', array_filter( [
        $patient->address_line1 ?? '',
        $patient->address_line2 ?? '',
        $patient->city ?? '',
        $patient->postcode ?? '',
    ] ) ) );

    $date = date( 'j F Y' );

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset="UTF-8">
    <style>
    @page { margin: 20mm 15mm 25mm 15mm; }
    body {
        font-family: 'Source Sans 3', 'Source Sans Pro', Helvetica, Arial, sans-serif;
        font-size: 11pt;
        color: #1e293b;
        line-height: 1.5;
        margin: 0;
        padding: 20px;
    }
    .pdf-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        border-bottom: 3px solid #0BB4C4;
        padding-bottom: 12px;
        margin-bottom: 20px;
    }
    .pdf-brand h1 {
        font-family: 'Cormorant Garamond', Georgia, serif;
        font-size: 22pt;
        font-weight: 700;
        color: #151B33;
        margin: 0 0 2px 0;
    }
    .pdf-brand p {
        font-size: 9pt;
        color: #64748b;
        margin: 0;
    }
    .pdf-doc-title {
        font-family: 'Bricolage Grotesque', 'Source Sans 3', sans-serif;
        font-size: 13pt;
        font-weight: 600;
        color: #151B33;
        text-align: right;
    }
    .pdf-doc-date {
        font-size: 9pt;
        color: #64748b;
        text-align: right;
    }
    .pdf-section {
        margin-bottom: 16px;
        page-break-inside: avoid;
    }
    .pdf-section-title {
        font-family: 'Bricolage Grotesque', 'Source Sans 3', sans-serif;
        font-size: 12pt;
        font-weight: 600;
        color: #151B33;
        border-bottom: 1px solid #e2e8f0;
        padding-bottom: 4px;
        margin-bottom: 8px;
    }
    .pdf-field {
        margin-bottom: 6px;
    }
    .pdf-field-label {
        font-weight: 600;
        font-size: 10pt;
        color: #0BB4C4;
        display: inline;
    }
    .pdf-field-value {
        font-size: 10.5pt;
        color: #1e293b;
        display: inline;
    }
    .pdf-patient-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 6px 20px;
        margin-bottom: 8px;
    }
    .pdf-footer {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 8px 15mm;
        font-size: 8pt;
        color: #94a3b8;
        border-top: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
    }
    .pdf-draft-watermark {
        position: fixed;
        top: 40%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-45deg);
        font-size: 80pt;
        font-weight: 700;
        color: rgba(200,200,200,0.15);
        pointer-events: none;
        z-index: 0;
    }
    .pdf-consent {
        margin-top: 30px;
        padding-top: 12px;
        border-top: 1px solid #e2e8f0;
    }
    .pdf-sig-line {
        border-bottom: 1px solid #1e293b;
        width: 250px;
        height: 40px;
        display: inline-block;
        margin-top: 12px;
    }
    </style>
    </head>
    <body>
    <?php if ( $is_draft ): ?>
    <div class="pdf-draft-watermark">DRAFT</div>
    <?php endif; ?>

    <!-- Header -->
    <div class="pdf-header">
        <div class="pdf-brand">
            <h1>HearMed</h1>
            <p>Clinical Audiology Services</p>
        </div>
        <div>
            <div class="pdf-doc-title"><?php echo esc_html( $doc->template_name ?? 'Clinical Document' ); ?></div>
            <div class="pdf-doc-date"><?php echo esc_html( $date ); ?></div>
        </div>
    </div>

    <?php
    /* ── Render sections ── */
    if ( is_array( $sections ) ) {
        foreach ( $sections as $sec ) {
            if ( empty( $sec['enabled'] ) ) continue;
            $sec_key = $sec['key'] ?? '';
            $sec_data = $data[ $sec_key ] ?? [];

            echo '<div class="pdf-section">';
            echo '<div class="pdf-section-title">' . esc_html( $sec['label'] ?? '' ) . '</div>';

            /* Patient details auto-fill */
            if ( ( $sec['type'] ?? '' ) === 'auto' && $sec_key === 'patient_details' ) {
                echo '<div class="pdf-patient-grid">';
                echo '<div class="pdf-field"><span class="pdf-field-label">Name: </span><span class="pdf-field-value">' . esc_html( $p_name ) . '</span></div>';
                echo '<div class="pdf-field"><span class="pdf-field-label">H-Number: </span><span class="pdf-field-value">' . esc_html( $p_h ) . '</span></div>';
                echo '<div class="pdf-field"><span class="pdf-field-label">Date of Birth: </span><span class="pdf-field-value">' . esc_html( $p_dob ) . '</span></div>';
                echo '<div class="pdf-field"><span class="pdf-field-label">Phone: </span><span class="pdf-field-value">' . esc_html( $p_phone ) . '</span></div>';
                echo '<div class="pdf-field"><span class="pdf-field-label">Email: </span><span class="pdf-field-value">' . esc_html( $p_email ) . '</span></div>';
                echo '<div class="pdf-field"><span class="pdf-field-label">Address: </span><span class="pdf-field-value">' . esc_html( $p_addr ) . '</span></div>';
                echo '</div>';
            }
            /* Consent / Signature */
            elseif ( ( $sec['type'] ?? '' ) === 'signature' ) {
                echo '<div class="pdf-consent">';
                echo '<p style="font-size:10pt;">I consent to the information contained in this document being used for the purposes of my audiological care.</p>';
                echo '<div style="display:flex;gap:40px;margin-top:16px;">';
                echo '<div><p style="font-size:9pt;color:#64748b;margin:0;">Patient Signature</p><div class="pdf-sig-line"></div></div>';
                echo '<div><p style="font-size:9pt;color:#64748b;margin:0;">Date</p><div class="pdf-sig-line" style="width:120px;"></div></div>';
                echo '</div>';
                echo '</div>';
            }
            /* Chart placeholder */
            elseif ( ( $sec['type'] ?? '' ) === 'chart' ) {
                echo '<div style="border:1px dashed #cbd5e1;border-radius:8px;padding:30px;text-align:center;color:#94a3b8;font-size:10pt;">';
                echo '[Audiogram chart will be inserted here]';
                echo '</div>';
            }
            /* Text / AI Extract with fields */
            else {
                $fields = $sec['fields'] ?? [];
                if ( ! empty( $fields ) && is_array( $fields ) ) {
                    foreach ( $fields as $f ) {
                        $fkey  = is_array( $f ) ? ( $f['key'] ?? '' ) : $f;
                        $flabel = is_array( $f ) ? ( $f['label'] ?? ucwords( str_replace( '_', ' ', $fkey ) ) ) : ucwords( str_replace( '_', ' ', $f ) );
                        $val   = '';
                        if ( is_array( $sec_data ) && isset( $sec_data[ $fkey ] ) ) {
                            $val = $sec_data[ $fkey ];
                        } elseif ( is_string( $sec_data ) ) {
                            $val = $sec_data;
                        }
                        echo '<div class="pdf-field">';
                        echo '<span class="pdf-field-label">' . esc_html( $flabel ) . ': </span>';
                        echo '<span class="pdf-field-value">' . esc_html( $val ?: '—' ) . '</span>';
                        echo '</div>';
                    }
                } elseif ( is_string( $sec_data ) && $sec_data ) {
                    echo '<p>' . nl2br( esc_html( $sec_data ) ) . '</p>';
                } elseif ( is_array( $sec_data ) ) {
                    foreach ( $sec_data as $k => $v ) {
                        if ( is_string( $v ) ) {
                            echo '<div class="pdf-field">';
                            echo '<span class="pdf-field-label">' . esc_html( ucwords( str_replace( '_', ' ', $k ) ) ) . ': </span>';
                            echo '<span class="pdf-field-value">' . esc_html( $v ) . '</span>';
                            echo '</div>';
                        }
                    }
                } else {
                    echo '<p style="color:#94a3b8;font-style:italic;">No data recorded.</p>';
                }
            }

            echo '</div>';
        }
    }
    ?>

    <!-- Footer -->
    <div class="pdf-footer">
        <span>HearMed Clinical Document — Confidential</span>
        <span>Generated <?php echo esc_html( date( 'Y-m-d H:i' ) ); ?></span>
    </div>

    </body>
    </html>
    <?php
    return ob_get_clean();
}
