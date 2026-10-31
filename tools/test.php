<?php
// End-to-end checks: php tools/test.php
// Starts its own copy of the site on the bookworm_test database, so the real site's data is never involved.
// By default this sends no email and uploads nothing, so it can run freely without using up any service's limits.
// Add --real-services to also call Resend and Cloudinary for real. That uses quota, so save it for rare final checks.
if (PHP_SAPI !== 'cli') {
    exit;
}
$realServices = in_array('--real-services', $argv, true);
$testConfigFile = __DIR__ . '/test-config.php';
putenv("BOOKWORM_CONFIG=$testConfigFile");
if ($realServices) {
    putenv('BOOKWORM_REAL_SERVICES=1');
}
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/cloudinary.php';

if (!str_ends_with(config('database'), '_test')) {
    exit("Refusing to run: the tests create and delete data, so they need a database named *_test.\n");
}

$sitePort = 17000 + random_int(0, 999);
$base = "http://localhost:$sitePort";
$site = proc_open([PHP_BINARY, '-d', 'upload_max_filesize=10M', '-d', 'post_max_size=12M', '-S', "localhost:$sitePort", '-t', __DIR__ . '/../public'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $sitePipes);
usleep(800000);
$tag = 'zztest_' . bin2hex(random_bytes(3));
$failures = 0;
$checks = 0;

function check($name, $ok) {
    global $failures, $checks;
    $checks++;
    if (!$ok) {
        $failures++;
    }
    echo($ok ? 'ok    ' : 'FAIL  '), $name, "\n";
}

function db($sql, $types = '', ...$params) {
    global $conn;
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result();
}

$logStart = (int) db("SELECT COALESCE(MAX(logID), 0) FROM admin_log")->fetch_row()[0];

function cleanup() {
    global $tag, $logStart, $adminServer, $site;
    if (!empty($adminServer)) {
        proc_terminate($adminServer);
    }
    proc_terminate($site);
    db("DELETE FROM admin_log WHERE logID > ?", 'i', $logStart);
    db("DELETE FROM rate_limits");
    $covers = db("SELECT b.cover_public_id FROM books b JOIN collection c ON b.bookID = c.bookID JOIN users u ON c.userID = u.userID WHERE u.username LIKE CONCAT(?, '%')", 's', $tag)->fetch_all();
    $avatars = db("SELECT avatar_public_id FROM users WHERE username LIKE CONCAT(?, '%')", 's', $tag)->fetch_all();
    delete_images(array_merge(array_column($covers, 0), array_column($avatars, 0)));
    db("DELETE b FROM books b JOIN collection c ON b.bookID = c.bookID JOIN users u ON c.userID = u.userID WHERE u.username LIKE CONCAT(?, '%')", 's', $tag);
    db("DELETE FROM users WHERE username LIKE CONCAT(?, '%') OR email LIKE CONCAT('delivered+', ?, '%')", 'ss', $tag, $tag);
}
register_shutdown_function('cleanup');

// A browser: keeps its own cookies and follows redirects.
class Browser {
    private $jar;
    private $site;
    public $code;
    public $url;

    public function __construct($site = null) {
        global $base;
        $this->jar = tempnam(sys_get_temp_dir(), 'bw');
        $this->site = $site ?? $base;
    }

    private function request($path, $fields = null) {
        // The suite signs up far more than one person would. The limits have their own check below.
        if ($fields !== null && empty($GLOBALS['keepLimits'])) {
            db("DELETE FROM rate_limits WHERE hit_key LIKE '%:ip:%'");
        }
        $ch = curl_init($this->site . '/' . $path);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar]);
        if ($fields !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        }
        $body = curl_exec($ch);
        $this->code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $this->url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        return $body;
    }

    public function get($path) {
        return $this->request($path);
    }

    // Multipart form with file uploads: $files maps field name => local path.
    public function upload($path, $fields, $files, $formPage) {
        preg_match('/name="csrf" value="(\w+)"/', $this->get($formPage), $m);
        $ch = curl_init($this->site . '/' . $path);
        foreach ($files as $name => $file) {
            $fields[$name] = new CURLFile($file);
        }
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_POSTFIELDS => $fields + ['csrf' => $m[1] ?? '']]);
        $body = curl_exec($ch);
        $this->code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $this->url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        return $body;
    }

    public function rawPost($path, $fields) {
        return $this->request($path, $fields);
    }

    // Takes the CSRF token from $formPage, as a real form would.
    public function post($path, $fields, $formPage = 'profile.php') {
        preg_match('/name="csrf" value="(\w+)"/', $this->get($formPage), $m);
        return $this->request($path, $fields + ['csrf' => $m[1] ?? '']);
    }
}

// example.com addresses never reach Resend. Its test address does, and counts against the sending limit.
$realEmail = $realServices;
function test_email($suffix) {
    global $tag, $realEmail;
    return $realEmail ? "delivered+$tag$suffix@resend.dev" : "$tag$suffix@example.com";
}

function signup($browser, $suffix, $overrides = [], $activate = true) {
    global $tag;
    $email = $overrides['email'] ?? test_email($suffix);
    $html = $browser->post('signup.php', $overrides + ['name' => 'Test User', 'email' => $email, 'password' => 'correct-horse-1', 'agree' => '1'], 'signup.php');
    if ($activate) {
        db("UPDATE users SET username = ?, email_verified_at = NOW() WHERE email = ?", 'ss', "$tag$suffix", $email);
    }
    usleep(600000); // Resend allows only a few requests per second.
    return $html;
}

function signin($browser, $suffix, $password = 'correct-horse-1') {
    global $tag;
    return $browser->post('actions/signin.php', ['login' => "$tag$suffix", 'password' => $password], 'signin.php');
}

// Gives an account a link we know, since only its hash is ever stored.
function plant_token($userID, $hours = 1) {
    $token = bin2hex(random_bytes(32));
    db("UPDATE users SET verify_token_hash = ?, verify_expires_at = NOW() + INTERVAL ? HOUR WHERE userID = ?", 'sii', hash('sha256', $token), $hours, $userID);
    return $token;
}

function user_id($suffix) {
    global $tag;
    return db("SELECT userID FROM users WHERE username = ?", 's', "$tag$suffix")->fetch_row()[0] ?? null;
}

function add_book($browser, $title, $showcase = true) {
    $browser->post('add-book.php', ['title' => $title, 'author' => 'Some Author', 'year_published' => '1999'], 'add-book.php');
    $id = db("SELECT bookID FROM books WHERE title = ? ORDER BY bookID DESC LIMIT 1", 's', $title)->fetch_row()[0] ?? 0;
    if ($showcase) {
        $browser->post('actions/book-showcase.php', ['bookID' => $id, 'showcase' => '1']);
    }
    return $id;
}

function swap_status($id) {
    return db("SELECT status FROM swap WHERE swapID = ?", 'i', $id)->fetch_row()[0] ?? null;
}

function latest_swap($bookID) {
    return db("SELECT swapID FROM swap WHERE bookID = ? ORDER BY swapID DESC LIMIT 1", 'i', $bookID)->fetch_row()[0] ?? null;
}

// ---- Cloudinary signing, using the worked example from Cloudinary's docs
check('cloudinary: signature matches the documented example', cloudinary_signature(
    ['timestamp' => 1315060510, 'public_id' => 'sample_image', 'eager' => 'w_400,h_300,c_pad|w_260,h_200,c_crop'], 'abcd'
) === 'bfd09f95f331f558cbd1320e67aa8d488770583e');

// ---- Private files must not be reachable from the web
$anon = new Browser();
foreach (['config.php', 'ca.pem', 'demo-accounts.txt', 'database/schema.sql', 'src/db.php', 'tools/seed.php', '../config.php'] as $file) {
    $body = $anon->get($file);
    check("web: $file is not served", !preg_match('/<\?php|BEGIN CERTIFICATE|CREATE TABLE|username  password/', $body));
}

// ---- Phone app
$manifest = json_decode($anon->get('manifest.json'), true);
check('pwa: the manifest is valid', $anon->code === 200 && ($manifest['name'] ?? '') === 'Bookworm Buddies' && ($manifest['display'] ?? '') === 'standalone' && ($manifest['start_url'] ?? '') === '/');
$sizes = [];
foreach ($manifest['icons'] ?? [] as $icon) {
    $data = (new Browser())->get(ltrim($icon['src'], '/'));
    $info = @getimagesizefromstring($data);
    $sizes[] = $info && "{$info[0]}x{$info[1]}" === $icon['sizes'] ? $icon['purpose'] . $info[0] : 'bad';
}
check('pwa: icons exist at their stated sizes', $sizes === ['any192', 'any512', 'maskable512']);
$worker = $anon->get('sw.js');
check('pwa: the service worker is served, and never stores pages', $anon->code === 200 && str_contains($worker, "cache.add(OFFLINE_URL)") && substr_count($worker, 'cache.add') === 1 && !str_contains($worker, 'cache.put'));
check('pwa: the offline page exists', str_contains($anon->get('offline.html'), "waking up") && $anon->code === 200);
$home = $anon->get('');
check('pwa: pages link the manifest and icons', str_contains($home, 'rel="manifest"') && str_contains($home, 'apple-touch-icon') && str_contains($home, 'data-install'));

// The test database starts empty, so guests need one shared book, in a different city from the others.
db("INSERT INTO users (username, password, email, first_name, last_name, city, area, email_verified_at, rules_accepted_at) VALUES (?, 'x', ?, 'Sample', 'Owner', 'Sylhet', 'Zindabazar', NOW(), NOW())", 'ss', "{$tag}sample", "$tag-sample@example.com");
$sampleOwner = $conn->insert_id;
db("INSERT INTO books (title, author, year_published, cover_public_id) VALUES (?, 'Sample Author', 2000, 'sample')", 's', "$tag sample book");
db("INSERT INTO collection (userID, bookID, showcase, city) VALUES (?, ?, 1, 'Sylhet')", 'ii', $sampleOwner, $conn->insert_id);

// ---- Guests can look around
$html = $anon->get('');
check('guest: landing page loads', $anon->code === 200 && str_contains($html, 'How it works') && str_contains($html, 'Join free'));
check('guest: landing page shows a book quote', str_contains($html, 'Quote of the day') && str_contains($html, '<cite>'));
check('guest: landing page shows books', str_contains($html, 'book-card__title'));
$html = $anon->get('home.php');
check('guest: browse page is public', $anon->code === 200 && str_ends_with($anon->url, 'home.php') && str_contains($html, 'book-card__title'));
check('guest: requesting needs no form until signed in', !str_contains($html, 'actions/swap-request.php') && !str_contains($html, 'name="csrf"'));
check('guest: the city filter is offered', str_contains($html, 'name="city"') && str_contains($html, 'All cities'));
$anon->get('home.php?available=1');
check('guest: available filter is public', $anon->code === 200 && str_ends_with($anon->url, 'available=1'));
$publicBook = db("SELECT c.bookID, c.userID, u.email FROM collection c JOIN users u ON c.userID = u.userID LEFT JOIN swap s ON s.activeBookID = c.bookID WHERE c.showcase = 1 AND s.swapID IS NULL LIMIT 1")->fetch_assoc();
if ($publicBook) {
    $html = $anon->get("book.php?id={$publicBook['bookID']}");
    check('guest: book page is public', $anon->code === 200 && str_contains($html, 'Sign in to request') && str_contains($html, 'to leave feedback'));
    check('guest: is not treated as having requested the book', !str_contains($html, 'You requested this book'));
    $html = $anon->get("profile.php?userID={$publicBook['userID']}");
    check('guest: public profile shows no email', $anon->code === 200 && !str_contains($html, $publicBook['email']));
}
$html = $anon->get('search.php?q=' . urlencode('the'));
check('guest: can search books', $anon->code === 200 && str_ends_with(strtok($anon->url, '?'), 'search.php') && !str_contains($html, '<h2>People</h2>'));
foreach (['requests.php', 'add-book.php', 'profile.php', 'settings.php', 'edit-book.php?id=1'] as $page) {
    $anon->get($page);
    check("guest: $page redirects to sign in", str_ends_with($anon->url, 'signin.php'));
}
foreach (['actions/book-delete.php', 'actions/account-delete.php', 'actions/swap-update.php', 'actions/swap-request.php', 'actions/feedback-add.php', 'actions/feedback-reply.php', 'actions/feedback-delete.php', 'actions/book-showcase.php'] as $page) {
    $anon->rawPost($page, ['bookID' => 1]);
    check("guest: POST $page redirects to sign in", str_ends_with($anon->url, 'signin.php'));
}
foreach (['//evil.example', "/\t/evil.example", '/\\evil.example', 'https://evil.example', '/actions/logout.php'] as $bad) {
    check("signin: refuses next=$bad", str_contains($anon->get('signin.php?next=' . urlencode($bad)), 'name="next" value="/home.php"'));
}

