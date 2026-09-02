<?php
// createDevTab.php — create a [GameName] dev tab in Google Sheets.
//
// POST params:
//   id   — Google Spreadsheet ID
//   game — Game name (tab will be "[game] dev")

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId  = trim($_POST['id']   ?? '');
$gameName = trim($_POST['game'] ?? '');

if (!$sheetId || !$gameName) {
    echo json_encode(['error' => 'Missing id or game']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$arg        = $sheetId . '|' . $gameName;

$cmd    = escapeshellarg($pythonPath) . ' '
        . escapeshellarg(__DIR__ . '/gcreatedevtab.py') . ' '
        . escapeshellarg($arg) . ' 2>&1';
$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from script']);
    exit;
}

$result = json_decode($output, true);
if ($result === null) {
    echo json_encode(['error' => $output]);
    exit;
}

if (!empty($result['ok'])) {
    // Write an empty JSON cache for this tab so the file exists immediately
    // and the dashboard knows to show this game.
    $tabLower = strtolower('[' . $gameName . '] dev');
    $dir      = dirname(__DIR__) . '/sheets/' . $sheetId;
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $cachePath = $dir . '/' . $tabLower . '.json';
    if (!file_exists($cachePath)) {
        file_put_contents($cachePath, '[]');
    }
    $result['cache_file'] = $tabLower . '.json';
}

echo json_encode($result);
?>
