<?php
// Visitors never see internals; the details go to the server log.
ini_set('display_errors', '0');
set_exception_handler(function (Throwable $e) {
    error_log((string) $e);
    http_response_code(500);
    echo 'Something went wrong. Please try again.';
});

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();
require_once __DIR__ . '/dbconnect.php';

const DUPLICATE_KEY = 1062;

function e($value) {
    return htmlspecialchars((string) $value);
}

function run($sql, $types = '', ...$params) {
    global $conn;
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
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

function show_flash() {
    if (isset($_SESSION['flash'])) {
        echo '<p class="' . e($_SESSION['flash']['type']) . '-message">' . e($_SESSION['flash']['message']) . '</p>';
        unset($_SESSION['flash']);
    }
}

function csrf_token() {
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function require_csrf($url) {
    if (!hash_equals(csrf_token(), post('csrf'))) {
        flash('That page expired. Please try again.', $url);
    }
}

// For pages that only change data: refuses plain links and forged forms.
function require_post($url) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        go($url);
    }
    require_csrf($url);
}

// Returns an error message, or null when valid.
function validate_user($firstName, $lastName, $email, $dob, $username) {
    if ($firstName === '' || $lastName === '' || $email === '' || $dob === '' || $username === '') {
        return 'All fields are required.';
    }
    if (mb_strlen($firstName) > 50 || mb_strlen($lastName) > 50) {
        return 'Names can be at most 50 characters.';
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        return 'Username must be 3-50 letters, numbers, dots, dashes or underscores.';
    }
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }
    $date = DateTime::createFromFormat('!Y-m-d', $dob);
    if (!$date || $date->format('Y-m-d') !== $dob || $date > new DateTime()) {
        return 'Please enter a valid date of birth.';
    }
    return null;
}

function validate_password($password) {
    // bcrypt ignores anything past 72 bytes
    if (strlen($password) < 8 || strlen($password) > 72) {
        return 'Password must be 8-72 characters.';
    }
    return null;
}

function validate_book($title, $author, $year) {
    if ($title === '' || $author === '' || $year === '') {
        return 'All fields are required.';
    }
    if (mb_strlen($title) > 255 || mb_strlen($author) > 255) {
        return 'Title and author can be at most 255 characters.';
    }
    if (!ctype_digit($year) || (int) $year < 1 || (int) $year > (int) date('Y')) {
        return 'Please enter a valid published year.';
    }
    return null;
}
