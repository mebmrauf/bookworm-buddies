<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/search.php';

const MIN_QUERY_LENGTH = 2;
const MAX_BOOKS = 6;
const MAX_PEOPLE = 3;
const THUMB_WIDTH = 120;

header('Content-Type: application/json');

$q = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$response = ['books' => [], 'people' => []];

if (mb_strlen($q) >= MIN_QUERY_LENGTH) {
    $books = search_books($q, $userID, MAX_BOOKS);

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
        $people = search_people($q, MAX_PEOPLE);

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
