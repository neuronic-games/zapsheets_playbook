<?php
$_rp = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('#^(.*?)(?:sheets/)?[A-Za-z0-9_\-]+/sellsheet/?$#', $_rp, $_bm);
$_base = (isset($_bm[1]) && $_bm[1] !== '') ? $_bm[1] : '/';
if (substr($_base, -1) !== '/') $_base .= '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title id="pageTitle">Sellsheet</title>
  <link id="appIconLink" rel="icon" type="image/x-icon" href="images/sheet_2_new.webp" />
  <style>
    @font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2') format('woff2'),url('fonts/DINBlack.ttf'); }
    @font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf'); }

    *, *::before, *::after { box-sizing: border-box; }

    @page { size: letter portrait; margin: 0; }
    @media print {
      html, body {
        margin: 0 !important; padding: 0 !important;
        background: #fff !important;
        width: 8.5in; height: 11in;
        display: block;
      }
      .print-btn { display: none !important; }

      /* Force the sheet to fill the page exactly — reset any JS-applied mobile scaling */
      .sheet {
        box-shadow: none !important;
        width: 8.5in !important;
        height: 11in !important;
        margin: 0 !important;
        padding: 43px 46px !important;
        overflow: hidden !important;
        display: flex !important;
        flex-direction: column !important;
        transform: none !important;
        transform-origin: unset !important;
      }

      /* Restore layout overridden by the mobile breakpoint */
      .ss-header {
        display: grid !important;
        grid-template-columns: 1fr auto auto !important;
      }
      .ss-header-qr { display: flex !important; }
      .ss-body {
        display: grid !important;
        grid-template-columns: 1fr 1fr !important;
      }
      .ss-hero-wrap {
        height: 330px !important;
        overflow: hidden !important;
      }
    }

    body {
      margin: 0;
      background: #e0dbd3;
      font-family: 'DINRegular', Arial, sans-serif;
      color: #111;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 2rem 1rem;
    }
    .print-btn {
      margin-bottom: 1rem;
      padding: .45rem 1.3rem;
      background: #333; color: #fff;
      border: none; border-radius: 6px;
      font-family: 'DINBlack', sans-serif;
      font-size: .78rem; text-transform: uppercase;
      letter-spacing: .07em; cursor: pointer;
    }
    .print-btn:hover { background: #000; }

    /* ── Sheet: 8.5 × 11 in @ 96 dpi = 816 × 1056 px ───────────── */
    .sheet {
      background: #fff;
      width: 816px; height: 1056px;
      overflow: hidden;
      box-shadow: 0 4px 32px rgba(0,0,0,.22);
      display: flex; flex-direction: column;
      padding: 43px 46px;
    }

    /* ── Header: title+byline | stats | QR ──────────────────────── */
    .ss-header {
      flex-shrink: 0;
      display: grid;
      grid-template-columns: 1fr auto auto;
      align-items: center;
      padding-bottom: .7rem;
      gap: 0;
    }
    .ss-header-stats {
      border-left: 1.5px solid #222;
      padding: 0 1.3rem;
      text-align: center;
      white-space: nowrap;
    }
    .ss-header-qr {
      padding-left: 1.3rem;
      display: flex;
      align-items: center;
    }
    /* QR in header — sized at 72 px */
    #ssQR { width: 72px; height: 72px; flex-shrink: 0; }
    #ssQR img    { display: block !important; width: 72px !important; height: 72px !important; }
    #ssQR canvas { display: block;            width: 72px !important; height: 72px !important; }

    .ss-title {
      font-family: 'DINBlack', sans-serif;
      font-size: 1.3rem;
      letter-spacing: .01em;
      margin: 0 0 .25rem;
      line-height: 1.1;
    }
    .ss-designer-byline {
      font-size: .8rem;
      color: #444;
      margin: 0;
      line-height: 1.35;
    }
    .ss-stat-line {
      font-size: .85rem;
      line-height: 1.55;
      margin: 0;
    }

    /* ── Hero image ──────────────────────────────────────────────── */
    .ss-hero-wrap {
      flex-shrink: 0;
      width: 100%; height: 330px;
      overflow: hidden;
      background: #d0d0d0;
      margin-top: 1rem; margin-bottom: 1rem;
    }
    .ss-hero-wrap img {
      width: 100%; height: 100%;
      object-fit: cover; display: block;
    }

    /* ── Body: 2-col × 2-row grid ────────────────────────────────
       Col 1 spans both rows (text)
       Col 2 row 1: components
       Col 2 row 2: (empty — QR moved to header)              */
    .ss-body {
      flex: 1; min-height: 0;
      display: grid;
      grid-template-columns: 1fr 1fr;
      grid-template-rows: 1fr;
      column-gap: 1.3rem;
      overflow: hidden;
    }

    /* ── Left column: subtitle → hook → desc → features ─────────── */
    .ss-left {
      display: flex; flex-direction: column;
      gap: .75rem;
      overflow: hidden;
    }
    .ss-body-subtitle {
      font-style: italic;
      font-size: .82rem;
      color: #444;
      margin: 0;
      line-height: 1.4;
      flex-shrink: 0;
    }
    .ss-hook {
      font-family: 'DINBlack', sans-serif;
      font-size: .86rem;
      margin: 0; line-height: 1.35;
      flex-shrink: 0;
    }
    .ss-desc {
      font-size: .82rem; line-height: 1.52;
      color: #222; flex-shrink: 0;
    }
    .ss-desc p { margin: 0 0 .5rem; }
    .ss-desc p:last-child { margin-bottom: 0; }
    .ss-features {
      list-style: none; margin: 0; padding: 0;
      display: flex; flex-direction: column;
      gap: .3rem; flex-shrink: 0;
    }
    .ss-features li {
      display: flex; align-items: flex-start;
      gap: .5rem; font-size: .82rem;
      line-height: 1.4; color: #222;
    }
    .ss-features li::before {
      content: ''; width: 6px; height: 6px;
      background: #222; border-radius: 50%;
      flex-shrink: 0; margin-top: .38rem;
    }

    /* ── Right column: components box ───────────────────────────── */
    .ss-right {
      overflow: hidden;
      align-self: start;
    }
    .ss-components {
      border: 1px solid #bbb;
      background: #f6f4f1;
      border-radius: 4px;
      padding: .85rem 1rem;
    }
    .ss-components h3 {
      font-family: 'DINBlack', sans-serif;
      font-size: .88rem;
      margin: 0 0 .55rem; color: #555;
    }
    .ss-comp-list {
      list-style: none; margin: 0; padding: 0;
      display: flex; flex-direction: column; gap: .32rem;
    }
    .ss-comp-list li {
      display: flex; align-items: flex-start;
      gap: .5rem; font-size: .82rem;
      line-height: 1.4; color: #222;
    }
    .ss-comp-list li::before {
      content: ''; width: 6px; height: 6px;
      background: #222; border-radius: 50%;
      flex-shrink: 0; margin-top: .38rem;
    }

    /* ── Footer contact bar ──────────────────────────────────────── */
    .ss-contact-bar {
      flex-shrink: 0;
      padding-top: .5rem;
      margin-top: .6rem;
      border-top: 1px solid #ddd;
      font-size: .75rem;
      color: #444;
      text-align: center;
      line-height: 1.6;
    }
    .ss-contact-bar a { color: #444; text-decoration: none; }

    /* No responsive breakpoints — layout is fixed at 816×1056 px (8.5×11 in) on all screen sizes */
  </style>
</head>
<body>

<button class="print-btn" onclick="window.print()">Print / Save as PDF</button>

<div class="sheet">

  <!-- Header: title+byline | stats | QR -->
  <header class="ss-header">
    <div>
      <h1 class="ss-title" id="ssTitle">…</h1>
      <p class="ss-designer-byline" id="ssDesignerByline" style="display:none"></p>
    </div>
    <div class="ss-header-stats">
      <p class="ss-stat-line" id="ssPlayers" style="display:none"></p>
      <p class="ss-stat-line" id="ssTime"    style="display:none"></p>
    </div>
    <div class="ss-header-qr">
      <div id="ssQR"></div>
    </div>
  </header>

  <!-- Hero image -->
  <div class="ss-hero-wrap">
    <img id="ssHero" src="" alt="" />
  </div>

  <!-- Body: left text | right components -->
  <div class="ss-body">

    <!-- Left: subtitle · hook · description · features -->
    <div class="ss-left">
      <p class="ss-body-subtitle" id="ssBodySubtitle" style="display:none"></p>
      <p class="ss-hook"          id="ssHook"         style="display:none"></p>
      <div class="ss-desc"        id="ssDesc"         style="display:none"></div>
      <ul class="ss-features"     id="ssFeatures"     style="display:none"></ul>
    </div>

    <!-- Right: components -->
    <div class="ss-right">
      <div class="ss-components" id="ssComponents" style="display:none">
        <h3>Components</h3>
        <ul class="ss-comp-list" id="ssCompList"></ul>
      </div>
    </div>

  </div>

  <!-- Footer: contact info -->
  <div class="ss-contact-bar" id="ssContactBar" style="display:none"></div>

</div>

<script src="js/common/jquery-3.5.1.min.js"></script>
<script src="js/common/qrcode.min.js"></script>
<script>
var data = {};

function getSheetId() {
  var parts = window.location.pathname.split('/').filter(Boolean);
  var idx = parts.indexOf('sheets');
  if (idx >= 0 && parts[idx + 1]) return parts[idx + 1];
  var si = parts.lastIndexOf('sellsheet');
  if (si > 0) return parts[si - 1];
  var m = window.location.search.match(/[?&]id=([^&]+)/);
  return m ? m[1] : '';
}
function getLang() {
  var m = window.location.search.match(/[?&]code=([^&]+)/);
  return m ? m[1].toLowerCase() : 'en';
}

var sheet_Id = getSheetId();
var lang     = getLang();
var APP_BASE = document.querySelector('base').getAttribute('href');
var BASE     = APP_BASE + 'sheets/' + sheet_Id + '/';

function getViewUrl() {
  var _path  = window.location.pathname;
  var _idEnd = _path.indexOf(sheet_Id) + sheet_Id.length;
  return window.location.origin + _path.substring(0, _idEnd) + '/view/';
}

function cachedImage(url) {
  if (!url) return '';
  if (url.indexOf('https://drive.google.com') === 0) {
    var imgid = url.split('https://drive.google.com')[1].split('/')[3];
    return BASE + 'cacheImages/' + imgid + '.png';
  }
  var parts = url.split('/');
  var raw   = parts[parts.length - 1];
  var fname = raw.indexOf('?') !== -1 ? raw.split('?')[0] : raw;
  return BASE + 'cacheImages/' + fname;
}

var _loadTotal = 4, _loadDone = 0;
function _jsonLoad(path, key) {
  $.ajax({
    url: path + '?v=' + Date.now(),
    type: 'GET', dataType: 'text', cache: false,
    success: function(r) {
      try { data[key] = JSON.parse(r.replace(/�/g, '')); }
      catch(e) { try { data[key] = eval('(' + r + ')'); } catch(e2) {} }
      if (++_loadDone >= _loadTotal) render();
    },
    error: function() { if (++_loadDone >= _loadTotal) render(); }
  });
}

var _gameParam = (function() {
  var m = window.location.search.match(/[?&]game=([^&]+)/);
  return m ? decodeURIComponent(m[1]) : '';
})();
var _gameFile = _gameParam ? ('game-' + _gameParam + '-' + lang + '.json') : ('game-' + lang + '.json');

_jsonLoad(BASE + 'settings.json',           'settings');
_jsonLoad(BASE + _gameFile,                 'bgg');
_jsonLoad(BASE + 'splash-' + lang + '.json','splash');
_jsonLoad(BASE + 'bgg.json',                'stats');

function render() {
  var cfg = {};
  (data.settings || []).forEach(function(r) {
    // settings.json uses col-A header as key column ('My Name' or 'Name')
    // and whatever the user named col B as the value column (e.g. 'TAM')
    var key = r['My Name'] || r['Name'];
    if (!key) return;
    var val = r['Value'];
    if (val === undefined) {
      for (var k in r) { if (k !== 'My Name' && k !== 'Name') { val = r[k]; break; } }
    }
    cfg[key] = val || '';
  });

  var bg = {};
  if (data.stats && data.stats.boardgame && data.stats.boardgame[0])
    bg = data.stats.boardgame[0].boardgame || {};
  var basic = {};
  if (data.stats && data.stats.boardgameBasicData) {
    var k = Object.keys(data.stats.boardgameBasicData);
    if (k.length) basic = data.stats.boardgameBasicData[k[0]];
  }

  var gv = function(name) {
    var r = (data.bgg || []).find(function(r){ return r.Name === name && r.Value; });
    return r ? r.Value : null;
  };
  var gvAll = function(name) {
    return (data.bgg || []).filter(function(r){ return r.Name === name && r.Value; });
  };

  // ── Title ────────────────────────────────────────────────────
  var title = gv('Title') || cfg['Title'] || basic['name'] || 'Game';
  document.title = title + ' — Sellsheet';
  document.getElementById('ssTitle').textContent = title;

  // ── Designer byline below title ────────────────────────────
  var designers = gvAll('Designer');
  var dNames = [];
  if (designers.length) {
    dNames = designers.map(function(r){ return r.Value; });
  } else if (bg['boardgamedesigner']) {
    var bgd = Array.isArray(bg['boardgamedesigner']) ? bg['boardgamedesigner'] : [bg['boardgamedesigner']];
    dNames = bgd.map(function(d){ return typeof d === 'object' ? d.name : d; });
  }
  if (dNames.length) {
    var byl = document.getElementById('ssDesignerByline');
    byl.textContent = 'Designed by: ' + dNames.join(', ');
    byl.style.display = '';
  }

  // ── Players + time ────────────────────────────────────────
  var minP = gv('MinPlayers')  || bg['minplayers'];
  var maxP = gv('MaxPlayers')  || bg['maxplayers'];
  var minT = gv('MinPlaytime') || bg['minplaytime'];
  var maxT = gv('MaxPlaytime') || bg['maxplaytime'];

  if (minP || maxP) {
    var pStr = (minP && maxP && minP !== maxP)
      ? minP + '–' + maxP + ' players' : (minP || maxP) + ' players';
    var pEl = document.getElementById('ssPlayers');
    pEl.textContent = pStr; pEl.style.display = '';
  }
  if (minT || maxT) {
    var tStr = (minT && maxT && minT !== maxT)
      ? minT + '–' + maxT + ' mins' : (minT || maxT) + ' mins';
    var tEl = document.getElementById('ssTime');
    tEl.textContent = tStr; tEl.style.display = '';
  }

  // ── QR code in header (→ /view URL) ──────────────────────
  var viewUrl = getViewUrl();
  if (viewUrl && typeof QRCode !== 'undefined') {
    new QRCode(document.getElementById('ssQR'), {
      text: viewUrl,
      width: 72, height: 72,
      colorDark: '#000000', colorLight: '#ffffff',
      correctLevel: QRCode.CorrectLevel.M
    });
  }

  // ── Hero image ─────────────────────────────────────────────
  // PitchImageUrl takes priority; fall back to splash-en.json
  var _pitchImg = gv('PitchImageUrl');
  var _heroSrc  = '';
  if (_pitchImg) {
    _heroSrc = _pitchImg;
  } else if (data.splash) {
    var row = data.splash.find(function(r){
      return (r.ID || '').toLowerCase() === 'layout' && r.Content;
    });
    if (!row) row = data.splash.find(function(r){
      return (r.Type || '').toLowerCase() === 'image' && r.Content;
    });
    if (row) _heroSrc = cachedImage(row.Content);
  }
  if (_heroSrc) document.getElementById('ssHero').src = _heroSrc;

  // ── Subtitle at top of body left ──────────────────────────
  var sub = gv('SubTitle');
  if (sub) {
    var subEl = document.getElementById('ssBodySubtitle');
    subEl.textContent = sub; subEl.style.display = '';
  }

  // ── Hook ──────────────────────────────────────────────────
  var hook = gv('Hook');
  if (hook) {
    var hookEl = document.getElementById('ssHook');
    hookEl.textContent = hook; hookEl.style.display = '';
  }

  // ── Description (line breaks → paragraph spacing) ─────────
  // PitchDescription takes priority on the sellsheet; fall back to Description, then BGG
  var desc = gv('PitchDescription') || gv('Description') || (bg['description'] || '');
  if (desc) {
    var descEl = document.getElementById('ssDesc');
    var lines = desc.replace(/&#10;/g, '\n').split(/\n+/).filter(function(l){ return l.trim(); });
    descEl.innerHTML = lines.map(function(l){
      return '<p>' + l.trim() + '</p>';
    }).join('');
    descEl.style.display = '';
  }

  // ── Features ──────────────────────────────────────────────
  var features = gvAll('Feature');
  if (features.length) {
    var ul = document.getElementById('ssFeatures');
    ul.innerHTML = features.map(function(r){ return '<li>' + r.Value + '</li>'; }).join('');
    ul.style.display = '';
  }

  // ── Components ────────────────────────────────────────────
  var components = gvAll('Component');
  if (components.length) {
    document.getElementById('ssCompList').innerHTML =
      components.map(function(r){ return '<li>' + r.Value + '</li>'; }).join('');
    document.getElementById('ssComponents').style.display = '';
  }

  // ── Footer contact bar ─────────────────────────────────────
  var _fName    = cfg['My Name'];
  var _fAddress = cfg['My Address'];
  var _fEmail   = cfg['My Email'];
  var _fPhone   = cfg['My Phone'];

  var parts = [];
  if (_fName)    parts.push(_fName);
  if (_fAddress) parts.push(_fAddress);
  if (_fEmail)   parts.push('<a href="mailto:' + _fEmail + '">' + _fEmail + '</a>');
  if (_fPhone)   parts.push(_fPhone);

  if (parts.length) {
    var barEl = document.getElementById('ssContactBar');
    barEl.innerHTML = parts.join(' &nbsp;|&nbsp; ');
    barEl.style.display = '';
  }
}

// ── Scale sheet to fit viewport on small screens ──────────────
(function() {
  var sheet = document.querySelector('.sheet');
  function fit() {
    var pad = 16; // px breathing room on each side
    var available = window.innerWidth - pad * 2;
    if (available < 816) {
      var scale = available / 816;
      sheet.style.transform = 'scale(' + scale + ')';
      sheet.style.transformOrigin = 'top center';
      // Collapse the empty layout space left behind by the CSS transform
      sheet.style.marginBottom = '-' + Math.round(1056 * (1 - scale)) + 'px';
    } else {
      sheet.style.transform = '';
      sheet.style.transformOrigin = '';
      sheet.style.marginBottom = '';
    }
  }
  fit();
  window.addEventListener('resize', fit);
})();
</script>
</body>
</html>
