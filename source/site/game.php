<?php
// ─── Data ─────────────────────────────────────────────────────────────────────
// router.php includes PHP files inside serveFile() — a function scope.
// Variables used by our helper functions via `global` must be declared
// global here so they land in PHP's global scope, not the function's local scope.
global $tab, $tabMulti, $_cacheDir;

$dir = dirname(__DIR__);    // sheets/{id}/

// Site branding (site.json: [{Name, Value}])
$site     = json_decode(@file_get_contents($dir . '/site.json')     ?: '[]', true) ?: [];
$settings = json_decode(@file_get_contents($dir . '/settings.json') ?: '[]', true) ?: [];
$sd = []; $sdMulti = [];
foreach ($site as $row) {
    $n = trim($row['Name'] ?? ''); $v = trim($row['Value'] ?? '');
    if ($n !== '') { $sd[$n] = $v; $sdMulti[$n][] = $v; }
}
$sett = [];
foreach ($settings as $row) {
    $n = trim($row['Name'] ?? '');
    if ($n !== '') $sett[$n] = trim($row['Value'] ?? '');
}
$splashUrls = array_values(array_filter($sdMulti['SplashUrl'] ?? ($sdMulti['SplashURL'] ?? [])));
$tagline    = $sd['Tagline'] ?? '';

// Game name from URL
$gameName = trim($_GET['name'] ?? '');
if ($gameName === '') { header('Location: index.php'); exit; }

// Find game in games.json
$games = json_decode(@file_get_contents($dir . '/games.json') ?: '[]', true) ?: [];
$game  = null;
foreach ($games as $g) {
    if (strcasecmp(trim($g['Name'] ?? ''), $gameName) === 0) { $game = $g; break; }
}
if (!$game) { header('Location: index.php'); exit; }

// Per-game tab JSON (Name/Value/Alt Value rows)
$tabFile  = $dir . '/' . strtolower($gameName) . '.json';
$tab = []; $tabMulti = [];
if (file_exists($tabFile)) {
    $raw = json_decode(@file_get_contents($tabFile) ?: '[]', true) ?: [];
    foreach ($raw as $row) {
        $n = trim($row['Name'] ?? '');
        if ($n === '') continue;
        $tab[$n]        = $row;
        $tabMulti[$n][] = $row;
    }
}
function tabVal(string $k): string  { global $tab; return trim($tab[$k]['Value']   ?? ''); }
function tabAlt(string $k): string  { global $tab; return trim($tab[$k]['Value 1'] ?? ''); }
function tabRows(string $k): array  { global $tabMulti; return $tabMulti[$k] ?? []; }

// ─── Shared helpers (color, cache, esc, statusInfo) ──────────────────────────
require_once __DIR__ . '/functions.php';

$accent  = validHex($sd['Color1'] ?? ($sd['Color 1'] ?? '')) ?: '#c9913d';
$bgRaw   = validHex($sd['Color2'] ?? ($sd['Color 2'] ?? '')) ?: '#1a1a1a';
$bg      = luma($bgRaw) > 160 ? '#1a1a1a' : $bgRaw;
$accentH = lighten($accent, .14);
$accentR = ($t=hexRgb($accent)) ? "{$t[0]},{$t[1]},{$t[2]}" : '201,145,61';
$hdrBg   = hexRgba($accent, .97);

// Light / dark adaptive palette
$isLight  = luma($bg) > 160;
$bgCard   = $isLight ? darken($bg, .05) : lighten($bg, .06);
$bgCard2  = $isLight ? darken($bg, .10) : lighten($bg, .11);
$textClr  = $isLight ? '#111111' : '#ffffff';
$textDim  = $isLight ? 'rgba(0,0,0,.62)' : 'rgba(255,255,255,.58)';
$textMute = $isLight ? 'rgba(0,0,0,.38)' : 'rgba(255,255,255,.32)';
$borderClr= $isLight ? 'rgba(0,0,0,.1)'  : 'rgba(255,255,255,.09)';
$accentOnBg = (luma($accent) > 160 && $isLight) ? darken($accent,.2) : $accent;

// ─── Media cache ───────────────────────────────────────────────────────────────
$_cacheDir = $dir . '/cache';
function youtubeId(string $u): string {
    return preg_match('/(?:v=|\/embed\/|youtu\.be\/|shorts\/)([A-Za-z0-9_-]{11})/',$u,$m) ? $m[1] : '';
}
function isVideoUrl(string $u): bool {
    return (bool)preg_match('/\.(mp4|webm|ogg|mov)(\?|$)/i', $u);
}

// ─── Game fields ───────────────────────────────────────────────────────────────
$name      = $game['Name']          ?? '';
$status    = $game['Status']        ?? '';
$summary   = trim($game['Summary']  ?? ($game['Description'] ?? ''));
$count     = $game['Count']         ?? '';
$duration  = $game['Duration']      ?? '';
$avail     = $game['Availability']  ?? '';
$coverG    = $game['Cover URL']     ?? ($game['Image URL'] ?? '');
$pageUrl   = $game['Page URL']      ?? '';
$sellUrl   = $game['Sellsheet URL'] ?? '';
$videoUrl  = $game['Video URL']     ?? '';
$playUrl   = $game['Play URL']      ?? '';
$rulesUrl  = $game['Rules URL']     ?? '';
$datePubl  = $game['Date Published'] ?? '';
$dateSigned= $game['Date Signed']   ?? '';
$designers = array_values(array_filter([
    $game['Designer1']??'', $game['Designer2']??'',
    $game['Designer3']??'', $game['Designer4']??'',
]));

