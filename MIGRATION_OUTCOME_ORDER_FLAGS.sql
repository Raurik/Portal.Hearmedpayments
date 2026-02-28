-- ============================================================
-- MIGRATION: Outcome order/invoice flow flags
-- ============================================================
-- Replaces is_invoiceable with two separate flags:
--   triggers_order   → opens new order creation form
--   triggers_invoice → opens order picker (existing orders / payment)
-- ============================================================

-- 1. Add triggers_order column
ALTER TABLE hearmed_core.outcome_templates
  ADD COLUMN IF NOT EXISTS triggers_order BOOLEAN NOT NULL DEFAULT false;

-- 2. Rename is_invoiceable → triggers_invoice (keeps existing data)
DO $$
BEGIN
  IF EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = 'hearmed_core'
      AND table_name = 'outcome_templates'
      AND column_name = 'is_invoiceable'
  ) AND NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = 'hearmed_core'
      AND table_name = 'outcome_templates'
      AND column_name = 'triggers_invoice'
  ) THEN
    ALTER TABLE hearmed_core.outcome_templates
      RENAME COLUMN is_invoiceable TO triggers_invoice;
  END IF;
END $$;

-- If is_invoiceable was already gone but triggers_invoice doesn't exist yet
ALTER TABLE hearmed_core.outcome_templates
  ADD COLUMN IF NOT EXISTS triggers_invoice BOOLEAN NOT NULL DEFAULT false;

COMMENT ON COLUMN hearmed_core.outcome_templates.triggers_order   IS 'Opens new order creation form after outcome save';
COMMENT ON COLUMN hearmed_core.outcome_templates.triggers_invoice IS 'Opens order picker / payment flow after outcome save';
