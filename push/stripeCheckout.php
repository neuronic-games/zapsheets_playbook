<?php
/**
 * stripeCheckout.php — create a Stripe Checkout session for a Pro
 * subscription or a one-time donation.
 *
 * POST params:
 *   type        — 'subscription' (default) | 'donation'
 *   id          — sheet ID (stored in metadata so the webhook can find it)
 *   app         — 'devboard' | 'gameboard' | 'pitchboard' | 'cardboard'
 *   plan        — 'pro' (default) | 'core' (DevBoard only)
 *   email       — pre-fill customer email (optional)
 *   success_url — page to return to on success (must be on this site)
 *   cancel_url  — page to return to on cancel  (must be on this site)
 *
 * Prices (in .env):
 *   STRIPE_PRICE_ID_DEVBOARD / _GAMEBOARD / _PITCHBOARD / _CARDBOARD — recurring Pro price
 *   STRIPE_PRICE_ID_DEVBOARD_CORE — recurring DevBoard Core price
 *   STRIPE_PRICE_ID_DONATION — one-time price set to "Customer chooses what to pay"
 *
 * Response: { ok, url } — redirect the browser there
 */
error_reporting(0);
header('Content-Type: application/json');
require_once __DIR__ . '/stripeCommon.php';

$type    = (($_POST['type'] ?? '') === 'donation') ? 'donation' : 'subscription';
$sheetId = trim($_POST['id']    ?? '');
$app     = trim($_POST['app']   ?? '');
$email   = trim($_POST['email'] ?? '');
$plan    = (($_POST['plan'] ?? '') === 'core' && $app === 'devboard') ? 'core' : 'pro';

if (!stripeSecretKey())                     stripeFail('Payments are not configured yet');
if (!stripeValidSheetId($sheetId))          stripeFail('Invalid sheet id');
if (!in_array($app, STRIPE_APPS, true))     stripeFail('Invalid app');

$priceId = $type === 'donation' ? ($_ENV['STRIPE_PRICE_ID_DONATION'] ?? '') : stripePlanPriceId($app, $plan);
if (!$priceId) {
    stripeFail($type === 'donation' ? 'Donations are not configured yet' : "No Stripe price configured for $app" . ($plan === 'core' ? ' Core' : ''));
}

$fallback = stripeAppUrl();
$params = [
    'line_items[0][price]'    => $priceId,
    'line_items[0][quantity]' => '1',
    'success_url'             => stripeSafeReturnUrl($_POST['success_url'] ?? '', $fallback),
    'cancel_url'              => stripeSafeReturnUrl($_POST['cancel_url']  ?? '', $fallback),
    'metadata[sheet_id]'      => $sheetId,
    'metadata[app]'           => $app,
    'metadata[type]'          => $type,
];
if ($type === 'donation') {
    $params['mode']        = 'payment';
    $params['submit_type'] = 'donate';
} else {
    $params['mode']                                   = 'subscription';
    $params['allow_promotion_codes']                  = 'true';
    $params['subscription_data[metadata][sheet_id]']  = $sheetId;
    $params['subscription_data[metadata][app]']       = $app;
    $params['subscription_data[metadata][plan]']      = $plan;
    $params['metadata[plan]']                         = $plan;
}
if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $params['customer_email'] = $email;
}

$result = stripeRequest('POST', 'checkout/sessions', $params);
if (!empty($result['error'])) stripeFail($result['error']['message'] ?? 'Stripe error');
if (empty($result['url']))    stripeFail('Stripe did not return a checkout page');

echo json_encode(['ok' => true, 'url' => $result['url']]);
