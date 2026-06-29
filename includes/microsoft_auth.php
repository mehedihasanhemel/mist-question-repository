<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';

function getMicrosoftAuthUrl(): string {
    startSession();
    $state = bin2hex(random_bytes(16));
    $_SESSION['ms_oauth_state'] = $state;

    $params = http_build_query([
        'client_id'     => MS_CLIENT_ID,
        'response_type' => 'code',
        'redirect_uri'  => MS_REDIRECT_URI,
        'response_mode' => 'query',
        'scope'         => 'openid email profile User.Read',
        'state'         => $state,
        'prompt'        => 'select_account',
    ]);

    return "https://login.microsoftonline.com/" . MS_TENANT_ID . "/oauth2/v2.0/authorize?" . $params;
}

function exchangeMicrosoftCode(string $code): ?array {
    $url  = "https://login.microsoftonline.com/" . MS_TENANT_ID . "/oauth2/v2.0/token";
    $body = http_build_query([
        'client_id'     => MS_CLIENT_ID,
        'client_secret' => MS_CLIENT_SECRET,
        'code'          => $code,
        'redirect_uri'  => MS_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) return null;
    return json_decode($response, true);
}

function getMicrosoftUserInfo(string $accessToken): ?array {
    $ch = curl_init('https://graph.microsoft.com/v1.0/me');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ["Authorization: Bearer $accessToken"],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) return null;
    $data = json_decode($response, true);

    if (empty($data['mail']) && empty($data['userPrincipalName'])) return null;

    return [
        'email' => $data['mail'] ?? $data['userPrincipalName'],
        'name'  => trim(($data['displayName'] ?? '') ?: ($data['givenName'] . ' ' . $data['surname'])),
    ];
}

function loginOrCreateMicrosoftUser(array $msUser): bool {
    $email = strtolower(trim($msUser['email']));
    $name  = trim($msUser['name']);

    $stmt = db()->prepare("SELECT * FROM qrepo_users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    startSession();

    if (!$user) {
        // Brand-new account — created via Microsoft, no password yet
        $stmt = db()->prepare(
            "INSERT INTO qrepo_users (name, email, password, auth_provider, status)
             VALUES (?, ?, NULL, 'microsoft', 'active')"
        );
        $stmt->execute([$name, $email]);
        $userId = (int) db()->lastInsertId();

        $_SESSION['user_id']           = $userId;
        $_SESSION['user_name']         = $name;
        $_SESSION['user_email']        = $email;
        $_SESSION['user_role']         = 'viewer';
        $_SESSION['user_roles']        = ['viewer'];
        $_SESSION['user_auth']         = 'microsoft';
        $_SESSION['user_has_password'] = false;
        $_SESSION['ms_first_login']    = true;
        return true;
    }

    if ($user['status'] !== 'active') return false;

    // Existing account — upgrade auth_provider if they now have both
    $provider = $user['auth_provider'];
    if ($provider === 'local') {
        db()->prepare("UPDATE qrepo_users SET auth_provider = 'both' WHERE id = ?")
             ->execute([$user['id']]);
        $provider = 'both';
    }

    // Update name if it changed
    if ($user['name'] !== $name) {
        db()->prepare("UPDATE qrepo_users SET name = ? WHERE id = ?")->execute([$name, $user['id']]);
    }

    $_SESSION['user_id']           = (int) $user['id'];
    $_SESSION['user_name']         = $name;
    $_SESSION['user_email']        = $email;
    $_SESSION['user_role']         = $user['role'] ?? 'viewer';
    $_SESSION['user_roles']        = $user['roles'] ? json_decode($user['roles'], true) : [$user['role'] ?? 'viewer'];
    $_SESSION['user_auth']         = 'microsoft';
    $_SESSION['user_has_password'] = !empty($user['password']);
    return true;
}
