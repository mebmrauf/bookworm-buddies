<?php
// End-to-end checks against a running site: php tools/test.php http://localhost:8000
// Creates zztest_* accounts and removes them at the end. Existing data is not touched.
if (PHP_SAPI !== 'cli') {
    exit;
}
require __DIR__ . '/../dbconnect.php';

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
    db("DELETE b FROM books b JOIN collection c ON b.bookID = c.bookID JOIN users u ON c.userID = u.userID WHERE u.username LIKE CONCAT(?, '%')", 's', $tag);
    db("DELETE FROM users WHERE username LIKE CONCAT(?, '%')", 's', $tag);
    db("DELETE FROM login_attempts WHERE username LIKE CONCAT(?, '%')", 's', $tag);
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

    public function rawPost($path, $fields) {
        return $this->request($path, $fields);
    }

    // Takes the CSRF token from $formPage, as a real form would.
    public function post($path, $fields, $formPage = 'profile.php') {
        preg_match('/name="csrf" value="(\w+)"/', $this->get($formPage), $m);
        return $this->request($path, $fields + ['csrf' => $m[1] ?? '']);
    }
}

function signup($browser, $suffix, $overrides = []) {
    global $tag;
    return $browser->post('signup.php', $overrides + [
        'fname' => 'Test', 'lname' => 'User', 'dob' => '2000-01-15',
        'email' => "$tag$suffix@example.com", 'username' => "$tag$suffix", 'password' => 'correct-horse-1',
    ], 'signup.php');
}

function signin($browser, $suffix, $password = 'correct-horse-1') {
    global $tag;
    return $browser->post('signin.php', ['username' => "$tag$suffix", 'password' => $password], 'index.php');
}

function user_id($suffix) {
    global $tag;
    return db("SELECT userID FROM users WHERE username = ?", 's', "$tag$suffix")->fetch_row()[0] ?? null;
}

function add_book($browser, $title, $showcase = true) {
    $browser->post('registerbook.php', ['title' => $title, 'author' => 'Some Author', 'publish' => '1999'], 'registerbook.php');
    $id = db("SELECT bookID FROM books WHERE title = ? ORDER BY bookID DESC LIMIT 1", 's', $title)->fetch_row()[0] ?? 0;
    if ($showcase) {
        $browser->post('showcase.php', ['bookID' => $id, 'showcase' => '1']);
    }
    return $id;
}

function swap_status($id) {
    return db("SELECT status FROM swap WHERE swapID = ?", 'i', $id)->fetch_row()[0] ?? null;
}

function latest_swap($bookID) {
    return db("SELECT swapID FROM swap WHERE bookID = ? ORDER BY swapID DESC LIMIT 1", 'i', $bookID)->fetch_row()[0] ?? null;
}

// ---- Logged out
$anon = new Browser();
foreach (['home.php', 'availablebooks.php', 'requests.php', 'registerbook.php', 'profile.php', 'updateprofile.php', 'search.php?q=a', 'editbookdetails.php?bookID=1'] as $page) {
    $anon->get($page);
    check("logged out: $page redirects to sign in", str_ends_with($anon->url, 'index.php'));
}
foreach (['deletebook.php', 'deleteaccount.php', 'updateswap.php', 'requestbook.php', 'submitfeedback.php', 'replyfeedback.php', 'deletefeedback.php', 'showcase.php'] as $page) {
    $anon->rawPost($page, ['bookID' => 1]);
    check("logged out: POST $page redirects to sign in", str_ends_with($anon->url, 'index.php'));
}

// ---- Signup and sign in
$alice = new Browser();
$bob = new Browser();
$carol = new Browser();

check('signup: rejects a short password', str_contains(signup($alice, 'a', ['password' => 'short']), 'Password must be'));
check('signup: rejects a bad email', str_contains(signup($alice, 'a', ['email' => 'nope']), 'valid email'));
check('signup: rejects a future birth date', str_contains(signup($alice, 'a', ['dob' => '2999-01-01']), 'date of birth'));
check('signup: rejects a bad username', str_contains(signup($alice, 'a', ['username' => "a' OR 1=1 --"]), 'Username must be'));
check('signup: wrong CSRF token refused', !user_id('x') && str_contains($alice->rawPost('signup.php', ['csrf' => 'bad', 'fname' => 'T', 'lname' => 'U', 'dob' => '2000-01-01', 'email' => "{$tag}x@example.com", 'username' => "{$tag}x", 'password' => 'correct-horse-1']), 'expired') && !user_id('x'));

