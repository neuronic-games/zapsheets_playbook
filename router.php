<?php
/**
 * Router for PHP built-in server.
 * Start the server with:   php -S 0.0.0.0:8000 router.php
 * Or just run:             ./start.sh
 *
 * Short-URL mappings handled here:
 *   /{id}/view        → /sheets/{id}/view/index.html
 *   /{id}/view/       → /sheets/{id}/view/index.html
 *   /{id}/view/...    → /sheets/{id}/view/...
 *   /{id}             → /sheets/{id}/index.html   (same as /sheets/{id}/)
 *   /{id}/            → /sheets/{id}/index.html
 *   /{id}/...         → /sheets/{id}/...
 *
 * Everything else falls through to normal static/PHP serving.
 */

$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// MIME map shared by both routing blocks
$MIME = [
    'html'  => 'text/html; charset=UTF-8',
    'css'   => 'text/css',
    'js'    => 'application/javascript',
    'json'  => 'application/json',
    'png'   => 'image/png',
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'webp'  => 'image/webp',
    'gif'   => 'image/gif',
    'svg'   => 'image/svg+xml',
    'woff2' => 'font/woff2',
    'woff'  => 'font/woff',
    'ttf'   => 'font/ttf',
    'pdf'   => 'application/pdf',
];

/**
 * Serve a file with the correct Content-Type and exit.
 */
function serveFile(string $path, array $mime): void {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext === 'php') {
        // Execute PHP files rather than dumping source
        chdir(dirname($path));
        include $path;
        exit;
    }
    if (isset($mime[$ext])) {
        header('Content-Type: ' . $mime[$ext]);
    }
    readfile($path);
    exit;
}

/**
 * Show a friendly "not initialised" error page.
 */
function sheetNotFound(string $id): void {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Sheet not found</title>'
       . '<style>body{font-family:sans-serif;padding:2rem;background:#f5f5f5}'
       . 'h2{color:#c0392b}code{background:#eee;padding:.1em .4em;border-radius:3px}'
       . 'a{color:#2980b9}</style></head><body>'
       . '<h2>Sheet not initialised</h2>'
       . '<p>No sheet found for <code>' . htmlspecialchars($id) . '</code>.</p>'
       . '<p>Initialise it first:<br>'
       . '<a href="/push/?id=' . urlencode($id) . '">/push/?id=' . htmlspecialchars($id) . '</a></p>'
       . '</body></html>';
    exit;
}

// Top-level directories / files that must never be rewritten
$RESERVED = [
    'js', 'css', 'fonts', 'images', 'sheets', 'source',
    'push', 'menu', 'steps',
    'index', 'manifest', 'result', 'dotEnv',
    'sw_playbook', 'sw_map', 'router', 'start',
    'clear-sw', 'debug-jquery',
];

// ── Short URL: /{id}/view[/…] (more specific — check first) ──────────────
if (preg_match('#^/([A-Za-z0-9_\-]+)/view(/.*)?$#', $uri, $m)) {
    $id   = $m[1];
    $rest = (isset($m[2]) && $m[2] !== '' && $m[2] !== '/') ? $m[2] : '/index.html';

    $target = __DIR__ . '/sheets/' . $id . '/view' . $rest;
    if (is_file($target))  { serveFile($target, $MIME); }

    // Prefer index.php (PHP sets <base href> server-side); fall back to .html
    $idx = __DIR__ . '/sheets/' . $id . '/view/index.php';
    if (is_file($idx))     { serveFile($idx, $MIME); }

    $idx = __DIR__ . '/sheets/' . $id . '/view/index.html';
    if (is_file($idx))     { serveFile($idx, $MIME); }

    sheetNotFound($id);
}

// ── Short URL: /{id}/connections[/…] ─────────────────────────────────────
if (preg_match('#^/([A-Za-z0-9_\-]+)/connections(/.*)?$#', $uri, $m)) {
    $id   = $m[1];
    $rest = (isset($m[2]) && $m[2] !== '' && $m[2] !== '/') ? $m[2] : '/index.html';

    $target = __DIR__ . '/sheets/' . $id . '/connections' . $rest;
    if (is_file($target))  { serveFile($target, $MIME); }

    $idx = __DIR__ . '/sheets/' . $id . '/connections/index.php';
    if (is_file($idx))     { serveFile($idx, $MIME); }

    $idx = __DIR__ . '/sheets/' . $id . '/connections/index.html';
    if (is_file($idx))     { serveFile($idx, $MIME); }

    sheetNotFound($id);
}

// ── Short URL: /{id}/sellsheet[/…] ───────────────────────────────────────
if (preg_match('#^/([A-Za-z0-9_\-]+)/sellsheet(/.*)?$#', $uri, $m)) {
    $id   = $m[1];
    $rest = (isset($m[2]) && $m[2] !== '' && $m[2] !== '/') ? $m[2] : '/index.html';

    $target = __DIR__ . '/sheets/' . $id . '/sellsheet' . $rest;
    if (is_file($target))  { serveFile($target, $MIME); }

    $idx = __DIR__ . '/sheets/' . $id . '/sellsheet/index.php';
    if (is_file($idx))     { serveFile($idx, $MIME); }

    $idx = __DIR__ . '/sheets/' . $id . '/sellsheet/index.html';
    if (is_file($idx))     { serveFile($idx, $MIME); }

    sheetNotFound($id);
}

// ── Short URL: /{id}[/…] → redirect to /sheets/{id}[/…] ─────────────────
if (preg_match('#^/([A-Za-z0-9_\-]+)(/.*)?$#', $uri, $m)) {
    $id   = $m[1];
    $rest = (isset($m[2]) && $m[2] !== '') ? $m[2] : '/';

    // Let reserved top-level names fall through to normal PHP serving
    if (in_array($id, $RESERVED)) {
        return false;
    }

    // Only redirect when the sheet directory actually exists
    if (is_dir(__DIR__ . '/sheets/' . $id)) {
        header('Location: /sheets/' . $id . $rest, true, 302);
        exit;
    }

    // Unknown ID — fall through (PHP will serve its own 404)
    return false;
}

// ── Fall through: let the built-in server handle everything else normally ──
return false;
