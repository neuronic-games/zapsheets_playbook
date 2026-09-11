<?php
// addDevRow.php — append a single row to a [GameName] dev tab.
//
// POST params:
//   id          — Google Spreadsheet ID
//   game        — Game name (tab = "[game] dev")
//   date        — Date string (ISO, or blank)
//   event       — Session label e.g. "Playtest 1" (blank for tester/obs rows)
//   observation — Observation text, location (header row), or tester name
//   solution    — Solution text (blank for header/tester rows)

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId     = trim($_POST['id']          ?? '');
$gameName    = trim($_POST['game']        ?? '');
$date        = trim($_POST['date']        ?? '');
$event       = trim($_POST['event']       ?? '');
$observation = trim($_POST['observation'] ?? '');
$solution    = trim($_POST['solution']    ?? '');
$rowType     = trim($_POST['row_type']    ?? '');

if (!$sheetId || !$gameName) {
    echo json_encode(['error' => 'Missing id or game']);
    exit;
}

$tabName    = '[' . $gameName . '] dev';
$pythonPath = $_ENV['PYTHON'] ?? 'python3';

// row_type='tester' or 'obs' is explicit; fall back to heuristic only when absent
if ($rowType === 'tester') {
    $isTesterRow = true;
} elseif ($rowType === 'obs' || $rowType === 'header') {
    $isTesterRow = false;
} else {
    // Legacy: blank date+event+solution was the only signal — but this mis-routes
    // obs rows that have no solution. Kept for backward compat with old clients.
    $isTesterRow = ($date === '' && $event === '' && $solution === '');
}

if ($isTesterRow) {
    // Parse inline email from tester name field — handles formats:
    //   John Doe john@acme.com
    //   John Doe, john@acme.com
    //   John Doe <john@acme.com>
    $emailPattern = '/^(.+?)(?:\s*<|\s*,\s*|\s+)([a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,})>?\s*$/i';
    $inlineEmail  = '';
    if (preg_match($emailPattern, $observation, $em)) {
        $observation = trim($em[1], " ,<>");
        $inlineEmail = trim($em[2]);
    }

    // Look up email in people.json as fallback (inline email takes priority)
    $email      = $inlineEmail;
    $peopleFile = dirname(__DIR__) . '/sheets/' . $sheetId . '/people.json';
    if (!$email && file_exists($peopleFile)) {
        $people = json_decode(file_get_contents($peopleFile), true) ?: [];
        foreach ($people as $p) {
            $pName = trim($p['Name'] ?? '');
            if (strcasecmp($pName, $observation) === 0) {
                $email = trim($p['Email'] ?? $p['email'] ?? '');
                break;
            }
        }
    }
    $arg    = $sheetId . '|' . $tabName . '|' . $observation . '|' . $email;
    $cmd    = escapeshellarg($pythonPath) . ' '
            . escapeshellarg(__DIR__ . '/gaddtesterrow.py') . ' '
            . escapeshellarg($arg) . ' 2>&1';
    $output = trim((string) shell_exec($cmd));
} else {
    $row     = [
        'Date'         => $date,
        'Event'        => $event,
        'Observations' => $observation,
        'Observation'  => $observation,   // legacy sheets used 'Observation' (no s)
        'Thoughts'     => $solution,
        'Solution'     => $solution,      // legacy sheets used 'Solution' not 'Thoughts'
    ];
    $encoded = base64_encode(json_encode($row, JSON_UNESCAPED_UNICODE));
    $arg     = $sheetId . '|' . $tabName . '|' . $encoded;
    $cmd     = escapeshellarg($pythonPath) . ' '
             . escapeshellarg(__DIR__ . '/gadd.py') . ' '
             . escapeshellarg($arg) . ' 2>&1';
    $output  = trim((string) shell_exec($cmd));
}

if ($output === '') {
    echo json_encode(['error' => 'No response from Python script']);
    exit;
}

$result = json_decode($output, true);
if ($result === null) {
    echo json_encode(['error' => $output]);
    exit;
}

// Refresh the dev JSON cache so the card reflects the new row
if (!empty($result['ok'])) {
    $sheetRowNum = (int)($result['row'] ?? 0);  // save before overwriting
    refreshJson($pythonPath, $sheetId, $tabName);
    $result['row'] = $row;

    // If the observation is an =IMAGE() formula, resize the row to show the image
    if (!$isTesterRow && $sheetRowNum > 0 && strpos($observation, '=IMAGE(') !== false) {
        $rowNum   = $sheetRowNum;
        $resizeArg = $sheetId . '|' . $tabName . '|' . $rowNum . '|300';
        shell_exec(
            escapeshellarg($pythonPath) . ' '
            . escapeshellarg(__DIR__ . '/gresizerow.py') . ' '
            . escapeshellarg($resizeArg) . ' 2>/dev/null'
        );
    }
}

echo json_encode($result);
?>
