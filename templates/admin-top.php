<?php
$openReports = run("SELECT COUNT(*) FROM reports WHERE status = 'open'")->get_result()->fetch_row()[0];
$adminLink = fn($file, $label) => '<a href="/admin/' . $file . '"' . (basename($_SERVER['SCRIPT_NAME']) === $file ? ' aria-current="page"' : '') . '>' . $label . '</a>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title><?= e($pageTitle) ?> - Admin</title>
    <link rel="stylesheet" href="<?= asset('app.css') ?>">
    <script src="<?= asset('app.js') ?>" defer></script>
</head>
<body>
    <div class="loading-bar" aria-hidden="true"></div>
    <header class="topbar admin-header">
        <div class="admin-bar">
            <a class="brand" href="/admin/index.php"><?= icon('book') ?><span>Admin</span></a>
            <div class="admin-bar__end">
                <a class="link link--icon" href="/home.php" target="_blank" rel="noopener"><?= icon('external') ?>View site</a>
                <form action="/admin/action.php" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="do" value="logout">
                    <button type="submit" class="btn btn--ghost btn--small">Log out</button>
                </form>
            </div>
        </div>
        <nav class="admin-tabs" aria-label="Admin">
            <?= $adminLink('index.php', 'Dashboard') ?>
            <?= $adminLink('reports.php', 'Reports' . ($openReports ? ' <span class="badge" aria-label="' . (int) $openReports . ' open">' . (int) $openReports . '</span>' : '')) ?>
            <?= $adminLink('users.php', 'Users') ?>
            <?= $adminLink('books.php', 'Books') ?>
            <?= $adminLink('content.php', 'Comments') ?>
            <?= $adminLink('log.php', 'Log') ?>
        </nav>
    </header>
    <main id="main" class="page admin">
        <?php show_flash(); ?>
