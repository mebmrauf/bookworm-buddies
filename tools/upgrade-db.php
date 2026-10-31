<?php
// Adds what owner ratings, Most requested and book ratings need to an existing database. Safe to run again.
// php tools/upgrade-db.php
if (PHP_SAPI !== 'cli') {
    exit;
}
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/validation.php';

$hasColumn = fn($name) => $conn->query("SHOW COLUMNS FROM collection LIKE '$name'")->num_rows > 0;
$hasIndex = fn($name) => $conn->query("SHOW INDEX FROM collection WHERE Key_name = '$name'")->num_rows > 0;

$conn->query("CREATE TABLE IF NOT EXISTS book_ratings (
    book_key CHAR(32) NOT NULL,
    userID INT NOT NULL,
    stars TINYINT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (book_key, userID),
    CHECK (stars BETWEEN 1 AND 5),
    FOREIGN KEY (userID) REFERENCES users(userID) ON DELETE CASCADE
)");

$columns = [
    'request_count' => 'INT NOT NULL DEFAULT 0',
    'book_key' => 'CHAR(32)',
    'rating_count' => 'INT NOT NULL DEFAULT 0',
    'rating_avg' => 'DECIMAL(3,2) NOT NULL DEFAULT 0',
    'rating_rank' => 'DECIMAL(3,2) NOT NULL DEFAULT 0',
];
foreach ($columns as $name => $definition) {
    if (!$hasColumn($name)) {
        $conn->query("ALTER TABLE collection ADD COLUMN $name $definition");
    }
}

$indexes = [
    'requested_all' => '(showcase, request_count, bookID)',
    'requested_city' => '(showcase, city, request_count, bookID)',
    'rated_all' => '(showcase, rating_rank, bookID)',
    'rated_city' => '(showcase, city, rating_rank, bookID)',
    'book_key' => '(book_key)',
];
foreach ($indexes as $name => $definition) {
    if (!$hasIndex($name)) {
        $conn->query("ALTER TABLE collection ADD INDEX $name $definition");
    }
}

$conn->query("UPDATE collection c SET request_count = (SELECT COUNT(DISTINCT s.requesterID) FROM swap s WHERE s.bookID = c.bookID)");

$set = $conn->prepare("UPDATE collection SET book_key = ? WHERE bookID = ?");
foreach ($conn->query("SELECT b.bookID, b.title, b.author FROM books b JOIN collection c ON c.bookID = b.bookID WHERE c.book_key IS NULL")->fetch_all(MYSQLI_ASSOC) as $book) {
    $key = book_key($book['title'], $book['author']);
    $set->bind_param('si', $key, $book['bookID']);
    $set->execute();
}

echo "Done\n";
