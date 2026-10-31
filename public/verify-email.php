<?php
require_once __DIR__ . '/../src/bootstrap.php';

const MAX_REQUESTS_PER_HOUR = 15;

$email = $_SESSION['verify_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/verify-email.php');
    $email = post('email');
    $_SESSION['verify_email'] = $email;

    if (validate_email($email)) {
        flash('Please enter a valid email address.', '/verify-email.php');
    }
    if (rate_limited('verify:ip:' . $_SERVER['REMOTE_ADDR'], MAX_REQUESTS_PER_HOUR, 3600)) {
        flash('Too many requests from this connection. Please try again in an hour.', '/verify-email.php');
    }

    $user = run("
        SELECT userID, first_name FROM users
        WHERE email = ? AND email_verified_at IS NULL
            AND (verify_sent_at IS NULL OR verify_sent_at < NOW() - INTERVAL " . RESEND_WAIT_SECONDS . " SECOND)
    ", 's', $email)->get_result()->fetch_assoc();

    if ($user) {
        send_verification($user['userID'], $email, $user['first_name']);
    }
    // The same answer either way, so this form can't reveal who has an account.
    flash('If that email needs confirming, a new link is on its way. Links can take a minute to arrive.', '/verify-email.php', 'success');
}

$pageTitle = 'Confirm your email';
include __DIR__ . '/../templates/auth-top.php';
?>
<h1>Check your email</h1>
<p class="muted">Open the link we sent to finish creating your account. It may be in your spam folder.</p>
<form class="form" action="/verify-email.php" method="post">
    <?= csrf_field() ?>
    <div class="field">
        <label for="email">Didn't get it? Send a new link to:</label>
        <input id="email" type="email" name="email" maxlength="254" autocomplete="email" value="<?= e($email) ?>" required>
    </div>
    <button class="btn btn--ghost btn--block" type="submit">Send a new link</button>
</form>
<p class="auth__switch"><a class="link" href="/signin.php">Back to sign in</a></p>
<?php include __DIR__ . '/../templates/auth-bottom.php'; ?>
