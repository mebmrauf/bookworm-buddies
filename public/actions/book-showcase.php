<?php
require_once __DIR__ . '/../../src/auth.php';

require_post('/profile.php');

$bookID = (int) post('bookID');
$showcase = post('showcase') === '1' ? 1 : 0;

$book = run("SELECT b.removed_at FROM collection c JOIN books b ON c.bookID = b.bookID WHERE c.userID = ? AND c.bookID = ?", 'ii', $userID, $bookID)->get_result()->fetch_assoc();
if (!$book) {
    flash('You cannot change that book.', '/profile.php');
}
if ($book['removed_at'] && $showcase) {
    flash('An admin took this book down, so it cannot be shared.', "/book.php?id=$bookID");
}

run("UPDATE collection SET showcase = ? WHERE userID = ? AND bookID = ?", 'iii', $showcase, $userID, $bookID);
flash($showcase ? 'Your book is now showcased.' : 'Your book is now private.', "/book.php?id=$bookID", 'success');
