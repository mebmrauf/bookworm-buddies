<?php
require_once __DIR__ . '/../src/bootstrap.php';

if ($userID) {
    go('/home.php');
}

$next = $_GET['next'] ?? $_SESSION['next'] ?? '/home.php';
unset($_SESSION['next']);

$pageTitle = 'Sign in';
include __DIR__ . '/../templates/auth-top.php';
?>
<h1>Welcome back</h1>
<p class="muted">Sign in to find your next read.</p>
<form class="form" action="/actions/signin.php" method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="next" value="<?= e(safe_next($next)) ?>">
    <div class="field">
        <label for="login">Email or username</label>
        <input id="login" type="text" name="login" autocomplete="username" required autofocus>
    </div>
    <div class="field">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="current-password" required>
    </div>
    <button class="btn btn--primary btn--block" type="submit">Sign in</button>
</form>
<p class="auth__switch">New here? <a class="link" href="/signup.php">Create an account</a></p>
<?php include __DIR__ . '/../templates/auth-bottom.php'; ?>
