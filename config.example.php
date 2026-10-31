<?php
// Copy to config.php and fill in the values from the Aiven service page.
// A PHP file, not .env, so a web request can never download the password.
return [
    'host' => 'your-service-your-project.aivencloud.com',
    'port' => 12345,
    'user' => 'avnadmin',
    'password' => 'your-password',
    'database' => 'defaultdb',
    'ssl_ca' => __DIR__ . '/ca.pem', // CA certificate from the same Aiven page

    // From the Cloudinary dashboard. Leave blank to run without image uploads.
    'cloudinary' => [
        'cloud_name' => '',
        'api_key' => '',
        'api_secret' => '',
    ],

    // Public address of the site, used for links in emails.
    'app_url' => 'http://localhost:8000',

    // Admin login at /admin. Create the hash with: php tools/hash-password.php
    'admin' => [
        'email' => '',
        'password_hash' => '',
    ],

    // Secret for the daily reminder link. Any long random text. Leave blank to turn the link off.
    'cron_token' => '',

    // Optional: shown in the footer so rights holders can ask for a cover to be removed.
    'contact_email' => '',

    // Email goes through Resend's HTTPS API: only EMAIL_PASS (your API key) and EMAIL_FROM are used.
    'resend' => [
        'SMTP_HOST' => '',
        'SMTP_PORT' => '',
        'EMAIL_USER' => '',
        'EMAIL_PASS' => '',
        'EMAIL_FROM' => 'Bookworm Buddies <verify@xyz.com>'
    ]
];
