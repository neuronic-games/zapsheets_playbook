<?php
// setupFitboard.php — one-call setup for a new FitBoard spreadsheet.
//
// Steps:
//   1. Initialise the Week tab with headers (ginitfitboard.py)
//   2. Create sheets/{id}/ directory
//   3. Populate week.json from the sheet (gread_week.py)
//
// POST param: id — Google Spreadsheet ID

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId = trim($_POST['id'] ?? $_GET['id'] ?? '');
if (!$sheetId) {
    echo json_encode(['error' => 'Missing sheet ID']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$result     = ['ok' => true];

// ── Step 1: Initialise Week tab ───────────────────────────────────────────────
$cmd    = escapeshellarg($pythonPath) . ' '
        . escapeshellarg(__DIR__ . '/ginitfitboard.py') . ' '
        . escapeshellarg($sheetId) . ' 2>&1';
$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from setup script']);
    exit;
}

$pyResult = json_decode($output, true);
if ($pyResult === null) {
    echo json_encode(['error' => $output]);
    exit;
}

$result['tabs']  = $pyResult['tabs']  ?? [];
$result['title'] = $pyResult['title'] ?? '';
if (empty($pyResult['ok'])) {
    $result['ok'] = false;
}

// ── Step 2: Create sheets/{id}/ directory ────────────────────────────────────
// Always run even if init had errors so local files are ready for future use.
$sheetDir = realpath(__DIR__ . '/..') . '/sheets/' . $sheetId;
if (!is_dir($sheetDir)) {
    mkdir($sheetDir, 0777, true);
}

// ── Step 3: Populate week.json ────────────────────────────────────────────────
$readCmd = escapeshellarg($pythonPath) . ' '
         . escapeshellarg(__DIR__ . '/gread_week.py') . ' '
         . escapeshellarg($sheetId) . ' 2>/dev/null';
$readOut = trim((string) shell_exec($readCmd));

if ($readOut !== '') {
    $readResult = json_decode($readOut, true);
    if (!empty($readResult['data'])) {
        file_put_contents($sheetDir . '/week.json',
            json_encode($readResult['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $result['week_json'] = 'created';
    } else {
        // Sheet has headers but no data yet — write an empty array
        file_put_contents($sheetDir . '/week.json', '[]');
        $result['week_json'] = 'empty';
    }
} else {
    // gread_week failed silently — still write an empty file
    file_put_contents($sheetDir . '/week.json', '[]');
    $result['week_json'] = 'empty';
}

echo json_encode($result);
?>
