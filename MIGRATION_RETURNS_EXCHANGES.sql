-- ═══════════════════════════════════════════════════════════════════════════
-- MIGRATION: Returns, Exchanges, Patient Credits & PRSI Tracking
-- Run against hearmed database. Safe to re-run (IF NOT EXISTS / IF guards).
-- ═══════════════════════════════════════════════════════════════════════════

-- ── 1. Add columns to credit_notes for PRSI tracking ────────────────────
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='credit_notes' AND column_name='prsi_amount') THEN
        ALTER TABLE hearmed_core.credit_notes ADD COLUMN prsi_amount DECIMAL(10,2) DEFAULT 0;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='credit_notes' AND column_name='patient_refund_amount') THEN
        ALTER TABLE hearmed_core.credit_notes ADD COLUMN patient_refund_amount DECIMAL(10,2) DEFAULT 0;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='credit_notes' AND column_name='prsi_notified') THEN
        ALTER TABLE hearmed_core.credit_notes ADD COLUMN prsi_notified BOOLEAN DEFAULT FALSE;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='credit_notes' AND column_name='prsi_notified_date') THEN
        ALTER TABLE hearmed_core.credit_notes ADD COLUMN prsi_notified_date DATE;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='credit_notes' AND column_name='prsi_notified_by') THEN
        ALTER TABLE hearmed_core.credit_notes ADD COLUMN prsi_notified_by BIGINT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='credit_notes' AND column_name='device_id') THEN
        ALTER TABLE hearmed_core.credit_notes ADD COLUMN device_id BIGINT;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='credit_notes' AND column_name='exchange_type') THEN
        ALTER TABLE hearmed_core.credit_notes ADD COLUMN exchange_type VARCHAR(20);
    END IF;
END $$;

-- ── 2. Patient Credits table ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS hearmed_core.patient_credits (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    patient_id      BIGINT NOT NULL,
    credit_note_id  BIGINT,
    original_invoice_id BIGINT,
    original_order_id   BIGINT,
    amount          DECIMAL(10,2) NOT NULL DEFAULT 0,
    used_amount     DECIMAL(10,2) NOT NULL DEFAULT 0,
    remaining_amount DECIMAL(10,2) GENERATED ALWAYS AS (amount - used_amount) STORED,
    status          VARCHAR(20) NOT NULL DEFAULT 'active',
    notes           TEXT,
    created_by      BIGINT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── 3. Credit Applications table (tracks credit applied to invoices) ────
CREATE TABLE IF NOT EXISTS hearmed_core.credit_applications (
    id                  BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    patient_credit_id   BIGINT NOT NULL,
    invoice_id          BIGINT NOT NULL,
    amount              DECIMAL(10,2) NOT NULL,
    applied_by          BIGINT,
    applied_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── 4. Exchanges table ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS hearmed_core.exchanges (
    id                  BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    patient_id          BIGINT NOT NULL,
    original_order_id   BIGINT,
    original_invoice_id BIGINT,
    new_order_id        BIGINT,
    new_invoice_id      BIGINT,
    credit_note_id      BIGINT,
    patient_credit_id   BIGINT,
    device_id           BIGINT,
    exchange_type       VARCHAR(20) NOT NULL DEFAULT 'same_value',
    original_amount     DECIMAL(10,2) DEFAULT 0,
    new_amount          DECIMAL(10,2) DEFAULT 0,
    credit_amount       DECIMAL(10,2) DEFAULT 0,
    balance_due         DECIMAL(10,2) DEFAULT 0,
    refund_amount       DECIMAL(10,2) DEFAULT 0,
    prsi_amount         DECIMAL(10,2) DEFAULT 0,
    reason              TEXT,
    status              VARCHAR(20) NOT NULL DEFAULT 'pending',
    completed_at        TIMESTAMP,
    completed_by        BIGINT,
    created_by          BIGINT,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── 5. Returns table ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS hearmed_core.returns (
    id                    BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    patient_id            BIGINT NOT NULL,
    order_id              BIGINT,
    invoice_id            BIGINT,
    credit_note_id        BIGINT,
    device_id             BIGINT,
    return_date           DATE NOT NULL DEFAULT CURRENT_DATE,
    reason                TEXT,
    side                  VARCHAR(10) DEFAULT 'both',
    total_refund_amount   DECIMAL(10,2) DEFAULT 0,
    patient_refund_amount DECIMAL(10,2) DEFAULT 0,
    prsi_refund_amount    DECIMAL(10,2) DEFAULT 0,
    refund_status         VARCHAR(20) NOT NULL DEFAULT 'pending',
    refund_sent_date      DATE,
    prsi_notified         BOOLEAN DEFAULT FALSE,
    prsi_notified_date    DATE,
    prsi_notified_by      BIGINT,
    notes                 TEXT,
    created_by            BIGINT,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── 6. Register tables in DB class mapping ──────────────────────────────
-- (handled via auto-migrate in PHP, no SQL needed)

-- ── 7. Add invoice columns for credit tracking ─────────────────────────
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='invoices' AND column_name='credit_applied') THEN
        ALTER TABLE hearmed_core.invoices ADD COLUMN credit_applied DECIMAL(10,2) DEFAULT 0;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='invoices' AND column_name='is_exchange') THEN
        ALTER TABLE hearmed_core.invoices ADD COLUMN is_exchange BOOLEAN DEFAULT FALSE;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='invoices' AND column_name='exchange_id') THEN
        ALTER TABLE hearmed_core.invoices ADD COLUMN exchange_id BIGINT;
    END IF;
END $$;
