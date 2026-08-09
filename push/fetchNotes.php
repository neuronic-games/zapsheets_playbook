<?php
/**
 * fetchNotes.php
 * Delegates to gfetchnotes.py, which reads all "[{game}] notes" tabs
 * and refreshes local cache files (notes-{safe}-en.json).
 *
 * POST params:
 *   id — sheet ID
 *
 * Returns JSON:
 *   { "ok": true, "logs": [{"msg":"…","type":"ok|info|error"}] }
 *   { "error": "…", "logs": […] }
 */

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require_once __DIR__ . '/../dotEnv.php';

$raw  = trim($_POST['id']   ?? '');
$game = trim($_POST['game'] ?? '');
if (!$raw) {
    echo json_encode(['error' => 'Missing id', 'logs' => []]);
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
     . escapeshellarg(__DIR__ . '/gfetchnotes.py') . ' '
     . escapeshellarg($sheetId)
     . ($game !== '' ? ' ' . escapeshellarg($game) : '')
     . ' 2>&1';

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
