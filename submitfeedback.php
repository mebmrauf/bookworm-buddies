<?php
require_once 'auth.php';

require_post('home.php');

$bookID = (int) post('bookID');
$comment = post('comment');

$owner = run("SELECT userID FROM collection WHERE bookID = ? AND showcase = 1", 'i', $bookID)->get_result()->fetch_assoc();
if (!$owner) {
    flash('That book was not found.', 'home.php');
}

$ownerURL = 'profile.php?userID=' . $owner['userID'];
if ($owner['userID'] == $userID) {
    flash('You cannot leave feedback on your own book.', $ownerURL);
}
if ($comment === '' || mb_strlen($comment) > 500) {
    flash('Feedback must be 1-500 characters.', $ownerURL);
}

run("INSERT INTO feedback (userID, bookID, comment) VALUES (?, ?, ?)", 'iis', $userID, $bookID, $comment);
flash('Feedback added.', $ownerURL, 'success');
