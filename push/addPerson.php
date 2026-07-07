<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId = trim($_POST['id']      ?? '');
$name    = trim($_POST['name']    ?? '');
$company = trim($_POST['company'] ?? '');
$email   = trim($_POST['email']   ?? '');

if (!$sheetId || !$name) {
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

$row = ['Name' => $name, 'Company' => $company, 'Email' => $email];

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$encoded    = base64_encode(json_encode($row, JSON_UNESCAPED_UNICODE));
$arg        = $sheetId . '|people|' . $encoded;

$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/gadd.py') . ' '
     . escapeshellarg($arg) . ' 2>&1';

$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from Python script']);
    exit;
}

$result = json_decode($output, true);
if ($result !== null && !empty($result['ok'])) {
    refreshJson($pythonPath, $sheetId, 'people');
}
echo $result !== null ? json_encode($result) : json_encode(['error' => $output]);
?>
