<?php
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

function getFolderTree(?int $parentId = null): array {
    $stmt = db()->prepare("SELECT * FROM qrepo_folders WHERE parent_id " . ($parentId === null ? "IS NULL" : "= ?") . " ORDER BY sort_order, name");
    if ($parentId === null) $stmt->execute();
    else $stmt->execute([$parentId]);
    $folders = $stmt->fetchAll();
    foreach ($folders as &$folder) {
        $folder['children'] = getFolderTree($folder['id']);
        $folder['file_count'] = getFolderFileCount($folder['id']);
    }
    return $folders;
}

function getFolderFileCount(int $folderId): int {
    $stmt = db()->prepare("SELECT COUNT(*) FROM qrepo_files WHERE folder_id = ?");
    $stmt->execute([$folderId]);
    return (int) $stmt->fetchColumn();
}

function getFilesInFolder(int $folderId): array {
    $stmt = db()->prepare("SELECT * FROM qrepo_files WHERE folder_id = ? ORDER BY title, uploaded_at DESC");
    $stmt->execute([$folderId]);
    return $stmt->fetchAll();
}

function getFolder(int $id): ?array {
    $stmt = db()->prepare("SELECT * FROM qrepo_folders WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function getFolderBreadcrumb(int $folderId): array {
    $path = [];
    $id = $folderId;
    while ($id) {
        $folder = getFolder($id);
        if (!$folder) break;
        array_unshift($path, $folder);
        $id = $folder['parent_id'];
    }
    return $path;
}

function getAllFoldersFlat(): array {
    $stmt = db()->query("SELECT * FROM qrepo_folders ORDER BY name");
    return $stmt->fetchAll();
}

function formatFileSize(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}
