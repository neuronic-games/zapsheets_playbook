<?php
// createInvoice.php — generate a formatted invoice tab in the Google Sheet.
//
// POST params:
//   id           — Google Spreadsheet ID
//   game         — Game name
//   client       — Client / publisher name
//   quote        — Dollar amount (numeric string)
//   payment      — Payment status
//   status       — Contract status
//   target_start — Target start date
//   target_end   — Target end date
//   start_date   — Actual start date
//   end_date     — Actual end date
//   notes        — Contract notes
//   my_name      — Designer name (from settings)
//   my_phone     — Designer phone
//   my_company   — Designer company name
//   my_logo      — Logo URL
//   my_address   — Designer mailing address

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId    = trim($_POST['id']           ?? '');
$game       = trim($_POST['game']         ?? '');
$client     = trim($_POST['client']       ?? '');
$quote      = trim($_POST['quote']        ?? '');
$payment    = trim($_POST['payment']      ?? '');
$status     = trim($_POST['status']       ?? '');
$tgtStart   = trim($_POST['target_start'] ?? '');
$tgtEnd     = trim($_POST['target_end']   ?? '');
$startDate  = trim($_POST['start_date']   ?? '');
$endDate    = trim($_POST['end_date']     ?? '');
$notes       = trim($_POST['notes']        ?? '');
$description = trim($_POST['description'] ?? $game);
$myName     = trim($_POST['my_name']      ?? '');
$myPhone    = trim($_POST['my_phone']     ?? '');
$myCompany  = trim($_POST['my_company']   ?? '');
$myLogo     = trim($_POST['my_logo']      ?? '');
$myAddress  = trim($_POST['my_address']   ?? '');

if (!$sheetId) {
    echo json_encode(['error' => 'Missing id']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';

// Invoices are generated from existing contracts — no new row needed.
// The doc_id comes from the existing contract's ID (passed in POST).
$docId = trim($_POST['contract_id'] ?? '');

// Look up the contract's Ref Number from the local cache so gcreateDocPdf.py
// can use it as the PDF URL token (the unique, non-guessable identifier).
$refCode = '';
if ($docId) {
    $contractsFile = dirname(__DIR__) . '/sheets/' . $sheetId . '/contracts.json';
    if (file_exists($contractsFile)) {
        $contracts = json_decode(file_get_contents($contractsFile), true) ?: [];
        foreach ($contracts as $c) {
            if ((string)($c['Ref Number'] ?? '') === (string)$docId) {
                $refCode = $c['Ref Number'] ?? '';
                break;
            }
        }
    }
}

$appDomain = rtrim($_ENV['APP_DOMAIN'] ?? 'http://localhost:8000', '/');
$basePath  = trim($_ENV['BASE_PATH']  ?? '', '/');
$baseUrl   = $basePath ? "$appDomain/$basePath" : $appDomain;

$payload = [
    'game'       => $game,
    'client'     => $client,
    'doc_id'     => $docId,
    'doc_type'   => 'Invoice',
    'quote'      => $quote,
    'description'=> $description,
    'notes'      => $notes,
    'tgt_start'  => $tgtStart,
    'my_name'    => $myName,
    'my_phone'   => $myPhone,
    'my_company' => $myCompany,
    'my_logo'    => $myLogo,
    'my_address' => $myAddress,
    'base_url'   => $baseUrl,
    'ref_code'   => $refCode,
];

$encoded = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
$arg     = $sheetId . '|' . $encoded;

$cmd    = escapeshellarg($pythonPath) . ' '
        . escapeshellarg(__DIR__ . '/gcreateDocPdf.py') . ' '
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

// Keep contracts.json cache in sync with the new PDF URL
if (!empty($result['ok']) && !empty($result['url']) && $docId) {
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

echo json_encode($result);
?>
