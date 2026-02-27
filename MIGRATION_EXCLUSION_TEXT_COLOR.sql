-- ================================================================
-- MIGRATION: Add text_color column to exclusion_types
-- Run once in pgAdmin or psql against the HearMed database.
-- Safe to re-run (uses IF NOT EXISTS pattern).
-- ================================================================

ALTER TABLE hearmed_reference.exclusion_types
    ADD COLUMN IF NOT EXISTS text_color VARCHAR(7) DEFAULT NULL;

-- NULL = inherit from colour (existing behaviour)
-- When set, the calendar exclusion label uses this colour for text instead.
