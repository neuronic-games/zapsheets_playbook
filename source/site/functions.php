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

/**
 * Render a plain-text string as safe HTML, converting:
 *   [label](https://...)  → <a href="...">label</a>   (markdown link)
 *   https://...           → <a href="...">url</a>      (bare URL)
 * Everything else is HTML-escaped; newlines become <br>.
 * Links open in a new tab with rel="noopener".
 */
function textWithLinks(string $text, string $cssClass = ''): string {
    if ($text === '') return '';

    $cls  = $cssClass !== '' ? ' class="' . esc($cssClass) . '"' : '';
    $attr = ' target="_blank" rel="noopener"' . $cls;

    // Matches (in priority order):
    //   1. [title, https://...]    bracket-comma format
    //   2. [label](https://...)    markdown format (from gread.py hyperlink export)
    //   3. https://...             bare URL
    $pattern = '/\[([^\],]+),\s*(https?:\/\/[^\]]+)\]|\[([^\]]*)\]\((https?:\/\/[^)]+)\)|https?:\/\/\S+/u';

    $out  = '';
    $last = 0;
    $len  = strlen($text);

    preg_match_all($pattern, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

    foreach ($matches as $m) {
        $offset = $m[0][1];
        $raw    = $m[0][0];

        // Plain text before this match
        if ($offset > $last) {
            $out .= nl2br(esc(substr($text, $last, $offset - $last)));
        }

        if (isset($m[1]) && $m[1][1] !== -1) {
            // Format 1: [title, url]
            $label = trim($m[1][0]);
            $url   = trim($m[2][0]);
        } elseif (isset($m[3]) && $m[3][1] !== -1) {
            // Format 2: [label](url)
            $label = $m[3][0];
            $url   = $m[4][0];
        } else {
            // Format 3: bare URL — strip trailing punctuation
            $raw   = rtrim($raw, '.,;:!?)\'\"');
            $label = $raw;
            $url   = $raw;
        }

        $out .= '<a href="' . esc($url) . '"' . $attr . '>' . esc($label) . '</a>';
        $last = $offset + strlen($m[0][0]);
    }

    // Remaining text after last match
    if ($last < $len) {
        $out .= nl2br(esc(substr($text, $last)));
    }

    return $out;
}
