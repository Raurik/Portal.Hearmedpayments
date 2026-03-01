-- ============================================================
-- MIGRATION: Backfill order_id on patient_devices
-- ============================================================
-- Problem: patient_devices records created by the fitting receive
-- flow were missing order_id, so invoices couldn't find serial
-- numbers when querying WHERE pd.order_id = inv.order_id.
--
-- This migration links existing patient_devices to their orders
-- by matching on patient_id + product_id and looking for orders
-- that are in Awaiting Fitting / Complete / Fitted status.
-- ============================================================

-- Backfill order_id where it's NULL by matching patient + product to order items
UPDATE hearmed_core.patient_devices pd
SET order_id = sub.order_id
FROM (
    SELECT DISTINCT ON (pd2.id)
           pd2.id AS device_id,
           o.id   AS order_id
    FROM hearmed_core.patient_devices pd2
    JOIN hearmed_core.orders o ON o.patient_id = pd2.patient_id
    JOIN hearmed_core.order_items oi ON oi.order_id = o.id
                                    AND oi.item_id = pd2.product_id
                                    AND oi.item_type = 'product'
    WHERE pd2.order_id IS NULL
      AND o.current_status IN ('Awaiting Fitting', 'Complete', 'Fitted', 'Ordered')
    ORDER BY pd2.id, o.created_at DESC
) sub
WHERE pd.id = sub.device_id;
