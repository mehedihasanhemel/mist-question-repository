<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireAnyLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) { http_response_code(404); exit('Not found'); }

$stmt = db()->prepare("SELECT * FROM qrepo_files WHERE id = ?");
$stmt->execute([$id]);
$file = $stmt->fetch();

if (!$file) { http_response_code(404); exit('File not found'); }

$path = UPLOAD_DIR . $file['filename'];
if (!file_exists($path)) { http_response_code(404); exit('File missing on disk'); }

$mime = mime_content_type($path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . rawurlencode($file['original_name']) . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=3600');
header('X-Frame-Options: SAMEORIGIN');
readfile($path);
exit;
