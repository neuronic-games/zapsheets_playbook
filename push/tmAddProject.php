<?php
// tmAddProject.php — add a project row to the Projects tab
// POST: id, client, project, status, estimate_hours, start_date, end_date, notes
error_reporting(0); ini_set('display_errors','0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId       = trim($_POST['id']             ?? '');
$client        = trim($_POST['client']         ?? '');
$project       = trim($_POST['project']        ?? '');
$status        = trim($_POST['status']         ?? 'Active');
$estimateHours = trim($_POST['estimate_hours'] ?? '');
$startDate     = trim($_POST['start_date']     ?? '');
$endDate       = trim($_POST['end_date']       ?? '');
$notes         = trim($_POST['notes']          ?? '');

if (!$sheetId || !$project) {
    echo json_encode(['error' => 'Missing id or project']); exit;
}

$rowId  = substr(md5(uniqid($sheetId . $project, true)), 0, 16);
$row    = ['Row ID'=>$rowId,'Client'=>$client,'Project'=>$project,'Status'=>$status,
           'Estimate Hours'=>$estimateHours,'Start Date'=>$startDate,'End Date'=>$endDate,'Notes'=>$notes];
$enc    = base64_encode(json_encode($row, JSON_UNESCAPED_UNICODE));
$py     = $_ENV['PYTHON'] ?? 'python3';
$cmd    = escapeshellarg($py) . ' ' . escapeshellarg(__DIR__ . '/gadd.py') . ' '
        . escapeshellarg($sheetId . '|Projects|' . $enc) . ' 2>&1';
$out    = trim((string) shell_exec($cmd));
$result = $out ? json_decode($out, true) : null;
if (!$result) { echo json_encode(['error' => $out ?: 'No response']); exit; }
if (!empty($result['ok'])) { refreshJson($py, $sheetId, 'Projects'); $result['row_id'] = $rowId; }
echo json_encode($result);
?>
