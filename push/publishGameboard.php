<?php
/**
 * publishGameboard.php — create (or refresh) a public share token for a GameBoard catalog.
 *
 * POST params:
 *   id    — Google Sheet ID
 *   title — (optional) publisher/company name for display
 *
 * Token is deterministic: substr(md5($sheetId), 0, 24)
 * Re-publishing the same sheet always gives the same URL.
 *
 * Saved to: shares/gameboard/{token}.json
 * Returns:  {"ok": true, "token": "...", "url": "https://…/gameboard/{token}"}
 */
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require dirname(__DIR__) . '/dotEnv.php';

$sheetId = trim($_POST['id']    ?? '');
$title   = trim($_POST['title'] ?? '');

if (!$sheetId || !preg_match('/^[A-Za-z0-9_\-]+$/', $sheetId)) {
    echo json_encode(['error' => 'Missing or invalid sheet ID']);
    exit;
}

// Stable 24-char hex token derived from the sheet ID
$token = substr(md5($sheetId), 0, 24);

// Directory for gameboard share files
$sharesDir = dirname(__DIR__) . '/shares/gameboard';
if (!is_dir($sharesDir)) {
    if (!mkdir($sharesDir, 0755, true)) {
        echo json_encode(['error' => 'Could not create shares/gameboard directory']);
        exit;
    }
    file_put_contents($sharesDir . '/.htaccess', "Options -Indexes\n");
}

// Load existing share data (preserve original publish date on re-publish)
$shareFile = $sharesDir . '/' . $token . '.json';
$existing  = [];
if (file_exists($shareFile)) {
    $existing = json_decode(file_get_contents($shareFile), true) ?: [];
}

$shareData = [
    'sheet_id'     => $sheetId,
    'token'        => $token,
    'title'        => $title ?: ($existing['title'] ?? ''),
    'published_at' => $existing['published_at'] ?? date('c'),
    'updated_at'   => date('c'),
];

if (file_put_contents($shareFile, json_encode($shareData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) {
    echo json_encode(['error' => 'Could not save share file']);
    exit;
}

// Build the public URL
$bp     = rtrim($_ENV['BASE_PATH'] ?? '', '/');
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$url    = $scheme . '://' . $host . $bp . '/gameboard/' . $token;

echo json_encode(['ok' => true, 'token' => $token, 'url' => $url]);
