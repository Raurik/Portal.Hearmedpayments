-- Add missing columns to hearmed_reference.staff table
-- Run this in your PostgreSQL database (psql or any SQL client)
-- Safe to run even if columns already exist (uses IF NOT EXISTS)

-- Add employee_number column
ALTER TABLE hearmed_reference.staff
ADD COLUMN IF NOT EXISTS employee_number character varying(50);

-- Add qualifications column
ALTER TABLE hearmed_reference.staff
ADD COLUMN IF NOT EXISTS qualifications jsonb;

-- Add hire_date column
ALTER TABLE hearmed_reference.staff
ADD COLUMN IF NOT EXISTS hire_date date;

-- Add photo_url column
ALTER TABLE hearmed_reference.staff
ADD COLUMN IF NOT EXISTS photo_url character varying(500);

-- Add created_at column if missing
ALTER TABLE hearmed_reference.staff
ADD COLUMN IF NOT EXISTS created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP;

-- Add updated_at column if missing
ALTER TABLE hearmed_reference.staff
ADD COLUMN IF NOT EXISTS updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP;

-- Add is_active column if missing
ALTER TABLE hearmed_reference.staff
ADD COLUMN IF NOT EXISTS is_active boolean DEFAULT true;

-- Add wp_user_id column if missing
ALTER TABLE hearmed_reference.staff
ADD COLUMN IF NOT EXISTS wp_user_id bigint UNIQUE;

-- Verify the table structure
SELECT column_name, data_type, is_nullable, column_default
FROM information_schema.columns
WHERE table_schema = 'hearmed_reference' AND table_name = 'staff'
ORDER BY ordinal_position;
