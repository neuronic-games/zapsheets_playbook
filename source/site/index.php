<?php
// ─── Data ─────────────────────────────────────────────────────────────────────
// router.php includes PHP files inside serveFile() — a function scope.
// $_cacheDir is used via `global` in cachedUrl(); declare it here so it
// lands in PHP's global scope, not the function's local scope.
global $_cacheDir;

$dir = dirname(__DIR__);

// ─── Derive absolute site URL base ────────────────────────────────────────────
// Needed so game.php links are correct regardless of trailing-slash in the URL.
// Handles both /{id}/site/ and /subdir/{id}/site/ deployments.
$_sheetId  = basename($dir);   // e.g. "14_0uRG3..."
$_reqPath  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Find the /{id}/site portion and ensure it ends with /
if (preg_match('#^(.*/|/)' . preg_quote($_sheetId, '#') . '/site(?:/|$)#i', $_reqPath, $_bm)) {
    $_siteUrl = $_bm[0];
    if (substr($_siteUrl, -1) !== '/') $_siteUrl .= '/';
} else {
    $_siteUrl = '/' . $_sheetId . '/site/';
}
$games    = json_decode(@file_get_contents($dir . '/games.json')    ?: '[]', true) ?: [];
$site     = json_decode(@file_get_contents($dir . '/site.json')     ?: '[]', true) ?: [];
$settings = json_decode(@file_get_contents($dir . '/settings.json') ?: '[]', true) ?: [];

$sd = []; $sdMulti = []; $sdRows = [];
foreach ($site as $row) {
    $n = trim($row['Name'] ?? ''); $v = trim($row['Value'] ?? '');
    if ($n !== '') { $sd[$n] = $v; $sdMulti[$n][] = $v; $sdRows[$n][] = $row; }
}

$sett = [];
foreach ($settings as $row) {
    $n = trim($row['Name'] ?? '');
    if ($n !== '') $sett[$n] = trim($row['Value'] ?? '');
}

// Splash URLs (multiple allowed)
$splashUrls = array_values(array_filter(
    $sdMulti['SplashUrl'] ?? ($sdMulti['SplashURL'] ?? ($sdMulti['Splash URL'] ?? []))
));
if (empty($splashUrls) && !empty($sd['HeroImageURL'])) $splashUrls = [$sd['HeroImageURL']];

// Full splash rows (carry Value 1 / Value 2 text per entry)
$splashRows = $sdRows['SplashUrl'] ?? ($sdRows['SplashURL'] ?? ($sdRows['Splash URL'] ?? []));

// Games
$games = array_values(array_filter($games, fn($g) => trim($g['Name'] ?? '') !== ''));

// Names (lowercase) of games that have a pulled JSON data file on disk.
// Used to decide whether to show "Find out more" on splash slides.
$gamesWithJson = array_values(array_filter(
    array_map('strtolower', array_column($games, 'Name')),
    fn($n) => file_exists($dir . '/' . $n . '.json')
));

// Sort order per status
$statusOrder = ['published' => 0, 'signed' => 1, 'available' => 2];
usort($games, fn($a,$b) =>
    ($statusOrder[strtolower(trim($a['Status']??''))] ?? 3) <=>
    ($statusOrder[strtolower(trim($b['Status']??''))] ?? 3));

// Company config
$appIconRaw = $sett['AppIconImageUrl'] ?? ($sd['AppIconImageUrl'] ?? '');
$company = $sd['CompanyName'] ?? 'Board Game Publisher';
$tagline = $sd['Tagline']     ?? '';
$about   = $sd['Description'] ?? '';
$logoUrl = $sd['LogoUrl'] ?? ($sd['LogoURL'] ?? '');
$website = $sd['Website']     ?? '';
$email   = $sd['Email']       ?? '';
$bgg     = $sd['BGG']         ?? '';
$insta    = $sd['Instagram']  ?? '';
$twitter  = $sd['Twitter']    ?? '';
$xdotcom  = $sd['X']          ?? '';
$facebook = $sd['Facebook']   ?? '';
$copy    = $sd['Copyright']   ?? '&copy; ' . date('Y') . ' ' . htmlspecialchars($company, ENT_QUOTES);

// Colors
$color1 = $sd['Color1'] ?? ($sd['Color 1'] ?? '');
$color2 = $sd['Color2'] ?? ($sd['Color 2'] ?? '');

// ─── Shared helpers (color, cache, esc, statusInfo) ──────────────────────────
require_once __DIR__ . '/functions.php';
$accent  = validHex($color1) ?: '#c9913d';
$accentH = lighten($accent, .14);
$bgRaw   = validHex($color2) ?: '#1a1a1a';
// If Color 2 is light (e.g. white), use dark gray so white text stays readable
$bg      = luma($bgRaw) > 160 ? '#1a1a1a' : $bgRaw;
$accentR = ($t=hexRgb($accent)) ? "{$t[0]},{$t[1]},{$t[2]}" : '201,145,61';
$hdrBg   = hexRgba($accent, .96);

// Light / dark adaptive palette
$isLight  = luma($bg) > 160;
$bgCard   = $isLight ? darken($bg, .05) : lighten($bg, .06);
$bgCard2  = $isLight ? darken($bg, .10) : lighten($bg, .11);
$textClr  = $isLight ? '#111111'                : '#ffffff';
$textDim  = $isLight ? 'rgba(0,0,0,.6)'         : 'rgba(255,255,255,.5)';
$textMute = $isLight ? 'rgba(0,0,0,.36)'         : 'rgba(255,255,255,.32)';
$borderClr= $isLight ? 'rgba(0,0,0,.1)'          : 'rgba(255,255,255,.08)';
$accentOnBg = (luma($accent) > 160 && $isLight) ? darken($accent,.2) : $accent;

