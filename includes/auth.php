<?php
require_once __DIR__ . '/config.php';

/* ─────────────────────────────────────────────────────────
   Permission map
   ───────────────────────────────────────────────────────── */
const ROLE_PERMISSIONS = [
    'viewer'           => ['view'],
    'submitter'        => ['view', 'upload_assigned'],
    'resource_manager' => ['view', 'upload_assigned', 'upload_any', 'manage_content'],
    'admin'            => ['view', 'upload_assigned', 'upload_any', 'manage_content', 'manage_users'],
];

const ROLE_LABELS = [
    'viewer'           => 'Viewer',
    'submitter'        => 'Submitter',
    'resource_manager' => 'Resource Manager',
    'admin'            => 'Admin',
];

/* ─────────────────────────────────────────────────────────
   Session
   ───────────────────────────────────────────────────────── */
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

/* ─────────────────────────────────────────────────────────
   Admin panel auth (qrepo_admins table)
   ───────────────────────────────────────────────────────── */
function isLoggedIn(): bool {
    startSession();
    return !empty($_SESSION['admin_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /qrepo/login.php');
        exit;
    }
}

/* ─────────────────────────────────────────────────────────
   Combined guards: admin table OR user with permission
   ───────────────────────────────────────────────────────── */
function isAdminPanelUser(): bool {
    startSession();
    if (!empty($_SESSION['admin_id'])) return true;
    if (empty($_SESSION['user_id'])) return false;
    $roles = $_SESSION['user_roles'] ?? [$_SESSION['user_role'] ?? 'viewer'];
    return !empty(array_intersect(['resource_manager','admin'], $roles));
}

function requireAdminPanel(): void {
    startSession();
    if (!isAdminPanelUser()) {
        header('Location: /qrepo/login.php');
        exit;
    }
}