// ---- Signup
$alice = new Browser();
$bob = new Browser();
$carol = new Browser();

$signupForm = $alice->get('signup.php');
check('signup: asks only name, email and password', str_contains($signupForm, 'name="name"') && str_contains($signupForm, 'name="email"') && str_contains($signupForm, 'name="password"') && !str_contains($signupForm, 'name="username"') && !str_contains($signupForm, 'name="dob"'));
check('signup: the rules checkbox is required', (bool) preg_match('/name="agree"[^>]* required/', $signupForm) && str_contains($signupForm, '/rules.php'));
$refused = $alice->post('signup.php', ['name' => 'No Agree', 'email' => test_email('noagree'), 'password' => 'correct-horse-1'], 'signup.php');
check('signup: refused without agreeing to the rules', str_contains($refused, 'agree to the community rules') && !db("SELECT 1 FROM users WHERE email = ?", 's', test_email('noagree'))->num_rows);
check('about: the page is public', str_contains($anon->get('about.php'), 'Get in touch') && $anon->code === 200);
check('rules: the page is public', str_contains($anon->get('rules.php'), 'Community rules') && $anon->code === 200);
check('signup: rejects a short password', str_contains(signup($alice, 'a', ['password' => 'short'], false), 'Password must be'));
check('signup: rejects a bad email', str_contains(signup($alice, 'a', ['email' => 'nope'], false), 'valid email'));
check('signup: rejects a blank name', str_contains(signup($alice, 'a', ['name' => '  '], false), 'enter your name'));
check('signup: wrong CSRF token refused', str_contains($alice->rawPost('signup.php', ['csrf' => 'bad', 'name' => 'T U', 'email' => test_email('x'), 'password' => 'correct-horse-1']), 'expired') && !db("SELECT 1 FROM users WHERE email = ?", 's', test_email('x'))->num_rows);

$html = signup($alice, 'a', ['name' => "Test O'Brien"], false);
$row = db("SELECT userID, username, first_name, last_name, password, email_verified_at, verify_sent_at, verify_token_hash FROM users WHERE email = ?", 's', test_email('a'))->fetch_assoc();
check('signup: goes to the check-your-email page', str_ends_with($alice->url, 'verify-email.php') && str_contains($html, 'Check your email'));
check('signup: splits the name, apostrophe included', $row['first_name'] === 'Test' && $row['last_name'] === "O'Brien");
check('signup: a username is generated', (bool) preg_match('/^test\.o\.brien[0-9]*$/', $row['username']));
check('signup: the time of agreeing is recorded', db("SELECT rules_accepted_at FROM users WHERE userID = ?", 'i', $row['userID'])->fetch_row()[0] !== null);
check('signup: the confirmation email counts as sent', $row['verify_sent_at'] !== null);
check('signup: the link is stored only as a hash', strlen((string) $row['verify_token_hash']) === 64);
$hash = $row['password'];
check('password stored as a hash', password_verify('correct-horse-1', $hash) && !str_contains($hash, 'correct-horse'));

signup($alice, 'dupe', ['name' => 'Test Duplicate', 'email' => test_email('a')], false);
check('signup: a used email makes no second account', db("SELECT COUNT(*) FROM users WHERE email = ?", 's', test_email('a'))->fetch_row()[0] == 1);
check('signup: a used email looks the same as a new one', str_ends_with($alice->url, 'verify-email.php'));
check('signup: a used email does not change the password', db("SELECT password FROM users WHERE userID = ?", 'i', $row['userID'])->fetch_row()[0] === $hash);
signup($alice, 'twin', ['name' => "Test O'Brien"], false);
$twin = db("SELECT username FROM users WHERE email = ?", 's', test_email('twin'))->fetch_row()[0] ?? '';
check('signup: the same name gets a different username', $twin !== '' && $twin !== $row['username']);

// ---- Email verification
$pending = new Browser();
$login = fn($browser, $password = 'correct-horse-1') => $browser->post('actions/signin.php', ['login' => test_email('a'), 'password' => $password], 'signin.php');
$login($pending);
check('verify: an unconfirmed account cannot sign in', str_ends_with($pending->url, 'verify-email.php'));
$pending->get('profile.php');
check('verify: and has no session', str_ends_with($pending->url, 'signin.php'));
$token = plant_token($row['userID']);
$html = $pending->get("verify.php?token=$token");
check('verify: opening the link alone confirms nothing', str_contains($html, 'Confirm my email') && db("SELECT email_verified_at FROM users WHERE userID = ?", 'i', $row['userID'])->fetch_row()[0] === null);
check('verify: a wrong link is refused', str_contains($pending->get('verify.php?token=' . str_repeat('a', 64)), "doesn't work"));
$html = $pending->post('verify.php', ['token' => $token], "verify.php?token=$token");
check('verify: pressing the button confirms', db("SELECT email_verified_at FROM users WHERE userID = ?", 'i', $row['userID'])->fetch_row()[0] !== null && str_contains($html, 'confirmed'));
check('verify: a link only works once', str_contains($pending->get("verify.php?token=$token"), "doesn't work"));
$login($pending);
check('verify: can sign in with email afterwards', str_ends_with($pending->url, 'home.php'));
db("UPDATE users SET username = ? WHERE userID = ?", 'si', "{$tag}a", $row['userID']);

$expired = plant_token(db("SELECT userID FROM users WHERE email = ?", 's', test_email('twin'))->fetch_row()[0], -1);
check('verify: an expired link is refused', str_contains($anon->get("verify.php?token=$expired"), "doesn't work"));
signup($alice, 'twin', ['name' => 'New Owner'], false);
check('signup: an expired unconfirmed account frees its email', db("SELECT first_name FROM users WHERE email = ?", 's', test_email('twin'))->fetch_row()[0] === 'New');
$sentBefore = db("SELECT verify_sent_at FROM users WHERE email = ?", 's', test_email('twin'))->fetch_row()[0];
$anon->post('verify-email.php', ['email' => test_email('twin')], 'verify-email.php');
check('verify: resending is limited to once a minute', db("SELECT verify_sent_at FROM users WHERE email = ?", 's', test_email('twin'))->fetch_row()[0] === $sentBefore);
check('verify: the resend answer reveals nothing', str_contains($anon->post('verify-email.php', ['email' => 'nobody-here@example.com'], 'verify-email.php'), 'If that email needs confirming'));
db("DELETE FROM users WHERE email = ?", 's', test_email('twin'));

// ---- Sign in
db("UPDATE users SET email = ? WHERE email = ?", 'ss', "{$tag}a@example.com", test_email('a'));
$realEmail = false;
$alice = new Browser();
signup($bob, 'b');
signup($carol, 'c');
signup(new Browser(), 'q');

check('signin: wrong password refused', str_contains(signin($alice, 'a', 'wrong-password'), 'Invalid email'));
signin($alice, 'a');
check('signin: correct password with username works', str_ends_with($alice->url, 'home.php'));
signin($bob, 'b');
signin($carol, 'c');
$alice->get('index.php');
check('signin: signed-in user skips the landing page', str_ends_with($alice->url, 'home.php'));
$redirected = new Browser();
$redirected->post('actions/signin.php', ['login' => "{$tag}b", 'password' => 'correct-horse-1', 'next' => '/requests.php'], 'signin.php');
check('signin: returns to the page they wanted', str_ends_with($redirected->url, 'requests.php'));

$limited = new Browser();
for ($i = 0; $i < 5; $i++) {
    signin($limited, 'q', 'wrong-password');
}
check('signin: blocked after 5 failures', str_contains(signin($limited, 'q'), 'Too many failed attempts') && !str_ends_with($limited->url, 'home.php'));

// ---- Username and optional details
$settings = fn($overrides = []) => $overrides + ['current_password' => 'correct-horse-1', 'first_name' => 'A', 'last_name' => 'B', 'email' => test_email('a'), 'username' => "{$tag}a", 'mobile' => '', 'address' => '', 'new_password' => ''];
check('username: a taken name is refused', str_contains($alice->post('settings.php', $settings(['username' => "{$tag}b"]), 'settings.php'), 'already taken'));
check('username: uniqueness ignores capitals', str_contains($alice->post('settings.php', $settings(['username' => strtoupper("{$tag}b")]), 'settings.php'), 'already taken'));
check('username: bad characters are refused', str_contains($alice->post('settings.php', $settings(['username' => "a b'c"]), 'settings.php'), 'Username must be'));
$alice->post('settings.php', $settings(['username' => "{$tag}renamed"]), 'settings.php');
check('username: can be changed', db("SELECT username FROM users WHERE email = ?", 's', test_email('a'))->fetch_row()[0] === "{$tag}renamed");
$alice->post('settings.php', $settings(), 'settings.php');
$alice->post('settings.php', $settings(['mobile' => '+880 1712-345678', 'address' => 'Dhaka']), 'settings.php');
$details = db("SELECT mobile, address FROM users WHERE email = ?", 's', test_email('a'))->fetch_assoc();
check('details: optional fields save', $details === ['mobile' => '+880 1712-345678', 'address' => 'Dhaka']);
foreach ([['mobile' => 'call me', 'mobile number'], ['address' => str_repeat('x', 256), 'Address can be']] as $bad) {
    $needle = $bad[0];
    unset($bad[0]);
    check("details: refuses a bad value ($needle)", str_contains($alice->post('settings.php', $settings($bad), 'settings.php'), $needle));
}
$alice->post('settings.php', $settings(), 'settings.php');
check('details: blank fields clear them', db("SELECT mobile FROM users WHERE email = ?", 's', test_email('a'))->fetch_row()[0] === null);
$alice->post('settings.php', $settings(['mobile' => '+880 1712-345678']), 'settings.php');
$ownProfile = $alice->get('profile.php');
check('details: the owner sees them', str_contains($ownProfile, '+880 1712-345678'));

// ---- Changing email
$newEmail = test_email('a2');
$alice->post('settings.php', $settings(['email' => $newEmail]), 'settings.php');
usleep(600000);
$emailRow = db("SELECT userID, email, pending_email FROM users WHERE username = ?", 's', "{$tag}a")->fetch_assoc();
check('email: stays the same until confirmed', $emailRow['email'] === test_email('a') && $emailRow['pending_email'] === $newEmail);
check('email: a taken address is refused', str_contains($alice->post('settings.php', $settings(['email' => test_email('b')]), 'settings.php'), 'already used'));
$token = plant_token($emailRow['userID']);
$alice->post('verify.php', ['token' => $token], "verify.php?token=$token");
$emailRow = db("SELECT email, pending_email FROM users WHERE username = ?", 's', "{$tag}a")->fetch_assoc();
check('email: changes after confirming', $emailRow['email'] === $newEmail && $emailRow['pending_email'] === null);
db("UPDATE users SET email = ? WHERE username = ?", 'ss', test_email('a'), "{$tag}a");

// ---- Finding 1: account takeover
$aliceID = user_id('a');
$bobID = user_id('b');
$carolID = user_id('c');
$bobBefore = db("SELECT email, password FROM users WHERE userID = ?", 'i', $bobID)->fetch_assoc();
$alice->post("settings.php?userID=$bobID", ['first_name' => 'Hacked', 'last_name' => 'Hacked', 'email' => test_email('a'), 'username' => "{$tag}a", 'new_password' => ''], 'settings.php');
check('profile: ?userID= cannot change another account', db("SELECT email, password FROM users WHERE userID = ?", 'i', $bobID)->fetch_assoc() === $bobBefore);
check('profile: blank password keeps the old one', db("SELECT password FROM users WHERE userID = ?", 'i', $aliceID)->fetch_row()[0] === $hash);
check('profile: the form never shows the password hash', !str_contains($alice->get('settings.php'), $hash));
check('profile: duplicate username refused', str_contains($alice->post('settings.php', ['first_name' => 'A', 'last_name' => 'B', 'email' => test_email('a'), 'username' => "{$tag}b", 'new_password' => ''], 'settings.php'), 'already taken'));
check('profile: changing password needs the current one', str_contains($alice->post('settings.php', ['first_name' => 'A', 'last_name' => 'B', 'email' => test_email('a'), 'username' => "{$tag}a", 'new_password' => 'brand-new-pass-1', 'current_password' => 'wrong'], 'settings.php'), 'current password is incorrect'));
$alice->post('settings.php', ['first_name' => 'A', 'last_name' => 'B', 'email' => test_email('a'), 'username' => "{$tag}a", 'new_password' => 'brand-new-pass-1', 'current_password' => 'correct-horse-1'], 'settings.php');
check('profile: password change works', password_verify('brand-new-pass-1', db("SELECT password FROM users WHERE userID = ?", 'i', $aliceID)->fetch_row()[0]));

