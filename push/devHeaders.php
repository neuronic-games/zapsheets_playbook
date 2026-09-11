<?php
// devHeaders.php — diagnostic: return actual column headers of a [game] dev tab.
// Usage: /push/devHeaders.php?id={sheetId}&game={gameName}
// Returns the first row's keys from gread.py output (= actual sheet headers).

error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId = trim($_GET['id']   ?? '');
$game    = trim($_GET['game'] ?? '');
if (!$sheetId || !$game) {
    echo json_encode(['error' => 'Pass ?id=SHEET_ID&game=GAME_NAME']);
    exit;
}

$tabName    = '[' . $game . '] dev';
$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$script     = __DIR__ . '/gread.py';
$arg        = $sheetId . 'sheetname' . $tabName;
$cmd        = escapeshellarg($pythonPath) . ' '
            . escapeshellarg($script) . ' '
            . escapeshellarg($arg) . ' 2>&1';
$out = trim((string) shell_exec($cmd));

if ($out === '') {
    echo json_encode(['error' => 'No output from gread.py']);
    exit;
}

$records = json_decode($out, true);
if ($records === null) {
    // gread.py printed an error or traceback — show raw output
    echo json_encode(['error' => $out]);
    exit;
}

if (empty($records)) {
    echo json_encode(['note' => 'Tab is empty', 'tab' => $tabName]);
    exit;
}

$headers   = array_keys($records[0]);
$firstRow  = $records[0];

echo json_encode([
    'tab'       => $tabName,
    'headers'   => $headers,
    'first_row' => $firstRow,
    'row_count' => count($records),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
