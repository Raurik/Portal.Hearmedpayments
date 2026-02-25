-- ========================================================
-- HearMed Migration: Fix Repairs Table
-- ========================================================
-- The hearmed_core.repairs table is missing columns that
-- the application code depends on. This migration adds
-- them safely using IF NOT EXISTS.
--
-- Run on your Railway PostgreSQL database:
--   psql -U <user> -d <database> < MIGRATION_FIX_REPAIRS_TABLE.sql
-- ========================================================

-- Add repair_number column (e.g. HMREP-0001)
ALTER TABLE hearmed_core.repairs
ADD COLUMN IF NOT EXISTS repair_number character varying(30);

-- Add repair_reason column (e.g. "No sound", "Feedback / whistling")
ALTER TABLE hearmed_core.repairs
ADD COLUMN IF NOT EXISTS repair_reason text;

-- Add under_warranty boolean flag
ALTER TABLE hearmed_core.repairs
ADD COLUMN IF NOT EXISTS under_warranty boolean DEFAULT false;

-- Add sent_to column (manufacturer/lab name)
ALTER TABLE hearmed_core.repairs
ADD COLUMN IF NOT EXISTS sent_to character varying(200);

-- Add tracking_number column (courier tracking)
ALTER TABLE hearmed_core.repairs
ADD COLUMN IF NOT EXISTS tracking_number character varying(100);

-- Create index on repair_number for fast lookups
CREATE INDEX IF NOT EXISTS idx_repairs_repair_number
ON hearmed_core.repairs(repair_number);

-- Create index on repair_status for filtered queries
CREATE INDEX IF NOT EXISTS idx_repairs_repair_status
ON hearmed_core.repairs(repair_status);

-- Create the repair number sequence if it doesn't exist
-- (used by PHP to generate HMREP-XXXX numbers)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_sequences
        WHERE schemaname = 'hearmed_core'
          AND sequencename = 'repair_number_seq'
    ) THEN
        CREATE SEQUENCE hearmed_core.repair_number_seq START WITH 1;
    END IF;
END $$;

-- Verify
SELECT column_name, data_type, is_nullable, column_default
FROM information_schema.columns
WHERE table_schema = 'hearmed_core' AND table_name = 'repairs'
ORDER BY ordinal_position;
