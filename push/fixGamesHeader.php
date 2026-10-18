<?php
/**
 * fixGamesHeader.php — restore the Games tab A1 header to "Name", then
 * re-cache games.json.  Visit once to repair; delete when done.
 *
 * GET param: id — sheet ID
 */
header('Content-Type: application/json');
require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId = trim($_GET['id'] ?? '');
if (!$sheetId || !preg_match('/^[A-Za-z0-9_-]+$/', $sheetId)) {
    echo json_encode(['error' => 'missing or invalid id']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';

// Step 1: fix A1 in the sheet
$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/gfixgamesheader.py') . ' '
     . escapeshellarg($sheetId) . ' 2>&1';
$out = trim((string) shell_exec($cmd));
$fix = json_decode($out, true);
if (!$fix || !empty($fix['error'])) {
    echo json_encode(['error' => 'header fix failed', 'detail' => $fix['error'] ?? $out]);
    exit;
}

// Step 2: refresh games.json so the cache is clean
$err = refreshJson($pythonPath, $sheetId, 'games');
if ($err !== null) {
    echo json_encode(['fix' => $fix, 'refresh_error' => $err]);
    exit;
}

echo json_encode(['ok' => true, 'header_fix' => $fix, 'games_json' => 'refreshed']);
