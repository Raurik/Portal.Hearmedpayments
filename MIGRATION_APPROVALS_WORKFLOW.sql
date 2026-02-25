-- ============================================================
-- MIGRATION: Approvals & Ordering Workflow
-- Adds missing columns for approval flow, order item details,
-- and manufacturer ordering contact information
-- ============================================================

-- ────────────────────────────────────────────────────────────
-- 1) Orders table: approval & cancellation columns
-- ────────────────────────────────────────────────────────────
ALTER TABLE hearmed_core.orders
    ADD COLUMN IF NOT EXISTS approved_by         bigint REFERENCES hearmed_reference.staff(id),
    ADD COLUMN IF NOT EXISTS approved_date       timestamp without time zone,
    ADD COLUMN IF NOT EXISTS cancellation_type   character varying(30),   -- 'denied','cancelled','duplicate'
    ADD COLUMN IF NOT EXISTS cancellation_reason text,
    ADD COLUMN IF NOT EXISTS cancellation_date   timestamp without time zone,
    ADD COLUMN IF NOT EXISTS cost_total           numeric(10,2) DEFAULT 0.00;

-- ────────────────────────────────────────────────────────────
-- 2) Order items: dome, speaker, charger, bundled items
-- ────────────────────────────────────────────────────────────
ALTER TABLE hearmed_core.order_items
    ADD COLUMN IF NOT EXISTS dome_type        character varying(50),
    ADD COLUMN IF NOT EXISTS dome_size        character varying(20),
    ADD COLUMN IF NOT EXISTS speaker_size     character varying(20),
    ADD COLUMN IF NOT EXISTS is_rechargeable  boolean DEFAULT false,
    ADD COLUMN IF NOT EXISTS needs_charger    boolean DEFAULT false,
    ADD COLUMN IF NOT EXISTS filter_type      character varying(50),
    ADD COLUMN IF NOT EXISTS bundled_items    jsonb;  -- [{type:'dome', size:'8mm', qty:2}, ...]

-- ────────────────────────────────────────────────────────────
-- 3) Manufacturers: ordering contact details
-- ────────────────────────────────────────────────────────────
ALTER TABLE hearmed_reference.manufacturers
    ADD COLUMN IF NOT EXISTS order_email       character varying(255),
    ADD COLUMN IF NOT EXISTS order_phone       character varying(50),
    ADD COLUMN IF NOT EXISTS order_contact_name character varying(100),
    ADD COLUMN IF NOT EXISTS account_number    character varying(50),
    ADD COLUMN IF NOT EXISTS address           text;

-- ────────────────────────────────────────────────────────────
-- 4) Index for fast approval queries
-- ────────────────────────────────────────────────────────────
CREATE INDEX IF NOT EXISTS idx_orders_status ON hearmed_core.orders(current_status);
CREATE INDEX IF NOT EXISTS idx_orders_approved_by ON hearmed_core.orders(approved_by);
