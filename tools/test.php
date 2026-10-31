<?php
// End-to-end checks against a running site: php tools/test.php http://localhost:8000
// Creates zztest_* accounts and removes them at the end. Existing data is not touched.
if (PHP_SAPI !== 'cli') {
    exit;
}
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/cloudinary.php';

$base = rtrim($argv[1] ?? 'http://localhost:8000', '/');
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

function cleanup() {
    global $tag;
    $covers = db("SELECT b.cover_public_id FROM books b JOIN collection c ON b.bookID = c.bookID JOIN users u ON c.userID = u.userID WHERE u.username LIKE CONCAT(?, '%')", 's', $tag)->fetch_all();
    $avatars = db("SELECT avatar_public_id FROM users WHERE username LIKE CONCAT(?, '%')", 's', $tag)->fetch_all();
    delete_images(array_merge(array_column($covers, 0), array_column($avatars, 0)));
    db("DELETE b FROM books b JOIN collection c ON b.bookID = c.bookID JOIN users u ON c.userID = u.userID WHERE u.username LIKE CONCAT(?, '%')", 's', $tag);
    db("DELETE FROM users WHERE username LIKE CONCAT(?, '%') OR email LIKE CONCAT('delivered+', ?, '%')", 'ss', $tag, $tag);
    db("DELETE FROM login_attempts WHERE username LIKE CONCAT(?, '%') OR username LIKE CONCAT('delivered+', ?, '%')", 'ss', $tag, $tag);
}
register_shutdown_function('cleanup');

// A browser: keeps its own cookies and follows redirects.
class Browser {
    private $jar;
    public $code;
    public $url;

    public function __construct() {
        $this->jar = tempnam(sys_get_temp_dir(), 'bw');
    }