// cachemedia.py stores files as md5(url)+ext in sheets/{id}/cache/.
$_cacheDir = $dir . '/cache';

// ─── Splash helpers ───────────────────────────────────────────────────────────
function splashType(string $u): string {
    $l=strtolower($u);
    if(preg_match('/\.(mp4|webm|ogg|mov)(\?.*)?$/',$l)) return 'video';
    if(str_contains($l,'youtube.com')||str_contains($l,'youtu.be')) return 'youtube';
    if(str_contains($l,'vimeo.com')) return 'vimeo';
    return 'image';
}
function ytId(string $u): string {
    return preg_match('/(?:v=|\/embed\/|youtu\.be\/)([A-Za-z0-9_-]{11})/',$u,$m)?$m[1]:'';
}
function vmId(string $u): string {
    return preg_match('/vimeo\.com\/(?:video\/)?(\d+)/',$u,$m)?$m[1]:'';
}
$slides = [];
foreach ($splashRows as $r) {
    $u  = trim($r['Value'] ?? '');
    $v1 = trim($r['Value 1'] ?? '');
    $v2 = trim($r['Value 2'] ?? '');
    $t  = splashType($u);
    if ($t && $u) $slides[] = ['url'=>$u,'type'=>$t,'v1'=>$v1,'v2'=>$v2];
}
// Fallback: if splashRows was empty but splashUrls has content (old site.json without extra cols)
if (empty($slides)) {
    foreach ($splashUrls as $u) { $t=splashType($u); if($t) $slides[]=['url'=>$u,'type'=>$t,'v1'=>'','v2'=>'']; }
}

// Group games by status for sections
$sections = [];
$sectionOrder = [
    'published'  => 'Published',
    'signed'     => 'Signed',
    'available'  => 'Available',
    'dev'        => 'In Development',
];
foreach ($games as $g) {
    [,$cls] = statusInfo($g['Status'] ?? '');
    $sections[$cls][] = $g;
}
// Add any unknown statuses last
foreach ($sections as $cls => $_) {
    if (!isset($sectionOrder[$cls])) $sectionOrder[$cls] = ucfirst($cls);
}

$totalGames = count($games);
$published  = count(array_filter($games, fn($g)=>strtolower(trim($g['Status']??''))==='published'));
$signed     = count(array_filter($games, fn($g)=>strtolower(trim($g['Status']??''))==='signed'));

