<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId     = trim($_POST['id']           ?? '');
$game        = trim($_POST['game']         ?? '');
$publisher   = trim($_POST['publisher']    ?? '');
$origContact = trim($_POST['orig_contact'] ?? '');   // original contact for row lookup
$origDate    = trim($_POST['orig_date']    ?? '');   // original date for row lookup
$origEvent   = trim($_POST['orig_event']  ?? '');   // original event for row lookup
$newContact  = trim($_POST['contact']     ?? '');
$newDate     = trim($_POST['date']        ?? '');
$newEvent    = trim($_POST['event']       ?? '');
$newStatus   = trim($_POST['status']      ?? '');
$notes       = $_POST['notes']            ?? '';    // preserve internal whitespace

if (!$sheetId) {
    echo json_encode(['error' => 'Missing sheet ID']);
    exit;
}

$data = [
    'game'         => $game,
    'publisher'    => $publisher,
    'orig_contact' => $origContact,
    'orig_date'    => $origDate,
    'orig_event'   => $origEvent,
    'contact'      => $newContact,
    'date'         => $newDate,
    'event'        => $newEvent,
    'status'       => $newStatus,
    'notes'        => $notes,
];

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$encoded    = base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE));
$arg        = $sheetId . '|' . $encoded;

$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/gupdate.py') . ' '
     . escapeshellarg($arg) . ' 2>&1';

$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from Python script']);
    exit;
}

$result = json_decode($output, true);
echo $result !== null ? json_encode($result) : json_encode(['error' => $output]);
?>
