<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

if (isUserLoggedIn()) {
    header('Location: /qrepo/');
    exit;
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($name === '' || $email === '' || $password === '') {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $result = registerUser($name, $email, $password);
        if ($result === true) {
            $success = 'Account created! You can now <a href="/qrepo/login.php">sign in</a>.';
        } else {
            $error = $result;
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
<title>Register — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; }
:root { --gold:#c9a84c; --gold-light:#e8c96d; --navy:#0d1b2e; }
body {
    margin:0; min-height:100vh;
    font-family:'Segoe UI',system-ui,sans-serif;
    background:var(--navy);
    display:flex; flex-direction:column;
    position:relative; overflow-x:hidden;
}
.bg-glow { position:fixed; border-radius:50%; filter:blur(90px); opacity:.3; pointer-events:none; }
.bg-glow-1 { width:500px;height:500px;background:#1e3a6e;top:-120px;left:-150px; }
.bg-glow-2 { width:400px;height:400px;background:#0a2a4a;bottom:-100px;right:-100px; }
.bg-grid {
    position:fixed;inset:0;pointer-events:none;opacity:.05;
    background-image:linear-gradient(rgba(255,255,255,.7) 1px,transparent 1px),
                     linear-gradient(90deg,rgba(255,255,255,.7) 1px,transparent 1px);
    background-size:40px 40px;
}
.login-wrapper {
    flex:1; display:flex;
    align-items:center; justify-content:center;
    padding:2rem 1rem;
}
.mist-login-card {
    position:relative;z-index:10;
    width:100%;max-width:440px;
    background:#fff;border-radius:20px;
    box-shadow:0 24px 80px rgba(0,0,0,.45);
    overflow:hidden;
}
.mist-login-header {
    background:linear-gradient(135deg,var(--navy) 0%,#1a3a5c 100%);
    padding:2rem 2rem 1.5rem; text-align:center;
    border-bottom:2px solid rgba(201,168,76,.3);
}
.mist-login-logo {
    width:72px;height:72px;
    margin:0 auto .9rem;display:block;
    filter:drop-shadow(0 6px 18px rgba(0,0,0,.4));
}
.mist-login-title { font-size:1.3rem;font-weight:800;color:#fff;margin:0 0 .25rem; }
.mist-login-subtitle { font-size:.82rem;color:var(--gold-light);margin:0; }
.mist-login-divider {
    display:flex;align-items:center;gap:.75rem;
    padding:1.25rem 2rem .5rem;
}
.mist-login-divider-line { flex:1;height:1px;background:#e4e8ef; }
.mist-login-divider-text { font-size:.75rem;font-weight:700;color:#9aa5b8;text-transform:uppercase;letter-spacing:.6px;white-space:nowrap; }
.mist-login-form { padding:.5rem 2rem 1.5rem; }
.form-control-lg {
    border-radius:10px !important;border:1.5px solid #e0e5ef !important;
    font-size:.92rem !important;padding:.7rem 1rem !important;
    transition:border-color .2s,box-shadow .2s;
}
.form-control-lg:focus {
    border-color:var(--gold) !important;
    box-shadow:0 0 0 3px rgba(201,168,76,.15) !important;
}
.btn-mist-primary {
    background:linear-gradient(135deg,var(--navy),#1a3a5c);
    border:none;color:#fff;font-weight:700;font-size:.95rem;
    padding:.8rem;border-radius:10px;transition:opacity .2s,transform .1s;
}
.btn-mist-primary:hover { opacity:.9;transform:translateY(-1px);color:#fff; }
.dropdown-item { font-size:.84rem;color:#5a6a80;padding:.35rem 0;text-decoration:none;display:block;transition:color .15s; }
.dropdown-item:hover { color:var(--navy); }
.mist-login-footer { background:#f8fafc;border-top:1px solid #eef0f5;padding:.9rem 2rem;text-align:center; }
.mist-login-footer p { font-size:.75rem;color:#9aa5b8;margin:0;line-height:1.7; }
.alert { border-radius:10px;font-size:.875rem; }
</style>
</head>
<body>
<div class="bg-glow bg-glow-1"></div>
<div class="bg-glow bg-glow-2"></div>
<div class="bg-grid"></div>

<div class="login-wrapper">
<div class="mist-login-card">
    <div class="mist-login-header">
        <img src="/qrepo/assets/mist-logo.svg" alt="MIST Logo" class="mist-login-logo">
        <h1 class="mist-login-title"><?= APP_NAME ?></h1>
        <p class="mist-login-subtitle">Create your account</p>
    </div>

    <div class="mist-login-divider">
        <span class="mist-login-divider-line"></span>
        <span class="mist-login-divider-text">Register</span>
        <span class="mist-login-divider-line"></span>
    </div>

    <div class="mist-login-form">
        <?php if ($error): ?>
        <div class="alert alert-danger py-2 mb-3">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="alert alert-success py-2 mb-3">
            <i class="bi bi-check-circle-fill me-2"></i><?= $success ?>
        </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" novalidate>
            <div class="mb-3">
                <input type="text" name="name" class="form-control form-control-lg"
                       placeholder="Full name" required autofocus
                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <input type="email" name="email" class="form-control form-control-lg"
                       placeholder="Email address" required autocomplete="username"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <input type="password" name="password" class="form-control form-control-lg"
                       placeholder="Password (min 6 characters)" required autocomplete="new-password">
            </div>
            <div class="mb-4">
                <input type="password" name="confirm_password" class="form-control form-control-lg"
                       placeholder="Confirm password" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-mist-primary w-100">
                <i class="bi bi-person-check me-2"></i>Create Account
            </button>
        </form>
        <?php endif; ?>

        <div class="mt-3">
            <a href="/qrepo/login.php" class="dropdown-item">
                Already have an account? Sign in here.
            </a>
        </div>
    </div>

</div><!-- /mist-login-card -->
</div><!-- /login-wrapper -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
