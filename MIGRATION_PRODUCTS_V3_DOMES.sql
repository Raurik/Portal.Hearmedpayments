-- =====================================================================
-- HearMed Migration: Products V3 — Dome columns + Manufacturer category
-- =====================================================================
-- Adds dome_type, dome_size to products table (bundled items)
-- Adds manufacturer_category to manufacturers table
-- Run against Railway PostgreSQL if needed (also auto-applied by code)
-- =====================================================================

-- 1. Add dome columns to products table
ALTER TABLE hearmed_reference.products
    ADD COLUMN IF NOT EXISTS dome_type VARCHAR(50),
    ADD COLUMN IF NOT EXISTS dome_size VARCHAR(20);

-- 2. Add manufacturer_category to manufacturers table
ALTER TABLE hearmed_reference.manufacturers
    ADD COLUMN IF NOT EXISTS manufacturer_category VARCHAR(100) DEFAULT '';

-- 3. Ensure other commonly needed columns exist (idempotent)
ALTER TABLE hearmed_reference.products
    ADD COLUMN IF NOT EXISTS item_type VARCHAR(50) DEFAULT 'product',
    ADD COLUMN IF NOT EXISTS power_type VARCHAR(50),
    ADD COLUMN IF NOT EXISTS vat_category VARCHAR(50) DEFAULT '',
    ADD COLUMN IF NOT EXISTS display_name VARCHAR(255) DEFAULT '',
    ADD COLUMN IF NOT EXISTS bundled_category VARCHAR(50),
    ADD COLUMN IF NOT EXISTS speaker_length VARCHAR(10),
    ADD COLUMN IF NOT EXISTS speaker_power VARCHAR(20);
