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

// A short reference, like the other contract rows (used to find the row again, e.g. to delete it)
$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$ref = '';
for ($i = 0; $i < 8; $i++) $ref .= $alphabet[random_int(0, strlen($alphabet) - 1)];

$row = [
    'ID'         => $ref,
    'Ref Number' => $ref,
    'Date'       => $date,
    'Game'       => $game,
    'Client'     => $client,
    'Type'       => 'Payment',
    'Payee'      => $payee,
    'Quote'      => number_format((float) $amount, 2, '.', ''),
    'Status'     => 'Paid',
    'Payment'    => 'Paid',
    'Summary'    => $summary,
    'Notes'      => $notes,
    '__add_headers__' => ['Type', 'Ref Number', 'Payee', 'Summary'],
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
echo json_encode(['ok' => true, 'row' => $row] + (empty($slip['ok']) ? ['slip_error' => $slip['error'] ?? 'Could not make the slip'] : []), JSON_UNESCAPED_UNICODE);
