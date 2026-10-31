<?php
// Each function returns an error message, or null when valid.

function validate_user($firstName, $lastName, $email, $dob, $username) {
    if ($firstName === '' || $lastName === '' || $email === '' || $dob === '' || $username === '') {
        return 'All fields are required.';
    }
    if (mb_strlen($firstName) > 50 || mb_strlen($lastName) > 50) {
        return 'Names can be at most 50 characters.';
    }
    if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
        return 'Username must be 3-50 letters, numbers, dots, dashes or underscores.';
    }
    if (strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }
    $date = DateTime::createFromFormat('!Y-m-d', $dob);
    if (!$date || $date->format('Y-m-d') !== $dob || $date > new DateTime()) {
        return 'Please enter a valid date of birth.';
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
