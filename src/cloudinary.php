<?php
const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
const IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

class ImageError extends RuntimeException {}

function cloudinary_enabled() {
    $c = config('cloudinary');
    return !empty($c['cloud_name']) && !empty($c['api_key']) && !empty($c['api_secret']);
}

// Cloudinary's rule: sort params by name, join as a=1&b=2, append the secret, SHA-1.
function cloudinary_signature(array $params, $secret) {
    ksort($params);
    $pairs = [];
    foreach ($params as $name => $value) {
        $pairs[] = "$name=$value";
    }
    return sha1(implode('&', $pairs) . $secret);
}

function cloudinary_request($endpoint, array $params) {
    $c = config('cloudinary');
    $file = $params['file'] ?? null;
    unset($params['file']);
    $params['timestamp'] = time();
    $params['signature'] = cloudinary_signature($params, $c['api_secret']);
    $params['api_key'] = $c['api_key'];
    if ($file) {
        $params['file'] = $file;
    }

    $ch = curl_init("https://api.cloudinary.com/v1_1/{$c['cloud_name']}/image/$endpoint");
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $params, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);

    $data = is_string($body) ? json_decode($body, true) : null;
    if ($status !== 200 || !is_array($data)) {
        error_log("Cloudinary $endpoint failed ($status): " . ($error ?: $body));
        return null;
    }
    return $data;
}

// Returns the new public ID, or null when no file was chosen. Throws ImageError with a message for the user.
function upload_image_field($field, $folder) {
    $upload = $_FILES[$field] ?? null;
    if (!$upload || !is_int($upload['error']) || $upload['error'] === UPLOAD_ERR_NO_FILE || !cloudinary_enabled()) {
        return null;
    }
    if (in_array($upload['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $upload['size'] > MAX_IMAGE_BYTES) {
        throw new ImageError('Image must be 5 MB or smaller.');
    }
    if ($upload['error'] !== UPLOAD_ERR_OK) {
        throw new ImageError('The image could not be uploaded. Please try again.');
    }

    // Trust the file's contents, not the name or type the browser sent.
    $type = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    if (!in_array($type, IMAGE_TYPES, true) || !getimagesize($upload['tmp_name'])) {
        throw new ImageError('Please choose a JPG, PNG or WebP image.');
    }

    $result = cloudinary_request('upload', ['file' => new CURLFile($upload['tmp_name'], $type), 'folder' => "bookworm-buddies/$folder"]);
    if (empty($result['public_id'])) {
        throw new ImageError('The image could not be uploaded. Please try again.');
    }
    return $result['public_id'];
}

// Best effort: a leftover image costs little, so a failure here must not fail the user's action.
function delete_images(array $publicIDs) {
    if (!cloudinary_enabled()) {
        return;
    }
    foreach (array_filter($publicIDs) as $id) {
        cloudinary_request('destroy', ['public_id' => $id]);
    }
}

// Covers aren't all 2:3: padding with a matching color keeps their text from being cropped.
function image_url($publicID, $width, $height, $crop = 'c_pad,b_auto:predominant') {
    $cloud = config('cloudinary')['cloud_name'] ?? '';
    $path = implode('/', array_map('rawurlencode', explode('/', $publicID)));
    return "https://res.cloudinary.com/$cloud/image/upload/$crop,w_$width,h_$height,f_auto,q_auto/$path";
}
