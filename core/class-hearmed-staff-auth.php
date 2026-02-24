<?php
/**
 * HearMed Staff Authentication
 *
 * Custom staff auth in PostgreSQL with optional TOTP 2FA.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class HearMed_Staff_Auth {
    const TOTP_STEP = 30;
    const TOTP_DIGITS = 6;

    public static function default_temp_password() {
        return 'Hearmed1674!';
    }

    public static function get_auth_by_staff_id( $staff_id ) {
        return HearMed_DB::get_row(
            "SELECT staff_id, username, password_hash, temp_password, two_factor_enabled, totp_secret, last_login
             FROM hearmed_reference.staff_auth
             WHERE staff_id = $1",
            [ (int) $staff_id ]
        );
    }

    public static function get_auth_by_identifier( $identifier ) {
        $identifier = strtolower( trim( (string) $identifier ) );
        if ( $identifier === '' ) {
            return null;
        }

        return HearMed_DB::get_row(
            "SELECT a.staff_id, a.username, a.password_hash, a.temp_password, a.two_factor_enabled, a.totp_secret,
                    s.wp_user_id, s.is_active, s.email, s.first_name, s.last_name, s.role
             FROM hearmed_reference.staff_auth a
             JOIN hearmed_reference.staff s ON s.id = a.staff_id
             WHERE lower(a.username) = $1 OR lower(s.email) = $1
             LIMIT 1",
            [ $identifier ]
        );
    }

    public static function ensure_auth_for_staff( $staff_id, $email, $username = null ) {
        $staff_id = (int) $staff_id;
        $username = trim( (string) ( $username ?: $email ) );
        if ( $staff_id <= 0 || $username === '' ) {
            return null;
        }

        $auth = self::get_auth_by_staff_id( $staff_id );
        if ( $auth ) {
            if ( $auth->username !== $username ) {
                HearMed_DB::update(
                    'hearmed_reference.staff_auth',
                    [ 'username' => $username, 'updated_at' => current_time( 'mysql' ) ],
                    [ 'staff_id' => $staff_id ]
                );
            }
            return self::get_auth_by_staff_id( $staff_id );
        }

        // Don't auto-set password - admin must explicitly set on creation
        // password_hash stays NULL until first password is set
        $insert_result = HearMed_DB::insert(
            'hearmed_reference.staff_auth',
            [
                'staff_id' => $staff_id,
                'username' => $username,
                'password_hash' => null,          // No password until set by admin
                'temp_password' => false,         // Not a temp password (no password at all)
                'two_factor_enabled' => false,
                'created_at' => current_time( 'mysql' ),
                'updated_at' => current_time( 'mysql' ),
            ]
        );
        
        if (!$insert_result) {
            error_log('[HearMed_Staff_Auth] Failed to insert staff_auth for staff_id=' . $staff_id . ', error: ' . HearMed_DB::last_error());
            return null;
        }

        $auth = self::get_auth_by_staff_id( $staff_id );
        if (!$auth) {
            error_log('[HearMed_Staff_Auth] Insert succeeded but get_auth_by_staff_id returned null for staff_id=' . $staff_id);
        }
        return $auth;
    }

    public static function set_password( $staff_id, $password, $is_temp = false ) {
        $hash = password_hash( (string) $password, PASSWORD_DEFAULT );
        return HearMed_DB::update(
            'hearmed_reference.staff_auth',
            [
                'password_hash' => $hash,
                'temp_password' => $is_temp ? true : false,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'staff_id' => (int) $staff_id ]
        );
    }

    public static function ensure_wp_user_for_staff( $staff_id, $email, $username, $role_label = '' ) {
        $staff_id = (int) $staff_id;
        $email = sanitize_email( (string) $email );
        $username = sanitize_user( (string) $username, true );

        if ( $staff_id <= 0 || $email === '' ) {
            return 0;
        }

        $staff = HearMed_DB::get_row(
            "SELECT wp_user_id FROM hearmed_reference.staff WHERE id = $1",
            [ $staff_id ]
        );

        if ( $staff && ! empty( $staff->wp_user_id ) ) {
            $user = get_user_by( 'id', (int) $staff->wp_user_id );
            if ( $user ) {
                return (int) $user->ID;
            }
        }

        $candidate = $username !== '' ? $username : sanitize_user( $email, true );
        if ( $candidate === '' ) {
            $candidate = 'staff_' . $staff_id;
        }

        $candidate = self::unique_wp_username( $candidate );
        $password = wp_generate_password( 24, true, true );
        $user_id = wp_create_user( $candidate, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            return 0;
        }

        $role = self::map_role_label_to_wp_role( $role_label );
        $user = get_user_by( 'id', (int) $user_id );
        if ( $user ) {
            $user->set_role( $role );
        }

        HearMed_DB::update(
            'hearmed_reference.staff',
            [ 'wp_user_id' => (int) $user_id, 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $staff_id ]
        );

        return (int) $user_id;
    }

    public static function set_two_factor( $staff_id, $enabled ) {
        $staff_id = (int) $staff_id;
        if ( $enabled ) {
            $auth = self::get_auth_by_staff_id( $staff_id );
            $secret = $auth && ! empty( $auth->totp_secret ) ? $auth->totp_secret : self::generate_totp_secret();
            HearMed_DB::update(
                'hearmed_reference.staff_auth',
                [
                    'two_factor_enabled' => true,
                    'totp_secret' => $secret,
                    'updated_at' => current_time( 'mysql' ),
                ],
                [ 'staff_id' => $staff_id ]
            );
            return $secret;
        }

        HearMed_DB::update(
            'hearmed_reference.staff_auth',
            [
                'two_factor_enabled' => false,
                'totp_secret' => null,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'staff_id' => $staff_id ]
        );

        return null;
    }

    public static function verify_password( $hash, $password ) {
        if ( ! $hash || $password === '' || $password === null ) {
            return false;
        }
        return password_verify( (string) $password, (string) $hash );
    }

    public static function verify_totp( $secret, $code ) {
        $code = preg_replace( '/\D+/', '', (string) $code );
        if ( strlen( $code ) !== self::TOTP_DIGITS ) {
            return false;
        }

        $secret_bin = self::base32_decode( $secret );
        if ( $secret_bin === '' ) {
            return false;
        }

        $time = time();
        $window = 1;
        for ( $i = -$window; $i <= $window; $i++ ) {
            $calc = self::totp_at( $secret_bin, $time + ( $i * self::TOTP_STEP ) );
            if ( hash_equals( $calc, $code ) ) {
                return true;
            }
        }

        return false;
    }

    public static function generate_totp_secret( $length = 20 ) {
        $bytes = random_bytes( $length );
        return self::base32_encode( $bytes );
    }

    private static function unique_wp_username( $base ) {
        $candidate = $base;
        $suffix = 1;
        while ( username_exists( $candidate ) ) {
            $candidate = $base . '_' . $suffix;
            $suffix++;
        }
        return $candidate;
    }

    private static function map_role_label_to_wp_role( $role_label ) {
        $label = strtolower( trim( (string) $role_label ) );
        $mapped = 'administrator';

        if ( $label !== '' ) {
            if ( strpos( $label, 'finance' ) !== false ) {
                $mapped = 'hm_finance';
            } elseif ( strpos( $label, 'admin' ) !== false ) {
                $mapped = 'hm_admin';
            } elseif ( strpos( $label, 'director' ) !== false || strpos( $label, 'owner' ) !== false || strpos( $label, 'clevel' ) !== false ) {
                $mapped = 'hm_clevel';
            } elseif ( strpos( $label, 'reception' ) !== false ) {
                $mapped = 'hm_reception';
            } elseif ( strpos( $label, 'assistant' ) !== false || $label === 'ca' ) {
                $mapped = 'hm_ca';
            } else {
                $mapped = 'hm_dispenser';
            }
        }

        if ( ! get_role( $mapped ) ) {
            $mapped = 'administrator';
        }

        return $mapped;
    }

    private static function totp_at( $secret_bin, $time ) {
        $counter = floor( $time / self::TOTP_STEP );
        $bin = pack( 'N*', 0 ) . pack( 'N*', $counter );
        $hash = hash_hmac( 'sha1', $bin, $secret_bin, true );
        $offset = ord( substr( $hash, -1 ) ) & 0x0F;
        $truncated = substr( $hash, $offset, 4 );
        $value = unpack( 'N', $truncated )[1] & 0x7FFFFFFF;
        $mod = 10 ** self::TOTP_DIGITS;
        return str_pad( (string) ( $value % $mod ), self::TOTP_DIGITS, '0', STR_PAD_LEFT );
    }

    private static function base32_encode( $data ) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $binary = '';
        foreach ( str_split( $data ) as $char ) {
            $binary .= str_pad( decbin( ord( $char ) ), 8, '0', STR_PAD_LEFT );
        }

        $five_bit = str_split( $binary, 5 );
        $encoded = '';
        foreach ( $five_bit as $chunk ) {
            if ( strlen( $chunk ) < 5 ) {
                $chunk = str_pad( $chunk, 5, '0', STR_PAD_RIGHT );
            }
            $encoded .= $alphabet[ bindec( $chunk ) ];
        }

        return $encoded;
    }

    private static function base32_decode( $data ) {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = strtoupper( preg_replace( '/[^A-Z2-7]/', '', (string) $data ) );
        if ( $data === '' ) {
            return '';
        }

        $binary = '';
        foreach ( str_split( $data ) as $char ) {
            $pos = strpos( $alphabet, $char );
            if ( $pos === false ) {
                continue;
            }
            $binary .= str_pad( decbin( $pos ), 5, '0', STR_PAD_LEFT );
        }

        $eight_bit = str_split( $binary, 8 );
        $decoded = '';
        foreach ( $eight_bit as $chunk ) {
            if ( strlen( $chunk ) === 8 ) {
                $decoded .= chr( bindec( $chunk ) );
            }
        }

        return $decoded;
    }
}
