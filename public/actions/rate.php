<?php
require_once __DIR__ . '/../../src/auth.php';

require_post('/requests.php');

$swapID = (int) post('swapID');
$stars = (int) post('stars');
$comment = post('comment');

$swap = run("SELECT ownerID, requesterID FROM swap WHERE swapID = ? AND status IN ('returned', 'completed')", 'i', $swapID)->get_result()->fetch_assoc();

if (!$swap || ($swap['ownerID'] != $userID && $swap['requesterID'] != $userID)) {
    flash('You can only rate your own finished exchanges.', '/requests.php');
}
if ($stars < 1 || $stars > 5) {
    flash('Please choose 1 to 5 stars.', '/requests.php');
}
if (mb_strlen($comment) > 300) {
    flash('Your comment can be at most 300 characters.', '/requests.php');
}

$ratedID = $swap['ownerID'] == $userID ? $swap['requesterID'] : $swap['ownerID'];
try {
    run("INSERT INTO ratings (swapID, raterID, ratedID, stars, comment) VALUES (?, ?, ?, ?, ?)", 'iiiis', $swapID, $userID, $ratedID, $stars, $comment === '' ? null : $comment);
} catch (mysqli_sql_exception $ex) {
    if ($ex->getCode() !== DUPLICATE_KEY) {
        throw $ex;
    }
    flash('You already rated this exchange.', '/requests.php');
}
flash('Thanks for your rating.', '/requests.php', 'success');
