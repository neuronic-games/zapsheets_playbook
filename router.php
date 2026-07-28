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

require_once __DIR__ . '/dotEnv.php';

$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip BASE_PATH prefix so routes work when deployed in a subdirectory.
// Set BASE_PATH=/playbook-test (no trailing slash) in .env for subdirectory deploys.
$basePath = rtrim($_ENV['BASE_PATH'] ?? '/', '/');   // e.g. "/playbook-test" or ""
if ($basePath !== '' && str_starts_with($uri, $basePath)) {
    $uri = substr($uri, strlen($basePath)) ?: '/';
}

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
        // Execute PHP files rather than dumping source.
        // Do NOT chdir() here — it changes the process CWD and persists across
        // subsequent requests in PHP's single-process built-in server, causing
        // later requests (e.g. POST to push/*) to 404 because the built-in server
        // looks for files relative to the changed CWD.  All PHP files in this
        // project use __DIR__ for path resolution, so chdir() is unnecessary.
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
    global $basePath;
    $pushLink = $basePath . '/push/?id=' . urlencode($id);
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Sheet not found</title>'
       . '<style>body{font-family:sans-serif;padding:2rem;background:#f5f5f5}'
       . 'h2{color:#c0392b}code{background:#eee;padding:.1em .4em;border-radius:3px}'
       . 'a{color:#2980b9}</style></head><body>'
       . '<h2>Sheet not initialised</h2>'
       . '<p>No sheet found for <code>' . htmlspecialchars($id) . '</code>.</p>'
       . '<p>Initialise it first:<br>'
       . '<a href="' . $pushLink . '">' . htmlspecialchars($pushLink) . '</a></p>'
       . '</body></html>';
    exit;
}

// Top-level directories / files that must never be rewritten
$RESERVED = [
    'js', 'css', 'fonts', 'images', 'sheets', 'source',
    'push', 'menu', 'steps',
    'index', 'manifest', 'result', 'dotEnv',
    'sw_playbook', 'sw_map', 'pitchboard-sw', 'fitboard-sw', 'router', 'start',
    'clear-sw', 'debug-jquery',
    'pitchboard',   // setup landing page — handled below, reserved so /{id} never grabs it
    'fitboard',     // setup landing page — handled below, reserved so /{id} never grabs it
];

// ── /pushsite → push/pushsite.php ────────────────────────────────────────────
if (preg_match('#^/pushsite(/.*)?$#', $uri)) {
    serveFile(__DIR__ . '/push/pushsite.php', $MIME);
}

// ── /pitchboard — setup landing page (no sheet ID yet) ───────────────────────
if (preg_match('#^/pitchboard/?$#', $uri)) {
    serveFile(__DIR__ . '/source/pitchboard/index.php', $MIME);
}

// ── /pitchboard/help — instructional help page ────────────────────────────────
if (preg_match('#^/pitchboard/help/?$#', $uri)) {
    serveFile(__DIR__ . '/source/pitchboard/help/index.php', $MIME);
}

// ── /fitboard — setup landing page (no sheet ID yet) ─────────────────────────
if (preg_match('#^/fitboard/?$#', $uri)) {
    serveFile(__DIR__ . '/source/fitboard/setup/index.php', $MIME);
}

// ── Backward compat: sheets/{id}/view|dashboard|sellsheet|fitboard|trmnl ──
if (preg_match('#^/sheets/([A-Za-z0-9_\-]+)/view(/.*)?$#', $uri))       { serveFile(__DIR__ . '/source/view/index.php',       $MIME); }
if (preg_match('#^/sheets/([A-Za-z0-9_\-]+)/pitchboard(/.*)?$#', $uri)) { serveFile(__DIR__ . '/source/dashboard/index.php',  $MIME); }
if (preg_match('#^/sheets/([A-Za-z0-9_\-]+)/dashboard(/.*)?$#',  $uri)) { serveFile(__DIR__ . '/source/dashboard/index.php',  $MIME); } // backward compat
if (preg_match('#^/sheets/([A-Za-z0-9_\-]+)/sellsheet(/.*)?$#', $uri))  { serveFile(__DIR__ . '/source/sellsheet/index.php',  $MIME); }
if (preg_match('#^/sheets/([A-Za-z0-9_\-]+)/trmnl(/.*)?$#', $uri))      { serveFile(__DIR__ . '/source/trmnl/index.php',      $MIME); }
if (preg_match('#^/sheets/([A-Za-z0-9_\-]+)/fitboard/onboard/?$#', $uri)) { serveFile(__DIR__ . '/source/fitboard/onboard/index.php', $MIME); }
if (preg_match('#^/sheets/([A-Za-z0-9_\-]+)/fitboard(/.*)?$#', $uri))   { serveFile(__DIR__ . '/source/fitboard/index.php',   $MIME); }

