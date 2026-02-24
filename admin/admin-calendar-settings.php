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
        <div class="hm-page" id="hm-calendar-settings-app">
            <div class="hm-page-hd">
                <h1 class="hm-page-title">Calendar Settings</h1>
            </div>
            <div class="hm-card">
                <div id="hm-app" class="hm-calendar" data-module="calendar" data-view="settings">
                    <!-- Server-side fallback markup: matches client-side two-column layout until JS loads -->
                    <div class="hm-settings">
                        <div class="hm-admin-hd"><div><h2>Calendar Settings</h2><div class="hm-admin-subtitle">Adjust your scheduling and display preferences.</div></div></div>
                        <div class="hm-settings-two" style="display:grid;grid-template-columns:1fr 360px;gap:16px;margin-top:12px">
                            <div class="hs-left">
                                <div class="hm-card">
                                    <div class="hm-card-hd"><h3>Time &amp; View</h3></div>
                                    <div class="hm-card-body">
                                        <div class="hm-srow"><span class="hm-slbl">Start time</span><div class="hm-sval">09:00</div></div>
                                        <div class="hm-srow"><span class="hm-slbl">End time</span><div class="hm-sval">18:00</div></div>
                                        <div class="hm-srow"><span class="hm-slbl">Time interval</span><div class="hm-sval">30 minutes</div></div>
                                    </div>
                                </div>
                            </div>
                            <div class="hs-right">
                                <div class="hm-card">
                                    <div class="hm-card-hd outcome-header">Outcome</div>
                                    <div class="hm-card-body">
                                        <div class="hm-appt-preview outcome-default">
                                            <div class="hm-appt-outcome">Outcome</div>
                                            <div class="hm-appt-body">
                                                <div class="hm-appt-name">Joe Bloggs</div>
                                                <div class="hm-appt-badges"><span class="hm-badge">C</span><span class="hm-badge">R</span><span class="hm-badge">VM</span></div>
                                                <div class="hm-appt-time">09:00</div>
                                                <div class="hm-appt-meta">Follow up · Cosgrove's Pharmacy</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="hm-card" style="margin-top:12px">
                                    <div class="hm-card-hd"><h3>Display Preferences</h3></div>
                                    <div class="hm-card-body">
                                        <div class="hm-srow"><span class="hm-slbl">Display time inline with patient name</span><div class="hm-sval">✔</div></div>
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