// Pre-load ProductImage URL for each game from its tab JSON
$productImgs = [];
foreach ($games as $g) {
    $n = trim($g['Name'] ?? '');
    if (!$n) continue;
    $tabFile = $dir . '/' . strtolower($n) . '.json';
    if (!file_exists($tabFile)) continue;
    $tabData = json_decode(@file_get_contents($tabFile) ?: '[]', true) ?: [];
    foreach ($tabData as $row) {
        if (!is_array($row)) continue;
        if (strtolower(trim($row['Name'] ?? '')) === 'productimage') {
            $v = trim($row['Value'] ?? '');
            if ($v) { $productImgs[$n] = $v; break; }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= esc($company) ?></title>
  <meta name="description" content="<?= esc($tagline ?: $company . ' — Board Game Publisher') ?>">
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
      --text:      <?= $textClr ?>;
      --text-dim:  <?= $textDim ?>;
      --text-muted:<?= $textMute ?>;
      --border:    <?= $borderClr ?>;
      --hdr-bg:    <?= $hdrBg ?>;
      --radius:    3px;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; font-size: 16px; }

    body {
      background: var(--bg);
      color: var(--text);
      font-family: 'DM Sans', system-ui, sans-serif;
      line-height: 1.6;
      min-height: 100vh;
      overflow-x: hidden;
    }

    /* ────────────────────────────────── NAV */
    .hdr {
      position: fixed; top: 0; left: 0; right: 0; z-index: 300;
      display: flex; align-items: center; justify-content: space-between;
      padding: 1.4rem 2.5rem;
      background: transparent;
      transition: background .35s ease, padding .35s ease, border-color .35s ease;
      border-bottom: 1px solid transparent;
    }
    .hdr.scrolled {
      background: var(--hdr-bg);
      backdrop-filter: blur(14px);
      padding: .9rem 2.5rem;
      border-bottom-color: rgba(0,0,0,.18);
    }
    .hdr-brand { display: flex; align-items: center; gap: .65rem; text-decoration: none; }
    .hdr-logo  { height: 38px; width: auto; filter: drop-shadow(0 1px 3px rgba(0,0,0,.4)); }
    .hdr-splash-thumb {
      height: 34px; width: 52px; object-fit: cover; border-radius: 2px;
      opacity: .78; transition: opacity .2s;
      filter: drop-shadow(0 1px 4px rgba(0,0,0,.4));
    }
    .hdr-splash-thumb:hover { opacity: 1; }
    .hdr-tagline-sm {
      font-size: .68rem; color: rgba(255,255,255,.68); letter-spacing: .1em;
      font-style: italic; flex: 1; text-align: center; padding: 0 1.5rem;
    }
    .hdr-nav { display: flex; align-items: center; gap: 2rem; }
    .hdr-nav a {
      color: rgba(255,255,255,.85); text-decoration: none;
      font-size: .75rem; font-weight: 500; letter-spacing: .12em; text-transform: uppercase;
      transition: color .2s; text-shadow: 0 1px 4px rgba(0,0,0,.4);
    }
    .hdr-nav a:hover { color: #fff; }
    .hdr-cta {
      display: inline-block;
      border: 1px solid rgba(255,255,255,.55);
      color: #fff !important;
      padding: .32rem .9rem; border-radius: 2px;
      font-size: .68rem !important; letter-spacing: .14em !important;
    }
    .hdr-cta:hover { background: rgba(255,255,255,.15); border-color: #fff; }

    /* ────────────────────────────────── HERO */
    .hero {
      position: relative;
      height: 100vh; min-height: 600px;
      overflow: hidden;
    }
    .hero-slides { position: absolute; inset: 0; }

    @keyframes kb1 { 0%{transform:scale(1)    translate(0,0)}        100%{transform:scale(1.12) translate(-2%,-1%)} }
    @keyframes kb2 { 0%{transform:scale(1)    translate(0,0)}        100%{transform:scale(1.12) translate( 2%, 1%)} }
    @keyframes kb3 { 0%{transform:scale(1.12) translate(-2%,-1%)}    100%{transform:scale(1)    translate( 2%, 1%)} }
    @keyframes kb4 { 0%{transform:scale(1.12) translate( 2%, 1%)}    100%{transform:scale(1)    translate(-2%,-1%)} }

    .hero-slide {
      position: absolute; inset: 0;
      opacity: 0; transition: opacity 1.4s ease;
    }
    .hero-slide.active { opacity: 1; }

    .hero-bg {
      position: absolute; inset: 0;
      background-size: cover; background-position: center;
      filter: brightness(.82) saturate(.9);
      will-change: transform;
    }
    .hero-video {
      position: absolute; inset: 0; width: 100%; height: 100%;
      object-fit: cover; filter: brightness(.82) saturate(.9);
    }
    .hero-iframe-wrap {
      position: absolute; inset: 0; overflow: hidden;
      filter: brightness(.82) saturate(.9);
    }
    .hero-iframe-wrap iframe {
      position: absolute;
      width: 177.78vh; height: 56.25vw;
      min-width: 100%; min-height: 100%;
      top: 50%; left: 50%; transform: translate(-50%,-50%);
      border: none; pointer-events: none;
    }

    /* Dark bands top and bottom so white text is always legible */
    .hero-overlay {
      position: absolute; inset: 0; z-index: 1;
      background:
        linear-gradient(to bottom, rgba(0,0,0,.72) 0%, rgba(0,0,0,.18) 18%, transparent 36%),
        linear-gradient(to top,    <?= hexRgba($bg,.92) ?> 0%, <?= hexRgba($bg,.32) ?> 22%, transparent 42%);
    }

    /* Text block — bottom left */
    .hero-body {
      position: absolute; left: 0; right: 0; bottom: 0; z-index: 2;
      padding: 0 2.5rem 4rem;
      max-width: 660px;
    }
    .hero-title {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: clamp(3rem, 7vw, 6rem);
      font-weight: 800; line-height: 1; letter-spacing: .02em; text-transform: uppercase;
      color: #fff; text-shadow: 0 2px 16px rgba(0,0,0,.5);
      margin-bottom: .6rem;
    }
    .hero-title a {
      color: inherit; text-decoration: none;
      border-bottom: 2px solid rgba(255,255,255,.35);
      transition: border-color .2s;
    }
    .hero-title a:hover { border-bottom-color: #fff; }
    .hero-tagline {
      font-size: clamp(1.05rem, 2vw, 1.4rem); font-weight: 400;
      color: rgba(255,255,255,.92); letter-spacing: .06em; line-height: 1.65;
      text-transform: uppercase; max-width: 560px; margin-bottom: 1.25rem;
      text-shadow: 0 1px 8px rgba(0,0,0,.5);
    }
    .hero-findout {
      display: inline-flex; align-items: center; gap: .55rem;
      border: 1px solid rgba(255,255,255,.6); color: #fff;
      padding: .6rem 1.4rem; border-radius: 2px;
      font-size: .72rem; font-weight: 600; letter-spacing: .18em; text-transform: uppercase;
      text-decoration: none; transition: background .2s, border-color .2s;
      text-shadow: none;
    }
    .hero-findout:hover { background: rgba(255,255,255,.12); border-color: #fff; }
    .hero-findout svg { transition: transform .2s; }
    .hero-findout:hover svg { transform: translateX(3px); }
    .hero-rule {
      width: 48px; height: 1px;
      background: var(--accent); margin-bottom: 1.5rem;
    }
    .hero-actions { display: flex; gap: .85rem; flex-wrap: wrap; align-items: center; }
    .btn-primary {
      display: inline-flex; align-items: center; gap: .5rem;
      background: var(--accent); color: #fff;
      padding: .75rem 1.75rem; border-radius: 2px;
      font-size: .75rem; font-weight: 600; letter-spacing: .1em; text-transform: uppercase;
      text-decoration: none; transition: background .2s, transform .2s;
    }
    .btn-primary:hover { background: var(--accent-h); transform: translateY(-1px); }
    .btn-ghost {
      display: inline-flex; align-items: center; gap: .5rem;
      border: 1px solid rgba(255,255,255,.25); color: rgba(255,255,255,.7);
      padding: .75rem 1.75rem; border-radius: 2px;
      font-size: .75rem; font-weight: 500; letter-spacing: .1em; text-transform: uppercase;
      text-decoration: none; transition: border-color .2s, color .2s;
    }
    .btn-ghost:hover { border-color: var(--accent); color: var(--accent); }

    /* Stats — inline with actions */
    .hero-stats { display: flex; gap: 2.5rem; margin-top: 2.5rem; padding-top: 1.75rem; border-top: 1px solid var(--border); }
    .stat-n { font-family: 'Barlow Condensed', sans-serif; font-size: 2rem; font-weight: 700; color: var(--text); display: block; line-height: 1; }
    .stat-l { font-size: .62rem; color: var(--text-muted); letter-spacing: .18em; text-transform: uppercase; }

    /* Slide counter — bottom right */
    .slide-counter {
      position: absolute; right: 2.5rem; bottom: 4.25rem; z-index: 3;
      font-size: .68rem; font-weight: 500; letter-spacing: .18em;
      color: var(--text-dim); font-variant-numeric: tabular-nums;
    }
    .slide-counter .cur { color: var(--text); }

    /* Dots — bottom center */
    .slide-dots {
      position: absolute; bottom: 2rem; left: 50%; transform: translateX(-50%); z-index: 3;
      display: flex; gap: .55rem; align-items: center;
    }
    .slide-dot {
      width: 6px; height: 6px; border-radius: 50%;
      background: rgba(255,255,255,.25); border: none; padding: 0; cursor: pointer;
      transition: background .3s, transform .3s;
    }
    .slide-dot.active { background: var(--accent); transform: scale(1.4); }

    /* ────────────────────────────────── CATALOG */
    .catalog { padding: 5rem 2.5rem 6rem; max-width: 1560px; margin: 0 auto; }

    .catalog-hdr {
      display: flex; align-items: center; justify-content: space-between;
      flex-wrap: wrap; gap: 1rem;
      margin-bottom: 3.5rem;
      padding-bottom: 1.5rem;
      border-bottom: 1px solid var(--border);
    }
    .catalog-title {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: clamp(1.6rem, 3vw, 2.6rem); font-weight: 700;
      color: var(--text);
    }
    .catalog-sub { font-size: .78rem; color: var(--text-muted); letter-spacing: .1em; text-transform: uppercase; }

    /* Search */
    .search-wrap { position: relative; }
    .search-inp {
      padding: .5rem 1rem .5rem 2.2rem;
      background: rgba(255,255,255,.05); border: 1px solid var(--border);
      border-radius: 2px; color: var(--text); font-family: inherit; font-size: .8rem;
      width: 220px; outline: none; transition: border-color .2s;
    }
    .search-inp::placeholder { color: var(--text-muted); }
    .search-inp:focus { border-color: rgba(var(--accent-r),.5); }
    .search-icon {
      position: absolute; left: .75rem; top: 50%; transform: translateY(-50%);
      color: var(--text-muted); pointer-events: none; font-size: .8rem;
    }

    /* ── Status sections ── */
    .status-section { margin-bottom: 4rem; }
    .status-section.hidden { display: none; }

    .sec-hdr {
      display: flex; align-items: center; gap: 1.2rem;
      margin-bottom: 1.5rem;
    }
    .sec-label {
      font-size: .65rem; font-weight: 600; letter-spacing: .22em; text-transform: uppercase;
      color: var(--text-muted);
    }
    .sec-divider { flex: 1; height: 1px; background: var(--border); }
    .sec-count { font-size: .65rem; color: var(--text-muted); letter-spacing: .1em; }
    .sec-label.s-published { color: #4ade80; }
    .sec-label.s-signed    { color: #c084fc; }
    .sec-label.s-available { color: #60a5fa; }
    .sec-label.s-dev       { color: var(--text-muted); }

    /* Full-width grid — columns set per section via --cols custom property */
    .games-row {
      display: grid;
      grid-template-columns: repeat(var(--cols, 4), 1fr);
      gap: 1.1rem;
    }

    /* ── Game card ── */
    .game-card {
      position: relative; border-radius: var(--radius); overflow: hidden;
      background: var(--bg-card); cursor: pointer;
      transition: transform .35s ease;
    }
    .game-card:hover { transform: translateY(-4px); }
    .game-card.search-hidden { display: none; }

    /* Fixed height — stays constant regardless of card width */
    .card-media {
      height: 320px; overflow: hidden; position: relative;
      background: linear-gradient(160deg, <?= lighten($bg,.08) ?> 0%, var(--bg) 100%);
    }
    .card-media img {
      width: 100%; height: 100%; object-fit: cover;
      transition: transform .55s ease;
      display: block;
    }
    .game-card:hover .card-media img { transform: scale(1.07); }

    /* Bottom gradient + text overlay on image */
    .card-overlay {
      position: absolute; inset: 0;
      background: linear-gradient(to top,
        rgba(0,0,0,.9) 0%,
        rgba(0,0,0,.5) 35%,
        transparent 65%);
      display: flex; flex-direction: column; justify-content: flex-end;
      padding: 1.1rem 1rem 1rem;
    }
    .card-status-badge {
      position: absolute; top: .75rem; left: .75rem;
      font-size: .58rem; font-weight: 600; letter-spacing: .12em; text-transform: uppercase;
      border-radius: 1px; padding: .18rem .55rem;
    }
    .badge-published { background: rgba(74,222,128,.12); color: #4ade80; border: 1px solid rgba(74,222,128,.25); }
    .badge-signed    { background: rgba(192,132,252,.12); color: #c084fc; border: 1px solid rgba(192,132,252,.25); }
    .badge-available { background: rgba(96,165,250,.12);  color: #60a5fa; border: 1px solid rgba(96,165,250,.25); }
    .badge-dev       { background: rgba(107,114,128,.12); color: #9ca3af; border: 1px solid rgba(107,114,128,.2); }

    /* No-cover placeholder */
    .card-no-cover {
      width: 100%; height: 100%;
      display: flex; align-items: center; justify-content: center;
      font-size: 3.5rem; opacity: .12;
    }

    .card-name {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: .95rem; font-weight: 700; line-height: 1.25;
      color: #fff; margin-bottom: .25rem;
    }
    .card-byline {
      font-size: .65rem; color: rgba(255,255,255,.5); font-weight: 400;
    }

    /* Hover reveal panel */
    .card-hover {
      position: absolute; inset: 0;
      background: rgba(0,0,0,.82);
      display: flex; flex-direction: column; justify-content: flex-end;
      padding: 1.1rem 1rem;
      opacity: 0; transition: opacity .3s ease;
      pointer-events: none;
    }
    .game-card:hover .card-hover { opacity: 1; pointer-events: auto; }
    .card-hover .card-name   { margin-bottom: .3rem; }
    .card-summary {
      font-size: .7rem; color: rgba(255,255,255,.6); line-height: 1.55;
      margin-bottom: .9rem;
      display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden;
    }
    .card-meta-row {
      display: flex; gap: .7rem; flex-wrap: wrap;
      font-size: .62rem; color: rgba(255,255,255,.4);
      margin-bottom: .9rem;
    }
    .card-links { display: flex; gap: .4rem; flex-wrap: wrap; }
    .card-link {
      padding: .28rem .7rem; border-radius: 1px;
      font-size: .62rem; font-weight: 600; letter-spacing: .1em; text-transform: uppercase;
      text-decoration: none; border: 1px solid rgba(255,255,255,.18);
      color: rgba(255,255,255,.7); transition: border-color .2s, color .2s, background .2s;
    }
    .card-link:hover { border-color: var(--accent); color: var(--accent); }
    .card-link.primary {
      background: var(--accent); border-color: var(--accent); color: #fff;
    }
    .card-link.primary:hover { background: var(--accent-h); border-color: var(--accent-h); }

    /* ── Empty / No results ── */
    .no-results {
      padding: 3rem 0; text-align: center;
      color: var(--text-muted); font-size: .85rem;
      display: none;
    }

    /* ────────────────────────────────── FOOTER */
    .site-footer {
      border-top: 1px solid var(--border);
      padding: 3.5rem 2.5rem 2.5rem;
      display: grid; gap: 2rem;
    }
    .footer-inner { display: flex; flex-wrap: wrap; gap: 2.5rem; align-items: flex-start; }
    .footer-brand { flex: 1; min-width: 220px; }
    /* ────────────────────────────────── CONTACT */
    .contact-section {
      background: var(--bg-card); border-top: 1px solid var(--border);
      padding: 2.5rem 2.5rem;
    }
    .contact-inner {
      max-width: 900px; margin: 0 auto;
      display: flex; gap: 3rem; align-items: flex-start;
    }
    .contact-copy { flex: 0 0 auto; width: 200px; padding-top: .25rem; }
    .contact-heading {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: clamp(1.4rem, 2.5vw, 1.9rem); font-weight: 900;
      color: var(--text); margin-bottom: .4rem;
    }
    .contact-sub { font-size: .82rem; color: var(--text-dim); line-height: 1.55; margin: 0; }
    .contact-form { flex: 1; }
    .cf-row { display: flex; gap: .75rem; flex-wrap: wrap; margin-bottom: .75rem; }
    .cf-field { flex: 1; min-width: 160px; display: flex; flex-direction: column; gap: .3rem; }
    .cf-field-full { margin-bottom: .75rem; display: flex; flex-direction: column; gap: .3rem; }
    .cf-label { font-size: .65rem; font-weight: 600; text-transform: uppercase; letter-spacing: .1em; color: var(--text-muted); }
    .cf-input {
      background: var(--bg); border: 1px solid var(--border); border-radius: 3px;
      color: var(--text); padding: .6rem .85rem; font-size: .88rem; font-family: inherit;
      transition: border-color .2s; outline: none; width: 100%;
    }
    .cf-input:focus { border-color: var(--accent); }
    .cf-textarea {
      background: var(--bg); border: 1px solid var(--border); border-radius: 3px;
      color: var(--text); padding: .6rem .85rem; font-size: .88rem; font-family: inherit;
      transition: border-color .2s; outline: none; width: 100%;
      resize: vertical; min-height: 80px; line-height: 1.5;
    }
    .cf-textarea:focus { border-color: var(--accent); }
    .cf-footer { display: flex; align-items: center; gap: 1.25rem; flex-wrap: wrap; }
    .cf-submit {
      background: var(--accent); color: #fff; border: none; border-radius: 3px;
      padding: .65rem 1.75rem; font-size: .75rem; font-weight: 600; letter-spacing: .1em;
      text-transform: uppercase; cursor: pointer; transition: background .2s; white-space: nowrap;
    }
    .cf-submit:hover { background: var(--accent-h); }
    .cf-notice { font-size: .82rem; min-height: 1.2em; margin: 0; }
    .cf-notice.ok  { color: #4ade80; }
    .cf-notice.err { color: #f87171; }
    @media (max-width: 640px) {
      .contact-inner { flex-direction: column; gap: 1.25rem; }
      .contact-copy { width: auto; }
    }
    .footer-brand { flex: 1; min-width: 200px; }
    .footer-company-name {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: .95rem; font-weight: 700; color: var(--text); margin-bottom: .3rem;
    }
    .footer-address { font-size: .78rem; color: var(--text-muted); line-height: 1.65; }
    .footer-company {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 1.1rem; font-weight: 700; color: var(--text); margin-bottom: .5rem;
    }
    .footer-about { font-size: .8rem; color: var(--text-muted); line-height: 1.75; max-width: 380px; }
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
    .footer-bottom {
      display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;
      gap: 1rem; padding-top: 1.5rem; border-top: 1px solid var(--border);
    }
    .footer-copy { font-size: .7rem; color: var(--text-muted); }

    /* ────────────────────────────────── RESPONSIVE */
    @media (max-width: 768px) {
      .hdr { padding: .85rem 1.25rem; }
      .hdr-nav a:not(.hdr-cta) { display: none; }
      .hero-body { padding: 0 1.25rem 4.5rem; }
      .hero-title { font-size: 2.4rem; }
      .catalog { padding: 3.5rem 1.25rem 4rem; }
      .games-row { grid-template-columns: repeat(min(var(--cols, 2), 2), 1fr) !important; }
      .slide-counter { display: none; }
    }
    @media (max-width: 480px) {
      .hero-stats { gap: 1.5rem; }
      .hero-actions { flex-direction: column; align-items: flex-start; }
    }
  </style>
</head>
<body>

<!-- ── Header ────────────────────────────────────────────────────────────────── -->
<header class="hdr">
  <a class="hdr-brand" href="#">
    <?php if ($logoUrl): ?><img class="hdr-logo" src="<?= esc(cachedUrl($logoUrl)) ?>" alt="<?= esc($company) ?>"><?php endif; ?>
  </a>
  <?php if ($tagline): ?><span class="hdr-tagline-sm"><?= esc($tagline) ?></span><?php endif; ?>
  <nav class="hdr-nav">
    <a href="#catalog">Games</a>
    <?php if (file_exists($dir . '/news.json')): ?><a href="news.php">News</a><?php endif; ?>
    <?php if (file_exists($dir . '/about.json')): ?><a href="about.php">About</a>
    <?php elseif ($about): ?><a href="#about">About</a><?php endif; ?>
    <a class="hdr-cta" href="#contact">Contact</a>
  </nav>
</header>

<!-- ── Hero Slideshow ────────────────────────────────────────────────────────── -->
<section class="hero" id="hero">

  <?php if (!empty($slides)): ?>
  <div class="hero-slides" id="heroSlides">
    <?php foreach ($slides as $i => $slide):
      $type = $slide['type']; $url = $slide['url'];
    ?>
    <div class="hero-slide<?= $i===0?' active':'' ?>" data-type="<?= esc($type) ?>" data-idx="<?= $i ?>" data-v1="<?= esc($slide['v1']) ?>" data-v2="<?= esc($slide['v2']) ?>">
      <?php if ($type==='image'): ?>
        <div class="hero-bg" style="background-image:url('<?= esc(cachedUrl($url)) ?>')"></div>
      <?php elseif ($type==='video'):
        $cachedVideoUrl = cachedUrl($url);
        $ext=strtolower(pathinfo(strtok($cachedVideoUrl,'?'),PATHINFO_EXTENSION));
      ?>
        <video class="hero-video" autoplay loop muted playsinline preload="auto">
          <source src="<?= esc($cachedVideoUrl) ?>" type="video/<?= $ext==='webm'?'webm':'mp4' ?>">
        </video>
      <?php elseif ($type==='youtube'): $yid=ytId($url); if($yid): ?>
        <div class="hero-iframe-wrap">
          <iframe src="https://www.youtube.com/embed/<?= esc($yid) ?>?autoplay=1&mute=1&loop=1&playlist=<?= esc($yid) ?>&controls=0&showinfo=0&modestbranding=1&rel=0&iv_load_policy=3&disablekb=1"
            allow="autoplay; encrypted-media" title="bg"></iframe>
        </div>
      <?php endif; elseif ($type==='vimeo'): $vid=vmId($url); if($vid): ?>
        <div class="hero-iframe-wrap">
          <iframe src="https://player.vimeo.com/video/<?= esc($vid) ?>?autoplay=1&muted=1&loop=1&background=1&title=0&byline=0&portrait=0"
            allow="autoplay; fullscreen" title="bg"></iframe>
        </div>
      <?php endif; endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="hero-overlay"></div>

  <div class="hero-body">
    <?php $fv1 = $slides[0]['v1'] ?? ''; $fv2 = $slides[0]['v2'] ?? ''; ?>
    <h1 class="hero-title" id="heroTitle"<?= $fv1 ? '' : ' hidden' ?>><?= esc($fv1) ?></h1>
    <p  class="hero-tagline" id="heroTagline"<?= $fv2 ? '' : ' hidden' ?>><?= esc($fv2) ?></p>
    <a class="hero-findout" id="heroFindOut" href="#" hidden>
      Find out more
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
    </a>
    <div class="hero-rule"></div>
  </div>

  <?php if (count($slides) > 1): ?>
  <div class="slide-counter">
    <span class="cur" id="slideNum">01</span> / <?= sprintf('%02d', count($slides)) ?>
  </div>
  <div class="slide-dots" id="slideDots">
    <?php foreach ($slides as $i => $_): ?>
      <button class="slide-dot<?= $i===0?' active':'' ?>" data-goto="<?= $i ?>" aria-label="Slide <?= $i+1 ?>"></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>

<!-- ── Game Catalog ───────────────────────────────────────────────────────────── -->
<main class="catalog" id="catalog">
  <div class="catalog-hdr">
    <div>
      <h2 class="catalog-title">Our Games</h2>
    </div>
    <div class="search-wrap">
      <span class="search-icon">&#9906;</span>
      <input class="search-inp" id="search" type="search" placeholder="Search games…" autocomplete="off">
    </div>
  </div>

  <?php foreach ($sectionOrder as $cls => $label):
    $sectionGames = $sections[$cls] ?? [];
    if (empty($sectionGames)) continue;
  ?>
  <section class="status-section" id="sec-<?= esc($cls) ?>" data-cls="<?= esc($cls) ?>">
    <?php $cols = min(count($sectionGames), 6); ?>
    <div class="games-row" id="row-<?= esc($cls) ?>" style="--cols:<?= $cols ?>">
      <?php foreach ($sectionGames as $g):
        $name     = $g['Name']          ?? '';
        $summary  = $g['Summary']       ?? ($g['Description'] ?? '');
        $count    = $g['Count']         ?? '';
        $duration = $g['Duration']      ?? '';
        $avail    = $g['Availability']  ?? '';
        $cover    = $g['Cover URL']     ?? ($g['Image URL'] ?? '');
        $cardImg  = $productImgs[$name] ?? $cover;
        $pageUrl  = $g['Page URL']      ?? '';
        $sellUrl  = $g['Sellsheet URL'] ?? '';
        $videoUrl = $g['Video URL']     ?? '';
        $playUrl  = $g['Play URL']      ?? '';
        $status   = $g['Status']        ?? '';
        $designers = array_values(array_filter([
          $g['Designer1']??'',$g['Designer2']??'',$g['Designer3']??'',$g['Designer4']??'',
        ]));
        [$statusLabel,$statusCls] = statusInfo($status);
      ?>
      <article class="game-card" data-status="<?= esc($statusCls) ?>" data-name="<?= esc(strtolower($name)) ?>" data-sec="<?= esc($cls) ?>" data-href="<?= esc($_siteUrl) ?>game.php?name=<?= urlencode($name) ?>">
        <div class="card-media">
          <?php if ($cardImg): ?>
            <img src="<?= esc(cachedUrl($cardImg)) ?>" alt="<?= esc($name) ?>" loading="lazy">
          <?php else: ?>
            <div class="card-no-cover">&#127922;</div>
          <?php endif; ?>
          <div class="card-overlay">
            <h3 class="card-name"><?= esc($name) ?></h3>
            <?php if ($designers): ?><p class="card-byline">by <?= esc(implode(', ', $designers)) ?></p><?php endif; ?>
          </div>
          <div class="card-hover">
            <h3 class="card-name"><?= esc($name) ?></h3>
            <?php if ($designers): ?><p class="card-byline" style="margin-bottom:.6rem">by <?= esc(implode(', ', $designers)) ?></p><?php endif; ?>
            <?php if ($summary): ?><p class="card-summary"><?= esc($summary) ?></p><?php endif; ?>
            <?php if ($count || $duration || $avail): ?>
            <div class="card-meta-row">
              <?php if ($count):    ?><span>👥 <?= esc($count) ?></span><?php endif; ?>
              <?php if ($duration): ?><span>⏱ <?= esc($duration) ?></span><?php endif; ?>
              <?php if ($avail):    ?><span>🖥 <?= esc($avail) ?></span><?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="card-links">
              <a class="card-link primary" href="<?= esc($_siteUrl) ?>game.php?name=<?= urlencode($name) ?>">Details</a>
              <?php if ($sellUrl):  ?><a class="card-link" href="<?= esc($sellUrl)  ?>" target="_blank" rel="noopener">Sell Sheet</a><?php endif; ?>
              <?php if ($videoUrl): ?><a class="card-link" href="<?= esc($videoUrl) ?>" target="_blank" rel="noopener">Video</a><?php endif; ?>
              <?php if ($playUrl):  ?><a class="card-link" href="<?= esc($playUrl)  ?>" target="_blank" rel="noopener">Play</a><?php endif; ?>
            </div>
          </div>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endforeach; ?>

  <div class="no-results" id="noResults">No games match your search.</div>
</main>

<!-- ── Contact form ───────────────────────────────────────────────────────────── -->
<section class="contact-section" id="contact">
  <div class="contact-inner">
    <div class="contact-copy">
      <h2 class="contact-heading">Get in Touch</h2>
      <?php if ($tagline): ?><p class="contact-sub"><?= esc($tagline) ?></p><?php endif; ?>
    </div>
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
      <div class="cf-field-full">
        <label class="cf-label" for="cf-message">Message</label>
        <textarea class="cf-textarea" id="cf-message" name="message" placeholder="Tell us what's on your mind…"></textarea>
      </div>
      <div class="cf-footer">
        <button class="cf-submit" type="submit">Send Message</button>
        <p class="cf-notice" id="cfNotice"></p>
      </div>
    </form>
  </div>
</section>

<!-- ── Footer ─────────────────────────────────────────────────────────────────── -->
<footer class="site-footer" id="about">
  <div class="footer-inner">
    <div class="footer-brand">
      <?php if ($company): ?><p class="footer-company-name"><?= esc($company) ?></p><?php endif; ?>
      <?php if ($address = $sd['Address'] ?? ''): ?><p class="footer-address"><?= nl2br(esc($address)) ?></p><?php endif; ?>
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
  <div class="footer-bottom">
    <?php if ($copy): ?><p class="footer-copy"><?= $copy ?></p><?php endif; ?>
  </div>
</footer>

<script>
/* ── Slideshow ─────────────────────────────────────────────────────────────── */
(function(){
  var slides  = Array.from(document.querySelectorAll('.hero-slide'));
  var dots    = Array.from(document.querySelectorAll('.slide-dot'));
  var numEl   = document.getElementById('slideNum');
  var total   = slides.length;
  if (total < 2) {
    // Single slide: start Ken Burns if image, then update text/findout link and stop.
    var s = slides[0];
    if (s && s.dataset.type === 'image') startKB(s, 0);
    updateSlideText(s);
    return;
  }
  var cur     = 0;
  var timer   = null;
  var KB      = ['kb1','kb2','kb3','kb4'];
  var IMG_MS  = 10000;
  var VID_MS  = 30000;
  var FADE_MS = 1400;

  function startKB(slide, idx) {
    var bg = slide.querySelector('.hero-bg');
    if (!bg) return;
    bg.style.animation = 'none';
    void bg.offsetWidth;
    bg.style.animation = KB[idx % 4] + ' 13s ease-in-out forwards';
  }

  var titleEl   = document.getElementById('heroTitle');
  var taglineEl = document.getElementById('heroTagline');
  var findOutEl = document.getElementById('heroFindOut');
  // Map lowercase game name → product page URL (only for games with a JSON data file on disk)
  var gamesWithJson = <?= json_encode($gamesWithJson) ?>;
  var gameLinks = {};
  <?php foreach ($games as $g):
      $n = strtolower(trim($g['Name']));
  ?>
  if (gamesWithJson.indexOf(<?= json_encode($n) ?>) !== -1) {
    gameLinks[<?= json_encode($n) ?>] = <?= json_encode($_siteUrl . 'game.php?name=' . rawurlencode($g['Name'])) ?>;
  }
  <?php endforeach; ?>
  function updateSlideText(slide) {
    var v1 = slide.dataset.v1 || '';
    var v2 = slide.dataset.v2 || '';
    var href = v1 ? (gameLinks[v1.toLowerCase()] || '') : '';
    // Title — link if v1 matches a game name
    if (titleEl) {
      titleEl.innerHTML = href
        ? '<a href="' + href + '">' + v1.replace(/&/g,'&amp;').replace(/</g,'&lt;') + '</a>'
        : v1.replace(/&/g,'&amp;').replace(/</g,'&lt;');
      titleEl.hidden = !v1;
    }
    if (taglineEl) { taglineEl.textContent = v2; taglineEl.hidden = !v2; }
    // "Find out more" — only when v1 is a game name
    if (findOutEl) { findOutEl.href = href || '#'; findOutEl.hidden = !href; }
  }

  function goTo(n) {
    var prev = cur;
    cur = ((n % total) + total) % total;
    slides[prev].classList.remove('active');
    slides[cur].classList.add('active');
    dots.forEach(function(d,i){ d.classList.toggle('active', i===cur); });
    if (numEl) numEl.textContent = ('0'+(cur+1)).slice(-2);
    updateSlideText(slides[cur]);

    var slide = slides[cur];
    var type  = slide.dataset.type;
    if (type === 'image') startKB(slide, cur);
    var vid = slide.querySelector('video');
    if (vid) { vid.currentTime = 0; vid.play().catch(function(){}); vid.onended = null; }
    clearTimeout(timer);
    if (type === 'video' && vid) {
      vid.onended = function(){ vid.onended=null; clearTimeout(timer); setTimeout(function(){ goTo(cur+1); }, FADE_MS); };
      timer = setTimeout(function(){ goTo(cur+1); }, VID_MS);
    } else {
      timer = setTimeout(function(){ goTo(cur+1); }, IMG_MS);
    }
  }

  // Boot first slide
  startKB(slides[0], 0);
  updateSlideText(slides[0]);
  timer = setTimeout(function(){ goTo(1); }, IMG_MS);

  dots.forEach(function(dot){
    dot.addEventListener('click', function(){
      clearTimeout(timer);
      goTo(parseInt(dot.dataset.goto, 10));
    });
  });
})();

/* ── Search ────────────────────────────────────────────────────────────────── */
(function(){
  var cards     = Array.from(document.querySelectorAll('.game-card'));
  var sections  = Array.from(document.querySelectorAll('.status-section'));
  var searchInp = document.getElementById('search');
  var noResults = document.getElementById('noResults');

  if (!searchInp) return;

  searchInp.addEventListener('input', function(){
    var term = searchInp.value.toLowerCase().trim();
    var totalVisible = 0;

    sections.forEach(function(sec){
      var cls       = sec.dataset.cls;
      var cntEl     = document.getElementById('cnt-' + cls);
      var secCards  = Array.from(sec.querySelectorAll('.game-card'));
      var secVisible= 0;

      secCards.forEach(function(c){
        var show = term === '' || c.dataset.name.indexOf(term) !== -1;
        c.classList.toggle('search-hidden', !show);
        if (show) { secVisible++; totalVisible++; }
      });

      sec.classList.toggle('hidden', secVisible === 0);
      if (cntEl) cntEl.textContent = secVisible;
    });

    noResults.style.display = (term !== '' && totalVisible === 0) ? 'block' : 'none';
  });
})();

/* ── Header: transparent → accent on scroll ── */
(function(){
  var hdr = document.querySelector('.hdr');
  if (!hdr) return;
  function tick() {
    hdr.classList.toggle('scrolled', window.scrollY > 60);
  }
  window.addEventListener('scroll', tick, { passive: true });
  tick();
})();

/* ── Card click → product page ── */
document.querySelectorAll('.game-card').forEach(function(card) {
  card.addEventListener('click', function(e) {
    if (e.target.closest('a')) return;
    var href = card.getAttribute('data-href');
    if (href) window.location.href = href;
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
