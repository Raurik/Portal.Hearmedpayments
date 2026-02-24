<?php

// Calendar Settings admin shortcode wrapper
// Registers the shortcode used by the portal and renders the hm-app wrapper
// so the client-side calendar JS can initialise the Settings view.
if (!defined('ABSPATH')) exit;

class HearMed_Admin_Calendar_Settings {

    public function __construct() {
        add_shortcode('hearmed_calendar_settings', [$this, 'render']);
    }

    public function render() {
        if (!is_user_logged_in()) return '<p>Please log in.</p>';

        // Show debug output to admins or when ?hm_debug=1
        $show_debug = ( current_user_can( 'manage_options' ) || ( isset( $_GET['hm_debug'] ) && current_user_can( 'edit_posts' ) ) );

        ob_start();
        ?>
        <div id="hm-app" class="hm-calendar" data-module="calendar" data-view="settings">
            <div class="hm-page">
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
                            <div class="hm-srow"><span class="hm-slbl">Start time</span><span class="hm-sval"><input id="hs-start" name="start_time" class="hm-inp" type="time" value="09:00"></span></div>
                            <div class="hm-srow"><span class="hm-slbl">End time</span><span class="hm-sval"><input id="hs-end" name="end_time" class="hm-inp" type="time" value="18:00"></span></div>
                            <div class="hm-srow"><span class="hm-slbl">Time interval</span><span class="hm-sval"><select id="hs-interval" name="time_interval" class="hm-dd"><option value="15">15 minutes</option><option value="20">20 minutes</option><option value="30" selected>30 minutes</option><option value="45">45 minutes</option><option value="60">60 minutes</option></select></span></div>
                            <div class="hm-srow"><span class="hm-slbl">Slot height</span><span class="hm-sval"><select id="hs-slotH" name="slot_height" class="hm-dd"><option value="compact">Compact</option><option value="regular" selected>Regular</option><option value="large">Large</option></select></span></div>
                            <div class="hm-srow"><span class="hm-slbl">Default timeframe</span><span class="hm-sval"><select id="hs-view" name="default_view" class="hm-dd"><option value="day">Day</option><option value="week" selected>Week</option></select></span></div>
                        </div>
                    </div>
                    <!-- Block 2: Display Preferences -->
                    <div class="hm-card">
                        <div class="hm-card-hd">Display Preferences</div>
                        <div class="hm-card-body">
                            <div class="hm-srow">
                                <label class="hm-day-check">
                                    <input id="hs-timeInline" name="show_time_inline" type="checkbox">
                                    <span class="hm-check"></span>
                                    Display time inline with patient name
                                </label>
                            </div>
                            <div class="hm-srow">
                                <label class="hm-day-check">
                                    <input id="hs-hideEnd" name="hide_end_time" type="checkbox" checked>
                                    <span class="hm-check"></span>
                                    Hide appointment end time
                                </label>
                            </div>
                            <div class="hm-srow">
                                <span class="hm-slbl">Outcome style</span>
                                <span class="hm-sval">
                                    <label>
                                        <input type="radio" name="outcome_style" value="default" checked>
                                        <span class="hm-check"></span>
                                        Default
                                    </label>
                                    <label>
                                        <input type="radio" name="outcome_style" value="small">
                                        <span class="hm-check"></span>
                                        Small
                                    </label>
                                </span>
                            </div>
                            <div class="hm-srow">
                                <label class="hm-day-check">
                                    <input id="hs-fullName" name="display_full_name" type="checkbox">
                                    <span class="hm-check"></span>
                                    Display full resource name
                                </label>
                            </div>
                        </div>
                    </div>
                    <!-- Block 3: Preview -->
                    <div class="hm-card">
                        <div class="hm-card-hd">Preview</div>
                        <div class="hm-card-body">
                            <div class="hm-appt-preview-card" id="hs-preview-card">
                                <div class="hm-appt-body">
                                    <div class="hm-appt-name" id="hs-preview-name">Joe Bloggs</div>
                                    <div style="margin:6px 0" id="hs-preview-badges"><span class="hm-badge hm-badge-c">C</span> <span class="hm-badge hm-badge-r">R</span> <span class="hm-badge hm-badge-v">VM</span></div>
                                    <div class="hm-appt-time" id="hs-preview-time">09:00</div>
                                    <div class="hm-appt-meta" id="hs-preview-meta">Follow up · Cosgrove's Pharmacy</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div style="text-align:right;margin-top:18px;">
                    <button type="button" class="hm-btn hm-btn-teal" id="hm-settings-save">Save Settings</button>
                </div>
                </form>
            </div>
        </div>
        </div>
        </div>
            <?php
            if ( current_user_can( 'manage_options' ) ) : ?>
                <script>
                (function(){
                    try{
                        console.log('HM-DEBUG (admin):', window.HM || 'HM missing');
                        console.log('hearmed-calendar script present?', typeof Settings !== 'undefined');
                        console.log('#hm-app element', document.getElementById('hm-app'));
                        // quick ajax test
                        var ajax = (window.HM && window.HM.ajax_url) || '/wp-admin/admin-ajax.php';
                        var nonce = (window.HM && window.HM.nonce) || '';
                        fetch(ajax, {
                            method:'POST',
                            headers:{'Content-Type':'application/x-www-form-urlencoded'},
                            body:new URLSearchParams({action:'hm_get_settings',nonce:nonce})
                        }).then(function(r){return r.text().then(function(t){console.log('hm_get_settings status',r.status,'body',t);});}).catch(function(e){console.error('hm_get_settings fetch error',e);});
                    }catch(e){console.error('HM-DEBUG admin error',e);}    
                })();
                </script>
            <?php endif;
            return ob_get_clean();
    }
}

new HearMed_Admin_Calendar_Settings();
