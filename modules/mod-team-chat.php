<?php
/**
 * Team chat / internal messaging
 * 
 * Shortcode: [hearmed_team_chat]
 * Page: see blueprint for URL
 */
if (!defined("ABSPATH")) exit;

class HearMed_TeamChat {

    public static function init() {
        add_shortcode("hearmed_team_chat", [__CLASS__, "render"]);
    }

    public static function render($atts = []): string {
        if (!is_user_logged_in()) return "";
        
        ob_start();
        ?>
        <div id="hm-app" data-view="hearmed_team_chat">
            <div class="hm-page-header">
                <h1 class="hm-page-title">" . esc_html(ucwords(str_replace('_', ' ', 'hearmed_team_chat'))) . "</h1>
            </div>
            <div class="hm-placeholder">
                <p>Module not yet built. See blueprint.</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

HearMed_TeamChat::init();
