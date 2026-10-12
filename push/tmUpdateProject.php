<?php
// tmUpdateProject.php — update a project row by Ref Number
// POST: id, row_id, client, project, status, estimate_hours, start_date, end_date, notes
error_reporting(0); ini_set('display_errors','0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId       = trim($_POST['id']             ?? '');
$rowId         = trim($_POST['row_id']         ?? '');
$client        = trim($_POST['client']         ?? '');
$project       = trim($_POST['project']        ?? '');
$status        = trim($_POST['status']         ?? '');
$estimateHours = trim($_POST['estimate_hours'] ?? '');
$startDate     = trim($_POST['start_date']     ?? '');
$endDate       = trim($_POST['end_date']       ?? '');
$notes         = trim($_POST['notes']          ?? '');

if (!$sheetId || !$rowId) {
    echo json_encode(['error' => 'Missing id or row_id']); exit;
}

$data   = ['row_id'=>$rowId,'Client'=>$client,'Project'=>$project,'Status'=>$status,
           'Estimate Hours'=>$estimateHours,'Start Date'=>$startDate,'End Date'=>$endDate,'Notes'=>$notes];
$enc    = base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE));
$py     = $_ENV['PYTHON'] ?? 'python3';
$cmd    = escapeshellarg($py) . ' ' . escapeshellarg(__DIR__ . '/gtmUpdate.py') . ' '
        . escapeshellarg($sheetId . '|Projects|' . $enc) . ' 2>&1';
$out    = trim((string) shell_exec($cmd));
$result = $out ? json_decode($out, true) : null;
if (!$result) { echo json_encode(['error' => $out ?: 'No response']); exit; }
if (!empty($result['ok'])) refreshJson($py, $sheetId, 'Projects');
echo json_encode($result);
?>
