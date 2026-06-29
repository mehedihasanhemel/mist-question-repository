<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireUserLogin();

$currentUser  = getLoggedInUser();
$folderId     = isset($_GET['folder']) ? (int)$_GET['folder'] : null;
$searchQuery  = trim($_GET['q'] ?? '');
$currentFolder = $folderId ? getFolder($folderId) : null;
$breadcrumb   = $folderId ? getFolderBreadcrumb($folderId) : [];
$files        = $folderId ? getFilesInFolder($folderId) : [];
$tree         = getFolderTree();

// Search across all files
$searchResults = [];
if ($searchQuery !== '') {
    $stmt = db()->prepare(
        "SELECT f.*, fo.name AS folder_name, fo.id AS folder_id_ref
         FROM qrepo_files f
         JOIN qrepo_folders fo ON fo.id = f.folder_id
         WHERE f.title LIKE ? OR f.original_name LIKE ?
         ORDER BY f.title LIMIT 50"
    );
    $stmt->execute(["%$searchQuery%", "%$searchQuery%"]);
    $searchResults = $stmt->fetchAll();
}

// Stats for hero cards
$totalFiles   = (int) db()->query("SELECT COUNT(*) FROM qrepo_files")->fetchColumn();
$totalFolders = (int) db()->query("SELECT COUNT(*) FROM qrepo_folders")->fetchColumn();
$depts        = (int) db()->query("SELECT COUNT(*) FROM qrepo_folders WHERE parent_id IS NULL")->fetchColumn();

