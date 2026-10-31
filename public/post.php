<?php
require_once __DIR__ . '/../src/bootstrap.php';

$postID = (int) ($_GET['id'] ?? 0);
// The blog opens posts in a popup, which only needs the article itself. The full page stays for shared links.
$inPopup = isset($_GET['popup']);

$post = run("
    SELECT p.*, u.username, u.first_name, u.last_name, u.avatar_public_id, u.suspended_at,
        (SELECT AVG(stars) FROM ratings WHERE ratedID = u.userID) AS ratingAverage,
        (SELECT COUNT(*) FROM ratings WHERE ratedID = u.userID) AS ratingCount
    FROM posts p JOIN users u ON p.userID = u.userID
    WHERE p.postID = ?
", 'i', $postID)->get_result()->fetch_assoc();

$isAuthor = $post && $userID && $post['userID'] == $userID;

if (!$post || (!$isAuthor && ($post['removed_at'] || $post['suspended_at']))) {
    http_response_code(404);
    $pageTitle = 'Post not found';
    include __DIR__ . '/../templates/top.php';
    ?>
    <div class="empty">
        <h1>Post not found</h1>
        <p class="muted">It may have been removed.</p>
        <a class="btn btn--primary" href="/blog.php">Read the blog</a>
    </div>
    <?php
    include __DIR__ . '/../templates/bottom.php';
    exit();
}

// Copies of this book that a reader could request right now.
$copies = run("
    SELECT b.bookID, b.listing_type, u.username, u.city, u.area
    FROM books b
    JOIN collection c ON b.bookID = c.bookID
    JOIN users u ON c.userID = u.userID
    WHERE b.title = ? AND " . VISIBLE_BOOK . " AND c.userID != ? AND NOT " . BOOK_BUSY . "
    LIMIT 3
", 'si', $post['book_title'], $userID)->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = $post['title'];
$pageDescription = $post['excerpt'];
$navActive = 'blog';
$cover = cloudinary_enabled() ? (post_covers([$post])[mb_strtolower($post['book_title'])] ?? null) : null;
if (!$inPopup) {
    include __DIR__ . '/../templates/top.php';
}
?>
<article class="post">
    <?php if (!$inPopup): ?>
        <p><a class="link" href="/blog.php">Back to blog</a></p>
    <?php endif; ?>
    <?php if ($cover && !$post['image_public_id']): ?>
        <img class="post__cover" src="<?= e(image_url($cover, 280, 420)) ?>" width="280" height="420" alt="Cover of <?= e($post['book_title']) ?>" decoding="async">
    <?php endif; ?>
    <h1><?= e($post['title']) ?></h1>
    <p class="post__book">On <strong><?= e($post['book_title']) ?></strong> by <?= e($post['book_author']) ?></p>
    <div class="post__meta">
        <a class="person" href="/profile.php?userID=<?= (int) $post['userID'] ?>">
            <?= avatar($post, 40) ?>
            <span><strong><?= e(trim($post['first_name'] . ' ' . $post['last_name'])) ?></strong><span class="muted"><?= e(date('j F Y', strtotime($post['created_at'] . ' UTC'))) ?></span></span>
        </a>
        <span class="rating-summary"><?= e(stars_label($post['ratingAverage'], $post['ratingCount'])) ?></span>
    </div>
    <?php if ($post['removed_at']): ?>
        <p class="notice notice--error">An admin took this post down, so only you can see it: <?= e($post['removed_reason']) ?></p>
    <?php endif; ?>
    <?php if ($post['image_public_id'] && cloudinary_enabled()): ?>
        <img class="post__image" src="<?= e(image_url($post['image_public_id'], 1280, 720, 'c_limit')) ?>" alt="Photo for <?= e($post['title']) ?>" decoding="async">
    <?php endif; ?>
    <div class="post__body">
        <?php foreach (preg_split('/\R{2,}/', $post['body']) as $paragraph): ?>
            <p><?= nl2br(e($paragraph)) ?></p>
        <?php endforeach; ?>
    </div>

    <?php if ($copies): ?>
        <section class="section">
            <h2>Want to read it?</h2>
            <ul class="post-links">
                <?php foreach ($copies as $copy): ?>
                    <li><a class="link" href="/book.php?id=<?= (int) $copy['bookID'] ?>"><?= e(['lend' => 'Borrow', 'give' => 'Get for free', 'swap' => 'Swap'][$copy['listing_type']]) ?> from <?= e($copy['username']) ?></a><?= location_label($copy) !== '' ? ' <span class="muted">' . e(location_label($copy)) . '</span>' : '' ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <div class="post__actions actions">
        <?php if ($isAuthor): ?>
            <a class="btn btn--ghost" href="/write-post.php?id=<?= $postID ?>">Edit</a>
            <form action="/actions/post-delete.php" method="post" data-confirm="Delete this post? This cannot be undone.">
                <?= csrf_field() ?>
                <input type="hidden" name="postID" value="<?= $postID ?>">
                <button type="submit" class="btn btn--danger">Delete</button>
            </form>
        <?php elseif ($userID): ?>
            <?= report_form('post', $postID) ?>
        <?php endif; ?>
    </div>
</article>
<?php
if (!$inPopup) {
    include __DIR__ . '/../templates/bottom.php';
}

