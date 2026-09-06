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
//   obs_pairs  — JSON array of {obs, sol} objects

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId   = trim($_POST['id']         ?? '');
$gameName  = trim($_POST['game']       ?? '');
$origDate  = trim($_POST['orig_date']  ?? '');
$origEvent = trim($_POST['orig_event'] ?? '');
$date      = trim($_POST['date']       ?? '');
$event     = trim($_POST['event']      ?? '');
$location  = trim($_POST['location']   ?? '');
$testersRaw  = $_POST['testers']    ?? '[]';
$obsPairsRaw = $_POST['obs_pairs']  ?? '[]';

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

// Header row: date + event + blank People + location in Observation + blank Solution
$rows[] = [$date, $event, '', $location, ''];

// Tester rows: blank Date/Event + "Name email" in People + blank Observation/Solution
foreach ($testers as $t) {
    $t = trim((string)$t);
    if ($t === '') continue;
    $email    = $emailMap[strtolower($t)] ?? '';
    $combined = $t . ($email !== '' ? ' ' . $email : '');
    $rows[] = ['', '', $combined, '', ''];
}

// Obs rows: blank Date/Event/People + Observation + Solution
foreach ($obsPairs as $pair) {
    $obs = trim($pair['obs'] ?? '');
    $sol = trim($pair['sol'] ?? '');
    if ($obs !== '' || $sol !== '') {
        $rows[] = ['', '', '', $obs, $sol];
    }
}

$tabName    = '[' . $gameName . '] dev';
$pythonPath = $_ENV['PYTHON'] ?? 'python3';

$payload = [
    'orig_date'  => $origDate,
    'orig_event' => $origEvent,
    'rows'       => $rows,
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
