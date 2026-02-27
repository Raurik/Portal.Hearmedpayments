-- ════════════════════════════════════════════════════════════════
-- SEED: Ensure all setting keys used by the plugin exist in DB
-- Run against hearmed_admin schema
-- Safe to run multiple times (ON CONFLICT DO NOTHING)
-- ════════════════════════════════════════════════════════════════

INSERT INTO hearmed_admin.settings (setting_key, setting_value) VALUES
  ('hearmed_qbo_oauth_state', ''),
  ('hm_elementor_meta_repaired_v1', ''),
  ('hm_vat_number', ''),
  ('hm_form_templates', ''),
  ('hm_ai_extraction_mode', 'mock'),
  ('hm_openrouter_api_key', ''),
  ('hm_openrouter_model', 'anthropic/claude-sonnet-4-20250514'),
  ('hm_ai_extraction_enabled', '1'),
  ('hm_ai_max_retries', '2'),
  ('hm_ai_mock_mode', '0')
ON CONFLICT (setting_key) DO NOTHING;
