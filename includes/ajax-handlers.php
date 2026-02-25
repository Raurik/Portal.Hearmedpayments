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

    switch ($entity) {
        case 'manufacturer':
            $id = HearMed_DB::insert('hearmed_reference.manufacturers', [
                'name'       => $name,
                'is_active'  => true,
                'created_at' => $now,
            ]);
            if ($id) wp_send_json_success(['id' => $id, 'name' => $name]);
            break;

        case 'clinic':
            $id = HearMed_DB::insert('hearmed_reference.clinics', [
                'clinic_name' => $name,
                'is_active'   => true,
                'created_at'  => $now,
            ]);
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
            $id = HearMed_DB::insert('hearmed_reference.appointment_types', [
                'type_name'    => $name,
                'service_color'=> '#0BB4C4',
                'is_active'    => true,
                'created_at'   => $now,
            ]);
            if ($id) wp_send_json_success(['id' => $id, 'name' => $name]);
            break;

        default:
            wp_send_json_error('Unknown entity type');
            return;
    }

    if (empty($id)) {
        wp_send_json_error(HearMed_DB::last_error() ?: 'Database error');
    }
}