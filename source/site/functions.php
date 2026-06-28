<?php
/**
 * Shared helpers for index.php and game.php.
 * Loaded via require_once so they are defined only once per PHP process,
 * even when the built-in server reuses a process across requests.
 */

function validHex(string $h): string {
    $h = ltrim(trim($h), '#');
    if (preg_match('/^[0-9a-fA-F]{3}$/', $h)) $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
    return preg_match('/^[0-9a-fA-F]{6}$/', $h) ? '#'.strtolower($h) : '';
}

function hexRgb(string $h): ?array {
    $h = ltrim($h, '#'); if (strlen($h) !== 6) return null;
    return [hexdec(substr($h,0,2)), hexdec(substr($h,2,2)), hexdec(substr($h,4,2))];
}

function hexRgba(string $h, float $a): string {
    $r = hexRgb($h); if (!$r) return "rgba(0,0,0,$a)";
    return "rgba({$r[0]},{$r[1]},{$r[2]},$a)";
}

function lighten(string $h, float $t): string {
    $r = hexRgb($h); if (!$r) return $h;
    return sprintf('#%02x%02x%02x',
        min(255, (int)round($r[0] + (255-$r[0])*$t)),
        min(255, (int)round($r[1] + (255-$r[1])*$t)),
        min(255, (int)round($r[2] + (255-$r[2])*$t)));
}

function darken(string $h, float $t): string {
    $r = hexRgb($h); if (!$r) return $h;
    return sprintf('#%02x%02x%02x',
        max(0, (int)round($r[0]*(1-$t))),
        max(0, (int)round($r[1]*(1-$t))),
        max(0, (int)round($r[2]*(1-$t))));
}

function luma(string $h): float {
    $r = hexRgb($h); if (!$r) return 0;
    return 0.299*$r[0] + 0.587*$r[1] + 0.114*$r[2];
}

/**
 * Returns a local cache path if cachemedia.py has downloaded the file,
 * otherwise returns the original URL.
 * Caller must set global $_cacheDir before calling.
 */
function cachedUrl(string $url): string {
    global $_cacheDir;
    if ($url === '' || !str_starts_with($url, 'http')) return $url;
    $ext   = strtolower(pathinfo(strtok($url, '?'), PATHINFO_EXTENSION)) ?: 'bin';
    $fname = md5($url) . '.' . $ext;
    return file_exists($_cacheDir . '/' . $fname) ? '../cache/' . $fname : $url;
}

function statusInfo(string $s): array {
    $s = strtolower(trim($s));
    if ($s === 'published') return ['Published', 'published', '#4ade80'];
    if ($s === 'signed')    return ['Signed',    'signed',   '#c084fc'];
    if ($s === 'available') return ['Available', 'available','#60a5fa'];
    if (str_contains($s, 'progress') || str_contains($s, 'development'))
                            return ['In Development', 'dev', '#9ca3af'];
    return [ucfirst($s) ?: 'In Development', 'dev', '#9ca3af'];
}

function esc($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
