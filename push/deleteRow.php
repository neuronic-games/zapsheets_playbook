<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId   = trim($_POST['id']        ?? '');
$game      = trim($_POST['game']      ?? '');
$publisher = trim($_POST['publisher'] ?? '');
$contact   = trim($_POST['contact']   ?? '');
$date      = trim($_POST['date']      ?? '');
$event     = trim($_POST['event']     ?? '');

if (!$sheetId) {
    echo json_encode(['error' => 'Missing sheet ID']);
    exit;
}

$data = [
    'game'      => $game,
    'publisher' => $publisher,
    'contact'   => $contact,
    'date'      => $date,
    'event'     => $event,
];

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$encoded    = base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE));
$arg        = $sheetId . '|' . $encoded;

$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/gdelete_row.py') . ' '
     . escapeshellarg($arg) . ' 2>&1';

$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from Python script']);
    exit;
}

$result = json_decode($output, true);
if ($result !== null && !empty($result['ok'])) {
    refreshJson($pythonPath, $sheetId, 'pitches');
}
echo $result !== null ? json_encode($result) : json_encode(['error' => $output]);
?>
