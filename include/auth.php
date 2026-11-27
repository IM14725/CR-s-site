<?php
declare(strict_types=1);
const REG_PATTERN = '#^NIT/[A-Z]{2,6}/\d{4}/\d{1,6}$#';

require_once __DIR__ . '/db.php';

//SECURE SESSION 
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_httponly', 1);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'lax',
    ]);
    session_start();
}

//LOG OUT AFTER 30 MINUTES OF INACTIVITY
const SESSION_IDLE_LIMIT = 1800; // 30 minutes in seconds
if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > SESSION_IDLE_LIMIT) {
    $_SESSION = [];
    session_regenerate_id(true);
}
$_SESSION['last_activity'] = time();

//OUTPUT ESCAPING
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

//CSRF PROTECTION
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' .csrf_token() . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('Invalid request. Please refresh the page and try again.');
    }
}

//LOGIN / LOGOUT
function login_user(string $id, string $role): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
    $_SESSION['user_role'] = $role;
}

function logout_user(): void
{
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $p['path'],
        'domain' => $p['domain'],
        'secure' => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => $p['samesite'],
    ]);
    session_destroy();
}

function current_user_id(): ?string
{
    return $_SESSION['user_id'] ?? null;
}

//PAGE GUARD
function require_role(string $role, string $loginPage): void
{
    if (($_SESSION['role'] ?? '') !== $role) {
        header("Location: $loginPage");
        exit();
    }
}

function require_admin(): void
{
    require_role('admin', 'admin-login.php');
}
function require_cr(): void
{
    require_role('cr', 'login.php');
}