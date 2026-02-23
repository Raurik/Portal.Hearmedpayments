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