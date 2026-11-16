<?php
if (!defined('SITE_URL')) { http_response_code(403); exit('Forbidden'); }

const BOOK_COVER_MAX_BYTES = 2097152;

function bookCoverUploadDir() {
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'covers';
}

function bookCoverFilename($value) {
    $value = trim((string)$value);
    if ($value === '' || basename($value) !== $value) {
        return '';
    }
    return preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $value) ? $value : '';
}

function bookCoverUrl($value) {
    $file = bookCoverFilename($value);
    if ($file === '') {
        return SITE_URL . '/assets/img/cover-placeholder.svg';
    }
    return SITE_URL . '/uploads/covers/' . $file;
}

function bookCoverUploadError($code) {
    switch ((int)$code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Cover image is too large. Maximum size is 2 MB.';
        case UPLOAD_ERR_PARTIAL:
            return 'The cover image was only partially uploaded. Please try again.';
        case UPLOAD_ERR_NO_TMP_DIR:
        case UPLOAD_ERR_CANT_WRITE:
        case UPLOAD_ERR_EXTENSION:
            return 'The server could not save the cover image. Please try again.';
        case UPLOAD_ERR_NO_FILE:
            return '';
        default:
            return 'The cover image could not be uploaded. Please try again.';
    }
}

function bookCoverProcess($field, $existing, $removeRequested) {
    $result = [
        'filename' => bookCoverFilename($existing) !== '' ? bookCoverFilename($existing) : null,
        'old_to_delete' => null,
        'error' => '',
    ];

    if (!isset($_FILES[$field])) {
        if ($removeRequested && $result['filename'] !== null) {
            $result['old_to_delete'] = $result['filename'];
            $result['filename'] = null;
        }
        return $result;
    }

    $file = $_FILES[$field];
    $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error === UPLOAD_ERR_NO_FILE) {
        if ($removeRequested && $result['filename'] !== null) {
            $result['old_to_delete'] = $result['filename'];
            $result['filename'] = null;
        }
        return $result;
    }

    if ($error !== UPLOAD_ERR_OK) {
        $result['error'] = bookCoverUploadError($error);
        return $result;
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        $result['error'] = 'The cover image could not be uploaded. Please try again.';
        return $result;
    }

    $size = (int)@filesize($tmp);
    if ($size < 1 || $size > BOOK_COVER_MAX_BYTES) {
        $result['error'] = 'Cover image must be 2 MB or smaller.';
        return $result;
    }

    if (!class_exists('finfo')) {
        $result['error'] = 'The server cannot verify cover images right now.';
        return $result;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        $result['error'] = 'Cover must be a JPG, PNG, or WebP image.';
        return $result;
    }

    $info = @getimagesize($tmp);
    if (!$info || ($info['mime'] ?? '') !== $mime) {
        $result['error'] = 'Cover must be a valid JPG, PNG, or WebP image.';
        return $result;
    }

    $dir = bookCoverUploadDir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        $result['error'] = 'The server could not save the cover image. Please try again.';
        return $result;
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    $destination = $dir . DIRECTORY_SEPARATOR . $filename;
    if (!move_uploaded_file($tmp, $destination)) {
        $result['error'] = 'The server could not save the cover image. Please try again.';
        return $result;
    }

    if ($result['filename'] !== null) {
        $result['old_to_delete'] = $result['filename'];
    }
    $result['filename'] = $filename;
    return $result;
}

function bookCoverDelete($filename) {
    $file = bookCoverFilename($filename);
    if ($file === '') {
        return;
    }
    $directory = realpath(bookCoverUploadDir());
    if ($directory === false) {
        return;
    }
    $fullPath = realpath($directory . DIRECTORY_SEPARATOR . $file);
    if ($fullPath !== false && strpos($fullPath, $directory . DIRECTORY_SEPARATOR) === 0 && is_file($fullPath)) {
        @unlink($fullPath);
    }
}
