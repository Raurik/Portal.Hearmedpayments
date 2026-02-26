-- ═══════════════════════════════════════════════════════════════════════
-- HearMed Portal — Combined Migrations & Fixes
-- ═══════════════════════════════════════════════════════════════════════
-- All incremental migrations consolidated into a single idempotent file.
-- Safe to run multiple times (uses IF NOT EXISTS / IF EXISTS / ON CONFLICT).
--
-- Run AFTER DATABASE_SCHEMA.sql and (optionally) SEED_DATA.sql.
-- Usage:  psql -U <user> -d hearmed_prod < MIGRATIONS_ALL.sql
--
-- Consolidated: 2026-02-26
-- Source files (now deleted):
--   FIX_STAFF_TABLE_COLUMNS.sql
--   FIX_STAFF_AUTH_TABLE_COLUMNS.sql
--   FIX_CALENDAR_SETTINGS_COLUMNS.sql
--   MIGRATION_ADD_ROLES_TABLE.sql
--   MIGRATION_STAFF_AUTH_SCHEDULES.sql
--   MIGRATION_ADD_AUDIOMETERS.sql
--   MIGRATION_ADMIN_GROUPS_RESOURCES.sql
--   MIGRATION_ADMIN_PAGES_V51.sql
--   MIGRATION_ADD_MISSING_COLUMNS.sql
--   MIGRATION_PRODUCTS_POWER_TYPE.sql
--   MIGRATION_ADD_PRODUCT_VAT_NAME.sql
--   MIGRATION_PRODUCTS_V2.sql
--   MIGRATION_PRODUCTS_V3_DOMES.sql
--   MIGRATION_HEARING_AID_CLASS.sql
--   MIGRATION_SERVICES_COLUMNS.sql
--   MIGRATION_SERVICE_DETAIL.sql
--   MIGRATION_RESOURCES_V2.sql
--   MIGRATION_DOCUMENT_TEMPLATES.sql
--   MIGRATION_APPROVALS_WORKFLOW.sql
--   MIGRATION_FITTING_WORKFLOW.sql
--   MIGRATION_FIX_REPAIRS_TABLE.sql
--   MIGRATION_PATIENT_ENHANCEMENTS.sql
-- ═══════════════════════════════════════════════════════════════════════

BEGIN;


-- ════════════════════════════════════════════════════════════════════
-- SECTION 1: CORE TABLE CREATION (prerequisite tables first)
-- ════════════════════════════════════════════════════════════════════

-- ── 1a. Roles ─────────────────────────────────────────────────────
-- Source: MIGRATION_ADD_ROLES_TABLE.sql

