<?php
require_once __DIR__ . '/../../src/bootstrap.php';

require_post('/signin.php');

const MAX_ATTEMPTS = 5;
const MAX_ACCOUNT_ATTEMPTS = 20; // From every connection together, so changing IP doesn't allow endless guesses.
const MAX_IP_ATTEMPTS = 60; // Across accounts, against trying one password on many people. High because mobile networks share IPs.
// Checked when the account doesn't exist, so response time doesn't reveal which accounts do.
const DUMMY_HASH = '$2y$12$u.zJmE0XRZZKUMjtV6pOXec2KtoEAt/W8B3LyA9geXmc3gmDIAUHC';

$login = post('login');
$password = post('password', false);
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

if ($login === '' || strlen($login) > 254) {
    flash('Invalid email, username or password.', '/signin.php');
}

run("DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 15 MINUTE");
$attempts = run("SELECT COUNT(*) FROM login_attempts WHERE username = ? AND ip = ?", 'ss', $login, $ip)
    ->get_result()->fetch_row()[0];
$accountAttempts = run("SELECT COUNT(*) FROM login_attempts WHERE username = ?", 's', $login)->get_result()->fetch_row()[0];
if ($attempts >= MAX_ATTEMPTS || $accountAttempts >= MAX_ACCOUNT_ATTEMPTS || rate_limited("signin:ip:$ip", MAX_IP_ATTEMPTS, 900)) {
    flash('Too many failed attempts. Please try again in 15 minutes.', '/signin.php');
}

// Usernames can't contain @, so a value can only ever match one account.
$user = run("SELECT userID, email, password, email_verified_at, suspended_at, suspended_reason FROM users WHERE email = ? OR username = ?", 'ss', $login, $login)->get_result()->fetch_assoc();

if (!password_verify($password, $user['password'] ?? DUMMY_HASH) || !$user) {
    run("INSERT INTO login_attempts (username, ip) VALUES (?, ?)", 'ss', $login, $ip);
    flash('Invalid email, username or password.', '/signin.php');
}

run("DELETE FROM login_attempts WHERE username = ? AND ip = ?", 'ss', $login, $ip);

if (!$user['email_verified_at']) {
    $_SESSION['verify_email'] = $user['email'];
    flash('Please confirm your email before signing in.', '/verify-email.php');
}

if ($user['suspended_at']) {
    flash('This account is suspended: ' . $user['suspended_reason'] . ' See the rules and policies page for how to ask us to look again.', '/signin.php');
}

session_regenerate_id(true);
$_SESSION['user_id'] = $user['userID'];
$_SESSION['password_check'] = password_session_check($user['password']);
go(safe_next(post('next')));
