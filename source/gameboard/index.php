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

// Public share mode: $_gbPublicBase is injected by source/gameboard/public/index.php
// e.g. 'gameboard/abc123def456abc123def456/' — replaces sheet-ID-based path in all links.
$_gbBase = isset($_gbPublicBase)
    ? ($_appRoot . ltrim($_gbPublicBase, '/'))
    : ($_appRoot . $_sheetId . '/gameboard/');

function _gb_load(string $f): array {
    return file_exists($f) ? (json_decode(file_get_contents($f), true) ?: []) : [];
}

/**
 * Renders the unified site footer for both the catalog page and individual game pages.
 * $opts keys:
 *   'gameCredit' string  — e.g. "Floristry · Designed by Alice & Bob" (page 2 only)
 */
function _gb_footer(array $opts = []): void {
    global $_subHeading, $_subText, $_tagline, $_pubLogo, $_pubName,
           $_copyright, $_footerNote, $_socialDefs, $_gbPublicBase;
    $gameCredit = trim($opts['gameCredit'] ?? '');
    $isAdmin    = !isset($_gbPublicBase);
    $heading    = $_subHeading ?: ($_tagline ?: 'Stay in the loop.');
    $desc       = $_subText ?: 'Join our newsletter for exciting announcements and special offers!';
    ?>
<footer class="gb-footer">
  <div class="gb-footer-main">
    <!-- Brand column: logo → tagline → social icons → copyright -->
    <div class="gb-footer-brand">
      <?php if ($_pubLogo): ?>
      <img src="<?= htmlspecialchars($_pubLogo, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($_pubName, ENT_QUOTES) ?>" class="gb-footer-logo-img" />
      <?php elseif ($_pubName): ?>
      <div class="gb-footer-bar-brand"><?= htmlspecialchars($_pubName, ENT_QUOTES) ?></div>
      <?php endif; ?>
      <?php if ($_tagline): ?>
      <div class="gb-footer-brand-tagline"><?= htmlspecialchars($_tagline, ENT_QUOTES) ?></div>
      <?php endif; ?>
      <div class="gb-footer-brand-icons" <?= $isAdmin ? 'id="gbFooterIcons"' : '' ?>>
        <?php if (!empty($_socialDefs)) foreach ($_socialDefs as $_sd):
          $_isSet = !empty($_sd['url']);
          if ($isAdmin || $_isSet): ?>
        <button class="gb-social-btn<?= $_isSet ? ' gb-social-set' : ' gb-social-empty' ?>"
                data-key="<?= htmlspecialchars($_sd['key'], ENT_QUOTES) ?>"
                data-label="<?= htmlspecialchars($_sd['label'], ENT_QUOTES) ?>"
                data-url="<?= htmlspecialchars($_sd['url'], ENT_QUOTES) ?>"
                onclick="gbClickSocial(this)"
                title="<?= htmlspecialchars($_sd['label'] . ($_isSet ? '' : ' — click to add'), ENT_QUOTES) ?>"><?= $_sd['svg'] ?></button>
        <?php endif; endforeach; ?>
      </div>
      <div class="gb-footer-brand-copy">
        <?php if ($isAdmin): ?>
        <span class="gb-footer-editable"
              contenteditable="false"
              id="gbFooterCopyright"
              data-setting="copyright"
              data-placeholder="© <?= date('Y') ?> <?= htmlspecialchars($_pubName ?: 'Your Company', ENT_QUOTES) ?>"
              onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}"
              onblur="gbSaveFooterField(this)"><?= htmlspecialchars($_copyright, ENT_QUOTES) ?></span>
        <span class="gb-footer-editable"
              contenteditable="false"
              id="gbFooterNote"
              data-setting="footer_note"
              data-placeholder="Tagline or contact info…"
              onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}"
              onblur="gbSaveFooterField(this)"><?= htmlspecialchars($_footerNote, ENT_QUOTES) ?></span>
        <?php else: ?>
        <span><?= htmlspecialchars($_copyright, ENT_QUOTES) ?></span>
        <?php if ($_footerNote): ?><span><?= htmlspecialchars($_footerNote, ENT_QUOTES) ?></span><?php endif; ?>
        <?php endif; ?>
        <?php if ($gameCredit): ?>
        <span class="gb-footer-game-credit"><?= htmlspecialchars($gameCredit, ENT_QUOTES) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <!-- Subscribe column -->
    <div class="gb-footer-subscribe">
      <div class="gb-footer-sub-label"><?= htmlspecialchars($heading, ENT_QUOTES) ?></div>
      <div class="gb-footer-sub-desc"><?= htmlspecialchars($desc, ENT_QUOTES) ?></div>
      <form class="gb-footer-sub-form" id="gbSubscribeForm" onsubmit="gbSubscribeSubmit(event)">
        <input type="email" id="gbSubscribeEmail" class="gb-footer-sub-input" placeholder="Your email" required autocomplete="email" />
        <button type="submit" class="gb-footer-sub-btn" id="gbSubscribeBtn">Subscribe</button>
      </form>
      <p class="gb-footer-sub-msg" id="gbSubscribeMsg"></p>
    </div>
  </div>
</footer>
    <?php
}

function _gb_slug(string $s): string {
    return strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
}

function _gb_img(string $raw): string {
    if (preg_match('/^=IMAGE\("([^"]*)"\)/i', $raw, $m)) return $m[1];
    return $raw;
}

