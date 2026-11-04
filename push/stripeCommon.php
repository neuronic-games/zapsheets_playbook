<?php
/**
 * stripeCommon.php — shared helpers for the Stripe endpoints
 * (stripeCheckout.php, stripeWebhook.php, getSubscription.php).
 */
$_bpFile = __DIR__ . '/../dotEnv.php';
if (file_exists($_bpFile)) require_once $_bpFile;

const STRIPE_APPS = ['devboard', 'gameboard', 'pitchboard'];

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
