<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current  = $_POST['current_password'] ?? '';
    $new      = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    $stmt = db()->prepare("SELECT password FROM qrepo_admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $admin = $stmt->fetch();

    if (!password_verify($current, $admin['password'])) {
        $err = 'Current password is incorrect.';
    } elseif (strlen($new) < 6) {
        $err = 'New password must be at least 6 characters.';
    } elseif ($new !== $confirm) {
        $err = 'Passwords do not match.';
    } else {
        $hash = password_hash($new, PASSWORD_DEFAULT);
        db()->prepare("UPDATE qrepo_admins SET password = ? WHERE id = ?")->execute([$hash, $_SESSION['admin_id']]);
        $msg = 'Password changed successfully.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="/qrepo/assets/mist-logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php include __DIR__ . '/admin_styles.php'; ?>
</head>
<body>
<?php include __DIR__ . '/admin_nav.php'; ?>
<div class="admin-content">
<div class="container-fluid py-4">
    <h4 class="fw-bold mb-4"><i class="bi bi-key me-2 text-primary"></i>Change Password</h4>
    <div class="row justify-content-center">
        <div class="col-lg-5">
            <?php if ($msg): ?><div class="alert alert-success py-2"><?= $msg ?></div><?php endif; ?>
            <?php if ($err): ?><div class="alert alert-danger py-2"><?= $err ?></div><?php endif; ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">New Password</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6">
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 fw-semibold">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
