<?php
/**
 * Token-based public game page.
 * Resolves /game/{token} → sheet_id + game, then renders the view page
 * without exposing the sheet ID in the URL.
 */
error_reporting(0);

$_bpFile = __DIR__ . '/../../dotEnv.php';
if (file_exists($_bpFile)) { require_once $_bpFile; }

$_rp = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_bp = rtrim($_ENV['BASE_PATH'] ?? '', '/');
$_rp_stripped = ($_bp !== '' && str_starts_with($_rp, $_bp))
    ? substr($_rp, strlen($_bp)) : $_rp;

preg_match('#^/game/([a-f0-9]{24})/?$#', $_rp_stripped, $_m);
$_token = $_m[1] ?? '';

$_tokenFile = __DIR__ . '/../../shares/pitch-game-view/' . $_token . '.json';
if (!$_token || !file_exists($_tokenFile)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Not found</title></head>'
       . '<body style="font-family:sans-serif;padding:3rem;text-align:center">'
       . '<h2>Game link not found or expired.</h2></body></html>';
    exit;
}

$_meta     = json_decode(file_get_contents($_tokenFile), true) ?: [];
$_sheetId  = $_meta['sheet_id'] ?? '';
$_gameName = $_meta['game']     ?? '';

if (!$_sheetId || !$_gameName) { http_response_code(404); exit; }

// Pass sheet ID and game name to view/index.php via globals
$GLOBALS['_gv_sheet_id'] = $_sheetId;
$GLOBALS['_gv_game']     = $_gameName;

// Fake REQUEST_URI so view/index.php computes the correct base href for assets
$_SERVER['REQUEST_URI'] = $_bp . '/sheets/' . $_sheetId . '/view/?game=' . urlencode($_gameName);
$_GET['game'] = $_gameName;

include __DIR__ . '/../view/index.php';
?>
