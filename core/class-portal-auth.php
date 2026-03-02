<?php
/**
 * HearMed Portal Auth — Custom Authentication Subsystem
 *
 * Single source of truth for authentication. Replaces all WP auth dependencies.
 *
 * Features:
 *   - Custom session cookies (HMSESS) with hashed tokens in PG
 *   - Per-device 90-day 2FA trust via HMDEVICE cookie
 *   - Role-based access control (RBAC)
 *   - Account lockout after 5 failed attempts
 *   - TOTP 2FA with encrypted secrets
 *   - C-Level impersonation with audit trail
 *   - GDPR-safe audit logging
 *
 * Feature flag: PORTAL_AUTH_V2 constant (true = new system, false = legacy WP)
 *
 * @package HearMed_Portal
 * @since   5.0.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PortalAuth {

    /* ═══════════════════════════════════════════════════════════════════
     * CONSTANTS & CONFIG
     * ═══════════════════════════════════════════════════════════════════ */

    const COOKIE_SESSION      = 'HMSESS';
    const COOKIE_DEVICE       = 'HMDEVICE';
    const COOKIE_CSRF         = 'HMCSRF';

    const SESSION_LIFETIME    = 86400 * 7;   // 7 days
    const DEVICE_LIFETIME     = 86400 * 365; // 1 year
    const TRUST_PERIOD        = 86400 * 90;  // 90 days 2FA trust
    const IMPERSONATE_TTL     = 900;         // 15 minutes
    const LOCKOUT_THRESHOLD   = 5;
    const LOCKOUT_WINDOW      = 900;         // 15 minutes
    const LOCKOUT_DURATION    = 900;         // 15 minutes
    const INVITE_LIFETIME     = 86400;       // 24 hours

    const ALGO_HASH           = 'sha256';

    /* ── Role constants ──────────────────────────────────────────────── */
    const ROLE_CLEVEL    = 'c_level';
    const ROLE_FINANCE   = 'finance';
    const ROLE_DISPENSER = 'dispenser';
    const ROLE_RECEPTION = 'reception';

    /* ── Capability constants ────────────────────────────────────────── */
    const CAP_ADMIN_CONSOLE          = 'admin_console';
    const CAP_APPROVALS              = 'approvals';
    const CAP_FINANCE_AREA           = 'finance_area';
    const CAP_CALENDAR               = 'calendar';
    const CAP_PATIENTS               = 'patients';
    const CAP_AWAITING_FITTING       = 'awaiting_fitting';
    const CAP_KPI_VIEW_SELF          = 'kpi_view_self';
    const CAP_KPI_TEMPLATE_DISPENSER = 'kpi_template_dispenser';
    const CAP_KPI_TEMPLATE_RECEPTION = 'kpi_template_reception';
    const CAP_CHAT_MAIN              = 'chat_main';
    const CAP_CHAT_MEMBER_ONLY       = 'chat_member_only';
    const CAP_REPAIRS                = 'repairs';
    const CAP_STOCK                  = 'stock';
    const CAP_REPORTS                = 'reports';
    const CAP_TEAM_AVAILABILITY      = 'team_availability';
    const CAP_IMPERSONATE            = 'impersonate';
    const CAP_STAFF_MANAGEMENT       = 'staff_management';

    /**
     * Role → capabilities map
     */
    const ROLE_CAPS = [
        self::ROLE_CLEVEL => ['*'], // all capabilities
        self::ROLE_FINANCE => [
            self::CAP_FINANCE_AREA,
            self::CAP_CALENDAR,
            self::CAP_PATIENTS,
            self::CAP_AWAITING_FITTING,
            self::CAP_KPI_VIEW_SELF,
            self::CAP_KPI_TEMPLATE_DISPENSER,
            self::CAP_KPI_TEMPLATE_RECEPTION,
            self::CAP_CHAT_MAIN,
            self::CAP_CHAT_MEMBER_ONLY,
            self::CAP_REPAIRS,
            self::CAP_STOCK,
            self::CAP_REPORTS,
            self::CAP_TEAM_AVAILABILITY,
        ],
        self::ROLE_DISPENSER => [
            self::CAP_CALENDAR,
            self::CAP_PATIENTS,
            self::CAP_AWAITING_FITTING,
            self::CAP_KPI_VIEW_SELF,
            self::CAP_KPI_TEMPLATE_DISPENSER,
            self::CAP_CHAT_MAIN,
            self::CAP_CHAT_MEMBER_ONLY,
            self::CAP_REPAIRS,
            self::CAP_STOCK,
            self::CAP_REPORTS,
            self::CAP_TEAM_AVAILABILITY,
        ],
        self::ROLE_RECEPTION => [
            self::CAP_CALENDAR,
            self::CAP_PATIENTS,
            self::CAP_AWAITING_FITTING,
            self::CAP_KPI_VIEW_SELF,
            self::CAP_KPI_TEMPLATE_RECEPTION,
            self::CAP_CHAT_MAIN,
            self::CAP_CHAT_MEMBER_ONLY,
            self::CAP_REPAIRS,
            self::CAP_STOCK,
            self::CAP_TEAM_AVAILABILITY,
        ],
    ];

    /* ═══════════════════════════════════════════════════════════════════
     * REQUEST-SCOPED STATE
     * ═══════════════════════════════════════════════════════════════════ */

    private static $current_staff    = null;  // staff record (object)
    private static $current_session  = null;  // session record
    private static $actor_staff      = null;  // real user if impersonating
    private static $resolved         = false; // has auth been resolved this request?

    /* ═══════════════════════════════════════════════════════════════════
     * FEATURE FLAG CHECK
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Is V2 auth active?
     */
    public static function is_v2() {
        return defined( 'PORTAL_AUTH_V2' ) && PORTAL_AUTH_V2;
    }

    /* ═══════════════════════════════════════════════════════════════════
     * SESSION RESOLUTION — called once per request
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Resolve the current authenticated staff from the HMSESS cookie.
     * Safe to call multiple times — lazy-loads once.
     * 
     * @return object|null Staff record or null
     */
    public static function resolve() {
        if ( self::$resolved ) return self::$current_staff;
        self::$resolved = true;

        if ( ! self::is_v2() ) {
            // Legacy mode: bridge from WP user to PG staff
            return self::resolve_legacy();
        }

        $token = $_COOKIE[ self::COOKIE_SESSION ] ?? '';
        if ( ! $token || strlen( $token ) < 32 ) return null;

        $hash = hash( self::ALGO_HASH, $token );
        $conn = self::pg();
        if ( ! $conn ) return null;

        $result = pg_query_params( $conn,
            "SELECT ss.*, s.id AS staff_id, s.first_name, s.last_name, s.email, s.role,
                    s.is_active, s.status, s.photo_url, s.employee_number
             FROM hearmed_reference.staff_sessions ss
             JOIN hearmed_reference.staff s ON s.id = ss.staff_id
             WHERE ss.session_token_hash = $1
               AND ss.revoked_at IS NULL
               AND ss.expires_at > NOW()",
            [ $hash ]
        );

        $session = $result ? pg_fetch_object( $result ) : null;
        if ( ! $session ) return null;

        // Staff must be active
        if ( ! $session->is_active || $session->status === 'disabled' ) {
            self::revoke_session( $hash );
            return null;
        }

        // Update last_seen
        pg_query_params( $conn,
            "UPDATE hearmed_reference.staff_sessions SET last_seen_at = NOW() WHERE session_token_hash = $1",
            [ $hash ]
        );

        self::$current_session = $session;

        // Handle impersonation
        if ( $session->is_impersonation && $session->target_staff_id ) {
            $target = self::get_staff_by_id( (int) $session->target_staff_id );
            $actor  = self::get_staff_by_id( (int) $session->actor_staff_id );
            if ( $target && $actor ) {
                self::$current_staff = $target;
                self::$actor_staff   = $actor;
                return self::$current_staff;
            }
        }

        self::$current_staff = (object) [
            'id'              => (int) $session->staff_id,
            'first_name'      => $session->first_name,
            'last_name'       => $session->last_name,
            'email'           => $session->email,
            'role'            => self::normalize_role( $session->role ),
            'is_active'       => $session->is_active,
            'status'          => $session->status ?? 'active',
            'photo_url'       => $session->photo_url,
            'employee_number' => $session->employee_number,
            'display_name'    => trim( $session->first_name . ' ' . $session->last_name ),
        ];

        return self::$current_staff;
    }

    /**
     * Legacy WP→PG bridge — used when PORTAL_AUTH_V2 is false.
     */
    private static function resolve_legacy() {
        if ( ! function_exists('is_user_logged_in') || ! is_user_logged_in() ) return null;

        $wp_user = wp_get_current_user();
        if ( ! $wp_user || ! $wp_user->ID ) return null;

        $conn = self::pg();
        if ( ! $conn ) return null;

        $result = pg_query_params( $conn,
            "SELECT * FROM hearmed_reference.staff WHERE wp_user_id = $1 AND is_active = true LIMIT 1",
            [ $wp_user->ID ]
        );
        $staff = $result ? pg_fetch_object( $result ) : null;
        if ( ! $staff ) return null;

        self::$current_staff = (object) [
            'id'              => (int) $staff->id,
            'first_name'      => $staff->first_name,
            'last_name'       => $staff->last_name,
            'email'           => $staff->email,
            'role'            => self::normalize_role( $staff->role ),
            'is_active'       => $staff->is_active,
            'status'          => $staff->status ?? 'active',
            'photo_url'       => $staff->photo_url ?? '',
            'employee_number' => $staff->employee_number ?? null,
            'display_name'    => trim( $staff->first_name . ' ' . $staff->last_name ),
            'wp_user_id'      => $wp_user->ID,
        ];
        return self::$current_staff;
    }

    /* ═══════════════════════════════════════════════════════════════════
     * CURRENT USER ACCESSORS
     * ═══════════════════════════════════════════════════════════════════ */

    /** @return object|null Current staff record */
    public static function current_user()  { self::resolve(); return self::$current_staff; }

    /** @return int|null Current staff ID */
    public static function current_id()    { $u = self::current_user(); return $u ? $u->id : null; }

    /** @return string|null Current role */
    public static function current_role()  { $u = self::current_user(); return $u ? $u->role : null; }

    /** @return bool Is anyone logged in? */
    public static function is_logged_in()  { return self::current_user() !== null; }

    /** @return object|null Real actor (C-Level) during impersonation, null otherwise */
    public static function actor_user()    { self::resolve(); return self::$actor_staff; }

    /** @return bool Are we in impersonation mode? */
    public static function is_impersonating() { return self::actor_user() !== null; }

    /* ═══════════════════════════════════════════════════════════════════
     * RBAC — CAPABILITY CHECKS
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Check if current user has a capability.
     *
     * @param  string $cap Capability constant (e.g. 'calendar')
     * @return bool
     */
    public static function can( $cap ) {
        $role = self::current_role();
        if ( ! $role ) return false;
        $caps = self::ROLE_CAPS[ $role ] ?? [];
        return in_array( '*', $caps, true ) || in_array( $cap, $caps, true );
    }

    /**
     * Require capability — dies with 403 if denied.
     *
     * @param string $cap
     * @param string $context Optional context for audit
     */
    public static function require_cap( $cap, $context = '' ) {
        if ( ! self::can( $cap ) ) {
            self::audit( 'ACCESS_DENIED', null, [ 'cap' => $cap, 'context' => $context ] );
            if ( wp_doing_ajax() ) {
                wp_send_json_error( [ 'message' => 'Access denied.' ], 403 );
            }
            wp_die( 'Access denied.', 'Forbidden', [ 'response' => 403 ] );
        }
    }

    /**
     * Require authenticated user — dies if not logged in.
     */
    public static function require_auth() {
        if ( ! self::is_logged_in() ) {
            if ( wp_doing_ajax() ) {
                wp_send_json_error( [ 'message' => 'Not authenticated.' ], 401 );
            }
            wp_redirect( home_url( '/login/' ) );
            exit;
        }
    }

    /**
     * Is current user at admin/C-Level/finance level?
     */
    public static function is_admin_level() {
        $role = self::current_role();
        return in_array( $role, [ self::ROLE_CLEVEL, self::ROLE_FINANCE ], true );
    }

    /**
     * Is current user C-Level?
     */
    public static function is_clevel() {
        return self::current_role() === self::ROLE_CLEVEL;
    }

    /* ═══════════════════════════════════════════════════════════════════
     * LOGIN FLOW
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Attempt login with email/username + password.
     *
     * @param  string $identifier Email or username
     * @param  string $password   Plaintext password
     * @return array  ['status' => 'success'|'2fa_required'|'2fa_setup'|'locked'|'error', ...]
     */
    public static function login( $identifier, $password ) {
        $conn = self::pg();
        if ( ! $conn ) return [ 'status' => 'error', 'message' => 'Database unavailable.' ];

        $identifier = strtolower( trim( $identifier ) );

        // Look up staff + auth record
        $result = pg_query_params( $conn,
            "SELECT s.id AS staff_id, s.first_name, s.last_name, s.email, s.role,
                    s.is_active, s.status, s.wp_user_id,
                    sa.password_hash, sa.two_factor_enabled, sa.totp_secret, sa.totp_secret_enc,
                    sa.failed_login_count, sa.last_failed_login_at, sa.locked_until,
                    sa.temp_password
             FROM hearmed_reference.staff s
             JOIN hearmed_reference.staff_auth sa ON sa.staff_id = s.id
             WHERE LOWER(sa.username) = $1 OR LOWER(s.email) = $1
             LIMIT 1",
            [ $identifier ]
        );
        $auth = $result ? pg_fetch_object( $result ) : null;

        if ( ! $auth ) {
            // Constant-time: still hash something to prevent timing attacks
            password_verify( $password, '$2y$12$dummyhashforconstanttimecheck000000000000000' );
            self::audit( 'LOGIN_FAIL', null, [ 'identifier' => $identifier, 'reason' => 'not_found' ] );
            return [ 'status' => 'error', 'message' => 'Invalid login details.' ];
        }

        $staff_id = (int) $auth->staff_id;

        // Check account status
        if ( ! $auth->is_active || ($auth->status ?? 'active') === 'disabled' ) {
            self::audit( 'LOGIN_FAIL', $staff_id, [ 'reason' => 'inactive' ] );
            return [ 'status' => 'error', 'message' => 'This account is inactive.' ];
        }

        // Check lockout
        if ( $auth->locked_until && strtotime( $auth->locked_until ) > time() ) {
            self::audit( 'LOGIN_LOCKED', $staff_id );
            $remaining = strtotime( $auth->locked_until ) - time();
            return [ 'status' => 'locked', 'message' => 'Account locked. Try again in ' . ceil( $remaining / 60 ) . ' minutes.' ];
        }

        // Verify password
        if ( ! password_verify( $password, $auth->password_hash ) ) {
            self::increment_failed_login( $staff_id, $auth );
            self::audit( 'LOGIN_FAIL', $staff_id, [ 'reason' => 'bad_password' ] );
            return [ 'status' => 'error', 'message' => 'Invalid login details.' ];
        }

        // Password correct — reset lockout counters
        pg_query_params( $conn,
            "UPDATE hearmed_reference.staff_auth
             SET failed_login_count = 0, last_failed_login_at = NULL, locked_until = NULL, updated_at = NOW()
             WHERE staff_id = $1",
            [ $staff_id ]
        );

        // Determine 2FA state
        $device_token = self::get_or_create_device_token();
        $device       = self::get_device( $staff_id, $device_token );
        $needs_2fa    = false;

        if ( ! $auth->two_factor_enabled ) {
            // Force 2FA setup on first login
            return [
                'status'   => '2fa_setup',
                'staff_id' => $staff_id,
                'message'  => '2FA setup required.',
            ];
        }

        // Check if device is trusted
        if ( ! $device || ! $device->trusted_until || strtotime( $device->trusted_until ) < time() ) {
            $needs_2fa = true;
        }

        if ( $needs_2fa ) {
            // Store pending login in transient (short-lived, 5 min)
            $pending_token = bin2hex( random_bytes( 32 ) );
            set_transient( 'hm_2fa_pending_' . hash( 'sha256', $pending_token ), [
                'staff_id'     => $staff_id,
                'device_token' => $device_token,
                'created_at'   => time(),
            ], 300 );

            self::audit( '2FA_CHALLENGE', $staff_id );
            return [
                'status'        => '2fa_required',
                'pending_token' => $pending_token,
                'staff_id'      => $staff_id,
                'message'       => 'Enter your 2FA code.',
            ];
        }

        // No 2FA needed — create session
        $session_result = self::create_session( $staff_id, $device_token );
        self::audit( 'LOGIN_SUCCESS', $staff_id, [ 'device_trusted' => true ] );

        return [
            'status'   => 'success',
            'staff_id' => $staff_id,
        ];
    }

    /**
     * Verify 2FA code and complete login.
     *
     * @param  string $pending_token From the 2FA challenge
     * @param  string $code          TOTP code
     * @return array
     */
    public static function verify_2fa( $pending_token, $code ) {
        $key     = 'hm_2fa_pending_' . hash( 'sha256', $pending_token );
        $pending = get_transient( $key );
        if ( ! $pending ) {
            return [ 'status' => 'error', 'message' => 'Session expired. Please login again.' ];
        }

        $staff_id     = (int) $pending['staff_id'];
        $device_token = $pending['device_token'];

        // Get TOTP secret
        $secret = self::get_totp_secret( $staff_id );
        if ( ! $secret ) {
            return [ 'status' => 'error', 'message' => 'Two-factor not configured.' ];
        }

        if ( ! HearMed_Staff_Auth::verify_totp( $secret, $code ) ) {
            self::audit( '2FA_FAIL', $staff_id );
            return [ 'status' => 'error', 'message' => 'Invalid code. Please try again.' ];
        }

        // 2FA success — trust device for 90 days
        delete_transient( $key );
        self::trust_device( $staff_id, $device_token );
        self::create_session( $staff_id, $device_token );

        self::audit( '2FA_SUCCESS', $staff_id );
        self::audit( 'DEVICE_TRUSTED', $staff_id, [ 'device_token' => substr( $device_token, 0, 8 ) . '...' ] );
        self::audit( 'LOGIN_SUCCESS', $staff_id );

        return [ 'status' => 'success', 'staff_id' => $staff_id ];
    }

    /**
     * Set up 2FA for a staff member (first time).
     *
     * @param  int    $staff_id
     * @param  string $code Verification OTP
     * @param  string $secret Base32 secret (generated client-side QR was shown)
     * @param  string $pending_token From the login flow
     * @return array
     */
    public static function setup_2fa( $staff_id, $code, $secret, $pending_token = '' ) {
        // Verify the code against the provided secret
        if ( ! HearMed_Staff_Auth::verify_totp( $secret, $code ) ) {
            return [ 'status' => 'error', 'message' => 'Invalid code. Scan the QR code and try again.' ];
        }

        // Encrypt and store secret
        $encrypted = self::encrypt_totp_secret( $secret );
        $conn = self::pg();
        pg_query_params( $conn,
            "UPDATE hearmed_reference.staff_auth
             SET two_factor_enabled = true, totp_secret_enc = $2, totp_secret = '', updated_at = NOW()
             WHERE staff_id = $1",
            [ $staff_id, $encrypted ]
        );

        // Trust device & create session
        $device_token = self::get_or_create_device_token();
        self::trust_device( $staff_id, $device_token );
        self::create_session( $staff_id, $device_token );

        self::audit( '2FA_SETUP', $staff_id );
        self::audit( '2FA_SUCCESS', $staff_id );
        self::audit( 'DEVICE_TRUSTED', $staff_id );
        self::audit( 'LOGIN_SUCCESS', $staff_id );

        if ( $pending_token ) {
            delete_transient( 'hm_2fa_pending_' . hash( 'sha256', $pending_token ) );
        }

        return [ 'status' => 'success', 'staff_id' => $staff_id ];
    }

    /**
     * Generate a new TOTP secret and return QR data.
     *
     * @param  int $staff_id
     * @return array ['secret' => ..., 'qr_uri' => ...]
     */
    public static function generate_2fa_secret( $staff_id ) {
        $secret = HearMed_Staff_Auth::generate_totp_secret();
        $staff  = self::get_staff_by_id( $staff_id );
        $name   = $staff ? urlencode( $staff->email ) : 'user';
        $issuer = urlencode( 'HearMed Portal' );
        $uri    = "otpauth://totp/{$issuer}:{$name}?secret={$secret}&issuer={$issuer}&digits=6&period=30";

        return [
            'secret' => $secret,
            'qr_uri' => $uri,
        ];
    }

    /**
     * Logout — revoke current session and clear cookies.
     */
    public static function logout() {
        $token = $_COOKIE[ self::COOKIE_SESSION ] ?? '';
        if ( $token ) {
            $hash = hash( self::ALGO_HASH, $token );
            self::revoke_session( $hash );
            self::audit( 'LOGOUT', self::current_id() );
        }
        self::clear_session_cookie();

        // Also log out of WP if still bridged
        if ( function_exists('wp_logout') ) {
            wp_logout();
        }
    }

    /* ═══════════════════════════════════════════════════════════════════
     * SESSION MANAGEMENT
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Create a new session and set cookie.
     */
    public static function create_session( $staff_id, $device_token, $is_impersonation = false, $actor_id = null, $target_id = null ) {
        $conn = self::pg();
        if ( ! $conn ) return false;

        $token = bin2hex( random_bytes( 32 ) );
        $hash  = hash( self::ALGO_HASH, $token );

        pg_query_params( $conn,
            "INSERT INTO hearmed_reference.staff_sessions
                (staff_id, device_token, session_token_hash, expires_at, ip, user_agent, is_impersonation, actor_staff_id, target_staff_id)
             VALUES ($1, $2, $3, NOW() + interval '" . self::SESSION_LIFETIME . " seconds', $4, $5, $6, $7, $8)",
            [
                $staff_id,
                $device_token,
                $hash,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512 ),
                $is_impersonation ? 't' : 'f',
                $actor_id,
                $target_id,
            ]
        );

        // Update last_login
        pg_query_params( $conn,
            "UPDATE hearmed_reference.staff_auth SET last_login = NOW(), updated_at = NOW() WHERE staff_id = $1",
            [ $staff_id ]
        );

        self::set_session_cookie( $token );

        // Bridge to WP session if legacy mode coexists
        if ( ! self::is_v2() || defined('PORTAL_AUTH_BRIDGE_WP') ) {
            self::bridge_wp_login( $staff_id );
        }

        return $token;
    }

    /**
     * Revoke a session by token hash.
     */
    public static function revoke_session( $hash ) {
        $conn = self::pg();
        if ( ! $conn ) return;
        pg_query_params( $conn,
            "UPDATE hearmed_reference.staff_sessions SET revoked_at = NOW() WHERE session_token_hash = $1",
            [ $hash ]
        );
    }

    /**
     * Revoke all sessions for a staff member.
     */
    public static function revoke_all_sessions( $staff_id ) {
        $conn = self::pg();
        if ( ! $conn ) return;
        pg_query_params( $conn,
            "UPDATE hearmed_reference.staff_sessions SET revoked_at = NOW() WHERE staff_id = $1 AND revoked_at IS NULL",
            [ $staff_id ]
        );
    }

    /**
     * Set the session cookie.
     */
    private static function set_session_cookie( $token ) {
        $secure   = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' );
        $options  = [
            'expires'  => time() + self::SESSION_LIFETIME,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly'  => true,
            'samesite'  => 'Lax',
        ];
        setcookie( self::COOKIE_SESSION, $token, $options );
        $_COOKIE[ self::COOKIE_SESSION ] = $token; // make available this request
    }

    /**
     * Clear the session cookie.
     */
    private static function clear_session_cookie() {
        setcookie( self::COOKIE_SESSION, '', [
            'expires' => time() - 86400,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ] );
        unset( $_COOKIE[ self::COOKIE_SESSION ] );
    }

    /* ═══════════════════════════════════════════════════════════════════
     * DEVICE MANAGEMENT
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Get or create the device token cookie.
     */
    public static function get_or_create_device_token() {
        $token = $_COOKIE[ self::COOKIE_DEVICE ] ?? '';
        if ( $token && strlen( $token ) >= 32 ) return $token;

        $token = bin2hex( random_bytes( 32 ) );
        $secure = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' );
        setcookie( self::COOKIE_DEVICE, $token, [
            'expires'  => time() + self::DEVICE_LIFETIME,
            'path'     => '/',
            'secure'   => $secure,
            'httponly'  => true,
            'samesite'  => 'Lax',
        ] );
        $_COOKIE[ self::COOKIE_DEVICE ] = $token;
        return $token;
    }

    /**
     * Get device record for staff + device_token.
     */
    public static function get_device( $staff_id, $device_token ) {
        $conn = self::pg();
        if ( ! $conn ) return null;
        $result = pg_query_params( $conn,
            "SELECT * FROM hearmed_reference.staff_devices
             WHERE staff_id = $1 AND device_token = $2 AND revoked_at IS NULL
             LIMIT 1",
            [ $staff_id, $device_token ]
        );
        return $result ? pg_fetch_object( $result ) : null;
    }

    /**
     * Trust a device for 90 days.
     */
    public static function trust_device( $staff_id, $device_token ) {
        $conn = self::pg();
        if ( ! $conn ) return;

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512 );

        // Upsert device
        pg_query_params( $conn,
            "INSERT INTO hearmed_reference.staff_devices (staff_id, device_token, last_seen_ip, user_agent, last_2fa_at, trusted_until)
             VALUES ($1, $2, $3, $4, NOW(), NOW() + interval '" . self::TRUST_PERIOD . " seconds')
             ON CONFLICT (staff_id, device_token)
             DO UPDATE SET last_seen_at = NOW(), last_seen_ip = $3, user_agent = $4,
                           last_2fa_at = NOW(), trusted_until = NOW() + interval '" . self::TRUST_PERIOD . " seconds'",
            [ $staff_id, $device_token, $ip, $ua ]
        );
    }

    /* ═══════════════════════════════════════════════════════════════════
     * IMPERSONATION (C-Level only)
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Start impersonating another staff member.
     * Requires C-Level role, step-up 2FA, and a reason.
     *
     * @param  int    $target_staff_id
     * @param  string $reason
     * @param  string $totp_code Step-up 2FA code
     * @return array
     */
    public static function start_impersonation( $target_staff_id, $reason, $totp_code ) {
        $actor = self::current_user();
        if ( ! $actor || $actor->role !== self::ROLE_CLEVEL ) {
            return [ 'status' => 'error', 'message' => 'Only C-Level can impersonate.' ];
        }

        if ( self::is_impersonating() ) {
            return [ 'status' => 'error', 'message' => 'Already impersonating. End current session first.' ];
        }

        // Step-up 2FA verification
        $secret = self::get_totp_secret( $actor->id );
        if ( ! $secret || ! HearMed_Staff_Auth::verify_totp( $secret, $totp_code ) ) {
            self::audit( '2FA_FAIL', $actor->id, [ 'context' => 'impersonation_stepup' ] );
            return [ 'status' => 'error', 'message' => 'Invalid 2FA code.' ];
        }

        $target = self::get_staff_by_id( $target_staff_id );
        if ( ! $target ) {
            return [ 'status' => 'error', 'message' => 'Target staff not found.' ];
        }

        $conn = self::pg();

        // Create impersonation record
        pg_query_params( $conn,
            "INSERT INTO hearmed_admin.impersonation_sessions
                (actor_staff_id, target_staff_id, reason, expires_at)
             VALUES ($1, $2, $3, NOW() + interval '" . self::IMPERSONATE_TTL . " seconds')",
            [ $actor->id, $target_staff_id, $reason ]
        );

        // Create impersonation session
        $device_token = self::get_or_create_device_token();
        self::create_session( $target_staff_id, $device_token, true, $actor->id, $target_staff_id );

        self::audit( 'IMPERSONATION_START', $actor->id, [
            'target_staff_id' => $target_staff_id,
            'target_name'     => $target->first_name . ' ' . $target->last_name,
            'reason'          => $reason,
        ] );

        return [ 'status' => 'success', 'message' => 'Now impersonating ' . $target->first_name . ' ' . $target->last_name ];
    }

    /**
     * End impersonation — restore actor's own session.
     */
    public static function end_impersonation() {
        $actor = self::actor_user();
        if ( ! $actor ) return [ 'status' => 'error', 'message' => 'Not impersonating.' ];

        $target = self::current_user();
        self::audit( 'IMPERSONATION_END', $actor->id, [
            'target_staff_id' => $target ? $target->id : null,
        ] );

        // Close the impersonation session record
        $conn = self::pg();
        pg_query_params( $conn,
            "UPDATE hearmed_admin.impersonation_sessions SET ended_at = NOW()
             WHERE actor_staff_id = $1 AND ended_at IS NULL",
            [ $actor->id ]
        );

        // Revoke current session and create a new one for the actor
        $token = $_COOKIE[ self::COOKIE_SESSION ] ?? '';
        if ( $token ) {
            self::revoke_session( hash( self::ALGO_HASH, $token ) );
        }
        $device_token = self::get_or_create_device_token();
        self::create_session( $actor->id, $device_token );

        // Reset request state
        self::$current_staff = null;
        self::$actor_staff   = null;
        self::$resolved      = false;

        return [ 'status' => 'success' ];
    }

    /* ═══════════════════════════════════════════════════════════════════
     * PASSWORD MANAGEMENT
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Hash a password (Argon2id if available, else bcrypt 12).
     */
    public static function hash_password( $password ) {
        if ( defined( 'PASSWORD_ARGON2ID' ) ) {
            return password_hash( $password, PASSWORD_ARGON2ID );
        }
        return password_hash( $password, PASSWORD_BCRYPT, [ 'cost' => 12 ] );
    }

    /**
     * Set password for a staff member.
     */
    public static function set_password( $staff_id, $password ) {
        $hash = self::hash_password( $password );
        $conn = self::pg();
        pg_query_params( $conn,
            "UPDATE hearmed_reference.staff_auth
             SET password_hash = $2, temp_password = false, password_changed_at = NOW(), updated_at = NOW()
             WHERE staff_id = $1",
            [ $staff_id, $hash ]
        );
    }

    /* ═══════════════════════════════════════════════════════════════════
     * TOTP SECRET ENCRYPTION
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Encrypt TOTP secret for storage.
     */
    public static function encrypt_totp_secret( $secret ) {
        $key = self::get_encryption_key();
        $nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $cipher = sodium_crypto_secretbox( $secret, $nonce, $key );
        return base64_encode( $nonce . $cipher );
    }

    /**
     * Decrypt TOTP secret from storage.
     */
    public static function decrypt_totp_secret( $encrypted ) {
        $key = self::get_encryption_key();
        $decoded = base64_decode( $encrypted );
        $nonce = substr( $decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $cipher = substr( $decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
        $plain = sodium_crypto_secretbox_open( $cipher, $nonce, $key );
        if ( $plain === false ) {
            error_log( '[PortalAuth] Failed to decrypt TOTP secret.' );
            return null;
        }
        return $plain;
    }

    /**
     * Get TOTP secret for a staff member (decrypt if encrypted, fallback to legacy).
     */
    public static function get_totp_secret( $staff_id ) {
        $conn = self::pg();
        $result = pg_query_params( $conn,
            "SELECT totp_secret, totp_secret_enc FROM hearmed_reference.staff_auth WHERE staff_id = $1",
            [ $staff_id ]
        );
        $row = $result ? pg_fetch_object( $result ) : null;
        if ( ! $row ) return null;

        // Prefer encrypted
        if ( ! empty( $row->totp_secret_enc ) ) {
            return self::decrypt_totp_secret( $row->totp_secret_enc );
        }
        // Legacy plaintext fallback
        return $row->totp_secret ?: null;
    }

    /**
     * Get encryption key from env/constant.
     */
    private static function get_encryption_key() {
        if ( defined( 'HEARMED_TOTP_KEY' ) ) {
            $key = HEARMED_TOTP_KEY;
        } else {
            $key = getenv( 'HEARMED_TOTP_KEY' );
        }
        if ( ! $key ) {
            // Fallback: derive from WP AUTH_KEY (not ideal but functional)
            $key = defined('AUTH_KEY') ? AUTH_KEY : 'hearmed-fallback-key-please-set-HEARMED_TOTP_KEY';
        }
        // Ensure 32 bytes
        return hash( 'sha256', $key, true );
    }

    /* ═══════════════════════════════════════════════════════════════════
     * LOCKOUT MANAGEMENT
     * ═══════════════════════════════════════════════════════════════════ */

    private static function increment_failed_login( $staff_id, $auth ) {
        $conn = self::pg();
        $count = (int) ($auth->failed_login_count ?? 0) + 1;
        $lock = null;

        // Check if within the lockout window
        $last_fail = $auth->last_failed_login_at ? strtotime( $auth->last_failed_login_at ) : 0;
        if ( $last_fail && ( time() - $last_fail > self::LOCKOUT_WINDOW ) ) {
            // Reset counter — failures were outside window
            $count = 1;
        }

        if ( $count >= self::LOCKOUT_THRESHOLD ) {
            $lock = date( 'Y-m-d H:i:s', time() + self::LOCKOUT_DURATION );
            self::audit( 'ACCOUNT_LOCKED', $staff_id, [ 'locked_until' => $lock ] );
        }

        pg_query_params( $conn,
            "UPDATE hearmed_reference.staff_auth
             SET failed_login_count = $2, last_failed_login_at = NOW(), locked_until = $3, updated_at = NOW()
             WHERE staff_id = $1",
            [ $staff_id, $count, $lock ]
        );
    }

    /* ═══════════════════════════════════════════════════════════════════
     * STAFF INVITES
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Create a one-time invite for a staff member.
     *
     * @return array ['token' => ..., 'url' => ..., 'expires_at' => ...]
     */
    public static function create_invite( $staff_id ) {
        $conn = self::pg();
        $token = bin2hex( random_bytes( 32 ) );
        $hash  = hash( self::ALGO_HASH, $token );
        $expires = date( 'Y-m-d H:i:s', time() + self::INVITE_LIFETIME );

        pg_query_params( $conn,
            "INSERT INTO hearmed_reference.staff_invites (staff_id, token_hash, expires_at) VALUES ($1, $2, $3)",
            [ $staff_id, $hash, $expires ]
        );

        $url = home_url( '/login/?invite=' . $token );

        self::audit( 'STAFF_INVITED', self::current_id(), [ 'target_staff_id' => $staff_id ] );

        return [
            'token'      => $token,
            'url'        => $url,
            'expires_at' => $expires,
        ];
    }

    /**
     * Validate an invite token.
     *
     * @return object|null Invite record with staff_id
     */
    public static function validate_invite( $token ) {
        $conn = self::pg();
        $hash = hash( self::ALGO_HASH, $token );
        $result = pg_query_params( $conn,
            "SELECT si.*, s.email, s.first_name, s.last_name, s.role
             FROM hearmed_reference.staff_invites si
             JOIN hearmed_reference.staff s ON s.id = si.staff_id
             WHERE si.token_hash = $1 AND si.used_at IS NULL AND si.expires_at > NOW()
             LIMIT 1",
            [ $hash ]
        );
        return $result ? pg_fetch_object( $result ) : null;
    }

    /**
     * Accept an invite — set password and mark used.
     */
    public static function accept_invite( $token, $password ) {
        $invite = self::validate_invite( $token );
        if ( ! $invite ) return [ 'status' => 'error', 'message' => 'Invalid or expired invite.' ];

        $staff_id = (int) $invite->staff_id;
        self::set_password( $staff_id, $password );

        $conn = self::pg();
        pg_query_params( $conn,
            "UPDATE hearmed_reference.staff_invites SET used_at = NOW() WHERE token_hash = $1",
            [ hash( self::ALGO_HASH, $token ) ]
        );

        self::audit( 'INVITE_ACCEPTED', $staff_id );

        // Return 2FA setup required
        return [
            'status'   => '2fa_setup',
            'staff_id' => $staff_id,
            'message'  => 'Password set. Now set up 2FA.',
        ];
    }

    /* ═══════════════════════════════════════════════════════════════════
     * CSRF PROTECTION
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Generate CSRF token tied to session.
     */
    public static function csrf_token() {
        $session_token = $_COOKIE[ self::COOKIE_SESSION ] ?? '';
        $key = $session_token . ( defined('AUTH_SALT') ? AUTH_SALT : 'hm-csrf-salt' );
        return hash_hmac( 'sha256', 'hm-csrf', $key );
    }

    /**
     * Verify CSRF token.
     */
    public static function verify_csrf( $token = null ) {
        if ( $token === null ) {
            $token = $_POST['_hm_csrf'] ?? $_SERVER['HTTP_X_HM_CSRF'] ?? '';
        }
        return hash_equals( self::csrf_token(), $token );
    }

    /**
     * Require valid CSRF — dies if invalid.
     */
    public static function require_csrf() {
        if ( ! self::verify_csrf() ) {
            if ( wp_doing_ajax() ) {
                wp_send_json_error( [ 'message' => 'Invalid CSRF token.' ], 403 );
            }
            wp_die( 'Invalid request.', 'Forbidden', [ 'response' => 403 ] );
        }
    }

    /* ═══════════════════════════════════════════════════════════════════
     * AUDIT LOGGING (GDPR-safe)
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Write an auth audit log entry.
     *
     * @param string   $action          Action name (e.g. LOGIN_SUCCESS)
     * @param int|null $target_staff_id Optional target
     * @param array    $meta            Optional metadata (NEVER passwords/OTPs)
     */
    public static function audit( $action, $target_staff_id = null, $meta = [] ) {
        $conn = self::pg();
        if ( ! $conn ) return;

        $actor_id = null;
        if ( self::$current_staff ) {
            $actor_id = self::$actor_staff ? self::$actor_staff->id : self::$current_staff->id;
        }

        // If actor unknown but target known, use target as actor for self-actions
        if ( ! $actor_id && $target_staff_id ) {
            $actor_id = $target_staff_id;
        }

        // Sanitize meta — never store secrets
        unset( $meta['password'], $meta['totp_code'], $meta['secret'], $meta['token'] );

        pg_query_params( $conn,
            "INSERT INTO hearmed_admin.auth_audit_log
                (actor_staff_id, action, target_staff_id, meta, ip, user_agent)
             VALUES ($1, $2, $3, $4, $5, $6)",
            [
                $actor_id,
                $action,
                $target_staff_id,
                json_encode( $meta ),
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 512 ),
            ]
        );
    }

    /* ═══════════════════════════════════════════════════════════════════
     * ADMIN: STAFF MANAGEMENT HELPERS
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Disable a staff account. Revokes all sessions.
     */
    public static function disable_staff( $staff_id ) {
        $conn = self::pg();
        pg_query_params( $conn,
            "UPDATE hearmed_reference.staff SET status = 'disabled', is_active = false, updated_at = NOW() WHERE id = $1",
            [ $staff_id ]
        );
        self::revoke_all_sessions( $staff_id );
        self::audit( 'STAFF_DISABLED', $staff_id );
    }

    /**
     * Reset 2FA for a staff member (admin action, audited).
     */
    public static function reset_2fa( $staff_id ) {
        $conn = self::pg();
        pg_query_params( $conn,
            "UPDATE hearmed_reference.staff_auth
             SET two_factor_enabled = false, totp_secret = '', totp_secret_enc = NULL, updated_at = NOW()
             WHERE staff_id = $1",
            [ $staff_id ]
        );
        // Revoke all device trust
        pg_query_params( $conn,
            "UPDATE hearmed_reference.staff_devices SET trusted_until = NULL, revoked_at = NOW() WHERE staff_id = $1",
            [ $staff_id ]
        );
        self::audit( '2FA_RESET', $staff_id );
    }

    /**
     * Get active sessions for a staff member.
     */
    public static function get_sessions( $staff_id ) {
        $conn = self::pg();
        $result = pg_query_params( $conn,
            "SELECT id, device_token, created_at, expires_at, last_seen_at, ip, user_agent, is_impersonation
             FROM hearmed_reference.staff_sessions
             WHERE staff_id = $1 AND revoked_at IS NULL AND expires_at > NOW()
             ORDER BY last_seen_at DESC",
            [ $staff_id ]
        );
        $sessions = [];
        while ( $row = pg_fetch_object( $result ) ) $sessions[] = $row;
        return $sessions;
    }

    /**
     * Get devices for a staff member.
     */
    public static function get_devices( $staff_id ) {
        $conn = self::pg();
        $result = pg_query_params( $conn,
            "SELECT * FROM hearmed_reference.staff_devices
             WHERE staff_id = $1 AND revoked_at IS NULL
             ORDER BY last_seen_at DESC",
            [ $staff_id ]
        );
        $devices = [];
        while ( $row = pg_fetch_object( $result ) ) $devices[] = $row;
        return $devices;
    }

    /**
     * DSAR: Export all auth data for a staff member.
     */
    public static function export_staff_data( $staff_id ) {
        $conn = self::pg();
        $data = [ 'staff_id' => $staff_id ];

        // Profile
        $r = pg_query_params( $conn, "SELECT * FROM hearmed_reference.staff WHERE id = $1", [ $staff_id ] );
        $data['profile'] = $r ? pg_fetch_assoc( $r ) : null;

        // Auth (exclude sensitive fields)
        $r = pg_query_params( $conn,
            "SELECT staff_id, username, two_factor_enabled, last_login, created_at, password_changed_at FROM hearmed_reference.staff_auth WHERE staff_id = $1",
            [ $staff_id ] );
        $data['auth'] = $r ? pg_fetch_assoc( $r ) : null;

        // Devices
        $data['devices'] = self::get_devices( $staff_id );

        // Audit log
        $r = pg_query_params( $conn,
            "SELECT * FROM hearmed_admin.auth_audit_log WHERE actor_staff_id = $1 OR target_staff_id = $1 ORDER BY created_at DESC LIMIT 1000",
            [ $staff_id ] );
        $data['audit_log'] = [];
        while ( $row = pg_fetch_assoc( $r ) ) $data['audit_log'][] = $row;

        return $data;
    }

    /* ═══════════════════════════════════════════════════════════════════
     * DATA SCOPING HELPERS
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Get SQL WHERE clause for KPI scoping. Non-admin sees only their own.
     */
    public static function kpi_scope_sql( $staff_id_col = 'staff_id' ) {
        if ( self::is_admin_level() ) return '1=1';
        $id = self::current_id();
        return $id ? "$staff_id_col = " . intval( $id ) : '1=0';
    }

    /**
     * Get chat channel IDs the current user is a member of.
     */
    public static function chat_visible_channels() {
        $user = self::current_user();
        if ( ! $user ) return [];

        $conn = self::pg();
        // Everyone sees the main company channel
        $result = pg_query_params( $conn,
            "SELECT DISTINCT cc.id
             FROM hearmed_communication.chat_channels cc
             LEFT JOIN hearmed_communication.chat_channel_members ccm ON ccm.channel_id = cc.id
             WHERE cc.channel_type = 'company'
                OR ccm.staff_id = $1",
            [ $user->id ]
        );
        $ids = [];
        while ( $row = pg_fetch_object( $result ) ) $ids[] = (int) $row->id;
        return $ids;
    }

    /**
     * Check if a report is allowed for the current role.
     */
    public static function can_view_report( $report_key ) {
        if ( self::is_clevel() ) return true;
        $role = self::current_role();
        if ( ! $role ) return false;

        $conn = self::pg();
        $result = pg_query_params( $conn,
            "SELECT allowed FROM hearmed_admin.report_permissions WHERE report_key = $1 AND role = $2 LIMIT 1",
            [ $report_key, $role ]
        );
        $row = $result ? pg_fetch_object( $result ) : null;
        return $row ? (bool) $row->allowed : false;
    }

    /* ═══════════════════════════════════════════════════════════════════
     * WP BRIDGE — for transition period
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Bridge portal auth to WP session (transition period).
     * Creates/finds WP user and sets WP auth cookie.
     */
    private static function bridge_wp_login( $staff_id ) {
        $staff = self::get_staff_by_id( $staff_id );
        if ( ! $staff ) return;

        $wp_user_id = HearMed_Staff_Auth::ensure_wp_user_for_staff(
            $staff_id,
            $staff->email,
            strtolower( str_replace( ' ', '.', trim( $staff->first_name . '.' . $staff->last_name ) ) ),
            $staff->role
        );

        if ( $wp_user_id ) {
            wp_set_current_user( $wp_user_id );
            wp_set_auth_cookie( $wp_user_id, true );
        }
    }

    /* ═══════════════════════════════════════════════════════════════════
     * UTILITY
     * ═══════════════════════════════════════════════════════════════════ */

    /**
     * Get PG connection.
     */
    private static function pg() {
        return HearMed_DB::instance() ? HearMed_DB::get_connection() : null;
    }

    /**
     * Get staff record by ID.
     */
    public static function get_staff_by_id( $staff_id ) {
        $conn = self::pg();
        if ( ! $conn ) return null;
        $result = pg_query_params( $conn,
            "SELECT * FROM hearmed_reference.staff WHERE id = $1 LIMIT 1",
            [ $staff_id ]
        );
        return $result ? pg_fetch_object( $result ) : null;
    }

    /**
     * Normalize role label to canonical constant.
     */
    public static function normalize_role( $role ) {
        $map = [
            'c_level'     => self::ROLE_CLEVEL,
            'c-level'     => self::ROLE_CLEVEL,
            'clevel'      => self::ROLE_CLEVEL,
            'C-Level'     => self::ROLE_CLEVEL,
            'C-level'     => self::ROLE_CLEVEL,
            'administrator' => self::ROLE_CLEVEL,
            'admin'       => self::ROLE_CLEVEL,
            'finance'     => self::ROLE_FINANCE,
            'Finance'     => self::ROLE_FINANCE,
            'dispenser'   => self::ROLE_DISPENSER,
            'Dispenser'   => self::ROLE_DISPENSER,
            'reception'   => self::ROLE_RECEPTION,
            'Reception'   => self::ROLE_RECEPTION,
            'clinical_assistant' => self::ROLE_RECEPTION,
            'Clinical Assistant' => self::ROLE_RECEPTION,
            'ca'          => self::ROLE_RECEPTION,
            'scheme'      => self::ROLE_RECEPTION,
        ];
        $normalized = strtolower( trim( $role ) );
        return $map[ $role ] ?? $map[ $normalized ] ?? self::ROLE_RECEPTION;
    }

    /**
     * Get user's clinic IDs (for data scoping).
     */
    public static function get_user_clinics( $staff_id = null ) {
        $staff_id = $staff_id ?: self::current_id();
        if ( ! $staff_id ) return [];

        // Admin-level sees all clinics
        if ( self::is_admin_level() ) {
            $conn = self::pg();
            $result = pg_query( $conn, "SELECT id FROM hearmed_reference.clinics WHERE is_active = true" );
            $ids = [];
            while ( $row = pg_fetch_object( $result ) ) $ids[] = (int) $row->id;
            return $ids;
        }

        $conn = self::pg();
        $result = pg_query_params( $conn,
            "SELECT clinic_id FROM hearmed_reference.staff_clinics WHERE staff_id = $1",
            [ $staff_id ]
        );
        $ids = [];
        while ( $row = pg_fetch_object( $result ) ) $ids[] = (int) $row->clinic_id;
        return $ids;
    }
}
