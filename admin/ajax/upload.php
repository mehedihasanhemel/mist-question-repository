<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');

if (!isAdminPanelUser()) { echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit; }

$folderId = (int)($_POST['folder_id'] ?? 0);
if (!$folderId) { echo json_encode(['ok'=>false,'error'=>'No folder selected']); exit; }
if (!canUploadToFolder($folderId)) { echo json_encode(['ok'=>false,'error'=>'No upload permission for this folder']); exit; }

$allowed  = ['pdf','doc','docx','ppt','pptx','xls','xlsx','png','jpg','jpeg','zip','txt'];
$maxBytes = 100 * 1024 * 1024; // 100 MB

$file = $_FILES['file'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok'=>false,'error'=>'Upload error: '.($file['error']??'none')]);
    exit;
}

$orig = basename($file['name']);
$ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

if (!in_array($ext, $allowed)) {
    echo json_encode(['ok'=>false,'error'=>".$ext not allowed"]);
    exit;
}
if ($file['size'] > $maxBytes) {
    echo json_encode(['ok'=>false,'error'=>'File exceeds 100 MB limit']);
    exit;
}

if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
$filename = uniqid('qf_', true) . '.' . $ext;
$dest     = UPLOAD_DIR . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['ok'=>false,'error'=>'Could not save file']);
    exit;
}

$title      = pathinfo($orig, PATHINFO_FILENAME);
$uploadedBy = $_SESSION['admin_username'] ?? $_SESSION['user_name'] ?? null;
$stmt       = db()->prepare(
    "INSERT INTO qrepo_files (folder_id, title, filename, original_name, file_size, uploaded_by)
     VALUES (?,?,?,?,?,?)"
);
$stmt->execute([$folderId, $title, $filename, $orig, $file['size'], $uploadedBy]);

echo json_encode(['ok'=>true, 'id'=>db()->lastInsertId(), 'name'=>$orig, 'size'=>$file['size']]);
