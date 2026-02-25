-- Migration: Document Templates + Manufacturer Other Description
-- Run against the Railway PostgreSQL database
-- Part of HearMed Portal v5.2
--
-- IMPORTANT: Create a new WordPress page:
--   Title: Document Template Editor
--   Slug:  document-template-editor
--   Content: [hearmed_document_template_editor]
--   (Use same Elementor template as other admin pages)

-- 1. Document Templates table
CREATE TABLE IF NOT EXISTS hearmed_admin.document_templates (
    id              SERIAL PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    category        VARCHAR(50)  DEFAULT 'clinical',
    ai_enabled      BOOLEAN      DEFAULT false,
    password_protect BOOLEAN     DEFAULT true,
    sections_json   JSONB        DEFAULT '[]'::jsonb,
    is_active       BOOLEAN      DEFAULT true,
    sort_order      INTEGER      DEFAULT 0,
    created_at      TIMESTAMP    DEFAULT NOW(),
    updated_at      TIMESTAMP    DEFAULT NOW()
);

-- 2. Add manufacturer_other_desc to manufacturers table
ALTER TABLE hearmed_reference.manufacturers
    ADD COLUMN IF NOT EXISTS manufacturer_other_desc VARCHAR(255) DEFAULT '';
