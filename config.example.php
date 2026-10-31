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

    # resend verification
    'resend' => [
        'SMTP_HOST' => '',
        'SMTP_PORT' => '',
        'EMAIL_USER' => '',
        'EMAIL_PASS' => '',
        'EMAIL_FROM' => 'Bookworm Buddies <verify@xyz.com>'
    ]
];
