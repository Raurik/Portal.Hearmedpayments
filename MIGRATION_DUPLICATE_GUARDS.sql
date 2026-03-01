-- ================================================================
-- MIGRATION: Duplicate prevention guards
-- Adds unique partial indexes to prevent duplicate serial numbers
-- and a unique index on patient name + DOB + address
-- ================================================================

-- Unique index on serial_number_left (non-empty values only)
CREATE UNIQUE INDEX IF NOT EXISTS idx_patient_devices_serial_left_uniq
    ON hearmed_core.patient_devices (serial_number_left)
    WHERE COALESCE(TRIM(serial_number_left), '') <> '';

-- Unique index on serial_number_right (non-empty values only)
CREATE UNIQUE INDEX IF NOT EXISTS idx_patient_devices_serial_right_uniq
    ON hearmed_core.patient_devices (serial_number_right)
    WHERE COALESCE(TRIM(serial_number_right), '') <> '';

-- Cross-check: serial_number_left must not appear as any serial_number_right
-- (enforced in application code — cannot be done with a single unique index)

-- Unique index on patient name + DOB + address to prevent duplicate patients
CREATE UNIQUE INDEX IF NOT EXISTS idx_patients_name_dob_addr_uniq
    ON hearmed_core.patients (
        LOWER(TRIM(first_name)),
        LOWER(TRIM(last_name)),
        date_of_birth,
        LOWER(TRIM(COALESCE(address_line1, '')))
    )
    WHERE date_of_birth IS NOT NULL;
