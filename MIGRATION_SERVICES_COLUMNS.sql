-- ========================================================
-- HearMed Migration: Add missing columns to services table
-- ========================================================
-- Adds appointment_category, sales_opportunity, income_bearing
-- columns needed by the Appointment Types admin page.
-- ========================================================

ALTER TABLE hearmed_reference.services
    ADD COLUMN IF NOT EXISTS appointment_category VARCHAR(50),
    ADD COLUMN IF NOT EXISTS sales_opportunity    BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS income_bearing       BOOLEAN DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS text_color           VARCHAR(10) DEFAULT '#FFFFFF';

-- Verify
SELECT 'Services columns migration complete' AS status;
