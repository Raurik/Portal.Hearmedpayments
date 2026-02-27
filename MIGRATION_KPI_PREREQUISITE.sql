-- ════════════════════════════════════════════════════════════
-- MIGRATION: KPI Prerequisites — Reporting Categories,
--            Commission Setup, Monthly Revenue Target
-- Run once against the portal database.
-- ════════════════════════════════════════════════════════════

-- ─── 1. Services: add reporting columns ─────────────────
ALTER TABLE hearmed_reference.services
  ADD COLUMN IF NOT EXISTS is_reportable    BOOLEAN NOT NULL DEFAULT false,
  ADD COLUMN IF NOT EXISTS report_category  TEXT;

COMMENT ON COLUMN hearmed_reference.services.is_reportable   IS 'Include this service type in KPI / revenue reports';
COMMENT ON COLUMN hearmed_reference.services.report_category  IS 'high-level bucket: hearing_aids | accessories | wax_removal | diagnostic | aftercare | other';

-- ─── 2. Outcome templates: add reporting columns ────────
ALTER TABLE hearmed_core.outcome_templates
  ADD COLUMN IF NOT EXISTS is_reportable    BOOLEAN NOT NULL DEFAULT false,
  ADD COLUMN IF NOT EXISTS report_outcome   TEXT;

COMMENT ON COLUMN hearmed_core.outcome_templates.is_reportable  IS 'Include in KPI outcome reports';
COMMENT ON COLUMN hearmed_core.outcome_templates.report_outcome IS 'sale | no_sale | partial | return | other';

-- ─── 3. Staff: base salary for commission calc ──────────
ALTER TABLE hearmed_reference.staff
  ADD COLUMN IF NOT EXISTS base_salary NUMERIC(10,2);

-- ─── 4. Staff auth: commission verification PIN ─────────
ALTER TABLE hearmed_reference.staff_auth
  ADD COLUMN IF NOT EXISTS commission_pin TEXT;

-- ─── 5. Commission rules — add columns to existing table ─
--    Existing cols: id, role_type, rule_type, bracket_from, bracket_to,
--                   rate_pct, applies_to, clinic_scope, is_active, created_at, updated_at
--    We add: rule_name (friendly label), category (alias for applies_to grouping)
ALTER TABLE hearmed_admin.commission_rules
  ADD COLUMN IF NOT EXISTS rule_name       TEXT,
  ADD COLUMN IF NOT EXISTS category        TEXT,
  ADD COLUMN IF NOT EXISTS min_revenue     NUMERIC(10,2) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS max_revenue     NUMERIC(10,2),
  ADD COLUMN IF NOT EXISTS commission_pct  NUMERIC(5,2) DEFAULT 0;

-- Seed default tiers using BOTH legacy and new columns (idempotent)
INSERT INTO hearmed_admin.commission_rules
    (rule_name, role_type, rule_type, bracket_from, bracket_to, rate_pct, applies_to, category, min_revenue, max_revenue, commission_pct)
SELECT v.*
FROM (VALUES
    ('Tier 1 – up to €5 000',   'dispenser', 'tiered',   0.00,  5000.00,  5.00, 'hearing_aids', 'hearing_aids',   0.00,  5000.00,  5.00),
    ('Tier 2 – €5 001–€15 000', 'dispenser', 'tiered',5001.00, 15000.00,  7.50, 'hearing_aids', 'hearing_aids',5001.00, 15000.00,  7.50),
    ('Tier 3 – €15 001+',       'dispenser', 'tiered',15001.00,    NULL, 10.00, 'hearing_aids', 'hearing_aids',15001.00,     NULL, 10.00),
    ('Accessories flat',         'dispenser', 'flat',      0.00,    NULL,  3.00, 'accessories',  'accessories',    0.00,     NULL,  3.00)
) AS v(rule_name, role_type, rule_type, bracket_from, bracket_to, rate_pct, applies_to, category, min_revenue, max_revenue, commission_pct)
WHERE NOT EXISTS (SELECT 1 FROM hearmed_admin.commission_rules LIMIT 1);

-- ─── 6. KPI targets: add monthly revenue metric ─────────
INSERT INTO hearmed_admin.kpi_targets (target_name, target_value, staff_id)
SELECT 'monthly_revenue', 40000, NULL
WHERE NOT EXISTS (
    SELECT 1 FROM hearmed_admin.kpi_targets
    WHERE target_name = 'monthly_revenue' AND staff_id IS NULL
);
