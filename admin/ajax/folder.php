<?php
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';

header('Content-Type: application/json');
if (!isAdminPanelUser()) { echo json_encode(['ok'=>false,'error'=>'Unauthorized']); exit; }
if (!can('manage_content')) { echo json_encode(['ok'=>false,'error'=>'Permission denied']); exit; }

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $name     = trim($_POST['name'] ?? '');
    $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    if ($name === '') { echo json_encode(['ok'=>false,'error'=>'Name required']); exit; }
    $stmt = db()->prepare("INSERT INTO qrepo_folders (name, parent_id, sort_order) VALUES (?,?,0)");
    $stmt->execute([$name, $parentId]);
    echo json_encode(['ok'=>true, 'id'=>db()->lastInsertId(), 'name'=>$name]);
    exit;
}

if ($action === 'rename') {
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    if (!$id || $name === '') { echo json_encode(['ok'=>false,'error'=>'Invalid']); exit; }
    db()->prepare("UPDATE qrepo_folders SET name=? WHERE id=?")->execute([$name,$id]);
    echo json_encode(['ok'=>true]);
    exit;
}

if ($action === 'color') {
    $id    = (int)($_POST['id'] ?? 0);
    $color = $_POST['color'] ?? '#f59e0b';
    if (!$id || !preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        echo json_encode(['ok'=>false,'error'=>'Invalid']); exit;
    }
    db()->prepare("UPDATE qrepo_folders SET color=? WHERE id=?")->execute([$color,$id]);
    echo json_encode(['ok'=>true]);
    exit;
}

if ($action === 'move') {
    $id       = (int)($_POST['id'] ?? 0);
    $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    if (!$id) { echo json_encode(['ok'=>false,'error'=>'Invalid']); exit; }

    // Prevent moving into self or a descendant
    $queue = [$id]; $descendants = [];
    while ($queue) {
        $cur = array_shift($queue);
        $descendants[] = $cur;
        $ch = db()->prepare("SELECT id FROM qrepo_folders WHERE parent_id=?");
        $ch->execute([$cur]);
        foreach ($ch->fetchAll() as $r) $queue[] = (int)$r['id'];
    }
    if ($parentId !== null && in_array($parentId, $descendants)) {
        echo json_encode(['ok'=>false,'error'=>"Can't move a folder into itself or its sub-folder"]); exit;
    }

    db()->prepare("UPDATE qrepo_folders SET parent_id=? WHERE id=?")->execute([$parentId, $id]);
    echo json_encode(['ok'=>true]);
    exit;
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $ids = [];
    $queue = [$id];
    while ($queue) {
        $cur = array_shift($queue);
        $ids[] = $cur;
        $ch = db()->prepare("SELECT id FROM qrepo_folders WHERE parent_id=?");
        $ch->execute([$cur]);
        foreach ($ch->fetchAll() as $r) $queue[] = $r['id'];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $files = db()->prepare("SELECT filename FROM qrepo_files WHERE folder_id IN ($in)");
    $files->execute($ids);
    foreach ($files->fetchAll() as $f) {
        $p = UPLOAD_DIR . $f['filename'];
        if (file_exists($p)) unlink($p);
    }
    db()->prepare("DELETE FROM qrepo_files WHERE folder_id IN ($in)")->execute($ids);
    foreach (array_reverse($ids) as $fid) {
        db()->prepare("DELETE FROM qrepo_folders WHERE id=?")->execute([$fid]);
    }
    echo json_encode(['ok'=>true]);
    exit;
}

echo json_encode(['ok'=>false,'error'=>'Unknown action']);
