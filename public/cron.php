<?php
// Daily reminder link for an outside scheduler, because Render's free plan has no scheduled jobs.
require_once __DIR__ . '/../src/bootstrap.php';
require_once __DIR__ . '/../src/reminders.php';

$token = config('cron_token');
$given = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
if (!$token || !hash_equals($token, $given)) {
    http_response_code(404);
    exit('Not found');
}

header('Content-Type: application/json');
echo json_encode(send_reminders());
