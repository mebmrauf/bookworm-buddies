<?php
require_once __DIR__ . '/../src/bootstrap.php';

if ($userID) {
    go('/home.php');
}

// Readable, and unique: a short number is added when the plain name is taken.
function generate_username($firstName, $lastName) {
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', trim("$firstName $lastName")) ?: '';
    $base = substr(trim(preg_replace('/[^a-z0-9]+/', '.', strtolower($ascii)), '.'), 0, 40);
    if (strlen($base) < 3) {
        $base = 'reader';
    }
    $candidate = $base;
    while (run("SELECT 1 FROM users WHERE username = ?", 's', $candidate)->get_result()->num_rows) {
        $candidate = $base . random_int(100, 999999);
    }
    return $candidate;
}

$name = post('name');
$email = post('email');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/signup.php');
    $password = post('password', false);
    $parts = preg_split('/\s+/', $name, 2);
    $firstName = $parts[0] ?? '';
    $lastName = $parts[1] ?? '';
    $error = validate_name($firstName, $lastName) ?? validate_email($email) ?? validate_password($password);

    if (!$error) {
        // An account nobody confirmed in time must not block the real owner of that email.
        run("DELETE FROM users WHERE email = ? AND email_verified_at IS NULL AND verify_expires_at < NOW()", 's', $email);

        $newUser = false;
        try {
            run(
                "INSERT INTO users (username, password, email, first_name, last_name) VALUES (?, ?, ?, ?, ?)",
                'sssss', generate_username($firstName, $lastName), password_hash($password, PASSWORD_DEFAULT), $email, $firstName, $lastName
            );
            $newUser = true;
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() !== DUPLICATE_KEY) {
                throw $ex;
            }
        }

        // Everyone sees the same next page, so this form can't reveal who has an account.
        $sent = $newUser ? send_verification($conn->insert_id, $email, $firstName) : true;
        $_SESSION['verify_email'] = $email;
        if ($sent) {
            flash("We sent a confirmation link to $email.", '/verify-email.php', 'success');
        }
        flash('We could not send your confirmation email. Please try again in a minute.', '/verify-email.php');
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
    <div class="field">
        <label for="name">Name</label>
        <input id="name" type="text" name="name" maxlength="101" autocomplete="name" value="<?= e($name) ?>" required autofocus>
    </div>
    <div class="field">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" maxlength="254" autocomplete="email" value="<?= e($email) ?>" aria-describedby="email-help" required>
        <p class="help" id="email-help">We'll send a link to confirm it.</p>
    </div>
    <div class="field">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" aria-describedby="password-help" required>
        <p class="help" id="password-help">At least 8 characters.</p>
    </div>
    <button class="btn btn--primary btn--block" type="submit">Create account</button>
</form>
<p class="auth__switch">Already have an account? <a class="link" href="/signin.php">Sign in</a></p>
<?php include __DIR__ . '/../templates/auth-bottom.php'; ?>
