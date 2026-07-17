<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId = trim($_POST['id']    ?? '');
$name    = trim($_POST['name']  ?? '');
$email   = trim($_POST['email'] ?? '');
$phone   = trim($_POST['phone'] ?? '');

if (!$sheetId || !$name) {
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$payload    = ['name' => $name, 'email' => $email, 'phone' => $phone];
$encoded    = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
$arg        = $sheetId . '|' . $encoded;

$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/gupdateprofile.py') . ' '
     . escapeshellarg($arg) . ' 2>&1';

$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from update script']);
    exit;
}

$result = json_decode($output, true);
if ($result === null) {
    echo json_encode(['error' => $output]);
    exit;
}

if (!empty($result['ok'])) {
    refreshJson($pythonPath, $sheetId, 'settings');
}

echo json_encode($result);
?>
