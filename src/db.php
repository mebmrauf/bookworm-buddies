<?php
function config($key) {
    static $config = null;
    $config ??= require __DIR__ . '/../config.php';
    return $config[$key] ?? null;
}

$conn = mysqli_init();
$conn->ssl_set(null, null, config('ssl_ca'), null, null);
$conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);

try {
    $connected = $conn->real_connect(config('host'), config('user'), config('password'), config('database'), config('port'), null, MYSQLI_CLIENT_SSL);
} catch (mysqli_sql_exception $e) {
    $connected = false;
    error_log($e->getMessage());
}

if (!$connected) {
    http_response_code(503);
    die('Could not connect to the database.');
}

$conn->set_charset('utf8mb4');

function run($sql, $types = '', ...$params) {
    global $conn;
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt;
}
