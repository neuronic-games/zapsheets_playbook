<?php
/**
 * getSubscription.php — return the Pro subscription status for a sheet.
 *
 * POST params:  id — sheet ID
 * Response: {
 *   ok:         true,
 *   status:     "active" | "trialing" | "past_due" | "canceled" | "unpaid" | "none" | …,
 *   is_pro:     true | false,
 *   period_end: "YYYY-MM-DD" | "",
 *   app:        "devboard" | "gameboard" | "",
 *   portal_url: Stripe customer portal login link (STRIPE_PORTAL_URL) | ""
 * }
 * The subscriber's email is deliberately not returned.
 */
error_reporting(0);
header('Content-Type: application/json');
require_once __DIR__ . '/stripeCommon.php';

$sheetId = trim($_POST['id'] ?? '');
if (!stripeValidSheetId($sheetId)) stripeFail('Invalid sheet id');

$portalUrl = $_ENV['STRIPE_PORTAL_URL'] ?? '';
$file      = dirname(__DIR__) . '/sheets/' . $sheetId . '/subscription.json';
$data      = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
$status    = $data['status'] ?? 'none';

echo json_encode([
    'ok'         => true,
    'status'     => $status,
    'is_pro'     => in_array($status, ['active', 'trialing', 'past_due'], true),
    'period_end' => $data['period_end'] ?? '',
    'app'        => $data['app']        ?? '',
    'portal_url' => $portalUrl,
]);
