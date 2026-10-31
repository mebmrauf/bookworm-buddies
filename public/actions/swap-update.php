<?php
require_once __DIR__ . '/../../src/auth.php';

require_post('/requests.php');

$swapID = (int) post('swapID');
$action = post('action');

$swap = run("
    SELECT s.*, b.title, r.email AS requesterEmail, r.first_name AS requesterName, o.email AS ownerEmail, o.first_name AS ownerName
    FROM swap s
    JOIN books b ON s.bookID = b.bookID
    JOIN users r ON s.requesterID = r.userID
    JOIN users o ON s.ownerID = o.userID
    WHERE s.swapID = ?
", 'i', $swapID)->get_result()->fetch_assoc();

$refuse = fn() => flash('You cannot do that for this request.', '/requests.php');
if (!$swap) {
    $refuse();
}
$isOwner = $swap['ownerID'] == $userID;
$isRequester = $swap['requesterID'] == $userID;
$title = $swap['title'];

// Rechecks the status in the query, so two clicks at once can't both succeed.
$move = fn($from, $to) => run("UPDATE swap SET status = ? WHERE swapID = ? AND status = ?", 'sis', $to, $swapID, $from)->affected_rows === 1;

// Hands the book over for good. New owners start with it private, so they decide what to do next.
function transfer_book($bookID, $toUserID) {
    run("UPDATE collection c JOIN users u ON u.userID = ? SET c.userID = u.userID, c.showcase = 0, c.city = u.city WHERE c.bookID = ?", 'ii', $toUserID, $bookID);
}

$rateNote = fn($name, $email) => notify($email, $name, "Rate your exchange: $title", "The exchange for $title is finished. Please rate it, so the community knows who to trust.");

switch ($action) {
    case 'cancel':
        if (!$isRequester || $swap['status'] !== 'pending') {
            $refuse();
        }
        run("DELETE FROM swap WHERE swapID = ? AND status = 'pending'", 'i', $swapID);
        // A withdrawn request stops counting, unless this reader also asked for the book before.
        if (!run("SELECT 1 FROM swap WHERE bookID = ? AND requesterID = ? LIMIT 1", 'ii', $swap['bookID'], $userID)->get_result()->num_rows) {
            run("UPDATE collection SET request_count = GREATEST(request_count - 1, 0) WHERE bookID = ?", 'i', $swap['bookID']);
        }
        break;

    case 'decline':
        if (!$isOwner || !$move('pending', 'declined')) {
            $refuse();
        }
        notify($swap['requesterEmail'], $swap['requesterName'], "Update on $title", "Your request for $title was declined this time. There are plenty of other books to explore.");
        break;

    case 'accept':
        if (!$isOwner || $swap['status'] !== 'pending') {
            $refuse();
        }
        $due = null;
        if ($swap['kind'] === 'lend') {
            $due = post('due_date') ?: date('Y-m-d', strtotime('+' . DEFAULT_LOAN_DAYS . ' days'));
            $error = validate_due_date($due);
            if ($error) {
                flash($error, '/requests.php');
            }
        }
        run("UPDATE swap SET status = 'accepted', due_date = ? WHERE swapID = ? AND status = 'pending'", 'si', $due, $swapID);
        $when = $due ? ' Please return it by ' . date('j F Y', strtotime($due)) . '.' : '';
        notify($swap['requesterEmail'], $swap['requesterName'], "Accepted: $title", "Good news! Your request for $title was accepted.$when Open your requests to chat with the owner and arrange the handover.");
        break;

    case 'extend':
        if (!$isOwner || $swap['status'] !== 'accepted' || $swap['kind'] !== 'lend') {
            $refuse();
        }
        $due = post('due_date');
        $error = validate_due_date($due);
        if ($error) {
            flash($error, '/requests.php');
        }
        run("UPDATE swap SET due_date = ?, reminded_due_at = NULL, reminded_overdue_at = NULL WHERE swapID = ?", 'si', $due, $swapID);
        notify($swap['requesterEmail'], $swap['requesterName'], "New return date: $title", "The new return date for $title is " . date('j F Y', strtotime($due)) . '.');
        break;

    case 'return':
        if (!$isOwner || $swap['kind'] !== 'lend' || !$move('accepted', 'returned')) {
            $refuse();
        }
        $rateNote($swap['requesterName'], $swap['requesterEmail']);
        $rateNote($swap['ownerName'], $swap['ownerEmail']);
        break;

    case 'complete':
        if ($swap['status'] !== 'accepted' || $swap['kind'] === 'lend') {
            $refuse();
        }
        if ($swap['kind'] === 'give') {
            if (!$isOwner) {
                $refuse();
            }
            $conn->begin_transaction();
            $move('accepted', 'completed');
            transfer_book($swap['bookID'], $swap['requesterID']);
            $conn->commit();
        } else {
            // In a two-way swap each person gives up a book, so both must confirm before ownership changes.
            if (!$isOwner && !$isRequester) {
                $refuse();
            }
            $column = $isOwner ? 'owner_confirmed' : 'requester_confirmed';
            run("UPDATE swap SET $column = 1 WHERE swapID = ?", 'i', $swapID);
            $otherConfirmed = $isOwner ? $swap['requester_confirmed'] : $swap['owner_confirmed'];
            if (!$otherConfirmed) {
                if ($isOwner) {
                    notify($swap['requesterEmail'], $swap['requesterName'], "Please confirm your swap: $title", "The owner confirmed the swap for $title. Please confirm once you have your book too.");
                } else {
                    notify($swap['ownerEmail'], $swap['ownerName'], "Please confirm your swap: $title", "The other reader confirmed the swap for $title. Please confirm once you have your book too.");
                }
                flash('Thanks. We are waiting for the other person to confirm.', '/requests.php', 'success');
            }
            $conn->begin_transaction();
            $move('accepted', 'completed');
            transfer_book($swap['bookID'], $swap['requesterID']);
            if ($swap['offered_bookID']) {
                transfer_book($swap['offered_bookID'], $swap['ownerID']);
            }
            $conn->commit();
        }
        $rateNote($swap['requesterName'], $swap['requesterEmail']);
        $rateNote($swap['ownerName'], $swap['ownerEmail']);
        flash('All done! The books are on their new shelves, private until shared. Please rate the exchange.', '/requests.php', 'success');
        break;

    default:
        $refuse();
}

flash('Request updated.', '/requests.php', 'success');
