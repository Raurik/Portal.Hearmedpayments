<?php
/**
 * Repairs overview — tracking, status, manufacturer returns
 * 
 * Shortcode: [hearmed_repairs]
 * Page: see blueprint for URL
 */
if (!defined("ABSPATH")) exit;

//Standalone render function called by router
function hm_repairs_render() {
    if (!is_user_logged_in()) return;
    ?>
    <div class="hm-content">
        <div class="hm-page-header">
            <h1 class="hm-page-title">Repairs</h1>
        </div>
        <div class="hm-placeholder" style="padding:3rem;text-align:center;color:#94a3b8;">
            <p>Repairs module — coming soon</p>
            <p style="font-size:0.875rem;margin-top:0.5rem;">Track hearing aid repairs and returns</p>
        </div>
    </div>
    <?php
}

class HearMed_Repairs {

    public static function init() {
        add_shortcode("hearmed_repairs", [__CLASS__, "render"]);
    }

    public static function render($atts = []): string {
        if (!is_user_logged_in()) return "";
        
        ob_start();
        hm_repairs_render();
        return ob_get_clean();
    }
}

HearMed_Repairs::init();
