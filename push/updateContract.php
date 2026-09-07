<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId     = trim($_POST['id']          ?? '');
$contractId  = trim($_POST['contract_id'] ?? '');
$game        = trim($_POST['game']        ?? '');
$client      = trim($_POST['client']      ?? '');
$targetStart = trim($_POST['target_start']?? '');
$targetEnd   = trim($_POST['target_end']  ?? '');
$startDate   = trim($_POST['start_date']  ?? '');
$endDate     = trim($_POST['end_date']    ?? '');
$quote       = trim($_POST['quote']       ?? '');
$payment     = trim($_POST['payment']     ?? '');
$notes       = trim($_POST['notes']       ?? '');

if (!$sheetId || !$contractId) {
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$data = [
    'contract_id'       => $contractId,
    'game'              => $game,
    'client'            => $client,
    'target_start_date' => $targetStart,
    'target_end_date'   => $targetEnd,
    'start_date'        => $startDate,
    'end_date'          => $endDate,
    'quote'             => $quote,
    'payment'           => $payment,
    'notes'             => $notes,
];

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$encoded    = base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE));
$arg        = $sheetId . '|' . $encoded;

$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/gupdatecontract.py') . ' '
     . escapeshellarg($arg) . ' 2>&1';

$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from Python script']);
    exit;
}

$result = json_decode($output, true);
if ($result !== null && !empty($result['ok'])) {
    refreshJson($pythonPath, $sheetId, 'contracts');
}
echo $result !== null ? json_encode($result) : json_encode(['error' => $output]);
?>
