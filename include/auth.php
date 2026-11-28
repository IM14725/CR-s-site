<?php
declare(strict_types=1);

date_default_timezone_set('Africa/Dar_es_Salaam');

const REG_PATTERN = '#^NIT/[A-Z]{2,6}/\d{4}/\d{1,6}$#';

const ROLE_LABELS = [
    'admin'    => 'Admin',
    'ministry' => 'Ministry of Education',
    'cr'       => 'CR',
];

require_once __DIR__ . '/db.php';

// ---------- Secure session ----------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('CRS_SESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

// Log out after 30 minutes of no activity
const SESSION_IDLE_LIMIT = 1800;
if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > SESSION_IDLE_LIMIT) {
    $_SESSION = [];
    session_regenerate_id(true);
}
$_SESSION['last_activity'] = time();

// ---------- Output escaping ----------
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// ---------- CSRF protection ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        exit('Invalid request. Please reload the page and try again.');
    }
}

// ---------- Login / logout ----------
function login_user(string $id): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
}

function logout_user(): void
{
    $_SESSION = [];
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => $p['path'],
        'domain'   => $p['domain'],
        'secure'   => $p['secure'],
        'httponly' => $p['httponly'],
        'samesite' => $p['samesite'],
    ]);
    session_destroy();
}

function current_user_id(): ?string
{
    return $_SESSION['user_id'] ?? null;
}

/**
 * The logged-in user, read fresh from the database on every request.
 * So if the admin changes someone's role or deletes them, it applies immediately.
 */
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $user = null;
        if (!empty($_SESSION['user_id'])) {
            $stmt = db()->prepare('SELECT id, role, full_name, reg_number, username, level, stream FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
            if ($user === null) {          // account was deleted
                $_SESSION = [];
                session_regenerate_id(true);
            }
        }
    }
    return $user;
}

// ---------- Roles ----------
function home_page(string $role): string
{
    return $role === 'cr' ? 'index.php' : 'admin.php';
}

/** Stop the page unless the user has one of $roles. Returns the user. */
function require_roles(string ...$roles): array
{
    $user = current_user();
    if ($user === null) {
        header('Location: login.php');
        exit;
    }
    if (!in_array($user['role'], $roles, true)) {
        header('Location: ' . home_page($user['role']));
        exit;
    }
    return $user;
}

function require_staff(): array    { return require_roles('admin', 'ministry'); }
function require_any_user(): array { return require_roles('admin', 'ministry', 'cr'); }

/** Roles that $actorRole may create, edit and delete. */
function manageable_roles(string $actorRole): array
{
    return match ($actorRole) {
        'admin'    => ['ministry', 'cr'],
        'ministry' => ['cr'],
        default    => [],
    };
}

function can_manage(string $actorRole, string $targetRole): bool
{
    return in_array($targetRole, manageable_roles($actorRole), true);
}
