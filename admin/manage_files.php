<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = db()->prepare("SELECT filename FROM qrepo_files WHERE id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetch();
    if ($file) {
        $path = UPLOAD_DIR . $file['filename'];
        if (file_exists($path)) unlink($path);
        db()->prepare("DELETE FROM qrepo_files WHERE id = ?")->execute([$id]);
        $msg = 'File deleted.';
    }
}

$search = trim($_GET['q'] ?? '');
$folderId = isset($_GET['folder']) ? (int)$_GET['folder'] : 0;

$sql = "SELECT f.*, fo.name as folder_name FROM qrepo_files f JOIN qrepo_folders fo ON fo.id = f.folder_id";
$params = [];
$where = [];
if ($search) { $where[] = "(f.title LIKE ? OR f.original_name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($folderId) { $where[] = "f.folder_id = ?"; $params[] = $folderId; }
if ($where) $sql .= " WHERE " . implode(" AND ", $where);
$sql .= " ORDER BY f.uploaded_at DESC LIMIT 200";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$files = $stmt->fetchAll();

$allFolders = getAllFoldersFlat();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="/qrepo/assets/mist-logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Files — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php include __DIR__ . '/admin_styles.php'; ?>
</head>
<body>
<?php include __DIR__ . '/admin_nav.php'; ?>
<div class="admin-content">
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4 gap-3">
        <h4 class="fw-bold mb-0"><i class="bi bi-files me-2 text-primary"></i>Manage Files</h4>
        <a href="/qrepo/admin/files.php" class="btn btn-success btn-sm ms-auto">
            <i class="bi bi-upload me-1"></i>Upload New
        </a>
    </div>

    <?php if ($msg): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i><?= $msg ?></div><?php endif; ?>

    <!-- Search / Filter -->
    <form class="row g-2 mb-3" method="GET">
        <div class="col-md-6">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search by title or filename…" value="<?= htmlspecialchars($search) ?>">
        </div>
        <div class="col-md-4">
            <select name="folder" class="form-select form-select-sm">
                <option value="">All folders</option>
                <?php foreach ($allFolders as $f): ?>
                    <option value="<?= $f['id'] ?>" <?= $folderId === (int)$f['id'] ? 'selected' : '' ?>><?= htmlspecialchars($f['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <a href="/qrepo/admin/manage_files.php" class="btn btn-outline-secondary btn-sm">Reset</a>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Title</th>
                        <th>Folder</th>
                        <th>File</th>
                        <th>Size</th>
                        <th>Uploaded</th>
                        <th style="width:80px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($files)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No files found.</td></tr>
                    <?php else: foreach ($files as $file):
                        $ext = strtolower(pathinfo($file['original_name'], PATHINFO_EXTENSION));
                        $exists = file_exists(UPLOAD_DIR . $file['filename']);
                    ?>
                    <tr class="<?= !$exists ? 'table-danger' : '' ?>">
                        <td>
                            <?= htmlspecialchars($file['title']) ?>
                            <?php if (!$exists): ?><span class="badge bg-danger ms-1">missing</span><?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark">
                                <i class="bi bi-folder2 me-1"></i><?= htmlspecialchars($file['folder_name']) ?>
                            </span>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars($file['original_name']) ?></td>
                        <td class="text-muted small"><?= formatFileSize($file['file_size']) ?></td>
                        <td class="text-muted small"><?= date('d M Y', strtotime($file['uploaded_at'])) ?></td>
                        <td>
                            <?php if ($exists): ?>
                            <a href="/qrepo/download.php?id=<?= $file['id'] ?>" class="btn btn-icon btn-outline-primary btn-sm me-1" title="Download">
                                <i class="bi bi-download"></i>
                            </a>
                            <?php endif; ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this file permanently?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $file['id'] ?>">
                                <button type="submit" class="btn btn-icon btn-outline-danger btn-sm" title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
