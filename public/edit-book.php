<?php
require_once __DIR__ . '/../src/auth.php';

$bookID = (int) ($_GET['id'] ?? 0);

$values = run("
    SELECT b.bookID, b.title, b.author, b.year_published, b.cover_public_id, b.handover, b.listing_type
    FROM books b JOIN collection c ON b.bookID = c.bookID
    WHERE b.bookID = ? AND c.userID = ?
", 'ii', $bookID, $userID)->get_result()->fetch_assoc();

if (!$values) {
    flash('You cannot edit that book.', '/profile.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf("/edit-book.php?id=$bookID");
    $oldCover = $values['cover_public_id'];
    $values = ['title' => post('title'), 'author' => post('author'), 'year_published' => post('year_published'), 'handover' => isset(HANDOVERS[post('handover')]) ? post('handover') : $values['handover'], 'listing_type' => isset(LISTING_TYPES[post('listing_type')]) ? post('listing_type') : $values['listing_type']] + $values;
    $error = validate_book($values['title'], $values['author'], $values['year_published']);

    if (!$error) {
        try {
            $cover = upload_image_field('cover', 'covers') ?? (post('remove_cover') === '1' ? null : $oldCover);
            run("UPDATE books SET title = ?, author = ?, year_published = ?, handover = ?, listing_type = ?, cover_public_id = ?, cover_credit = IF(? <=> ?, cover_credit, NULL) WHERE bookID = ?", 'ssisssssi', $values['title'], $values['author'], $values['year_published'], $values['handover'], $values['listing_type'], $cover, $cover, $oldCover, $bookID);
            if ($cover !== $oldCover) {
                delete_images([$oldCover]);
            }
            flash('Book updated.', "/book.php?id=$bookID", 'success');
        } catch (ImageError $ex) {
            $error = $ex->getMessage();
        }
    }
}

$pageTitle = 'Edit book';
$navActive = 'library';
include __DIR__ . '/../templates/top.php';
?>
<div class="narrow">
    <h1>Edit book</h1>
    <?php
    $action = "/edit-book.php?id=$bookID";
    $submitLabel = 'Save changes';
    include __DIR__ . '/../templates/book-form.php';
    ?>
</div>
<?php include __DIR__ . '/../templates/bottom.php'; ?>
