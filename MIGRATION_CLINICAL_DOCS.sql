-- ═══════════════════════════════════════════════════════════════════════════
--  MIGRATION: Clinical Documents Engine
--  Run ONCE against the PostgreSQL database
--  Adds: document_template_versions, appointment_clinical_docs,
--         appointment_transcripts
--  Alters: document_templates (adds ai_system_prompt, current_version)
-- ═══════════════════════════════════════════════════════════════════════════

-- 1. Alter document_templates —————————————————————————————————————————————
ALTER TABLE hearmed_admin.document_templates
    ADD COLUMN IF NOT EXISTS ai_system_prompt TEXT DEFAULT '',
    ADD COLUMN IF NOT EXISTS current_version  INTEGER DEFAULT 1;

-- 2. Document template versions (snapshot history) ————————————————————————
CREATE TABLE IF NOT EXISTS hearmed_admin.document_template_versions (
    id              SERIAL PRIMARY KEY,
    template_id     INTEGER NOT NULL REFERENCES hearmed_admin.document_templates(id) ON DELETE CASCADE,
    version         INTEGER NOT NULL DEFAULT 1,
    sections_json   JSONB   NOT NULL DEFAULT '[]'::jsonb,
    ai_system_prompt TEXT   DEFAULT '',
    created_by      INTEGER,
    created_at      TIMESTAMP DEFAULT NOW(),
    UNIQUE (template_id, version)
);

CREATE INDEX IF NOT EXISTS idx_dtv_template ON hearmed_admin.document_template_versions(template_id);

-- 3. Appointment transcripts ——————————————————————————————————————————————
CREATE TABLE IF NOT EXISTS hearmed_admin.appointment_transcripts (
    id              SERIAL PRIMARY KEY,
    appointment_id  INTEGER,
    patient_id      INTEGER NOT NULL,
    staff_id        INTEGER,
    transcript_text TEXT    NOT NULL,
    transcript_hash VARCHAR(64),
    word_count      INTEGER DEFAULT 0,
    duration_secs   INTEGER DEFAULT 0,
    source          VARCHAR(30) DEFAULT 'whisper',
    created_at      TIMESTAMP DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_at_appointment ON hearmed_admin.appointment_transcripts(appointment_id);
CREATE INDEX IF NOT EXISTS idx_at_patient     ON hearmed_admin.appointment_transcripts(patient_id);

-- 4. Appointment clinical docs ————————————————————————————————————————————
CREATE TABLE IF NOT EXISTS hearmed_admin.appointment_clinical_docs (
    id              SERIAL PRIMARY KEY,
    appointment_id  INTEGER,
    patient_id      INTEGER NOT NULL,
    template_id     INTEGER REFERENCES hearmed_admin.document_templates(id),
    template_version INTEGER DEFAULT 1,
    transcript_id   INTEGER REFERENCES hearmed_admin.appointment_transcripts(id),
    status          VARCHAR(20) DEFAULT 'draft',
    extracted_json  JSONB   DEFAULT '{}'::jsonb,
    reviewed_json   JSONB   DEFAULT '{}'::jsonb,
    pdf_path        VARCHAR(500),
    reviewed_by     INTEGER,
    reviewed_at     TIMESTAMP,
    created_by      INTEGER,
    created_at      TIMESTAMP DEFAULT NOW(),
    updated_at      TIMESTAMP DEFAULT NOW()
);

-- status: draft | extracted | reviewed | approved | generated
CREATE INDEX IF NOT EXISTS idx_acd_appointment ON hearmed_admin.appointment_clinical_docs(appointment_id);
CREATE INDEX IF NOT EXISTS idx_acd_patient     ON hearmed_admin.appointment_clinical_docs(patient_id);
CREATE INDEX IF NOT EXISTS idx_acd_status      ON hearmed_admin.appointment_clinical_docs(status);

-- ═══════════════════════════════════════════════════════════════════════════
--  END MIGRATION
-- ═══════════════════════════════════════════════════════════════════════════
