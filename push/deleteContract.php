<?php
// deleteContract.php — delete a contract row from the Google Sheet and contracts.json cache.
//
// POST params:
//   id          — Google Spreadsheet ID
//   contract_id — Value in the ID column of the row to delete

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId    = trim($_POST['id']          ?? '');
$contractId = trim($_POST['contract_id'] ?? '');

if (!$sheetId || $contractId === '') {
    echo json_encode(['error' => 'Missing id or contract_id']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$encoded    = base64_encode(json_encode(['contract_id' => $contractId], JSON_UNESCAPED_UNICODE));
$cmd        = escapeshellarg($pythonPath) . ' '
            . escapeshellarg(__DIR__ . '/gdeletecontractrow.py') . ' '
            . escapeshellarg($sheetId . '|' . $encoded) . ' 2>&1';
$output     = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from Python script']);
    exit;
}

$result = json_decode($output, true);
if ($result === null) {
    echo json_encode(['error' => $output]);
    exit;
}

if (!empty($result['ok'])) {
    // Remove from contracts.json cache
    $file = dirname(__DIR__) . '/sheets/' . $sheetId . '/contracts.json';
    if (file_exists($file)) {
        $rows = json_decode(file_get_contents($file), true) ?: [];
        $rows = array_values(array_filter($rows, function($r) use ($contractId) {
            return (string)($r['ID'] ?? '') !== (string)$contractId;
        }));
        file_put_contents($file, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

echo json_encode($result);
?>
