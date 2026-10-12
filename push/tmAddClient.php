<?php
// tmAddClient.php — add a client row to the Clients tab
// POST: id, client, contact_email, phone, notes
error_reporting(0); ini_set('display_errors','0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId      = trim($_POST['id']            ?? '');
$client       = trim($_POST['client']        ?? '');
$contactEmail = trim($_POST['contact_email'] ?? '');
$phone        = trim($_POST['phone']         ?? '');
$notes        = trim($_POST['notes']         ?? '');

if (!$sheetId || !$client) {
    echo json_encode(['error' => 'Missing id or client']); exit;
}

$chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
$refNum = ''; for ($i=0;$i<8;$i++) $refNum .= $chars[random_int(0,35)];
$row    = ['Ref Number'=>$refNum,'Client'=>$client,'Contact Email'=>$contactEmail,'Phone'=>$phone,'Notes'=>$notes];
$enc    = base64_encode(json_encode($row, JSON_UNESCAPED_UNICODE));
$py     = $_ENV['PYTHON'] ?? 'python3';
$cmd    = escapeshellarg($py) . ' ' . escapeshellarg(__DIR__ . '/gadd.py') . ' '
        . escapeshellarg($sheetId . '|Clients|' . $enc) . ' 2>&1';
$out    = trim((string) shell_exec($cmd));
$result = $out ? json_decode($out, true) : null;
if (!$result) { echo json_encode(['error' => $out ?: 'No response']); exit; }
if (!empty($result['ok'])) { refreshJson($py, $sheetId, 'Clients'); $result['ref_number'] = $refNum; }
echo json_encode($result);
?>
