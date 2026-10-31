<?php
require_once __DIR__ . '/../../src/auth.php';

require_post('/requests.php');

$swapID = (int) post('swapID');
$action = post('action');

// action => [who may do it, status it must be in, new status]
$rules = [
    'accept' => ['ownerID', 'pending', 'accepted'],
    'decline' => ['ownerID', 'pending', 'declined'],
    'return' => ['ownerID', 'accepted', 'returned'],
];

if ($action === 'cancel') {
    $changed = run("DELETE FROM swap WHERE swapID = ? AND requesterID = ? AND status = 'pending'", 'ii', $swapID, $userID)->affected_rows;
} elseif (isset($rules[$action])) {
    [$role, $from, $to] = $rules[$action];
    $changed = run("UPDATE swap SET status = ? WHERE swapID = ? AND $role = ? AND status = ?", 'siis', $to, $swapID, $userID, $from)->affected_rows;
} else {
    $changed = 0;
}

if ($changed === 0) {
    flash('You cannot do that for this request.', '/requests.php');
}
flash('Request updated.', '/requests.php', 'success');