function renderTree(array $folders, int $depth = 0, ?int $activeFolderId = null): void {
    foreach ($folders as $folder) {
        $hasChildren  = !empty($folder['children']);
        $isActive     = ($activeFolderId === (int)$folder['id']);
        $isAncestor   = isAncestorOf($folder, $activeFolderId);
        $collapseId   = 'folder-' . $folder['id'];
        $expandClass  = ($isActive || $isAncestor) ? 'show' : '';
        $indent       = $depth * 18;
        ?>
        <div class="tree-item">
            <div class="tree-row d-flex align-items-center <?= $isActive ? 'active' : '' ?>"
                 style="padding-left:<?= 10 + $indent ?>px">
                <?php if ($hasChildren): ?>
                <button class="btn btn-link tree-toggle p-0 me-1"
                        data-bs-toggle="collapse"
                        data-bs-target="#<?= $collapseId ?>"
                        aria-expanded="<?= $expandClass ? 'true' : 'false' ?>">
                    <i class="bi bi-chevron-right toggle-icon <?= $expandClass ? 'rotated' : '' ?>"></i>
                </button>
                <?php else: ?>
                <span class="tree-spacer me-1"></span>
                <?php endif; ?>
                <i class="bi bi-folder2<?= $isActive ? '-open' : '' ?> folder-icon me-2"></i>
                <a href="?folder=<?= $folder['id'] ?>" class="tree-link flex-grow-1 text-truncate">
                    <?= htmlspecialchars($folder['name']) ?>
                </a>
                <?php if ($folder['file_count'] > 0): ?>
                <span class="tree-badge"><?= $folder['file_count'] ?></span>
                <?php endif; ?>
            </div>
            <?php if ($hasChildren): ?>
            <div class="collapse <?= $expandClass ?>" id="<?= $collapseId ?>">
                <?php renderTree($folder['children'], $depth + 1, $activeFolderId); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

function isAncestorOf(array $folder, ?int $activeId): bool {
    if ($activeId === null) return false;
    foreach ($folder['children'] as $child) {
        if ((int)$child['id'] === $activeId) return true;
        if (isAncestorOf($child, $activeId)) return true;
    }
    return false;
}

$fileIcons = [
    'pdf'  => ['bi-file-earmark-pdf-fill',   'icon-pdf',   true],
    'doc'  => ['bi-file-earmark-word-fill',   'icon-word',  false],
    'docx' => ['bi-file-earmark-word-fill',   'icon-word',  false],
    'ppt'  => ['bi-file-earmark-ppt-fill',    'icon-ppt',   false],
    'pptx' => ['bi-file-earmark-ppt-fill',    'icon-ppt',   false],
    'xls'  => ['bi-file-earmark-excel-fill',  'icon-excel', false],
    'xlsx' => ['bi-file-earmark-excel-fill',  'icon-excel', false],
    'jpg'  => ['bi-file-earmark-image-fill',  'icon-img',   true],
    'png'  => ['bi-file-earmark-image-fill',  'icon-img',   true],
    'zip'  => ['bi-file-earmark-zip-fill',    'icon-zip',   false],
];
function getFileIcon(string $fn, array $icons): array {
    $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
    return $icons[$ext] ?? ['bi-file-earmark-fill', 'icon-default', false];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="/qrepo/assets/mist-logo.svg">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
/* ── Reset & base ────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; }
:root {
    --gold:        #c9a84c;
    --gold-light:  #e8c96d;
    --navy:        #0d1b2e;
    --navy-2:      #122240;
    --teal:        #0fa3b1;
    --blue-acc:    #3b82f6;
    --sidebar-w:   280px;
    --topbar-h:    58px;
}
body { margin: 0; font-family: 'Segoe UI', system-ui, sans-serif; background: #f4f6f9; }

/* ── Top nav ─────────────────────────────────────────────── */
.top-nav {
    position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
    height: var(--topbar-h);
    background: rgba(13,27,46,.97);
    backdrop-filter: blur(12px);
    display: flex; align-items: center; padding: 0 1.5rem;
    border-bottom: 1px solid rgba(201,168,76,.25);
}
.top-nav .brand {
    display: flex; align-items: center; gap: .6rem;
    font-weight: 700; font-size: 1.05rem; color: #fff; text-decoration: none;
}
.top-nav .brand .brand-logo {
    width: 34px; height: 34px; background: linear-gradient(135deg,var(--gold),#f0d070);
    border-radius: 8px; display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem; color: var(--navy); flex-shrink: 0;
}
.top-nav .brand-sub { font-size: .7rem; color: var(--gold); font-weight: 400; letter-spacing: .4px; display: block; }
.top-nav .nav-pills-top { display: flex; gap: .25rem; margin-left: 2rem; }
.top-nav .nav-pills-top a {
    color: rgba(255,255,255,.65); font-size: .84rem; padding: .3rem .7rem;
    border-radius: 6px; text-decoration: none; transition: background .15s, color .15s;
}
.top-nav .nav-pills-top a:hover { background: rgba(255,255,255,.08); color: #fff; }

/* ── Hero ────────────────────────────────────────────────── */
.mist-hero-wrapper { padding-top: var(--topbar-h); }
.mist-hero {
    position: relative; overflow: hidden;
    background: var(--navy);
    padding: 5rem 0 0;
}
.mist-bg-glow {
    position: absolute; border-radius: 50%; filter: blur(80px); opacity: .35; pointer-events: none;
}
.mist-glow-1 { width: 520px; height: 520px; background: #1e3a6e; top: -100px; left: -120px; }
.mist-glow-2 { width: 400px; height: 400px; background: #0a2a4a; top: 20px; right: -80px; }
.mist-glow-3 { width: 300px; height: 300px; background: rgba(201,168,76,.18); bottom: 60px; left: 35%; }
.mist-bg-grid {
    position: absolute; inset: 0; pointer-events: none; opacity: .06;
    background-image: linear-gradient(rgba(255,255,255,.6) 1px,transparent 1px),
                      linear-gradient(90deg,rgba(255,255,255,.6) 1px,transparent 1px);
    background-size: 40px 40px;
}
.mist-orb {
    position: absolute; border-radius: 50%; pointer-events: none;
    animation: orbFloat 8s ease-in-out infinite;
}
.mist-orb-1 {
    width: 180px; height: 180px;
    background: radial-gradient(circle,rgba(201,168,76,.22) 0%,transparent 70%);
    top: 40px; right: 18%;
    animation-delay: 0s;
}
.mist-orb-2 {
    width: 120px; height: 120px;
    background: radial-gradient(circle,rgba(59,130,246,.2) 0%,transparent 70%);
    bottom: 80px; left: 25%;
    animation-delay: -4s;
}
@keyframes orbFloat {
    0%,100% { transform: translateY(0); }
    50%      { transform: translateY(-18px); }
}

.mist-hero-inner { position: relative; z-index: 2; }

/* Badge pill */
.mist-badge-pill {
    display: inline-flex; align-items: center; gap: .45rem;
    background: rgba(201,168,76,.12); border: 1px solid rgba(201,168,76,.35);
    color: var(--gold-light); font-size: .78rem; font-weight: 600;
    padding: .3rem .85rem; border-radius: 100px; margin-bottom: 1.25rem;
    letter-spacing: .3px;
}
.mist-badge-dot {
    width: 7px; height: 7px; border-radius: 50%; background: var(--gold);
    animation: pulse 2s ease-in-out infinite;
}
@keyframes pulse { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.5;transform:scale(1.4)} }

.mist-hero-heading {
    font-size: clamp(2rem, 4.5vw, 3.2rem);
    font-weight: 800; color: #fff; line-height: 1.15;
    margin: 0 0 1rem;
}
.mist-heading-gold { color: var(--gold); }
.mist-hero-sub {
    color: rgba(255,255,255,.6); font-size: 1rem; line-height: 1.65;
    max-width: 480px; margin-bottom: 2.25rem;
}

/* Hero stat strip */
.mist-stats { display: flex; gap: 2.5rem; margin-bottom: 2.5rem; }
.mist-stat-val { font-size: 1.7rem; font-weight: 800; color: var(--gold); line-height: 1; }
.mist-stat-label { font-size: .75rem; color: rgba(255,255,255,.5); margin-top: .2rem; }

/* Feature cards (right column) */
.mist-feat-wrap { display: flex; flex-direction: column; gap: 1rem; }
.mist-feat-card {
    display: flex; align-items: flex-start; gap: 1rem;
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.09);
    border-radius: 14px; padding: 1.1rem 1.25rem;
    backdrop-filter: blur(6px);
    transition: border-color .2s, background .2s;
}
.mist-feat-card:hover { background: rgba(255,255,255,.09); border-color: rgba(201,168,76,.3); }
.mist-feat-icon-wrap {
    width: 44px; height: 44px; border-radius: 11px; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 1.25rem;
    background: rgba(201,168,76,.18); color: var(--gold);
}
.mist-feat-icon-teal  { background: rgba(15,163,177,.18); color: var(--teal); }
.mist-feat-icon-blue  { background: rgba(59,130,246,.18);  color: var(--blue-acc); }
.mist-feat-title { font-weight: 700; color: #fff; font-size: .9rem; }
.mist-feat-desc  { font-size: .8rem; color: rgba(255,255,255,.5); margin-top: .25rem; line-height: 1.5; }

/* Search row */
.mist-hero-search-row { padding-bottom: 3rem; }
.mist-search-inner {
    position: relative; display: flex; align-items: center;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.18);
    border-radius: 12px; overflow: hidden; max-width: 700px;
    transition: border-color .2s;
}
.mist-search-inner:focus-within { border-color: var(--gold); }
.mist-search-icon { position: absolute; left: 1rem; color: rgba(255,255,255,.4); font-size: 1rem; pointer-events:none; }
.mist-search-input {
    flex: 1; background: transparent; border: none; outline: none;
    color: #fff; font-size: .95rem; padding: .85rem 1rem .85rem 2.8rem;
}
.mist-search-input::placeholder { color: rgba(255,255,255,.4); }
.mist-search-btn {
    background: var(--gold); color: var(--navy); border: none;
    font-weight: 700; font-size: .9rem; padding: .85rem 1.6rem;
    cursor: pointer; transition: background .2s; white-space: nowrap;
}
.mist-search-btn:hover { background: var(--gold-light); }

.mist-hero-links { display: flex; align-items: center; gap: .5rem; margin-top: .85rem; }
.mist-hero-link { color: rgba(255,255,255,.55); font-size: .85rem; text-decoration: none; transition: color .15s; }
.mist-hero-link:hover { color: var(--gold); }
.mist-hero-link-sep { color: rgba(255,255,255,.25); }

/* Wave */
.mist-hero-wave svg { display: block; }

/* ── Browse section header ───────────────────────────────── */
.mist-browse-header { padding: 2.5rem 0 1.5rem; }
.mist-browse-title-row { display: flex; align-items: center; gap: .9rem; }
.mist-browse-accent-bar { width: 4px; height: 28px; background: var(--gold); border-radius: 4px; flex-shrink:0; }
.mist-browse-heading { font-size: 1.4rem; font-weight: 800; margin: 0; color: #1a2a4a; }

/* ── Layout below hero ───────────────────────────────────── */
.browse-layout { display: flex; gap: 1.5rem; align-items: flex-start; }

/* Sidebar */
.sidebar {
    width: var(--sidebar-w); min-width: var(--sidebar-w); flex-shrink: 0;
    background: #fff; border-radius: 14px;
    border: 1px solid #e4e8ef;
    box-shadow: 0 2px 12px rgba(0,0,0,.06);
    position: sticky; top: calc(var(--topbar-h) + 1rem);
    max-height: calc(100vh - var(--topbar-h) - 2rem); overflow-y: auto;
}
.sidebar-header {
    padding: .85rem 1rem; border-bottom: 1px solid #eef0f5;
    font-size: .75rem; font-weight: 800; text-transform: uppercase;
    letter-spacing: .7px; color: #7a8aaa;
    display: flex; align-items: center; gap: .4rem;
}
.tree-row {
    display: flex; align-items: center; padding: .38rem .5rem;
    border-radius: 8px; margin: 1px 6px; cursor: pointer; transition: background .13s;
}
.tree-row:hover { background: #f0f5ff; }
.tree-row.active { background: #eff6ff; }
.tree-row.active .tree-link { color: #1d4ed8; font-weight: 700; }
.tree-row.active .folder-icon { color: var(--gold) !important; }
.tree-toggle { width: 18px; height: 18px; border:none; background:none; padding:0; color:#9aa5b8; flex-shrink:0; }
.tree-toggle:hover { color:#333; }
.toggle-icon { font-size: .72rem; transition: transform .2s; }
.toggle-icon.rotated { transform: rotate(90deg); }
.tree-spacer { width: 18px; flex-shrink:0; }
.folder-icon { font-size: .95rem; flex-shrink:0; color: #5a7fc4; }
.tree-link { font-size: .84rem; color: #3a4a60; text-decoration:none; flex:1; min-width:0; }
.tree-link:hover { color: #1d4ed8; }
.tree-badge {
    font-size: .68rem; background: #eef2ff; color: #4f6fc6;
    border-radius: 100px; padding: .05rem .42rem; font-weight: 700; flex-shrink:0;
}

/* Main content */
.main-content { flex: 1; min-width: 0; }

/* File cards */
.file-card {
    display: flex; align-items: center; gap: 1rem;
    background: #fff; border: 1px solid #e4e8ef; border-radius: 12px;
    padding: 1rem 1.25rem; text-decoration: none; color: inherit;
    transition: box-shadow .15s, border-color .15s, transform .1s;
}
.file-card:hover {
    box-shadow: 0 6px 20px rgba(0,0,0,.1);
    border-color: #b8cbf0; transform: translateY(-1px); color: inherit;
}
.file-icon { font-size: 2rem; flex-shrink:0; }
.icon-pdf   { color: #e53e3e; }
.icon-word  { color: #2b579a; }
.icon-ppt   { color: #d24726; }
.icon-excel { color: #1d6f42; }
.icon-img   { color: #0ea5e9; }
.icon-zip   { color: #7c3aed; }
.icon-default { color: #94a3b8; }
.file-meta { font-size: .76rem; color: #94a3b8; margin-top: .2rem; }
.file-dl { color: #94a3b8; font-size: .9rem; }

/* Department quick-link cards (home) */
.dept-card {
    background: #fff; border: 1px solid #e4e8ef; border-radius: 14px;
    padding: 1.5rem; text-decoration: none; color: inherit;
    display: block; transition: box-shadow .15s, border-color .15s, transform .1s;
}
.dept-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.1); border-color: var(--gold); transform: translateY(-2px); color:inherit; }
.dept-card .dept-icon {
    width: 48px; height: 48px; border-radius: 12px; margin-bottom: 1rem;
    display: flex; align-items: center; justify-content: center; font-size: 1.5rem;
}
.dept-card .dept-name { font-weight: 700; font-size: 1rem; color: #1a2a4a; }
.dept-card .dept-meta { font-size: .8rem; color: #94a3b8; margin-top: .25rem; }
.dept-accent-bar { height: 3px; border-radius: 2px; margin-bottom: 1rem; }

/* Breadcrumb */
.breadcrumb { font-size: .84rem; margin-bottom: 1rem; }
.breadcrumb-item + .breadcrumb-item::before { content: "›"; }

/* Empty state */
.empty-state { text-align:center; padding: 3.5rem 1.5rem; color:#c0c8d8; }
.empty-state i { font-size: 3.5rem; display:block; margin-bottom:.75rem; }

/* Search results */
.search-result-item {
    display: flex; align-items: center; gap: .85rem;
    background:#fff; border:1px solid #e4e8ef; border-radius:10px;
    padding:.8rem 1rem; text-decoration:none; color:inherit;
    transition: box-shadow .15s;
}
.search-result-item:hover { box-shadow:0 4px 16px rgba(0,0,0,.08); color:inherit; }
.search-badge { font-size:.7rem; background:#f0f4ff; color:#4f6fc6; border-radius:6px; padding:.15rem .5rem; font-weight:600; }

@media(max-width:768px) {
    .sidebar { display:none; }
    .mist-stats { gap:1.5rem; }
    .mist-feat-wrap .mist-feat-card:last-child { display:none; }
}
</style>
</head>
<body>

<?php
// Show first-login banner once, then clear it
$showFirstLoginBanner = !empty($_SESSION['ms_first_login']);
if ($showFirstLoginBanner) unset($_SESSION['ms_first_login']);
?>

<?php if ($showFirstLoginBanner): ?>
<div style="background:#1e3a5c;border-bottom:1px solid rgba(201,168,76,.35);padding:.65rem 1.5rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
    <i class="bi bi-microsoft" style="color:#60bbff;font-size:1.1rem;flex-shrink:0"></i>
    <span style="color:#e2eaf6;font-size:.875rem;flex:1">
        <strong style="color:#fff">Welcome!</strong>
        Your MIST Microsoft 365 account has been linked.
        You can optionally <a href="/qrepo/profile.php" style="color:var(--gold);font-weight:700">set a password</a>
        to also sign in without Microsoft.
    </span>
    <a href="/qrepo/profile.php" style="background:rgba(201,168,76,.2);color:var(--gold);border:1px solid rgba(201,168,76,.35);border-radius:7px;padding:.3rem .85rem;font-size:.82rem;font-weight:700;text-decoration:none;white-space:nowrap;">
        Set Password →
    </a>
</div>
<?php endif; ?>

<!-- ── Top nav ── -->
<nav class="top-nav">
    <a href="/qrepo/" class="brand">
        <img src="/qrepo/assets/mist-logo.svg" alt="MIST" style="width:36px;height:36px;filter:brightness(0) invert(1);flex-shrink:0;">
        <div>
            <span style="display:block">MIST Question Repository</span>
            <span class="brand-sub">Military Institute of Science &amp; Technology</span>
        </div>
    </a>
    <div class="nav-pills-top d-none d-md-flex">
        <a href="/qrepo/"><i class="bi bi-house me-1"></i>Home</a>
        <a href="/qrepo/?browse=1"><i class="bi bi-diagram-3 me-1"></i>Browse</a>
        <?php if (can('upload_assigned')): ?>
        <a href="/qrepo/submit.php"><i class="bi bi-upload me-1"></i>Upload</a>
        <?php endif; ?>
    </div>
    <div class="ms-auto d-flex align-items-center gap-2">
        <a href="/qrepo/profile.php" class="d-none d-md-flex align-items-center gap-1 text-decoration-none"
           style="color:rgba(255,255,255,.7);font-size:.84rem;" title="My Profile">
            <i class="bi bi-person-circle"></i>
            <span><?= htmlspecialchars($currentUser['name']) ?></span>
        </a>
        <a href="/qrepo/user_logout.php" class="btn btn-sm" style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.15);font-size:.82rem;">
            <i class="bi bi-box-arrow-right me-1"></i>Sign out
        </a>
        <?php if (can('manage_content')): ?>
        <a href="/qrepo/admin/drive.php" class="btn btn-sm d-none d-md-inline-flex" style="background:rgba(201,168,76,.15);color:var(--gold);border:1px solid rgba(201,168,76,.3);font-weight:600;">
            <i class="bi bi-folder2-open me-1"></i>Drive
        </a>
        <?php endif; ?>
        <?php if (isLoggedIn()): ?>
        <a href="/qrepo/admin/" class="btn btn-sm d-none d-md-inline-flex" style="background:rgba(201,168,76,.15);color:var(--gold);border:1px solid rgba(201,168,76,.3);font-weight:600;">
            <i class="bi bi-shield-lock me-1"></i>Admin
        </a>
        <?php endif; ?>
    </div>
</nav>

<!-- ── Hero ── -->
<div class="mist-hero-wrapper">
<section class="mist-hero" aria-label="Repository hero">
    <div class="mist-bg-glow mist-glow-1"></div>
    <div class="mist-bg-glow mist-glow-2"></div>
    <div class="mist-bg-glow mist-glow-3"></div>
    <div class="mist-bg-grid"></div>
    <div class="mist-orb mist-orb-1"></div>
    <div class="mist-orb mist-orb-2"></div>

    <div class="container mist-hero-inner">
        <div class="row align-items-center gy-4">
            <!-- Left: text -->
            <div class="col-lg-6">
                <div class="mist-badge-pill">
                    <span class="mist-badge-dot"></span>
                    Military Institute of Science and Technology
                </div>
                <h1 class="mist-hero-heading">
                    Open Access to<br>
                    <span class="mist-heading-gold">Question Papers</span>
                </h1>
                <p class="mist-hero-sub">
                    Browse and download exam question papers organized by department,
                    course, and year — all in one place.
                </p>
                <div class="mist-stats">
                    <div>
                        <div class="mist-stat-val"><?= $totalFiles ?></div>
                        <div class="mist-stat-label">Question Papers</div>
                    </div>
                    <div>
                        <div class="mist-stat-val"><?= $depts ?></div>
                        <div class="mist-stat-label">Departments</div>
                    </div>
                    <div>
                        <div class="mist-stat-val"><?= $totalFolders ?></div>
                        <div class="mist-stat-label">Total Folders</div>
                    </div>
                </div>
            </div>
            <!-- Right: feature cards -->
            <div class="col-lg-6">
                <div class="mist-feat-wrap">
                    <div class="mist-feat-card">
                        <div class="mist-feat-icon-wrap"><i class="bi bi-building"></i></div>
                        <div class="mist-feat-body">
                            <div class="mist-feat-title">Department-wise Browse</div>
                            <div class="mist-feat-desc">Navigate questions by CSE, EEE, ME and other departments</div>
                        </div>
                    </div>
                    <div class="mist-feat-card">
                        <div class="mist-feat-icon-wrap mist-feat-icon-teal"><i class="bi bi-file-earmark-pdf"></i></div>
                        <div class="mist-feat-body">
                            <div class="mist-feat-title">Exam Question Papers</div>
                            <div class="mist-feat-desc">Midterm, final, and class test papers organized by year</div>
                        </div>
                    </div>
                    <div class="mist-feat-card">
                        <div class="mist-feat-icon-wrap mist-feat-icon-blue"><i class="bi bi-download"></i></div>
                        <div class="mist-feat-body">
                            <div class="mist-feat-title">Free Download</div>
                            <div class="mist-feat-desc">Download PDFs and documents instantly — no login required</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search -->
        <div class="row mist-hero-search-row mt-4">
            <div class="col-12">
                <form action="/qrepo/" method="GET">
                    <div class="mist-search-inner">
                        <i class="bi bi-search mist-search-icon"></i>
                        <input type="text" name="q" class="mist-search-input"
                               placeholder="Search question papers, courses, subjects…"
                               value="<?= htmlspecialchars($searchQuery) ?>" autocomplete="off" aria-label="Search">
                        <button type="submit" class="mist-search-btn">Search</button>
                    </div>
                </form>
                <div class="mist-hero-links">
                    <a href="/qrepo/#browse" class="mist-hero-link">Browse Departments</a>
                    <span class="mist-hero-link-sep">·</span>
                    <a href="/qrepo/admin/" class="mist-hero-link">Admin Panel</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Wave -->
    <div class="mist-hero-wave" aria-hidden="true">
        <svg viewBox="0 0 1440 60" fill="none" xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" style="width:100%;height:60px;">
            <path d="M0 60 C360 0 1080 0 1440 60 L1440 60 L0 60 Z" fill="#f4f6f9"></path>
        </svg>
    </div>
</section>

<!-- ── Browse section ── -->
<div id="browse" class="container mist-browse-header">
    <div class="mist-browse-title-row">
        <div class="mist-browse-accent-bar"></div>
        <h2 class="mist-browse-heading">
            <?php if ($searchQuery): ?>
                Search Results for "<?= htmlspecialchars($searchQuery) ?>"
            <?php elseif ($currentFolder): ?>
                <i class="bi bi-folder2-open me-2" style="color:var(--gold)"></i><?= htmlspecialchars($currentFolder['name']) ?>
            <?php else: ?>
                Browse Departments &amp; Courses
            <?php endif; ?>
        </h2>
    </div>
</div>

<div class="container pb-5">
<?php if ($searchQuery): ?>
    <!-- ── Search results ── -->
    <?php if (empty($searchResults)): ?>
        <div class="empty-state">
            <i class="bi bi-search"></i>
            <p class="fw-semibold">No results for "<?= htmlspecialchars($searchQuery) ?>"</p>
            <small>Try different keywords.</small>
        </div>
    <?php else: ?>
        <p class="text-muted mb-3"><?= count($searchResults) ?> result(s) found</p>
        <div class="d-flex flex-column gap-2">
            <?php foreach ($searchResults as $sr):
                [$icon, $cls, $canView] = getFileIcon($sr['original_name'], $fileIcons);
                $title = htmlspecialchars($sr['title'], ENT_QUOTES);
            ?>
            <div class="search-result-item" role="button"
                 onclick="openViewer(<?= $sr['id'] ?>, '<?= $title ?>', <?= $canView ? 'true' : 'false' ?>)">
                <i class="bi <?= $icon ?> <?= $cls ?>" style="font-size:1.6rem;flex-shrink:0"></i>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold text-truncate"><?= htmlspecialchars($sr['title']) ?></div>
                    <div class="file-meta">
                        <span class="search-badge"><i class="bi bi-folder2 me-1"></i><?= htmlspecialchars($sr['folder_name']) ?></span>
                        &nbsp;<?= htmlspecialchars($sr['original_name']) ?>
                        &nbsp;·&nbsp;<?= formatFileSize($sr['file_size']) ?>
                    </div>
                </div>
                <i class="bi bi-eye text-muted flex-shrink-0" title="View"></i>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="mt-3">
            <a href="/qrepo/" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Clear search
            </a>
        </div>
    <?php endif; ?>

<?php elseif ($currentFolder || $folderId): ?>
    <!-- ── Folder view with sidebar ── -->
    <div class="browse-layout">
        <!-- Sidebar tree -->
        <aside class="sidebar">
            <div class="sidebar-header"><i class="bi bi-diagram-3"></i> Question Tree</div>
            <div class="pt-1 pb-2">
                <?php renderTree($tree, 0, $folderId); ?>
            </div>
        </aside>
        <!-- Content -->
        <div class="main-content">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/qrepo/">Home</a></li>
                    <?php foreach ($breadcrumb as $i => $crumb): ?>
                        <?php if ($i === count($breadcrumb) - 1): ?>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($crumb['name']) ?></li>
                        <?php else: ?>
                            <li class="breadcrumb-item"><a href="?folder=<?= $crumb['id'] ?>"><?= htmlspecialchars($crumb['name']) ?></a></li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ol>
            </nav>

            <?php if (empty($files)): ?>
                <div class="empty-state">
                    <i class="bi bi-folder2-open"></i>
                    <p class="fw-semibold">No files in this folder</p>
                    <small>Select a sub-folder from the tree on the left.</small>
                </div>
            <?php else: ?>
                <p class="text-muted mb-3" style="font-size:.85rem;"><?= count($files) ?> file(s)</p>
                <div class="row g-3">
                    <?php foreach ($files as $file):
                        [$icon, $cls, $canView] = getFileIcon($file['original_name'], $fileIcons);
                        $exists = file_exists(UPLOAD_DIR . $file['filename']);
                        $title  = htmlspecialchars($file['title'], ENT_QUOTES);
                    ?>
                    <div class="col-12 col-xl-6">
                        <?php if ($exists): ?>
                        <div class="file-card" role="button"
                             onclick="openViewer(<?= $file['id'] ?>, '<?= $title ?>', <?= $canView ? 'true' : 'false' ?>)">
                        <?php else: ?>
                        <div class="file-card" style="opacity:.5;cursor:default">
                        <?php endif; ?>
                            <i class="bi <?= $icon ?> file-icon <?= $cls ?>"></i>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate"><?= htmlspecialchars($file['title']) ?></div>
                                <div class="file-meta">
                                    <?= htmlspecialchars($file['original_name']) ?>
                                    &nbsp;·&nbsp;<?= formatFileSize($file['file_size']) ?>
                                    &nbsp;·&nbsp;<?= date('d M Y', strtotime($file['uploaded_at'])) ?>
                                </div>
                            </div>
                            <?php if ($exists): ?>
                            <i class="bi bi-eye file-dl" title="View"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- ── Home: department grid ── -->
    <?php
    $deptColors = [
        ['#c9a84c','#f0d070','bi-cpu'],
        ['#0fa3b1','#5dd3de','bi-lightning-charge'],
        ['#3b82f6','#7cb9f4','bi-gear'],
        ['#10b981','#5ee7b5','bi-diagram-2'],
        ['#8b5cf6','#c4b5fd','bi-book'],
        ['#ef4444','#fc8888','bi-tools'],
    ];
    $di = 0;
    ?>
    <div class="row g-3 mb-4">
        <?php foreach ($tree as $dept):
            [$c1,$c2,$icon] = $deptColors[$di++ % count($deptColors)];
            $courseCount = count($dept['children']);
        ?>
        <div class="col-sm-6 col-lg-4">
            <a href="?folder=<?= $dept['id'] ?>" class="dept-card">
                <div class="dept-accent-bar" style="background:linear-gradient(90deg,<?= $c1 ?>,<?= $c2 ?>)"></div>
                <div class="dept-icon" style="background:<?= $c1 ?>22;color:<?= $c1 ?>">
                    <i class="bi <?= $icon ?>"></i>
                </div>
                <div class="dept-name"><?= htmlspecialchars($dept['name']) ?></div>
                <div class="dept-meta">
                    <?= $courseCount ?> course<?= $courseCount !== 1 ? 's' : '' ?>
                    &nbsp;·&nbsp;
                    <?= $dept['file_count'] ?> file<?= $dept['file_count'] !== 1 ? 's' : '' ?>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Also show sidebar + welcome for desktop hint -->
    <div class="browse-layout mt-2">
        <aside class="sidebar">
            <div class="sidebar-header"><i class="bi bi-diagram-3"></i> Full Question Tree</div>
            <div class="pt-1 pb-2">
                <?php renderTree($tree, 0, null); ?>
            </div>
        </aside>
        <div class="main-content">
            <div class="empty-state" style="padding:3rem 1rem">
                <i class="bi bi-arrow-left-circle" style="font-size:2.5rem;color:#c9a84c"></i>
                <p class="fw-semibold mt-2" style="color:#5a6a80">Select a folder from the tree<br>or click a department above</p>
            </div>
        </div>
    </div>
<?php endif; ?>
</div>

</div><!-- /mist-hero-wrapper -->

<!-- ── File viewer modal ── -->
<div class="modal fade" id="fileViewerModal" tabindex="-1" aria-labelledby="viewerModalLabel">
    <div class="modal-dialog modal-xl modal-fullscreen-lg-down">
        <div class="modal-content viewer-modal-content">
            <div class="viewer-modal-header">
                <div class="viewer-title-wrap">
                    <i class="bi bi-file-earmark-text viewer-title-icon"></i>
                    <span id="viewerModalLabel" class="viewer-title-text">Loading…</span>
                </div>
                <div class="viewer-actions">
                    <button class="viewer-btn viewer-btn-close" data-bs-dismiss="modal" title="Close">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
            <div class="viewer-body" id="viewerBody">
                <iframe id="viewerFrame" name="viewerFrame" src="" allowfullscreen></iframe>
                <div id="viewerNoSupport" style="display:none" class="viewer-no-support">
                    <i class="bi bi-file-earmark-x"></i>
                    <p class="fw-semibold">This file type cannot be viewed in the browser.</p>
                    <small class="text-muted">Only PDF and image files can be viewed directly.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.file-card { cursor: pointer; }
.search-result-item { cursor: pointer; }

.viewer-modal-content {
    border: none; border-radius: 14px; overflow: hidden;
    height: 95vh; display: flex; flex-direction: column;
}
.viewer-modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: .8rem 1.25rem;
    background: var(--navy);
    border-bottom: 1px solid rgba(201,168,76,.2);
    flex-shrink: 0;
}
.viewer-title-wrap { display: flex; align-items: center; gap: .6rem; min-width: 0; }
.viewer-title-icon { color: var(--gold); font-size: 1.15rem; flex-shrink: 0; }
.viewer-title-text { color: #fff; font-weight: 700; font-size: .95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.viewer-actions { display: flex; align-items: center; gap: .5rem; flex-shrink: 0; }
.viewer-btn {
    display: inline-flex; align-items: center; gap: .4rem;
    background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.2);
    color: #fff; border-radius: 8px; padding: .35rem .85rem; font-size: .85rem;
    cursor: pointer; transition: background .15s; white-space: nowrap;
}
.viewer-btn:hover { background: rgba(255,255,255,.2); }
.viewer-btn-close { padding: .35rem .6rem; }
.viewer-body { flex: 1; min-height: 0; position: relative; background: #525659; }
#viewerFrame { width: 100%; height: 100%; border: none; display: block; }
.viewer-no-support {
    position: absolute; inset: 0; display: flex; flex-direction: column;
    align-items: center; justify-content: center; color: #ccc; text-align: center; gap: .5rem;
}
.viewer-no-support i { font-size: 4rem; color: #888; }
@media(max-width:576px) {
    .viewer-modal-content { height: 100vh; border-radius: 0; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Tree chevron rotation
document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(btn => {
    const icon   = btn.querySelector('.toggle-icon');
    const target = document.querySelector(btn.dataset.bsTarget);
    if (!target || !icon) return;
    target.addEventListener('show.bs.collapse', () => icon.classList.add('rotated'));
    target.addEventListener('hide.bs.collapse', () => icon.classList.remove('rotated'));
});

// File viewer
const viewerModal = new bootstrap.Modal(document.getElementById('fileViewerModal'));
const viewerFrame = document.getElementById('viewerFrame');
const viewerBody  = document.getElementById('viewerBody');
const noSupport   = document.getElementById('viewerNoSupport');

function openViewer(id, title, canView) {
    document.getElementById('viewerModalLabel').textContent = title;
    viewerFrame.src = '';
    noSupport.style.display = 'none';
    viewerFrame.style.display = 'block';

    if (canView) {
        viewerFrame.src = '/qrepo/viewer.php?id=' + id;
    } else {
        viewerFrame.style.display = 'none';
        noSupport.style.display = 'flex';
    }
    viewerModal.show();
}

function printFile() {
    try {
        viewerFrame.contentWindow.doPrint();
    } catch(e) {
        viewerFrame.contentWindow.print();
    }
}

// Clear iframe src on close to stop any media playing
document.getElementById('fileViewerModal').addEventListener('hidden.bs.modal', () => {
    viewerFrame.src = '';
});
</script>
</body>
</html>
