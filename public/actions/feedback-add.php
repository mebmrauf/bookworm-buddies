<?php
require_once __DIR__ . '/../../src/auth.php';

require_post('/home.php');

$bookID = (int) post('bookID');
$comment = post('comment');

$owner = run("SELECT c.userID FROM collection c JOIN users u ON c.userID = u.userID WHERE c.bookID = ? AND " . VISIBLE_BOOK, 'i', $bookID)->get_result()->fetch_assoc();
if (!$owner) {
    flash('That book was not found.', '/home.php');
}

$bookURL = "/book.php?id=$bookID";
if ($owner['userID'] == $userID) {
    flash('You cannot leave feedback on your own book.', $bookURL);
}
if ($comment === '' || mb_strlen($comment) > 500) {
    flash('Feedback must be 1-500 characters.', $bookURL);
}

run("INSERT INTO feedback (userID, bookID, comment) VALUES (?, ?, ?)", 'iis', $userID, $bookID, $comment);
flash('Thanks for your feedback.', $bookURL, 'success');
