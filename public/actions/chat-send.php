<?php
require_once __DIR__ . '/../../src/auth.php';
require_once __DIR__ . '/../../src/chat.php';

$swapID = (int) post('swapID');
$chatURL = "/chat.php?swap=$swapID";
$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

// The page sends messages in the background, and falls back to a normal form without JavaScript.
$refuse = function ($message, $code = 400) use ($wantsJson, $chatURL) {
    if ($wantsJson) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit();
    }
    flash($message, $chatURL);
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    go($chatURL);
}
if (!hash_equals(csrf_token(), post('csrf'))) {
    $refuse('That page expired. Please refresh and try again.', 403);
}

$swap = chat_swap($swapID, $userID);
if (!$swap || !chat_is_open($swap)) {
    $refuse('This chat has ended, so no more messages can be sent.', 403);
}

$body = post('body');
if ($body === '' || mb_strlen($body) > MAX_MESSAGE_LENGTH) {
    $refuse('Messages must be 1-' . MAX_MESSAGE_LENGTH . ' characters.');
}

$recent = run("SELECT COUNT(*) FROM messages WHERE senderID = ? AND created_at > NOW() - INTERVAL 1 MINUTE", 'i', $userID)->get_result()->fetch_row()[0];
if ($recent >= MAX_MESSAGES_PER_MINUTE) {
    $refuse('You are sending messages too quickly. Please wait a moment.', 429);
}

$isOwner = $swap['ownerID'] == $userID;

// One email per batch of unread messages, so a busy chat doesn't flood anyone's inbox.
$alreadyUnread = run("SELECT 1 FROM messages WHERE swapID = ? AND senderID = ? AND read_at IS NULL LIMIT 1", 'ii', $swapID, $userID)->get_result()->num_rows;

run("INSERT INTO messages (swapID, senderID, body) VALUES (?, ?, ?)", 'iis', $swapID, $userID, $body);
$messageID = $conn->insert_id;

if (!$alreadyUnread) {
    $me = $isOwner ? $swap['owner'] : $swap['requester'];
    notify(
        $isOwner ? $swap['requesterEmail'] : $swap['ownerEmail'],
        $isOwner ? $swap['requesterName'] : $swap['ownerName'],
        "New message about {$swap['title']}",
        "$me sent you a message about {$swap['title']}.",
        null,
        $chatURL,
        'Open the chat'
    );
}

if ($wantsJson) {
    $row = run("SELECT messageID, senderID, body, UNIX_TIMESTAMP(created_at) AS sentAt FROM messages WHERE messageID = ?", 'i', $messageID)->get_result()->fetch_assoc();
    header('Content-Type: application/json');
    echo json_encode(['message' => chat_format($row, $userID)]);
    exit();
}
go($chatURL);
