<?php
// updateGameTab.php — write edited game-tab rows back to Google Sheets,
// then refresh the local game JSON cache.
//
// POST params:
//   id   — Google Spreadsheet ID
//   game — Game name
//   rows — JSON array of {name, value, extra} objects

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId  = trim($_POST['id']   ?? '');
$gameName = trim($_POST['game'] ?? '');
$rowsJson = trim($_POST['rows'] ?? '[]');

if (!$sheetId || !$gameName) {
    echo json_encode(['error' => 'Missing sheet ID or game name']);
    exit;
}

$rows = json_decode($rowsJson, true);
if (!is_array($rows)) {
    echo json_encode(['error' => 'Invalid rows JSON']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';

$payload = [
    'game' => $gameName,
    'rows' => $rows,
];
$encoded = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
$arg     = $sheetId . '|' . $encoded;

$cmd    = escapeshellarg($pythonPath) . ' '
        . escapeshellarg(__DIR__ . '/gupdategametab.py') . ' '
        . escapeshellarg($arg) . ' 2>&1';
$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from update script']);
    exit;
}

$result = json_decode($output, true);
if ($result === null) {
    echo json_encode(['error' => $output]);
    exit;
}

// On success, re-export the game JSON so the view page reflects the new data
if (!empty($result['ok'])) {
    $safeName = str_replace(['/', '\\'], '-', $gameName);
    $pyCmd    = escapeshellarg($pythonPath) . ' '
              . escapeshellarg(__DIR__ . '/gpublishgame.py') . ' '
              . escapeshellarg($sheetId . '|' . $gameName) . ' 2>&1';
    shell_exec($pyCmd);  // best-effort; don't block on errors
}

echo json_encode($result);
?>
