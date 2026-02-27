<?php
/**
 * Calendar Settings v3.1 — 8-card layout using hearmed-core.css design system.
 * JS save / live-preview handled by hearmed-calendar-settings.js
 * Styles in assets/css/calendar-settings.css (minimal — only what core doesn't provide)
 */
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Calendar_Settings {

    public function __construct() {
        add_shortcode('hearmed_calendar_settings', [$this, 'render']);
    }

    private function get_saved_settings() {
        $row = HearMed_DB::get_row("SELECT * FROM hearmed_core.calendar_settings LIMIT 1");
        if (!$row) return [];
        $arr = (array) $row;
        // Decode jsonb columns — PostgreSQL returns them with JSON encoding (quoted strings)
        foreach (['working_days', 'enabled_days', 'calendar_order', 'appointment_statuses'] as $jf) {
            if (isset($arr[$jf]) && is_string($arr[$jf])) {
                $decoded = json_decode($arr[$jf], true);
                if ($decoded !== null) {
                    $arr[$jf] = is_array($decoded) ? implode(',', $decoded) : $decoded;
                }
            }
        }
        // Decode status_badge_colours as associative array (keep as array, don't implode)
        if (isset($arr['status_badge_colours']) && is_string($arr['status_badge_colours'])) {
            $decoded = json_decode($arr['status_badge_colours'], true);
            if (is_array($decoded)) {
                $arr['status_badge_colours'] = $decoded;
            }
        }
        return $arr;
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        $saved = $this->get_saved_settings();
        if (isset($saved['time_interval_minutes'])) {
            $saved['time_interval'] = $saved['time_interval_minutes'];
        }

        // Text field helper
        $s = function($key, $default) use ($saved) {
            if (!isset($saved[$key]) || $saved[$key] === '' || $saved[$key] === null) return $default;
            return $saved[$key];
        };

        // Boolean field helper (handles PostgreSQL t/f)
        $cb = function($key, $default = false) use ($saved) {
            if (!isset($saved[$key])) return $default;
            $v = $saved[$key];
            return ($v === true || $v === 't' || $v === '1' || $v === 'on' || $v === 'true' || $v === 1);
        };

        // Load dispensers for Calendar Order card
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
                <a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-back">&larr; Back to Console</a>
                <div class="hm-page-header">
                    <div>
                        <h1 class="hm-page-title">Calendar Settings</h1>
                        <div class="hm-page-subtitle">Adjust your scheduling, display and appearance preferences.</div>
                    </div>
                </div>

                <form id="hm-settings-form" autocomplete="off">
                <div class="hm-settings-grid">

                    <!-- ═══ LEFT COLUMN ═══ -->
                    <div class="hm-settings-col">

                        <!-- Card 1 — Time & View -->
                        <div class="hm-card">
                            <div class="hm-card-hd"><h3 class="hm-card-title">Time &amp; View</h3></div>
                            <div class="hm-card-body">
                                <div class="hm-srow"><span class="hm-slbl">Start time</span><span class="hm-sval"><input id="hs-start" name="start_time" type="time" value="<?php echo esc_attr($s('start_time','09:00')); ?>"></span></div>
                                <div class="hm-srow"><span class="hm-slbl">End time</span><span class="hm-sval"><input id="hs-end" name="end_time" type="time" value="<?php echo esc_attr($s('end_time','18:00')); ?>"></span></div>
                                <div class="hm-srow"><span class="hm-slbl">Time interval</span><span class="hm-sval">
                                    <select id="hs-interval" name="time_interval"><?php foreach ([15=>'15 min',20=>'20 min',30=>'30 min',45=>'45 min',60=>'60 min'] as $v=>$l): ?><option value="<?php echo $v;?>" <?php selected($s('time_interval','30'),$v);?>><?php echo $l;?></option><?php endforeach;?></select>
                                </span></div>
                                <div class="hm-srow"><span class="hm-slbl">Slot height</span><span class="hm-sval">
                                    <select id="hs-slotH" name="slot_height"><?php foreach (['compact'=>'Compact','regular'=>'Regular','large'=>'Large'] as $v=>$l): ?><option value="<?php echo $v;?>" <?php selected($s('slot_height','regular'),$v);?>><?php echo $l;?></option><?php endforeach;?></select>
                                </span></div>
                                <div class="hm-srow"><span class="hm-slbl">Default timeframe</span><span class="hm-sval">
                                    <select id="hs-view" name="default_view"><?php foreach (['day'=>'Day','week'=>'Week'] as $v=>$l): ?><option value="<?php echo $v;?>" <?php selected($s('default_view','week'),$v);?>><?php echo $l;?></option><?php endforeach;?></select>
                                </span></div>
                            </div>
                        </div>

                        <!-- Card 2 — Card Appearance + Live Preview -->
                        <div class="hm-card">
                            <div class="hm-card-hd"><h3 class="hm-card-title">Card Appearance</h3></div>
                            <div class="hm-card-body">
                                <div class="hm-srow"><span class="hm-slbl">Card style</span><span class="hm-sval">
                                    <select id="hs-cardStyle" name="card_style"><?php foreach (['solid'=>'Solid','tinted'=>'Tinted','outline'=>'Outline','minimal'=>'Minimal'] as $v=>$l): ?><option value="<?php echo $v;?>" <?php selected($s('card_style','tinted'),$v);?>><?php echo $l;?></option><?php endforeach;?></select>
                                </span></div>
                                <div class="hm-srow"><span class="hm-slbl">Banner style</span><span class="hm-sval">
                                    <select id="hs-bannerStyle" name="banner_style"><?php foreach (['default'=>'Default','gradient'=>'Gradient','stripe'=>'Striped','none'=>'None'] as $v=>$l): ?><option value="<?php echo $v;?>" <?php selected($s('banner_style','default'),$v);?>><?php echo $l;?></option><?php endforeach;?></select>
                                </span></div>
                                <div class="hm-srow"><span class="hm-slbl">Banner size</span><span class="hm-sval">
                                    <select id="hs-bannerSize" name="banner_size"><?php foreach (['small'=>'Small','default'=>'Default','large'=>'Large'] as $v=>$l): ?><option value="<?php echo $v;?>" <?php selected($s('banner_size','default'),$v);?>><?php echo $l;?></option><?php endforeach;?></select>
                                </span></div>
                                <div class="hm-srow"><span class="hm-slbl">Border colour</span><div class="hm-sval hm-color-pick"><input type="color" id="hs-borderColor" name="border_color" value="<?php echo esc_attr($s('border_color','') ?: '#0BB4C4'); ?>" class="hm-color-inp"><span class="hm-color-hex" data-for="hs-borderColor"><?php echo esc_html($s('border_color','') ?: '#0BB4C4'); ?></span></div></div>
                                <div class="hm-srow"><span class="hm-slbl">Tint opacity</span><span class="hm-sval"><div class="hm-range-wrap"><input type="range" class="hm-range hm-color-inp" id="hs-tintOpacity" name="tint_opacity" min="3" max="40" value="<?php echo intval($s('tint_opacity','12')); ?>"><span class="hm-range-val" id="hm-tint-val"><?php echo intval($s('tint_opacity','12')); ?>%</span></div></span></div>
                                <div class="hm-srow-help">Colour is driven by appointment type. <strong>Solid:</strong> filled, white text. <strong>Tinted:</strong> light wash + accent bar. <strong>Outline:</strong> border only. <strong>Minimal:</strong> left bar only.</div>

                                <!-- Live Preview -->
                                <div class="hm-preview-wrap">
                                    <div class="hm-preview-label">Live Preview</div>
                                    <div class="hm-preview-status-bar">
                                        <button type="button" class="hm-prev-status" data-status="Not Confirmed">Not Confirmed</button>
                                        <button type="button" class="hm-prev-status on" data-status="Confirmed">Confirmed</button>
                                        <button type="button" class="hm-prev-status" data-status="Arrived">Arrived</button>
                                        <button type="button" class="hm-prev-status" data-status="Completed">Completed</button>
                                        <button type="button" class="hm-prev-status" data-status="Cancelled">Cancelled</button>
                                        <button type="button" class="hm-prev-status" data-status="No Show">No Show</button>
                                        <button type="button" class="hm-prev-status" data-status="Rescheduled">Rescheduled</button>
                                    </div>
                                    <div class="hm-preview-card" id="hm-preview-card">
                                        <!-- JS renders preview card here -->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card 3 — Rules & Safety -->
                        <div class="hm-card">
                            <div class="hm-card-hd"><h3 class="hm-card-title">Rules &amp; Safety</h3></div>
                            <div class="hm-card-body">
                                <div class="hm-srow"><span class="hm-slbl">Require cancellation reason</span><label class="hm-toggle"><input type="checkbox" name="require_cancel_reason" value="1" <?php if($cb('require_cancel_reason',true)) echo 'checked';?>><span class="hm-toggle-track"></span></label></div>
                                <div class="hm-srow"><span class="hm-slbl">Hide cancelled appointments</span><label class="hm-toggle"><input type="checkbox" name="hide_cancelled" value="1" <?php if($cb('hide_cancelled',true)) echo 'checked';?>><span class="hm-toggle-track"></span></label></div>
                                <div class="hm-srow"><span class="hm-slbl">Require reschedule note</span><label class="hm-toggle"><input type="checkbox" name="require_reschedule_note" value="1" <?php if($cb('require_reschedule_note')) echo 'checked';?>><span class="hm-toggle-track"></span></label></div>
                                <div class="hm-srow"><span class="hm-slbl">Prevent mismatched location bookings</span><label class="hm-toggle"><input type="checkbox" name="prevent_location_mismatch" value="1" <?php if($cb('prevent_location_mismatch')) echo 'checked';?>><span class="hm-toggle-track"></span></label></div>
                            </div>
                        </div>

                        <!-- Card 4 — Working Days -->
                        <div class="hm-card">
                            <div class="hm-card-hd"><h3 class="hm-card-title">Working Days</h3></div>
                            <div class="hm-card-body">
                                <div class="hm-srow hm-srow--col"><span class="hm-slbl">Enabled days</span>
                                    <div class="hm-day-pills">
                                        <?php foreach ([['1','Mon'],['2','Tue'],['3','Wed'],['4','Thu'],['5','Fri'],['6','Sat'],['0','Sun']] as $d): ?>
                                        <label class="hm-pill<?php echo in_array($d[0],$wd)?' on':'';?>"><input type="checkbox" class="hs-wd" name="working_day[]" value="<?php echo $d[0];?>" <?php if(in_array($d[0],$wd)) echo 'checked';?>><?php echo $d[1];?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <div class="hm-srow"><span class="hm-slbl">Apply clinic colour to working times</span><label class="hm-toggle"><input type="checkbox" name="apply_clinic_colour" value="1" <?php if($cb('apply_clinic_colour')) echo 'checked';?>><span class="hm-toggle-track"></span></label></div>
                            </div>
                        </div>

                        <!-- Card 5 — Calendar Order -->
                        <div class="hm-card">
                            <div class="hm-card-hd"><h3 class="hm-card-title">Calendar Order</h3></div>
                            <div class="hm-card-body">
                                <div class="hm-srow-help" style="margin-bottom:10px">Drag to reorder how dispensers appear on the calendar.</div>
                                <ul class="hm-sort-list" id="hs-sortList">
                                    <?php foreach($dispensers as $dd): ?>
                                    <li class="hm-sort-item" data-id="<?php echo $dd['id'];?>">
                                        <span class="hm-sort-grip">&#x2801;</span>
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
                    <div class="hm-settings-col">

                        <!-- Card 6 — Card Content -->
                        <div class="hm-card">
                            <div class="hm-card-hd"><h3 class="hm-card-title">Card Content</h3></div>
                            <div class="hm-card-body">
                                <div class="hm-srow"><span class="hm-slbl">Show appointment type</span><label class="hm-toggle"><input type="checkbox" name="show_appointment_type" value="1" <?php if($cb('show_appointment_type',true)) echo 'checked';?>><span class="hm-toggle-track"></span></label></div>
                                <div class="hm-srow"><span class="hm-slbl">Show time on card</span><label class="hm-toggle"><input type="checkbox" name="show_time" value="1" <?php if($cb('show_time',true)) echo 'checked';?>><span class="hm-toggle-track"></span></label></div>
                                <div class="hm-srow"><span class="hm-slbl">Show clinic name</span><label class="hm-toggle"><input type="checkbox" name="show_clinic" value="1" <?php if($cb('show_clinic')) echo 'checked';?>><span class="hm-toggle-track"></span></label></div>
                                <div class="hm-srow"><span class="hm-slbl">Show dispenser initials</span><label class="hm-toggle"><input type="checkbox" name="show_dispenser_initials" value="1" <?php if($cb('show_dispenser_initials',true)) echo 'checked';?>><span class="hm-toggle-track"></span></label></div>
                                <div class="hm-srow"><span class="hm-slbl">Show status badge</span><label class="hm-toggle"><input type="checkbox" name="show_status_badge" value="1" <?php if($cb('show_status_badge',true)) echo 'checked';?>><span class="hm-toggle-track"></span></label></div>
                                <div class="hm-srow"><span class="hm-slbl">Show badges (type, initials)</span><label class="hm-toggle"><input type="checkbox" name="show_badges" value="1" <?php if($cb('show_badges',true)) echo 'checked';?>><span class="hm-toggle-track"></span></label></div>
                                <div class="hm-srow"><span class="hm-slbl">Display full name (vs first name only)</span><label class="hm-toggle"><input type="checkbox" name="display_full_name" value="1" <?php if($cb('display_full_name')) echo 'checked';?>><span class="hm-toggle-track"></span></label></div>
                                <div class="hm-srow"><span class="hm-slbl">Display time inline with patient name</span><label class="hm-toggle"><input type="checkbox" name="show_time_inline" value="1" <?php if($cb('show_time_inline')) echo 'checked';?>><span class="hm-toggle-track"></span></label></div>
                                <div class="hm-srow"><span class="hm-slbl">Hide appointment end time</span><label class="hm-toggle"><input type="checkbox" name="hide_end_time" value="1" <?php if($cb('hide_end_time',true)) echo 'checked';?>><span class="hm-toggle-track"></span></label></div>
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
                            <div class="hm-card-hd"><h3 class="hm-card-title">Card Colours</h3></div>
                            <div class="hm-card-body">
                                <div class="hm-srow-help" style="margin-bottom:10px">Text and element colours used on appointment cards. Card fill colour is set per appointment type.</div>
                                <?php
                                $colors = [
                                    ['Card background',  'hs-apptBg',       'appt_bg_color',       '#0BB4C4'],
                                    ['Patient name',     'hs-apptFont',     'appt_font_color',     '#ffffff'],
                                    ['Appt type label',  'hs-apptName',     'appt_name_color',     '#ffffff'],
                                    ['Time text',        'hs-apptTime',     'appt_time_color',     '#38bdf8'],
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
                            <div class="hm-card-hd"><h3 class="hm-card-title">Calendar Theme</h3></div>
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

                        <!-- Card 9 — Status Badge Colours -->
                        <div class="hm-card">
                            <div class="hm-card-hd"><h3 class="hm-card-title">Status Badge Colours</h3></div>
                            <div class="hm-card-body">
                                <div class="hm-srow-help" style="margin-bottom:10px">Customise the badge colours for each appointment status.</div>
                                <?php
                                $badge_defaults = [
                                    'Not Confirmed'=>['bg'=>'#fefce8','color'=>'#854d0e','border'=>'#fde68a'],
                                    'Confirmed'   => ['bg'=>'#eff6ff','color'=>'#1e40af','border'=>'#bfdbfe'],
                                    'Arrived'     => ['bg'=>'#ecfdf5','color'=>'#065f46','border'=>'#a7f3d0'],
                                    'In Progress' => ['bg'=>'#fff7ed','color'=>'#9a3412','border'=>'#fed7aa'],
                                    'Completed'   => ['bg'=>'#f9fafb','color'=>'#6b7280','border'=>'#e5e7eb'],
                                    'No Show'     => ['bg'=>'#fef2f2','color'=>'#991b1b','border'=>'#fecaca'],
                                    'Late'        => ['bg'=>'#fffbeb','color'=>'#92400e','border'=>'#fde68a'],
                                    'Pending'     => ['bg'=>'#f5f3ff','color'=>'#5b21b6','border'=>'#ddd6fe'],
                                    'Cancelled'   => ['bg'=>'#fef2f2','color'=>'#991b1b','border'=>'#fecaca'],
                                    'Rescheduled' => ['bg'=>'#f0f9ff','color'=>'#0c4a6e','border'=>'#bae6fd'],
                                ];
                                $saved_badges = is_array($saved['status_badge_colours'] ?? null) ? $saved['status_badge_colours'] : $badge_defaults;
                                foreach ($badge_defaults as $status => $defs):
                                    $slug = strtolower(str_replace(' ', '_', $status));
                                    $cur = $saved_badges[$status] ?? $defs;
                                ?>
                                <div class="hm-status-badge-row" style="margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid #f1f5f9;">
                                    <div style="font-size:12px;font-weight:700;color:#334155;margin-bottom:6px;"><?php echo esc_html($status); ?></div>
                                    <div style="display:flex;gap:16px;align-items:center;">
                                        <label style="font-size:11px;color:#64748b;display:flex;align-items:center;gap:4px;">Bg
                                            <input type="color" class="hm-color-inp hm-badge-inp" name="sbadge_<?php echo $slug; ?>_bg" value="<?php echo esc_attr($cur['bg'] ?? $defs['bg']); ?>" style="width:28px;height:22px;padding:0;border:1px solid #e2e8f0;border-radius:4px;">
                                        </label>
                                        <label style="font-size:11px;color:#64748b;display:flex;align-items:center;gap:4px;">Text
                                            <input type="color" class="hm-color-inp hm-badge-inp" name="sbadge_<?php echo $slug; ?>_color" value="<?php echo esc_attr($cur['color'] ?? $defs['color']); ?>" style="width:28px;height:22px;padding:0;border:1px solid #e2e8f0;border-radius:4px;">
                                        </label>
                                        <label style="font-size:11px;color:#64748b;display:flex;align-items:center;gap:4px;">Border
                                            <input type="color" class="hm-color-inp hm-badge-inp" name="sbadge_<?php echo $slug; ?>_border" value="<?php echo esc_attr($cur['border'] ?? $defs['border']); ?>" style="width:28px;height:22px;padding:0;border:1px solid #e2e8f0;border-radius:4px;">
                                        </label>
                                        <span class="hm-prev-badge" style="background:<?php echo esc_attr($cur['bg'] ?? $defs['bg']); ?>;color:<?php echo esc_attr($cur['color'] ?? $defs['color']); ?>;border:1px solid <?php echo esc_attr($cur['border'] ?? $defs['border']); ?>;padding:2px 8px;border-radius:9999px;font-size:10px;font-weight:700;"><?php echo esc_html($status); ?></span>
                                    </div>
                                </div>
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