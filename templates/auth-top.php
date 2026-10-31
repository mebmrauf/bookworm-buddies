<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?php include __DIR__ . '/pwa-head.php'; ?>
    <title><?= e($pageTitle) ?> - Bookworm Buddies</title>
    <link rel="preload" href="/assets/fonts/inter.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= asset('app.css') ?>">
    <script src="<?= asset('app.js') ?>" defer></script>
</head>
<body class="auth">
    <div class="loading-bar" aria-hidden="true"></div>
    <aside class="auth__aside">
        <div class="auth__pitch">
            <a class="brand brand--light" href="/"><?= icon('book') ?><span>Bookworm Buddies</span></a>
            <p class="auth__quote">Your next favorite book is on a neighbor's shelf.</p>
            <p>Share the books you've finished and borrow the ones you haven't read yet.</p>
        </div>
    </aside>
    <main class="auth__main">
        <div class="auth__card">
            <?php show_flash(); ?>