// ---- Books
check('book: bad year refused', str_contains($alice->post('add-book.php', ['title' => 'T', 'author' => 'A', 'year_published' => 'abc'], 'add-book.php'), 'valid published year'));
check('book: over-long title refused, no crash', str_contains($alice->post('add-book.php', ['title' => str_repeat('x', 256), 'author' => 'A', 'year_published' => '2000'], 'add-book.php'), 'at most 255') && $alice->code === 200);
$long = str_repeat('L', 60) . $tag;
$longID = add_book($alice, $long, false);
check('book: 60+ character title now saves', $longID > 0);
$shown = add_book($alice, "$tag shown");
$hidden = add_book($alice, "$tag hidden", false);

// Findings 4 and 6
$bob->post('actions/book-delete.php', ['bookID' => $shown]);
check('book: another user cannot delete it', db("SELECT 1 FROM books WHERE bookID = ?", 'i', $shown)->num_rows === 1);
$bob->get("edit-book.php?id=$shown");
check('book: another user cannot open the edit page', str_ends_with($bob->url, 'profile.php'));
$bob->post("edit-book.php?id=$shown", ['title' => 'Hacked', 'author' => 'Hacked', 'year_published' => '2000']);
check('book: another user cannot edit it', db("SELECT title FROM books WHERE bookID = ?", 'i', $shown)->fetch_row()[0] === "$tag shown");
$bob->post('actions/book-showcase.php', ['bookID' => $hidden, 'showcase' => '1']);
check('book: another user cannot showcase it', db("SELECT showcase FROM collection WHERE bookID = ?", 'i', $hidden)->fetch_row()[0] == 0);
$alice->get("actions/book-delete.php?bookID=$longID");
check('csrf: a plain link does not delete a book', db("SELECT 1 FROM books WHERE bookID = ?", 'i', $longID)->num_rows === 1);
$alice->rawPost('actions/book-delete.php', ['bookID' => $longID, 'csrf' => 'forged']);
check('csrf: a forged form does not delete a book', db("SELECT 1 FROM books WHERE bookID = ?", 'i', $longID)->num_rows === 1);
$alice->post("edit-book.php?id=$longID", ['title' => "$tag edited", 'author' => 'A', 'year_published' => '2001']);
check('book: owner can edit it', db("SELECT title FROM books WHERE bookID = ?", 'i', $longID)->fetch_row()[0] === "$tag edited");
$alice->post('actions/book-delete.php', ['bookID' => $longID]);
check('book: owner can delete it', db("SELECT 1 FROM books WHERE bookID = ?", 'i', $longID)->num_rows === 0);

// ---- Finding 16: privacy
$visit = $bob->get("profile.php?userID=$aliceID");
check('privacy: visitor does not see email', !str_contains($visit, test_email('a')));
check('privacy: visitor does not see birth date', !str_contains($visit, '2000-01-15'));
check('privacy: visitor sees showcased books', str_contains($visit, "$tag shown"));
check('privacy: visitor does not see other books', !str_contains($visit, "$tag hidden"));
$bob->get("book.php?id=$hidden");
check('privacy: a hidden book page returns 404 to visitors', $bob->code === 404);
$alice->get("book.php?id=$hidden");
check('privacy: the owner can open their hidden book', $alice->code === 200);
// The card title class marks a result; the page heading also repeats the search term.
check('privacy: search hides non-showcased books', !str_contains($bob->get('search.php?q=' . urlencode("$tag hidden")), "book-card__title\">$tag hidden"));
check('search: owner finds their own hidden book', str_contains($alice->get('search.php?q=' . urlencode("$tag hidden")), "book-card__title\">$tag hidden"));
check('search: finds a showcased book by title', str_contains($bob->get('search.php?q=' . urlencode("$tag shown")), "book-card__title\">$tag shown"));
check('search: finds users', str_contains($bob->get("search.php?q={$tag}c"), "{$tag}c"));
check('search: % is not a wildcard', str_contains($bob->get('search.php?q=' . urlencode("%{$tag}zzz%")), 'Nothing found'));
check('search: 0 is a valid search', !str_contains($bob->get('search.php?q=0'), 'Please enter'));
check('sql: run() refuses mismatched placeholders', (function () { try { run('SELECT ?', 'ii', 1, 2); return false; } catch (InvalidArgumentException $e) { return true; } })());
check('search: output is escaped', !str_contains($bob->get('search.php?q=' . urlencode('<script>x</script>')), '<script>x'));

// ---- Findings 5, 7, 8: swaps
$alice->post('actions/swap-request.php', ['bookID' => $shown]);
check('swap: cannot request your own book', latest_swap($shown) === null);
$bob->post('actions/swap-request.php', ['bookID' => $hidden]);
check('swap: cannot request a non-showcased book', latest_swap($hidden) === null);
check('swap: book offered on Available Books', str_contains($bob->get('home.php?available=1&city=all'), "$tag shown"));

db("UPDATE users SET mobile = '+8801700000001', address = 'Owner Lane 1' WHERE userID = ?", 'i', $aliceID);
db("UPDATE users SET mobile = '+8801700000002', address = 'Requester Road 2' WHERE userID = ?", 'i', $bobID);
$hasContact = fn($html, $mobile, $address, $email) => str_contains($html, $mobile) || str_contains($html, $address) || str_contains($html, $email);
$bob->post('settings.php', ['first_name' => 'Test', 'last_name' => 'User', 'email' => test_email('b'), 'username' => "{$tag}b", 'mobile' => '+8801700000002', 'address' => 'Requester Road 2', 'city' => 'Chittagong', 'area' => 'Agrabad']);
check('location: saves a city and area', db("SELECT city, area FROM users WHERE userID = ?", 'i', $bobID)->fetch_assoc() === ['city' => 'Chittagong', 'area' => 'Agrabad']);
check('location: refuses a made-up city', str_contains($bob->post('settings.php', ['first_name' => 'Test', 'last_name' => 'User', 'email' => test_email('b'), 'username' => "{$tag}b", 'city' => 'Atlantis']), 'city from the list'));
$bob->post('settings.php', ['first_name' => 'Test', 'last_name' => 'User', 'email' => test_email('b'), 'username' => "{$tag}b", 'mobile' => '+8801700000002', 'address' => 'Requester Road 2', 'city' => 'Chittagong', 'area' => 'Agrabad']);
$alice->post('settings.php', ['first_name' => 'A', 'last_name' => 'B', 'email' => test_email('a'), 'username' => "{$tag}a", 'city' => 'Dhaka', 'area' => 'Dhanmondi']);
check('location: a city change reaches the owner\'s books', db("SELECT city FROM collection WHERE bookID = ?", 'i', $shown)->fetch_row()[0] === 'Dhaka');
check('location: the book card shows the owner\'s area', str_contains($bob->get('home.php?city=all'), 'Dhanmondi, Dhaka'));
check('location: the city filter narrows results', str_contains($bob->get('home.php?city=Dhaka'), "$tag shown") && !str_contains($bob->get('home.php?city=Sylhet'), "$tag shown"));
check('location: an unknown city filter shows all', str_contains($bob->get('home.php?city=' . urlencode("x' OR 1=1")), 'across all cities'));
check('location: a visitor sees the area on the profile', str_contains($carol->get("profile.php?userID=$aliceID"), 'Dhanmondi, Dhaka'));
check('location: a person without a city is prompted', str_contains($carol->get('home.php'), 'Add your city'));
db("UPDATE users SET mobile = '+8801700000001', address = 'Owner Lane 1' WHERE userID = ?", 'i', $aliceID);
$bob->post('actions/swap-request.php', ['bookID' => $shown]);
$swap = latest_swap($shown);
check('contact: requester sees no owner details while pending', !$hasContact($bob->get('requests.php'), '+8801700000001', 'Owner Lane 1', test_email('a')));
check('contact: owner sees no requester details while pending', !$hasContact($alice->get('requests.php'), '+8801700000002', 'Requester Road 2', test_email('b')));
check('swap: request created as pending', swap_status($swap) === 'pending');
check('swap: goes to the right owner', db("SELECT ownerID FROM swap WHERE swapID = ?", 'i', $swap)->fetch_row()[0] == $aliceID);
check('swap: leaves Available Books once requested', !str_contains($bob->get('home.php?available=1&city=all'), "$tag shown"));
check('swap: Home shows Not Available', str_contains($carol->get('home.php'), 'Not Available'));
$carol->post('actions/swap-request.php', ['bookID' => $shown]);
check('swap: second open request refused', latest_swap($shown) == $swap);
$reqPage = $alice->get('requests.php');
check('swap: owner sees the requester by name', str_contains($reqPage, "{$tag}b") && !str_contains($reqPage, 'Requester ID'));
check('swap: no email shown before accepting', !str_contains($reqPage, test_email('b')));

$bob->post('actions/swap-update.php', ['swapID' => $swap, 'action' => 'accept']);
check('swap: requester cannot accept their own request', swap_status($swap) === 'pending');
$carol->post('actions/swap-update.php', ['swapID' => $swap, 'action' => 'accept']);
check('swap: stranger cannot accept', swap_status($swap) === 'pending');
$carol->post('actions/swap-update.php', ['swapID' => $swap, 'action' => 'decline']);
check('swap: stranger cannot decline', swap_status($swap) === 'pending');
$carol->post('actions/swap-update.php', ['swapID' => $swap, 'action' => 'cancel']);
check('swap: stranger cannot cancel', swap_status($swap) === 'pending');
$alice->get("actions/swap-update.php?swapID=$swap&action=accept");
check('csrf: a plain link does not accept a swap', swap_status($swap) === 'pending');
$alice->post('actions/swap-update.php', ['swapID' => $swap, 'action' => 'return']);
check('swap: cannot return before accepting', swap_status($swap) === 'pending');
$alice->post('actions/swap-update.php', ['swapID' => $swap, 'action' => 'accept']);
check('swap: owner can accept', swap_status($swap) === 'accepted');
check('swap: the chat link shows after accepting', str_contains($bob->get('requests.php'), "chat.php?swap=$swap"));
$requesterView = $bob->get('requests.php');
check('contact: email and mobile are never shared automatically', !str_contains($requesterView, test_email('a')) && !str_contains($requesterView, '+8801700000001'));
check('contact: the requester gets a chat link instead', str_contains($requesterView, "chat.php?swap=$swap"));
check('contact: requester never sees the owner address', !str_contains($requesterView, 'Owner Lane 1'));
$ownerView = $alice->get('requests.php');
check('contact: the owner sees no email or mobile either', !str_contains($ownerView, test_email('b')) && !str_contains($ownerView, '+8801700000002'));
check('contact: the owner gets a chat link too', str_contains($ownerView, "chat.php?swap=$swap"));
check('contact: a meet-up never shares the home address', !str_contains($ownerView, 'Requester Road 2'));
check('contact: safety advice shows for a meet-up', str_contains($ownerView, 'meet in a public place'));
check('contact: an unrelated user sees nothing', !$hasContact($carol->get('requests.php'), '+8801700000001', 'Owner Lane 1', test_email('a')));
check('contact: nothing on the public profile', !$hasContact($carol->get("profile.php?userID=$aliceID"), '+8801700000001', 'Owner Lane 1', test_email('a')));
check('contact: nothing on the book page', !$hasContact($carol->get("book.php?id=$shown"), '+8801700000001', 'Owner Lane 1', test_email('a')));
check('swap: shows in the requester\'s Swapped Books', str_contains($bob->get('profile.php?show=borrowed'), "$tag shown"));
$alice->post('actions/book-delete.php', ['bookID' => $shown]);
check('swap: a lent book cannot be deleted', db("SELECT 1 FROM books WHERE bookID = ?", 'i', $shown)->num_rows === 1);
$alice->post('actions/swap-update.php', ['swapID' => $swap, 'action' => 'accept']);
$bob->post('actions/swap-update.php', ['swapID' => $swap, 'action' => 'return']);
check('swap: requester cannot mark it returned', swap_status($swap) === 'accepted');
$alice->post('actions/swap-update.php', ['swapID' => $swap, 'action' => 'return']);
check('swap: owner can mark it returned', swap_status($swap) === 'returned');
check('contact: hidden again after return', !$hasContact($bob->get('requests.php'), '+8801700000001', 'Owner Lane 1', test_email('a')) && !$hasContact($alice->get('requests.php'), '+8801700000002', 'Requester Road 2', test_email('b')));
$alice->post('actions/swap-update.php', ['swapID' => $swap, 'action' => 'accept']);
check('swap: a returned swap cannot be reopened', swap_status($swap) === 'returned');

