<?php
require_once __DIR__ . '/../src/bootstrap.php';

const COLLAGE_SIZE = 12;

$covers = cloudinary_enabled() ? array_column(run("
    SELECT b.cover_public_id
    FROM (SELECT bookID, userID FROM collection FORCE INDEX (browse_all) WHERE showcase = 1 ORDER BY bookID DESC LIMIT " . HIGHLIGHT_WINDOW . ") c
    STRAIGHT_JOIN books b ON c.bookID = b.bookID
    STRAIGHT_JOIN users u ON c.userID = u.userID
    WHERE u.suspended_at IS NULL AND b.cover_public_id IS NOT NULL
    ORDER BY c.bookID DESC
    LIMIT " . COLLAGE_SIZE
)->get_result()->fetch_all(), 0) : [];

$contact = config('contact_email');
$pageTitle = 'About';
$pageDescription = 'Bookworm Buddies is a free community where readers in Bangladesh lend, swap and give away physical books.';
include __DIR__ . '/../templates/top.php';
?>
<div class="about">
    <header class="about__hero">
        <div>
            <p class="page-eyebrow">About Bookworm Buddies</p>
            <h1>Books are meant to be <em>passed along.</em></h1>
            <p class="about__lead">A free community where readers in Bangladesh lend, swap and give away the books on their shelves.</p>
        </div>
        <?php if (count($covers) === COLLAGE_SIZE): ?>
            <div class="about__collage" aria-hidden="true">
                <?php foreach ($covers as $cover): ?>
                    <img src="<?= e(image_url($cover, 200, 300)) ?>" width="200" height="300" alt="">
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </header>

    <section class="about__section">
        <h2>Why it exists</h2>
        <div>
            <p class="about__statement">Somewhere near you, the book you've been meaning to read is gathering dust on someone's shelf.</p>
            <p>Finished books tend to stay where they are, while other readers nearby look for those same titles. Buying every book isn't realistic for everyone, and good libraries aren't always close by.</p>
            <p>Bookworm Buddies makes those shelves visible, so readers can find books near them and arrange an exchange directly.</p>
        </div>
    </section>

    <section class="about__section">
        <h2>How we approach it</h2>
        <div class="about__grid">
            <div>
                <h3>Free to use</h3>
                <p>No fees, no ads, and no selling of your information.</p>
            </div>
            <div>
                <h3>Local first</h3>
                <p>Browse by city and see the area of each book, so you know what's within reach.</p>
            </div>
            <div>
                <h3>Private by default</h3>
                <p>Arrange exchanges in chat, without sharing your email or phone number.</p>
            </div>
            <div>
                <h3>Built on trust</h3>
                <p>Return dates, ratings, reports and <a class="link" href="/rules.php">clear rules</a> keep exchanges fair.</p>
            </div>
        </div>
    </section>

    <section class="about__section">
        <h2>Get in touch</h2>
        <div>
            <p>For questions or feedback, email <?php if ($contact): ?><a class="link" href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a> or <?php endif; ?>visit <a class="link" href="https://bmrauf.me" rel="noopener">bmrauf.me</a>.</p>
        </div>
    </section>
</div>

<section class="about__cta">
    <h2>Have a book to share, or looking for <em>your next one?</em></h2>
    <div class="actions">
        <a class="btn btn--primary btn--large" href="/home.php">Browse books</a>
        <?php if ($userID): ?>
            <a class="btn btn--light btn--large" href="/add-book.php">Add a book</a>
        <?php else: ?>
            <a class="btn btn--light btn--large" href="/signup.php">Join free</a>
        <?php endif; ?>
    </div>
</section>
<?php include __DIR__ . '/../templates/bottom.php'; ?>
