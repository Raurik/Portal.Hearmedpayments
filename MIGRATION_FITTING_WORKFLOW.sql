-- ═══════════════════════════════════════════════════════════════
-- MIGRATION: Fitting Workflow Columns
-- Run on Railway PostgreSQL AFTER MIGRATION_APPROVALS_WORKFLOW.sql
-- ═══════════════════════════════════════════════════════════════

-- 1) Fitting-queue columns for receive/fit tracking
ALTER TABLE hearmed_core.fitting_queue
    ADD COLUMN IF NOT EXISTS received_date  timestamp without time zone,
    ADD COLUMN IF NOT EXISTS received_by    bigint REFERENCES hearmed_reference.staff(id),
    ADD COLUMN IF NOT EXISTS no_fitting_reason text,
    ADD COLUMN IF NOT EXISTS fitted_date    timestamp without time zone,
    ADD COLUMN IF NOT EXISTS fitted_by      bigint REFERENCES hearmed_reference.staff(id),
    ADD COLUMN IF NOT EXISTS patient_device_id bigint REFERENCES hearmed_core.patient_devices(id);

-- 2) Orders: received/fitted tracking (if not already present)
ALTER TABLE hearmed_core.orders
    ADD COLUMN IF NOT EXISTS received_date  timestamp without time zone,
    ADD COLUMN IF NOT EXISTS received_by    bigint REFERENCES hearmed_reference.staff(id),
    ADD COLUMN IF NOT EXISTS fitted_date    timestamp without time zone;

-- 3) Indexes for fitting lookups
CREATE INDEX IF NOT EXISTS idx_fitting_queue_status ON hearmed_core.fitting_queue(queue_status);
CREATE INDEX IF NOT EXISTS idx_fitting_queue_order  ON hearmed_core.fitting_queue(order_id);
CREATE INDEX IF NOT EXISTS idx_orders_patient       ON hearmed_core.orders(patient_id);
CREATE INDEX IF NOT EXISTS idx_appointments_patient  ON hearmed_core.appointments(patient_id);
CREATE INDEX IF NOT EXISTS idx_patient_devices_patient ON hearmed_core.patient_devices(patient_id);
