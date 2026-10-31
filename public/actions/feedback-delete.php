<?php
require_once __DIR__ . '/../../src/auth.php';

require_post('/profile.php');

$feedbackID = (int) post('feedbackID');

$feedback = run("SELECT bookID FROM feedback WHERE feedbackID = ? AND userID = ?", 'ii', $feedbackID, $userID)->get_result()->fetch_assoc();

if (!$feedback) {
    flash('You cannot delete that feedback.', '/profile.php');
}

run("DELETE FROM feedback WHERE feedbackID = ?", 'i', $feedbackID);
flash('Feedback deleted.', '/book.php?id=' . $feedback['bookID'], 'success');