$html = signup($alice, 'a', ['lname' => "O'Brien"]);
check('signup: apostrophe in a name works', str_contains($html, 'Registration successful'));
check('signup: name stored literally', db("SELECT last_name FROM users WHERE userID = ?", 'i', user_id('a'))->fetch_row()[0] === "O'Brien");
$hash = db("SELECT password FROM users WHERE userID = ?", 'i', user_id('a'))->fetch_row()[0];
check('password stored as a hash', password_verify('correct-horse-1', $hash) && !str_contains($hash, 'correct-horse'));
check('signup: duplicate username refused', str_contains(signup($alice, 'a', ['email' => "{$tag}other@example.com"]), 'already used'));
check('signup: duplicate email refused', str_contains(signup($alice, 'z', ['email' => "{$tag}a@example.com"]), 'already used'));
check('signup: injection text is only text', str_contains(signup($alice, 'q', ['fname' => "x'); DROP TABLE users; --"]), 'Registration successful') && db("SELECT COUNT(*) FROM users")->fetch_row()[0] > 0);
signup($bob, 'b');
signup($carol, 'c');

check('signin: wrong password refused', str_contains(signin($alice, 'a', 'wrong-password'), 'Invalid username'));
signin($alice, 'a');
check('signin: correct password works', str_ends_with($alice->url, 'home.php'));
signin($bob, 'b');
signin($carol, 'c');
$alice->get('index.php');
check('signin: signed-in user skips the form', str_ends_with($alice->url, 'home.php'));

$limited = new Browser();
for ($i = 0; $i < 5; $i++) {
    signin($limited, 'q', 'wrong-password');
}
check('signin: blocked after 5 failures', str_contains(signin($limited, 'q'), 'Too many failed attempts') && !str_ends_with($limited->url, 'home.php'));

// ---- Finding 1: account takeover
$aliceID = user_id('a');
$bobID = user_id('b');
$carolID = user_id('c');
$bobBefore = db("SELECT email, password FROM users WHERE userID = ?", 'i', $bobID)->fetch_assoc();
$alice->post("updateprofile.php?userID=$bobID", ['first_name' => 'Hacked', 'last_name' => 'Hacked', 'email' => "{$tag}a@example.com", 'date_of_birth' => '2000-01-15', 'username' => "{$tag}a", 'new_password' => ''], 'updateprofile.php');
check('profile: ?userID= cannot change another account', db("SELECT email, password FROM users WHERE userID = ?", 'i', $bobID)->fetch_assoc() === $bobBefore);
check('profile: blank password keeps the old one', db("SELECT password FROM users WHERE userID = ?", 'i', $aliceID)->fetch_row()[0] === $hash);
check('profile: the form never shows the password hash', !str_contains($alice->get('updateprofile.php'), $hash));
check('profile: duplicate username refused', str_contains($alice->post('updateprofile.php', ['first_name' => 'A', 'last_name' => 'B', 'email' => "{$tag}a@example.com", 'date_of_birth' => '2000-01-15', 'username' => "{$tag}b", 'new_password' => ''], 'updateprofile.php'), 'already used'));
check('profile: changing password needs the current one', str_contains($alice->post('updateprofile.php', ['first_name' => 'A', 'last_name' => 'B', 'email' => "{$tag}a@example.com", 'date_of_birth' => '2000-01-15', 'username' => "{$tag}a", 'new_password' => 'brand-new-pass-1', 'current_password' => 'wrong'], 'updateprofile.php'), 'current password is incorrect'));
$alice->post('updateprofile.php', ['first_name' => 'A', 'last_name' => 'B', 'email' => "{$tag}a@example.com", 'date_of_birth' => '2000-01-15', 'username' => "{$tag}a", 'new_password' => 'brand-new-pass-1', 'current_password' => 'correct-horse-1'], 'updateprofile.php');
check('profile: password change works', password_verify('brand-new-pass-1', db("SELECT password FROM users WHERE userID = ?", 'i', $aliceID)->fetch_row()[0]));

