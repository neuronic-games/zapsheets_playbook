<?php
/**
 * stripeCommon.php — shared helpers for the Stripe endpoints
 * (stripeCheckout.php, stripeWebhook.php, getSubscription.php).
 */
$_bpFile = __DIR__ . '/../dotEnv.php';
if (file_exists($_bpFile)) require_once $_bpFile;

const STRIPE_APPS = ['devboard', 'billboard', 'pitchboard', 'cardboard'];

/** Statuses that count as a paid, working subscription. */
const STRIPE_LIVE_STATUSES = ['active', 'trialing', 'past_due'];

/**
 * Plans. DevBoard has two: 'core' (STRIPE_PRICE_ID_DEVBOARD_CORE) and
 * 'pro' (STRIPE_PRICE_ID_DEVBOARD). Other apps only have 'pro'.
 * app 'all' is the all-in-one (STRIPE_PRICE_ID_ALL): every app, Pro.
 */
function stripePlanPriceId(string $app, string $plan): string {
    if ($app === 'all') return $_ENV['STRIPE_PRICE_ID_ALL'] ?? '';   // the all-in-one: every app
    $key = 'STRIPE_PRICE_ID_' . strtoupper($app) . ($plan === 'core' ? '_CORE' : '');
    return $_ENV[$key] ?? '';
}

/** Work out the plan from a Stripe subscription's price (handles plan switches in the portal). */
function stripePlanFromSub(array $sub, string $app = ''): string {
    $app = $app ?: ($sub['metadata']['app'] ?? '');
    $core = $app ? stripePlanPriceId($app, 'core') : '';
    foreach ($sub['items']['data'] ?? [] as $item) {
        if ($core && ($item['price']['id'] ?? '') === $core) return 'core';
    }
    return $sub['metadata']['plan'] ?? 'pro';
}

/**
 * Subscription info for a sheet: [status, plan ('core'|'pro'|''), is_pro, is_core, period_end, app].
 * Sheets listed in STRIPE_PRO_SHEET_IDS (comma separated) always get Pro — for your own or comped sheets.
 */
/**
 * Each app's subscription is kept in sheets/{id}/subscription-{app}.json, so DevBoard,
 * PitchBoard and BillBoard on the same sheet don't overwrite each other.
 * subscription.json is the older single file; it still counts for the app it names.
 */
function stripeSubFile(string $sheetId, string $app = ''): string {
    $dir = dirname(__DIR__) . '/sheets/' . $sheetId;
    return $dir . (in_array($app, STRIPE_APPS, true) ? '/subscription-' . $app . '.json' : '/subscription.json');
}

function stripeReadSub(string $sheetId, string $app = ''): array {
    if (!stripeValidSheetId($sheetId)) return [];
    $file = stripeSubFile($sheetId, $app);
    if (file_exists($file)) return json_decode(file_get_contents($file), true) ?: [];
    $legacy = stripeSubFile($sheetId);
    $data   = file_exists($legacy) ? (json_decode(file_get_contents($legacy), true) ?: []) : [];
    if ($app && !empty($data['app']) && $data['app'] !== $app) return [];   // that one belongs to another app
    return $data;
}

function stripeSheetPlan(string $sheetId, string $app = ''): array {
    $data   = stripeReadSub($sheetId, $app);
    $status = $data['status'] ?? 'none';
    $live   = in_array($status, STRIPE_LIVE_STATUSES, true);
    $plan   = $live ? (($data['plan'] ?? 'pro') === 'core' ? 'core' : 'pro') : '';
    $comped = array_filter(array_map('trim', explode(',', $_ENV['STRIPE_PRO_SHEET_IDS'] ?? '')));
    if (in_array($sheetId, $comped, true)) { $plan = 'pro'; $live = true; if ($status === 'none') $status = 'active'; }
    $activated = activationActive($sheetId);
    if ($activated) { $plan = 'pro'; $live = true; if (!in_array($status, STRIPE_LIVE_STATUSES, true)) $status = 'active'; }
    return [
        'status'     => $status,
        'plan'       => $plan,
        'is_pro'     => $plan === 'pro',
        'is_core'    => $live,
        'period_end' => $data['period_end'] ?? '',
        'app'        => $data['app'] ?? '',
        'activated'  => $activated,
    ];
}

