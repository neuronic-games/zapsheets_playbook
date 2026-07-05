<?php
// ─── Data ─────────────────────────────────────────────────────────────────────
global $_cacheDir;

$dir = dirname(__DIR__);   // sheets/{id}/

if (!file_exists($dir . '/about.json')) {
    header('Location: index.php');
    exit;
}

// Site branding
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

// About tab data
// Row type is in the ID column; image in Image, name/heading in Title, text in Body.
// Supported ID values:
//   SplashUrl → slideshow image  (Image = url, Title = caption)
//   People    → team member      (Image = photo url, Title = name, Body = bio)
//   Heading   → page heading     (Title = heading text)
//   Body      → intro text       (Body = text)
$aboutRaw = json_decode(@file_get_contents($dir . '/about.json') ?: '[]', true) ?: [];

// Row type key: prefer 'ID', fall back to 'Name'
function rowId(array $row): string {
    $v = trim($row['ID'] ?? ($row['Name'] ?? ''));
    return $v;
}

$splashRows = [];
$peopleRows = [];
$heading    = 'About Us';
$bodyText   = '';

foreach ($aboutRaw as $row) {
    $id = rowId($row);
    if ($id === '') continue;
    $idLower = strtolower($id);
    if ($idLower === 'splashurl') {
        $splashRows[] = $row;
    } elseif ($idLower === 'people') {
        $peopleRows[] = $row;
    } elseif ($idLower === 'heading') {
        $heading = trim($row['Title'] ?? ($row['Value'] ?? '')) ?: $heading;
    } elseif ($idLower === 'body') {
        $bodyText = trim($row['Body'] ?? ($row['Value'] ?? ''));
    }
}

// Image URL from a row: try Image, then Value 3, then Value
function rowImage(array $row): string {
    foreach (['Image', 'ImageUrl', 'ImageURL', 'Photo', 'Value 3', 'Value'] as $k) {
        $v = trim($row[$k] ?? '');
        if ($v !== '') return $v;
    }
    return '';
}

// Build slide list: url + caption from Title (and optional Body)
$splashSlides = array_values(array_filter(
    array_map(fn($r) => [
        'url'     => rowImage($r),
        'caption' => trim($r['Title'] ?? ''),
        'sub'     => trim($r['Body']  ?? ''),
    ], $splashRows),
    fn($s) => $s['url'] !== ''
));

require_once __DIR__ . '/functions.php';

// Colors & branding
$appIconRaw = $sett['AppIconImageUrl'] ?? ($sd['AppIconImageUrl'] ?? '');
$company  = $sd['CompanyName'] ?? 'Board Game Publisher';
$logoUrl  = $sd['LogoUrl']     ?? ($sd['LogoURL'] ?? '');
$insta    = $sd['Instagram']   ?? '';
$twitter  = $sd['Twitter']     ?? '';
$xdotcom  = $sd['X']           ?? '';
$facebook = $sd['Facebook']    ?? '';
$copy     = $sd['Copyright']   ?? '&copy; ' . date('Y') . ' ' . htmlspecialchars($company, ENT_QUOTES);

$color1 = $sd['Color1'] ?? ($sd['Color 1'] ?? '');
$color2 = $sd['Color2'] ?? ($sd['Color 2'] ?? '');
$accent  = validHex($color1) ?: '#c9913d';
$accentH = lighten($accent, .14);
$bgRaw   = validHex($color2) ?: '#1a1a1a';
$bg      = luma($bgRaw) > 160 ? '#1a1a1a' : $bgRaw;
$accentR = ($t = hexRgb($accent)) ? "{$t[0]},{$t[1]},{$t[2]}" : '201,145,61';
$hdrBg   = hexRgba($accent, .96);
$isLight  = luma($bg) > 160;
$bgCard   = $isLight ? darken($bg, .05) : lighten($bg, .06);
$bgCard2  = $isLight ? darken($bg, .10) : lighten($bg, .11);
$textClr  = $isLight ? '#111111'               : '#ffffff';
$textDim  = $isLight ? 'rgba(0,0,0,.6)'        : 'rgba(255,255,255,.5)';
$textMute = $isLight ? 'rgba(0,0,0,.36)'        : 'rgba(255,255,255,.32)';
$borderClr= $isLight ? 'rgba(0,0,0,.1)'         : 'rgba(255,255,255,.08)';
$accentOnBg = (luma($accent) > 160 && $isLight) ? darken($accent, .2) : $accent;

