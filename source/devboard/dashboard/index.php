<?php
/**
 * DevBoard dashboard — collapsed game cards with lazy-loaded playtest sessions.
 * URL patterns: /{id}/devboard  or  /sheets/{id}/devboard
 *
 * Only shows games that have a cached "[GameName] dev" JSON file.
 */

$_rp = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('#^(.*?)(?:sheets/)?([A-Za-z0-9_\-]+)/devboard/?$#', $_rp, $_bm);
$_base     = (isset($_bm[1]) && $_bm[1] !== '') ? $_bm[1] : '/';
if (substr($_base, -1) !== '/') $_base .= '/';
$_sheet_id = $_bm[2] ?? '';

$_games_file = __DIR__ . '/../../../sheets/' . $_sheet_id . '/games.json';
$_games_raw  = file_exists($_games_file)
    ? (json_decode(file_get_contents($_games_file), true) ?: [])
    : [];

// Scan for [*] dev.json cache files — use scandir to avoid bracket glob issues
$_active_keys = [];
$_sheets_dir  = __DIR__ . '/../../../sheets/' . $_sheet_id . '/';
if (is_dir($_sheets_dir)) {
    foreach (scandir($_sheets_dir) as $_f) {
        if (preg_match('/^\[(.+)\] dev\.json$/i', $_f, $_m)) {
            $_active_keys[strtolower($_m[1])] = true;
        }
    }
}

// Load settings for default tester name
$_settings_file = __DIR__ . '/../../../sheets/' . $_sheet_id . '/settings.json';
$_settings      = file_exists($_settings_file)
    ? (json_decode(file_get_contents($_settings_file), true) ?: [])
    : [];
$_my_name  = '';
$_my_email = '';
$_my_phone = '';
$_company  = '';
$_logo_url = '';

foreach ($_settings as $_s) {
    // PitchBoard-style: {Name: 'My Name', Value: '...'}
    $n = $_s['Name'] ?? $_s['name'] ?? '';
    $v = $_s['Value'] ?? $_s['value'] ?? '';
    if ($n === 'My Name')  { $_my_name  = $v; continue; }
    if ($n === 'My Email') { $_my_email = $v; continue; }
    if ($n === 'My Phone') { $_my_phone = $v; continue; }
    if ($n === 'Company')  { $_company  = ltrim($v, "'"); continue; }
    if ($n === 'Logo') {
        if (preg_match('/^=IMAGE\("([^"]*)"\)$/i', ltrim($v,"'"), $_lm)) { $_logo_url = $_lm[1]; }
        else { $_logo_url = ltrim($v, "'"); }
        continue;
    }

    // DevBoard quirky format: first row becomes headers, so
    // {"My Name": "My Email", "<actual-name>": "email@domain.com"}
    $label = $_s['My Name'] ?? '';
    $keys  = array_keys($_s);
    $val2  = count($keys) > 1 ? ltrim(trim($_s[$keys[1]] ?? ''), "'") : '';
    if ($label === 'My Email') { $_my_email = $val2; }
    if ($label === 'My Phone') { $_my_phone = $val2; }
    if ($label === 'Company')  { $_company  = $val2; }
    if ($label === 'Logo') {
        if (preg_match('/^=IMAGE\("([^"]*)"\)$/i', $val2, $_lm)) { $_logo_url = $_lm[1]; }
        else { $_logo_url = $val2; }
    }
}

// DevBoard quirky: person's name is the second column header in the first record
if (!$_my_name && !empty($_settings)) {
    $firstRec = $_settings[0];
    $keys = array_keys($firstRec);
    if (count($keys) > 1 && ($keys[0] === 'My Name')) {
        $_my_name = $keys[1];
    }
}

// Load bio data for current user from bios.json
$_bios_file = __DIR__ . '/../../../sheets/' . $_sheet_id . '/bios.json';
$_bios      = file_exists($_bios_file)
    ? (json_decode(file_get_contents($_bios_file), true) ?: [])
    : [];
$_my_bio_image    = '';
$_my_bio_desc     = '';
$_my_bio_skills   = '';
$_my_bio_location = '';
$_my_bio_discord  = '';
$_my_bio_payment  = '';
$_my_bio_notes    = '';
foreach ($_bios as $_b) {
    $bEmail = ltrim(trim($_b['Email'] ?? ''), "'");
    if ($_my_email && strcasecmp($bEmail, $_my_email) === 0) {
        $rawImg = trim($_b['Image'] ?? '');
        if (preg_match('/^=IMAGE\("([^"]*)"\)$/i', $rawImg, $_im)) {
            $_my_bio_image = $_im[1];
        } elseif ($rawImg) {
            $_my_bio_image = ltrim($rawImg, "'");
        }
        $_my_bio_desc     = ltrim(trim($_b['Description'] ?? ''), "'");
        $_my_bio_skills   = ltrim(trim($_b['Skills']      ?? ''), "'");
        $_my_bio_location = ltrim(trim($_b['Location']    ?? ''), "'");
        $_my_bio_discord  = ltrim(trim($_b['Discord']     ?? ''), "'");
        $_my_bio_payment  = ltrim(trim($_b['Payment']     ?? ''), "'");
        $_my_bio_notes    = ltrim(trim($_b['Notes']       ?? ''), "'");
        break;
    }
}

// Load people names for Testers combo
$_people_file  = __DIR__ . '/../../../sheets/' . $_sheet_id . '/people.json';
$_people_raw   = file_exists($_people_file)
    ? (json_decode(file_get_contents($_people_file), true) ?: [])
    : [];
$_people_names = [];
foreach ($_people_raw as $_p) {
    $n = trim($_p['Name'] ?? '');
    if ($n) $_people_names[] = $n;
}

// Load contracts
$_contracts_file = __DIR__ . '/../../../sheets/' . $_sheet_id . '/contracts.json';
$_contracts_raw  = file_exists($_contracts_file)
    ? (json_decode(file_get_contents($_contracts_file), true) ?: [])
    : [];

