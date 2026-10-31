<?php
require_once 'auth.php';

$user = run("SELECT first_name, last_name, email, date_of_birth, username, password FROM users WHERE userID = ?", 'i', $userID)->get_result()->fetch_assoc();
$currentHash = $user['password'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('updateprofile.php');
    $user = ['first_name' => post('first_name'), 'last_name' => post('last_name'), 'email' => post('email'), 'date_of_birth' => post('date_of_birth'), 'username' => post('username')];
    $newPassword = post('new_password', false);

    $error = validate_user($user['first_name'], $user['last_name'], $user['email'], $user['date_of_birth'], $user['username']);
    if (!$error && $newPassword !== '') {
        $error = validate_password($newPassword);
        if (!$error && !password_verify(post('current_password', false), $currentHash)) {
            $error = 'Your current password is incorrect.';
        }
    }

    if (!$error) {
        $hash = $newPassword === '' ? $currentHash : password_hash($newPassword, PASSWORD_DEFAULT);
        try {
            run(
                "UPDATE users SET first_name = ?, last_name = ?, email = ?, date_of_birth = ?, username = ?, password = ? WHERE userID = ?",
                'ssssssi', $user['first_name'], $user['last_name'], $user['email'], $user['date_of_birth'], $user['username'], $hash, $userID
            );
            flash('Profile updated.', 'profile.php', 'success');
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() !== DUPLICATE_KEY) {
                throw $ex;
            }
            $error = 'That email or username is already used.';
        }
    }
}

$pageTitle = 'Update Profile';
include 'header.php';
?>
<section class="container">
    <h1>Update Profile Information</h1>
    <?php if (!empty($error)): ?>
        <p class="error-message"><?= e($error) ?></p>
    <?php endif; ?>
    <form class="form-container" action="updateprofile.php" method="POST">
        <?= csrf_field() ?>
        <label for="first_name">First Name:</label>
        <input class="textbox" type="text" id="first_name" name="first_name" maxlength="50" value="<?= e($user['first_name']) ?>" required><br>

        <label for="last_name">Last Name:</label>
        <input class="textbox" type="text" id="last_name" name="last_name" maxlength="50" value="<?= e($user['last_name']) ?>" required><br>

        <label for="email">Email:</label>
        <input class="textbox" type="email" id="email" name="email" maxlength="254" value="<?= e($user['email']) ?>" required><br>

        <label for="date_of_birth">Date of Birth:</label>
        <input class="textbox" type="date" id="date_of_birth" name="date_of_birth" value="<?= e($user['date_of_birth']) ?>" required><br>

        <label for="username">Username:</label>
        <input class="textbox" type="text" id="username" name="username" maxlength="50" value="<?= e($user['username']) ?>" required><br>

        <label for="new_password">New Password (leave blank to keep the current one):</label>
        <input class="textbox" type="password" id="new_password" name="new_password" minlength="8" maxlength="72" autocomplete="new-password"><br>

        <label for="current_password">Current Password (only needed to change it):</label>
        <input class="textbox" type="password" id="current_password" name="current_password" autocomplete="current-password"><br>

        <button type="submit" class="button">Update</button>
    </form>

    <button onclick="window.location.href='profile.php'" class="button">Back to Profile</button>
</section>
<?php include 'footer.php'; ?>
