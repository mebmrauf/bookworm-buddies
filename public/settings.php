<?php
require_once __DIR__ . '/../src/auth.php';

$user = run("SELECT first_name, last_name, email, date_of_birth, username, avatar_public_id, password FROM users WHERE userID = ?", 'i', $userID)->get_result()->fetch_assoc();
$currentHash = $user['password'];
$oldAvatar = $user['avatar_public_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/settings.php');
    $user = ['first_name' => post('first_name'), 'last_name' => post('last_name'), 'email' => post('email'), 'date_of_birth' => post('date_of_birth'), 'username' => post('username')] + $user;
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
            $avatar = upload_image_field('avatar', 'avatars') ?? (post('remove_avatar') === '1' ? null : $oldAvatar);
            run(
                "UPDATE users SET first_name = ?, last_name = ?, email = ?, date_of_birth = ?, username = ?, password = ?, avatar_public_id = ? WHERE userID = ?",
                'sssssssi', $user['first_name'], $user['last_name'], $user['email'], $user['date_of_birth'], $user['username'], $hash, $avatar, $userID
            );
            if ($avatar !== $oldAvatar) {
                delete_images([$oldAvatar]);
            }
            flash('Profile updated.', '/profile.php', 'success');
        } catch (ImageError $ex) {
            $error = $ex->getMessage();
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() !== DUPLICATE_KEY) {
                throw $ex;
            }
            $error = 'That email or username is already used.';
        }
    }
}

$pageTitle = 'Settings';
include __DIR__ . '/../templates/top.php';
?>
<div class="narrow">
    <h1>Settings</h1>
    <?php form_error($error ?? null); ?>
    <form class="form" action="/settings.php" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <fieldset class="panel">
            <legend>Profile</legend>
            <?php if (cloudinary_enabled()): ?>
                <div class="field">
                    <label for="avatar">Profile photo <span class="optional">(optional)</span></label>
                    <div class="avatar-field">
                        <?= avatar(['avatar_public_id' => $oldAvatar] + $user, 80) ?>
                        <img class="preview preview--round" data-preview="avatar" alt="Selected photo preview" hidden>
                        <input id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" data-image-input="avatar" aria-describedby="avatar-help">
                    </div>
                    <p class="help" id="avatar-help">JPG, PNG or WebP, up to 5 MB.</p>
                    <?php if ($oldAvatar): ?>
                        <label class="check"><input type="checkbox" name="remove_avatar" value="1"> Remove this photo</label>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="form__row">
                <div class="field">
                    <label for="first_name">First name</label>
                    <input id="first_name" type="text" name="first_name" maxlength="50" autocomplete="given-name" value="<?= e($user['first_name']) ?>" required>
                </div>
                <div class="field">
                    <label for="last_name">Last name</label>
                    <input id="last_name" type="text" name="last_name" maxlength="50" autocomplete="family-name" value="<?= e($user['last_name']) ?>" required>
                </div>
            </div>
            <div class="field">
                <label for="username">Username</label>
                <input id="username" type="text" name="username" maxlength="50" autocomplete="username" value="<?= e($user['username']) ?>" required>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" maxlength="254" autocomplete="email" value="<?= e($user['email']) ?>" aria-describedby="email-help" required>
                <p class="help" id="email-help">Only shared with someone when you're both in an accepted swap.</p>
            </div>
            <div class="field">
                <label for="date_of_birth">Date of birth</label>
                <input id="date_of_birth" type="date" name="date_of_birth" autocomplete="bday" value="<?= e($user['date_of_birth']) ?>" required>
            </div>
        </fieldset>

        <fieldset class="panel">
            <legend>Change password</legend>
            <p class="help">Leave these blank to keep your current password.</p>
            <div class="field">
                <label for="current_password">Current password</label>
                <input id="current_password" type="password" name="current_password" autocomplete="current-password">
            </div>
            <div class="field">
                <label for="new_password">New password</label>
                <input id="new_password" type="password" name="new_password" minlength="8" maxlength="72" autocomplete="new-password">
            </div>
        </fieldset>

        <div class="form__actions">
            <button type="submit" class="btn btn--primary">Save changes</button>
            <a class="btn btn--ghost" href="/profile.php">Cancel</a>
        </div>
    </form>

    <section class="panel panel--danger">
        <h2>Delete account</h2>
        <p class="muted">This permanently removes your account, your books and your requests.</p>
        <form action="/actions/account-delete.php" method="post" data-confirm="Delete your account and all your books? This cannot be undone.">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn--danger">Delete my account</button>
        </form>
    </section>
</div>
<?php include __DIR__ . '/../templates/bottom.php'; ?>
