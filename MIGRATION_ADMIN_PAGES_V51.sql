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

-- 3. Add clinic_id and role_id to staff_groups table
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

-- ============================================================
-- DONE — Verify with:
-- SELECT table_name FROM information_schema.tables 
-- WHERE table_schema = 'hearmed_reference' ORDER BY table_name;
-- ============================================================
