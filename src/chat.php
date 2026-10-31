<?php
const MAX_MESSAGE_LENGTH = 1000;
const MAX_MESSAGES_PER_MINUTE = 20;

// A swap this person is part of, once it has been accepted. A chat opens at acceptance.
function chat_swap($swapID, $userID) {
    return run("
        SELECT s.swapID, s.status, s.ownerID, s.requesterID, b.title,
            r.username AS requester, r.first_name AS requesterName, r.email AS requesterEmail, r.suspended_at AS requesterSuspended,
            o.username AS owner, o.first_name AS ownerName, o.email AS ownerEmail, o.suspended_at AS ownerSuspended
        FROM swap s
        JOIN books b ON s.bookID = b.bookID
        JOIN users r ON s.requesterID = r.userID
        JOIN users o ON s.ownerID = o.userID
        WHERE s.swapID = ? AND (s.ownerID = ? OR s.requesterID = ?) AND s.status IN ('accepted', 'returned', 'completed')
    ", 'iii', $swapID, $userID, $userID)->get_result()->fetch_assoc();
}

function chat_is_open($swap) {
    return $swap['status'] === 'accepted' && !$swap['ownerSuspended'] && !$swap['requesterSuspended'];
}

function chat_messages($swapID, $userID, $afterID = 0) {
    $rows = run("
        SELECT messageID, senderID, body, UNIX_TIMESTAMP(created_at) AS sentAt
        FROM messages WHERE swapID = ? AND messageID > ?
        ORDER BY messageID
        LIMIT 500
    ", 'ii', $swapID, $afterID)->get_result();
    $messages = [];
    while ($row = $rows->fetch_assoc()) {
        $messages[] = chat_format($row, $userID);
    }
    return $messages;
}

function chat_format($row, $userID) {
    return [
        'id' => (int) $row['messageID'],
        'mine' => $row['senderID'] == $userID,
        'body' => $row['body'],
        'time' => date('j M, g:i A', (int) $row['sentAt']),
    ];
}

function chat_mark_read($swapID, $userID) {
    run("UPDATE messages SET read_at = NOW() WHERE swapID = ? AND senderID != ? AND read_at IS NULL", 'ii', $swapID, $userID);
}
