<?php
require_once __DIR__ . '/includes/auth.php';
userLogout();
logout();
header('Location: /qrepo/login.php');
exit;
