-- ============================================================
-- MIGRATION: Admin Pages Overhaul v5.1
-- Run this against your Railway PostgreSQL database.
-- ============================================================

-- 1. Exclusion Types table (NEW)
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

-- Seed default exclusion types
INSERT INTO hearmed_reference.exclusion_types (type_name, color, description, sort_order)
VALUES
    ('Lunch Break', '#f59e0b', 'Staff lunch break', 1),
    ('Holiday', '#ef4444', 'Staff holiday / annual leave', 2),
    ('Meeting', '#8b5cf6', 'Internal meeting', 3),
    ('Training', '#3b82f6', 'Training session', 4),
    ('Sick Leave', '#f97316', 'Sick day', 5),
    ('Other', '#6b7280', 'Other exclusion', 6)
ON CONFLICT DO NOTHING;

-- 2. Create resources table if it doesn't exist, then add clinic_id
CREATE TABLE IF NOT EXISTS hearmed_reference.resources (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    title       VARCHAR(200) NOT NULL,
    category    VARCHAR(100),
    url         VARCHAR(500),
    description TEXT,
    sort_order  INTEGER DEFAULT 0 NOT NULL,
    is_active   BOOLEAN DEFAULT TRUE NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE hearmed_reference.resources
    ADD COLUMN IF NOT EXISTS clinic_id INTEGER REFERENCES hearmed_reference.clinics(id);

-- 3. Create staff_groups table if it doesn't exist, then add clinic_id and role_id
CREATE TABLE IF NOT EXISTS hearmed_reference.staff_groups (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    group_name  VARCHAR(150) NOT NULL,
    description TEXT,
    is_active   BOOLEAN DEFAULT TRUE NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS hearmed_reference.staff_group_members (
    id         BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    group_id   BIGINT REFERENCES hearmed_reference.staff_groups(id) ON DELETE CASCADE,
    staff_id   BIGINT REFERENCES hearmed_reference.staff(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE hearmed_reference.staff_groups
    ADD COLUMN IF NOT EXISTS clinic_id INTEGER REFERENCES hearmed_reference.clinics(id),
    ADD COLUMN IF NOT EXISTS role_id INTEGER REFERENCES hearmed_reference.roles(id);

-- 4. Add item_type to products table (for 5-category support)
ALTER TABLE hearmed_reference.products
    ADD COLUMN IF NOT EXISTS item_type VARCHAR(20) DEFAULT 'product';

-- Set existing products to type 'product'
UPDATE hearmed_reference.products SET item_type = 'product' WHERE item_type IS NULL;

-- 5. Add staff_id to kpi_targets for per-dispenser targets
ALTER TABLE hearmed_admin.kpi_targets
    ADD COLUMN IF NOT EXISTS staff_id INTEGER REFERENCES hearmed_reference.staff(id);

-- 6. Extend calendar_blockouts table with new columns for the blockout rules page
ALTER TABLE hearmed_core.calendar_blockouts
    ADD COLUMN IF NOT EXISTS rule_name VARCHAR(200),
    ADD COLUMN IF NOT EXISTS block_mode VARCHAR(20) DEFAULT 'only',
    ADD COLUMN IF NOT EXISTS appointment_type_id INTEGER REFERENCES hearmed_reference.appointment_types(id),
    ADD COLUMN IF NOT EXISTS clinic_id INTEGER REFERENCES hearmed_reference.clinics(id),
    ADD COLUMN IF NOT EXISTS day_of_week VARCHAR(20),
    ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE;

-- 7. Extend staff_absences table with missing columns for holidays page
ALTER TABLE hearmed_core.staff_absences
    ADD COLUMN IF NOT EXISTS absence_type VARCHAR(50) DEFAULT 'holiday',
    ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'approved',
    ADD COLUMN IF NOT EXISTS notes TEXT;

-- 8. Create hearmed_range table if missing (used by Products / Range Settings)
CREATE TABLE IF NOT EXISTS hearmed_reference.hearmed_range (
    id             BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    range_name     VARCHAR(150) NOT NULL,
    price_total    NUMERIC(10,2),
    price_ex_prsi  NUMERIC(10,2),
    is_active      BOOLEAN DEFAULT TRUE NOT NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 9. Create appointment_types table if missing (used by Blockouts, Calendar)
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

-- 10. Create GDPR documents table (for policy/form PDF uploads)
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

-- 11. Create referral_sources table if missing (used by Lead Types taxonomy)
CREATE TABLE IF NOT EXISTS hearmed_reference.referral_sources (
    id           BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    source_name  VARCHAR(150) NOT NULL,
    parent_id    BIGINT,
    sort_order   INTEGER DEFAULT 0,
    is_active    BOOLEAN DEFAULT TRUE NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 12. Create manufacturers table if missing (used by Brands taxonomy)
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

-- ============================================================
-- DONE — Verify with:
-- SELECT table_name FROM information_schema.tables 
-- WHERE table_schema = 'hearmed_reference' ORDER BY table_name;
-- SELECT table_name FROM information_schema.tables 
-- WHERE table_schema = 'hearmed_admin' ORDER BY table_name;
-- ============================================================
