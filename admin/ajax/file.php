<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
if (!isAdminPanelUser()) { echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit; }
if (!can('manage_content')) { echo json_encode(['ok'=>false,'error'=>'Permission denied']); exit; }

$action = $_POST['action'] ?? '';

if ($action === 'delete') {
    $id   = (int)($_POST['id'] ?? 0);
    $stmt = db()->prepare("SELECT filename FROM qrepo_files WHERE id=?");
    $stmt->execute([$id]);
    $f = $stmt->fetch();
    if ($f) {
        $p = UPLOAD_DIR . $f['filename'];
        if (file_exists($p)) unlink($p);
        db()->prepare("DELETE FROM qrepo_files WHERE id=?")->execute([$id]);
    }
    echo json_encode(['ok'=>true]);
    exit;
}

if ($action === 'rename') {
    $id    = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    if (!$id || $title === '') { echo json_encode(['ok'=>false,'error'=>'Invalid']); exit; }
    db()->prepare("UPDATE qrepo_files SET title=? WHERE id=?")->execute([$title,$id]);
    echo json_encode(['ok'=>true]);
    exit;
}

if ($action === 'move') {
    $id       = (int)($_POST['id'] ?? 0);
    $folderId = (int)($_POST['folder_id'] ?? 0);
    if (!$id || !$folderId) { echo json_encode(['ok'=>false,'error'=>'Invalid']); exit; }
    db()->prepare("UPDATE qrepo_files SET folder_id=? WHERE id=?")->execute([$folderId,$id]);
    echo json_encode(['ok'=>true]);
    exit;
}

if ($action === 'bulk_delete') {
    $ids = array_map('intval', json_decode($_POST['ids'] ?? '[]', true));
    if (empty($ids)) { echo json_encode(['ok'=>false,'error'=>'No files specified']); exit; }
    foreach ($ids as $id) {
        $stmt = db()->prepare("SELECT filename FROM qrepo_files WHERE id=?");
        $stmt->execute([$id]);
        $f = $stmt->fetch();
        if ($f) {
            $p = UPLOAD_DIR . $f['filename'];
            if (file_exists($p)) unlink($p);
            db()->prepare("DELETE FROM qrepo_files WHERE id=?")->execute([$id]);
        }
    }
    echo json_encode(['ok'=>true]);
    exit;
}

if ($action === 'bulk_move') {
    $ids      = array_map('intval', json_decode($_POST['ids'] ?? '[]', true));
    $folderId = (int)($_POST['folder_id'] ?? 0);
    if (empty($ids) || !$folderId) { echo json_encode(['ok'=>false,'error'=>'Invalid']); exit; }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $params = array_merge([$folderId], $ids);
    db()->prepare("UPDATE qrepo_files SET folder_id=? WHERE id IN ($in)")->execute($params);
    echo json_encode(['ok'=>true]);
    exit;
}

if ($action === 'copy') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = db()->prepare("SELECT * FROM qrepo_files WHERE id=?");
    $stmt->execute([$id]);
    $file = $stmt->fetch();
    if (!$file) { echo json_encode(['ok'=>false,'error'=>'File not found']); exit; }

    $ext         = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
    $newFilename = uniqid('qf_', true) . '.' . $ext;
    $src         = UPLOAD_DIR . $file['filename'];
    $dest        = UPLOAD_DIR . $newFilename;

    if (!copy($src, $dest)) { echo json_encode(['ok'=>false,'error'=>'Could not copy file']); exit; }

    $targetFolder = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : $file['folder_id'];
    $newTitle     = $file['title'] . ' (Copy)';
    $uploadedBy   = $_SESSION['admin_username'] ?? $_SESSION['user_name'] ?? null;

    $stmt = db()->prepare(
        "INSERT INTO qrepo_files (folder_id, title, filename, original_name, file_size, uploaded_by)
         VALUES (?,?,?,?,?,?)"
    );
    $stmt->execute([$targetFolder, $newTitle, $newFilename, $file['original_name'], $file['file_size'], $uploadedBy]);
    echo json_encode(['ok'=>true, 'id'=>db()->lastInsertId()]);
    exit;
}

echo json_encode(['ok'=>false,'error'=>'Unknown action']);