// ---- Books
check('book: bad year refused', str_contains($alice->post('registerbook.php', ['title' => 'T', 'author' => 'A', 'publish' => 'abc'], 'registerbook.php'), 'valid published year'));
check('book: over-long title refused, no crash', str_contains($alice->post('registerbook.php', ['title' => str_repeat('x', 256), 'author' => 'A', 'publish' => '2000'], 'registerbook.php'), 'at most 255') && $alice->code === 200);
$long = str_repeat('L', 60) . $tag;
$longID = add_book($alice, $long, false);
check('book: 60+ character title now saves', $longID > 0);
$shown = add_book($alice, "$tag shown");
$hidden = add_book($alice, "$tag hidden", false);

// Findings 4 and 6
$bob->post('deletebook.php', ['bookID' => $shown]);
check('book: another user cannot delete it', db("SELECT 1 FROM books WHERE bookID = ?", 'i', $shown)->num_rows === 1);
$bob->get("editbookdetails.php?bookID=$shown");
check('book: another user cannot open the edit page', str_ends_with($bob->url, 'profile.php'));
$bob->post("editbookdetails.php?bookID=$shown", ['title' => 'Hacked', 'author' => 'Hacked', 'year_published' => '2000']);
check('book: another user cannot edit it', db("SELECT title FROM books WHERE bookID = ?", 'i', $shown)->fetch_row()[0] === "$tag shown");
$bob->post('showcase.php', ['bookID' => $hidden, 'showcase' => '1']);
check('book: another user cannot showcase it', db("SELECT showcase FROM collection WHERE bookID = ?", 'i', $hidden)->fetch_row()[0] == 0);
$alice->get("deletebook.php?bookID=$longID");
check('csrf: a plain link does not delete a book', db("SELECT 1 FROM books WHERE bookID = ?", 'i', $longID)->num_rows === 1);
$alice->rawPost('deletebook.php', ['bookID' => $longID, 'csrf' => 'forged']);
check('csrf: a forged form does not delete a book', db("SELECT 1 FROM books WHERE bookID = ?", 'i', $longID)->num_rows === 1);
$alice->post("editbookdetails.php?bookID=$longID", ['title' => "$tag edited", 'author' => 'A', 'year_published' => '2001']);
check('book: owner can edit it', db("SELECT title FROM books WHERE bookID = ?", 'i', $longID)->fetch_row()[0] === "$tag edited");
$alice->post('deletebook.php', ['bookID' => $longID]);
check('book: owner can delete it', db("SELECT 1 FROM books WHERE bookID = ?", 'i', $longID)->num_rows === 0);

// ---- Finding 16: privacy
$visit = $bob->get("profile.php?userID=$aliceID");
check('privacy: visitor does not see email', !str_contains($visit, "{$tag}a@example.com"));
check('privacy: visitor does not see birth date', !str_contains($visit, '2000-01-15'));
check('privacy: visitor sees showcased books', str_contains($visit, "$tag shown"));
check('privacy: visitor does not see other books', !str_contains($visit, "$tag hidden"));
// <strong> marks a result; the page heading also repeats the search term.
check('privacy: search hides non-showcased books', !str_contains($bob->get('search.php?q=' . urlencode("$tag hidden")), "<strong>$tag hidden"));
check('search: owner finds their own hidden book', str_contains($alice->get('search.php?q=' . urlencode("$tag hidden")), "<strong>$tag hidden"));
check('search: finds a showcased book by title', str_contains($bob->get('search.php?q=' . urlencode("$tag shown")), "<strong>$tag shown"));
check('search: finds users', str_contains($bob->get("search.php?q={$tag}c"), "{$tag}c"));
check('search: % is not a wildcard', str_contains($bob->get('search.php?q=' . urlencode("%{$tag}zzz%")), 'Nothing found'));
check('search: 0 is a valid search', !str_contains($bob->get('search.php?q=0'), 'Please enter'));
check('search: output is escaped', !str_contains($bob->get('search.php?q=' . urlencode('<script>x</script>')), '<script>x'));

// ---- Findings 5, 7, 8: swaps
$alice->post('requestbook.php', ['bookID' => $shown]);
check('swap: cannot request your own book', latest_swap($shown) === null);
$bob->post('requestbook.php', ['bookID' => $hidden]);
check('swap: cannot request a non-showcased book', latest_swap($hidden) === null);
check('swap: book offered on Available Books', str_contains($bob->get('availablebooks.php'), "$tag shown"));

