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
.sync-dialog { background:#fff; border-radius:10px; padding:1.4rem 1.8rem; min-width:240px; text-align:center; box-shadow:0 8px 32px rgba(0,0,0,.2); }
.sync-dialog p { font-size:.85rem; color:#555; margin:.5rem 0 0; }
.sync-spinner { font-size:1.4rem; animation:spin .8s linear infinite; display:inline-block; }

/* Add game dialog */
.add-dialog {
  background:#fff; border-radius:12px;
  padding:1.5rem; width:min(420px,94vw);
  box-shadow:0 8px 32px rgba(0,0,0,.22);
  display:flex; flex-direction:column; gap:1rem;
}
.add-dialog h2 { font-family:'DINBlack',sans-serif; font-size:.95rem; text-transform:uppercase; letter-spacing:.07em; color:#1a1a2e; margin:0; }

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
  background:#fff; resize:vertical; min-height:120px;
  transition:border-color .15s;
}
.field-textarea:focus { border-color:#1a5f7a; }

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
    <button class="top-btn" id="fetchBtn" onclick="doFetch()">
      <span class="sync-icon">↻</span> Fetch
    </button>
  </div>
</div>

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

<!-- Fetch overlay -->
<div class="overlay" id="syncOverlay">
  <div class="sync-dialog">
    <div class="sync-spinner">↻</div>
    <p id="syncMsg">Fetching from Google Sheets…</p>
  </div>
</div>

<!-- Add game dialog -->
<div class="overlay" id="addOverlay" onclick="if(event.target===this){if(hasAddData())shakeDialog(this.querySelector('.add-dialog'));else closeAddDialog();}">
  <div class="add-dialog">
    <h2>Add Game</h2>
    <div>
      <label style="font-family:'DINBlack',sans-serif;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:#555;display:block;margin-bottom:.35rem;">Game Name</label>
      <div class="combo-wrap" id="gameCombo">
        <input type="text" class="combo-input" id="gameComboInput"
          placeholder="Select or type a game name…"
          autocomplete="off" spellcheck="false"
          oninput="comboFilter()"
          onfocus="comboOpen()"
          onkeydown="comboKey(event)" />
        <div class="combo-dropdown" id="comboDrop"></div>
      </div>
    </div>
    <div class="dialog-err" id="addErr"></div>
    <div class="dialog-actions">
      <button class="btn-cancel" onclick="closeAddDialog()">Cancel</button>
      <button class="btn-dark" id="addBtn" onclick="submitAddGame()">Add Game</button>
    </div>
  </div>
</div>

<!-- Add session dialog -->
<div class="overlay" id="sessionOverlay" onclick="if(event.target===this){if(hasSessionData())shakeDialog(this.querySelector('.session-dialog'));else closeSessionDialog();}">
  <div class="session-dialog">
    <h2>+ Session — <span id="sessionGameTitle"></span></h2>

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
      <button class="btn-cancel" onclick="closeSessionDialog()">Cancel</button>
      <button class="btn-primary" id="sessionBtn" onclick="submitSession()">Add Session</button>
    </div>
  </div>
</div>

<script>
var APP_BASE    = document.querySelector('base').getAttribute('href');
var SHEET_ID    = <?= json_encode($_sheet_id) ?>;
var GAMES_RAW   = <?= json_encode(array_values($_games_raw), JSON_UNESCAPED_UNICODE) ?>;
var ACTIVE_KEYS = <?= json_encode($_active_keys, JSON_UNESCAPED_UNICODE) ?>;
var MY_NAME      = <?= json_encode($_my_name) ?>;
var PEOPLE_NAMES = <?= json_encode(array_values($_people_names), JSON_UNESCAPED_UNICODE) ?>;

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

function renderBody(gameName, rows) {
  var body = document.getElementById('body-' + safeName(gameName));
  if (!body) return;

  var allSessions = buildSessions(rows).reverse();  // newest first
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

      html += '<div class="session-block" id="sblock-' + i + '">';
      html += '<div class="session-header" onclick="toggleSession(' + i + ')">';
      html +=   '<div class="session-header-row">';
      if (s.testnum) html += '<span class="session-type ' + typeClass + '">' + esc(s.testnum) + '</span>';
      if (s.date)    html += '<span class="session-sep">·</span><span class="session-date">' + esc(fmtDate(s.date)) + '</span>';
      if (s.location) html += '<span class="session-sep">·</span><span class="session-location">' + esc(s.location) + '</span>';
      html +=   '<span class="session-count">' + s.obs.length + (s.obs.length === 1 ? ' note' : ' notes') + '</span>';
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
  var seenObs  = false;  // once we see a non-empty Solution, switch to obs mode

  rows.forEach(function(row) {
    var date  = (row['Date']        || '').trim();
    var event = (row['Event']       || '').trim();
    var obs   = (row['Observation'] || '').trim();
    var sol   = (row['Solution']    || '').trim();

    if (date || event) {
      // New session header row
      current = { date: date, testnum: event, location: obs, testers: [], obs: [] };
      sessions.push(current);
      seenObs = false;
    } else if (current) {
      // Email in Solution column = tester row (email placed there for sheet reference)
      var solIsEmail = sol && /\S+@\S+\.\S+/.test(sol);
      if (!seenObs && (!sol || solIsEmail)) {
        // Still in tester region
        if (obs) current.testers.push(obs);
      } else {
        // Observation row
        seenObs = true;
        if (obs || sol) current.obs.push({ obs: obs, sol: sol });
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

function doFetch() {
  var btn = document.getElementById('fetchBtn');
  var overlay = document.getElementById('syncOverlay');
  btn.disabled = true; btn.classList.add('syncing');
  overlay.classList.add('open');
  document.getElementById('syncMsg').textContent = 'Fetching from Google Sheets…';
  var fd = new FormData(); fd.append('id', SHEET_ID); fd.append('tabs', 'games');
  fetch(APP_BASE + 'push/pushSheetUpdate.php', { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function() { window.location.reload(); })
    .catch(function() {
      document.getElementById('syncMsg').textContent = 'Error — could not reach server.';
      setTimeout(function() { overlay.classList.remove('open'); btn.disabled = false; btn.classList.remove('syncing'); }, 2000);
    });
}

// ── Add game dialog ───────────────────────────────────────────────────────────

var _comboOptions = [];
var _comboHighlight = -1;

function openAddDialog() {
  _comboOptions = GAMES_RAW.map(function(g) { return (g.Name||'').trim(); }).filter(function(n) { return n && !isActive(n); });
  document.getElementById('gameComboInput').value = '';
  document.getElementById('addErr').style.display = 'none';
  document.getElementById('addBtn').disabled = false;
  document.getElementById('addBtn').textContent = 'Add Game';
  document.getElementById('comboDrop').innerHTML = '';
  document.getElementById('gameCombo').classList.remove('open');
  document.getElementById('addOverlay').classList.add('open');
  setTimeout(function() { document.getElementById('gameComboInput').focus(); }, 80);
}
// ── Global Escape handler — guard if dirty ────────────────────────────────────
document.addEventListener('keydown', function(ev) {
  if (ev.key !== 'Escape') return;
  var el;
  el = document.getElementById('sessionOverlay');
  if (el.classList.contains('open')) {
    if (hasSessionData()) shakeDialog(el.querySelector('.session-dialog'));
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
function comboFilter() { renderComboOptions(document.getElementById('gameComboInput').value); document.getElementById('gameCombo').classList.add('open'); }
function renderComboOptions(q) {
  var drop = document.getElementById('comboDrop');
  var filtered = q.trim()
    ? _comboOptions.filter(function(n) { return n.toLowerCase().indexOf(q.toLowerCase()) !== -1; })
    : _comboOptions;
  _comboHighlight = -1;
  if (!filtered.length) { drop.innerHTML = q.trim() ? '<div class="combo-empty">New game: "' + esc(q.trim()) + '"</div>' : '<div class="combo-empty">All games already tracked, or type a new name.</div>'; return; }
  drop.innerHTML = filtered.map(function(n) { return '<div class="combo-option" onmousedown="comboSelect(\'' + esc(n) + '\')">' + esc(n) + '</div>'; }).join('');
}
function comboSelect(name) { document.getElementById('gameComboInput').value = name; document.getElementById('gameCombo').classList.remove('open'); }
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
  if (isActive(name)) { err.textContent = '"' + name + '" is already tracked.'; err.style.display = 'block'; return; }
  btn.disabled = true; btn.textContent = 'Adding…'; err.style.display = 'none';
  var fd = new FormData(); fd.append('id', SHEET_ID); fd.append('game', name);
  fetch(APP_BASE + 'push/createDevTab.php', { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(res) {
      if (res.error) throw new Error(res.error);
      ACTIVE_KEYS[name.toLowerCase()] = true;
      extraGames.push(name);
      buildGameList();
      renderCards(document.getElementById('searchInput').value);
      closeAddDialog();
    })
    .catch(function(e) {
      err.textContent = e.message || 'Could not create tab.';
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

var _sessionGame = '';

function openSessionDialog(gameName) {
  _sessionGame = gameName;
  document.getElementById('sessionGameTitle').textContent = gameName;
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

function closeSessionDialog() {
  document.getElementById('sessionOverlay').classList.remove('open');
}

function submitSession() {
  var err = document.getElementById('sessionErr');
  var btn = document.getElementById('sessionBtn');

  // Collect testers (skip empty fields)
  var testerVals = [];
  document.querySelectorAll('#testersContainer input').forEach(function(el) {
    var v = el.value.trim();
    if (v) testerVals.push(v);
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
  div.className   = 'obs-pair';
  div.dataset.idx = idx;
  var labelsHtml = showLabels
    ? '<div class="obs-pair-labels"><label>Observation</label><label>Solution</label></div>'
    : '';
  div.innerHTML = labelsHtml +
    '<div class="obs-pair-inputs">' +
      '<textarea class="field-textarea" id="sObs-' + idx + '" rows="4"' +
        ' placeholder="What happened…"' +
        ' oninput="onObsInput(' + idx + ')"></textarea>' +
      '<textarea class="field-textarea" id="sSol-' + idx + '" rows="4"' +
        ' placeholder="How to address it…"' +
        ' oninput="onObsInput(' + idx + ')"></textarea>' +
    '</div>';
  container.appendChild(div);
  return idx;
}

function onObsInput(idx) {
  var pairs = document.querySelectorAll('#obsContainer .obs-pair');
  var last  = pairs[pairs.length - 1];
  if (!last || parseInt(last.dataset.idx) !== idx) return;
  var obs = (document.getElementById('sObs-' + idx) || {}).value || '';
  var sol = (document.getElementById('sSol-' + idx) || {}).value || '';
  if (obs.trim() || sol.trim()) addObsPair(false);
}

// ── Init ──────────────────────────────────────────────────────────────────────

buildGameList();
renderCards('');
</script>
</body>
</html>
