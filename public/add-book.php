<?php
require_once __DIR__ . '/../src/auth.php';

$values = ['bookID' => 0, 'title' => post('title'), 'author' => post('author'), 'year_published' => post('year_published'), 'handover' => isset(HANDOVERS[post('handover')]) ? post('handover') : 'meet', 'listing_type' => isset(LISTING_TYPES[post('listing_type')]) ? post('listing_type') : 'lend'];
$share = $_SERVER['REQUEST_METHOD'] !== 'POST' || post('share') === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/add-book.php');
    $error = validate_book($values['title'], $values['author'], $values['year_published']);

    if (!$error) {
        try {
            $cover = upload_image_field('cover', 'covers');
            $conn->begin_transaction();
            run("INSERT INTO books (title, author, year_published, cover_public_id, handover, listing_type) VALUES (?, ?, ?, ?, ?, ?)", 'ssisss', $values['title'], $values['author'], $values['year_published'], $cover, $values['handover'], $values['listing_type']);
            $newID = $conn->insert_id;
            run("INSERT INTO collection (userID, bookID, showcase) VALUES (?, ?, ?)", 'iii', $userID, $newID, $share ? 1 : 0);
            $conn->commit();
            flash($share ? 'Book added and shared.' : 'Book added. Only you can see it for now.', "/book.php?id=$newID", 'success');
        } catch (ImageError $ex) {
            $error = $ex->getMessage();
        }
    }
}

$pageTitle = 'Add a book';
$navActive = 'add';
include __DIR__ . '/../templates/top.php';
?>
<div class="narrow">
    <h1>Add a book</h1>
    <p class="muted">Share something from your shelf. You can change or remove it at any time.</p>
    <?php
    $action = '/add-book.php';
    $submitLabel = 'Add book';
    $shareOption = $share;
    include __DIR__ . '/../templates/book-form.php';
    ?>
</div>
<?php include __DIR__ . '/../templates/bottom.php'; ?>