$bob->post('requestbook.php', ['bookID' => $shown]);
$swap = latest_swap($shown);
check('swap: request created as pending', swap_status($swap) === 'pending');
check('swap: goes to the right owner', db("SELECT ownerID FROM swap WHERE swapID = ?", 'i', $swap)->fetch_row()[0] == $aliceID);
check('swap: leaves Available Books once requested', !str_contains($bob->get('availablebooks.php'), "$tag shown"));
check('swap: Home shows Not Available', str_contains($carol->get('home.php'), 'Not Available'));
$carol->post('requestbook.php', ['bookID' => $shown]);
check('swap: second open request refused', latest_swap($shown) == $swap);
$reqPage = $alice->get('requests.php');
check('swap: owner sees the requester by name', str_contains($reqPage, "{$tag}b") && !str_contains($reqPage, 'Requester ID'));
check('swap: no email shown before accepting', !str_contains($reqPage, "{$tag}b@example.com"));

$bob->post('updateswap.php', ['swapID' => $swap, 'action' => 'accept']);
check('swap: requester cannot accept their own request', swap_status($swap) === 'pending');
$carol->post('updateswap.php', ['swapID' => $swap, 'action' => 'accept']);
check('swap: stranger cannot accept', swap_status($swap) === 'pending');
$carol->post('updateswap.php', ['swapID' => $swap, 'action' => 'decline']);
check('swap: stranger cannot decline', swap_status($swap) === 'pending');
$carol->post('updateswap.php', ['swapID' => $swap, 'action' => 'cancel']);
check('swap: stranger cannot cancel', swap_status($swap) === 'pending');
$alice->get("updateswap.php?swapID=$swap&action=accept");
check('csrf: a plain link does not accept a swap', swap_status($swap) === 'pending');
$alice->post('updateswap.php', ['swapID' => $swap, 'action' => 'return']);
check('swap: cannot return before accepting', swap_status($swap) === 'pending');
$alice->post('updateswap.php', ['swapID' => $swap, 'action' => 'accept']);
check('swap: owner can accept', swap_status($swap) === 'accepted');
check('swap: contact email shown after accepting', str_contains($bob->get('requests.php'), "{$tag}a@example.com"));
check('swap: shows in the requester\'s Swapped Books', str_contains($bob->get('profile.php?show=swapped'), "$tag shown"));
$alice->post('deletebook.php', ['bookID' => $shown]);
check('swap: a lent book cannot be deleted', db("SELECT 1 FROM books WHERE bookID = ?", 'i', $shown)->num_rows === 1);
$alice->post('updateswap.php', ['swapID' => $swap, 'action' => 'accept']);
$bob->post('updateswap.php', ['swapID' => $swap, 'action' => 'return']);
check('swap: requester cannot mark it returned', swap_status($swap) === 'accepted');
$alice->post('updateswap.php', ['swapID' => $swap, 'action' => 'return']);
check('swap: owner can mark it returned', swap_status($swap) === 'returned');
$alice->post('updateswap.php', ['swapID' => $swap, 'action' => 'accept']);
check('swap: a returned swap cannot be reopened', swap_status($swap) === 'returned');

$availablePage = $carol->get('availablebooks.php');
check('swap: available again after return', str_contains($availablePage, "$tag shown"));
$carol->post('requestbook.php', ['bookID' => $shown]);
$swap2 = latest_swap($shown);
$alice->post('updateswap.php', ['swapID' => $swap2, 'action' => 'decline']);
check('swap: owner can decline', swap_status($swap2) === 'declined');
check('swap: no duplicates after several old swaps', substr_count($bob->get('availablebooks.php'), "$tag shown") === 1);
$carol->post('requestbook.php', ['bookID' => $shown]);
$swap3 = latest_swap($shown);
$alice->post('updateswap.php', ['swapID' => $swap3, 'action' => 'accept']);
check('swap: lent book with old declined swap stays hidden (finding 8)', !str_contains($bob->get('availablebooks.php'), "$tag shown"));
$alice->post('updateswap.php', ['swapID' => $swap3, 'action' => 'return']);
$bob->post('requestbook.php', ['bookID' => $shown]);
$swap4 = latest_swap($shown);
$bob->post('updateswap.php', ['swapID' => $swap4, 'action' => 'cancel']);
check('swap: requester can cancel', swap_status($swap4) === null);

