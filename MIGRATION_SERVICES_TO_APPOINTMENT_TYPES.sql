-- =============================================================================
-- MIGRATION: Services → Appointment Types + New Product Tables
-- =============================================================================
-- This migration:
-- 1. Adds all services columns to appointment_types table
-- 2. Migrates data from services → appointment_types
-- 3. Moves FK references to point at appointment_types
-- 4. Repurposes services table for sellable line-item services
-- 5. Creates consumables, accessories, chargers tables
-- =============================================================================
-- RUN THIS IN A TRANSACTION. Back up first!
-- =============================================================================

BEGIN;

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 1: Add all missing columns to appointment_types
-- ─────────────────────────────────────────────────────────────────────────────
ALTER TABLE hearmed_reference.appointment_types
  ADD COLUMN IF NOT EXISTS service_name        VARCHAR(200),
  ADD COLUMN IF NOT EXISTS service_code        VARCHAR(50),
  ADD COLUMN IF NOT EXISTS duration_minutes    INTEGER DEFAULT 30,
  ADD COLUMN IF NOT EXISTS default_price       NUMERIC(10,2),
  ADD COLUMN IF NOT EXISTS is_invoiceable      BOOLEAN DEFAULT TRUE,
  ADD COLUMN IF NOT EXISTS requires_outcome    BOOLEAN DEFAULT TRUE,
  ADD COLUMN IF NOT EXISTS service_color       VARCHAR(10),
  ADD COLUMN IF NOT EXISTS appointment_category VARCHAR(50),
  ADD COLUMN IF NOT EXISTS sales_opportunity   BOOLEAN DEFAULT FALSE,
  ADD COLUMN IF NOT EXISTS income_bearing      BOOLEAN DEFAULT TRUE,
  ADD COLUMN IF NOT EXISTS text_color          VARCHAR(10) DEFAULT '#FFFFFF',
  ADD COLUMN IF NOT EXISTS reminder_sms_enabled BOOLEAN DEFAULT FALSE,
  ADD COLUMN IF NOT EXISTS reminder_enabled    BOOLEAN DEFAULT FALSE,
  ADD COLUMN IF NOT EXISTS reminder_sms_template_id BIGINT,
  ADD COLUMN IF NOT EXISTS colour              VARCHAR(20) DEFAULT '#3B82F6',
  ADD COLUMN IF NOT EXISTS duration            INTEGER DEFAULT 30,
  ADD COLUMN IF NOT EXISTS name_color          VARCHAR(20) DEFAULT '#FFFFFF',
  ADD COLUMN IF NOT EXISTS time_color          VARCHAR(20) DEFAULT '#38bdf8',
  ADD COLUMN IF NOT EXISTS meta_color          VARCHAR(20) DEFAULT '#38bdf8',
  ADD COLUMN IF NOT EXISTS badge_bg_color      VARCHAR(20) DEFAULT '#ffffff33',
  ADD COLUMN IF NOT EXISTS badge_text_color    VARCHAR(20) DEFAULT '#FFFFFF',
  ADD COLUMN IF NOT EXISTS border_color        VARCHAR(20) DEFAULT '',
  ADD COLUMN IF NOT EXISTS is_reportable       BOOLEAN DEFAULT FALSE,
  ADD COLUMN IF NOT EXISTS report_category     TEXT,
  ADD COLUMN IF NOT EXISTS tint_opacity        INTEGER DEFAULT 12;

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 2: Migrate all services data into appointment_types
-- We use ON CONFLICT to handle any ID clashes gracefully.
-- ─────────────────────────────────────────────────────────────────────────────
INSERT INTO hearmed_reference.appointment_types (
    id, type_name, service_name, service_code, default_duration, duration_minutes,
    default_price, is_invoiceable, requires_outcome, service_color,
    is_active, description, created_at, updated_at,
    appointment_category, sales_opportunity, income_bearing, text_color,
    reminder_sms_enabled, reminder_enabled, reminder_sms_template_id,
    colour, duration, name_color, time_color, meta_color,
    badge_bg_color, badge_text_color, border_color,
    is_reportable, report_category
)
SELECT
    s.id,
    s.service_name,        -- type_name = service_name
    s.service_name,        -- keep service_name for backward compat
    s.service_code,
    COALESCE(s.duration_minutes, 30),
    COALESCE(s.duration_minutes, 30),
    s.default_price,
    COALESCE(s.is_invoiceable, TRUE),
    COALESCE(s.requires_outcome, TRUE),
    s.service_color,
    COALESCE(s.is_active, TRUE),
    s.description,
    s.created_at,
    s.updated_at,
    s.appointment_category,
    COALESCE(s.sales_opportunity, FALSE),
    COALESCE(s.income_bearing, TRUE),
    COALESCE(s.text_color, '#FFFFFF'),
    COALESCE(s.reminder_sms_enabled, FALSE),
    COALESCE(s.reminder_enabled, FALSE),
    s.reminder_sms_template_id,
    COALESCE(s.colour, '#3B82F6'),
    COALESCE(s.duration, 30),
    COALESCE(s.name_color, '#FFFFFF'),
    COALESCE(s.time_color, '#38bdf8'),
    COALESCE(s.meta_color, '#38bdf8'),
    COALESCE(s.badge_bg_color, '#ffffff33'),
    COALESCE(s.badge_text_color, '#FFFFFF'),
    COALESCE(s.border_color, ''),
    COALESCE(s.is_reportable, FALSE),
    s.report_category
