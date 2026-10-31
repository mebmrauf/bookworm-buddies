<?php
require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/chat.php';

$swapID = (int) ($_GET['swap'] ?? 0);
$swap = chat_swap($swapID, $userID);
if (!$swap) {
    flash('That chat is not available.', '/requests.php');
}

$isOwner = $swap['ownerID'] == $userID;
$otherName = $isOwner ? $swap['requester'] : $swap['owner'];
$otherID = $isOwner ? $swap['requesterID'] : $swap['ownerID'];
$open = chat_is_open($swap);
$messages = chat_messages($swapID, $userID);
chat_mark_read($swapID, $userID);
$lastID = $messages ? end($messages)['id'] : 0;

$pageTitle = "Chat about {$swap['title']}";
$navActive = 'requests';
include __DIR__ . '/../templates/top.php';
?>
<section class="chat" data-chat="<?= $swapID ?>" data-last="<?= $lastID ?>" data-open="<?= $open ? '1' : '0' ?>">
    <header class="chat__head">
        <p><a class="link" href="/requests.php">Back to requests</a></p>
        <h1>Chat with <a class="link" href="/profile.php?userID=<?= (int) $otherID ?>"><?= e($otherName) ?></a></h1>
        <p class="muted">About <?= e($swap['title']) ?></p>
        <p class="help">Agree on a public place and time. You don't have to share your number or address here.</p>
    </header>

    <div class="chat__log" data-chat-log role="log" aria-live="polite" aria-label="Messages" tabindex="0">
        <?php if (!$messages): ?>
            <p class="chat__empty" data-chat-empty><?= $open ? 'Say hello, and suggest where and when to meet.' : 'No messages were sent.' ?></p>
        <?php endif; ?>
        <?php foreach ($messages as $message): ?>
            <div class="bubble<?= $message['mine'] ? ' bubble--mine' : '' ?>" data-id="<?= $message['id'] ?>"><?= e($message['body']) ?><div class="bubble__meta"><time><?= e($message['time']) ?></time><?= $message['mine'] ? '' : report_form('message', $message['id']) ?></div></div>
        <?php endforeach; ?>
    </div>

    <template data-report-template><?= report_form('message', 0) ?></template>

    <?php if ($open): ?>
        <form class="chat__form" action="/actions/chat-send.php" method="post" data-chat-form>
            <?= csrf_field() ?>
            <input type="hidden" name="swapID" value="<?= $swapID ?>">
            <div class="field">
                <label class="visually-hidden" for="chat-message">Your message</label>
                <textarea id="chat-message" name="body" rows="1" maxlength="<?= MAX_MESSAGE_LENGTH ?>" placeholder="Write a message" required></textarea>
            </div>
            <button type="submit" class="btn btn--primary">Send</button>
        </form>
    <?php endif; ?>
    <p class="chat__status" data-chat-status role="status"><?= $open ? '' : 'This chat has ended, so no more messages can be sent.' ?></p>
</section>
<?php include __DIR__ . '/../templates/bottom.php'; ?>
