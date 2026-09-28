<?php
// createInvoiceFromEstimate.php — generate a formatted invoice sheet from an estimate.
//
// POST params:
//   id           — Google Spreadsheet ID
//   game         — Game name
//   client       — Client / publisher name
//   estimate_num — Source estimate number (for reference)
//   estimate_amt — Original estimate amount (numeric string)
//   invoice_amt  — Amount to invoice (numeric string)
//   notes        — Invoice notes
//   my_name / my_phone / my_company / my_logo / my_address — designer info

error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';

$sheetId     = trim($_POST['id']           ?? '');
$game        = trim($_POST['game']         ?? '');
$client      = trim($_POST['client']       ?? '');
$estimateNum = trim($_POST['estimate_num'] ?? '');
$estimateAmt = trim($_POST['estimate_amt'] ?? '0');
$invoiceAmt  = trim($_POST['invoice_amt']  ?? '0');
$notes       = trim($_POST['notes']        ?? '');
$myName      = trim($_POST['my_name']      ?? '');
$myPhone     = trim($_POST['my_phone']     ?? '');
$myCompany   = trim($_POST['my_company']   ?? '');
$myLogo      = trim($_POST['my_logo']      ?? '');
$myAddress   = trim($_POST['my_address']   ?? '');

if (!$sheetId) {
    echo json_encode(['error' => 'Missing sheet ID']);
    exit;
}

$pythonPath = $_ENV['PYTHON'] ?? 'python3';

// Step 1: add a new row to the contracts sheet with Type = "Invoice"
$docId = '';
$rowPayload = [
    'game'     => $game,
    'client'   => $client,
    'quote'    => $invoiceAmt,
    'payment'  => 'Invoiced',
    'type'     => 'Invoice',
    'notes'    => $notes ?: ('Invoice from estimate #' . $estimateNum),
    'date'     => date('n/j/Y'),
];
$rowEncoded = base64_encode(json_encode($rowPayload, JSON_UNESCAPED_UNICODE));
$rowCmd     = escapeshellarg($pythonPath) . ' '
            . escapeshellarg(__DIR__ . '/gaddcontractrow.py') . ' '
            . escapeshellarg($sheetId . '|' . $rowEncoded) . ' 2>&1';
$rowOut     = trim((string) shell_exec($rowCmd));
$rowResult  = json_decode($rowOut, true);
if (!empty($rowResult['ok'])) {
    $docId = $rowResult['contract_id'] ?? '';
}

// Step 2: generate the invoice sheet tab
$payload = [
    'game'         => $game,
    'client'       => $client,
    'doc_id'       => $docId,
    'quote'        => $invoiceAmt,
    'payment'      => 'Invoiced',
    'estimate_num' => $estimateNum,
    'notes'        => $notes,
    'my_name'    => $myName,
    'my_phone'   => $myPhone,
    'my_company' => $myCompany,
    'my_logo'    => $myLogo,
    'my_address' => $myAddress,
];
$encoded = base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE));
$cmd     = escapeshellarg($pythonPath) . ' '
         . escapeshellarg(__DIR__ . '/gcreateInvoice.py') . ' '
         . escapeshellarg($sheetId . '|' . $encoded) . ' 2>&1';
$output  = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from invoice script']);
    exit;
}

$result = json_decode($output, true);
if ($result === null) {
    echo json_encode(['error' => $output]);
    exit;
}

// Step 3: cache record to estimates.json
if (!empty($result['ok'])) {
    $estFile  = dirname(__DIR__) . '/sheets/' . $sheetId . '/estimates.json';
    $existing = file_exists($estFile)
        ? (json_decode(file_get_contents($estFile), true) ?: [])
        : [];
    $record = [
        'type'         => 'invoice',
        'client'       => $client,
        'game'         => $game,
        'estimate_num' => $result['invoice_num'] ?? $docId,
        'amount'       => floatval($invoiceAmt),
        'url'          => $result['url']   ?? '',
        'tab'          => $result['tab']   ?? '',
        'date'         => date('m/d/Y'),
    ];
    $existing[] = $record;
    file_put_contents($estFile, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $result['invoice_record'] = $record;
}

echo json_encode($result);
?>
