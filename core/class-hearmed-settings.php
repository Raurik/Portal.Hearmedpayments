<?php
/**
 * HearMed Settings — reads/writes from hearmed_admin.settings table.
 * Drop-in replacement for get_option / update_option.
 */
class HearMed_Settings {
    private static $cache = null;

    /**
     * Get a setting value.
     */
    public static function get( $key, $default = '' ) {
        if ( self::$cache === null ) {
            self::load_all();
        }
        return array_key_exists( $key, self::$cache ) ? self::$cache[ $key ] : $default;
    }

    /**
     * Set a setting value (upsert).
     */
    public static function set( $key, $value ) {
        $db = HearMed_DB::instance();
        $exists = $db->get_var(
            "SELECT id FROM hearmed_admin.settings WHERE setting_key = $1",
            [ $key ]
        );
        if ( $exists ) {
            $db->query(
                "UPDATE hearmed_admin.settings SET setting_value = $1, updated_at = NOW() WHERE setting_key = $2",
                [ $value, $key ]
            );
        } else {
            $db->insert( 'hearmed_admin.settings', [
                'setting_key'   => $key,
                'setting_value' => $value,
            ]);
        }
        self::$cache[ $key ] = $value;
    }

    /**
     * Bulk load all settings into cache (one query per page load).
     */
    public static function load_all() {
        self::$cache = [];
        $rows = HearMed_DB::get_results(
            "SELECT setting_key, setting_value FROM hearmed_admin.settings"
        );
        if ( $rows ) {
            foreach ( $rows as $r ) {
                self::$cache[ $r->setting_key ] = $r->setting_value;
            }
        }
    }

    /**
     * Clear cache (useful after bulk updates).
     */
    public static function clear_cache() {
        self::$cache = null;
    }
}
