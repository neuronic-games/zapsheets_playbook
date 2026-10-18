<?php
/**
 * Public GameBoard view — no sheet ID in the URL.
 * URL: /gameboard/{token}[/{game-slug}]
 *
 * Resolves the 24-char hex token → sheet ID via shares/gameboard/{token}.json,
 * then hands off to the main gameboard renderer (source/gameboard/index.php)
 * with the URL rewritten so the renderer sees a standard /{sheet_id}/gameboard/{slug} path.
 *
 * $_gbPublicBase is set before the include so the renderer builds all internal
 * links as /gameboard/{token}/ instead of /{sheet_id}/gameboard/.
 */

$_bpFile = dirname(__DIR__, 3) . '/dotEnv.php';
if (file_exists($_bpFile)) require_once $_bpFile;

$_rp  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_bp  = rtrim($_ENV['BASE_PATH'] ?? '', '/');
$_rps = ($_bp !== '' && str_starts_with($_rp, $_bp)) ? substr($_rp, strlen($_bp)) : $_rp;

// Parse /gameboard/{token}[/{slug}]
preg_match('#^/gameboard/([a-f0-9]{24})(?:/([^/?]*))?#', $_rps, $_pm);
$_pubToken = $_pm[1] ?? '';
$_pubSlug  = urldecode(trim($_pm[2] ?? ''));

if (!$_pubToken) { http_response_code(404); echo 'Not found.'; exit; }

$_shareFile = dirname(__DIR__, 3) . '/shares/gameboard/' . $_pubToken . '.json';
if (!file_exists($_shareFile)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Not found</title>'
       . '<style>body{font-family:sans-serif;padding:3rem;text-align:center;background:#0d1b2a;color:#aaa}'
       . 'h2{color:#7c9eff;margin-bottom:.5rem}</style></head><body>'
       . '<h2>GameBoard not found</h2>'
       . '<p>This link may have expired or never existed.</p>'
       . '</body></html>';
    exit;
}

$_shareData  = json_decode(file_get_contents($_shareFile), true) ?: [];
$_pubSheetId = $_shareData['sheet_id'] ?? '';

if (!$_pubSheetId) {
    http_response_code(500);
    echo 'Invalid share file.';
    exit;
}

// Rewrite REQUEST_URI so the main index.php regex finds the sheet ID correctly.
// The renderer will extract $_sheetId from this, then use $_gbPublicBase for links.
$_SERVER['REQUEST_URI'] = $_bp . '/' . $_pubSheetId . '/gameboard/' . $_pubSlug;

// Tell the renderer to use the public base path for all internal links.
// Must be set BEFORE including index.php.
$_gbPublicBase = 'gameboard/' . $_pubToken . '/';

require __DIR__ . '/../index.php';
