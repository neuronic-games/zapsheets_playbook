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
$_my_name         = '';
$_my_email        = '';
$_my_phone        = '';
$_company         = '';
$_logo_url        = '';
$_company_address = '';
$_payment_info    = '';

foreach ($_settings as $_s) {
    // PitchBoard-style: {Name: 'My Name', Value: '...'}
    $n = $_s['Name'] ?? $_s['name'] ?? '';
    $v = $_s['Value'] ?? $_s['value'] ?? '';
    if ($n === 'My Name')     { $_my_name       = $v; continue; }
    if ($n === 'My Email')    { $_my_email      = $v; continue; }
    if ($n === 'My Phone')    { $_my_phone      = $v; continue; }
    if ($n === 'Company')     { $_company       = ltrim($v, "'"); continue; }
    if ($n === 'Address')     { $_company_address = ltrim($v, "'"); continue; }
    if ($n === 'Payment Info'){ $_payment_info  = ltrim($v, "'"); continue; }
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
    if ($label === 'My Email')     { $_my_email      = $val2; }
    if ($label === 'My Phone')     { $_my_phone      = $val2; }
    if ($label === 'Company')      { $_company       = $val2; }
    if ($label === 'Address')      { $_company_address = $val2; }
    if ($label === 'Payment Info') { $_payment_info  = $val2; }
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
        $_my_bio_location = ltrim(trim($_b['Address']    ?? ''), "'");
        $_my_bio_discord  = ltrim(trim($_b['Messaging'] ?? ''), "'");
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

// Load TODOs (the "todos" sheet)
$_todos_file = __DIR__ . '/../../../sheets/' . $_sheet_id . '/todos.json';
$_todos_raw  = file_exists($_todos_file)
    ? (json_decode(file_get_contents($_todos_file), true) ?: [])
    : [];

// Load estimates
$_estimates_file = __DIR__ . '/../../../sheets/' . $_sheet_id . '/estimates.json';
$_estimates_raw  = file_exists($_estimates_file)
    ? (json_decode(file_get_contents($_estimates_file), true) ?: [])
    : [];

// Sessions of every game (from the cached "[Game] dev" tabs), in brief, for the Summary's Activity list:
// one entry per session — game, date, type and number, where, and who played
$_sessions_brief = [];
if (is_dir($_sheets_dir)) {
    $_gname = [];
    foreach ($_games_raw as $_g) { $_n = trim((string) ($_g['Name'] ?? '')); if ($_n !== '') $_gname[strtolower($_n)] = $_n; }
    foreach (scandir($_sheets_dir) as $_f) {
        if (!preg_match('/^\[(.+)\] dev\.json$/i', $_f, $_m)) continue;
        $_rows = json_decode((string) @file_get_contents($_sheets_dir . $_f), true);
        if (!is_array($_rows)) continue;
        $_game = $_gname[strtolower($_m[1])] ?? $_m[1];
        $_cur = null;
        foreach ($_rows as $_r) {
            $_d = trim((string) ($_r['Date'] ?? '')); $_e = trim((string) ($_r['Event'] ?? '')); $_p = trim((string) ($_r['People'] ?? ''));
            if ($_d !== '' || $_e !== '') {
                if ($_cur !== null) $_sessions_brief[] = $_cur;
                $_cur = null;
                if (in_array(strtolower($_e), ['time', 'material'], true)) {   // time & materials: one row each — who, how long or how much, and a note
                    $_sessions_brief[] = ['g' => $_game, 'd' => $_d, 'e' => ucfirst(strtolower($_e)), 'tm' => 1, 'p' => $_p,
                        'v' => trim((string) ($_r['Observations'] ?? $_r['Observation'] ?? '')), 'w' => mb_substr(trim((string) ($_r['Solutions'] ?? $_r['Thoughts'] ?? '')), 0, 80)];
                    continue;
                }
                $_cur = ['g' => $_game, 'd' => $_d, 'e' => $_e, 'n' => $_p, 'w' => mb_substr(trim((string) ($_r['Observations'] ?? $_r['Observation'] ?? '')), 0, 80), 't' => 0];
            } elseif ($_cur !== null && $_p !== '' && strpos($_p, '[sub:') !== 0) {
                $_cur['t']++;
            }
        }
        if ($_cur !== null) $_sessions_brief[] = $_cur;
    }
}

// Publisher count — unique clients from Contracts sheet
$_client_count = count(array_unique(array_filter(array_map(
    fn($c) => trim($c['Client'] ?? ''), $_contracts_raw
))));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<script src="js/activationKey.js" defer></script>
<script src="js/gb-account.js?v=13"></script>
<script src="js/gb-queue.js?v=2"></script>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link rel="icon" type="image/png" sizes="32x32" href="images/db_icon_32.png" />
<link rel="icon" type="image/png" sizes="16x16" href="images/db_icon_16.png" />
<link rel="apple-touch-icon" sizes="180x180" href="images/db_icon_180.png" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-title" content="DevBoard" />
<title>DevBoard</title>
<?php require_once dirname(__DIR__, 3) . '/push/linkPreview.php'; echo gbLinkPreview('devboard', $_sheet_id); ?>
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2') format('woff2'),url('fonts/DINBlack.ttf'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf'); }
*, *::before, *::after { box-sizing:border-box; }
body { margin:0; background:#f0f4f8; font-family:'DINRegular',Arial,sans-serif; color:#111; }

/* ── Account menu ────────────────────────────────────── */
.account-menu-wrap { position:relative; flex-shrink:0; }
.account-menu-wrap-inner { display:flex; flex-direction:column; align-items:flex-end; gap:.1rem; }
.account-menu {
  display:none; position:absolute; top:calc(100% + .4rem); right:0;
  background:#1a1a2e; border:1px solid rgba(255,255,255,.2);
  border-radius:8px; min-width:150px; z-index:300;
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
.account-menu-divider { border:none; border-top:1px solid rgba(255,255,255,.12); margin:.2rem 0; }
.account-menu-label {
  display:block; padding:.45rem 1rem .2rem;
  font-family:'DINRegular',sans-serif; font-size:.68rem; color:rgba(255,255,255,.4);
  text-transform:uppercase; letter-spacing:.06em; pointer-events:none;
}

/* ── Sign-in dialog ──────────────────────────────────── */
.dash-signin-overlay {
  display:none; position:fixed; inset:0;
  background:rgba(0,0,0,.45); z-index:500;
  align-items:center; justify-content:center; padding:1rem;
}
.dash-signin-overlay.open { display:flex; }
.dash-signin-dialog {
  background:#fff; border-radius:14px; padding:1.6rem 1.8rem;
  width:min(380px,94vw); box-shadow:0 8px 40px rgba(0,0,0,.22);
  position:relative;
}
.dash-signin-dialog h2 { margin:0 0 .3rem; font-size:1.05rem; }
.dash-signin-notice { font-family:'DINRegular',sans-serif; font-size:.8rem; color:#777; margin:0 0 1rem; line-height:1.5; }
.dash-signin-err { color:#c0392b; font-size:.78rem; min-height:1.2em; margin:.3rem 0 0; }

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
.tm-section-label { font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase; letter-spacing:.07em; color:#888; margin:.6rem 0 .5rem; }
.tm-time-wrap { position:relative; }
.tm-time-list { display:none; position:absolute; top:calc(100% + 2px); left:0; right:0; background:#fff; border:1px solid #c8d8e0; border-radius:6px; box-shadow:0 4px 14px rgba(0,0,0,.13); z-index:9999; max-height:200px; overflow-y:auto; }
.tm-time-list.open { display:block; }
.tm-time-opt { padding:.38rem .75rem; font-family:'DINRegular',sans-serif; font-size:.85rem; cursor:pointer; color:#1a1a2e; }
.tm-time-opt:hover { background:#e8f4f8; }

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
.save-toast.success { background:#15803d; }
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
.dtl { background:#fff; border-radius:8px; box-shadow:0 1px 3px rgba(0,0,0,.07); padding:.35rem 0 .45rem; }
.dtl-row.grp.first:not(:nth-child(2)) { border-top:1px solid #eef1f5; margin-top:.25rem; padding-top:.6rem; }
.dtl-row.cont { padding-top:.15rem; }
.dtl-row.after-grp { border-top:1px solid #eef1f5; margin-top:.25rem; padding-top:.6rem; }
.dtl-row.cont .dtl-name { padding-left:.7rem; border-left:2px solid #e3e8ee; }
.dtl-row.cont .dtl-name span { color:#667; }
.dtl-name b em { font-style:normal; font-family:'DINRegular',sans-serif; font-size:.66rem; color:#9aa5b1; margin-left:.3rem; letter-spacing:.02em; }
.dtl-tip { position:fixed; z-index:9000; display:none; max-width:300px; padding:.55rem .75rem; background:#1f2733; color:#fff; border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,.25); pointer-events:none; font-size:.74rem; line-height:1.4; }
.dtl-tip b { display:block; font-family:'DINBlack',sans-serif; font-weight:normal; font-size:.8rem; margin-bottom:.15rem; }
.dtl-tip span { display:block; color:rgba(255,255,255,.72); }
.dtl-empty { padding:.9rem 1rem; font-size:.8rem; color:#888; }
.dtl-row { display:grid; grid-template-columns:minmax(150px,24%) 1fr 112px; gap:.9rem; align-items:center; padding:.42rem .9rem; cursor:pointer; }
.dtl-row:not(.dtl-head):hover { background:#f6fafc; }
.dtl-head { cursor:default; padding-top:.2rem; padding-bottom:0; }
.dtl-head .dtl-track { height:1.4rem; }
.dtl-name { min-width:0; }
.dtl-name b { display:block; font-family:'DINBlack',sans-serif; font-weight:normal; font-size:.8rem; color:#1a5f7a; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.dtl-name span { display:block; font-size:.7rem; color:#888; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.dtl-track { position:relative; height:1.05rem; }
.dtl-row:not(.dtl-head) .dtl-track { background:repeating-linear-gradient(90deg,transparent 0 calc(100% - 1px),transparent 0); border-radius:6px; background-color:#f3f6f9; }
.dtl-mark { position:absolute; top:0; bottom:-.3rem; border-left:1px solid #e3e8ee; }
.dtl-mark i { position:absolute; top:.15rem; left:.3rem; font-style:normal; font-family:'DINBlack',sans-serif; font-size:.58rem; text-transform:uppercase; letter-spacing:.08em; color:#a3adb8; white-space:nowrap; }
.dtl-today { position:absolute; top:-.45rem; bottom:-.45rem; border-left:2px solid #e2557a; z-index:2; }
.dtl-head .dtl-today { top:auto; bottom:-.3rem; height:.55rem; }
.dtl-today i { position:absolute; bottom:.55rem; left:-1.1rem; width:2.2rem; text-align:center; font-style:normal; font-family:'DINBlack',sans-serif; font-size:.55rem; text-transform:uppercase; letter-spacing:.06em; color:#e2557a; }
.dtl-bar { position:absolute; top:0; bottom:0; border-radius:6px; background:#2a8fa8; min-width:6px; }
.dtl-bar.soon { background:#e8a33d; }
.dtl-bar.late { background:#d92d20; }
.dtl-bar.est { background-image:repeating-linear-gradient(135deg,rgba(255,255,255,.45) 0 5px,transparent 5px 10px); opacity:.75; }
.dtl-due { text-align:right; line-height:1.2; }
.dtl-due b { display:block; font-family:'DINBlack',sans-serif; font-weight:normal; font-size:.76rem; color:#333; }
.dtl-due span { font-size:.68rem; color:#8a95a1; }
.dtl-due.soon span { color:#b7791f; } .dtl-due.late span { color:#d92d20; font-family:'DINBlack',sans-serif; }
@media (max-width:640px) { .dtl-row { grid-template-columns:110px 1fr 84px; gap:.5rem; padding:.4rem .6rem; } }
.dash-contracts { background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); }
.dash-contract-row { display:grid; grid-template-columns:1fr 5.5rem 6.5rem 6.5rem; gap:.75rem; align-items:center; padding:.48rem .85rem; border-bottom:1px solid #f0f4f8; font-size:.78rem; }
.dash-contract-row:last-child { border-bottom:none; }
.dash-contract-row > :nth-child(2) { text-align:right; } .dash-contract-row > :nth-child(3) { text-align:right; } .dash-contract-row > :nth-child(4) { text-align:right; }
@media (max-width:560px) { .dash-contract-row { grid-template-columns:1fr auto auto; } .dash-contract-row > :nth-child(3) { display:none; } }
.act-row { cursor:pointer; } .act-row:hover { background:#f8fafc; }
.act-badge { display:inline-block; padding:.2rem .6rem; border-radius:999px; color:#fff; font-family:'DINBlack',sans-serif; font-size:.6rem; text-transform:uppercase; letter-spacing:.07em; background:#1a5f7a; }
.act-badge.stat-meeting { background:#6b3fa8; } .act-badge.stat-idea { background:#2e7a52; } .act-badge.stat-editing { background:#a0522d; } .act-badge.stat-reporting { background:#2d3a8c; }
.act-badge.act-tm { background:#eef3f6; color:#4a5a6a; }
.act-more { display:block; width:100%; padding:.6rem; border:none; border-top:1px solid #f0f4f8; background:#fff; color:#1a5f7a; font-family:'DINBlack',sans-serif; font-size:.68rem; text-transform:uppercase; letter-spacing:.08em; cursor:pointer; }
.act-more:hover { background:#f8fafc; }
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
.contract-item { border-bottom:1px solid #f0f4f8; }
.contract-item:last-child { border-bottom:none; }
.client-contract-row { display:grid; grid-template-columns:1fr auto auto auto; gap:.75rem; align-items:center; padding:.5rem 1rem; cursor:pointer; transition:background .1s; }
.client-contract-row:hover { background:#f8fafc; }
.contract-item.open .client-contract-row { background:#f4f8fb; }
.client-contract-row-chevron { font-size:.6rem; opacity:.4; transition:transform .2s; }
.contract-item.open .client-contract-row-chevron { transform:rotate(180deg); opacity:.7; }
.client-contract-game { font-family:'DINBlack',sans-serif; font-size:.8rem; color:#1a5f7a; }
.client-contract-dates { font-size:.7rem; color:#888; margin-top:.1rem; }
.client-contract-quote { font-family:'DINBlack',sans-serif; font-size:.8rem; color:#111; white-space:nowrap; }
.client-contract-badge { font-family:'DINBlack',sans-serif; font-size:.6rem; letter-spacing:.05em; text-transform:uppercase; padding:.15rem .45rem; border-radius:999px; white-space:nowrap; background:#e8f4f8; color:#1a5f7a; }
.client-contract-badge.paid     { background:#dcfce7; color:#15803d; }
.client-contract-badge.invoiced { background:#fef9c3; color:#a16207; }
.client-contract-badge.partial  { background:#ffedd5; color:#9a3412; }
.client-contract-details { display:none; padding:.6rem 1rem .8rem 1rem; background:#f4f8fb; border-top:1px solid #e8eef3; }
.contract-item.open .client-contract-details { display:block; }
.contract-detail-fields { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:.35rem .75rem; margin-bottom:.7rem; }
.contract-detail-field label { font-size:.62rem; text-transform:uppercase; letter-spacing:.05em; color:#888; display:block; margin-bottom:.1rem; }
.contract-detail-field span  { font-family:'DINRegular',sans-serif; font-size:.78rem; color:#222; }
.contract-detail-notes { font-size:.75rem; color:#555; margin-bottom:.5rem; font-style:italic; }
.contract-del-row { display:flex; justify-content:flex-end; gap:.4rem; padding:.3rem 0 .1rem; }
.contract-detail-actions { display:flex; gap:.5rem; }
.btn-contract-action { font-family:'DINBlack',sans-serif; font-size:.75rem; text-transform:uppercase; letter-spacing:.06em; background:none; border:1.5px solid #1a5f7a; color:#1a5f7a; border-radius:7px; padding:.35rem .85rem; cursor:pointer; transition:background .15s, color .15s; }
.btn-contract-action:hover { background:#1a5f7a; color:#fff; }
.btn-contract-action.estimate { border-color:#2e7d9e; color:#2e7d9e; }
.btn-contract-action.estimate:hover { background:#2e7d9e; color:#fff; }
.btn-contract-action.invoice { border-color:#a16207; color:#a16207; }
.btn-contract-action.invoice:hover { background:#a16207; color:#fff; }
.btn-contract-action.report { border-color:#1a5f7a; color:#1a5f7a; }
.btn-contract-action.report:hover { background:#1a5f7a; color:#fff; }
.btn-contract-action:disabled { opacity:.5; cursor:default; }
.publishers-empty { color:#888; font-size:.85rem; padding:1rem 0; }

/* ── Game groups inside publisher card ────────────────────── */
.game-group { border-bottom:1px solid #edf1f5; }
.game-group:last-child { border-bottom:none; }
.game-group-header { display:grid; grid-template-columns:1fr auto auto; gap:.75rem; align-items:center; padding:.48rem 1rem; cursor:pointer; user-select:none; transition:background .1s; }
.game-group-header:hover { background:#f8fafc; }
.game-group.open .game-group-header { background:#f4f8fb; }
.game-group-name { font-family:'DINBlack',sans-serif; font-size:.8rem; color:#1a5f7a; }
.game-group-summary { font-family:'DINRegular',sans-serif; font-size:.7rem; color:#aab; white-space:nowrap; }
.game-group-chevron { font-size:.6rem; opacity:.35; transition:transform .2s; }
.game-group.open .game-group-chevron { transform:rotate(180deg); opacity:.65; }
.game-group-body { display:none; border-top:1px solid #edf1f5; }
.game-group.open .game-group-body { display:block; }
.game-group-footer { padding:.45rem 1rem; background:#f9fbfc; border-top:1px solid #edf1f5; display:flex; justify-content:space-between; align-items:center; gap:.5rem; }
.game-group-footer-left { display:flex; gap:.4rem; align-items:center; flex-wrap:wrap; }
.btn-card-subtitle { font-family:'DINBlack',sans-serif; font-size:.7rem; text-transform:uppercase; letter-spacing:.06em; background:none; border:1.5px solid #2e7d9e; color:#2e7d9e; border-radius:7px; padding:.35rem .65rem; cursor:pointer; transition:background .15s,color .15s; }
.btn-card-subtitle:hover { background:#2e7d9e; color:#fff; }

/* ── Estimate rows inside game group ──────────────────────── */
.estimate-item { border-bottom:1px solid #f4f7fb; background:#fafcfe; cursor:pointer; }
.estimate-item:last-of-type { border-bottom:none; }
.estimate-item-header { display:grid; grid-template-columns:auto auto 1fr auto auto; gap:.6rem; align-items:baseline; padding:.38rem 1rem; font-size:.78rem; user-select:none; }   /* baseline: tag, number, date and amount share one text line */
.estimate-type-pill { font-family:'DINBlack',sans-serif; font-size:.58rem; text-transform:uppercase; letter-spacing:.05em; color:#2e7d9e; background:#e8f4f8; border-radius:4px; padding:.1rem .35rem; white-space:nowrap; }
/* Centre the DIN capitals inside the tag (trim the font's extra space above capitals) */
@supports (text-box: trim-both cap alphabetic) { .estimate-type-pill { display:inline-block; text-box:trim-both cap alphabetic; padding:.3rem .4rem; } }
.estimate-type-pill.invoice { color:#a16207; background:#fef3cd; }
.estimate-type-pill.paid    { color:#15803d; background:#dcfce7; }
.estimate-type-pill.partial { color:#9a3412; background:#ffedd5; }
.estimate-type-pill.payout  { color:#6d28d9; background:#ede9fe; }   /* a payment to a collaborator (money out) */
.pay-payee { font-family:'DINBlack',sans-serif; font-size:.78rem; color:#333; }
.estimate-amount.out { color:#6d28d9; }
.btn-contract-action.payment { border-color:#6d28d9; color:#6d28d9; }
.btn-contract-action.payment:hover { background:#6d28d9; color:#fff; }
.estimate-num-badge { font-family:'DINRegular',sans-serif; font-size:.7rem; color:#999; }
.estimate-date { font-family:'DINRegular',sans-serif; font-size:.7rem; color:#bbb; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.estimate-sum { margin-left:.7rem; font-size:.8rem; color:#2b2d42; }   /* the contract's one-line Summary */
.estimate-amount { font-family:'DINBlack',sans-serif; font-size:.8rem; color:#111; white-space:nowrap; }
.estimate-item-chevron { font-size:.6rem; opacity:.35; transition:transform .2s; }
.estimate-item.open .estimate-item-chevron { transform:rotate(180deg); opacity:.65; }
/* Expanded body */
.estimate-item-body { display:none; border-top:1px solid #edf1f5; background:#f5f9fc; }
.estimate-item.open .estimate-item-body { display:block; }
.estimate-item-details { padding:.5rem 1rem; cursor:pointer; }
.estimate-item-details:hover { background:#eef4f8; }
.estimate-item-fields { display:flex; flex-wrap:wrap; gap:.35rem 1.5rem; }
.estimate-item-fields dt { font-family:'DINBlack',sans-serif; font-size:.6rem; text-transform:uppercase; letter-spacing:.05em; color:#aab; margin-bottom:.05rem; }
.estimate-item-fields dd { font-family:'DINRegular',sans-serif; font-size:.78rem; color:#333; margin:0; white-space:nowrap; }
.estimate-item-field { display:flex; flex-direction:column; }
.estimate-item-actions { display:flex; justify-content:flex-end; gap:.5rem; padding:.4rem 1rem .55rem; border-top:1px solid #edf1f5; }
.estimate-del-btn { font-family:'DINBlack',sans-serif; font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; background:none; border:1.5px solid #c0c8d0; color:#c0c8d0; border-radius:6px; padding:.35rem .55rem; cursor:pointer; white-space:nowrap; transition:border-color .15s,color .15s; }
.estimate-del-btn:hover { border-color:#c0392b; color:#c0392b; }
/* Card action buttons: one line each, all the same height; wrap the row (not the labels) when narrow */
.estimate-item-actions > div, .contract-del-row { flex-wrap:wrap; justify-content:flex-end; }
.estimate-item-actions .btn-contract-action, .estimate-item-actions .estimate-del-btn,
.contract-del-row .btn-contract-action, .contract-del-row .estimate-del-btn {
  white-space:nowrap; height:2.1rem; box-sizing:border-box;
  display:inline-flex; align-items:center; justify-content:center; gap:.3rem;
}
/* Inline delete confirmation */
.estimate-item .est-confirm { display:none; }
/* .confirming removed — delete confirm is now inline within .estimate-item-actions */
.est-nocancel-btn { font-family:'DINBlack',sans-serif; font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; background:none; border:1.5px solid #c0c8d0; color:#888; border-radius:6px; padding:.35rem .65rem; cursor:pointer; white-space:nowrap; transition:border-color .15s,color .15s; }
.est-nocancel-btn:hover { border-color:#555; color:#333; }
.est-confirm-btn { font-family:'DINBlack',sans-serif; font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; background:none; border:1.5px solid #c0392b; color:#c0392b; border-radius:6px; padding:.35rem .65rem; cursor:pointer; white-space:nowrap; transition:background .15s,color .15s; }
.est-confirm-btn:hover { background:#c0392b; color:#fff; }
.est-confirm-btn:disabled { opacity:.5; cursor:default; }

/* ── Estimate dialog ──────────────────────────────────────── */
.estimate-dialog { background:#fff; border-radius:12px; padding:1.5rem; width:min(480px,96vw); box-shadow:0 8px 32px rgba(0,0,0,.22); display:flex; flex-direction:column; gap:.8rem; max-height:92vh; overflow-y:auto; }
.estimate-dialog h2 { font-family:'DINBlack',sans-serif; font-size:1rem; margin:0; }
.estimate-dialog h2 span { font-family:'DINRegular',sans-serif; opacity:.55; }
.estimate-field-row { display:grid; gap:.6rem; }
.estimate-field-row.two { grid-template-columns:1fr 1fr; }
.estimate-field-row.three-one { grid-template-columns:2fr 1fr; }
.estimate-field-row.dur-dates { grid-template-columns:1fr 1fr 1fr; }
.estimate-field-row.dur-dates .ge-label { min-width:0; }
.estimate-field-row.dur-dates input[type="date"] { width:100%; min-width:0; box-sizing:border-box; }

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
.content { padding:.4rem 1.25rem 1rem; max-width:860px; margin:0 auto; }
.zs-footer { text-align:center; font-size:.72rem; line-height:1.6; color:#9aa0b8; padding:1.4rem 1rem 2.4rem; }
.zs-footer a { color:inherit; text-decoration:none; } .zs-footer a:hover { color:#555; text-decoration:underline; }
.zs-footer .sep { margin:0 .45rem; opacity:.6; }
@media print { .zs-footer { display:none; } }

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
.stat-playtest  { background:#1a5f7a; }
.stat-meeting   { background:#6b3fa8; }
.stat-idea      { background:#2e7a52; }
.stat-editing   { background:#a0522d; }
.stat-reporting { background:#2d3a8c; }
.stat-dim      { opacity:.35; }
.tm-summary-row {
  padding:.35rem 1rem; background:#fdf8f0;
  border-bottom:1px solid #f0e4c8;
  display:flex; flex-wrap:wrap; gap:.25rem .9rem;
  font-family:'DINRegular',sans-serif; font-size:.72rem; color:#888;
}
.tm-summary-person { color:#1a5f7a; font-family:'DINBlack',sans-serif; font-size:.68rem; text-transform:uppercase; letter-spacing:.04em; }
.tm-summary-val { color:#555; }
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
.contract-quote-wrap input { padding-left:1.4rem !important; width:100%; box-sizing:border-box; }
/* Same font, size and line-height as the input so "$" sits on the number's baseline */
.contract-quote-prefix {
  position:absolute; left:.55rem; top:0; bottom:0;
  display:flex; align-items:center;
  font-family:'DINRegular',sans-serif; font-size:.85rem; line-height:normal;
  letter-spacing:0; text-transform:none; color:#666; pointer-events:none;
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
.session-header { --sess-right:calc(14px + 1.75rem); }   /* the note count (or Copy) ends under the + T&M button */
/* position:relative so button+chevron can be abs-positioned within the row */
.session-header-row { position:relative; display:flex; align-items:baseline; gap:.55rem; padding-right:var(--sess-right); }   /* the note count (or Copy) ends under the + T&M button */
.session-type {
  font-family:'DINBlack',sans-serif; font-size:.72rem;
  text-transform:uppercase; letter-spacing:.06em; line-height:1;
}
.session-type.type-playtest  { color:#1a5f7a; }
.session-type.type-meeting   { color:#6b3fa8; }
.session-type.type-idea      { color:#2e7a52; }
.session-type.type-editing   { color:#a0522d; }
.session-type.type-reporting { color:#2d3a8c; }
.session-date     { font-family:'DINRegular',sans-serif; font-size:.72rem; color:#999; }
.session-sep      { color:#ccc; font-size:.6rem; }
.session-location { font-family:'DINRegular',sans-serif; font-size:.72rem; color:#777; }
.session-length   { font-family:'DINRegular',sans-serif; font-size:.72rem; color:#999; }
.session-count    { font-family:'DINRegular',sans-serif; font-size:.68rem; color:#bbb; margin-left:auto; white-space:nowrap; }
/* Chevron: absolute so it doesn't affect row height */
.session-chevron  { position:absolute; right:.3rem; top:50%; transform:translateY(-50%) rotate(-90deg); font-size:.6rem; opacity:.45; transition:transform .22s ease; }
.session-block.open .session-chevron { transform:translateY(-50%) rotate(0deg); }
.session-edit-btn { display:none; }
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
.obs-table .obs-line,.obs-table .obs-bul { display:block; }
.obs-table .obs-bul { padding-left:var(--bul-indent,1.4em); text-indent:calc(-1 * var(--bul-indent,1.4em)); }

/* ── No games ─────────────────────────────────────────── */
.no-games { text-align:center; padding:3rem 1rem; font-size:.88rem; color:#aaa; }
.no-games strong { display:block; font-family:'DINBlack',sans-serif; font-size:1rem; color:#888; margin-bottom:.4rem; }

/* ── Overlays ─────────────────────────────────────────── */
.overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:200; align-items:center; justify-content:center; padding:1rem; touch-action:none; }
.overlay.open { display:flex; }
.confirm-overlay { z-index:300; }

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
  padding:1.5rem 1rem; width:min(680px,96vw);
  box-shadow:0 8px 32px rgba(0,0,0,.22);
  display:flex; flex-direction:column; gap:1.1rem;
  max-height:92vh; overflow-y:auto;
  touch-action:pan-y; -webkit-overflow-scrolling:touch;
}
.session-dialog h2 { font-family:'DINBlack',sans-serif; font-size:.95rem; text-transform:uppercase; letter-spacing:.07em; color:#1a5f7a; margin:0; display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
.session-dialog h2 > span:not(.sw-display) { color:#1a1a2e; }
.session-dialog-title { flex:1; min-width:0; }
.sw-display { margin-left:auto; font-family:'DINBlack',sans-serif; font-size:.85rem; color:#e67e22; letter-spacing:.06em; display:none; }
.sw-display.sw-active { display:block; }
.session-dialog-controls { display:flex; align-items:center; gap:.6rem; margin-left:auto; flex-shrink:0; }
.rounds-wrap { display:flex; align-items:center; gap:.28rem; flex-shrink:0; }
.rounds-btn { background:none; border:1.5px solid #d0d8e0; border-radius:5px; width:2.1rem; height:2.1rem; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; color:#999; font-family:Arial,sans-serif; font-size:1.15rem; line-height:0; transition:border-color .15s,color .15s; padding:0; user-select:none; -webkit-user-select:none; }
.rounds-btn:hover { border-color:#aaa; color:#555; }
@media (max-width:500px) {
  .session-dialog-title { flex-basis:100%; width:100%; }
  .session-dialog-controls { margin-left:0; width:100%; justify-content:center; }
}
.rounds-count { font-family:'DINBlack',sans-serif; font-size:1rem; color:#1a5f7a; min-width:1.6rem; letter-spacing:.02em; display:block; text-align:center; line-height:1; text-box:trim-both cap alphabetic; }
.rounds-label { font-family:'DINRegular',sans-serif; font-size:.72rem; color:#999; letter-spacing:.04em; text-transform:uppercase; display:block; line-height:1; margin-right:.6rem; text-box:trim-both cap alphabetic; }
/* Trim the DIN font's extra space above capitals so digits centre on the icon */
#swTime { display:block; line-height:1; text-box:trim-both cap alphabetic; }
/* Browsers without text-box (Firefox, Safari < 18.2): nudge by the DIN fonts'
   measured offset between the line box centre and the capital-letter centre */
@supports not (text-box: trim-both cap alphabetic) {
  .rounds-count, #swTime { position:relative; top:-.234em; }
  .rounds-label { position:relative; top:.106em; }
}
.sw-wrap { display:flex; align-items:center; flex-shrink:0; }
.sw-wrap.sw-expanded { display:flex; align-items:center; }
.sw-panel { display:none; align-items:center; gap:.5rem; background:#fff8f2; border:1.5px solid #e67e22; border-radius:8px; padding:.28rem .5rem .28rem .65rem; animation:sw-expand-in .18s ease; }
.sw-wrap.sw-expanded .sw-panel { display:flex; }
.sw-wrap.sw-expanded #swBtn { display:none; }
@keyframes sw-expand-in { from{opacity:0;transform:scaleX(.88);transform-origin:left} to{opacity:1;transform:scaleX(1)} }
.sw-panel-input { font-size:1.55rem; font-family:'DINBlack',sans-serif; color:#e67e22; border:none; outline:none; background:transparent; width:3ch; text-align:right; -moz-appearance:textfield; }
.sw-panel-input::-webkit-inner-spin-button, .sw-panel-input::-webkit-outer-spin-button { -webkit-appearance:none; }
.sw-panel-unit { font-family:'DINRegular',sans-serif; font-size:.85rem; color:#e67e22; opacity:.65; }
.sw-panel-confirm { background:#e67e22; color:#fff; border:none; border-radius:5px; padding:0 .75rem; cursor:pointer; font-family:'DINBlack',sans-serif; font-size:.75rem; letter-spacing:.04em; align-self:stretch; display:flex; align-items:center; margin-left:.35rem; }
.sw-panel-cancel { background:none; border:none; color:#bbb; cursor:pointer; font-size:1rem; padding:.2rem .3rem; line-height:1; }
.sw-panel-cancel:hover { color:#888; }
.btn-stopwatch { background:none; border:1.5px solid #d0d8e0; border-radius:6px; height:2.1rem; box-sizing:border-box; padding:0 .65rem; cursor:pointer; display:inline-flex; align-items:center; gap:.35rem; color:#bbb; font-family:'DINBlack',sans-serif; font-size:.78rem; letter-spacing:.04em; transition:border-color .15s, color .15s, background .15s; user-select:none; -webkit-user-select:none; }
.btn-stopwatch:hover { border-color:#aaa; color:#888; }
.btn-stopwatch.sw-running { border-color:#e67e22; color:#e67e22; background:#fff8f2; animation:sw-pulse 1.4s ease-out infinite; }
@keyframes sw-pulse {
  0%,100% { box-shadow:0 0 0 0 rgba(230,126,34,.35); }
  50%      { box-shadow:0 0 0 5px rgba(230,126,34,0); }
}

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
/* Contract dialogs: text/number inputs and dropdowns match the date fields' height */
.contract-dialog input.field-input, .contract-dialog select.field-input { height:2.45rem; box-sizing:border-box; }
.date-wrap { position:relative; }
.date-wrap input[type="date"] { -webkit-appearance:none; appearance:none; width:100%; box-sizing:border-box; background:#fff; padding-right:2.2rem; cursor:pointer; }
.date-wrap input[type="date"]::-webkit-calendar-picker-indicator { position:absolute; right:0; top:0; bottom:0; width:2.2rem; opacity:0; cursor:pointer; }
.date-wrap::after { content:''; position:absolute; right:.65rem; top:50%; transform:translateY(-50%); width:15px; height:15px; pointer-events:none; background-image:url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxNSIgaGVpZ2h0PSIxNSIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiM4ODgiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48cmVjdCB4PSIzIiB5PSI0IiB3aWR0aD0iMTgiIGhlaWdodD0iMTgiIHJ4PSIyIi8+PGxpbmUgeDE9IjE2IiB5MT0iMiIgeDI9IjE2IiB5Mj0iNiIvPjxsaW5lIHgxPSI4IiB5MT0iMiIgeDI9IjgiIHkyPSI2Ii8+PGxpbmUgeDE9IjMiIHkxPSIxMCIgeDI9IjIxIiB5Mj0iMTAiLz48L3N2Zz4="); background-repeat:no-repeat; background-size:contain; }
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

/* Observations/solutions textareas */
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
/* empty rows get minimal vertical padding */
.obs-pair-empty .obs-ta-wrap .obs-editable,
.obs-pair-empty .obs-sol-col  .obs-editable { padding-top:.12rem; padding-bottom:.12rem; }

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
.btn-delete {
  font-family:'DINBlack',sans-serif; font-size:.8rem; text-transform:uppercase; letter-spacing:.06em;
  background:none; border:1.5px solid #e53e3e; color:#e53e3e; border-radius:7px; padding:.5rem 1rem; cursor:pointer;
  transition:background .15s, color .15s; margin-right:auto;
}
.btn-delete:hover:not(:disabled) { background:#e53e3e; color:#fff; }
.btn-delete:disabled { opacity:.5; cursor:default; }
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
.combo-option, .combo-item { padding:.5rem .8rem; font-family:'DINRegular',sans-serif; font-size:.85rem; cursor:pointer; color:#222; }
.combo-option:hover, .combo-option.highlighted, .combo-item:hover { background:#e8f4f8; color:#1a5f7a; }
.name-combo .combo-input { padding-right:2.1rem; }
.combo-toggle { position:absolute; right:.25rem; top:50%; transform:translateY(-50%); width:1.8rem; height:1.8rem; border:none; background:none; color:#8a95a1; font-size:.9rem; cursor:pointer; border-radius:5px; }
.combo-toggle:hover { background:#eef3f6; color:#1a5f7a; }
.name-combo.open .combo-toggle { color:#1a5f7a; transform:translateY(-50%) rotate(180deg); }
.combo-item.hl { background:#e8f4f8; color:#1a5f7a; }
.combo-item small { color:#9aa5b1; margin-left:.35rem; }
.combo-empty { padding:.5rem .8rem; font-size:.8rem; color:#aaa; font-style:italic; }

/* Dynamic tester rows */
.tester-row { margin-bottom:.4rem; }
.tester-row:last-child { margin-bottom:0; }

/* Dynamic obs/sol pairs */
.obs-section { display:flex; flex-direction:column; gap:.3rem; }
#obsContainer { margin-left:0; }
.obs-pair { position:relative; margin-bottom:.75rem; }
.obs-pair:last-child { margin-bottom:0; }
.obs-pair-row { display:block; }
/* Reorder buttons — flex sibling inside obs column, UP at top / DN at bottom */
.obs-reorder { display:flex; flex-direction:column; justify-content:space-between; padding:.3rem .12rem .3rem 0; align-self:stretch; }
.obs-reorder-btn { background:rgba(255,255,255,.92); border:1px solid #d0d8e4; border-radius:4px; padding:.13rem .22rem; cursor:pointer; font-size:.62rem; color:#aab; line-height:1; transition:color .12s,border-color .12s,background .12s; backdrop-filter:blur(2px); }
.obs-reorder-btn:hover:not(:disabled) { color:#1a5f7a; border-color:#a0b8c8; background:#f0f7fa; }
.obs-reorder-btn:disabled { opacity:.25; cursor:default; }
.obs-pair:last-child .obs-reorder { visibility:hidden; }
/* Column labels — flush with dialog left edge */
.obs-pair-labels {
  display:grid; grid-template-columns:1fr 1fr; gap:.9rem;
  margin-bottom:0; margin-left:0;
}
.obs-pair-labels label {
  font-family:'DINBlack',sans-serif; font-size:.68rem;
  text-transform:uppercase; letter-spacing:.07em; color:#888;
}
/* Row box — one joined container split into two cells */
.obs-pair-inputs { display:grid; grid-template-columns:1fr 1fr; border:1.5px solid #d0d8e0; border-radius:8px; overflow:hidden; }
.obs-pair-inputs:focus-within { border-color:#1a5f7a; box-shadow:0 0 0 2px rgba(26,95,122,.12); }
.obs-obs-col { display:flex; flex-direction:row; align-items:stretch; min-width:0; background:#fff; border-right:1px solid #d0d8e0; }
.obs-sol-col  { display:flex; flex-direction:column; min-width:0; background:#f5f7fa; position:relative; }
/* TODO toggle (light bulb): top-right of the Thoughts cell; stored as a "TODO" note in the sheet */
.obs-todo-btn { position:absolute; top:.3rem; right:.3rem; width:1.55rem; height:1.55rem; background:rgba(255,255,255,.88); border:1px solid #d0d8e4; border-radius:5px; cursor:pointer; color:#a0a8b4; display:flex; align-items:center; justify-content:center; z-index:2; padding:0; transition:color .15s,background .15s,border-color .15s; }
.obs-todo-btn:hover { color:#6b7280; border-color:#a0b8c8; background:#fff; }
.obs-pair:not(.obs-has-text):not(.obs-todo) .obs-todo-btn { display:none; }
.obs-pair.obs-todo .obs-todo-btn { color:#b8860b; background:#fff8db; border-color:#f5c518; }
.obs-pair.obs-todo .obs-todo-btn .bulb-glass { fill:#ffd43b; }
.obs-pair.obs-todo .obs-pair-inputs,
.obs-pair.obs-todo .obs-pair-inputs:focus-within { border-color:#f5c518; box-shadow:0 0 0 2px rgba(245,197,24,.35); }
/* Strip borders/bg from textareas/editables inside the box */
.obs-pair-inputs .field-textarea { border:none !important; background:transparent !important; border-radius:0 !important; box-shadow:none !important; resize:none; }
.obs-pair-inputs .field-textarea:focus { border:none !important; box-shadow:none !important; }
/* contenteditable obs/sol fields */
.obs-editable { outline:none; white-space:pre-wrap; word-break:break-word; overflow-wrap:break-word; cursor:text; }
.obs-editable > div.bul { padding-left:var(--bul-indent,1.4em); text-indent:calc(-1 * var(--bul-indent,1.4em)); }
/* placeholder via data-ph when empty */
.obs-editable[data-empty="1"]::before { content:attr(data-ph); color:#b0b8c8; pointer-events:none; }
/* obs-obs-col: textarea wrapper with icon overlaid inside */
.obs-ta-wrap { position:relative; flex:1; display:flex; flex-direction:column; min-width:0; }
/* tighter padding inside obs pairs so row shrinks to the height of the UP/DN buttons */
.obs-ta-wrap .field-textarea,.obs-ta-wrap .obs-editable { flex:1; padding:.3rem .5rem; }
.obs-sol-col  .field-textarea,.obs-sol-col  .obs-editable { padding:.3rem 2.1rem .3rem .5rem; }
/* Upload button: overlays top-right of textarea; hidden when obs has text */
.obs-ta-wrap.has-obs-text .obs-img-btn { display:none; }
.obs-img-btn { position:absolute; top:.3rem; right:.3rem; width:1.55rem; height:1.55rem; background:rgba(255,255,255,.88); border:1px solid #d0d8e4; border-radius:5px; cursor:pointer; color:#99a; display:flex; align-items:center; justify-content:center; z-index:2; backdrop-filter:blur(2px); transition:color .15s,background .15s,border-color .15s; padding:0; }
.obs-img-btn:hover { color:#1a5f7a; background:#fff; border-color:#a0b8c8; }
/* Preview container: relative so trash button can anchor to it */
.obs-img-preview { position:relative; }
.obs-img-preview img { width:100%; display:block; }
.obs-img-loading { display:flex; align-items:center; justify-content:center; gap:.5rem; min-height:4.5rem; color:#999; font-family:'DINRegular',sans-serif; font-size:.75rem; }
.obs-img-spinner { width:18px; height:18px; border:2px solid #d0d8e0; border-top-color:#1a5f7a; border-radius:50%; animation:spin .8s linear infinite; }
/* While an edit dialog is open, hide session-card images so they don't bleed through the overlay */
body.session-dialog-open .session-body img { display:none !important; }
/* Trash button: bottom-right of the preview image */
.obs-img-trash { position:absolute; bottom:.3rem; right:.3rem; width:1.55rem; height:1.55rem; background:rgba(255,255,255,.92); border:1px solid #e8c0c0; border-radius:5px; cursor:pointer; color:#c0392b; display:flex; align-items:center; justify-content:center; z-index:3; backdrop-filter:blur(2px); padding:0; transition:background .15s,border-color .15s; }
.obs-img-trash:hover { background:#fff0f0; border-color:#c0392b; }

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
  .obs-obs-col { border-right:none; border-bottom:1px solid #d0d8e0; }
  .obs-pair-labels label:last-child { display:none; }
  .obs-sol-col .field-textarea,.obs-sol-col .obs-editable { padding-right:1.8rem; }
  .field-grid { grid-template-columns:1fr 1fr; }
  .field-group.span2 { grid-column:span 1; }
  .obs-grid { grid-template-columns:1fr; }
  /* Prevent iOS Safari from auto-zooming inputs with font-size < 16px */
  .field-input, .field-textarea { font-size:1rem; }
}
/* Plan picker */
.plan-grid { display:grid; grid-template-columns:1fr 1fr; gap:.7rem; text-align:left; }
@media (max-width:480px) { .plan-grid { grid-template-columns:1fr; } }
.plan-card { border:1.5px solid #e2e2e2; border-radius:10px; padding:.85rem .9rem .9rem; display:flex; flex-direction:column; position:relative; }
.plan-card.plan-pro { border-color:#f5a623; }
.plan-card.current { border-color:#4caf50; background:#f3faf3; }
.plan-card.current::before { content:'Current plan'; position:absolute; top:-.6rem; left:.8rem; background:#4caf50; color:#fff; font-family:'DINBlack',sans-serif; font-size:.6rem; letter-spacing:.06em; text-transform:uppercase; padding:.12rem .45rem; border-radius:999px; }
.plan-name { font-family:'DINBlack',sans-serif; font-size:.78rem; letter-spacing:.07em; text-transform:uppercase; color:#555; }
.plan-price { font-family:'DINBlack',sans-serif; font-size:1.45rem; color:#222; margin:.15rem 0 .4rem; }
.plan-price span { font-family:'DINRegular',sans-serif; font-size:.78rem; color:#888; margin-left:.15rem; }
.plan-card ul { list-style:none; margin:0 0 .8rem; padding:0; font-family:'DINRegular',sans-serif; font-size:.8rem; color:#444; line-height:1.55; flex:1; }
.plan-card li::before { content:'✓ '; color:#4caf50; font-weight:700; }
.plan-btn { background:#2b3a55; color:#fff; border:none; border-radius:6px; padding:.5rem .6rem; font-family:'DINBlack',sans-serif; font-size:.7rem; letter-spacing:.06em; text-transform:uppercase; cursor:pointer; }
.plan-pro .plan-btn { background:#f5a623; }
.plan-btn:disabled { opacity:.5; cursor:default; }
.pro-badge { display:inline-block; margin-left:.35rem; background:#f5a623; color:#fff; font-family:'DINBlack',sans-serif; font-size:.55rem; letter-spacing:.06em; padding:.08rem .32rem; border-radius:4px; vertical-align:middle; }
.todo-section { border-bottom:1px solid #e6eaf0; background:#fffdf3; }
.todo-head { display:flex; align-items:center; gap:.45rem; width:100%; background:none; border:none; cursor:pointer; padding:.6rem 1.25rem; text-align:left; color:#8a6d00; }
.todo-head-bulb .bulb-glass, .todo-head-bulb path:first-child { fill:#ffd43b; }
.todo-head-bulb { color:#b8860b; flex-shrink:0; }
.todo-title { font-family:'DINBlack',sans-serif; font-size:.72rem; letter-spacing:.08em; text-transform:uppercase; }
.todo-count { font-family:'DINBlack',sans-serif; font-size:.66rem; background:#f5c518; color:#fff; border-radius:999px; padding:.08rem .45rem; min-width:1.2rem; text-align:center; }
.todo-chevron { margin-left:auto; font-size:.7rem; color:#b8a25a; transition:transform .2s; transform:rotate(-90deg); }
.todo-section.open .todo-chevron { transform:none; }
.todo-list { display:none; padding:0 1.25rem .7rem; }
.todo-section.open .todo-list { display:block; }
.todo-item { display:flex; align-items:center; gap:.6rem; padding:.3rem 0; border-top:1px solid #f1e8c4; }
.todo-item:first-child { border-top:none; }
.todo-item input { width:1rem; height:1rem; accent-color:#b8860b; flex-shrink:0; cursor:pointer; }
.todo-text { flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-family:'DINRegular',sans-serif; font-size:.85rem; color:#333; line-height:1.4; cursor:pointer; }
.todo-text > span + span { margin-left:.5rem; }
.todo-text:hover .todo-sol { color:#1a5f7a; }
.todo-obs { font-family:'DINBlack',sans-serif; font-size:.8rem; color:#1a5f7a; }
.todo-meta { font-size:.72rem; color:#999; }
.todo-item.done .todo-obs, .todo-item.done .todo-sol { text-decoration:line-through; color:#aaa; }
.btodo-count { font-family:'DINRegular',sans-serif; text-transform:none; letter-spacing:0; color:#b8860b; margin-left:.3rem; }
.btodo-list { background:#fff; border:1px solid #e3e8ef; border-radius:10px; overflow:hidden; }
.btodo-row { display:flex; align-items:center; gap:.6rem; padding:.35rem .8rem .35rem .4rem; border-top:1px solid #eef1f5; background:#fff; }
.btodo-row:first-child { border-top:none; }
.btodo-row { position:relative; will-change:transform; }
.btodo-row.dragging { background:#fffbe6; box-shadow:0 12px 28px rgba(15,23,42,.18), 0 2px 6px rgba(15,23,42,.08); z-index:3; border-radius:8px; cursor:grabbing; }
body.btodo-dragging, body.btodo-dragging * { cursor:grabbing !important; user-select:none !important; -webkit-user-select:none !important; }
.btodo-list { overflow:visible; }
.btodo-row:first-child { border-radius:10px 10px 0 0; } .btodo-row:last-child { border-radius:0 0 10px 10px; } .btodo-row:only-child { border-radius:10px; }
.btodo-handle { cursor:grab; color:#c3cad4; font-size:.8rem; letter-spacing:-.15em; padding:.15rem .25rem; touch-action:none; user-select:none; line-height:1.2; }
.btodo-row.dragging .btodo-handle { cursor:grabbing; }
.btodo-check { width:1rem; height:1rem; accent-color:#b8860b; flex-shrink:0; cursor:pointer; }
.btodo-text { flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-family:'DINRegular',sans-serif; font-size:.85rem; color:#333; line-height:1.4; }
.btodo-text > span + span { margin-left:.5rem; }
.btodo-text { cursor:pointer; } .btodo-text:hover .btodo-sol { color:#1a5f7a; }
.btodo-obs { font-family:'DINBlack',sans-serif; font-size:.8rem; color:#1a5f7a; }
.btodo-meta { font-size:.72rem; color:#999; }
.btodo-row.done .btodo-obs, .btodo-row.done .btodo-sol { text-decoration:line-through; color:#aaa; }
.btodo-fields { display:flex; gap:.2rem; flex-shrink:0; }
.btodo-ic { width:1.75rem; height:1.75rem; border:1px solid transparent; border-radius:6px; background:none; cursor:pointer; color:#c3cad4; display:flex; align-items:center; justify-content:center; padding:0; font-family:'DINBlack',sans-serif; font-size:.85rem; letter-spacing:-.05em; transition:background .12s,border-color .12s,color .12s; }
.btodo-ic:hover { background:#f3f5f8; border-color:#dde3ea; color:#8a94a3; }
.btodo-pri.p3 { color:#d92d20; } .btodo-pri.p2 { color:#e08a00; } .btodo-pri.p1 { color:#2e7d32; }
.btodo-who.set { color:#1a5f7a; }
.btodo-due.set { color:#7c4dbd; }
.btodo-due.overdue { color:#d92d20; }
.btodo-menu { position:absolute; z-index:1000; background:#fff; border:1px solid #e1e6ed; border-radius:8px; box-shadow:0 10px 28px rgba(15,23,42,.16); padding:.3rem; min-width:9.5rem; max-height:260px; overflow:auto; }
.btodo-mi { display:flex; align-items:center; gap:.5rem; width:100%; background:none; border:none; border-radius:5px; padding:.4rem .55rem; font-family:'DINRegular',sans-serif; font-size:.82rem; color:#333; text-align:left; cursor:pointer; white-space:nowrap; }
.btodo-mi:hover { background:#f3f6fa; }
.btodo-mi.cur { background:#eef6fa; color:#1a5f7a; font-family:'DINBlack',sans-serif; }
.btodo-bang { font-family:'DINBlack',sans-serif; width:1.4rem; color:#aab; letter-spacing:-.05em; }
.btodo-bang.p3 { color:#d92d20; } .btodo-bang.p2 { color:#e08a00; } .btodo-bang.p1 { color:#2e7d32; }
.btodo-date { font-family:'DINRegular',sans-serif; font-size:.85rem; border:1px solid #d6dde6; border-radius:6px; padding:.3rem .4rem; margin:.15rem; }
.btodo-mempty { font-family:'DINRegular',sans-serif; font-size:.8rem; color:#999; padding:.4rem .55rem; }
.btodo-row.done .btodo-fields { opacity:.5; }
.todo-dlg { width:min(560px,94vw); text-align:left; }
.todo-dlg-meta { font-family:'DINRegular',sans-serif; font-size:.75rem; color:#999; margin-bottom:.3rem; }
.todo-dlg h2 { font-family:'DINBlack',sans-serif; font-size:1.05rem; color:#1a5f7a; margin:0 0 .6rem; line-height:1.35; }
.todo-dlg-lbl { display:block; font-family:'DINBlack',sans-serif; font-size:.65rem; letter-spacing:.08em; text-transform:uppercase; color:#999; margin:.55rem 0 .25rem; }
.todo-dlg-obs, .todo-dlg-sol { width:100%; box-sizing:border-box; border:1.5px solid #d0d8e0; border-radius:8px; padding:.5rem .65rem; font-family:'DINRegular',sans-serif; font-size:.92rem; color:#333; background:#fff; }
.todo-dlg-obs { font-family:'DINBlack',sans-serif; color:#1a5f7a; }
.todo-dlg-obs[hidden] { display:none; }
.todo-dlg-pic { margin:0 0 .2rem; }
.todo-dlg-pic img { display:block; max-width:100%; max-height:45vh; border-radius:8px; border:1.5px solid #d0d8e0; background:#f3f6f9; }
.todo-dlg-piclink { margin:.35rem 0 0; padding:0; border:none; background:none; color:#8a95a1; font:inherit; font-size:.72rem; text-decoration:underline; cursor:pointer; }
.todo-thumb { display:inline-block; vertical-align:middle; height:2.4rem; max-width:4.5rem; object-fit:cover; border-radius:5px; border:1px solid #e3e8ee; margin:.1rem .5rem .1rem 0; background:#f3f6f9; }
.todo-dlg-sol { resize:vertical; line-height:1.55; background:#fffdf3; border-color:#f1e8c4; min-height:5rem; }
.todo-dlg-obs:focus, .todo-dlg-sol:focus { outline:none; border-color:#1a5f7a; box-shadow:0 0 0 2px rgba(26,95,122,.12); }
.todo-dlg-obs[readonly], .todo-dlg-sol[readonly] { background:#f6f7f9; color:#555; }
.todo-dlg-note { font-family:'DINRegular',sans-serif; font-size:.75rem; color:#999; margin-top:.5rem; }
.todo-dlg-err { font-family:'DINRegular',sans-serif; font-size:.8rem; color:#c0392b; margin-top:.4rem; }
.todo-dlg-err:empty { display:none; }
.todo-dlg-save { background:#1a5f7a; color:#fff; border:none; border-radius:6px; padding:.45rem 1.1rem; font-family:'DINBlack',sans-serif; font-size:.72rem; letter-spacing:.06em; text-transform:uppercase; cursor:pointer; }
.todo-dlg-save:disabled { opacity:.6; cursor:default; }
.todo-dlg h2 img { max-height:180px; }
.todo-dlg-body { font-family:'DINRegular',sans-serif; font-size:.92rem; color:#333; line-height:1.6; background:#fffdf3; border:1px solid #f1e8c4; border-radius:8px; padding:.7rem .85rem; max-height:55vh; overflow:auto; }
.todo-dlg-body:empty { display:none; }
.todo-dlg .obs-line, .todo-dlg .obs-bul { display:block; }
.todo-dlg .obs-bul { padding-left:var(--bul-indent,1.1em); text-indent:calc(-1 * var(--bul-indent,1.1em)); }
.todo-dlg-tags { display:flex; flex-wrap:wrap; gap:.4rem; margin:.75rem 0 .2rem; }
.todo-dlg-tags:empty { display:none; }
.todo-tag { font-family:'DINRegular',sans-serif; font-size:.75rem; background:#f1f4f8; color:#444; border-radius:999px; padding:.18rem .6rem; }
.todo-tag.pri-high { background:#fde8e8; color:#b42318; } .todo-tag.pri-medium { background:#fff4d6; color:#a35f12; } .todo-tag.pri-low { background:#e8f4ec; color:#2e7d32; }
.todo-tag.done { background:#e8f4ec; color:#2e7d32; }
.btodo-ic:disabled { cursor:default; opacity:.6; } .btodo-ic:disabled:hover { background:none; border-color:transparent; color:#c3cad4; }
.todo-item .btodo-fields { margin-left:.3rem; }
.todo-item.done .btodo-fields { opacity:.5; }
.plan-title { display:flex; align-items:center; justify-content:center; gap:.5rem; font-size:1.1rem; margin-bottom:.35rem; }
.plan-title img { width:28px; height:28px; border-radius:7px; }
.plan-current { display:block; width:fit-content; margin:0 auto .2rem; font-family:'DINRegular',sans-serif; font-size:.82rem; color:#555; background:#f1f4f8; border-radius:999px; padding:.22rem .8rem; }
.plan-current:empty { display:none; }
.plan-current b { font-family:'DINBlack',sans-serif; color:#333; }
.plan-current span { color:#888; }
.plan-current.paid { background:#edf7ed; } .plan-current.paid b { color:#2e7d32; }
</style>
<link rel="stylesheet" href="css/gameboard-ui.css?v=6" />
</head>
<body class="gb-app gb-page gb-dev">

<!-- Top bar -->
<div class="top-bar">
  <div class="top-bar-inner">
    <div class="top-bar-left">
      <img src="images/db_icon_180.png" alt="DevBoard" title="DevBoard" class="top-bar-icon" onclick="window.location.href=APP_BASE+'devboard'">
      <div class="top-bar-name">
        <h1 onclick="window.location.href=APP_BASE+'devboard'"><span class="db-dev">Dev</span><span class="db-board">Board</span></h1>
        <p class="sub" id="subTitle">Playtest Notes</p>
      </div>
    </div>

    <div class="top-tab-btns">
      <button class="top-tab"        id="tabDash"       onclick="switchTab('dash')">Summary</button>
      <button class="top-tab active" id="tabGames"      onclick="switchTab('games')">Games</button>
      <button class="top-tab"        id="tabPublishers" onclick="switchTab('publishers')">Contracts</button>
    </div>

    <div class="account-menu-wrap">
      <button class="top-btn top-menu-btn" id="accountBtn" onclick="toggleAccountMenu()" title="Menu">
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
        <hr class="account-menu-divider" />
        <button class="account-menu-item" id="dbDonateBtn" onclick="closeAccountMenu();startDonation()" style="color:#e2557a">Donate</button>
        <button class="account-menu-item" id="dbUpgradeBtn" onclick="closeAccountMenu();openUpgradeDialog()" style="color:#f5a623">Subscribe</button>
        <hr class="account-menu-divider" />
        <button class="account-menu-item" id="dashAuthBtn" onclick="closeAccountMenu();_dashMenuAuthAction()">Sign In</button>
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

<!-- View: Publishers / Contracts -->
<div class="view" id="view-publishers">
  <div class="search-bar">
    <div class="search-wrap" id="contractsSearchWrap">
      <input type="text" id="contractsSearchInput" placeholder="Search publishers, games, contracts…"
        oninput="onContractsSearch()" autocomplete="off" spellcheck="false"
        onkeydown="if(event.key==='Escape'){clearContractsSearch();this.blur();event.stopPropagation();}" />
      <button class="search-clear" onclick="clearContractsSearch()">✕</button>
    </div>
    <button class="add-game-btn" onclick="openNewContractDialog()">+ Contract<span class="pro-badge" data-pro-badge>PRO</span></button>
  </div>
  <div class="publishers-view-wrap" id="publishersViewWrap"></div>
</div>

<!-- Footer: copyright, and room at the bottom of the page -->
<footer class="zs-footer">© <?= date('Y') ?> ZapSheets. All rights reserved.<span class="sep">·</span><a href="devboard/features" target="_blank" rel="noopener">DevBoard</a><span class="sep">·</span><a href="devboard/help" target="_blank" rel="noopener">Help</a></footer>

<!-- Edit Contract dialog -->
<div class="overlay" id="contractEditOverlay" onclick="if(event.target===this){if(isContractEditDirty())shakeDialog(this.querySelector('.contract-dialog'));else closeContractEditDialog();}">
  <div class="contract-dialog">
    <h2><span id="ceDocTitle" style="font-family:inherit;opacity:1">Edit Estimate</span> — <span id="ceGameTitle"></span></h2>
    <div class="field-grid" style="grid-template-columns:1fr 1fr">
      <div class="field-group span2">
        <label>Client</label>
        <input type="text" class="field-input" id="ceClient" autocomplete="off" />
      </div>
      <div class="field-group span2">
        <label>Summary</label>
        <input type="text" class="field-input" id="ceSummary" maxlength="120" placeholder="One line: what this contract is for" autocomplete="off" />
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
      <div class="field-group span2" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:inherit">
        <div class="field-group">
          <label>Quote</label>
          <div class="contract-quote-wrap">
            <span class="contract-quote-prefix">$</span>
            <input type="number" class="field-input" id="ceQuote" min="0" step="0.01" />
          </div>
        </div>
        <div class="field-group">
          <label>Printing</label>
          <div class="contract-quote-wrap">
            <span class="contract-quote-prefix">$</span>
            <input type="number" class="field-input" id="cePrinting" min="0" step="0.01" placeholder="0.00" />
          </div>
        </div>
        <div class="field-group">
          <label>Status</label>
          <!-- Stored in the sheet's Status column: Estimate / Invoiced / Paid -->
          <select class="field-input" id="ceStatus">
            <option value="Estimate">Estimate</option>
            <option value="Invoiced">Invoice</option>
            <option value="Paid">Paid</option>
          </select>
        </div>
      </div>
      <div class="field-group span2" style="display:grid;grid-template-columns:2fr 1fr;gap:inherit">
        <div class="field-group">
          <label>Discount Label</label>
          <input type="text" class="field-input" id="ceDiscountLabel" placeholder="e.g. First-timer discount" autocomplete="off" />
        </div>
        <div class="field-group">
          <label>Discount %</label>
          <input type="number" class="field-input" id="ceDiscountPct" min="0" max="100" step="1" placeholder="0" />
        </div>
      </div>
      <div class="field-group span2">
        <label>Notes</label>
        <input type="text" class="field-input" id="ceNotes" autocomplete="off" />
      </div>
    </div>
    <div class="dialog-err" id="ceErr"></div>
    <div class="dialog-actions">
      <button class="btn-cancel" onclick="forceCloseContractEditDialog()">Cancel</button>
      <button class="btn-dark" id="ceBtn" onclick="submitContractEdit()">Save Estimate</button>
    </div>
  </div>
</div>

<!-- Estimate dialog -->
<div class="overlay" id="estimateOverlay" onclick="if(event.target===this){if(_estimateIsDirty())shakeDialog(this.querySelector('.estimate-dialog'));else forceCloseEstimateDialog();}">
  <div class="estimate-dialog">
    <h2>New Contract — <span id="estimateClientLabel"></span></h2>
    <input type="hidden" id="estimateClient" />
    <label class="ge-label">Type
      <select id="estimateType" class="ge-input">
        <option value="estimate">Estimate</option>
        <option value="invoice">Invoice</option>
      </select>
    </label>
    <div class="ge-label" id="estimateClientFieldWrap">
      <span style="font-family:'DINBlack',sans-serif;font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:#888">Publisher / Client</span>
      <div class="combo-wrap" id="estimateClientCombo" style="margin-top:.3rem">
        <input type="text" id="estimateClientVisible" class="field-input combo-input" placeholder="Select or type a publisher name" autocomplete="off"
          oninput="_estClientFilter()" onfocus="_estClientOpen()" onkeydown="_estClientKey(event)" />
        <div class="combo-dropdown" id="estimateClientDrop"></div>
      </div>
    </div>
    <div class="ge-label">
      <span style="font-family:'DINBlack',sans-serif;font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:#888">Game</span>
      <div class="combo-wrap" id="estimateGameCombo" style="margin-top:.3rem">
        <input type="text" id="estimateGame" class="field-input combo-input" placeholder="Select or type a game name" autocomplete="off"
          oninput="_estGameFilter();_seedScopeOfWork()" onfocus="_estGameOpen()" onkeydown="_estGameKey(event)" />
        <div class="combo-dropdown" id="estimateGameDrop"></div>
      </div>
    </div>
    <label class="ge-label">Summary
      <input type="text" id="estimateSummary" class="ge-input" maxlength="120" autocomplete="off" placeholder="One line: what this contract is for, e.g. Rules edit and 2 playtests" />
    </label>
    <div class="estimate-field-row two">
      <label class="ge-label"># of Tests
        <input type="number" id="estimateTests" class="ge-input" value="2" min="0" step="1" oninput="_seedScopeOfWork()" />
      </label>
      <label class="ge-label"># of Edit Rounds
        <input type="number" id="estimateEdits" class="ge-input" value="2" min="0" step="1" oninput="_seedScopeOfWork()" />
      </label>
    </div>
    <label class="ge-label">Scope of Work
      <textarea id="estimateScopeOfWork" class="ge-input ge-textarea" rows="5" placeholder="Describe what's included…" oninput="_estimateScopeEdited=true"></textarea>
    </label>
    <input type="hidden" id="estimateQty" value="1" />
    <div class="estimate-field-row two">
      <label class="ge-label">Unit Price
        <div class="contract-quote-wrap">
          <span class="contract-quote-prefix">$</span>
          <input type="number" id="estimateUnitPrice" class="ge-input" style="padding-left:1.4rem" min="0" step="0.01" placeholder="0.00" />
        </div>
      </label>
      <label class="ge-label">
        <span style="display:flex;justify-content:space-between;align-items:baseline">Printing<span style="font-family:'DINRegular',sans-serif;letter-spacing:0;text-transform:none;opacity:.45;font-size:.65rem">(optional)</span></span>
        <div class="contract-quote-wrap">
          <span class="contract-quote-prefix">$</span>
          <input type="number" id="estimatePrinting" class="ge-input" style="padding-left:1.4rem" min="0" step="0.01" placeholder="0.00" />
        </div>
      </label>
    </div>
    <div class="estimate-field-row dur-dates">
      <label class="ge-label">Duration
        <input type="text" id="estimateDuration" class="ge-input" placeholder="e.g. 2 months" />
      </label>
      <label class="ge-label">Target Start
        <input type="date" id="estimateTargetStart" class="ge-input" />
      </label>
      <label class="ge-label">Target End
        <input type="date" id="estimateTargetEnd" class="ge-input" />
      </label>
    </div>
    <div class="estimate-field-row three-one">
      <label class="ge-label">
        <span style="display:flex;justify-content:space-between;align-items:baseline">Discount Label<span style="font-family:'DINRegular',sans-serif;letter-spacing:0;text-transform:none;opacity:.45;font-size:.65rem">(optional)</span></span>
        <input type="text" id="estimateDiscountLabel" class="ge-input" placeholder="e.g. First-timer discount" />
      </label>
      <label class="ge-label">%
        <input type="number" id="estimateDiscountPct" class="ge-input" value="0" min="0" max="100" step="1" />
      </label>
    </div>
    <label class="ge-label">
      <span style="display:flex;justify-content:space-between;align-items:baseline">Notes<span style="font-family:'DINRegular',sans-serif;letter-spacing:0;text-transform:none;opacity:.45;font-size:.65rem">(optional)</span></span>
      <input type="text" id="estimateNotes" class="ge-input" placeholder="e.g. Printing costs not included" />
    </label>
    <div class="sync-log" id="estimateLog" style="display:none"></div>
    <div class="sync-dialog-actions">
      <button type="button" class="notes-close" onclick="forceCloseEstimateDialog()">Cancel</button>
      <button type="button" class="notes-close" id="estimateCreateBtn" onclick="submitEstimate()" style="background:#1a5f7a;color:#fff;border-color:#1a5f7a">Create</button>
    </div>
  </div>
</div>

<!-- Invoice-from-Estimate dialog -->
<div class="overlay" id="invoiceOverlay" onclick="if(event.target===this){if(_invoiceIsDirty())shakeDialog(this.querySelector('.estimate-dialog'));else forceCloseInvoiceDialog();}">
  <div class="estimate-dialog">
    <h2>New Invoice — <span id="invoiceGameTitle"></span></h2>
    <input type="hidden" id="invoiceEstIdx" />

    <!-- Reference info: CSS grid so all values share the same row baseline -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;grid-template-rows:auto auto;row-gap:.15rem;padding:.45rem .75rem;background:#f5f9fc;border-radius:7px;border:1px solid #e2edf5">
      <div style="font-family:'DINBlack',sans-serif;font-size:.6rem;text-transform:uppercase;letter-spacing:.05em;color:#aab;white-space:nowrap">Client</div>
      <div style="font-family:'DINBlack',sans-serif;font-size:.6rem;text-transform:uppercase;letter-spacing:.05em;color:#aab;white-space:nowrap">Estimate #</div>
      <div style="font-family:'DINBlack',sans-serif;font-size:.6rem;text-transform:uppercase;letter-spacing:.05em;color:#aab;white-space:nowrap">Estimate Total</div>
      <div id="invoiceRefClient" style="font-family:'DINRegular',sans-serif;font-size:.82rem;color:#333"></div>
      <div id="invoiceRefNum"    style="font-family:'DINRegular',sans-serif;font-size:.82rem;color:#333"></div>
      <div id="invoiceRefAmount" style="font-family:'DINBlack',sans-serif;font-size:.82rem;color:#1a5f7a"></div>
    </div>

    <!-- Amount type + value (all in one label row) -->
    <label class="ge-label">Invoice Amount
      <div style="display:flex;align-items:center;gap:.6rem">
        <select id="invoiceAmtType" class="ge-input" style="flex:1 1 auto;min-width:0;width:auto" onchange="_invoiceAmtTypeChange()">
          <option value="fixed">Fixed Amount ($)</option>
          <option value="pct">Percentage of Estimate</option>
        </select>
        <!-- Fixed amount input -->
        <div id="invoiceAmtLabel" class="contract-quote-wrap" style="flex:0 0 9rem">
          <span class="contract-quote-prefix">$</span>
          <input type="number" id="invoiceAmount" class="ge-input" style="padding-left:1.4rem" min="0" step="0.01" placeholder="0.00" oninput="_invoiceMarkDirty()" />
        </div>
        <!-- Percentage inputs (shown when pct mode) -->
        <div id="invoicePctRow" style="display:none;align-items:center;gap:.5rem;flex:0 0 auto">
          <div class="contract-quote-wrap" style="flex:0 0 6rem">
            <input type="number" id="invoicePct" class="ge-input" style="padding-right:1.6rem" min="1" max="100" step="1" value="100" oninput="_invoiceUpdatePct()" />
            <span class="contract-quote-prefix" style="left:auto;right:.5rem">%</span>
          </div>
          <span style="font-family:'DINRegular',sans-serif;font-size:.8rem;color:#888">=</span>
          <span id="invoicePctCalc" style="font-family:'DINBlack',sans-serif;font-size:.9rem;color:#1a5f7a"></span>
        </div>
      </div>
    </label>

    <label class="ge-label">Summary
      <input type="text" id="invoiceSummary" class="ge-input" maxlength="120" autocomplete="off" placeholder="One line: what this invoice is for" oninput="_invoiceMarkDirty()" />
    </label>
    <label class="ge-label">Description
      <textarea id="invoiceDescription" class="ge-input ge-textarea" rows="3" oninput="_invoiceMarkDirty()"></textarea>
    </label>

    <label class="ge-label">Due Date
      <input type="date" id="invoiceDueDate" class="ge-input" oninput="_invoiceMarkDirty()" />
    </label>

    <label class="ge-label">
      <span style="display:flex;justify-content:space-between;align-items:baseline">Notes<span style="font-family:'DINRegular',sans-serif;letter-spacing:0;text-transform:none;opacity:.45;font-size:.65rem">(optional)</span></span>
      <input type="text" id="invoiceNotes" class="ge-input" placeholder="e.g. Milestone 1 of 2" oninput="_invoiceMarkDirty()" />
    </label>

    <div class="sync-log" id="invoiceLog" style="display:none"></div>
    <div class="sync-dialog-actions">
      <button type="button" class="notes-close" onclick="forceCloseInvoiceDialog()">Cancel</button>
      <button type="button" class="notes-close" id="invoiceCreateBtn" onclick="submitInvoice()" style="background:#1a5f7a;color:#fff;border-color:#1a5f7a">Create</button>
    </div>
  </div>
</div>

<!-- Sign-in dialog -->
<div class="dash-signin-overlay" id="dashSignInOverlay" onclick="if(event.target===this)closeDashSignInDialog()">
  <div class="dash-signin-dialog" onclick="event.stopPropagation()">
    <button onclick="closeDashSignInDialog()" style="position:absolute;top:.75rem;right:.75rem;background:none;border:none;cursor:pointer;font-size:1rem;color:#888;padding:.25rem .4rem" title="Close">&#x2715;</button>

    <!-- Sign-in fields -->
    <div id="dashSignInFields">
      <h2>Sign In</h2>
      <p class="dash-signin-notice">Sign in to track your activity. If you don't have an account yet, we'll create one.</p>
      <div class="field-group">
        <label style="font-family:'DINBlack',sans-serif;font-size:.68rem;text-transform:uppercase;letter-spacing:.07em;color:#888">Email</label>
        <input type="email" class="field-input" id="dashSignInEmail" placeholder="you@example.com" autocomplete="email" autocapitalize="off" />
      </div>
      <div class="field-group">
        <label style="font-family:'DINBlack',sans-serif;font-size:.68rem;text-transform:uppercase;letter-spacing:.07em;color:#888">Password</label>
        <input type="password" class="field-input" id="dashSignInPassword" placeholder="Password"
          autocomplete="current-password" onkeydown="if(event.key==='Enter')submitDashSignIn()" />
      </div>
      <label style="display:flex;align-items:center;gap:.5rem;font-size:.8rem;color:#666;margin:.3rem 0 .6rem;cursor:pointer">
        <input type="checkbox" id="dashSignInRemember" /> Remember me on this device
      </label>
      <div class="dash-signin-err" id="dashSignInErr"></div>
      <div class="dialog-actions" style="margin-top:.6rem">
        <button class="btn-cancel" onclick="closeDashSignInDialog()">Cancel</button>
        <button class="btn-primary" id="dashSignInBtn" onclick="submitDashSignIn()">Sign In</button>
      </div>
    </div>

    <!-- Create-account confirmation (shown when email not found) -->
    <div id="dashSignInConfirm" style="display:none">
      <h2>Create Account</h2>
      <p class="dash-signin-notice" style="background:#fff8e8;border:1px solid #f0d898;border-radius:6px;padding:.6rem .8rem">
        No account found for <strong id="dashSignInConfirmEmail"></strong>. Create one?
      </p>
      <div class="dialog-actions" style="margin-top:.6rem">
        <button class="btn-cancel" onclick="_dashSignInConfirmBack()">Back</button>
        <button class="btn-primary" id="dashSignInConfirmBtn" onclick="_dashSignInConfirmCreate()">Create Account</button>
      </div>
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
      <label class="ge-label">Mailing Address<textarea id="profileLocation" class="ge-input ge-textarea" placeholder="Street&#10;City, State, ZIP" rows="3"></textarea></label>
      <label class="ge-label">Messaging<input type="text" id="profileDiscord" class="ge-input" placeholder="@handle or username" /></label>
      <label class="ge-label">Phone<input type="tel" id="profilePhone" class="ge-input" placeholder="+1 555 000 0000" /></label>
      <label class="ge-label">Preferred Payment Method<input type="text" id="profilePayment" class="ge-input" placeholder="e.g. Venmo: @YourName  or  PayPal: you@email.com" /></label>
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
    <label class="ge-label" style="margin-top:.35rem">Address
      <textarea id="companyAddress" class="ge-input" rows="3"
        placeholder="Street&#10;City, State, ZIP"
        style="resize:vertical;min-height:3.5rem;line-height:1.5"></textarea>
    </label>
    <label class="ge-label" style="margin-top:.35rem">Payment Info
      <textarea id="companyPaymentInfo" class="ge-input" rows="4"
        placeholder="ACH Payments:&#10;Bank Name&#10;Routing: ...&#10;Account: ..."
        style="resize:vertical;min-height:4rem;line-height:1.5;font-size:.8rem"></textarea>
    </label>
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
          <div class="combo-wrap name-combo" id="gDesigner1Combo"><input type="text" class="field-input combo-input" id="gDesigner1" placeholder="Pick or type a name…" autocomplete="off" /><button type="button" class="combo-toggle" tabindex="-1" aria-label="Show names">▾</button><div class="combo-dropdown"></div></div>
        </div>
        <div class="field-group">
          <label>Designer 2</label>
          <div class="combo-wrap name-combo" id="gDesigner2Combo"><input type="text" class="field-input combo-input" id="gDesigner2" placeholder="Pick or type a name…" autocomplete="off" /><button type="button" class="combo-toggle" tabindex="-1" aria-label="Show names">▾</button><div class="combo-dropdown"></div></div>
        </div>
        <div class="field-group">
          <label>Designer 3</label>
          <div class="combo-wrap name-combo" id="gDesigner3Combo"><input type="text" class="field-input combo-input" id="gDesigner3" placeholder="Pick or type a name…" autocomplete="off" /><button type="button" class="combo-toggle" tabindex="-1" aria-label="Show names">▾</button><div class="combo-dropdown"></div></div>
        </div>
        <div class="field-group">
          <label>Designer 4</label>
          <div class="combo-wrap name-combo" id="gDesigner4Combo"><input type="text" class="field-input combo-input" id="gDesigner4" placeholder="Pick or type a name…" autocomplete="off" /><button type="button" class="combo-toggle" tabindex="-1" aria-label="Show names">▾</button><div class="combo-dropdown"></div></div>
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
      <div class="field-group span2">
        <label>Summary</label>
        <input type="text" class="field-input" id="contractSummary" maxlength="120" placeholder="One line: what this contract is for" autocomplete="off" />
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
          <option value="Paid">Paid</option>
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

<!-- Payment dialog: record a payment to a collaborator (a Contracts row with Type "Payment") -->
<div class="overlay" id="paymentOverlay" onclick="if(event.target===this){closePaymentDialog();}">
  <div class="contract-dialog" style="position:relative;overflow:visible" onclick="event.stopPropagation()"
       onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitPayment();}">
    <button onclick="closePaymentDialog()" style="position:absolute;top:.75rem;right:.75rem;background:none;border:none;cursor:pointer;font-size:1rem;color:#888;line-height:1;padding:.25rem .4rem;" title="Close">&#x2715;</button>
    <h2>Payment — <span id="payGameTitle"></span></h2>
    <div class="field-grid" style="grid-template-columns:1fr 1fr">
      <div class="field-group span2">
        <label>Paid to</label>
        <div class="combo-wrap" id="payPersonCombo">
          <input type="text" id="payPersonInput" class="field-input combo-input" placeholder="Collaborator" autocomplete="off"
            oninput="payPersonRebuild(this.value)" onfocus="payPersonRebuild(this.value)" onkeydown="payPersonKey(event)" />
          <div class="combo-dropdown" id="payPersonDrop"></div>
        </div>
      </div>
      <div class="field-group span2">
        <label>Contract</label>
        <div class="combo-wrap" id="paySumCombo">
          <input type="text" id="paySummary" class="field-input combo-input" placeholder="Pick a contract’s summary, or type one" autocomplete="off" maxlength="120"
            oninput="sumRebuild('pay',this.value)" onfocus="sumRebuild('pay',this.value)" onkeydown="sumKey('pay',event)" />
          <div class="combo-dropdown" id="paySumDrop"></div>
        </div>
      </div>
      <div class="field-group">
        <label>Amount</label>
        <div class="contract-quote-wrap">
          <span class="contract-quote-prefix">$</span>
          <input type="number" class="field-input" id="payAmount" placeholder="0.00" min="0" step="0.01" />
        </div>
      </div>
      <div class="field-group">
        <label>Date paid</label>
        <div class="date-wrap"><input type="date" class="field-input" id="payDate" /></div>
      </div>
      <div class="field-group span2">
        <label>Notes</label>
        <input type="text" class="field-input" id="payNotes" placeholder="e.g. Art for the box cover, via PayPal" autocomplete="off" />
      </div>
    </div>
    <div class="dialog-err" id="payErr"></div>
    <div class="dialog-actions">
      <button class="btn-cancel" onclick="forceClosePaymentDialog()">Cancel</button>
      <button class="btn-dark" id="payBtn" onclick="submitPayment()">Save Payment</button>
    </div>
  </div>
</div>

<!-- Standalone Invoice dialog -->
<div class="overlay" id="standaloneInvoiceOverlay" onclick="if(event.target===this){closeStandaloneInvoiceDialog();}">
  <div class="contract-dialog" style="position:relative" onclick="event.stopPropagation()">
    <button onclick="closeStandaloneInvoiceDialog()" style="position:absolute;top:.75rem;right:.75rem;background:none;border:none;cursor:pointer;font-size:1rem;color:#888;line-height:1;padding:.25rem .4rem;" title="Close">&#x2715;</button>
    <h2>New Invoice — <span id="siGameTitle"></span></h2>
    <div class="field-grid" style="grid-template-columns:1fr 1fr">
      <div class="field-group span2">
        <label>Client</label>
        <div id="siClientDisplay" style="font-family:'DINRegular',sans-serif;font-size:.9rem;color:#333;padding:.45rem 0"></div>
      </div>
      <div class="field-group span2">
        <label>Summary</label>
        <input type="text" class="field-input" id="siSummary" maxlength="120" placeholder="One line: what this invoice is for" autocomplete="off" />
      </div>
      <div class="field-group">
        <label>Amount</label>
        <div class="contract-quote-wrap">
          <span class="contract-quote-prefix">$</span>
          <input type="number" class="field-input" id="siAmount" placeholder="0.00" min="0" step="0.01"
                 style="height:2.4rem;box-sizing:border-box" oninput="_siMarkDirty()" />
        </div>
      </div>
      <div class="field-group">
        <label>Due Date</label>
        <input type="date" class="field-input" id="siDueDate"
               style="height:2.4rem;box-sizing:border-box" oninput="_siMarkDirty()" />
      </div>
      <div class="field-group span2">
        <label>Notes</label>
        <input type="text" class="field-input" id="siNotes" placeholder="Optional notes…" autocomplete="off" oninput="_siMarkDirty()" />
      </div>
    </div>
    <div class="dialog-err" id="siErr"></div>
    <div class="dialog-actions">
      <button class="btn-cancel" onclick="forceCloseStandaloneInvoiceDialog()">Cancel</button>
      <button class="btn-dark" id="siBtn" onclick="submitStandaloneInvoice()">Create Invoice</button>
    </div>
  </div>
</div>

<!-- Add session dialog -->
<div class="overlay" id="sessionOverlay" onclick="if(event.target===this){var _d=this.querySelector('.session-dialog');if(_editMode?isSessionDirty():hasSessionData())shakeDialog(_d);else closeSessionDialog();}">
  <div class="session-dialog">
    <h2>
      <span class="session-dialog-title"><span id="sessionDialogAction">+ Session</span><span style="color:#1a5f7a"> — </span><span id="sessionGameTitle"></span></span>
      <div class="session-dialog-controls">
      <div class="rounds-wrap" id="roundsWrap">
        <button type="button" class="rounds-btn" onclick="_roundsChange(-1)" title="Remove a round" aria-label="Remove a round"><svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true" style="display:block"><path d="M2 6h8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button>
        <div class="rounds-count" id="roundsCount">0</div>
        <button type="button" class="rounds-btn" onclick="_roundsChange(1)" title="Add a round" aria-label="Add a round"><svg width="12" height="12" viewBox="0 0 12 12" aria-hidden="true" style="display:block"><path d="M2 6h8M6 2v8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button>
        <div class="rounds-label">Rounds</div>
      </div>
      <div class="sw-wrap" id="swWrap">
        <button class="btn-stopwatch" id="swBtn" onclick="toggleStopwatch()" onpointerdown="_swStartLongPress()" onpointerup="_swCancelLongPress(event)" onpointerleave="_swCancelLongPress(event)" title="Start / pause · Hold 2s to enter minutes">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0"><circle cx="12" cy="13" r="8"/><path d="M12 5V3"/><path d="M9 3h6"/><path d="M12 13V9"/></svg>
          <span id="swTime">0</span>
        </button>
        <div class="sw-panel" id="swPanel">
          <input type="number" id="swPanelInput" class="sw-panel-input" min="0" max="999" placeholder="0"
            onkeydown="if(event.key==='Enter'){event.preventDefault();_swExpandCommit();}if(event.key==='Escape')_swExpandCancel();"
            onclick="event.stopPropagation()" />
          <span class="sw-panel-unit">min</span>
          <button type="button" class="sw-panel-confirm" onclick="_swExpandCommit()">Set</button>
          <button type="button" class="sw-panel-cancel" onclick="_swExpandCancel()">✕</button>
        </div>
      </div>
      </div><!-- /.session-dialog-controls -->
    </h2>

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
              <option value="Editing">Editing</option>
              <option value="Reporting">Reporting</option>
            </select>
          </div>
        </div>
        <div class="session-meta-row">
          <div class="field-group">
            <label>Location</label>
            <div class="combo-wrap name-combo" id="sLocationCombo"><input type="text" class="field-input combo-input" id="sLocation" placeholder="Pick or type a place…" autocomplete="off" /><button type="button" class="combo-toggle" tabindex="-1" aria-label="Show places">▾</button><div class="combo-dropdown"></div></div>
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

    <!-- Observations + Solutions (dynamic pairs) -->
    <div class="obs-section">
      <div class="obs-pair-labels"><label>Observations</label><label>Solutions</label></div>
      <div id="obsContainer"></div>
    </div>

    <div class="dialog-err" id="sessionErr"></div>
    <span class="obs-kbd-hint">⌘ / Ctrl + Arrow — move between fields &nbsp;·&nbsp; ⌘ / Ctrl + Enter — save</span>
    <div class="dialog-actions">
      <button class="btn-delete" id="deleteSessionBtn" style="display:none" onclick="deleteSession()">Delete</button>
      <button class="btn-cancel" onclick="closeSessionDialog()">Cancel</button>
      <button class="btn-primary" id="sessionBtn" onclick="submitSession()">Add Session</button>
    </div>
  </div>
</div>

<!-- Time & Materials dialog -->
<div class="overlay" id="dashTMOverlay" onclick="if(event.target===this){if(_dashTMIsDirty())shakeDialog(this.querySelector('.session-dialog'));else forceCloseDashTMDialog();}">
  <div class="session-dialog" style="max-width:500px;position:relative" onclick="event.stopPropagation()" onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitDashTM();}">
    <button onclick="closeDashTMDialog()" style="position:absolute;top:.75rem;right:.75rem;background:none;border:none;cursor:pointer;font-size:1rem;color:#888;line-height:1;padding:.25rem .4rem" title="Close">&#x2715;</button>
    <h2>Time &amp; Materials — <span id="dashTMGameTitle"></span></h2>

    <div class="field-group" style="margin:0 0 .6rem">
      <label>Contract</label>
      <div class="combo-wrap" id="dashTMSumCombo">
        <input type="text" id="dashTMSummary" class="field-input combo-input" placeholder="Pick a contract’s summary, or type one" autocomplete="off" maxlength="120"
          oninput="sumRebuild('dashTM',this.value)" onfocus="sumRebuild('dashTM',this.value)" onkeydown="sumKey('dashTM',event)" />
        <div class="combo-dropdown" id="dashTMSumDrop"></div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 110px;gap:.5rem .6rem;margin-bottom:.6rem">
      <div class="field-group" style="margin:0">
        <label>Notes</label>
        <textarea class="field-input ge-textarea" id="dashTMTimeNotes" placeholder="What did you work on?" style="height:2.45rem;min-height:2.45rem;resize:vertical"></textarea>
      </div>
      <div class="field-group" style="margin:0">
        <label>Time</label>
        <div class="tm-time-wrap">
          <input type="text" class="field-input" id="dashTMTimeMinutes" placeholder="e.g. 1 hr"
            autocomplete="off" style="height:2.45rem"
            onfocus="_showDashTMTimeList()" oninput="_showDashTMTimeList()" onblur="_hideDashTMTimeList();_fmtTMTimeInput(this)" />
          <div class="tm-time-list" id="dashTMTimeList"></div>
        </div>
      </div>
    </div>

    <hr class="field-sep" style="margin:.2rem 0 .6rem" />

    <div>
      <div style="display:grid;grid-template-columns:1fr 110px;gap:0 .6rem;margin-bottom:.15rem">
        <div class="tm-section-label" style="margin:0">Material</div>
        <div class="tm-section-label" style="margin:0">Cost ($)</div>
      </div>
      <div id="dashTMMaterialsContainer"></div>
    </div>

    <div class="dialog-err" id="dashTMErr"></div>
    <div class="dialog-actions" style="margin-top:.5rem">
      <button class="btn-cancel" onclick="forceCloseDashTMDialog()">Cancel</button>
      <button class="btn-primary" id="dashTMBtn" onclick="submitDashTM()">Save</button>
    </div>
  </div>
</div>

<!-- Confirm dialog -->
<div class="overlay confirm-overlay" id="confirmOverlay" onclick="if(event.target===this)_closeConfirmDialog()">
  <div class="sync-dialog" style="width:min(360px,92vw)" onclick="event.stopPropagation()">
    <h2 id="confirmTitle" style="margin-bottom:.35rem">Confirm</h2>
    <p id="confirmMessage" style="font-family:'DINRegular',sans-serif;font-size:.88rem;color:#555;margin:.1rem 0 1.1rem;line-height:1.5"></p>
    <div class="sync-dialog-actions">
      <button class="notes-close" onclick="_closeConfirmDialog()">Cancel</button>
      <button class="notes-close" id="confirmOkBtn" onclick="_confirmOk()" style="background:#e53e3e;color:#fff;border-color:#e53e3e">Delete</button>
    </div>
  </div>
</div>

<!-- Upgrade to Pro dialog -->
<div class="overlay" id="upgradeOverlay" onclick="if(event.target===this)closeUpgradeDialog()">
  <div class="sync-dialog" style="width:min(520px,94vw);text-align:center" onclick="event.stopPropagation()">
    <h2 class="plan-title"><img src="images/db_icon_180.png" alt="">DevBoard plans</h2>
    <div class="plan-current" id="upgradeCurrent"></div>
    <p id="upgradeIntro" style="font-family:'DINRegular',sans-serif;font-size:.82rem;color:#666;margin:.2rem 0 .9rem">Pick the plan that fits how you work.</p>
    <div class="plan-grid">
      <div class="plan-card" id="planCore">
        <div class="plan-name">Core</div>
        <div class="plan-price">$2.99<span>/month</span></div>
        <ul>
          <li>Playtest sessions &amp; notes</li>
          <li>Timer, rounds &amp; photos</li>
          <li>Team sharing &amp; reports</li>
        </ul>
        <button class="plan-btn" id="planCoreBtn" onclick="startUpgradeCheckout('core')">Choose Core</button>
      </div>
      <div class="plan-card plan-pro" id="planPro">
        <div class="plan-name">Pro</div>
        <div class="plan-price">$3.99<span>/month</span></div>
        <ul>
          <li>Everything in Core</li>
          <li>Contracts &amp; estimates</li>
          <li>Invoices with PDFs</li>
        </ul>
        <button class="plan-btn" id="planProBtn" onclick="startUpgradeCheckout('pro')">Choose Pro</button>
      </div>
    </div>
    <div id="upgradeSubStatus" style="margin:.9rem 0 .7rem;font-size:.8rem;color:#888"></div>
    <div id="upgradeActKey" style="margin:-.3rem 0 .8rem"></div>
    <div class="sync-dialog-actions" style="justify-content:center;gap:.6rem">
      <button class="notes-close" onclick="closeUpgradeDialog()">Close</button>
      <button id="upgradeSubBtn" style="display:none;background:#f5a623;color:#fff;font-family:'DINBlack',sans-serif;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;padding:.45rem 1.1rem;border-radius:6px;border:none;cursor:pointer">Manage Billing</button>
    </div>
  </div>
</div>

<!-- TODO details -->
<div class="overlay" id="todoOverlay" onclick="if(event.target===this)closeTodoDialog()">
  <div class="sync-dialog todo-dlg" onclick="event.stopPropagation()">
    <div class="todo-dlg-meta" id="todoDlgMeta"></div>
    <label class="todo-dlg-lbl" for="todoDlgObsIn">TODO</label>
    <input type="text" class="todo-dlg-obs" id="todoDlgObsIn" placeholder="What needs doing">
    <div class="todo-dlg-pic" id="todoDlgPic" hidden><a id="todoDlgPicA" target="_blank" rel="noopener" title="Open the picture"><img id="todoDlgPicImg" alt="The TODO’s picture"></a><button type="button" class="todo-dlg-piclink" onclick="document.getElementById('todoDlgObsIn').hidden=false;this.hidden=true">Show the cell</button></div>
    <label class="todo-dlg-lbl" for="todoDlgSolIn">Notes</label>
    <textarea class="todo-dlg-sol" id="todoDlgSolIn" rows="4" placeholder="Details" oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight+4,window.innerHeight*.5)+'px'"></textarea>
    <div class="todo-dlg-tags" id="todoDlgTags"></div>
    <div class="todo-dlg-note" id="todoDlgNote">Save the session to edit this TODO.</div>
    <div class="todo-dlg-err" id="todoDlgErr"></div>
    <div class="sync-dialog-actions" style="justify-content:flex-end;gap:.5rem">
      <button class="notes-close" onclick="closeTodoDialog()">Cancel</button>
      <button class="todo-dlg-save" id="todoDlgSave" onclick="saveTodoDialog()">Save</button>
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
var MY_NAME             = <?= json_encode($_my_name) ?>;
var MY_EMAIL            = <?= json_encode($_my_email) ?>;
var MY_PHONE            = <?= json_encode($_my_phone) ?>;
var MY_COMPANY          = <?= json_encode($_company) ?>;
var MY_COMPANY_ADDRESS  = <?= json_encode($_company_address) ?>;
var MY_PAYMENT_INFO     = <?= json_encode($_payment_info) ?>;
var MY_LOGO             = <?= json_encode($_logo_url) ?>;
var MY_BIO_IMAGE    = <?= json_encode($_my_bio_image) ?>;
var MY_BIO_DESC     = <?= json_encode($_my_bio_desc) ?>;
var MY_BIO_SKILLS   = <?= json_encode($_my_bio_skills) ?>;
var MY_BIO_LOCATION = <?= json_encode($_my_bio_location) ?>;
var MY_BIO_DISCORD  = <?= json_encode($_my_bio_discord) ?>;
var MY_BIO_PAYMENT  = <?= json_encode($_my_bio_payment) ?>;
var MY_BIO_NOTES    = <?= json_encode($_my_bio_notes) ?>;
var _profilePhotoUrl = MY_BIO_IMAGE || '';
var PEOPLE_NAMES  = <?= json_encode(array_values($_people_names), JSON_UNESCAPED_UNICODE) ?>;
var PEOPLE_DATA   = <?= json_encode(array_values($_people_raw),  JSON_UNESCAPED_UNICODE) ?>;
var CONTRACT_RAW  = <?= json_encode(array_values($_contracts_raw), JSON_UNESCAPED_UNICODE) ?>;
// Each person's payment details from their bio (Bios tab "Payment"), by email — shown on payments to them
var BIO_PAYMENT   = <?= json_encode((object) array_filter(array_reduce($_bios, function ($m, $b) {
  $e = strtolower(ltrim(trim((string) ($b['Email'] ?? '')), "'")); if ($e !== '') $m[$e] = ltrim(trim((string) ($b['Payment'] ?? '')), "'"); return $m; }, [])), JSON_UNESCAPED_UNICODE) ?>;
var TODOS_RAW     = <?= json_encode(array_values($_todos_raw), JSON_UNESCAPED_UNICODE) ?>;
var ESTIMATES_RAW = <?= json_encode(array_values($_estimates_raw), JSON_UNESCAPED_UNICODE) ?>;
var SESSIONS_BRIEF = <?= json_encode($_sessions_brief, JSON_UNESCAPED_UNICODE) ?>;   // {g game, d date, e type, n number, w where, t people}

// Quick lookup: email (lowercase) → display name from People sheet
var _peopleByEmail = {};
PEOPLE_DATA.forEach(function(p) {
  var em = (p['Email'] || p['email'] || '').trim().toLowerCase();
  var nm = (p['Name']  || p['name']  || '').trim();
  if (em && nm) _peopleByEmail[em] = nm;
});

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
  if (v === null || v === undefined || v === '') return '';
  var s = String(v).trim();
  if (!s) return '';
  // Google Sheets serial number (integer > 1000, no slashes or dashes)
  if (/^\d+(\.\d+)?$/.test(s)) {
    var n = parseFloat(s);
    if (n > 1000) {
      var d = new Date(Date.UTC(1899, 11, 30) + Math.round(n) * 86400000);
      if (!isNaN(d.getTime())) return d.toISOString().slice(0, 10);
    }
  }
  // YYYY-MM-DD — already correct format
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) return s;
  // M/D/YYYY or MM/DD/YYYY
  var mdy = s.match(/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/);
  if (mdy) {
    var mm = mdy[1].padStart(2, '0'), dd = mdy[2].padStart(2, '0');
    return mdy[3] + '-' + mm + '-' + dd;
  }
  // Fallback: let the browser try
  var fb = new Date(s);
  return isNaN(fb.getTime()) ? '' : fb.toISOString().slice(0, 10);
}

// ── Tab switching ─────────────────────────────────────────────────────────────
var _activeTab = 'games';
// The tabs say how many, as in PitchBoard: "14 Games", "26 Contracts"
function _dbTabCounts() {
  var g = document.getElementById('tabGames'), c = document.getElementById('tabPublishers');
  var ng = (typeof allGames !== 'undefined' && allGames.length) ? allGames.length : GAMES_RAW.length;
  var nc = CONTRACT_RAW.filter(function(x) { return x && !_isPaymentRow(x); }).length;
  if (g) g.textContent = ng + (ng === 1 ? ' Game' : ' Games');
  if (c) c.textContent = nc + (nc === 1 ? ' Contract' : ' Contracts');
}
function switchTab(tab) {
  _activeTab = tab;
  _dbTabCounts();
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

// ── TODOs: the "todos" sheet and the Board list ─────────────────────────────
var _boardTodosEl = document.createElement('div');
_boardTodosEl.className = 'btodo-wrap';
var TODO_PRIORITIES = ['High', 'Medium', 'Low'];

function _todosPost(action, data, cb) {
  var fd = new FormData();
  fd.append('id', SHEET_ID); fd.append('action', action); fd.append('data', JSON.stringify(data));
  return fetch(APP_BASE + 'push/todos.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (res.error) throw new Error(res.error);
      if (res.todos) {
        TODOS_RAW = _todosKeepChecked(res.todos);
        if (_activeTab === 'dash' && !_btodoDragging) _renderBoardTodos();
        if (!_btodoMenuEl) Object.keys(_sessionCache).forEach(_refreshTodoSection);
      }
      if (cb) cb(res);
      return res;
    })
    .catch(function(e) { showSaveToast('Couldn\'t update TODOs — ' + _friendlySaveError(e)); throw e; });
}

// After a session save: add its light-bulb rows to the todos sheet (existing ones are left alone)
// Checked-off TODOs are deleted from the sheet, but stay on this page (struck through) until reload,
// so they can be unchecked. ID → { rec: the row as it was, before: ID of the row that followed it }
var _todosChecked = {};
function _todosKeepChecked(list) {
  list = list.slice();
  Object.keys(_todosChecked).forEach(function(id) {
    if (list.some(function(t) { return t.ID === id; })) return;
    var c = _todosChecked[id], rec = Object.assign({}, c.rec, { Status: 'Done' });
    var at = c.before ? list.findIndex(function(t) { return t.ID === c.before; }) : -1;
    if (at < 0) list.push(rec); else list.splice(at, 0, rec);
  });
  return list;
}

// Check / uncheck a TODO in the todos sheet: checked → its row is removed; unchecked → put back
function _todoSetChecked(t, done) {
  if (!t || !t.ID) return;
  if (done) {
    var at = TODOS_RAW.indexOf(t), next = null;
    for (var i = at + 1; i < TODOS_RAW.length; i++) { if (!_todosChecked[TODOS_RAW[i].ID]) { next = TODOS_RAW[i].ID; break; } }
    _todosChecked[t.ID] = { rec: Object.assign({}, t), before: next };
    t.Status = 'Done';
    _todosPost('status', { id: t.ID, status: 'Done', game: t.Game, obs: t.Observation || '', sol: t.Thoughts || '' }).catch(function() {});
  } else {
    var c = _todosChecked[t.ID]; delete _todosChecked[t.ID];
    t.Status = 'Open';
    var rec = Object.assign({}, (c && c.rec) || t, { Status: 'Open' });
    _todosPost('status', { id: t.ID, status: 'Open', todo: rec, before: c ? c.before : '' }).catch(function() {});
  }
}

function _todosSyncFromSave(game, eventType, sessionNum, date, pairs) {
  var items = (pairs || []).filter(function(p) { return p.todo; }).map(function(p) {
    return { id: p.todoId, game: game, obs: p.obs, sol: p.sol, session: [eventType, sessionNum].filter(Boolean).join(' ') + (date ? ' · ' + fmtDate(date) : '') };
  });
  if (items.length) _todosPost('add', { items: items }).catch(function() {});
}

// The list is in the same order as the rows of the todos sheet
function _todosSorted() { return TODOS_RAW.slice(); }

// A TODO's text that may be a picture cell (=IMAGE("url")): the picture as a thumbnail, any other text after it
function _todoImgUrl(s) { var m = /=IMAGE\(\s*"(https?:\/\/[^"]+)"/i.exec(String(s || '')); return m ? m[1] : ''; }
function _todoObsHtml(s, cls) {
  var url = _todoImgUrl(s), rest = String(s || '').replace(/=IMAGE\([^)]*\)/gi, '').trim();
  if (!url) return rest ? '<span class="' + cls + '">' + esc(rest) + '</span>' : '';
  return '<img class="todo-thumb" src="' + esc(url) + '" alt="" loading="lazy" onerror="this.replaceWith(document.createTextNode(\'🖼 Image\'))">' + (rest ? '<span class="' + cls + '">' + esc(rest) + '</span>' : '');
}
function _renderBoardTodos() {
  var list = _todosSorted();
  if (!list.length) { _boardTodosEl.innerHTML = ''; return; }
  var open = list.filter(function(t) { return (t.Status || '').toLowerCase() !== 'done'; }).length;
  var h = '<div class="dash-section-hd">TODOs <span class="btodo-count">' + open + ' open</span></div><div class="btodo-list" id="btodoList">';
  list.forEach(function(t) {
    var done = (t.Status || '').toLowerCase() === 'done';
    var id   = esc(t.ID || '');
    var obs  = String(t.Observation || '').replace(/=IMAGE\("[^"]*"\)/gi, '🖼 Image').trim();
    var pri  = t.Priority || '';
    h += '<div class="btodo-row' + (done ? ' done' : '') + '" data-id="' + id + '">';
    h += '<span class="btodo-handle" title="Drag to reorder" onpointerdown="_btodoDragStart(event)">⋮⋮</span>';
    h += '<input type="checkbox" class="btodo-check"' + (done ? ' checked' : '') + ' onchange="onBoardTodoCheck(\'' + id + '\', this)">';
    h += '<div class="btodo-text" onclick="openTodoDialog({ id: \'' + id + '\' })" title="' + esc([obs, t.Thoughts, [t.Game, t.Session].filter(Boolean).join(' · ')].filter(Boolean).join('\n')) + '">' +
           _todoObsHtml(t.Observation, 'btodo-obs') +
           (t.Thoughts ? '<span class="btodo-sol">' + esc(t.Thoughts) + '</span>' : '') +
           '<span class="btodo-meta">' + esc([t.Game, t.Session].filter(Boolean).join(' · ')) + '</span>' +
         '</div>';
    h += _todoIconsHtml(t, done);
    h += '</div>';
  });
  h += '</div>';
  _boardTodosEl.innerHTML = h;
}

// Priority / person / due-date icons for a TODO (t = row of the todos sheet; null → not saved there yet)
function _todoIconsHtml(t, done) {
  var id = t ? esc(t.ID || '') : '';
  function click(field) { return t ? ' onclick="_btodoMenu(event,\'' + id + '\',\'' + field + '\')"' : ' disabled'; }
  var pri = t ? (t.Priority || '') : '', pn = { high: 3, medium: 2, low: 1 }[pri.toLowerCase()] || 0;
  var who = t ? (t.Assigned || '') : '', due = t ? (t.Due || '') : '';
  var overdue = due && !done && due < todayISO();
  var none = 'Save the session to set this';
  return '<div class="btodo-fields">' +
    '<button type="button" class="btodo-ic btodo-pri' + (pn ? ' p' + pn : '') + '" title="' + (t ? 'Priority: ' + esc(pri || 'none') : none) + '"' + click('Priority') + '>' + (pn ? '!!!'.slice(0, pn) : '!') + '</button>' +
    '<button type="button" class="btodo-ic btodo-who' + (who ? ' set' : '') + '" title="' + (t ? (who ? 'Assigned to ' + esc(who) : 'Assign someone') : none) + '"' + click('Assigned') + '>' +
      '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg></button>' +
    '<button type="button" class="btodo-ic btodo-due' + (due ? ' set' : '') + (overdue ? ' overdue' : '') + '" title="' + (t ? (due ? 'Due ' + esc(fmtDate(due)) + (overdue ? ' (overdue)' : '') : 'Set a due date') : none) + '"' + click('Due') + '>' +
      '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4.5" width="18" height="17" rx="2"/><path d="M16 2.5v4M8 2.5v4M3 10h18"/></svg></button>' +
  '</div>';
}

// The todos-sheet row for a TODO in a game's notes (matched by game + text)
function _todoFor(game, obs, sol, id) {
  if (id) { var byId = _todoById(id); if (byId) return byId; }
  var g = String(game || '').toLowerCase();
  return TODOS_RAW.filter(function(t) {
    return String(t.Game || '').toLowerCase() === g && (t.Observation || '') === (obs || '') && (t.Thoughts || '') === (sol || '');
  })[0] || null;
}

function _todoById(id) { return TODOS_RAW.filter(function(t) { return t.ID === id; })[0]; }

function _btodoSet(id, field, value) {
  var t = _todoById(id); if (!t) return;
  t[field] = value;
  _btodoCloseMenu();
  _renderBoardTodos();
  _refreshTodoSection(t.Game);
  var f = {}; f[field] = value;
  _todosPost('update', { id: id, fields: f, game: t.Game, obs: t.Observation || '', sol: t.Thoughts || '' }).catch(function() {});
}

// Small dropdown under a priority / person / due-date icon
var _btodoMenuEl = null;
function _btodoCloseMenu() {
  if (_btodoMenuEl) { _btodoMenuEl.remove(); _btodoMenuEl = null; }
  document.removeEventListener('mousedown', _btodoOutside, true);
  document.removeEventListener('keydown', _btodoEsc, true);
}
function _btodoOutside(e) { if (_btodoMenuEl && !_btodoMenuEl.contains(e.target) && !e.target.closest('.btodo-ic')) _btodoCloseMenu(); }
function _btodoEsc(e) { if (e.key === 'Escape') _btodoCloseMenu(); }
function _btodoMenu(ev, id, field) {
  ev.stopPropagation();
  var btn = ev.currentTarget, same = _btodoMenuEl && _btodoMenuEl.dataset.for === id + field;
  _btodoCloseMenu();
  if (same) return;
  var t = _todoById(id); if (!t) return;
  var m = document.createElement('div');
  m.className = 'btodo-menu'; m.dataset.for = id + field;
  var idJ = id.replace(/'/g, "\\'");
  function item(label, value, cur, extra) {
    return '<button type="button" class="btodo-mi' + (cur ? ' cur' : '') + '" onclick="_btodoSet(\'' + idJ + '\',\'' + field + '\',' + JSON.stringify(value).replace(/"/g, '&quot;') + ')">' + (extra || '') + esc(label) + '</button>';
  }
  if (field === 'Priority') {
    m.innerHTML = [['High', '!!!', 3], ['Medium', '!!', 2], ['Low', '!', 1]].map(function(p) {
      return item(p[0], p[0], t.Priority === p[0], '<span class="btodo-bang p' + p[2] + '">' + p[1] + '</span>');
    }).join('') + item('No priority', '', !t.Priority, '<span class="btodo-bang">–</span>');
  } else if (field === 'Assigned') {
    var names = PEOPLE_NAMES.slice(); if (t.Assigned && names.indexOf(t.Assigned) < 0) names.unshift(t.Assigned);
    m.innerHTML = names.map(function(n) { return item(n, n, n === t.Assigned); }).join('') +
                  (names.length ? '' : '<div class="btodo-mempty">No people yet</div>') +
                  (t.Assigned ? item('Unassign', '', false) : '');
  } else {
    m.innerHTML = '<input type="date" class="btodo-date" value="' + esc(t.Due || '') + '" onchange="_btodoSet(\'' + idJ + '\',\'Due\',this.value)">' +
                  (t.Due ? item('Clear date', '', false) : '');
  }
  document.body.appendChild(m);
  var r = btn.getBoundingClientRect(), w = m.offsetWidth;
  m.style.top  = (r.bottom + window.scrollY + 4) + 'px';
  m.style.left = Math.max(8, Math.min(window.scrollX + document.documentElement.clientWidth - w - 8, r.right + window.scrollX - w)) + 'px';
  _btodoMenuEl = m;
  var di = m.querySelector('.btodo-date');
  if (di) { di.focus(); try { di.showPicker(); } catch (e) {} }
  document.addEventListener('mousedown', _btodoOutside, true);
  document.addEventListener('keydown', _btodoEsc, true);
}

function onBoardTodoCheck(id, cb) {
  var t = _todoById(id); if (!t) return;
  var done = cb.checked;
  cb.closest('.btodo-row').classList.toggle('done', done);
  // Keep the game card and the session dialog in step, and update the note on the dev cell (found by its ID)
  var dev = _todoDevText[id] || { obs: t.Observation || '', sol: t.Thoughts || '' };
  var it = { todoId: id, obs: dev.obs, sol: dev.sol };
  var k = _todoKey(t.Game, it.obs, it.sol, id), kt = _todoKey(t.Game, it.obs, it.sol);
  if (done) { _todoDone[k] = true; _todoDone[kt] = id; } else { delete _todoDone[k]; delete _todoDone[kt]; }
  _todoSetCacheNote(t.Game, it, done ? '' : 'TODO:' + id);
  _refreshTodoSection(t.Game);
  var fd = new FormData();
  fd.append('id', SHEET_ID); fd.append('game', t.Game); fd.append('todo_id', id); fd.append('row', 0);
  fd.append('obs', it.obs); fd.append('sol', it.sol); fd.append('note', done ? '' : 'TODO:' + id);
  fetch(APP_BASE + 'push/setDevNote.php', { method: 'POST', body: fd }).catch(function() {});
  _todoSetChecked(t, done);
}

// Drag to reorder (pointer events: mouse and touch).
// Press the ⋮⋮ handle, or press and hold anywhere on a row: the row lifts, follows the pointer,
// the other rows slide out of the way, and on release it settles into its new place.
var _btodoDragging = null, _btodoHold = null, _btodoNoClick = false;
function _btodoDragStart(ev) {          // the ⋮⋮ handle: starts at once
  var row = ev.target.closest('.btodo-row');
  if (!row || ev.button > 0) return;
  ev.preventDefault();
  _btodoBeginDrag(row, ev);
}
// Press-and-hold anywhere else on a row (not the checkbox or the icons)
_boardTodosEl.addEventListener('pointerdown', function(ev) {
  var row = ev.target.closest('.btodo-row');
  if (!row || ev.button > 0 || _btodoDragging) return;
  if (ev.target.closest('.btodo-handle, input, button, select, .btodo-menu')) return;
  var x0 = ev.clientX, y0 = ev.clientY;
  function cancel() { clearTimeout(_btodoHold); document.removeEventListener('pointermove', mv); document.removeEventListener('pointerup', cancel); document.removeEventListener('pointercancel', cancel); }
  function mv(e) { if (Math.abs(e.clientX - x0) > 6 || Math.abs(e.clientY - y0) > 6) cancel(); }
  document.addEventListener('pointermove', mv);
  document.addEventListener('pointerup', cancel);
  document.addEventListener('pointercancel', cancel);
  _btodoHold = setTimeout(function() { cancel(); _btodoNoClick = true; _btodoBeginDrag(row, ev); }, 280);
});
// A press-and-hold drag shouldn't also open the TODO when the button is released
_boardTodosEl.addEventListener('click', function(ev) {
  if (_btodoNoClick) { _btodoNoClick = false; ev.stopPropagation(); ev.preventDefault(); }
}, true);
_boardTodosEl.addEventListener('contextmenu', function(ev) { if (_btodoDragging) ev.preventDefault(); });

function _btodoBeginDrag(row, ev) {
  var list = row.parentNode;
  var rows = Array.prototype.slice.call(list.querySelectorAll('.btodo-row'));
  var from = rows.indexOf(row);
  var rects = rows.map(function(r) { return r.getBoundingClientRect(); });
  var h = rects[from].height, startY = ev.clientY, to = from;
  _btodoDragging = row;
  _btodoCloseMenu && _btodoCloseMenu();
  document.body.classList.add('btodo-dragging');
  row.classList.add('dragging');
  rows.forEach(function(r) { if (r !== row) r.style.transition = 'transform .18s ease'; });
  row.style.transition = 'box-shadow .15s, transform 0s';
  row.style.transform = 'translateY(0) scale(1.02)';

  function layout(dy) {
    var mid = rects[from].top + h / 2 + dy;
    to = 0;
    for (var i = 0; i < rows.length; i++) {
      if (i === from) continue;
      if (mid > rects[i].top + rects[i].height / 2) to += 1;
    }
    rows.forEach(function(r, i) {
      if (r === row) return;
      var shift = 0;
      if (from < to && i > from && i <= to) shift = -h;
      if (to < from && i >= to && i < from) shift = h;
      r.style.transform = shift ? 'translateY(' + shift + 'px)' : '';
    });
  }
  function move(e) {
    var dy = e.clientY - startY;
    var min = rects[0].top - rects[from].top, max = rects[rows.length - 1].bottom - rects[from].bottom;
    dy = Math.max(min - 8, Math.min(max + 8, dy));   // stay within the list
    row.style.transform = 'translateY(' + dy + 'px) scale(1.02)';
    layout(dy);
  }
  function up() {
    document.removeEventListener('pointermove', move);
    document.removeEventListener('pointerup', up);
    document.removeEventListener('pointercancel', up);
    // Glide into the slot, then put the row there for real and save the order
    var target = 0;
    if (to > from) for (var i = from + 1; i <= to; i++) target += rects[i].height;
    if (to < from) for (var j = to; j < from; j++) target -= rects[j].height;
    row.style.transition = 'transform .18s ease, box-shadow .18s';
    row.style.transform = 'translateY(' + target + 'px)';
    row.classList.remove('dragging');
    setTimeout(function() {
      rows.forEach(function(r) { r.style.transition = 'none'; r.style.transform = ''; });
      if (to !== from) {
        var others = rows.filter(function(r) { return r !== row; });
        list.insertBefore(row, others[to] || null);
      }
      void list.offsetHeight;
      rows.forEach(function(r) { r.style.transition = ''; });
      document.body.classList.remove('btodo-dragging');
      _btodoDragging = null;
      if (to === from) return;
      var ids = Array.prototype.map.call(list.querySelectorAll('.btodo-row'), function(r) { return r.dataset.id; });
      TODOS_RAW = ids.map(_todoById).filter(Boolean).concat(TODOS_RAW.filter(function(t) { return ids.indexOf(t.ID) < 0; }));
      _todosPost('order', { ids: ids }).catch(function() {});
    }, 190);
  }
  document.addEventListener('pointermove', move);
  document.addEventListener('pointerup', up);
  document.addEventListener('pointercancel', up);
}

var _todosSynced = false;
// Contracts from before Ref Numbers were codes (a plain number like 7, or none): once per page load the
// server gives them standard 8-character codes in the sheet; the page then shows them.
var _refsFixed = false;
function _fixOldRefs() {
  if (_refsFixed) return; _refsFixed = true;
  if (!(window.GBAccount && GBAccount.canEdit && GBAccount.canEdit())) { _refsFixed = false; return; }   // the owner, once the account is checked
  var old = CONTRACT_RAW.some(function(c) { return c && (c.Game || c.Client) && !/^[0-9A-Z]{8}$/.test(String(c['Ref Number'] || '').replace(/^'/, '').trim()); });
  if (!old) return;
  var fd = new FormData(); fd.append('id', SHEET_ID);
  fetch(APP_BASE + 'push/fixContractRefs.php', { method: 'POST', body: fd }).then(function(r) { return r.json(); }).then(function(r) {
    if (r && r.ok && r.changed && r.changed.length) {
      if (window.GBAccount) GBAccount.toast('✓ ' + r.changed.length + ' older contract' + (r.changed.length === 1 ? '' : 's') + ' got a Ref Number');
      setTimeout(function() { location.reload(); }, 1200);
    }
  }).catch(function() {});
}

// ── Summary: timeline of active projects ─────────────────────────────────────
// A project is a contract (not a payment) with a Target End Date (or a Duration to work one out from) that isn't finished: no actual End Date,
// and not paid (or only estimated) with its due date gone by. Estimates show striped. Bars run from the target start (or the contract date) to the due date.
// A date (ms, noon UTC) plus a duration written in words: "1 month", "2 months", "6 weeks", "10 days", "1.5 months", "1 year"
function _dashAddDuration(t, dur) {
  var m = /(\d+(?:\.\d+)?)\s*(d|day|days|w|wk|wks|week|weeks|m|mo|mos|month|months|y|yr|yrs|year|years)\b/i.exec(String(dur || ''));
  if (!m) return 0;
  var n = parseFloat(m[1]), u = m[2].toLowerCase().charAt(0), d = new Date(t);
  if (u === 'd') return t + Math.round(n * 864e5);
  if (u === 'w') return t + Math.round(n * 7 * 864e5);
  var months = u === 'y' ? n * 12 : n, whole = Math.floor(months);
  d.setUTCMonth(d.getUTCMonth() + whole);
  return d.getTime() + Math.round((months - whole) * 30 * 864e5);
}
// ── Summary: Activity — contracts and sessions together, newest first ─────────
var _actShown = 12;
function _dbDay(v) {   // a date as written in the sheet → noon UTC that day (0 when there's none)
  var s = String(v || '').trim(), m;
  if ((m = /^(\d{4})-(\d\d)-(\d\d)/.exec(s))) return Date.UTC(+m[1], m[2] - 1, +m[3], 12);
  if ((m = /^(\d{1,2})\/(\d{1,2})\/(\d{4})/.exec(s))) return Date.UTC(+m[3], m[1] - 1, +m[2], 12);
  if (/^\d{5}$/.test(s)) return Date.UTC(1899, 11, 30, 12) + s * 864e5;
  var d = new Date(s); return isNaN(d) || !s ? 0 : Date.UTC(d.getFullYear(), d.getMonth(), d.getDate(), 12);
}
function _dashActivityHtml() {
  var items = [];
  CONTRACT_RAW.forEach(function(c, i) { if (c) items.push({ k: 'c', c: c, i: i, t: _dbDay(c.Date), o: i }); });
  // Time & materials: one line per game, person and day (time added up, materials added up)
  var tmDay = {};
  (SESSIONS_BRIEF || []).forEach(function(s, i) {
    if (!s.tm) { items.push({ k: 's', s: s, t: _dbDay(s.d), o: i }); return; }
    var who = _resolveTMPerson(s.p || '') || '', key = s.g.toLowerCase() + '|' + who.toLowerCase() + '|' + _dbDay(s.d);
    var x = tmDay[key];
    if (!x) { x = tmDay[key] = { k: 'tm', g: s.g, who: who, t: _dbDay(s.d), o: i, mins: 0, cost: 0, notes: [] }; items.push(x); }
    x.o = i;
    if (s.e === 'Time') x.mins += _parseTMMinutes(s.v); else { var c = parseFloat(String(s.v || '').replace(/[^0-9.]/g, '')); if (!isNaN(c)) x.cost += c; }
    if (s.w && x.notes.indexOf(s.w) < 0) x.notes.push(s.w);
  });
  if (!items.length) return '';
  // Newest first; undated last. Same day: sessions before contracts, then later rows first
  var rank = { s: 0, tm: 1, c: 2 };
  items.sort(function(a, b) { return (b.t || -1) - (a.t || -1) || (a.k === b.k ? b.o - a.o : rank[a.k] - rank[b.k]); });
  var html = '<div><div class="dash-section-hd">Activity</div><div class="dash-contracts">';
  items.slice(0, _actShown).forEach(function(x) {
    var when = x.t ? fmtDate(new Date(x.t).toISOString().slice(0, 10)) : '';
    if (x.k === 'tm') {
      var parts = [], amt = [];
      if (x.mins) { parts.push(_fmtTMTime(x.mins)); amt.push(_fmtTMTime(x.mins)); }
      var money = '$' + x.cost.toLocaleString('en-US', { minimumFractionDigits: x.cost % 1 ? 2 : 0, maximumFractionDigits: 2 });
      if (x.cost) { parts.push('materials ' + money); amt.push(money); }
      var tsub = [x.who, x.notes.join('; ')].filter(Boolean).join(' · ');
      var label = x.mins && x.cost ? 'T&M' : x.mins ? 'Time' : 'Materials';
      html += '<div class="dash-contract-row act-row" onclick="_actOpenGame(' + JSON.stringify(x.g).replace(/"/g, '&quot;') + ')" title="' + esc(x.g + ' — ' + parts.join(', ')) + '">' +
        '<div><div class="dash-contract-game">' + esc(x.g) + '</div><div class="dash-contract-client">' + esc(tsub || parts.join(' · ')) + '</div></div>' +
        '<div style="color:#888;font-size:.72rem">' + esc(when) + '</div>' +
        '<div style="font-family:DINBlack,sans-serif;font-size:.78rem;white-space:nowrap">' + esc(amt.join(' + ')) + '</div>' +
        '<div><span class="act-badge act-tm">' + esc(label) + '</span></div></div>';
      return;
    }
    if (x.k === 's') {
      var s = x.s, type = s.e || 'Session', cls = 'stat-' + type.toLowerCase().replace(/[^a-z]/g, '');
      var sub = [type + (s.n ? ' ' + s.n : ''), s.w, s.t ? s.t + (s.t === 1 ? ' person' : ' people') : ''].filter(Boolean).join(' · ');
      html += '<div class="dash-contract-row act-row" onclick="_actOpenGame(' + JSON.stringify(s.g).replace(/"/g, '&quot;') + ')" title="Open ' + esc(s.g) + '">' +
        '<div><div class="dash-contract-game">' + esc(s.g) + '</div><div class="dash-contract-client">' + esc(sub) + '</div></div>' +
        '<div style="color:#888;font-size:.72rem">' + esc(when) + '</div><div></div>' +
        '<div><span class="act-badge ' + cls + '">' + esc(type) + '</span></div></div>';
      return;
    }
    var c = x.c, quoteNum = parseFloat(String(c.Quote || '').replace(/[$,]/g, ''));
    var quote = isNaN(quoteNum) ? '' : '$' + quoteNum.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
    var isPay = _isPaymentRow(c);
    var payment = isPay ? 'Paid out' : (_conStatus(c) || (/^(estimate|invoice)$/i.test(String(c.Type || '').trim()) ? _ceStatusValue(c) : ''));
    var badgeCls = 'client-contract-badge';
    if (!isPay) { if (payment === 'Paid' || payment === 'Fully Paid') badgeCls += ' paid'; else if (payment === 'Invoiced') badgeCls += ' invoiced'; else if (payment === 'Partial') badgeCls += ' partial'; }
    var what = String(c.Summary || '').trim();
    html += '<div class="dash-contract-row act-row"' + (isPay ? '' : ' onclick="openContractEditDialog(' + x.i + ')"') + '>' +
      '<div><div class="dash-contract-game">' + esc(c.Game || '—') + '</div><div class="dash-contract-client">' + esc(isPay ? '→ ' + (c.Payee || '') : [c.Client || '', what].filter(Boolean).join(' · ')) + '</div></div>' +
      '<div style="color:#888;font-size:.72rem">' + esc(when) + '</div>' +
      '<div style="font-family:DINBlack,sans-serif;font-size:.78rem">' + esc(quote) + '</div>' +
      '<div><span class="' + badgeCls + '">' + esc(payment || '—') + '</span></div></div>';
  });
  if (items.length > _actShown) html += '<button type="button" class="act-more" onclick="_actShown+=12;renderDashView()">Show more (' + (items.length - _actShown) + ')</button>';
  return html + '</div></div>';
}
// A session in Activity: open its game on the Games tab
function _actOpenGame(name) {
  switchTab('games');
  var card = Array.prototype.filter.call(document.querySelectorAll('#cardList [data-game]'), function(c) { return String(c.dataset.game).toLowerCase() === String(name).toLowerCase(); })[0];
  if (!card) return;
  if (!card.classList.contains('open')) toggleCard(card);
  setTimeout(function() { card.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 60);
}

// The hover card on a timeline row: the contract's summary first, then who, when and how the date was found
function _dtlTip(ev, row) {
  var tip = document.getElementById('dtlTip');
  if (!ev) { if (tip) tip.style.display = 'none'; return; }
  if (!tip) { tip = document.createElement('div'); tip.id = 'dtlTip'; tip.className = 'dtl-tip'; document.body.appendChild(tip); }
  var lines = String(row.getAttribute('data-tip') || '').split('\n');
  if (tip._for !== row) { tip._for = row; tip.innerHTML = '<b>' + esc(lines[0]) + '</b>' + lines.slice(1).map(function(l) { return '<span>' + esc(l) + '</span>'; }).join(''); }
  tip.style.display = 'block';
  var x = Math.min(ev.clientX + 14, window.innerWidth - tip.offsetWidth - 10), y = ev.clientY + 16;
  if (y + tip.offsetHeight > window.innerHeight - 8) y = ev.clientY - tip.offsetHeight - 12;
  tip.style.left = x + 'px'; tip.style.top = y + 'px';
}
function _dashTimelineHtml() {
  var DAY = 864e5, today = new Date(todayISO() + 'T12:00:00Z').getTime();
  // A date as written in the sheet (2026-11-30, 11/30/2026, a Sheets serial number…) → noon UTC that day
  var day = function(v) {
    var s = String(v || '').trim(), m;
    if ((m = /^(\d{4})-(\d\d)-(\d\d)/.exec(s))) return Date.UTC(+m[1], m[2] - 1, +m[3], 12);
    if ((m = /^(\d{1,2})\/(\d{1,2})\/(\d{4})/.exec(s))) return Date.UTC(+m[3], m[1] - 1, +m[2], 12);
    if (/^\d{5}$/.test(s)) return Date.UTC(1899, 11, 30, 12) + s * DAY;
    var d = new Date(s); return isNaN(d) || !s ? 0 : Date.UTC(d.getFullYear(), d.getMonth(), d.getDate(), 12);
  };
  // Estimates that were invoiced (a deposit, say) are going ahead: projects, not just offers
  var ref = function(c) { return String((c && c['Ref Number']) || '').replace(/^'/, '').trim(); };
  var invoiced = {};
  CONTRACT_RAW.forEach(function(c) {
    if (!c || !/^invoice$/i.test(String(c.Type || '').trim())) return;
    String(c.Notes || '').replace(/#([0-9A-Za-z]+)/g, function(m, r) { invoiced[r] = 1; });
    var sm = String(c.Summary || '').trim().toLowerCase(), gm = String(c.Game || '').trim().toLowerCase();   // same game and summary
    if (sm) CONTRACT_RAW.forEach(function(e) { if (e && /^estimate$/i.test(String(e.Type || '').trim()) && String(e.Summary || '').trim().toLowerCase() === sm && String(e.Game || '').trim().toLowerCase() === gm) invoiced[ref(e)] = 1; });
  });
  (typeof ESTIMATES_RAW !== 'undefined' ? ESTIMATES_RAW : []).forEach(function(e) { if (e && e.from_estimate) invoiced[String(e.from_estimate).trim()] = 1; });
  var items = [];
  CONTRACT_RAW.forEach(function(c, i) {
    if (!c || _isPaymentRow(c)) return;
    if (String(c['End Date'] || '').trim()) return;                                   // finished
    var start = day(c['Target Start Date']) || day(c['Start Date']) || day(c.Date);
    // The due date: the Target End Date, or else the start plus the Duration ("1 month", "6 weeks", "10 days")
    var end = day(c['Target End Date']), fromDur = false;
    if (!end && start) { end = _dashAddDuration(start, c.Duration); fromDur = !!end; }
    if (!end) return;
    if (!start) start = end - 14 * DAY;
    var st = _conStatus(c), paid = st === 'Paid' || st === 'Fully Paid';
    if (paid && end < today) return;                                                    // paid and done
    if (start > end) start = end;
    var est = /^estimate$/i.test(st || _ceStatusValue(c)) && !invoiced[ref(c)];       // only an offer so far (striped)
    if (est && end < today) return;                                                     // an offer whose dates have gone by
    items.push({ i: i, c: c, start: start, end: end, est: est, dur: fromDur });
  });
  var out = '<div><div class="dash-section-hd">Timeline · active projects</div>';
  if (!items.length) return out + '<div class="dtl dtl-empty">No active projects with due dates yet. Give a contract a <b>Target End Date</b> and it shows here.</div></div>';
  // A game with several contracts: its contracts together, under the game's name; games by their soonest due date
  var gk = function(x) { return String(x.c.Game || '').trim().toLowerCase(); }, first = {};
  items.forEach(function(x) { var k = gk(x); if (!(k in first) || x.end < first[k]) first[k] = x.end; });
  items.sort(function(a, b) { return first[gk(a)] - first[gk(b)] || (gk(a) < gk(b) ? -1 : gk(a) > gk(b) ? 1 : 0) || a.end - b.end || a.start - b.start; });
  var perGame = {}; items.forEach(function(x) { perGame[gk(x)] = (perGame[gk(x)] || 0) + 1; });
  var lo = Math.min(today - 7 * DAY, Math.min.apply(null, items.map(function(x) { return x.start; })));
  var hi = Math.max(today + 21 * DAY, Math.max.apply(null, items.map(function(x) { return x.end; }))) + 4 * DAY;
  if (hi - lo > 400 * DAY) lo = Math.max(lo, today - 90 * DAY);                       // a very long span: start a while before today
  var pct = function(t) { return Math.max(0, Math.min(100, (t - lo) / (hi - lo) * 100)); };
  // Month marks
  var marks = '', d = new Date(lo); d = new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth() + 1, 1, 12));
  while (d.getTime() < hi) {
    var lbl = d.toLocaleDateString(undefined, { month: 'short', timeZone: 'UTC' }) + (d.getUTCMonth() === 0 ? ' ' + d.getUTCFullYear() : '');
    marks += '<span class="dtl-mark" style="left:' + pct(d.getTime()).toFixed(2) + '%"><i>' + esc(lbl) + '</i></span>';
    d = new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth() + 1, 1, 12));
  }
  var todayX = pct(today).toFixed(2);
  out += '<div class="dtl"><div class="dtl-row dtl-head"><div></div><div class="dtl-track">' + marks + '<span class="dtl-today" style="left:' + todayX + '%"><i>Today</i></span></div><div></div></div>';
  var prevGame = null, firstWho = '', prevMany = false;
  items.forEach(function(x) {
    var k = gk(x), firstOfGame = k !== prevGame, many = perGame[k] > 1; prevGame = k;
    var c = x.c, left = pct(x.start), right = pct(x.end), w = Math.max(1.2, right - left);
    var days = Math.round((x.end - today) / DAY);
    var cls = days < 0 ? 'late' : days <= 7 ? 'soon' : 'ok';
    var due = days < 0 ? (-days) + (days === -1 ? ' day' : ' days') + ' late' : days === 0 ? 'Due today' : days === 1 ? 'Due tomorrow' : 'Due in ' + days + ' days';
    var who = String(c.Client || '').trim(), what = String(c.Summary || '').trim().split('\n')[0] || (c['Ref Number'] ? '#' + String(c['Ref Number']).replace(/^'/, '') : '');
    var sub = (firstOfGame ? [who, many ? what : ''] : [what, who !== String(firstWho || '').trim() ? who : '']).filter(Boolean).join(' · ') + (x.est ? ' · Estimate' : '');
    if (firstOfGame) firstWho = who;
    var afterGrp = firstOfGame && prevMany; prevMany = many;
    var smry = String(c.Summary || '').trim();
    var tip = (smry ? smry + '\n' : '') + (c.Game || '') + (who ? ' · ' + who : '') + (ref(c) ? ' · #' + ref(c) : '') + '\n' + fmtDate(new Date(x.start).toISOString().slice(0, 10)) + ' → ' + fmtDate(new Date(x.end).toISOString().slice(0, 10)) + (x.dur ? '\nDue date worked out from the duration (' + String(c.Duration).trim() + ')' : '') + (x.est ? '\nEstimate' : '');
    out += '<div class="dtl-row' + (many ? (firstOfGame ? ' grp first' : ' grp more') : '') + (firstOfGame ? '' : ' cont') + (afterGrp && !many ? ' after-grp' : '') + '" onclick="openContractEditDialog(' + x.i + ')" data-tip="' + esc(tip) + '" onmousemove="_dtlTip(event,this)" onmouseleave="_dtlTip(null)">' +
      '<div class="dtl-name">' + (firstOfGame ? '<b>' + esc(c.Game || '—') + (many ? ' <em>' + perGame[k] + ' contracts</em>' : '') + '</b>' : '') + '<span>' + esc(sub || what) + '</span></div>' +
      '<div class="dtl-track"><span class="dtl-today" style="left:' + todayX + '%"></span><span class="dtl-bar ' + cls + (x.est ? ' est' : '') + '" style="left:' + left.toFixed(2) + '%;width:' + w.toFixed(2) + '%"></span></div>' +
      '<div class="dtl-due ' + cls + '"><b>' + (x.dur ? '<small title="From the duration">≈ </small>' : '') + esc(fmtDate(new Date(x.end).toISOString().slice(0, 10))) + '</b><span>' + esc(due) + '</span></div></div>';
  });
  return out + '</div></div>';
}

function renderDashView() {
  var wrap = document.getElementById('dashWrap');
  if (!wrap) return;
  _dbTabCounts();
  _renderBoardTodos();
  // Once per page load: make sure every light-bulb note is in the todos sheet (recreates it if deleted)
  if (!_todosSynced) { _todosSynced = true; _todosPost('sync', {}).catch(function() {}); }
  _fixOldRefs();

  // Stat chips
  var activeCount = Object.keys(ACTIVE_KEYS).length;
  var html = '<div class="dash-stat-row">';
  html += '<div class="dash-stat"><div class="dash-stat-num">' + GAMES_RAW.length + '</div><div class="dash-stat-label">Games</div></div>';
  html += '<div class="dash-stat"><div class="dash-stat-num">' + activeCount + '</div><div class="dash-stat-label">Active Dev</div></div>';
  html += '<div class="dash-stat"><div class="dash-stat-num">' + CONTRACT_RAW.filter(function(c) { return c && !_isPaymentRow(c); }).length + '</div><div class="dash-stat-label">Contracts</div></div>';
  var _pubCount = Object.keys(CONTRACT_RAW.reduce(function(a,c){if((c.Client||'').trim())a[(c.Client||'').trim().toLowerCase()]=1;return a},{})).length;
  html += '<div class="dash-stat"><div class="dash-stat-num">' + (_pubCount || '—') + '</div><div class="dash-stat-label">Publishers</div></div>';
  html += '</div>';

  // Timeline: active projects (contracts with a target end date, not finished), by due date
  html += _dashTimelineHtml();

  // Activity: contracts and sessions, newest first
  html += _dashActivityHtml();

  wrap.innerHTML = html;
  // The totals at the top, then the TODOs, the timeline and the contracts
  var stats = wrap.querySelector('.dash-stat-row');
  wrap.insertBefore(_boardTodosEl, stats ? stats.nextSibling : wrap.firstChild);
}

function renderPublishersView() {
  var wrap = document.getElementById('publishersViewWrap');
  if (!wrap) return;
  _dbTabCounts();

  var byClient = {};
  CONTRACT_RAW.forEach(function(con) {
    if (!con) return;   // deleted this session
    var name = (con.Client || '').trim();
    if (!name) return;
    var key = name.toLowerCase();
    if (!byClient[key]) byClient[key] = { name: name, contracts: [] };
    byClient[key].contracts.push(con);
  });

  var publishers = Object.keys(byClient).sort().map(function(k) { return byClient[k]; });

  // Filter by search query
  if (_contractsQuery) {
    var q = _contractsQuery;
    publishers = publishers.filter(function(pub) {
      if (pub.name.toLowerCase().indexOf(q) >= 0) return true;
      // Check contracts
      for (var ci = 0; ci < pub.contracts.length; ci++) {
        var c = pub.contracts[ci];
        if ((c.Game || '').toLowerCase().indexOf(q) >= 0) return true;
        if ((c['Ref Number'] || '').toLowerCase().indexOf(q) >= 0) return true;
        if ((c.Notes || '').toLowerCase().indexOf(q) >= 0) return true;
      }
      // Check estimates
      var pubKey = pub.name.toLowerCase();
      for (var ei = 0; ei < ESTIMATES_RAW.length; ei++) {
        var e = ESTIMATES_RAW[ei];
        if ((e.client || '').trim().toLowerCase() !== pubKey) continue;
        if ((e.game || '').toLowerCase().indexOf(q) >= 0) return true;
        if ((e.estimate_num || '').toLowerCase().indexOf(q) >= 0) return true;
      }
      return false;
    });
  }

  if (!publishers.length) {
    wrap.innerHTML = _contractsQuery
      ? '<p class="publishers-empty">No results for "' + _contractsQuery + '".</p>'
      : '<p class="publishers-empty">No publishers yet. Add contracts to see them here.</p>';
    return;
  }

  var html = '';
  publishers.forEach(function(pub, pubIdx) {
    var contracts = pub.contracts;
    html += '<div class="client-card" id="pub-' + esc(pub.name.replace(/\s+/g,'_')) + '">';
    html += '<div class="client-card-header" onclick="togglePublisherCard(this.parentNode)">' +
      '<span>' + esc(pub.name) + '</span>' +
      '<span style="display:flex;align-items:center;gap:.5rem">' +
        '<span class="client-card-count">' + _pubConCount(contracts) + '</span>' +
        '<span class="client-card-chevron">▼</span>' +
      '</span>' +
    '</div>';
    html += '<div class="client-card-body">';

    // Build per-game groups (merge estimates + contracts under each unique game)
    var gameMap = {};
    function gameKey(n) { return (n || '').trim().toLowerCase() || '__none__'; }
    var pubEstimates = ESTIMATES_RAW.filter(function(e) {
      return (e.client || '').trim().toLowerCase() === pub.name.toLowerCase();
    });
    pubEstimates.forEach(function(est) {
      var k = gameKey(est.game);
      if (!gameMap[k]) gameMap[k] = { name: est.game || '—', estimates: [], contracts: [], payments: [] };
      gameMap[k].estimates.push(est);
    });
    // Estimates and invoices DevBoard made itself are listed from their documents (above); ones written
    // straight into the sheet (older ones, or added by hand) have no document, so they're listed as rows here
    var haveDoc = {};
    pubEstimates.forEach(function(e) { [e.contract_id, e.estimate_num].forEach(function(r) { r = String(r || '').replace(/^'/, '').trim(); if (r) haveDoc[r] = 1; }); });
    contracts.forEach(function(con) {
      var conType = (con.Type || '').trim().toLowerCase();
      if ((conType === 'estimate' || conType === 'invoice') && haveDoc[String(con['Ref Number'] || '').replace(/^'/, '').trim()]) return;
      var k = gameKey(con.Game);
      if (!gameMap[k]) gameMap[k] = { name: con.Game || '—', estimates: [], contracts: [], payments: [] };
      else if (con.Game) gameMap[k].name = con.Game; // contracts have canonical casing
      if (conType === 'payment') gameMap[k].payments.push(con);   // money paid out to a collaborator
      else gameMap[k].contracts.push(con);
    });
    var gameKeys = Object.keys(gameMap).sort(function(a, b) {
      return gameMap[a].name.localeCompare(gameMap[b].name);
    });

    gameKeys.forEach(function(gk, gIdx) {
      var g = gameMap[gk];
      var ggId = 'gg-' + (pub.name + '|' + g.name).replace(/[^a-z0-9|]/gi, '_');
      var summaryParts = [];
      var estOnlyCount = g.estimates.filter(function(e) { return e.type !== 'invoice'; }).length;
      var invCount     = g.estimates.filter(function(e) { return e.type === 'invoice'; }).length;
      if (estOnlyCount) summaryParts.push(estOnlyCount + (estOnlyCount === 1 ? ' estimate' : ' estimates'));
      if (invCount)     summaryParts.push(invCount     + (invCount     === 1 ? ' invoice'  : ' invoices'));
      // Rows without a document count by their type
      var rowType = function(c) { return (c.Type || '').trim().toLowerCase(); };
      estOnlyCount += g.contracts.filter(function(c) { return rowType(c) === 'estimate'; }).length;
      invCount     += g.contracts.filter(function(c) { return rowType(c) === 'invoice'; }).length;
      var conCount  = g.contracts.filter(function(c) { return rowType(c) !== 'estimate' && rowType(c) !== 'invoice'; }).length;
      summaryParts = [];
      if (estOnlyCount) summaryParts.push(estOnlyCount + (estOnlyCount === 1 ? ' estimate' : ' estimates'));
      if (invCount)     summaryParts.push(invCount     + (invCount     === 1 ? ' invoice'  : ' invoices'));
      if (conCount) summaryParts.push(conCount + (conCount === 1 ? ' contract' : ' contracts'));
      if (g.payments.length)  summaryParts.push(g.payments.length  + (g.payments.length  === 1 ? ' payment'  : ' payments'));

      html += '<div class="game-group" id="' + ggId + '">';
      html += '<div class="game-group-header" onclick="toggleGameGroup(\'' + ggId + '\')">' +
        '<div class="game-group-name">' + esc(g.name) + '</div>' +
        '<div class="game-group-summary">' + esc(summaryParts.join(' · ')) + '</div>' +
        '<div class="game-group-chevron">▼</div>' +
      '</div>';
      html += '<div class="game-group-body">';

      // Estimates for this game — sort latest date first
      // Find a contract index for EDIT GAME — prefer a true contract, but fall back to
      // any CONTRACT_RAW entry for this game (including Estimate/Invoice-type rows).
      var firstConDataIdx = -1;
      if (g.contracts.length) {
        firstConDataIdx = CONTRACT_RAW.indexOf(g.contracts[0]);
      } else {
        for (var ci = 0; ci < CONTRACT_RAW.length; ci++) {
          if (CONTRACT_RAW[ci] && gameKey(CONTRACT_RAW[ci].Game) === gk) {
            firstConDataIdx = ci;
            break;
          }
        }
      }
      var sortedEsts = g.estimates.slice().sort(function(a, b) {
        // Latest sheet row first (reverse of sheet order)
        return ESTIMATES_RAW.indexOf(b) - ESTIMATES_RAW.indexOf(a);
      });
      sortedEsts.forEach(function(est) {
        var amtFmt = est.amount ? '$' + parseFloat(est.amount).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) : '—';
        var globalIdx = ESTIMATES_RAW.indexOf(est);
        var openAttr = est.url ? ' onclick="event.stopPropagation();window.open(\'' + esc(est.url) + '\',\'_blank\')"' : '';
        html += '<div class="estimate-item" id="est-item-' + globalIdx + '">';
        // Header row — click to expand, no action buttons
        var isInvoice = (est.type === 'invoice');
        var estTag    = _estStatusTag(est);
        html += '<div class="estimate-item-header" onclick="_estToggle(' + globalIdx + ')">' +
          '<span class="estimate-type-pill' + estTag.cls + '">' + estTag.label + '</span>' +
          '<div class="estimate-num-badge">' + esc(est.estimate_num || '') + '</div>' +
          '<div class="estimate-date">' + esc(est.date || '') + (function(sm) { return sm ? '<span class="estimate-sum" title="' + esc(sm) + '">' + esc(sm) + '</span>' : ''; })(_contractSummaryFor(est.contract_id || est.estimate_num)) + '</div>' +
          '<div class="estimate-amount">' + esc(amtFmt) + '</div>' +
          '<div class="estimate-item-chevron">▼</div>' +
        '</div>';
        // Expanded body
        html += '<div class="estimate-item-body">';
        // Clickable details area — opens estimate URL
        html += '<div class="estimate-item-details"' + openAttr + '>';
        html += '<dl class="estimate-item-fields">';
        html += '<div class="estimate-item-field"><dt>Contract #</dt><dd>' + esc(est.estimate_num || '—') + '</dd></div>';
        html += '<div class="estimate-item-field"><dt>Amount</dt><dd>' + esc(amtFmt) + '</dd></div>';
        html += '<div class="estimate-item-field"><dt>Date</dt><dd>' + esc(est.date || '—') + '</dd></div>';
        if (est.game)   html += '<div class="estimate-item-field"><dt>Game</dt><dd>' + esc(est.game) + '</dd></div>';
        if (est.client) html += '<div class="estimate-item-field"><dt>Client</dt><dd>' + esc(est.client) + '</dd></div>';
        if (est.tab)    html += '<div class="estimate-item-field"><dt>Sheet Tab</dt><dd>' + esc(est.tab) + '</dd></div>';
        html += '</dl>';
        html += '</div>';
        html += '<div class="estimate-item-actions">';
        // Normal action buttons
        html += '<div id="est-act-btns-' + globalIdx + '" style="display:flex;gap:.5rem;align-items:center">';
        if (est.url) {
          var shareTypeLabel = est.type === 'invoice' ? 'Invoice' : 'Estimate';
          var shareSubject   = encodeURIComponent((est.game || '') + ' ' + shareTypeLabel + ' - ' + (est.estimate_num || ''));
          var shareBody      = encodeURIComponent('Download ' + shareTypeLabel + ': ' + est.url);
          html += '<button type="button" class="btn-contract-action" onclick="event.stopPropagation();window.open(\'mailto:?subject=' + shareSubject + '&body=' + shareBody + '\',\'_self\')">✉ Share</button>';
        }
        if (est.contract_id) {
          html += '<button type="button" class="btn-contract-action" onclick="event.stopPropagation();openContractEditForEstimate(' + globalIdx + ')">Edit</button>';
        }
        html += '<button type="button" class="btn-contract-action invoice" onclick="event.stopPropagation();openInvoiceFromEstimate(' + globalIdx + ')">+ Invoice</button>';
        html += '<button type="button" class="estimate-del-btn" onclick="event.stopPropagation();_estStartDelete(' + globalIdx + ')">🗑 Delete</button>';
        html += '</div>';
        // Inline confirm (shown in place of action buttons when delete is clicked)
        html += '<div id="est-act-confirm-' + globalIdx + '" style="display:none;align-items:center;justify-content:flex-end;gap:.5rem">';
        html += '<button type="button" class="est-nocancel-btn" onclick="event.stopPropagation();_estCancelDelete(' + globalIdx + ')">Don\'t Delete</button>';
        html += '<button type="button" class="est-confirm-btn" id="est-confirm-btn-' + globalIdx + '" onclick="event.stopPropagation();_estConfirmDelete(' + globalIdx + ')">Confirm</button>';
        html += '</div>';
        html += '</div>'; // .estimate-item-actions
        html += '</div>'; // .estimate-item-body
        html += '</div>'; // .estimate-item
      });

      // Payments to collaborators for this game — latest first
      g.payments.slice().sort(function(a, b) { return CONTRACT_RAW.indexOf(b) - CONTRACT_RAW.indexOf(a); })
        .forEach(function(pay) { html += _paymentItemHtml(pay); });

      // Contracts for this game — latest sheet row first
      var sortedCons = g.contracts.slice().sort(function(a, b) {
        return CONTRACT_RAW.indexOf(b) - CONTRACT_RAW.indexOf(a);
      });
      sortedCons.forEach(function(con) {
        var quoteNum = parseFloat(con.Quote || '');
        var quote    = isNaN(quoteNum) ? '—' : '$' + quoteNum.toLocaleString('en-US', {minimumFractionDigits:0, maximumFractionDigits:2});
        var payment  = _conStatus(con) || (/^(estimate|invoice)$/i.test(String(con.Type || '').trim()) ? _ceStatusValue(con) : '');
        var badgeCls = 'client-contract-badge';
        if (payment === 'Paid' || payment === 'Fully Paid') badgeCls += ' paid';
        else if (payment === 'Invoiced') badgeCls += ' invoiced';
        else if (payment === 'Partial')  badgeCls += ' partial';
        var ts = fmtDate(con['Target Start Date'] || '');
        var te = fmtDate(con['Target End Date']   || '');
        var dateRange = (ts || te) ? (ts || '?') + ' → ' + (te || '?') : '';
        var dataIdx = CONTRACT_RAW.indexOf(con);
        var ss = fmtDate(con['Start Date'] || '');
        var se = fmtDate(con['End Date']   || '');
        var actualRange = (ss || se) ? (ss || '?') + ' → ' + (se || '?') : '';
        var notes = (con.Notes || '').trim();
        var status = '';   // the Status column is shown as the badge above

        html += '<div class="contract-item" id="ci-' + dataIdx + '">';
        html += '<div class="client-contract-row" onclick="toggleContractItem(' + dataIdx + ')">';
        var refLbl = String(con['Ref Number'] || '').replace(/^'/, '').trim();
        html += '<div>' + (dateRange ? '<div class="client-contract-dates">' + esc(dateRange) + '</div>'
          : '<div class="client-contract-dates">' + esc([fmtDate(con.Date || ''), refLbl ? '#' + refLbl : ''].filter(Boolean).join(' · ')) + '</div>') + '</div>';
        html += '<div class="client-contract-quote">' + esc(quote) + '</div>';
        html += '<div><span class="' + badgeCls + '">' + esc(payment || '—') + '</span></div>';
        html += '<div class="client-contract-row-chevron">▼</div>';
        html += '</div>'; // .client-contract-row
        html += '<div class="client-contract-details">';
        if (actualRange || status) {
          html += '<div class="contract-detail-fields">';
          if (actualRange) html += '<div class="contract-detail-field"><label>Actual Dates</label><span>' + esc(actualRange) + '</span></div>';
          if (status)      html += '<div class="contract-detail-field"><label>Status</label><span>' + esc(status) + '</span></div>';
          html += '</div>';
        }
        if (notes) html += '<div class="contract-detail-notes">' + esc(notes) + '</div>';
        // Share + Delete button row
        var conFilesUrl = (con.Files || '').trim();
        var conType     = (con.Type || '').trim() || 'Contract';
        html += '<div class="contract-del-row" id="cdel-row-' + dataIdx + '">';
        if (conFilesUrl) {
          var conShareSubject = encodeURIComponent((g.name || '') + ' ' + conType + ' - ' + (con['Ref Number'] || ''));
          var conShareBody    = encodeURIComponent('Download ' + conType + ': ' + conFilesUrl);
          html += '<button type="button" class="btn-contract-action" onclick="event.stopPropagation();window.open(\'mailto:?subject=' + conShareSubject + '&body=' + conShareBody + '\',\'_self\')">✉ Share</button>';
        }
        html += '<button type="button" class="btn-contract-action" onclick="event.stopPropagation();openContractEditDialog(' + dataIdx + ')">Edit</button>';
        html += '<button type="button" class="estimate-del-btn" onclick="event.stopPropagation();_conStartDelete(' + dataIdx + ')">🗑 Delete Contract</button>';
        html += '</div>';
        html += '<div class="est-confirm contract-del-confirm" id="cdel-confirm-' + dataIdx + '" style="display:none;padding:.45rem 1rem;">' +
          '<button type="button" class="est-nocancel-btn" onclick="event.stopPropagation();_conCancelDelete(' + dataIdx + ')">Don\'t Delete</button>' +
          '<button type="button" class="est-confirm-btn" id="cdel-btn-' + dataIdx + '" onclick="event.stopPropagation();_conConfirmDelete(' + dataIdx + ')">Confirm</button>' +
        '</div>';
        html += '</div>'; // .client-contract-details
        html += '</div>'; // .contract-item
      });

      // Footer: Edit Game (left) | + Estimate + + Invoice + + Report (right)
      html += '<div class="game-group-footer">';
      html += '<div class="game-group-footer-left">';
      html += '<button class="btn-contract-action" onclick="event.stopPropagation();openEditGame(' + esc(JSON.stringify(g.name)) + ')">Edit Game</button>';
      html += '<button type="button" class="btn-card-subtitle" onclick="event.stopPropagation();openEstimateDialog(' + esc(JSON.stringify(pub.name)) + ',' + esc(JSON.stringify(g.name)) + ')">+ Estimate</button>';
      html += '<button type="button" class="btn-contract-action invoice" onclick="event.stopPropagation();openStandaloneInvoiceDialog(' + esc(JSON.stringify(g.name)) + ',' + esc(JSON.stringify(pub.name)) + ')">+ Invoice</button>';
      html += '<button type="button" class="btn-contract-action payment" onclick="event.stopPropagation();openPaymentDialog(' + esc(JSON.stringify(pub.name)) + ',' + esc(JSON.stringify(g.name)) + ')">+ Payment</button>';
      html += '<button type="button" class="btn-contract-action report" onclick="event.stopPropagation();createReport(' + esc(JSON.stringify(g.name)) + ',' + esc(JSON.stringify(pub.name)) + ')">+ Report</button>';
      html += '</div>';
      html += '<div style="display:flex;gap:.4rem;align-items:center"></div>';
      html += '</div>';
      html += '</div>'; // .game-group-body
      html += '</div>'; // .game-group
    });

    html += '</div>'; // .client-card-body
    html += '</div>'; // .client-card
  });
  wrap.innerHTML = html;
}

function togglePublisherCard(card) {
  card.classList.toggle('open');
}

function toggleGameGroup(id) {
  var el = document.getElementById(id);
  if (el) el.classList.toggle('open');
}

// ── Payments to collaborators ─────────────────────────────────────────────────
// A payment is a Contracts row with Type "Payment": Payee (who was paid), Quote (amount), Date, Notes, and
// Client/Game so it sits with that publisher's game. Shown in purple — money going out.
function _isPaymentRow(con) { return !!con && /^payment$/i.test(String(con.Type || '').trim()); }
function _pubConCount(contracts) {
  var pays = contracts.filter(_isPaymentRow).length, cons = contracts.length - pays;
  var parts = [];
  if (cons || !pays) parts.push(cons + (cons === 1 ? ' contract' : ' contracts'));
  if (pays) parts.push(pays + (pays === 1 ? ' payment' : ' payments'));
  return parts.join(' · ');
}
function _paymentItemHtml(pay) {
  var idx = CONTRACT_RAW.indexOf(pay), n = parseFloat(pay.Quote || '');
  var amt = isNaN(n) ? '—' : '$' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  var when = fmtDate(pay.Date || '') || pay.Date || '';
  var h = '<div class="estimate-item pay-item" id="ci-' + idx + '">';
  h += '<div class="estimate-item-header" onclick="_payToggle(' + idx + ')">' +
    '<span class="estimate-type-pill payout">Paid out</span>' +
    '<div class="pay-payee">' + esc(pay.Payee || '—') + (_cleanSummary(pay.Summary) ? ' <span style="opacity:.6">· ' + esc(_cleanSummary(pay.Summary)) + '</span>' : '') + '</div>' +
    '<div class="estimate-date">' + esc(when) + '</div>' +
    '<div class="estimate-amount out">' + esc(amt) + '</div>' +
    '<div class="estimate-item-chevron">▼</div></div>';
  h += '<div class="estimate-item-body"><div class="estimate-item-details" style="cursor:default"><dl class="estimate-item-fields">' +
    '<div class="estimate-item-field"><dt>Paid to</dt><dd>' + esc(pay.Payee || '—') + '</dd></div>' +
    (_cleanSummary(pay.Summary) ? '<div class="estimate-item-field"><dt>For</dt><dd>' + esc(_cleanSummary(pay.Summary)) + '</dd></div>' : '') +
    '<div class="estimate-item-field"><dt>Amount</dt><dd>' + esc(amt) + '</dd></div>' +
    '<div class="estimate-item-field"><dt>Date</dt><dd>' + esc(when || '—') + '</dd></div>' +
    (pay['Ref Number'] ? '<div class="estimate-item-field"><dt>Ref #</dt><dd>' + esc(pay['Ref Number']) + '</dd></div>' : '') +
    (_payeePayment(pay.Payee) ? '<div class="estimate-item-field"><dt>Paid via</dt><dd>' + esc(_payeePayment(pay.Payee)) + '</dd></div>' : '') +
    '</dl>' + (pay.Notes ? '<div class="contract-detail-notes" style="margin:.4rem 0 0">' + esc(pay.Notes) + '</div>' : '') + '</div>';
  h += '<div class="estimate-item-actions">' +
    '<div id="cdel-row-' + idx + '" style="display:flex;gap:.5rem;align-items:center">' +
      '<button type="button" class="btn-contract-action" id="pay-share-btn-' + idx + '" onclick="event.stopPropagation();_paySlipShare(' + idx + ')">✉ Share</button>' +
      '<button type="button" class="btn-contract-action payment" id="pay-slip-btn-' + idx + '" onclick="event.stopPropagation();_payOpenSlip(' + idx + ')">Payment Slip</button>' +
      '<button type="button" class="estimate-del-btn" onclick="event.stopPropagation();_conStartDelete(' + idx + ')">🗑 Delete Payment</button></div>' +
    '<div id="cdel-confirm-' + idx + '" style="display:none;align-items:center;justify-content:flex-end;gap:.5rem">' +
      '<button type="button" class="est-nocancel-btn" onclick="event.stopPropagation();_conCancelDelete(' + idx + ')">Don\'t Delete</button>' +
      '<button type="button" class="est-confirm-btn" id="cdel-btn-' + idx + '" onclick="event.stopPropagation();_conConfirmDelete(' + idx + ')">Confirm</button></div>' +
    '</div></div></div>';
  return h;
}
function _payToggle(idx) { var el = document.getElementById('ci-' + idx); if (el) el.classList.toggle('open'); }
// The sender's details for the slip — the same ones estimates and invoices use
function _payAppendMe(fd) {
  fd.append('my_name', MY_NAME || ''); fd.append('my_phone', MY_PHONE || ''); fd.append('my_company', MY_COMPANY || '');
  fd.append('my_logo', MY_LOGO || ''); fd.append('my_address', MY_COMPANY_ADDRESS || MY_BIO_LOCATION || '');
}
function _payeeEmail(name) {
  var n = String(name || '').trim().toLowerCase();
  for (var i = 0; i < (PEOPLE_DATA || []).length; i++) {
    var p = PEOPLE_DATA[i] || {};
    if (String(p.Name || '').replace(/^'/, '').trim().toLowerCase() === n) return String(p.Email || '').replace(/^'/, '').trim();
  }
  return /@/.test(n) ? n : '';
}
function _payeePayment(name) { var e = _payeeEmail(name).toLowerCase(); return (e && BIO_PAYMENT && BIO_PAYMENT[e]) || ''; }
// Make the slip on the server (always fresh: today's bio and settings), then hand its link to done(url)
function _payMakeSlip(idx, btn, done, fail) {
  var pay = CONTRACT_RAW[idx]; if (!pay) return;
  var label = btn ? btn.textContent : '';
  if (btn) { btn.disabled = true; btn.textContent = 'Making…'; }
  var fd = new FormData(); fd.append('id', SHEET_ID); fd.append('ref', pay['Ref Number'] || pay.ID || ''); _payAppendMe(fd);
  fetch(APP_BASE + 'push/createPaymentSlip.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(j) {
      if (!j || !j.ok) throw new Error((j && j.error) || 'Could not make the slip');
      pay.Files = j.url;
      if (btn) { btn.disabled = false; btn.textContent = label; }
      done(j.url);
    })
    .catch(function(e) { if (btn) { btn.disabled = false; btn.textContent = label; } if (fail) fail(); alert(e.message); });
}
// Payment Slip: a tab opens straight away (so it isn't blocked), and shows the slip once it's made
function _payOpenSlip(idx) {
  var win = window.open('', '_blank');
  if (win) win.document.write('<p style="font:16px -apple-system,Arial,sans-serif;color:#666;padding:2rem">Making the payment slip…</p>');
  _payMakeSlip(idx, document.getElementById('pay-slip-btn-' + idx),
    function(url) { if (win) win.location.href = url; else window.open(url, '_blank'); },
    function() { if (win) win.close(); });
}
function _paySlipShare(idx) {
  var pay = CONTRACT_RAW[idx]; if (!pay) return;
  var send = function(url) {
    var n = parseFloat(pay.Quote || ''), amt = isNaN(n) ? '' : '$' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    var subject = 'Payment ' + (pay['Ref Number'] || '') + (pay.Game ? ' — ' + pay.Game : '');
    var body = 'Hi ' + String(pay.Payee || '').split(' ')[0] + ',\n\nI sent you ' + amt + (pay.Game ? ' for ' + pay.Game : '') + '. Here is the payment slip:\n' + url + '\n\nThanks!\n' + (MY_NAME || '');
    window.location.href = 'mailto:' + encodeURIComponent(_payeeEmail(pay.Payee)) + '?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
  };
  if (pay.Files) send(pay.Files); else _payMakeSlip(idx, document.getElementById('pay-share-btn-' + idx), send);
}
var _payCtx = null, _payInitial = {};
// ── Contract summaries: the one-line Summary every contract has ─────────────
function _cleanSummary(v) { return String(v || '').replace(/^'/, '').replace(/\s+/g, ' ').trim(); }
function _contractSummaryFor(ref) {   // a contract's Summary by its Ref Number
  ref = String(ref || ''); if (!ref) return '';
  for (var i = 0; i < CONTRACT_RAW.length; i++) {
    var c = CONTRACT_RAW[i]; if (String(c['Ref Number'] || c.ID || '') === ref) return _cleanSummary(c.Summary);
  }
  return '';
}
// The invoices' summaries for a game (newest first), for T&M and payments to pick from; every game's if none
function _contractSummaries(game) {   // [{s: summary, ref: Ref Number}]
  var g = String(game || '').trim().toLowerCase(), mine = [], all = [], seen = {};
  CONTRACT_RAW.slice().reverse().forEach(function(c) {
    if (!/^invoice$/i.test(String(c.Type || '').trim())) return;   // T&M and payouts go with invoices (not estimates or payments)
    var sm = _cleanSummary(c.Summary) || _cleanSummary(c.Description) || _cleanSummary(c.Game);   // older invoices have no Summary yet
    var it = { s: sm, ref: String(c['Ref Number'] || c.ID || '').replace(/^'/, '').trim() }, k = it.s + '\u0001' + it.ref;
    if (seen[k]) return; seen[k] = 1;
    all.push(it);
    if (g && String(c.Game || '').trim().toLowerCase() === g) mine.push(it);
  });
  return mine.length ? mine : all;
}
// A summary combo (the combo box pattern): fooSum…(prefix) — input #{prefix}Summary, list #{prefix}SumDrop in #{prefix}SumCombo
var _sumSrc = {};
function sumRebuild(px, filter) {
  var drop = document.getElementById(px + 'SumDrop'), lower = (filter || '').toLowerCase();
  var items = (_sumSrc[px] || []).filter(function(n) { return !lower || (n.s + ' ' + n.ref).toLowerCase().indexOf(lower) !== -1; });
  if (!items.length) { drop.innerHTML = ''; document.getElementById(px + 'SumCombo').classList.remove('open'); return; }
  drop.innerHTML = items.map(function(n) {   // the summary, with its Ref # in brackets
    return '<div class="combo-item" data-name="' + esc(n.s) + '" data-ref="' + esc(n.ref) + '" onmousedown="sumPick(\'' + px + '\',this.dataset.name,this.dataset.ref)">' + esc(n.s) +
      (n.ref ? ' <span style="opacity:.55">(' + esc(n.ref) + ')</span>' : '') + '</div>';
  }).join('');
  document.getElementById(px + 'SumCombo').classList.add('open');
}
function sumPick(px, n, ref) {
  document.getElementById(px + 'Summary').value = n;
  document.getElementById(px + 'Summary').dataset.ref = ref || '';
  document.getElementById(px + 'SumDrop').innerHTML = '';
  document.getElementById(px + 'SumCombo').classList.remove('open');
}
function sumKey(px, e) {
  if (e.key === 'Escape') { document.getElementById(px + 'SumCombo').classList.remove('open'); }
  if (e.key === 'Enter' && !(e.metaKey || e.ctrlKey)) { var first = document.querySelector('#' + px + 'SumDrop .combo-item'); if (first) { e.preventDefault(); sumPick(px, first.dataset.name, first.dataset.ref); } }
}
// The Ref Number of the contract chosen in a summary combo (the one picked, else the first with that summary)
function sumRef(px) {
  var inp = document.getElementById(px + 'Summary'), v = _cleanSummary(inp.value);
  var src = _sumSrc[px] || [], picked = inp.dataset.ref || '';
  if (picked && src.some(function(n) { return n.s === v && n.ref === picked; })) return picked;
  var m = src.filter(function(n) { return n.s.toLowerCase() === v.toLowerCase(); })[0];
  return m ? m.ref : '';
}

function _payValues() {
  return { person: document.getElementById('payPersonInput').value.trim(), summary: document.getElementById('paySummary').value.trim(), amount: document.getElementById('payAmount').value.trim(),
           date: document.getElementById('payDate').value, notes: document.getElementById('payNotes').value.trim() };
}
function openPaymentDialog(client, game) {
  _payCtx = { client: client || '', game: game || '' };
  document.getElementById('payGameTitle').textContent = game || '';
  document.getElementById('payPersonInput').value = '';
  _sumSrc.pay = _contractSummaries(game);
  var _one = _sumSrc.pay.length === 1 ? _sumSrc.pay[0] : null;   // only one invoice: it's the one
  document.getElementById('paySummary').value = _one ? _one.s : '';
  document.getElementById('paySummary').dataset.ref = _one ? _one.ref : '';
  document.getElementById('paySumCombo').classList.remove('open');
  document.getElementById('payAmount').value = '';
  var d = new Date(); document.getElementById('payDate').value = d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
  document.getElementById('payNotes').value = '';
  document.getElementById('payErr').style.display = 'none';
  var b = document.getElementById('payBtn'); b.disabled = false; b.textContent = 'Save Payment';
  document.getElementById('payPersonCombo').classList.remove('open');
  _payInitial = _payValues();
  document.getElementById('paymentOverlay').classList.add('open');
  setTimeout(function() { document.getElementById('payPersonInput').focus(); }, 60);
}
function _payIsDirty() { var v = _payValues(); return Object.keys(v).some(function(k) { return v[k] !== _payInitial[k]; }); }
function closePaymentDialog() {
  if (_payIsDirty()) { shakeDialog(document.getElementById('paymentOverlay').querySelector('.contract-dialog')); return; }
  forceClosePaymentDialog();
}
function forceClosePaymentDialog() { document.getElementById('paymentOverlay').classList.remove('open'); }
function payPersonRebuild(filter) {
  var drop = document.getElementById('payPersonDrop'), lower = (filter || '').toLowerCase();
  var items = (PEOPLE_NAMES || []).filter(function(n) { return n && (!lower || n.toLowerCase().indexOf(lower) !== -1); });
  if (!items.length) { drop.innerHTML = ''; document.getElementById('payPersonCombo').classList.remove('open'); return; }
  drop.innerHTML = items.map(function(n) {
    return '<div class="combo-item" data-name="' + esc(n) + '" onmousedown="payPersonPick(this.dataset.name)">' + esc(n) + '</div>';
  }).join('');
  document.getElementById('payPersonCombo').classList.add('open');
}
function payPersonPick(n) {
  document.getElementById('payPersonInput').value = n;
  document.getElementById('payPersonDrop').innerHTML = '';
  document.getElementById('payPersonCombo').classList.remove('open');
  document.getElementById('paySummary').focus();
}
function payPersonKey(e) {
  if (e.key === 'Escape') { document.getElementById('payPersonCombo').classList.remove('open'); }
  if (e.key === 'Enter' && !(e.metaKey || e.ctrlKey)) { var first = document.querySelector('#payPersonDrop .combo-item'); if (first) { e.preventDefault(); payPersonPick(first.dataset.name); } }
}
// A name box with a list: type to filter the people you know (People tab), pick with the mouse or the
// arrow keys and Enter, or keep typing a new name. ▾ shows everyone. Names already picked in the
// boxes of the same group are left out.
function _nameCombo(inputId, group, opts) {
  opts = opts || {};
  var inp = document.getElementById(inputId); if (!inp || inp._combo) return; inp._combo = true;
  var wrap = inp.parentNode, drop = wrap.querySelector('.combo-dropdown'), hl = -1;
  function taken() { return (group || []).filter(function(id) { return id !== inputId; }).map(function(id) { var e = document.getElementById(id); return e ? e.value.trim().toLowerCase() : ''; }).filter(Boolean); }
  function names() {
    if (opts.source) return opts.source();
    var seen = {}, out = [];
    [MY_NAME].concat(PEOPLE_NAMES || []).forEach(function(n) { n = String(n || '').trim(); var k = n.toLowerCase(); if (n && !seen[k]) { seen[k] = 1; out.push(n); } });
    return out.sort(function(a, b) { return a.localeCompare(b); });
  }
  function build(all) {
    var q = all ? '' : inp.value.trim().toLowerCase(), skip = taken();
    var list = names().filter(function(n) { var k = n.toLowerCase(); return skip.indexOf(k) < 0 && (!q || k.indexOf(q) >= 0) && k !== q; });
    hl = -1;
    if (!list.length) { drop.innerHTML = q ? '<div class="combo-empty">' + (opts.newText || 'New name — it’s added to People when you save') + '</div>' : ''; wrap.classList.toggle('open', !!q); return; }
    drop.innerHTML = list.map(function(n) { return '<div class="combo-item" data-name="' + esc(n) + '">' + esc(n) + (!opts.source && n === MY_NAME ? '<small>you</small>' : '') + '</div>'; }).join('');
    wrap.classList.add('open');
  }
  function close() { wrap.classList.remove('open'); hl = -1; }
  function pick(n) { inp.value = n; close(); inp.dispatchEvent(new Event('input', { bubbles: true })); close(); }
  function mark() { drop.querySelectorAll('.combo-item').forEach(function(x, i) { x.classList.toggle('hl', i === hl); if (i === hl) x.scrollIntoView({ block: 'nearest' }); }); }
  inp.addEventListener('input', function() { if (document.activeElement === inp) build(false); });
  inp.addEventListener('focus', function() { build(false); });
  inp.addEventListener('blur', function() { setTimeout(close, 120); });
  inp.addEventListener('keydown', function(e) {
    var items = drop.querySelectorAll('.combo-item');
    if (e.key === 'ArrowDown') { e.preventDefault(); if (!wrap.classList.contains('open')) build(true); hl = Math.min(items.length - 1, hl + 1); mark(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); hl = Math.max(0, hl - 1); mark(); }
    else if (e.key === 'Enter' && wrap.classList.contains('open') && hl >= 0 && items[hl]) { e.preventDefault(); pick(items[hl].dataset.name); }
    else if (e.key === 'Escape' && wrap.classList.contains('open')) { e.stopPropagation(); close(); }
    else if (e.key === 'Tab') close();
  });
  drop.addEventListener('mousedown', function(e) { var it = e.target.closest('.combo-item'); if (it) { e.preventDefault(); pick(it.dataset.name); } });
  var tg = wrap.querySelector('.combo-toggle');
  if (tg) tg.addEventListener('mousedown', function(e) { e.preventDefault(); if (wrap.classList.contains('open')) close(); else { inp.focus(); build(true); } });
}
(function() {
  var ids = ['gDesigner1', 'gDesigner2', 'gDesigner3', 'gDesigner4'];
  var go = function() {
    ids.forEach(function(id) { _nameCombo(id, ids); });
    // A session's location: the places used before, in every game — the most used first
    _nameCombo('sLocation', null, { newText: 'A new place', source: function() {
      var n = {}, add = function(v) { v = String(v || '').trim(); if (v && v.length <= 60 && !/^length:/i.test(v)) { var k = v.toLowerCase(); n[k] = n[k] || { v: v, c: 0 }; n[k].c++; } };
      var cache = typeof _sessionCache !== 'undefined' ? _sessionCache : {}, fresh = {};
      Object.keys(cache).forEach(function(g) { fresh[g.toLowerCase()] = 1; });   // games opened here: their own (newer) sessions count, not the brief
      (SESSIONS_BRIEF || []).forEach(function(s) { if (!s.tm && !fresh[String(s.g).toLowerCase()]) add(s.w); });
      Object.keys(cache).forEach(function(g) { (_sessionCache[g] || []).forEach(function(s) { add(s.location); }); });
      return Object.keys(n).map(function(k) { return n[k]; }).sort(function(a, b) { return b.c - a.c || a.v.localeCompare(b.v); }).map(function(x) { return x.v; });
    } });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go); else go();
})();

function submitPayment() {
  var v = _payValues(), err = document.getElementById('payErr'), btn = document.getElementById('payBtn');
  var fail = function(m) { err.textContent = m; err.style.display = 'block'; btn.disabled = false; btn.textContent = 'Save Payment'; };
  if (!v.person) { fail('Who was paid?'); document.getElementById('payPersonInput').focus(); return; }
  v.summary = v.summary.replace(/\s+/g, ' ');
  if (!v.summary) { fail('Which contract is it for? Pick or type its summary.'); document.getElementById('paySummary').focus(); return; }
  if (!(parseFloat(v.amount) > 0)) { fail('Enter the amount paid.'); document.getElementById('payAmount').focus(); return; }
  err.style.display = 'none'; btn.disabled = true; btn.textContent = 'Saving…';
  var fd = new FormData();
  fd.append('id', SHEET_ID); fd.append('game', _payCtx.game); fd.append('client', _payCtx.client);
  fd.append('payee', v.person); fd.append('summary', v.summary); fd.append('contract_ref', sumRef('pay')); fd.append('amount', v.amount); fd.append('date', v.date); fd.append('notes', v.notes);
  _payAppendMe(fd);
  fetch(APP_BASE + 'push/addPayment.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(j) {
      if (!j || !j.ok) { fail((j && j.error) || 'Save failed.'); return; }
      CONTRACT_RAW.push(j.row);
      forceClosePaymentDialog();
      var st = _saveOpenState(); renderPublishersView(); _restoreOpenState(st);
      if (typeof showSaveToast === 'function') showSaveToast(j.slip_error ? 'Payment saved — the slip didn’t work: ' + j.slip_error : '✓ Payment to ' + v.person + ' saved, with its payment slip', j.slip_error ? '' : 'success');
    })
    .catch(function() { fail('Network error.'); });
}

// Save/restore open state for publisher cards, game groups, and game cards across re-renders
function _saveOpenState() {
  var open = { pubs: [], games: [], cards: [], scroll: window.scrollY };
  document.querySelectorAll('.client-card.open').forEach(function(el) { open.pubs.push(el.id); });
  document.querySelectorAll('.game-group.open').forEach(function(el) { open.games.push(el.id); });
  document.querySelectorAll('.game-card.open').forEach(function(el) { if (el.dataset.game) open.cards.push(el.dataset.game); });
  return open;
}
function _restoreOpenState(state) {
  if (!state) return;
  state.pubs.forEach(function(id)  { var el = document.getElementById(id); if (el) el.classList.add('open'); });
  state.games.forEach(function(id) { var el = document.getElementById(id); if (el) el.classList.add('open'); });
  if (state.cards && state.cards.length) {
    document.querySelectorAll('.game-card').forEach(function(el) {
      if (state.cards.indexOf(el.dataset.game) !== -1) {
        el.classList.add('open');
        // Trigger lazy load / re-render just like toggleCard does
        var name = el.dataset.game;
        if (devCache[name] === undefined || _devIsStale(name)) loadDevData(name, _devIsStale(name));
        else if (devCache[name] !== null) renderBody(name, devCache[name]);
      }
    });
  }
  if (state.scroll) requestAnimationFrame(function() { window.scrollTo(0, state.scroll); });
}
function renderPublishersViewKeepOpen() {
  var state = _saveOpenState();
  renderPublishersView();
  _restoreOpenState(state);
}

function toggleContractItem(dataIdx) {
  var el = document.getElementById('ci-' + dataIdx);
  if (el) el.classList.toggle('open');
}

function generateInvoice(dataIdx) {
  var con = CONTRACT_RAW[dataIdx];
  if (!con) return;
  var btn = document.getElementById('inv-btn-' + dataIdx);
  if (btn) { btn.disabled = true; btn.textContent = 'Generating…'; }

  var fd = new FormData();
  fd.append('id',          SHEET_ID);
  fd.append('data_idx',    dataIdx);
  fd.append('game',        con.Game         || '');
  fd.append('client',      con.Client       || '');
  fd.append('quote',       con.Quote        || '');
  fd.append('payment',     con.Payment      || '');
  fd.append('status',      con.Status       || '');
  fd.append('target_start', con['Target Start Date'] || '');
  fd.append('target_end',   con['Target End Date']   || '');
  fd.append('start_date',   con['Start Date']        || '');
  fd.append('end_date',     con['End Date']           || '');
  fd.append('notes',        con.Notes        || '');
  fd.append('contract_id',  con.ID           || '');
  fd.append('my_name',     MY_NAME          || '');
  fd.append('my_phone',    MY_PHONE         || '');
  fd.append('my_company',  MY_COMPANY       || '');
  fd.append('my_logo',     MY_LOGO          || '');
  fd.append('my_address',  MY_COMPANY_ADDRESS || MY_BIO_LOCATION || '');

  fetch(APP_BASE + 'push/createInvoice.php', { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(j) {
      if (btn) { btn.disabled = false; btn.textContent = 'Invoice'; }
      if (!j.ok) {
        alert('Invoice error: ' + (j.error || 'Unknown error'));
        return;
      }
      // Open the spreadsheet in a new tab
      if (j.url) window.open(j.url, '_blank');
    })
    .catch(function() {
      if (btn) { btn.disabled = false; btn.textContent = 'Invoice'; }
      alert('Invoice generation failed. Check your connection.');
    });
}


// ── Estimate dialog ───────────────────────────────────────────────────────────

var _estimateInitial = {};

var _estimateScopeEdited = false;

function _buildScopeText(game, tests, edits) {
  var lines = [];
  if (game) lines.push(game + ' Development');
  lines.push('Includes (but not limited to):');
  if (tests > 0) {
    lines.push('- ' + tests + ' organized test' + (tests !== 1 ? 's' : ''));
    lines.push('- Test reports');
  }
  if (edits > 0) lines.push('- ' + edits + ' iteration' + (edits !== 1 ? 's' : '') + ' of rules editing');
  return lines.join('\n');
}

function _seedScopeOfWork() {
  if (_estimateScopeEdited) return;
  var game  = (document.getElementById('estimateGame').value  || '').trim();
  var tests = parseInt(document.getElementById('estimateTests').value, 10) || 0;
  var edits = parseInt(document.getElementById('estimateEdits').value, 10) || 0;
  document.getElementById('estimateScopeOfWork').value = _buildScopeText(game, tests, edits);
}

function _estimateIsDirty() {
  var game    = (document.getElementById('estimateGame').value      || '').trim();
  if ((document.getElementById('estimateSummary').value || '').trim()) return true;
  var price   = (document.getElementById('estimateUnitPrice').value || '').trim();
  var printing = (document.getElementById('estimatePrinting').value || '').trim();
  var clientWrap = document.getElementById('estimateClientFieldWrap');
  var clientVal  = (clientWrap && clientWrap.style.display !== 'none')
    ? (document.getElementById('estimateClientVisible').value || '').trim()
    : '';
  return game !== '' || price !== '' || printing !== '' || clientVal !== '';
}

function _estimateLog(msg, type) {
  var el = document.getElementById('estimateLog');
  if (!el) return;
  el.style.display = msg ? 'block' : 'none';
  el.textContent = msg;
  el.className = 'sync-log' + (type ? ' ' + type : '');
}

function openEstimateDialog(clientName, gameName) {
  var _args = arguments; if (!_dbRequirePro(function() { openEstimateDialog.apply(null, _args); })) return;
  // Close any open combos
  document.getElementById('estimateClientCombo').classList.remove('open');
  document.getElementById('estimateGameCombo').classList.remove('open');

  // Show/hide client field depending on whether client is pre-set
  var hasClient = !!(clientName || '').trim();
  var clientWrap = document.getElementById('estimateClientFieldWrap');
  clientWrap.style.display = hasClient ? 'none' : '';
  document.getElementById('estimateClientVisible').value = '';

  document.getElementById('estimateClientLabel').textContent = gameName || clientName || 'New Contract';
  document.getElementById('estimateClient').value            = clientName;
  document.getElementById('estimateGame').value              = gameName || '';
  document.getElementById('estimateType').value              = 'estimate';
  document.getElementById('estimateSummary').value           = '';
  document.getElementById('estimateTests').value             = '2';
  document.getElementById('estimateEdits').value             = '2';
  document.getElementById('estimateQty').value               = '1';
  document.getElementById('estimateUnitPrice').value         = '';
  document.getElementById('estimatePrinting').value          = '';
  document.getElementById('estimateDuration').value          = '';
  document.getElementById('estimateTargetStart').value       = '';
  _estimateScopeEdited = false;
  document.getElementById('estimateScopeOfWork').value       = _buildScopeText(gameName || '', 2, 2);
  document.getElementById('estimateTargetEnd').value         = '';
  document.getElementById('estimateDiscountLabel').value     = '';
  document.getElementById('estimateDiscountPct').value       = '0';
  document.getElementById('estimateNotes').value             = '50% due on signing';
  document.getElementById('estimateCreateBtn').disabled      = false;
  _estimateLog('', '');
  document.getElementById('estimateOverlay').classList.add('open');
  setTimeout(function() { document.getElementById('estimateType').focus(); }, 50);
}

function forceCloseEstimateDialog() {
  document.getElementById('estimateOverlay').classList.remove('open');
}

function _updateEstimateClient() {
  var val = document.getElementById('estimateClientVisible').value.trim();
  document.getElementById('estimateClient').value = val;
  document.getElementById('estimateClientLabel').textContent = val || 'New Contract';
}

// ── Estimate dialog custom combo helpers ──────────────────────────────────────
function _estClientRebuild(filter) {
  var drop  = document.getElementById('estimateClientDrop');
  var lower = (filter || '').toLowerCase();
  var knownClients = {};
  CONTRACT_RAW.forEach(function(c) { if (!c) return; var n = (c.Client || '').trim(); if (n) knownClients[n.toLowerCase()] = n; });
  var items = Object.keys(knownClients).sort()
    .map(function(k) { return knownClients[k]; })
    .filter(function(n) { return !lower || n.toLowerCase().indexOf(lower) !== -1; });
  if (!items.length) { drop.innerHTML = ''; document.getElementById('estimateClientCombo').classList.remove('open'); return; }
  drop.innerHTML = items.map(function(n) {
    return '<div class="combo-item" data-name="' + esc(n) + '" onmousedown="estimateClientPick(this.dataset.name)">' + esc(n) + '</div>';
  }).join('');
  document.getElementById('estimateClientCombo').classList.add('open');
}
function _estClientFilter() { _updateEstimateClient(); _estClientRebuild(document.getElementById('estimateClientVisible').value); }
function _estClientOpen()   { _estClientRebuild(document.getElementById('estimateClientVisible').value); }
function estimateClientPick(n) {
  document.getElementById('estimateClientVisible').value = n;
  document.getElementById('estimateClientDrop').innerHTML = '';
  document.getElementById('estimateClientCombo').classList.remove('open');
  _updateEstimateClient();
}
function _estClientKey(e) {
  if (e.key === 'Escape') { document.getElementById('estimateClientCombo').classList.remove('open'); }
  if (e.key === 'Enter') { var first = document.querySelector('#estimateClientDrop .combo-item'); if (first) estimateClientPick(first.dataset.name); }
}

function _estGameRebuild(filter) {
  var drop  = document.getElementById('estimateGameDrop');
  var lower = (filter || '').toLowerCase();
  var items = GAMES_RAW.map(function(g) { return (g.Name || '').trim(); }).filter(function(n) {
    return n && (!lower || n.toLowerCase().indexOf(lower) !== -1);
  });
  if (!items.length) { drop.innerHTML = ''; document.getElementById('estimateGameCombo').classList.remove('open'); return; }
  drop.innerHTML = items.map(function(n) {
    return '<div class="combo-item" data-name="' + esc(n) + '" onmousedown="estimateGamePick(this.dataset.name)">' + esc(n) + '</div>';
  }).join('');
  document.getElementById('estimateGameCombo').classList.add('open');
}
function _estGameFilter() { _estGameRebuild(document.getElementById('estimateGame').value); _seedScopeOfWork(); }
function _estGameOpen()   { _estGameRebuild(document.getElementById('estimateGame').value); }
function estimateGamePick(n) {
  document.getElementById('estimateGame').value = n;
  document.getElementById('estimateGameDrop').innerHTML = '';
  document.getElementById('estimateGameCombo').classList.remove('open');
  _seedScopeOfWork();
}
function _estGameKey(e) {
  if (e.key === 'Escape') { document.getElementById('estimateGameCombo').classList.remove('open'); }
  if (e.key === 'Enter') { var first = document.querySelector('#estimateGameDrop .combo-item'); if (first) estimateGamePick(first.dataset.name); }
}

function openNewContractDialog() {
  var _args = arguments; if (!_dbRequirePro(function() { openNewContractDialog.apply(null, _args); })) return;
  openEstimateDialog('', '');
}

var _contractsQuery = '';
function onContractsSearch() {
  var inp = document.getElementById('contractsSearchInput');
  _contractsQuery = (inp ? inp.value : '').toLowerCase().trim();
  var sw = document.getElementById('contractsSearchWrap');
  if (sw) sw.classList.toggle('has-text', !!_contractsQuery);
  renderPublishersView();
}
function clearContractsSearch() {
  var inp = document.getElementById('contractsSearchInput');
  if (inp) inp.value = '';
  _contractsQuery = '';
  var sw = document.getElementById('contractsSearchWrap');
  if (sw) sw.classList.remove('has-text');
  renderPublishersView();
}

function submitEstimate() {
  var game      = document.getElementById('estimateGame').value.trim();
  var unitPrice = document.getElementById('estimateUnitPrice').value.trim();
  var client    = document.getElementById('estimateClient').value.trim();
  var clientWrap = document.getElementById('estimateClientFieldWrap');
  var clientRequired = clientWrap && clientWrap.style.display !== 'none';
  if (clientRequired && !client) { _estimateLog('Please enter a publisher name.', 'error'); return; }
  if (!game)      { _estimateLog('Please enter a game name.', 'error'); return; }
  if (!unitPrice) { _estimateLog('Please enter a unit price.', 'error'); return; }
  var summary = document.getElementById('estimateSummary').value.replace(/\s+/g, ' ').trim();
  if (!summary) { _estimateLog('Please add a one-line summary.', 'error'); document.getElementById('estimateSummary').focus(); return; }

  document.getElementById('estimateCreateBtn').disabled = true;
  _estimateLog('Creating contract…', '');

  var fd = new FormData();
  fd.append('id',             SHEET_ID);
  fd.append('type',           document.getElementById('estimateType').value || 'estimate');
  fd.append('game',           game);
  fd.append('client',         document.getElementById('estimateClient').value);
  fd.append('num_tests',      document.getElementById('estimateTests').value);
  fd.append('num_edits',      document.getElementById('estimateEdits').value);
  fd.append('qty',            document.getElementById('estimateQty').value);
  fd.append('unit_price',     unitPrice);
  fd.append('printing',       document.getElementById('estimatePrinting').value.trim());
  fd.append('duration',       document.getElementById('estimateDuration').value.trim());
  fd.append('target_start',  document.getElementById('estimateTargetStart').value);
  fd.append('target_end',    document.getElementById('estimateTargetEnd').value);
  fd.append('discount_pct',   document.getElementById('estimateDiscountPct').value);
  fd.append('discount_label', document.getElementById('estimateDiscountLabel').value.trim());
  fd.append('notes',          document.getElementById('estimateNotes').value.trim());
  fd.append('scope_of_work',  document.getElementById('estimateScopeOfWork').value.trim());
  fd.append('summary',        summary);
  fd.append('my_name',        MY_NAME          || '');
  fd.append('my_phone',       MY_PHONE         || '');
  fd.append('my_company',     MY_COMPANY       || '');
  fd.append('my_logo',        MY_LOGO          || '');
  fd.append('my_address',     MY_COMPANY_ADDRESS || MY_BIO_LOCATION || '');
  fd.append('my_payment',     MY_PAYMENT_INFO  || '');

  fetch(APP_BASE + 'push/createEstimate.php', { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(j) {
      document.getElementById('estimateCreateBtn').disabled = false;
      if (!j.ok) {
        _estimateLog('✕  ' + (j.error || 'Unknown error'), 'error');
        return;
      }
      // Add to in-memory list and re-render
      if (j.estimate_record) ESTIMATES_RAW.push(j.estimate_record);
      if (j.contract_record) CONTRACT_RAW.push(j.contract_record);
      forceCloseEstimateDialog();
      renderPublishersViewKeepOpen();
      // Open in new tab
      if (j.url) window.open(j.url, '_blank');
    })
    .catch(function() {
      document.getElementById('estimateCreateBtn').disabled = false;
      _estimateLog('✕  Request failed. Check your connection.', 'error');
    });
}

// ── Invoice-from-Estimate dialog ──────────────────────────────────────────────

var _invoiceEstIdx  = -1;
var _invoiceDirty   = false;

function _invoiceMarkDirty() { _invoiceDirty = true; }

function _invoiceIsDirty() {
  var amt = (document.getElementById('invoiceAmount').value || '').trim();
  return _invoiceDirty || amt !== '';
}

function openInvoiceFromEstimate(estIdx) {
  var _args = arguments; if (!_dbRequirePro(function() { openInvoiceFromEstimate.apply(null, _args); })) return;
  var est = ESTIMATES_RAW[estIdx];
  if (!est) return;
  _invoiceEstIdx = estIdx;
  _invoiceDirty  = false;

  // Populate reference info
  document.getElementById('invoiceGameTitle').textContent  = est.game   || est.client || '';
  document.getElementById('invoiceRefClient').textContent  = est.client || '—';
  document.getElementById('invoiceRefNum').textContent     = est.estimate_num || '—';
  var estAmt = est.amount ? parseFloat(est.amount) : 0;
  document.getElementById('invoiceRefAmount').textContent  = estAmt ? '$' + estAmt.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) : '—';

  // Reset fields
  document.getElementById('invoiceAmtType').value  = 'fixed';
  document.getElementById('invoiceAmount').value   = estAmt ? estAmt.toFixed(2) : '';
  document.getElementById('invoicePct').value      = '100';
  document.getElementById('invoicePctCalc').textContent = estAmt ? '$' + estAmt.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}) : '';
  document.getElementById('invoicePctRow').style.display = 'none';
  document.getElementById('invoiceAmtLabel').querySelector('label') && (document.getElementById('invoiceAmtLabel').style.display = '');
  document.getElementById('invoiceDescription').value = est.game || '';
  document.getElementById('invoiceSummary').value  = _contractSummaryFor(est.contract_id || est.estimate_num);   // the estimate's own
  document.getElementById('invoiceDueDate').value  = _isoDaysFromToday(14);
  document.getElementById('invoiceNotes').value    = '';
  document.getElementById('invoiceCreateBtn').disabled = false;
  var log = document.getElementById('invoiceLog');
  log.style.display = 'none'; log.textContent = '';

  document.getElementById('invoiceOverlay').classList.add('open');
  setTimeout(function() { document.getElementById('invoiceAmount').focus(); }, 50);
}

function _invoiceAmtTypeChange() {
  var type   = document.getElementById('invoiceAmtType').value;
  var isPct  = type === 'pct';
  document.getElementById('invoicePctRow').style.display  = isPct ? 'flex' : 'none';
  document.getElementById('invoiceAmtLabel').style.display = isPct ? 'none' : '';
  if (isPct) _invoiceUpdatePct();
  _invoiceDirty = true;
}

function _invoiceUpdatePct() {
  var est    = ESTIMATES_RAW[_invoiceEstIdx];
  var estAmt = est && est.amount ? parseFloat(est.amount) : 0;
  var pct    = parseFloat(document.getElementById('invoicePct').value) || 0;
  var calc   = (estAmt * pct / 100);
  document.getElementById('invoicePctCalc').textContent = '$' + calc.toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
  _invoiceDirty = true;
}

function closeInvoiceDialog() {
  if (_invoiceIsDirty()) { shakeDialog(document.querySelector('#invoiceOverlay .estimate-dialog')); return; }
  forceCloseInvoiceDialog();
}

function forceCloseInvoiceDialog() {
  document.getElementById('invoiceOverlay').classList.remove('open');
}

function submitInvoice() {
  var est = ESTIMATES_RAW[_invoiceEstIdx];
  if (!est) return;

  var isPct    = document.getElementById('invoiceAmtType').value === 'pct';
  var amount   = isPct
    ? parseFloat((document.getElementById('invoicePctCalc').textContent || '').replace(/[^0-9.]/g, '')) || 0
    : parseFloat(document.getElementById('invoiceAmount').value)  || 0;
  var notes    = document.getElementById('invoiceNotes').value.trim();
  var invSummary = document.getElementById('invoiceSummary').value.replace(/\s+/g, ' ').trim();
  if (!invSummary) {
    var lg = document.getElementById('invoiceLog');
    lg.textContent = '✕  Please add a one-line summary.'; lg.className = 'sync-log error'; lg.style.display = '';
    document.getElementById('invoiceSummary').focus();
    return;
  }

  if (!amount) {
    var log = document.getElementById('invoiceLog');
    log.textContent = '✕  Please enter an invoice amount.';
    log.className = 'sync-log error'; log.style.display = '';
    return;
  }

  var btn = document.getElementById('invoiceCreateBtn');
  btn.disabled = true; btn.textContent = 'Creating…';
  var log = document.getElementById('invoiceLog');
  log.style.display = 'none';

  var fd = new FormData();
  fd.append('id',           SHEET_ID);
  fd.append('game',         est.game         || '');
  fd.append('client',       est.client       || '');
  fd.append('estimate_num', est.estimate_num  || '');
  fd.append('source_ref',   est.contract_id   || '');   // the estimate's Ref Number (a note on the invoice's Summary)
  fd.append('estimate_amt', est.amount        || '0');
  fd.append('invoice_amt',  amount);
  fd.append('description',  document.getElementById('invoiceDescription').value.trim());
  fd.append('summary',      invSummary);
  fd.append('tgt_start',    document.getElementById('invoiceDueDate').value || '');
  fd.append('notes',        notes);
  fd.append('my_name',      MY_NAME          || '');
  fd.append('my_phone',     MY_PHONE         || '');
  fd.append('my_company',   MY_COMPANY       || '');
  fd.append('my_logo',        MY_LOGO          || '');
  fd.append('my_address',     MY_COMPANY_ADDRESS || MY_BIO_LOCATION || '');
  fd.append('my_payment',     MY_PAYMENT_INFO  || '');

  fetch(APP_BASE + 'push/createInvoiceFromEstimate.php', { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(j) {
      btn.disabled = false; btn.textContent = 'Create';
      if (!j.ok) {
        log.textContent = '✕  ' + (j.error || 'Unknown error');
        log.className = 'sync-log error'; log.style.display = '';
        return;
      }
      // Add to ESTIMATES_RAW so it shows without reload
      if (j.invoice_record) {
        ESTIMATES_RAW.push(j.invoice_record);
        if (j.contract_record) CONTRACT_RAW.push(j.contract_record);
        renderPublishersViewKeepOpen();
      }
      // Open the new invoice sheet
      if (j.url) window.open(j.url, '_blank');
      forceCloseInvoiceDialog();
    })
    .catch(function() {
      btn.disabled = false; btn.textContent = 'Create';
      log.textContent = '✕  Request failed. Check your connection.';
      log.className = 'sync-log error'; log.style.display = '';
    });
}

// ── Expand/collapse estimate row ──────────────────────────────────────────────
function _estToggle(idx) {
  var el = document.getElementById('est-item-' + idx);
  if (el) el.classList.toggle('open');
}

// ── Delete estimate (inline confirmation — stays expanded) ────────────────────
function _estStartDelete(idx) {
  var btns = document.getElementById('est-act-btns-' + idx);
  var conf = document.getElementById('est-act-confirm-' + idx);
  if (btns) { btns.style.transition = 'opacity .15s'; btns.style.opacity = '0'; setTimeout(function(){ btns.style.display = 'none'; }, 150); }
  if (conf) { setTimeout(function(){ conf.style.display = 'flex'; conf.style.opacity = '0'; requestAnimationFrame(function(){ conf.style.transition = 'opacity .15s'; conf.style.opacity = '1'; }); }, 155); }
}
function _estCancelDelete(idx) {
  var btns = document.getElementById('est-act-btns-' + idx);
  var conf = document.getElementById('est-act-confirm-' + idx);
  if (conf) { conf.style.transition = 'opacity .15s'; conf.style.opacity = '0'; setTimeout(function(){ conf.style.display = 'none'; }, 150); }
  if (btns) { setTimeout(function(){ btns.style.display = 'flex'; btns.style.opacity = '0'; requestAnimationFrame(function(){ btns.style.transition = 'opacity .15s'; btns.style.opacity = '1'; }); }, 155); }
}
function _estConfirmDelete(idx) {
  var est = ESTIMATES_RAW[idx];
  if (!est) return;
  var btn = document.getElementById('est-confirm-btn-' + idx);
  if (btn) { btn.disabled = true; btn.textContent = 'Deleting…'; }
  var fd = new FormData();
  fd.append('id',          SHEET_ID);
  fd.append('tab',         est.tab      || '');
  fd.append('file_url',    est.url      || est.file || '');
  fd.append('contract_id', est.contract_id || '');
  fetch(APP_BASE + 'push/deleteEstimate.php', { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(j) {
      if (!j.ok) {
        if (btn) { btn.disabled = false; btn.textContent = 'Confirm'; }
        _estCancelDelete(idx);
        alert('Delete failed: ' + (j.error || 'Unknown error'));
        return;
      }
      // Fade out and remove the row without re-rendering the whole view
      var el = document.getElementById('est-item-' + idx);
      if (el) {
        el.style.transition = 'opacity .25s';
        el.style.opacity = '0';
        setTimeout(function() { if (el.parentNode) el.parentNode.removeChild(el); }, 260);
      }
      ESTIMATES_RAW.splice(idx, 1);
    })
    .catch(function() {
      if (btn) { btn.disabled = false; btn.textContent = 'Confirm'; }
      _estCancelDelete(idx);
      alert('Delete failed. Check your connection.');
    });
}

// ── Delete contract ───────────────────────────────────────────────────────────
function _conStartDelete(dataIdx) {
  var row  = document.getElementById('cdel-row-' + dataIdx);
  var conf = document.getElementById('cdel-confirm-' + dataIdx);
  if (row)  { row.style.transition = 'opacity .15s'; row.style.opacity = '0'; setTimeout(function(){ row.style.display = 'none'; }, 150); }
  if (conf) { setTimeout(function(){ conf.style.display = 'flex'; conf.style.opacity = '0'; requestAnimationFrame(function(){ conf.style.transition = 'opacity .15s'; conf.style.opacity = '1'; }); }, 150); }
}
function _conCancelDelete(dataIdx) {
  var row  = document.getElementById('cdel-row-' + dataIdx);
  var conf = document.getElementById('cdel-confirm-' + dataIdx);
  if (conf) { conf.style.transition = 'opacity .15s'; conf.style.opacity = '0'; setTimeout(function(){ conf.style.display = 'none'; }, 150); }
  if (row)  { setTimeout(function(){ row.style.display = ''; row.style.opacity = '0'; requestAnimationFrame(function(){ row.style.transition = 'opacity .15s'; row.style.opacity = '1'; }); }, 150); }
}
function _conConfirmDelete(dataIdx) {
  var con = CONTRACT_RAW[dataIdx];
  if (!con) return;
  var btn = document.getElementById('cdel-btn-' + dataIdx);
  if (btn) { btn.disabled = true; btn.textContent = 'Deleting…'; }

  var fd = new FormData();
  fd.append('id',          SHEET_ID);
  fd.append('contract_id', con.ID || '');

  fetch(APP_BASE + 'push/deleteContract.php', { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(j) {
      if (!j.ok) {
        if (btn) { btn.disabled = false; btn.textContent = 'Confirm'; }
        _conCancelDelete(dataIdx);
        alert('Delete failed: ' + (j.error || 'Unknown error'));
        return;
      }
      // Remove just the contract-item element — keep the game group expanded
      var el = document.getElementById('ci-' + dataIdx);
      if (el) {
        el.style.transition = 'opacity .2s';
        el.style.opacity = '0';
        setTimeout(function() { if (el.parentNode) el.parentNode.removeChild(el); }, 220);
      }
      CONTRACT_RAW[dataIdx] = null; // null out so indices stay stable
    })
    .catch(function() {
      if (btn) { btn.disabled = false; btn.textContent = 'Confirm'; }
      _conCancelDelete(dataIdx);
      alert('Delete failed. Check your connection.');
    });
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
  // Search cached dev notes (people, observations, solutions)
  var rows = devCache[g.Name];
  if (rows && rows.length) {
    for (var r = 0; r < rows.length; r++) {
      var row = rows[r];
      if ((row['People']       || '').toLowerCase().indexOf(q) !== -1) return true;
      if ((row['Observations'] || row['Observation'] || '').toLowerCase().indexOf(q) !== -1) return true;
      if ((row['Solutions']    || row['Thoughts']    || row['Solution']    || '').toLowerCase().indexOf(q) !== -1) return true;
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
  _dbTabCounts();
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

// After a Fetch, every game's dev tab is read fresh from the sheet the next time its card opens
function _devStaleKey() { return 'devboard_dev_stale:' + SHEET_ID; }
function _devStaleMark() {
  try { localStorage.setItem(_devStaleKey(), JSON.stringify({})); } catch (e) {}   // {} = no game refreshed yet
  Object.keys(devCache).forEach(function(k) { delete devCache[k]; });
}
function _devIsStale(name) {
  try { var m = JSON.parse(localStorage.getItem(_devStaleKey()) || 'null'); return !!m && !m[name.toLowerCase()]; } catch (e) { return false; }
}
function _devFresh(name) {
  try { var m = JSON.parse(localStorage.getItem(_devStaleKey()) || 'null'); if (!m) return; m[name.toLowerCase()] = 1; localStorage.setItem(_devStaleKey(), JSON.stringify(m)); } catch (e) {}
}

function toggleCard(card) {
  var wasOpen = card.classList.contains('open');
  card.classList.toggle('open');
  if (!wasOpen) {
    var name = card.dataset.game;
    if (devCache[name] === undefined || _devIsStale(name)) loadDevData(name, _devIsStale(name));
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
      if (force && Array.isArray(rows)) _devFresh(gameName);
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
      if (Array.isArray(rows)) _devFresh(gameName);
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

// ── TODO list ────────────────────────────────────────────────────────────────
// Observation rows flagged with the light bulb (a "TODO" note on the sheet cell).
// Checking an item removes the note; it stays here, struck through, until the next load.
var _todoItems = {};          // game → [{obs, sol, row, label}]
var _todoDone  = {};          // "game\u0001obs\u0001sol" → true (checked off in this page view)
var _todoOpen  = (function() { try { return JSON.parse(localStorage.getItem('devboard_todo_open') || '{}') || {}; } catch (e) { return {}; } })();
function _todoKey(game, obs, sol, id) { return game + '\u0001' + (id ? '#' + id : obs + '\u0001' + sol); }
var _todoDevText = {};        // TODO id → {obs, sol} as written in the dev tab (its text in the todos sheet may differ)

function _buildTodoHtml(gameName, sessions) {
  var items = [];
  sessions.forEach(function(s) {
    s.obs.forEach(function(o) {
      var mark = _todoDone[_todoKey(gameName, o.obs, o.sol, o.todoId)] || _todoDone[_todoKey(gameName, o.obs, o.sol)];
      var done = !!mark, tid = o.todoId || (typeof mark === 'string' ? mark : '');
      if (o.todo === 'TODO' || done) {
        if (tid) _todoDevText[tid] = { obs: o.obs, sol: o.sol };
        items.push({ obs: o.obs, sol: o.sol, todoId: tid, row: o.row || 0, done: done,
                     label: [s.testnum, s.date ? fmtDate(s.date) : ''].filter(Boolean).join(' · ') });
      }
    });
  });
  _todoItems[gameName] = items;
  if (!items.length) return '';
  var open  = _todoOpen[gameName] !== false;
  var left  = items.filter(function(it) { return !it.done; }).length;
  var gnJ   = esc(gameName).replace(/'/g, "\\'");
  var html  = '<div class="todo-section' + (open ? ' open' : '') + '" data-todo-game="' + esc(gameName) + '">';
  html += '<button type="button" class="todo-head" onclick="toggleTodoSection(\'' + gnJ + '\', this)">' +
            '<svg class="todo-head-bulb" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14Z"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>' +
            '<span class="todo-title">TODO</span><span class="todo-count">' + left + '</span>' +
            '<span class="todo-chevron">▼</span></button>';
  html += '<div class="todo-list">';
  items.forEach(function(it, i) {
    var tr = _todoFor(gameName, it.obs, it.sol, it.todoId);
    var shownObs = tr ? (tr.Observation || '') : it.obs, shownSol = tr ? (tr.Thoughts || '') : it.sol;
    var obsText = String(shownObs || '').replace(/=IMAGE\("[^"]*"\)/gi, '🖼 Image').trim();
    html += '<div class="todo-item' + (it.done ? ' done' : '') + '">' +
              '<input type="checkbox"' + (it.done ? ' checked' : '') + ' onchange="onTodoCheck(\'' + gnJ + '\',' + i + ',this)">' +
              '<span class="todo-text" onclick="openTodoDialog({ game: \'' + gnJ + '\', idx: ' + i + ' })">' +
                _todoObsHtml(shownObs, 'todo-obs') +
                (shownSol ? '<span class="todo-sol">' + esc(shownSol) + '</span>' : '') +
                (it.label ? '<span class="todo-meta">' + esc(it.label) + '</span>' : '') +
              '</span>' +
              _todoIconsHtml(tr, it.done) +
            '</div>';
  });
  html += '</div></div>';
  return html;
}

// Full notes of a TODO: { game, idx } from a game card, or { id } from the Board
// Full notes of a TODO: { game, idx } from a game card, or { id } from the Board.
// A TODO in the todos sheet can be edited here — only the todos sheet changes, not the session notes.
var _todoDlgId = null;
function openTodoDialog(ref) {
  var obs = '', sol = '', meta = '', tags = [];
  _todoDlgId = null;
  if (ref.id) {
    var t = _todoById(ref.id); if (!t) return;
    _todoDlgId = t.ID;
    obs = t.Observation || ''; sol = t.Thoughts || '';
    meta = [t.Game, t.Session].filter(Boolean).join(' · ');
    if (t.Priority) tags.push('<span class="todo-tag pri-' + esc(t.Priority.toLowerCase()) + '">' + esc({ High: '!!! High', Medium: '!! Medium', Low: '! Low' }[t.Priority] || t.Priority) + '</span>');
    if (t.Assigned) tags.push('<span class="todo-tag">👤 ' + esc(t.Assigned) + '</span>');
    if (t.Due)      tags.push('<span class="todo-tag">📅 Due ' + esc(fmtDate(t.Due)) + '</span>');
    if ((t.Status || '').toLowerCase() === 'done') tags.push('<span class="todo-tag done">✓ Done</span>');
  } else {
    var it = (_todoItems[ref.game] || [])[ref.idx]; if (!it) return;
    var tr = _todoFor(ref.game, it.obs, it.sol, it.todoId);
    if (tr) return openTodoDialog({ id: tr.ID });
    obs = it.obs || ''; sol = it.sol || '';
    meta = [ref.game, it.label].filter(Boolean).join(' · ');
    if (it.done) tags.push('<span class="todo-tag done">✓ Done</span>');
  }
  var editable = !!_todoDlgId;
  var oEl = document.getElementById('todoDlgObsIn'), sEl = document.getElementById('todoDlgSolIn');
  oEl.value = obs; sEl.value = sol;
  // A picture cell: show the picture (the cell itself is a click away)
  var pic = _todoImgUrl(obs), pw = document.getElementById('todoDlgPic');
  pw.hidden = !pic; oEl.hidden = !!pic;
  if (pic) { document.getElementById('todoDlgPicImg').src = pic; document.getElementById('todoDlgPicA').href = pic; pw.querySelector('.todo-dlg-piclink').hidden = false; }
  oEl.readOnly = sEl.readOnly = !editable;
  document.getElementById('todoDlgSave').style.display = editable ? '' : 'none';
  document.getElementById('todoDlgNote').style.display = editable ? 'none' : '';
  document.getElementById('todoDlgErr').textContent = '';
  document.getElementById('todoDlgMeta').textContent = meta;
  document.getElementById('todoDlgTags').innerHTML = tags.join('');
  document.getElementById('todoOverlay').classList.add('open');
  setTimeout(function() { sEl.style.height = 'auto'; sEl.style.height = Math.min(sEl.scrollHeight + 4, window.innerHeight * .5) + 'px'; }, 0);
}

function saveTodoDialog() {
  var t = _todoDlgId && _todoById(_todoDlgId); if (!t) return closeTodoDialog();
  var obs = document.getElementById('todoDlgObsIn').value.trim();
  var sol = document.getElementById('todoDlgSolIn').value.trim();
  if (obs === (t.Observation || '') && sol === (t.Thoughts || '')) return closeTodoDialog();
  var btn = document.getElementById('todoDlgSave');
  btn.disabled = true; btn.textContent = 'Saving…';
  _todosPost('update', { id: t.ID, fields: { Observation: obs, Thoughts: sol } })
    .then(function() {
      t.Observation = obs; t.Thoughts = sol;
      closeTodoDialog();
      _renderBoardTodos();
      _refreshTodoSection(t.Game);
    })
    .catch(function(e) { document.getElementById('todoDlgErr').textContent = 'Couldn\'t save — ' + _friendlySaveError(e); })
    .then(function() { btn.disabled = false; btn.textContent = 'Save'; });
}
function closeTodoDialog() { document.getElementById('todoOverlay').classList.remove('open'); }

// Redraw only a game card's TODO section (keeps open sessions open)
function _refreshTodoSection(gameName) {
  var body = document.getElementById('body-' + safeName(gameName));
  var sessions = _sessionCache[gameName];
  if (!body || !sessions) return;
  var html = _buildTodoHtml(gameName, sessions);
  var old = body.querySelector('.todo-section');
  if (old) { if (html) old.outerHTML = html; else old.remove(); }
  else if (html) { var sub = body.querySelector('.card-subtitle'); if (sub) sub.insertAdjacentHTML('afterend', html); }
}

function toggleTodoSection(gameName, btn) {
  var sec = btn.closest('.todo-section');
  var open = !sec.classList.contains('open');
  sec.classList.toggle('open', open);
  _todoOpen[gameName] = open;
  try { localStorage.setItem('devboard_todo_open', JSON.stringify(_todoOpen)); } catch (e) {}
}

// Update the note on a TODO's row in the page's copy of the dev tab (by its ID, else by its dev text)
function _todoSetCacheNote(gameName, it, note) {
  var id = it.todoId || '', dev = (id && _todoDevText[id]) || it;
  var idRe = id ? new RegExp('^\\s*TODO\\s*:\\s*' + id + '\\s*$', 'i') : null;
  function isIt(r) {
    var n = r['_notes'] || {};
    if (idRe && Object.keys(n).some(function(k) { return idRe.test(String(n[k])); })) return true;
    var o = (r['Observations'] || r['Observation'] || '').trim();
    var so = (r['Solutions'] || r['Thoughts'] || r['Solution'] || '').trim();
    return o === dev.obs && so === dev.sol;
  }
  (devCache[gameName] || []).forEach(function(r) {
    if (!isIt(r)) return;
    if (note) r['_notes'] = { 'Solutions': note }; else delete r['_notes'];
  });
  // Sessions shown on the page (the edit dialog reads these): keep the light bulb in step
  (_sessionCache[gameName] || []).forEach(function(s) {
    s.obs.forEach(function(o) {
      if ((id && o.todoId === id) || (o.obs === dev.obs && o.sol === dev.sol)) { o.todo = note ? 'TODO' : ''; if (id) o.todoId = id; }
    });
  });
}

function onTodoCheck(gameName, i, cb) {
  var it = (_todoItems[gameName] || [])[i];
  if (!it) return;
  var done = cb.checked, label = cb.closest('.todo-item'), sec = cb.closest('.todo-section');
  var tr = _todoFor(gameName, it.obs, it.sol, it.todoId);
  if (!it.todoId && tr) it.todoId = tr.ID;
  function apply(d) {
    it.done = d; cb.checked = d;
    label.classList.toggle('done', d);
    var k = _todoKey(gameName, it.obs, it.sol, it.todoId), kt = _todoKey(gameName, it.obs, it.sol);
    if (d) { _todoDone[k] = true; _todoDone[kt] = it.todoId || true; } else { delete _todoDone[k]; delete _todoDone[kt]; }
    _todoSetCacheNote(gameName, it, d ? '' : 'TODO' + (it.todoId ? ':' + it.todoId : ''));
    var left = (_todoItems[gameName] || []).filter(function(x) { return !x.done; }).length;
    var c = sec && sec.querySelector('.todo-count'); if (c) c.textContent = left;
  }
  apply(done);
  cb.disabled = true;
  var fd = new FormData();
  fd.append('id', SHEET_ID); fd.append('game', gameName); fd.append('todo_id', it.todoId || '');
  fd.append('row', it.row || 0); fd.append('obs', it.obs); fd.append('sol', it.sol);
  fd.append('note', done ? '' : 'TODO' + (it.todoId ? ':' + it.todoId : ''));   // done → remove the TODO note from the cell
  if (tr) { _todoSetChecked(tr, done); if (_activeTab === 'dash') _renderBoardTodos(); }
  fetch(APP_BASE + 'push/setDevNote.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(res) { if (res.error) throw new Error(res.error); if (res.row) it.row = res.row; })
    .catch(function(e) { apply(!done); showSaveToast('Couldn\'t update the TODO — ' + _friendlySaveError(e)); })
    .then(function() { cb.disabled = false; });
}

function renderBody(gameName, rows) {
  var body = document.getElementById('body-' + safeName(gameName));
  if (!body) return;

  var gameRec = GAMES_INDEX[gameName.toLowerCase()] || {};

  var allSessions = buildSessions(rows).reverse().filter(function(s) {  // newest first; exclude T&M rows
    var et = (s.eventType || '').toLowerCase();
    return et !== 'time' && et !== 'material';
  });
  _sessionCache[gameName] = allSessions;
  var nPlay   = allSessions.filter(function(s){ return s.testnum.toLowerCase().indexOf('playtest ')  === 0; }).length;
  var nMeet   = allSessions.filter(function(s){ return s.testnum.toLowerCase().indexOf('meeting ')   === 0; }).length;
  var nIdea   = allSessions.filter(function(s){ return s.testnum.toLowerCase().indexOf('idea ')      === 0; }).length;
  var nEdit   = allSessions.filter(function(s){ return s.testnum.toLowerCase().indexOf('editing ')   === 0; }).length;
  var nReport = allSessions.filter(function(s){ return s.testnum.toLowerCase().indexOf('reporting ') === 0; }).length;

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
  if (nPlay)   html += '<div class="' + chipClass('Playtest',  'stat-playtest')  + '" onclick="filterSessions(\'' + gnJ + '\',\'Playtest\')">'  + nPlay   + ' <span>' + (nPlay   === 1 ? 'Playtest'  : 'Playtests')  + '</span></div>';
  if (nMeet)   html += '<div class="' + chipClass('Meeting',   'stat-meeting')   + '" onclick="filterSessions(\'' + gnJ + '\',\'Meeting\')">'   + nMeet   + ' <span>' + (nMeet   === 1 ? 'Meeting'   : 'Meetings')   + '</span></div>';
  if (nIdea)   html += '<div class="' + chipClass('Idea',      'stat-idea')      + '" onclick="filterSessions(\'' + gnJ + '\',\'Idea\')">'      + nIdea   + ' <span>' + (nIdea   === 1 ? 'Idea'      : 'Ideas')      + '</span></div>';
  if (nEdit)   html += '<div class="' + chipClass('Editing',   'stat-editing')   + '" onclick="filterSessions(\'' + gnJ + '\',\'Editing\')">'   + nEdit   + ' <span>' + (nEdit   === 1 ? 'Edit'      : 'Edits')      + '</span></div>';
  if (nReport) html += '<div class="' + chipClass('Reporting', 'stat-reporting') + '" onclick="filterSessions(\'' + gnJ + '\',\'Reporting\')">' + nReport + ' <span>' + (nReport === 1 ? 'Report'    : 'Reports')    + '</span></div>';
  if (!nPlay && !nMeet && !nIdea && !nEdit && !nReport) html += '<div class="card-stat stat-playtest">0 <span>Sessions</span></div>';
  html += '<div class="subtitle-right">';
  html += '<button class="subtitle-btn" onclick="shareGame(\'' + gnJ + '\')">' +
    '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>' +
    'Share</button>';
  html += '<button class="subtitle-btn" onclick="openEditGame(\'' + gnJ + '\')">Edit</button>';
  html += '<button class="subtitle-btn subtitle-btn-primary" onclick="openSessionDialog(\'' + gnJ + '\')">+ Session</button>';
  html += '<button class="subtitle-btn" onclick="openDashTMDialog(\'' + gnJ + '\')">+ T&amp;M</button>';
  html += '<button class="subtitle-btn subtitle-btn-reload" onclick="reloadDevData(\'' + gnJ + '\')" title="Reload from sheet">' +
    '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>' +
    '</button>';
  html += '</div>';
  html += '</div>';

  // TODO list (rows flagged with the light bulb)
  html += _buildTodoHtml(gameName, allSessions);

  // T&M summary row (per-person totals)
  html += _buildTMSummaryHtml(rows);

  // Sessions list
  if (!sessions.length) {
    html += '<div class="dev-empty">' + (_searchQuery ? 'No sessions match your search.' : activeFilter ? 'No ' + activeFilter + ' sessions.' : 'No playtest sessions yet. Click "+ Session" to log one.') + '</div>';
  } else {
    sessions.forEach(function(s, i) {
      // Determine type class from testnum prefix
      var typeClass = 'type-playtest';
      if      (s.testnum.toLowerCase().indexOf('meeting')   === 0) typeClass = 'type-meeting';
      else if (s.testnum.toLowerCase().indexOf('idea')      === 0) typeClass = 'type-idea';
      else if (s.testnum.toLowerCase().indexOf('editing')   === 0) typeClass = 'type-editing';
      else if (s.testnum.toLowerCase().indexOf('reporting') === 0) typeClass = 'type-reporting';

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
      html +=   '<span class="session-chevron">▼</span>';
      html +=   '</div>';
      // Testers as comma-separated line in the header
      if (s.testers.length || s.submittedBy) {
        html += '<div class="session-testers-line">';
        if (s.testers.length) html += s.testers.map(esc).join(', ');
        if (s.submittedBy) { var _sbLabel = _peopleByEmail[s.submittedBy.toLowerCase()] || s.submittedBy; html += (s.testers.length ? ' ' : '') + '(Submitted by ' + esc(_sbLabel) + ')'; }
        html += '</div>';
      }
      if (s.obs.length) html += sessCopyBtn('d-' + blockId, gameName, s);
      html += '</div>';
      // Collapsible body — click anywhere to open edit dialog
      html += '<div class="session-body-wrap"><div class="session-body" onclick="openEditSessionDialog(' + gnQ + ',' + allIdx + ')" style="cursor:pointer">';
      var visibleObs = _filterObsByQuery(s.obs, _searchQuery);
      if (visibleObs.length) {
        html += '<table class="obs-table"><tbody>';
        visibleObs.forEach(function(o) {
          html += '<tr><td class="td-obs">' + obsHtml(o.obs) + '</td><td class="td-sol">' + bulletsHtml(o.sol) + '</td></tr>';
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

var _sharePopupOpen      = false;
var _shareCurrentGame    = '';
var _generatedGameLinks  = {};
var _generatedReportLinks    = {};
var _reportDataFingerprint  = {};   // gameName → row count at last generation

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

      // ── Section 3: PDF report ─────────────────────────────────────────────
      '<hr style="border:none;border-top:1px solid #edf2f6;margin:.1rem 0" />' +
      '<p style="' + SB + 'font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:#888;margin:0">Report</p>' +
      '<p style="' + S + 'font-size:.8rem;color:#555;margin:0">Session notes and T&amp;M summary — open the link and print to save as PDF.</p>' +
      '<div id="shareReportGenSection">' +
        '<button id="shareReportBtn" onclick="generateDevReport(_shareCurrentGame)" ' +
          'style="' + SB + 'font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;width:100%;padding:.5rem 1rem;background:#f0f4f8;color:#1a5f7a;border:1.5px solid #c8d8e4;border-radius:6px;cursor:pointer">Generate Report</button>' +
      '</div>' +
      '<div id="shareReportUrlSection" style="display:none">' +
        '<div style="display:flex;gap:.5rem;align-items:center">' +
          '<input id="shareReportInput" readonly style="flex:1;' + S + 'font-size:.75rem;padding:.42rem .6rem;border:1.5px solid #d0d8e0;border-radius:6px;outline:none;color:#111;background:#f7fafb" />' +
          '<button id="shareReportCopyBtn" onclick="shareReportAction()" style="' + SB + 'font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;background:#1a5f7a;color:#fff;border:none;border-radius:6px;padding:.42rem .85rem;cursor:pointer;white-space:nowrap">Copy</button>' +
          '<button onclick="generateDevReport(_shareCurrentGame)" title="Regenerate report" ' +
            'style="flex-shrink:0;background:none;border:1.5px solid #c8d8e4;border-radius:6px;padding:.3rem .45rem;cursor:pointer;color:#1a5f7a;display:flex;align-items:center;justify-content:center">' +
            '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>' +
          '</button>' +
        '</div>' +
      '</div>' +
      '<p id="shareReportCopiedMsg" style="' + S + 'font-size:.78rem;color:#2e7a52;margin:0;display:none">Link copied!</p>' +
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

  // Restore existing report URL if already generated this session
  if (_generatedReportLinks[gameName]) {
    _showDevReportUrl(_generatedReportLinks[gameName]);
    _updateReportBtn(gameName);
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

function _showDevReportUrl(url) {
  var gen = document.getElementById('shareReportGenSection');
  var sec = document.getElementById('shareReportUrlSection');
  var inp = document.getElementById('shareReportInput');
  if (gen) gen.style.display = 'none';
  if (sec) sec.style.display = '';
  if (inp) inp.value = url;
  _updateReportBtn(_shareCurrentGame);
}

function _isReportStale(gameName) {
  var fp = _reportDataFingerprint[gameName];
  if (fp === undefined) return false;
  var rows = devCache[gameName];
  if (!rows) return false;
  return rows.length !== fp;
}

function _updateReportBtn(gameName) {
  var btn = document.getElementById('shareReportCopyBtn');
  if (!btn) return;
  var stale = _isReportStale(gameName);
  btn.dataset.stale = stale ? '1' : '0';
  btn.textContent   = stale ? 'UPDATE' : 'COPY';
  btn.style.background = stale ? '#a16207' : '#1a5f7a';
}

function shareReportAction() {
  var btn = document.getElementById('shareReportCopyBtn');
  if (btn && btn.dataset.stale === '1') {
    generateDevReport(_shareCurrentGame);
  } else {
    copyDevReportUrl();
  }
}

function copyDevReportUrl() {
  var inp = document.getElementById('shareReportInput');
  var btn = document.getElementById('shareReportCopyBtn');
  var msg = document.getElementById('shareReportCopiedMsg');
  if (!inp || !inp.value) return;
  inp.select(); inp.setSelectionRange(0, 9999);
  navigator.clipboard ? navigator.clipboard.writeText(inp.value).catch(function(){}) : (function(){ try { document.execCommand('copy'); } catch(e){} })();
  if (btn) { btn.textContent = 'Copied!'; setTimeout(function(){ btn.textContent = 'Copy'; }, 2000); }
  if (msg) { msg.style.display = 'block'; setTimeout(function(){ msg.style.display = 'none'; }, 2000); }
}

function closeSharePopup() {
  _sharePopupOpen = false;
  var el = document.getElementById('sharePopupOverlay');
  if (el) el.parentNode.removeChild(el);
}

// ── Generate & save a PDF report for a game ──────────────────────────────────

function _reportGameImageUrl(rec) {
  // Extract a direct image URL from games.json, handling =IMAGE("url") Sheets formulas
  var raw = rec['Image'] || rec['Cover URL'] || rec['Image URL'] || rec['Sellsheet'] || '';
  if (!raw) return '';
  var fm = String(raw).match(/^=IMAGE\s*\(\s*["']([^"']+)/i);
  if (fm) raw = fm[1];
  var md = String(raw).match(/^\[.*?\]\((.+?)\)\s*$/);
  if (md) raw = md[1];
  // Convert Dropbox share links to direct URLs
  raw = raw.replace(/[?&]dl=[01]/, function(m) { return m.replace('dl=', 'raw=').slice(0, -1) + '1'; });
  return raw.trim();
}

function generateDevReport(gameName) {
  var rows = devCache[gameName];
  if (!rows) {
    // Data not loaded yet — load it, then re-run
    var btn = document.getElementById('shareReportBtn');
    if (btn) { btn.disabled = true; btn.textContent = 'Loading…'; }
    var fd = new FormData();
    fd.append('id', SHEET_ID);
    fd.append('game', gameName);
    fetch(APP_BASE + 'push/getDevJson.php', { method:'POST', body:fd })
      .then(function(r) { return r.json(); })
      .then(function(result) {
        devCache[gameName] = Array.isArray(result) ? result : [];
        generateDevReport(gameName);
      })
      .catch(function() {
        var b = document.getElementById('shareReportBtn');
        if (b) { b.disabled = false; b.textContent = 'Generate Report'; }
      });
    return;
  }

  var btn = document.getElementById('shareReportBtn');
  if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

  var gameRec = GAMES_INDEX[gameName.toLowerCase()] || {};
  var tagline = gameRec.Tagline || '';
  var imgUrl  = _reportGameImageUrl(gameRec);
  var today   = new Date().toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });

  // ── Sessions (newest first, excluding T&M) ───────────────────────────────
  var allSessions = buildSessions(rows).reverse().filter(function(s) {
    var et = (s.eventType || '').toLowerCase();
    return et !== 'time' && et !== 'material';
  });

  // ── T&M totals per person ────────────────────────────────────────────────
  var byPerson = {};
  rows.forEach(function(row) {
    var et = (row['Event'] || '').trim().toLowerCase();
    if (et !== 'time' && et !== 'material') return;
    var person = _resolveTMPerson((row['People'] || '').trim() || '(unknown)');
    if (!byPerson[person]) byPerson[person] = { mins:0, cost:0 };
    if (et === 'time') {
      byPerson[person].mins += _parseTMMinutes(row['Observations'] || row['Observation'] || '');
    } else {
      var c = parseFloat((row['Observations'] || row['Observation'] || '').replace(/[^0-9.]/g, ''));
      if (!isNaN(c)) byPerson[person].cost += c;
    }
  });

  // ── Build HTML ───────────────────────────────────────────────────────────
  function escR(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
  // Render =IMAGE("url") formulas as actual <img> tags; otherwise HTML-escape the text
  function cellHtml(s) {
    var m = String(s || '').match(/^=IMAGE\s*\(\s*["']([^"']+)/i);
    if (m) return '<img src="' + escR(m[1]) + '" style="max-width:100%;max-height:220px;border-radius:4px;display:block" onerror="this.style.display=\'none\'">';
    return escR(s);
  }

  var h = '<!DOCTYPE html><html><head><meta charset="utf-8">';
  h += '<title>' + escR(gameName) + ' — DevBoard Report</title>';
  h += '<style>';
  h += '*{box-sizing:border-box}';
  h += 'body{font-family:Georgia,serif;font-size:11pt;color:#111;margin:0;padding:0}';
  h += '.page{max-width:740px;margin:2rem auto;padding:0 1.75rem}';
  h += '.report-header{display:flex;align-items:flex-start;gap:1.5rem;margin-bottom:1.5rem;padding-bottom:.9rem;border-bottom:2px solid #1a5f7a}';
  h += '.report-header-text{flex:1}';
  h += '.report-img{width:120px;height:120px;object-fit:cover;border-radius:8px;flex-shrink:0}';
  h += 'h1{font-size:20pt;margin:0 0 .2rem;font-family:Georgia,serif}';
  h += '.subtitle{font-size:10.5pt;color:#555;margin:0 0 .5rem;font-style:italic}';
  h += '.meta{font-size:9pt;color:#888;margin:0}';
  h += 'h2{font-size:10.5pt;text-transform:uppercase;letter-spacing:.07em;color:#1a5f7a;margin:1.6rem 0 .5rem;border-bottom:1px solid #cde0ea;padding-bottom:.25rem}';
  h += 'table{width:100%;border-collapse:collapse;font-size:10pt;margin-bottom:.5rem;table-layout:fixed}';
  h += 'th{text-align:left;font-size:8pt;text-transform:uppercase;letter-spacing:.05em;color:#999;border-bottom:2px solid #ddd;padding:.25rem .5rem}';
  h += 'td{padding:.3rem .5rem;border-bottom:1px solid #eee;vertical-align:top;word-wrap:break-word}';
  h += 'tr:last-child td{border-bottom:none}';
  h += '.session-block{margin:1.1rem 0 .15rem;page-break-inside:avoid}';
  h += '.session-label{font-weight:bold;font-size:10.5pt}';
  h += '.session-meta{color:#777;font-size:9pt;margin-left:.4rem}';
  h += '.session-testers{font-size:9pt;color:#666;margin:.15rem 0 .35rem}';
  h += '.col-half{width:50%}';
  h += '.col-obs{color:#1a5f7a;font-size:8pt}';
  h += '.col-sol{color:#888;font-size:8pt}';
  h += '.td-sol{color:#444}';
  h += '.tm-total td{font-weight:bold;border-top:2px solid #ddd;border-bottom:none}';
  h += '.no-data{color:#aaa;font-style:italic;font-size:10pt;margin:.3rem 0}';
  h += 'p{margin:.2rem 0 .5rem}';
  h += '@media print{body{margin:0}.page{margin:.4in .5in;max-width:none}h2{page-break-after:avoid}.session-block{page-break-inside:avoid}}';
  h += '</style></head><body><div class="page">';

  // ── Header ────────────────────────────────────────────────────────────────
  h += '<div class="report-header">';
  h += '<div class="report-header-text">';
  h += '<h1>' + escR(gameName) + '</h1>';
  if (tagline) h += '<div class="subtitle">' + escR(tagline) + '</div>';
  h += '<div class="meta">DevBoard Report &nbsp;&middot;&nbsp; ' + escR(today) + '</div>';
  h += '</div>';
  if (imgUrl) h += '<img class="report-img" src="' + escR(imgUrl) + '" alt="" onerror="this.style.display=\'none\'">';
  h += '</div>';

  // ── Sessions ─────────────────────────────────────────────────────────────
  h += '<h2>Sessions (' + allSessions.length + ')</h2>';
  if (!allSessions.length) {
    h += '<p class="no-data">No sessions logged yet.</p>';
  } else {
    allSessions.forEach(function(s) {
      h += '<div class="session-block">';
      h += '<div><span class="session-label">' + escR(s.testnum) + '</span>';
      if (s.date)     h += '<span class="session-meta">' + escR(fmtDate(s.date)) + '</span>';
      if (s.location) h += '<span class="session-meta">&middot; ' + escR(s.location) + '</span>';
      if (s.length)   h += '<span class="session-meta">&middot; ' + escR(s.length)   + '</span>';
      h += '</div>';
      if (s.testers.length) h += '<div class="session-testers">' + s.testers.map(escR).join(', ') + '</div>';
      if (s.obs.length) {
        h += '<table><colgroup><col class="col-half"><col class="col-half"></colgroup>';
        h += '<thead><tr><th class="col-obs">Observation</th><th class="col-sol">Note / Solution</th></tr></thead><tbody>';
        s.obs.forEach(function(o) {
          h += '<tr><td>' + cellHtml(o.obs) + '</td><td class="td-sol">' + cellHtml(o.sol) + '</td></tr>';
        });
        h += '</tbody></table>';
      }
      h += '</div>';
    });
  }

  // ── T&M Summary ──────────────────────────────────────────────────────────
  var tmPeople = Object.keys(byPerson);
  h += '<h2>Time &amp; Materials</h2>';
  if (!tmPeople.length) {
    h += '<p class="no-data">No time or materials logged.</p>';
  } else {
    h += '<table><thead><tr><th>Person</th><th>Time</th><th>Materials</th></tr></thead><tbody>';
    var totalMins = 0, totalCost = 0;
    tmPeople.forEach(function(p) {
      var d = byPerson[p];
      totalMins += d.mins; totalCost += d.cost;
      h += '<tr><td>' + escR(p) + '</td>';
      h += '<td>' + (d.mins ? escR(_fmtTMTime(d.mins)) : '—') + '</td>';
      h += '<td>' + (d.cost ? '$' + d.cost.toFixed(2) : '—') + '</td></tr>';
    });
    if (tmPeople.length > 1) {
      h += '<tr class="tm-total"><td>Total</td>';
      h += '<td>' + (totalMins ? escR(_fmtTMTime(totalMins)) : '—') + '</td>';
      h += '<td>' + (totalCost ? '$' + totalCost.toFixed(2) : '—') + '</td></tr>';
    }
    h += '</tbody></table>';
  }

  h += '</div></body></html>';

  // ── POST to server ────────────────────────────────────────────────────────
  var fd2 = new FormData();
  fd2.append('id',   SHEET_ID);
  fd2.append('game', gameName);
  fd2.append('html', h);
  fetch(APP_BASE + 'push/saveDevReport.php', { method:'POST', body:fd2 })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (res.ok && res.url) {
        _generatedReportLinks[gameName]   = res.url;
        _reportDataFingerprint[gameName]  = (devCache[gameName] || []).length;
        _showDevReportUrl(res.url);
        window.open(res.url, '_blank');
      } else {
        if (btn) { btn.disabled = false; btn.textContent = 'Generate Report'; }
        alert('Could not save report: ' + (res.error || 'unknown error'));
      }
    })
    .catch(function(e) {
      if (btn) { btn.disabled = false; btn.textContent = 'Generate Report'; }
      alert('Could not save report.');
    });
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

  var sheets = ['games', 'people', 'bios', 'contracts', 'settings'];

  var pushBase    = APP_BASE + 'push/pushSheetUpdate.php';
  var idx = 0;

  function finish() {
    syncLog('Done.', 'ok');
    document.getElementById('syncDialogTitle').textContent = 'Fetch Complete';
    document.getElementById('syncDialogSub').style.display = 'none';
    document.getElementById('syncDoneBtn').disabled = false;
  }

  // The dev tabs aren't fetched here: each game's is read fresh from the sheet when its card is next opened
  function pushNext() {
    if (idx >= sheets.length) { _devStaleMark(); finish(); return; }
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

function openContractDialog(name, clientName) {
  var _args = arguments; if (!_dbRequirePro(function() { openContractDialog.apply(null, _args); })) return;
  _contractGame = name;
  document.getElementById('contractGameTitle').textContent = name;
  document.getElementById('contractClient').value      = clientName || '';
  document.getElementById('contractTargetStart').value = '';
  document.getElementById('contractTargetEnd').value   = '';
  document.getElementById('contractQuote').value       = '';
  document.getElementById('contractPayment').value     = 'Estimate';
  document.getElementById('contractNotes').value       = '';
  document.getElementById('contractSummary').value     = '';
  document.getElementById('contractErr').textContent   = '';
  document.getElementById('contractErr').style.display = 'none';
  document.getElementById('contractBtn').disabled      = false;
  document.getElementById('contractBtn').textContent   = 'Add Contract';
  // populate client combo
  contractClientRebuild(clientName || '');
  document.getElementById('contractOverlay').classList.add('open');
}

function hasContractData() {
  return !!(document.getElementById('contractClient').value.trim() || document.getElementById('contractSummary').value.trim());
}
function closeContractDialog() {
  if (hasContractData()) { shakeDialog(document.getElementById('contractOverlay').querySelector('.contract-dialog')); return; }
  document.getElementById('contractOverlay').classList.remove('open');
}
function forceCloseContractDialog() {
  document.getElementById('contractOverlay').classList.remove('open');
}

// ── Standalone Invoice dialog ──────────────────────────────────────────────
var _siGame      = '';
var _siClient    = '';
var _siInitial   = {};

// ── + Report: generate a .rtf playtest report with formatting and images ─────
function createReport(gameName, clientName) {
  var btn = event.currentTarget || event.target;
  var origLabel = btn ? btn.textContent : '';
  if (btn) { btn.disabled = true; btn.textContent = 'Creating…'; }

  var fd = new FormData();
  fd.append('id',     SHEET_ID);
  fd.append('game',   gameName  || '');
  fd.append('client', clientName || '');

  fetch(APP_BASE + 'push/createReport.php', { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(j) {
      if (btn) { btn.disabled = false; btn.textContent = origLabel; }
      if (j.error) {
        alert('Report error: ' + j.error);
        return;
      }
      console.log('[Report] logo:', j.debug_logo, '| fetched:', j.logo_fetched, '| error:', j.logo_error || 'none');
      if (j.b64) {
        var bin    = atob(j.b64);
        var bytes  = new Uint8Array(bin.length);
        for (var i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        var blob = new Blob([bytes], { type: 'application/rtf' });
        var url  = URL.createObjectURL(blob);
        var a    = document.createElement('a');
        a.href     = url;
        a.download = j.filename || 'playtest_report.rtf';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        setTimeout(function() { URL.revokeObjectURL(url); }, 5000);
      }
    })
    .catch(function(e) {
      if (btn) { btn.disabled = false; btn.textContent = origLabel; }
      alert('Report failed: ' + e);
    });
}

// YYYY-MM-DD for today + n days (local time), for date inputs
function _isoDaysFromToday(n) {
  var d = new Date(); d.setDate(d.getDate() + n);
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

function openStandaloneInvoiceDialog(gameName, clientName) {
  var _args = arguments; if (!_dbRequirePro(function() { openStandaloneInvoiceDialog.apply(null, _args); })) return;
  _siGame   = gameName   || '';
  _siClient = clientName || '';
  document.getElementById('siGameTitle').textContent    = _siGame;
  document.getElementById('siClientDisplay').textContent = _siClient;
  document.getElementById('siAmount').value          = '';
  document.getElementById('siDueDate').value         = _isoDaysFromToday(14);
  document.getElementById('siNotes').value           = '';
  document.getElementById('siSummary').value         = '';
  document.getElementById('siErr').textContent       = '';
  document.getElementById('siErr').style.display     = 'none';
  document.getElementById('siBtn').disabled          = false;
  document.getElementById('siBtn').textContent       = 'Create Invoice';
  _siInitial = { client: _siClient, amount: '', dueDate: _isoDaysFromToday(14), notes: '' };
  document.getElementById('standaloneInvoiceOverlay').classList.add('open');
  setTimeout(function() { document.getElementById('siSummary').focus(); }, 50);
}

function _siIsDirty() {
  return document.getElementById('siSummary').value.trim() !== '' ||
         document.getElementById('siAmount').value  !== _siInitial.amount  ||
         document.getElementById('siDueDate').value !== _siInitial.dueDate ||
         document.getElementById('siNotes').value   !== _siInitial.notes;
}
function _siMarkDirty() { /* tracked live via _siIsDirty */ }

function closeStandaloneInvoiceDialog() {
  if (_siIsDirty()) {
    shakeDialog(document.getElementById('standaloneInvoiceOverlay').querySelector('.contract-dialog'));
    return;
  }
  document.getElementById('standaloneInvoiceOverlay').classList.remove('open');
}
function forceCloseStandaloneInvoiceDialog() {
  document.getElementById('standaloneInvoiceOverlay').classList.remove('open');
}

function submitStandaloneInvoice() {
  var client  = _siClient;
  var amount  = document.getElementById('siAmount').value.trim();
  var dueDate = document.getElementById('siDueDate').value.trim();
  var notes   = document.getElementById('siNotes').value.trim();
  var errEl   = document.getElementById('siErr');
  errEl.textContent   = '';
  errEl.style.display = 'none';
  var siSummary = document.getElementById('siSummary').value.replace(/\s+/g, ' ').trim();
  if (!siSummary) { errEl.textContent = 'Add a one-line summary.'; errEl.style.display = 'block'; document.getElementById('siSummary').focus(); return; }
  if (!amount || parseFloat(amount) <= 0) { errEl.textContent = 'Amount is required.'; errEl.style.display = 'block'; return; }
  var btn = document.getElementById('siBtn');
  btn.disabled    = true;
  btn.textContent = 'Creating…';
  var fd = new FormData();
  fd.append('id',           SHEET_ID);
  fd.append('game',         _siGame);
  fd.append('client',       client);
  fd.append('estimate_num', '');
  fd.append('estimate_amt', '0');
  fd.append('invoice_amt',  amount);
  fd.append('summary',      siSummary);
  fd.append('tgt_start',    dueDate);
  fd.append('notes',        notes);
  fd.append('my_name',      MY_NAME            || '');
  fd.append('my_phone',     MY_PHONE           || '');
  fd.append('my_company',   MY_COMPANY         || '');
  fd.append('my_logo',      MY_LOGO            || '');
  fd.append('my_address',   MY_COMPANY_ADDRESS || MY_BIO_LOCATION || '');
  fd.append('my_payment',   MY_PAYMENT_INFO    || '');
  fetch(APP_BASE + 'push/createInvoiceFromEstimate.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(j) {
      if (j && j.ok) {
        if (j.invoice_record) ESTIMATES_RAW.push(j.invoice_record);
        if (j.contract_record) CONTRACT_RAW.push(j.contract_record);
        forceCloseStandaloneInvoiceDialog();
        renderPublishersViewKeepOpen();
        if (j.url) window.open(j.url, '_blank');
      } else {
        errEl.textContent   = (j && j.error) ? j.error : 'Create failed.';
        errEl.style.display = 'block';
        btn.disabled        = false;
        btn.textContent     = 'Create Invoice';
      }
    })
    .catch(function() {
      errEl.textContent   = 'Create failed. Check your connection.';
      errEl.style.display = 'block';
      btn.disabled        = false;
      btn.textContent     = 'Create Invoice';
    });
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
  var summary = document.getElementById('contractSummary').value.replace(/\s+/g, ' ').trim();
  if (!summary) { errEl.textContent = 'Add a one-line summary.'; errEl.style.display = 'block'; document.getElementById('contractSummary').focus(); return; }
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
  fd.append('summary',      summary);
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
// ── Confirm dialog ────────────────────────────────────────────────────────────
var _confirmCallback = null;
function _showConfirmDialog(title, message, okLabel, callback) {
  document.getElementById('confirmTitle').textContent   = title   || 'Confirm';
  document.getElementById('confirmMessage').textContent = message || 'Are you sure?';
  document.getElementById('confirmOkBtn').textContent   = okLabel || 'OK';
  _confirmCallback = callback || null;
  document.getElementById('confirmOverlay').classList.add('open');
  setTimeout(function() { document.getElementById('confirmOkBtn').focus(); }, 50);
}
function _closeConfirmDialog() {
  document.getElementById('confirmOverlay').classList.remove('open');
  _confirmCallback = null;
}
function _confirmOk() {
  var cb = _confirmCallback;
  _closeConfirmDialog();
  if (cb) cb();
}

// ── Global keyboard shortcuts ─────────────────────────────────────────────────
document.addEventListener('keydown', function(ev) {
  // Cmd/Ctrl + Enter → save session dialog
  if (ev.key === 'Enter' && (ev.metaKey || ev.ctrlKey)) {
    var so = document.getElementById('sessionOverlay');
    if (so && so.classList.contains('open')) {
      ev.preventDefault();
      submitSession();
      return;
    }
  }
  if (ev.key !== 'Escape') return;
  var el;
  el = document.getElementById('confirmOverlay');
  if (el && el.classList.contains('open')) { _closeConfirmDialog(); return; }
  el = document.getElementById('todoOverlay');
  if (el && el.classList.contains('open')) {
    // Unsaved changes to the TODO: a shake, as in the other dialogs; otherwise it closes
    var td = _todoDlgId && _todoById(_todoDlgId);
    var changed = td && (document.getElementById('todoDlgObsIn').value.trim() !== (td.Observation || '') || document.getElementById('todoDlgSolIn').value.trim() !== (td.Thoughts || ''));
    if (changed) shakeDialog(el.firstElementChild); else closeTodoDialog();
    return;
  }
  el = document.getElementById('upgradeOverlay');
  if (el && el.classList.contains('open')) { closeUpgradeDialog(); return; }
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
  el = document.getElementById('invoiceOverlay');
  if (el && el.classList.contains('open')) {
    closeInvoiceDialog();
    return;
  }
  el = document.getElementById('estimateOverlay');
  if (el && el.classList.contains('open')) {
    if (_estimateIsDirty()) shakeDialog(el.querySelector('.estimate-dialog'));
    else forceCloseEstimateDialog();
    return;
  }
  el = document.getElementById('contractEditOverlay');
  if (el && el.classList.contains('open')) {
    closeContractEditDialog();
    return;
  }
  el = document.getElementById('standaloneInvoiceOverlay');
  if (el && el.classList.contains('open')) {
    closeStandaloneInvoiceDialog();
    return;
  }
  el = document.getElementById('paymentOverlay');
  if (el && el.classList.contains('open')) {
    if (document.getElementById('payPersonCombo').classList.contains('open')) { document.getElementById('payPersonCombo').classList.remove('open'); return; }
    closePaymentDialog();
    return;
  }
  el = document.getElementById('contractOverlay');
  if (el && el.classList.contains('open')) {
    closeContractDialog();
    return;
  }
  el = document.getElementById('dashSignInOverlay');
  if (el && el.classList.contains('open')) { closeDashSignInDialog(); return; }
  el = document.getElementById('dashTMOverlay');
  if (el && el.classList.contains('open')) { closeDashTMDialog(); return; }
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
// ── Dashboard Time & Materials dialog ────────────────────────────────────────

// ── T&M helpers ──────────────────────────────────────────────────────────────
function _fmtTMTimeInput(inp) {
  var raw = (inp.value || '').trim();
  if (!raw || /[a-zA-Z]/.test(raw)) return;
  var num = parseFloat(raw);
  if (isNaN(num) || num <= 0) return;
  var totalMins = num < 9 ? num * 60 : num;
  totalMins = Math.round(totalMins / 15) * 15;
  if (totalMins <= 0) totalMins = 15;
  var h = Math.floor(totalMins / 60), m = totalMins % 60;
  inp.value = h && m ? h + ' hr ' + m + ' min' : h ? h + ' hr' : m + ' min';
}

function _parseTMMinutes(s) {
  if (!s) return 0;
  var m = 0;
  var hr  = s.match(/(\d+)\s*hr/);
  var min = s.match(/(\d+)\s*min/);
  if (hr)  m += parseInt(hr[1])  * 60;
  if (min) m += parseInt(min[1]);
  return m;
}
function _fmtTMTime(mins) {
  if (!mins) return '';
  var h = Math.floor(mins / 60), m = mins % 60;
  return h && m ? h + 'h ' + m + 'm' : h ? h + 'h' : m + 'm';
}
function _resolveTMPerson(raw) {
  // If the stored value is an email, try to map it to a display name
  if (!raw || raw.indexOf('@') === -1) return raw;
  var email = raw.toLowerCase();
  // Check owner's own email (MY_EMAIL from Settings)
  if (MY_EMAIL && MY_EMAIL.toLowerCase() === email) return MY_NAME || raw;
  // Check signed-in collab user
  if (_dashCollabUser && (_dashCollabUser.email || '').toLowerCase() === email) {
    var n = (_dashCollabUser.bio && _dashCollabUser.bio.name) ? _dashCollabUser.bio.name : '';
    if (n) return n;
    return MY_NAME || raw;
  }
  // Check saved email→name alias (persists in localStorage from previous sign-ins)
  var alias = _dashGetEmailAlias(email);
  if (alias) return alias;
  // Check people data
  if (typeof PEOPLE_DATA !== 'undefined' && PEOPLE_DATA) {
    for (var i = 0; i < PEOPLE_DATA.length; i++) {
      var pe = (PEOPLE_DATA[i]['Email'] || PEOPLE_DATA[i]['email'] || '').toLowerCase();
      if (pe === email) {
        var pn = (PEOPLE_DATA[i]['Name'] || PEOPLE_DATA[i]['name'] || '').trim();
        if (pn) return pn;
      }
    }
  }
  return raw;
}

function _buildTMSummaryHtml(rows) {
  // Aggregate T&M rows by person
  var byPerson = {};
  rows.forEach(function(row) {
    var et = (row['Event'] || '').trim().toLowerCase();
    if (et !== 'time' && et !== 'material') return;
    var person = _resolveTMPerson((row['People'] || '').trim() || '(unknown)');
    if (!byPerson[person]) byPerson[person] = { mins: 0, cost: 0 };
    if (et === 'time') {
      byPerson[person].mins += _parseTMMinutes(row['Observations'] || row['Observation'] || '');
    } else {
      var c = parseFloat((row['Observations'] || row['Observation'] || '').replace(/[^0-9.]/g, ''));
      if (!isNaN(c)) byPerson[person].cost += c;
    }
  });
  var people = Object.keys(byPerson);
  if (!people.length) return '';
  var html = '<div class="tm-summary-row">';
  people.forEach(function(p) {
    var parts = [];
    if (byPerson[p].mins) parts.push(_fmtTMTime(byPerson[p].mins));
    if (byPerson[p].cost) parts.push('$' + byPerson[p].cost.toFixed(2));
    if (!parts.length) return;
    html += '<span><span class="tm-summary-person">' + esc(p) + '</span> <span class="tm-summary-val">' + parts.join(' · ') + '</span></span>';
  });
  html += '</div>';
  return html;
}

var _dashTMGame = '', _dashTMInitialSum = '';
var _dashTMMaterialCount = 0;
var _dashTMTimeOptions = ['15 min','30 min','45 min','1 hr','1 hr 15 min','1 hr 30 min','1 hr 45 min','2 hr','2 hr 30 min','3 hr','3 hr 30 min','4 hr','5 hr','6 hr','7 hr','8 hr'];

function _showDashTMTimeList() {
  var inp  = document.getElementById('dashTMTimeMinutes');
  var list = document.getElementById('dashTMTimeList');
  if (!inp || !list) return;
  var q    = inp.value.trim().toLowerCase();
  var opts = q ? _dashTMTimeOptions.filter(function(o) { return o.toLowerCase().indexOf(q) !== -1; }) : _dashTMTimeOptions;
  if (!opts.length) { list.classList.remove('open'); return; }
  list.innerHTML = opts.map(function(o) {
    return '<div class="tm-time-opt" onmousedown="_pickDashTMTime(\'' + o + '\')">' + o + '</div>';
  }).join('');
  list.classList.add('open');
}
function _hideDashTMTimeList() {
  setTimeout(function() {
    var list = document.getElementById('dashTMTimeList');
    if (list) list.classList.remove('open');
  }, 150);
}
function _pickDashTMTime(val) {
  var inp = document.getElementById('dashTMTimeMinutes');
  if (inp) inp.value = val;
  var list = document.getElementById('dashTMTimeList');
  if (list) list.classList.remove('open');
}

function _dashTMIsDirty() {
  if ((document.getElementById('dashTMSummary').value || '').trim() !== (_dashTMInitialSum || '')) return true;
  if ((document.getElementById('dashTMTimeMinutes').value || '').trim()) return true;
  if ((document.getElementById('dashTMTimeNotes').value   || '').trim()) return true;
  for (var i = 1; i <= _dashTMMaterialCount; i++) {
    var ce = document.getElementById('dashTMMatCost-' + i);
    var de = document.getElementById('dashTMMatDesc-' + i);
    if (ce && ce.value.trim()) return true;
    if (de && de.value.trim()) return true;
  }
  return false;
}

function _dashTMCheckAutoAdd(i) {
  if (i < _dashTMMaterialCount) return;
  var cost = (document.getElementById('dashTMMatCost-' + i) || {}).value || '';
  var desc = (document.getElementById('dashTMMatDesc-' + i) || {}).value || '';
  if (cost.trim() || desc.trim()) _addDashTMMaterialRow();
}

function _addDashTMMaterialRow() {
  _dashTMMaterialCount++;
  var i = _dashTMMaterialCount;
  var div = document.createElement('div');
  div.style.marginBottom = '.35rem';
  div.id = 'dashTMMat-' + i;
  div.innerHTML =
    '<div style="display:grid;grid-template-columns:1fr 110px;gap:.3rem .6rem;align-items:center">'
    + '<input type="text" class="field-input" id="dashTMMatDesc-' + i + '" placeholder="" autocomplete="off" oninput="_dashTMCheckAutoAdd(' + i + ')" style="margin:0" />'
    + '<input type="text" class="field-input" id="dashTMMatCost-' + i + '" placeholder="0.00" autocomplete="off" oninput="_dashTMCheckAutoAdd(' + i + ')" style="margin:0" />'
    + '</div>';
  document.getElementById('dashTMMaterialsContainer').appendChild(div);
}

function openDashTMDialog(gameName) {
  _dashTMGame = gameName;
  document.getElementById('dashTMGameTitle').textContent = gameName;
  document.getElementById('dashTMTimeMinutes').value  = '';
  document.getElementById('dashTMTimeNotes').value    = '';
  document.getElementById('dashTMErr').textContent    = '';
  // T&M belongs to a contract: pick one of this game's (the only one is filled in)
  _sumSrc.dashTM = _contractSummaries(gameName);
  _dashTMInitialSum = _sumSrc.dashTM.length === 1 ? _sumSrc.dashTM[0].s : '';
  document.getElementById('dashTMSummary').value = _dashTMInitialSum;
  document.getElementById('dashTMSummary').dataset.ref = _sumSrc.dashTM.length === 1 ? _sumSrc.dashTM[0].ref : '';
  document.getElementById('dashTMSumCombo').classList.remove('open');
  _dashTMMaterialCount = 0;
  document.getElementById('dashTMMaterialsContainer').innerHTML = '';
  _addDashTMMaterialRow();
  document.getElementById('dashTMBtn').disabled    = false;
  document.getElementById('dashTMBtn').textContent = 'Save';
  document.getElementById('dashTMOverlay').classList.add('open');
  setTimeout(function() { document.getElementById(_dashTMInitialSum ? 'dashTMTimeNotes' : 'dashTMSummary').focus(); }, 80);
}

function closeDashTMDialog() {
  if (_dashTMIsDirty()) { shakeDialog(document.querySelector('#dashTMOverlay .session-dialog')); return; }
  forceCloseDashTMDialog();
}
function forceCloseDashTMDialog() {
  document.getElementById('dashTMOverlay').classList.remove('open');
  _hideDashTMTimeList();
}

async function submitDashTM() {
  _fmtTMTimeInput(document.getElementById('dashTMTimeMinutes'));
  var date      = todayISO();
  var timeMins  = document.getElementById('dashTMTimeMinutes').value.trim();
  var timeNotes = document.getElementById('dashTMTimeNotes').value.trim();

  var rows = [];
  if (timeMins) rows.push({ event: 'Time', observation: timeMins, solution: timeNotes });

  for (var i = 1; i <= _dashTMMaterialCount; i++) {
    var ce = document.getElementById('dashTMMatCost-' + i);
    var de = document.getElementById('dashTMMatDesc-' + i);
    if (!ce) continue;
    var matCost = ce.value.trim();
    var matDesc = de ? de.value.trim() : '';
    if (matCost || matDesc) {
      var costNum = parseFloat(matCost.replace(/[^0-9.]/g, ''));
      var costFmt = (!isNaN(costNum) && matCost !== '') ? '$' + costNum.toFixed(2) : matCost;
      rows.push({ event: 'Material', observation: costFmt, solution: matDesc });
    }
  }

  var tmContract = document.getElementById('dashTMSummary').value.replace(/\s+/g, ' ').trim();
  if (!tmContract || !sumRef('dashTM')) { document.getElementById('dashTMErr').textContent = 'Pick the invoice this is for from the list.'; document.getElementById('dashTMSummary').focus(); return; }
  if (!rows.length) { document.getElementById('dashTMErr').textContent = 'Enter at least one time or material entry.'; return; }

  var btn = document.getElementById('dashTMBtn');
  btn.disabled = true; btn.textContent = 'Saving…';
  document.getElementById('dashTMErr').textContent = '';

  var errors = [];
  for (var r = 0; r < rows.length; r++) {
    var row = rows[r];
    var fd = new FormData();
    var tmPerson = (_dashCollabUser && (_dashCollabUser.bio && _dashCollabUser.bio.name)) || MY_NAME || (_dashCollabUser && _dashCollabUser.email) || '';
    fd.append('id',          SHEET_ID);
    fd.append('game',        _dashTMGame);
    fd.append('date',        date);
    fd.append('event',       row.event);
    fd.append('session_num', tmPerson);
    fd.append('observation', row.observation);
    fd.append('solution',    row.solution);
    fd.append('row_type',    'header');
    fd.append('contract',    tmContract);
    fd.append('contract_ref', sumRef('dashTM'));
    try {
      var resp = await fetch(APP_BASE + 'push/addDevRow.php', { method:'POST', body: fd });
      var data = await resp.json();
      if (!data.ok) errors.push(data.error || 'Unknown error');
    } catch(e) { errors.push(String(e)); }
  }

  btn.disabled = false; btn.textContent = 'Save';
  if (errors.length) {
    document.getElementById('dashTMErr').textContent = errors.join('; ');
  } else {
    forceCloseDashTMDialog();
    reloadDevData(_dashTMGame);
  }
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
        // Refresh card body if cached, then rebuild list — preserve open state + scroll
        if (devCache[_editGameOrigName] !== undefined) {
          renderBody(_editGameOrigName, devCache[_editGameOrigName] || []);
        }
        var _openState = _saveOpenState();
        buildGameList();
        renderCards();
        _restoreOpenState(_openState);
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

// Stopwatch + rounds are defined in devboard-common.js

function getSessionSnapshot() {
  var testers = [];
  document.querySelectorAll('#testersContainer input').forEach(function(el) {
    var v = el.value.trim(); if (v) testers.push(v);
  });
  var obs = [];
  document.querySelectorAll('#obsContainer .obs-pair').forEach(function(pair) {
    var idx = pair.dataset.idx;
    var o = _ceGet(document.getElementById('sObs-' + idx));
    var s = _ceGet(document.getElementById('sSol-' + idx));
    if (o.trim() || s.trim()) obs.push(o.trim() + '|' + s.trim());
  });
  return [
    document.getElementById('sDate').value,
    document.getElementById('sType').value,
    (document.getElementById('sLocation').value || '').trim(),
    (document.getElementById('sTestNum').value  || '').trim(),
    testers.join(','),
    obs.join('||'),
    String(_swSeconds),
    String(_sessionRounds)
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
  addObsPair();

  document.getElementById('sessionErr').style.display    = 'none';
  document.getElementById('sessionBtn').disabled         = false;
  document.getElementById('sessionBtn').textContent      = 'Add Session';
  document.getElementById('deleteSessionBtn').style.display = 'none';
  document.querySelectorAll('#sessionOverlay .btn-cancel').forEach(function(b) { b.disabled = false; });
  _swReset();
  // New session: restore this game's unsaved timer if one is running, else start at 0
  _swLoad(_swSessionKey(gameName), 0, 0);
  document.body.classList.add('session-dialog-open');
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

  // Session length and rounds: a timer still running for this session continues;
  // otherwise load the saved length (paused)
  _swReset();
  _swLoad(_swSessionKey(gameName, session.date, editEventType, editSessionNum),
          _swParseLength(session.length), _swParseRounds(session.length));

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
    var oidx = addObsPair();
    var obsVal = pair.obs || '';
    var imgMatch = obsVal.match(/=IMAGE\("([^"]*)"\)/i);
    if (imgMatch) {
      var imgUrl = imgMatch[1];
      setTimeout(function(i, u) { return function() { _showObsImage(i, u); }; }(oidx, imgUrl), 0);
      obsVal = obsVal.replace(/=IMAGE\("[^"]*"\)/gi, '').trim();
    }
    _ceSet(document.getElementById('sObs-' + oidx), obsVal);
    toggleObsImgBtn(oidx);
    _ceSet(document.getElementById('sSol-' + oidx), pair.sol || '');
    if (pair.todo) toggleObsTodo(oidx, true, pair.todoId);
    _syncTodoBtn(oidx);
  });
  addObsPair();  // trailing empty pair

  document.getElementById('sessionErr').style.display       = 'none';
  document.getElementById('sessionBtn').disabled            = false;
  document.getElementById('sessionBtn').textContent         = 'Save';
  document.getElementById('deleteSessionBtn').style.display = '';
  document.getElementById('deleteSessionBtn').disabled      = false;
  document.getElementById('deleteSessionBtn').textContent   = 'Delete';
  document.querySelectorAll('#sessionOverlay .btn-cancel').forEach(function(b) { b.disabled = false; });
  _swUpdate();
  // Hide session-card images behind the overlay so they don't bleed through while editing
  document.body.classList.add('session-dialog-open');
  document.getElementById('sessionOverlay').classList.add('open');
  // Resize textareas after the overlay is visible so scrollHeight is accurate,
  // then sync pair heights so both columns match the taller of the two.
  // Snapshot is taken here so hidden-element innerText returns real content.
  setTimeout(function() {
    document.querySelectorAll('#obsContainer .field-textarea').forEach(function(el) {
      if (el.isContentEditable) { _ceNorm(el); } else { _autoBullet(el); autoResize(el); }
    });
    document.querySelectorAll('#obsContainer .obs-pair').forEach(function(pair) {
      syncPairHeight(parseInt(pair.dataset.idx));
    });
    _syncReorderBtns();
    _editSnapshot = getSessionSnapshot();
  }, 0);
}

// Turn a server error into a short message: Google's temporary errors arrive as
// a Python traceback plus a full HTML error page, which isn't useful to read.
function _friendlySaveError(e) {
  var msg = String((e && e.message) || 'unknown error');
  var m = msg.match(/APIError[\s\S]*?Error\s*(\d{3})|\b(5\d\d)\b[^\n]*(Server Error|Bad Gateway|Service Unavailable)/i);
  if (m || /<!DOCTYPE|<html/i.test(msg)) {
    var code = m ? (m[1] || m[2]) : '';
    return 'Google Sheets had a temporary problem' + (code ? ' (error ' + code + ')' : '') +
           '. Your changes are still here — wait a moment and click Save again.';
  }
  if (msg.length > 300) msg = msg.slice(0, 300) + '…';
  return msg;
}

// Key identifying a session's timer: a game's new (unsaved) session, or a saved session
function _swSessionKey(game, date, type, num) {
  return date ? ['edit', game, date, type || '', num || ''].join('|') : 'new|' + game;
}
// Key of the session as currently entered in the dialog (used after saving)
function _swDialogKey() {
  return _swSessionKey(_sessionGame, document.getElementById('sDate').value || todayISO(),
    document.getElementById('sType').value, (document.getElementById('sTestNum').value || '').trim());
}

function closeSessionDialog() {
  document.getElementById('sessionOverlay').classList.remove('open');
  _editMode = false;
  _editSnapshot = null;
  document.body.classList.remove('session-dialog-open');
}

function forceCloseSessionDialog() {
  _editSnapshot = null;  // prevent dirty-check from blocking
  closeSessionDialog();
}

function deleteSession() {
  _showConfirmDialog('Delete Session', 'This cannot be undone.', 'Delete', function() {
    var delBtn = document.getElementById('deleteSessionBtn');
    delBtn.disabled    = true;
    delBtn.textContent = 'Deleting…';
    document.getElementById('sessionBtn').disabled = true;

    var fd = new FormData();
    fd.append('id',              SHEET_ID);
    fd.append('game',            _sessionGame);
    fd.append('orig_date',       _editOrigDate);
    fd.append('orig_event',      _editOrigEvent);
    fd.append('orig_session_num', _editOrigSessionNum);

    fetch(APP_BASE + 'push/deleteDevSession.php', { method:'POST', body:fd })
      .then(function(r){ return r.json(); })
      .then(function(j) {
        if (!j.ok) {
          delBtn.disabled    = false;
          delBtn.textContent = 'Delete';
          document.getElementById('sessionBtn').disabled = false;
          var err = document.getElementById('sessionErr');
          err.textContent    = j.error || 'Delete failed';
          err.style.display  = '';
          return;
        }
        // Remove this session's rows from the local cache
        var cache    = devCache[_sessionGame] || [];
        var newCache = [];
        var skipping = false;
        for (var ci = 0; ci < cache.length; ci++) {
          var cr = cache[ci];
          var rd = (cr['Date']  || '').trim();
          var re = (cr['Event'] || '').trim();
          if (skipping) {
            if (rd || re) skipping = false;
            else continue;
          }
          if (!skipping && rd === _editOrigDate && re === _editOrigEvent) {
            if (!_editOrigSessionNum || (cr['People']||'').trim() === _editOrigSessionNum) {
              skipping = true;
              continue;
            }
          }
          newCache.push(cr);
        }
        devCache[_sessionGame] = newCache;
        _swForget(_swKey); _swReset();   // session deleted — drop its timer
        forceCloseSessionDialog();
        renderBody(_sessionGame, devCache[_sessionGame]);
      })
      .catch(function() {
        delBtn.disabled    = false;
        delBtn.textContent = 'Delete';
        document.getElementById('sessionBtn').disabled = false;
      });
  });
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
    var obs = _ceGet(document.getElementById('sObs-' + idx)).trim();
    var sol = _ceGet(document.getElementById('sSol-' + idx)).trim();
    if (_obsImages[idx]) obs = '=IMAGE("' + _obsImages[idx] + '")';
    var todoOn = pair.classList.contains('obs-todo');
    if (obs || sol) obsPairs.push({ obs: obs, sol: sol, todoId: todoOn ? pair.dataset.todoId : '', todo: todoOn ? 'TODO:' + pair.dataset.todoId : '' });
  });

  var date       = document.getElementById('sDate').value || todayISO();
  var eventType  = document.getElementById('sType').value;
  var sessionNum = document.getElementById('sTestNum').value.trim();
  var location   = document.getElementById('sLocation').value.trim();
  var _timeMins  = Math.floor(_swSeconds / 60);
  var _lenPart   = _swSeconds > 0      ? 'Length: ' + _timeMins + ' mins' : '';
  var _rndPart   = _sessionRounds > 0  ? 'Rounds: ' + _sessionRounds       : '';
  var swLength   = [_lenPart, _rndPart].filter(Boolean).join(', ');

  // ── Build local cache rows (same shape as sheet JSON) for optimistic update ──
  var localRows = [];
  localRows.push({ 'Date': date, 'Event': eventType, 'People': sessionNum,
                   'Observations': location, 'Solutions': swLength });
  testerVals.forEach(function(t) {
    localRows.push({ 'Date': '', 'Event': '', 'People': t, 'Observations': '', 'Solutions': '' });
  });
  obsPairs.forEach(function(pair) {
    var lr = { 'Date': '', 'Event': '', 'People': '', 'Observations': pair.obs, 'Solutions': pair.sol };
    if (pair.todo) lr['_notes'] = { 'Solutions': pair.todo };
    localRows.push(lr);
  });

  // ── Edit mode: optimistically patch the cache, wait for save, then close ──
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

    // Keep dialog open while saving
    btn.disabled = true; btn.textContent = 'Saving…';
    var _sCancelBtns = document.querySelectorAll('#sessionOverlay .btn-cancel');
    _sCancelBtns.forEach(function(b) { b.disabled = true; });

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
        _swAfterSave(_swDialogKey());
        _todosSyncFromSave(_sessionGame, eventType, sessionNum, date, obsPairs);
        devCache[_sessionGame] = undefined;
        loadDevData(_sessionGame, true);
        forceCloseSessionDialog();
      })
      .catch(function(e) {
        btn.disabled = false;
        btn.textContent = 'Save';
        _sCancelBtns.forEach(function(b) { b.disabled = false; });
        err.textContent = 'Couldn\'t save changes — ' + _friendlySaveError(e);
        console.error('Save error:', e.message);
        err.style.display = 'block';
      });
    return;
  }

  // ── Add mode: require at least one observation ───────────────────────────────
  if (!obsPairs.length) {
    err.textContent = 'At least one observation is required.';
    err.style.display = 'block';
    return;
  }

  // ── Add mode: show saving state, post rows to server, then close on success ──
  addNewPeople(testerRaws);
  btn.disabled = true; btn.textContent = 'Saving…';
  var _sCancelBtns = document.querySelectorAll('#sessionOverlay .btn-cancel');
  _sCancelBtns.forEach(function(b) { b.disabled = true; });

  // Build all rows and POST in one batch — server writes JSON immediately,
  // syncs to Google Sheet in the background.
  var allRows = [];
  allRows.push({ date: date, event: eventType, session_num: sessionNum, observation: location, solution: swLength, type: 'header' });
  testerVals.forEach(function(t) {
    allRows.push({ date: '', event: '', session_num: t, observation: '', solution: '', type: 'tester' });
  });
  obsPairs.forEach(function(pair) {
    allRows.push({ date: '', event: '', session_num: '', observation: pair.obs, solution: pair.sol, type: 'obs', todo: pair.todo || '' });
  });

  var fd = new FormData();
  fd.append('id',   SHEET_ID);
  fd.append('game', _sessionGame);
  fd.append('rows', JSON.stringify(allRows));
  fetch(APP_BASE + 'push/addDevRows.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (res.error) throw new Error(res.error);
      _swAfterSave(_swDialogKey());
      _todosSyncFromSave(_sessionGame, eventType, sessionNum, date, obsPairs);
      if (!devCache[_sessionGame]) devCache[_sessionGame] = [];
      localRows.forEach(function(r) { devCache[_sessionGame].push(r); });
      renderBody(_sessionGame, devCache[_sessionGame]);
      forceCloseSessionDialog();
    })
    .catch(function(e) {
      btn.disabled = false;
      btn.textContent = 'Add Session';
      _sCancelBtns.forEach(function(b) { b.disabled = false; });
      err.textContent = 'Couldn\'t save session — ' + _friendlySaveError(e);
      err.style.display = 'block';
    });
}

// ── People sheet sync ────────────────────────────────────────────────────────
// After a session or game save, silently add any new names to the People sheet.
// If a name string contains an embedded email (e.g. "Jane Smith jane@co.com"),
// the email is extracted and stored separately; the session sheet keeps the
// original full string unchanged.

function _parsePerson(raw) {
  var m = raw.match(/(\S+@\S+\.\S+)/);
  if (!m) return { name: raw.trim().replace(/,+$/, '').trim(), email: '' };
  var email = m[1];
  var name  = raw.replace(email, '').trim().replace(/,+$/, '').trim();
  return { name: name || raw.trim().replace(/,+$/, '').trim(), email: email };
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

// Event delegation for trash buttons — handles clicks even after innerHTML replacement.
// Buttons only need class obs-img-trash and data-obs-idx; no inline onclick required.
document.addEventListener('click', function(e) {
  var btn = e.target.closest('.obs-img-trash');
  if (!btn) return;
  e.stopPropagation();
  var idx = parseInt(btn.dataset.obsIdx, 10);
  if (!isNaN(idx)) _removeObsImage(idx);
});

function toggleObsImgBtn(idx) {
  var ta   = document.getElementById('sObs-' + idx);
  var wrap = ta ? ta.closest('.obs-ta-wrap') : null;
  if (!wrap) return;
  wrap.classList.toggle('has-obs-text', _ceGet(ta).trim().length > 0);
}  // obs pair idx → uploaded image URL

function _showObsImage(idx, url) {
  _obsImages[idx] = url;
  var ta   = document.getElementById('sObs-' + idx);
  var wrap = ta ? ta.closest('.obs-ta-wrap') : null;
  var pair = ta ? ta.closest('.obs-pair')    : null;
  var pv   = document.getElementById('sImgPreview-' + idx);
  if (ta)   ta.style.display = 'none';
  if (wrap) wrap.classList.remove('has-obs-text');
  if (pv) {
    // Rebuild preview with trash button + image
    pv.style.display = 'block';
    pv.innerHTML =
      '<button type="button" class="obs-img-trash" data-obs-idx="' + idx + '" title="Remove image">' +
        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="pointer-events:none"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>' +
      '</button>' +
      '<img src="' + esc(url) + '" alt="observation image">';
  }
  // Treat image as data — promote pair and add trailing empty row if it was last
  _syncTodoBtn(idx);
  if (pair) {
    var pairs = document.querySelectorAll('#obsContainer .obs-pair');
    var last  = pairs[pairs.length - 1];
    if (last && parseInt(last.dataset.idx) === idx) {
      pair.classList.remove('obs-pair-empty');
      addObsPair();
      _syncReorderBtns();
    }
  }
}

function _removeObsImage(idx) {
  delete _obsImages[idx];
  var ta   = document.getElementById('sObs-' + idx);
  var wrap = ta ? ta.closest('.obs-ta-wrap') : null;
  var pair = ta ? ta.closest('.obs-pair')    : null;
  var pv   = document.getElementById('sImgPreview-' + idx);
  // Show the textarea explicitly
  if (ta) { ta.style.display = 'block'; toggleObsImgBtn(idx); }
  // Replace pv entirely with a fresh empty hidden element (eliminates render cache)
  var newPv = null;
  if (pv && pv.parentElement) {
    var pvParent = pv.parentElement;
    var pvNext   = pv.nextSibling;
    pv.remove();
    newPv = document.createElement('div');
    newPv.className  = 'obs-img-preview';
    newPv.id         = 'sImgPreview-' + idx;
    newPv.style.display = 'none';
    if (pvNext) pvParent.insertBefore(newPv, pvNext);
    else        pvParent.appendChild(newPv);
  }
  // Remove any stray <img> anywhere in this pair
  if (pair) {
    pair.querySelectorAll('img').forEach(function(img) { img.remove(); });
    var sol = _ceGet(document.getElementById('sSol-' + idx));
    if (!sol.trim()) pair.classList.add('obs-pair-empty');
  }
  _syncTodoBtn(idx);
}

// Pasting an image into an Observation uploads it, the same as the image button.
// A row that already has text keeps it; the image goes into the next empty row.
function onObsPaste(e, idx) {
  var items = (e.clipboardData && e.clipboardData.items) || [];
  var file  = null;
  for (var i = 0; i < items.length; i++) {
    if (items[i].kind === 'file' && /^image\//.test(items[i].type)) { file = items[i].getAsFile(); break; }
  }
  if (!file) return;               // plain text paste — let the browser handle it
  e.preventDefault();
  var ta = document.getElementById('sObs-' + idx);
  var target = idx;
  if ((ta && _ceGet(ta).trim()) || _obsImages[idx]) {
    var pairs = document.querySelectorAll('#obsContainer .obs-pair');
    var last  = pairs[pairs.length - 1];
    var li    = last ? parseInt(last.dataset.idx, 10) : -1;
    var lta   = document.getElementById('sObs-' + li);
    if (last && li !== idx && !_obsImages[li] && !(lta && _ceGet(lta).trim())) target = li;
    else { addObsPair(); _syncReorderBtns(); target = _obsCount - 1; }
  }
  handleObsImageFile(target, file);
}

function triggerObsImageUpload(idx) {
  var inp = document.getElementById('sImgFile-' + idx);
  if (inp) inp.click();
}

// Show a spinner in the observation box while an image uploads
function _showObsImageLoading(idx) {
  var ta = document.getElementById('sObs-' + idx);
  var wrap = ta ? ta.closest('.obs-ta-wrap') : null;
  var pv = document.getElementById('sImgPreview-' + idx);
  if (ta) ta.style.display = 'none';
  if (wrap) wrap.classList.remove('has-obs-text');
  if (pv) {
    pv.style.display = 'block';
    pv.innerHTML = '<div class="obs-img-loading"><span class="obs-img-spinner"></span>Uploading image…</div>';
  }
}

function handleObsImageFile(idx, file) {
  if (!file) return;
  _showObsImageLoading(idx);
  if (!file.name) file = new File([file], 'pasted-image.' + ((file.type || 'image/png').split('/')[1] || 'png'), { type: file.type || 'image/png' });
  var fd = new FormData();
  fd.append('id',   SHEET_ID);
  fd.append('file', file);
  fetch(APP_BASE + 'push/uploadMedia.php', { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (!res.ok || !res.url) { _removeObsImage(idx); alert('Upload failed: ' + (res.error || 'Unknown error')); return; }
      _showObsImage(idx, res.url);
    })
    .catch(function() { _removeObsImage(idx); alert('Upload failed.'); });
}

function addObsPair() {
  var idx       = _obsCount++;
  var container = document.getElementById('obsContainer');
  var div       = document.createElement('div');
  div.className   = 'obs-pair obs-pair-empty';
  div.dataset.idx = idx;
  div.innerHTML =
    '<div class="obs-pair-row">' +
      '<div class="obs-pair-inputs">' +
        '<div class="obs-obs-col">' +
          '<div class="obs-ta-wrap">' +
            '<div contenteditable="true" class="field-textarea obs-editable" id="sObs-' + idx + '"' +
              ' data-ph="What happened…" data-empty="1"' +
              ' onfocus="this.dataset.empty=\'\'"' +
              ' onblur="_ceNorm(this)"' +
              ' oninput="_autoBullet(this);_ceNorm(this);syncPairHeight(' + idx + ');onObsInput(' + idx + ');toggleObsImgBtn(' + idx + ');_syncTodoBtn(' + idx + ')"' +
              ' onkeydown="onObsKeydown(event,' + idx + ',0)"' +
              ' onpaste="onObsPaste(event,' + idx + ')"></div>' +
            '<div class="obs-img-preview" id="sImgPreview-' + idx + '" style="display:none">' +
              '<button type="button" class="obs-img-trash" data-obs-idx="' + idx + '" title="Remove image">' +
                '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="pointer-events:none"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>' +
              '</button>' +
            '</div>' +
            '<button type="button" class="obs-img-btn" onclick="triggerObsImageUpload(' + idx + ')" title="Attach image">' +
              '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="pointer-events:none">' +
                '<rect x="3" y="3" width="18" height="18" rx="2"/>' +
                '<circle cx="8.5" cy="8.5" r="1.5"/>' +
                '<polyline points="21 15 16 10 5 21"/>' +
              '</svg>' +
            '</button>' +
            '<input type="file" accept="image/*" id="sImgFile-' + idx + '" style="display:none" onchange="handleObsImageFile(' + idx + ', this.files[0])">' +
          '</div>' +
          '<div class="obs-reorder">' +
            '<button type="button" class="obs-reorder-btn" title="Move up"   onclick="moveObsPair(' + idx + ',-1)">▲</button>' +
            '<button type="button" class="obs-reorder-btn" title="Move down" onclick="moveObsPair(' + idx + ', 1)">▼</button>' +
          '</div>' +
        '</div>' +
        '<div class="obs-sol-col">' +
          '<div contenteditable="true" class="field-textarea obs-editable" id="sSol-' + idx + '"' +
            ' data-ph="Thoughts or solutions…" data-empty="1"' +
            ' onfocus="this.dataset.empty=\'\'"' +
            ' onblur="_ceNorm(this)"' +
            ' oninput="_autoBullet(this);_ceNorm(this);syncPairHeight(' + idx + ');onObsInput(' + idx + ');_syncTodoBtn(' + idx + ')"' +
            ' onkeydown="onObsKeydown(event,' + idx + ',1)"></div>' +
          '<button type="button" class="obs-todo-btn" title="Mark as TODO" aria-pressed="false" onclick="toggleObsTodo(' + idx + ')">' +
            '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="pointer-events:none"><path class="bulb-glass" d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14Z"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>' +
          '</button>' +
        '</div>' +
      '</div>' +
    '</div>';
  container.appendChild(div);
  // Seed each new contenteditable with an empty block so iOS always types
  // inside a <div> child rather than as a bare text node directly in el.
  ['sObs-' + idx, 'sSol-' + idx].forEach(function(id) {
    var ce = document.getElementById(id);
    if (ce && !ce.firstChild) {
      var seed = document.createElement('div');
      seed.innerHTML = '<br>';
      ce.appendChild(seed);
    }
  });
  return idx;
}


// Light-bulb toggle: marks an observation row as TODO (yellow border, "TODO" note in the sheet)
// The light bulb only shows on rows with something in them (text or an image), or that are already TODOs
function _syncTodoBtn(idx) {
  var pair = document.querySelector('#obsContainer .obs-pair[data-idx="' + idx + '"]');
  if (!pair) return;
  var o = document.getElementById('sObs-' + idx), so = document.getElementById('sSol-' + idx);
  var has = !!((o && _ceGet(o).trim()) || (so && _ceGet(so).trim()) || _obsImages[idx]);
  pair.classList.toggle('obs-has-text', has);
}

function _newTodoId() {
  var c = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789', id = '';
  for (var i = 0; i < 8; i++) id += c.charAt(Math.floor(Math.random() * c.length));
  return id;
}

// Light-bulb toggle: marks an observation row as TODO (yellow border, "TODO:<id>" note in the sheet)
function toggleObsTodo(idx, on, todoId) {
  var pair = document.querySelector('#obsContainer .obs-pair[data-idx="' + idx + '"]');
  if (!pair) return;
  on = (on === undefined) ? !pair.classList.contains('obs-todo') : !!on;
  pair.classList.toggle('obs-todo', on);
  if (todoId) pair.dataset.todoId = todoId;
  if (on && !pair.dataset.todoId) pair.dataset.todoId = _newTodoId();
  var b = pair.querySelector('.obs-todo-btn');
  if (b) { b.setAttribute('aria-pressed', on ? 'true' : 'false'); b.title = on ? 'Marked as TODO — click to clear' : 'Mark as TODO'; }
}

function syncPairHeight(idx) {
  var obs = document.getElementById('sObs-' + idx);
  var sol = document.getElementById('sSol-' + idx);
  if (!obs || !sol) return;
  if (!obs.isContentEditable) { obs.style.height = 'auto'; sol.style.height = 'auto'; }
  var h = Math.max(obs.scrollHeight, sol.scrollHeight);
  obs.style.height = h + 'px'; sol.style.height = h + 'px';
}

// Pending reorder animation timer — cancel if user clicks again before it fires
var _moveTimer = null;

function _findScroller(el) {
  var p = el.parentElement;
  while (p && p !== document.body) {
    var ov = getComputedStyle(p).overflowY;
    if (ov === 'auto' || ov === 'scroll') return p;
    p = p.parentElement;
  }
  return document.documentElement;
}

function moveObsPair(idx, dir) {
  // Cancel any in-flight animation and reset all transforms immediately
  if (_moveTimer) {
    clearTimeout(_moveTimer);
    _moveTimer = null;
    var _c = document.getElementById('obsContainer');
    if (_c) {
      _c.querySelectorAll('.obs-pair').forEach(function(p) { p.style.transition = 'none'; p.style.transform = ''; });
      Array.from(_c.parentElement.children).forEach(function(el) { el.style.transition = 'none'; el.style.transform = ''; });
    }
  }

  var container = document.getElementById('obsContainer');
  var all   = Array.from(container.querySelectorAll('.obs-pair'));
  var pairs = all.filter(function(p) {
    var di = p.dataset.idx;
    var o  = _ceGet(document.getElementById('sObs-' + di));
    var s  = _ceGet(document.getElementById('sSol-' + di));
    return o.trim() || s.trim() || _obsImages[di];
  });
  var pos  = pairs.findIndex(function(p){ return parseInt(p.dataset.idx) === idx; });
  var tpos = pos + dir;
  if (pos < 0 || tpos < 0 || tpos >= pairs.length) return;

  var aEl      = pairs[pos];
  var bEl      = pairs[tpos];
  var aIdx     = parseInt(aEl.dataset.idx);
  var bIdx     = parseInt(bEl.dataset.idx);
  var scroller = _findScroller(container);

  // Pin aEl: record its screen position before the DOM change
  var aScreenTop = aEl.getBoundingClientRect().top;

  // Move DOM
  if (dir < 0) {
    container.insertBefore(aEl, bEl);
  } else {
    container.insertBefore(aEl, bEl.nextSibling);
  }

  // Instantly compensate scroll so aEl stays at the same screen position
  var scrollDelta = aEl.getBoundingClientRect().top - aScreenTop;
  scroller.scrollTop += scrollDelta;

  // The instant scrollTop change shifted every element in the scroller by −scrollDelta.
  // Apply the inverse transform to ALL of them (obs-pairs + the top-section siblings)
  // so they appear at their old positions, then animate to 0 — the whole panel slides.
  var otherPairs = all.filter(function(p) { return p !== aEl; });

  // Siblings within obs-section (just the labels row) + siblings of obs-section in the dialog
  var obsSection  = container.parentElement;
  var topSiblings = Array.from(obsSection.children).filter(function(el) { return el !== container; })
    .concat(Array.from(obsSection.parentElement.children).filter(function(el) { return el !== obsSection; }));

  var toAnimate = otherPairs.concat(topSiblings);
  toAnimate.forEach(function(el) {
    el.style.transition = 'none';
    el.style.transform  = 'translateY(' + scrollDelta + 'px)';
  });
  void container.offsetWidth; // flush

  toAnimate.forEach(function(el) {
    el.style.transition = 'transform 0.25s ease-out';
    el.style.transform  = '';
  });

  _moveTimer = setTimeout(function() {
    _moveTimer = null;
    toAnimate.forEach(function(el) { el.style.transition = ''; el.style.transform = ''; });
    all.forEach(function(p)        { p.style.transition  = ''; p.style.transform  = ''; });
    syncPairHeight(aIdx);
    syncPairHeight(bIdx);
    _syncReorderBtns();
  }, 270);
}

function autoResize(el) {
  if (el && el.isContentEditable) return; // contenteditable auto-expands
  el.style.height = 'auto';
  el.style.height = el.scrollHeight + 'px';
}

// ── ContentEditable helpers ───────────────────────────────────────────────────
function _ceGet(el) {
  if (!el) return '';
  if (el.isContentEditable) return (el.innerText || '').replace(/\n$/, '');
  return el.value || '';
}
function _ceSet(el, text) {
  if (!el) return;
  if (!el.isContentEditable) { el.value = text || ''; return; }
  el.innerHTML = '';
  var lines = (text || '').split('\n');
  lines.forEach(function(line) {
    var d = document.createElement('div');
    if (line) { d.textContent = line; if (line.startsWith('• ')) d.classList.add('bul'); }
    else       { d.innerHTML  = '<br>'; }
    el.appendChild(d);
  });
  el.dataset.empty = (text || '').trim() ? '' : '1';
}
function _ceNorm(el) {
  if (!el || !el.isContentEditable) return;
  Array.from(el.children).forEach(function(d) {
    d.classList.toggle('bul', (d.textContent || '').startsWith('• '));
  });
  el.dataset.empty = (el.textContent || '').trim() ? '' : '1';
}

// Auto-convert "- ", "* ", or ". " at the start of a line to "• "
function _autoBullet(el) {
  if (el.isContentEditable) {
    var sel = window.getSelection();
    if (!sel || !sel.rangeCount) return;
    var node = sel.anchorNode;
    while (node && node.parentNode !== el) node = node.parentNode;
    if (!node || node === el) return;
    // If a bare text node sits directly in el (can happen on some browsers/iOS),
    // wrap it in a div so the rest of the logic always deals with a block element.
    if (node.nodeType === 3) {
      var wrapper = document.createElement('div');
      el.insertBefore(wrapper, node);
      wrapper.appendChild(node);
      node = wrapper;
    }
    var block = node;
    var t = block.textContent || '';
    if (/^[-*.] /.test(t)) {
      var tn = block.firstChild;
      // Walk down to the actual text node (iOS may wrap it in a <span>)
      while (tn && tn.nodeType !== 3) tn = tn.firstChild;
      if (tn && tn.nodeType === 3) {
        tn.textContent = '• ' + tn.textContent.slice(2);
        block.classList.add('bul');
        // Defer cursor placement — iOS needs a tick to settle after DOM changes
        setTimeout(function() {
          try {
            var s = window.getSelection();
            if (!s) return;
            var r = document.createRange();
            r.setStart(tn, tn.textContent.length); // always after '• '
            r.collapse(true);
            s.removeAllRanges();
            s.addRange(r);
          } catch(e) {}
        }, 0);
      }
    }
    return;
  }
  var pos    = el.selectionStart;
  var val    = el.value;
  var newVal = val.replace(/^([-*.]) /gm, '• ');
  if (newVal === val) return;
  var delta  = newVal.length - val.length;
  el.value   = newVal;
  el.setSelectionRange(pos + delta, pos + delta);
}

function _syncReorderBtns() {
  var all    = Array.from(document.querySelectorAll('#obsContainer .obs-pair'));
  // Last pair is always the trailing empty row — exclude it from enabled/disabled logic
  var active = all.slice(0, all.length - 1);
  active.forEach(function(pair, i) {
    var btns = pair.querySelectorAll('.obs-reorder-btn');
    if (btns[0]) btns[0].disabled = (i === 0);                    // UP disabled on first
    if (btns[1]) btns[1].disabled = (i === active.length - 1);   // DN disabled on last
  });
}

function onObsInput(idx) {
  var pairs = document.querySelectorAll('#obsContainer .obs-pair');
  var last  = pairs[pairs.length - 1];
  if (!last || parseInt(last.dataset.idx) !== idx) return;
  var obs = _ceGet(document.getElementById('sObs-' + idx));
  var sol = _ceGet(document.getElementById('sSol-' + idx));
  if (obs.trim() || sol.trim()) {
    last.classList.remove('obs-pair-empty');
    addObsPair();
    _syncReorderBtns();
  }
}

// ── Keyboard navigation between obs/sol textareas ────────────────────────────
// Cmd/Ctrl + Arrow moves focus within the grid:
//   Left/Right → same row, other column   Up/Down → same column, adjacent row

function onObsKeydown(e, idx, col) {
  // Bullet list: Enter continues the list; Enter on an empty bullet ends it
  if (e.key === 'Enter' && !e.metaKey && !e.ctrlKey && !e.shiftKey) {
    var el = e.target;
    if (el.isContentEditable) {
      var sel = window.getSelection();
      if (sel && sel.rangeCount) {
        var an = sel.anchorNode;
        while (an && an.parentNode !== el) an = an.parentNode;
        if (an) {
          var blk = an.nodeType === 1 ? an : an.parentNode;
          var t   = (blk && blk !== el) ? (blk.textContent || '') : '';
          if (t.startsWith('• ')) {
            e.preventDefault();
            if (t === '• ') {
              blk.textContent = '';
              blk.classList.remove('bul');
              var r0 = document.createRange();
              r0.setStart(blk, 0); r0.collapse(true);
              sel.removeAllRanges(); sel.addRange(r0);
            } else {
              // Split at cursor: text after cursor goes to new bullet line
              var range   = sel.getRangeAt(0);
              var afterRng = document.createRange();
              afterRng.setStart(range.endContainer, range.endOffset);
              afterRng.setEnd(blk, blk.childNodes.length);
              var afterText = afterRng.toString();
              afterRng.deleteContents();
              var nd = document.createElement('div');
              nd.textContent = '• ' + afterText; nd.classList.add('bul');
              blk.after ? blk.after(nd) : blk.parentNode.insertBefore(nd, blk.nextSibling);
              var tn1 = nd.firstChild;
              var r1  = document.createRange();
              r1.setStart(tn1, 2); r1.collapse(true); // place cursor after '• '
              sel.removeAllRanges(); sel.addRange(r1);
            }
            syncPairHeight(idx);
            return;
          }
        }
      }
      return; // non-bullet Enter: let browser handle
    }
    // Textarea version
    var pos      = el.selectionStart;
    var val      = el.value;
    var lineStart = val.lastIndexOf('\n', pos - 1) + 1;
    var line      = val.slice(lineStart, pos);
    if (line.startsWith('• ')) {
      e.preventDefault();
      if (line === '• ') {
        el.value = val.slice(0, lineStart) + val.slice(pos);
        el.setSelectionRange(lineStart, lineStart);
      } else {
        var ins  = '\n• ';
        el.value = val.slice(0, pos) + ins + val.slice(pos);
        el.setSelectionRange(pos + ins.length, pos + ins.length);
      }
      autoResize(el);
      syncPairHeight(idx);
      return;
    }
  }
  // Plain arrow at the edge of the text → move to the neighbouring cell
  if (!e.metaKey && !e.ctrlKey && !e.altKey && !e.shiftKey &&
      (e.key === 'ArrowLeft' || e.key === 'ArrowRight' || e.key === 'ArrowUp' || e.key === 'ArrowDown') &&
      e.target.isContentEditable && _obsCaretAtEdge(e.target, e.key)) {
    if (_obsMoveCell(idx, col, e.key)) e.preventDefault();
    return;
  }
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

// ── Arrow-key navigation between observation / solution cells ────────────────

// Screen rectangle of the caret (falls back to its line's element when the line is empty)
function _obsCaretRect(range) {
  var r = range.cloneRange(); r.collapse(true);
  var rects = r.getClientRects();
  if (rects.length) return rects[0];
  var n = r.startContainer;
  var el = n.nodeType === 1 ? (n.childNodes[r.startOffset] || n) : n.parentNode;
  if (el && el.nodeType !== 1) el = el.parentNode;
  return el ? el.getBoundingClientRect() : null;
}

// Is the caret at the edge of `el` in direction `key`?
//   Up / Down    → on the first / last visual line
//   Left / Right → at the very start / end of the text
function _obsCaretAtEdge(el, key) {
  var sel = window.getSelection();
  if (!sel || !sel.rangeCount || !sel.isCollapsed) return false;
  var range = sel.getRangeAt(0);
  if (!el.contains(range.startContainer)) return false;
  var caret = _obsCaretRect(range);
  if (!caret) return false;
  var before = document.createRange(); before.selectNodeContents(el); before.setEnd(range.startContainer, range.startOffset);
  var after  = document.createRange(); after.selectNodeContents(el);  after.setStart(range.startContainer, range.startOffset);
  var onFirstLine = !Array.prototype.some.call(before.getClientRects(), function(r) { return r.height && r.top < caret.top - 2; });
  var onLastLine  = !Array.prototype.some.call(after.getClientRects(),  function(r) { return r.height && r.bottom > caret.bottom + 2; });
  if (key === 'ArrowUp')    return onFirstLine;
  if (key === 'ArrowDown')  return onLastLine;
  if (key === 'ArrowLeft')  return onFirstLine && before.toString() === '';
  if (key === 'ArrowRight') return onLastLine  && after.toString()  === '';
  return false;
}

// Focus a cell, putting the caret at its start or end
function _obsFocusCell(el, atEnd) {
  el.focus();
  var r = document.createRange(); r.selectNodeContents(el); r.collapse(!atEnd);
  var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(r);
}

// Move from cell (pair idx, column col) in direction `key`. Returns true if it moved.
function _obsMoveCell(idx, col, key) {
  var pairs = Array.from(document.querySelectorAll('#obsContainer .obs-pair'));
  var pi    = pairs.findIndex(function(p) { return parseInt(p.dataset.idx, 10) === idx; });
  if (pi < 0) return false;
  var tPi = pi, tCol = col;
  if (key === 'ArrowUp')    tPi = pi - 1;
  if (key === 'ArrowDown')  tPi = pi + 1;
  if (key === 'ArrowLeft')  tCol = col - 1;
  if (key === 'ArrowRight') tCol = col + 1;
  if (tPi < 0 || tPi >= pairs.length || tCol < 0 || tCol > 1) return false;
  var tIdx = parseInt(pairs[tPi].dataset.idx, 10);
  var el = document.getElementById((tCol === 0 ? 'sObs-' : 'sSol-') + tIdx);
  if (!el || el.offsetParent === null) {          // observation shown as an image
    if (tCol === 0 && (key === 'ArrowUp' || key === 'ArrowDown')) el = document.getElementById('sSol-' + tIdx);
    else el = null;
    if (!el || el.offsetParent === null) return false;
  }
  // Up / Left land at the end of the text, Down / Right at the start
  _obsFocusCell(el, key === 'ArrowUp' || key === 'ArrowLeft');
  return true;
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

// ── Collab sign-in for owner view ─────────────────────────────────────────────

var _dashCollabUser    = null;
var _dashCollabRemember = false;

function _dashLoadStoredUser() {
  try { var ls = localStorage.getItem('devboard_collab_user'); if (ls) { _dashCollabUser = JSON.parse(ls); _dashCollabRemember = true; return; } } catch(e) {}
  try { var ss = sessionStorage.getItem('devboard_collab_user'); if (ss) _dashCollabUser = JSON.parse(ss); } catch(e) {}
}
function _dashSaveStoredUser(u) {
  var val = JSON.stringify(u);
  if (_dashCollabRemember) { try { localStorage.setItem('devboard_collab_user', val); return; } catch(e) {} }
  else { try { localStorage.removeItem('devboard_collab_user'); } catch(e) {} }
  try { sessionStorage.setItem('devboard_collab_user', val); } catch(e) {}
}
function _dashClearStoredUser() {
  try { localStorage.removeItem('devboard_collab_user'); } catch(e) {}
  try { sessionStorage.removeItem('devboard_collab_user'); } catch(e) {}
  _dashCollabUser = null;
  _dashCollabRemember = false;
}
function _dashUpdateSignedInState() {
  var btn = document.getElementById('dashAuthBtn');
  if (btn) btn.textContent = (window.GBAccount && GBAccount.account()) ? 'Sign Out' : 'Sign In';   // the GameBoard account, whatever else is going on
}
function _dashMenuAuthAction() {
  if (_dashCollabUser) { _dashClearStoredUser(); _dashUpdateSignedInState(); }
  else openDashSignInDialog();
}
function openDashSignInDialog() {
  document.getElementById('dashSignInEmail').value     = '';
  document.getElementById('dashSignInPassword').value  = '';
  document.getElementById('dashSignInErr').textContent = '';
  document.getElementById('dashSignInBtn').disabled    = false;
  document.getElementById('dashSignInBtn').textContent = 'Sign In';
  document.getElementById('dashSignInFields').style.display  = '';
  document.getElementById('dashSignInConfirm').style.display = 'none';
  document.getElementById('dashSignInOverlay').classList.add('open');
  setTimeout(function() { document.getElementById('dashSignInEmail').focus(); }, 80);
}
function closeDashSignInDialog() {
  document.getElementById('dashSignInOverlay').classList.remove('open');
}
function _dashSignInConfirmBack() {
  document.getElementById('dashSignInConfirm').style.display = 'none';
  document.getElementById('dashSignInFields').style.display  = '';
  document.getElementById('dashSignInErr').textContent = '';
  document.getElementById('dashSignInBtn').disabled    = false;
  document.getElementById('dashSignInBtn').textContent = 'Sign In';
  setTimeout(function() { document.getElementById('dashSignInPassword').focus(); }, 50);
}
async function _dashSignInConfirmCreate() {
  var email    = document.getElementById('dashSignInEmail').value.trim();
  var password = document.getElementById('dashSignInPassword').value;
  var remember = document.getElementById('dashSignInRemember').checked;
  var btn      = document.getElementById('dashSignInConfirmBtn');
  btn.disabled = true; btn.textContent = 'Creating…';
  try {
    var fd = new FormData();
    fd.append('email', email); fd.append('password', password);
    fd.append('id', SHEET_ID); fd.append('confirm_new', '1');
    var resp = await fetch(APP_BASE + 'push/collabAuth.php', { method:'POST', body:fd });
    var data = await resp.json();
    if (!data.ok) { btn.disabled = false; btn.textContent = 'Create Account'; return; }
    _dashCollabRemember = remember;
    _dashCollabUser = { email: data.email, bio: data.bio || {} };
    _dashSaveStoredUser(_dashCollabUser);
    _dashSaveEmailAlias(data.email, (data.bio && data.bio.name) ? data.bio.name : '');
    _dashUpdateSignedInState();
    closeDashSignInDialog();
  } catch(e) { btn.disabled = false; btn.textContent = 'Create Account'; }
}
async function submitDashSignIn() {
  var email    = document.getElementById('dashSignInEmail').value.trim();
  var password = document.getElementById('dashSignInPassword').value;
  var remember = document.getElementById('dashSignInRemember').checked;
  var errEl    = document.getElementById('dashSignInErr');
  var btn      = document.getElementById('dashSignInBtn');
  if (!email || !password) { errEl.textContent = 'Enter your email and password.'; return; }
  btn.disabled = true; btn.textContent = 'Signing in…'; errEl.textContent = '';
  try {
    var fd = new FormData();
    fd.append('email', email); fd.append('password', password); fd.append('id', SHEET_ID);
    var resp = await fetch(APP_BASE + 'push/collabAuth.php', { method:'POST', body:fd });
    var data = await resp.json();
    if (data.prompt_create) {
      // Show create-account confirmation panel
      document.getElementById('dashSignInConfirmEmail').textContent = email;
      document.getElementById('dashSignInFields').style.display  = 'none';
      document.getElementById('dashSignInConfirm').style.display = '';
      document.getElementById('dashSignInConfirmBtn').disabled    = false;
      document.getElementById('dashSignInConfirmBtn').textContent = 'Create Account';
      return;
    }
    if (!data.ok) { errEl.textContent = data.error || 'Incorrect email or password.'; btn.disabled = false; btn.textContent = 'Sign In'; return; }
    _dashCollabRemember = remember;
    _dashCollabUser = { email: data.email, bio: data.bio || {} };
    _dashSaveStoredUser(_dashCollabUser);
    _dashSaveEmailAlias(data.email, (data.bio && data.bio.name) ? data.bio.name : '');
    _dashUpdateSignedInState();
    closeDashSignInDialog();
  } catch(e) { errEl.textContent = 'Could not connect. Please try again.'; btn.disabled = false; btn.textContent = 'Sign In'; }
}

function _dashSaveEmailAlias(email, name) {
  if (!email || !name) return;
  try {
    var key = 'devboard_email_aliases_' + SHEET_ID;
    var map = JSON.parse(localStorage.getItem(key) || '{}');
    map[email.toLowerCase()] = name;
    localStorage.setItem(key, JSON.stringify(map));
  } catch(e) {}
}
function _dashGetEmailAlias(email) {
  try {
    var key = 'devboard_email_aliases_' + SHEET_ID;
    var map = JSON.parse(localStorage.getItem(key) || '{}');
    return map[(email || '').toLowerCase()] || '';
  } catch(e) { return ''; }
}

// Load stored user on page load
_dashLoadStoredUser();
_dashUpdateSignedInState();

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
  var name    = (document.getElementById('companyName').value        || '').trim();
  var address = (document.getElementById('companyAddress').value     || '').trim();
  var payment = (document.getElementById('companyPaymentInfo').value || '').trim();
  return name    !== (_companyInitial.name    || '') ||
         address !== (_companyInitial.address || '') ||
         payment !== (_companyInitial.payment || '') ||
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
  document.getElementById('companyName').value        = MY_COMPANY         || '';
  document.getElementById('companyAddress').value     = MY_COMPANY_ADDRESS || '';
  document.getElementById('companyPaymentInfo').value = MY_PAYMENT_INFO    || '';
  document.getElementById('companyLogoFile').value = '';
  _companyLogoUrl = MY_LOGO || '';
  _companyShowLogo(_companyLogoUrl);
  _companyLog('', '');
  document.getElementById('companySaveBtn').disabled   = false;
  document.getElementById('companyCancelBtn').disabled = false;
  document.getElementById('companyCancelBtn').textContent = 'Cancel';
  _companyInitial = { name: MY_COMPANY || '', address: MY_COMPANY_ADDRESS || '', payment: MY_PAYMENT_INFO || '' };
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
  var name        = document.getElementById('companyName').value.trim();
  var address     = document.getElementById('companyAddress').value.trim();
  var paymentInfo = document.getElementById('companyPaymentInfo').value.trim();
  var logoFile    = document.getElementById('companyLogoFile').files[0];
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
    fd.append('company',      name);
    fd.append('address',      address);
    fd.append('payment_info', paymentInfo);
    fd.append('logo_url',     _companyLogoUrl);
    return fetch(APP_BASE + 'push/updateProfile.php', { method:'POST', body:fd })
      .then(function(r) { return r.json(); });
  })
  .then(function(res) {
    if (res.error) throw new Error(res.error);
    MY_COMPANY         = name;
    MY_COMPANY_ADDRESS = address;
    MY_PAYMENT_INFO    = paymentInfo;
    MY_LOGO            = _companyLogoUrl;
    _updateTopBarLogo(MY_LOGO, MY_COMPANY);
    // (Company/Logo no longer shown in profile dialog)
    document.getElementById('companyLogoFile').value = '';
    _companyInitial = { name: MY_COMPANY, address: MY_COMPANY_ADDRESS, payment: MY_PAYMENT_INFO };
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
         f('profileDesc')     !== (_profileInitial.desc     || '') ||
         f('profileSkills')   !== (_profileInitial.skills   || '') ||
         f('profileLocation') !== (_profileInitial.location || '') ||
         f('profileDiscord')  !== (_profileInitial.discord  || '') ||
         f('profilePayment')  !== (_profileInitial.payment  || '') ||
         f('profileNotes')    !== (_profileInitial.notes    || '') ||
         document.getElementById('profilePhotoFile').files.length > 0;
}
function _updateTopBarLogo(url, alt) {   // the top bar shows the DevBoard icon, like every app (the studio's logo stays on its documents)
  var el = document.getElementById('topBarLogo');
  if (!el) return;
  if (url) { el.src = url; el.alt = alt || ''; el.style.display = ''; }
  else { el.src = ''; el.style.display = 'none'; }
}
function openProfileDialog() {
  document.getElementById('profileName').value     = MY_NAME         || '';
  document.getElementById('profileEmail').value    = MY_EMAIL        || '';
  document.getElementById('profilePhone').value    = MY_PHONE        || '';
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

  var _oldProfileEmail = MY_EMAIL || '';
  photoPromise.then(function(imageUrl) {
    _profilePhotoUrl = imageUrl || '';
    // Step 2: update settings (name / email / phone); preserve existing company/logo
    var fd1 = new FormData();
    fd1.append('id', SHEET_ID); fd1.append('name', name);
    fd1.append('email', email); fd1.append('phone', phone);
    fd1.append('company', MY_COMPANY || ''); fd1.append('logo_url', MY_LOGO || '');
    var p1 = fetch(APP_BASE + 'push/updateProfile.php', { method:'POST', body:fd1 }).then(function(r){ return r.json(); });
    // Step 3: update bios row
    var fd2 = new FormData();
    fd2.append('id', SHEET_ID); fd2.append('email', email);
    fd2.append('image_url', imageUrl || ''); fd2.append('description', desc);
    fd2.append('skills', skills); fd2.append('location', location);
    fd2.append('phone', phone);   fd2.append('discord', discord);
    fd2.append('payment', payment); fd2.append('notes', notes);
    var p2 = fetch(APP_BASE + 'push/updateBio.php', { method:'POST', body:fd2 }).then(function(r){ return r.json(); });
    // Step 4: if signed in as collab user and email changed, update collab account too
    var p3 = Promise.resolve();
    if (_dashCollabUser && email && email.toLowerCase() !== (_dashCollabUser.email || '').toLowerCase()) {
      var efd = new FormData();
      efd.append('id', SHEET_ID);
      efd.append('old_email', _dashCollabUser.email);
      efd.append('new_email', email);
      p3 = fetch(APP_BASE + 'push/collabUpdateEmail.php', { method:'POST', body:efd })
        .then(function(r) { return r.json(); })
        .then(function(res) { if (res && res.ok) { _dashCollabUser.email = email; _dashSaveStoredUser(_dashCollabUser); _dashSaveEmailAlias(email, name || MY_NAME); } });
    }
    return Promise.all([p1, p2, p3]);
  })
  .then(function(results) {
    var err = (results[0] && results[0].error) || (results[1] && results[1].error);
    if (err) throw new Error(err);
    MY_NAME = document.getElementById('profileName').value.trim();
    MY_EMAIL = document.getElementById('profileEmail').value.trim();
    MY_PHONE = document.getElementById('profilePhone').value.trim();
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
var _ceDocLabel = 'Estimate';
var _ceInitial = {};
function isContractEditDirty() {
  var f = function(id) { return (document.getElementById(id).value || '').trim(); };
  return f('ceClient')      !== (_ceInitial.client      || '') ||
         f('ceSummary')     !== (_ceInitial.summary     || '') ||
         f('ceTargetStart') !== (_ceInitial.targetStart || '') ||
         f('ceTargetEnd')   !== (_ceInitial.targetEnd   || '') ||
         f('ceStartDate')   !== (_ceInitial.startDate   || '') ||
         f('ceEndDate')     !== (_ceInitial.endDate     || '') ||
         f('ceQuote')       !== (_ceInitial.quote       || '') ||
         f('cePrinting')    !== (_ceInitial.printing    || '') ||
         f('ceStatus')      !== (_ceInitial.status      || '') ||
         f('ceDiscountLabel') !== (_ceInitial.discountLabel || '') ||
         f('ceDiscountPct') !== (_ceInitial.discountPct || '') ||
         f('ceNotes')       !== (_ceInitial.notes       || '');
}

// Sheet money/number value as a plain number for an input (e.g. "$1,200.00" → "1200.00")
function _ceNum(v) {
  return String(v || '').replace(/^'/, '').replace(/[^0-9.]/g, '');
}
function _cePrintingValue(con) { return _ceNum(con.Printing); }

// Tag for an estimate/invoice card, from its contract's Status column
// (falls back to the document type when the contract has no status yet)
function _estStatusTag(est) {
  var st = '';
  if (est && est.contract_id) {
    for (var i = 0; i < CONTRACT_RAW.length; i++) {
      var c = CONTRACT_RAW[i];
      if (c && String(c['Ref Number'] || '') === String(est.contract_id)) { st = _conStatus(c); break; }
    }
  }
  var k = st.toLowerCase();
  if (k === 'paid' || k === 'fully paid') return { cls: ' paid',    label: 'Paid' };
  if (k === 'partial')                    return { cls: ' partial', label: 'Partial' };
  if (k === 'invoiced' || k === 'invoice') return { cls: ' invoice', label: 'Invoiced' };
  if (k === 'estimate')                   return { cls: '',         label: 'Estimate' };
  return est && est.type === 'invoice' ? { cls: ' invoice', label: 'Invoiced' } : { cls: '', label: 'Estimate' };
}

// Contract status: the sheet's "Status" column (older sheets: "Payment")
function _conStatus(con) {
  return String((con && (con.Status || con.Payment)) || '').trim();
}

// Status for the edit dialog. Empty → based on the document type.
function _ceStatusValue(con) {
  var p = _conStatus(con);
  if (p) return p;
  return /^invoice$/i.test(String(con.Type || '').trim()) ? 'Invoiced' : 'Estimate';
}
function _ceSetStatus(val) {
  var sel = document.getElementById('ceStatus');
  // Keep any other existing value (e.g. "Partial") selectable so saving doesn't change it
  sel.querySelectorAll('option[data-extra]').forEach(function(o) { o.remove(); });
  if (val && !Array.prototype.some.call(sel.options, function(o) { return o.value === val; })) {
    var o = document.createElement('option'); o.value = val; o.textContent = val; o.setAttribute('data-extra', '1');
    sel.appendChild(o);
  }
  sel.value = val;
}

// Open the edit dialog from an estimate/invoice card (matched by Ref Number)
function openContractEditForEstimate(estIdx) {
  var _args = arguments; if (!_dbRequirePro(function() { openContractEditForEstimate.apply(null, _args); })) return;
  var est = ESTIMATES_RAW[estIdx];
  if (!est || !est.contract_id) return;
  var ref = String(est.contract_id);
  for (var i = 0; i < CONTRACT_RAW.length; i++) {
    var c = CONTRACT_RAW[i];
    if (c && String(c['Ref Number'] || '') === ref) { openContractEditDialog(i); return; }
  }
  // Not in memory (created since the page loaded) — check the server's contracts cache
  fetch(APP_BASE + 'sheets/' + SHEET_ID + '/contracts.json?v=' + Date.now())
    .then(function(r) { return r.json(); })
    .then(function(list) {
      var row = (Array.isArray(list) ? list : []).filter(function(c) { return c && String(c['Ref Number'] || '') === ref; })[0];
      if (!row) throw new Error('missing');
      CONTRACT_RAW.push(row);
      openContractEditDialog(CONTRACT_RAW.length - 1);
    })
    .catch(function() {
      showSaveToast('This contract isn\'t loaded yet. Use Fetch to reload contracts, then try again.');
    });
}

function openContractEditDialog(idx) {
  var _args = arguments; if (!_dbRequirePro(function() { openContractEditDialog.apply(null, _args); })) return;
  var con = CONTRACT_RAW[idx];
  if (!con) return;
  _ceIdx = idx;
  document.getElementById('ceGameTitle').textContent  = con.Game || '';
  document.getElementById('ceClient').value           = con.Client || '';
  document.getElementById('ceSummary').value          = (con.Summary || '').replace(/^'/, '');
  document.getElementById('ceTargetStart').value      = _toDateInput(con['Target Start Date'] || '');
  document.getElementById('ceTargetEnd').value        = _toDateInput(con['Target End Date']   || '');
  document.getElementById('ceStartDate').value        = _toDateInput(con['Start Date']        || '');
  document.getElementById('ceEndDate').value          = _toDateInput(con['End Date']          || '');
  document.getElementById('ceQuote').value            = _ceNum(con.Quote);
  document.getElementById('ceDiscountLabel').value    = con['Discount Label'] || '';
  document.getElementById('ceDiscountPct').value      = _ceNum(con['Discount %']);
  document.getElementById('cePrinting').value         = _cePrintingValue(con);
  _ceSetStatus(_ceStatusValue(con));
  // Title and button follow the document type (Estimate / Invoice)
  var _ceType = (con.Type || '').trim();
  _ceDocLabel = /^invoice$/i.test(_ceType) ? 'Invoice' : (/^estimate$/i.test(_ceType) ? 'Estimate' : 'Contract');
  document.getElementById('ceDocTitle').textContent   = 'Edit ' + _ceDocLabel;
  document.getElementById('ceNotes').value            = con.Notes   || '';
  document.getElementById('ceErr').textContent        = '';
  document.getElementById('ceErr').style.display      = 'none';
  document.getElementById('ceBtn').disabled           = false;
  document.getElementById('ceBtn').textContent        = 'Save ' + _ceDocLabel;
  // Capture initial state for dirty checking
  _ceInitial = {
    client:      (con.Client || '').trim(),
    summary:     (con.Summary || '').replace(/^'/, '').trim(),
    targetStart: _toDateInput(con['Target Start Date'] || ''),
    targetEnd:   _toDateInput(con['Target End Date']   || ''),
    startDate:   _toDateInput(con['Start Date']        || ''),
    endDate:     _toDateInput(con['End Date']          || ''),
    quote:       _ceNum(con.Quote),
    discountLabel: (con['Discount Label'] || '').trim(),
    discountPct: _ceNum(con['Discount %']),
    printing:    _cePrintingValue(con),
    status:      _ceStatusValue(con),
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
  var ceSummary = document.getElementById('ceSummary').value.replace(/\s+/g, ' ').trim();
  if (!ceSummary) { errEl.textContent = 'Add a one-line summary.'; errEl.style.display = 'block'; document.getElementById('ceSummary').focus(); return; }
  var btn = document.getElementById('ceBtn');
  btn.disabled = true; btn.textContent = 'Saving…';
  var fd = new FormData();
  fd.append('id',           SHEET_ID);
  fd.append('contract_id',  con['Ref Number'] || con.ID || '');
  fd.append('game',         con.Game || '');
  fd.append('client',       client);
  fd.append('summary',      ceSummary);
  fd.append('target_start', document.getElementById('ceTargetStart').value);
  fd.append('target_end',   document.getElementById('ceTargetEnd').value);
  fd.append('start_date',   document.getElementById('ceStartDate').value);
  fd.append('end_date',     document.getElementById('ceEndDate').value);
  fd.append('quote',        document.getElementById('ceQuote').value);
  fd.append('printing',     document.getElementById('cePrinting').value);
  fd.append('discount_label', document.getElementById('ceDiscountLabel').value.trim());
  fd.append('discount_pct',   document.getElementById('ceDiscountPct').value);
  // Profile details, used when the estimate/invoice PDF is regenerated
  fd.append('my_name',      MY_NAME          || '');
  fd.append('my_phone',     MY_PHONE         || '');
  fd.append('my_company',   MY_COMPANY       || '');
  fd.append('my_logo',      MY_LOGO          || '');
  fd.append('my_address',   MY_COMPANY_ADDRESS || MY_BIO_LOCATION || '');
  fd.append('my_payment',   MY_PAYMENT_INFO  || '');
  fd.append('payment',      document.getElementById('ceStatus').value);
  fd.append('notes',        document.getElementById('ceNotes').value);
  fetch(APP_BASE + 'push/updateContract.php', { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(j) {
      if (j && j.ok) {
        // Update in-memory record
        con.Client              = client;
        con.Summary             = ceSummary;
        con['Target Start Date']= document.getElementById('ceTargetStart').value;
        con['Target End Date']  = document.getElementById('ceTargetEnd').value;
        con['Start Date']       = document.getElementById('ceStartDate').value;
        con['End Date']         = document.getElementById('ceEndDate').value;
        con.Quote               = document.getElementById('ceQuote').value;
        con.Printing            = document.getElementById('cePrinting').value;
        con.Status              = document.getElementById('ceStatus').value;
        if ('Payment' in con) con.Payment = con.Status;
        con.Notes               = document.getElementById('ceNotes').value;
        con['Discount Label']   = document.getElementById('ceDiscountLabel').value.trim();
        con['Discount %']       = document.getElementById('ceDiscountPct').value;
        // Estimate/invoice PDF regenerated on the server → refresh its card
        var rg = j.regenerated;
        if (rg && rg.ok && rg.estimate_record) {
          for (var k = ESTIMATES_RAW.length - 1; k >= 0; k--) {
            if (ESTIMATES_RAW[k] && String(ESTIMATES_RAW[k].contract_id) === String(rg.estimate_record.contract_id)) {
              ESTIMATES_RAW[k] = rg.estimate_record; break;
            }
          }
          showSaveToast('Saved and PDF updated.', 'success');
        } else if (rg && rg.error) {
          showSaveToast('Saved, but the PDF could not be updated: ' + rg.error);
        }
        forceCloseContractEditDialog();
        renderPublishersViewKeepOpen();
      } else {
        errEl.textContent = (j && j.error) ? j.error : 'Save failed.';
        errEl.style.display = 'block';
        btn.disabled = false; btn.textContent = 'Save ' + _ceDocLabel;
      }
    })
    .catch(function() {
      errEl.textContent = 'Network error.'; errEl.style.display = 'block';
      btn.disabled = false; btn.textContent = 'Save ' + _ceDocLabel;
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
// #game=Name (from "Your games" on a shared page): open that game on the Games tab
(function() {
  var m = /(?:^#|&)game=([^&]*)/.exec(location.hash || ''); if (!m) return;
  var name = ''; try { name = decodeURIComponent(m[1]); } catch (e) { return; }
  try { history.replaceState(null, '', location.pathname + location.search); } catch (e) {}
  if (name) setTimeout(function() { _actOpenGame(name); }, 0);
})();
// Auto-wrap all date inputs with .date-wrap for cross-browser calendar icon
document.querySelectorAll('input[type="date"]').forEach(function(inp) {
  if (inp.closest('.date-wrap')) return;
  var w = document.createElement('div'); w.className = 'date-wrap';
  inp.parentNode.insertBefore(w, inp); w.appendChild(inp);
});
// Open the calendar when a date field is clicked. The calendar icon is drawn
// by CSS, and only Chrome reacts to clicks on it natively — Safari and Firefox
// need showPicker() (supported in current versions of all three).
// Listen in the capture phase: several dialogs call stopPropagation() on clicks,
// which would otherwise stop this handler from ever seeing them.
document.addEventListener('click', function(e) {
  var inp = e.target;
  if (!inp || inp.tagName !== 'INPUT' || inp.type !== 'date' || inp.disabled || inp.readOnly) return;
  if (typeof inp.showPicker !== 'function') return;
  try { inp.showPicker(); } catch (err) { /* picker already open or not allowed */ }
}, true);

// ── Background-save error toast ────────────────────────────────────────────
var _saveToastTimer = null;
// kind: 'success' shows a green toast; anything else the default red
function showSaveToast(msg, kind) {
  var t = document.getElementById('saveToast');
  document.getElementById('saveToastMsg').textContent = msg;
  t.classList.toggle('success', kind === 'success');
  t.classList.add('visible');
  clearTimeout(_saveToastTimer);
  _saveToastTimer = setTimeout(function() { t.classList.remove('visible'); }, 10000);
}

// ── Pro subscription & donations ──────────────────────────────────────────
var _dbSubStatus = null;

function _dbReturnUrl(flag) {
  var u = new URL(window.location.href);
  u.searchParams.delete('sub');
  u.searchParams.delete('donate');
  if (flag) u.searchParams.set(flag[0], flag[1]);
  return u.toString();
}

function _dbLoadSubStatus(cb) {
  var fd = new FormData(); fd.append('id', SHEET_ID); fd.append('app', 'devboard');
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_BASE + 'push/getSubscription.php');
  xhr.onload = function() {
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = null; }
    _dbSubStatus = (r && r.ok) ? r : { ok: false };
    _dbUpdateSubMenu();
    if (cb) cb(_dbSubStatus);
  };
  xhr.onerror = function() { _dbSubStatus = { ok: false }; if (cb) cb(_dbSubStatus); };
  xhr.send(fd);
}

function _dbUpdateSubMenu() {
  var s = _dbSubStatus || {};
  var item = document.getElementById('dbUpgradeBtn');
  if (item) {
    item.textContent = s.is_pro ? 'Pro Active' : (s.is_core ? 'Core Active' : 'Subscribe');
    item.style.color = s.is_core ? '#4caf50' : '#f5a623';
  }
  document.querySelectorAll('[data-pro-badge]').forEach(function(b) { b.style.display = s.is_pro ? 'none' : ''; });
}

// Contracts, estimates and invoices are DevBoard Pro. Returns true when allowed;
// otherwise shows the plans (after loading the status first if needed).
function _dbRequirePro(retry) {
  if (_dbSubStatus === null) {
    _dbLoadSubStatus(function(s) { if (s.is_pro || s.ok === false) retry(); else openUpgradeDialog('pro'); });
    return false;
  }
  if (_dbSubStatus.is_pro || _dbSubStatus.ok === false) return true;
  openUpgradeDialog('pro');
  return false;
}

function openUpgradeDialog(reason) {
  document.getElementById('upgradeIntro').textContent = reason === 'pro'
    ? 'Contracts, estimates and invoices are part of DevBoard Pro.'
    : 'Pick the plan that fits how you work.';
  document.getElementById('upgradeOverlay').classList.add('open');
  if (_dbSubStatus === null) {
    document.getElementById('upgradeSubStatus').textContent = 'Checking subscription…';
    _dbLoadSubStatus(_renderUpgradeStatus);
  } else {
    _renderUpgradeStatus();
  }
}

function _renderUpgradeStatus() {
  _renderUpgradeStatusMain();
  var s = _dbSubStatus || {};
  if (s.activated) {   // unlocked with an activation key: nothing to buy or manage
    ['upgradeSubBtn', 'planCoreBtn', 'planProBtn'].forEach(function(id) { var b = document.getElementById(id); if (b) b.style.display = 'none'; });
    document.getElementById('upgradeSubStatus').textContent = '';
  }
  if (window.zsActivationKey) zsActivationKey({ el: document.getElementById('upgradeActKey'), base: APP_BASE, sheetId: SHEET_ID, activated: !!s.activated,
    onActivated: function() { _dbSubStatus = null; _dbLoadSubStatus(_renderUpgradeStatus); } });
}
function _renderUpgradeStatusMain() {
  var statusEl = document.getElementById('upgradeSubStatus');
  var manage   = document.getElementById('upgradeSubBtn');
  var coreCard = document.getElementById('planCore'), proCard = document.getElementById('planPro');
  var coreBtn  = document.getElementById('planCoreBtn'), proBtn = document.getElementById('planProBtn');
  var s = _dbSubStatus || {};
  coreCard.classList.toggle('current', s.plan === 'core');
  proCard.classList.toggle('current', s.plan === 'pro');
  coreBtn.disabled = proBtn.disabled = false;
  coreBtn.textContent = 'Choose Core'; coreBtn.onclick = function() { startUpgradeCheckout('core'); };
  proBtn.textContent  = 'Choose Pro';  proBtn.onclick  = function() { startUpgradeCheckout('pro'); };
  manage.style.display = 'none'; manage.onclick = _dbOpenBillingPortal;
  coreBtn.style.display = proBtn.style.display = '';
  if (s.is_core) {
    // Already subscribed: plan changes go through the billing portal (no second subscription).
    manage.style.display = '';
    if (s.plan === 'pro') { coreBtn.style.display = proBtn.style.display = 'none'; }
    else { coreBtn.style.display = 'none'; proBtn.textContent = 'Upgrade to Pro'; proBtn.onclick = _dbOpenBillingPortal; }
  }
  var cur = document.getElementById('upgradeCurrent');
  if (s.ok === false)   cur.innerHTML = 'Current plan: <b>—</b> <span>(couldn’t check right now)</span>';
  else if (_dbSubStatus === null) cur.textContent = '';
  else cur.innerHTML = 'Current plan: <b>' + (s.is_pro ? 'Pro' : s.is_core ? 'Core' : 'Free') + '</b>' +
         (s.is_core && s.period_end ? ' <span>· renews ' + esc(fmtDate(s.period_end)) + '</span>' : '');
  cur.className = 'plan-current' + (s.is_core ? ' paid' : '');
  if (s.status === 'past_due') {
    statusEl.innerHTML = '<span style="color:#f5a623;font-weight:700">⚠ Payment past due</span>';
    manage.textContent = 'Update Payment';
  } else if (s.is_core) {
    statusEl.textContent = '';
    manage.textContent = 'Manage Billing';
  } else {
    statusEl.textContent = (s.ok === false) ? 'Could not check your subscription right now.' : '';
  }
}

function _dbOpenBillingPortal() {
  var url = _dbSubStatus && _dbSubStatus.portal_url;
  if (url) { window.open(url, '_blank', 'noopener'); }
  else { alert('Online billing management is not available yet. Please contact support to change your subscription.'); }
}

function closeUpgradeDialog() {
  document.getElementById('upgradeOverlay').classList.remove('open');
}

function startUpgradeCheckout(plan) {
  plan = plan === 'core' ? 'core' : 'pro';
  var btn = document.getElementById(plan === 'core' ? 'planCoreBtn' : 'planProBtn');
  _dbStartCheckout('subscription', btn, btn.textContent, plan);
}

function startDonation() {
  _dbStartCheckout('donation', null, '');
}

function _dbStartCheckout(type, btn, btnLabel, plan) {
  if (btn) { btn.textContent = 'Redirecting…'; btn.disabled = true; }
  else { showSaveToast('Opening secure checkout…', 'success'); }
  var fd = new FormData();
  fd.append('type', type);
  fd.append('id', SHEET_ID);
  fd.append('app', 'devboard');
  if (plan) fd.append('plan', plan);
  fd.append('success_url', _dbReturnUrl(type === 'donation' ? ['donate', 'thanks'] : ['sub', 'success']));
  fd.append('cancel_url',  _dbReturnUrl(null));
  function failed(msg) {
    if (btn) { btn.textContent = btnLabel; btn.disabled = false; }
    alert(msg);
  }
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_BASE + 'push/stripeCheckout.php');
  xhr.onload = function() {
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = null; }
    if (!r || r.error || !r.url) { failed(r && r.error ? r.error : 'Could not start checkout. Please try again.'); return; }
    window.location.href = r.url;
  };
  xhr.onerror = function() { failed('Network error. Please try again.'); };
  xhr.send(fd);
}

// On load: handle the return from Stripe Checkout, then load the Pro status.
(function() {
  function run() {
    var u   = new URL(window.location.href);
    var sub = u.searchParams.get('sub');
    var don = u.searchParams.get('donate');
    if (sub || don) {
      u.searchParams.delete('sub');
      u.searchParams.delete('donate');
      history.replaceState(null, '', u.pathname + u.search + u.hash);
    }
    if (don === 'thanks') { showSaveToast('♥ Thank you for your donation!', 'success'); }
    if (sub !== 'success') { _dbLoadSubStatus(); return; }
    // The webhook may take a few seconds — confirm before showing Pro.
    showSaveToast('Payment received — activating your plan…', 'success');
    var tries = 0;
    (function poll() {
      _dbLoadSubStatus(function(s) {
        if (s.is_core) { showSaveToast('⭐ DevBoard ' + (s.is_pro ? 'Pro' : 'Core') + ' is now active. Thank you!', 'success'); return; }
        if (++tries < 10) { setTimeout(poll, 3000); return; }
        showSaveToast('Payment received. Your plan will activate shortly — refresh in a minute.', 'success');
      });
    })();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
})();
</script>

<div class="save-toast" id="saveToast" role="alert">
  <span id="saveToastMsg"></span>
  <button class="save-toast-close" onclick="this.parentNode.classList.remove('visible')" aria-label="Dismiss">✕</button>
</div>
<script>
// The GameBoard account: one sign-in for every app. The dashboard is for the sheet's owner; after the free
// week it needs a DevBoard plan (Core, or Pro for contracts, estimates and invoices) or the all-in-one.
// Sheets paid for before accounts keep their plan.
(function() {
  var loadSheetPlan = _dbLoadSubStatus;   // the sheet's own subscription file (paid before accounts)
  function fromAccount(s) {
    var a = s.account || {}, x = s.app || {}, live = s.canEdit;
    if (live && typeof _fixOldRefs === 'function') setTimeout(_fixOldRefs, 0);   // older contracts get standard Ref Numbers
    _dbSubStatus = { ok: true, is_core: live, is_pro: live && !(x.state === 'subscribed' && x.plan === 'core'),
                     status: a.status || '', portal_url: a.portal_url || '', activated: x.state === 'activated' };
    var item = document.getElementById('dbUpgradeBtn');
    if (item) { item.textContent = s.signedIn ? 'Subscription' : 'Subscribe'; item.style.color = x.state === 'subscribed' || x.state === 'activated' || x.state === 'code' ? '#4caf50' : '#f5a623'; }
    document.querySelectorAll('[data-pro-badge]').forEach(function(b) { b.style.display = _dbSubStatus.is_pro ? 'none' : ''; });
    var auth = document.getElementById('dashAuthBtn'); if (auth) auth.textContent = s.signedIn ? 'Sign Out' : 'Sign In';
    // Sessions and notes are signed with the account's email and name
    if (s.signedIn && a.email && (!_dashCollabUser || (_dashCollabUser.email || '').toLowerCase() !== a.email.toLowerCase())) {
      _dashCollabUser = { email: a.email, bio: { name: a.name || '' } }; _dashSaveStoredUser(_dashCollabUser);
    }
    if (a.sheet_paid) loadSheetPlan();   // Core or Pro: the sheet's file knows
  }
  GBAccount.init({
    app: 'devboard', name: 'DevBoard', sheet: SHEET_ID, sheetName: <?= json_encode(gbSheetName((string) $_sheet_id)) ?>, base: APP_BASE, home: 'devboard/features', icon: 'images/db_icon_180.png',
    plans: [{ plan: 'core', label: 'Core', price: '$2.99', note: 'sessions, TODOs, reports' }, { plan: 'pro', label: 'Pro', price: '$3.99', note: 'adds contracts, estimates & invoices' }],
    intros: { pro: 'Contracts, estimates and invoices are part of DevBoard Pro (and the all-in-one).' },
    onChange: fromAccount
  });
  // Changes to the sheet are kept in this browser and saved together (Save, ⌘S, or by themselves after a pause) — like CardBoard
  GBQueue.init({ app: 'devboard', sheet: SHEET_ID, base: APP_BASE, reloadAfterPending: true,
    queue: ['updateGame', 'addGame', 'updateDevSession', 'addDevRows', 'addDevRow', 'deleteDevSession', 'setDevNote', 'updateContract', 'addContract', 'deleteContract', 'addPerson', 'updateProfile', 'updateBio'],
    free: ['gameboardAccount', 'getSubscription', 'getAnalytics', 'pageViewLogger', 'stripeCheckout', 'uploadMedia', 'collabAuth', 'collabGetBio', 'collabUpdateEmail'] });
  window._dbLoadSubStatus = function(cb) { fromAccount(GBAccount.state()); if (cb) cb(_dbSubStatus); };
  window.openUpgradeDialog = function(reason) { GBAccount.openPlans(reason === 'pro' ? 'pro' : ''); };
  window._dashMenuAuthAction = function() { if (GBAccount.account()) GBAccount.signOut(); else GBAccount.signIn().catch(function() {}); };
})();
</script>
<script>
// "Forgot password?" under the collaborator sign-in (js/collab-reset.js)
(function() {
  var s = document.createElement('script'); s.src = String(APP_BASE).replace(/\/?$/, '/') + 'js/collab-reset.js?v=1';
  s.onload = function() { CollabReset.attach({ pass: 'dashSignInPassword', email: 'dashSignInEmail', sheet: (typeof SHEET_ID !== 'undefined' ? SHEET_ID : ''), base: APP_BASE }); };
  document.head.appendChild(s);
})();
</script>
</body>
</html>
