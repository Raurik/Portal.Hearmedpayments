<?php
/**
 * Commission tracking — periods, calculations, tiers, bonuses
 * 
 * Shortcode: [hearmed_commissions]
 * Page: see blueprint for URL
 */
if (!defined("ABSPATH")) exit;

// Standalone render function called by router
function hm_commissions_render() {
    if (!is_user_logged_in()) return;
    ?>
    <div class="hm-content">
        <div class="hm-page-header">
            <h1 class="hm-page-title">Commissions</h1>
        </div>
        <div class="hm-placeholder" style="padding:3rem;text-align:center;color:var(--hm-text-muted);">
            <p>Commission tracking module — coming soon</p>
            <p style="font-size:0.875rem;margin-top:0.5rem;">Track commission periods, calculations, and payments</p>
        </div>
    </div>
    <?php
}

class HearMed_Commissions {

    public static function init() {
        add_shortcode("hearmed_commissions", [__CLASS__, "render"]);
    }

    public static function render($atts = []): string {
        if (!is_user_logged_in()) return "";
        
        ob_start();
        hm_commissions_render();
        return ob_get_clean();
    }
}

HearMed_Commissions::init();
