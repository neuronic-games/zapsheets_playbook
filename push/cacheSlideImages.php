<?php
/**
 * push/cacheSlideImages.php
 *
 * Downloads slide images from external URLs (Dropbox, etc.) and caches them
 * server-side in sheets/{id}/cache/ so the slides page can serve them from
 * the same origin — faster loads, no cross-origin issues, and simpler SW caching.
 *
 * POST params:
 *   id    – sheet ID (alphanumeric + underscore/dash)
 *   urls  – JSON-encoded array of image URLs to cache
 *
 * Returns JSON: [{photo: originalUrl, cached: "sheets/{id}/cache/{hash}.{ext}"}]
 *   cached is null if the download failed.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');

// ── Validate inputs ──────────────────────────────────────────────────────────

$id = preg_replace('/[^A-Za-z0-9_\-]/', '', $_POST['id'] ?? $_GET['id'] ?? '');
if (!$id) {
    echo json_encode(['error' => 'Missing id']);
    exit;
}

$urlsRaw = $_POST['urls'] ?? $_GET['urls'] ?? '[]';
$urls    = json_decode($urlsRaw, true);
if (!is_array($urls) || empty($urls)) {
    echo json_encode([]);
    exit;
}

// Safety cap
$urls = array_slice($urls, 0, 30);

// ── Ensure cache directory exists ────────────────────────────────────────────

$cacheDir = __DIR__ . '/../sheets/' . $id . '/cache/';
if (!is_dir($cacheDir)) {
    if (!mkdir($cacheDir, 0755, true)) {
        echo json_encode(['error' => 'Cannot create cache directory']);
        exit;
    }
}

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Guess a file extension from a Content-Type header or the URL path.
 */
function guessExt(string $url, string $contentType = ''): string {
    if ($contentType) {
        $ct  = strtolower(trim(explode(';', $contentType)[0]));
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg'  => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
            'image/avif' => 'avif',
        ];
        if (isset($map[$ct])) return $map[$ct];
    }
    // Fall back to URL path extension
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $ok   = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];
    if (in_array($ext, $ok)) return $ext === 'jpeg' ? 'jpg' : $ext;
    return 'jpg';
}

/**
 * Download a URL and return ['data' => ..., 'contentType' => ...] or null on failure.
 * Follows redirects (needed for Dropbox dl=1 links).
 */
function curlFetch(string $url, bool $verifySsl): ?array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        CURLOPT_SSL_VERIFYPEER => $verifySsl,
        CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
        CURLOPT_HEADER         => false,
        CURLOPT_ENCODING       => '',   // accept any Content-Encoding
    ]);
    $data        = curl_exec($ch);
    $httpCode    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($data === false || strlen($data) < 64 || $httpCode !== 200) return null;
    // Reject HTML responses (happens when Dropbox redirects to a login page)
    $ct = strtolower(trim(explode(';', $contentType)[0]));
    if ($ct === 'text/html' || $ct === 'application/json') return null;
    return ['data' => $data, 'contentType' => $contentType];
}

function downloadUrl(string $url): ?array {
    // Prefer cURL — handles redirects and SSL reliably
    if (function_exists('curl_init')) {
        // Try with SSL verification first; fall back without it if the server's
        // CA bundle is outdated (common on shared hosts).
        $result = curlFetch($url, true);
        if (!$result) $result = curlFetch($url, false);
        if ($result) return $result;
    }

    // Fallback: file_get_contents
    if (!ini_get('allow_url_fopen')) return null;
    $ctx  = stream_context_create([
        'http' => [
            'timeout'         => 25,
            'follow_location' => 1,
            'max_redirects'   => 10,
            'user_agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
        ],
        'ssl'  => ['verify_peer' => true],
    ]);
    $data = @file_get_contents($url, false, $ctx);
    if (!$data || strlen($data) < 64) return null;

    $contentType = '';
    foreach ($http_response_header ?? [] as $h) {
        if (stripos($h, 'Content-Type:') === 0) {
            $contentType = trim(substr($h, 13));
            break;
        }
    }
    return ['data' => $data, 'contentType' => $contentType];
}

// ── Process each URL ─────────────────────────────────────────────────────────

$results = [];

foreach ($urls as $photo) {
    $photo = is_string($photo) ? trim($photo) : '';
    if (!$photo || !filter_var($photo, FILTER_VALIDATE_URL)) {
        $results[] = ['photo' => $photo, 'cached' => null];
        continue;
    }

    $hash = md5($photo);

    // Check if already cached (any extension — glob for the hash prefix)
    $existing = glob($cacheDir . $hash . '.*');
    if ($existing) {
        $results[] = [
            'photo'  => $photo,
            'cached' => 'sheets/' . $id . '/cache/' . basename($existing[0]),
        ];
        continue;
    }

    // Download and cache
    $dl = downloadUrl($photo);
    if (!$dl) {
        $results[] = ['photo' => $photo, 'cached' => null];
        continue;
    }

    $ext       = guessExt($photo, $dl['contentType']);
    $cacheFile = $cacheDir . $hash . '.' . $ext;
    if (file_put_contents($cacheFile, $dl['data']) !== false) {
        $results[] = [
            'photo'  => $photo,
            'cached' => 'sheets/' . $id . '/cache/' . $hash . '.' . $ext,
        ];
    } else {
        $results[] = ['photo' => $photo, 'cached' => null];
    }
}

// ── Write / merge cache index ────────────────────────────────────────────────
// index.json maps original URL → cached relative path so that the view page
// and sellsheet can resolve the correct md5-based filename without md5 in JS.
$indexFile = $cacheDir . 'index.json';
$index     = [];
if (file_exists($indexFile)) {
    $raw = @file_get_contents($indexFile);
    if ($raw) $index = json_decode($raw, true) ?: [];
}
foreach ($results as $r) {
    if (!empty($r['cached'])) $index[$r['photo']] = $r['cached'];
}
@file_put_contents($indexFile, json_encode($index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

echo json_encode($results);
