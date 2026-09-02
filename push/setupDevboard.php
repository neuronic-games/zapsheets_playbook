<?php
// setupDevboard.php — initialise DevBoard for a Google Spreadsheet.
//
// POST params:
//   id  — Google Spreadsheet ID

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId = trim($_POST['id'] ?? '');
if (!$sheetId) {
    echo json_encode(['error' => 'Missing sheet ID']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$cmd        = escapeshellarg($pythonPath) . ' '
            . escapeshellarg(__DIR__ . '/ginitdevboard.py') . ' '
            . escapeshellarg($sheetId) . ' 2>&1';
$output     = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from init script']);
    exit;
}

$result = json_decode($output, true);
if ($result === null) {
    echo json_encode(['error' => $output]);
    exit;
}

// On success, also refresh games.json so the dashboard has something to show.
if (!empty($result['ok'])) {
    require_once __DIR__ . '/refreshJson.php';
    refreshJson($pythonPath, $sheetId, 'games');
}

echo json_encode($result);
?>
