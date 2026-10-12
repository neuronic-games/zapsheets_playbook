<?php
// tmAddTime.php — add a time entry row to the Time tab
// POST: id, date, project, client, staff_name, hours, description
error_reporting(0); ini_set('display_errors','0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId     = trim($_POST['id']          ?? '');
$date        = trim($_POST['date']        ?? '');
$project     = trim($_POST['project']     ?? '');
$client      = trim($_POST['client']      ?? '');
$staffName   = trim($_POST['staff_name']  ?? '');
$hours       = trim($_POST['hours']       ?? '');
$description = trim($_POST['description'] ?? '');

if (!$sheetId || !$project || !$date) {
    echo json_encode(['error' => 'Missing id, project, or date']); exit;
}

$chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
$refNum = ''; for ($i=0;$i<8;$i++) $refNum .= $chars[random_int(0,35)];
$row    = ['Ref Number'=>$refNum,'Date'=>$date,'Project'=>$project,'Client'=>$client,
           'Staff Name'=>$staffName,'Hours'=>$hours,'Description'=>$description];
$enc    = base64_encode(json_encode($row, JSON_UNESCAPED_UNICODE));
$py     = $_ENV['PYTHON'] ?? 'python3';
$cmd    = escapeshellarg($py) . ' ' . escapeshellarg(__DIR__ . '/gadd.py') . ' '
        . escapeshellarg($sheetId . '|Time|' . $enc) . ' 2>&1';
$out    = trim((string) shell_exec($cmd));
$result = $out ? json_decode($out, true) : null;
if (!$result) { echo json_encode(['error' => $out ?: 'No response']); exit; }
if (!empty($result['ok'])) { refreshJson($py, $sheetId, 'Time'); $result['ref_number'] = $refNum; }
echo json_encode($result);
?>
