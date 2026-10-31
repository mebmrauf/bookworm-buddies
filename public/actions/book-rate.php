<?php
require_once __DIR__ . '/../../src/auth.php';

require_post('/home.php');

const MAX_RATINGS_PER_HOUR = 60;

$bookID = (int) post('bookID');
$stars = (int) post('stars');
$bookURL = "/book.php?id=$bookID";

$book = run("
    SELECT b.title, b.author FROM books b
    JOIN collection c ON b.bookID = c.bookID
    JOIN users u ON c.userID = u.userID
    WHERE b.bookID = ? AND ((" . VISIBLE_BOOK . ") OR c.userID = ?)
", 'ii', $bookID, $userID)->get_result()->fetch_assoc();

if (!$book) {
    flash('That book was not found.', '/home.php');
}
if ($stars < 1 || $stars > 5) {
    flash('Please choose from 1 to 5 stars.', $bookURL);
}
if (rate_limited("book-rate:user:$userID", MAX_RATINGS_PER_HOUR, 3600)) {
    flash('You have rated a lot of books this hour. Please try again later.', $bookURL);
}

$key = book_key($book['title'], $book['author']);
run("
    INSERT INTO book_ratings (book_key, userID, stars) VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE stars = VALUES(stars)
", 'sii', $key, $userID, $stars);
refresh_book_rating($key);

flash('Thanks for rating this book.', $bookURL, 'success');
