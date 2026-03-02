-- ================================================================
-- MIGRATION: Add referral_source_id + sms_reminder_hours to appointments
-- Run once against your PostgreSQL database
-- ================================================================

-- Referral source (FK to hearmed_reference.referral_sources)
ALTER TABLE hearmed_core.appointments
ADD COLUMN IF NOT EXISTS referral_source_id INT;

-- SMS reminder lead-time in hours (NULL = no reminder, 24/48/72)
ALTER TABLE hearmed_core.appointments
ADD COLUMN IF NOT EXISTS sms_reminder_hours INT;

-- Track whether the SMS reminder has been sent
ALTER TABLE hearmed_core.appointments
ADD COLUMN IF NOT EXISTS sms_reminder_sent BOOLEAN DEFAULT FALSE;

-- Back-fill referral_source_id from the existing referring_source text column where possible
UPDATE hearmed_core.appointments a
SET referral_source_id = rs.id
FROM hearmed_reference.referral_sources rs
WHERE a.referral_source_id IS NULL
  AND a.referring_source IS NOT NULL
  AND a.referring_source <> ''
  AND LOWER(TRIM(a.referring_source)) = LOWER(TRIM(rs.source_name));
