<?php
// submitNote.php — append a feedback note to a game's notes sheet tab.
//
// POST params:
//   id    — Google Spreadsheet ID
//   game  — Game name
//   name  — Reviewer name
//   email — Reviewer email (optional)
//   note  — Feedback text

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId  = trim($_POST['id']    ?? '');
$gameName = trim($_POST['game']  ?? '');
$name     = trim($_POST['name']  ?? '');
$email    = trim($_POST['email'] ?? '');
$note     = trim($_POST['note']  ?? '');

if (!$sheetId || !$gameName || !$note) {
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';

$payload = [
    'game'  => $gameName,
    'name'  => $name,
    'email' => $email,
    'note'  => $note,
];
$encoded = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
$arg     = $sheetId . '|' . $encoded;

$cmd    = escapeshellarg($pythonPath) . ' '
        . escapeshellarg(__DIR__ . '/gsubmitnote.py') . ' '
        . escapeshellarg($arg) . ' 2>&1';
$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from submit script']);
    exit;
}

$result = json_decode($output, true);
if ($result === null) {
    echo json_encode(['error' => $output]);
    exit;
}

echo json_encode($result);
?>
