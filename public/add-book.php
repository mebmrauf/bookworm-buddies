<?php
require_once __DIR__ . '/../src/auth.php';

$values = ['bookID' => 0, 'title' => post('title'), 'author' => post('author'), 'year_published' => post('year_published')];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('/add-book.php');
    $error = validate_book($values['title'], $values['author'], $values['year_published']);

    if (!$error) {
        try {
            $cover = upload_image_field('cover', 'covers');
            $conn->begin_transaction();
            run("INSERT INTO books (title, author, year_published, cover_public_id) VALUES (?, ?, ?, ?)", 'ssis', $values['title'], $values['author'], $values['year_published'], $cover);
            $newID = $conn->insert_id;
            run("INSERT INTO collection (userID, bookID, showcase) VALUES (?, ?, 0)", 'ii', $userID, $newID);
            $conn->commit();
            flash('Book added. Showcase it when you are ready to share.', "/book.php?id=$newID", 'success');
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
    <p class="muted">New books stay private until you showcase them.</p>
    <?php
    $action = '/add-book.php';
    $submitLabel = 'Add book';
    include __DIR__ . '/../templates/book-form.php';
    ?>
</div>
<?php include __DIR__ . '/../templates/bottom.php'; ?>
