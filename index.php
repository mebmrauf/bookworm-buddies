<?php
require_once 'lib.php';

if (!empty($_SESSION['user_id'])) {
    go('home.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./css/design.css">
    <link rel="stylesheet" href="./css/signin.css">
    <title>Bookworm Buddies</title>
</head>
<body class="auth-page">
    <div class="container">
        <div class="form-container">
            <?php show_flash(); ?>
            <h1 class="logo-s">Bookworm Buddies</h1>
            <form action="signin.php" method="post">
                <?= csrf_field() ?>
                <label for="username">Username:</label>
                <br>
                <input class="textbox" type="text" id="username" name="username" required>
                <br>
                <label for="password">Password:</label>
                <br>
                <input class="textbox" type="password" id="password" name="password" required>
                <br>
                <button class="button"><strong>Sign In</strong></button>
                <br>
                <a class="anchor" href="signup.php">Don't have an account? Sign up here!</a>
            </form>
        </div>
    </div>
</body>
</html>
