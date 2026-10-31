<?php
// Expects $book. Optional keys: username + ownerID (show owner), unavailable (show request state), showcase (show visibility).
?>
<article class="book-card">
    <a class="book-card__link" href="/book.php?id=<?= (int) $book['bookID'] ?>">
        <?= book_cover($book) ?>
        <h3 class="book-card__title"><?= e($book['title']) ?></h3>
    </a>
    <p class="book-card__meta"><?= e($book['author']) ?> &middot; <?= e($book['year_published']) ?></p>
    <?php if (isset($book['username'])): ?>
        <p class="book-card__meta">Shared by <a class="link" href="/profile.php?userID=<?= (int) $book['ownerID'] ?>"><?= e($book['username']) ?></a></p>
    <?php endif; ?>
    <?php if (isset($book['showcase'])): ?>
        <span class="tag<?= $book['showcase'] ? ' tag--success' : '' ?>"><?= $book['showcase'] ? 'Showcased' : 'Only you can see this' ?></span>
    <?php endif; ?>
    <?php if (isset($book['unavailable'])): ?>
        <div class="book-card__action">
            <?php if ($book['unavailable']): ?>
                <span class="tag">Not Available</span>
            <?php else: ?>
                <form action="/actions/swap-request.php" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="bookID" value="<?= (int) $book['bookID'] ?>">
                    <button type="submit" class="btn btn--primary btn--block">Request</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</article>
