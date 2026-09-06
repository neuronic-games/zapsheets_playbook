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
$_my_name = '';
foreach ($_settings as $_s) {
    if (($s['Label'] ?? $s[0] ?? '') === 'My Name' || ($s['label'] ?? '') === 'My Name') {
        $_my_name = $_s['Value'] ?? $_s[1] ?? '';
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

// Load contracts for Clients tab
$_contracts_file = __DIR__ . '/../../../sheets/' . $_sheet_id . '/contract.json';
$_contracts_raw  = file_exists($_contracts_file)
    ? (json_decode(file_get_contents($_contracts_file), true) ?: [])
    : [];
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
.top-bar-left  { flex:1; min-width:0; }
.top-bar h1    { font-family:'DINBlack',sans-serif; font-size:1rem; margin:0; letter-spacing:.03em; cursor:pointer; }
.top-bar h1:hover { opacity:.8; }
.top-bar .sub  { font-size:.73rem; opacity:.6; margin:0; }

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

/* ── Tab bar ──────────────────────────────────────────── */
.tab-bar { background:#1a1a2e; padding:.45rem 1.25rem; }
.tab-bar-inner { max-width:860px; margin:0 auto; display:flex; gap:.3rem; }
.tab-btn {
  font-family:'DINBlack',sans-serif; font-size:.65rem; text-transform:uppercase;
  letter-spacing:.07em; padding:.28rem .7rem; border-radius:5px;
  border:1px solid rgba(255,255,255,.15); color:rgba(255,255,255,.5);
  background:transparent; cursor:pointer; transition:all .15s;
}
.tab-btn.active { background:#fff; color:#1a1a2e; border-color:#fff; }
.tab-btn:not(.active):hover { color:#fff; border-color:rgba(255,255,255,.35); }

/* ── Views ────────────────────────────────────────────── */
.view { display:none; }
.view.active { display:block; }

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
.clients-view-wrap { max-width:860px; margin:0 auto; padding:1rem 1.25rem; display:flex; flex-direction:column; gap:.75rem; }
.client-card { background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.08); }
.client-card-header { background:#1a1a2e; color:#fff; padding:.55rem 1rem; font-family:'DINBlack',sans-serif; font-size:.88rem; letter-spacing:.02em; display:flex; align-items:center; justify-content:space-between; }
.client-card-count { font-family:'DINRegular',sans-serif; font-size:.7rem; opacity:.55; }
.client-contract-row { display:grid; grid-template-columns:1fr auto auto; gap:.75rem; align-items:center; padding:.5rem 1rem; border-bottom:1px solid #f0f4f8; }
.client-contract-row:last-child { border-bottom:none; }
.client-contract-game { font-family:'DINBlack',sans-serif; font-size:.8rem; color:#1a5f7a; }
.client-contract-dates { font-size:.7rem; color:#888; margin-top:.1rem; }
.client-contract-quote { font-family:'DINBlack',sans-serif; font-size:.8rem; color:#111; white-space:nowrap; }
.client-contract-badge { font-family:'DINBlack',sans-serif; font-size:.6rem; letter-spacing:.05em; text-transform:uppercase; padding:.15rem .45rem; border-radius:999px; white-space:nowrap; background:#e8f4f8; color:#1a5f7a; }
.client-contract-badge.paid     { background:#dcfce7; color:#15803d; }
.client-contract-badge.invoiced { background:#fef9c3; color:#a16207; }
.client-contract-badge.partial  { background:#ffedd5; color:#9a3412; }
.clients-empty { color:#888; font-size:.85rem; padding:1rem 0; }

/* ── Search bar ───────────────────────────────────────── */
.search-bar { padding:.6rem 1.25rem .5rem; max-width:860px; margin:0 auto; display:flex; gap:.6rem; align-items:center; }
.search-wrap { position:relative; flex:1; }
.search-wrap input {
  width:100%; padding:.45rem 2rem .45rem .8rem;
  font-family:'DINRegular',sans-serif; font-size:.8rem;
  border:1px solid #c8d6e0; border-radius:6px; outline:none;
  background:#fff; color:#111;
}
.search-wrap input:focus { border-color:#1a5f7a; }
.search-clear { position:absolute; right:.5rem; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:1rem; color:#aaa; line-height:1; padding:0; display:none; }
.search-wrap.has-text .search-clear { display:block; }
.game-count {
  font-family:'DINBlack',sans-serif; font-size:.7rem;
  text-transform:uppercase; letter-spacing:.05em;
  background:#1a5f7a; color:#fff;
  padding:.28rem .7rem; border-radius:999px; white-space:nowrap; flex-shrink:0;
}
.add-game-btn {
  font-family:'DINBlack',sans-serif; font-size:.75rem;
  text-transform:uppercase; letter-spacing:.07em;
  background:#1a1a2e; color:#fff;
  border:none; border-radius:8px;
  padding:.42rem .85rem; cursor:pointer; flex-shrink:0;
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
.game-card-meta  { font-family:'DINRegular',sans-serif; font-size:.7rem; color:rgba(255,255,255,.55); white-space:nowrap; flex-shrink:0; }
.game-card-chevron { font-size:.65rem; opacity:.55; flex-shrink:0; transition:transform .22s ease; transform:rotate(-90deg); }
.game-card.open .game-card-chevron { transform:rotate(0deg); }
.game-card-body-wrap { display:grid; grid-template-rows:0fr; transition:grid-template-rows .22s ease; }
.game-card.open .game-card-body-wrap { grid-template-rows:1fr; }
.game-card-body { overflow:hidden; min-height:0; }

/* ── Card subtitle bar ────────────────────────────────── */
.card-subtitle {
  display:flex; align-items:center; gap:.75rem;
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
.add-session-btn {
  margin-left:auto;
  font-family:'DINBlack',sans-serif; font-size:.7rem;
  text-transform:uppercase; letter-spacing:.07em;
  background:#1a5f7a; color:#fff;
  border:none; border-radius:6px;
  padding:.3rem .75rem; cursor:pointer;
  transition:background .15s;
}
.add-session-btn:hover { background:#145070; }

/* ── Game info sub-bar ────────────────────────────────── */
.game-info-bar {
  background:#134a5e; color:#fff;
  display:flex; align-items:center; gap:.75rem;
  padding:.42rem 1rem;
}
.game-info-designers {
  font-family:'DINRegular',sans-serif; font-size:.72rem;
  color:rgba(255,255,255,.65); flex:1; min-width:0;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.game-info-edit-btn {
  font-family:'DINBlack',sans-serif; font-size:.6rem;
  text-transform:uppercase; letter-spacing:.06em;
  background:rgba(255,255,255,.15); color:#fff;
  border:1px solid rgba(255,255,255,.22); border-radius:999px;
  padding:.28rem .7rem; cursor:pointer; white-space:nowrap; flex-shrink:0;
  transition:background .15s;
}
.game-info-edit-btn:hover { background:rgba(255,255,255,.28); }

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
.dev-error   { padding:1.1rem 1.1rem; font-size:.8rem; color:#c0392b; }

.session-block { border-top:1px solid #e8f0f4; }
.session-block:first-child { border-top:none; }
.session-header {
  display:flex; flex-direction:column; gap:.28rem;
  padding:.75rem 1rem .65rem;
  background:#f0f7fb; border-bottom:1px solid #d8eaf2;
  cursor:pointer; user-select:none;
}
.session-header:hover { background:#e6f2f8; }
.session-header-row { display:flex; align-items:center; gap:.55rem; }
.session-type {
  font-family:'DINBlack',sans-serif; font-size:.72rem;
  text-transform:uppercase; letter-spacing:.06em;
}
.session-type.type-playtest { color:#1a5f7a; }
.session-type.type-meeting  { color:#6b3fa8; }
.session-type.type-idea     { color:#2e7a52; }
.session-date     { font-family:'DINRegular',sans-serif; font-size:.72rem; color:#999; }
.session-sep      { color:#ccc; font-size:.6rem; }
.session-location { font-family:'DINRegular',sans-serif; font-size:.72rem; color:#777; }
.session-count    { font-family:'DINRegular',sans-serif; font-size:.68rem; color:#bbb; margin-left:auto; white-space:nowrap; }
.session-chevron  { font-size:.6rem; opacity:.45; flex-shrink:0; transition:transform .22s ease; transform:rotate(-90deg); }
.session-block.open .session-chevron { transform:rotate(0deg); }
.session-edit-btn {
  display:none; margin-left:.5rem; padding:.18rem .55rem;
  font-size:.68rem; font-family:'DINRegular',sans-serif;
  background:#1a5f7a; color:#fff; border:none; border-radius:4px;
  cursor:pointer; flex-shrink:0; line-height:1.4;
}
.session-edit-btn:hover { background:#134d63; }
@media (hover: hover) {
  .session-header:hover .session-edit-btn { display:inline-flex; align-items:center; }
}
.session-testers-line { font-family:'DINRegular',sans-serif; font-size:.72rem; color:#888; font-style:italic; padding-left:.05rem; }

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

/* Sync overlay */
.sync-dialog {
  background:#fff; border-radius:10px;
  padding:1.4rem; width:min(480px,92vw);
  box-shadow:0 8px 32px rgba(0,0,0,.22);
  display:flex; flex-direction:column; gap:.75rem;
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
  max-height:92vh; overflow-y:auto;
}
.add-dialog h2 { font-family:'DINBlack',sans-serif; font-size:.95rem; text-transform:uppercase; letter-spacing:.07em; color:#1a1a2e; margin:0; }
.add-new-only { display:none; }   /* shown only when game is not in GAMES_RAW */

/* Session dialog */
.session-dialog {
  background:#fff; border-radius:12px;
  padding:1.5rem; width:min(680px,96vw);
  box-shadow:0 8px 32px rgba(0,0,0,.22);
  display:flex; flex-direction:column; gap:1.1rem;
  max-height:92vh; overflow-y:auto;
}
.session-dialog h2 { font-family:'DINBlack',sans-serif; font-size:.95rem; text-transform:uppercase; letter-spacing:.07em; color:#1a5f7a; margin:0; }
.session-dialog h2 span { color:#1a1a2e; }

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
.field-sep { border:none; border-top:1px solid #e8edf0; margin:.1rem 0; }

/* Observation/solution textareas */
.obs-grid { display:grid; grid-template-columns:1fr 1fr; gap:.9rem; }
.field-textarea {
  display:block; width:100%; padding:.6rem .75rem;
  font-family:'DINRegular',sans-serif; font-size:.85rem; color:#111; line-height:1.5;
  border:2px solid #1a1a2e; border-radius:6px; outline:none;
  background:#fff; resize:none; overflow:hidden;
  transition:border-color .15s;
}
.field-textarea:focus { border-color:#1a5f7a; }
.obs-pair-empty .field-textarea { border:1.5px solid #d0d8e0; background:#fafbfc; }
.obs-pair-empty .field-textarea:focus { border-color:#1a5f7a; background:#fff; }

/* Keyboard navigation hint — hidden on touch-only devices */
.obs-kbd-hint {
  display:none; font-family:'DINRegular',sans-serif; font-size:.7rem;
  color:#c8d0d8; user-select:none; margin-right:auto;
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

@media (max-width:540px) {
  .field-grid { grid-template-columns:1fr 1fr; }
  .field-group.span2 { grid-column:span 1; }
  .obs-grid { grid-template-columns:1fr; }
}
</style>
</head>
<body>

<!-- Top bar -->
<div class="top-bar">
  <div class="top-bar-inner">
    <div class="top-bar-left">
      <h1 onclick="window.location.href=APP_BASE+'devboard'">DevBoard</h1>
      <p class="sub">Playtest Notes</p>
    </div>

    <div class="account-menu-wrap">
      <button class="top-btn" onclick="toggleAccountMenu()" title="Menu">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <circle cx="12" cy="7.5" r="4.5"/>
          <path d="M3.5 21c0-4.14 3.81-7.5 8.5-7.5s8.5 3.36 8.5 7.5"/>
        </svg>
      </button>
      <div class="account-menu" id="accountMenu">
        <button class="account-menu-item" onclick="accountMenuFetch()">Fetch</button>
        <button class="account-menu-item" onclick="accountMenuRelease()">Releases</button>
        <button class="account-menu-item" onclick="accountMenuHelp()">Help</button>
      </div>
    </div>
  </div>
</div>

<!-- Tab bar -->
<div class="tab-bar">
  <div class="tab-bar-inner">
    <button class="tab-btn active" id="tabDash"    onclick="switchTab('dash')">Dash</button>
    <button class="tab-btn"        id="tabGames"   onclick="switchTab('games')"><?= count($_games_raw) ?> Games</button>
    <button class="tab-btn"        id="tabClients" onclick="switchTab('clients')"><?= $_client_count ?> Clients</button>
  </div>
</div>

<!-- View: Dash (default) -->
<div class="view active" id="view-dash">
  <!-- Search + Add game -->
  <div class="search-bar">
    <div class="search-wrap" id="searchWrap">
      <input type="text" id="searchInput" placeholder="Search games…"
        oninput="onSearch()" autocomplete="off" spellcheck="false" />
      <button class="search-clear" onclick="clearSearch()">✕</button>
    </div>
    <div class="game-count" id="gameCount">0 Games</div>
    <button class="add-game-btn" onclick="openAddDialog()">+ Game</button>
  </div>
  <!-- Cards -->
  <div class="content" id="cardList"></div>
</div>

<!-- View: Games -->
<div class="view" id="view-games">
  <div class="games-view-wrap">
    <table class="games-list-table">
      <thead><tr><th>Game</th><th>Status</th><th>Started</th><th>Designers</th></tr></thead>
      <tbody id="gamesListBody"></tbody>
    </table>
  </div>
</div>

<!-- View: Clients -->
<div class="view" id="view-clients">
  <div class="clients-view-wrap" id="clientsViewWrap"></div>
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
<div class="overlay" id="addOverlay" onclick="if(event.target===this){if(hasAddData())shakeDialog(this.querySelector('.add-dialog'));else closeAddDialog();}">
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

    <!-- Fields shown only for new games (not already in games sheet) -->
    <div class="add-new-only" id="addNewFields">
      <hr class="field-sep" style="margin:.25rem 0 .75rem" />
      <div class="field-grid" style="grid-template-columns:1fr 1fr">
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
          <label>Designer 1</label>
          <input type="text" class="field-input" id="gDesigner1" placeholder="" autocomplete="off" />
        </div>
        <div class="field-group">
          <label>Designer 2</label>
          <input type="text" class="field-input" id="gDesigner2" placeholder="" autocomplete="off" />
        </div>
        <div class="field-group span2">
          <label>Tagline</label>
          <input type="text" class="field-input" id="gTagline" placeholder="One-line description…" autocomplete="off" />
        </div>
        <div class="field-group span2">
          <label>Description</label>
          <textarea class="field-input" id="gDescription" rows="3" placeholder="Game overview…" style="resize:vertical"></textarea>
        </div>
        <div class="field-group">
          <label>Rules URL</label>
          <input type="url" class="field-input" id="gRules" placeholder="https://…" autocomplete="off" />
        </div>
        <div class="field-group">
          <label>Sellsheet URL</label>
          <input type="url" class="field-input" id="gSellsheet" placeholder="https://…" autocomplete="off" />
        </div>
      </div>
    </div>

    <div class="dialog-err" id="addErr"></div>
    <div class="dialog-actions">
      <button class="btn-cancel" onclick="closeAddDialog()">Cancel</button>
      <button class="btn-dark" id="addBtn" onclick="submitAddGame()">Add Game</button>
    </div>
  </div>
</div>

<!-- Contract dialog -->
<div class="overlay" id="contractOverlay" onclick="if(event.target===this)closeContractDialog()">
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
      <button class="btn-cancel" onclick="closeContractDialog()">Cancel</button>
      <button class="btn-dark" id="contractBtn" onclick="submitContract()">Add Contract</button>
    </div>
  </div>
</div>

<!-- Add session dialog -->
<div class="overlay" id="sessionOverlay" onclick="if(event.target===this){var _d=this.querySelector('.session-dialog');if(_editMode?isSessionDirty():hasSessionData())shakeDialog(_d);else closeSessionDialog();}">
  <div class="session-dialog">
    <h2><span id="sessionDialogAction">+ Session</span> — <span id="sessionGameTitle"></span></h2>

    <!-- Session metadata -->
    <div class="field-grid">
      <!-- Row 1 -->
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
      <div class="field-group" style="grid-row:span 2">
        <label>People</label>
        <div id="testersContainer"></div>
      </div>

      <!-- Row 2 -->
      <div class="field-group">
        <label>Location</label>
        <input type="text" class="field-input" id="sLocation" placeholder="" autocomplete="off" />
      </div>
      <div class="field-group">
        <label>Test Number</label>
        <input type="text" class="field-input" id="sTestNum" readonly
          style="background:#f0f4f8;color:#888;cursor:default;" />
      </div>
      <!-- Testers row 2 (empty cell to keep grid aligned) -->
      <div></div>
    </div>

    <hr class="field-sep" />

    <!-- Observation + Solution (dynamic pairs) -->
    <div id="obsContainer"></div>

    <div class="dialog-err" id="sessionErr"></div>
    <div class="dialog-actions">
      <span class="obs-kbd-hint">⌘ / Ctrl + Arrow — move between fields</span>
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
var APP_BASE    = document.querySelector('base').getAttribute('href');
var SHEET_ID    = <?= json_encode($_sheet_id) ?>;
var GAMES_RAW   = <?= json_encode(array_values($_games_raw), JSON_UNESCAPED_UNICODE) ?>;
var ACTIVE_KEYS = <?= json_encode($_active_keys, JSON_UNESCAPED_UNICODE) ?>;
var MY_NAME      = <?= json_encode($_my_name) ?>;
var PEOPLE_NAMES  = <?= json_encode(array_values($_people_names), JSON_UNESCAPED_UNICODE) ?>;
var CONTRACT_RAW  = <?= json_encode(array_values($_contracts_raw), JSON_UNESCAPED_UNICODE) ?>;

// Quick lookup: lowercased game name → full GAMES_RAW record
var GAMES_INDEX = {};
GAMES_RAW.forEach(function(g) {
  var n = (g.Name || '').trim();
  if (n) GAMES_INDEX[n.toLowerCase()] = g;
});

// ── Helpers ───────────────────────────────────────────────────────────────────

function esc(s) {
  return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmtDate(raw) {
  if (!raw) return '';
  var d = new Date(raw);
  if (isNaN(d.getTime())) return raw;
  return d.toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' });
}
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
var _activeTab = 'dash';
function switchTab(tab) {
  _activeTab = tab;
  ['Dash','Games','Clients'].forEach(function(t) {
    var btn = document.getElementById('tab' + t);
    if (btn) btn.classList.toggle('active', t.toLowerCase() === tab);
  });
  ['dash','games','clients'].forEach(function(v) {
    var el = document.getElementById('view-' + v);
    if (el) el.classList.toggle('active', v === tab);
  });
  if (tab === 'games')   renderGamesView();
  if (tab === 'clients') renderClientsView();
}

function renderGamesView() {
  var sorted = GAMES_RAW.slice().sort(function(a, b) {
    return (a.Name || '').localeCompare(b.Name || '');
  });
  var rows = sorted.map(function(g) {
    var name = g.Name || '';
    var status = g.Status || '';
    var started = g['Date Started'] || g.DateStarted || '';
    var designers = [
      g.Designer1 || g['Designer 1'] || '',
      g.Designer2 || g['Designer 2'] || '',
      g.Designer3 || g['Designer 3'] || '',
      g.Designer4 || g['Designer 4'] || '',
    ].filter(Boolean).join(', ');
    var nameClick = isActive(name)
      ? ' onclick="switchTab(\'dash\');setTimeout(function(){var el=document.querySelector(\'[data-game=\\\'' + esc(name).replace(/'/g,"\\'") + '\\\']\');if(el)el.scrollIntoView({behavior:\'smooth\'});},50)"'
      : '';
    return '<tr>' +
      '<td class="glt-name"' + nameClick + '>' + esc(name) + '</td>' +
      '<td>' + (status ? '<span class="glt-status">' + esc(status) + '</span>' : '') + '</td>' +
      '<td>' + esc(started || '—') + '</td>' +
      '<td>' + esc(designers || '—') + '</td>' +
      '</tr>';
  }).join('');
  var tbody = document.getElementById('gamesListBody');
  if (tbody) tbody.innerHTML = rows || '<tr><td colspan="4" style="color:#888;padding:.75rem">No games yet.</td></tr>';
}

function renderClientsView() {
  var wrap = document.getElementById('clientsViewWrap');
  if (!wrap) return;
  if (!CONTRACT_RAW.length) {
    wrap.innerHTML = '<p class="clients-empty">No contracts yet.</p>';
    return;
  }
  // Group by client
  var byClient = {};
  CONTRACT_RAW.forEach(function(c) {
    var client = (c.Client || '').trim() || 'Unknown';
    if (!byClient[client]) byClient[client] = [];
    byClient[client].push(c);
  });
  var html = '';
  Object.keys(byClient).sort().forEach(function(client) {
    var contracts = byClient[client];
    html += '<div class="client-card">';
    html += '<div class="client-card-header">' +
      esc(client) +
      '<span class="client-card-count">' + contracts.length + (contracts.length === 1 ? ' contract' : ' contracts') + '</span>' +
    '</div>';
    contracts.forEach(function(c) {
      var quoteNum = parseFloat(c.Quote || '');
      var quote    = isNaN(quoteNum) ? '—' : '$' + quoteNum.toLocaleString('en-US', {minimumFractionDigits:0, maximumFractionDigits:2});
      var payment  = (c.Payment || '').trim();
      var badgeCls = 'client-contract-badge';
      if (payment === 'Fully Paid') badgeCls += ' paid';
      else if (payment === 'Invoiced') badgeCls += ' invoiced';
      else if (payment === 'Partial')  badgeCls += ' partial';
      var ts = c['Target Start Date'] || '';
      var te = c['Target End Date']   || '';
      var dateRange = (ts || te) ? (ts || '?') + ' → ' + (te || '?') : '';
      html += '<div class="client-contract-row">';
      html += '<div>' +
        '<div class="client-contract-game">' + esc(c.Game || '—') + '</div>' +
        (dateRange ? '<div class="client-contract-dates">' + esc(dateRange) + '</div>' : '') +
      '</div>';
      html += '<div class="client-contract-quote">' + esc(quote) + '</div>';
      html += '<div><span class="' + badgeCls + '">' + esc(payment || '—') + '</span></div>';
      html += '</div>';
    });
    html += '</div>';
  });
  wrap.innerHTML = html;
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

function renderCards(filter) {
  var list    = document.getElementById('cardList');
  var count   = document.getElementById('gameCount');
  var q       = (filter || '').toLowerCase().trim();
  var visible = q
    ? allGames.filter(function(g) { return (g.Name||'').toLowerCase().indexOf(q) !== -1; })
    : allGames;

  count.textContent = visible.length + (visible.length === 1 ? ' Game' : ' Games');

  if (!visible.length) {
    list.innerHTML = '<div class="no-games"><strong>' + (q ? 'No matching games' : 'No games yet') + '</strong>' +
      (q ? 'Try a different search.' : 'Click "+ Game" to start tracking a game.') + '</div>';
    return;
  }

  list.innerHTML = '';
  visible.forEach(function(g) {
    var name = g.Name || '';
    var div  = document.createElement('div');
    div.className    = 'game-card';
    div.dataset.game = name;
    div.innerHTML =
      '<div class="game-card-header" onclick="toggleCard(this.parentNode)">' +
        '<span class="game-card-title">' + esc(name) + '</span>' +
        '<span class="game-card-meta">' + esc(g.Status || '') + '</span>' +
        '<span class="game-card-chevron">▼</span>' +
      '</div>' +
      '<div class="game-card-body-wrap">' +
        '<div class="game-card-body" id="body-' + safeName(name) + '">' +
          '<div class="dev-loading">Loading…</div>' +
        '</div>' +
      '</div>';
    list.appendChild(div);
  });
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

function loadDevData(gameName) {
  devCache[gameName] = null;
  var fd = new FormData();
  fd.append('id', SHEET_ID);
  fd.append('game', gameName);
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

// ── Render card body (subtitle + sessions) ────────────────────────────────────

var _sessionCache = {};  // gameName → allSessions array (all, not filtered)

function renderBody(gameName, rows) {
  var body = document.getElementById('body-' + safeName(gameName));
  if (!body) return;

  var allSessions = buildSessions(rows).reverse();  // newest first
  _sessionCache[gameName] = allSessions;
  var nPlay = allSessions.filter(function(s){ return s.testnum.toLowerCase().indexOf('playtest ') === 0; }).length;
  var nMeet = allSessions.filter(function(s){ return s.testnum.toLowerCase().indexOf('meeting ')  === 0; }).length;
  var nIdea = allSessions.filter(function(s){ return s.testnum.toLowerCase().indexOf('idea ')     === 0; }).length;

  var activeFilter = devFilter[gameName] || null;
  var sessions = activeFilter
    ? allSessions.filter(function(s){ return s.testnum.toLowerCase().indexOf(activeFilter.toLowerCase() + ' ') === 0; })
    : allSessions;

  function chipClass(type, baseClass) {
    var cls = 'card-stat ' + baseClass;
    if (activeFilter) cls += (activeFilter.toLowerCase() === type.toLowerCase() ? ' stat-active' : ' stat-dim');
    return cls;
  }

  var gn = esc(gameName);
  var html = '';

  // Game info bar — designers + Edit button
  var gameRec   = GAMES_INDEX[gameName.toLowerCase()] || {};
  var designers = [
    (gameRec.Designer1 || gameRec['Designer 1'] || '').trim(),
    (gameRec.Designer2 || gameRec['Designer 2'] || '').trim(),
    (gameRec.Designer3 || gameRec['Designer 3'] || '').trim(),
    (gameRec.Designer4 || gameRec['Designer 4'] || '').trim(),
  ].filter(Boolean);
  html += '<div class="game-info-bar">';
  html += '<span class="game-info-designers">' + (designers.length ? esc(designers.join(', ')) : '') + '</span>';
  html += '<button class="game-info-edit-btn" onclick="openContractDialog(\'' + gn + '\')">+ Contract</button>';
  html += '<button class="game-info-edit-btn" onclick="openEditGame(\'' + gn + '\')">Edit</button>';
  html += '</div>';

  // Subtitle bar — per-type chips (clickable to filter)
  html += '<div class="card-subtitle">';
  if (nPlay) html += '<div class="' + chipClass('Playtest','stat-playtest') + '" onclick="filterSessions(\'' + gn + '\',\'Playtest\')">' + nPlay + ' <span>' + (nPlay === 1 ? 'Playtest' : 'Playtests') + '</span></div>';
  if (nMeet) html += '<div class="' + chipClass('Meeting', 'stat-meeting')  + '" onclick="filterSessions(\'' + gn + '\',\'Meeting\')">'  + nMeet + ' <span>' + (nMeet === 1 ? 'Meeting'  : 'Meetings')  + '</span></div>';
  if (nIdea) html += '<div class="' + chipClass('Idea',    'stat-idea')     + '" onclick="filterSessions(\'' + gn + '\',\'Idea\')">'     + nIdea + ' <span>' + (nIdea === 1 ? 'Idea'     : 'Ideas')      + '</span></div>';
  if (!nPlay && !nMeet && !nIdea) html += '<div class="card-stat stat-playtest">0 <span>Sessions</span></div>';
  html += '<button class="add-session-btn" onclick="openSessionDialog(\'' + gn + '\')">+ Session</button>';
  html += '</div>';

  // Sessions list
  if (!sessions.length) {
    html += '<div class="dev-empty">' + (activeFilter ? 'No ' + activeFilter + ' sessions.' : 'No playtest sessions yet. Click "+ Session" to log one.') + '</div>';
  } else {
    sessions.forEach(function(s, i) {
      // Determine type class from testnum prefix
      var typeClass = 'type-playtest';
      if (s.testnum.toLowerCase().indexOf('meeting') === 0) typeClass = 'type-meeting';
      else if (s.testnum.toLowerCase().indexOf('idea') === 0) typeClass = 'type-idea';

      var allIdx = allSessions.indexOf(s);
      var gnQ    = JSON.stringify(gameName).replace(/"/g, '&quot;');
      html += '<div class="session-block" id="sblock-' + i + '">';
      html += '<div class="session-header" onclick="toggleSession(' + i + ')" data-game="' + esc(gameName) + '" data-idx="' + allIdx + '">';
      html +=   '<div class="session-header-row">';
      if (s.testnum) html += '<span class="session-type ' + typeClass + '">' + esc(s.testnum) + '</span>';
      if (s.date)    html += '<span class="session-sep">·</span><span class="session-date">' + esc(fmtDate(s.date)) + '</span>';
      if (s.location) html += '<span class="session-sep">·</span><span class="session-location">' + esc(s.location) + '</span>';
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
      if (s.obs.length) {
        html += '<table class="obs-table"><tbody>';
        s.obs.forEach(function(o) {
          html += '<tr><td class="td-obs">' + esc(o.obs) + '</td><td class="td-sol">' + esc(o.sol) + '</td></tr>';
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

function buildSessions(rows) {
  if (!rows || !rows.length) return [];
  var sessions = [];
  var current  = null;

  rows.forEach(function(row) {
    var date   = (row['Date']        || '').trim();
    var event  = (row['Event']       || '').trim();
    var people = (row['People']      || '').trim();
    var obs    = (row['Observation'] || '').trim();
    var sol    = (row['Solution']    || '').trim();

    if (date || event) {
      // Session header row; location is in Observation
      current = { date: date, testnum: event, location: obs, testers: [], obs: [] };
      sessions.push(current);
    } else if (current) {
      if (people) {
        // Tester row: name+email in People column; strip email for display
        var tname = people.replace(/\s+\S+@\S+\.\S+\s*$/, '').trim() || people.trim();
        current.testers.push(tname);
      } else if (obs || sol) {
        // Note row
        current.obs.push({ obs: obs, sol: sol });
      }
    }
  });
  return sessions;
}

// ── Filter sessions by type ───────────────────────────────────────────────────

function filterSessions(gameName, type) {
  devFilter[gameName] = (devFilter[gameName] === type) ? null : type;
  renderBody(gameName, devCache[gameName] || []);
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
  renderCards(inp.value);
}
function clearSearch() {
  document.getElementById('searchInput').value = '';
  document.getElementById('searchWrap').classList.remove('has-text');
  renderCards('');
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

  // Build list: games tab first, then each active dev tab
  var sheets = ['games'];
  Object.keys(ACTIVE_KEYS).forEach(function(k) {
    sheets.push('[' + k + '] dev');
  });

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

var _comboOptions   = [];
var _comboHighlight = -1;
var _existingNames  = {};   // lowercased names already in GAMES_RAW

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
  document.getElementById('gStatus').value                = 'Design';
  document.getElementById('gDateStarted').value           = todayISO();
  document.getElementById('gDesigner1').value             = MY_NAME || '';
  document.getElementById('gDesigner2').value             = '';
  document.getElementById('gTagline').value               = '';
  document.getElementById('gDescription').value           = '';
  document.getElementById('gRules').value                 = '';
  document.getElementById('gSellsheet').value             = '';
  _setNewGameFieldsVisible(false);
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

function closeContractDialog() {
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
    return '<div class="combo-item" onmousedown="contractClientPick(\'' + n.replace(/'/g,"&#39;") + '\')">' + n + '</div>';
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
        closeContractDialog();
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
  document.getElementById('gDateStarted').value  = _toDateInput(rec['Date Started'] || rec.DateStarted || '');
  document.getElementById('gDesigner1').value    = rec.Designer1 || rec['Designer 1'] || '';
  document.getElementById('gDesigner2').value    = rec.Designer2 || rec['Designer 2'] || '';
  document.getElementById('gTagline').value      = rec.Tagline || rec['Tag Line'] || rec.SubTitle || '';
  document.getElementById('gDescription').value  = rec.Description || '';
  document.getElementById('gRules').value        = rec.Rules || rec['Rules URL'] || rec.RulesURL || '';
  document.getElementById('gSellsheet').value    = rec.Sellsheet || rec['Sellsheet URL'] || rec.SellsheetURL || '';
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
  // more than one tester row, or first tester differs from the auto-fill default
  var testerInputs = document.querySelectorAll('#testersContainer input');
  if (testerInputs.length > 1) return true;
  if (testerInputs.length === 1) {
    var v = testerInputs[0].value.trim();
    if (v && v !== (MY_NAME || '')) return true;
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
  var isNew = q.trim() && !_existingNames[q.trim().toLowerCase()];
  _setNewGameFieldsVisible(isNew);
}
function renderComboOptions(q) {
  var drop = document.getElementById('comboDrop');
  var filtered = q.trim()
    ? _comboOptions.filter(function(n) { return n.toLowerCase().indexOf(q.toLowerCase()) !== -1; })
    : _comboOptions;
  _comboHighlight = -1;
  if (!filtered.length) { drop.innerHTML = q.trim() ? '<div class="combo-empty">New game: "' + esc(q.trim()) + '"</div>' : '<div class="combo-empty">All games already tracked, or type a new name.</div>'; return; }
  drop.innerHTML = filtered.map(function(n) { return '<div class="combo-option" onmousedown="comboSelect(\'' + esc(n) + '\')">' + esc(n) + '</div>'; }).join('');
}
function comboSelect(name) {
  document.getElementById('gameComboInput').value = name;
  document.getElementById('gameCombo').classList.remove('open');
  // Existing game — hide new-game fields
  _setNewGameFieldsVisible(false);
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
    document.getElementById('gDesigner1').value = _gd1e.name;
    document.getElementById('gDesigner2').value = _gd2e.name;

    var fd = new FormData();
    fd.append('id',           SHEET_ID);
    fd.append('orig_name',    _editGameOrigName);
    fd.append('name',         _editGameOrigName);  // name locked in edit mode
    fd.append('status',       document.getElementById('gStatus').value);
    fd.append('date_started', document.getElementById('gDateStarted').value);
    fd.append('designer1',    _gd1e.name);
    fd.append('designer2',    _gd2e.name);
    fd.append('tagline',      document.getElementById('gTagline').value);
    fd.append('description',  document.getElementById('gDescription').value);
    fd.append('rules',        document.getElementById('gRules').value);
    fd.append('sellsheet',    document.getElementById('gSellsheet').value);

    fetch(APP_BASE + 'push/updateGame.php', { method:'POST', body:fd })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        if (res.error) throw new Error(res.error);
        addNewPeople([_gd1e, _gd2e].filter(function(p) { return p.name; }));
        // Update in-memory record
        var key = _editGameOrigName.toLowerCase();
        var rec = GAMES_INDEX[key] || {};
        rec.Status = document.getElementById('gStatus').value;
        rec.Designer1 = _gd1e.name; rec['Designer 1'] = _gd1e.name;
        rec.Designer2 = _gd2e.name; rec['Designer 2'] = _gd2e.name;
        rec.Tagline = document.getElementById('gTagline').value;
        rec.Description = document.getElementById('gDescription').value;
        rec.Rules = document.getElementById('gRules').value;
        rec.Sellsheet = document.getElementById('gSellsheet').value;
        GAMES_INDEX[key] = rec;
        GAMES_RAW.forEach(function(g) { if ((g.Name||'').toLowerCase() === key) g.Status = rec.Status; });
        // Refresh card body if cached, then rebuild list
        if (devCache[_editGameOrigName] !== undefined) {
          renderBody(_editGameOrigName, devCache[_editGameOrigName] || []);
        }
        buildGameList();
        renderCards(document.getElementById('searchInput').value);
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
  document.getElementById('gDesigner1').value = _gd1.name;
  document.getElementById('gDesigner2').value = _gd2.name;

  function addToGamesSheet() {
    var fd = new FormData();
    fd.append('id',           SHEET_ID);
    fd.append('name',         name);
    fd.append('status',       document.getElementById('gStatus').value);
    fd.append('date_started', document.getElementById('gDateStarted').value);
    fd.append('designer1',    _gd1.name);
    fd.append('designer2',    _gd2.name);
    fd.append('tagline',      document.getElementById('gTagline').value);
    fd.append('description',  document.getElementById('gDescription').value);
    fd.append('rules',        document.getElementById('gRules').value);
    fd.append('sellsheet',    document.getElementById('gSellsheet').value);
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
      renderCards(document.getElementById('searchInput').value);
      closeAddDialog();
    })
    .catch(function(e) {
      err.textContent = e.message || 'Could not add game.';
      err.style.display = 'block';
      btn.disabled = false; btn.textContent = 'Add Game';
    });
}

// ── Session numbering ─────────────────────────────────────────────────────────

// Count existing sessions of a given type for a game, return next label e.g. "Playtest 3"
// Sessions store the type in the Event column as "Playtest 1", "Meeting 2", etc.
function nextSessionLabel(gameName, type) {
  var rows     = devCache[gameName] || [];
  var sessions = buildSessions(rows);
  var prefix   = type.toLowerCase() + ' ';
  var count    = sessions.filter(function(s) {
    return s.testnum.toLowerCase().indexOf(prefix) === 0;
  }).length;
  return type + ' ' + (count + 1);
}

function onTypeChange() {
  var type = document.getElementById('sType').value;
  document.getElementById('sTestNum').value = nextSessionLabel(_sessionGame, type);
}

// ── Session dialog ────────────────────────────────────────────────────────────

var _sessionGame   = '';
var _editMode      = false;
var _editOrigDate  = '';
var _editOrigEvent = '';
var _editSnapshot  = null;

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
  var firstIdx = addTesterField('Select or type…');
  if (MY_NAME) document.getElementById('sTesters-' + firstIdx).value = MY_NAME;

  _obsCount = 0;
  document.getElementById('obsContainer').innerHTML = '';
  addObsPair(true);  // first pair with labels

  document.getElementById('sessionErr').style.display = 'none';
  document.getElementById('sessionBtn').disabled    = false;
  document.getElementById('sessionBtn').textContent = 'Add Session';
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
  _editOrigEvent = session.testnum;
  _sessionGame   = gameName;

  document.getElementById('sessionDialogAction').textContent = 'Edit Session';
  document.getElementById('sessionGameTitle').textContent    = gameName;
  document.getElementById('sDate').value     = session.date     || '';
  document.getElementById('sLocation').value = session.location || '';

  // Infer type from testnum prefix; set without triggering onTypeChange auto-numbering
  var tn = (session.testnum || '').toLowerCase();
  var type = tn.indexOf('meeting') === 0 ? 'Meeting' : tn.indexOf('idea') === 0 ? 'Idea' : 'Playtest';
  document.getElementById('sType').value    = type;
  document.getElementById('sTestNum').value = session.testnum || '';

  // Pre-fill testers
  _testerCount = 0; _testersHL = {};
  document.getElementById('testersContainer').innerHTML = '';
  session.testers.forEach(function(t) {
    var tidx = addTesterField('Select or type…');
    document.getElementById('sTesters-' + tidx).value = t;
  });
  addTesterField('Add tester…');  // trailing empty field

  // Pre-fill obs/sol pairs
  _obsCount = 0;
  document.getElementById('obsContainer').innerHTML = '';
  session.obs.forEach(function(pair, pi) {
    var oidx = addObsPair(pi === 0);
    document.getElementById('sObs-' + oidx).value = pair.obs || '';
    document.getElementById('sSol-' + oidx).value = pair.sol || '';
  });
  addObsPair(session.obs.length === 0);  // trailing empty pair (shows labels if first)

  document.getElementById('sessionErr').style.display = 'none';
  document.getElementById('sessionBtn').disabled    = false;
  document.getElementById('sessionBtn').textContent = 'Save Changes';
  _editSnapshot = getSessionSnapshot();
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
    if (obs || sol) obsPairs.push({ obs: obs, sol: sol });
  });

  if (!obsPairs.length) {
    err.textContent = 'At least one observation is required.';
    err.style.display = 'block';
    return;
  }

  btn.disabled = true; btn.textContent = 'Saving…'; err.style.display = 'none';

  var date     = document.getElementById('sDate').value || todayISO();
  var testnum  = document.getElementById('sTestNum').value.trim();
  var location = document.getElementById('sLocation').value.trim();

  // ── Edit mode: replace the existing session via updateDevSession ──────────────
  if (_editMode) {
    var fd = new FormData();
    fd.append('id',         SHEET_ID);
    fd.append('game',       _sessionGame);
    fd.append('orig_date',  _editOrigDate);
    fd.append('orig_event', _editOrigEvent);
    fd.append('date',       date);
    fd.append('event',      testnum);
    fd.append('location',   location);
    fd.append('testers',    JSON.stringify(testerVals));
    fd.append('obs_pairs',  JSON.stringify(obsPairs));

    fetch(APP_BASE + 'push/updateDevSession.php', { method:'POST', body:fd })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        if (res.error) throw new Error(res.error);
        addNewPeople(testerRaws);
        // Force a fresh fetch so devCache reflects the new sheet state
        devCache[_sessionGame] = undefined;
        closeSessionDialog();
        loadDevData(_sessionGame);
      })
      .catch(function(e) {
        err.textContent = e.message || 'Could not save. Try again.';
        err.style.display = 'block';
        btn.disabled = false; btn.textContent = 'Save Changes';
      });
    return;
  }

  // ── Add mode: append new session rows one by one ──────────────────────────────
  // Build ordered rows matching sheet format: Date | Event | Observation | Solution
  //   1. Session header row  (date, testnum, location, "")
  //   2. One tester row each (blank date/event, testerName, "")
  //   3. One obs row each    (blank date/event, obs, sol)
  var allRows = [];
  allRows.push({ date: date, event: testnum, observation: location, solution: '' });
  testerVals.forEach(function(t) {
    allRows.push({ date: '', event: '', observation: t, solution: '' });
  });
  obsPairs.forEach(function(pair) {
    allRows.push({ date: '', event: '', observation: pair.obs, solution: pair.sol });
  });

  // Submit sequentially to preserve sheet row order
  function postRow(row) {
    var fd = new FormData();
    fd.append('id',          SHEET_ID);
    fd.append('game',        _sessionGame);
    fd.append('date',        row.date);
    fd.append('event',       row.event);
    fd.append('observation', row.observation);
    fd.append('solution',    row.solution);
    return fetch(APP_BASE + 'push/addDevRow.php', { method:'POST', body:fd })
      .then(function(r) { return r.json(); });
  }

  allRows.reduce(function(chain, row) {
    return chain.then(function(acc) {
      return postRow(row).then(function(res) { return acc.concat([res]); });
    });
  }, Promise.resolve([]))
    .then(function(results) {
      var failed = results.find(function(r) { return r.error; });
      if (failed) throw new Error(failed.error);
      addNewPeople(testerRaws);
      if (!devCache[_sessionGame]) devCache[_sessionGame] = [];
      results.forEach(function(res) { if (res.row) devCache[_sessionGame].push(res.row); });
      renderBody(_sessionGame, devCache[_sessionGame]);
      closeSessionDialog();
    })
    .catch(function(e) {
      err.textContent = e.message || 'Could not save. Try again.';
      err.style.display = 'block';
      btn.disabled = false; btn.textContent = 'Add Session';
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
    return '<div class="combo-option" onmousedown="testersSelect(' + idx + ',\'' + esc(n) + '\')">' + esc(n) + '</div>';
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

function addObsPair(showLabels) {
  var idx       = _obsCount++;
  var container = document.getElementById('obsContainer');
  var div       = document.createElement('div');
  div.className   = 'obs-pair obs-pair-empty';
  div.dataset.idx = idx;
  var labelsHtml = showLabels
    ? '<div class="obs-pair-labels"><label>Observation</label><label>Solution</label></div>'
    : '';
  div.innerHTML = labelsHtml +
    '<div class="obs-pair-inputs">' +
      '<textarea class="field-textarea" id="sObs-' + idx + '" rows="1"' +
        ' placeholder="What happened…"' +
        ' oninput="autoResize(this);onObsInput(' + idx + ')"' +
        ' onkeydown="onObsKeydown(event,' + idx + ',0)"></textarea>' +
      '<textarea class="field-textarea" id="sSol-' + idx + '" rows="1"' +
        ' placeholder="How to address it…"' +
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

var _lpTimer     = null;
var _lpMoved     = false;

document.addEventListener('touchstart', function(e) {
  var header = e.target.closest('.session-header');
  if (!header || e.target.closest('.session-edit-btn')) return;
  _lpMoved = false;
  _lpTimer = setTimeout(function() {
    _lpTimer = null;
    if (_lpMoved) return;
    var gn  = header.dataset.game;
    var idx = parseInt(header.dataset.idx, 10);
    openEditSessionDialog(gn, idx);
  }, 600);
}, { passive: true });

document.addEventListener('touchmove', function() {
  _lpMoved = true;
  if (_lpTimer) { clearTimeout(_lpTimer); _lpTimer = null; }
}, { passive: true });

document.addEventListener('touchend', function() {
  if (_lpTimer) { clearTimeout(_lpTimer); _lpTimer = null; }
}, { passive: true });

document.addEventListener('touchcancel', function() {
  if (_lpTimer) { clearTimeout(_lpTimer); _lpTimer = null; }
}, { passive: true });

// ── Account menu ─────────────────────────────────────────────────────────────

function toggleAccountMenu() {
  var menu = document.getElementById('accountMenu');
  menu.classList.toggle('open');
}
function closeAccountMenu() {
  document.getElementById('accountMenu').classList.remove('open');
}
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

buildGameList();
renderCards('');
</script>
</body>
</html>
