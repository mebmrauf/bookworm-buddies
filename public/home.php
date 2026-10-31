<?php
require_once __DIR__ . '/../src/auth.php';

const PER_PAGE = 24;

$onlyAvailable = ($_GET['available'] ?? '') === '1';
$availableFilter = $onlyAvailable ? "AND NOT EXISTS (SELECT 1 FROM swap s WHERE s.bookID = b.bookID AND s.status IN ('pending', 'accepted'))" : '';
$from = "
    FROM collection c
    JOIN books b ON c.bookID = b.bookID
    JOIN users u ON c.userID = u.userID
    WHERE c.showcase = 1 AND u.userID != ? $availableFilter
";

$total = run("SELECT COUNT(*) $from", 'i', $userID)->get_result()->fetch_row()[0];
$pages = max(1, (int) ceil($total / PER_PAGE));
$page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$offset = ($page - 1) * PER_PAGE;

$books = run("
    SELECT b.bookID, b.title, b.author, b.year_published, b.cover_public_id, u.username, u.userID AS ownerID,
        EXISTS (SELECT 1 FROM swap s WHERE s.bookID = b.bookID AND s.status IN ('pending', 'accepted')) AS unavailable
    $from
    ORDER BY b.bookID DESC
    LIMIT ? OFFSET ?
", 'iii', $userID, PER_PAGE, $offset)->get_result();

$pageTitle = 'Browse books';
$navActive = 'browse';
include __DIR__ . '/../templates/top.php';
$query = $onlyAvailable ? 'available=1&' : '';
?>
<div class="page-head">
    <div>
        <h1>Browse books</h1>
        <p class="muted"><?= (int) $total ?> <?= $total === 1 ? 'book' : 'books' ?> <?= $onlyAvailable ? 'ready to borrow' : 'shared by the community' ?></p>
    </div>
    <div class="tabs" aria-label="Filter books">
        <a href="/home.php"<?= $onlyAvailable ? '' : ' aria-current="page"' ?>>All books</a>
        <a href="/home.php?available=1"<?= $onlyAvailable ? ' aria-current="page"' : '' ?>>Available now</a>
    </div>
</div>

<?php if ($total === 0): ?>
    <div class="empty">
        <h2>No books found</h2>
        <p class="muted">Be the first to share something from your shelf.</p>
        <a class="btn btn--primary" href="/add-book.php">Add a book</a>
    </div>
<?php else: ?>
    <div class="book-grid">
        <?php while ($book = $books->fetch_assoc()) {
            include __DIR__ . '/../templates/book-card.php';
        } ?>
    </div>
    <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Pages">
            <?php if ($page > 1): ?><a class="btn btn--ghost" rel="prev" href="/home.php?<?= $query ?>page=<?= $page - 1 ?>">Previous</a><?php endif; ?>
            <span class="muted">Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?><a class="btn btn--ghost" rel="next" href="/home.php?<?= $query ?>page=<?= $page + 1 ?>">Next</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
<?php include __DIR__ . '/../templates/bottom.php'; ?>
