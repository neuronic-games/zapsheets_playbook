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
<?php if (isset($GLOBALS['_gv_sheet_id'])): ?>
<script>window._gvSheetId=<?=json_encode($GLOBALS['_gv_sheet_id'])?>;window._gvGame=<?=json_encode($GLOBALS['_gv_game']??'')?>;window._gvGameToken=<?=json_encode($GLOBALS['_gv_game_token']??'')?></script>
<?php endif; ?>
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
      /* hero height: let image set its own height on screen; clipped by sheet overflow on print */
    }

    /* PWA back bar — only shown in standalone / Home Screen mode */
    #pwaBackBar {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 999;
      background: #1c1c1e;
      padding: .55rem 1rem;
    }
    #pwaBackBar button {
      background: none;
      border: none;
      color: #0a84ff;
      font-size: 1rem;
      font-family: -apple-system, sans-serif;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: .3rem;
      padding: 0;
    }
    @media print { #pwaBackBar { display: none !important; } }

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
      font-size: 11pt;
      color: #444;
      margin: 0;
      line-height: 1.35;
    }
    .ss-stat-line {
      font-size: 11pt;
      line-height: 1.55;
      margin: 0;
    }

    /* ── Hero image ──────────────────────────────────────────────── */
    .ss-hero-wrap {
      flex: 1 1 auto;
      min-height: 80px;
      overflow: hidden;
      background: #d0d0d0;
      margin-top: 1rem; margin-bottom: 1rem;
      position: relative;
    }
    .ss-hero-wrap img {
      width: 100%; height: 100%;
      object-fit: cover; display: block;
    }
    .ss-steps-overlay {
      position: absolute; bottom: 0; left: 0; right: 0;
      background: rgba(0,0,0,.72); display: flex; backdrop-filter: blur(2px);
    }
    .ss-step-item { flex: 1; display: flex; flex-direction: column; align-items: center; text-align: center; padding: .6rem .5rem; border-right: 1px solid rgba(255,255,255,.12); }
    .ss-step-item:last-child { border-right: none; }
    .ss-step-num { width: 22px; height: 22px; border-radius: 50%; background: #c8860a; color: #fff; font-family: 'DINBlack', sans-serif; font-size: .78rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-bottom: .3rem; }
    .ss-step-text { font-size: .72rem; line-height: 1.3; color: rgba(255,255,255,.9); }

    /* ── Body: 2-col × 2-row grid ────────────────────────────────
       Col 1 spans both rows (text)
       Col 2 row 1: components
       Col 2 row 2: (empty — QR moved to header)              */
    .ss-body {
      flex: 0 0 auto;
      display: grid;
      grid-template-columns: 1fr 1fr;
      grid-template-rows: auto;
      column-gap: 1.3rem;
    }

    /* ── Left column: subtitle → hook → desc → features ─────────── */
    .ss-left {
      display: flex; flex-direction: column;
      gap: .75rem;
      overflow: hidden;
      justify-content: flex-end;
    }
    .ss-body-subtitle {
      font-style: italic;
      font-size: 11pt;
      color: #444;
      margin: 0;
      line-height: 1.4;
      flex-shrink: 0;
    }
    .ss-hook {
      font-family: 'DINBlack', sans-serif;
      font-size: 11pt;
      margin: 0; line-height: 1.35;
      flex-shrink: 0;
    }
    .ss-desc {
      font-size: 11pt; line-height: 1.52;
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
      gap: .4rem; font-size: 11pt;
      line-height: 1.4; color: #222;
    }
    .ss-features li::before {
      content: '•';
      flex-shrink: 0;
      line-height: 1.4;
      color: #222;
    }

    /* ── Right column: components box ───────────────────────────── */
    .ss-right {
      overflow: hidden;
      align-self: end;
    }
    .ss-components {
      border: 1px solid #bbb;
      background: #f6f4f1;
      border-radius: 4px;
      padding: .85rem 1rem;
    }
    .ss-components h3 {
      font-family: 'DINBlack', sans-serif;
      font-size: 11pt;
      margin: 0 0 .55rem; color: #555;
    }
    .ss-comp-list {
      list-style: none; margin: 0; padding: 0;
      display: flex; flex-direction: column; gap: .32rem;
    }
    .ss-comp-list li {
      display: flex; align-items: flex-start;
      gap: .4rem; font-size: 11pt;
      line-height: 1.4; color: #222;
    }
    .ss-comp-list li::before {
      content: '•';
      flex-shrink: 0;
      line-height: 1.4;
      color: #222;
    }

    /* ── Footer contact bar ──────────────────────────────────────── */
    .ss-contact-bar {
      flex-shrink: 0;
      padding-top: .5rem;
      margin-top: .6rem;
      border-top: 1px solid #ddd;
      font-size: 11pt;
      color: #444;
      text-align: center;
      line-height: 1.6;
    }
    .ss-contact-bar a { color: #444; text-decoration: none; }

    /* No responsive breakpoints — layout is fixed at 816×1056 px (8.5×11 in) on all screen sizes */
  </style>
</head>
<body>

<div id="pwaBackBar">
  <button id="pwaBackBtn">&#8249; Back</button>
</div>

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

  <!-- Hero image — hidden when no PitchImageUrl or splash image exists -->
  <div class="ss-hero-wrap" id="ssHeroWrap" style="display:none">
    <img id="ssHero" src="" alt="" />
    <div class="ss-steps-overlay" id="ssStepsOverlay" style="display:none">
      <div class="ss-step-item"><div class="ss-step-num">1</div><div class="ss-step-text" id="ssStepText1"></div></div>
      <div class="ss-step-item"><div class="ss-step-num">2</div><div class="ss-step-text" id="ssStepText2"></div></div>
      <div class="ss-step-item"><div class="ss-step-num">3</div><div class="ss-step-text" id="ssStepText3"></div></div>
    </div>
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
<script src="js/core/zapsheetsCore.js?v=2"></script>
<script>
var data = {};

function getSheetId() {
  if (window._gvSheetId) return window._gvSheetId;
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

function getViewUrl(game) {
  // Only use the token-based public game page URL (no sheet ID exposed)
  if (window._gvGameToken) {
    return window.location.origin + APP_BASE + 'game/' + window._gvGameToken;
  }
  return '';
}

function cachedImage(url) {
  if (!url) return '';
  // Prefer the server index (md5-based filename written by cacheSlideImages.php)
  if (data.cacheIdx && data.cacheIdx[url]) return APP_BASE + data.cacheIdx[url];
  // Fallback: derive from URL (works for Google Drive; approximate for others)
  if (url.indexOf('https://drive.google.com') === 0) {
    var imgid = url.split('https://drive.google.com')[1].split('/')[3];
    return BASE + 'cache/' + imgid + '.png';
  }
  var parts = url.split('/');
  var raw   = parts[parts.length - 1];
  var fname = raw.indexOf('?') !== -1 ? raw.split('?')[0] : raw;
  return BASE + 'cache/' + fname;
}

// directImageUrl() — canonical definition in js/core/zapsheetsCore.js; inline fallback below
if (typeof directImageUrl !== 'function') {
  window.directImageUrl = function(url) {
    if (!url) return url;
    if (url.indexOf('dropbox.com') !== -1) {
      if (url.match(/[?&]dl=/)) return url.replace(/([?&])dl=[^&]*/g, '$1raw=1');
      return url + (url.indexOf('?') !== -1 ? '&' : '?') + 'raw=1';
    }
    if (url.indexOf('drive.google.com') !== -1 || url.indexOf('docs.google.com') !== -1) {
      var _m = url.match(/\/file\/d\/([A-Za-z0-9_-]+)/);
      if (_m) return 'https://drive.google.com/uc?export=view&id=' + _m[1];
      _m = url.match(/[?&]id=([A-Za-z0-9_-]+)/);
      if (_m) return 'https://drive.google.com/uc?export=view&id=' + _m[1];
    }
    return url;
  };
}

// Normalise a URL: strip Markdown [label](url) or [url] wrappers, ensure absolute.
function _absUrl(url) {
  if (!url) return '';
  url = String(url).trim();
  var md = url.match(/^\[.*?\]\((.+)\)\s*$/);
  if (md) url = md[1].trim();
  var br = url.match(/^\[(.+)\]\s*$/);
  if (br) url = br[1].trim();
  if (!url) return '';
  return /^https?:\/\//i.test(url) ? url : 'https://' + url;
}

// Try each src in order; move to next on error.
function _setImgWithFallbacks(el, srcs) {
  var idx = 0;
  function tryNext() {
    if (idx >= srcs.length) return; // all failed — leave broken
    var s = srcs[idx++];
    if (!s) { tryNext(); return; }
    el.onerror = function() { el.onerror = null; tryNext(); };
    el.src = s;
  }
  tryNext();
}

var _loadTotal = 6, _loadDone = 0;  // +1 for cache/index.json
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
  if (window._gvGame) return window._gvGame;
  var m = window.location.search.match(/[?&]game=([^&]+)/);
  return m ? decodeURIComponent(m[1]) : '';
})();
var _gameFile = _gameParam ? ('game-' + _gameParam + '-' + lang + '.json') : ('game-' + lang + '.json');

_jsonLoad(BASE + 'cache/index.json',         'cacheIdx'); // url → cached-path map
_jsonLoad(BASE + 'settings.json',           'settings');
_jsonLoad(BASE + _gameFile,                 'bgg');
_jsonLoad(BASE + 'splash-' + lang + '.json','splash');
_jsonLoad(BASE + 'bgg.json',                'stats');
_jsonLoad(BASE + 'games.json',              'games');

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

  // ── games.json lookup ────────────────────────────────────────
  var _gameRow = null;
  if (data.games && _gameParam) {
    _gameRow = data.games.find(function(g) {
      return g.Name && g.Name.toLowerCase() === _gameParam.toLowerCase();
    }) || null;
  }
  function _games(key) {
    return (_gameRow && _gameRow[key] && String(_gameRow[key]).trim()) || '';
  }

  // ── Title ────────────────────────────────────────────────────
  var title = _games('Name') || gv('Title') || cfg['Title'] || basic['name'] || 'Game';
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

  // ── QR code in header (→ /game/{token} public URL) ───────
  var viewUrl = getViewUrl(_gameParam || title);
  if (viewUrl && typeof QRCode !== 'undefined') {
    new QRCode(document.getElementById('ssQR'), {
      text: viewUrl,
      width: 72, height: 72,
      colorDark: '#000000', colorLight: '#ffffff',
      correctLevel: QRCode.CorrectLevel.M
    });
  }

  // ── Hero image ─────────────────────────────────────────────
  // Priority chain (cached first, direct fallback):
  //   1. PitchImageUrl (game JSON)  2. Image URL (games.json)  3. splash image
  // Each source tries its cached path, then its direct URL, before moving on.
  var _pitchImg = _absUrl(gv('PitchImageUrl'));
  var _gameImg  = _absUrl(_games('Image URL'));
  var _splashUrl = '';
  if (!_pitchImg && !_gameImg && data.splash) {
    var _splashRow = data.splash.find(function(r){
      return (r.ID || '').toLowerCase() === 'layout' && r.Content;
    });
    if (!_splashRow) _splashRow = data.splash.find(function(r){
      return (r.Type || '').toLowerCase() === 'image' && r.Content;
    });
    if (_splashRow) _splashUrl = _splashRow.Content;
  }

  // Build ordered fallback list: cached path then direct URL for each candidate.
  var _heroSrcs = [];
  [_pitchImg, _gameImg, _splashUrl].forEach(function(url) {
    if (!url) return;
    var cached = cachedImage(url);
    var direct = directImageUrl(url);
    _heroSrcs.push(cached);
    if (direct && direct !== cached) _heroSrcs.push(direct);
  });

  if (_heroSrcs.length) {
    var _heroEl = document.getElementById('ssHero');
    _setImgWithFallbacks(_heroEl, _heroSrcs);
    document.getElementById('ssHeroWrap').style.display = '';
    // ── Steps overlay ──────────────────────────────────────────
    var _ssStepVal = function(name) {
      var r = (data.bgg || []).find(function(r) { return r.Name === name && r.Value; });
      return r ? r.Value.trim() : '';
    };
    var _ss1 = _ssStepVal('Step 1'), _ss2 = _ssStepVal('Step 2'), _ss3 = _ssStepVal('Step 3');
    if (_ss1 || _ss2 || _ss3) {
      document.getElementById('ssStepText1').textContent = _ss1;
      document.getElementById('ssStepText2').textContent = _ss2;
      document.getElementById('ssStepText3').textContent = _ss3;
      document.getElementById('ssStepsOverlay').style.display = 'flex';
    }
  }

  // ── Subtitle at top of body left ──────────────────────────
  var sub = _games('Tagline') || gv('Tagline') || gv('SubTitle') || gv('Subtitle');
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
  var desc = gv('PitchDescription') || _games('Description') || gv('Description') || (bg['description'] || '');
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

// Show the back bar only when running as a Home Screen / PWA app
(function() {
  var standalone = (window.navigator.standalone === true)
                || window.matchMedia('(display-mode: standalone)').matches;
  if (standalone) {
    var bar = document.getElementById('pwaBackBar');
    bar.style.display = 'block';
    document.body.style.paddingTop = bar.offsetHeight + 'px';
    document.getElementById('pwaBackBtn').addEventListener('click', function() {
      if (history.length > 1) {
        history.back();
      } else {
        // Tab was opened fresh via window.open() — close it to return to the /view tab.
        window.close();
        // Fallback if close() is blocked (fires only if window is still open):
        setTimeout(function() {
          window.location.href = window.location.pathname.replace(/\/sellsheet\/?$/, '/view');
        }, 150);
      }
    });
  }
})();
</script>
</body>
</html>
