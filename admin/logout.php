<?php
require_once __DIR__ . '/../includes/auth.php';
logout();
header('Location: /qrepo/admin/login.php');
exit;