// Tab extras
$bggId      = tabVal('BggGameId');
$price      = tabVal('Price');
$stock      = tabVal('Stock');
$weight     = tabVal('Weight');
$rulesPdf   = tabVal('Rules PDF Url') ?: $rulesUrl;
$productImg = tabVal('ProductImage');
$buyUrls    = tabRows('BuyUrl');
$reviews    = tabRows('Review');
$videos     = tabRows('Video');
$faqs       = tabRows('FAQ');

// ─── BGG data ──────────────────────────────────────────────────────────────────
$bgg = [];
if ($bggId) {
    $bggFile = $dir . '/bgg-' . $bggId . '.json';
    if (file_exists($bggFile)) {
        $bgg = json_decode(@file_get_contents($bggFile) ?: '[]', true) ?: [];
    }
}

// Computed BGG values
$bggAvg     = '';
$bggRankNum = '';
$bggMinage  = '';
$bggWeight  = '';
$bggMechanics  = [];
$bggCategories = [];
$bggUrl        = $bggId ? 'https://boardgamegeek.com/boardgame/' . $bggId : '';
if ($bgg) {
    $rawAvg = (float)($bgg['stats']['average']       ?? 0);
    $rawWgt = (float)($bgg['stats']['averageweight'] ?? 0);
    $rawRnk = (string)($bgg['stats']['rank']         ?? '');
    if ($rawAvg > 0) $bggAvg    = number_format($rawAvg, 1);
    if ($rawWgt > 0) $bggWeight = number_format($rawWgt, 1);
    if ($rawRnk !== '' && is_numeric($rawRnk)) $bggRankNum = number_format((int)$rawRnk);
    $bggMinage     = (string)($bgg['minage'] ?? '');
    $bggMechanics  = array_column($bgg['mechanics']  ?? [], 'value');
    $bggCategories = array_column($bgg['categories'] ?? [], 'value');
}

