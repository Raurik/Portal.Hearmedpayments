<?php

add_action('wp_ajax_hm_acknowledge_privacy_notice', 'hm_acknowledge_privacy_notice_handler');

function hm_acknowledge_privacy_notice_handler() {
    check_ajax_referer('hm_nonce', 'nonce');

    if ( ! is_user_logged_in() ) {
        wp_send_json_error('User not logged in');
    }

    $user_id = get_current_user_id();
    update_user_meta($user_id, 'hm_privacy_notice_accepted', current_time('mysql'));
    wp_send_json_success();
}

/* ─── Generic Quick-Add for Reference Dropdowns ─── */
add_action('wp_ajax_hm_quick_add', 'hm_quick_add_handler');

function hm_quick_add_handler() {
    check_ajax_referer('hm_nonce', 'nonce');
    if (!current_user_can('edit_posts')) { wp_send_json_error('Permission denied'); return; }

    $entity = sanitize_text_field($_POST['entity'] ?? '');
    $name   = sanitize_text_field($_POST['name'] ?? '');
    if (empty($name)) { wp_send_json_error('Name is required'); return; }

    $now = current_time('mysql');
    $id  = null;

    switch ($entity) {
        case 'clinic':
            $clinic_data = [
                'clinic_name' => $name,
                'is_active'   => true,
                'created_at'  => $now,
            ];
            if (!empty($_POST['address'])) $clinic_data['address_line1'] = sanitize_text_field($_POST['address']);
            if (!empty($_POST['phone']))   $clinic_data['phone']         = sanitize_text_field($_POST['phone']);
            if (!empty($_POST['email']))   $clinic_data['email']         = sanitize_email($_POST['email']);
            $id = HearMed_DB::insert('hearmed_reference.clinics', $clinic_data);
            if ($id) wp_send_json_success(['id' => $id, 'name' => $name]);
            break;

        case 'role':
            $role_key = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $name));
            $id = HearMed_DB::insert('hearmed_admin.roles', [
                'role_name'    => $role_key,
                'display_name' => $name,
                'is_active'    => true,
                'created_at'   => $now,
            ]);
            if ($id) wp_send_json_success(['id' => $id, 'name' => $name, 'role_name' => $role_key]);
            break;

        case 'appointment_type':
            $appt_data = [
                'type_name'     => $name,
                'service_color' => '#0BB4C4',
                'is_active'     => true,
                'created_at'    => $now,
            ];
            if (!empty($_POST['duration'])) $appt_data['duration_minutes'] = intval($_POST['duration']);
            if (!empty($_POST['colour']))   $appt_data['service_color']    = sanitize_hex_color($_POST['colour']);
            $id = HearMed_DB::insert('hearmed_reference.appointment_types', $appt_data);
            if ($id) wp_send_json_success(['id' => $id, 'name' => $name]);
            break;

        case 'resource_type':
            $exists = HearMed_DB::get_row(
                "SELECT id, type_name FROM hearmed_reference.resource_types WHERE type_name = $1",
                [$name]
            );
            if ($exists) {
                wp_send_json_success(['id' => $name, 'name' => $name]);
                return;
            }
            $id = HearMed_DB::insert('hearmed_reference.resource_types', [
                'type_name'  => $name,
                'is_active'  => true,
                'sort_order' => 0,
                'created_at' => $now,
            ]);
            if ($id) wp_send_json_success(['id' => $name, 'name' => $name]);
            break;

        case 'bundled_category':
            // Ensure table exists
            $check = HearMed_DB::get_var("SELECT to_regclass('hearmed_reference.bundled_categories')");
            if ($check === null) {
                HearMed_DB::get_results("CREATE TABLE IF NOT EXISTS hearmed_reference.bundled_categories (
                    id BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
                    category_name VARCHAR(100) NOT NULL UNIQUE,
                    is_active BOOLEAN DEFAULT TRUE,
                    sort_order INTEGER DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )");
            }
            $id = HearMed_DB::insert('hearmed_reference.bundled_categories', [
                'category_name' => $name,
                'created_at'    => $now,
            ]);
            if ($id) wp_send_json_success(['id' => $name, 'name' => $name]); // value = name not id
            break;

        default:
            // Generic dropdown option — stored in dropdown_options table
            hm_ensure_dropdown_options_table();
            $field = sanitize_key($entity);
            // Check for duplicate
            $exists = HearMed_DB::get_var(
                "SELECT id FROM hearmed_reference.dropdown_options WHERE field_name = $1 AND option_value = $2",
                [$field, $name]
            );
            if ($exists) {
                wp_send_json_success(['id' => $name, 'name' => $name]);
                return;
            }
            $id = HearMed_DB::insert('hearmed_reference.dropdown_options', [
                'field_name'   => $field,
                'option_value' => $name,
                'option_label' => $name,
                'is_active'    => true,
                'created_at'   => $now,
            ]);
            if ($id) wp_send_json_success(['id' => $name, 'name' => $name]); // value = name
            break;
    }

    if (empty($id)) {
        wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
    }
}

/**
 * Ensure the dropdown_options table exists for storing custom dropdown entries.
 */
function hm_ensure_dropdown_options_table() {
    static $checked = false;
    if ($checked) return;
    $checked = true;
    $exists = HearMed_DB::get_var("SELECT to_regclass('hearmed_reference.dropdown_options')");
    if ($exists !== null) return;
    HearMed_DB::get_results("CREATE TABLE IF NOT EXISTS hearmed_reference.dropdown_options (
        id BIGINT PRIMARY KEY GENERATED ALWAYS AS IDENTITY,
        field_name VARCHAR(50) NOT NULL,
        option_value VARCHAR(100) NOT NULL,
        option_label VARCHAR(100) NOT NULL,
        is_active BOOLEAN DEFAULT TRUE,
        sort_order INTEGER DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(field_name, option_value)
    )");
}

/**
 * Helper: get custom dropdown options for a given field.
 * Returns array of option_value strings.
 */
function hm_get_dropdown_options($field_name) {
    hm_ensure_dropdown_options_table();
    $rows = HearMed_DB::get_results(
        "SELECT option_value, option_label FROM hearmed_reference.dropdown_options
         WHERE field_name = $1 AND is_active = true ORDER BY sort_order, option_label",
        [$field_name]
    );
    if (!$rows) return [];
    return array_map(function($r) { return $r->option_value; }, $rows);
}