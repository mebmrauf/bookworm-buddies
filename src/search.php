<?php
// TiDB has no MATCH ... AGAINST. fts_match_word finds whole words anywhere, through the FULLTEXT index, and a
// prefix match covers a word that is still being typed. Each part reads a limited batch, so a common word
// can't turn into millions of rows to sort.

// Returns [sql, types, params] for a subquery of matching IDs.
function search_candidates($table, $id, $wordColumns, $prefixColumn, $q) {
    $q = mb_substr($q, 0, 100);
    // shortcut: the database compares text case-sensitively, so the prefix is tried in the usual capitalisations
    // rather than ignoring case; enough for titles and usernames, a case-insensitive collation would cover the rest.
    $cases = array_unique([$q, mb_strtolower($q), mb_strtoupper(mb_substr($q, 0, 1)) . mb_substr($q, 1), mb_convert_case($q, MB_CASE_TITLE)]);
    $parts = [];
    $params = [];
    // fts_match_word matches any one word and can't be combined with another, so it searches for the longest
    // word, and the few hundred results are then checked for every word.
    preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($q), $words);
    $words = array_slice(array_unique($words[0]), 0, 8);
    if ($words) {
        usort($words, fn($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        $everyWord = str_repeat(" AND LOWER(CONCAT_WS(' ', $wordColumns)) LIKE ?", count($words));
        $parts[] = "(SELECT $id FROM (SELECT $id, $wordColumns FROM $table WHERE fts_match_word(?, $wordColumns) LIMIT " . SEARCH_CANDIDATES . ") w WHERE 1 = 1$everyWord)";
        array_push($params, $words[0], ...array_map('like_pattern', $words));
    }
    foreach ($cases as $case) {
        $parts[] = "(SELECT $id FROM $table WHERE $prefixColumn LIKE ? LIMIT " . SEARCH_CANDIDATES . ")";
        $params[] = prefix_pattern($case);
    }
    return [implode(' UNION ', $parts), str_repeat('s', count($params)), $params];
}

function search_books($q, $userID, $limit) {
    [$candidates, $types, $params] = search_candidates('books', 'bookID', 'title, author', 'title', $q);
    return run("
        SELECT b.bookID, b.title, b.author, b.year_published, b.cover_public_id, b.listing_type, c.rating_avg, c.rating_count, u.userID AS ownerID, u.username, u.city, u.area
        FROM ($candidates) m
        JOIN books b ON b.bookID = m.bookID
        JOIN collection c ON b.bookID = c.bookID
        JOIN users u ON c.userID = u.userID
        WHERE ((" . VISIBLE_BOOK . ") OR c.userID = ?)
        LIMIT ?
    ", $types . 'ii', ...[...$params, $userID, $limit])->get_result();
}

function search_people($q, $limit) {
    [$candidates, $types, $params] = search_candidates('users', 'userID', 'username, first_name, last_name', 'username', $q);
    return run("
        SELECT u.userID, u.username, u.first_name, u.last_name, u.avatar_public_id, u.city, u.area
        FROM ($candidates) m
        JOIN users u ON u.userID = m.userID
        WHERE u.suspended_at IS NULL
        LIMIT ?
    ", $types . 'i', ...[...$params, $limit])->get_result();
}
