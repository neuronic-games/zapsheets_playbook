<?php
// addTopic.php — add a new NoteBoard topic.
//
// POST params:
//   id   — Google Spreadsheet ID
//   name — Topic name

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId = trim($_POST['id']   ?? '');
$name    = trim($_POST['name'] ?? '');

if (!$sheetId || !$name) {
    echo json_encode(['error' => 'Missing sheet ID or topic name']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';

$payload = json_encode(['name' => $name], JSON_UNESCAPED_UNICODE);
$encoded = base64_encode($payload);
$arg     = $sheetId . '|' . $encoded;

$cmd    = escapeshellarg($pythonPath) . ' '
        . escapeshellarg(__DIR__ . '/gaddtopic.py') . ' '
        . escapeshellarg($arg) . ' 2>&1';
$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from script']);
    exit;
}

$result = json_decode($output, true);
echo json_encode($result ?? ['error' => $output]);
?>
