<?php
require_once 'auth.php';

require_post('profile.php');

$bookID = (int) post('bookID');

$owned = run("SELECT 1 FROM collection WHERE bookID = ? AND userID = ?", 'ii', $bookID, $userID)->get_result()->num_rows;
if (!$owned) {
    flash('You cannot delete that book.', 'profile.php');
}

$lent = run("SELECT 1 FROM swap WHERE bookID = ? AND status = 'accepted'", 'i', $bookID)->get_result()->num_rows;
if ($lent) {
    flash('That book is lent out. Mark it as returned before deleting it.', 'profile.php');
}

// collection, swap and feedback rows go with it (ON DELETE CASCADE).
run("DELETE FROM books WHERE bookID = ?", 'i', $bookID);
flash('Book deleted.', 'profile.php', 'success');
