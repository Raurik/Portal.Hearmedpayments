<?php
/**
 * Calendar Settings v3.1 — 8-card layout rendered server-side.
 * JS save / live-preview handled by hearmed-calendar-settings.js
 * Styles in assets/css/calendar-settings.css
 */
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
        if (isset($saved['time_interval_minutes'])) {
            $saved['time_interval'] = $saved['time_interval_minutes'];
        }

        $s = function($key, $default) use ($saved) {
            if (!isset($saved[$key]) || $saved[$key] === '' || $saved[$key] === null) return $default;
            return $saved[$key];
        };

        $cb = function($key, $default = false) use ($saved) {
            if (!isset($saved[$key])) return $default;
            $v = $saved[$key];
            return ($v === true || $v === 't' || $v === '1' || $v === 'on' || $v === 'true' || $v === 1);
        };

        // Load dispensers for the Calendar Order card
        $dispensers = [];
        try {
            $rows = HearMed_DB::get_results(
                "SELECT s.id, s.first_name, s.last_name, s.role
                 FROM hearmed_reference.staff s
                 WHERE s.is_active = true AND LOWER(s.role) IN ('dispenser','audiologist','c_level','hm_clevel')
                 ORDER BY s.first_name, s.last_name"
            );
            foreach ($rows as $r) {
                $dispensers[] = [
                    'id' => (int)$r->id,
                    'name' => trim($r->first_name . ' ' . $r->last_name),
                    'initials' => strtoupper(substr($r->first_name,0,1) . substr($r->last_name,0,1)),
                    'role' => $r->role ?: 'Dispenser',
                ];
            }
        } catch(Throwable $e) { /* silently ignore */ }

        $wd = array_map('trim', explode(',', $s('working_days', '1,2,3,4,5')));

        ob_start();
        ?>
        <div id="hm-app" class="hm-calendar" data-module="calendar" data-view="settings">
            <div class="hm-page">
                <a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-back">&larr; Back</a>
                <div class="hm-page-header">
                    <h1 class="hm-page-title">Calendar Settings</h1>
                    <div class="hm-page-subtitle">Adjust your scheduling, display and appearance preferences.</div>
                </div>

                <form id="hm-settings-form" autocomplete="off">
                <div class="hm-settings-grid">

                    <!-- ═══ LEFT COLUMN ═══ -->
                    <div class="hm-settings-left">

                        <!-- Card 1 — Time & View -->
                        <div class="hm-card">
                            <div class="hm-card-hd"><span class="hm-card-hd-icon">🕐</span><h3>Time &amp; View</h3></div>
                            <div class="hm-card-body">
                                <div class="hm-srow"><span class="hm-slbl">Start time</span><span class="hm-sval"><input id="hs-start" name="start_time" class="hm-inp" type="time" value="<?php echo esc_attr($s('start_time','09:00')); ?>"></span></div>
                                <div class="hm-srow"><span class="hm-slbl">End time</span><span class="hm-sval"><input id="hs-end" name="end_time" class="hm-inp" type="time" value="<?php echo esc_attr($s('end_time','18:00')); ?>"></span></div>
                                <div class="hm-srow"><span class="hm-slbl">Time interval</span><span class="hm-sval">
                                    <select id="hs-interval" name="time_interval" class="hm-dd"><?php foreach ([15=>'15 min',20=>'20 min',30=>'30 min',45=>'45 min',60=>'60 min'] as $v=>$l): ?><option value="<?php echo $v;?>" <?php selected($s('time_interval','30'),$v);?>><?php echo $l;?></option><?php endforeach;?></select>
                                </span></div>
                                <div class="hm-srow"><span class="hm-slbl">Slot height</span><span class="hm-sval">
                                    <select id="hs-slotH" name="slot_height" class="hm-dd"><?php foreach (['compact'=>'Compact','regular'=>'Regular','large'=>'Large'] as $v=>$l): ?><option value="<?php echo $v;?>" <?php selected($s('slot_height','regular'),$v);?>><?php echo $l;?></option><?php endforeach;?></select>
                                </span></div>
                                <div class="hm-srow"><span class="hm-slbl">Default timeframe</span><span class="hm-sval">
                                    <select id="hs-view" name="default_view" class="hm-dd"><?php foreach (['day'=>'Day','week'=>'Week'] as $v=>$l): ?><option value="<?php echo $v;?>" <?php selected($s('default_view','week'),$v);?>><?php echo $l;?></option><?php endforeach;?></select>
                                </span></div>
                            </div>
                        </div>

                        <!-- Card 2 — Card Appearance -->
                        <div class="hm-card">
                            <div class="hm-card-hd"><span class="hm-card-hd-icon">🎨</span><h3>Card Appearance</h3></div>
                            <div class="hm-card-body">
                                <div class="hm-srow"><span class="hm-slbl">Card style</span><span class="hm-sval">
                                    <select id="hs-cardStyle" name="card_style" class="hm-dd"><?php foreach (['solid'=>'Solid','tinted'=>'Tinted','outline'=>'Outline','minimal'=>'Minimal'] as $v=>$l): ?><option value="<?php echo $v;?>" <?php selected($s('card_style','tinted'),$v);?>><?php echo $l;?></option><?php endforeach;?></select>
                                </span></div>
                                <div class="hm-srow"><span class="hm-slbl">Colour source</span><span class="hm-sval">
                                    <select id="hs-colorSource" name="color_source" class="hm-dd"><?php foreach (['appointment_type'=>'Appointment Type','clinic'=>'Clinic','dispenser'=>'Dispenser','global'=>'Global (single colour)'] as $v=>$l): ?><option value="<?php echo $v;?>" <?php selected($s('color_source','appointment_type'),$v);?>><?php echo $l;?></option><?php endforeach;?></select>
                                </span></div>
                                <div class="hm-srow-help"><strong>Solid:</strong> filled colour, white text. <strong>Tinted:</strong> light colour wash + accent bar. <strong>Outline:</strong> border only. <strong>Minimal:</strong> left bar only.</div>
                            </div>
                        </div>

                        <!-- Card 3 — Rules & Safety -->
                        <div class="hm-card">
                            <div class="hm-card-hd"><span class="hm-card-hd-icon">🛡</span><h3>Rules &amp; Safety</h3></div>
                            <div class="hm-card-body">
                                <div class="hm-srow"><span class="hm-slbl">Require cancellation reason</span><label class="hm-tog"><input type="checkbox" id="hs-cancelReason" name="require_cancel_reason" value="1" <?php if($cb('require_cancel_reason',true)) echo 'checked';?>><span class="hm-tog-track"></span><span class="hm-tog-thumb"></span></label></div>
                                <div class="hm-srow"><span class="hm-slbl">Hide cancelled appointments</span><label class="hm-tog"><input type="checkbox" id="hs-hideCancelled" name="hide_cancelled" value="1" <?php if($cb('hide_cancelled',true)) echo 'checked';?>><span class="hm-tog-track"></span><span class="hm-tog-thumb"></span></label></div>
                                <div class="hm-srow"><span class="hm-slbl">Require reschedule note</span><label class="hm-tog"><input type="checkbox" id="hs-reschedNote" name="require_reschedule_note" value="1" <?php if($cb('require_reschedule_note')) echo 'checked';?>><span class="hm-tog-track"></span><span class="hm-tog-thumb"></span></label></div>
                                <div class="hm-srow"><span class="hm-slbl">Prevent mismatched location bookings</span><label class="hm-tog"><input type="checkbox" id="hs-locMismatch" name="prevent_location_mismatch" value="1" <?php if($cb('prevent_location_mismatch')) echo 'checked';?>><span class="hm-tog-track"></span><span class="hm-tog-thumb"></span></label></div>
                            </div>
                        </div>

                        <!-- Card 4 — Working Days -->
                        <div class="hm-card">
                            <div class="hm-card-hd"><span class="hm-card-hd-icon">📅</span><h3>Working Days</h3></div>
                            <div class="hm-card-body">
                                <div class="hm-srow hm-srow--col"><span class="hm-slbl">Enabled days</span>
                                    <div class="hm-day-pills">
                                        <?php foreach ([['1','Mon'],['2','Tue'],['3','Wed'],['4','Thu'],['5','Fri'],['6','Sat'],['0','Sun']] as $d): ?>
                                        <label class="hm-pill<?php echo in_array($d[0],$wd)?' on':'';?>"><input type="checkbox" class="hs-wd" name="working_day[]" value="<?php echo $d[0];?>" <?php if(in_array($d[0],$wd)) echo 'checked';?>><?php echo $d[1];?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="hm-srow"><span class="hm-slbl">Apply clinic colour to working times</span><label class="hm-tog"><input type="checkbox" id="hs-clinicColour" name="apply_clinic_colour" value="1" <?php if($cb('apply_clinic_colour')) echo 'checked';?>><span class="hm-tog-track"></span><span class="hm-tog-thumb"></span></label></div>
                            </div>
                        </div>

                        <!-- Card 5 — Calendar Order -->
                        <div class="hm-card">
                            <div class="hm-card-hd"><span class="hm-card-hd-icon">⠿</span><h3>Calendar Order</h3></div>
                            <div class="hm-card-body">
                                <div class="hm-srow-help" style="margin-bottom:10px">Drag to reorder how dispensers appear on the calendar.</div>
                                <ul class="hm-sort-list" id="hs-sortList">
                                    <?php foreach($dispensers as $dd): ?>
                                    <li class="hm-sort-item" data-id="<?php echo $dd['id'];?>">
                                        <span class="hm-sort-grip">⠿</span>
                                        <span class="hm-sort-avatar"><?php echo esc_html($dd['initials']);?></span>
                                        <span class="hm-sort-info">
                                            <span class="hm-sort-name"><?php echo esc_html($dd['name']);?></span>
                                            <span class="hm-sort-role"><?php echo esc_html($dd['initials'] . ' · ' . $dd['role']);?></span>
                                        </span>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>

                    </div><!-- end left -->

                    <!-- ═══ RIGHT COLUMN ═══ -->
                    <div class="hm-settings-right">

                        <!-- Card 6 — Card Content -->
                        <div class="hm-card">
                            <div class="hm-card-hd"><span class="hm-card-hd-icon">👁</span><h3>Card Content</h3></div>
                            <div class="hm-card-body">
                                <div class="hm-srow"><span class="hm-slbl">Show appointment type</span><label class="hm-tog"><input type="checkbox" id="hs-showApptType" name="show_appointment_type" value="1" <?php if($cb('show_appointment_type',true)) echo 'checked';?>><span class="hm-tog-track"></span><span class="hm-tog-thumb"></span></label></div>
                                <div class="hm-srow"><span class="hm-slbl">Show time on card</span><label class="hm-tog"><input type="checkbox" id="hs-showTime" name="show_time" value="1" <?php if($cb('show_time',true)) echo 'checked';?>><span class="hm-tog-track"></span><span class="hm-tog-thumb"></span></label></div>
                                <div class="hm-srow"><span class="hm-slbl">Show clinic name</span><label class="hm-tog"><input type="checkbox" id="hs-showClinic" name="show_clinic" value="1" <?php if($cb('show_clinic')) echo 'checked';?>><span class="hm-tog-track"></span><span class="hm-tog-thumb"></span></label></div>
                                <div class="hm-srow"><span class="hm-slbl">Show dispenser initials</span><label class="hm-tog"><input type="checkbox" id="hs-showDispIni" name="show_dispenser_initials" value="1" <?php if($cb('show_dispenser_initials',true)) echo 'checked';?>><span class="hm-tog-track"></span><span class="hm-tog-thumb"></span></label></div>
                                <div class="hm-srow"><span class="hm-slbl">Show status badge</span><label class="hm-tog"><input type="checkbox" id="hs-showStatusBadge" name="show_status_badge" value="1" <?php if($cb('show_status_badge',true)) echo 'checked';?>><span class="hm-tog-track"></span><span class="hm-tog-thumb"></span></label></div>
                                <div class="hm-srow"><span class="hm-slbl">Display full name (vs first name only)</span><label class="hm-tog"><input type="checkbox" id="hs-fullName" name="display_full_name" value="1" <?php if($cb('display_full_name')) echo 'checked';?>><span class="hm-tog-track"></span><span class="hm-tog-thumb"></span></label></div>
                                <div class="hm-srow"><span class="hm-slbl">Display time inline with patient name</span><label class="hm-tog"><input type="checkbox" id="hs-timeInline" name="show_time_inline" value="1" <?php if($cb('show_time_inline')) echo 'checked';?>><span class="hm-tog-track"></span><span class="hm-tog-thumb"></span></label></div>
                                <div class="hm-srow"><span class="hm-slbl">Hide appointment end time</span><label class="hm-tog"><input type="checkbox" id="hs-hideEnd" name="hide_end_time" value="1" <?php if($cb('hide_end_time',true)) echo 'checked';?>><span class="hm-tog-track"></span><span class="hm-tog-thumb"></span></label></div>
                                <div class="hm-srow hm-srow--col"><span class="hm-slbl">Outcome style</span>
                                    <div class="hm-radio-pills">
                                        <?php foreach (['default'=>'Default','small'=>'Small','tag'=>'Tag','popover'=>'Popover'] as $v=>$l): ?>
                                        <label class="hm-pill<?php echo $s('outcome_style','default')===$v?' on':'';?>"><input type="radio" name="outcome_style" value="<?php echo $v;?>" <?php checked($s('outcome_style','default'),$v);?>><?php echo $l;?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card 7 — Card Colours -->
                        <div class="hm-card">
                            <div class="hm-card-hd"><span class="hm-card-hd-icon">🎭</span><h3>Card Colours</h3></div>
                            <div class="hm-card-body">
                                <div class="hm-srow-help" style="margin-bottom:10px">These apply when colour source is <strong>Global</strong>, or as fallback colours.</div>
                                <?php
                                $colors = [
                                    ['Card background',  'hs-apptBg',       'appt_bg_color',       '#0BB4C4'],
                                    ['Patient name',     'hs-apptFont',     'appt_font_color',     '#ffffff'],
                                    ['Badge colour',     'hs-apptBadge',    'appt_badge_color',    '#3b82f6'],
                                    ['Badge text',       'hs-apptBadgeFont','appt_badge_font_color','#ffffff'],
                                    ['Meta text',        'hs-apptMeta',     'appt_meta_color',     '#38bdf8'],
                                ];
                                foreach($colors as $c): $cv = $s($c[2],$c[3]); ?>
                                <div class="hm-srow"><span class="hm-slbl"><?php echo $c[0];?></span><div class="hm-sval hm-color-pick"><input type="color" id="<?php echo $c[1];?>" name="<?php echo $c[2];?>" value="<?php echo esc_attr($cv);?>" class="hm-color-inp"><span class="hm-color-hex" data-for="<?php echo $c[1];?>"><?php echo esc_html($cv);?></span></div></div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Card 8 — Calendar Theme -->
                        <div class="hm-card">
                            <div class="hm-card-hd"><span class="hm-card-hd-icon">🖌</span><h3>Calendar Theme</h3></div>
                            <div class="hm-card-body">
                                <?php
                                $theme = [
                                    ['Time indicator',      'hs-indicator', 'indicator_color',       '#0BB4C4'],
                                    ['Today highlight',     'hs-todayHl',   'today_highlight_color', '#f0fafb'],
                                    ['Grid lines',          'hs-gridLine',  'grid_line_color',       '#e2e8f0'],
                                    ['Calendar background', 'hs-calBg',     'cal_bg_color',          '#ffffff'],
                                ];
                                foreach($theme as $t): $tv = $s($t[2],$t[3]); ?>
                                <div class="hm-srow"><span class="hm-slbl"><?php echo $t[0];?></span><div class="hm-sval hm-color-pick"><input type="color" id="<?php echo $t[1];?>" name="<?php echo $t[2];?>" value="<?php echo esc_attr($tv);?>" class="hm-color-inp"><span class="hm-color-hex" data-for="<?php echo $t[1];?>"><?php echo esc_html($tv);?></span></div></div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Save button -->
                        <div class="hm-settings-save-wrap">
                            <button type="button" class="hm-btn hm-btn--primary" id="hm-settings-save">Save Settings</button>
                        </div>

                    </div><!-- end right -->

                </div><!-- end grid -->
                </form>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

new HearMed_Admin_Calendar_Settings();
