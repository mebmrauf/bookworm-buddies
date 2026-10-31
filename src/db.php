<?php
function config($key) {
    static $config = null;
    // The test suite points this at its own file to run a server with a known admin login.
    $config ??= require getenv('BOOKWORM_CONFIG') ?: __DIR__ . '/../config.php';
    return $config[$key] ?? null;
}

$conn = mysqli_init();
$conn->ssl_set(null, null, config('ssl_ca'), null, null);
$conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
// Set during the handshake, which saves a round trip per page.
$conn->options(MYSQLI_SET_CHARSET_NAME, 'utf8mb4');

try {
    // p: reuses an open connection, because a new encrypted one costs about 0.3s on every page.
    $connected = $conn->real_connect('p:' . config('host'), config('user'), config('password'), config('database'), config('port'), null, MYSQLI_CLIENT_SSL);
} catch (mysqli_sql_exception $e) {
    $connected = false;
    error_log($e->getMessage());
}

if (!$connected) {
    http_response_code(503);
    die('Could not connect to the database.');
}

class QueryResult {
    public $affected_rows;
    private $result;

    public function __construct($result, $affectedRows) {
        $this->result = $result;
        $this->affected_rows = $affectedRows;
    }

    public function get_result() {
        return $this->result;
    }
}

// Same protection as a prepared statement, by escaping values for the connection's character set.
// A prepared statement costs two trips to the database, and that's noticeable on every page.
function run($sql, $types = '', ...$params) {
    global $conn;
    if (strlen($types) !== count($params) || substr_count($sql, '?') !== count($params)) {
        throw new InvalidArgumentException('SQL placeholders and values do not match.');
    }
    $index = 0;
    $bound = preg_replace_callback('/\?/', function () use (&$index, $types, $params, $conn) {
        $value = $params[$index];
        $type = $types[$index++];
        if ($value === null) {
            return 'NULL';
        }
        return $type === 'i' ? (string) (int) $value : "'" . $conn->real_escape_string((string) $value) . "'";
    }, $sql);
    $result = $conn->query($bound);
    return new QueryResult($result, $conn->affected_rows);
}

// True once $key has been used $max times within the window. Otherwise records this use.
// Two requests at the same instant can both slip through, which is fine for slowing down abuse.
function rate_limited($key, $max, $seconds) {
    if (random_int(1, 200) === 1) {
        run("DELETE FROM rate_limits WHERE hit_at < NOW() - INTERVAL 2 DAY");
    }
    $used = run("SELECT COUNT(*) FROM rate_limits WHERE hit_key = ? AND hit_at > NOW() - INTERVAL ? SECOND", 'si', $key, $seconds)->get_result()->fetch_row()[0];
    if ($used >= $max) {
        return true;
    }
    run("INSERT INTO rate_limits (hit_key) VALUES (?)", 's', $key);
    return false;
}