// Publisher count — unique clients from Contracts sheet
$_client_count = count(array_unique(array_filter(array_map(
    fn($c) => trim($c['Client'] ?? ''), $_contracts_raw
))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link rel="icon" type="image/png" sizes="32x32" href="images/db_icon_32.png" />
<link rel="icon" type="image/png" sizes="16x16" href="images/db_icon_16.png" />
<link rel="apple-touch-icon" sizes="180x180" href="images/db_icon_180.png" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-title" content="DevBoard" />
<title>DevBoard</title>
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2') format('woff2'),url('fonts/DINBlack.ttf'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf'); }
*, *::before, *::after { box-sizing:border-box; }
body { margin:0; background:#f0f4f8; font-family:'DINRegular',Arial,sans-serif; color:#111; }

/* ── Account menu ────────────────────────────────────── */
.account-menu-wrap { position:relative; flex-shrink:0; }
.account-menu {
  display:none; position:absolute; top:calc(100% + .4rem); right:0;
  background:#1a1a2e; border:1px solid rgba(255,255,255,.2);
  border-radius:8px; min-width:130px; z-index:300;
  box-shadow:0 6px 20px rgba(0,0,0,.4); overflow:hidden;
}
.account-menu.open { display:block; }
.account-menu-item {
  display:block; width:100%; background:none; border:none;
  color:rgba(255,255,255,.85); text-align:left; cursor:pointer;
  font-family:'DINBlack',sans-serif; font-size:.72rem;
  text-transform:uppercase; letter-spacing:.07em;
  padding:.6rem 1rem; transition:background .12s;
}
.account-menu-item:hover { background:rgba(255,255,255,.1); color:#fff; }

/* ── Release notes dialog ─────────────────────────────── */
.rn-overlay {
  display:none; position:fixed; inset:0;
  background:rgba(0,0,0,.45); z-index:400;
  align-items:center; justify-content:center; padding:1rem;
}
.rn-overlay.open { display:flex; }
.rn-dialog {
  background:#fff; border-radius:12px; padding:1.5rem;
  width:min(520px,94vw); box-shadow:0 8px 32px rgba(0,0,0,.22);
  display:flex; flex-direction:column; gap:0;
  max-height:80vh; overflow:hidden;
}
.rn-dialog h2 { font-family:'DINBlack',sans-serif; font-size:.95rem; margin:0 0 1rem; }
.rn-body { overflow-y:auto; flex:1; display:flex; flex-direction:column; gap:1.1rem; }
.rn-release { display:flex; flex-direction:column; gap:.35rem; }
.rn-version {
  font-family:'DINBlack',sans-serif; font-size:.72rem;
  text-transform:uppercase; letter-spacing:.07em;
  color:#1a1a2e; border-bottom:1px solid #e8e8f0;
  padding-bottom:.3rem; margin-bottom:.1rem;
}
.rn-feature {
  font-size:.8rem; color:#444; line-height:1.45;
  display:flex; gap:.5rem; align-items:baseline;
}
.rn-feature::before { content:'·'; color:#1a1a2e; font-weight:700; flex-shrink:0; }
.rn-dialog-actions { display:flex; justify-content:flex-end; padding-top:.9rem; }

/* ── Top bar ──────────────────────────────────────────── */
.top-bar {
  background:#1a5f7a; color:#fff;
  padding:.75rem 1.25rem;
  position:sticky; top:0; z-index:100;
}
.top-bar-inner { max-width:860px; margin:0 auto; display:flex; align-items:center; gap:.75rem; }
.top-bar-left  { flex:1; min-width:0; display:flex; align-items:center; gap:.65rem; }
.top-bar h1    { font-family:'DINBlack',sans-serif; font-size:1rem; margin:0; letter-spacing:.03em; cursor:pointer; }
.top-bar h1:hover { opacity:.8; }
.db-dev   { color:#a8bcd7; }
.db-board { color:#48c4d2; }
.top-bar .sub  { font-size:.73rem; opacity:.6; margin:0; }
.top-bar-logo  { height:2.5rem; width:auto; object-fit:contain; flex-shrink:0; border-radius:3px; }

.top-btn {
  display:inline-flex; align-items:center; justify-content:center;
  background:rgba(255,255,255,.15); color:#fff;
  border:1px solid rgba(255,255,255,.3); border-radius:6px;
  padding:.38rem .65rem; cursor:pointer; font-size:.75rem;
  transition:background .15s; flex-shrink:0; gap:.35rem;
  font-family:'DINBlack',sans-serif; text-transform:uppercase; letter-spacing:.06em;
}
.top-btn:hover { background:rgba(255,255,255,.25); }
.top-btn:disabled { opacity:.5; cursor:default; }
@keyframes spin { to { transform:rotate(360deg); } }
.top-btn.syncing .sync-icon { animation:spin .8s linear infinite; display:inline-block; }
@keyframes dialog-shake {
  0%,100% { transform:translateX(0); }
  20%      { transform:translateX(-8px); }
  40%      { transform:translateX(8px); }
  60%      { transform:translateX(-5px); }
  80%      { transform:translateX(5px); }
}
.dialog-shake { animation:dialog-shake .35s ease; }

/* ── Save toast (background-save error notification) ─────────────────────── */
.save-toast {
  position:fixed; bottom:1.25rem; left:50%; transform:translateX(-50%);
  background:#b91c1c; color:#fff;
  padding:.55rem 1rem .55rem .9rem;
  border-radius:8px; box-shadow:0 4px 16px rgba(0,0,0,.25);
  font-family:'DINRegular',sans-serif; font-size:.8rem; line-height:1.4;
  display:flex; align-items:center; gap:.75rem;
  max-width:min(420px,90vw);
  opacity:0; pointer-events:none; transition:opacity .2s;
  z-index:9999;
}
.save-toast.visible { opacity:1; pointer-events:auto; }
.save-toast-close {
  background:none; border:none; color:#fff; cursor:pointer;
  font-size:1rem; line-height:1; padding:0; flex-shrink:0; opacity:.8;
}
.save-toast-close:hover { opacity:1; }

/* ── Top tab segmented control ────────────────────────── */
.top-tab-btns {
  display:flex; align-items:center;
  background:rgba(255,255,255,.12); border-radius:8px; padding:3px; gap:0;
}
.top-tab {
  font-family:'DINBlack',sans-serif; font-size:.62rem; text-transform:uppercase;
  letter-spacing:.07em; padding:.3rem .9rem; border-radius:6px;
  border:none; color:rgba(255,255,255,.6);
  background:transparent; cursor:pointer; transition:all .15s; white-space:nowrap;
}
.top-tab.active { background:#fff; color:#1a1a2e; }
.top-tab:not(.active):hover { color:#fff; }

/* ── Mobile top bar: wrap tabs to second row ─────────── */
@media (max-width:560px) {
  .top-bar { padding:.5rem 1rem; }
  .top-bar-inner { flex-wrap:wrap; gap:.4rem; padding-bottom:.4rem; }
  .top-bar-left  { order:1; flex:1; }
  .account-menu-wrap { order:2; }
  .top-tab-btns  { order:3; width:100%; }
  .top-tab       { flex:1; text-align:center; padding:.3rem .4rem; }
}

/* ── Views ────────────────────────────────────────────── */
.view { display:none; }
.view.active { display:block; }

/* ── Dash view ────────────────────────────────────────── */
.dash-wrap { max-width:860px; margin:0 auto; padding:1rem 1.25rem; display:flex; flex-direction:column; gap:1.2rem; }
.dash-stat-row { display:flex; gap:.65rem; flex-wrap:wrap; }
.dash-stat { background:#fff; border-radius:8px; padding:.7rem 1rem; flex:1; min-width:110px; box-shadow:0 1px 3px rgba(0,0,0,.07); }
.dash-stat-num { font-family:'DINBlack',sans-serif; font-size:1.5rem; color:#1a5f7a; line-height:1; }
.dash-stat-label { font-size:.62rem; text-transform:uppercase; letter-spacing:.07em; color:#999; margin-top:.25rem; }
.dash-section-hd { font-family:'DINBlack',sans-serif; font-size:.65rem; text-transform:uppercase; letter-spacing:.09em; color:#999; margin:0 0 .5rem; }
.dash-status-cols { display:flex; gap:.65rem; flex-wrap:wrap; }
.dash-status-col { background:#fff; border-radius:8px; flex:1; min-width:130px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); }
.dash-status-col-hd { padding:.38rem .75rem; font-family:'DINBlack',sans-serif; font-size:.62rem; text-transform:uppercase; letter-spacing:.07em; }
.dash-status-game { padding:.32rem .75rem; font-size:.78rem; border-top:1px solid #f0f4f8; color:#333; cursor:pointer; }
.dash-status-game:hover { background:#f8fafc; }
.dash-status-game.has-dev { font-family:'DINBlack',sans-serif; color:#1a5f7a; }
.dash-status-empty { padding:.32rem .75rem; font-size:.72rem; color:#ccc; border-top:1px solid #f0f4f8; }
.dash-contracts { background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); }
.dash-contract-row { display:grid; grid-template-columns:1fr auto auto auto; gap:.75rem; align-items:center; padding:.48rem .85rem; border-bottom:1px solid #f0f4f8; font-size:.78rem; }
.dash-contract-row:last-child { border-bottom:none; }
.dash-contract-game { font-family:'DINBlack',sans-serif; color:#1a5f7a; }
.dash-contract-client { color:#666; font-size:.72rem; }

/* ── Games list view ──────────────────────────────────── */
.games-view-wrap { max-width:860px; margin:0 auto; padding:1rem 1.25rem; }
.games-list-table { width:100%; border-collapse:collapse; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.08); }
.games-list-table th { font-family:'DINBlack',sans-serif; font-size:.62rem; text-transform:uppercase; letter-spacing:.07em; color:#666; padding:.5rem .85rem; text-align:left; border-bottom:2px solid #e2e8f0; background:#f8fafc; }
.games-list-table td { padding:.55rem .85rem; font-size:.8rem; border-bottom:1px solid #f0f4f8; vertical-align:middle; }
.games-list-table tbody tr:last-child td { border-bottom:none; }
.games-list-table tbody tr:hover td { background:#f8fafc; }
.games-list-table td.glt-name { font-family:'DINBlack',sans-serif; color:#1a5f7a; cursor:pointer; }
.games-list-table td.glt-name:hover { text-decoration:underline; }
.glt-status { font-family:'DINBlack',sans-serif; font-size:.6rem; letter-spacing:.05em; text-transform:uppercase; padding:.15rem .45rem; border-radius:999px; background:#e8f4f8; color:#1a5f7a; white-space:nowrap; }

/* ── Clients view ─────────────────────────────────────── */
.publishers-view-wrap { max-width:860px; margin:0 auto; padding:1rem 1.25rem; display:flex; flex-direction:column; gap:.75rem; }
.client-card { background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.08); }
.client-card-header { background:#1a1a2e; color:#fff; padding:.55rem 1rem; font-family:'DINBlack',sans-serif; font-size:.88rem; letter-spacing:.02em; display:flex; align-items:center; justify-content:space-between; cursor:pointer; user-select:none; }
.client-card-header:hover { background:#252540; }
.client-card-chevron { font-size:.65rem; opacity:.5; transition:transform .2s; flex-shrink:0; margin-left:.5rem; }
.client-card.open .client-card-chevron { transform:rotate(180deg); }
.client-card-count { font-family:'DINRegular',sans-serif; font-size:.7rem; opacity:.55; }
.client-card-body { display:none; }
.client-card.open .client-card-body { display:block; }
.client-contract-row { display:grid; grid-template-columns:1fr auto auto; gap:.75rem; align-items:center; padding:.5rem 1rem; border-bottom:1px solid #f0f4f8; cursor:pointer; transition:background .1s; }
.client-contract-row:last-child { border-bottom:none; }
.client-contract-row:hover { background:#f8fafc; }
.client-contract-game { font-family:'DINBlack',sans-serif; font-size:.8rem; color:#1a5f7a; }
.client-contract-dates { font-size:.7rem; color:#888; margin-top:.1rem; }
.client-contract-quote { font-family:'DINBlack',sans-serif; font-size:.8rem; color:#111; white-space:nowrap; }
.client-contract-badge { font-family:'DINBlack',sans-serif; font-size:.6rem; letter-spacing:.05em; text-transform:uppercase; padding:.15rem .45rem; border-radius:999px; white-space:nowrap; background:#e8f4f8; color:#1a5f7a; }
.client-contract-badge.paid     { background:#dcfce7; color:#15803d; }
.client-contract-badge.invoiced { background:#fef9c3; color:#a16207; }
.client-contract-badge.partial  { background:#ffedd5; color:#9a3412; }
.publishers-empty { color:#888; font-size:.85rem; padding:1rem 0; }

/* ── Search bar ───────────────────────────────────────── */
.search-bar { padding:.6rem 1.25rem .5rem; max-width:860px; margin:0 auto; display:flex; gap:.6rem; align-items:center; }
.search-wrap { position:relative; flex:1; }
.search-wrap input {
  width:100%; padding:0 2rem 0 .8rem; height:2.1rem; box-sizing:border-box;
  font-family:'DINRegular',sans-serif; font-size:.8rem;
  border:1px solid #c8d6e0; border-radius:6px; outline:none;
  background:#fff; color:#111;
}
.search-wrap input:focus { border-color:#1a5f7a; }
.search-clear { position:absolute; right:.5rem; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:1rem; color:#aaa; line-height:1; padding:0; display:none; }
.search-wrap.has-text .search-clear { display:block; }
.add-game-btn {
  font-family:'DINBlack',sans-serif; font-size:.75rem;
  text-transform:uppercase; letter-spacing:.07em;
  background:#1a1a2e; color:#fff;
  border:none; border-radius:8px;
  height:2.1rem; padding:0 .85rem; box-sizing:border-box; cursor:pointer; flex-shrink:0;
  transition:background .15s;
}
.add-game-btn:hover { background:#2d2d4e; }

/* ── Content ──────────────────────────────────────────── */
.content { padding:.4rem 1.25rem 3rem; max-width:860px; margin:0 auto; }

/* ── Game card ────────────────────────────────────────── */
.game-card { background:#fff; border-radius:10px; box-shadow:0 1px 4px rgba(0,0,0,.08); margin-bottom:.75rem; overflow:hidden; }
.game-card-header {
  background:#1a5f7a; color:#fff; padding:.55rem 1rem;
  display:flex; align-items:center; gap:.5rem;
  cursor:pointer; user-select:none;
}
.game-card-header:hover { background:#145070; }
.game-card-title { font-family:'DINBlack',sans-serif; font-size:.88rem; letter-spacing:.03em; flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.game-card-meta  { font-family:'DINRegular',sans-serif; font-size:.72rem; color:rgba(255,255,255,.5); white-space:nowrap; flex-shrink:0; overflow:hidden; text-overflow:ellipsis; max-width:40%; }
.game-card-chevron { font-size:.65rem; opacity:.55; flex-shrink:0; transition:transform .22s ease; transform:rotate(-90deg); }
.game-card.open .game-card-chevron { transform:rotate(0deg); }
.game-card-body-wrap { display:grid; grid-template-rows:0fr; transition:grid-template-rows .22s ease; }
.game-card.open .game-card-body-wrap { grid-template-rows:1fr; }
.game-card-body { overflow:hidden; min-height:0; }

/* ── Card subtitle bar ────────────────────────────────── */
.card-subtitle {
  display:flex; align-items:center; gap:.5rem .75rem; flex-wrap:wrap;
  padding:.5rem 1rem;
  background:#f7fafb; border-bottom:1px solid #ddeaf0;
}
.card-stat {
  font-family:'DINBlack',sans-serif; font-size:.68rem;
  text-transform:uppercase; letter-spacing:.05em;
  padding:.22rem .6rem; border-radius:999px; color:#fff; white-space:nowrap;
  cursor:pointer; transition:opacity .15s, box-shadow .15s;
}
.card-stat:hover { opacity:.85; }
.card-stat span { font-family:'DINRegular',sans-serif; opacity:.85; }
.stat-playtest { background:#1a5f7a; }
.stat-meeting  { background:#6b3fa8; }
.stat-idea     { background:#2e7a52; }
.stat-dim      { opacity:.35; }
.stat-active   { box-shadow:0 0 0 2.5px #fff, 0 0 0 4.5px rgba(0,0,0,.25); }
.subtitle-right { margin-left:auto; display:flex; align-items:stretch; gap:.45rem; flex-wrap:wrap; }
@media (max-width:560px) { .subtitle-right { margin-left:0; width:100%; } }
.subtitle-btn {
  font-family:'DINBlack',sans-serif; font-size:.7rem;
  text-transform:uppercase; letter-spacing:.07em;
  background:transparent; color:#1a5f7a;
  border:1.5px solid #1a5f7a; border-radius:6px;
  padding:.28rem .65rem; cursor:pointer;
  display:inline-flex; align-items:center; justify-content:center; gap:.3rem;
  transition:background .15s, color .15s;
  white-space:nowrap;
}
.subtitle-btn:hover { background:#1a5f7a; color:#fff; }
.subtitle-btn svg { flex-shrink:0; }
.subtitle-btn-primary { background:#1a5f7a; color:#fff; }
.subtitle-btn-primary:hover { background:#145070; }
.subtitle-btn-reload { color:#aaa; border-color:#d0d8e0; }
.subtitle-btn-reload:hover { color:#1a5f7a; border-color:#1a5f7a; background:transparent; }
.subtitle-btn-reload.loading svg { animation:sw-spin .7s linear infinite; }
@keyframes sw-spin { to { transform:rotate(360deg); } }


/* ── Contract dialog ──────────────────────────────────── */
.contract-dialog {
  background:#fff; border-radius:12px;
  padding:1.5rem; width:min(560px,96vw);
  box-shadow:0 8px 32px rgba(0,0,0,.22);
  display:flex; flex-direction:column; gap:1rem;
  max-height:92vh; overflow-y:auto;
}
.contract-dialog h2 { font-family:'DINBlack',sans-serif; font-size:1rem; margin:0; }
.contract-dialog h2 span { font-family:'DINRegular',sans-serif; opacity:.55; }
.contract-quote-wrap { position:relative; }
.contract-quote-wrap input { padding-left:1.4rem !important; }
.contract-quote-prefix {
  position:absolute; left:.55rem; top:50%; transform:translateY(-50%);
  font-size:.82rem; color:#666; pointer-events:none;
}

/* ── Sessions inside a card ───────────────────────────── */
.dev-loading { padding:1.1rem 1.1rem; font-size:.8rem; color:#888; font-style:italic; }
.dev-empty   { padding:1.1rem 1.1rem; font-size:.8rem; color:#aaa; }
.game-image-wrap { background:#fff; border-top:1px solid #e8edf2; text-align:center; }
.game-image-wrap img { max-width:100%; max-height:280px; display:block; margin:0 auto; object-fit:contain; }
.dev-error   { padding:1.1rem 1.1rem; font-size:.8rem; color:#c0392b; }

.session-block { border-top:1px solid #e8f0f4; }
.session-block:first-child { border-top:none; }
.session-header {
  display:flex; flex-direction:column; gap:0;
  padding:.7rem 1rem .6rem;
  background:#f0f7fb; border-bottom:1px solid #d8eaf2;
  cursor:pointer; user-select:none; -webkit-user-select:none;
  -webkit-touch-callout:none;
}
.session-header:hover { background:#e6f2f8; }
/* position:relative so button+chevron can be abs-positioned within the row */
.session-header-row { position:relative; display:flex; align-items:baseline; gap:.55rem; padding-right:5.5rem; }
.session-type {
  font-family:'DINBlack',sans-serif; font-size:.72rem;
  text-transform:uppercase; letter-spacing:.06em; line-height:1;
}
.session-type.type-playtest { color:#1a5f7a; }
.session-type.type-meeting  { color:#6b3fa8; }
.session-type.type-idea     { color:#2e7a52; }
.session-date     { font-family:'DINRegular',sans-serif; font-size:.72rem; color:#999; }
.session-sep      { color:#ccc; font-size:.6rem; }
.session-location { font-family:'DINRegular',sans-serif; font-size:.72rem; color:#777; }
.session-length   { font-family:'DINRegular',sans-serif; font-size:.72rem; color:#999; }
.session-count    { font-family:'DINRegular',sans-serif; font-size:.68rem; color:#bbb; margin-left:auto; white-space:nowrap; }
/* Chevron: absolute so it doesn't affect row height */
.session-chevron  { position:absolute; right:.3rem; top:50%; transform:translateY(-50%) rotate(-90deg); font-size:.6rem; opacity:.45; transition:transform .22s ease; }
.session-block.open .session-chevron { transform:translateY(-50%) rotate(0deg); }
/* EDIT button: absolute so it doesn't inflate the row */
.session-edit-btn {
  position:absolute; right:1.7rem; top:50%; transform:translateY(-50%);
  visibility:hidden;
  font-family:'DINBlack',sans-serif; font-size:.7rem;
  text-transform:uppercase; letter-spacing:.07em;
  background:transparent; color:#1a5f7a;
  border:1.5px solid #1a5f7a; border-radius:6px;
  padding:.28rem .65rem; cursor:pointer; white-space:nowrap;
  display:inline-flex; align-items:center; justify-content:center;
  transition:background .15s, color .15s;
}
.session-edit-btn:hover { background:#1a5f7a; color:#fff; }
@media (hover: hover) {
  .session-header:hover .session-edit-btn { visibility:visible; }
}
@media (hover: none) {
  .session-block.open .session-edit-btn { visibility:visible; }
}
.session-testers-line { font-family:'DINRegular',sans-serif; font-size:.72rem; color:#888; font-style:italic; padding-left:.05rem; margin-top:.2rem; }

/* Collapsible session body */
.session-body-wrap { display:grid; grid-template-rows:0fr; transition:grid-template-rows .22s ease; }
.session-block.open .session-body-wrap { grid-template-rows:1fr; }
.session-body { overflow:hidden; min-height:0; }

.obs-table { width:100%; border-collapse:collapse; }
.obs-table td { padding:.45rem 1rem; font-size:.8rem; line-height:1.5; vertical-align:top; border-bottom:1px solid #f0f4f8; }
.obs-table tr:last-child td { border-bottom:none; }
.obs-table .td-obs { width:50%; color:#222; }
.obs-table .td-sol { width:50%; color:#1a5f7a; border-left:1px solid #d8eaf2; }
.obs-table .td-sol:empty::after { content:'—'; color:#e0e0e0; }

/* ── No games ─────────────────────────────────────────── */
.no-games { text-align:center; padding:3rem 1rem; font-size:.88rem; color:#aaa; }
.no-games strong { display:block; font-family:'DINBlack',sans-serif; font-size:1rem; color:#888; margin-bottom:.4rem; }

/* ── Overlays ─────────────────────────────────────────── */
.overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:200; align-items:center; justify-content:center; padding:1rem; }
.overlay.open { display:flex; }

/* Profile form fields */
.ge-label { display:flex; flex-direction:column; gap:.3rem; font-family:'DINBlack',sans-serif; font-size:.68rem; text-transform:uppercase; letter-spacing:.06em; color:#666; }
.ge-input { font-family:'DINRegular',sans-serif; font-size:.85rem; padding:.42rem .65rem; border:1px solid #d1dde6; border-radius:6px; outline:none; color:#111; background:#fff; }
.ge-input:focus { border-color:#1a5f7a; }
.ge-textarea { resize:vertical; min-height:5rem; line-height:1.5; }
.profile-photo-wrap { width:72px; height:72px; border-radius:50%; border:2px dashed #c8d6e0; cursor:pointer; overflow:hidden; flex-shrink:0; background:#f0f4f8; display:flex; align-items:center; justify-content:center; font-size:.6rem; color:#aaa; text-align:center; font-family:'DINBlack',sans-serif; text-transform:uppercase; letter-spacing:.04em; transition:border-color .15s; }
.profile-photo-wrap:hover { border-color:#1a5f7a; }
.profile-photo-wrap img { width:100%; height:100%; object-fit:cover; }

/* Sync overlay */
.sync-dialog {
  background:#fff; border-radius:10px;
  padding:1.4rem; width:min(480px,92vw);
  box-shadow:0 8px 32px rgba(0,0,0,.22);
  display:flex; flex-direction:column; gap:.75rem;
  max-height:calc(100dvh - 2rem); overflow-y:auto;
}
.sync-dialog h2 { font-family:'DINBlack',sans-serif; font-size:.95rem; margin:0; }
.sync-log {
  background:#0f172a; border-radius:6px;
  padding:.75rem 1rem; min-height:6rem; max-height:14rem;
  overflow-y:auto; font-family:monospace; font-size:.75rem;
  line-height:1.6; color:#94a3b8;
}
.sync-log-line { display:block; }
.sync-log-line.ok    { color:#4ade80; }
.sync-log-line.skip  { color:#94a3b8; }
.sync-log-line.error { color:#f87171; }
.sync-log-line.info  { color:#60a5fa; }
.sync-dialog-actions { display:flex; align-items:center; justify-content:flex-end; gap:.5rem; }
.notes-close {
  font-family:'DINBlack',sans-serif; font-size:.7rem;
  text-transform:uppercase; letter-spacing:.05em;
  background:none; color:#999; border:1px solid #ddd;
  border-radius:6px; padding:.42rem .9rem; cursor:pointer;
}
.notes-close:hover { background:#f5f5f5; color:#333; }
.notes-close:disabled { opacity:.4; cursor:default; }

/* Add game dialog */
.add-dialog {
  background:#fff; border-radius:12px;
  padding:1.5rem; width:min(620px,96vw);
  box-shadow:0 8px 32px rgba(0,0,0,.22);
  display:flex; flex-direction:column; gap:1rem;
  max-height:92vh;
  /* No overflow-y:auto here — it would clip the game-name combo dropdown.
     Scrolling happens inside .add-dialog-body instead. */
}
.add-dialog h2 { font-family:'DINBlack',sans-serif; font-size:.95rem; text-transform:uppercase; letter-spacing:.07em; color:#1a1a2e; margin:0; }
.add-dialog-body { overflow-y:auto; display:flex; flex-direction:column; gap:1rem; }
.add-new-only { display:none; }   /* shown only when game is not in GAMES_RAW */

/* Session dialog */
.session-dialog {
  background:#fff; border-radius:12px;
  padding:1.5rem; width:min(680px,96vw);
  box-shadow:0 8px 32px rgba(0,0,0,.22);
  display:flex; flex-direction:column; gap:1.1rem;
  max-height:92vh; overflow-y:auto;
}
.session-dialog h2 { font-family:'DINBlack',sans-serif; font-size:.95rem; text-transform:uppercase; letter-spacing:.07em; color:#1a5f7a; margin:0; display:flex; align-items:center; gap:.5rem; }
.session-dialog h2 > span:not(.sw-display) { color:#1a1a2e; }
.sw-display { margin-left:auto; font-family:'DINBlack',sans-serif; font-size:.85rem; color:#e67e22; letter-spacing:.06em; display:none; }
.sw-display.sw-active { display:block; }
.btn-stopwatch { margin-right:auto; background:none; border:1.5px solid #d0d8e0; border-radius:6px; padding:.35rem .65rem; cursor:pointer; display:inline-flex; align-items:center; gap:.35rem; color:#bbb; font-family:'DINBlack',sans-serif; font-size:.78rem; letter-spacing:.04em; transition:border-color .15s, color .15s, background .15s; }
.btn-stopwatch:hover { border-color:#aaa; color:#888; }
.btn-stopwatch.sw-running { border-color:#e67e22; color:#e67e22; background:#fff8f2; }

/* Field grid: 3 cols top, separator, 2 cols bottom */
.field-grid {
  display:grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap:.75rem .9rem;
}
.field-group { display:flex; flex-direction:column; gap:.3rem; }
.field-group.span2 { grid-column:span 2; }
.field-group label {
  font-family:'DINBlack',sans-serif; font-size:.68rem;
  text-transform:uppercase; letter-spacing:.07em; color:#888;
}
.field-input {
  display:block; width:100%; padding:.5rem .7rem;
  font-family:'DINRegular',sans-serif; font-size:.85rem; color:#111;
  border:1.5px solid #d0d8e0; border-radius:6px; outline:none;
  background:#fafbfc; transition:border-color .15s;
}
.field-input:focus { border-color:#1a5f7a; background:#fff; }
input[type="date"].field-input { -webkit-appearance:none; appearance:none; min-height:2.45rem; box-sizing:border-box; }
select.field-input { height:2.45rem; -webkit-appearance:none; appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%23888' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right .7rem center; padding-right:2rem; }
.field-sep { border:none; border-top:1px solid #e8edf0; margin:.1rem 0; }
/* URL input + upload button combo */
.ge-url-wrap { display:flex; align-items:stretch; border:1.5px solid #d0d8e0; border-radius:6px; overflow:hidden; background:#fafbfc; transition:border-color .15s; }
.ge-url-wrap:focus-within { border-color:#1a5f7a; background:#fff; }
.ge-url-wrap .field-input { border:none; border-radius:0; flex:1; min-width:0; background:transparent; }
.ge-url-wrap .field-input:focus { border-color:transparent; background:transparent; }
.ge-upload-btn { flex:0 0 auto; background:none; border:none; border-left:1px solid #e0e8ee; padding:0 .5rem; cursor:pointer; color:#bbb; display:flex; align-items:center; transition:color .15s,background .15s; }
.ge-upload-btn:hover { color:#1a5f7a; background:#e8f4f8; }
.ge-upload-btn svg { display:block; }

/* Observations/thoughts textareas */
.obs-grid { display:grid; grid-template-columns:1fr 1fr; gap:.9rem; }
.field-textarea {
  display:block; width:100%; padding:.6rem .75rem;
  font-family:'DINRegular',sans-serif; font-size:.85rem; color:#111; line-height:1.5;
  border:2px solid #1a1a2e; border-radius:6px; outline:none;
  background:#fff; resize:none; overflow:hidden;
  transition:border-color .15s;
}
.field-textarea:focus { border-color:#1a5f7a; }
.field-textarea::placeholder { color:#c4cdd8; font-style:italic; }
.field-input::placeholder { color:#c4cdd8; }
.obs-pair-empty .field-textarea { border:1.5px solid #d0d8e0; background:#fafbfc; }
.obs-pair-empty .field-textarea:focus { border-color:#1a5f7a; background:#fff; }

/* Keyboard navigation hint — hidden on touch-only devices */
.obs-kbd-hint {
  display:none; font-family:'DINRegular',sans-serif; font-size:.7rem;
  color:#c8d0d8; user-select:none; text-align:center;
}
@media (hover: hover) { .obs-kbd-hint { display:block; } }

/* Shared dialog bits */
.dialog-actions { display:flex; justify-content:flex-end; gap:.6rem; }
.btn-cancel {
  font-family:'DINBlack',sans-serif; font-size:.8rem; text-transform:uppercase; letter-spacing:.06em;
  background:none; border:1.5px solid #ddd; color:#888; border-radius:7px; padding:.5rem 1rem; cursor:pointer;
  transition:border-color .15s, color .15s;
}
.btn-cancel:hover { border-color:#aaa; color:#555; }
.btn-primary {
  font-family:'DINBlack',sans-serif; font-size:.8rem; text-transform:uppercase; letter-spacing:.06em;
  background:#1a5f7a; color:#fff; border:none; border-radius:7px; padding:.5rem 1.2rem; cursor:pointer;
  transition:background .15s;
}
.btn-primary:hover:not(:disabled) { background:#145070; }
.btn-primary:disabled { opacity:.5; cursor:default; }
.btn-dark {
  font-family:'DINBlack',sans-serif; font-size:.8rem; text-transform:uppercase; letter-spacing:.06em;
  background:#1a1a2e; color:#fff; border:none; border-radius:7px; padding:.5rem 1.2rem; cursor:pointer;
  transition:background .15s;
}
.btn-dark:hover:not(:disabled) { background:#2d2d4e; }
.btn-dark:disabled { opacity:.5; cursor:default; }
.dialog-err { font-size:.78rem; color:#c0392b; display:none; }

/* Combo box */
.combo-wrap { position:relative; }
.combo-input { display:block; width:100%; padding:.6rem .8rem; font-family:'DINRegular',sans-serif; font-size:.88rem; color:#111; border:1.5px solid #ccc; border-radius:7px; outline:none; background:#fff; transition:border-color .15s; }
.combo-input:focus { border-color:#1a5f7a; }
.combo-dropdown { display:none; position:absolute; left:0; right:0; top:calc(100% + 2px); background:#fff; border:1.5px solid #1a5f7a; border-radius:7px; max-height:200px; overflow-y:auto; z-index:50; box-shadow:0 4px 16px rgba(0,0,0,.12); }
.combo-wrap.open .combo-dropdown { display:block; }
.combo-option { padding:.5rem .8rem; font-family:'DINRegular',sans-serif; font-size:.85rem; cursor:pointer; color:#222; }
.combo-option:hover, .combo-option.highlighted { background:#e8f4f8; color:#1a5f7a; }
.combo-empty { padding:.5rem .8rem; font-size:.8rem; color:#aaa; font-style:italic; }

/* Dynamic tester rows */
.tester-row { margin-bottom:.4rem; }
.tester-row:last-child { margin-bottom:0; }

/* Dynamic obs/sol pairs */
.obs-pair { margin-bottom:.75rem; }
.obs-pair:last-child { margin-bottom:0; }
.obs-pair-labels {
  display:grid; grid-template-columns:1fr 1fr; gap:.9rem;
  margin-bottom:.3rem;
}
.obs-pair-labels label {
  font-family:'DINBlack',sans-serif; font-size:.68rem;
  text-transform:uppercase; letter-spacing:.07em; color:#888;
}
.obs-pair-inputs { display:grid; grid-template-columns:1fr 1fr; gap:.9rem; }
.obs-obs-col { display:flex; flex-direction:column; min-width:0; }
/* obs-obs-col: textarea wrapper with icon overlaid inside */
.obs-ta-wrap { position:relative; }
.obs-ta-wrap .field-textarea { padding-right:2.1rem; }
/* icon button: hidden when obs textarea has text; always shown when image present */
.obs-ta-wrap.has-obs-text .obs-img-btn { display:none; }
.obs-img-btn { position:absolute; top:.35rem; right:.35rem; background:rgba(255,255,255,.88); border:1px solid #d0d8e4; border-radius:5px; padding:.22rem .26rem; cursor:pointer; color:#99a; line-height:1; z-index:2; backdrop-filter:blur(2px); transition:color .15s,background .15s,border-color .15s; }
.obs-img-btn:hover { color:#1a5f7a; background:#fff; border-color:#a0b8c8; }
.obs-img-preview img { width:100%; border-radius:6px; border:1px solid #dce8f0; display:block; }

/* ── Session metadata: mobile-first (column), side-by-side on wide screens ── */
.session-meta-wrap { display:flex; flex-direction:column; gap:.9rem; }
.session-meta-left { display:flex; flex-direction:column; gap:.75rem; }
.session-meta-row { display:grid; grid-template-columns:1fr 1fr; gap:.75rem .9rem; }
.session-people { width:100%; }
@media (min-width:769px) {
  .session-meta-wrap { flex-direction:row; align-items:flex-start; }
  .session-meta-left { flex:1; min-width:0; }
  .session-people { width:190px; flex-shrink:0; }
}

/* ── Mobile (≤768px): bottom-sheet dialog, stack obs/sol ── */
@media (max-width:768px) {
  #sessionOverlay { align-items:flex-end; padding:0; }
  .session-dialog {
    width:100vw; max-width:100vw; border-radius:16px 16px 0 0;
    margin-top:auto; padding:1.25rem 1rem 1.5rem;
    max-height:94dvh;
  }
  .obs-pair-inputs { grid-template-columns:1fr; }
  .obs-pair-labels label:last-child { display:none; }
  .field-grid { grid-template-columns:1fr 1fr; }
  .field-group.span2 { grid-column:span 1; }
  .obs-grid { grid-template-columns:1fr; }
  /* Prevent iOS Safari from auto-zooming inputs with font-size < 16px */
  .field-input, .field-textarea { font-size:1rem; }
}
</style>
</head>
<body>

<!-- Top bar -->
<div class="top-bar">
  <div class="top-bar-inner">
    <div class="top-bar-left">
      <?php if ($_logo_url): ?>
      <img src="<?= htmlspecialchars($_logo_url, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($_company, ENT_QUOTES) ?>"
           class="top-bar-logo" id="topBarLogo">
      <?php else: ?>
      <img src="" alt="" class="top-bar-logo" id="topBarLogo" style="display:none">
      <?php endif; ?>
      <div>
        <h1 onclick="window.location.href=APP_BASE+'devboard'"><span class="db-dev">Dev</span><span class="db-board">Board</span></h1>
        <p class="sub" id="subTitle">Playtest Notes</p>
      </div>
    </div>

    <div class="top-tab-btns">
      <button class="top-tab"        id="tabDash"       onclick="switchTab('dash')">Board</button>
      <button class="top-tab active" id="tabGames"      onclick="switchTab('games')">Games</button>
      <button class="top-tab"        id="tabPublishers" onclick="switchTab('publishers')"><?= $_client_count ?> Publishers</button>
    </div>

    <div class="account-menu-wrap">
      <button class="top-btn" onclick="toggleAccountMenu()" title="Menu">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <circle cx="12" cy="7.5" r="4.5"/>
          <path d="M3.5 21c0-4.14 3.81-7.5 8.5-7.5s8.5 3.36 8.5 7.5"/>
        </svg>
      </button>
      <div class="account-menu" id="accountMenu">
        <button class="account-menu-item" onclick="accountMenuProfile()">Profile</button>
        <button class="account-menu-item" onclick="accountMenuCompany()">Company</button>
        <button class="account-menu-item" onclick="accountMenuFetch()">Fetch</button>
        <button class="account-menu-item" onclick="accountMenuRelease()">Releases</button>
        <button class="account-menu-item" onclick="accountMenuHelp()">Help</button>
      </div>
    </div>
  </div>
</div>

<!-- View: Dash -->
<div class="view" id="view-dash">
  <div class="dash-wrap" id="dashWrap"></div>
</div>

<!-- View: Games (card + session view) -->
<div class="view active" id="view-games">
  <div class="search-bar">
    <div class="search-wrap" id="searchWrap">
      <input type="text" id="searchInput" placeholder="Search sessions, people, notes…"
        oninput="onSearch()" autocomplete="off" spellcheck="false"
        onkeydown="if(event.key==='Escape'){clearSearch();this.blur();event.stopPropagation();}" />
      <button class="search-clear" onclick="clearSearch()">✕</button>
    </div>
    <button class="add-game-btn" onclick="openAddDialog()">+ Game</button>
  </div>
  <div class="content" id="cardList"></div>
</div>

<!-- View: Publishers -->
<div class="view" id="view-publishers">
  <div class="publishers-view-wrap" id="publishersViewWrap"></div>
</div>

<!-- Edit Contract dialog -->
<div class="overlay" id="contractEditOverlay" onclick="if(event.target===this){if(isContractEditDirty())shakeDialog(this.querySelector('.contract-dialog'));else closeContractEditDialog();}">
  <div class="contract-dialog">
    <h2>Edit Contract — <span id="ceGameTitle"></span></h2>
    <div class="field-grid" style="grid-template-columns:1fr 1fr">
      <div class="field-group span2">
        <label>Client</label>
        <input type="text" class="field-input" id="ceClient" autocomplete="off" />
      </div>
      <div class="field-group">
        <label>Target Start Date</label>
        <input type="date" class="field-input" id="ceTargetStart" />
      </div>
      <div class="field-group">
        <label>Target End Date</label>
        <input type="date" class="field-input" id="ceTargetEnd" />
      </div>
      <div class="field-group">
        <label>Start Date</label>
        <input type="date" class="field-input" id="ceStartDate" />
      </div>
      <div class="field-group">
        <label>End Date</label>
        <input type="date" class="field-input" id="ceEndDate" />
      </div>
      <div class="field-group">
        <label>Quote</label>
        <div class="contract-quote-wrap">
          <span class="contract-quote-prefix">$</span>
          <input type="number" class="field-input" id="ceQuote" min="0" step="0.01" />
        </div>
      </div>
      <div class="field-group">
        <label>Payment</label>
        <select class="field-input" id="cePayment">
          <option value="Estimate">Estimate</option>
          <option value="Invoiced">Invoiced</option>
          <option value="Partial">Partial</option>
          <option value="Fully Paid">Fully Paid</option>
        </select>
      </div>
      <div class="field-group span2">
        <label>Notes</label>
        <input type="text" class="field-input" id="ceNotes" autocomplete="off" />
      </div>
    </div>
    <div class="dialog-err" id="ceErr"></div>
    <div class="dialog-actions">
      <button class="btn-cancel" onclick="forceCloseContractEditDialog()">Cancel</button>
      <button class="btn-dark" id="ceBtn" onclick="submitContractEdit()">Save Contract</button>
    </div>
  </div>
</div>

<!-- Profile dialog -->
<div class="overlay" id="profileOverlay" onclick="if(event.target===this)closeProfileDialog()">
  <div class="sync-dialog" style="width:min(500px,94vw)">
    <h2>Profile</h2>
    <!-- Photo + Name/Email row -->
    <div style="display:flex;gap:1rem;align-items:flex-start;margin:.25rem 0 0">
      <div class="profile-photo-wrap" id="profilePhotoWrap" title="Click to change photo" onclick="document.getElementById('profilePhotoFile').click()">
        <img id="profilePhotoImg" src="" alt="" style="display:none">
        <span id="profilePhotoPlaceholder" style="padding:.3rem">Photo</span>
      </div>
      <div style="flex:1;display:flex;flex-direction:column;gap:.6rem">
        <label class="ge-label">Name<input type="text"  id="profileName"  class="ge-input" placeholder="Your name" /></label>
        <label class="ge-label">Email<input type="email" id="profileEmail" class="ge-input" placeholder="your@email.com" /></label>
      </div>
    </div>
    <!-- Bio fields -->
    <div style="display:flex;flex-direction:column;gap:.65rem;margin:.75rem 0 .5rem">
      <label class="ge-label">Description<textarea id="profileDesc" class="ge-input ge-textarea" placeholder="Brief bio…"></textarea></label>
      <label class="ge-label" style="text-transform:none;letter-spacing:0"><span style="font-family:'DINBlack',sans-serif;font-size:.68rem;text-transform:uppercase;letter-spacing:.06em">Skills</span> <span style="color:#bbb;font-size:.65rem">(comma-separated)</span><input type="text" id="profileSkills" class="ge-input" placeholder="Game Designer, Tester…" /></label>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:.65rem">
        <label class="ge-label">Location<input type="text" id="profileLocation" class="ge-input" placeholder="City, Country" /></label>
        <label class="ge-label">Discord<input type="text" id="profileDiscord" class="ge-input" placeholder="@handle" /></label>
      </div>
      <label class="ge-label">Phone<input type="tel" id="profilePhone" class="ge-input" placeholder="+1 555 000 0000" /></label>
      <label class="ge-label">Company<input type="text" id="profileCompany" class="ge-input" placeholder="Your studio or company" /></label>
      <div>
        <label class="ge-label" style="margin-bottom:.3rem">Logo URL<input type="url" id="profileLogoUrl" class="ge-input" placeholder="https://…/logo.png" oninput="profileLogoPreview()" /></label>
        <div id="profileLogoPreview" style="margin-top:.4rem;display:none">
          <img id="profileLogoImg" src="" alt="Logo preview" style="max-height:3rem;max-width:12rem;object-fit:contain;border-radius:3px;border:1px solid #ddd">
        </div>
      </div>
      <label class="ge-label">Payment<input type="text" id="profilePayment" class="ge-input" placeholder="Venmo @handle, PayPal…" /></label>
      <label class="ge-label">Notes<textarea id="profileNotes" class="ge-input ge-textarea" placeholder="Availability, preferences…" rows="2"></textarea></label>
    </div>
    <input type="file" id="profilePhotoFile" accept="image/*" style="display:none" onchange="profilePhotoPreview(this)">
    <div class="sync-log" id="profileLog" style="display:none"></div>
    <div class="sync-dialog-actions">
      <button class="notes-close" id="profileCancelBtn" onclick="forceCloseProfileDialog()">Cancel</button>
      <button class="notes-close" id="profileSaveBtn"   onclick="submitProfile()" style="background:#1a5f7a;color:#fff;border-color:#1a5f7a">Save</button>
    </div>
  </div>
</div>

<!-- Company dialog -->
<div class="overlay" id="companyOverlay" onclick="if(event.target===this)closeCompanyDialog()">
  <div class="sync-dialog" style="width:min(420px,94vw)">
    <h2>Company</h2>
    <div style="display:flex;gap:1rem;align-items:center;margin:.25rem 0 .5rem">
      <div class="profile-photo-wrap" id="companyLogoWrap"
           style="width:88px;height:56px;border-radius:6px;flex-shrink:0"
           title="Click to change logo"
           onclick="document.getElementById('companyLogoFile').click()">
        <img id="companyLogoImg" src="" alt="" style="display:none;width:100%;height:100%;object-fit:contain">
        <span id="companyLogoPlaceholder" style="padding:.3rem">Logo</span>
      </div>
      <label class="ge-label" style="flex:1">Company Name
        <input type="text" id="companyName" class="ge-input" placeholder="Your studio or company" />
      </label>
    </div>
    <input type="file" id="companyLogoFile" accept="image/*" style="display:none" onchange="companyLogoFileChange(this)">
    <div class="sync-log" id="companyLog" style="display:none"></div>
    <div class="sync-dialog-actions">
      <button class="notes-close" id="companyCancelBtn" onclick="forceCloseCompanyDialog()">Cancel</button>
      <button class="notes-close" id="companySaveBtn" onclick="submitCompany()" style="background:#1a5f7a;color:#fff;border-color:#1a5f7a">Save</button>
    </div>
  </div>
</div>

<!-- Fetch overlay -->
<div class="overlay" id="syncOverlay">
  <div class="sync-dialog">
    <h2 id="syncDialogTitle">Fetching…</h2>
    <p id="syncDialogSub" style="color:#888;font-size:.78rem;margin:.1rem 0 .4rem">Fetch data from your spreadsheet</p>
    <div class="sync-log" id="syncLog"></div>
    <div class="sync-dialog-actions">
      <button class="notes-close" id="syncDoneBtn" disabled onclick="closeSyncDialog()">Close</button>
    </div>
  </div>
</div>

<!-- Add game dialog -->
<div class="overlay" id="addOverlay"
  onmousedown="_addOverlayMd=event.target"
  onclick="if(event.target===this&&_addOverlayMd===this){if(hasAddData())shakeDialog(this.querySelector('.add-dialog'));else closeAddDialog();}">
  <div class="add-dialog">
    <h2 id="addDialogTitle">Add Game</h2>

    <!-- Game name (always shown) -->
    <div class="field-group">
      <label>Game Name</label>
      <div class="combo-wrap" id="gameCombo">
        <input type="text" class="field-input combo-input" id="gameComboInput"
          placeholder="Select or type a game name…"
          autocomplete="off" spellcheck="false"
          oninput="comboFilter()"
          onfocus="comboOpen()"
          onkeydown="comboKey(event)" />
        <div class="combo-dropdown" id="comboDrop"></div>
      </div>
    </div>

    <!-- Scrollable body: new-game fields + actions.
         Kept separate from game-name combo so the dropdown isn't clipped by overflow. -->
    <div class="add-dialog-body">
    <!-- Fields shown only for new games (not already in games sheet) -->
    <div class="add-new-only" id="addNewFields">
      <hr class="field-sep" style="margin:.25rem 0 .75rem" />

      <!-- Description -->
      <div class="field-group" style="margin-bottom:.9rem">
        <label>Description</label>
        <textarea class="field-input" id="gDescription" rows="3" placeholder="Game overview…" style="resize:vertical"></textarea>
      </div>
      <div class="field-group" style="margin-bottom:.9rem">
        <label>Tagline</label>
        <input type="text" class="field-input" id="gTagline" placeholder="One-line description…" autocomplete="off" />
      </div>

      <!-- Designers -->
      <p style="font-family:'DINBlack',sans-serif;font-size:.65rem;text-transform:uppercase;letter-spacing:.07em;color:#aaa;margin:.1rem 0 .55rem">Designers</p>
      <div class="field-grid" style="grid-template-columns:1fr 1fr;margin-bottom:.9rem">
        <div class="field-group">
          <label>Designer 1</label>
          <input type="text" class="field-input" id="gDesigner1" placeholder="Search or enter name…" autocomplete="off" />
        </div>
        <div class="field-group">
          <label>Designer 2</label>
          <input type="text" class="field-input" id="gDesigner2" placeholder="Search or enter name…" autocomplete="off" />
        </div>
        <div class="field-group">
          <label>Designer 3</label>
          <input type="text" class="field-input" id="gDesigner3" placeholder="Search or enter name…" autocomplete="off" />
        </div>
        <div class="field-group">
          <label>Designer 4</label>
          <input type="text" class="field-input" id="gDesigner4" placeholder="Search or enter name…" autocomplete="off" />
        </div>
      </div>

      <!-- Details -->
      <p style="font-family:'DINBlack',sans-serif;font-size:.65rem;text-transform:uppercase;letter-spacing:.07em;color:#aaa;margin:.1rem 0 .55rem">Details</p>
      <div class="field-grid" style="grid-template-columns:1fr 1fr;margin-bottom:.9rem">
        <div class="field-group">
          <label>Status</label>
          <select class="field-input" id="gStatus">
            <option value="Design">Design</option>
            <option value="Pitching">Pitching</option>
            <option value="Signed">Signed</option>
            <option value="Published">Published</option>
          </select>
        </div>
        <div class="field-group">
          <label>Date Started</label>
          <input type="date" class="field-input" id="gDateStarted" />
        </div>
        <div class="field-group">
          <label>Date Signed</label>
          <input type="date" class="field-input" id="gDateSigned" />
        </div>
        <div class="field-group">
          <label>Date Published</label>
          <input type="date" class="field-input" id="gDatePublished" />
        </div>
      </div>

      <!-- Links -->
      <p style="font-family:'DINBlack',sans-serif;font-size:.65rem;text-transform:uppercase;letter-spacing:.07em;color:#aaa;margin:.1rem 0 .55rem">Links</p>
      <div class="field-grid" style="grid-template-columns:1fr 1fr;margin-bottom:.5rem">
        <div class="field-group">
          <label>Rules</label>
          <input type="url" class="field-input" id="gRules" placeholder="https://…" autocomplete="off" />
        </div>
        <div class="field-group">
          <label>Play</label>
          <input type="url" class="field-input" id="gPlay" placeholder="https://…" autocomplete="off" />
        </div>
        <div class="field-group">
          <label>Print</label>
          <input type="url" class="field-input" id="gPrint" placeholder="https://…" autocomplete="off" />
        </div>
        <div class="field-group">
          <label>Sellsheet</label>
          <input type="url" class="field-input" id="gSellsheet" placeholder="https://…" autocomplete="off" />
        </div>
        <div class="field-group">
          <label>BGG / View</label>
          <input type="url" class="field-input" id="gView" placeholder="https://…" autocomplete="off" />
        </div>
        <div class="field-group">
          <label>Video</label>
          <div class="ge-url-wrap">
            <input type="url" class="field-input" id="gVideo" placeholder="https://…" autocomplete="off" />
            <button type="button" class="ge-upload-btn" title="Upload video" onclick="gGameUploadClick('gVideo')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg></button>
          </div>
        </div>
        <div class="field-group span2">
          <label>Image</label>
          <div class="ge-url-wrap">
            <input type="url" class="field-input" id="gImage" placeholder="https://…" autocomplete="off" />
            <button type="button" class="ge-upload-btn" title="Upload image" onclick="gGameUploadClick('gImage')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 16 12 12 8 16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.3"/></svg></button>
          </div>
        </div>
      </div>
    </div>

    <div class="dialog-err" id="addErr"></div>
    <div class="dialog-actions">
      <button class="btn-cancel" onclick="closeAddDialog()">Cancel</button>
      <button class="btn-dark" id="addBtn" onclick="submitAddGame()">Add Game</button>
    </div>
    </div><!-- /.add-dialog-body -->
  </div>
</div>

<!-- Contract dialog -->
<div class="overlay" id="contractOverlay" onclick="if(event.target===this){if(hasContractData())shakeDialog(this.querySelector('.contract-dialog'));else closeContractDialog();}">
  <div class="contract-dialog">
    <h2>New Contract — <span id="contractGameTitle"></span></h2>
    <div class="field-grid" style="grid-template-columns:1fr 1fr">
      <div class="field-group span2">
        <label>Client</label>
        <div class="combo-wrap" id="contractClientCombo">
          <input type="text" class="field-input combo-input" id="contractClient"
            placeholder="Publisher or company…" autocomplete="off"
            oninput="contractClientFilter()"
            onfocus="contractClientOpen()"
            onkeydown="contractClientKey(event)" />
          <div class="combo-dropdown" id="contractClientDrop"></div>
        </div>
      </div>
      <div class="field-group">
        <label>Target Start Date</label>
        <input type="date" class="field-input" id="contractTargetStart" />
      </div>
      <div class="field-group">
        <label>Target End Date</label>
        <input type="date" class="field-input" id="contractTargetEnd" />
      </div>
      <div class="field-group">
        <label>Quote</label>
        <div class="contract-quote-wrap">
          <span class="contract-quote-prefix">$</span>
          <input type="number" class="field-input" id="contractQuote" placeholder="0.00" min="0" step="0.01" />
        </div>
      </div>
      <div class="field-group">
        <label>Payment</label>
        <select class="field-input" id="contractPayment">
          <option value="Estimate">Estimate</option>
          <option value="Invoiced">Invoiced</option>
          <option value="Partial">Partial</option>
          <option value="Fully Paid">Fully Paid</option>
        </select>
      </div>
      <div class="field-group span2">
        <label>Notes</label>
        <input type="text" class="field-input" id="contractNotes" placeholder="e.g. 2 tests, 2 rules reviews" autocomplete="off" />
      </div>
    </div>
    <div class="dialog-err" id="contractErr"></div>
    <div class="dialog-actions">
      <button class="btn-cancel" onclick="forceCloseContractDialog()">Cancel</button>
      <button class="btn-dark" id="contractBtn" onclick="submitContract()">Add Contract</button>
    </div>
  </div>
</div>

<!-- Add session dialog -->
<div class="overlay" id="sessionOverlay" onclick="if(event.target===this){var _d=this.querySelector('.session-dialog');if(_editMode?isSessionDirty():hasSessionData())shakeDialog(_d);else closeSessionDialog();}">
  <div class="session-dialog">
    <h2><span id="sessionDialogAction">+ Session</span><span style="color:#1a5f7a"> — </span><span id="sessionGameTitle"></span></h2>

    <!-- Session metadata: left 2×2 + right people -->
    <div class="session-meta-wrap">
      <div class="session-meta-left">
        <div class="session-meta-row">
          <div class="field-group">
            <label>Date</label>
            <input type="date" class="field-input" id="sDate" autocomplete="off" />
          </div>
          <div class="field-group">
            <label>Type</label>
            <select class="field-input" id="sType" onchange="onTypeChange()">
              <option value="Playtest">Playtest</option>
              <option value="Meeting">Meeting</option>
              <option value="Idea">Idea</option>
            </select>
          </div>
        </div>
        <div class="session-meta-row">
          <div class="field-group">
            <label>Location</label>
            <input type="text" class="field-input" id="sLocation" placeholder="" autocomplete="off" />
          </div>
          <div class="field-group">
            <label>Session Number</label>
            <input type="text" class="field-input" id="sTestNum" readonly
              style="background:#f0f4f8;color:#888;cursor:default;" />
          </div>
        </div>
      </div>
      <div class="field-group session-people">
        <label>People</label>
        <div id="testersContainer"></div>
      </div>
    </div>

    <hr class="field-sep" />

    <!-- Observations + Thoughts (dynamic pairs) -->
    <div id="obsContainer"></div>

    <div class="dialog-err" id="sessionErr"></div>
    <span class="obs-kbd-hint">⌘ / Ctrl + Arrow — move between fields</span>
    <div class="dialog-actions">
      <button class="btn-stopwatch" id="swBtn" onclick="toggleStopwatch()" onpointerdown="_swStartLongPress()" onpointerup="_swCancelLongPress(event)" onpointerleave="_swCancelLongPress(event)" title="Start / pause · Hold to reset">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><circle cx="12" cy="13" r="8"/><path d="M12 5V3"/><path d="M9 3h6"/><path d="M12 13V9"/></svg>
        <span id="swTime">00:00</span>
      </button>
      <button class="btn-cancel" onclick="closeSessionDialog()">Cancel</button>
      <button class="btn-primary" id="sessionBtn" onclick="submitSession()">Add Session</button>
    </div>
  </div>
</div>

<!-- Release notes dialog -->
<div class="rn-overlay" id="rnOverlay" onclick="if(event.target===this)closeRnDialog()">
  <div class="rn-dialog">
    <h2>Release Notes</h2>
    <div class="rn-body" id="rnBody"></div>
    <div class="rn-dialog-actions">
      <button class="btn-cancel" onclick="closeRnDialog()">Close</button>
    </div>
  </div>
</div>

<script>
<?php include __DIR__ . '/../devboard-common.js'; ?>

var APP_BASE    = document.querySelector('base').getAttribute('href');
var SHEET_ID    = <?= json_encode($_sheet_id) ?>;
var GAMES_RAW   = <?= json_encode(array_values($_games_raw), JSON_UNESCAPED_UNICODE) ?>;
var ACTIVE_KEYS = <?= json_encode($_active_keys, JSON_UNESCAPED_UNICODE) ?>;
var MY_NAME         = <?= json_encode($_my_name) ?>;
var MY_EMAIL        = <?= json_encode($_my_email) ?>;
var MY_PHONE        = <?= json_encode($_my_phone) ?>;
var MY_COMPANY      = <?= json_encode($_company) ?>;
var MY_LOGO         = <?= json_encode($_logo_url) ?>;
var MY_BIO_IMAGE    = <?= json_encode($_my_bio_image) ?>;
var MY_BIO_DESC     = <?= json_encode($_my_bio_desc) ?>;
var MY_BIO_SKILLS   = <?= json_encode($_my_bio_skills) ?>;
var MY_BIO_LOCATION = <?= json_encode($_my_bio_location) ?>;
var MY_BIO_DISCORD  = <?= json_encode($_my_bio_discord) ?>;
var MY_BIO_PAYMENT  = <?= json_encode($_my_bio_payment) ?>;
var MY_BIO_NOTES    = <?= json_encode($_my_bio_notes) ?>;
var _profilePhotoUrl = MY_BIO_IMAGE || '';
var PEOPLE_NAMES  = <?= json_encode(array_values($_people_names), JSON_UNESCAPED_UNICODE) ?>;
var CONTRACT_RAW  = <?= json_encode(array_values($_contracts_raw), JSON_UNESCAPED_UNICODE) ?>;

// Quick lookup: lowercased game name → full GAMES_RAW record
var GAMES_INDEX = {};
GAMES_RAW.forEach(function(g) {
  var n = (g.Name || '').trim();
  if (n) GAMES_INDEX[n.toLowerCase()] = g;
});

// ── Helpers ───────────────────────────────────────────────────────────────────

// esc, fmtDate, obsHtml, buildSessions, _sessionMatchesQuery, _filterObsByQuery
// are defined in devboard-common.js (loaded above).
function safeName(name) { return name.replace(/[^a-zA-Z0-9]/g, '_'); }
function todayISO() {
  var d = new Date(); var m = String(d.getMonth()+1).padStart(2,'0'); var day = String(d.getDate()).padStart(2,'0');
  return d.getFullYear() + '-' + m + '-' + day;
}
function _toDateInput(v) {
  if (!v) return '';
  var d = new Date(v);
  return isNaN(d.getTime()) ? '' : d.toISOString().slice(0,10);
}

// ── Tab switching ─────────────────────────────────────────────────────────────
var _activeTab = 'games';
function switchTab(tab) {
  _activeTab = tab;
  ['Dash','Games','Publishers'].forEach(function(t) {
    var btn = document.getElementById('tab' + t);
    if (btn) btn.classList.toggle('active', t.toLowerCase() === tab);
  });
  ['dash','games','publishers'].forEach(function(v) {
    var el = document.getElementById('view-' + v);
    if (el) el.classList.toggle('active', v === tab);
  });
  if (tab === 'dash')       renderDashView();
  if (tab === 'publishers') renderPublishersView();
}

function _statusStyle(status) {
  var s = (status || '').toLowerCase();
  if (s === 'design')      return 'background:#e0f2fe;color:#0369a1';
  if (s === 'prototype')   return 'background:#ede9fe;color:#6d28d9';
  if (s === 'playtesting') return 'background:#dcfce7;color:#15803d';
  if (s === 'signed')      return 'background:#fef9c3;color:#a16207';
  if (s === 'published')   return 'background:#1a1a2e;color:#fff';
  return 'background:#e2e8f0;color:#475569';
}

function renderDashView() {
  var wrap = document.getElementById('dashWrap');
  if (!wrap) return;

  // Stat chips
  var activeCount = Object.keys(ACTIVE_KEYS).length;
  var html = '<div class="dash-stat-row">';
  html += '<div class="dash-stat"><div class="dash-stat-num">' + GAMES_RAW.length + '</div><div class="dash-stat-label">Games</div></div>';
  html += '<div class="dash-stat"><div class="dash-stat-num">' + activeCount + '</div><div class="dash-stat-label">Active Dev</div></div>';
  html += '<div class="dash-stat"><div class="dash-stat-num">' + CONTRACT_RAW.length + '</div><div class="dash-stat-label">Contracts</div></div>';
  var _pubCount = Object.keys(CONTRACT_RAW.reduce(function(a,c){if((c.Client||'').trim())a[(c.Client||'').trim().toLowerCase()]=1;return a},{})).length;
  html += '<div class="dash-stat"><div class="dash-stat-num">' + (_pubCount || '—') + '</div><div class="dash-stat-label">Publishers</div></div>';
  html += '</div>';

  // Games by status
  var STATUS_ORDER = ['Design','Prototype','Playtesting','Signed','Published'];
  var byStatus = {};
  GAMES_RAW.forEach(function(g) {
    var s = (g.Status || 'Design').trim();
    if (!byStatus[s]) byStatus[s] = [];
    byStatus[s].push(g.Name || '');
  });
  // Sort columns: known order first, then any others
  var cols = STATUS_ORDER.filter(function(s) { return byStatus[s] && byStatus[s].length; });
  Object.keys(byStatus).forEach(function(s) { if (STATUS_ORDER.indexOf(s) === -1) cols.push(s); });

  if (cols.length) {
    html += '<div>';
    html += '<div class="dash-section-hd">Games by Status</div>';
    html += '<div class="dash-status-cols">';
    cols.forEach(function(status) {
      var style = _statusStyle(status);
      html += '<div class="dash-status-col">';
      html += '<div class="dash-status-col-hd" style="' + style + '">' + esc(status) + ' <span style="opacity:.6;font-family:DINRegular,sans-serif">(' + byStatus[status].length + ')</span></div>';
      byStatus[status].forEach(function(name) {
        var hasDev = ACTIVE_KEYS.hasOwnProperty(name.toLowerCase());
        html += '<div class="dash-status-game' + (hasDev ? ' has-dev' : '') + '" onclick="switchTab(\'games\')">' + esc(name) + '</div>';
      });
      html += '</div>';
    });
    html += '</div></div>';
  }

  // Recent contracts
  if (CONTRACT_RAW.length) {
    var recent = CONTRACT_RAW.slice().reverse().slice(0, 8);
    html += '<div>';
    html += '<div class="dash-section-hd">Contracts</div>';
    html += '<div class="dash-contracts">';
    recent.forEach(function(c) {
      var quoteNum = parseFloat(c.Quote || '');
      var quote    = isNaN(quoteNum) ? '' : '$' + quoteNum.toLocaleString('en-US', {minimumFractionDigits:0, maximumFractionDigits:2});
      var payment  = (c.Payment || '').trim();
      var badgeCls = 'client-contract-badge';
      if (payment === 'Fully Paid') badgeCls += ' paid';
      else if (payment === 'Invoiced') badgeCls += ' invoiced';
      else if (payment === 'Partial')  badgeCls += ' partial';
      html += '<div class="dash-contract-row">';
      html += '<div><div class="dash-contract-game">' + esc(c.Game || '—') + '</div><div class="dash-contract-client">' + esc(c.Client || '') + '</div></div>';
      html += '<div style="color:#888;font-size:.72rem">' + esc(c.Date || '') + '</div>';
      html += '<div style="font-family:DINBlack,sans-serif;font-size:.78rem">' + esc(quote) + '</div>';
      html += '<div><span class="' + badgeCls + '">' + esc(payment || '—') + '</span></div>';
      html += '</div>';
    });
    html += '</div></div>';
  }

  wrap.innerHTML = html;
}

function renderPublishersView() {
  var wrap = document.getElementById('publishersViewWrap');
  if (!wrap) return;

  var byClient = {};
  CONTRACT_RAW.forEach(function(con) {
    var name = (con.Client || '').trim();
    if (!name) return;
    var key = name.toLowerCase();
    if (!byClient[key]) byClient[key] = { name: name, contracts: [] };
    byClient[key].contracts.push(con);
  });

  var publishers = Object.keys(byClient).sort().map(function(k) { return byClient[k]; });

  if (!publishers.length) {
    wrap.innerHTML = '<p class="publishers-empty">No publishers yet. Add contracts to see them here.</p>';
    return;
  }

  var html = '';
  publishers.forEach(function(pub) {
    var contracts = pub.contracts;
    html += '<div class="client-card" id="pub-' + esc(pub.name.replace(/\s+/g,'_')) + '">';
    html += '<div class="client-card-header" onclick="togglePublisherCard(this.parentNode)">' +
      '<span>' + esc(pub.name) + '</span>' +
      '<span style="display:flex;align-items:center;gap:.5rem">' +
        '<span class="client-card-count">' + contracts.length + (contracts.length === 1 ? ' contract' : ' contracts') + '</span>' +
        '<span class="client-card-chevron">▼</span>' +
      '</span>' +
    '</div>';
    html += '<div class="client-card-body">';
    contracts.forEach(function(con, i) {
      var quoteNum = parseFloat(con.Quote || '');
      var quote    = isNaN(quoteNum) ? '—' : '$' + quoteNum.toLocaleString('en-US', {minimumFractionDigits:0, maximumFractionDigits:2});
      var payment  = (con.Payment || '').trim();
      var badgeCls = 'client-contract-badge';
      if (payment === 'Fully Paid') badgeCls += ' paid';
      else if (payment === 'Invoiced') badgeCls += ' invoiced';
      else if (payment === 'Partial')  badgeCls += ' partial';
      var ts = con['Target Start Date'] || '';
      var te = con['Target End Date']   || '';
      var dateRange = (ts || te) ? (ts || '?') + ' → ' + (te || '?') : '';
      var dataIdx = CONTRACT_RAW.indexOf(con);
      html += '<div class="client-contract-row" onclick="openContractEditDialog(' + dataIdx + ')">';
      html += '<div><div class="client-contract-game">' + esc(con.Game || '—') + '</div>' +
        (dateRange ? '<div class="client-contract-dates">' + esc(dateRange) + '</div>' : '') + '</div>';
      html += '<div class="client-contract-quote">' + esc(quote) + '</div>';
      html += '<div><span class="' + badgeCls + '">' + esc(payment || '—') + '</span></div>';
      html += '</div>';
    });
    html += '</div>';
    html += '</div>';
  });
  wrap.innerHTML = html;
}

function togglePublisherCard(card) {
  card.classList.toggle('open');
}


// ── Games state ───────────────────────────────────────────────────────────────

var allGames   = [];
var devCache   = {};   // gameName → rows[] | null (loading) | undefined (not loaded)
var devFilter  = {};   // gameName → active type filter string | null
var extraGames = [];

function isActive(name) { return ACTIVE_KEYS.hasOwnProperty(name.toLowerCase()); }

function buildGameList() {
  var seen = {};
  allGames = [];
  GAMES_RAW.forEach(function(g) {
    var name = (g.Name || '').trim();
    if (!name || seen[name] || !isActive(name)) return;
    seen[name] = true;
    allGames.push(g);
  });
  extraGames.forEach(function(name) {
    if (!seen[name]) { seen[name] = true; allGames.push({ Name: name, Status: '' }); }
  });
}

// ── Render cards ──────────────────────────────────────────────────────────────

function _gameMatchesQuery(g, q) {
  if (!q) return true;
  var name = (g.Name || '').toLowerCase();
  if (name.indexOf(q) !== -1) return true;
  // Search designers
  var rec = GAMES_INDEX[name] || g;
  var designers = ['Designer1','Designer2','Designer3','Designer4',
                   'Designer 1','Designer 2','Designer 3','Designer 4'];
  for (var i = 0; i < designers.length; i++) {
    if ((rec[designers[i]] || '').toLowerCase().indexOf(q) !== -1) return true;
  }
  // Search cached dev notes (people, observations, thoughts)
  var rows = devCache[g.Name];
  if (rows && rows.length) {
    for (var r = 0; r < rows.length; r++) {
      var row = rows[r];
      if ((row['People']       || '').toLowerCase().indexOf(q) !== -1) return true;
      if ((row['Observations'] || row['Observation'] || '').toLowerCase().indexOf(q) !== -1) return true;
      if ((row['Thoughts']     || row['Solution']    || '').toLowerCase().indexOf(q) !== -1) return true;
    }
  }
  return false;
}

var _searchQuery = '';

function renderCards() {
  // Rebuild all card elements (called on initial load and after add/edit game).
  // Does NOT filter visibility — applySearchFilter does that afterwards.
  var list = document.getElementById('cardList');
  list.innerHTML = '';
  if (!allGames.length) {
    list.innerHTML = '<div class="no-games"><strong>No games yet</strong>Click "+ Game" to start tracking a game.</div>';
    return;
  }
  allGames.forEach(function(g) {
    var name    = g.Name || '';
    var gameRec = GAMES_INDEX[name.toLowerCase()] || g;
    var designers = [
      (gameRec.Designer1 || gameRec['Designer 1'] || '').trim(),
      (gameRec.Designer2 || gameRec['Designer 2'] || '').trim(),
      (gameRec.Designer3 || gameRec['Designer 3'] || '').trim(),
      (gameRec.Designer4 || gameRec['Designer 4'] || '').trim(),
    ].filter(Boolean).join(', ');
    var div  = document.createElement('div');
    div.className    = 'game-card';
    div.dataset.game = name;
    div.innerHTML =
      '<div class="game-card-header" onclick="toggleCard(this.parentNode)">' +
        '<span class="game-card-title">' + esc(name) + '</span>' +
        (designers ? '<span class="game-card-meta">' + esc(designers) + '</span>' : '') +
        '<span class="game-card-chevron">▼</span>' +
      '</div>' +
      '<div class="game-card-body-wrap">' +
        '<div class="game-card-body" id="body-' + safeName(name) + '">' +
          '<div class="dev-loading">Loading…</div>' +
        '</div>' +
      '</div>';
    list.appendChild(div);
  });
  applySearchFilter(_searchQuery);
}

function applySearchFilter(q) {
  _searchQuery = (q || '').toLowerCase().trim();
  var list     = document.getElementById('cardList');
  var cards    = list ? list.querySelectorAll('.game-card') : [];
  var anyVisible = false;

  cards.forEach(function(card) {
    var name    = card.dataset.game;
    var matches = _gameMatchesQuery({ Name: name }, _searchQuery);
    card.style.display = matches ? '' : 'none';
    if (matches) { anyVisible = true; }
    // Re-render body if open and data is available (so sessions get filtered)
    if (matches && card.classList.contains('open') && devCache[name] && devCache[name].length !== undefined) {
      renderBody(name, devCache[name]);
    }
  });

  // Show/update no-results placeholder
  var placeholder = list ? list.querySelector('.no-games') : null;
  if (!anyVisible && cards.length) {
    if (!placeholder) {
      placeholder = document.createElement('div');
      placeholder.className = 'no-games';
      list.appendChild(placeholder);
    }
    placeholder.innerHTML = '<strong>No matching games</strong>Try a different search.';
    placeholder.style.display = '';
  } else if (placeholder) {
    placeholder.style.display = 'none';
  }
}

// ── Toggle + lazy load ────────────────────────────────────────────────────────

function toggleCard(card) {
  var wasOpen = card.classList.contains('open');
  card.classList.toggle('open');
  if (!wasOpen) {
    var name = card.dataset.game;
    if (devCache[name] === undefined) loadDevData(name);
    else if (devCache[name] !== null) renderBody(name, devCache[name]);
  }
}

function loadDevData(gameName, force) {
  devCache[gameName] = null;
  var fd = new FormData();
  fd.append('id', SHEET_ID);
  fd.append('game', gameName);
  if (force) fd.append('force', '1');
  fetch(APP_BASE + 'push/getDevJson.php', { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(rows) {
      devCache[gameName] = Array.isArray(rows) ? rows : [];
      renderBody(gameName, devCache[gameName]);
    })
    .catch(function() {
      devCache[gameName] = [];
      var body = document.getElementById('body-' + safeName(gameName));
      if (body) body.innerHTML = '<div class="dev-error">Could not load dev notes.</div>';
    });
}

function reloadDevData(gameName) {
  // Find the reload button and show a spinner while fetching
  var cards = document.querySelectorAll('.game-card');
  var reloadBtn = null;
  cards.forEach(function(card) {
    if (card.dataset.game === gameName) {
      reloadBtn = card.querySelector('.subtitle-btn-reload');
    }
  });
  if (reloadBtn) reloadBtn.classList.add('loading');
  devCache[gameName] = null;
  var fd = new FormData();
  fd.append('id', SHEET_ID);
  fd.append('game', gameName);
  fd.append('force', '1');
  fetch(APP_BASE + 'push/getDevJson.php', { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(rows) {
      devCache[gameName] = Array.isArray(rows) ? rows : [];
      renderBody(gameName, devCache[gameName]);
      if (reloadBtn) reloadBtn.classList.remove('loading');
    })
    .catch(function() {
      devCache[gameName] = [];
      if (reloadBtn) reloadBtn.classList.remove('loading');
    });
}

// ── Render card body (subtitle + sessions) ────────────────────────────────────

var _sessionCache = {};  // gameName → allSessions array (all, not filtered)

function renderBody(gameName, rows) {
  var body = document.getElementById('body-' + safeName(gameName));
  if (!body) return;

  var gameRec = GAMES_INDEX[gameName.toLowerCase()] || {};

  var allSessions = buildSessions(rows).reverse();  // newest first
  _sessionCache[gameName] = allSessions;
  var nPlay = allSessions.filter(function(s){ return s.testnum.toLowerCase().indexOf('playtest ') === 0; }).length;
  var nMeet = allSessions.filter(function(s){ return s.testnum.toLowerCase().indexOf('meeting ')  === 0; }).length;
  var nIdea = allSessions.filter(function(s){ return s.testnum.toLowerCase().indexOf('idea ')     === 0; }).length;

  var activeFilter = devFilter[gameName] || null;
  var sessions = activeFilter
    ? allSessions.filter(function(s){ return s.testnum.toLowerCase().indexOf(activeFilter.toLowerCase() + ' ') === 0; })
    : allSessions;
  if (_searchQuery) {
    sessions = sessions.filter(function(s) { return _sessionMatchesQuery(s, _searchQuery); });
  }

  function chipClass(type, baseClass) {
    var cls = 'card-stat ' + baseClass;
    if (activeFilter) cls += (activeFilter.toLowerCase() === type.toLowerCase() ? ' stat-active' : ' stat-dim');
    return cls;
  }

  var gn   = esc(gameName);
  var gnJ  = gn.replace(/'/g, "\\'");   // additionally JS-safe for onclick strings
  var html = '';

  // Subtitle bar — per-type chips + action buttons
  html += '<div class="card-subtitle">';
  if (nPlay) html += '<div class="' + chipClass('Playtest','stat-playtest') + '" onclick="filterSessions(\'' + gnJ + '\',\'Playtest\')">' + nPlay + ' <span>' + (nPlay === 1 ? 'Playtest' : 'Playtests') + '</span></div>';
  if (nMeet) html += '<div class="' + chipClass('Meeting', 'stat-meeting')  + '" onclick="filterSessions(\'' + gnJ + '\',\'Meeting\')">'  + nMeet + ' <span>' + (nMeet === 1 ? 'Meeting'  : 'Meetings')  + '</span></div>';
  if (nIdea) html += '<div class="' + chipClass('Idea',    'stat-idea')     + '" onclick="filterSessions(\'' + gnJ + '\',\'Idea\')">'     + nIdea + ' <span>' + (nIdea === 1 ? 'Idea'     : 'Ideas')      + '</span></div>';
  if (!nPlay && !nMeet && !nIdea) html += '<div class="card-stat stat-playtest">0 <span>Sessions</span></div>';
  html += '<div class="subtitle-right">';
  html += '<button class="subtitle-btn" onclick="shareGame(\'' + gnJ + '\')">' +
    '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>' +
    'Share</button>';
  html += '<button class="subtitle-btn" onclick="openContractDialog(\'' + gnJ + '\')">+ Contract</button>';
  html += '<button class="subtitle-btn" onclick="openEditGame(\'' + gnJ + '\')">Edit</button>';
  html += '<button class="subtitle-btn subtitle-btn-primary" onclick="openSessionDialog(\'' + gnJ + '\')">+ Session</button>';
  html += '<button class="subtitle-btn subtitle-btn-reload" onclick="reloadDevData(\'' + gnJ + '\')" title="Reload from sheet">' +
    '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>' +
    '</button>';
  html += '</div>';
  html += '</div>';

  // Sessions list
  if (!sessions.length) {
    html += '<div class="dev-empty">' + (_searchQuery ? 'No sessions match your search.' : activeFilter ? 'No ' + activeFilter + ' sessions.' : 'No playtest sessions yet. Click "+ Session" to log one.') + '</div>';
  } else {
    sessions.forEach(function(s, i) {
      // Determine type class from testnum prefix
      var typeClass = 'type-playtest';
      if (s.testnum.toLowerCase().indexOf('meeting') === 0) typeClass = 'type-meeting';
      else if (s.testnum.toLowerCase().indexOf('idea') === 0) typeClass = 'type-idea';

      var allIdx  = allSessions.indexOf(s);
      var gnQ     = JSON.stringify(gameName).replace(/"/g, '&quot;');
      var blockId = gameName.replace(/[^a-z0-9]/gi, '-').toLowerCase() + '-' + i;
      html += '<div class="session-block' + (_searchQuery ? ' open' : '') + '" id="sblock-' + blockId + '">';
      html += '<div class="session-header" onclick="toggleSession(\'' + blockId + '\')" data-game="' + esc(gameName) + '" data-idx="' + allIdx + '">';
      html +=   '<div class="session-header-row">';
      if (s.testnum) html += '<span class="session-type ' + typeClass + '">' + esc(s.testnum) + '</span>';
      if (s.date)    html += '<span class="session-sep">·</span><span class="session-date">' + esc(fmtDate(s.date)) + '</span>';
      if (s.location) html += '<span class="session-sep">·</span><span class="session-location">' + esc(s.location) + '</span>';
      if (s.length)   html += '<span class="session-sep">·</span><span class="session-length">' + esc(s.length) + '</span>';
      html +=   '<span class="session-count">' + s.obs.length + (s.obs.length === 1 ? ' note' : ' notes') + '</span>';
      html +=   '<button class="session-edit-btn" onclick="event.stopPropagation();openEditSessionDialog(' + gnQ + ',' + allIdx + ')">Edit</button>';
      html +=   '<span class="session-chevron">▼</span>';
      html +=   '</div>';
      // Testers as comma-separated line in the header
      if (s.testers.length) {
        html += '<div class="session-testers-line">' + s.testers.map(esc).join(', ') + '</div>';
      }
      html += '</div>';
      // Collapsible body
      html += '<div class="session-body-wrap"><div class="session-body">';
      var visibleObs = _filterObsByQuery(s.obs, _searchQuery);
      if (visibleObs.length) {
        html += '<table class="obs-table"><tbody>';
        visibleObs.forEach(function(o) {
          html += '<tr><td class="td-obs">' + obsHtml(o.obs) + '</td><td class="td-sol">' + esc(o.sol) + '</td></tr>';
        });
        html += '</tbody></table>';
      }
      html += '</div></div>';
      html += '</div>';
    });
  }

  body.innerHTML = html;
}

// ── Build sessions from flat 4-column rows ────────────────────────────────────
// Sheet format: Date | Event | Observation | Solution
//   Header row : date non-empty  → starts a new session; Observation = location
//   Tester rows: date empty, solution empty  → tester name in Observation
//   Obs rows   : date empty, solution non-empty (or after first obs seen)

// ── Filter sessions by type ───────────────────────────────────────────────────

function filterSessions(gameName, type) {
  devFilter[gameName] = (devFilter[gameName] === type) ? null : type;
  renderBody(gameName, devCache[gameName] || []);
}

// ── Share game ────────────────────────────────────────────────────────────────

function shareGame(gameName) {
  var fd = new FormData();
  fd.append('id', SHEET_ID);
  fd.append('game', gameName);
  fd.append('sharer', '');
  fetch(APP_BASE + 'push/createDevShare.php', { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (!res.ok || !res.viewUrl) { alert('Could not create share link.'); return; }
      showSharePopup(gameName, res.viewUrl);
    })
    .catch(function() { alert('Could not create share link.'); });
}

var _sharePopupOpen   = false;
var _shareCurrentGame = '';
var _generatedGameLinks = {};

function showSharePopup(gameName, collabUrl) {
  if (_sharePopupOpen) {
    var el = document.getElementById('sharePopupOverlay');
    if (el) el.parentNode.removeChild(el);
  }
  _sharePopupOpen   = true;
  _shareCurrentGame = gameName;

  var S = 'font-family:DINRegular,sans-serif;';
  var SB = 'font-family:DINBlack,sans-serif;';
  var overlay = document.createElement('div');
  overlay.id = 'sharePopupOverlay';
  overlay.style.cssText = 'position:fixed;inset:0;z-index:9000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.35)';
  overlay.onclick = function(e) { if (e.target === overlay) closeSharePopup(); };
  overlay.innerHTML =
    '<div style="background:#fff;border-radius:12px;padding:1.5rem;width:min(440px,92vw);box-shadow:0 8px 32px rgba(0,0,0,.22);display:flex;flex-direction:column;gap:.85rem">' +
      '<div style="display:flex;align-items:center;justify-content:space-between">' +
        '<h2 style="' + SB + 'font-size:.95rem;margin:0">Share <span style="' + S + 'opacity:.55">' + esc(gameName) + '</span></h2>' +
        '<button onclick="closeSharePopup()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:#888;line-height:1">×</button>' +
      '</div>' +

      // ── Section 1: Collaborator link ──────────────────────────────────────
      '<p style="' + SB + 'font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:#888;margin:0">Collaborate</p>' +
      '<p style="' + S + 'font-size:.8rem;color:#555;margin:0">Anyone with this link can view sessions and submit new data — no account needed.</p>' +
      '<div style="display:flex;gap:.5rem">' +
        '<input id="shareLinkInput" readonly value="' + esc(collabUrl) + '" ' +
          'style="flex:1;' + S + 'font-size:.75rem;padding:.42rem .6rem;border:1.5px solid #d0d8e0;border-radius:6px;outline:none;color:#111;background:#f7fafb" />' +
        '<button onclick="copyShareLink()" style="' + SB + 'font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;background:#1a5f7a;color:#fff;border:none;border-radius:6px;padding:.42rem .85rem;cursor:pointer;white-space:nowrap">Copy</button>' +
      '</div>' +
      '<p id="shareCopiedMsg" style="' + S + 'font-size:.78rem;color:#2e7a52;margin:0;display:none">Link copied!</p>' +

      // ── Section 2: Public game page ───────────────────────────────────────
      '<hr style="border:none;border-top:1px solid #edf2f6;margin:.1rem 0" />' +
      '<p style="' + SB + 'font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:#888;margin:0">Public Page</p>' +
      '<p style="' + S + 'font-size:.8rem;color:#555;margin:0">Public link to this game\'s info page. Anyone can view it — no account needed.</p>' +
      '<div id="sharePageGenSection">' +
        '<button id="sharePageGenBtn" onclick="generateDevGamePageLink()" ' +
          'style="' + SB + 'font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;width:100%;padding:.5rem 1rem;background:#f0f4f8;color:#1a5f7a;border:1.5px solid #c8d8e4;border-radius:6px;cursor:pointer">Generate Link</button>' +
      '</div>' +
      '<div id="sharePageUrlSection" style="display:none">' +
        '<div style="display:flex;gap:.5rem">' +
          '<input id="sharePageInput" readonly style="flex:1;' + S + 'font-size:.75rem;padding:.42rem .6rem;border:1.5px solid #d0d8e0;border-radius:6px;outline:none;color:#111;background:#f7fafb" />' +
          '<button id="sharePageCopyBtn" onclick="copyDevGamePageUrl()" style="' + SB + 'font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;background:#1a5f7a;color:#fff;border:none;border-radius:6px;padding:.42rem .85rem;cursor:pointer;white-space:nowrap">Copy</button>' +
        '</div>' +
      '</div>' +
      '<p id="sharePageCopiedMsg" style="' + S + 'font-size:.78rem;color:#2e7a52;margin:0;display:none">Link copied!</p>' +
    '</div>';
  document.body.appendChild(overlay);

  // Check whether a game page link already exists (GET = check-only)
  if (_generatedGameLinks[gameName]) {
    _showDevGamePageUrl(_generatedGameLinks[gameName]);
  } else {
    fetch(APP_BASE + 'push/createGameView.php?id=' + encodeURIComponent(SHEET_ID) + '&game=' + encodeURIComponent(gameName))
      .then(function(r) { return r.json(); })
      .then(function(res) {
        if (res.ok && res.viewUrl && _shareCurrentGame === gameName) {
          _generatedGameLinks[gameName] = res.viewUrl;
          _showDevGamePageUrl(res.viewUrl);
        }
      }).catch(function() {});
  }
}

function _showDevGamePageUrl(url) {
  var gen = document.getElementById('sharePageGenSection');
  var sec = document.getElementById('sharePageUrlSection');
  var inp = document.getElementById('sharePageInput');
  if (gen) gen.style.display = 'none';
  if (sec) sec.style.display = '';
  if (inp) inp.value = url;
}

function generateDevGamePageLink() {
  var btn = document.getElementById('sharePageGenBtn');
  if (btn) { btn.disabled = true; btn.textContent = 'Generating…'; }
  var fd = new FormData();
  fd.append('id',   SHEET_ID);
  fd.append('game', _shareCurrentGame);
  fetch(APP_BASE + 'push/createGameView.php', { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (res.viewUrl) {
        _generatedGameLinks[_shareCurrentGame] = res.viewUrl;
        _showDevGamePageUrl(res.viewUrl);
        copyDevGamePageUrl();
      } else {
        if (btn) { btn.disabled = false; btn.textContent = 'Generate Link'; }
      }
    }).catch(function() {
      if (btn) { btn.disabled = false; btn.textContent = 'Generate Link'; }
    });
}

function copyDevGamePageUrl() {
  var inp = document.getElementById('sharePageInput');
  var btn = document.getElementById('sharePageCopyBtn');
  var msg = document.getElementById('sharePageCopiedMsg');
  if (!inp || !inp.value) return;
  inp.select(); inp.setSelectionRange(0, 9999);
  navigator.clipboard ? navigator.clipboard.writeText(inp.value).catch(function(){}) : (function(){ try { document.execCommand('copy'); } catch(e){} })();
  if (btn) { btn.textContent = 'Copied!'; setTimeout(function(){ btn.textContent = 'Copy'; }, 2000); }
  if (msg) { msg.style.display = 'block'; setTimeout(function(){ msg.style.display='none'; }, 2000); }
}

function closeSharePopup() {
  _sharePopupOpen = false;
  var el = document.getElementById('sharePopupOverlay');
  if (el) el.parentNode.removeChild(el);
}
function copyShareLink() {
  var inp = document.getElementById('shareLinkInput');
  if (!inp) return;
  inp.select(); inp.setSelectionRange(0, 9999);
  try { document.execCommand('copy'); } catch(e) { navigator.clipboard && navigator.clipboard.writeText(inp.value); }
  var msg = document.getElementById('shareCopiedMsg');
  if (msg) { msg.style.display = 'block'; setTimeout(function(){ msg.style.display='none'; }, 2000); }
}

// ── Toggle session block ──────────────────────────────────────────────────────

function toggleSession(idx) {
  var block = document.getElementById('sblock-' + idx);
  if (block) block.classList.toggle('open');
}

// ── Search ────────────────────────────────────────────────────────────────────

function onSearch() {
  var inp  = document.getElementById('searchInput');
  var wrap = document.getElementById('searchWrap');
  wrap.classList.toggle('has-text', inp.value.length > 0);
  applySearchFilter(inp.value);
}
function clearSearch() {
  document.getElementById('searchInput').value = '';
  document.getElementById('searchWrap').classList.remove('has-text');
  applySearchFilter('');
}

// ── Fetch ─────────────────────────────────────────────────────────────────────

function openSyncDialog() {
  document.getElementById('syncLog').innerHTML = '';
  document.getElementById('syncDialogTitle').textContent = 'Fetching…';
  document.getElementById('syncDialogSub').style.display = '';
  document.getElementById('syncDoneBtn').disabled = true;
  document.getElementById('syncOverlay').classList.add('open');
}
function closeSyncDialog() {
  document.getElementById('syncOverlay').classList.remove('open');
  window.location.reload();
}
function syncLog(msg, type) {
  var log  = document.getElementById('syncLog');
  var line = document.createElement('span');
  line.className = 'sync-log-line ' + (type || 'info');
  line.textContent = msg;
  log.appendChild(line);
  log.scrollTop = log.scrollHeight;
}

function doFetch() {
  openSyncDialog();

  var sheets = ['games', 'people', 'bios', 'contracts'];

  var pushBase = APP_BASE + 'push/pushSheetUpdate.php';
  var idx = 0;

  function finish() {
    syncLog('Done.', 'ok');
    document.getElementById('syncDialogTitle').textContent = 'Fetch Complete';
    document.getElementById('syncDialogSub').style.display = 'none';
    document.getElementById('syncDoneBtn').disabled = false;
  }

  function pushNext() {
    if (idx >= sheets.length) { finish(); return; }
    var sheetName = sheets[idx++];
    syncLog('Fetching ' + sheetName + '…', 'info');
    var xhr = new XMLHttpRequest();
    xhr.open('POST', pushBase);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
      var resp = (xhr.responseText || '').trim();
      if (resp.indexOf('ERROR:') === 0) {
        var m = resp.replace(/^ERROR:[^:]+:/, '');
        syncLog('  ✗ ' + sheetName + ': ' + m, 'error');
      } else if (resp.indexOf('SKIP:') === 0) {
        syncLog('  – ' + sheetName + ': skipped (not found)', 'skip');
      } else {
        syncLog('  ✓ ' + resp, 'ok');
      }
      pushNext();
    };
    xhr.onerror = function() {
      syncLog('  ✗ ' + sheetName + ': network error', 'error');
      pushNext();
    };
    xhr.timeout = 45000;
    xhr.ontimeout = function() {
      syncLog('  ⚠ ' + sheetName + ': timed out', 'error');
      pushNext();
    };
    xhr.send('id=' + encodeURIComponent(SHEET_ID) +
             '&sheetname=' + encodeURIComponent(sheetName) +
             '&date_string=');
  }
  pushNext();
}

// ── Add game dialog ───────────────────────────────────────────────────────────

var _comboOptions    = [];
var _comboHighlight  = -1;
var _existingNames   = {};   // lowercased names already in GAMES_RAW
var _addOverlayMd    = null; // mousedown target — prevents spurious shake when combo shrinks dialog

function _setNewGameFieldsVisible(visible) {
  document.getElementById('addNewFields').style.display = visible ? 'block' : 'none';
}

var _editGameMode     = false;
var _editGameOrigName = '';

function openAddDialog() {
  _editGameMode     = false;
  _editGameOrigName = '';
  _existingNames = {};
  _comboOptions  = [];
  GAMES_RAW.forEach(function(g) {
    var n = (g.Name || '').trim();
    if (!n) return;
    _existingNames[n.toLowerCase()] = true;
    if (!isActive(n)) _comboOptions.push(n);
  });
  document.getElementById('addDialogTitle').textContent   = 'Add Game';
  document.getElementById('gameComboInput').value         = '';
  document.getElementById('gameComboInput').readOnly      = false;
  document.getElementById('gStatus').value         = 'Design';
  document.getElementById('gDateStarted').value    = todayISO();
  document.getElementById('gDateSigned').value     = '';
  document.getElementById('gDatePublished').value  = '';
  document.getElementById('gDesigner1').value      = MY_NAME || '';
  document.getElementById('gDesigner2').value      = '';
  document.getElementById('gDesigner3').value      = '';
  document.getElementById('gDesigner4').value      = '';
  document.getElementById('gTagline').value        = '';
  document.getElementById('gDescription').value   = '';
  document.getElementById('gRules').value          = '';
  document.getElementById('gPlay').value           = '';
  document.getElementById('gPrint').value          = '';
  document.getElementById('gSellsheet').value      = '';
  document.getElementById('gView').value           = '';
  document.getElementById('gVideo').value          = '';
  document.getElementById('gImage').value          = '';
  _setNewGameFieldsVisible(true);
  document.getElementById('addErr').style.display  = 'none';
  document.getElementById('addBtn').disabled       = false;
  document.getElementById('addBtn').textContent    = 'Add Game';
  document.getElementById('comboDrop').innerHTML   = '';
  document.getElementById('gameCombo').classList.remove('open');
  document.getElementById('addOverlay').classList.add('open');
  setTimeout(function() { document.getElementById('gameComboInput').focus(); }, 80);
}

// ── Contract dialog ────────────────────────────────────────────────────────
var _contractGame = '';

function openContractDialog(name) {
  _contractGame = name;
  document.getElementById('contractGameTitle').textContent = name;
  document.getElementById('contractClient').value      = '';
  document.getElementById('contractTargetStart').value = '';
  document.getElementById('contractTargetEnd').value   = '';
  document.getElementById('contractQuote').value       = '';
  document.getElementById('contractPayment').value     = 'Estimate';
  document.getElementById('contractNotes').value       = '';
  document.getElementById('contractErr').textContent   = '';
  document.getElementById('contractErr').style.display = 'none';
  document.getElementById('contractBtn').disabled      = false;
  document.getElementById('contractBtn').textContent   = 'Add Contract';
  // populate client combo
  contractClientRebuild('');
  document.getElementById('contractOverlay').classList.add('open');
}

function hasContractData() {
  return !!(document.getElementById('contractClient').value.trim());
}
function closeContractDialog() {
  if (hasContractData()) { shakeDialog(document.getElementById('contractOverlay').querySelector('.contract-dialog')); return; }
  document.getElementById('contractOverlay').classList.remove('open');
}
function forceCloseContractDialog() {
  document.getElementById('contractOverlay').classList.remove('open');
}

// Client combo helpers
function contractClientRebuild(filter) {
  var drop  = document.getElementById('contractClientDrop');
  var lower = filter.toLowerCase();
  var items = PEOPLE_NAMES.filter(function(n) {
    return !lower || n.toLowerCase().indexOf(lower) !== -1;
  });
  if (!items.length) { drop.innerHTML = ''; document.getElementById('contractClientCombo').classList.remove('open'); return; }
  drop.innerHTML = items.map(function(n) {
    return '<div class="combo-item" data-name="' + esc(n) + '" onmousedown="contractClientPick(this.dataset.name)">' + esc(n) + '</div>';
  }).join('');
  document.getElementById('contractClientCombo').classList.add('open');
}
function contractClientOpen()  { contractClientRebuild(document.getElementById('contractClient').value); }
function contractClientFilter(){ contractClientRebuild(document.getElementById('contractClient').value); }
function contractClientPick(n) {
  document.getElementById('contractClient').value = n;
  document.getElementById('contractClientDrop').innerHTML = '';
  document.getElementById('contractClientCombo').classList.remove('open');
}
function contractClientKey(e)  {
  if (e.key === 'Escape') { document.getElementById('contractClientCombo').classList.remove('open'); }
  if (e.key === 'Enter')  {
    var first = document.querySelector('#contractClientDrop .combo-item');
    if (first) { document.getElementById('contractClient').value = first.textContent; document.getElementById('contractClientCombo').classList.remove('open'); }
  }
}

function submitContract() {
  var client      = document.getElementById('contractClient').value.trim();
  var targetStart = document.getElementById('contractTargetStart').value.trim();
  var targetEnd   = document.getElementById('contractTargetEnd').value.trim();
  var quote       = document.getElementById('contractQuote').value.trim();
  var payment     = document.getElementById('contractPayment').value;
  var notes       = document.getElementById('contractNotes').value.trim();
  var errEl       = document.getElementById('contractErr');
  errEl.textContent   = '';
  errEl.style.display = 'none';
  if (!client) { errEl.textContent = 'Client is required.'; errEl.style.display = 'block'; return; }
  var btn = document.getElementById('contractBtn');
  btn.disabled    = true;
  btn.textContent = 'Saving…';
  var fd = new FormData();
  fd.append('id',           SHEET_ID);
  fd.append('game',         _contractGame);
  fd.append('client',       client);
  fd.append('target_start', targetStart);
  fd.append('target_end',   targetEnd);
  fd.append('quote',        quote);
  fd.append('payment',      payment);
  fd.append('notes',        notes);
  fetch(APP_BASE + 'push/addContract.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(j) {
      if (j && j.ok) {
        forceCloseContractDialog();
      } else {
        errEl.textContent   = (j && j.error) ? j.error : 'Save failed.';
        errEl.style.display = 'block';
        btn.disabled    = false;
        btn.textContent = 'Add Contract';
      }
    })
    .catch(function() {
      errEl.textContent   = 'Network error.';
      errEl.style.display = 'block';
      btn.disabled    = false;
      btn.textContent = 'Add Contract';
    });
}
// ── end Contract dialog ─────────────────────────────────────────────────────

function _stripImageFormula(v) {
  // Convert =IMAGE("url") stored in the sheet back to a plain URL for input fields
  var m = String(v || '').match(/^=IMAGE\("([^"]*)"\)$/i);
  return m ? m[1] : (v || '');
}

function openEditGame(name) {
  var rec = GAMES_INDEX[name.toLowerCase()] || {};
  _editGameMode     = true;
  _editGameOrigName = name;
  document.getElementById('addDialogTitle').textContent   = 'Edit Game';
  document.getElementById('gameComboInput').value         = name;
  document.getElementById('gameComboInput').readOnly      = true;
  document.getElementById('comboDrop').innerHTML          = '';
  document.getElementById('gameCombo').classList.remove('open');
  // Select option in Status dropdown
  var statusEl = document.getElementById('gStatus');
  var status   = rec.Status || 'Design';
  for (var i = 0; i < statusEl.options.length; i++) {
    if (statusEl.options[i].value === status) { statusEl.selectedIndex = i; break; }
  }
  document.getElementById('gDescription').value  = rec.Description || '';
  document.getElementById('gTagline').value      = rec.Tagline || rec['Tag Line'] || rec.SubTitle || '';
  document.getElementById('gDesigner1').value    = rec.Designer1 || rec['Designer 1'] || '';
  document.getElementById('gDesigner2').value    = rec.Designer2 || rec['Designer 2'] || '';
  document.getElementById('gDesigner3').value    = rec.Designer3 || rec['Designer 3'] || '';
  document.getElementById('gDesigner4').value    = rec.Designer4 || rec['Designer 4'] || '';
  document.getElementById('gDateStarted').value  = _toDateInput(rec['Date Started']   || rec.DateStarted   || '');
  document.getElementById('gDateSigned').value   = _toDateInput(rec['Date Signed']    || rec.DateSigned    || '');
  document.getElementById('gDatePublished').value= _toDateInput(rec['Date Published'] || rec.DatePublished || '');
  document.getElementById('gRules').value        = rec.Rules    || rec['Rules URL']    || rec.RulesURL    || '';
  document.getElementById('gPlay').value         = rec.Play     || rec['Play URL']     || rec.PlayURL     || '';
  document.getElementById('gPrint').value        = rec.Print    || rec['Print URL']    || rec.PrintURL    || '';
  document.getElementById('gSellsheet').value    = rec.Sellsheet|| rec['Sellsheet URL']|| rec.SellsheetURL|| '';
  document.getElementById('gView').value         = rec.BGG      || rec['BGG / View URL']|| rec.View       || rec['View URL'] || '';
  document.getElementById('gVideo').value        = rec.Video    || rec['Video URL']    || rec.VideoURL    || '';
  document.getElementById('gImage').value        = _stripImageFormula(rec['Image URL'] || rec.Image || rec.ImageURL || '');
  _setNewGameFieldsVisible(true);
  document.getElementById('addErr').style.display  = 'none';
  document.getElementById('addBtn').disabled       = false;
  document.getElementById('addBtn').textContent    = 'Save Game';
  document.getElementById('addOverlay').classList.add('open');
}
// ── Global Escape handler — guard if dirty ────────────────────────────────────
document.addEventListener('keydown', function(ev) {
  if (ev.key !== 'Escape') return;
  var el;
  el = document.getElementById('companyOverlay');
  if (el && el.classList.contains('open')) {
    closeCompanyDialog();
    return;
  }
  el = document.getElementById('profileOverlay');
  if (el && el.classList.contains('open')) {
    closeProfileDialog();
    return;
  }
  el = document.getElementById('contractEditOverlay');
  if (el && el.classList.contains('open')) {
    closeContractEditDialog();
    return;
  }
  el = document.getElementById('contractOverlay');
  if (el && el.classList.contains('open')) {
    closeContractDialog();
    return;
  }
  el = document.getElementById('sessionOverlay');
  if (el.classList.contains('open')) {
    var dlg = el.querySelector('.session-dialog');
    if (_editMode ? isSessionDirty() : hasSessionData()) shakeDialog(dlg);
    else closeSessionDialog();
    return;
  }
  el = document.getElementById('addOverlay');
  if (el.classList.contains('open')) {
    if (hasAddData()) shakeDialog(el.querySelector('.add-dialog'));
    else closeAddDialog();
    return;
  }
});

// ── Dialog dirty-check helpers ────────────────────────────────────────────────
function hasAddData() {
  if (_editGameMode) return false;
  return !!(document.getElementById('gameComboInput').value.trim());
}
function hasSessionData() {
  if ((document.getElementById('sLocation').value || '').trim()) return true;
  // any obs/sol input filled
  var inputs = document.querySelectorAll('#obsContainer input, #obsContainer textarea');
  for (var i = 0; i < inputs.length; i++) {
    if (inputs[i].value.trim()) return true;
  }
  var testerInputs = document.querySelectorAll('#testersContainer input');
  for (var i = 0; i < testerInputs.length; i++) {
    if (testerInputs[i].value.trim()) return true;
  }
  return false;
}
function shakeDialog(dialogEl) {
  dialogEl.classList.remove('dialog-shake');
  void dialogEl.offsetWidth; // force reflow so animation restarts
  dialogEl.classList.add('dialog-shake');
  dialogEl.addEventListener('animationend', function() {
    dialogEl.classList.remove('dialog-shake');
  }, { once: true });
}

function closeAddDialog() {
  document.getElementById('addOverlay').classList.remove('open');
  document.getElementById('gameCombo').classList.remove('open');
}
function comboOpen() { renderComboOptions(document.getElementById('gameComboInput').value); document.getElementById('gameCombo').classList.add('open'); }
function comboFilter() {
  var q = document.getElementById('gameComboInput').value;
  renderComboOptions(q);
  document.getElementById('gameCombo').classList.add('open');
  // Show extra fields only if the typed name is not already in the games sheet
  _setNewGameFieldsVisible(true);
}
function renderComboOptions(q) {
  var drop = document.getElementById('comboDrop');
  var filtered = q.trim()
    ? _comboOptions.filter(function(n) { return n.toLowerCase().indexOf(q.toLowerCase()) !== -1; })
    : _comboOptions;
  _comboHighlight = -1;
  if (!filtered.length) { drop.innerHTML = q.trim() ? '<div class="combo-empty">New game: "' + esc(q.trim()) + '"</div>' : '<div class="combo-empty">All games already tracked, or type a new name.</div>'; return; }
  drop.innerHTML = filtered.map(function(n) { return '<div class="combo-option" data-name="' + esc(n) + '" onmousedown="comboSelect(this.dataset.name)">' + esc(n) + '</div>'; }).join('');
}
function comboSelect(name) {
  document.getElementById('gameComboInput').value = name;
  document.getElementById('gameCombo').classList.remove('open');
  _setNewGameFieldsVisible(true);
}
function comboKey(e) {
  var drop = document.getElementById('comboDrop'); var items = drop.querySelectorAll('.combo-option');
  if (e.key === 'Escape') { document.getElementById('gameCombo').classList.remove('open'); return; }
  if (e.key === 'Enter')  { e.preventDefault(); submitAddGame(); return; }
  if (!items.length) return;
  if (e.key === 'ArrowDown') _comboHighlight = Math.min(_comboHighlight + 1, items.length - 1);
  else if (e.key === 'ArrowUp') _comboHighlight = Math.max(_comboHighlight - 1, 0);
  else return;
  e.preventDefault();
  items.forEach(function(el, i) { el.classList.toggle('highlighted', i === _comboHighlight); });
  if (items[_comboHighlight]) items[_comboHighlight].scrollIntoView({ block:'nearest' });
}
document.addEventListener('click', function(e) {
  var wrap = document.getElementById('gameCombo');
  if (wrap && !wrap.contains(e.target)) wrap.classList.remove('open');
});
/* ── Upload button for IMAGE / VIDEO fields in Edit Game dialog ── */
var _gGameUploadTargetId = '';
var _gGameUploadInput = null;
function gGameUploadClick(inputId) {
  _gGameUploadTargetId = inputId;
  if (!_gGameUploadInput) {
    _gGameUploadInput = document.createElement('input');
    _gGameUploadInput.type = 'file';
    _gGameUploadInput.accept = 'image/*,video/mp4,video/webm';
    _gGameUploadInput.style.display = 'none';
    document.body.appendChild(_gGameUploadInput);
    _gGameUploadInput.addEventListener('change', function() {
      var file = _gGameUploadInput.files[0];
      if (!file || !_gGameUploadTargetId) return;
      _gGameUploadInput.value = '';
      var btn = document.querySelector('#addOverlay [onclick*="' + _gGameUploadTargetId + '"]');
      if (btn) { btn.disabled = true; btn.style.opacity = '.4'; }
      var fd = new FormData();
      fd.append('id', SHEET_ID);
      fd.append('file', file);
      fetch(APP_BASE + 'push/uploadMedia.php', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(res) {
          if (btn) { btn.disabled = false; btn.style.opacity = ''; }
          if (res.url) {
            var el = document.getElementById(_gGameUploadTargetId);
            if (el) { el.value = res.url; }
          } else { alert('Upload failed: ' + (res.error || 'unknown error')); }
        })
        .catch(function() { if (btn) { btn.disabled = false; btn.style.opacity = ''; } });
    });
  }
  _gGameUploadInput.click();
}

function submitAddGame() {
  var name = document.getElementById('gameComboInput').value.trim();
  var err  = document.getElementById('addErr');
  var btn  = document.getElementById('addBtn');
  if (!name) { err.textContent = 'Please enter a game name.'; err.style.display = 'block'; return; }

  // ── Edit mode: update existing game ──────────────────────────────────────────
  if (_editGameMode) {
    btn.disabled = true; btn.textContent = 'Saving…'; err.style.display = 'none';

    // Parse designer fields (strips emails, updates inputs)
    var _gd1e = _parsePerson(document.getElementById('gDesigner1').value.trim());
    var _gd2e = _parsePerson(document.getElementById('gDesigner2').value.trim());
    var _gd3e = _parsePerson(document.getElementById('gDesigner3').value.trim());
    var _gd4e = _parsePerson(document.getElementById('gDesigner4').value.trim());
    document.getElementById('gDesigner1').value = _gd1e.name;
    document.getElementById('gDesigner2').value = _gd2e.name;
    document.getElementById('gDesigner3').value = _gd3e.name;
    document.getElementById('gDesigner4').value = _gd4e.name;

    var fd = new FormData();
    fd.append('id',            SHEET_ID);
    fd.append('orig_name',     _editGameOrigName);
    fd.append('name',          _editGameOrigName);
    fd.append('status',        document.getElementById('gStatus').value);
    fd.append('date_started',  document.getElementById('gDateStarted').value);
    fd.append('date_signed',   document.getElementById('gDateSigned').value);
    fd.append('date_published',document.getElementById('gDatePublished').value);
    fd.append('designer1',     _gd1e.name);
    fd.append('designer2',     _gd2e.name);
    fd.append('designer3',     _gd3e.name);
    fd.append('designer4',     _gd4e.name);
    fd.append('tagline',       document.getElementById('gTagline').value);
    fd.append('description',   document.getElementById('gDescription').value);
    fd.append('rules',         document.getElementById('gRules').value);
    fd.append('play',          document.getElementById('gPlay').value);
    fd.append('print',         document.getElementById('gPrint').value);
    fd.append('sellsheet',     document.getElementById('gSellsheet').value);
    fd.append('view',          document.getElementById('gView').value);
    fd.append('video',         document.getElementById('gVideo').value);
    fd.append('image',         document.getElementById('gImage').value);

    fetch(APP_BASE + 'push/updateGame.php', { method:'POST', body:fd })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        if (res.error) throw new Error(res.error);
        addNewPeople([_gd1e, _gd2e, _gd3e, _gd4e].filter(function(p) { return p.name; }));
        // Update in-memory record
        var key = _editGameOrigName.toLowerCase();
        var rec = GAMES_INDEX[key] || {};
        rec.Status = document.getElementById('gStatus').value;
        rec['Date Started']   = rec.DateStarted   = document.getElementById('gDateStarted').value;
        rec['Date Signed']    = rec.DateSigned    = document.getElementById('gDateSigned').value;
        rec['Date Published'] = rec.DatePublished = document.getElementById('gDatePublished').value;
        rec.Designer1 = rec['Designer 1'] = _gd1e.name;
        rec.Designer2 = rec['Designer 2'] = _gd2e.name;
        rec.Designer3 = rec['Designer 3'] = _gd3e.name;
        rec.Designer4 = rec['Designer 4'] = _gd4e.name;
        rec.Tagline     = document.getElementById('gTagline').value;
        rec.Description = document.getElementById('gDescription').value;
        rec.Rules       = rec['Rules URL']     = document.getElementById('gRules').value;
        rec.Play        = rec['Play URL']      = document.getElementById('gPlay').value;
        rec.Print       = rec['Print URL']     = document.getElementById('gPrint').value;
        rec.Sellsheet   = rec['Sellsheet URL'] = document.getElementById('gSellsheet').value;
        rec.BGG = rec.View = rec['View URL']   = document.getElementById('gView').value;
        rec.Video       = rec['Video URL']     = document.getElementById('gVideo').value;
        rec['Image URL']= rec.Image            = document.getElementById('gImage').value;
        GAMES_INDEX[key] = rec;
        GAMES_RAW.forEach(function(g) { if ((g.Name||'').toLowerCase() === key) g.Status = rec.Status; });
        // Refresh card body if cached, then rebuild list
        if (devCache[_editGameOrigName] !== undefined) {
          renderBody(_editGameOrigName, devCache[_editGameOrigName] || []);
        }
        buildGameList();
        renderCards();
        closeAddDialog();
      })
      .catch(function(e) {
        err.textContent = e.message || 'Could not save changes.';
        err.style.display = 'block';
        btn.disabled = false; btn.textContent = 'Save Game';
      });
    return;
  }

  if (isActive(name)) { err.textContent = '"' + name + '" is already tracked.'; err.style.display = 'block'; return; }
  btn.disabled = true; btn.textContent = 'Adding…'; err.style.display = 'none';

  var isNewGame = !_existingNames[name.toLowerCase()];

  function createTab() {
    var fd = new FormData(); fd.append('id', SHEET_ID); fd.append('game', name);
    return fetch(APP_BASE + 'push/createDevTab.php', { method:'POST', body:fd })
      .then(function(r) { return r.json(); });
  }

  // Parse designer fields: strip emails, update inputs to show name only
  var _gd1 = _parsePerson(document.getElementById('gDesigner1').value.trim());
  var _gd2 = _parsePerson(document.getElementById('gDesigner2').value.trim());
  var _gd3 = _parsePerson(document.getElementById('gDesigner3').value.trim());
  var _gd4 = _parsePerson(document.getElementById('gDesigner4').value.trim());
  document.getElementById('gDesigner1').value = _gd1.name;
  document.getElementById('gDesigner2').value = _gd2.name;
  document.getElementById('gDesigner3').value = _gd3.name;
  document.getElementById('gDesigner4').value = _gd4.name;

  function addToGamesSheet() {
    var fd = new FormData();
    fd.append('id',            SHEET_ID);
    fd.append('name',          name);
    fd.append('status',        document.getElementById('gStatus').value);
    fd.append('date_started',  document.getElementById('gDateStarted').value);
    fd.append('date_signed',   document.getElementById('gDateSigned').value);
    fd.append('date_published',document.getElementById('gDatePublished').value);
    fd.append('designer1',     _gd1.name);
    fd.append('designer2',     _gd2.name);
    fd.append('designer3',     _gd3.name);
    fd.append('designer4',     _gd4.name);
    fd.append('tagline',       document.getElementById('gTagline').value);
    fd.append('description',   document.getElementById('gDescription').value);
    fd.append('rules',         document.getElementById('gRules').value);
    fd.append('play',          document.getElementById('gPlay').value);
    fd.append('print',         document.getElementById('gPrint').value);
    fd.append('sellsheet',     document.getElementById('gSellsheet').value);
    fd.append('view',          document.getElementById('gView').value);
    fd.append('video',         document.getElementById('gVideo').value);
    fd.append('image',         document.getElementById('gImage').value);
    return fetch(APP_BASE + 'push/addGame.php', { method:'POST', body:fd })
      .then(function(r) { return r.json(); });
  }

  var chain = isNewGame
    ? addToGamesSheet().then(function(res) { if (res.error) throw new Error(res.error); return createTab(); })
    : createTab();

  chain
    .then(function(res) {
      if (res.error) throw new Error(res.error);
      ACTIVE_KEYS[name.toLowerCase()] = true;
      if (isNewGame) {
        GAMES_RAW.push({ Name: name, Status: document.getElementById('gStatus').value });
        _existingNames[name.toLowerCase()] = true;
        // Add designers to People sheet
        addNewPeople([_gd1, _gd2].filter(function(p) { return p.name; }));
      } else {
        extraGames.push(name);
      }
      buildGameList();
      renderCards();
      closeAddDialog();
    })
    .catch(function(e) {
      err.textContent = e.message || 'Could not add game.';
      err.style.display = 'block';
      btn.disabled = false; btn.textContent = 'Add Game';
    });
}

// ── Session numbering ─────────────────────────────────────────────────────────

// Count existing sessions of a given type for a game, return next session number e.g. "3"
// New schema: Event = type only ("Playtest"), People = number ("3")
// Old schema: Event = "Playtest 1" — prefix match on testnum handles both transparently.
function nextSessionLabel(gameName, type) {
  var rows     = devCache[gameName] || [];
  var sessions = buildSessions(rows);
  var prefix   = type.toLowerCase() + ' ';
  var count    = sessions.filter(function(s) {
    return s.testnum.toLowerCase().indexOf(prefix) === 0;
  }).length;
  return String(count + 1);
}

function onTypeChange() {
  var type = document.getElementById('sType').value;
  document.getElementById('sTestNum').value = nextSessionLabel(_sessionGame, type);
}

// ── Session dialog ────────────────────────────────────────────────────────────

var _sessionGame        = '';
var _editMode           = false;
var _editOrigDate       = '';
var _editOrigEvent      = '';
var _editOrigSessionNum = '';
var _editSnapshot       = null;

// ── Stopwatch ─────────────────────────────────────────────────────────────────
var _swSeconds  = 0;
var _swRunning  = false;
var _swInterval = null;

function _swFormat(secs) {
  var h  = Math.floor(secs / 3600);
  var m  = Math.floor((secs % 3600) / 60);
  var s  = secs % 60;
  var mm = String(m).padStart(2, '0');
  var ss = String(s).padStart(2, '0');
  return h > 0 ? h + ':' + mm + ':' + ss : mm + ':' + ss;
}

function _swUpdate() {
  var timeStr = _swFormat(_swSeconds);
  var swTime = document.getElementById('swTime');
  if (swTime) swTime.textContent = timeStr;
}

// Parse "Length: MM:SS" or "Length: H:MM:SS" → total seconds
function _swParseLength(str) {
  str = (str || '').trim();
  var m = str.match(/Length:\s*(\d+):(\d+):(\d+)/);
  if (m) return parseInt(m[1]) * 3600 + parseInt(m[2]) * 60 + parseInt(m[3]);
  m = str.match(/Length:\s*(\d+):(\d+)/);
  if (m) return parseInt(m[1]) * 60 + parseInt(m[2]);
  return 0;
}

var _swLongPressTimer = null;

function toggleStopwatch() {
  var btn = document.getElementById('swBtn');
  if (_swRunning) {
    // Pause
    clearInterval(_swInterval);
    _swInterval = null;
    _swRunning = false;
    if (btn) btn.classList.remove('sw-running');
  } else {
    // Start / resume
    _swRunning = true;
    if (btn) btn.classList.add('sw-running');
    _swInterval = setInterval(function() {
      _swSeconds++;
      _swUpdate();
    }, 1000);
  }
  _swUpdate();
}

function _swStartLongPress() {
  _swLongPressTimer = setTimeout(function() {
    _swLongPressTimer = null;
    _swReset();
  }, 600);
}

function _swCancelLongPress(e) {
  if (_swLongPressTimer) {
    clearTimeout(_swLongPressTimer);
    _swLongPressTimer = null;
  } else {
    // Long press fired — prevent the click from also toggling
    e.preventDefault();
    e.stopPropagation();
  }
}

function _swReset() {
  clearInterval(_swInterval);
  _swInterval = null;
  _swRunning  = false;
  _swSeconds  = 0;
  var btn = document.getElementById('swBtn');
  if (btn) btn.classList.remove('sw-running');
  _swUpdate();
}

function getSessionSnapshot() {
  var testers = [];
  document.querySelectorAll('#testersContainer input').forEach(function(el) {
    var v = el.value.trim(); if (v) testers.push(v);
  });
  var obs = [];
  document.querySelectorAll('#obsContainer .obs-pair').forEach(function(pair) {
    var idx = pair.dataset.idx;
    var o = (document.getElementById('sObs-' + idx) || {}).value || '';
    var s = (document.getElementById('sSol-' + idx) || {}).value || '';
    if (o.trim() || s.trim()) obs.push(o.trim() + '|' + s.trim());
  });
  return [
    document.getElementById('sDate').value,
    document.getElementById('sType').value,
    (document.getElementById('sLocation').value || '').trim(),
    (document.getElementById('sTestNum').value  || '').trim(),
    testers.join(','),
    obs.join('||')
  ].join('\n');
}
function isSessionDirty() {
  return _editSnapshot !== null && getSessionSnapshot() !== _editSnapshot;
}

function openSessionDialog(gameName) {
  _editMode = false;
  _sessionGame = gameName;
  document.getElementById('sessionDialogAction').textContent = '+ Session';
  document.getElementById('sessionGameTitle').textContent    = gameName;
  document.getElementById('sDate').value     = todayISO();
  document.getElementById('sType').value     = 'Playtest';
  document.getElementById('sLocation').value = '';
  document.getElementById('sTestNum').value  = nextSessionLabel(_sessionGame, 'Playtest');

  // Reset dynamic lists
  _testerCount = 0; _testersHL = {};
  document.getElementById('testersContainer').innerHTML = '';
  addTesterField('Add tester…');

  _obsCount = 0; _obsImages = {};
  document.getElementById('obsContainer').innerHTML = '';
  addObsPair(true);  // first pair with labels

  document.getElementById('sessionErr').style.display = 'none';
  document.getElementById('sessionBtn').disabled    = false;
  document.getElementById('sessionBtn').textContent = 'Add Session';
  _swReset();  // each new session starts the clock at 00:00
  document.getElementById('sessionOverlay').classList.add('open');
  setTimeout(function() {
    var firstObs = document.getElementById('sObs-0');
    if (firstObs) firstObs.focus();
  }, 80);
}

function openEditSessionDialog(gameName, idx) {
  var cache   = _sessionCache[gameName];
  var session = cache && cache[idx];
  if (!session) return;

  _editMode      = true;
  _editOrigDate  = session.date;
  _sessionGame   = gameName;

  // Resolve event type and session number.
  // New schema: session.eventType = "Playtest", session.sessionNum = "3"
  // Legacy schema: session.eventType = "Playtest 3", session.sessionNum = "" — parse it.
  var editEventType  = session.eventType || '';
  var editSessionNum = session.sessionNum || '';
  if (!editSessionNum) {
    var legacyMatch = editEventType.match(/^(.*?)\s+(\d+)$/);
    if (legacyMatch) { editEventType = legacyMatch[1]; editSessionNum = legacyMatch[2]; }
  }
  _editOrigEvent      = editEventType;
  _editOrigSessionNum = editSessionNum;

  // Load existing session length into the stopwatch (paused)
  _swReset();
  _swSeconds = _swParseLength(session.length);
  _swUpdate();

  document.getElementById('sessionDialogAction').textContent = 'Edit Session';
  document.getElementById('sessionGameTitle').textContent    = gameName;
  document.getElementById('sDate').value     = session.date     || '';
  document.getElementById('sLocation').value = session.location || '';
  document.getElementById('sType').value     = editEventType;
  document.getElementById('sTestNum').value  = editSessionNum;

  // Pre-fill testers
  _testerCount = 0; _testersHL = {};
  document.getElementById('testersContainer').innerHTML = '';
  session.testers.forEach(function(t) {
    var tidx = addTesterField('Select or type…');
    document.getElementById('sTesters-' + tidx).value = t;
  });
  addTesterField('Add tester…');  // trailing empty field

  // Pre-fill obs/sol pairs
  _obsCount = 0; _obsImages = {};
  document.getElementById('obsContainer').innerHTML = '';
  session.obs.forEach(function(pair, pi) {
    var oidx = addObsPair(pi === 0);
    var obsVal = pair.obs || '';
    var imgMatch = obsVal.match(/=IMAGE\("([^"]*)"\)/i);
    if (imgMatch) {
      var imgUrl = imgMatch[1];
      setTimeout(function(i, u) { return function() { _showObsImage(i, u); }; }(oidx, imgUrl), 0);
      obsVal = obsVal.replace(/=IMAGE\("[^"]*"\)/gi, '').trim();
    }
    document.getElementById('sObs-' + oidx).value = obsVal;
    toggleObsImgBtn(oidx);
    document.getElementById('sSol-' + oidx).value = pair.sol || '';
  });
  addObsPair(session.obs.length === 0);  // trailing empty pair (shows labels if first)

  document.getElementById('sessionErr').style.display = 'none';
  document.getElementById('sessionBtn').disabled    = false;
  document.getElementById('sessionBtn').textContent = 'Save Changes';
  _editSnapshot = getSessionSnapshot();
  _swUpdate();
  document.getElementById('sessionOverlay').classList.add('open');
  // Resize textareas after the overlay is visible so scrollHeight is accurate
  setTimeout(function() {
    document.querySelectorAll('#obsContainer .field-textarea').forEach(autoResize);
  }, 0);
}

function closeSessionDialog() {
  document.getElementById('sessionOverlay').classList.remove('open');
  _editMode = false;
  _editSnapshot = null;
}

function submitSession() {
  var err = document.getElementById('sessionErr');
  var btn = document.getElementById('sessionBtn');

  // Collect testers — strip embedded emails from inputs, keep raw for People sync
  var testerVals = [];   // clean names → session sheet
  var testerRaws = [];   // original values (may include email) → People sheet
  document.querySelectorAll('#testersContainer input').forEach(function(el) {
    var raw = el.value.trim();
    if (!raw) return;
    var p = _parsePerson(raw);
    el.value = p.name;       // update display to show name only
    testerVals.push(p.name);
    testerRaws.push(raw);
  });

  // Collect obs/sol pairs (skip entirely empty)
  var obsPairs = [];
  document.querySelectorAll('#obsContainer .obs-pair').forEach(function(pair) {
    var idx = pair.dataset.idx;
    var obs = (document.getElementById('sObs-' + idx) || {}).value || '';
    var sol = (document.getElementById('sSol-' + idx) || {}).value || '';
    obs = obs.trim(); sol = sol.trim();
    if (_obsImages[idx]) obs = '=IMAGE("' + _obsImages[idx] + '")';
    if (obs || sol) obsPairs.push({ obs: obs, sol: sol });
  });

  if (!obsPairs.length) {
    err.textContent = 'At least one observation is required.';
    err.style.display = 'block';
    return;
  }

  var date       = document.getElementById('sDate').value || todayISO();
  var eventType  = document.getElementById('sType').value;
  var sessionNum = document.getElementById('sTestNum').value.trim();
  var location   = document.getElementById('sLocation').value.trim();
  var swLength   = _swSeconds > 0 ? 'Length: ' + _swFormat(_swSeconds) : '';

  // ── Build local cache rows (same shape as sheet JSON) for optimistic update ──
  var localRows = [];
  localRows.push({ 'Date': date, 'Event': eventType, 'People': sessionNum,
                   'Observations': location, 'Thoughts': swLength });
  testerVals.forEach(function(t) {
    localRows.push({ 'Date': '', 'Event': '', 'People': t, 'Observations': '', 'Thoughts': '' });
  });
  obsPairs.forEach(function(pair) {
    localRows.push({ 'Date': '', 'Event': '', 'People': '', 'Observations': pair.obs, 'Thoughts': pair.sol });
  });

  // ── Edit mode: optimistically patch the cache, close immediately, save in bg ──
  if (_editMode) {
    // Find the old session header in cache and splice in the new rows
    var cache = devCache[_sessionGame] || [];
    var startIdx = -1;
    for (var ci = 0; ci < cache.length; ci++) {
      var cr = cache[ci];
      if ((cr['Date']||'').trim() === _editOrigDate &&
          (cr['Event']||'').trim() === _editOrigEvent) {
        if (!_editOrigSessionNum || (cr['People']||'').trim() === _editOrigSessionNum) {
          startIdx = ci; break;
        }
      }
    }
    if (startIdx >= 0) {
      var endIdx = startIdx + 1;
      while (endIdx < cache.length && !(cache[endIdx]['Date'] || cache[endIdx]['Event'])) endIdx++;
      cache.splice.apply(cache, [startIdx, endIdx - startIdx].concat(localRows));
    }
    addNewPeople(testerRaws);
    renderBody(_sessionGame, devCache[_sessionGame]);
    closeSessionDialog();

    // Background: persist to sheet, then refresh cache from server to confirm
    var fd = new FormData();
    fd.append('id',               SHEET_ID);
    fd.append('game',             _sessionGame);
    fd.append('orig_date',        _editOrigDate);
    fd.append('orig_event',       _editOrigEvent);
    fd.append('orig_session_num', _editOrigSessionNum);
    fd.append('date',             date);
    fd.append('event',            eventType);
    fd.append('session_num',      sessionNum);
    fd.append('location',         location);
    fd.append('length',           swLength);
    fd.append('testers',          JSON.stringify(testerVals));
    fd.append('obs_pairs',        JSON.stringify(obsPairs));
    fetch(APP_BASE + 'push/updateDevSession.php', { method:'POST', body:fd })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        if (res.error) throw new Error(res.error);
        devCache[_sessionGame] = undefined;
        loadDevData(_sessionGame);
      })
      .catch(function(e) {
        showSaveToast('Couldn\'t save changes to sheet — ' + (e.message || 'unknown error') + '. Reload to try again.');
      });
    return;
  }

  // ── Add mode: push to local cache, close immediately, send rows in background ──
  if (!devCache[_sessionGame]) devCache[_sessionGame] = [];
  localRows.forEach(function(r) { devCache[_sessionGame].push(r); });
  addNewPeople(testerRaws);
  renderBody(_sessionGame, devCache[_sessionGame]);
  closeSessionDialog();

  // Background: post rows sequentially to preserve sheet row order
  var postRows = [];
  postRows.push({ date: date, event: eventType, session_num: sessionNum, observation: location, solution: swLength, type: 'header' });
  testerVals.forEach(function(t) {
    postRows.push({ date: '', event: '', observation: t, solution: '', type: 'tester' });
  });
  obsPairs.forEach(function(pair) {
    postRows.push({ date: '', event: '', observation: pair.obs, solution: pair.sol, type: 'obs' });
  });

  function postRow(row) {
    var fd = new FormData();
    fd.append('id',          SHEET_ID);
    fd.append('game',        _sessionGame);
    fd.append('date',        row.date);
    fd.append('event',       row.event);
    fd.append('session_num', row.session_num || '');
    fd.append('observation', row.observation);
    fd.append('solution',    row.solution);
    fd.append('row_type',    row.type || '');
    return fetch(APP_BASE + 'push/addDevRow.php', { method:'POST', body:fd })
      .then(function(r) { return r.json(); });
  }

  postRows.reduce(function(chain, row) {
    return chain.then(function() { return postRow(row); });
  }, Promise.resolve())
  .catch(function(e) {
    showSaveToast('Couldn\'t save session to sheet — ' + (e.message || 'unknown error') + '. Reload to try again.');
  });
}

// ── People sheet sync ────────────────────────────────────────────────────────
// After a session or game save, silently add any new names to the People sheet.
// If a name string contains an embedded email (e.g. "Jane Smith jane@co.com"),
// the email is extracted and stored separately; the session sheet keeps the
// original full string unchanged.

function _parsePerson(raw) {
  var m = raw.match(/(\S+@\S+\.\S+)/);
  if (!m) return { name: raw.trim(), email: '' };
  var email = m[1];
  var name  = raw.replace(email, '').trim();
  return { name: name || raw.trim(), email: email };
}

// items: array of strings (may include embedded email) or {name, email} objects
function addNewPeople(items) {
  var known = {};
  PEOPLE_NAMES.forEach(function(n) { known[n.toLowerCase()] = true; });
  items.forEach(function(item) {
    var parsed = (typeof item === 'string') ? _parsePerson(item.trim()) : item;
    if (!parsed.name) return;
    var key = parsed.name.toLowerCase();
    if (known[key]) return;
    known[key] = true;
    PEOPLE_NAMES.push(parsed.name);   // update in-memory autocomplete list
    var fd = new FormData();
    fd.append('id',      SHEET_ID);
    fd.append('name',    parsed.name);
    fd.append('email',   parsed.email || '');
    fd.append('company', '');
    fetch(APP_BASE + 'push/addPerson.php', { method:'POST', body:fd }).catch(function(){});
  });
}

// ── Dynamic testers ───────────────────────────────────────────────────────────

var _testerCount = 0;
var _testersHL   = {};  // idx → highlighted index

function addTesterField(placeholder) {
  var idx       = _testerCount++;
  var container = document.getElementById('testersContainer');
  var div       = document.createElement('div');
  div.className    = 'tester-row';
  div.dataset.idx  = idx;
  _testersHL[idx]  = -1;
  div.innerHTML =
    '<div class="combo-wrap" id="testersCombo-' + idx + '">' +
      '<input type="text" class="field-input combo-input" id="sTesters-' + idx + '"' +
        ' placeholder="' + esc(placeholder || 'Add tester…') + '" autocomplete="off"' +
        ' oninput="onTesterInput(' + idx + ')"' +
        ' onfocus="testersOpen(' + idx + ')"' +
        ' onkeydown="testersKey(event,' + idx + ')" />' +
      '<div class="combo-dropdown" id="testersDrop-' + idx + '"></div>' +
    '</div>';
  container.appendChild(div);
  return idx;
}

function onTesterInput(idx) {
  testersFilter(idx);
  // If this is the last field and has content, add a new empty one
  var rows = document.querySelectorAll('#testersContainer .tester-row');
  var last = rows[rows.length - 1];
  if (last && parseInt(last.dataset.idx) === idx) {
    var val = document.getElementById('sTesters-' + idx).value.trim();
    if (val) addTesterField();
  }
}

function testersOpen(idx) {
  renderTestersOptions(idx, document.getElementById('sTesters-' + idx).value);
  document.getElementById('testersCombo-' + idx).classList.add('open');
}
function testersFilter(idx) {
  renderTestersOptions(idx, document.getElementById('sTesters-' + idx).value);
  document.getElementById('testersCombo-' + idx).classList.add('open');
}
function renderTestersOptions(idx, q) {
  var drop = document.getElementById('testersDrop-' + idx);
  if (!drop) return;
  var filtered = q.trim()
    ? PEOPLE_NAMES.filter(function(n) { return n.toLowerCase().indexOf(q.trim().toLowerCase()) !== -1; })
    : PEOPLE_NAMES.slice();
  _testersHL[idx] = -1;
  if (!filtered.length) { drop.innerHTML = '<div class="combo-empty">No matching people.</div>'; return; }
  drop.innerHTML = filtered.map(function(n) {
    return '<div class="combo-option" data-name="' + esc(n) + '" onmousedown="testersSelect(' + idx + ',this.dataset.name)">' + esc(n) + '</div>';
  }).join('');
}
function testersSelect(idx, name) {
  document.getElementById('sTesters-' + idx).value = name;
  document.getElementById('testersCombo-' + idx).classList.remove('open');
  // Add a new field if this was the last and now has content
  var rows = document.querySelectorAll('#testersContainer .tester-row');
  var last = rows[rows.length - 1];
  if (last && parseInt(last.dataset.idx) === idx) addTesterField();
}
function testersKey(e, idx) {
  var drop  = document.getElementById('testersDrop-' + idx);
  var items = drop ? drop.querySelectorAll('.combo-option') : [];
  if (e.key === 'Escape') { document.getElementById('testersCombo-' + idx).classList.remove('open'); return; }
  if (!items.length) return;
  if (e.key === 'ArrowDown') _testersHL[idx] = Math.min((_testersHL[idx]||0) + 1, items.length - 1);
  else if (e.key === 'ArrowUp') _testersHL[idx] = Math.max((_testersHL[idx]||0) - 1, 0);
  else return;
  e.preventDefault();
  items.forEach(function(el, i) { el.classList.toggle('highlighted', i === _testersHL[idx]); });
  if (items[_testersHL[idx]]) items[_testersHL[idx]].scrollIntoView({ block:'nearest' });
}
document.addEventListener('click', function(e) {
  document.querySelectorAll('.combo-wrap').forEach(function(wrap) {
    if (!wrap.contains(e.target)) wrap.classList.remove('open');
  });
});

// ── Dynamic obs/sol pairs ─────────────────────────────────────────────────────

var _obsCount = 0;
var _obsImages = {};

function toggleObsImgBtn(idx) {
  var ta   = document.getElementById('sObs-' + idx);
  var wrap = ta ? ta.closest('.obs-ta-wrap') : null;
  if (!wrap) return;
  wrap.classList.toggle('has-obs-text', ta.value.trim().length > 0);
}  // obs pair idx → uploaded image URL

function _showObsImage(idx, url) {
  _obsImages[idx] = url;
  var ta   = document.getElementById('sObs-' + idx);
  var wrap = ta ? ta.closest('.obs-ta-wrap') : null;
  var pv   = document.getElementById('sImgPreview-' + idx);
  if (ta)   ta.style.display = 'none';   // hide textarea
  if (wrap) wrap.classList.remove('has-obs-text');  // always show replace btn
  if (pv) { pv.style.display = 'block'; pv.innerHTML = _obsImgPreviewHtml(idx, url); }
}

function _obsImgPreviewHtml(idx, url) {
  // Just the image — the .obs-img-btn in obs-ta-wrap overlays it at top-right
  return '<img src="' + esc(url) + '" alt="observation image">';
}

function triggerObsImageUpload(idx) {
  var inp = document.getElementById('sImgFile-' + idx);
  if (inp) inp.click();
}

function handleObsImageFile(idx, file) {
  if (!file) return;
  var fd = new FormData();
  fd.append('id',   SHEET_ID);
  fd.append('file', file);
  fetch(APP_BASE + 'push/uploadMedia.php', { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (!res.ok || !res.url) { alert('Upload failed: ' + (res.error || 'Unknown error')); return; }
      _showObsImage(idx, res.url);
    })
    .catch(function() { alert('Upload failed.'); });
}

function addObsPair(showLabels) {
  var idx       = _obsCount++;
  var container = document.getElementById('obsContainer');
  var div       = document.createElement('div');
  div.className   = 'obs-pair obs-pair-empty';
  div.dataset.idx = idx;
  var labelsHtml = showLabels
    ? '<div class="obs-pair-labels"><label>Observations</label><label>Thoughts</label></div>'
    : '';
  div.innerHTML = labelsHtml +
    '<div class="obs-pair-inputs">' +
      '<div class="obs-obs-col">' +
        '<div class="obs-ta-wrap">' +
          '<textarea class="field-textarea" id="sObs-' + idx + '" rows="1"' +
            ' placeholder="What happened…"' +
            ' oninput="autoResize(this);onObsInput(' + idx + ');toggleObsImgBtn(' + idx + ')"' +
            ' onkeydown="onObsKeydown(event,' + idx + ',0)"></textarea>' +
          '<div class="obs-img-preview" id="sImgPreview-' + idx + '" style="display:none"></div>' +
          '<button type="button" class="obs-img-btn" onclick="triggerObsImageUpload(' + idx + ')" title="Attach image">' +
            '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
              '<rect x="3" y="3" width="18" height="18" rx="2"/>' +
              '<circle cx="8.5" cy="8.5" r="1.5"/>' +
              '<polyline points="21 15 16 10 5 21"/>' +
            '</svg>' +
          '</button>' +
          '<input type="file" accept="image/*" id="sImgFile-' + idx + '" style="display:none" onchange="handleObsImageFile(' + idx + ', this.files[0])">' +
        '</div>' +
      '</div>' +
      '<textarea class="field-textarea" id="sSol-' + idx + '" rows="1"' +
        ' placeholder="Thoughts…"' +
        ' oninput="autoResize(this);onObsInput(' + idx + ')"' +
        ' onkeydown="onObsKeydown(event,' + idx + ',1)"></textarea>' +
    '</div>';
  container.appendChild(div);
  return idx;
}

function autoResize(el) {
  el.style.height = 'auto';
  el.style.height = el.scrollHeight + 'px';
}

function onObsInput(idx) {
  var pairs = document.querySelectorAll('#obsContainer .obs-pair');
  var last  = pairs[pairs.length - 1];
  if (!last || parseInt(last.dataset.idx) !== idx) return;
  var obs = (document.getElementById('sObs-' + idx) || {}).value || '';
  var sol = (document.getElementById('sSol-' + idx) || {}).value || '';
  if (obs.trim() || sol.trim()) {
    last.classList.remove('obs-pair-empty');
    addObsPair(false);
  }
}

// ── Keyboard navigation between obs/sol textareas ────────────────────────────
// Cmd/Ctrl + Arrow moves focus within the grid:
//   Left/Right → same row, other column   Up/Down → same column, adjacent row

function onObsKeydown(e, idx, col) {
  if (!e.metaKey && !e.ctrlKey) return;
  var dir = e.key;
  if (dir !== 'ArrowLeft' && dir !== 'ArrowRight' && dir !== 'ArrowUp' && dir !== 'ArrowDown') return;
  e.preventDefault();

  var pairs    = Array.from(document.querySelectorAll('#obsContainer .obs-pair'));
  var pairIdx  = pairs.findIndex(function(p) { return parseInt(p.dataset.idx, 10) === idx; });
  var tgtCol   = col;
  var tgtPairI = pairIdx;

  if      (dir === 'ArrowRight') tgtCol   = 1;
  else if (dir === 'ArrowLeft')  tgtCol   = 0;
  else if (dir === 'ArrowDown')  tgtPairI = Math.min(pairIdx + 1, pairs.length - 1);
  else if (dir === 'ArrowUp')    tgtPairI = Math.max(pairIdx - 1, 0);

  var tgtPair = pairs[tgtPairI];
  if (!tgtPair) return;
  var tgtDataIdx = parseInt(tgtPair.dataset.idx, 10);
  var tgtEl = document.getElementById((tgtCol === 0 ? 'sObs-' : 'sSol-') + tgtDataIdx);
  if (tgtEl) tgtEl.focus();
}

// ── Touch long-press → Edit session ──────────────────────────────────────────
// On touch devices, a long press (600 ms) on a session header opens the edit
// dialog. A normal tap still expands/collapses via the click handler.


// ── Account menu ─────────────────────────────────────────────────────────────

function toggleAccountMenu() {
  var menu = document.getElementById('accountMenu');
  menu.classList.toggle('open');
}
function closeAccountMenu() {
  document.getElementById('accountMenu').classList.remove('open');
}
function accountMenuProfile() { closeAccountMenu(); openProfileDialog(); }
function accountMenuCompany() { closeAccountMenu(); openCompanyDialog(); }
function accountMenuFetch()   { closeAccountMenu(); doFetch(); }
function accountMenuRelease() { closeAccountMenu(); openRnDialog(); }
function accountMenuHelp()    { closeAccountMenu(); window.open(APP_BASE + 'devboard/help', '_blank'); }

document.addEventListener('click', function(e) {
  var wrap = document.querySelector('.account-menu-wrap');
  if (wrap && !wrap.contains(e.target)) closeAccountMenu();
});

// ── Release notes dialog ──────────────────────────────────────────────────────

function openRnDialog() {
  var overlay = document.getElementById('rnOverlay');
  var body    = document.getElementById('rnBody');
  body.innerHTML = '<span style="color:#aaa;font-size:.8rem">Loading…</span>';
  overlay.classList.add('open');
  var xhr = new XMLHttpRequest();
  xhr.open('GET', APP_BASE + 'changelog.json?v=' + Date.now());
  xhr.onload = function() {
    var all;
    try { all = JSON.parse(xhr.responseText); } catch(e) { all = null; }
    if (!all || !all.length) {
      body.innerHTML = '<span style="color:#aaa;font-size:.8rem">No release notes available.</span>';
      return;
    }
    var releases = all.filter(function(r) {
      return !r.apps || r.apps.indexOf('devboard') !== -1;
    });
    if (!releases.length) {
      body.innerHTML = '<span style="color:#aaa;font-size:.8rem">No release notes available.</span>';
      return;
    }
    var html = '';
    releases.forEach(function(r) {
      html += '<div class="rn-release">';
      html += '<div class="rn-version">' + esc(r.version || '') + '</div>';
      (r.features || []).forEach(function(f) {
        html += '<div class="rn-feature">' + esc(f) + '</div>';
      });
      html += '</div>';
    });
    body.innerHTML = html;
  };
  xhr.onerror = function() {
    body.innerHTML = '<span style="color:#aaa;font-size:.8rem">Could not load release notes.</span>';
  };
  xhr.send();
}
function closeRnDialog() {
  document.getElementById('rnOverlay').classList.remove('open');
}

// ── Init ──────────────────────────────────────────────────────────────────────

// ── Company dialog ─────────────────────────────────────────────────────────────
var _companyInitial  = {};
var _companyLogoUrl  = '';
function _companyIsDirty() {
  var name = (document.getElementById('companyName').value || '').trim();
  return name !== (_companyInitial.name || '') ||
         document.getElementById('companyLogoFile').files.length > 0;
}
function _companyLog(msg, type) {
  var el = document.getElementById('companyLog');
  el.textContent = msg;
  el.style.display = msg ? '' : 'none';
  el.style.color = type === 'ok' ? '#1a5f7a' : '#b91c1c';
}
function _companyShowLogo(url) {
  var img = document.getElementById('companyLogoImg');
  var ph  = document.getElementById('companyLogoPlaceholder');
  if (url) { img.src = url; img.style.display = ''; ph.style.display = 'none'; }
  else     { img.style.display = 'none'; ph.style.display = ''; img.src = ''; }
}
function companyLogoFileChange(input) {
  if (!input.files || !input.files[0]) return;
  var reader = new FileReader();
  reader.onload = function(e) { _companyShowLogo(e.target.result); };
  reader.readAsDataURL(input.files[0]);
}
function openCompanyDialog() {
  document.getElementById('companyName').value = MY_COMPANY || '';
  document.getElementById('companyLogoFile').value = '';
  _companyLogoUrl = MY_LOGO || '';
  _companyShowLogo(_companyLogoUrl);
  _companyLog('', '');
  document.getElementById('companySaveBtn').disabled   = false;
  document.getElementById('companyCancelBtn').disabled = false;
  document.getElementById('companyCancelBtn').textContent = 'Cancel';
  _companyInitial = { name: MY_COMPANY || '' };
  document.getElementById('companyOverlay').classList.add('open');
}
function closeCompanyDialog() {
  if (_companyIsDirty()) { shakeDialog(document.getElementById('companyOverlay').querySelector('.sync-dialog')); return; }
  forceCloseCompanyDialog();
}
function forceCloseCompanyDialog() {
  document.getElementById('companyOverlay').classList.remove('open');
}
function submitCompany() {
  var name      = document.getElementById('companyName').value.trim();
  var logoFile  = document.getElementById('companyLogoFile').files[0];
  document.getElementById('companySaveBtn').disabled   = true;
  document.getElementById('companyCancelBtn').disabled = true;
  _companyLog('Saving…', '');
  // Step 1: upload logo file if a new one was picked
  var logoPromise = Promise.resolve(_companyLogoUrl);
  if (logoFile) {
    _companyLog('Uploading logo…', '');
    var ufd = new FormData();
    ufd.append('id', SHEET_ID);
    ufd.append('file', logoFile);
    logoPromise = fetch(APP_BASE + 'push/uploadMedia.php', { method:'POST', body:ufd })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        if (!res || res.error) throw new Error(res.error || 'Upload failed');
        return res.url;
      });
  }
  // Step 2: save name + logo URL to settings
  logoPromise.then(function(logoUrl) {
    _companyLogoUrl = logoUrl || '';
    var fd = new FormData();
    fd.append('id',       SHEET_ID);
    fd.append('name',     MY_NAME  || '');
    fd.append('email',    MY_EMAIL || '');
    fd.append('phone',    MY_PHONE || '');
    fd.append('company',  name);
    fd.append('logo_url', _companyLogoUrl);
    return fetch(APP_BASE + 'push/updateProfile.php', { method:'POST', body:fd })
      .then(function(r) { return r.json(); });
  })
  .then(function(res) {
    if (res.error) throw new Error(res.error);
    MY_COMPANY = name;
    MY_LOGO    = _companyLogoUrl;
    _updateTopBarLogo(MY_LOGO, MY_COMPANY);
    // Keep profile dialog in sync if open
    var pCo = document.getElementById('profileCompany');
    if (pCo) pCo.value = MY_COMPANY;
    var pLo = document.getElementById('profileLogoUrl');
    if (pLo) { pLo.value = MY_LOGO; profileLogoPreview(); }
    document.getElementById('companyLogoFile').value = '';
    _companyInitial = { name: MY_COMPANY };
    _companyLog('✓  Saved', 'ok');
    document.getElementById('companySaveBtn').disabled   = true;
    document.getElementById('companyCancelBtn').disabled = false;
    document.getElementById('companyCancelBtn').textContent = 'Close';
  })
  .catch(function(err) {
    _companyLog('✕  ' + (err.message || 'Error'), 'error');
    document.getElementById('companySaveBtn').disabled   = false;
    document.getElementById('companyCancelBtn').disabled = false;
  });
}

// ── Profile dialog ─────────────────────────────────────────────────────────────
var _profileInitial = {};
function _profileIsDirty() {
  var f = function(id) { return (document.getElementById(id).value || '').trim(); };
  return f('profileName')     !== (_profileInitial.name     || '') ||
         f('profileEmail')    !== (_profileInitial.email    || '') ||
         f('profilePhone')    !== (_profileInitial.phone    || '') ||
         f('profileCompany')  !== (_profileInitial.company  || '') ||
         f('profileLogoUrl')  !== (_profileInitial.logo_url || '') ||
         f('profileDesc')     !== (_profileInitial.desc     || '') ||
         f('profileSkills')   !== (_profileInitial.skills   || '') ||
         f('profileLocation') !== (_profileInitial.location || '') ||
         f('profileDiscord')  !== (_profileInitial.discord  || '') ||
         f('profilePayment')  !== (_profileInitial.payment  || '') ||
         f('profileNotes')    !== (_profileInitial.notes    || '') ||
         document.getElementById('profilePhotoFile').files.length > 0;
}
function profileLogoPreview() {
  var url = (document.getElementById('profileLogoUrl').value || '').trim();
  var wrap = document.getElementById('profileLogoPreview');
  var img  = document.getElementById('profileLogoImg');
  if (url) { img.src = url; wrap.style.display = ''; }
  else { wrap.style.display = 'none'; img.src = ''; }
}
function _updateTopBarLogo(url, alt) {
  var el = document.getElementById('topBarLogo');
  if (!el) return;
  if (url) { el.src = url; el.alt = alt || ''; el.style.display = ''; }
  else { el.src = ''; el.style.display = 'none'; }
}
function openProfileDialog() {
  document.getElementById('profileName').value     = MY_NAME         || '';
  document.getElementById('profileEmail').value    = MY_EMAIL        || '';
  document.getElementById('profilePhone').value    = MY_PHONE        || '';
  document.getElementById('profileCompany').value  = MY_COMPANY      || '';
  document.getElementById('profileLogoUrl').value  = MY_LOGO         || '';
  profileLogoPreview();
  document.getElementById('profileDesc').value     = MY_BIO_DESC     || '';
  document.getElementById('profileSkills').value   = MY_BIO_SKILLS   || '';
  document.getElementById('profileLocation').value = MY_BIO_LOCATION || '';
  document.getElementById('profileDiscord').value  = MY_BIO_DISCORD  || '';
  document.getElementById('profilePayment').value  = MY_BIO_PAYMENT  || '';
  document.getElementById('profileNotes').value    = MY_BIO_NOTES    || '';
  _profilePhotoUrl = MY_BIO_IMAGE || '';
  var img = document.getElementById('profilePhotoImg');
  var ph  = document.getElementById('profilePhotoPlaceholder');
  if (_profilePhotoUrl) {
    img.src = _profilePhotoUrl; img.style.display = ''; ph.style.display = 'none';
  } else {
    img.style.display = 'none'; ph.style.display = '';
  }
  document.getElementById('profilePhotoFile').value = '';
  document.getElementById('profileLog').innerHTML = '';
  document.getElementById('profileLog').style.display = 'none';
  document.getElementById('profileSaveBtn').disabled   = false;
  document.getElementById('profileCancelBtn').disabled = false;
  document.getElementById('profileCancelBtn').textContent = 'Cancel';
  // Capture initial state for dirty checking
  _profileInitial = {
    name: MY_NAME || '', email: MY_EMAIL || '', phone: MY_PHONE || '',
    company: MY_COMPANY || '', logo_url: MY_LOGO || '',
    desc: MY_BIO_DESC || '', skills: MY_BIO_SKILLS || '', location: MY_BIO_LOCATION || '',
    discord: MY_BIO_DISCORD || '', payment: MY_BIO_PAYMENT || '', notes: MY_BIO_NOTES || ''
  };
  document.getElementById('profileOverlay').classList.add('open');
}
function closeProfileDialog() {
  if (_profileIsDirty()) { shakeDialog(document.getElementById('profileOverlay').querySelector('.sync-dialog')); return; }
  document.getElementById('profileOverlay').classList.remove('open');
}
function forceCloseProfileDialog() {
  document.getElementById('profileOverlay').classList.remove('open');
}
function profilePhotoPreview(input) {
  if (!input.files || !input.files[0]) return;
  var reader = new FileReader();
  reader.onload = function(e) {
    var img = document.getElementById('profilePhotoImg');
    var ph  = document.getElementById('profilePhotoPlaceholder');
    img.src = e.target.result; img.style.display = ''; ph.style.display = 'none';
  };
  reader.readAsDataURL(input.files[0]);
}
function _profileLog(msg, type) {
  var log  = document.getElementById('profileLog');
  log.style.display = '';
  var span = document.createElement('span');
  span.className   = 'sync-log-line ' + (type || 'info');
  span.textContent = msg;
  log.appendChild(span);
  log.scrollTop = log.scrollHeight;
}
function submitProfile() {
  var name     = document.getElementById('profileName').value.trim();
  var email    = document.getElementById('profileEmail').value.trim();
  var phone    = document.getElementById('profilePhone').value.trim();
  var desc     = document.getElementById('profileDesc').value.trim();
  var skills   = document.getElementById('profileSkills').value.trim();
  var location = document.getElementById('profileLocation').value.trim();
  var discord  = document.getElementById('profileDiscord').value.trim();
  var payment  = document.getElementById('profilePayment').value.trim();
  var notes    = document.getElementById('profileNotes').value.trim();
  var photoFile = document.getElementById('profilePhotoFile').files[0];
  if (!name) { _profileLog('Name is required.', 'error'); return; }
  document.getElementById('profileSaveBtn').disabled   = true;
  document.getElementById('profileCancelBtn').disabled = true;
  _profileLog('Saving…', 'info');

  // Step 1: upload photo if a new file was selected
  var photoPromise = Promise.resolve(_profilePhotoUrl);
  if (photoFile) {
    _profileLog('Uploading photo…', 'info');
    var ufd = new FormData();
    ufd.append('id',   SHEET_ID);
    ufd.append('file', photoFile);
    photoPromise = fetch(APP_BASE + 'push/uploadMedia.php', { method:'POST', body:ufd })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        if (!res || res.error) throw new Error(res.error || 'Upload failed');
        return res.url;
      });
  }

  photoPromise.then(function(imageUrl) {
    _profilePhotoUrl = imageUrl || '';
    // Step 2: update settings (name / email / phone / company / logo)
    var company  = document.getElementById('profileCompany').value.trim();
    var logo_url = document.getElementById('profileLogoUrl').value.trim();
    var fd1 = new FormData();
    fd1.append('id', SHEET_ID); fd1.append('name', name);
    fd1.append('email', email); fd1.append('phone', phone);
    fd1.append('company', company); fd1.append('logo_url', logo_url);
    var p1 = fetch(APP_BASE + 'push/updateProfile.php', { method:'POST', body:fd1 }).then(function(r){ return r.json(); });
    // Step 3: update bios row
    var fd2 = new FormData();
    fd2.append('id', SHEET_ID); fd2.append('email', email);
    fd2.append('image_url', imageUrl || ''); fd2.append('description', desc);
    fd2.append('skills', skills); fd2.append('location', location);
    fd2.append('phone', phone);   fd2.append('discord', discord);
    fd2.append('payment', payment); fd2.append('notes', notes);
    var p2 = fetch(APP_BASE + 'push/updateBio.php', { method:'POST', body:fd2 }).then(function(r){ return r.json(); });
    return Promise.all([p1, p2]);
  })
  .then(function(results) {
    var err = (results[0] && results[0].error) || (results[1] && results[1].error);
    if (err) throw new Error(err);
    MY_NAME = document.getElementById('profileName').value.trim();
    MY_EMAIL = document.getElementById('profileEmail').value.trim();
    MY_PHONE = document.getElementById('profilePhone').value.trim();
    MY_COMPANY = document.getElementById('profileCompany').value.trim();
    MY_LOGO    = document.getElementById('profileLogoUrl').value.trim();
    MY_BIO_IMAGE    = _profilePhotoUrl;
    MY_BIO_DESC     = document.getElementById('profileDesc').value.trim();
    MY_BIO_SKILLS   = document.getElementById('profileSkills').value.trim();
    MY_BIO_LOCATION = document.getElementById('profileLocation').value.trim();
    MY_BIO_DISCORD  = document.getElementById('profileDiscord').value.trim();
    MY_BIO_PAYMENT  = document.getElementById('profilePayment').value.trim();
    MY_BIO_NOTES    = document.getElementById('profileNotes').value.trim();
    _updateTopBarLogo(MY_LOGO, MY_COMPANY);
    // Sync initial snapshot so Cancel/Close after save doesn't falsely shake
    _profileInitial = {
      name: MY_NAME, email: MY_EMAIL, phone: MY_PHONE,
      company: MY_COMPANY, logo_url: MY_LOGO,
      desc: MY_BIO_DESC, skills: MY_BIO_SKILLS, location: MY_BIO_LOCATION,
      discord: MY_BIO_DISCORD, payment: MY_BIO_PAYMENT, notes: MY_BIO_NOTES
    };
    _updateSubTitle();
    _profileLog('✓  Saved', 'ok');
    document.getElementById('profileSaveBtn').disabled    = true;
    document.getElementById('profileCancelBtn').disabled  = false;
    document.getElementById('profileCancelBtn').textContent = 'Close';
  })
  .catch(function(err) {
    _profileLog('✕  ' + (err.message || 'Error'), 'error');
    document.getElementById('profileSaveBtn').disabled   = false;
    document.getElementById('profileCancelBtn').disabled = false;
  });
}

// ── Contract edit dialog ───────────────────────────────────────────────────────
var _ceIdx = -1;
var _ceInitial = {};
function isContractEditDirty() {
  var f = function(id) { return (document.getElementById(id).value || '').trim(); };
  return f('ceClient')      !== (_ceInitial.client      || '') ||
         f('ceTargetStart') !== (_ceInitial.targetStart || '') ||
         f('ceTargetEnd')   !== (_ceInitial.targetEnd   || '') ||
         f('ceStartDate')   !== (_ceInitial.startDate   || '') ||
         f('ceEndDate')     !== (_ceInitial.endDate     || '') ||
         f('ceQuote')       !== (_ceInitial.quote       || '') ||
         f('cePayment')     !== (_ceInitial.payment     || '') ||
         f('ceNotes')       !== (_ceInitial.notes       || '');
}

function openContractEditDialog(idx) {
  var con = CONTRACT_RAW[idx];
  if (!con) return;
  _ceIdx = idx;
  document.getElementById('ceGameTitle').textContent  = con.Game || '';
  document.getElementById('ceClient').value           = con.Client || '';
  document.getElementById('ceTargetStart').value      = _toDateInput(con['Target Start Date'] || '');
  document.getElementById('ceTargetEnd').value        = _toDateInput(con['Target End Date']   || '');
  document.getElementById('ceStartDate').value        = _toDateInput(con['Start Date']        || '');
  document.getElementById('ceEndDate').value          = _toDateInput(con['End Date']          || '');
  document.getElementById('ceQuote').value            = con.Quote   || '';
  document.getElementById('cePayment').value          = con.Payment || 'Estimate';
  document.getElementById('ceNotes').value            = con.Notes   || '';
  document.getElementById('ceErr').textContent        = '';
  document.getElementById('ceErr').style.display      = 'none';
  document.getElementById('ceBtn').disabled           = false;
  document.getElementById('ceBtn').textContent        = 'Save Contract';
  // Capture initial state for dirty checking
  _ceInitial = {
    client:      (con.Client || '').trim(),
    targetStart: _toDateInput(con['Target Start Date'] || ''),
    targetEnd:   _toDateInput(con['Target End Date']   || ''),
    startDate:   _toDateInput(con['Start Date']        || ''),
    endDate:     _toDateInput(con['End Date']          || ''),
    quote:       (con.Quote   || '').trim(),
    payment:     (con.Payment || 'Estimate').trim(),
    notes:       (con.Notes   || '').trim()
  };
  document.getElementById('contractEditOverlay').classList.add('open');
}

function closeContractEditDialog() {
  if (isContractEditDirty()) { shakeDialog(document.getElementById('contractEditOverlay').querySelector('.contract-dialog')); return; }
  document.getElementById('contractEditOverlay').classList.remove('open');
}
function forceCloseContractEditDialog() {
  document.getElementById('contractEditOverlay').classList.remove('open');
}

function submitContractEdit() {
  var con = CONTRACT_RAW[_ceIdx];
  if (!con) return;
  var errEl = document.getElementById('ceErr');
  errEl.textContent = ''; errEl.style.display = 'none';
  var client = document.getElementById('ceClient').value.trim();
  if (!client) { errEl.textContent = 'Client is required.'; errEl.style.display = 'block'; return; }
  var btn = document.getElementById('ceBtn');
  btn.disabled = true; btn.textContent = 'Saving…';
  var fd = new FormData();
  fd.append('id',           SHEET_ID);
  fd.append('contract_id',  con.ID || '');
  fd.append('game',         con.Game || '');
  fd.append('client',       client);
  fd.append('target_start', document.getElementById('ceTargetStart').value);
  fd.append('target_end',   document.getElementById('ceTargetEnd').value);
  fd.append('start_date',   document.getElementById('ceStartDate').value);
  fd.append('end_date',     document.getElementById('ceEndDate').value);
  fd.append('quote',        document.getElementById('ceQuote').value);
  fd.append('payment',      document.getElementById('cePayment').value);
  fd.append('notes',        document.getElementById('ceNotes').value);
  fetch(APP_BASE + 'push/updateContract.php', { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(j) {
      if (j && j.ok) {
        // Update in-memory record
        con.Client              = client;
        con['Target Start Date']= document.getElementById('ceTargetStart').value;
        con['Target End Date']  = document.getElementById('ceTargetEnd').value;
        con['Start Date']       = document.getElementById('ceStartDate').value;
        con['End Date']         = document.getElementById('ceEndDate').value;
        con.Quote               = document.getElementById('ceQuote').value;
        con.Payment             = document.getElementById('cePayment').value;
        con.Notes               = document.getElementById('ceNotes').value;
        forceCloseContractEditDialog();
        renderPublishersView();
      } else {
        errEl.textContent = (j && j.error) ? j.error : 'Save failed.';
        errEl.style.display = 'block';
        btn.disabled = false; btn.textContent = 'Save Contract';
      }
    })
    .catch(function() {
      errEl.textContent = 'Network error.'; errEl.style.display = 'block';
      btn.disabled = false; btn.textContent = 'Save Contract';
    });
}


function _updateSubTitle() {
  var parts = [MY_NAME, MY_EMAIL, MY_PHONE].filter(Boolean);
  var el = document.getElementById('subTitle');
  if (el) el.textContent = parts.join('  ·  ') || 'Playtest Notes';
}

buildGameList();
renderCards();
_updateSubTitle();

// ── Background-save error toast ────────────────────────────────────────────
var _saveToastTimer = null;
function showSaveToast(msg) {
  var t = document.getElementById('saveToast');
  document.getElementById('saveToastMsg').textContent = msg;
  t.classList.add('visible');
  clearTimeout(_saveToastTimer);
  _saveToastTimer = setTimeout(function() { t.classList.remove('visible'); }, 10000);
}
</script>

<div class="save-toast" id="saveToast" role="alert">
  <span id="saveToastMsg"></span>
  <button class="save-toast-close" onclick="this.parentNode.classList.remove('visible')" aria-label="Dismiss">✕</button>
</div>
</body>
</html>
