<?php
// tmDeleteTime.php — delete a time entry row by Row ID
// POST: id, row_id
error_reporting(0); ini_set('display_errors','0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId = trim($_POST['id']     ?? '');
$rowId   = trim($_POST['row_id'] ?? '');

if (!$sheetId) { echo json_encode(['error' => 'Missing id']); exit; }

$py = $_ENV['PYTHON'] ?? 'python3';

if ($rowId) {
    // Normal path: pass row_id directly as plain string
    $payload = $rowId;
} else {
    // No Ref Number: pass fingerprint JSON so Python can locate the row
    $fp = [
        'row_id'      => '',
        '_fingerprint' => [
            'Date'        => trim($_POST['date']        ?? ''),
            'Project'     => trim($_POST['project']     ?? ''),
            'Client'      => trim($_POST['client']      ?? ''),
            'Staff Name'  => trim($_POST['staff_name']  ?? ''),
            'Hours'       => trim($_POST['hours']       ?? ''),
            'Description' => trim($_POST['description'] ?? ''),
        ],
    ];
    $payload = base64_encode(json_encode($fp, JSON_UNESCAPED_UNICODE));
}

$cmd = escapeshellarg($py) . ' ' . escapeshellarg(__DIR__ . '/gtmDelete.py') . ' '
     . escapeshellarg($sheetId . '|Time|' . $payload) . ' 2>&1';
$out    = trim((string) shell_exec($cmd));
$result = $out ? json_decode($out, true) : null;
if (!$result) { echo json_encode(['error' => $out ?: 'No response']); exit; }
if (!empty($result['ok'])) refreshJson($py, $sheetId, 'Time');
echo json_encode($result);
?>
