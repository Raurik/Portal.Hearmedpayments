-- ═══════════════════════════════════════════════════════════════════════
--  MIGRATION: Notes Rebuild — Categories + Alert Notes
--  Adds note_category (general / appointment / clinical) and is_alert
-- ═══════════════════════════════════════════════════════════════════════

-- 1. Add new columns
ALTER TABLE hearmed_core.patient_notes
  ADD COLUMN IF NOT EXISTS note_category VARCHAR(20) NOT NULL DEFAULT 'general',
  ADD COLUMN IF NOT EXISTS is_alert BOOLEAN NOT NULL DEFAULT false;

-- 2. Back-fill existing Clinical notes into the "clinical" category
UPDATE hearmed_core.patient_notes
   SET note_category = 'clinical'
 WHERE LOWER(note_type) = 'clinical';

-- 3. Index for fast alert lookups (partial index — only alert rows)
CREATE INDEX IF NOT EXISTS idx_patient_notes_alerts
  ON hearmed_core.patient_notes (patient_id)
  WHERE is_alert = true;

-- 4. Index for category-based filtering
CREATE INDEX IF NOT EXISTS idx_patient_notes_category
  ON hearmed_core.patient_notes (patient_id, note_category);
