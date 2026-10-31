<?php
require_once __DIR__ . '/../../src/admin.php';
require_once __DIR__ . '/../../src/reminders.php';

require_admin();
require_post('/admin/index.php');

$do = post('do');
$id = (int) post('id');
$reason = post('reason');
$back = post('back');
$back = str_starts_with($back, '/admin/') && !str_contains($back, '//') ? $back : '/admin/index.php';

if (in_array($do, ['suspend', 'remove_cover', 'takedown', 'takedown_post'], true) && ($reason === '' || mb_strlen($reason) > 255)) {
    flash('Please give a reason of up to 255 characters.', $back);
}

function book_with_owner($id) {
    return run("
        SELECT b.title, b.cover_public_id, u.email, u.first_name
        FROM books b JOIN collection c ON b.bookID = c.bookID JOIN users u ON c.userID = u.userID
        WHERE b.bookID = ?
    ", 'i', $id)->get_result()->fetch_assoc();
}

$done = 'Done.';
switch ($do) {
    case 'logout':
        unset($_SESSION['is_admin']);
        session_regenerate_id(true);
        go('/admin/login.php');

    case 'suspend':
        $user = run("SELECT email, first_name FROM users WHERE userID = ? AND suspended_at IS NULL", 'i', $id)->get_result()->fetch_assoc();
        if (!$user) {
            flash('That user was not found or is already suspended.', $back);
        }
        $conn->begin_transaction();
        run("UPDATE users SET suspended_at = NOW(), suspended_reason = ? WHERE userID = ?", 'si', $reason, $id);
        // Books already handed over stay as they are; only requests still waiting are closed.
        run("UPDATE swap SET status = 'declined' WHERE status = 'pending' AND (ownerID = ? OR requesterID = ?)", 'ii', $id, $id);
        admin_log('suspend', 'user', $id, $reason);
        $conn->commit();
        notify($user['email'], $user['first_name'], 'Your Bookworm Buddies account is suspended', "Your account was suspended for this reason: $reason. You can ask us to look again by following the steps in our rules and policies: " . rtrim(config('app_url') ?: 'http://localhost:8000', '/') . "/rules.php#enforcement");
        $done = 'User suspended.';
        break;

    case 'unsuspend':
        $user = run("SELECT email, first_name FROM users WHERE userID = ? AND suspended_at IS NOT NULL", 'i', $id)->get_result()->fetch_assoc();
        if (!$user) {
            flash('That user is not suspended.', $back);
        }
        run("UPDATE users SET suspended_at = NULL, suspended_reason = NULL WHERE userID = ?", 'i', $id);
        admin_log('unsuspend', 'user', $id);
        notify($user['email'], $user['first_name'], 'Your Bookworm Buddies account is restored', 'Your account has been restored. You can sign in again.');
        $done = 'Suspension lifted.';
        break;

    case 'remove_cover':
        $book = book_with_owner($id);
        if (!$book || !$book['cover_public_id']) {
            flash('That book has no cover.', $back);
        }
        run("UPDATE books SET cover_public_id = NULL, cover_credit = NULL WHERE bookID = ?", 'i', $id);
        delete_images([$book['cover_public_id']]);
        admin_log('remove_cover', 'book', $id, $reason);
        notify($book['email'], $book['first_name'], "Cover removed: {$book['title']}", "An admin removed the cover of {$book['title']} for this reason: $reason. The book itself is still listed.");
        $done = 'Cover removed.';
        break;

    case 'takedown':
        $book = book_with_owner($id);
        if (!$book) {
            flash('That book was not found.', $back);
        }
        $conn->begin_transaction();
        run("UPDATE books SET removed_at = NOW(), removed_reason = ? WHERE bookID = ?", 'si', $reason, $id);
        run("UPDATE collection SET showcase = 0 WHERE bookID = ?", 'i', $id);
        run("UPDATE swap SET status = 'declined' WHERE status = 'pending' AND (bookID = ? OR offered_bookID = ?)", 'ii', $id, $id);
        admin_log('takedown', 'book', $id, $reason);
        $conn->commit();
        notify($book['email'], $book['first_name'], "Book taken down: {$book['title']}", "An admin took down {$book['title']} for this reason: $reason. Only you can see it now.");
        $done = 'Book taken down.';
        break;

    case 'restore':
        run("UPDATE books SET removed_at = NULL, removed_reason = NULL WHERE bookID = ?", 'i', $id);
        admin_log('restore', 'book', $id);
        $done = 'Book restored. Its owner can share it again.';
        break;

    case 'delete_feedback':
        $text = run("SELECT comment FROM feedback WHERE feedbackID = ?", 'i', $id)->get_result()->fetch_row()[0] ?? null;
        run("DELETE FROM feedback WHERE feedbackID = ?", 'i', $id);
        admin_log('delete_feedback', 'feedback', $id, $text ? mb_substr($text, 0, 255) : null);
        $done = 'Feedback deleted.';
        break;

    case 'delete_rating':
        $text = run("SELECT CONCAT(stars, ' stars: ', COALESCE(comment, '')) FROM ratings WHERE ratingID = ?", 'i', $id)->get_result()->fetch_row()[0] ?? null;
        run("DELETE FROM ratings WHERE ratingID = ?", 'i', $id);
        admin_log('delete_rating', 'rating', $id, $text ? mb_substr($text, 0, 255) : null);
        $done = 'Rating deleted.';
        break;

    case 'takedown_post':
        $post = run("SELECT p.title, u.email, u.first_name FROM posts p JOIN users u ON p.userID = u.userID WHERE p.postID = ?", 'i', $id)->get_result()->fetch_assoc();
        if (!$post) {
            flash('That post was not found.', $back);
        }
        run("UPDATE posts SET removed_at = NOW(), removed_reason = ? WHERE postID = ?", 'si', $reason, $id);
        admin_log('takedown_post', 'post', $id, $reason);
        notify($post['email'], $post['first_name'], "Post taken down: {$post['title']}", "An admin took down your blog post, {$post['title']}, for this reason: $reason. Only you can see it now.");
        $done = 'Post taken down.';
        break;

    case 'restore_post':
        run("UPDATE posts SET removed_at = NULL, removed_reason = NULL WHERE postID = ?", 'i', $id);
        admin_log('restore_post', 'post', $id);
        $done = 'Post restored.';
        break;

    case 'delete_message':
        // Only reported messages can reach this, and admins can't browse chats.
        $reported = run("SELECT 1 FROM reports WHERE target_type = 'message' AND target_id = ?", 'i', $id)->get_result()->num_rows;
        if (!$reported) {
            flash('Only reported messages can be deleted.', $back);
        }
        run("DELETE FROM messages WHERE messageID = ?", 'i', $id);
        admin_log('delete_message', 'message', $id);
        $done = 'Message deleted.';
        break;

    case 'resolve_report':
        run("UPDATE reports SET status = 'resolved', resolved_at = NOW() WHERE reportID = ?", 'i', $id);
        admin_log('resolve_report', 'report', $id);
        $done = 'Report closed.';
        break;

    case 'reminders':
        $sent = send_reminders();
        admin_log('reminders', null, null, "due {$sent['due']}, overdue {$sent['overdue']}");
        $done = "Reminders sent: {$sent['due']} due soon, {$sent['overdue']} overdue.";
        break;

    default:
        flash('Unknown action.', $back);
}

flash($done, $back, 'success');
