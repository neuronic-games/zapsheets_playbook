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
<link rel="apple-touch-icon" sizes="180x180" href="images/pb_icon_180.png" />
<link rel="icon" type="image/png" href="images/pb_icon_192.png" />
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
    <p class="empty-sub">Add games with <strong>Status = Pitching</strong><br>and a <strong>Photo URL</strong> or <strong>Image URL</strong>.</p>
  </div>
</div>

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

  // ── Image URL helpers (mirrors view/index.php) ───────────
  function resolveImage(url) {
    if (!url) return { cached: '', direct: '' };
    url = url.trim();
    var direct = url;
    // Dropbox: force direct download; use the same URL as both cached and direct
    // so the SW caches the correct full URL (including rlkey) and can serve it offline.
    if (url.includes('dropbox.com')) {
      direct = url.includes('dl=0')
        ? url.replace('dl=0', 'dl=1')
        : (url.match(/[?&]dl=/) ? url : url + (url.includes('?') ? '&' : '?') + 'dl=1');
      return { cached: direct, direct: direct };
    }
    // Google Drive: route through local cache
    if (url.includes('drive.google.com')) {
      var parts = url.split('drive.google.com')[1].split('/');
      var imgid = parts[3] || parts[2] || '';
      var cached = imgid ? BASE + 'cache/' + imgid + '.png' : direct;
      return { cached: cached, direct: direct };
    }
    // Everything else: strip query/hash for cached guess, use original as direct
    var clean = url.split('?')[0].split('#')[0];
    return { cached: clean, direct: direct };
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
    } else {
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
    else if (!paused)    { resetProgress(); }
  });

  // ── Empty / error ─────────────────────────────────────────
  function showEmpty() {
    loadingEl.style.display = 'none';
    emptyEl.style.display   = 'flex';
    document.getElementById('progress-bar').style.display = 'none';
    counterEl.style.display = 'none';
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

    slides = games
      .filter(function (g) {
        var status = (g['Status'] || '').trim().toLowerCase();
        var photo  = (g['Photo URL'] || g['Image URL'] || '').trim();
        return status === 'pitching' && photo;
      })
      .map(function (g) {
        var designers = ['Designer1', 'Designer2', 'Designer3', 'Designer4']
          .map(function (f) { return (g[f] || '').trim(); })
          .filter(Boolean).join(', ');
        return {
          name:      (g['Name']    || '').trim(),
          tagline:   (g['Tagline'] || '').trim(),
          photo:     (g['Photo URL'] || g['Image URL'] || '').trim(),
          designers: designers,
          page:      (g['Page URL'] || '').trim(),
        };
      });

    if (!slides.length) { showEmpty(); return; }

    // If a previous online session cached images server-side, swap Dropbox URLs
    // to same-origin server URLs now — before buildDom sets img.src — so the SW
    // can serve them from cache even when the device is offline.
    (function applyUrlMapping() {
      try {
        var stored = localStorage.getItem('pb_urlmap_' + sheetId);
        if (!stored) return;
        var map = JSON.parse(stored);
        slides.forEach(function (s) {
          if (map[s.photo]) s.photo = map[s.photo];
        });
      } catch (e) {}
    })();

    // Apply any IDB-stored blobs as Object URLs before building DOM —
    // this makes images work offline on the very first paint without
    // relying on the service worker to serve them from cache.
    applyIDBImages().then(function () {
      buildDom();
      setSlide(0, false);
      resetProgress();
      updateDotCacheStatus();
      prefetchImages().then(function () {
        setTimeout(updateDotCacheStatus, 400);
      });
      cacheOnServer();
    });
  };
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
