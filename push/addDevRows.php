<?php
// addDevRows.php — write all session rows to JSON cache immediately,
// then sync to Google Sheet in the background (fire-and-forget).
//
// POST params:
//   id    — Google Spreadsheet ID
//   game  — Game name (tab = "[game] dev")
//   rows  — JSON array of row objects {date, event, session_num, observation, solution, type}

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId  = trim($_POST['id']   ?? '');
$gameName = trim($_POST['game'] ?? '');
$rowsJson = trim($_POST['rows'] ?? '[]');

if (!$sheetId || !$gameName) {
    echo json_encode(['error' => 'Missing id or game']);
    exit;
}

$rows = json_decode($rowsJson, true);
if (!is_array($rows) || empty($rows)) {
    echo json_encode(['error' => 'No rows provided']);
    exit;
}

$tabName   = '[' . $gameName . '] dev';
$cacheFile = dirname(__DIR__) . '/sheets/' . $sheetId . '/' . strtolower($tabName) . '.json';

// ── Build cache-format row objects ──────────────────────────────────────────
$cacheRows  = [];
$sheetRows  = [];
foreach ($rows as $row) {
    $obs  = $row['observation'] ?? '';
    $sol  = $row['solution']    ?? '';
    $snum = $row['session_num'] ?? '';
    $dt   = $row['date']        ?? '';
    $ev   = $row['event']       ?? '';

    // Cache format: matches the shape returned by gread.py
    $cacheRows[] = [
        'Date'         => $dt,
        'Event'        => $ev,
        'People'       => $snum,
        'Observations' => $obs,
        'Observation'  => $obs,   // legacy column name
        'Thoughts'     => $sol,
        'Solution'     => $sol,   // legacy column name
    ];

    // Sheet format: same keys, passed to gadddevrows.py
    $sheetRows[] = [
        'Date'         => $dt,
        'Event'        => $ev,
        'People'       => $snum,
        'Observations' => $obs,
        'Observation'  => $obs,
        'Thoughts'     => $sol,
        'Solution'     => $sol,
    ];
}

// ── Append to JSON cache immediately (fast, no API call) ────────────────────
$existing = [];
if (file_exists($cacheFile)) {
    $existing = json_decode(file_get_contents($cacheFile), true) ?: [];
}
foreach ($cacheRows as $r) {
    $existing[] = $r;
}
$dir = dirname($cacheFile);
if (!is_dir($dir)) mkdir($dir, 0777, true);
if (file_put_contents($cacheFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
    echo json_encode(['error' => 'Could not write session cache']);
    exit;
}

// ── Fire background Google Sheets sync (no waiting) ─────────────────────────
$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$payload    = ['tab' => $tabName, 'rows' => $sheetRows];
$encoded    = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
$arg        = $sheetId . '|' . $encoded;

$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/gadddevrows.py') . ' '
     . escapeshellarg($arg) . ' > /dev/null 2>&1 &';
exec($cmd);

echo json_encode(['ok' => true, 'rows' => count($cacheRows)]);
?>
