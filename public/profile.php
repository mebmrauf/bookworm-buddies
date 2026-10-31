<?php
require_once __DIR__ . '/../src/auth.php';

$profileID = isset($_GET['userID']) ? (int) $_GET['userID'] : $userID;
$isOwn = $profileID === $userID;
// Visitors only ever see showcased books.
$show = 'showcased';
if ($isOwn) {
    $show = in_array($_GET['show'] ?? '', ['showcased', 'borrowed'], true) ? $_GET['show'] : 'all';
}

$user = run("SELECT first_name, last_name, email, date_of_birth, username, avatar_public_id FROM users WHERE userID = ?", 'i', $profileID)->get_result()->fetch_assoc();

$books = [];
if ($user && $show === 'borrowed') {
    $books = run("
        SELECT b.bookID, b.title, b.author, b.year_published, b.cover_public_id, u.username, u.userID AS ownerID
        FROM swap s
        JOIN books b ON s.bookID = b.bookID
        JOIN users u ON s.ownerID = u.userID
        WHERE s.requesterID = ? AND s.status = 'accepted'
    ", 'i', $userID)->get_result()->fetch_all(MYSQLI_ASSOC);
} elseif ($user) {
    $books = run("
        SELECT b.bookID, b.title, b.author, b.year_published, b.cover_public_id, c.showcase
        FROM collection c JOIN books b ON c.bookID = b.bookID
        WHERE c.userID = ? AND (c.showcase = 1 OR ? = 'all')
        ORDER BY b.bookID DESC
    ", 'is', $profileID, $show)->get_result()->fetch_all(MYSQLI_ASSOC);
}

$pageTitle = $user ? $user['first_name'] . ' ' . $user['last_name'] : 'User not found';
$navActive = $isOwn ? 'library' : '';
include __DIR__ . '/../templates/top.php';
?>
<?php if (!$user): ?>
    <div class="empty">
        <h1>User not found</h1>
        <a class="btn btn--primary" href="/home.php">Browse books</a>
    </div>
<?php else: ?>
    <header class="profile-head">
        <?= avatar($user, 80) ?>
        <div class="profile-head__text">
            <h1><?= e($user['first_name'] . ' ' . $user['last_name']) ?></h1>
            <p class="muted">@<?= e($user['username']) ?></p>
            <?php if ($isOwn): ?>
                <p class="muted"><?= e($user['email']) ?> &middot; Born <?= e($user['date_of_birth']) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($isOwn): ?>
            <a class="btn btn--ghost" href="/settings.php">Edit profile</a>
        <?php endif; ?>
    </header>

    <?php if ($isOwn): ?>
        <div class="tabs tabs--spaced" aria-label="Filter your books">
            <a href="/profile.php"<?= $show === 'all' ? ' aria-current="page"' : '' ?>>All books</a>
            <a href="/profile.php?show=showcased"<?= $show === 'showcased' ? ' aria-current="page"' : '' ?>>Showcased</a>
            <a href="/profile.php?show=borrowed"<?= $show === 'borrowed' ? ' aria-current="page"' : '' ?>>Borrowed</a>
        </div>
    <?php else: ?>
        <h2 class="section-title">Shared books</h2>
    <?php endif; ?>

    <?php if (!$books): ?>
        <div class="empty">
            <h2><?= $show === 'borrowed' ? 'Nothing borrowed right now' : 'No books yet' ?></h2>
            <?php if ($isOwn && $show === 'borrowed'): ?>
                <a class="btn btn--primary" href="/home.php?available=1">Find a book</a>
            <?php elseif ($isOwn): ?>
                <a class="btn btn--primary" href="/add-book.php">Add a book</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="book-grid">
            <?php foreach ($books as $book) {
                if (!$isOwn) {
                    unset($book['showcase']);
                }
                include __DIR__ . '/../templates/book-card.php';
            } ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php include __DIR__ . '/../templates/bottom.php'; ?>
