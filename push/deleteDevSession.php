<?php
// deleteDevSession.php — delete an existing dev session's rows from the sheet.
//
// POST params:
//   id              — Google Spreadsheet ID
//   game            — Game name  (tab = "[game] dev")
//   orig_date       — Date of the session header (lookup key)
//   orig_event      — Event/type of the session header (lookup key)
//   orig_session_num — Session number in People col (lookup key; may be empty)

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId        = trim($_POST['id']               ?? '');
$gameName       = trim($_POST['game']             ?? '');
$origDate       = trim($_POST['orig_date']        ?? '');
$origEvent      = trim($_POST['orig_event']       ?? '');
$origSessionNum = trim($_POST['orig_session_num'] ?? '');

if (!$sheetId || !$gameName) {
    echo json_encode(['error' => 'Missing id or game']);
    exit;
}

$tabName    = '[' . $gameName . '] dev';
$pythonPath = $_ENV['PYTHON'] ?? 'python3';

// Pass empty rows array — gupdatedevsession.py deletes old rows and inserts nothing.
$payload = [
    'orig_date'        => $origDate,
    'orig_event'       => $origEvent,
    'orig_session_num' => $origSessionNum,
    'rows'             => [],
];
$encoded = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
$arg     = $sheetId . '|' . $tabName . '|' . $encoded;

$cmd    = escapeshellarg($pythonPath) . ' '
        . escapeshellarg(__DIR__ . '/gupdatedevsession.py') . ' '
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

if (!empty($result['ok'])) {
    refreshJson($pythonPath, $sheetId, $tabName);
}

echo json_encode($result);
?>
