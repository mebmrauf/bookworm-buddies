<?php
const VERIFY_HOURS = 24;
const RESEND_WAIT_SECONDS = 60;

// Uses Resend's HTTPS API because hosts often block outgoing SMTP. Returns false on failure.
function send_email($to, $subject, $html, $text) {
    // example.com is reserved and never delivers, so demo accounts don't waste quota or hurt the sender's reputation.
    if (str_ends_with(strtolower($to), '@example.com')) {
        return true;
    }

    $resend = config('resend');
    if (empty($resend['EMAIL_PASS']) || empty($resend['EMAIL_FROM'])) {
        error_log('Email not sent: Resend is not configured.');
        return false;
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['from' => $resend['EMAIL_FROM'], 'to' => [$to], 'subject' => $subject, 'html' => $html, 'text' => $text]),
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $resend['EMAIL_PASS'], 'Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

    if ($status < 200 || $status >= 300) {
        error_log("Resend failed ($status): " . ($body ?: curl_error($ch)));
        return false;
    }
    return true;
}

// Tells someone about a change on a request. Best effort: a mail failure must not undo their action.
function notify($to, $firstName, $subject, $line, $note = null) {
    $link = rtrim(config('app_url') ?: 'http://localhost:8000', '/') . '/requests.php';
    $html = '<p>Hi ' . htmlspecialchars($firstName) . ',</p><p>' . htmlspecialchars($line) . '</p>'
        . ($note ? '<blockquote style="margin:0;padding:8px 12px;border-left:3px solid #a8431a">' . htmlspecialchars($note) . '</blockquote>' : '')
        . "<p><a href=\"$link\" style=\"display:inline-block;padding:12px 20px;background:#a8431a;color:#ffffff;border-radius:8px;text-decoration:none\">Open your requests</a></p>";
    $text = "Hi $firstName,\n\n$line\n" . ($note ? "\n\"$note\"\n" : '') . "\nOpen your requests: $link\n";
    send_email($to, $subject, $html, $text);
}

// Stores a fresh one-time link and emails it. Only a hash is stored, so a database leak exposes no working links.
function send_verification($userID, $to, $firstName) {
    $token = bin2hex(random_bytes(32));
    run(
        "UPDATE users SET verify_token_hash = ?, verify_expires_at = NOW() + INTERVAL " . VERIFY_HOURS . " HOUR WHERE userID = ?",
        'si', hash('sha256', $token), $userID
    );

    // From config, never the request's Host header, which a visitor can fake to poison the link.
    $link = rtrim(config('app_url') ?: 'http://localhost:8000', '/') . "/verify.php?token=$token";
    $name = htmlspecialchars($firstName);
    $html = "<p>Hi $name,</p><p>Please confirm your email address to start using Bookworm Buddies.</p>"
        . "<p><a href=\"$link\" style=\"display:inline-block;padding:12px 20px;background:#a8431a;color:#ffffff;border-radius:8px;text-decoration:none\">Confirm my email</a></p>"
        . "<p>Or open this link: $link</p><p>The link works for " . VERIFY_HOURS . " hours. If this wasn't you, you can ignore this email.</p>";
    $text = "Hi $firstName,\n\nConfirm your email address for Bookworm Buddies:\n$link\n\nThe link works for " . VERIFY_HOURS . " hours. If this wasn't you, you can ignore this email.\n";

    $sent = send_email($to, 'Confirm your email for Bookworm Buddies', $html, $text);
    if ($sent) {
        run("UPDATE users SET verify_sent_at = NOW() WHERE userID = ?", 'i', $userID);
    }
    return $sent;
}
