-- ========================================================
-- HearMed Migration: Resources v2
-- ========================================================
-- Adds resource_class column (room/equipment), room_id FK,
-- and resource_types table for dynamic equipment types.
-- ========================================================

-- 1. Add resource_class column: 'room' or 'equipment'
ALTER TABLE hearmed_reference.resources
    ADD COLUMN IF NOT EXISTS resource_class VARCHAR(20) DEFAULT 'equipment';

-- 2. Add room_id for equipment → room relationship
ALTER TABLE hearmed_reference.resources
    ADD COLUMN IF NOT EXISTS room_id BIGINT REFERENCES hearmed_reference.resources(id);

-- 3. Resource types table (dynamic list of equipment types)
CREATE TABLE IF NOT EXISTS hearmed_reference.resource_types (
    id          BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    type_name   VARCHAR(100) NOT NULL UNIQUE,
    is_active   BOOLEAN DEFAULT TRUE,
    sort_order  INTEGER DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed default resource types
INSERT INTO hearmed_reference.resource_types (type_name, sort_order)
VALUES
    ('Audiometer', 1),
    ('Wax Machine', 2),
    ('Endoscope', 3),
    ('Diagnostics', 4),
    ('Computer', 5),
    ('Other', 99)
ON CONFLICT (type_name) DO NOTHING;

-- 4. Set existing resources with category 'Room' to resource_class='room'
UPDATE hearmed_reference.resources
SET resource_class = 'room'
WHERE category = 'Room';

-- Verify
SELECT 'Resources v2 migration complete' AS status;
