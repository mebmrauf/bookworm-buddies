<?php
require_once __DIR__ . '/../src/bootstrap.php';

const PER_PAGE = 24;

$onlyAvailable = ($_GET['available'] ?? '') === '1';
$availableFilter = $onlyAvailable ? 'AND NOT ' . BOOK_BUSY : '';
$listing = isset(LISTING_TYPES[$_GET['type'] ?? '']) ? $_GET['type'] : '';

$cities = array_column(run("
    SELECT DISTINCT u.city
    FROM collection c JOIN users u ON c.userID = u.userID
    WHERE " . VISIBLE_BOOK . " AND u.city IS NOT NULL AND u.userID != ?
    ORDER BY u.city
", 'i', $userID)->get_result()->fetch_all(), 0);

$myCity = current_user()['city'] ?? null;

// People can only borrow what they can reach, so start with their own city when it has books.
$requested = is_string($_GET['city'] ?? null) ? $_GET['city'] : null;
$city = $requested === null ? (in_array($myCity, $cities, true) ? $myCity : '') : (in_array($requested, $cities, true) ? $requested : '');

$from = "
    FROM collection c
    JOIN books b ON c.bookID = b.bookID
    JOIN users u ON c.userID = u.userID
    WHERE " . VISIBLE_BOOK . " AND u.userID != ? " . ($city !== '' ? 'AND u.city = ?' : '') . ($listing !== '' ? " AND b.listing_type = '$listing'" : '') . " $availableFilter
";
$types = $city !== '' ? 'is' : 'i';
$params = $city !== '' ? [$userID, $city] : [$userID];

$total = run("SELECT COUNT(*) $from", $types, ...$params)->get_result()->fetch_row()[0];
$pages = max(1, (int) ceil($total / PER_PAGE));
$page = min($pages, max(1, (int) ($_GET['page'] ?? 1)));
$offset = ($page - 1) * PER_PAGE;

$books = run("
    SELECT b.bookID, b.title, b.author, b.year_published, b.cover_public_id, b.handover, b.listing_type, u.username, u.userID AS ownerID, u.city, u.area,
        " . BOOK_BUSY . " AS unavailable
    $from
    ORDER BY b.bookID DESC
    LIMIT ? OFFSET ?
", $types . 'ii', ...[...$params, PER_PAGE, $offset])->get_result();

$pageTitle = 'Browse books';
$navActive = 'browse';
include __DIR__ . '/../templates/top.php';

$url = fn($changes) => '/home.php?' . http_build_query($changes + ['city' => $city === '' ? 'all' : $city, 'type' => $listing ?: null, 'available' => $onlyAvailable ? '1' : null]);
?>
<?php if ($userID && !$myCity): ?>
    <p class="notice notice--info"><a class="link" href="/settings.php">Add your city</a> to see books near you first, and to let others know where you are.</p>
<?php endif; ?>

<div class="page-head">
    <div>
        <h1>Browse books</h1>
        <p class="muted"><?= (int) $total ?> <?= $total === 1 ? 'book' : 'books' ?><?= $city !== '' ? ' in ' . e($city) : ' across all cities' ?><?= $onlyAvailable ? ' ready to borrow' : '' ?></p>
    </div>
    <div class="filters">
        <form class="filters__city" action="/home.php" method="get" data-autosubmit>
            <label for="city"><?= icon('pin') ?><span class="visually-hidden">City</span></label>
            <select id="city" name="city">
                <option value="all">All cities</option>
                <?php foreach ($cities as $option): ?>
                    <option value="<?= e($option) ?>"<?= $option === $city ? ' selected' : '' ?>><?= e($option) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="visually-hidden" for="type">Type</label>
            <select id="type" name="type">
                <option value="">Any type</option>
                <?php foreach (LISTING_TYPES as $value => $label): ?>
                    <option value="<?= $value ?>"<?= $listing === $value ? ' selected' : '' ?>><?= e(['lend' => 'To borrow', 'give' => 'Free to keep', 'swap' => 'To swap'][$value]) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($onlyAvailable): ?><input type="hidden" name="available" value="1"><?php endif; ?>
            <noscript><button class="btn btn--ghost" type="submit">Apply</button></noscript>
        </form>
        <div class="tabs" aria-label="Filter books">
            <a href="<?= e($url(['available' => null])) ?>"<?= $onlyAvailable ? '' : ' aria-current="page"' ?>>All books</a>
            <a href="<?= e($url(['available' => '1'])) ?>"<?= $onlyAvailable ? ' aria-current="page"' : '' ?>>Available now</a>
        </div>
    </div>
</div>

<?php if ($total === 0): ?>
    <div class="empty">
        <h2>No books found<?= $city !== '' ? ' in ' . e($city) : '' ?></h2>
        <p class="muted">Try another city, or be the first to share something from your shelf.</p>
        <div class="actions">
            <?php if ($city !== ''): ?><a class="btn btn--ghost" href="<?= e($url(['city' => 'all'])) ?>">See all cities</a><?php endif; ?>
            <a class="btn btn--primary" href="/add-book.php">Add a book</a>
        </div>
    </div>
<?php else: ?>
    <div class="book-grid">
        <?php while ($book = $books->fetch_assoc()) {
            include __DIR__ . '/../templates/book-card.php';
        } ?>
    </div>
    <?php if ($pages > 1): ?>
        <nav class="pagination" aria-label="Pages">
            <?php if ($page > 1): ?><a class="btn btn--ghost" rel="prev" href="<?= e($url(['page' => $page - 1])) ?>">Previous</a><?php endif; ?>
            <span class="muted">Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?><a class="btn btn--ghost" rel="next" href="<?= e($url(['page' => $page + 1])) ?>">Next</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
<?php include __DIR__ . '/../templates/bottom.php'; ?>
