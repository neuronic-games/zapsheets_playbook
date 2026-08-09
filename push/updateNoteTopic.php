<?php
/**
 * updateNoteTopic.php
 * Updates the display name (cell B1) of a notes tab and refreshes the local cache.
 *
 * POST params:
 *   id    — sheet ID
 *   key   — internal topic key (original name from noteboard-index.json)
 *   topic — new display name to save in B1 and the cache file
 *
 * Returns JSON: {"ok": true} or {"error": "…"}
 */

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require_once __DIR__ . '/../dotEnv.php';

$id    = trim($_POST['id']    ?? '');
$key   = trim($_POST['key']   ?? '');
$topic = trim($_POST['topic'] ?? '');

if (!$id || !$key || !$topic) {
    echo json_encode(['error' => 'Missing id, key, or topic']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/gupdatenotetopic.py') . ' '
     . escapeshellarg($id) . ' '
     . escapeshellarg($key) . ' '
     . escapeshellarg($topic) . ' 2>&1';

$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from script']);
    exit;
}

$result = json_decode($output, true);
echo json_encode($result ?? ['error' => $output]);
?>
