<?php
/**
 * push/createGameTab.php — creates a new game tab in Google Sheets with default fields.
 *
 * POST params:
 *   id   — Google Sheet ID
 *   game — game / tab name
 *
 * Returns: {"ok": true, "tab": "..."} or {"error": "..."}
 */
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId  = trim($_POST['id']   ?? '');
$gameName = trim($_POST['game'] ?? '');

if (!$sheetId || !$gameName) {
    echo json_encode(['error' => 'Missing sheet ID or game name']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$script     = __DIR__ . '/gcreategametab.py';

if (!file_exists($script)) {
    echo json_encode(['error' => 'gcreategametab.py not found']);
    exit;
}

$cmd    = escapeshellarg($pythonPath) . ' '
        . escapeshellarg($script) . ' '
        . escapeshellarg($sheetId . '|' . $gameName) . ' 2>/dev/null';
$output = trim((string) shell_exec($cmd));

$result = $output ? json_decode($output, true) : null;
echo $result ? json_encode($result) : json_encode(['error' => $output ?: 'No response from script']);
?>
