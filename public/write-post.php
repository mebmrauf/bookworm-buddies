<?php
require_once __DIR__ . '/../src/auth.php';

const MAX_TITLE = 150;
const MIN_BODY = 100;
const MAX_BODY = 10000;
const EXCERPT_LENGTH = 200;
const MAX_POSTS_PER_DAY = 5;

$postID = (int) ($_GET['id'] ?? 0);
$post = ['title' => '', 'book_title' => '', 'book_author' => '', 'body' => '', 'image_public_id' => null];

if ($postID) {
    $post = run("SELECT title, book_title, book_author, body, image_public_id FROM posts WHERE postID = ? AND userID = ?", 'ii', $postID, $userID)->get_result()->fetch_assoc();
    if (!$post) {
        flash('You cannot edit that post.', '/blog.php');
    }
}
$oldImage = $post['image_public_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $self = '/write-post.php' . ($postID ? "?id=$postID" : '');
    require_csrf($self);
    $post = ['title' => post('title'), 'book_title' => post('book_title'), 'book_author' => post('book_author'), 'body' => post('body')] + $post;

    if ($post['title'] === '' || $post['book_title'] === '' || $post['book_author'] === '') {
        $error = 'Please add a title, and the name and author of the book.';
    } elseif (mb_strlen($post['title']) > MAX_TITLE || mb_strlen($post['book_title']) > 255 || mb_strlen($post['book_author']) > 255) {
        $error = 'Titles can be at most ' . MAX_TITLE . ' characters, and book details at most 255.';
    } elseif (mb_strlen($post['body']) < MIN_BODY || mb_strlen($post['body']) > MAX_BODY) {
        $error = 'Your post must be ' . MIN_BODY . ' to ' . number_format(MAX_BODY) . ' characters.';
    } elseif (!$postID && run("SELECT COUNT(*) FROM posts WHERE userID = ? AND created_at > NOW() - INTERVAL 1 DAY", 'i', $userID)->get_result()->fetch_row()[0] >= MAX_POSTS_PER_DAY) {
        $error = 'You can publish up to ' . MAX_POSTS_PER_DAY . ' posts a day. Please come back tomorrow.';
    }

    if (empty($error)) {
        try {
            $image = upload_image_field('photo', 'posts') ?? (post('remove_photo') === '1' ? null : $oldImage);
            // Saved separately so blog lists never have to read every post's full text.
            $flat = preg_replace('/\s+/u', ' ', $post['body']);
            $excerpt = mb_strlen($flat) > EXCERPT_LENGTH ? rtrim(mb_substr($flat, 0, EXCERPT_LENGTH)) . '…' : $flat;
            if ($postID) {
                run(
                    "UPDATE posts SET title = ?, book_title = ?, book_author = ?, body = ?, excerpt = ?, image_public_id = ?, updated_at = NOW() WHERE postID = ? AND userID = ?",
                    'ssssssii', $post['title'], $post['book_title'], $post['book_author'], $post['body'], $excerpt, $image, $postID, $userID
                );
                if ($image !== $oldImage) {
                    delete_images([$oldImage]);
                }
                flash('Post updated.', "/post.php?id=$postID", 'success');
            }
            run(
                "INSERT INTO posts (userID, title, book_title, book_author, body, excerpt, image_public_id) VALUES (?, ?, ?, ?, ?, ?, ?)",
                'issssss', $userID, $post['title'], $post['book_title'], $post['book_author'], $post['body'], $excerpt, $image
            );
            flash('Your post is published.', '/post.php?id=' . $conn->insert_id, 'success');
        } catch (ImageError $ex) {
            $error = $ex->getMessage();
        }
    }
}

$pageTitle = $postID ? 'Edit post' : 'Write a post';
$navActive = 'blog';
include __DIR__ . '/../templates/top.php';
?>
<div class="narrow">
    <h1><?= e($pageTitle) ?></h1>
    <p class="muted">Tell other readers about a book you love: what it's about, what stayed with you, and who should read it.</p>
    <?php form_error($error ?? null); ?>
    <form class="form" action="/write-post.php<?= $postID ? "?id=$postID" : '' ?>" method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="field">
            <label for="title">Post title</label>
            <input id="title" type="text" name="title" maxlength="<?= MAX_TITLE ?>" value="<?= e($post['title']) ?>" required<?= $postID ? '' : ' autofocus' ?>>
        </div>
        <div class="form__row">
            <div class="field">
                <label for="book_title">Book</label>
                <input id="book_title" type="text" name="book_title" maxlength="255" value="<?= e($post['book_title']) ?>" required>
            </div>
            <div class="field">
                <label for="book_author">Author</label>
                <input id="book_author" type="text" name="book_author" maxlength="255" value="<?= e($post['book_author']) ?>" required>
            </div>
        </div>
        <div class="field">
            <label for="body">Your post</label>
            <textarea id="body" name="body" rows="12" minlength="<?= MIN_BODY ?>" maxlength="<?= MAX_BODY ?>" aria-describedby="body-help" required><?= e($post['body']) ?></textarea>
            <p class="help" id="body-help">At least <?= MIN_BODY ?> characters, in your own words. Leave a blank line between paragraphs. Please don't include your phone number or address.</p>
        </div>
        <?php if (cloudinary_enabled()): ?>
            <div class="field">
                <label for="photo">Photo <span class="optional">(optional)</span></label>
                <?= upload_box('photo', 'Selected photo', $oldImage ? '<img class="cover" src="' . e(image_url($oldImage, 160, 240, 'c_fill,g_auto')) . '" alt="">' : null) ?>
                <?php if ($oldImage): ?>
                    <label class="check"><input type="checkbox" name="remove_photo" value="1"> Remove this photo</label>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="form__actions">
            <button type="submit" class="btn btn--primary"><?= $postID ? 'Save changes' : 'Publish' ?></button>
            <a class="btn btn--ghost" href="<?= $postID ? "/post.php?id=$postID" : '/blog.php' ?>">Cancel</a>
        </div>
    </form>
</div>
<?php include __DIR__ . '/../templates/bottom.php'; ?>
