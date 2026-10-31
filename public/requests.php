<?php
require_once __DIR__ . '/../src/auth.php';

$rows = run("
    SELECT s.swapID, s.status, s.ownerID, s.requesterID, b.bookID, b.title, b.author, b.cover_public_id,
        r.username AS requester, r.email AS requesterEmail,
        o.username AS owner, o.email AS ownerEmail
    FROM swap s
    JOIN books b ON s.bookID = b.bookID
    JOIN users r ON s.requesterID = r.userID
    JOIN users o ON s.ownerID = o.userID
    WHERE s.ownerID = ? OR s.requesterID = ?
    ORDER BY s.swapID DESC
", 'ii', $userID, $userID)->get_result()->fetch_all(MYSQLI_ASSOC);

$incoming = array_filter($rows, fn($row) => $row['ownerID'] == $userID);
$outgoing = array_filter($rows, fn($row) => $row['requesterID'] == $userID);

function swap_button($swapID, $action, $label, $class = 'btn--primary') {
    return '<form action="/actions/swap-update.php" method="post">' . csrf_field()
        . '<input type="hidden" name="swapID" value="' . (int) $swapID . '">'
        . '<input type="hidden" name="action" value="' . $action . '">'
        . '<button type="submit" class="btn ' . $class . '">' . $label . '</button></form>';
}

function swap_card($swap, $isOwner) {
    $otherID = $isOwner ? $swap['requesterID'] : $swap['ownerID'];
    $otherName = $isOwner ? $swap['requester'] : $swap['owner'];
    $otherEmail = $isOwner ? $swap['requesterEmail'] : $swap['ownerEmail'];
    $status = $swap['status'];
    ?>
    <article class="swap">
        <a class="swap__cover" href="/book.php?id=<?= (int) $swap['bookID'] ?>" tabindex="-1" aria-hidden="true"><?= book_cover($swap, 120) ?></a>
        <div class="swap__body">
            <div class="swap__head">
                <h3><a href="/book.php?id=<?= (int) $swap['bookID'] ?>"><?= e($swap['title']) ?></a></h3>
                <span class="tag tag--<?= e($status) ?>"><?= e(ucfirst($status)) ?></span>
            </div>
            <p class="muted"><?= $isOwner ? 'Requested by' : 'Owned by' ?> <a class="link" href="/profile.php?userID=<?= (int) $otherID ?>"><?= e($otherName) ?></a></p>

            <?php if ($status === 'accepted'): ?>
                <!-- Only people in an accepted swap can see each other's email, so they can arrange the handover. -->
                <p><a class="link link--icon" href="mailto:<?= e($otherEmail) ?>"><?= icon('mail') ?><?= e($otherEmail) ?></a></p>
            <?php endif; ?>

            <div class="actions">
                <?php if ($status === 'pending' && $isOwner): ?>
                    <?= swap_button($swap['swapID'], 'accept', 'Accept') ?>
                    <?= swap_button($swap['swapID'], 'decline', 'Decline', 'btn--ghost') ?>
                <?php elseif ($status === 'pending'): ?>
                    <?= swap_button($swap['swapID'], 'cancel', 'Cancel request', 'btn--ghost') ?>
                <?php elseif ($status === 'accepted' && $isOwner): ?>
                    <?= swap_button($swap['swapID'], 'return', 'Mark as returned') ?>
                <?php elseif ($status === 'accepted'): ?>
                    <p class="help">The owner marks this as returned when they get the book back.</p>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
}

$pageTitle = 'Requests';
$navActive = 'requests';
include __DIR__ . '/../templates/top.php';
?>
<div class="page-head"><h1>Requests</h1></div>

<?php if (!$rows): ?>
    <div class="empty">
        <h2>No swap requests yet</h2>
        <p class="muted">When you request a book, or someone requests yours, it shows up here.</p>
        <a class="btn btn--primary" href="/home.php?available=1">Find a book</a>
    </div>
<?php endif; ?>

<?php if ($incoming): ?>
    <section class="section">
        <h2>Requests for your books</h2>
        <div class="swap-list"><?php foreach ($incoming as $swap) { swap_card($swap, true); } ?></div>
    </section>
<?php endif; ?>

<?php if ($outgoing): ?>
    <section class="section">
        <h2>Books you requested</h2>
        <div class="swap-list"><?php foreach ($outgoing as $swap) { swap_card($swap, false); } ?></div>
    </section>
<?php endif; ?>
<?php include __DIR__ . '/../templates/bottom.php'; ?>
