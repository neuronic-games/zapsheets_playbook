<?php
// tmUpdateTime.php — update a time entry row by Row ID
// POST: id, row_id, date, project, client, staff_name, hours, description
error_reporting(0); ini_set('display_errors','0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId     = trim($_POST['id']          ?? '');
$rowId       = trim($_POST['row_id']      ?? '');
$date        = trim($_POST['date']        ?? '');
$project     = trim($_POST['project']     ?? '');
$client      = trim($_POST['client']      ?? '');
$staffName   = trim($_POST['staff_name']  ?? '');
$hours       = trim($_POST['hours']       ?? '');
$description = trim($_POST['description'] ?? '');

if (!$sheetId || !$rowId) {
    echo json_encode(['error' => 'Missing id or row_id']); exit;
}

$data   = ['row_id'=>$rowId,'Date'=>$date,'Project'=>$project,'Client'=>$client,
           'Staff Name'=>$staffName,'Hours'=>$hours,'Description'=>$description];
$enc    = base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE));
$py     = $_ENV['PYTHON'] ?? 'python3';
$cmd    = escapeshellarg($py) . ' ' . escapeshellarg(__DIR__ . '/gtmUpdate.py') . ' '
        . escapeshellarg($sheetId . '|Time|' . $enc) . ' 2>&1';
$out    = trim((string) shell_exec($cmd));
$result = $out ? json_decode($out, true) : null;
if (!$result) { echo json_encode(['error' => $out ?: 'No response']); exit; }
if (!empty($result['ok'])) refreshJson($py, $sheetId, 'Time');
echo json_encode($result);
?>
