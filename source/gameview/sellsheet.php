<?php
/**
 * Token-based sellsheet page — /game/{token}/sellsheet
 * Resolves the token to sheet_id + game, then renders the sellsheet
 * without exposing the sheet ID in the URL.
 */
error_reporting(0);

$_bpFile = __DIR__ . '/../../dotEnv.php';
if (file_exists($_bpFile)) { require_once $_bpFile; }

$_rp = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_bp = rtrim($_ENV['BASE_PATH'] ?? '', '/');
$_rp_stripped = ($_bp !== '' && str_starts_with($_rp, $_bp))
    ? substr($_rp, strlen($_bp)) : $_rp;

preg_match('#^/game/([a-f0-9]{24})/sellsheet/?$#', $_rp_stripped, $_m);
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

// If the games sheet has its own Sell Sheet for this game, send visitors there
// instead of the generated sellsheet (covers links that were already shared).
$_gamesFile = __DIR__ . '/../../sheets/' . preg_replace('/[^A-Za-z0-9_\-]/', '', $_sheetId) . '/games.json';
if (file_exists($_gamesFile)) {
    foreach (json_decode(file_get_contents($_gamesFile), true) ?: [] as $_g) {
        if (($_g['Name'] ?? '') !== $_gameName) continue;
        foreach (['Sell Sheet', 'Sell Sheet URL', 'Sellsheet URL', 'Sellsheet', 'Link Sellsheet'] as $_k) {
            $_v = trim(ltrim(trim((string)($_g[$_k] ?? '')), "'"));
            if (preg_match('/^\[.*?\]\((.+)\)\s*$/', $_v, $_mm)) $_v = trim($_mm[1]);   // [text](url)
            if (preg_match('/^\[(.+)\]\s*$/', $_v, $_mm))         $_v = trim($_mm[1]);   // [url]
            if ($_v === '') continue;
            if (!preg_match('#^https?://#i', $_v)) $_v = 'https://' . $_v;
            header('Location: ' . $_v, true, 302);
            exit;
        }
        break;
    }
}

$GLOBALS['_gv_sheet_id']   = $_sheetId;
$GLOBALS['_gv_game']       = $_gameName;
$GLOBALS['_gv_game_token'] = $_token;

// Fake REQUEST_URI so sellsheet/index.php computes the correct base href
$_SERVER['REQUEST_URI'] = $_bp . '/sheets/' . $_sheetId . '/sellsheet/?game=' . urlencode($_gameName);
$_GET['game'] = $_gameName;

include __DIR__ . '/../sellsheet/index.php';
?>
