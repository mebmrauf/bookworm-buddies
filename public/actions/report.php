<?php
require_once __DIR__ . '/../../src/auth.php';

require_post('/home.php');

$type = post('type');
$id = (int) post('id');
$reason = post('reason');
$details = post('details');

$lookups = [
    'book' => "SELECT 1 FROM books WHERE bookID = ?",
    'user' => "SELECT 1 FROM users WHERE userID = ?",
    'feedback' => "SELECT 1 FROM feedback WHERE feedbackID = ?",
    'rating' => "SELECT 1 FROM ratings WHERE ratingID = ?",
    'message' => "SELECT 1 FROM messages WHERE messageID = ?",
];
if (!isset($lookups[$type]) || !in_array($reason, REPORT_REASONS, true) || mb_strlen($details) > 500 || !run($lookups[$type], 'i', $id)->get_result()->num_rows) {
    flash('That report could not be sent.', '/home.php');
}
// A chat is private, so only someone in it can report one of its messages.
if ($type === 'message' && !run("
    SELECT 1 FROM messages m JOIN swap s ON m.swapID = s.swapID
    WHERE m.messageID = ? AND m.senderID != ? AND (s.ownerID = ? OR s.requesterID = ?)
", 'iiii', $id, $userID, $userID, $userID)->get_result()->num_rows) {
    flash('That report could not be sent.', '/requests.php');
}
if ($type === 'user' && $id === $userID) {
    flash('You cannot report yourself.', '/home.php');
}

try {
    run("INSERT INTO reports (reporterID, target_type, target_id, reason, details) VALUES (?, ?, ?, ?, ?)", 'isiss', $userID, $type, $id, $reason, $details === '' ? null : $details);
} catch (mysqli_sql_exception $ex) {
    if ($ex->getCode() !== DUPLICATE_KEY) {
        throw $ex;
    }
}
// The same reply for a repeat report, which is ignored.
$referer = parse_url($_SERVER['HTTP_REFERER'] ?? '/home.php');
$back = ($referer['path'] ?? '/home.php') . (isset($referer['query']) ? '?' . $referer['query'] : '');
flash('Thanks. An admin will take a look.', safe_next($back), 'success');
