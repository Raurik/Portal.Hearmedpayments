-- ========================================================
-- HearMed Portal — Minimum Viable Data Seed
-- ========================================================
-- This script seeds the PostgreSQL database with essential
-- reference data needed to get the system functional:
-- - 1 clinic (HearMed Dublin)
-- - 1 dispenser staff member
-- - 1 patient (test case)
-- - Essential services & appointment types
-- - Basic outcomes for testing
--
-- Run this AFTER creating the database schema.
-- Use: psql -U <user> -d hearmed_prod < SEED_DATA.sql
-- ========================================================

-- ========================================================
-- CLINICS (hearmed_reference.clinics)
-- ========================================================
INSERT INTO hearmed_reference.clinics
(clinic_name, clinic_colour, text_colour, address, city, county, phone, is_active, created_at, created_by)
VALUES
('HearMed Dublin', '#0BB4C4', '#FFFFFF', '123 Main Street', 'Dublin', 'Dublin', '+353 1 234 5678', true, NOW(), 'system');

-- ========================================================
-- ROLES (hearmed_reference.roles) — Default roles
-- ========================================================
INSERT INTO hearmed_reference.roles
(role_name, display_name, description, permissions, is_active)
VALUES
('admin', 'Administrator', 'Full system access', '["view_all", "create_all", "edit_all", "delete_all", "manage_staff", "manage_roles"]'::jsonb, true),
('manager', 'Manager', 'Clinic management and staff oversight', '["view_own_clinic", "create_appointments", "edit_own_clinic", "manage_staff"]'::jsonb, true),
('dispenser', 'Dispenser', 'Dispense healthcare products and services', '["view_appointments", "dispense_products", "record_outcomes"]'::jsonb, true),
('audiologist', 'Audiologist', 'Perform audiological services and assessments', '["view_patients", "create_notes", "order_tests", "record_assessments"]'::jsonb, true),
('receptionist', 'Receptionist', 'Reception and appointment scheduling', '["view_patients", "create_appointments", "manage_calendar"]'::jsonb, true),
('finance', 'Finance Officer', 'Invoice and payment management', '["view_invoices", "edit_invoices", "record_payments", "generate_reports"]'::jsonb, true);

-- Get the clinic ID for use in subsequent inserts
DO $$
DECLARE
    clinic_id INT;
