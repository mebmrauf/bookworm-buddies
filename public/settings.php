<?php
require_once __DIR__ . '/../src/auth.php';

$user = run("SELECT first_name, last_name, email, pending_email, username, city, area, date_of_birth, gender, mobile, address, avatar_public_id, password FROM users WHERE userID = ?", 'i', $userID)->get_result()->fetch_assoc();
$currentHash = $user['password'];
$currentEmail = $user['email'];
$oldAvatar = $user['avatar_public_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/settings.php');
    $user = [
        'first_name' => post('first_name'), 'last_name' => post('last_name'), 'username' => post('username'), 'email' => post('email'), 'city' => post('city'), 'area' => post('area'),
        'date_of_birth' => post('date_of_birth'), 'gender' => post('gender'), 'mobile' => post('mobile'), 'address' => post('address'),
    ] + $user;
    $newPassword = post('new_password', false);
    $emailChanged = strcasecmp($user['email'], $currentEmail) !== 0;

    $error = validate_name($user['first_name'], $user['last_name'])
        ?? validate_username($user['username'])
        ?? validate_email($user['email'])
        ?? validate_location($user['city'], $user['area'])
        ?? validate_details($user['date_of_birth'], $user['gender'], $user['mobile'], $user['address']);

    if (!$error && $newPassword !== '') {
        $error = validate_password($newPassword);
        if (!$error && !password_verify(post('current_password', false), $currentHash)) {
            $error = 'Your current password is incorrect.';
        }
    }
    if (!$error && $emailChanged && run("SELECT 1 FROM users WHERE email = ?", 's', $user['email'])->get_result()->num_rows) {
        $error = 'That email is already used.';
    }

    if (!$error) {
        $hash = $newPassword === '' ? $currentHash : password_hash($newPassword, PASSWORD_DEFAULT);
        // A new address only takes over once confirmed, so a typo can't lock anyone out.
        $pendingEmail = $emailChanged ? $user['email'] : $user['pending_email'];
        $optional = fn($value) => $value === '' ? null : $value;
        try {
            $avatar = upload_image_field('avatar', 'avatars') ?? (post('remove_avatar') === '1' ? null : $oldAvatar);
            run(
                "UPDATE users SET first_name = ?, last_name = ?, username = ?, pending_email = ?, city = ?, area = ?, date_of_birth = ?, gender = ?, mobile = ?, address = ?, password = ?, avatar_public_id = ? WHERE userID = ?",
                'ssssssssssssi', $user['first_name'], $user['last_name'], $user['username'], $pendingEmail, $optional($user['city']), $optional($user['area']),
                $optional($user['date_of_birth']), $optional($user['gender']), $optional($user['mobile']), $optional($user['address']), $hash, $avatar, $userID
            );
            if ($avatar !== $oldAvatar) {
                delete_images([$oldAvatar]);
            }
            if ($emailChanged) {
                if (send_verification($userID, $user['email'], $user['first_name'])) {
                    flash("Saved. Open the link we sent to {$user['email']} to finish changing your email.", '/profile.php', 'success');
                }
                flash('Your profile was saved, but we could not send the email confirmation. Please try again in a minute.', '/settings.php');
            }
            flash('Profile updated.', '/profile.php', 'success');
        } catch (ImageError $ex) {
            $error = $ex->getMessage();
        } catch (mysqli_sql_exception $ex) {
            if ($ex->getCode() !== DUPLICATE_KEY) {
                throw $ex;
            }
            $error = 'That username is already taken. Please choose another.';
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
            <legend>Account</legend>
            <?php if (cloudinary_enabled()): ?>
                <div class="field">
                    <label for="avatar">Profile photo <span class="optional">(optional)</span></label>
                    <?= upload_box('avatar', 'Selected photo', avatar(['avatar_public_id' => $oldAvatar] + $user, 80), true, (bool) $oldAvatar) ?>
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
                    <label for="last_name">Last name <span class="optional">(optional)</span></label>
                    <input id="last_name" type="text" name="last_name" maxlength="50" autocomplete="family-name" value="<?= e($user['last_name']) ?>">
                </div>
            </div>
            <div class="field">
                <label for="username">Username</label>
                <input id="username" type="text" name="username" maxlength="50" autocomplete="username" value="<?= e($user['username']) ?>" aria-describedby="username-help" required>
                <p class="help" id="username-help">Shown on your books and feedback. Must be unique: 3-50 letters, numbers, dots, dashes or underscores.</p>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" type="email" name="email" maxlength="254" autocomplete="email" value="<?= e($user['email']) ?>" aria-describedby="email-help" required>
                <p class="help" id="email-help">Never shown to other members. If you change it, we'll email a link to confirm the new address.</p>
                <?php if ($user['pending_email']): ?>
                    <p class="help">Waiting for you to confirm <?= e($user['pending_email']) ?>.</p>
                <?php endif; ?>
            </div>
        </fieldset>

        <fieldset class="panel">
            <legend>Your location</legend>
            <p class="help">Shown on your profile and books, so people nearby can find them. Never your full address.</p>
            <div class="form__row">
                <div class="field">
                    <label for="city">City</label>
                    <select id="city" name="city">
                        <option value="">Not set</option>
                        <?php foreach (CITIES as $option): ?>
                            <option value="<?= e($option) ?>"<?= $user['city'] === $option ? ' selected' : '' ?>><?= e($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="area">Area <span class="optional">(optional)</span></label>
                    <input id="area" type="text" name="area" maxlength="60" placeholder="For example: Dhanmondi" value="<?= e($user['area']) ?>">
                </div>
            </div>
        </fieldset>

        <fieldset class="panel">
            <legend>Personal details</legend>
            <p class="help">All optional, and other members can't see them. Your delivery address is the one exception: it goes to a book's owner only when they accept your courier request.</p>
            <div class="form__row">
                <div class="field">
                    <label for="date_of_birth">Date of birth</label>
                    <input id="date_of_birth" type="date" name="date_of_birth" autocomplete="bday" value="<?= e($user['date_of_birth']) ?>">
                </div>
                <div class="field">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender">
                        <option value="">Not set</option>
                        <?php foreach (GENDERS as $option): ?>
                            <option value="<?= e($option) ?>"<?= $user['gender'] === $option ? ' selected' : '' ?>><?= e($option) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="field">
                <label for="mobile">Mobile number</label>
                <input id="mobile" type="tel" name="mobile" maxlength="20" autocomplete="tel" value="<?= e($user['mobile']) ?>">
            </div>
            <div class="field">
                <label for="address">Delivery address</label>
                <textarea id="address" name="address" rows="2" maxlength="255" autocomplete="street-address"><?= e($user['address']) ?></textarea>
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
