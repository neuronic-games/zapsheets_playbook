<?php
// tmDeleteTime.php — delete a time entry row by Row ID
// POST: id, row_id
error_reporting(0); ini_set('display_errors','0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId = trim($_POST['id']     ?? '');
$rowId   = trim($_POST['row_id'] ?? '');

if (!$sheetId || !$rowId) {
    echo json_encode(['error' => 'Missing id or row_id']); exit;
}

$py     = $_ENV['PYTHON'] ?? 'python3';
$cmd    = escapeshellarg($py) . ' ' . escapeshellarg(__DIR__ . '/gtmDelete.py') . ' '
        . escapeshellarg($sheetId . '|Time|' . $rowId) . ' 2>&1';
$out    = trim((string) shell_exec($cmd));
$result = $out ? json_decode($out, true) : null;
if (!$result) { echo json_encode(['error' => $out ?: 'No response']); exit; }
if (!empty($result['ok'])) refreshJson($py, $sheetId, 'Time');
echo json_encode($result);
?>
