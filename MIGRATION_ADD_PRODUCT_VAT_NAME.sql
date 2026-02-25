-- Migration: Add vat_category and display_name columns to products table
-- Run this against the Railway PostgreSQL database

ALTER TABLE hearmed_reference.products
ADD COLUMN IF NOT EXISTS vat_category VARCHAR(50) DEFAULT '';

ALTER TABLE hearmed_reference.products
ADD COLUMN IF NOT EXISTS display_name VARCHAR(255) DEFAULT '';

-- Backfill vat_category based on item_type for existing records
UPDATE hearmed_reference.products SET vat_category = 'Hearing Aids (0%)'   WHERE item_type = 'product'    AND (vat_category IS NULL OR vat_category = '');
UPDATE hearmed_reference.products SET vat_category = 'Services (13.5%)'    WHERE item_type = 'service'    AND (vat_category IS NULL OR vat_category = '');
UPDATE hearmed_reference.products SET vat_category = 'Bundled Items (0%)'  WHERE item_type = 'bundled'    AND (vat_category IS NULL OR vat_category = '');
UPDATE hearmed_reference.products SET vat_category = 'Accessories (0%)'    WHERE item_type = 'accessory'  AND (vat_category IS NULL OR vat_category = '');
UPDATE hearmed_reference.products SET vat_category = 'Consumables (23%)'   WHERE item_type = 'consumable' AND (vat_category IS NULL OR vat_category = '');

-- Backfill display_name for existing hearing aids
UPDATE hearmed_reference.products p
SET display_name = CONCAT_WS(' - ',
    (SELECT m.name FROM hearmed_reference.manufacturers m WHERE m.id = p.manufacturer_id),
    p.product_name,
    p.tech_level,
    p.category
) || CASE
    WHEN LOWER(p.power_type) LIKE '%rechargeable%' THEN ' (R)'
    WHEN LOWER(p.power_type) LIKE '%battery%' OR LOWER(p.power_type) LIKE '%312%' OR LOWER(p.power_type) LIKE '%13%' OR LOWER(p.power_type) LIKE '%10%' OR LOWER(p.power_type) LIKE '%675%' THEN ' (B)'
    ELSE ''
END
WHERE item_type = 'product' AND (display_name IS NULL OR display_name = '');
