-- Add missing columns to hearmed_reference.staff_auth table
-- Run this in your PostgreSQL database (psql or any SQL client)
-- Safe to run even if columns already exist (uses IF NOT EXISTS)

-- Add password_hash column if missing (allow NULL for first-sign-in flow)
ALTER TABLE hearmed_reference.staff_auth
ADD COLUMN IF NOT EXISTS password_hash text;

-- Drop NOT NULL constraint on password_hash if it exists
ALTER TABLE hearmed_reference.staff_auth
ALTER COLUMN password_hash DROP NOT NULL;

-- Add temp_password column if missing
ALTER TABLE hearmed_reference.staff_auth
ADD COLUMN IF NOT EXISTS temp_password boolean DEFAULT false;

-- Add two_factor_enabled column if missing
ALTER TABLE hearmed_reference.staff_auth
ADD COLUMN IF NOT EXISTS two_factor_enabled boolean DEFAULT false;

-- Add totp_secret column if missing
ALTER TABLE hearmed_reference.staff_auth
ADD COLUMN IF NOT EXISTS totp_secret character varying(64);

-- Add last_login column if missing
ALTER TABLE hearmed_reference.staff_auth
ADD COLUMN IF NOT EXISTS last_login timestamp without time zone;

-- Add username column if missing
ALTER TABLE hearmed_reference.staff_auth
ADD COLUMN IF NOT EXISTS username character varying(150);

-- Add created_at column if missing
ALTER TABLE hearmed_reference.staff_auth
ADD COLUMN IF NOT EXISTS created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP;

-- Add updated_at column if missing
ALTER TABLE hearmed_reference.staff_auth
ADD COLUMN IF NOT EXISTS updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP;

-- Add staff_id column if missing
ALTER TABLE hearmed_reference.staff_auth
ADD COLUMN IF NOT EXISTS staff_id bigint;

-- Verify the table structure
SELECT column_name, data_type, is_nullable, column_default
FROM information_schema.columns
WHERE table_schema = 'hearmed_reference' AND table_name = 'staff_auth'
ORDER BY ordinal_position;
