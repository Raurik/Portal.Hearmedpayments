-- ============================================================
-- MIGRATION: Add effective_from / effective_to to dispenser_schedules
-- Enables date-effective scheduling & historical tracking
-- ============================================================

-- Add date columns (nullable — NULL effective_from = always active, NULL effective_to = ongoing)
ALTER TABLE hearmed_reference.dispenser_schedules
    ADD COLUMN IF NOT EXISTS effective_from DATE,
    ADD COLUMN IF NOT EXISTS effective_to   DATE;

-- Index for fast lookups by date range
CREATE INDEX IF NOT EXISTS idx_dispenser_schedules_dates
    ON hearmed_reference.dispenser_schedules (staff_id, clinic_id, effective_from, effective_to);

-- For existing rows, leave effective_from and effective_to NULL
-- This means they are treated as "always active" (legacy behaviour preserved)
