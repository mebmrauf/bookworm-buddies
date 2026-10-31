<?php
require_once 'auth.php';

require_post('profile.php');

$feedbackID = (int) post('feedbackID');
$reply = post('reply');

$allowed = run("
    SELECT 1 FROM feedback f JOIN collection c ON f.bookID = c.bookID
    WHERE f.feedbackID = ? AND c.userID = ?
", 'ii', $feedbackID, $userID)->get_result()->num_rows;

if (!$allowed) {
    flash('You cannot reply to that feedback.', 'profile.php');
}
if ($reply === '' || mb_strlen($reply) > 500) {
    flash('Reply must be 1-500 characters.', 'profile.php');
}

run("UPDATE feedback SET reply = ? WHERE feedbackID = ?", 'si', $reply, $feedbackID);
flash('Reply added.', 'profile.php', 'success');
