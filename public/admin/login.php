<?php
require_once __DIR__ . '/../../src/admin.php';

const MAX_ATTEMPTS = 5;
const MAX_ALL_ATTEMPTS = 20;

$admin = admin_config();
if (!$admin) {
    http_response_code(404);
    exit('Not found');
}
if (!empty($_SESSION['is_admin'])) {
    go('/admin/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/admin/login.php');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    // From every connection together too, so changing IP doesn't allow endless guesses.
    if (rate_count("admin-fail:$ip", 900) >= MAX_ATTEMPTS || rate_limited('admin-login:all', MAX_ALL_ATTEMPTS, 900)) {
        flash('Too many failed attempts. Please try again in 15 minutes.', '/admin/login.php');
    }

    $emailMatches = hash_equals(strtolower($admin['email']), strtolower(post('email')));
    if (!password_verify(post('password', false), $admin['password_hash']) || !$emailMatches) {
        rate_record("admin-fail:$ip");
        flash('Invalid email or password.', '/admin/login.php');
    }

    rate_clear("admin-fail:$ip");
    session_regenerate_id(true);
    $_SESSION['is_admin'] = true;
    admin_log('login', null, null);
    go('/admin/index.php');
}

$pageTitle = 'Admin Login';
include __DIR__ . '/../../templates/auth-top.php';
?>
<h1>Admin Login</h1>
<form class="form" action="/admin/login.php" method="post">
    <?= csrf_field() ?>
    <div class="field">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" autocomplete="username" required autofocus>
    </div>
    <div class="field">
        <label for="password">Password</label>
        <?= password_input('password', 'password', 'autocomplete="current-password" required') ?>
    </div>
    <button class="btn btn--primary btn--block" type="submit">Sign in</button>
</form>
<?php include __DIR__ . '/../../templates/auth-bottom.php'; ?>
