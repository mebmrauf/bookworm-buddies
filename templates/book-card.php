<?php
// Expects $book. Optional keys: username + ownerID (show owner), city/area (show where), unavailable (show state), showcase (show visibility).
$bookURL = '/book.php?id=' . (int) $book['bookID'];
$where = location_label($book);
?>
<article class="book-card">
    <a class="book-card__link" href="<?= $bookURL ?>">
        <?= book_cover($book) ?>
        <h3 class="book-card__title"><?= e($book['title']) ?></h3>
    </a>
    <p class="book-card__meta"><?= e($book['author']) ?> &middot; <?= e($book['year_published']) ?></p>
    <?php if ($where !== ''): ?>
        <p class="book-card__meta book-card__where"><?= icon('pin') ?><?= e($where) ?></p>
    <?php endif; ?>
    <?php if (isset($book['listing_type'])): ?>
        <span class="tag book-card__type tag--<?= e($book['listing_type']) ?>"><?= e(['lend' => 'To borrow', 'give' => 'Free to keep', 'swap' => 'To swap'][$book['listing_type']]) ?></span>
    <?php endif; ?>
    <?php if (isset($book['username'])): ?>
        <p class="book-card__meta">Shared by <a class="link" href="/profile.php?userID=<?= (int) $book['ownerID'] ?>"><?= e($book['username']) ?></a></p>
    <?php endif; ?>
    <?php if (isset($book['showcase'])): ?>
        <span class="tag<?= $book['showcase'] ? ' tag--success' : '' ?>"><?= $book['showcase'] ? 'Shared' : 'Private' ?></span>
    <?php endif; ?>
    <?php if (isset($book['unavailable'])): ?>
        <div class="book-card__action">
            <?php if ($book['unavailable']): ?>
                <span class="tag">Not Available</span>
            <?php else: ?>
                <a class="btn btn--primary btn--block" href="<?= $bookURL ?>#request">Request</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</article>
