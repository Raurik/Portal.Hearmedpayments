-- ============================================================
-- MIGRATION: Fix appointment_status CHECK constraint
-- ============================================================
-- The original constraint only allows:
--   Confirmed, Pending, Cancelled, No Show, Completed, Rescheduled
--
-- This migration updates it to include ALL 10 statuses:
--   Not Confirmed, Confirmed, Arrived, In Progress, Completed,
--   No Show, Late, Pending, Cancelled, Rescheduled
--
-- It also changes the column default from 'Confirmed' to 'Not Confirmed'.
-- ============================================================

-- 1. Drop the old constraint
ALTER TABLE hearmed_core.appointments
    DROP CONSTRAINT IF EXISTS chk_appointment_status;

-- 2. Add the updated constraint with all 10 statuses
ALTER TABLE hearmed_core.appointments
    ADD CONSTRAINT chk_appointment_status
    CHECK (appointment_status IN (
        'Not Confirmed',
        'Confirmed',
        'Arrived',
        'In Progress',
        'Completed',
        'No Show',
        'Late',
        'Pending',
        'Cancelled',
        'Rescheduled'
    ));

-- 3. Change column default from 'Confirmed' to 'Not Confirmed'
ALTER TABLE hearmed_core.appointments
    ALTER COLUMN appointment_status SET DEFAULT 'Not Confirmed';

-- 4. (Optional) Update any existing appointments with NULL status
UPDATE hearmed_core.appointments
    SET appointment_status = 'Not Confirmed'
    WHERE appointment_status IS NULL;
