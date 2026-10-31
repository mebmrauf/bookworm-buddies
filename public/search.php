<?php
require_once __DIR__ . '/../src/bootstrap.php';

$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$pattern = like_pattern($q);

$books = $people = null;
if ($q !== '') {
    $books = run("
        SELECT b.bookID, b.title, b.author, b.year_published, b.cover_public_id, b.listing_type, u.userID AS ownerID, u.username, u.city, u.area
        FROM books b
        JOIN collection c ON b.bookID = c.bookID
        JOIN users u ON c.userID = u.userID
        WHERE (b.title LIKE ? OR b.author LIKE ?) AND ((" . VISIBLE_BOOK . ") OR c.userID = ?)
        ORDER BY b.title
    ", 'ssi', $pattern, $pattern, $userID)->get_result();

    // Finding people is for members, so names can't be scraped by anyone passing by.
    $people = !$userID ? null : run("
        SELECT userID, username, first_name, last_name, avatar_public_id
        FROM users
        WHERE suspended_at IS NULL AND (username LIKE ? OR first_name LIKE ? OR last_name LIKE ?)
        ORDER BY first_name
    ", 'sss', $pattern, $pattern, $pattern)->get_result();
}

$pageTitle = 'Search';
$searchValue = $q;
include __DIR__ . '/../templates/top.php';
?>
<?php if ($q === ''): ?>
    <div class="empty">
        <h1>Search</h1>
        <p class="muted">Please enter a search term.</p>
    </div>
<?php else: ?>
    <div class="page-head">
        <h1>Results for "<?= e($q) ?>"</h1>
    </div>

    <?php if ($people && $people->num_rows > 0): ?>
        <section class="section">
            <h2>People</h2>
            <ul class="people">
                <?php while ($person = $people->fetch_assoc()): ?>
                    <li>
                        <a class="person" href="/profile.php?userID=<?= (int) $person['userID'] ?>">
                            <?= avatar($person, 40) ?>
                            <span><strong><?= e($person['first_name'] . ' ' . $person['last_name']) ?></strong><span class="muted"><?= e($person['username']) ?></span></span>
                        </a>
                    </li>
                <?php endwhile; ?>
            </ul>
        </section>
    <?php endif; ?>

    <?php if ($books->num_rows > 0): ?>
        <section class="section">
            <h2>Books</h2>
            <div class="book-grid">
                <?php while ($book = $books->fetch_assoc()) {
                    include __DIR__ . '/../templates/book-card.php';
                } ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($books->num_rows === 0 && (!$people || $people->num_rows === 0)): ?>
        <div class="empty">
            <h2>Nothing found</h2>
            <p class="muted">Try a different title, author or name.</p>
            <a class="btn btn--ghost" href="/home.php">Browse all books</a>
        </div>
    <?php endif; ?>
<?php endif; ?>
<?php include __DIR__ . '/../templates/bottom.php'; ?>
