<?php
require_once __DIR__ . '/../../src/admin.php';
require_once __DIR__ . '/../../templates/admin-action.php';
require_admin();

const PER_PAGE = 30;

$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$onlyRemoved = ($_GET['status'] ?? '') === 'removed';
$page = max(1, (int) ($_GET['page'] ?? 1));
$pattern = like_pattern($q);
$offset = ($page - 1) * PER_PAGE;

$books = run("
    SELECT b.bookID, b.title, b.author, b.cover_public_id, b.cover_credit, b.listing_type, b.removed_at, b.removed_reason, c.showcase, u.userID, u.username,
        (SELECT COUNT(*) FROM reports r WHERE r.target_type = 'book' AND r.target_id = b.bookID) AS reportCount
    FROM books b JOIN collection c ON b.bookID = c.bookID JOIN users u ON c.userID = u.userID
    WHERE (b.title LIKE ? OR b.author LIKE ? OR u.username LIKE ?)" . ($onlyRemoved ? ' AND b.removed_at IS NOT NULL' : '') . "
    ORDER BY b.bookID DESC
    LIMIT ? OFFSET ?
", 'sssii', $pattern, $pattern, $pattern, PER_PAGE + 1, $offset)->get_result()->fetch_all(MYSQLI_ASSOC);
$hasMore = count($books) > PER_PAGE;
$books = array_slice($books, 0, PER_PAGE);

$pageTitle = 'Books';
include __DIR__ . '/../../templates/admin-top.php';
?>
<div class="page-head"><h1>Books</h1></div>
<form class="admin-search" action="/admin/books.php" method="get">
    <label class="visually-hidden" for="q">Search books</label>
    <input id="q" type="search" name="q" placeholder="Title, author or owner" value="<?= e($q) ?>">
    <button class="btn btn--ghost" type="submit">Search</button>
</form>
<ul class="admin-list">
    <?php foreach ($books as $book): ?>
        <li class="admin-row">
            <div class="swap__cover"><?= book_cover($book, '88px') ?></div>
            <div class="admin-row__main">
                <strong><?= e($book['title']) ?></strong>
                <p class="muted"><?= e($book['author']) ?> · owner <a class="link" href="/profile.php?userID=<?= (int) $book['userID'] ?>"><?= e($book['username']) ?></a></p>
                <p class="muted"><?= e(LISTING_TYPES[$book['listing_type']]) ?> · <?= $book['showcase'] ? 'Shared' : 'Private' ?> · <?= (int) $book['reportCount'] ?> reports<?= $book['cover_credit'] ? ' · Cover from ' . e($book['cover_credit']) : '' ?></p>
                <?php if ($book['removed_at']): ?>
                    <p><span class="tag tag--declined">Taken down</span> <?= e($book['removed_reason']) ?></p>
                <?php endif; ?>
            </div>
            <div class="admin-row__actions">
                <?php if ($book['cover_public_id']): ?><?= admin_action('remove_cover', $book['bookID'], 'Remove cover', true) ?><?php endif; ?>
                <?= $book['removed_at'] ? admin_action('restore', $book['bookID'], 'Restore') : admin_action('takedown', $book['bookID'], 'Take down', true) ?>
            </div>
        </li>
    <?php endforeach; ?>
</ul>
<?php if (!$books): ?><p class="muted">No books found.</p><?php endif; ?>
<nav class="pagination" aria-label="Pages">
    <?php $query = fn($p) => http_build_query(['q' => $q ?: null, 'status' => $onlyRemoved ? 'removed' : null, 'page' => $p]); ?>
    <?php if ($page > 1): ?><a class="btn btn--ghost" href="?<?= e($query($page - 1)) ?>">Previous</a><?php endif; ?>
    <?php if ($hasMore): ?><a class="btn btn--ghost" href="?<?= e($query($page + 1)) ?>">Next</a><?php endif; ?>
</nav>
<?php include __DIR__ . '/../../templates/admin-bottom.php'; ?>
