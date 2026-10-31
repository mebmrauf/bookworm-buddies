<?php
function e($value) {
    return htmlspecialchars((string) $value);
}

function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

// Icons are decorative: every use sits next to visible text or inside a labelled control.
// A cover for each post's book, in one query. Keys are lower case, because titles match without regard to case.
function post_covers($posts) {
    $titles = array_values(array_unique(array_column($posts, 'book_title')));
    if (!$titles) {
        return [];
    }
    $rows = run(
        "SELECT title, MIN(cover_public_id) FROM books WHERE title IN (" . implode(',', array_fill(0, count($titles), '?')) . ") AND cover_public_id IS NOT NULL AND removed_at IS NULL GROUP BY title",
        str_repeat('s', count($titles)), ...$titles
    )->get_result()->fetch_all();
    $covers = [];
    foreach ($rows as [$title, $cover]) {
        $covers[mb_strtolower($title)] = $cover;
    }
    return $covers;
}

function password_input($id, $name, $attributes) {
    return '<div class="password-field"><input id="' . $id . '" type="password" name="' . $name . '" ' . $attributes . '>'
        . '<button type="button" class="password-field__toggle" data-password-toggle aria-label="Show password" aria-pressed="false">'
        . '<span data-eye-show>' . icon('eye') . '</span><span data-eye-hide hidden>' . icon('eye-off') . '</span></button></div>';
}

function icon($name) {
    $paths = [
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'compass' => '<circle cx="12" cy="12" r="10"/><path d="m16.2 7.8-2 6.4-6.4 2 2-6.4z"/>',
        'plus' => '<path d="M5 12h14"/><path d="M12 5v14"/>',
        'inbox' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5.1 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.5-6.9A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.1"/>',
        'book' => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'settings' => '<path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/>',
        'eye' => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7"/><circle cx="12" cy="12" r="3"/>',
        'eye-off' => '<path d="M10.7 5.1A10 10 0 0 1 12 5c6.5 0 10 7 10 7a18 18 0 0 1-2.2 3.2"/><path d="M6.6 6.6A18 18 0 0 0 2 12s3.5 7 10 7a10 10 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="m2 2 20 20"/>',
        'external' => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        'close' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'download' => '<path d="M12 3v12"/><path d="m7 10 5 5 5-5"/><path d="M5 21h14"/>',
        'pen' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'pin' => '<path d="M20 10c0 5-8 12-8 12s-8-7-8-12a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
        'login' => '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/>',
    ];
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
}

function asset($file) {
    // A new version gets a new address, so browsers can keep the old one for a year without going stale.
    return "/assets/$file?v=" . filemtime(__DIR__ . "/../public/assets/$file");
}

// A designed placeholder keeps the grid even for books without a photo.
// $sizes says how wide the cover shows on screen, so a phone doesn't download a desktop-sized image.
function book_cover($book, $sizes = '(min-width: 1500px) 170px, (min-width: 1200px) 240px, (min-width: 960px) 280px, (min-width: 640px) 31vw, 46vw') {
    if (!empty($book['cover_public_id']) && cloudinary_enabled()) {
        $srcset = [];
        foreach ([120, 200, 320, 480, 640] as $width) {
            $srcset[] = image_url($book['cover_public_id'], $width, $width * 3 / 2) . " {$width}w";
        }
        return '<img class="cover" loading="lazy" decoding="async" width="320" height="480" alt="Cover of ' . e($book['title']) . '"'
            . ' src="' . e(image_url($book['cover_public_id'], 320, 480)) . '"'
            . ' srcset="' . e(implode(', ', $srcset)) . '" sizes="' . e($sizes) . '">';
    }
    return '<div class="cover cover--blank cover--tone-' . ($book['bookID'] % 6) . '" role="img" aria-label="No cover photo for ' . e($book['title']) . '">'
        . '<span class="cover__title">' . e($book['title']) . '</span>'
        . '<span class="cover__author">' . e($book['author']) . '</span></div>';
}

function avatar($person, $size = 40) {
    if (!empty($person['avatar_public_id']) && cloudinary_enabled()) {
        return '<img class="avatar avatar--' . $size . '" width="' . $size . '" height="' . $size . '" alt=""'
            . ' src="' . e(image_url($person['avatar_public_id'], $size * 2, $size * 2, 'c_fill,g_face')) . '">';
    }
    $initials = mb_strtoupper(mb_substr($person['first_name'], 0, 1) . mb_substr($person['last_name'], 0, 1));
    return '<span class="avatar avatar--' . $size . '" aria-hidden="true">' . e($initials) . '</span>';
}

