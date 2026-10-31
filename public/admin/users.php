<?php
require_once __DIR__ . '/../../src/admin.php';
require_once __DIR__ . '/../../templates/admin-action.php';
require_admin();

const PER_PAGE = 30;

$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$onlySuspended = ($_GET['status'] ?? '') === 'suspended';
$page = max(1, (int) ($_GET['page'] ?? 1));
$pattern = like_pattern($q);
$offset = ($page - 1) * PER_PAGE;

// Deliberately no mobile number or address: moderation doesn't need them.
$users = run("
    SELECT u.userID, u.username, u.first_name, u.last_name, u.email, u.city, u.area, u.email_verified_at, u.suspended_at, u.suspended_reason,
        (SELECT COUNT(*) FROM collection c WHERE c.userID = u.userID) AS bookCount,
        (SELECT COUNT(*) FROM reports r WHERE r.target_type = 'user' AND r.target_id = u.userID) AS reportCount,
        (SELECT AVG(stars) FROM ratings WHERE ratedID = u.userID) AS ratingAverage,
        (SELECT COUNT(*) FROM ratings WHERE ratedID = u.userID) AS ratingCount
    FROM users u
    WHERE (u.username LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)" . ($onlySuspended ? ' AND u.suspended_at IS NOT NULL' : '') . "
    ORDER BY u.userID DESC
    LIMIT ? OFFSET ?
", 'ssssii', $pattern, $pattern, $pattern, $pattern, PER_PAGE + 1, $offset)->get_result()->fetch_all(MYSQLI_ASSOC);
$hasMore = count($users) > PER_PAGE;
$users = array_slice($users, 0, PER_PAGE);

$pageTitle = 'Users';
include __DIR__ . '/../../templates/admin-top.php';
?>
<div class="page-head"><h1>Users</h1></div>
<form class="admin-search" action="/admin/users.php" method="get">
    <label class="visually-hidden" for="q">Search users</label>
    <input id="q" type="search" name="q" placeholder="Name, username or email" value="<?= e($q) ?>">
    <button class="btn btn--ghost" type="submit">Search</button>
</form>
<ul class="admin-list">
    <?php foreach ($users as $user): ?>
        <li class="admin-row">
            <div class="admin-row__main">
                <strong><?= e(trim($user['first_name'] . ' ' . $user['last_name'])) ?></strong>
                <a class="link" href="/profile.php?userID=<?= (int) $user['userID'] ?>">@<?= e($user['username']) ?></a>
                <p class="muted"><?= e($user['email']) ?><?= location_label($user) !== '' ? ' · ' . e(location_label($user)) : '' ?></p>
                <p class="muted"><?= (int) $user['bookCount'] ?> books · <?= e(stars_label($user['ratingAverage'], $user['ratingCount'])) ?> · <?= (int) $user['reportCount'] ?> reports<?= $user['email_verified_at'] ? '' : ' · Email not confirmed' ?></p>
                <?php if ($user['suspended_at']): ?>
                    <p><span class="tag tag--declined">Suspended</span> <?= e($user['suspended_reason']) ?></p>
                <?php endif; ?>
            </div>
            <div class="admin-row__actions">
                <?= $user['suspended_at'] ? admin_action('unsuspend', $user['userID'], 'Lift suspension') : admin_action('suspend', $user['userID'], 'Suspend', true) ?>
            </div>
        </li>
    <?php endforeach; ?>
</ul>
<?php if (!$users): ?><p class="muted">No users found.</p><?php endif; ?>
<nav class="pagination" aria-label="Pages">
    <?php $query = fn($p) => http_build_query(['q' => $q ?: null, 'status' => $onlySuspended ? 'suspended' : null, 'page' => $p]); ?>
    <?php if ($page > 1): ?><a class="btn btn--ghost" href="?<?= e($query($page - 1)) ?>">Previous</a><?php endif; ?>
    <?php if ($hasMore): ?><a class="btn btn--ghost" href="?<?= e($query($page + 1)) ?>">Next</a><?php endif; ?>
</nav>
<?php include __DIR__ . '/../../templates/admin-bottom.php'; ?>
