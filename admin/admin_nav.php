<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<header class="admin-header">
    <i class="bi bi-journal-bookmark-fill fs-5"></i>
    <span class="fw-bold"><?= APP_NAME ?></span>
    <span class="badge bg-warning text-dark ms-1">Admin</span>
    <div class="ms-auto d-flex gap-2 align-items-center">
        <span class="text-white-50 small d-none d-md-inline">
            <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['admin_username'] ?? '') ?>
        </span>
        <a href="/qrepo/" class="btn btn-sm btn-outline-light">
            <i class="bi bi-eye me-1"></i>View Site
        </a>
        <a href="/qrepo/admin/logout.php" class="btn btn-sm btn-outline-danger">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</header>
<nav class="admin-sidebar">
    <div class="sidebar-section">Main</div>
    <a href="/qrepo/admin/" class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>">
        <i class="bi bi-speedometer2"></i> Dashboard
    </a>
    <div class="sidebar-section mt-2">Content</div>
    <a href="/qrepo/admin/folders.php" class="nav-link <?= $currentPage === 'folders.php' ? 'active' : '' ?>">
        <i class="bi bi-folder2"></i> Folders
    </a>
    <a href="/qrepo/admin/files.php" class="nav-link <?= $currentPage === 'files.php' ? 'active' : '' ?>">
        <i class="bi bi-file-earmark-arrow-up"></i> Upload Files
    </a>
    <a href="/qrepo/admin/manage_files.php" class="nav-link <?= $currentPage === 'manage_files.php' ? 'active' : '' ?>">
        <i class="bi bi-files"></i> Manage Files
    </a>
    <div class="sidebar-section mt-2">Account</div>
    <a href="/qrepo/admin/change_password.php" class="nav-link <?= $currentPage === 'change_password.php' ? 'active' : '' ?>">
        <i class="bi bi-key"></i> Change Password
    </a>
</nav>
