<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId = trim($_POST['id']   ?? '');
$game    = trim($_POST['game'] ?? '');

if (!$sheetId || !$game) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$encoded    = base64_encode(json_encode(['game' => $game], JSON_UNESCAPED_UNICODE));
$arg        = $sheetId . '|' . $encoded;

$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/gdelete_game.py') . ' '
     . escapeshellarg($arg) . ' 2>&1';

$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from Python script']);
    exit;
}

$result = json_decode($output, true);

if ($result !== null && !empty($result['ok'])) {
    // Refresh the cached JSON for games and pitches
    refreshJson($pythonPath, $sheetId, 'games');
    refreshJson($pythonPath, $sheetId, 'pitches');

    // Delete the per-game JSON file (game-{safeName}-en.json)
    $safeName = str_replace(['/', '\\'], '-', $game);
    $sheetsDir = dirname(__DIR__) . '/sheets/' . $sheetId;
    $gameJson  = $sheetsDir . '/game-' . $safeName . '-en.json';
    if (file_exists($gameJson)) {
        unlink($gameJson);
        $result['game_json_deleted'] = true;
    }
}

echo $result !== null ? json_encode($result) : json_encode(['error' => $output]);
?>
