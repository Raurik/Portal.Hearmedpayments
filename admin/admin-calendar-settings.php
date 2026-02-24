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
                <div id="hm-app" class="hm-calendar" data-module="calendar" data-view="settings"></div>
                <?php if ( $show_debug ): ?>
                <div style="margin-top:18px;padding:12px;border-top:1px solid var(--hm-border);">
                    <h3 style="margin:0 0 10px 0">Preview (server-side fallback)</h3>
                    <div class="hs-preview-container">
                        <div class="hm-appt-preview outcome-default">
                            <div class="hm-appt-outcome">Outcome</div>
                            <div class="hm-appt-body">
                                <div class="hm-appt-name">Joe Bloggs</div>
                                <div class="hm-appt-time">09:00</div>
                                <div class="hm-appt-meta">Follow up · Cosgrove's Pharmacy</div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        return ob_get_clean();
    }
}

new HearMed_Admin_Calendar_Settings();
