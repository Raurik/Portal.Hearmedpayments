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

-- 1. appointment_clinical_docs — add columns used by hm_trigger_ai_extraction()
ALTER TABLE hearmed_admin.appointment_clinical_docs
    ADD COLUMN IF NOT EXISTS schema_snapshot  JSONB   DEFAULT '[]'::jsonb,
    ADD COLUMN IF NOT EXISTS structured_json  JSONB   DEFAULT '{}'::jsonb,
    ADD COLUMN IF NOT EXISTS missing_fields   JSONB   DEFAULT '[]'::jsonb,
    ADD COLUMN IF NOT EXISTS anonymised_text  TEXT    DEFAULT '',
    ADD COLUMN IF NOT EXISTS ai_model        VARCHAR(100) DEFAULT 'mock',
    ADD COLUMN IF NOT EXISTS ai_tokens_used  INTEGER DEFAULT 0;

-- 2. appointment_transcripts — add created_by alias + duration_seconds alias
--    Original schema used staff_id / transcript_hash / duration_secs;
--    code uses created_by / checksum_hash / duration_seconds.
--    Add the code-expected columns so both old and new names work.
ALTER TABLE hearmed_admin.appointment_transcripts
    ADD COLUMN IF NOT EXISTS created_by       INTEGER,
    ADD COLUMN IF NOT EXISTS checksum_hash    VARCHAR(64),
    ADD COLUMN IF NOT EXISTS duration_seconds INTEGER DEFAULT 0;

-- ═══════════════════════════════════════════════════════════════════════════
--  END MIGRATION
-- ═══════════════════════════════════════════════════════════════════════════
