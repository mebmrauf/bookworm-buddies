<?php
const MAX_INPUT_BYTES = 10 * 1024 * 1024;
const MAX_INPUT_PIXELS = 30000000; // Decoding larger images could exhaust the small server's memory.
const CLOUDINARY_MAX_BYTES = 4500000; // The free plan's limit, as stated by the account owner.
const HD_LONG_EDGE = 1280;
const JPEG_QUALITIES = [82, 70, 55, 40];
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

// Applies the camera's rotation, which is lost once the photo is re-encoded.
function apply_exif_rotation($image, $path, $type) {
    if ($type !== 'image/jpeg' || !function_exists('exif_read_data')) {
        return $image;
    }
    $orientation = @exif_read_data($path)['Orientation'] ?? 1;
    $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;
    return $angle ? imagerotate($image, $angle, 0) : $image;
}

// Re-encodes an upload as an HD JPEG, so raw files never reach Cloudinary. This also strips hidden data like GPS location.
// Returns a temporary file's path for the caller to delete.
function compress_to_hd($path, $type, $width, $height) {
    if ($width * $height > MAX_INPUT_PIXELS) {
        throw new ImageError('That photo has too many pixels. Please choose a smaller one.');
    }
    ini_set('memory_limit', '256M');
    $source = @imagecreatefromstring(file_get_contents($path));
    if (!$source) {
        throw new ImageError('Please choose a JPG, PNG or WebP image.');
    }
    $source = apply_exif_rotation($source, $path, $type);
    $width = imagesx($source);
    $height = imagesy($source);

    $scale = min(1, HD_LONG_EDGE / max($width, $height));
    $newWidth = max(1, (int) round($width * $scale));
    $newHeight = max(1, (int) round($height * $scale));
    $hd = imagecreatetruecolor($newWidth, $newHeight);
    // JPEG has no transparency, so transparent areas become white instead of black.
    imagefill($hd, 0, 0, imagecolorallocate($hd, 255, 255, 255));
    imagecopyresampled($hd, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $output = tempnam(sys_get_temp_dir(), 'hd');
    foreach (JPEG_QUALITIES as $quality) {
        imagejpeg($hd, $output, $quality);
        clearstatcache(true, $output);
        if (filesize($output) <= CLOUDINARY_MAX_BYTES) {
            return $output;
        }
    }
    unlink($output);
    throw new ImageError('That photo could not be compressed enough. Please choose another.');
}

// Returns the new public ID, or null when no file was chosen. Throws ImageError with a message for the user.
function upload_image_field($field, $folder) {
    $upload = $_FILES[$field] ?? null;
    if (!$upload || !is_int($upload['error']) || $upload['error'] === UPLOAD_ERR_NO_FILE || !cloudinary_enabled()) {
        return null;
    }
    if (in_array($upload['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || $upload['size'] > MAX_INPUT_BYTES) {
        throw new ImageError('Image must be 10 MB or smaller.');
    }
    if ($upload['error'] !== UPLOAD_ERR_OK) {
        throw new ImageError('The image could not be uploaded. Please try again.');
    }

    // Trust the file's contents, not the name or type the browser sent.
    $type = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
    $size = getimagesize($upload['tmp_name']);
    if (!in_array($type, IMAGE_TYPES, true) || !$size) {
        throw new ImageError('Please choose a JPG, PNG or WebP image.');
    }

    $hd = compress_to_hd($upload['tmp_name'], $type, $size[0], $size[1]);
    $result = cloudinary_request('upload', ['file' => new CURLFile($hd, 'image/jpeg'), 'folder' => "bookworm-buddies/$folder"]);
    unlink($hd);
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
    return "https://res.cloudinary.com/$cloud/image/upload/$crop,w_$width,h_$height,f_auto,q_auto:eco/$path";
}
