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
];
