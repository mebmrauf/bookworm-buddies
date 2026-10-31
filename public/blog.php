<?php
require_once __DIR__ . '/../src/bootstrap.php';

const PER_PAGE = 18;
const SEARCH_RESULTS = 30;

$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$terms = fulltext_query($q);
// Continues from the last post shown, because page numbers get slower as the blog grows.
$before = max(0, (int) ($_GET['before'] ?? 0));

$columns = 'p.postID, p.title, p.book_title, p.book_author, p.excerpt, p.image_public_id, p.created_at, u.userID, u.username';

// The full text isn't read here, just the saved excerpt.
if ($terms !== '') {
    // A search shows its best batch of matches, so a common word can't mean sorting huge numbers of posts.
    $rows = run("
        SELECT $columns
        FROM (SELECT postID FROM posts WHERE MATCH (title, book_title, book_author) AGAINST (? IN BOOLEAN MODE) LIMIT " . SEARCH_CANDIDATES . ") m
        JOIN posts p ON p.postID = m.postID
        JOIN users u ON p.userID = u.userID
        WHERE p.removed_at IS NULL AND u.suspended_at IS NULL
        LIMIT " . SEARCH_RESULTS,
        's', $terms
    )->get_result()->fetch_all(MYSQLI_ASSOC);
    usort($rows, fn($a, $b) => $b['postID'] <=> $a['postID']);
    $before = 0;
} else {
    $older = $before > 0 ? 'AND p.postID < ?' : '';
    $rows = run("
        SELECT $columns
        FROM posts p
        STRAIGHT_JOIN users u ON p.userID = u.userID
        WHERE p.removed_at IS NULL AND u.suspended_at IS NULL $older
        ORDER BY p.postID DESC
        LIMIT ?
    ", $before > 0 ? 'ii' : 'i', ...($before > 0 ? [$before, PER_PAGE + 1] : [PER_PAGE + 1]))->get_result()->fetch_all(MYSQLI_ASSOC);
}

$hasMore = $terms === '' && count($rows) > PER_PAGE;
$posts = $terms === '' ? array_slice($rows, 0, PER_PAGE) : $rows;

$pageTitle = $q !== '' ? "Blog posts about \"$q\"" : 'Blog';
$pageDescription = 'Readers on Bookworm Buddies write about their favorite books: what they loved, and why you might too.';
$navActive = 'blog';
include __DIR__ . '/../templates/top.php';
?>
<div class="page-head">
    <div>
        <h1>Blog</h1>
        <p class="muted">Readers on the books they love, and why.</p>
    </div>
    <?php if ($posts || $q !== ''): ?>
    <form class="blog-search" action="/blog.php" method="get" role="search">
        <label class="visually-hidden" for="blog-q">Search posts</label>
        <input id="blog-q" type="search" name="q" placeholder="Search posts" value="<?= e($q) ?>">
        <button class="btn btn--ghost" type="submit">Search</button>
    </form>
    <?php endif; ?>
    <a class="btn btn--primary" href="/write-post.php"><?= icon('pen') ?>Write a post</a>
</div>

<?php if ($q === '' && $before === 0): ?><?= quote_of_the_day() ?><?php endif; ?>

<?php if (!$posts): ?>
    <div class="empty">
        <h2><?= $q !== '' ? 'No posts found' : 'No posts yet' ?></h2>
        <p class="muted"><?= $q !== '' ? 'Try another book or author.' : 'Be the first to write about a book you love.' ?></p>
    </div>
<?php else: ?>
    <div class="post-grid">
        <?php foreach ($posts as $post): ?>
            <article class="post-card">
                <?php if ($post['image_public_id'] && cloudinary_enabled()): ?>
                    <img class="post-card__image" src="<?= e(image_url($post['image_public_id'], 640, 360, 'c_fill,g_auto')) ?>" width="640" height="360" alt="" loading="lazy" decoding="async">
                <?php endif; ?>
                <h2><a href="/post.php?id=<?= (int) $post['postID'] ?>"><?= e($post['title']) ?></a></h2>
                <p class="muted">On <?= e($post['book_title']) ?> by <?= e($post['book_author']) ?></p>
                <p><?= e($post['excerpt']) ?></p>
                <p class="muted">By <a class="link" href="/profile.php?userID=<?= (int) $post['userID'] ?>"><?= e($post['username']) ?></a> · <?= e(date('j M Y', strtotime($post['created_at'] . ' UTC'))) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
    <?php if ($hasMore || $before > 0): ?>
        <nav class="pagination" aria-label="More posts">
            <?php $link = fn($changes) => '/blog.php?' . http_build_query($changes + ['q' => $q ?: null]); ?>
            <?php if ($before > 0): ?><a class="btn btn--ghost" href="<?= e($link([])) ?>">Newest</a><?php endif; ?>
            <?php if ($hasMore): ?><a class="btn btn--primary" rel="next" href="<?= e($link(['before' => end($posts)['postID']])) ?>">More posts</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
<?php include __DIR__ . '/../templates/bottom.php'; ?>