$availablePage = $carol->get('home.php?available=1');
check('swap: available again after return', str_contains($availablePage, "$tag shown"));
$carol->post('actions/swap-request.php', ['bookID' => $shown]);
$swap2 = latest_swap($shown);
$alice->post('actions/swap-update.php', ['swapID' => $swap2, 'action' => 'decline']);
check('swap: owner can decline', swap_status($swap2) === 'declined');
check('swap: no duplicates after several old swaps', substr_count($bob->get('home.php?available=1&city=all'), "book-card__title\">$tag shown") === 1);
$carol->post('actions/swap-request.php', ['bookID' => $shown]);
$swap3 = latest_swap($shown);
$alice->post('actions/swap-update.php', ['swapID' => $swap3, 'action' => 'accept']);
check('swap: lent book with old declined swap stays hidden (finding 8)', !str_contains($bob->get('home.php?available=1&city=all'), "$tag shown"));
$alice->post('actions/swap-update.php', ['swapID' => $swap3, 'action' => 'return']);
$bob->post('actions/swap-request.php', ['bookID' => $shown]);
$swap4 = latest_swap($shown);
$bob->post('actions/swap-update.php', ['swapID' => $swap4, 'action' => 'cancel']);
check('swap: requester can cancel', swap_status($swap4) === null);

// A reused database connection must keep its character set.
$bangla = 'বইটি খুব ভালো ' . $tag;
for ($i = 0; $i < 3; $i++) {
    $bob->post('actions/feedback-add.php', ['bookID' => $shown, 'comment' => $bangla]);
}
$stored = db("SELECT comment, HEX(comment) AS hex FROM feedback WHERE bookID = ? ORDER BY feedbackID DESC LIMIT 1", 'i', $shown)->fetch_assoc();
check('charset: Bangla text survives a reused connection', ($stored['comment'] ?? '') === $bangla && $stored['hex'] === strtoupper(bin2hex($bangla)) && str_contains($bob->get("book.php?id=$shown"), $bangla));
db("DELETE FROM feedback WHERE bookID = ?", 'i', $shown);

// ---- Live search
$suggest = fn($browser, $q) => json_decode($browser->get('search-suggest.php?q=' . urlencode($q)), true);
$live = $suggest($bob, "$tag shown");
check('live search: finds a book as you type', ($live['books'][0]['title'] ?? '') === "$tag shown" && $bob->code === 200);
check('live search: a short query returns nothing', $suggest($bob, 'a') === ['books' => [], 'people' => []]);
check('live search: hidden books stay hidden', $suggest($bob, "$tag hidden")['books'] === []);
check('live search: the owner finds their hidden book', count($suggest($alice, "$tag hidden")['books']) === 1);
check('live search: members can find people', ($suggest($bob, "{$tag}c")['people'][0]['username'] ?? '') === "{$tag}c");
check('live search: guests can\'t find people', $suggest(new Browser(), "{$tag}c")['people'] === []);
check('live search: no emails or private details', !preg_match('/@example|@resend|address|mobile/', $bob->get('search-suggest.php?q=' . urlencode($tag))));
check('live search: wildcards are plain text', $suggest($bob, "%{$tag}zzz%")['books'] === []);
check('live search: injection is plain text', $suggest($bob, "' OR 1=1 -- ")['books'] === [] && $bob->code === 200);
check('live search: an array is refused safely', $bob->get('search-suggest.php?q[]=x') !== false && $bob->code === 200);
check('live search: results are limited', count($suggest($bob, 'the')['books']) <= 6);

// ---- Search at scale
db("UPDATE books SET title = ? WHERE bookID = ?", 'si', "The Great $tag Story", $shown);
check('search: words like "the" do not break a search', str_contains($bob->get('search.php?q=' . urlencode("the great $tag")), "The Great $tag Story"));
check('search: a word beginning matches', ($suggest($bob, substr($tag, 0, 11))['books'][0]['title'] ?? '') !== '');
check('search: a search for only "the" still works', $bob->get('search.php?q=the') !== false && $bob->code === 200 && is_array($suggest($bob, 'the')['books']));
db("UPDATE books SET title = ? WHERE bookID = ?", 'si', "$tag shown", $shown);

// ---- Blog
$blogFields = fn($overrides = []) => $overrides + ['title' => "Why I love $tag", 'book_title' => "$tag shown", 'book_author' => 'Some Author', 'body' => str_repeat("A book that stayed with me. ", 6) . "\n\nIt's a <b>second</b> paragraph, for $tag."];
$anonBlog = new Browser();
check('blog: guests can read the list', str_contains($anonBlog->get('blog.php'), 'Write a post') && $anonBlog->code === 200);
$anonBlog->get('write-post.php');
check('blog: guests must sign in to write', str_ends_with($anonBlog->url, 'signin.php'));
check('blog: a short post is refused', str_contains($bob->post('write-post.php', $blogFields(['body' => 'Too short']), 'write-post.php'), 'characters'));
check('blog: a missing book is refused', str_contains($bob->post('write-post.php', $blogFields(['book_title' => '']), 'write-post.php'), 'name and author'));
check('blog: too long a post is refused', str_contains($bob->post('write-post.php', $blogFields(['body' => str_repeat('x', 10001)]), 'write-post.php'), 'characters'));
$bob->rawPost('write-post.php', $blogFields(['csrf' => 'forged']));
check('blog: a forged form cannot publish', db("SELECT COUNT(*) FROM posts WHERE userID = ?", 'i', $bobID)->fetch_row()[0] == 0);
$bob->post('write-post.php', $blogFields(), 'write-post.php');
$postRow = db("SELECT postID, excerpt, body FROM posts WHERE userID = ? ORDER BY postID DESC LIMIT 1", 'i', $bobID)->fetch_assoc();
$postID = (int) ($postRow['postID'] ?? 0);
check('blog: publishing opens the post', $postID > 0 && str_contains($bob->url, "post.php?id=$postID"));
check('blog: a short excerpt is saved for lists', mb_strlen($postRow['excerpt']) <= 201 && !str_contains($postRow['excerpt'], "\n"));
$blogPage = $anonBlog->get('blog.php');
check('blog: it appears on the list for guests', str_contains($blogPage, "Why I love $tag"));
$postPage = $anonBlog->get("post.php?id=$postID");
check('blog: guests can read a post', $anonBlog->code === 200 && str_contains($postPage, "Why I love $tag"));
check('blog: text is escaped and split into paragraphs', str_contains($postPage, '&lt;b&gt;second') && !str_contains($postPage, '<b>second') && substr_count($postPage, "stayed with me.") >= 6);
check('blog: guests see no report or edit controls', !str_contains($postPage, 'actions/report.php') && !str_contains($postPage, 'write-post.php?id='));
check('blog: the post links to a copy that can be requested', str_contains($postPage, "/book.php?id=$shown"));
check('blog: search finds it by book', str_contains($anonBlog->get('blog.php?q=' . urlencode($tag)), "Why I love $tag"));
check('blog: search with no match is handled', str_contains($anonBlog->get('blog.php?q=' . urlencode("{$tag}zzz")), 'No posts found'));
check('blog: it appears on the landing page', str_contains($anonBlog->get(''), "Why I love $tag"));
check('blog: it appears on the author\'s profile', str_contains($anonBlog->get("profile.php?userID=$bobID"), "Why I love $tag"));
check('blog: a bad page value is handled', $anonBlog->get('blog.php?before=abc&q[]=x') !== false && $anonBlog->code === 200);
$anonBlog->get('post.php?id=999999999');
check('blog: a missing post returns 404', $anonBlog->code === 404);
$carol->get("write-post.php?id=$postID");
check('blog: others cannot open the edit page', str_ends_with($carol->url, 'blog.php'));
$carol->post("write-post.php?id=$postID", $blogFields(['title' => 'Hijacked']), 'write-post.php');
check('blog: others cannot edit it', db("SELECT title FROM posts WHERE postID = ?", 'i', $postID)->fetch_row()[0] === "Why I love $tag");
$carol->post('actions/post-delete.php', ['postID' => $postID]);
check('blog: others cannot delete it', db("SELECT 1 FROM posts WHERE postID = ?", 'i', $postID)->num_rows === 1);
$bob->post("write-post.php?id=$postID", $blogFields(['title' => "Still love $tag"]), "write-post.php?id=$postID");
check('blog: the author can edit it', db("SELECT title FROM posts WHERE postID = ?", 'i', $postID)->fetch_row()[0] === "Still love $tag");
db("UPDATE posts SET title = ? WHERE postID = ?", 'si', "Why I love $tag", $postID);
for ($i = 0; $i < 4; $i++) {
    db("INSERT INTO posts (userID, title, book_title, book_author, body, excerpt) VALUES (?, 'Extra', 'B', 'A', 'body', 'e')", 'i', $bobID);
}
check('blog: publishing is limited per day', str_contains($bob->post('write-post.php', $blogFields(['title' => 'One too many']), 'write-post.php'), 'posts a day'));
db("DELETE FROM posts WHERE userID = ? AND title = 'Extra'", 'i', $bobID);

// ---- Handover choice, note and courier
$shareForm = $alice->get('add-book.php');
check('handover: the add form offers all three options', str_contains($shareForm, 'value="meet"') && str_contains($shareForm, 'value="courier"') && str_contains($shareForm, 'value="both"'));
check('share: new books are shared by default', str_contains($shareForm, 'name="share" value="1" checked'));
$alice->post('add-book.php', ['title' => "$tag either", 'author' => 'A', 'year_published' => '2000', 'handover' => 'both', 'share' => '1'], 'add-book.php');
$either = db("SELECT b.bookID, b.handover, c.showcase FROM books b JOIN collection c ON b.bookID = c.bookID WHERE b.title = ?", 's', "$tag either")->fetch_assoc();
check('handover: saved on the book, and shared', $either['handover'] === 'both' && $either['showcase'] == 1);
$alice->post('edit-book.php?id=' . $either['bookID'], ['title' => "$tag either", 'author' => 'A', 'year_published' => '2000', 'handover' => 'both'], 'edit-book.php?id=' . $either['bookID']);
$page = $bob->get('book.php?id=' . $either['bookID']);
check('handover: the requester chooses when both are offered', str_contains($page, 'How would you like to get it?') && str_contains($page, 'name="message"'));
$bob->post('actions/swap-request.php', ['bookID' => $either['bookID']]);
check('handover: a choice is required when both are offered', latest_swap($either['bookID']) === null);
$bob->post('actions/swap-request.php', ['bookID' => $either['bookID'], 'handover' => 'teleport']);
check('handover: a made-up method is refused', latest_swap($either['bookID']) === null);
$bob->post('actions/swap-request.php', ['bookID' => $either['bookID'], 'handover' => 'meet', 'message' => str_repeat('x', 301)]);
check('note: over 300 characters is refused', latest_swap($either['bookID']) === null);
db("UPDATE users SET address = NULL WHERE userID = ?", 'i', $bobID);
$bob->post('actions/swap-request.php', ['bookID' => $either['bookID'], 'handover' => 'courier']);
check('courier: needs a delivery address first', latest_swap($either['bookID']) === null && str_ends_with($bob->url, 'settings.php'));
db("UPDATE users SET address = 'Requester Road 2' WHERE userID = ?", 'i', $bobID);
$note = "Can you send it <b>next week</b>?";
$bob->post('actions/swap-request.php', ['bookID' => $either['bookID'], 'handover' => 'courier', 'message' => $note]);
$courierSwap = latest_swap($either['bookID']);
$saved = db("SELECT handover, message FROM swap WHERE swapID = ?", 'i', $courierSwap)->fetch_assoc();
check('courier: method and note are saved', $saved === ['handover' => 'courier', 'message' => $note]);
$ownerView = $alice->get('requests.php');
check('note: the owner reads it, safely escaped', str_contains($ownerView, htmlspecialchars($note)) && !str_contains($ownerView, '<b>next week'));
check('courier: address stays hidden before accepting', !str_contains($ownerView, 'Requester Road 2'));
$alice->post('actions/swap-update.php', ['swapID' => $courierSwap, 'action' => 'accept']);
check('courier: the owner sees the delivery address once accepted', str_contains($alice->get('requests.php'), 'Requester Road 2'));
check('courier: the borrower still never sees the owner address', !str_contains($bob->get('requests.php'), 'Owner Lane 1'));
$alice->post('actions/swap-update.php', ['swapID' => $courierSwap, 'action' => 'return']);
check('courier: the address is hidden again after return', !str_contains($alice->get('requests.php'), 'Requester Road 2'));
$alice->post('edit-book.php?id=' . $either['bookID'], ['title' => "$tag either", 'author' => 'A', 'year_published' => '2000', 'handover' => 'courier'], 'edit-book.php?id=' . $either['bookID']);
check('handover: the owner can change it', db("SELECT handover FROM books WHERE bookID = ?", 'i', $either['bookID'])->fetch_row()[0] === 'courier');
$bob->post('actions/swap-request.php', ['bookID' => $either['bookID'], 'handover' => 'meet']);
check('handover: a request can\'t override a courier-only book', db("SELECT handover FROM swap WHERE swapID = ?", 'i', latest_swap($either['bookID']))->fetch_row()[0] === 'courier');
$bob->post('actions/swap-update.php', ['swapID' => latest_swap($either['bookID']), 'action' => 'cancel']);

