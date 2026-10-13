<?php

declare(strict_types=1);
function require_login(): void
{
    if (empty($_SESSION['user'])) {
        header('Location: ' . APP_BASE_URL . '/frontend/login.php');
        exit;
    }
}
function require_admin(): void
{
    require_login();
    if (($_SESSION['user']['role'] ?? '') !== 'Admin') {
        http_response_code(403);
        exit('Administrator access is required.');
    }
}
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = ['id' => (int)$user['id'], 'name' => $user['full_name'], 'username' => $user['username'], 'role' => $user['role']];
}
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
