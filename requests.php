<?php
require_once 'auth.php';

$swaps = run("
    SELECT s.swapID, s.status, s.ownerID, s.requesterID, b.title,
        r.username AS requester, r.email AS requesterEmail,
        o.username AS owner, o.email AS ownerEmail
    FROM swap s
    JOIN books b ON s.bookID = b.bookID
    JOIN users r ON s.requesterID = r.userID
    JOIN users o ON s.ownerID = o.userID
    WHERE s.ownerID = ? OR s.requesterID = ?
    ORDER BY s.swapID DESC
", 'ii', $userID, $userID)->get_result();

function swap_button($swapID, $action, $label, $class = 'btn-primary') {
    return '<form action="updateswap.php" method="post" class="inline-form">' . csrf_field()
        . '<input type="hidden" name="swapID" value="' . (int) $swapID . '">'
        . '<input type="hidden" name="action" value="' . $action . '">'
        . '<button type="submit" class="' . $class . '">' . $label . '</button></form>';
}

$pageTitle = 'Swap Requests';
include 'header.php';
?>
<div class="wholebody">
    <div class="collections">
        <h1>Swap Requests</h1>
        <?php while ($swap = $swaps->fetch_assoc()):
            $isOwner = $swap['ownerID'] == $userID; ?>
            <div class="swap-request">
                <h3><?= e($swap['title']) ?></h3>
                <p><b>Requested by:</b> <a class="link" href="profile.php?userID=<?= $swap['requesterID'] ?>"><?= e($swap['requester']) ?></a></p>
                <p><b>Owner:</b> <a class="link" href="profile.php?userID=<?= $swap['ownerID'] ?>"><?= e($swap['owner']) ?></a></p>
                <p><b>Status:</b> <?= e(ucfirst($swap['status'])) ?></p>

                <?php if ($swap['status'] === 'pending' && $isOwner): ?>
                    <?= swap_button($swap['swapID'], 'accept', 'Accept') ?>
                    <?= swap_button($swap['swapID'], 'decline', 'Decline', 'btn-primary btn-danger') ?>
                <?php elseif ($swap['status'] === 'pending'): ?>
                    <p>Waiting for the owner's response...</p>
                    <?= swap_button($swap['swapID'], 'cancel', 'Cancel Request', 'btn-primary btn-danger') ?>
                <?php elseif ($swap['status'] === 'accepted'): ?>
                    <!-- Only people in an accepted swap can see each other's email, so they can arrange the handover. -->
                    <p><b>Contact:</b> <?= e($isOwner ? $swap['requesterEmail'] : $swap['ownerEmail']) ?></p>
                    <?php if ($isOwner): ?>
                        <?= swap_button($swap['swapID'], 'return', 'Mark as Returned') ?>
                    <?php else: ?>
                        <p>The owner marks this as returned when they get the book back.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
        <?php if ($swaps->num_rows === 0): ?>
            <p>No swap requests yet.</p>
        <?php endif; ?>
    </div>
</div>
<?php include 'footer.php'; ?>
