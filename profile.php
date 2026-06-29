<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireUserLogin();

$user = getLoggedInUser();

// Fetch fresh record
$stmt = db()->prepare("SELECT * FROM qrepo_users WHERE id = ?");
$stmt->execute([$user['id']]);
$record = $stmt->fetch();

$hasPassword = !empty($record['password']);
$provider    = $record['auth_provider'] ?? 'local';

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'set_password') {
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($new) < 6) {
            $err = 'Password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $err = 'Passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $newProvider = ($provider === 'microsoft') ? 'both' : $provider;
            db()->prepare("UPDATE qrepo_users SET password = ?, auth_provider = ? WHERE id = ?")
                 ->execute([$hash, $newProvider, $user['id']]);
            $_SESSION['user_has_password'] = true;
            $hasPassword = true;
            $provider    = $newProvider;
            $msg = 'Password set successfully. You can now log in with either method.';
        }
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $record['password'])) {
            $err = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $err = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $err = 'Passwords do not match.';
        } else {
            db()->prepare("UPDATE qrepo_users SET password = ? WHERE id = ?")
                 ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            $msg = 'Password changed successfully.';
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
<title>My Profile — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; }
:root { --gold:#c9a84c; --navy:#0d1b2e; --navy-mid:#1a3a5c; }
body {
    margin: 0; min-height: 100vh;
    font-family: 'Segoe UI', system-ui, sans-serif;
    background: #f0f4f8;
}
.top-nav {
    height: 56px;
    background: linear-gradient(135deg, var(--navy), var(--navy-mid));
    display: flex; align-items: center; padding: 0 1.5rem;
    border-bottom: 1px solid rgba(201,168,76,.2);
    position: sticky; top: 0; z-index: 100;
}
.top-nav .brand { display:flex;align-items:center;gap:.6rem;color:#fff;text-decoration:none;font-weight:700;font-size:.95rem; }
.top-nav .brand img { width:30px;height:30px;filter:brightness(0) invert(1); }
.top-nav-links a { color:rgba(255,255,255,.65);font-size:.85rem;text-decoration:none;padding:.3rem .7rem;border-radius:6px;transition:background .15s,color .15s; }
.top-nav-links a:hover { background:rgba(255,255,255,.1);color:#fff; }

.page-body { max-width: 680px; margin: 2.5rem auto; padding: 0 1rem; }

.profile-header {
    background: linear-gradient(135deg, var(--navy), var(--navy-mid));
    border-radius: 16px; padding: 2rem;
    display: flex; align-items: center; gap: 1.25rem; margin-bottom: 1.5rem;
    position: relative; overflow: hidden;
}
.profile-header::before {
    content: ''; position: absolute; inset: 0;
    background: linear-gradient(135deg,rgba(201,168,76,.08),transparent);
}
.profile-avatar {
    width: 64px; height: 64px; border-radius: 50%;
    background: linear-gradient(135deg, var(--gold), #f0d070);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem; font-weight: 800; color: var(--navy);
    flex-shrink: 0; position: relative; z-index: 1;
}
.profile-info { position: relative; z-index: 1; }
.profile-name  { font-size: 1.2rem; font-weight: 700; color: #fff; margin: 0 0 .2rem; }
.profile-email { font-size: .84rem; color: rgba(255,255,255,.6); margin: 0 0 .5rem; }
.auth-method-badges { display: flex; gap: .4rem; flex-wrap: wrap; }
.auth-badge {
    display: inline-flex; align-items: center; gap: .3rem;
    font-size: .7rem; font-weight: 700; padding: .2rem .6rem; border-radius: 100px;
}
.badge-ms365 { background: rgba(0,120,212,.2); color: #60bbff; border: 1px solid rgba(0,120,212,.3); }
.badge-local { background: rgba(201,168,76,.2); color: var(--gold); border: 1px solid rgba(201,168,76,.3); }
.badge-nopw  { background: rgba(255,100,100,.15); color: #ff8080; border: 1px solid rgba(255,100,100,.2); }

.card-section {
    background: #fff; border-radius: 14px; border: 1px solid #e4e8ef;
    box-shadow: 0 2px 12px rgba(0,0,0,.05); overflow: hidden; margin-bottom: 1.25rem;
}
.card-section-header {
    padding: 1rem 1.5rem; border-bottom: 1px solid #f0f2f6;
    display: flex; align-items: center; gap: .6rem;
    font-weight: 700; font-size: .92rem; color: #1a2a4a;
}
.card-section-header i { color: var(--gold); font-size: 1.05rem; }
.card-section-body { padding: 1.5rem; }

.form-label { font-weight: 600; font-size: .875rem; color: #3a4a60; margin-bottom: .4rem; }
.form-control {
    border-radius: 9px; border: 1.5px solid #dde2ee;
    font-size: .9rem; padding: .65rem .9rem;
    transition: border-color .2s, box-shadow .2s; background: #fafbfd;
}
.form-control:focus {
    border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,168,76,.14); background: #fff;
}
.btn-save {
    background: linear-gradient(135deg, var(--navy), var(--navy-mid));
    color: #fff; border: none; border-radius: 9px;
    padding: .65rem 1.5rem; font-weight: 700; font-size: .9rem;
    transition: opacity .2s; cursor: pointer;
}
.btn-save:hover { opacity: .88; }

.info-box {
    border-radius: 10px; padding: .85rem 1rem; font-size: .86rem;
    display: flex; align-items: flex-start; gap: .6rem; line-height: 1.55;
}
.info-box-blue { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; }
.info-box-gold { background: #fefce8; border: 1px solid #fde68a; color: #92400e; }
.info-box-green { background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }
.info-box i { flex-shrink: 0; margin-top: .1rem; }

.alert { border-radius: 10px; font-size: .875rem; }
</style>
</head>
<body>

<nav class="top-nav">
    <a href="/qrepo/" class="brand">
        <img src="/qrepo/assets/mist-logo.svg" alt="MIST">
        <span>MIST Question Repository</span>
    </a>
    <div class="top-nav-links ms-auto d-flex gap-1">
        <a href="/qrepo/"><i class="bi bi-house me-1"></i>Home</a>
        <a href="/qrepo/user_logout.php"><i class="bi bi-box-arrow-right me-1"></i>Sign out</a>
    </div>
</nav>

<div class="page-body">

    <!-- Profile header card -->
    <div class="profile-header">
        <div class="profile-avatar"><?= strtoupper(substr($record['name'], 0, 1)) ?></div>
        <div class="profile-info">
            <div class="profile-name"><?= htmlspecialchars($record['name']) ?></div>
            <div class="profile-email"><?= htmlspecialchars($record['email']) ?></div>
            <div class="auth-method-badges">
                <?php if (in_array($provider, ['microsoft','both'])): ?>
                <span class="auth-badge badge-ms365">
                    <i class="bi bi-microsoft"></i> Microsoft 365
                </span>
                <?php endif; ?>
                <?php if (in_array($provider, ['local','both'])): ?>
                <span class="auth-badge badge-local">
                    <i class="bi bi-key-fill"></i> Password Login
                </span>
                <?php endif; ?>
                <?php if (!$hasPassword): ?>
                <span class="auth-badge badge-nopw">
                    <i class="bi bi-exclamation-circle"></i> No password set
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($msg): ?>
    <div class="alert alert-success py-2 mb-3">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
    </div>
    <?php endif; ?>
    <?php if ($err): ?>
    <div class="alert alert-danger py-2 mb-3">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($err) ?>
    </div>
    <?php endif; ?>

    <?php if (!$hasPassword): ?>
    <!-- ── Set password (no password yet) ── -->
    <div class="card-section">
        <div class="card-section-header">
            <i class="bi bi-key-fill"></i> Set a Password
        </div>
        <div class="card-section-body">
            <div class="info-box info-box-blue mb-4">
                <i class="bi bi-info-circle-fill"></i>
                <div>
                    Your account was created via <strong>Microsoft 365</strong>. You can optionally set a password
                    so you can also sign in with your email and password — without needing Microsoft.
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="set_password">
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control"
                           placeholder="Minimum 6 characters" required minlength="6" autocomplete="new-password">
                </div>
                <div class="mb-4">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control"
                           placeholder="Repeat the password" required autocomplete="new-password">
                </div>
                <button type="submit" class="btn-save">
                    <i class="bi bi-check-lg me-1"></i>Set Password
                </button>
            </form>
        </div>
    </div>

    <?php else: ?>
    <!-- ── Change password (already has one) ── -->
    <div class="card-section">
        <div class="card-section-header">
            <i class="bi bi-shield-lock-fill"></i> Change Password
        </div>
        <div class="card-section-body">
            <?php if ($provider === 'both'): ?>
            <div class="info-box info-box-green mb-4">
                <i class="bi bi-check-circle-fill"></i>
                <div>
                    You can sign in using <strong>either</strong> Microsoft 365 or your email &amp; password.
                </div>
            </div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="mb-3">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="mb-3">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control"
                           placeholder="Minimum 6 characters" required minlength="6" autocomplete="new-password">
                </div>
                <div class="mb-4">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control"
                           required autocomplete="new-password">
                </div>
                <button type="submit" class="btn-save">
                    <i class="bi bi-check-lg me-1"></i>Update Password
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Login methods summary -->
    <div class="card-section">
        <div class="card-section-header">
            <i class="bi bi-person-badge"></i> Your Login Methods
        </div>
        <div class="card-section-body">
            <div class="d-flex flex-column gap-2">
                <div class="d-flex align-items-center justify-content-between p-3 rounded-3"
                     style="background:<?= in_array($provider,['microsoft','both']) ? '#eff6ff' : '#f8f9fc' ?>;border:1px solid <?= in_array($provider,['microsoft','both']) ? '#bfdbfe' : '#e4e8ef' ?>">
                    <div class="d-flex align-items-center gap-2">
                        <span style="display:grid;grid-template-columns:1fr 1fr;gap:2px;width:18px;height:18px;flex-shrink:0">
                            <span style="background:#f25022;border-radius:1px"></span>
                            <span style="background:#7fba00;border-radius:1px"></span>
                            <span style="background:#00a4ef;border-radius:1px"></span>
                            <span style="background:#ffb900;border-radius:1px"></span>
                        </span>
                        <div>
                            <div style="font-weight:600;font-size:.875rem">Microsoft 365</div>
                            <div style="font-size:.76rem;color:#94a3b8">MIST institutional email</div>
                        </div>
                    </div>
                    <?php if (in_array($provider, ['microsoft','both'])): ?>
                    <span class="badge bg-success">Active</span>
                    <?php else: ?>
                    <span class="badge bg-secondary">Not linked</span>
                    <?php endif; ?>
                </div>

                <div class="d-flex align-items-center justify-content-between p-3 rounded-3"
                     style="background:<?= $hasPassword ? '#f0fdf4' : '#f8f9fc' ?>;border:1px solid <?= $hasPassword ? '#bbf7d0' : '#e4e8ef' ?>">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-key-fill" style="font-size:1.1rem;color:<?= $hasPassword ? 'var(--gold)' : '#cbd5e1' ?>"></i>
                        <div>
                            <div style="font-weight:600;font-size:.875rem">Email &amp; Password</div>
                            <div style="font-size:.76rem;color:#94a3b8"><?= htmlspecialchars($record['email']) ?></div>
                        </div>
                    </div>
                    <?php if ($hasPassword): ?>
                    <span class="badge bg-success">Active</span>
                    <?php else: ?>
                    <span class="badge" style="background:#fef3c7;color:#92400e">Not set</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
