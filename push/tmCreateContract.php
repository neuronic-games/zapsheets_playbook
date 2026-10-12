<?php
// tmCreateContract.php — create an Estimate or Invoice for a T&MBoard project.
// Saves a row to the contracts tab, then generates a PDF via gcreateDocPdf.py.
// POST: id, project, client, doc_type (estimate|invoice),
//       time_hours, hourly_rate, time_cost, mat_cost, notes, due_date
error_reporting(0); ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId    = trim($_POST['id']          ?? '');
$project    = trim($_POST['project']     ?? '');
$client     = trim($_POST['client']      ?? '');
$docTypeRaw = strtolower(trim($_POST['doc_type'] ?? 'estimate'));
$docType    = $docTypeRaw === 'invoice' ? 'Invoice' : 'Estimate';
$timeHours  = trim($_POST['time_hours']  ?? '');
$hourlyRate = trim($_POST['hourly_rate'] ?? '');
$timeCost   = trim($_POST['time_cost']   ?? '0');
$matCost    = trim($_POST['mat_cost']    ?? '0');
$notes      = trim($_POST['notes']       ?? '');
$dueDate    = trim($_POST['due_date']    ?? '');

if (!$sheetId || !$project) {
    echo json_encode(['error' => 'Missing id or project']); exit;
}

$total = floatval($timeCost) + floatval($matCost);

$py = $_ENV['PYTHON'] ?? 'python3';

// ── Step 1: add row to the contracts tab ─────────────────────────────────────
$rowPayload = [
    'game'         => $project,
    'client'       => $client,
    'quote'        => strval($total),
    'payment'      => '',
    'type'         => $docType,
    'notes'        => $notes,
    'target_start' => $dueDate,
    'date'         => date('n/j/Y'),
];
$rowEnc = base64_encode(json_encode($rowPayload, JSON_UNESCAPED_UNICODE));
$rowCmd = escapeshellarg($py) . ' '
        . escapeshellarg(__DIR__ . '/gaddcontractrow.py') . ' '
        . escapeshellarg($sheetId . '|' . $rowEnc) . ' 2>&1';
$rowOut    = trim((string) shell_exec($rowCmd));
$rowResult = json_decode($rowOut, true);
if (empty($rowResult['ok'])) {
    echo json_encode(['error' => $rowResult['error'] ?? ('Row script failed: ' . $rowOut)]); exit;
}
$docId   = $rowResult['contract_id'] ?? '';
$refCode = $rowResult['ref_code']    ?? '';

// ── Step 2: generate PDF ─────────────────────────────────────────────────────
$appDomain = rtrim($_ENV['APP_DOMAIN'] ?? 'http://localhost:8000', '/');
$basePath  = trim($_ENV['BASE_PATH']  ?? '', '/');
$baseUrl   = $basePath ? "$appDomain/$basePath" : $appDomain;

// Read company settings from settings.json if present
$settingsFile = dirname(__DIR__) . '/sheets/' . $sheetId . '/settings.json';
$settings     = file_exists($settingsFile)
    ? (json_decode(file_get_contents($settingsFile), true) ?: [])
    : [];

$pdfPayload = [
    'game'          => $project,
    'client'        => $client,
    'doc_id'        => $docId,
    'doc_type'      => $docType,
    'quote'         => strval($total),
    'notes'         => $notes,
    'tgt_start'     => $dueDate,
    'tgt_end'       => '',
    'base_url'      => $baseUrl,
    'ref_code'      => $refCode,
    'my_name'       => $settings['Name']         ?? '',
    'my_phone'      => $settings['Phone']         ?? '',
    'my_company'    => $settings['Company']       ?? '',
    'my_logo'       => $settings['Logo URL']      ?? '',
    'my_address'    => $settings['Address']       ?? '',
    'my_payment'    => $settings['Payment Info']  ?? '',
    // T&M specific
    'tm_mode'       => true,
    'tm_time_cost'  => $timeCost,
    'tm_time_hours' => $timeHours,
    'tm_hourly_rate'=> $hourlyRate,
    'tm_mat_cost'   => $matCost,
];
$pdfEnc = base64_encode(json_encode($pdfPayload, JSON_UNESCAPED_UNICODE));
$pdfCmd = escapeshellarg($py) . ' '
        . escapeshellarg(__DIR__ . '/gcreateDocPdf.py') . ' '
        . escapeshellarg($sheetId . '|' . $pdfEnc) . ' 2>&1';
$pdfOut    = trim((string) shell_exec($pdfCmd));
$pdfResult = json_decode($pdfOut, true);
if ($pdfResult === null) {
    echo json_encode(['error' => $pdfOut]); exit;
}

// ── Step 3: update contracts.json cache with PDF URL ─────────────────────────
if (!empty($pdfResult['ok'])) {
    refreshJson($py, $sheetId, 'contracts');
    if (!empty($pdfResult['url']) && $docId) {
        $contractsFile = dirname(__DIR__) . '/sheets/' . $sheetId . '/contracts.json';
        if (file_exists($contractsFile)) {
            $contracts = json_decode(file_get_contents($contractsFile), true) ?: [];
            foreach ($contracts as &$c) {
                if ((string)($c['Ref Number'] ?? '') === (string)$docId) {
                    $c['Files'] = $pdfResult['url'];
                    break;
                }
            }
            unset($c);
            file_put_contents($contractsFile, json_encode($contracts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
}

echo json_encode($pdfResult);
?>
