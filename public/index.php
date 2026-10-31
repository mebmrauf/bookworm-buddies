<?php
require_once __DIR__ . '/../src/bootstrap.php';

if ($userID) {
    go('/home.php');
}

const HERO_COVERS = 4;

// Looks through a fixed window of the newest books, so the home page costs the same at any size.
$books = run("
    SELECT b.bookID, b.title, b.author, b.year_published, b.cover_public_id, b.listing_type, u.username, u.userID AS ownerID, u.city, u.area
    FROM (SELECT bookID, userID FROM collection FORCE INDEX (browse_all) WHERE showcase = 1 ORDER BY bookID DESC LIMIT " . HIGHLIGHT_WINDOW . ") c
    STRAIGHT_JOIN books b ON c.bookID = b.bookID
    STRAIGHT_JOIN users u ON c.userID = u.userID
    WHERE u.suspended_at IS NULL AND b.cover_public_id IS NOT NULL
    ORDER BY c.bookID DESC
    LIMIT 12
")->get_result()->fetch_all(MYSQLI_ASSOC);

$posts = run("
    SELECT p.postID, p.title, p.book_title, p.excerpt, u.username
    FROM posts p STRAIGHT_JOIN users u ON p.userID = u.userID
    WHERE p.removed_at IS NULL AND u.suspended_at IS NULL
    ORDER BY p.postID DESC
    LIMIT 3
")->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Bookworm Buddies';
$pageDescription = 'Bookworm Buddies is a free community for lending books. Browse shared books, borrow what you want to read and lend what you have finished.';
$mainClass = 'landing';
include __DIR__ . '/../templates/top.php';
?>
<section class="hero">
    <div class="hero__inner">
        <div class="hero__text">
        <h1>Every book deserves <em>another reader.</em></h1>
        <p class="hero__lead">Borrow from book lovers near you, pass on the stories you've finished, and give every shelf a second life. It's free, and you can look around without an account.</p>
        <div class="actions">
            <a class="btn btn--primary btn--large" href="/home.php">Browse books</a>
            <a class="btn btn--light btn--large" href="/signup.php">Join free</a>
        </div>
        </div>
        <?php if (cloudinary_enabled()): ?>
            <div class="hero__fan">
                <?php foreach (array_slice($books, 0, HERO_COVERS) as $book): ?>
                    <?php // The fan is hidden on phones, and this stops them downloading covers they'd never show. ?>
                    <a href="/book.php?id=<?= (int) $book['bookID'] ?>"><picture><source media="(min-width: 960px)" srcset="<?= e(image_url($book['cover_public_id'], 440, 660)) ?>"><img width="440" height="660" alt="Cover of <?= e($book['title']) ?>"></picture></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($books): ?>
<section class="landing__section">
    <div class="landing__head">
        <h2>Recently shared</h2>
        <a class="link" href="/home.php">See all books</a>
    </div>
    <div class="book-grid">
        <?php foreach ($books as $book) {
            include __DIR__ . '/../templates/book-card.php';
        } ?>
    </div>
</section>
<?php endif; ?>

<?php if ($posts): ?>
<section class="landing__section">
    <div class="landing__head">
        <h2>From the blog</h2>
        <a class="link" href="/blog.php">See all posts</a>
    </div>
    <div class="post-grid">
        <?php foreach ($posts as $post): ?>
            <article class="post-card">
                <h3><a href="/post.php?id=<?= (int) $post['postID'] ?>"><?= e($post['title']) ?></a></h3>
                <p class="muted">On <?= e($post['book_title']) ?> · by <?= e($post['username']) ?></p>
                <p><?= e($post['excerpt']) ?></p>
                <a class="link post-card__more" href="/post.php?id=<?= (int) $post['postID'] ?>" aria-label="Read the full post: <?= e($post['title']) ?>">Read the full post</a>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="landing__section landing__quote"><?= quote_of_the_day() ?></section>

<section class="landing__section landing__section--tint">
    <div class="landing__inner">
        <h2>How it works</h2>
        <ol class="steps">
            <li>
                <h3>Find a book</h3>
                <p class="muted">See what readers in your city are sharing, or search by title or author.</p>
            </li>
            <li>
                <h3>Send a request</h3>
                <p class="muted">Borrow it, swap one of yours for it, or take a free one. The owner decides whether to accept.</p>
            </li>
            <li>
                <h3>Hand over and read</h3>
                <p class="muted">Once accepted, you get a private chat to arrange it. Meet somewhere public, or use a courier if you live far apart. Then rate each other, so the community knows who to trust.</p>
            </li>
        </ol>
    </div>
</section>

<section class="landing__section landing__cta">
    <h2>Got books gathering dust?</h2>
    <p class="muted">Put them on your shelf and let someone else enjoy them.</p>
    <a class="btn btn--primary btn--large" href="/signup.php">Create a free account</a>
    <p class="landing__links"><a class="link" href="/about.php">About</a> &middot; <a class="link" href="/rules.php">Rules and policies</a></p>
</section>
<?php include __DIR__ . '/../templates/bottom.php'; ?>
