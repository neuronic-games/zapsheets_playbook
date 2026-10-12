<?php
// tmUpdateHardware.php — update a hardware row by Row ID
// POST: id, row_id, date, project, client, description, amount, status, notes
error_reporting(0); ini_set('display_errors','0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId     = trim($_POST['id']          ?? '');
$rowId       = trim($_POST['row_id']      ?? '');
$date        = trim($_POST['date']        ?? '');
$project     = trim($_POST['project']     ?? '');
$client      = trim($_POST['client']      ?? '');
$description = trim($_POST['description'] ?? '');
$amount      = trim($_POST['amount']      ?? '');
$status      = trim($_POST['status']      ?? '');
$notes       = trim($_POST['notes']       ?? '');

if (!$sheetId || !$rowId) {
    echo json_encode(['error' => 'Missing id or row_id']); exit;
}

$data   = ['row_id'=>$rowId,'Date'=>$date,'Project'=>$project,'Client'=>$client,
           'Description'=>$description,'Amount'=>$amount,'Status'=>$status,'Notes'=>$notes];
$enc    = base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE));
$py     = $_ENV['PYTHON'] ?? 'python3';
$cmd    = escapeshellarg($py) . ' ' . escapeshellarg(__DIR__ . '/gtmUpdate.py') . ' '
        . escapeshellarg($sheetId . '|Hardware|' . $enc) . ' 2>&1';
$out    = trim((string) shell_exec($cmd));
$result = $out ? json_decode($out, true) : null;
if (!$result) { echo json_encode(['error' => $out ?: 'No response']); exit; }
if (!empty($result['ok'])) refreshJson($py, $sheetId, 'Hardware');
echo json_encode($result);
?>
