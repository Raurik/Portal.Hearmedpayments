<?php
/**
 * HearMed Database Abstraction Layer
 * PostgreSQL ONLY - ZERO WordPress database usage
 * 
 * This is the ONLY class that talks to the database.
 * All modules use this class.
 */

if (!defined('ABSPATH')) exit;

class HearMed_DB {
    
    private static $pg_conn = null;
    
    /**
     * Table mapping - slug to PostgreSQL schema.table
     */
    private static $tables = [
        // Reference data
        'clinics'         => 'hearmed_reference.clinics',
        'staff'           => 'hearmed_reference.staff',
        'staff_clinics'   => 'hearmed_reference.staff_clinics',
        'manufacturers'   => 'hearmed_reference.manufacturers',
        'products'        => 'hearmed_reference.products',
        'services'        => 'hearmed_reference.services',
        'patients'        => 'hearmed_reference.patients',
        
        // Core operational
        'appointments'    => 'hearmed_core.appointments',
        'patient_notes'   => 'hearmed_core.patient_notes',
        'patient_documents' => 'hearmed_core.patient_documents',
        'patient_devices' => 'hearmed_core.patient_devices',
        'orders'          => 'hearmed_core.orders',
        'order_items'     => 'hearmed_core.order_items',
        'invoices'        => 'hearmed_core.invoices',
        'invoice_items'   => 'hearmed_core.invoice_items',
        'payments'        => 'hearmed_core.payments',
        'repairs'         => 'hearmed_core.repairs',
        
        // Communication
        'notifications'   => 'hearmed_communication.notifications',
        'sms_messages'    => 'hearmed_communication.sms_messages',
        
        // Admin
        'audit_log'       => 'hearmed_admin.audit_log',
        'kpi_targets'     => 'hearmed_admin.kpi_targets',
    ];
    
    /**
     * Get PostgreSQL connection
     */
    private static function connect() {
        if (self::$pg_conn) {
            return self::$pg_conn;
        }
        
        if (!defined('HEARMED_PG_HOST')) {
            error_log('[HearMed DB] PostgreSQL credentials not defined in wp-config.php');
            return false;
        }
        
        $conn_string = sprintf(
            "host=%s port=%s dbname=%s user=%s password=%s sslmode=%s",
            HEARMED_PG_HOST,
            HEARMED_PG_PORT ?? 5432,
            HEARMED_PG_DB,
            HEARMED_PG_USER,
            HEARMED_PG_PASS,
            HEARMED_PG_SSLMODE ?? 'require'
        );
        
        self::$pg_conn = @pg_connect($conn_string);
        
        if (!self::$pg_conn) {
            error_log('[HearMed DB] PostgreSQL connection failed');
            return false;
        }
        
        return self::$pg_conn;
    }
    
    /**
     * Get full table name from slug
     */
    public static function table($slug) {
        return self::$tables[$slug] ?? 'hearmed_core.' . $slug;
    }
    
    /**
     * Execute SELECT query - Returns array of objects
     */
    public static function get_results($sql, $params = []) {
        $conn = self::connect();
        if (!$conn) return [];
        
        if (empty($params)) {
            $result = @pg_query($conn, $sql);
        } else {
            $result = @pg_query_params($conn, $sql, $params);
        }
        
        if (!$result) {
            error_log('[HearMed DB] Query failed: ' . pg_last_error($conn));
            return [];
        }
        
        $rows = [];
        while ($row = pg_fetch_object($result)) {
            $rows[] = $row;
        }
        
        return $rows;
    }
    
    /**
     * Execute SELECT query - Returns single object
     */
    public static function get_row($sql, $params = []) {
        $results = self::get_results($sql, $params);
        return $results[0] ?? null;
    }
    
    /**
     * Execute SELECT query - Returns single value
     */
    public static function get_var($sql, $params = []) {
        $row = self::get_row($sql, $params);
        if (!$row) return null;
        
        $vars = get_object_vars($row);
        return array_shift($vars);
    }
    
    /**
     * Insert row - Returns new ID
     */
    public static function insert($table, $data) {
        $conn = self::connect();
        if (!$conn) return false;
        
        $table_name = self::table($table);
        
        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = [];
        
        for ($i = 1; $i <= count($values); $i++) {
            $placeholders[] = '$' . $i;
        }
        
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) RETURNING id',
            $table_name,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        
        $result = @pg_query_params($conn, $sql, $values);
        
        if (!$result) {
            error_log('[HearMed DB] Insert failed: ' . pg_last_error($conn));
            return false;
        }
        
        $row = pg_fetch_object($result);
        return $row->id ?? false;
    }
    
    /**
     * Update row - Returns affected rows count
     */
    public static function update($table, $data, $where) {
        $conn = self::connect();
        if (!$conn) return false;
        
        $table_name = self::table($table);
        
        $set_parts = [];
        $values = [];
        $i = 1;
        
        foreach ($data as $col => $val) {
            $set_parts[] = "$col = \$$i";
            $values[] = $val;
            $i++;
        }
        
        $where_parts = [];
        foreach ($where as $col => $val) {
            $where_parts[] = "$col = \$$i";
            $values[] = $val;
            $i++;
        }
        
        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $table_name,
            implode(', ', $set_parts),
            implode(' AND ', $where_parts)
        );
        
        $result = @pg_query_params($conn, $sql, $values);
        
        if (!$result) {
            error_log('[HearMed DB] Update failed: ' . pg_last_error($conn));
            return false;
        }
        
        return pg_affected_rows($result);
    }
    
    /**
     * Delete row - Returns affected rows count
     */
    public static function delete($table, $where) {
        $conn = self::connect();
        if (!$conn) return false;
        
        $table_name = self::table($table);
        
        $where_parts = [];
        $values = [];
        $i = 1;
        
        foreach ($where as $col => $val) {
            $where_parts[] = "$col = \$$i";
            $values[] = $val;
            $i++;
        }
        
        $sql = sprintf(
            'DELETE FROM %s WHERE %s',
            $table_name,
            implode(' AND ', $where_parts)
        );
        
        $result = @pg_query_params($conn, $sql, $values);
        
        if (!$result) {
            error_log('[HearMed DB] Delete failed: ' . pg_last_error($conn));
            return false;
        }
        
        return pg_affected_rows($result);
    }
    
    /**
     * Begin transaction
     */
    public static function begin_transaction() {
        $conn = self::connect();
        if (!$conn) return false;
        
        return @pg_query($conn, 'BEGIN');
    }
    
    /**
     * Commit transaction
     */
    public static function commit() {
        $conn = self::connect();
        if (!$conn) return false;
        
        return @pg_query($conn, 'COMMIT');
    }
    
    /**
     * Rollback transaction
     */
    public static function rollback() {
        $conn = self::connect();
        if (!$conn) return false;
        
        return @pg_query($conn, 'ROLLBACK');
    }
    
    /**
     * Execute raw query (for complex operations)
     */
    public static function query($sql, $params = []) {
        $conn = self::connect();
        if (!$conn) return false;
        
        if (empty($params)) {
            return @pg_query($conn, $sql);
        } else {
            return @pg_query_params($conn, $sql, $params);
        }
    }
}
