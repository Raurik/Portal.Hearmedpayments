-- ============================================================
-- Migration: Add banner_style, banner_size, show_badges columns
-- to hearmed_core.calendar_settings
-- Run ONCE — safe to re-run (IF NOT EXISTS checks)
-- ============================================================

DO $$
BEGIN
    -- banner_style (solid banner, gradient, stripe, none)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'hearmed_core'
          AND table_name   = 'calendar_settings'
          AND column_name  = 'banner_style'
    ) THEN
        ALTER TABLE hearmed_core.calendar_settings
            ADD COLUMN banner_style VARCHAR(20) DEFAULT 'default';
    END IF;

    -- banner_size (small, default, large)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'hearmed_core'
          AND table_name   = 'calendar_settings'
          AND column_name  = 'banner_size'
    ) THEN
        ALTER TABLE hearmed_core.calendar_settings
            ADD COLUMN banner_size VARCHAR(20) DEFAULT 'default';
    END IF;

    -- show_badges (toggles badge row on appointment cards)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'hearmed_core'
          AND table_name   = 'calendar_settings'
          AND column_name  = 'show_badges'
    ) THEN
        ALTER TABLE hearmed_core.calendar_settings
            ADD COLUMN show_badges BOOLEAN DEFAULT true;
    END IF;
END
$$;
