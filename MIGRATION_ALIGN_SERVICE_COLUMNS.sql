-- ============================================================
-- MIGRATION: Align hearmed_reference.services column names
-- ============================================================
-- The admin pages write service_color / duration_minutes / text_color.
-- The calendar module historically read colour / duration.
-- This migration ensures BOTH column names exist and are in sync,
-- so all code paths work regardless of which column was populated.
-- ============================================================

-- 1. Add new canonical columns if they don't exist
ALTER TABLE hearmed_reference.services
    ADD COLUMN IF NOT EXISTS service_color   VARCHAR(20) DEFAULT '#3B82F6',
    ADD COLUMN IF NOT EXISTS text_color       VARCHAR(20) DEFAULT '#FFFFFF',
    ADD COLUMN IF NOT EXISTS duration_minutes INTEGER     DEFAULT 30;

-- 2. Add legacy columns if they don't exist (for backward compat)
ALTER TABLE hearmed_reference.services
    ADD COLUMN IF NOT EXISTS colour   VARCHAR(20) DEFAULT '#3B82F6',
    ADD COLUMN IF NOT EXISTS duration  INTEGER     DEFAULT 30;

-- 3. Sync data: copy from old → new where new is empty
UPDATE hearmed_reference.services
SET service_color = colour
WHERE (service_color IS NULL OR service_color = '')
  AND colour IS NOT NULL AND colour <> '';

UPDATE hearmed_reference.services
SET duration_minutes = duration
WHERE (duration_minutes IS NULL OR duration_minutes = 0)
  AND duration IS NOT NULL AND duration > 0;

-- 4. Sync data: copy from new → old where old is empty
UPDATE hearmed_reference.services
SET colour = service_color
WHERE (colour IS NULL OR colour = '')
  AND service_color IS NOT NULL AND service_color <> '';

UPDATE hearmed_reference.services
SET duration = duration_minutes
WHERE (duration IS NULL OR duration = 0)
  AND duration_minutes IS NOT NULL AND duration_minutes > 0;

-- 5. Final fallback: set defaults where both are still null
UPDATE hearmed_reference.services
SET service_color = '#3B82F6', colour = '#3B82F6'
WHERE (service_color IS NULL OR service_color = '')
  AND (colour IS NULL OR colour = '');

UPDATE hearmed_reference.services
SET duration_minutes = 30, duration = 30
WHERE (duration_minutes IS NULL OR duration_minutes = 0)
  AND (duration IS NULL OR duration = 0);

UPDATE hearmed_reference.services
SET text_color = '#FFFFFF'
WHERE text_color IS NULL OR text_color = '';
