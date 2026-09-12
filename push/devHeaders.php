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

// Also probe gadd.py directly to see what it returns for a header row
$testRow = [
    'Date'         => date('Y-m-d'),
    'Event'        => '__DEBUG_TEST__',
    'Observations' => 'debug-location',
    'Observation'  => 'debug-location',
    'Thoughts'     => 'Length: 00:42',
    'Solution'     => 'Length: 00:42',
    'People'       => '',
];
$encoded  = base64_encode(json_encode($testRow, JSON_UNESCAPED_UNICODE));
$gaddArg  = $sheetId . '|' . $tabName . '|' . $encoded;
$gaddCmd  = escapeshellarg($pythonPath) . ' '
          . escapeshellarg(__DIR__ . '/gadd.py') . ' '
          . escapeshellarg($gaddArg) . ' 2>&1';
$gaddOut  = trim((string) shell_exec($gaddCmd));
$gaddResult = json_decode($gaddOut, true);

// If the test row was actually written, immediately delete it by noting its row num
// (we can't delete easily, so just flag it)

echo json_encode([
    'tab'             => $tabName,
    'headers'         => $headers,
    'row_count'       => count($records),
    'last_3_rows'     => array_slice($records, -3),
    'gadd_raw_output' => $gaddOut,
    'gadd_result'     => $gaddResult,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
