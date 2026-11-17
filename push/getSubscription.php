<?php
/**
 * getSubscription.php — return the Pro subscription status for a sheet.
 *
 * POST params:  id — sheet ID, app — devboard | gameboard | pitchboard
 * Response: {
 *   ok:         true,
 *   status:     "active" | "trialing" | "past_due" | "canceled" | "unpaid" | "none" | …,
 *   plan:       "core" | "pro" | ""  (DevBoard has Core and Pro; other apps only Pro)
 *   is_pro:     true | false   (Pro plan)
 *   is_core:    true | false   (any paid plan: Core or Pro)
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
$app     = trim($_POST['app'] ?? '');   // devboard | gameboard | pitchboard
if (!stripeValidSheetId($sheetId)) stripeFail('Invalid sheet id');
if ($app !== '' && !in_array($app, STRIPE_APPS, true)) stripeFail('Invalid app');

$portalUrl = $_ENV['STRIPE_PORTAL_URL'] ?? '';
$info      = stripeSheetPlan($sheetId, $app);

echo json_encode([
    'ok'         => true,
    'status'     => $info['status'],
    'plan'       => $info['plan'],
    'is_pro'     => $info['is_pro'],
    'is_core'    => $info['is_core'],
    'period_end' => $info['period_end'],
    'app'        => $info['app'],
    'portal_url' => $portalUrl,
]);
