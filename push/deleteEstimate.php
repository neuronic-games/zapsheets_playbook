<?php
// deleteEstimate.php — delete an estimate tab from the Google Sheet and remove from estimates.json.
//
// POST params:
//   id  — Google Spreadsheet ID
//   tab — Tab name to delete (e.g. "[Club K9] invoice 13")

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId    = trim($_POST['id']          ?? '');
$tab        = trim($_POST['tab']         ?? '');
$contractId = trim($_POST['contract_id'] ?? '');
$fileUrl    = trim($_POST['file_url']    ?? '');  // server path for PDF-based records

if (!$sheetId || (!$tab && !$fileUrl)) {
    echo json_encode(['error' => 'Missing id and tab/file_url']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$result     = ['ok' => false];

if ($tab) {
    // Legacy: delete a Google Sheets tab
    $arg    = $sheetId . '|' . $tab;
    $cmd    = escapeshellarg($pythonPath) . ' '
            . escapeshellarg(__DIR__ . '/gdeletetab.py') . ' '
            . escapeshellarg($arg) . ' 2>&1';
    $output = trim((string) shell_exec($cmd));
    if ($output === '') {
        echo json_encode(['error' => 'No response from Python script']);
        exit;
    }
    $result = json_decode($output, true);
    if ($result === null) {
        echo json_encode(['error' => $output]);
        exit;
    }
} else {
    // PDF-based: delete the file from the server
    $basePath = trim($_ENV['BASE_PATH'] ?? '', '/');
    $rel = preg_replace('#^https?://[^/]+#', '', $fileUrl);
    $rel = ltrim($rel, '/');
    if ($basePath !== '' && strpos($rel, $basePath . '/') === 0) {
        $rel = substr($rel, strlen($basePath) + 1);
    }
    $serverFile = dirname(__DIR__) . '/' . $rel;
    if (file_exists($serverFile)) {
        unlink($serverFile);
    }
    $result = ['ok' => true];
}

if (!empty($result['ok'])) {
    // Remove from estimates.json cache
    $estFile = dirname(__DIR__) . '/sheets/' . $sheetId . '/estimates.json';
    if (file_exists($estFile)) {
        $estimates = json_decode(file_get_contents($estFile), true) ?: [];
        $estimates = array_values(array_filter($estimates, function($e) use ($tab, $fileUrl) {
            if ($tab && ($e['tab'] ?? '') === $tab) return false;
            if ($fileUrl && ($e['url'] ?? '') === $fileUrl) return false;
            if ($fileUrl && ($e['file'] ?? '') === $fileUrl) return false;
            return true;
        }));
        file_put_contents($estFile, json_encode($estimates, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // Also delete the corresponding Contracts sheet row if we have an ID
    if ($contractId !== '') {
        $delEncoded = base64_encode(json_encode(['contract_id' => $contractId], JSON_UNESCAPED_UNICODE));
        $delCmd     = escapeshellarg($pythonPath) . ' '
                    . escapeshellarg(__DIR__ . '/gdeletecontractrow.py') . ' '
                    . escapeshellarg($sheetId . '|' . $delEncoded) . ' 2>&1';
        shell_exec($delCmd); // fire-and-forget; tab deletion already succeeded

        // Remove from contracts.json cache too
        $conFile = dirname(__DIR__) . '/sheets/' . $sheetId . '/contracts.json';
        if (file_exists($conFile)) {
            $contracts = json_decode(file_get_contents($conFile), true) ?: [];
            $contracts = array_values(array_filter($contracts, function($r) use ($contractId) {
                return (string)($r['ID'] ?? '') !== (string)$contractId;
            }));
            file_put_contents($conFile, json_encode($contracts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
}

echo json_encode($result);
?>
