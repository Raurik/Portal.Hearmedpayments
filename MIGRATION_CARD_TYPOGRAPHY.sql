-- ============================================================
-- Migration: Add card typography settings columns
-- to hearmed_core.calendar_settings
-- Run ONCE — safe to re-run (IF NOT EXISTS checks)
-- ============================================================

DO $$
BEGIN
    -- card_font_family
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'hearmed_core'
          AND table_name   = 'calendar_settings'
          AND column_name  = 'card_font_family'
    ) THEN
        ALTER TABLE hearmed_core.calendar_settings
            ADD COLUMN card_font_family VARCHAR(120) DEFAULT 'Plus Jakarta Sans';
    END IF;

    -- card_font_size (px)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'hearmed_core'
          AND table_name   = 'calendar_settings'
          AND column_name  = 'card_font_size'
    ) THEN
        ALTER TABLE hearmed_core.calendar_settings
            ADD COLUMN card_font_size INTEGER DEFAULT 11;
    END IF;

    -- card_font_weight
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'hearmed_core'
          AND table_name   = 'calendar_settings'
          AND column_name  = 'card_font_weight'
    ) THEN
        ALTER TABLE hearmed_core.calendar_settings
            ADD COLUMN card_font_weight INTEGER DEFAULT 600;
    END IF;

    -- outcome_font_family
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'hearmed_core'
          AND table_name   = 'calendar_settings'
          AND column_name  = 'outcome_font_family'
    ) THEN
        ALTER TABLE hearmed_core.calendar_settings
            ADD COLUMN outcome_font_family VARCHAR(120) DEFAULT 'Plus Jakarta Sans';
    END IF;

    -- outcome_font_size (px)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'hearmed_core'
          AND table_name   = 'calendar_settings'
          AND column_name  = 'outcome_font_size'
    ) THEN
        ALTER TABLE hearmed_core.calendar_settings
            ADD COLUMN outcome_font_size INTEGER DEFAULT 9;
    END IF;

    -- outcome_font_weight
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'hearmed_core'
          AND table_name   = 'calendar_settings'
          AND column_name  = 'outcome_font_weight'
    ) THEN
        ALTER TABLE hearmed_core.calendar_settings
            ADD COLUMN outcome_font_weight INTEGER DEFAULT 600;
    END IF;
END
$$;
