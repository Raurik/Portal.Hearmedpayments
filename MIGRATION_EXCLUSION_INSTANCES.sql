-- ================================================================
-- Migration: Exclusion Instances Table
-- Stores scheduled exclusion/unavailability instances on the calendar.
-- Safe to run multiple times (IF NOT EXISTS).
-- ================================================================

CREATE TABLE IF NOT EXISTS hearmed_core.exclusion_instances (
    id SERIAL PRIMARY KEY,
    exclusion_type_id INTEGER NOT NULL,
    staff_id INTEGER,
    scope VARCHAR(20) DEFAULT 'full_day',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    start_time TIME,
    end_time TIME,
    reason TEXT,
    repeat_type VARCHAR(20) DEFAULT 'none',
    repeat_days VARCHAR(20),
    repeat_until DATE,
    created_by INTEGER,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    is_active BOOLEAN DEFAULT true
);

-- Optional FK to exclusion_types reference table
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_class WHERE relname = 'exclusion_types') THEN
        IF NOT EXISTS (
            SELECT 1 FROM pg_constraint WHERE conname = 'fk_exclusion_instances_type'
        ) THEN
            ALTER TABLE hearmed_core.exclusion_instances
                ADD CONSTRAINT fk_exclusion_instances_type
                FOREIGN KEY (exclusion_type_id)
                REFERENCES hearmed_reference.exclusion_types(id)
                ON DELETE CASCADE;
        END IF;
    END IF;
END $$;

-- Index for date range queries
CREATE INDEX IF NOT EXISTS idx_excl_inst_dates ON hearmed_core.exclusion_instances (start_date, end_date) WHERE is_active = true;
CREATE INDEX IF NOT EXISTS idx_excl_inst_staff ON hearmed_core.exclusion_instances (staff_id) WHERE is_active = true;
