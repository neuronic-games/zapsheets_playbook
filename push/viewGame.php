<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId  = trim($_POST['id']   ?? '');
$gameName = trim($_POST['game'] ?? '');

if (!$sheetId) {
    echo json_encode(['error' => 'Missing sheet ID']);
    exit;
}

if (!$gameName) {
    echo json_encode(['error' => 'Missing game name']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';

// Refresh games.json from the live Games tab so that the latest tagline,
// description, and other metadata are available to the view page.
refreshJson($pythonPath, $sheetId, 'games');

$arg = $sheetId . '|' . $gameName;

$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/gpublishgame.py') . ' '
     . escapeshellarg($arg) . ' 2>&1';

$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from Python script']);
    exit;
}

$result = json_decode($output, true);
echo $result !== null ? json_encode($result) : json_encode(['error' => $output]);
?>
