<?php
/**
 * Commission tracking — periods, calculations, tiers, bonuses
 * 
 * Shortcode: [hearmed_commissions]
 * Page: see blueprint for URL
 */
if (!defined("ABSPATH")) exit;

class HearMed_Commissions {

    public static function init() {
        add_shortcode("hearmed_commissions", [__CLASS__, "render"]);
    }

    public static function render($atts = []): string {
        if (!is_user_logged_in()) return "";
        
        ob_start();
        ?>
        <div id="hm-app" data-view="hearmed_commissions">
            <div class="hm-page-header">
                <h1 class="hm-page-title">" . esc_html(ucwords(str_replace('_', ' ', 'hearmed_commissions'))) . "</h1>
            </div>
            <div class="hm-placeholder">
                <p>Module not yet built. See blueprint.</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

HearMed_Commissions::init();