// Fill missing fields from BGG
if (!$count && ($bgg['minplayers'] ?? '') !== '') {
    $mn = $bgg['minplayers']; $mx = $bgg['maxplayers'];
    $count = ($mn === $mx) ? $mn : $mn . '–' . $mx;
}
if (!$duration && ($bgg['minplaytime'] ?? '') !== '') {
    $mn = $bgg['minplaytime']; $mx = $bgg['maxplaytime'];
    $duration = ($mn === $mx) ? $mn . ' min' : $mn . '–' . $mx . ' min';
}
if (!$weight && $bggWeight !== '') $weight = $bggWeight;
if (!$summary && ($bgg['description'] ?? '')) {
    $summary = html_entity_decode((string)$bgg['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
// BGG cover image fallback
if (!$productImg && !$coverG && ($bgg['image'] ?? '')) {
    $coverG = (string)$bgg['image'];
}

// ─── Media gallery ─────────────────────────────────────────────────────────────
// Collect all images from Media rows (skip videos for gallery display)
$mediaRows  = tabRows('Media');
$mediaImgs  = [];
$mediaVidUrl = '';
foreach ($mediaRows as $mr) {
    $mu = trim($mr['Value'] ?? '');
    if ($mu === '') continue;
    if (isVideoUrl($mu)) { if (!$mediaVidUrl) $mediaVidUrl = $mu; }
    else                  { $mediaImgs[] = cachedUrl($mu); }
}

// Gallery: ProductImage first, then Cover URL, then Media images
$gallery = [];
if ($productImg)  $gallery[] = cachedUrl($productImg);
$coverCached = $coverG ? cachedUrl($coverG) : '';
if ($coverCached && $coverCached !== ($gallery[0] ?? '')) $gallery[] = $coverCached;
foreach ($mediaImgs as $mi) {
    if (!in_array($mi, $gallery)) $gallery[] = $mi;
}
// Primary display image
$mainImg = $gallery[0] ?? '';

// ─── Stats ─────────────────────────────────────────────────────────────────────
$stats = [];
if ($count)    $stats[] = ['Players',    $count];
if ($duration) $stats[] = ['Play Time',  $duration];
if ($avail)    $stats[] = ['Available',  $avail];
if ($weight)   $stats[] = ['Complexity', $weight . ' / 5'];
if ($bggMinage && $bggMinage !== '0') $stats[] = ['Min Age', $bggMinage . '+'];

// ─── Stock label ───────────────────────────────────────────────────────────────
$stockNum = (int)$stock;
if ($stock !== '') {
    if ($stockNum > 5)     { $stockCls = 'in';  $stockLabel = 'In Stock'; }
    elseif ($stockNum > 0) { $stockCls = 'low'; $stockLabel = 'Only '.$stockNum.' left'; }
    else                   { $stockCls = 'out'; $stockLabel = 'Out of Stock'; }
} else { $stockCls = ''; $stockLabel = ''; }

// ─── Status ────────────────────────────────────────────────────────────────────
[$statusLabel, $statusCls, $statusColor] = statusInfo($status);

$company    = $sd['CompanyName'] ?? '';
$appIconRaw = $sett['AppIconImageUrl'] ?? ($sd['AppIconImageUrl'] ?? '');
$logoUrl = cachedUrl($sd['LogoUrl'] ?? ($sd['LogoURL'] ?? ''));
$address = $sd['Address']   ?? '';
$insta    = $sd['Instagram'] ?? '';
$twitter  = $sd['Twitter']   ?? '';
$xdotcom  = $sd['X']         ?? '';
$facebook = $sd['Facebook']  ?? '';
$copy    = $sd['Copyright'] ?? ($company ? '&copy; ' . date('Y') . ' ' . htmlspecialchars($company, ENT_QUOTES) : '');
$hasTabs = !empty($videos) || !empty($reviews) || !empty($faqs);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($name) ?><?= $company ? ' — '.esc($company) : '' ?></title>
  <meta name="description" content="<?= esc($summary ?: $name) ?>">
  <?php if ($appIconRaw): $appIcon = cachedUrl($appIconRaw); ?>
  <link rel="icon" href="<?= esc($appIcon) ?>">
  <link rel="apple-touch-icon" href="<?= esc($appIcon) ?>">
  <?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg:        <?= $bg ?>;
      --bg-card:   <?= $bgCard ?>;
      --bg-card2:  <?= $bgCard2 ?>;
      --accent:    <?= $accentOnBg ?>;
      --accent-h:  <?= $accentH ?>;
      --accent-r:  <?= $accentR ?>;
      --hdr-bg:    <?= $hdrBg ?>;
      --status:    <?= $statusColor ?>;
      --text:      <?= $textClr ?>;
      --text-dim:  <?= $textDim ?>;
      --text-muted:<?= $textMute ?>;
      --border:    <?= $borderClr ?>;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
      background: var(--bg); color: var(--text);
      font-family: 'DM Sans', system-ui, sans-serif;
      font-size: 15px; line-height: 1.65; min-height: 100vh;
    }

    /* ── Header ─────────────────────────────────────────────── */
    .hdr {
      position: sticky; top: 0; z-index: 200;
      display: flex; align-items: center; justify-content: space-between;
      padding: .85rem 2.5rem;
      background: var(--hdr-bg); backdrop-filter: blur(14px);
      border-bottom: 1px solid rgba(0,0,0,.18);
    }
    .hdr-back {
      display: inline-flex; align-items: center; gap: .4rem;
      color: rgba(255,255,255,.85); text-decoration: none;
      font-size: .72rem; font-weight: 500; letter-spacing: .12em; text-transform: uppercase;
      transition: color .2s; flex-shrink: 0;
    }
    .hdr-back:hover { color: #fff; }
    .hdr-tagline {
      font-size: .68rem; color: rgba(255,255,255,.7); letter-spacing: .1em;
      font-style: italic; flex: 1; text-align: center; padding: 0 1.5rem;
    }
    .hdr-nav { display: flex; align-items: center; gap: 2rem; flex-shrink: 0; }
    .hdr-nav a {
      color: rgba(255,255,255,.85); text-decoration: none;
      font-size: .75rem; font-weight: 500; letter-spacing: .12em; text-transform: uppercase;
      transition: color .2s; text-shadow: 0 1px 4px rgba(0,0,0,.4);
    }
    .hdr-nav a:hover { color: #fff; }
    .hdr-cta {
      border: 1px solid rgba(255,255,255,.55); color: #fff !important;
      padding: .32rem .9rem; border-radius: 2px;
      font-size: .68rem !important; letter-spacing: .14em !important;
    }
    .hdr-cta:hover { background: rgba(255,255,255,.15) !important; border-color: #fff; }

    /* ── Page ────────────────────────────────────────────────── */
    .page-wrap { max-width: 1060px; margin: 0 auto; padding: 2.5rem 2rem 5rem; }

    /* ── Title row ───────────────────────────────────────────── */
    .title-row { display:flex; align-items:center; gap:.85rem; flex-wrap:wrap; margin-bottom:.65rem; }
    .game-title {
      font-family:'Barlow Condensed',sans-serif;
      font-size: clamp(2.4rem,5vw,3.8rem); font-weight:800; line-height:1;
      letter-spacing:.03em; text-transform:uppercase; color:var(--text);
    }
    .status-badge {
      font-size:.6rem; font-weight:600; letter-spacing:.16em; text-transform:uppercase;
      color:var(--status); border:1px solid var(--status); padding:.22rem .7rem; border-radius:2px;
      background: rgba(<?= $accentR ?>,.06); white-space:nowrap;
    }
    .game-byline { font-size:.82rem; color:var(--accent); font-weight:500; margin-bottom:1.75rem; }

    /* ── Two-column ──────────────────────────────────────────── */
    .main-cols { display:grid; grid-template-columns:320px 1fr; gap:2rem; align-items:start; }

    /* ── Image panel ─────────────────────────────────────────── */
    .img-panel {}
    .main-img-wrap {
      background:var(--bg-card); border:1px solid var(--border);
      border-radius:6px; overflow:hidden; margin-bottom:.6rem; cursor:zoom-in;
      height: 320px;
    }
    .main-img-wrap img, .main-img-wrap video {
      width:100%; height:100%; object-fit:cover; display:block;
    }
    .no-cover {
      width:100%; height:100%;
      display:flex; align-items:center; justify-content:center; font-size:5rem; opacity:.08;
    }
    /* Thumb strip */
    .thumb-strip {
      display:flex; gap:.4rem; overflow-x:auto; padding-bottom:.25rem; margin-bottom:.75rem;
      scrollbar-width:thin; scrollbar-color: rgba(<?= $accentR ?>,.4) transparent;
    }
    .thumb-strip::-webkit-scrollbar { height:3px; }
    .thumb-strip::-webkit-scrollbar-thumb { background: rgba(<?= $accentR ?>,.4); border-radius:2px; }
    .thumb {
      flex:0 0 64px; height:48px; object-fit:cover; border-radius:4px;
      border:2px solid transparent; cursor:pointer; opacity:.65;
      transition:border-color .15s, opacity .15s;
    }
    .thumb:hover, .thumb.active { border-color:var(--accent); opacity:1; }

    /* Buy buttons */
    .buy-list { display:flex; flex-direction:column; gap:.4rem; margin-bottom:.7rem; }
    .btn-buy {
      display:block; width:100%; padding:.8rem 1.25rem; background:var(--accent); color:#fff;
      border-radius:3px; font-size:.8rem; font-weight:600; letter-spacing:.08em;
      text-transform:uppercase; text-decoration:none; text-align:center; transition:background .2s;
    }
    .btn-buy:hover { background:var(--accent-h); }
    /* Action links */
    .action-stack { display:flex; flex-direction:column; gap:.35rem; }
    .btn-action {
      display:flex; align-items:center; justify-content:center; gap:.45rem;
      border:1px solid var(--border); color:var(--text-dim);
      padding:.65rem 1.25rem; border-radius:3px;
      font-size:.72rem; font-weight:500; letter-spacing:.1em; text-transform:uppercase;
      text-decoration:none; transition:border-color .2s, color .2s;
    }
    .btn-action:hover { border-color:var(--accent); color:var(--accent); }

    /* ── Info panel ──────────────────────────────────────────── */
    .info-panel {}

    /* BGG */
    .rating-row { display:flex; align-items:stretch; gap:.75rem; flex-wrap:wrap; margin-bottom:1rem; }
    .bgg-link {
      display:inline-flex; align-items:center; justify-content:center;
      border:1px solid var(--border); color:var(--text-dim);
      padding:.55rem 1.1rem; border-radius:4px; font-size:.78rem; text-decoration:none;
      transition:border-color .2s, color .2s;
    }
    .bgg-link:hover { border-color:var(--accent); color:var(--accent); }

    /* Stats grid */
    .stats-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:.5rem; margin-bottom:1.1rem; }
    .stat-box {
      background:var(--bg-card); border:1px solid var(--border);
      border-radius:4px; padding:.65rem .5rem; text-align:center;
    }
    .stat-val {
      font-family:'Barlow Condensed',sans-serif; font-size:1.1rem; font-weight:700; color:var(--text);
      display:block; line-height:1;
    }
    .stat-key {
      font-size:.62rem; text-transform:uppercase; letter-spacing:.1em; color:var(--text-muted);
      display:block; margin-top:.25rem;
    }

    /* Price / stock */
    .price-row { display:flex; align-items:center; gap:.85rem; margin-bottom:1.1rem; }
    .price-tag { font-family:'Barlow Condensed',sans-serif; font-size:1.7rem; font-weight:700; color:var(--text); }
    .stock-tag { font-size:.7rem; font-weight:600; letter-spacing:.09em; text-transform:uppercase; padding:.22rem .65rem; border-radius:3px; }
    .stock-in  { background:rgba(74,222,128,.1);  color:#4ade80; border:1px solid rgba(74,222,128,.3); }
    .stock-low { background:rgba(251,191,36,.1);  color:#d97706; border:1px solid rgba(251,191,36,.3); }
    .stock-out { background:rgba(239,68,68,.1);   color:#ef4444; border:1px solid rgba(239,68,68,.3); }

    /* Description */
    .desc-text {
      font-size:.95rem; line-height:1.8; color:var(--text-dim);
      margin-bottom:1.1rem; border-left:2px solid var(--accent); padding-left:.85rem;
    }
    .desc-text a {
      color: var(--accent); text-decoration: underline;
      text-underline-offset: 2px; text-decoration-thickness: 1px;
    }
    .desc-text a:hover { color: var(--accentH); }

    /* Meta rows */
    .meta-list { margin-bottom:1.25rem; }
    .meta-row {
      display:flex; gap:.5rem; font-size:.84rem; padding:.4rem 0;
      border-bottom:1px solid var(--border); align-items:baseline;
    }
    .meta-row:last-child { border-bottom:none; }
    .meta-key { font-size:.68rem; font-weight:600; text-transform:uppercase; letter-spacing:.1em; color:var(--text-muted); min-width:95px; }
    .meta-val { color:var(--text-dim); flex:1; }

    /* CTAs */
    .cta-row { display:flex; gap:.6rem; flex-wrap:wrap; }
    .btn-cta {
      display:inline-flex; align-items:center; gap:.4rem;
      background:var(--bg-card2); color:var(--text-dim); border:1px solid var(--border);
      padding:.55rem 1.1rem; border-radius:3px;
      font-size:.72rem; font-weight:500; letter-spacing:.1em; text-transform:uppercase;
      text-decoration:none; transition:border-color .2s, color .2s;
    }
    .btn-cta:hover { border-color:var(--accent); color:var(--accent); }

    /* ── Tabs ────────────────────────────────────────────────── */
    .tabs-section { margin-top:2.5rem; }
    .tab-nav { display:flex; border-bottom:1px solid var(--border); margin-bottom:1.5rem; }
    .tab-btn {
      font-size:.73rem; letter-spacing:.12em; text-transform:uppercase; font-weight:500;
      padding:.7rem 1.25rem; background:none; border:none; cursor:pointer; color:var(--text-muted);
      border-bottom:2px solid transparent; margin-bottom:-1px; transition:color .2s;
    }
    .tab-btn:hover  { color:var(--text-dim); }
    .tab-btn.active { color:var(--text); border-bottom-color:var(--accent); }
    .tab-pane { display:none; }
    .tab-pane.active { display:block; }

    /* ── Reviews ─────────────────────────────────────────────── */
    .review-list { display:flex; flex-direction:column; gap:.85rem; }
    .review-card {
      border-left:3px solid var(--accent); background:var(--bg-card);
      border-radius:0 4px 4px 0; padding:1rem 1.25rem;
    }
    .review-quote { font-size:.92rem; line-height:1.75; color:var(--text-dim); font-style:italic; margin-bottom:.45rem; }
    .review-quote::before { content:'\201C'; }
    .review-quote::after  { content:'\201D'; }
    .review-byline { font-size:.65rem; font-weight:600; letter-spacing:.12em; text-transform:uppercase; color:var(--text-muted); }

    /* ── Videos ──────────────────────────────────────────────── */
    .video-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:1rem; }
    .video-card { background:var(--bg-card); border-radius:4px; overflow:hidden; }
    .video-embed { position:relative; padding-bottom:56.25%; height:0; overflow:hidden; }
    .video-embed iframe { position:absolute; inset:0; width:100%; height:100%; border:none; }
    .video-caption { padding:.55rem .85rem; border-top:1px solid var(--border); font-size:.68rem; font-weight:500; letter-spacing:.08em; text-transform:uppercase; color:var(--text-muted); }
    .video-link-card {
      display:flex; align-items:center; gap:.75rem; padding:1rem 1.1rem;
      background:var(--bg-card); border-radius:4px; text-decoration:none; color:inherit; transition:background .2s;
    }
    .video-link-card:hover { background:var(--bg-card2); }
    .video-icon { font-size:1.6rem; }
    .video-link-title { font-size:.88rem; font-weight:600; color:var(--text); }
    .video-link-platform { font-size:.7rem; color:var(--text-muted); }

    /* ── FAQs ────────────────────────────────────────────────── */
    .faq-list { display:flex; flex-direction:column; }
    .faq-item { border-bottom:1px solid var(--border); padding:1rem 0; }
    .faq-item:last-child { border-bottom:none; }
    .faq-q {
      display:flex; justify-content:space-between; align-items:flex-start; gap:1rem;
      font-size:.95rem; font-weight:600; color:var(--text); cursor:pointer;
    }
    .faq-chev { transition:transform .25s; flex-shrink:0; color:var(--text-muted); }
    .faq-item.open .faq-chev { transform:rotate(180deg); }
    .faq-a { display:none; padding-top:.65rem; font-size:.9rem; line-height:1.75; color:var(--text-dim); }
    .faq-item.open .faq-a { display:block; }
    .faq-img { display:block; margin-top:.85rem; max-width:100%; border-radius:4px; }

    /* ── Footer ────────────────────────────────────────────────── */
    .site-footer {
      border-top: 1px solid var(--border);
      padding: 2.5rem 2.5rem 1.5rem;
    }
    .footer-inner {
      display: flex; flex-wrap: wrap; gap: 2rem;
      align-items: flex-start; margin-bottom: 2rem;
    }
    .footer-brand { flex: 1; min-width: 200px; }
    .footer-company-name {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: .95rem; font-weight: 700; color: var(--text); margin-bottom: .3rem;
    }
    .footer-address { font-size: .78rem; color: var(--text-muted); line-height: 1.65; }
    .footer-links { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
    .footer-social {
      display: flex; align-items: center; justify-content: center;
      width: 32px; height: 32px; border-radius: 50%;
      background: transparent; border: 1px solid var(--border);
      color: var(--text-muted); text-decoration: none;
      transition: background .2s, color .2s, border-color .2s;
    }
    .footer-social:hover { background: var(--accent); border-color: var(--accent); color: #fff; }
    .footer-social svg { width: 15px; height: 15px; display: block; }
    .footer-bottom { border-top: 1px solid var(--border); padding-top: 1.25rem; }
    .footer-copy { font-size: .7rem; color: var(--text-muted); }

    /* ── Contact form ──────────────────────────────────────────── */
    .contact-section {
      background: var(--bg-card); border-top: 1px solid var(--border);
      padding: 4rem 2.5rem;
    }
    .contact-inner { max-width: 680px; margin: 0 auto; }
    .contact-heading {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 900;
      color: var(--text); margin-bottom: .5rem;
    }
    .contact-sub { font-size: .9rem; color: var(--text-dim); margin-bottom: 2rem; }
    .cf-row { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .cf-field { flex: 1; min-width: 200px; display: flex; flex-direction: column; gap: .35rem; }
    .cf-label { font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .1em; color: var(--text-muted); }
    .cf-input {
      background: var(--bg); border: 1px solid var(--border); border-radius: 3px;
      color: var(--text); padding: .75rem 1rem; font-size: .9rem; font-family: inherit;
      transition: border-color .2s; outline: none; width: 100%;
    }
    .cf-input:focus { border-color: var(--accent); }
    .cf-submit {
      background: var(--accent); color: #fff; border: none; border-radius: 3px;
      padding: .8rem 2rem; font-size: .78rem; font-weight: 600; letter-spacing: .1em;
      text-transform: uppercase; cursor: pointer; transition: background .2s;
    }
    .cf-submit:hover { background: var(--accent-h); }
    .cf-notice { margin-top: .75rem; font-size: .82rem; min-height: 1.2em; }
    .cf-notice.ok  { color: #4ade80; }
    .cf-notice.err { color: #f87171; }

    /* ── BGG rating widget ───────────────────────────────────── */
    .bgg-row {
      display: flex; align-items: center; gap: 0; flex-wrap: wrap;
      margin-bottom: 1.1rem;
      background: var(--bg-card); border: 1px solid var(--border); border-radius: 4px;
      overflow: hidden;
    }
    .bgg-score-box {
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      text-decoration: none; padding: .6rem .9rem;
      border-right: 1px solid var(--border); min-width: 68px; gap: .15rem;
    }
    .bgg-score-box:hover .bgg-score { color: var(--accent-h); }
    .bgg-score {
      font-family: 'Barlow Condensed', sans-serif; font-size: 1.7rem; font-weight: 800;
      color: var(--accent); line-height: 1; transition: color .2s;
    }
    .bgg-score-label, .bgg-rank-label {
      font-size: .57rem; text-transform: uppercase; letter-spacing: .1em;
      color: var(--text-muted); white-space: nowrap;
    }
    .bgg-rank-box {
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      padding: .6rem .9rem; border-right: 1px solid var(--border); min-width: 68px; gap: .15rem;
    }
    .bgg-rank-num {
      font-family: 'Barlow Condensed', sans-serif; font-size: 1.3rem; font-weight: 700;
      color: var(--text); line-height: 1;
    }
    .bgg-visit-link {
      font-size: .7rem; font-weight: 500; letter-spacing: .06em;
      color: var(--accent); text-decoration: none; flex: 1;
      padding: .6rem .9rem; transition: color .2s;
    }
    .bgg-visit-link:hover { color: var(--accent-h); text-decoration: underline; }

    /* ── Tag pills (mechanics / categories) ─────────────────── */
    .tag-groups { margin-bottom: 1.1rem; display: flex; flex-direction: column; gap: .45rem; }
    .tag-group  { display: flex; flex-wrap: wrap; gap: .35rem; }
    .tag-pill {
      font-size: .6rem; font-weight: 500; letter-spacing: .07em; text-transform: uppercase;
      padding: .2rem .55rem; border-radius: 2px; white-space: nowrap;
    }
    .tag-cat { background: rgba(<?= $accentR ?>,.12); color: var(--accent); border: 1px solid rgba(<?= $accentR ?>,.22); }
    .tag-mec { background: var(--bg-card); color: var(--text-muted); border: 1px solid var(--border); }

    /* ── Lightbox ─────────────────────────────────────────────── */
    .lightbox { display:none; position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,.92); align-items:center; justify-content:center; }
    .lightbox.open { display:flex; }
    .lightbox img { max-width:88vw; max-height:88vh; border-radius:4px; display:block; }
    .lb-close { position:absolute; top:1.25rem; right:1.5rem; font-size:2rem; color:rgba(255,255,255,.7); background:none; border:none; cursor:pointer; line-height:1; transition:color .2s; }
    .lb-close:hover { color:#fff; }

    /* ── Responsive ──────────────────────────────────────────── */
    @media (max-width:780px) {
      .main-cols { grid-template-columns:1fr; }
      .main-img-wrap { height:220px; }
    }
    @media (max-width:540px) {
      .hdr { padding:.85rem 1.25rem; }
      .page-wrap { padding:1.5rem 1.25rem 4rem; }
      .stats-grid { grid-template-columns:repeat(2,1fr); }
    }
  </style>
</head>
<body>

<!-- Lightbox -->
<div class="lightbox" id="lightbox">
  <button class="lb-close" id="lbClose">&times;</button>
  <img id="lbImg" src="" alt="">
</div>

<!-- Header -->
<header class="hdr">
  <a class="hdr-back" href="index.php">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg>
    All Games
  </a>
  <?php if ($tagline): ?><span class="hdr-tagline"><?= esc($tagline) ?></span><?php endif; ?>
  <nav class="hdr-nav">
    <a href="index.php#catalog">Games</a>
    <?php if (file_exists($dir . '/news.json')): ?><a href="news.php">News</a><?php endif; ?>
    <?php if (file_exists($dir . '/about.json')): ?><a href="about.php">About</a><?php endif; ?>
    <a class="hdr-cta" href="index.php#contact">Contact</a>
  </nav>
</header>

<!-- Page -->
<div class="page-wrap">

  <!-- Title -->
  <div class="title-row">
    <h1 class="game-title"><?= esc($name) ?></h1>
  </div>
  <?php if ($designers): ?>
    <p class="game-byline">by <?= esc(implode(' & ', $designers)) ?></p>
  <?php endif; ?>

  <!-- Two columns -->
  <div class="main-cols">

    <!-- Left: images + buy -->
    <div class="img-panel">
      <!-- Main image -->
      <div class="main-img-wrap" id="mainWrap">
        <?php if ($mainImg): ?>
          <img id="mainImg" src="<?= esc($mainImg) ?>" alt="<?= esc($name) ?>">
        <?php elseif ($mediaVidUrl): ?>
          <video id="mainImg" src="<?= esc(cachedUrl($mediaVidUrl)) ?>" muted loop playsinline autoplay></video>
        <?php else: ?>
          <div class="no-cover">&#127922;</div>
        <?php endif; ?>
      </div>

      <!-- Thumbnail strip -->
      <?php if (count($gallery) > 1): ?>
      <div class="thumb-strip" id="thumbStrip">
        <?php foreach ($gallery as $i => $img): ?>
          <img class="thumb<?= $i===0?' active':'' ?>" src="<?= esc($img) ?>" alt="" data-idx="<?= $i ?>" loading="lazy">
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Buy buttons -->
      <?php if (!empty($buyUrls)): ?>
      <div class="buy-list">
        <?php foreach ($buyUrls as $b):
          $bl = trim($b['Value 1'] ?? '') ?: 'Buy Now';
          $bh = trim($b['Value']     ?? '');
          if (!$bh) continue;
        ?>
          <a class="btn-buy" href="<?= esc($bh) ?>" target="_blank" rel="noopener"><?= esc($bl) ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Action links -->
      <?php
        $actions = [];
        if ($sellUrl)  $actions[] = [$sellUrl,  'Sell Sheet',    '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>'];
        if ($rulesPdf) $actions[] = [$rulesPdf, 'Rules PDF',     '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'];
        if ($playUrl)  $actions[] = [$playUrl,  'Play Online',   '<polygon points="5 3 19 12 5 21 5 3"/>'];
        if ($videoUrl) $actions[] = [$videoUrl, 'Watch Video',   '<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>'];
      ?>
      <?php if ($actions): ?>
      <div class="action-stack">
        <?php foreach ($actions as [$href,$label,$path]): ?>
          <a class="btn-action" href="<?= esc($href) ?>" target="_blank" rel="noopener">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?= $path ?></svg>
            <?= esc($label) ?>
          </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div><!-- /img-panel -->

    <!-- Right: info -->
    <div class="info-panel">

      <!-- Stats -->
      <?php if ($stats): ?>
      <div class="stats-grid">
        <?php foreach ($stats as [$k,$v]): ?>
          <div class="stat-box">
            <span class="stat-val"><?= esc($v) ?></span>
            <span class="stat-key"><?= esc($k) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- BGG Rating -->
      <?php if ($bggAvg): ?>
      <div class="bgg-row">
        <a class="bgg-score-box" href="<?= esc($bggUrl) ?>" target="_blank" rel="noopener" title="BGG Community Rating">
          <span class="bgg-score"><?= esc($bggAvg) ?></span>
          <span class="bgg-score-label">BGG Rating</span>
        </a>
        <?php if ($bggRankNum): ?>
        <div class="bgg-rank-box" title="BoardGameGeek Overall Rank">
          <span class="bgg-rank-num">#<?= esc($bggRankNum) ?></span>
          <span class="bgg-rank-label">BGG Rank</span>
        </div>
        <?php endif; ?>
        <a class="bgg-visit-link" href="<?= esc($bggUrl) ?>" target="_blank" rel="noopener">
          View on BoardGameGeek ↗
        </a>
      </div>
      <?php elseif ($bggUrl): ?>
      <div class="bgg-row">
        <a class="bgg-visit-link" href="<?= esc($bggUrl) ?>" target="_blank" rel="noopener" style="border:none;">
          View on BoardGameGeek ↗
        </a>
      </div>
      <?php endif; ?>

      <!-- Price / stock -->
      <?php if ($price): ?>
      <div class="price-row">
        <span class="price-tag"><?= esc($price) ?></span>
        <?php if ($stockLabel): ?>
          <span class="stock-tag stock-<?= $stockCls ?>"><?= esc($stockLabel) ?></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Description -->
      <?php if ($summary): ?>
        <p class="desc-text"><?= textWithLinks($summary) ?></p>
      <?php endif; ?>

      <!-- Mechanics / Categories -->
      <?php if ($bggCategories || $bggMechanics): ?>
      <div class="tag-groups">
        <?php if ($bggCategories): ?>
        <div class="tag-group">
          <?php foreach ($bggCategories as $cat): ?>
            <span class="tag-pill tag-cat"><?= esc($cat) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($bggMechanics): ?>
        <div class="tag-group">
          <?php foreach ($bggMechanics as $mec): ?>
            <span class="tag-pill tag-mec"><?= esc($mec) ?></span>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Meta -->
      <?php
        $meta = [];
        if ($designers)   $meta[] = ['Designers',   implode(', ', $designers)];
        if ($avail)       $meta[] = ['Availability',$avail];
        if ($dateSigned)  $meta[] = ['Signed',       $dateSigned];
        if ($datePubl)    $meta[] = ['Published',    $datePubl];
      ?>
      <?php if ($meta): ?>
      <div class="meta-list">
        <?php foreach ($meta as [$mk,$mv]): ?>
          <div class="meta-row">
            <span class="meta-key"><?= esc($mk) ?></span>
            <span class="meta-val"><?= esc($mv) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    </div><!-- /info-panel -->
  </div><!-- /main-cols -->

  <!-- Tabs -->
  <?php if ($hasTabs): ?>
  <div class="tabs-section">
    <div class="tab-nav" id="tabNav">
      <?php if (!empty($videos)):  ?><button class="tab-btn" data-tab="videos">Videos (<?= count($videos) ?>)</button><?php endif; ?>
      <?php if (!empty($reviews)): ?><button class="tab-btn" data-tab="reviews">Reviews (<?= count($reviews) ?>)</button><?php endif; ?>
      <?php if (!empty($faqs)):    ?><button class="tab-btn" data-tab="faqs">FAQs (<?= count($faqs) ?>)</button><?php endif; ?>
    </div>

    <!-- Videos -->
    <?php if (!empty($videos)): ?>
    <div class="tab-pane" id="pane-videos">
      <div class="video-grid">
        <?php foreach ($videos as $v):
          $vTitle = trim($v['Value']     ?? '');
          $vUrl   = trim($v['Value 1'] ?? '');
          if (!$vUrl) continue;
          $ytId = youtubeId($vUrl);
          $platform = str_contains(strtolower($vUrl),'tiktok') ? 'TikTok' : (str_contains(strtolower($vUrl),'youtube') || str_contains(strtolower($vUrl),'youtu.be') ? 'YouTube' : 'Video');
        ?>
          <?php if ($ytId): ?>
            <div class="video-card">
              <div class="video-embed">
                <iframe src="https://www.youtube.com/embed/<?= esc($ytId) ?>?rel=0" allowfullscreen loading="lazy" title="<?= esc($vTitle) ?>"></iframe>
              </div>
              <?php if ($vTitle): ?><p class="video-caption"><?= esc($vTitle) ?></p><?php endif; ?>
            </div>
          <?php else: ?>
            <a class="video-link-card" href="<?= esc($vUrl) ?>" target="_blank" rel="noopener">
              <span class="video-icon">&#9654;</span>
              <span>
                <div class="video-link-title"><?= esc($vTitle ?: 'Watch') ?></div>
                <div class="video-link-platform"><?= esc($platform) ?></div>
              </span>
            </a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Reviews -->
    <?php if (!empty($reviews)): ?>
    <div class="tab-pane" id="pane-reviews">
      <div class="review-list">
        <?php foreach ($reviews as $rv):
          $rq = trim($rv['Value']     ?? '');
          $rb = trim($rv['Value 1'] ?? '');
          if (!$rq) continue;
        ?>
          <div class="review-card">
            <p class="review-quote"><?= esc($rq) ?></p>
            <?php if ($rb): ?><span class="review-byline">— <?= esc($rb) ?></span><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- FAQs -->
    <?php if (!empty($faqs)): ?>
    <div class="tab-pane" id="pane-faqs">
      <div class="faq-list" id="faqList">
        <?php foreach ($faqs as $fq):
          $qq   = trim($fq['Value']   ?? '');
          $qa   = trim($fq['Value 1'] ?? '');
          $qimg = trim($fq['Value 2'] ?? '');
          if (!$qq) continue;
        ?>
          <div class="faq-item">
            <div class="faq-q">
              <?= esc($qq) ?>
              <svg class="faq-chev" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
            </div>
            <?php if ($qa || $qimg): ?>
            <div class="faq-a">
              <?php if ($qa): ?><?= nl2br($qa) ?><?php endif; ?>
              <?php if ($qimg): ?><img class="faq-img" src="<?= esc(cachedUrl($qimg)) ?>" alt="" loading="lazy"><?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
  <?php endif; ?>

</div><!-- /page-wrap -->

<!-- ── Contact form ── -->
<section class="contact-section" id="contact">
  <div class="contact-inner">
    <h2 class="contact-heading">Get in Touch</h2>
    <?php if ($tagline): ?><p class="contact-sub"><?= esc($tagline) ?></p><?php endif; ?>
    <form class="contact-form" action="contact.php" method="post" novalidate>
      <div class="cf-row">
        <div class="cf-field">
          <label class="cf-label" for="cf-name">Name</label>
          <input class="cf-input" type="text" id="cf-name" name="name" placeholder="Your name" required>
        </div>
        <div class="cf-field">
          <label class="cf-label" for="cf-email">Email</label>
          <input class="cf-input" type="email" id="cf-email" name="email" placeholder="your@email.com" required>
        </div>
      </div>
      <button class="cf-submit" type="submit">Send Message</button>
      <p class="cf-notice" id="cfNotice"></p>
    </form>
  </div>
</section>

<!-- ── Footer ── -->
<footer class="site-footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <?php if ($company): ?><p class="footer-company-name"><?= esc($company) ?></p><?php endif; ?>
      <?php if ($address): ?><p class="footer-address"><?= nl2br(esc($address)) ?></p><?php endif; ?>
    </div>
    <div class="footer-links">
      <?php if ($facebook): ?>
      <a class="footer-social" href="<?= esc($facebook) ?>" target="_blank" rel="noopener" aria-label="Facebook">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
      </a>
      <?php endif; ?>
      <?php if ($twitter): ?>
      <a class="footer-social" href="<?= esc($twitter) ?>" target="_blank" rel="noopener" aria-label="Twitter">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
      </a>
      <?php endif; ?>
      <?php if ($xdotcom): ?>
      <a class="footer-social" href="<?= esc($xdotcom) ?>" target="_blank" rel="noopener" aria-label="X">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.746l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
      </a>
      <?php endif; ?>
      <?php if ($insta): ?>
      <a class="footer-social" href="<?= esc($insta) ?>" target="_blank" rel="noopener" aria-label="Instagram">
        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
      </a>
      <?php endif; ?>
    </div>
  </div>
  <?php if ($copy): ?>
  <div class="footer-bottom">
    <p class="footer-copy"><?= $copy ?></p>
  </div>
  <?php endif; ?>
</footer>

<script>
/* ── Gallery ── */
<?php if (count($gallery) > 1): ?>
(function() {
  var imgs   = <?= json_encode(array_values($gallery)) ?>;
  var thumbs = Array.from(document.querySelectorAll('.thumb'));
  var mainEl = document.getElementById('mainImg');
  function show(idx) {
    mainEl.src = imgs[idx];
    thumbs.forEach(function(t,i){ t.classList.toggle('active', i===idx); });
  }
  thumbs.forEach(function(t,i){
    t.addEventListener('click', function(){ show(i); });
  });
})();
<?php endif; ?>

/* ── Lightbox ── */
<?php if ($mainImg): ?>
(function(){
  var lb = document.getElementById('lightbox');
  var lbImg = document.getElementById('lbImg');
  document.getElementById('mainWrap').addEventListener('click', function(){
    var src = document.getElementById('mainImg');
    if (!src || src.tagName === 'VIDEO') return;
    lbImg.src = src.src;
    lb.classList.add('open');
  });
  document.getElementById('lbClose').addEventListener('click', function(){ lb.classList.remove('open'); });
  lb.addEventListener('click', function(e){ if(e.target===lb) lb.classList.remove('open'); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape') lb.classList.remove('open'); });
})();
<?php endif; ?>

/* ── Tabs ── */
(function(){
  var btns  = Array.from(document.querySelectorAll('.tab-btn'));
  var panes = Array.from(document.querySelectorAll('.tab-pane'));
  if (!btns.length) return;
  function activate(id){
    btns.forEach(function(b){ b.classList.toggle('active', b.dataset.tab===id); });
    panes.forEach(function(p){ p.classList.toggle('active', p.id==='pane-'+id); });
  }
  btns.forEach(function(b){ b.addEventListener('click', function(){ activate(this.dataset.tab); }); });
  activate(btns[0].dataset.tab);
})();

/* ── FAQs ── */
document.querySelectorAll('.faq-q').forEach(function(q){
  q.addEventListener('click', function(){
    q.closest('.faq-item').classList.toggle('open');
  });
});

/* ── Contact form (AJAX) ── */
(function(){
  var form   = document.querySelector('.contact-form');
  var notice = document.getElementById('cfNotice');
  if (!form) return;
  form.addEventListener('submit', function(e){
    e.preventDefault();
    notice.className = 'cf-notice'; notice.textContent = '';
    var data = new FormData(form);
    fetch('contact.php', { method: 'POST', body: data })
      .then(function(r){ return r.json(); })
      .then(function(j){
        notice.className = 'cf-notice ' + (j.ok ? 'ok' : 'err');
        notice.textContent = j.message;
        if (j.ok) form.reset();
      })
      .catch(function(){ notice.className='cf-notice err'; notice.textContent='Could not send. Please try again.'; });
  });
})();
</script>
</body>
</html>
