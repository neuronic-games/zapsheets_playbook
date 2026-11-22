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
 * Writes/updates:  sheets/{sheet_id}/subscription-{app}.json  (one per app)
 *                  CardBoard: also the person's row in the GameBoard Admin sheet (metadata cb_email)
 * Format: {
 *   "email":                  "user@example.com",
 *   "status":                 "active" | "trialing" | "past_due" | "canceled" | "unpaid",
 *   "app":                    "devboard" | "billboard",
 *   "plan":                   "core" | "pro",
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
function writeSubscription(string $sheetId, array $data, string $app = ''): void {
    if (!stripeValidSheetId($sheetId)) return;
    $dir = dirname(__DIR__) . '/sheets/' . $sheetId;
    if (!is_dir($dir)) return;
    if ($app === 'all') {   // the all-in-one: Pro in every app on this sheet
        foreach (STRIPE_APPS as $one) writeSubscription($sheetId, array_merge($data, ['app' => $one], isset($data['plan']) ? ['plan' => 'pro'] : []), $one);
        return;
    }
    $file     = stripeSubFile($sheetId, $app);   // one file per app
    $existing = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
    $merged   = array_merge($existing, $data, ['updated_at' => gmdate('Y-m-d')]);
    file_put_contents($file, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/**
 * Record a subscription on the person's GameBoard account: the app's column in the Admin sheet (All Apps for the
 * all-in-one) holds the date the paid time runs to, "… core" for DevBoard Core, blank when it has ended.
 * CardBoard also keeps Status and Period End, as before the app columns.
 */
function gbRecordSub(string $email, string $app, string $status, string $periodEnd, string $plan = 'pro', array $extra = []): void {
    if (!$email) return;
    require_once __DIR__ . '/gameboardAuth.php';
    $col = $app === 'all' ? GB_ALL_COL : (array_key_exists($app, GB_APP_COLS) ? GB_APP_COLS[$app] : '');
    if ($col === '') return;
    $live   = in_array($status, STRIPE_LIVE_STATUSES, true);
    $fields = [$col => $live ? ($periodEnd ?: gmdate('Y-m-d', time() + 31 * 86400)) . ($plan === 'core' ? ' core' : '') : ''];
    if ($app === 'cardboard') $fields += ['Status' => $status, 'Period End' => $live ? $periodEnd : ''];
    cbAdmin(['action' => 'update', 'email' => $email, 'fields' => $fields + $extra]);
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
        $status  = $sub['status'] ?? 'active';
        $pe      = $sub ? stripePeriodEnd($sub) : '';
        $plan    = $sub ? stripePlanFromSub($sub, $app === 'all' ? '' : $app) : ($object['metadata']['plan'] ?? 'pro');

        writeSubscription($sheetId, [
            'email'                  => $email,
            'status'                 => $status,
            'app'                    => $app,
            'stripe_customer_id'     => $object['customer'] ?? '',
            'stripe_subscription_id' => $subId,
            'period_end'             => $pe,
            'plan'                   => $plan,
        ], $app);
        gbRecordSub($object['metadata']['cb_email'] ?? ($sub['metadata']['cb_email'] ?? ''), $app, $status, $pe, $plan,
                    ['Stripe Customer' => $object['customer'] ?? '', '+Stripe Subscription' => $subId]);
        break;

    case 'customer.subscription.updated':
        $app = $object['metadata']['app'] ?? '';
        gbRecordSub($object['metadata']['cb_email'] ?? '', $app, $object['status'] ?? 'unknown', stripePeriodEnd($object), stripePlanFromSub($object, $app === 'all' ? '' : $app));
        $sheetId = $object['metadata']['sheet_id'] ?? '';
        if (!$sheetId) break;
        writeSubscription($sheetId, [
            'status'     => $object['status'] ?? 'unknown',
            'period_end' => stripePeriodEnd($object),
            'plan'       => stripePlanFromSub($object, $app === 'all' ? '' : $app),
        ], $app);
        break;

    case 'customer.subscription.deleted':
        gbRecordSub($object['metadata']['cb_email'] ?? '', $object['metadata']['app'] ?? '', 'canceled', '');
        $sheetId = $object['metadata']['sheet_id'] ?? '';
        if (!$sheetId) break;
        writeSubscription($sheetId, [
            'status'     => 'canceled',
            'period_end' => '',
        ], $object['metadata']['app'] ?? '');
        break;

    case 'invoice.payment_failed':
        // 2025-03-31 "basil" and later: invoice.parent.subscription_details;
        // older API versions: invoice.subscription.
        // The account's app column keeps its date (past_due still works, and customer.subscription.updated
        // follows with the new state); only CardBoard's older Status column is changed here.
        $details = $object['parent']['subscription_details'] ?? [];
        $subId   = $details['subscription'] ?? ($object['subscription'] ?? '');
        if (is_array($subId)) $subId = $subId['id'] ?? '';
        $sheetId = $details['metadata']['sheet_id'] ?? '';
        $app     = $details['metadata']['app'] ?? '';
        if ((!$sheetId || !$app) && $subId) {
            $sub     = stripeRequest('GET', 'subscriptions/' . $subId);
            $sheetId = $sheetId ?: ($sub['metadata']['sheet_id'] ?? '');
            $app     = $app ?: ($sub['metadata']['app'] ?? '');
        }
        if ($app === 'cardboard') {
            $cbEmail = $details['metadata']['cb_email'] ?? '';
            if (!$cbEmail && $subId) { $sub = $sub ?? stripeRequest('GET', 'subscriptions/' . $subId); $cbEmail = $sub['metadata']['cb_email'] ?? ''; }
            if ($cbEmail) { require_once __DIR__ . '/gameboardAuth.php'; cbAdmin(['action' => 'update', 'email' => $cbEmail, 'fields' => ['Status' => 'past_due']]); }
        }
        if (!$sheetId) break;
        writeSubscription($sheetId, ['status' => 'past_due'], $app);
        break;
}

http_response_code(200);
echo json_encode(['ok' => true]);
