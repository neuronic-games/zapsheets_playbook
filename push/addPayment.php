<?php
// addPayment.php — record a payment to a collaborator (money going out) in the Contracts tab.
//
// The payment is a Contracts row with Type "Payment": Payee is who was paid, Quote the amount, Client the
// publisher it belongs with (so it shows under that publisher's game), Status "Paid". Sheets without
// Type / Payee / Ref Number columns get them added.
//
// POST params: id (sheet), game, client, payee, amount, date (YYYY-MM-DD, default today), summary (required), notes,
//              my_name, my_phone, my_company, my_logo, my_address (for the payment slip)
// Returns: { ok, row: { …the Contracts row, Files = the payment slip's link… }, slip_error? }
require_once __DIR__ . '/gbGate.php'; gbGate(['devboard']);   // GameBoard account: signed in, the sheet's owner, the app unlocked
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/refreshJson.php';
require_once __DIR__ . '/paymentSlip.php';

$sheetId = trim($_POST['id']     ?? '');
require_once __DIR__ . '/stripeCommon.php';
devboardRequirePro($sheetId);
$game    = trim($_POST['game']   ?? '');
$client  = trim($_POST['client'] ?? '');
$payee   = trim($_POST['payee']  ?? '');
$amount  = trim($_POST['amount'] ?? '');
$date    = trim($_POST['date']   ?? '');
$notes   = trim($_POST['notes']  ?? '');
$summary = trim(preg_replace('/\s+/', ' ', (string) ($_POST['summary'] ?? '')));   // the contract it's for (its one-line Summary)

if (!$sheetId || $payee === '') { echo json_encode(['error' => 'Who was paid?']); exit; }
if (!is_numeric($amount) || (float) $amount <= 0) { echo json_encode(['error' => 'Enter the amount paid']); exit; }
if ($summary === '') { echo json_encode(['error' => 'Which contract is it for? Add its summary']); exit; }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

// A short reference, like the other contract rows (used to find the row again, e.g. to delete it).
// The page makes it when the dialog opens and sends the same one if the save is tried again — so a retry
// (the first try still running, or its answer lost) never adds the payment twice.
$ref = strtoupper(trim((string) ($_POST['ref'] ?? '')));
if (!preg_match('/^[A-Z0-9]{8}$/', $ref)) {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $ref = '';
    for ($i = 0; $i < 8; $i++) $ref .= $alphabet[random_int(0, strlen($alphabet) - 1)];
}
$_dir = dirname(__DIR__) . '/sheets/' . basename($sheetId);
foreach ((json_decode((string) @file_get_contents($_dir . '/contracts.json'), true) ?: []) as $_c) {
    if ((string) ($_c['Ref Number'] ?? '') === $ref) { echo json_encode(['ok' => true, 'row' => $_c, 'already' => true], JSON_UNESCAPED_UNICODE); exit; }
}
// One try at a time for this payment: a marker made only if it isn't there (a second try waits for the first)
$_mkDir = $_dir . '/_payments';
if (!is_dir($_mkDir)) @mkdir($_mkDir, 0755, true);
$_mk = $_mkDir . '/' . $ref . '.json';
$_fh = @fopen($_mk, 'x');
if (!$_fh) {
    for ($_i = 0; $_i < 120; $_i++) {
        $_was = json_decode((string) @file_get_contents($_mk), true);
        if (!empty($_was['ok'])) { echo json_encode($_was + ['already' => true], JSON_UNESCAPED_UNICODE); exit; }
        if (!is_file($_mk)) break;   // the first try failed: this one goes ahead
        sleep(1);
    }
    if (is_file($_mk)) { echo json_encode(['error' => 'This payment is still being saved — wait a moment, then check the list before trying again']); exit; }
    $_fh = @fopen($_mk, 'x');
    if (!$_fh) { echo json_encode(['error' => 'This payment is being saved already']); exit; }
}
fwrite($_fh, '{}'); fclose($_fh);
register_shutdown_function(function () use ($_mk) { $d = json_decode((string) @file_get_contents($_mk), true); if (empty($d['ok'])) @unlink($_mk); });   // not saved: let a retry go

$row = [
    'ID'         => $ref,
    'Ref Number' => $ref,
    'Date'       => date('m/d/Y', strtotime($date)),   // a date in the sheet (like the other rows), not text
    'Game'       => $game,
    'Client'     => $client,
    'Type'       => 'Payment',
    'Payee'      => $payee,
    'Quote'      => '$' . number_format((float) $amount, 2, '.', ','),   // money, like the invoices
    'Status'     => 'Paid',
    'Payment'    => 'Paid',
    'Summary'    => $summary,
    'Notes'      => $notes,
    '__add_headers__' => ['Type', 'Ref Number', 'Payee', 'Summary'],
    '__raw__'    => ['Date', 'Quote'],
];
$contractRef = trim((string) ($_POST['contract_ref'] ?? ''));   // the contract it's for: its Ref Number as a note on the Summary
if ($contractRef !== '') $row['__notes__'] = ['Summary' => 'Ref Number: ' . $contractRef];

$pythonPath = $_ENV['PYTHON'] ?? 'python3';
$arg = $sheetId . '|contracts|' . base64_encode(json_encode($row, JSON_UNESCAPED_UNICODE));
$output = trim((string) shell_exec(escapeshellarg($pythonPath) . ' ' . escapeshellarg(__DIR__ . '/gadd.py') . ' ' . escapeshellarg($arg) . ' 2>&1'));
if ($output === '') { echo json_encode(['error' => 'No response from Python script']); exit; }

$result = json_decode($output, true);
if ($result === null) { echo json_encode(['error' => $output]); exit; }
if (empty($result['ok'])) { echo json_encode($result); exit; }

refreshJson($pythonPath, $sheetId, 'contracts');
unset($row['__add_headers__'], $row['__notes__']);

// The payment slip to send to the person (PDF, like estimates and invoices)
$slip = pbMakePaymentSlip($sheetId, $row, $_POST);
if (!empty($slip['ok'])) $row['Files'] = $slip['url'];
$_out = ['ok' => true, 'row' => $row] + (empty($slip['ok']) ? ['slip_error' => $slip['error'] ?? 'Could not make the slip'] : []);
@file_put_contents($_mk, json_encode($_out, JSON_UNESCAPED_UNICODE));   // a retry gets this answer
echo json_encode($_out, JSON_UNESCAPED_UNICODE);
