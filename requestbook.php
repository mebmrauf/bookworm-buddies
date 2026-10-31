<?php
require_once 'auth.php';

require_post('home.php');

$bookID = (int) post('bookID');

$owner = run("SELECT userID FROM collection WHERE bookID = ? AND showcase = 1", 'i', $bookID)->get_result()->fetch_assoc();

if (!$owner) {
    flash('That book is not available.', 'availablebooks.php');
}
if ($owner['userID'] == $userID) {
    flash('You cannot request your own book.', 'availablebooks.php');
}

try {
    run("INSERT INTO swap (requesterID, ownerID, bookID, status) VALUES (?, ?, ?, 'pending')", 'iii', $userID, $owner['userID'], $bookID);
} catch (mysqli_sql_exception $ex) {
    if ($ex->getCode() !== DUPLICATE_KEY) {
        throw $ex;
    }
    flash('That book is not available.', 'availablebooks.php');
}

flash('Request sent.', 'requests.php', 'success');
