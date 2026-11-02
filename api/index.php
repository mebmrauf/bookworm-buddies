<?php
// Vercel sends every request here. It runs the matching page from public/, or serves a file from it.
$public = realpath(__DIR__ . '/../public');
$target = $public . '/' . ltrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/');

// /home works like /home.php, and a folder like /admin/ opens its index page.
if (is_dir($target)) {
    $target = rtrim($target, '/') . '/index.php';
} elseif (!file_exists($target) && file_exists($target . '.php')) {
    $target .= '.php';
}

// Only files inside public/, so a crafted path can't reach the code or settings beside it.
$real = realpath($target);
if ($real === false || !str_starts_with($real, $public . '/') || !is_file($real)) {
    http_response_code(404);
    exit('404 Not Found');
}

if (str_ends_with($real, '.php')) {
    chdir(dirname($real));
    require $real;
    exit();
}

$mimes = [
    'css' => 'text/css',
    'js' => 'application/javascript',
    'json' => 'application/json',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    'ico' => 'image/x-icon',
    'html' => 'text/html',
    'woff2' => 'font/woff2',
];
header('Content-Type: ' . ($mimes[strtolower(pathinfo($real, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
readfile($real);