BEGIN
    SELECT id INTO clinic_id FROM hearmed_reference.clinics WHERE clinic_name = 'HearMed Dublin' LIMIT 1;
    
    -- ========================================================
    -- STAFF (hearmed_reference.staff) — Dispenser
    -- ========================================================
    INSERT INTO hearmed_reference.staff
    (full_name, initials, role_type, user_account, is_active, created_at, created_by)
    VALUES
    ('John Dispenser', 'JD', 'dispenser', 'john.dispenser', true, NOW(), 'system');
    
    -- Link staff to clinic (hearmed_reference.staff_clinics)
    INSERT INTO hearmed_reference.staff_clinics (staff_id, clinic_id, created_at)
    SELECT s.id, clinic_id, NOW()
    FROM hearmed_reference.staff s
    WHERE s.full_name = 'John Dispenser'
    LIMIT 1;
    
    -- ========================================================
    -- SERVICES (hearmed_reference.services)
    -- ========================================================
    INSERT INTO hearmed_reference.services
    (service_name, colour, duration, is_active, sales_opportunity, income_bearing, appointment_category, created_at, created_by)
    VALUES
    ('Initial Consultation', '#3B82F6', 30, true, false, true, 'consultation', NOW(), 'system'),
    ('Fitting & Adjustment', '#10B981', 45, true, true, true, 'service', NOW(), 'system'),
    ('Follow-up Review', '#8B5CF6', 20, true, false, true, 'review', NOW(), 'system'),
    ('Ear Impression', '#F59E0B', 15, true, true, false, 'diagnostic', NOW(), 'system');
    
    -- ========================================================
    -- APPOINTMENT TYPES (hearmed_core.appointment_types)
    -- ========================================================
    INSERT INTO hearmed_core.appointment_types
    (type_name, colour, duration, is_active, clinic_id, created_at, created_by)
    VALUES
    ('Initial Assessment', '#0BB4C4', 45, true, clinic_id, NOW(), 'system'),
    ('Hearing Test', '#3B82F6', 30, true, clinic_id, NOW(), 'system'),
    ('Hearing Aid Fitting', '#10B981', 60, true, clinic_id, NOW(), 'system'),
    ('Repair & Service', '#F59E0B', 30, true, clinic_id, NOW(), 'system'),
    ('Follow-up', '#8B5CF6', 20, true, clinic_id, NOW(), 'system');
    
    -- ========================================================
    -- OUTCOME TEMPLATES (hearmed_core.outcome_templates)
    -- ========================================================
    INSERT INTO hearmed_core.outcome_templates
    (template_name, description, fields_json, is_active, clinic_id, created_at, created_by)
    VALUES
    ('Standard Assessment', 'Basic hearing assessment outcome', 
     '{"left_air": "", "left_bone": "", "right_air": "", "right_bone": "", "interpretation": ""}', 
     true, clinic_id, NOW(), 'system'),
    ('Hearing Aid Fitting', 'Post-fitting outcome documentation',
     '{"gain_settings": "", "patient_feedback": "", "follow_up_date": "", "notes": ""}',
     true, clinic_id, NOW(), 'system'),
    ('Repair Assessment', 'Device repair completion notes',
     '{"issue_found": "", "repair_type": "", "testing_result": "", "status": ""}',
     true, clinic_id, NOW(), 'system');
    
    -- ========================================================
    -- PRODUCTS (hearmed_reference.products) — Hearing Aids
    -- ========================================================
    INSERT INTO hearmed_reference.products
    (product_name, manufacturer_id, colour, is_hearing_aid, is_active, cost_to_dispensary, selling_price, commission_rate, created_at, created_by)
    VALUES
    ('ReSound Omnia A', 1, 'silver', true, true, 800.00, 1600.00, 15.00, NOW(), 'system'),
    ('Phonak Paradise Pro', 1, 'black', true, true, 750.00, 1500.00, 15.00, NOW(), 'system'),
    ('Signia Styletto X', 2, 'gold', true, true, 900.00, 1800.00, 15.00, NOW(), 'system');
    
    -- ========================================================
    -- PATIENTS (hearmed_core.patients) — Test Patient
    -- ========================================================
    INSERT INTO hearmed_core.patients
    (first_name, last_name, date_of_birth, gender, email, phone_primary, address, city, county, postcode, clinic_id, is_active, gdpr_consent, created_at, created_by, patient_type)
    VALUES
    ('Test', 'Patient', '1975-05-15'::DATE, 'M', 'test@hearmed.ie', '+353 1 555 0123', '456 Test Lane', 'Dublin', 'Dublin', 'D01 2AB', clinic_id, true, true, NOW(), 'system', 'hearing_aid_user');
    
    -- ========================================================
    -- PAYMENT METHODS (hearmed_reference.payment_methods)
    -- ========================================================
    INSERT INTO hearmed_reference.payment_methods
    (method_name, is_active, qbo_account_id, created_at, created_by)
    VALUES
    ('Cash', true, 'cash', NOW(), 'system'),
    ('Debit Card', true, 'card', NOW(), 'system'),
    ('Bank Transfer', true, 'bank', NOW(), 'system'),
    ('Insurance Claim', true, 'insurance', NOW(), 'system');
    
END $$;

-- ========================================================
-- VERIFICATION QUERIES
-- ========================================================
-- Uncomment to verify data was inserted:
--
-- SELECT 'Clinics' as table_name, COUNT(*) as row_count FROM hearmed_reference.clinics
-- UNION
-- SELECT 'Staff' as table_name, COUNT(*) as row_count FROM hearmed_reference.staff
-- UNION
-- SELECT 'Services' as table_name, COUNT(*) as row_count FROM hearmed_reference.services
-- UNION
-- SELECT 'Appointment Types' as table_name, COUNT(*) as row_count FROM hearmed_core.appointment_types
-- UNION
-- SELECT 'Patients' as table_name, COUNT(*) as row_count FROM hearmed_core.patients
-- UNION
-- SELECT 'Products' as table_name, COUNT(*) as row_count FROM hearmed_reference.products
-- UNION
-- SELECT 'Outcome Templates' as table_name, COUNT(*) as row_count FROM hearmed_core.outcome_templates;
