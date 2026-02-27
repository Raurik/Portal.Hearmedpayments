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

-- ─── 5. Commission rules reference data ─────────────────
CREATE TABLE IF NOT EXISTS hearmed_admin.commission_rules (
    id              SERIAL PRIMARY KEY,
    rule_name       TEXT NOT NULL,
    category        TEXT,
    min_revenue     NUMERIC(10,2) DEFAULT 0,
    max_revenue     NUMERIC(10,2),
    commission_pct  NUMERIC(5,2) NOT NULL DEFAULT 0,
    is_active       BOOLEAN NOT NULL DEFAULT true,
    created_at      TIMESTAMPTZ DEFAULT now(),
    updated_at      TIMESTAMPTZ DEFAULT now()
);

-- Seed default tiers (idempotent)
INSERT INTO hearmed_admin.commission_rules (rule_name, category, min_revenue, max_revenue, commission_pct)
SELECT * FROM (VALUES
    ('Tier 1 – up to €5 000',   'hearing_aids',   0,     5000,  5.00),
    ('Tier 2 – €5 001–€15 000', 'hearing_aids',   5001, 15000, 7.50),
    ('Tier 3 – €15 001+',       'hearing_aids',  15001,  NULL, 10.00),
    ('Accessories flat',         'accessories',      0,   NULL,  3.00)
) AS v(rule_name, category, min_revenue, max_revenue, commission_pct)
WHERE NOT EXISTS (SELECT 1 FROM hearmed_admin.commission_rules LIMIT 1);

-- ─── 6. KPI targets: add monthly revenue metric ─────────
INSERT INTO hearmed_admin.kpi_targets (target_name, target_value, staff_id)
SELECT 'monthly_revenue', 40000, NULL
WHERE NOT EXISTS (
    SELECT 1 FROM hearmed_admin.kpi_targets
    WHERE target_name = 'monthly_revenue' AND staff_id IS NULL
);
