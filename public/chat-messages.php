<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/chat.php';

// Lets the person keep browsing in another tab while this checks for messages.
session_write_close();

header('Content-Type: application/json');
header('Cache-Control: no-store');

$swapID = (int) ($_GET['swap'] ?? 0);
$swap = chat_swap($swapID, $userID);
if (!$swap) {
    http_response_code(404);
    echo json_encode(['error' => 'That chat is not available.']);
    exit();
}

$messages = chat_messages($swapID, $userID, (int) ($_GET['after'] ?? 0));
if ($messages) {
    chat_mark_read($swapID, $userID);
}
echo json_encode(['messages' => $messages, 'open' => chat_is_open($swap)]);
