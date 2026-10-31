<?php
require_once __DIR__ . '/../../src/admin.php';
require_admin();

$entries = run("SELECT action, target_type, target_id, detail, created_at FROM admin_log ORDER BY logID DESC LIMIT 200")->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Log';
include __DIR__ . '/../../templates/admin-top.php';
?>
<div class="page-head"><h1>Log</h1></div>
<p class="muted">The latest 200 admin actions.</p>
<ul class="admin-list section">
    <?php foreach ($entries as $entry): ?>
        <li class="admin-row">
            <div class="admin-row__main">
                <strong><?= e(str_replace('_', ' ', $entry['action'])) ?></strong>
                <?php if ($entry['target_type']): ?><span class="muted"><?= e($entry['target_type']) ?> #<?= (int) $entry['target_id'] ?></span><?php endif; ?>
                <?php if ($entry['detail']): ?><p><?= e($entry['detail']) ?></p><?php endif; ?>
            </div>
            <span class="muted"><?= e($entry['created_at']) ?> UTC</span>
        </li>
    <?php endforeach; ?>
</ul>
<?php include __DIR__ . '/../../templates/admin-bottom.php'; ?>
