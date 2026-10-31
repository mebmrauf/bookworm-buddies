<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/search.php';

const SEARCH_PAGE_LIMIT = 40;
const SEARCH_PEOPLE_LIMIT = 12;

$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$books = $people = null;
if ($q !== '') {
    $books = search_books($q, $userID, SEARCH_PAGE_LIMIT);
    // Finding people is for members, so names can't be scraped by anyone passing by.
    $people = $userID ? search_people($q, SEARCH_PEOPLE_LIMIT) : null;
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
