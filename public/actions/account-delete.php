<?php
require_once __DIR__ . '/../../src/auth.php';

require_post('/settings.php');

$images = run("SELECT b.cover_public_id FROM books b JOIN collection c ON b.bookID = c.bookID WHERE c.userID = ?", 'i', $userID)->get_result()->fetch_all();
$images = array_column($images, 0);
$images[] = run("SELECT avatar_public_id FROM users WHERE userID = ?", 'i', $userID)->get_result()->fetch_row()[0];

$conn->begin_transaction();
// Books have one owner, so they go with the account. Everything else follows by ON DELETE CASCADE.
run("DELETE b FROM books b JOIN collection c ON b.bookID = c.bookID WHERE c.userID = ?", 'i', $userID);
run("DELETE FROM users WHERE userID = ?", 'i', $userID);
$conn->commit();

delete_images($images);
session_unset();
session_destroy();
go('/');
