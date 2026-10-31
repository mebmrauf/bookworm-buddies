<?php
// Adds covers from Open Library to books that have none: php tools/seed-covers.php
// Safe to run again: it only touches books still missing a cover.
if (PHP_SAPI !== 'cli') {
    exit;
}
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/cloudinary.php';

const MIN_COVER_WIDTH = 150; // Smaller "covers" are usually blank placeholders.

// Hand-picked after reviewing the images: the first search result can be a plain, stickered or wrong edition.
const COVER_PICKS = [
    'The Great Gatsby' => 14314120,
    'Pride and Prejudice' => 14858485,
    'Jane Eyre' => 14083354,
    'The Kite Runner' => 15162782,
    'And Then There Were None' => 15244938,
    'Gone Girl' => 12498395,
    'Gitanjali' => 13172798,
    'The Namesake' => 10943402,
    'Interpreter of Maladies' => 1883501,
    '1984' => 15196195,
    'Animal Farm' => 15258669,
    'The Catcher in the Rye' => 14577326,
    'The Alchemist' => 7414780,
    'War and Peace' => 12621906,
    'Pather Panchali' => 6975329,
];

if (!cloudinary_enabled()) {
    exit("Add your Cloudinary keys to config.php first.\n");
}

function normalize($text) {
    return preg_replace('/[^a-z0-9]+/', ' ', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text)));
}

// Open Library cover IDs for this title and author, best match first.
function find_cover_ids($title, $author) {
    $wanted = trim(normalize(explode(':', $title)[0]));
    $firstAuthor = trim(normalize(preg_replace('/ and .*/', '', $author)));
    $surname = substr($firstAuthor, strrpos(" $firstAuthor", ' '));

    $query = http_build_query(['title' => explode(':', $title)[0], 'author' => $surname, 'fields' => 'title,author_name,cover_i', 'limit' => 20]);
    $ch = curl_init("https://openlibrary.org/search.json?$query");
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_USERAGENT => 'BookwormBuddies/1.0']);
    $results = json_decode((string) curl_exec($ch), true)['docs'] ?? [];

    $ids = [];
    foreach ($results as $doc) {
        $names = normalize(implode(' ', $doc['author_name'] ?? []));
        if (!empty($doc['cover_i']) && str_contains(normalize($doc['title'] ?? ''), $wanted) && str_contains($names, $surname)) {
            $ids[] = $doc['cover_i'];
        }
    }
    return $ids;
}

$books = $conn->query("SELECT bookID, title, author FROM books WHERE cover_public_id IS NULL ORDER BY bookID")->fetch_all(MYSQLI_ASSOC);
$save = $conn->prepare("UPDATE books SET cover_public_id = ?, cover_credit = 'Open Library' WHERE bookID = ?");
$added = 0;

foreach ($books as $book) {
    $saved = false;
    $candidates = isset(COVER_PICKS[$book['title']]) ? [COVER_PICKS[$book['title']]] : array_slice(find_cover_ids($book['title'], $book['author']), 0, 5);
    foreach ($candidates as $coverID) {
        // Cloudinary fetches the image itself, so nothing is downloaded here.
        $result = cloudinary_request('upload', ['file' => "https://covers.openlibrary.org/b/id/$coverID-L.jpg", 'folder' => 'bookworm-buddies/covers']);
        if (empty($result['public_id'])) {
            continue;
        }
        if ($result['width'] < MIN_COVER_WIDTH) {
            delete_images([$result['public_id']]);
            continue;
        }
        $save->bind_param('si', $result['public_id'], $book['bookID']);
        $save->execute();
        $saved = true;
        $added++;
        echo "added      {$book['title']} ({$result['width']}x{$result['height']}, OL $coverID)\n";
        break;
    }
    if (!$saved) {
        echo "no cover   {$book['title']}\n";
    }
    usleep(300000); // Be polite to a free service.
}

echo "\nAdded $added of " . count($books) . " covers.\n";
