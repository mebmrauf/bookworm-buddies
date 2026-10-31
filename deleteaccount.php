<?php
require_once 'auth.php';

require_post('profile.php');

$conn->begin_transaction();
// Books have one owner, so they go with the account. Everything else follows by ON DELETE CASCADE.
run("DELETE b FROM books b JOIN collection c ON b.bookID = c.bookID WHERE c.userID = ?", 'i', $userID);
run("DELETE FROM users WHERE userID = ?", 'i', $userID);
$conn->commit();

session_unset();
session_destroy();
go('index.php');
