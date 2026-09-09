<?php
/**
 * DevBoard collaborator share page.
 * URL: /devboard/share/{24-char-hex-hash}
 * Collaborators can view sessions and submit new ones for the shared game.
 */
error_reporting(0);

$_bpFile = __DIR__ . '/../../../dotEnv.php';
if (file_exists($_bpFile)) { require_once $_bpFile; }

$_rp = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_bp = rtrim($_ENV['BASE_PATH'] ?? '', '/');
$_rp_stripped = ($_bp !== '' && str_starts_with($_rp, $_bp))
    ? substr($_rp, strlen($_bp)) : $_rp;

preg_match('#^/devboard/share/([a-f0-9]{24})/?$#', $_rp_stripped, $_m);
$_token = $_m[1] ?? '';

$_viewFile = __DIR__ . '/../../../shares/dev-collab/' . $_token . '.json';
if (!$_token || !file_exists($_viewFile)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Not found</title></head>'
       . '<body style="font-family:sans-serif;padding:3rem;text-align:center">'
       . '<h2>Share link not found or expired.</h2></body></html>';
    exit;
}

$_meta     = json_decode(file_get_contents($_viewFile), true) ?: [];
$_sheetId  = $_meta['sheet_id'] ?? '';
$_gameName = $_meta['game']     ?? '';

if (!$_sheetId || !$_gameName) { http_response_code(404); exit; }

// Base URL
$_scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host    = $_SERVER['HTTP_HOST'];
$_base    = $_scheme . '://' . $_host . $_bp . '/';

// Load people names for tester autocomplete
$_people_file  = __DIR__ . '/../../../sheets/' . $_sheetId . '/people.json';
$_people_raw   = file_exists($_people_file)
    ? (json_decode(file_get_contents($_people_file), true) ?: [])
    : [];
$_people_names = [];
foreach ($_people_raw as $_p) {
    $n = trim($_p['Name'] ?? '');
    if ($n) $_people_names[] = $n;
}

// Game image (strip =IMAGE("url") formula if present)
$_gamesFile = __DIR__ . '/../../../sheets/' . $_sheetId . '/games.json';
$_gameImage = '';
if (file_exists($_gamesFile)) {
    $_games = json_decode(file_get_contents($_gamesFile), true) ?: [];
    foreach ($_games as $_g) {
        if (strcasecmp(trim($_g['Name'] ?? ''), $_gameName) === 0) {
            $_raw = trim($_g['Image URL'] ?? $_g['Image'] ?? $_g['ImageURL'] ?? '');
            if (preg_match('/^=IMAGE\("([^"]*)"\)$/i', $_raw, $_im)) {
                $_gameImage = $_im[1];
            } elseif ($_raw) {
                $_gameImage = $_raw;
            }
            break;
        }
    }
}

// Game public page link (only shown if the page token file exists)
$_gameToken    = substr(md5($_sheetId . '|game|' . $_gameName), 0, 24);
$_gameViewFile = __DIR__ . '/../../../shares/pitch-game-view/' . $_gameToken . '.json';
$_gameUrl      = file_exists($_gameViewFile) ? ($_base . 'game/' . $_gameToken) : '';

function _ds_e(string $s): string { return htmlspecialchars($s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= _ds_e($_base) ?>" />
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= _ds_e($_gameName) ?> — DevBoard</title>
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2') format('woff2'),url('fonts/DINBlack.ttf'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf'); }