FROM hearmed_reference.services s
ON CONFLICT (id) DO UPDATE SET
    type_name          = EXCLUDED.type_name,
    service_name       = EXCLUDED.service_name,
    service_code       = EXCLUDED.service_code,
    default_duration   = EXCLUDED.default_duration,
    duration_minutes   = EXCLUDED.duration_minutes,
    default_price      = EXCLUDED.default_price,
    is_invoiceable     = EXCLUDED.is_invoiceable,
    requires_outcome   = EXCLUDED.requires_outcome,
    service_color      = EXCLUDED.service_color,
    is_active          = EXCLUDED.is_active,
    description        = EXCLUDED.description,
    appointment_category = EXCLUDED.appointment_category,
    sales_opportunity  = EXCLUDED.sales_opportunity,
    income_bearing     = EXCLUDED.income_bearing,
    text_color         = EXCLUDED.text_color,
    reminder_sms_enabled = EXCLUDED.reminder_sms_enabled,
    reminder_enabled   = EXCLUDED.reminder_enabled,
    reminder_sms_template_id = EXCLUDED.reminder_sms_template_id,
    colour             = EXCLUDED.colour,
    duration           = EXCLUDED.duration,
    name_color         = EXCLUDED.name_color,
    time_color         = EXCLUDED.time_color,
    meta_color         = EXCLUDED.meta_color,
    badge_bg_color     = EXCLUDED.badge_bg_color,
    badge_text_color   = EXCLUDED.badge_text_color,
    border_color       = EXCLUDED.border_color,
    is_reportable      = EXCLUDED.is_reportable,
    report_category    = EXCLUDED.report_category,
    updated_at         = NOW();

-- Reset the sequence so new inserts don't clash
SELECT setval(
    'hearmed_reference.appointment_types_id_seq',
    GREATEST(
        (SELECT COALESCE(MAX(id), 0) FROM hearmed_reference.appointment_types),
        (SELECT COALESCE(MAX(id), 0) FROM hearmed_reference.services)
    ) + 1,
    false
);

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 3: Update FK references — point service_id columns at appointment_types
-- ─────────────────────────────────────────────────────────────────────────────

-- 3a. appointments.service_id → keep column name but repoint FK
ALTER TABLE hearmed_core.appointments
  DROP CONSTRAINT IF EXISTS appointments_service_id_fkey;
ALTER TABLE hearmed_core.appointments
  ADD CONSTRAINT appointments_service_id_fkey
  FOREIGN KEY (service_id) REFERENCES hearmed_reference.appointment_types(id);

-- 3b. calendar_blockouts.service_id
ALTER TABLE hearmed_core.calendar_blockouts
  DROP CONSTRAINT IF EXISTS calendar_blockouts_service_id_fkey;
ALTER TABLE hearmed_core.calendar_blockouts
  ADD CONSTRAINT calendar_blockouts_service_id_fkey
  FOREIGN KEY (service_id) REFERENCES hearmed_reference.appointment_types(id);

-- 3c. outcome_templates.service_id
ALTER TABLE hearmed_core.outcome_templates
  DROP CONSTRAINT IF EXISTS outcome_templates_service_id_fkey;
ALTER TABLE hearmed_core.outcome_templates
  ADD CONSTRAINT outcome_templates_service_id_fkey
  FOREIGN KEY (service_id) REFERENCES hearmed_reference.appointment_types(id);

-- 3d. service_assignable_staff.service_id
ALTER TABLE hearmed_reference.service_assignable_staff
  DROP CONSTRAINT IF EXISTS service_assignable_staff_service_id_fkey;
ALTER TABLE hearmed_reference.service_assignable_staff
  ADD CONSTRAINT service_assignable_staff_service_id_fkey
  FOREIGN KEY (service_id) REFERENCES hearmed_reference.appointment_types(id) ON DELETE CASCADE;

