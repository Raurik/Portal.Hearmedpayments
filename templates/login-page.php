<?php
/**
 * Standalone Login Page Template
 *
 * Loaded via template_include — bypasses Elementor / theme completely
 * so there is NO sidebar, header, or footer chrome.
 *
 * @package HearMed_Portal
 * @since 5.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Prevent caching of the login page ──
if ( ! defined( 'DONOTCACHEPAGE' ) ) define( 'DONOTCACHEPAGE', true );
nocache_headers();

// ── Force-logout: /login/?logout=1 ──
// Allows users stuck with the wrong identity to flush everything
// without needing the portal UI's logout button.
if ( ! empty( $_GET['logout'] ) ) {
    PortalAuth::logout();
    wp_clear_auth_cookie();
    wp_redirect( home_url( '/' ) );   // back to /login/ clean
    exit;
}

// ── Already portal-authenticated → show identity confirmation ──
// Do NOT silently redirect — the user may be stuck under the wrong
// identity (e.g. test2) with a valid session cookie.  Give them the
// choice to continue or log out and re-authenticate.
if ( PortalAuth::is_logged_in() ) {
    $staff = PortalAuth::current_user();
    $go = $_REQUEST['redirect_to'] ?? '';
    // If redirect_to is empty or points back at the login page, go to calendar
    if ( empty( $go ) || preg_match( '#/login/?(?:\\?|$)#', $go ) ) {
        $go = home_url( '/calendar/' );
    }
    ?><!DOCTYPE html>
    <html <?php language_attributes(); ?>>
    <head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — HearMed Portal</title>
    <?php wp_head(); ?>
    <style>
        html,body{margin:0;padding:0;height:100%;overflow:hidden}
        #wpadminbar,.admin-bar{display:none!important}
        html.wp-toolbar{padding-top:0!important}
        body{display:flex;justify-content:center;align-items:center;min-height:100vh;background:#FAFBFF;font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif}
        .hm-login-card{background:#fff;border-radius:16px;box-shadow:0 8px 40px rgba(0,0,0,0.2);padding:40px 36px;max-width:420px;width:100%;margin:20px;text-align:center}
        .hm-login-card h2{margin:0 0 8px;font-size:22px;color:#1a1a2e}
        .hm-login-card p{color:#666;font-size:15px;margin:0 0 24px}
        .hm-login-card .name{font-weight:600;color:#1a1a2e}
        .hm-btn{display:inline-block;padding:12px 28px;border-radius:10px;font-size:15px;font-weight:600;text-decoration:none;cursor:pointer;margin:6px}
        .hm-btn-primary{background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;border:none}
        .hm-btn-outline{background:transparent;color:#764ba2;border:2px solid #764ba2}
        .hm-btn:hover{opacity:0.85}
    </style>
    </head>
    <body>
    <div class="hm-login-card">
        <h2>Welcome back</h2>
        <p>You're logged in as <span class="name"><?php echo esc_html( $staff->display_name ); ?></span></p>
        <a class="hm-btn hm-btn-primary" href="<?php echo esc_url( $go ); ?>">Continue to Portal</a>
        <br>
        <a class="hm-btn hm-btn-outline" href="<?php echo esc_url( home_url( '/?logout=1' ) ); ?>">Not you? Log out</a>
    </div>
    <?php wp_footer(); ?>
    </body>
    </html>
    <?php
    exit;
}

$atts = [ 'redirect' => home_url( '/calendar/' ) ];
$redirect_to = esc_url( $_REQUEST['redirect_to'] ?? $atts['redirect'] );
$ajax_url    = admin_url( 'admin-ajax.php' );

// Invite?
$invite_token = sanitize_text_field( $_GET['invite'] ?? '' );
$invite_data  = null;
if ( $invite_token ) {
    $invite_data = PortalAuth::validate_invite( $invite_token );
}

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — HearMed Portal</title>
<?php wp_head(); ?>
<style>
    /* ── Reset everything: no WP admin bar, no theme chrome ── */
    html, body { margin:0; padding:0; height:100%; overflow:hidden; }
    #wpadminbar, .admin-bar { display:none !important; }
    html.wp-toolbar { padding-top:0 !important; }
    body { display:flex; justify-content:center; align-items:center; min-height:100vh; background:#FAFBFF; font-family:'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }

    .hm-login-card { background:#fff; border-radius:16px; box-shadow:0 8px 40px rgba(0,0,0,0.2); padding:40px 36px; max-width:420px; width:100%; margin:20px; }
    .hm-login-logo { text-align:center; margin-bottom:28px; display:flex; justify-content:center; }
    .hm-login-logo img { max-width:260px; height:auto; }
    .hm-login-step { display:none; }
    .hm-login-step.active { display:block; }
    .hm-login-step h2 { font-size:22px; font-weight:700; color:#151B33; margin:0 0 6px; }
    .hm-login-subtitle { color:#6b7280; font-size:14px; margin-bottom:20px; line-height:1.4; }
    .hm-login-error { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; border-radius:8px; padding:10px 14px; margin-bottom:16px; font-size:14px; display:none; }
    .hm-login-mono { background:#f3f4f6; padding:10px 14px; border-radius:6px; font-family:monospace; font-size:13px; color:#374151; user-select:all; word-break:break-all; }
    .hm-login-qr { text-align:center; margin:16px 0; }
    .hm-login-qr img { max-width:200px; border:8px solid #fff; box-shadow:0 2px 12px rgba(0,0,0,0.1); border-radius:8px; }
    .hm-form-group { margin-bottom:16px; }
    .hm-form-group label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:5px; }
    .hm-form-group input { width:100%; padding:10px 14px; border:1px solid #d1d5db; border-radius:8px; font-size:15px; box-sizing:border-box; transition:border-color .2s; }
    .hm-form-group input:focus { outline:none; border-color:#0BB4C4; box-shadow:0 0 0 3px rgba(11,180,196,0.15); }
    .hm-btn { display:inline-block; }
    .hm-btn--full { width:100%; padding:12px; font-size:15px; font-weight:600; border-radius:8px; cursor:pointer; border:none; transition:opacity .2s; }
    .hm-btn--primary { background:#0BB4C4; color:#fff; }
    .hm-btn--full:disabled { opacity:.5; cursor:not-allowed; }
    .hm-btn--full:hover:not(:disabled) { opacity:.85; }
</style>
</head>
<body>
    <div class="hm-login-card" id="hm-login-app">
        <div class="hm-login-logo">
            <img src="<?php echo esc_url( HEARMED_URL . 'assets/img/Untitled%20(600%20x%20200%20px).png' ); ?>" alt="HearMed Portal">
        </div>

        <!-- ═══ INVITE STEP ═══ -->
        <?php if ( $invite_data ): ?>
        <div id="hm-login-invite" class="hm-login-step active">
            <h2>Welcome, <?php echo esc_html( $invite_data->first_name ); ?>!</h2>
            <p class="hm-login-subtitle">Set your password to activate your account.</p>
            <div id="hm-invite-error" class="hm-login-error"></div>
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
            <div id="hm-login-error" class="hm-login-error"></div>
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
            <div class="hm-login-qr" id="hm-2fa-qr-container"></div>
            <div class="hm-form-group" style="margin-top:12px;">
                <label>Manual entry code</label>
                <div id="hm-2fa-manual-secret" class="hm-login-mono"></div>
            </div>
            <div id="hm-2fa-setup-error" class="hm-login-error"></div>
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
            <div id="hm-2fa-verify-error" class="hm-login-error"></div>
            <div class="hm-form-group">
                <input type="text" id="hm-2fa-verify-code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" autocomplete="one-time-code" placeholder="000000" style="font-size:24px;text-align:center;letter-spacing:8px;">
            </div>
            <button class="hm-btn hm-btn--primary hm-btn--full" id="hm-2fa-verify-btn">Verify</button>
        </div>

        <!-- ═══ SUCCESS ═══ -->
        <div id="hm-login-success" class="hm-login-step">
            <h2>&#10003; Logged In</h2>
            <p class="hm-login-subtitle">Redirecting…</p>
        </div>
    </div>

<script>
(function(){
    const ajaxUrl    = <?php echo wp_json_encode( $ajax_url ); ?>;
    const redirectTo = <?php echo wp_json_encode( $redirect_to ); ?>;

    let pendingToken = '';
    let setupStaffId = null;
    let setupSecret  = '';

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
                    try {
                        const qr = await post('hm_auth_get_2fa_qr', { staff_id: d.staff_id });
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
                            document.getElementById('hm-2fa-qr-container').innerHTML =
                                '<p style="color:red;">Could not generate QR code. Error: ' + (qr.data?.message || 'Unknown') + '</p>';
                        }
                    } catch(qrErr) {
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
<?php wp_footer(); ?>
</body>
</html>
