-- Migration: Service (Appointment Type) Detail — Outcome Templates extras + Assignable Staff
-- Run on Railway PostgreSQL
-- Safe to re-run (IF NOT EXISTS / ON CONFLICT)

-- 1. Ensure outcome_templates table exists (may already be present)
CREATE TABLE IF NOT EXISTS hearmed_core.outcome_templates (
    id                   bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    service_id           bigint REFERENCES hearmed_reference.services(id) ON DELETE CASCADE,
    outcome_name         character varying(100) NOT NULL,
    outcome_color        character varying(10) DEFAULT '#cccccc',
    is_invoiceable       boolean DEFAULT false,
    requires_note        boolean DEFAULT false,
    triggers_followup    boolean DEFAULT false,
    followup_service_ids jsonb,
    created_at           timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at           timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

-- 2. Add columns that the detail page needs (safe if already present)
ALTER TABLE hearmed_core.outcome_templates
    ADD COLUMN IF NOT EXISTS triggers_reminder boolean DEFAULT false;

ALTER TABLE hearmed_core.outcome_templates
    ADD COLUMN IF NOT EXISTS triggers_followup_call boolean DEFAULT false;

ALTER TABLE hearmed_core.outcome_templates
    ADD COLUMN IF NOT EXISTS followup_call_days integer DEFAULT 7;

-- 3. Add missing columns to services table for the detail page
ALTER TABLE hearmed_reference.services
    ADD COLUMN IF NOT EXISTS text_color character varying(10) DEFAULT '#FFFFFF',
    ADD COLUMN IF NOT EXISTS appointment_category character varying(50),
    ADD COLUMN IF NOT EXISTS sales_opportunity boolean DEFAULT false,
    ADD COLUMN IF NOT EXISTS income_bearing boolean DEFAULT true;

-- 4. Assignable staff per service (junction table)
CREATE TABLE IF NOT EXISTS hearmed_reference.service_assignable_staff (
    id         bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    service_id bigint NOT NULL REFERENCES hearmed_reference.services(id) ON DELETE CASCADE,
    staff_id   bigint NOT NULL REFERENCES hearmed_reference.staff(id) ON DELETE CASCADE,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(service_id, staff_id)
);

-- 5. Service reminder settings
ALTER TABLE hearmed_reference.services
    ADD COLUMN IF NOT EXISTS reminder_enabled boolean DEFAULT false,
    ADD COLUMN IF NOT EXISTS reminder_sms_template_id bigint;

-- Verify
SELECT 'outcome_templates' AS tbl, COUNT(*) FROM hearmed_core.outcome_templates
UNION ALL
SELECT 'service_assignable_staff', COUNT(*) FROM hearmed_reference.service_assignable_staff;
