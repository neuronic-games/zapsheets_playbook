<?php
// getDevJson.php — serve playtest session data for a single game.
//
// Serves from the cached JSON file written by gread.py (via the dashboard
// Fetch button).  Falls back to a live gread.py call only when the cache
// file doesn't exist yet (first-ever load).  This prevents concurrent
// Google Sheets API calls from multiple collaborators hitting rate limits.
//
// POST params:
//   id   — Google Spreadsheet ID
//   game — Game name (tab name will be "[{game}] dev")

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

$tabName   = '[' . $gameName . '] dev';
$cacheFile = dirname(__DIR__) . '/sheets/' . $sheetId . '/' . strtolower($tabName) . '.json';

// ── Serve from cache when available (skip if force=1) ────────────────────────
$force = !empty($_POST['force']);
if (!$force && file_exists($cacheFile)) {
    $data = json_decode(file_get_contents($cacheFile), true);
    echo json_encode(is_array($data) ? $data : []);
    exit;
}

// ── First-time load: fetch live and populate the cache ────────────────────────
$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$arg        = $sheetId . 'sheetname' . $tabName;

$cmd    = escapeshellarg($pythonPath) . ' '
        . escapeshellarg(__DIR__ . '/gread.py') . ' '
        . escapeshellarg($arg) . ' 2>/dev/null';
$output = trim((string) shell_exec($cmd));

if ($output === '') {
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
