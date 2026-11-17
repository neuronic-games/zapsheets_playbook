<?php
// updateDevSession.php — replace an existing dev session's rows in the sheet.
//
// POST params:
//   id         — Google Spreadsheet ID
//   game       — Game name  (tab = "[game] dev")
//   orig_date  — Original date of the session header (lookup key)
//   orig_event — Original event/testnum of the session header (lookup key)
//   date       — New date
//   event      — New testnum / event label
//   location   — New location (Observation column of the header row)
//   testers    — JSON array of tester name strings
//   obs_pairs  — JSON array of {obs, sol, todo} objects (todo "TODO:<id>" → note on the Solution cell)

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
$date           = trim($_POST['date']             ?? '');
$event          = trim($_POST['event']            ?? '');
$sessionNum     = trim($_POST['session_num']      ?? '');
$location       = trim($_POST['location']         ?? '');
$length         = trim($_POST['length']           ?? '');  // existing session length (preserved on edit)
$testersRaw  = $_POST['testers']    ?? '[]';
$obsPairsRaw = $_POST['obs_pairs']  ?? '[]';
$submitter   = trim($_POST['submitter'] ?? '');

if (!$sheetId || !$gameName) {
    echo json_encode(['error' => 'Missing id or game']);
    exit;
}

$testers  = json_decode($testersRaw,  true) ?: [];
$obsPairs = json_decode($obsPairsRaw, true) ?: [];

// Build tester-name → email map from people.json
$peopleFile = dirname(__DIR__) . '/sheets/' . $sheetId . '/people.json';
$emailMap   = [];
if (file_exists($peopleFile)) {
    $people = json_decode(file_get_contents($peopleFile), true) ?: [];
    foreach ($people as $p) {
        $n = trim($p['Name'] ?? '');
        if ($n) $emailMap[strtolower($n)] = trim($p['Email'] ?? $p['email'] ?? '');
    }
}

// Build replacement rows: Date | Event | People | Observation | Solution
$rows = [];

// Header row: date + event type + session number in People + location in Observations + length in Solutions
$rows[] = [$date, $event, $sessionNum, $location, $length];

// Tester rows: blank Date/Event + "Name email" in People + blank Observation/Solution
foreach ($testers as $t) {
    $t = trim((string)$t);
    if ($t === '') continue;
    $email    = $emailMap[strtolower($t)] ?? '';
    $combined = $t . ($email !== '' ? ' ' . $email : '');
    $rows[] = ['', '', $combined, '', ''];
}

// Submitter attribution row
if ($submitter !== '') {
    $rows[] = ['', '', '[sub:' . $submitter . ']', '', ''];
}

// Obs rows: blank Date/Event/People + Observation + Solution
$todoRows = [];
foreach ($obsPairs as $pair) {
    $obs = trim($pair['obs'] ?? '');
    $sol = trim($pair['sol'] ?? '');
    if ($obs !== '' || $sol !== '') {
        $todo = strtoupper(trim((string) ($pair['todo'] ?? '')));   // "TODO:<id>" note, or ''
        if ($todo !== '' && !preg_match('/^TODO(:[A-Z0-9]{1,20})?$/', $todo)) $todo = 'TODO';
        if ($todo !== '') $todoRows[] = [count($rows), $todo];
        $rows[] = ['', '', '', $obs, $sol];
    }
}

$tabName    = '[' . $gameName . '] dev';
$pythonPath = $_ENV['PYTHON'] ?? 'python3';

$payload = [
    'orig_date'        => $origDate,
    'orig_event'       => $origEvent,
    'orig_session_num' => $origSessionNum,
    'rows'             => $rows,
    'note_rows'        => $todoRows,
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
