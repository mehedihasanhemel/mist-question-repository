<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
requireUserManagement();

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* Create user */
    if ($action === 'create') {
        $name       = trim($_POST['name'] ?? '');
        $email      = strtolower(trim($_POST['email'] ?? ''));
        $pass       = $_POST['password'] ?? '';
        $rolesList  = array_filter($_POST['roles'] ?? ['viewer'], fn($r) => array_key_exists($r, ROLE_LABELS));
        if (!$rolesList) $rolesList = ['viewer'];

        if (!$name || !$email || !$pass) { $err = 'All fields are required.'; }
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $err = 'Invalid email.'; }
        else {
            $chk = db()->prepare("SELECT id FROM qrepo_users WHERE email=?"); $chk->execute([$email]);
            if ($chk->fetch()) { $err = 'Email already exists.'; }
            else {
                $primaryRole = highestRole($rolesList);
                $rolesJson   = json_encode(array_values($rolesList));
                db()->prepare(
                    "INSERT INTO qrepo_users (name,email,password,auth_provider,role,roles,status) VALUES(?,?,?,'local',?,?,'active')"
                )->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT),$primaryRole,$rolesJson]);
                $msg = "User \"$name\" created.";
            }
        }
    }

    /* Change roles */
    if ($action === 'role') {
        $uid        = (int)$_POST['uid'];
        $rolesList  = array_filter($_POST['roles'] ?? ['viewer'], fn($r) => array_key_exists($r, ROLE_LABELS));
        if (!$rolesList) $rolesList = ['viewer'];
        $primaryRole = highestRole($rolesList);
        $rolesJson   = json_encode(array_values($rolesList));
        db()->prepare("UPDATE qrepo_users SET role=?, roles=? WHERE id=?")->execute([$primaryRole,$rolesJson,$uid]);
        $msg = 'Roles updated.';
    }

    /* Save folder access */
    if ($action === 'folders') {
        $uid        = (int)$_POST['uid'];
        $folders    = array_map('intval', $_POST['folders'] ?? []);
        $subfolders = array_map('intval', $_POST['subfolders'] ?? []);
        db()->prepare("DELETE FROM qrepo_folder_access WHERE user_id=?")->execute([$uid]);
        $ins = db()->prepare(
            "INSERT IGNORE INTO qrepo_folder_access(user_id,folder_id,include_subfolders) VALUES(?,?,?)"
        );
        foreach ($folders as $fid) {
            if ($fid) $ins->execute([$uid, $fid, in_array($fid,$subfolders) ? 1 : 0]);
        }
        $msg = 'Folder access saved.';
    }

    /* Toggle active/inactive */
    if ($action === 'toggle') {
        $uid = (int)$_POST['uid'];
        db()->prepare("UPDATE qrepo_users SET status=IF(status='active','inactive','active') WHERE id=?")->execute([$uid]);
    }

    /* Reset password */
    if ($action === 'resetpw') {
        $uid  = (int)$_POST['uid'];
        $pass = $_POST['password'] ?? '';
        if (strlen($pass) < 6) { $err = 'Password must be at least 6 characters.'; }
        else {
            db()->prepare(
                "UPDATE qrepo_users SET password=?,auth_provider=IF(auth_provider='microsoft','both',auth_provider) WHERE id=?"
            )->execute([password_hash($pass,PASSWORD_DEFAULT),$uid]);
            $msg = 'Password reset.';
        }
    }

    /* Delete user */
    if ($action === 'delete') {
        $uid = (int)$_POST['uid'];
        db()->prepare("DELETE FROM qrepo_folder_access WHERE user_id=?")->execute([$uid]);
        db()->prepare("DELETE FROM qrepo_users WHERE id=?")->execute([$uid]);
        $msg = 'User deleted.';
    }

    /* System admin password */
    if ($action === 'sa_pw') {
        $said = (int)$_POST['said'];
        $pass = $_POST['password'] ?? '';
        if (strlen($pass) < 6) { $err = 'Password must be at least 6 characters.'; }
        else {
            db()->prepare("UPDATE qrepo_admins SET password=? WHERE id=?")->execute([password_hash($pass,PASSWORD_DEFAULT),$said]);
            $msg = 'Admin password updated.';
        }
    }
}

