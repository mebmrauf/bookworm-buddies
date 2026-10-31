<?php
require_once __DIR__ . '/../src/bootstrap.php';

$fname = post('fname');
$lname = post('lname');
$dob = post('dob');
$email = post('email');
$username = post('username');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/signup.php');
    $password = post('password', false);
    $error = validate_user($fname, $lname, $email, $dob, $username) ?? validate_password($password);

    if (!$error) {
        try {
            run(
                "INSERT INTO users (username, password, email, first_name, last_name, date_of_birth) VALUES (?, ?, ?, ?, ?, ?)",
                'ssssss', $username, password_hash($password, PASSWORD_DEFAULT), $email, $fname, $lname, $dob
            );
            flash('Registration successful. Please sign in.', '/index.php', 'success');
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() !== DUPLICATE_KEY) {
                throw $ex;
            }
            $error = 'That email or username is already used.';
        }
    }
}

$pageTitle = 'Create account';
include __DIR__ . '/../templates/auth-top.php';
?>
<h1>Create your account</h1>
<p class="muted">It's free, and only takes a minute.</p>
<?php form_error($error ?? null); ?>
<form class="form" action="/signup.php" method="post">
    <?= csrf_field() ?>
    <div class="form__row">
        <div class="field">
            <label for="fname">First name</label>
            <input id="fname" type="text" name="fname" maxlength="50" autocomplete="given-name" value="<?= e($fname) ?>" required>
        </div>
        <div class="field">
            <label for="lname">Last name</label>
            <input id="lname" type="text" name="lname" maxlength="50" autocomplete="family-name" value="<?= e($lname) ?>" required>
        </div>
    </div>
    <div class="field">
        <label for="dob">Date of birth</label>
        <input id="dob" type="date" name="dob" autocomplete="bday" value="<?= e($dob) ?>" required>
    </div>
    <div class="field">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" maxlength="254" autocomplete="email" value="<?= e($email) ?>" required>
    </div>
    <div class="field">
        <label for="username">Username</label>
        <input id="username" type="text" name="username" maxlength="50" autocomplete="username" value="<?= e($username) ?>" aria-describedby="username-help" required>
        <p class="help" id="username-help">Letters, numbers, dots, dashes or underscores.</p>
    </div>
    <div class="field">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" aria-describedby="password-help" required>
        <p class="help" id="password-help">At least 8 characters.</p>
    </div>
    <button class="btn btn--primary btn--block" type="submit">Create account</button>
</form>
<p class="auth__switch">Already have an account? <a class="link" href="/index.php">Sign in</a></p>
<?php include __DIR__ . '/../templates/auth-bottom.php'; ?>
