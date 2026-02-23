<?php
/**
 * KPI tracking dashboard — targets vs actuals, gauges, trends
 * 
 * Shortcode: [hearmed_kpi]
 * Page: see blueprint for URL
 */
if (!defined("ABSPATH")) exit;

class HearMed_KPI {

    public static function init() {
        add_shortcode("hearmed_kpi", [__CLASS__, "render"]);
    }

    public static function render($atts = []): string {
        if (!is_user_logged_in()) return "";
        
        ob_start();
        ?>
        <div id="hm-app" data-view="hearmed_kpi">
            <div class="hm-page-header">
                <h1 class="hm-page-title">" . esc_html(ucwords(str_replace('_', ' ', 'hearmed_kpi'))) . "</h1>
            </div>
            <div class="hm-placeholder">
                <p>Module not yet built. See blueprint.</p>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

HearMed_KPI::init();
