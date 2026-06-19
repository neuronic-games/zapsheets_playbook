<?php
$_rp = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('#^(.*?)(?:sheets/)?[A-Za-z0-9_\-]+/connections/?$#', $_rp, $_bm);
$_base = (isset($_bm[1]) && $_bm[1] !== '') ? $_bm[1] : '/';
if (substr($_base, -1) !== '/') $_base .= '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Publisher Connections</title>
  <style>
    @font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2') format('woff2'),url('fonts/DINBlack.ttf'); }
    @font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf'); }

    *, *::before, *::after { box-sizing: border-box; }
    body {
      margin: 0;
      background: #f3f2ef;
      font-family: 'DINRegular', Arial, sans-serif;
      color: #111;
    }

    /* ── Top bar ───────────────────────────────────────── */
    .top-bar {
      background: #1a1a2e;
      color: #fff;
      padding: .9rem 1.5rem;
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    .top-bar-left { flex: 1; }
    .top-bar h1 {
      font-family: 'DINBlack', sans-serif;
      font-size: 1.1rem;
      margin: 0;
      letter-spacing: .03em;
    }
    .top-bar .game-name {
      font-size: .85rem;
      opacity: .65;
      margin: 0;
    }
    .share-btn {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      font-family: 'DINBlack', sans-serif;
      font-size: .75rem;
      text-transform: uppercase;
      letter-spacing: .06em;
      background: rgba(255,255,255,.15);
      color: #fff;
      border: 1px solid rgba(255,255,255,.3);
      border-radius: 6px;
      padding: .4rem .9rem;
      cursor: pointer;
      transition: background .15s;
      white-space: nowrap;
    }
    .share-btn:hover { background: rgba(255,255,255,.25); }

    /* ── Share dialog ──────────────────────────────────── */
    .share-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.45);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }
    .share-overlay.open { display: flex; }
    .share-dialog {
      background: #fff;
      border-radius: 10px;
      padding: 1.5rem;
      width: min(480px, 92vw);
      box-shadow: 0 8px 32px rgba(0,0,0,.22);
    }
    .share-dialog h2 {
      font-family: 'DINBlack', sans-serif;
      font-size: 1rem;
      margin: 0 0 .3rem;
    }
    .share-dialog p {
      font-size: .82rem;
      color: #666;
      margin: 0 0 1rem;
    }
    .share-url-row {
      display: flex;
      gap: .5rem;
      align-items: stretch;
    }
    .share-url-input {
      flex: 1;
      font-family: 'DINRegular', monospace, sans-serif;
      font-size: .78rem;
      border: 1px solid #ccc;
      border-radius: 6px;
      padding: .5rem .75rem;
      color: #333;
      background: #f8f8f8;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      cursor: text;
      outline: none;
    }
    .copy-btn {
      font-family: 'DINBlack', sans-serif;
      font-size: .75rem;
      text-transform: uppercase;
      letter-spacing: .05em;
      background: #1a1a2e;
      color: #fff;
      border: none;
      border-radius: 6px;
      padding: .5rem 1rem;
      cursor: pointer;
      white-space: nowrap;
      transition: background .15s;
      flex-shrink: 0;
    }
    .copy-btn:hover  { background: #2d2d50; }
    .copy-btn.copied { background: #16a34a; }
    .share-close {
      display: block;
      margin-top: 1rem;
      text-align: right;
      font-size: .8rem;
      color: #999;
      cursor: pointer;
      background: none;
      border: none;
      font-family: 'DINRegular', sans-serif;
    }
    .share-close:hover { color: #333; }

    /* ── Summary pills ─────────────────────────────────── */
    .summary-bar {
      display: flex;
      gap: .6rem;
      flex-wrap: wrap;
      padding: 1rem 1.5rem .5rem;
    }
    .pill {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      font-family: 'DINBlack', sans-serif;
      font-size: .75rem;
      text-transform: uppercase;
      letter-spacing: .05em;
      padding: .3rem .75rem;
      border-radius: 999px;
    }
    .pill-total   { background: #1a1a2e; color: #fff; }
    .pill-pitched { background: #e2e8f0; color: #334155; }
    .pill-interested { background: #dcfce7; color: #166534; }
    .pill-passed  { background: #fee2e2; color: #991b1b; }

    /* ── Main content ──────────────────────────────────── */
    .content { padding: 1rem 1.5rem 3rem; max-width: 860px; }

    /* ── Publisher card ────────────────────────────────── */
    .pub-card {
      background: #fff;
      border-radius: 10px;
      box-shadow: 0 1px 4px rgba(0,0,0,.08);
      margin-bottom: 1.1rem;
      overflow: hidden;
    }
    .pub-header {
      background: #1a1a2e;
      color: #fff;
      padding: .6rem 1rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .5rem;
    }
    .pub-name {
      font-family: 'DINBlack', sans-serif;
      font-size: .95rem;
      letter-spacing: .03em;
    }
    .pub-header-right {
      display: flex;
      align-items: center;
      gap: .4rem;
      flex-shrink: 0;
    }
    .pub-status-badge {
      font-size: .7rem;
      font-family: 'DINBlack', sans-serif;
      text-transform: uppercase;
      letter-spacing: .06em;
      padding: .2rem .6rem;
      border-radius: 999px;
    }
    .badge-interested { background: #dcfce7; color: #166534; }
    .badge-passed     { background: #fee2e2; color: #991b1b; }
    .badge-pitched    { background: #e2e8f0; color: #334155; }
    .pub-age-tag {
      font-size: .7rem;
      font-family: 'DINBlack', sans-serif;
      text-transform: uppercase;
      letter-spacing: .06em;
      padding: .2rem .6rem;
      border-radius: 999px;
    }
    .pub-age-6mo { background: #ef4444; color: #fff; }
    .pub-age-3mo { background: #f59e0b; color: #fff; }

    /* ── Contact group ─────────────────────────────────── */
    .contact-group { border-top: 1px solid #f0f0f0; }
    .contact-group:first-child { border-top: none; }
    .contact-header {
      display: flex;
      align-items: center;
      gap: .55rem;
      padding: .55rem 1rem .3rem;
    }
    .contact-name {
      font-family: 'DINBlack', sans-serif;
      font-size: .8rem;
      color: #555;
      text-transform: uppercase;
      letter-spacing: .05em;
    }
    .contact-email-btn {
      display: inline-flex;
      align-items: center;
      gap: .3rem;
      font-family: 'DINBlack', sans-serif;
      font-size: .68rem;
      text-transform: uppercase;
      letter-spacing: .05em;
      color: #1a1a2e;
      background: #e8e8f0;
      border: none;
      border-radius: 999px;
      padding: .18rem .6rem;
      text-decoration: none;
      cursor: pointer;
      white-space: nowrap;
      transition: background .15s;
    }
    .contact-email-btn:hover { background: #1a1a2e; color: #fff; }

    /* ── Entry row ─────────────────────────────────────── */
    .entry-row {
      display: grid;
      grid-template-columns: 90px 90px 90px 1fr;
      align-items: center;
      gap: .5rem;
      padding: .35rem 1rem .35rem 1.8rem;
      border-top: 1px solid #f5f5f5;
      font-size: .82rem;
    }
    .entry-row:first-of-type { border-top: none; }
    .entry-date  { color: #666; white-space: nowrap; }
    .entry-event {
      color: #888;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .entry-status {
      font-family: 'DINBlack', sans-serif;
      font-size: .7rem;
      text-transform: uppercase;
      letter-spacing: .05em;
      padding: .15rem .5rem;
      border-radius: 999px;
      white-space: nowrap;
      justify-self: start;
    }
    .status-interested { background: #dcfce7; color: #166534; }
    .status-passed     { background: #fee2e2; color: #991b1b; }
    .status-pitched    { background: #e2e8f0; color: #334155; }
    .entry-notes { color: #444; line-height: 1.45; }

    /* ── Empty state ───────────────────────────────────── */
    .empty { padding: 3rem; text-align: center; color: #999; font-size: .9rem; }
  </style>
</head>
<body>

<div class="top-bar">
  <div class="top-bar-left">
    <h1>Publisher Connections</h1>
    <p class="game-name" id="gameName"></p>
  </div>
  <button class="share-btn" onclick="openShare()">&#8679; Share</button>
</div>

<div class="share-overlay" id="shareOverlay" onclick="if(event.target===this)closeShare()">
  <div class="share-dialog">
    <h2>Share Connections</h2>
    <p>Send this link to share your connections list.</p>
    <div class="share-url-row">
      <input class="share-url-input" id="shareUrl" type="text" readonly />
      <button class="copy-btn" id="copyBtn" onclick="copyUrl()">Copy</button>
    </div>
    <button class="share-close" onclick="closeShare()">Close</button>
  </div>
</div>

<div class="summary-bar" id="summaryBar"></div>

<div class="content" id="content">
  <div class="empty">Loading…</div>
</div>

<script>
function getSheetId() {
  var parts = window.location.pathname.split('/').filter(Boolean);
  var idx = parts.indexOf('sheets');
  if (idx >= 0 && parts[idx + 1]) return parts[idx + 1];
  var ci = parts.lastIndexOf('connections');
  if (ci > 0) return parts[ci - 1];
  var m = window.location.search.match(/[?&]id=([^&]+)/);
  return m ? m[1] : '';
}

var sheet_Id = getSheetId();
var APP_BASE = document.querySelector('base').getAttribute('href');
var BASE     = APP_BASE + 'sheets/' + sheet_Id + '/';

function statusClass(s) {
  s = (s || '').toLowerCase();
  if (s === 'interested') return 'interested';
  if (s === 'passed')     return 'passed';
  return 'pitched';
}

function latestStatus(entries) {
  // Return the status of the most recent entry
  var sorted = entries.slice().sort(function(a, b) {
    return new Date(b.Date) - new Date(a.Date);
  });
  return sorted[0] ? sorted[0].Status : '';
}

function render(connections, settings, game) {
  // Game title — prefer settings.json, fall back to game-en.json
  var cfg = {};
  (settings || []).forEach(function(r){ if (r.Name) cfg[r.Name] = r.Value; });
  var gd = {};
  (game || []).forEach(function(r){ if (r.Name) gd[r.Name] = r.Value; });
  var gameTitle = cfg['Title'] || gd['Title'] || '';
  if (gameTitle) document.getElementById('gameName').textContent = gameTitle;

  if (!connections || !connections.length) {
    document.getElementById('content').innerHTML = '<div class="empty">No connections found.</div>';
    return;
  }

  // Summary counts
  var total = connections.length;
  var counts = { pitched: 0, interested: 0, passed: 0 };
  connections.forEach(function(r) {
    var s = (r.Status || '').toLowerCase();
    if (s === 'interested') counts.interested++;
    else if (s === 'passed') counts.passed++;
    else counts.pitched++;
  });

  document.getElementById('summaryBar').innerHTML =
    '<span class="pill pill-total">' + total + ' total</span>' +
    '<span class="pill pill-pitched">' + counts.pitched + ' pitched</span>' +
    '<span class="pill pill-interested">' + counts.interested + ' interested</span>' +
    '<span class="pill pill-passed">' + counts.passed + ' passed</span>';

  // Group by Publisher → Contact
  var publishers = {};
  connections.forEach(function(r) {
    var pub  = r.Publisher || '(Unknown)';
    var con  = r.Contact   || '(Unknown)';
    if (!publishers[pub]) publishers[pub] = {};
    if (!publishers[pub][con]) publishers[pub][con] = [];
    publishers[pub][con].push(r);
  });

  // Sort publishers by most recent entry date (newest first)
  var pubNames = Object.keys(publishers).sort(function(a, b) {
    function latestDate(pub) {
      var all = [];
      Object.keys(publishers[pub]).forEach(function(c) {
        publishers[pub][c].forEach(function(e) { all.push(new Date(e.Date)); });
      });
      return Math.max.apply(null, all);
    }
    return latestDate(b) - latestDate(a);
  });

  var html = '';

  pubNames.forEach(function(pub) {
    var allEntries = [];
    Object.keys(publishers[pub]).forEach(function(c) {
      publishers[pub][c].forEach(function(e) { allEntries.push(e); });
    });
    var pubLatest = latestStatus(allEntries);
    var sc = statusClass(pubLatest);

    // Age tag: only for publishers whose latest status is Interested
    var pubAgeTag = '';
    if (pubLatest && pubLatest.toLowerCase() === 'interested') {
      // Find the most recent Interested entry across all contacts
      var intEntries = allEntries.filter(function(e) {
        return (e.Status || '').toLowerCase() === 'interested';
      });
      intEntries.sort(function(a, b) { return new Date(b.Date) - new Date(a.Date); });
      if (intEntries.length) {
        var d      = new Date(intEntries[0].Date);
        var now    = new Date();
        var months = (now.getFullYear() - d.getFullYear()) * 12
                   + (now.getMonth()   - d.getMonth());
        if (months >= 6) {
          pubAgeTag = '<span class="pub-age-tag pub-age-6mo">6mo+</span>';
        } else if (months >= 3) {
          pubAgeTag = '<span class="pub-age-tag pub-age-3mo">3mo+</span>';
        }
      }
    }

    html += '<div class="pub-card">';
    html += '<div class="pub-header">';
    html += '<span class="pub-name">' + pub + '</span>';
    html += '<span class="pub-header-right">';
    html += pubAgeTag;
    html += '<span class="pub-status-badge badge-' + sc + '">' + (pubLatest || '—') + '</span>';
    html += '</span>';
    html += '</div>';

    // Sort contacts — put unknowns last
    var contacts = Object.keys(publishers[pub]).sort(function(a, b) {
      if (a === '(Unknown)') return 1;
      if (b === '(Unknown)') return -1;
      return a.localeCompare(b);
    });

    contacts.forEach(function(con) {
      var entries = publishers[pub][con].slice().sort(function(a, b) {
        return new Date(a.Date) - new Date(b.Date);
      });

      // Pick up email from the first entry that has one
      var emailVal = '';
      for (var ei = 0; ei < entries.length; ei++) {
        if (entries[ei].Email) { emailVal = entries[ei].Email; break; }
      }

      html += '<div class="contact-group">';
      html += '<div class="contact-header">';
      html += '<span class="contact-name">' + con + '</span>';
      if (emailVal) {
        var mailHref = 'mailto:' + emailVal + (gameTitle ? '?subject=' + encodeURIComponent(gameTitle) : '');
        html += '<a class="contact-email-btn" href="' + mailHref + '">&#9993; Email</a>';
      }
      html += '</div>';

      entries.forEach(function(e) {
        var sc2 = statusClass(e.Status);
        html += '<div class="entry-row">';
        html += '<span class="entry-date">'  + (e.Date  || '') + '</span>';
        html += '<span class="entry-event">' + (e.Event || '') + '</span>';
        html += '<span class="entry-status status-' + sc2 + '">' + (e.Status || '') + '</span>';
        html += '<span class="entry-notes">' + (e.Notes || '') + '</span>';
        html += '</div>';
      });

      html += '</div>'; // contact-group
    });

    html += '</div>'; // pub-card
  });

  document.getElementById('content').innerHTML = html;
}

// Load all data files in parallel
var loaded = {}, needed = 3;
function check() { if (--needed === 0) render(loaded.connections, loaded.settings, loaded.game); }

function loadJSON(url, key) {
  var xhr = new XMLHttpRequest();
  xhr.open('GET', url + '?v=' + Date.now());
  xhr.onload = function() {
    try { loaded[key] = JSON.parse(xhr.responseText); } catch(e) { loaded[key] = []; }
    check();
  };
  xhr.onerror = function() { loaded[key] = []; check(); };
  xhr.send();
}

loadJSON(BASE + 'connections.json', 'connections');
loadJSON(BASE + 'settings.json',    'settings');
loadJSON(BASE + 'game-en.json',     'game');

// ── Share dialog ──────────────────────────────────────
function openShare() {
  var url = window.location.origin
          + '/' + sheet_Id + '/connections.json';
  document.getElementById('shareUrl').value = url;
  document.getElementById('copyBtn').textContent = 'Copy';
  document.getElementById('copyBtn').classList.remove('copied');
  document.getElementById('shareOverlay').classList.add('open');
}
function closeShare() {
  document.getElementById('shareOverlay').classList.remove('open');
}
function copyUrl() {
  var input = document.getElementById('shareUrl');
  input.select();
  var btn = document.getElementById('copyBtn');
  try {
    navigator.clipboard.writeText(input.value).then(function() {
      btn.textContent = 'Copied!';
      btn.classList.add('copied');
      setTimeout(function(){ btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 2000);
    });
  } catch(e) {
    document.execCommand('copy');
    btn.textContent = 'Copied!';
    btn.classList.add('copied');
    setTimeout(function(){ btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 2000);
  }
}
</script>
</body>
</html>
