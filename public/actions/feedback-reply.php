<?php
require_once __DIR__ . '/../../src/auth.php';

require_post('/profile.php');

if (rate_limited("feedback:user:$userID", 30, 3600)) {
    flash('You are posting too quickly. Please try again later.', '/profile.php');
}

$feedbackID = (int) post('feedbackID');
$reply = post('reply');

$feedback = run("
    SELECT f.bookID FROM feedback f JOIN collection c ON f.bookID = c.bookID
    WHERE f.feedbackID = ? AND c.userID = ?
", 'ii', $feedbackID, $userID)->get_result()->fetch_assoc();

if (!$feedback) {
    flash('You cannot reply to that feedback.', '/profile.php');
}

$bookURL = '/book.php?id=' . $feedback['bookID'];
if ($reply === '' || mb_strlen($reply) > 500) {
    flash('Reply must be 1-500 characters.', $bookURL);
}

run("UPDATE feedback SET reply = ? WHERE feedbackID = ?", 'si', $reply, $feedbackID);
flash('Reply posted.', $bookURL, 'success');
