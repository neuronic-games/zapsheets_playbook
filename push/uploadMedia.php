<?php
/**
 * uploadMedia.php — upload a single media file to sheets/{sheetId}/cache/
 *
 * POST params:
 *   id    — sheet ID (required)
 *   file  — the uploaded file ($_FILES['file'])
 *
 * Returns JSON: { ok: true, url: "https://…/sheets/{id}/cache/{filename}" }
 *           or  { error: "reason" }
 */
error_reporting(0);
header('Content-Type: application/json');

$_bpFile = __DIR__ . '/../dotEnv.php';
if (file_exists($_bpFile)) { require_once $_bpFile; }

$sheetId = trim($_POST['id'] ?? '');
if (!$sheetId) { echo json_encode(['error' => 'Missing sheet id']); exit; }

if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['file']['error'] ?? -1;
    echo json_encode(['error' => 'Upload error ' . $code]);
    exit;
}

// Allowed MIME types
$allowedMime = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'image/svg+xml', 'image/avif',
    'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime',
];
$mime = mime_content_type($_FILES['file']['tmp_name']);
if (!in_array($mime, $allowedMime, true)) {
    echo json_encode(['error' => 'File type not allowed: ' . $mime]);
    exit;
}

// Sanitise filename — keep only safe chars, preserve extension
$origName = basename($_FILES['file']['name']);
$ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$base     = pathinfo($origName, PATHINFO_FILENAME);
$base     = preg_replace('/[^A-Za-z0-9._-]/', '_', $base);
$base     = trim($base, '._-') ?: 'upload';
$filename = $base . '.' . $ext;

// If a file with this name already exists, add a short hash to avoid collisions
$cacheDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR
          . 'sheets' . DIRECTORY_SEPARATOR . $sheetId . DIRECTORY_SEPARATOR . 'cache';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

$dest = $cacheDir . DIRECTORY_SEPARATOR . $filename;
if (file_exists($dest)) {
    // Only skip if content is identical, otherwise deduplicate
    if (md5_file($_FILES['file']['tmp_name']) !== md5_file($dest)) {
        $hash     = substr(md5_file($_FILES['file']['tmp_name']), 0, 6);
        $filename = $base . '_' . $hash . '.' . $ext;
        $dest     = $cacheDir . DIRECTORY_SEPARATOR . $filename;
    }
}

if (!move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
    echo json_encode(['error' => 'Failed to save file']);
    exit;
}

// Build public URL
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'];
$appRoot = rtrim(dirname(dirname($_SERVER['REQUEST_URI'])), '/');
$url     = $scheme . '://' . $host . $appRoot . '/sheets/' . $sheetId . '/cache/' . $filename;

echo json_encode(['ok' => true, 'url' => $url]);
