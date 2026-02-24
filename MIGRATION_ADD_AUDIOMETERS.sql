-- ============================================================
-- Migration: Add Audiometers Table to PostgreSQL
-- Run this on Railway if hearmed_reference.audiometers doesn't exist
-- ============================================================

CREATE TABLE IF NOT EXISTS hearmed_reference.audiometers (
    id                bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    audiometer_name   character varying(200) NOT NULL,
    audiometer_make   character varying(100),
    audiometer_model  character varying(100),
    serial_number     character varying(100),
    calibration_date  date,
    clinic_id         bigint REFERENCES hearmed_reference.clinics(id) ON DELETE SET NULL,
    is_active         boolean DEFAULT true NOT NULL,
    notes             text,
    created_at        timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at        timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

-- Add index on clinic_id for faster lookups
CREATE INDEX IF NOT EXISTS idx_audiometers_clinic_id ON hearmed_reference.audiometers(clinic_id);

-- Add index on is_active for filtered queries
CREATE INDEX IF NOT EXISTS idx_audiometers_is_active ON hearmed_reference.audiometers(is_active);

-- Add update trigger for updated_at
DROP TRIGGER IF EXISTS set_updated_at_audiometers ON hearmed_reference.audiometers;
CREATE TRIGGER set_updated_at_audiometers
    BEFORE UPDATE ON hearmed_reference.audiometers
    FOR EACH ROW
    EXECUTE FUNCTION public.hearmed_set_updated_at();

-- ============================================================
-- Verify hearmed_range table exists with correct structure
-- ============================================================

-- Note: hearmed_range already exists but verify it has these columns:
-- id, range_name, price_total, price_ex_prsi, is_active, created_at, updated_at

-- If hearmed_range needs is_active and timestamps, run:
-- ALTER TABLE hearmed_reference.hearmed_range ADD COLUMN IF NOT EXISTS is_active boolean DEFAULT true NOT NULL;
-- ALTER TABLE hearmed_reference.hearmed_range ADD COLUMN IF NOT EXISTS created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP;
-- ALTER TABLE hearmed_reference.hearmed_range ADD COLUMN IF NOT EXISTS updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP;
