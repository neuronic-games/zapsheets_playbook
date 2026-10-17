<?php
/**
 * GameBoard — polished public product page for a single board game.
 * URL: /{sheet_id}/gameboard/{game-slug}
 * Public / no sign-in required.
 */
error_reporting(0);

$_bpFile = dirname(__DIR__, 2) . '/dotEnv.php';
if (file_exists($_bpFile)) { require_once $_bpFile; }

$_rp   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_bp   = rtrim($_ENV['BASE_PATH'] ?? '', '/');
$_rps  = ($_bp !== '' && str_starts_with($_rp, $_bp)) ? substr($_rp, strlen($_bp)) : $_rp;

// Match /{sheet_id}/gameboard/{game-slug}
preg_match('#^/([A-Za-z0-9_\-]+)/gameboard/([^/?]+)#', $_rps, $_m);
$_sheetId  = $_m[1] ?? '';
$_gameSlug = $_m[2] ?? '';

if (!$_sheetId) { http_response_code(404); exit; }

$_root     = dirname(__DIR__, 2);
$_sDir     = $_root . '/sheets/' . $_sheetId;
$_appRoot  = $_bp . '/';

function _gb_load(string $f): array {
    return file_exists($f) ? (json_decode(file_get_contents($f), true) ?: []) : [];
}

function _gb_slug(string $s): string {
    return strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
}

function _gb_img(string $raw): string {
    if (preg_match('/^=IMAGE\("([^"]*)"\)/i', $raw, $m)) return $m[1];
    return $raw;
}

// Load games and find the requested one
$_games = _gb_load($_sDir . '/games.json');

// If no slug given, redirect to the first published/signed game
if (!$_gameSlug) {
    foreach ($_games as $g) {
        $st = strtolower($g['Status'] ?? '');
        if (in_array($st, ['signed','published'])) {
            header('Location: ' . $_appRoot . $_sheetId . '/gameboard/' . _gb_slug($g['Name'] ?? 'game'));
            exit;
        }
    }
    if (!empty($_games)) {
        header('Location: ' . $_appRoot . $_sheetId . '/gameboard/' . _gb_slug($_games[0]['Name'] ?? 'game'));
        exit;
    }
    http_response_code(404); exit;
}

$_game = null;
foreach ($_games as $g) {
    if (_gb_slug($g['Name'] ?? '') === $_gameSlug) {
        $_game = $g;
        break;
    }
}

if (!$_game) { http_response_code(404); exit; }

// ── Data extraction ──────────────────────────────────────────────────────────
$_name        = trim($_game['Name']          ?? '');
$_summary     = trim($_game['Summary']       ?? '');
$_description = trim($_game['Description']   ?? '');
$_count       = trim($_game['Count']         ?? '');
$_duration    = trim($_game['Duration']      ?? '');
$_availability= trim($_game['Availability']  ?? '');
$_status      = trim($_game['Status']        ?? '');
$_coverUrl    = _gb_img(trim($_game['Cover URL']   ?? ''));
$_imageUrl    = _gb_img(trim($_game['Image URL']   ?? ''));
$_videoUrl    = trim($_game['Video URL']     ?? '');
$_pageUrl     = trim($_game['Page URL']      ?? '');
$_sellsheet   = trim($_game['Sellsheet URL'] ?? '');
$_playUrl     = trim($_game['Play URL']      ?? '');
$_printUrl    = trim($_game['Print URL']     ?? '');
$_rulesUrl    = trim($_game['Rules URL']     ?? '');
$_pbSheetId   = trim($_game['Playbook Sheet ID'] ?? '');
$_dateSigned  = trim($_game['Date Signed']   ?? '');
$_datePub     = trim($_game['Date Published']?? '');

// Designers
$_designers = [];
for ($i = 1; $i <= 4; $i++) {
    $d = trim($_game["Designer$i"] ?? '');
    if ($d) $_designers[] = $d;
}

// Hero image — prefer Cover URL, fall back to Image URL
$_heroImg = $_coverUrl ?: $_imageUrl;

// Gallery images (additional images beyond the hero)
$_galleryImgs = [];
foreach ([$_coverUrl, $_imageUrl] as $img) {
    if ($img && $img !== $_heroImg && count($_galleryImgs) < 5) $_galleryImgs[] = $img;
}

// Embed-friendly YouTube URL
function _gb_embed(string $url): string {
    if (!$url) return '';
    if (preg_match('/youtube\.com\/embed\//', $url)) return $url;
    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_\-]+)/', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    return $url;
}
$_videoEmbed = _gb_embed($_videoUrl);