// ---- Findings 14, 15: feedback
$comment = "Don't miss it, a<b \"really\"";
$bob->post('actions/feedback-add.php', ['bookID' => $shown, 'comment' => $comment]);
$feedback = db("SELECT feedbackID, comment FROM feedback WHERE bookID = ?", 'i', $shown)->fetch_assoc();
check('feedback: text saved exactly as typed', ($feedback['comment'] ?? '') === $comment);
check('feedback: text shown correctly and escaped', str_contains($bob->get("book.php?id=$shown"), htmlspecialchars($comment)));
check('feedback: over 500 characters refused', str_contains($bob->post('actions/feedback-add.php', ['bookID' => $shown, 'comment' => str_repeat('x', 501)]), '1-500'));
$alice->post('actions/feedback-add.php', ['bookID' => $shown, 'comment' => 'my own']);
check('feedback: owner cannot review their own book', db("SELECT COUNT(*) FROM feedback WHERE bookID = ?", 'i', $shown)->fetch_row()[0] == 1);
$bob->post('actions/feedback-add.php', ['bookID' => $hidden, 'comment' => 'sneaky']);
check('feedback: refused on a non-showcased book', db("SELECT COUNT(*) FROM feedback WHERE bookID = ?", 'i', $hidden)->fetch_row()[0] == 0);
$carol->post('actions/feedback-reply.php', ['feedbackID' => $feedback['feedbackID'], 'reply' => 'hijack']);
check('feedback: stranger cannot reply', db("SELECT reply FROM feedback WHERE feedbackID = ?", 'i', $feedback['feedbackID'])->fetch_row()[0] === null);
$alice->post('actions/feedback-reply.php', ['feedbackID' => $feedback['feedbackID'], 'reply' => 'Thanks!']);
check('feedback: owner can reply', db("SELECT reply FROM feedback WHERE feedbackID = ?", 'i', $feedback['feedbackID'])->fetch_row()[0] === 'Thanks!');
$carol->post('actions/feedback-delete.php', ['feedbackID' => $feedback['feedbackID']]);
check('feedback: stranger cannot delete', db("SELECT 1 FROM feedback WHERE feedbackID = ?", 'i', $feedback['feedbackID'])->num_rows === 1);
$bob->post('actions/feedback-delete.php', ['feedbackID' => $feedback['feedbackID']]);
check('feedback: author can delete', db("SELECT 1 FROM feedback WHERE feedbackID = ?", 'i', $feedback['feedbackID'])->num_rows === 0);

// ---- Real Cloudinary uploads, skipped when no keys are configured
function cloudinary_asset($publicID) {
    $c = config('cloudinary');
    $ch = curl_init("https://api.cloudinary.com/v1_1/{$c['cloud_name']}/resources/image/upload/" . implode('/', array_map('rawurlencode', explode('/', $publicID))));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => "{$c['api_key']}:{$c['api_secret']}"]);
    $body = curl_exec($ch);
    return curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200 ? json_decode($body, true) : null;
}

function test_image($color) {
    $image = imagecreatetruecolor(400, 600);
    imagefill($image, 0, 0, imagecolorallocate($image, ...$color));
    $file = tempnam(sys_get_temp_dir(), 'bw') . '.png';
    imagepng($image, $file);
    return $file;
}

function book_image($id) {
    return db("SELECT cover_public_id FROM books WHERE bookID = ?", 'i', $id)->fetch_row()[0] ?? null;
}

if (cloudinary_enabled() && $realServices) {
    $bookFields = ['title' => "$tag photo", 'author' => 'Some Author', 'year_published' => '2001'];
    $red = test_image([180, 40, 40]);
    $blue = test_image([40, 40, 180]);

    check('upload: the add-book form offers a cover field', str_contains($alice->get('add-book.php'), 'name="cover"'));

    $html = $alice->upload('add-book.php', $bookFields, ['cover' => $red], 'add-book.php');
    $photoBook = (int) db("SELECT bookID FROM books WHERE title = ? ORDER BY bookID DESC LIMIT 1", 's', "$tag photo")->fetch_row()[0];
    $first = book_image($photoBook);
    check('upload: a cover is saved with the book', (bool) $first);
    check('upload: the public ID is in the covers folder', str_starts_with((string) $first, 'bookworm-buddies/covers/'));
    $asset = $first ? cloudinary_asset($first) : null;
    check('upload: the image exists on Cloudinary', $asset !== null);
    check('upload: it is in the covers folder on Cloudinary', ($asset['asset_folder'] ?? $asset['folder'] ?? '') === 'bookworm-buddies/covers');
    check('upload: the book page shows the Cloudinary image', str_contains($alice->get("book.php?id=$photoBook"), 'res.cloudinary.com'));
    $ch = curl_init(image_url((string) $first, 300, 450));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true]);
    $delivered = curl_exec($ch);
    check('upload: the delivery URL returns an image', curl_getinfo($ch, CURLINFO_RESPONSE_CODE) === 200 && str_starts_with((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE), 'image/') && strlen($delivered) > 0);

    $alice->upload("edit-book.php?id=$photoBook", $bookFields, ['cover' => $blue], "edit-book.php?id=$photoBook");
    $second = book_image($photoBook);
    check('upload: replacing changes the cover', $second && $second !== $first);
    check('upload: replacing deletes the old image', cloudinary_asset($first) === null);

    $alice->post("edit-book.php?id=$photoBook", $bookFields, "edit-book.php?id=$photoBook");
    check('upload: saving without a file keeps the cover', book_image($photoBook) === $second);

    $alice->post("edit-book.php?id=$photoBook", $bookFields + ['remove_cover' => '1'], "edit-book.php?id=$photoBook");
    check('upload: removing clears the cover', book_image($photoBook) === null);
    check('upload: removing deletes the image', cloudinary_asset($second) === null);

    $fake = tempnam(sys_get_temp_dir(), 'bw') . '.png';
    file_put_contents($fake, '<?php echo "not an image";');
    check('upload: a fake image is refused', str_contains($alice->upload('add-book.php', $bookFields, ['cover' => $fake], 'add-book.php'), 'JPG, PNG or WebP'));
    $big = tempnam(sys_get_temp_dir(), 'bw') . '.png';
    file_put_contents($big, str_repeat('x', 10 * 1024 * 1024 + 1024));
    check('upload: an oversized image is refused', str_contains($alice->upload('add-book.php', $bookFields, ['cover' => $big], 'add-book.php'), '10 MB or smaller'));
    check('upload: refused images do not create a book', db("SELECT COUNT(*) FROM books WHERE title = ?", 's', "$tag photo")->fetch_row()[0] == 1);

    // Random pixels don't compress well, so this is a worst case for the size limit.
    $noisy = imagecreatetruecolor(1800, 1400);
    for ($y = 0; $y < 1400; $y++) {
        for ($x = 0; $x < 1800; $x++) {
            imagesetpixel($noisy, $x, $y, random_int(0, 0xFFFFFF));
        }
    }
    $largeFile = tempnam(sys_get_temp_dir(), 'bw') . '.png';
    imagepng($noisy, $largeFile);
    check('compress: the test photo is over the limit and over HD', filesize($largeFile) > CLOUDINARY_MAX_BYTES && filesize($largeFile) <= MAX_INPUT_BYTES);
    $alice->upload("edit-book.php?id=$photoBook", $bookFields, ['cover' => $largeFile], "edit-book.php?id=$photoBook");
    $hdID = book_image($photoBook);
    $hdAsset = $hdID ? cloudinary_asset($hdID) : null;
    check('compress: the long edge is cut to HD', ($hdAsset['width'] ?? 9999) === 1280 && ($hdAsset['height'] ?? 0) === 996);
    check('compress: the stored file fits the limit', ($hdAsset['bytes'] ?? PHP_INT_MAX) <= CLOUDINARY_MAX_BYTES);
    check('compress: it is stored as a JPEG', ($hdAsset['format'] ?? '') === 'jpg');

    $small = imagecreatetruecolor(100, 150);
    imagesavealpha($small, true);
    imagefill($small, 0, 0, imagecolorallocatealpha($small, 0, 0, 0, 127));
    $smallFile = tempnam(sys_get_temp_dir(), 'bw') . '.png';
    imagepng($small, $smallFile);
    $alice->upload("edit-book.php?id=$photoBook", $bookFields, ['cover' => $smallFile], "edit-book.php?id=$photoBook");
    $smallAsset = cloudinary_asset((string) book_image($photoBook));
    check('compress: small images are not enlarged', ($smallAsset['width'] ?? 0) === 100);
    $flat = imagecreatefromstring(file_get_contents($smallAsset['secure_url'] ?? ''));
    check('compress: transparency becomes white, not black', $flat && (imagecolorat($flat, 50, 75) & 0xFFFFFF) > 0xF0F0F0);

    // A phone stores a sideways photo plus a note to rotate it. This builds that note by hand: EXIF orientation 6.
    $tall = imagecreatetruecolor(200, 100);
    ob_start();
    imagejpeg($tall);
    $jpeg = ob_get_clean();
    $tiff = "MM\x00\x2A\x00\x00\x00\x08\x00\x01\x01\x12\x00\x03\x00\x00\x00\x01\x00\x06\x00\x00\x00\x00\x00\x00";
    $exif = "Exif\x00\x00" . $tiff;
    $rotatedFile = tempnam(sys_get_temp_dir(), 'bw') . '.jpg';
    file_put_contents($rotatedFile, "\xFF\xD8\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2));
    $alice->upload("edit-book.php?id=$photoBook", $bookFields, ['cover' => $rotatedFile], "edit-book.php?id=$photoBook");
    $rotated = cloudinary_asset((string) book_image($photoBook));
    check('compress: a sideways phone photo is turned upright', ($rotated['width'] ?? 0) === 100 && ($rotated['height'] ?? 0) === 200);

    $alice->upload("edit-book.php?id=$photoBook", $bookFields, ['cover' => $red], "edit-book.php?id=$photoBook");
    $third = book_image($photoBook);
    $alice->post('actions/book-delete.php', ['bookID' => $photoBook]);
    check('upload: deleting a book deletes its image', $third && cloudinary_asset($third) === null);

    $profile = ['first_name' => 'A', 'last_name' => 'B', 'email' => test_email('a'), 'username' => "{$tag}a"];
    $alice->upload('settings.php', $profile, ['avatar' => $red], 'settings.php');
    $avatarID = db("SELECT avatar_public_id FROM users WHERE userID = ?", 'i', $aliceID)->fetch_row()[0];
    check('upload: an avatar goes in the avatars folder', str_starts_with((string) $avatarID, 'bookworm-buddies/avatars/'));
    check('upload: the avatar shows in the page header', str_contains($alice->get('home.php'), 'res.cloudinary.com'));

    $doomedCover = test_image([40, 140, 40]);
    $alice->upload('add-book.php', ['title' => "$tag doomed photo"] + $bookFields, ['cover' => $doomedCover], 'add-book.php');
    $doomedImage = db("SELECT cover_public_id FROM books WHERE title = ?", 's', "$tag doomed photo")->fetch_row()[0] ?? null;
    $imagesToGo = [$avatarID, $doomedImage];
} else {
    echo "skip  Cloudinary uploads (add --real-services to run them)\n";
    $imagesToGo = [];
}

// ---- Giveaways
$addListed = function ($browser, $title, $type) {
    $browser->post('add-book.php', ['title' => $title, 'author' => 'A', 'year_published' => '2000', 'listing_type' => $type, 'share' => '1'], 'add-book.php');
    return db("SELECT bookID FROM books WHERE title = ? ORDER BY bookID DESC LIMIT 1", 's', $title)->fetch_row()[0];
};
$ownerOf = fn($id) => db("SELECT userID, showcase FROM collection WHERE bookID = ?", 'i', $id)->fetch_assoc();
$swapRow = fn($id) => db("SELECT * FROM swap WHERE swapID = ?", 'i', $id)->fetch_assoc();

$gift = $addListed($alice, "$tag gift", 'give');
check('listing: the card shows the type', str_contains($bob->get('home.php?city=all&type=give'), 'Free to keep') && str_contains($bob->get('home.php?city=all&type=give'), "$tag gift"));
check('listing: the type filter narrows results', !str_contains($bob->get('home.php?city=all&type=lend'), "$tag gift"));
$bob->post('actions/swap-request.php', ['bookID' => $gift]);
$giftSwap = latest_swap($gift);
check('give: the request is a giveaway', $swapRow($giftSwap)['kind'] === 'give');
$alice->post('actions/swap-update.php', ['swapID' => $giftSwap, 'action' => 'accept']);
check('give: no return date is set', $swapRow($giftSwap)['status'] === 'accepted' && $swapRow($giftSwap)['due_date'] === null);
$bob->post('actions/swap-update.php', ['swapID' => $giftSwap, 'action' => 'complete']);
check('give: the receiver cannot complete it alone', $swapRow($giftSwap)['status'] === 'accepted' && $ownerOf($gift)['userID'] == $aliceID);
$alice->post('actions/swap-update.php', ['swapID' => $giftSwap, 'action' => 'return']);
check('give: it cannot be marked returned', $swapRow($giftSwap)['status'] === 'accepted');
$alice->post('actions/swap-update.php', ['swapID' => $giftSwap, 'action' => 'complete']);
check('give: completing moves the book to the receiver', $swapRow($giftSwap)['status'] === 'completed' && $ownerOf($gift) == ['userID' => $bobID, 'showcase' => 0]);
check('give: the book is on the new owner\'s shelf', str_contains($bob->get('profile.php'), "$tag gift"));

