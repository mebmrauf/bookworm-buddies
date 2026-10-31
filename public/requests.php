<?php
require_once __DIR__ . '/../src/auth.php';

$rows = run("
    SELECT s.swapID, s.status, s.kind, s.handover, s.message, s.ownerID, s.requesterID, s.due_date, s.owner_confirmed, s.requester_confirmed,
        s.offered_bookID, offered.title AS offeredTitle, b.bookID, b.title, b.author, b.cover_public_id,
        (SELECT rt.stars FROM ratings rt WHERE rt.swapID = s.swapID AND rt.raterID = ?) AS myStars,
        (SELECT COUNT(*) FROM messages m WHERE m.swapID = s.swapID) AS messageCount,
        (SELECT COUNT(*) FROM messages m WHERE m.swapID = s.swapID AND m.read_at IS NULL AND m.senderID != ?) AS unread,
        r.username AS requester, r.address AS requesterAddress, r.city AS requesterCity, r.area AS requesterArea,
        o.username AS owner, o.city AS ownerCity, o.area AS ownerArea
    FROM swap s
    JOIN books b ON s.bookID = b.bookID
    JOIN users r ON s.requesterID = r.userID
    JOIN users o ON s.ownerID = o.userID
    LEFT JOIN books offered ON s.offered_bookID = offered.bookID
    WHERE s.ownerID = ? OR s.requesterID = ?
    ORDER BY s.status = 'pending' DESC, s.status = 'accepted' DESC, s.swapID DESC
    LIMIT 100
", 'iiii', $userID, $userID, $userID, $userID)->get_result()->fetch_all(MYSQLI_ASSOC);

$incoming = array_filter($rows, fn($row) => $row['ownerID'] == $userID);
$outgoing = array_filter($rows, fn($row) => $row['requesterID'] == $userID);

function swap_button($swapID, $action, $label, $class = 'btn--primary') {
    return '<form action="/actions/swap-update.php" method="post">' . csrf_field()
        . '<input type="hidden" name="swapID" value="' . (int) $swapID . '">'
        . '<input type="hidden" name="action" value="' . $action . '">'
        . '<button type="submit" class="btn ' . $class . '">' . $label . '</button></form>';
}

function rating_form($swapID, $otherName) {
    ob_start();
    ?>
    <details class="feedback__respond" open>
        <summary>Rate <?= e($otherName) ?></summary>
        <form class="form" action="/actions/rate.php" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="swapID" value="<?= (int) $swapID ?>">
            <fieldset class="star-choice">
                <legend>How did it go?</legend>
                <?php for ($stars = 1; $stars <= 5; $stars++): ?>
                    <label><input class="visually-hidden" type="radio" name="stars" value="<?= $stars ?>" required><span aria-hidden="true">★</span><span class="visually-hidden"><?= $stars ?> out of 5 stars</span></label>
                <?php endfor; ?>
            </fieldset>
            <div class="field">
                <label for="rating-comment-<?= (int) $swapID ?>">Add a comment <span class="optional">(optional)</span></label>
                <textarea id="rating-comment-<?= (int) $swapID ?>" name="comment" rows="2" maxlength="300"></textarea>
            </div>
            <div><button type="submit" class="btn btn--ghost">Send rating</button></div>
        </form>
    </details>
    <?php
    return ob_get_clean();
}

function due_form($swapID, $action, $label, $value, $class = 'btn--primary') {
    $min = date('Y-m-d', strtotime('+1 day'));
    $max = date('Y-m-d', strtotime('+' . MAX_LOAN_DAYS . ' days'));
    return '<form class="due-form" action="/actions/swap-update.php" method="post">' . csrf_field()
        . '<input type="hidden" name="swapID" value="' . (int) $swapID . '">'
        . '<input type="hidden" name="action" value="' . $action . '">'
        . '<label for="due-' . $action . (int) $swapID . '">Return by</label>'
        . '<input id="due-' . $action . (int) $swapID . '" type="date" name="due_date" min="' . $min . '" max="' . $max . '" value="' . e($value) . '" required>'
        . '<button type="submit" class="btn ' . $class . '">' . $label . '</button></form>';
}

function swap_card($swap, $isOwner) {
    $other = $isOwner ? 'requester' : 'owner';
    $where = location_label(['area' => $swap[$other . 'Area'], 'city' => $swap[$other . 'City']]);
    $status = $swap['status'];
    $isCourier = $swap['handover'] === 'courier';
    $kindLabel = ['lend' => 'Loan', 'give' => 'Giveaway', 'swap' => 'Two-way swap'][$swap['kind']];
    $overdue = $status === 'accepted' && $swap['due_date'] && $swap['due_date'] < date('Y-m-d');
    $myConfirm = $isOwner ? $swap['owner_confirmed'] : $swap['requester_confirmed'];
    ?>
    <article class="swap">
        <a class="swap__cover" href="/book.php?id=<?= (int) $swap['bookID'] ?>" tabindex="-1" aria-hidden="true"><?= book_cover($swap, '88px') ?></a>
        <div class="swap__body">
            <div class="swap__head">
                <h3><a href="/book.php?id=<?= (int) $swap['bookID'] ?>"><?= e($swap['title']) ?></a></h3>
                <span class="tag tag--<?= $overdue ? 'overdue' : e($status) ?>"><?= $overdue ? 'Overdue' : e(ucfirst($status)) ?></span>
            </div>
            <p class="muted">
                <?= $isOwner ? 'Requested by' : 'Owned by' ?> <a class="link" href="/profile.php?userID=<?= (int) $swap[$other . 'ID'] ?>"><?= e($swap[$other]) ?></a><?php if ($where !== ''): ?>, <?= e($where) ?><?php endif; ?>
            </p>
            <p><b><?= e($kindLabel) ?>:</b> <?= e(HANDOVERS[$swap['handover']]) ?></p>
            <?php if ($swap['kind'] === 'swap'): ?>
                <p><b><?= $isOwner ? "They'd give you:" : "You'd give them:" ?></b> <?= $swap['offered_bookID'] ? '<a class="link" href="/book.php?id=' . (int) $swap['offered_bookID'] . '">' . e($swap['offeredTitle']) . '</a>' : 'A book that was later removed' ?></p>
            <?php endif; ?>
            <?php if ($swap['due_date'] && $status === 'accepted'): ?>
                <p><b>Return by:</b> <?= e(date('j F Y', strtotime($swap['due_date']))) ?></p>
            <?php endif; ?>
            <?php if ($swap['message']): ?>
                <blockquote class="swap__note"><?= e($swap['message']) ?></blockquote>
            <?php endif; ?>

            <?php if ($status === 'accepted'): ?>
                <!-- People arrange the handover in chat. A home address is shared only when a courier needs it. -->
                <div class="contact">
                    <a class="btn btn--primary" href="/chat.php?swap=<?= (int) $swap['swapID'] ?>">Open chat<?php if ($swap['unread'] > 0): ?> <span class="badge" aria-label="<?= (int) $swap['unread'] ?> new"><?= (int) $swap['unread'] ?></span><?php endif; ?></a>
                    <?php if ($isOwner && $isCourier): ?>
                        <p><b>Send to:</b> <?= e($swap['requesterAddress'] ?: 'They have not added an address yet. Please ask them for it in chat.') ?></p>
                        <p class="help">The borrower pays the courier. Agree on the cost before you send the book.</p>
                    <?php elseif ($isCourier): ?>
                        <p class="help">The owner can see your delivery address. You pay the courier, so agree on the cost in chat before they send it.</p>
                    <?php else: ?>
                        <p class="help">Agree on the place and time in chat. Stay safe: meet in a public place like a campus, library or café, and tell a friend where you're going.</p>
                    <?php endif; ?>
                </div>
            <?php elseif ($swap['messageCount'] > 0): ?>
                <p><a class="link" href="/chat.php?swap=<?= (int) $swap['swapID'] ?>">View past messages<?= $swap['unread'] > 0 ? ' (' . (int) $swap['unread'] . ' new)' : '' ?></a></p>
            <?php endif; ?>

            <div class="actions">
                <?php if ($status === 'pending' && $isOwner): ?>
                    <?php if ($swap['kind'] === 'lend'): ?>
                        <?= due_form($swap['swapID'], 'accept', 'Accept', date('Y-m-d', strtotime('+' . DEFAULT_LOAN_DAYS . ' days'))) ?>
                    <?php else: ?>
                        <?= swap_button($swap['swapID'], 'accept', 'Accept') ?>
                    <?php endif; ?>
                    <?= swap_button($swap['swapID'], 'decline', 'Decline', 'btn--ghost') ?>
                    <p class="help">Accepting opens a private chat with them<?= $isCourier ? ', and shows you their delivery address' : '' ?>.</p>
                <?php elseif ($status === 'pending'): ?>
                    <?= swap_button($swap['swapID'], 'cancel', 'Cancel request', 'btn--ghost') ?>
                    <p class="help">Waiting for the owner. We'll email you when they answer.</p>
                <?php elseif ($status === 'accepted' && $swap['kind'] === 'lend' && $isOwner): ?>
                    <?= swap_button($swap['swapID'], 'return', 'Mark as returned') ?>
                    <?= due_form($swap['swapID'], 'extend', 'Change date', $swap['due_date'] ?? date('Y-m-d', strtotime('+' . DEFAULT_LOAN_DAYS . ' days')), 'btn--ghost') ?>
                <?php elseif ($status === 'accepted' && $swap['kind'] === 'lend'): ?>
                    <p class="help">The owner marks this as returned when they get the book back.</p>
                <?php elseif ($status === 'accepted' && $swap['kind'] === 'give' && $isOwner): ?>
                    <?= swap_button($swap['swapID'], 'complete', 'Mark as given') ?>
                <?php elseif ($status === 'accepted' && $swap['kind'] === 'give'): ?>
                    <p class="help">The book becomes yours once the owner marks it as given.</p>
                <?php elseif ($status === 'accepted' && $swap['kind'] === 'swap' && !$myConfirm): ?>
                    <?= swap_button($swap['swapID'], 'complete', "We've exchanged books") ?>
                    <p class="help">Each book moves to its new owner once you've both confirmed.</p>
                <?php elseif ($status === 'accepted' && $swap['kind'] === 'swap'): ?>
                    <p class="help">Thanks. We're waiting for the other person to confirm.</p>
                <?php endif; ?>
            </div>
            <?php if (in_array($status, ['returned', 'completed'], true)): ?>
                <?php if ($swap['myStars']): ?>
                    <p class="help">You rated this exchange <?= (int) $swap['myStars'] ?> out of 5.</p>
                <?php else: ?>
                    <?= rating_form($swap['swapID'], $swap[$other]) ?>
                <?php endif; ?>
            <?php endif; ?>
            <div>
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
