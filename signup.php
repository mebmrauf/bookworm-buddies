<?php
require_once 'lib.php';

$fname = post('fname');
$lname = post('lname');
$dob = post('dob');
$email = post('email');
$username = post('username');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('signup.php');
    $password = post('password', false);
    $error = validate_user($fname, $lname, $email, $dob, $username) ?? validate_password($password);

    if (!$error) {
        try {
            run(
                "INSERT INTO users (username, password, email, first_name, last_name, date_of_birth) VALUES (?, ?, ?, ?, ?, ?)",
                'ssssss', $username, password_hash($password, PASSWORD_DEFAULT), $email, $fname, $lname, $dob
            );
            flash('Registration successful. Please sign in.', 'index.php', 'success');
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() !== DUPLICATE_KEY) {
                throw $ex;
            }
            $error = 'That email or username is already used.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./css/design.css">
    <link rel="stylesheet" href="./css/signin.css">
    <title>Sign Up - Bookworm Buddies</title>
</head>
<body class="auth-page">
    <div class="container">
        <div class="form-container">
            <?php show_flash(); ?>
            <?php if (!empty($error)): ?>
                <p class="error-message"><?= e($error) ?></p>
            <?php endif; ?>
            <h1 class="logo-s">Bookworm Buddies</h1>
            <form action="signup.php" method="post">
                <?= csrf_field() ?>
                <label for="fname">First Name:</label>
                <br>
                <input class="textbox" type="text" id="fname" name="fname" maxlength="50" value="<?= e($fname) ?>" required>
                <br>
                <label for="lname">Last Name:</label>
                <br>
                <input class="textbox" type="text" id="lname" name="lname" maxlength="50" value="<?= e($lname) ?>" required>
                <br>
                <label for="dob">Date of Birth:</label>
                <br>
                <input class="textbox" type="date" id="dob" name="dob" value="<?= e($dob) ?>" required>
                <br>
                <label for="email">Email:</label>
                <br>
                <input class="textbox" type="email" id="email" name="email" maxlength="254" value="<?= e($email) ?>" required>
                <br>
                <label for="username">Username:</label>
                <br>
                <input class="textbox" type="text" id="username" name="username" maxlength="50" value="<?= e($username) ?>" required>
                <br>
                <label for="password">Password:</label>
                <br>
                <input class="textbox" type="password" id="password" name="password" minlength="8" maxlength="72" required>
                <br>
                <button class="button" type="submit"><strong>Sign Up</strong></button>
                <br>
                <a class="anchor" href="index.php">Already have an account? Sign in</a>
            </form>
        </div>
    </div>
</body>
</html>
