<?php
$me = current_user();
// The account was deleted in another browser.
if ($userID && !$me) {
    session_unset();
    $userID = 0;
}
$current = fn($page) => ($navActive ?? '') === $page ? ' aria-current="page"' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - Bookworm Buddies</title>
    <?php if (isset($pageDescription)): ?><meta name="description" content="<?= e($pageDescription) ?>"><?php endif; ?>
    <link rel="preload" href="/assets/fonts/inter.woff2" as="font" type="font/woff2" crossorigin>
    <script type="speculationrules">{"prefetch":[{"where":{"and":[{"href_matches":"/*"},{"not":{"href_matches":"/actions/*"}},{"not":{"href_matches":"/verify*"}}]},"eagerness":"moderate"}]}</script>
    <link rel="stylesheet" href="<?= asset('app.css') ?>">
    <script src="<?= asset('app.js') ?>" defer></script>
</head>
<body>
    <div class="loading-bar" aria-hidden="true"></div>
    <a class="skip-link" href="#main">Skip to content</a>
    <header class="topbar">
        <div class="topbar__inner">
            <a class="brand" href="<?= $me ? '/home.php' : '/' ?>"><?= icon('book') ?><span>Bookworm Buddies</span></a>
            <form class="search" action="/search.php" method="get" role="search">
                <label class="visually-hidden" for="site-search">Search books</label>
                <?= icon('search') ?>
                <input id="site-search" type="search" name="q" placeholder="<?= $me ? 'Search books and people' : 'Search books' ?>" value="<?= e($searchValue ?? '') ?>" required
                    autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="search-results">
                <div id="search-results" class="search__results" role="listbox" aria-label="Search results" hidden></div>
            </form>
            <nav class="nav" aria-label="Main">
                <a href="/home.php"<?= $current('browse') ?>><?= icon('compass') ?><span>Browse</span></a>
                <?php if ($me): ?>
                    <a href="/requests.php"<?= $current('requests') ?>><?= icon('inbox') ?><span>Requests</span><?php if ($me['pending'] > 0): ?><span class="badge" aria-label="<?= (int) $me['pending'] ?> waiting for you"><?= (int) $me['pending'] ?></span><?php endif; ?></a>
                    <a href="/profile.php"<?= $current('library') ?>><?= icon('user') ?><span>My books</span></a>
                    <a class="nav__add" href="/add-book.php"<?= $current('add') ?>><?= icon('plus') ?><span>Add book</span></a>
                <?php else: ?>
                    <a href="/signin.php"><?= icon('login') ?><span>Sign in</span></a>
                    <a class="nav__add" href="/signup.php"><?= icon('plus') ?><span>Join free</span></a>
                <?php endif; ?>
            </nav>
            <?php if ($me): ?>
                <details class="menu">
                    <summary aria-label="Account menu"><?= avatar($me, 40) ?></summary>
                    <div class="menu__panel">
                        <p class="menu__name"><?= e($me['first_name'] . ' ' . $me['last_name']) ?></p>
                        <a href="/settings.php"><?= icon('settings') ?>Settings</a>
                        <a href="/rules.php"><?= icon('book') ?>Rules and policies</a>
                        <form action="/actions/logout.php" method="post">
                            <?= csrf_field() ?>
                            <button type="submit"><?= icon('logout') ?>Log out</button>
                        </form>
                    </div>
                </details>
            <?php endif; ?>
        </div>
    </header>
    <main id="main" class="<?= e($mainClass ?? 'page') ?>">
        <?php show_flash(); ?>
