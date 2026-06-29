<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$msg = $err = '';
$allowedExts = ['pdf','doc','docx','ppt','pptx','xls','xlsx','png','jpg','jpeg','zip'];
$maxSize = 50 * 1024 * 1024; // 50MB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $folderId = (int)($_POST['folder_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $file = $_FILES['file'] ?? null;

    if (!$folderId) { $err = 'Please select a folder.'; }
    elseif ($title === '') { $err = 'Title is required.'; }
    elseif (!$file || $file['error'] !== UPLOAD_ERR_OK) { $err = 'File upload failed. Error code: ' . ($file['error'] ?? '?'); }
    else {
        $origName = basename($file['name']);
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts)) {
            $err = 'File type not allowed. Allowed: ' . implode(', ', $allowedExts);
        } elseif ($file['size'] > $maxSize) {
            $err = 'File too large. Maximum 50MB.';
        } else {
            $filename = uniqid('qf_', true) . '.' . $ext;
            $destPath = UPLOAD_DIR . $filename;
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            if (move_uploaded_file($file['tmp_name'], $destPath)) {
                $stmt = db()->prepare("INSERT INTO qrepo_files (folder_id, title, filename, original_name, file_size) VALUES (?,?,?,?,?)");
                $stmt->execute([$folderId, $title, $filename, $origName, $file['size']]);
                $msg = "File \"$title\" uploaded successfully.";
            } else {
                $err = 'Could not move uploaded file. Check permissions on uploads/ directory.';
            }
        }
    }
}

$tree = getFolderTree();

function renderFolderOptions(array $folders, int $depth = 0, int $selected = 0): void {
    foreach ($folders as $folder) {
        $prefix = str_repeat('— ', $depth);
        $sel = $selected === (int)$folder['id'] ? 'selected' : '';
        echo "<option value=\"{$folder['id']}\" $sel>{$prefix}" . htmlspecialchars($folder['name']) . "</option>";
        if (!empty($folder['children'])) renderFolderOptions($folder['children'], $depth + 1, $selected);
    }
}

$selectedFolder = (int)($_GET['folder'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="/qrepo/assets/mist-logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Files — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <?php include __DIR__ . '/admin_styles.php'; ?>
    <style>
        .drop-zone {
            border: 2px dashed #adb5bd;
            border-radius: 12px;
            padding: 2.5rem;
            text-align: center;
            cursor: pointer;
            transition: border-color .2s, background .2s;
        }
        .drop-zone:hover, .drop-zone.dragover { border-color: #0d6efd; background: #f0f6ff; }
        .drop-zone i { font-size: 2.5rem; color: #adb5bd; }
        .drop-zone.has-file { border-color: #198754; background: #f0fff4; }
        .drop-zone.has-file i { color: #198754; }
    </style>
</head>
<body>
<?php include __DIR__ . '/admin_nav.php'; ?>
<div class="admin-content">
<div class="container-fluid py-4">
    <h4 class="fw-bold mb-4"><i class="bi bi-cloud-upload me-2 text-success"></i>Upload File</h4>

    <?php if ($msg): ?><div class="alert alert-success py-2"><i class="bi bi-check-circle me-1"></i><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-1"></i><?= $err ?></div><?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Target Folder *</label>
                            <select name="folder_id" class="form-select" required>
                                <option value="">— Select a folder —</option>
                                <?php renderFolderOptions($tree, 0, $selectedFolder); ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">File Title *</label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. Midterm 2024 Question Paper">
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">File *</label>
                            <div class="drop-zone" id="dropZone" onclick="document.getElementById('fileInput').click()">
                                <i class="bi bi-cloud-arrow-up d-block mb-2" id="dropIcon"></i>
                                <div id="dropText">Click or drag & drop a file here</div>
                                <div class="text-muted small mt-1">PDF, DOC, DOCX, PPT, XLS, PNG, JPG, ZIP — max 50MB</div>
                            </div>
                            <input type="file" name="file" id="fileInput" class="d-none" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.png,.jpg,.jpeg,.zip" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100 fw-semibold">
                            <i class="bi bi-upload me-2"></i>Upload File
                        </button>
                    </form>
                </div>
            </div>
            <div class="text-center mt-3">
                <a href="/qrepo/admin/manage_files.php" class="text-muted small">
                    <i class="bi bi-files me-1"></i>View all uploaded files
                </a>
            </div>
        </div>
    </div>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fileInput');
const dropText = document.getElementById('dropText');
const dropIcon = document.getElementById('dropIcon');

function setFile(file) {
    if (!file) return;
    dropZone.classList.add('has-file');
    dropIcon.className = 'bi bi-file-earmark-check d-block mb-2';
    dropText.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
    const dt = new DataTransfer();
    dt.items.add(file);
    fileInput.files = dt.files;
}

fileInput.addEventListener('change', () => setFile(fileInput.files[0]));
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('dragover'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('dragover'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('dragover');
    setFile(e.dataTransfer.files[0]);
});
</script>
</body>
</html>
