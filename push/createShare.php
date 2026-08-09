<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId  = trim($_POST['id']   ?? '');
$jsonData = trim($_POST['data'] ?? '');

if (!$sheetId || !$jsonData) {
    echo json_encode(['error' => 'Missing data']);
    exit;
}

// Validate it's valid JSON
if (json_decode($jsonData) === null) {
    echo json_encode(['error' => 'Invalid JSON data']);
    exit;
}

// Create shares directory at the app root (sibling of push/)
$sharesDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'shares';
if (!is_dir($sharesDir)) {
    if (!mkdir($sharesDir, 0755, true)) {
        echo json_encode(['error' => 'Could not create shares directory']);
        exit;
    }
    // Prevent directory listing
    file_put_contents($sharesDir . DIRECTORY_SEPARATOR . '.htaccess', "Options -Indexes\n");
}

// Derive a stable filename from sheet ID + game name so re-packaging overwrites the same file.
$decoded  = json_decode($jsonData, true);
$gameName = trim($decoded['game']['Name'] ?? '');
$filename = substr(md5($sheetId . '|' . $gameName), 0, 24) . '.json';

$filepath = $sharesDir . DIRECTORY_SEPARATOR . $filename;

if (file_put_contents($filepath, $jsonData) === false) {
    echo json_encode(['error' => 'Could not save share file']);
    exit;
}

// Build the full URL from the current request
// REQUEST_URI is e.g. /sheets/{id}/push/createShare.php
// Two dirnames bring us up to the app root /sheets/{id}/
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$appRoot  = dirname(dirname($_SERVER['REQUEST_URI']));
$shareUrl = $scheme . '://' . $host . rtrim($appRoot, '/') . '/shares/' . $filename;

echo json_encode(['ok' => true, 'url' => $shareUrl, 'filename' => $filename]);
?>