// ---- Two-way swaps
$wanted = $addListed($alice, "$tag wanted", 'swap');
$offer = $addListed($bob, "$tag offer", 'lend');
$bobPrivate = add_book($bob, "$tag bobprivate", false);
$page = $bob->get("book.php?id=$wanted");
check('swap: the form offers your shared books only', str_contains($page, 'name="offeredID"') && str_contains($page, "$tag offer") && !str_contains($page, "$tag bobprivate"));
$bob->post('actions/swap-request.php', ['bookID' => $wanted]);
check('swap: a book must be offered', latest_swap($wanted) === null);
$bob->post('actions/swap-request.php', ['bookID' => $wanted, 'offeredID' => $shown]);
check('swap: you cannot offer someone else\'s book', latest_swap($wanted) === null);
$bob->post('actions/swap-request.php', ['bookID' => $wanted, 'offeredID' => $bobPrivate]);
check('swap: you cannot offer a private book', latest_swap($wanted) === null);
$bob->post('actions/swap-request.php', ['bookID' => $wanted, 'offeredID' => $offer]);
$twoWay = latest_swap($wanted);
check('swap: the request records the offered book', $swapRow($twoWay)['kind'] === 'swap' && $swapRow($twoWay)['offered_bookID'] == $offer);
check('swap: the owner sees what is offered', str_contains($alice->get('requests.php'), "$tag offer"));
check('swap: an offered book shows as unavailable', str_contains($carol->get("book.php?id=$offer"), 'Not Available'));
check('swap: an offered book leaves Available Books', !str_contains($carol->get('home.php?available=1&city=all'), "$tag offer"));
$carol->post('actions/swap-request.php', ['bookID' => $offer]);
check('swap: an offered book cannot be requested', latest_swap($offer) === null);
$alice->post('actions/swap-update.php', ['swapID' => $twoWay, 'action' => 'accept']);
$bob->post('actions/book-delete.php', ['bookID' => $offer]);
check('swap: an offered book in an accepted swap cannot be deleted', db("SELECT 1 FROM books WHERE bookID = ?", 'i', $offer)->num_rows === 1);
$carol->post('actions/swap-update.php', ['swapID' => $twoWay, 'action' => 'complete']);
check('swap: a stranger cannot confirm', !$swapRow($twoWay)['owner_confirmed'] && !$swapRow($twoWay)['requester_confirmed']);
$alice->post('actions/swap-update.php', ['swapID' => $twoWay, 'action' => 'complete']);
check('swap: one confirmation changes nothing yet', $swapRow($twoWay)['status'] === 'accepted' && $ownerOf($wanted)['userID'] == $aliceID);
$bob->post('actions/swap-update.php', ['swapID' => $twoWay, 'action' => 'complete']);
check('swap: both confirmations exchange the books', $swapRow($twoWay)['status'] === 'completed' && $ownerOf($wanted) == ['userID' => $bobID, 'showcase' => 0] && $ownerOf($offer) == ['userID' => $aliceID, 'showcase' => 0]);

// ---- Return dates and reminders
$loan = $addListed($alice, "$tag loan", 'lend');
$bob->post('actions/swap-request.php', ['bookID' => $loan]);
$loanSwap = latest_swap($loan);
$alice->post('actions/swap-update.php', ['swapID' => $loanSwap, 'action' => 'accept', 'due_date' => '2001-01-01']);
check('due: a past date is refused', $swapRow($loanSwap)['status'] === 'pending');
$alice->post('actions/swap-update.php', ['swapID' => $loanSwap, 'action' => 'accept', 'due_date' => date('Y-m-d', strtotime('+400 days'))]);
check('due: a date too far ahead is refused', $swapRow($loanSwap)['status'] === 'pending');
$dueDate = date('Y-m-d', strtotime('+20 days'));
$alice->post('actions/swap-update.php', ['swapID' => $loanSwap, 'action' => 'accept', 'due_date' => $dueDate]);
check('due: accepting saves the return date', $swapRow($loanSwap)['status'] === 'accepted' && $swapRow($loanSwap)['due_date'] === $dueDate);
check('due: the borrower sees it', str_contains($bob->get('requests.php'), date('j F Y', strtotime($dueDate))));
$bob->post('actions/swap-update.php', ['swapID' => $loanSwap, 'action' => 'extend', 'due_date' => date('Y-m-d', strtotime('+60 days'))]);
check('due: the borrower cannot change it', $swapRow($loanSwap)['due_date'] === $dueDate);
$alice->post('actions/swap-update.php', ['swapID' => $loanSwap, 'action' => 'extend', 'due_date' => date('Y-m-d', strtotime('+30 days'))]);
check('due: the owner can change it', $swapRow($loanSwap)['due_date'] === date('Y-m-d', strtotime('+30 days')));
db("UPDATE swap SET due_date = ? WHERE swapID = ?", 'si', date('Y-m-d', strtotime('-2 days')), $loanSwap);
check('due: an overdue loan is marked', str_contains($bob->get('requests.php'), 'Overdue'));

// Rate limits and sessions.
check('limits: allows up to the limit, then refuses', !rate_limited("{$tag}x", 2, 60) && !rate_limited("{$tag}x", 2, 60) && rate_limited("{$tag}x", 2, 60));
check('limits: other keys are not affected', !rate_limited("{$tag}y", 2, 60));

$keepLimits = true;
foreach (['127.0.0.1', '::1'] as $localIP) {
    for ($i = 0; $i < 10; $i++) {
        db("INSERT INTO rate_limits (hit_key) VALUES (?)", 's', "signup:ip:$localIP");
    }
}
$blocked = new Browser();
check('limits: too many signups from one connection are refused', str_contains(signup($blocked, 'lim', [], false), 'Too many accounts'));
check('limits: the refused signup created no account', !db("SELECT 1 FROM users WHERE email = ?", 's', test_email('lim'))->num_rows);
$keepLimits = false;

$headers = implode("\n", get_headers("$base/rules.php"));
check('headers: browser features are switched off', str_contains($headers, 'Permissions-Policy'));
check('headers: no other site may read responses', !stripos($headers, 'Access-Control-Allow-Origin'));

$laptop = new Browser();
$phone = new Browser();
signup($laptop, 'pw');
signin($laptop, 'pw');
signin($phone, 'pw');
$phone->get('requests.php');
check('sessions: a second device is signed in', str_ends_with($phone->url, 'requests.php'));
$laptop->post('settings.php', ['first_name' => 'A', 'last_name' => 'B', 'email' => test_email('pw'), 'username' => "{$tag}pw", 'mobile' => '', 'address' => '', 'current_password' => 'correct-horse-1', 'new_password' => 'correct-horse-2'], 'settings.php');
$laptop->get('requests.php');
check('sessions: the device that changed the password stays signed in', str_ends_with($laptop->url, 'requests.php'));
$phone->get('requests.php');
check('sessions: changing the password signs out other devices', str_ends_with($phone->url, 'signin.php'));

// The blog's popup gets the article alone, and shared links still get the whole page.
$anyPost = db("SELECT p.postID FROM posts p JOIN users u ON p.userID = u.userID WHERE p.removed_at IS NULL AND u.suspended_at IS NULL LIMIT 1")->fetch_row()[0] ?? null;
if ($anyPost) {
    $reader = new Browser();
    $inPopup = $reader->get("post.php?id=$anyPost&popup=1");
    check('blog popup: returns just the article', str_contains($inPopup, '<article class="post"') && !str_contains($inPopup, '<html'));
    check('blog popup: a shared link is a full page', str_contains($reader->get("post.php?id=$anyPost"), '<html'));
}
check('sign-in text: differs from signup', str_contains($anon->get('signin.php'), 'Your next chapter') && str_contains($anon->get('signup.php'), 'to stay on one shelf'));
check('password fields: sign-in and signup can show the password', str_contains($anon->get('signin.php'), 'data-password-toggle') && str_contains($anon->get('signup.php'), 'data-password-toggle'));

// Forgotten passwords.
$forgetful = new Browser();
$otherDevice = new Browser();
signup($forgetful, 'rst');
signin($otherDevice, 'rst');
$rstEmail = test_email('rst');
$rstID = user_id('rst');
$sayKnown = $forgetful->post('forgot-password.php', ['email' => $rstEmail], 'forgot-password.php');
$sayUnknown = $forgetful->post('forgot-password.php', ['email' => "$tag-nobody@example.com"], 'forgot-password.php');
check('reset: known and unknown emails get the same answer', str_contains($sayKnown, 'If that email has an account') && str_contains($sayUnknown, 'If that email has an account'));
check('reset: only the token hash is stored, with an expiry', strlen(db("SELECT reset_token_hash FROM users WHERE userID = ?", 'i', $rstID)->fetch_row()[0]) === 64);
check('reset: sign-in page links to it', str_contains($forgetful->get('signin.php'), 'forgot-password.php'));
$plantReset = function ($minutes) use ($rstID) {
    $token = bin2hex(random_bytes(32));
    db("UPDATE users SET reset_token_hash = ?, reset_expires_at = NOW() + INTERVAL ? MINUTE WHERE userID = ?", 'sii', hash('sha256', $token), $minutes, $rstID);
    return $token;
};
check('reset: an expired link is refused', str_contains($forgetful->get('reset-password.php?token=' . $plantReset(-1)), "doesn't work"));
check('reset: a made-up link is refused', str_contains($forgetful->get('reset-password.php?token=nope'), "doesn't work"));
check('reset: an empty link is refused', str_contains($forgetful->get('reset-password.php'), "doesn't work"));
$resetToken = $plantReset(30);
$resetPage = 'reset-password.php?token=' . $resetToken;
check('reset: a valid link shows the form', str_contains($forgetful->get($resetPage), 'Choose a new password'));
check('reset: opening the link changes nothing', db("SELECT reset_token_hash FROM users WHERE userID = ?", 'i', $rstID)->fetch_row()[0] !== null);
check('reset: a short password is refused', str_contains($forgetful->post('reset-password.php', ['token' => $resetToken, 'password' => 'short'], $resetPage), 'Password must be'));
$forgetful->rawPost('reset-password.php', ['token' => $resetToken, 'password' => 'after-reset-pass-1']);
check('reset: a forged form changes nothing', db("SELECT reset_token_hash FROM users WHERE userID = ?", 'i', $rstID)->fetch_row()[0] !== null);
$forgetful->post('reset-password.php', ['token' => $resetToken, 'password' => 'after-reset-pass-1'], $resetPage);
check('reset: the link works only once', str_contains($forgetful->get($resetPage), "doesn't work"));
$fresh = new Browser();
check('reset: the old password stops working', str_contains(signin($fresh, 'rst'), 'Invalid email'));
signin($fresh, 'rst', 'after-reset-pass-1');
$fresh->get('requests.php');
check('reset: the new password works', str_ends_with($fresh->url, 'requests.php'));
$otherDevice->get('requests.php');
check('reset: other devices are signed out', str_ends_with($otherDevice->url, 'signin.php'));
$keepLimits = true;
db("DELETE FROM rate_limits WHERE hit_key = ?", 's', 'reset:to:' . md5(strtolower($rstEmail)));
foreach ([1, 2, 3] as $i) {
    $forgetful->post('forgot-password.php', ['email' => $rstEmail], 'forgot-password.php');
}
db("UPDATE users SET reset_token_hash = NULL WHERE userID = ?", 'i', $rstID);
$forgetful->post('forgot-password.php', ['email' => $rstEmail], 'forgot-password.php');
check('reset: one address gets a limited number of links', db("SELECT reset_token_hash FROM users WHERE userID = ?", 'i', $rstID)->fetch_row()[0] === null);
$keepLimits = false;

// Changes a stolen session shouldn't be able to make.
$guarded = new Browser();
signup($guarded, 'grd');
signin($guarded, 'grd');
$guardedForm = ['first_name' => 'A', 'last_name' => 'B', 'username' => "{$tag}grd", 'email' => "$tag-moved@example.com", 'mobile' => '', 'address' => '', 'new_password' => ''];
check('email: changing it needs the current password', str_contains($guarded->post('settings.php', $guardedForm + ['current_password' => 'wrong'], 'settings.php'), 'current password is incorrect'));
check('email: nothing changed without it', db("SELECT pending_email FROM users WHERE userID = ?", 'i', user_id('grd'))->fetch_row()[0] === null);
$guarded->post('settings.php', $guardedForm + ['current_password' => 'correct-horse-1'], 'settings.php');
check('email: the right password allows it', db("SELECT pending_email FROM users WHERE userID = ?", 'i', user_id('grd'))->fetch_row()[0] === "$tag-moved@example.com");
for ($i = 0; $i < 10; $i++) {
    $guarded->post('actions/account-delete.php', ['password' => 'wrong-password'], 'settings.php');
}
$guarded->post('actions/account-delete.php', ['password' => 'correct-horse-1'], 'settings.php');
check('password checks: guessing is limited', user_id('grd') !== null);

