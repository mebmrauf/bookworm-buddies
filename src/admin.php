<?php
require_once __DIR__ . '/bootstrap.php';

// Search engines have no business here.
header('X-Robots-Tag: noindex');

// Blank in config.php means the admin area doesn't exist.
function admin_config() {
    $admin = config('admin');
    return !empty($admin['email']) && !empty($admin['password_hash']) ? $admin : null;
}

function require_admin() {
    if (!admin_config()) {
        http_response_code(404);
        exit('Not found');
    }
    if (empty($_SESSION['is_admin'])) {
        go('/admin/login.php');
    }
}

function admin_log($action, $targetType, $targetID, $detail = null) {
    run("INSERT INTO admin_log (action, target_type, target_id, detail) VALUES (?, ?, ?, ?)", 'ssis', $action, $targetType, $targetID, $detail);
}
