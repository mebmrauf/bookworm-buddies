<?php
require_once __DIR__ . '/bootstrap.php';

if (!$userID) {
    $_SESSION['next'] = $_SERVER['REQUEST_METHOD'] === 'GET' ? ($_SERVER['REQUEST_URI'] ?? null) : null;
    go('/signin.php');
}

// Suspended or deleted since signing in.
if (!current_user()) {
    session_unset();
    flash('Please sign in again.', '/signin.php');
}
