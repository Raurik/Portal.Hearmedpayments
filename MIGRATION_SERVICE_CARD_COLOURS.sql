-- ============================================================
-- MIGRATION: Add granular card colour columns to services
-- ============================================================
-- Adds per-appointment-type colour controls for every section
-- of the calendar card: name, time, meta, badges, border.
-- ============================================================

ALTER TABLE hearmed_reference.services
    ADD COLUMN IF NOT EXISTS name_color       VARCHAR(20) DEFAULT '#FFFFFF',
    ADD COLUMN IF NOT EXISTS time_color       VARCHAR(20) DEFAULT '#38bdf8',
    ADD COLUMN IF NOT EXISTS meta_color       VARCHAR(20) DEFAULT '#38bdf8',
    ADD COLUMN IF NOT EXISTS badge_bg_color   VARCHAR(20) DEFAULT '#ffffff33',
    ADD COLUMN IF NOT EXISTS badge_text_color VARCHAR(20) DEFAULT '#FFFFFF',
    ADD COLUMN IF NOT EXISTS border_color     VARCHAR(20) DEFAULT '',
    ADD COLUMN IF NOT EXISTS tint_opacity     INTEGER     DEFAULT 12;

-- Set defaults for existing rows
UPDATE hearmed_reference.services SET name_color       = '#FFFFFF'   WHERE name_color       IS NULL OR name_color = '';
UPDATE hearmed_reference.services SET time_color       = '#38bdf8'   WHERE time_color       IS NULL OR time_color = '';
UPDATE hearmed_reference.services SET meta_color       = '#38bdf8'   WHERE meta_color       IS NULL OR meta_color = '';
UPDATE hearmed_reference.services SET badge_bg_color   = '#ffffff33' WHERE badge_bg_color   IS NULL OR badge_bg_color = '';
UPDATE hearmed_reference.services SET badge_text_color = '#FFFFFF'   WHERE badge_text_color IS NULL OR badge_text_color = '';
UPDATE hearmed_reference.services SET tint_opacity     = 12          WHERE tint_opacity IS NULL;
