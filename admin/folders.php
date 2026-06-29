<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$msg = $err = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $order = (int)($_POST['sort_order'] ?? 0);
        if ($name === '') { $err = 'Folder name is required.'; }
        else {
            $stmt = db()->prepare("INSERT INTO qrepo_folders (name, parent_id, sort_order) VALUES (?, ?, ?)");
            $stmt->execute([$name, $parentId, $order]);
            $msg = "Folder \"$name\" created.";
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $childCount = db()->prepare("SELECT COUNT(*) FROM qrepo_folders WHERE parent_id = ?");
        $childCount->execute([$id]);
        $fileCount = db()->prepare("SELECT COUNT(*) FROM qrepo_files WHERE folder_id = ?");
        $fileCount->execute([$id]);
        if ($childCount->fetchColumn() > 0 || $fileCount->fetchColumn() > 0) {
            $err = 'Cannot delete: folder has children or files. Remove them first.';
        } else {
            db()->prepare("DELETE FROM qrepo_folders WHERE id = ?")->execute([$id]);
            $msg = 'Folder deleted.';
        }
    }

    if ($action === 'rename') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($name === '') { $err = 'Name cannot be empty.'; }
        else {
            db()->prepare("UPDATE qrepo_folders SET name = ? WHERE id = ?")->execute([$name, $id]);
            $msg = 'Folder renamed.';
        }
    }
}

$allFolders = getAllFoldersFlat();
$tree = getFolderTree();

function renderAdminTree(array $folders, int $depth = 0): void {
    foreach ($folders as $folder) {
        $indent = $depth * 20;
        $childCount = count($folder['children']);
        ?>
        <tr>
            <td style="padding-left: <?= 16 + $indent ?>px">
                <i class="bi bi-folder2 text-warning me-1"></i>
                <?= htmlspecialchars($folder['name']) ?>
                <?php if ($childCount): ?><span class="badge bg-light text-dark ms-1"><?= $childCount ?> sub</span><?php endif; ?>
            </td>
            <td class="text-muted small"><?= $folder['file_count'] ?> file(s)</td>
            <td class="text-muted small"><?= date('d M Y', strtotime($folder['created_at'])) ?></td>
            <td>
                <button class="btn btn-icon btn-outline-secondary btn-sm me-1"
                    title="Rename"
                    onclick="openRename(<?= $folder['id'] ?>, '<?= addslashes(htmlspecialchars($folder['name'])) ?>')">
                    <i class="bi bi-pencil"></i>
                </button>
                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this folder?')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $folder['id'] ?>">
                    <button type="submit" class="btn btn-icon btn-outline-danger btn-sm" title="Delete">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            </td>
        </tr>
        <?php
        if (!empty($folder['children'])) renderAdminTree($folder['children'], $depth + 1);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="/qrepo/assets/mist-logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Folders — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php include __DIR__ . '/admin_styles.php'; ?>
</head>
<body>
<?php include __DIR__ . '/admin_nav.php'; ?>
<div class="admin-content">
<div class="container-fluid py-4">
    <div class="d-flex align-items-center mb-4 gap-3">
        <h4 class="fw-bold mb-0"><i class="bi bi-folder2 me-2 text-primary"></i>Manage Folders</h4>
        <button class="btn btn-primary btn-sm ms-auto" data-bs-toggle="modal" data-bs-target="#createModal">
            <i class="bi bi-folder-plus me-1"></i>New Folder
        </button>
    </div>

    <?php if ($msg): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= $err ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Folder Name</th>
                        <th>Files</th>
                        <th>Created</th>
                        <th style="width:100px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tree)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">No folders yet.</td></tr>
                    <?php else: ?>
                        <?php renderAdminTree($tree); ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-folder-plus me-2"></i>New Folder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Folder Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. CSE Department">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Parent Folder</label>
                    <select name="parent_id" class="form-select">
                        <option value="">— Root (top level) —</option>
                        <?php foreach ($allFolders as $f): ?>
                            <option value="<?= $f['id'] ?>"><?= htmlspecialchars($f['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-1">
                    <label class="form-label fw-semibold">Sort Order</label>
                    <input type="number" name="sort_order" class="form-control" value="0" min="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Rename Modal -->
<div class="modal fade" id="renameModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content">
            <input type="hidden" name="action" value="rename">
            <input type="hidden" name="id" id="renameId">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil me-2"></i>Rename Folder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label fw-semibold">New Name *</label>
                <input type="text" name="name" id="renameName" class="form-control" required>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Rename</button>
            </div>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function openRename(id, name) {
    document.getElementById('renameId').value = id;
    document.getElementById('renameName').value = name;
    new bootstrap.Modal(document.getElementById('renameModal')).show();
}
</script>
</body>
</html>
