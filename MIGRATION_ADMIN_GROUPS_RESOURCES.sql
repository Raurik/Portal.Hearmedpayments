-- ============================================================
-- Migration: Add Groups, Resources, and GDPR Settings Tables
-- Run on Railway PostgreSQL
-- ============================================================

CREATE TABLE IF NOT EXISTS hearmed_reference.staff_groups (
    id          bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    group_name  character varying(150) NOT NULL,
    description text,
    is_active   boolean DEFAULT true NOT NULL,
    created_at  timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at  timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS hearmed_reference.staff_group_members (
    id         bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    group_id   bigint REFERENCES hearmed_reference.staff_groups(id) ON DELETE CASCADE,
    staff_id   bigint REFERENCES hearmed_reference.staff(id) ON DELETE CASCADE,
    created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS hearmed_reference.resources (
    id          bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    title       character varying(200) NOT NULL,
    category    character varying(100),
    url         character varying(500),
    description text,
    sort_order  integer DEFAULT 0 NOT NULL,
    is_active   boolean DEFAULT true NOT NULL,
    created_at  timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at  timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS hearmed_admin.gdpr_settings (
    id                           bigint PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
    hm_privacy_policy_url        character varying(255),
    hm_retention_patient_years   integer,
    hm_retention_financial_years integer,
    hm_retention_sms_years       integer,
    hm_data_processors           text,
    created_at                   timestamp without time zone DEFAULT CURRENT_TIMESTAMP,
    updated_at                   timestamp without time zone DEFAULT CURRENT_TIMESTAMP
);

-- Ensure HearMed Range has common columns
ALTER TABLE hearmed_reference.hearmed_range
    ADD COLUMN IF NOT EXISTS is_active boolean DEFAULT true NOT NULL;
ALTER TABLE hearmed_reference.hearmed_range
    ADD COLUMN IF NOT EXISTS created_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE hearmed_reference.hearmed_range
    ADD COLUMN IF NOT EXISTS updated_at timestamp without time zone DEFAULT CURRENT_TIMESTAMP;

-- ============================================================
-- Indexes
-- ============================================================
CREATE INDEX IF NOT EXISTS idx_staff_groups_active ON hearmed_reference.staff_groups(is_active);
CREATE INDEX IF NOT EXISTS idx_resources_active ON hearmed_reference.resources(is_active);
CREATE INDEX IF NOT EXISTS idx_resources_category ON hearmed_reference.resources(category);
