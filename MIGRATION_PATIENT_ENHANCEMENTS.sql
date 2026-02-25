-- ============================================================
-- MIGRATION: Patient Module Enhancements
-- Adds columns for pinned notes, repair numbering (HMREP),
-- exchange tracking, and refund processing.
-- Safe to run multiple times (IF NOT EXISTS).
-- ============================================================

-- 1. Patient Notes — pinned note feature
ALTER TABLE hearmed_core.patient_notes
ADD COLUMN IF NOT EXISTS is_pinned boolean DEFAULT false;

-- 2. Repairs — HMREP repair number + additional tracking
ALTER TABLE hearmed_core.repairs
ADD COLUMN IF NOT EXISTS repair_number character varying(20);

ALTER TABLE hearmed_core.repairs
ADD COLUMN IF NOT EXISTS repair_reason text;

ALTER TABLE hearmed_core.repairs
ADD COLUMN IF NOT EXISTS sent_to character varying(200);

ALTER TABLE hearmed_core.repairs
ADD COLUMN IF NOT EXISTS tracking_number character varying(100);

ALTER TABLE hearmed_core.repairs
ADD COLUMN IF NOT EXISTS under_warranty boolean DEFAULT false;

ALTER TABLE hearmed_core.repairs
ADD COLUMN IF NOT EXISTS repair_cost numeric(10,2) DEFAULT 0.00;

ALTER TABLE hearmed_core.repairs
ADD COLUMN IF NOT EXISTS notified_dispenser boolean DEFAULT false;

-- 3. Credit Notes — exchange tracking
ALTER TABLE hearmed_core.credit_notes
ADD COLUMN IF NOT EXISTS exchange_id bigint;

ALTER TABLE hearmed_core.credit_notes
ADD COLUMN IF NOT EXISTS refund_type character varying(30) DEFAULT 'cheque';

ALTER TABLE hearmed_core.credit_notes
ADD COLUMN IF NOT EXISTS cheque_number character varying(50);

ALTER TABLE hearmed_core.credit_notes
ADD COLUMN IF NOT EXISTS processed_by bigint;

ALTER TABLE hearmed_core.credit_notes
ADD COLUMN IF NOT EXISTS processed_at timestamp without time zone;

-- 4. Patient Devices — purchase date tracking
ALTER TABLE hearmed_core.patient_devices
ADD COLUMN IF NOT EXISTS purchase_date date;

ALTER TABLE hearmed_core.patient_devices
ADD COLUMN IF NOT EXISTS order_id bigint;

-- 5. Patients — structured address + PRSI tracking
-- (address_line1, address_line2, city, county, eircode already exist in schema)
ALTER TABLE hearmed_core.patients
ADD COLUMN IF NOT EXISTS last_prsi_claim_date date;

ALTER TABLE hearmed_core.patients
ADD COLUMN IF NOT EXISTS next_prsi_eligible_date date;

-- 6. Sequence for HMREP repair numbers
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_sequences WHERE schemaname = 'hearmed_core' AND sequencename = 'repair_number_seq') THEN
        CREATE SEQUENCE hearmed_core.repair_number_seq START WITH 1001;
    END IF;
END $$;

-- 7. Index for repair_number lookups
CREATE INDEX IF NOT EXISTS idx_repairs_repair_number ON hearmed_core.repairs(repair_number);
CREATE INDEX IF NOT EXISTS idx_repairs_repair_status ON hearmed_core.repairs(repair_status);
CREATE INDEX IF NOT EXISTS idx_repairs_date_sent ON hearmed_core.repairs(date_sent);

-- 8. Index for credit note refund processing
CREATE INDEX IF NOT EXISTS idx_credit_notes_cheque_sent ON hearmed_core.credit_notes(cheque_sent);
CREATE INDEX IF NOT EXISTS idx_credit_notes_patient ON hearmed_core.credit_notes(patient_id);

-- 9. Index for pinned notes
CREATE INDEX IF NOT EXISTS idx_patient_notes_pinned ON hearmed_core.patient_notes(patient_id, is_pinned) WHERE is_pinned = true;

-- ============================================================
-- VERIFY
-- ============================================================
SELECT 'Migration complete — patient_notes.is_pinned, repairs.repair_number, credit_notes.exchange_id added' AS status;
