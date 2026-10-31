<?php
require_once __DIR__ . '/../src/auth.php';

$bookID = (int) ($_GET['id'] ?? 0);

$book = run("
    SELECT b.bookID, b.title, b.author, b.year_published, b.cover_public_id, c.showcase, c.userID AS ownerID,
        u.username, u.first_name, u.last_name, u.avatar_public_id,
        s.status AS swapStatus, s.requesterID
    FROM books b
    JOIN collection c ON b.bookID = c.bookID
    JOIN users u ON c.userID = u.userID
    LEFT JOIN swap s ON s.activeBookID = b.bookID
    WHERE b.bookID = ?
", 'i', $bookID)->get_result()->fetch_assoc();

$isOwner = $book && $book['ownerID'] == $userID;
$canView = $book && ($book['showcase'] || $isOwner || $book['requesterID'] == $userID);

if (!$canView) {
    http_response_code(404);
    $pageTitle = 'Book not found';
    include __DIR__ . '/../templates/top.php';
    ?>
    <div class="empty">
        <h1>Book not found</h1>
        <p class="muted">It may have been removed, or the owner may not be sharing it.</p>
        <a class="btn btn--primary" href="/home.php">Browse books</a>
    </div>
    <?php
    include __DIR__ . '/../templates/bottom.php';
    exit();
}

$feedback = run("
    SELECT f.feedbackID, f.comment, f.reply, f.userID, u.username, u.first_name, u.last_name, u.avatar_public_id
    FROM feedback f JOIN users u ON f.userID = u.userID
    WHERE f.bookID = ?
    ORDER BY f.feedbackID
", 'i', $bookID)->get_result();

$pageTitle = $book['title'];
$navActive = $isOwner ? 'library' : 'browse';
include __DIR__ . '/../templates/top.php';
?>
<article class="book-detail">
    <div class="book-detail__cover"><?= book_cover($book, 400) ?></div>
    <div class="book-detail__info">
        <h1><?= e($book['title']) ?></h1>
        <p class="book-detail__author">by <?= e($book['author']) ?></p>
        <p class="muted">Published <?= e($book['year_published']) ?></p>

        <a class="person" href="/profile.php?userID=<?= (int) $book['ownerID'] ?>">
            <?= avatar($book, 40) ?>
            <span><span class="muted">Shared by</span><strong><?= e($book['first_name'] . ' ' . $book['last_name']) ?></strong></span>
        </a>

        <div class="actions">
            <?php if ($isOwner): ?>
                <a class="btn btn--primary" href="/edit-book.php?id=<?= $bookID ?>">Edit</a>
                <form action="/actions/book-showcase.php" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="bookID" value="<?= $bookID ?>">
                    <input type="hidden" name="showcase" value="<?= $book['showcase'] ? 0 : 1 ?>">
                    <button type="submit" class="btn btn--ghost"><?= $book['showcase'] ? 'Remove from showcase' : 'Showcase this book' ?></button>
                </form>
                <form action="/actions/book-delete.php" method="post" data-confirm="Delete this book? This cannot be undone.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="bookID" value="<?= $bookID ?>">
                    <button type="submit" class="btn btn--danger">Delete</button>
                </form>
            <?php elseif ($book['requesterID'] == $userID): ?>
                <span class="tag tag--success"><?= $book['swapStatus'] === 'accepted' ? 'You are borrowing this book' : 'You requested this book' ?></span>
                <a class="btn btn--ghost" href="/requests.php">View request</a>
            <?php elseif ($book['swapStatus']): ?>
                <span class="tag">Not Available</span>
            <?php else: ?>
                <form action="/actions/swap-request.php" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="bookID" value="<?= $bookID ?>">
                    <button type="submit" class="btn btn--primary">Request this book</button>
                </form>
            <?php endif; ?>
        </div>
        <?php if ($isOwner && !$book['showcase']): ?>
            <p class="help">Only you can see this book. Showcase it to let others request it.</p>
        <?php endif; ?>
    </div>
</article>

<section class="section feedback">
    <h2>Feedback</h2>
    <?php if ($feedback->num_rows === 0): ?>
        <p class="muted">No feedback yet.</p>
    <?php endif; ?>
    <ul class="feedback__list">
        <?php while ($item = $feedback->fetch_assoc()): ?>
            <li class="feedback__item">
                <?= avatar($item, 40) ?>
                <div class="feedback__body">
                    <a class="link" href="/profile.php?userID=<?= (int) $item['userID'] ?>"><?= e($item['username']) ?></a>
                    <p><?= e($item['comment']) ?></p>
                    <?php if (!empty($item['reply'])): ?>
                        <blockquote class="feedback__reply"><strong>Reply from the owner</strong><?= e($item['reply']) ?></blockquote>
                    <?php endif; ?>
                    <?php if ($isOwner): ?>
                        <details class="feedback__respond">
                            <summary><?= empty($item['reply']) ? 'Reply' : 'Edit reply' ?></summary>
                            <form class="form" action="/actions/feedback-reply.php" method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="feedbackID" value="<?= (int) $item['feedbackID'] ?>">
                                <label class="visually-hidden" for="reply-<?= (int) $item['feedbackID'] ?>">Your reply</label>
                                <textarea id="reply-<?= (int) $item['feedbackID'] ?>" name="reply" rows="2" maxlength="500" required></textarea>
                                <button type="submit" class="btn btn--ghost">Send reply</button>
                            </form>
                        </details>
                    <?php endif; ?>
                    <?php if ($item['userID'] == $userID): ?>
                        <form action="/actions/feedback-delete.php" method="post" data-confirm="Delete your feedback?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="feedbackID" value="<?= (int) $item['feedbackID'] ?>">
                            <button type="submit" class="btn-text">Delete</button>
                        </form>
                    <?php endif; ?>
                </div>
            </li>
        <?php endwhile; ?>
    </ul>

    <?php if (!$isOwner && $book['showcase']): ?>
        <form class="form" action="/actions/feedback-add.php" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="bookID" value="<?= $bookID ?>">
            <div class="field">
                <label for="comment">Add your feedback</label>
                <textarea id="comment" name="comment" rows="3" maxlength="500" required></textarea>
            </div>
            <div><button type="submit" class="btn btn--primary">Post feedback</button></div>
        </form>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/../templates/bottom.php'; ?>
