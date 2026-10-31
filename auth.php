<?php
require_once __DIR__ . '/lib.php';

if (empty($_SESSION['user_id'])) {
    go('index.php');
}
$userID = (int) $_SESSION['user_id'];
