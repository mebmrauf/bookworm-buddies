<?php
require_once __DIR__ . '/../../src/auth.php';

require_post('/home.php');

$bookID = (int) post('bookID');
$message = post('message');

$book = run("
    SELECT b.title, b.handover, u.userID, u.email, u.first_name
    FROM collection c JOIN books b ON c.bookID = b.bookID JOIN users u ON c.userID = u.userID
    WHERE c.bookID = ? AND c.showcase = 1
", 'i', $bookID)->get_result()->fetch_assoc();

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

try {
    run("INSERT INTO swap (requesterID, ownerID, bookID, status, handover, message) VALUES (?, ?, ?, 'pending', ?, ?)", 'iiiss', $userID, $book['userID'], $bookID, $handover, $message === '' ? null : $message);
} catch (mysqli_sql_exception $ex) {
    if ($ex->getCode() !== DUPLICATE_KEY) {
        throw $ex;
    }
    flash('That book is not available.', '/home.php?available=1');
}

notify($book['email'], $book['first_name'], "New request for {$book['title']}", "{$me['username']} would like to borrow {$book['title']} (" . HANDOVERS[$handover] . ').', $message ?: null);
flash('Request sent. We emailed the owner, and you will hear back here and by email.', '/requests.php', 'success');