-- 3e. service_prerequisites.service_id
ALTER TABLE hearmed_reference.service_prerequisites
  DROP CONSTRAINT IF EXISTS service_prerequisites_service_id_fkey;
ALTER TABLE hearmed_reference.service_prerequisites
  ADD CONSTRAINT service_prerequisites_service_id_fkey
  FOREIGN KEY (service_id) REFERENCES hearmed_reference.appointment_types(id) ON DELETE CASCADE;

-- 3f. service_staff.service_id
ALTER TABLE hearmed_reference.service_staff
  DROP CONSTRAINT IF EXISTS service_staff_service_id_fkey;
ALTER TABLE hearmed_reference.service_staff
  ADD CONSTRAINT service_staff_service_id_fkey
  FOREIGN KEY (service_id) REFERENCES hearmed_reference.appointment_types(id) ON DELETE CASCADE;

-- 3g. appointment_types.default_service_id (self-ref no longer needed, drop FK)
ALTER TABLE hearmed_reference.appointment_types
  DROP CONSTRAINT IF EXISTS appointment_types_default_service_id_fkey;

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 4: Repurpose services table for sellable services (Products & Services)
-- ─────────────────────────────────────────────────────────────────────────────
-- Truncate old appointment-type data — everything is now in appointment_types
TRUNCATE hearmed_reference.services RESTART IDENTITY CASCADE;

-- Drop columns that are appointment-type specific and not needed for sellable services
ALTER TABLE hearmed_reference.services
  DROP COLUMN IF EXISTS requires_outcome,
  DROP COLUMN IF EXISTS appointment_category,
  DROP COLUMN IF EXISTS sales_opportunity,
  DROP COLUMN IF EXISTS income_bearing,
  DROP COLUMN IF EXISTS reminder_sms_enabled,
  DROP COLUMN IF EXISTS reminder_enabled,
  DROP COLUMN IF EXISTS reminder_sms_template_id,
  DROP COLUMN IF EXISTS colour,
  DROP COLUMN IF EXISTS name_color,
  DROP COLUMN IF EXISTS time_color,
  DROP COLUMN IF EXISTS meta_color,
  DROP COLUMN IF EXISTS badge_bg_color,
  DROP COLUMN IF EXISTS badge_text_color,
  DROP COLUMN IF EXISTS border_color,
  DROP COLUMN IF EXISTS is_reportable,
  DROP COLUMN IF EXISTS report_category,
  DROP COLUMN IF EXISTS tint_opacity,
  DROP COLUMN IF EXISTS text_color,
  DROP COLUMN IF EXISTS service_color;

-- Add columns appropriate for sellable services
ALTER TABLE hearmed_reference.services
  ADD COLUMN IF NOT EXISTS cost_price       NUMERIC(10,2) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS retail_price     NUMERIC(10,2) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS vat_rate         NUMERIC(5,2) DEFAULT 13.5,
  ADD COLUMN IF NOT EXISTS category         VARCHAR(50) DEFAULT 'General';

-- Rename default_price to retail_price if it exists (keep retail_price)
-- default_price stays as a legacy alias — or we drop it
ALTER TABLE hearmed_reference.services
  DROP COLUMN IF EXISTS default_price;

-- Final services table columns should be:
-- id, service_name, service_code, duration_minutes, cost_price, retail_price,
-- vat_rate, category, is_invoiceable, is_active, description,
-- created_at, updated_at, duration

-- ─────────────────────────────────────────────────────────────────────────────
-- STEP 5: Create new tables — consumables, accessories, chargers
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS hearmed_reference.consumables (
    id             BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    name           VARCHAR(200) NOT NULL,
    description    TEXT,
    cost_price     NUMERIC(10,2) DEFAULT 0,
    retail_price   NUMERIC(10,2) DEFAULT 0,
    vat_rate       NUMERIC(5,2) DEFAULT 23,
    is_active      BOOLEAN DEFAULT TRUE,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS hearmed_reference.chargers (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    manufacturer_id BIGINT REFERENCES hearmed_reference.manufacturers(id),
    manufacturer_name VARCHAR(200),
    cost_price      NUMERIC(10,2) DEFAULT 0,
    retail_price    NUMERIC(10,2) DEFAULT 0,
    vat_rate        NUMERIC(5,2) DEFAULT 23,
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS hearmed_reference.accessories (
    id              BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    manufacturer_id BIGINT REFERENCES hearmed_reference.manufacturers(id),
    manufacturer_name VARCHAR(200),
    product_type    VARCHAR(100),
    name            VARCHAR(200),
    description     TEXT,
    cost_price      NUMERIC(10,2) DEFAULT 0,
    retail_price    NUMERIC(10,2) DEFAULT 0,
    vat_rate        NUMERIC(5,2) DEFAULT 23,
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

COMMIT;
