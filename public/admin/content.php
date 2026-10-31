<?php
require_once __DIR__ . '/../../src/admin.php';
require_once __DIR__ . '/../../templates/admin-action.php';
require_admin();

const SHOW_LATEST = 50;

$feedback = run("
    SELECT f.feedbackID, f.comment, f.reply, f.bookID, b.title, u.userID, u.username
    FROM feedback f JOIN users u ON f.userID = u.userID JOIN books b ON f.bookID = b.bookID
    ORDER BY f.feedbackID DESC LIMIT " . SHOW_LATEST
)->get_result()->fetch_all(MYSQLI_ASSOC);

$ratings = run("
    SELECT r.ratingID, r.stars, r.comment, rater.userID AS raterID, rater.username AS rater, rated.userID AS ratedID, rated.username AS rated
    FROM ratings r JOIN users rater ON r.raterID = rater.userID JOIN users rated ON r.ratedID = rated.userID
    ORDER BY r.ratingID DESC LIMIT " . SHOW_LATEST
)->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Comments';
include __DIR__ . '/../../templates/admin-top.php';
?>
<div class="page-head"><h1>Comments</h1></div>
<section class="section">
    <h2>Latest ratings</h2>
    <?php if (!$ratings): ?><p class="muted">No ratings yet.</p><?php endif; ?>
    <ul class="admin-list">
        <?php foreach ($ratings as $rating): ?>
            <li class="admin-row">
                <div class="admin-row__main">
                    <p><strong class="rating-summary"><?= (int) $rating['stars'] ?> ★</strong> from <a class="link" href="/profile.php?userID=<?= (int) $rating['raterID'] ?>">@<?= e($rating['rater']) ?></a> to <a class="link" href="/profile.php?userID=<?= (int) $rating['ratedID'] ?>">@<?= e($rating['rated']) ?></a></p>
                    <?php if ($rating['comment']): ?><p><?= e($rating['comment']) ?></p><?php endif; ?>
                </div>
                <div class="admin-row__actions"><?= admin_action('delete_rating', $rating['ratingID'], 'Delete', false, 'Delete this rating?') ?></div>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<section class="section">
    <h2>Latest feedback</h2>
    <ul class="admin-list">
        <?php foreach ($feedback as $item): ?>
            <li class="admin-row">
                <div class="admin-row__main">
                    <p><a class="link" href="/profile.php?userID=<?= (int) $item['userID'] ?>">@<?= e($item['username']) ?></a> on <a class="link" href="/book.php?id=<?= (int) $item['bookID'] ?>"><?= e($item['title']) ?></a></p>
                    <p><?= e($item['comment']) ?></p>
                    <?php if ($item['reply']): ?><p class="muted">Owner replied: <?= e($item['reply']) ?></p><?php endif; ?>
                </div>
                <div class="admin-row__actions"><?= admin_action('delete_feedback', $item['feedbackID'], 'Delete', false, 'Delete this feedback?') ?></div>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
<?php include __DIR__ . '/../../templates/admin-bottom.php'; ?>
