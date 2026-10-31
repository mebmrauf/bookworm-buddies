<?php
require_once __DIR__ . '/../src/bootstrap.php';

$bookID = (int) ($_GET['id'] ?? 0);

$book = run("
    SELECT b.bookID, b.title, b.author, b.year_published, b.cover_public_id, b.cover_credit, b.handover, b.listing_type, b.removed_at, b.removed_reason, c.showcase, c.userID AS ownerID,
        u.username, u.first_name, u.last_name, u.avatar_public_id, u.city, u.area, u.suspended_at,
        (SELECT AVG(stars) FROM ratings WHERE ratedID = u.userID) AS ratingAverage,
        (SELECT COUNT(*) FROM ratings WHERE ratedID = u.userID) AS ratingCount,
        (SELECT 1 FROM swap o WHERE o.activeOfferedID = b.bookID) AS offeredElsewhere,
        s.status AS swapStatus, s.requesterID
    FROM books b
    JOIN collection c ON b.bookID = c.bookID
    JOIN users u ON c.userID = u.userID
    LEFT JOIN swap s ON s.activeBookID = b.bookID
    WHERE b.bookID = ?
", 'i', $bookID)->get_result()->fetch_assoc();

$isOwner = $book && $book['ownerID'] == $userID;
$isRequester = $book && $userID && $book['requesterID'] == $userID;
$canView = $book && (($book['showcase'] && !$book['suspended_at']) || $isOwner || $isRequester);

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

$myAddress = current_user()['address'] ?? null;
$isBusy = $book['swapStatus'] || $book['offeredElsewhere'];

// Books this person could offer in a two-way swap.
$myBooks = $userID && $book['listing_type'] === 'swap' && !$isOwner ? run("
    SELECT b.bookID, b.title
    FROM collection c JOIN books b ON c.bookID = b.bookID
    WHERE c.userID = ? AND c.showcase = 1 AND NOT " . BOOK_BUSY . "
    ORDER BY b.title
", 'i', $userID)->get_result()->fetch_all(MYSQLI_ASSOC) : [];

$pageTitle = $book['title'];
$navActive = $isOwner ? 'library' : 'browse';
include __DIR__ . '/../templates/top.php';
?>
<article class="book-detail">
    <div class="book-detail__cover"><?= book_cover($book, '(min-width: 960px) 300px, (min-width: 640px) 240px, 60vw') ?></div>
    <div class="book-detail__info">
        <h1><?= e($book['title']) ?></h1>
        <p class="book-detail__author">by <?= e($book['author']) ?></p>
        <p class="muted">Published <?= e($book['year_published']) ?></p>
        <?php if ($book['cover_credit'] === 'Open Library'): ?>
            <p class="help">Cover image from <a class="link" href="https://openlibrary.org/" rel="noopener">Open Library</a></p>
        <?php endif; ?>

        <a class="person" href="/profile.php?userID=<?= (int) $book['ownerID'] ?>">
            <?= avatar($book, 40) ?>
            <span><span class="muted">Shared by</span><strong><?= e($book['first_name'] . ' ' . $book['last_name']) ?></strong><span class="muted"><?= e(stars_label($book['ratingAverage'], $book['ratingCount'])) ?></span></span>
        </a>

        <span class="tag tag--<?= e($book['listing_type']) ?>"><?= e(LISTING_DESCRIPTIONS[$book['listing_type']]) ?></span>
        <ul class="book-facts">
            <?php if (location_label($book) !== ''): ?>
                <li><?= icon('pin') ?><?= e(location_label($book)) ?></li>
            <?php endif; ?>
            <li><?= icon('inbox') ?><?= e(HANDOVERS[$book['handover']]) ?></li>
        </ul>

        <div class="actions">
            <?php if ($isOwner): ?>
                <a class="btn btn--primary" href="/edit-book.php?id=<?= $bookID ?>">Edit</a>
                <form action="/actions/book-showcase.php" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="bookID" value="<?= $bookID ?>">
                    <input type="hidden" name="showcase" value="<?= $book['showcase'] ? 0 : 1 ?>">
                    <button type="submit" class="btn btn--ghost"><?= $book['showcase'] ? 'Make private' : 'Share this book' ?></button>
                </form>
                <form action="/actions/book-delete.php" method="post" data-confirm="Delete this book? This cannot be undone.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="bookID" value="<?= $bookID ?>">
                    <button type="submit" class="btn btn--danger">Delete</button>
                </form>
            <?php elseif ($isRequester): ?>
                <span class="tag tag--success"><?= $book['swapStatus'] === 'accepted' ? 'You are borrowing this book' : 'You requested this book' ?></span>
                <a class="btn btn--ghost" href="/requests.php">View request</a>
            <?php elseif ($isBusy): ?>
                <span class="tag">Not Available</span>
            <?php elseif (!$userID): ?>
                <a class="btn btn--primary" href="/signin.php?next=<?= urlencode("/book.php?id=$bookID") ?>">Sign in to request</a>
            <?php endif; ?>
        </div>
        <?php if ($isOwner && $book['removed_at']): ?>
            <p class="notice notice--error">An admin took this book down: <?= e($book['removed_reason']) ?></p>
        <?php endif; ?>
        <?php if ($userID && !$isOwner && !$isRequester && !$isBusy && $book['showcase']): ?>
            <form id="request" class="form request-form" action="/actions/swap-request.php" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="bookID" value="<?= $bookID ?>">
                <?php if ($book['listing_type'] === 'swap'): ?>
                    <div class="field">
                        <label for="offeredID">Which of your books would you swap for it?</label>
                        <?php if ($myBooks): ?>
                            <select id="offeredID" name="offeredID" required>
                                <?php foreach ($myBooks as $mine): ?>
                                    <option value="<?= (int) $mine['bookID'] ?>"><?= e($mine['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <p class="help">You need a shared, available book to offer. <a class="link" href="/add-book.php">Add a book</a> first.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($book['handover'] === 'both'): ?>
                    <fieldset class="choices">
                        <legend>How would you like to get it?</legend>
                        <label class="check"><input type="radio" name="handover" value="meet" checked> Meet in person</label>
                        <label class="check"><input type="radio" name="handover" value="courier"> Courier, you pay the cost</label>
                    </fieldset>
                <?php elseif ($book['handover'] === 'courier'): ?>
                    <p class="help">This owner only sends by courier. You pay the cost, and they'll see your delivery address if they accept.</p>
                <?php endif; ?>
                <?php if ($book['handover'] !== 'meet' && !$myAddress): ?>
                    <p class="help">For courier, first <a class="link" href="/settings.php">add your delivery address</a>.</p>
                <?php endif; ?>
                <div class="field">
                    <label for="message">Add a note <span class="optional">(optional)</span></label>
                    <textarea id="message" name="message" rows="2" maxlength="300" aria-describedby="message-help"></textarea>
                    <p class="help" id="message-help">For example: where and when you could meet. Please don't share personal details here.</p>
                </div>
                <div><button type="submit" class="btn btn--primary">Request this book</button></div>
            </form>
        <?php endif; ?>
        <?php if ($isOwner && !$book['showcase']): ?>
            <p class="help">Only you can see this book. Share it to let others request it.</p>
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
                    <?php if ($userID && $item['userID'] != $userID): ?>
                        <?= report_form('feedback', $item['feedbackID']) ?>
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

    <?php if (!$userID): ?>
        <p><a class="link" href="/signin.php?next=<?= urlencode("/book.php?id=$bookID") ?>">Sign in</a> to leave feedback.</p>
    <?php elseif (!$isOwner && $book['showcase']): ?>
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
<?php if ($userID && !$isOwner): ?>
    <section class="section"><?= report_form('book', $bookID) ?></section>
<?php endif; ?>
<?php include __DIR__ . '/../templates/bottom.php'; ?>
