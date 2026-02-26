-- ================================================================
-- Migration: Exclusion Enhancements for Calendar v5.2
-- Adds columns to staff_absences for exclusion type linking,
-- full-day flag, repeat days, and repeat end date.
-- Safe to run multiple times (IF NOT EXISTS / IF NOT ALREADY).
-- ================================================================

-- 1. Link to exclusion_types reference table
ALTER TABLE hearmed_core.staff_absences
    ADD COLUMN IF NOT EXISTS exclusion_type_id INTEGER;

-- 2. Full-day flag (if false, start_time/end_time define the window)
ALTER TABLE hearmed_core.staff_absences
    ADD COLUMN IF NOT EXISTS is_full_day BOOLEAN DEFAULT true;

-- 3. Custom repeat days (comma-separated: "1,3,5" = Mon/Wed/Fri)
ALTER TABLE hearmed_core.staff_absences
    ADD COLUMN IF NOT EXISTS repeat_days VARCHAR(20) DEFAULT '';

-- 4. Repeat end date (NULL = single occurrence, '2099-12-31' = indefinite)
ALTER TABLE hearmed_core.staff_absences
    ADD COLUMN IF NOT EXISTS repeat_end_date DATE;

-- Optional FK (only if exclusion_types table exists)
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_class WHERE relname = 'exclusion_types') THEN
        IF NOT EXISTS (
            SELECT 1 FROM pg_constraint WHERE conname = 'fk_staff_absences_exclusion_type'
        ) THEN
            ALTER TABLE hearmed_core.staff_absences
                ADD CONSTRAINT fk_staff_absences_exclusion_type
                FOREIGN KEY (exclusion_type_id)
                REFERENCES hearmed_reference.exclusion_types(id)
                ON DELETE SET NULL;
        END IF;
    END IF;
END $$;
