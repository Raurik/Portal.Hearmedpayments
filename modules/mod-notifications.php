<?php
/**
 * Notification centre — bell icon + full page
 * 
 * Shortcode: [hearmed_notifications]
 * Page: see blueprint for URL
 */
if (!defined("ABSPATH")) exit;

// Standalone render function called by router
function hm_notifications_render() {
    if (!is_user_logged_in()) return;
    ?>
    <div class="hm-content">
        <div class="hm-page-header">
            <h1 class="hm-page-title">Notifications</h1>
        </div>
        <div id="hm-notifications-list" class="hm-notifications-container">
            <div class="hm-placeholder" style="padding:3rem;text-align:center;color:#94a3b8;">
                <p>📬 No new notifications</p>
                <p style="font-size:0.875rem;margin-top:0.5rem;">You're all caught up!</p>
            </div>
        </div>
    </div>
    <?php
}

class HearMed_Notifications {

    public static function init() {
        add_shortcode("hearmed_notifications", [__CLASS__, "render"]);
    }

    public static function render($atts = []): string {
        if (!is_user_logged_in()) return "";
        
        ob_start();
        hm_notifications_render();
        return ob_get_clean();
    }
}

HearMed_Notifications::init();
