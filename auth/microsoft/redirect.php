<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/microsoft_auth.php';

if (!MS_ENABLED) {
    header('Location: /qrepo/login.php?error=ms_not_configured');
    exit;
}

if (isUserLoggedIn()) {
    header('Location: /qrepo/');
    exit;
}

header('Location: ' . getMicrosoftAuthUrl());
exit;