CREATE TABLE IF NOT EXISTS hearmed_reference.roles (
    id              bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    role_name       character varying(100) NOT NULL UNIQUE,
    display_name    character varying(150) NOT NULL,
    description     text,
    permissions     jsonb DEFAULT '[]'::jsonb,
    is_active       boolean DEFAULT true,
    created_at      timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at      timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_roles_role_name ON hearmed_reference.roles(role_name);
CREATE INDEX IF NOT EXISTS idx_roles_is_active ON hearmed_reference.roles(is_active);

INSERT INTO hearmed_reference.roles
(role_name, display_name, description, permissions, is_active)
VALUES
('admin', 'Administrator', 'Full system access', '["view_all", "create_all", "edit_all", "delete_all", "manage_staff", "manage_roles"]'::jsonb, true),
('manager', 'Manager', 'Clinic management and staff oversight', '["view_own_clinic", "create_appointments", "edit_own_clinic", "manage_staff"]'::jsonb, true),
('dispenser', 'Dispenser', 'Dispense healthcare products and services', '["view_appointments", "dispense_products", "record_outcomes"]'::jsonb, true),
('audiologist', 'Audiologist', 'Perform audiological services and assessments', '["view_patients", "create_notes", "order_tests", "record_assessments"]'::jsonb, true),
('receptionist', 'Receptionist', 'Reception and appointment scheduling', '["view_patients", "create_appointments", "manage_calendar"]'::jsonb, true),
('finance', 'Finance Officer', 'Invoice and payment management', '["view_invoices", "edit_invoices", "record_payments", "generate_reports"]'::jsonb, true)
ON CONFLICT (role_name) DO NOTHING;


-- ── 1b. Staff Auth & Dispenser Schedules ──────────────────────────
-- Source: MIGRATION_STAFF_AUTH_SCHEDULES.sql

CREATE TABLE IF NOT EXISTS hearmed_reference.staff_auth (
    id                  bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    staff_id            bigint UNIQUE REFERENCES hearmed_reference.staff(id) ON DELETE CASCADE,
    username            character varying(150) NOT NULL UNIQUE,
    password_hash       text NOT NULL,
    temp_password       boolean DEFAULT true,
    two_factor_enabled  boolean DEFAULT false,
    totp_secret         character varying(64),
    last_login          timestamp without time zone,
    created_at          timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at          timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS hearmed_reference.dispenser_schedules (
    id             bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    staff_id       bigint REFERENCES hearmed_reference.staff(id) ON DELETE CASCADE,
    clinic_id      bigint REFERENCES hearmed_reference.clinics(id) ON DELETE CASCADE,
    day_of_week    smallint NOT NULL,
    rotation_weeks smallint DEFAULT 1 NOT NULL,
    week_number    smallint DEFAULT 1 NOT NULL,
    is_active      boolean DEFAULT true NOT NULL,
    created_at     timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at     timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (staff_id, clinic_id, day_of_week, rotation_weeks, week_number)
);


-- ── 1c. Audiometers ───────────────────────────────────────────────
-- Source: MIGRATION_ADD_AUDIOMETERS.sql

CREATE TABLE IF NOT EXISTS hearmed_reference.audiometers (
    id                bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    audiometer_name   character varying(200) NOT NULL,
    audiometer_make   character varying(100),
    audiometer_model  character varying(100),
    serial_number     character varying(100),
    calibration_date  date,
    clinic_id         bigint REFERENCES hearmed_reference.clinics(id) ON DELETE SET NULL,
    is_active         boolean DEFAULT true NOT NULL,
    notes             text,
    created_at        timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at        timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_audiometers_clinic_id ON hearmed_reference.audiometers(clinic_id);
CREATE INDEX IF NOT EXISTS idx_audiometers_is_active ON hearmed_reference.audiometers(is_active);

DROP TRIGGER IF EXISTS set_updated_at_audiometers ON hearmed_reference.audiometers;
CREATE TRIGGER set_updated_at_audiometers
    BEFORE UPDATE ON hearmed_reference.audiometers
    FOR EACH ROW
    EXECUTE FUNCTION public.hearmed_set_updated_at();


-- ── 1d. Groups, Resources, GDPR Settings ─────────────────────────
-- Source: MIGRATION_ADMIN_GROUPS_RESOURCES.sql

CREATE TABLE IF NOT EXISTS hearmed_reference.staff_groups (
    id          bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    group_name  character varying(150) NOT NULL,
    description text,
    is_active   boolean DEFAULT true NOT NULL,
    created_at  timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at  timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS hearmed_reference.staff_group_members (
    id         bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    group_id   bigint REFERENCES hearmed_reference.staff_groups(id) ON DELETE CASCADE,
    staff_id   bigint REFERENCES hearmed_reference.staff(id) ON DELETE CASCADE,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS hearmed_reference.resources (
    id          bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    title       character varying(200) NOT NULL,
    category    character varying(100),
    url         character varying(500),
    description text,
    sort_order  integer DEFAULT 0 NOT NULL,
    is_active   boolean DEFAULT true NOT NULL,
    created_at  timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at  timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS hearmed_admin.gdpr_settings (
    id                           bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    hm_privacy_policy_url        character varying(255),
    hm_retention_patient_years   integer,
    hm_retention_financial_years integer,
    hm_retention_sms_years       integer,
    hm_data_processors           text,
    created_at                   timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at                   timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE hearmed_reference.hearmed_range
    ADD COLUMN IF NOT EXISTS is_active boolean DEFAULT true NOT NULL;
ALTER TABLE hearmed_reference.hearmed_range
    ADD COLUMN IF NOT EXISTS created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE hearmed_reference.hearmed_range
    ADD COLUMN IF NOT EXISTS updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP;

CREATE INDEX IF NOT EXISTS idx_staff_groups_active ON hearmed_reference.staff_groups(is_active);
CREATE INDEX IF NOT EXISTS idx_resources_active ON hearmed_reference.resources(is_active);
CREATE INDEX IF NOT EXISTS idx_resources_category ON hearmed_reference.resources(category);


-- ── 1e. Exclusion Types, Appointment Types, Manufacturers, etc. ──
-- Source: MIGRATION_ADMIN_PAGES_V51.sql

CREATE TABLE IF NOT EXISTS hearmed_reference.exclusion_types (
    id          SERIAL PRIMARY KEY,
    type_name   VARCHAR(100) NOT NULL,
    color       VARCHAR(7) NOT NULL DEFAULT '#6b7280',
    description TEXT,
    sort_order  INTEGER DEFAULT 0,
    is_active   BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT NOW(),
    updated_at  TIMESTAMP DEFAULT NOW()
);

INSERT INTO hearmed_reference.exclusion_types (type_name, color, description, sort_order)
VALUES
    ('Lunch Break', '#f59e0b', 'Staff lunch break', 1),
    ('Holiday', '#ef4444', 'Staff holiday / annual leave', 2),
    ('Meeting', '#8b5cf6', 'Internal meeting', 3),
    ('Training', '#3b82f6', 'Training session', 4),
    ('Sick Leave', '#f97316', 'Sick day', 5),
    ('Other', '#6b7280', 'Other exclusion', 6)
ON CONFLICT DO NOTHING;

ALTER TABLE hearmed_reference.resources
    ADD COLUMN IF NOT EXISTS clinic_id INTEGER REFERENCES hearmed_reference.clinics(id);

ALTER TABLE hearmed_reference.staff_groups
    ADD COLUMN IF NOT EXISTS clinic_id INTEGER REFERENCES hearmed_reference.clinics(id),
    ADD COLUMN IF NOT EXISTS role_id INTEGER REFERENCES hearmed_reference.roles(id);

CREATE TABLE IF NOT EXISTS hearmed_reference.hearmed_range (
    id             BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    range_name     VARCHAR(150) NOT NULL,
    price_total    NUMERIC(10,2),
    price_ex_prsi  NUMERIC(10,2),
    is_active      BOOLEAN DEFAULT TRUE NOT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS hearmed_reference.appointment_types (
    id                 BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    type_name          VARCHAR(100) NOT NULL,
    default_service_id BIGINT,
    default_duration   INTEGER DEFAULT 30,
    requires_referral  BOOLEAN DEFAULT FALSE,
    is_active          BOOLEAN DEFAULT TRUE,
    description        TEXT,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS hearmed_admin.gdpr_documents (
    id           BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    doc_name     VARCHAR(200) NOT NULL,
    doc_type     VARCHAR(50) DEFAULT 'policy',
    file_url     TEXT,
    file_path    TEXT,
    uploaded_by  INTEGER,
    is_active    BOOLEAN DEFAULT TRUE,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS hearmed_reference.referral_sources (
    id           BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    source_name  VARCHAR(150) NOT NULL,
    parent_id    BIGINT,
    sort_order   INTEGER DEFAULT 0,
    is_active    BOOLEAN DEFAULT TRUE NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS hearmed_reference.manufacturers (
    id             BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    name           VARCHAR(200) NOT NULL,
    country        VARCHAR(100),
    website        VARCHAR(500),
    support_email  VARCHAR(200),
    support_phone  VARCHAR(50),
    is_active      BOOLEAN DEFAULT TRUE NOT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


-- ── 1f. Document Templates ────────────────────────────────────────
-- Source: MIGRATION_DOCUMENT_TEMPLATES.sql

CREATE TABLE IF NOT EXISTS hearmed_admin.document_templates (
    id              SERIAL PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    category        VARCHAR(50)  DEFAULT 'clinical',
    ai_enabled      BOOLEAN      DEFAULT false,
    password_protect BOOLEAN     DEFAULT true,
    sections_json   JSONB        DEFAULT '[]'::jsonb,
    is_active       BOOLEAN      DEFAULT true,
    sort_order      INTEGER      DEFAULT 0,
    created_at      TIMESTAMP    DEFAULT NOW(),
    updated_at      TIMESTAMP    DEFAULT NOW()
);


-- ── 1g. Bundled Categories ────────────────────────────────────────
-- Source: MIGRATION_PRODUCTS_V2.sql

CREATE TABLE IF NOT EXISTS hearmed_reference.bundled_categories (
    id            BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    is_active     BOOLEAN DEFAULT TRUE,
    sort_order    INTEGER DEFAULT 0,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO hearmed_reference.bundled_categories (category_name, sort_order)
VALUES
    ('Speaker', 1),
    ('Dome', 2),
    ('Other', 99)
ON CONFLICT (category_name) DO NOTHING;


-- ── 1h. Resource Types ───────────────────────────────────────────
-- Source: MIGRATION_RESOURCES_V2.sql

CREATE TABLE IF NOT EXISTS hearmed_reference.resource_types (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    type_name   VARCHAR(100) NOT NULL UNIQUE,
    is_active   BOOLEAN DEFAULT TRUE,
    sort_order  INTEGER DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO hearmed_reference.resource_types (type_name, sort_order)
VALUES
    ('Audiometer', 1),
    ('Wax Machine', 2),
    ('Endoscope', 3),
    ('Diagnostics', 4),
    ('Computer', 5),
    ('Other', 99)
ON CONFLICT (type_name) DO NOTHING;


-- ── 1i. Service Detail (Outcome Templates & Assignable Staff) ────
-- Source: MIGRATION_SERVICE_DETAIL.sql

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

ALTER TABLE hearmed_core.outcome_templates
    ADD COLUMN IF NOT EXISTS triggers_reminder boolean DEFAULT false;
ALTER TABLE hearmed_core.outcome_templates
    ADD COLUMN IF NOT EXISTS triggers_followup_call boolean DEFAULT false;
ALTER TABLE hearmed_core.outcome_templates
    ADD COLUMN IF NOT EXISTS followup_call_days integer DEFAULT 7;

CREATE TABLE IF NOT EXISTS hearmed_reference.service_assignable_staff (
    id         bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    service_id bigint NOT NULL REFERENCES hearmed_reference.services(id) ON DELETE CASCADE,
    staff_id   bigint NOT NULL REFERENCES hearmed_reference.staff(id) ON DELETE CASCADE,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(service_id, staff_id)
);


-- ════════════════════════════════════════════════════════════════════
-- SECTION 2: COLUMN ADDITIONS / FIXES (ALTER TABLE)
-- ════════════════════════════════════════════════════════════════════

-- ── 2a. Staff table fixes ─────────────────────────────────────────
-- Source: FIX_STAFF_TABLE_COLUMNS.sql, MIGRATION_ADD_MISSING_COLUMNS.sql

ALTER TABLE hearmed_reference.staff
    ADD COLUMN IF NOT EXISTS employee_number character varying(50),
    ADD COLUMN IF NOT EXISTS qualifications jsonb,
    ADD COLUMN IF NOT EXISTS hire_date date,
    ADD COLUMN IF NOT EXISTS photo_url character varying(500),
    ADD COLUMN IF NOT EXISTS created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS is_active boolean DEFAULT true,
    ADD COLUMN IF NOT EXISTS wp_user_id bigint UNIQUE;


-- ── 2b. Staff Auth fixes ──────────────────────────────────────────
-- Source: FIX_STAFF_AUTH_TABLE_COLUMNS.sql, MIGRATION_ADD_MISSING_COLUMNS.sql

ALTER TABLE hearmed_reference.staff_auth
    ADD COLUMN IF NOT EXISTS password_hash text,
    ADD COLUMN IF NOT EXISTS temp_password boolean DEFAULT false,
    ADD COLUMN IF NOT EXISTS two_factor_enabled boolean DEFAULT false,
    ADD COLUMN IF NOT EXISTS totp_secret character varying(64),
    ADD COLUMN IF NOT EXISTS last_login timestamp without time zone,
    ADD COLUMN IF NOT EXISTS username character varying(150),
    ADD COLUMN IF NOT EXISTS created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS staff_id bigint;

ALTER TABLE hearmed_reference.staff_auth
    ALTER COLUMN password_hash DROP NOT NULL;


-- ── 2c. Calendar Settings ─────────────────────────────────────────
-- Source: FIX_CALENDAR_SETTINGS_COLUMNS.sql

ALTER TABLE hearmed_core.calendar_settings
    ADD COLUMN IF NOT EXISTS display_full_name BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS prevent_location_mismatch BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS enabled_days VARCHAR(50),
    ADD COLUMN IF NOT EXISTS calendar_order VARCHAR(200),
    ADD COLUMN IF NOT EXISTS appointment_statuses TEXT,
    ADD COLUMN IF NOT EXISTS double_booking_warning BOOLEAN DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS show_patient BOOLEAN DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS show_service BOOLEAN DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS show_initials BOOLEAN DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS show_status BOOLEAN DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS appt_bg_color VARCHAR(10) DEFAULT '#0BB4C4',
    ADD COLUMN IF NOT EXISTS appt_font_color VARCHAR(10) DEFAULT '#ffffff',
    ADD COLUMN IF NOT EXISTS appt_badge_color VARCHAR(10) DEFAULT '#3b82f6',
    ADD COLUMN IF NOT EXISTS appt_badge_font_color VARCHAR(10) DEFAULT '#ffffff',
    ADD COLUMN IF NOT EXISTS appt_meta_color VARCHAR(10) DEFAULT '#38bdf8';


-- ── 2d. Products columns ──────────────────────────────────────────
-- Source: MIGRATION_PRODUCTS_POWER_TYPE.sql, MIGRATION_ADD_PRODUCT_VAT_NAME.sql,
--         MIGRATION_PRODUCTS_V2.sql, MIGRATION_PRODUCTS_V3_DOMES.sql,
--         MIGRATION_HEARING_AID_CLASS.sql, MIGRATION_ADMIN_PAGES_V51.sql

ALTER TABLE hearmed_reference.products
    ADD COLUMN IF NOT EXISTS item_type VARCHAR(50) DEFAULT 'product',
    ADD COLUMN IF NOT EXISTS power_type VARCHAR(50),
    ADD COLUMN IF NOT EXISTS vat_category VARCHAR(50) DEFAULT '',
    ADD COLUMN IF NOT EXISTS display_name VARCHAR(255) DEFAULT '',
    ADD COLUMN IF NOT EXISTS bundled_category VARCHAR(50),
    ADD COLUMN IF NOT EXISTS speaker_length VARCHAR(10),
    ADD COLUMN IF NOT EXISTS speaker_power VARCHAR(20),
    ADD COLUMN IF NOT EXISTS dome_type VARCHAR(50),
    ADD COLUMN IF NOT EXISTS dome_size VARCHAR(20),
    ADD COLUMN IF NOT EXISTS hearing_aid_class VARCHAR(20) DEFAULT '';

ALTER TABLE hearmed_reference.products
    ALTER COLUMN category DROP NOT NULL;

UPDATE hearmed_reference.products SET item_type = 'product' WHERE item_type IS NULL;

COMMENT ON COLUMN hearmed_reference.products.power_type IS 'Hearing aid power type: Rechargeable, 312 Battery, 13 Battery, 10 Battery, 675 Battery';
COMMENT ON COLUMN hearmed_reference.products.hearing_aid_class IS 'Hearing aid class: Custom or Ready-Fit. Affects delivery aging thresholds.';

CREATE INDEX IF NOT EXISTS idx_products_hearing_aid_class
    ON hearmed_reference.products (hearing_aid_class)
    WHERE hearing_aid_class IS NOT NULL AND hearing_aid_class != '';

-- Backfill vat_category based on item_type
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


-- ── 2e. Manufacturers columns ─────────────────────────────────────
-- Source: MIGRATION_APPROVALS_WORKFLOW.sql, MIGRATION_DOCUMENT_TEMPLATES.sql,
--         MIGRATION_PRODUCTS_V3_DOMES.sql

ALTER TABLE hearmed_reference.manufacturers
    ADD COLUMN IF NOT EXISTS order_email character varying(255),
    ADD COLUMN IF NOT EXISTS order_phone character varying(50),
    ADD COLUMN IF NOT EXISTS order_contact_name character varying(100),
    ADD COLUMN IF NOT EXISTS account_number character varying(50),
    ADD COLUMN IF NOT EXISTS address text,
    ADD COLUMN IF NOT EXISTS manufacturer_other_desc VARCHAR(255) DEFAULT '',
    ADD COLUMN IF NOT EXISTS manufacturer_category VARCHAR(100) DEFAULT '';


-- ── 2f. Services columns ─────────────────────────────────────────
-- Source: MIGRATION_SERVICES_COLUMNS.sql, MIGRATION_SERVICE_DETAIL.sql

ALTER TABLE hearmed_reference.services
    ADD COLUMN IF NOT EXISTS appointment_category VARCHAR(50),
    ADD COLUMN IF NOT EXISTS sales_opportunity BOOLEAN DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS income_bearing BOOLEAN DEFAULT TRUE,
    ADD COLUMN IF NOT EXISTS text_color VARCHAR(10) DEFAULT '#FFFFFF',
    ADD COLUMN IF NOT EXISTS reminder_enabled boolean DEFAULT false,
    ADD COLUMN IF NOT EXISTS reminder_sms_template_id bigint;


-- ── 2g. Resources columns ─────────────────────────────────────────
-- Source: MIGRATION_RESOURCES_V2.sql

ALTER TABLE hearmed_reference.resources
    ADD COLUMN IF NOT EXISTS resource_class VARCHAR(20) DEFAULT 'equipment',
    ADD COLUMN IF NOT EXISTS room_id BIGINT REFERENCES hearmed_reference.resources(id);

UPDATE hearmed_reference.resources
SET resource_class = 'room'
WHERE category = 'Room';


-- ── 2h. Calendar Blockouts columns ────────────────────────────────
-- Source: MIGRATION_ADMIN_PAGES_V51.sql

ALTER TABLE hearmed_core.calendar_blockouts
    ADD COLUMN IF NOT EXISTS rule_name VARCHAR(200),
    ADD COLUMN IF NOT EXISTS block_mode VARCHAR(20) DEFAULT 'only',
    ADD COLUMN IF NOT EXISTS appointment_type_id INTEGER REFERENCES hearmed_reference.appointment_types(id),
    ADD COLUMN IF NOT EXISTS clinic_id INTEGER REFERENCES hearmed_reference.clinics(id),
    ADD COLUMN IF NOT EXISTS day_of_week VARCHAR(20),
    ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE;


-- ── 2i. Staff Absences columns ────────────────────────────────────
-- Source: MIGRATION_ADMIN_PAGES_V51.sql

ALTER TABLE hearmed_core.staff_absences
    ADD COLUMN IF NOT EXISTS absence_type VARCHAR(50) DEFAULT 'holiday',
    ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'approved',
    ADD COLUMN IF NOT EXISTS notes TEXT;


-- ── 2j. KPI Targets — per-dispenser ──────────────────────────────
-- Source: MIGRATION_ADMIN_PAGES_V51.sql

ALTER TABLE hearmed_admin.kpi_targets
    ADD COLUMN IF NOT EXISTS staff_id INTEGER REFERENCES hearmed_reference.staff(id);


-- ════════════════════════════════════════════════════════════════════
-- SECTION 3: ORDERING & FITTING WORKFLOW
-- ════════════════════════════════════════════════════════════════════

-- ── 3a. Orders — approval & cancellation ──────────────────────────
-- Source: MIGRATION_APPROVALS_WORKFLOW.sql, MIGRATION_FITTING_WORKFLOW.sql

ALTER TABLE hearmed_core.orders
    ADD COLUMN IF NOT EXISTS approved_by         bigint REFERENCES hearmed_reference.staff(id),
    ADD COLUMN IF NOT EXISTS approved_date       timestamp without time zone,
    ADD COLUMN IF NOT EXISTS cancellation_type   character varying(30),
    ADD COLUMN IF NOT EXISTS cancellation_reason text,
    ADD COLUMN IF NOT EXISTS cancellation_date   timestamp without time zone,
    ADD COLUMN IF NOT EXISTS cost_total          numeric(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS received_date       timestamp without time zone,
    ADD COLUMN IF NOT EXISTS received_by         bigint REFERENCES hearmed_reference.staff(id),
    ADD COLUMN IF NOT EXISTS fitted_date         timestamp without time zone;

CREATE INDEX IF NOT EXISTS idx_orders_status ON hearmed_core.orders(current_status);
CREATE INDEX IF NOT EXISTS idx_orders_approved_by ON hearmed_core.orders(approved_by);
CREATE INDEX IF NOT EXISTS idx_orders_patient ON hearmed_core.orders(patient_id);


-- ── 3b. Order Items — dome/speaker/charger ────────────────────────
-- Source: MIGRATION_APPROVALS_WORKFLOW.sql

ALTER TABLE hearmed_core.order_items
    ADD COLUMN IF NOT EXISTS dome_type        character varying(50),
    ADD COLUMN IF NOT EXISTS dome_size        character varying(20),
    ADD COLUMN IF NOT EXISTS speaker_size     character varying(20),
    ADD COLUMN IF NOT EXISTS is_rechargeable  boolean DEFAULT false,
    ADD COLUMN IF NOT EXISTS needs_charger    boolean DEFAULT false,
    ADD COLUMN IF NOT EXISTS filter_type      character varying(50),
    ADD COLUMN IF NOT EXISTS bundled_items    jsonb;


-- ── 3c. Fitting Queue ─────────────────────────────────────────────
-- Source: MIGRATION_FITTING_WORKFLOW.sql

ALTER TABLE hearmed_core.fitting_queue
    ADD COLUMN IF NOT EXISTS received_date     timestamp without time zone,
    ADD COLUMN IF NOT EXISTS received_by       bigint REFERENCES hearmed_reference.staff(id),
    ADD COLUMN IF NOT EXISTS no_fitting_reason text,
    ADD COLUMN IF NOT EXISTS fitted_date       timestamp without time zone,
    ADD COLUMN IF NOT EXISTS fitted_by         bigint REFERENCES hearmed_reference.staff(id),
    ADD COLUMN IF NOT EXISTS patient_device_id bigint REFERENCES hearmed_core.patient_devices(id);

CREATE INDEX IF NOT EXISTS idx_fitting_queue_status ON hearmed_core.fitting_queue(queue_status);
CREATE INDEX IF NOT EXISTS idx_fitting_queue_order ON hearmed_core.fitting_queue(order_id);
CREATE INDEX IF NOT EXISTS idx_appointments_patient ON hearmed_core.appointments(patient_id);
CREATE INDEX IF NOT EXISTS idx_patient_devices_patient ON hearmed_core.patient_devices(patient_id);


-- ════════════════════════════════════════════════════════════════════
-- SECTION 4: REPAIRS
-- ════════════════════════════════════════════════════════════════════
-- Source: MIGRATION_FIX_REPAIRS_TABLE.sql, MIGRATION_PATIENT_ENHANCEMENTS.sql

ALTER TABLE hearmed_core.repairs
    ADD COLUMN IF NOT EXISTS repair_number character varying(30),
    ADD COLUMN IF NOT EXISTS repair_reason text,
    ADD COLUMN IF NOT EXISTS under_warranty boolean DEFAULT false,
    ADD COLUMN IF NOT EXISTS sent_to character varying(200),
    ADD COLUMN IF NOT EXISTS tracking_number character varying(100),
    ADD COLUMN IF NOT EXISTS repair_cost numeric(10,2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS notified_dispenser boolean DEFAULT false;

CREATE INDEX IF NOT EXISTS idx_repairs_repair_number ON hearmed_core.repairs(repair_number);
CREATE INDEX IF NOT EXISTS idx_repairs_repair_status ON hearmed_core.repairs(repair_status);
CREATE INDEX IF NOT EXISTS idx_repairs_date_sent ON hearmed_core.repairs(date_sent);

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_sequences
        WHERE schemaname = 'hearmed_core'
          AND sequencename = 'repair_number_seq'
    ) THEN
        CREATE SEQUENCE hearmed_core.repair_number_seq START WITH 1001;
    END IF;
END $$;


-- ════════════════════════════════════════════════════════════════════
-- SECTION 5: PATIENT ENHANCEMENTS
-- ════════════════════════════════════════════════════════════════════
-- Source: MIGRATION_PATIENT_ENHANCEMENTS.sql

-- Pinned notes
ALTER TABLE hearmed_core.patient_notes
    ADD COLUMN IF NOT EXISTS is_pinned boolean DEFAULT false;

CREATE INDEX IF NOT EXISTS idx_patient_notes_pinned
    ON hearmed_core.patient_notes(patient_id, is_pinned) WHERE is_pinned = true;

-- Credit Notes — exchange & refund tracking
ALTER TABLE hearmed_core.credit_notes
    ADD COLUMN IF NOT EXISTS exchange_id bigint,
    ADD COLUMN IF NOT EXISTS refund_type character varying(30) DEFAULT 'cheque',
    ADD COLUMN IF NOT EXISTS cheque_number character varying(50),
    ADD COLUMN IF NOT EXISTS processed_by bigint,
    ADD COLUMN IF NOT EXISTS processed_at timestamp without time zone;

CREATE INDEX IF NOT EXISTS idx_credit_notes_cheque_sent ON hearmed_core.credit_notes(cheque_sent);
CREATE INDEX IF NOT EXISTS idx_credit_notes_patient ON hearmed_core.credit_notes(patient_id);

-- Patient Devices — purchase tracking
ALTER TABLE hearmed_core.patient_devices
    ADD COLUMN IF NOT EXISTS purchase_date date,
    ADD COLUMN IF NOT EXISTS order_id bigint;

-- Patients — PRSI tracking
ALTER TABLE hearmed_core.patients
    ADD COLUMN IF NOT EXISTS last_prsi_claim_date date,
    ADD COLUMN IF NOT EXISTS next_prsi_eligible_date date;


-- ════════════════════════════════════════════════════════════════════
-- DONE
-- ════════════════════════════════════════════════════════════════════

COMMIT;

SELECT 'All migrations applied successfully' AS status;
