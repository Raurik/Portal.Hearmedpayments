-- ========================================================
-- HearMed Migration: Products V2 — Bundled item columns
-- ========================================================
-- Adds bundled_category, speaker_length, speaker_power
-- columns used by the restructured Products page.
-- Also creates bundled_categories table for dynamic categories.
-- ========================================================

-- 1. Add bundled item columns to products table
ALTER TABLE hearmed_reference.products
    ADD COLUMN IF NOT EXISTS bundled_category VARCHAR(50),
    ADD COLUMN IF NOT EXISTS speaker_length   VARCHAR(10),
    ADD COLUMN IF NOT EXISTS speaker_power    VARCHAR(20);

-- 2. Make category column nullable (services/bundled don't need it)
ALTER TABLE hearmed_reference.products
    ALTER COLUMN category DROP NOT NULL;

-- 3. Create bundled categories table
CREATE TABLE IF NOT EXISTS hearmed_reference.bundled_categories (
    id            BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    is_active     BOOLEAN DEFAULT TRUE,
    sort_order    INTEGER DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed default bundled categories
INSERT INTO hearmed_reference.bundled_categories (category_name, sort_order)
VALUES
    ('Speaker', 1),
    ('Dome', 2),
    ('Other', 99)
ON CONFLICT (category_name) DO NOTHING;

-- Verify
SELECT 'Products V2 migration complete' AS status;
