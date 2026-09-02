<?php
// getDevJson.php — lazy-load playtest data for a single game.
//
// Reads the "{GameName} dev" tab from Google Sheets via gread.py and returns
// the rows as JSON.  If the tab doesn't exist yet, returns an empty array.
//
// POST params:
//   id   — Google Spreadsheet ID
//   game — Game name (tab name will be "{game} dev")

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId  = trim($_POST['id']   ?? '');
$gameName = trim($_POST['game'] ?? '');

if (!$sheetId || !$gameName) {
    echo json_encode(['error' => 'Missing id or game']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$tabName    = '[' . $gameName . '] dev';
$arg        = $sheetId . 'sheetname' . $tabName;

$cmd    = escapeshellarg($pythonPath) . ' '
        . escapeshellarg(__DIR__ . '/gread.py') . ' '
        . escapeshellarg($arg) . ' 2>/dev/null';
$output = trim((string) shell_exec($cmd));

if ($output === '') {
    // Tab doesn't exist yet — no dev notes recorded
    echo json_encode([]);
    exit;
}

$data = json_decode($output, true);

// Apply conditional formatting once per tab (marker file prevents repeat calls)
$fmtMarker = dirname(__DIR__) . '/sheets/' . $sheetId . '/' . strtolower(str_replace(' ', '_', $tabName)) . '.fmt';
if (!file_exists($fmtMarker)) {
    $fmtArg = $sheetId . '|' . $gameName;
    $fmtCmd = escapeshellarg($pythonPath) . ' '
            . escapeshellarg(__DIR__ . '/gapplydevformat.py') . ' '
            . escapeshellarg($fmtArg) . ' 2>/dev/null';
    shell_exec($fmtCmd);
    file_put_contents($fmtMarker, date('c'));
}

echo json_encode($data !== null ? $data : []);
?>