// ── Short URL: /{id}/view[/…] ────────────────────────────────────────────
// View is served from the single source file — no per-sheet copies needed.
if (preg_match('#^/([A-Za-z0-9_\-]+)/view(/.*)?$#', $uri, $m)) {
    serveFile(__DIR__ . '/source/view/index.php', $MIME);
}

// ── Short URL: /{id}/pitchboard[/…] ──────────────────────────────────────
// Dashboard is served from the single source file — no per-sheet copies needed.
if (preg_match('#^/([A-Za-z0-9_\-]+)/pitchboard(/.*)?$#', $uri, $m)) {
    $id = $m[1];
    serveFile(__DIR__ . '/source/dashboard/index.php', $MIME);
}
// Backward compat: old /dashboard URLs still work
if (preg_match('#^/([A-Za-z0-9_\-]+)/dashboard(/.*)?$#', $uri, $m)) {
    $id = $m[1];
    serveFile(__DIR__ . '/source/dashboard/index.php', $MIME);
}

// ── Short URL: /{id}/site[/…] ────────────────────────────────────────────
// Site is served from source/site/ — no per-sheet copies needed.
if (preg_match('#^/([A-Za-z0-9_\-]+)/site(/.*)?$#', $uri, $m)) {
    $id      = $m[1];
    $hasRest = isset($m[2]) && $m[2] !== '';

    // No trailing slash → redirect so relative URLs in the page resolve correctly
    if (!$hasRest) {
        header('Location: ' . $basePath . '/' . $id . '/site/', true, 302);
        exit;
    }

    $rest = ($m[2] === '/') ? '/index.php' : $m[2];

    // Serve directly from source/site/
    $target = __DIR__ . '/source/site' . $rest;
    if (is_file($target))  { serveFile($target, $MIME); }

    $idx = __DIR__ . '/source/site/index.php';
    if (is_file($idx))     { serveFile($idx, $MIME); }

    $idx = __DIR__ . '/source/site/index.html';
    if (is_file($idx))     { serveFile($idx, $MIME); }

    sheetNotFound($id);
}

// ── Short URL: /{id}/sellsheet[/…] ───────────────────────────────────────
// Sellsheet is served from the single source file — no per-sheet copies needed.
if (preg_match('#^/([A-Za-z0-9_\-]+)/sellsheet(/.*)?$#', $uri, $m)) {
    serveFile(__DIR__ . '/source/sellsheet/index.php', $MIME);
}

// ── Short URL: /{id}/trmnl ────────────────────────────────────────────────
// TRMNL e-paper plugin — follow-up list.
if (preg_match('#^/([A-Za-z0-9_\-]+)/trmnl(/.*)?$#', $uri, $m)) {
    serveFile(__DIR__ . '/source/trmnl/index.php', $MIME);
}

// ── Short URL: /{id}/fitboard/onboard ── must come before the fitboard rule ──
if (preg_match('#^/([A-Za-z0-9_\-]+)/fitboard/onboard/?$#', $uri, $m)) {
    serveFile(__DIR__ . '/source/fitboard/onboard/index.php', $MIME);
}

// ── Short URL: /{id}/fitboard ─────────────────────────────────────────────
if (preg_match('#^/([A-Za-z0-9_\-]+)/fitboard(/.*)?$#', $uri, $m)) {
    serveFile(__DIR__ . '/source/fitboard/index.php', $MIME);
}

// ── Short URL: /{id}[/…] → redirect to /sheets/{id}[/…] ─────────────────
if (preg_match('#^/([A-Za-z0-9_\-]+)(/.*)?$#', $uri, $m)) {
    $id   = $m[1];
    $rest = (isset($m[2]) && $m[2] !== '') ? $m[2] : '/';

    // Reserved top-level names (js, css, push, etc.) must reach their actual files.
    // Returning false here would make the built-in server resolve against the
    // *original* REQUEST_URI, which still carries the BASE_PATH prefix
    // (e.g. /playbook-test/push/updateProfile.php).  Because there is no
    // playbook-test/ subdirectory in the doc root the file won't be found → 404.
    // Instead, serve the file explicitly using the already-stripped $uri.
    if (in_array($id, $RESERVED)) {
        // $uri may contain percent-encoded characters (e.g. spaces in filenames
        // like game-Test%20Game-en.json).  Decode before constructing the
        // filesystem path so is_file() finds the actual file on disk.
        $physicalPath = __DIR__ . rawurldecode($uri);
        if (is_file($physicalPath)) {
            serveFile($physicalPath, $MIME);
        }
        return false;   // path doesn't exist; let the built-in server 404
    }

    // Only redirect when the sheet directory actually exists
    if (is_dir(__DIR__ . '/sheets/' . $id)) {
        header('Location: ' . $basePath . '/sheets/' . $id . $rest, true, 302);
        exit;
    }

    // Unknown ID — fall through (PHP will serve its own 404)
    return false;
}

// ── Fall through: let the built-in server handle everything else normally ──
return false;
