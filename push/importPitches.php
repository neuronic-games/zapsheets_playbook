<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId     = trim($_POST['id']      ?? '');
$pitchesJson = trim($_POST['pitches'] ?? '[]');
$peopleJson  = trim($_POST['people']  ?? '[]');
$gameJson    = trim($_POST['game']    ?? 'null');

if (!$sheetId) {
    echo json_encode(['error' => 'Missing sheet ID']);
    exit;
}

$pitches = json_decode($pitchesJson, true) ?: [];
$people  = json_decode($peopleJson,  true) ?: [];
$game    = ($gameJson !== 'null' && $gameJson !== '') ? json_decode($gameJson, true) : null;

$pythonPath = $_ENV['PYTHON'] ?? 'python3';

// Build the payload for gimportpitches.py
$payload = [
    'pitches' => $pitches,
    'people'  => $people,
    'game'    => $game,
];
$encoded = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
$arg     = $sheetId . '|' . $encoded;

$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/gimportpitches.py') . ' '
     . escapeshellarg($arg) . ' 2>&1';

$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from import script']);
    exit;
}

$result = json_decode($output, true);
if ($result === null) {
    echo json_encode(['error' => $output]);
    exit;
}

if (!empty($result['ok'])) {
    refreshJson($pythonPath, $sheetId, 'pitches');
    if (!empty($result['people_added'])) {
        refreshJson($pythonPath, $sheetId, 'people');
    }
    if (!empty($result['game_added'])) {
        refreshJson($pythonPath, $sheetId, 'games');
    }
}

echo json_encode($result);
?>
