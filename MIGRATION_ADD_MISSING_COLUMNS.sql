-- Migration: Add missing columns to HearMed Portal tables
-- Run this on your Railway PostgreSQL if you get "column does not exist" errors
-- Date: 2026-02-24

-- Add missing columns to hearmed_reference.staff table
ALTER TABLE hearmed_reference.staff
ADD COLUMN IF NOT EXISTS employee_number character varying(50),
ADD COLUMN IF NOT EXISTS qualifications jsonb,
ADD COLUMN IF NOT EXISTS photo_url character varying(500);

-- Add missing columns to hearmed_reference.staff_auth table
ALTER TABLE hearmed_reference.staff_auth
ADD COLUMN IF NOT EXISTS last_login timestamp without time zone;

-- Fix staff_auth password_hash to allow NULL (for first-sign-in flow)
ALTER TABLE hearmed_reference.staff_auth
ALTER COLUMN password_hash DROP NOT NULL;

-- Verify tables exist and have correct structure
SELECT table_name FROM information_schema.tables 
WHERE table_schema = 'hearmed_reference' 
AND table_name IN ('staff', 'staff_auth', 'staff_clinics');

-- Show columns in staff table
SELECT column_name, data_type, is_nullable 
FROM information_schema.columns 
WHERE table_schema = 'hearmed_reference' 
AND table_name = 'staff'
ORDER BY ordinal_position;

-- Show columns in staff_auth table
SELECT column_name, data_type, is_nullable 
FROM information_schema.columns 
WHERE table_schema = 'hearmed_reference' 
AND table_name = 'staff_auth'
ORDER BY ordinal_position;
