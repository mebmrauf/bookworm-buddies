<?php
function e($value) {
    return htmlspecialchars((string) $value);
}

function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

// Icons are decorative: every use sits next to visible text or inside a labelled control.
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
    ];
    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
}

// A designed placeholder keeps the grid even for books without a photo.
function book_cover($book, $width = 300) {
    $height = (int) round($width * 1.5);
    if (!empty($book['cover_public_id']) && cloudinary_enabled()) {
        return '<img class="cover" loading="lazy" width="' . $width . '" height="' . $height . '" alt="Cover of ' . e($book['title']) . '"'
            . ' src="' . e(image_url($book['cover_public_id'], $width, $height)) . '"'
            . ' srcset="' . e(image_url($book['cover_public_id'], $width * 2, $height * 2)) . ' 2x">';
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
