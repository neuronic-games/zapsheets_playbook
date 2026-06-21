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
  <title>Pitch Dashboard</title>
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
    }
    .top-bar-left { flex:1; min-width:0; }
    .top-bar h1 { font-family:'DINBlack',sans-serif; font-size:1rem; margin:0; letter-spacing:.03em; }
    .top-bar .sub { font-size:.73rem; opacity:.6; margin:0; letter-spacing:.01em; }

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

    /* ── Share button ────────────────────────────────── */
    .share-btn {
      display:inline-flex; align-items:center; gap:.35rem;
      font-family:'DINBlack',sans-serif; font-size:.7rem;
      text-transform:uppercase; letter-spacing:.06em;
      background:rgba(255,255,255,.15); color:#fff;
      border:1px solid rgba(255,255,255,.3); border-radius:6px;
      padding:.38rem .8rem; cursor:pointer;
      transition:background .15s; white-space:nowrap; flex-shrink:0;
    }
    .share-btn:hover { background:rgba(255,255,255,.25); }

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
    .pill-total    { background:#1a1a2e; color:#fff; }
    .pill-pitched  { background:#e2e8f0; color:#334155; }
    .pill-int      { background:#dcfce7; color:#166534; }
    .pill-passed   { background:#fee2e2; color:#991b1b; }

    /* ── Search ──────────────────────────────────────── */
    .search-bar { padding:.4rem 1.25rem .6rem; }
    .search-bar input {
      width:100%; max-width:380px;
      font-family:'DINRegular',sans-serif; font-size:.82rem;
      border:1px solid #ccc; border-radius:6px;
      padding:.42rem .8rem; outline:none;
      background:#fff; color:#111;
    }
    .search-bar input:focus { border-color:#1a1a2e; }

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
  </style>
</head>
<body>

<!-- Top bar -->
<div class="top-bar">
  <div class="top-bar-left">
    <h1>Pitch Dashboard</h1>
    <p class="sub" id="subTitle">Loading…</p>
  </div>
  <div class="view-toggle">
    <button id="btnGame"      class="active" onclick="setView('game')">By Game</button>
    <button id="btnPublisher"               onclick="setView('publisher')">By Publisher</button>
  </div>
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

<div class="summary-bar" id="summaryBar"></div>
<div class="search-bar">
  <input type="text" id="searchInput" placeholder="Search games, publishers, contacts…" oninput="applySearch()" />
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
var allPitches      = [];
var filteredPitches = [];
var searchQuery     = '';
var peopleIndex     = {};   // "Name|Company" → email

// ── Helpers ───────────────────────────────────────────
function statusClass(s) {
  s = (s||'').toLowerCase();
  if (s==='interested') return 'interested';
  if (s==='passed')     return 'passed';
  return 'pitched';
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

function escHtml(s) {
  return (s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Build summary bar ─────────────────────────────────
function buildSummary(pitches) {
  var games = {}, pubs = {}, counts = {pitched:0, interested:0, passed:0};
  pitches.forEach(function(r) {
    if (r.Game) games[r.Game] = 1;
    if (r.Publisher) pubs[r.Publisher] = 1;
    var s = (r.Status||'').toLowerCase();
    if (s==='interested') counts.interested++;
    else if (s==='passed') counts.passed++;
    else if (s) counts.pitched++;
  });
  document.getElementById('summaryBar').innerHTML =
    '<span class="pill pill-total">' + pitches.length + ' pitches</span>' +
    '<span class="pill pill-total" style="background:#2d2d50">' + Object.keys(games).length + ' games</span>' +
    '<span class="pill pill-total" style="background:#374151">' + Object.keys(pubs).length + ' publishers</span>' +
    '<span class="pill pill-pitched">'  + counts.pitched    + ' pitched</span>' +
    '<span class="pill pill-int">'      + counts.interested + ' interested</span>' +
    '<span class="pill pill-passed">'   + counts.passed     + ' passed</span>';
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
  document.getElementById('btnGame').classList.toggle('active',      v==='game');
  document.getElementById('btnPublisher').classList.toggle('active', v==='publisher');
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

  // Sort games by most recent entry
  var gameNames = Object.keys(games).sort(function(a,b) {
    function maxDate(g) {
      var dates = [];
      Object.keys(games[g]).forEach(function(p) {
        Object.keys(games[g][p]).forEach(function(c) {
          games[g][p][c].forEach(function(e){ dates.push(new Date(e.Date)); });
        });
      });
      return Math.max.apply(null, dates);
    }
    return maxDate(b)-maxDate(a);
  });

  var html = '';
  gameNames.forEach(function(g) {
    var allEntries = [];
    Object.keys(games[g]).forEach(function(p) {
      Object.keys(games[g][p]).forEach(function(c) {
        games[g][p][c].forEach(function(e){ allEntries.push(e); });
      });
    });
    var latest = latestEntry(allEntries);
    var sc = statusClass(latest.Status);
    var at = ageTag(allEntries);

    html += '<div class="card">';
    html += '<div class="card-header" onclick="toggleCard(this)">';
    html += '<span class="card-title">' + escHtml(g) + '</span>';
    html += '<span class="card-badges">' + at +
            '<span class="badge badge-' + sc + '">' + escHtml(latest.Status||'—') + '</span></span>';
    html += '<span class="card-chevron">▼</span>';
    html += '</div>';
    html += '<div class="card-body">';

    // Sort publishers alphabetically
    var pubNames = Object.keys(games[g]).sort(function(a,b){ return a.localeCompare(b); });
    pubNames.forEach(function(p) {
      // Each contact under this publisher
      var contacts = Object.keys(games[g][p]).sort(function(a,b){
        if (a==='(Unknown)') return 1; if (b==='(Unknown)') return -1;
        return a.localeCompare(b);
      });
      // Publisher label spanning contacts
      var pubEntries = [];
      contacts.forEach(function(c){ games[g][p][c].forEach(function(e){ pubEntries.push(e); }); });
      var pubLatest = latestEntry(pubEntries);
      var psc = statusClass(pubLatest.Status);

      html += '<div class="sub-group">';
      html += '<div class="sub-label" style="color:#333;font-size:.75rem">' +
              '<span style="flex:1">' + escHtml(p) + '</span>' +
              '<span class="badge badge-' + psc + '" style="margin-right:.75rem">' + escHtml(pubLatest.Status||'—') + '</span>' +
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

  // Sort publishers by most recent
  var pubNames = Object.keys(pubs).sort(function(a,b) {
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
    var latest = latestEntry(allEntries);
    var sc = statusClass(latest.Status);
    var at = ageTag(allEntries);

    html += '<div class="card">';
    html += '<div class="card-header" onclick="toggleCard(this)">';
    html += '<span class="card-title">' + escHtml(p) + '</span>';
    html += '<span class="card-badges">' + at +
            '<span class="badge badge-' + sc + '">' + escHtml(latest.Status||'—') + '</span></span>';
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
      var gLatest = latestEntry(gameEntries);
      var gsc = statusClass(gLatest.Status);

      html += '<div class="sub-group">';
      html += '<div class="sub-label" style="color:#333;font-size:.75rem">' +
              '<span style="flex:1">' + escHtml(g) + '</span>' +
              '<span class="badge badge-' + gsc + '" style="margin-right:.75rem">' + escHtml(gLatest.Status||'—') + '</span>' +
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

// ── Render ────────────────────────────────────────────
function buildView() {
  document.getElementById('content').innerHTML =
    currentView === 'game'
      ? buildGameView(filteredPitches)
      : buildPublisherView(filteredPitches);
}

function render(pitches, settings, game, people) {
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
var loaded = {}, needed = 4;
function check() { if (--needed===0) render(loaded.pitches, loaded.settings, loaded.game, loaded.people); }
function loadJSON(url, key, fallbackUrl) {
  var xhr = new XMLHttpRequest();
  xhr.open('GET', url + '?v=' + Date.now());
  xhr.onload = function() {
    if (xhr.status === 200) {
      try { loaded[key] = JSON.parse(xhr.responseText); } catch(e) { loaded[key] = []; }
      check();
    } else if (fallbackUrl) {
      loadJSON(fallbackUrl, key);
    } else {
      loaded[key] = []; check();
    }
  };
  xhr.onerror = function() {
    if (fallbackUrl) { loadJSON(fallbackUrl, key); }
    else { loaded[key]=[]; check(); }
  };
  xhr.send();
}
// Try pitches.json first; fall back to connections.json for legacy sheets
loadJSON(BASE + 'pitches.json',  'pitches', BASE + 'connections.json');
loadJSON(BASE + 'settings.json', 'settings');
loadJSON(BASE + 'game-en.json',  'game');
loadJSON(BASE + 'people.json',   'people');
</script>
</body>
</html>
