<?php
/**
 * enableGameNotes.php
 * Delegates to genableGameNotes.py, which registers the game in
 * noteboard-index.json and creates the notes tab in Google Sheets.
 *
 * POST params:
 *   id   — sheet ID (raw URL or bare ID)
 *   game — game name (exact)
 *
 * Returns JSON:
 *   { "ok": true, "hash": "…", "logs": [{"msg":"…","type":"ok|info|error"}] }
 *   { "error": "…", "logs": […] }
 */

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require_once __DIR__ . '/../dotEnv.php';

$raw  = trim($_POST['id']   ?? '');
$game = trim($_POST['game'] ?? '');

if (!$raw || !$game) {
    echo json_encode(['error' => 'Missing id or game', 'logs' => []]);
    exit;
}

// Accept full URL or bare sheet ID
$m = [];
if (preg_match('/\/spreadsheets\/d\/([A-Za-z0-9_\-]+)/', $raw, $m)) {
    $sheetId = $m[1];
} elseif (preg_match('/^[A-Za-z0-9_\-]{10,}$/', $raw)) {
    $sheetId = $raw;
} else {
    echo json_encode(['error' => 'Invalid sheet ID', 'logs' => []]);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/genableGameNotes.py') . ' '
     . escapeshellarg($sheetId) . ' '
     . escapeshellarg($game) . ' 2>&1';

$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from script', 'logs' => []]);
    exit;
}

$result = json_decode($output, true);
if ($result === null) {
    echo json_encode(['error' => $output, 'logs' => []]);
    exit;
}

echo json_encode($result);
?>
