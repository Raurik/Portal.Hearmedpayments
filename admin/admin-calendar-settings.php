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

        ob_start();
        ?>
        <div id="hm-app" class="hm-calendar" data-module="calendar" data-view="settings"></div>
        <?php

        // Inline debug panel (visible to admins or when ?hm_debug=1)
        $show_debug = ( current_user_can( 'manage_options' ) || ( isset( $_GET['hm_debug'] ) && current_user_can( 'edit_posts' ) ) );
        if ( $show_debug ) {
            echo '<div class="hm-debug" style="margin:20px;padding:12px;border:1px solid #eee;background:#fff;font-family:monospace;font-size:13px;">';
            echo '<h3 style="margin:0 0 8px 0">Calendar Settings Debug</h3>';
            echo '<pre style="white-space:pre-wrap">';

            $user = wp_get_current_user();
            $info = [];
            $info['current_user_id'] = get_current_user_id();
            $info['user_login'] = $user->user_login ?? '';
            $info['capabilities'] = array_keys( $user->allcaps ?? [] );
            $info['shortcode_registered'] = shortcode_exists( 'hearmed_calendar_settings' ) ? 'yes' : 'no';

            $legacy = HEARMED_PATH . 'modules/mod-calendar.php';
            $modern = HEARMED_PATH . 'modules/calendar/calendar.php';
            $info['module_legacy_path'] = $legacy;
            $info['module_modern_path'] = $modern;
            $info['module_legacy_exists'] = file_exists( $legacy ) ? 'yes' : 'no';
            $info['module_modern_exists'] = file_exists( $modern ) ? 'yes' : 'no';

            $info['admin_ajax_url'] = admin_url( 'admin-ajax.php' );
            $info['nonce_example'] = wp_create_nonce( 'hm_nonce' );

            if ( class_exists( 'HearMed_Portal' ) && method_exists( 'HearMed_Portal', 'table' ) ) {
                try {
                    $t = HearMed_Portal::table( 'calendar_settings' );
                    $info['calendar_settings_table'] = $t;
                    // check table exists (Postgres to_regclass)
                    if ( method_exists( 'HearMed_DB', 'prepare' ) ) {
                        $reg = HearMed_DB::get_var( HearMed_DB::prepare( "SELECT to_regclass(%s)", $t ) );
                        $info['table_exists'] = $reg ? 'yes' : 'no';
                    } else {
                        $info['table_exists'] = 'unknown';
                    }

                    // fetch a row if present
                    $row = @HearMed_DB::get_row( "SELECT * FROM {$t} LIMIT 1", ARRAY_A );
                    $info['settings_row'] = $row ?: [];
                } catch ( Exception $e ) {
                    $info['table_error'] = $e->getMessage();
                }
            } else {
                $info['calendar_settings_table'] = 'HearMed_Portal::table() not available';
            }

            print_r( $info );
            echo '</pre>';
            echo '</div>';
        }

        return ob_get_clean();
    }
}

new HearMed_Admin_Calendar_Settings();
