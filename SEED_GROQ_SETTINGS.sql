-- Seed Groq Whisper settings into hearmed_admin.settings
-- Run AFTER SEED_SETTINGS_AI.sql

INSERT INTO hearmed_admin.settings (setting_key, setting_value) VALUES
  ('hm_groq_api_key',          ''),
  ('hm_groq_whisper_model',    'whisper-large-v3-turbo')
ON CONFLICT (setting_key) DO NOTHING;
