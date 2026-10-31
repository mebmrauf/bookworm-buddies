<?php
require_once __DIR__ . '/../../src/admin.php';

const MAX_ATTEMPTS = 5;

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
    run("DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 15 MINUTE");
    $attempts = run("SELECT COUNT(*) FROM login_attempts WHERE username = 'admin' AND ip = ?", 's', $ip)->get_result()->fetch_row()[0];
    if ($attempts >= MAX_ATTEMPTS) {
        flash('Too many failed attempts. Please try again in 15 minutes.', '/admin/login.php');
    }

    $emailMatches = hash_equals(strtolower($admin['email']), strtolower(post('email')));
    if (!password_verify(post('password', false), $admin['password_hash']) || !$emailMatches) {
        run("INSERT INTO login_attempts (username, ip) VALUES ('admin', ?)", 's', $ip);
        flash('Invalid email or password.', '/admin/login.php');
    }

    run("DELETE FROM login_attempts WHERE username = 'admin' AND ip = ?", 's', $ip);
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
        <div class="password-field">
            <input id="password" type="password" name="password" autocomplete="current-password" required>
            <button type="button" class="password-field__toggle" data-password-toggle aria-label="Show password" aria-pressed="false">
                <span data-eye-show><?= icon('eye') ?></span>
                <span data-eye-hide hidden><?= icon('eye-off') ?></span>
            </button>
        </div>
    </div>
    <button class="btn btn--primary btn--block" type="submit">Sign in</button>
</form>
<?php include __DIR__ . '/../../templates/auth-bottom.php'; ?>
