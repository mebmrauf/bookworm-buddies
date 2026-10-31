<?php
require_once __DIR__ . '/../../src/auth.php';

require_post('/home.php');

$bookID = (int) post('bookID');
$message = post('message');

if (rate_limited("swap:user:$userID", 30, 86400)) {
    flash('You have sent a lot of requests today. Please try again tomorrow.', '/home.php');
}

$book = run("
    SELECT b.title, b.handover, b.listing_type, u.userID, u.email, u.first_name
    FROM collection c JOIN books b ON c.bookID = b.bookID JOIN users u ON c.userID = u.userID
    WHERE c.bookID = ? AND " . VISIBLE_BOOK,
    'i', $bookID
)->get_result()->fetch_assoc();

if (!$book) {
    flash('That book is not available.', '/home.php?available=1');
}
if ($book['userID'] == $userID) {
    flash('You cannot request your own book.', '/home.php?available=1');
}

$bookURL = "/book.php?id=$bookID";
// When the owner offers one method, that's the method; otherwise the requester picks.
$handover = $book['handover'] === 'both' ? post('handover') : $book['handover'];
if (!in_array($handover, ['meet', 'courier'], true)) {
    flash('Please choose how you would like to get this book.', $bookURL);
}
if (mb_strlen($message) > 300) {
    flash('Your note can be at most 300 characters.', $bookURL);
}

$me = run("SELECT username, address FROM users WHERE userID = ?", 'i', $userID)->get_result()->fetch_assoc();
if ($handover === 'courier' && !$me['address']) {
    flash('Please add your delivery address first. The owner sees it only if they accept a courier request.', '/settings.php');
}

$busy = fn($id) => run("SELECT 1 FROM books b WHERE b.bookID = ? AND " . BOOK_BUSY, 'i', $id)->get_result()->num_rows;
if ($busy($bookID)) {
    flash('That book is not available.', '/home.php?available=1');
}

$offeredID = null;
$offeredTitle = null;
if ($book['listing_type'] === 'swap') {
    $offeredID = (int) post('offeredID');
    $offered = run("
        SELECT b.title FROM collection c JOIN books b ON c.bookID = b.bookID
        WHERE c.bookID = ? AND c.userID = ? AND c.showcase = 1
    ", 'ii', $offeredID, $userID)->get_result()->fetch_assoc();
    if (!$offered || $busy($offeredID)) {
        flash('Please choose one of your shared, available books to offer.', $bookURL);
    }
    $offeredTitle = $offered['title'];
}

try {
    run(
        "INSERT INTO swap (requesterID, ownerID, bookID, status, kind, offered_bookID, handover, message) VALUES (?, ?, ?, 'pending', ?, ?, ?, ?)",
        'iiisiss', $userID, $book['userID'], $bookID, $book['listing_type'], $offeredID, $handover, $message === '' ? null : $message
    );
} catch (mysqli_sql_exception $ex) {
    if ($ex->getCode() !== DUPLICATE_KEY) {
        throw $ex;
    }
    flash('That book is not available.', '/home.php?available=1');
}

$wants = [
    'lend' => "would like to borrow {$book['title']}",
    'give' => "would like to take {$book['title']}, which you're giving away",
    'swap' => "would like to swap {$offeredTitle} for {$book['title']}",
][$book['listing_type']];
notify($book['email'], $book['first_name'], "New request for {$book['title']}", "{$me['username']} $wants (" . HANDOVERS[$handover] . ').', $message ?: null);
flash('Request sent. We emailed the owner, and you will hear back here and by email.', '/requests.php', 'success');
