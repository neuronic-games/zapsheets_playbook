<?php
/**
 * uploadImage.php — upload an image file for a GameBoard game page.
 *
 * POST params:
 *   id   — Google Spreadsheet ID (used to namespace the folder)
 *   file — the uploaded image (multipart/form-data)
 *
 * Returns: {"ok": true, "url": "https://…/images/{id}/filename.jpg"}
 *          {"error": "reason"}
 */
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require dirname(__DIR__) . '/dotEnv.php';

$sheetId = trim($_POST['id'] ?? '');
if (!$sheetId || !preg_match('/^[A-Za-z0-9_\-]+$/', $sheetId)) {
    echo json_encode(['error' => 'Missing or invalid sheet ID']);
    exit;
}

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['file']['error'] ?? -1;
    echo json_encode(['error' => 'Upload error code ' . $code]);
    exit;
}

$file = $_FILES['file'];

// Validate MIME type by content (not just extension)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime, $allowed)) {
    echo json_encode(['error' => 'Unsupported image type: ' . $mime]);
    exit;
}

$maxBytes = 10 * 1024 * 1024; // 10 MB
if ($file['size'] > $maxBytes) {
    echo json_encode(['error' => 'File too large (max 10 MB)']);
    exit;
}

// Safe filename: uid + original extension
$extMap = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
$ext      = $extMap[$mime] ?? 'jpg';
$filename = bin2hex(random_bytes(10)) . '.' . $ext;

$dir = dirname(__DIR__) . '/images/' . $sheetId . '/';
if (!is_dir($dir)) {
    if (!mkdir($dir, 0755, true)) {
        echo json_encode(['error' => 'Could not create upload directory']);
        exit;
    }
}

if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
    echo json_encode(['error' => 'Could not save file']);
    exit;
}

// Build the public URL
$bp     = rtrim($_ENV['BASE_PATH'] ?? '', '/');
$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$url    = $scheme . '://' . $host . $bp . '/images/' . $sheetId . '/' . $filename;

echo json_encode(['ok' => true, 'url' => $url, 'filename' => $filename]);
