<?php
require_once __DIR__ . '/../../src/auth.php';

require_post('/profile.php');

$bookID = (int) post('bookID');

$book = run("
    SELECT b.cover_public_id FROM books b JOIN collection c ON b.bookID = c.bookID
    WHERE b.bookID = ? AND c.userID = ?
", 'ii', $bookID, $userID)->get_result()->fetch_assoc();

if (!$book) {
    flash('You cannot delete that book.', '/profile.php');
}

$lent = run("SELECT 1 FROM swap WHERE bookID = ? AND status = 'accepted'", 'i', $bookID)->get_result()->num_rows;
if ($lent) {
    flash('That book is lent out. Mark it as returned before deleting it.', "/book.php?id=$bookID");
}

// collection, swap and feedback rows go with it (ON DELETE CASCADE).
run("DELETE FROM books WHERE bookID = ?", 'i', $bookID);
delete_images([$book['cover_public_id']]);
flash('Book deleted.', '/profile.php', 'success');
