<?php
const REMIND_DAYS_BEFORE = 3;
const OVERDUE_REPEAT_DAYS = 7;

// Sends return-date emails. Safe to run often: each one is only sent once, and overdue ones repeat weekly.
function send_reminders() {
    $today = date('Y-m-d');
    $soon = date('Y-m-d', strtotime('+' . REMIND_DAYS_BEFORE . ' days'));
    $base = "
        SELECT s.swapID, s.due_date, b.title, r.email AS requesterEmail, r.first_name AS requesterName, o.email AS ownerEmail, o.first_name AS ownerName
        FROM swap s
        JOIN books b ON s.bookID = b.bookID
        JOIN users r ON s.requesterID = r.userID
        JOIN users o ON s.ownerID = o.userID
        WHERE s.status = 'accepted' AND s.kind = 'lend' AND s.due_date IS NOT NULL
    ";
    $sent = ['due' => 0, 'overdue' => 0];

    $due = run("$base AND s.due_date >= ? AND s.due_date <= ? AND s.reminded_due_at IS NULL", 'ss', $today, $soon)->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($due as $swap) {
        $when = date('j F Y', strtotime($swap['due_date']));
        notify($swap['requesterEmail'], $swap['requesterName'], "Reminder: return {$swap['title']} by $when", "{$swap['title']} is due back by $when. Please arrange the return with the owner, or ask them for more time.");
        run("UPDATE swap SET reminded_due_at = NOW() WHERE swapID = ?", 'i', $swap['swapID']);
        $sent['due']++;
    }

    $overdue = run(
        "$base AND s.due_date < ? AND (s.reminded_overdue_at IS NULL OR s.reminded_overdue_at < NOW() - INTERVAL " . OVERDUE_REPEAT_DAYS . " DAY)",
        's', $today
    )->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($overdue as $swap) {
        $when = date('j F Y', strtotime($swap['due_date']));
        notify($swap['requesterEmail'], $swap['requesterName'], "Overdue: {$swap['title']}", "{$swap['title']} was due back on $when. Please return it, or talk to the owner.");
        notify($swap['ownerEmail'], $swap['ownerName'], "Overdue: {$swap['title']}", "{$swap['title']} was due back on $when. We reminded the borrower. You can change the date on your requests page.");
        run("UPDATE swap SET reminded_overdue_at = NOW() WHERE swapID = ?", 'i', $swap['swapID']);
        $sent['overdue']++;
    }
    return $sent;
}
