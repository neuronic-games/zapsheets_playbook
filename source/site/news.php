<?php
// ─── Data ─────────────────────────────────────────────────────────────────────
global $_cacheDir;

$dir = dirname(__DIR__);   // sheets/{id}/

// Redirect to index if no news.json
if (!file_exists($dir . '/news.json')) {
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

// News items — [{Title, Date, Body, ImageUrl, Link, Game}, ...]
$newsRaw = json_decode(@file_get_contents($dir . '/news.json') ?: '[]', true) ?: [];
// Filter out rows with no title
$newsItems = array_values(array_filter($newsRaw, fn($r) => trim($r['Title'] ?? '') !== ''));
// Sort newest first if Date column is present
usort($newsItems, function($a, $b) {
    $da = trim($a['Date'] ?? ''); $db = trim($b['Date'] ?? '');
    if (!$da && !$db) return 0;
    if (!$da) return 1; if (!$db) return -1;
    return strtotime($db) <=> strtotime($da);
});

require_once __DIR__ . '/functions.php';

// Colors & branding (same logic as index.php)
$appIconRaw = $sett['AppIconImageUrl'] ?? ($sd['AppIconImageUrl'] ?? '');
$company  = $sd['CompanyName'] ?? 'Board Game Publisher';
$tagline  = $sd['Tagline']     ?? '';
$logoUrl  = $sd['LogoUrl']     ?? ($sd['LogoURL'] ?? '');
$insta    = $sd['Instagram']   ?? '';
$twitter  = $sd['Twitter']     ?? '';
$xdotcom  = $sd['X']          ?? '';
$facebook = $sd['Facebook']    ?? '';
$copy     = $sd['Copyright']   ?? '&copy; ' . date('Y') . ' ' . htmlspecialchars($company, ENT_QUOTES);
$about    = $sd['Description'] ?? '';

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
  <title>News — <?= esc($company) ?></title>
  <meta name="description" content="Latest news from <?= esc($company) ?>">
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
    .hdr-sep   { width: 1px; height: 18px; background: rgba(255,255,255,.3); }
    .hdr-page-title {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: 1.05rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase;
      color: rgba(255,255,255,.9); line-height: 1;
    }
    .hdr-count {
      font-size: .68rem; color: rgba(255,255,255,.5);
      font-family: 'DM Sans', sans-serif; font-weight: 400;
      letter-spacing: 0; text-transform: none;
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

    /* ── News list ── */
    .news-section { padding: 0 2.5rem 5rem; }
    .news-inner   { max-width: 860px; margin: 0 auto; }

    .news-empty {
      text-align: center; padding: 5rem 0;
      font-size: .9rem; color: var(--text-muted);
    }

    /* Card layout */
    .news-list { display: flex; flex-direction: column; }

    .news-card {
      display: grid;
      grid-template-columns: 1fr;
      border-bottom: 1px solid var(--border);
      padding: 1.75rem 0;
      gap: 1.25rem;
    }
    .news-card.has-image {
      grid-template-columns: 260px 1fr;
      align-items: stretch;
      gap: 1.75rem;
    }
    .news-img-wrap {
      border-radius: var(--radius);
      overflow: hidden;
      min-height: 160px;
    }
    .news-thumb {
      width: 100%; height: 100%; min-height: 160px;
      object-fit: cover; object-position: center;
      display: block;
      transition: transform .4s ease;
    }
    .news-card:hover .news-thumb { transform: scale(1.04); }
    .news-body { display: flex; flex-direction: column; justify-content: center; }
    .news-meta {
      display: flex; align-items: center; gap: .75rem;
      margin-bottom: .5rem; flex-wrap: wrap;
    }
    .news-date {
      font-size: .7rem; font-weight: 500; letter-spacing: .08em;
      text-transform: uppercase; color: var(--text-muted);
    }
    .news-game-tag {
      font-size: .63rem; font-weight: 600; letter-spacing: .08em;
      text-transform: uppercase; padding: .13rem .45rem;
      background: rgba(var(--accent-r), .12); color: var(--accent);
      border-radius: 3px;
    }
    .news-title {
      font-family: 'Barlow Condensed', sans-serif;
      font-size: clamp(1.15rem, 2.2vw, 1.45rem); font-weight: 800;
      color: var(--text); line-height: 1.15; margin-bottom: .55rem;
    }
    .news-title a { color: inherit; text-decoration: none; }
    .news-title a:hover { color: var(--accent); }
    .news-excerpt {
      font-size: .86rem; color: var(--text-dim);
      line-height: 1.7; margin-bottom: .85rem;
      display: -webkit-box; -webkit-line-clamp: 3;
      -webkit-box-orient: vertical; overflow: hidden;
    }
    .news-excerpt a {
      color: var(--accent); text-decoration: underline;
      text-underline-offset: 2px; text-decoration-thickness: 1px;
    }
    .news-excerpt a:hover { color: var(--accent-h); }
    .news-read-more {
      display: inline-flex; align-items: center; gap: .35rem;
      font-size: .72rem; font-weight: 600; letter-spacing: .08em;
      text-transform: uppercase; color: var(--accent); text-decoration: none;
      transition: gap .2s; margin-top: auto;
    }
    .news-read-more:hover { gap: .55rem; }
    .news-read-more svg { width: 12px; height: 12px; }

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
      .hdr-nav a:not(.hdr-cta) { display: none; }
      .news-hero, .news-section { padding-left: 1.25rem; padding-right: 1.25rem; }
      .news-card.has-image { grid-template-columns: 1fr; }
      .news-img-wrap { min-height: 200px; aspect-ratio: 16/9; }
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
    <span class="hdr-page-title">News</span>
    <?php if (!empty($newsItems)): ?>
    <span class="hdr-count"><?= count($newsItems) ?> article<?= count($newsItems) !== 1 ? 's' : '' ?></span>
    <?php endif; ?>
  </div>
  <nav class="hdr-nav">
    <a href="index.php#catalog">Games</a>
    <a href="news.php" class="active">News</a>
    <?php if (file_exists($dir . '/about.json')): ?><a href="about.php">About</a><?php endif; ?>
    <a class="hdr-cta" href="index.php#contact">Contact</a>
  </nav>
</header>

<!-- ── News list ── -->
<section class="news-section">
  <div class="news-inner">
    <?php if (empty($newsItems)): ?>
    <p class="news-empty">No news items yet.</p>
    <?php else: ?>
    <div class="news-list">
      <?php foreach ($newsItems as $item):
        $title   = trim($item['Title']    ?? '');
        $dateRaw = trim($item['Date']     ?? '');
        $body    = trim($item['Body']     ?? ($item['Content'] ?? ($item['Summary'] ?? '')));
        $imgRaw  = trim($item['ImageUrl'] ?? ($item['Image'] ?? ($item['ImageURL'] ?? '')));
        $link    = trim($item['Link']     ?? ($item['URL'] ?? ''));
        $game    = trim($item['Game']     ?? '');
        $img     = $imgRaw ? cachedUrl($imgRaw) : '';

        $dateFormatted = '';
        if ($dateRaw) {
            $ts = strtotime($dateRaw);
            $dateFormatted = $ts ? date('F j, Y', $ts) : $dateRaw;
        }
      ?>
      <article class="news-card<?= $img ? ' has-image' : '' ?>">
        <?php if ($img): ?>
        <div class="news-img-wrap">
          <img class="news-thumb" src="<?= esc($img) ?>" alt="<?= esc($title) ?>" loading="lazy">
        </div>
        <?php endif; ?>
        <div class="news-body">
          <div class="news-meta">
            <?php if ($dateFormatted): ?><span class="news-date"><?= esc($dateFormatted) ?></span><?php endif; ?>
            <?php if ($game): ?><span class="news-game-tag"><?= esc($game) ?></span><?php endif; ?>
          </div>
          <h2 class="news-title">
            <?php if ($link): ?><a href="<?= esc($link) ?>" target="_blank" rel="noopener"><?= esc($title) ?></a>
            <?php else: ?><?= esc($title) ?><?php endif; ?>
          </h2>
          <?php if ($body): ?><p class="news-excerpt"><?= textWithLinks($body) ?></p><?php endif; ?>
          <?php if ($link): ?>
          <a class="news-read-more" href="<?= esc($link) ?>" target="_blank" rel="noopener">
            Read more
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
          </a>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

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

</body>
</html>
