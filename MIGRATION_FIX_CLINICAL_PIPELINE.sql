-- ═══════════════════════════════════════════════════════════════════════════
--  MIGRATION: Fix Clinical Pipeline Column Mismatches
--  Run ONCE against the PostgreSQL database
--
--  1. Adds missing columns to appointment_clinical_docs
--     (schema_snapshot, structured_json, missing_fields, anonymised_text,
--      ai_model, ai_tokens_used)
--  2. Adds missing columns to appointment_transcripts
--     (created_by, word_count default fix, duration_seconds alias)
-- ═══════════════════════════════════════════════════════════════════════════

-- 1. appointment_clinical_docs — add ALL columns that may be missing
ALTER TABLE hearmed_admin.appointment_clinical_docs
    ADD COLUMN IF NOT EXISTS template_version INTEGER DEFAULT 1,
    ADD COLUMN IF NOT EXISTS transcript_id    INTEGER,
    ADD COLUMN IF NOT EXISTS extracted_json   JSONB   DEFAULT '{}'::jsonb,
    ADD COLUMN IF NOT EXISTS reviewed_json    JSONB   DEFAULT '{}'::jsonb,
    ADD COLUMN IF NOT EXISTS schema_snapshot  JSONB   DEFAULT '[]'::jsonb,
    ADD COLUMN IF NOT EXISTS structured_json  JSONB   DEFAULT '{}'::jsonb,
    ADD COLUMN IF NOT EXISTS missing_fields   JSONB   DEFAULT '[]'::jsonb,
    ADD COLUMN IF NOT EXISTS anonymised_text  TEXT    DEFAULT '',
    ADD COLUMN IF NOT EXISTS ai_model         VARCHAR(100) DEFAULT 'mock',
    ADD COLUMN IF NOT EXISTS ai_tokens_used   INTEGER DEFAULT 0,
    ADD COLUMN IF NOT EXISTS pdf_path         VARCHAR(500),
    ADD COLUMN IF NOT EXISTS reviewed_by      INTEGER,
    ADD COLUMN IF NOT EXISTS reviewed_at      TIMESTAMP,
    ADD COLUMN IF NOT EXISTS created_by       INTEGER;

-- 2. appointment_transcripts — add ALL columns (original + alias names)
--    Original: staff_id / transcript_hash / duration_secs / word_count
--    Alias:    created_by / checksum_hash / duration_seconds
ALTER TABLE hearmed_admin.appointment_transcripts
    ADD COLUMN IF NOT EXISTS staff_id         INTEGER,
    ADD COLUMN IF NOT EXISTS transcript_hash  VARCHAR(64),
    ADD COLUMN IF NOT EXISTS duration_secs    INTEGER DEFAULT 0,
    ADD COLUMN IF NOT EXISTS word_count       INTEGER DEFAULT 0,
    ADD COLUMN IF NOT EXISTS created_by       INTEGER,
    ADD COLUMN IF NOT EXISTS checksum_hash    VARCHAR(64),
    ADD COLUMN IF NOT EXISTS duration_seconds INTEGER DEFAULT 0;

-- ═══════════════════════════════════════════════════════════════════════════
--  END MIGRATION
-- ═══════════════════════════════════════════════════════════════════════════
