<?php
require_once __DIR__ . '/../../src/auth.php';

require_post('/home.php');

$bookID = (int) post('bookID');

$owner = run("SELECT userID FROM collection WHERE bookID = ? AND showcase = 1", 'i', $bookID)->get_result()->fetch_assoc();

if (!$owner) {
    flash('That book is not available.', '/home.php?available=1');
}
if ($owner['userID'] == $userID) {
    flash('You cannot request your own book.', '/home.php?available=1');
}

try {
    run("INSERT INTO swap (requesterID, ownerID, bookID, status) VALUES (?, ?, ?, 'pending')", 'iii', $userID, $owner['userID'], $bookID);
} catch (mysqli_sql_exception $ex) {
    if ($ex->getCode() !== DUPLICATE_KEY) {
        throw $ex;
    }
    flash('That book is not available.', '/home.php?available=1');
}

flash('Request sent. The owner will see it on their Requests page.', '/requests.php', 'success');
