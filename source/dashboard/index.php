<?php
$_rp = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('#^(.*?)(?:sheets/)?[A-Za-z0-9_\-]+/dashboard/?$#', $_rp, $_bm);
$_base = (isset($_bm[1]) && $_bm[1] !== '') ? $_bm[1] : '/';
if (substr($_base, -1) !== '/') $_base .= '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Pitch Board</title>
  <style>
    @font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2') format('woff2'),url('fonts/DINBlack.ttf'); }
    @font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf'); }
    *, *::before, *::after { box-sizing: border-box; }
    body { margin:0; background:#f3f2ef; font-family:'DINRegular',Arial,sans-serif; color:#111; }

    /* ── Top bar ─────────────────────────────────────── */
    .top-bar {
      background:#1a1a2e; color:#fff;
      padding:.75rem 1.25rem;
      display:flex; align-items:center; gap:.75rem;
      flex-wrap:wrap;
      position:sticky; top:0; z-index:100;
    }
    .top-bar-left { flex:1; min-width:0; }
    .top-bar h1 { font-family:'DINBlack',sans-serif; font-size:1rem; margin:0; letter-spacing:.03em; }
    .top-bar .sub { font-size:.73rem; opacity:.6; margin:0; letter-spacing:.01em; }

    @media (max-width:500px) {
      .top-bar-left { flex-basis:100%; }
    }

    /* ── View toggle ─────────────────────────────────── */
    .view-toggle {
      display:flex; background:rgba(255,255,255,.12);
      border-radius:6px; overflow:hidden; flex-shrink:0;
    }
    .view-toggle button {
      font-family:'DINBlack',sans-serif; font-size:.7rem;
      text-transform:uppercase; letter-spacing:.06em;
      background:none; color:rgba(255,255,255,.65);
      border:none; padding:.38rem .85rem; cursor:pointer;
      transition:background .15s, color .15s;
    }
    .view-toggle button.active { background:#fff; color:#1a1a2e; }

    /* ── Top-bar buttons (Share / Sync) ─────────────── */
    .share-btn, .sync-btn {
      display:inline-flex; align-items:center; gap:.35rem;
      font-family:'DINBlack',sans-serif; font-size:.7rem;
      text-transform:uppercase; letter-spacing:.06em;
      background:rgba(255,255,255,.15); color:#fff;
      border:1px solid rgba(255,255,255,.3); border-radius:6px;
      padding:.38rem .8rem; cursor:pointer;
      transition:background .15s; white-space:nowrap; flex-shrink:0;
    }
    .share-btn:hover, .sync-btn:hover { background:rgba(255,255,255,.25); }
    .sync-btn:disabled { opacity:.5; cursor:default; }
    .sync-btn:disabled:hover { background:rgba(255,255,255,.15); }
    @keyframes spin { to { transform:rotate(360deg); } }
    .sync-icon { display:inline-block; }
    .sync-btn.syncing .sync-icon { animation:spin .8s linear infinite; }

    /* ── Summary bar ─────────────────────────────────── */
    .summary-bar {
      display:flex; gap:.5rem; flex-wrap:wrap;
      padding:.75rem 1.25rem .4rem;
    }
    .pill {
      display:inline-flex; align-items:center; gap:.3rem;
      font-family:'DINBlack',sans-serif; font-size:.7rem;
      text-transform:uppercase; letter-spacing:.05em;
      padding:.25rem .65rem; border-radius:999px;
    }
    .pill-total     { background:#1a1a2e; color:#fff; }
    .pill-pitched   { background:#e2e8f0; color:#334155; cursor:pointer; border:none; transition:opacity .15s,box-shadow .15s; }
    .pill-int       { background:#dcfce7; color:#166534; cursor:pointer; border:none; transition:opacity .15s,box-shadow .15s; }
    .pill-passed    { background:#fee2e2; color:#991b1b; cursor:pointer; border:none; transition:opacity .15s,box-shadow .15s; }
    .pill-signed    { background:#7c3aed; color:#fff; cursor:pointer; border:none; transition:opacity .15s,box-shadow .15s; }
    .pill-published { background:#0369a1; color:#fff; cursor:pointer; border:none; transition:opacity .15s,box-shadow .15s; }
    .pill-pitched:hover, .pill-int:hover, .pill-passed:hover,
    .pill-signed:hover, .pill-published:hover { opacity:.85; }
    .pill-pitched.filter-active   { box-shadow:0 0 0 2px #fff, 0 0 0 4px #94a3b8; }
    .pill-int.filter-active       { box-shadow:0 0 0 2px #fff, 0 0 0 4px #16a34a; }
    .pill-passed.filter-active    { box-shadow:0 0 0 2px #fff, 0 0 0 4px #dc2626; }
    .pill-signed.filter-active    { box-shadow:0 0 0 2px #fff, 0 0 0 4px #7c3aed; }
    .pill-published.filter-active { box-shadow:0 0 0 2px #fff, 0 0 0 4px #0369a1; }

    /* ── Search + sort ───────────────────────────────── */
    .search-bar { padding:.4rem 1.25rem .6rem; display:flex; align-items:center; gap:.6rem; }
    .search-bar input {
      flex:1; max-width:380px;
      font-family:'DINRegular',sans-serif; font-size:.82rem;
      border:1px solid #ccc; border-radius:6px;
      padding:.42rem .8rem; outline:none;
      background:#fff; color:#111;
    }
    .search-bar input:focus { border-color:#1a1a2e; }
    .sort-toggle {
      display:flex; background:#e2e8f0;
      border-radius:6px; overflow:hidden; flex-shrink:0;
    }
    .sort-toggle button {
      font-family:'DINBlack',sans-serif; font-size:.68rem;
      text-transform:uppercase; letter-spacing:.05em;
      background:none; color:#64748b;
      border:none; padding:.38rem .75rem; cursor:pointer;
      transition:background .15s, color .15s;
    }
    .sort-toggle button.active { background:#1a1a2e; color:#fff; }

    /* ── Content ─────────────────────────────────────── */
    .content { padding:.4rem 1.25rem 3rem; max-width:900px; }

    /* ── Card ────────────────────────────────────────── */
    .card {
      background:#fff; border-radius:10px;
      box-shadow:0 1px 4px rgba(0,0,0,.08);
      margin-bottom:.9rem; overflow:hidden;
    }
    .card-header {
      background:#1a1a2e; color:#fff;
      padding:.55rem 1rem;
      display:flex; align-items:center; gap:.5rem;
      cursor:pointer; user-select:none;
    }
    .card-header:hover { background:#252545; }
    .card-title {
      font-family:'DINBlack',sans-serif; font-size:.9rem;
      letter-spacing:.03em; flex:1; min-width:0;
      white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
    }
    .card-badges { display:flex; align-items:center; gap:.35rem; flex-shrink:0; }
    .game-designers {
      font-family:'DINRegular',sans-serif; font-size:.75rem;
      opacity:.65; font-weight:normal; letter-spacing:0;
    }
    .card-chevron {
      font-size:.65rem; opacity:.55; flex-shrink:0;
      transition:transform .2s; transform:rotate(0deg);
    }
    .card.open .card-chevron { transform:rotate(180deg); }
    .card-body { display:none; }
    .card.open .card-body { display:block; }

    /* ── Status badges ───────────────────────────────── */
    .badge {
      font-family:'DINBlack',sans-serif; font-size:.65rem;
      text-transform:uppercase; letter-spacing:.06em;
      padding:.18rem .55rem; border-radius:999px; white-space:nowrap;
    }
    .badge-interested  { background:#dcfce7; color:#166534; }
    .badge-passed      { background:#fee2e2; color:#991b1b; }
    .badge-pitched     { background:#e2e8f0; color:#334155; }
    .badge-signed      { background:#7c3aed; color:#fff; }
    .badge-published   { background:#0369a1; color:#fff; }
    .badge-age-6mo     { background:#ef4444; color:#fff; }
    .badge-age-3mo     { background:#f59e0b; color:#fff; }

    /* ── Sub-group ───────────────────────────────────── */
    .sub-group { border-top:1px solid #f0f0f0; }
    .sub-group:first-child { border-top:none; }
    .sub-label {
      font-family:'DINBlack',sans-serif; font-size:.72rem;
      color:#666; text-transform:uppercase; letter-spacing:.05em;
      padding:.45rem 1rem .2rem 1.6rem;
      display:flex; align-items:center; gap:.5rem;
    }
    .contact-email-btn {
      display:inline-flex; align-items:center;
      font-family:'DINBlack',sans-serif; font-size:.62rem;
      text-transform:uppercase; letter-spacing:.05em;
      color:#1a1a2e; background:#e8e8f0; border:none;
      border-radius:999px; padding:.12rem .5rem;
      text-decoration:none; cursor:pointer; white-space:nowrap;
      transition:background .15s;
    }
    .contact-email-btn:hover { background:#1a1a2e; color:#fff; }

    /* ── Entry row ───────────────────────────────────── */
    .entry-row {
      display:grid;
      grid-template-columns: 88px 88px auto 1fr;
      align-items:center; gap:.5rem;
      padding:.32rem 1rem .32rem 2rem;
      border-top:1px solid #f5f5f5;
      font-size:.8rem;
    }
    .entry-date  { color:#777; white-space:nowrap; }
    .entry-event { color:#999; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .entry-status { justify-self:start; }
    .entry-notes { color:#444; line-height:1.42; min-width:0;
      overflow:hidden; white-space:nowrap; text-overflow:ellipsis; }

    /* ── Empty / loading ─────────────────────────────── */
    .empty { padding:3rem; text-align:center; color:#999; font-size:.88rem; }

    /* ── Dashboard view ──────────────────────────────── */
    .db-stats { display:flex; flex-wrap:wrap; gap:.65rem; margin-bottom:1rem; }
    .db-stat {
      background:#fff; border-radius:8px;
      box-shadow:0 1px 4px rgba(0,0,0,.08);
      padding:.85rem 1.1rem; flex:1; min-width:110px;
    }
    .db-stat-value {
      font-family:'DINBlack',sans-serif; font-size:1.55rem; color:#1a1a2e; line-height:1;
    }
    .db-stat-label {
      font-size:.67rem; color:#888; text-transform:uppercase;
      letter-spacing:.05em; margin-top:.3rem;
    }
    .db-charts {
      display:grid; grid-template-columns:1fr 1fr; gap:.65rem; margin-bottom:.65rem;
    }
    .db-chart-card {
      background:#fff; border-radius:8px;
      box-shadow:0 1px 4px rgba(0,0,0,.08);
      padding:1rem 1.1rem;
    }
    .db-chart-card h3 {
      font-family:'DINBlack',sans-serif; font-size:.75rem; margin:0 0 .75rem;
      text-transform:uppercase; letter-spacing:.05em; color:#555;
    }
    .db-chart-wide { grid-column:1 / -1; }
    @media (max-width:600px) { .db-charts { grid-template-columns:1fr; } }

    /* ── Share dialog ────────────────────────────────── */
    .share-overlay {
      display:none; position:fixed; inset:0;
      background:rgba(0,0,0,.45); z-index:1000;
      align-items:center; justify-content:center;
    }
    .share-overlay.open { display:flex; }
    .share-dialog {
      background:#fff; border-radius:10px;
      padding:1.4rem; width:min(460px,92vw);
      box-shadow:0 8px 32px rgba(0,0,0,.22);
    }
    .share-dialog h2 { font-family:'DINBlack',sans-serif; font-size:.95rem; margin:0 0 .25rem; }
    .share-dialog p  { font-size:.8rem; color:#666; margin:0 0 .9rem; }
    .share-url-row   { display:flex; gap:.45rem; align-items:stretch; }
    .share-url-input {
      flex:1; font-size:.75rem; border:1px solid #ccc;
      border-radius:6px; padding:.45rem .7rem;
      color:#333; background:#f8f8f8;
      overflow:hidden; text-overflow:ellipsis;
      white-space:nowrap; outline:none;
    }
    .copy-btn {
      font-family:'DINBlack',sans-serif; font-size:.72rem;
      text-transform:uppercase; letter-spacing:.05em;
      background:#1a1a2e; color:#fff; border:none;
      border-radius:6px; padding:.45rem .9rem;
      cursor:pointer; white-space:nowrap;
      transition:background .15s; flex-shrink:0;
    }
    .copy-btn:hover  { background:#2d2d50; }
    .copy-btn.copied { background:#16a34a; }
    .share-close {
      display:block; margin-top:.9rem; text-align:right;
      font-size:.78rem; color:#999; cursor:pointer;
      background:none; border:none; font-family:'DINRegular',sans-serif;
    }
    .share-close:hover { color:#333; }

    /* ── Sync dialog ─────────────────────────────────── */
    .sync-overlay {
      display:none; position:fixed; inset:0;
      background:rgba(0,0,0,.45); z-index:1000;
      align-items:center; justify-content:center;
    }
    .sync-overlay.open { display:flex; }
    .sync-dialog {
      background:#fff; border-radius:10px;
      padding:1.4rem; width:min(480px,92vw);
      box-shadow:0 8px 32px rgba(0,0,0,.22);
      display:flex; flex-direction:column; gap:.75rem;
    }
    .sync-dialog h2 {
      font-family:'DINBlack',sans-serif; font-size:.95rem; margin:0;
    }
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
    .sync-done-btn {
      font-family:'DINBlack',sans-serif; font-size:.72rem;
      text-transform:uppercase; letter-spacing:.05em;
      background:#1a1a2e; color:#fff; border:none;
      border-radius:6px; padding:.45rem .9rem;
      cursor:pointer; align-self:flex-end;
      transition:background .15s;
    }
    .sync-done-btn:hover { background:#2d2d50; }
    .sync-done-btn:disabled { opacity:.45; cursor:default; }
  </style>
</head>
<body>

<!-- Top bar -->
<div class="top-bar">
  <div class="top-bar-left">
    <h1>Pitch Board</h1>
    <p class="sub" id="subTitle">Loading…</p>
  </div>
  <div class="view-toggle">
    <button id="btnDashboard"              onclick="setView('dashboard')">Dashboard</button>
    <button id="btnGame"      class="active" onclick="setView('game')">Games</button>
    <button id="btnPublisher"               onclick="setView('publisher')">Publishers</button>
  </div>
  <button class="sync-btn" id="syncBtn" onclick="syncData()"><span class="sync-icon">&#8635;</span> Sync</button>
  <button class="share-btn" onclick="openShare()">&#8679; Share</button>
</div>

<!-- Share dialog -->
<div class="share-overlay" id="shareOverlay" onclick="if(event.target===this)closeShare()">
  <div class="share-dialog">
    <h2>Share Pitches</h2>
    <p>Send this link to share your pitch data.</p>
    <div class="share-url-row">
      <input class="share-url-input" id="shareUrl" type="text" readonly />
      <button class="copy-btn" id="copyBtn" onclick="copyUrl()">Copy</button>
    </div>
    <button class="share-close" onclick="closeShare()">Close</button>
  </div>
</div>

<!-- Sync dialog -->
<div class="sync-overlay" id="syncOverlay">
  <div class="sync-dialog">
    <h2 id="syncDialogTitle">Syncing…</h2>
    <div class="sync-log" id="syncLog"></div>
    <button class="sync-done-btn" id="syncDoneBtn" disabled onclick="closeSyncDialog()">Done</button>
  </div>
</div>

<div class="summary-bar" id="summaryBar"></div>
<div class="search-bar" id="searchBar">
  <input type="text" id="searchInput" placeholder="Search games, publishers, contacts…" oninput="applySearch()" />
  <div class="sort-toggle">
    <button id="btnSortDate"  class="active" onclick="setSort('date')">Date</button>
    <button id="btnSortAlpha"            onclick="setSort('alpha')">A–Z</button>
  </div>
</div>
<div class="content" id="content"><div class="empty">Loading…</div></div>

<script>
// ── Routing ───────────────────────────────────────────
function getSheetId() {
  var parts = window.location.pathname.split('/').filter(Boolean);
  var idx = parts.indexOf('sheets');
  if (idx >= 0 && parts[idx+1]) return parts[idx+1];
  var di = parts.lastIndexOf('dashboard');
  if (di > 0) return parts[di-1];
  var m = window.location.search.match(/[?&]id=([^&]+)/);
  return m ? m[1] : '';
}
var sheet_Id = getSheetId();
var APP_BASE = document.querySelector('base').getAttribute('href');
var BASE     = APP_BASE + 'sheets/' + sheet_Id + '/';

// ── State ─────────────────────────────────────────────
var currentView     = 'game';
var currentSort     = 'date';   // 'date' | 'alpha'
var allPitches      = [];
var filteredPitches = [];
var searchQuery     = '';
var activeFilters   = {};   // keys: 'signed', 'published'
var peopleIndex     = {};   // "Name|Company" → email
var gamesIndex      = {};   // Game name → {Designers, …}
var totalGameCount  = 0;
var totalPubCount   = 0;

// ── Helpers ───────────────────────────────────────────
function statusClass(s) {
  s = (s||'').toLowerCase();
  if (s==='signed')     return 'signed';
  if (s==='interested') return 'interested';
  if (s==='passed')     return 'passed';
  return 'pitched';
}

// A game is signed if games.json has a Date Signed or Status=Signed, or any pitch entry has Status=Signed
function isGameSigned(gameName, allEntries) {
  var info = gamesIndex[gameName] || {};
  if ((info['Date Signed'] || '').trim()) return true;
  if ((info['Status'] || '').toLowerCase() === 'signed') return true;
  return (allEntries || []).some(function(e) {
    return (e.Status || '').toLowerCase() === 'signed' ||
           (e['Date Signed'] || '').trim();
  });
}

// A game is published if games.json has a Date Published or Status=Published, or any pitch entry has Status=Published
function isGamePublished(gameName, allEntries) {
  var info = gamesIndex[gameName] || {};
  if ((info['Date Published'] || '').trim()) return true;
  if ((info['Status'] || '').toLowerCase() === 'published') return true;
  return (allEntries || []).some(function(e) {
    return (e.Status || '').toLowerCase() === 'published';
  });
}

function latestEntry(entries) {
  return entries.slice().sort(function(a,b){ return new Date(b.Date)-new Date(a.Date); })[0] || {};
}

function ageTag(entries) {
  // Find most recent Interested entry
  var ints = entries.filter(function(e){ return (e.Status||'').toLowerCase()==='interested'; });
  if (!ints.length) return '';
  var latest = latestEntry(ints);
  if (!latest.Date) return '';
  var d = new Date(latest.Date), now = new Date();
  var months = (now.getFullYear()-d.getFullYear())*12 + (now.getMonth()-d.getMonth());
  if (months >= 6) return '<span class="badge badge-age-6mo">6mo+</span>';
  if (months >= 3) return '<span class="badge badge-age-3mo">3mo+</span>';
  return '';
}

// Age tag for game header: checks each non-passed publisher independently;
// shows 3mo+ and/or 6mo+ if any publisher falls into that bracket
function gameAgeTag(pubMap) {
  var now = new Date();
  var has3mo = false, has6mo = false;
  Object.keys(pubMap).forEach(function(p) {
    var pubEntries = [];
    Object.keys(pubMap[p]).forEach(function(c){
      pubMap[p][c].forEach(function(e){ pubEntries.push(e); });
    });
    if (!pubEntries.length) return;
    var latest = latestEntry(pubEntries);
    if ((latest.Status||'').toLowerCase() === 'passed') return;
    if (!latest.Date) return;
    var months = (now.getFullYear() - new Date(latest.Date).getFullYear()) * 12
               + (now.getMonth()    - new Date(latest.Date).getMonth());
    if (months >= 6) has6mo = true;
    else if (months >= 3) has3mo = true;
  });
  if (has6mo) return '<span class="badge badge-age-6mo">6mo+</span>';
  if (has3mo) return '<span class="badge badge-age-3mo">3mo+</span>';
  return '';
}

function escHtml(s) {
  return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Build summary bar ─────────────────────────────────
function buildSummary(pitches) {
  // Build game→publisher→entries map for pair-level counting
  var gameEntryMap = {}, gamePubMap = {}, pubSet = {};
  pitches.forEach(function(r) {
    var g = r.Game || '(Unknown)';
    var p = r.Publisher || '(Unknown)';
    if (r.Publisher) pubSet[r.Publisher] = 1;
    if (!gameEntryMap[g]) gameEntryMap[g] = [];
    gameEntryMap[g].push(r);
    if (!gamePubMap[g]) gamePubMap[g] = {};
    if (!gamePubMap[g][p]) gamePubMap[g][p] = [];
    gamePubMap[g][p].push(r);
  });

  // Also include games in gamesIndex that may not have pitches yet
  Object.keys(gamesIndex).forEach(function(n){ if (!gameEntryMap[n]) gameEntryMap[n] = []; });

  // Count per game-publisher pair by latest status
  var pairCounts = {pitched:0, interested:0, passed:0};
  Object.keys(gamePubMap).forEach(function(g) {
    Object.keys(gamePubMap[g]).forEach(function(p) {
      var latest = latestEntry(gamePubMap[g][p]);
      var s = (latest.Status||'').toLowerCase();
      if (s === 'interested') pairCounts.interested++;
      else if (s === 'passed') pairCounts.passed++;
      else if (s) pairCounts.pitched++;
    });
  });

  // Count signed and published at the game level
  var signedGames = 0, publishedGames = 0;
  Object.keys(gameEntryMap).forEach(function(name) {
    if (isGamePublished(name, gameEntryMap[name])) publishedGames++;
    else if (isGameSigned(name, gameEntryMap[name])) signedGames++;
  });

  // Store for view-toggle button labels
  totalGameCount = Object.keys(gameEntryMap).length;
  totalPubCount  = Object.keys(pubSet).length;
  document.getElementById('btnGame').textContent      = totalGameCount + ' Games';
  document.getElementById('btnPublisher').textContent = totalPubCount  + ' Publishers';

  function filterBtn(cls, key, label, count) {
    if (!count) return '';
    var active = activeFilters[key] ? ' filter-active' : '';
    return '<button class="pill ' + cls + active + '" onclick="toggleFilter(\'' + key + '\')">' + count + ' ' + label + '</button>';
  }

  var html =
    filterBtn('pill-pitched', 'pitched',   'pitched',   pairCounts.pitched) +
    filterBtn('pill-int',     'interested','interested', pairCounts.interested) +
    filterBtn('pill-passed',  'passed',    'passed',     pairCounts.passed) +
    filterBtn('pill-signed',  'signed',    'signed',     signedGames) +
    filterBtn('pill-published','published','published',  publishedGames);
  document.getElementById('summaryBar').innerHTML = html;
}

// ── Filter toggle ─────────────────────────────────────
function toggleFilter(key) {
  if (activeFilters[key]) {
    delete activeFilters[key];
  } else {
    activeFilters[key] = true;
  }
  buildSummary(allPitches);
  buildView();
}

// ── Search ────────────────────────────────────────────
function applySearch() {
  searchQuery = document.getElementById('searchInput').value.toLowerCase().trim();
  filteredPitches = searchQuery
    ? allPitches.filter(function(r) {
        return (r.Game||'').toLowerCase().includes(searchQuery)
            || (r.Publisher||'').toLowerCase().includes(searchQuery)
            || (r.Contact||'').toLowerCase().includes(searchQuery)
            || (r.Notes||'').toLowerCase().includes(searchQuery);
      })
    : allPitches;
  buildView();
}

// ── View switcher ─────────────────────────────────────
function setView(v) {
  currentView = v;
  document.getElementById('btnDashboard').classList.toggle('active', v==='dashboard');
  document.getElementById('btnGame').classList.toggle('active',      v==='game');
  document.getElementById('btnPublisher').classList.toggle('active', v==='publisher');
  var isDash = v === 'dashboard';
  document.getElementById('summaryBar').style.display = isDash ? 'none' : '';
  document.getElementById('searchBar').style.display  = isDash ? 'none' : '';
  buildView();
}

// ── Sort switcher ─────────────────────────────────────
function setSort(s) {
  currentSort = s;
  document.getElementById('btnSortDate').classList.toggle('active',  s==='date');
  document.getElementById('btnSortAlpha').classList.toggle('active', s==='alpha');
  buildView();
}

// ── Entry row ─────────────────────────────────────────
function entryRow(e) {
  var sc = statusClass(e.Status);
  return '<div class="entry-row">' +
    '<span class="entry-date">'   + escHtml(e.Date)   + '</span>' +
    '<span class="entry-event">'  + escHtml(e.Event)  + '</span>' +
    '<span class="entry-status badge badge-' + sc + '">' + escHtml(e.Status||'—') + '</span>' +
    '<span class="entry-notes">'  + escHtml(e.Notes)  + '</span>' +
    '</div>';
}

// ── Sub-group (contact or game label) ─────────────────
function subGroup(label, email, gameTitle, entries) {
  var sorted = entries.slice().sort(function(a,b){ return new Date(a.Date)-new Date(b.Date); });
  var mailHref = email
    ? 'mailto:' + email + (gameTitle ? '?subject=' + encodeURIComponent(gameTitle) : '')
    : '';
  var emailBtn = mailHref
    ? '<a class="contact-email-btn" href="' + mailHref + '">&#9993; Email</a>'
    : '';
  return '<div class="sub-group">' +
    '<div class="sub-label"><span>' + escHtml(label) + '</span>' + emailBtn + '</div>' +
    sorted.map(entryRow).join('') +
    '</div>';
}

// ── GAME VIEW ─────────────────────────────────────────
function buildGameView(pitches) {
  // Group by Game → Publisher → Contact
  var games = {};
  pitches.forEach(function(r) {
    var g = r.Game || '(Unknown)';
    var p = r.Publisher || '(Unknown)';
    var c = r.Contact   || '(Unknown)';
    if (!games[g]) games[g] = {};
    if (!games[g][p]) games[g][p] = {};
    if (!games[g][p][c]) games[g][p][c] = [];
    games[g][p][c].push(r);
  });

  // Add games from games.json that have no pitch entries yet
  // When searching, only include unpitched games whose name or designers match the query
  Object.keys(gamesIndex).forEach(function(name) {
    if (games[name]) return; // already has pitches
    if (searchQuery) {
      var info = gamesIndex[name];
      var designers = ['Designer1','Designer2','Designer3','Designer4']
        .map(function(f){ return (info[f]||'').toLowerCase(); }).join(' ');
      var matchesSearch = name.toLowerCase().includes(searchQuery) ||
                          designers.includes(searchQuery);
      if (!matchesSearch) return;
    }
    games[name] = {};
  });

  // Apply active filters (any combination, OR logic)
  var hasFilter = activeFilters.signed || activeFilters.published ||
                  activeFilters.interested || activeFilters.passed || activeFilters.pitched;
  if (hasFilter) {
    Object.keys(games).forEach(function(name) {
      var entries = [];
      Object.keys(games[name]).forEach(function(p) {
        Object.keys(games[name][p]).forEach(function(c) {
          games[name][p][c].forEach(function(e){ entries.push(e); });
        });
      });
      var pub = isGamePublished(name, entries);
      var sig = !pub && isGameSigned(name, entries);
      var keep = false;
      if (activeFilters.published && pub) keep = true;
      if (activeFilters.signed    && sig) keep = true;
      if (!keep && (activeFilters.interested || activeFilters.passed || activeFilters.pitched)) {
        // Check if any publisher's latest status matches an active status filter
        keep = Object.keys(games[name]).some(function(p) {
          var pe = [];
          Object.keys(games[name][p]).forEach(function(c){
            games[name][p][c].forEach(function(e){ pe.push(e); });
          });
          var s = (latestEntry(pe).Status||'').toLowerCase();
          return (activeFilters.interested && s === 'interested') ||
                 (activeFilters.passed     && s === 'passed') ||
                 (activeFilters.pitched    && s === 'pitched');
        });
      }
      if (!keep) delete games[name];
    });
  }

  // Sort games
  var gameNames = Object.keys(games).sort(function(a,b) {
    if (currentSort === 'alpha') return a.localeCompare(b);
    // Date sort: most recent first; unpitched (no dates) fall to end, then alpha
    function maxDate(g) {
      var dates = [];
      Object.keys(games[g]).forEach(function(p) {
        Object.keys(games[g][p]).forEach(function(c) {
          games[g][p][c].forEach(function(e){ dates.push(new Date(e.Date)); });
        });
      });
      return dates.length ? Math.max.apply(null, dates) : 0;
    }
    var da = maxDate(a), db = maxDate(b);
    if (db !== da) return db - da;
    return a.localeCompare(b);
  });

  var html = '';
  gameNames.forEach(function(g) {
    var allEntries = [];
    Object.keys(games[g]).forEach(function(p) {
      Object.keys(games[g][p]).forEach(function(c) {
        games[g][p][c].forEach(function(e){ allEntries.push(e); });
      });
    });
    var at = gameAgeTag(games[g]);

    // Determine game-level status badge (never show PASSED; INTERESTED if any pub is currently interested)
    var published = isGamePublished(g, allEntries);
    var signed    = !published && isGameSigned(g, allEntries);
    var gameStatusBadge = '';
    if (published) {
      gameStatusBadge = '<span class="badge badge-published">Published</span>';
    } else if (signed) {
      gameStatusBadge = '<span class="badge badge-signed">Signed</span>';
    } else {
      // Check if any publisher's most-recent status is Interested
      var anyInterested = Object.keys(games[g]).some(function(pub) {
        var pe = [];
        Object.keys(games[g][pub]).forEach(function(c){ games[g][pub][c].forEach(function(e){ pe.push(e); }); });
        return (latestEntry(pe).Status||'').toLowerCase() === 'interested';
      });
      if (anyInterested) {
        gameStatusBadge = '<span class="badge badge-interested">Interested</span>';
      } else {
        // Show Pitched only (suppress Passed)
        var anyPitched = allEntries.some(function(e){ return (e.Status||'').toLowerCase() === 'pitched'; });
        if (anyPitched) gameStatusBadge = '<span class="badge badge-pitched">Pitched</span>';
      }
    }

    html += '<div class="card">';
    var gameInfo  = gamesIndex[g] || {};
    var designers = ['Designer1','Designer2','Designer3','Designer4']
      .map(function(f){ return (gameInfo[f]||'').trim(); })
      .filter(function(v){ return v; })
      .join(', ');
    var gameLabel = designers ? escHtml(g) + ' <span class="game-designers">(' + escHtml(designers) + ')</span>' : escHtml(g);

    html += '<div class="card-header" onclick="toggleCard(this)">';
    html += '<span class="card-title">' + gameLabel + '</span>';
    html += '<span class="card-badges">' + (published || signed ? '' : at) + gameStatusBadge + '</span>';
    html += '<span class="card-chevron">▼</span>';
    html += '</div>';
    html += '<div class="card-body">';

    // Sort publishers alphabetically
    var pubNames = Object.keys(games[g]).sort(function(a,b){ return a.localeCompare(b); });

    if (pubNames.length === 0) {
      html += '<div style="padding:.75rem 1rem;color:#aaa;font-size:.8rem;font-style:italic">No pitches yet</div>';
    }

    pubNames.forEach(function(p) {
      // Each contact under this publisher
      var contacts = Object.keys(games[g][p]).sort(function(a,b){
        if (a==='(Unknown)') return 1; if (b==='(Unknown)') return -1;
        return a.localeCompare(b);
      });
      // Publisher label spanning contacts
      var pubEntries = [];
      contacts.forEach(function(c){ games[g][p][c].forEach(function(e){ pubEntries.push(e); }); });
      var pubLatest  = latestEntry(pubEntries);
      var pubStatus  = (pubLatest.Status||'').toLowerCase();
      var pubAgeTag  = ageTag(pubEntries);  // age tag for this specific publisher

      // Publisher status badge: show Interested; show Pitched; suppress Passed
      var pubBadge = '';
      if (pubStatus === 'interested') {
        pubBadge = '<span class="badge badge-interested" style="margin-right:.75rem">Interested</span>';
      } else if (pubStatus === 'pitched') {
        pubBadge = '<span class="badge badge-pitched" style="margin-right:.75rem">Pitched</span>';
      }
      // (Passed → no badge)

      html += '<div class="sub-group">';
      html += '<div class="sub-label" style="color:#333;font-size:.75rem">' +
              '<span style="flex:1">' + escHtml(p) + '</span>' +
              pubAgeTag + pubBadge +
              '</div>';

      contacts.forEach(function(c) {
        var entries  = games[g][p][c];
        var rawEmail = '';
        entries.forEach(function(e){ if (!rawEmail && e.Email) rawEmail = e.Email; });
        var emailVal = resolveEmail(c, p, rawEmail);
        var sorted = entries.slice().sort(function(a,b){ return new Date(a.Date)-new Date(b.Date); });
        if (c !== '(Unknown)') {
          var mailHref = emailVal
            ? 'mailto:' + emailVal + '?subject=' + encodeURIComponent(g)
            : '';
          var emailBtn = mailHref
            ? '<a class="contact-email-btn" href="' + mailHref + '">&#9993; Email</a>'
            : '';
          html += '<div class="sub-label" style="padding-left:2.5rem;color:#888;font-size:.68rem">' +
                  '<span>' + escHtml(c) + '</span>' + emailBtn + '</div>';
        }
        sorted.forEach(function(e){ html += entryRow(e); });
      });

      html += '</div>';
    });

    html += '</div>'; // card-body
    html += '</div>'; // card
  });

  return html || '<div class="empty">No results.</div>';
}

// ── PUBLISHER VIEW ────────────────────────────────────
function buildPublisherView(pitches) {
  // Group by Publisher → Game → Contact
  var pubs = {};
  pitches.forEach(function(r) {
    var p = r.Publisher || '(Unknown)';
    var g = r.Game      || '(Unknown)';
    var c = r.Contact   || '(Unknown)';
    if (!pubs[p]) pubs[p] = {};
    if (!pubs[p][g]) pubs[p][g] = {};
    if (!pubs[p][g][c]) pubs[p][g][c] = [];
    pubs[p][g][c].push(r);
  });

  // Apply active filters — remove games from each publisher that don't match,
  // then remove publishers left with no games
  var hasFilter = activeFilters.signed || activeFilters.published ||
                  activeFilters.interested || activeFilters.passed || activeFilters.pitched;
  if (hasFilter) {
    Object.keys(pubs).forEach(function(p) {
      Object.keys(pubs[p]).forEach(function(g) {
        var entries = [];
        Object.keys(pubs[p][g]).forEach(function(c){ pubs[p][g][c].forEach(function(e){ entries.push(e); }); });
        var pub = isGamePublished(g, entries);
        var sig = !pub && isGameSigned(g, entries);
        var keep = false;
        if (activeFilters.published && pub) keep = true;
        if (activeFilters.signed    && sig) keep = true;
        if (!keep && (activeFilters.interested || activeFilters.passed || activeFilters.pitched)) {
          var s = (latestEntry(entries).Status||'').toLowerCase();
          keep = (activeFilters.interested && s === 'interested') ||
                 (activeFilters.passed     && s === 'passed') ||
                 (activeFilters.pitched    && s === 'pitched');
        }
        if (!keep) delete pubs[p][g];
      });
      if (Object.keys(pubs[p]).length === 0) delete pubs[p];
    });
  }

  // Sort publishers
  var pubNames = Object.keys(pubs).sort(function(a,b) {
    if (currentSort === 'alpha') return a.localeCompare(b);
    function maxDate(p) {
      var dates = [];
      Object.keys(pubs[p]).forEach(function(g) {
        Object.keys(pubs[p][g]).forEach(function(c) {
          pubs[p][g][c].forEach(function(e){ dates.push(new Date(e.Date)); });
        });
      });
      return Math.max.apply(null, dates);
    }
    return maxDate(b)-maxDate(a);
  });

  var html = '';
  pubNames.forEach(function(p) {
    var allEntries = [];
    Object.keys(pubs[p]).forEach(function(g) {
      Object.keys(pubs[p][g]).forEach(function(c) {
        pubs[p][g][c].forEach(function(e){ allEntries.push(e); });
      });
    });
    var at = ageTag(allEntries);

    // Publisher header: INTERESTED if any game's current status with this publisher is Interested
    var anyPubInterested = Object.keys(pubs[p]).some(function(g) {
      var ge = [];
      Object.keys(pubs[p][g]).forEach(function(c){ pubs[p][g][c].forEach(function(e){ ge.push(e); }); });
      return (latestEntry(ge).Status||'').toLowerCase() === 'interested';
    });
    var pubHeaderBadge = anyPubInterested
      ? '<span class="badge badge-interested">Interested</span>'
      : (allEntries.some(function(e){ return (e.Status||'').toLowerCase() === 'pitched'; })
          ? '<span class="badge badge-pitched">Pitched</span>'
          : '');

    html += '<div class="card">';
    html += '<div class="card-header" onclick="toggleCard(this)">';
    html += '<span class="card-title">' + escHtml(p) + '</span>';
    html += '<span class="card-badges">' + at + pubHeaderBadge + '</span>';
    html += '<span class="card-chevron">▼</span>';
    html += '</div>';
    html += '<div class="card-body">';

    // Sort games alphabetically
    var gameNames = Object.keys(pubs[p]).sort(function(a,b){ return a.localeCompare(b); });
    gameNames.forEach(function(g) {
      var contacts = Object.keys(pubs[p][g]).sort(function(a,b){
        if (a==='(Unknown)') return 1; if (b==='(Unknown)') return -1;
        return a.localeCompare(b);
      });
      var gameEntries = [];
      contacts.forEach(function(c){ pubs[p][g][c].forEach(function(e){ gameEntries.push(e); }); });
      var gLatest  = latestEntry(gameEntries);
      var gStatus  = (gLatest.Status||'').toLowerCase();
      var gAgeTag  = ageTag(gameEntries);

      var gamePublished = isGamePublished(g, gameEntries);
      var gameSigned    = !gamePublished && isGameSigned(g, gameEntries);
      var gBadge;
      if (gamePublished) {
        gBadge = '<span class="badge badge-published" style="margin-right:.75rem">Published</span>';
      } else if (gameSigned) {
        gBadge = '<span class="badge badge-signed" style="margin-right:.75rem">Signed</span>';
      } else if (gStatus === 'interested') {
        gBadge = '<span class="badge badge-interested" style="margin-right:.75rem">Interested</span>';
      } else if (gStatus === 'pitched') {
        gBadge = '<span class="badge badge-pitched" style="margin-right:.75rem">Pitched</span>';
      } else {
        gBadge = ''; // Passed → no badge
      }

      html += '<div class="sub-group">';
      html += '<div class="sub-label" style="color:#333;font-size:.75rem">' +
              '<span style="flex:1">' + escHtml(g) + '</span>' +
              (gamePublished || gameSigned ? '' : gAgeTag) + gBadge +
              '</div>';

      contacts.forEach(function(c) {
        var entries  = pubs[p][g][c];
        var rawEmail = '';
        entries.forEach(function(e){ if (!rawEmail && e.Email) rawEmail = e.Email; });
        var emailVal = resolveEmail(c, p, rawEmail);
        var sorted = entries.slice().sort(function(a,b){ return new Date(a.Date)-new Date(b.Date); });
        if (c !== '(Unknown)') {
          var mailHref = emailVal
            ? 'mailto:' + emailVal + '?subject=' + encodeURIComponent(g)
            : '';
          var emailBtn = mailHref
            ? '<a class="contact-email-btn" href="' + mailHref + '">&#9993; Email</a>'
            : '';
          html += '<div class="sub-label" style="padding-left:2.5rem;color:#888;font-size:.68rem">' +
                  '<span>' + escHtml(c) + '</span>' + emailBtn + '</div>';
        }
        sorted.forEach(function(e){ html += entryRow(e); });
      });

      html += '</div>';
    });

    html += '</div>'; // card-body
    html += '</div>'; // card
  });

  return html || '<div class="empty">No results.</div>';
}

// ── Chart.js lazy loader ──────────────────────────────
var _chartJsReady = false, _chartJsQueue = [];
function loadChartJS(cb) {
  if (_chartJsReady) { cb(); return; }
  _chartJsQueue.push(cb);
  if (_chartJsQueue.length > 1) return;
  var s = document.createElement('script');
  s.src = 'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js';
  s.onload = function() {
    _chartJsReady = true;
    _chartJsQueue.forEach(function(fn){ fn(); });
    _chartJsQueue = [];
  };
  document.head.appendChild(s);
}

// ── Dashboard view ─────────────────────────────────────
var _activeCharts = {};
function buildDashboardView() {
  // ── Compute stats ──────────────────────────────────────
  var allGameNames = Object.keys(gamesIndex).slice();
  allPitches.forEach(function(r){
    if (r.Game && allGameNames.indexOf(r.Game) < 0) allGameNames.push(r.Game);
  });

  var counts = { notStarted:0, pitching:0, signed:0, published:0 };
  var timeToSign = [], timeToPublish = [];

  allGameNames.forEach(function(name) {
    var info    = gamesIndex[name] || {};
    var entries = allPitches.filter(function(r){ return r.Game === name; });
    var pub     = isGamePublished(name, entries);
    var sig     = !pub && isGameSigned(name, entries);
    if (pub)              counts.published++;
    else if (sig)         counts.signed++;
    else if (entries.length) counts.pitching++;
    else                  counts.notStarted++;

    var ds  = (info['Date Started']   || '').trim();
    var dsg = (info['Date Signed']    || '').trim();
    var dp  = (info['Date Published'] || '').trim();
    if (ds && dsg) {
      var mo = (new Date(dsg).getFullYear()-new Date(ds).getFullYear())*12
             + (new Date(dsg).getMonth()  -new Date(ds).getMonth());
      if (mo >= 0) timeToSign.push(mo);
    }
    if (ds && dp) {
      var mo2 = (new Date(dp).getFullYear()-new Date(ds).getFullYear())*12
              + (new Date(dp).getMonth()  -new Date(ds).getMonth());
      if (mo2 >= 0) timeToPublish.push(mo2);
    }
  });

  function avg(arr) {
    return arr.length ? (arr.reduce(function(a,b){return a+b;},0)/arr.length).toFixed(1) : null;
  }
  var avgSign = avg(timeToSign), avgPub = avg(timeToPublish);

  // Publisher → unique games map
  var pubGames = {};
  allPitches.forEach(function(r){
    if (!r.Publisher || !r.Game) return;
    if (!pubGames[r.Publisher]) pubGames[r.Publisher] = {};
    pubGames[r.Publisher][r.Game] = 1;
  });

  // Timeline: pitches per month
  var monthMap = {};
  allPitches.forEach(function(r){
    if (!r.Date) return;
    var d = new Date(r.Date);
    if (isNaN(d)) return;
    var key = d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0');
    monthMap[key] = (monthMap[key]||0) + 1;
  });

  // ── Build HTML ─────────────────────────────────────────
  function statCard(value, label, color) {
    return '<div class="db-stat" style="border-top:3px solid '+color+'">' +
           '<div class="db-stat-value">' + escHtml(String(value)) + '</div>' +
           '<div class="db-stat-label">' + escHtml(label) + '</div>' +
           '</div>';
  }

  var html = '<div class="db-stats">';
  html += statCard(allGameNames.length,       'Total Games',      '#1a1a2e');
  html += statCard(counts.published,          'Published',        '#0369a1');
  html += statCard(counts.signed,             'Signed',           '#7c3aed');
  html += statCard(counts.pitching,           'In Pitching',      '#166534');
  html += statCard(counts.notStarted,         'Not Pitched',      '#94a3b8');
  html += statCard(Object.keys(pubGames).length, 'Publishers',    '#334155');
  if (avgSign !== null) html += statCard(avgSign + ' mo', 'Avg to Sign',    '#7c3aed');
  if (avgPub  !== null) html += statCard(avgPub  + ' mo', 'Avg to Publish', '#0369a1');
  html += '</div>';

  html +=
    '<div class="db-charts">' +
      '<div class="db-chart-card"><h3>Games by Status</h3><canvas id="chartStatus"></canvas></div>' +
      '<div class="db-chart-card"><h3>Top Publishers by Games Pitched</h3><canvas id="chartPublishers"></canvas></div>' +
    '</div>' +
    '<div class="db-chart-card db-chart-wide"><h3>Pitches Over Time</h3><canvas id="chartTimeline"></canvas></div>';

  document.getElementById('content').innerHTML = html;

  // ── Charts ─────────────────────────────────────────────
  loadChartJS(function() {
    // Destroy old chart instances to avoid canvas reuse errors
    Object.keys(_activeCharts).forEach(function(k){
      try { _activeCharts[k].destroy(); } catch(e){}
    });
    _activeCharts = {};

    // Status doughnut
    _activeCharts.status = new Chart(
      document.getElementById('chartStatus').getContext('2d'), {
        type: 'doughnut',
        data: {
          labels: ['Not Pitched', 'Pitching', 'Signed', 'Published'],
          datasets: [{ data: [counts.notStarted, counts.pitching, counts.signed, counts.published],
            backgroundColor: ['#e2e8f0','#bbf7d0','#7c3aed','#0369a1'],
            borderWidth: 2, borderColor: '#fff' }]
        },
        options: { responsive:true, plugins:{ legend:{ position:'bottom', labels:{ font:{size:11} } } } }
      }
    );

    // Top publishers horizontal bar
    var topPubs = Object.keys(pubGames)
      .map(function(p){ return { name:p, count:Object.keys(pubGames[p]).length }; })
      .sort(function(a,b){ return b.count-a.count; }).slice(0,12);

    _activeCharts.publishers = new Chart(
      document.getElementById('chartPublishers').getContext('2d'), {
        type: 'bar',
        data: {
          labels: topPubs.map(function(p){ return p.name; }),
          datasets: [{ label:'Games', data: topPubs.map(function(p){ return p.count; }),
            backgroundColor:'#1a1a2e', borderRadius:3 }]
        },
        options: { indexAxis:'y', responsive:true,
          plugins:{ legend:{ display:false } },
          scales:{ x:{ ticks:{ stepSize:1 }, grid:{ display:false } },
                   y:{ ticks:{ font:{ size:11 } } } } }
      }
    );

    // Timeline bar chart
    var monthKeys = Object.keys(monthMap).sort();
    _activeCharts.timeline = new Chart(
      document.getElementById('chartTimeline').getContext('2d'), {
        type: 'bar',
        data: {
          labels: monthKeys,
          datasets: [{ label:'Pitches', data: monthKeys.map(function(k){ return monthMap[k]; }),
            backgroundColor:'#1a1a2e', borderRadius:3 }]
        },
        options: { responsive:true,
          plugins:{ legend:{ display:false } },
          scales:{ x:{ ticks:{ font:{ size:10 }, maxRotation:45 } },
                   y:{ ticks:{ stepSize:1 }, grid:{ color:'#f0f0f0' } } } }
      }
    );
  });
}

// ── Render ────────────────────────────────────────────
function buildView() {
  if (currentView === 'dashboard') { buildDashboardView(); return; }
  document.getElementById('content').innerHTML =
    currentView === 'game'
      ? buildGameView(filteredPitches)
      : buildPublisherView(filteredPitches);
}

function render(pitches, settings, people, games) {
  // ── Parse settings.json (format: [{My Name: label, COL: value}, …]) ──
  // The value column is whatever key isn't "My Name"
  var myEmail = '', myPhone = '';
  var userName = '';
  if (settings && settings.length) {
    // Detect value column name (the non-"My Name" key)
    var valCol = '';
    var keys = Object.keys(settings[0] || {});
    for (var ki = 0; ki < keys.length; ki++) {
      if (keys[ki] !== 'My Name') { valCol = keys[ki]; break; }
    }
    if (!userName && valCol) userName = valCol; // e.g. "TAM"
    settings.forEach(function(r) {
      var label = (r['My Name']||'').trim();
      var val   = valCol ? (r[valCol]||'').trim() : '';
      if (label === 'My Email') myEmail = val;
      if (label === 'My Phone') myPhone = val;
    });
  }

  // ── Build subtitle ───────────────────────────────────
  var parts = [];
  if (userName) parts.push(userName);
  if (myEmail)  parts.push(myEmail);
  if (myPhone)  parts.push(myPhone);
  document.getElementById('subTitle').textContent = parts.join('  ·  ') || ('Sheet ' + sheet_Id.slice(0,8) + '…');

  // ── Build people index: "Name|Company" → email ───────
  peopleIndex = {};
  (people||[]).forEach(function(p) {
    var key = (p.Name||'').trim() + '|' + (p.Company||'').trim();
    if (p.Email) peopleIndex[key] = p.Email;
  });

  // ── Build games index: game name → {Designers, …} ────
  gamesIndex = {};
  (games||[]).forEach(function(g) {
    var name = (g.Name||'').trim();
    if (name) gamesIndex[name] = g;
  });

  allPitches = (pitches||[]).filter(function(r){ return r.Date || r.Publisher || r.Game; });
  filteredPitches = allPitches;
  buildSummary(allPitches);
  buildView();
}

// ── Resolve email for a contact+publisher ─────────────
function resolveEmail(contact, publisher, fallbackEmail) {
  if (fallbackEmail) return fallbackEmail;
  var key = (contact||'').trim() + '|' + (publisher||'').trim();
  return peopleIndex[key] || '';
}

// ── Collapse / expand ────────────────────────────────
function toggleCard(header) {
  header.parentElement.classList.toggle('open');
}

// ── Share ─────────────────────────────────────────────
function openShare() {
  document.getElementById('shareUrl').value =
    window.location.origin + '/' + sheet_Id + '/pitches.json';
  var btn = document.getElementById('copyBtn');
  btn.textContent = 'Copy'; btn.classList.remove('copied');
  document.getElementById('shareOverlay').classList.add('open');
}
function closeShare() { document.getElementById('shareOverlay').classList.remove('open'); }
function copyUrl() {
  var input = document.getElementById('shareUrl');
  var btn   = document.getElementById('copyBtn');
  input.select();
  navigator.clipboard.writeText(input.value).then(function() {
    btn.textContent='Copied!'; btn.classList.add('copied');
    setTimeout(function(){ btn.textContent='Copy'; btn.classList.remove('copied'); }, 2000);
  }).catch(function() {
    try { document.execCommand('copy'); } catch(e){}
    btn.textContent='Copied!'; btn.classList.add('copied');
    setTimeout(function(){ btn.textContent='Copy'; btn.classList.remove('copied'); }, 2000);
  });
}

// ── Load ──────────────────────────────────────────────
function loadJSON(url, key, fallbackUrl, onDone) {
  var xhr = new XMLHttpRequest();
  xhr.open('GET', url + '?v=' + Date.now());
  xhr.onload = function() {
    if (xhr.status === 200) {
      var data; try { data = JSON.parse(xhr.responseText); } catch(e) { data = []; }
      onDone(key, data);
    } else if (fallbackUrl) {
      loadJSON(fallbackUrl, key, null, onDone);
    } else {
      onDone(key, []);
    }
  };
  xhr.onerror = function() {
    if (fallbackUrl) { loadJSON(fallbackUrl, key, null, onDone); }
    else { onDone(key, []); }
  };
  xhr.send();
}

function loadAll(onComplete) {
  var loaded = {}, needed = 4;
  function done(key, data) {
    loaded[key] = data;
    if (--needed === 0) {
      render(loaded.pitches, loaded.settings, loaded.people, loaded.games);
      if (onComplete) onComplete();
    }
  }
  loadJSON(BASE + 'pitches.json',  'pitches',  BASE + 'connections.json', done);
  loadJSON(BASE + 'settings.json', 'settings', null,                      done);
  loadJSON(BASE + 'people.json',   'people',   null,                      done);
  loadJSON(BASE + 'games.json',    'games',    null,                      done);
}

// ── Sync dialog helpers ───────────────────────────────
function openSyncDialog() {
  document.getElementById('syncLog').innerHTML = '';
  document.getElementById('syncDialogTitle').textContent = 'Syncing…';
  document.getElementById('syncDoneBtn').disabled = true;
  document.getElementById('syncOverlay').classList.add('open');
}
function closeSyncDialog() {
  document.getElementById('syncOverlay').classList.remove('open');
}
function syncLog(msg, type) {
  var log  = document.getElementById('syncLog');
  var line = document.createElement('span');
  line.className = 'sync-log-line ' + (type||'info');
  line.textContent = msg;
  log.appendChild(line);
  log.scrollTop = log.scrollHeight;
}

// ── Sync (push then reload) ───────────────────────────
function syncData() {
  var btn = document.getElementById('syncBtn');
  btn.disabled = true;
  btn.classList.add('syncing');

  openSyncDialog();

  var sheets   = ['pitches', 'games', 'people', 'settings'];
  var pushBase = APP_BASE + 'push/pushSheetUpdate.php';
  var idx = 0;

  function pushNext() {
    if (idx >= sheets.length) {
      syncLog('─────────────────────────────', 'info');
      syncLog('Reloading data…', 'info');
      loadAll(function() {
        syncLog('Done.', 'ok');
        document.getElementById('syncDialogTitle').textContent = 'Sync Complete';
        document.getElementById('syncDoneBtn').disabled = false;
        btn.disabled = false;
        btn.classList.remove('syncing');
      });
      return;
    }
    var sheetName = sheets[idx++];
    syncLog('Pushing ' + sheetName + '…', 'info');
    var xhr = new XMLHttpRequest();
    xhr.open('POST', pushBase);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
      var resp = (xhr.responseText || '').trim();
      if (resp.indexOf('ERROR:') === 0) {
        var msg = resp.replace(/^ERROR:[^:]+:/, '');
        syncLog('  ✗ ' + sheetName + ': ' + msg, 'error');
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
    xhr.send('id=' + encodeURIComponent(sheet_Id) +
             '&sheetname=' + encodeURIComponent(sheetName) +
             '&date_string=');
  }
  pushNext();
}

// Initial load
loadAll();
</script>
</body>
</html>