function highestRole(array $roles): string {
    $order = ['viewer','submitter','resource_manager','admin'];
    $best  = 'viewer';
    foreach ($order as $r) { if (in_array($r,$roles)) $best = $r; }
    return $best;
}

/* ── Data ── */
$users = db()->query(
    "SELECT u.*,
            GROUP_CONCAT(fa.folder_id) AS folder_ids,
            GROUP_CONCAT(IF(fa.include_subfolders=1, fa.folder_id, NULL)) AS subfolder_ids
     FROM qrepo_users u
     LEFT JOIN qrepo_folder_access fa ON fa.user_id=u.id
     GROUP BY u.id ORDER BY u.created_at DESC"
)->fetchAll();

$sysAdmins  = db()->query("SELECT * FROM qrepo_admins ORDER BY id")->fetchAll();
$allFolders = getAllFoldersFlat();

// Build folder tree for modal
function buildFolderTree(array $flat, ?int $parentId = null): array {
    $out = [];
    foreach ($flat as $f) {
        $pid = $f['parent_id'] ? (int)$f['parent_id'] : null;
        if ($pid === $parentId) {
            $f['children'] = buildFolderTree($flat, (int)$f['id']);
            $out[] = $f;
        }
    }
    return $out;
}
$folderTree = buildFolderTree($allFolders);

$roleInfo = [
    'viewer'           => ['icon'=>'bi-eye',          'color'=>'#4338ca', 'bg'=>'#eef2ff', 'desc'=>'Browse & read files'],
    'submitter'        => ['icon'=>'bi-upload',        'color'=>'#16a34a', 'bg'=>'#f0fdf4', 'desc'=>'Upload to assigned folders'],
    'resource_manager' => ['icon'=>'bi-folder2',       'color'=>'#b45309', 'bg'=>'#fef3c7', 'desc'=>'Manage all content'],
    'admin'            => ['icon'=>'bi-shield-lock',   'color'=>'#dc2626', 'bg'=>'#fef2f2', 'desc'=>'Manage users'],
];