$_cacheDir = $dir . '/cache';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>About — <?= esc($company) ?></title>
  <meta name="description" content="About <?= esc($company) ?>">
  <?php if ($appIconRaw): $appIcon = cachedUrl($appIconRaw); ?>
  <link rel="icon" href="<?= esc($appIcon) ?>">
  <link rel="apple-touch-icon" href="<?= esc($appIcon) ?>">
  <?php endif; ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg:         <?= $bg ?>;
      --bg-card:    <?= $bgCard ?>;
      --bg-card2:   <?= $bgCard2 ?>;
      --accent:     <?= $accentOnBg ?>;
      --accent-h:   <?= $accentH ?>;
      --accent-r:   <?= $accentR ?>;
      --text:       <?= $textClr ?>;
      --text-dim:   <?= $textDim ?>;
      --text-muted: <?= $textMute ?>;
      --border:     <?= $borderClr ?>;
      --hdr-bg:     <?= $hdrBg ?>;
      --radius:     3px;
    }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    body {
      background: var(--bg); color: var(--text);
      font-family: 'DM Sans', system-ui, sans-serif;
      line-height: 1.6; min-height: 100vh; overflow-x: hidden;
    }

    /* ── Header ── */
    .hdr {
      position: sticky; top: 0; z-index: 300;
      display: flex; align-items: center; justify-content: space-between;
      padding: .55rem 2.5rem;
      background: var(--hdr-bg);
      backdrop-filter: blur(14px);
      border-bottom: 1px solid rgba(0,0,0,.18);
    }
    .hdr-left  { display: flex; align-items: center; gap: .9rem; }
    .hdr-brand { display: flex; align-items: center; gap: .65rem; text-decoration: none; }
    .hdr-logo  { height: 28px; width: auto; }
    .hdr-sep   {
      width: 1px; height: 18px;
      background: rgba(255,255,255,.3);
    }
    .hdr-page-title {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 1.05rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase;
      color: rgba(255,255,255,.9); line-height: 1;
    }
    .hdr-nav { display: flex; align-items: center; gap: 1.75rem; }
    .hdr-nav a {
      color: rgba(255,255,255,.85); text-decoration: none;
      font-size: .72rem; font-weight: 500; letter-spacing: .12em; text-transform: uppercase;
      transition: color .2s;
    }
    .hdr-nav a:hover, .hdr-nav a.active { color: #fff; }
    .hdr-cta {
      border: 1px solid rgba(255,255,255,.55); border-radius: 3px;
      padding: .3rem .85rem; font-size: .68rem; font-weight: 600;
      letter-spacing: .1em; text-transform: uppercase;
      transition: background .2s, border-color .2s;
    }
    .hdr-cta:hover { background: rgba(255,255,255,.15); border-color: #fff; }

    /* ── Full-screen slideshow ── */
    .splash {
      position: relative;
      height: calc(100dvh - 48px);
      min-height: 400px;
      overflow: hidden; background: #111;
    }
    .splash-slide {
      position: absolute; inset: 0;
      opacity: 0; transition: opacity 1.2s ease;
    }
    .splash-slide.active { opacity: 1; }
    .splash-bg {
      position: absolute; inset: -6%;
      background-size: cover; background-position: center;
    }
    @keyframes kb1 { from { transform: scale(1)   translate(0,0);        } to { transform: scale(1.12) translate(-2%,-1.5%); } }
    @keyframes kb2 { from { transform: scale(1.08) translate(-1.5%,0);   } to { transform: scale(1)   translate(0,2%);      } }
    @keyframes kb3 { from { transform: scale(1)   translate(2%,-1%);     } to { transform: scale(1.1) translate(-1%,1%);    } }
    @keyframes kb4 { from { transform: scale(1.06) translate(1%,1%);     } to { transform: scale(1)   translate(-2%,-1%);  } }
    .splash-overlay {
      position: absolute; inset: 0;
      background: linear-gradient(to bottom, rgba(0,0,0,.08) 0%, rgba(0,0,0,.4) 100%);
    }
    /* Caption top-left */
    .splash-caption {
      position: absolute; top: 0; left: 0;
      padding: .55rem 1rem .5rem;
      background: rgba(0,0,0,.52);
      backdrop-filter: blur(6px);
      color: #fff;
      max-width: 55%;
      opacity: 0; transition: opacity .6s ease .4s;
      pointer-events: none;
    }
    .splash-slide.active .splash-caption { opacity: 1; }
    .splash-caption-title {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: .95rem; font-weight: 700; letter-spacing: .04em;
      line-height: 1.2;
    }
    .splash-caption-sub {
      font-size: .72rem; color: rgba(255,255,255,.75);
      margin-top: .2rem; line-height: 1.4;
    }
    .splash-dots {
      position: absolute; bottom: 1.25rem; left: 50%; transform: translateX(-50%);
      display: flex; gap: .45rem;
    }
    .splash-dot {
      width: 6px; height: 6px; border-radius: 50%;
      background: rgba(255,255,255,.4); border: none; padding: 0; cursor: pointer;
      transition: background .3s, transform .3s;
    }
    .splash-dot.active { background: #fff; transform: scale(1.3); }

    /* ── Company body section ── */
    .about-intro {
      max-width: 720px; margin: 0 auto;
      padding: 3rem 2.5rem;
      text-align: center;
    }
    .about-body {
      font-size: .95rem; color: var(--text-dim);
      line-height: 1.8; white-space: pre-line;
    }
    .about-body a, .team-bio a {
      color: var(--accent); text-decoration: underline;
      text-underline-offset: 2px; text-decoration-thickness: 1px;
    }
    .about-body a:hover, .team-bio a:hover { color: var(--accent-h); }

    /* ── Team section ── */
    .team-section {
      padding: 0 2.5rem 5rem;
      border-top: 1px solid var(--border);
    }
    .team-inner { max-width: 980px; margin: 0 auto; }
    .team-label {
      font-size: .68rem; font-weight: 600; letter-spacing: .2em; text-transform: uppercase;
      color: var(--accent); padding-top: 3rem; margin-bottom: 2.5rem;
    }
    .team-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap: 2.5rem 2rem;
    }
    /* ── Team entrance animation ── */
    @keyframes cardIn {
      from { opacity: 0; transform: translateY(36px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .team-card {
      display: flex; flex-direction: column; align-items: center; text-align: center;
      opacity: 0;
    }
    .team-card.visible {
      animation: cardIn .55s cubic-bezier(.22,.68,0,1.2) forwards;
    }
    .team-photo-wrap {
      width: 140px; height: 140px; border-radius: 50%;
      overflow: hidden; margin-bottom: 1.1rem; flex-shrink: 0;
      background: var(--bg-card2);
      box-shadow: 0 4px 24px rgba(0,0,0,.25);
      position: relative;
    }
    .team-photo-wrap::after {
      content: ''; position: absolute; inset: 0; border-radius: 50%;
      box-shadow: inset 0 0 0 2px rgba(var(--accent-r),.35);
    }
    .team-photo {
      width: 100%; height: 100%; object-fit: cover; object-position: center top;
      transition: transform .5s ease;
    }
    .team-card:hover .team-photo { transform: scale(1.06); }
    .team-initials {
      width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 2.8rem; font-weight: 800; color: var(--accent);
    }
    .team-name {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 1.25rem; font-weight: 800; color: var(--text);
      margin-bottom: .2rem; line-height: 1.1;
    }
    .team-role {
      font-size: .7rem; font-weight: 600; letter-spacing: .1em; text-transform: uppercase;
      color: var(--accent); margin-bottom: .65rem;
    }
    .team-bio {
      font-size: .83rem; color: var(--text-dim); line-height: 1.7;
    }

    /* ── Footer ── */
    .site-footer {
      background: var(--bg-card); border-top: 1px solid var(--border);
      padding: 2rem 2.5rem 1.5rem;
    }
    .footer-inner {
      max-width: 860px; margin: 0 auto;
      display: flex; flex-wrap: wrap; gap: 2rem; align-items: flex-start;
      padding-bottom: 1.5rem; margin-bottom: 1.5rem;
      border-bottom: 1px solid var(--border);
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
    .footer-copy { max-width: 860px; margin: 0 auto; font-size: .7rem; color: var(--text-muted); }

    @media (max-width: 640px) {
      .hdr { padding: .5rem 1.25rem; }
      .hdr-page-title { display: none; }
      .hdr-nav a:not(.hdr-cta) { display: none; }
      .splash { height: calc(100svh - 44px); }
      .splash-caption { max-width: 80%; }
      .about-intro { padding: 2rem 1.25rem; }
      .team-section { padding-left: 1.25rem; padding-right: 1.25rem; }
      .team-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 2rem 1.25rem; }
      .team-photo-wrap { width: 110px; height: 110px; }
    }
  </style>
</head>
<body>

<!-- ── Header ── -->
<header class="hdr">
  <div class="hdr-left">
    <a class="hdr-brand" href="index.php">
      <?php if ($logoUrl): ?><img class="hdr-logo" src="<?= esc(cachedUrl($logoUrl)) ?>" alt="<?= esc($company) ?>"><?php endif; ?>
    </a>
    <span class="hdr-sep"></span>
    <span class="hdr-page-title"><?= esc($heading) ?></span>
  </div>
  <nav class="hdr-nav">
    <a href="index.php#catalog">Games</a>
    <?php if (file_exists($dir . '/news.json')): ?><a href="news.php">News</a><?php endif; ?>
    <a href="about.php" class="active">About</a>
    <a class="hdr-cta" href="index.php#contact">Contact</a>
  </nav>
</header>

<!-- ── Full-screen slideshow ── -->
<?php if (!empty($splashSlides)): ?>
<section class="splash" id="splashSlides">
  <?php
  $kbAnims = ['kb1','kb2','kb3','kb4'];
  foreach ($splashSlides as $i => $slide):
      $cached  = cachedUrl($slide['url']);
      $caption = $slide['caption'];
      $sub     = $slide['sub'];
  ?>
  <div class="splash-slide<?= $i === 0 ? ' active' : '' ?>" data-idx="<?= $i ?>">
    <div class="splash-bg" style="background-image:url('<?= esc($cached) ?>')<?= $i === 0 ? ';animation:' . $kbAnims[0] . ' 13s ease-in-out forwards' : '' ?>"></div>
    <?php if ($caption || $sub): ?>
    <div class="splash-caption">
      <?php if ($caption): ?><div class="splash-caption-title"><?= esc($caption) ?></div><?php endif; ?>
      <?php if ($sub):     ?><div class="splash-caption-sub"><?= esc($sub) ?></div><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <div class="splash-overlay"></div>
  <?php if (count($splashSlides) > 1): ?>
  <div class="splash-dots" id="splashDots">
    <?php foreach ($splashSlides as $i => $_): ?>
    <button class="splash-dot<?= $i === 0 ? ' active' : '' ?>" data-idx="<?= $i ?>" aria-label="Slide <?= $i + 1 ?>"></button>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</section>
<?php endif; ?>

<!-- ── Company intro ── -->
<?php if ($bodyText): ?>
<div class="about-intro">
  <p class="about-body"><?= textWithLinks($bodyText) ?></p>
</div>
<?php endif; ?>

<!-- ── Team ── -->
<?php if (!empty($peopleRows)): ?>
<section class="team-section">
  <div class="team-inner">
    <p class="team-label">Our Team</p>
    <div class="team-grid">
      <?php foreach ($peopleRows as $person):
        // Column names from actual about.json: Title = name, Image = photo, Body = bio
        // Also try common alternatives in case structure varies
        $pName  = trim($person['Title']       ?? ($person['Value']   ?? ($person['PersonName'] ?? '')));
        $pRole  = trim($person['Role']        ?? ($person['Value 1'] ?? ($person['Position']   ?? '')));
        $pBio   = trim($person['Body']        ?? ($person['Value 2'] ?? ($person['Bio']        ?? ($person['Description'] ?? ''))));
        $pPhoto = rowImage($person);
        $pImg   = $pPhoto ? cachedUrl($pPhoto) : '';
        // Initials fallback
        $words    = preg_split('/\s+/', $pName);
        $initials = implode('', array_map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)), array_slice($words, 0, 2)));
        if (!$pName && !$pRole && !$pBio && !$pPhoto) continue; // skip blank rows
      ?>
      <div class="team-card">
        <div class="team-photo-wrap">
          <?php if ($pImg): ?>
          <img class="team-photo" src="<?= esc($pImg) ?>" alt="<?= esc($pName) ?>" loading="lazy">
          <?php else: ?>
          <div class="team-initials"><?= esc($initials ?: '?') ?></div>
          <?php endif; ?>
        </div>
        <?php if ($pName): ?><p class="team-name"><?= esc($pName) ?></p><?php endif; ?>
        <?php if ($pRole): ?><p class="team-role"><?= esc($pRole) ?></p><?php endif; ?>
        <?php if ($pBio):  ?><p class="team-bio"><?= textWithLinks($pBio) ?></p><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ── Footer ── -->
<footer class="site-footer">
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
  <?php if ($copy): ?><p class="footer-copy"><?= $copy ?></p><?php endif; ?>
</footer>

<script>
// ── Slideshow ──────────────────────────────────────────────────────────────────
(function () {
  var slides = Array.from(document.querySelectorAll('.splash-slide'));
  var dots   = Array.from(document.querySelectorAll('.splash-dot'));
  var total  = slides.length;
  if (total < 1) return;

  var cur   = 0;
  var timer = null;
  var KB    = ['kb1','kb2','kb3','kb4'];
  var MS    = 6000;

  function startKB(slide, idx) {
    var bg = slide.querySelector('.splash-bg');
    if (!bg) return;
    bg.style.animation = 'none';
    void bg.offsetWidth;
    bg.style.animation = KB[idx % 4] + ' 13s ease-in-out forwards';
  }

  function goTo(n) {
    slides[cur].classList.remove('active');
    dots[cur] && dots[cur].classList.remove('active');
    cur = ((n % total) + total) % total;
    slides[cur].classList.add('active');
    dots[cur] && dots[cur].classList.add('active');
    startKB(slides[cur], cur);
    clearTimeout(timer);
    if (total > 1) timer = setTimeout(function () { goTo(cur + 1); }, MS);
  }

  dots.forEach(function (d) {
    d.addEventListener('click', function () { goTo(parseInt(d.dataset.idx, 10)); });
  });

  startKB(slides[0], 0);
  if (total > 1) timer = setTimeout(function () { goTo(1); }, MS);
})();

// ── Team cards stagger-in via IntersectionObserver ────────────────────────────
(function () {
  var cards = Array.from(document.querySelectorAll('.team-card'));
  if (!cards.length || !window.IntersectionObserver) {
    cards.forEach(function (c) { c.classList.add('visible'); c.style.animationDelay = '0s'; });
    return;
  }
  var triggered = false;
  var obs = new IntersectionObserver(function (entries) {
    if (triggered) return;
    var anyVisible = entries.some(function (e) { return e.isIntersecting; });
    if (!anyVisible) return;
    triggered = true;
    obs.disconnect();
    cards.forEach(function (card, i) {
      card.style.animationDelay = (i * 0.12) + 's';
      card.classList.add('visible');
    });
  }, { threshold: 0.15 });
  // Observe first card to know when section comes into view
  if (cards[0]) obs.observe(cards[0]);
})();
</script>
</body>
</html>
