<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId     = trim($_POST['id']           ?? '');
$game        = trim($_POST['game']         ?? '');
$client      = trim($_POST['client']       ?? '');
$targetStart = trim($_POST['target_start'] ?? '');
$targetEnd   = trim($_POST['target_end']   ?? '');
$quote       = trim($_POST['quote']        ?? '');
$payment     = trim($_POST['payment']      ?? '');
$notes       = trim($_POST['notes']        ?? '');

if (!$sheetId || !$client) {
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// Determine next contract ID from local cache
$cacheDir  = __DIR__ . '/../sheets/' . $sheetId;
$cacheFile = $cacheDir . '/contract.json';
$nextId    = 1;

if (file_exists($cacheFile)) {
    $rows = json_decode(file_get_contents($cacheFile), true);
    if (is_array($rows) && count($rows)) {
        $maxId = 0;
        foreach ($rows as $row) {
            $id = intval($row['ID'] ?? $row['id'] ?? 0);
            if ($id > $maxId) $maxId = $id;
        }
        $nextId = $maxId + 1;
    }
}

// Format today's date
$today = date('Y-m-d');

$row = [
    'ID'               => (string) $nextId,
    'Date'             => $today,
    'Game'             => $game,
    'Client'           => $client,
    'Target Start Date'=> $targetStart,
    'Target End Date'  => $targetEnd,
    'Start Date'       => '',
    'End Date'         => '',
    'Quote'            => $quote,
    'Payment'          => $payment,
    'Notes'            => $notes,
];

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$encoded    = base64_encode(json_encode($row, JSON_UNESCAPED_UNICODE));
$arg        = $sheetId . '|contract|' . $encoded;

$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/gadd.py') . ' '
     . escapeshellarg($arg) . ' 2>&1';

$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from Python script']);
    exit;
}

$result = json_decode($output, true);
if ($result !== null && !empty($result['ok'])) {
    refreshJson($pythonPath, $sheetId, 'contract');
}
echo $result !== null ? json_encode($result) : json_encode(['error' => $output]);
?>