// Components from Playbook Sheet
$_components = [];
if ($_pbSheetId) {
    $compFile = $_root . '/sheets/' . $_pbSheetId . '/components.json';
    if (file_exists($compFile)) {
        $_components = _gb_load($compFile);
    }
}

// Other games for "More Games" section (same sheet, exclude current)
$_otherGames = [];
foreach ($_games as $g) {
    $st = strtolower($g['Status'] ?? '');
    if (_gb_slug($g['Name'] ?? '') !== $_gameSlug && in_array($st, ['signed','published','interested'])) {
        $_otherGames[] = $g;
    }
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= h($_name) ?><?= $_summary ? ' — ' . h($_summary) : '' ?></title>
<meta name="description" content="<?= h(substr($_description ?: $_summary, 0, 160)) ?>" />
<?php if ($_heroImg): ?>
<meta property="og:image" content="<?= h($_heroImg) ?>" />
<?php endif; ?>
<style>
@font-face { font-family:'DINBlack';   src:url('<?= $_appRoot ?>fonts/DINBlack.woff2')  format('woff2'); }
@font-face { font-family:'DINRegular'; src:url('<?= $_appRoot ?>fonts/DINMedium.woff2') format('woff2'); }

*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }

:root {
  --ink:        #1a1a2e;
  --ink-lt:     #444;
  --muted:      #888;
  --border:     #e0e0e0;
  --surface:    #fff;
  --bg:         #f7f5f2;
  --accent:     #3b3baa;
  --accent-dk:  #2d2d88;
  --accent-lt:  #eef;
  --gold:       #c8960c;
}

body { background:var(--bg); font-family:'DINRegular',Arial,sans-serif; color:var(--ink); min-height:100vh; }

a { color:inherit; text-decoration:none; }

