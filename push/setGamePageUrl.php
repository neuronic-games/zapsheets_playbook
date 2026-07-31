<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId  = trim($_POST['id']        ?? '');
$origName = trim($_POST['orig_name'] ?? '');
$pageUrl  = trim($_POST['page_url']  ?? '');

if (!$sheetId) {
    echo json_encode(['error' => 'Missing sheet ID']);
    exit;
}
if (!$origName) {
    echo json_encode(['error' => 'Missing orig_name']);
    exit;
}
if (!$pageUrl) {
    echo json_encode(['error' => 'Missing page_url']);
    exit;
}

$data    = ['orig_name' => $origName, 'page_url' => $pageUrl];
$encoded = base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE));
$arg     = $sheetId . '|' . $encoded;

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/gsetgamepageurl.py') . ' '
     . escapeshellarg($arg) . ' 2>&1';

$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from Python script']);
    exit;
}

$result = json_decode($output, true);
if ($result !== null && !empty($result['ok'])) {
    refreshJson($pythonPath, $sheetId, 'games');
}
echo $result !== null ? json_encode($result) : json_encode(['error' => $output]);
?>
