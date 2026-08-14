<?php
/**
 * Create (or refresh) a view-only pitch token for a specific game.
 * Stores pitch-views/{token}.json → { sheet_id, game }
 * Returns the public view URL — no sheet ID exposed.
 */
error_reporting(0);
header('Content-Type: application/json');

$_bpFile = __DIR__ . '/../dotEnv.php';
if (file_exists($_bpFile)) { require_once $_bpFile; }

$sheetId  = trim($_POST['id']   ?? '');
$gameName = trim($_POST['game'] ?? '');

if (!$sheetId || !$gameName) {
    echo json_encode(['error' => 'Missing data']);
    exit;
}

// Deterministic, stable token — same game always gets the same URL
$token = substr(md5($sheetId . '|' . $gameName), 0, 24);

// Store mapping: token → sheet_id + game
$viewsDir = realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR . 'pitch-views';
if (!is_dir($viewsDir)) {
    mkdir($viewsDir, 0755, true);
    // Block direct directory listing and raw JSON access
    file_put_contents($viewsDir . DIRECTORY_SEPARATOR . '.htaccess',
        "Options -Indexes\nDeny from all\n");
}

file_put_contents(
    $viewsDir . DIRECTORY_SEPARATOR . $token . '.json',
    json_encode(['sheet_id' => $sheetId, 'game' => $gameName])
);

// Build the public view URL — appRoot derived from request URI, no sheet ID
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'];
$appRoot = rtrim(dirname(dirname($_SERVER['REQUEST_URI'])), '/');
$viewUrl = $scheme . '://' . $host . $appRoot . '/pitchboard/share/' . $token;

echo json_encode(['ok' => true, 'viewUrl' => $viewUrl]);
?>
