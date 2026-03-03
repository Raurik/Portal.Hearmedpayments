<?php
/**
 * HearMed Staff Login v2
 * Shortcode: [hearmed_staff_login]
 *
 * Multi-step AJAX login:
 *   Step 1: Email/username + Password
 *   Step 2a: 2FA setup (QR + verification code) — first login / reset
 *   Step 2b: 2FA code entry — untrusted device
 *   Step 3: Session created → redirect
 *
 * Also handles invite links: /login/?invite=TOKEN
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class HearMed_Staff_Login {

    public function __construct() {
        add_shortcode( 'hearmed_staff_login', [ $this, 'render' ] );

        // Register for both logged-in and non-logged-in WP users.
        // Portal auth is independent of WP auth — a WP-logged-in user
        // may not have a portal session yet.
        $actions = [
            'hm_auth_login'          => 'ajax_login',
            'hm_auth_verify_2fa'     => 'ajax_verify_2fa',
            'hm_auth_setup_2fa'      => 'ajax_setup_2fa',
            'hm_auth_get_2fa_qr'     => 'ajax_get_2fa_qr',
            'hm_auth_accept_invite'  => 'ajax_accept_invite',
            'hm_auth_logout'         => 'ajax_logout',
        ];
        foreach ( $actions as $action => $method ) {
            add_action( "wp_ajax_$action",        [ $this, $method ] );
            add_action( "wp_ajax_nopriv_$action", [ $this, $method ] );
        }
    }

    /* ─────────────────────────────────────────────────────────────────
     * RENDER  
     * ───────────────────────────────────────────────────────────────── */
    public function render( $atts = [] ) {
        // Don't redirect during REST API / AJAX / Cron — allows Gutenberg to save the page
        if ( defined( 'REST_REQUEST' ) || defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
            return '<p style="padding:2rem;text-align:center;color:#888;">[HearMed Staff Login]</p>';
        }

        // Already logged in → redirect to portal
        if ( PortalAuth::is_v2() && PortalAuth::is_logged_in() ) {
            wp_redirect( home_url( '/' ) );
            exit;
        }
        if ( ! PortalAuth::is_v2() && is_user_logged_in() ) {
            wp_redirect( home_url( '/' ) );
            exit;
        }

        $atts = shortcode_atts( [ 'redirect' => home_url( '/' ) ], $atts );

        // Check for invite token in URL
        $invite_token = sanitize_text_field( $_GET['invite'] ?? '' );
        $invite_data  = null;
        if ( $invite_token ) {
            $invite_data = PortalAuth::validate_invite( $invite_token );
        }

        ob_start();
        ?>
        <div class="hm-login-wrap" id="hm-login-app">
            <div class="hm-login-card">
                <div class="hm-login-logo">
                    <img src="<?php echo esc_url( HEARMED_URL . 'assets/img/hearmed-logo.png' ); ?>" alt="HearMed" style="max-width:200px;">
                </div>

                <!-- ═══ INVITE STEP ═══ -->
                <?php if ( $invite_data ): ?>
                <div id="hm-login-invite" class="hm-login-step active">
                    <h2>Welcome, <?php echo esc_html( $invite_data->first_name ); ?>!</h2>
                    <p class="hm-login-subtitle">Set your password to activate your account.</p>
                    <div id="hm-invite-error" class="hm-login-error" style="display:none;"></div>
                    <div class="hm-form-group">
                        <label>New Password</label>
                        <input type="password" id="hm-invite-password" minlength="10" required placeholder="Min 10 characters">
                    </div>
                    <div class="hm-form-group">
                        <label>Confirm Password</label>
                        <input type="password" id="hm-invite-password2" minlength="10" required>
                    </div>
                    <button class="hm-btn hm-btn--primary hm-btn--full" id="hm-invite-btn">Set Password</button>
                    <input type="hidden" id="hm-invite-token" value="<?php echo esc_attr( $invite_token ); ?>">
                </div>
                <?php endif; ?>

                <!-- ═══ STEP 1: CREDENTIALS ═══ -->
                <div id="hm-login-step1" class="hm-login-step <?php echo $invite_data ? '' : 'active'; ?>">
                    <h2>Staff Login</h2>
                    <div id="hm-login-error" class="hm-login-error" style="display:none;"></div>
                    <div class="hm-form-group">
                        <label>Email or Username</label>
                        <input type="text" id="hm-login-identifier" autocomplete="username" required>
                    </div>
                    <div class="hm-form-group">
                        <label>Password</label>
                        <input type="password" id="hm-login-password" autocomplete="current-password" required>
                    </div>
                    <button class="hm-btn hm-btn--primary hm-btn--full" id="hm-login-btn">Sign In</button>
                </div>

                <!-- ═══ STEP 2A: 2FA SETUP (first time) ═══ -->
                <div id="hm-login-step2a" class="hm-login-step">
                    <h2>Set Up Two-Factor Authentication</h2>
                    <p class="hm-login-subtitle">Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.).</p>
                    <div class="hm-login-qr" id="hm-2fa-qr-container">
                        <!-- QR image injected dynamically -->
                    </div>
                    <div class="hm-form-group" style="margin-top:12px;">
                        <label>Manual entry code</label>
                        <div id="hm-2fa-manual-secret" class="hm-login-mono" style="word-break:break-all;"></div>
                    </div>
                    <div id="hm-2fa-setup-error" class="hm-login-error" style="display:none;"></div>
                    <div class="hm-form-group">
                        <label>Enter 6-digit code from your app</label>
                        <input type="text" id="hm-2fa-setup-code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code" placeholder="000000">
                    </div>
                    <button class="hm-btn hm-btn--primary hm-btn--full" id="hm-2fa-setup-btn">Verify &amp; Complete Setup</button>
                </div>

                <!-- ═══ STEP 2B: 2FA VERIFY (existing) ═══ -->
                <div id="hm-login-step2b" class="hm-login-step">
                    <h2>Two-Factor Verification</h2>
                    <p class="hm-login-subtitle">Enter the 6-digit code from your authenticator app.</p>
                    <div id="hm-2fa-verify-error" class="hm-login-error" style="display:none;"></div>
                    <div class="hm-form-group">
                        <input type="text" id="hm-2fa-verify-code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code" placeholder="000000" style="font-size:24px;text-align:center;letter-spacing:8px;">
                    </div>
                    <button class="hm-btn hm-btn--primary hm-btn--full" id="hm-2fa-verify-btn">Verify</button>
                </div>

                <!-- ═══ SUCCESS ═══ -->
                <div id="hm-login-success" class="hm-login-step">
                    <h2>✓ Logged In</h2>
                    <p class="hm-login-subtitle">Redirecting…</p>
                </div>
            </div>
        </div>

        <style>
            .hm-login-wrap { display:flex; justify-content:center; align-items:center; min-height:100vh; background:#f5f7fa; padding:20px; font-family:var(--hm-font-body, 'Inter', sans-serif); }
            .hm-login-card { background:#fff; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,0.08); padding:40px 36px; max-width:420px; width:100%; }
            .hm-login-logo { text-align:center; margin-bottom:28px; }
            .hm-login-step { display:none; }
            .hm-login-step.active { display:block; }
            .hm-login-step h2 { font-size:22px; font-weight:700; color:#151B33; margin:0 0 6px; }
            .hm-login-subtitle { color:#6b7280; font-size:14px; margin-bottom:20px; line-height:1.4; }
            .hm-login-error { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; border-radius:8px; padding:10px 14px; margin-bottom:16px; font-size:14px; }
            .hm-login-mono { background:#f3f4f6; padding:10px 14px; border-radius:6px; font-family:monospace; font-size:13px; color:#374151; user-select:all; }
            .hm-login-qr { text-align:center; margin:16px 0; }
            .hm-login-qr img { max-width:200px; border:8px solid #fff; box-shadow:0 2px 12px rgba(0,0,0,0.1); border-radius:8px; }
            .hm-login-card .hm-form-group { margin-bottom:16px; }
            .hm-login-card .hm-form-group label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:5px; }
            .hm-login-card .hm-form-group input { width:100%; padding:10px 14px; border:1px solid #d1d5db; border-radius:8px; font-size:15px; box-sizing:border-box; transition:border-color .2s; }
            .hm-login-card .hm-form-group input:focus { outline:none; border-color:#0BB4C4; box-shadow:0 0 0 3px rgba(11,180,196,0.15); }
            .hm-btn--full { width:100%; padding:12px; font-size:15px; font-weight:600; border-radius:8px; cursor:pointer; border:none; transition:opacity .2s; }
            .hm-btn--full:disabled { opacity:.5; cursor:not-allowed; }
            .hm-btn--full:hover:not(:disabled) { opacity:.9; }
        </style>

        <script>
        (function(){
            const ajaxUrl = '<?php echo esc_js( admin_url("admin-ajax.php") ); ?>';
            const redirectTo = '<?php echo esc_js( esc_url( $_REQUEST['redirect_to'] ?? $atts['redirect'] ) ); ?>';

            // State
            let pendingToken = '';
            let setupStaffId = null;
            let setupSecret  = '';

            // Helpers
            function showStep(id) {
                document.querySelectorAll('.hm-login-step').forEach(s => s.classList.remove('active'));
                const el = document.getElementById(id);
                if (el) el.classList.add('active');
            }
            function showError(elId, msg) {
                const el = document.getElementById(elId);
                if (el) { el.textContent = msg; el.style.display = 'block'; }
            }
            function clearError(elId) {
                const el = document.getElementById(elId);
                if (el) el.style.display = 'none';
            }
            function disable(btn, label) { btn.disabled = true; btn.textContent = label || 'Please wait…'; }
            function enable(btn, label)  { btn.disabled = false; btn.textContent = label; }

            async function post(action, data) {
                const fd = new FormData();
                fd.append('action', action);
                Object.keys(data).forEach(k => fd.append(k, data[k]));
                const res = await fetch(ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' });
                return res.json();
            }

            /* ── STEP 1: Login ── */
            const loginBtn = document.getElementById('hm-login-btn');
            if (loginBtn) {
                loginBtn.addEventListener('click', async function(e) {
                    e.preventDefault();
                    clearError('hm-login-error');
                    const identifier = document.getElementById('hm-login-identifier').value.trim();
                    const password   = document.getElementById('hm-login-password').value;
                    if (!identifier || !password) { showError('hm-login-error', 'Please enter both fields.'); return; }

                    disable(loginBtn, 'Signing in…');
                    try {
                        const r = await post('hm_auth_login', { identifier, password });
                        if (!r.success) { showError('hm-login-error', r.data?.message || 'Login failed.'); enable(loginBtn, 'Sign In'); return; }

                        const d = r.data;
                        if (d.status === '2fa_required') {
                            pendingToken = d.pending_token;
                            showStep('hm-login-step2b');
                            document.getElementById('hm-2fa-verify-code').focus();
                        } else if (d.status === '2fa_setup') {
                            setupStaffId = d.staff_id;
                            // Get QR code
                            try {
                                const qr = await post('hm_auth_get_2fa_qr', { staff_id: d.staff_id });
                                console.log('QR response:', qr);
                                if (qr.success && qr.data) {
                                    setupSecret = qr.data.secret;
                                    const qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(qr.data.qr_uri);
                                    document.getElementById('hm-2fa-qr-container').innerHTML =
                                        '<img src="' + qrUrl + '" alt="QR Code" style="max-width:200px;margin:10px auto;display:block;">' +
                                        '<p style="margin-top:8px;font-size:12px;color:#666;">Can\'t scan? Enter this key manually:</p>' +
                                        '<code style="display:block;text-align:center;padding:8px;background:#f5f5f5;border-radius:4px;font-size:14px;word-break:break-all;">' + qr.data.secret + '</code>';
                                    if (document.getElementById('hm-2fa-manual-secret')) {
                                        document.getElementById('hm-2fa-manual-secret').textContent = qr.data.secret;
                                    }
                                } else {
                                    console.error('QR generation failed:', qr);
                                    document.getElementById('hm-2fa-qr-container').innerHTML =
                                        '<p style="color:red;">Could not generate QR code. Error: ' + (qr.data?.message || 'Unknown') + '</p>';
                                }
                            } catch(qrErr) {
                                console.error('QR fetch error:', qrErr);
                                document.getElementById('hm-2fa-qr-container').innerHTML =
                                    '<p style="color:red;">Network error loading QR code.</p>';
                            }
                            showStep('hm-login-step2a');
                            document.getElementById('hm-2fa-setup-code').focus();
                        } else if (d.status === 'success') {
                            showStep('hm-login-success');
                            window.location.href = redirectTo;
                        } else if (d.status === 'locked') {
                            showError('hm-login-error', d.message);
                            enable(loginBtn, 'Sign In');
                        } else {
                            showError('hm-login-error', d.message || 'Login failed.');
                            enable(loginBtn, 'Sign In');
                        }
                    } catch(err) {
                        showError('hm-login-error', 'Network error. Please try again.');
                        enable(loginBtn, 'Sign In');
                    }
                });

                // Enter key on password field
                document.getElementById('hm-login-password').addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') loginBtn.click();
                });
            }

            /* ── STEP 2B: 2FA Verify ── */
            const verifyBtn = document.getElementById('hm-2fa-verify-btn');
            if (verifyBtn) {
                verifyBtn.addEventListener('click', async function(e) {
                    e.preventDefault();
                    clearError('hm-2fa-verify-error');
                    const code = document.getElementById('hm-2fa-verify-code').value.trim();
                    if (!code || code.length !== 6) { showError('hm-2fa-verify-error', 'Enter a 6-digit code.'); return; }

                    disable(verifyBtn, 'Verifying…');
                    try {
                        const r = await post('hm_auth_verify_2fa', { pending_token: pendingToken, code });
                        if (!r.success) { showError('hm-2fa-verify-error', r.data?.message || 'Invalid code.'); enable(verifyBtn, 'Verify'); return; }
                        showStep('hm-login-success');
                        window.location.href = redirectTo;
                    } catch(err) {
                        showError('hm-2fa-verify-error', 'Network error.');
                        enable(verifyBtn, 'Verify');
                    }
                });
                document.getElementById('hm-2fa-verify-code').addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') verifyBtn.click();
                });
            }

            /* ── STEP 2A: 2FA Setup ── */
            const setupBtn = document.getElementById('hm-2fa-setup-btn');
            if (setupBtn) {
                setupBtn.addEventListener('click', async function(e) {
                    e.preventDefault();
                    clearError('hm-2fa-setup-error');
                    const code = document.getElementById('hm-2fa-setup-code').value.trim();
                    if (!code || code.length !== 6) { showError('hm-2fa-setup-error', 'Enter a 6-digit code.'); return; }

                    disable(setupBtn, 'Verifying…');
                    try {
                        const r = await post('hm_auth_setup_2fa', { staff_id: setupStaffId, code, secret: setupSecret });
                        if (!r.success) { showError('hm-2fa-setup-error', r.data?.message || 'Invalid code.'); enable(setupBtn, 'Verify & Complete Setup'); return; }
                        showStep('hm-login-success');
                        window.location.href = redirectTo;
                    } catch(err) {
                        showError('hm-2fa-setup-error', 'Network error.');
                        enable(setupBtn, 'Verify & Complete Setup');
                    }
                });
                document.getElementById('hm-2fa-setup-code').addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') setupBtn.click();
                });
            }

            /* ── INVITE ── */
            const inviteBtn = document.getElementById('hm-invite-btn');
            if (inviteBtn) {
                inviteBtn.addEventListener('click', async function(e) {
                    e.preventDefault();
                    clearError('hm-invite-error');
                    const pw1 = document.getElementById('hm-invite-password').value;
                    const pw2 = document.getElementById('hm-invite-password2').value;
                    if (!pw1 || pw1.length < 10) { showError('hm-invite-error', 'Password must be at least 10 characters.'); return; }
                    if (pw1 !== pw2) { showError('hm-invite-error', 'Passwords do not match.'); return; }

                    disable(inviteBtn, 'Setting password…');
                    try {
                        const token = document.getElementById('hm-invite-token').value;
                        const r = await post('hm_auth_accept_invite', { invite_token: token, password: pw1 });
                        if (!r.success) { showError('hm-invite-error', r.data?.message || 'Failed.'); enable(inviteBtn, 'Set Password'); return; }
                        if (r.data.status === '2fa_setup') {
                            setupStaffId = r.data.staff_id;
                            const qr = await post('hm_auth_get_2fa_qr', { staff_id: r.data.staff_id });
                            if (qr.success) {
                                setupSecret = qr.data.secret;
                                document.getElementById('hm-2fa-qr-container').innerHTML =
                                    '<img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' + encodeURIComponent(qr.data.qr_uri) + '" alt="QR Code">';
                                document.getElementById('hm-2fa-manual-secret').textContent = qr.data.secret;
                            }
                            showStep('hm-login-step2a');
                        } else {
                            showStep('hm-login-success');
                            window.location.href = redirectTo;
                        }
                    } catch(err) {
                        showError('hm-invite-error', 'Network error.');
                        enable(inviteBtn, 'Set Password');
                    }
                });
            }
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /* ─────────────────────────────────────────────────────────────────
     * AJAX HANDLERS  
     * ───────────────────────────────────────────────────────────────── */

    public function ajax_login() {
        $identifier = sanitize_text_field( $_POST['identifier'] ?? '' );
        $password   = (string) ( $_POST['password'] ?? '' );

        if ( ! $identifier || ! $password ) {
            wp_send_json_error( [ 'message' => 'Please provide both fields.' ] );
        }

        error_log( '[HM Auth] Login attempt for: ' . $identifier );
        $result = PortalAuth::login( $identifier, $password );
        error_log( '[HM Auth] Login result: ' . json_encode( $result ) );

        if ( $result['status'] === 'success' ) {
            wp_send_json_success( $result );
        }
        if ( in_array( $result['status'], [ '2fa_required', '2fa_setup' ], true ) ) {
            wp_send_json_success( $result );
        }
        wp_send_json_error( $result );
    }

    public function ajax_verify_2fa() {
        $pending_token = sanitize_text_field( $_POST['pending_token'] ?? '' );
        $code          = sanitize_text_field( $_POST['code'] ?? '' );

        if ( ! $pending_token || ! $code ) {
            wp_send_json_error( [ 'message' => 'Missing required fields.' ] );
        }

        $result = PortalAuth::verify_2fa( $pending_token, $code );
        if ( $result['status'] === 'success' ) {
            wp_send_json_success( $result );
        }
        wp_send_json_error( $result );
    }

    public function ajax_setup_2fa() {
        $staff_id = (int) ( $_POST['staff_id'] ?? 0 );
        $code     = sanitize_text_field( $_POST['code'] ?? '' );
        $secret   = sanitize_text_field( $_POST['secret'] ?? '' );

        if ( ! $staff_id || ! $code || ! $secret ) {
            wp_send_json_error( [ 'message' => 'Missing required fields.' ] );
        }

        $result = PortalAuth::setup_2fa( $staff_id, $code, $secret );
        if ( $result['status'] === 'success' ) {
            wp_send_json_success( $result );
        }
        wp_send_json_error( $result );
    }

    public function ajax_get_2fa_qr() {
        try {
            $staff_id = (int) ( $_POST['staff_id'] ?? 0 );
            error_log( '[HM Auth] QR request for staff_id: ' . $staff_id );
            if ( ! $staff_id ) {
                wp_send_json_error( [ 'message' => 'Missing staff ID.' ] );
            }
            $data = PortalAuth::generate_2fa_secret( $staff_id );
            error_log( '[HM Auth] QR generated OK, secret length: ' . strlen( $data['secret'] ?? '' ) );
            wp_send_json_success( $data );
        } catch ( \Throwable $e ) {
            error_log( '[HM Auth] QR error: ' . $e->getMessage() );
            wp_send_json_error( [ 'message' => 'Server error: ' . $e->getMessage() ] );
        }
    }

    public function ajax_accept_invite() {
        $token    = sanitize_text_field( $_POST['invite_token'] ?? '' );
        $password = (string) ( $_POST['password'] ?? '' );

        if ( ! $token || ! $password ) {
            wp_send_json_error( [ 'message' => 'Missing required fields.' ] );
        }
        if ( strlen( $password ) < 10 ) {
            wp_send_json_error( [ 'message' => 'Password must be at least 10 characters.' ] );
        }

        $result = PortalAuth::accept_invite( $token, $password );
        if ( in_array( $result['status'] ?? '', [ 'success', '2fa_setup' ], true ) ) {
            wp_send_json_success( $result );
        }
        wp_send_json_error( $result );
    }

    public function ajax_logout() {
        PortalAuth::logout();
        wp_send_json_success( [ 'message' => 'Logged out.' ] );
    }
}

new HearMed_Staff_Login();
