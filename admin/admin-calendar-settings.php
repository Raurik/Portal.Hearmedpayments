<?php

// Calendar Settings admin shortcode wrapper
// Registers the shortcode used by the portal and renders the hm-app wrapper
// so the client-side calendar JS can initialise the Settings view.
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Calendar_Settings {

    public function __construct() {
        add_shortcode('hearmed_calendar_settings', [$this, 'render']);
    }

    private function get_saved_settings() {
        $row = HearMed_DB::get_row("SELECT * FROM hearmed_core.calendar_settings LIMIT 1");
        if (!$row) return [];
        return (array) $row;
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $saved = $this->get_saved_settings();
        // Map DB column name to form field name where they differ
        if (isset($saved['time_interval_minutes'])) {
            $saved['time_interval'] = $saved['time_interval_minutes'];
        }
        $s = function($key, $default) use ($saved) {
            if (!isset($saved[$key]) || $saved[$key] === '' || $saved[$key] === null) return $default;
            return $saved[$key];
        };
        // Helper for checkboxes: DB stores boolean (t/f) or 'on'/''
        $cb = function($key, $default = false) use ($saved) {
            if (!isset($saved[$key])) return $default;
            $v = $saved[$key];
            return ($v === true || $v === 't' || $v === '1' || $v === 'on' || $v === 'true');
        };

        ob_start();
        ?>
        <div id="hm-app" class="hm-calendar" data-module="calendar" data-view="settings">
            <div class="hm-page">
                <div style="margin-bottom:16px;"><a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-btn">&larr; Back</a></div>
                <div class="hm-page-header">
                    <h1 class="hm-page-title">Calendar Settings</h1>
                    <div class="hm-page-subtitle">Adjust your scheduling and display preferences.</div>
                </div>
                <form id="hm-settings-form" autocomplete="off">
                <div class="hm-card-grid hm-card-grid--3">
                    <!-- Block 1: Time & View -->
                    <div class="hm-card">
                        <div class="hm-card-hd">Time &amp; View</div>
                        <div class="hm-card-body">
                            <div class="hm-srow"><span class="hm-slbl">Start time</span><span class="hm-sval"><input id="hs-start" name="start_time" class="hm-inp" type="time" value="<?php echo esc_attr($s('start_time', '09:00')); ?>"></span></div>
                            <div class="hm-srow"><span class="hm-slbl">End time</span><span class="hm-sval"><input id="hs-end" name="end_time" class="hm-inp" type="time" value="<?php echo esc_attr($s('end_time', '18:00')); ?>"></span></div>
                            <div class="hm-srow"><span class="hm-slbl">Time interval</span><span class="hm-sval"><select id="hs-interval" name="time_interval" class="hm-dd"><?php foreach ([15=>'15 minutes',20=>'20 minutes',30=>'30 minutes',45=>'45 minutes',60=>'60 minutes'] as $v=>$l): ?><option value="<?php echo $v; ?>" <?php selected($s('time_interval','30'), $v); ?>><?php echo $l; ?></option><?php endforeach; ?></select></span></div>
                            <div class="hm-srow"><span class="hm-slbl">Slot height</span><span class="hm-sval"><select id="hs-slotH" name="slot_height" class="hm-dd"><?php foreach (['compact'=>'Compact','regular'=>'Regular','large'=>'Large'] as $v=>$l): ?><option value="<?php echo $v; ?>" <?php selected($s('slot_height','regular'), $v); ?>><?php echo $l; ?></option><?php endforeach; ?></select></span></div>
                            <div class="hm-srow"><span class="hm-slbl">Default timeframe</span><span class="hm-sval"><select id="hs-view" name="default_view" class="hm-dd"><?php foreach (['day'=>'Day','week'=>'Week'] as $v=>$l): ?><option value="<?php echo $v; ?>" <?php selected($s('default_view','week'), $v); ?>><?php echo $l; ?></option><?php endforeach; ?></select></span></div>
                        </div>
                    </div>
                    <!-- Block 2: Display Preferences (now includes color pickers) -->
                    <div class="hm-card">
                        <div class="hm-card-hd">Display Preferences</div>
                        <div class="hm-card-body">
                            <div class="hm-srow">
                                <label class="hm-day-check">
                                    <input id="hs-timeInline" name="show_time_inline" type="checkbox" <?php if ($cb('show_time_inline')) echo 'checked'; ?>>
                                    <span class="hm-check"></span>
                                    Display time inline with patient name
                                </label>
                            </div>
                            <div class="hm-srow">
                                <label class="hm-day-check">
                                    <input id="hs-hideEnd" name="hide_end_time" type="checkbox" <?php if ($cb('hide_end_time', true)) echo 'checked'; ?>>
                                    <span class="hm-check"></span>
                                    Hide appointment end time
                                </label>
                            </div>
                            <div class="hm-srow">
                                <span class="hm-slbl">Outcome style</span>
                                <span class="hm-sval">
                                    <label>
                                        <input type="radio" name="outcome_style" value="default" <?php checked($s('outcome_style', 'default'), 'default'); ?>>
                                        <span class="hm-check"></span>
                                        Default
                                    </label>
                                    <label>
                                        <input type="radio" name="outcome_style" value="small" <?php checked($s('outcome_style', 'default'), 'small'); ?>>
                                        <span class="hm-check"></span>
                                        Small
                                    </label>
                                </span>
                            </div>
                            <div class="hm-srow">
                                <label class="hm-day-check">
                                    <input id="hs-fullName" name="display_full_name" type="checkbox" <?php if ($cb('display_full_name')) echo 'checked'; ?>>
                                    <span class="hm-check"></span>
                                    Display full resource name
                                </label>
                            </div>
                            <div class="hm-srow hm-color-row">
                                <span class="hm-slbl">Appointment Colors</span>
                                <span class="hm-sval hm-color-pickers">
                                    <div class="hm-color-label-group">
                                        <label class="hm-color-label">BG<br><input type="color" id="hs-appt-bg" name="appt_bg_color" value="<?php echo esc_attr($s('appt_bg_color','#0BB4C4')); ?>" class="hm-color-box"></label>
                                        <label class="hm-color-label">Font<br><input type="color" id="hs-appt-font" name="appt_font_color" value="<?php echo esc_attr($s('appt_font_color','#ffffff')); ?>" class="hm-color-box"></label>
                                        <label class="hm-color-label">Badge<br><input type="color" id="hs-appt-badge" name="appt_badge_color" value="<?php echo esc_attr($s('appt_badge_color','#3b82f6')); ?>" class="hm-color-box"></label>
                                        <label class="hm-color-label">Badge Font<br><input type="color" id="hs-appt-badge-font" name="appt_badge_font_color" value="<?php echo esc_attr($s('appt_badge_font_color','#ffffff')); ?>" class="hm-color-box"></label>
                                        <label class="hm-color-label">Meta<br><input type="color" id="hs-appt-meta" name="appt_meta_color" value="<?php echo esc_attr($s('appt_meta_color','#38bdf8')); ?>" class="hm-color-box"></label>
                                    </div>
                                </span>
                            </div>
                        </div>
                    </div>
                    <!-- Block 3: Appointment Preview -->
                    <div class="hm-card">
                        <div class="hm-card-hd">Display Preview</div>
                        <div class="hm-appt-preview-wrap">
                        <div class="hm-appt-preview-card" id="hs-preview-card">
                            <div class="hm-appt-body">
                                <div class="hm-appt-name" id="hs-preview-name">Joe</div>
                                <div style="margin:2px 0" id="hs-preview-badges"><span class="hm-badge hm-badge-c">C</span> <span class="hm-badge hm-badge-r">R</span> <span class="hm-badge hm-badge-v">VM</span></div>
                                <div class="hm-appt-time" id="hs-preview-time">09:00</div>
                                <div class="hm-appt-meta" id="hs-preview-meta">Follow up · Cosgrove's Pharmacy</div>
                            </div>
                        </div>
                        </div>
                    </div>
                </div>
                <div style="text-align:right;margin-top:18px;">
                    <button type="button" class="hm-btn hm-btn--primary" id="hm-settings-save">Save Settings</button>
                </div>
                </form>
            </div>
        </div>
            <?php
            return ob_get_clean();
    }
}

new HearMed_Admin_Calendar_Settings();