// A second copy of the site with a known admin login, so the suite never needs the real password.
$adminPassword = bin2hex(random_bytes(12));
$cronToken = bin2hex(random_bytes(16));
$testConfig = tempnam(sys_get_temp_dir(), 'bw') . '.php';
file_put_contents($testConfig, '<?php return array_merge(require ' . var_export($testConfigFile, true) . ", ['admin' => ['email' => 'admin@example.com', 'password_hash' => " . var_export(password_hash($adminPassword, PASSWORD_BCRYPT), true) . "], 'cron_token' => " . var_export($cronToken, true) . ']);');
$adminPort = 18000 + random_int(0, 999);
$adminSite = "http://localhost:$adminPort";
$adminServer = proc_open([PHP_BINARY, '-S', "localhost:$adminPort", '-t', __DIR__ . '/../public'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, ['BOOKWORM_CONFIG' => $testConfig] + getenv());
usleep(800000);

$cron = new Browser($adminSite);
$cron->get('cron.php?token=wrong');
check('reminders: a wrong token is refused', $cron->code === 404);
$reminded = json_decode($cron->get("cron.php?token=$cronToken"), true);
check('reminders: an overdue loan gets a reminder', $cron->code === 200 && ($reminded['overdue'] ?? 0) >= 1 && $swapRow($loanSwap)['reminded_overdue_at'] !== null);
$overdueAt = $swapRow($loanSwap)['reminded_overdue_at'];
$cron->get("cron.php?token=$cronToken");
check('reminders: it is not repeated the same day', $swapRow($loanSwap)['reminded_overdue_at'] === $overdueAt);
db("UPDATE swap SET due_date = ?, reminded_overdue_at = NULL WHERE swapID = ?", 'si', date('Y-m-d', strtotime('+2 days')), $loanSwap);
$cron->get("cron.php?token=$cronToken");
check('reminders: a loan due soon gets a reminder', $swapRow($loanSwap)['reminded_due_at'] !== null);
db("UPDATE swap SET reminded_due_at = NULL, due_date = ? WHERE swapID = ?", 'si', date('Y-m-d', strtotime('+25 days')), $loanSwap);
$cron->get("cron.php?token=$cronToken");
check('reminders: a far-off date gets none', $swapRow($loanSwap)['reminded_due_at'] === null);

// ---- Ratings
$rate = fn($browser, $swapID, $stars, $comment = '') => $browser->post('actions/rate.php', ['swapID' => $swapID, 'stars' => $stars, 'comment' => $comment]);
$countRatings = fn($swapID) => db("SELECT COUNT(*) FROM ratings WHERE swapID = ?", 'i', $swapID)->fetch_row()[0];
$rate($bob, $loanSwap, 5);
check('rating: not allowed before the exchange ends', $countRatings($loanSwap) == 0);
$alice->post('actions/swap-update.php', ['swapID' => $loanSwap, 'action' => 'return']);
check('rating: the form appears afterwards', str_contains($bob->get('requests.php'), 'actions/rate.php'));
$rate($carol, $loanSwap, 1);
check('rating: a stranger cannot rate', $countRatings($loanSwap) == 0);
$rate($bob, $loanSwap, 6);
$rate($bob, $loanSwap, 0);
check('rating: stars must be 1 to 5', $countRatings($loanSwap) == 0);
$ratingText = "Great <b>owner</b> $tag";
$rate($bob, $loanSwap, 4, $ratingText);
$ratingRow = db("SELECT ratingID, raterID, ratedID, stars FROM ratings WHERE swapID = ?", 'i', $loanSwap)->fetch_assoc();
check('rating: goes to the other person', $ratingRow['raterID'] == $bobID && $ratingRow['ratedID'] == $aliceID && $ratingRow['stars'] == 4);
$rate($bob, $loanSwap, 1);
check('rating: only once per exchange', db("SELECT stars FROM ratings WHERE swapID = ? AND raterID = ?", 'ii', $loanSwap, $bobID)->fetch_row()[0] == 4);
$profilePage = $carol->get("profile.php?userID=$aliceID");
check('rating: the profile shows it, escaped', str_contains($profilePage, '★ 4.0 (1 rating)') && str_contains($profilePage, htmlspecialchars($ratingText)) && !str_contains($profilePage, '<b>owner'));
check('rating: the book page shows the owner\'s score', str_contains($carol->get("book.php?id=$shown"), '★ 4.0'));
$rate($alice, $loanSwap, 5, 'Returned on time');
check('rating: both people can rate', $countRatings($loanSwap) == 2);

$report = fn($browser, $type, $id, $reason = 'Spam or fake') => $browser->post('actions/report.php', ['type' => $type, 'id' => $id, 'reason' => $reason, 'details' => 'test']);
$countReports = fn($type, $id) => db("SELECT COUNT(*) FROM reports WHERE target_type = ? AND target_id = ?", 'si', $type, $id)->fetch_row()[0];

// ---- Chat
$chatBook = $addListed($alice, "$tag chatbook", 'lend');
$bob->post('actions/swap-request.php', ['bookID' => $chatBook]);
$chatSwap = latest_swap($chatBook);
$alice->get("chat.php?swap=$chatSwap");
check('chat: closed while a request is pending', str_ends_with($alice->url, 'requests.php'));
$send = function ($browser, $swapID, $body) {
    preg_match('/name="csrf" value="(\w+)"/', $browser->get('settings.php'), $m);
    return $browser->rawPost('actions/chat-send.php', ['csrf' => $m[1] ?? '', 'swapID' => $swapID, 'body' => $body]);
};
$countMessages = fn($swapID) => db("SELECT COUNT(*) FROM messages WHERE swapID = ?", 'i', $swapID)->fetch_row()[0];
$send($bob, $chatSwap, 'too early');
check('chat: no sending while pending', $countMessages($chatSwap) == 0);
$alice->post('actions/swap-update.php', ['swapID' => $chatSwap, 'action' => 'accept']);
$page = $alice->get("chat.php?swap=$chatSwap");
check('chat: opens once accepted, for both people', str_contains($page, 'data-chat-form') && str_contains($bob->get("chat.php?swap=$chatSwap"), 'data-chat-form'));
$carol->get("chat.php?swap=$chatSwap");
check('chat: a stranger cannot open it', str_ends_with($carol->url, 'requests.php'));
$strangerPoll = json_decode($carol->get("chat-messages.php?swap=$chatSwap"), true);
check('chat: a stranger cannot read it', $carol->code === 404 && !isset($strangerPoll['messages']));
(new Browser())->get("chat-messages.php?swap=$chatSwap");
$send($carol, $chatSwap, 'intruder');
check('chat: a stranger cannot post', $countMessages($chatSwap) == 0);
$bob->get("actions/chat-send.php?swapID=$chatSwap&body=link");
check('chat: a plain link cannot post', $countMessages($chatSwap) == 0);
$bob->rawPost('actions/chat-send.php', ['csrf' => 'forged', 'swapID' => $chatSwap, 'body' => 'forged']);
check('chat: a forged form cannot post', $countMessages($chatSwap) == 0);
$send($bob, $chatSwap, str_repeat('x', 1001));
check('chat: over 1000 characters is refused', $countMessages($chatSwap) == 0);
$hello = "Meet at <script>alert(1)</script> & বই $tag";
$send($bob, $chatSwap, $hello);
$stored = db("SELECT messageID, senderID, body, read_at FROM messages WHERE swapID = ? ORDER BY messageID DESC LIMIT 1", 'i', $chatSwap)->fetch_assoc();
check('chat: a message is saved exactly', ($stored['body'] ?? '') === $hello && $stored['senderID'] == $bobID && $stored['read_at'] === null);
check('chat: the Requests badge shows it as unread', str_contains($alice->get('requests.php'), 'aria-label="1 new"'));
$poll = json_decode($alice->get("chat-messages.php?swap=$chatSwap&after=0"), true);
check('chat: new messages arrive by polling', ($poll['messages'][0]['body'] ?? '') === $hello && $poll['messages'][0]['mine'] === false && $poll['open'] === true);
check('chat: reading marks it as read', db("SELECT read_at FROM messages WHERE messageID = ?", 'i', $stored['messageID'])->fetch_row()[0] !== null);
$after = json_decode($alice->get("chat-messages.php?swap=$chatSwap&after={$stored['messageID']}"), true);
check('chat: polling returns only newer messages', $after['messages'] === []);
$page = $alice->get("chat.php?swap=$chatSwap");
check('chat: the page shows it safely escaped', str_contains($page, htmlspecialchars($hello)) && !str_contains($page, '<script>alert'));
preg_match('/name="csrf" value="(\w+)"/', $alice->get('settings.php'), $m);
$alice->rawPost('actions/chat-send.php', ['csrf' => $m[1], 'swapID' => $chatSwap, 'body' => 'See you there']);
check('chat: replies work', $countMessages($chatSwap) == 2);

$report($carol, 'message', $stored['messageID']);
check('chat: a stranger cannot report a message', $countReports('message', $stored['messageID']) == 0);
$bobOwn = $stored['messageID'];
$report($bob, 'message', $bobOwn);
check('chat: you cannot report your own message', $countReports('message', $bobOwn) == 0);
$report($alice, 'message', $bobOwn, 'Harassment');
check('chat: a participant can report a message', $countReports('message', $bobOwn) == 1);

$alice->post('actions/swap-update.php', ['swapID' => $chatSwap, 'action' => 'return']);
$page = $bob->get("chat.php?swap=$chatSwap");
check('chat: after the exchange it is read-only', str_contains($page, 'See you there') && !str_contains($page, 'data-chat-form') && str_contains($page, 'chat has ended'));
$before = $countMessages($chatSwap);
$send($bob, $chatSwap, 'too late');
check('chat: no sending after the exchange', $countMessages($chatSwap) == $before);
$poll = json_decode($bob->get("chat-messages.php?swap=$chatSwap"), true);
check('chat: polling reports that it closed', $poll['open'] === false);

$limitBook = $addListed($alice, "$tag limitbook", 'lend');
$bob->post('actions/swap-request.php', ['bookID' => $limitBook]);
$limitSwap = latest_swap($limitBook);
$alice->post('actions/swap-update.php', ['swapID' => $limitSwap, 'action' => 'accept']);
db("INSERT INTO messages (swapID, senderID, body) SELECT ?, ?, 'x' FROM (SELECT 1 FROM information_schema.tables LIMIT 20) t", 'ii', $limitSwap, $bobID);
$countBefore = $countMessages($limitSwap);
$send($bob, $limitSwap, 'one too many');
check('chat: sending is limited per minute', $countMessages($limitSwap) == $countBefore);
$alice->post('actions/swap-update.php', ['swapID' => $limitSwap, 'action' => 'return']);

// ---- Reports
check('report: the link shows on a book', str_contains($carol->get("book.php?id=$shown"), 'actions/report.php'));
$anonReport = new Browser();
$anonReport->rawPost('actions/report.php', ['type' => 'book', 'id' => $shown, 'reason' => 'Copyright']);
check('report: guests cannot report', str_ends_with($anonReport->url, 'signin.php') && $countReports('book', $shown) == 0);
$report($carol, 'book', $shown, 'Not a real reason');
check('report: a made-up reason is refused', $countReports('book', $shown) == 0);
$report($carol, 'book', 999999999);
check('report: a missing target is refused', $countReports('book', 999999999) == 0);
$report($carol, 'post', $postID);
check('report: a blog post can be reported', $countReports('post', $postID) == 1);
$report($carol, 'book', $shown);
$report($carol, 'post', $postID);
check('report: a blog post can be reported', $countReports('post', $postID) == 1);
$report($carol, 'book', $shown);
check('report: repeats are ignored', $countReports('book', $shown) == 1);
$report($carol, 'user', $carolID);
check('report: you cannot report yourself', $countReports('user', $carolID) == 0);
$report($carol, 'rating', $ratingRow['ratingID']);

// ---- Admin
$admin = new Browser($adminSite);
if (empty(config('admin')['email'])) {
    $plain = new Browser();
    $plain->get('admin/index.php');
    check('admin: without admin config, the area does not exist', $plain->code === 404);
}
foreach (['index.php', 'users.php', 'books.php', 'reports.php', 'content.php', 'log.php'] as $page) {
    $admin->get("admin/$page");
    check("admin: $page needs a login", str_ends_with($admin->url, 'admin/login.php'));
}
$adminAsUser = new Browser($adminSite);
$adminAsUser->post('actions/signin.php', ['login' => "{$tag}c", 'password' => 'correct-horse-1'], 'signin.php');
$adminAsUser->get('admin/users.php');
check('admin: a normal member cannot get in', str_ends_with($adminAsUser->url, 'admin/login.php'));
$loginAdmin = fn($browser, $email, $password) => $browser->post('admin/login.php', ['email' => $email, 'password' => $password], 'admin/login.php');
check('admin: a wrong password is refused', str_contains($loginAdmin($admin, 'admin@example.com', 'wrong-password'), 'Invalid email or password'));
check('admin: a wrong email is refused', str_contains($loginAdmin($admin, 'other@example.com', $adminPassword), 'Invalid email or password'));
$loginAdmin($admin, 'ADMIN@example.com', $adminPassword);
check('admin: the right login works', str_ends_with($admin->url, 'admin/index.php'));
foreach (['index.php', 'users.php', 'books.php', 'reports.php', 'content.php', 'log.php', 'users.php?q=' . urlencode("' OR 1=1 -- "), 'books.php?status=removed', 'users.php?status=suspended&page=2'] as $page) {
    $html = $admin->get("admin/$page");
    check("admin: $page renders", $admin->code === 200 && !preg_match('/Warning|Fatal error|Something went wrong/', $html));
}
$usersPage = $admin->get('admin/users.php?q=' . urlencode($tag));
check('admin: finds users', str_contains($usersPage, "{$tag}b"));
check('admin: never shows mobile, address or birth date', !str_contains($usersPage, '+880') && !str_contains($usersPage, 'Requester Road') && !str_contains($usersPage, '2000-01-15') && !str_contains($usersPage, '$2y$'));
$reportsPage = $admin->get('admin/reports.php');
check('admin: a reported chat message appears', str_contains($reportsPage, htmlspecialchars($hello)) && !str_contains($reportsPage, '<script>alert'));
check('admin: chats that are not reported stay hidden', !str_contains($reportsPage, 'See you there') && !str_contains($admin->get('admin/content.php'), 'See you there') && !str_contains($admin->get('admin/log.php'), 'See you there'));
$admin->post('admin/action.php', ['do' => 'delete_message', 'id' => db("SELECT messageID FROM messages WHERE body = 'See you there' AND swapID = ?", 'i', $chatSwap)->fetch_row()[0], 'reason' => ''], 'admin/index.php');
check('admin: an unreported message cannot be deleted', db("SELECT 1 FROM messages WHERE body = 'See you there' AND swapID = ?", 'i', $chatSwap)->num_rows === 1);
$admin->post('admin/action.php', ['do' => 'delete_message', 'id' => $bobOwn, 'reason' => ''], 'admin/index.php');
check('admin: a reported message can be deleted', db("SELECT 1 FROM messages WHERE messageID = ?", 'i', $bobOwn)->num_rows === 0);
check('admin: reports appear in the queue', str_contains($admin->get('admin/reports.php'), "$tag shown"));

$adminDo = fn($do, $id, $reason = '') => $admin->post('admin/action.php', ['do' => $do, 'id' => $id, 'reason' => $reason], 'admin/index.php');
$admin->get("admin/action.php?do=suspend&id=$carolID&reason=x");
check('admin: a plain link changes nothing', db("SELECT suspended_at FROM users WHERE userID = ?", 'i', $carolID)->fetch_row()[0] === null);
$admin->rawPost('admin/action.php', ['do' => 'suspend', 'id' => $carolID, 'reason' => 'x', 'csrf' => 'forged']);
check('admin: a forged form changes nothing', db("SELECT suspended_at FROM users WHERE userID = ?", 'i', $carolID)->fetch_row()[0] === null);
$outsider = new Browser($adminSite);
$outsider->rawPost('admin/action.php', ['do' => 'suspend', 'id' => $carolID, 'reason' => 'x']);
check('admin: actions need a login', db("SELECT suspended_at FROM users WHERE userID = ?", 'i', $carolID)->fetch_row()[0] === null);
$adminDo('suspend', $carolID);
check('admin: suspending needs a reason', db("SELECT suspended_at FROM users WHERE userID = ?", 'i', $carolID)->fetch_row()[0] === null);

// Suspension: Carol shares a book and has a pending request on Bob's.
$carolBook = $addListed($carol, "$tag carolbook", 'lend');
$bobLend = $addListed($bob, "$tag boblend", 'lend');
$carol->post('actions/swap-request.php', ['bookID' => $bobLend]);
$carolSwap = latest_swap($bobLend);
$adminDo('suspend', $carolID, 'Repeated spam');
check('suspend: the session ends at once', str_ends_with(($carol->get('requests.php') !== false ? $carol->url : ''), 'signin.php'));
check('suspend: sign-in is refused with the reason', str_contains(signin($carol, 'c'), 'Repeated spam') && !str_ends_with($carol->url, 'home.php'));
check('suspend: open requests are closed', $swapRow($carolSwap)['status'] === 'declined');
check('suspend: their books leave Browse', !str_contains($bob->get('home.php?city=all'), "$tag carolbook"));
$bob->get("book.php?id=$carolBook");
check('suspend: their book page is hidden', $bob->code === 404);
check('suspend: their profile is hidden', str_contains($bob->get("profile.php?userID=$carolID"), 'User not found'));
check('suspend: they leave live search', $suggest($bob, "$tag carolbook")['books'] === [] && $suggest($bob, "{$tag}c")['people'] === []);
$bob->post('actions/swap-request.php', ['bookID' => $carolBook]);
check('suspend: their book cannot be requested', latest_swap($carolBook) === null);
$adminDo('unsuspend', $carolID);
signin($carol, 'c');
check('unsuspend: they can sign in again', str_ends_with($carol->url, 'home.php'));
check('unsuspend: their books return', str_contains($bob->get('home.php?city=all'), "$tag carolbook"));

// Books and comments.
$adminDo('takedown', $carolBook, 'Not a real book');
check('takedown: hidden from others', str_contains($carol->get("book.php?id=$carolBook"), 'Not a real book') && db("SELECT showcase FROM collection WHERE bookID = ?", 'i', $carolBook)->fetch_row()[0] == 0);
$carol->post('actions/book-showcase.php', ['bookID' => $carolBook, 'showcase' => '1']);
check('takedown: the owner cannot re-share it', db("SELECT showcase FROM collection WHERE bookID = ?", 'i', $carolBook)->fetch_row()[0] == 0);
$adminDo('restore', $carolBook);
$carol->post('actions/book-showcase.php', ['bookID' => $carolBook, 'showcase' => '1']);
check('restore: the owner can share it again', db("SELECT showcase FROM collection WHERE bookID = ?", 'i', $carolBook)->fetch_row()[0] == 1);
$bob->post('actions/feedback-add.php', ['bookID' => $carolBook, 'comment' => "abusive $tag"]);
$feedbackID = db("SELECT feedbackID FROM feedback WHERE bookID = ?", 'i', $carolBook)->fetch_row()[0];
check('admin: comments are listed', str_contains($admin->get('admin/content.php'), "abusive $tag"));
$adminDo('delete_feedback', $feedbackID);
check('admin: can delete feedback', db("SELECT 1 FROM feedback WHERE feedbackID = ?", 'i', $feedbackID)->num_rows === 0);
$adminDo('delete_rating', $ratingRow['ratingID']);
check('admin: can delete a rating', db("SELECT 1 FROM ratings WHERE ratingID = ?", 'i', $ratingRow['ratingID'])->num_rows === 0);
check('admin: a reported post appears in the queue', str_contains($admin->get('admin/reports.php'), "Why I love $tag"));
check('admin: posts are listed', str_contains($admin->get('admin/content.php'), "Why I love $tag"));
$adminDo('takedown_post', $postID);
check('admin: taking down a post needs a reason', db("SELECT removed_at FROM posts WHERE postID = ?", 'i', $postID)->fetch_row()[0] === null);
$adminDo('takedown_post', $postID, 'Copied text');
$anonBlog->get("post.php?id=$postID");
check('takedown: the post is gone for readers', $anonBlog->code === 404 && !str_contains($anonBlog->get('blog.php'), "Why I love $tag"));
check('takedown: the author sees the reason', str_contains($bob->get("post.php?id=$postID"), 'Copied text'));
$adminDo('restore_post', $postID);
check('restore: the post returns', str_contains($anonBlog->get("post.php?id=$postID"), "Why I love $tag"));
$adminDo('suspend', $bobID, 'Testing');
$anonBlog->get("post.php?id=$postID");
check('suspend: their posts are hidden', $anonBlog->code === 404 && !str_contains($anonBlog->get('blog.php'), "Why I love $tag"));
$adminDo('unsuspend', $bobID);
signin($bob, 'b');
$bob->post('actions/post-delete.php', ['postID' => $postID]);
check('blog: the author can delete it', db("SELECT 1 FROM posts WHERE postID = ?", 'i', $postID)->num_rows === 0);
$reportID = db("SELECT reportID FROM reports WHERE target_type = 'book' AND target_id = ?", 'i', $shown)->fetch_row()[0];
$adminDo('resolve_report', $reportID);
check('admin: can close a report', db("SELECT status FROM reports WHERE reportID = ?", 'i', $reportID)->fetch_row()[0] === 'resolved' && !str_contains($admin->get('admin/reports.php'), "$tag shown"));
$logPage = $admin->get('admin/log.php');
check('admin: actions are logged with reasons', str_contains($logPage, 'suspend') && str_contains($logPage, 'Repeated spam') && str_contains($logPage, 'takedown'));
$admin->post('admin/action.php', ['do' => 'logout'], 'admin/index.php');
$admin->get('admin/users.php');
check('admin: logging out ends access', str_ends_with($admin->url, 'admin/login.php'));
for ($i = 0; $i < 5; $i++) {
    $loginAdmin($admin, 'admin@example.com', 'wrong-password');
}
check('admin: login is blocked after 5 failures', str_contains($loginAdmin($admin, 'admin@example.com', $adminPassword), 'Too many failed attempts'));
db("DELETE FROM rate_limits WHERE hit_key LIKE 'admin-%'");

// ---- Pages render cleanly
foreach (['home.php', 'home.php?available=1', 'requests.php', 'add-book.php', 'profile.php', 'profile.php?show=showcased', 'profile.php?show=borrowed', 'profile.php?show[]=x', 'profile.php?userID=999999999', 'settings.php', 'search.php', 'search.php?q[]=x'] as $page) {
    $html = $alice->get($page);
    check("render: $page", $alice->code === 200 && !preg_match('/Warning|Fatal error|Notice|Deprecated|Something went wrong/', $html) && substr_count(strtolower($html), '<html') <= 1);
}
$html = $alice->post('add-book.php', ['title' => ['x'], 'author' => ['y'], 'year_published' => ['z']], 'add-book.php');
check('render: array input is refused, no crash', $alice->code === 200 && str_contains($html, 'All fields are required'));

// ---- Logout and finding 10: account deletion
$carol->get('actions/logout.php');
$carol->get('profile.php');
check('logout: a plain link does not log out', str_ends_with($carol->url, 'profile.php'));
$carol->post('actions/logout.php', []);
$carol->get('profile.php');
check('logout: works', str_ends_with($carol->url, 'signin.php'));

$aliceBook = add_book($alice, "$tag doomed");
$bob->post('actions/feedback-add.php', ['bookID' => $aliceBook, 'comment' => 'nice']);
$bob->post('actions/swap-request.php', ['bookID' => $aliceBook]);
$alice->get('actions/account-delete.php');
check('csrf: a plain link does not delete an account', user_id('a') !== null);
$alice->post('actions/account-delete.php', ['password' => 'wrong-password']);
check('account: the wrong password does not delete it', user_id('a') !== null);
$alice->post('actions/account-delete.php', []);
check('account: no password does not delete it', user_id('a') !== null);
$alice->post('actions/account-delete.php', ['password' => 'brand-new-pass-1']);
check('account: user deleted', user_id('a') === null);
check('account: their books deleted (finding 10)', db("SELECT COUNT(*) FROM books WHERE bookID IN (?, ?, ?)", 'iii', $aliceBook, $shown, $hidden)->fetch_row()[0] == 0);
check('account: their swaps deleted', latest_swap($aliceBook) === null);
check('account: feedback on their books deleted', db("SELECT COUNT(*) FROM feedback WHERE bookID = ?", 'i', $aliceBook)->fetch_row()[0] == 0);
check('account: other users remain', user_id('b') !== null);
foreach (array_filter($imagesToGo) as $image) {
    check('account: their Cloudinary image is deleted', cloudinary_asset($image) === null);
}
$alice->get('profile.php');
check('account: session ended', str_ends_with($alice->url, 'signin.php'));

echo "\n", $checks - $failures, " of $checks checks passed\n";
exit($failures ? 1 : 0);