function renderFolderTree(array $folders, int $depth = 0): void {
    foreach ($folders as $f) {
        $indent = $depth * 20;
        ?>
        <div class="ft-item" data-id="<?= $f['id'] ?>">
            <div class="ft-row" style="padding-left:<?= 8+$indent ?>px">
                <label class="ft-check-label">
                    <input type="checkbox" name="folders[]" value="<?= $f['id'] ?>"
                           class="ft-folder-cb" onchange="ftToggle(this)">
                    <i class="bi bi-folder2" style="color:#f59e0b;font-size:.88rem"></i>
                    <span class="ft-name"><?= htmlspecialchars($f['name']) ?></span>
                </label>
                <label class="ft-sub-label" style="display:none">
                    <input type="checkbox" name="subfolders[]" value="<?= $f['id'] ?>" class="ft-sub-cb">
                    <i class="bi bi-diagram-3" title="Include sub-folders"></i>
                    <span>+sub-folders</span>
                </label>
            </div>
            <?php if (!empty($f['children'])): ?>
            <div class="ft-children">
                <?php renderFolderTree($f['children'], $depth+1); ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="/qrepo/assets/mist-logo.svg">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Users — <?= APP_NAME ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
*,*::before,*::after{box-sizing:border-box}
body{margin:0;font-family:'Segoe UI',system-ui,sans-serif;background:#f0f2f5;min-height:100vh}

.topbar{height:52px;background:#fff;border-bottom:1px solid #e4e8ef;display:flex;align-items:center;
        padding:0 1.25rem;gap:.65rem;position:sticky;top:0;z-index:100}
.topbar .logo{display:flex;align-items:center;gap:.45rem;font-weight:800;font-size:.93rem;
              color:#0d1b2e;text-decoration:none}
.topbar .logo img{width:26px;height:26px}
.vl{width:1px;height:20px;background:#e4e8ef}

.page{max-width:1000px;margin:0 auto;padding:1.75rem 1rem}

.sec-label{font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.6px;
           color:#94a3b8;margin-bottom:.65rem;display:flex;align-items:center;gap:.4rem}

table{width:100%;border-collapse:separate;border-spacing:0}
thead th{font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.5px;
         color:#94a3b8;padding:.5rem .85rem;background:#fff;
         border-bottom:1.5px solid #e4e8ef;white-space:nowrap}
tbody tr{background:#fff;transition:background .1s}
tbody tr:hover{background:#fafbff}
tbody td{padding:.65rem .85rem;border-bottom:1px solid #f0f2f5;vertical-align:middle;font-size:.85rem}
tbody tr:last-child td{border-bottom:0}
.tbl-wrap{background:#fff;border:1.5px solid #e4e8ef;border-radius:14px;overflow:hidden}

.av{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;
    font-weight:800;font-size:.88rem;flex-shrink:0}

.pill{display:inline-flex;align-items:center;gap:.25rem;font-size:.68rem;font-weight:700;
      border-radius:100px;padding:.15rem .5rem;white-space:nowrap}
.pill-active{background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0}
.pill-inactive{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}

.auth{font-size:.67rem;background:#f0f4ff;color:#4338ca;border:1px solid #c7d7f5;
      border-radius:100px;padding:.08rem .4rem;font-weight:600}

.act{border:none;border-radius:7px;padding:.28rem .6rem;font-size:.75rem;font-weight:600;
     cursor:pointer;display:inline-flex;align-items:center;gap:.25rem;transition:opacity .15s}
.act:hover{opacity:.82}

.sa-row{display:flex;align-items:center;gap:.85rem;padding:.75rem 1rem;border-bottom:1px solid #f0f2f5}
.sa-row:last-child{border-bottom:0}
.sa-av{width:34px;height:34px;border-radius:50%;background:#fef2f2;color:#dc2626;
       display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.9rem}

/* Role badges in table */
.role-badge{display:inline-flex;align-items:center;gap:.25rem;font-size:.7rem;font-weight:700;
            border-radius:7px;padding:.18rem .5rem;white-space:nowrap;border:1px solid transparent}

/* Role checkboxes in modal */
.role-opt{display:flex;align-items:flex-start;gap:.75rem;padding:.7rem .9rem;border-radius:10px;
          border:1.5px solid #e4e8ef;cursor:pointer;transition:border-color .13s,background .13s;margin-bottom:.5rem}
.role-opt:hover{border-color:#c7d7f5;background:#f8faff}
.role-opt.checked{border-color:#3b82f6;background:#eff6ff}
.role-opt input[type=checkbox]{width:18px;height:18px;accent-color:#1d4ed8;flex-shrink:0;margin-top:2px;cursor:pointer}
.ro-icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0}
.ro-body{flex:1;min-width:0}
.ro-label{font-size:.85rem;font-weight:700;color:#1a2a4a}
.ro-desc{font-size:.75rem;color:#64748b;margin-top:.1rem}

/* Folder tree in modal */
.ft-item{margin-bottom:2px}
.ft-row{display:flex;align-items:center;gap:.5rem;padding:.38rem .5rem;border-radius:8px;transition:background .12s}
.ft-row:hover{background:#f5f7ff}
.ft-check-label{display:flex;align-items:center;gap:.4rem;cursor:pointer;flex:1;min-width:0;font-size:.84rem;color:#1a2a4a;font-weight:500}
.ft-check-label input{width:16px;height:16px;accent-color:#5b21b6;cursor:pointer;flex-shrink:0}
.ft-name{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ft-sub-label{display:flex;align-items:center;gap:.3rem;cursor:pointer;font-size:.73rem;
              color:#7c3aed;background:#f5f3ff;border:1px solid #ddd6fe;border-radius:6px;
              padding:.15rem .45rem;white-space:nowrap;flex-shrink:0}
.ft-sub-label input{width:14px;height:14px;accent-color:#7c3aed;cursor:pointer}
.ft-sub-label i{font-size:.78rem}
.ft-children{margin-left:0}

.search-input{padding:.4rem .8rem .4rem 2rem;border:1.5px solid #e4e8ef;border-radius:9px;
              font-size:.84rem;background:#fff;outline:none;width:220px}
.search-input:focus{border-color:#93c5fd}
.search-wrap{position:relative}
.search-wrap i{position:absolute;left:.65rem;top:50%;transform:translateY(-50%);
               color:#94a3b8;font-size:.82rem;pointer-events:none}

.modal-content{border-radius:14px;border:none}
.modal-header{border-bottom:1.5px solid #f0f2f5;padding:1.1rem 1.35rem}
.modal-body{padding:1.35rem}
.modal-footer{border-top:1.5px solid #f0f2f5;padding:.9rem 1.35rem}
.form-label{font-size:.79rem;font-weight:700;color:#475569;margin-bottom:.3rem}
.form-control,.form-select{border:1.5px solid #e4e8ef;border-radius:9px;font-size:.87rem}
.form-control:focus,.form-select:focus{border-color:#93c5fd;box-shadow:0 0 0 3px #dbeafe55;outline:none}
</style>
</head>
<body>

<div class="topbar">
    <a href="/qrepo/admin/drive.php" class="logo">
        <img src="/qrepo/assets/mist-logo.svg" alt="MIST">QRepo Admin
    </a>
    <div class="vl"></div>
    <span style="font-size:.83rem;color:#64748b">
        <a href="/qrepo/admin/drive.php" style="color:#64748b;text-decoration:none"><i class="bi bi-folder2 me-1"></i>Drive</a>
        <span style="color:#cbd5e1;margin:0 .4rem">›</span>
        <strong style="color:#1a2a4a">Users</strong>
    </span>
    <div class="ms-auto d-flex gap-2">
        <a href="/qrepo/" class="btn btn-sm btn-outline-secondary" style="font-size:.79rem"><i class="bi bi-eye me-1"></i>Site</a>
        <a href="/qrepo/admin/logout.php" class="btn btn-sm btn-outline-danger" style="font-size:.79rem"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</div>

<div class="page">

    <?php if ($msg): ?>
    <div class="alert alert-success py-2 mb-3 d-flex align-items-center gap-2">
        <i class="bi bi-check-circle-fill"></i><?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($err): ?>
    <div class="alert alert-danger py-2 mb-3 d-flex align-items-center gap-2">
        <i class="bi bi-exclamation-triangle-fill"></i><?= htmlspecialchars($err) ?>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- System Administrators -->
    <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="sec-label mb-0"><i class="bi bi-shield-lock-fill text-danger"></i> System Administrators</div>
    </div>
    <div class="tbl-wrap mb-4">
        <?php foreach ($sysAdmins as $sa): ?>
        <div class="sa-row">
            <div class="sa-av"><?= strtoupper(substr($sa['username'],0,1)) ?></div>
            <div class="flex-grow-1">
                <strong style="font-size:.88rem;color:#1a2a4a"><?= htmlspecialchars($sa['username']) ?></strong>
                <span class="ms-2" style="font-size:.72rem;color:#94a3b8">Full system access · no email required</span>
            </div>
            <button class="act" style="background:#fef3c7;color:#b45309"
                    onclick="openSaPw(<?=$sa['id']?>, '<?= htmlspecialchars(addslashes($sa['username'])) ?>')">
                <i class="bi bi-key"></i>Change Password
            </button>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Registered Users -->
    <div class="d-flex align-items-center justify-content-between mb-2">
        <div class="sec-label mb-0">
            <i class="bi bi-people-fill" style="color:#4338ca"></i>
            Registered Users
            <span style="font-weight:400;color:#b0b8c8">(<?= count($users) ?>)</span>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <div class="search-wrap">
                <i class="bi bi-search"></i>
                <input class="search-input" type="text" placeholder="Search…" oninput="filterTable(this.value)">
            </div>
            <button class="act" style="background:#1a2a4a;color:#fff" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-person-plus-fill"></i>Add User
            </button>
        </div>
    </div>

    <div class="tbl-wrap">
        <?php if (empty($users)): ?>
        <div class="text-center text-muted py-5" style="font-size:.88rem">
            No users yet. Click <strong>Add User</strong> to create one.
        </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th style="width:32%">User</th>
                    <th style="width:30%">Roles</th>
                    <th style="width:11%">Status</th>
                    <th style="width:27%">Actions</th>
                </tr>
            </thead>
            <tbody id="userTbody">
            <?php foreach ($users as $u):
                $userRoles   = $u['roles'] ? json_decode($u['roles'], true) : [$u['role']];
                $primaryRole = highestRole($userRoles);
                $ri          = $roleInfo[$primaryRole] ?? $roleInfo['viewer'];
                $init        = strtoupper(mb_substr($u['name'],0,1));
                $auth        = match($u['auth_provider']??'local'){ 'microsoft'=>'M365','both'=>'M365+PW',default=>'Password'};
                $fIds        = $u['folder_ids'] ? array_map('intval', explode(',',$u['folder_ids'])) : [];
                $sfIds       = $u['subfolder_ids'] ? array_map('intval', explode(',',$u['subfolder_ids'])) : [];
                $active      = ($u['status']==='active');
                $hasUpload   = in_array('submitter',$userRoles) || in_array('resource_manager',$userRoles);
            ?>
            <tr data-search="<?= strtolower($u['name'].' '.$u['email']) ?>">
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="av" style="background:<?=$ri['bg']?>;color:<?=$ri['color']?>"><?=$init?></div>
                        <div style="min-width:0">
                            <div style="font-weight:700;color:#1a2a4a;font-size:.86rem"><?= htmlspecialchars($u['name']) ?></div>
                            <div style="font-size:.75rem;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                <?= htmlspecialchars($u['email']) ?>
                                <span class="auth ms-1"><?=$auth?></span>
                            </div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="d-flex flex-wrap gap-1 align-items-center">
                        <?php foreach ($userRoles as $r):
                            $ri2 = $roleInfo[$r] ?? $roleInfo['viewer']; ?>
                        <span class="role-badge" style="background:<?=$ri2['bg']?>;color:<?=$ri2['color']?>;border-color:<?=$ri2['color']?>33">
                            <i class="bi <?=$ri2['icon']?>"></i><?= ROLE_LABELS[$r] ?>
                        </span>
                        <?php endforeach; ?>
                        <button class="act ms-1" style="background:#f0f4ff;color:#3730a3;padding:.2rem .45rem"
                                title="Edit roles"
                                onclick="openRoles(<?=$u['id']?>, '<?=htmlspecialchars(addslashes($u['name']))?>', <?=htmlspecialchars(json_encode($userRoles),ENT_QUOTES)?>)">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                    </div>
                </td>
                <td>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="uid" value="<?=$u['id']?>">
                        <button type="submit" class="pill <?=$active?'pill-active':'pill-inactive'?>"
                                style="border:none;cursor:pointer;background:<?=$active?'#f0fdf4':'#fef2f2'?>">
                            <i class="bi bi-<?=$active?'check-circle':'slash-circle'?>"></i>
                            <?=$active?'Active':'Inactive'?>
                        </button>
                    </form>
                </td>
                <td>
                    <div class="d-flex gap-1 flex-wrap">
                        <?php if ($hasUpload): ?>
                        <button class="act" style="background:#f5f3ff;color:#5b21b6"
                                onclick="openFolders(<?=$u['id']?>, '<?=htmlspecialchars(addslashes($u['name']))?>', <?=json_encode($fIds)?>, <?=json_encode($sfIds)?>)"
                                title="Folder access">
                            <i class="bi bi-folder2-open"></i>Folders<?= count($fIds) ? ' ('.count($fIds).')' : '' ?>
                        </button>
                        <?php endif; ?>
                        <button class="act" style="background:#fef3c7;color:#b45309"
                                onclick="openResetPw(<?=$u['id']?>, '<?=htmlspecialchars(addslashes($u['name']))?>')">
                            <i class="bi bi-key"></i>PW
                        </button>
                        <form method="POST" class="d-inline"
                              onsubmit="return confirm('Delete <?=htmlspecialchars(addslashes($u['name']))?>?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="uid" value="<?=$u['id']?>">
                            <button type="submit" class="act" style="background:#fef2f2;color:#dc2626">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

</div>

<!-- ══ Add User Modal ══ -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:.97rem;font-weight:800;color:#1a2a4a">
                    <i class="bi bi-person-plus-fill me-2 text-primary"></i>Add User
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-body d-flex flex-column gap-3">
                    <div>
                        <label class="form-label">Full Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Md. Karim" required>
                    </div>
                    <div>
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" placeholder="user@mist.ac.bd" required>
                    </div>
                    <div>
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" placeholder="Min 6 characters" required minlength="6">
                    </div>
                    <div>
                        <label class="form-label">Roles (select one or more)</label>
                        <?php foreach ($roleInfo as $r => $info): ?>
                        <label class="role-opt" id="cro-<?=$r?>">
                            <input type="checkbox" name="roles[]" value="<?=$r?>"
                                   <?=$r==='viewer'?'checked':''?>
                                   onchange="updateRoleOpt(this,'cro-<?=$r?>')">
                            <div class="ro-icon" style="background:<?=$info['bg']?>;color:<?=$info['color']?>">
                                <i class="bi <?=$info['icon']?>"></i>
                            </div>
                            <div class="ro-body">
                                <div class="ro-label"><?= ROLE_LABELS[$r] ?></div>
                                <div class="ro-desc"><?= $info['desc'] ?></div>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background:#1a2a4a;color:#fff;font-weight:700;border-radius:9px;padding:.42rem 1rem">
                        <i class="bi bi-person-check me-1"></i>Create
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ Edit Roles Modal ══ -->
<div class="modal fade" id="rolesModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:.95rem;font-weight:800;color:#1a2a4a">
                    <i class="bi bi-person-badge me-2 text-primary"></i>Roles — <span id="rName"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="role">
                <input type="hidden" name="uid" id="rUid">
                <div class="modal-body" id="rolesBody"></div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary" style="font-weight:700;border-radius:9px;padding:.42rem 1rem">
                        <i class="bi bi-check-lg me-1"></i>Save Roles
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ Reset Password Modal ══ -->
<div class="modal fade" id="resetPwModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:380px">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:.95rem;font-weight:800;color:#1a2a4a">
                    <i class="bi bi-key-fill me-2 text-warning"></i>Reset Password — <span id="rpwName"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="resetpw">
                <input type="hidden" name="uid" id="rpwUid">
                <div class="modal-body">
                    <label class="form-label">New Password *</label>
                    <input type="password" name="password" class="form-control" placeholder="Min 6 characters" required minlength="6">
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background:#b45309;color:#fff;font-weight:700;border-radius:9px;padding:.42rem 1rem">
                        <i class="bi bi-check-lg me-1"></i>Reset
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ Folder Access Modal ══ -->
<div class="modal fade" id="foldersModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:460px">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:.95rem;font-weight:800;color:#1a2a4a">
                    <i class="bi bi-folder2-open me-2" style="color:#7c3aed"></i>Folder Access — <span id="fName"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="folders">
                <input type="hidden" name="uid" id="fUid">
                <div class="modal-body">
                    <p style="font-size:.8rem;color:#64748b;margin-bottom:.85rem">
                        Check folders this user can upload to.
                        Enable <span style="color:#7c3aed;font-weight:700"><i class="bi bi-diagram-3"></i> +sub-folders</span>
                        to also grant access to all folders inside.
                    </p>
                    <?php if (empty($allFolders)): ?>
                    <p class="text-muted" style="font-size:.83rem">No folders yet. Create folders in Drive first.</p>
                    <?php else: ?>
                    <div style="max-height:340px;overflow-y:auto;padding:.25rem 0" id="folderTreeWrap">
                        <?php renderFolderTree($folderTree); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background:#5b21b6;color:#fff;font-weight:700;border-radius:9px;padding:.42rem 1rem">
                        <i class="bi bi-check-lg me-1"></i>Save Access
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ System Admin Password Modal ══ -->
<div class="modal fade" id="saPwModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered" style="max-width:380px">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:.95rem;font-weight:800;color:#1a2a4a">
                    <i class="bi bi-key-fill me-2 text-warning"></i>Change Password — <span id="saName"></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="sa_pw">
                <input type="hidden" name="said" id="saId">
                <div class="modal-body">
                    <label class="form-label">New Password *</label>
                    <input type="password" name="password" class="form-control" placeholder="Min 6 characters" required minlength="6">
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm" style="background:#b45309;color:#fff;font-weight:700;border-radius:9px;padding:.42rem 1rem">
                        <i class="bi bi-check-lg me-1"></i>Update
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const ROLE_INFO = <?= json_encode($roleInfo) ?>;
const ROLE_LABELS = <?= json_encode(ROLE_LABELS) ?>;

/* ── Search ── */
function filterTable(q) {
    q = q.toLowerCase();
    document.querySelectorAll('#userTbody tr').forEach(tr => {
        tr.style.display = tr.dataset.search.includes(q) ? '' : 'none';
    });
}

/* ── Role option style sync ── */
function updateRoleOpt(cb, id) {
    document.getElementById(id).classList.toggle('checked', cb.checked);
}
// Init create modal on open
document.getElementById('addUserModal').addEventListener('show.bs.modal', () => {
    document.querySelectorAll('#addUserModal .role-opt').forEach(el => {
        const cb = el.querySelector('input[type=checkbox]');
        el.classList.toggle('checked', cb.checked);
    });
});

/* ── Edit Roles modal ── */
function openRoles(uid, name, currentRoles) {
    document.getElementById('rUid').value = uid;
    document.getElementById('rName').textContent = name;

    let html = '';
    for (const [r, info] of Object.entries(ROLE_INFO)) {
        const checked = currentRoles.includes(r);
        html += `<label class="role-opt ${checked?'checked':''}" id="ro-${r}">
            <input type="checkbox" name="roles[]" value="${r}" ${checked?'checked':''}
                   onchange="updateRoleOpt(this,'ro-${r}')">
            <div class="ro-icon" style="background:${info.bg};color:${info.color}">
                <i class="bi ${info.icon}"></i>
            </div>
            <div class="ro-body">
                <div class="ro-label">${ROLE_LABELS[r]}</div>
                <div class="ro-desc">${info.desc}</div>
            </div>
        </label>`;
    }
    document.getElementById('rolesBody').innerHTML = html;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('rolesModal')).show();
}

/* ── Reset PW ── */
function openResetPw(uid, name) {
    document.getElementById('rpwUid').value = uid;
    document.getElementById('rpwName').textContent = name;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('resetPwModal')).show();
}

/* ── Folder access ── */
function openFolders(uid, name, assignedIds, subfolderIds) {
    document.getElementById('fUid').value = uid;
    document.getElementById('fName').textContent = name;

    // Reset all checkboxes
    document.querySelectorAll('#folderTreeWrap .ft-folder-cb').forEach(cb => {
        const fid = parseInt(cb.value);
        cb.checked = assignedIds.includes(fid);
        const subLabel = cb.closest('.ft-row').querySelector('.ft-sub-label');
        if (subLabel) subLabel.style.display = cb.checked ? 'flex' : 'none';
    });

    // Set sub-folder toggles
    document.querySelectorAll('#folderTreeWrap .ft-sub-cb').forEach(cb => {
        cb.checked = subfolderIds.includes(parseInt(cb.value));
    });

    bootstrap.Modal.getOrCreateInstance(document.getElementById('foldersModal')).show();
}

/* Toggle folder sub-label visibility */
function ftToggle(cb) {
    const subLabel = cb.closest('.ft-row').querySelector('.ft-sub-label');
    if (subLabel) {
        subLabel.style.display = cb.checked ? 'flex' : 'none';
        if (!cb.checked) {
            const subCb = subLabel.querySelector('.ft-sub-cb');
            if (subCb) subCb.checked = false;
        }
    }
}

/* ── System admin PW ── */
function openSaPw(id, name) {
    document.getElementById('saId').value = id;
    document.getElementById('saName').textContent = name;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('saPwModal')).show();
}
</script>
</body>
</html>