/* ── Nav ── */
.gb-nav {
  background:var(--ink); color:#fff;
  display:flex; align-items:center; justify-content:space-between;
  padding:.75rem 1.5rem; position:sticky; top:0; z-index:100;
}
.gb-nav-logo { font-family:'DINBlack',sans-serif; font-size:1.05rem; letter-spacing:.06em; color:#7c9eff; }
.gb-nav-game { font-size:.82rem; opacity:.65; letter-spacing:.03em; margin-left:.75rem; }
.gb-nav-links { display:flex; gap:.5rem; }
.btn-nav {
  font-family:'DINBlack',sans-serif; font-size:.7rem; text-transform:uppercase;
  letter-spacing:.07em; padding:.35rem .8rem; border-radius:5px; cursor:pointer;
  border:1.5px solid rgba(255,255,255,.3); color:#fff; background:transparent;
  transition:background .15s; white-space:nowrap;
}
.btn-nav:hover { background:rgba(255,255,255,.15); border-color:#fff; }
.btn-nav.filled { background:#fff; color:var(--ink); border-color:#fff; }
.btn-nav.filled:hover { background:#eee; }

/* ── Hero ── */
.gb-hero {
  position:relative; background:var(--ink); color:#fff;
  min-height:65vh; display:flex; align-items:flex-end;
  overflow:hidden;
}
.gb-hero-bg {
  position:absolute; inset:0;
  background-size:cover; background-position:center;
}
.gb-hero-overlay {
  position:absolute; inset:0;
  background:linear-gradient(to top, rgba(10,10,30,.92) 0%, rgba(10,10,30,.5) 50%, rgba(10,10,30,.1) 100%);
}
.gb-hero-content {
  position:relative; z-index:2;
  max-width:900px; padding:3rem 2rem 3.5rem;
}
.gb-status-badge {
  display:inline-block; font-family:'DINBlack',sans-serif; font-size:.68rem;
  text-transform:uppercase; letter-spacing:.1em; padding:.25rem .7rem;
  border-radius:999px; border:1.5px solid rgba(255,255,255,.4);
  color:rgba(255,255,255,.85); margin-bottom:.9rem;
}
.gb-hero-title {
  font-family:'DINBlack',sans-serif; font-size:clamp(2rem,6vw,3.5rem);
  line-height:1.05; letter-spacing:.02em; margin-bottom:.7rem;
  text-shadow:0 2px 16px rgba(0,0,0,.6);
}
.gb-hero-summary {
  font-size:clamp(.95rem,2.2vw,1.15rem); opacity:.85; max-width:560px;
  line-height:1.5; margin-bottom:1.4rem;
}
.gb-stats {
  display:flex; flex-wrap:wrap; gap:.55rem 1.4rem; margin-bottom:1.6rem;
}
.gb-stat { font-size:.82rem; opacity:.8; }
.gb-stat strong { font-family:'DINBlack',sans-serif; opacity:1; margin-right:.2rem; }
.gb-hero-ctas { display:flex; flex-wrap:wrap; gap:.55rem; }

/* ── Main layout ── */
.gb-main { max-width:960px; margin:0 auto; padding:3rem 1.25rem 5rem; }

/* ── Section ── */
.gb-section { margin-bottom:3.5rem; }
.gb-section-label {
  font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase;
  letter-spacing:.1em; color:var(--muted); margin-bottom:.9rem;
  padding-bottom:.5rem; border-bottom:1px solid var(--border);
}

/* ── Description ── */
.gb-description {
  font-size:1rem; line-height:1.72; color:var(--ink-lt);
  white-space:pre-wrap;
}

/* ── Gallery ── */
.gb-gallery { display:flex; gap:.6rem; overflow-x:auto; scroll-snap-type:x mandatory; }
.gb-gallery img {
  height:220px; border-radius:8px; object-fit:cover;
  flex-shrink:0; scroll-snap-align:start; cursor:pointer;
  transition:transform .2s; box-shadow:0 2px 12px rgba(0,0,0,.12);
}
.gb-gallery img:hover { transform:scale(1.015); }

/* ── Video ── */
.gb-video-wrap {
  position:relative; padding-top:56.25%; border-radius:10px;
  overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.14);
}
.gb-video-wrap iframe {
  position:absolute; inset:0; width:100%; height:100%; border:none;
}

/* ── Components ── */
.gb-components { display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:.55rem; }
.gb-comp-item {
  display:flex; align-items:baseline; gap:.6rem;
  background:var(--surface); border:1px solid var(--border);
  border-radius:7px; padding:.55rem .75rem; font-size:.87rem;
}
.gb-comp-qty {
  font-family:'DINBlack',sans-serif; font-size:1rem;
  color:var(--accent); flex-shrink:0; min-width:1.6rem;
}
.gb-comp-name { color:var(--ink-lt); }

/* ── Links grid ── */
.gb-links { display:flex; flex-wrap:wrap; gap:.7rem; }
.btn-link {
  font-family:'DINBlack',sans-serif; font-size:.76rem; text-transform:uppercase;
  letter-spacing:.07em; padding:.55rem 1.1rem; border-radius:7px;
  border:2px solid var(--accent); color:var(--accent); background:#fff;
  transition:background .14s, color .14s; cursor:pointer;
}
.btn-link:hover { background:var(--accent); color:#fff; }
.btn-link.primary { background:var(--accent); color:#fff; }
.btn-link.primary:hover { background:var(--accent-dk); }

/* ── More games ── */
.gb-more-grid {
  display:grid; grid-template-columns:repeat(auto-fill, minmax(170px, 1fr)); gap:1rem;
}
.gb-more-card {
  background:var(--surface); border-radius:10px;
  box-shadow:0 2px 10px rgba(0,0,0,.08); overflow:hidden;
  transition:box-shadow .15s, transform .15s; cursor:pointer;
}
.gb-more-card:hover { box-shadow:0 4px 20px rgba(59,59,170,.16); transform:translateY(-2px); }
.gb-more-card img { width:100%; aspect-ratio:1; object-fit:cover; display:block; }
.gb-more-card-body { padding:.6rem .75rem .8rem; }
.gb-more-card-name { font-family:'DINBlack',sans-serif; font-size:.82rem; color:var(--accent); margin-bottom:.15rem; }
.gb-more-card-meta { font-size:.74rem; color:var(--muted); }
.gb-more-card-placeholder {
  width:100%; aspect-ratio:1; background:linear-gradient(135deg, #dde 0%, #c8c8e8 100%);
  display:flex; align-items:center; justify-content:center;
}
.gb-more-card-placeholder span { font-size:2.5rem; opacity:.4; }

/* ── Footer ── */
.gb-footer {
  background:var(--ink); color:rgba(255,255,255,.6);
  text-align:center; padding:2rem 1.5rem;
  font-size:.78rem; letter-spacing:.03em;
}
.gb-footer strong { color:#fff; font-family:'DINBlack',sans-serif; }

/* ── Lightbox ── */
.gb-lightbox {
  display:none; position:fixed; inset:0; z-index:500;
  background:rgba(0,0,0,.9); align-items:center; justify-content:center;
  cursor:zoom-out;
}
.gb-lightbox.open { display:flex; }
.gb-lightbox img { max-width:92vw; max-height:92vh; border-radius:8px; object-fit:contain; }

@media (max-width:640px) {
  .gb-hero-content { padding:2rem 1.2rem 2.5rem; }
  .gb-nav-game { display:none; }
  .gb-main { padding:2rem .9rem 4rem; }
  .gb-components { grid-template-columns:1fr 1fr; }
  .gb-more-grid { grid-template-columns:repeat(2, 1fr); }
}
</style>
</head>
<body>

<!-- ── Navigation ── -->
<nav class="gb-nav">
  <div style="display:flex;align-items:center">
    <a class="gb-nav-logo" href="<?= h($_appRoot . $_sheetId . '/gameboard/' . _gb_slug($_name)) ?>">GameBoard</a>
    <span class="gb-nav-game"><?= h($_name) ?></span>
  </div>
  <div class="gb-nav-links">
    <?php if ($_rulesUrl): ?><a class="btn-nav" href="<?= h($_rulesUrl) ?>" target="_blank">Rules</a><?php endif; ?>
    <?php if ($_sellsheet): ?><a class="btn-nav" href="<?= h($_sellsheet) ?>" target="_blank">Sellsheet</a><?php endif; ?>
    <?php if ($_pageUrl): ?><a class="btn-nav filled" href="<?= h($_pageUrl) ?>" target="_blank">View Page ↗</a><?php endif; ?>
  </div>
</nav>

<!-- ── Hero ── -->
<div class="gb-hero">
  <?php if ($_heroImg): ?>
  <div class="gb-hero-bg" style="background-image:url('<?= h($_heroImg) ?>')"></div>
  <?php else: ?>
  <div class="gb-hero-bg" style="background:linear-gradient(135deg,#1a1a2e 0%,#2d2d88 100%)"></div>
  <?php endif; ?>
  <div class="gb-hero-overlay"></div>
  <div class="gb-hero-content">
    <?php if ($_status): ?>
    <div class="gb-status-badge"><?= h(ucfirst($_status)) ?></div>
    <?php endif; ?>
    <h1 class="gb-hero-title"><?= h($_name) ?></h1>
    <?php if ($_summary): ?>
    <p class="gb-hero-summary"><?= h($_summary) ?></p>
    <?php endif; ?>
    <?php if ($_count || $_duration || $_designers || $_availability): ?>
    <div class="gb-stats">
      <?php if ($_count): ?><div class="gb-stat"><strong>👥</strong><?= h($_count) ?> players</div><?php endif; ?>
      <?php if ($_duration): ?><div class="gb-stat"><strong>⏱</strong><?= h($_duration) ?></div><?php endif; ?>
      <?php if ($_designers): ?><div class="gb-stat"><strong>🎨</strong><?= h(implode(', ', $_designers)) ?></div><?php endif; ?>
      <?php if ($_availability): ?><div class="gb-stat"><strong>📦</strong><?= h($_availability) ?></div><?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="gb-hero-ctas">
      <?php if ($_rulesUrl): ?><a class="btn-nav" href="<?= h($_rulesUrl) ?>" target="_blank">📖 Rules</a><?php endif; ?>
      <?php if ($_playUrl): ?><a class="btn-nav" href="<?= h($_playUrl) ?>" target="_blank">🎮 Play Online</a><?php endif; ?>
      <?php if ($_printUrl): ?><a class="btn-nav" href="<?= h($_printUrl) ?>" target="_blank">🖨 Print &amp; Play</a><?php endif; ?>
      <?php if ($_sellsheet): ?><a class="btn-nav filled" href="<?= h($_sellsheet) ?>" target="_blank">📄 Sellsheet</a><?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Main content ── -->
<div class="gb-main">

  <!-- Description -->
  <?php if ($_description): ?>
  <div class="gb-section">
    <div class="gb-section-label">About the Game</div>
    <p class="gb-description"><?= h($_description) ?></p>
  </div>
  <?php endif; ?>

  <!-- Gallery -->
  <?php
  $_allImgs = array_values(array_unique(array_filter([$_coverUrl, $_imageUrl])));
  if (count($_allImgs) > 1): ?>
  <div class="gb-section">
    <div class="gb-section-label">Gallery</div>
    <div class="gb-gallery">
      <?php foreach ($_allImgs as $img): ?>
      <img src="<?= h($img) ?>" alt="<?= h($_name) ?>" loading="lazy" onclick="openLightbox('<?= h($img) ?>')" />
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Video -->
  <?php if ($_videoEmbed): ?>
  <div class="gb-section">
    <div class="gb-section-label">Watch</div>
    <div class="gb-video-wrap">
      <iframe src="<?= h($_videoEmbed) ?>" allow="autoplay; encrypted-media" allowfullscreen loading="lazy"></iframe>
    </div>
  </div>
  <?php endif; ?>

  <!-- Components -->
  <?php if (!empty($_components)): ?>
  <div class="gb-section">
    <div class="gb-section-label">What's in the Box</div>
    <div class="gb-components">
      <?php foreach ($_components as $c):
        $cName = trim($c['Name'] ?? $c['Component'] ?? $c['Item'] ?? '');
        $cQty  = trim($c['Count'] ?? $c['Qty'] ?? $c['Quantity'] ?? '');
        if (!$cName) continue;
      ?>
      <div class="gb-comp-item">
        <?php if ($cQty): ?><span class="gb-comp-qty"><?= h($cQty) ?></span><?php endif; ?>
        <span class="gb-comp-name"><?= h($cName) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Links -->
  <?php
  $_hasLinks = $_rulesUrl || $_sellsheet || $_playUrl || $_printUrl || $_pageUrl;
  if ($_hasLinks): ?>
  <div class="gb-section">
    <div class="gb-section-label">Resources</div>
    <div class="gb-links">
      <?php if ($_rulesUrl): ?><a class="btn-link primary" href="<?= h($_rulesUrl) ?>" target="_blank">📖 Rules</a><?php endif; ?>
      <?php if ($_sellsheet): ?><a class="btn-link" href="<?= h($_sellsheet) ?>" target="_blank">📄 Sellsheet</a><?php endif; ?>
      <?php if ($_playUrl): ?><a class="btn-link" href="<?= h($_playUrl) ?>" target="_blank">🎮 Play Online</a><?php endif; ?>
      <?php if ($_printUrl): ?><a class="btn-link" href="<?= h($_printUrl) ?>" target="_blank">🖨 Print &amp; Play</a><?php endif; ?>
      <?php if ($_pageUrl): ?><a class="btn-link" href="<?= h($_pageUrl) ?>" target="_blank">🔗 Product Page</a><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- More games -->
  <?php if (!empty($_otherGames)): ?>
  <div class="gb-section">
    <div class="gb-section-label">More Games</div>
    <div class="gb-more-grid">
      <?php foreach (array_slice($_otherGames, 0, 8) as $og):
        $ogName  = trim($og['Name'] ?? '');
        $ogSlug  = _gb_slug($ogName);
        $ogImg   = _gb_img(trim($og['Cover URL'] ?? $og['Image URL'] ?? ''));
        $ogCount = trim($og['Count'] ?? '');
        $ogDur   = trim($og['Duration'] ?? '');
      ?>
      <a class="gb-more-card" href="<?= h($_appRoot . $_sheetId . '/gameboard/' . $ogSlug) ?>">
        <?php if ($ogImg): ?>
        <img src="<?= h($ogImg) ?>" alt="<?= h($ogName) ?>" loading="lazy" />
        <?php else: ?>
        <div class="gb-more-card-placeholder"><span>🎲</span></div>
        <?php endif; ?>
        <div class="gb-more-card-body">
          <div class="gb-more-card-name"><?= h($ogName) ?></div>
          <?php if ($ogCount || $ogDur): ?>
          <div class="gb-more-card-meta"><?= $ogCount ? h($ogCount) . ' players' : '' ?><?= ($ogCount && $ogDur) ? ' · ' : '' ?><?= $ogDur ? h($ogDur) : '' ?></div>
          <?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /gb-main -->

<!-- ── Footer ── -->
<footer class="gb-footer">
  <strong><?= h($_name) ?></strong>
  <?php if ($_designers): ?> &nbsp;·&nbsp; Designed by <?= h(implode(' &amp; ', $_designers)) ?><?php endif; ?>
  <?php if ($_datePub): ?> &nbsp;·&nbsp; Published <?= h($_datePub) ?><?php elseif ($_dateSigned): ?> &nbsp;·&nbsp; Signed <?= h($_dateSigned) ?><?php endif; ?>
</footer>

<!-- ── Lightbox ── -->
<div class="gb-lightbox" id="lightbox" onclick="closeLightbox()">
  <img id="lightboxImg" src="" alt="" />
</div>

<script>
function openLightbox(src) {
  document.getElementById('lightboxImg').src = src;
  document.getElementById('lightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeLightbox() {
  document.getElementById('lightbox').classList.remove('open');
  document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeLightbox();
});
</script>
</body>
</html>
