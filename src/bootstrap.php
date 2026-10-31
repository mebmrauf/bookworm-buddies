<?php
// Visitors never see internals; the details go to the server log.
ini_set('display_errors', '0');
set_exception_handler(function (Throwable $e) {
    error_log((string) $e);
    http_response_code(500);
    echo 'Something went wrong. Please try again.';
});

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'inline-speculation-rules'; img-src 'self' https://res.cloudinary.com blob:; style-src 'self'; font-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

// Revalidates on normal visits, but unlike PHP's default no-store it lets the back button be instant.
session_cache_limiter('');
header('Cache-Control: private, no-cache');

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/validation.php';
require_once __DIR__ . '/cloudinary.php';
require_once __DIR__ . '/mail.php';
require_once __DIR__ . '/view.php';

const DUPLICATE_KEY = 1062;

// 0 means a guest. Public pages use this; private ones go through auth.php.
$userID = (int) ($_SESSION['user_id'] ?? 0);

// % and _ are wildcards in LIKE, so a search for "%" must not match everything.
function like_pattern($text) {
    return '%' . addcslashes($text, '%_\\') . '%';
}

function current_user() {
    global $userID;
    static $user = false;
    if ($user === false) {
        $user = !$userID ? null : run("
            SELECT first_name, last_name, avatar_public_id, city, address,
                (SELECT COUNT(*) FROM swap WHERE ownerID = users.userID AND status = 'pending') AS pending
            FROM users WHERE userID = ?
        ", 'i', $userID)->get_result()->fetch_assoc();
    }
    return $user;
}

// Only same-site paths, so a crafted link can't send people to another site after sign-in.
function safe_next($path) {
    return is_string($path) && preg_match('#^/(?![/\\\\])#', $path) && !str_contains($path, 'actions/') ? $path : '/home.php';
}

// Crafted requests can send arrays where a string is expected.
function post($key, $trim = true) {
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    return $trim ? trim($value) : $value;
}

function go($url) {
    header("Location: $url");
    exit();
}

function flash($message, $url, $type = 'error') {
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
    go($url);
}

function csrf_token() {
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function require_csrf($url) {
    if (!hash_equals(csrf_token(), post('csrf'))) {
        flash('That page expired. Please try again.', $url);
    }
}

// For endpoints that only change data: refuses plain links and forged forms.
function require_post($url) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        go($url);
    }
    require_csrf($url);
}
