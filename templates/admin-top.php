<?php
$adminLink = fn($file, $label) => '<a href="/admin/' . $file . '"' . (basename($_SERVER['SCRIPT_NAME']) === $file ? ' aria-current="page"' : '') . '>' . $label . '</a>';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= e($pageTitle) ?> - Admin</title>
    <link rel="stylesheet" href="<?= asset('app.css') ?>">
    <script src="<?= asset('app.js') ?>" defer></script>
</head>
<body>
    <div class="loading-bar" aria-hidden="true"></div>
    <header class="topbar">
        <nav class="admin-nav" aria-label="Admin">
            <?= $adminLink('index.php', '<strong>Admin</strong>') ?>
            <?= $adminLink('reports.php', 'Reports') ?>
            <?= $adminLink('users.php', 'Users') ?>
            <?= $adminLink('books.php', 'Books') ?>
            <?= $adminLink('content.php', 'Comments') ?>
            <?= $adminLink('log.php', 'Log') ?>
            <form action="/admin/action.php" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="do" value="logout">
                <button type="submit" class="btn-text">Log out</button>
            </form>
        </nav>
    </header>
    <main id="main" class="page admin">
        <?php show_flash(); ?>
