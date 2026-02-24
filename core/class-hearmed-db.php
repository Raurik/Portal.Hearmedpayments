<?php
/**
 * HearMed Database Abstraction Layer
 * PostgreSQL ONLY - ZERO WordPress database usage
 *
 * This is the ONLY class that talks to the database.
 * All modules use this class.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_DB {

    private static $pg_conn    = null;
    private static $last_error = '';

    /**
     * Singleton accessor.
     * Allows both HearMed_DB::method() and HearMed_DB::instance()->method() patterns.
     */
    public static function instance() {
        static $inst = null;
        if ( $inst === null ) $inst = new self();
        return $inst;
    }

    // -------------------------------------------------------------------------
    // Table slug → fully-qualified schema.table
    // Use the slug in insert()/update()/delete() calls.
    // In raw SQL always use the full schema.table name directly.
    // -------------------------------------------------------------------------
    private static $tables = [

        // ── hearmed_reference ─────────────────────────────────────────────
        'clinics'                  => 'hearmed_reference.clinics',
        'staff'                    => 'hearmed_reference.staff',
        'staff_clinics'            => 'hearmed_reference.staff_clinics',
        'staff_auth'               => 'hearmed_reference.staff_auth',
        'staff_qualifications'     => 'hearmed_reference.staff_qualifications',
        'dispenser_schedules'      => 'hearmed_reference.dispenser_schedules',
        'manufacturers'            => 'hearmed_reference.manufacturers',
        'products'                 => 'hearmed_reference.products',
        'product_specifications'   => 'hearmed_reference.product_specifications',
        'services'                 => 'hearmed_reference.services',
        'service_prerequisites'    => 'hearmed_reference.service_prerequisites',
        'roles'                    => 'hearmed_reference.roles',
        'appointment_types'        => 'hearmed_reference.appointment_types',
        'referral_sources'         => 'hearmed_reference.referral_sources',
        'payment_methods'          => 'hearmed_reference.payment_methods',
        'coupled_items'            => 'hearmed_reference.coupled_items',
        'domes'                    => 'hearmed_reference.domes',
        'speakers'                 => 'hearmed_reference.speakers',
        'inventory_stock'          => 'hearmed_reference.inventory_stock',
        'stock_movements'          => 'hearmed_reference.stock_movements',
        'audiometers'              => 'hearmed_reference.audiometers',
        'hearmed_range'            => 'hearmed_reference.hearmed_range',

        // ── hearmed_core ──────────────────────────────────────────────────
        'patients'                 => 'hearmed_core.patients',          // ← WAS WRONG (was hearmed_reference)
        'appointments'             => 'hearmed_core.appointments',
        'appointment_outcomes'     => 'hearmed_core.appointment_outcomes',
        'outcome_templates'        => 'hearmed_core.outcome_templates',
        'calendar_settings'        => 'hearmed_core.calendar_settings',
        'calendar_blockouts'       => 'hearmed_core.calendar_blockouts',
        'staff_absences'           => 'hearmed_core.staff_absences',
        'patient_notes'            => 'hearmed_core.patient_notes',
        'patient_documents'        => 'hearmed_core.patient_documents',
        'patient_forms'            => 'hearmed_core.patient_forms',
        'patient_devices'          => 'hearmed_core.patient_devices',
        'patient_timeline'         => 'hearmed_core.patient_timeline',
        'orders'                   => 'hearmed_core.orders',
        'order_items'              => 'hearmed_core.order_items',
        'order_shipments'          => 'hearmed_core.order_shipments',
        'order_status_history'     => 'hearmed_core.order_status_history',
        'invoices'                 => 'hearmed_core.invoices',
        'invoice_items'            => 'hearmed_core.invoice_items',
        'payments'                 => 'hearmed_core.payments',
        'credit_notes'             => 'hearmed_core.credit_notes',
        'fitting_queue'            => 'hearmed_core.fitting_queue',
        'repairs'                  => 'hearmed_core.repairs',
        'cash_transactions'        => 'hearmed_core.cash_transactions',
        'cash_drawer_readings'     => 'hearmed_core.cash_drawer_readings',
        'till_reconciliations'     => 'hearmed_core.till_reconciliations',
        'financial_transactions'   => 'hearmed_core.financial_transactions',

        // ── hearmed_communication ─────────────────────────────────────────
        'notifications'            => 'hearmed_communication.notifications',
        'internal_notifications'   => 'hearmed_communication.internal_notifications',
        'notification_recipients'  => 'hearmed_communication.notification_recipients',
        'notification_actions'     => 'hearmed_communication.notification_actions',
        'sms_messages'             => 'hearmed_communication.sms_messages',
        'sms_templates'            => 'hearmed_communication.sms_templates',
        'chat_channels'            => 'hearmed_communication.chat_channels',
        'chat_channel_members'     => 'hearmed_communication.chat_channel_members',
        'chat_messages'            => 'hearmed_communication.chat_messages',

        // ── hearmed_admin ─────────────────────────────────────────────────
        'audit_log'                => 'hearmed_admin.audit_log',
        'kpi_targets'              => 'hearmed_admin.kpi_targets',
        'commission_entries'       => 'hearmed_admin.commission_entries',
        'commission_periods'       => 'hearmed_admin.commission_periods',
        'commission_rules'         => 'hearmed_admin.commission_rules',
        'gdpr_deletions'           => 'hearmed_admin.gdpr_deletions',
        'gdpr_exports'             => 'hearmed_admin.gdpr_exports',
    ];

    // -------------------------------------------------------------------------
    // Connection
    // -------------------------------------------------------------------------
    private static function connect() {
        if ( self::$pg_conn ) return self::$pg_conn;

        if ( ! defined( 'HEARMED_PG_HOST' ) ) {
            self::$last_error = 'PostgreSQL credentials not defined in wp-config.php';
            error_log( '[HearMed DB] ' . self::$last_error );
            return false;
        }

        $conn_string = sprintf(
            "host=%s port=%s dbname=%s user=%s password=%s sslmode=%s",
            HEARMED_PG_HOST,
            defined( 'HEARMED_PG_PORT' )    ? HEARMED_PG_PORT    : 5432,
            HEARMED_PG_DB,
            HEARMED_PG_USER,
            HEARMED_PG_PASS,
            defined( 'HEARMED_PG_SSLMODE' ) ? HEARMED_PG_SSLMODE : 'require'
        );

        self::$pg_conn = @pg_connect( $conn_string );

        if ( ! self::$pg_conn ) {
            self::$last_error = 'PostgreSQL connection failed. Check host, port, db, user, password, sslmode.';
            error_log( '[HearMed DB] ' . self::$last_error );
            return false;
        }

        // ✅ Tell PostgreSQL which schemas to search — critical for cross-schema queries
        @pg_query(
            self::$pg_conn,
            "SET search_path TO hearmed_core, hearmed_reference, hearmed_communication, hearmed_admin, public"
        );

        return self::$pg_conn;
    }

    // -------------------------------------------------------------------------
    // Table name resolver
    // -------------------------------------------------------------------------
    public static function table( $slug ) {
        // Already fully-qualified (e.g. 'hearmed_core.orders') — pass through
        if ( strpos( $slug, '.' ) !== false ) return $slug;
        // Known slug → look up
        if ( isset( self::$tables[ $slug ] ) ) return self::$tables[ $slug ];
        // Unknown slug → assume hearmed_core (fail-safe, will surface in logs)
        error_log( "[HearMed DB] Unknown table slug '{$slug}' — defaulting to hearmed_core.{$slug}" );
        return 'hearmed_core.' . $slug;
    }

    // -------------------------------------------------------------------------
    // Legacy prepare helper (%s / %d placeholders)
    // Prefer parameterised queries ($1, $2…) in new code — safer and faster.
    // -------------------------------------------------------------------------
    public static function prepare( $sql, ...$params ) {
        $conn = self::connect();
        if ( ! $conn || empty( $params ) ) return $sql;
        $i = 0;
        return preg_replace_callback( '/%[sd]/', function ( $m ) use ( &$i, $params, $conn ) {
            $val = $params[ $i ] ?? '';
            $i++;
            return $m[0] === '%d' ? (string) intval( $val ) : pg_escape_literal( $conn, (string) $val );
        }, $sql );
    }

    // -------------------------------------------------------------------------
    // SELECT — returns array of objects
    // -------------------------------------------------------------------------
    public static function get_results( $sql, $params = [] ) {
        $conn = self::connect();
        if ( ! $conn ) return [];

        $result = empty( $params )
            ? @pg_query( $conn, $sql )
            : @pg_query_params( $conn, $sql, $params );

        if ( ! $result ) {
            self::$last_error = pg_last_error( $conn );
            error_log( '[HearMed DB] get_results failed: ' . self::$last_error . ' | SQL: ' . $sql );
            return [];
        }

        $rows = [];
        while ( $row = pg_fetch_object( $result ) ) {
            $rows[] = $row;
        }
        return $rows;
    }

    // -------------------------------------------------------------------------
    // SELECT — returns single object
    // -------------------------------------------------------------------------
    public static function get_row( $sql, $params = [] ) {
        $results = self::get_results( $sql, $params );
        return $results[0] ?? null;
    }

    // -------------------------------------------------------------------------
    // SELECT — returns single scalar value
    // -------------------------------------------------------------------------
    public static function get_var( $sql, $params = [] ) {
        $row = self::get_row( $sql, $params );
        if ( ! $row ) return null;
        $vars = get_object_vars( $row );
        return array_shift( $vars );
    }

    // -------------------------------------------------------------------------
    // INSERT — returns new row ID (or false)
    // -------------------------------------------------------------------------
    public static function insert( $table, $data ) {
        $conn = self::connect();
        if ( ! $conn ) return false;

        $table_name   = self::table( $table );
        $columns      = array_keys( $data );
        $values       = array_values( $data );

        // PHP booleans → PostgreSQL literal strings
        $values = array_map( fn( $v ) => is_bool( $v ) ? ( $v ? 'true' : 'false' ) : $v, $values );

        $placeholders = array_map( fn( $i ) => '$' . ( $i + 1 ), array_keys( $values ) );

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) RETURNING id',
            $table_name,
            implode( ', ', $columns ),
            implode( ', ', $placeholders )
        );

        $result = @pg_query_params( $conn, $sql, $values );

        if ( ! $result ) {
            self::$last_error = pg_last_error( $conn );
            error_log( '[HearMed DB] insert failed on ' . $table_name . ': ' . self::$last_error );
            return false;
        }

        $row = pg_fetch_object( $result );
        return $row->id ?? false;
    }

    // -------------------------------------------------------------------------
    // UPDATE — returns affected row count (or false)
    // -------------------------------------------------------------------------
    public static function update( $table, $data, $where ) {
        $conn = self::connect();
        if ( ! $conn ) return false;

        $table_name = self::table( $table );
        $set_parts  = [];
        $values     = [];
        $i          = 1;

        foreach ( $data as $col => $val ) {
            $set_parts[] = "{$col} = \${$i}";
            $values[]    = is_bool( $val ) ? ( $val ? 'true' : 'false' ) : $val;
            $i++;
        }

        $where_parts = [];
        foreach ( $where as $col => $val ) {
            $where_parts[] = "{$col} = \${$i}";
            $values[]      = is_bool( $val ) ? ( $val ? 'true' : 'false' ) : $val;
            $i++;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table_name,
            implode( ', ', $set_parts ),
            implode( ' AND ', $where_parts )
        );

        $result = @pg_query_params( $conn, $sql, $values );

        if ( ! $result ) {
            self::$last_error = pg_last_error( $conn );
            error_log( '[HearMed DB] update failed on ' . $table_name . ': ' . self::$last_error );
            return false;
        }

        return pg_affected_rows( $result );
    }

    // -------------------------------------------------------------------------
    // DELETE — returns affected row count (or false)
    // -------------------------------------------------------------------------
    public static function delete( $table, $where ) {
        $conn = self::connect();
        if ( ! $conn ) return false;

        $table_name  = self::table( $table );
        $where_parts = [];
        $values      = [];
        $i           = 1;

        foreach ( $where as $col => $val ) {
            $where_parts[] = "{$col} = \${$i}";
            $values[]      = $val;
            $i++;
        }

        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $table_name,
            implode( ' AND ', $where_parts )
        );

        $result = @pg_query_params( $conn, $sql, $values );

        if ( ! $result ) {
            self::$last_error = pg_last_error( $conn );
            error_log( '[HearMed DB] delete failed on ' . $table_name . ': ' . self::$last_error );
            return false;
        }

        return pg_affected_rows( $result );
    }

    // -------------------------------------------------------------------------
    // Raw query (complex SQL, CTEs, etc.)
    // -------------------------------------------------------------------------
    public static function query( $sql, $params = [] ) {
        $conn = self::connect();
        if ( ! $conn ) return false;

        $result = empty( $params )
            ? @pg_query( $conn, $sql )
            : @pg_query_params( $conn, $sql, $params );

        if ( ! $result ) {
            self::$last_error = pg_last_error( $conn );
            error_log( '[HearMed DB] query failed: ' . self::$last_error . ' | SQL: ' . $sql );
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // Transactions
    // -------------------------------------------------------------------------
    public static function begin_transaction() {
        $conn = self::connect();
        return $conn ? @pg_query( $conn, 'BEGIN' ) : false;
    }

    public static function commit() {
        $conn = self::connect();
        return $conn ? @pg_query( $conn, 'COMMIT' ) : false;
    }

    public static function rollback() {
        $conn = self::connect();
        return $conn ? @pg_query( $conn, 'ROLLBACK' ) : false;
    }

    // -------------------------------------------------------------------------
    // Error reporting
    // -------------------------------------------------------------------------
    public static function last_error() {
        return self::$last_error;
    }
}