-- Migration: Add power_type column to products table
-- Run against Railway PostgreSQL
-- Date: 2025-01-26

ALTER TABLE hearmed_reference.products
ADD COLUMN IF NOT EXISTS power_type VARCHAR(30) DEFAULT NULL;

COMMENT ON COLUMN hearmed_reference.products.power_type IS 'Hearing aid power type: Rechargeable, 312 Battery, 13 Battery, 10 Battery, 675 Battery';