    private function request($path, $fields = null) {
        global $base;
        $ch = curl_init($base . '/' . $path);
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
        global $base;
        preg_match('/name="csrf" value="(\w+)"/', $this->get($formPage), $m);
        $ch = curl_init($base . '/' . $path);
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

// Resend's test address makes a real API call without reaching anyone. Used only where sending is being tested, to save quota.
$realEmail = true;
function test_email($suffix) {
    global $tag, $realEmail;
    return $realEmail ? "delivered+$tag$suffix@resend.dev" : "$tag$suffix@example.com";
}

function signup($browser, $suffix, $overrides = [], $activate = true) {
    global $tag;
    $email = $overrides['email'] ?? test_email($suffix);
    $html = $browser->post('signup.php', $overrides + ['name' => 'Test User', 'email' => $email, 'password' => 'correct-horse-1'], 'signup.php');
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

// ---- Guests can look around
$html = $anon->get('');
check('guest: landing page loads', $anon->code === 200 && str_contains($html, 'How it works') && str_contains($html, 'Join free'));
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
foreach (['//evil.example', '/\\evil.example', 'https://evil.example', '/actions/logout.php'] as $bad) {
    check("signin: refuses next=$bad", str_contains($anon->get('signin.php?next=' . urlencode($bad)), 'name="next" value="/home.php"'));
}

// ---- Signup
$alice = new Browser();
$bob = new Browser();
$carol = new Browser();

$signupForm = $alice->get('signup.php');
check('signup: asks only name, email and password', str_contains($signupForm, 'name="name"') && str_contains($signupForm, 'name="email"') && str_contains($signupForm, 'name="password"') && !str_contains($signupForm, 'name="username"') && !str_contains($signupForm, 'name="dob"'));
check('signup: rejects a short password', str_contains(signup($alice, 'a', ['password' => 'short'], false), 'Password must be'));
check('signup: rejects a bad email', str_contains(signup($alice, 'a', ['email' => 'nope'], false), 'valid email'));
check('signup: rejects a blank name', str_contains(signup($alice, 'a', ['name' => '  '], false), 'enter your name'));
check('signup: wrong CSRF token refused', str_contains($alice->rawPost('signup.php', ['csrf' => 'bad', 'name' => 'T U', 'email' => test_email('x'), 'password' => 'correct-horse-1']), 'expired') && !db("SELECT 1 FROM users WHERE email = ?", 's', test_email('x'))->num_rows);

$html = signup($alice, 'a', ['name' => "Test O'Brien"], false);
$row = db("SELECT userID, username, first_name, last_name, password, email_verified_at, verify_sent_at, verify_token_hash, date_of_birth FROM users WHERE email = ?", 's', test_email('a'))->fetch_assoc();
check('signup: goes to the check-your-email page', str_ends_with($alice->url, 'verify-email.php') && str_contains($html, 'Check your email'));
check('signup: splits the name, apostrophe included', $row['first_name'] === 'Test' && $row['last_name'] === "O'Brien");
check('signup: a username is generated', (bool) preg_match('/^test\.o\.brien[0-9]*$/', $row['username']));
check('signup: birth date is left empty', $row['date_of_birth'] === null);
check('signup: Resend accepted the email over HTTPS', $row['verify_sent_at'] !== null);
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
$settings = fn($overrides = []) => $overrides + ['first_name' => 'A', 'last_name' => 'B', 'email' => test_email('a'), 'username' => "{$tag}a", 'date_of_birth' => '', 'gender' => '', 'mobile' => '', 'address' => '', 'new_password' => ''];
check('username: a taken name is refused', str_contains($alice->post('settings.php', $settings(['username' => "{$tag}b"]), 'settings.php'), 'already taken'));
check('username: uniqueness ignores capitals', str_contains($alice->post('settings.php', $settings(['username' => strtoupper("{$tag}b")]), 'settings.php'), 'already taken'));
check('username: bad characters are refused', str_contains($alice->post('settings.php', $settings(['username' => "a b'c"]), 'settings.php'), 'Username must be'));
$alice->post('settings.php', $settings(['username' => "{$tag}renamed"]), 'settings.php');
check('username: can be changed', db("SELECT username FROM users WHERE email = ?", 's', test_email('a'))->fetch_row()[0] === "{$tag}renamed");
$alice->post('settings.php', $settings(), 'settings.php');
$alice->post('settings.php', $settings(['date_of_birth' => '2000-01-15', 'gender' => 'Female', 'mobile' => '+880 1712-345678', 'address' => 'Dhaka']), 'settings.php');
$details = db("SELECT date_of_birth, gender, mobile, address FROM users WHERE email = ?", 's', test_email('a'))->fetch_assoc();
check('details: optional fields save', $details === ['date_of_birth' => '2000-01-15', 'gender' => 'Female', 'mobile' => '+880 1712-345678', 'address' => 'Dhaka']);
foreach ([['date_of_birth' => '2999-01-01', 'date of birth'], ['gender' => 'Dragon', 'gender'], ['mobile' => 'call me', 'mobile number'], ['address' => str_repeat('x', 256), 'Address can be']] as $bad) {
    $needle = $bad[0];
    unset($bad[0]);
    check("details: refuses a bad value ($needle)", str_contains($alice->post('settings.php', $settings($bad), 'settings.php'), $needle));
}
$alice->post('settings.php', $settings(['date_of_birth' => '2000-01-15']), 'settings.php');
check('details: blank fields clear them', db("SELECT gender FROM users WHERE email = ?", 's', test_email('a'))->fetch_row()[0] === null);
$ownProfile = $alice->get('profile.php');
check('details: the owner sees them', str_contains($ownProfile, '2000-01-15'));

// ---- Changing email
$newEmail = test_email('a2');
$alice->post('settings.php', $settings(['email' => $newEmail, 'date_of_birth' => '2000-01-15']), 'settings.php');
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
$alice->post("settings.php?userID=$bobID", ['first_name' => 'Hacked', 'last_name' => 'Hacked', 'email' => test_email('a'), 'date_of_birth' => '2000-01-15', 'username' => "{$tag}a", 'new_password' => ''], 'settings.php');
check('profile: ?userID= cannot change another account', db("SELECT email, password FROM users WHERE userID = ?", 'i', $bobID)->fetch_assoc() === $bobBefore);
check('profile: blank password keeps the old one', db("SELECT password FROM users WHERE userID = ?", 'i', $aliceID)->fetch_row()[0] === $hash);
check('profile: the form never shows the password hash', !str_contains($alice->get('settings.php'), $hash));
check('profile: duplicate username refused', str_contains($alice->post('settings.php', ['first_name' => 'A', 'last_name' => 'B', 'email' => test_email('a'), 'date_of_birth' => '2000-01-15', 'username' => "{$tag}b", 'new_password' => ''], 'settings.php'), 'already taken'));
check('profile: changing password needs the current one', str_contains($alice->post('settings.php', ['first_name' => 'A', 'last_name' => 'B', 'email' => test_email('a'), 'date_of_birth' => '2000-01-15', 'username' => "{$tag}a", 'new_password' => 'brand-new-pass-1', 'current_password' => 'wrong'], 'settings.php'), 'current password is incorrect'));
$alice->post('settings.php', ['first_name' => 'A', 'last_name' => 'B', 'email' => test_email('a'), 'date_of_birth' => '2000-01-15', 'username' => "{$tag}a", 'new_password' => 'brand-new-pass-1', 'current_password' => 'correct-horse-1'], 'settings.php');
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
db("UPDATE users SET city = 'Dhaka', area = 'Dhanmondi' WHERE userID = ?", 'i', $aliceID);
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
check('swap: contact email shown after accepting', str_contains($bob->get('requests.php'), test_email('a')));
$requesterView = $bob->get('requests.php');
check('contact: requester sees owner email and mobile once accepted', str_contains($requesterView, test_email('a')) && str_contains($requesterView, '+8801700000001'));
check('contact: requester never sees the owner address', !str_contains($requesterView, 'Owner Lane 1'));
$ownerView = $alice->get('requests.php');
check('contact: owner sees requester email and mobile once accepted', str_contains($ownerView, test_email('b')) && str_contains($ownerView, '+8801700000002'));
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

if (cloudinary_enabled()) {
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

    $profile = ['first_name' => 'A', 'last_name' => 'B', 'email' => test_email('a'), 'date_of_birth' => '2000-01-15', 'username' => "{$tag}a"];
    $alice->upload('settings.php', $profile, ['avatar' => $red], 'settings.php');
    $avatarID = db("SELECT avatar_public_id FROM users WHERE userID = ?", 'i', $aliceID)->fetch_row()[0];
    check('upload: an avatar goes in the avatars folder', str_starts_with((string) $avatarID, 'bookworm-buddies/avatars/'));
    check('upload: the avatar shows in the page header', str_contains($alice->get('home.php'), 'res.cloudinary.com'));

    $doomedCover = test_image([40, 140, 40]);
    $alice->upload('add-book.php', ['title' => "$tag doomed photo"] + $bookFields, ['cover' => $doomedCover], 'add-book.php');
    $doomedImage = db("SELECT cover_public_id FROM books WHERE title = ?", 's', "$tag doomed photo")->fetch_row()[0] ?? null;
    $imagesToGo = [$avatarID, $doomedImage];
} else {
    echo "skip  Cloudinary uploads (no keys in config.php)\n";
    $imagesToGo = [];
}

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
$alice->post('actions/account-delete.php', []);
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