// ---- Findings 14, 15: feedback
$comment = "Don't miss it, a<b \"really\"";
$bob->post('submitfeedback.php', ['bookID' => $shown, 'comment' => $comment]);
$feedback = db("SELECT feedbackID, comment FROM feedback WHERE bookID = ?", 'i', $shown)->fetch_assoc();
check('feedback: text saved exactly as typed', ($feedback['comment'] ?? '') === $comment);
check('feedback: text shown correctly and escaped', str_contains($bob->get("profile.php?userID=$aliceID"), htmlspecialchars($comment)));
check('feedback: over 500 characters refused', str_contains($bob->post('submitfeedback.php', ['bookID' => $shown, 'comment' => str_repeat('x', 501)]), '1-500'));
$alice->post('submitfeedback.php', ['bookID' => $shown, 'comment' => 'my own']);
check('feedback: owner cannot review their own book', db("SELECT COUNT(*) FROM feedback WHERE bookID = ?", 'i', $shown)->fetch_row()[0] == 1);
$bob->post('submitfeedback.php', ['bookID' => $hidden, 'comment' => 'sneaky']);
check('feedback: refused on a non-showcased book', db("SELECT COUNT(*) FROM feedback WHERE bookID = ?", 'i', $hidden)->fetch_row()[0] == 0);
$carol->post('replyfeedback.php', ['feedbackID' => $feedback['feedbackID'], 'reply' => 'hijack']);
check('feedback: stranger cannot reply', db("SELECT reply FROM feedback WHERE feedbackID = ?", 'i', $feedback['feedbackID'])->fetch_row()[0] === null);
$alice->post('replyfeedback.php', ['feedbackID' => $feedback['feedbackID'], 'reply' => 'Thanks!']);
check('feedback: owner can reply', db("SELECT reply FROM feedback WHERE feedbackID = ?", 'i', $feedback['feedbackID'])->fetch_row()[0] === 'Thanks!');
$carol->post('deletefeedback.php', ['feedbackID' => $feedback['feedbackID']]);
check('feedback: stranger cannot delete', db("SELECT 1 FROM feedback WHERE feedbackID = ?", 'i', $feedback['feedbackID'])->num_rows === 1);
$bob->post('deletefeedback.php', ['feedbackID' => $feedback['feedbackID']]);
check('feedback: author can delete', db("SELECT 1 FROM feedback WHERE feedbackID = ?", 'i', $feedback['feedbackID'])->num_rows === 0);

// ---- Pages render cleanly
foreach (['home.php', 'availablebooks.php', 'requests.php', 'registerbook.php', 'profile.php', 'profile.php?show=showcased', 'profile.php?show=swapped', 'profile.php?show[]=x', 'profile.php?userID=999999999', 'updateprofile.php', 'search.php', 'search.php?q[]=x'] as $page) {
    $html = $alice->get($page);
    check("render: $page", $alice->code === 200 && !preg_match('/Warning|Fatal error|Notice|Deprecated|Something went wrong/', $html) && substr_count(strtolower($html), '<html') <= 1);
}
$html = $alice->post('registerbook.php', ['title' => ['x'], 'author' => ['y'], 'publish' => ['z']], 'registerbook.php');
check('render: array input is refused, no crash', $alice->code === 200 && str_contains($html, 'All fields are required'));

// ---- Logout and finding 10: account deletion
$carol->get('logout.php');
$carol->get('profile.php');
check('logout: a plain link does not log out', str_ends_with($carol->url, 'profile.php'));
$carol->post('logout.php', []);
$carol->get('profile.php');
check('logout: works', str_ends_with($carol->url, 'index.php'));

$aliceBook = add_book($alice, "$tag doomed");
$bob->post('submitfeedback.php', ['bookID' => $aliceBook, 'comment' => 'nice']);
$bob->post('requestbook.php', ['bookID' => $aliceBook]);
$alice->get('deleteaccount.php');
check('csrf: a plain link does not delete an account', user_id('a') !== null);
$alice->post('deleteaccount.php', []);
check('account: user deleted', user_id('a') === null);
check('account: their books deleted (finding 10)', db("SELECT COUNT(*) FROM books WHERE bookID IN (?, ?, ?)", 'iii', $aliceBook, $shown, $hidden)->fetch_row()[0] == 0);
check('account: their swaps deleted', latest_swap($aliceBook) === null);
check('account: feedback on their books deleted', db("SELECT COUNT(*) FROM feedback WHERE bookID = ?", 'i', $aliceBook)->fetch_row()[0] == 0);
check('account: other users remain', user_id('b') !== null);
$alice->get('home.php');
check('account: session ended', str_ends_with($alice->url, 'index.php'));

echo "\n", $checks - $failures, " of $checks checks passed\n";
exit($failures ? 1 : 0);