// Strip markdown hyperlink [text](url) → url, or =IMAGE("url") → url
function _gb_link(string $raw): string {
    $raw = trim($raw);
    if (preg_match('/^\[.*?\]\((.+?)\)$/', $raw, $m)) return trim($m[1]);
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

// ── Load games list ──────────────────────────────────────────────────────────
$_allGames = _gb_load($_sDir . '/games.json');
// Catalog only shows Published games; individual pages work for any game
$_games = array_values(array_filter($_allGames, fn($g) => strtolower(trim($g['Status'] ?? '')) === 'published'));

// Shared image-file SVG icon used on every image-change button
$_imgBtnIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';

// ── Shared settings (available to both catalog and game pages) ────────────────
$_pubName    = trim($_settings['Company'] ?? $_settings['Name'] ?? '');
$_pubLogo    = trim($_settings['Logo URL'] ?? '') ?: ($_appRoot . 'images/gb_logo.png');
$_tagline    = trim($_settings['Tagline'] ?? '');
$_insta      = trim($_settings['Instagram'] ?? '');
$_twitter    = trim($_settings['Twitter']   ?? '');
$_fb         = trim($_settings['Facebook']  ?? '');
$_yt         = trim($_settings['YouTube']   ?? '');
$_bgg        = trim($_settings['BGG URL']   ?? '');
$_copyright  = trim($_settings['Copyright']   ?? '') ?: '© ' . date('Y') . ' ' . ($_pubName ?: 'GameBoard');
$_footerNote = trim($_settings['Footer Note']       ?? '');
$_subHeading = trim($_settings['Subscribe Heading'] ?? '');
$_subText    = trim($_settings['Subscribe Text']    ?? '');
$_socialDefs = [
  ['key'=>'instagram','label'=>'Instagram',    'url'=>$_insta,   'svg'=>'<svg width="16" height="16" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>'],
  ['key'=>'twitter',  'label'=>'X / Twitter', 'url'=>$_twitter, 'svg'=>'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.73-8.835L1.254 2.25H8.08l4.253 5.622L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>'],
  ['key'=>'facebook', 'label'=>'Facebook',    'url'=>$_fb,      'svg'=>'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>'],
  ['key'=>'youtube',  'label'=>'YouTube',     'url'=>$_yt,      'svg'=>'<svg width="16" height="12" viewBox="0 0 24 17"><path d="M23.495 2.205a3.02 3.02 0 0 0-2.122-2.136C19.545 0 12 0 12 0S4.455 0 2.627.07a3.02 3.02 0 0 0-2.122 2.135C0 4.046 0 8.5 0 8.5s0 4.455.505 6.295a3.02 3.02 0 0 0 2.122 2.136C4.455 17 12 17 12 17s7.545 0 9.373-.07a3.02 3.02 0 0 0 2.122-2.135C24 12.954 24 8.5 24 8.5s0-4.454-.505-6.295zM9.545 12.273V4.727L15.818 8.5l-6.273 3.773z"/></svg>'],
  ['key'=>'bgg_url',  'label'=>'BoardGameGeek','url'=>$_bgg,    'svg'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2v-4M9 21H5a2 2 0 0 1-2-2v-4m0 0h18"/></svg>'],
];

if (!$_gameSlug) {
    // ── No slug: render the admin catalog ────────────────────────────────────────
    $_catUrl   = $_gbBase;
    $_logoColor = $_template === 'opensea' ? '#00e5ff' : '#7c9eff';
    $_accentHex = $_template === 'opensea' ? '#0097a7' : '#3b3baa';
    // Carousel images — new format: "Image" → array; old format: "Carousel N" fallback
    $_carousel = [];
    if (!empty($_settings['Image'])) {
        $imgs = $_settings['Image'];
        foreach ((is_array($imgs) ? $imgs : [$imgs]) as $_u) {
            $_u = trim($_u); if ($_u) $_carousel[] = $_u;
        }
    } else {
        for ($i = 1; $i <= 6; $i++) {
            $_u = trim($_settings['Carousel ' . $i] ?? '');
            if ($_u) $_carousel[] = $_u;
        }
    }
    // Company copy
    $_about       = trim($_settings['About'] ?? '');
    // Banner color
    $_bannerColor = trim($_settings['Banner Color'] ?? '') ?: '#1a1a2e';
    // Carousel fallback: use game cover images if no carousel URLs are set
    if (empty($_carousel)) {
        foreach ($_games as $_g) {
            $_cu = _gb_img(trim($_g['Cover URL'] ?? $_g['Image URL'] ?? $_g['Image'] ?? ''));
            if ($_cu) { $_carousel[] = $_cu; if (count($_carousel) >= 6) break; }
        }
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= h($_pubName ?: 'GameBoard') ?></title>
<style>
@font-face { font-family:'DINBlack';   src:url('<?= h($_appRoot) ?>fonts/DINBlack.woff2')  format('woff2'); }
@font-face { font-family:'DINRegular'; src:url('<?= h($_appRoot) ?>fonts/DINMedium.woff2') format('woff2'); }
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --ink:#1a1a2e; --ink-lt:#4a4a6a; --muted:#888; --border:#e2e2ec;
  --surface:#fff; --bg:#f6f5f2; --accent:<?= h($_accentHex) ?>; --nav-bg:#1a1a2e; --banner-bg:<?= h($_bannerColor) ?>;
<?php if ($_template === 'opensea'): ?>
  --ink:#0d1b2a; --ink-lt:#2c4a6e; --muted:#6b8fae; --border:#c8dcea;
  --surface:#fff; --bg:#f0f6fb; --nav-bg:#0d1b2a;
<?php endif; ?>
}
body { background:var(--bg); font-family:'DINRegular',Arial,sans-serif; color:var(--ink); min-height:100vh; }
a { color:inherit; text-decoration:none; }

/* ── Sticky top wrapper ── */
.gb-top-wrap { position:sticky; top:0; z-index:100; }
/* ── Admin bar (hidden in public) ── */
.gb-admin-bar { background:#0d0d1a; color:rgba(255,255,255,.55); display:flex; align-items:center; justify-content:space-between; padding:.3rem 1.25rem; gap:1rem; }
.gb-admin-label { font-family:'DINBlack',sans-serif; font-size:.6rem; text-transform:uppercase; letter-spacing:.12em; color:rgba(255,255,255,.22); }
.gb-admin-tools { display:flex; align-items:center; gap:.5rem; flex-shrink:0; }
.gb-meta { font-size:.72rem; opacity:.55; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:200px; }
.btn-add-game { font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase; letter-spacing:.07em; padding:.28rem .8rem; border-radius:5px; border:1.5px solid rgba(255,255,255,.35); color:#fff; background:transparent; cursor:pointer; transition:background .14s,border-color .14s; white-space:nowrap; }
.btn-add-game:hover { background:rgba(255,255,255,.14); border-color:#fff; }
/* ── Page banner (always visible) ── */
.gb-banner { background:var(--banner-bg); color:#fff; display:flex; align-items:center; justify-content:space-between; padding:1.1rem 2rem; gap:1.5rem; }
.gb-banner-left { display:flex; align-items:center; gap:.9rem; min-width:0; }
.gb-banner-name { font-family:'DINBlack',sans-serif; font-size:1.1rem; letter-spacing:.06em; opacity:.75; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; outline:none; }
.gb-banner-name-editable { border-bottom:1.5px solid transparent; border-radius:2px; transition:border-color .14s,opacity .14s; min-width:6px; display:inline-block; }
body.gb-admin-mode .gb-banner-name-editable { cursor:text; opacity:.85; border-bottom-color:rgba(255,255,255,.25); }
body.gb-admin-mode .gb-banner-name-editable:hover { border-bottom-color:rgba(255,255,255,.55); opacity:1; }
body.gb-admin-mode .gb-banner-name-editable:focus { border-bottom-color:#fff; opacity:1; }
.gb-banner-name-editable:empty:before { content:attr(data-placeholder); opacity:.35; font-style:italic; pointer-events:none; }
.gb-banner-nav { display:flex; align-items:center; gap:1.75rem; flex:1; justify-content:center; }
.gb-banner-nav a { font-family:'DINBlack',sans-serif; font-size:.82rem; text-transform:uppercase; letter-spacing:.06em; color:rgba(255,255,255,.75); transition:color .14s; white-space:nowrap; }
.gb-banner-nav a:hover { color:#fff; }
.gb-banner-right { display:flex; align-items:center; gap:.6rem; flex-shrink:0; }
/* Color picker button */
.gb-banner-color-btn { width:30px; height:30px; border-radius:50%; border:2px solid rgba(255,255,255,.35); background:transparent; color:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:border-color .14s,background .14s; padding:0; flex-shrink:0; }
.gb-banner-color-btn:hover { border-color:#fff; background:rgba(255,255,255,.15); }

/* User icon + menu (matches T&MBoard) */
.btn-user-icon {
  width:32px; height:32px; border-radius:50%; border:1.5px solid rgba(255,255,255,.3);
  background:transparent; color:#fff; cursor:pointer; display:flex; align-items:center;
  justify-content:center; transition:background .14s, border-color .14s;
}
.btn-user-icon:hover, .btn-user-icon.signed-in { background:rgba(255,255,255,.15); border-color:#fff; }
.btn-user-icon.signed-in { border-color:<?= h($_logoColor) ?>; color:<?= h($_logoColor) ?>; }
.user-menu-wrap { position:relative; }
.user-menu {
  display:none; position:absolute; top:calc(100% + 6px); right:0;
  background:#1e1e30; border:1px solid rgba(255,255,255,.12); border-radius:8px;
  min-width:140px; box-shadow:0 6px 24px rgba(0,0,0,.4); overflow:hidden; z-index:200;
}
.user-menu.open { display:block; }
.user-menu-item {
  display:block; width:100%; text-align:left; padding:.55rem 1rem;
  font-family:'DINRegular',sans-serif; font-size:.82rem; color:rgba(255,255,255,.8);
  background:none; border:none; cursor:pointer; transition:background .12s;
}
.user-menu-item:hover { background:rgba(255,255,255,.08); }
.user-menu-item.signout { color:#ff6b6b; }
.user-menu-divider { border:none; border-top:1px solid rgba(255,255,255,.1); margin:2px 0; }

/* ── Logo placeholder ── */
.gb-logo-wrap { position:relative; display:inline-flex; align-items:center; flex-shrink:0; }
.gb-logo-ph { display:flex; align-items:center; justify-content:center; height:48px; min-width:96px; padding:0 .85rem; border:1.5px dashed rgba(255,255,255,.3); border-radius:6px; font-family:'DINBlack',sans-serif; font-size:.62rem; text-transform:uppercase; letter-spacing:.1em; color:rgba(255,255,255,.35); white-space:nowrap; transition:border-color .15s; cursor:pointer; }
.gb-logo-wrap.gb-drag-over .gb-logo-ph { border-color:rgba(255,255,255,.7); background:rgba(255,255,255,.07); }
.gb-logo-edit-btn { position:absolute; top:-.45rem; right:-.45rem; z-index:6; }
.gb-logo-img { height:48px; object-fit:contain; flex-shrink:0; }

/* ── Carousel ── */
.cat-carousel { position:relative; width:100%; height:clamp(240px,42vw,520px); overflow:hidden; background:var(--nav-bg); }
.cat-slide { position:absolute; inset:0; background-size:cover; background-position:center; opacity:0; transition:opacity .7s ease; }
.cat-slide.active { opacity:1; }
.cat-carousel-ph { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.75rem; }
.cat-carousel-ph-icon { width:80px; height:80px; opacity:.18; }
.cat-carousel-ph-text { font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase; letter-spacing:.1em; color:rgba(255,255,255,.35); }
.cat-slide-btn { position:absolute; top:50%; transform:translateY(-50%); background:rgba(0,0,0,.45); border:none; color:#fff; width:2.4rem; height:2.4rem; border-radius:50%; cursor:pointer; font-size:1.2rem; display:flex; align-items:center; justify-content:center; z-index:10; transition:background .15s; }
.cat-slide-btn:hover { background:rgba(0,0,0,.75); }
.cat-slide-prev { left:.75rem; }
.cat-slide-next { right:.75rem; }
.cat-slide-dots { position:absolute; bottom:.75rem; left:50%; transform:translateX(-50%); display:flex; gap:.45rem; z-index:10; }
.cat-slide-dot { width:.55rem; height:.55rem; border-radius:50%; background:rgba(255,255,255,.45); border:none; cursor:pointer; padding:0; transition:background .2s,transform .2s; }
.cat-slide-dot.active { background:#fff; transform:scale(1.2); }
/* Carousel admin controls */
.cat-slide-edit-btn,.cat-slide-del-btn { position:absolute; display:none; align-items:center; justify-content:center; border:none; cursor:pointer; z-index:15; transition:background .14s; }
.cat-slide-edit-btn { bottom:.75rem; right:.75rem; width:2rem; height:2rem; border-radius:50%; background:rgba(0,0,0,.55); color:#fff; }
.cat-slide-edit-btn svg { pointer-events:none; }
.cat-slide-del-btn { top:.5rem; right:.5rem; width:1.6rem; height:1.6rem; border-radius:50%; background:rgba(200,50,50,.75); color:#fff; font-size:1rem; line-height:1; }
.cat-slide-del-btn:hover { background:rgba(200,50,50,1); }
body.gb-admin-mode .cat-slide-edit-btn,
body.gb-admin-mode .cat-slide-del-btn { display:flex; }
body.gb-admin-mode .cat-slide-edit-btn:hover { background:rgba(0,0,0,.85); }

/* ── About strip ── */
.cat-about { max-width:720px; margin:2.5rem auto 0; padding:0 1.5rem; text-align:center; }
.cat-about-text { font-size:.93rem; line-height:1.7; color:var(--ink-lt); }

/* ── Section heading ── */
.cat-section-head { max-width:1100px; margin:2.5rem auto .75rem; padding:0 1.25rem; display:flex; align-items:baseline; gap:.75rem; }
.cat-section-title { font-family:'DINBlack',sans-serif; font-size:1rem; text-transform:uppercase; letter-spacing:.06em; color:var(--ink); }
.cat-section-count { font-size:.78rem; color:var(--muted); }

/* ── Grid ── */
.cat-grid { max-width:1100px; margin:0 auto 5rem; padding:0 1.25rem; display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:1.1rem; }
.cat-card { background:var(--surface); border-radius:12px; box-shadow:0 2px 14px rgba(0,0,0,.08); overflow:hidden; transition:box-shadow .15s, transform .15s; cursor:pointer; display:flex; flex-direction:column; width:100%; text-decoration:none; }
.cat-card:hover { box-shadow:0 6px 24px rgba(0,0,0,.14); transform:translateY(-3px); }
.cat-card-img { width:100%; aspect-ratio:1; object-fit:cover; display:block; }
.cat-card-ph { width:100%; aspect-ratio:1; display:flex; align-items:center; justify-content:center; font-size:3.5rem; opacity:.28; background:linear-gradient(135deg,#dde,#c8c8e8); }
.cat-card-body { padding:.75rem .9rem 1rem; flex:1; display:flex; flex-direction:column; gap:.2rem; }
.cat-card-name { font-family:'DINBlack',sans-serif; font-size:.95rem; color:var(--accent); line-height:1.2; }
.cat-card-summary { font-size:.8rem; color:var(--ink-lt); line-height:1.45; flex:1; margin-top:.1rem; }
.cat-card-meta { display:flex; flex-wrap:wrap; gap:.3rem .65rem; margin-top:.35rem; }
.cat-card-stat { font-size:.72rem; color:var(--muted); }
.cat-card-wrap { position:relative; }
.cat-card-del-btn { display:none; position:absolute; bottom:.55rem; right:.55rem; width:1.6rem; height:1.6rem; border-radius:50%; border:none; background:rgba(220,50,50,.85); color:#fff; font-size:.7rem; line-height:1; cursor:pointer; align-items:center; justify-content:center; z-index:3; transition:background .13s; padding:0; }
.cat-card-del-btn:hover { background:#c0392b; }
body.gb-admin-mode .cat-card-del-btn { display:flex; }

/* ── Shared image-change button (admin mode) ── */
.gb-img-btn { width:2rem; height:2rem; border-radius:50%; background:rgba(0,0,0,.52); color:#fff; display:none; align-items:center; justify-content:center; cursor:pointer; transition:background .15s; line-height:1; padding:0; user-select:none; border:none; flex-shrink:0; }
.gb-img-btn:hover { background:rgba(0,0,0,.82); }
.gb-img-btn svg { pointer-events:none; }
body.gb-admin-mode .gb-img-btn { display:flex; }

/* ── Cat card image upload (admin mode) ── */
.cat-img-wrap { position:relative; display:block; width:100%; }
.cat-img-upload-btn { position:absolute; top:.4rem; right:.4rem; z-index:2; }
.cat-img-wrap.drag-over::after { content:'Drop to set image'; position:absolute; inset:0; background:rgba(55,110,200,.75); color:#fff; font-family:'DINBlack',sans-serif; font-size:.8rem; text-transform:uppercase; letter-spacing:.07em; display:flex; align-items:center; justify-content:center; z-index:3; pointer-events:none; }
/* Loading overlay on card image during upload */
@keyframes gbImgSpin { to { transform:translate(-50%,-50%) rotate(360deg); } }
.cat-img-wrap.img-loading::after { content:''; position:absolute; inset:0; background:rgba(0,0,0,.45); z-index:4; pointer-events:none; border-radius:inherit; }
.cat-img-wrap.img-loading::before { content:''; position:absolute; top:50%; left:50%; width:28px; height:28px; border:3px solid rgba(255,255,255,.3); border-top-color:#fff; border-radius:50%; animation:gbImgSpin .7s linear infinite; z-index:5; pointer-events:none; }
/* Loading overlay on carousel slide during upload */
.cat-slide-loading { position:absolute; inset:0; background:rgba(0,0,0,.45); z-index:12; pointer-events:none; }
.cat-slide-loading::after { content:''; position:absolute; top:50%; left:50%; width:36px; height:36px; border:3px solid rgba(255,255,255,.3); border-top-color:#fff; border-radius:50%; animation:gbImgSpin .7s linear infinite; transform:translate(-50%,-50%); }

/* ── Empty ── */
.cat-empty { grid-column:1/-1; padding:4rem 1rem; text-align:center; color:var(--muted); }
.cat-empty-icon { font-size:3rem; margin-bottom:.75rem; }
.cat-empty-title { font-family:'DINBlack',sans-serif; font-size:1rem; color:var(--ink); margin-bottom:.4rem; }
.cat-empty-body { font-size:.85rem; line-height:1.6; }

/* ── Footer ── */
.gb-footer { background:#1c1c1e; color:rgba(255,255,255,.5); }
.gb-footer-main { max-width:1100px; margin:0 auto; padding:3.5rem 2rem 3rem; display:grid; grid-template-columns:1fr 1.15fr; gap:2.5rem 5rem; align-items:start; }
@media(max-width:680px){ .gb-footer-main { grid-template-columns:1fr; padding:2.5rem 1.5rem 2.5rem; gap:2rem; } }
/* Brand column */
.gb-footer-brand { display:flex; flex-direction:column; gap:.7rem; }
.gb-footer-logo-img { height:52px; object-fit:contain; object-position:left center; display:block; }
.gb-footer-bar-brand { font-family:'DINBlack',sans-serif; font-size:1.15rem; color:rgba(255,255,255,.9); }
.gb-footer-brand-tagline { font-size:.83rem; color:rgba(255,255,255,.42); line-height:1.65; }
.gb-footer-brand-icons { display:flex; gap:.45rem; align-items:center; }
.gb-footer-brand-copy { display:flex; flex-direction:column; gap:.18rem; font-size:.71rem; color:rgba(255,255,255,.28); margin-top:.15rem; }
.gb-footer-game-credit { font-size:.68rem; color:rgba(255,255,255,.2); }
/* Subscribe column */
.gb-footer-sub-label { font-family:'DINBlack',sans-serif; font-size:1rem; color:#fff; margin-bottom:.3rem; }
.gb-footer-sub-desc { font-size:.82rem; color:rgba(255,255,255,.42); margin-bottom:1rem; line-height:1.55; }
.gb-footer-sub-form { display:flex; gap:.4rem; }
.gb-footer-sub-input { flex:1; min-width:0; padding:.62rem 1rem; border:none; border-radius:999px; background:rgba(255,255,255,.1); color:#fff; font-size:.88rem; outline:none; transition:background .18s; }
.gb-footer-sub-input::placeholder { color:rgba(255,255,255,.35); }
.gb-footer-sub-input:focus { background:rgba(255,255,255,.16); }
.gb-footer-sub-btn { padding:.62rem 1.4rem; border:none; border-radius:999px; background:var(--accent); color:#fff; font-family:'DINBlack',sans-serif; font-size:.78rem; letter-spacing:.05em; cursor:pointer; transition:opacity .18s; white-space:nowrap; }
.gb-footer-sub-btn:hover { opacity:.85; }
.gb-footer-sub-btn:disabled { opacity:.55; cursor:default; }
.gb-footer-sub-msg { margin-top:.55rem; font-size:.77rem; min-height:1em; }
.gb-footer-sub-msg.ok  { color:#6bd98a; }
.gb-footer-sub-msg.err { color:#e07070; }
/* Social buttons */
.gb-social-btn { width:1.85rem; height:1.85rem; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:border-color .14s,background .14s; cursor:pointer; padding:0; }
.gb-social-btn svg { fill:rgba(255,255,255,.6); pointer-events:none; }
.gb-social-set { border:1.5px solid rgba(255,255,255,.25); background:transparent; }
.gb-social-set:hover { border-color:rgba(255,255,255,.6); background:rgba(255,255,255,.08); }
.gb-social-empty { border:1.5px dashed rgba(255,255,255,.18); background:transparent; display:none; }
.gb-social-empty svg { opacity:.35; }
body.gb-admin-mode .gb-social-empty { display:flex; }
body.gb-admin-mode .gb-social-empty:hover { border-color:rgba(255,255,255,.4); background:rgba(255,255,255,.05); }
body.gb-admin-mode .gb-social-btn { cursor:pointer; }
/* Inline-edit fields in footer (admin only) */
.gb-footer-editable { outline:none; cursor:default; border-radius:3px; transition:background .13s,border-color .13s; min-width:4px; display:inline-block; border-bottom:1.5px solid transparent; }
body.gb-admin-mode .gb-footer-editable { cursor:text; border-bottom-color:rgba(255,255,255,.18); padding-bottom:1px; }
body.gb-admin-mode .gb-footer-editable:hover { border-bottom-color:rgba(255,255,255,.4); }
body.gb-admin-mode .gb-footer-editable:focus { border-bottom-color:rgba(255,255,255,.75); outline:none; }
.gb-footer-editable:empty:before { content:attr(data-placeholder); color:rgba(255,255,255,.22); pointer-events:none; font-style:italic; }
/* Save button highlight */
#gbSavePageBtn { background:rgba(255,255,255,.12); border-color:rgba(255,255,255,.45); color:#fff; }
#gbSavePageBtn:hover { background:rgba(255,255,255,.22); border-color:#fff; }

/* ── Overlay / Dialog (sign-in + settings) ── */
.gb-overlay {
  display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:500;
  align-items:center; justify-content:center;
}
.gb-overlay.open { display:flex; }
.gb-dialog {
  background:#fff; border-radius:12px; padding:1.6rem 1.75rem 1.5rem;
  width:90%; max-width:360px; box-shadow:0 8px 40px rgba(0,0,0,.28);
}
.gb-dialog-title { font-family:'DINBlack',sans-serif; font-size:1rem; text-transform:uppercase; letter-spacing:.07em; color:var(--ink); margin-bottom:1rem; }
.gb-field-group { margin-bottom:.7rem; }
.gb-field-group label { display:block; font-size:.75rem; color:var(--muted); margin-bottom:.25rem; }
.gb-field { display:block; width:100%; padding:.55rem .75rem; border:1.5px solid var(--border); border-radius:7px; font-family:'DINRegular',sans-serif; font-size:.9rem; color:var(--ink); outline:none; transition:border-color .14s; }
.gb-field:focus { border-color:var(--accent); }
.gb-err, .gbed-err { font-size:.78rem; color:#e53935; min-height:1.1rem; margin:.3rem 0; }
.gb-dialog-close { position:absolute; top:.75rem; right:.9rem; background:none; border:none; font-size:1.35rem; color:var(--ink-lt); cursor:pointer; line-height:1; }
.gb-dialog { position:relative; }
.gb-btn { font-family:'DINBlack',sans-serif; font-size:.75rem; text-transform:uppercase; letter-spacing:.07em; padding:.42rem 1rem; border-radius:6px; border:1.5px solid var(--border); background:var(--surface); color:var(--ink); cursor:pointer; }
.gb-btn:hover { background:#f0f0f8; }
.gb-btn-sm { font-family:'DINBlack',sans-serif; font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; padding:.3rem .65rem; border-radius:5px; border:1.5px solid var(--border); background:var(--surface); color:var(--ink-lt); cursor:pointer; white-space:nowrap; }
.gb-btn-sm:hover { background:#f0f0f8; color:var(--ink); }
.gb-dialog-actions { display:flex; gap:.5rem; justify-content:flex-end; margin-top:.9rem; }
.btn-cancel { font-family:'DINBlack',sans-serif; font-size:.75rem; text-transform:uppercase; letter-spacing:.07em; padding:.42rem .9rem; border-radius:6px; border:1.5px solid var(--border); background:var(--surface); color:var(--ink-lt); cursor:pointer; }
.btn-cancel:hover { background:#f0f0f8; }
.btn-primary { font-family:'DINBlack',sans-serif; font-size:.75rem; text-transform:uppercase; letter-spacing:.07em; padding:.42rem .9rem; border-radius:6px; border:none; background:var(--accent); color:#fff; cursor:pointer; transition:opacity .13s; }
.btn-primary:disabled { opacity:.45; cursor:default; }
.btn-primary:not(:disabled):hover { opacity:.88; }
#siConfirmPanel { display:none; }

@media(max-width:520px) { .cat-grid { grid-template-columns:1fr 1fr; } }
@media(max-width:360px) { .cat-grid { grid-column:1; } }

/* ── Shared gbed dialog styles (also used by Add Game) ── */
.gbed-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:600; align-items:flex-start; justify-content:center; padding:2rem 1rem; overflow-y:auto; }
.gbed-overlay.open { display:flex; }
.gbed-dialog { background:#fff; border-radius:14px; width:100%; max-width:520px; box-shadow:0 12px 50px rgba(0,0,0,.32); overflow:hidden; margin:auto; }
.gbed-header { background:var(--nav-bg); color:#fff; padding:1rem 1.25rem; display:flex; align-items:center; justify-content:space-between; }
.gbed-title { font-family:'DINBlack',sans-serif; font-size:.9rem; text-transform:uppercase; letter-spacing:.07em; }
.gbed-close { background:none; border:none; color:rgba(255,255,255,.6); font-size:1.2rem; cursor:pointer; padding:0 .25rem; line-height:1; }
.gbed-close:hover { color:#fff; }
.gbed-body { overflow:hidden; }
.gbed-scroll { padding:1.25rem 1.5rem 1.5rem; max-height:calc(80vh - 120px); overflow-y:auto; }
.gbed-section { margin-bottom:1.1rem; }
.gbed-section-title { font-family:'DINBlack',sans-serif; font-size:.65rem; text-transform:uppercase; letter-spacing:.1em; color:var(--muted); border-bottom:1px solid var(--border); padding-bottom:.35rem; margin-bottom:.65rem; }
.gbed-row { display:flex; gap:.6rem; margin-bottom:.55rem; }
.gbed-row > * { flex:1; min-width:0; }
.gbed-field-group { margin-bottom:.55rem; }
.gbed-label { display:block; font-size:.73rem; color:var(--muted); margin-bottom:.2rem; }
.gbed-field { display:block; width:100%; padding:.45rem .7rem; border:1.5px solid var(--border); border-radius:6px; font-family:'DINRegular',Arial,sans-serif; font-size:.87rem; color:var(--ink); outline:none; transition:border-color .14s; }
.gbed-field:focus { border-color:var(--accent); }
.gbed-footer { padding:.9rem 1.5rem; background:#f8f8fc; border-top:1px solid var(--border); display:flex; gap:.5rem; justify-content:flex-end; align-items:center; }
.gbed-footer .gbed-err { margin:0; flex:1; }
.btn-adm { font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase; letter-spacing:.07em; padding:.38rem .9rem; border-radius:6px; cursor:pointer; border:1.5px solid transparent; white-space:nowrap; transition:opacity .13s; }
.btn-adm-primary { background:var(--accent); color:#fff; border-color:var(--accent); }
.btn-adm-primary:hover { opacity:.88; }
.btn-adm-secondary { background:transparent; color:var(--ink-lt); border-color:var(--border); }
.btn-adm-secondary:hover { background:#f0f0f8; }
/* Add-game card (admin only) — matches game card shape: grey image area + white body */
.gb-add-game-card { display:none; border-radius:12px; border:2px dashed rgba(59,59,170,.22); background:#fff; cursor:pointer; transition:border-color .15s, box-shadow .15s, transform .15s; overflow:hidden; box-shadow:0 2px 14px rgba(0,0,0,.06); }
body.gb-admin-mode .gb-add-game-card { display:block; }
.gb-add-game-card:hover { border-color:var(--accent); box-shadow:0 6px 24px rgba(0,0,0,.12); transform:translateY(-3px); }
.gb-add-game-card-inner { position:relative; width:100%; aspect-ratio:1; display:flex; align-items:center; justify-content:center; font-size:3rem; color:rgba(59,59,170,.28); line-height:1; transition:color .15s, background .15s; background:linear-gradient(135deg,#e8e8f2,#d8d8ec); overflow:hidden; }
.gb-add-game-card:hover .gb-add-game-card-inner { color:var(--accent); background:linear-gradient(135deg,#e0e0f0,#cecee8); }
.gb-agc-plus { pointer-events:none; }
.gb-agc-img-btn { position:absolute; top:.4rem; right:.4rem; z-index:2; }
.gb-agc-preview { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; z-index:1; }
.gb-add-game-card-label { padding:.75rem .9rem 1rem; }
.gb-agc-name { outline:none; font-family:'DINBlack',sans-serif; font-size:.95rem; color:rgba(59,59,170,.45); cursor:text; display:block; min-width:2ch; transition:color .15s; }
.gb-add-game-card:hover .gb-agc-name { color:var(--accent); }
.gb-agc-name:focus { color:var(--accent); border-bottom:1.5px solid var(--accent); }
</style>
</head>
<body>

<!-- ── Sticky top wrapper ── -->
<div class="gb-top-wrap">

  <?php if (!isset($_gbPublicBase)): ?>
  <!-- Admin bar: editing tools only, hidden in public mode -->
  <div class="gb-admin-bar">
    <span class="gb-admin-label">GameBoard Admin</span>
    <div class="gb-admin-tools">
      <span class="gb-meta" id="gbMeta"><?= h($_pubName ?: '') ?></span>
      <button class="btn-add-game" id="gbSavePageBtn" onclick="gbSavePage()" style="display:none">Save</button>
      <div class="user-menu-wrap">
        <button class="btn-user-icon" id="gbUserBtn" onclick="toggleGbMenu(event)" title="Menu">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
          </svg>
        </button>
        <div class="user-menu" id="gbMenu">
          <button class="user-menu-item" id="gbMenuProfile" onclick="gbMenuAction('profile')">Profile</button>
          <button class="user-menu-item" onclick="gbMenuAction('settings')">Settings</button>
          <hr class="user-menu-divider" />
          <button class="user-menu-item" onclick="gbMenuAction('fetch')">Fetch</button>
          <button class="user-menu-item" id="gbMenuPublish" onclick="gbMenuAction('publish')" style="display:none">🌐 Publish Site</button>
          <hr class="user-menu-divider" />
          <button class="user-menu-item" id="gbMenuSignInOut" onclick="gbMenuAction('signout')">Sign In</button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Page banner: logo + nav, always shown (public + admin) -->
  <header class="gb-banner">
    <div class="gb-banner-left">
      <?php if ($_pubLogo || !isset($_gbPublicBase)): ?>
      <div class="gb-logo-wrap" id="gbLogoWrap"
           ondragover="gbLogoDragOver(event)" ondragleave="gbLogoDragLeave(event)" ondrop="gbLogoDrop(event)">
        <?php if ($_pubLogo): ?>
        <img src="<?= h($_pubLogo) ?>" alt="<?= h($_pubName) ?>" class="gb-logo-img" id="gbLogoImg" />
        <?php else: ?>
        <div class="gb-logo-ph" id="gbLogoImg">Your Logo</div>
        <?php endif; ?>
        <button class="gb-img-btn gb-logo-edit-btn" onclick="gbPickLogoImage(event)" title="Change logo"><?= $_imgBtnIcon ?></button>
      </div>
      <?php endif; ?>
      <?php if ($_pubName || !isset($_gbPublicBase)): ?>
      <span class="gb-banner-name<?= !isset($_gbPublicBase) ? ' gb-banner-name-editable' : '' ?>"
            <?php if (!isset($_gbPublicBase)): ?>
            contenteditable="false"
            data-setting="company"
            data-placeholder="Company Name"
            onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}"
            onblur="gbSaveFooterField(this);document.getElementById('gbMeta') && (document.getElementById('gbMeta').textContent=(this.textContent.trim()||<?= htmlspecialchars(json_encode($_pubName ?: 'Games'), ENT_QUOTES) ?>)+' · GameBoard')"
            <?php endif; ?>
            ><?= h($_pubName) ?></span>
      <?php endif; ?>
    </div>
    <nav class="gb-banner-nav" id="gbBannerNav">
      <!-- Nav links populated from Settings in a future update -->
    </nav>
    <?php if (!isset($_gbPublicBase)): ?>
    <div class="gb-banner-right">
      <button class="gb-banner-color-btn" id="gbBannerColorBtn" onclick="document.getElementById('gbBannerColorInput').click()" title="Banner color">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg>
      </button>
      <input type="color" id="gbBannerColorInput" value="<?= h($_bannerColor) ?>"
             style="position:absolute;opacity:0;pointer-events:none;width:0;height:0"
             oninput="gbApplyBannerColor(this.value)" onchange="gbSaveBannerColor(this.value)" />
    </div>
    <?php endif; ?>
  </header>

</div><!-- /.gb-top-wrap -->

<!-- ── Carousel ── -->
<div class="cat-carousel" id="catCarousel">
<?php if (!empty($_carousel)): ?>
  <?php foreach ($_carousel as $_ci => $_cUrl): ?>
  <div class="cat-slide<?= $_ci === 0 ? ' active' : '' ?>" data-url="<?= h($_cUrl) ?>" style="background-image:url('<?= h($_cUrl) ?>')">
    <?php if (!isset($_gbPublicBase)): ?>
    <button class="cat-slide-edit-btn" onclick="carouselPickImage(this.closest('.cat-slide'))" title="Change image"><?= $_imgBtnIcon ?></button>
    <button class="cat-slide-del-btn"  onclick="carouselRemoveSlide(this.closest('.cat-slide'))" title="Remove">×</button>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
  <button class="cat-slide-btn cat-slide-prev" onclick="carouselPrev()" title="Previous">&#8249;</button>
  <button class="cat-slide-btn cat-slide-next" onclick="carouselNext()" title="Next">&#8250;</button>
  <div class="cat-slide-dots" id="catSlideDots">
    <?php foreach ($_carousel as $_ci => $_cUrl): ?>
    <button class="cat-slide-dot<?= $_ci === 0 ? ' active' : '' ?>" onclick="carouselGo(<?= $_ci ?>)"></button>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="cat-carousel-ph" id="catCarouselPh">
    <svg class="cat-carousel-ph-icon" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.6)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
    <div class="cat-carousel-ph-text">Drag &amp; drop images or click + to add</div>
  </div>
<?php endif; ?>
</div>

<?php if ($_about): ?>
<div class="cat-about"><p class="cat-about-text"><?= h($_about) ?></p></div>
<?php endif; ?>

<!-- ── Games section ── -->
<div class="cat-section-head">
  <span class="cat-section-title">Our Games</span>
  <?php if (!empty($_games)): ?><span class="cat-section-count"><?= count($_games) ?> published</span><?php endif; ?>
</div>

<!-- ── Game grid ── -->
<div class="cat-grid" id="catGrid">
<?php if (empty($_games)): ?>
  <div class="cat-empty">
    <div class="cat-empty-icon"><img src="<?= h($_appRoot) ?>images/gb_logo.png" alt="GameBoard" style="width:64px;height:64px;border-radius:14px;opacity:.5;" /></div>
    <div class="cat-empty-title">No published games yet</div>
    <div class="cat-empty-body">Sign in and use Fetch to sync your Games sheet, then mark games as Published to display them here.</div>
  </div>
<?php else:
  foreach ($_games as $g):
    $gName    = trim($g['Name']       ?? '');
    $gSummary = trim($g['Tagline'] ?? $g['Tag Line'] ?? $g['Summary'] ?? '');
    $gCount   = trim($g['Count']      ?? '');
    $gDur     = trim($g['Duration']   ?? '');
    $gCover   = _gb_img(trim($g['Cover URL'] ?? $g['Image URL'] ?? $g['Image'] ?? ''));
    $gSlug    = _gb_slug($gName);
    if (!$gName) continue;
?>
  <div class="cat-card-wrap">
  <a class="cat-card" href="<?= h($_catUrl . $gSlug) ?>">
    <div class="cat-img-wrap" data-game="<?= h($gName) ?>"
      ondragover="catDragOver(event,this)" ondragleave="catDragLeave(event,this)" ondrop="catDrop(event,this)">
      <?php if ($gCover): ?>
      <img class="cat-card-img" src="<?= h($gCover) ?>" alt="<?= h($gName) ?>" loading="lazy" />
      <?php else: ?>
      <div class="cat-card-ph"><img src="<?= h($_appRoot) ?>images/gb_logo.png" alt="" style="width:64px;height:64px;border-radius:14px;opacity:.35;" /></div>
      <?php endif; ?>
      <button class="gb-img-btn cat-img-upload-btn" title="Change image"
        onclick="catPickImage(event,this.closest('.cat-img-wrap'))"><?= $_imgBtnIcon ?></button>
    </div>
    <div class="cat-card-body">
      <div class="cat-card-name"><?= h($gName) ?></div>
      <?php if ($gSummary): ?><div class="cat-card-summary"><?= h($gSummary) ?></div><?php endif; ?>
      <div class="cat-card-meta">
        <?php if ($gCount): ?><span class="cat-card-stat">👥 <?= h($gCount) ?></span><?php endif; ?>
        <?php if ($gDur):   ?><span class="cat-card-stat">⏱ <?= h($gDur) ?></span><?php endif; ?>
      </div>
    </div>
  </a>
  <button class="cat-card-del-btn" title="Delete game"
    onclick="catDeleteGame(event,'<?= addslashes(h($gName)) ?>')">✕</button>
  </div>
<?php endforeach; endif; ?>
<?php if (!isset($_gbPublicBase)): ?>
  <div class="gb-add-game-card" id="catAddGameCard" onclick="catAddGameClick(event)">
    <div class="gb-add-game-card-inner" id="catAddGameInner">
      <span class="gb-agc-plus">+</span>
      <button class="gb-img-btn gb-agc-img-btn" title="Set cover image"
        onclick="catAddGamePickImg(event)"><?= $_imgBtnIcon ?></button>
    </div>
    <div class="gb-add-game-card-label">
      <div class="gb-agc-name" id="catAddGameName" contenteditable="true"
        onkeydown="catAddGameKey(event)"
        onfocus="catAddGameNameFocus(this)"
        onblur="catAddGameNameBlur(this)">New Game</div>
    </div>
  </div>
<?php endif; ?>
</div>

<?php _gb_footer(); ?>


<!-- ── Publish Site dialog ── -->
<div class="gb-overlay" id="gbPublishOverlay" onclick="if(event.target===this)closePublishDialog()">
  <div class="gb-dialog" onclick="event.stopPropagation()" style="max-width:480px">
    <button class="gb-dialog-close" onclick="closePublishDialog()">×</button>
    <div class="gb-dialog-title">🌐 Publish Site</div>
    <p style="font-size:.82rem;color:var(--ink-lt,#666);margin-bottom:1.2rem;line-height:1.5">
      Your public GameBoard is live. Anyone with this link can browse your published games — no sheet ID exposed.
    </p>
    <div class="gb-field-group" style="margin-bottom:.6rem">
      <label style="font-size:.75rem;font-weight:600;color:var(--ink-lt,#888);text-transform:uppercase;letter-spacing:.05em">Catalog</label>
      <div style="display:flex;gap:.4rem;align-items:center">
        <input class="gb-field" id="gbPubCatalogUrl" type="text" readonly onclick="this.select()" style="flex:1;font-size:.8rem;cursor:text" />
        <button class="gb-btn-sm" onclick="gbCopyUrl('gbPubCatalogUrl')">Copy</button>
        <a id="gbPubCatalogLink" href="#" target="_blank" class="gb-btn-sm" style="text-decoration:none">Open ↗</a>
      </div>
    </div>
    <div id="gbPubGameUrl" style="margin-bottom:1.2rem">
      <div class="gb-field-group" style="margin-bottom:.3rem">
        <label style="font-size:.75rem;font-weight:600;color:var(--ink-lt,#888);text-transform:uppercase;letter-spacing:.05em">This Game</label>
        <div style="display:flex;gap:.4rem;align-items:center">
          <input class="gb-field" id="gbPubGameUrlInput" type="text" readonly onclick="this.select()" style="flex:1;font-size:.8rem;cursor:text" />
          <button class="gb-btn-sm" onclick="gbCopyUrl('gbPubGameUrlInput')">Copy</button>
          <a id="gbPubGameUrlLink" href="#" target="_blank" class="gb-btn-sm" style="text-decoration:none">Open ↗</a>
        </div>
      </div>
    </div>
    <p style="font-size:.75rem;color:var(--ink-lt,#999);line-height:1.5">
      Re-publishing updates the same URL. Only games with status <strong>Published</strong> appear in the catalog.
    </p>
    <div style="display:flex;justify-content:flex-end;margin-top:1rem">
      <button class="gb-btn" onclick="closePublishDialog()">Done</button>
    </div>
  </div>
</div>

<!-- ── Sign-in dialog ── -->
<div class="gb-overlay" id="signInOverlay" onclick="if(event.target===this)forceCloseSignIn()">
  <div class="gb-dialog" onclick="event.stopPropagation()"
       onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitSignIn(false);}">
    <div class="gb-dialog-title" id="siTitle">Sign In</div>
    <div id="siMainPanel">
      <div class="gb-field-group"><label>Email</label><input class="gb-field" id="siEmail" type="email" placeholder="you@example.com" autocomplete="email" /></div>
      <div class="gb-field-group"><label>Password</label><input class="gb-field" id="siPassword" type="password" placeholder="••••••••" autocomplete="current-password" /></div>
    </div>
    <div id="siConfirmPanel">
      <p style="font-size:.85rem;color:var(--ink-lt);line-height:1.55;margin-bottom:.75rem;">No account found for this email. Create one now?</p>
    </div>
    <div class="gb-err" id="siErr"></div>
    <div class="gb-dialog-actions">
      <button class="btn-cancel" onclick="forceCloseSignIn()">Cancel</button>
      <button class="btn-primary" id="siBtn" onclick="submitSignIn(false)">Sign In</button>
    </div>
  </div>
</div>

<!-- ── Settings dialog ── -->
<div class="gbed-overlay" id="settingsOverlay" onclick="if(event.target===this)forceCloseSettings()">
  <div class="gbed-dialog" onclick="event.stopPropagation()"
       onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitSettings();}">
    <div class="gbed-header">
      <span class="gbed-title">Settings</span>
      <button class="gbed-close" onclick="forceCloseSettings()">✕</button>
    </div>
    <div class="gbed-body"><div class="gbed-scroll">

      <div class="gbed-section">
        <div class="gbed-section-title">Company</div>
        <div class="gbed-field-group"><label class="gbed-label">Company Name</label><input class="gbed-field" id="setCompany" type="text" placeholder="Acme Games LLC" /></div>
        <div class="gbed-field-group"><label class="gbed-label">Logo URL <span style="font-size:.7rem;color:var(--muted)">(image link)</span></label><input class="gbed-field" id="setLogo" type="url" placeholder="https://…/logo.png" /></div>
        <div class="gbed-field-group"><label class="gbed-label">Tagline <span style="font-size:.7rem;color:var(--muted)">(short description shown in About)</span></label><input class="gbed-field" id="setTagline" type="text" placeholder="Independent board game publisher" /></div>
        <div class="gbed-field-group"><label class="gbed-label">About</label><textarea class="gbed-field" id="setAbout" rows="3" placeholder="A short paragraph about your company or games…" style="resize:vertical"></textarea></div>
      </div>

      <div class="gbed-section">
        <div class="gbed-section-title">Images <span style="font-size:.68rem;font-weight:400;color:var(--muted)">(paste image URLs)</span></div>
        <?php for ($i = 1; $i <= 6; $i++): ?>
        <div class="gbed-field-group"><label class="gbed-label">Image <?= $i ?></label><input class="gbed-field" id="setCarousel<?= $i ?>" type="url" placeholder="https://…/image.jpg" /></div>
        <?php endfor; ?>
      </div>

      <div class="gbed-section">
        <div class="gbed-section-title">Social Links</div>
        <div class="gbed-row">
          <div class="gbed-field-group"><label class="gbed-label">Instagram</label><input class="gbed-field" id="setInsta" type="url" placeholder="https://instagram.com/…" /></div>
          <div class="gbed-field-group"><label class="gbed-label">X / Twitter</label><input class="gbed-field" id="setTwitter" type="url" placeholder="https://x.com/…" /></div>
        </div>
        <div class="gbed-row">
          <div class="gbed-field-group"><label class="gbed-label">Facebook</label><input class="gbed-field" id="setFb" type="url" placeholder="https://facebook.com/…" /></div>
          <div class="gbed-field-group"><label class="gbed-label">YouTube</label><input class="gbed-field" id="setYt" type="url" placeholder="https://youtube.com/…" /></div>
        </div>
        <div class="gbed-field-group"><label class="gbed-label">BoardGameGeek</label><input class="gbed-field" id="setBgg" type="url" placeholder="https://boardgamegeek.com/…" /></div>
      </div>

      <div class="gbed-section">
        <div class="gbed-section-title">Display</div>
        <div class="gbed-row">
          <div class="gbed-field-group">
            <label class="gbed-label">Banner Color</label>
            <input class="gbed-field" id="setBannerColor" type="color" style="height:2.4rem;padding:.2rem .3rem;cursor:pointer" />
          </div>
          <div class="gbed-field-group">
            <label class="gbed-label">GameBoard Template</label>
            <select class="gbed-field" id="setTemplate">
              <option value="">Classic (default)</option>
              <option value="opensea">OpenSea</option>
            </select>
          </div>
        </div>
        <div class="gbed-field-group"><label class="gbed-label">Copyright Line <span style="font-size:.7rem;color:var(--muted)">(footer left)</span></label><input class="gbed-field" id="setCopyright" type="text" placeholder="© 2026 Acme Games LLC" /></div>
        <div class="gbed-field-group"><label class="gbed-label">Footer Note <span style="font-size:.7rem;color:var(--muted)">(second line)</span></label><input class="gbed-field" id="setFooterNote" type="text" placeholder="contact@example.com · All rights reserved" /></div>
      </div>

      <div class="gbed-section">
        <div class="gbed-section-title">Newsletter Subscribe</div>
        <div class="gbed-field-group"><label class="gbed-label">Heading <span style="font-size:.7rem;color:var(--muted)">(defaults to tagline)</span></label><input class="gbed-field" id="setSubHeading" type="text" placeholder="Stay in the loop." /></div>
        <div class="gbed-field-group"><label class="gbed-label">Description</label><input class="gbed-field" id="setSubText" type="text" placeholder="Join our newsletter for exciting announcements and special offers!" /></div>
      </div>

    </div></div>
    <div class="gbed-footer">
      <div class="gb-err gbed-err" id="setErr"></div>
      <button class="btn-adm btn-adm-secondary" onclick="forceCloseSettings()">Cancel</button>
      <button class="btn-adm btn-adm-primary" id="setBtn" onclick="submitSettings()">Save</button>
    </div>
  </div>
</div>

<!-- ── Add Game dialog ── -->
<div class="gbed-overlay" id="addGameOverlay" onclick="if(event.target===this)forceCloseAddGame()">
  <div class="gbed-dialog" onclick="event.stopPropagation()" onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitAddGame();}">
    <div class="gbed-header">
      <span class="gbed-title">New Game</span>
      <button class="gbed-close" onclick="forceCloseAddGame()">✕</button>
    </div>
    <div class="gbed-body">
    <div class="gbed-scroll">
      <div class="gbed-section">
        <div class="gbed-section-title">Game Info</div>
        <div class="gbed-field-group">
          <label class="gbed-label">Game Name <span style="color:#c00">*</span></label>
          <input class="gbed-field" id="agName" type="text" placeholder="e.g. Lair" />
        </div>
        <div class="gbed-field-group">
          <label class="gbed-label">Tagline</label>
          <input class="gbed-field" id="agTagline" type="text" placeholder="One-line description" />
        </div>
        <div class="gbed-field-group">
          <label class="gbed-label">Description</label>
          <textarea class="gbed-field" id="agDescription" rows="3" style="resize:vertical"></textarea>
        </div>
        <div class="gbed-row">
          <div class="gbed-field-group">
            <label class="gbed-label">Status</label>
            <select class="gbed-field" id="agStatus">
              <option value="">—</option>
              <option value="Concept">Concept</option>
              <option value="Developing">Developing</option>
              <option value="Playtesting">Playtesting</option>
              <option value="Pitching">Pitching</option>
              <option value="Signed">Signed</option>
              <option value="Published">Published</option>
              <option value="Shelved">Shelved</option>
            </select>
          </div>
          <div class="gbed-field-group">
            <label class="gbed-label">Availability</label>
            <input class="gbed-field" id="agAvailability" type="text" placeholder="e.g. Kickstarter" />
          </div>
        </div>
        <div class="gbed-row">
          <div class="gbed-field-group">
            <label class="gbed-label">Player Count</label>
            <input class="gbed-field" id="agCount" type="text" placeholder="e.g. 2–4" />
          </div>
          <div class="gbed-field-group">
            <label class="gbed-label">Play Time</label>
            <input class="gbed-field" id="agDuration" type="text" placeholder="e.g. 30–60 min" />
          </div>
        </div>
        <div class="gbed-row">
          <div class="gbed-field-group">
            <label class="gbed-label">Designer 1</label>
            <input class="gbed-field" id="agDesigner1" type="text" />
          </div>
          <div class="gbed-field-group">
            <label class="gbed-label">Designer 2</label>
            <input class="gbed-field" id="agDesigner2" type="text" />
          </div>
        </div>
        <div class="gbed-row">
          <div class="gbed-field-group">
            <label class="gbed-label">Designer 3</label>
            <input class="gbed-field" id="agDesigner3" type="text" />
          </div>
          <div class="gbed-field-group">
            <label class="gbed-label">Designer 4</label>
            <input class="gbed-field" id="agDesigner4" type="text" />
          </div>
        </div>
      </div>
      <div class="gbed-section">
        <div class="gbed-section-title">Dates</div>
        <div class="gbed-row">
          <div class="gbed-field-group">
            <label class="gbed-label">Date Started</label>
            <input class="gbed-field" id="agDateStarted" type="text" placeholder="e.g. Jan 2024" />
          </div>
          <div class="gbed-field-group">
            <label class="gbed-label">Date Signed</label>
            <input class="gbed-field" id="agDateSigned" type="text" placeholder="e.g. Mar 2024" />
          </div>
        </div>
        <div class="gbed-field-group" style="max-width:50%;padding-right:.3rem">
          <label class="gbed-label">Date Published</label>
          <input class="gbed-field" id="agDatePublished" type="text" placeholder="e.g. Nov 2024" />
        </div>
      </div>
      <div class="gbed-section">
        <div class="gbed-section-title">Images &amp; Media</div>
        <div class="gbed-field-group">
          <label class="gbed-label">Cover URL (background image)</label>
          <input class="gbed-field" id="agCoverUrl" type="url" placeholder="https://…" />
        </div>
        <div class="gbed-field-group">
          <label class="gbed-label">Image URL (product photo)</label>
          <input class="gbed-field" id="agImageUrl" type="url" placeholder="https://…" />
        </div>
        <div class="gbed-field-group">
          <label class="gbed-label">Video URL (YouTube)</label>
          <input class="gbed-field" id="agVideoUrl" type="url" placeholder="https://youtube.com/watch?v=…" />
        </div>
      </div>
      <div class="gbed-section">
        <div class="gbed-section-title">Links</div>
        <div class="gbed-field-group">
          <label class="gbed-label">Page URL</label>
          <input class="gbed-field" id="agPageUrl" type="url" placeholder="https://…" />
        </div>
        <div class="gbed-field-group">
          <label class="gbed-label">Sellsheet URL</label>
          <input class="gbed-field" id="agSellsheet" type="url" placeholder="https://…" />
        </div>
        <div class="gbed-field-group">
          <label class="gbed-label">Rules URL</label>
          <input class="gbed-field" id="agRulesUrl" type="url" placeholder="https://…" />
        </div>
        <div class="gbed-row">
          <div class="gbed-field-group">
            <label class="gbed-label">Play Online URL</label>
            <input class="gbed-field" id="agPlayUrl" type="url" placeholder="https://…" />
          </div>
          <div class="gbed-field-group">
            <label class="gbed-label">Print &amp; Play URL</label>
            <input class="gbed-field" id="agPrintUrl" type="url" placeholder="https://…" />
          </div>
        </div>
      </div>
      <div class="gbed-err" id="agErr"></div>
    </div>
    </div>
    <div class="gbed-footer">
      <button class="btn-adm btn-adm-secondary" style="color:var(--ink-lt);border-color:var(--border)" onclick="forceCloseAddGame()">Cancel</button>
      <button class="btn-adm btn-adm-primary" style="background:var(--accent);color:#fff" id="agBtn" onclick="submitAddGame()">Create Game</button>
    </div>
  </div>
</div>

<script>
var SHEET_ID   = <?= json_encode($_sheetId) ?>;
var APP_ROOT   = <?= json_encode($_appRoot) ?>;
var IMG_BTN_ICON = <?= json_encode($_imgBtnIcon) ?>;

// ── Auth state ──────────────────────────────────────────────────────────────
var _gbUser = null;
var _siPendingEmail = '', _siPendingPass = '';

(function() {
  try {
    var raw = sessionStorage.getItem('gameboard_owner_' + SHEET_ID);
    if (raw) { _gbUser = JSON.parse(raw); }
  } catch(e) {}
  _updateGbAuth();
})();

// Restore scroll position after save redirect
(function() {
  try {
    var slug = location.pathname.replace(/\/$/, '').split('/').pop();
    var key  = 'gb_scroll_' + slug;
    var y    = sessionStorage.getItem(key);
    if (y !== null) {
      sessionStorage.removeItem(key);
      var yNum = parseInt(y, 10);
      if (yNum > 0) {
        window.scrollTo(0, yNum);
      }
    }
  } catch(e) {}
})();

function _updateGbAuth() {
  var btn = document.getElementById('gbUserBtn');
  var metaEl = document.getElementById('gbMeta');
  var siOut = document.getElementById('gbMenuSignInOut');
  var profile = document.getElementById('gbMenuProfile');
  var publishBtn = document.getElementById('gbMenuPublish');
  if (_gbUser) {
    var name = (_gbUser.bio && (_gbUser.bio['name'] || _gbUser.bio['Name'])) || _gbUser.email || '';
    if (btn) { btn.classList.add('signed-in'); btn.title = 'Signed in as ' + (_gbUser.email || ''); }
    if (metaEl) metaEl.textContent = (name || <?= json_encode($_pubName ?: 'Games') ?>) + ' · GameBoard';
    if (siOut)  { siOut.textContent = 'Sign Out'; siOut.className = 'user-menu-item signout'; }
    if (profile) profile.style.display = '';
    if (publishBtn) publishBtn.style.display = '';
    var saveBtn = document.getElementById('gbSavePageBtn');
    if (saveBtn) saveBtn.style.display = '';
    document.body.classList.add('gb-admin-mode');
    document.querySelectorAll('.gb-footer-editable, .gb-banner-name-editable').forEach(function(el){ el.contentEditable = 'true'; });
  } else {
    document.querySelectorAll('.gb-footer-editable, .gb-banner-name-editable').forEach(function(el){ el.contentEditable = 'false'; });
    var saveBtn = document.getElementById('gbSavePageBtn');
    if (saveBtn) saveBtn.style.display = 'none';
    if (btn) { btn.classList.remove('signed-in'); btn.title = 'Menu'; }
    if (metaEl) metaEl.textContent = <?= json_encode($_pubName ?: 'Games') ?>;
    if (siOut)  { siOut.textContent = 'Sign In'; siOut.className = 'user-menu-item'; }
    if (profile) profile.style.display = 'none';
    if (publishBtn) publishBtn.style.display = 'none';
    document.body.classList.remove('gb-admin-mode');
  }
}

// ── Menu ────────────────────────────────────────────────────────────────────
function toggleGbMenu(e) { e.stopPropagation(); document.getElementById('gbMenu').classList.toggle('open'); }
function closeGbMenu() { document.getElementById('gbMenu').classList.remove('open'); }
document.addEventListener('click', closeGbMenu);

function gbMenuAction(action) {
  closeGbMenu();
  if (action === 'profile') {
    if (_gbUser) {
      var n = (_gbUser.bio && (_gbUser.bio['name'] || _gbUser.bio['Name'])) || '—';
      alert('Signed in as: ' + (_gbUser.email || '') + '\nName: ' + n);
    } else { openSignIn(); }
  } else if (action === 'settings') {
    openSettings();
  } else if (action === 'fetch') {
    if (!_gbUser) { openSignIn(); return; }
    gbDoFetch();
  } else if (action === 'publish') {
    if (!_gbUser) { openSignIn(); return; }
    gbPublishSite();
  } else if (action === 'signout') {
    if (_gbUser) {
      if (confirm('Sign out?')) {
        try { sessionStorage.removeItem('gameboard_owner_' + SHEET_ID); } catch(e) {}
        _gbUser = null; _updateGbAuth();
      }
    } else { openSignIn(); }
  }
}

// ── Publish Site ─────────────────────────────────────────────────────────────
function gbPublishSite() {
  var userBtn = document.getElementById('gbUserBtn');
  if (userBtn) userBtn.style.opacity = '.4';
  var fd = new FormData();
  fd.append('id', SHEET_ID);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/publishGameboard.php');
  xhr.onload = function() {
    if (userBtn) userBtn.style.opacity = '';
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = {error: 'parse error'}; }
    if (!r || r.error) { alert('Publish failed: ' + (r && r.error || 'unknown')); return; }
    openPublishDialog(r.url, r.token);
  };
  xhr.onerror = function() {
    if (userBtn) userBtn.style.opacity = '';
    alert('Publish failed — network error.');
  };
  xhr.send(fd);
}

function openPublishDialog(catalogUrl, token) {
  document.getElementById('gbPubCatalogUrl').value  = catalogUrl;
  document.getElementById('gbPubCatalogLink').href  = catalogUrl;
  // If we're on a game page, also show the game-specific URL
  var gameSlugEl = document.getElementById('gbPubGameUrl');
  if (gameSlugEl) {
    var gameName = (typeof ORIG_GAME_NAME !== 'undefined' && ORIG_GAME_NAME)
      ? ORIG_GAME_NAME.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
      : '';
    if (gameName) {
      var gameUrl = catalogUrl.replace(/\/?$/, '/') + gameName;
      document.getElementById('gbPubGameUrlInput').value = gameUrl;
      document.getElementById('gbPubGameUrlLink').href   = gameUrl;
      gameSlugEl.style.display = '';
    } else {
      gameSlugEl.style.display = 'none';
    }
  }
  document.getElementById('gbPublishOverlay').classList.add('open');
}
function closePublishDialog() { document.getElementById('gbPublishOverlay').classList.remove('open'); }
function gbCopyUrl(inputId) {
  var inp = document.getElementById(inputId);
  if (!inp) return;
  inp.select(); inp.setSelectionRange(0, 9999);
  try { document.execCommand('copy'); } catch(e) { navigator.clipboard && navigator.clipboard.writeText(inp.value); }
  var btn = inp.nextElementSibling;
  if (btn) { var old = btn.textContent; btn.textContent = 'Copied!'; setTimeout(function(){ btn.textContent = old; }, 1500); }
}

// ── Catalog card image upload (admin mode) ───────────────────────────────────
var _catImgPickWrap = null;

function catPickImage(e, wrap) {
  e.preventDefault(); e.stopPropagation();
  if (!document.body.classList.contains('gb-admin-mode')) return;
  _catImgPickWrap = wrap;
  var inp = document.getElementById('catImgFileInput');
  if (!inp) {
    inp = document.createElement('input');
    inp.type = 'file'; inp.id = 'catImgFileInput'; inp.accept = 'image/*';
    inp.style.display = 'none';
    inp.addEventListener('change', function() {
      if (inp.files && inp.files[0] && _catImgPickWrap) {
        catUploadCardImage(inp.files[0], _catImgPickWrap);
      }
      inp.value = '';
    });
    document.body.appendChild(inp);
  }
  inp.click();
}

function catDragOver(e, wrap) {
  if (!document.body.classList.contains('gb-admin-mode')) return;
  e.preventDefault(); e.stopPropagation();
  wrap.classList.add('drag-over');
}

function catDragLeave(e, wrap) {
  e.preventDefault();
  wrap.classList.remove('drag-over');
}

function catDrop(e, wrap) {
  e.preventDefault(); e.stopPropagation();
  wrap.classList.remove('drag-over');
  if (!document.body.classList.contains('gb-admin-mode')) return;
  var files = e.dataTransfer && e.dataTransfer.files;
  if (files && files[0]) catUploadCardImage(files[0], wrap);
}

function catUploadCardImage(file, wrap) {
  var gameName = wrap.dataset.game;
  if (!gameName) return;
  var btn = wrap.querySelector('.cat-img-upload-btn');
  if (btn) { btn._orig = btn.innerHTML; btn.disabled = true; }
  wrap.classList.add('img-loading');

  var formData = new FormData();
  formData.append('file', file);
  formData.append('id', SHEET_ID);

  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/uploadImage.php');
  xhr.onload = function() {
    var r; try { r = JSON.parse(xhr.responseText); } catch(ex) { r = {error:'parse error'}; }
    if (!r || r.error) {
      wrap.classList.remove('img-loading');
      alert('Upload failed: ' + (r && r.error || 'unknown'));
      if (btn) { btn.innerHTML = btn._orig || ''; btn.disabled = false; }
      return;
    }
    var imageUrl = r.url;
    var fd2 = new FormData();
    fd2.append('id',        SHEET_ID);
    fd2.append('name',      gameName);
    fd2.append('image_url', imageUrl);
    var xhr2 = new XMLHttpRequest();
    xhr2.open('POST', APP_ROOT + 'push/updateGameImage.php');
    xhr2.onload = function() {
      if (btn) { btn.innerHTML = btn._orig || ''; btn.disabled = false; }
      var r2; try { r2 = JSON.parse(xhr2.responseText); } catch(ex) { r2 = {error:'parse error'}; }
      if (!r2 || r2.error) {
        wrap.classList.remove('img-loading');
        alert('Sheet update failed: ' + (r2 && r2.error || 'unknown'));
        return;
      }
      // Swap image in DOM immediately; remove spinner once new image loads
      var existing = wrap.querySelector('.cat-card-img');
      var ph = wrap.querySelector('.cat-card-ph');
      var targetImg;
      if (existing) {
        targetImg = existing;
        existing.src = imageUrl;
      } else {
        targetImg = document.createElement('img');
        targetImg.className = 'cat-card-img';
        targetImg.src = imageUrl;
        targetImg.alt = gameName;
        targetImg.loading = 'lazy';
        if (ph) ph.parentNode.replaceChild(targetImg, ph);
        else wrap.insertBefore(targetImg, wrap.firstChild);
      }
      targetImg.onload  = function() { wrap.classList.remove('img-loading'); };
      targetImg.onerror = function() { wrap.classList.remove('img-loading'); };
    };
    xhr2.onerror = function() { wrap.classList.remove('img-loading'); if (btn) { btn.innerHTML = btn._orig || ''; btn.disabled = false; } };
    xhr2.send(fd2);
  };
  xhr.onerror = function() { wrap.classList.remove('img-loading'); if (btn) { btn.innerHTML = btn._orig || ''; btn.disabled = false; } };
  xhr.send(formData);
}

// ── Logo image upload ─────────────────────────────────────────────────────────
function gbPickLogoImage(e) {
  e.preventDefault(); e.stopPropagation();
  if (!document.body.classList.contains('gb-admin-mode')) return;
  var inp = document.getElementById('gbLogoFileInput');
  if (!inp) {
    inp = document.createElement('input');
    inp.type = 'file'; inp.id = 'gbLogoFileInput'; inp.accept = 'image/*'; inp.style.display = 'none';
    inp.addEventListener('change', function() {
      if (inp.files && inp.files[0]) gbUploadLogo(inp.files[0]);
      inp.value = '';
    });
    document.body.appendChild(inp);
  }
  inp.click();
}
function gbLogoDragOver(e) {
  if (!document.body.classList.contains('gb-admin-mode')) return;
  e.preventDefault(); e.stopPropagation();
  var wrap = document.getElementById('gbLogoWrap');
  if (wrap) wrap.classList.add('gb-drag-over');
}
function gbLogoDragLeave(e) {
  var wrap = document.getElementById('gbLogoWrap');
  if (wrap) wrap.classList.remove('gb-drag-over');
}
function gbLogoDrop(e) {
  e.preventDefault(); e.stopPropagation();
  var wrap = document.getElementById('gbLogoWrap');
  if (wrap) wrap.classList.remove('gb-drag-over');
  if (!document.body.classList.contains('gb-admin-mode')) return;
  var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
  if (f && f.type.startsWith('image/')) gbUploadLogo(f);
}
function gbUploadLogo(file) {
  var btn = document.querySelector('.gb-logo-edit-btn');
  if (btn) { btn._orig = btn.innerHTML; btn.textContent = '⏳'; btn.disabled = true; }
  var fd = new FormData();
  fd.append('file', file); fd.append('id', SHEET_ID);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/uploadImage.php');
  xhr.onload = function() {
    var r; try { r = JSON.parse(xhr.responseText); } catch(ex) { r = {error:'parse error'}; }
    if (!r || r.error) {
      alert('Upload failed: ' + (r && r.error || 'unknown'));
      if (btn) { btn.innerHTML = btn._orig || IMG_BTN_ICON; btn.disabled = false; }
      return;
    }
    // Save logo URL to settings
    var fd2 = new FormData();
    fd2.append('id', SHEET_ID); fd2.append('logo', r.url);
    var xhr2 = new XMLHttpRequest();
    xhr2.open('POST', APP_ROOT + 'push/tmSaveSettings.php');
    xhr2.onload = function() {
      if (btn) { btn.innerHTML = btn._orig || IMG_BTN_ICON; btn.disabled = false; }
      // Swap placeholder/image in DOM
      var existing = document.getElementById('gbLogoImg');
      if (existing && existing.tagName === 'IMG') {
        existing.src = r.url;
      } else if (existing) {
        var img = document.createElement('img');
        img.src = r.url; img.className = 'gb-logo-img'; img.id = 'gbLogoImg';
        img.alt = document.title;
        existing.parentNode.replaceChild(img, existing);
      }
    };
    xhr2.onerror = function() {
      if (btn) { btn.innerHTML = btn._orig || IMG_BTN_ICON; btn.disabled = false; }
    };
    xhr2.send(fd2);
  };
  xhr.onerror = function() {
    if (btn) { btn.innerHTML = btn._orig || IMG_BTN_ICON; btn.disabled = false; }
    alert('Upload failed — network error.');
  };
  xhr.send(fd);
}

// ── Footer inline edit + page Save ───────────────────────────────────────────
function gbSaveFooterField(el) {
  if (!document.body.classList.contains('gb-admin-mode')) return;
  var key = el.dataset.setting;
  var val = el.textContent.trim();
  if (!key) return;
  var fd = new FormData();
  fd.append('id', SHEET_ID);
  fd.append(key, val);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/tmSaveSettings.php');
  xhr.onload = function() {
    var r = {}; try { r = JSON.parse(xhr.responseText); } catch(e) {}
    console.log('tmSaveSettings response:', r);
    if (!r.ok) { gbShowSaveToast('Save failed', true); return; }
    if (!r.sheetOk) {
      gbShowSaveToast('Saved locally — sheet sync failed', true);
      if (r.sheetErr) console.warn('Sheet sync error:', r.sheetErr);
    } else {
      gbShowSaveToast('Saved', false);
    }
  };
  xhr.onerror = function() { gbShowSaveToast('Save failed', true); };
  xhr.send(fd);
}
function gbShowSaveToast(msg, isErr) {
  var t = document.getElementById('gbSaveToast');
  if (!t) return;
  t.textContent = msg;
  t.style.background = isErr ? '#c0392b' : '#27ae60';
  t.classList.add('visible');
  clearTimeout(t._tid);
  t._tid = setTimeout(function(){ t.classList.remove('visible'); }, 1800);
}

function gbSavePage() {
  if (!document.body.classList.contains('gb-admin-mode')) return;
  var btn = document.getElementById('gbSavePageBtn');
  if (btn) { btn.textContent = 'Saving…'; btn.disabled = true; }
  var fd = new FormData();
  fd.append('id', SHEET_ID);
  document.querySelectorAll('.gb-footer-editable[data-setting]').forEach(function(el) {
    fd.append(el.dataset.setting, el.textContent.trim());
  });
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/tmSaveSettings.php');
  xhr.onload = function() {
    if (btn) { btn.textContent = 'Saved ✓'; btn.disabled = false; setTimeout(function(){ btn.textContent = 'Save'; }, 1800); }
  };
  xhr.onerror = function() {
    if (btn) { btn.textContent = 'Save'; btn.disabled = false; }
    alert('Save failed — network error.');
  };
  xhr.send(fd);
}

// ── Social link editor ───────────────────────────────────────────────────────
var _gbSocialFieldMap = { instagram:'setInsta', twitter:'setTwitter', facebook:'setFb', youtube:'setYt', bgg_url:'setBgg' };
function gbClickSocial(btn) {
  if (!document.body.classList.contains('gb-admin-mode')) {
    var url = btn.dataset.url;
    if (url) window.open(url, '_blank', 'noopener');
    return;
  }
  // Admin: open Settings dialog focused on the matching field
  openSettings(_gbSocialFieldMap[btn.dataset.key] || null);
}

// ── Banner color ──────────────────────────────────────────────────────────────
function gbApplyBannerColor(hex) {
  document.documentElement.style.setProperty('--banner-bg', hex);
}
function gbSaveBannerColor(hex) {
  gbApplyBannerColor(hex);
  var fd = new FormData();
  fd.append('id', SHEET_ID); fd.append('banner_color', hex);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/tmSaveSettings.php');
  xhr.send(fd);
}

// ── Carousel ─────────────────────────────────────────────────────────────────
var _carouselCur = 0, _carouselTimer = null;
function _carouselSlides() { return document.querySelectorAll('#catCarousel .cat-slide'); }
function _carouselDots()   { return document.querySelectorAll('#catSlideDots .cat-slide-dot'); }

function _carouselGo(n) {
  var slides = _carouselSlides(), dots = _carouselDots();
  if (!slides.length) return;
  slides[_carouselCur].classList.remove('active');
  if (dots[_carouselCur]) dots[_carouselCur].classList.remove('active');
  _carouselCur = ((n % slides.length) + slides.length) % slides.length;
  slides[_carouselCur].classList.add('active');
  if (dots[_carouselCur]) dots[_carouselCur].classList.add('active');
}
function _carouselStart() {
  clearInterval(_carouselTimer);
  if (_carouselSlides().length > 1) _carouselTimer = setInterval(function(){ _carouselGo(_carouselCur + 1); }, 5000);
}
function _carouselRebuildDots() {
  var box = document.getElementById('catSlideDots');
  if (!box) return;
  var slides = _carouselSlides();
  box.innerHTML = '';
  slides.forEach(function(_, i) {
    var b = document.createElement('button');
    b.className = 'cat-slide-dot' + (i === _carouselCur ? ' active' : '');
    b.onclick = function() { carouselGo(i); };
    box.appendChild(b);
  });
}
window.carouselPrev = function() { _carouselGo(_carouselCur - 1); _carouselStart(); };
window.carouselNext = function() { _carouselGo(_carouselCur + 1); _carouselStart(); };
window.carouselGo   = function(n) { _carouselGo(n); _carouselStart(); };

// Save all slide URLs to settings (slots 1–6)
function carouselSaveAll() {
  var urls = Array.from(_carouselSlides()).map(function(s){ return s.dataset.url || ''; });
  var fd = new FormData(); fd.append('id', SHEET_ID);
  for (var i = 0; i < 6; i++) fd.append('carousel_' + (i + 1), urls[i] || '');
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/tmSaveSettings.php');
  xhr.onload = function() {
    var ok = false; try { ok = JSON.parse(xhr.responseText).ok; } catch(e) {}
    gbShowSaveToast(ok ? 'Saved' : 'Save failed', !ok);
  };
  xhr.onerror = function() { gbShowSaveToast('Save failed', true); };
  xhr.send(fd);
}

// Upload a file, then call callback(url)
function _carouselUpload(file, loadingEl, callback) {
  if (!file || !file.type.startsWith('image/')) return;
  // Show spinner on the target element (slide or carousel container)
  var spinner = null;
  if (loadingEl) {
    spinner = document.createElement('div');
    spinner.className = 'cat-slide-loading';
    loadingEl.appendChild(spinner);
  }
  var fd = new FormData(); fd.append('file', file); fd.append('id', SHEET_ID);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/uploadImage.php');
  xhr.onload = function() {
    if (spinner) spinner.remove();
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = {}; }
    if (r && r.url) { callback(r.url); }
    else { gbShowSaveToast('Upload failed', true); }
  };
  xhr.onerror = function() { if (spinner) spinner.remove(); gbShowSaveToast('Upload failed', true); };
  xhr.send(fd);
}

// Replace image on an existing slide
window.carouselPickImage = function(slideEl) {
  if (!document.body.classList.contains('gb-admin-mode')) return;
  var inp = document.createElement('input');
  inp.type = 'file'; inp.accept = 'image/*';
  inp.onchange = function() {
    if (!inp.files || !inp.files[0]) return;
    _carouselUpload(inp.files[0], slideEl, function(url) {
      slideEl.dataset.url = url;
      slideEl.style.backgroundImage = "url('" + url + "')";
      carouselSaveAll();
    });
  };
  inp.click();
};

// Add a new slide
window.carouselAddSlide = function() {
  if (!document.body.classList.contains('gb-admin-mode')) return;
  if (_carouselSlides().length >= 6) { gbShowSaveToast('Max 6 images', false); return; }
  var inp = document.createElement('input');
  inp.type = 'file'; inp.accept = 'image/*';
  inp.onchange = function() {
    if (!inp.files || !inp.files[0]) return;
    var carousel = document.getElementById('catCarousel');
    _carouselUpload(inp.files[0], carousel, function(url) { _carouselInsertSlide(url); });
  };
  inp.click();
};
window.carouselDropAdd = function(e) {
  e.preventDefault();
  if (!document.body.classList.contains('gb-admin-mode')) return;
  if (_carouselSlides().length >= 6) return;
  var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
  if (f) {
    var carousel = document.getElementById('catCarousel');
    _carouselUpload(f, carousel, function(url) { _carouselInsertSlide(url); });
  }
};
function _carouselInsertSlide(url) {
  var carousel = document.getElementById('catCarousel');
  var addTile  = document.getElementById('catSlideAdd');
  var ph       = document.getElementById('catCarouselPh');
  if (ph) { ph.remove(); }
  var div = document.createElement('div');
  div.className = 'cat-slide'; div.dataset.url = url;
  div.style.backgroundImage = "url('" + url + "')";
  var editBtn = document.createElement('button');
  editBtn.className = 'cat-slide-edit-btn'; editBtn.title = 'Change image';
  editBtn.innerHTML = document.getElementById('IMG_BTN_ICON') ? document.getElementById('IMG_BTN_ICON').innerHTML : '🖼';
  editBtn.onclick = function() { carouselPickImage(div); };
  var delBtn = document.createElement('button');
  delBtn.className = 'cat-slide-del-btn'; delBtn.title = 'Remove'; delBtn.textContent = '×';
  delBtn.onclick = function() { carouselRemoveSlide(div); };
  div.appendChild(editBtn); div.appendChild(delBtn);
  // Insert before the add tile (or prev/next buttons)
  var insertBefore = addTile || carousel.querySelector('.cat-slide-btn');
  carousel.insertBefore(div, insertBefore);
  // Show dots bar if needed
  var dotsBar = document.getElementById('catSlideDots');
  if (!dotsBar) {
    dotsBar = document.createElement('div'); dotsBar.id = 'catSlideDots'; dotsBar.className = 'cat-slide-dots';
    carousel.insertBefore(dotsBar, addTile);
  }
  // Show prev/next if not already there
  if (!carousel.querySelector('.cat-slide-prev')) {
    var p = document.createElement('button'); p.className = 'cat-slide-btn cat-slide-prev'; p.innerHTML = '&#8249;'; p.onclick = function(){ carouselPrev(); };
    var n = document.createElement('button'); n.className = 'cat-slide-btn cat-slide-next'; n.innerHTML = '&#8250;'; n.onclick = function(){ carouselNext(); };
    carousel.insertBefore(p, dotsBar); carousel.insertBefore(n, dotsBar);
  }
  _carouselCur = _carouselSlides().length - 1;
  _carouselRebuildDots();
  _carouselGo(_carouselCur);
  _carouselStart();
  carouselSaveAll();
  if (_carouselSlides().length >= 6) document.getElementById('catSlideAdd') && (document.getElementById('catSlideAdd').style.opacity = '.4');
}

// Remove a slide
window.carouselRemoveSlide = function(slideEl) {
  if (!document.body.classList.contains('gb-admin-mode')) return;
  var slides = _carouselSlides();
  if (slides.length <= 1) { slideEl.remove(); _carouselRebuildDots(); carouselSaveAll(); return; }
  var idx = Array.from(slides).indexOf(slideEl);
  slideEl.remove();
  if (_carouselCur >= _carouselSlides().length) _carouselCur = _carouselSlides().length - 1;
  if (_carouselCur === idx) _carouselGo(_carouselCur);
  _carouselRebuildDots();
  _carouselStart();
  carouselSaveAll();
  if (_carouselSlides().length < 6) { var add = document.getElementById('catSlideAdd'); if (add) add.style.opacity = ''; }
};

// Init
(function() {
  var slides = _carouselSlides();
  if (slides.length) { slides[0].classList.add('active'); }
  _carouselRebuildDots();
  _carouselStart();
})();

// ── Sign-in dialog ──────────────────────────────────────────────────────────
function openSignIn() {
  document.getElementById('siErr').textContent = '';
  document.getElementById('siEmail').value = '';
  document.getElementById('siPassword').value = '';
  document.getElementById('siTitle').textContent = 'Sign In';
  document.getElementById('siBtn').textContent = 'Sign In';
  document.getElementById('siBtn').onclick = function(){ submitSignIn(false); };
  document.getElementById('siMainPanel').style.display = '';
  document.getElementById('siConfirmPanel').style.display = 'none';
  _siPendingEmail = ''; _siPendingPass = '';
  document.getElementById('signInOverlay').classList.add('open');
  setTimeout(function(){ document.getElementById('siEmail').focus(); }, 60);
}
function forceCloseSignIn() { document.getElementById('signInOverlay').classList.remove('open'); }

function submitSignIn(confirmNew) {
  var email = confirmNew ? _siPendingEmail : document.getElementById('siEmail').value.trim();
  var pass  = confirmNew ? _siPendingPass  : document.getElementById('siPassword').value;
  if (!email || !pass) { document.getElementById('siErr').textContent = 'Email and password required.'; return; }
  var btn = document.getElementById('siBtn');
  btn.disabled = true;
  document.getElementById('siErr').textContent = '';
  var fd = new FormData();
  fd.append('email', email); fd.append('password', pass); fd.append('id', SHEET_ID);
  if (confirmNew) fd.append('confirm_new', '1');
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/collabAuth.php');
  xhr.onload = function() {
    btn.disabled = false;
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = {error: xhr.responseText}; }
    if (r.error) { document.getElementById('siErr').textContent = r.error; return; }
    if (r.prompt_create) {
      _siPendingEmail = email; _siPendingPass = pass;
      document.getElementById('siTitle').textContent = 'Create Account';
      document.getElementById('siMainPanel').style.display = 'none';
      document.getElementById('siConfirmPanel').style.display = '';
      document.getElementById('siBtn').textContent = 'Create Account';
      document.getElementById('siBtn').onclick = function(){ submitSignIn(true); };
      return;
    }
    _gbUser = { email: r.email || email, bio: r.bio || {} };
    try { sessionStorage.setItem('gameboard_owner_' + SHEET_ID, JSON.stringify(_gbUser)); } catch(e) {}
    _updateGbAuth();
    forceCloseSignIn();
  };
  xhr.onerror = function() { btn.disabled = false; document.getElementById('siErr').textContent = 'Network error.'; };
  xhr.send(fd);
}

// ── Settings dialog ──────────────────────────────────────────────────────────
function openSettings(focusFieldId) {
  document.getElementById('setErr').textContent = '';
  var xhr = new XMLHttpRequest();
  xhr.open('GET', APP_ROOT + 'push/tmGetSettings.php?id=' + encodeURIComponent(SHEET_ID));
  xhr.onload = function() {
    var s = {}; try { s = JSON.parse(xhr.responseText); } catch(e) {}
    document.getElementById('setCompany').value  = s['Company']            || s['Name'] || '';
    document.getElementById('setLogo').value     = s['Logo URL']           || '';
    document.getElementById('setTagline').value  = s['Tagline']            || '';
    document.getElementById('setAbout').value    = s['About']              || '';
    document.getElementById('setTemplate').value    = s['Gameboard Template'] || '';
    document.getElementById('setBannerColor').value  = s['Banner Color']  || '#1a1a2e';
    document.getElementById('setCopyright').value    = s['Copyright']    || '';
    document.getElementById('setFooterNote').value   = s['Footer Note']  || '';
    document.getElementById('setSubHeading').value   = s['Subscribe Heading'] || '';
    document.getElementById('setSubText').value      = s['Subscribe Text']    || '';
    var _imgs = s['Image'] || [];
    if (!Array.isArray(_imgs)) _imgs = _imgs ? [_imgs] : [];
    for (var i = 1; i <= 6; i++) {
      var el = document.getElementById('setCarousel' + i);
      if (el) el.value = _imgs[i - 1] || s['Carousel ' + i] || '';
    }
    document.getElementById('setInsta').value   = s['Instagram'] || '';
    document.getElementById('setTwitter').value = s['Twitter']   || '';
    document.getElementById('setFb').value      = s['Facebook']  || '';
    document.getElementById('setYt').value      = s['YouTube']   || '';
    document.getElementById('setBgg').value     = s['BGG URL']   || '';
    var target = focusFieldId ? document.getElementById(focusFieldId) : document.getElementById('setCompany');
    if (target) { target.focus(); target.select(); target.scrollIntoView({ behavior:'smooth', block:'center' }); }
  };
  xhr.send();
  document.getElementById('settingsOverlay').classList.add('open');
}
function forceCloseSettings() { document.getElementById('settingsOverlay').classList.remove('open'); }
function submitSettings() {
  var btn = document.getElementById('setBtn');
  btn.disabled = true;
  var fd = new FormData();
  fd.append('id',          SHEET_ID);
  fd.append('company',     document.getElementById('setCompany').value.trim());
  fd.append('logo',        document.getElementById('setLogo').value.trim());
  fd.append('tagline',     document.getElementById('setTagline').value.trim());
  fd.append('about',       document.getElementById('setAbout').value.trim());
  fd.append('gb_template',      document.getElementById('setTemplate').value);
  fd.append('banner_color',     document.getElementById('setBannerColor').value);
  fd.append('copyright',        document.getElementById('setCopyright').value.trim());
  fd.append('footer_note',      document.getElementById('setFooterNote').value.trim());
  fd.append('subscribe_heading', document.getElementById('setSubHeading').value.trim());
  fd.append('subscribe_text',   document.getElementById('setSubText').value.trim());
  for (var i = 1; i <= 6; i++) {
    var el = document.getElementById('setCarousel' + i);
    if (el) fd.append('carousel_' + i, el.value.trim());
  }
  fd.append('instagram', document.getElementById('setInsta').value.trim());
  fd.append('twitter',   document.getElementById('setTwitter').value.trim());
  fd.append('facebook',  document.getElementById('setFb').value.trim());
  fd.append('youtube',   document.getElementById('setYt').value.trim());
  fd.append('bgg_url',   document.getElementById('setBgg').value.trim());
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/tmSaveSettings.php');
  xhr.onload = function() {
    btn.disabled = false;
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = {error: xhr.responseText}; }
    if (r.error) { document.getElementById('setErr').textContent = r.error; return; }
    if (!r.sheetOk && r.sheetErr) {
      console.warn('Sheet sync error:', r.sheetErr);
      document.getElementById('setErr').textContent = 'Saved locally — sheet sync: ' + r.sheetErr;
    }
    forceCloseSettings();
    location.reload();
  };
  xhr.onerror = function() { btn.disabled = false; document.getElementById('setErr').textContent = 'Network error.'; };
  xhr.send(fd);
}

// ── Esc closes open dialogs ──────────────────────────────────────────────────
document.addEventListener('keydown', function(e) {
  if (e.key !== 'Escape') return;
  if (document.getElementById('gbPublishOverlay').classList.contains('open')){ closePublishDialog(); return; }
  if (document.getElementById('signInOverlay').classList.contains('open'))  { forceCloseSignIn(); return; }
  if (document.getElementById('settingsOverlay').classList.contains('open')){ forceCloseSettings(); return; }
  if (document.getElementById('addGameOverlay').classList.contains('open')) { forceCloseAddGame(); return; }
});

// ── Add Game dialog ──────────────────────────────────────────────────────────
var _agFields = ['agName','agTagline','agDescription','agStatus','agAvailability',
  'agCount','agDuration','agDesigner1','agDesigner2','agDesigner3','agDesigner4',
  'agDateStarted','agDateSigned','agDatePublished',
  'agCoverUrl','agImageUrl','agVideoUrl','agPageUrl','agSellsheet','agRulesUrl','agPlayUrl','agPrintUrl'];

function openAddGame() {
  _agFields.forEach(function(id){ document.getElementById(id).value = ''; });
  document.getElementById('agStatus').value = 'Published';
  document.getElementById('agErr').textContent = '';
  document.getElementById('addGameOverlay').classList.add('open');
  setTimeout(function(){ document.getElementById('agName').focus(); }, 60);
}

function forceCloseAddGame() {
  document.getElementById('addGameOverlay').classList.remove('open');
}

function closeAddGame() {
  var dirty = _agFields.some(function(id){ return document.getElementById(id).value.trim() !== ''; });
  if (dirty) {
    var d = document.getElementById('addGameOverlay').querySelector('.gbed-dialog');
    d.style.transition = 'transform .08s';
    var seq = [6,-6,4,-4,2,0], i = 0;
    (function step(){ d.style.transform = 'translateX('+seq[i]+'px)'; if(++i < seq.length) setTimeout(step,60); })();
    return;
  }
  forceCloseAddGame();
}

function submitAddGame() {
  var name = document.getElementById('agName').value.trim();
  if (!name) {
    document.getElementById('agErr').textContent = 'Game name is required.';
    document.getElementById('agName').focus();
    return;
  }
  var btn = document.getElementById('agBtn');
  btn.disabled = true; btn.textContent = 'Creating…';
  document.getElementById('agErr').textContent = '';

  var g = function(id){ return document.getElementById(id).value.trim(); };
  var imageRaw = g('agImageUrl');
  var coverRaw = g('agCoverUrl');
  var fd = new FormData();
  fd.append('id',             SHEET_ID);
  fd.append('name',           name);
  fd.append('tagline',        g('agTagline'));
  fd.append('description',    g('agDescription'));
  fd.append('status',         g('agStatus'));
  fd.append('availability',   g('agAvailability'));
  fd.append('count',          g('agCount'));
  fd.append('duration',       g('agDuration'));
  fd.append('designer1',      g('agDesigner1'));
  fd.append('designer2',      g('agDesigner2'));
  fd.append('designer3',      g('agDesigner3'));
  fd.append('designer4',      g('agDesigner4'));
  fd.append('date_started',   g('agDateStarted'));
  fd.append('date_signed',    g('agDateSigned'));
  fd.append('date_published', g('agDatePublished'));
  fd.append('cover_url',      coverRaw);
  fd.append('image',          imageRaw ? '=IMAGE("' + imageRaw + '")' : '');
  fd.append('video',          g('agVideoUrl'));
  fd.append('view',           g('agPageUrl'));
  fd.append('sellsheet',      g('agSellsheet'));
  fd.append('rules',          g('agRulesUrl'));
  fd.append('play',           g('agPlayUrl'));
  fd.append('print',          g('agPrintUrl'));

  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/addGame.php');
  xhr.onload = function() {
    btn.disabled = false; btn.textContent = 'Create Game';
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = null; }
    if (!r || r.error) {
      document.getElementById('agErr').textContent = (r && r.error) || 'Unknown error. Please try again.';
      return;
    }
    forceCloseAddGame();
    location.reload();
  };
  xhr.onerror = function() {
    btn.disabled = false; btn.textContent = 'Create Game';
    document.getElementById('agErr').textContent = 'Network error. Please try again.';
  };
  xhr.send(fd);
}

// ── Fetch log dialog ─────────────────────────────────────────────────────────
function gbSyncLog(msg, type) {
  var log  = document.getElementById('fetchLogBody');
  var line = document.createElement('span');
  line.className = 'sync-log-line ' + (type || 'info');
  line.textContent = msg;
  log.appendChild(line);
  log.scrollTop = log.scrollHeight;
}
function gbOpenFetchDialog() {
  document.getElementById('fetchLogBody').innerHTML = '';
  document.getElementById('fetchLogTitle').textContent = 'Fetching…';
  document.getElementById('fetchLogDoneBtn').disabled = true;
  document.getElementById('fetchLogOverlay').classList.add('open');
}
function gbFinishFetch(hasErrors) {
  document.getElementById('fetchLogTitle').textContent = hasErrors ? 'Fetch — errors' : 'Fetch Complete';
  document.getElementById('fetchLogDoneBtn').disabled = false;
  gbSyncLog(hasErrors ? 'Completed with errors.' : 'Done.', hasErrors ? 'error' : 'ok');
}
function gbDoFetch() {
  gbOpenFetchDialog();
  var base = APP_ROOT + 'push/setupGameboard.php';
  var hasErrors = false;

  // Step 1: setup + refresh main sheets
  gbSyncLog('Fetching sheets…', 'info');
  var fd = new FormData(); fd.append('id', SHEET_ID);
  fetch(base, { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(r) {
      if (r.error) { gbSyncLog('✗ ' + r.error, 'error'); gbFinishFetch(true); return; }
      if (r.tabs) Object.keys(r.tabs).forEach(function(t) {
        var s = r.tabs[t];
        gbSyncLog((s==='error'?'  ✗ ':'  ✓ ') + t, s==='error'?'error':(s==='created'?'ok':'skip'));
        if (s==='error') hasErrors = true;
      });
      if (r.json_refreshed) r.json_refreshed.forEach(function(t) { gbSyncLog('  ✓ ' + t, 'ok'); });
      if (r.refresh_errors) { Object.keys(r.refresh_errors).forEach(function(t) { gbSyncLog('  ✗ ' + t, 'error'); hasErrors = true; }); }

      // Step 2: publish each game tab cache individually
      var games = r.game_names || [];
      if (!games.length) { gbFinishFetch(hasErrors); return; }
      gbSyncLog('Publishing game caches…', 'info');
      var gi = 0;
      function nextGame() {
        if (gi >= games.length) { gbFinishFetch(hasErrors); return; }
        var name = games[gi++];
        var gfd = new FormData();
        gfd.append('id', SHEET_ID); gfd.append('mode', 'game'); gfd.append('name', name);
        fetch(base, { method:'POST', body:gfd })
          .then(function(r) { return r.json(); })
          .then(function(r) {
            if (r && r.ok) { gbSyncLog('  ✓ ' + name, 'ok'); }
            else {
              var msg = (r && r.error ? ': ' + r.error : '');
              if (r && r.detail) msg += ' — ' + r.detail;
              gbSyncLog('  ✗ ' + name + msg, 'error'); hasErrors = true;
            }
            nextGame();
          })
          .catch(function() { gbSyncLog('  ✗ ' + name + ': network error', 'error'); hasErrors = true; nextGame(); });
      }
      nextGame();
    })
    .catch(function() { gbSyncLog('✗ Network error', 'error'); gbFinishFetch(true); });
}
function closeFetchLog() {
  document.getElementById('fetchLogOverlay').classList.remove('open');
  location.reload();
}

// ── Strip HTML on paste — always insert plain text ────────────────────────────
document.addEventListener('paste', function(e) {
  var el = e.target;
  var text = (e.clipboardData || window.clipboardData).getData('text/plain');
  if (!text) return;
  if (el.tagName === 'TEXTAREA' || (el.tagName === 'INPUT' && el.type === 'text')) {
    e.preventDefault();
    var start = el.selectionStart, end = el.selectionEnd, val = el.value;
    el.value = val.substring(0, start) + text + val.substring(end);
    el.selectionStart = el.selectionEnd = start + text.length;
    el.dispatchEvent(new Event('input', { bubbles: true }));
  } else if (el.isContentEditable || el.closest('[contenteditable="true"]')) {
    e.preventDefault();
    document.execCommand('insertText', false, text);
  }
}, true);

// ── Subscribe (shared footer) ─────────────────────────────────────────────────
function gbSubscribeSubmit(e) {
  e.preventDefault();
  var email = (document.getElementById('gbSubscribeEmail').value || '').trim();
  if (!email) return;
  var btn = document.getElementById('gbSubscribeBtn');
  var msg = document.getElementById('gbSubscribeMsg');
  btn.disabled = true;
  btn.textContent = 'Subscribing…';
  msg.textContent = '';
  msg.className = 'gb-footer-sub-msg';
  var fd = new FormData();
  fd.append('id',    SHEET_ID);
  fd.append('game',  'subscribe');
  fd.append('name',  '');
  fd.append('email', email);
  fd.append('note',  'Newsletter Subscribe');
  fetch(APP_ROOT + 'push/submitNote.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (d.ok) {
        msg.textContent = "You’re subscribed! Thanks for signing up.";
        msg.className = 'gb-footer-sub-msg ok';
        document.getElementById('gbSubscribeEmail').value = '';
        btn.textContent = 'Subscribed ✓';
      } else {
        msg.textContent = d.error || 'Something went wrong. Please try again.';
        msg.className = 'gb-footer-sub-msg err';
        btn.disabled = false;
        btn.textContent = 'Subscribe';
      }
    })
    .catch(function() {
      msg.textContent = 'Network error. Please try again.';
      msg.className = 'gb-footer-sub-msg err';
      btn.disabled = false;
      btn.textContent = 'Subscribe';
    });
}

// ── Inline add-game card (catalog page) ──────────────────────────────────────
var _catAddGameImgUrl = '';

function catAddGameClick(e) {
  if (e.target.closest('.gb-agc-img-btn')) return;
  var name = document.getElementById('catAddGameName');
  if (name) name.focus();
}

function catAddGameNameFocus(el) {
  if (el.textContent.trim() === 'New Game') {
    var r = document.createRange(); r.selectNodeContents(el);
    var s = window.getSelection(); s.removeAllRanges(); s.addRange(r);
  }
}

function catAddGameNameBlur(el) {
  if (!el.textContent.trim()) el.textContent = 'New Game';
}

function catAddGameKey(e) {
  if (e.key === 'Enter') { e.preventDefault(); catAddGameSubmit(); }
  if (e.key === 'Escape') {
    var el = document.getElementById('catAddGameName');
    if (el) { el.textContent = 'New Game'; el.blur(); }
    _catAddGameImgUrl = '';
    var inner = document.getElementById('catAddGameInner');
    if (inner) {
      var prev = inner.querySelector('.gb-agc-preview'); if (prev) prev.remove();
      var plus = inner.querySelector('.gb-agc-plus'); if (plus) plus.style.display = '';
    }
  }
}

function catAddGamePickImg(e) {
  e.preventDefault(); e.stopPropagation();
  var inp = document.getElementById('catAddGameImgInput');
  if (!inp) {
    inp = document.createElement('input');
    inp.type = 'file'; inp.id = 'catAddGameImgInput'; inp.accept = 'image/*'; inp.style.display = 'none';
    inp.addEventListener('change', function() {
      if (inp.files && inp.files[0]) catAddGameUploadImg(inp.files[0]);
      inp.value = '';
    });
    document.body.appendChild(inp);
  }
  inp.click();
}

function catAddGameUploadImg(file) {
  var inner = document.getElementById('catAddGameInner');
  var btn = inner ? inner.querySelector('.gb-agc-img-btn') : null;
  if (inner) inner.classList.add('img-loading');
  if (btn) btn.disabled = true;
  var fd = new FormData();
  fd.append('file', file); fd.append('id', SHEET_ID);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/uploadImage.php');
  xhr.onload = function() {
    if (inner) inner.classList.remove('img-loading');
    if (btn) btn.disabled = false;
    var r; try { r = JSON.parse(xhr.responseText); } catch(ex) { r = null; }
    if (!r || r.error) { alert('Upload failed: ' + (r && r.error || 'unknown')); return; }
    _catAddGameImgUrl = r.url;
    if (inner) {
      var prev = inner.querySelector('.gb-agc-preview'); if (prev) prev.remove();
      var plus = inner.querySelector('.gb-agc-plus'); if (plus) plus.style.display = 'none';
      var img = document.createElement('img');
      img.className = 'gb-agc-preview'; img.src = r.url;
      inner.insertBefore(img, inner.firstChild);
    }
  };
  xhr.onerror = function() {
    if (inner) inner.classList.remove('img-loading');
    if (btn) btn.disabled = false;
    alert('Upload failed. Please try again.');
  };
  xhr.send(fd);
}

function catAddGameSubmit() {
  var nameEl = document.getElementById('catAddGameName');
  var name = nameEl ? nameEl.textContent.trim() : '';
  if (!name || name === 'New Game') { if (nameEl) nameEl.focus(); return; }
  var card = document.getElementById('catAddGameCard');
  if (card) { card.style.opacity = '.5'; card.style.pointerEvents = 'none'; }
  var fd = new FormData();
  fd.append('id',        SHEET_ID);
  fd.append('name',      name);
  fd.append('status',    'Published');
  fd.append('cover_url', _catAddGameImgUrl || '');
  fd.append('image',     _catAddGameImgUrl ? '=IMAGE("' + _catAddGameImgUrl + '")' : '');
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/addGame.php');
  xhr.onload = function() {
    if (card) { card.style.opacity = ''; card.style.pointerEvents = ''; }
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = null; }
    if (!r || r.error) { alert((r && r.error) || 'Unknown error. Please try again.'); return; }
    location.reload();
  };
  xhr.onerror = function() {
    if (card) { card.style.opacity = ''; card.style.pointerEvents = ''; }
    alert('Network error. Please try again.');
  };
  xhr.send(fd);
}

function catDeleteGame(e, gameName) {
  e.preventDefault(); e.stopPropagation();
  if (!confirm('Delete "' + gameName + '"?\n\nThis will permanently remove the game row and its sheet tab. This cannot be undone.')) return;
  var wrap = e.target.closest('.cat-card-wrap');
  if (wrap) { wrap.style.opacity = '.4'; wrap.style.pointerEvents = 'none'; }
  var fd = new FormData();
  fd.append('id',   SHEET_ID);
  fd.append('game', gameName);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/deleteGame.php');
  xhr.onload = function() {
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = null; }
    if (!r || r.error) {
      if (wrap) { wrap.style.opacity = ''; wrap.style.pointerEvents = ''; }
      alert('Delete failed: ' + (r && r.error || 'Unknown error'));
      return;
    }
    location.reload();
  };
  xhr.onerror = function() {
    if (wrap) { wrap.style.opacity = ''; wrap.style.pointerEvents = ''; }
    alert('Network error. Please try again.');
  };
  xhr.send(fd);
}
</script>

<!-- ── Fetch Log dialog ── -->
<div class="gbed-overlay" id="fetchLogOverlay">
  <div class="sync-dialog" onclick="event.stopPropagation()">
    <h2 id="fetchLogTitle">Fetching…</h2>
    <div class="sync-log" id="fetchLogBody"></div>
    <div class="sync-dialog-actions">
      <button class="btn-adm btn-adm-secondary" id="fetchLogDoneBtn" disabled onclick="closeFetchLog()">Close</button>
    </div>
  </div>
</div>

<style>
.sync-dialog { background:#fff; border-radius:10px; padding:1.4rem; width:min(480px,92vw); box-shadow:0 8px 32px rgba(0,0,0,.22); display:flex; flex-direction:column; gap:.75rem; max-height:calc(100dvh - 2rem); overflow-y:auto; }
.sync-dialog h2 { font-size:.95rem; margin:0; font-weight:700; }
.sync-log { background:#0f172a; border-radius:6px; padding:.75rem 1rem; min-height:6rem; max-height:14rem; overflow-y:auto; font-family:monospace; font-size:.75rem; line-height:1.6; color:#94a3b8; display:flex; flex-direction:column; }
.sync-log-line { display:block; }
.sync-log-line.ok    { color:#4ade80; }
.sync-log-line.skip  { color:#94a3b8; }
.sync-log-line.error { color:#f87171; }
.sync-log-line.info  { color:#60a5fa; }
.sync-dialog-actions { display:flex; align-items:center; justify-content:flex-end; gap:.5rem; position:sticky; bottom:0; background:#fff; padding-top:.5rem; margin-top:auto; }
</style>

<div class="gb-save-toast" id="gbSaveToast" style="position:fixed;bottom:1.5rem;right:1.5rem;padding:.55rem 1.1rem;border-radius:8px;font-size:.82rem;font-family:'DINBlack',sans-serif;color:#fff;box-shadow:0 3px 16px rgba(0,0,0,.22);display:none;z-index:9999;"></div>

</body>
</html>
<?php
    exit;
}
// ── END CATALOG ──────────────────────────────────────────────────────────────

$_game = null;
foreach ($_allGames as $g) {
    if (_gb_slug($g['Name'] ?? '') === _gb_slug($_gameSlug)) { $_game = $g; break; }
}

if (!$_game) { http_response_code(404); exit; }

// ── Core fields from games.json ──────────────────────────────────────────────
$_name        = trim($_game['Name']           ?? '');
$_summary     = trim($_game['Tagline'] ?? $_game['Tag Line'] ?? $_game['Summary'] ?? '');
$_description = trim($_game['Description']    ?? '');
$_count       = trim($_game['Count']    ?? '');  // may be overridden by tab rows below
$_duration    = trim($_game['Duration'] ?? '');  // may be overridden by tab rows below
$_availability= trim($_game['Availability'] ?? '');
$_status      = trim($_game['Status']         ?? '');
$_coverUrl    = _gb_img(_gb_link(trim($_game['Cover URL']    ?? $_game['Image'] ?? '')));
$_imageUrl    = _gb_img(_gb_link(trim($_game['Image URL']    ?? $_game['Image'] ?? '')));
$_videoUrl    = _gb_link(trim($_game['Video URL'] ?? $_game['Video'] ?? ''));
$_pageUrl     = _gb_link(trim($_game['Page URL']       ?? ''));
$_sellsheet   = _gb_link(trim($_game['Sellsheet URL']  ?? ''));
$_playUrl     = _gb_link(trim($_game['Play URL']       ?? ''));
$_printUrl    = _gb_link(trim($_game['Print URL']      ?? ''));
$_rulesUrl    = _gb_link(trim($_game['Rules URL']      ?? ''));
$_pbSheetId   = trim($_game['Playbook Sheet ID'] ?? '');
$_dateSigned  = trim($_game['Date Signed']    ?? '');
$_datePub     = trim($_game['Date Published'] ?? '');

$_designers = [];
for ($i = 1; $i <= 4; $i++) {
    $d = trim($_game["Designer$i"] ?? '');
    if ($d) $_designers[] = $d;
}
$_artists = []; // populated after $_rich loads below

// ── Load rich game-specific data ─────────────────────────────────────────────
// Sources checked in order:
//   1. game-{name}-en.json in the current sheet dir  (GameBoard [GameName] tab)
//   2. game-{name}-en.json in the Playbook Sheet dir (if Playbook Sheet ID set)
//   3. {lowercase_name}.json in the Playbook Sheet dir (legacy Playbook naming)
// Format: [{Name, Value, "Value 1", "Value 2"}, ...]
$_safeName = str_replace(['/', '\\'], '-', $_name);

$_rich = [];
$_richCandidates = [
    $_sDir . '/game-' . $_safeName . '-en.json',
];
if ($_pbSheetId) {
    $_richCandidates[] = $_root . '/sheets/' . $_pbSheetId . '/game-' . $_safeName . '-en.json';
    $_richCandidates[] = $_root . '/sheets/' . $_pbSheetId . '/' . strtolower($_name) . '.json';
}
foreach ($_richCandidates as $_richFile) {
    $_rich = _gb_load($_richFile);
    if (!empty($_rich)) break;
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

$_components  = array_values(array_filter(_gb_rows($_rich, 'Component'), fn($r) => trim($r['Value'] ?? '') !== ''));
$_reviews     = array_values(array_filter(_gb_rows($_rich, 'Review'),    fn($r) => trim($r['Value'] ?? '') !== ''));
$_faqs        = array_values(array_filter(_gb_rows($_rich, 'FAQ'),       fn($r) => trim($r['Value'] ?? '') !== ''));
$_buyUrls        = _gb_rows($_rich, 'BuyUrl');
$_resourceLinks  = array_values(array_filter(_gb_rows($_rich, 'ResourceLink'), fn($r) => trim($r['Value'] ?? '') !== '' || trim($r['Value 1'] ?? '') !== ''));
$_hasRichData    = !empty($_rich);
// Video rows in [GameName] tab = video reviews (col B = URL, col C = name/label)
// Main video comes from Games sheet Video column only ($videoUrl below)
$_videoReviews = array_values(array_filter(_gb_rows($_rich, 'Video'), fn($r) => trim($r['Value'] ?? '') !== ''));
$_mediaImgs      = array_merge(_gb_rows($_rich, 'Media'), _gb_rows($_rich, 'ProductImage'));
$_productImgUrls = array_values(array_filter(array_map(fn($r) => _gb_img(trim($r['Value'] ?? '')), _gb_rows($_rich, 'ProductImage')), fn($u) => $u !== ''));
$_headerImageUrl = _gb_img(_gb_link(_gb_val($_rich, 'HeaderImage')));
$_richPrice   = _gb_val($_rich, 'Price');
$_richRules   = _gb_link(_gb_val($_rich, 'Rules PDF Url')) ?: $_rulesUrl;

// ── Stats from [GameName] tab rows ───────────────────────────────────────────
$_minPlayers  = _gb_val($_rich, 'MinPlayers');
$_maxPlayers  = _gb_val($_rich, 'MaxPlayers');
$_minPlaytime = _gb_val($_rich, 'MinPlaytime');
$_maxPlaytime = _gb_val($_rich, 'MaxPlaytime');
$_weight      = _gb_val($_rich, 'Weight');
$_mechanic    = _gb_val($_rich, 'Mechanic') ?: _gb_val($_rich, 'Main Mechanic');
$_ages        = _gb_val($_rich, 'Ages')     ?: _gb_val($_rich, 'Age');
$_series      = _gb_val($_rich, 'Series');
$_boxSize     = _gb_val($_rich, 'Box Size');
// Artists from [GameName] tab rows (multiple rows all named 'Artist')
foreach (_gb_rows($_rich, 'Artist') as $r) {
    $a = trim($r['Value'] ?? '');
    if ($a) $_artists[] = $a;
}

// Override count/duration display with tab data when available
if ($_minPlayers || $_maxPlayers) {
    $_count = $_minPlayers . ($_maxPlayers && $_maxPlayers !== $_minPlayers ? '–' . $_maxPlayers : '');
}
if ($_minPlaytime || $_maxPlaytime) {
    $_duration = $_minPlaytime . ($_maxPlaytime && $_maxPlaytime !== $_minPlaytime ? '–' . $_maxPlaytime : '') . ' min';
}

// ── Gallery: cover + image + rich media images ─────────────────────────────
$_gallery = [];
foreach (array_filter([$_coverUrl, $_imageUrl]) as $img) {
    if (!in_array($img, $_gallery)) $_gallery[] = $img;
}
foreach ($_mediaImgs as $r) {
    $u = trim($r['Value'] ?? '');
    if ($u && !in_array($u, $_gallery)) $_gallery[] = $u;
}

// Header image: HeaderImage row in [game] tab → first ProductImage → gallery[0]
$_heroImg = $_headerImageUrl ?: ($_productImgUrls[0] ?? ($_gallery[0] ?? ''));

// Main video: Games sheet Video column only
$_videoEmbed = _gb_embed($_videoUrl);

// Direct (non-embeddable) video file URL — mp4/webm/ogg/mov uploads
$_videoDirect = '';
if (!$_videoEmbed && $_videoUrl) {
    $ext = strtolower(pathinfo(parse_url($_videoUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
    if (in_array($ext, ['mp4','webm','ogg','mov'], true)) $_videoDirect = $_videoUrl;
}

// Other Published games for "More Games" section
$_otherGames = [];
foreach ($_allGames as $g) {
    if (strtolower(trim($g['Status'] ?? '')) === 'published' && _gb_slug($g['Name'] ?? '') !== _gb_slug($_name)) {
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
.gb-nav-left { display:flex; align-items:baseline; gap:.5rem; min-width:0; }
.gb-nav-back { display:inline-flex; align-items:center; gap:.25rem; color:rgba(255,255,255,.75); font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; text-decoration:none; flex-shrink:0; padding:.2rem .4rem; border-radius:5px; transition:color .12s, background .12s; line-height:1; }
.gb-nav-back:hover { color:#fff; background:rgba(255,255,255,.12); }
.gb-nav-back svg { flex-shrink:0; vertical-align:middle; position:relative; top:2.5px; }
.gb-nav-back-label { white-space:nowrap; vertical-align:middle; }
.gb-nav-sep { color:rgba(255,255,255,.25); font-size:.85rem; flex-shrink:0; line-height:1; }
.gb-nav-game { font-size:.78rem; opacity:.55; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; line-height:1; }
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
.gb-hero-content { position:relative; z-index:5; max-width:860px; padding:2.5rem 2rem 3.5rem; }
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
.gb-hero-ctas { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; margin-top:.75rem; }
.gb-hero-ctas .btn-nav { display:inline-flex; align-items:center; height:2.75rem; box-sizing:border-box; padding:0 1rem; }
.gb-price-tag {
  display:inline-block; vertical-align:middle;
  height:2.75rem; box-sizing:border-box; padding:0 .85rem;
  /* slightly above center */
  line-height:calc(2.75rem - 14px);
  border:2px solid rgba(255,255,255,.35); border-radius:6px;
  font-family:'DINBlack',sans-serif; font-size:1.4rem; color:#fff;
  letter-spacing:-.01em;
}
#gbEditPrice { display:inline; line-height:inherit; }

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
.gb-gallery-viewer { display:flex; flex-direction:column; gap:.55rem; }
.gb-gallery-featured { position:relative; border-radius:10px; overflow:hidden; aspect-ratio:4/3; }
.gb-gallery-featured img { width:100%; height:100%; object-fit:cover; object-position:center; display:block; transition:opacity .35s; }
.gb-gallery-featured img.gb-gf-fade { opacity:0; }
/* admin overlay on featured */
.gb-gallery-featured-del { position:absolute; top:.5rem; right:.5rem; background:rgba(0,0,0,.55); color:#fff; border:none; border-radius:50%; width:24px; height:24px; font-size:.7rem; cursor:pointer; display:none; align-items:center; justify-content:center; z-index:3; }
body.gb-admin-mode .gb-gallery-featured-del { display:flex; }
/* uploading spinner on main featured area */
.gb-gallery-featured.gb-uploading::before { content:''; position:absolute; inset:0; background:rgba(0,0,0,.35); z-index:4; border-radius:10px; }
.gb-gallery-featured.gb-uploading::after,
.gb-gallery-viewer.gb-gallery-empty .gb-gallery-featured.gb-uploading::after {
  content:''; position:absolute; inset:0; margin:auto; width:36px; height:36px;
  border:4px solid rgba(255,255,255,.25); border-top-color:#fff; border-radius:50%;
  animation:gb-spin .7s linear infinite; z-index:5; font-size:0;
}
/* Thumbs row */
.gb-gallery-thumbs { display:flex; flex-direction:row; gap:.45rem; overflow-x:auto; padding-bottom:2px; }
.gb-gallery-thumbs::-webkit-scrollbar { height:3px; }
.gb-gallery-thumbs::-webkit-scrollbar-thumb { background:var(--border); border-radius:2px; }
.gb-gallery-thumb { position:relative; width:100px; aspect-ratio:4/3; border-radius:7px; overflow:hidden; flex-shrink:0; cursor:pointer; border:2px solid transparent; transition:border-color .18s,opacity .15s; background:#eee; }
.gb-gallery-thumb img { width:100%; height:100%; object-fit:cover; object-position:center; display:block; pointer-events:none; }
.gb-gallery-thumb.gb-gt-active { border-color:var(--accent); }
.gb-gallery-thumb:hover:not(.gb-gt-active) { border-color:rgba(0,0,0,.2); }
/* drag-to-reorder */
body.gb-admin-mode .gb-gallery-thumb { cursor:grab; }
.gb-gallery-thumb.gb-gt-dragging { opacity:.35; cursor:grabbing; }
.gb-gallery-thumb.gb-gt-drag-over { border-color:var(--accent); box-shadow:0 0 0 2px var(--accent); }
/* thumb delete btn */
.gb-gt-del { position:absolute; top:2px; right:2px; background:rgba(0,0,0,.6); color:#fff; border:none; border-radius:50%; width:18px; height:18px; font-size:.55rem; cursor:pointer; display:none; align-items:center; justify-content:center; z-index:2; line-height:1; }
body.gb-admin-mode .gb-gt-del { display:flex; }
/* "+" add thumb */
.gb-gallery-thumb-add { width:100px; aspect-ratio:4/3; border-radius:7px; flex-shrink:0; cursor:pointer; border:2px dashed rgba(59,59,170,.25); background:linear-gradient(135deg,#f0f0fa,#e4e4f4); display:flex; align-items:center; justify-content:center; font-size:1.6rem; color:rgba(59,59,170,.35); position:relative; transition:opacity .18s; opacity:0; pointer-events:none; }
body.gb-admin-mode .gb-gallery-thumb-add { opacity:1; pointer-events:auto; }
.gb-gallery-thumb-add:hover { opacity:.7 !important; }
.gb-gallery-thumb-add.gb-drag-over { opacity:1 !important; border-color:var(--accent); background:#e8eaf8; }
.gb-gallery-thumb-add.gb-uploading { opacity:1 !important; border-color:var(--accent); pointer-events:none; }
.gb-gallery-thumb-add.gb-uploading > span { visibility:hidden; }
.gb-gallery-thumb-add.gb-uploading::after { content:''; position:absolute; inset:0; margin:auto; width:22px; height:22px; border:3px solid rgba(59,59,170,.2); border-top-color:var(--accent); border-radius:50%; animation:gb-spin .7s linear infinite; }
@keyframes gb-spin { to { transform:rotate(360deg); } }
/* Gallery empty state (admin only) */
body.gb-admin-mode .gb-gallery-viewer.gb-gallery-empty .gb-gallery-featured {
  display:flex; align-items:center; justify-content:center;
  border:2px dashed rgba(59,59,170,.25); background:linear-gradient(135deg,#f0f0fa,#e4e4f4);
  cursor:pointer;
}
body.gb-admin-mode .gb-gallery-viewer.gb-gallery-empty .gb-gallery-featured::after {
  content:'+'; font-size:3.5rem; color:rgba(59,59,170,.3); line-height:1; pointer-events:none;
}
body.gb-admin-mode .gb-gallery-viewer.gb-gallery-empty .gb-gallery-featured-del { display:none !important; }
body.gb-admin-mode .gb-gallery-viewer.gb-gallery-empty .gb-gallery-featured img { display:none !important; }

/* ── Video ── */
.gb-video-wrap { position:relative; padding-top:56.25%; border-radius:10px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.13); }
.gb-video-wrap iframe { position:absolute; inset:0; width:100%; height:100%; border:none; }
.gb-video-wrap video { position:absolute; inset:0; width:100%; height:100%; object-fit:contain; background:#000; }
/* Video admin remove button (admin only) */
.gb-video-del-btn { display:none; position:absolute; top:.4rem; right:.4rem; z-index:10; background:rgba(0,0,0,.55); color:#fff; border:none; border-radius:50%; width:26px; height:26px; font-size:.75rem; line-height:26px; text-align:center; cursor:pointer; padding:0; transition:background .15s; }
body.gb-admin-mode .gb-video-del-btn { display:block; }
.gb-video-del-btn:hover { background:rgba(0,0,0,.82); }
/* Video placeholder (admin only) */
.gb-video-ph-inner { background:linear-gradient(135deg,#1a1a2e,#2d2d88); border-radius:10px; aspect-ratio:16/9; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:.5rem; opacity:.3; cursor:pointer; transition:opacity .2s; }
.gb-video-ph-inner:hover { opacity:.5; }
.gb-video-ph-inner.drag-over { opacity:.8; outline:2px dashed #fff; }
.gb-video-ph-inner.url-entry { opacity:1; cursor:default; gap:.75rem; padding:1.5rem; }
.gb-video-ph-url-wrap { display:flex; gap:.5rem; width:min(420px,100%); }
.gb-video-ph-url-wrap input { flex:1; padding:.55rem .8rem; border-radius:6px; border:none; font-size:.85rem; outline:none; }
.gb-video-ph-url-wrap button { padding:.55rem .9rem; border-radius:6px; border:none; background:#4ade80; color:#0f172a; font-weight:700; font-size:.8rem; cursor:pointer; white-space:nowrap; }
.gb-video-ph-url-wrap button:hover { background:#22c55e; }
.gb-video-ph-cancel { font-size:.65rem; color:#94a3b8; cursor:pointer; text-transform:uppercase; letter-spacing:.08em; }
.gb-video-ph-cancel:hover { color:#fff; }
.gb-video-tabs { display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.7rem; }
.gb-vtab {
  font-family:'DINBlack',sans-serif; font-size:.68rem; text-transform:uppercase;
  letter-spacing:.06em; padding:.28rem .65rem; border-radius:4px; cursor:pointer;
  border:1px solid var(--border); background:var(--surface); color:var(--ink-lt);
  transition:background .12s;
}
.gb-vtab:hover, .gb-vtab.active { background:var(--accent); color:#fff; border-color:var(--accent); }

/* ── Inline section edit textareas (admin mode) ── */
.gb-inline-edit {
  display:none; width:100%; min-height:110px; padding:.6rem .8rem;
  border:1.5px dashed var(--border); border-radius:8px;
  font-family:inherit; font-size:.88rem; line-height:1.6;
  resize:vertical; box-sizing:border-box;
  background:var(--surface); color:var(--ink);
}
body.gb-admin-mode .gb-inline-edit { display:block; }
body.gb-admin-mode .gb-display-content { display:none; }
/* Hide empty sections from public view */
body:not(.gb-admin-mode) .gb-sect-empty { display:none; }
.gb-sect-hidden { display:none; }
.gb-inline-hint { display:none; font-size:.73rem; color:var(--muted); margin:.3rem 0 0; font-style:italic; }
body.gb-admin-mode .gb-inline-hint { display:block; }

/* ── FAQ editor (dynamic Q+A pairs) ── */
.gb-faq-editor { display:none; flex-direction:column; gap:.6rem; min-height:0; padding:0; border:none; background:none; resize:none; }
body.gb-admin-mode .gb-faq-editor { display:flex; }
.gb-faq-pair { border:1.5px dashed var(--border); border-radius:8px; overflow:hidden; }
.faq-ans-wrap { position:relative; }
.faq-img-btn { position:absolute; bottom:.35rem; right:.35rem; width:1.55rem; height:1.55rem; background:rgba(255,255,255,.88); border:1px solid #d0d8e4; border-radius:5px; cursor:pointer; color:#99a; display:flex; align-items:center; justify-content:center; z-index:2; padding:0; transition:color .15s,border-color .15s; }
.faq-img-btn:hover { color:var(--accent); border-color:#a0b8c8; }
.faq-img-preview { position:relative; }
.faq-img-preview img { width:100%; display:block; border-radius:0 0 6px 6px; }
.faq-img-trash { position:absolute; bottom:.3rem; right:.3rem; width:1.55rem; height:1.55rem; background:rgba(255,255,255,.92); border:1px solid #e8c0c0; border-radius:5px; cursor:pointer; color:#c0392b; display:flex; align-items:center; justify-content:center; z-index:3; padding:0; }
.faq-img-trash:hover { background:#fff0f0; border-color:#c0392b; }
.gb-faq-pair textarea {
  display:block; width:100%; box-sizing:border-box; border:none; outline:none;
  font-family:inherit; font-size:.88rem; line-height:1.6; resize:none; overflow:hidden;
  background:var(--surface); color:var(--ink); padding:.55rem .8rem;
}
.gb-faq-pair textarea.faq-q { font-weight:700; border-bottom:1px solid var(--border); }
.gb-faq-pair textarea.faq-a { color:var(--ink-lt); }

/* ── Components ── */
.gb-components { display:grid; grid-template-columns:repeat(auto-fill, minmax(195px,1fr)); gap:.5rem; }
.gb-comp-item {
  display:flex; align-items:baseline; gap:.55rem;
  background:var(--surface); border:1px solid var(--border);
  border-radius:7px; padding:.52rem .7rem; font-size:.86rem;
}
.gb-comp-qty { font-family:'DINBlack',sans-serif; font-size:1rem; color:var(--accent); flex-shrink:0; min-width:1.5rem; }
.gb-comp-name { color:var(--ink-lt); }

/* ── Review Ticker ── */
.gb-ticker-wrap { background:#f5a623; overflow:hidden; padding:0; }
.gb-ticker-hidden { display:none; }
.gb-ticker-empty { min-height:80px; display:flex; align-items:center; justify-content:center; }
.gb-ticker-track { display:flex; will-change:transform; }
.gb-ticker-item { flex-shrink:0; width:480px; padding:1.6rem 2.5rem; text-align:center; box-sizing:border-box; }
.gb-ticker-quote { font-family:'DINBlack',sans-serif; font-size:1.05rem; color:#fff; font-style:italic; line-height:1.45; }
.gb-ticker-author { margin-top:.5rem; font-size:.7rem; color:rgba(255,255,255,.75); text-transform:uppercase; letter-spacing:.07em; }
.gb-ticker-dot { flex-shrink:0; display:flex; align-items:center; color:rgba(255,255,255,.3); font-size:1.4rem; padding:0 .25rem; align-self:center; }
/* Admin ticker edit card */
.gb-ticker-item-edit { position:relative; cursor:pointer; transition:background .15s; }
.gb-ticker-item-edit:hover { background:rgba(0,0,0,.12); border-radius:6px; }
.gb-ticker-item-edit.gb-ticker-active { background:rgba(0,0,0,.2); border-radius:6px; }
.gb-ticker-item-ph { border:2px dashed rgba(255,255,255,.35); border-radius:8px; opacity:.55; }
.gb-ticker-item-ph:hover { opacity:.9; }
/* Inline edit card (replaces card content in place) */
.gb-ticker-item-editing { width:480px; background:rgba(0,0,0,.22); border-radius:8px; padding:1rem 1.2rem; display:flex; flex-direction:column; gap:.55rem; cursor:default; }
.gb-ticker-edit-ta  { font-family:inherit; font-size:.95rem; padding:.5rem .7rem; border:none; border-radius:6px; resize:none; outline:none; width:100%; box-sizing:border-box; line-height:1.5; background:#fff; color:#1a3a5c; }
.gb-ticker-edit-inp { font-family:inherit; font-size:.8rem; padding:.4rem .7rem; border:none; border-radius:6px; outline:none; width:100%; box-sizing:border-box; background:rgba(255,255,255,.85); color:#1a3a5c; }
.gb-ticker-edit-ta:focus, .gb-ticker-edit-inp:focus { box-shadow:0 0 0 2px rgba(255,255,255,.7); }
.gb-ticker-edit-actions { display:flex; gap:.4rem; flex-wrap:wrap; }
.btn-adm-danger { background:#e53e3e !important; color:#fff !important; }

/* ── Reviews ── */
.gb-reviews { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px,1fr)); gap:1rem; }
.gb-review {
  background:var(--surface); border:1px solid var(--border); border-radius:10px;
  padding:1rem 1.1rem; position:relative;
}
.gb-review-quote { font-size:.9rem; line-height:1.6; color:var(--ink-lt); font-style:italic; margin-bottom:.6rem; }
.gb-review-quote::before { content:'\201C'; font-size:1.8rem; color:var(--accent); line-height:0; vertical-align:-.45em; margin-right:.1rem; }
.gb-review-author { font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); }
/* ── Video Reviews ── */
.gb-vreviews { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px,1fr)); gap:1.2rem; }
.gb-vreview { background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,.08); }
.gb-vreview-wrap { position:relative; padding-top:56.25%; }
.gb-vreview-wrap iframe { position:absolute; inset:0; width:100%; height:100%; border:none; }
.gb-vreview-wrap video { position:absolute; inset:0; width:100%; height:100%; object-fit:contain; background:#000; }
.gb-vreview-label { font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); padding:.5rem .75rem .6rem; }
/* ── Video Review tile editor (admin) ── */
.gb-vrev-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:.75rem; min-height:unset; padding:0; }
body.gb-admin-mode .gb-vrev-grid { display:grid; }
.gb-vrev-tile { position:relative; border-radius:8px; overflow:hidden; aspect-ratio:16/9; cursor:pointer; background:#111; box-shadow:0 2px 8px rgba(0,0,0,.18); }
.gb-vrev-tile img { width:100%; height:100%; object-fit:cover; display:block; }
.gb-vrev-tile-label { position:absolute; bottom:0; left:0; right:0; background:rgba(0,0,0,.6); color:#fff; font-family:'DINBlack',sans-serif; font-size:.6rem; text-transform:uppercase; letter-spacing:.06em; padding:.3rem .4rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.gb-vrev-tile-del { position:absolute; top:.25rem; right:.25rem; background:rgba(0,0,0,.55); color:#fff; border:none; border-radius:50%; width:18px; height:18px; font-size:.65rem; line-height:18px; text-align:center; cursor:pointer; display:none; padding:0; }
.gb-vrev-tile:hover .gb-vrev-tile-del { display:block; }
.gb-vrev-empty { border:2px dashed #aac; border-radius:8px; aspect-ratio:16/9; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:.3rem; cursor:pointer; opacity:.55; transition:opacity .15s; }
.gb-vrev-empty:hover { opacity:1; }
.gb-vrev-add-icon { font-size:1.4rem; color:#88a; }
.gb-vrev-add-label { font-family:'DINBlack',sans-serif; font-size:.6rem; text-transform:uppercase; letter-spacing:.08em; color:#88a; }
/* inline editing inside the tile */
.gb-vrev-editing { aspect-ratio:unset !important; min-height:128px; background:#f5f7fa !important; display:flex !important; flex-direction:column; align-items:stretch; justify-content:flex-start; padding:.65rem; gap:.4rem; cursor:default; opacity:1 !important; }
.gb-vrev-editing .gb-vrev-inp { width:100%; box-sizing:border-box; padding:.38rem .55rem; border:1px solid #dde; border-radius:5px; font-size:.78rem; outline:none; background:#fff; color:#1e293b; }
.gb-vrev-editing .gb-vrev-inp:focus { border-color:#7c9eff; }
.gb-vrev-editing .gb-vrev-inline-btns { display:flex; gap:.4rem; margin-top:.1rem; }
.gb-vrev-editing .gb-vrev-btn-save { padding:.32rem .7rem; border-radius:5px; border:none; background:#4ade80; color:#0f172a; font-size:.72rem; font-weight:700; cursor:pointer; }
.gb-vrev-editing .gb-vrev-btn-cancel { padding:.32rem .7rem; border-radius:5px; border:none; background:#e2e8f0; color:#334155; font-size:.72rem; font-weight:700; cursor:pointer; }

/* ── Edit Details: image row upload button ── */
.gbed-img-row { display:flex; gap:.4rem; align-items:center; }
.gbed-img-row .gbed-field { flex:1; min-width:0; }
.gbed-upload-btn { flex-shrink:0; padding:.3rem .5rem; border:1px solid var(--border); border-radius:5px; background:#f8f9fa; cursor:pointer; font-size:.9rem; line-height:1; transition:background .13s; }
.gbed-upload-btn:hover { background:#e8eaed; }
.gbed-upload-btn.gbed-uploading { opacity:.5; cursor:wait; }

/* ── Price tag: in-place editing (admin) ── */
.gb-cta-ph { opacity:.45; font-style:italic; }
body.gb-admin-mode #gbPriceBox { cursor:text; border-style:dashed; }
body.gb-admin-mode #gbEditPrice { outline:none; min-width:3rem; }
body.gb-admin-mode #gbPriceBox.gb-price-ph #gbEditPrice::before { content:attr(data-ph); opacity:.4; pointer-events:none; display:inline; }
body.gb-admin-mode #gbPriceBox:focus-within,
body.gb-admin-mode #gbPriceBox:hover { outline:2px solid rgba(255,255,255,.75); border-style:dashed; }
/* ── Buy links: in-place editing (admin) ── */
body.gb-admin-mode .gb-buy-pub { display:none; }
body.gb-admin-mode #gbBuyCtas { display:contents; }
.gb-buy-cta-wrap { position:relative; display:inline-flex; align-items:center; }
.gb-buy-cta-del { position:absolute; top:-5px; right:-5px; width:16px; height:16px; border-radius:50%; background:rgba(0,0,0,.6); color:#fff; border:none; font-size:.55rem; line-height:16px; text-align:center; cursor:pointer; padding:0; display:none; z-index:2; }
.gb-buy-cta-wrap:hover .gb-buy-cta-del { display:block; }
/* Inline-editable button: label + link icon */
.gb-buy-cta-inner { display:inline-flex; align-items:center; gap:.35rem; height:2.75rem; padding:0 .85rem; border:2px solid currentColor; border-radius:6px; font-family:'DINBlack',sans-serif; font-size:.72rem; font-weight:900; letter-spacing:.07em; text-transform:uppercase; cursor:default; white-space:nowrap; box-sizing:border-box; }
.gb-buy-cta-inner.filled { background:#fff; color:#1a3a5c; }
a.gb-buy-cta-inner { text-decoration:none; cursor:pointer; }
.gb-buy-cta-ph .gb-buy-cta-inner { border-style:dashed; opacity:.6; }
.gb-buy-cta-lbl { outline:none; min-width:2.5rem; cursor:text; }
.gb-buy-cta-lbl:focus { text-decoration:underline; text-underline-offset:2px; }
.gb-buy-link-icon { background:none; border:none; padding:0; cursor:pointer; color:inherit; opacity:.5; display:inline-flex; align-items:center; flex-shrink:0; transition:opacity .15s; }
.gb-buy-link-icon:hover { opacity:1; }
/* Inline URL entry — replaces label inside the button */
.gb-buy-url-inline { border:none; outline:none; background:transparent; font-family:'DINBlack',sans-serif; font-weight:900; font-size:.72rem; letter-spacing:.07em; min-width:9rem; width:9rem; color:inherit; text-transform:none; }
.gb-buy-url-ok-inline { background:none; border:none; padding:0 0 0 .15rem; cursor:pointer; color:inherit; font-size:1rem; font-weight:900; opacity:.7; }
.gb-buy-url-ok-inline:hover { opacity:1; }

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
  display:inline-block;
  font-family:'DINBlack',sans-serif; font-size:.78rem; text-transform:uppercase;
  letter-spacing:.07em; height:2.75rem; line-height:2.75rem; padding:0 1.2rem; border-radius:7px;
  background:var(--accent); color:#fff; border:none; cursor:pointer;
  transition:background .13s;
}
.btn-buy:hover { background:var(--accent-dk); }

/* ── Resource links ── */
.gb-links { display:flex; flex-wrap:wrap; gap:.6rem; }
.btn-link {
  display:inline-flex; align-items:center; gap:.35rem;
  height:2.75rem; padding:0 .85rem; border-radius:6px; box-sizing:border-box;
  font-family:'DINBlack',sans-serif; font-size:.72rem; font-weight:900;
  letter-spacing:.07em; text-transform:uppercase;
  border:2px solid var(--accent); color:var(--accent); background:transparent;
  text-decoration:none; white-space:nowrap;
  transition:background .12s, color .12s; cursor:pointer;
}
.btn-link:hover { background:var(--accent); color:#fff; }
.gb-rl-drop-target .gb-buy-cta-inner { outline:2px dashed var(--accent); outline-offset:2px; }
/* Custom resource link pill buttons — match admin gb-buy-cta-inner (outlined) */
.gb-res-btn {
  display:inline-flex; align-items:center; gap:.35rem;
  height:2.75rem; padding:0 .85rem; border-radius:6px;
  border:2px solid currentColor; color:var(--ink);
  font-family:'DINBlack',sans-serif; font-size:.72rem; font-weight:900;
  letter-spacing:.07em; text-transform:uppercase;
  text-decoration:none; white-space:nowrap; box-sizing:border-box;
  transition:background .12s, color .12s; cursor:pointer;
}
.gb-res-btn:hover { background:var(--ink); color:var(--surface); }
/* Coming Soon section */
.gb-coming-soon { text-align:center; padding:4rem 2rem; }
.gb-cs-badge { display:inline-block; font-family:'DINBlack',sans-serif; font-size:.8rem; letter-spacing:.12em; text-transform:uppercase; color:var(--accent); border:2px solid var(--accent); border-radius:2rem; padding:.4rem 1.2rem; margin-bottom:1.2rem; }
.gb-cs-text { font-size:1.1rem; color:var(--ink-lt); margin:0; }

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
/* Add-game card (admin only) — matches game card shape: grey image area + white body */
.gb-add-game-card { display:none; border-radius:12px; border:2px dashed rgba(59,59,170,.22); background:#fff; cursor:pointer; transition:border-color .15s, box-shadow .15s, transform .15s; overflow:hidden; box-shadow:0 2px 14px rgba(0,0,0,.06); }
body.gb-admin-mode .gb-add-game-card { display:block; }
.gb-add-game-card:hover { border-color:var(--accent); box-shadow:0 6px 24px rgba(0,0,0,.12); transform:translateY(-3px); }
.gb-add-game-card-inner { width:100%; aspect-ratio:1; display:flex; align-items:center; justify-content:center; font-size:3rem; color:rgba(59,59,170,.28); line-height:1; transition:color .15s, background .15s; background:linear-gradient(135deg,#e8e8f2,#d8d8ec); }
.gb-add-game-card:hover .gb-add-game-card-inner { color:var(--accent); background:linear-gradient(135deg,#e0e0f0,#cecee8); }
.gb-add-game-card-label { padding:.75rem .9rem 1rem; font-family:'DINBlack',sans-serif; font-size:.95rem; color:rgba(59,59,170,.45); }
.gb-add-game-card:hover .gb-add-game-card-label { color:var(--accent); }

/* ── Footer (shared) ── */

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

/* ── Admin edit mode ── */
.gb-admin-bar { display:none; }  /* replaced by in-nav admin controls */
/* Save toast */
.gb-save-toast {
  display:none; position:fixed; bottom:1.5rem; right:1.5rem; z-index:900;
  background:#1e1e30; border:1px solid rgba(124,158,255,.3); border-radius:8px;
  padding:.65rem 1.1rem; font-family:'DINBlack',sans-serif; font-size:.78rem;
  text-transform:uppercase; letter-spacing:.07em; color:#7c9eff;
  box-shadow:0 4px 20px rgba(0,0,0,.4); animation:gb-toast-in .2s ease;
}
.gb-save-toast.visible { display:block; }
@keyframes gb-toast-in { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
.gb-admin-label {
  font-family:'DINBlack',sans-serif; font-size:.66rem; text-transform:uppercase;
  letter-spacing:.1em; color:#7c9eff; opacity:.8;
}
/* In-nav admin controls — hidden until admin mode activates */
.gb-nav-admin { display:none; align-items:center; gap:.5rem; flex-shrink:0; }
body.gb-admin-mode .gb-nav-admin { display:flex; }
body.gb-admin-mode .gb-nav-links { display:none; }
/* User icon + menu (game detail page) */
.btn-user-icon {
  width:30px; height:30px; border-radius:50%; border:1.5px solid rgba(255,255,255,.3);
  background:transparent; color:#fff; cursor:pointer; display:flex; align-items:center;
  justify-content:center; transition:background .14s, border-color .14s; flex-shrink:0;
}
.btn-user-icon:hover { background:rgba(255,255,255,.15); border-color:#fff; }
.btn-user-icon.signed-in { border-color:<?= $_template === 'opensea' ? '#00e5ff' : '#7c9eff' ?>; color:<?= $_template === 'opensea' ? '#00e5ff' : '#7c9eff' ?>; background:rgba(255,255,255,.1); }
.user-menu-wrap { position:relative; }
.user-menu {
  display:none; position:absolute; top:calc(100% + 6px); right:0;
  background:#1e1e30; border:1px solid rgba(255,255,255,.12); border-radius:8px;
  min-width:140px; box-shadow:0 6px 24px rgba(0,0,0,.4); overflow:hidden; z-index:200;
}
.user-menu.open { display:block; }
.user-menu-item {
  display:block; width:100%; text-align:left; padding:.55rem 1rem;
  font-family:'DINRegular',sans-serif; font-size:.82rem; color:rgba(255,255,255,.8);
  background:none; border:none; cursor:pointer; transition:background .12s;
}
.user-menu-item:hover { background:rgba(255,255,255,.08); }
.user-menu-divider { border:none; border-top:1px solid rgba(255,255,255,.1); margin:2px 0; }
/* Overlay dialogs (sign-in, settings, publish) */
.gb-overlay {
  display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:500;
  align-items:center; justify-content:center; padding:1.5rem;
}
.gb-overlay.open { display:flex; }
.gb-dialog {
  background:#fff; border-radius:12px; padding:1.6rem 1.75rem 1.5rem;
  width:100%; max-width:360px; box-shadow:0 12px 48px rgba(0,0,0,.35); position:relative;
}
.gb-dialog-title { font-family:'DINBlack',sans-serif; font-size:1rem; text-transform:uppercase; letter-spacing:.07em; color:var(--ink); margin-bottom:1rem; }
.gb-field-group { margin-bottom:.7rem; }
.gb-field-group label { display:block; font-size:.75rem; color:var(--muted); margin-bottom:.25rem; }
.gb-field { display:block; width:100%; padding:.55rem .75rem; border:1.5px solid var(--border); border-radius:7px; font-family:'DINRegular',sans-serif; font-size:.9rem; color:var(--ink); outline:none; transition:border-color .14s; }
.gb-field:focus { border-color:var(--accent); }
.gb-err { font-size:.78rem; color:#e53935; min-height:1.1rem; margin:.3rem 0; }
.gb-dialog-close { position:absolute; top:.75rem; right:.9rem; background:none; border:none; font-size:1.35rem; color:var(--ink-lt); cursor:pointer; line-height:1; }
.gb-btn { font-family:'DINBlack',sans-serif; font-size:.75rem; text-transform:uppercase; letter-spacing:.07em; padding:.42rem 1rem; border-radius:6px; border:1.5px solid var(--border); background:var(--surface); color:var(--ink); cursor:pointer; }
.gb-btn:hover { background:#f0f0f8; }
.gb-btn-sm { font-family:'DINBlack',sans-serif; font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; padding:.3rem .65rem; border-radius:5px; border:1.5px solid var(--border); background:var(--surface); color:var(--ink-lt); cursor:pointer; white-space:nowrap; }
.gb-btn-sm:hover { background:#f0f0f8; color:var(--ink); }
.gb-dialog-actions { display:flex; gap:.5rem; justify-content:flex-end; margin-top:.9rem; }
.btn-cancel { font-family:'DINBlack',sans-serif; font-size:.75rem; text-transform:uppercase; letter-spacing:.07em; padding:.42rem .9rem; border-radius:6px; border:1.5px solid var(--border); background:var(--surface); color:var(--ink-lt); cursor:pointer; }
.btn-cancel:hover { background:#f0f0f8; }
.btn-primary { font-family:'DINBlack',sans-serif; font-size:.75rem; text-transform:uppercase; letter-spacing:.07em; padding:.42rem .9rem; border-radius:6px; border:none; background:var(--accent); color:#fff; cursor:pointer; transition:opacity .13s; }
.btn-primary:disabled { opacity:.45; cursor:default; }
.btn-primary:not(:disabled):hover { opacity:.88; }
#siConfirmPanel { display:none; }
/* gbed-scroll for settings dialog */
.gbed-scroll { max-height:calc(80vh - 180px); overflow-y:auto; }
.btn-adm {
  font-family:'DINBlack',sans-serif; font-size:.68rem; text-transform:uppercase;
  letter-spacing:.07em; padding:.3rem .75rem; border-radius:5px; cursor:pointer;
  transition:background .13s, opacity .13s;
}
.btn-adm-primary { background:#7c9eff; color:#0d1b2a; border:none; }
.btn-adm-primary:hover:not(:disabled) { background:#9eb8ff; }
.btn-adm-primary:disabled { opacity:.45; cursor:default; }
.btn-adm-secondary { background:transparent; border:1.5px solid rgba(255,255,255,.25); color:rgba(255,255,255,.75); }
.btn-adm-secondary:hover { background:rgba(255,255,255,.08); border-color:#fff; }
.btn-adm-details { background:rgba(124,158,255,.15); border:1.5px solid rgba(124,158,255,.4); color:#7c9eff; }
.btn-adm-details:hover { background:rgba(124,158,255,.25); }
/* Admin inline stat editor */
.gb-stats-edit { display:flex; flex-wrap:wrap; gap:.5rem .75rem; margin:.4rem 0 .2rem; }
.gb-stat-field { display:flex; align-items:center; gap:.3rem; background:rgba(0,0,0,.25); border:1px solid rgba(255,255,255,.15); border-radius:6px; padding:.3rem .55rem; }
.gb-stat-icon { width:14px; height:14px; stroke:rgba(255,255,255,.55); flex-shrink:0; }
.gb-stat-input { background:transparent; border:none; outline:none; color:#fff; font-size:.8rem; width:90px; font-family:inherit; }
.gb-stat-num { width:36px; text-align:center; -moz-appearance:textfield; }
.gb-stat-num::-webkit-inner-spin-button, .gb-stat-num::-webkit-outer-spin-button { -webkit-appearance:none; }
.gb-stat-sep { color:rgba(255,255,255,.4); font-size:.8rem; }
.gb-stat-unit { color:rgba(255,255,255,.55); font-size:.75rem; white-space:nowrap; }
.gb-stat-input::placeholder { color:rgba(255,255,255,.3); }
.gb-stat-field:focus-within { border-color:rgba(124,158,255,.6); background:rgba(0,0,0,.35); }
.gb-stat-field:focus-within .gb-stat-icon { stroke:rgba(124,158,255,.8); }
/* Stat icon pip inside public pills */
.gb-stat-pip { width:12px; height:12px; stroke:rgba(255,255,255,.7); flex-shrink:0; margin-right:4px; vertical-align:middle; }
.gb-stat { display:inline-flex; align-items:center; }
/* Role icon (gear/brush) before designer/artist inputs */
.gb-role-icon { width:13px; height:13px; stroke:rgba(255,255,255,.5); flex-shrink:0; margin-right:5px; }
/* Designer/artist rows */
.gb-stat-designers { font-size:.85rem; color:rgba(255,255,255,.75); margin-top:.2rem; display:flex; flex-wrap:wrap; align-items:center; gap:.25rem; min-height:1.6em; }
/* Public credits row */
.gb-stat-credits { display:flex; flex-wrap:wrap; gap:.5rem 1.2rem; margin-top:.25rem; }
.gb-credit-item { display:inline-flex; align-items:center; font-size:.85rem; color:rgba(255,255,255,.75); }
/* Inline designer inputs */
.gb-designer-input {
  background:transparent; border:none; border-bottom:1.5px dashed rgba(255,255,255,.45);
  color:#fff; font-family:'DINRegular',Arial,sans-serif; font-size:inherit;
  width:110px; outline:none; padding:0 2px; margin-right:4px;
  transition:border-color .15s, width .15s;
}
.gb-designer-input:focus { border-bottom-color:#fff; }
.gb-designer-input::placeholder { color:rgba(255,255,255,.35); font-style:italic; }
/* Editable field highlights in admin mode */
body.gb-admin-mode #gbEditTitle { outline:2px dashed rgba(124,158,255,.5); border-radius:3px; cursor:text; padding:2px 4px; }
body.gb-admin-mode #gbEditTitle:focus,
body.gb-admin-mode #gbEditTitle:hover { outline:2px solid #7c9eff; }
body.gb-admin-mode #gbEditSummary { outline:2px dashed rgba(255,255,255,.3); border-radius:3px; cursor:text; padding:4px 6px; min-height:2em; display:block; }
body.gb-admin-mode #gbEditSummary:focus,
body.gb-admin-mode #gbEditSummary:hover { outline:2px solid rgba(255,255,255,.7); }
body.gb-admin-mode #gbEditDesc { outline:2px dashed rgba(59,59,170,.4); border-radius:4px; cursor:text; padding:4px 6px; }
body.gb-admin-mode #gbEditDesc:focus,
body.gb-admin-mode #gbEditDesc:hover { outline:2px solid var(--accent); background:rgba(59,59,170,.04); }
/* Edit details overlay */
.gbed-overlay {
  display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:600;
  align-items:flex-start; justify-content:center; padding:2rem 1rem; overflow-y:auto;
}
.gbed-overlay.open { display:flex; }
.gbed-dialog {
  background:#fff; border-radius:14px; width:100%; max-width:520px;
  box-shadow:0 12px 50px rgba(0,0,0,.32); overflow:hidden; margin:auto;
}
.gbed-header {
  background:var(--nav-bg); color:#fff; padding:1rem 1.25rem;
  display:flex; align-items:center; justify-content:space-between;
}
.gbed-title { font-family:'DINBlack',sans-serif; font-size:.9rem; text-transform:uppercase; letter-spacing:.07em; }
.gbed-close { background:none; border:none; color:rgba(255,255,255,.6); font-size:1.2rem; cursor:pointer; padding:0 .25rem; line-height:1; }
.gbed-close:hover { color:#fff; }
.gbed-body { padding:1.25rem 1.5rem 1.5rem; max-height:calc(80vh - 120px); overflow-y:auto; }
.gbed-section { margin-bottom:1.1rem; }
.gbed-section-title {
  font-family:'DINBlack',sans-serif; font-size:.65rem; text-transform:uppercase;
  letter-spacing:.1em; color:var(--muted); border-bottom:1px solid var(--border);
  padding-bottom:.35rem; margin-bottom:.65rem;
}
.gbed-row { display:flex; gap:.6rem; margin-bottom:.55rem; }
.gbed-row > * { flex:1; min-width:0; }
.gbed-field-group { margin-bottom:.55rem; }
.gbed-label { display:block; font-size:.73rem; color:var(--muted); margin-bottom:.2rem; }
.gbed-field {
  display:block; width:100%; padding:.45rem .7rem;
  border:1.5px solid var(--border); border-radius:6px;
  font-family:'DINRegular',Arial,sans-serif; font-size:.87rem; color:var(--ink);
  outline:none; transition:border-color .14s;
}
.gbed-field:focus { border-color:var(--accent); }
.gbed-footer {
  padding:.9rem 1.5rem; background:#f8f8fc; border-top:1px solid var(--border);
  display:flex; gap:.5rem; justify-content:flex-end;
}
/* ── Placeholder content (admin only) ── */
/* CSS placeholder for empty contenteditable fields */
body.gb-admin-mode [contenteditable][data-ph]:empty::before {
  content: attr(data-ph);
  opacity: .35; font-style: italic; pointer-events: none; font-family:'DINRegular',Arial,sans-serif;
}
/* Sections that only show in admin mode when empty */
.gb-ph-section { display: none; }
body.gb-admin-mode .gb-ph-section { display: block; }
/* Placeholder stat chips */
.gb-ph-stat { display: none; }
body.gb-admin-mode .gb-ph-stat { display: flex; }
.gb-ph-stat strong { opacity: .5; }
.gb-ph-stat span { opacity: .35; font-style: italic; }
/* Cover image button (top-left of hero) */
.gb-cover-btn { position: absolute; top: .75rem; right: .75rem; z-index: 6; display: none; }
body.gb-admin-mode .gb-hero.no-cover .gb-cover-btn { display: flex; }
body.gb-admin-mode .gb-hero:not(.no-cover) .gb-cover-btn { display: none; }
body.gb-admin-mode .gb-hero:not(.no-cover):hover .gb-cover-btn,
body.gb-admin-mode .gb-hero.gb-drag-over .gb-cover-btn { display: flex; }
/* Hero image upload spinner */
.gb-hero.gb-cover-loading::after { content:''; position:absolute; top:50%; left:50%; width:44px; height:44px; margin:-22px 0 0 -22px; border:4px solid rgba(255,255,255,.25); border-top-color:#fff; border-radius:50%; animation:gb-spin .7s linear infinite; z-index:20; pointer-events:none; }
.gb-hero.gb-cover-loading .gb-hero-bg { filter:brightness(.45); transition:filter .2s; }
/* Gallery upload zones */
.gb-upload-zone {
  position: relative; cursor: pointer; transition: background .13s, border-color .13s, opacity .13s;
  opacity: .35; border: 2px dashed rgba(59,59,170,.25);
}
.gb-upload-zone:hover,
.gb-upload-zone.gb-drag-over { opacity: .7; border-color: rgba(124,158,255,.6); background: rgba(124,158,255,.08); }
.gb-upload-zone.gb-uploading { opacity: .5; cursor: wait; }
.gb-upload-zone.gb-uploaded { opacity: 1; border: none; background: none; padding: 0; font-size: 0; }
.gb-upload-zone.gb-uploaded img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; display: block; }
/* Placeholder lorem sections */
.gb-ph-lorem {
  font-size:.93rem; line-height:1.75; color:var(--ink-lt); opacity:.35;
  font-style:italic; white-space:pre-wrap;
  border:2px dashed rgba(59,59,170,.2); border-radius:6px; padding:.75rem 1rem;
}
/* ── Footer (shared) ── */
.gb-footer { background:#1c1c1e; color:rgba(255,255,255,.5); }
.gb-footer-main { max-width:1100px; margin:0 auto; padding:3.5rem 2rem 3rem; display:grid; grid-template-columns:1fr 1.15fr; gap:2.5rem 5rem; align-items:start; }
@media(max-width:680px){ .gb-footer-main { grid-template-columns:1fr; padding:2.5rem 1.5rem 2.5rem; gap:2rem; } }
.gb-footer-brand { display:flex; flex-direction:column; gap:.7rem; }
.gb-footer-logo-img { height:52px; object-fit:contain; object-position:left center; display:block; }
.gb-footer-bar-brand { font-family:'DINBlack',sans-serif; font-size:1.15rem; color:rgba(255,255,255,.9); }
.gb-footer-brand-tagline { font-size:.83rem; color:rgba(255,255,255,.42); line-height:1.65; }
.gb-footer-brand-icons { display:flex; gap:.45rem; align-items:center; }
.gb-footer-brand-copy { display:flex; flex-direction:column; gap:.18rem; font-size:.71rem; color:rgba(255,255,255,.28); margin-top:.15rem; }
.gb-footer-game-credit { font-size:.68rem; color:rgba(255,255,255,.2); }
.gb-footer-sub-label { font-family:'DINBlack',sans-serif; font-size:1rem; color:#fff; margin-bottom:.3rem; }
.gb-footer-sub-desc { font-size:.82rem; color:rgba(255,255,255,.42); margin-bottom:1rem; line-height:1.55; }
.gb-footer-sub-form { display:flex; gap:.4rem; }
.gb-footer-sub-input { flex:1; min-width:0; padding:.62rem 1rem; border:none; border-radius:999px; background:rgba(255,255,255,.1); color:#fff; font-size:.88rem; outline:none; transition:background .18s; }
.gb-footer-sub-input::placeholder { color:rgba(255,255,255,.35); }
.gb-footer-sub-input:focus { background:rgba(255,255,255,.16); }
.gb-footer-sub-btn { padding:.62rem 1.4rem; border:none; border-radius:999px; background:var(--accent); color:#fff; font-family:'DINBlack',sans-serif; font-size:.78rem; letter-spacing:.05em; cursor:pointer; transition:opacity .18s; white-space:nowrap; }
.gb-footer-sub-btn:hover { opacity:.85; }
.gb-footer-sub-btn:disabled { opacity:.55; cursor:default; }
.gb-footer-sub-msg { margin-top:.55rem; font-size:.77rem; min-height:1em; }
.gb-footer-sub-msg.ok  { color:#6bd98a; }
.gb-footer-sub-msg.err { color:#e07070; }
.gb-social-btn { width:1.85rem; height:1.85rem; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:border-color .14s,background .14s; cursor:pointer; padding:0; }
.gb-social-btn svg { fill:rgba(255,255,255,.6); pointer-events:none; }
.gb-social-set { border:1.5px solid rgba(255,255,255,.25); background:transparent; }
.gb-social-set:hover { border-color:rgba(255,255,255,.6); background:rgba(255,255,255,.08); }
.gb-social-empty { display:none; }
</style>
</head>
<body>

<!-- ── Nav ── -->
<nav class="gb-nav">
  <div class="gb-nav-left">
    <a class="gb-nav-back" href="<?= h($_gbBase) ?>" title="Back to all games">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      <span class="gb-nav-back-label">All Games</span>
    </a>
    <span class="gb-nav-sep">|</span>
    <span class="gb-nav-game" id="gbNavGame"><?= h($_name) ?></span>
  </div>
  <!-- Regular nav links (hidden in admin mode) -->
  <div class="gb-nav-links" id="gbNavLinks">
    <?php if ($_richRules): ?><a class="btn-nav" href="<?= h($_richRules) ?>" target="_blank">Rules</a><?php endif; ?>
    <?php if ($_sellsheet): ?><a class="btn-nav" href="<?= h($_sellsheet) ?>" target="_blank">Sellsheet</a><?php endif; ?>
    <?php if ($_pageUrl): ?><a class="btn-nav filled" href="<?= h($_pageUrl) ?>" target="_blank">View Page ↗</a><?php endif; ?>
  </div>
  <!-- Admin controls (shown in admin mode, replaces nav links) -->
  <div class="gb-nav-admin" id="gbAdminBar">
    <span class="gb-admin-label">✏ Admin</span>
    <button class="btn-adm btn-adm-details" onclick="openEditDetails()">Edit Details</button>
    <button class="btn-adm btn-adm-primary" id="gbSaveBtn" onclick="saveGame()">Save Changes</button>
    <button class="btn-adm btn-adm-secondary" onclick="cancelEdit()">Cancel</button>
    <div class="user-menu-wrap">
      <button class="btn-user-icon signed-in" id="gbUserBtn" onclick="toggleGbMenu(event)" title="Menu">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </button>
      <div class="user-menu" id="gbMenu">
        <button class="user-menu-item" onclick="gbMenuAction('settings')">Settings</button>
        <hr class="user-menu-divider" />
        <button class="user-menu-item" onclick="gbMenuAction('fetch')">Fetch</button>
        <button class="user-menu-item" onclick="gbMenuAction('publish')">🌐 Publish Site</button>
        <hr class="user-menu-divider" />
        <button class="user-menu-item" id="gbMenuSignInOut" onclick="gbMenuAction('signout')">Sign Out</button>
      </div>
    </div>
  </div>
</nav>

<!-- Legacy admin bar slot (kept for JS compat, hidden) -->
<div class="gb-admin-bar" id="gbAdminBarLegacy" style="display:none"></div>

<!-- ── Hidden file inputs for image uploads ── -->
<input type="file" id="gbFileInput" accept="image/*" style="display:none" onchange="gbHandleFileInput(event)">
<input type="file" id="gbedFileInput" accept="image/*" style="display:none" onchange="gbedHandleFileInput(event)">

<!-- ── Hero ── -->
<div class="gb-hero<?= $_heroImg ? '' : ' no-cover' ?>" id="gbHero">
  <?php if ($_heroImg): ?>
  <div class="gb-hero-bg" id="gbHeroBg" style="background-image:url('<?= h($_heroImg) ?>')"></div>
  <?php else: ?>
  <div class="gb-hero-bg" id="gbHeroBg" style="background:linear-gradient(135deg,#1a1a2e 0%,#2d2d88 100%)"></div>
  <?php endif; ?>
  <!-- Cover image button — top-left, shown in admin mode (always when no cover, on hover when cover exists) -->
  <button class="gb-img-btn gb-cover-btn" onclick="gbPickImage(gbSetCover)" title="Change cover image"><?= $_imgBtnIcon ?></button>
  <div class="gb-hero-overlay"></div>
  <div class="gb-hero-content">
    <?php if ($_status && !isset($_gbPublicBase)): ?><div class="gb-status-badge" id="gbStatusBadge"><?= h(ucfirst($_status)) ?></div><?php endif; ?>
    <h1 class="gb-hero-title" id="gbEditTitle" data-ph="Game Title" onclick="event.stopPropagation()"><?= h($_name) ?></h1>
    <p class="gb-hero-summary" id="gbEditSummary" data-ph="Click to add a one-line tagline for this game…" onclick="event.stopPropagation()"><?= h($_summary) ?></p>
    <?php if (!isset($_gbPublicBase)): ?>
    <!-- Admin: inline stat inputs with icons -->
    <div class="gb-stats-edit" id="gbStatsEdit">
      <div class="gb-stat-field gb-stat-range">
        <svg class="gb-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <input class="gb-stat-input gb-stat-num" id="gbsiMinPlayers" type="number" min="1" max="99" placeholder="1" oninput="_ed.min_players=this.value.trim()" />
        <span class="gb-stat-sep">–</span>
        <input class="gb-stat-input gb-stat-num" id="gbsiMaxPlayers" type="number" min="1" max="99" placeholder="4" oninput="_ed.max_players=this.value.trim()" />
        <span class="gb-stat-unit">players</span>
      </div>
      <div class="gb-stat-field gb-stat-range">
        <svg class="gb-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <input class="gb-stat-input gb-stat-num" id="gbsiMinPlaytime" type="number" min="1" placeholder="30" oninput="_ed.min_playtime=this.value.trim()" />
        <span class="gb-stat-sep">–</span>
        <input class="gb-stat-input gb-stat-num" id="gbsiMaxPlaytime" type="number" min="1" placeholder="60" oninput="_ed.max_playtime=this.value.trim()" />
        <span class="gb-stat-unit">min</span>
      </div>
      <div class="gb-stat-field">
        <svg class="gb-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 16l3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1z"/><path d="M2 16l3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 4-1 7-1s5 1 7 1h2"/></svg>
        <input class="gb-stat-input" id="gbsiWeight" type="text" placeholder="Weight" oninput="_ed.weight=this.value.trim()" />
      </div>
    </div>
    <!-- Designer row (admin) -->
    <div class="gb-stat-designers" id="gbStatDesigners">
      <svg class="gb-role-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      <span id="gbDesignerInputs"></span>
    </div>
    <!-- Artist row (admin) -->
    <div class="gb-stat-designers" id="gbStatArtists">
      <svg class="gb-role-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9.06 11.9 8.07-8.06a2.85 2.85 0 1 1 4.03 4.03l-8.06 8.08"/><path d="M7.07 14.94c-1.66 0-3 1.35-3 3.02 0 1.33-2.5 1.52-2 2.02 1 1 2.48 1.02 3.5 1.02 2.2 0 3.96-1.77 3.96-3.96a.96.96 0 0 0-.96-.96c-.28-.01-.54-.14-.87-.14z"/></svg>
      <span id="gbArtistInputs"></span>
    </div>
    <?php else: ?>
    <!-- Public: show stat pills for non-empty values -->
    <div class="gb-stats" id="gbStatsRow">
      <?php if ($_count): ?>
      <div class="gb-stat"><svg class="gb-stat-pip" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><?= h($_count) ?> players</div>
      <?php endif; ?>
      <?php if ($_duration): ?>
      <div class="gb-stat"><svg class="gb-stat-pip" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><?= h($_duration) ?></div>
      <?php endif; ?>
      <?php if ($_weight):       ?><div class="gb-stat"><svg class="gb-stat-pip" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 16l3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1z"/><path d="M2 16l3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 4-1 7-1s5 1 7 1h2"/></svg><?= h($_weight) ?></div><?php endif; ?>
      <?php if ($_mechanic):    ?><div class="gb-stat"><?= h($_mechanic) ?></div><?php endif; ?>
      <?php if ($_ages):        ?><div class="gb-stat">Ages <?= h($_ages) ?></div><?php endif; ?>
      <?php if ($_series):      ?><div class="gb-stat"><?= h($_series) ?></div><?php endif; ?>
      <?php if ($_boxSize):     ?><div class="gb-stat"><?= h($_boxSize) ?></div><?php endif; ?>
      <?php if ($_availability):?><div class="gb-stat"><?= h($_availability) ?></div><?php endif; ?>
    </div>
    <?php if ($_designers || $_artists): ?>
    <div class="gb-stat-credits">
      <?php if ($_designers): ?><span class="gb-credit-item"><svg class="gb-role-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg><?= h(implode(', ', $_designers)) ?></span><?php endif; ?>
      <?php if ($_artists): ?><span class="gb-credit-item"><svg class="gb-role-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9.06 11.9 8.07-8.06a2.85 2.85 0 1 1 4.03 4.03l-8.06 8.08"/><path d="M7.07 14.94c-1.66 0-3 1.35-3 3.02 0 1.33-2.5 1.52-2 2.02 1 1 2.48 1.02 3.5 1.02 2.2 0 3.96-1.77 3.96-3.96a.96.96 0 0 0-.96-.96c-.28-.01-.54-.14-.87-.14z"/></svg><?= h(implode(', ', $_artists)) ?></span><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    <div class="gb-hero-ctas">
      <?php if ($_richPrice || !isset($_gbPublicBase)): ?>
      <span class="gb-price-tag<?= (!$_richPrice) ? ' gb-cta-ph' : '' ?>" id="gbPriceBox"><span id="gbEditPrice" data-ph="$0.00"><?= h($_richPrice) ?></span></span>
      <?php endif; ?>
      <?php /* Admin: JS-rendered editable buy buttons */ ?>
      <?php if (!isset($_gbPublicBase)): ?><span id="gbBuyCtas" style="display:none"></span><?php endif; ?>
      <?php /* Public: static buy buttons */ ?>
      <?php foreach ($_buyUrls as $b):
        $bUrl   = trim($b['Value']   ?? '');
        $bLabel = trim($b['Value 1'] ?? '') ?: 'Buy Now';
        if (!$bUrl && !$bLabel) continue;
      ?><?php if ($bUrl): ?><a class="gb-buy-cta-inner filled gb-buy-pub" href="<?= h($bUrl) ?>" target="_blank"><?= h($bLabel) ?></a><?php else: ?><span class="gb-buy-cta-inner filled gb-buy-pub"><?= h($bLabel) ?></span><?php endif; ?><?php endforeach; ?>
      <?php if ($_richRules): ?><a class="btn-nav" href="<?= h($_richRules) ?>" target="_blank">📖 Rules</a><?php endif; ?>
      <?php if ($_playUrl):   ?><a class="btn-nav" href="<?= h($_playUrl) ?>" target="_blank">🎮 Play Online</a><?php endif; ?>
      <?php if ($_printUrl):  ?><a class="btn-nav" href="<?= h($_printUrl) ?>" target="_blank">🖨 Print &amp; Play</a><?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Review Ticker ── -->
<div class="gb-ticker-wrap<?= (empty($_reviews) && !isset($_gbPublicBase)) ? ' gb-ticker-empty' : (empty($_reviews) ? ' gb-ticker-hidden' : '') ?>" id="gbTickerWrap">
  <div class="gb-ticker-track" id="gbTickerTrack">

    <?php foreach ([1,2] as $_pass): /* duplicate for seamless loop */ ?>
    <?php foreach ($_reviews as $rv):
      $rQ = trim($rv['Value'] ?? ''); $rA = trim($rv['Value 1'] ?? '');
      if (!$rQ) continue;
    ?>
    <div class="gb-ticker-item">
      <div class="gb-ticker-quote">"<?= h($rQ) ?>"</div>
      <?php if ($rA): ?><div class="gb-ticker-author">— <?= h($rA) ?></div><?php endif; ?>
    </div>
    <div class="gb-ticker-dot">✦</div>
    <?php endforeach; ?>
    <?php endforeach; ?>
  </div>

</div>

<!-- ── Main ── -->
<div class="gb-main">

  <!-- Coming Soon (public only, when no rich data published yet) -->
  <?php if (!$_hasRichData && isset($_gbPublicBase)): ?>
  <div class="gb-coming-soon">
    <div class="gb-cs-badge">Coming Soon</div>
    <p class="gb-cs-text">More details on this game are coming soon. Stay tuned!</p>
  </div>
  <?php endif; ?>

  <!-- Description (always rendered; hidden in public when empty via .gb-ph-section) -->
  <div class="gb-section<?= $_description ? '' : ' gb-ph-section' ?>">
    <div class="gb-section-label">About the Game</div>
    <?php if ($_description): ?>
    <p class="gb-description" id="gbEditDesc"><?= h($_description) ?></p>
    <?php else: ?>
    <p class="gb-description gb-ph-lorem" id="gbEditDesc" data-ph="Click to add a game description…">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.</p>
    <?php endif; ?>
  </div>

  <!-- Gallery -->
  <div class="gb-section<?= empty($_productImgUrls) && !isset($_gbPublicBase) ? ' gb-sect-empty' : (empty($_productImgUrls) ? ' gb-sect-hidden' : '') ?>" id="gbGallerySection">
    <div class="gb-section-label">Gallery</div>
    <div class="gb-gallery-viewer<?= empty($_productImgUrls) ? ' gb-gallery-empty' : '' ?>" id="gbGalleryViewer">
      <div class="gb-gallery-featured" id="gbGalleryFeatured">
        <img id="gbGalleryFeaturedImg" src="" alt="<?= h($_name) ?>" onload="this.style.display=''" onclick="openLightbox(this.src)" style="display:none" />
        <button class="gb-gallery-featured-del" title="Remove this image" onclick="gbGalleryDeleteFeatured()">✕</button>
      </div>
      <div class="gb-gallery-thumbs" id="gbGalleryThumbs">
        <?php foreach ($_productImgUrls as $i => $img): ?>
        <div class="gb-gallery-thumb<?= $i === 0 ? ' gb-gt-active' : '' ?>" onclick="gbGallerySelect(<?= $i ?>)">
          <img src="<?= h($img) ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>"<?= $i === 0 ? ' onload="(function(el){var f=document.getElementById(\'gbGalleryFeaturedImg\');if(f&&!f.naturalWidth){f.src=el.src;}})(this)"' : '' ?> />
          <button class="gb-gt-del" onclick="event.stopPropagation();gbGalleryDelete(<?= $i ?>)" title="Remove">✕</button>
        </div>
        <?php endforeach; ?>
        <div class="gb-gallery-thumb-add" id="gbGalleryAddBtn"
             onclick="gbPickGalleryImage()" title="Click or drop image here"
             ondragover="event.preventDefault();this.classList.add('gb-drag-over')"
             ondragleave="this.classList.remove('gb-drag-over')"
             ondrop="event.preventDefault();this.classList.remove('gb-drag-over');gbGalleryDropImage(event)"><span>+</span></div>
      </div>
    </div>
  </div>

  <!-- Video(s) -->
  <?php if (!$_videoEmbed && !$_videoDirect): ?>
  <!-- Placeholder video (admin only) -->
  <div class="gb-section gb-ph-section" id="gbVideoPlaceholder">
    <div class="gb-section-label">Watch</div>
    <div class="gb-video-ph-inner"
         onclick="gbVideoPhClick(event,this)"
         ondragover="gbVideoDragOver(event,this)"
         ondragleave="gbVideoDragLeave(event,this)"
         ondrop="gbVideoDrop(event,this)">
      <div class="gb-video-ph-icon" style="font-size:3rem;">▶</div>
      <div class="gb-video-ph-label" style="font-family:'DINBlack',sans-serif;font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;color:#fff;">Drop a video file or click to add URL</div>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($_videoEmbed || $_videoDirect): ?>
  <div class="gb-section" id="gbVideoSection">
    <div class="gb-section-label">Watch</div>
    <div class="gb-video-wrap" id="videoWrap">
      <?php if ($_videoEmbed): ?>
      <iframe id="videoFrame" src="<?= h($_videoEmbed) ?>" allow="autoplay; encrypted-media" allowfullscreen loading="lazy"></iframe>
      <?php else: ?>
      <video id="videoFrame" src="<?= h($_videoDirect) ?>" controls style="width:100%;border-radius:10px;display:block;"></video>
      <?php endif; ?>
      <button class="gb-video-del-btn" onclick="gbVideoRemoveClick()" title="Remove video">✕</button>
    </div>
  </div>
  <?php endif; ?>

  <!-- What's in the Box -->
  <div class="gb-section<?= empty($_components) ? ' gb-sect-empty' : '' ?>">
    <div class="gb-section-label">What's in the Box</div>
    <textarea class="gb-inline-edit" id="gbCompEdit"
      placeholder="One item per line:&#10;50 Cards&#10;1 Game Board&#10;6 Dice"></textarea>
    <p class="gb-inline-hint">One item per line — e.g. "50 Cards"</p>
    <div class="gb-components gb-display-content">
      <?php foreach ($_components as $c):
        $cVal = trim($c['Value'] ?? ''); if (!$cVal) continue;
        $cQty = ''; $cName = $cVal;
        if (preg_match('/^(\d+)\s+(.+)$/', $cVal, $pm)) { $cQty = $pm[1]; $cName = $pm[2]; }
      ?>
      <div class="gb-comp-item">
        <?php if ($cQty): ?><span class="gb-comp-qty"><?= h($cQty) ?></span><?php endif; ?>
        <span class="gb-comp-name"><?= h($cName) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Video Reviews -->
  <div class="gb-section<?= empty($_videoReviews) ? ' gb-sect-empty' : '' ?>">
    <div class="gb-section-label">Video Reviews</div>
    <!-- Admin: compact tile editor (JS-rendered) -->
    <div class="gb-vrev-grid gb-inline-edit" id="gbVrevGrid"></div>
    <!-- Public: full embedded players -->
    <div class="gb-vreviews gb-display-content">
      <?php foreach ($_videoReviews as $vr):
        $vrUrl   = trim($vr['Value']   ?? '');
        $vrName  = trim($vr['Value 1'] ?? '');
        if (!$vrUrl) continue;
        $vrEmbed = _gb_embed($vrUrl);
      ?>
      <div class="gb-vreview">
        <div class="gb-vreview-wrap">
          <?php if ($vrEmbed): ?>
          <iframe src="<?= h($vrEmbed) ?>" allow="autoplay; encrypted-media" allowfullscreen loading="lazy"></iframe>
          <?php else: ?>
          <video src="<?= h($vrUrl) ?>" controls></video>
          <?php endif; ?>
        </div>
        <?php if ($vrName): ?><div class="gb-vreview-label"><?= h($vrName) ?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Resources -->
  <?php
  $_hasHardLinks = $_richRules || $_sellsheet || $_playUrl || $_printUrl || $_pageUrl;
  $_hasLinks = $_hasHardLinks || !empty($_resourceLinks);
  ?>
  <div class="gb-section<?= (!$_hasLinks && !isset($_gbPublicBase)) ? ' gb-sect-empty' : (!$_hasLinks ? ' gb-sect-hidden' : '') ?>" id="gbResourcesSection">
    <div class="gb-section-label">Resources</div>
    <!-- Admin: JS-rendered inline buttons (same style as buy CTAs) -->
    <div class="gb-links" id="gbResLinks" style="display:none"></div>
    <!-- Public: static resource link pills -->
    <div class="gb-links gb-display-content">
      <?php if ($_richRules): ?><a class="btn-link" href="<?= h($_richRules) ?>" target="_blank">📖 Rules PDF</a><?php endif; ?>
      <?php if ($_sellsheet): ?><a class="btn-link" href="<?= h($_sellsheet) ?>" target="_blank">📄 Sellsheet</a><?php endif; ?>
      <?php if ($_playUrl):   ?><a class="btn-link" href="<?= h($_playUrl) ?>" target="_blank">🎮 Play Online</a><?php endif; ?>
      <?php if ($_printUrl):  ?><a class="btn-link" href="<?= h($_printUrl) ?>" target="_blank">🖨 Print &amp; Play</a><?php endif; ?>
      <?php if ($_pageUrl):   ?><a class="btn-link" href="<?= h($_pageUrl) ?>" target="_blank">🔗 Product Page</a><?php endif; ?>
      <?php foreach ($_resourceLinks as $rl):
        $rlUrl = trim($rl['Value'] ?? ''); $rlLabel = trim($rl['Value 1'] ?? '');
        if (!$rlUrl && !$rlLabel) continue;
      ?><?php if ($rlUrl): ?><a class="gb-res-btn" href="<?= h($rlUrl) ?>" target="_blank"><?= h($rlLabel ?: 'Link') ?></a><?php else: ?><span class="gb-res-btn"><?= h($rlLabel ?: 'Link') ?></span><?php endif; ?><?php endforeach; ?>
    </div>
  </div>

  <!-- FAQs -->
  <div class="gb-section<?= empty($_faqs) ? ' gb-sect-empty' : '' ?>">
    <div class="gb-section-label">FAQ</div>
    <div class="gb-faq-editor gb-inline-edit" id="gbFaqsList"></div>
    <?php foreach ($_faqs as $fi => $fq):
      $fqQ = trim($fq['Value']   ?? '');
      $fqA = trim($fq['Value 1'] ?? '');
      if (!$fqQ) continue;
    ?>
    <div class="gb-faq-item gb-display-content" id="faq<?= $fi ?>">
      <div class="gb-faq-q" onclick="toggleFaq('faq<?= $fi ?>')"><?= h($fqQ) ?></div>
      <div class="gb-faq-a"><?php if (strncmp($fqA, 'img:', 4) === 0): ?><img class="gb-faq-img" src="<?= h(substr($fqA, 4)) ?>" alt="" loading="lazy"><?php else: ?><?= nl2br(h($fqA)) ?><?php endif; ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- More Games -->
  <?php if (!empty($_otherGames) || !isset($_gbPublicBase)): ?>
  <div class="gb-section<?= empty($_otherGames) && !isset($_gbPublicBase) ? ' gb-sect-empty' : '' ?>">
    <div class="gb-section-label">More Games</div>
    <div class="gb-more-grid">
      <?php foreach (array_slice($_otherGames, 0, 8) as $og):
        $ogName = trim($og['Name'] ?? '');
        $ogImg  = _gb_img(trim($og['Cover URL'] ?? $og['Image URL'] ?? $og['Image'] ?? ''));
        $ogMeta = array_filter([trim($og['Count'] ?? '') ? trim($og['Count']).' players' : '', trim($og['Duration'] ?? '')]);
      ?>
      <a class="gb-more-card" href="<?= h($_gbBase . _gb_slug($ogName)) ?>">
        <?php if ($ogImg): ?>
        <img src="<?= h($ogImg) ?>" alt="<?= h($ogName) ?>" loading="lazy" />
        <?php else: ?>
        <div class="gb-more-card-ph"><img src="<?= h($_appRoot) ?>images/gb_logo.png" alt="" style="width:48px;height:48px;border-radius:10px;opacity:.35;" /></div>
        <?php endif; ?>
        <div class="gb-more-body">
          <div class="gb-more-name"><?= h($ogName) ?></div>
          <?php if ($ogMeta): ?><div class="gb-more-meta"><?= h(implode(' · ', $ogMeta)) ?></div><?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
      <!-- Add-game card: admin only, appended after existing games -->
      <?php if (!isset($_gbPublicBase)): ?>
      <div class="gb-add-game-card" onclick="openAddGame()" title="Add a new game">
        <div class="gb-add-game-card-inner">+</div>
        <div class="gb-add-game-card-label">New Game</div>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /gb-main -->

<?php
$_gameCredit = !empty($_designers)
    ? $_name . ' · Designed by ' . implode(' & ', $_designers)
    : '';
_gb_footer(['gameCredit' => $_gameCredit]);
?>

<div class="gb-lightbox" id="lightbox" onclick="closeLightbox()">
  <img id="lightboxImg" src="" alt="" />
</div>

<!-- ── Save toast ── -->
<div class="gb-save-toast" id="gbSaveToast"></div>

<!-- ── Publish Site dialog ── -->
<div class="gb-overlay" id="gbPublishOverlay" onclick="if(event.target===this)closePublishDialog()">
  <div class="gb-dialog" onclick="event.stopPropagation()" style="max-width:480px">
    <button class="gb-dialog-close" onclick="closePublishDialog()">×</button>
    <div class="gb-dialog-title">🌐 Publish Site</div>
    <p style="font-size:.82rem;color:var(--ink-lt,#666);margin-bottom:1.2rem;line-height:1.5">
      Your public GameBoard is live. Anyone with this link can browse your published games.
    </p>
    <div class="gb-field-group" style="margin-bottom:.6rem">
      <label style="font-size:.75rem;font-weight:600;color:var(--ink-lt,#888);text-transform:uppercase;letter-spacing:.05em">Catalog</label>
      <div style="display:flex;gap:.4rem;align-items:center">
        <input class="gb-field" id="gbPubCatalogUrl" type="text" readonly onclick="this.select()" style="flex:1;font-size:.8rem;cursor:text" />
        <button class="gb-btn-sm" onclick="gbCopyUrl('gbPubCatalogUrl')">Copy</button>
        <a id="gbPubCatalogLink" href="#" target="_blank" class="gb-btn-sm" style="text-decoration:none">Open ↗</a>
      </div>
    </div>
    <div id="gbPubGameUrl" style="margin-bottom:1.2rem">
      <div class="gb-field-group" style="margin-bottom:.3rem">
        <label style="font-size:.75rem;font-weight:600;color:var(--ink-lt,#888);text-transform:uppercase;letter-spacing:.05em">This Game</label>
        <div style="display:flex;gap:.4rem;align-items:center">
          <input class="gb-field" id="gbPubGameUrlInput" type="text" readonly onclick="this.select()" style="flex:1;font-size:.8rem;cursor:text" />
          <button class="gb-btn-sm" onclick="gbCopyUrl('gbPubGameUrlInput')">Copy</button>
          <a id="gbPubGameUrlLink" href="#" target="_blank" class="gb-btn-sm" style="text-decoration:none">Open ↗</a>
        </div>
      </div>
    </div>
    <div style="display:flex;justify-content:flex-end;margin-top:1rem">
      <button class="gb-btn" onclick="closePublishDialog()">Done</button>
    </div>
  </div>
</div>

<!-- ── Sign-in dialog ── -->
<div class="gb-overlay" id="signInOverlay" onclick="if(event.target===this)forceCloseSignIn()">
  <div class="gb-dialog" onclick="event.stopPropagation()"
       onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitSignIn(false);}">
    <div class="gb-dialog-title" id="siTitle">Sign In</div>
    <div id="siMainPanel">
      <div class="gb-field-group"><label>Email</label><input class="gb-field" id="siEmail" type="email" placeholder="you@example.com" autocomplete="email" /></div>
      <div class="gb-field-group"><label>Password</label><input class="gb-field" id="siPassword" type="password" placeholder="••••••••" autocomplete="current-password" /></div>
    </div>
    <div id="siConfirmPanel">
      <p style="font-size:.85rem;color:var(--ink-lt);line-height:1.55;margin-bottom:.75rem;">No account found for this email. Create one now?</p>
    </div>
    <div class="gb-err" id="siErr"></div>
    <div class="gb-dialog-actions">
      <button class="btn-cancel" onclick="forceCloseSignIn()">Cancel</button>
      <button class="btn-primary" id="siBtn" onclick="submitSignIn(false)">Sign In</button>
    </div>
  </div>
</div>

<!-- ── Settings dialog ── -->
<div class="gbed-overlay" id="settingsOverlay" onclick="if(event.target===this)forceCloseSettings()">
  <div class="gbed-dialog" onclick="event.stopPropagation()"
       onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitSettings();}">
    <div class="gbed-header">
      <span class="gbed-title">Settings</span>
      <button class="gbed-close" onclick="forceCloseSettings()">✕</button>
    </div>
    <div class="gbed-body"><div class="gbed-scroll">
      <div class="gbed-section">
        <div class="gbed-section-title">Company</div>
        <div class="gbed-field-group"><label class="gbed-label">Company Name</label><input class="gbed-field" id="setCompany" type="text" placeholder="Acme Games LLC" /></div>
        <div class="gbed-field-group"><label class="gbed-label">Logo URL</label><input class="gbed-field" id="setLogo" type="url" placeholder="https://…/logo.png" /></div>
        <div class="gbed-field-group"><label class="gbed-label">Tagline</label><input class="gbed-field" id="setTagline" type="text" placeholder="Independent board game publisher" /></div>
        <div class="gbed-field-group"><label class="gbed-label">About</label><textarea class="gbed-field" id="setAbout" rows="3" style="resize:vertical"></textarea></div>
      </div>
      <div class="gbed-section">
        <div class="gbed-section-title">Images</div>
        <?php for ($i = 1; $i <= 6; $i++): ?>
        <div class="gbed-field-group"><label class="gbed-label">Image <?= $i ?></label><input class="gbed-field" id="setCarousel<?= $i ?>" type="url" placeholder="https://…/image.jpg" /></div>
        <?php endfor; ?>
      </div>
      <div class="gbed-section">
        <div class="gbed-section-title">Social Links</div>
        <div class="gbed-row">
          <div class="gbed-field-group"><label class="gbed-label">Instagram</label><input class="gbed-field" id="setInsta" type="url" placeholder="https://instagram.com/…" /></div>
          <div class="gbed-field-group"><label class="gbed-label">X / Twitter</label><input class="gbed-field" id="setTwitter" type="url" placeholder="https://x.com/…" /></div>
        </div>
        <div class="gbed-row">
          <div class="gbed-field-group"><label class="gbed-label">Facebook</label><input class="gbed-field" id="setFb" type="url" placeholder="https://facebook.com/…" /></div>
          <div class="gbed-field-group"><label class="gbed-label">YouTube</label><input class="gbed-field" id="setYt" type="url" placeholder="https://youtube.com/…" /></div>
        </div>
        <div class="gbed-field-group"><label class="gbed-label">BoardGameGeek</label><input class="gbed-field" id="setBgg" type="url" placeholder="https://boardgamegeek.com/…" /></div>
      </div>
      <div class="gbed-section">
        <div class="gbed-section-title">Display</div>
        <div class="gbed-row">
          <div class="gbed-field-group"><label class="gbed-label">Banner Color</label><input class="gbed-field" id="setBannerColor" type="color" style="height:2.4rem;padding:.2rem .3rem;cursor:pointer" /></div>
          <div class="gbed-field-group"><label class="gbed-label">Template</label><select class="gbed-field" id="setTemplate"><option value="">Classic (default)</option><option value="opensea">OpenSea</option></select></div>
        </div>
        <div class="gbed-field-group"><label class="gbed-label">Copyright Line</label><input class="gbed-field" id="setCopyright" type="text" placeholder="© 2026 Acme Games LLC" /></div>
        <div class="gbed-field-group"><label class="gbed-label">Footer Note</label><input class="gbed-field" id="setFooterNote" type="text" placeholder="contact@example.com" /></div>
      </div>
    </div></div>
    <div class="gbed-footer">
      <div class="gb-err gbed-err" id="setErr"></div>
      <button class="btn-adm btn-adm-secondary" onclick="forceCloseSettings()">Cancel</button>
      <button class="btn-adm btn-adm-primary" id="setBtn" onclick="submitSettings()">Save</button>
    </div>
  </div>
</div>

<!-- ── Edit Details modal ── -->
<div class="gbed-overlay" id="gbedOverlay" onclick="if(event.target===this)closeEditDetails()">
  <div class="gbed-dialog" onclick="event.stopPropagation()">
    <div class="gbed-header">
      <span class="gbed-title">Edit Details</span>
      <button class="gbed-close" onclick="closeEditDetails()">✕</button>
    </div>
    <div class="gbed-body">
      <div class="gbed-section">
        <div class="gbed-section-title">Game Info</div>
        <div class="gbed-row">
          <div class="gbed-field-group">
            <label class="gbed-label">Status</label>
            <select class="gbed-field" id="gbedStatus">
              <option value="">—</option>
              <option value="Concept">Concept</option>
              <option value="Developing">Developing</option>
              <option value="Playtesting">Playtesting</option>
              <option value="Pitching">Pitching</option>
              <option value="Signed">Signed</option>
              <option value="Published">Published</option>
              <option value="Shelved">Shelved</option>
            </select>
          </div>
          <div class="gbed-field-group">
            <label class="gbed-label">Availability</label>
            <input class="gbed-field" id="gbedAvailability" type="text" placeholder="e.g. Kickstarter" />
          </div>
        </div>
        <div class="gbed-row">
          <div class="gbed-field-group">
            <label class="gbed-label">Designer 1</label>
            <input class="gbed-field" id="gbedDesigner1" type="text" />
          </div>
          <div class="gbed-field-group">
            <label class="gbed-label">Designer 2</label>
            <input class="gbed-field" id="gbedDesigner2" type="text" />
          </div>
        </div>
        <div class="gbed-row">
          <div class="gbed-field-group">
            <label class="gbed-label">Designer 3</label>
            <input class="gbed-field" id="gbedDesigner3" type="text" />
          </div>
          <div class="gbed-field-group">
            <label class="gbed-label">Designer 4</label>
            <input class="gbed-field" id="gbedDesigner4" type="text" />
          </div>
        </div>
        <div class="gbed-row">
          <div class="gbed-field-group">
            <label class="gbed-label">Artist 1</label>
            <input class="gbed-field" id="gbedArtist1" type="text" />
          </div>
          <div class="gbed-field-group">
            <label class="gbed-label">Artist 2</label>
            <input class="gbed-field" id="gbedArtist2" type="text" />
          </div>
        </div>
        <div class="gbed-row">
          <div class="gbed-field-group">
            <label class="gbed-label">Artist 3</label>
            <input class="gbed-field" id="gbedArtist3" type="text" />
          </div>
          <div class="gbed-field-group">
            <label class="gbed-label">Artist 4</label>
            <input class="gbed-field" id="gbedArtist4" type="text" />
          </div>
        </div>
      </div>
      <div class="gbed-section">
        <div class="gbed-section-title">Dates</div>
        <div class="gbed-row">
          <div class="gbed-field-group">
            <label class="gbed-label">Date Started</label>
            <input class="gbed-field" id="gbedDateStarted" type="text" placeholder="e.g. Jan 2024" />
          </div>
          <div class="gbed-field-group">
            <label class="gbed-label">Date Signed</label>
            <input class="gbed-field" id="gbedDateSigned" type="text" placeholder="e.g. Mar 2024" />
          </div>
        </div>
        <div class="gbed-field-group" style="max-width:50%;padding-right:.3rem">
          <label class="gbed-label">Date Published</label>
          <input class="gbed-field" id="gbedDatePublished" type="text" placeholder="e.g. Nov 2024" />
        </div>
      </div>
      <div class="gbed-section">
        <div class="gbed-section-title">Images &amp; Media</div>
        <div class="gbed-field-group">
          <label class="gbed-label">Header Image URL (hero banner — overrides ProductImage)</label>
          <div class="gbed-img-row">
            <input class="gbed-field" id="gbedHeaderImageUrl" type="url" placeholder="https://…" />
            <button type="button" class="gbed-upload-btn" onclick="gbedPickImage('gbedHeaderImageUrl')" title="Upload image">📷</button>
          </div>
        </div>
        <div class="gbed-field-group">
          <label class="gbed-label">Cover URL (background image)</label>
          <div class="gbed-img-row">
            <input class="gbed-field" id="gbedCoverUrl" type="url" placeholder="https://…" />
            <button type="button" class="gbed-upload-btn" onclick="gbedPickImage('gbedCoverUrl')" title="Upload image">📷</button>
          </div>
        </div>
        <div class="gbed-field-group">
          <label class="gbed-label">Image URL (product photo)</label>
          <div class="gbed-img-row">
            <input class="gbed-field" id="gbedImageUrl" type="url" placeholder="https://…" />
            <button type="button" class="gbed-upload-btn" onclick="gbedPickImage('gbedImageUrl')" title="Upload image">📷</button>
          </div>
        </div>
        <div class="gbed-field-group">
          <label class="gbed-label">Video URL (YouTube)</label>
          <input class="gbed-field" id="gbedVideoUrl" type="url" placeholder="https://youtube.com/watch?v=…" />
        </div>
      </div>
      <div class="gbed-section">
        <div class="gbed-section-title">Links</div>
        <div class="gbed-field-group">
          <label class="gbed-label">Page URL</label>
          <input class="gbed-field" id="gbedPageUrl" type="url" placeholder="https://…" />
        </div>
        <div class="gbed-field-group">
          <label class="gbed-label">Sellsheet URL</label>
          <input class="gbed-field" id="gbedSellsheet" type="url" placeholder="https://…" />
        </div>
        <div class="gbed-field-group">
          <label class="gbed-label">Rules URL</label>
          <input class="gbed-field" id="gbedRulesUrl" type="url" placeholder="https://…" />
        </div>
        <div class="gbed-row">
          <div class="gbed-field-group">
            <label class="gbed-label">Play Online URL</label>
            <input class="gbed-field" id="gbedPlayUrl" type="url" placeholder="https://…" />
          </div>
          <div class="gbed-field-group">
            <label class="gbed-label">Print &amp; Play URL</label>
            <input class="gbed-field" id="gbedPrintUrl" type="url" placeholder="https://…" />
          </div>
        </div>
      </div>
    </div>
    <div class="gbed-footer">
      <button class="btn-adm btn-adm-secondary" style="color:var(--ink-lt);border-color:var(--border)" onclick="closeEditDetails()">Cancel</button>
      <button class="btn-adm btn-adm-primary" style="background:var(--accent);color:#fff" onclick="applyEditDetails()">Apply</button>
    </div>
  </div>
</div>

<script>
var SHEET_ID      = <?= json_encode($_sheetId) ?>;
var APP_ROOT      = <?= json_encode($_appRoot) ?>;
var ORIG_GAME_NAME = <?= json_encode($_name) ?>;
var GAME_DATA = <?= json_encode([
    'status'         => $_status,
    'header_image_url' => $_headerImageUrl,
    'cover_url'      => $_coverUrl,
    'image_url'      => $_imageUrl,
    'video_url'      => $_videoUrl,
    'page_url'       => $_pageUrl,
    'sellsheet'      => $_sellsheet,
    'play_url'       => $_playUrl,
    'print_url'      => $_printUrl,
    'rules_url'      => $_rulesUrl,
    'availability'   => $_availability,
    'min_players'    => $_minPlayers,
    'max_players'    => $_maxPlayers,
    'min_playtime'   => $_minPlaytime,
    'max_playtime'   => $_maxPlaytime,
    'weight'         => $_weight,
    'mechanic'       => $_mechanic,
    'ages'           => $_ages,
    'series'         => $_series,
    'box_size'       => $_boxSize,
    'designer1'      => $_designers[0] ?? '',
    'designer2'      => $_designers[1] ?? '',
    'designer3'      => $_designers[2] ?? '',
    'designer4'      => $_designers[3] ?? '',
    'artist1'        => $_artists[0] ?? '',
    'artist2'        => $_artists[1] ?? '',
    'artist3'        => $_artists[2] ?? '',
    'artist4'        => $_artists[3] ?? '',
    'date_started'   => trim($_game['Date Started'] ?? ''),
    'date_signed'    => $_dateSigned,
    'date_published' => $_datePub,
    'price'          => $_richPrice,
    'buy_urls'       => array_values(array_filter(array_map(function($b) { return ['url' => trim($b['Value'] ?? ''), 'label' => trim($b['Value 1'] ?? '')]; }, $_buyUrls), function($b){ return $b['url'] !== '' || $b['label'] !== ''; })),
    'resource_links' => array_values(array_map(function($r) { return ['url' => trim($r['Value'] ?? ''), 'label' => trim($r['Value 1'] ?? '')]; }, $_resourceLinks)),
    'components'     => array_values(array_map(function($c) { return trim($c['Value'] ?? ''); }, $_components)),
    'reviews'        => array_values(array_map(function($r) { return ['attr' => trim($r['Value'] ?? ''), 'quote' => trim($r['Value 1'] ?? '')]; }, $_reviews)),
    'videos'         => array_values(array_map(function($r) { return ['url' => trim($r['Value'] ?? ''), 'name' => trim($r['Value 1'] ?? '')]; }, $_videoReviews)),
    'faqs'           => array_values(array_map(function($f) { return ['q' => trim($f['Value'] ?? ''), 'a' => trim($f['Value 1'] ?? '')]; }, $_faqs)),
    'gallery'        => $_productImgUrls,
]) ?>;

// Mutable copy that tracks edits from the details modal
var _ed = Object.assign({}, GAME_DATA);

// ── Admin mode + auth ────────────────────────────────────────────────────────
var _gbUser = null;
var _siPendingEmail = '', _siPendingPass = '';
(function() {
  try {
    var raw = sessionStorage.getItem('gameboard_owner_' + SHEET_ID);
    if (!raw) return;
    var u = JSON.parse(raw);
    if (!u || !u.email) return;
    _gbUser = u;
    // Activate admin mode
    document.body.classList.add('gb-admin-mode');
    // Make text fields contenteditable
    ['gbEditTitle', 'gbEditSummary', 'gbEditDesc', 'gbEditPrice'].forEach(function(id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.contentEditable = 'true';
      if (el.classList.contains('gb-ph-lorem')) {
        el.dataset.phContent = el.textContent;
        el.textContent = '';
        el.classList.remove('gb-ph-lorem');
      }
    });
    // Sync price on input; show/hide placeholder class when empty
    var priceEl  = document.getElementById('gbEditPrice');
    var priceBox = document.getElementById('gbPriceBox');
    if (priceEl && priceBox) {
      if (!priceEl.textContent.trim()) priceBox.classList.add('gb-price-ph');
      priceEl.oninput = function() {
        var val = this.textContent.trim();
        _ed.price = val;
        priceBox.classList.toggle('gb-price-ph', !val);
      };
      priceEl.onkeydown = function(e) {
        if (e.key === 'Enter') { e.preventDefault(); this.blur(); }
      };
      priceEl.onfocus = function() { priceBox.classList.remove('gb-price-ph'); };
      priceEl.onblur  = function() { if (!this.textContent.trim()) priceBox.classList.add('gb-price-ph'); };
    }
    // Build admin buy CTA buttons in hero
    gbInitBuyCtas();
    gbInitResLinks();
    gbInitInlineEdits();
    _updateGbAuth();
  } catch(e) { console.error('[admin init]', e); }
  // Gallery init runs outside try/catch so errors here are visible
  if (document.body.classList.contains('gb-admin-mode')) {
    if (!_ed.gallery) _ed.gallery = (GAME_DATA.gallery || []).slice();
    _ed._galleryDirty = false;  // only set true when user adds/removes/reorders
    gbRenderGallery();
    gbGalleryAutoAdvance();
  }
})();

function _updateGbAuth() {
  var btn  = document.getElementById('gbUserBtn');
  var siOut = document.getElementById('gbMenuSignInOut');
  if (_gbUser) {
    if (btn) { btn.classList.add('signed-in'); btn.title = 'Signed in as ' + (_gbUser.email || ''); }
    if (siOut) { siOut.textContent = 'Sign Out'; siOut.className = 'user-menu-item signout'; }
    document.body.classList.add('gb-admin-mode');
  } else {
    if (btn) { btn.classList.remove('signed-in'); btn.title = 'Sign In'; }
    if (siOut) { siOut.textContent = 'Sign In'; siOut.className = 'user-menu-item'; }
    document.body.classList.remove('gb-admin-mode');
  }
}

// ── Menu ─────────────────────────────────────────────────────────────────────
function toggleGbMenu(e) { e.stopPropagation(); document.getElementById('gbMenu').classList.toggle('open'); }
function closeGbMenu() { var m = document.getElementById('gbMenu'); if (m) m.classList.remove('open'); }
document.addEventListener('click', closeGbMenu);

function gbMenuAction(action) {
  closeGbMenu();
  if (action === 'settings') {
    openSettings();
  } else if (action === 'fetch') {
    if (!_gbUser) { openSignIn(); return; }
    gbDoFetch();
  } else if (action === 'publish') {
    if (!_gbUser) { openSignIn(); return; }
    gbPublishSite();
  } else if (action === 'signout') {
    if (_gbUser) {
      if (confirm('Sign out?')) {
        try { sessionStorage.removeItem('gameboard_owner_' + SHEET_ID); } catch(e) {}
        _gbUser = null; _updateGbAuth();
      }
    } else { openSignIn(); }
  }
}

// ── Sign-in ───────────────────────────────────────────────────────────────────
function openSignIn() {
  document.getElementById('siErr').textContent = '';
  document.getElementById('siTitle').textContent = 'Sign In';
  document.getElementById('siBtn').textContent = 'Sign In';
  document.getElementById('siMainPanel').style.display = '';
  document.getElementById('siConfirmPanel').style.display = 'none';
  _siPendingEmail = ''; _siPendingPass = '';
  document.getElementById('signInOverlay').classList.add('open');
  setTimeout(function(){ document.getElementById('siEmail').focus(); }, 60);
}
function forceCloseSignIn() { document.getElementById('signInOverlay').classList.remove('open'); }

function submitSignIn(confirmNew) {
  var email = confirmNew ? _siPendingEmail : document.getElementById('siEmail').value.trim();
  var pass  = confirmNew ? _siPendingPass  : document.getElementById('siPassword').value;
  if (!email || (!confirmNew && !pass)) { document.getElementById('siErr').textContent = 'Email and password required.'; return; }
  var btn = document.getElementById('siBtn'); btn.disabled = true;
  var fd = new FormData();
  fd.append('email', email); fd.append('password', pass); fd.append('id', SHEET_ID);
  if (confirmNew) fd.append('confirm_new', '1');
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/gbAuth.php');
  xhr.onload = function() {
    btn.disabled = false;
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = null; }
    if (!r) { document.getElementById('siErr').textContent = 'Server error.'; return; }
    if (r.prompt_create) {
      _siPendingEmail = email; _siPendingPass = pass;
      document.getElementById('siTitle').textContent = 'Create Account';
      document.getElementById('siBtn').textContent = 'Create Account';
      document.getElementById('siMainPanel').style.display = 'none';
      document.getElementById('siConfirmPanel').style.display = '';
      document.getElementById('siBtn').onclick = function(){ submitSignIn(true); };
      return;
    }
    if (r.error) { document.getElementById('siErr').textContent = r.error; return; }
    _gbUser = { email: r.email || email, bio: r.bio || {} };
    try { sessionStorage.setItem('gameboard_owner_' + SHEET_ID, JSON.stringify(_gbUser)); } catch(e) {}
    _updateGbAuth();
    forceCloseSignIn();
    location.reload();
  };
  xhr.onerror = function() { btn.disabled = false; document.getElementById('siErr').textContent = 'Network error.'; };
  xhr.send(fd);
}

// ── Settings ──────────────────────────────────────────────────────────────────
function openSettings(focusFieldId) {
  document.getElementById('setErr').textContent = '';
  var xhr = new XMLHttpRequest();
  xhr.open('GET', APP_ROOT + 'push/tmGetSettings.php?id=' + encodeURIComponent(SHEET_ID));
  xhr.onload = function() {
    var s = {}; try { s = JSON.parse(xhr.responseText); } catch(e) {}
    document.getElementById('setCompany').value      = s['Company']            || s['Name'] || '';
    document.getElementById('setLogo').value         = s['Logo URL']           || '';
    document.getElementById('setTagline').value      = s['Tagline']            || '';
    document.getElementById('setAbout').value        = s['About']              || '';
    document.getElementById('setTemplate').value     = s['Gameboard Template'] || '';
    document.getElementById('setBannerColor').value  = s['Banner Color']       || '#1a1a2e';
    document.getElementById('setCopyright').value    = s['Copyright']          || '';
    document.getElementById('setFooterNote').value   = s['Footer Note']        || '';
    var _imgs = s['Image'] || [];
    if (!Array.isArray(_imgs)) _imgs = _imgs ? [_imgs] : [];
    for (var i = 1; i <= 6; i++) {
      var el = document.getElementById('setCarousel' + i);
      if (el) el.value = _imgs[i - 1] || s['Carousel ' + i] || '';
    }
    document.getElementById('setInsta').value   = s['Instagram'] || '';
    document.getElementById('setTwitter').value = s['Twitter']   || '';
    document.getElementById('setFb').value      = s['Facebook']  || '';
    document.getElementById('setYt').value      = s['YouTube']   || '';
    document.getElementById('setBgg').value     = s['BGG URL']   || '';
    var target = focusFieldId ? document.getElementById(focusFieldId) : document.getElementById('setCompany');
    if (target) { target.focus(); target.select(); target.scrollIntoView({ behavior:'smooth', block:'center' }); }
  };
  xhr.send();
  document.getElementById('settingsOverlay').classList.add('open');
}
function forceCloseSettings() { document.getElementById('settingsOverlay').classList.remove('open'); }
function submitSettings() {
  var btn = document.getElementById('setBtn'); btn.disabled = true;
  var fd = new FormData();
  fd.append('id',          SHEET_ID);
  fd.append('company',     document.getElementById('setCompany').value.trim());
  fd.append('logo',        document.getElementById('setLogo').value.trim());
  fd.append('tagline',     document.getElementById('setTagline').value.trim());
  fd.append('about',       document.getElementById('setAbout').value.trim());
  fd.append('gb_template', document.getElementById('setTemplate').value);
  fd.append('banner_color',document.getElementById('setBannerColor').value);
  fd.append('copyright',   document.getElementById('setCopyright').value.trim());
  fd.append('footer_note', document.getElementById('setFooterNote').value.trim());
  for (var i = 1; i <= 6; i++) {
    var el = document.getElementById('setCarousel' + i);
    if (el) fd.append('carousel_' + i, el.value.trim());
  }
  fd.append('instagram', document.getElementById('setInsta').value.trim());
  fd.append('twitter',   document.getElementById('setTwitter').value.trim());
  fd.append('facebook',  document.getElementById('setFb').value.trim());
  fd.append('youtube',   document.getElementById('setYt').value.trim());
  fd.append('bgg_url',   document.getElementById('setBgg').value.trim());
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/tmSaveSettings.php');
  xhr.onload = function() {
    btn.disabled = false;
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = null; }
    if (!r || r.error) { document.getElementById('setErr').textContent = 'Save failed: ' + (r && r.error || 'unknown'); return; }
    if (!r.sheetOk && r.sheetErr) console.warn('Sheet sync error:', r.sheetErr);
    forceCloseSettings();
  };
  xhr.onerror = function() { btn.disabled = false; document.getElementById('setErr').textContent = 'Network error.'; };
  xhr.send(fd);
}

// ── Publish ───────────────────────────────────────────────────────────────────
function gbPublishSite() {
  var btn = document.getElementById('gbUserBtn');
  if (btn) btn.style.opacity = '.4';
  var fd = new FormData(); fd.append('id', SHEET_ID);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/publishGameboard.php');
  xhr.onload = function() {
    if (btn) btn.style.opacity = '';
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = {error: 'parse error'}; }
    if (!r || r.error) { alert('Publish failed: ' + (r && r.error || 'unknown')); return; }
    openPublishDialog(r.url, r.token);
  };
  xhr.onerror = function() { if (btn) btn.style.opacity = ''; alert('Publish failed — network error.'); };
  xhr.send(fd);
}
function openPublishDialog(catalogUrl, token) {
  document.getElementById('gbPubCatalogUrl').value = catalogUrl;
  document.getElementById('gbPubCatalogLink').href = catalogUrl;
  var gameName = ORIG_GAME_NAME ? ORIG_GAME_NAME.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') : '';
  var gameEl = document.getElementById('gbPubGameUrl');
  if (gameName && gameEl) {
    var gameUrl = catalogUrl.replace(/\/?$/, '/') + gameName;
    document.getElementById('gbPubGameUrlInput').value = gameUrl;
    document.getElementById('gbPubGameUrlLink').href   = gameUrl;
    gameEl.style.display = '';
  } else if (gameEl) { gameEl.style.display = 'none'; }
  document.getElementById('gbPublishOverlay').classList.add('open');
}
function closePublishDialog() { document.getElementById('gbPublishOverlay').classList.remove('open'); }
function gbCopyUrl(inputId) {
  var inp = document.getElementById(inputId); if (!inp) return;
  inp.select(); inp.setSelectionRange(0, 9999);
  try { document.execCommand('copy'); } catch(e) { navigator.clipboard && navigator.clipboard.writeText(inp.value); }
  var btn = inp.nextElementSibling;
  if (btn) { var old = btn.textContent; btn.textContent = 'Copied!'; setTimeout(function(){ btn.textContent = old; }, 1500); }
}

function cancelEdit() { location.reload(); }

// ── Edit Details modal ───────────────────────────────────────────────────────
function openEditDetailsAt(fieldId) {
  openEditDetails();
  // Scroll the target field into view and focus it after the modal opens
  setTimeout(function() {
    var el = document.getElementById(fieldId);
    if (!el) return;
    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    el.focus();
    el.select && el.select();
  }, 60);
}
// ── Video placeholder: click → inline URL entry; drop → upload ──────────────
function gbVideoPhClick(e, el) {
  if (el.classList.contains('url-entry')) return; // already open
  el.classList.add('url-entry');
  el.querySelector('.gb-video-ph-icon').style.display = 'none';
  el.querySelector('.gb-video-ph-label').style.display = 'none';
  var urlWrap = document.createElement('div');
  urlWrap.className = 'gb-video-ph-url-wrap';
  urlWrap.innerHTML =
    '<input id="gbVideoUrlInline" type="url" placeholder="Paste YouTube or video URL…" />'
    + '<button onclick="gbVideoUrlConfirm()">Add</button>';
  var cancel = document.createElement('div');
  cancel.className = 'gb-video-ph-cancel';
  cancel.textContent = 'Cancel';
  cancel.onclick = function() { gbVideoUrlCancel(el); };
  el.appendChild(urlWrap);
  el.appendChild(cancel);
  setTimeout(function() { var inp = document.getElementById('gbVideoUrlInline'); if (inp) inp.focus(); }, 30);
  urlWrap.querySelector('input').addEventListener('keydown', function(ev) {
    if (ev.key === 'Enter') { ev.preventDefault(); gbVideoUrlConfirm(); }
    if (ev.key === 'Escape') { gbVideoUrlCancel(el); }
  });
}
function gbVideoUrlCancel(el) {
  el.classList.remove('url-entry');
  el.querySelector('.gb-video-ph-icon').style.display = '';
  el.querySelector('.gb-video-ph-label').style.display = '';
  var wrap = el.querySelector('.gb-video-ph-url-wrap');
  var cancel = el.querySelector('.gb-video-ph-cancel');
  if (wrap)   el.removeChild(wrap);
  if (cancel) el.removeChild(cancel);
}
function gbVideoUrlConfirm() {
  var inp = document.getElementById('gbVideoUrlInline');
  if (!inp) return;
  var url = inp.value.trim();
  if (!url) return;
  _ed.video_url = url;
  gbVideoApplyUrl(url);
}
function gbVideoApplyUrl(url) {
  // Compute embed URL (YouTube) or direct URL
  var embed = '';
  var yt = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/shorts\/)([A-Za-z0-9_\-]+)/);
  if (yt) embed = 'https://www.youtube.com/embed/' + yt[1];
  var section = document.getElementById('gbVideoPlaceholder');
  if (!section) section = document.getElementById('gbVideoSection');
  if (!section) return;
  section.id = 'gbVideoSection';
  section.className = 'gb-section';
  var delBtn = '<button class="gb-video-del-btn" onclick="gbVideoRemoveClick()" title="Remove video">✕</button>';
  if (embed) {
    section.innerHTML = '<div class="gb-section-label">Watch</div>'
      + '<div class="gb-video-wrap" id="videoWrap">'
      + '<iframe id="videoFrame" src="' + embed.replace(/"/g,'&quot;') + '" allow="autoplay; encrypted-media" allowfullscreen></iframe>'
      + delBtn + '</div>';
  } else {
    section.innerHTML = '<div class="gb-section-label">Watch</div>'
      + '<div class="gb-video-wrap" id="videoWrap">'
      + '<video id="videoFrame" src="' + url.replace(/"/g,'&quot;') + '" controls style="position:absolute;inset:0;width:100%;height:100%;object-fit:contain;background:#000;"></video>'
      + delBtn + '</div>';
  }
}
function gbSyncVideoSection(url) {
  var placeholder = document.getElementById('gbVideoPlaceholder');
  var section     = document.getElementById('gbVideoSection');
  if (!url) {
    // Clear → restore placeholder, hide live section
    if (section) {
      section.id        = 'gbVideoPlaceholder';
      section.className = 'gb-section gb-ph-section';
      section.innerHTML =
        '<div class="gb-section-label">Watch</div>'
        + '<div class="gb-video-ph-inner"'
        + ' onclick="gbVideoPhClick(event,this)"'
        + ' ondragover="gbVideoDragOver(event,this)"'
        + ' ondragleave="gbVideoDragLeave(event,this)"'
        + ' ondrop="gbVideoDrop(event,this)">'
        + '<div class="gb-video-ph-icon" style="font-size:3rem;">▶</div>'
        + '<div class="gb-video-ph-label" style="font-family:\'DINBlack\',sans-serif;font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;color:#fff;">Drop a video file or click to add URL</div>'
        + '</div>';
    }
  } else {
    gbVideoApplyUrl(url);
    gbRenderVideoAdminOverlay();
  }
}
function gbRenderVideoAdminOverlay() {
  var section = document.getElementById('gbVideoSection');
  if (!section) return;
  // Remove any existing overlay
  var old = section.querySelector('.gb-video-admin-bar');
  if (old) old.parentNode.removeChild(old);
  var bar = document.createElement('div');
  bar.className = 'gb-video-admin-bar';
  bar.innerHTML =
    '<button onclick="gbVideoChangeClick()" title="Change video">✏ Change</button>'
    + '<button onclick="gbVideoRemoveClick()" title="Remove video" style="color:#f87171;">✕ Remove</button>';
  section.appendChild(bar);
}
function gbVideoRemoveClick() {
  if (!confirm('Remove this video?')) return;
  _ed.video_url = '';
  gbSyncVideoSection('');
}
function gbVideoDragOver(e, el) {
  e.preventDefault();
  el.classList.add('drag-over');
}
function gbVideoDragLeave(e, el) {
  e.preventDefault();
  el.classList.remove('drag-over');
}
function gbVideoDrop(e, el) {
  e.preventDefault();
  el.classList.remove('drag-over');
  var file = e.dataTransfer.files && e.dataTransfer.files[0];
  if (!file) return;
  if (!file.type.startsWith('video/')) {
    gbVideoPhClick(e, el);
    return;
  }
  var origHTML = el.innerHTML;
  el.innerHTML = '<div style="color:#fff;font-size:.8rem;text-transform:uppercase;letter-spacing:.1em;">Uploading…</div>';
  var fd = new FormData();
  fd.append('id', SHEET_ID);
  fd.append('file', file);
  fetch(APP_ROOT + 'push/uploadMedia.php', { method:'POST', body:fd })
    .then(function(r){ return r.json(); })
    .then(function(r){
      if (r.ok && r.url) {
        _ed.video_url = r.url;
        gbVideoApplyUrl(r.url);
      } else {
        alert('Upload failed: ' + (r.error || 'Unknown error'));
        el.innerHTML = origHTML;
      }
    })
    .catch(function(){
      alert('Upload failed.');
      el.innerHTML = origHTML;
    });
}

function openEditDetails() {
  // Populate from _ed (live-edited values)
  document.getElementById('gbedStatus').value       = _ed.status       || '';
  document.getElementById('gbedAvailability').value = _ed.availability || '';
  document.getElementById('gbedDesigner1').value    = _ed.designer1    || '';
  document.getElementById('gbedDesigner2').value    = _ed.designer2    || '';
  document.getElementById('gbedDesigner3').value    = _ed.designer3    || '';
  document.getElementById('gbedDesigner4').value    = _ed.designer4    || '';
  document.getElementById('gbedArtist1').value      = _ed.artist1      || '';
  document.getElementById('gbedArtist2').value      = _ed.artist2      || '';
  document.getElementById('gbedArtist3').value      = _ed.artist3      || '';
  document.getElementById('gbedArtist4').value      = _ed.artist4      || '';
  document.getElementById('gbedDateStarted').value   = _ed.date_started   || '';
  document.getElementById('gbedDateSigned').value    = _ed.date_signed    || '';
  document.getElementById('gbedDatePublished').value = _ed.date_published || '';
  document.getElementById('gbedHeaderImageUrl').value = _ed.header_image_url || '';
  document.getElementById('gbedCoverUrl').value  = _ed.cover_url  || '';
  document.getElementById('gbedImageUrl').value  = _ed.image_url  || '';
  document.getElementById('gbedVideoUrl').value  = _ed.video_url  || '';
  document.getElementById('gbedPageUrl').value   = _ed.page_url   || '';
  document.getElementById('gbedSellsheet').value = _ed.sellsheet  || '';
  document.getElementById('gbedRulesUrl').value  = _ed.rules_url  || '';
  document.getElementById('gbedPlayUrl').value   = _ed.play_url   || '';
  document.getElementById('gbedPrintUrl').value  = _ed.print_url  || '';
  document.getElementById('gbedOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeEditDetails() {
  document.getElementById('gbedOverlay').classList.remove('open');
  document.body.style.overflow = '';
}
function applyEditDetails() {
  _ed.status         = document.getElementById('gbedStatus').value.trim();
  _ed.availability   = document.getElementById('gbedAvailability').value.trim();
  _ed.designer1      = document.getElementById('gbedDesigner1').value.trim();
  _ed.designer2      = document.getElementById('gbedDesigner2').value.trim();
  _ed.designer3      = document.getElementById('gbedDesigner3').value.trim();
  _ed.designer4      = document.getElementById('gbedDesigner4').value.trim();
  _ed.artist1        = document.getElementById('gbedArtist1').value.trim();
  _ed.artist2        = document.getElementById('gbedArtist2').value.trim();
  _ed.artist3        = document.getElementById('gbedArtist3').value.trim();
  _ed.artist4        = document.getElementById('gbedArtist4').value.trim();
  gbInitArtistInputs();
  _ed.date_started   = document.getElementById('gbedDateStarted').value.trim();
  _ed.date_signed    = document.getElementById('gbedDateSigned').value.trim();
  _ed.date_published = document.getElementById('gbedDatePublished').value.trim();
  _ed.header_image_url = document.getElementById('gbedHeaderImageUrl').value.trim();
  _ed.cover_url      = document.getElementById('gbedCoverUrl').value.trim();
  _ed.image_url      = document.getElementById('gbedImageUrl').value.trim();
  _ed.video_url      = document.getElementById('gbedVideoUrl').value.trim();
  gbSyncVideoSection(_ed.video_url);
  _ed.page_url       = document.getElementById('gbedPageUrl').value.trim();
  _ed.sellsheet      = document.getElementById('gbedSellsheet').value.trim();
  _ed.rules_url      = document.getElementById('gbedRulesUrl').value.trim();
  _ed.play_url       = document.getElementById('gbedPlayUrl').value.trim();
  _ed.print_url      = document.getElementById('gbedPrintUrl').value.trim();
  closeEditDetails();
  // Sync back to inline stat inputs
  gbInitStatInputs();
  // Update status badge live
  var badge = document.getElementById('gbStatusBadge');
  if (badge) {
    if (_ed.status) {
      badge.textContent = _ed.status.charAt(0).toUpperCase() + _ed.status.slice(1).toLowerCase();
      badge.style.display = '';
    } else {
      badge.style.display = 'none';
    }
  }
}

// ── Inline section editors (What's In the Box / Quotes / FAQs) ───────────────
// Format: components = one item per line
//         reviews    = "Quote | Source" per line  (source optional)
//         faqs       = "Question | Answer" per line
function gbParseReviews(text) {
  return text.split('\n').map(function(line) {
    line = line.trim(); if (!line) return null;
    var idx = line.indexOf(' | ');
    return idx !== -1
      ? { attr: line.slice(0, idx).trim(), quote: line.slice(idx + 3).trim() }
      : { attr: line, quote: '' };
  }).filter(Boolean);
}
function gbParseFaqs(text) {
  return text.split('\n').map(function(line) {
    line = line.trim(); if (!line) return null;
    var idx = line.indexOf(' | ');
    return idx !== -1
      ? { q: line.slice(0, idx).trim(), a: line.slice(idx + 3).trim() }
      : { q: line, a: '' };
  }).filter(Boolean);
}
function gbInitInlineEdits() {
  var compEl = document.getElementById('gbCompEdit');
  if (compEl) {
    compEl.value = (_ed.components || []).filter(Boolean).join('\n');
    compEl.oninput = function() {
      _ed.components = this.value.split('\n').map(function(s){return s.trim();}).filter(Boolean);
    };
  }
  gbInitReviewTicker();
  gbInitVrevGrid();
  gbBuildFaqEditor(_ed.faqs || []);
  gbInitDesignerInputs();
  gbInitArtistInputs();
  gbInitStatInputs();
}

function gbInitStatInputs() {
  var map = {
    gbsiMinPlayers:  'min_players',  gbsiMaxPlayers:  'max_players',
    gbsiMinPlaytime: 'min_playtime', gbsiMaxPlaytime: 'max_playtime',
    gbsiWeight:      'weight'
  };
  Object.keys(map).forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.value = _ed[map[id]] || '';
  });
}

function gbInitDesignerInputs() {
  var wrap = document.getElementById('gbDesignerInputs');
  if (!wrap) return;
  wrap.innerHTML = '';
  // Start with existing values; always show at least 1 input
  var vals = [_ed.designer1||'', _ed.designer2||'', _ed.designer3||'', _ed.designer4||''];
  // Show inputs up to last filled + 1 (but at least 1, at most 4)
  var lastFilled = -1;
  vals.forEach(function(v, i){ if (v) lastFilled = i; });
  var showCount = Math.min(lastFilled + 2, 4);
  if (showCount < 1) showCount = 1;
  for (var i = 0; i < showCount; i++) { gbAddDesignerInput(wrap, vals[i], i); }
}

function gbAddDesignerInput(wrap, value, idx) {
  var inp = document.createElement('input');
  inp.type = 'text';
  inp.className = 'gb-designer-input';
  inp.value = value || '';
  inp.placeholder = idx === 0 ? 'Designer name' : '+ name';
  inp.dataset.idx = idx;
  inp.oninput = function() {
    var keys = ['designer1','designer2','designer3','designer4'];
    _ed[keys[idx]] = this.value.trim();
    // If this is the last visible input and now has a value, add a new one
    var allInputs = wrap.querySelectorAll('.gb-designer-input');
    if (this.value && parseInt(this.dataset.idx) === allInputs.length - 1 && allInputs.length < 4) {
      var nextIdx = allInputs.length;
      var nextVals = [_ed.designer1||'', _ed.designer2||'', _ed.designer3||'', _ed.designer4||''];
      gbAddDesignerInput(wrap, nextVals[nextIdx] || '', nextIdx);
    }
  };
  wrap.appendChild(inp);
}

function gbInitArtistInputs() {
  var wrap = document.getElementById('gbArtistInputs');
  if (!wrap) return;
  wrap.innerHTML = '';
  var vals = [_ed.artist1||'', _ed.artist2||'', _ed.artist3||'', _ed.artist4||''];
  var lastFilled = -1;
  vals.forEach(function(v, i){ if (v) lastFilled = i; });
  var showCount = Math.min(lastFilled + 2, 4);
  if (showCount < 1) showCount = 1;
  for (var i = 0; i < showCount; i++) { gbAddArtistInput(wrap, vals[i], i); }
}

function gbAddArtistInput(wrap, value, idx) {
  var inp = document.createElement('input');
  inp.type = 'text';
  inp.className = 'gb-designer-input';
  inp.value = value || '';
  inp.placeholder = idx === 0 ? 'Artist name' : '+ name';
  inp.dataset.idx = idx;
  inp.oninput = function() {
    var keys = ['artist1','artist2','artist3','artist4'];
    _ed[keys[idx]] = this.value.trim();
    var allInputs = wrap.querySelectorAll('.gb-designer-input');
    if (this.value && parseInt(this.dataset.idx) === allInputs.length - 1 && allInputs.length < 4) {
      var nextIdx = allInputs.length;
      var nextVals = [_ed.artist1||'', _ed.artist2||'', _ed.artist3||'', _ed.artist4||''];
      gbAddArtistInput(wrap, nextVals[nextIdx] || '', nextIdx);
    }
  };
  wrap.appendChild(inp);
}

function gbAutoResize(ta) {
  ta.style.height = 'auto';
  ta.style.height = ta.scrollHeight + 'px';
}

function gbBuildReviewEditor(reviews) {
  var list = document.getElementById('gbReviewsList');
  if (!list) return;
  list.innerHTML = '';
  var pairs = reviews.filter(function(r){ return r.attr || r.quote; });
  pairs.push({attr:'', quote:''});
  // attr = review text (col B), quote = source/attribution (col C)
  pairs.forEach(function(r, idx) { gbAddReviewPair(list, r.attr, r.quote, idx === pairs.length - 1); });
}

function gbAddReviewPair(list, quote, source, isLast) {
  var pair = document.createElement('div');
  pair.className = 'gb-faq-pair';

  var qta = document.createElement('textarea');
  qta.className = 'faq-q';
  qta.placeholder = 'Quote';
  qta.rows = 1;
  qta.value = quote || '';

  var sta = document.createElement('textarea');
  sta.className = 'faq-a';
  sta.placeholder = 'Source (optional)';
  sta.rows = 1;
  sta.value = source || '';

  function syncReviews() {
    _ed.reviews = Array.prototype.slice.call(
      document.querySelectorAll('#gbReviewsList .gb-faq-pair')
    ).map(function(p) {
      return {
        attr:  p.querySelector('.faq-q').value.trim(),  // review text (top)
        quote: p.querySelector('.faq-a').value.trim()   // source/attribution (bottom)
      };
    }).filter(function(r){ return r.attr || r.quote; });
  }

  function maybeAddPair() {
    var pairs = document.querySelectorAll('#gbReviewsList .gb-faq-pair');
    var last  = pairs[pairs.length - 1];
    if (pair === last && (qta.value.trim() || sta.value.trim())) {
      gbAddReviewPair(list, '', '', true);
    }
  }

  qta.oninput = function() { gbAutoResize(this); syncReviews(); maybeAddPair(); };
  sta.oninput = function() { gbAutoResize(this); syncReviews(); maybeAddPair(); };

  pair.appendChild(qta);
  pair.appendChild(sta);
  list.appendChild(pair);
  setTimeout(function() { gbAutoResize(qta); gbAutoResize(sta); }, 0);
}

function esc(s) {
  return String(s == null ? '' : s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function gbYtThumb(url) {
  var m = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/shorts\/)([A-Za-z0-9_\-]+)/);
  return m ? 'https://img.youtube.com/vi/' + m[1] + '/mqdefault.jpg' : '';
}

function gbInitVrevGrid() {
  var grid = document.getElementById('gbVrevGrid');
  if (!grid) return;
  grid.innerHTML = '';
  var videos = _ed.videos || [];
  videos.forEach(function(v, i) { if (v.url) gbAddVrevCard(grid, v, i); });
  // Always show enough empty tiles: 3 total when fewer than 3 filled, else 1
  var emptyCount = Math.max(1, 3 - videos.length);
  for (var i = 0; i < emptyCount; i++) gbAddVrevEmptyTile(grid);
}

function gbAddVrevCard(grid, v, idx) {
  var tile = document.createElement('div');
  tile.className = 'gb-vrev-tile';
  tile.dataset.idx = idx;
  var thumb = gbYtThumb(v.url);
  tile.innerHTML = (thumb ? '<img src="' + esc(thumb) + '" loading="lazy">' : '<div style="width:100%;height:100%;background:#222;border-radius:6px;"></div>')
    + '<div class="gb-vrev-tile-label">' + esc(v.name || v.url) + '</div>'
    + '<button class="gb-vrev-tile-del" title="Remove" onmousedown="gbVrevRemove(event,' + idx + ')">✕</button>';
  tile.onclick = function(e) { if (!e.target.classList.contains('gb-vrev-tile-del')) gbVrevOpenInlineTile(tile, idx); };
  grid.insertBefore(tile, grid.querySelector('.gb-vrev-empty') || null);
}

function gbAddVrevEmptyTile(grid) {
  var tile = document.createElement('div');
  tile.className = 'gb-vrev-empty';
  tile.innerHTML = '<div class="gb-vrev-add-icon">▶</div><div class="gb-vrev-add-label">Add Video</div>';
  tile.onclick = function() { gbVrevOpenInlineTile(tile, -1); };
  grid.appendChild(tile);
}

function gbVrevOpenInlineTile(tile, idx) {
  // Close any other open inline editor first
  var grid = document.getElementById('gbVrevGrid');
  if (grid) {
    grid.querySelectorAll('.gb-vrev-editing').forEach(function(t) {
      if (t !== tile) gbInitVrevGrid();
    });
  }
  var v = idx >= 0 ? ((_ed.videos || [])[idx] || {}) : {};
  tile.classList.add('gb-vrev-editing');
  tile.onclick = null;
  tile.innerHTML =
    '<input class="gb-vrev-inp" type="url" placeholder="YouTube or video URL…" value="' + esc(v.url || '') + '">'
    + '<input class="gb-vrev-inp" type="text" placeholder="Source / reviewer (optional)" value="' + esc(v.name || '') + '">'
    + '<div class="gb-vrev-inline-btns">'
    + '<button class="gb-vrev-btn-save" onclick="gbVrevSaveInline(this,'+idx+')">Save</button>'
    + '<button class="gb-vrev-btn-cancel" onclick="gbInitVrevGrid()">Cancel</button>'
    + '</div>';
  var inp = tile.querySelector('input[type="url"]');
  if (inp) setTimeout(function() { inp.focus(); }, 20);
  tile.querySelector('input[type="url"]').addEventListener('keydown', function(ev) {
    if (ev.key === 'Enter') { ev.preventDefault(); tile.querySelector('.gb-vrev-btn-save').click(); }
    if (ev.key === 'Escape') { gbInitVrevGrid(); }
  });
}

function gbVrevSaveInline(btn, idx) {
  var tile = btn.closest('.gb-vrev-editing');
  if (!tile) return;
  var inputs = tile.querySelectorAll('.gb-vrev-inp');
  var url  = inputs[0] ? inputs[0].value.trim() : '';
  var name = inputs[1] ? inputs[1].value.trim() : '';
  if (!url) { gbInitVrevGrid(); return; }
  if (!_ed.videos) _ed.videos = [];
  if (idx >= 0) { _ed.videos[idx] = {url: url, name: name}; }
  else          { _ed.videos.push({url: url, name: name}); }
  gbInitVrevGrid();
}

function gbVrevRemove(e, idx) {
  e.stopPropagation();
  if (!_ed.videos) return;
  _ed.videos.splice(idx, 1);
  gbInitVrevGrid();
}

var _buyDragIdx = -1;
function gbMakeBuyCtaEl(b, i, isPlaceholder) {
  var w = document.createElement('span');
  w.className = 'gb-buy-cta-wrap' + (isPlaceholder ? ' gb-buy-cta-ph' : '');

  // Drag-and-drop reordering (non-placeholder only)
  if (!isPlaceholder) {
    w.draggable = true; w.style.cursor = 'grab';
    w.addEventListener('dragstart', function(e) {
      _buyDragIdx = i; e.dataTransfer.effectAllowed = 'move';
      setTimeout(function() { w.style.opacity = '.4'; }, 0);
    });
    w.addEventListener('dragend', function() {
      w.style.opacity = '';
      document.querySelectorAll('.gb-rl-drop-target').forEach(function(el) { el.classList.remove('gb-rl-drop-target'); });
    });
    w.addEventListener('dragover', function(e) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; w.classList.add('gb-rl-drop-target'); });
    w.addEventListener('dragleave', function() { w.classList.remove('gb-rl-drop-target'); });
    w.addEventListener('drop', function(e) {
      e.preventDefault(); w.classList.remove('gb-rl-drop-target');
      if (_buyDragIdx < 0 || _buyDragIdx === i) return;
      var moved = _ed.buy_urls.splice(_buyDragIdx, 1)[0];
      _ed.buy_urls.splice(i, 0, moved);
      gbInitBuyCtas();
    });
  }

  var inner = document.createElement('span');
  inner.className = 'gb-buy-cta-inner filled';

  // Editable label — click selects all so user can type replacement immediately
  var lbl = document.createElement('span');
  lbl.className = 'gb-buy-cta-lbl';
  lbl.contentEditable = 'true';
  lbl.textContent = isPlaceholder ? 'ADD BUY LINK' : (b.label || 'Buy Now');
  lbl.onclick = function(e) {
    e.stopPropagation();
    var range = document.createRange(); range.selectNodeContents(this);
    var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(range);
  };
  if (i >= 0) {
    lbl.oninput = function() {
      if (_ed.buy_urls && _ed.buy_urls[i]) _ed.buy_urls[i].label = this.textContent.trim() || 'Buy Now';
    };
  }

  // Inline URL input (hidden until link icon clicked)
  var urlInp = document.createElement('input');
  urlInp.type = 'url'; urlInp.placeholder = 'https://…';
  urlInp.value = b.url || ''; urlInp.className = 'gb-buy-url-inline';
  urlInp.style.display = 'none';
  urlInp.onclick = function(e) { e.stopPropagation(); };

  // Confirm button (hidden until URL input shown)
  var okBtn = document.createElement('button');
  okBtn.className = 'gb-buy-url-ok-inline'; okBtn.textContent = '✓'; okBtn.style.display = 'none';

  // Link icon button
  var linkBtn = document.createElement('button');
  linkBtn.className = 'gb-buy-link-icon';
  linkBtn.title = isPlaceholder ? 'Set URL' : 'Edit URL';
  linkBtn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';

  function showUrl(e) {
    if (e) e.preventDefault();
    lbl.style.display = 'none'; linkBtn.style.display = 'none';
    urlInp.style.display = ''; okBtn.style.display = '';
    urlInp.focus(); if (urlInp.value) urlInp.select();
  }
  function revertUrl() {
    urlInp.style.display = 'none'; okBtn.style.display = 'none';
    lbl.style.display = ''; linkBtn.style.display = '';
  }
  function confirmUrl() {
    var url   = urlInp.value.trim();
    var label = lbl.textContent.trim() || 'Buy Now';
    if (!url && !label) { revertUrl(); return; }
    if (!_ed.buy_urls) _ed.buy_urls = [];
    if (i >= 0) { _ed.buy_urls[i] = {url: url, label: label}; }
    else        { _ed.buy_urls.push({url: url, label: label}); }
    gbInitBuyCtas();
  }

  // Enter in label → go to URL; Enter in URL → confirm; Escape → revert
  lbl.onkeydown       = function(e) { if (e.key === 'Enter') { e.preventDefault(); showUrl(null); } };
  linkBtn.onmousedown = showUrl;
  okBtn.onmousedown   = function(e) { e.preventDefault(); confirmUrl(); };
  urlInp.onkeydown    = function(e) {
    if (e.key === 'Enter')  { e.preventDefault(); confirmUrl(); }
    if (e.key === 'Escape') { revertUrl(); }
  };

  inner.appendChild(lbl); inner.appendChild(urlInp);
  inner.appendChild(linkBtn); inner.appendChild(okBtn);
  w.appendChild(inner);

  // Delete button (not on placeholder)
  if (!isPlaceholder) {
    var del = document.createElement('button');
    del.className = 'gb-buy-cta-del'; del.textContent = '✕'; del.title = 'Remove';
    del.onmousedown = function(e) { e.preventDefault(); _ed.buy_urls.splice(i, 1); gbInitBuyCtas(); };
    w.appendChild(del);
  }

  return w;
}

function gbInitBuyCtas() {
  var wrap = document.getElementById('gbBuyCtas');
  if (!wrap) return;
  wrap.innerHTML = '';
  wrap.style.display = 'contents';
  document.querySelectorAll('.gb-buy-pub').forEach(function(el) { el.style.display = 'none'; });
  if (!_ed.buy_urls) _ed.buy_urls = (GAME_DATA.buy_urls || []).slice();
  _ed.buy_urls.forEach(function(b, i) { if (b.url || b.label) wrap.appendChild(gbMakeBuyCtaEl(b, i, false)); });
  wrap.appendChild(gbMakeBuyCtaEl({label: 'ADD BUY LINK', url: ''}, -1, true));
}

var _rlDragIdx = -1;
function gbMakeResLinkEl(rl, i, isPlaceholder) {
  var w = document.createElement('span');
  w.className = 'gb-buy-cta-wrap' + (isPlaceholder ? ' gb-buy-cta-ph' : '');
  // Drag-and-drop reordering (non-placeholder only)
  if (!isPlaceholder) {
    w.draggable = true;
    w.style.cursor = 'grab';
    w.addEventListener('dragstart', function(e) {
      _rlDragIdx = i;
      e.dataTransfer.effectAllowed = 'move';
      setTimeout(function() { w.style.opacity = '.4'; }, 0);
    });
    w.addEventListener('dragend', function() { w.style.opacity = ''; document.querySelectorAll('.gb-rl-drop-target').forEach(function(el) { el.classList.remove('gb-rl-drop-target'); }); });
    w.addEventListener('dragover', function(e) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; w.classList.add('gb-rl-drop-target'); });
    w.addEventListener('dragleave', function() { w.classList.remove('gb-rl-drop-target'); });
    w.addEventListener('drop', function(e) {
      e.preventDefault(); w.classList.remove('gb-rl-drop-target');
      if (_rlDragIdx < 0 || _rlDragIdx === i) return;
      var arr = _ed.resource_links;
      var moved = arr.splice(_rlDragIdx, 1)[0];
      arr.splice(i, 0, moved);
      gbInitResLinks();
    });
  }

  var inner = document.createElement('span');
  inner.className = 'gb-buy-cta-inner'; // outlined, not filled

  var lbl = document.createElement('span');
  lbl.className = 'gb-buy-cta-lbl';
  lbl.contentEditable = 'true';
  lbl.textContent = (isPlaceholder ? 'ADD RESOURCE' : (rl.label || 'Resource'));
  // Click: select all text so user can just type the new name
  lbl.onclick = function(e) {
    e.stopPropagation();
    var range = document.createRange(); range.selectNodeContents(this);
    var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(range);
  };
  if (i >= 0) {
    lbl.oninput = function() {
      if (_ed.resource_links && _ed.resource_links[i]) _ed.resource_links[i].label = this.textContent.trim() || 'Resource';
    };
  }

  var urlInp = document.createElement('input');
  urlInp.type = 'url'; urlInp.placeholder = 'https://…';
  urlInp.value = rl.url || ''; urlInp.className = 'gb-buy-url-inline';
  urlInp.style.display = 'none';
  urlInp.onclick = function(e) { e.stopPropagation(); };

  var okBtn = document.createElement('button');
  okBtn.className = 'gb-buy-url-ok-inline'; okBtn.textContent = '✓'; okBtn.style.display = 'none';

  var linkBtn = document.createElement('button');
  linkBtn.className = 'gb-buy-link-icon';
  linkBtn.title = isPlaceholder ? 'Set URL' : 'Edit URL';
  linkBtn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';

  function showUrl(e) {
    if (e) e.preventDefault();
    lbl.style.display = 'none'; linkBtn.style.display = 'none';
    urlInp.style.display = ''; okBtn.style.display = '';
    urlInp.focus(); if (urlInp.value) urlInp.select();
  }
  function revertUrl() {
    urlInp.style.display = 'none'; okBtn.style.display = 'none';
    lbl.style.display = ''; linkBtn.style.display = '';
  }
  function confirmUrl() {
    var url   = urlInp.value.trim();
    var label = lbl.textContent.trim() || 'Resource';
    if (!url && !label) { revertUrl(); return; }
    if (!_ed.resource_links) _ed.resource_links = [];
    if (i >= 0) { _ed.resource_links[i] = {url: url, label: label}; }
    else        { _ed.resource_links.push({url: url, label: label}); }
    gbInitResLinks();
  }

  // Enter in label → go to URL
  lbl.onkeydown = function(e) { if (e.key === 'Enter') { e.preventDefault(); showUrl(null); } };
  linkBtn.onmousedown = showUrl;
  okBtn.onmousedown   = function(e) { e.preventDefault(); confirmUrl(); };
  urlInp.onkeydown    = function(e) {
    if (e.key === 'Enter')  { e.preventDefault(); confirmUrl(); }
    if (e.key === 'Escape') { revertUrl(); }
  };

  inner.appendChild(lbl); inner.appendChild(urlInp);
  inner.appendChild(linkBtn); inner.appendChild(okBtn);
  w.appendChild(inner);

  if (!isPlaceholder) {
    var del = document.createElement('button');
    del.className = 'gb-buy-cta-del'; del.textContent = '✕'; del.title = 'Remove';
    del.onmousedown = function(e) { e.preventDefault(); _ed.resource_links.splice(i, 1); gbInitResLinks(); };
    w.appendChild(del);
  }
  return w;
}

function gbInitResLinks() {
  var wrap = document.getElementById('gbResLinks');
  if (!wrap) return;
  wrap.innerHTML = '';
  wrap.style.display = 'flex';
  document.querySelectorAll('#gbResourcesSection .gb-display-content').forEach(function(el) { el.style.display = 'none'; });
  if (!_ed.resource_links) _ed.resource_links = (GAME_DATA.resource_links || []).slice();
  _ed.resource_links.forEach(function(rl, i) {
    wrap.appendChild(gbMakeResLinkEl(rl, i, false));
  });
  wrap.appendChild(gbMakeResLinkEl({label: 'ADD RESOURCE', url: ''}, -1, true));
}

function gbBuildFaqEditor(faqs) {
  var list = document.getElementById('gbFaqsList');
  if (!list) return;
  list.innerHTML = '';
  var pairs = faqs.filter(function(f){ return f.q || f.a; });
  // Always ensure at least one empty pair at end
  pairs.push({q:'', a:''});
  pairs.forEach(function(f, idx) { gbAddFaqPair(list, f.q, f.a, idx === pairs.length - 1); });
}

function gbAddFaqPair(list, q, a, isLast) {
  var pair = document.createElement('div');
  pair.className = 'gb-faq-pair';

  var qta = document.createElement('textarea');
  qta.className = 'faq-q';
  qta.placeholder = 'Question';
  qta.rows = 1;
  qta.value = q || '';

  // Answer area: textarea + image upload button + image preview
  var ansWrap = document.createElement('div');
  ansWrap.className = 'faq-ans-wrap';

  var ata = document.createElement('textarea');
  ata.className = 'faq-a';
  ata.placeholder = 'Answer';
  ata.rows = 2;

  // Image upload button (hidden when text exists)
  var imgBtn = document.createElement('button');
  imgBtn.type = 'button';
  imgBtn.className = 'faq-img-btn';
  imgBtn.title = 'Upload image';
  imgBtn.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';

  var fileInp = document.createElement('input');
  fileInp.type = 'file'; fileInp.accept = 'image/*'; fileInp.style.display = 'none';

  // Image preview
  var imgPreview = document.createElement('div');
  imgPreview.className = 'faq-img-preview';
  imgPreview.style.display = 'none';
  var previewImg = document.createElement('img');
  previewImg.alt = '';
  var trashBtn = document.createElement('button');
  trashBtn.type = 'button'; trashBtn.className = 'faq-img-trash'; trashBtn.title = 'Remove image';
  trashBtn.innerHTML = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>';
  imgPreview.appendChild(previewImg); imgPreview.appendChild(trashBtn);

  // Determine initial state: if answer is an img: URL, show image; else text
  var imgUrl = '';
  if (a && a.indexOf('img:') === 0) {
    imgUrl = a.slice(4);
    previewImg.src = imgUrl;
    imgPreview.style.display = '';
    ata.style.display = 'none';
    imgBtn.style.display = 'none';
  } else {
    ata.value = a || '';
  }

  function getAnswerValue() {
    return imgUrl ? 'img:' + imgUrl : ata.value.trim();
  }

  function syncFaqs() {
    _ed.faqs = Array.prototype.slice.call(
      document.querySelectorAll('#gbFaqsList .gb-faq-pair')
    ).map(function(p) {
      var q2 = p.querySelector('.faq-q').value.trim();
      var aw = p._gbGetAnswer ? p._gbGetAnswer() : (p.querySelector('.faq-a') ? p.querySelector('.faq-a').value.trim() : '');
      return { q: q2, a: aw };
    }).filter(function(f){ return f.q || f.a; });
  }
  pair._gbGetAnswer = getAnswerValue;

  function maybeAddPair() {
    var pairs = document.querySelectorAll('#gbFaqsList .gb-faq-pair');
    var last  = pairs[pairs.length - 1];
    if (pair === last && (qta.value.trim() || getAnswerValue())) {
      gbAddFaqPair(list, '', '', true);
    }
  }

  qta.oninput = function() { gbAutoResize(this); syncFaqs(); maybeAddPair(); };
  ata.oninput = function() {
    imgBtn.style.display = this.value ? 'none' : '';
    gbAutoResize(this); syncFaqs(); maybeAddPair();
  };

  imgBtn.onclick = function() { fileInp.value = ''; fileInp.click(); };

  fileInp.onchange = function() {
    var file = this.files[0]; if (!file) return;
    imgBtn.disabled = true;
    var fd = new FormData(); fd.append('id', SHEET_ID); fd.append('file', file);
    fetch(APP_ROOT + 'push/uploadMedia.php', { method:'POST', body:fd })
      .then(function(r) { return r.json(); })
      .then(function(res) {
        imgBtn.disabled = false;
        if (!res.ok || !res.url) { alert('Upload failed: ' + (res.error || 'unknown')); return; }
        imgUrl = res.url;
        previewImg.src = imgUrl;
        imgPreview.style.display = '';
        ata.style.display = 'none';
        imgBtn.style.display = 'none';
        syncFaqs(); maybeAddPair();
      })
      .catch(function() { imgBtn.disabled = false; alert('Upload failed.'); });
  };

  trashBtn.onclick = function() {
    imgUrl = ''; previewImg.src = '';
    imgPreview.style.display = 'none';
    ata.style.display = ''; ata.value = '';
    imgBtn.style.display = '';
    syncFaqs();
  };

  ansWrap.appendChild(ata);
  ansWrap.appendChild(imgBtn);
  ansWrap.appendChild(fileInp);
  ansWrap.appendChild(imgPreview);

  pair.appendChild(qta);
  pair.appendChild(ansWrap);
  list.appendChild(pair);

  setTimeout(function() { gbAutoResize(qta); gbAutoResize(ata); }, 0);
}

// ── Save game ────────────────────────────────────────────────────────────────
// ── Image upload helpers ──────────────────────────────────────────────────────
var _gbFileCallback = null;

function gbPickImage(callback) {
  _gbFileCallback = callback;
  var inp = document.getElementById('gbFileInput');
  inp.value = '';
  inp.click();
}

function gbHandleFileInput(e) {
  var file = e.target.files[0];
  if (!file || !_gbFileCallback) return;
  // Determine loading zone — apply it NOW (after file is chosen, not on click)
  var zone = null;
  if (_gbFileCallback === gbSetCover) {
    zone = document.getElementById('gbHero');
    zone.classList.add('gb-cover-loading');
  } else {
    zone = document.getElementById('gbGalleryAddBtn');
    if (zone) zone.classList.add('gb-uploading');
    var featDiv = document.getElementById('gbGalleryFeatured');
    if (featDiv) featDiv.classList.add('gb-uploading');
  }
  var cb = _gbFileCallback;
  gbUploadImage(file, cb, zone);
  _gbFileCallback = null;
}

function gbUploadImage(file, callback, zoneEl) {
  if (zoneEl) zoneEl.classList.add('gb-uploading');
  var featDiv = document.getElementById('gbGalleryFeatured');
  var fd = new FormData();
  fd.append('id', SHEET_ID);
  fd.append('file', file);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/uploadImage.php');
  xhr.onload = function() {
    if (zoneEl) { zoneEl.classList.remove('gb-uploading'); zoneEl.classList.remove('gb-cover-loading'); }
    var r;
    try { r = JSON.parse(xhr.responseText); } catch(e) { r = {error: 'parse error'}; }
    if (r && r.ok && r.url) {
      callback(r.url, zoneEl);
    } else {
      alert('Image upload failed: ' + (r && r.error || 'unknown'));
    }
  };
  xhr.onerror = function() {
    if (zoneEl) { zoneEl.classList.remove('gb-uploading'); zoneEl.classList.remove('gb-cover-loading'); }
    if (featDiv) featDiv.classList.remove('gb-uploading');
    alert('Image upload failed: network error');
  };
  xhr.send(fd);
}

// ── Edit Details image pick (fills URL input; uses its own file input to avoid gallery conflicts) ─
var _gbedPickTarget = null;
function gbedPickImage(inputId) {
  _gbedPickTarget = inputId;
  var inp = document.getElementById('gbedFileInput');
  inp.value = '';
  inp.click();
}
function gbedHandleFileInput(e) {
  var file = e.target.files[0];
  if (!file || !_gbedPickTarget) return;
  var inputId = _gbedPickTarget;
  _gbedPickTarget = null;
  var btn = document.querySelector('[onclick="gbedPickImage(\'' + inputId + '\')"]');
  if (btn) btn.classList.add('gbed-uploading');
  gbUploadImage(file, function(url) {
    if (btn) btn.classList.remove('gbed-uploading');
    var inp = document.getElementById(inputId);
    if (inp) inp.value = url;
    // Also update the hero preview if this is the header image
    if (inputId === 'gbedHeaderImageUrl') {
      _ed.header_image_url = url;
      var bg = document.getElementById('gbHeroBg');
      if (bg) bg.style.backgroundImage = 'url(' + url + ')';
      var hero = document.getElementById('gbHero');
      if (hero) hero.classList.remove('no-cover');
    }
  }, null);
}

// ── Hero image (HeaderImage in [{game}] tab) ──────────────────────────────────
function gbSetCover(url) {
  _ed.header_image_url = url;
  var hero = document.getElementById('gbHero');
  var bg   = document.getElementById('gbHeroBg');
  if (hero) hero.classList.remove('gb-cover-loading');
  if (bg)   bg.style.backgroundImage = 'url(' + url + ')';
  if (hero) hero.classList.remove('no-cover');
  gbShowUploadToast('Hero image ready — click Save Changes');
}

// ── Gallery ───────────────────────────────────────────────────────────────────
var _gbGalleryIdx  = 0;
var _gbGalleryTimer = null;
var _gbDragIdx = -1;

function gbRenderGallery() {
  var imgs = _ed.gallery || [];
  var section = document.getElementById('gbGallerySection');
  var featEl  = document.getElementById('gbGalleryFeaturedImg');
  var thumbsEl = document.getElementById('gbGalleryThumbs');
  if (!section || !featEl || !thumbsEl) return;

  // Show/hide section
  var viewer = document.getElementById('gbGalleryViewer');
  if (!imgs.length) {
    section.classList.add('gb-sect-empty');
    if (viewer) viewer.classList.add('gb-gallery-empty');
  } else {
    section.classList.remove('gb-sect-empty', 'gb-sect-hidden');
    if (viewer) viewer.classList.remove('gb-gallery-empty');
  }

  // When empty in admin mode, make featured area a clickable add target
  var featuredDiv = document.getElementById('gbGalleryFeatured');
  if (featuredDiv) {
    featuredDiv.onclick = !imgs.length ? function() { gbPickGalleryImage(); } : null;
  }

  // Clamp active index
  if (_gbGalleryIdx >= imgs.length) _gbGalleryIdx = Math.max(0, imgs.length - 1);

  // Set featured image src
  if (imgs.length) {
    if (imgs[_gbGalleryIdx]) featEl.src = imgs[_gbGalleryIdx];
    featEl.style.display = '';
  } else {
    featEl.style.display = 'none';
  }

  // Rebuild thumbs
  // Keep the add button, rebuild everything before it
  var addBtn = document.getElementById('gbGalleryAddBtn');
  // Remove all thumb elements
  Array.prototype.slice.call(thumbsEl.children).forEach(function(el) {
    if (el.id !== 'gbGalleryAddBtn') thumbsEl.removeChild(el);
  });
  imgs.forEach(function(url, i) {
    var div = document.createElement('div');
    div.className = 'gb-gallery-thumb' + (i === _gbGalleryIdx ? ' gb-gt-active' : '');
    div.dataset.idx = i;
    if (document.body.classList.contains('gb-admin-mode')) {
      div.draggable = true;
    }
    var img = document.createElement('img'); img.src = url; img.loading = 'lazy';
    var del = document.createElement('button');
    del.className = 'gb-gt-del'; del.textContent = '✕'; del.title = 'Remove';
    (function(idx) {
      div.onclick = function() { gbGallerySelect(idx); };
      del.onclick = function(e) { e.stopPropagation(); gbGalleryDelete(idx); };
      div.addEventListener('dragstart', function(e) {
        _gbDragIdx = idx;
        div.classList.add('gb-gt-dragging');
        e.dataTransfer.effectAllowed = 'move';
      });
      div.addEventListener('dragend', function() { div.classList.remove('gb-gt-dragging'); _gbDragIdx = -1; });
      div.addEventListener('dragover', function(e) { e.preventDefault(); div.classList.add('gb-gt-drag-over'); });
      div.addEventListener('dragleave', function() { div.classList.remove('gb-gt-drag-over'); });
      div.addEventListener('drop', function(e) {
        e.preventDefault(); div.classList.remove('gb-gt-drag-over');
        if (_gbDragIdx === -1 || _gbDragIdx === idx) return;
        var arr = _ed.gallery || [];
        var moved = arr.splice(_gbDragIdx, 1)[0];
        arr.splice(idx, 0, moved);
        _ed.gallery = arr;
        _ed._galleryDirty = true;
        _gbGalleryIdx = idx;
        gbRenderGallery();
      });
    })(i);
    div.appendChild(img); div.appendChild(del);
    thumbsEl.insertBefore(div, addBtn);
  });
}

function gbGallerySelect(idx) {
  var imgs = _ed.gallery || [];
  if (idx < 0 || idx >= imgs.length) return;
  _gbGalleryIdx = idx;
  var featEl = document.getElementById('gbGalleryFeaturedImg');
  if (featEl && featEl.getAttribute('src') !== imgs[idx]) {
    featEl.classList.add('gb-gf-fade');
    featEl.style.display = '';
    setTimeout(function() {
      featEl.src = imgs[idx];
      featEl.onload = function() { featEl.classList.remove('gb-gf-fade'); featEl.onload = null; };
    }, 180);
  }
  // Update active thumb
  document.querySelectorAll('#gbGalleryThumbs .gb-gallery-thumb').forEach(function(el, i) {
    el.classList.toggle('gb-gt-active', i === idx);
  });
}

function gbGalleryDelete(idx) {
  if (!_ed.gallery || idx < 0 || idx >= _ed.gallery.length) return;
  _ed.gallery.splice(idx, 1);
  _ed._galleryDirty = true;
  if (_gbGalleryIdx >= _ed.gallery.length) _gbGalleryIdx = Math.max(0, _ed.gallery.length - 1);
  gbRenderGallery();
  gbShowUploadToast('Image removed — click Save Changes');
}

function gbGalleryDeleteFeatured() {
  gbGalleryDelete(_gbGalleryIdx);
}

function gbGalleryAutoAdvance() {
  if (_gbGalleryTimer) clearInterval(_gbGalleryTimer);
  _gbGalleryTimer = setInterval(function() {
    var imgs = _ed.gallery || [];
    if (imgs.length > 1) gbGallerySelect((_gbGalleryIdx + 1) % imgs.length);
  }, 4000);
}

function gbPickGalleryImage() {
  var addBtn = document.getElementById('gbGalleryAddBtn');
  var featDiv = document.getElementById('gbGalleryFeatured');
  _gbFileCallback = function(url) {
    if (addBtn) addBtn.classList.remove('gb-uploading');
    if (featDiv) featDiv.classList.remove('gb-uploading');
    if (!_ed.gallery) _ed.gallery = [];
    _ed.gallery.push(url);
    _ed._galleryDirty = true;
    _gbGalleryIdx = _ed.gallery.length - 1;
    gbRenderGallery();
    gbShowUploadToast('Image added — click Save Changes');
  };
  var inp = document.getElementById('gbFileInput');
  inp.value = '';
  inp.click();
}

function gbGalleryDropImage(e) {
  var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
  if (!file || !file.type.startsWith('image/')) return;
  var addBtn = document.getElementById('gbGalleryAddBtn');
  var featDiv = document.getElementById('gbGalleryFeatured');
  if (featDiv) featDiv.classList.add('gb-uploading');
  gbUploadImage(file, function(url) {
    if (addBtn) addBtn.classList.remove('gb-uploading');
    if (featDiv) featDiv.classList.remove('gb-uploading');
    if (!_ed.gallery) _ed.gallery = [];
    _ed.gallery.push(url);
    _ed._galleryDirty = true;
    _gbGalleryIdx = _ed.gallery.length - 1;
    gbRenderGallery();
    gbShowUploadToast('Image added — click Save Changes');
  }, addBtn);
}

function gbShowUploadToast(msg) {
  var toast = document.getElementById('gbSaveToast');
  if (!toast) return;
  toast.textContent = '✓ ' + msg;
  toast.classList.add('visible');
  setTimeout(function() { toast.classList.remove('visible'); }, 3000);
}

// ── Hero drag-drop ────────────────────────────────────────────────────────────
(function() {
  if (!document.body.classList.contains('gb-admin-mode')) return;
  var hero = document.getElementById('gbHero');
  if (!hero) return;
  hero.addEventListener('dragover', function(e) {
    e.preventDefault();
    hero.classList.add('gb-drag-over');
  });
  hero.addEventListener('dragleave', function(e) {
    if (!hero.contains(e.relatedTarget)) hero.classList.remove('gb-drag-over');
  });
  hero.addEventListener('drop', function(e) {
    e.preventDefault();
    hero.classList.remove('gb-drag-over');
    var file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
      hero.classList.add('gb-cover-loading');
      gbUploadImage(file, gbSetCover, hero);
    }
  });
})();

// ── Gallery placeholder drag-drop (delegated) ─────────────────────────────────
document.addEventListener('dragover', function(e) {
  var zone = e.target.closest('.gb-upload-zone');
  if (zone) { e.preventDefault(); zone.classList.add('gb-drag-over'); }
});
document.addEventListener('dragleave', function(e) {
  var zone = e.target.closest('.gb-upload-zone');
  if (zone && !zone.contains(e.relatedTarget)) zone.classList.remove('gb-drag-over');
});
document.addEventListener('drop', function(e) {
  var zone = e.target.closest('.gb-upload-zone');
  if (!zone) return;
  e.preventDefault();
  zone.classList.remove('gb-drag-over');
  var file = e.dataTransfer.files[0];
  if (file && file.type.startsWith('image/')) gbUploadImage(file, gbSetGalleryImage, zone);
});

function saveGame() {
  // If the edit-details dialog is open, flush its values into _ed first
  if (document.getElementById('gbedOverlay').classList.contains('open')) {
    applyEditDetails();
  }
  // Sync inline stat inputs into _ed before building FormData
  var _statSync = {
    gbsiMinPlayers:'min_players', gbsiMaxPlayers:'max_players',
    gbsiMinPlaytime:'min_playtime', gbsiMaxPlaytime:'max_playtime',
    gbsiWeight:'weight'
  };
  Object.keys(_statSync).forEach(function(id) {
    var el = document.getElementById(id);
    if (el) _ed[_statSync[id]] = el.value.trim();
  });

  var saveBtn = document.getElementById('gbSaveBtn');
  saveBtn.disabled = true;
  saveBtn.textContent = 'Saving…';

  var titleEl = document.getElementById('gbEditTitle');
  var summaryEl = document.getElementById('gbEditSummary');
  var descEl = document.getElementById('gbEditDesc');

  var fd = new FormData();
  fd.append('id',             SHEET_ID);
  fd.append('orig_name',      ORIG_GAME_NAME);
  fd.append('name',           titleEl   ? titleEl.textContent.trim()   : ORIG_GAME_NAME);
  fd.append('tagline',        summaryEl ? summaryEl.textContent.trim() : '');
  fd.append('description',    descEl    ? descEl.textContent.trim()    : '');
  fd.append('status',         _ed.status         || '');
  fd.append('availability',   _ed.availability   || '');
  fd.append('min_players',    _ed.min_players    || '');
  fd.append('max_players',    _ed.max_players    || '');
  fd.append('min_playtime',   _ed.min_playtime   || '');
  fd.append('max_playtime',   _ed.max_playtime   || '');
  fd.append('weight',         _ed.weight         || '');
  fd.append('designer1',      _ed.designer1      || '');
  fd.append('designer2',      _ed.designer2      || '');
  fd.append('designer3',      _ed.designer3      || '');
  fd.append('designer4',      _ed.designer4      || '');
  var _artists = [_ed.artist1||'',_ed.artist2||'',_ed.artist3||'',_ed.artist4||''].filter(function(a){return a.trim();});
  fd.append('artists', JSON.stringify(_artists));
  fd.append('date_started',   _ed.date_started   || '');
  fd.append('date_signed',    _ed.date_signed    || '');
  fd.append('date_published', _ed.date_published || '');
  fd.append('header_image_url', _ed.header_image_url || '');
  fd.append('cover_url',      _ed.cover_url      || '');
  fd.append('image',          _ed.image_url      || '');
  fd.append('video',          _ed.video_url      || '');
  fd.append('view',           _ed.page_url       || '');
  fd.append('sellsheet',      _ed.sellsheet      || '');
  fd.append('rules',          _ed.rules_url      || '');
  fd.append('play',           _ed.play_url       || '');
  fd.append('print',          _ed.print_url      || '');
  // Sync price from contenteditable span before saving
  var _priceEl = document.getElementById('gbEditPrice');
  if (_priceEl) _ed.price = _priceEl.textContent.trim();
  if (_ed.price      !== undefined) fd.append('price',     _ed.price     || '');
  if (_ed.buy_urls        !== undefined) fd.append('buy_urls',        JSON.stringify(_ed.buy_urls        || []));
  if (_ed.resource_links  !== undefined) fd.append('resource_links',  JSON.stringify(_ed.resource_links  || []));
  if (_ed.components !== undefined) fd.append('components', JSON.stringify(_ed.components || []));
  if (_ed.reviews    !== undefined) fd.append('reviews',    JSON.stringify(_ed.reviews    || []));
  if (_ed.videos     !== undefined) fd.append('videos',     JSON.stringify(_ed.videos     || []));
  if (_ed.faqs       !== undefined) fd.append('faqs',       JSON.stringify(_ed.faqs       || []));
  if (_ed._galleryDirty) {
    fd.append('gallery_urls', JSON.stringify(_ed.gallery || []));
  }

  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/updateGame.php');
  xhr.onload = function() {
    var r;
    try { r = JSON.parse(xhr.responseText); } catch(e) { r = {error: 'Could not parse response: ' + xhr.responseText.substring(0, 200)}; }
    console.log('[GameBoard save response]', r, xhr.responseText);
    if (!r || r.error) {
      saveBtn.disabled = false;
      saveBtn.textContent = 'Save Changes';
      var errMsg = (r && r.error) || 'Unknown error';
      if (r && r.stderr) errMsg += '\n\nServer detail: ' + r.stderr;
      alert('Save failed: ' + errMsg);
      return;
    }
    if (!r.ok) {
      saveBtn.disabled = false;
      saveBtn.textContent = 'Save Changes';
      alert('Save failed: unexpected response\n' + JSON.stringify(r));
      return;
    }
    // Success — cache was already refreshed inside updateGame.php; redirect directly
    saveBtn.textContent = '✓ Saved!';
    var toast = document.getElementById('gbSaveToast');
    if (toast) { toast.textContent = '✓ Saved — refreshing…'; toast.classList.add('visible'); }
    var newName = titleEl ? titleEl.textContent.trim() : ORIG_GAME_NAME;
    var newSlug = newName.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    var destUrl = APP_ROOT + SHEET_ID + '/gameboard/' + newSlug;
    try { sessionStorage.setItem('gb_scroll_' + newSlug, window.scrollY); } catch(e) {}
    setTimeout(function() { location.href = destUrl; }, 400);
  };
  xhr.onerror = function() {
    saveBtn.disabled = false;
    saveBtn.textContent = 'Save Changes';
    alert('Save failed: network error');
  };
  xhr.send(fd);
}

// ── Standard page JS ─────────────────────────────────────────────────────────
function openLightbox(src) {
  document.getElementById('lightboxImg').src = src;
  document.getElementById('lightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeLightbox() {
  document.getElementById('lightbox').classList.remove('open');
  if (!document.getElementById('gbedOverlay').classList.contains('open')) {
    document.body.style.overflow = '';
  }
}
function switchVideo(btn, embed) {
  document.getElementById('videoFrame').src = embed;
  document.querySelectorAll('.gb-vtab').forEach(function(t){ t.classList.remove('active'); });
  btn.classList.add('active');
}
function toggleFaq(id) {
  document.getElementById(id).classList.toggle('open');
}
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') {
    closeGbMenu();
    if (document.getElementById('fetchLogOverlay').classList.contains('open'))  { closeFetchLog(); return; }
    if (document.getElementById('settingsOverlay').classList.contains('open')) { forceCloseSettings(); return; }
    if (document.getElementById('signInOverlay').classList.contains('open'))   { forceCloseSignIn(); return; }
    if (document.getElementById('gbPublishOverlay').classList.contains('open')){ closePublishDialog(); return; }
    if (document.getElementById('gbedOverlay').classList.contains('open'))     { closeEditDetails(); return; }
    closeLightbox();
  }
});

// ── Fetch log dialog ─────────────────────────────────────────────────────────
function gbSyncLog(msg, type) {
  var log  = document.getElementById('fetchLogBody');
  var line = document.createElement('span');
  line.className = 'sync-log-line ' + (type || 'info');
  line.textContent = msg;
  log.appendChild(line);
  log.scrollTop = log.scrollHeight;
}
function gbOpenFetchDialog() {
  document.getElementById('fetchLogBody').innerHTML = '';
  document.getElementById('fetchLogTitle').textContent = 'Fetching…';
  document.getElementById('fetchLogDoneBtn').disabled = true;
  document.getElementById('fetchLogOverlay').classList.add('open');
}
function gbFinishFetch(hasErrors) {
  document.getElementById('fetchLogTitle').textContent = hasErrors ? 'Fetch — errors' : 'Fetch Complete';
  document.getElementById('fetchLogDoneBtn').disabled = false;
  gbSyncLog(hasErrors ? 'Completed with errors.' : 'Done.', hasErrors ? 'error' : 'ok');
}
function gbDoFetch() {
  gbOpenFetchDialog();
  var base = APP_ROOT + 'push/setupGameboard.php';
  var hasErrors = false;

  gbSyncLog('Fetching sheets…', 'info');
  var fd = new FormData(); fd.append('id', SHEET_ID);
  fetch(base, { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(r) {
      if (r.error) { gbSyncLog('✗ ' + r.error, 'error'); gbFinishFetch(true); return; }
      if (r.tabs) Object.keys(r.tabs).forEach(function(t) {
        var s = r.tabs[t];
        gbSyncLog((s==='error'?'  ✗ ':'  ✓ ') + t, s==='error'?'error':(s==='created'?'ok':'skip'));
        if (s==='error') hasErrors = true;
      });
      if (r.json_refreshed) r.json_refreshed.forEach(function(t) { gbSyncLog('  ✓ ' + t, 'ok'); });
      if (r.refresh_errors) { Object.keys(r.refresh_errors).forEach(function(t) { gbSyncLog('  ✗ ' + t, 'error'); hasErrors = true; }); }

      var games = r.game_names || [];
      if (!games.length) { gbFinishFetch(hasErrors); return; }
      gbSyncLog('Publishing game caches…', 'info');
      var gi = 0;
      function nextGame() {
        if (gi >= games.length) { gbFinishFetch(hasErrors); return; }
        var name = games[gi++];
        var gfd = new FormData();
        gfd.append('id', SHEET_ID); gfd.append('mode', 'game'); gfd.append('name', name);
        fetch(base, { method:'POST', body:gfd })
          .then(function(r) { return r.json(); })
          .then(function(r) {
            if (r && r.ok) { gbSyncLog('  ✓ ' + name, 'ok'); }
            else {
              var msg = (r && r.error ? ': ' + r.error : '');
              if (r && r.detail) msg += ' — ' + r.detail;
              gbSyncLog('  ✗ ' + name + msg, 'error'); hasErrors = true;
            }
            nextGame();
          })
          .catch(function() { gbSyncLog('  ✗ ' + name + ': network error', 'error'); hasErrors = true; nextGame(); });
      }
      nextGame();
    })
    .catch(function() { gbSyncLog('✗ Network error', 'error'); gbFinishFetch(true); });
}
function closeFetchLog() {
  document.getElementById('fetchLogOverlay').classList.remove('open');
  location.reload();
}

// ── Strip HTML on paste — always insert plain text ────────────────────────────
document.addEventListener('paste', function(e) {
  var el = e.target;
  var text = (e.clipboardData || window.clipboardData).getData('text/plain');
  if (!text) return;
  if (el.tagName === 'TEXTAREA' || (el.tagName === 'INPUT' && el.type === 'text')) {
    e.preventDefault();
    var start = el.selectionStart, end = el.selectionEnd, val = el.value;
    el.value = val.substring(0, start) + text + val.substring(end);
    el.selectionStart = el.selectionEnd = start + text.length;
    el.dispatchEvent(new Event('input', { bubbles: true }));
  } else if (el.isContentEditable || el.closest('[contenteditable="true"]')) {
    e.preventDefault();
    document.execCommand('insertText', false, text);
  }
}, true);

// ── Review Ticker ─────────────────────────────────────────────────────────────
var _gbTickerRaf = null, _gbTickerPos = 0, _gbTickerPaused = false, _gbTickerEditIdx = -1, _gbTickerEditEl = null;

function gbInitReviewTicker() {
  var wrap  = document.getElementById('gbTickerWrap');
  var track = document.getElementById('gbTickerTrack');
  if (!track) return;

  if (_gbTickerRaf) { cancelAnimationFrame(_gbTickerRaf); _gbTickerRaf = null; }
  _gbTickerPos = 0; _gbTickerPaused = false; _gbTickerEditIdx = -1; _gbTickerEditEl = null;
  track.style.transform = '';

  var reviews = (_ed.reviews || []).filter(function(r) { return r.attr; });
  track.innerHTML = '';

  if (!reviews.length && !document.body.classList.contains('gb-admin-mode')) {
    if (wrap) wrap.classList.add('gb-ticker-hidden');
    return;
  }
  if (wrap) wrap.classList.remove('gb-ticker-hidden', 'gb-ticker-empty');

  function makeDot() {
    var d = document.createElement('div'); d.className = 'gb-ticker-dot'; d.textContent = '✦'; return d;
  }

  function makeItem(r, idx) {
    var item = document.createElement('div');
    item.className = 'gb-ticker-item gb-ticker-item-edit';
    item.dataset.reviewIdx = String(idx);
    var q = document.createElement('div');
    q.className = 'gb-ticker-quote';
    q.textContent = '"' + r.attr + '"';
    var a = document.createElement('div');
    a.className = 'gb-ticker-author';
    a.textContent = r.quote ? '— ' + r.quote : '';
    item.appendChild(q);
    item.appendChild(a);
    return item;
  }

  // Placeholder "+" card
  var ph = document.createElement('div');
  ph.className = 'gb-ticker-item gb-ticker-item-edit gb-ticker-item-ph';
  ph.dataset.reviewIdx = '-1';
  var phQ = document.createElement('div');
  phQ.className = 'gb-ticker-quote';
  phQ.textContent = '+ Add review';
  ph.appendChild(phQ);

  // Build first pass
  reviews.forEach(function(r, idx) {
    track.appendChild(makeItem(r, idx));
    track.appendChild(makeDot());
  });
  track.appendChild(ph);
  track.appendChild(makeDot());

  // Clone first pass for seamless loop — clones inherit data attributes, no handler needed
  var originals = Array.prototype.slice.call(track.childNodes);
  originals.forEach(function(node) { track.appendChild(node.cloneNode(true)); });

  // Event delegation on track — works for both originals and clones
  track.onclick = function(e) {
    e.stopPropagation();
    var item = e.target.closest('[data-review-idx]');
    if (!item) return;
    // Ignore clicks on the already-open editing card
    if (item.classList.contains('gb-ticker-item-editing')) return;
    var idx = parseInt(item.dataset.reviewIdx, 10);
    // Open the form in the clicked item (may be a clone) so it appears where the user clicked
    gbTickerEditOpen(idx, item);
  };

  // Hover pause
  if (wrap) {
    wrap.onmouseenter = function() { _gbTickerPaused = true; };
    wrap.onmouseleave = function() { if (!_gbTickerEditEl) _gbTickerPaused = false; };
  }

  // rAF seamless scroll
  var halfWidth = null, last = null, speed = 80;
  function step(ts) {
    if (halfWidth === null) halfWidth = track.scrollWidth / 2;
    if (!_gbTickerPaused) {
      if (last !== null) {
        _gbTickerPos += speed * (ts - last) / 1000;
        if (_gbTickerPos >= halfWidth) _gbTickerPos -= halfWidth;
        track.style.transform = 'translateX(-' + _gbTickerPos.toFixed(2) + 'px)';
      }
    }
    last = ts;
    _gbTickerRaf = requestAnimationFrame(step);
  }
  _gbTickerRaf = requestAnimationFrame(step);
}

function gbTickerEditOpen(idx, el) {
  if (_gbTickerEditEl) gbTickerEditCancel(); // restore previous card in-place, then open new
  _gbTickerEditIdx = idx;
  _gbTickerEditEl  = el;
  _gbTickerPaused  = true;
  var r = idx >= 0 ? (_ed.reviews || [])[idx] : null;

  el.className = 'gb-ticker-item gb-ticker-item-editing';
  el.onclick = null;

  var ta = document.createElement('textarea');
  ta.className = 'gb-ticker-edit-ta';
  ta.placeholder = 'Review quote…';
  ta.rows = 3;
  ta.value = r ? (r.attr || '') : '';

  var inp = document.createElement('input');
  inp.type = 'text';
  inp.className = 'gb-ticker-edit-inp';
  inp.placeholder = '— Source (e.g. Rahdo Runs Through)';
  inp.value = r ? (r.quote || '') : '';

  var actions = document.createElement('div');
  actions.className = 'gb-ticker-edit-actions';
  var bSave   = document.createElement('button'); bSave.className   = 'btn-adm';               bSave.textContent   = 'Save';   bSave.onmousedown   = function(e) { e.stopPropagation(); e.preventDefault(); gbTickerEditSave(); };
  var bCancel = document.createElement('button'); bCancel.className = 'btn-adm btn-adm-secondary'; bCancel.textContent = 'Cancel'; bCancel.onmousedown = function(e) { e.stopPropagation(); e.preventDefault(); gbTickerEditCancel(); };
  var bDel    = document.createElement('button'); bDel.className    = 'btn-adm btn-adm-danger';    bDel.textContent    = 'Delete'; bDel.onmousedown    = function(e) { e.stopPropagation(); e.preventDefault(); gbTickerEditDelete(); };
  if (idx < 0) bDel.style.display = 'none';
  actions.appendChild(bSave); actions.appendChild(bCancel); actions.appendChild(bDel);

  el.innerHTML = '';
  el.appendChild(ta);
  el.appendChild(inp);
  el.appendChild(actions);

  ta.focus();
  ta.setSelectionRange(ta.value.length, ta.value.length);

  // Close if user clicks anywhere outside this card
  setTimeout(function() {
    document.addEventListener('mousedown', _gbTickerOutside);
  }, 0);
}

function _gbTickerOutside(e) {
  if (_gbTickerEditEl && !_gbTickerEditEl.contains(e.target)) {
    document.removeEventListener('mousedown', _gbTickerOutside);
    gbTickerEditCancel();
  }
}

function _gbTickerRestoreCard(idx, el) {
  var r = idx >= 0 ? (_ed.reviews || [])[idx] : null;
  if (idx >= 0 && r) {
    el.className = 'gb-ticker-item gb-ticker-item-edit';
    el.innerHTML = '';
    var q = document.createElement('div'); q.className = 'gb-ticker-quote'; q.textContent = '"' + r.attr + '"';
    var a = document.createElement('div'); a.className = 'gb-ticker-author'; a.textContent = r.quote ? '— ' + r.quote : '';
    el.appendChild(q); el.appendChild(a);
    (function(i, e2) { e2.onclick = function(ev) { ev.stopPropagation(); gbTickerEditOpen(i, e2); }; })(idx, el);
  } else {
    el.className = 'gb-ticker-item gb-ticker-item-edit gb-ticker-item-ph';
    el.innerHTML = '';
    var phQ = document.createElement('div'); phQ.className = 'gb-ticker-quote'; phQ.textContent = '+ Add review';
    el.appendChild(phQ);
    (function(e2) { e2.onclick = function(ev) { ev.stopPropagation(); gbTickerEditOpen(-1, e2); }; })(el);
  }
}

function gbTickerEditSave() {
  if (!_gbTickerEditEl) return;
  document.removeEventListener('mousedown', _gbTickerOutside);
  var ta  = _gbTickerEditEl.querySelector('.gb-ticker-edit-ta');
  var inp = _gbTickerEditEl.querySelector('.gb-ticker-edit-inp');
  var q = ta ? ta.value.trim() : '', s = inp ? inp.value.trim() : '';
  if (!q) { gbTickerEditCancel(); return; }
  if (!_ed.reviews) _ed.reviews = [];
  if (_gbTickerEditIdx >= 0) {
    _ed.reviews[_gbTickerEditIdx] = {attr: q, quote: s};
  } else {
    _ed.reviews.push({attr: q, quote: s});
  }
  _gbTickerEditEl = null;
  gbInitReviewTicker();
}

function gbTickerEditDelete() {
  document.removeEventListener('mousedown', _gbTickerOutside);
  if (_gbTickerEditIdx < 0) { gbTickerEditCancel(); return; }
  if (!_ed.reviews) return;
  _ed.reviews.splice(_gbTickerEditIdx, 1);
  _gbTickerEditEl = null;
  gbInitReviewTicker();
}

function gbTickerEditCancel() {
  document.removeEventListener('mousedown', _gbTickerOutside);
  if (_gbTickerEditEl) {
    _gbTickerRestoreCard(_gbTickerEditIdx, _gbTickerEditEl);
  }
  _gbTickerPaused  = false;
  _gbTickerEditIdx = -1;
  _gbTickerEditEl  = null;
}

// Public page: start gallery auto-advance and ticker
(function() {
  if (!document.body.classList.contains('gb-admin-mode')) {
    var thumbs = document.querySelectorAll('#gbGalleryThumbs .gb-gallery-thumb');
    var featEl = document.getElementById('gbGalleryFeaturedImg');
    if (thumbs.length && featEl) {
      var pubGalleryIdx = 0;
      var pubUrls = Array.prototype.map.call(thumbs, function(t) { var i = t.querySelector('img'); return i ? i.getAttribute('src') : ''; }).filter(Boolean);
      // Set first image immediately
      if (pubUrls.length) { featEl.src = pubUrls[0]; featEl.style.display = ''; }
      function pubSelect(idx) {
        pubGalleryIdx = idx;
        featEl.classList.add('gb-gf-fade');
        setTimeout(function() {
          featEl.src = pubUrls[idx];
          featEl.onload = function() { featEl.classList.remove('gb-gf-fade'); featEl.onload = null; };
        }, 180);
        thumbs.forEach(function(tt, ii) { tt.classList.toggle('gb-gt-active', ii === idx); });
      }
      thumbs.forEach(function(t, i) { t.onclick = function() { pubSelect(i); }; });
      if (pubUrls.length > 1) {
        setInterval(function() { pubSelect((pubGalleryIdx + 1) % pubUrls.length); }, 4000);
      }
    }
  }
})();

// Public page: start ticker from PHP-rendered content using rAF
(function() {
  var wrap  = document.getElementById('gbTickerWrap');
  var track = document.getElementById('gbTickerTrack');
  if (!track || !track.children.length) return;
  if (document.body.classList.contains('gb-admin-mode')) return; // admin handles its own init

  var halfWidth = null, last = null, speed = 80;
  if (wrap) {
    wrap.onmouseenter = function() { _gbTickerPaused = true; };
    wrap.onmouseleave = function() { _gbTickerPaused = false; };
  }
  function step(ts) {
    if (halfWidth === null) halfWidth = track.scrollWidth / 2;
    if (!_gbTickerPaused) {
      if (last !== null) {
        _gbTickerPos += speed * (ts - last) / 1000;
        if (_gbTickerPos >= halfWidth) _gbTickerPos -= halfWidth;
        track.style.transform = 'translateX(-' + _gbTickerPos.toFixed(2) + 'px)';
      }
      last = ts;
    } else {
      last = ts;
    }
    _gbTickerRaf = requestAnimationFrame(step);
  }
  _gbTickerRaf = requestAnimationFrame(step);
})();

// ── Shared footer ────────────────────────────────────────────────────────────
function gbClickSocial(btn) {
  var url = btn.dataset.url;
  if (url) window.open(url, '_blank', 'noopener');
}
// ── Subscribe (shared footer) ─────────────────────────────────────────────────
function gbSubscribeSubmit(e) {
  e.preventDefault();
  var email = (document.getElementById('gbSubscribeEmail').value || '').trim();
  if (!email) return;
  var btn = document.getElementById('gbSubscribeBtn');
  var msg = document.getElementById('gbSubscribeMsg');
  btn.disabled = true;
  btn.textContent = 'Subscribing…';
  msg.textContent = '';
  msg.className = 'gb-footer-sub-msg';
  var fd = new FormData();
  fd.append('id',    SHEET_ID);
  fd.append('game',  'subscribe');
  fd.append('name',  '');
  fd.append('email', email);
  fd.append('note',  'Newsletter Subscribe');
  fetch(APP_ROOT + 'push/submitNote.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(d) {
      if (d.ok) {
        msg.textContent = "You’re subscribed! Thanks for signing up.";
        msg.className = 'gb-footer-sub-msg ok';
        document.getElementById('gbSubscribeEmail').value = '';
        btn.textContent = 'Subscribed ✓';
      } else {
        msg.textContent = d.error || 'Something went wrong. Please try again.';
        msg.className = 'gb-footer-sub-msg err';
        btn.disabled = false;
        btn.textContent = 'Subscribe';
      }
    })
    .catch(function() {
      msg.textContent = 'Network error. Please try again.';
      msg.className = 'gb-footer-sub-msg err';
      btn.disabled = false;
      btn.textContent = 'Subscribe';
    });
}
</script>

<!-- ── Fetch Log dialog ── -->
<div class="gbed-overlay" id="fetchLogOverlay">
  <div class="sync-dialog" onclick="event.stopPropagation()">
    <h2 id="fetchLogTitle">Fetching…</h2>
    <div class="sync-log" id="fetchLogBody"></div>
    <div class="sync-dialog-actions">
      <button class="btn-adm btn-adm-secondary" id="fetchLogDoneBtn" disabled onclick="closeFetchLog()">Close</button>
    </div>
  </div>
</div>

<style>
.sync-dialog { background:#fff; border-radius:10px; padding:1.4rem; width:min(480px,92vw); box-shadow:0 8px 32px rgba(0,0,0,.22); display:flex; flex-direction:column; gap:.75rem; max-height:calc(100dvh - 2rem); overflow-y:auto; }
.sync-dialog h2 { font-size:.95rem; margin:0; font-weight:700; }
.sync-log { background:#0f172a; border-radius:6px; padding:.75rem 1rem; min-height:6rem; max-height:14rem; overflow-y:auto; font-family:monospace; font-size:.75rem; line-height:1.6; color:#94a3b8; display:flex; flex-direction:column; }
.sync-log-line { display:block; }
.sync-log-line.ok    { color:#4ade80; }
.sync-log-line.skip  { color:#94a3b8; }
.sync-log-line.error { color:#f87171; }
.sync-log-line.info  { color:#60a5fa; }
.sync-dialog-actions { display:flex; align-items:center; justify-content:flex-end; gap:.5rem; position:sticky; bottom:0; background:#fff; padding-top:.5rem; margin-top:auto; }
</style>

</body>
</html>
