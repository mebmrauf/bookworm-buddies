<?php
require_once __DIR__ . '/../../src/bootstrap.php';

require_post('/signin.php');

const MAX_ATTEMPTS = 5;

$login = post('login');
$password = post('password', false);
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

if ($login === '' || strlen($login) > 254) {
    flash('Invalid email, username or password.', '/signin.php');
}

run("DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 15 MINUTE");
$attempts = run("SELECT COUNT(*) FROM login_attempts WHERE username = ? AND ip = ?", 'ss', $login, $ip)
    ->get_result()->fetch_row()[0];
if ($attempts >= MAX_ATTEMPTS) {
    flash('Too many failed attempts. Please try again in 15 minutes.', '/signin.php');
}

// Usernames can't contain @, so a value can only ever match one account.
$user = run("SELECT userID, email, password, email_verified_at, suspended_at, suspended_reason FROM users WHERE email = ? OR username = ?", 'ss', $login, $login)->get_result()->fetch_assoc();

if (!$user || !password_verify($password, $user['password'])) {
    run("INSERT INTO login_attempts (username, ip) VALUES (?, ?)", 'ss', $login, $ip);
    flash('Invalid email, username or password.', '/signin.php');
}

run("DELETE FROM login_attempts WHERE username = ? AND ip = ?", 'ss', $login, $ip);

if (!$user['email_verified_at']) {
    $_SESSION['verify_email'] = $user['email'];
    flash('Please confirm your email before signing in.', '/verify-email.php');
}

if ($user['suspended_at']) {
    flash('This account is suspended: ' . $user['suspended_reason'] . ' See the community rules for how to get in touch.', '/signin.php');
}

session_regenerate_id(true);
$_SESSION['user_id'] = $user['userID'];
go(safe_next(post('next')));
