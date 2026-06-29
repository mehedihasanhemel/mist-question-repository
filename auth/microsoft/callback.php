<?php
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/microsoft_auth.php';

startSession();

function redirectWithError(string $code): never {
    header('Location: /qrepo/login.php?error=' . $code);
    exit;
}

// Check for Microsoft error response
if (!empty($_GET['error'])) {
    redirectWithError('ms_' . htmlspecialchars($_GET['error']));
}

// Validate state (CSRF protection)
$state         = $_GET['state']  ?? '';
$sessionState  = $_SESSION['ms_oauth_state'] ?? '';
unset($_SESSION['ms_oauth_state']);

if (!$state || !hash_equals($sessionState, $state)) {
    redirectWithError('invalid_state');
}

$code = $_GET['code'] ?? '';
if (!$code) redirectWithError('no_code');

// Exchange code for tokens
$tokens = exchangeMicrosoftCode($code);
if (!$tokens || empty($tokens['access_token'])) {
    redirectWithError('token_exchange_failed');
}

// Get user profile from Microsoft Graph
$msUser = getMicrosoftUserInfo($tokens['access_token']);
if (!$msUser) redirectWithError('profile_fetch_failed');

// Login or create local user
if (!loginOrCreateMicrosoftUser($msUser)) {
    redirectWithError('account_inactive');
}

// Success — everyone goes to the main site
$redirect = $_SESSION['ms_redirect'] ?? '/qrepo/';
unset($_SESSION['ms_redirect']);
header('Location: ' . $redirect);
exit;
