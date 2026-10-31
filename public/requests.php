<?php
require_once __DIR__ . '/../src/auth.php';

$rows = run("
    SELECT s.swapID, s.status, s.handover, s.message, s.ownerID, s.requesterID, b.bookID, b.title, b.author, b.cover_public_id,
        r.username AS requester, r.email AS requesterEmail, r.mobile AS requesterMobile, r.address AS requesterAddress, r.city AS requesterCity, r.area AS requesterArea,
        o.username AS owner, o.email AS ownerEmail, o.mobile AS ownerMobile, o.city AS ownerCity, o.area AS ownerArea
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
    $other = $isOwner ? 'requester' : 'owner';
    $where = location_label(['area' => $swap[$other . 'Area'], 'city' => $swap[$other . 'City']]);
    $status = $swap['status'];
    $isCourier = $swap['handover'] === 'courier';
    ?>
    <article class="swap">
        <a class="swap__cover" href="/book.php?id=<?= (int) $swap['bookID'] ?>" tabindex="-1" aria-hidden="true"><?= book_cover($swap, 120) ?></a>
        <div class="swap__body">
            <div class="swap__head">
                <h3><a href="/book.php?id=<?= (int) $swap['bookID'] ?>"><?= e($swap['title']) ?></a></h3>
                <span class="tag tag--<?= e($status) ?>"><?= e(ucfirst($status)) ?></span>
            </div>
            <p class="muted">
                <?= $isOwner ? 'Requested by' : 'Owned by' ?> <a class="link" href="/profile.php?userID=<?= (int) $swap[$other . 'ID'] ?>"><?= e($swap[$other]) ?></a><?php if ($where !== ''): ?>, <?= e($where) ?><?php endif; ?>
            </p>
            <p><b>Handover:</b> <?= e(HANDOVERS[$swap['handover']]) ?></p>
            <?php if ($swap['message']): ?>
                <blockquote class="swap__note"><?= e($swap['message']) ?></blockquote>
            <?php endif; ?>

            <?php if ($status === 'accepted'): ?>
                <!-- Contact details are shared only while a swap is accepted. A home address is shared only when a courier needs it. -->
                <div class="contact">
                    <h4><?= $isOwner ? 'Contact the borrower' : 'Contact the owner' ?></h4>
                    <a class="link" href="mailto:<?= e($swap[$other . 'Email']) ?>"><?= e($swap[$other . 'Email']) ?></a>
                    <?php if ($swap[$other . 'Mobile']): ?>
                        <a class="link" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $swap[$other . 'Mobile'])) ?>"><?= e($swap[$other . 'Mobile']) ?></a>
                    <?php endif; ?>
                    <?php if ($isOwner && $isCourier): ?>
                        <p><b>Send to:</b> <?= e($swap['requesterAddress'] ?: 'They have not added an address yet. Please ask them for it.') ?></p>
                        <p class="help">The borrower pays the courier. Agree on the cost before you send the book.</p>
                    <?php elseif ($isCourier): ?>
                        <p class="help">The owner can see your delivery address. You pay the courier, so agree on the cost before they send it.</p>
                    <?php else: ?>
                        <p class="help">Stay safe: meet in a public place like a campus, library or café, and tell a friend where you're going.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="actions">
                <?php if ($status === 'pending' && $isOwner): ?>
                    <?= swap_button($swap['swapID'], 'accept', 'Accept') ?>
                    <?= swap_button($swap['swapID'], 'decline', 'Decline', 'btn--ghost') ?>
                    <p class="help">Accepting shares your contact details with them<?= $isCourier ? ', and shows you their delivery address' : '' ?>.</p>
                <?php elseif ($status === 'pending'): ?>
                    <?= swap_button($swap['swapID'], 'cancel', 'Cancel request', 'btn--ghost') ?>
                    <p class="help">Waiting for the owner. We'll email you when they answer.</p>
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
