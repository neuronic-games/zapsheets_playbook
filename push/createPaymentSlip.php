<?php
// createPaymentSlip.php — make (or remake) the PDF payment slip for a payment already in the Contracts tab.
//
// POST params: id (sheet), ref (the payment's Ref Number), my_name, my_phone, my_company, my_logo, my_address
// Returns: { ok, url }
require_once __DIR__ . '/gbGate.php'; gbGate(['devboard']);   // GameBoard account: signed in, the sheet's owner, the app unlocked
error_reporting(0);
ini_set('display_errors', '0');
header('Content-Type: application/json');

require __DIR__ . '/../dotEnv.php';
require_once __DIR__ . '/paymentSlip.php';

$sheetId = trim($_POST['id']  ?? '');
require_once __DIR__ . '/stripeCommon.php';
devboardRequirePro($sheetId);
$ref = trim($_POST['ref'] ?? '');
if (!stripeValidSheetId($sheetId) || $ref === '') { echo json_encode(['error' => 'Missing id or ref']); exit; }

$row = null;
foreach ((json_decode((string) @file_get_contents(dirname(__DIR__) . '/sheets/' . $sheetId . '/contracts.json'), true) ?: []) as $c) {
    if ((string) ($c['Ref Number'] ?? '') === $ref) { $row = $c; break; }
}
if (!$row) { echo json_encode(['error' => 'Payment not found — try Fetch, then again']); exit; }

echo json_encode(pbMakePaymentSlip($sheetId, $row, $_POST));
