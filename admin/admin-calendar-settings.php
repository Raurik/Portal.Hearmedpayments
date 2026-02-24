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
                            <div class="hm-settings-tog"><span>Start time</span><span>09:00</span></div>
                            <div class="hm-settings-tog"><span>End time</span><span>18:00</span></div>
                            <div class="hm-settings-tog"><span>Time interval</span><span>30 minutes</span></div>
                            <div class="hm-settings-tog"><span>Slot height</span><span>Regular</span></div>
                            <div class="hm-settings-tog"><span>Default timeframe</span><span>Week</span></div>
                        </div>
                    </div>
                    <div class="hm-settings-card">
                        <div class="hm-settings-card-hd">🛡 Rules &amp; Safety</div>
                        <div class="hm-settings-card-body">
                            <div class="hm-settings-tog"><span>Require cancellation reason</span><span>✔</span></div>
                            <div class="hm-settings-tog"><span>Hide cancelled appointments</span><span>✔</span></div>
                            <div class="hm-settings-tog"><span>Require reschedule note</span><span>✘</span></div>
                            <div class="hm-settings-tog"><span>Prevent mismatched location bookings</span><span>✘</span></div>
                        </div>
                    </div>
                    <div class="hm-settings-card">
                        <div class="hm-settings-card-hd">📅 Availability</div>
                        <div class="hm-settings-card-body">
                            <div class="hm-settings-tog"><span>Enabled days</span><span>Mon, Tue, Wed, Thu, Fri</span></div>
                            <div class="hm-settings-tog"><span>Apply clinic colour to working times</span><span>✘</span></div>
                        </div>
                    </div>
                </div>
                <div>
                    <div class="hm-settings-card">
                        <div class="hm-settings-card-hd">👁 Display Preferences</div>
                        <div class="hm-settings-card-body">
                            <div class="hm-settings-tog"><span>Display time inline with patient name</span><span>✔</span></div>
                            <div class="hm-settings-tog"><span>Hide appointment end time</span><span>✔</span></div>
                            <div class="hm-settings-tog"><span>Outcome style</span><span>Default</span></div>
                            <div class="hm-settings-tog"><span>Display full resource name</span><span>✘</span></div>
                        </div>
                    </div>
                    <div class="hm-settings-preview">
                        <div class="hm-settings-appt-card">
                            <div class="hm-settings-appt-name">Joe Bloggs</div>
                            <div class="hm-settings-appt-badges">
                                <span class="hm-settings-badge">C</span>
                                <span class="hm-settings-badge">R</span>
                                <span class="hm-settings-badge">VM</span>
                            </div>
                            <div class="hm-settings-appt-time">09:00</div>
                            <div class="hm-settings-appt-meta">Follow up · Cosgrove's Pharmacy</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
                                        <div class="hm-srow"><span class="hm-slbl">Hide appointment end time</span><div class="hm-sval">✖</div></div>
                                    </div>
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
