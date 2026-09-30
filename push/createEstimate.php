<?php
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId      = trim($_POST['id']             ?? '');
$game         = trim($_POST['game']           ?? '');
$client       = trim($_POST['client']         ?? '');
$numTests     = max(0, intval($_POST['num_tests']     ?? 2));
$numEdits     = max(0, intval($_POST['num_edits']     ?? 2));
$qty          = trim($_POST['qty']            ?? '1');
$unitPrice    = trim($_POST['unit_price']     ?? '');
$duration     = trim($_POST['duration']       ?? '');
$targetStart  = trim($_POST['target_start']  ?? '');
$targetEnd    = trim($_POST['target_end']    ?? '');
$discountPct  = max(0, floatval($_POST['discount_pct']   ?? 0));
$discountLbl  = trim($_POST['discount_label'] ?? '');
$notes        = trim($_POST['notes']          ?? '');
$scopeOfWork  = trim($_POST['scope_of_work'] ?? '');
$myName       = trim($_POST['my_name']        ?? '');
$myPhone      = trim($_POST['my_phone']       ?? '');
$myCompany    = trim($_POST['my_company']     ?? '');
$myLogo       = trim($_POST['my_logo']        ?? '');
$myAddress    = trim($_POST['my_address']     ?? '');

if (!$sheetId) {
    echo json_encode(['error' => 'Missing sheet ID']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';

// Step 1: add a row to the contracts sheet and get the assigned ID
$docId = '';
$rowPayload = [
    'game'          => $game,
    'client'        => $client,
    'quote'         => $unitPrice,
    'payment'       => 'Estimate',
    'tests'         => $numTests,
    'edits'         => $numEdits,
    'duration'      => $duration,
    'target_start'  => $targetStart,
    'target_end'    => $targetEnd,
    'type'          => 'Estimate',
    'notes'         => $notes,
    'scope_of_work' => $scopeOfWork,
    'date'          => date('n/j/Y'),
];
$rowEncoded = base64_encode(json_encode($rowPayload, JSON_UNESCAPED_UNICODE));
$rowCmd     = escapeshellarg($pythonPath) . ' '
            . escapeshellarg(__DIR__ . '/gaddcontractrow.py') . ' '
            . escapeshellarg($sheetId . '|' . $rowEncoded) . ' 2>&1';
$rowOut     = trim((string) shell_exec($rowCmd));
$rowResult  = json_decode($rowOut, true);
if (empty($rowResult['ok'])) {
    echo json_encode(['error' => $rowResult['error'] ?? ('Row script failed: ' . $rowOut)]);
    exit;
}
$docId   = $rowResult['contract_id'] ?? '';
$refCode = $rowResult['ref_code']    ?? '';

$appDomain = rtrim($_ENV['APP_DOMAIN'] ?? 'http://localhost:8000', '/');
$basePath  = trim($_ENV['BASE_PATH']  ?? '', '/');
$baseUrl   = $basePath ? "$appDomain/$basePath" : $appDomain;

$payload = [
    'game'          => $game,
    'client'        => $client,
    'doc_id'        => $docId,
    'doc_type'      => 'Estimate',
    'num_tests'     => $numTests,
    'num_edits'     => $numEdits,
    'qty'           => $qty,
    'unit_price'    => $unitPrice,
    'duration'      => $duration,
    'discount_pct'  => $discountPct,
    'discount_label'=> $discountLbl,
    'notes'         => $notes,
    'scope_of_work' => $scopeOfWork,
    'my_name'       => $myName,
    'my_phone'      => $myPhone,
    'my_company'    => $myCompany,
    'my_logo'       => $myLogo,
    'my_address'    => $myAddress,
    'tgt_start'     => $targetStart,
    'tgt_end'       => $targetEnd,
    'base_url'      => $baseUrl,
    'ref_code'      => $refCode,
];
$encoded = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
$arg     = $sheetId . '|' . $encoded;

$cmd    = escapeshellarg($pythonPath) . ' '
        . escapeshellarg(__DIR__ . '/gcreateDocPdf.py') . ' '
        . escapeshellarg($arg) . ' 2>&1';
$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from PDF script']);
    exit;
}

$result = json_decode($output, true);
if ($result === null) {
    echo json_encode(['error' => $output]);
    exit;
}

// On success, append to estimates.json
if (!empty($result['ok'])) {
    $estFile  = dirname(__DIR__) . '/sheets/' . $sheetId . '/estimates.json';
    $existing = file_exists($estFile)
        ? (json_decode(file_get_contents($estFile), true) ?: [])
        : [];
    $record = [
        'type'         => 'estimate',
        'client'       => $client,
        'game'         => $game,
        'estimate_num' => $result['doc_num'] ?? $docId,
        'amount'       => floatval($unitPrice),
        'url'          => $result['url']  ?? '',
        'file'         => $result['file'] ?? '',
        'tab'          => '',
        'contract_id'  => $docId,
        'date'         => date('m/d/Y'),
    ];
    $existing[] = $record;
    file_put_contents($estFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $result['estimate_record'] = $record;

    // Keep contracts.json cache in sync with the new PDF URL
    if (!empty($result['url']) && $docId) {
        $contractsFile = dirname(__DIR__) . '/sheets/' . $sheetId . '/contracts.json';
        if (file_exists($contractsFile)) {
            $contracts = json_decode(file_get_contents($contractsFile), true) ?: [];
            foreach ($contracts as &$c) {
                if ((string)($c['Ref Number'] ?? '') === (string)$docId) {
                    $c['Files'] = $result['url'];
                    break;
                }
            }
            unset($c);
            file_put_contents($contractsFile, json_encode($contracts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
}

echo json_encode($result);
?>
