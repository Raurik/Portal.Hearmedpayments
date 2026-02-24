-- ========================================================
-- HearMed Migration: Add Roles Table
-- ========================================================
-- Run this on your Railway PostgreSQL database to add the
-- hearmed_reference.roles table and seed default roles.
-- 
-- Usage: psql -U <user> -d <database> < MIGRATION_ADD_ROLES_TABLE.sql
-- ========================================================

-- Create roles table in hearmed_reference schema
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

-- Add index on role_name for faster lookups
CREATE INDEX idx_roles_role_name ON hearmed_reference.roles(role_name);
CREATE INDEX idx_roles_is_active ON hearmed_reference.roles(is_active);

-- Seed default roles (only if they don't already exist)
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

-- Verify
SELECT 'Roles table created successfully' AS status, COUNT(*) as role_count FROM hearmed_reference.roles;
