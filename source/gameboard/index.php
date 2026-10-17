<?php
/**
 * GameBoard — polished public product page for a single board game.
 * URL: /{sheet_id}/gameboard/{game-slug}
 * Data: games.json (main info) + {game_name}.json from Playbook Sheet (rich content)
 * Public / no sign-in required.
 */
error_reporting(0);

$_bpFile = dirname(__DIR__, 2) . '/dotEnv.php';
if (file_exists($_bpFile)) { require_once $_bpFile; }

$_rp   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_bp   = rtrim($_ENV['BASE_PATH'] ?? '', '/');
$_rps  = ($_bp !== '' && str_starts_with($_rp, $_bp)) ? substr($_rp, strlen($_bp)) : $_rp;

preg_match('#^/([A-Za-z0-9_\-]+)/gameboard(?:/([^/?]*))?#', $_rps, $_m);
$_sheetId  = $_m[1] ?? '';
$_gameSlug = urldecode(trim($_m[2] ?? ''));

if (!$_sheetId) { http_response_code(404); exit; }

$_root    = dirname(__DIR__, 2);
$_sDir    = $_root . '/sheets/' . $_sheetId;
$_appRoot = $_bp . '/';

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

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function _gb_embed(string $url): string {
    if (!$url) return '';
    if (preg_match('/youtube\.com\/embed\//', $url)) return $url;
    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_\-]+)/', $url, $m))
        return 'https://www.youtube.com/embed/' . $m[1];
    if (preg_match('/youtube\.com\/shorts\/([A-Za-z0-9_\-]+)/', $url, $m))
        return 'https://www.youtube.com/embed/' . $m[1];
    return '';  // skip non-YouTube for embed safety
}

// ── Load settings and determine template ────────────────────────────────────
$_settings = _gb_load($_sDir . '/settings.json');
$_template = strtolower(trim($_settings['Gameboard Template'] ?? ''));
// Supported templates: '' / 'classic' = Classic (default), 'opensea' = OpenSea

// ── Load games list and find requested game ──────────────────────────────────
$_games = _gb_load($_sDir . '/games.json');

if (!$_gameSlug) {
    // ── No slug: render the game catalog ────────────────────────────────────────
    $_pubName  = trim($_settings['Company'] ?? $_settings['Name'] ?? '');
    $_pubLogo  = trim($_settings['Logo URL'] ?? '');
    $__catalog_url = $_appRoot . $_sheetId . '/gameboard/';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= h($_pubName ?: 'GameBoard') ?> — Games Catalog</title>
<style>
@font-face { font-family:'DINBlack';   src:url('<?= h($_appRoot) ?>fonts/DINBlack.woff2')  format('woff2'); }
@font-face { font-family:'DINRegular'; src:url('<?= h($_appRoot) ?>fonts/DINMedium.woff2') format('woff2'); }
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --ink:#1a1a2e; --ink-lt:#4a4a6a; --muted:#888; --border:#e2e2ec;
  --surface:#fff; --bg:#f6f5f2; --accent:#3b3baa; --nav-bg:#1a1a2e;
<?php if ($_template === 'opensea'): ?>
  --ink:#0d1b2a; --ink-lt:#2c4a6e; --muted:#6b8fae; --border:#c8dcea;
  --surface:#fff; --bg:#f0f6fb; --accent:#0097a7; --nav-bg:#0d1b2a;
<?php endif; ?>
}
body { background:var(--bg); font-family:'DINRegular',Arial,sans-serif; color:var(--ink); min-height:100vh; }
a { color:inherit; text-decoration:none; }

