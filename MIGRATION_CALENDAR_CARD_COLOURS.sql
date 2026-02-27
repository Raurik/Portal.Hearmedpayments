-- ============================================================
-- MIGRATION: Move card appearance controls to calendar_settings
-- Run ONCE against the hearmed_core.calendar_settings table.
-- Adds: tint_opacity, border_color, appt_name_color,
--        appt_time_color, status_badge_colours (jsonb)
-- ============================================================

-- Tint opacity (integer 3-40, default 12) — controls tinted card wash
ALTER TABLE hearmed_core.calendar_settings
    ADD COLUMN IF NOT EXISTS tint_opacity INTEGER DEFAULT 12;

-- Border colour — used for outline/tinted card border
ALTER TABLE hearmed_core.calendar_settings
    ADD COLUMN IF NOT EXISTS border_color VARCHAR(20) DEFAULT '';

-- Appointment type label colour (the service name text on the card)
ALTER TABLE hearmed_core.calendar_settings
    ADD COLUMN IF NOT EXISTS appt_name_color VARCHAR(20) DEFAULT '#ffffff';

-- Time text colour
ALTER TABLE hearmed_core.calendar_settings
    ADD COLUMN IF NOT EXISTS appt_time_color VARCHAR(20) DEFAULT '#38bdf8';

-- Per-status badge colours — jsonb containing:
-- { "Confirmed": {"bg":"#eff6ff","color":"#1e40af","border":"#bfdbfe"},
--   "Arrived":   {"bg":"#ecfdf5","color":"#065f46","border":"#a7f3d0"}, ... }
ALTER TABLE hearmed_core.calendar_settings
    ADD COLUMN IF NOT EXISTS status_badge_colours JSONB DEFAULT '{
        "Confirmed":   {"bg":"#eff6ff","color":"#1e40af","border":"#bfdbfe"},
        "Arrived":     {"bg":"#ecfdf5","color":"#065f46","border":"#a7f3d0"},
        "In Progress": {"bg":"#fff7ed","color":"#9a3412","border":"#fed7aa"},
        "Completed":   {"bg":"#f9fafb","color":"#6b7280","border":"#e5e7eb"},
        "No Show":     {"bg":"#fef2f2","color":"#991b1b","border":"#fecaca"},
        "Late":        {"bg":"#fffbeb","color":"#92400e","border":"#fde68a"},
        "Pending":     {"bg":"#f5f3ff","color":"#5b21b6","border":"#ddd6fe"},
        "Cancelled":   {"bg":"#fef2f2","color":"#991b1b","border":"#fecaca"}
    }'::jsonb;

-- Done
