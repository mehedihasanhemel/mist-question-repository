<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requirePermission('upload_assigned');

$userId = $_SESSION['user_id'];
$assignedFolders = getAssignedFolders($userId);

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $folderId = (int)($_POST['folder_id'] ?? 0);

    if (!canUploadToFolder($folderId)) {
        $err = 'You do not have upload permission for this folder.';
    } elseif ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $err = 'Upload error. Please try again.';
    } else {
        $allowed = ['pdf','doc','docx','ppt','pptx','xls','xlsx','jpg','jpeg','png','zip','txt'];
        $ext     = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) {
            $err = 'File type not allowed.';
        } elseif ($_FILES['file']['size'] > 100 * 1024 * 1024) {
            $err = 'File too large (max 100 MB).';
        } else {
            $safe   = preg_replace('/[^a-z0-9_\-]/i', '_', pathinfo($_FILES['file']['name'], PATHINFO_FILENAME));
            $fname  = uniqid($safe . '_') . '.' . $ext;
            $dest   = UPLOAD_DIR . $fname;
            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
            if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
                $title = trim($_POST['title'] ?? '') ?: pathinfo($_FILES['file']['name'], PATHINFO_FILENAME);
                db()->prepare(
                    "INSERT INTO qrepo_files (folder_id, title, filename, original_name, file_size)
                     VALUES (?, ?, ?, ?, ?)"
                )->execute([$folderId, $title, $fname, $_FILES['file']['name'], $_FILES['file']['size']]);
                $msg = 'File uploaded successfully.';
            } else {
                $err = 'Failed to save file.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="/qrepo/assets/mist-logo.svg">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Submit File — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
*,*::before,*::after{box-sizing:border-box}
:root{--navy:#0d1b2e;--gold:#c9a84c}
body{margin:0;font-family:'Segoe UI',system-ui,sans-serif;background:#f0f2f5;min-height:100vh}
.topbar{height:54px;background:#fff;border-bottom:1px solid #e4e8ef;display:flex;align-items:center;padding:0 1.25rem;gap:.75rem}
.topbar .logo{display:flex;align-items:center;gap:.5rem;font-weight:800;font-size:.95rem;color:var(--navy);text-decoration:none}
.topbar .logo img{width:28px;height:28px}
.page{max-width:700px;margin:2.5rem auto;padding:0 1rem}

.upload-card{background:#fff;border-radius:16px;border:1.5px solid #e4e8ef;padding:2rem}
.card-title{font-size:1.15rem;font-weight:800;color:#1a2a4a;margin-bottom:1.5rem;display:flex;align-items:center;gap:.5rem}

.drop-zone{border:2px dashed #cbd5e1;border-radius:12px;padding:2.5rem 1.5rem;text-align:center;cursor:pointer;transition:all .2s;background:#f8fafc;position:relative}
.drop-zone.over{border-color:#3b82f6;background:#eff6ff}
.drop-zone .dz-icon{font-size:2.5rem;color:#94a3b8;margin-bottom:.75rem}
.drop-zone .dz-text{font-size:.9rem;font-weight:600;color:#64748b}
.drop-zone .dz-sub{font-size:.78rem;color:#94a3b8;margin-top:.3rem}
.drop-zone #fileInput{position:absolute;inset:0;opacity:0;cursor:pointer}
.file-preview{background:#f0fdf4;border:1.5px solid #bbf7d0;border-radius:10px;padding:.75rem 1rem;display:flex;align-items:center;gap:.75rem;margin-top:1rem;display:none}
.file-preview .fp-icon{font-size:1.4rem;color:#16a34a}
.file-preview .fp-name{font-size:.85rem;font-weight:700;color:#15803d}
.file-preview .fp-size{font-size:.76rem;color:#4ade80}

.folder-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.6rem;margin-top:.5rem}
.folder-option{border:1.5px solid #e4e8ef;border-radius:10px;padding:.75rem 1rem;cursor:pointer;transition:all .15s;display:flex;align-items:center;gap:.6rem;font-size:.85rem;font-weight:600;color:#374151}
.folder-option:hover{border-color:#93c5fd;background:#eff6ff}
.folder-option.selected{border-color:#3b82f6;background:#eff6ff;color:#1d4ed8}
.folder-option input{accent-color:#3b82f6}

label.field-label{font-size:.8rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:.4rem}
.submit-btn{width:100%;padding:.75rem;border:none;border-radius:10px;background:var(--navy);color:#fff;font-weight:700;font-size:.95rem;cursor:pointer;transition:opacity .15s;display:flex;align-items:center;justify-content:center;gap:.5rem}
.submit-btn:hover{opacity:.9}
.submit-btn:disabled{opacity:.5;cursor:not-allowed}

.recent-files{background:#fff;border-radius:16px;border:1.5px solid #e4e8ef;padding:1.5rem;margin-top:1.25rem}
.rf-row{display:flex;align-items:center;gap:.75rem;padding:.55rem 0;border-bottom:1px solid #f0f2f5}
.rf-row:last-child{border-bottom:0}
</style>
</head>
<body>

<div class="topbar">
    <a href="/qrepo/" class="logo">
        <img src="/qrepo/assets/mist-logo.svg" alt="MIST">
        <span><?= APP_NAME ?></span>
    </a>
    <div class="ms-auto d-flex gap-2 align-items-center">
        <span style="font-size:.82rem;color:#7a8aaa">
            <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?>
        </span>
        <a href="/qrepo/" class="btn btn-sm btn-outline-secondary" style="font-size:.8rem">
            <i class="bi bi-eye me-1"></i>Browse
        </a>
        <a href="/qrepo/logout.php" class="btn btn-sm btn-outline-danger" style="font-size:.8rem">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</div>

<div class="page">

    <?php if ($msg): ?>
    <div class="alert alert-success mb-3"><i class="bi bi-check-circle-fill me-2"></i><?= $msg ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
    <div class="alert alert-danger mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= $err ?></div>
    <?php endif; ?>

    <?php if (empty($assignedFolders)): ?>
    <div class="upload-card text-center py-5">
        <i class="bi bi-folder-x" style="font-size:2.5rem;color:#94a3b8"></i>
        <div style="font-size:.95rem;font-weight:700;color:#1a2a4a;margin-top:.75rem">No Folders Assigned</div>
        <div style="font-size:.84rem;color:#94a3b8;margin-top:.3rem">
            Contact an administrator to get upload access to specific folders.
        </div>
    </div>
    <?php else: ?>

    <form method="POST" enctype="multipart/form-data" id="uploadForm">
        <div class="upload-card">
            <div class="card-title">
                <i class="bi bi-cloud-upload text-primary"></i> Upload Question Paper
            </div>

            <!-- File drop zone -->
            <label class="field-label">File</label>
            <div class="drop-zone" id="dropZone">
                <input type="file" name="file" id="fileInput" required
                       accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.jpg,.jpeg,.png,.zip,.txt">
                <div class="dz-icon"><i class="bi bi-cloud-arrow-up"></i></div>
                <div class="dz-text">Drag & drop or click to browse</div>
                <div class="dz-sub">PDF, Word, PowerPoint, Excel, Image, ZIP — max 100 MB</div>
            </div>
            <div class="file-preview" id="filePreview">
                <i class="bi bi-file-earmark-check fp-icon"></i>
                <div>
                    <div class="fp-name" id="fpName"></div>
                    <div class="fp-size" id="fpSize"></div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger ms-auto" onclick="clearFile()">
                    <i class="bi bi-x"></i>
                </button>
            </div>

            <!-- Title -->
            <div class="mt-3">
                <label class="field-label" for="titleInput">Title <span style="font-weight:400;color:#aab">(optional — uses filename if blank)</span></label>
                <input type="text" name="title" id="titleInput" class="form-control"
                       placeholder="e.g. CSE-101 Midterm 2024" style="border-radius:10px">
            </div>

            <!-- Folder picker -->
            <div class="mt-3">
                <label class="field-label">Upload to Folder</label>
                <div class="folder-grid">
                    <?php foreach ($assignedFolders as $f): ?>
                    <label class="folder-option" id="fo-<?= $f['id'] ?>">
                        <input type="radio" name="folder_id" value="<?= $f['id'] ?>" required
                               onchange="selectFolder(<?= $f['id'] ?>)">
                        <i class="bi bi-folder2-open text-warning"></i>
                        <?= htmlspecialchars($f['name']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Submit -->
            <div class="mt-4">
                <button type="submit" class="submit-btn" id="submitBtn" disabled>
                    <i class="bi bi-upload"></i> Upload File
                </button>
            </div>
        </div>
    </form>

    <!-- Recent uploads by this user -->
    <?php
    $recent = db()->prepare(
        "SELECT fi.*, fo.name AS folder_name
         FROM qrepo_files fi
         JOIN qrepo_folders fo ON fo.id = fi.folder_id
         WHERE fi.folder_id IN (
             SELECT folder_id FROM qrepo_folder_access WHERE user_id = ?
         )
         ORDER BY fi.uploaded_at DESC LIMIT 10"
    );
    $recent->execute([$userId]);
    $recentFiles = $recent->fetchAll();
    if ($recentFiles): ?>
    <div class="recent-files">
        <div style="font-size:.82rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;color:#9aa5b8;margin-bottom:.75rem">
            Recent Uploads in Your Folders
        </div>
        <?php foreach ($recentFiles as $rf): ?>
        <div class="rf-row">
            <i class="bi bi-file-earmark-text text-primary" style="font-size:1.1rem"></i>
            <div class="flex-grow-1">
                <div style="font-size:.85rem;font-weight:600;color:#1a2a4a"><?= htmlspecialchars($rf['title']) ?></div>
                <div style="font-size:.75rem;color:#94a3b8">
                    <?= htmlspecialchars($rf['folder_name']) ?> &nbsp;·&nbsp;
                    <?= formatFileSize($rf['file_size']) ?> &nbsp;·&nbsp;
                    <?= date('M j, Y', strtotime($rf['uploaded_at'])) ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<script>
const fileInput = document.getElementById('fileInput');
const dropZone  = document.getElementById('dropZone');
const preview   = document.getElementById('filePreview');
const submitBtn = document.getElementById('submitBtn');
let folderSelected = false;

function updateSubmit() {
    submitBtn.disabled = !(fileInput.files.length && folderSelected);
}

function showPreview(file) {
    document.getElementById('fpName').textContent = file.name;
    const mb = (file.size / 1048576).toFixed(2);
    document.getElementById('fpSize').textContent = mb + ' MB';
    preview.style.display = 'flex';
    updateSubmit();
}

function clearFile() {
    fileInput.value = '';
    preview.style.display = 'none';
    updateSubmit();
}

fileInput.addEventListener('change', () => {
    if (fileInput.files[0]) showPreview(fileInput.files[0]);
});

dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('over'); });
dropZone.addEventListener('dragleave', () => dropZone.classList.remove('over'));
dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('over');
    if (e.dataTransfer.files[0]) {
        const dt = new DataTransfer();
        dt.items.add(e.dataTransfer.files[0]);
        fileInput.files = dt.files;
        showPreview(e.dataTransfer.files[0]);
    }
});

function selectFolder(id) {
    document.querySelectorAll('.folder-option').forEach(el => el.classList.remove('selected'));
    document.getElementById('fo-' + id).classList.add('selected');
    folderSelected = true;
    updateSubmit();
}
</script>
</body>
</html>
