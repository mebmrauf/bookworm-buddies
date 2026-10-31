<?php
require_once 'auth.php';

$bookID = (int) ($_GET['bookID'] ?? 0);

$book = run("
    SELECT b.title, b.author, b.year_published
    FROM books b JOIN collection c ON b.bookID = c.bookID
    WHERE b.bookID = ? AND c.userID = ?
", 'ii', $bookID, $userID)->get_result()->fetch_assoc();

if (!$book) {
    flash('You cannot edit that book.', 'profile.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf("editbookdetails.php?bookID=$bookID");
    $book = ['title' => post('title'), 'author' => post('author'), 'year_published' => post('year_published')];
    $error = validate_book($book['title'], $book['author'], $book['year_published']);

    if (!$error) {
        run("UPDATE books SET title = ?, author = ?, year_published = ? WHERE bookID = ?", 'ssii', $book['title'], $book['author'], $book['year_published'], $bookID);
        flash('Book updated.', 'profile.php', 'success');
    }
}

$pageTitle = 'Edit Book';
include 'header.php';
?>
<section class="container">
    <h1>Update Book Information</h1>
    <?php if (!empty($error)): ?>
        <p class="error-message"><?= e($error) ?></p>
    <?php endif; ?>

    <form class="form-container" action="editbookdetails.php?bookID=<?= $bookID ?>" method="POST">
        <?= csrf_field() ?>
        <label for="title">Title:</label>
        <input class="textbox" type="text" id="title" name="title" maxlength="255" value="<?= e($book['title']) ?>" required><br>

        <label for="author">Author:</label>
        <input class="textbox" type="text" id="author" name="author" maxlength="255" value="<?= e($book['author']) ?>" required><br>

        <label for="year_published">Published Year:</label>
        <input class="textbox" type="number" id="year_published" name="year_published" min="1" max="<?= date('Y') ?>" value="<?= e($book['year_published']) ?>" required><br>

        <button type="submit" class="button">Update</button>
    </form>

    <button onclick="window.location.href='profile.php'" class="button">Back to Profile</button>
</section>
<?php include 'footer.php'; ?>
