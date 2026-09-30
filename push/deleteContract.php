<?php
// deleteContract.php — delete a contract row from the Google Sheet and contracts.json cache.
//
// POST params:
//   id          — Google Spreadsheet ID
//   contract_id — Ref Number of the row to delete

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

/**
 * Delete a PDF share: given a share URL like "{base}/shares/contracts/{hash}",
 * extract the hash, read the JSON to find the private PDF path, and delete both.
 * Falls back to the old direct-path deletion for legacy URLs.
 */
function deleteLinkedPdf($fileUrl) {
    if (!$fileUrl) return;

    $base     = dirname(__DIR__);
    $basePath = trim($_ENV['BASE_PATH'] ?? '', '/');

    // Normalize to a relative path (strip protocol + domain + optional base path)
    $rel = preg_replace('#^https?://[^/]+#', '', $fileUrl);
    $rel = ltrim($rel, '/');
    if ($basePath !== '' && strpos($rel, $basePath . '/') === 0) {
        $rel = substr($rel, strlen($basePath) + 1);
    }

    // Share-token format: shares/contracts/{alphanumeric token}
    if (preg_match('#^shares/contracts/([A-Za-z0-9]+)$#', $rel, $m)) {
        $hash     = $m[1];
        $jsonFile = $base . '/shares/contracts/' . $hash . '.json';
        if (file_exists($jsonFile)) {
            $meta    = json_decode(file_get_contents($jsonFile), true) ?: [];
            $pdfRel  = $meta['file'] ?? '';
            if ($pdfRel) {
                $pdfFile = $base . '/' . $pdfRel;
                if (file_exists($pdfFile)) { @unlink($pdfFile); }
            }
            @unlink($jsonFile);
        }
        return;
    }

    // Legacy: direct file path
    $serverFile = $base . '/' . $rel;
    if (file_exists($serverFile)) { @unlink($serverFile); }
}

if (!empty($result['ok'])) {
    // Remove from contracts.json cache and delete any linked PDF/share files
    $contractsPath = dirname(__DIR__) . '/sheets/' . $sheetId . '/contracts.json';
    $fileUrl = '';

    if (file_exists($contractsPath)) {
        $rows = json_decode(file_get_contents($contractsPath), true) ?: [];

        foreach ($rows as $r) {
            if ((string)($r['Ref Number'] ?? '') === (string)$contractId) {
                $fileUrl = $r['Files'] ?? '';
                break;
            }
        }

        $rows = array_values(array_filter($rows, function($r) use ($contractId) {
            return (string)($r['Ref Number'] ?? '') !== (string)$contractId;
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

    deleteLinkedPdf($fileUrl);
}

echo json_encode($result);
?>
