-- Migration: Add status_card_styles JSONB column to calendar_settings
-- Stores per-status card overlay/text styles for Cancelled, No Show, Rescheduled

ALTER TABLE hearmed_core.calendar_settings
  ADD COLUMN IF NOT EXISTS status_card_styles jsonb DEFAULT '{}'::jsonb;

-- Seed default values
UPDATE hearmed_core.calendar_settings
SET status_card_styles = '{
  "Cancelled": {
    "pattern": "striped",
    "overlayColor": "#ef4444",
    "overlayOpacity": 10,
    "label": "CANCELLED",
    "labelColor": "#7f1d1d",
    "labelSize": 8,
    "contentOpacity": 35,
    "halfWidth": true
  },
  "No Show": {
    "pattern": "striped",
    "overlayColor": "#f59e0b",
    "overlayOpacity": 8,
    "label": "",
    "labelColor": "#92400e",
    "labelSize": 8,
    "contentOpacity": 35,
    "halfWidth": false
  },
  "Rescheduled": {
    "pattern": "striped",
    "overlayColor": "#0e7490",
    "overlayOpacity": 10,
    "label": "Rescheduled",
    "labelColor": "#155e75",
    "labelSize": 8,
    "contentOpacity": 35,
    "halfWidth": true
  }
}'::jsonb
WHERE status_card_styles IS NULL OR status_card_styles = '{}'::jsonb;