// A photo picker with a preview. The real file input covers the box, so clicking and dragging a file both work natively.
function upload_box($field, $previewAlt, $currentHtml = null, $round = false, $hasPhoto = null) {
    $action = ($hasPhoto ?? $currentHtml) ? 'Change photo' : 'Choose a photo';
    return '<div class="upload' . ($round ? ' upload--round' : '') . '" data-upload>'
        . '<div class="upload__picture">'
        . ($currentHtml ? '<div class="upload__current" data-upload-current>' . $currentHtml . '</div>' : '<span class="upload__placeholder" data-upload-current>' . icon('image') . '</span>')
        . '<img class="upload__preview" data-upload-preview alt="' . e($previewAlt) . '" hidden>'
        . '</div>'
        . '<div class="upload__text">'
        . '<strong data-upload-name data-default="' . $action . '">' . $action . '</strong>'
        . '<span class="help" data-upload-hint>Click or drag a file here. JPG, PNG or WebP. Large photos are compressed for you.</span>'
        . '</div>'
        . '<input class="upload__input" id="' . e($field) . '" type="file" name="' . e($field) . '" accept="image/jpeg,image/png,image/webp">'
        . '<button class="btn-text upload__clear" type="button" data-upload-clear hidden>Remove</button>'
        . '</div>';
}

function stars_label($average, $count) {
    if (!$count) {
        return 'No ratings yet';
    }
    return '★ ' . number_format((float) $average, 1) . ' (' . (int) $count . ' ' . ($count == 1 ? 'rating' : 'ratings') . ')';
}

function report_form($type, $id) {
    ob_start();
    ?>
    <details class="report">
        <summary>Report</summary>
        <form class="form" action="/actions/report.php" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="type" value="<?= e($type) ?>">
            <input type="hidden" name="id" value="<?= (int) $id ?>">
            <div class="field">
                <label for="reason-<?= e($type . $id) ?>">What's wrong?</label>
                <select id="reason-<?= e($type . $id) ?>" name="reason" required>
                    <?php foreach (REPORT_REASONS as $reason): ?>
                        <option value="<?= e($reason) ?>"><?= e($reason) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="details-<?= e($type . $id) ?>">More details <span class="optional">(optional)</span></label>
                <textarea id="details-<?= e($type . $id) ?>" name="details" rows="2" maxlength="500"></textarea>
            </div>
            <div><button type="submit" class="btn btn--ghost">Send report</button></div>
        </form>
    </details>
    <?php
    return ob_get_clean();
}

// Short lines from named works, because many popular "book quotes" online are misattributed.
const BOOK_QUOTES = [
    ['Books are a uniquely portable magic.', 'Stephen King', 'On Writing'],
    ['I have always imagined that Paradise will be a kind of library.', 'Jorge Luis Borges', 'Poem of the Gifts'],
    ['The reading of all good books is like a conversation with the finest minds of past centuries.', 'René Descartes', 'Discourse on Method'],
    ['I declare after all there is no enjoyment like reading!', 'Jane Austen', 'Pride and Prejudice'],
    ['The person, be it gentleman or lady, who has not pleasure in a good novel, must be intolerably stupid.', 'Jane Austen', 'Northanger Abbey'],
    ['Reading is to the mind what exercise is to the body.', 'Richard Steele', 'The Tatler'],
    ['Books are the quietest and most constant of friends.', 'Charles W. Eliot', 'The Happy Life'],
    ['A reader lives a thousand lives before he dies. The man who never reads lives only one.', 'George R.R. Martin', 'A Dance with Dragons'],
    ['Until I feared I would lose it, I never loved to read. One does not love breathing.', 'Harper Lee', 'To Kill a Mockingbird'],
];

function quote_of_the_day() {
    [$text, $author, $work] = BOOK_QUOTES[(int) date('z') % count(BOOK_QUOTES)];
    return '<figure class="book-quote"><p class="book-quote__label">Quote of the day</p><blockquote>' . e($text) . '</blockquote>'
        . '<figcaption>' . e($author) . ', <cite>' . e($work) . '</cite></figcaption></figure>';
}

function location_label($person) {
    return implode(', ', array_filter([$person['area'] ?? null, $person['city'] ?? null]));
}

function show_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $role = $flash['type'] === 'error' ? 'alert' : 'status';
        echo '<p class="notice notice--' . e($flash['type']) . '" role="' . $role . '">' . e($flash['message']) . '</p>';
    }
}

function form_error($error) {
    if (!empty($error)) {
        echo '<p class="notice notice--error" role="alert">' . e($error) . '</p>';
    }
}
