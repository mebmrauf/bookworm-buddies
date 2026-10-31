<?php
// Each function returns an error message, or null when valid.


// Copies of the same book share one rating, but titles are typed by hand. This treats "The Hobbit" and
// "Hobbit: There and Back Again" by "J.R.R. Tolkien" or "JRR Tolkien" as one book.
function book_key($title, $author) {
    $title = preg_split('/\s*[:(]|\s+-\s+/u', mb_strtolower(trim($title)), 2)[0];
    $title = preg_replace('/^(the|a|an)\s+/u', '', $title);
    $clean = fn($text) => preg_replace('/[^\p{L}\p{N}]+/u', '', $text);
    return md5($clean($title) . '|' . $clean(mb_strtolower($author)));
}

const LISTING_TYPES = ['lend' => 'Lend', 'give' => 'Give away', 'swap' => 'Swap'];

const LISTING_DESCRIPTIONS = [
    'lend' => 'Lend it, and get it back by a date you choose',
    'give' => 'Give it away for free, and the new reader keeps it',
    'swap' => 'Swap it for one of their books, and each of you keeps the new one',
];

const REPORT_REASONS = ['Inappropriate content', 'Spam or fake', 'Copyright', 'Harassment', 'Did not return or exchange a book', 'Something else'];

const DEFAULT_LOAN_DAYS = 14;
const MAX_LOAN_DAYS = 180;

// Returns an error message, or null when valid.
function validate_due_date($date) {
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        return 'Please choose a valid return date.';
    }
    if ($date <= date('Y-m-d') || $date > date('Y-m-d', strtotime('+' . MAX_LOAN_DAYS . ' days'))) {
        return 'The return date must be within the next ' . MAX_LOAN_DAYS . ' days.';
    }
    return null;
}

const HANDOVERS = ['meet' => 'Meet in person', 'courier' => 'Courier, receiver pays', 'both' => 'Meet in person or courier'];

// Bangladesh's 64 districts, so people pick the same spelling and the city filter stays clean.
const CITIES = [
    'Bagerhat', 'Bandarban', 'Barguna', 'Barishal', 'Bhola', 'Bogura', 'Brahmanbaria', 'Chandpur', 'Chapainawabganj', 'Chittagong', 'Chuadanga',
    'Cumilla', "Cox's Bazar", 'Dhaka', 'Dinajpur', 'Faridpur', 'Feni', 'Gaibandha', 'Gazipur', 'Gopalganj', 'Habiganj', 'Jamalpur', 'Jashore',
    'Jhalokathi', 'Jhenaidah', 'Joypurhat', 'Khagrachhari', 'Khulna', 'Kishoreganj', 'Kurigram', 'Kushtia', 'Lakshmipur', 'Lalmonirhat', 'Madaripur',
    'Magura', 'Manikganj', 'Meherpur', 'Moulvibazar', 'Munshiganj', 'Mymensingh', 'Naogaon', 'Narail', 'Narayanganj', 'Narsingdi', 'Natore', 'Netrokona',
    'Nilphamari', 'Noakhali', 'Pabna', 'Panchagarh', 'Patuakhali', 'Pirojpur', 'Rajbari', 'Rajshahi', 'Rangamati', 'Rangpur', 'Satkhira', 'Shariatpur',
    'Sherpur', 'Sirajganj', 'Sunamganj', 'Sylhet', 'Tangail', 'Thakurgaon',
];

function validate_location($city, $area) {
    if ($city !== '' && !in_array($city, CITIES, true)) {
        return 'Please choose a city from the list.';
    }
    if (mb_strlen($area) > 60) {
        return 'Area can be at most 60 characters.';
    }
    return null;
}

function validate_name($firstName, $lastName) {
    if ($firstName === '') {
        return 'Please enter your name.';
    }
    if (mb_strlen($firstName) > 50 || mb_strlen($lastName) > 50) {
        return 'Names can be at most 50 characters.';
    }
    return null;
}

function validate_email($email) {
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }
    return null;
}

function validate_username($username) {
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        return 'Username must be 3-50 letters, numbers, dots, dashes or underscores.';
    }
    return null;
}

function validate_password($password) {
    // bcrypt ignores anything past 72 bytes
    if (strlen($password) < 8 || strlen($password) > 72) {
        return 'Password must be 8-72 characters.';
    }
    return null;
}

// All of these are optional, so blank is always valid.
function validate_details($mobile, $address) {
    if ($mobile !== '' && !preg_match('/^\+?[0-9][0-9 ()-]{5,18}$/', $mobile)) {
        return 'Please enter a valid mobile number.';
    }
    if (mb_strlen($address) > 255) {
        return 'Address can be at most 255 characters.';
    }
    return null;
}

function validate_book($title, $author, $year) {
    if ($title === '' || $author === '' || $year === '') {
        return 'All fields are required.';
    }
    if (mb_strlen($title) > 255 || mb_strlen($author) > 255) {
        return 'Title and author can be at most 255 characters.';
    }
    if (!ctype_digit($year) || (int) $year < 1 || (int) $year > (int) date('Y')) {
        return 'Please enter a valid published year.';
    }
    return null;
}
