<?php
// Vercel Serverless Entrypoint for Bookworm Buddies

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = ltrim($uri, '/');

if ($uri === '' || $uri === 'index.php') {
    $target = __DIR__ . '/../public/index.php';
} else {
    $target = __DIR__ . '/../public/' . $uri;
}

// Allow extensionless URLs (e.g. /home -> /home.php)
if (!file_exists($target) && file_exists($target . '.php')) {
    $target .= '.php';
}

if (file_exists($target) && !is_dir($target)) {
    if (str_ends_with($target, '.php')) {
        chdir(dirname($target));
        require $target;
        exit();
    }

    $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
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
    ];
    $contentType = $mimes[$ext] ?? 'application/octet-stream';
    header("Content-Type: $contentType");
    readfile($target);
    exit();
}

http_response_code(404);
echo "404 Not Found";

