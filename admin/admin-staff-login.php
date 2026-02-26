<?php
/**
 * HearMed Staff Login
 * Shortcode: [hearmed_staff_login]
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_Staff_Login {

    public function __construct() {
        add_shortcode( 'hearmed_staff_login', [ $this, 'render' ] );
    }

    public function render( $atts = [] ) {
        if ( is_user_logged_in() ) {
            return '<p>You are already logged in.</p>';
        }

        $atts = shortcode_atts(
            [
                'redirect' => home_url( '/' ),
            ],
            $atts
        );

        $errors = [];
        $identifier = '';

        if ( isset( $_POST['hm_staff_login'] ) ) {
            $nonce = $_POST['hm_staff_nonce'] ?? '';
            if ( ! wp_verify_nonce( $nonce, 'hm_staff_login' ) ) {
                $errors[] = 'Invalid session. Please refresh and try again.';
            } else {
                $identifier = sanitize_text_field( $_POST['identifier'] ?? '' );
                $password = (string) ( $_POST['password'] ?? '' );
                $code = sanitize_text_field( $_POST['totp_code'] ?? '' );
                $remember = ! empty( $_POST['remember'] );

                $auth = HearMed_Staff_Auth::get_auth_by_identifier( $identifier );
                if ( ! $auth ) {
                    $errors[] = 'Invalid login details.';
                } elseif ( ! $auth->is_active ) {
                    $errors[] = 'This account is inactive.';
                } elseif ( ! HearMed_Staff_Auth::verify_password( $auth->password_hash, $password ) ) {
                    $errors[] = 'Invalid login details.';
                } elseif ( $auth->two_factor_enabled && ! HearMed_Staff_Auth::verify_totp( $auth->totp_secret, $code ) ) {
                    $errors[] = 'Two-factor code is required.';
                } else {
                    $wp_user_id = (int) $auth->wp_user_id;
                    if ( ! $wp_user_id || ! get_user_by( 'id', $wp_user_id ) ) {
                        $wp_user_id = HearMed_Staff_Auth::ensure_wp_user_for_staff(
                            (int) $auth->staff_id,
                            $auth->email,
                            $auth->username,
                            $auth->role ?? ''
                        );
                    }

                    if ( ! $wp_user_id ) {
                        $errors[] = 'Unable to create a WordPress user for this staff account.';
                        $wp_user_id = 0;
                    } else {
                        wp_set_current_user( (int) $wp_user_id );
                        wp_set_auth_cookie( (int) $wp_user_id, $remember );
                    }
                }

                if ( empty( $errors ) ) {
                    HearMed_DB::update(
                        'hearmed_reference.staff_auth',
                        [
                            'last_login' => current_time( 'mysql' ),
                            'updated_at' => current_time( 'mysql' ),
                        ],
                        [ 'staff_id' => (int) $auth->staff_id ]
                    );

                    $redirect = $_REQUEST['redirect_to'] ?? $atts['redirect'];
                    wp_safe_redirect( esc_url_raw( $redirect ) );
                    exit;
                }
            }
        }

        ob_start(); ?>
        <div class="hm-admin" id="hm-staff-login">
            <a href="<?php echo esc_url(home_url("/admin-console/")); ?>" class="hm-back">← Back</a>
            <div class="hm-admin-hd">
                <h2>Staff Login</h2>
            </div>

            <?php if ( ! empty( $errors ) ): ?>
                <div class="hm-alert hm-alert-error">
                    <?php echo esc_html( implode( ' ', $errors ) ); ?>
                </div>
            <?php endif; ?>

            <form method="post" class="hm-form" style="max-width:520px;">
                <input type="hidden" name="hm_staff_login" value="1">
                <?php echo wp_nonce_field( 'hm_staff_login', 'hm_staff_nonce', true, false ); ?>

                <div class="hm-form-group">
                    <label>Username or Email</label>
                    <input type="text" name="identifier" value="<?php echo esc_attr( $identifier ); ?>" required>
                </div>

                <div class="hm-form-group">
                    <label>Password</label>
                    <input type="password" name="password" required>
                </div>

                <div class="hm-form-group">
                    <label>Two-Factor Code (if enabled)</label>
                    <input type="text" name="totp_code" inputmode="numeric" autocomplete="one-time-code">
                </div>

                <div class="hm-form-row" style="align-items:center;">
                    <label class="hm-toggle-label" style="margin:0;">
                        <input type="checkbox" name="remember" value="1">
                        Remember me
                    </label>
                </div>

                <div class="hm-form-row" style="margin-top:16px;">
                    <button class="hm-btn hm-btn--primary" type="submit">Sign in</button>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}

new HearMed_Staff_Login();
