<?php
/**
 * KPI tracking dashboard — targets vs actuals, gauges, trends
 * 
 * Shortcode: [hearmed_kpi]
 * Page: see blueprint for URL
 */
if (!defined("ABSPATH")) exit;

// Standalone render function called by router
function hm_kpi_render() {
    if (!is_user_logged_in()) return;
    ?>
    <div class="hm-content">
        <div class="hm-page-header">
            <h1 class="hm-page-title">KPI Dashboard</h1>
        </div>
        <div class="hm-placeholder" style="padding:3rem;text-align:center;color:#94a3b8;">
            <p>KPI tracking module — coming soon</p>
            <p style="font-size:0.875rem;margin-top:0.5rem;">Monitor targets, actuals, and performance trends</p>
        </div>
    </div>
    <?php
}

class HearMed_KPI {

    public static function init() {
        add_shortcode("hearmed_kpi", [__CLASS__, "render"]);
    }

    public static function render($atts = []): string {
        if (!is_user_logged_in()) return "";
        
        ob_start();
        hm_kpi_render();
        return ob_get_clean();
    }
}

HearMed_KPI::init();
