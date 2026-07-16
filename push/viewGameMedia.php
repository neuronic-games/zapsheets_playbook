<?php
/**
 * push/viewGameMedia.php — runs cachemedia.py for a sheet and returns
 * the log lines as JSON so the dashboard publish dialog can display them.
 *
 * POST params:
 *   id  — Google Sheet ID
 *
 * Returns: {"ok": true, "lines": ["OK  ...", "CACHED  ...", "media cache — ..."]}
 *       or {"error": "..."} on failure
 */
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId  = trim($_POST['id']   ?? '');
$gameName = trim($_POST['game'] ?? '');

if (!$sheetId) {
    echo json_encode(['error' => 'Missing sheet ID']);
    exit;
}

$pythonPath  = $_ENV['PYTHON'] ?? 'python3';
$cacheScript = __DIR__ . '/cachemedia.py';

if (!file_exists($cacheScript)) {
    echo json_encode(['error' => 'cachemedia.py not found']);
    exit;
}

$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg($cacheScript) . ' '
     . escapeshellarg($sheetId);

if ($gameName !== '') {
    $cmd .= ' ' . escapeshellarg($gameName);
}

$cmd .= ' 2>/dev/null';

$output = trim((string) shell_exec($cmd));
$lines  = $output !== ''
    ? array_values(array_filter(array_map('trim', explode("\n", $output))))
    : [];

echo json_encode(['ok' => true, 'lines' => $lines]);
?>
