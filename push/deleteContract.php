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

// Helper: resolve a file URL to a server path and delete it
function deleteLinkedPdf($fileUrl, $sheetId) {
    if (!$fileUrl) return;
    $appDomain = rtrim($_ENV['APP_DOMAIN'] ?? '', '/');
    $basePath  = trim($_ENV['BASE_PATH']  ?? '', '/');
    // Strip protocol + domain
    $rel = preg_replace('#^https?://[^/]+#', '', $fileUrl);
    $rel = ltrim($rel, '/');
    // Strip BASE_PATH prefix if present
    if ($basePath !== '' && strpos($rel, $basePath . '/') === 0) {
        $rel = substr($rel, strlen($basePath) + 1);
    }
    $serverFile = dirname(__DIR__) . '/' . $rel;
    if (file_exists($serverFile)) {
        @unlink($serverFile);
    }
}

if (!empty($result['ok'])) {
    // Remove from contracts.json cache and delete any linked PDF file
    $contractsPath = dirname(__DIR__) . '/sheets/' . $sheetId . '/contracts.json';
    $fileUrl = '';

    if (file_exists($contractsPath)) {
        $rows = json_decode(file_get_contents($contractsPath), true) ?: [];

        foreach ($rows as $r) {
            if ((string)($r['ID'] ?? '') === (string)$contractId) {
                $fileUrl = $r['Files'] ?? '';
                break;
            }
        }

        $rows = array_values(array_filter($rows, function($r) use ($contractId) {
            return (string)($r['ID'] ?? '') !== (string)$contractId;
        }));
        file_put_contents($contractsPath, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // Fallback: check estimates.json if contracts.json didn't have a file URL
    if (!$fileUrl) {
        $estPath = dirname(__DIR__) . '/sheets/' . $sheetId . '/estimates.json';
        if (file_exists($estPath)) {
            $ests = json_decode(file_get_contents($estPath), true) ?: [];
            foreach ($ests as $e) {
                if ((string)($e['contract_id'] ?? '') === (string)$contractId && !empty($e['url'])) {
                    $fileUrl = $e['url'];
                    break;
                }
            }
        }
    }

    deleteLinkedPdf($fileUrl, $sheetId);
}

echo json_encode($result);
?>
