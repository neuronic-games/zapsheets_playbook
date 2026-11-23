<?php
/**
 * stripeCheckout.php — create a Stripe Checkout session for a Pro
 * subscription or a one-time donation.
 *
 * POST params:
 *   type        — 'subscription' (default) | 'donation'
 *   id          — sheet ID (stored in metadata so the webhook can find it; optional for the all-in-one)
 *   app         — 'devboard' | 'billboard' | 'pitchboard' | 'cardboard' | 'all' (the all-in-one: every app)
 *   plan        — 'pro' (default) | 'core' (DevBoard only)
 *   email       — pre-fill customer email (optional)
 *   cbt         — GameBoard account token: the subscription is recorded on that account (its app column in the
 *                 Admin sheet). Needed for CardBoard and the all-in-one; used by the other apps when given.
 *   success_url — page to return to on success (must be on this site)
 *   cancel_url  — page to return to on cancel  (must be on this site)
 *
 * Prices (in .env):
 *   STRIPE_PRICE_ID_DEVBOARD / _BILLBOARD / _PITCHBOARD / _CARDBOARD — recurring Pro price
 *   STRIPE_PRICE_ID_DEVBOARD_CORE — recurring DevBoard Core price
 *   STRIPE_PRICE_ID_ALL — recurring all-in-one price ($9.99)
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
if (!in_array($app, STRIPE_APPS, true) && !($app === 'all' && $type === 'subscription')) stripeFail('Invalid app');
if (!stripeValidSheetId($sheetId) && !($app === 'all' && $sheetId === '')) stripeFail('Invalid sheet id');

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
// The subscription is recorded on the signed-in GameBoard account (its row in the Admin sheet). CardBoard and the
// all-in-one need an account; the other apps still work per sheet, and add the account when someone is signed in.
if ($type === 'subscription') {
    require_once __DIR__ . '/gameboardAuth.php';
    $who = cbReadToken(cbRequestToken());
    if (!$who && ($app === 'cardboard' || $app === 'all')) stripeFail('Sign in with your GameBoard account first');
    if ($who && $app === 'all' && !empty($who['all'])) stripeFail('You already have the all-in-one — every app is yours');
    if ($who) {
        $params['customer_email']                          = $who['e'];
        $params['metadata[cb_email]']                      = $who['e'];
        $params['subscription_data[metadata][cb_email]']   = $who['e'];
    }
}

$result = stripeRequest('POST', 'checkout/sessions', $params);
if (!empty($result['error'])) stripeFail($result['error']['message'] ?? 'Stripe error');
if (empty($result['url']))    stripeFail('Stripe did not return a checkout page');

echo json_encode(['ok' => true, 'url' => $result['url']]);
