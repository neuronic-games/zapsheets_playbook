<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require dirname(__DIR__) . '/dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$body     = json_decode(file_get_contents('php://input'), true);
$sheetId  = trim($body['id']        ?? '');
$exercises = $body['exercises']     ?? [];

if (!$sheetId || !is_array($exercises) || empty($exercises)) {
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}

// ── 1. Update local week.json ─────────────────────────────────────────────────
$jsonPath = dirname(__DIR__) . '/sheets/' . $sheetId . '/week.json';
if (!file_exists($jsonPath)) {
    echo json_encode(['error' => 'week.json not found for sheet ' . $sheetId]);
    exit;
}

$data = json_decode(file_get_contents($jsonPath), true);
if (!is_array($data)) {
    echo json_encode(['error' => 'Invalid week.json']);
    exit;
}

// Build lookup by Day + Exercise
$updMap = [];
foreach ($exercises as $ex) {
    $key = ($ex['Day'] ?? '') . '||' . ($ex['Exercise'] ?? '');
    $updMap[$key] = $ex;
}

$updFields = ['Date','Done','Weight (lbs)','Weight (kg)',
              'Set 1','Set 2','Set 3','Set 4',
              'Total Reps','Total Volume (lbs)','My Notes'];

foreach ($data as &$row) {
    $key = ($row['Day'] ?? '') . '||' . ($row['Exercise'] ?? '');
    if (isset($updMap[$key])) {
        $upd = $updMap[$key];
        foreach ($updFields as $field) {
            if (array_key_exists($field, $upd)) {
                $row[$field] = $upd[$field];
            }
        }
    }
}
unset($row);

file_put_contents($jsonPath, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

// ── 2. Update Google Sheet via Python ─────────────────────────────────────────
$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$encoded    = base64_encode(json_encode($exercises, JSON_UNESCAPED_UNICODE));
$arg        = $sheetId . '|' . $encoded;

$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/gupdate_fitboard.py') . ' '
     . escapeshellarg($arg) . ' 2>&1';

$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from Python script']);
    exit;
}

$result = json_decode($output, true);
echo $result !== null ? json_encode($result) : json_encode(['error' => $output]);
?>