/**
 * Activation keys: a key entered in any app's plans dialog unlocks Pro in every app
 * for that sheet. Stored as sheets/{id}/activation.json. Valid keys are compared by
 * SHA-256 of the lower-cased key: ACTIVATION_KEY_HASHES in .env (comma separated)
 * replaces the built-in list.
 */
const ACTIVATION_KEY_HASHES = ['3e71e46d644e6274b1967c05bcbf7f4358722909316ba04501c50035c8f67d39'];

function activationFile(string $sheetId): string { return dirname(__DIR__) . '/sheets/' . $sheetId . '/activation.json'; }

function activationActive(string $sheetId): bool {
    return stripeValidSheetId($sheetId) && is_file(activationFile($sheetId));
}

function activationKeyValid(string $key): bool {
    $hashes = array_filter(array_map('trim', explode(',', strtolower($_ENV['ACTIVATION_KEY_HASHES'] ?? ''))));
    if (!$hashes) $hashes = ACTIVATION_KEY_HASHES;
    $h = hash('sha256', strtolower(trim($key)));
    foreach ($hashes as $ok) if (hash_equals($ok, $h)) return true;
    return false;
}

/** Stop a DevBoard Pro-only endpoint (contracts, estimates, invoices) for sheets without Pro. */
function devboardRequirePro(string $sheetId): void {
    if (stripeSheetPlan($sheetId, 'devboard')['is_pro']) return;
    echo json_encode(['error' => 'Contracts, estimates and invoices are part of DevBoard Pro. Upgrade to Pro to use them.', 'need_pro' => true]);
    exit;
}

function stripeFail(string $msg, int $code = 200): void {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

function stripeSecretKey(): string {
    return $_ENV['STRIPE_SECRET_KEY'] ?? '';
}

function stripeValidSheetId(string $id): bool {
    return (bool) preg_match('/^[A-Za-z0-9_-]{1,128}$/', $id);
}

/** Call the Stripe API. Returns the decoded JSON, or ['error' => ['message' => …]]. */
function stripeRequest(string $method, string $path, array $params = []): array {
    $key = stripeSecretKey();
    if (!$key) return ['error' => ['message' => 'Stripe not configured']];
    $url = 'https://api.stripe.com/v1/' . $path;
    if ($method === 'GET' && $params) $url .= '?' . http_build_query($params);
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $key . ':',
        CURLOPT_TIMEOUT        => 20,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false || $err) return ['error' => ['message' => 'Network error: ' . $err]];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : ['error' => ['message' => 'Invalid response from Stripe']];
}

/**
 * Billing period end as YYYY-MM-DD. Works with both the older API
 * (subscription.current_period_end) and the 2025-03-31 "basil" API and later
 * (period end lives on each subscription item).
 */
function stripePeriodEnd(array $sub): string {
    $ts = (int) ($sub['current_period_end'] ?? 0);
    foreach ($sub['items']['data'] ?? [] as $item) {
        $ts = max($ts, (int) ($item['current_period_end'] ?? 0));
    }
    return $ts ? gmdate('Y-m-d', $ts) : '';
}

function stripeRequestHost(): string {
    return strtolower($_SERVER['HTTP_HOST'] ?? '');
}

/** Absolute URL of the app root for this request (fallback return URL). */
function stripeAppUrl(): string {
    $host = stripeRequestHost();
    if (!$host) return rtrim($_ENV['APP_DOMAIN'] ?? 'https://zapsheets.com', '/') . '/';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $bp = trim($_ENV['BASE_PATH'] ?? '/', '/');
    return ($https ? 'https' : 'http') . '://' . $host . '/' . ($bp !== '' ? $bp . '/' : '');
}

/** Only allow return URLs on this site; anything else falls back to the app root. */
function stripeSafeReturnUrl(string $url, string $fallback): string {
    $p = parse_url(trim($url));
    if (!$p || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true)) return $fallback;
    $host = strtolower($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '');
    if ($host === '' || $host !== stripeRequestHost()) return $fallback;
    return trim($url);
}
