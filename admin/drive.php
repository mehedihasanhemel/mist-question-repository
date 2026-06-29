<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdminPanel();

$folderId    = isset($_GET['folder']) ? (int)$_GET['folder'] : null;
$searchQuery = trim($_GET['q'] ?? '');

$currentFolder = $folderId ? getFolder($folderId) : null;
$breadcrumb    = $folderId ? getFolderBreadcrumb($folderId) : [];

// Sub-folders with color + counts
$parentCond = $folderId ? "= $folderId" : "IS NULL";
$stmt = db()->prepare(
    "SELECT f.*, COALESCE(f.color,'#f59e0b') AS color,
            (SELECT COUNT(*) FROM qrepo_files WHERE folder_id=f.id) AS file_count,
            (SELECT COUNT(*) FROM qrepo_folders WHERE parent_id=f.id) AS sub_count
     FROM qrepo_folders f WHERE f.parent_id $parentCond ORDER BY f.sort_order, f.name"
);
$stmt->execute();
$subFolders = $stmt->fetchAll();

$files      = $folderId ? getFilesInFolder($folderId) : [];
$tree       = getFolderTree();
$allFolders = getAllFoldersFlat();

// Storage stats
$totalBytes = (int)db()->query("SELECT COALESCE(SUM(file_size),0) FROM qrepo_files")->fetchColumn();

// Search
$searchFiles = $searchFolders = [];
if ($searchQuery !== '') {
    $like = '%' . str_replace(['%','_'], ['\%','\_'], $searchQuery) . '%';
    $sf   = db()->prepare(
        "SELECT f.*, COALESCE(fo.name,'Root') AS folder_name
         FROM qrepo_files f LEFT JOIN qrepo_folders fo ON fo.id=f.folder_id
         WHERE f.title LIKE ? OR f.original_name LIKE ?
         ORDER BY f.uploaded_at DESC LIMIT 100"
    );
    $sf->execute([$like, $like]);
    $searchFiles = $sf->fetchAll();
    $sd = db()->prepare("SELECT * FROM qrepo_folders WHERE name LIKE ? ORDER BY name LIMIT 30");
    $sd->execute([$like]);
    $searchFolders = $sd->fetchAll();
}

// Image extensions
$imageExts = ['jpg','jpeg','png','gif','webp','bmp'];

// File type icon map
$extIcon = [
    'pdf'  => ['bi-file-earmark-pdf-fill',  '#e53e3e'],
    'doc'  => ['bi-file-earmark-word-fill',  '#2b579a'],
    'docx' => ['bi-file-earmark-word-fill',  '#2b579a'],
    'ppt'  => ['bi-file-earmark-ppt-fill',   '#d24726'],
    'pptx' => ['bi-file-earmark-ppt-fill',   '#d24726'],
    'xls'  => ['bi-file-earmark-excel-fill', '#1d6f42'],
    'xlsx' => ['bi-file-earmark-excel-fill', '#1d6f42'],
    'jpg'  => ['bi-file-earmark-image-fill', '#0ea5e9'],
    'jpeg' => ['bi-file-earmark-image-fill', '#0ea5e9'],
    'png'  => ['bi-file-earmark-image-fill', '#0ea5e9'],
    'gif'  => ['bi-file-earmark-image-fill', '#0ea5e9'],
    'zip'  => ['bi-file-earmark-zip-fill',   '#7c3aed'],
    'txt'  => ['bi-file-earmark-text-fill',  '#64748b'],
];
function fileIcon(string $name, array $map): array {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    return $map[$ext] ?? ['bi-file-earmark-fill','#94a3b8'];
}
function isImg(string $name, array $exts): bool {
    return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $exts);
}

function sidebarTree(array $folders, int $depth = 0, ?int $activeId = null): void {
    foreach ($folders as $f) {
        $indent  = $depth * 14;
        $active  = ($activeId === (int)$f['id']);
        $hasKids = !empty($f['children']);
        $colId   = 'st-' . $f['id'];
        ?>
        <div>
          <div class="st-row <?= $active ? 'st-active' : '' ?>" style="padding-left:<?= 10+$indent ?>px">
            <?php if ($hasKids): ?>
              <button class="st-toggle" data-bs-toggle="collapse" data-bs-target="#<?= $colId ?>">
                <i class="bi bi-chevron-right st-chev <?= $active ? 'rotated' : '' ?>"></i>
              </button>
            <?php else: ?>
              <span style="width:16px;display:inline-block;flex-shrink:0"></span>
            <?php endif; ?>
            <i class="bi bi-folder2<?= $active ? '-open text-warning' : ' text-primary' ?>" style="font-size:.9rem;flex-shrink:0"></i>
            <a href="?folder=<?= $f['id'] ?>" class="st-link"><?= htmlspecialchars($f['name']) ?></a>
          </div>
          <?php if ($hasKids): ?>
            <div class="collapse <?= $active ? 'show' : '' ?>" id="<?= $colId ?>">
              <?php sidebarTree($f['children'], $depth+1, $activeId); ?>
            </div>
          <?php endif; ?>
        </div>
        <?php
    }
}

$displayName    = $_SESSION['admin_username'] ?? $_SESSION['user_name'] ?? 'Admin';
$canManageUsers = !empty($_SESSION['admin_id']) || ($_SESSION['user_role']??'') === 'admin';