function requireUserManagement(): void {
    startSession();
    $isTableAdmin    = !empty($_SESSION['admin_id']);
    $isRoleAdmin     = !empty($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'admin';
    if (!$isTableAdmin && !$isRoleAdmin) {
        http_response_code(403);
        exit('Access denied: user management requires admin role.');
    }
}

function logout(): void {
    startSession();
    $_SESSION = [];
    session_destroy();
}

/* ─────────────────────────────────────────────────────────
   User auth
   ───────────────────────────────────────────────────────── */
function isUserLoggedIn(): bool {
    startSession();
    return !empty($_SESSION['user_id']);
}

function requireUserLogin(): void {
    if (!isUserLoggedIn()) {
        $redirect = urlencode($_SERVER['REQUEST_URI']);
        header('Location: /qrepo/login.php?redirect=' . $redirect);
        exit;
    }
}

function getLoggedInUser(): ?array {
    startSession();
    if (empty($_SESSION['user_id'])) return null;
    return [
        'id'    => $_SESSION['user_id'],
        'name'  => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'role'  => $_SESSION['user_role'] ?? 'viewer',
    ];
}

/* ─────────────────────────────────────────────────────────
   Role & permission helpers
   ───────────────────────────────────────────────────────── */
function currentUserRole(): string {
    startSession();
    if (!empty($_SESSION['admin_id'])) return 'admin';
    // Return highest assigned role for display purposes
    $roles     = $_SESSION['user_roles'] ?? [$_SESSION['user_role'] ?? 'viewer'];
    $hierarchy = ['viewer','submitter','resource_manager','admin'];
    $highest   = 'viewer';
    foreach ($hierarchy as $r) {
        if (in_array($r, $roles)) $highest = $r;
    }
    return $highest;
}

function can(string $permission): bool {
    startSession();
    if (!empty($_SESSION['admin_id'])) return true;
    $roles = $_SESSION['user_roles'] ?? [$_SESSION['user_role'] ?? 'viewer'];
    foreach ($roles as $role) {
        if (in_array($permission, ROLE_PERMISSIONS[$role] ?? [])) return true;
    }
    return false;
}

function requirePermission(string $permission): void {
    startSession();
    $loggedIn = !empty($_SESSION['admin_id']) || !empty($_SESSION['user_id']);
    if (!$loggedIn) {
        header('Location: /qrepo/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    if (!can($permission)) {
        http_response_code(403);
        exit('Access denied: you do not have the required permission.');
    }
}

function canUploadToFolder(int $folderId): bool {
    if (can('upload_any')) return true;
    if (!can('upload_assigned')) return false;
    startSession();
    $userId = $_SESSION['user_id'] ?? 0;
    if (!$userId) return false;

    // Exact folder match
    $stmt = db()->prepare("SELECT 1 FROM qrepo_folder_access WHERE user_id=? AND folder_id=?");
    $stmt->execute([$userId, $folderId]);
    if ($stmt->fetchColumn()) return true;

    // Walk up the tree — check if any ancestor grants include_subfolders access
    $currentId = $folderId;
    while (true) {
        $row = db()->prepare("SELECT parent_id FROM qrepo_folders WHERE id=?");
        $row->execute([$currentId]);
        $r = $row->fetch();
        if (!$r || !$r['parent_id']) break;
        $parentId = (int)$r['parent_id'];
        $chk = db()->prepare(
            "SELECT 1 FROM qrepo_folder_access WHERE user_id=? AND folder_id=? AND include_subfolders=1"
        );
        $chk->execute([$userId, $parentId]);
        if ($chk->fetchColumn()) return true;
        $currentId = $parentId;
    }
    return false;
}

function getAssignedFolders(int $userId): array {
    $stmt = db()->prepare(
        "SELECT f.* FROM qrepo_folders f
         JOIN qrepo_folder_access fa ON fa.folder_id = f.id
         WHERE fa.user_id = ? ORDER BY f.name"
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/* ─────────────────────────────────────────────────────────
   Unified login
   ───────────────────────────────────────────────────────── */
function attemptUnifiedLogin(string $identifier, string $password): string|false {
    $identifier = trim($identifier);

    // 1. Admin table (username match)
    $stmt = db()->prepare("SELECT * FROM qrepo_admins WHERE username = ?");
    $stmt->execute([$identifier]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password'])) {
        startSession();
        $_SESSION['admin_id']       = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        return 'admin';
    }

    // 2. Users table (email match)
    $stmt = db()->prepare("SELECT * FROM qrepo_users WHERE email = ? AND status = 'active'");
    $stmt->execute([strtolower($identifier)]);
    $user = $stmt->fetch();
    if ($user && !empty($user['password']) && password_verify($password, $user['password'])) {
        startSession();
        $_SESSION['user_id']           = (int)$user['id'];
        $_SESSION['user_name']         = $user['name'];
        $_SESSION['user_email']        = $user['email'];
        $_SESSION['user_role']         = $user['role'] ?? 'viewer';
        $_SESSION['user_roles']        = $user['roles'] ? json_decode($user['roles'], true) : [$user['role'] ?? 'viewer'];
        $_SESSION['user_auth']         = 'local';
        $_SESSION['user_has_password'] = true;
        return $user['role'] ?? 'user';
    }

    return false;
}

function attemptUserLogin(string $email, string $password): bool {
    $result = attemptUnifiedLogin($email, $password);
    return $result !== false && $result !== 'admin';
}

function registerUser(string $name, string $email, string $password): bool|string {
    $email = strtolower(trim($email));
    $check = db()->prepare("SELECT id FROM qrepo_users WHERE email = ?");
    $check->execute([$email]);
    if ($check->fetch()) return 'An account with this email already exists.';
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = db()->prepare(
        "INSERT INTO qrepo_users (name, email, password, auth_provider, role) VALUES (?, ?, ?, 'local', 'viewer')"
    );
    $stmt->execute([trim($name), $email, $hash]);
    return true;
}

function isAnyLoggedIn(): bool {
    startSession();
    return !empty($_SESSION['admin_id']) || !empty($_SESSION['user_id']);
}

function requireAnyLogin(): void {
    if (!isAnyLoggedIn()) {
        header('Location: /qrepo/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function userLogout(): void {
    startSession();
    unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user_email'],
          $_SESSION['user_role'], $_SESSION['user_auth'], $_SESSION['user_has_password']);
    if (empty($_SESSION['admin_id'])) session_destroy();
}
