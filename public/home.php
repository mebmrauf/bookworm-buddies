<?php
require_once __DIR__ . '/../src/bootstrap.php';

const PER_PAGE = 24;

$onlyAvailable = ($_GET['available'] ?? '') === '1';
$availableFilter = $onlyAvailable ? 'AND NOT ' . BOOK_BUSY : '';
$listing = isset(LISTING_TYPES[$_GET['type'] ?? '']) ? $_GET['type'] : '';
$sort = in_array($_GET['sort'] ?? '', ['requested', 'rated'], true) ? $_GET['sort'] : '';
$sortColumn = ['requested' => 'request_count', 'rated' => 'rating_rank'][$sort] ?? null;

// Reads straight from an index, so it stays quick however many books exist.
$cities = array_column(run("SELECT city FROM collection WHERE showcase = 1 AND city IS NOT NULL GROUP BY showcase, city ORDER BY city")->get_result()->fetch_all(), 0);

$myCity = current_user()['city'] ?? null;

// People can only borrow what they can reach, so start with their own city when it has books.
$requested = is_string($_GET['city'] ?? null) ? $_GET['city'] : null;
$city = $requested === null ? (in_array($myCity, $cities, true) ? $myCity : '') : (in_array($requested, $cities, true) ? $requested : '');

// Each page continues from the last book of the previous one. Page numbers and totals would mean counting
// or skipping every earlier row, which gets slower as the site grows.
$before = max(0, (int) ($_GET['before'] ?? 0));
// Other sorts continue from the last book's value too, since many books share one.
$beforeValue = sprintf('%.2f', max(0, (float) ($_GET['value'] ?? 0)));

$types = 'i';
$params = [$userID];
$where = VISIBLE_BOOK . ' AND c.userID != ?';
if ($city !== '') {
    $where .= ' AND c.city = ?';
    $types .= 's';
    $params[] = $city;
}
if ($before > 0 && $sortColumn) {
    $where .= " AND (c.$sortColumn < ? OR (c.$sortColumn = ? AND c.bookID < ?))";
    $types .= 'ssi';
    array_push($params, $beforeValue, $beforeValue, $before);
} elseif ($before > 0) {
    $where .= ' AND c.bookID < ?';
    $types .= 'i';
    $params[] = $before;
}
if ($listing !== '') {
    $where .= " AND b.listing_type = '$listing'";
}
$where .= " $availableFilter";

$rows = run("
    SELECT b.bookID, b.title, b.author, b.year_published, b.cover_public_id, b.handover, b.listing_type, u.username, u.userID AS ownerID, u.city, u.area, c.request_count, c.rating_rank, c.rating_avg, c.rating_count,
        " . BOOK_BUSY . " AS unavailable
    FROM collection c FORCE INDEX (" . (['requested' => 'requested', 'rated' => 'rated', '' => 'browse'][$sort] . ($city !== '' ? '_city' : '_all')) . ")
    STRAIGHT_JOIN books b ON c.bookID = b.bookID
    STRAIGHT_JOIN users u ON c.userID = u.userID
    WHERE $where
    ORDER BY " . ($sortColumn ? "c.$sortColumn DESC, " : '') . "c.bookID DESC
    LIMIT ?
", $types . 'i', ...[...$params, PER_PAGE + 1])->get_result()->fetch_all(MYSQLI_ASSOC);

$hasMore = count($rows) > PER_PAGE;
$books = array_slice($rows, 0, PER_PAGE);
$ownerRatings = owner_ratings($books);
$showRequestCount = $sort === 'requested';

$pageTitle = 'Browse books';
$navActive = 'browse';
include __DIR__ . '/../templates/top.php';

$url = fn($changes) => '/home.php?' . http_build_query($changes + ['city' => $city === '' ? 'all' : $city, 'type' => $listing ?: null, 'sort' => $sort ?: null, 'available' => $onlyAvailable ? '1' : null]);
?>
<?php if ($userID && !$myCity): ?>
    <p class="notice notice--info"><a class="link" href="/settings.php">Add your city</a> to see books near you first, and to let others know where you are.</p>
<?php endif; ?>

<div class="page-head">
    <div>
        <h1>Browse books</h1>
        <p class="muted">Books <?= $city !== '' ? 'in ' . e($city) : 'across all cities' ?><?= $onlyAvailable ? ' ready to request' : '' ?><?= $sort === 'rated' ? ' · ranked once a book has ' . BOOK_RATINGS_TO_RANK . ' ratings' : '' ?></p>
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
            <label class="visually-hidden" for="sort">Sort</label>
            <select id="sort" name="sort">
                <option value="">Newest</option>
                <option value="requested"<?= $sort === 'requested' ? ' selected' : '' ?>>Most requested</option>
                <option value="rated"<?= $sort === 'rated' ? ' selected' : '' ?>>Top rated</option>
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

<?php if (!$books): ?>
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
        <?php foreach ($books as $book) {
            include __DIR__ . '/../templates/book-card.php';
        } ?>
    </div>
    <?php if ($hasMore || $before > 0): ?>
        <nav class="pagination" aria-label="More books">
            <?php if ($before > 0): ?><a class="btn btn--ghost" href="<?= e($url([])) ?>">Back to start</a><?php endif; ?>
            <?php if ($hasMore): ?><a class="btn btn--primary" rel="next" href="<?= e($url(['before' => end($books)['bookID'], 'value' => $sortColumn ? end($books)[$sortColumn] : null])) ?>">More books</a><?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
<?php include __DIR__ . '/../templates/bottom.php'; ?>