/* Nav */
.gb-nav { background:var(--nav-bg); color:#fff; display:flex; align-items:center; justify-content:space-between; padding:.75rem 1.5rem; gap:1rem; }
.gb-nav-left { display:flex; align-items:center; gap:.75rem; }
.gb-nav-logo { font-family:'DINBlack',sans-serif; font-size:1rem; letter-spacing:.06em; color:<?= $_template==='opensea' ? '#00e5ff' : '#7c9eff' ?>; }
.gb-nav-pub { font-size:.82rem; opacity:.65; }
.gb-nav-logo img { height:28px; border-radius:4px; vertical-align:middle; margin-right:.35rem; }

/* Hero strip */
.cat-hero { background:var(--nav-bg); color:#fff; padding:2.8rem 1.5rem 2.4rem; text-align:center; }
.cat-hero-title { font-family:'DINBlack',sans-serif; font-size:clamp(1.8rem,6vw,3rem); letter-spacing:.04em; margin-bottom:.5rem; }
.cat-hero-sub { font-size:.95rem; opacity:.6; }

/* Filters */
.cat-filters { max-width:960px; margin:1.8rem auto 0; padding:0 1.25rem; display:flex; flex-wrap:wrap; gap:.4rem; align-items:center; }
.cat-filter-btn {
  font-family:'DINBlack',sans-serif; font-size:.68rem; text-transform:uppercase; letter-spacing:.07em;
  padding:.28rem .7rem; border-radius:999px; border:1.5px solid var(--border);
  background:var(--surface); color:var(--ink-lt); cursor:pointer; transition:background .13s;
}
.cat-filter-btn:hover, .cat-filter-btn.active { background:var(--accent); color:#fff; border-color:var(--accent); }

/* Grid */
.cat-grid { max-width:960px; margin:1.5rem auto 5rem; padding:0 1.25rem; display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:1.1rem; }
.cat-card { background:var(--surface); border-radius:12px; box-shadow:0 2px 14px rgba(0,0,0,.08); overflow:hidden; transition:box-shadow .15s, transform .15s; cursor:pointer; display:flex; flex-direction:column; }
.cat-card:hover { box-shadow:0 6px 24px rgba(59,59,170,.14); transform:translateY(-3px); }
<?php if ($_template==='opensea'): ?>
.cat-card:hover { box-shadow:0 6px 24px rgba(0,151,167,.18); }
<?php endif; ?>
.cat-card-img { width:100%; aspect-ratio:1; object-fit:cover; display:block; background:linear-gradient(135deg,#dde 0%,#c8c8e8 100%); }
.cat-card-ph { width:100%; aspect-ratio:1; display:flex; align-items:center; justify-content:center; font-size:3.5rem; opacity:.3; background:linear-gradient(135deg,#dde 0%,#c8c8e8 100%); }
.cat-card-body { padding:.75rem .9rem 1rem; flex:1; display:flex; flex-direction:column; gap:.25rem; }
.cat-card-name { font-family:'DINBlack',sans-serif; font-size:.95rem; color:var(--accent); line-height:1.2; }
.cat-card-summary { font-size:.8rem; color:var(--ink-lt); line-height:1.45; flex:1; margin-top:.1rem; }
.cat-card-meta { display:flex; flex-wrap:wrap; gap:.35rem .7rem; margin-top:.35rem; }
.cat-card-stat { font-size:.72rem; color:var(--muted); }
.cat-card-status { font-family:'DINBlack',sans-serif; font-size:.62rem; text-transform:uppercase; letter-spacing:.08em; padding:.18rem .5rem; border-radius:999px; border:1px solid currentColor; align-self:flex-start; margin-top:.3rem; }
.cat-card-status.published { color:#2e7d32; border-color:#a5d6a7; background:#f1f8e9; }
.cat-card-status.signed     { color:#1565c0; border-color:#90caf9; background:#e3f2fd; }
.cat-card-status.interested { color:#e65100; border-color:#ffcc80; background:#fff3e0; }

/* Empty state */
.cat-empty { max-width:480px; margin:5rem auto; text-align:center; color:var(--muted); }
.cat-empty-icon { font-size:3.5rem; margin-bottom:1rem; }
.cat-empty-title { font-family:'DINBlack',sans-serif; font-size:1.1rem; color:var(--ink); margin-bottom:.5rem; }
.cat-empty-body { font-size:.88rem; line-height:1.65; }

/* Footer */
.gb-footer { background:var(--nav-bg); color:rgba(255,255,255,.5); text-align:center; padding:1.5rem; font-size:.76rem; }

@media(max-width:520px) { .cat-grid { grid-template-columns:1fr 1fr; } }
@media(max-width:360px) { .cat-grid { grid-template-columns:1fr; } }
</style>
</head>
<body>

<nav class="gb-nav">
  <div class="gb-nav-left">
    <?php if ($_pubLogo): ?><img src="<?= h($_pubLogo) ?>" alt="" style="height:28px;border-radius:4px;" /><?php endif; ?>
    <span class="gb-nav-logo">GameBoard</span>
    <?php if ($_pubName): ?><span class="gb-nav-pub"><?= h($_pubName) ?></span><?php endif; ?>
  </div>
</nav>

<div class="cat-hero">
  <div class="cat-hero-title"><?= h($_pubName ?: 'Our Games') ?></div>
  <?php if (!empty($_games)): ?><div class="cat-hero-sub"><?= count($_games) ?> game<?= count($_games) !== 1 ? 's' : '' ?></div><?php endif; ?>
</div>

<?php
// Collect unique statuses for filter buttons
$_statuses = [];
foreach ($_games as $g) { $s = strtolower(trim($g['Status'] ?? '')); if ($s && !in_array($s, $_statuses)) $_statuses[] = $s; }
if (count($_statuses) > 1): ?>
<div class="cat-filters">
  <button class="cat-filter-btn active" onclick="filterCatalog('',this)">All</button>
  <?php foreach ($_statuses as $st): ?>
  <button class="cat-filter-btn" onclick="filterCatalog('<?= h($st) ?>',this)"><?= h(ucfirst($st)) ?></button>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="cat-grid" id="catGrid">
<?php if (empty($_games)): ?>
  <div class="cat-empty" style="grid-column:1/-1">
    <div class="cat-empty-icon">🎲</div>
    <div class="cat-empty-title">No games yet</div>
    <div class="cat-empty-body">Add games to your Google Sheet and fetch the data to see them here.</div>
  </div>
<?php else: ?>
  <?php foreach ($_games as $g):
    $gName    = trim($g['Name']       ?? '');
    $gSummary = trim($g['Summary']    ?? '');
    $gStatus  = strtolower(trim($g['Status'] ?? ''));
    $gCount   = trim($g['Count']      ?? '');
    $gDur     = trim($g['Duration']   ?? '');
    $gCover   = _gb_img(trim($g['Cover URL'] ?? $g['Image URL'] ?? ''));
    $gSlug    = _gb_slug($gName);
    if (!$gName) continue;
  ?>
  <a class="cat-card" href="<?= h($__catalog_url . $gSlug) ?>" data-status="<?= h($gStatus) ?>">
    <?php if ($gCover): ?>
    <img class="cat-card-img" src="<?= h($gCover) ?>" alt="<?= h($gName) ?>" loading="lazy" />
    <?php else: ?>
    <div class="cat-card-ph">🎲</div>
    <?php endif; ?>
    <div class="cat-card-body">
      <div class="cat-card-name"><?= h($gName) ?></div>
      <?php if ($gSummary): ?><div class="cat-card-summary"><?= h($gSummary) ?></div><?php endif; ?>
      <div class="cat-card-meta">
        <?php if ($gCount): ?><span class="cat-card-stat">👥 <?= h($gCount) ?></span><?php endif; ?>
        <?php if ($gDur):   ?><span class="cat-card-stat">⏱ <?= h($gDur) ?></span><?php endif; ?>
      </div>
      <?php if ($gStatus): ?><span class="cat-card-status <?= h($gStatus) ?>"><?= h(ucfirst($gStatus)) ?></span><?php endif; ?>
    </div>
  </a>
  <?php endforeach; ?>
<?php endif; ?>
</div>

<footer class="gb-footer">
  <?= h($_pubName ?: 'GameBoard') ?> &nbsp;·&nbsp; Powered by GameBoard
</footer>

<script>
function filterCatalog(status, btn) {
  document.querySelectorAll('.cat-filter-btn').forEach(function(b){ b.classList.remove('active'); });
  btn.classList.add('active');
  document.querySelectorAll('.cat-card').forEach(function(c){
    c.style.display = (!status || c.dataset.status === status) ? '' : 'none';
  });
}
</script>
</body>
</html>
<?php
    exit;
}
// ── END CATALOG ──────────────────────────────────────────────────────────────

$_game = null;
foreach ($_games as $g) {
    if (_gb_slug($g['Name'] ?? '') === _gb_slug($_gameSlug)) { $_game = $g; break; }
}

if (!$_game) { http_response_code(404); exit; }

// ── Core fields from games.json ──────────────────────────────────────────────
$_name        = trim($_game['Name']           ?? '');
$_summary     = trim($_game['Summary']        ?? '');
$_description = trim($_game['Description']    ?? '');
$_count       = trim($_game['Count']          ?? '');
$_duration    = trim($_game['Duration']       ?? '');
$_availability= trim($_game['Availability']   ?? '');
$_status      = trim($_game['Status']         ?? '');
$_coverUrl    = _gb_img(trim($_game['Cover URL']    ?? ''));
$_imageUrl    = _gb_img(trim($_game['Image URL']    ?? ''));
$_videoUrl    = trim($_game['Video URL']      ?? '');
$_pageUrl     = trim($_game['Page URL']       ?? '');
$_sellsheet   = trim($_game['Sellsheet URL']  ?? '');
$_playUrl     = trim($_game['Play URL']       ?? '');
$_printUrl    = trim($_game['Print URL']      ?? '');
$_rulesUrl    = trim($_game['Rules URL']      ?? '');
$_pbSheetId   = trim($_game['Playbook Sheet ID'] ?? '');
$_dateSigned  = trim($_game['Date Signed']    ?? '');
$_datePub     = trim($_game['Date Published'] ?? '');

$_designers = [];
for ($i = 1; $i <= 4; $i++) {
    $d = trim($_game["Designer$i"] ?? '');
    if ($d) $_designers[] = $d;
}

// ── Load rich game-specific data from Playbook Sheet ─────────────────────────
// File: sheets/{playbook_sheet_id}/{lowercase_game_name}.json
// Format: [{Name, Value, "Value 1", "Value 2"}, ...]
$_rich = [];
if ($_pbSheetId) {
    $_richFile = $_root . '/sheets/' . $_pbSheetId . '/' . strtolower($_name) . '.json';
    $_rich = _gb_load($_richFile);
}

// Helper: get all rows of a given Name
function _gb_rows(array $rich, string $name): array {
    return array_values(array_filter($rich, fn($r) => strcasecmp(trim($r['Name'] ?? ''), $name) === 0));
}

// Helper: first Value for a given Name
function _gb_val(array $rich, string $name): string {
    foreach ($rich as $r) { if (strcasecmp(trim($r['Name'] ?? ''), $name) === 0) return trim($r['Value'] ?? ''); }
    return '';
}

$_components  = _gb_rows($_rich, 'Component');
$_reviews     = _gb_rows($_rich, 'Review');
$_faqs        = _gb_rows($_rich, 'FAQ');
$_buyUrls     = _gb_rows($_rich, 'BuyUrl');
$_richVideos  = _gb_rows($_rich, 'Video');
$_mediaImgs   = array_merge(_gb_rows($_rich, 'Media'), _gb_rows($_rich, 'ProductImage'));
$_richPrice   = _gb_val($_rich, 'Price');
$_richRules   = _gb_val($_rich, 'Rules PDF Url') ?: $_rulesUrl;

// ── Gallery: cover + image + rich media images ─────────────────────────────
$_gallery = [];
foreach (array_filter([$_coverUrl, $_imageUrl]) as $img) {
    if (!in_array($img, $_gallery)) $_gallery[] = $img;
}
foreach ($_mediaImgs as $r) {
    $u = trim($r['Value'] ?? '');
    if ($u && !in_array($u, $_gallery)) $_gallery[] = $u;
}

$_heroImg = $_gallery[0] ?? '';

// Primary video embed: first rich Video with embeddable URL, else games.json Video URL
$_videoEmbed = '';
foreach ($_richVideos as $rv) {
    $e = _gb_embed(trim($rv['Value 1'] ?? $rv['Value'] ?? ''));
    if ($e) { $_videoEmbed = $e; break; }
}
if (!$_videoEmbed) $_videoEmbed = _gb_embed($_videoUrl);

// Additional video list (all rich videos that are embeddable)
$_videoList = [];
foreach ($_richVideos as $rv) {
    $e = _gb_embed(trim($rv['Value 1'] ?? $rv['Value'] ?? ''));
    if ($e) $_videoList[] = ['label' => trim($rv['Value'] ?? ''), 'embed' => $e];
}

// Other games for "More Games" section
$_otherGames = [];
foreach ($_games as $g) {
    if (_gb_slug($g['Name'] ?? '') !== _gb_slug($_name)) {
        $_otherGames[] = $g;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= h($_name) ?><?= $_summary ? ' — ' . h($_summary) : '' ?></title>
<meta name="description" content="<?= h(substr($_description ?: $_summary, 0, 160)) ?>" />
<?php if ($_heroImg): ?><meta property="og:image" content="<?= h($_heroImg) ?>" /><?php endif; ?>
<style>
@font-face { font-family:'DINBlack';   src:url('<?= $_appRoot ?>fonts/DINBlack.woff2')  format('woff2'); }
@font-face { font-family:'DINRegular'; src:url('<?= $_appRoot ?>fonts/DINMedium.woff2') format('woff2'); }
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --ink:#1a1a2e; --ink-lt:#4a4a6a; --muted:#888; --border:#e2e2ec;
  --surface:#fff; --bg:#f6f5f2; --accent:#3b3baa; --accent-dk:#2d2d88; --accent-lt:#eeeeff;
  --nav-bg:#1a1a2e; --hero-gradient:linear-gradient(to top, rgba(10,10,28,.95) 0%, rgba(10,10,28,.55) 55%, rgba(10,10,28,.05) 100%);
}
<?php if ($_template === 'opensea'): ?>
/* ── OpenSea template overrides ── */
:root {
  --ink:#0d1b2a; --ink-lt:#2c4a6e; --muted:#6b8fae; --border:#c8dcea;
  --surface:#fff; --bg:#f0f6fb; --accent:#0097a7; --accent-dk:#00838f; --accent-lt:#e0f7fa;
  --nav-bg:#0d1b2a; --hero-gradient:linear-gradient(to top, rgba(13,27,42,.96) 0%, rgba(13,27,42,.5) 50%, rgba(13,27,42,.04) 100%);
}
.gb-nav-logo { color:#00e5ff; }
.gb-hero-title { color:#fff; }
.gb-status-badge { border-color:rgba(0,229,255,.4); color:rgba(255,255,255,.9); }
.btn-nav.filled { background:#00e5ff; color:#0d1b2a; border-color:#00e5ff; }
.btn-nav.filled:hover { background:#b2fff9; }
.gb-section-label { color:#0097a7; border-color:#c8dcea; }
.gb-comp-qty { color:#0097a7; }
.gb-review-quote::before { color:#0097a7; }
.gb-faq-item { border-color:#c8dcea; }
.gb-vtab.active, .gb-vtab:hover { background:#0097a7; border-color:#0097a7; }
.btn-buy { background:#0097a7; }
.btn-buy:hover { background:#00838f; }
.btn-link { border-color:#0097a7; color:#0097a7; }
.btn-link:hover { background:#0097a7; color:#fff; }
.gb-more-card:hover { box-shadow:0 4px 18px rgba(0,151,167,.18); }
.gb-more-name { color:#0097a7; }
<?php endif; ?>
body { background:var(--bg); font-family:'DINRegular',Arial,sans-serif; color:var(--ink); min-height:100vh; }
a { color:inherit; text-decoration:none; }

/* ── Nav ── */
.gb-nav {
  background:var(--nav-bg); color:#fff;
  display:flex; align-items:center; justify-content:space-between;
  padding:.7rem 1.5rem; position:sticky; top:0; z-index:100;
  gap:1rem;
}
.gb-nav-left { display:flex; align-items:center; gap:.65rem; min-width:0; }
.gb-nav-logo { font-family:'DINBlack',sans-serif; font-size:1rem; letter-spacing:.06em; color:#7c9eff; flex-shrink:0; }
.gb-nav-game { font-size:.78rem; opacity:.6; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.gb-nav-links { display:flex; gap:.4rem; flex-shrink:0; }
.btn-nav {
  font-family:'DINBlack',sans-serif; font-size:.68rem; text-transform:uppercase;
  letter-spacing:.07em; padding:.33rem .75rem; border-radius:5px;
  border:1.5px solid rgba(255,255,255,.3); color:#fff; background:transparent;
  transition:background .14s; white-space:nowrap; cursor:pointer;
}
.btn-nav:hover { background:rgba(255,255,255,.14); border-color:#fff; }
.btn-nav.filled { background:#fff; color:var(--ink); border-color:#fff; }
.btn-nav.filled:hover { background:#e8e8ff; }

/* ── Hero ── */
.gb-hero {
  position:relative; min-height:70vh; display:flex; align-items:flex-end;
  overflow:hidden; background:var(--ink);
}
.gb-hero-bg { position:absolute; inset:0; background-size:cover; background-position:center; }
.gb-hero-overlay {
  position:absolute; inset:0;
  background:var(--hero-gradient);
}
.gb-hero-content { position:relative; z-index:2; max-width:860px; padding:2.5rem 2rem 3.5rem; }
.gb-status-badge {
  display:inline-block; font-family:'DINBlack',sans-serif; font-size:.66rem;
  text-transform:uppercase; letter-spacing:.1em; padding:.22rem .65rem;
  border-radius:999px; border:1.5px solid rgba(255,255,255,.38); color:rgba(255,255,255,.8);
  margin-bottom:.8rem;
}
.gb-hero-title {
  font-family:'DINBlack',sans-serif; font-size:clamp(2rem,7vw,3.8rem);
  line-height:1.04; letter-spacing:.02em; color:#fff;
  text-shadow:0 2px 20px rgba(0,0,0,.55); margin-bottom:.65rem;
}
.gb-hero-summary {
  font-size:clamp(.92rem,2.5vw,1.12rem); color:rgba(255,255,255,.82);
  max-width:560px; line-height:1.55; margin-bottom:1.3rem;
}
.gb-stats {
  display:flex; flex-wrap:wrap; gap:.45rem 1.3rem; margin-bottom:1.5rem;
}
.gb-stat { font-size:.82rem; color:rgba(255,255,255,.75); }
.gb-stat strong { font-family:'DINBlack',sans-serif; color:#fff; margin-right:.25rem; }
.gb-hero-ctas { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; }
.gb-price-tag {
  font-family:'DINBlack',sans-serif; font-size:1.5rem; color:#fff;
  margin-right:.25rem; letter-spacing:-.01em;
}

/* ── Main ── */
.gb-main { max-width:940px; margin:0 auto; padding:3rem 1.25rem 5rem; }
.gb-section { margin-bottom:3.2rem; }
.gb-section-label {
  font-family:'DINBlack',sans-serif; font-size:.7rem; text-transform:uppercase;
  letter-spacing:.1em; color:var(--muted); margin-bottom:.85rem;
  padding-bottom:.45rem; border-bottom:1px solid var(--border);
}
.gb-description { font-size:.98rem; line-height:1.75; color:var(--ink-lt); white-space:pre-wrap; }

/* ── Gallery ── */
.gb-gallery { display:flex; gap:.55rem; overflow-x:auto; scroll-snap-type:x mandatory; padding-bottom:.4rem; }
.gb-gallery::-webkit-scrollbar { height:4px; }
.gb-gallery::-webkit-scrollbar-thumb { background:var(--border); border-radius:2px; }
.gb-gallery img {
  height:210px; border-radius:8px; object-fit:cover; flex-shrink:0;
  scroll-snap-align:start; cursor:pointer; transition:transform .18s;
  box-shadow:0 2px 10px rgba(0,0,0,.1);
}
.gb-gallery img:hover { transform:scale(1.012); }

/* ── Video ── */
.gb-video-wrap { position:relative; padding-top:56.25%; border-radius:10px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.13); }
.gb-video-wrap iframe { position:absolute; inset:0; width:100%; height:100%; border:none; }
.gb-video-tabs { display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.7rem; }
.gb-vtab {
  font-family:'DINBlack',sans-serif; font-size:.68rem; text-transform:uppercase;
  letter-spacing:.06em; padding:.28rem .65rem; border-radius:4px; cursor:pointer;
  border:1px solid var(--border); background:var(--surface); color:var(--ink-lt);
  transition:background .12s;
}
.gb-vtab:hover, .gb-vtab.active { background:var(--accent); color:#fff; border-color:var(--accent); }

/* ── Components ── */
.gb-components { display:grid; grid-template-columns:repeat(auto-fill, minmax(195px,1fr)); gap:.5rem; }
.gb-comp-item {
  display:flex; align-items:baseline; gap:.55rem;
  background:var(--surface); border:1px solid var(--border);
  border-radius:7px; padding:.52rem .7rem; font-size:.86rem;
}
.gb-comp-qty { font-family:'DINBlack',sans-serif; font-size:1rem; color:var(--accent); flex-shrink:0; min-width:1.5rem; }
.gb-comp-name { color:var(--ink-lt); }

/* ── Reviews ── */
.gb-reviews { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px,1fr)); gap:1rem; }
.gb-review {
  background:var(--surface); border:1px solid var(--border); border-radius:10px;
  padding:1rem 1.1rem; position:relative;
}
.gb-review-quote { font-size:.9rem; line-height:1.6; color:var(--ink-lt); font-style:italic; margin-bottom:.6rem; }
.gb-review-quote::before { content:'\201C'; font-size:1.8rem; color:var(--accent); line-height:0; vertical-align:-.45em; margin-right:.1rem; }
.gb-review-author { font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); }

/* ── FAQs ── */
.gb-faq-item { border-bottom:1px solid var(--border); padding:.85rem 0; }
.gb-faq-item:last-child { border-bottom:none; }
.gb-faq-q { font-family:'DINBlack',sans-serif; font-size:.9rem; color:var(--ink); cursor:pointer; display:flex; justify-content:space-between; align-items:center; gap:.5rem; }
.gb-faq-q::after { content:'＋'; font-size:.9rem; color:var(--muted); flex-shrink:0; }
.gb-faq-item.open .gb-faq-q::after { content:'－'; }
.gb-faq-a { display:none; font-size:.87rem; line-height:1.65; color:var(--ink-lt); margin-top:.6rem; }
.gb-faq-item.open .gb-faq-a { display:block; }
.gb-faq-a a { color:var(--accent); text-decoration:underline; }
.gb-faq-img { max-width:100%; border-radius:6px; margin-top:.5rem; }

/* ── Buy links ── */
.gb-buy-links { display:flex; flex-wrap:wrap; gap:.6rem; }
.btn-buy {
  font-family:'DINBlack',sans-serif; font-size:.78rem; text-transform:uppercase;
  letter-spacing:.07em; padding:.55rem 1.2rem; border-radius:7px;
  background:var(--accent); color:#fff; border:none; cursor:pointer;
  transition:background .13s;
}
.btn-buy:hover { background:var(--accent-dk); }

/* ── Resource links ── */
.gb-links { display:flex; flex-wrap:wrap; gap:.6rem; }
.btn-link {
  font-family:'DINBlack',sans-serif; font-size:.74rem; text-transform:uppercase;
  letter-spacing:.07em; padding:.48rem 1rem; border-radius:6px;
  border:2px solid var(--accent); color:var(--accent); background:var(--surface);
  transition:background .12s, color .12s; cursor:pointer;
}
.btn-link:hover { background:var(--accent); color:#fff; }

/* ── More games ── */
.gb-more-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(160px,1fr)); gap:.9rem; }
.gb-more-card {
  background:var(--surface); border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.07);
  overflow:hidden; transition:box-shadow .15s, transform .15s; cursor:pointer;
}
.gb-more-card:hover { box-shadow:0 4px 18px rgba(59,59,170,.15); transform:translateY(-2px); }
.gb-more-card img { width:100%; aspect-ratio:1; object-fit:cover; display:block; }
.gb-more-card-ph { width:100%; aspect-ratio:1; background:linear-gradient(135deg,#dde 0%,#c8c8e8 100%); display:flex; align-items:center; justify-content:center; font-size:2.2rem; opacity:.45; }
.gb-more-body { padding:.55rem .7rem .7rem; }
.gb-more-name { font-family:'DINBlack',sans-serif; font-size:.8rem; color:var(--accent); margin-bottom:.1rem; }
.gb-more-meta { font-size:.72rem; color:var(--muted); }

/* ── Footer ── */
.gb-footer { background:var(--ink); color:rgba(255,255,255,.55); text-align:center; padding:1.8rem 1.5rem; font-size:.76rem; }
.gb-footer strong { color:#fff; font-family:'DINBlack',sans-serif; }

/* ── Lightbox ── */
.gb-lightbox { display:none; position:fixed; inset:0; z-index:500; background:rgba(0,0,0,.92); align-items:center; justify-content:center; cursor:zoom-out; }
.gb-lightbox.open { display:flex; }
.gb-lightbox img { max-width:92vw; max-height:92vh; border-radius:8px; object-fit:contain; }

@media(max-width:640px) {
  .gb-hero-content { padding:1.8rem 1rem 2.5rem; }
  .gb-nav-game { display:none; }
  .gb-main { padding:2rem .9rem 4rem; }
  .gb-components { grid-template-columns:1fr 1fr; }
  .gb-more-grid { grid-template-columns:repeat(2,1fr); }
  .gb-reviews { grid-template-columns:1fr; }
}
</style>
</head>
<body>

<!-- ── Nav ── -->
<nav class="gb-nav">
  <div class="gb-nav-left">
    <a class="gb-nav-logo" href="<?= h($_appRoot . $_sheetId . '/gameboard/' . _gb_slug($_name)) ?>">GameBoard</a>
    <span class="gb-nav-game"><?= h($_name) ?></span>
  </div>
  <div class="gb-nav-links">
    <?php if ($_richRules): ?><a class="btn-nav" href="<?= h($_richRules) ?>" target="_blank">Rules</a><?php endif; ?>
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
    <?php if ($_status): ?><div class="gb-status-badge"><?= h(ucfirst($_status)) ?></div><?php endif; ?>
    <h1 class="gb-hero-title"><?= h($_name) ?></h1>
    <?php if ($_summary): ?><p class="gb-hero-summary"><?= h($_summary) ?></p><?php endif; ?>
    <div class="gb-stats">
      <?php if ($_count):     ?><div class="gb-stat"><strong>👥</strong><?= h($_count) ?> players</div><?php endif; ?>
      <?php if ($_duration):  ?><div class="gb-stat"><strong>⏱</strong><?= h($_duration) ?></div><?php endif; ?>
      <?php if ($_designers): ?><div class="gb-stat"><strong>✏️</strong><?= h(implode(', ', $_designers)) ?></div><?php endif; ?>
      <?php if ($_availability): ?><div class="gb-stat"><strong>📦</strong><?= h($_availability) ?></div><?php endif; ?>
    </div>
    <div class="gb-hero-ctas">
      <?php if ($_richPrice): ?><span class="gb-price-tag"><?= h($_richPrice) ?></span><?php endif; ?>
      <?php foreach ($_buyUrls as $b):
        $bUrl   = trim($b['Value']   ?? '');
        $bLabel = trim($b['Value 1'] ?? '') ?: 'Buy Now';
        if (!$bUrl) continue;
      ?><a class="btn-nav filled" href="<?= h($bUrl) ?>" target="_blank"><?= h($bLabel) ?></a><?php endforeach; ?>
      <?php if ($_richRules): ?><a class="btn-nav" href="<?= h($_richRules) ?>" target="_blank">📖 Rules</a><?php endif; ?>
      <?php if ($_playUrl):   ?><a class="btn-nav" href="<?= h($_playUrl) ?>" target="_blank">🎮 Play Online</a><?php endif; ?>
      <?php if ($_printUrl):  ?><a class="btn-nav" href="<?= h($_printUrl) ?>" target="_blank">🖨 Print &amp; Play</a><?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Main ── -->
<div class="gb-main">

  <!-- Description -->
  <?php if ($_description): ?>
  <div class="gb-section">
    <div class="gb-section-label">About the Game</div>
    <p class="gb-description"><?= h($_description) ?></p>
  </div>
  <?php endif; ?>

  <!-- Gallery -->
  <?php if (count($_gallery) > 1): ?>
  <div class="gb-section">
    <div class="gb-section-label">Gallery</div>
    <div class="gb-gallery">
      <?php foreach ($_gallery as $img): ?>
      <img src="<?= h($img) ?>" alt="<?= h($_name) ?>" loading="lazy" onclick="openLightbox('<?= h($img) ?>')" />
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Video(s) -->
  <?php if ($_videoEmbed): ?>
  <div class="gb-section">
    <div class="gb-section-label">Watch</div>
    <div class="gb-video-wrap" id="videoWrap">
      <iframe id="videoFrame" src="<?= h($_videoEmbed) ?>" allow="autoplay; encrypted-media" allowfullscreen loading="lazy"></iframe>
    </div>
    <?php if (count($_videoList) > 1): ?>
    <div class="gb-video-tabs">
      <?php foreach ($_videoList as $vi => $v): ?>
      <button class="gb-vtab<?= $vi === 0 ? ' active' : '' ?>"
              onclick="switchVideo(this, '<?= h($v['embed']) ?>')"><?= h($v['label'] ?: ('Video ' . ($vi + 1))) ?></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Components -->
  <?php if (!empty($_components)): ?>
  <div class="gb-section">
    <div class="gb-section-label">What's in the Box</div>
    <div class="gb-components">
      <?php foreach ($_components as $c):
        $cVal = trim($c['Value'] ?? '');
        if (!$cVal) continue;
        // Try to parse "42 Flower Tiles" → qty + name, or just show as-is
        $cQty  = '';
        $cName = $cVal;
        if (preg_match('/^(\d+)\s+(.+)$/', $cVal, $pm)) { $cQty = $pm[1]; $cName = $pm[2]; }
      ?>
      <div class="gb-comp-item">
        <?php if ($cQty): ?><span class="gb-comp-qty"><?= h($cQty) ?></span><?php endif; ?>
        <span class="gb-comp-name"><?= h($cName) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Reviews / Quotes -->
  <?php if (!empty($_reviews)): ?>
  <div class="gb-section">
    <div class="gb-section-label">What People Are Saying</div>
    <div class="gb-reviews">
      <?php foreach ($_reviews as $rv):
        $rQuote  = trim($rv['Value']   ?? '');
        $rAuthor = trim($rv['Value 1'] ?? '');
        if (!$rQuote) continue;
      ?>
      <div class="gb-review">
        <div class="gb-review-quote"><?= h($rQuote) ?></div>
        <?php if ($rAuthor): ?><div class="gb-review-author">— <?= h($rAuthor) ?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- FAQs -->
  <?php if (!empty($_faqs)): ?>
  <div class="gb-section">
    <div class="gb-section-label">FAQ</div>
    <?php foreach ($_faqs as $fi => $fq):
      $fqQ = trim($fq['Value']   ?? '');
      $fqA = trim($fq['Value 1'] ?? '');
      $fqI = trim($fq['Value 2'] ?? '');
      if (!$fqQ || !$fqA) continue;
    ?>
    <div class="gb-faq-item" id="faq<?= $fi ?>">
      <div class="gb-faq-q" onclick="toggleFaq('faq<?= $fi ?>')"><?= h($fqQ) ?></div>
      <div class="gb-faq-a"><?= nl2br(h($fqA)) ?>
        <?php if ($fqI && preg_match('/^https?:\/\//', $fqI)): ?>
        <br><img class="gb-faq-img" src="<?= h($fqI) ?>" loading="lazy" alt="" />
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Resources -->
  <?php
  $_hasLinks = $_richRules || $_sellsheet || $_playUrl || $_printUrl || $_pageUrl;
  if ($_hasLinks): ?>
  <div class="gb-section">
    <div class="gb-section-label">Resources</div>
    <div class="gb-links">
      <?php if ($_richRules): ?><a class="btn-link" href="<?= h($_richRules) ?>" target="_blank">📖 Rules PDF</a><?php endif; ?>
      <?php if ($_sellsheet): ?><a class="btn-link" href="<?= h($_sellsheet) ?>" target="_blank">📄 Sellsheet</a><?php endif; ?>
      <?php if ($_playUrl):   ?><a class="btn-link" href="<?= h($_playUrl) ?>" target="_blank">🎮 Play Online</a><?php endif; ?>
      <?php if ($_printUrl):  ?><a class="btn-link" href="<?= h($_printUrl) ?>" target="_blank">🖨 Print &amp; Play</a><?php endif; ?>
      <?php if ($_pageUrl):   ?><a class="btn-link" href="<?= h($_pageUrl) ?>" target="_blank">🔗 Product Page</a><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- More Games -->
  <?php if (!empty($_otherGames)): ?>
  <div class="gb-section">
    <div class="gb-section-label">More Games</div>
    <div class="gb-more-grid">
      <?php foreach (array_slice($_otherGames, 0, 8) as $og):
        $ogName = trim($og['Name'] ?? '');
        $ogImg  = _gb_img(trim($og['Cover URL'] ?? $og['Image URL'] ?? ''));
        $ogMeta = array_filter([trim($og['Count'] ?? '') ? trim($og['Count']).' players' : '', trim($og['Duration'] ?? '')]);
      ?>
      <a class="gb-more-card" href="<?= h($_appRoot . $_sheetId . '/gameboard/' . _gb_slug($ogName)) ?>">
        <?php if ($ogImg): ?>
        <img src="<?= h($ogImg) ?>" alt="<?= h($ogName) ?>" loading="lazy" />
        <?php else: ?>
        <div class="gb-more-card-ph">🎲</div>
        <?php endif; ?>
        <div class="gb-more-body">
          <div class="gb-more-name"><?= h($ogName) ?></div>
          <?php if ($ogMeta): ?><div class="gb-more-meta"><?= h(implode(' · ', $ogMeta)) ?></div><?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /gb-main -->

<footer class="gb-footer">
  <strong><?= h($_name) ?></strong>
  <?php if ($_designers): ?>&nbsp;·&nbsp; Designed by <?= h(implode(' & ', $_designers)) ?><?php endif; ?>
  <?php if ($_datePub): ?>&nbsp;·&nbsp; Published <?= h($_datePub) ?><?php elseif ($_dateSigned): ?>&nbsp;·&nbsp; Signed <?= h($_dateSigned) ?><?php endif; ?>
</footer>

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
function switchVideo(btn, embed) {
  document.getElementById('videoFrame').src = embed;
  document.querySelectorAll('.gb-vtab').forEach(function(t){ t.classList.remove('active'); });
  btn.classList.add('active');
}
function toggleFaq(id) {
  document.getElementById(id).classList.toggle('open');
}
document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeLightbox(); });
</script>
</body>
</html>
