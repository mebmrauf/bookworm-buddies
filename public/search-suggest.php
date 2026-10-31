<?php
require_once __DIR__ . '/../src/bootstrap.php';

const MIN_QUERY_LENGTH = 2;
const MAX_BOOKS = 6;
const MAX_PEOPLE = 3;
const THUMB_WIDTH = 120;

header('Content-Type: application/json');

$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$response = ['books' => [], 'people' => []];

if (mb_strlen($q) >= MIN_QUERY_LENGTH) {
    $pattern = like_pattern($q);

    $books = run("
        SELECT b.bookID, b.title, b.author, b.cover_public_id, u.city, u.area
        FROM books b
        JOIN collection c ON b.bookID = c.bookID
        JOIN users u ON c.userID = u.userID
        WHERE (b.title LIKE ? OR b.author LIKE ?) AND ((" . VISIBLE_BOOK . ") OR c.userID = ?)
        ORDER BY b.title
        LIMIT " . MAX_BOOKS,
        'ssi', $pattern, $pattern, $userID
    )->get_result();

    while ($book = $books->fetch_assoc()) {
        $response['books'][] = [
            'id' => (int) $book['bookID'],
            'title' => $book['title'],
            'author' => $book['author'],
            'where' => location_label($book),
            'cover' => $book['cover_public_id'] && cloudinary_enabled() ? image_url($book['cover_public_id'], THUMB_WIDTH, THUMB_WIDTH * 3 / 2) : null,
        ];
    }

    // Finding people is for members, so names can't be scraped by anyone passing by.
    if ($userID) {
        $people = run("
            SELECT userID, username, first_name, last_name, city, area
            FROM users
            WHERE suspended_at IS NULL AND (username LIKE ? OR first_name LIKE ? OR last_name LIKE ?)
            ORDER BY first_name
            LIMIT " . MAX_PEOPLE,
            'sss', $pattern, $pattern, $pattern
        )->get_result();

        while ($person = $people->fetch_assoc()) {
            $response['people'][] = [
                'id' => (int) $person['userID'],
                'name' => trim($person['first_name'] . ' ' . $person['last_name']),
                'username' => $person['username'],
                'initials' => mb_strtoupper(mb_substr($person['first_name'], 0, 1) . mb_substr($person['last_name'], 0, 1)),
                'where' => location_label($person),
            ];
        }
    }
}

echo json_encode($response);
