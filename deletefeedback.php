<?php
require_once 'auth.php';

require_post('profile.php');

$feedbackID = (int) post('feedbackID');

$owner = run("
    SELECT c.userID FROM feedback f JOIN collection c ON f.bookID = c.bookID
    WHERE f.feedbackID = ? AND f.userID = ?
", 'ii', $feedbackID, $userID)->get_result()->fetch_assoc();

if (!$owner) {
    flash('You cannot delete that feedback.', 'profile.php');
}

run("DELETE FROM feedback WHERE feedbackID = ?", 'i', $feedbackID);
flash('Feedback deleted.', 'profile.php?userID=' . $owner['userID'], 'success');
