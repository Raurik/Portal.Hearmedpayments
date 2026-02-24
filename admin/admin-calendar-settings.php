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
        <div class="hm-settings-main" id="hm-calendar-settings-app">
            <div class="hm-settings-header">Calendar Settings</div>
            <div class="hm-settings-subtitle">Adjust your scheduling and display preferences.</div>
            <div class="hm-settings-grid">
                <div class="hm-settings-cards">
                    <div class="hm-settings-card">
                        <div class="hm-settings-card-hd">🕐 Time &amp; View</div>
                        <div class="hm-settings-card-body">
                            <div class="hm-settings-tog"><label for="hs-start">Start time</label><input id="hs-start" type="time" value="09:00"></div>
                            <div class="hm-settings-tog"><label for="hs-end">End time</label><input id="hs-end" type="time" value="18:00"></div>
                            <div class="hm-settings-tog"><label for="hs-interval">Time interval</label><select id="hs-interval"><option value="15">15 minutes</option><option value="20">20 minutes</option><option value="30" selected>30 minutes</option><option value="45">45 minutes</option><option value="60">60 minutes</option></select></div>
                            <div class="hm-settings-tog"><label for="hs-slotH">Slot height</label><select id="hs-slotH"><option value="compact">Compact</option><option value="regular" selected>Regular</option><option value="large">Large</option></select></div>
                            <div class="hm-settings-tog"><label for="hs-view">Default timeframe</label><select id="hs-view"><option value="day">Day</option><option value="week" selected>Week</option></select></div>
                        </div>
                    </div>
                    <div class="hm-settings-card">
                        <div class="hm-settings-card-hd">🛡 Rules &amp; Safety</div>
                        <div class="hm-settings-card-body">
                            <div class="hm-settings-tog"><label><input id="hs-cancelReason" type="checkbox" checked> Require cancellation reason</label></div>
                            <div class="hm-settings-tog"><label><input id="hs-hideCancelled" type="checkbox" checked> Hide cancelled appointments</label></div>
                            <div class="hm-settings-tog"><label><input id="hs-reschedNote" type="checkbox"> Require reschedule note</label></div>
                            <div class="hm-settings-tog"><label><input id="hs-locMismatch" type="checkbox"> Prevent mismatched location bookings</label></div>
                        </div>
                    </div>
                    <div class="hm-settings-card">
                        <div class="hm-settings-card-hd">📅 Availability</div>
                        <div class="hm-settings-card-body">
                            <div class="hm-settings-tog"><div>Enabled days</div><div>
                                <label><input class="hs-day" type="checkbox" value="mon" checked> Mon</label>
                                <label><input class="hs-day" type="checkbox" value="tue" checked> Tue</label>
                                <label><input class="hs-day" type="checkbox" value="wed" checked> Wed</label>
                                <label><input class="hs-day" type="checkbox" value="thu" checked> Thu</label>
                                <label><input class="hs-day" type="checkbox" value="fri" checked> Fri</label>
                                <label><input class="hs-day" type="checkbox" value="sat"> Sat</label>
                                <label><input class="hs-day" type="checkbox" value="sun"> Sun</label>
                            </div></div>
                            <div class="hm-settings-tog"><label><input id="hs-clinicColour" type="checkbox"> Apply clinic colour to working times</label></div>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="hm-settings-card">
                        <div class="hm-settings-card-hd">👁 Display Preferences</div>
                        <div class="hm-settings-card-body">
                            <div class="hm-settings-tog"><label><input id="hs-timeInline" type="checkbox"> Display time inline with patient name</label></div>
                            <div class="hm-settings-tog"><label><input id="hs-hideEnd" type="checkbox" checked> Hide appointment end time</label></div>
                            <div class="hm-settings-tog"><div>Outcome style</div><div>
                                <label><input type="radio" name="hs-outcome" value="default" checked> Default</label>
                                <label><input type="radio" name="hs-outcome" value="small"> Small</label>
                                <label><input type="radio" name="hs-outcome" value="tag"> Tag</label>
                                <label><input type="radio" name="hs-outcome" value="popover"> Popover</label>
                            </div></div>
                            <div class="hm-settings-tog"><label><input id="hs-fullName" type="checkbox"> Display full resource name</label></div>
                        </div>
                    </div>
                    <div class="hm-settings-preview">
                        <div class="hm-settings-appt-card" id="hs-preview-card">
                            <div class="hm-settings-appt-name" id="hs-preview-name">Joe Bloggs</div>
                            <div class="hm-settings-appt-badges" id="hs-preview-badges">
                                <span class="hm-settings-badge">C</span>
                                <span class="hm-settings-badge">R</span>
                                <span class="hm-settings-badge">VM</span>
                            </div>
                            <div class="hm-settings-appt-time" id="hs-preview-time">09:00</div>
                            <div class="hm-settings-appt-meta" id="hs-preview-meta">Follow up · Cosgrove's Pharmacy</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
                <?php if ( $show_debug ): ?>
                <div style="margin-top:18px;padding:12px;border-top:1px solid var(--hm-border);">
                    <h3 style="margin:0 0 10px 0">Preview (server-side fallback)</h3>
                    <div class="hs-preview-container">
                        <div class="hm-appt-preview outcome-default">
                            <div class="hm-appt-outcome">Outcome</div>
                            <div class="hm-appt-body">
                                <div class="hm-appt-name">Piet Pompies</div>
                                <div class="hm-appt-time">09:00</div>
                                <div class="hm-appt-meta">Follow up · Cosgrove's Pharmacy</div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
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