*, *::before, *::after { box-sizing:border-box; }
html, body { margin:0; padding:0; background:#f2f5f8; color:#1a1a2e; min-height:100vh; }

/* ── Top bar ── */
.top-bar { background:#1a1a2e; color:#fff; padding:0 1rem; }
.top-bar-inner { max-width:860px; margin:0 auto; display:flex; align-items:center; gap:.75rem; min-height:48px; }
.top-bar h1 { font-family:'DINBlack',sans-serif; font-size:.9rem; letter-spacing:.04em; text-transform:uppercase; margin:0; cursor:pointer; }
.top-bar .sep { opacity:.3; font-size:.85rem; }
.top-bar .game-label { font-family:'DINRegular',sans-serif; font-size:.82rem; color:rgba(255,255,255,.65); }
.top-bar .collab-badge {
  margin-left:auto; font-family:'DINBlack',sans-serif; font-size:.62rem; text-transform:uppercase; letter-spacing:.07em;
  background:rgba(255,255,255,.12); color:rgba(255,255,255,.65); border:1px solid rgba(255,255,255,.2);
  border-radius:999px; padding:.2rem .65rem;
}

/* ── Page wrapper ── */
.page { max-width:860px; margin:0 auto; padding:1.25rem 1rem 3rem; }

/* ── Game card ── */
.game-header {
  background:#1a5f7a; color:#fff;
  border-radius:10px 10px 0 0; padding:.65rem 1rem;
  display:flex; align-items:center; gap:.5rem;
}
.game-title { font-family:'DINBlack',sans-serif; font-size:.95rem; letter-spacing:.03em; flex:1; }
.header-btns { display:flex; align-items:center; gap:.5rem; }
.add-session-btn {
  font-family:'DINBlack',sans-serif; font-size:.7rem;
  text-transform:uppercase; letter-spacing:.07em;
  background:#fff; color:#1a5f7a;
  border:none; border-radius:6px;
  padding:.3rem .75rem; cursor:pointer;
  transition:background .15s, color .15s;
}
.add-session-btn:hover { background:#e8f4f8; }
.page-link-btn {
  font-family:'DINBlack',sans-serif; font-size:.7rem;
  text-transform:uppercase; letter-spacing:.07em;
  background:transparent; color:#fff;
  border:1.5px solid rgba(255,255,255,.45); border-radius:6px;
  padding:.28rem .65rem; cursor:pointer; text-decoration:none;
  display:inline-flex; align-items:center; gap:.3rem;
  transition:background .15s, border-color .15s;
}
.page-link-btn:hover { background:rgba(255,255,255,.15); border-color:rgba(255,255,255,.7); }

/* ── Game image ── */
.game-image-wrap { background:#fff; border-left:1px solid #d8eaf2; border-right:1px solid #d8eaf2; text-align:center; }
.game-image-wrap img { max-width:100%; max-height:300px; display:block; margin:0 auto; object-fit:contain; }

/* ── Sessions list ── */
.sessions-wrap { background:#fff; border-radius:0 0 10px 10px; overflow:hidden; border:1px solid #d8eaf2; border-top:none; }

.session-block { border-top:1px solid #e8f0f4; }
.session-block:first-child { border-top:none; }
.session-header {
  display:flex; flex-direction:column; gap:.28rem;
  padding:.75rem 1rem .65rem;
  background:#f0f7fb; border-bottom:1px solid #d8eaf2;
  cursor:pointer; user-select:none; -webkit-user-select:none;
  -webkit-touch-callout:none;
}
.session-header:hover { background:#e6f2f8; }
.session-header-row { display:flex; align-items:center; gap:.55rem; }
.session-type { font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; }
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
  background:#1a5f7a; color:#fff; border:none; border-radius:5px;
  cursor:pointer; flex-shrink:0; line-height:1.4;
}
.session-edit-btn:hover { background:#134d63; }
@media (hover: hover) {
  .session-header:hover .session-edit-btn { display:inline-flex; align-items:center; }
}
/* Always show Edit on touch devices (no hover) */
@media (hover: none) {
  .session-edit-btn { display:inline-flex; align-items:center; }
}
.session-testers-line { font-family:'DINRegular',sans-serif; font-size:.72rem; color:#888; font-style:italic; padding-left:.05rem; }
.session-body-wrap { display:grid; grid-template-rows:0fr; transition:grid-template-rows .22s ease; }
.session-block.open .session-body-wrap { grid-template-rows:1fr; }
.session-body { overflow:hidden; min-height:0; }
.obs-table { width:100%; border-collapse:collapse; }
.obs-table td { padding:.45rem 1rem; font-size:.8rem; line-height:1.5; vertical-align:top; border-bottom:1px solid #f0f4f8; }
.obs-table tr:last-child td { border-bottom:none; }
.obs-table .td-obs { width:50%; color:#222; }
.obs-table .td-sol { width:50%; color:#1a5f7a; border-left:1px solid #d8eaf2; }
.obs-table .td-sol:empty::after { content:'—'; color:#e0e0e0; }

.dev-empty { padding:2rem 1rem; text-align:center; font-family:'DINRegular',sans-serif; font-size:.85rem; color:#aaa; }

/* ── Overlay & session dialog ── */
.overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.45); z-index:200; align-items:center; justify-content:center; padding:1rem; }
.overlay.open { display:flex; }
.session-dialog {
  background:#fff; border-radius:12px;
  padding:1.5rem; width:min(680px,96vw);
  box-shadow:0 8px 32px rgba(0,0,0,.22);
  display:flex; flex-direction:column; gap:1.1rem;
  max-height:92vh; overflow-y:auto;
}
.session-dialog h2 { font-family:'DINBlack',sans-serif; font-size:.95rem; text-transform:uppercase; letter-spacing:.07em; color:#1a5f7a; margin:0; }
.session-dialog h2 span { color:#1a1a2e; }
.session-meta-wrap { display:flex; flex-direction:column; gap:.9rem; }
.session-meta-left { display:flex; flex-direction:column; gap:.75rem; }
.session-meta-row  { display:grid; grid-template-columns:1fr 1fr; gap:.75rem .9rem; }
.session-people { width:100%; }
@media (min-width:769px) {
  .session-meta-wrap { flex-direction:row; align-items:flex-start; }
  .session-meta-left { flex:1; min-width:0; }
  .session-people { width:190px; flex-shrink:0; }
}
@media (max-width:768px) {
  #sessionOverlay { align-items:flex-end; padding:0; }
  .session-dialog {
    width:100vw; max-width:100vw; border-radius:16px 16px 0 0;
    margin-top:auto; padding:1.25rem 1rem 1.5rem;
    max-height:94dvh;
  }
  .obs-pair-inputs { grid-template-columns:1fr; }
  .obs-pair-labels label:last-child { display:none; }
  .field-input, .field-textarea { font-size:1rem; }
}
.field-group { display:flex; flex-direction:column; gap:.3rem; }
.field-group label { font-family:'DINBlack',sans-serif; font-size:.68rem; text-transform:uppercase; letter-spacing:.07em; color:#888; }
.field-input {
  display:block; width:100%; padding:.5rem .7rem;
  font-family:'DINRegular',sans-serif; font-size:.85rem; color:#111;
  border:1.5px solid #d0d8e0; border-radius:6px; outline:none;
  background:#fafbfc; transition:border-color .15s;
}
.field-input:focus { border-color:#1a5f7a; background:#fff; }
select.field-input { height:2.45rem; -webkit-appearance:none; appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M1 1l4 4 4-4' stroke='%23888' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right .7rem center; padding-right:2rem; }
.field-sep { border:none; border-top:1px solid #e8edf0; margin:.1rem 0; }
.field-textarea {
  display:block; width:100%; padding:.6rem .75rem;
  font-family:'DINRegular',sans-serif; font-size:.85rem; color:#111; line-height:1.5;
  border:2px solid #1a1a2e; border-radius:6px; outline:none;
  background:#fff; resize:none; overflow:hidden; transition:border-color .15s;
}
.field-textarea::placeholder { color:#c4cdd8; font-style:italic; }
.field-input::placeholder { color:#c4cdd8; }
.field-textarea:focus { border-color:#1a5f7a; }
.obs-pair-empty .field-textarea { border:1.5px solid #d0d8e0; background:#fafbfc; }
.obs-pair-empty .field-textarea:focus { border-color:#1a5f7a; background:#fff; }
.dialog-actions { display:flex; justify-content:flex-end; gap:.6rem; }
.dialog-err { font-size:.78rem; color:#c0392b; display:none; }
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
.obs-kbd-hint {
  display:none; font-family:'DINRegular',sans-serif; font-size:.7rem; color:#c8d0d8; user-select:none; margin-right:auto;
}
@media (hover:hover) { .obs-kbd-hint { display:block; } }
/* Combo box */
.combo-wrap { position:relative; }
.combo-input { display:block; width:100%; padding:.6rem .8rem; font-family:'DINRegular',sans-serif; font-size:.88rem; color:#111; border:1.5px solid #ccc; border-radius:7px; outline:none; background:#fff; transition:border-color .15s; }
.combo-input:focus { border-color:#1a5f7a; }
.combo-dropdown { display:none; position:absolute; left:0; right:0; top:calc(100% + 2px); background:#fff; border:1.5px solid #1a5f7a; border-radius:7px; max-height:200px; overflow-y:auto; z-index:50; box-shadow:0 4px 16px rgba(0,0,0,.12); }
.combo-wrap.open .combo-dropdown { display:block; }
.combo-option { padding:.5rem .8rem; font-family:'DINRegular',sans-serif; font-size:.85rem; cursor:pointer; color:#222; }
.combo-option:hover, .combo-option.highlighted { background:#e8f4f8; color:#1a5f7a; }
.combo-empty { padding:.5rem .8rem; font-size:.8rem; color:#aaa; font-style:italic; }
.tester-row { margin-bottom:.4rem; }
.tester-row:last-child { margin-bottom:0; }
/* Obs pairs */
.obs-pair { margin-bottom:.75rem; }
.obs-pair:last-child { margin-bottom:0; }
.obs-pair-labels { display:grid; grid-template-columns:1fr 1fr; gap:.9rem; margin-bottom:.3rem; }
.obs-pair-labels label { font-family:'DINBlack',sans-serif; font-size:.68rem; text-transform:uppercase; letter-spacing:.07em; color:#888; }
.obs-pair-inputs { display:grid; grid-template-columns:1fr 1fr; gap:.9rem; }
.obs-obs-col { display:flex; flex-direction:column; min-width:0; }
.obs-ta-wrap { position:relative; }
.obs-ta-wrap .field-textarea { padding-right:2.1rem; }
.obs-ta-wrap.has-obs-text .obs-img-btn { display:none; }
.obs-img-btn { position:absolute; top:.35rem; right:.35rem; background:rgba(255,255,255,.88); border:1px solid #d0d8e4; border-radius:5px; padding:.22rem .26rem; cursor:pointer; color:#99a; line-height:1; z-index:2; backdrop-filter:blur(2px); transition:color .15s,background .15s,border-color .15s; }
.obs-img-btn:hover { color:#1a5f7a; background:#fff; border-color:#a0b8c8; }
.obs-img-preview img { width:100%; border-radius:6px; border:1px solid #dce8f0; display:block; }
/* Loading / success states */
.loading-msg { text-align:center; padding:2rem 1rem; font-family:'DINRegular',sans-serif; font-size:.85rem; color:#aaa; }
.success-banner { display:none; background:#e8f8ef; border:1px solid #b2dfc4; border-radius:8px; padding:.75rem 1rem; font-family:'DINRegular',sans-serif; font-size:.85rem; color:#2e7a52; margin-bottom:.75rem; }
/* Search bar */
.search-bar { padding:.6rem 1rem; background:#fff; border-bottom:1px solid #e8edf2; }
.search-wrap { position:relative; max-width:480px; }
.search-wrap input { width:100%; padding:.45rem .7rem .45rem 2rem; font-family:'DINRegular',sans-serif; font-size:.82rem; border:1.5px solid #d0d8e0; border-radius:20px; outline:none; background:#f6f9fb; color:#222; transition:border-color .15s,background .15s; box-sizing:border-box; }
.search-wrap input:focus { border-color:#1a5f7a; background:#fff; }
.search-wrap input::placeholder { color:#c0ccd8; font-style:italic; }
.search-wrap .search-icon { position:absolute; left:.6rem; top:50%; transform:translateY(-50%); color:#b0b8c4; pointer-events:none; }
.search-wrap .search-clear { position:absolute; right:.55rem; top:50%; transform:translateY(-50%); background:none; border:none; color:#b0b8c4; cursor:pointer; font-size:.8rem; display:none; padding:0; line-height:1; }
.search-wrap.has-text .search-clear { display:block; }
.search-wrap.has-text input { padding-right:1.6rem; }
</style>
</head>
<body>

<div class="top-bar">
  <div class="top-bar-inner">
    <h1>DevBoard</h1>
    <span class="sep">·</span>
    <span class="game-label"><?= _ds_e($_gameName) ?></span>
    <span class="collab-badge">Collaborator View</span>
  </div>
</div>

<div class="page">
  <div class="game-header">
    <span class="game-title"><?= _ds_e($_gameName) ?></span>
    <div class="header-btns">
<?php if ($_gameUrl): ?>
      <a class="page-link-btn" href="<?= _ds_e($_gameUrl) ?>">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        Page
      </a>
<?php endif; ?>
      <button class="add-session-btn" onclick="openSessionDialog()">+ Session</button>
    </div>
  </div>

  <div class="search-bar">
    <div class="search-wrap" id="searchWrap">
      <svg class="search-icon" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <input type="text" id="searchInput" placeholder="Search sessions, people, notes…"
        oninput="onSearch()" autocomplete="off" spellcheck="false" />
      <button class="search-clear" onclick="clearSearch()">✕</button>
    </div>
  </div>
  <div class="sessions-wrap" id="sessionsWrap">
    <div class="loading-msg">Loading sessions…</div>
  </div>
</div>

<!-- Session dialog -->
<div class="overlay" id="sessionOverlay" onclick="if(event.target===this)closeSessionDialog()">
  <div class="session-dialog">
    <h2 id="sessionDialogTitle">+ Session — <span><?= _ds_e($_gameName) ?></span></h2>

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
            <label>Test Number</label>
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

    <div id="obsContainer"></div>

    <div class="dialog-err" id="sessionErr"></div>
    <div class="dialog-actions">
      <span class="obs-kbd-hint">⌘ / Ctrl + Arrow — move between fields</span>
      <button class="btn-cancel" onclick="closeSessionDialog()">Cancel</button>
      <button class="btn-primary" id="sessionBtn" onclick="submitSession()">Add Session</button>
    </div>
  </div>
</div>

<script>
<?php include __DIR__ . '/../devboard-common.js'; ?>

var APP_BASE     = document.querySelector('base').getAttribute('href');
var SHEET_ID     = <?= json_encode($_sheetId) ?>;
var GAME_NAME    = <?= json_encode($_gameName) ?>;
var PEOPLE_NAMES = <?= json_encode(array_values($_people_names), JSON_UNESCAPED_UNICODE) ?>;

// ── Utilities ────────────────────────────────────────────────────────────────
// esc, fmtDate, obsHtml, buildSessions, _sessionMatchesQuery, _filterObsByQuery
// are defined in devboard-common.js (loaded above).

function todayISO() {
  var d = new Date(); var m = String(d.getMonth()+1).padStart(2,'0'); var day = String(d.getDate()).padStart(2,'0');
  return d.getFullYear() + '-' + m + '-' + day;
}

// ── Render sessions ───────────────────────────────────────────────────────────

var _allRows     = [];
var _allSessions = [];
var _editMode      = false;
var _editOrigDate  = '';
var _editOrigEvent = '';

function renderSessions() {
  _allSessions = buildSessions(_allRows).reverse();
  var q    = (document.getElementById('searchInput') ? document.getElementById('searchInput').value : '').toLowerCase().trim();
  var sessions = q ? _allSessions.filter(function(s) { return _sessionMatchesQuery(s, q); }) : _allSessions;
  var wrap = document.getElementById('sessionsWrap');
  if (!sessions.length) {
    wrap.innerHTML = '<div class="dev-empty">' + (q ? 'No sessions match "' + esc(q) + '".' : 'No sessions yet. Click "+ Session" to log one.') + '</div>';
    return;
  }
  var html = '';
  sessions.forEach(function(s, i) {
    var allIdx = _allSessions.indexOf(s);  // index into _allSessions for edit dialog
    var typeClass = 'type-playtest';
    if (s.testnum.toLowerCase().indexOf('meeting') === 0) typeClass = 'type-meeting';
    else if (s.testnum.toLowerCase().indexOf('idea') === 0) typeClass = 'type-idea';
    html += '<div class="session-block' + (q ? ' open' : '') + '" id="sblock-' + i + '">';
    html += '<div class="session-header" onclick="toggleSession(' + i + ')">';
    html += '<div class="session-header-row">';
    if (s.testnum) html += '<span class="session-type ' + typeClass + '">' + esc(s.testnum) + '</span>';
    if (s.date)    html += '<span class="session-sep">·</span><span class="session-date">' + esc(fmtDate(s.date)) + '</span>';
    if (s.location) html += '<span class="session-sep">·</span><span class="session-location">' + esc(s.location) + '</span>';
    html += '<span class="session-count">' + s.obs.length + (s.obs.length === 1 ? ' note' : ' notes') + '</span>';
    html += '<button class="session-edit-btn" onclick="event.stopPropagation();openEditSessionDialog(' + allIdx + ')">Edit</button>';
    html += '<span class="session-chevron">▼</span>';
    html += '</div>';
    if (s.testers.length) html += '<div class="session-testers-line">' + s.testers.map(esc).join(', ') + '</div>';
    html += '</div>';  // .session-header
    // Body
    html += '<div class="session-body-wrap"><div class="session-body">';
    var visibleObs = _filterObsByQuery(s.obs, q);
    if (visibleObs.length) {
      html += '<table class="obs-table"><tbody>';
      visibleObs.forEach(function(pair) {
        html += '<tr><td class="td-obs">' + obsHtml(pair.obs) + '</td><td class="td-sol">' + esc(pair.sol) + '</td></tr>';
      });
      html += '</tbody></table>';
    }
    html += '</div></div>';  // .session-body .session-body-wrap
    html += '</div>';  // .session-block
  });
  wrap.innerHTML = html;
}

function toggleSession(idx) {
  var block = document.getElementById('sblock-' + idx);
  if (block) block.classList.toggle('open');
}

// ── Load data from server ─────────────────────────────────────────────────────

function onSearch() {
  var inp  = document.getElementById('searchInput');
  var wrap = document.getElementById('searchWrap');
  wrap.classList.toggle('has-text', inp.value.length > 0);
  renderSessions();
}
function clearSearch() {
  document.getElementById('searchInput').value = '';
  document.getElementById('searchWrap').classList.remove('has-text');
  renderSessions();
}

function loadSessions() {
  var fd = new FormData();
  fd.append('id',   SHEET_ID);
  fd.append('game', GAME_NAME);
  fetch(APP_BASE + 'push/getDevJson.php', { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(rows) {
      _allRows = Array.isArray(rows) ? rows : [];
      renderSessions();
    })
    .catch(function() {
      document.getElementById('sessionsWrap').innerHTML =
        '<div class="dev-empty">Could not load sessions. Please try again later.</div>';
    });
}

// ── Session type → auto-label ─────────────────────────────────────────────────

function nextSessionLabel(type) {
  var sessions = buildSessions(_allRows);
  var prefix   = type.toLowerCase() + ' ';
  var count    = sessions.filter(function(s) {
    return s.testnum.toLowerCase().indexOf(prefix) === 0;
  }).length;
  return type + ' ' + (count + 1);
}
function onTypeChange() {
  document.getElementById('sTestNum').value = nextSessionLabel(document.getElementById('sType').value);
}

// ── Session dialog open/close ─────────────────────────────────────────────────

function openSessionDialog() {
  _editMode = false;
  document.getElementById('sessionDialogTitle').innerHTML = '+ Session — <span>' + esc(GAME_NAME) + '</span>';
  document.getElementById('sDate').value     = todayISO();
  document.getElementById('sType').value     = 'Playtest';
  document.getElementById('sLocation').value = '';
  document.getElementById('sTestNum').value  = nextSessionLabel('Playtest');
  _testerCount = 0; _testersHL = {};
  document.getElementById('testersContainer').innerHTML = '';
  addTesterField('Select or type…');
  _obsCount = 0; _obsImages = {};
  document.getElementById('obsContainer').innerHTML = '';
  addObsPair(true);
  document.getElementById('sessionErr').style.display = 'none';
  document.getElementById('sessionBtn').disabled    = false;
  document.getElementById('sessionBtn').textContent = 'Add Session';
  document.getElementById('sessionOverlay').classList.add('open');
  setTimeout(function() {
    var firstObs = document.getElementById('sObs-0');
    if (firstObs) firstObs.focus();
  }, 80);
}

function openEditSessionDialog(idx) {
  var session = _allSessions[idx];
  if (!session) return;
  _editMode      = true;
  _editOrigDate  = session.date;
  _editOrigEvent = session.testnum;
  document.getElementById('sessionDialogTitle').innerHTML = 'Edit Session — <span>' + esc(GAME_NAME) + '</span>';
  var tn = (session.testnum || '').toLowerCase();
  var type = tn.indexOf('meeting') === 0 ? 'Meeting' : tn.indexOf('idea') === 0 ? 'Idea' : 'Playtest';
  document.getElementById('sDate').value     = session.date     || '';
  document.getElementById('sType').value     = type;
  document.getElementById('sTestNum').value  = session.testnum  || '';
  document.getElementById('sLocation').value = session.location || '';
  _testerCount = 0; _testersHL = {};
  document.getElementById('testersContainer').innerHTML = '';
  session.testers.forEach(function(t) {
    var tidx = addTesterField('Select or type…');
    document.getElementById('sTesters-' + tidx).value = t;
  });
  addTesterField('Add tester…');
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
  addObsPair(session.obs.length === 0);
  document.getElementById('sessionErr').style.display = 'none';
  document.getElementById('sessionBtn').disabled    = false;
  document.getElementById('sessionBtn').textContent = 'Save Changes';
  document.getElementById('sessionOverlay').classList.add('open');
  setTimeout(function() {
    document.querySelectorAll('#obsContainer .field-textarea').forEach(autoResize);
  }, 0);
}

function closeSessionDialog() {
  document.getElementById('sessionOverlay').classList.remove('open');
  _editMode = false;
}

// ── Submit session ────────────────────────────────────────────────────────────

function _parsePerson(raw) {
  var m = raw.match(/(\S+@\S+\.\S+)/);
  if (!m) return { name:raw.trim(), email:'' };
  var email = m[1];
  return { name:raw.replace(email,'').trim() || raw.trim(), email:email };
}

function addNewPeople(raws) {
  var known = {};
  PEOPLE_NAMES.forEach(function(n) { known[n.toLowerCase()] = true; });
  raws.forEach(function(raw) {
    var p = _parsePerson(raw);
    if (!p.name || known[p.name.toLowerCase()]) return;
    known[p.name.toLowerCase()] = true;
    PEOPLE_NAMES.push(p.name);
    var fd = new FormData();
    fd.append('id',    SHEET_ID);
    fd.append('name',  p.name);
    fd.append('email', p.email);
    fetch(APP_BASE + 'push/addPerson.php', { method:'POST', body:fd }).catch(function(){});
  });
}

function submitSession() {
  var err = document.getElementById('sessionErr');
  var btn = document.getElementById('sessionBtn');

  var testerVals = [], testerRaws = [];
  document.querySelectorAll('#testersContainer input').forEach(function(el) {
    var raw = el.value.trim();
    if (!raw) return;
    var p = _parsePerson(raw);
    el.value = p.name;
    testerVals.push(p.name);
    testerRaws.push(raw);
  });

  var obsPairs = [];
  document.querySelectorAll('#obsContainer .obs-pair').forEach(function(pair) {
    var idx = pair.dataset.idx;
    var obs = (document.getElementById('sObs-' + idx) || {}).value || '';
    var sol = (document.getElementById('sSol-' + idx) || {}).value || '';
    obs = obs.trim(); sol = sol.trim();
    if (_obsImages[idx]) obs = '=IMAGE("' + _obsImages[idx] + '")';
    if (obs || sol) obsPairs.push({ obs:obs, sol:sol });
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

  // ── Edit mode: replace existing session ───────────────────────────────────────
  if (_editMode) {
    var fd = new FormData();
    fd.append('id',         SHEET_ID);
    fd.append('game',       GAME_NAME);
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
        closeSessionDialog();
        loadSessions();
      })
      .catch(function(e) {
        err.textContent = e.message || 'Could not save. Try again.';
        err.style.display = 'block';
        btn.disabled = false; btn.textContent = 'Save Changes';
      });
    return;
  }

  var allRows = [];
  allRows.push({ date:date, event:testnum, observation:location, solution:'', type:'header' });
  testerVals.forEach(function(t) { allRows.push({ date:'', event:'', observation:t, solution:'', type:'tester' }); });
  obsPairs.forEach(function(pair) { allRows.push({ date:'', event:'', observation:pair.obs, solution:pair.sol, type:'obs' }); });

  function postRow(row) {
    var fd = new FormData();
    fd.append('id',          SHEET_ID);
    fd.append('game',        GAME_NAME);
    fd.append('date',        row.date);
    fd.append('event',       row.event);
    fd.append('observation', row.observation);
    fd.append('solution',    row.solution);
    fd.append('row_type',    row.type || '');
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
      closeSessionDialog();
      loadSessions();  // refresh from server
    })
    .catch(function(e) {
      err.textContent = e.message || 'Could not save. Try again.';
      err.style.display = 'block';
      btn.disabled = false; btn.textContent = 'Add Session';
    });
}

// ── Tester combo ──────────────────────────────────────────────────────────────

var _testerCount = 0;
var _testersHL   = {};

function addTesterField(placeholder) {
  var idx = _testerCount++;
  var container = document.getElementById('testersContainer');
  var div = document.createElement('div');
  div.className = 'tester-row';
  div.dataset.idx = idx;
  _testersHL[idx] = -1;
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
  var rows = document.querySelectorAll('#testersContainer .tester-row');
  var last = rows[rows.length - 1];
  if (last && parseInt(last.dataset.idx) === idx) {
    if (document.getElementById('sTesters-' + idx).value.trim()) addTesterField();
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

var _obsCount  = 0;
var _obsImages = {};

function addObsPair(showLabels) {
  var idx = _obsCount++;
  var container = document.getElementById('obsContainer');
  var div = document.createElement('div');
  div.className = 'obs-pair obs-pair-empty';
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

function onObsKeydown(e, idx, col) {
  if (!e.metaKey && !e.ctrlKey) return;
  var dir = e.key;
  if (dir !== 'ArrowLeft' && dir !== 'ArrowRight' && dir !== 'ArrowUp' && dir !== 'ArrowDown') return;
  e.preventDefault();
  var pairs   = Array.from(document.querySelectorAll('#obsContainer .obs-pair'));
  var pairIdx = pairs.findIndex(function(p) { return parseInt(p.dataset.idx, 10) === idx; });
  var tgtCol  = col, tgtPairI = pairIdx;
  if (dir === 'ArrowLeft'  || dir === 'ArrowRight') tgtCol   = 1 - col;
  if (dir === 'ArrowUp'   && pairIdx > 0)             tgtPairI = pairIdx - 1;
  if (dir === 'ArrowDown' && pairIdx < pairs.length - 1) tgtPairI = pairIdx + 1;
  var tgtPair = pairs[tgtPairI];
  if (!tgtPair) return;
  var tgtIdx  = parseInt(tgtPair.dataset.idx, 10);
  var tgtId   = tgtCol === 0 ? 'sObs-' + tgtIdx : 'sSol-' + tgtIdx;
  var tgtEl   = document.getElementById(tgtId);
  if (tgtEl) tgtEl.focus();
}

function toggleObsImgBtn(idx) {
  var ta   = document.getElementById('sObs-' + idx);
  var wrap = ta ? ta.closest('.obs-ta-wrap') : null;
  if (!wrap) return;
  wrap.classList.toggle('has-obs-text', ta.value.trim().length > 0);
}

function _showObsImage(idx, url) {
  _obsImages[idx] = url;
  var ta   = document.getElementById('sObs-' + idx);
  var wrap = ta ? ta.closest('.obs-ta-wrap') : null;
  var pv   = document.getElementById('sImgPreview-' + idx);
  if (ta)   ta.style.display = 'none';
  if (wrap) wrap.classList.remove('has-obs-text');
  if (pv) { pv.style.display = 'block'; pv.innerHTML = '<img src="' + esc(url) + '" alt="observation image">'; }
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

// ── Bootstrap ─────────────────────────────────────────────────────────────────

loadSessions();
</script>
</body>
</html>
