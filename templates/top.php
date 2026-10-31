<?php
$me = run("
    SELECT first_name, last_name, avatar_public_id,
        (SELECT COUNT(*) FROM swap WHERE ownerID = users.userID AND status = 'pending') AS pending
    FROM users WHERE userID = ?
", 'i', $userID)->get_result()->fetch_assoc();
$current = fn($page) => ($navActive ?? '') === $page ? ' aria-current="page"' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - Bookworm Buddies</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Source+Serif+4:opsz,wght@8..60,500;8..60,600&display=swap">
    <link rel="stylesheet" href="/assets/app.css">
    <script src="/assets/app.js" defer></script>
</head>
<body>
    <a class="skip-link" href="#main">Skip to content</a>
    <header class="topbar">
        <div class="topbar__inner">
            <a class="brand" href="/home.php"><?= icon('book') ?><span>Bookworm Buddies</span></a>
            <form class="search" action="/search.php" method="get" role="search">
                <label class="visually-hidden" for="site-search">Search books and people</label>
                <?= icon('search') ?>
                <input id="site-search" type="search" name="q" placeholder="Search books and people" value="<?= e($searchValue ?? '') ?>" required>
            </form>
            <nav class="nav" aria-label="Main">
                <a href="/home.php"<?= $current('browse') ?>><?= icon('compass') ?><span>Browse</span></a>
                <a href="/requests.php"<?= $current('requests') ?>><?= icon('inbox') ?><span>Requests</span><?php if ($me['pending'] > 0): ?><span class="badge" aria-label="<?= (int) $me['pending'] ?> waiting for you"><?= (int) $me['pending'] ?></span><?php endif; ?></a>
                <a href="/profile.php"<?= $current('library') ?>><?= icon('user') ?><span>My books</span></a>
                <a class="nav__add" href="/add-book.php"<?= $current('add') ?>><?= icon('plus') ?><span>Add book</span></a>
            </nav>
            <details class="menu">
                <summary aria-label="Account menu"><?= avatar($me, 40) ?></summary>
                <div class="menu__panel">
                    <p class="menu__name"><?= e($me['first_name'] . ' ' . $me['last_name']) ?></p>
                    <a href="/settings.php"><?= icon('settings') ?>Settings</a>
                    <form action="/actions/logout.php" method="post">
                        <?= csrf_field() ?>
                        <button type="submit"><?= icon('logout') ?>Log out</button>
                    </form>
                </div>
            </details>
        </div>
    </header>
    <main id="main" class="page">
        <?php show_flash(); ?>
