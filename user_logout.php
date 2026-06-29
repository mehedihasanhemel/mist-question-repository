<?php
require_once __DIR__ . '/includes/auth.php';
userLogout();
header('Location: /qrepo/login.php');
exit;
