<?php
// Compute the deployment base path from the request URI — no .env needed.
// Works for both short-URL (/{id}/view) and direct access (/sheets/{id}/view/).
// Also works in subdirectory deployments like /playbook-test/.
$_rp = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('#^(.*?)(?:sheets/)?[A-Za-z0-9_\-]+/view/?$#', $_rp, $_bm);
$_base = (isset($_bm[1]) && $_bm[1] !== '') ? $_bm[1] : '/';
if (substr($_base, -1) !== '/') $_base .= '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta http-equiv="cache-control" content="no-cache, no-store, must-revalidate" />
  <title id="pageTitle">Game</title>
  <link id="appIconLink" rel="icon" type="image/x-icon" href="images/sheet_2_new.webp" />
  <link rel="stylesheet" href="css/bootstrap.min.css" />
  <style>
    @font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2') format('woff2'), url('fonts/DINBlack.ttf'); }
    @font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'), url('fonts/DINMedium.ttf'); }

    *, *::before, *::after { box-sizing: border-box; }
    body {
      background: #fff;
      font-family: 'DINRegular', Arial, sans-serif;
      color: #222;
      margin: 0;
      min-height: 100vh;
    }

    /* ── Page container ──────────────────────────────────────── */
    .page-wrap { max-width: 1060px; margin: 0 auto; padding: 1.5rem 1rem 3rem; }

    /* ── Game title row ──────────────────────────────────────── */
    .game-title-row {
      display: flex;
      align-items: baseline;
      gap: .75rem;
      margin-bottom: 1.25rem;
      flex-wrap: wrap;
    }
    .game-title {
      font-family: 'DINBlack', sans-serif;
      font-size: clamp(1.6rem, 4vw, 2.4rem);
      text-transform: uppercase;
      letter-spacing: .04em;
      line-height: 1;
      color: #111;
      margin: 0;
    }
    .game-year {
      font-size: .95rem;
      color: #888;
      font-family: 'DINRegular', sans-serif;
      white-space: nowrap;
    }

    /* ── Two-column layout ───────────────────────────────────── */
    .main-columns {
      display: grid;
      grid-template-columns: 320px 1fr;
      gap: 1.75rem;
      align-items: start;
    }
    @media (max-width: 700px) {
      .main-columns { grid-template-columns: 1fr; }
    }

    /* ── Left: image panel ───────────────────────────────────── */
    .image-panel {}
    .main-image-wrap {
      background: #fff;
      border: 1px solid #ddd;
      border-radius: 10px;
      overflow: hidden;
      margin-bottom: .6rem;
      cursor: pointer;
    }
    .main-image-wrap {
      height: 320px;
    }
    .main-image-wrap img,
    .main-image-wrap video {
      width: 100%;
      height: 100%;
      display: block;
      object-fit: cover;
    }
    .main-image-wrap video { display: none; background: #000; }
    .thumb-strip {
      display: flex;
      gap: .4rem;
      overflow-x: auto;
      padding-bottom: .25rem;
      scrollbar-width: thin;
      scrollbar-color: #c8860a #e8e3db;
    }
    .thumb-strip::-webkit-scrollbar { height: 4px; }
    .thumb-strip::-webkit-scrollbar-thumb { background: #c8860a; border-radius: 2px; }
    .thumb {
      flex: 0 0 auto;
      width: 64px; height: 48px;
      object-fit: cover;
      border-radius: 5px;
      border: 2px solid transparent;
      cursor: pointer;
      transition: border-color .15s, opacity .15s;
      opacity: .7;
    }
    .thumb:hover, .thumb.active { border-color: #c8860a; opacity: 1; }
    .thumb-video-wrap {
      flex: 0 0 auto;
      width: 64px; height: 48px;
      background: #1a1a2e;
      border-radius: 5px;
      border: 2px solid transparent;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: border-color .15s, opacity .15s;
      opacity: .7;
    }
    .thumb-video-wrap:hover, .thumb-video-wrap.active { border-color: #c8860a; opacity: 1; }
    .thumb-play-icon { color: #fff; font-size: 1.1rem; line-height: 1; }

    /* ── Buy buttons ─────────────────────────────────────────── */
    .buy-btn-list { display: flex; flex-direction: column; gap: .45rem; margin-top: .9rem; }
    .btn-buy {
      display: block;
      width: 100%;
      background: #c8860a;
      color: #fff;
      font-family: 'DINBlack', sans-serif;
      text-transform: uppercase;
      letter-spacing: .07em;
      font-size: .9rem;
      padding: .7rem 1.2rem;
      border-radius: 8px;
      text-decoration: none;
      text-align: center;
      transition: background .15s, transform .1s;
    }
    .btn-buy:hover { background: #a06808; color: #fff; transform: translateY(-1px); }

    /* ── Right: info panel ───────────────────────────────────── */
    .info-panel {}

    /* Rating row */
    .rating-row {
      display: flex;
      align-items: stretch;
      gap: .75rem;
      margin-bottom: 1rem;
      flex-wrap: wrap;
    }
    .rating-badge {
      background: #c8860a;
      color: #fff;
      font-family: 'DINBlack', sans-serif;
      border-radius: 10px;
      padding: .55rem .9rem;
      display: inline-flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      min-width: 72px;
      text-align: center;
      gap: .3rem;
    }
    .rating-badge span {
      font-size: 1.65rem;
      line-height: 1;
      letter-spacing: -.01em;
    }
    .rating-badge small {
      font-size: .6rem;
      line-height: 1;
      font-family: 'DINRegular', sans-serif;
      opacity: .9;
      text-transform: uppercase;
      letter-spacing: .07em;
    }
    .bgg-link {
      font-size: .85rem;
      color: #555;
      text-decoration: none;
      border: 1.5px solid #ccc;
      border-radius: 10px;
      padding: .55rem 1.1rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      white-space: nowrap;
      font-family: 'DINRegular', sans-serif;
      letter-spacing: .01em;
    }
    .bgg-link:hover { border-color: #999; color: #222; background: #fafafa; }

    /* Stats grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: .5rem;
      margin-bottom: 1.1rem;
    }
    .stat-box {
      background: #fff;
      border: 1px solid #e0dbd3;
      border-radius: 8px;
      padding: .55rem .5rem;
      text-align: center;
    }
    .stat-val {
      font-family: 'DINBlack', sans-serif;
      font-size: 1.1rem;
      color: #111;
      line-height: 1;
      display: block;
    }
    .stat-key {
      font-size: .68rem;
      text-transform: uppercase;
      letter-spacing: .07em;
      color: #888;
      display: block;
      margin-top: .2rem;
    }

    /* Price row */
    .price-row {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1.1rem;
    }
    .price-tag {
      font-family: 'DINBlack', sans-serif;
      font-size: 1.6rem;
      color: #2a7a3b;
    }
    .stock-tag {
      font-size: .8rem;
      color: #666;
      padding: .2rem .55rem;
      background: #eafaf0;
      border-radius: 4px;
      border: 1px solid #b2dfcc;
    }
    .stock-tag.low { background: #fff5e6; border-color: #f5c285; color: #a05a00; }

    /* Description */
    .desc-text {
      font-size: .97rem;
      line-height: 1.7;
      color: #333;
      margin-bottom: 1.1rem;
      border-left: 3px solid #e0dbd3;
      padding-left: .85rem;
    }

    /* Meta list */
    .meta-list { margin-bottom: 1.1rem; }
    .meta-row {
      display: flex;
      gap: .5rem;
      font-size: .87rem;
      padding: .35rem 0;
      border-bottom: 1px solid #ede9e2;
      align-items: baseline;
    }
    .meta-row:last-child { border-bottom: none; }
    .meta-key {
      font-family: 'DINBlack', sans-serif;
      text-transform: uppercase;
      font-size: .75rem;
      letter-spacing: .06em;
      color: #777;
      min-width: 90px;
    }
    .meta-val { color: #222; flex: 1; }
    .tag-pill {
      display: inline-block;
      background: #ede9e2;
      border-radius: 4px;
      padding: .1rem .45rem;
      font-size: .78rem;
      margin: .1rem .15rem .1rem 0;
      color: #444;
    }

    /* CTA */
    .cta-row { display: flex; gap: .75rem; flex-wrap: wrap; }
    .btn-play {
      background: #1a1a2e;
      color: #fff;
      font-family: 'DINBlack', sans-serif;
      text-transform: uppercase;
      letter-spacing: .06em;
      font-size: .9rem;
      padding: .6rem 1.4rem;
      border-radius: 7px;
      border: none;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
      transition: background .15s;
    }
    .btn-play:hover { background: #2d2d50; color: #fff; }
    .btn-rules {
      background: #fff;
      color: #222;
      font-family: 'DINBlack', sans-serif;
      text-transform: uppercase;
      letter-spacing: .06em;
      font-size: .9rem;
      padding: .6rem 1.4rem;
      border-radius: 7px;
      border: 1px solid #ccc;
      cursor: pointer;
      text-decoration: none;
      display: inline-block;
      transition: background .15s;
    }
    .btn-rules:hover { background: #f5f5f5; color: #222; }

    /* ── Tabs ────────────────────────────────────────────────── */
    .tabs-section { margin-top: 2rem; }
    .tab-nav {
      display: flex;
      border-bottom: 2px solid #ddd;
      gap: 0;
      margin-bottom: 1.25rem;
    }
    .tab-btn {
      font-family: 'DINBlack', sans-serif;
      text-transform: uppercase;
      letter-spacing: .06em;
      font-size: .85rem;
      padding: .65rem 1.25rem;
      background: none;
      border: none;
      cursor: pointer;
      color: #888;
      border-bottom: 3px solid transparent;
      margin-bottom: -2px;
      transition: color .15s;
    }
    .tab-btn:hover { color: #333; }
    .tab-btn.active { color: #1a1a2e; border-bottom-color: #c8860a; }
    .tab-pane { display: none; }
    .tab-pane.active { display: block; }

    /* ── Videos ──────────────────────────────────────────────── */
    .video-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px,1fr)); gap: 1rem; }
    .video-card { background: #fff; border-radius: 9px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,.07); }
    .video-embed { position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; }
    .video-embed iframe { position: absolute; inset: 0; width: 100%; height: 100%; border: none; }
    .video-caption { padding: .55rem .85rem; font-family: 'DINBlack', sans-serif; font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; color: #555; background: #f9f6f1; border-top: 1px solid #ede9e2; }
    .video-link-card { display: flex; align-items: center; gap: .75rem; padding: .9rem 1rem; background: #fff; border-radius: 9px; box-shadow: 0 2px 8px rgba(0,0,0,.07); text-decoration: none; color: inherit; transition: background .15s; }
    .video-link-card:hover { background: #fdf8f0; }
    .video-icon { font-size: 1.6rem; }
    .video-link-title { font-family: 'DINBlack', sans-serif; font-size: .9rem; text-transform: uppercase; color: #222; }
    .video-link-platform { font-size: .75rem; color: #999; }

    /* ── Rules accordion ─────────────────────────────────────── */
    .rules-accordion .accordion-item { border: 1px solid #e0dbd3; margin-bottom: .35rem; border-radius: 8px !important; overflow: hidden; }
    .rules-accordion .accordion-button { font-family: 'DINBlack', sans-serif; text-transform: uppercase; letter-spacing: .05em; font-size: .95rem; background: #fff; color: #111; }
    .rules-accordion .accordion-button:not(.collapsed) { background: #1a1a2e; color: #fff; box-shadow: none; }
    .rules-accordion .accordion-button:not(.collapsed)::after { filter: invert(1); }
    .rule-text { font-size: .95rem; line-height: 1.75; white-space: pre-wrap; margin-bottom: .75rem; }
    .rule-img { max-width: 100%; border-radius: 8px; margin-bottom: .75rem; display: block; }

    /* ── FAQs ────────────────────────────────────────────────── */
    .faq-item { border-bottom: 1px solid #ede9e2; padding: 1rem 0; }
    .faq-item:last-child { border-bottom: none; }
    .faq-q { font-family: 'DINBlack', sans-serif; font-size: .97rem; cursor: pointer; display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
    .faq-q .chev { transition: transform .25s; flex-shrink: 0; }
    .faq-q.open .chev { transform: rotate(180deg); }
    .faq-a { display: none; padding-top: .6rem; font-size: .92rem; line-height: 1.7; color: #3a3a3a; }
    .faq-a img { max-width: 100%; border-radius: 8px; margin-top: .5rem; display: block; }

    /* ── Lightbox ────────────────────────────────────────────── */
    .lightbox { display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(0,0,0,.88); align-items: center; justify-content: center; }
    .lightbox.open { display: flex; }
    .lightbox img { max-width: 92vw; max-height: 90vh; border-radius: 8px; display: block; }
    .lb-close { position: absolute; top: 1rem; right: 1.25rem; font-size: 2.2rem; color: #fff; cursor: pointer; background: none; border: none; line-height: 1; }

    /* ── Loading ─────────────────────────────────────────────── */
    #loadScreen { position: fixed; inset: 0; z-index: 9998; background: #fff; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 1rem; }
    #loadScreen img { width: 60px; opacity: .6; }
    .spin { width: 32px; height: 32px; border: 3px solid #ddd; border-top-color: #c8860a; border-radius: 50%; animation: sp .7s linear infinite; }
    @keyframes sp { to { transform: rotate(360deg); } }

    /* ── Reviews ─────────────────────────────────────────────── */
    .review-list { display: flex; flex-direction: column; gap: 1.1rem; }
    .review-card {
      border-left: 4px solid #c8860a;
      background: #fdf8f0;
      border-radius: 0 8px 8px 0;
      padding: 1rem 1.25rem;
    }
    .review-quote {
      font-size: 1rem;
      line-height: 1.7;
      color: #222;
      margin: 0 0 .55rem;
      font-style: italic;
    }
    .review-quote::before { content: '\201C'; }
    .review-quote::after  { content: '\201D'; }
    .review-byline {
      font-family: 'DINBlack', sans-serif;
      font-size: .78rem;
      text-transform: uppercase;
      letter-spacing: .07em;
      color: #888;
    }

    /* ── Rule tag images ─────────────────────────────────────── */
    .rule-tag-img { height: 1.4em; vertical-align: middle; margin: 0 1px; }

    @media (max-width: 480px) {
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
      .page-wrap { padding: 1rem .75rem 2rem; }
    }
  </style>
</head>
<body>

<!-- Loading -->
<div id="loadScreen">
  <img src="images/sheet_2_new.webp" alt="" />
  <div class="spin"></div>
</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox">
  <button class="lb-close" id="lbClose">&times;</button>
  <img id="lbImg" src="" alt="" />
</div>

<!-- Page -->
<div class="page-wrap">

  <!-- Game title -->
  <div class="game-title-row">
    <h1 class="game-title" id="gameTitle">…</h1>
    <span class="game-year" id="gameYear"></span>
  </div>

  <!-- Two-column main section -->
  <div class="main-columns">

    <!-- Left: images -->
    <div class="image-panel">
      <div class="main-image-wrap" id="mainImgWrap">
        <img id="mainImg" src="" alt="" />
        <video id="mainVideo" muted playsinline></video>
      </div>
      <div class="thumb-strip" id="thumbStrip"></div>
      <div id="buyBtnWrap" style="display:none"></div>
    </div>

    <!-- Right: info -->
    <div class="info-panel">

      <!-- Rating + BGG link -->
      <div class="rating-row">
        <div class="rating-badge" id="ratingBadge" style="display:none">
          <span id="ratingVal">—</span>
          <small>BGG Rating</small>
        </div>
        <a id="bggLink" class="bgg-link" href="#" target="_blank" rel="noopener" style="display:none">
          View on BGG
        </a>
      </div>

      <!-- Stats -->
      <div class="stats-grid" id="statsGrid"></div>

      <!-- Price -->
      <div class="price-row" id="priceRow" style="display:none">
        <span class="price-tag" id="priceTag"></span>
        <span class="stock-tag" id="stockTag"></span>
      </div>

      <!-- Description -->
      <p class="desc-text" id="descText"></p>

      <!-- Meta: designers, mechanics -->
      <div class="meta-list" id="metaList"></div>

      <!-- CTAs -->
      <div class="cta-row" id="ctaRow"></div>

    </div><!-- /info-panel -->
  </div><!-- /main-columns -->

  <!-- Tabs -->
  <div class="tabs-section">
    <div class="tab-nav" id="tabNav"></div>

    <div class="tab-pane" id="pane-videos">
      <div class="video-grid" id="videoGrid"></div>
    </div>
    <div class="tab-pane" id="pane-reviews">
      <div class="review-list" id="reviewList"></div>
    </div>
    <div class="tab-pane" id="pane-rules">
      <div class="accordion rules-accordion" id="rulesAccordion"></div>
    </div>
    <div class="tab-pane" id="pane-faqs">
      <div id="faqList"></div>
    </div>
  </div>

</div><!-- /page-wrap -->

<script src="js/common/jquery-3.5.1.min.js?v=3"></script>
<script src="js/common/bootstrap.bundle.min.js?v=2"></script>
<script>
// Unregister any stale service workers (e.g. sw_map.js from old deployments)
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.getRegistrations().then(function(regs) {
    regs.forEach(function(reg) {
      var url = (reg.active || reg.installing || reg.waiting || {}).scriptURL || '';
      if (!url.includes('sw_playbookHomeApp')) reg.unregister();
    });
  });
}
</script>
<script>
////////////////////////////////////////////////////////////////////////////////
function getSheetId() {
  var parts = window.location.pathname.split('/').filter(Boolean);
  // /sheets/{id}/view/
  var idx = parts.indexOf('sheets');
  if (idx >= 0 && parts[idx + 1]) return parts[idx + 1];
  // /{id}/view/ or /subdir/{id}/view/ — find 'view' and take the element before it
  var viewIdx = parts.lastIndexOf('view');
  if (viewIdx > 0) return parts[viewIdx - 1];
  // query string fallback
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

function cachedImage(url) {
  if (!url) return '';
  if (url.includes('https://drive.google.com')) {
    var imgid = url.split('https://drive.google.com')[1].split('/')[3];
    return BASE + 'cacheImages/' + imgid + '.png';
  }
  var parts = url.split('/');
  var raw   = parts[parts.length - 1];
  var name  = raw.indexOf('?') !== -1 ? raw.split('?')[0] : raw;
  return BASE + 'cacheImages/' + name;
}

function decodeHtml(html) {
  var t = document.createElement('textarea');
  t.innerHTML = html;
  return t.value;
}

function youtubeId(url) {
  var m = url.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?(?:.*&)?v=|shorts\/|embed\/))([A-Za-z0-9_-]{11})/);
  return m ? m[1] : null;
}

////////////////////////////////////////////////////////////////////////////////
var data = {};
var _loadTotal = 8;
var _loadDone  = 0;

function _jsonLoad(path, key) {
  $.ajax({
    url: path + '?v=' + Date.now(),
    type: 'GET',
    dataType: 'text',
    cache: false,
    success: function(response) {
      try {
        var clean = response.replace(/�/g, '').replace(/�/g, '');
        data[key] = JSON.parse(clean);
      } catch(e) {
        try { data[key] = eval('(' + clean + ')'); } catch(e2) { /* leave data[key] undefined */ }
      }
      _checkAllLoaded();
    },
    error: function() { _checkAllLoaded(); }
  });
}

function _checkAllLoaded() {
  _loadDone++;
  if (_loadDone >= _loadTotal) render();
}

_jsonLoad(BASE + 'settings.json',          'settings');
_jsonLoad(BASE + 'stats.json',             'stats');
_jsonLoad(BASE + 'bgg-'    + lang + '.json', 'bgg');
_jsonLoad(BASE + 'splash-' + lang + '.json', 'splash');
_jsonLoad(BASE + 'videos-' + lang + '.json', 'videos');
_jsonLoad(BASE + 'rules-'  + lang + '.json', 'rules');
_jsonLoad(BASE + 'faqs-'   + lang + '.json', 'faqs');
_jsonLoad(BASE + 'tags.json',              'tags');

////////////////////////////////////////////////////////////////////////////////
// allImages entries: { src, caption, type ('image'|'video'), delay (seconds) }
var allImages   = [];
var activeThumb = 0;
var slideshowTimer = null;

function stopSlideshow() {
  if (slideshowTimer) { clearTimeout(slideshowTimer); slideshowTimer = null; }
}

function startSlideshow(fromIdx) {
  stopSlideshow();
  if (allImages.length < 2) return;
  var delay = (allImages[fromIdx] && allImages[fromIdx].delay) ? allImages[fromIdx].delay : 5;
  slideshowTimer = setTimeout(function() {
    var nextIdx = (fromIdx + 1) % allImages.length;
    activeThumb = nextIdx;
    setMainMedia(nextIdx);
    updateThumbActive(nextIdx);
    startSlideshow(nextIdx);
  }, delay * 1000);
}

function updateThumbActive(idx) {
  var strip = document.getElementById('thumbStrip');
  strip.querySelectorAll('.thumb, .thumb-video-wrap').forEach(function(t) {
    t.classList.remove('active');
  });
  var all = strip.querySelectorAll('.thumb, .thumb-video-wrap');
  if (all[idx]) all[idx].classList.add('active');
}

function setMainMedia(idx) {
  var item = allImages[idx];
  if (!item) return;
  var img = document.getElementById('mainImg');
  var vid = document.getElementById('mainVideo');
  if (item.type === 'video') {
    img.style.display = 'none';
    vid.src = item.src;
    vid.style.display = 'block';
    vid.load();
    vid.play().catch(function(){});
  } else {
    vid.pause();
    vid.removeAttribute('src');
    vid.style.display = 'none';
    img.src = item.src;
    img.style.display = 'block';
  }
}

function buildThumbs() {
  var strip = document.getElementById('thumbStrip');
  if (allImages.length <= 1) { strip.style.display = 'none'; return; }
  strip.innerHTML = allImages.map(function(item, i) {
    var activeCls = i === 0 ? ' active' : '';
    if (item.type === 'video') {
      return '<div class="thumb-video-wrap' + activeCls + '" data-idx="' + i + '" title="' + item.caption + '">'
        + '<span class="thumb-play-icon">&#9654;</span></div>';
    }
    return '<img class="thumb' + activeCls + '" src="' + item.src
      + '" alt="" data-idx="' + i + '" loading="lazy" />';
  }).join('');
  strip.querySelectorAll('.thumb, .thumb-video-wrap').forEach(function(el) {
    el.addEventListener('click', function() {
      var idx = parseInt(this.dataset.idx);
      activeThumb = idx;
      stopSlideshow();
      setMainMedia(idx);
      updateThumbActive(idx);
      startSlideshow(idx);
    });
  });
}

function render() {
  try {
  // ── Parse settings ──────────────────────────────────────────
  var cfg = {};
  (data.settings || []).forEach(function(r){ if(r.Name) cfg[r.Name]=r.Value; });

  var bggCfg = {};
  (data.bgg || []).forEach(function(r){ if(r.Name) bggCfg[r.Name]=r.Value; });

  var bg = {};
  if (data.stats && data.stats.boardgame && data.stats.boardgame[0])
    bg = data.stats.boardgame[0].boardgame || {};

  var basic = {};
  if (data.stats && data.stats.boardgameBasicData) {
    var k = Object.keys(data.stats.boardgameBasicData);
    if (k.length) basic = data.stats.boardgameBasicData[k[0]];
  }

  // ── Title & meta ─────────────────────────────────────────────
  var title = cfg['Title'] || basic['name'] || 'Game';
  var year  = bg['yearpublished'] ? '(' + bg['yearpublished'] + ')' : '';

  document.title = title;
  document.getElementById('gameTitle').textContent = title;
  document.getElementById('gameYear').textContent  = year;
  document.getElementById('pageTitle').textContent = title;

  // App icon
  if (cfg['AppIconImageUrl']) {
    document.getElementById('appIconLink').href = cachedImage(cfg['AppIconImageUrl']);
  }

  // ── Tag image map ─────────────────────────────────────────────
  var tagImageMap = {};
  if (data.tags && Array.isArray(data.tags)) {
    data.tags.forEach(function(row) {
      var tagName  = row['Name'];
      var tagValue = row['Value'];
      if (!tagName || !tagValue) return;
      var parts = tagValue.split('/');
      var raw = parts[parts.length - 1];
      var fname = raw.indexOf('?') !== -1 ? raw.split('?')[0] : raw;
      tagImageMap[tagName] = BASE + 'cacheImages/' + fname;
    });
  }
  function applyTags(text) {
    if (!text) return '';
    return text.replace(/\[([A-Z0-9_]+)\]/g, function(match) {
      var p = tagImageMap[match];
      return p ? '<img class="rule-tag-img" src="' + p + '" loading="lazy" alt="' + match + '">' : match;
    });
  }

  // ── Images / Videos — sourced exclusively from splash JSON ──
  if (data.splash) {
    data.splash.forEach(function(r) {
      var t = (r.Type || '').toLowerCase();
      if ((t === 'image' || t === 'video') && r.Content) {
        allImages.push({
          src:     cachedImage(r.Content),
          caption: r.ID || '',
          type:    t,
          delay:   parseFloat(r.DelaySec) || 5
        });
      }
    });
  }

  if (allImages.length) {
    setMainMedia(0);
    buildThumbs();
    startSlideshow(0);
  }

  // ── Buy URLs ─────────────────────────────────────────────────
  var buyUrls = (data.bgg || []).filter(function(r) {
    return r.Name === 'BuyUrl' && r.Value;
  });
  if (buyUrls.length) {
    document.getElementById('buyBtnWrap').innerHTML =
      '<div class="buy-btn-list">'
      + buyUrls.map(function(r) {
          var label = (r['Alt Value'] && r['Alt Value'].trim()) ? r['Alt Value'].trim() : 'Buy Now';
          return '<a class="btn-buy" href="' + r.Value + '" target="_blank" rel="noopener">'
            + label + '</a>';
        }).join('')
      + '</div>';
    document.getElementById('buyBtnWrap').style.display = '';
  }

  // Lightbox on main image click (images only, not video)
  document.getElementById('mainImgWrap').addEventListener('click', function() {
    var item = allImages[activeThumb];
    if (!item || item.type === 'video') return;
    document.getElementById('lbImg').src = item.src;
    document.getElementById('lightbox').classList.add('open');
  });

  // ── Rating ───────────────────────────────────────────────────
  if (basic['rating']) {
    var r = parseFloat(basic['rating']).toFixed(1);
    document.getElementById('ratingVal').textContent = r;
    document.getElementById('ratingBadge').style.display = '';
  }
  var bggId = bggCfg['BggGameId'];
  if (bggId) {
    var lnk = document.getElementById('bggLink');
    lnk.href = 'https://boardgamegeek.com/boardgame/' + bggId;
    lnk.style.display = '';
  }

  // ── Stats ────────────────────────────────────────────────────
  var stats = [];
  if (bg['minplayers'] && bg['maxplayers'])
    stats.push({ v: bg['minplayers'] + '–' + bg['maxplayers'], k: 'Players' });
  if (bg['minplaytime'])
    stats.push({ v: bg['minplaytime'] + ' min', k: 'Play Time' });
  if (bg['age'])
    stats.push({ v: bg['age'] + '+', k: 'Age' });
  if (bggCfg['Weight'])
    stats.push({ v: parseFloat(bggCfg['Weight']).toFixed(1) + ' / 5', k: 'Complexity' });

  document.getElementById('statsGrid').innerHTML = stats.map(function(s) {
    return '<div class="stat-box"><span class="stat-val">' + s.v
      + '</span><span class="stat-key">' + s.k + '</span></div>';
  }).join('');

  // ── Price / stock ────────────────────────────────────────────
  if (bggCfg['Price']) {
    document.getElementById('priceTag').textContent = bggCfg['Price'];
    var stock = parseInt(bggCfg['Stock']) || 0;
    var stockEl = document.getElementById('stockTag');
    if (stock > 5) {
      stockEl.textContent = 'In Stock';
      stockEl.className = 'stock-tag';
    } else if (stock > 0) {
      stockEl.textContent = 'Only ' + stock + ' left';
      stockEl.className = 'stock-tag low';
    } else {
      stockEl.textContent = 'Out of Stock';
      stockEl.className = 'stock-tag low';
    }
    document.getElementById('priceRow').style.display = '';
  }

  // ── Description ──────────────────────────────────────────────
  var desc = bg['description'] ? decodeHtml(bg['description']) : '';
  document.getElementById('descText').textContent = desc;

  // ── Meta rows ────────────────────────────────────────────────
  var meta = [];
  if (bg['boardgamedesigner']) {
    var designers = Array.isArray(bg['boardgamedesigner'])
      ? bg['boardgamedesigner'] : [bg['boardgamedesigner']];
    meta.push({ k: 'Designers', v: designers.map(function(d){ return '<span class="tag-pill">' + d + '</span>'; }).join('') });
  }
  if (bg['boardgameartist']) {
    var artists = Array.isArray(bg['boardgameartist'])
      ? bg['boardgameartist'] : [bg['boardgameartist']];
    meta.push({ k: 'Artists', v: artists.map(function(a){ return '<span class="tag-pill">' + a + '</span>'; }).join('') });
  }
  if (bg['boardgamemechanic']) {
    var mechs = Array.isArray(bg['boardgamemechanic'])
      ? bg['boardgamemechanic'] : [bg['boardgamemechanic']];
    meta.push({ k: 'Mechanics', v: mechs.map(function(m){ return '<span class="tag-pill">' + m + '</span>'; }).join('') });
  }
  if (cfg['PublishedOn']) {
    var pubDate = cfg['PublishedOn'].split(' ')[0];
    meta.push({ k: 'Published', v: pubDate });
  }

  document.getElementById('metaList').innerHTML = meta.map(function(m) {
    return '<div class="meta-row"><span class="meta-key">' + m.k
      + '</span><span class="meta-val">' + m.v + '</span></div>';
  }).join('');

  // ── CTAs ─────────────────────────────────────────────────────
  var domain = cfg['DomainPrefix'] || '../../../';
  var stepsUrl = domain + 'sheets/' + sheet_Id + '/?sheet=steps-' + lang + '&id=' + sheet_Id;
  document.getElementById('ctaRow').innerHTML =
    '<a class="btn-rules" id="cta-rules" href="#">Read Rules</a>'
    + '<a class="btn-play" href="' + stepsUrl + '" target="_blank" rel="noopener">Teach Me</a>';

  document.getElementById('cta-rules').addEventListener('click', function(e) {
    e.preventDefault();
    activateTab('rules');
    document.querySelector('.tabs-section').scrollIntoView({ behavior: 'smooth' });
  });

  // ── Build tabs ───────────────────────────────────────────────
  var reviews = (data.bgg || []).filter(function(r) { return r.Name === 'Review' && r.Value; });

  var tabs = [];
  if (data.videos && data.videos.length)   tabs.push({ id: 'videos',  label: 'Videos (' + data.videos.length + ')' });
  if (reviews.length)                      tabs.push({ id: 'reviews', label: 'Reviews (' + reviews.length + ')' });
  if (data.rules  && data.rules.length)    tabs.push({ id: 'rules',   label: 'Rules' });
  if (data.faqs   && data.faqs.length)     tabs.push({ id: 'faqs',    label: 'FAQs (' + data.faqs.length + ')' });

  if (tabs.length) {
    document.getElementById('tabNav').innerHTML = tabs.map(function(t) {
      return '<button class="tab-btn" data-tab="' + t.id + '">' + t.label + '</button>';
    }).join('');
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
      btn.addEventListener('click', function(){ activateTab(this.dataset.tab); });
    });
    activateTab(tabs[0].id);
  }

  // ── Videos ───────────────────────────────────────────────────
  if (data.videos && data.videos.length) {
    document.getElementById('videoGrid').innerHTML = data.videos.map(function(v) {
      var url = v.Content || '';
      var ytId = youtubeId(url);
      if (ytId) {
        return '<div class="video-card">'
          + '<div class="video-embed"><iframe src="https://www.youtube.com/embed/' + ytId + '?rel=0" allowfullscreen loading="lazy"></iframe></div>'
          + (v.Title ? '<div class="video-caption">' + v.Title + '</div>' : '')
          + '</div>';
      }
      var platform = url.includes('tiktok.com') ? 'TikTok' : 'Video';
      return '<a class="video-link-card" href="' + url + '" target="_blank" rel="noopener">'
        + '<span class="video-icon">&#9654;</span>'
        + '<span><div class="video-link-title">' + (v.Title || 'Watch') + '</div>'
        + '<div class="video-link-platform">' + platform + '</div></span></a>';
    }).join('');
  }

  // ── Reviews ──────────────────────────────────────────────────
  if (reviews.length) {
    document.getElementById('reviewList').innerHTML = reviews.map(function(r) {
      var byline = (r['Alt Value'] && r['Alt Value'].trim()) ? r['Alt Value'].trim() : '';
      return '<div class="review-card">'
        + '<p class="review-quote">' + r.Value + '</p>'
        + (byline ? '<span class="review-byline">— ' + byline + '</span>' : '')
        + '</div>';
    }).join('');
  }

  // ── Rules ────────────────────────────────────────────────────
  if (data.rules && data.rules.length) {
    var sections = [], cur = null;
    data.rules.forEach(function(row) {
      if (row.Type === 'menu' && row.Level == 1) {
        cur = { title: row.Text, rows: [] };
        sections.push(cur);
      } else if (cur) {
        cur.rows.push(row);
      }
    });

    document.getElementById('rulesAccordion').innerHTML = sections.map(function(sec, i) {
      var bodyId = 'rb_' + i;
      var body = sec.rows.map(function(r) {
        if (r.Type === 'image' && r.Text)
          return '<img class="rule-img" src="' + cachedImage(r.Text) + '" loading="lazy" alt="" />';
        if (r.Type === 'text' && r.Text)
          return '<p class="rule-text">' + applyTags(r.Text.replace(/\n/g,'<br>')) + '</p>';
        return '';
      }).join('');
      return '<div class="accordion-item">'
        + '<h2 class="accordion-header"><button class="accordion-button' + (i?'  collapsed':'') + '" type="button" data-bs-toggle="collapse" data-bs-target="#' + bodyId + '" aria-expanded="' + (!i) + '">' + sec.title + '</button></h2>'
        + '<div id="' + bodyId + '" class="accordion-collapse collapse' + (i?'':' show') + '">'
        + '<div class="accordion-body">' + body + '</div></div></div>';
    }).join('');
  }

  // ── FAQs ─────────────────────────────────────────────────────
  if (data.faqs && data.faqs.length) {
    document.getElementById('faqList').innerHTML = data.faqs.map(function(f, i) {
      var img = f.Image ? '<img src="' + cachedImage(f.Image) + '" loading="lazy" alt="" />' : '';
      return '<div class="faq-item">'
        + '<div class="faq-q" id="fqq' + i + '"><span>' + f.Question + '</span><span class="chev">&#9660;</span></div>'
        + '<div class="faq-a" id="fqa' + i + '">' + f.Answer + img + '</div>'
        + '</div>';
    }).join('');
    document.querySelectorAll('.faq-q').forEach(function(q) {
      q.addEventListener('click', function() {
        var a = document.getElementById(this.id.replace('fqq','fqa'));
        var open = this.classList.contains('open');
        a.style.display = open ? 'none' : 'block';
        this.classList.toggle('open', !open);
      });
    });
  }

  // ── Lightbox ─────────────────────────────────────────────────
  document.getElementById('lbClose').addEventListener('click', function(){
    document.getElementById('lightbox').classList.remove('open');
  });
  document.getElementById('lightbox').addEventListener('click', function(e){
    if (e.target === this) this.classList.remove('open');
  });

  } finally {
    document.getElementById('loadScreen').style.display = 'none';
  }
}

function activateTab(id) {
  document.querySelectorAll('.tab-btn').forEach(function(b) {
    b.classList.toggle('active', b.dataset.tab === id);
  });
  document.querySelectorAll('.tab-pane').forEach(function(p) {
    p.classList.toggle('active', p.id === 'pane-' + id);
  });
}
</script>
</body>
</html>
