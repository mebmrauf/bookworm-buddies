<?php
// Searches use FULLTEXT indexes: LIKE '%word%' has to read every row, which doesn't survive a large table.
// That means matching words and word beginnings, not the middle of a word. Each search first takes a
// limited set of matches from the index, so a very common word can't turn into millions of rows to sort.

function search_books($q, $userID, $limit) {
    $terms = fulltext_query($q);
    $candidates = $terms !== ''
        ? "SELECT bookID FROM books WHERE MATCH (title, author) AGAINST (? IN BOOLEAN MODE) LIMIT " . SEARCH_CANDIDATES
        : "SELECT bookID FROM books WHERE title LIKE ? LIMIT " . SEARCH_CANDIDATES;
    return run("
        SELECT b.bookID, b.title, b.author, b.year_published, b.cover_public_id, b.listing_type, c.rating_avg, c.rating_count, u.userID AS ownerID, u.username, u.city, u.area
        FROM ($candidates) m
        JOIN books b ON b.bookID = m.bookID
        JOIN collection c ON b.bookID = c.bookID
        JOIN users u ON c.userID = u.userID
        WHERE ((" . VISIBLE_BOOK . ") OR c.userID = ?)
        LIMIT ?
    ", 'sii', $terms !== '' ? $terms : prefix_pattern($q), $userID, $limit)->get_result();
}

function search_people($q, $limit) {
    $terms = fulltext_query($q);
    $candidates = $terms !== ''
        ? "SELECT userID FROM users WHERE MATCH (username, first_name, last_name) AGAINST (? IN BOOLEAN MODE) LIMIT " . SEARCH_CANDIDATES
        : "SELECT userID FROM users WHERE username LIKE ? LIMIT " . SEARCH_CANDIDATES;
    return run("
        SELECT u.userID, u.username, u.first_name, u.last_name, u.avatar_public_id, u.city, u.area
        FROM ($candidates) m
        JOIN users u ON u.userID = m.userID
        WHERE u.suspended_at IS NULL
        LIMIT ?
    ", 'si', $terms !== '' ? $terms : prefix_pattern($q), $limit)->get_result();
}
