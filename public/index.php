<?php
require_once __DIR__ . '/../src/bootstrap.php';

if ($userID) {
    go('/home.php');
}

$showcased = "FROM collection c JOIN books b ON c.bookID = b.bookID JOIN users u ON c.userID = u.userID WHERE " . VISIBLE_BOOK;

$books = run("
    SELECT b.bookID, b.title, b.author, b.year_published, b.cover_public_id, b.listing_type, u.username, u.userID AS ownerID, u.city, u.area
    $showcased
    ORDER BY b.cover_public_id IS NULL, b.bookID DESC
    LIMIT 12
")->get_result();

$pageTitle = 'Share and borrow books';
$pageDescription = 'Bookworm Buddies is a free community for lending books. Browse shared books, borrow what you want to read and lend what you have finished.';
$mainClass = 'landing';
include __DIR__ . '/../templates/top.php';
?>
<section class="hero">
    <div class="hero__inner">
        <h1>Your next favorite book is on a neighbor's shelf.</h1>
        <p class="hero__lead">Borrow books from other readers, and lend the ones you've finished. It's free, and you can look around without an account.</p>
        <div class="actions">
            <a class="btn btn--primary btn--large" href="/home.php">Browse books</a>
            <a class="btn btn--light btn--large" href="/signup.php">Join free</a>
        </div>
    </div>
</section>

<?php if ($books->num_rows > 0): ?>
<section class="landing__section">
    <div class="landing__head">
        <h2>Recently shared</h2>
        <a class="link" href="/home.php">See all books</a>
    </div>
    <div class="book-grid">
        <?php while ($book = $books->fetch_assoc()) {
            include __DIR__ . '/../templates/book-card.php';
        } ?>
    </div>
</section>
<?php endif; ?>

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
                <p class="muted">Once accepted, you can contact each other. Meet somewhere public, or use a courier if you live far apart. Then rate each other, so the community knows who to trust.</p>
            </li>
        </ol>
    </div>
</section>

<section class="landing__section landing__cta">
    <h2>Got books gathering dust?</h2>
    <p class="muted">Put them on your shelf and let someone else enjoy them. Your contact details stay private until a request is accepted.</p>
    <a class="btn btn--primary btn--large" href="/signup.php">Create a free account</a>
</section>
<?php include __DIR__ . '/../templates/bottom.php'; ?>
