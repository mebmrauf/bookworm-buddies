<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> - Bookworm Buddies</title>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Source+Serif+4:opsz,wght@8..60,500;8..60,600&display=swap">
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="auth">
    <aside class="auth__aside">
        <div class="auth__pitch">
            <p class="brand brand--light"><?= icon('book') ?><span>Bookworm Buddies</span></p>
            <p class="auth__quote">Your next favorite book is on a neighbor's shelf.</p>
            <p>Share the books you've finished and borrow the ones you haven't read yet.</p>
        </div>
    </aside>
    <main class="auth__main">
        <div class="auth__card">
            <?php show_flash(); ?>
