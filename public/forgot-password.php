<?php
require_once __DIR__ . '/../src/bootstrap.php';

const MAX_REQUESTS_PER_HOUR = 10;
const MAX_RESETS_PER_ADDRESS = 3;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/forgot-password.php');
    $email = post('email');

    if (validate_email($email)) {
        flash('Please enter a valid email address.', '/forgot-password.php');
    }
    if (rate_limited('reset:ip:' . $_SERVER['REMOTE_ADDR'], MAX_REQUESTS_PER_HOUR, 3600)) {
        flash('Too many requests from this connection. Please try again in an hour.', '/forgot-password.php');
    }

    $user = run("SELECT userID, email, first_name FROM users WHERE email = ?", 's', $email)->get_result()->fetch_assoc();
    if ($user && !rate_limited('reset:to:' . md5(strtolower($user['email'])), MAX_RESETS_PER_ADDRESS, 86400)) {
        send_password_reset($user['userID'], $user['email'], $user['first_name']);
    }
    // The same answer either way, so this form can't reveal who has an account.
    flash('If that email has an account, a reset link is on its way. It can take a minute to arrive, and may be in your spam folder.', '/forgot-password.php', 'success');
}

$pageTitle = 'Forgot password';
include __DIR__ . '/../templates/auth-top.php';
?>
<h1>Forgot your password?</h1>
<p class="muted">Enter your email and we'll send you a link to choose a new one.</p>
<form class="form" action="/forgot-password.php" method="post">
    <?= csrf_field() ?>
    <div class="field">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" maxlength="254" autocomplete="email" required autofocus>
    </div>
    <button class="btn btn--primary btn--block" type="submit">Send reset link</button>
</form>
<p class="auth__switch"><a class="link" href="/signin.php">Back to sign in</a></p>
<?php include __DIR__ . '/../templates/auth-bottom.php'; ?>
