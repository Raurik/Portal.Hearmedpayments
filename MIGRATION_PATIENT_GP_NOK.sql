-- ============================================================
-- MIGRATION: Add GP and Next of Kin columns to patients table
-- Run ONCE on production database
-- ============================================================

ALTER TABLE hearmed_core.patients
    ADD COLUMN IF NOT EXISTS gp_name VARCHAR(200),
    ADD COLUMN IF NOT EXISTS gp_address TEXT,
    ADD COLUMN IF NOT EXISTS nok_name VARCHAR(200),
    ADD COLUMN IF NOT EXISTS nok_phone VARCHAR(30);
