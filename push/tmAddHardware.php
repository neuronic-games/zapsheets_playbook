<?php
// tmAddHardware.php — add a hardware cost row to the Hardware tab
// POST: id, date, project, client, description, amount, notes
error_reporting(0); ini_set('display_errors','0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId     = trim($_POST['id']          ?? '');
$date        = trim($_POST['date']        ?? '');
$project     = trim($_POST['project']     ?? '');
$client      = trim($_POST['client']      ?? '');
$description = trim($_POST['description'] ?? '');
$amount      = trim($_POST['amount']      ?? '');
$notes       = trim($_POST['notes']       ?? '');

if (!$sheetId || !$project || !$description) {
    echo json_encode(['error' => 'Missing id, project, or description']); exit;
}

$rowId  = substr(md5(uniqid($sheetId . $project . $description, true)), 0, 16);
$row    = ['Row ID'=>$rowId,'Date'=>$date,'Project'=>$project,'Client'=>$client,
           'Description'=>$description,'Amount'=>$amount,'Notes'=>$notes];
$enc    = base64_encode(json_encode($row, JSON_UNESCAPED_UNICODE));
$py     = $_ENV['PYTHON'] ?? 'python3';
$cmd    = escapeshellarg($py) . ' ' . escapeshellarg(__DIR__ . '/gadd.py') . ' '
        . escapeshellarg($sheetId . '|Hardware|' . $enc) . ' 2>&1';
$out    = trim((string) shell_exec($cmd));
$result = $out ? json_decode($out, true) : null;
if (!$result) { echo json_encode(['error' => $out ?: 'No response']); exit; }
if (!empty($result['ok'])) { refreshJson($py, $sheetId, 'Hardware'); $result['row_id'] = $rowId; }
echo json_encode($result);
?>
