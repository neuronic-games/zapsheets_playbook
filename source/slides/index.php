<?php
$_rp = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('#^(.*?)(?:sheets/)?([A-Za-z0-9_\-]+)/pitchboard/slides/?$#', $_rp, $_bm);
$_base     = (isset($_bm[1]) && $_bm[1] !== '') ? $_bm[1] : '/';
if (substr($_base, -1) !== '/') $_base .= '/';
$_sheet_id = $_bm[2] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
<title>Slides · PitchBoard</title>
<link rel="apple-touch-icon" sizes="180x180" href="images/pitchboard_icon_180.png" />
<link rel="icon" type="image/png" href="images/pitchboard_icon_192.png" />
<link rel="manifest" href="<?= htmlspecialchars($_base) ?>manifest.php?app=slides&id=<?= urlencode($_sheet_id) ?>&base=<?= urlencode($_base) ?>" />
<style>
  @font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2')  format('woff2'),url('fonts/DINBlack.ttf'); }
  @font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf'); }

  *, *::before, *::after { box-sizing: border-box; }

  html, body {
    margin: 0; padding: 0;
    width: 100%; height: 100%;
    overflow: hidden;
    background: #0a0a14;
    font-family: 'DINRegular', Arial, sans-serif;
    -webkit-tap-highlight-color: transparent;
    -webkit-user-select: none;
    user-select: none;
  }

  /* ── Outer container ─────────────────────────────────────── */
  #slideshow {
    position: fixed; top: 0; right: 0; bottom: 0; left: 0;
    overflow: hidden;
  }

  /* ── Slides track (horizontal flex, shifted by translateX) ─ */
  #slides-track {
    position: absolute; top: 0; right: 0; bottom: 0; left: 0;
    display: flex;
    will-change: transform;
  }
  #slides-track.animating {
    transition: transform .38s cubic-bezier(.25,.46,.45,.94);
  }

  /* ── Individual slide ────────────────────────────────────── */
  .slide {
    flex: 0 0 100%;
    height: 100%;
    position: relative;
    overflow: hidden;
  }
  .slide-bg {
    position: absolute; top: 0; right: 0; bottom: 0; left: 0;
    overflow: hidden;
  }
  /* <img> fills the slide, Ken Burns applied here */
  .slide-img {
    width: 100%; height: 100%;
    object-fit: cover;
    object-position: center;
    display: block;
    transition: transform 8s ease-in-out;
    -webkit-transition: -webkit-transform 8s ease-in-out;
  }
  /* Ken Burns: active slide slowly zooms in */
  .slide.active .slide-img { transform: scale(1.06); -webkit-transform: scale(1.06); }
  .slide:not(.active) .slide-img { transform: scale(1); -webkit-transform: scale(1); }

  /* Dark gradient from bottom */
  .slide-grad {
    position: absolute; top: 0; right: 0; bottom: 0; left: 0;
    background: linear-gradient(
      to top,
      rgba(0,0,0,.92) 0%,
      rgba(0,0,0,.55) 38%,
      rgba(0,0,0,.12) 68%,
      transparent     100%
    );
  }

  /* ── Text info ───────────────────────────────────────────── */
  .slide-info {
    position: absolute;
    bottom: 0; left: 0; right: 0;
    padding: 3.5rem 1.5rem 1.5rem;
    padding-bottom: max(1.5rem, env(safe-area-inset-bottom, 1.5rem));
  }
  .slide-name {
    font-family: 'DINBlack', sans-serif;
    font-size: clamp(1.5rem, 7vw, 2.4rem);
    color: #fff;
    line-height: 1.15;
    margin: 0 0 .35rem;
    text-shadow: 0 2px 12px rgba(0,0,0,.7);
  }
  .slide-name a {
    color: inherit;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    /* Generous tap/click padding around the icon */
    padding: .4em .5em;
    margin-left: .2em;
    margin-bottom: -.4em; /* optical alignment with name baseline */
    vertical-align: middle;
    border-radius: .3em;
  }
  .slide-name a:active,
  .slide-name a:hover { opacity: .65; }
  .slide-page-eye {
    width: .9em;
    height: .9em;
    flex-shrink: 0;
    opacity: .8;
    stroke: currentColor;
    fill: none;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
  }
  .slide-tagline {
    font-size: clamp(.85rem, 4vw, 1.05rem);
    color: rgba(255,255,255,.85);
    margin: 0 0 .55rem;
    line-height: 1.45;
    text-shadow: 0 1px 6px rgba(0,0,0,.55);
  }
  .slide-designers {
    font-size: .75rem;
    color: rgba(255,255,255,.5);
    margin: 0;
    letter-spacing: .03em;
  }

  /* ── Progress bar (top edge) ─────────────────────────────── */
  #progress-bar {
    position: absolute; top: 0; left: 0; right: 0;
    height: 3px;
    background: rgba(255,255,255,.18);
    z-index: 20;
  }
  #progress-fill {
    height: 100%;
    width: 0;
    background: #c8860a;
  }

  /* ── Slide counter ───────────────────────────────────────── */
  #counter {
    position: absolute; top: 12px; right: 14px;
    font-size: .7rem;
    color: rgba(255,255,255,.5);
    letter-spacing: .04em;
    z-index: 20;
    pointer-events: none;
  }

  /* ── Dots ────────────────────────────────────────────────── */
  #dots {
    position: absolute;
    top: 20px;
    left: 0; right: 0;
    display: flex;
    justify-content: center;
    gap: .4rem;
    z-index: 20;
    pointer-events: none;
  }
  .dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: rgba(255,255,255,.35);
    transition: background .25s, transform .25s;
    flex-shrink: 0;
  }
  .dot.active {
    background: #fff;
    transform: scale(1.5);
  }
  /* Orange dot = image confirmed in local cache */
  .dot.cached        { background: rgba(200,134,10,.65); }
  .dot.active.cached { background: #c8860a; }

  /* ── Pause / play overlay ────────────────────────────────── */
  #pause-icon {
    position: absolute; top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(0,0,0,.55);
    border-radius: 50%;
    width: 68px; height: 68px;
    display: flex; align-items: center; justify-content: center;
    z-index: 30;
    opacity: 0;
    transition: opacity .2s;
    pointer-events: none;
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
  }
  #pause-icon.show { opacity: 1; }
  #pause-icon svg { width: 26px; height: 26px; fill: #fff; }

  /* ── Tap zones (left 30% / right 30%) ───────────────────── */
  /* pointer-events:none so elements inside #slides-track (e.g. the eye-icon link)
     receive mouse clicks directly; desktop navigation is handled by the
     #slideshow click handler that checks the x coordinate. */
  .tap-zone {
    position: absolute; top: 0; bottom: 0;
    width: 28%;
    z-index: 15;
    cursor: pointer;
    pointer-events: none;
  }
  #tap-prev { left: 0; }
  #tap-next { right: 0; }

  /* ── Loading spinner ─────────────────────────────────────── */
  #loading {
    position: absolute; top: 0; right: 0; bottom: 0; left: 0;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: 1rem;
    color: rgba(255,255,255,.45);
    font-size: .85rem; letter-spacing: .07em;
    text-transform: uppercase;
    z-index: 50;
  }
  .spinner {
    width: 32px; height: 32px;
    border: 2px solid rgba(255,255,255,.15);
    border-top-color: #c8860a;
    border-radius: 50%;
    animation: spin .8s linear infinite;
  }
  @keyframes spin { to { transform: rotate(360deg); } }

  /* ── Back button (shown when ?back=1, i.e. opened from dashboard) ── */
  #back-btn {
    display: none;
    position: absolute;
    top: 14px; left: 14px;
    z-index: 25;
    align-items: center;
    gap: .3rem;
    background: rgba(0,0,0,.42);
    border: 1px solid rgba(255,255,255,.18);
    border-radius: 20px;
    padding: .28rem .7rem .28rem .5rem;
    color: rgba(255,255,255,.75);
    font-family: 'DINRegular', Arial, sans-serif;
    font-size: .72rem;
    letter-spacing: .04em;
    cursor: pointer;
    text-decoration: none;
    backdrop-filter: blur(6px);
    -webkit-backdrop-filter: blur(6px);
    transition: background .15s, color .15s;
    -webkit-tap-highlight-color: transparent;
  }
  #back-btn:hover { background: rgba(0,0,0,.62); color: #fff; }
  #back-btn svg { flex-shrink: 0; }

  /* ── Empty state ─────────────────────────────────────────── */
  #empty-state {
    position: absolute; top: 0; right: 0; bottom: 0; left: 0;
    display: none;
    flex-direction: column;
    align-items: center; justify-content: center;
    gap: 1rem;
    color: rgba(255,255,255,.4);
    text-align: center;
    padding: 2rem;
    z-index: 50;
  }
  #empty-state svg { opacity: .25; }
  #empty-state .empty-title {
    font-family: 'DINBlack', sans-serif;
    font-size: 1rem; color: rgba(255,255,255,.6);
    margin: 0; letter-spacing: .02em;
  }
  #empty-state .empty-sub {
    font-size: .82rem; line-height: 1.6;
    color: rgba(255,255,255,.35); margin: 0;
  }
  /* ── Pick the games: a panel that pulls down from the top ── */
  #pick-tab {
    position: fixed; top: 3px; left: 50%; transform: translateX(-50%);
    z-index: 40; padding: 3px 18px 10px;   /* a bigger target than the bar itself */
    cursor: grab; touch-action: none; -webkit-tap-highlight-color: transparent; transition: opacity .2s;
  }
  #pick-tab::before { content: ''; display: block; width: 30px; height: 3px; border-radius: 2px; background: rgba(255,255,255,.28); transition: background .15s, width .15s; }
  #pick-tab:hover::before, #pick-tab:focus-visible::before { background: rgba(255,255,255,.7); width: 38px; }
  #pick-tab:focus { outline: none; }
  body.pick-open #pick-tab { opacity: 0; pointer-events: none; }
  #pick-shade { position: fixed; inset: 0; z-index: 44; background: rgba(0,0,0,.45); opacity: 0; pointer-events: none; transition: opacity .25s; }
  body.pick-open #pick-shade { opacity: 1; pointer-events: auto; }
  #pick {
    position: fixed; top: 0; left: 50%; z-index: 45;
    width: min(520px, 100%); max-height: 78vh; display: flex; flex-direction: column;
    background: rgba(22,22,36,.97); border: 1px solid rgba(255,255,255,.1); border-top: none;
    border-radius: 0 0 18px 18px; box-shadow: 0 12px 40px rgba(0,0,0,.55);
    transform: translate(-50%, -100%); transition: transform .28s cubic-bezier(.25,.46,.45,.94);
    padding-top: env(safe-area-inset-top, 0px);
    -webkit-user-select: none; user-select: none;
  }
  body.pick-open #pick { transform: translate(-50%, 0); }
  #pick.drag { transition: none; }
  .pick-hd { display: flex; align-items: center; gap: .5rem; padding: .9rem 1rem .6rem; color: #fff; }
  .pick-hd b { font-family: 'DINBlack', sans-serif; font-weight: normal; font-size: .95rem; letter-spacing: .02em; }
  .pick-hd span { font-size: .72rem; color: rgba(255,255,255,.45); margin-right: auto; }
  .pick-hd button {
    font-family: 'DINBlack', sans-serif; font-size: .62rem; letter-spacing: .08em; text-transform: uppercase;
    background: transparent; color: rgba(255,255,255,.75); border: 1px solid rgba(255,255,255,.25);
    border-radius: 999px; padding: .3rem .7rem; cursor: pointer;
  }
  .pick-hd button:hover { color: #fff; border-color: rgba(255,255,255,.6); }
  .pick-hd #pick-done { background: #c8860a; border-color: #c8860a; color: #fff; }
  .pick-sub { font-size: .7rem; color: rgba(255,255,255,.4); padding: 0 1rem .5rem; }
  #pick-list { overflow-y: auto; padding: 0 .5rem .5rem; -webkit-overflow-scrolling: touch; }
  .pick-row {
    display: flex; align-items: center; gap: .65rem; padding: .4rem .5rem; margin: 2px 0;
    border-radius: 10px; color: #fff; cursor: pointer; background: rgba(255,255,255,.03);
  }
  .pick-row:hover { background: rgba(255,255,255,.07); }
  .pick-row.off { color: rgba(255,255,255,.45); }
  .pick-row.off .pick-img { opacity: .4; filter: grayscale(1); }
  .pick-row.nophoto { cursor: default; }
  .pick-row.dragging { background: rgba(200,134,10,.22); box-shadow: 0 6px 20px rgba(0,0,0,.4); position: relative; z-index: 2; }
  .pick-grip { flex: none; width: 22px; text-align: center; color: rgba(255,255,255,.35); font-size: 1rem; letter-spacing: -2px; cursor: grab; touch-action: none; padding: .35rem 0; }
  .pick-grip:hover { color: #fff; }
  .pick-num { flex: none; width: 1.3rem; text-align: right; font-size: .7rem; color: #c8860a; font-family: 'DINBlack', sans-serif; }
  .pick-row.off .pick-num { visibility: hidden; }
  .pick-img { flex: none; width: 46px; height: 32px; border-radius: 5px; background: #2a2a40 center/cover no-repeat; }
  .pick-nm { flex: 1; min-width: 0; display: flex; flex-direction: column; }
  .pick-nm b { font-family: 'DINBlack', sans-serif; font-weight: normal; font-size: .82rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .pick-nm small { font-size: .66rem; color: rgba(255,255,255,.4); }
  .pick-sw { flex: none; width: 34px; height: 20px; border-radius: 10px; background: rgba(255,255,255,.18); position: relative; transition: background .15s; }
  .pick-sw::after { content: ''; position: absolute; top: 2px; left: 2px; width: 16px; height: 16px; border-radius: 50%; background: #fff; transition: transform .15s; }
  .pick-row:not(.off) .pick-sw { background: #c8860a; }
  .pick-row:not(.off) .pick-sw::after { transform: translateX(14px); }
  .pick-row.nophoto .pick-sw { display: none; }
  .pick-bar { flex: none; height: 22px; display: flex; align-items: center; justify-content: center; cursor: grab; touch-action: none; }
  .pick-bar::before { content: ''; width: 40px; height: 4px; border-radius: 2px; background: rgba(255,255,255,.3); }
</style>
</head>
<body>

<div id="slideshow">
  <div id="slides-track"></div>

  <div id="progress-bar"><div id="progress-fill"></div></div>
  <div id="counter"></div>
  <div id="dots"></div>

  <div id="pause-icon">
    <!-- Swaps between pause and play SVG -->
    <svg id="pause-svg" viewBox="0 0 24 24"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
  </div>

  <div id="tap-prev" class="tap-zone" aria-label="Previous"></div>
  <div id="tap-next" class="tap-zone" aria-label="Next"></div>

  <a id="back-btn" href="#" role="button" aria-label="Back to dashboard">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
      <polyline points="15 18 9 12 15 6"/>
    </svg>
    Back
  </a>

  <div id="loading"><div class="spinner"></div>Loading</div>

  <div id="empty-state">
    <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.3"
         stroke-linecap="round" stroke-linejoin="round">
      <rect x="3" y="3" width="18" height="18" rx="2"/>
      <path d="M3 9h18M9 21V9"/>
    </svg>
    <p class="empty-title">No slides to show</p>
    <p class="empty-sub" id="empty-sub">Add games with <strong>Status = Pitching</strong><br>and a <strong>Photo URL</strong> or <strong>Image URL</strong>.</p>
  </div>
</div>

<!-- Pull down to pick the games and their order -->
<div id="pick-tab" role="button" tabindex="0" aria-label="Choose the games" title="Pull down to choose the games"></div>
<div id="pick-shade"></div>
<div id="pick" role="dialog" aria-label="Choose the games and their order">
  <div class="pick-hd"><b>Slides</b><span id="pick-n"></span><button type="button" id="pick-all">All</button><button type="button" id="pick-reset">Reset</button><button type="button" id="pick-done">Done</button></div>
  <div class="pick-sub">Tap a game to show or hide it · drag <span style="letter-spacing:-2px">⋮⋮</span> to change the order</div>
  <div id="pick-list"></div>
  <div class="pick-bar" id="pick-bar" aria-hidden="true"></div>
</div>

<script src="js/core/zapsheetsCore.js?v=2"></script>
<script>
(function () {
  'use strict';

  // ── Config ───────────────────────────────────────────────
  var AUTO_DELAY   = 7000;   // ms per slide before auto-advance
  var SWIPE_THRESH = 0.2;    // fraction of screen width to trigger a slide
  var ICON_LINGER  = 900;    // ms the pause/play icon stays visible

  // ── Sheet ID + data URL ──────────────────────────────────
  var APP_BASE = document.querySelector('base').getAttribute('href');
  var sheetId  = (function () {
    var m = window.location.pathname.match(/\/([A-Za-z0-9_\-]+)\/pitchboard\/slides/);
    return m ? m[1] : '';
  })();
  var BASE = APP_BASE + 'sheets/' + sheetId + '/';

  // ── Back button (only when opened from the dashboard via ?back=1) ──
  (function () {
    var params = new URLSearchParams(window.location.search);
    if (params.get('back') === '1') {
      var btn = document.getElementById('back-btn');
      if (btn) {
        btn.style.display = 'flex';
        btn.href = APP_BASE + sheetId + '/pitchboard';
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          window.location.href = APP_BASE + sheetId + '/pitchboard';
        });
      }
    }
  })();

  // ── State ────────────────────────────────────────────────
  var slides      = [];
  var current     = 0;
  var paused      = false;
  var progRaf     = null;
  var progStart   = null;
  var progTotal   = AUTO_DELAY;
  var iconTimer   = null;

  // ── IndexedDB image store (data URLs) ───────────────────────
  // Stores images as data-URL strings (base64) keyed by server URL.
  // Data URLs live in JS heap — unlike Object URLs, iOS cannot evict their
  // backing memory under pressure, so images never disappear mid-slideshow.
  var _idb = null;

  function openIDB() {
    if (_idb) return Promise.resolve(_idb);
    return new Promise(function (resolve, reject) {
      if (!('indexedDB' in window)) return reject(new Error('no idb'));
      // v2 DB name — clean break from the earlier blob-based v1 store
      var req = indexedDB.open('pb-img-store-v2', 1);
      req.onupgradeneeded = function (e) { e.target.result.createObjectStore('imgs'); };
      req.onsuccess = function (e) { _idb = e.target.result; resolve(_idb); };
      req.onerror   = function () { reject(req.error); };
    });
  }

  function idbGet(key) {
    return openIDB().then(function (db) {
      return new Promise(function (resolve) {
        var tx  = db.transaction('imgs', 'readonly');
        var req = tx.objectStore('imgs').get(key);
        req.onsuccess = function () { resolve(req.result || null); };
        req.onerror   = function () { resolve(null); };
      });
    }).catch(function () { return null; });
  }

  function idbPut(key, val) {
    return openIDB().then(function (db) {
      return new Promise(function (resolve) {
        var tx  = db.transaction('imgs', 'readwrite');
        var req = tx.objectStore('imgs').put(val, key);
        req.onsuccess = function () { resolve(); };
        req.onerror   = function () { resolve(); };
      });
    }).catch(function () {});
  }

  // Convert a Blob to a base64 data URL.
  function blobToDataUrl(blob) {
    return new Promise(function (resolve, reject) {
      var reader = new FileReader();
      reader.onload  = function () { resolve(reader.result); };
      reader.onerror = function () { reject(reader.error); };
      reader.readAsDataURL(blob);
    });
  }

  // Sets s._idbSrc (data URL string) on each slide whose image is in IDB.
  // Must run before buildDom() so img.src is set correctly on first paint.
  // Returns a Promise that resolves once all IDB lookups finish.
  function applyIDBImages() {
    return Promise.all(slides.map(function (s) {
      var url = resolveImage(s.photo).cached;
      if (!url) return Promise.resolve();
      return idbGet(url).then(function (dataUrl) {
        if (!dataUrl || typeof dataUrl !== 'string') return;
        s._idbSrc = dataUrl;
      });
    })).catch(function () {});
  }

  // ── Element refs ─────────────────────────────────────────
  var trackEl    = document.getElementById('slides-track');
  var dotsEl     = document.getElementById('dots');
  var fillEl     = document.getElementById('progress-fill');
  var counterEl  = document.getElementById('counter');
  var pauseEl    = document.getElementById('pause-icon');
  var pauseSvg   = document.getElementById('pause-svg');
  var emptyEl    = document.getElementById('empty-state');
  var loadingEl  = document.getElementById('loading');

  var PAUSE_SVG = '<rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/>';
  var PLAY_SVG  = '<path d="M5 3l14 9-14 9V3z"/>';

  // ── Image URL helpers ────────────────────────────────────
  // Strip =IMAGE("url") Google Sheets formula wrapper if present.
  function stripImageFormula(val) {
    if (!val) return '';
    var m = val.match(/^=IMAGE\("([^"]*)"\)/i);
    return m ? m[1] : val;
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
  function resolveImage(url) {
    if (!url) return { cached: '', direct: '' };
    url = url.trim();
    var direct = directImageUrl(url);
    // Dropbox: use the converted URL for both cache key and direct src
    if (url.indexOf('dropbox.com') !== -1) {
      return { cached: direct, direct: direct };
    }
    // Google Drive: keep local cache path; use converted uc?export=view as direct fallback
    if (url.indexOf('drive.google.com') !== -1 || url.indexOf('docs.google.com') !== -1) {
      var _m = url.match(/\/file\/d\/([A-Za-z0-9_-]+)/);
      var imgid = _m ? _m[1] : '';
      if (!imgid) { _m = url.match(/[?&]id=([A-Za-z0-9_-]+)/); imgid = _m ? _m[1] : ''; }
      var cached = imgid ? BASE + 'cache/' + imgid + '.png' : direct;
      return { cached: cached, direct: direct };
    }
    // Everything else: strip query/hash for cached guess, use original as direct
    return { cached: url.split('?')[0].split('#')[0], direct: url };
  }

  // ── Build slide DOM ──────────────────────────────────────
  function buildDom() {
    trackEl.innerHTML = '';

    slides.forEach(function (s, i) {
      var wrap = document.createElement('div');
      wrap.className = 'slide' + (i === 0 ? ' active' : '');

      // Background photo — use <img> (more reliable on iOS than background-image)
      var bg = document.createElement('div');
      bg.className = 'slide-bg';
      var urls = resolveImage(s.photo);
      var img = document.createElement('img');
      img.className = 'slide-img';
      img.alt = '';
      img.setAttribute('decoding', 'async');
      img.src = s._idbSrc || urls.cached;
      if (s._idbSrc) {
        // IDB blob — if it somehow fails to render, fall back to the normal URL
        img.onerror = (function (el, fb) {
          return function () { el.onerror = null; el.src = fb; };
        })(img, urls.cached);
      } else if (urls.direct && urls.cached !== urls.direct) {
        img.onerror = (function (el, src) {
          return function () { el.onerror = null; el.src = src; };
        })(img, urls.direct);
      }
      bg.appendChild(img);
      wrap.appendChild(bg);

      // Dark gradient overlay
      var grad = document.createElement('div');
      grad.className = 'slide-grad';
      wrap.appendChild(grad);

      // Text info
      var info = document.createElement('div');
      info.className = 'slide-info';

      var nameEl = document.createElement('p');
      nameEl.className = 'slide-name';
      nameEl.textContent = s.name;
      if (s.page) {
        // Only the eye icon is the link — name text is not interactive.
        // Same-origin pages navigate in the same window so history.back() works.
        // External URLs open in a new tab.
        var _isSameOrigin = s.page.indexOf(window.location.origin + '/') === 0 ||
                            s.page.charAt(0) === '/';
        var nameLink = document.createElement('a');
        nameLink.href      = s.page;
        nameLink.className = 'slide-page-link';
        nameLink.setAttribute('aria-label', 'View game page');
        if (!_isSameOrigin) {
          nameLink.target = '_blank';
          nameLink.rel    = 'noopener noreferrer';
        }
        var eyeSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        eyeSvg.setAttribute('class', 'slide-page-eye');
        eyeSvg.setAttribute('viewBox', '0 0 24 24');
        eyeSvg.setAttribute('aria-hidden', 'true');
        eyeSvg.innerHTML =
          '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>' +
          '<circle cx="12" cy="12" r="3"/>';
        nameLink.appendChild(eyeSvg);
        nameEl.appendChild(nameLink);
      }
      info.appendChild(nameEl);

      if (s.tagline) {
        var tagEl = document.createElement('p');
        tagEl.className = 'slide-tagline';
        tagEl.textContent = s.tagline;
        info.appendChild(tagEl);
      }
      if (s.designers) {
        var desEl = document.createElement('p');
        desEl.className = 'slide-designers';
        desEl.textContent = s.designers;
        info.appendChild(desEl);
      }
      wrap.appendChild(info);
      trackEl.appendChild(wrap);
    });

    // Dots
    if (slides.length > 1) {
      dotsEl.innerHTML = slides.map(function (_, i) {
        return '<div class="dot' + (i === 0 ? ' active' : '') + '"></div>';
      }).join('');
      dotsEl.style.display = '';
    } else {
      dotsEl.innerHTML = '';
      dotsEl.style.display = 'none';
    }
  }

  // ── Navigation ───────────────────────────────────────────
  function setSlide(idx, animate) {
    current = ((idx % slides.length) + slides.length) % slides.length;

    if (animate !== false) {
      trackEl.classList.add('animating');
      setTimeout(function () { trackEl.classList.remove('animating'); }, 420);
    } else {
      trackEl.classList.remove('animating');
    }
    trackEl.style.transform = 'translateX(' + (-current * 100) + '%)';

    // Active class
    trackEl.querySelectorAll('.slide').forEach(function (s, i) {
      s.classList.toggle('active', i === current);
    });
    // Dots
    dotsEl.querySelectorAll('.dot').forEach(function (d, i) {
      d.classList.toggle('active', i === current);
    });
    // Counter
    counterEl.textContent = (current + 1) + ' / ' + slides.length;
  }

  function next() { setSlide(current + 1, true); resetProgress(); }
  function prev() { setSlide(current - 1, true); resetProgress(); }

  // ── Progress bar (rAF-based) ─────────────────────────────
  function resetProgress() {
    cancelAnimationFrame(progRaf);
    progStart = null;
    fillEl.style.transition = 'none';
    fillEl.style.width = '0%';
    if (!paused) startProgress();
  }

  function startProgress() {
    cancelAnimationFrame(progRaf);
    progStart = null;
    function tick(ts) {
      if (!progStart) progStart = ts;
      var elapsed = ts - progStart;
      var pct = Math.min(100, (elapsed / AUTO_DELAY) * 100);
      fillEl.style.width = pct + '%';
      if (elapsed >= AUTO_DELAY) {
        next();
        return;
      }
      progRaf = requestAnimationFrame(tick);
    }
    progRaf = requestAnimationFrame(tick);
  }

  function stopProgress() {
    cancelAnimationFrame(progRaf);
    progRaf = null;
  }

  // ── Pause / resume ───────────────────────────────────────
  function showIcon(isPaused) {
    pauseSvg.innerHTML = isPaused ? PAUSE_SVG : PLAY_SVG;
    pauseEl.classList.add('show');
    clearTimeout(iconTimer);
    iconTimer = setTimeout(function () { pauseEl.classList.remove('show'); }, ICON_LINGER);
  }

  function togglePause() {
    paused = !paused;
    if (paused) { stopProgress(); }
    else { resetProgress(); }
    showIcon(paused);
  }

  // ── Touch handling ───────────────────────────────────────
  var touchX0 = null;
  var touchY0 = null;
  var swiping  = false;

  trackEl.addEventListener('touchstart', function (e) {
    if (e.touches.length !== 1) return;
    touchX0 = e.touches[0].clientX;
    touchY0 = e.touches[0].clientY;
    swiping = false;
    trackEl.classList.remove('animating');
  }, { passive: true });

  trackEl.addEventListener('touchmove', function (e) {
    if (touchX0 == null) return;
    var dx = e.touches[0].clientX - touchX0;
    var dy = e.touches[0].clientY - touchY0;
    if (!swiping) {
      if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 10) {
        swiping = true;
        stopProgress();
      } else if (Math.abs(dy) > 10) {
        // Vertical scroll intent — abort
        touchX0 = null; return;
      }
    }
    if (swiping) {
      e.preventDefault();
      trackEl.style.transform =
        'translateX(calc(' + (-current * 100) + '% + ' + dx + 'px))';
    }
  }, { passive: false });

  trackEl.addEventListener('touchend', function (e) {
    if (touchX0 == null) return;
    var dx = e.changedTouches[0].clientX - touchX0;
    touchX0 = null; touchY0 = null;

    if (!swiping) {
      // Let taps on the game-page link pass through to the browser
      var tgt = e.target;
      if (tgt && tgt.closest && tgt.closest('.slide-page-link')) return;
      // Treat as a tap
      var x = e.changedTouches[0].clientX;
      var w = window.innerWidth;
      if (x < w * 0.28)      { prev(); }
      else if (x > w * 0.72) { next(); }
      else                    { togglePause(); }
      return;
    }
    swiping = false;
    if (dx < -window.innerWidth * SWIPE_THRESH)       { next(); }
    else if (dx > window.innerWidth * SWIPE_THRESH)   { prev(); }
    else {
      // Snap back
      setSlide(current, true);
      if (!paused) startProgress();
    }
  }, { passive: true });

  // Prevent context menu on long-press (phone)
  document.addEventListener('contextmenu', function (e) { e.preventDefault(); });

  // ── Desktop click navigation ─────────────────────────────
  // Tap zones have pointer-events:none so the eye-icon link inside the slide
  // receives clicks directly. We delegate prev/next to a single handler on
  // #slideshow that mirrors the touch handler's coordinate logic.
  document.getElementById('slideshow').addEventListener('click', function (e) {
    // Let clicks on the game-page eye link pass through to the browser
    if (e.target && e.target.closest && e.target.closest('.slide-page-link')) return;
    var x = e.clientX;
    var w = window.innerWidth;
    if (x < w * 0.28)      { prev(); }
    else if (x > w * 0.72) { next(); }
    // centre click: no action on desktop (pause/play is touch-only)
  });

  // ── Keyboard nav ─────────────────────────────────────────
  document.addEventListener('keydown', function (e) {
    if (document.body.classList.contains('pick-open')) { if (e.key === 'Escape') { e.preventDefault(); closePick(); } return; }
    if (e.key === 'g' || e.key === 'G') { e.preventDefault(); openPick(); return; }
    if (!slides.length) return;
    if (e.key === 'ArrowRight' || e.key === 'ArrowDown')  { e.preventDefault(); next(); }
    if (e.key === 'ArrowLeft'  || e.key === 'ArrowUp')    { e.preventDefault(); prev(); }
    if (e.key === ' ')                                     { e.preventDefault(); togglePause(); }
    if (e.key === 'f' || e.key === 'F') {
      // Full-screen toggle
      if (!document.fullscreenElement) document.documentElement.requestFullscreen().catch(function(){});
      else document.exitFullscreen().catch(function(){});
    }
  });

  // ── Visibility: pause when tab is hidden ─────────────────
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) { stopProgress(); }
    else if (!paused && slides.length && !document.body.classList.contains('pick-open')) { resetProgress(); }
  });

  // ── Empty / error ─────────────────────────────────────────
  function showEmpty() {
    loadingEl.style.display = 'none';
    emptyEl.style.display   = 'flex';
    document.getElementById('progress-bar').style.display = 'none';
    counterEl.style.display = 'none';
    dotsEl.style.display = 'none';
    trackEl.innerHTML = '';
    stopProgress();
  }
  function hideEmpty() {
    emptyEl.style.display = 'none';
    document.getElementById('progress-bar').style.display = '';
    counterEl.style.display = '';
  }

  // ── Load games.json ──────────────────────────────────────
  var xhr = new XMLHttpRequest();
  xhr.open('GET', BASE + 'games.json?v=' + Date.now());
  xhr.onload = function () {
    loadingEl.style.display = 'none';
    if (xhr.status !== 200) { showEmpty(); return; }
    var games;
    try { games = JSON.parse(xhr.responseText); } catch (e) { showEmpty(); return; }
    if (!Array.isArray(games)) { showEmpty(); return; }

    // Every game with a name: the ones with a picture can be slides
    allGames = games
      .filter(function (g) { return (g['Name'] || '').trim(); })
      .map(function (g) {
        var designers = ['Designer1', 'Designer2', 'Designer3', 'Designer4']
          .map(function (f) { return (g[f] || '').trim(); })
          .filter(Boolean).join(', ');
        return {
          name:      (g['Name']    || '').trim(),
          tagline:   (g['Tagline'] || '').trim(),
          photo:     stripImageFormula((g['Photo URL'] || g['Image URL'] || g['Image'] || '').trim()),
          designers: designers,
          page:      (g['Page URL'] || '').trim(),
          status:    (g['Status']  || '').trim(),
        };
      });

    // If a previous online session cached images server-side, swap Dropbox URLs
    // to same-origin server URLs now — before buildDom sets img.src — so the SW
    // can serve them from cache even when the device is offline.
    (function applyUrlMapping() {
      try {
        var stored = localStorage.getItem('pb_urlmap_' + sheetId);
        if (!stored) return;
        var map = JSON.parse(stored);
        allGames.forEach(function (s) {
          if (map[s.photo]) s.photo = map[s.photo];
        });
      } catch (e) {}
    })();

    applyPick();
  };
  // ── The games shown, and their order: yours to choose (pull down from the top) ──
  // Kept per sheet in this browser: {order: [names], on: [names]}. Until you choose, it's the
  // Pitching games with a picture, in the sheet's order; new games join at the end the same way.
  var allGames = [];
  var PICK_KEY = 'pb_slides_pick_' + sheetId;
  function defaultOn(g) { return !!g.photo && g.status.toLowerCase() === 'pitching'; }
  function loadPick() { try { var p = JSON.parse(localStorage.getItem(PICK_KEY) || 'null'); return p && Array.isArray(p.order) && Array.isArray(p.on) ? p : null; } catch (e) { return null; } }
  function savePick(p) { try { if (p) localStorage.setItem(PICK_KEY, JSON.stringify(p)); else localStorage.removeItem(PICK_KEY); } catch (e) {} }
  // The games in the chosen order, each with whether it's shown
  function pickedList() {
    var p = loadPick(), byName = {}, out = [];
    allGames.forEach(function (g) { byName[g.name.toLowerCase()] = g; });
    if (p) {
      var on = {}; p.on.forEach(function (n) { on[String(n).toLowerCase()] = 1; });
      p.order.forEach(function (n) {
        var g = byName[String(n).toLowerCase()]; if (!g || g._seen) return;
        g._seen = 1; out.push({ g: g, on: !!on[g.name.toLowerCase()] && !!g.photo });
      });
    }
    allGames.forEach(function (g) { if (!g._seen) out.push({ g: g, on: defaultOn(g) }); delete g._seen; });
    out.forEach(function (x) { delete x.g._seen; });
    return out;
  }
  var _shownOnce = false;
  function applyPick(keepName) {
    slides = pickedList().filter(function (x) { return x.on; }).map(function (x) { return x.g; });
    renderPickList();
    if (!slides.length) {
      var sub = document.getElementById('empty-sub');
      if (sub && allGames.some(function (g) { return g.photo; })) sub.innerHTML = 'No games picked. Pull down the bar at the top to choose them.';
      showEmpty(); return;
    }
    hideEmpty();
    var start = 0;
    if (keepName) slides.forEach(function (s, i) { if (s.name === keepName) start = i; });
    // Apply any IDB-stored images before building the DOM — this makes images work offline
    // on the very first paint without relying on the service worker.
    applyIDBImages().then(function () {
      buildDom();
      setSlide(start, false);
      if (!document.body.classList.contains('pick-open')) resetProgress();
      updateDotCacheStatus();
      prefetchImages().then(function () {
        setTimeout(updateDotCacheStatus, 400);
      });
      cacheOnServer();
    });
  }
  var pickEl = document.getElementById('pick'), pickList = document.getElementById('pick-list');
  function escH(t) { return String(t || '').replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function renderPickList() {
    var list = pickedList(), n = 0;
    pickList.innerHTML = list.map(function (x) {
      var g = x.g, u = g.photo ? (g._idbSrc || resolveImage(g.photo).cached) : '';
      var num = x.on ? ++n : '';
      return '<div class="pick-row' + (x.on ? '' : ' off') + (g.photo ? '' : ' nophoto') + '" data-name="' + escH(g.name) + '">' +
        '<span class="pick-grip" title="Drag to reorder">⋮⋮</span><span class="pick-num">' + num + '</span>' +
        '<span class="pick-img"' + (u ? ' style="background-image:url(&quot;' + escH(u) + '&quot;)"' : '') + '></span>' +
        '<span class="pick-nm"><b>' + escH(g.name) + '</b><small>' + escH(g.photo ? (g.status || 'No status') : 'No picture — add a Photo URL to show it') + '</small></span>' +
        '<span class="pick-sw" aria-hidden="true"></span></div>';
    }).join('');
    document.getElementById('pick-n').textContent = n + ' of ' + list.filter(function (x) { return x.g.photo; }).length + ' shown';
  }
  // The list as it stands on screen → saved
  function savePickFromDom() {
    var order = [], on = [];
    pickList.querySelectorAll('.pick-row').forEach(function (r) { order.push(r.dataset.name); if (!r.classList.contains('off')) on.push(r.dataset.name); });
    savePick({ order: order, on: on });
    _pickChanged = true;
  }
  var _pickChanged = false;
  function openPick() {
    if (document.body.classList.contains('pick-open')) return;
    renderPickList();
    _pickChanged = false;
    pickEl.classList.remove('drag'); pickEl.style.transform = '';
    document.body.classList.add('pick-open');
    stopProgress();
  }
  function closePick() {
    pickEl.classList.remove('drag'); pickEl.style.transform = '';
    if (!document.body.classList.contains('pick-open')) return;
    document.body.classList.remove('pick-open');
    if (_pickChanged) { _pickChanged = false; applyPick(slides[current] && slides[current].name); }
    else if (!paused && slides.length) resetProgress();
  }
  window.openPick = openPick;
  pickList.addEventListener('click', function (e) {
    if (e.target.closest('.pick-grip')) return;
    var r = e.target.closest('.pick-row'); if (!r || r.classList.contains('nophoto')) return;
    r.classList.toggle('off');
    var n = 0; pickList.querySelectorAll('.pick-row').forEach(function (x) { x.querySelector('.pick-num').textContent = x.classList.contains('off') ? '' : ++n; });
    document.getElementById('pick-n').textContent = n + ' of ' + pickList.querySelectorAll('.pick-row:not(.nophoto)').length + ' shown';
    savePickFromDom();
  });
  document.getElementById('pick-done').onclick = closePick;
  document.getElementById('pick-shade').onclick = closePick;
  document.getElementById('pick-all').onclick = function () {
    pickList.querySelectorAll('.pick-row:not(.nophoto)').forEach(function (r) { r.classList.remove('off'); });
    savePickFromDom(); renderPickList();
  };
  document.getElementById('pick-reset').onclick = function () { savePick(null); _pickChanged = true; renderPickList(); };
  // Reorder: drag a row by its grip (mouse or touch)
  pickList.addEventListener('pointerdown', function (e) {
    var grip = e.target.closest('.pick-grip'); if (!grip) return;
    e.preventDefault();
    var row = grip.closest('.pick-row'), y0 = e.clientY, moved = false, scrollT = null;
    row.classList.add('dragging');
    // Listen on the window: moving the row in the list would end a pointer capture on the grip
    function move(ev) {
      var y = ev.clientY, lr = pickList.getBoundingClientRect();
      if (Math.abs(y - y0) > 3) moved = true;
      // Near the list's edges: scroll it
      clearInterval(scrollT);
      if (y < lr.top + 28) scrollT = setInterval(function () { pickList.scrollTop -= 8; }, 16);
      else if (y > lr.bottom - 28) scrollT = setInterval(function () { pickList.scrollTop += 8; }, 16);
      var rows = Array.prototype.slice.call(pickList.querySelectorAll('.pick-row:not(.dragging)'));
      var before = null;
      for (var i = 0; i < rows.length; i++) { var rr = rows[i].getBoundingClientRect(); if (y < rr.top + rr.height / 2) { before = rows[i]; break; } }
      if (before !== row.nextSibling) pickList.insertBefore(row, before);
    }
    function up() {
      clearInterval(scrollT);
      window.removeEventListener('pointermove', move); window.removeEventListener('pointerup', up); window.removeEventListener('pointercancel', up);
      row.classList.remove('dragging');
      if (moved) { savePickFromDom(); renderPickList(); }
    }
    window.addEventListener('pointermove', move); window.addEventListener('pointerup', up); window.addEventListener('pointercancel', up);
  });
  // Pull the panel down by the tab at the top (or tap it); push it back up by the bar at its bottom
  function pullable(handle, opening) {
    handle.addEventListener('pointerdown', function (e) {
      e.preventDefault();
      var y0 = e.clientY, h = pickEl.offsetHeight, dy = 0;
      try { handle.setPointerCapture(e.pointerId); } catch (x) {}
      if (opening) { renderPickList(); h = pickEl.offsetHeight; stopProgress(); }
      pickEl.classList.add('drag');
      function move(ev) {
        dy = ev.clientY - y0;
        var t = opening ? Math.min(0, -h + Math.max(0, dy)) : Math.min(0, Math.min(0, dy));
        pickEl.style.transform = 'translate(-50%, ' + t + 'px)';
      }
      function up() {
        handle.removeEventListener('pointermove', move); handle.removeEventListener('pointerup', up); handle.removeEventListener('pointercancel', up);
        pickEl.classList.remove('drag'); pickEl.style.transform = '';
        if (opening) { if (dy > 50 || Math.abs(dy) < 6) openPick(); else if (!paused && slides.length) resetProgress(); }
        else { if (dy < -40 || Math.abs(dy) < 6) closePick(); }
      }
      handle.addEventListener('pointermove', move); handle.addEventListener('pointerup', up); handle.addEventListener('pointercancel', up);
    });
  }
  pullable(document.getElementById('pick-tab'), true);
  pullable(document.getElementById('pick-bar'), false);
  document.getElementById('pick-tab').addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); e.stopPropagation(); openPick(); } });
  // On a phone: swipe down from the top of a slide
  (function () {
    var vy0 = null, vx0 = 0;
    document.getElementById('slideshow').addEventListener('touchstart', function (e) {
      vy0 = e.touches.length === 1 && e.touches[0].clientY < window.innerHeight * 0.3 ? e.touches[0].clientY : null;
      if (vy0 != null) vx0 = e.touches[0].clientX;
    }, { passive: true });
    document.getElementById('slideshow').addEventListener('touchmove', function (e) {
      if (vy0 == null) return;
      var dy = e.touches[0].clientY - vy0, dx = Math.abs(e.touches[0].clientX - vx0);
      if (dx > dy) { vy0 = null; return; }
      if (dy > 60) { vy0 = null; openPick(); }
    }, { passive: true });
  })();
  xhr.onerror = function () { showEmpty(); };
  xhr.send();

  // ── Cache-status dots ────────────────────────────────────────
  // Marks each slide's dot orange when its image is confirmed in the
  // local SW cache. Called after buildDom, after prefetch, and after
  // server-side caching updates the image URLs.
  function updateDotCacheStatus() {
    var dotEls = dotsEl.querySelectorAll('.dot');
    if (!dotEls.length) return;
    slides.forEach(function (s, i) {
      var dot = dotEls[i];
      if (!dot) return;
      // IDB blob already loaded → image is definitely available offline
      if (s._idbSrc) { dot.classList.add('cached'); return; }
      if (!('caches' in window)) { dot.classList.remove('cached'); return; }
      var url = resolveImage(s.photo).cached;
      if (!url) { dot.classList.remove('cached'); return; }
      caches.match(url).then(function (match) {
        if (match) dot.classList.add('cached');
        else        dot.classList.remove('cached');
      });
    });
  }

  // ── Eager image pre-fetch ───────────────────────────────────
  // Writes every slide image directly into the SW cache from page JS.
  // Tries CORS mode first — Dropbox CDN (dropboxusercontent.com) returns
  // Access-Control-Allow-Origin: * so the response is transparent rather
  // than opaque, which avoids the iOS opaque-response size limit that
  // silently blocks cache.put() for large cross-origin images.
  // Falls back to no-cors if CORS fails.
  // Returns a Promise that resolves when all cache writes are attempted.
  function prefetchImages() {
    if (!('caches' in window)) return Promise.resolve();
    return caches.keys().then(function (keys) {
      var swKey = keys.find(function (k) { return k.startsWith('pb-slides-'); });
      if (!swKey) return Promise.resolve();
      return caches.open(swKey).then(function (cache) {
        return Promise.all(slides.map(function (s) {
          var url = resolveImage(s.photo).cached;
          if (!url) return Promise.resolve();
          return cache.match(url).then(function (existing) {
            if (existing) return Promise.resolve();   // already cached
            // Try transparent CORS response first; opaque fallback if CORS blocked
            return fetch(url)
              .then(function (res) {
                if (res.ok) return cache.put(url, res);
              })
              .catch(function () {
                return fetch(url, { mode: 'no-cors' })
                  .then(function (res) {
                    if (res.ok || res.type === 'opaque') return cache.put(url, res);
                  })
                  .catch(function () {});
              });
          });
        }));
      });
    }).catch(function () {});
  }

  // ── Server-side image cache + SW pre-cache ──────────────────
  //
  // On first load: POSTs all photo URLs to push/cacheSlideImages.php.
  //   The server downloads each image to sheets/{id}/cache/{md5}.ext.
  //   When the response arrives, each <img> src is swapped to the same-origin
  //   cached URL, which is then registered with the SW for offline use.
  //
  // On subsequent loads: the server cache already exists → response is
  //   immediate (no downloading), so img srcs are updated before the first
  //   slide auto-advance.
  //
  function cacheOnServer() {
    if (!slides.length) return;

    var photos = slides.map(function (s) { return s.photo; }).filter(Boolean);
    if (!photos.length) return;

    var cxhr = new XMLHttpRequest();
    cxhr.open('POST', APP_BASE + 'push/cacheSlideImages.php');
    cxhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    cxhr.timeout = 90000;  // generous — first run downloads N images

    cxhr.onload = function () {
      if (cxhr.status !== 200) return;
      var results;
      try { results = JSON.parse(cxhr.responseText); } catch (e) { return; }
      if (!Array.isArray(results)) return;

      // Build map: original URL → absolute cached URL
      var cacheMap = {};
      results.forEach(function (r) {
        if (r.photo && r.cached) {
          cacheMap[r.photo] = APP_BASE + r.cached;
        }
      });

      // Persist map so offline visits can swap Dropbox URLs → server URLs
      // without any network call. Stored per sheet so multiple sheets don't collide.
      try {
        localStorage.setItem('pb_urlmap_' + sheetId, JSON.stringify(cacheMap));
      } catch (e) {}

      // For each slide: update photo URL, fetch blob, store in IDB + SW cache,
      // then swap img.src to an Object URL so the image renders offline
      // from memory — no service worker or network involved.
      var imgEls = trackEl.querySelectorAll('.slide-img');
      slides.forEach(function (s, i) {
        var serverUrl = cacheMap[s.photo];
        if (!serverUrl) return;
        s.photo = serverUrl;   // update slide record to server URL

        (function (slide, el, sUrl) {
          idbGet(sUrl).then(function (existing) {
            if (existing) {
              // Already in IDB as a data URL — apply immediately
              slide._idbSrc = existing;
              if (el && el.src !== existing) {
                el.onerror = (function (imgEl, fb) {
                  return function () { imgEl.onerror = null; imgEl.src = fb; };
                })(el, sUrl);
                el.src = existing;
              }
              return;
            }
            // Fetch from server, convert to data URL, store in IDB (+ prime SW cache)
            fetch(sUrl)
              .then(function (res) {
                if (!res.ok) throw new Error('bad response');
                var resClone = res.clone();   // for SW cache — clone before reading body
                return res.blob().then(function (blob) {
                  if (!blob || blob.size < 64) throw new Error('empty blob');
                  // Prime the SW cache as a backup (best-effort)
                  if ('caches' in window) {
                    caches.keys().then(function (keys) {
                      var k = keys.find(function (k) { return k.startsWith('pb-slides-'); });
                      if (k) caches.open(k).then(function (c) { c.put(sUrl, resClone); });
                    });
                  }
                  return blobToDataUrl(blob).then(function (dataUrl) {
                    return idbPut(sUrl, dataUrl).then(function () {
                      slide._idbSrc = dataUrl;
                      if (el && el.src !== dataUrl) {
                        el.onerror = (function (imgEl, fb) {
                          return function () { imgEl.onerror = null; imgEl.src = fb; };
                        })(el, sUrl);
                        el.src = dataUrl;
                      }
                    });
                  });
                });
              })
              .catch(function () {
                // Fetch/IDB failed — leave img.src unchanged (original URL still works)
              });
          });
        })(s, imgEls[i], serverUrl);
      });

      // Tell the SW to pre-cache all server-cached URLs so they work offline
      // even for slides the user hasn't manually swiped to yet.
      var cachedPhotos = slides.map(function (s) { return s.photo; }).filter(Boolean);
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.ready.then(function (reg) {
          if (reg.active) {
            reg.active.postMessage({ type: 'PRECACHE_IMAGES', urls: cachedPhotos });
          }
        });
      }

      // Update dot colours now that server-cached URLs are in place.
      // Short delay lets the SW finish writing PRECACHE_IMAGES entries.
      setTimeout(updateDotCacheStatus, 600);
    };

    cxhr.send(
      'id='   + encodeURIComponent(sheetId) +
      '&urls=' + encodeURIComponent(JSON.stringify(photos))
    );
  }

  // ── Service Worker (offline image caching) ───────────────
  if ('serviceWorker' in navigator) {
    var _swScope = APP_BASE + sheetId + '/pitchboard/slides';
    navigator.serviceWorker
      .register(APP_BASE + 'slides-sw.js', { scope: _swScope })
      .catch(function () {});
  }
})();
</script>
</body>
</html>
