<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config.php';

if (isUserLoggedIn()) {
    header('Location: /qrepo/');
    exit;
}

$error    = '';
$redirect = $_GET['redirect'] ?? '/qrepo/';

$errorMessages = [
    'ms_not_configured'     => 'Microsoft login is not configured yet. Please use your credentials below.',
    'invalid_state'         => 'Security validation failed. Please try again.',
    'token_exchange_failed' => 'Microsoft authentication failed. Please try again.',
    'profile_fetch_failed'  => 'Could not retrieve your Microsoft profile. Please try again.',
    'account_inactive'      => 'Your account has been deactivated. Contact admin.',
    'ms_access_denied'      => 'You cancelled the Microsoft login.',
];
if (!empty($_GET['error']) && isset($errorMessages[$_GET['error']])) {
    $error = $errorMessages[$_GET['error']];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password   = $_POST['password'] ?? '';
    $redirect   = $_POST['redirect'] ?? '/qrepo/';

    if ($identifier === '' || $password === '') {
        $error = 'Please enter your username/email and password.';
    } else {
        $role = attemptUnifiedLogin($identifier, $password);
        if ($role === 'admin') {
            header('Location: /qrepo/admin/');
            exit;
        } elseif ($role !== false) {
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Invalid username/email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="/qrepo/assets/mist-logo.svg">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; }
:root {
    --gold:       #c9a84c;
    --gold-light: #e8c96d;
    --navy:       #0d1b2e;
    --navy-mid:   #1a3a5c;
    --ms-blue:    #0078d4;
    --ms-blue-dk: #005a9e;
}

body {
    margin: 0; min-height: 100vh;
    font-family: 'Segoe UI', system-ui, sans-serif;
    background: var(--navy);
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    position: relative; overflow: hidden;
    padding: 1rem;
}

/* Background */
.bg-glow { position: fixed; border-radius: 50%; filter: blur(90px); opacity: .28; pointer-events: none; }
.bg-glow-1 { width: 600px; height: 600px; background: #1e3a6e; top: -180px; left: -180px; }
.bg-glow-2 { width: 450px; height: 450px; background: #0a2540; bottom: -120px; right: -120px; }
.bg-glow-3 { width: 280px; height: 280px; background: rgba(201,168,76,.13); top: 45%; left: 55%; }
.bg-grid {
    position: fixed; inset: 0; pointer-events: none; opacity: .04;
    background-image: linear-gradient(rgba(255,255,255,.8) 1px, transparent 1px),
                      linear-gradient(90deg, rgba(255,255,255,.8) 1px, transparent 1px);
    background-size: 44px 44px;
}

/* Card */
.login-card {
    position: relative; z-index: 10;
    width: 100%; max-width: 460px;
    background: #fff;
    border-radius: 22px;
    box-shadow: 0 28px 90px rgba(0,0,0,.5);
    overflow: hidden;
}

/* Header */
.login-header {
    background: linear-gradient(150deg, var(--navy) 0%, var(--navy-mid) 100%);
    padding: 2.25rem 2rem 1.8rem;
    text-align: center;
    border-bottom: 2px solid rgba(201,168,76,.25);
    position: relative;
}
.login-header::after {
    content: '';
    position: absolute; bottom: -1px; left: 0; right: 0; height: 1px;
    background: linear-gradient(90deg, transparent, var(--gold), transparent);
}
.login-logo {
    width: 78px; height: 78px;
    display: block; margin: 0 auto 1rem;
    filter: drop-shadow(0 6px 20px rgba(0,0,0,.45));
}
.login-title { font-size: 1.45rem; font-weight: 800; color: #fff; margin: 0 0 .3rem; letter-spacing: .2px; }
.login-subtitle { font-size: .82rem; color: var(--gold-light); margin: 0; letter-spacing: .3px; }

/* Body */
.login-body { padding: 1.75rem 2rem; }

/* Section labels */
.method-label {
    display: flex; align-items: center; gap: .7rem;
    margin-bottom: 1rem;
}
.method-label-line { flex: 1; height: 1px; background: #e4e8ef; }
.method-label-text {
    font-size: .72rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: .7px; color: #9aa5b8; white-space: nowrap;
}
.method-badge {
    font-size: .65rem; font-weight: 700; padding: .18rem .5rem;
    border-radius: 100px; letter-spacing: .4px; white-space: nowrap;
}
.badge-all  { background: #eef2ff; color: #4f6fc6; }
.badge-inst { background: #e8f3ff; color: var(--ms-blue); }

/* Form inputs */
.form-control-lg {
    border-radius: 10px !important;
    border: 1.5px solid #dde2ee !important;
    font-size: .92rem !important;
    padding: .72rem 1rem !important;
    transition: border-color .2s, box-shadow .2s;
    background: #fafbfd !important;
}
.form-control-lg:focus {
    border-color: var(--gold) !important;
    box-shadow: 0 0 0 3px rgba(201,168,76,.14) !important;
    background: #fff !important;
}
.input-icon-wrap { position: relative; }
.input-icon-wrap .bi {
    position: absolute; left: .9rem; top: 50%; transform: translateY(-50%);
    color: #aab; font-size: .95rem; pointer-events: none;
}
.input-icon-wrap .form-control-lg { padding-left: 2.4rem !important; }

/* Buttons */
.btn-signin {
    background: linear-gradient(135deg, var(--navy), var(--navy-mid));
    border: none; color: #fff; font-weight: 700; font-size: .95rem;
    padding: .78rem; border-radius: 10px; width: 100%;
    transition: opacity .2s, transform .12s; cursor: pointer;
}
.btn-signin:hover:not(:disabled) { opacity: .88; transform: translateY(-1px); }
.btn-signin:disabled { opacity: .45; cursor: not-allowed; }

.btn-microsoft {
    display: flex; align-items: center; justify-content: center; gap: .75rem;
    background: #fff; border: 1.5px solid #dde2ee;
    border-radius: 10px; padding: .72rem 1rem; width: 100%;
    font-size: .93rem; font-weight: 600; color: #1a1a1a;
    cursor: pointer; transition: border-color .2s, box-shadow .2s, background .15s;
    text-decoration: none;
}
.btn-microsoft:hover {
    border-color: var(--ms-blue);
    box-shadow: 0 2px 12px rgba(0,120,212,.15);
    background: #f8fbff; color: #1a1a1a;
}
.btn-microsoft:active { transform: translateY(1px); }
.btn-microsoft .ms-logo { flex-shrink: 0; }
.ms-logo-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2px; width: 20px; height: 20px; }
.ms-logo-grid span { display: block; border-radius: 1px; }
.ms-sq-1 { background: #f25022; }
.ms-sq-2 { background: #7fba00; }
.ms-sq-3 { background: #00a4ef; }
.ms-sq-4 { background: #ffb900; }

.btn-microsoft-disabled {
    display: flex; align-items: center; justify-content: center; gap: .75rem;
    background: #f8f9fc; border: 1.5px dashed #d0d5e0;
    border-radius: 10px; padding: .72rem 1rem; width: 100%;
    font-size: .88rem; color: #aab; cursor: not-allowed;
    position: relative;
}
.not-configured-badge {
    position: absolute; top: -8px; right: 10px;
    font-size: .65rem; background: #fff3cd; color: #856404;
    border: 1px solid #ffc107; border-radius: 100px;
    padding: .1rem .5rem; font-weight: 700;
}

/* Divider between the two methods */
.method-divider {
    display: flex; align-items: center; gap: .6rem;
    margin: 1.4rem 0;
}
.method-divider-line { flex: 1; height: 1px; background: #eef0f5; }
.method-divider-text { font-size: .75rem; color: #bbb; font-weight: 500; }

/* Links */
.auth-links { margin-top: .9rem; }
.auth-link {
    display: block; font-size: .82rem; color: #6a7a95;
    text-decoration: none; padding: .3rem 0;
    transition: color .15s;
}
.auth-link:hover { color: var(--navy); }

/* Footer */
.login-footer {
    background: #f8fafc; border-top: 1px solid #eef0f5;
    padding: .9rem 2rem; text-align: center;
}
.login-footer p { font-size: .73rem; color: #9aa5b8; margin: 0; line-height: 1.75; }

.alert { border-radius: 10px; font-size: .875rem; }
</style>
</head>
<body>

<div class="bg-glow bg-glow-1"></div>
<div class="bg-glow bg-glow-2"></div>
<div class="bg-glow bg-glow-3"></div>
<div class="bg-grid"></div>

<div class="login-card">

    <!-- Header -->
    <div class="login-header">
        <img src="/qrepo/assets/mist-logo.svg" alt="MIST Logo" class="login-logo">
        <h1 class="login-title"><?= APP_NAME ?></h1>
        <p class="login-subtitle">Military Institute of Science and Technology</p>
    </div>

    <div class="login-body">

        <?php if ($error): ?>
        <div class="alert alert-danger py-2 mb-4">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <!-- ── Option 1: Microsoft Institutional Login (primary) ── -->
        <div class="method-label">
            <span class="method-label-line"></span>
            <span class="method-label-text">Recommended</span>
            <span class="method-badge badge-inst">Microsoft 365</span>
            <span class="method-label-line"></span>
        </div>

        <?php if (MS_ENABLED): ?>
        <a href="/qrepo/auth/microsoft/redirect.php" class="btn-microsoft">
            <span class="ms-logo">
                <span class="ms-logo-grid">
                    <span class="ms-sq-1"></span><span class="ms-sq-2"></span>
                    <span class="ms-sq-3"></span><span class="ms-sq-4"></span>
                </span>
            </span>
            <span>Continue with MIST Institutional Email</span>
        </a>
        <p style="font-size:.76rem;color:#9aa5b8;text-align:center;margin:.55rem 0 0;">
            <i class="bi bi-shield-check me-1" style="color:#60bbff"></i>
            Sign in with your <strong>@mist.ac.bd</strong> Microsoft 365 account
        </p>
        <?php else: ?>
        <div class="btn-microsoft-disabled">
            <span class="not-configured-badge">Setup Required</span>
            <span class="ms-logo">
                <span class="ms-logo-grid">
                    <span class="ms-sq-1"></span><span class="ms-sq-2"></span>
                    <span class="ms-sq-3"></span><span class="ms-sq-4"></span>
                </span>
            </span>
            <span>Continue with MIST Institutional Email</span>
        </div>
        <p style="font-size:.73rem;color:#aab;text-align:center;margin:.5rem 0 0;">
            <i class="bi bi-info-circle me-1"></i>
            Azure AD credentials not configured — <a href="/qrepo/admin/" style="color:inherit">see admin panel</a>
        </p>
        <?php endif; ?>

        <!-- ── Divider ── -->
        <div class="method-divider">
            <span class="method-divider-line"></span>
            <span class="method-divider-text">or sign in with password</span>
            <span class="method-divider-line"></span>
        </div>

        <!-- ── Option 2: Password login (secondary) ── -->
        <div class="method-label">
            <span class="method-label-line"></span>
            <span class="method-label-text">Email &amp; Password</span>
            <span class="method-badge badge-all">Admin · Faculty · Student</span>
            <span class="method-label-line"></span>
        </div>

        <form method="POST" id="loginForm" novalidate>
            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

            <div class="input-icon-wrap mb-3">
                <i class="bi bi-person"></i>
                <input type="text" name="identifier" class="form-control form-control-lg"
                       placeholder="Username or email address"
                       value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>"
                       autocomplete="username" autofocus required>
            </div>
            <div class="input-icon-wrap mb-3">
                <i class="bi bi-lock"></i>
                <input type="password" name="password" class="form-control form-control-lg"
                       placeholder="Password"
                       autocomplete="current-password" required>
            </div>

            <button type="submit" class="btn-signin" id="loginBtn" disabled>
                <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
            </button>
        </form>

        <div class="auth-links">
            <a href="/qrepo/register.php" class="auth-link">
                <i class="bi bi-person-plus me-1"></i>New user? Create an account
            </a>
            <a href="/qrepo/forgot.php" class="auth-link">
                <i class="bi bi-key me-1"></i>Forgot your password?
            </a>
        </div>

    </div><!-- /login-body -->

    <!-- Footer -->
    <div class="login-footer">
        <p>© 2026 Military Institute of Science and Technology</p>
        <p>Mirpur Cantonment, Dhaka, Bangladesh</p>
    </div>

</div><!-- /login-card -->

<script>
const ident    = document.querySelector('input[name="identifier"]');
const password = document.querySelector('input[name="password"]');
const btn      = document.getElementById('loginBtn');
function check() { btn.disabled = !(ident.value.trim() && password.value); }
ident.addEventListener('input', check);
password.addEventListener('input', check);
</script>
</body>
</html>
