<?php
require_once __DIR__ . '/../src/bootstrap.php';

$token = $_POST['token'] ?? $_GET['token'] ?? '';
$token = is_string($token) ? $token : '';

$user = run(
    "SELECT userID, pending_email FROM users WHERE verify_token_hash = ? AND verify_expires_at > NOW()",
    's', hash('sha256', $token)
)->get_result()->fetch_assoc();

// Confirming takes a button press: email scanners open links, and must not confirm anything by doing so.
if ($user && $_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/verify.php?token=' . urlencode($token));
    try {
        run("
            UPDATE users
            SET email = COALESCE(pending_email, email), pending_email = NULL, email_verified_at = COALESCE(email_verified_at, NOW()),
                verify_token_hash = NULL, verify_expires_at = NULL
            WHERE userID = ?
        ", 'i', $user['userID']);
    } catch (mysqli_sql_exception $ex) {
        if ($ex->getCode() !== DUPLICATE_KEY) {
            throw $ex;
        }
        run("UPDATE users SET pending_email = NULL, verify_token_hash = NULL, verify_expires_at = NULL WHERE userID = ?", 'i', $user['userID']);
        flash('That email is now used by another account.', '/settings.php');
    }
    unset($_SESSION['verify_email']);
    if ($userID) {
        flash('Your email is confirmed.', '/settings.php', 'success');
    }
    flash('Your email is confirmed. You can sign in now.', '/signin.php', 'success');
}

$pageTitle = 'Confirm your email';
include __DIR__ . '/../templates/auth-top.php';
?>
<?php if ($user): ?>
    <h1>Confirm your email</h1>
    <p class="muted">One last step to finish.</p>
    <form class="form" action="/verify.php" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <button class="btn btn--primary btn--block" type="submit">Confirm my email</button>
    </form>
<?php else: ?>
    <h1>This link doesn't work</h1>
    <p class="muted">It may have expired or already been used. If you've already confirmed, just sign in.</p>
    <p class="auth__switch"><a class="btn btn--primary btn--block" href="/verify-email.php">Get a new link</a></p>
    <p class="auth__switch"><a class="link" href="/signin.php">Sign in</a></p>
<?php endif; ?>
<?php include __DIR__ . '/../templates/auth-bottom.php'; ?>
