<?php
// Configuration is loaded from environment variables or .env.
// Copy .env.example to .env and configure your secrets there.
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$k, $v] = explode('=', $line, 2);
            $k = trim($k);
            $v = trim($v, " \t\n\r\0\x0B\"'");
            putenv("$k=$v");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
}

// Support connection string via MYSQL_URL or DATABASE_URL
$dbUrl = getenv('MYSQL_URL') ?: getenv('DATABASE_URL');
$dbConfig = [];
if ($dbUrl) {
    $parts = parse_url($dbUrl);
    $pathDb = isset($parts['path']) ? trim($parts['path'], '/') : '';
    $dbConfig = [
        'host' => $parts['host'] ?? '127.0.0.1',
        'port' => (int) ($parts['port'] ?? 4000),
        'user' => urldecode($parts['user'] ?? ''),
        'password' => urldecode($parts['pass'] ?? ''),
        'database' => ($pathDb !== '' && $pathDb !== 'sys' && $pathDb !== 'test') ? $pathDb : 'bookworm-buddies',
        'ssl_ca' => getenv('MYSQL_SSL_CA') ?: null,
    ];
}

return array_merge([
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('DB_PORT') ?: 4000),
    'user' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASSWORD') ?: '',
    'database' => getenv('DB_NAME') ?: 'bookworm-buddies',
    'ssl_ca' => getenv('MYSQL_SSL_CA') ?: null,

    // From the Cloudinary dashboard. Leave blank to run without image uploads.
    'cloudinary' => [
        'cloud_name' => getenv('CLOUDINARY_CLOUD_NAME') ?: '',
        'api_key' => getenv('CLOUDINARY_API_KEY') ?: '',
        'api_secret' => getenv('CLOUDINARY_API_SECRET') ?: '',
    ],

    // Admin login at /admin. Create the hash with: php tools/hash-password.php
    'admin' => [
        'email' => getenv('ADMIN_EMAIL') ?: '',
        'password_hash' => getenv('ADMIN_PASSWORD_HASH') ?: '',
    ],

    // Public address of the site, used for links in emails.
    'app_url' => getenv('APP_URL') ?: 'http://localhost:8000',

    // Secret for the daily reminder link. Any long random text. Leave blank to turn the link off.
    'cron_token' => getenv('CRON_TOKEN') ?: '',

    // Optional: shown in the footer so rights holders can ask for a cover to be removed.
    'contact_email' => getenv('CONTACT_EMAIL') ?: '',

    // Email goes through Resend's HTTPS REST API.
    'resend' => [
        'EMAIL_PASS' => getenv('EMAIL_PASS') ?: (getenv('RESEND_API_KEY') ?: ''),
        'EMAIL_FROM' => getenv('EMAIL_FROM') ?: 'Bookworm Buddies <verify@yourdomain.com>',
    ]
], $dbConfig);
