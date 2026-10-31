<?php
require_once __DIR__ . '/../../src/auth.php';

require_post('/profile.php');

$bookID = (int) post('bookID');
$showcase = post('showcase') === '1' ? 1 : 0;

$owned = run("SELECT 1 FROM collection WHERE userID = ? AND bookID = ?", 'ii', $userID, $bookID)->get_result()->num_rows;
if (!$owned) {
    flash('You cannot change that book.', '/profile.php');
}

run("UPDATE collection SET showcase = ? WHERE userID = ? AND bookID = ?", 'iii', $showcase, $userID, $bookID);
flash($showcase ? 'Your book is now showcased.' : 'Your book is now private.', "/book.php?id=$bookID", 'success');
