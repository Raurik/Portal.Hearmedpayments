-- ============================================================
-- FIX: Add missing columns to calendar_settings table
-- Run this in Railway PostgreSQL console
-- Date: 2026-02-25
-- ============================================================

ALTER TABLE hearmed_core.calendar_settings
    ADD COLUMN IF NOT EXISTS display_full_name BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS prevent_location_mismatch BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS enabled_days VARCHAR(50),
    ADD COLUMN IF NOT EXISTS calendar_order VARCHAR(200),
    ADD COLUMN IF NOT EXISTS appointment_statuses TEXT,
    ADD COLUMN IF NOT EXISTS double_booking_warning BOOLEAN DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS show_patient BOOLEAN DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS show_service BOOLEAN DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS show_initials BOOLEAN DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS show_status BOOLEAN DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS appt_bg_color VARCHAR(10) DEFAULT '#0BB4C4',
    ADD COLUMN IF NOT EXISTS appt_font_color VARCHAR(10) DEFAULT '#ffffff',
    ADD COLUMN IF NOT EXISTS appt_badge_color VARCHAR(10) DEFAULT '#3b82f6',
    ADD COLUMN IF NOT EXISTS appt_badge_font_color VARCHAR(10) DEFAULT '#ffffff',
    ADD COLUMN IF NOT EXISTS appt_meta_color VARCHAR(10) DEFAULT '#38bdf8';

-- Verify columns were added
SELECT column_name, data_type, column_default
FROM information_schema.columns
WHERE table_schema = 'hearmed_core'
  AND table_name = 'calendar_settings'
ORDER BY ordinal_position;
