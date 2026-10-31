<?php
// Prints a bcrypt hash for config.php: php tools/hash-password.php
if (PHP_SAPI !== 'cli') {
    exit;
}
echo 'Admin password: ';
system('stty -echo');
$password = rtrim((string) fgets(STDIN), "\r\n");
system('stty echo');
echo "\n";
if (strlen($password) < 8 || strlen($password) > 72) {
    exit("Please use 12-72 characters.\n");
}
echo "\nPaste this as password_hash in config.php, in single quotes:\n", password_hash($password, PASSWORD_BCRYPT), "\n";
