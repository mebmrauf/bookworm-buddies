<?php
require_once __DIR__ . '/../../src/admin.php';
require_admin();

$count = fn($sql) => run($sql)->get_result()->fetch_row()[0];
$stats = [
    ['Open reports', $count("SELECT COUNT(*) FROM reports WHERE status = 'open'"), 'reports.php'],
    ['Users', $count("SELECT COUNT(*) FROM users"), 'users.php'],
    ['Suspended', $count("SELECT COUNT(*) FROM users WHERE suspended_at IS NOT NULL"), 'users.php?status=suspended'],
    ['Books', $count("SELECT COUNT(*) FROM books"), 'books.php'],
    ['Taken down', $count("SELECT COUNT(*) FROM books WHERE removed_at IS NOT NULL"), 'books.php?status=removed'],
    ['Open requests', $count("SELECT COUNT(*) FROM swap WHERE status = 'pending'"), null],
    ['Books out now', $count("SELECT COUNT(*) FROM swap WHERE status = 'accepted'"), null],
    ['Overdue', $count("SELECT COUNT(*) FROM swap WHERE status = 'accepted' AND kind = 'lend' AND due_date < CURDATE()"), null],
];

$pageTitle = 'Dashboard';
include __DIR__ . '/../../templates/admin-top.php';
?>
<div class="page-head"><h1>Dashboard</h1></div>
<div class="stat-grid">
    <?php foreach ($stats as [$label, $value, $link]): ?>
        <div class="stat">
            <strong><?= (int) $value ?></strong>
            <?php if ($link): ?><a class="link" href="/admin/<?= $link ?>"><?= e($label) ?></a><?php else: ?><span class="muted"><?= e($label) ?></span><?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
<section class="section">
    <h2>Return reminders</h2>
    <p class="muted">Emails borrowers whose books are due soon or overdue. Each reminder only goes out once, so this is safe to press at any time.</p>
    <form action="/admin/action.php" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="do" value="reminders">
        <p><button type="submit" class="btn btn--ghost">Send reminders now</button></p>
    </form>
</section>
<?php include __DIR__ . '/../../templates/admin-bottom.php'; ?>
