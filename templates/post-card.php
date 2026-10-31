<?php
// Expects $post and $covers from post_covers(). $headingTag keeps the page's heading order right.
$postURL = '/post.php?id=' . (int) $post['postID'];
$picture = cloudinary_enabled() ? ($post['image_public_id'] ?? null) ?: ($covers[mb_strtolower($post['book_title'])] ?? null) : null;
?>
<article class="post-card">
    <?php if ($picture): ?>
        <a class="post-card__picture" href="<?= $postURL ?>" tabindex="-1" aria-hidden="true"><img src="<?= e(image_url($picture, 200, 300, 'c_fill,g_auto')) ?>" width="200" height="300" alt="" loading="lazy" decoding="async"></a>
    <?php endif; ?>
    <div class="post-card__body">
        <<?= $headingTag ?>><a href="<?= $postURL ?>"><?= e($post['title']) ?></a></<?= $headingTag ?>>
        <p class="muted">On <?= e($post['book_title']) ?> &middot; by <a class="link" href="/profile.php?userID=<?= (int) $post['userID'] ?>"><?= e($post['username']) ?></a> &middot; <?= e(date('j M Y', strtotime($post['created_at']))) ?></p>
        <p class="post-card__excerpt"><?= e($post['excerpt']) ?></p>
        <a class="link post-card__more" href="<?= $postURL ?>" aria-label="Read the full post: <?= e($post['title']) ?>">Read the full post</a>
    </div>
</article>
