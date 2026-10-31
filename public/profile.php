<?php
require_once __DIR__ . '/../src/bootstrap.php';

if (!isset($_GET['userID']) && !$userID) {
    $_SESSION['next'] = '/profile.php';
    go('/signin.php');
}

$profileID = isset($_GET['userID']) ? (int) $_GET['userID'] : $userID;
$isOwn = $profileID === $userID;
// Visitors only ever see showcased books.
$show = 'showcased';
if ($isOwn) {
    $show = in_array($_GET['show'] ?? '', ['showcased', 'borrowed'], true) ? $_GET['show'] : 'all';
}

$user = run("
    SELECT first_name, last_name, email, date_of_birth, gender, mobile, address, username, avatar_public_id, city, area,
        (SELECT AVG(stars) FROM ratings WHERE ratedID = users.userID) AS ratingAverage,
        (SELECT COUNT(*) FROM ratings WHERE ratedID = users.userID) AS ratingCount
    FROM users WHERE userID = ? AND (suspended_at IS NULL OR userID = ?)
", 'ii', $profileID, $userID)->get_result()->fetch_assoc();

$ratings = $user ? run("
    SELECT r.ratingID, r.stars, r.comment, r.raterID, u.username
    FROM ratings r JOIN users u ON r.raterID = u.userID
    WHERE r.ratedID = ?
    ORDER BY r.ratingID DESC
    LIMIT 10
", 'i', $profileID)->get_result()->fetch_all(MYSQLI_ASSOC) : [];

$posts = $user ? run("
    SELECT postID, title, book_title FROM posts
    WHERE userID = ? AND removed_at IS NULL
    ORDER BY postID DESC
    LIMIT 10
", 'i', $profileID)->get_result()->fetch_all(MYSQLI_ASSOC) : [];

$books = [];
if ($user && $show === 'borrowed') {
    $books = run("
        SELECT b.bookID, b.title, b.author, b.year_published, b.cover_public_id, u.username, u.userID AS ownerID
        FROM swap s
        JOIN books b ON s.bookID = b.bookID
        JOIN users u ON s.ownerID = u.userID
        WHERE s.requesterID = ? AND s.status = 'accepted'
        LIMIT 60
    ", 'i', $userID)->get_result()->fetch_all(MYSQLI_ASSOC);
} elseif ($user) {
    $books = run("
        SELECT b.bookID, b.title, b.author, b.year_published, b.cover_public_id, b.listing_type, c.showcase
        FROM collection c JOIN books b ON c.bookID = b.bookID
        WHERE c.userID = ? AND (c.showcase = 1 OR ? = 'all')
        ORDER BY c.bookID DESC
        LIMIT 60
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
            <p class="rating-summary"><?= e(stars_label($user['ratingAverage'], $user['ratingCount'])) ?></p>
            <?php if (location_label($user) !== ''): ?>
                <p class="muted book-card__where"><?= icon('pin') ?><?= e(location_label($user)) ?></p>
            <?php endif; ?>
            <?php if ($isOwn): ?>
                <p class="muted"><?= e($user['email']) ?></p>
                <?php $details = array_filter([$user['date_of_birth'] ? 'Born ' . $user['date_of_birth'] : null, $user['gender'], $user['mobile'], $user['address']]); ?>
                <?php if ($details): ?>
                    <p class="muted"><?= e(implode(' · ', $details)) ?></p>
                <?php endif; ?>
                <p class="help">Visitors can't see these. Use chat to arrange exchanges.</p>
            <?php endif; ?>
        </div>
        <?php if ($isOwn): ?>
            <a class="btn btn--ghost" href="/settings.php">Edit profile</a>
        <?php elseif ($userID): ?>
            <?= report_form('user', $profileID) ?>
        <?php endif; ?>
    </header>

    <?php if ($isOwn): ?>
        <div class="tabs tabs--spaced" aria-label="Filter your books">
            <a href="/profile.php"<?= $show === 'all' ? ' aria-current="page"' : '' ?>>All books</a>
            <a href="/profile.php?show=showcased"<?= $show === 'showcased' ? ' aria-current="page"' : '' ?>>Shared</a>
            <a href="/profile.php?show=borrowed"<?= $show === 'borrowed' ? ' aria-current="page"' : '' ?>>Borrowed</a>
        </div>
    <?php else: ?>
        <h2 class="section-title">Shared books</h2>
    <?php endif; ?>

    <?php if (!$books): ?>
        <div class="empty">
            <h2><?= $show === 'borrowed' ? 'Nothing borrowed right now' : ($isOwn ? 'Your shelf is empty' : 'No shared books yet') ?></h2>
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
<?php if ($user && $posts): ?>
    <section class="section">
        <h2>Blog posts</h2>
        <ul class="post-links">
            <?php foreach ($posts as $post): ?>
                <li><a class="link" href="/post.php?id=<?= (int) $post['postID'] ?>"><?= e($post['title']) ?></a> <span class="muted">on <?= e($post['book_title']) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
<?php if ($user && $ratings): ?>
    <section class="section feedback">
        <h2>Ratings</h2>
        <ul class="feedback__list">
            <?php foreach ($ratings as $rating): ?>
                <li>
                    <p><strong class="rating-summary"><?= str_repeat('★', (int) $rating['stars']) ?></strong> <span class="visually-hidden"><?= (int) $rating['stars'] ?> out of 5</span> by <a class="link" href="/profile.php?userID=<?= (int) $rating['raterID'] ?>"><?= e($rating['username']) ?></a></p>
                    <?php if ($rating['comment']): ?><p><?= e($rating['comment']) ?></p><?php endif; ?>
                    <?php if ($userID && !$isOwn && $rating['raterID'] != $userID): ?><?= report_form('rating', $rating['ratingID']) ?><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>
<?php include __DIR__ . '/../templates/bottom.php'; ?>
