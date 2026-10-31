<?php
require_once __DIR__ . '/../src/bootstrap.php';

$token = $_POST['token'] ?? $_GET['token'] ?? '';
$token = is_string($token) ? $token : '';

$user = run(
    "SELECT userID, username, email FROM users WHERE reset_token_hash = ? AND reset_expires_at > NOW()",
    's', hash('sha256', $token)
)->get_result()->fetch_assoc();

// Changing the password takes a form: email scanners open links, and must not use them up by doing so.
if ($user && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $resetPage = '/reset-password.php?token=' . urlencode($token);
    require_csrf($resetPage);
    $password = post('password', false);
    $error = validate_password($password);
    if ($error) {
        flash($error, $resetPage);
    }

    // Opening the link proves they own the email, so an unconfirmed account counts as confirmed.
    // Other devices sign out at their next page, because the password they signed in with is gone.
    run("
        UPDATE users
        SET password = ?, reset_token_hash = NULL, reset_expires_at = NULL, email_verified_at = COALESCE(email_verified_at, NOW())
        WHERE userID = ?
    ", 'si', password_hash($password, PASSWORD_DEFAULT), $user['userID']);
    run("DELETE FROM login_attempts WHERE username IN (?, ?)", 'ss', $user['username'], $user['email']);
    session_regenerate_id(true);
    unset($_SESSION['user_id'], $_SESSION['password_check']);
    flash('Your password has been changed. You can sign in now.', '/signin.php', 'success');
}

$pageTitle = 'Choose a new password';
include __DIR__ . '/../templates/auth-top.php';
?>
<?php if ($user): ?>
    <h1>Choose a new password</h1>
    <p class="muted">This also signs you out on your other devices.</p>
    <form class="form" action="/reset-password.php" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="field">
            <label for="password">New password</label>
            <input id="password" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" aria-describedby="password-help" required autofocus>
            <p class="help" id="password-help">At least 8 characters.</p>
        </div>
        <button class="btn btn--primary btn--block" type="submit">Change password</button>
    </form>
<?php else: ?>
    <h1>This link doesn't work</h1>
    <p class="muted">It may have expired or already been used.</p>
    <p class="auth__switch"><a class="btn btn--primary btn--block" href="/forgot-password.php">Get a new link</a></p>
    <p class="auth__switch"><a class="link" href="/signin.php">Sign in</a></p>
<?php endif; ?>
<?php include __DIR__ . '/../templates/auth-bottom.php'; ?>
