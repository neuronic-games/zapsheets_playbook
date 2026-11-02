<?php
/**
 * stripeWebhook.php — receive Stripe events and update subscription.json.
 *
 * Register this URL in your Stripe dashboard (Developers → Webhooks), e.g.:
 *   https://<your-domain>/<base-path>/push/stripeWebhook.php
 * and copy its signing secret into STRIPE_WEBHOOK_SECRET in .env.
 *
 * Events to enable:
 *   checkout.session.completed
 *   customer.subscription.updated
 *   customer.subscription.deleted
 *   invoice.payment_failed
 *
 * Writes/updates:  sheets/{sheet_id}/subscription.json
 * Format: {
 *   "email":                  "user@example.com",
 *   "status":                 "active" | "trialing" | "past_due" | "canceled" | "unpaid",
 *   "app":                    "devboard" | "gameboard",
 *   "stripe_customer_id":     "cus_xxx",
 *   "stripe_subscription_id": "sub_xxx",
 *   "period_end":             "2026-12-01",
 *   "updated_at":             "2026-11-01"
 * }
 * Donations (one-time checkout sessions) are recorded by Stripe only and
 * never change the Pro status.
 */
error_reporting(0);
header('Content-Type: application/json');
require_once __DIR__ . '/stripeCommon.php';

const STRIPE_SIG_TOLERANCE = 300; // seconds

$webhookSecret = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? '';
$payload       = file_get_contents('php://input');
$sigHeader     = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// ── Signature verification (fails closed) ────────────────────────────────
function stripeVerifySignature(string $payload, string $sigHeader, string $secret): bool {
    if ($secret === '' || $sigHeader === '') return false;
    $timestamp = '';
    $sigs      = [];
    foreach (explode(',', $sigHeader) as $part) {
        [$k, $v] = array_pad(explode('=', $part, 2), 2, '');
        $k = trim($k); $v = trim($v);
        if ($k === 't')  $timestamp = $v;
        if ($k === 'v1') $sigs[]    = $v;
    }
    if (!ctype_digit($timestamp) || !$sigs) return false;
    if (abs(time() - (int) $timestamp) > STRIPE_SIG_TOLERANCE) return false; // replay guard
    $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    foreach ($sigs as $sig) {
        if (hash_equals($expected, $sig)) return true;
    }
    return false;
}

if ($webhookSecret === '') {
    // Not configured: refuse everything (Stripe will retry once it is set up).
    stripeFail('Webhook not configured', 500);
}
if (!stripeVerifySignature($payload, $sigHeader, $webhookSecret)) {
    stripeFail('Invalid signature', 400);
}

$event = json_decode($payload, true);
if (!is_array($event)) stripeFail('Invalid payload', 400);

// ── Helper: write subscription.json ──────────────────────────────────────
function writeSubscription(string $sheetId, array $data): void {
    if (!stripeValidSheetId($sheetId)) return;
    $dir = dirname(__DIR__) . '/sheets/' . $sheetId;
    if (!is_dir($dir)) return;
    $file     = $dir . '/subscription.json';
    $existing = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
    $merged   = array_merge($existing, $data, ['updated_at' => gmdate('Y-m-d')]);
    file_put_contents($file, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

// ── Event dispatch ────────────────────────────────────────────────────────
$type   = $event['type']           ?? '';
$object = $event['data']['object'] ?? [];

switch ($type) {

    case 'checkout.session.completed':
        // Donations use mode=payment — they must never grant Pro.
        if (($object['mode'] ?? '') !== 'subscription') break;
        if (!in_array($object['payment_status'] ?? '', ['paid', 'no_payment_required'], true)) break;

        $subId  = $object['subscription'] ?? '';
        $sub    = $subId ? stripeRequest('GET', 'subscriptions/' . $subId) : [];
        if (!empty($sub['error'])) $sub = [];

        $sheetId = $object['metadata']['sheet_id'] ?? ($sub['metadata']['sheet_id'] ?? '');
        $app     = $object['metadata']['app']      ?? ($sub['metadata']['app']      ?? '');
        $email   = $object['customer_details']['email'] ?? ($object['customer_email'] ?? '');

        writeSubscription($sheetId, [
            'email'                  => $email,
            'status'                 => $sub['status'] ?? 'active',
            'app'                    => $app,
            'stripe_customer_id'     => $object['customer'] ?? '',
            'stripe_subscription_id' => $subId,
            'period_end'             => $sub ? stripePeriodEnd($sub) : '',
        ]);
        break;

    case 'customer.subscription.updated':
        $sheetId = $object['metadata']['sheet_id'] ?? '';
        if (!$sheetId) break;
        writeSubscription($sheetId, [
            'status'     => $object['status'] ?? 'unknown',
            'period_end' => stripePeriodEnd($object),
        ]);
        break;

    case 'customer.subscription.deleted':
        $sheetId = $object['metadata']['sheet_id'] ?? '';
        if (!$sheetId) break;
        writeSubscription($sheetId, [
            'status'     => 'canceled',
            'period_end' => '',
        ]);
        break;

    case 'invoice.payment_failed':
        // 2025-03-31 "basil" and later: invoice.parent.subscription_details;
        // older API versions: invoice.subscription.
        $details = $object['parent']['subscription_details'] ?? [];
        $subId   = $details['subscription'] ?? ($object['subscription'] ?? '');
        if (is_array($subId)) $subId = $subId['id'] ?? '';
        $sheetId = $details['metadata']['sheet_id'] ?? '';
        if (!$sheetId && $subId) {
            $sub     = stripeRequest('GET', 'subscriptions/' . $subId);
            $sheetId = $sub['metadata']['sheet_id'] ?? '';
        }
        if (!$sheetId) break;
        writeSubscription($sheetId, ['status' => 'past_due']);
        break;
}

http_response_code(200);
echo json_encode(['ok' => true]);
