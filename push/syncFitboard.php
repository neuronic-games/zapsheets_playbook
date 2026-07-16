<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require dirname(__DIR__) . '/dotEnv.php';

$body    = json_decode(file_get_contents('php://input'), true);
$sheetId = trim($body['id'] ?? '');

if (!$sheetId) {
    echo json_encode(['error' => 'Missing sheet id']);
    exit;
}

$jsonPath = dirname(__DIR__) . '/sheets/' . $sheetId . '/week.json';
if (!file_exists($jsonPath)) {
    echo json_encode(['error' => 'week.json not found for sheet ' . $sheetId]);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/gread_week.py') . ' '
     . escapeshellarg($sheetId) . ' 2>&1';

$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from Python script']);
    exit;
}

$result = json_decode($output, true);
if (!$result) {
    echo json_encode(['error' => $output]);
    exit;
}

if (!empty($result['data'])) {
    file_put_contents(
        $jsonPath,
        json_encode($result['data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );
    echo json_encode(['ok' => true]);
} else {
    echo json_encode($result);
}
?>
