<?php
require_once 'lib.php';

require_post('index.php');

const MAX_ATTEMPTS = 5;

$username = post('username');
$password = post('password', false);
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

if ($username === '' || mb_strlen($username) > 50) {
    flash('Invalid username or password.', 'index.php');
}

run("DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 15 MINUTE");
$attempts = run("SELECT COUNT(*) FROM login_attempts WHERE username = ? AND ip = ?", 'ss', $username, $ip)
    ->get_result()->fetch_row()[0];
if ($attempts >= MAX_ATTEMPTS) {
    flash('Too many failed attempts. Please try again in 15 minutes.', 'index.php');
}

$user = run("SELECT userID, password FROM users WHERE username = ?", 's', $username)->get_result()->fetch_assoc();

if (!$user || !password_verify($password, $user['password'])) {
    run("INSERT INTO login_attempts (username, ip) VALUES (?, ?)", 'ss', $username, $ip);
    flash('Invalid username or password.', 'index.php');
}

run("DELETE FROM login_attempts WHERE username = ? AND ip = ?", 'ss', $username, $ip);
session_regenerate_id(true);
$_SESSION['user_id'] = $user['userID'];
go('home.php');
