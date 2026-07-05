<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId   = trim($_POST['id']        ?? '');
$game      = trim($_POST['game']      ?? '');
$publisher = trim($_POST['publisher'] ?? '');
$contact   = trim($_POST['contact']   ?? '');
$date      = trim($_POST['date']      ?? '');
$event     = trim($_POST['event']     ?? '');
$status    = trim($_POST['status']    ?? 'Pitched');
$notes     = trim($_POST['notes']     ?? '');

if (!$sheetId) {
    echo json_encode(['error' => 'Missing sheet ID']);
    exit;
}

$row = [
    'Game'      => $game,
    'Publisher' => $publisher,
    'Contact'   => $contact,
    'Date'      => $date,
    'Event'     => $event,
    'Status'    => $status,
    'Notes'     => $notes,
];

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$encoded    = base64_encode(json_encode($row, JSON_UNESCAPED_UNICODE));
$arg        = $sheetId . '|' . $encoded;

$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/gadd.py') . ' '
     . escapeshellarg($arg) . ' 2>&1';

$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from Python script']);
    exit;
}

$result = json_decode($output, true);
if ($result === null) {
    echo json_encode(['error' => $output]);
    exit;
}

echo json_encode($result);
?>
