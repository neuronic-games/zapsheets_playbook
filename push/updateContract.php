<?php
require_once __DIR__ . '/gbGate.php'; gbGate(['devboard']);   // GameBoard account: signed in, the sheet's owner, the app unlocked
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';

$sheetId       = trim($_POST['id']             ?? '');
require_once __DIR__ . '/stripeCommon.php';
devboardRequirePro($sheetId);
$contractId    = trim($_POST['contract_id']    ?? '');
$game          = trim($_POST['game']           ?? '');
$client        = trim($_POST['client']         ?? '');
$targetStart   = trim($_POST['target_start']   ?? '');
$targetEnd     = trim($_POST['target_end']     ?? '');
$startDate     = trim($_POST['start_date']     ?? '');
$endDate       = trim($_POST['end_date']       ?? '');
$quote         = trim($_POST['quote']          ?? '');
$printing      = trim($_POST['printing']       ?? '');
$payment       = trim($_POST['payment']        ?? '');
$notes         = trim($_POST['notes']          ?? '');
$discountLabel = trim($_POST['discount_label'] ?? '');
$discountPct   = max(0, floatval($_POST['discount_pct'] ?? 0));
$summary       = isset($_POST['summary']) ? trim(preg_replace('/\s+/', ' ', (string) $_POST['summary'])) : null;   // one line; not sent = unchanged

if (!$sheetId || !$contractId) {
    echo json_encode(['error' => 'Missing required fields']);
    exit;
}
if ($summary !== null && $summary === '') {
    echo json_encode(['error' => 'Add a one-line summary']);
    exit;
}

