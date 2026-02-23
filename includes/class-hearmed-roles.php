<?php
/**
 * HearMed PostgreSQL Database Wrapper (Safe Version)
 * Production-safe PostgreSQL connector for WordPress
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HearMed_PG {

    private static $conn = null;
    private static $connected = false;

    /**
     * Build connection string from wp-config constants
     */
    private static function get_connection_string() {

        if ( ! defined('HEARMED_PG_HOST') ) {
            return false;
        }

        return sprintf(
            "host=%s port=%s dbname=%s user=%s password=%s sslmode=%s",
            HEARMED_PG_HOST,
            HEARMED_PG_PORT ?? 5432,
            HEARMED_PG_DB,
            HEARMED_PG_USER,
            HEARMED_PG_PASS,
            HEARMED_PG_SSLMODE ?? 'require'
        );
    }

    /**
     * Connect (lazy)
     */
    public static function connect() {

        if ( self::$connected && self::$conn ) {
            return self::$conn;
        }

        $conn_string = self::get_connection_string();
        if ( ! $conn_string ) {
            error_log('[HearMed PG] Missing DB config.');
            return false;
        }

        try {

            self::$conn = @pg_connect($conn_string);

            if ( ! self::$conn ) {
                error_log('[HearMed PG] Connection failed.');
                return false;
            }

            self::$connected = true;
            return self::$conn;

        } catch ( Throwable $e ) {
            error_log('[HearMed PG] Exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Safe query execution
     */
    public static function query( $sql, $params = [] ) {

        $conn = self::connect();
        if ( ! $conn ) {
            return false;
        }

        try {

            $result = empty($params)
                ? @pg_query($conn, $sql)
                : @pg_query_params($conn, $sql, $params);

            if ( ! $result ) {
                error_log('[HearMed PG] Query failed: ' . pg_last_error($conn));
                return false;
            }

            return $result;

        } catch ( Throwable $e ) {
            error_log('[HearMed PG] Query exception: ' . $e->getMessage());
            return false;
        }
    }

    public static function get_results( $sql, $params = [] ) {
        $result = self::query($sql, $params);
        if ( ! $result ) return [];
        $rows = pg_fetch_all($result);
        return $rows ? array_map(fn($r) => (object)$r, $rows) : [];
    }

    public static function get_row( $sql, $params = [] ) {
        $result = self::query($sql, $params);
        if ( ! $result ) return null;
        $row = pg_fetch_assoc($result);
        return $row ? (object)$row : null;
    }

    public static function get_var( $sql, $params = [] ) {
        $result = self::query($sql, $params);
        if ( ! $result ) return null;
        $row = pg_fetch_array($result, 0, PGSQL_NUM);
        return $row ? $row[0] : null;
    }

    public static function insert( $table, $data ) {

        $conn = self::connect();
        if ( ! $conn ) return false;

        $columns = array_keys($data);
        $values  = array_values($data);

        $placeholders = [];
        for ($i = 1; $i <= count($values); $i++) {
            $placeholders[] = '$' . $i;
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) RETURNING id',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $result = @pg_query_params($conn, $sql, $values);
        if ( ! $result ) return false;

        $row = pg_fetch_assoc($result);
        return $row ? (int)$row['id'] : false;
    }

    public static function update( $table, $data, $where ) {

        $conn = self::connect();
        if ( ! $conn ) return false;

        $set_parts = [];
        $values = [];
        $i = 1;

        foreach ($data as $col => $val) {
            $set_parts[] = "$col = $$i";
            $values[] = $val;
            $i++;
        }

        $where_parts = [];
        foreach ($where as $col => $val) {
            $where_parts[] = "$col = $$i";
            $values[] = $val;
            $i++;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table,
            implode(', ', $set_parts),
            implode(' AND ', $where_parts)
        );

        $result = @pg_query_params($conn, $sql, $values);
        return $result ? pg_affected_rows($result) : false;
    }

    public static function delete( $table, $where ) {

        $conn = self::connect();
        if ( ! $conn ) return false;

        $where_parts = [];
        $values = [];
        $i = 1;

        foreach ($where as $col => $val) {
            $where_parts[] = "$col = $$i";
            $values[] = $val;
            $i++;
        }

        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $table,
            implode(' AND ', $where_parts)
        );

        $result = @pg_query_params($conn, $sql, $values);
        return $result ? pg_affected_rows($result) : false;
    }

    public static function begin_transaction() {
        self::query('BEGIN');
    }

    public static function commit() {
        self::query('COMMIT');
    }

    public static function rollback() {
        self::query('ROLLBACK');
    }

    public static function close() {
        if ( self::$conn ) {
            pg_close(self::$conn);
            self::$conn = null;
            self::$connected = false;
        }
    }
}

if ( ! class_exists('HearMed_DB_PostgreSQL') ) {
    class_alias('HearMed_PG', 'HearMed_DB_PostgreSQL');
}