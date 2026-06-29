<?php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_URL', '/qrepo/uploads/');
define('APP_NAME', 'MIST Question Repository');
define('SESSION_NAME', 'qrepo_session');

// Microsoft OAuth2 (Azure AD) — register at https://portal.azure.com
define('MS_CLIENT_ID',     '');
define('MS_CLIENT_SECRET', '');
define('MS_TENANT_ID',     '');
define('MS_REDIRECT_URI',  'http://your-domain/qrepo/auth/microsoft/callback.php');
define('MS_ENABLED', MS_CLIENT_ID !== '' && MS_CLIENT_SECRET !== '');