$FOLDER_COLORS = [
    '#f59e0b','#3b82f6','#10b981','#ef4444',
    '#8b5cf6','#ec4899','#14b8a6','#f97316','#6b7280',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="/qrepo/assets/mist-logo.svg">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Drive — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
*,*::before,*::after{box-sizing:border-box}
:root{--navy:#0d1b2e;--navy2:#1a3a5c;--gold:#c9a84c;--side:240px;--top:54px}
body{margin:0;font-family:'Segoe UI',system-ui,sans-serif;background:#f0f2f5;display:flex;flex-direction:column;height:100vh;overflow:hidden}

/* ── Topbar ── */
.topbar{height:var(--top);background:#fff;border-bottom:1px solid #e4e8ef;display:flex;align-items:center;padding:0 1rem;gap:.6rem;flex-shrink:0;z-index:200}
.topbar .logo{display:flex;align-items:center;gap:.5rem;font-weight:800;font-size:.95rem;color:var(--navy);text-decoration:none;margin-right:.25rem;flex-shrink:0}
.topbar .logo img{width:28px;height:28px}
.topbar .breadcrumb{margin:0;font-size:.84rem;flex-shrink:1;min-width:0}
.topbar .breadcrumb-item+.breadcrumb-item::before{content:'›'}
.topbar .breadcrumb a{color:#5a6a80;text-decoration:none}
.topbar .breadcrumb a:hover{color:var(--navy)}
.topbar .breadcrumb-item.active{color:#1a2a4a;font-weight:600}

/* Search */
.search-wrap{flex:1;max-width:480px;min-width:120px;position:relative}
.search-wrap input{width:100%;height:36px;border:1.5px solid #e4e8ef;border-radius:20px;padding:0 2.4rem 0 2.4rem;font-size:.85rem;outline:none;background:#f5f7fb;color:#1a2a4a;transition:border-color .15s,background .15s}
.search-wrap input:focus{border-color:#93c5fd;background:#fff}
.search-wrap .si{position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.9rem;pointer-events:none}
.search-wrap .sc{position:absolute;right:.65rem;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.85rem;cursor:pointer;display:none;background:none;border:none;padding:0;line-height:1}
.search-wrap input:not(:placeholder-shown) ~ .sc{display:block}

/* ── Sidebar ── */
.sidebar{width:var(--side);background:#fff;border-right:1px solid #e4e8ef;display:flex;flex-direction:column;flex-shrink:0;overflow-y:auto}
.sidebar-top{padding:.75rem .85rem .5rem;border-bottom:1px solid #f0f2f6}
.sidebar-label{font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:#9aa5b8;padding:.6rem .85rem .25rem}
.st-row{display:flex;align-items:center;gap:.35rem;padding:.32rem .5rem;border-radius:8px;margin:1px 5px;cursor:pointer;transition:background .13s}
.st-row:hover{background:#f0f4ff}
.st-active{background:#eff6ff}
.st-active .st-link{color:#1d4ed8;font-weight:600}
.st-toggle{width:16px;height:16px;border:none;background:none;padding:0;color:#aab;flex-shrink:0;display:flex;align-items:center;justify-content:center}
.st-chev{font-size:.65rem;transition:transform .2s}
.st-chev.rotated{transform:rotate(90deg)}
.st-link{font-size:.82rem;color:#3a4a60;text-decoration:none;flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.st-link:hover{color:#1d4ed8}
.storage-bar-wrap{padding:.75rem .9rem;border-top:1px solid #f0f2f6;margin-top:auto;flex-shrink:0}
.storage-label{font-size:.73rem;color:#7a8aaa;margin-bottom:.35rem}
.storage-bar{height:5px;background:#e4e8ef;border-radius:3px;overflow:hidden}
.storage-fill{height:100%;background:linear-gradient(90deg,#3b82f6,#60a5fa);border-radius:3px;transition:width .4s}

/* ── Body layout ── */
.body-wrap{display:flex;flex:1;overflow:hidden}
.main{flex:1;overflow-y:auto;padding:1.25rem 1.5rem;min-width:0}

/* ── Toolbar ── */
.drive-toolbar{display:flex;align-items:center;gap:.6rem;margin-bottom:1rem;flex-wrap:wrap}
.btn-new{display:inline-flex;align-items:center;gap:.4rem;background:var(--navy);color:#fff;border:none;border-radius:9px;padding:.5rem 1rem;font-size:.88rem;font-weight:700;cursor:pointer;transition:opacity .15s}
.btn-new:hover{opacity:.88}
.btn-new-dd{background:#fff;color:#1a2a4a;border:1.5px solid #dde2ee}
.btn-new-dd:hover{background:#f5f7ff;opacity:1}
.vbtn{width:32px;height:32px;border:1.5px solid #dde2ee;background:#fff;border-radius:7px;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#7a8aaa;transition:background .13s,color .13s;font-size:.88rem}
.vbtn.active,.vbtn:hover{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}
.view-toggle{display:flex;gap:.3rem}
.sort-label{font-size:.78rem;color:#94a3b8;font-weight:600;padding:.25rem .6rem;background:#f8fafc;border:1.5px solid #e4e8ef;border-radius:7px;white-space:nowrap}

/* ── Selection bar ── */
.sel-bar{background:#1d4ed8;color:#fff;padding:.5rem .85rem;display:none;align-items:center;gap:.6rem;border-radius:10px;margin-bottom:1rem;flex-shrink:0}
.sel-bar.show{display:flex}
.sel-count{font-size:.85rem;font-weight:700;flex:1}
.sel-action{background:rgba(255,255,255,.15);border:none;color:#fff;padding:.3rem .7rem;border-radius:7px;font-size:.8rem;cursor:pointer;display:flex;align-items:center;gap:.3rem;transition:background .13s}
.sel-action:hover{background:rgba(255,255,255,.28)}
.sel-action.danger{color:#fca5a5}
.sel-action.danger:hover{background:rgba(252,165,165,.2)}
.sel-close{background:none;border:none;color:rgba(255,255,255,.7);cursor:pointer;font-size:1rem;padding:0 .2rem;margin-left:.2rem}

/* ── Section titles ── */
.section-title{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:#9aa5b8;margin:.25rem 0 .65rem;display:flex;align-items:center;gap:.5rem}

/* ── Folder cards (grid) ── */
.folder-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.75rem;margin-bottom:1.25rem}
.folder-card{background:#fff;border:1.5px solid #e4e8ef;border-radius:12px;padding:.85rem 1rem;cursor:pointer;transition:border-color .15s,box-shadow .15s,background .13s;position:relative;text-decoration:none;color:inherit;display:block;user-select:none}
.folder-card:hover{border-color:#93c5fd;box-shadow:0 4px 16px rgba(0,0,0,.08)}
.folder-card.selected{border-color:#3b82f6;background:#eff6ff}
.folder-card .fc-icon{font-size:2rem;display:block;margin-bottom:.5rem}
.folder-card .fc-name{font-size:.84rem;font-weight:600;color:#1a2a4a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.folder-card .fc-meta{font-size:.72rem;color:#94a3b8;margin-top:.2rem}

/* Folder list row */
.folder-list-row{display:flex;align-items:center;gap:.85rem;padding:.6rem 1rem;border-bottom:1px solid #f4f6f9;cursor:pointer;transition:background .12s;text-decoration:none;color:inherit;position:relative;user-select:none}
.folder-list-row:hover{background:#fafbff}
.folder-list-row:last-child{border-bottom:none}
.folder-list-row.selected{background:#eff6ff}
.folder-list-row .fc-icon{font-size:1.35rem;flex-shrink:0}
.folder-list-row .fc-name{flex:1;font-size:.875rem;font-weight:600;color:#1a2a4a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.folder-list-row .fc-meta{font-size:.78rem;color:#94a3b8;white-space:nowrap;flex-shrink:0}

/* ── Item checkbox ── */
.item-check{position:absolute;top:7px;left:7px;width:20px;height:20px;border-radius:50%;border:2px solid rgba(100,120,160,.35);background:rgba(255,255,255,.92);display:flex;align-items:center;justify-content:center;cursor:pointer;opacity:0;transition:opacity .15s,background .15s,border-color .15s;z-index:3;flex-shrink:0}
.folder-card:hover .item-check,.file-item:hover .item-check,
.folder-card.selected .item-check,.file-item.selected .item-check,
.folder-list-row:hover .item-check,.folder-list-row.selected .item-check{opacity:1}
.item-check.checked,.folder-card.selected .item-check,.file-item.selected .item-check,.folder-list-row.selected .item-check{background:#1d4ed8;border-color:#1d4ed8}
.item-check i{font-size:.65rem;color:#fff;display:none}
.item-check.checked i,.folder-card.selected .item-check i,.file-item.selected .item-check i,.folder-list-row.selected .item-check i{display:block}

/* 3-dot action button on cards */
.ic-more{position:absolute;top:7px;right:7px;width:24px;height:24px;border:none;background:rgba(255,255,255,.9);border-radius:5px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.8rem;color:#64748b;box-shadow:0 1px 4px rgba(0,0,0,.1);opacity:0;transition:opacity .15s,color .15s;z-index:3}
.folder-card:hover .ic-more,.file-item:hover .ic-more{opacity:1}
.ic-more:hover{color:#1d4ed8}

/* ── File grid cards ── */
.file-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:.75rem;margin-bottom:1.25rem}
.file-item{background:#fff;border:1.5px solid #e4e8ef;border-radius:12px;cursor:pointer;transition:border-color .15s,box-shadow .15s,background .13s;position:relative;overflow:hidden;user-select:none;display:flex;flex-direction:column}
.file-item:hover{border-color:#93c5fd;box-shadow:0 4px 16px rgba(0,0,0,.08)}
.file-item.selected{border-color:#3b82f6;background:#eff6ff}
.fi-thumb{height:96px;display:flex;align-items:center;justify-content:center;background:#f8fafc;border-bottom:1px solid #f0f2f6;flex-shrink:0;overflow:hidden}
.fi-thumb i{font-size:2.5rem}
.fi-thumb img{width:100%;height:100%;object-fit:cover}
.fi-body{padding:.55rem .75rem .6rem;flex:1;min-width:0}
.fi-title{font-size:.82rem;font-weight:600;color:#1a2a4a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.fi-meta{font-size:.7rem;color:#94a3b8;margin-top:.1rem}

/* ── File list rows ── */
.file-list-wrap{background:#fff;border:1.5px solid #e4e8ef;border-radius:12px;overflow:hidden;margin-bottom:1.25rem}
.file-row{display:flex;align-items:center;gap:.75rem;padding:.62rem 1rem;border-bottom:1px solid #f4f6f9;transition:background .12s;cursor:default;position:relative;user-select:none}
.file-row:last-child{border-bottom:none}
.file-row:hover{background:#fafbff}
.file-row.selected{background:#eff6ff}
.file-row .item-check{position:static;opacity:0;transition:opacity .15s;width:20px;height:20px;flex-shrink:0}
.file-row:hover .item-check,.file-row.selected .item-check{opacity:1}
.fr-icon{font-size:1.35rem;flex-shrink:0}
.fr-name{flex:1;min-width:0}
.fr-title{font-size:.875rem;font-weight:600;color:#1a2a4a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.fr-orig{font-size:.74rem;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.fr-size{font-size:.78rem;color:#94a3b8;white-space:nowrap;width:70px;text-align:right;flex-shrink:0}
.fr-date{font-size:.78rem;color:#94a3b8;white-space:nowrap;width:80px;text-align:right;flex-shrink:0}
.fr-actions{display:flex;gap:.3rem;opacity:0;transition:opacity .15s;flex-shrink:0}
.file-row:hover .fr-actions{opacity:1}
.ic-btn{width:26px;height:26px;border:none;background:rgba(255,255,255,.9);border-radius:5px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:.78rem;color:#64748b;box-shadow:0 1px 4px rgba(0,0,0,.1);transition:color .15s}
.ic-btn:hover{color:#1d4ed8}

/* ── Context menu ── */
.ctx-menu{position:fixed;background:#fff;border:1.5px solid #e2e8f0;border-radius:10px;box-shadow:0 8px 28px rgba(0,0,0,.18);min-width:210px;z-index:9999;padding:4px 0;display:none;animation:ctxFade .1s ease}
@keyframes ctxFade{from{opacity:0;transform:scale(.96)}to{opacity:1;transform:scale(1)}}
.ctx-item{display:flex;align-items:center;gap:.6rem;padding:.48rem 1rem;cursor:pointer;color:#1a2a4a;white-space:nowrap;font-size:.84rem}
.ctx-item:hover{background:#f0f4ff}
.ctx-item.danger{color:#dc2626}
.ctx-item.danger:hover{background:#fef2f2}
.ctx-sep{height:1px;background:#f0f2f6;margin:3px 0}
.ctx-item i{font-size:.95rem;width:18px;text-align:center;color:#64748b;flex-shrink:0}
.ctx-item.danger i{color:#dc2626}
.ctx-item:hover i{color:#1d4ed8}
.ctx-item.danger:hover i{color:#dc2626}

/* ── Details panel ── */
.details-panel{width:0;background:#fff;border-left:1.5px solid #e4e8ef;display:flex;flex-direction:column;flex-shrink:0;overflow:hidden;transition:width .22s ease}
.details-panel.open{width:290px;overflow-y:auto}
.dp-head{padding:.8rem 1rem;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #f0f2f6;flex-shrink:0}
.dp-head span{font-size:.85rem;font-weight:700;color:#1a2a4a}
.dp-head button{background:none;border:none;color:#94a3b8;cursor:pointer;font-size:1rem;line-height:1;padding:2px}
.dp-head button:hover{color:#1a2a4a}
.dp-thumb{display:flex;align-items:center;justify-content:center;height:120px;background:#f8fafc;border-bottom:1px solid #f0f2f6;flex-shrink:0}
.dp-thumb i{font-size:3.5rem}
.dp-thumb img{max-width:100%;max-height:100%;object-fit:contain}
.dp-body{padding:.85rem 1rem;flex:1}
.dp-name{font-size:.88rem;font-weight:700;color:#1a2a4a;word-break:break-word;margin-bottom:.85rem;line-height:1.4}
.dp-row{display:flex;gap:.5rem;margin-bottom:.45rem;font-size:.79rem}
.dp-label{color:#94a3b8;min-width:72px;flex-shrink:0}
.dp-val{color:#1a2a4a;font-weight:500;word-break:break-word}
.dp-actions{padding:.75rem 1rem;border-top:1px solid #f0f2f6;display:flex;flex-direction:column;gap:.4rem;flex-shrink:0}
.dp-btn{width:100%;text-align:left;padding:.42rem .75rem;border-radius:8px;font-size:.82rem;display:flex;align-items:center;gap:.5rem;border:1.5px solid #e4e8ef;background:#fff;cursor:pointer;color:#1a2a4a;transition:background .13s,border-color .13s}
.dp-btn:hover{background:#f5f7ff;border-color:#bfdbfe}
.dp-btn.primary{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
.dp-btn.primary:hover{background:#1e40af}
.dp-btn.danger{border-color:#fca5a5;color:#dc2626}
.dp-btn.danger:hover{background:#fef2f2}
.dp-btn i{font-size:.9rem;flex-shrink:0}

/* ── Preview overlay ── */
.preview-overlay{position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:10000;display:none;flex-direction:column}
.preview-overlay.show{display:flex}
.preview-head{height:52px;background:rgba(0,0,0,.6);display:flex;align-items:center;gap:.75rem;padding:0 1rem;flex-shrink:0;color:#fff;border-bottom:1px solid rgba(255,255,255,.08)}
.preview-head .pv-title{flex:1;font-size:.88rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pv-btn{background:rgba(255,255,255,.12);border:none;color:#fff;border-radius:7px;padding:.35rem .75rem;font-size:.8rem;cursor:pointer;display:flex;align-items:center;gap:.35rem;transition:background .13s}
.pv-btn:hover{background:rgba(255,255,255,.22)}
.pv-close{background:none;border:none;color:rgba(255,255,255,.7);font-size:1.25rem;cursor:pointer;padding:0 .25rem;line-height:1}
.pv-close:hover{color:#fff}
.preview-body{flex:1;overflow:hidden;position:relative;display:flex;align-items:center;justify-content:center}
.preview-body iframe{width:100%;height:100%;border:none}
.preview-body img{max-width:90%;max-height:90%;object-fit:contain;box-shadow:0 4px 40px rgba(0,0,0,.6)}
.pv-nav{position:absolute;top:50%;transform:translateY(-50%);width:44px;height:44px;border-radius:50%;background:rgba(255,255,255,.15);border:none;color:#fff;font-size:1.1rem;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background .15s;z-index:2}
.pv-nav:hover{background:rgba(255,255,255,.28)}
.pv-nav.left{left:1rem}
.pv-nav.right{right:1rem}
.pv-nav:disabled{opacity:.25;cursor:default}
.pv-counter{font-size:.78rem;color:rgba(255,255,255,.5)}

/* ── Move modal tree ── */
.move-tree-item{display:flex;align-items:center;gap:.5rem;padding:.45rem .75rem;border-radius:8px;cursor:pointer;font-size:.84rem;color:#1a2a4a;transition:background .12s}
.move-tree-item:hover{background:#f0f4ff}
.move-tree-item.selected{background:#eff6ff;color:#1d4ed8;font-weight:600}
.move-tree-item i{font-size:.9rem;color:#f59e0b;flex-shrink:0}
.move-tree-item.selected i{color:#1d4ed8}
.root-option{display:flex;align-items:center;gap:.5rem;padding:.45rem .75rem;border-radius:8px;cursor:pointer;font-size:.84rem;color:#1a2a4a;transition:background .12s;border-bottom:1px solid #f0f2f6;margin-bottom:.35rem}
.root-option:hover{background:#f0f4ff}
.root-option.selected{background:#eff6ff;color:#1d4ed8;font-weight:600}

/* ── Color palette ── */
.color-swatch{width:34px;height:34px;border-radius:50%;border:3px solid transparent;cursor:pointer;transition:transform .15s,border-color .15s;flex-shrink:0}
.color-swatch:hover{transform:scale(1.15)}
.color-swatch.active{border-color:#1d4ed8}

/* ── Drop overlay ── */
#dropOverlay{position:fixed;inset:0;background:rgba(29,78,216,.12);border:3px dashed #3b82f6;z-index:9999;display:none;align-items:center;justify-content:center;flex-direction:column;gap:.75rem;pointer-events:none}
#dropOverlay.active{display:flex}
#dropOverlay i{font-size:4rem;color:#3b82f6}
#dropOverlay p{font-size:1.2rem;font-weight:700;color:#1d4ed8;margin:0}

/* ── Upload progress panel ── */
#uploadPanel{position:fixed;bottom:1.25rem;right:1.25rem;width:320px;background:#fff;border-radius:14px;box-shadow:0 8px 32px rgba(0,0,0,.18);z-index:1000;display:none;overflow:hidden}
.up-header{background:var(--navy);color:#fff;padding:.65rem 1rem;display:flex;align-items:center;justify-content:space-between;font-size:.875rem;font-weight:700}
.up-list{max-height:240px;overflow-y:auto;padding:.5rem}
.up-item{padding:.45rem .5rem;border-radius:8px;margin-bottom:.3rem;background:#f8fafc}
.up-item-name{font-size:.8rem;font-weight:600;color:#1a2a4a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:.3rem}
.up-bar-wrap{height:5px;background:#e4e8ef;border-radius:3px;overflow:hidden}
.up-bar{height:100%;background:linear-gradient(90deg,#3b82f6,#60a5fa);border-radius:3px;transition:width .2s;width:0%}
.up-status{font-size:.7rem;color:#94a3b8;margin-top:.2rem}
.up-status.done{color:#16a34a}
.up-status.error{color:#dc2626}

/* ── New folder inline ── */
#newFolderForm{margin-bottom:1rem;display:none}

/* ── Empty state ── */
.empty-state{text-align:center;padding:2.5rem 1rem;color:#c0c8d8}
.empty-state i{font-size:3.2rem;display:block;margin-bottom:.65rem}

/* ── Search results ── */
.sr-item{display:flex;align-items:center;gap:.85rem;padding:.62rem 1rem;border-bottom:1px solid #f4f6f9;cursor:pointer;transition:background .12s;user-select:none}
.sr-item:last-child{border-bottom:none}
.sr-item:hover{background:#fafbff}
.sr-icon{font-size:1.3rem;flex-shrink:0}
.sr-name{flex:1;min-width:0}
.sr-title{font-size:.875rem;font-weight:600;color:#1a2a4a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sr-sub{font-size:.74rem;color:#94a3b8}
.sr-actions{display:flex;gap:.3rem;opacity:0;transition:opacity .15s;flex-shrink:0}
.sr-item:hover .sr-actions{opacity:1}
</style>
</head>
<body>

<!-- Context menu -->
<div id="ctxMenu" class="ctx-menu"></div>

<!-- Drop overlay -->
<div id="dropOverlay">
    <i class="bi bi-cloud-upload"></i>
    <p>Drop files to upload<?= $folderId ? ' into <strong>' . htmlspecialchars($currentFolder['name']) . '</strong>' : '' ?></p>
</div>

<!-- Preview overlay -->
<div id="previewOverlay" class="preview-overlay">
    <div class="preview-head">
        <button class="pv-close" onclick="closePreview()"><i class="bi bi-arrow-left"></i></button>
        <span class="pv-title" id="pvTitle"></span>
        <span class="pv-counter" id="pvCounter"></span>
        <a id="pvDownload" href="#" class="pv-btn"><i class="bi bi-download"></i> Download</a>
        <button class="pv-close ms-1" onclick="closePreview()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="preview-body" id="previewBody">
        <button class="pv-nav left" id="pvPrev" onclick="prevFile()"><i class="bi bi-chevron-left"></i></button>
        <iframe id="pvFrame" src="" style="display:none"></iframe>
        <img id="pvImg" src="" style="display:none" alt="">
        <div id="pvUnsupported" style="display:none;color:rgba(255,255,255,.5);font-size:.9rem;text-align:center">
            <i class="bi bi-file-earmark-x" style="font-size:4rem;display:block;margin-bottom:1rem"></i>
            Preview not available for this file type
        </div>
        <button class="pv-nav right" id="pvNext" onclick="nextFile()"><i class="bi bi-chevron-right"></i></button>
    </div>
</div>

<!-- Move modal -->
<div class="modal fade" id="moveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fs-6"><i class="bi bi-folder-symlink me-2 text-primary"></i>Move to</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-2">
                <div id="moveTree" style="max-height:340px;overflow-y:auto;padding:.25rem 0"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <div id="moveDestLabel" class="me-auto text-muted" style="font-size:.8rem">Select a destination</div>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="btnConfirmMove" onclick="confirmMove()" disabled>Move here</button>
            </div>
        </div>
    </div>
</div>

<!-- Color modal -->
<div class="modal fade" id="colorModal" tabindex="-1">
    <div class="modal-dialog" style="max-width:240px;margin:5rem auto">
        <div class="modal-content">
            <div class="modal-header py-2 border-0">
                <span style="font-size:.85rem;font-weight:700;color:#1a2a4a"><i class="bi bi-palette me-2"></i>Folder color</span>
                <button class="btn-close btn-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-0 pb-3">
                <div id="colorPalette" class="d-flex flex-wrap gap-2 justify-content-center"></div>
            </div>
        </div>
    </div>
</div>

<!-- Topbar -->
<div class="topbar">
    <a href="/qrepo/admin/drive.php" class="logo">
        <img src="/qrepo/assets/mist-logo.svg" alt="MIST">
        <span>QRepo</span>
    </a>
    <?php if ($searchQuery !== ''): ?>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="/qrepo/admin/drive.php"><i class="bi bi-house-fill"></i></a></li>
            <li class="breadcrumb-item active">Search: <?= htmlspecialchars($searchQuery) ?></li>
        </ol>
    </nav>
    <?php elseif ($breadcrumb): ?>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="/qrepo/admin/drive.php"><i class="bi bi-house-fill"></i></a></li>
            <?php foreach ($breadcrumb as $i => $crumb): ?>
                <?php if ($i === count($breadcrumb)-1): ?>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($crumb['name']) ?></li>
                <?php else: ?>
                    <li class="breadcrumb-item"><a href="?folder=<?= $crumb['id'] ?>"><?= htmlspecialchars($crumb['name']) ?></a></li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ol>
    </nav>
    <?php endif; ?>

    <!-- Search -->
    <form action="/qrepo/admin/drive.php" method="get" class="search-wrap ms-auto">
        <?php if ($folderId): ?><input type="hidden" name="folder" value="<?= $folderId ?>"><?php endif; ?>
        <i class="bi bi-search si"></i>
        <input type="text" name="q" placeholder="Search in Drive…" value="<?= htmlspecialchars($searchQuery) ?>" autocomplete="off">
        <?php if ($searchQuery): ?>
        <button type="button" class="sc" onclick="window.location='/qrepo/admin/drive.php<?= $folderId ? '?folder='.$folderId : '' ?>'">
            <i class="bi bi-x-circle-fill"></i>
        </button>
        <?php else: ?>
        <button type="button" class="sc"><i class="bi bi-x-circle-fill"></i></button>
        <?php endif; ?>
    </form>

    <div class="d-flex align-items-center gap-2 flex-shrink-0">
        <span style="font-size:.82rem;color:#7a8aaa"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($displayName) ?></span>
        <?php if ($canManageUsers): ?>
        <a href="/qrepo/admin/users.php" class="btn btn-sm btn-outline-primary" style="font-size:.8rem">
            <i class="bi bi-people me-1"></i>Users
        </a>
        <?php endif; ?>
        <a href="/qrepo/" class="btn btn-sm btn-outline-secondary" style="font-size:.8rem">
            <i class="bi bi-eye me-1"></i>Site
        </a>
        <a href="/qrepo/admin/logout.php" class="btn btn-sm btn-outline-danger" style="font-size:.8rem">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</div>

<div class="body-wrap">
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-top">
            <a href="/qrepo/admin/drive.php" class="d-flex align-items-center gap-2 text-decoration-none py-1 px-1 rounded-2"
               style="color:<?= !$folderId && !$searchQuery ? '#1d4ed8' : '#3a4a60' ?>;font-weight:<?= !$folderId && !$searchQuery ? '700' : '500' ?>;font-size:.875rem;background:<?= !$folderId && !$searchQuery ? '#eff6ff' : 'transparent' ?>">
                <i class="bi bi-house<?= !$folderId && !$searchQuery ? '-fill text-primary' : ' text-muted' ?>"></i> My Drive
            </a>
        </div>
        <div class="sidebar-label">Folders</div>
        <div class="pb-2">
            <?php if (empty($tree)): ?>
                <div class="text-muted text-center py-3" style="font-size:.8rem">No folders yet</div>
            <?php else: ?>
                <?php sidebarTree($tree, 0, $folderId); ?>
            <?php endif; ?>
        </div>
        <!-- Storage -->
        <?php
        $storageLimit = 10 * 1024 * 1024 * 1024; // 10 GB display limit
        $pct = min(100, round($totalBytes / $storageLimit * 100));
        ?>
        <div class="storage-bar-wrap">
            <div class="storage-label"><?= formatFileSize($totalBytes) ?> used</div>
            <div class="storage-bar"><div class="storage-fill" style="width:<?= $pct ?>%"></div></div>
        </div>
    </aside>

    <!-- Main -->
    <main class="main" id="mainArea" oncontextmenu="ctxBackground(event)">

        <!-- Selection bar -->
        <div id="selBar" class="sel-bar">
            <span id="selCount" class="sel-count">0 items selected</span>
            <button class="sel-action" onclick="openBulkMove()"><i class="bi bi-folder-symlink"></i> Move</button>
            <button class="sel-action danger" onclick="bulkDelete()"><i class="bi bi-trash"></i> Delete</button>
            <button class="sel-close" onclick="clearSelection()" title="Clear selection"><i class="bi bi-x-lg"></i></button>
        </div>

        <!-- Drive toolbar -->
        <div id="driveToolbar" class="drive-toolbar">
            <div class="dropdown">
                <button class="btn-new dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="bi bi-plus-lg"></i> New
                </button>
                <ul class="dropdown-menu shadow-sm border-0 rounded-3" style="font-size:.875rem">
                    <li>
                        <a class="dropdown-item py-2" href="#" onclick="showNewFolder(); return false">
                            <i class="bi bi-folder-plus me-2 text-warning"></i>New Folder
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item py-2" href="#" onclick="document.getElementById('fileInput').click(); return false">
                            <i class="bi bi-file-earmark-arrow-up me-2 text-primary"></i>Upload Files
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-2" href="#" onclick="document.getElementById('folderInput').click(); return false">
                            <i class="bi bi-folder-symlink me-2 text-success"></i>Upload Folder
                        </a>
                    </li>
                </ul>
            </div>

            <?php if ($folderId): ?>
            <button class="btn-new btn-new-dd" onclick="document.getElementById('fileInput').click()">
                <i class="bi bi-upload"></i> Upload
            </button>
            <?php endif; ?>

            <!-- Sort -->
            <div class="dropdown ms-auto">
                <button class="vbtn" data-bs-toggle="dropdown" title="Sort" id="sortBtn">
                    <i class="bi bi-sort-down"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3" style="font-size:.84rem;min-width:190px">
                    <li><span class="dropdown-header" style="font-size:.7rem">Sort by</span></li>
                    <li><a class="dropdown-item py-2" href="#" onclick="sortBy('name','asc');return false"><i class="bi bi-sort-alpha-down me-2 text-muted"></i>Name (A → Z)</a></li>
                    <li><a class="dropdown-item py-2" href="#" onclick="sortBy('name','desc');return false"><i class="bi bi-sort-alpha-up me-2 text-muted"></i>Name (Z → A)</a></li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><a class="dropdown-item py-2" href="#" onclick="sortBy('date','desc');return false"><i class="bi bi-clock me-2 text-muted"></i>Newest first</a></li>
                    <li><a class="dropdown-item py-2" href="#" onclick="sortBy('date','asc');return false"><i class="bi bi-clock-history me-2 text-muted"></i>Oldest first</a></li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><a class="dropdown-item py-2" href="#" onclick="sortBy('size','desc');return false"><i class="bi bi-sort-numeric-down me-2 text-muted"></i>Largest first</a></li>
                    <li><a class="dropdown-item py-2" href="#" onclick="sortBy('size','asc');return false"><i class="bi bi-sort-numeric-up me-2 text-muted"></i>Smallest first</a></li>
                </ul>
            </div>

            <div class="view-toggle">
                <button class="vbtn" id="btnGrid" onclick="setView('grid')" title="Grid view"><i class="bi bi-grid-3x3-gap"></i></button>
                <button class="vbtn" id="btnList" onclick="setView('list')" title="List view"><i class="bi bi-list-ul"></i></button>
            </div>
        </div>

        <!-- New folder inline -->
        <div id="newFolderForm">
            <div class="d-flex align-items-center gap-2" style="max-width:380px">
                <i class="bi bi-folder-plus text-warning fs-5"></i>
                <input type="text" id="newFolderName" class="form-control form-control-sm" placeholder="Folder name…" style="border-radius:8px">
                <button class="btn btn-sm btn-primary px-3" onclick="createFolder()" style="border-radius:8px;white-space:nowrap">Create</button>
                <button class="btn btn-sm btn-outline-secondary" onclick="hideNewFolder()" style="border-radius:8px">✕</button>
            </div>
        </div>

        <?php if ($searchQuery !== ''): ?>
        <!-- ── Search results ── -->
        <?php if (empty($searchFiles) && empty($searchFolders)): ?>
        <div class="empty-state">
            <i class="bi bi-search"></i>
            <p class="fw-semibold">No results for "<?= htmlspecialchars($searchQuery) ?>"</p>
            <small>Try a different search term</small>
        </div>
        <?php else: ?>
        <?php if (!empty($searchFolders)): ?>
        <div class="section-title">Folders <span style="font-weight:400;color:#aab">(<?= count($searchFolders) ?>)</span></div>
        <div class="file-list-wrap mb-3">
            <?php foreach ($searchFolders as $sf): ?>
            <div class="sr-item" onclick="window.location='?folder=<?= $sf['id'] ?>'">
                <i class="bi bi-folder2-open sr-icon" style="color:#f59e0b"></i>
                <div class="sr-name">
                    <div class="sr-title"><?= htmlspecialchars($sf['name']) ?></div>
                </div>
                <div class="sr-actions">
                    <button class="ic-btn" title="Open" onclick="event.stopPropagation();window.location='?folder=<?= $sf['id'] ?>'"><i class="bi bi-folder2-open" style="color:#f59e0b"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($searchFiles)): ?>
        <div class="section-title">Files <span style="font-weight:400;color:#aab">(<?= count($searchFiles) ?>)</span></div>
        <div class="file-list-wrap">
            <?php foreach ($searchFiles as $sf):
                [$icon, $color] = fileIcon($sf['original_name'], $extIcon);
                $isImage = isImg($sf['original_name'], $imageExts);
            ?>
            <div class="sr-item"
                 data-id="<?= $sf['id'] ?>" data-type="file"
                 onclick="showDetails('file',<?= $sf['id'] ?>,<?= htmlspecialchars(json_encode([
                     'id'=>$sf['id'],'title'=>$sf['title'],'original_name'=>$sf['original_name'],
                     'file_size'=>$sf['file_size'],'uploaded_at'=>$sf['uploaded_at'],
                     'uploaded_by'=>$sf['uploaded_by']??null,'folder_name'=>$sf['folder_name']
                 ]), ENT_QUOTES) ?>)"
                 ondblclick="openPreview(<?= $sf['id'] ?>,<?= (int)$isImage ?>)">
                <i class="bi <?= $icon ?> sr-icon" style="color:<?= $color ?>"></i>
                <div class="sr-name">
                    <div class="sr-title"><?= htmlspecialchars($sf['title']) ?></div>
                    <div class="sr-sub"><?= htmlspecialchars($sf['folder_name']) ?> · <?= formatFileSize((int)$sf['file_size']) ?></div>
                </div>
                <div class="sr-actions">
                    <button class="ic-btn" title="Preview" onclick="event.stopPropagation();openPreview(<?= $sf['id'] ?>,<?= (int)$isImage ?>)"><i class="bi bi-eye" style="color:#3b82f6"></i></button>
                    <a href="/qrepo/download.php?id=<?= $sf['id'] ?>" class="ic-btn" title="Download" onclick="event.stopPropagation()"><i class="bi bi-download" style="color:#16a34a"></i></a>
                    <button class="ic-btn" title="Delete" onclick="event.stopPropagation();deleteFile(<?= $sf['id'] ?>,this.closest('.sr-item'))"><i class="bi bi-trash" style="color:#ef4444"></i></button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php else: ?>
        <!-- ── Normal folder/file view ── -->

        <?php if (!$folderId && empty($subFolders)): ?>
        <div class="empty-state">
            <i class="bi bi-folder2-open"></i>
            <p class="fw-semibold">No folders yet</p>
            <small>Click <strong>+ New → New Folder</strong> to get started</small>
        </div>
        <?php else: ?>

        <!-- Sub-folders -->
        <?php if (!empty($subFolders)): ?>
        <div class="section-title">Folders <span style="font-weight:400;color:#aab">(<?= count($subFolders) ?>)</span></div>
        <div id="folderContainer" class="folder-grid">
            <?php foreach ($subFolders as $sf): ?>
            <div class="folder-card"
                 data-type="folder" data-id="<?= $sf['id'] ?>"
                 data-name="<?= htmlspecialchars($sf['name'], ENT_QUOTES) ?>"
                 data-color="<?= htmlspecialchars($sf['color'], ENT_QUOTES) ?>"
                 data-date="<?= $sf['created_at'] ?>" data-size="0"
                 onclick="handleItemClick(event,this,'folder',<?= $sf['id'] ?>,<?= htmlspecialchars(json_encode(['name'=>$sf['name'],'color'=>$sf['color'],'sub_count'=>$sf['sub_count'],'file_count'=>$sf['file_count'],'created_at'=>$sf['created_at']]), ENT_QUOTES) ?>)"
                 ondblclick="window.location='?folder=<?= $sf['id'] ?>'"
                 oncontextmenu="ctxFolder(event,<?= $sf['id'] ?>,'<?= addslashes(htmlspecialchars($sf['name'])) ?>','<?= $sf['color'] ?>'); return false">
                <div class="item-check" onclick="event.stopPropagation();toggleSelectEl(this.closest('.folder-card'),'folder',<?= $sf['id'] ?>)"><i class="bi bi-check"></i></div>
                <button class="ic-more" onclick="event.stopPropagation();ctxFolder(event,<?= $sf['id'] ?>,'<?= addslashes(htmlspecialchars($sf['name'])) ?>','<?= $sf['color'] ?>')"><i class="bi bi-three-dots-vertical"></i></button>
                <i class="bi bi-folder2-open fc-icon" style="color:<?= htmlspecialchars($sf['color']) ?>"></i>
                <div class="fc-name"><?= htmlspecialchars($sf['name']) ?></div>
                <div class="fc-meta"><?= $sf['sub_count'] ?> folder<?= $sf['sub_count']!=1?'s':'' ?> · <?= $sf['file_count'] ?> file<?= $sf['file_count']!=1?'s':'' ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Files -->
        <?php if ($folderId): ?>
        <div class="section-title">Files
            <?php if (!empty($files)): ?><span style="font-weight:400;color:#aab">(<?= count($files) ?>)</span><?php endif; ?>
        </div>
        <?php if (empty($files)): ?>
        <div class="empty-state" style="padding:2rem 1rem">
            <i class="bi bi-cloud-upload" style="font-size:2.5rem"></i>
            <p class="fw-semibold mt-2">No files here</p>
            <small>Drag & drop or click <strong>Upload</strong></small>
        </div>
        <?php else: ?>
        <!-- Grid view (default) -->
        <div id="fileContainer" class="file-grid">
            <?php foreach ($files as $file):
                [$icon, $color] = fileIcon($file['original_name'], $extIcon);
                $isImage = isImg($file['original_name'], $imageExts);
                $ext = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
                $fileData = json_encode([
                    'id'=>$file['id'],'title'=>$file['title'],'original_name'=>$file['original_name'],
                    'file_size'=>$file['file_size'],'uploaded_at'=>$file['uploaded_at'],
                    'uploaded_by'=>$file['uploaded_by']??null,'folder_id'=>$file['folder_id']
                ]);
            ?>
            <div class="file-item"
                 data-type="file" data-id="<?= $file['id'] ?>"
                 data-name="<?= htmlspecialchars($file['title'], ENT_QUOTES) ?>"
                 data-size="<?= $file['file_size'] ?>"
                 data-date="<?= $file['uploaded_at'] ?>"
                 data-ext="<?= $ext ?>"
                 data-is-image="<?= (int)$isImage ?>"
                 data-file='<?= htmlspecialchars($fileData, ENT_QUOTES) ?>'
                 onclick="handleItemClick(event,this,'file',<?= $file['id'] ?>,JSON.parse(this.dataset.file))"
                 ondblclick="openPreview(<?= $file['id'] ?>,<?= (int)$isImage ?>)"
                 oncontextmenu="ctxFile(event,<?= $file['id'] ?>,'<?= addslashes(htmlspecialchars($file['title'])) ?>',<?= (int)$isImage ?>,'<?= $ext ?>'); return false">
                <div class="item-check" onclick="event.stopPropagation();toggleSelectEl(this.closest('.file-item'),'file',<?= $file['id'] ?>)"><i class="bi bi-check"></i></div>
                <button class="ic-more" onclick="event.stopPropagation();ctxFile(event,<?= $file['id'] ?>,'<?= addslashes(htmlspecialchars($file['title'])) ?>',<?= (int)$isImage ?>,'<?= $ext ?>')"><i class="bi bi-three-dots-vertical"></i></button>
                <div class="fi-thumb">
                    <?php if ($isImage): ?>
                    <img src="/qrepo/view.php?id=<?= $file['id'] ?>" alt="<?= htmlspecialchars($file['title']) ?>" loading="lazy">
                    <?php else: ?>
                    <i class="bi <?= $icon ?>" style="color:<?= $color ?>"></i>
                    <?php endif; ?>
                </div>
                <div class="fi-body">
                    <div class="fi-title"><?= htmlspecialchars($file['title']) ?></div>
                    <div class="fi-meta"><?= strtoupper($ext) ?> · <?= formatFileSize((int)$file['file_size']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <?php endif; ?>
        <?php endif; ?>
    </main>

    <!-- Details panel -->
    <aside id="detailsPanel" class="details-panel">
        <div class="dp-head">
            <span id="dpTitle">Details</span>
            <button onclick="closeDetails()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div id="dpThumb" class="dp-thumb" style="display:none"></div>
        <div class="dp-body" id="dpBody"></div>
        <div class="dp-actions" id="dpActions"></div>
    </aside>
</div>

<!-- Hidden file inputs -->
<input type="file" id="fileInput" multiple style="display:none">
<input type="file" id="folderInput" webkitdirectory multiple style="display:none">

<!-- Upload panel -->
<div id="uploadPanel">
    <div class="up-header">
        <span><i class="bi bi-cloud-upload me-2"></i>Uploading</span>
        <button onclick="document.getElementById('uploadPanel').style.display='none'" style="background:none;border:none;color:#fff;font-size:1rem;cursor:pointer">✕</button>
    </div>
    <div class="up-list" id="upList"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
'use strict';

/* ── Constants from PHP ── */
const FOLDER_ID  = <?= $folderId ?? 'null' ?>;
const ALL_FOLDERS = <?= json_encode($allFolders) ?>;
const IMG_EXTS   = <?= json_encode($imageExts) ?>;
const FOLDER_COLORS = <?= json_encode($FOLDER_COLORS) ?>;

/* ── State ── */
const selected = new Map(); // key: "file:5" or "folder:3" → {type, id, el}
let ctxState   = null; // {type, id, name, extra}
let moveState  = null; // {type, id, isBulk}
let moveDestId = null;
let colorFolderTarget = null;
let pvFiles    = []; // array of {id, isImage, title} for navigation
let pvIndex    = 0;

/* ════════════════════════════════════════════
   VIEW TOGGLE
═══════════════════════════════════════════ */
let currentView = localStorage.getItem('driveView') || 'grid';
setView(currentView, true);

function setView(v, init) {
    currentView = v;
    localStorage.setItem('driveView', v);
    document.getElementById('btnGrid').classList.toggle('active', v === 'grid');
    document.getElementById('btnList').classList.toggle('active', v === 'list');

    // Folders
    const fc = document.getElementById('folderContainer');
    if (fc) {
        if (v === 'list') {
            fc.className = 'file-list-wrap mb-3';
            fc.querySelectorAll('.folder-card').forEach(el => el.classList.replace('folder-card','folder-list-row'));
        } else {
            fc.className = 'folder-grid';
            fc.querySelectorAll('.folder-list-row').forEach(el => el.classList.replace('folder-list-row','folder-card'));
        }
    }

    // Files
    const fileCt = document.getElementById('fileContainer');
    if (fileCt) {
        if (v === 'list') {
            fileCt.className = 'file-list-wrap';
            fileCt.querySelectorAll('.file-item').forEach(el => convertFileToRow(el));
        } else {
            fileCt.className = 'file-grid';
            fileCt.querySelectorAll('.file-item').forEach(el => convertFileToCard(el));
        }
    }
}

function convertFileToRow(el) {
    if (el.dataset.listMode) return;
    el.dataset.listMode = '1';
    const id      = el.dataset.id;
    const isImage = el.dataset.isImage === '1';
    const ext     = el.dataset.ext || '';
    const name    = el.dataset.name || '';
    const size    = formatBytes(parseInt(el.dataset.size || 0));
    const date    = formatDate(el.dataset.date);

    const thumbEl  = el.querySelector('.fi-thumb');
    const bodyEl   = el.querySelector('.fi-body');
    const titleEl  = bodyEl ? bodyEl.querySelector('.fi-title') : null;

    // Build list row structure
    el.innerHTML = '';

    const chk = document.createElement('div');
    chk.className = 'item-check';
    chk.innerHTML = '<i class="bi bi-check"></i>';
    chk.onclick = ev => { ev.stopPropagation(); toggleSelectEl(el, 'file', parseInt(id)); };
    el.appendChild(chk);

    const iconEl = document.createElement('i');
    iconEl.className = 'bi ' + (isImage ? 'bi-file-earmark-image-fill' : (getIconClass(ext))) + ' fr-icon';
    iconEl.style.color = isImage ? '#0ea5e9' : getIconColor(ext);
    el.appendChild(iconEl);

    const nameDiv = document.createElement('div');
    nameDiv.className = 'fr-name';
    nameDiv.innerHTML = `<div class="fr-title">${escHtml(name)}</div>
        <div class="fr-orig">${escHtml(el.dataset.name || '')}.${ext}</div>`;
    el.appendChild(nameDiv);

    const sizeDiv = document.createElement('div');
    sizeDiv.className = 'fr-size';
    sizeDiv.textContent = size;
    el.appendChild(sizeDiv);

    const dateDiv = document.createElement('div');
    dateDiv.className = 'fr-date';
    dateDiv.textContent = date;
    el.appendChild(dateDiv);

    const actDiv = document.createElement('div');
    actDiv.className = 'fr-actions';
    actDiv.innerHTML = `
        <button class="ic-btn" title="Preview" onclick="event.stopPropagation();openPreview(${id},${isImage?1:0})"><i class="bi bi-eye" style="color:#3b82f6"></i></button>
        <a href="/qrepo/download.php?id=${id}" class="ic-btn" title="Download" onclick="event.stopPropagation()"><i class="bi bi-download" style="color:#16a34a"></i></a>
        <button class="ic-btn" title="Rename" onclick="event.stopPropagation();renameFile(${id},'${escAttr(name)}')"><i class="bi bi-pencil" style="color:#64748b"></i></button>
        <button class="ic-btn" title="Delete" onclick="event.stopPropagation();deleteFile(${id},this.closest('.file-item'))"><i class="bi bi-trash" style="color:#ef4444"></i></button>
    `;
    el.appendChild(actDiv);
}

function convertFileToCard(el) {
    if (!el.dataset.listMode) return;
    delete el.dataset.listMode;
    const id      = parseInt(el.dataset.id);
    const isImage = el.dataset.isImage === '1';
    const ext     = el.dataset.ext || '';
    const name    = el.dataset.name || '';
    const size    = formatBytes(parseInt(el.dataset.size || 0));

    el.innerHTML = `
        <div class="item-check" onclick="event.stopPropagation();toggleSelectEl(this.closest('.file-item'),'file',${id})"><i class="bi bi-check"></i></div>
        <button class="ic-more" onclick="event.stopPropagation();ctxFile(event,${id},'${escAttr(name)}',${isImage?1:0},'${ext}')"><i class="bi bi-three-dots-vertical"></i></button>
        <div class="fi-thumb">
            ${isImage ? `<img src="/qrepo/view.php?id=${id}" alt="" loading="lazy">` : `<i class="bi ${getIconClass(ext)}" style="color:${getIconColor(ext)}"></i>`}
        </div>
        <div class="fi-body">
            <div class="fi-title">${escHtml(name)}</div>
            <div class="fi-meta">${ext.toUpperCase()} · ${size}</div>
        </div>`;
}

/* ════════════════════════════════════════════
   SORT
═══════════════════════════════════════════ */
function sortBy(field, dir) {
    ['folderContainer','fileContainer'].forEach(cid => {
        const ct = document.getElementById(cid);
        if (!ct) return;
        const items = [...ct.children];
        items.sort((a, b) => {
            let va = (a.dataset[field] || '').trim();
            let vb = (b.dataset[field] || '').trim();
            if (field === 'size') { va = parseInt(va)||0; vb = parseInt(vb)||0; return dir==='asc'?va-vb:vb-va; }
            if (field === 'date') { va = new Date(va||0); vb = new Date(vb||0); return dir==='asc'?va-vb:vb-va; }
            return dir === 'asc' ? va.localeCompare(vb, undefined, {sensitivity:'base'}) : vb.localeCompare(va, undefined, {sensitivity:'base'});
        });
        items.forEach(el => ct.appendChild(el));
    });
}

/* ════════════════════════════════════════════
   SELECTION
═══════════════════════════════════════════ */
function toggleSelectEl(el, type, id) {
    const key = type + ':' + id;
    if (selected.has(key)) {
        selected.delete(key);
        el.classList.remove('selected');
    } else {
        selected.set(key, {type, id, el});
        el.classList.add('selected');
    }
    updateSelBar();
}

function clearSelection() {
    selected.forEach(s => s.el && s.el.classList.remove('selected'));
    selected.clear();
    updateSelBar();
}

function updateSelBar() {
    const bar   = document.getElementById('selBar');
    const count = document.getElementById('selCount');
    if (selected.size > 0) {
        bar.classList.add('show');
        count.textContent = selected.size + ' item' + (selected.size !== 1 ? 's' : '') + ' selected';
    } else {
        bar.classList.remove('show');
    }
}

let lastClickedEl = null;
function handleItemClick(e, el, type, id, data) {
    if (e.target.closest('.item-check') || e.target.closest('.ic-more')) return;
    if (e.ctrlKey || e.metaKey) {
        toggleSelectEl(el, type, id);
        return;
    }
    if (e.shiftKey && lastClickedEl) {
        rangeSelect(el);
        return;
    }
    clearSelection();
    selected.set(type+':'+id, {type, id, el});
    el.classList.add('selected');
    updateSelBar();
    lastClickedEl = el;
    if (type === 'file') showDetails('file', id, data);
    else showFolderDetails(id, data);
}

function rangeSelect(endEl) {
    const items = [...document.querySelectorAll('.folder-card,.folder-list-row,.file-item')];
    const a = items.indexOf(lastClickedEl);
    const b = items.indexOf(endEl);
    if (a < 0 || b < 0) return;
    const [from, to] = a < b ? [a, b] : [b, a];
    for (let i = from; i <= to; i++) {
        const el   = items[i];
        const type = el.dataset.type;
        const id   = parseInt(el.dataset.id);
        const key  = type + ':' + id;
        selected.set(key, {type, id, el});
        el.classList.add('selected');
    }
    updateSelBar();
}

/* ════════════════════════════════════════════
   CONTEXT MENU
═══════════════════════════════════════════ */
const ctxMenu = document.getElementById('ctxMenu');

function showCtx(e, items) {
    ctxMenu.innerHTML = items.map(item => {
        if (item === 'sep') return '<div class="ctx-sep"></div>';
        return `<div class="ctx-item ${item.danger?'danger':''}" onclick="${item.action}">
            <i class="bi ${item.icon}"></i>${escHtml(item.label)}
        </div>`;
    }).join('');
    ctxMenu.style.display = 'block';
    const x = Math.min(e.clientX, window.innerWidth - 220);
    const y = Math.min(e.clientY, window.innerHeight - ctxMenu.offsetHeight - 8);
    ctxMenu.style.left = x + 'px';
    ctxMenu.style.top  = y + 'px';
}

function ctxFolder(e, id, name, color) {
    e.preventDefault();
    ctxState = {type:'folder', id, name, color};
    showCtx(e, [
        {icon:'bi-folder2-open', label:'Open', action:`window.location='?folder=${id}'`},
        'sep',
        {icon:'bi-pencil', label:'Rename', action:`renameFolder(${id},'${escAttr(name)}')`},
        {icon:'bi-folder-symlink', label:'Move to…', action:`openMoveModal('folder',${id})`},
        {icon:'bi-palette', label:'Change color', action:`openColorPicker(${id},'${escAttr(color)}')`},
        'sep',
        {icon:'bi-trash', label:'Delete', action:`deleteFolder(${id},'${escAttr(name)}')`, danger:true},
    ]);
}

function ctxFile(e, id, title, isImage, ext) {
    e.preventDefault();
    ctxState = {type:'file', id, title, isImage, ext};
    showCtx(e, [
        {icon:'bi-eye', label:'Preview', action:`openPreview(${id},${isImage})`},
        'sep',
        {icon:'bi-pencil', label:'Rename', action:`renameFile(${id},'${escAttr(title)}')`},
        {icon:'bi-folder-symlink', label:'Move to…', action:`openMoveModal('file',${id})`},
        {icon:'bi-files', label:'Make a copy', action:`copyFile(${id})`},
        {icon:'bi-download', label:'Download', action:`window.location='/qrepo/download.php?id=${id}'`},
        'sep',
        {icon:'bi-trash', label:'Delete', action:`deleteFile(${id},document.querySelector('.file-item[data-id="${id}"]'))`, danger:true},
    ]);
}

function ctxBackground(e) {
    if (e.target.closest('.folder-card,.folder-list-row,.file-item,.file-row,.ctx-menu')) return;
    e.preventDefault();
    showCtx(e, [
        {icon:'bi-folder-plus', label:'New Folder', action:'showNewFolder()'},
        FOLDER_ID ? {icon:'bi-file-earmark-arrow-up', label:'Upload Files', action:"document.getElementById('fileInput').click()"} : null,
    ].filter(Boolean));
}

document.addEventListener('click', e => {
    if (!ctxMenu.contains(e.target)) ctxMenu.style.display = 'none';
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        ctxMenu.style.display = 'none';
        closeDetails();
        closePreview();
    }
    if (e.key === 'Delete' && selected.size > 0 && !document.querySelector('input:focus,textarea:focus')) {
        bulkDelete();
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'a' && !document.querySelector('input:focus,textarea:focus')) {
        e.preventDefault();
        document.querySelectorAll('.folder-card,.folder-list-row,.file-item').forEach(el => {
            const type = el.dataset.type, id = parseInt(el.dataset.id);
            selected.set(type+':'+id, {type, id, el});
            el.classList.add('selected');
        });
        updateSelBar();
    }
});

/* ════════════════════════════════════════════
   DETAILS PANEL
═══════════════════════════════════════════ */
const detailsPanel = document.getElementById('detailsPanel');

function showDetails(type, id, data) {
    const dp      = document.getElementById('dpBody');
    const da      = document.getElementById('dpActions');
    const dt      = document.getElementById('dpTitle');
    const thumb   = document.getElementById('dpThumb');
    const isImage = parseInt(data.is_image || 0) || (type === 'file' && IMG_EXTS.includes((data.original_name||'').split('.').pop().toLowerCase()));
    const ext     = (data.original_name || '').split('.').pop().toLowerCase();

    dt.textContent = 'File details';
    thumb.style.display = 'flex';
    thumb.innerHTML = isImage
        ? `<img src="/qrepo/view.php?id=${id}" alt="">`
        : `<i class="bi ${getIconClass(ext)}" style="color:${getIconColor(ext)}"></i>`;

    dp.innerHTML = `
        <div class="dp-name">${escHtml(data.title || data.name || '')}</div>
        <div class="dp-row"><span class="dp-label">Filename</span><span class="dp-val">${escHtml(data.original_name || '')}</span></div>
        <div class="dp-row"><span class="dp-label">Type</span><span class="dp-val">${ext.toUpperCase() || 'Unknown'}</span></div>
        <div class="dp-row"><span class="dp-label">Size</span><span class="dp-val">${formatBytes(parseInt(data.file_size || 0))}</span></div>
        <div class="dp-row"><span class="dp-label">Uploaded</span><span class="dp-val">${formatDate(data.uploaded_at)}</span></div>
        ${data.uploaded_by ? `<div class="dp-row"><span class="dp-label">By</span><span class="dp-val">${escHtml(data.uploaded_by)}</span></div>` : ''}
        ${data.folder_name ? `<div class="dp-row"><span class="dp-label">Folder</span><span class="dp-val">${escHtml(data.folder_name)}</span></div>` : ''}
    `;

    da.innerHTML = `
        <button class="dp-btn primary" onclick="openPreview(${id},${isImage?1:0})"><i class="bi bi-eye"></i> Open preview</button>
        <a href="/qrepo/download.php?id=${id}" class="dp-btn"><i class="bi bi-download"></i> Download</a>
        <button class="dp-btn" onclick="renameFile(${id},'${escAttr(data.title || '')}')"><i class="bi bi-pencil"></i> Rename</button>
        <button class="dp-btn" onclick="openMoveModal('file',${id})"><i class="bi bi-folder-symlink"></i> Move to…</button>
        <button class="dp-btn" onclick="copyFile(${id})"><i class="bi bi-files"></i> Make a copy</button>
        <button class="dp-btn danger" onclick="deleteFile(${id},document.querySelector('.file-item[data-id=\\'${id}\\']'))"><i class="bi bi-trash"></i> Delete</button>
    `;

    detailsPanel.classList.add('open');

    // Build preview list for navigation
    pvFiles = [];
    document.querySelectorAll('.file-item').forEach(el => {
        pvFiles.push({id:parseInt(el.dataset.id), isImage:el.dataset.isImage==='1', title:el.dataset.name||''});
    });
    pvIndex = pvFiles.findIndex(f => f.id === id);
}

function showFolderDetails(id, data) {
    const dp    = document.getElementById('dpBody');
    const da    = document.getElementById('dpActions');
    const dt    = document.getElementById('dpTitle');
    const thumb = document.getElementById('dpThumb');

    dt.textContent = 'Folder details';
    thumb.style.display = 'flex';
    thumb.innerHTML = `<i class="bi bi-folder2-open" style="font-size:3.5rem;color:${escHtml(data.color||'#f59e0b')}"></i>`;

    dp.innerHTML = `
        <div class="dp-name">${escHtml(data.name || '')}</div>
        <div class="dp-row"><span class="dp-label">Subfolders</span><span class="dp-val">${data.sub_count || 0}</span></div>
        <div class="dp-row"><span class="dp-label">Files</span><span class="dp-val">${data.file_count || 0}</span></div>
        <div class="dp-row"><span class="dp-label">Created</span><span class="dp-val">${formatDate(data.created_at)}</span></div>
    `;

    da.innerHTML = `
        <button class="dp-btn primary" onclick="window.location='?folder=${id}'"><i class="bi bi-folder2-open"></i> Open folder</button>
        <button class="dp-btn" onclick="renameFolder(${id},'${escAttr(data.name||'')}')"><i class="bi bi-pencil"></i> Rename</button>
        <button class="dp-btn" onclick="openMoveModal('folder',${id})"><i class="bi bi-folder-symlink"></i> Move to…</button>
        <button class="dp-btn" onclick="openColorPicker(${id},'${escAttr(data.color||'#f59e0b')}')"><i class="bi bi-palette"></i> Change color</button>
        <button class="dp-btn danger" onclick="deleteFolder(${id},'${escAttr(data.name||'')}')"><i class="bi bi-trash"></i> Delete</button>
    `;

    detailsPanel.classList.add('open');
}

function closeDetails() {
    detailsPanel.classList.remove('open');
}

/* ════════════════════════════════════════════
   PREVIEW MODAL
═══════════════════════════════════════════ */
const previewOverlay = document.getElementById('previewOverlay');

function openPreview(id, isImage) {
    const frameEl       = document.getElementById('pvFrame');
    const imgEl         = document.getElementById('pvImg');
    const unsupportedEl = document.getElementById('pvUnsupported');
    const titleEl       = document.getElementById('pvTitle');
    const dlEl          = document.getElementById('pvDownload');

    // Find in pvFiles or find in DOM
    if (!pvFiles.length) {
        pvFiles = [];
        document.querySelectorAll('.file-item,.file-row').forEach(el => {
            pvFiles.push({id:parseInt(el.dataset.id), isImage:el.dataset.isImage==='1'||el.dataset.isImage==='1', title:el.dataset.name||''});
        });
    }
    pvIndex = pvFiles.findIndex(f => f.id === id);
    if (pvIndex < 0) { pvFiles.push({id, isImage:!!isImage, title:''}); pvIndex = pvFiles.length-1; }

    loadPreview(pvIndex);
    previewOverlay.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function loadPreview(idx) {
    const file    = pvFiles[idx];
    const frameEl = document.getElementById('pvFrame');
    const imgEl   = document.getElementById('pvImg');
    const unsEl   = document.getElementById('pvUnsupported');
    const titleEl = document.getElementById('pvTitle');
    const dlEl    = document.getElementById('pvDownload');
    const counter = document.getElementById('pvCounter');

    frameEl.style.display = 'none';
    imgEl.style.display   = 'none';
    unsEl.style.display   = 'none';

    const title = file.title || ('File #' + file.id);
    titleEl.textContent = title;
    dlEl.href = '/qrepo/download.php?id=' + file.id;
    counter.textContent = pvFiles.length > 1 ? (idx+1) + ' / ' + pvFiles.length : '';

    document.getElementById('pvPrev').disabled = idx === 0;
    document.getElementById('pvNext').disabled = idx === pvFiles.length - 1;

    if (file.isImage) {
        imgEl.src = '/qrepo/view.php?id=' + file.id;
        imgEl.style.display = 'block';
    } else {
        frameEl.src = '/qrepo/viewer.php?id=' + file.id;
        frameEl.style.display = 'block';
    }
}

function prevFile() { if (pvIndex > 0) { pvIndex--; loadPreview(pvIndex); } }
function nextFile() { if (pvIndex < pvFiles.length-1) { pvIndex++; loadPreview(pvIndex); } }

function closePreview() {
    previewOverlay.classList.remove('show');
    document.getElementById('pvFrame').src = '';
    document.body.style.overflow = '';
}

previewOverlay.addEventListener('click', e => {
    if (e.target === previewOverlay) closePreview();
});

/* ════════════════════════════════════════════
   MOVE MODAL
═══════════════════════════════════════════ */
const moveModal = new bootstrap.Modal(document.getElementById('moveModal'));

function openMoveModal(type, id) {
    moveState  = {type, id, isBulk: false};
    moveDestId = null;
    renderMoveTree();
    document.getElementById('moveDestLabel').textContent = 'Select a destination';
    document.getElementById('btnConfirmMove').disabled = true;
    moveModal.show();
}

function openBulkMove() {
    if (!selected.size) return;
    moveState  = {type:'bulk', isBulk:true};
    moveDestId = null;
    renderMoveTree();
    document.getElementById('moveDestLabel').textContent = 'Select a destination';
    document.getElementById('btnConfirmMove').disabled = true;
    moveModal.show();
}

function renderMoveTree() {
    const container = document.getElementById('moveTree');
    const excludeId = (!moveState.isBulk && moveState.type === 'folder') ? moveState.id : null;

    // Build nested tree from flat list
    function buildTree(parentId) {
        return ALL_FOLDERS.filter(f => {
            const pid = f.parent_id ? parseInt(f.parent_id) : null;
            return pid === parentId && f.id !== excludeId;
        }).map(f => ({...f, children: buildTree(parseInt(f.id))}));
    }

    function renderNode(nodes, depth) {
        return nodes.map(f => `
            <div style="padding-left:${depth*18}px">
                <div class="move-tree-item" data-id="${f.id}" onclick="selectMoveTarget(${f.id},'${escAttr(f.name)}')">
                    <i class="bi bi-folder2"></i>${escHtml(f.name)}
                </div>
                ${f.children.length ? renderNode(f.children, depth+1) : ''}
            </div>`).join('');
    }

    const tree = buildTree(null);
    container.innerHTML = `
        <div class="root-option" onclick="selectMoveTarget(null,'My Drive')">
            <i class="bi bi-house-fill text-primary"></i><strong>My Drive (root)</strong>
        </div>` + renderNode(tree, 0);
}

function selectMoveTarget(id, name) {
    moveDestId = id;
    document.querySelectorAll('.move-tree-item,.root-option').forEach(el => el.classList.remove('selected'));
    const sel = id === null
        ? document.querySelector('.root-option')
        : document.querySelector(`.move-tree-item[data-id="${id}"]`);
    if (sel) sel.classList.add('selected');
    document.getElementById('moveDestLabel').textContent = 'Move to: ' + name;
    document.getElementById('btnConfirmMove').disabled = false;
}

async function confirmMove() {
    if (!moveState) return;
    moveModal.hide();

    if (moveState.isBulk) {
        // Separate files and folders
        const fileIds   = [...selected.entries()].filter(([k])=>k.startsWith('file:')).map(([k])=>parseInt(k.slice(5)));
        const folderIds = [...selected.entries()].filter(([k])=>k.startsWith('folder:')).map(([k])=>parseInt(k.slice(7)));
        const promises  = [];
        if (fileIds.length) {
            const fd = new FormData();
            fd.append('action','bulk_move');
            fd.append('ids', JSON.stringify(fileIds));
            if (moveDestId) fd.append('folder_id', moveDestId);
            promises.push(post('/qrepo/admin/ajax/file.php', fd));
        }
        for (const fid of folderIds) {
            const fd = new FormData();
            fd.append('action','move'); fd.append('id', fid);
            if (moveDestId) fd.append('parent_id', moveDestId);
            promises.push(post('/qrepo/admin/ajax/folder.php', fd));
        }
        const results = await Promise.all(promises);
        if (results.every(r => r.ok)) { clearSelection(); location.reload(); }
        else alert('Move failed for some items');
        return;
    }

    const fd = new FormData();
    if (moveState.type === 'folder') {
        fd.append('action','move');
        fd.append('id', moveState.id);
        if (moveDestId) fd.append('parent_id', moveDestId);
        const res = await post('/qrepo/admin/ajax/folder.php', fd);
        if (res.ok) location.reload(); else alert(res.error);
    } else {
        fd.append('action','move');
        fd.append('id', moveState.id);
        if (moveDestId) fd.append('folder_id', moveDestId);
        else { alert('Please select a destination folder'); return; }
        const res = await post('/qrepo/admin/ajax/file.php', fd);
        if (res.ok) location.reload(); else alert(res.error);
    }
}

/* ════════════════════════════════════════════
   COLOR PICKER
═══════════════════════════════════════════ */
const colorModal = new bootstrap.Modal(document.getElementById('colorModal'));

function openColorPicker(folderId, currentColor) {
    colorFolderTarget = folderId;
    const palette = document.getElementById('colorPalette');
    palette.innerHTML = FOLDER_COLORS.map(c => `
        <div class="color-swatch ${c===currentColor?'active':''}" style="background:${c}"
             title="${c}" onclick="applyFolderColor('${c}')"></div>
    `).join('');
    colorModal.show();
}

async function applyFolderColor(color) {
    if (!colorFolderTarget) return;
    colorModal.hide();
    const fd = new FormData();
    fd.append('action','color'); fd.append('id', colorFolderTarget); fd.append('color', color);
    const res = await post('/qrepo/admin/ajax/folder.php', fd);
    if (res.ok) {
        const el = document.querySelector(`.folder-card[data-id="${colorFolderTarget}"],.folder-list-row[data-id="${colorFolderTarget}"]`);
        if (el) {
            el.dataset.color = color;
            el.querySelector('.fc-icon').style.color = color;
        }
    } else alert(res.error);
}

/* ════════════════════════════════════════════
   FOLDER ACTIONS
═══════════════════════════════════════════ */
function showNewFolder() {
    const f = document.getElementById('newFolderForm');
    f.style.display = 'block';
    document.getElementById('newFolderName').focus();
}
function hideNewFolder() {
    document.getElementById('newFolderForm').style.display = 'none';
    document.getElementById('newFolderName').value = '';
}
document.getElementById('newFolderName').addEventListener('keydown', e => {
    if (e.key === 'Enter') createFolder();
    if (e.key === 'Escape') hideNewFolder();
});
async function createFolder() {
    const name = document.getElementById('newFolderName').value.trim();
    if (!name) return;
    const fd = new FormData();
    fd.append('action','create'); fd.append('name', name);
    if (FOLDER_ID) fd.append('parent_id', FOLDER_ID);
    const res = await post('/qrepo/admin/ajax/folder.php', fd);
    if (res.ok) location.reload(); else alert('Error: ' + res.error);
}

async function renameFolder(id, name) {
    const n = prompt('Rename folder:', name);
    if (!n || n === name) return;
    const fd = new FormData();
    fd.append('action','rename'); fd.append('id', id); fd.append('name', n);
    const res = await post('/qrepo/admin/ajax/folder.php', fd);
    if (res.ok) location.reload(); else alert(res.error);
}

async function deleteFolder(id, name) {
    if (!confirm(`Delete folder "${name}" and ALL its contents?`)) return;
    const fd = new FormData();
    fd.append('action','delete'); fd.append('id', id);
    const res = await post('/qrepo/admin/ajax/folder.php', fd);
    if (res.ok) location.reload(); else alert(res.error);
}

/* ════════════════════════════════════════════
   FILE ACTIONS
═══════════════════════════════════════════ */
async function renameFile(id, title) {
    const n = prompt('Rename:', title);
    if (!n || n === title) return;
    const fd = new FormData();
    fd.append('action','rename'); fd.append('id', id); fd.append('title', n);
    const res = await post('/qrepo/admin/ajax/file.php', fd);
    if (res.ok) {
        const el = document.querySelector(`.file-item[data-id="${id}"],.file-row[data-id="${id}"]`);
        if (el) {
            el.dataset.name = n;
            const t = el.querySelector('.fi-title,.fr-title');
            if (t) t.textContent = n;
        }
    } else alert(res.error);
}

async function deleteFile(id, el) {
    if (!confirm('Delete this file permanently?')) return;
    const fd = new FormData();
    fd.append('action','delete'); fd.append('id', id);
    const res = await post('/qrepo/admin/ajax/file.php', fd);
    if (res.ok) { if (el) el.remove(); closeDetails(); }
    else alert(res.error);
}

async function copyFile(id) {
    const fd = new FormData();
    fd.append('action','copy'); fd.append('id', id);
    const res = await post('/qrepo/admin/ajax/file.php', fd);
    if (res.ok) location.reload();
    else alert(res.error);
}

/* ════════════════════════════════════════════
   BULK ACTIONS
═══════════════════════════════════════════ */
async function bulkDelete() {
    if (!selected.size) return;
    if (!confirm(`Delete ${selected.size} item${selected.size>1?'s':''}? This cannot be undone.`)) return;

    const fileIds   = [...selected.entries()].filter(([k])=>k.startsWith('file:')).map(([k])=>parseInt(k.slice(5)));
    const folderIds = [...selected.entries()].filter(([k])=>k.startsWith('folder:')).map(([k])=>parseInt(k.slice(7)));

    const promises = [];
    if (fileIds.length) {
        const fd = new FormData();
        fd.append('action','bulk_delete'); fd.append('ids', JSON.stringify(fileIds));
        promises.push(post('/qrepo/admin/ajax/file.php', fd));
    }
    for (const fid of folderIds) {
        const fd = new FormData();
        fd.append('action','delete'); fd.append('id', fid);
        promises.push(post('/qrepo/admin/ajax/folder.php', fd));
    }
    const results = await Promise.all(promises);
    if (results.every(r => r.ok)) location.reload();
    else { alert('Some deletions failed'); location.reload(); }
}

/* ════════════════════════════════════════════
   UPLOAD
═══════════════════════════════════════════ */
const fileInput   = document.getElementById('fileInput');
const folderInput = document.getElementById('folderInput');
const uploadPanel = document.getElementById('uploadPanel');
const upList      = document.getElementById('upList');

fileInput.addEventListener('change', () => uploadFiles([...fileInput.files]));
folderInput.addEventListener('change', () => {
    if (folderInput.files.length) handleFolderUpload([...folderInput.files]);
    folderInput.value = '';
});

function uploadFiles(files) {
    if (!FOLDER_ID) { alert('Open a folder first, then upload.'); return; }
    if (!files.length) return;
    uploadPanel.style.display = 'block';
    files.forEach(f => uploadOne(f, FOLDER_ID));
    fileInput.value = '';
}

/* ── Folder upload ── */
async function handleFolderUpload(files) {
    if (!files.length) return;

    // Get root folder name from first file's path
    const rootName = files[0].webkitRelativePath.split('/')[0];

    // Collect all unique directory paths (sorted shallow → deep)
    const dirPaths = new Set();
    for (const file of files) {
        const parts = file.webkitRelativePath.split('/');
        for (let depth = 1; depth < parts.length; depth++) {
            dirPaths.add(parts.slice(0, depth).join('/'));
        }
    }
    const sortedDirs = [...dirPaths].sort((a, b) =>
        a.split('/').length - b.split('/').length || a.localeCompare(b)
    );

    uploadPanel.style.display = 'block';

    // Status item for folder creation phase
    const statusItem = document.createElement('div');
    statusItem.className = 'up-item';
    statusItem.innerHTML = `<div class="up-item-name"><i class="bi bi-folder-plus me-1"></i>Creating folder structure…</div>
        <div class="up-bar-wrap"><div class="up-bar" id="folderCreateBar" style="width:0%"></div></div>
        <div class="up-status" id="folderCreateStatus">0 / ${sortedDirs.length} folders</div>`;
    upList.prepend(statusItem);

    // Create folders in order, track path → id
    const pathToId = {}; // "RootFolder/Sub" → DB id
    let created = 0;
    for (const dirPath of sortedDirs) {
        const parts    = dirPath.split('/');
        const name     = parts[parts.length - 1];
        const parentPath = parts.slice(0, -1).join('/');
        const parentId   = parentPath ? pathToId[parentPath] : (FOLDER_ID || null);

        const fd = new FormData();
        fd.append('action', 'create');
        fd.append('name', name);
        if (parentId) fd.append('parent_id', parentId);

        const res = await post('/qrepo/admin/ajax/folder.php', fd);
        if (res.ok) {
            pathToId[dirPath] = res.id;
        }
        created++;
        const bar = document.getElementById('folderCreateBar');
        const st  = document.getElementById('folderCreateStatus');
        if (bar) bar.style.width = Math.round(created / sortedDirs.length * 100) + '%';
        if (st)  st.textContent = created + ' / ' + sortedDirs.length + ' folders';
    }
    const st = document.getElementById('folderCreateStatus');
    if (st) { st.textContent = '✓ Folders ready — uploading files…'; st.className = 'up-status done'; }

    // Upload each file to its correct folder
    const uploads = files.map(file => {
        const parts      = file.webkitRelativePath.split('/');
        const dirPath    = parts.slice(0, -1).join('/');
        const targetFid  = pathToId[dirPath] || FOLDER_ID;
        return {file, folderId: targetFid};
    }).filter(u => u.folderId);

    for (const {file, folderId} of uploads) {
        await uploadOne(file, folderId);
    }

    // Reload after a short delay so last upload item is visible
    setTimeout(() => location.reload(), 800);
}

function uploadOne(file, folderId) {
    const safeId = 'up_' + Date.now() + '_' + Math.random().toString(36).slice(2);
    const item = document.createElement('div');
    item.className = 'up-item';
    item.innerHTML = `<div class="up-item-name">${escHtml(file.name)}</div>
        <div class="up-bar-wrap"><div class="up-bar" id="b-${safeId}"></div></div>
        <div class="up-status" id="s-${safeId}">Uploading…</div>`;
    upList.prepend(item);

    const fd = new FormData();
    fd.append('folder_id', folderId);
    fd.append('file', file);

    return new Promise(resolve => {
    const xhr = new XMLHttpRequest();
    xhr.upload.onprogress = e => {
        if (e.lengthComputable) {
            const bar = document.getElementById('b-' + safeId);
            if (bar) bar.style.width = Math.round(e.loaded/e.total*100) + '%';
        }
    };
    xhr.onload = () => {
        const st = document.getElementById('s-' + safeId);
        try {
            const res = JSON.parse(xhr.responseText);
            if (res.ok) {
                if (st) { st.textContent = '✓ Done'; st.className = 'up-status done'; }
                // Only auto-reload for single-file uploads (folder upload reloads itself)
                if (folderId === FOLDER_ID) location.reload();
            } else {
                if (st) { st.textContent = '✗ ' + res.error; st.className = 'up-status error'; }
            }
        } catch(ex) {
            if (st) { st.textContent = '✗ Server error'; st.className = 'up-status error'; }
        }
        resolve();
    };
    xhr.onerror = () => {
        const st = document.getElementById('s-' + safeId);
        if (st) { st.textContent = '✗ Network error'; st.className = 'up-status error'; }
        resolve();
    };
    xhr.open('POST', '/qrepo/admin/ajax/upload.php');
    xhr.send(fd);
    }); // end Promise
}

/* ════════════════════════════════════════════
   DRAG & DROP UPLOAD
═══════════════════════════════════════════ */
const overlay = document.getElementById('dropOverlay');
let dragCounter = 0;
document.addEventListener('dragenter', e => {
    if ([...e.dataTransfer.items].some(i=>i.kind==='file')) { dragCounter++; overlay.classList.add('active'); }
});
document.addEventListener('dragleave', () => { if (--dragCounter <= 0) { dragCounter=0; overlay.classList.remove('active'); } });
document.addEventListener('dragover', e => e.preventDefault());
document.addEventListener('drop', e => {
    e.preventDefault(); dragCounter=0; overlay.classList.remove('active');
    const files = [...e.dataTransfer.files];
    if (files.length) uploadFiles(files);
});

/* ════════════════════════════════════════════
   SIDEBAR CHEVRONS
═══════════════════════════════════════════ */
document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(btn => {
    const target = document.querySelector(btn.dataset.bsTarget);
    const icon   = btn.querySelector('.st-chev');
    if (!target || !icon) return;
    target.addEventListener('show.bs.collapse', () => icon.classList.add('rotated'));
    target.addEventListener('hide.bs.collapse', () => icon.classList.remove('rotated'));
});

/* ════════════════════════════════════════════
   UTILITIES
═══════════════════════════════════════════ */
async function post(url, fd) {
    try {
        const res = await fetch(url, {method:'POST', body:fd});
        return await res.json();
    } catch(e) {
        return {ok:false, error:'Network error'};
    }
}

function formatBytes(b) {
    if (!b) return '0 B';
    if (b<1024) return b+' B';
    if (b<1048576) return (b/1024).toFixed(1)+' KB';
    return (b/1048576).toFixed(1)+' MB';
}

function formatDate(s) {
    if (!s) return '';
    try { return new Date(s).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'}); }
    catch(e) { return s; }
}

function escHtml(s) {
    if (!s) return '';
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escAttr(s) {
    if (!s) return '';
    return String(s).replace(/'/g,"\\'").replace(/\\/g,'\\\\');
}

const EXT_ICONS = {
    pdf:'bi-file-earmark-pdf-fill',doc:'bi-file-earmark-word-fill',docx:'bi-file-earmark-word-fill',
    ppt:'bi-file-earmark-ppt-fill',pptx:'bi-file-earmark-ppt-fill',
    xls:'bi-file-earmark-excel-fill',xlsx:'bi-file-earmark-excel-fill',
    jpg:'bi-file-earmark-image-fill',jpeg:'bi-file-earmark-image-fill',png:'bi-file-earmark-image-fill',
    zip:'bi-file-earmark-zip-fill',txt:'bi-file-earmark-text-fill',
};
const EXT_COLORS = {
    pdf:'#e53e3e',doc:'#2b579a',docx:'#2b579a',ppt:'#d24726',pptx:'#d24726',
    xls:'#1d6f42',xlsx:'#1d6f42',jpg:'#0ea5e9',jpeg:'#0ea5e9',png:'#0ea5e9',
    zip:'#7c3aed',txt:'#64748b',
};
function getIconClass(ext) { return EXT_ICONS[ext] || 'bi-file-earmark-fill'; }
function getIconColor(ext) { return EXT_COLORS[ext] || '#94a3b8'; }
</script>
</body>
</html>
