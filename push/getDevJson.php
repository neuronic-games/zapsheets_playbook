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
//
// Returns:
//   JSON array of row objects on success
//   { "__error": true, "message": "..." } when the tab is missing or fetch fails
//   (never a plain {"error":"..."} object, which callers can't distinguish from data)

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId  = trim($_POST['id']   ?? '');
$gameName = trim($_POST['game'] ?? '');

if (!$sheetId || !$gameName) {
    echo json_encode(['__error' => true, 'message' => 'Missing id or game']);
    exit;
}

$tabName   = '[' . $gameName . '] dev';
$cacheDir  = dirname(__DIR__) . '/sheets/' . $sheetId;
$cacheFile = $cacheDir . '/' . strtolower($tabName) . '.json';

// ── Serve from cache when available (skip if force=1) ────────────────────────
$force = !empty($_POST['force']);
if (!$force && file_exists($cacheFile)) {
    $data = json_decode(file_get_contents($cacheFile), true);
    echo json_encode(is_array($data) ? $data : []);
    exit;
}

// ── Live fetch: call gread.py directly ───────────────────────────────────────
$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$arg        = $sheetId . 'sheetname' . $tabName;

$cmd    = escapeshellarg($pythonPath) . ' '
        . escapeshellarg(__DIR__ . '/gread.py') . ' '
        . escapeshellarg($arg) . ' 2>/dev/null';
$output = trim((string) shell_exec($cmd));

if ($output === '') {
    // gread.py produced no output — likely a Python crash or env issue
    echo json_encode(['__error' => true, 'message' => 'Session data could not be fetched. Try the reload button or contact the board owner.']);
    exit;
}

$data = json_decode($output, true);

// gread.py emits {"error":"..."} when the tab doesn't exist or the API errors
if (!is_array($data)) {
    $msg = (is_array($data) ? '' : '') ?: 'Sessions tab not found or could not be read.';
    if (isset($data['error'])) $msg = $data['error'];
    echo json_encode(['__error' => true, 'message' => $msg]);
    exit;
}

// ── Write cache explicitly (belt-and-suspenders — gread.py also writes it) ───
if (!is_dir($cacheDir)) { mkdir($cacheDir, 0777, true); }
file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));

// Apply conditional formatting once per tab (marker file prevents repeat calls)
$fmtMarker = $cacheDir . '/' . strtolower(str_replace(' ', '_', $tabName)) . '.fmt';
if (!file_exists($fmtMarker)) {
    $fmtArg = $sheetId . '|' . $gameName;
    $fmtCmd = escapeshellarg($pythonPath) . ' '
            . escapeshellarg(__DIR__ . '/gapplydevformat.py') . ' '
            . escapeshellarg($fmtArg) . ' 2>/dev/null';
    shell_exec($fmtCmd);
    file_put_contents($fmtMarker, date('c'));
}

echo json_encode($data);
?>
