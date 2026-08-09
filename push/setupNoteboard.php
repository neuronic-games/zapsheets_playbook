<?php
// setupNoteboard.php — initialise NoteBoard for a Google Spreadsheet.
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

$cmd    = escapeshellarg($pythonPath) . ' '
        . escapeshellarg(__DIR__ . '/ginitnoteboard.py') . ' '
        . escapeshellarg($sheetId) . ' 2>&1';
$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from init script']);
    exit;
}

$result = json_decode($output, true);
if ($result === null) {
    echo json_encode(['error' => $output]);
    exit;
}

echo json_encode($result);
?>
