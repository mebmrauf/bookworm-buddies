<?php
require_once 'auth.php';

$title = post('title');
$author = post('author');
$year = post('publish');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('registerbook.php');
    $error = validate_book($title, $author, $year);

    if (!$error) {
        $conn->begin_transaction();
        run("INSERT INTO books (title, author, year_published) VALUES (?, ?, ?)", 'ssi', $title, $author, $year);
        run("INSERT INTO collection (userID, bookID, showcase) VALUES (?, ?, 0)", 'ii', $userID, $conn->insert_id);
        $conn->commit();
        flash('Book registered successfully!', 'profile.php', 'success');
    }
}

$pageTitle = 'Register Book';
include 'header.php';
?>
<div class="container">
    <h1>Add a New Book</h1>
    <?php if (!empty($error)): ?>
        <p class="error-message"><?= e($error) ?></p>
    <?php endif; ?>
    <form action="registerbook.php" method="post">
        <?= csrf_field() ?>
        <label for="title">Book Title:</label>
        <input class="textbox" type="text" id="title" name="title" maxlength="255" value="<?= e($title) ?>" required><br>

        <label for="author">Author:</label>
        <input class="textbox" type="text" id="author" name="author" maxlength="255" value="<?= e($author) ?>" required><br>

        <label for="publish">Published Year:</label>
        <input class="textbox" type="number" id="publish" name="publish" min="1" max="<?= date('Y') ?>" value="<?= e($year) ?>" required><br>

        <button class="button" type="submit">Register</button>
    </form>
</div>
<?php include 'footer.php'; ?>
