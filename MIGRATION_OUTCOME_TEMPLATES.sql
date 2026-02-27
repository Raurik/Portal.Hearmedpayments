-- ================================================================
-- MIGRATION: Outcome Templates & Appointment Outcomes
-- ================================================================
-- This migration ensures the outcome_templates table has all required
-- columns for the calendar outcome flow (double-click → outcome modal),
-- and creates the appointment_outcomes table if it doesn't exist.
--
-- Safe to run multiple times (uses IF NOT EXISTS / ADD COLUMN IF NOT EXISTS).
-- ================================================================

BEGIN;

-- ────────────────────────────────────────────────────────
-- 1. Ensure outcome_templates table exists with full schema
-- ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS hearmed_core.outcome_templates (
    id                    SERIAL PRIMARY KEY,
    service_id            INTEGER NOT NULL,
    outcome_name          VARCHAR(255) NOT NULL,
    outcome_color         VARCHAR(20) DEFAULT '#6b7280',
    is_invoiceable        BOOLEAN DEFAULT FALSE,
    requires_note         BOOLEAN DEFAULT FALSE,
    triggers_followup     BOOLEAN DEFAULT FALSE,
    triggers_reminder     BOOLEAN DEFAULT FALSE,
    triggers_followup_call BOOLEAN DEFAULT FALSE,
    followup_call_days    INTEGER DEFAULT 7,
    followup_service_ids  JSONB DEFAULT '[]',
    is_active             BOOLEAN DEFAULT TRUE,
    clinic_id             INTEGER,
    created_at            TIMESTAMP DEFAULT NOW(),
    created_by            VARCHAR(100),
    updated_at            TIMESTAMP
);

-- Add any missing columns (safe for existing tables with old schema)
DO $$
BEGIN
    -- Core outcome columns
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='outcome_templates' AND column_name='service_id') THEN
        ALTER TABLE hearmed_core.outcome_templates ADD COLUMN service_id INTEGER NOT NULL DEFAULT 0;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='outcome_templates' AND column_name='outcome_name') THEN
        -- If table had template_name instead, rename it
        IF EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='outcome_templates' AND column_name='template_name') THEN
            ALTER TABLE hearmed_core.outcome_templates RENAME COLUMN template_name TO outcome_name;
        ELSE
            ALTER TABLE hearmed_core.outcome_templates ADD COLUMN outcome_name VARCHAR(255) NOT NULL DEFAULT '';
        END IF;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='outcome_templates' AND column_name='outcome_color') THEN
        ALTER TABLE hearmed_core.outcome_templates ADD COLUMN outcome_color VARCHAR(20) DEFAULT '#6b7280';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='outcome_templates' AND column_name='is_invoiceable') THEN
        ALTER TABLE hearmed_core.outcome_templates ADD COLUMN is_invoiceable BOOLEAN DEFAULT FALSE;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='outcome_templates' AND column_name='requires_note') THEN
        ALTER TABLE hearmed_core.outcome_templates ADD COLUMN requires_note BOOLEAN DEFAULT FALSE;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='outcome_templates' AND column_name='triggers_followup') THEN
        ALTER TABLE hearmed_core.outcome_templates ADD COLUMN triggers_followup BOOLEAN DEFAULT FALSE;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='outcome_templates' AND column_name='triggers_reminder') THEN
        ALTER TABLE hearmed_core.outcome_templates ADD COLUMN triggers_reminder BOOLEAN DEFAULT FALSE;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='outcome_templates' AND column_name='triggers_followup_call') THEN
        ALTER TABLE hearmed_core.outcome_templates ADD COLUMN triggers_followup_call BOOLEAN DEFAULT FALSE;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='outcome_templates' AND column_name='followup_call_days') THEN
        ALTER TABLE hearmed_core.outcome_templates ADD COLUMN followup_call_days INTEGER DEFAULT 7;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='outcome_templates' AND column_name='followup_service_ids') THEN
        ALTER TABLE hearmed_core.outcome_templates ADD COLUMN followup_service_ids JSONB DEFAULT '[]';
    END IF;
END $$;

-- ────────────────────────────────────────────────────────
-- 2. Ensure appointment_outcomes table exists
-- ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS hearmed_core.appointment_outcomes (
    id               SERIAL PRIMARY KEY,
    appointment_id   INTEGER NOT NULL,
    outcome_name     VARCHAR(255) NOT NULL,
    outcome_color    VARCHAR(20) DEFAULT '#6b7280',
    notes            TEXT DEFAULT '',
    created_at       TIMESTAMP DEFAULT NOW(),
    created_by       INTEGER
);

-- Index for fast lookup by appointment_id (used in LEFT JOIN LATERAL)
CREATE INDEX IF NOT EXISTS idx_appt_outcomes_appt_id
    ON hearmed_core.appointment_outcomes (appointment_id);

-- ────────────────────────────────────────────────────────
-- 3. Ensure appointments table has outcome column
-- ────────────────────────────────────────────────────────
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema='hearmed_core' AND table_name='appointments' AND column_name='outcome') THEN
        ALTER TABLE hearmed_core.appointments ADD COLUMN outcome VARCHAR(255) DEFAULT '';
    END IF;
END $$;

COMMIT;

-- ================================================================
-- VERIFICATION
-- ================================================================
-- SELECT column_name, data_type FROM information_schema.columns
-- WHERE table_schema='hearmed_core' AND table_name='outcome_templates'
-- ORDER BY ordinal_position;
--
-- SELECT column_name, data_type FROM information_schema.columns
-- WHERE table_schema='hearmed_core' AND table_name='appointment_outcomes'
-- ORDER BY ordinal_position;
