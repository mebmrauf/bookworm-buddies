<?php
require_once 'auth.php';

require_post('profile.php');

$bookID = (int) post('bookID');
$showcase = post('showcase') === '1' ? 1 : 0;

run("UPDATE collection SET showcase = ? WHERE userID = ? AND bookID = ?", 'iii', $showcase, $userID, $bookID);
go('profile.php');
