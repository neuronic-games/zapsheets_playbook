<?php
/**
 * cardboardImage.php — fetch a picture for CardBoard's Export tab.
 *
 * Card sheets are drawn in the browser, which can only read pictures served
 * from our own site. This passes a Dropbox / Google Drive / web picture
 * through, fresh every time — nothing is stored on the server.
 *
 * GET params:
 *   id  — Google Spreadsheet ID (must be a sheet this app knows)
 *   url — the picture's http(s) address
 */
error_reporting(0);
ini_set('display_errors', '0');

function fail($code, $msg) { http_response_code($code); header('Content-Type: text/plain'); echo $msg; exit; }

$sheetId = $_GET['id'] ?? '';
if (!preg_match('/^[A-Za-z0-9_\-]{20,}$/', $sheetId) || !is_dir(dirname(__DIR__) . '/sheets/' . $sheetId)) fail(403, 'Unknown sheet');

$url = trim($_GET['url'] ?? '');
if (!preg_match('#^https?://#i', $url) || strlen($url) > 2048) fail(400, 'Bad address');

// Only public addresses — never this server's network. Returns the IP to connect to.
function publicIp($u) {
    $host = parse_url($u, PHP_URL_HOST);
    if (!$host) return false;
    $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
    if (!$ips) return false;
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
    }
    return $ips[0];
}

$max = 20 * 1024 * 1024;
for ($hop = 0; $hop < 5; $hop++) {
    $ip = publicIp($url);
    if (!$ip) fail(400, 'That address is not allowed');
    $p = parse_url($url);
    $port = $p['port'] ?? (strtolower($p['scheme']) === 'https' ? 443 : 80);
    $body = ''; $tooBig = false;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RESOLVE        => [$p['host'] . ':' . $port . ':' . $ip],   // connect to the address we checked
        CURLOPT_FOLLOWLOCATION => false,           // redirects are checked one by one below
        CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 25,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (CardBoard export)',
        CURLOPT_WRITEFUNCTION  => function ($ch, $chunk) use (&$body, &$tooBig, $max) {
            $body .= $chunk;
            if (strlen($body) > $max) { $tooBig = true; return 0; }
            return strlen($chunk);
        },
    ]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $type   = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $next   = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    if ($tooBig) fail(413, 'Picture is over 20 MB');
    if ($status >= 300 && $status < 400 && $next) { $url = $next; continue; }
    break;
}
if ($status !== 200) fail(502, 'The picture could not be loaded (' . $status . ')');

// Check it really is a picture
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->buffer($body);
if (!in_array($mime, ['image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/svg+xml'], true)) {
    fail(415, 'That address is not a picture' . (stripos($type, 'html') !== false ? ' (it is a web page — check the sharing setting)' : ''));
}
header('Content-Type: ' . $mime);
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if ($mime === 'image/svg+xml') header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
echo $body;
