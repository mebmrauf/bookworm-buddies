<?php
require_once __DIR__ . '/../../src/auth.php';

require_post('/settings.php');

$hash = run("SELECT password FROM users WHERE userID = ?", 'i', $userID)->get_result()->fetch_row()[0];
if (!current_password_ok(post('password', false), $hash)) {
    flash('Your account was not deleted, because that password is incorrect.', '/settings.php');
}

$images = run("SELECT b.cover_public_id FROM books b JOIN collection c ON b.bookID = c.bookID WHERE c.userID = ?", 'i', $userID)->get_result()->fetch_all();
$images = array_column($images, 0);
$images = array_merge($images, array_column(run("SELECT image_public_id FROM posts WHERE userID = ?", 'i', $userID)->get_result()->fetch_all(), 0));
$images[] = run("SELECT avatar_public_id FROM users WHERE userID = ?", 'i', $userID)->get_result()->fetch_row()[0];

$ratedBooks = array_column(run("SELECT book_key FROM book_ratings WHERE userID = ?", 'i', $userID)->get_result()->fetch_all(), 0);

$conn->begin_transaction();
// Books have one owner, so they go with the account. Everything else follows by ON DELETE CASCADE.
run("DELETE b FROM books b JOIN collection c ON b.bookID = c.bookID WHERE c.userID = ?", 'i', $userID);
run("DELETE FROM users WHERE userID = ?", 'i', $userID);
$conn->commit();
foreach ($ratedBooks as $key) {
    refresh_book_rating($key);
}

delete_images($images);
session_unset();
session_destroy();
go('/');
