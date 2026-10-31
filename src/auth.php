<?php
require_once __DIR__ . '/bootstrap.php';

if (empty($_SESSION['user_id'])) {
    go('/index.php');
}
$userID = (int) $_SESSION['user_id'];
