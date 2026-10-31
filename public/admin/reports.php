<?php
require_once __DIR__ . '/../../src/admin.php';
require_once __DIR__ . '/../../templates/admin-action.php';
require_admin();

$reports = run("
    SELECT r.reportID, r.target_type, r.target_id, r.reason, r.details, r.created_at, u.username AS reporter, u.userID AS reporterID
    FROM reports r JOIN users u ON r.reporterID = u.userID
    WHERE r.status = 'open'
    ORDER BY r.reportID
    LIMIT 100
")->get_result()->fetch_all(MYSQLI_ASSOC);

// One row of what's being reported, with its link and the moderation action that fits.
function report_target($report) {
    $id = (int) $report['target_id'];
    switch ($report['target_type']) {
        case 'book':
            $row = run("SELECT title, cover_public_id, removed_at FROM books WHERE bookID = ?", 'i', $id)->get_result()->fetch_assoc();
            if (!$row) break;
            return [
                'Book: <a class="link" href="/book.php?id=' . $id . '">' . e($row['title']) . '</a>',
                ($row['cover_public_id'] ? admin_action('remove_cover', $id, 'Remove cover', true) : '') . ($row['removed_at'] ? '' : admin_action('takedown', $id, 'Take down', true)),
            ];
        case 'user':
            $row = run("SELECT username, suspended_at FROM users WHERE userID = ?", 'i', $id)->get_result()->fetch_assoc();
            if (!$row) break;
            return [
                'User: <a class="link" href="/profile.php?userID=' . $id . '">@' . e($row['username']) . '</a>',
                $row['suspended_at'] ? '' : admin_action('suspend', $id, 'Suspend', true),
            ];
        case 'feedback':
            $row = run("SELECT f.comment, f.bookID, u.userID, u.username, u.suspended_at FROM feedback f JOIN users u ON f.userID = u.userID WHERE f.feedbackID = ?", 'i', $id)->get_result()->fetch_assoc();
            if (!$row) break;
            return [
                'Feedback by <a class="link" href="/profile.php?userID=' . (int) $row['userID'] . '">@' . e($row['username']) . '</a> on <a class="link" href="/book.php?id=' . (int) $row['bookID'] . '">this book</a>: "' . e($row['comment']) . '"',
                admin_action('delete_feedback', $id, 'Delete feedback', false, 'Delete this feedback?') . ($row['suspended_at'] ? '' : admin_action('suspend', $row['userID'], 'Suspend author', true)),
            ];
        case 'rating':
            $row = run("SELECT r.stars, r.comment, u.userID, u.username, u.suspended_at FROM ratings r JOIN users u ON r.raterID = u.userID WHERE r.ratingID = ?", 'i', $id)->get_result()->fetch_assoc();
            if (!$row) break;
            return [
                'Rating by <a class="link" href="/profile.php?userID=' . (int) $row['userID'] . '">@' . e($row['username']) . '</a>: ' . (int) $row['stars'] . ' stars "' . e($row['comment']) . '"',
                admin_action('delete_rating', $id, 'Delete rating', false, 'Delete this rating?') . ($row['suspended_at'] ? '' : admin_action('suspend', $row['userID'], 'Suspend author', true)),
            ];
    }
    return ['This item has since been removed.', ''];
}

$pageTitle = 'Reports';
include __DIR__ . '/../../templates/admin-top.php';
?>
<div class="page-head"><h1>Reports</h1></div>
<?php if (!$reports): ?>
    <div class="empty"><h2>No open reports</h2><p class="muted">Nothing needs your attention.</p></div>
<?php endif; ?>
<ul class="admin-list">
    <?php foreach ($reports as $report):
        [$summary, $actions] = report_target($report); ?>
        <li class="admin-row">
            <div class="admin-row__main">
                <p><span class="tag tag--pending"><?= e($report['reason']) ?></span></p>
                <p><?= $summary ?></p>
                <?php if ($report['details']): ?><blockquote class="swap__note"><?= e($report['details']) ?></blockquote><?php endif; ?>
                <p class="muted">Reported by <a class="link" href="/profile.php?userID=<?= (int) $report['reporterID'] ?>">@<?= e($report['reporter']) ?></a> on <?= e($report['created_at']) ?></p>
            </div>
            <div class="admin-row__actions">
                <?= $actions ?>
                <?= admin_action('resolve_report', $report['reportID'], 'Close report') ?>
            </div>
        </li>
    <?php endforeach; ?>
</ul>
<?php include __DIR__ . '/../../templates/admin-bottom.php'; ?>
