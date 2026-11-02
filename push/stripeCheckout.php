<?php
/**
 * stripeCheckout.php — create a Stripe Checkout session for a Pro
 * subscription or a one-time donation.
 *
 * POST params:
 *   type        — 'subscription' (default) | 'donation'
 *   id          — sheet ID (stored in metadata so the webhook can find it)
 *   app         — 'devboard' | 'gameboard'
 *   email       — pre-fill customer email (optional)
 *   success_url — page to return to on success (must be on this site)
 *   cancel_url  — page to return to on cancel  (must be on this site)
 *
 * Prices (in .env):
 *   STRIPE_PRICE_ID_DEVBOARD / STRIPE_PRICE_ID_GAMEBOARD — recurring Pro price
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

if (!stripeSecretKey())                     stripeFail('Payments are not configured yet');
if (!stripeValidSheetId($sheetId))          stripeFail('Invalid sheet id');
if (!in_array($app, STRIPE_APPS, true))     stripeFail('Invalid app');

$priceKey = $type === 'donation' ? 'STRIPE_PRICE_ID_DONATION' : 'STRIPE_PRICE_ID_' . strtoupper($app);
$priceId  = $_ENV[$priceKey] ?? '';
if (!$priceId) {
    stripeFail($type === 'donation' ? 'Donations are not configured yet' : "No Stripe price configured for $app");
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
}
if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $params['customer_email'] = $email;
}

$result = stripeRequest('POST', 'checkout/sessions', $params);
if (!empty($result['error'])) stripeFail($result['error']['message'] ?? 'Stripe error');
if (empty($result['url']))    stripeFail('Stripe did not return a checkout page');

echo json_encode(['ok' => true, 'url' => $result['url']]);
