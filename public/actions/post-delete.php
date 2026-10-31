<?php
require_once __DIR__ . '/../../src/auth.php';

require_post('/blog.php');

$postID = (int) post('postID');
$post = run("SELECT image_public_id FROM posts WHERE postID = ? AND userID = ?", 'ii', $postID, $userID)->get_result()->fetch_assoc();
if (!$post) {
    flash('You cannot delete that post.', '/blog.php');
}

run("DELETE FROM posts WHERE postID = ? AND userID = ?", 'ii', $postID, $userID);
delete_images([$post['image_public_id']]);
flash('Post deleted.', '/blog.php', 'success');