$data = [
    'contract_id'       => $contractId,
    'game'              => $game,
    'client'            => $client,
    'target_start_date' => $targetStart,
    'target_end_date'   => $targetEnd,
    'start_date'        => $startDate,
    'end_date'          => $endDate,
    'quote'             => $quote,
    'printing'          => $printing,
    'payment'           => $payment,
    'notes'             => $notes,
    'discount_label'    => $discountPct > 0 ? $discountLabel : '',
    'discount_pct'      => $discountPct > 0 ? strval($discountPct) : '',
];
if ($summary !== null) $data['summary'] = $summary;
// A payment: who was paid and when
$extra = [];
if (isset($_POST['payee'])) { $payee = trim((string) $_POST['payee']); if ($payee === '') { echo json_encode(['error' => 'Who was paid?']); exit; } $extra['Payee'] = $payee; }
if (isset($_POST['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim((string) $_POST['date']))) $extra['Date'] = date('m/d/Y', strtotime(trim((string) $_POST['date'])));   // a date in the sheet
if ($extra && is_numeric(str_replace([',', '$'], '', $quote))) $data['quote'] = '$' . number_format((float) str_replace([',', '$'], '', $quote), 2, '.', ',');   // a payment's amount: money
if ($extra) $data['extra'] = $extra;

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$encoded    = base64_encode(json_encode($data, JSON_UNESCAPED_UNICODE));
$arg        = $sheetId . '|' . $encoded;

$cmd = escapeshellarg($pythonPath) . ' '
     . escapeshellarg(__DIR__ . '/gupdatecontract.py') . ' '
     . escapeshellarg($arg) . ' 2>&1';

$output = trim((string) shell_exec($cmd));

if ($output === '') {
    echo json_encode(['error' => 'No response from Python script']);
    exit;
}

$result = json_decode($output, true);
if ($result === null || empty($result['ok'])) {
    echo $result !== null ? json_encode($result) : json_encode(['error' => $output]);
    exit;
}

// ── Regenerate the estimate/invoice PDF (same file, link and number) ─────────
// Only for documents DevBoard created itself (they have a record in
// estimates.json with this contract's Ref Number).
$record = $result['record'] ?? [];
unset($result['record']);
$estFile = dirname(__DIR__) . '/sheets/' . $sheetId . '/estimates.json';
$ests    = file_exists($estFile) ? (json_decode(file_get_contents($estFile), true) ?: []) : [];
$estIdx  = null;
foreach ($ests as $i => $e) {
    if ((string)($e['contract_id'] ?? '') === $contractId) { $estIdx = $i; }
}
$docType = ucfirst(strtolower(trim($record['Type'] ?? '')));

if ($estIdx !== null && in_array($docType, ['Estimate', 'Invoice'], true)) {
    $appDomain = rtrim($_ENV['APP_DOMAIN'] ?? 'http://localhost:8000', '/');
    $basePath  = trim($_ENV['BASE_PATH']  ?? '', '/');
    $baseUrl   = $basePath ? "$appDomain/$basePath" : $appDomain;
    if (function_exists('gbLinkBase')) $baseUrl = rtrim(gbLinkBase(), '/');   // the PDF's share link: the apps' own domain (mygameboard.app), not zapsheets.com
    $desc      = trim($record['Description'] ?? '');
    $pdfNotes  = $notes;

    $payload = [
        'game'       => $game ?: ($record['Game'] ?? ''),
        'client'     => $client,
        'doc_id'     => $contractId,
        'ref_code'   => $contractId,
        'doc_type'   => $docType,
        'printing'   => $printing,
        'tgt_start'  => $targetStart,
        'tgt_end'    => $targetEnd,
        'duration'   => trim($record['Duration'] ?? ''),
        'doc_date'   => trim($record['Date'] ?? ($ests[$estIdx]['date'] ?? '')),
        'my_name'    => trim($_POST['my_name']    ?? ''),
        'my_phone'   => trim($_POST['my_phone']   ?? ''),
        'my_company' => trim($_POST['my_company'] ?? ''),
        'my_logo'    => trim($_POST['my_logo']    ?? ''),
        'my_address' => trim($_POST['my_address'] ?? ''),
        'my_payment' => trim($_POST['my_payment'] ?? ''),
        'base_url'   => $baseUrl,
        'summary'    => $summary !== null ? $summary : trim($record['Summary'] ?? ''),
    ];
    if ($docType === 'Estimate') {
        $payload += [
            'qty'            => '1',
            'unit_price'     => $quote,
            'num_tests'      => intval($record['Tests'] ?? 0),
            'num_edits'      => intval($record['Edits'] ?? 0),
            'discount_pct'   => $discountPct,
            'discount_label' => $discountLabel,
            'scope_of_work'  => $desc,
            'description'    => $desc,
        ];
    } else {
        // Invoice created from an estimate: keep the "Estimate #" reference
        $fromEst = trim($ests[$estIdx]['from_estimate'] ?? '');
        if (!$fromEst && preg_match('/^Invoice from estimate #(\S+)$/', $notes, $m)) $fromEst = $m[1];
        if (preg_match('/^Invoice from estimate #\S*$/', $notes)) $pdfNotes = '';  // auto-note, not shown on the PDF originally
        $payload += [
            'quote'        => $quote,
            'description'  => $desc,
            'estimate_num' => $fromEst,
        ];
    }
    $payload['notes'] = $pdfNotes;

    $pdfCmd = escapeshellarg($pythonPath) . ' '
            . escapeshellarg(__DIR__ . '/gcreateDocPdf.py') . ' '
            . escapeshellarg($sheetId . '|' . base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE))) . ' 2>&1';
    $pdfOut = json_decode(trim((string) shell_exec($pdfCmd)), true);

    if (!empty($pdfOut['ok'])) {
        $ests[$estIdx]['amount'] = floatval($quote) + floatval($printing);
        if (!empty($pdfOut['url'])) $ests[$estIdx]['url'] = $pdfOut['url'];
        $ests[$estIdx]['client'] = $client;
        file_put_contents($estFile, json_encode($ests, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $result['regenerated'] = ['ok' => true, 'estimate_record' => $ests[$estIdx]];
    } else {
        $result['regenerated'] = ['error' => $pdfOut['error'] ?? 'The PDF could not be regenerated'];
    }
}

// A payment: its slip made again with the corrected details (same link) — not when only tidying the sheet (no_slip)
if ($docType === 'Payment' && empty($_POST['no_slip'])) {
    require_once __DIR__ . '/paymentSlip.php';
    $slip = pbMakePaymentSlip($sheetId, $record, $_POST);
    $result['record'] = $record;
    if (!empty($slip['ok'])) { $result['slip'] = $slip['url']; $result['record']['Files'] = $slip['url']; }
    else $result['slip_error'] = $slip['error'] ?? 'Could not make the slip';
}

refreshJson($pythonPath, $sheetId, 'contracts');
echo json_encode($result);
