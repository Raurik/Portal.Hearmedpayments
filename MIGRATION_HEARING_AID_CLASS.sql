-- ═══════════════════════════════════════════════════════════════
-- MIGRATION: Add hearing_aid_class column to products table
-- Run this on your PostgreSQL database (Railway)
-- ═══════════════════════════════════════════════════════════════

-- Add hearing_aid_class column (Custom / Ready-Fit)
ALTER TABLE hearmed_reference.products
    ADD COLUMN IF NOT EXISTS hearing_aid_class VARCHAR(20) DEFAULT '';

-- Add comment for documentation
COMMENT ON COLUMN hearmed_reference.products.hearing_aid_class IS 'Hearing aid class: Custom or Ready-Fit. Affects delivery aging thresholds.';

-- Create index for filtering
CREATE INDEX IF NOT EXISTS idx_products_hearing_aid_class
    ON hearmed_reference.products (hearing_aid_class)
    WHERE hearing_aid_class IS NOT NULL AND hearing_aid_class != '';
