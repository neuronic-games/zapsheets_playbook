<?php
/**
 * BillBoard — polished public product page for a single board game.
 * URL: /{sheet_id}/billboard/{game-slug}
 * Data: games.json (main info) + {game_name}.json from Playbook Sheet (rich content)
 * Public / no sign-in required.
 */
error_reporting(0);

$_bpFile = dirname(__DIR__, 2) . '/dotEnv.php';
if (file_exists($_bpFile)) { require_once $_bpFile; }

$_rp   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_bp   = rtrim($_ENV['BASE_PATH'] ?? '', '/');
$_rps  = ($_bp !== '' && str_starts_with($_rp, $_bp)) ? substr($_rp, strlen($_bp)) : $_rp;

preg_match('#^/([A-Za-z0-9_\-]+)/billboard(?:/([^/?]*))?#', $_rps, $_m);
$_sheetId  = $_m[1] ?? '';
$_gameSlug = urldecode(trim($_m[2] ?? ''));

if (!$_sheetId) { http_response_code(404); exit; }

$_root    = dirname(__DIR__, 2);
$_sDir    = $_root . '/sheets/' . $_sheetId;
$_appRoot = $_bp . '/';

// Public share mode: $_bbPublicBase is injected by source/billboard/public/index.php
// e.g. 'billboard/abc123def456abc123def456/' — replaces sheet-ID-based path in all links.
$_bbBase = isset($_bbPublicBase)
    ? ($_appRoot . ltrim($_bbPublicBase, '/'))
    : ($_appRoot . $_sheetId . '/billboard/');

function _bb_load(string $f): array {
    return file_exists($f) ? (json_decode(file_get_contents($f), true) ?: []) : [];
}

/**
 * Renders the unified site footer for both the catalog page and individual game pages.
 * $opts keys:
 *   'gameCredit' string  — e.g. "Floristry · Designed by Alice & Bob" (page 2 only)
 */
function _bb_footer(array $opts = []): void {
    global $_subHeading, $_subText, $_tagline, $_pubLogo, $_pubName,
           $_copyright, $_footerNote, $_socialDefs, $_bbPublicBase;
    $gameCredit = trim($opts['gameCredit'] ?? '');
    $isAdmin    = !isset($_bbPublicBase);
    $heading    = $_subHeading ?: ($_tagline ?: 'Stay in the loop.');
    $desc       = $_subText ?: 'Join our newsletter for exciting announcements and special offers!';
    ?>
<footer class="bb-footer">
  <div class="bb-footer-main">
    <!-- Brand column: logo → tagline → social icons → copyright -->
    <div class="bb-footer-brand">
      <?php if ($_pubLogo): ?>
      <img src="<?= htmlspecialchars($_pubLogo, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($_pubName, ENT_QUOTES) ?>" class="bb-footer-logo-img" />
      <?php elseif ($_pubName): ?>
      <div class="bb-footer-bar-brand"><?= htmlspecialchars($_pubName, ENT_QUOTES) ?></div>
      <?php endif; ?>
      <?php if ($_tagline): ?>
      <div class="bb-footer-brand-tagline"><?= htmlspecialchars($_tagline, ENT_QUOTES) ?></div>
      <?php endif; ?>
      <div class="bb-footer-brand-icons" <?= $isAdmin ? 'id="bbFooterIcons"' : '' ?>>
        <?php if (!empty($_socialDefs)) foreach ($_socialDefs as $_sd):
          $_isSet = !empty($_sd['url']);
          if ($isAdmin || $_isSet): ?>
        <button class="bb-social-btn<?= $_isSet ? ' bb-social-set' : ' bb-social-empty' ?>"
                data-key="<?= htmlspecialchars($_sd['key'], ENT_QUOTES) ?>"
                data-label="<?= htmlspecialchars($_sd['label'], ENT_QUOTES) ?>"
                data-url="<?= htmlspecialchars($_sd['url'], ENT_QUOTES) ?>"
                onclick="bbClickSocial(this)"
                title="<?= htmlspecialchars($_sd['label'] . ($_isSet ? '' : ' — click to add'), ENT_QUOTES) ?>"><?= $_sd['svg'] ?></button>
        <?php endif; endforeach; ?>
      </div>
      <div class="bb-footer-brand-copy">
        <?php if ($isAdmin): ?>
        <span class="bb-footer-editable"
              contenteditable="false"
              id="bbFooterCopyright"
              data-setting="copyright"
              data-placeholder="© <?= date('Y') ?> <?= htmlspecialchars($_pubName ?: 'Your Company', ENT_QUOTES) ?>"
              onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}"
              onblur="bbSaveFooterField(this)"><?= htmlspecialchars($_copyright, ENT_QUOTES) ?></span>
        <span class="bb-footer-editable"
              contenteditable="false"
              id="bbFooterNote"
              data-setting="footer_note"
              data-placeholder="Tagline or contact info…"
              onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}"
              onblur="bbSaveFooterField(this)"><?= htmlspecialchars($_footerNote, ENT_QUOTES) ?></span>
        <?php else: ?>
        <span><?= htmlspecialchars($_copyright, ENT_QUOTES) ?></span>
        <?php if ($_footerNote): ?><span><?= htmlspecialchars($_footerNote, ENT_QUOTES) ?></span><?php endif; ?>
        <?php endif; ?>
        <?php if ($gameCredit): ?>
        <span class="bb-footer-game-credit"><?= htmlspecialchars($gameCredit, ENT_QUOTES) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <!-- Subscribe column -->
    <div class="bb-footer-subscribe">
      <div class="bb-footer-sub-label"><?= htmlspecialchars($heading, ENT_QUOTES) ?></div>
      <div class="bb-footer-sub-desc"><?= htmlspecialchars($desc, ENT_QUOTES) ?></div>
      <form class="bb-footer-sub-form" id="bbSubscribeForm" onsubmit="bbSubscribeSubmit(event)">
        <input type="email" id="bbSubscribeEmail" class="bb-footer-sub-input" placeholder="Your email" required autocomplete="email" />
        <button type="submit" class="bb-footer-sub-btn" id="bbSubscribeBtn">Subscribe</button>
      </form>
      <p class="bb-footer-sub-msg" id="bbSubscribeMsg"></p>
    </div>
  </div>
</footer>
    <?php
}

function _bb_slug(string $s): string {
    return strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
}

function _bb_img(string $raw): string {
    if (preg_match('/^=IMAGE\("([^"]*)"\)/i', $raw, $m)) return $m[1];
    return $raw;
}

// Strip markdown hyperlink [text](url) → url, or =IMAGE("url") → url
function _bb_link(string $raw): string {
    $raw = trim($raw);
    if (preg_match('/^\[.*?\]\((.+?)\)$/', $raw, $m)) return trim($m[1]);
    return $raw;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function _bb_embed(string $url): string {
    if (!$url) return '';
    if (preg_match('/youtube\.com\/embed\//', $url)) return $url;
    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_\-]+)/', $url, $m))
        return 'https://www.youtube.com/embed/' . $m[1];
    if (preg_match('/youtube\.com\/shorts\/([A-Za-z0-9_\-]+)/', $url, $m))
        return 'https://www.youtube.com/embed/' . $m[1];
    return '';  // skip non-YouTube for embed safety
}

// ── Load settings and determine template ────────────────────────────────────
$_settings = _bb_load($_sDir . '/settings.json');


$_template = strtolower(trim($_settings['Billboard Template'] ?? ''));
// Supported templates: '' / 'classic' = Classic (default), 'opensea' = OpenSea

// ── Load games list ──────────────────────────────────────────────────────────
$_allGames = _bb_load($_sDir . '/games.json');
// Catalog only shows Published games; individual pages work for any game
$_games = array_values(array_filter($_allGames, fn($g) => strtolower(trim($g['Status'] ?? '')) === 'published'));

// Shared image-file SVG icon used on every image-change button
$_imgBtnIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';

// ── Shared settings (available to both catalog and game pages) ────────────────
$_pubName    = trim($_settings['Company'] ?? $_settings['Name'] ?? '');
$_pubLogo    = trim($_settings['Logo URL'] ?? '') ?: ($_appRoot . 'images/bb_logo.png');
$_tagline    = trim($_settings['Tagline'] ?? '');
$_insta      = trim($_settings['Instagram'] ?? '');
$_twitter    = trim($_settings['Twitter']   ?? '');
$_fb         = trim($_settings['Facebook']  ?? '');
$_yt         = trim($_settings['YouTube']   ?? '');
$_bgg        = trim($_settings['BGG URL']   ?? '');
$_copyright  = trim($_settings['Copyright']   ?? '') ?: '© ' . date('Y') . ' ' . ($_pubName ?: 'BillBoard');
$_footerNote = trim($_settings['Footer Note']       ?? '');
$_subHeading = trim($_settings['Subscribe Heading'] ?? '');
$_subText    = trim($_settings['Subscribe Text']    ?? '');
$_socialDefs = [
  ['key'=>'instagram','label'=>'Instagram',    'url'=>$_insta,   'svg'=>'<svg width="16" height="16" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>'],
  ['key'=>'twitter',  'label'=>'X / Twitter', 'url'=>$_twitter, 'svg'=>'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.73-8.835L1.254 2.25H8.08l4.253 5.622L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>'],
  ['key'=>'facebook', 'label'=>'Facebook',    'url'=>$_fb,      'svg'=>'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>'],
  ['key'=>'youtube',  'label'=>'YouTube',     'url'=>$_yt,      'svg'=>'<svg width="16" height="12" viewBox="0 0 24 17"><path d="M23.495 2.205a3.02 3.02 0 0 0-2.122-2.136C19.545 0 12 0 12 0S4.455 0 2.627.07a3.02 3.02 0 0 0-2.122 2.135C0 4.046 0 8.5 0 8.5s0 4.455.505 6.295a3.02 3.02 0 0 0 2.122 2.136C4.455 17 12 17 12 17s7.545 0 9.373-.07a3.02 3.02 0 0 0 2.122-2.135C24 12.954 24 8.5 24 8.5s0-4.454-.505-6.295zM9.545 12.273V4.727L15.818 8.5l-6.273 3.773z"/></svg>'],
  ['key'=>'bgg_url',  'label'=>'BoardGameGeek','url'=>$_bgg,    'svg'=>'<svg width="10" height="14" viewBox="0 0 60 90" fill="currentColor"><path d="M58.584 16.512l-8.864 2.415L58.4 0 2.119 20.715l3.077 24.813L0 50.537l15.494 38.811 32.932-12.134 11.448-26.875-4.914-4.738z"/></svg>'],
];

if (!$_gameSlug) {
    // ── No slug: render the admin catalog ────────────────────────────────────────
    $_catUrl   = $_bbBase;
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
            $_cu = _bb_img(trim($_g['Cover URL'] ?? $_g['Image URL'] ?? $_g['Image'] ?? ''));
            if ($_cu) { $_carousel[] = $_cu; if (count($_carousel) >= 6) break; }
        }
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= h($_pubName ?: 'BillBoard') ?></title>
<?php require_once dirname(__DIR__, 2) . '/push/linkPreview.php'; echo gbLinkPreview('billboard', $_sheetId, array_filter(['title' => $_pubName ?: null, 'image' => (trim($_settings['Logo URL'] ?? '') && preg_match('#^https?://#', trim($_settings['Logo URL'] ?? ''))) ? trim($_settings['Logo URL']) : null])); ?>
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
.bb-top-wrap { position:sticky; top:0; z-index:100; }
/* ── Admin bar (hidden in public) ── */
.bb-admin-bar { background:#0d0d1a; color:rgba(255,255,255,.55); display:flex; align-items:center; justify-content:space-between; padding:.3rem 1.25rem; gap:1rem; }
.bb-admin-label { font-family:'DINBlack',sans-serif; font-size:.6rem; text-transform:uppercase; letter-spacing:.12em; color:rgba(255,255,255,.22); }
.bb-wm-bill { color:#9fe0b8; } .bb-wm-board { color:#f2c14e; }
.user-menu-wrap { position:relative; }
.bb-admin-tools { display:flex; align-items:center; gap:.5rem; flex-shrink:0; }
.bb-meta { font-size:.72rem; opacity:.55; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:200px; }
.btn-add-game { font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase; letter-spacing:.07em; padding:.28rem .8rem; border-radius:5px; border:1.5px solid rgba(255,255,255,.35); color:#fff; background:transparent; cursor:pointer; transition:background .14s,border-color .14s; white-space:nowrap; }
.btn-add-game:hover { background:rgba(255,255,255,.14); border-color:#fff; }
/* ── Page banner (always visible) ── */
.bb-banner { background:var(--banner-bg); color:#fff; display:flex; align-items:center; justify-content:space-between; padding:1.1rem 2rem; gap:1.5rem; }
.bb-banner-left { display:flex; align-items:center; gap:.9rem; min-width:0; }
.bb-banner-name { font-family:'DINBlack',sans-serif; font-size:1.1rem; letter-spacing:.06em; opacity:.75; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; outline:none; }
.bb-banner-name-editable { border-bottom:1.5px solid transparent; border-radius:2px; transition:border-color .14s,opacity .14s; min-width:6px; display:inline-block; }
body.bb-admin-mode .bb-banner-name-editable { cursor:text; opacity:.85; border-bottom-color:rgba(255,255,255,.25); }
body.bb-admin-mode .bb-banner-name-editable:hover { border-bottom-color:rgba(255,255,255,.55); opacity:1; }
body.bb-admin-mode .bb-banner-name-editable:focus { border-bottom-color:#fff; opacity:1; }
.bb-banner-name-editable:empty:before { content:attr(data-placeholder); opacity:.35; font-style:italic; pointer-events:none; }
.bb-banner-nav { display:flex; align-items:center; gap:1.75rem; flex:1; justify-content:center; }
.bb-banner-nav a { font-family:'DINBlack',sans-serif; font-size:.82rem; text-transform:uppercase; letter-spacing:.06em; color:rgba(255,255,255,.75); transition:color .14s; white-space:nowrap; }
.bb-banner-nav a:hover { color:#fff; }
.bb-banner-right { display:flex; align-items:center; gap:.6rem; flex-shrink:0; }
/* Color picker button */
.bb-banner-color-btn { width:30px; height:30px; border-radius:50%; border:2px solid rgba(255,255,255,.35); background:transparent; color:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:border-color .14s,background .14s; padding:0; flex-shrink:0; }
.bb-banner-color-btn:hover { border-color:#fff; background:rgba(255,255,255,.15); }
body:not(.bb-admin-mode) .bb-banner-color-btn { display:none; }   /* the banner color is the owner's to change */

/* User icon + menu (matches T&MBoard) */
.btn-user-icon {
  width:32px; height:32px; border-radius:50%; border:1.5px solid rgba(255,255,255,.3);
  background:transparent; color:#fff; cursor:pointer; display:flex; align-items:center;
  justify-content:center; transition:background .14s, border-color .14s;
}
.btn-user-icon:hover, .btn-user-icon.signed-in { background:rgba(255,255,255,.15); border-color:#fff; }
.btn-user-icon.signed-in { border-color:<?= h($_logoColor) ?>; color:<?= h($_logoColor) ?>; }
.user-menu-wrap { position:relative; flex-shrink:0; }
.user-menu {
  display:none; position:absolute; top:calc(100% + .4rem); right:0;
  background:#1a1a2e; border:1px solid rgba(255,255,255,.2);
  border-radius:8px; min-width:150px; z-index:300;
  box-shadow:0 6px 20px rgba(0,0,0,.4); overflow:hidden;
}
.user-menu.open { display:block; }
.user-menu-item {
  display:block; width:100%; background:none; border:none;
  color:rgba(255,255,255,.85); text-align:left; cursor:pointer;
  font-family:'DINBlack',sans-serif; font-size:.72rem;
  text-transform:uppercase; letter-spacing:.07em;
  padding:.6rem 1rem; transition:background .12s;
}
.user-menu-item:hover { background:rgba(255,255,255,.1); color:#fff; }
.user-menu-item.signout { color:#ff6b6b; }
.user-menu-divider { border:none; border-top:1px solid rgba(255,255,255,.12); margin:.2rem 0; }

/* ── Logo placeholder ── */
.bb-logo-wrap { position:relative; display:inline-flex; align-items:center; flex-shrink:0; }
.bb-logo-ph { display:flex; align-items:center; justify-content:center; height:48px; min-width:96px; padding:0 .85rem; border:1.5px dashed rgba(255,255,255,.3); border-radius:6px; font-family:'DINBlack',sans-serif; font-size:.62rem; text-transform:uppercase; letter-spacing:.1em; color:rgba(255,255,255,.35); white-space:nowrap; transition:border-color .15s; cursor:pointer; }
.bb-logo-wrap.bb-drag-over .bb-logo-ph { border-color:rgba(255,255,255,.7); background:rgba(255,255,255,.07); }
.bb-logo-edit-btn { position:absolute; top:-.45rem; right:-.45rem; z-index:6; }
.bb-logo-img { height:48px; object-fit:contain; flex-shrink:0; }

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
.cat-slide-edit-btn { top:.5rem; right:.5rem; width:2rem; height:2rem; border-radius:50%; background:rgba(0,0,0,.55); color:#fff; }
.cat-slide-edit-btn svg { pointer-events:none; }
.cat-slide-del-btn { bottom:.75rem; right:.75rem; width:1.6rem; height:1.6rem; border-radius:50%; background:rgba(200,50,50,.75); color:#fff; font-size:1rem; line-height:1; }
.cat-slide-del-btn:hover { background:rgba(200,50,50,1); }
body.bb-admin-mode .cat-slide-edit-btn,
body.bb-admin-mode .cat-slide-del-btn { display:flex; }
body.bb-admin-mode .cat-slide-edit-btn:hover { background:rgba(0,0,0,.85); }

/* ── About strip ── */
.cat-about { max-width:720px; margin:2.5rem auto 0; padding:0 1.5rem; text-align:center; }
.cat-about-text { font-size:.93rem; line-height:1.7; color:var(--ink-lt); }

/* ── Section heading ── */
.cat-section-head { max-width:1100px; margin:2.5rem auto .75rem; padding:0 1.25rem; display:flex; align-items:baseline; gap:.75rem; }
.cat-section-title { font-family:'DINBlack',sans-serif; font-size:1rem; text-transform:uppercase; letter-spacing:.06em; color:var(--ink); }
.cat-section-count { font-size:.78rem; color:var(--muted); }

/* ── Grid ── */
.cat-grid { max-width:1100px; margin:0 auto 5rem; padding:0 1.25rem; display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:1.1rem; }
.cat-card { background:var(--surface); border-radius:12px; box-shadow:0 2px 14px rgba(0,0,0,.08); overflow:hidden; transition:box-shadow .15s, transform .15s; cursor:pointer; display:flex; flex-direction:column; width:100%; text-decoration:none; flex:1; }
.cat-card:hover { box-shadow:0 6px 24px rgba(0,0,0,.14); transform:translateY(-3px); }
.cat-card-img { width:100%; aspect-ratio:1; object-fit:cover; display:block; }
.cat-card-ph { width:100%; aspect-ratio:1; display:flex; align-items:center; justify-content:center; font-size:3.5rem; opacity:.28; background:linear-gradient(135deg,#dde,#c8c8e8); }
.cat-card-body { padding:.75rem .9rem 1rem; flex:1; display:flex; flex-direction:column; gap:.2rem; }
.cat-card-name { font-family:'DINBlack',sans-serif; font-size:.95rem; color:var(--accent); line-height:1.2; }
.cat-card-summary { font-size:.8rem; color:var(--ink-lt); line-height:1.45; flex:1; margin-top:.1rem; }
.cat-card-meta { display:flex; flex-wrap:wrap; gap:.3rem .65rem; margin-top:.35rem; }
.cat-card-stat { font-size:.72rem; color:var(--muted); }
.cat-card-wrap { position:relative; display:flex; flex-direction:column; }
.cat-card-del-btn { display:none; position:absolute; bottom:.55rem; right:.55rem; width:1.6rem; height:1.6rem; border-radius:50%; border:none; background:rgba(220,50,50,.85); color:#fff; font-size:.7rem; line-height:1; cursor:pointer; align-items:center; justify-content:center; z-index:3; transition:background .13s; padding:0; }
.cat-card-del-btn:hover { background:#c0392b; }
body.bb-admin-mode .cat-card-del-btn { display:flex; }

/* ── Shared image-change button (admin mode) ── */
.bb-img-btn { width:2rem; height:2rem; border-radius:50%; background:rgba(0,0,0,.52); color:#fff; display:none; align-items:center; justify-content:center; cursor:pointer; transition:background .15s; line-height:1; padding:0; user-select:none; border:none; flex-shrink:0; }
.bb-img-btn:hover { background:rgba(0,0,0,.82); }
.bb-img-btn svg { pointer-events:none; }
body.bb-admin-mode .bb-img-btn { display:flex; }

/* ── Cat card image upload (admin mode) ── */
.cat-img-wrap { position:relative; display:block; width:100%; }
.cat-img-upload-btn { position:absolute; top:.4rem; right:.4rem; z-index:2; }
.cat-img-wrap.drag-over::after { content:'Drop to set image'; position:absolute; inset:0; background:rgba(55,110,200,.75); color:#fff; font-family:'DINBlack',sans-serif; font-size:.8rem; text-transform:uppercase; letter-spacing:.07em; display:flex; align-items:center; justify-content:center; z-index:3; pointer-events:none; }
/* Loading overlay on card image during upload */
@keyframes bbImgSpin { to { transform:translate(-50%,-50%) rotate(360deg); } }
.cat-img-wrap.img-loading::after { content:''; position:absolute; inset:0; background:rgba(0,0,0,.45); z-index:4; pointer-events:none; border-radius:inherit; }
.cat-img-wrap.img-loading::before { content:''; position:absolute; top:50%; left:50%; width:28px; height:28px; border:3px solid rgba(255,255,255,.3); border-top-color:#fff; border-radius:50%; animation:bbImgSpin .7s linear infinite; z-index:5; pointer-events:none; }
/* Loading overlay on carousel slide during upload */
.cat-slide-loading { position:absolute; inset:0; background:rgba(0,0,0,.45); z-index:20; pointer-events:all; }
.cat-slide-loading::after { content:''; position:absolute; top:50%; left:50%; width:36px; height:36px; border:3px solid rgba(255,255,255,.3); border-top-color:#fff; border-radius:50%; animation:bbImgSpin .7s linear infinite; transform:translate(-50%,-50%); }
.cat-carousel-add-btn { display:none; position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); z-index:11; width:4rem; height:4rem; border-radius:50%; border:2.5px dashed rgba(255,255,255,.55); background:rgba(0,0,0,.25); color:rgba(255,255,255,.8); font-size:2rem; line-height:1; cursor:pointer; align-items:center; justify-content:center; transition:background .15s, border-color .15s, color .15s; }
.cat-carousel-add-btn:hover { background:rgba(0,0,0,.55); border-color:#fff; color:#fff; }
body.bb-admin-mode .cat-carousel-add-btn { display:flex; }
#catCarousel.is-uploading .cat-carousel-add-btn { display:none !important; }

/* ── Empty ── */
.cat-empty { grid-column:1/-1; padding:4rem 1rem; text-align:center; color:var(--muted); }
.cat-empty-icon { font-size:3rem; margin-bottom:.75rem; }
.cat-empty-title { font-family:'DINBlack',sans-serif; font-size:1rem; color:var(--ink); margin-bottom:.4rem; }
.cat-empty-body { font-size:.85rem; line-height:1.6; }

/* ── Footer ── */
.bb-footer { background:#1c1c1e; color:rgba(255,255,255,.5); }
.bb-footer-main { max-width:1100px; margin:0 auto; padding:3.5rem 2rem 3rem; display:grid; grid-template-columns:1fr 1.15fr; gap:2.5rem 5rem; align-items:start; }
@media(max-width:680px){ .bb-footer-main { grid-template-columns:1fr; padding:2.5rem 1.5rem 2.5rem; gap:2rem; } }
/* Brand column */
.bb-footer-brand { display:flex; flex-direction:column; gap:.7rem; }
.bb-footer-logo-img { height:52px; object-fit:contain; object-position:left center; display:block; }
.bb-footer-bar-brand { font-family:'DINBlack',sans-serif; font-size:1.15rem; color:rgba(255,255,255,.9); }
.bb-footer-brand-tagline { font-size:.83rem; color:rgba(255,255,255,.42); line-height:1.65; }
.bb-footer-brand-icons { display:flex; gap:.45rem; align-items:center; }
.bb-footer-brand-copy { display:flex; flex-direction:column; gap:.18rem; font-size:.71rem; color:rgba(255,255,255,.28); margin-top:.15rem; }
.bb-footer-game-credit { font-size:.68rem; color:rgba(255,255,255,.2); }
/* Subscribe column */
.bb-footer-sub-label { font-family:'DINBlack',sans-serif; font-size:1rem; color:#fff; margin-bottom:.3rem; }
.bb-footer-sub-desc { font-size:.82rem; color:rgba(255,255,255,.42); margin-bottom:1rem; line-height:1.55; }
.bb-footer-sub-form { display:flex; gap:.4rem; }
.bb-footer-sub-input { flex:1; min-width:0; padding:.62rem 1rem; border:none; border-radius:999px; background:rgba(255,255,255,.1); color:#fff; font-size:.88rem; outline:none; transition:background .18s; }
.bb-footer-sub-input::placeholder { color:rgba(255,255,255,.35); }
.bb-footer-sub-input:focus { background:rgba(255,255,255,.16); }
.bb-footer-sub-btn { padding:.62rem 1.4rem; border:none; border-radius:999px; background:var(--accent); color:#fff; font-family:'DINBlack',sans-serif; font-size:.78rem; letter-spacing:.05em; cursor:pointer; transition:opacity .18s; white-space:nowrap; }
.bb-footer-sub-btn:hover { opacity:.85; }
.bb-footer-sub-btn:disabled { opacity:.55; cursor:default; }
.bb-footer-sub-msg { margin-top:.55rem; font-size:.77rem; min-height:1em; }
.bb-footer-sub-msg.ok  { color:#6bd98a; }
.bb-footer-sub-msg.err { color:#e07070; }
/* Social buttons */
.bb-social-btn { width:1.85rem; height:1.85rem; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:border-color .14s,background .14s; cursor:pointer; padding:0; }
.bb-social-btn svg { fill:rgba(255,255,255,.6); pointer-events:none; }
.bb-social-set { border:1.5px solid rgba(255,255,255,.25); background:transparent; }
.bb-social-set:hover { border-color:rgba(255,255,255,.6); background:rgba(255,255,255,.08); }
.bb-social-empty { border:1.5px dashed rgba(255,255,255,.18); background:transparent; display:none; }
.bb-social-empty svg { opacity:.35; }
body.bb-admin-mode .bb-social-empty { display:flex; }
body.bb-admin-mode .bb-social-empty:hover { border-color:rgba(255,255,255,.4); background:rgba(255,255,255,.05); }
body.bb-admin-mode .bb-social-btn { cursor:pointer; }
/* Inline-edit fields in footer (admin only) */
.bb-footer-editable { outline:none; cursor:default; border-radius:3px; transition:background .13s,border-color .13s; min-width:4px; display:inline-block; border-bottom:1.5px solid transparent; }
body.bb-admin-mode .bb-footer-editable { cursor:text; border-bottom-color:rgba(255,255,255,.18); padding-bottom:1px; }
body.bb-admin-mode .bb-footer-editable:hover { border-bottom-color:rgba(255,255,255,.4); }
body.bb-admin-mode .bb-footer-editable:focus { border-bottom-color:rgba(255,255,255,.75); outline:none; }
.bb-footer-editable:empty:before { content:attr(data-placeholder); color:rgba(255,255,255,.22); pointer-events:none; font-style:italic; }
/* Save button highlight */


/* ── Overlay / Dialog (sign-in + settings) ── */
.bb-overlay {
  display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:500;
  align-items:center; justify-content:center;
}
.bb-overlay.open { display:flex; }
.bb-dialog {
  background:#fff; border-radius:12px; padding:1.6rem 1.75rem 1.5rem;
  width:90%; max-width:360px; box-shadow:0 8px 40px rgba(0,0,0,.28);
}
.bb-dialog-title { font-family:'DINBlack',sans-serif; font-size:1rem; text-transform:uppercase; letter-spacing:.07em; color:var(--ink); margin-bottom:1rem; }
.bb-field-group { margin-bottom:.7rem; }
.bb-field-group label { display:block; font-size:.75rem; color:var(--muted); margin-bottom:.25rem; }
.bb-field { display:block; width:100%; padding:.55rem .75rem; border:1.5px solid var(--border); border-radius:7px; font-family:'DINRegular',sans-serif; font-size:.9rem; color:var(--ink); outline:none; transition:border-color .14s; }
.bb-field:focus { border-color:var(--accent); }
.bb-err, .bbed-err { font-size:.78rem; color:#e53935; min-height:1.1rem; margin:.3rem 0; }
.bb-dialog-close { position:absolute; top:.75rem; right:.9rem; background:none; border:none; font-size:1.35rem; color:var(--ink-lt); cursor:pointer; line-height:1; }
.bb-dialog { position:relative; }
.bb-btn { font-family:'DINBlack',sans-serif; font-size:.75rem; text-transform:uppercase; letter-spacing:.07em; padding:.42rem 1rem; border-radius:6px; border:1.5px solid var(--border); background:var(--surface); color:var(--ink); cursor:pointer; }
.bb-btn:hover { background:#f0f0f8; }
.bb-btn-sm { font-family:'DINBlack',sans-serif; font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; padding:.3rem .65rem; border-radius:5px; border:1.5px solid var(--border); background:var(--surface); color:var(--ink-lt); cursor:pointer; white-space:nowrap; }
.bb-btn-sm:hover { background:#f0f0f8; color:var(--ink); }
.bb-dialog-actions { display:flex; gap:.5rem; justify-content:flex-end; margin-top:.9rem; }
.btn-cancel { font-family:'DINBlack',sans-serif; font-size:.75rem; text-transform:uppercase; letter-spacing:.07em; padding:.42rem .9rem; border-radius:6px; border:1.5px solid var(--border); background:var(--surface); color:var(--ink-lt); cursor:pointer; }
.btn-cancel:hover { background:#f0f0f8; }
.btn-primary { font-family:'DINBlack',sans-serif; font-size:.75rem; text-transform:uppercase; letter-spacing:.07em; padding:.42rem .9rem; border-radius:6px; border:none; background:var(--accent); color:#fff; cursor:pointer; transition:opacity .13s; }
.btn-primary:disabled { opacity:.45; cursor:default; }
.btn-primary:not(:disabled):hover { opacity:.88; }
#siConfirmPanel { display:none; }

@media(max-width:520px) { .cat-grid { grid-template-columns:1fr 1fr; } }
@media(max-width:360px) { .cat-grid { grid-column:1; } }

/* ── Shared bbed dialog styles (also used by Add Game) ── */
.bbed-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:600; align-items:flex-start; justify-content:center; padding:2rem 1rem; overflow-y:auto; }
.bbed-overlay.open { display:flex; }
.bbed-dialog { background:#fff; border-radius:14px; width:100%; max-width:520px; box-shadow:0 12px 50px rgba(0,0,0,.32); overflow:hidden; margin:auto; }
.bbed-header { background:var(--nav-bg); color:#fff; padding:1rem 1.25rem; display:flex; align-items:center; justify-content:space-between; }
.bbed-title { font-family:'DINBlack',sans-serif; font-size:.9rem; text-transform:uppercase; letter-spacing:.07em; }
.bbed-close { background:none; border:none; color:rgba(255,255,255,.6); font-size:1.2rem; cursor:pointer; padding:0 .25rem; line-height:1; }
.bbed-close:hover { color:#fff; }
.bbed-body { overflow:hidden; }
.bbed-scroll { padding:1.25rem 1.5rem 1.5rem; max-height:calc(80vh - 120px); overflow-y:auto; }
.bbed-section { margin-bottom:1.1rem; }
.bbed-section-title { font-family:'DINBlack',sans-serif; font-size:.65rem; text-transform:uppercase; letter-spacing:.1em; color:var(--muted); border-bottom:1px solid var(--border); padding-bottom:.35rem; margin-bottom:.65rem; }
.bbed-row { display:flex; gap:.6rem; margin-bottom:.55rem; }
.bbed-row > * { flex:1; min-width:0; }
.bbed-field-group { margin-bottom:.55rem; }
.bbed-label { display:block; font-size:.73rem; color:var(--muted); margin-bottom:.2rem; }
.bbed-field { display:block; width:100%; padding:.45rem .7rem; border:1.5px solid var(--border); border-radius:6px; font-family:'DINRegular',Arial,sans-serif; font-size:.87rem; color:var(--ink); outline:none; transition:border-color .14s; }
.bbed-field:focus { border-color:var(--accent); }
.bbed-footer { padding:.9rem 1.5rem; background:#f8f8fc; border-top:1px solid var(--border); display:flex; gap:.5rem; justify-content:flex-end; align-items:center; }
.bbed-footer .bbed-err { margin:0; flex:1; }
.btn-adm { font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase; letter-spacing:.07em; padding:.38rem .9rem; border-radius:6px; cursor:pointer; border:1.5px solid transparent; white-space:nowrap; transition:opacity .13s; }
.btn-adm-primary { background:var(--accent); color:#fff; border-color:var(--accent); }
.btn-adm-primary:hover { opacity:.88; }
.btn-adm-secondary { background:transparent; color:var(--ink-lt); border-color:var(--border); }
.btn-adm-secondary:hover { background:#f0f0f8; }
/* Add-game card (admin only) — matches game card shape: grey image area + white body */
.bb-add-game-card { display:none; flex-direction:column; border-radius:12px; border:2px dashed rgba(59,59,170,.22); background:#fff; cursor:pointer; transition:border-color .15s, box-shadow .15s, transform .15s; overflow:hidden; box-shadow:0 2px 14px rgba(0,0,0,.06); }
body.bb-admin-mode .bb-add-game-card { display:flex; }
.bb-add-game-card:hover { border-color:var(--accent); box-shadow:0 6px 24px rgba(0,0,0,.12); transform:translateY(-3px); }
.bb-add-game-card-inner { position:relative; width:100%; aspect-ratio:1; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:3rem; color:rgba(59,59,170,.28); line-height:1; transition:color .15s, background .15s; background:linear-gradient(135deg,#e8e8f2,#d8d8ec); overflow:hidden; }
.bb-add-game-card:hover .bb-add-game-card-inner { color:var(--accent); background:linear-gradient(135deg,#e0e0f0,#cecee8); }
.bb-agc-plus { pointer-events:none; transition:opacity .15s; }
.bb-add-game-card-inner.img-loading .bb-agc-plus { opacity:0; }
.bb-add-game-card-inner.img-loading::after { content:''; position:absolute; top:50%; left:50%; width:32px; height:32px; border:3px solid rgba(59,59,170,.2); border-top-color:rgba(59,59,170,.7); border-radius:50%; animation:bbImgSpin .7s linear infinite; z-index:3; pointer-events:none; transform:translate(-50%,-50%); }
.bb-agc-img-btn { position:absolute; top:.4rem; right:.4rem; z-index:2; }
.bb-agc-preview { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; z-index:1; }
.bb-add-game-card-label { padding:.75rem .9rem 1rem; flex:1; display:flex; flex-direction:column; gap:.2rem; }
.bb-agc-name { outline:none; font-family:'DINBlack',sans-serif; font-size:.95rem; color:rgba(59,59,170,.45); cursor:text; display:block; min-width:2ch; transition:color .15s; }
.bb-add-game-card:hover .bb-agc-name { color:var(--accent); }
.bb-agc-name:focus { color:var(--accent); border-bottom:1.5px solid var(--accent); }
.bb-agc-tagline { outline:none; font-size:.8rem; color:var(--ink-lt); cursor:text; display:none; min-width:2ch; border:none; background:transparent; font-family:'DINRegular',Arial,sans-serif; padding:0; width:100%; }
.bb-agc-tagline.visible { display:block; }
.bb-agc-tagline:focus { border-bottom:1px solid var(--border); }
.bb-agc-tagline-ph { font-size:.8rem; color:var(--ink-lt); opacity:.5; display:block; pointer-events:none; }
.bb-agc-tagline.visible ~ .bb-agc-tagline-ph { display:none; }
.cat-draft-badge { position:absolute; top:.45rem; left:.45rem; background:rgba(200,120,0,.88); color:#fff; font-family:'DINBlack',sans-serif; font-size:.6rem; text-transform:uppercase; letter-spacing:.06em; padding:.18rem .45rem; border-radius:4px; pointer-events:none; z-index:2; }
.cat-draft-wrap .cat-card { pointer-events:none; opacity:.85; }
.bb-plan-title { display:flex; align-items:center; justify-content:center; gap:.5rem; margin-bottom:.45rem; }
.bb-plan-title img { width:28px; height:28px; border-radius:7px; }
.bb-plan-current { width:fit-content; margin:0 auto .7rem; font-size:.8rem; color:#555; background:#f1f4f8; border-radius:999px; padding:.22rem .8rem; }
.bb-plan-current:empty { display:none; }
.bb-plan-current b { color:#333; } .bb-plan-current span { color:#888; }
.bb-plan-current.paid { background:#edf7ed; } .bb-plan-current.paid b { color:#2e7d32; }
</style>
<link rel="stylesheet" href="<?= h($_appRoot) ?>css/gameboard-ui.css?v=6" />
<?php if (!isset($_bbPublicBase)): ?><script src="<?= h($_appRoot) ?>js/gb-account.js?v=11"></script><?php endif; ?>
</head>
<body<?= isset($_bbPublicBase) ? '' : ' class="gb-app gb-bill"' ?>>

<!-- ── Sticky top wrapper ── -->
<div class="bb-top-wrap">

  <?php if (!isset($_bbPublicBase)): ?>
  <!-- Admin bar: editing tools only, hidden in public mode. The same top bar as every GameBoard app. -->
  <div class="top-bar bb-admin-bar">
   <div class="top-bar-inner">
    <div class="top-bar-left">
      <img class="top-bar-icon" src="<?= h($_appRoot) ?>images/bb_icon_180.png" alt="BillBoard">
      <div class="top-bar-name">
        <h1><span class="bb-wm-bill">Bill</span><span class="bb-wm-board">Board</span></h1>
        <p class="sub" id="bbMeta"><?= h($_pubName ?: 'Games') ?></p>
      </div>
    </div>
    <div class="account-menu-wrap bb-admin-tools">
      <button class="top-save" id="bbSavePageBtn" onclick="bbSavePage()" style="display:none">Save</button>
      <div class="user-menu-wrap">
        <button class="btn-user-icon top-btn top-menu-btn" id="bbUserBtn" onclick="toggleBbMenu(event)" title="Menu">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="7.5" r="4.5"/><path d="M3.5 21c0-4.14 3.81-7.5 8.5-7.5s8.5 3.36 8.5 7.5"/></svg>
        </button>
        <div class="user-menu" id="bbMenu">
          <button class="user-menu-item" id="bbMenuProfile" onclick="bbMenuAction('profile')">Profile</button>
          <button class="user-menu-item" onclick="bbMenuAction('settings')">Settings</button>
          <button class="user-menu-item" onclick="bbMenuAction('analytics')">📊 Analytics</button>
          <hr class="user-menu-divider" />
          <button class="user-menu-item" onclick="bbMenuAction('fetch')">Fetch</button>
          <button class="user-menu-item" id="bbMenuPublish" onclick="bbMenuAction('publish')" style="display:none">🌐 Publish Site</button>
          <hr class="user-menu-divider" />
          <button class="user-menu-item" id="bbDonateBtn" onclick="bbMenuAction('donate')" style="color:#e2557a">Donate</button>
          <button class="user-menu-item" id="bbUpgradeBtn" onclick="bbMenuAction('upgrade')" style="color:#f5a623">Subscribe</button>
          <hr class="user-menu-divider" />
          <button class="user-menu-item" id="bbMenuSignInOut" onclick="bbMenuAction('signout')">Sign In</button>
        </div>
      </div>
    </div>
  </div>
   </div>
  <?php endif; ?>

  <!-- Page banner: logo + nav, always shown (public + admin) -->
  <header class="bb-banner">
    <div class="bb-banner-left">
      <?php if ($_pubLogo || !isset($_bbPublicBase)): ?>
      <div class="bb-logo-wrap" id="bbLogoWrap"
           ondragover="bbLogoDragOver(event)" ondragleave="bbLogoDragLeave(event)" ondrop="bbLogoDrop(event)">
        <?php if ($_pubLogo): ?>
        <img src="<?= h($_pubLogo) ?>" alt="<?= h($_pubName) ?>" class="bb-logo-img" id="bbLogoImg" />
        <?php else: ?>
        <div class="bb-logo-ph" id="bbLogoImg">Your Logo</div>
        <?php endif; ?>
        <button class="bb-img-btn bb-logo-edit-btn" onclick="bbPickLogoImage(event)" title="Change logo"><?= $_imgBtnIcon ?></button>
      </div>
      <?php endif; ?>
      <?php if ($_pubName || !isset($_bbPublicBase)): ?>
      <span class="bb-banner-name<?= !isset($_bbPublicBase) ? ' bb-banner-name-editable' : '' ?>"
            <?php if (!isset($_bbPublicBase)): ?>
            contenteditable="false"
            data-setting="company"
            data-placeholder="Company Name"
            onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}"
            onblur="bbSaveFooterField(this);document.getElementById('bbMeta') && (document.getElementById('bbMeta').textContent=(this.textContent.trim()||<?= htmlspecialchars(json_encode($_pubName ?: 'Games'), ENT_QUOTES) ?>)+' · BillBoard')"
            <?php endif; ?>
            ><?= h($_pubName) ?></span>
      <?php endif; ?>
    </div>
    <nav class="bb-banner-nav" id="bbBannerNav">
      <!-- Nav links populated from Settings in a future update -->
    </nav>
    <?php if (!isset($_bbPublicBase)): ?>
    <div class="bb-banner-right">
      <button class="bb-banner-color-btn" id="bbBannerColorBtn" onclick="if(document.body.classList.contains('bb-admin-mode'))document.getElementById('bbBannerColorInput').click()" title="Banner color">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/><circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/><circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/><circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/></svg>
      </button>
      <input type="color" id="bbBannerColorInput" value="<?= h($_bannerColor) ?>"
             style="position:absolute;opacity:0;pointer-events:none;width:0;height:0"
             oninput="bbApplyBannerColor(this.value)" onchange="bbSaveBannerColor(this.value)" />
    </div>
    <?php endif; ?>
  </header>

</div><!-- /.bb-top-wrap -->

<!-- ── Carousel ── -->
<div class="cat-carousel" id="catCarousel">
<?php if (!empty($_carousel)): ?>
  <?php foreach ($_carousel as $_ci => $_cUrl): ?>
  <div class="cat-slide<?= $_ci === 0 ? ' active' : '' ?>" data-url="<?= h($_cUrl) ?>" style="background-image:url('<?= h($_cUrl) ?>')">
    <?php if (!isset($_bbPublicBase)): ?>
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
<?php if (!isset($_bbPublicBase)): ?>
<button class="cat-carousel-add-btn" id="catCarouselAddBtn" onclick="carouselAddSlide()" title="Add image">+</button>
<?php endif; ?>
</div>

<?php if ($_about): ?>
<div class="cat-about"><p class="cat-about-text"><?= h($_about) ?></p></div>
<?php endif; ?>

<!-- ── Games section ── -->
<div class="cat-section-head">
  <span class="cat-section-title">Our Games</span>
</div>

<!-- ── Game grid ── -->
<div class="cat-grid" id="catGrid">
<?php if (empty($_games)): ?>
  <div class="cat-empty">
    <div class="cat-empty-icon"><img src="<?= h($_appRoot) ?>images/bb_logo.png" alt="BillBoard" style="width:64px;height:64px;border-radius:14px;opacity:.5;" /></div>
    <div class="cat-empty-title">No published games yet</div>
    <div class="cat-empty-body">Sign in and use Fetch to sync your Games sheet, then mark games as Published to display them here.</div>
  </div>
<?php else:
  foreach ($_games as $g):
    $gName    = trim($g['Name']       ?? '');
    $gSummary = trim($g['Tagline'] ?? $g['Tag Line'] ?? $g['Summary'] ?? '');
    $gCount   = trim($g['Count']      ?? '');
    $gDur     = trim($g['Duration']   ?? '');
    $gCover   = _bb_img(trim($g['Cover URL'] ?? $g['Image URL'] ?? $g['Image'] ?? ''));
    $gSlug    = _bb_slug($gName);
    if (!$gName) continue;
?>
  <div class="cat-card-wrap">
  <a class="cat-card" href="<?= h($_catUrl . $gSlug) ?>">
    <div class="cat-img-wrap" data-game="<?= h($gName) ?>"
      ondragover="catDragOver(event,this)" ondragleave="catDragLeave(event,this)" ondrop="catDrop(event,this)">
      <?php if ($gCover): ?>
      <img class="cat-card-img" src="<?= h($gCover) ?>" alt="<?= h($gName) ?>" loading="lazy" />
      <?php else: ?>
      <div class="cat-card-ph"><img src="<?= h($_appRoot) ?>images/bb_logo.png" alt="" style="width:64px;height:64px;border-radius:14px;opacity:.35;" /></div>
      <?php endif; ?>
      <button class="bb-img-btn cat-img-upload-btn" title="Change image"
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
<?php if (!isset($_bbPublicBase)): ?>
  <div class="bb-add-game-card" id="catAddGameCard" onclick="catAddGameClick(event)">
    <div class="bb-add-game-card-inner" id="catAddGameInner">
      <span class="bb-agc-plus">+</span>
      <button class="bb-img-btn bb-agc-img-btn" title="Set cover image"
        onclick="catAddGamePickImg(event)"><?= $_imgBtnIcon ?></button>
    </div>
    <div class="bb-add-game-card-label">
      <div class="bb-agc-name" id="catAddGameName" contenteditable="true"
        onkeydown="catAddGameKey(event)"
        onfocus="catAddGameNameFocus(this)"
        onblur="catAddGameNameBlur(this)">New Game</div>
      <input class="bb-agc-tagline" id="catAddGameTagline" type="text"
        placeholder="Game description goes here."
        onkeydown="catAddGameTaglineKey(event)"
        onclick="event.stopPropagation()" />
      <span class="bb-agc-tagline-ph" id="catAddGameTaglinePh">Game description goes here.</span>
    </div>
  </div>
<?php endif; ?>
</div>

<?php _bb_footer(); ?>


<!-- ── Upgrade to Pro dialog ── -->
<script src="<?= h($_appRoot) ?>js/activationKey.js" defer></script>
<div class="bb-overlay" id="bbUpgradeOverlay" onclick="if(event.target===this)closeBbUpgradeDialog()">
  <div class="bb-dialog" onclick="event.stopPropagation()" style="max-width:420px;text-align:center">
    <button class="bb-dialog-close" onclick="closeBbUpgradeDialog()">×</button>
    <div class="bb-dialog-title bb-plan-title"><img src="<?= h($_appRoot) ?>images/bb_icon_180.png" alt="">BillBoard plans</div>
    <div class="bb-plan-current" id="bbUpgradeCurrent"></div>
    <p style="font-size:.84rem;color:var(--ink-lt,#555);line-height:1.65;margin-bottom:1rem">
      <strong style="font-size:1.05rem">BillBoard Pro — $4.99/month</strong><br>
      Pro unlocks premium features as we build them —
      advanced analytics, custom branding, priority support, and more.<br><br>
      <strong>Support development and get early access to everything new.</strong>
    </p>
    <div id="bbUpgradeSubStatus" style="margin-bottom:.8rem;font-size:.8rem;color:#888"></div>
    <div id="bbUpgradeActKey" style="margin:-.3rem 0 .9rem"></div>
    <div style="display:flex;gap:.6rem;justify-content:center">
      <button class="btn-cancel" onclick="closeBbUpgradeDialog()">Maybe Later</button>
      <button id="bbUpgradeSubBtn" class="btn-primary" onclick="startBbUpgradeCheckout()" style="background:#f5a623;border-color:#f5a623">Subscribe</button>
    </div>
  </div>
</div>

<!-- ── Analytics dialog ── -->
<div class="bb-overlay" id="bbAnalyticsOverlay" onclick="if(event.target===this)closeAnalyticsDialog()">
  <div class="bb-dialog" onclick="event.stopPropagation()" style="max-width:560px;width:94%">
    <button class="bb-dialog-close" onclick="closeAnalyticsDialog()">×</button>
    <div class="bb-dialog-title">📊 Analytics</div>
    <div id="bbAnalyticsBody" style="min-height:120px">
      <div style="text-align:center;padding:2rem 0;color:var(--ink-lt);font-size:.85rem">Loading…</div>
    </div>
  </div>
</div>

<!-- ── Unsaved-changes warning dialog ── -->
<div class="bb-overlay" id="catUnsavedOverlay" onclick="event.stopPropagation()">
  <div class="bb-dialog" onclick="event.stopPropagation()" style="max-width:400px;text-align:center">
    <div style="font-size:1.1rem;font-weight:700;color:var(--ink,#222);margin-bottom:.6rem">⚠️ Unsaved Content</div>
    <p style="font-size:.88rem;color:var(--ink-lt,#555);line-height:1.55;margin-bottom:1.4rem">You have unsaved content on this page.<br>Would you like to save first?</p>
    <div style="display:flex;gap:.75rem;justify-content:center">
      <button class="btn-adm btn-adm-secondary" onclick="catUnsavedGoBack()" style="min-width:100px">Go Back</button>
      <button class="btn-adm btn-adm-primary" id="catUnsavedSaveBtn" onclick="catUnsavedSave()" style="min-width:100px;background:var(--accent);color:#fff">Save</button>
    </div>
  </div>
</div>

<!-- ── Publish Site dialog ── -->
<div class="bb-overlay" id="bbPublishOverlay" onclick="if(event.target===this)closePublishDialog()">
  <div class="bb-dialog" onclick="event.stopPropagation()" style="max-width:480px">
    <button class="bb-dialog-close" onclick="closePublishDialog()">×</button>
    <div class="bb-dialog-title">🌐 Publish Site</div>
    <p style="font-size:.82rem;color:var(--ink-lt,#666);margin-bottom:1.2rem;line-height:1.5">
      Your public BillBoard is live. Anyone with this link can browse your published games — no sheet ID exposed.
    </p>
    <div class="bb-field-group" style="margin-bottom:.6rem">
      <label style="font-size:.75rem;font-weight:600;color:var(--ink-lt,#888);text-transform:uppercase;letter-spacing:.05em">Catalog</label>
      <div style="display:flex;gap:.4rem;align-items:center">
        <input class="bb-field" id="bbPubCatalogUrl" type="text" readonly onclick="this.select()" style="flex:1;font-size:.8rem;cursor:text" />
        <button class="bb-btn-sm" onclick="bbCopyUrl('bbPubCatalogUrl')">Copy</button>
        <a id="bbPubCatalogLink" href="#" target="_blank" class="bb-btn-sm" style="text-decoration:none">Open ↗</a>
      </div>
    </div>
    <div id="bbPubGameUrl" style="margin-bottom:1.2rem">
      <div class="bb-field-group" style="margin-bottom:.3rem">
        <label style="font-size:.75rem;font-weight:600;color:var(--ink-lt,#888);text-transform:uppercase;letter-spacing:.05em">This Game</label>
        <div style="display:flex;gap:.4rem;align-items:center">
          <input class="bb-field" id="bbPubGameUrlInput" type="text" readonly onclick="this.select()" style="flex:1;font-size:.8rem;cursor:text" />
          <button class="bb-btn-sm" onclick="bbCopyUrl('bbPubGameUrlInput')">Copy</button>
          <a id="bbPubGameUrlLink" href="#" target="_blank" class="bb-btn-sm" style="text-decoration:none">Open ↗</a>
        </div>
      </div>
    </div>
    <p style="font-size:.75rem;color:var(--ink-lt,#999);line-height:1.5">
      Re-publishing updates the same URL. Only games with status <strong>Published</strong> appear in the catalog.
    </p>
    <div style="display:flex;justify-content:flex-end;margin-top:1rem">
      <button class="bb-btn" onclick="closePublishDialog()">Done</button>
    </div>
  </div>
</div>


<!-- ── Delete game confirm dialog ── -->
<div class="bb-overlay" id="catDeleteOverlay" onclick="if(event.target===this)closeCatDeleteDialog()">
  <div class="bb-dialog" onclick="event.stopPropagation()" style="max-width:380px">
    <button class="bb-dialog-close" onclick="closeCatDeleteDialog()">&#215;</button>
    <div class="bb-dialog-title">Delete Game</div>
    <p style="font-size:.88rem;color:var(--ink-lt);margin:.2rem 0 1rem">Delete &#8220;<strong id="catDeleteGameName"></strong>&#8221;? This will permanently remove the game row and its sheet tab and cannot be undone.</p>
    <div class="bb-dialog-actions">
      <button class="btn-cancel" onclick="closeCatDeleteDialog()">Cancel</button>
      <button class="bb-btn" id="catDeleteConfirmBtn" onclick="catDeleteGameConfirmed()" style="background:#e53935;color:#fff;border-color:#e53935">Delete</button>
    </div>
  </div>
</div>

<!-- ── Sign-in dialog ── -->
<div class="bb-overlay" id="signInOverlay" onclick="if(event.target===this)forceCloseSignIn()">
  <div class="bb-dialog" onclick="event.stopPropagation()"
       onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitSignIn(false);}">
    <div class="bb-dialog-title" id="siTitle">Sign In</div>
    <div id="siMainPanel">
      <div class="bb-field-group"><label>Email</label><input class="bb-field" id="siEmail" type="email" placeholder="you@example.com" autocomplete="email" /></div>
      <div class="bb-field-group"><label>Password</label><input class="bb-field" id="siPassword" type="password" placeholder="••••••••" autocomplete="current-password" /></div>
    </div>
    <div id="siConfirmPanel">
      <p style="font-size:.85rem;color:var(--ink-lt);line-height:1.55;margin-bottom:.75rem;">No account found for this email. Create one now?</p>
    </div>
    <div class="bb-err" id="siErr"></div>
    <div class="bb-dialog-actions">
      <button class="btn-cancel" onclick="forceCloseSignIn()">Cancel</button>
      <button class="btn-primary" id="siBtn" onclick="submitSignIn(false)">Sign In</button>
    </div>
  </div>
</div>

<!-- ── Settings dialog ── -->
<div class="bbed-overlay" id="settingsOverlay" onclick="if(event.target===this)forceCloseSettings()">
  <div class="bbed-dialog" onclick="event.stopPropagation()"
       onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitSettings();}">
    <div class="bbed-header">
      <span class="bbed-title">Settings</span>
      <button class="bbed-close" onclick="forceCloseSettings()">✕</button>
    </div>
    <div class="bbed-body"><div class="bbed-scroll">

      <div class="bbed-section">
        <div class="bbed-section-title">Company</div>
        <div class="bbed-field-group"><label class="bbed-label">Company Name</label><input class="bbed-field" id="setCompany" type="text" placeholder="Acme Games LLC" /></div>
        <div class="bbed-field-group"><label class="bbed-label">Logo URL <span style="font-size:.7rem;color:var(--muted)">(image link)</span></label><input class="bbed-field" id="setLogo" type="url" placeholder="https://…/logo.png" /></div>
        <div class="bbed-field-group"><label class="bbed-label">Tagline <span style="font-size:.7rem;color:var(--muted)">(short description shown in About)</span></label><input class="bbed-field" id="setTagline" type="text" placeholder="Independent board game publisher" /></div>
        <div class="bbed-field-group"><label class="bbed-label">About</label><textarea class="bbed-field" id="setAbout" rows="3" placeholder="A short paragraph about your company or games…" style="resize:vertical"></textarea></div>
      </div>

      <div class="bbed-section">
        <div class="bbed-section-title">Images <span style="font-size:.68rem;font-weight:400;color:var(--muted)">(paste image URLs)</span></div>
        <?php for ($i = 1; $i <= 6; $i++): ?>
        <div class="bbed-field-group"><label class="bbed-label">Image <?= $i ?></label><input class="bbed-field" id="setCarousel<?= $i ?>" type="url" placeholder="https://…/image.jpg" /></div>
        <?php endfor; ?>
      </div>

      <div class="bbed-section">
        <div class="bbed-section-title">Social Links</div>
        <div class="bbed-row">
          <div class="bbed-field-group"><label class="bbed-label">Instagram</label><input class="bbed-field" id="setInsta" type="url" placeholder="https://instagram.com/…" /></div>
          <div class="bbed-field-group"><label class="bbed-label">X / Twitter</label><input class="bbed-field" id="setTwitter" type="url" placeholder="https://x.com/…" /></div>
        </div>
        <div class="bbed-row">
          <div class="bbed-field-group"><label class="bbed-label">Facebook</label><input class="bbed-field" id="setFb" type="url" placeholder="https://facebook.com/…" /></div>
          <div class="bbed-field-group"><label class="bbed-label">YouTube</label><input class="bbed-field" id="setYt" type="url" placeholder="https://youtube.com/…" /></div>
        </div>
        <div class="bbed-field-group"><label class="bbed-label">BoardGameGeek</label><input class="bbed-field" id="setBgg" type="url" placeholder="https://boardgamegeek.com/…" /></div>
      </div>

      <div class="bbed-section">
        <div class="bbed-section-title">Display</div>
        <div class="bbed-row">
          <div class="bbed-field-group">
            <label class="bbed-label">Banner Color</label>
            <input class="bbed-field" id="setBannerColor" type="color" style="height:2.4rem;padding:.2rem .3rem;cursor:pointer" />
          </div>
          <div class="bbed-field-group">
            <label class="bbed-label">BillBoard Template</label>
            <select class="bbed-field" id="setTemplate">
              <option value="">Classic (default)</option>
              <option value="opensea">OpenSea</option>
            </select>
          </div>
        </div>
        <div class="bbed-field-group"><label class="bbed-label">Copyright Line <span style="font-size:.7rem;color:var(--muted)">(footer left)</span></label><input class="bbed-field" id="setCopyright" type="text" placeholder="© 2026 Acme Games LLC" /></div>
        <div class="bbed-field-group"><label class="bbed-label">Footer Note <span style="font-size:.7rem;color:var(--muted)">(second line)</span></label><input class="bbed-field" id="setFooterNote" type="text" placeholder="contact@example.com · All rights reserved" /></div>
      </div>

      <div class="bbed-section">
        <div class="bbed-section-title">Newsletter Subscribe</div>
        <div class="bbed-field-group"><label class="bbed-label">Heading <span style="font-size:.7rem;color:var(--muted)">(defaults to tagline)</span></label><input class="bbed-field" id="setSubHeading" type="text" placeholder="Stay in the loop." /></div>
        <div class="bbed-field-group"><label class="bbed-label">Description</label><input class="bbed-field" id="setSubText" type="text" placeholder="Join our newsletter for exciting announcements and special offers!" /></div>
      </div>

    </div></div>
    <div class="bbed-footer">
      <div class="bb-err bbed-err" id="setErr"></div>
      <button class="btn-adm btn-adm-secondary" onclick="forceCloseSettings()">Cancel</button>
      <button class="btn-adm btn-adm-primary" id="setBtn" onclick="submitSettings()">Save</button>
    </div>
  </div>
</div>

<!-- ── Add Game dialog ── -->
<div class="bbed-overlay" id="addGameOverlay" onclick="if(event.target===this)forceCloseAddGame()">
  <div class="bbed-dialog" onclick="event.stopPropagation()" onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitAddGame();}">
    <div class="bbed-header">
      <span class="bbed-title">New Game</span>
      <button class="bbed-close" onclick="forceCloseAddGame()">✕</button>
    </div>
    <div class="bbed-body">
    <div class="bbed-scroll">
      <div class="bbed-section">
        <div class="bbed-section-title">Game Info</div>
        <div class="bbed-field-group">
          <label class="bbed-label">Game Name <span style="color:#c00">*</span></label>
          <input class="bbed-field" id="agName" type="text" placeholder="e.g. Lair" />
        </div>
        <div class="bbed-field-group">
          <label class="bbed-label">Tagline</label>
          <input class="bbed-field" id="agTagline" type="text" placeholder="One-line description" />
        </div>
        <div class="bbed-field-group">
          <label class="bbed-label">Description</label>
          <textarea class="bbed-field" id="agDescription" rows="3" style="resize:vertical"></textarea>
        </div>
        <div class="bbed-row">
          <div class="bbed-field-group">
            <label class="bbed-label">Status</label>
            <select class="bbed-field" id="agStatus">
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
          <div class="bbed-field-group">
            <label class="bbed-label">Availability</label>
            <input class="bbed-field" id="agAvailability" type="text" placeholder="e.g. Kickstarter" />
          </div>
        </div>
        <div class="bbed-row">
          <div class="bbed-field-group">
            <label class="bbed-label">Player Count</label>
            <input class="bbed-field" id="agCount" type="text" placeholder="e.g. 2–4" />
          </div>
          <div class="bbed-field-group">
            <label class="bbed-label">Play Time</label>
            <input class="bbed-field" id="agDuration" type="text" placeholder="e.g. 30–60 min" />
          </div>
        </div>
        <div class="bbed-row">
          <div class="bbed-field-group">
            <label class="bbed-label">Designer 1</label>
            <input class="bbed-field" id="agDesigner1" type="text" />
          </div>
          <div class="bbed-field-group">
            <label class="bbed-label">Designer 2</label>
            <input class="bbed-field" id="agDesigner2" type="text" />
          </div>
        </div>
        <div class="bbed-row">
          <div class="bbed-field-group">
            <label class="bbed-label">Designer 3</label>
            <input class="bbed-field" id="agDesigner3" type="text" />
          </div>
          <div class="bbed-field-group">
            <label class="bbed-label">Designer 4</label>
            <input class="bbed-field" id="agDesigner4" type="text" />
          </div>
        </div>
      </div>
      <div class="bbed-section">
        <div class="bbed-section-title">Dates</div>
        <div class="bbed-row">
          <div class="bbed-field-group">
            <label class="bbed-label">Date Started</label>
            <input class="bbed-field" id="agDateStarted" type="text" placeholder="e.g. Jan 2024" />
          </div>
          <div class="bbed-field-group">
            <label class="bbed-label">Date Signed</label>
            <input class="bbed-field" id="agDateSigned" type="text" placeholder="e.g. Mar 2024" />
          </div>
        </div>
        <div class="bbed-field-group" style="max-width:50%;padding-right:.3rem">
          <label class="bbed-label">Date Published</label>
          <input class="bbed-field" id="agDatePublished" type="text" placeholder="e.g. Nov 2024" />
        </div>
      </div>
      <div class="bbed-section">
        <div class="bbed-section-title">Images &amp; Media</div>
        <div class="bbed-field-group">
          <label class="bbed-label">Cover URL (background image)</label>
          <input class="bbed-field" id="agCoverUrl" type="url" placeholder="https://…" />
        </div>
        <div class="bbed-field-group">
          <label class="bbed-label">Image URL (product photo)</label>
          <input class="bbed-field" id="agImageUrl" type="url" placeholder="https://…" />
        </div>
        <div class="bbed-field-group">
          <label class="bbed-label">Video URL (YouTube)</label>
          <input class="bbed-field" id="agVideoUrl" type="url" placeholder="https://youtube.com/watch?v=…" />
        </div>
      </div>
      <div class="bbed-section">
        <div class="bbed-section-title">Links</div>
        <div class="bbed-field-group">
          <label class="bbed-label">Page URL</label>
          <input class="bbed-field" id="agPageUrl" type="url" placeholder="https://…" />
        </div>
        <div class="bbed-field-group">
          <label class="bbed-label">Sellsheet URL</label>
          <input class="bbed-field" id="agSellsheet" type="url" placeholder="https://…" />
        </div>
        <div class="bbed-field-group">
          <label class="bbed-label">Rules URL</label>
          <input class="bbed-field" id="agRulesUrl" type="url" placeholder="https://…" />
        </div>
        <div class="bbed-row">
          <div class="bbed-field-group">
            <label class="bbed-label">Play Online URL</label>
            <input class="bbed-field" id="agPlayUrl" type="url" placeholder="https://…" />
          </div>
          <div class="bbed-field-group">
            <label class="bbed-label">Print &amp; Play URL</label>
            <input class="bbed-field" id="agPrintUrl" type="url" placeholder="https://…" />
          </div>
        </div>
      </div>
      <div class="bbed-err" id="agErr"></div>
    </div>
    </div>
    <div class="bbed-footer">
      <button class="btn-adm btn-adm-secondary" style="color:var(--ink-lt);border-color:var(--border)" onclick="forceCloseAddGame()">Cancel</button>
      <button class="btn-adm btn-adm-primary" style="background:var(--accent);color:#fff" id="agBtn" onclick="submitAddGame()">Create Game</button>
    </div>
  </div>
</div>

<script>
var SHEET_ID   = <?= json_encode($_sheetId) ?>;
var APP_ROOT   = <?= json_encode($_appRoot) ?>;
var CAT_URL    = <?= json_encode($_catUrl ?? '') ?>;
var IMG_BTN_ICON = <?= json_encode($_imgBtnIcon) ?>;

// ── Auth state ──────────────────────────────────────────────────────────────
var _bbUser = null;
var _siPendingEmail = '', _siPendingPass = '';

(function() {
  try {
    var raw = sessionStorage.getItem('billboard_owner_' + SHEET_ID);
    if (raw) { _bbUser = JSON.parse(raw); }
  } catch(e) {}
  _updateBbAuth();
})();

// Restore scroll position after save redirect
(function() {
  try {
    var slug = location.pathname.replace(/\/$/, '').split('/').pop();
    var key  = 'bb_scroll_' + slug;
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

function _updateBbAuth() {
  var btn = document.getElementById('bbUserBtn');
  var metaEl = document.getElementById('bbMeta');
  var siOut = document.getElementById('bbMenuSignInOut');
  var profile = document.getElementById('bbMenuProfile');
  var publishBtn = document.getElementById('bbMenuPublish');
  if (_bbUser) {
    var name = (_bbUser.bio && (_bbUser.bio['name'] || _bbUser.bio['Name'])) || _bbUser.email || '';
    if (btn) { btn.classList.add('signed-in'); btn.title = 'Signed in as ' + (_bbUser.email || ''); }
    if (metaEl) metaEl.textContent = <?= json_encode($_pubName ?: 'Games') ?>;
    if (siOut)  { siOut.textContent = 'Sign Out'; siOut.className = 'user-menu-item signout'; }
    if (profile) profile.style.display = '';
    if (publishBtn) publishBtn.style.display = '';
    var saveBtn = document.getElementById('bbSavePageBtn');
    if (saveBtn) saveBtn.style.display = '';
    document.body.classList.add('bb-admin-mode');
    document.querySelectorAll('.bb-footer-editable, .bb-banner-name-editable').forEach(function(el){ el.contentEditable = 'true'; });
  } else {
    document.querySelectorAll('.bb-footer-editable, .bb-banner-name-editable').forEach(function(el){ el.contentEditable = 'false'; });
    var saveBtn = document.getElementById('bbSavePageBtn');
    if (saveBtn) saveBtn.style.display = 'none';
    if (btn) { btn.classList.remove('signed-in'); btn.title = 'Menu'; }
    if (metaEl) metaEl.textContent = <?= json_encode($_pubName ?: 'Games') ?>;
    if (siOut)  { var _in = (window.GBAccount && GBAccount.account()); siOut.textContent = _in ? 'Sign Out' : 'Sign In'; siOut.className = 'user-menu-item' + (_in ? ' signout' : ''); }
    if (profile) profile.style.display = 'none';
    if (publishBtn) publishBtn.style.display = 'none';
    document.body.classList.remove('bb-admin-mode');
  }
}

// ── Menu ────────────────────────────────────────────────────────────────────
function toggleBbMenu(e) { e.stopPropagation(); document.getElementById('bbMenu').classList.toggle('open'); }
function closeBbMenu() { document.getElementById('bbMenu').classList.remove('open'); }
document.addEventListener('click', closeBbMenu);

function bbMenuAction(action) {
  closeBbMenu();
  if (action === 'profile') {
    if (_bbUser) {
      var n = (_bbUser.bio && (_bbUser.bio['name'] || _bbUser.bio['Name'])) || '—';
      alert('Signed in as: ' + (_bbUser.email || '') + '\nName: ' + n);
    } else { openSignIn(); }
  } else if (action === 'settings') {
    openSettings();
  } else if (action === 'fetch') {
    if (!_bbUser) { openSignIn(); return; }
    bbDoFetch();
  } else if (action === 'publish') {
    if (!_bbUser) { openSignIn(); return; }
    bbPublishSite();
  } else if (action === 'analytics') {
    if (!_bbUser) { openSignIn(); return; }
    openAnalyticsDialog();
  } else if (action === 'upgrade') {
    openBbUpgradeDialog();
  } else if (action === 'donate') {
    startBbDonation();
  } else if (action === 'signout') {
    if (_bbUser) {
      if (confirm('Sign out?')) {
        try { sessionStorage.removeItem('billboard_owner_' + SHEET_ID); } catch(e) {}
        _bbUser = null; _updateBbAuth();
      }
    } else { openSignIn(); }
  }
}

// ── Analytics ────────────────────────────────────────────────────────────────
function openAnalyticsDialog() {
  document.getElementById('bbAnalyticsOverlay').classList.add('open');
  document.getElementById('bbAnalyticsBody').innerHTML =
    '<div style="text-align:center;padding:2rem 0;color:var(--ink-lt);font-size:.85rem">Loading…</div>';
  var fd = new FormData(); fd.append('id', SHEET_ID);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/getAnalytics.php');
  xhr.onload = function() {
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = null; }
    if (!r || r.error) {
      document.getElementById('bbAnalyticsBody').innerHTML =
        '<p style="color:#e53935;font-size:.85rem">Could not load analytics.</p>';
      return;
    }
    _renderAnalytics(r.stats, r.storage);
  };
  xhr.onerror = function() {
    document.getElementById('bbAnalyticsBody').innerHTML =
      '<p style="color:#e53935;font-size:.85rem">Network error.</p>';
  };
  xhr.send(fd);
}

function closeAnalyticsDialog() {
  document.getElementById('bbAnalyticsOverlay').classList.remove('open');
}

function _renderAnalytics(stats, storage) {
  var s = stats || {};
  var pb   = s['pitchboard'] || {};
  var sh   = s['share']      || {};
  var gm   = s['game']       || {};

  function fmt(n) { return (n || 0).toLocaleString(); }

  // ── Summary cards ────────────────────────────────────────────────────────
  var html = '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.6rem;margin-bottom:1.2rem">';
  function card(label, total, recent) {
    return '<div style="background:#f5f5fb;border-radius:8px;padding:.75rem .8rem;text-align:center">'
      + '<div style="font-family:\'DINBlack\',sans-serif;font-size:1.4rem;color:var(--accent)">' + fmt(recent) + '</div>'
      + '<div style="font-size:.68rem;color:var(--ink-lt);margin:.1rem 0 .3rem;text-transform:uppercase;letter-spacing:.05em">Last 30 days</div>'
      + '<div style="font-size:.72rem;color:#aaa">' + fmt(total) + ' all time</div>'
      + '<div style="font-size:.7rem;font-weight:600;color:var(--ink);margin-top:.35rem">' + label + '</div>'
      + '</div>';
  }
  html += card('Catalog', pb.total, pb.recent);
  html += card('Share Page', sh.total, sh.recent);
  html += card('Game Detail', gm.total, gm.recent);
  html += '</div>';

  // ── Per-game breakdown ───────────────────────────────────────────────────
  // Merge share + game visits by game name
  var byGame = {};
  function mergeGame(src) {
    var bg = (src || {}).byGame || {};
    Object.keys(bg).forEach(function(g) {
      if (!byGame[g]) byGame[g] = {total:0, recent:0};
      byGame[g].total  += bg[g].total  || 0;
      byGame[g].recent += bg[g].recent || 0;
    });
  }
  mergeGame(sh); mergeGame(gm);

  var games = Object.keys(byGame).sort(function(a,b){ return byGame[b].recent - byGame[a].recent; });
  if (games.length) {
    var maxRecent = Math.max.apply(null, games.map(function(g){ return byGame[g].recent; })) || 1;
    html += '<div style="font-family:\'DINBlack\',sans-serif;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-lt);margin-bottom:.5rem">By Game</div>';
    html += '<div style="display:flex;flex-direction:column;gap:.35rem;max-height:260px;overflow-y:auto">';
    games.forEach(function(g) {
      var d = byGame[g];
      var pct = Math.max(4, Math.round(d.recent / maxRecent * 100));
      html += '<div style="display:grid;grid-template-columns:1fr auto auto;align-items:center;gap:.5rem">'
        + '<div>'
          + '<div style="font-size:.8rem;color:var(--ink);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + _esc(g) + '</div>'
          + '<div style="height:4px;background:#e8e8f2;border-radius:2px;margin-top:3px">'
            + '<div style="height:4px;width:' + pct + '%;background:var(--accent);border-radius:2px"></div>'
          + '</div>'
        + '</div>'
        + '<div style="text-align:right;font-size:.8rem;font-weight:700;color:var(--accent);min-width:2rem">' + fmt(d.recent) + '</div>'
        + '<div style="text-align:right;font-size:.72rem;color:#aaa;min-width:3.5rem">' + fmt(d.total) + ' total</div>'
        + '</div>';
    });
    html += '</div>';
  } else {
    html += '<p style="font-size:.82rem;color:var(--ink-lt);text-align:center;padding:.5rem 0">No game visit data yet.</p>';
  }

  // ── Storage ──────────────────────────────────────────────────────────────
  if (storage) {
    var kb = (storage.bytes / 1024).toFixed(1);
    html += '<div style="margin-top:.9rem;font-size:.72rem;color:#bbb;text-align:right">'
      + storage.files + ' cached files · ' + kb + ' KB</div>';
  }

  html += '<div style="font-size:.7rem;color:#ccc;margin-top:.3rem;text-align:right">Last 30 days · 90-day history</div>';

  document.getElementById('bbAnalyticsBody').innerHTML = html;
}

function _esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Publish Site ─────────────────────────────────────────────────────────────
function bbPublishSite() {
  var userBtn = document.getElementById('bbUserBtn');
  if (userBtn) userBtn.style.opacity = '.4';
  var fd = new FormData();
  fd.append('id', SHEET_ID);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/publishBillboard.php');
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
  document.getElementById('bbPubCatalogUrl').value  = catalogUrl;
  document.getElementById('bbPubCatalogLink').href  = catalogUrl;
  // If we're on a game page, also show the game-specific URL
  var gameSlugEl = document.getElementById('bbPubGameUrl');
  if (gameSlugEl) {
    var gameName = (typeof ORIG_GAME_NAME !== 'undefined' && ORIG_GAME_NAME)
      ? ORIG_GAME_NAME.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '')
      : '';
    if (gameName) {
      var gameUrl = catalogUrl.replace(/\/?$/, '/') + gameName;
      document.getElementById('bbPubGameUrlInput').value = gameUrl;
      document.getElementById('bbPubGameUrlLink').href   = gameUrl;
      gameSlugEl.style.display = '';
    } else {
      gameSlugEl.style.display = 'none';
    }
  }
  document.getElementById('bbPublishOverlay').classList.add('open');
}
function closePublishDialog() { document.getElementById('bbPublishOverlay').classList.remove('open'); }
function bbCopyUrl(inputId) {
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
  if (!document.body.classList.contains('bb-admin-mode')) return;
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
  if (!document.body.classList.contains('bb-admin-mode')) return;
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
  if (!document.body.classList.contains('bb-admin-mode')) return;
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
function bbPickLogoImage(e) {
  e.preventDefault(); e.stopPropagation();
  if (!document.body.classList.contains('bb-admin-mode')) return;
  var inp = document.getElementById('bbLogoFileInput');
  if (!inp) {
    inp = document.createElement('input');
    inp.type = 'file'; inp.id = 'bbLogoFileInput'; inp.accept = 'image/*'; inp.style.display = 'none';
    inp.addEventListener('change', function() {
      if (inp.files && inp.files[0]) bbUploadLogo(inp.files[0]);
      inp.value = '';
    });
    document.body.appendChild(inp);
  }
  inp.click();
}
function bbLogoDragOver(e) {
  if (!document.body.classList.contains('bb-admin-mode')) return;
  e.preventDefault(); e.stopPropagation();
  var wrap = document.getElementById('bbLogoWrap');
  if (wrap) wrap.classList.add('bb-drag-over');
}
function bbLogoDragLeave(e) {
  var wrap = document.getElementById('bbLogoWrap');
  if (wrap) wrap.classList.remove('bb-drag-over');
}
function bbLogoDrop(e) {
  e.preventDefault(); e.stopPropagation();
  var wrap = document.getElementById('bbLogoWrap');
  if (wrap) wrap.classList.remove('bb-drag-over');
  if (!document.body.classList.contains('bb-admin-mode')) return;
  var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
  if (f && f.type.startsWith('image/')) bbUploadLogo(f);
}
function bbUploadLogo(file) {
  var btn = document.querySelector('.bb-logo-edit-btn');
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
      var existing = document.getElementById('bbLogoImg');
      if (existing && existing.tagName === 'IMG') {
        existing.src = r.url;
      } else if (existing) {
        var img = document.createElement('img');
        img.src = r.url; img.className = 'bb-logo-img'; img.id = 'bbLogoImg';
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
function bbSaveFooterField(el) {
  if (!document.body.classList.contains('bb-admin-mode')) return;
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
    if (!r.ok) { bbShowSaveToast('Save failed', true); return; }
    if (!r.sheetOk) {
      bbShowSaveToast('Saved locally — sheet sync failed', true);
      if (r.sheetErr) console.warn('Sheet sync error:', r.sheetErr);
    } else {
      bbShowSaveToast('Saved', false);
    }
  };
  xhr.onerror = function() { bbShowSaveToast('Save failed', true); };
  xhr.send(fd);
}
function bbShowSaveToast(msg, isErr) {
  var t = document.getElementById('bbSaveToast');
  if (!t) return;
  t.textContent = msg;
  t.style.background = isErr ? '#c0392b' : '#27ae60';
  t.classList.add('visible');
  clearTimeout(t._tid);
  t._tid = setTimeout(function(){ t.classList.remove('visible'); }, 1800);
}

// The Save button (CardBoard's style): 'saving' (striped, spinner), 'saved' (a moment), or back to Save
function bbSaveBtn(state) {
  var btn = document.getElementById('bbSavePageBtn'); if (!btn) return;
  clearTimeout(btn._t);
  btn.classList.toggle('saving', state === 'saving');
  btn.disabled = state === 'saving';
  btn.innerHTML = state === 'saving' ? '<span class="sv-fill"></span><span class="sv-txt"><span class="sv-spin"></span>Saving…</span>' : state === 'saved' ? 'Saved ✓' : 'Save';
  if (state === 'saved') btn._t = setTimeout(function() { bbSaveBtn(''); }, 1800);
}
function bbSavePage() {
  if (!document.body.classList.contains('bb-admin-mode')) return;
  // Auto-queue any name typed but not yet queued
  var nameEl = document.getElementById('catAddGameName');
  if (nameEl && nameEl.textContent.trim() && nameEl.textContent.trim() !== 'New Game') {
    catAddGameQueue();
  }
  if (_catAddGameDrafts.length > 0) {
    _catSaveDrafts(function() {
      _catAddGameDrafts = [];
      window.location.reload();
    });
  } else {
    _catSaveSettings();
  }
}

var _catUnsavedDest = null;

function _catHasUnsaved() {
  var nameEl = document.getElementById('catAddGameName');
  var cardHasName = nameEl && nameEl.textContent.trim() && nameEl.textContent.trim() !== 'New Game';
  return _catAddGameDrafts.length > 0 || !!cardHasName;
}

function catUnsavedGoBack() {
  document.getElementById('catUnsavedOverlay').classList.remove('open');
  _catUnsavedDest = null;
}

function catUnsavedSave() {
  var btn = document.getElementById('catUnsavedSaveBtn');
  if (btn) { btn.textContent = 'Saving…'; btn.disabled = true; }
  // Auto-queue any name in the card
  var nameEl = document.getElementById('catAddGameName');
  if (nameEl && nameEl.textContent.trim() && nameEl.textContent.trim() !== 'New Game') catAddGameQueue();
  _catSaveDrafts(function() {
    _catAddGameDrafts = [];
    var dest = _catUnsavedDest;
    _catUnsavedDest = null;
    window.location.href = dest || window.location.href;
  });
}

// Intercept link clicks to show our custom dialog when there are unsaved drafts
document.addEventListener('click', function(e) {
  var a = e.target.closest('a[href]');
  if (!a) return;
  var href = a.getAttribute('href');
  if (!href || href.charAt(0) === '#') return; // ignore anchors
  // Auto-queue any name typed but not yet queued
  var nameEl = document.getElementById('catAddGameName');
  if (nameEl && nameEl.textContent.trim() && nameEl.textContent.trim() !== 'New Game') catAddGameQueue();
  if (_catAddGameDrafts.length === 0) return; // nothing unsaved — let navigate
  e.preventDefault();
  _catUnsavedDest = a.href;
  document.getElementById('catUnsavedOverlay').classList.add('open');
}, true);

// Fallback for browser back/forward only — only blocks when drafts are queued
window.addEventListener('beforeunload', function(e) {
  if (_catAddGameDrafts.length > 0) { e.preventDefault(); e.returnValue = ''; }
});

// ── Social link editor ───────────────────────────────────────────────────────
var _bbSocialFieldMap = { instagram:'setInsta', twitter:'setTwitter', facebook:'setFb', youtube:'setYt', bgg_url:'setBgg' };
function bbClickSocial(btn) {
  if (!document.body.classList.contains('bb-admin-mode')) {
    var url = btn.dataset.url;
    if (url) window.open(url, '_blank', 'noopener');
    return;
  }
  // Admin: open Settings dialog focused on the matching field
  openSettings(_bbSocialFieldMap[btn.dataset.key] || null);
}

// ── Banner color ──────────────────────────────────────────────────────────────
function bbApplyBannerColor(hex) {
  document.documentElement.style.setProperty('--banner-bg', hex);
}
function bbSaveBannerColor(hex) {
  bbApplyBannerColor(hex);
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
  var urls = Array.from(_carouselSlides()).map(function(s){ return s.dataset.url || ''; }).filter(Boolean);
  var fd = new FormData(); fd.append('id', SHEET_ID);
  fd.append('carousel_json', JSON.stringify(urls));
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/tmSaveSettings.php');
  xhr.onload = function() {
    var ok = false; try { ok = JSON.parse(xhr.responseText).ok; } catch(e) {}
    bbShowSaveToast(ok ? 'Saved' : 'Save failed', !ok);
  };
  xhr.onerror = function() { bbShowSaveToast('Save failed', true); };
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
    else { bbShowSaveToast('Upload failed', true); }
  };
  xhr.onerror = function() { if (spinner) spinner.remove(); bbShowSaveToast('Upload failed', true); };
  xhr.send(fd);
}

// Replace image on an existing slide
window.carouselPickImage = function(slideEl) {
  if (!document.body.classList.contains('bb-admin-mode')) return;
  clearInterval(_carouselTimer); // stop rotation while editing
  var inp = document.createElement('input');
  inp.type = 'file'; inp.accept = 'image/*';
  inp.onchange = function() {
    if (!inp.files || !inp.files[0]) return;
    // Show spinner immediately on file selection
    _carouselAddBtnHide();
    var spinner = document.createElement('div');
    spinner.className = 'cat-slide-loading';
    slideEl.appendChild(spinner);
    _carouselUpload(inp.files[0], null, function(url) {
      spinner.remove();
      _carouselAddBtnShow();
      slideEl.dataset.url = url;
      slideEl.style.backgroundImage = "url('" + url + "')";
      carouselSaveAll();
      _carouselStart();
    });
  };
  inp.click();
};

function _carouselAddBtnHide() { var c = document.getElementById('catCarousel'); if (c) c.classList.add('is-uploading'); }
function _carouselAddBtnShow() { var c = document.getElementById('catCarousel'); if (c) c.classList.remove('is-uploading'); }

// Add a new slide
window.carouselAddSlide = function() {
  if (!document.body.classList.contains('bb-admin-mode')) return;
  var inp = document.createElement('input');
  inp.type = 'file'; inp.accept = 'image/*';
  inp.onchange = function() {
    if (!inp.files || !inp.files[0]) return;
    _carouselAddBtnHide();
    var carousel = document.getElementById('catCarousel');
    _carouselUpload(inp.files[0], carousel, function(url) { _carouselInsertSlide(url); _carouselAddBtnShow(); });
  };
  inp.click();
};
window.carouselDropAdd = function(e) {
  e.preventDefault();
  if (!document.body.classList.contains('bb-admin-mode')) return;
  var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
  if (f) {
    _carouselAddBtnHide();
    var carousel = document.getElementById('catCarousel');
    _carouselUpload(f, carousel, function(url) { _carouselInsertSlide(url); _carouselAddBtnShow(); });
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
  editBtn.innerHTML = IMG_BTN_ICON;
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
}

// Remove a slide
window.carouselRemoveSlide = function(slideEl) {
  if (!document.body.classList.contains('bb-admin-mode')) return;
  var slides = _carouselSlides();
  if (slides.length <= 1) { slideEl.remove(); _carouselRebuildDots(); carouselSaveAll(); return; }
  var idx = Array.from(slides).indexOf(slideEl);
  slideEl.remove();
  if (_carouselCur >= _carouselSlides().length) _carouselCur = _carouselSlides().length - 1;
  if (_carouselCur === idx) _carouselGo(_carouselCur);
  _carouselRebuildDots();
  _carouselStart();
  carouselSaveAll();
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
    _bbUser = { email: r.email || email, bio: r.bio || {} };
    try { sessionStorage.setItem('billboard_owner_' + SHEET_ID, JSON.stringify(_bbUser)); } catch(e) {}
    _updateBbAuth();
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
    document.getElementById('setTemplate').value    = s['Billboard Template'] || '';
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
  fd.append('bb_template',      document.getElementById('setTemplate').value);
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
  if (document.getElementById('bbUpgradeOverlay').classList.contains('open'))  { closeBbUpgradeDialog(); return; }
  if (document.getElementById('bbAnalyticsOverlay').classList.contains('open')){ closeAnalyticsDialog(); return; }
  if (document.getElementById('bbPublishOverlay').classList.contains('open')){ closePublishDialog(); return; }
  if (document.getElementById('signInOverlay').classList.contains('open'))  { forceCloseSignIn(); return; }
  if (document.getElementById('settingsOverlay').classList.contains('open')){ forceCloseSettings(); return; }
  if (document.getElementById('addGameOverlay').classList.contains('open')) { forceCloseAddGame(); return; }
  if (document.getElementById('catDeleteOverlay').classList.contains('open')) { closeCatDeleteDialog(); return; }
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
    var d = document.getElementById('addGameOverlay').querySelector('.bbed-dialog');
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
function bbSyncLog(msg, type) {
  var log  = document.getElementById('fetchLogBody');
  var line = document.createElement('span');
  line.className = 'sync-log-line ' + (type || 'info');
  line.textContent = msg;
  log.appendChild(line);
  log.scrollTop = log.scrollHeight;
}
function bbOpenFetchDialog() {
  document.getElementById('fetchLogBody').innerHTML = '';
  document.getElementById('fetchLogTitle').textContent = 'Fetching…';
  document.getElementById('fetchLogDoneBtn').disabled = true;
  document.getElementById('fetchLogOverlay').classList.add('open');
}
function bbFinishFetch(hasErrors) {
  document.getElementById('fetchLogTitle').textContent = hasErrors ? 'Fetch — errors' : 'Fetch Complete';
  document.getElementById('fetchLogDoneBtn').disabled = false;
  bbSyncLog(hasErrors ? 'Completed with errors.' : 'Done.', hasErrors ? 'error' : 'ok');
}
function bbDoFetch() {
  bbOpenFetchDialog();
  var base = APP_ROOT + 'push/setupBillboard.php';
  var hasErrors = false;

  // Step 1: setup + refresh main sheets
  bbSyncLog('Fetching sheets…', 'info');
  var fd = new FormData(); fd.append('id', SHEET_ID);
  fetch(base, { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(r) {
      if (r.error) { bbSyncLog('✗ ' + r.error, 'error'); bbFinishFetch(true); return; }
      if (r.tabs) Object.keys(r.tabs).forEach(function(t) {
        var s = r.tabs[t];
        bbSyncLog((s==='error'?'  ✗ ':'  ✓ ') + t, s==='error'?'error':(s==='created'?'ok':'skip'));
        if (s==='error') hasErrors = true;
      });
      if (r.json_refreshed) r.json_refreshed.forEach(function(t) { bbSyncLog('  ✓ ' + t, 'ok'); });
      if (r.refresh_errors) { Object.keys(r.refresh_errors).forEach(function(t) { bbSyncLog('  ✗ ' + t, 'error'); hasErrors = true; }); }

      // Step 2: publish each game tab cache individually
      var games = r.game_names || [];
      if (!games.length) { bbFinishFetch(hasErrors); return; }
      bbSyncLog('Publishing game caches…', 'info');
      var gi = 0;
      function nextGame() {
        if (gi >= games.length) { bbFinishFetch(hasErrors); return; }
        var name = games[gi++];
        var gfd = new FormData();
        gfd.append('id', SHEET_ID); gfd.append('mode', 'game'); gfd.append('name', name);
        fetch(base, { method:'POST', body:gfd })
          .then(function(r) { return r.json(); })
          .then(function(r) {
            if (r && r.ok) { bbSyncLog('  ✓ ' + name, 'ok'); }
            else {
              var msg = (r && r.error ? ': ' + r.error : '');
              if (r && r.detail) msg += ' — ' + r.detail;
              bbSyncLog('  ✗ ' + name + msg, 'error'); hasErrors = true;
            }
            nextGame();
          })
          .catch(function() { bbSyncLog('  ✗ ' + name + ': network error', 'error'); hasErrors = true; nextGame(); });
      }
      nextGame();
    })
    .catch(function() { bbSyncLog('✗ Network error', 'error'); bbFinishFetch(true); });
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
function bbSubscribeSubmit(e) {
  e.preventDefault();
  var email = (document.getElementById('bbSubscribeEmail').value || '').trim();
  if (!email) return;
  var btn = document.getElementById('bbSubscribeBtn');
  var msg = document.getElementById('bbSubscribeMsg');
  btn.disabled = true;
  btn.textContent = 'Subscribing…';
  msg.textContent = '';
  msg.className = 'bb-footer-sub-msg';
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
        msg.className = 'bb-footer-sub-msg ok';
        document.getElementById('bbSubscribeEmail').value = '';
        btn.textContent = 'Subscribed ✓';
      } else {
        msg.textContent = d.error || 'Something went wrong. Please try again.';
        msg.className = 'bb-footer-sub-msg err';
        btn.disabled = false;
        btn.textContent = 'Subscribe';
      }
    })
    .catch(function() {
      msg.textContent = 'Network error. Please try again.';
      msg.className = 'bb-footer-sub-msg err';
      btn.disabled = false;
      btn.textContent = 'Subscribe';
    });
}

// ── Inline add-game card (catalog page) ──────────────────────────────────────
var _catAddGameImgUrl = '';

function catAddGameClick(e) {
  if (e.target.closest('.bb-agc-img-btn')) return;
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
  var name = el.textContent.trim();
  if (!name) { el.textContent = 'New Game'; }
  var tl = document.getElementById('catAddGameTagline');
  if (tl) { if (name && name !== 'New Game') tl.classList.add('visible'); else tl.classList.remove('visible'); }
}

function catAddGameTaglineKey(e) {
  if (e.key === 'Enter') { e.preventDefault(); catAddGameQueue(); }
  if (e.key === 'Escape') { catAddGameReset(); }
}

function catAddGameKey(e) {
  if (e.key === 'Enter') {
    e.preventDefault();
    var tl = document.getElementById('catAddGameTagline');
    if (tl) { tl.classList.add('visible'); tl.focus(); } else { catAddGameSubmit(); }
  }
  if (e.key === 'Escape') { catAddGameReset(); }
}

function catAddGameReset() {
  var el = document.getElementById('catAddGameName');
  if (el) { el.textContent = 'New Game'; el.blur(); }
  var tl = document.getElementById('catAddGameTagline');
  if (tl) { tl.value = ''; tl.classList.remove('visible'); }
  _catAddGameImgUrl = '';
  var inner = document.getElementById('catAddGameInner');
  if (inner) {
    var prev = inner.querySelector('.bb-agc-preview'); if (prev) prev.remove();
    var plus = inner.querySelector('.bb-agc-plus'); if (plus) plus.style.display = '';
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
  var btn = inner ? inner.querySelector('.bb-agc-img-btn') : null;
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
      var prev = inner.querySelector('.bb-agc-preview'); if (prev) prev.remove();
      var plus = inner.querySelector('.bb-agc-plus'); if (plus) plus.style.display = 'none';
      var img = document.createElement('img');
      img.className = 'bb-agc-preview'; img.src = r.url;
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

var _catAddGameDrafts = []; // [{name, tagline, imgUrl}] — queued, not yet saved to sheet

function catAddGameQueue() {
  var nameEl = document.getElementById('catAddGameName');
  var name = nameEl ? nameEl.textContent.trim() : '';
  if (!name || name === 'New Game') { if (nameEl) nameEl.focus(); return; }
  var taglineEl = document.getElementById('catAddGameTagline');
  var tagline = taglineEl ? taglineEl.value.trim() : '';
  var imgUrl = _catAddGameImgUrl || '';
  _catAddGameDrafts.push({name: name, tagline: tagline, imgUrl: imgUrl});
  _catAddGameShowDraft(name, tagline, imgUrl);
  catAddGameReset();
}

function _catAddGameShowDraft(name, tagline, imgUrl) {
  var grid = document.getElementById('catGrid');
  var addCard = document.getElementById('catAddGameCard');
  if (!grid || !addCard) return;
  var wrap = document.createElement('div');
  wrap.className = 'cat-card-wrap cat-draft-wrap';
  wrap.dataset.draftName = name;
  var card = document.createElement('div');
  card.className = 'cat-card';
  var imgWrap = document.createElement('div');
  imgWrap.className = 'cat-img-wrap';
  if (imgUrl) {
    var img = document.createElement('img');
    img.className = 'cat-card-img'; img.src = imgUrl; img.alt = name;
    imgWrap.appendChild(img);
  } else {
    var ph = document.createElement('div');
    ph.className = 'cat-card-ph';
    ph.innerHTML = '<img src="' + APP_ROOT + 'images/bb_logo.png" alt="" style="width:64px;height:64px;border-radius:14px;opacity:.35;">';
    imgWrap.appendChild(ph);
  }
  var badge = document.createElement('div');
  badge.className = 'cat-draft-badge'; badge.textContent = 'Unsaved';
  imgWrap.appendChild(badge);
  card.appendChild(imgWrap);
  var body = document.createElement('div');
  body.className = 'cat-card-body';
  var nameDiv = document.createElement('div');
  nameDiv.className = 'cat-card-name'; nameDiv.textContent = name;
  body.appendChild(nameDiv);
  if (tagline) {
    var sumDiv = document.createElement('div');
    sumDiv.className = 'cat-card-summary'; sumDiv.textContent = tagline;
    body.appendChild(sumDiv);
  }
  card.appendChild(body);
  wrap.appendChild(card);
  grid.insertBefore(wrap, addCard);
}

function _catSaveDrafts(onDone) {
  var drafts = _catAddGameDrafts.slice();
  var btn = document.getElementById('bbSavePageBtn');
  bbSaveBtn('saving');
  var idx = 0;
  function saveNext() {
    if (idx >= drafts.length) { if (onDone) onDone(); return; }
    var d = drafts[idx++];
    var fd = new FormData();
    fd.append('id', SHEET_ID); fd.append('name', d.name); fd.append('tagline', d.tagline);
    fd.append('status', 'Published');
    fd.append('cover_url', d.imgUrl || '');
    fd.append('image', d.imgUrl ? '=IMAGE("' + d.imgUrl + '")' : '');
    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_ROOT + 'push/addGame.php');
    xhr.onload = function() {
      var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = null; }
      if (!r || r.error) {
        bbSaveBtn('');
        alert('Error saving "' + d.name + '": ' + ((r && r.error) || 'Unknown error'));
        return;
      }
      saveNext();
    };
    xhr.onerror = function() {
      bbSaveBtn('');
      alert('Network error saving "' + d.name + '". Please try again.');
    };
    xhr.send(fd);
  }
  saveNext();
}

function _catSaveSettings(onDone) {
  var btn = document.getElementById('bbSavePageBtn');
  bbSaveBtn('saving');
  var fd = new FormData();
  fd.append('id', SHEET_ID);
  document.querySelectorAll('.bb-footer-editable[data-setting]').forEach(function(el) {
    fd.append(el.dataset.setting, el.textContent.trim());
  });
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/tmSaveSettings.php');
  xhr.onload = function() {
    bbSaveBtn('saved');
    if (onDone) onDone();
  };
  xhr.onerror = function() {
    bbSaveBtn('');
    alert('Save failed — network error.');
  };
  xhr.send(fd);
}

function catAddGameInsertCard(name, tagline, imgUrl, slug) {
  var grid = document.getElementById('catGrid');
  var addCard = document.getElementById('catAddGameCard');
  if (!grid || !addCard) return;

  // Build wrap
  var wrap = document.createElement('div');
  wrap.className = 'cat-card-wrap';

  var a = document.createElement('a');
  a.className = 'cat-card';
  a.href = CAT_URL + (slug || name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, ''));

  var imgWrap = document.createElement('div');
  imgWrap.className = 'cat-img-wrap';
  imgWrap.dataset.game = name;
  imgWrap.setAttribute('ondragover', 'catDragOver(event,this)');
  imgWrap.setAttribute('ondragleave', 'catDragLeave(event,this)');
  imgWrap.setAttribute('ondrop', 'catDrop(event,this)');

  if (imgUrl) {
    var img = document.createElement('img');
    img.className = 'cat-card-img'; img.src = imgUrl; img.alt = name; img.loading = 'lazy';
    imgWrap.appendChild(img);
  } else {
    var ph = document.createElement('div');
    ph.className = 'cat-card-ph';
    ph.innerHTML = '<img src="' + APP_ROOT + 'images/bb_logo.png" alt="" style="width:64px;height:64px;border-radius:14px;opacity:.35;" />';
    imgWrap.appendChild(ph);
  }
  var editBtn = document.createElement('button');
  editBtn.className = 'bb-img-btn cat-img-upload-btn'; editBtn.title = 'Change image';
  editBtn.innerHTML = IMG_BTN_ICON;
  editBtn.setAttribute('onclick', "catPickImage(event,this.closest('.cat-img-wrap'))");
  imgWrap.appendChild(editBtn);
  a.appendChild(imgWrap);

  var body = document.createElement('div');
  body.className = 'cat-card-body';
  var nameDiv = document.createElement('div');
  nameDiv.className = 'cat-card-name'; nameDiv.textContent = name;
  body.appendChild(nameDiv);
  if (tagline) {
    var sumDiv = document.createElement('div');
    sumDiv.className = 'cat-card-summary'; sumDiv.textContent = tagline;
    body.appendChild(sumDiv);
  }
  a.appendChild(body);
  wrap.appendChild(a);

  var delBtn = document.createElement('button');
  delBtn.className = 'cat-card-del-btn'; delBtn.title = 'Delete game'; delBtn.textContent = '✕';
  delBtn.setAttribute('onclick', 'catDeleteGame(event,' + JSON.stringify(name) + ')');
  wrap.appendChild(delBtn);

  grid.insertBefore(wrap, addCard);

  // Update published count
  var countEl = document.querySelector('.cat-section-count');
  if (countEl) {
    var n = parseInt(countEl.textContent) || 0;
    countEl.textContent = (n + 1) + ' published';
  }
}

var _catDeleteTarget = { name: '', wrap: null };

function catDeleteGame(e, gameName) {
  e.preventDefault(); e.stopPropagation();
  _catDeleteTarget.name = gameName;
  _catDeleteTarget.wrap = e.target.closest('.cat-card-wrap');
  document.getElementById('catDeleteGameName').textContent = gameName;
  document.getElementById('catDeleteOverlay').classList.add('open');
}

function closeCatDeleteDialog() {
  document.getElementById('catDeleteOverlay').classList.remove('open');
}

function catDeleteGameConfirmed() {
  var gameName = _catDeleteTarget.name;
  var wrap = _catDeleteTarget.wrap;
  closeCatDeleteDialog();
  if (wrap) { wrap.style.opacity = '.4'; wrap.style.pointerEvents = 'none'; }
  var btn = document.getElementById('catDeleteConfirmBtn');
  if (btn) { btn.disabled = true; btn.textContent = 'Deleting…'; }
  var fd = new FormData();
  fd.append('id',   SHEET_ID);
  fd.append('game', gameName);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/deleteGame.php');
  xhr.onload = function() {
    if (btn) { btn.disabled = false; btn.textContent = 'Delete'; }
    var r; try { r = JSON.parse(xhr.responseText); } catch(ex) { r = null; }
    if (!r || r.error) {
      if (wrap) { wrap.style.opacity = ''; wrap.style.pointerEvents = ''; }
      alert('Delete failed: ' + (r && r.error || 'Unknown error'));
      return;
    }
    if (wrap) wrap.remove();
    var countEl = document.querySelector('.cat-section-count');
    if (countEl) {
      var n = parseInt(countEl.textContent) || 0;
      countEl.textContent = Math.max(0, n - 1) + ' published';
    }
  };
  xhr.onerror = function() {
    if (btn) { btn.disabled = false; btn.textContent = 'Delete'; }
    if (wrap) { wrap.style.opacity = ''; wrap.style.pointerEvents = ''; }
    alert('Network error. Please try again.');
  };
  xhr.send(fd);
}

// ── Pro subscription & donations ──────────────────────────────────────────
function _bbToast(msg) { bbShowSaveToast(msg, false); }
var _bbSubStatus = null;

function _bbReturnUrl(flag) {
  var u = new URL(window.location.href);
  u.searchParams.delete('sub');
  u.searchParams.delete('donate');
  if (flag) u.searchParams.set(flag[0], flag[1]);
  return u.toString();
}

function _bbLoadSubStatus(cb) {
  var fd = new FormData(); fd.append('id', SHEET_ID); fd.append('app', 'billboard');
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/getSubscription.php');
  xhr.onload = function() {
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = null; }
    _bbSubStatus = (r && r.ok) ? r : { ok: false };
    _bbUpdateSubMenu();
    if (cb) cb(_bbSubStatus);
  };
  xhr.onerror = function() { _bbSubStatus = { ok: false }; if (cb) cb(_bbSubStatus); };
  xhr.send(fd);
}

function _bbUpdateSubMenu() {
  var item = document.getElementById('bbUpgradeBtn');
  if (!item) return;
  var pro = !!(_bbSubStatus && _bbSubStatus.is_pro);
  item.textContent = pro ? 'Pro Active' : 'Subscribe';
  item.style.color = pro ? '#4caf50' : '#f5a623';
}

function openBbUpgradeDialog() {
  document.getElementById('bbUpgradeOverlay').classList.add('open');
  if (_bbSubStatus === null) {
    document.getElementById('bbUpgradeSubStatus').textContent = 'Checking subscription…';
    _bbLoadSubStatus(_renderBbUpgradeStatus);
  } else {
    _renderBbUpgradeStatus();
  }
}

function _renderBbUpgradeStatus() {
  _renderBbUpgradeStatusMain();
  var s = _bbSubStatus || {};
  if (s.activated) { document.getElementById('bbUpgradeSubBtn').style.display = 'none'; document.getElementById('bbUpgradeSubStatus').textContent = ''; }
  else document.getElementById('bbUpgradeSubBtn').style.display = '';
  if (window.zsActivationKey) zsActivationKey({ el: document.getElementById('bbUpgradeActKey'), base: APP_ROOT, sheetId: SHEET_ID, activated: !!s.activated,
    onActivated: function() { _bbSubStatus = null; _bbLoadSubStatus(_renderBbUpgradeStatus); } });
}
function _renderBbUpgradeStatusMain() {
  var statusEl = document.getElementById('bbUpgradeSubStatus');
  var btn      = document.getElementById('bbUpgradeSubBtn');
  var s = _bbSubStatus || {};
  btn.disabled = false;
  var cur = document.getElementById('bbUpgradeCurrent');
  if (s.ok === false)        cur.innerHTML = 'Current plan: <b>—</b> <span>(couldn’t check right now)</span>';
  else if (!_bbSubStatus)    cur.textContent = '';
  else cur.innerHTML = 'Current plan: <b>' + (s.is_pro ? 'Pro' : 'Free') + '</b>' +
         (s.is_pro && s.period_end ? ' <span>· renews ' + s.period_end.split('-').slice(1).concat(s.period_end.split('-')[0]).join('/') + '</span>' : '');
  cur.className = 'bb-plan-current' + (s.is_pro ? ' paid' : '');
  if (s.status === 'past_due') {
    statusEl.innerHTML = '<span style="color:#f5a623;font-weight:700">⚠ Payment past due</span>';
    btn.textContent = 'Update Payment'; btn.onclick = _bbOpenBillingPortal;
  } else if (s.is_pro) {
    statusEl.textContent = '';
    btn.textContent = 'Manage Billing'; btn.onclick = _bbOpenBillingPortal;
  } else {
    statusEl.textContent = (s.ok === false) ? 'Could not check your subscription right now.' : '';
    btn.textContent = 'Subscribe'; btn.onclick = startBbUpgradeCheckout;
  }
}

function _bbOpenBillingPortal() {
  var url = _bbSubStatus && _bbSubStatus.portal_url;
  if (url) { window.open(url, '_blank', 'noopener'); }
  else { alert('Online billing management is not available yet. Please contact support to change your subscription.'); }
}

function closeBbUpgradeDialog() {
  document.getElementById('bbUpgradeOverlay').classList.remove('open');
}

function startBbUpgradeCheckout() {
  _bbStartCheckout('subscription', document.getElementById('bbUpgradeSubBtn'), 'Subscribe');
}

function startBbDonation() {
  _bbStartCheckout('donation', null, '');
}

function _bbStartCheckout(type, btn, btnLabel) {
  if (btn) { btn.textContent = 'Redirecting…'; btn.disabled = true; }
  else { _bbToast('Opening secure checkout…'); }
  var fd = new FormData();
  fd.append('type', type);
  fd.append('id', SHEET_ID);
  fd.append('app', 'billboard');
  fd.append('success_url', _bbReturnUrl(type === 'donation' ? ['donate', 'thanks'] : ['sub', 'success']));
  fd.append('cancel_url',  _bbReturnUrl(null));
  function failed(msg) {
    if (btn) { btn.textContent = btnLabel; btn.disabled = false; }
    alert(msg);
  }
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/stripeCheckout.php');
  xhr.onload = function() {
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = null; }
    if (!r || r.error || !r.url) { failed(r && r.error ? r.error : 'Could not start checkout. Please try again.'); return; }
    window.location.href = r.url;
  };
  xhr.onerror = function() { failed('Network error. Please try again.'); };
  xhr.send(fd);
}

// On load: handle the return from Stripe Checkout, then load the Pro status.
(function() {
  function run() {
    var u   = new URL(window.location.href);
    var sub = u.searchParams.get('sub');
    var don = u.searchParams.get('donate');
    if (sub || don) {
      u.searchParams.delete('sub');
      u.searchParams.delete('donate');
      history.replaceState(null, '', u.pathname + u.search + u.hash);
    }
    if (don === 'thanks') { _bbToast('♥ Thank you for your donation!'); }
    if (sub !== 'success') { _bbLoadSubStatus(); return; }
    // The webhook may take a few seconds — confirm before showing Pro.
    _bbToast('Payment received — activating Pro…');
    var tries = 0;
    (function poll() {
      _bbLoadSubStatus(function(s) {
        if (s.is_pro) { _bbToast('⭐ Pro is now active. Thank you!'); return; }
        if (++tries < 10) { setTimeout(poll, 3000); return; }
        _bbToast('Payment received. Pro will activate shortly — refresh in a minute.');
      });
    })();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
})();
</script>

<!-- ── Fetch Log dialog ── -->
<div class="bbed-overlay" id="fetchLogOverlay">
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

<div class="bb-save-toast" id="bbSaveToast" style="position:fixed;bottom:1.5rem;right:1.5rem;padding:.55rem 1.1rem;border-radius:8px;font-size:.82rem;font-family:'DINBlack',sans-serif;color:#fff;box-shadow:0 3px 16px rgba(0,0,0,.22);display:none;z-index:9999;"></div>

<?php if (!isset($_bbPublicBase)): ?>
<script>
// The GameBoard account: one sign-in for every app. Editing (admin mode) is for the sheet's owner — the
// account that set it up — and follows the account: the page reloads into admin mode once it's checked.
(function() {
  var KEY = 'billboard_owner_' + SHEET_ID;
  function had() { try { return !!sessionStorage.getItem(KEY); } catch (e) { return false; } }
  GBAccount.init({
    app: 'billboard', name: 'BillBoard', sheet: SHEET_ID, sheetName: <?= json_encode(gbSheetName((string) $_sheetId)) ?>, base: APP_ROOT, home: SHEET_ID + '/billboard', gate: false,
    icon: APP_ROOT + 'images/bb_icon_180.png', plans: [{ plan: 'pro', label: '', price: '$4.99', note: 'every feature' }],
    onChange: function(s) {
      // Signed in to the account (owner of this sheet or not): Sign Out
      var so = document.getElementById('bbMenuSignInOut'); if (so) { so.textContent = s.signedIn ? 'Sign Out' : 'Sign In'; so.classList.toggle('signout', s.signedIn); }
      var up = document.getElementById('bbUpgradeBtn');
      if (up) { up.textContent = s.signedIn ? 'Subscription' : 'Subscribe'; up.style.color = s.app && /subscribed|activated|code/.test(s.app.state) ? '#4caf50' : '#f5a623'; }
      if (s.signedIn && !s.checked) return;   // wait until the account is checked for this sheet
      var was = had();
      if (s.owner) {
        try { sessionStorage.setItem(KEY, JSON.stringify({ email: s.account.email, bio: { name: s.account.name || '' } })); } catch (e) {}
        if (!was) location.reload();
      } else {
        try { sessionStorage.removeItem(KEY); } catch (e) {}
        if (was) location.reload();
      }
    }
  });
  window.openSignIn = function() { GBAccount.signIn().catch(function() {}); };
  window.openBbUpgradeDialog = function() { GBAccount.openPlans(); };
  window._bbLoadSubStatus = function(cb) { if (cb) cb({}); };
  var menu = window.bbMenuAction;
  window.bbMenuAction = function(action) {
    if (action === 'signout') { if (typeof closeBbMenu === 'function') closeBbMenu(); if (GBAccount.account()) GBAccount.signOut(); else openSignIn(); return; }
    if (action === 'upgrade') { if (typeof closeBbMenu === 'function') closeBbMenu(); GBAccount.openPlans(); return; }
    menu(action);
  };
})();
</script>
<?php endif; ?>
</body>
</html>
<?php
    exit;
}
// ── END CATALOG ──────────────────────────────────────────────────────────────

$_game = null;
foreach ($_allGames as $g) {
    if (_bb_slug($g['Name'] ?? '') === _bb_slug($_gameSlug)) { $_game = $g; break; }
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
$_coverUrl    = _bb_img(_bb_link(trim($_game['Cover URL']    ?? $_game['Image'] ?? '')));
$_imageUrl    = _bb_img(_bb_link(trim($_game['Image URL']    ?? $_game['Image'] ?? '')));
$_videoUrl    = _bb_link(trim($_game['Video URL'] ?? $_game['Video'] ?? ''));
$_pageUrl     = _bb_link(trim($_game['Page URL']       ?? ''));
$_sellsheet   = _bb_link(trim($_game['Sellsheet URL']  ?? ''));
$_playUrl     = _bb_link(trim($_game['Play URL']       ?? ''));
$_printUrl    = _bb_link(trim($_game['Print URL']      ?? ''));
$_rulesUrl    = _bb_link(trim($_game['Rules URL']      ?? ''));
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
//   1. game-{name}-en.json in the current sheet dir  (BillBoard [GameName] tab)
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
    $_rich = _bb_load($_richFile);
    if (!empty($_rich)) break;
}

// Helper: get all rows of a given Name
function _bb_rows(array $rich, string $name): array {
    return array_values(array_filter($rich, fn($r) => strcasecmp(trim($r['Name'] ?? ''), $name) === 0));
}

// Helper: first Value for a given Name
function _bb_val(array $rich, string $name): string {
    foreach ($rich as $r) { if (strcasecmp(trim($r['Name'] ?? ''), $name) === 0) return trim($r['Value'] ?? ''); }
    return '';
}

$_components  = array_values(array_filter(_bb_rows($_rich, 'Component'), fn($r) => trim($r['Value'] ?? '') !== ''));
$_reviews     = array_values(array_filter(_bb_rows($_rich, 'Review'),    fn($r) => trim($r['Value'] ?? '') !== ''));
$_faqs        = array_values(array_filter(_bb_rows($_rich, 'FAQ'),       fn($r) => trim($r['Value'] ?? '') !== ''));
$_buyUrls        = _bb_rows($_rich, 'BuyUrl');
$_resourceLinks  = array_values(array_filter(_bb_rows($_rich, 'ResourceLink'), fn($r) => trim($r['Value'] ?? '') !== '' || trim($r['Value 1'] ?? '') !== ''));
$_hasRichData    = !empty($_rich);
// Video rows in [GameName] tab = video reviews (col B = URL, col C = name/label)
// Main video comes from Games sheet Video column only ($videoUrl below)
$_videoReviews = array_values(array_filter(_bb_rows($_rich, 'Video'), fn($r) => trim($r['Value'] ?? '') !== ''));
$_mediaImgs      = array_merge(_bb_rows($_rich, 'Media'), _bb_rows($_rich, 'ProductImage'));
$_productImgUrls = array_values(array_filter(array_map(fn($r) => _bb_img(trim($r['Value'] ?? '')), _bb_rows($_rich, 'ProductImage')), fn($u) => $u !== ''));
$_headerImageUrl = _bb_img(_bb_link(_bb_val($_rich, 'HeaderImage')));
$_richPrice   = _bb_val($_rich, 'Price');
$_richRules   = _bb_link(_bb_val($_rich, 'Rules PDF Url')) ?: $_rulesUrl;

// ── Stats from [GameName] tab rows ───────────────────────────────────────────
$_minPlayers  = _bb_val($_rich, 'MinPlayers');
$_maxPlayers  = _bb_val($_rich, 'MaxPlayers');
$_minPlaytime = _bb_val($_rich, 'MinPlaytime');
$_maxPlaytime = _bb_val($_rich, 'MaxPlaytime');
$_weight      = _bb_val($_rich, 'Weight');
$_mechanic    = _bb_val($_rich, 'Mechanic') ?: _bb_val($_rich, 'Main Mechanic');
$_ages        = _bb_val($_rich, 'Ages')     ?: _bb_val($_rich, 'Age');
$_series      = _bb_val($_rich, 'Series');
$_boxSize     = _bb_val($_rich, 'Box Size');
// Artists from [GameName] tab rows (multiple rows all named 'Artist')
foreach (_bb_rows($_rich, 'Artist') as $r) {
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
$_videoEmbed = _bb_embed($_videoUrl);

// Direct (non-embeddable) video file URL — mp4/webm/ogg/mov uploads
$_videoDirect = '';
if (!$_videoEmbed && $_videoUrl) {
    $ext = strtolower(pathinfo(parse_url($_videoUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
    if (in_array($ext, ['mp4','webm','ogg','mov'], true)) $_videoDirect = $_videoUrl;
}

// Other Published games for "More Games" section
$_otherGames = [];
foreach ($_allGames as $g) {
    if (strtolower(trim($g['Status'] ?? '')) === 'published' && _bb_slug($g['Name'] ?? '') !== _bb_slug($_name)) {
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
<?php require_once dirname(__DIR__, 2) . '/push/linkPreview.php'; echo gbLinkPreview('billboard', $_sheetId, array_filter(['title' => $_name . ($_summary ? ' — ' . $_summary : ''), 'description' => mb_substr(trim(strip_tags($_description ?: $_summary)), 0, 200) ?: null, 'image' => preg_match('#^https?://#', (string) $_heroImg) ? $_heroImg : null])); ?>
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
.bb-nav-logo { color:#00e5ff; }
.bb-hero-title { color:#fff; }
.bb-status-badge { border-color:rgba(0,229,255,.4); color:rgba(255,255,255,.9); }
.btn-nav.filled { background:#00e5ff; color:#0d1b2a; border-color:#00e5ff; }
.btn-nav.filled:hover { background:#b2fff9; }
.bb-section-label { color:#0097a7; border-color:#c8dcea; }
.bb-comp-qty { color:#0097a7; }
.bb-review-quote::before { color:#0097a7; }
.bb-faq-item { border-color:#c8dcea; }
.bb-vtab.active, .bb-vtab:hover { background:#0097a7; border-color:#0097a7; }
.btn-buy { background:#0097a7; }
.btn-buy:hover { background:#00838f; }
.btn-link { border-color:#0097a7; color:#0097a7; }
.btn-link:hover { background:#0097a7; color:#fff; }
.bb-more-card:hover { box-shadow:0 4px 18px rgba(0,151,167,.18); }
.bb-more-name { color:#0097a7; }
<?php endif; ?>
body { background:var(--bg); font-family:'DINRegular',Arial,sans-serif; color:var(--ink); min-height:100vh; }
a { color:inherit; text-decoration:none; }

/* ── Nav ── */
.bb-nav {
  background:var(--nav-bg); color:#fff;
  display:flex; align-items:center; justify-content:space-between;
  padding:.7rem 1.5rem; position:sticky; top:0; z-index:100;
  gap:1rem;
}
.bb-nav-left { display:flex; align-items:baseline; gap:.5rem; min-width:0; }
.bb-nav-back { display:inline-flex; align-items:center; gap:.25rem; color:rgba(255,255,255,.75); font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; text-decoration:none; flex-shrink:0; padding:.2rem .4rem; border-radius:5px; transition:color .12s, background .12s; line-height:1; }
.bb-nav-back:hover { color:#fff; background:rgba(255,255,255,.12); }
.bb-nav-back svg { flex-shrink:0; vertical-align:middle; position:relative; top:2.5px; }
.bb-nav-back-label { white-space:nowrap; vertical-align:middle; }
.bb-nav-sep { color:rgba(255,255,255,.25); font-size:.85rem; flex-shrink:0; line-height:1; }
.bb-nav-game { font-size:.78rem; opacity:.55; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; line-height:1; }
.bb-nav-links { display:flex; gap:.4rem; flex-shrink:0; }
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
.bb-hero {
  position:relative; min-height:70vh; display:flex; align-items:flex-end;
  overflow:hidden; background:var(--ink);
}
.bb-hero-bg { position:absolute; inset:0; background-size:cover; background-position:center; }
.bb-hero-overlay {
  position:absolute; inset:0;
  background:var(--hero-gradient);
}
.bb-hero-content { position:relative; z-index:5; max-width:860px; padding:2.5rem 2rem 3.5rem; }
.bb-status-badge {
  display:inline-block; font-family:'DINBlack',sans-serif; font-size:.66rem;
  text-transform:uppercase; letter-spacing:.1em; padding:.22rem .65rem;
  border-radius:999px; border:1.5px solid rgba(255,255,255,.38); color:rgba(255,255,255,.8);
  margin-bottom:.8rem;
}
.bb-hero-title {
  font-family:'DINBlack',sans-serif; font-size:clamp(2rem,7vw,3.8rem);
  line-height:1.04; letter-spacing:.02em; color:#fff;
  text-shadow:0 2px 20px rgba(0,0,0,.55); margin-bottom:.65rem;
}
.bb-hero-summary {
  font-size:clamp(.92rem,2.5vw,1.12rem); color:rgba(255,255,255,.82);
  max-width:560px; line-height:1.55; margin-bottom:1.3rem;
}
.bb-stats {
  display:flex; flex-wrap:wrap; gap:.45rem 1.3rem; margin-bottom:1.5rem;
}
.bb-stat { font-size:.82rem; color:rgba(255,255,255,.75); }
.bb-stat strong { font-family:'DINBlack',sans-serif; color:#fff; margin-right:.25rem; }
.bb-hero-ctas { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; margin-top:.75rem; }
.bb-hero-ctas .btn-nav { display:inline-flex; align-items:center; height:2.75rem; box-sizing:border-box; padding:0 1rem; }
.bb-price-tag {
  display:inline-block; vertical-align:middle;
  height:2.75rem; box-sizing:border-box; padding:0 .85rem;
  /* slightly above center */
  line-height:calc(2.75rem - 14px);
  border:2px solid rgba(255,255,255,.35); border-radius:6px;
  font-family:'DINBlack',sans-serif; font-size:1.4rem; color:#fff;
  letter-spacing:-.01em;
}
#bbEditPrice { display:inline; line-height:inherit; }

/* ── Main ── */
.bb-main { max-width:940px; margin:0 auto; padding:3rem 1.25rem 5rem; }
.bb-section { margin-bottom:3.2rem; }
.bb-section-label {
  font-family:'DINBlack',sans-serif; font-size:.7rem; text-transform:uppercase;
  letter-spacing:.1em; color:var(--muted); margin-bottom:.85rem;
  padding-bottom:.45rem; border-bottom:1px solid var(--border);
}
.bb-description { font-size:.98rem; line-height:1.75; color:var(--ink-lt); white-space:pre-wrap; }

/* ── Gallery ── */
.bb-gallery-viewer { display:flex; flex-direction:column; gap:.55rem; }
.bb-gallery-featured { position:relative; border-radius:10px; overflow:hidden; aspect-ratio:4/3; }
.bb-gallery-featured img { width:100%; height:100%; object-fit:cover; object-position:center; display:block; transition:opacity .35s; }
.bb-gallery-featured img.bb-gf-fade { opacity:0; }
/* admin overlay on featured */
.bb-gallery-featured-del { position:absolute; top:.5rem; right:.5rem; background:rgba(0,0,0,.55); color:#fff; border:none; border-radius:50%; width:24px; height:24px; font-size:.7rem; cursor:pointer; display:none; align-items:center; justify-content:center; z-index:3; }
body.bb-admin-mode .bb-gallery-featured-del { display:flex; }
/* uploading spinner on main featured area */
.bb-gallery-featured.bb-uploading::before { content:''; position:absolute; inset:0; background:rgba(0,0,0,.35); z-index:4; border-radius:10px; }
.bb-gallery-featured.bb-uploading::after,
.bb-gallery-viewer.bb-gallery-empty .bb-gallery-featured.bb-uploading::after {
  content:''; position:absolute; inset:0; margin:auto; width:36px; height:36px;
  border:4px solid rgba(255,255,255,.25); border-top-color:#fff; border-radius:50%;
  animation:bb-spin .7s linear infinite; z-index:5; font-size:0;
}
/* Thumbs row */
.bb-gallery-thumbs { display:flex; flex-direction:row; gap:.45rem; overflow-x:auto; padding-bottom:2px; }
.bb-gallery-thumbs::-webkit-scrollbar { height:3px; }
.bb-gallery-thumbs::-webkit-scrollbar-thumb { background:var(--border); border-radius:2px; }
.bb-gallery-thumb { position:relative; width:100px; aspect-ratio:4/3; border-radius:7px; overflow:hidden; flex-shrink:0; cursor:pointer; border:2px solid transparent; transition:border-color .18s,opacity .15s; background:#eee; }
.bb-gallery-thumb img { width:100%; height:100%; object-fit:cover; object-position:center; display:block; pointer-events:none; }
.bb-gallery-thumb.bb-gt-active { border-color:var(--accent); }
.bb-gallery-thumb:hover:not(.bb-gt-active) { border-color:rgba(0,0,0,.2); }
/* drag-to-reorder */
body.bb-admin-mode .bb-gallery-thumb { cursor:grab; }
.bb-gallery-thumb.bb-gt-dragging { opacity:.35; cursor:grabbing; }
.bb-gallery-thumb.bb-gt-drag-over { border-color:var(--accent); box-shadow:0 0 0 2px var(--accent); }
/* thumb delete btn */
.bb-gt-del { position:absolute; top:2px; right:2px; background:rgba(0,0,0,.6); color:#fff; border:none; border-radius:50%; width:18px; height:18px; font-size:.55rem; cursor:pointer; display:none; align-items:center; justify-content:center; z-index:2; line-height:1; }
body.bb-admin-mode .bb-gt-del { display:flex; }
/* "+" add thumb */
.bb-gallery-thumb-add { width:100px; aspect-ratio:4/3; border-radius:7px; flex-shrink:0; cursor:pointer; border:2px dashed rgba(59,59,170,.25); background:linear-gradient(135deg,#f0f0fa,#e4e4f4); display:flex; align-items:center; justify-content:center; font-size:1.6rem; color:rgba(59,59,170,.35); position:relative; transition:opacity .18s; opacity:0; pointer-events:none; }
body.bb-admin-mode .bb-gallery-thumb-add { opacity:1; pointer-events:auto; }
.bb-gallery-thumb-add:hover { opacity:.7 !important; }
.bb-gallery-thumb-add.bb-drag-over { opacity:1 !important; border-color:var(--accent); background:#e8eaf8; }
.bb-gallery-thumb-add.bb-uploading { opacity:1 !important; border-color:var(--accent); pointer-events:none; }
.bb-gallery-thumb-add.bb-uploading > span { visibility:hidden; }
.bb-gallery-thumb-add.bb-uploading::after { content:''; position:absolute; inset:0; margin:auto; width:22px; height:22px; border:3px solid rgba(59,59,170,.2); border-top-color:var(--accent); border-radius:50%; animation:bb-spin .7s linear infinite; }
@keyframes bb-spin { to { transform:rotate(360deg); } }
/* Gallery empty state (admin only) */
body.bb-admin-mode .bb-gallery-viewer.bb-gallery-empty .bb-gallery-featured {
  display:flex; align-items:center; justify-content:center;
  border:2px dashed rgba(59,59,170,.25); background:linear-gradient(135deg,#f0f0fa,#e4e4f4);
  cursor:pointer;
}
body.bb-admin-mode .bb-gallery-viewer.bb-gallery-empty .bb-gallery-featured::after {
  content:'+'; font-size:3.5rem; color:rgba(59,59,170,.3); line-height:1; pointer-events:none;
}
body.bb-admin-mode .bb-gallery-viewer.bb-gallery-empty .bb-gallery-featured-del { display:none !important; }
body.bb-admin-mode .bb-gallery-viewer.bb-gallery-empty .bb-gallery-featured img { display:none !important; }

/* ── Video ── */
.bb-video-wrap { position:relative; padding-top:56.25%; border-radius:10px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.13); }
.bb-video-wrap iframe { position:absolute; inset:0; width:100%; height:100%; border:none; }
.bb-video-wrap video { position:absolute; inset:0; width:100%; height:100%; object-fit:contain; background:#000; }
/* Video admin remove button (admin only) */
.bb-video-del-btn { display:none; position:absolute; top:.4rem; right:.4rem; z-index:10; background:rgba(0,0,0,.55); color:#fff; border:none; border-radius:50%; width:26px; height:26px; font-size:.75rem; line-height:26px; text-align:center; cursor:pointer; padding:0; transition:background .15s; }
body.bb-admin-mode .bb-video-del-btn { display:block; }
.bb-video-del-btn:hover { background:rgba(0,0,0,.82); }
/* Video placeholder (admin only) */
.bb-video-ph-inner { background:linear-gradient(135deg,#1a1a2e,#2d2d88); border-radius:10px; aspect-ratio:16/9; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:.5rem; opacity:.3; cursor:pointer; transition:opacity .2s; }
.bb-video-ph-inner:hover { opacity:.5; }
.bb-video-ph-inner.drag-over { opacity:.8; outline:2px dashed #fff; }
.bb-video-ph-inner.url-entry { opacity:1; cursor:default; gap:.75rem; padding:1.5rem; }
.bb-video-ph-url-wrap { display:flex; gap:.5rem; width:min(420px,100%); }
.bb-video-ph-url-wrap input { flex:1; padding:.55rem .8rem; border-radius:6px; border:none; font-size:.85rem; outline:none; }
.bb-video-ph-url-wrap button { padding:.55rem .9rem; border-radius:6px; border:none; background:#4ade80; color:#0f172a; font-weight:700; font-size:.8rem; cursor:pointer; white-space:nowrap; }
.bb-video-ph-url-wrap button:hover { background:#22c55e; }
.bb-video-ph-cancel { font-size:.65rem; color:#94a3b8; cursor:pointer; text-transform:uppercase; letter-spacing:.08em; }
.bb-video-ph-cancel:hover { color:#fff; }
.bb-video-tabs { display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.7rem; }
.bb-vtab {
  font-family:'DINBlack',sans-serif; font-size:.68rem; text-transform:uppercase;
  letter-spacing:.06em; padding:.28rem .65rem; border-radius:4px; cursor:pointer;
  border:1px solid var(--border); background:var(--surface); color:var(--ink-lt);
  transition:background .12s;
}
.bb-vtab:hover, .bb-vtab.active { background:var(--accent); color:#fff; border-color:var(--accent); }

/* ── Inline section edit textareas (admin mode) ── */
.bb-inline-edit {
  display:none; width:100%; min-height:110px; padding:.6rem .8rem;
  border:1.5px dashed var(--border); border-radius:8px;
  font-family:inherit; font-size:.88rem; line-height:1.6;
  resize:vertical; box-sizing:border-box;
  background:var(--surface); color:var(--ink);
}
body.bb-admin-mode .bb-inline-edit { display:block; }
body.bb-admin-mode .bb-display-content { display:none; }
/* Hide empty sections from public view */
body:not(.bb-admin-mode) .bb-sect-empty { display:none; }
.bb-sect-hidden { display:none; }
.bb-inline-hint { display:none; font-size:.73rem; color:var(--muted); margin:.3rem 0 0; font-style:italic; }
body.bb-admin-mode .bb-inline-hint { display:block; }

/* ── FAQ editor (dynamic Q+A pairs) ── */
.bb-faq-editor { display:none; flex-direction:column; gap:.6rem; min-height:0; padding:0; border:none; background:none; resize:none; }
body.bb-admin-mode .bb-faq-editor { display:flex; }
.bb-faq-pair { border:1.5px dashed var(--border); border-radius:8px; overflow:hidden; }
.faq-ans-wrap { position:relative; }
.faq-img-btn { position:absolute; bottom:.35rem; right:.35rem; width:1.55rem; height:1.55rem; background:rgba(255,255,255,.88); border:1px solid #d0d8e4; border-radius:5px; cursor:pointer; color:#99a; display:flex; align-items:center; justify-content:center; z-index:2; padding:0; transition:color .15s,border-color .15s; }
.faq-img-btn:hover { color:var(--accent); border-color:#a0b8c8; }
.faq-img-preview { position:relative; }
.faq-img-preview img { width:100%; display:block; border-radius:0 0 6px 6px; }
.faq-img-trash { position:absolute; bottom:.3rem; right:.3rem; width:1.55rem; height:1.55rem; background:rgba(255,255,255,.92); border:1px solid #e8c0c0; border-radius:5px; cursor:pointer; color:#c0392b; display:flex; align-items:center; justify-content:center; z-index:3; padding:0; }
.faq-img-trash:hover { background:#fff0f0; border-color:#c0392b; }
.bb-faq-pair textarea {
  display:block; width:100%; box-sizing:border-box; border:none; outline:none;
  font-family:inherit; font-size:.88rem; line-height:1.6; resize:none; overflow:hidden;
  background:var(--surface); color:var(--ink); padding:.55rem .8rem;
}
.bb-faq-pair textarea.faq-q { font-weight:700; border-bottom:1px solid var(--border); }
.bb-faq-pair textarea.faq-a { color:var(--ink-lt); }

/* ── Components ── */
.bb-components { display:grid; grid-template-columns:repeat(auto-fill, minmax(195px,1fr)); gap:.5rem; }
.bb-comp-item {
  display:flex; align-items:baseline; gap:.55rem;
  background:var(--surface); border:1px solid var(--border);
  border-radius:7px; padding:.52rem .7rem; font-size:.86rem;
}
.bb-comp-qty { font-family:'DINBlack',sans-serif; font-size:1rem; color:var(--accent); flex-shrink:0; min-width:1.5rem; }
.bb-comp-name { color:var(--ink-lt); }

/* ── Review Ticker ── */
.bb-ticker-wrap { background:#f5a623; overflow:hidden; padding:0; }
.bb-ticker-hidden { display:none; }
.bb-ticker-empty { min-height:80px; display:flex; align-items:center; justify-content:center; }
.bb-ticker-track { display:flex; will-change:transform; }
.bb-ticker-item { flex-shrink:0; width:480px; padding:1.6rem 2.5rem; text-align:center; box-sizing:border-box; }
.bb-ticker-quote { font-family:'DINBlack',sans-serif; font-size:1.05rem; color:#fff; font-style:italic; line-height:1.45; }
.bb-ticker-author { margin-top:.5rem; font-size:.7rem; color:rgba(255,255,255,.75); text-transform:uppercase; letter-spacing:.07em; }
.bb-ticker-dot { flex-shrink:0; display:flex; align-items:center; color:rgba(255,255,255,.3); font-size:1.4rem; padding:0 .25rem; align-self:center; }
/* Admin ticker edit card */
.bb-ticker-item-edit { position:relative; cursor:pointer; transition:background .15s; }
.bb-ticker-item-edit:hover { background:rgba(0,0,0,.12); border-radius:6px; }
.bb-ticker-item-edit.bb-ticker-active { background:rgba(0,0,0,.2); border-radius:6px; }
.bb-ticker-item-ph { border:2px dashed rgba(255,255,255,.35); border-radius:8px; opacity:.55; }
.bb-ticker-item-ph:hover { opacity:.9; }
/* Inline edit card (replaces card content in place) */
.bb-ticker-item-editing { width:480px; background:rgba(0,0,0,.22); border-radius:8px; padding:1rem 1.2rem; display:flex; flex-direction:column; gap:.55rem; cursor:default; }
.bb-ticker-edit-ta  { font-family:inherit; font-size:.95rem; padding:.5rem .7rem; border:none; border-radius:6px; resize:none; outline:none; width:100%; box-sizing:border-box; line-height:1.5; background:#fff; color:#1a3a5c; }
.bb-ticker-edit-inp { font-family:inherit; font-size:.8rem; padding:.4rem .7rem; border:none; border-radius:6px; outline:none; width:100%; box-sizing:border-box; background:rgba(255,255,255,.85); color:#1a3a5c; }
.bb-ticker-edit-ta:focus, .bb-ticker-edit-inp:focus { box-shadow:0 0 0 2px rgba(255,255,255,.7); }
.bb-ticker-edit-actions { display:flex; gap:.4rem; flex-wrap:wrap; }
.btn-adm-danger { background:#e53e3e !important; color:#fff !important; }

/* ── Reviews ── */
.bb-reviews { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px,1fr)); gap:1rem; }
.bb-review {
  background:var(--surface); border:1px solid var(--border); border-radius:10px;
  padding:1rem 1.1rem; position:relative;
}
.bb-review-quote { font-size:.9rem; line-height:1.6; color:var(--ink-lt); font-style:italic; margin-bottom:.6rem; }
.bb-review-quote::before { content:'\201C'; font-size:1.8rem; color:var(--accent); line-height:0; vertical-align:-.45em; margin-right:.1rem; }
.bb-review-author { font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); }
/* ── Video Reviews ── */
.bb-vreviews { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px,1fr)); gap:1.2rem; }
.bb-vreview { background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 2px 12px rgba(0,0,0,.08); }
.bb-vreview-wrap { position:relative; padding-top:56.25%; }
.bb-vreview-wrap iframe { position:absolute; inset:0; width:100%; height:100%; border:none; }
.bb-vreview-wrap video { position:absolute; inset:0; width:100%; height:100%; object-fit:contain; background:#000; }
.bb-vreview-label { font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); padding:.5rem .75rem .6rem; }
/* ── Video Review tile editor (admin) ── */
.bb-vrev-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:.75rem; min-height:unset; padding:0; }
body.bb-admin-mode .bb-vrev-grid { display:grid; }
.bb-vrev-tile { position:relative; border-radius:8px; overflow:hidden; aspect-ratio:16/9; cursor:pointer; background:#111; box-shadow:0 2px 8px rgba(0,0,0,.18); }
.bb-vrev-tile img { width:100%; height:100%; object-fit:cover; display:block; }
.bb-vrev-tile-label { position:absolute; bottom:0; left:0; right:0; background:rgba(0,0,0,.6); color:#fff; font-family:'DINBlack',sans-serif; font-size:.6rem; text-transform:uppercase; letter-spacing:.06em; padding:.3rem .4rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.bb-vrev-tile-del { position:absolute; top:.25rem; right:.25rem; background:rgba(0,0,0,.55); color:#fff; border:none; border-radius:50%; width:18px; height:18px; font-size:.65rem; line-height:18px; text-align:center; cursor:pointer; display:none; padding:0; }
.bb-vrev-tile:hover .bb-vrev-tile-del { display:block; }
.bb-vrev-empty { border:2px dashed #aac; border-radius:8px; aspect-ratio:16/9; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:.3rem; cursor:pointer; opacity:.55; transition:opacity .15s; }
.bb-vrev-empty:hover { opacity:1; }
.bb-vrev-add-icon { font-size:1.4rem; color:#88a; }
.bb-vrev-add-label { font-family:'DINBlack',sans-serif; font-size:.6rem; text-transform:uppercase; letter-spacing:.08em; color:#88a; }
/* inline editing inside the tile */
.bb-vrev-editing { aspect-ratio:unset !important; min-height:128px; background:#f5f7fa !important; display:flex !important; flex-direction:column; align-items:stretch; justify-content:flex-start; padding:.65rem; gap:.4rem; cursor:default; opacity:1 !important; }
.bb-vrev-editing .bb-vrev-inp { width:100%; box-sizing:border-box; padding:.38rem .55rem; border:1px solid #dde; border-radius:5px; font-size:.78rem; outline:none; background:#fff; color:#1e293b; }
.bb-vrev-editing .bb-vrev-inp:focus { border-color:#7c9eff; }
.bb-vrev-editing .bb-vrev-inline-btns { display:flex; gap:.4rem; margin-top:.1rem; }
.bb-vrev-editing .bb-vrev-btn-save { padding:.32rem .7rem; border-radius:5px; border:none; background:#4ade80; color:#0f172a; font-size:.72rem; font-weight:700; cursor:pointer; }
.bb-vrev-editing .bb-vrev-btn-cancel { padding:.32rem .7rem; border-radius:5px; border:none; background:#e2e8f0; color:#334155; font-size:.72rem; font-weight:700; cursor:pointer; }

/* ── Edit Details: image row upload button ── */
.bbed-img-row { display:flex; gap:.4rem; align-items:center; }
.bbed-img-row .bbed-field { flex:1; min-width:0; }
.bbed-upload-btn { flex-shrink:0; padding:.3rem .5rem; border:1px solid var(--border); border-radius:5px; background:#f8f9fa; cursor:pointer; font-size:.9rem; line-height:1; transition:background .13s; }
.bbed-upload-btn:hover { background:#e8eaed; }
.bbed-upload-btn.bbed-uploading { opacity:.5; cursor:wait; }

/* ── Price tag: in-place editing (admin) ── */
.bb-cta-ph { opacity:.45; font-style:italic; }
body.bb-admin-mode #bbPriceBox { cursor:text; border-style:dashed; }
body.bb-admin-mode #bbEditPrice { outline:none; min-width:3rem; }
body.bb-admin-mode #bbPriceBox.bb-price-ph #bbEditPrice::before { content:attr(data-ph); opacity:.4; pointer-events:none; display:inline; }
body.bb-admin-mode #bbPriceBox:focus-within,
body.bb-admin-mode #bbPriceBox:hover { outline:2px solid rgba(255,255,255,.75); border-style:dashed; }
/* ── Buy links: in-place editing (admin) ── */
body.bb-admin-mode .bb-buy-pub { display:none; }
body.bb-admin-mode #bbBuyCtas { display:contents; }
.bb-buy-cta-wrap { position:relative; display:inline-flex; align-items:center; }
.bb-buy-cta-del { position:absolute; top:-5px; right:-5px; width:16px; height:16px; border-radius:50%; background:rgba(0,0,0,.6); color:#fff; border:none; font-size:.55rem; line-height:16px; text-align:center; cursor:pointer; padding:0; display:none; z-index:2; }
.bb-buy-cta-wrap:hover .bb-buy-cta-del { display:block; }
/* Inline-editable button: label + link icon */
.bb-buy-cta-inner { display:inline-flex; align-items:center; gap:.35rem; height:2.75rem; padding:0 .85rem; border:2px solid currentColor; border-radius:6px; font-family:'DINBlack',sans-serif; font-size:.72rem; font-weight:900; letter-spacing:.07em; text-transform:uppercase; cursor:default; white-space:nowrap; box-sizing:border-box; }
.bb-buy-cta-inner.filled { background:#fff; color:#1a3a5c; }
a.bb-buy-cta-inner { text-decoration:none; cursor:pointer; }
.bb-buy-cta-ph .bb-buy-cta-inner { border-style:dashed; opacity:.6; }
.bb-buy-cta-lbl { outline:none; min-width:2.5rem; cursor:text; }
.bb-buy-cta-lbl:focus { text-decoration:underline; text-underline-offset:2px; }
.bb-buy-link-icon { background:none; border:none; padding:0; cursor:pointer; color:inherit; opacity:.5; display:inline-flex; align-items:center; flex-shrink:0; transition:opacity .15s; }
.bb-buy-link-icon:hover { opacity:1; }
/* Inline URL entry — replaces label inside the button */
.bb-buy-url-inline { border:none; outline:none; background:transparent; font-family:'DINBlack',sans-serif; font-weight:900; font-size:.72rem; letter-spacing:.07em; min-width:9rem; width:9rem; color:inherit; text-transform:none; }
.bb-buy-url-ok-inline { background:none; border:none; padding:0 0 0 .15rem; cursor:pointer; color:inherit; font-size:1rem; font-weight:900; opacity:.7; }
.bb-buy-url-ok-inline:hover { opacity:1; }

/* ── FAQs ── */
.bb-faq-item { border-bottom:1px solid var(--border); padding:.85rem 0; }
.bb-faq-item:last-child { border-bottom:none; }
.bb-faq-q { font-family:'DINBlack',sans-serif; font-size:.9rem; color:var(--ink); cursor:pointer; display:flex; justify-content:space-between; align-items:center; gap:.5rem; }
.bb-faq-q::after { content:'＋'; font-size:.9rem; color:var(--muted); flex-shrink:0; }
.bb-faq-item.open .bb-faq-q::after { content:'－'; }
.bb-faq-a { display:none; font-size:.87rem; line-height:1.65; color:var(--ink-lt); margin-top:.6rem; }
.bb-faq-item.open .bb-faq-a { display:block; }
.bb-faq-a a { color:var(--accent); text-decoration:underline; }
.bb-faq-img { max-width:100%; border-radius:6px; margin-top:.5rem; }

/* ── Buy links ── */
.bb-buy-links { display:flex; flex-wrap:wrap; gap:.6rem; }
.btn-buy {
  display:inline-block;
  font-family:'DINBlack',sans-serif; font-size:.78rem; text-transform:uppercase;
  letter-spacing:.07em; height:2.75rem; line-height:2.75rem; padding:0 1.2rem; border-radius:7px;
  background:var(--accent); color:#fff; border:none; cursor:pointer;
  transition:background .13s;
}
.btn-buy:hover { background:var(--accent-dk); }

/* ── Resource links ── */
.bb-links { display:flex; flex-wrap:wrap; gap:.6rem; }
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
.bb-rl-drop-target .bb-buy-cta-inner { outline:2px dashed var(--accent); outline-offset:2px; }
/* Custom resource link pill buttons — match admin bb-buy-cta-inner (outlined) */
.bb-res-btn {
  display:inline-flex; align-items:center; gap:.35rem;
  height:2.75rem; padding:0 .85rem; border-radius:6px;
  border:2px solid currentColor; color:var(--ink);
  font-family:'DINBlack',sans-serif; font-size:.72rem; font-weight:900;
  letter-spacing:.07em; text-transform:uppercase;
  text-decoration:none; white-space:nowrap; box-sizing:border-box;
  transition:background .12s, color .12s; cursor:pointer;
}
.bb-res-btn:hover { background:var(--ink); color:var(--surface); }
/* Coming Soon section */
.bb-coming-soon { text-align:center; padding:4rem 2rem; }
.bb-cs-badge { display:inline-block; font-family:'DINBlack',sans-serif; font-size:.8rem; letter-spacing:.12em; text-transform:uppercase; color:var(--accent); border:2px solid var(--accent); border-radius:2rem; padding:.4rem 1.2rem; margin-bottom:1.2rem; }
.bb-cs-text { font-size:1.1rem; color:var(--ink-lt); margin:0; }

/* ── More games ── */
.bb-more-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(160px,1fr)); gap:.9rem; }
.bb-more-card {
  background:var(--surface); border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.07);
  overflow:hidden; transition:box-shadow .15s, transform .15s; cursor:pointer;
}
.bb-more-card:hover { box-shadow:0 4px 18px rgba(59,59,170,.15); transform:translateY(-2px); }
.bb-more-card img { width:100%; aspect-ratio:1; object-fit:cover; display:block; }
.bb-more-card-ph { width:100%; aspect-ratio:1; background:linear-gradient(135deg,#dde 0%,#c8c8e8 100%); display:flex; align-items:center; justify-content:center; font-size:2.2rem; opacity:.45; }
.bb-more-body { padding:.55rem .7rem .7rem; }
.bb-more-name { font-family:'DINBlack',sans-serif; font-size:.8rem; color:var(--accent); margin-bottom:.1rem; }
.bb-more-meta { font-size:.72rem; color:var(--muted); }
/* Add-game card (admin only) — matches game card shape: grey image area + white body */
.bb-add-game-card { display:none; border-radius:12px; border:2px dashed rgba(59,59,170,.22); background:#fff; cursor:pointer; transition:border-color .15s, box-shadow .15s, transform .15s; overflow:hidden; box-shadow:0 2px 14px rgba(0,0,0,.06); }
body.bb-admin-mode .bb-add-game-card { display:block; }
.bb-add-game-card:hover { border-color:var(--accent); box-shadow:0 6px 24px rgba(0,0,0,.12); transform:translateY(-3px); }
.bb-add-game-card-inner { width:100%; aspect-ratio:1; display:flex; align-items:center; justify-content:center; font-size:3rem; color:rgba(59,59,170,.28); line-height:1; transition:color .15s, background .15s; background:linear-gradient(135deg,#e8e8f2,#d8d8ec); }
.bb-add-game-card:hover .bb-add-game-card-inner { color:var(--accent); background:linear-gradient(135deg,#e0e0f0,#cecee8); }
.bb-add-game-card-label { padding:.75rem .9rem 1rem; font-family:'DINBlack',sans-serif; font-size:.95rem; color:rgba(59,59,170,.45); }
.bb-add-game-card:hover .bb-add-game-card-label { color:var(--accent); }

/* ── Footer (shared) ── */

/* ── Lightbox ── */
.bb-lightbox { display:none; position:fixed; inset:0; z-index:500; background:rgba(0,0,0,.92); align-items:center; justify-content:center; cursor:zoom-out; }
.bb-lightbox.open { display:flex; }
.bb-lightbox img { max-width:92vw; max-height:92vh; border-radius:8px; object-fit:contain; }

@media(max-width:640px) {
  .bb-hero-content { padding:1.8rem 1rem 2.5rem; }
  .bb-nav-game { display:none; }
  .bb-main { padding:2rem .9rem 4rem; }
  .bb-components { grid-template-columns:1fr 1fr; }
  .bb-more-grid { grid-template-columns:repeat(2,1fr); }
  .bb-reviews { grid-template-columns:1fr; }
}

/* ── Admin edit mode ── */
.bb-admin-bar { display:none; }  /* replaced by in-nav admin controls */
/* Save toast */
.bb-save-toast {
  display:none; position:fixed; bottom:1.5rem; right:1.5rem; z-index:900;
  background:#1e1e30; border:1px solid rgba(124,158,255,.3); border-radius:8px;
  padding:.65rem 1.1rem; font-family:'DINBlack',sans-serif; font-size:.78rem;
  text-transform:uppercase; letter-spacing:.07em; color:#7c9eff;
  box-shadow:0 4px 20px rgba(0,0,0,.4); animation:bb-toast-in .2s ease;
}
.bb-save-toast.visible { display:block; }
@keyframes bb-toast-in { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:none; } }
.bb-admin-label {
  font-family:'DINBlack',sans-serif; font-size:.66rem; text-transform:uppercase;
  letter-spacing:.1em; color:#7c9eff; opacity:.8;
}
/* In-nav admin controls — hidden until admin mode activates */
.bb-nav-admin { display:none; align-items:center; gap:.5rem; flex-shrink:0; }
body.bb-admin-mode .bb-nav-admin { display:flex; }
body.bb-admin-mode .bb-nav-links { display:none; }
/* User icon + menu (game detail page) */
.btn-user-icon {
  width:30px; height:30px; border-radius:50%; border:1.5px solid rgba(255,255,255,.3);
  background:transparent; color:#fff; cursor:pointer; display:flex; align-items:center;
  justify-content:center; transition:background .14s, border-color .14s; flex-shrink:0;
}
.btn-user-icon:hover { background:rgba(255,255,255,.15); border-color:#fff; }
.btn-user-icon.signed-in { border-color:<?= $_template === 'opensea' ? '#00e5ff' : '#7c9eff' ?>; color:<?= $_template === 'opensea' ? '#00e5ff' : '#7c9eff' ?>; background:rgba(255,255,255,.1); }
.user-menu-wrap { position:relative; flex-shrink:0; }
.user-menu {
  display:none; position:absolute; top:calc(100% + .4rem); right:0;
  background:#1a1a2e; border:1px solid rgba(255,255,255,.2);
  border-radius:8px; min-width:150px; z-index:300;
  box-shadow:0 6px 20px rgba(0,0,0,.4); overflow:hidden;
}
.user-menu.open { display:block; }
.user-menu-item {
  display:block; width:100%; background:none; border:none;
  color:rgba(255,255,255,.85); text-align:left; cursor:pointer;
  font-family:'DINBlack',sans-serif; font-size:.72rem;
  text-transform:uppercase; letter-spacing:.07em;
  padding:.6rem 1rem; transition:background .12s;
}
.user-menu-item:hover { background:rgba(255,255,255,.1); color:#fff; }
.user-menu-divider { border:none; border-top:1px solid rgba(255,255,255,.12); margin:.2rem 0; }
/* Overlay dialogs (sign-in, settings, publish) */
.bb-overlay {
  display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:500;
  align-items:center; justify-content:center; padding:1.5rem;
}
.bb-overlay.open { display:flex; }
.bb-dialog {
  background:#fff; border-radius:12px; padding:1.6rem 1.75rem 1.5rem;
  width:100%; max-width:360px; box-shadow:0 12px 48px rgba(0,0,0,.35); position:relative;
}
.bb-dialog-title { font-family:'DINBlack',sans-serif; font-size:1rem; text-transform:uppercase; letter-spacing:.07em; color:var(--ink); margin-bottom:1rem; }
.bb-field-group { margin-bottom:.7rem; }
.bb-field-group label { display:block; font-size:.75rem; color:var(--muted); margin-bottom:.25rem; }
.bb-field { display:block; width:100%; padding:.55rem .75rem; border:1.5px solid var(--border); border-radius:7px; font-family:'DINRegular',sans-serif; font-size:.9rem; color:var(--ink); outline:none; transition:border-color .14s; }
.bb-field:focus { border-color:var(--accent); }
.bb-err { font-size:.78rem; color:#e53935; min-height:1.1rem; margin:.3rem 0; }
.bb-dialog-close { position:absolute; top:.75rem; right:.9rem; background:none; border:none; font-size:1.35rem; color:var(--ink-lt); cursor:pointer; line-height:1; }
.bb-btn { font-family:'DINBlack',sans-serif; font-size:.75rem; text-transform:uppercase; letter-spacing:.07em; padding:.42rem 1rem; border-radius:6px; border:1.5px solid var(--border); background:var(--surface); color:var(--ink); cursor:pointer; }
.bb-btn:hover { background:#f0f0f8; }
.bb-btn-sm { font-family:'DINBlack',sans-serif; font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; padding:.3rem .65rem; border-radius:5px; border:1.5px solid var(--border); background:var(--surface); color:var(--ink-lt); cursor:pointer; white-space:nowrap; }
.bb-btn-sm:hover { background:#f0f0f8; color:var(--ink); }
.bb-dialog-actions { display:flex; gap:.5rem; justify-content:flex-end; margin-top:.9rem; }
.btn-cancel { font-family:'DINBlack',sans-serif; font-size:.75rem; text-transform:uppercase; letter-spacing:.07em; padding:.42rem .9rem; border-radius:6px; border:1.5px solid var(--border); background:var(--surface); color:var(--ink-lt); cursor:pointer; }
.btn-cancel:hover { background:#f0f0f8; }
.btn-primary { font-family:'DINBlack',sans-serif; font-size:.75rem; text-transform:uppercase; letter-spacing:.07em; padding:.42rem .9rem; border-radius:6px; border:none; background:var(--accent); color:#fff; cursor:pointer; transition:opacity .13s; }
.btn-primary:disabled { opacity:.45; cursor:default; }
.btn-primary:not(:disabled):hover { opacity:.88; }
#siConfirmPanel { display:none; }
/* bbed-scroll for settings dialog */
.bbed-scroll { max-height:calc(80vh - 180px); overflow-y:auto; }
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
.bb-stats-edit { display:flex; flex-wrap:wrap; gap:.5rem .75rem; margin:.4rem 0 .2rem; }
.bb-stat-field { display:flex; align-items:center; gap:.3rem; background:rgba(0,0,0,.25); border:1px solid rgba(255,255,255,.15); border-radius:6px; padding:.3rem .55rem; }
.bb-stat-icon { width:14px; height:14px; stroke:rgba(255,255,255,.55); flex-shrink:0; }
.bb-stat-input { background:transparent; border:none; outline:none; color:#fff; font-size:.8rem; width:90px; font-family:inherit; }
.bb-stat-num { width:36px; text-align:center; -moz-appearance:textfield; }
.bb-stat-num::-webkit-inner-spin-button, .bb-stat-num::-webkit-outer-spin-button { -webkit-appearance:none; }
.bb-stat-sep { color:rgba(255,255,255,.4); font-size:.8rem; }
.bb-stat-unit { color:rgba(255,255,255,.55); font-size:.75rem; white-space:nowrap; }
.bb-stat-input::placeholder { color:rgba(255,255,255,.3); }
.bb-stat-field:focus-within { border-color:rgba(124,158,255,.6); background:rgba(0,0,0,.35); }
.bb-stat-field:focus-within .bb-stat-icon { stroke:rgba(124,158,255,.8); }
/* Stat icon pip inside public pills */
.bb-stat-pip { width:12px; height:12px; stroke:rgba(255,255,255,.7); flex-shrink:0; margin-right:4px; vertical-align:middle; }
.bb-stat { display:inline-flex; align-items:center; }
/* Role icon (gear/brush) before designer/artist inputs */
.bb-role-icon { width:13px; height:13px; stroke:rgba(255,255,255,.5); flex-shrink:0; margin-right:5px; }
/* Designer/artist rows */
.bb-stat-designers { font-size:.85rem; color:rgba(255,255,255,.75); margin-top:.2rem; display:flex; flex-wrap:wrap; align-items:center; gap:.25rem; min-height:1.6em; }
/* Public credits row */
.bb-stat-credits { display:flex; flex-wrap:wrap; gap:.5rem 1.2rem; margin-top:.25rem; }
.bb-credit-item { display:inline-flex; align-items:center; font-size:.85rem; color:rgba(255,255,255,.75); }
/* Inline designer inputs */
.bb-designer-input {
  background:transparent; border:none; border-bottom:1.5px dashed rgba(255,255,255,.45);
  color:#fff; font-family:'DINRegular',Arial,sans-serif; font-size:inherit;
  width:110px; outline:none; padding:0 2px; margin-right:4px;
  transition:border-color .15s, width .15s;
}
.bb-designer-input:focus { border-bottom-color:#fff; }
.bb-designer-input::placeholder { color:rgba(255,255,255,.35); font-style:italic; }
/* Editable field highlights in admin mode */
body.bb-admin-mode #bbEditTitle { outline:2px dashed rgba(124,158,255,.5); border-radius:3px; cursor:text; padding:2px 4px; }
body.bb-admin-mode #bbEditTitle:focus,
body.bb-admin-mode #bbEditTitle:hover { outline:2px solid #7c9eff; }
body.bb-admin-mode #bbEditSummary { outline:2px dashed rgba(255,255,255,.3); border-radius:3px; cursor:text; padding:4px 6px; min-height:2em; display:block; }
body.bb-admin-mode #bbEditSummary:focus,
body.bb-admin-mode #bbEditSummary:hover { outline:2px solid rgba(255,255,255,.7); }
body.bb-admin-mode #bbEditDesc { outline:2px dashed rgba(59,59,170,.4); border-radius:4px; cursor:text; padding:4px 6px; }
body.bb-admin-mode #bbEditDesc:focus,
body.bb-admin-mode #bbEditDesc:hover { outline:2px solid var(--accent); background:rgba(59,59,170,.04); }
/* Edit details overlay */
.bbed-overlay {
  display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:600;
  align-items:flex-start; justify-content:center; padding:2rem 1rem; overflow-y:auto;
}
.bbed-overlay.open { display:flex; }
.bbed-dialog {
  background:#fff; border-radius:14px; width:100%; max-width:520px;
  box-shadow:0 12px 50px rgba(0,0,0,.32); overflow:hidden; margin:auto;
}
.bbed-header {
  background:var(--nav-bg); color:#fff; padding:1rem 1.25rem;
  display:flex; align-items:center; justify-content:space-between;
}
.bbed-title { font-family:'DINBlack',sans-serif; font-size:.9rem; text-transform:uppercase; letter-spacing:.07em; }
.bbed-close { background:none; border:none; color:rgba(255,255,255,.6); font-size:1.2rem; cursor:pointer; padding:0 .25rem; line-height:1; }
.bbed-close:hover { color:#fff; }
.bbed-body { padding:1.25rem 1.5rem 1.5rem; max-height:calc(80vh - 120px); overflow-y:auto; }
.bbed-section { margin-bottom:1.1rem; }
.bbed-section-title {
  font-family:'DINBlack',sans-serif; font-size:.65rem; text-transform:uppercase;
  letter-spacing:.1em; color:var(--muted); border-bottom:1px solid var(--border);
  padding-bottom:.35rem; margin-bottom:.65rem;
}
.bbed-row { display:flex; gap:.6rem; margin-bottom:.55rem; }
.bbed-row > * { flex:1; min-width:0; }
.bbed-field-group { margin-bottom:.55rem; }
.bbed-label { display:block; font-size:.73rem; color:var(--muted); margin-bottom:.2rem; }
.bbed-field {
  display:block; width:100%; padding:.45rem .7rem;
  border:1.5px solid var(--border); border-radius:6px;
  font-family:'DINRegular',Arial,sans-serif; font-size:.87rem; color:var(--ink);
  outline:none; transition:border-color .14s;
}
.bbed-field:focus { border-color:var(--accent); }
.bbed-footer {
  padding:.9rem 1.5rem; background:#f8f8fc; border-top:1px solid var(--border);
  display:flex; gap:.5rem; justify-content:flex-end;
}
/* ── Placeholder content (admin only) ── */
/* CSS placeholder for empty contenteditable fields */
body.bb-admin-mode [contenteditable][data-ph]:empty::before {
  content: attr(data-ph);
  opacity: .35; font-style: italic; pointer-events: none; font-family:'DINRegular',Arial,sans-serif;
}
/* Sections that only show in admin mode when empty */
.bb-ph-section { display: none; }
body.bb-admin-mode .bb-ph-section { display: block; }
/* Placeholder stat chips */
.bb-ph-stat { display: none; }
body.bb-admin-mode .bb-ph-stat { display: flex; }
.bb-ph-stat strong { opacity: .5; }
.bb-ph-stat span { opacity: .35; font-style: italic; }
/* Cover image button (top-left of hero) */
.bb-cover-btn { position: absolute; top: .75rem; right: .75rem; z-index: 6; display: none; }
body.bb-admin-mode .bb-hero.no-cover .bb-cover-btn { display: flex; }
body.bb-admin-mode .bb-hero:not(.no-cover) .bb-cover-btn { display: none; }
body.bb-admin-mode .bb-hero:not(.no-cover):hover .bb-cover-btn,
body.bb-admin-mode .bb-hero.bb-drag-over .bb-cover-btn { display: flex; }
/* Hero image upload spinner */
.bb-hero.bb-cover-loading::after { content:''; position:absolute; top:50%; left:50%; width:44px; height:44px; margin:-22px 0 0 -22px; border:4px solid rgba(255,255,255,.25); border-top-color:#fff; border-radius:50%; animation:bb-spin .7s linear infinite; z-index:20; pointer-events:none; }
.bb-hero.bb-cover-loading .bb-hero-bg { filter:brightness(.45); transition:filter .2s; }
/* Gallery upload zones */
.bb-upload-zone {
  position: relative; cursor: pointer; transition: background .13s, border-color .13s, opacity .13s;
  opacity: .35; border: 2px dashed rgba(59,59,170,.25);
}
.bb-upload-zone:hover,
.bb-upload-zone.bb-drag-over { opacity: .7; border-color: rgba(124,158,255,.6); background: rgba(124,158,255,.08); }
.bb-upload-zone.bb-uploading { opacity: .5; cursor: wait; }
.bb-upload-zone.bb-uploaded { opacity: 1; border: none; background: none; padding: 0; font-size: 0; }
.bb-upload-zone.bb-uploaded img { width: 100%; height: 100%; object-fit: cover; border-radius: 8px; display: block; }
/* Placeholder lorem sections */
.bb-ph-lorem {
  font-size:.93rem; line-height:1.75; color:var(--ink-lt); opacity:.35;
  font-style:italic; white-space:pre-wrap;
  border:2px dashed rgba(59,59,170,.2); border-radius:6px; padding:.75rem 1rem;
}
/* ── Footer (shared) ── */
.bb-footer { background:#1c1c1e; color:rgba(255,255,255,.5); }
.bb-footer-main { max-width:1100px; margin:0 auto; padding:3.5rem 2rem 3rem; display:grid; grid-template-columns:1fr 1.15fr; gap:2.5rem 5rem; align-items:start; }
@media(max-width:680px){ .bb-footer-main { grid-template-columns:1fr; padding:2.5rem 1.5rem 2.5rem; gap:2rem; } }
.bb-footer-brand { display:flex; flex-direction:column; gap:.7rem; }
.bb-footer-logo-img { height:52px; object-fit:contain; object-position:left center; display:block; }
.bb-footer-bar-brand { font-family:'DINBlack',sans-serif; font-size:1.15rem; color:rgba(255,255,255,.9); }
.bb-footer-brand-tagline { font-size:.83rem; color:rgba(255,255,255,.42); line-height:1.65; }
.bb-footer-brand-icons { display:flex; gap:.45rem; align-items:center; }
.bb-footer-brand-copy { display:flex; flex-direction:column; gap:.18rem; font-size:.71rem; color:rgba(255,255,255,.28); margin-top:.15rem; }
.bb-footer-game-credit { font-size:.68rem; color:rgba(255,255,255,.2); }
.bb-footer-sub-label { font-family:'DINBlack',sans-serif; font-size:1rem; color:#fff; margin-bottom:.3rem; }
.bb-footer-sub-desc { font-size:.82rem; color:rgba(255,255,255,.42); margin-bottom:1rem; line-height:1.55; }
.bb-footer-sub-form { display:flex; gap:.4rem; }
.bb-footer-sub-input { flex:1; min-width:0; padding:.62rem 1rem; border:none; border-radius:999px; background:rgba(255,255,255,.1); color:#fff; font-size:.88rem; outline:none; transition:background .18s; }
.bb-footer-sub-input::placeholder { color:rgba(255,255,255,.35); }
.bb-footer-sub-input:focus { background:rgba(255,255,255,.16); }
.bb-footer-sub-btn { padding:.62rem 1.4rem; border:none; border-radius:999px; background:var(--accent); color:#fff; font-family:'DINBlack',sans-serif; font-size:.78rem; letter-spacing:.05em; cursor:pointer; transition:opacity .18s; white-space:nowrap; }
.bb-footer-sub-btn:hover { opacity:.85; }
.bb-footer-sub-btn:disabled { opacity:.55; cursor:default; }
.bb-footer-sub-msg { margin-top:.55rem; font-size:.77rem; min-height:1em; }
.bb-footer-sub-msg.ok  { color:#6bd98a; }
.bb-footer-sub-msg.err { color:#e07070; }
.bb-social-btn { width:1.85rem; height:1.85rem; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:border-color .14s,background .14s; cursor:pointer; padding:0; }
.bb-social-btn svg { fill:rgba(255,255,255,.6); pointer-events:none; }
.bb-social-set { border:1.5px solid rgba(255,255,255,.25); background:transparent; }
.bb-social-set:hover { border-color:rgba(255,255,255,.6); background:rgba(255,255,255,.08); }
.bb-social-empty { display:none; }
</style>
<link rel="stylesheet" href="<?= h($_appRoot) ?>css/gameboard-ui.css?v=6" />
<?php if (!isset($_bbPublicBase)): ?><script src="<?= h($_appRoot) ?>js/gb-account.js?v=11"></script><?php endif; ?>
</head>
<body<?= isset($_bbPublicBase) ? '' : ' class="gb-app gb-bill"' ?>>

<!-- ── Nav ── -->
<nav class="bb-nav">
  <div class="bb-nav-left">
    <a class="bb-nav-back" href="<?= h($_bbBase) ?>" title="Back to all games">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
      <span class="bb-nav-back-label">All Games</span>
    </a>
    <span class="bb-nav-sep">|</span>
    <span class="bb-nav-game" id="bbNavGame"><?= h($_name) ?></span>
  </div>
  <!-- Regular nav links (hidden in admin mode) -->
  <div class="bb-nav-links" id="bbNavLinks">
    <?php if ($_richRules): ?><a class="btn-nav" href="<?= h($_richRules) ?>" target="_blank">Rules</a><?php endif; ?>
    <?php if ($_sellsheet): ?><a class="btn-nav" href="<?= h($_sellsheet) ?>" target="_blank">Sellsheet</a><?php endif; ?>
    <?php if ($_pageUrl): ?><a class="btn-nav filled" href="<?= h($_pageUrl) ?>" target="_blank">View Page ↗</a><?php endif; ?>
  </div>
  <!-- Admin controls (shown in admin mode, replaces nav links) -->
  <div class="bb-nav-admin" id="bbAdminBar">
    <span class="bb-admin-label">✏ Admin</span>
    <button class="btn-adm btn-adm-details" onclick="openEditDetails()">Edit Details</button>
    <button class="btn-adm btn-adm-primary" id="bbSaveBtn" onclick="saveGame()">Save Changes</button>
    <button class="btn-adm btn-adm-secondary" onclick="cancelEdit()">Cancel</button>
    <div class="user-menu-wrap">
      <button class="btn-user-icon signed-in" id="bbUserBtn" onclick="toggleBbMenu(event)" title="Menu">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </button>
      <div class="user-menu" id="bbMenu">
        <button class="user-menu-item" onclick="bbMenuAction('settings')">Settings</button>
        <hr class="user-menu-divider" />
        <button class="user-menu-item" onclick="bbMenuAction('fetch')">Fetch</button>
        <button class="user-menu-item" onclick="bbMenuAction('publish')">🌐 Publish Site</button>
        <hr class="user-menu-divider" />
        <button class="user-menu-item" id="bbMenuSignInOut" onclick="bbMenuAction('signout')">Sign Out</button>
      </div>
    </div>
  </div>
</nav>

<!-- Legacy admin bar slot (kept for JS compat, hidden) -->
<div class="bb-admin-bar" id="bbAdminBarLegacy" style="display:none"></div>

<!-- ── Hidden file inputs for image uploads ── -->
<input type="file" id="bbFileInput" accept="image/*" style="display:none" onchange="bbHandleFileInput(event)">
<input type="file" id="bbedFileInput" accept="image/*" style="display:none" onchange="bbedHandleFileInput(event)">

<!-- ── Hero ── -->
<div class="bb-hero<?= $_heroImg ? '' : ' no-cover' ?>" id="bbHero">
  <?php if ($_heroImg): ?>
  <div class="bb-hero-bg" id="bbHeroBg" style="background-image:url('<?= h($_heroImg) ?>')"></div>
  <?php else: ?>
  <div class="bb-hero-bg" id="bbHeroBg" style="background:linear-gradient(135deg,#1a1a2e 0%,#2d2d88 100%)"></div>
  <?php endif; ?>
  <!-- Cover image button — top-left, shown in admin mode (always when no cover, on hover when cover exists) -->
  <button class="bb-img-btn bb-cover-btn" onclick="bbPickImage(bbSetCover)" title="Change cover image"><?= $_imgBtnIcon ?></button>
  <div class="bb-hero-overlay"></div>
  <div class="bb-hero-content">
    <?php if ($_status && !isset($_bbPublicBase)): ?><div class="bb-status-badge" id="bbStatusBadge"><?= h(ucfirst($_status)) ?></div><?php endif; ?>
    <h1 class="bb-hero-title" id="bbEditTitle" data-ph="Game Title" onclick="event.stopPropagation()"><?= h($_name) ?></h1>
    <p class="bb-hero-summary" id="bbEditSummary" data-ph="Click to add a one-line tagline for this game…" onclick="event.stopPropagation()"><?= h($_summary) ?></p>
    <?php if (!isset($_bbPublicBase)): ?>
    <!-- Admin: inline stat inputs with icons -->
    <div class="bb-stats-edit" id="bbStatsEdit">
      <div class="bb-stat-field bb-stat-range">
        <svg class="bb-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <input class="bb-stat-input bb-stat-num" id="bbsiMinPlayers" type="number" min="1" max="99" placeholder="1" oninput="_ed.min_players=this.value.trim()" />
        <span class="bb-stat-sep">–</span>
        <input class="bb-stat-input bb-stat-num" id="bbsiMaxPlayers" type="number" min="1" max="99" placeholder="4" oninput="_ed.max_players=this.value.trim()" />
        <span class="bb-stat-unit">players</span>
      </div>
      <div class="bb-stat-field bb-stat-range">
        <svg class="bb-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <input class="bb-stat-input bb-stat-num" id="bbsiMinPlaytime" type="number" min="1" placeholder="30" oninput="_ed.min_playtime=this.value.trim()" />
        <span class="bb-stat-sep">–</span>
        <input class="bb-stat-input bb-stat-num" id="bbsiMaxPlaytime" type="number" min="1" placeholder="60" oninput="_ed.max_playtime=this.value.trim()" />
        <span class="bb-stat-unit">min</span>
      </div>
      <div class="bb-stat-field">
        <svg class="bb-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 16l3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1z"/><path d="M2 16l3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 4-1 7-1s5 1 7 1h2"/></svg>
        <input class="bb-stat-input" id="bbsiWeight" type="text" placeholder="Weight" oninput="_ed.weight=this.value.trim()" />
      </div>
    </div>
    <!-- Designer row (admin) -->
    <div class="bb-stat-designers" id="bbStatDesigners">
      <svg class="bb-role-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      <span id="bbDesignerInputs"></span>
    </div>
    <!-- Artist row (admin) -->
    <div class="bb-stat-designers" id="bbStatArtists">
      <svg class="bb-role-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9.06 11.9 8.07-8.06a2.85 2.85 0 1 1 4.03 4.03l-8.06 8.08"/><path d="M7.07 14.94c-1.66 0-3 1.35-3 3.02 0 1.33-2.5 1.52-2 2.02 1 1 2.48 1.02 3.5 1.02 2.2 0 3.96-1.77 3.96-3.96a.96.96 0 0 0-.96-.96c-.28-.01-.54-.14-.87-.14z"/></svg>
      <span id="bbArtistInputs"></span>
    </div>
    <?php else: ?>
    <!-- Public: show stat pills for non-empty values -->
    <div class="bb-stats" id="bbStatsRow">
      <?php if ($_count): ?>
      <div class="bb-stat"><svg class="bb-stat-pip" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg><?= h($_count) ?> players</div>
      <?php endif; ?>
      <?php if ($_duration): ?>
      <div class="bb-stat"><svg class="bb-stat-pip" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><?= h($_duration) ?></div>
      <?php endif; ?>
      <?php if ($_weight):       ?><div class="bb-stat"><svg class="bb-stat-pip" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 16l3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1z"/><path d="M2 16l3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 4-1 7-1s5 1 7 1h2"/></svg><?= h($_weight) ?></div><?php endif; ?>
      <?php if ($_mechanic):    ?><div class="bb-stat"><?= h($_mechanic) ?></div><?php endif; ?>
      <?php if ($_ages):        ?><div class="bb-stat">Ages <?= h($_ages) ?></div><?php endif; ?>
      <?php if ($_series):      ?><div class="bb-stat"><?= h($_series) ?></div><?php endif; ?>
      <?php if ($_boxSize):     ?><div class="bb-stat"><?= h($_boxSize) ?></div><?php endif; ?>
      <?php if ($_availability):?><div class="bb-stat"><?= h($_availability) ?></div><?php endif; ?>
    </div>
    <?php if ($_designers || $_artists): ?>
    <div class="bb-stat-credits">
      <?php if ($_designers): ?><span class="bb-credit-item"><svg class="bb-role-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg><?= h(implode(', ', $_designers)) ?></span><?php endif; ?>
      <?php if ($_artists): ?><span class="bb-credit-item"><svg class="bb-role-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9.06 11.9 8.07-8.06a2.85 2.85 0 1 1 4.03 4.03l-8.06 8.08"/><path d="M7.07 14.94c-1.66 0-3 1.35-3 3.02 0 1.33-2.5 1.52-2 2.02 1 1 2.48 1.02 3.5 1.02 2.2 0 3.96-1.77 3.96-3.96a.96.96 0 0 0-.96-.96c-.28-.01-.54-.14-.87-.14z"/></svg><?= h(implode(', ', $_artists)) ?></span><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    <div class="bb-hero-ctas">
      <?php if ($_richPrice || !isset($_bbPublicBase)): ?>
      <span class="bb-price-tag<?= (!$_richPrice) ? ' bb-cta-ph' : '' ?>" id="bbPriceBox"><span id="bbEditPrice" data-ph="$0.00"><?= h($_richPrice) ?></span></span>
      <?php endif; ?>
      <?php /* Admin: JS-rendered editable buy buttons */ ?>
      <?php if (!isset($_bbPublicBase)): ?><span id="bbBuyCtas" style="display:none"></span><?php endif; ?>
      <?php /* Public: static buy buttons */ ?>
      <?php foreach ($_buyUrls as $b):
        $bUrl   = trim($b['Value']   ?? '');
        $bLabel = trim($b['Value 1'] ?? '') ?: 'Buy Now';
        if (!$bUrl && !$bLabel) continue;
      ?><?php if ($bUrl): ?><a class="bb-buy-cta-inner filled bb-buy-pub" href="<?= h($bUrl) ?>" target="_blank"><?= h($bLabel) ?></a><?php else: ?><span class="bb-buy-cta-inner filled bb-buy-pub"><?= h($bLabel) ?></span><?php endif; ?><?php endforeach; ?>
      <?php if ($_richRules): ?><a class="btn-nav" href="<?= h($_richRules) ?>" target="_blank">📖 Rules</a><?php endif; ?>
      <?php if ($_playUrl):   ?><a class="btn-nav" href="<?= h($_playUrl) ?>" target="_blank">🎮 Play Online</a><?php endif; ?>
      <?php if ($_printUrl):  ?><a class="btn-nav" href="<?= h($_printUrl) ?>" target="_blank">🖨 Print &amp; Play</a><?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Review Ticker ── -->
<div class="bb-ticker-wrap<?= (empty($_reviews) && !isset($_bbPublicBase)) ? ' bb-ticker-empty' : (empty($_reviews) ? ' bb-ticker-hidden' : '') ?>" id="bbTickerWrap">
  <div class="bb-ticker-track" id="bbTickerTrack">

    <?php foreach ([1,2] as $_pass): /* duplicate for seamless loop */ ?>
    <?php foreach ($_reviews as $rv):
      $rQ = trim($rv['Value'] ?? ''); $rA = trim($rv['Value 1'] ?? '');
      if (!$rQ) continue;
    ?>
    <div class="bb-ticker-item">
      <div class="bb-ticker-quote">"<?= h($rQ) ?>"</div>
      <?php if ($rA): ?><div class="bb-ticker-author">— <?= h($rA) ?></div><?php endif; ?>
    </div>
    <div class="bb-ticker-dot">✦</div>
    <?php endforeach; ?>
    <?php endforeach; ?>
  </div>

</div>

<!-- ── Main ── -->
<div class="bb-main">

  <!-- Coming Soon (public only, when no rich data published yet) -->
  <?php if (!$_hasRichData && isset($_bbPublicBase)): ?>
  <div class="bb-coming-soon">
    <div class="bb-cs-badge">Coming Soon</div>
    <p class="bb-cs-text">More details on this game are coming soon. Stay tuned!</p>
  </div>
  <?php endif; ?>

  <!-- Description (always rendered; hidden in public when empty via .bb-ph-section) -->
  <div class="bb-section<?= $_description ? '' : ' bb-ph-section' ?>">
    <div class="bb-section-label">About the Game</div>
    <?php if ($_description): ?>
    <p class="bb-description" id="bbEditDesc"><?= h($_description) ?></p>
    <?php else: ?>
    <p class="bb-description bb-ph-lorem" id="bbEditDesc" data-ph="Click to add a game description…">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.</p>
    <?php endif; ?>
  </div>

  <!-- Gallery -->
  <div class="bb-section<?= empty($_productImgUrls) && !isset($_bbPublicBase) ? ' bb-sect-empty' : (empty($_productImgUrls) ? ' bb-sect-hidden' : '') ?>" id="bbGallerySection">
    <div class="bb-section-label">Gallery</div>
    <div class="bb-gallery-viewer<?= empty($_productImgUrls) ? ' bb-gallery-empty' : '' ?>" id="bbGalleryViewer">
      <div class="bb-gallery-featured" id="bbGalleryFeatured">
        <img id="bbGalleryFeaturedImg" src="" alt="<?= h($_name) ?>" onload="this.style.display=''" onclick="openLightbox(this.src)" style="display:none" />
        <button class="bb-gallery-featured-del" title="Remove this image" onclick="bbGalleryDeleteFeatured()">✕</button>
      </div>
      <div class="bb-gallery-thumbs" id="bbGalleryThumbs">
        <?php foreach ($_productImgUrls as $i => $img): ?>
        <div class="bb-gallery-thumb<?= $i === 0 ? ' bb-gt-active' : '' ?>" onclick="bbGallerySelect(<?= $i ?>)">
          <img src="<?= h($img) ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>"<?= $i === 0 ? ' onload="(function(el){var f=document.getElementById(\'bbGalleryFeaturedImg\');if(f&&!f.naturalWidth){f.src=el.src;}})(this)"' : '' ?> />
          <button class="bb-gt-del" onclick="event.stopPropagation();bbGalleryDelete(<?= $i ?>)" title="Remove">✕</button>
        </div>
        <?php endforeach; ?>
        <div class="bb-gallery-thumb-add" id="bbGalleryAddBtn"
             onclick="bbPickGalleryImage()" title="Click or drop image here"
             ondragover="event.preventDefault();this.classList.add('bb-drag-over')"
             ondragleave="this.classList.remove('bb-drag-over')"
             ondrop="event.preventDefault();this.classList.remove('bb-drag-over');bbGalleryDropImage(event)"><span>+</span></div>
      </div>
    </div>
  </div>

  <!-- Video(s) -->
  <?php if (!$_videoEmbed && !$_videoDirect): ?>
  <!-- Placeholder video (admin only) -->
  <div class="bb-section bb-ph-section" id="bbVideoPlaceholder">
    <div class="bb-section-label">Watch</div>
    <div class="bb-video-ph-inner"
         onclick="bbVideoPhClick(event,this)"
         ondragover="bbVideoDragOver(event,this)"
         ondragleave="bbVideoDragLeave(event,this)"
         ondrop="bbVideoDrop(event,this)">
      <div class="bb-video-ph-icon" style="font-size:3rem;">▶</div>
      <div class="bb-video-ph-label" style="font-family:'DINBlack',sans-serif;font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;color:#fff;">Drop a video file or click to add URL</div>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($_videoEmbed || $_videoDirect): ?>
  <div class="bb-section" id="bbVideoSection">
    <div class="bb-section-label">Watch</div>
    <div class="bb-video-wrap" id="videoWrap">
      <?php if ($_videoEmbed): ?>
      <iframe id="videoFrame" src="<?= h($_videoEmbed) ?>" allow="autoplay; encrypted-media" allowfullscreen loading="lazy"></iframe>
      <?php else: ?>
      <video id="videoFrame" src="<?= h($_videoDirect) ?>" controls style="width:100%;border-radius:10px;display:block;"></video>
      <?php endif; ?>
      <button class="bb-video-del-btn" onclick="bbVideoRemoveClick()" title="Remove video">✕</button>
    </div>
  </div>
  <?php endif; ?>

  <!-- What's in the Box -->
  <div class="bb-section<?= empty($_components) ? ' bb-sect-empty' : '' ?>">
    <div class="bb-section-label">What's in the Box</div>
    <textarea class="bb-inline-edit" id="bbCompEdit"
      placeholder="One item per line:&#10;50 Cards&#10;1 Game Board&#10;6 Dice"></textarea>
    <p class="bb-inline-hint">One item per line — e.g. "50 Cards"</p>
    <div class="bb-components bb-display-content">
      <?php foreach ($_components as $c):
        $cVal = trim($c['Value'] ?? ''); if (!$cVal) continue;
        $cQty = ''; $cName = $cVal;
        if (preg_match('/^(\d+)\s+(.+)$/', $cVal, $pm)) { $cQty = $pm[1]; $cName = $pm[2]; }
      ?>
      <div class="bb-comp-item">
        <?php if ($cQty): ?><span class="bb-comp-qty"><?= h($cQty) ?></span><?php endif; ?>
        <span class="bb-comp-name"><?= h($cName) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Video Reviews -->
  <div class="bb-section<?= empty($_videoReviews) ? ' bb-sect-empty' : '' ?>">
    <div class="bb-section-label">Video Reviews</div>
    <!-- Admin: compact tile editor (JS-rendered) -->
    <div class="bb-vrev-grid bb-inline-edit" id="bbVrevGrid"></div>
    <!-- Public: full embedded players -->
    <div class="bb-vreviews bb-display-content">
      <?php foreach ($_videoReviews as $vr):
        $vrUrl   = trim($vr['Value']   ?? '');
        $vrName  = trim($vr['Value 1'] ?? '');
        if (!$vrUrl) continue;
        $vrEmbed = _bb_embed($vrUrl);
      ?>
      <div class="bb-vreview">
        <div class="bb-vreview-wrap">
          <?php if ($vrEmbed): ?>
          <iframe src="<?= h($vrEmbed) ?>" allow="autoplay; encrypted-media" allowfullscreen loading="lazy"></iframe>
          <?php else: ?>
          <video src="<?= h($vrUrl) ?>" controls></video>
          <?php endif; ?>
        </div>
        <?php if ($vrName): ?><div class="bb-vreview-label"><?= h($vrName) ?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Resources -->
  <?php
  $_hasHardLinks = $_richRules || $_sellsheet || $_playUrl || $_printUrl || $_pageUrl;
  $_hasLinks = $_hasHardLinks || !empty($_resourceLinks);
  ?>
  <div class="bb-section<?= (!$_hasLinks && !isset($_bbPublicBase)) ? ' bb-sect-empty' : (!$_hasLinks ? ' bb-sect-hidden' : '') ?>" id="bbResourcesSection">
    <div class="bb-section-label">Resources</div>
    <!-- Admin: JS-rendered inline buttons (same style as buy CTAs) -->
    <div class="bb-links" id="bbResLinks" style="display:none"></div>
    <!-- Public: static resource link pills -->
    <div class="bb-links bb-display-content">
      <?php if ($_richRules): ?><a class="btn-link" href="<?= h($_richRules) ?>" target="_blank">📖 Rules PDF</a><?php endif; ?>
      <?php if ($_sellsheet): ?><a class="btn-link" href="<?= h($_sellsheet) ?>" target="_blank">📄 Sellsheet</a><?php endif; ?>
      <?php if ($_playUrl):   ?><a class="btn-link" href="<?= h($_playUrl) ?>" target="_blank">🎮 Play Online</a><?php endif; ?>
      <?php if ($_printUrl):  ?><a class="btn-link" href="<?= h($_printUrl) ?>" target="_blank">🖨 Print &amp; Play</a><?php endif; ?>
      <?php if ($_pageUrl):   ?><a class="btn-link" href="<?= h($_pageUrl) ?>" target="_blank">🔗 Product Page</a><?php endif; ?>
      <?php foreach ($_resourceLinks as $rl):
        $rlUrl = trim($rl['Value'] ?? ''); $rlLabel = trim($rl['Value 1'] ?? '');
        if (!$rlUrl && !$rlLabel) continue;
      ?><?php if ($rlUrl): ?><a class="bb-res-btn" href="<?= h($rlUrl) ?>" target="_blank"><?= h($rlLabel ?: 'Link') ?></a><?php else: ?><span class="bb-res-btn"><?= h($rlLabel ?: 'Link') ?></span><?php endif; ?><?php endforeach; ?>
    </div>
  </div>

  <!-- FAQs -->
  <div class="bb-section<?= empty($_faqs) ? ' bb-sect-empty' : '' ?>">
    <div class="bb-section-label">FAQ</div>
    <div class="bb-faq-editor bb-inline-edit" id="bbFaqsList"></div>
    <?php foreach ($_faqs as $fi => $fq):
      $fqQ = trim($fq['Value']   ?? '');
      $fqA = trim($fq['Value 1'] ?? '');
      if (!$fqQ) continue;
    ?>
    <div class="bb-faq-item bb-display-content" id="faq<?= $fi ?>">
      <div class="bb-faq-q" onclick="toggleFaq('faq<?= $fi ?>')"><?= h($fqQ) ?></div>
      <div class="bb-faq-a"><?php if (strncmp($fqA, 'img:', 4) === 0): ?><img class="bb-faq-img" src="<?= h(substr($fqA, 4)) ?>" alt="" loading="lazy"><?php else: ?><?= nl2br(h($fqA)) ?><?php endif; ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- More Games -->
  <?php if (!empty($_otherGames) || !isset($_bbPublicBase)): ?>
  <div class="bb-section<?= empty($_otherGames) && !isset($_bbPublicBase) ? ' bb-sect-empty' : '' ?>">
    <div class="bb-section-label">More Games</div>
    <div class="bb-more-grid">
      <?php foreach (array_slice($_otherGames, 0, 8) as $og):
        $ogName = trim($og['Name'] ?? '');
        $ogImg  = _bb_img(trim($og['Cover URL'] ?? $og['Image URL'] ?? $og['Image'] ?? ''));
        $ogMeta = array_filter([trim($og['Count'] ?? '') ? trim($og['Count']).' players' : '', trim($og['Duration'] ?? '')]);
      ?>
      <a class="bb-more-card" href="<?= h($_bbBase . _bb_slug($ogName)) ?>">
        <?php if ($ogImg): ?>
        <img src="<?= h($ogImg) ?>" alt="<?= h($ogName) ?>" loading="lazy" />
        <?php else: ?>
        <div class="bb-more-card-ph"><img src="<?= h($_appRoot) ?>images/bb_logo.png" alt="" style="width:48px;height:48px;border-radius:10px;opacity:.35;" /></div>
        <?php endif; ?>
        <div class="bb-more-body">
          <div class="bb-more-name"><?= h($ogName) ?></div>
          <?php if ($ogMeta): ?><div class="bb-more-meta"><?= h(implode(' · ', $ogMeta)) ?></div><?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
      <!-- Add-game card: admin only, appended after existing games -->
      <?php if (!isset($_bbPublicBase)): ?>
      <div class="bb-add-game-card" onclick="openAddGame()" title="Add a new game">
        <div class="bb-add-game-card-inner">+</div>
        <div class="bb-add-game-card-label">New Game</div>
      </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /bb-main -->

<?php
$_gameCredit = !empty($_designers)
    ? $_name . ' · Designed by ' . implode(' & ', $_designers)
    : '';
_bb_footer(['gameCredit' => $_gameCredit]);
?>

<div class="bb-lightbox" id="lightbox" onclick="closeLightbox()">
  <img id="lightboxImg" src="" alt="" />
</div>

<!-- ── Save toast ── -->
<div class="bb-save-toast" id="bbSaveToast"></div>

<!-- ── Publish Site dialog ── -->
<div class="bb-overlay" id="bbPublishOverlay" onclick="if(event.target===this)closePublishDialog()">
  <div class="bb-dialog" onclick="event.stopPropagation()" style="max-width:480px">
    <button class="bb-dialog-close" onclick="closePublishDialog()">×</button>
    <div class="bb-dialog-title">🌐 Publish Site</div>
    <p style="font-size:.82rem;color:var(--ink-lt,#666);margin-bottom:1.2rem;line-height:1.5">
      Your public BillBoard is live. Anyone with this link can browse your published games.
    </p>
    <div class="bb-field-group" style="margin-bottom:.6rem">
      <label style="font-size:.75rem;font-weight:600;color:var(--ink-lt,#888);text-transform:uppercase;letter-spacing:.05em">Catalog</label>
      <div style="display:flex;gap:.4rem;align-items:center">
        <input class="bb-field" id="bbPubCatalogUrl" type="text" readonly onclick="this.select()" style="flex:1;font-size:.8rem;cursor:text" />
        <button class="bb-btn-sm" onclick="bbCopyUrl('bbPubCatalogUrl')">Copy</button>
        <a id="bbPubCatalogLink" href="#" target="_blank" class="bb-btn-sm" style="text-decoration:none">Open ↗</a>
      </div>
    </div>
    <div id="bbPubGameUrl" style="margin-bottom:1.2rem">
      <div class="bb-field-group" style="margin-bottom:.3rem">
        <label style="font-size:.75rem;font-weight:600;color:var(--ink-lt,#888);text-transform:uppercase;letter-spacing:.05em">This Game</label>
        <div style="display:flex;gap:.4rem;align-items:center">
          <input class="bb-field" id="bbPubGameUrlInput" type="text" readonly onclick="this.select()" style="flex:1;font-size:.8rem;cursor:text" />
          <button class="bb-btn-sm" onclick="bbCopyUrl('bbPubGameUrlInput')">Copy</button>
          <a id="bbPubGameUrlLink" href="#" target="_blank" class="bb-btn-sm" style="text-decoration:none">Open ↗</a>
        </div>
      </div>
    </div>
    <div style="display:flex;justify-content:flex-end;margin-top:1rem">
      <button class="bb-btn" onclick="closePublishDialog()">Done</button>
    </div>
  </div>
</div>

<!-- ── Sign-in dialog ── -->
<div class="bb-overlay" id="signInOverlay" onclick="if(event.target===this)forceCloseSignIn()">
  <div class="bb-dialog" onclick="event.stopPropagation()"
       onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitSignIn(false);}">
    <div class="bb-dialog-title" id="siTitle">Sign In</div>
    <div id="siMainPanel">
      <div class="bb-field-group"><label>Email</label><input class="bb-field" id="siEmail" type="email" placeholder="you@example.com" autocomplete="email" /></div>
      <div class="bb-field-group"><label>Password</label><input class="bb-field" id="siPassword" type="password" placeholder="••••••••" autocomplete="current-password" /></div>
    </div>
    <div id="siConfirmPanel">
      <p style="font-size:.85rem;color:var(--ink-lt);line-height:1.55;margin-bottom:.75rem;">No account found for this email. Create one now?</p>
    </div>
    <div class="bb-err" id="siErr"></div>
    <div class="bb-dialog-actions">
      <button class="btn-cancel" onclick="forceCloseSignIn()">Cancel</button>
      <button class="btn-primary" id="siBtn" onclick="submitSignIn(false)">Sign In</button>
    </div>
  </div>
</div>

<!-- ── Settings dialog ── -->
<div class="bbed-overlay" id="settingsOverlay" onclick="if(event.target===this)forceCloseSettings()">
  <div class="bbed-dialog" onclick="event.stopPropagation()"
       onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitSettings();}">
    <div class="bbed-header">
      <span class="bbed-title">Settings</span>
      <button class="bbed-close" onclick="forceCloseSettings()">✕</button>
    </div>
    <div class="bbed-body"><div class="bbed-scroll">
      <div class="bbed-section">
        <div class="bbed-section-title">Company</div>
        <div class="bbed-field-group"><label class="bbed-label">Company Name</label><input class="bbed-field" id="setCompany" type="text" placeholder="Acme Games LLC" /></div>
        <div class="bbed-field-group"><label class="bbed-label">Logo URL</label><input class="bbed-field" id="setLogo" type="url" placeholder="https://…/logo.png" /></div>
        <div class="bbed-field-group"><label class="bbed-label">Tagline</label><input class="bbed-field" id="setTagline" type="text" placeholder="Independent board game publisher" /></div>
        <div class="bbed-field-group"><label class="bbed-label">About</label><textarea class="bbed-field" id="setAbout" rows="3" style="resize:vertical"></textarea></div>
      </div>
      <div class="bbed-section">
        <div class="bbed-section-title">Images</div>
        <?php for ($i = 1; $i <= 6; $i++): ?>
        <div class="bbed-field-group"><label class="bbed-label">Image <?= $i ?></label><input class="bbed-field" id="setCarousel<?= $i ?>" type="url" placeholder="https://…/image.jpg" /></div>
        <?php endfor; ?>
      </div>
      <div class="bbed-section">
        <div class="bbed-section-title">Social Links</div>
        <div class="bbed-row">
          <div class="bbed-field-group"><label class="bbed-label">Instagram</label><input class="bbed-field" id="setInsta" type="url" placeholder="https://instagram.com/…" /></div>
          <div class="bbed-field-group"><label class="bbed-label">X / Twitter</label><input class="bbed-field" id="setTwitter" type="url" placeholder="https://x.com/…" /></div>
        </div>
        <div class="bbed-row">
          <div class="bbed-field-group"><label class="bbed-label">Facebook</label><input class="bbed-field" id="setFb" type="url" placeholder="https://facebook.com/…" /></div>
          <div class="bbed-field-group"><label class="bbed-label">YouTube</label><input class="bbed-field" id="setYt" type="url" placeholder="https://youtube.com/…" /></div>
        </div>
        <div class="bbed-field-group"><label class="bbed-label">BoardGameGeek</label><input class="bbed-field" id="setBgg" type="url" placeholder="https://boardgamegeek.com/…" /></div>
      </div>
      <div class="bbed-section">
        <div class="bbed-section-title">Display</div>
        <div class="bbed-row">
          <div class="bbed-field-group"><label class="bbed-label">Banner Color</label><input class="bbed-field" id="setBannerColor" type="color" style="height:2.4rem;padding:.2rem .3rem;cursor:pointer" /></div>
          <div class="bbed-field-group"><label class="bbed-label">Template</label><select class="bbed-field" id="setTemplate"><option value="">Classic (default)</option><option value="opensea">OpenSea</option></select></div>
        </div>
        <div class="bbed-field-group"><label class="bbed-label">Copyright Line</label><input class="bbed-field" id="setCopyright" type="text" placeholder="© 2026 Acme Games LLC" /></div>
        <div class="bbed-field-group"><label class="bbed-label">Footer Note</label><input class="bbed-field" id="setFooterNote" type="text" placeholder="contact@example.com" /></div>
      </div>
    </div></div>
    <div class="bbed-footer">
      <div class="bb-err bbed-err" id="setErr"></div>
      <button class="btn-adm btn-adm-secondary" onclick="forceCloseSettings()">Cancel</button>
      <button class="btn-adm btn-adm-primary" id="setBtn" onclick="submitSettings()">Save</button>
    </div>
  </div>
</div>

<!-- ── Edit Details modal ── -->
<div class="bbed-overlay" id="bbedOverlay" onclick="if(event.target===this)closeEditDetails()">
  <div class="bbed-dialog" onclick="event.stopPropagation()">
    <div class="bbed-header">
      <span class="bbed-title">Edit Details</span>
      <button class="bbed-close" onclick="closeEditDetails()">✕</button>
    </div>
    <div class="bbed-body">
      <div class="bbed-section">
        <div class="bbed-section-title">Game Info</div>
        <div class="bbed-row">
          <div class="bbed-field-group">
            <label class="bbed-label">Status</label>
            <select class="bbed-field" id="bbedStatus">
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
          <div class="bbed-field-group">
            <label class="bbed-label">Availability</label>
            <input class="bbed-field" id="bbedAvailability" type="text" placeholder="e.g. Kickstarter" />
          </div>
        </div>
        <div class="bbed-row">
          <div class="bbed-field-group">
            <label class="bbed-label">Designer 1</label>
            <input class="bbed-field" id="bbedDesigner1" type="text" />
          </div>
          <div class="bbed-field-group">
            <label class="bbed-label">Designer 2</label>
            <input class="bbed-field" id="bbedDesigner2" type="text" />
          </div>
        </div>
        <div class="bbed-row">
          <div class="bbed-field-group">
            <label class="bbed-label">Designer 3</label>
            <input class="bbed-field" id="bbedDesigner3" type="text" />
          </div>
          <div class="bbed-field-group">
            <label class="bbed-label">Designer 4</label>
            <input class="bbed-field" id="bbedDesigner4" type="text" />
          </div>
        </div>
        <div class="bbed-row">
          <div class="bbed-field-group">
            <label class="bbed-label">Artist 1</label>
            <input class="bbed-field" id="bbedArtist1" type="text" />
          </div>
          <div class="bbed-field-group">
            <label class="bbed-label">Artist 2</label>
            <input class="bbed-field" id="bbedArtist2" type="text" />
          </div>
        </div>
        <div class="bbed-row">
          <div class="bbed-field-group">
            <label class="bbed-label">Artist 3</label>
            <input class="bbed-field" id="bbedArtist3" type="text" />
          </div>
          <div class="bbed-field-group">
            <label class="bbed-label">Artist 4</label>
            <input class="bbed-field" id="bbedArtist4" type="text" />
          </div>
        </div>
      </div>
      <div class="bbed-section">
        <div class="bbed-section-title">Dates</div>
        <div class="bbed-row">
          <div class="bbed-field-group">
            <label class="bbed-label">Date Started</label>
            <input class="bbed-field" id="bbedDateStarted" type="text" placeholder="e.g. Jan 2024" />
          </div>
          <div class="bbed-field-group">
            <label class="bbed-label">Date Signed</label>
            <input class="bbed-field" id="bbedDateSigned" type="text" placeholder="e.g. Mar 2024" />
          </div>
        </div>
        <div class="bbed-field-group" style="max-width:50%;padding-right:.3rem">
          <label class="bbed-label">Date Published</label>
          <input class="bbed-field" id="bbedDatePublished" type="text" placeholder="e.g. Nov 2024" />
        </div>
      </div>
      <div class="bbed-section">
        <div class="bbed-section-title">Images &amp; Media</div>
        <div class="bbed-field-group">
          <label class="bbed-label">Header Image URL (hero banner — overrides ProductImage)</label>
          <div class="bbed-img-row">
            <input class="bbed-field" id="bbedHeaderImageUrl" type="url" placeholder="https://…" />
            <button type="button" class="bbed-upload-btn" onclick="bbedPickImage('bbedHeaderImageUrl')" title="Upload image">📷</button>
          </div>
        </div>
        <div class="bbed-field-group">
          <label class="bbed-label">Cover URL (background image)</label>
          <div class="bbed-img-row">
            <input class="bbed-field" id="bbedCoverUrl" type="url" placeholder="https://…" />
            <button type="button" class="bbed-upload-btn" onclick="bbedPickImage('bbedCoverUrl')" title="Upload image">📷</button>
          </div>
        </div>
        <div class="bbed-field-group">
          <label class="bbed-label">Image URL (product photo)</label>
          <div class="bbed-img-row">
            <input class="bbed-field" id="bbedImageUrl" type="url" placeholder="https://…" />
            <button type="button" class="bbed-upload-btn" onclick="bbedPickImage('bbedImageUrl')" title="Upload image">📷</button>
          </div>
        </div>
        <div class="bbed-field-group">
          <label class="bbed-label">Video URL (YouTube)</label>
          <input class="bbed-field" id="bbedVideoUrl" type="url" placeholder="https://youtube.com/watch?v=…" />
        </div>
      </div>
      <div class="bbed-section">
        <div class="bbed-section-title">Links</div>
        <div class="bbed-field-group">
          <label class="bbed-label">Page URL</label>
          <input class="bbed-field" id="bbedPageUrl" type="url" placeholder="https://…" />
        </div>
        <div class="bbed-field-group">
          <label class="bbed-label">Sellsheet URL</label>
          <input class="bbed-field" id="bbedSellsheet" type="url" placeholder="https://…" />
        </div>
        <div class="bbed-field-group">
          <label class="bbed-label">Rules URL</label>
          <input class="bbed-field" id="bbedRulesUrl" type="url" placeholder="https://…" />
        </div>
        <div class="bbed-row">
          <div class="bbed-field-group">
            <label class="bbed-label">Play Online URL</label>
            <input class="bbed-field" id="bbedPlayUrl" type="url" placeholder="https://…" />
          </div>
          <div class="bbed-field-group">
            <label class="bbed-label">Print &amp; Play URL</label>
            <input class="bbed-field" id="bbedPrintUrl" type="url" placeholder="https://…" />
          </div>
        </div>
      </div>
    </div>
    <div class="bbed-footer">
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
var _bbUser = null;
var _siPendingEmail = '', _siPendingPass = '';
(function() {
  try {
    var raw = sessionStorage.getItem('billboard_owner_' + SHEET_ID);
    if (!raw) return;
    var u = JSON.parse(raw);
    if (!u || !u.email) return;
    _bbUser = u;
    // Activate admin mode
    document.body.classList.add('bb-admin-mode');
    // Make text fields contenteditable
    ['bbEditTitle', 'bbEditSummary', 'bbEditDesc', 'bbEditPrice'].forEach(function(id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.contentEditable = 'true';
      if (el.classList.contains('bb-ph-lorem')) {
        el.dataset.phContent = el.textContent;
        el.textContent = '';
        el.classList.remove('bb-ph-lorem');
      }
    });
    // Sync price on input; show/hide placeholder class when empty
    var priceEl  = document.getElementById('bbEditPrice');
    var priceBox = document.getElementById('bbPriceBox');
    if (priceEl && priceBox) {
      if (!priceEl.textContent.trim()) priceBox.classList.add('bb-price-ph');
      priceEl.oninput = function() {
        var val = this.textContent.trim();
        _ed.price = val;
        priceBox.classList.toggle('bb-price-ph', !val);
      };
      priceEl.onkeydown = function(e) {
        if (e.key === 'Enter') { e.preventDefault(); this.blur(); }
      };
      priceEl.onfocus = function() { priceBox.classList.remove('bb-price-ph'); };
      priceEl.onblur  = function() { if (!this.textContent.trim()) priceBox.classList.add('bb-price-ph'); };
    }
    // Build admin buy CTA buttons in hero
    bbInitBuyCtas();
    bbInitResLinks();
    bbInitInlineEdits();
    _updateBbAuth();
  } catch(e) { console.error('[admin init]', e); }
  // Gallery init runs outside try/catch so errors here are visible
  if (document.body.classList.contains('bb-admin-mode')) {
    if (!_ed.gallery) _ed.gallery = (GAME_DATA.gallery || []).slice();
    _ed._galleryDirty = false;  // only set true when user adds/removes/reorders
    bbRenderGallery();
    bbGalleryAutoAdvance();
  }
})();

function _updateBbAuth() {
  var btn  = document.getElementById('bbUserBtn');
  var siOut = document.getElementById('bbMenuSignInOut');
  if (_bbUser) {
    if (btn) { btn.classList.add('signed-in'); btn.title = 'Signed in as ' + (_bbUser.email || ''); }
    if (siOut) { siOut.textContent = 'Sign Out'; siOut.className = 'user-menu-item signout'; }
    document.body.classList.add('bb-admin-mode');
  } else {
    if (btn) { btn.classList.remove('signed-in'); btn.title = 'Sign In'; }
    if (siOut) { var _in = (window.GBAccount && GBAccount.account()); siOut.textContent = _in ? 'Sign Out' : 'Sign In'; siOut.className = 'user-menu-item' + (_in ? ' signout' : ''); }
    document.body.classList.remove('bb-admin-mode');
  }
}

// ── Menu ─────────────────────────────────────────────────────────────────────
function toggleBbMenu(e) { e.stopPropagation(); document.getElementById('bbMenu').classList.toggle('open'); }
function closeBbMenu() { var m = document.getElementById('bbMenu'); if (m) m.classList.remove('open'); }
document.addEventListener('click', closeBbMenu);

function bbMenuAction(action) {
  closeBbMenu();
  if (action === 'settings') {
    openSettings();
  } else if (action === 'fetch') {
    if (!_bbUser) { openSignIn(); return; }
    bbDoFetch();
  } else if (action === 'publish') {
    if (!_bbUser) { openSignIn(); return; }
    bbPublishSite();
  } else if (action === 'signout') {
    if (_bbUser) {
      if (confirm('Sign out?')) {
        try { sessionStorage.removeItem('billboard_owner_' + SHEET_ID); } catch(e) {}
        _bbUser = null; _updateBbAuth();
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
  xhr.open('POST', APP_ROOT + 'push/bbAuth.php');
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
    _bbUser = { email: r.email || email, bio: r.bio || {} };
    try { sessionStorage.setItem('billboard_owner_' + SHEET_ID, JSON.stringify(_bbUser)); } catch(e) {}
    _updateBbAuth();
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
    document.getElementById('setTemplate').value     = s['Billboard Template'] || '';
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
  fd.append('bb_template', document.getElementById('setTemplate').value);
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
function bbPublishSite() {
  var btn = document.getElementById('bbUserBtn');
  if (btn) btn.style.opacity = '.4';
  var fd = new FormData(); fd.append('id', SHEET_ID);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/publishBillboard.php');
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
  document.getElementById('bbPubCatalogUrl').value = catalogUrl;
  document.getElementById('bbPubCatalogLink').href = catalogUrl;
  var gameName = ORIG_GAME_NAME ? ORIG_GAME_NAME.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') : '';
  var gameEl = document.getElementById('bbPubGameUrl');
  if (gameName && gameEl) {
    var gameUrl = catalogUrl.replace(/\/?$/, '/') + gameName;
    document.getElementById('bbPubGameUrlInput').value = gameUrl;
    document.getElementById('bbPubGameUrlLink').href   = gameUrl;
    gameEl.style.display = '';
  } else if (gameEl) { gameEl.style.display = 'none'; }
  document.getElementById('bbPublishOverlay').classList.add('open');
}
function closePublishDialog() { document.getElementById('bbPublishOverlay').classList.remove('open'); }
function bbCopyUrl(inputId) {
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
function bbVideoPhClick(e, el) {
  if (el.classList.contains('url-entry')) return; // already open
  el.classList.add('url-entry');
  el.querySelector('.bb-video-ph-icon').style.display = 'none';
  el.querySelector('.bb-video-ph-label').style.display = 'none';
  var urlWrap = document.createElement('div');
  urlWrap.className = 'bb-video-ph-url-wrap';
  urlWrap.innerHTML =
    '<input id="bbVideoUrlInline" type="url" placeholder="Paste YouTube or video URL…" />'
    + '<button onclick="bbVideoUrlConfirm()">Add</button>';
  var cancel = document.createElement('div');
  cancel.className = 'bb-video-ph-cancel';
  cancel.textContent = 'Cancel';
  cancel.onclick = function() { bbVideoUrlCancel(el); };
  el.appendChild(urlWrap);
  el.appendChild(cancel);
  setTimeout(function() { var inp = document.getElementById('bbVideoUrlInline'); if (inp) inp.focus(); }, 30);
  urlWrap.querySelector('input').addEventListener('keydown', function(ev) {
    if (ev.key === 'Enter') { ev.preventDefault(); bbVideoUrlConfirm(); }
    if (ev.key === 'Escape') { bbVideoUrlCancel(el); }
  });
}
function bbVideoUrlCancel(el) {
  el.classList.remove('url-entry');
  el.querySelector('.bb-video-ph-icon').style.display = '';
  el.querySelector('.bb-video-ph-label').style.display = '';
  var wrap = el.querySelector('.bb-video-ph-url-wrap');
  var cancel = el.querySelector('.bb-video-ph-cancel');
  if (wrap)   el.removeChild(wrap);
  if (cancel) el.removeChild(cancel);
}
function bbVideoUrlConfirm() {
  var inp = document.getElementById('bbVideoUrlInline');
  if (!inp) return;
  var url = inp.value.trim();
  if (!url) return;
  _ed.video_url = url;
  bbVideoApplyUrl(url);
}
function bbVideoApplyUrl(url) {
  // Compute embed URL (YouTube) or direct URL
  var embed = '';
  var yt = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/shorts\/)([A-Za-z0-9_\-]+)/);
  if (yt) embed = 'https://www.youtube.com/embed/' + yt[1];
  var section = document.getElementById('bbVideoPlaceholder');
  if (!section) section = document.getElementById('bbVideoSection');
  if (!section) return;
  section.id = 'bbVideoSection';
  section.className = 'bb-section';
  var delBtn = '<button class="bb-video-del-btn" onclick="bbVideoRemoveClick()" title="Remove video">✕</button>';
  if (embed) {
    section.innerHTML = '<div class="bb-section-label">Watch</div>'
      + '<div class="bb-video-wrap" id="videoWrap">'
      + '<iframe id="videoFrame" src="' + embed.replace(/"/g,'&quot;') + '" allow="autoplay; encrypted-media" allowfullscreen></iframe>'
      + delBtn + '</div>';
  } else {
    section.innerHTML = '<div class="bb-section-label">Watch</div>'
      + '<div class="bb-video-wrap" id="videoWrap">'
      + '<video id="videoFrame" src="' + url.replace(/"/g,'&quot;') + '" controls style="position:absolute;inset:0;width:100%;height:100%;object-fit:contain;background:#000;"></video>'
      + delBtn + '</div>';
  }
}
function bbSyncVideoSection(url) {
  var placeholder = document.getElementById('bbVideoPlaceholder');
  var section     = document.getElementById('bbVideoSection');
  if (!url) {
    // Clear → restore placeholder, hide live section
    if (section) {
      section.id        = 'bbVideoPlaceholder';
      section.className = 'bb-section bb-ph-section';
      section.innerHTML =
        '<div class="bb-section-label">Watch</div>'
        + '<div class="bb-video-ph-inner"'
        + ' onclick="bbVideoPhClick(event,this)"'
        + ' ondragover="bbVideoDragOver(event,this)"'
        + ' ondragleave="bbVideoDragLeave(event,this)"'
        + ' ondrop="bbVideoDrop(event,this)">'
        + '<div class="bb-video-ph-icon" style="font-size:3rem;">▶</div>'
        + '<div class="bb-video-ph-label" style="font-family:\'DINBlack\',sans-serif;font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;color:#fff;">Drop a video file or click to add URL</div>'
        + '</div>';
    }
  } else {
    bbVideoApplyUrl(url);
    bbRenderVideoAdminOverlay();
  }
}
function bbRenderVideoAdminOverlay() {
  var section = document.getElementById('bbVideoSection');
  if (!section) return;
  // Remove any existing overlay
  var old = section.querySelector('.bb-video-admin-bar');
  if (old) old.parentNode.removeChild(old);
  var bar = document.createElement('div');
  bar.className = 'bb-video-admin-bar';
  bar.innerHTML =
    '<button onclick="bbVideoChangeClick()" title="Change video">✏ Change</button>'
    + '<button onclick="bbVideoRemoveClick()" title="Remove video" style="color:#f87171;">✕ Remove</button>';
  section.appendChild(bar);
}
function bbVideoRemoveClick() {
  if (!confirm('Remove this video?')) return;
  _ed.video_url = '';
  bbSyncVideoSection('');
}
function bbVideoDragOver(e, el) {
  e.preventDefault();
  el.classList.add('drag-over');
}
function bbVideoDragLeave(e, el) {
  e.preventDefault();
  el.classList.remove('drag-over');
}
function bbVideoDrop(e, el) {
  e.preventDefault();
  el.classList.remove('drag-over');
  var file = e.dataTransfer.files && e.dataTransfer.files[0];
  if (!file) return;
  if (!file.type.startsWith('video/')) {
    bbVideoPhClick(e, el);
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
        bbVideoApplyUrl(r.url);
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
  document.getElementById('bbedStatus').value       = _ed.status       || '';
  document.getElementById('bbedAvailability').value = _ed.availability || '';
  document.getElementById('bbedDesigner1').value    = _ed.designer1    || '';
  document.getElementById('bbedDesigner2').value    = _ed.designer2    || '';
  document.getElementById('bbedDesigner3').value    = _ed.designer3    || '';
  document.getElementById('bbedDesigner4').value    = _ed.designer4    || '';
  document.getElementById('bbedArtist1').value      = _ed.artist1      || '';
  document.getElementById('bbedArtist2').value      = _ed.artist2      || '';
  document.getElementById('bbedArtist3').value      = _ed.artist3      || '';
  document.getElementById('bbedArtist4').value      = _ed.artist4      || '';
  document.getElementById('bbedDateStarted').value   = _ed.date_started   || '';
  document.getElementById('bbedDateSigned').value    = _ed.date_signed    || '';
  document.getElementById('bbedDatePublished').value = _ed.date_published || '';
  document.getElementById('bbedHeaderImageUrl').value = _ed.header_image_url || '';
  document.getElementById('bbedCoverUrl').value  = _ed.cover_url  || '';
  document.getElementById('bbedImageUrl').value  = _ed.image_url  || '';
  document.getElementById('bbedVideoUrl').value  = _ed.video_url  || '';
  document.getElementById('bbedPageUrl').value   = _ed.page_url   || '';
  document.getElementById('bbedSellsheet').value = _ed.sellsheet  || '';
  document.getElementById('bbedRulesUrl').value  = _ed.rules_url  || '';
  document.getElementById('bbedPlayUrl').value   = _ed.play_url   || '';
  document.getElementById('bbedPrintUrl').value  = _ed.print_url  || '';
  document.getElementById('bbedOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeEditDetails() {
  document.getElementById('bbedOverlay').classList.remove('open');
  document.body.style.overflow = '';
}
function applyEditDetails() {
  _ed.status         = document.getElementById('bbedStatus').value.trim();
  _ed.availability   = document.getElementById('bbedAvailability').value.trim();
  _ed.designer1      = document.getElementById('bbedDesigner1').value.trim();
  _ed.designer2      = document.getElementById('bbedDesigner2').value.trim();
  _ed.designer3      = document.getElementById('bbedDesigner3').value.trim();
  _ed.designer4      = document.getElementById('bbedDesigner4').value.trim();
  _ed.artist1        = document.getElementById('bbedArtist1').value.trim();
  _ed.artist2        = document.getElementById('bbedArtist2').value.trim();
  _ed.artist3        = document.getElementById('bbedArtist3').value.trim();
  _ed.artist4        = document.getElementById('bbedArtist4').value.trim();
  bbInitArtistInputs();
  _ed.date_started   = document.getElementById('bbedDateStarted').value.trim();
  _ed.date_signed    = document.getElementById('bbedDateSigned').value.trim();
  _ed.date_published = document.getElementById('bbedDatePublished').value.trim();
  _ed.header_image_url = document.getElementById('bbedHeaderImageUrl').value.trim();
  _ed.cover_url      = document.getElementById('bbedCoverUrl').value.trim();
  _ed.image_url      = document.getElementById('bbedImageUrl').value.trim();
  _ed.video_url      = document.getElementById('bbedVideoUrl').value.trim();
  bbSyncVideoSection(_ed.video_url);
  _ed.page_url       = document.getElementById('bbedPageUrl').value.trim();
  _ed.sellsheet      = document.getElementById('bbedSellsheet').value.trim();
  _ed.rules_url      = document.getElementById('bbedRulesUrl').value.trim();
  _ed.play_url       = document.getElementById('bbedPlayUrl').value.trim();
  _ed.print_url      = document.getElementById('bbedPrintUrl').value.trim();
  closeEditDetails();
  // Sync back to inline stat inputs
  bbInitStatInputs();
  // Update status badge live
  var badge = document.getElementById('bbStatusBadge');
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
function bbParseReviews(text) {
  return text.split('\n').map(function(line) {
    line = line.trim(); if (!line) return null;
    var idx = line.indexOf(' | ');
    return idx !== -1
      ? { attr: line.slice(0, idx).trim(), quote: line.slice(idx + 3).trim() }
      : { attr: line, quote: '' };
  }).filter(Boolean);
}
function bbParseFaqs(text) {
  return text.split('\n').map(function(line) {
    line = line.trim(); if (!line) return null;
    var idx = line.indexOf(' | ');
    return idx !== -1
      ? { q: line.slice(0, idx).trim(), a: line.slice(idx + 3).trim() }
      : { q: line, a: '' };
  }).filter(Boolean);
}
function bbInitInlineEdits() {
  var compEl = document.getElementById('bbCompEdit');
  if (compEl) {
    compEl.value = (_ed.components || []).filter(Boolean).join('\n');
    compEl.oninput = function() {
      _ed.components = this.value.split('\n').map(function(s){return s.trim();}).filter(Boolean);
    };
  }
  bbInitReviewTicker();
  bbInitVrevGrid();
  bbBuildFaqEditor(_ed.faqs || []);
  bbInitDesignerInputs();
  bbInitArtistInputs();
  bbInitStatInputs();
}

function bbInitStatInputs() {
  var map = {
    bbsiMinPlayers:  'min_players',  bbsiMaxPlayers:  'max_players',
    bbsiMinPlaytime: 'min_playtime', bbsiMaxPlaytime: 'max_playtime',
    bbsiWeight:      'weight'
  };
  Object.keys(map).forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.value = _ed[map[id]] || '';
  });
}

function bbInitDesignerInputs() {
  var wrap = document.getElementById('bbDesignerInputs');
  if (!wrap) return;
  wrap.innerHTML = '';
  // Start with existing values; always show at least 1 input
  var vals = [_ed.designer1||'', _ed.designer2||'', _ed.designer3||'', _ed.designer4||''];
  // Show inputs up to last filled + 1 (but at least 1, at most 4)
  var lastFilled = -1;
  vals.forEach(function(v, i){ if (v) lastFilled = i; });
  var showCount = Math.min(lastFilled + 2, 4);
  if (showCount < 1) showCount = 1;
  for (var i = 0; i < showCount; i++) { bbAddDesignerInput(wrap, vals[i], i); }
}

function bbAddDesignerInput(wrap, value, idx) {
  var inp = document.createElement('input');
  inp.type = 'text';
  inp.className = 'bb-designer-input';
  inp.value = value || '';
  inp.placeholder = idx === 0 ? 'Designer name' : '+ name';
  inp.dataset.idx = idx;
  inp.oninput = function() {
    var keys = ['designer1','designer2','designer3','designer4'];
    _ed[keys[idx]] = this.value.trim();
    // If this is the last visible input and now has a value, add a new one
    var allInputs = wrap.querySelectorAll('.bb-designer-input');
    if (this.value && parseInt(this.dataset.idx) === allInputs.length - 1 && allInputs.length < 4) {
      var nextIdx = allInputs.length;
      var nextVals = [_ed.designer1||'', _ed.designer2||'', _ed.designer3||'', _ed.designer4||''];
      bbAddDesignerInput(wrap, nextVals[nextIdx] || '', nextIdx);
    }
  };
  wrap.appendChild(inp);
}

function bbInitArtistInputs() {
  var wrap = document.getElementById('bbArtistInputs');
  if (!wrap) return;
  wrap.innerHTML = '';
  var vals = [_ed.artist1||'', _ed.artist2||'', _ed.artist3||'', _ed.artist4||''];
  var lastFilled = -1;
  vals.forEach(function(v, i){ if (v) lastFilled = i; });
  var showCount = Math.min(lastFilled + 2, 4);
  if (showCount < 1) showCount = 1;
  for (var i = 0; i < showCount; i++) { bbAddArtistInput(wrap, vals[i], i); }
}

function bbAddArtistInput(wrap, value, idx) {
  var inp = document.createElement('input');
  inp.type = 'text';
  inp.className = 'bb-designer-input';
  inp.value = value || '';
  inp.placeholder = idx === 0 ? 'Artist name' : '+ name';
  inp.dataset.idx = idx;
  inp.oninput = function() {
    var keys = ['artist1','artist2','artist3','artist4'];
    _ed[keys[idx]] = this.value.trim();
    var allInputs = wrap.querySelectorAll('.bb-designer-input');
    if (this.value && parseInt(this.dataset.idx) === allInputs.length - 1 && allInputs.length < 4) {
      var nextIdx = allInputs.length;
      var nextVals = [_ed.artist1||'', _ed.artist2||'', _ed.artist3||'', _ed.artist4||''];
      bbAddArtistInput(wrap, nextVals[nextIdx] || '', nextIdx);
    }
  };
  wrap.appendChild(inp);
}

function bbAutoResize(ta) {
  ta.style.height = 'auto';
  ta.style.height = ta.scrollHeight + 'px';
}

function bbBuildReviewEditor(reviews) {
  var list = document.getElementById('bbReviewsList');
  if (!list) return;
  list.innerHTML = '';
  var pairs = reviews.filter(function(r){ return r.attr || r.quote; });
  pairs.push({attr:'', quote:''});
  // attr = review text (col B), quote = source/attribution (col C)
  pairs.forEach(function(r, idx) { bbAddReviewPair(list, r.attr, r.quote, idx === pairs.length - 1); });
}

function bbAddReviewPair(list, quote, source, isLast) {
  var pair = document.createElement('div');
  pair.className = 'bb-faq-pair';

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
      document.querySelectorAll('#bbReviewsList .bb-faq-pair')
    ).map(function(p) {
      return {
        attr:  p.querySelector('.faq-q').value.trim(),  // review text (top)
        quote: p.querySelector('.faq-a').value.trim()   // source/attribution (bottom)
      };
    }).filter(function(r){ return r.attr || r.quote; });
  }

  function maybeAddPair() {
    var pairs = document.querySelectorAll('#bbReviewsList .bb-faq-pair');
    var last  = pairs[pairs.length - 1];
    if (pair === last && (qta.value.trim() || sta.value.trim())) {
      bbAddReviewPair(list, '', '', true);
    }
  }

  qta.oninput = function() { bbAutoResize(this); syncReviews(); maybeAddPair(); };
  sta.oninput = function() { bbAutoResize(this); syncReviews(); maybeAddPair(); };

  pair.appendChild(qta);
  pair.appendChild(sta);
  list.appendChild(pair);
  setTimeout(function() { bbAutoResize(qta); bbAutoResize(sta); }, 0);
}

function esc(s) {
  return String(s == null ? '' : s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function bbYtThumb(url) {
  var m = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/shorts\/)([A-Za-z0-9_\-]+)/);
  return m ? 'https://img.youtube.com/vi/' + m[1] + '/mqdefault.jpg' : '';
}

function bbInitVrevGrid() {
  var grid = document.getElementById('bbVrevGrid');
  if (!grid) return;
  grid.innerHTML = '';
  var videos = _ed.videos || [];
  videos.forEach(function(v, i) { if (v.url) bbAddVrevCard(grid, v, i); });
  // Always show enough empty tiles: 3 total when fewer than 3 filled, else 1
  var emptyCount = Math.max(1, 3 - videos.length);
  for (var i = 0; i < emptyCount; i++) bbAddVrevEmptyTile(grid);
}

function bbAddVrevCard(grid, v, idx) {
  var tile = document.createElement('div');
  tile.className = 'bb-vrev-tile';
  tile.dataset.idx = idx;
  var thumb = bbYtThumb(v.url);
  tile.innerHTML = (thumb ? '<img src="' + esc(thumb) + '" loading="lazy">' : '<div style="width:100%;height:100%;background:#222;border-radius:6px;"></div>')
    + '<div class="bb-vrev-tile-label">' + esc(v.name || v.url) + '</div>'
    + '<button class="bb-vrev-tile-del" title="Remove" onmousedown="bbVrevRemove(event,' + idx + ')">✕</button>';
  tile.onclick = function(e) { if (!e.target.classList.contains('bb-vrev-tile-del')) bbVrevOpenInlineTile(tile, idx); };
  grid.insertBefore(tile, grid.querySelector('.bb-vrev-empty') || null);
}

function bbAddVrevEmptyTile(grid) {
  var tile = document.createElement('div');
  tile.className = 'bb-vrev-empty';
  tile.innerHTML = '<div class="bb-vrev-add-icon">▶</div><div class="bb-vrev-add-label">Add Video</div>';
  tile.onclick = function() { bbVrevOpenInlineTile(tile, -1); };
  grid.appendChild(tile);
}

function bbVrevOpenInlineTile(tile, idx) {
  // Close any other open inline editor first
  var grid = document.getElementById('bbVrevGrid');
  if (grid) {
    grid.querySelectorAll('.bb-vrev-editing').forEach(function(t) {
      if (t !== tile) bbInitVrevGrid();
    });
  }
  var v = idx >= 0 ? ((_ed.videos || [])[idx] || {}) : {};
  tile.classList.add('bb-vrev-editing');
  tile.onclick = null;
  tile.innerHTML =
    '<input class="bb-vrev-inp" type="url" placeholder="YouTube or video URL…" value="' + esc(v.url || '') + '">'
    + '<input class="bb-vrev-inp" type="text" placeholder="Source / reviewer (optional)" value="' + esc(v.name || '') + '">'
    + '<div class="bb-vrev-inline-btns">'
    + '<button class="bb-vrev-btn-save" onclick="bbVrevSaveInline(this,'+idx+')">Save</button>'
    + '<button class="bb-vrev-btn-cancel" onclick="bbInitVrevGrid()">Cancel</button>'
    + '</div>';
  var inp = tile.querySelector('input[type="url"]');
  if (inp) setTimeout(function() { inp.focus(); }, 20);
  tile.querySelector('input[type="url"]').addEventListener('keydown', function(ev) {
    if (ev.key === 'Enter') { ev.preventDefault(); tile.querySelector('.bb-vrev-btn-save').click(); }
    if (ev.key === 'Escape') { bbInitVrevGrid(); }
  });
}

function bbVrevSaveInline(btn, idx) {
  var tile = btn.closest('.bb-vrev-editing');
  if (!tile) return;
  var inputs = tile.querySelectorAll('.bb-vrev-inp');
  var url  = inputs[0] ? inputs[0].value.trim() : '';
  var name = inputs[1] ? inputs[1].value.trim() : '';
  if (!url) { bbInitVrevGrid(); return; }
  if (!_ed.videos) _ed.videos = [];
  if (idx >= 0) { _ed.videos[idx] = {url: url, name: name}; }
  else          { _ed.videos.push({url: url, name: name}); }
  bbInitVrevGrid();
}

function bbVrevRemove(e, idx) {
  e.stopPropagation();
  if (!_ed.videos) return;
  _ed.videos.splice(idx, 1);
  bbInitVrevGrid();
}

var _buyDragIdx = -1;
function bbMakeBuyCtaEl(b, i, isPlaceholder) {
  var w = document.createElement('span');
  w.className = 'bb-buy-cta-wrap' + (isPlaceholder ? ' bb-buy-cta-ph' : '');

  // Drag-and-drop reordering (non-placeholder only)
  if (!isPlaceholder) {
    w.draggable = true; w.style.cursor = 'grab';
    w.addEventListener('dragstart', function(e) {
      _buyDragIdx = i; e.dataTransfer.effectAllowed = 'move';
      setTimeout(function() { w.style.opacity = '.4'; }, 0);
    });
    w.addEventListener('dragend', function() {
      w.style.opacity = '';
      document.querySelectorAll('.bb-rl-drop-target').forEach(function(el) { el.classList.remove('bb-rl-drop-target'); });
    });
    w.addEventListener('dragover', function(e) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; w.classList.add('bb-rl-drop-target'); });
    w.addEventListener('dragleave', function() { w.classList.remove('bb-rl-drop-target'); });
    w.addEventListener('drop', function(e) {
      e.preventDefault(); w.classList.remove('bb-rl-drop-target');
      if (_buyDragIdx < 0 || _buyDragIdx === i) return;
      var moved = _ed.buy_urls.splice(_buyDragIdx, 1)[0];
      _ed.buy_urls.splice(i, 0, moved);
      bbInitBuyCtas();
    });
  }

  var inner = document.createElement('span');
  inner.className = 'bb-buy-cta-inner filled';

  // Editable label — click selects all so user can type replacement immediately
  var lbl = document.createElement('span');
  lbl.className = 'bb-buy-cta-lbl';
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
  urlInp.value = b.url || ''; urlInp.className = 'bb-buy-url-inline';
  urlInp.style.display = 'none';
  urlInp.onclick = function(e) { e.stopPropagation(); };

  // Confirm button (hidden until URL input shown)
  var okBtn = document.createElement('button');
  okBtn.className = 'bb-buy-url-ok-inline'; okBtn.textContent = '✓'; okBtn.style.display = 'none';

  // Link icon button
  var linkBtn = document.createElement('button');
  linkBtn.className = 'bb-buy-link-icon';
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
    bbInitBuyCtas();
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
    del.className = 'bb-buy-cta-del'; del.textContent = '✕'; del.title = 'Remove';
    del.onmousedown = function(e) { e.preventDefault(); _ed.buy_urls.splice(i, 1); bbInitBuyCtas(); };
    w.appendChild(del);
  }

  return w;
}

function bbInitBuyCtas() {
  var wrap = document.getElementById('bbBuyCtas');
  if (!wrap) return;
  wrap.innerHTML = '';
  wrap.style.display = 'contents';
  document.querySelectorAll('.bb-buy-pub').forEach(function(el) { el.style.display = 'none'; });
  if (!_ed.buy_urls) _ed.buy_urls = (GAME_DATA.buy_urls || []).slice();
  _ed.buy_urls.forEach(function(b, i) { if (b.url || b.label) wrap.appendChild(bbMakeBuyCtaEl(b, i, false)); });
  wrap.appendChild(bbMakeBuyCtaEl({label: 'ADD BUY LINK', url: ''}, -1, true));
}

var _rlDragIdx = -1;
function bbMakeResLinkEl(rl, i, isPlaceholder) {
  var w = document.createElement('span');
  w.className = 'bb-buy-cta-wrap' + (isPlaceholder ? ' bb-buy-cta-ph' : '');
  // Drag-and-drop reordering (non-placeholder only)
  if (!isPlaceholder) {
    w.draggable = true;
    w.style.cursor = 'grab';
    w.addEventListener('dragstart', function(e) {
      _rlDragIdx = i;
      e.dataTransfer.effectAllowed = 'move';
      setTimeout(function() { w.style.opacity = '.4'; }, 0);
    });
    w.addEventListener('dragend', function() { w.style.opacity = ''; document.querySelectorAll('.bb-rl-drop-target').forEach(function(el) { el.classList.remove('bb-rl-drop-target'); }); });
    w.addEventListener('dragover', function(e) { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; w.classList.add('bb-rl-drop-target'); });
    w.addEventListener('dragleave', function() { w.classList.remove('bb-rl-drop-target'); });
    w.addEventListener('drop', function(e) {
      e.preventDefault(); w.classList.remove('bb-rl-drop-target');
      if (_rlDragIdx < 0 || _rlDragIdx === i) return;
      var arr = _ed.resource_links;
      var moved = arr.splice(_rlDragIdx, 1)[0];
      arr.splice(i, 0, moved);
      bbInitResLinks();
    });
  }

  var inner = document.createElement('span');
  inner.className = 'bb-buy-cta-inner'; // outlined, not filled

  var lbl = document.createElement('span');
  lbl.className = 'bb-buy-cta-lbl';
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
  urlInp.value = rl.url || ''; urlInp.className = 'bb-buy-url-inline';
  urlInp.style.display = 'none';
  urlInp.onclick = function(e) { e.stopPropagation(); };

  var okBtn = document.createElement('button');
  okBtn.className = 'bb-buy-url-ok-inline'; okBtn.textContent = '✓'; okBtn.style.display = 'none';

  var linkBtn = document.createElement('button');
  linkBtn.className = 'bb-buy-link-icon';
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
    bbInitResLinks();
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
    del.className = 'bb-buy-cta-del'; del.textContent = '✕'; del.title = 'Remove';
    del.onmousedown = function(e) { e.preventDefault(); _ed.resource_links.splice(i, 1); bbInitResLinks(); };
    w.appendChild(del);
  }
  return w;
}

function bbInitResLinks() {
  var wrap = document.getElementById('bbResLinks');
  if (!wrap) return;
  wrap.innerHTML = '';
  wrap.style.display = 'flex';
  document.querySelectorAll('#bbResourcesSection .bb-display-content').forEach(function(el) { el.style.display = 'none'; });
  if (!_ed.resource_links) _ed.resource_links = (GAME_DATA.resource_links || []).slice();
  _ed.resource_links.forEach(function(rl, i) {
    wrap.appendChild(bbMakeResLinkEl(rl, i, false));
  });
  wrap.appendChild(bbMakeResLinkEl({label: 'ADD RESOURCE', url: ''}, -1, true));
}

function bbBuildFaqEditor(faqs) {
  var list = document.getElementById('bbFaqsList');
  if (!list) return;
  list.innerHTML = '';
  var pairs = faqs.filter(function(f){ return f.q || f.a; });
  // Always ensure at least one empty pair at end
  pairs.push({q:'', a:''});
  pairs.forEach(function(f, idx) { bbAddFaqPair(list, f.q, f.a, idx === pairs.length - 1); });
}

function bbAddFaqPair(list, q, a, isLast) {
  var pair = document.createElement('div');
  pair.className = 'bb-faq-pair';

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
      document.querySelectorAll('#bbFaqsList .bb-faq-pair')
    ).map(function(p) {
      var q2 = p.querySelector('.faq-q').value.trim();
      var aw = p._bbGetAnswer ? p._bbGetAnswer() : (p.querySelector('.faq-a') ? p.querySelector('.faq-a').value.trim() : '');
      return { q: q2, a: aw };
    }).filter(function(f){ return f.q || f.a; });
  }
  pair._bbGetAnswer = getAnswerValue;

  function maybeAddPair() {
    var pairs = document.querySelectorAll('#bbFaqsList .bb-faq-pair');
    var last  = pairs[pairs.length - 1];
    if (pair === last && (qta.value.trim() || getAnswerValue())) {
      bbAddFaqPair(list, '', '', true);
    }
  }

  qta.oninput = function() { bbAutoResize(this); syncFaqs(); maybeAddPair(); };
  ata.oninput = function() {
    imgBtn.style.display = this.value ? 'none' : '';
    bbAutoResize(this); syncFaqs(); maybeAddPair();
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

  setTimeout(function() { bbAutoResize(qta); bbAutoResize(ata); }, 0);
}

// ── Save game ────────────────────────────────────────────────────────────────
// ── Image upload helpers ──────────────────────────────────────────────────────
var _bbFileCallback = null;

function bbPickImage(callback) {
  _bbFileCallback = callback;
  var inp = document.getElementById('bbFileInput');
  inp.value = '';
  inp.click();
}

function bbHandleFileInput(e) {
  var file = e.target.files[0];
  if (!file || !_bbFileCallback) return;
  // Determine loading zone — apply it NOW (after file is chosen, not on click)
  var zone = null;
  if (_bbFileCallback === bbSetCover) {
    zone = document.getElementById('bbHero');
    zone.classList.add('bb-cover-loading');
  } else {
    zone = document.getElementById('bbGalleryAddBtn');
    if (zone) zone.classList.add('bb-uploading');
    var featDiv = document.getElementById('bbGalleryFeatured');
    if (featDiv) featDiv.classList.add('bb-uploading');
  }
  var cb = _bbFileCallback;
  bbUploadImage(file, cb, zone);
  _bbFileCallback = null;
}

function bbUploadImage(file, callback, zoneEl) {
  if (zoneEl) zoneEl.classList.add('bb-uploading');
  var featDiv = document.getElementById('bbGalleryFeatured');
  var fd = new FormData();
  fd.append('id', SHEET_ID);
  fd.append('file', file);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/uploadImage.php');
  xhr.onload = function() {
    if (zoneEl) { zoneEl.classList.remove('bb-uploading'); zoneEl.classList.remove('bb-cover-loading'); }
    var r;
    try { r = JSON.parse(xhr.responseText); } catch(e) { r = {error: 'parse error'}; }
    if (r && r.ok && r.url) {
      callback(r.url, zoneEl);
    } else {
      alert('Image upload failed: ' + (r && r.error || 'unknown'));
    }
  };
  xhr.onerror = function() {
    if (zoneEl) { zoneEl.classList.remove('bb-uploading'); zoneEl.classList.remove('bb-cover-loading'); }
    if (featDiv) featDiv.classList.remove('bb-uploading');
    alert('Image upload failed: network error');
  };
  xhr.send(fd);
}

// ── Edit Details image pick (fills URL input; uses its own file input to avoid gallery conflicts) ─
var _bbedPickTarget = null;
function bbedPickImage(inputId) {
  _bbedPickTarget = inputId;
  var inp = document.getElementById('bbedFileInput');
  inp.value = '';
  inp.click();
}
function bbedHandleFileInput(e) {
  var file = e.target.files[0];
  if (!file || !_bbedPickTarget) return;
  var inputId = _bbedPickTarget;
  _bbedPickTarget = null;
  var btn = document.querySelector('[onclick="bbedPickImage(\'' + inputId + '\')"]');
  if (btn) btn.classList.add('bbed-uploading');
  bbUploadImage(file, function(url) {
    if (btn) btn.classList.remove('bbed-uploading');
    var inp = document.getElementById(inputId);
    if (inp) inp.value = url;
    // Also update the hero preview if this is the header image
    if (inputId === 'bbedHeaderImageUrl') {
      _ed.header_image_url = url;
      var bg = document.getElementById('bbHeroBg');
      if (bg) bg.style.backgroundImage = 'url(' + url + ')';
      var hero = document.getElementById('bbHero');
      if (hero) hero.classList.remove('no-cover');
    }
  }, null);
}

// ── Hero image (HeaderImage in [{game}] tab) ──────────────────────────────────
function bbSetCover(url) {
  _ed.header_image_url = url;
  var hero = document.getElementById('bbHero');
  var bg   = document.getElementById('bbHeroBg');
  if (hero) hero.classList.remove('bb-cover-loading');
  if (bg)   bg.style.backgroundImage = 'url(' + url + ')';
  if (hero) hero.classList.remove('no-cover');
  bbShowUploadToast('Hero image ready — click Save Changes');
}

// ── Gallery ───────────────────────────────────────────────────────────────────
var _bbGalleryIdx  = 0;
var _bbGalleryTimer = null;
var _bbDragIdx = -1;

function bbRenderGallery() {
  var imgs = _ed.gallery || [];
  var section = document.getElementById('bbGallerySection');
  var featEl  = document.getElementById('bbGalleryFeaturedImg');
  var thumbsEl = document.getElementById('bbGalleryThumbs');
  if (!section || !featEl || !thumbsEl) return;

  // Show/hide section
  var viewer = document.getElementById('bbGalleryViewer');
  if (!imgs.length) {
    section.classList.add('bb-sect-empty');
    if (viewer) viewer.classList.add('bb-gallery-empty');
  } else {
    section.classList.remove('bb-sect-empty', 'bb-sect-hidden');
    if (viewer) viewer.classList.remove('bb-gallery-empty');
  }

  // When empty in admin mode, make featured area a clickable add target
  var featuredDiv = document.getElementById('bbGalleryFeatured');
  if (featuredDiv) {
    featuredDiv.onclick = !imgs.length ? function() { bbPickGalleryImage(); } : null;
  }

  // Clamp active index
  if (_bbGalleryIdx >= imgs.length) _bbGalleryIdx = Math.max(0, imgs.length - 1);

  // Set featured image src
  if (imgs.length) {
    if (imgs[_bbGalleryIdx]) featEl.src = imgs[_bbGalleryIdx];
    featEl.style.display = '';
  } else {
    featEl.style.display = 'none';
  }

  // Rebuild thumbs
  // Keep the add button, rebuild everything before it
  var addBtn = document.getElementById('bbGalleryAddBtn');
  // Remove all thumb elements
  Array.prototype.slice.call(thumbsEl.children).forEach(function(el) {
    if (el.id !== 'bbGalleryAddBtn') thumbsEl.removeChild(el);
  });
  imgs.forEach(function(url, i) {
    var div = document.createElement('div');
    div.className = 'bb-gallery-thumb' + (i === _bbGalleryIdx ? ' bb-gt-active' : '');
    div.dataset.idx = i;
    if (document.body.classList.contains('bb-admin-mode')) {
      div.draggable = true;
    }
    var img = document.createElement('img'); img.src = url; img.loading = 'lazy';
    var del = document.createElement('button');
    del.className = 'bb-gt-del'; del.textContent = '✕'; del.title = 'Remove';
    (function(idx) {
      div.onclick = function() { bbGallerySelect(idx); };
      del.onclick = function(e) { e.stopPropagation(); bbGalleryDelete(idx); };
      div.addEventListener('dragstart', function(e) {
        _bbDragIdx = idx;
        div.classList.add('bb-gt-dragging');
        e.dataTransfer.effectAllowed = 'move';
      });
      div.addEventListener('dragend', function() { div.classList.remove('bb-gt-dragging'); _bbDragIdx = -1; });
      div.addEventListener('dragover', function(e) { e.preventDefault(); div.classList.add('bb-gt-drag-over'); });
      div.addEventListener('dragleave', function() { div.classList.remove('bb-gt-drag-over'); });
      div.addEventListener('drop', function(e) {
        e.preventDefault(); div.classList.remove('bb-gt-drag-over');
        if (_bbDragIdx === -1 || _bbDragIdx === idx) return;
        var arr = _ed.gallery || [];
        var moved = arr.splice(_bbDragIdx, 1)[0];
        arr.splice(idx, 0, moved);
        _ed.gallery = arr;
        _ed._galleryDirty = true;
        _bbGalleryIdx = idx;
        bbRenderGallery();
      });
    })(i);
    div.appendChild(img); div.appendChild(del);
    thumbsEl.insertBefore(div, addBtn);
  });
}

function bbGallerySelect(idx) {
  var imgs = _ed.gallery || [];
  if (idx < 0 || idx >= imgs.length) return;
  _bbGalleryIdx = idx;
  var featEl = document.getElementById('bbGalleryFeaturedImg');
  if (featEl && featEl.getAttribute('src') !== imgs[idx]) {
    featEl.classList.add('bb-gf-fade');
    featEl.style.display = '';
    setTimeout(function() {
      featEl.src = imgs[idx];
      featEl.onload = function() { featEl.classList.remove('bb-gf-fade'); featEl.onload = null; };
    }, 180);
  }
  // Update active thumb
  document.querySelectorAll('#bbGalleryThumbs .bb-gallery-thumb').forEach(function(el, i) {
    el.classList.toggle('bb-gt-active', i === idx);
  });
}

function bbGalleryDelete(idx) {
  if (!_ed.gallery || idx < 0 || idx >= _ed.gallery.length) return;
  _ed.gallery.splice(idx, 1);
  _ed._galleryDirty = true;
  if (_bbGalleryIdx >= _ed.gallery.length) _bbGalleryIdx = Math.max(0, _ed.gallery.length - 1);
  bbRenderGallery();
  bbShowUploadToast('Image removed — click Save Changes');
}

function bbGalleryDeleteFeatured() {
  bbGalleryDelete(_bbGalleryIdx);
}

function bbGalleryAutoAdvance() {
  if (_bbGalleryTimer) clearInterval(_bbGalleryTimer);
  _bbGalleryTimer = setInterval(function() {
    var imgs = _ed.gallery || [];
    if (imgs.length > 1) bbGallerySelect((_bbGalleryIdx + 1) % imgs.length);
  }, 4000);
}

function bbPickGalleryImage() {
  var addBtn = document.getElementById('bbGalleryAddBtn');
  var featDiv = document.getElementById('bbGalleryFeatured');
  _bbFileCallback = function(url) {
    if (addBtn) addBtn.classList.remove('bb-uploading');
    if (featDiv) featDiv.classList.remove('bb-uploading');
    if (!_ed.gallery) _ed.gallery = [];
    _ed.gallery.push(url);
    _ed._galleryDirty = true;
    _bbGalleryIdx = _ed.gallery.length - 1;
    bbRenderGallery();
    bbShowUploadToast('Image added — click Save Changes');
  };
  var inp = document.getElementById('bbFileInput');
  inp.value = '';
  inp.click();
}

function bbGalleryDropImage(e) {
  var file = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
  if (!file || !file.type.startsWith('image/')) return;
  var addBtn = document.getElementById('bbGalleryAddBtn');
  var featDiv = document.getElementById('bbGalleryFeatured');
  if (featDiv) featDiv.classList.add('bb-uploading');
  bbUploadImage(file, function(url) {
    if (addBtn) addBtn.classList.remove('bb-uploading');
    if (featDiv) featDiv.classList.remove('bb-uploading');
    if (!_ed.gallery) _ed.gallery = [];
    _ed.gallery.push(url);
    _ed._galleryDirty = true;
    _bbGalleryIdx = _ed.gallery.length - 1;
    bbRenderGallery();
    bbShowUploadToast('Image added — click Save Changes');
  }, addBtn);
}

function bbShowUploadToast(msg) {
  var toast = document.getElementById('bbSaveToast');
  if (!toast) return;
  toast.textContent = '✓ ' + msg;
  toast.classList.add('visible');
  setTimeout(function() { toast.classList.remove('visible'); }, 3000);
}

// ── Hero drag-drop ────────────────────────────────────────────────────────────
(function() {
  if (!document.body.classList.contains('bb-admin-mode')) return;
  var hero = document.getElementById('bbHero');
  if (!hero) return;
  hero.addEventListener('dragover', function(e) {
    e.preventDefault();
    hero.classList.add('bb-drag-over');
  });
  hero.addEventListener('dragleave', function(e) {
    if (!hero.contains(e.relatedTarget)) hero.classList.remove('bb-drag-over');
  });
  hero.addEventListener('drop', function(e) {
    e.preventDefault();
    hero.classList.remove('bb-drag-over');
    var file = e.dataTransfer.files[0];
    if (file && file.type.startsWith('image/')) {
      hero.classList.add('bb-cover-loading');
      bbUploadImage(file, bbSetCover, hero);
    }
  });
})();

// ── Gallery placeholder drag-drop (delegated) ─────────────────────────────────
document.addEventListener('dragover', function(e) {
  var zone = e.target.closest('.bb-upload-zone');
  if (zone) { e.preventDefault(); zone.classList.add('bb-drag-over'); }
});
document.addEventListener('dragleave', function(e) {
  var zone = e.target.closest('.bb-upload-zone');
  if (zone && !zone.contains(e.relatedTarget)) zone.classList.remove('bb-drag-over');
});
document.addEventListener('drop', function(e) {
  var zone = e.target.closest('.bb-upload-zone');
  if (!zone) return;
  e.preventDefault();
  zone.classList.remove('bb-drag-over');
  var file = e.dataTransfer.files[0];
  if (file && file.type.startsWith('image/')) bbUploadImage(file, bbSetGalleryImage, zone);
});

function saveGame() {
  // If the edit-details dialog is open, flush its values into _ed first
  if (document.getElementById('bbedOverlay').classList.contains('open')) {
    applyEditDetails();
  }
  // Sync inline stat inputs into _ed before building FormData
  var _statSync = {
    bbsiMinPlayers:'min_players', bbsiMaxPlayers:'max_players',
    bbsiMinPlaytime:'min_playtime', bbsiMaxPlaytime:'max_playtime',
    bbsiWeight:'weight'
  };
  Object.keys(_statSync).forEach(function(id) {
    var el = document.getElementById(id);
    if (el) _ed[_statSync[id]] = el.value.trim();
  });

  var saveBtn = document.getElementById('bbSaveBtn');
  saveBtn.disabled = true;
  saveBtn.textContent = 'Saving…';

  var titleEl = document.getElementById('bbEditTitle');
  var summaryEl = document.getElementById('bbEditSummary');
  var descEl = document.getElementById('bbEditDesc');

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
  var _priceEl = document.getElementById('bbEditPrice');
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
    console.log('[BillBoard save response]', r, xhr.responseText);
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
    var toast = document.getElementById('bbSaveToast');
    if (toast) { toast.textContent = '✓ Saved — refreshing…'; toast.classList.add('visible'); }
    var newName = titleEl ? titleEl.textContent.trim() : ORIG_GAME_NAME;
    var newSlug = newName.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    var destUrl = APP_ROOT + SHEET_ID + '/billboard/' + newSlug;
    try { sessionStorage.setItem('bb_scroll_' + newSlug, window.scrollY); } catch(e) {}
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
  if (!document.getElementById('bbedOverlay').classList.contains('open')) {
    document.body.style.overflow = '';
  }
}
function switchVideo(btn, embed) {
  document.getElementById('videoFrame').src = embed;
  document.querySelectorAll('.bb-vtab').forEach(function(t){ t.classList.remove('active'); });
  btn.classList.add('active');
}
function toggleFaq(id) {
  document.getElementById(id).classList.toggle('open');
}
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') {
    closeBbMenu();
    if (document.getElementById('fetchLogOverlay').classList.contains('open'))  { closeFetchLog(); return; }
    if (document.getElementById('settingsOverlay').classList.contains('open')) { forceCloseSettings(); return; }
    if (document.getElementById('signInOverlay').classList.contains('open'))   { forceCloseSignIn(); return; }
    if (document.getElementById('bbPublishOverlay').classList.contains('open')){ closePublishDialog(); return; }
    if (document.getElementById('bbedOverlay').classList.contains('open'))     { closeEditDetails(); return; }
    closeLightbox();
  }
});

// ── Fetch log dialog ─────────────────────────────────────────────────────────
function bbSyncLog(msg, type) {
  var log  = document.getElementById('fetchLogBody');
  var line = document.createElement('span');
  line.className = 'sync-log-line ' + (type || 'info');
  line.textContent = msg;
  log.appendChild(line);
  log.scrollTop = log.scrollHeight;
}
function bbOpenFetchDialog() {
  document.getElementById('fetchLogBody').innerHTML = '';
  document.getElementById('fetchLogTitle').textContent = 'Fetching…';
  document.getElementById('fetchLogDoneBtn').disabled = true;
  document.getElementById('fetchLogOverlay').classList.add('open');
}
function bbFinishFetch(hasErrors) {
  document.getElementById('fetchLogTitle').textContent = hasErrors ? 'Fetch — errors' : 'Fetch Complete';
  document.getElementById('fetchLogDoneBtn').disabled = false;
  bbSyncLog(hasErrors ? 'Completed with errors.' : 'Done.', hasErrors ? 'error' : 'ok');
}
function bbDoFetch() {
  bbOpenFetchDialog();
  var base = APP_ROOT + 'push/setupBillboard.php';
  var hasErrors = false;

  bbSyncLog('Fetching sheets…', 'info');
  var fd = new FormData(); fd.append('id', SHEET_ID);
  fetch(base, { method:'POST', body:fd })
    .then(function(r) { return r.json(); })
    .then(function(r) {
      if (r.error) { bbSyncLog('✗ ' + r.error, 'error'); bbFinishFetch(true); return; }
      if (r.tabs) Object.keys(r.tabs).forEach(function(t) {
        var s = r.tabs[t];
        bbSyncLog((s==='error'?'  ✗ ':'  ✓ ') + t, s==='error'?'error':(s==='created'?'ok':'skip'));
        if (s==='error') hasErrors = true;
      });
      if (r.json_refreshed) r.json_refreshed.forEach(function(t) { bbSyncLog('  ✓ ' + t, 'ok'); });
      if (r.refresh_errors) { Object.keys(r.refresh_errors).forEach(function(t) { bbSyncLog('  ✗ ' + t, 'error'); hasErrors = true; }); }

      var games = r.game_names || [];
      if (!games.length) { bbFinishFetch(hasErrors); return; }
      bbSyncLog('Publishing game caches…', 'info');
      var gi = 0;
      function nextGame() {
        if (gi >= games.length) { bbFinishFetch(hasErrors); return; }
        var name = games[gi++];
        var gfd = new FormData();
        gfd.append('id', SHEET_ID); gfd.append('mode', 'game'); gfd.append('name', name);
        fetch(base, { method:'POST', body:gfd })
          .then(function(r) { return r.json(); })
          .then(function(r) {
            if (r && r.ok) { bbSyncLog('  ✓ ' + name, 'ok'); }
            else {
              var msg = (r && r.error ? ': ' + r.error : '');
              if (r && r.detail) msg += ' — ' + r.detail;
              bbSyncLog('  ✗ ' + name + msg, 'error'); hasErrors = true;
            }
            nextGame();
          })
          .catch(function() { bbSyncLog('  ✗ ' + name + ': network error', 'error'); hasErrors = true; nextGame(); });
      }
      nextGame();
    })
    .catch(function() { bbSyncLog('✗ Network error', 'error'); bbFinishFetch(true); });
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
var _bbTickerRaf = null, _bbTickerPos = 0, _bbTickerPaused = false, _bbTickerEditIdx = -1, _bbTickerEditEl = null;

function bbInitReviewTicker() {
  var wrap  = document.getElementById('bbTickerWrap');
  var track = document.getElementById('bbTickerTrack');
  if (!track) return;

  if (_bbTickerRaf) { cancelAnimationFrame(_bbTickerRaf); _bbTickerRaf = null; }
  _bbTickerPos = 0; _bbTickerPaused = false; _bbTickerEditIdx = -1; _bbTickerEditEl = null;
  track.style.transform = '';

  var reviews = (_ed.reviews || []).filter(function(r) { return r.attr; });
  track.innerHTML = '';

  if (!reviews.length && !document.body.classList.contains('bb-admin-mode')) {
    if (wrap) wrap.classList.add('bb-ticker-hidden');
    return;
  }
  if (wrap) wrap.classList.remove('bb-ticker-hidden', 'bb-ticker-empty');

  function makeDot() {
    var d = document.createElement('div'); d.className = 'bb-ticker-dot'; d.textContent = '✦'; return d;
  }

  function makeItem(r, idx) {
    var item = document.createElement('div');
    item.className = 'bb-ticker-item bb-ticker-item-edit';
    item.dataset.reviewIdx = String(idx);
    var q = document.createElement('div');
    q.className = 'bb-ticker-quote';
    q.textContent = '"' + r.attr + '"';
    var a = document.createElement('div');
    a.className = 'bb-ticker-author';
    a.textContent = r.quote ? '— ' + r.quote : '';
    item.appendChild(q);
    item.appendChild(a);
    return item;
  }

  // Placeholder "+" card
  var ph = document.createElement('div');
  ph.className = 'bb-ticker-item bb-ticker-item-edit bb-ticker-item-ph';
  ph.dataset.reviewIdx = '-1';
  var phQ = document.createElement('div');
  phQ.className = 'bb-ticker-quote';
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
    if (item.classList.contains('bb-ticker-item-editing')) return;
    var idx = parseInt(item.dataset.reviewIdx, 10);
    // Open the form in the clicked item (may be a clone) so it appears where the user clicked
    bbTickerEditOpen(idx, item);
  };

  // Hover pause
  if (wrap) {
    wrap.onmouseenter = function() { _bbTickerPaused = true; };
    wrap.onmouseleave = function() { if (!_bbTickerEditEl) _bbTickerPaused = false; };
  }

  // rAF seamless scroll
  var halfWidth = null, last = null, speed = 80;
  function step(ts) {
    if (halfWidth === null) halfWidth = track.scrollWidth / 2;
    if (!_bbTickerPaused) {
      if (last !== null) {
        _bbTickerPos += speed * (ts - last) / 1000;
        if (_bbTickerPos >= halfWidth) _bbTickerPos -= halfWidth;
        track.style.transform = 'translateX(-' + _bbTickerPos.toFixed(2) + 'px)';
      }
    }
    last = ts;
    _bbTickerRaf = requestAnimationFrame(step);
  }
  _bbTickerRaf = requestAnimationFrame(step);
}

function bbTickerEditOpen(idx, el) {
  if (_bbTickerEditEl) bbTickerEditCancel(); // restore previous card in-place, then open new
  _bbTickerEditIdx = idx;
  _bbTickerEditEl  = el;
  _bbTickerPaused  = true;
  var r = idx >= 0 ? (_ed.reviews || [])[idx] : null;

  el.className = 'bb-ticker-item bb-ticker-item-editing';
  el.onclick = null;

  var ta = document.createElement('textarea');
  ta.className = 'bb-ticker-edit-ta';
  ta.placeholder = 'Review quote…';
  ta.rows = 3;
  ta.value = r ? (r.attr || '') : '';

  var inp = document.createElement('input');
  inp.type = 'text';
  inp.className = 'bb-ticker-edit-inp';
  inp.placeholder = '— Source (e.g. Rahdo Runs Through)';
  inp.value = r ? (r.quote || '') : '';

  var actions = document.createElement('div');
  actions.className = 'bb-ticker-edit-actions';
  var bSave   = document.createElement('button'); bSave.className   = 'btn-adm';               bSave.textContent   = 'Save';   bSave.onmousedown   = function(e) { e.stopPropagation(); e.preventDefault(); bbTickerEditSave(); };
  var bCancel = document.createElement('button'); bCancel.className = 'btn-adm btn-adm-secondary'; bCancel.textContent = 'Cancel'; bCancel.onmousedown = function(e) { e.stopPropagation(); e.preventDefault(); bbTickerEditCancel(); };
  var bDel    = document.createElement('button'); bDel.className    = 'btn-adm btn-adm-danger';    bDel.textContent    = 'Delete'; bDel.onmousedown    = function(e) { e.stopPropagation(); e.preventDefault(); bbTickerEditDelete(); };
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
    document.addEventListener('mousedown', _bbTickerOutside);
  }, 0);
}

function _bbTickerOutside(e) {
  if (_bbTickerEditEl && !_bbTickerEditEl.contains(e.target)) {
    document.removeEventListener('mousedown', _bbTickerOutside);
    bbTickerEditCancel();
  }
}

function _bbTickerRestoreCard(idx, el) {
  var r = idx >= 0 ? (_ed.reviews || [])[idx] : null;
  if (idx >= 0 && r) {
    el.className = 'bb-ticker-item bb-ticker-item-edit';
    el.innerHTML = '';
    var q = document.createElement('div'); q.className = 'bb-ticker-quote'; q.textContent = '"' + r.attr + '"';
    var a = document.createElement('div'); a.className = 'bb-ticker-author'; a.textContent = r.quote ? '— ' + r.quote : '';
    el.appendChild(q); el.appendChild(a);
    (function(i, e2) { e2.onclick = function(ev) { ev.stopPropagation(); bbTickerEditOpen(i, e2); }; })(idx, el);
  } else {
    el.className = 'bb-ticker-item bb-ticker-item-edit bb-ticker-item-ph';
    el.innerHTML = '';
    var phQ = document.createElement('div'); phQ.className = 'bb-ticker-quote'; phQ.textContent = '+ Add review';
    el.appendChild(phQ);
    (function(e2) { e2.onclick = function(ev) { ev.stopPropagation(); bbTickerEditOpen(-1, e2); }; })(el);
  }
}

function bbTickerEditSave() {
  if (!_bbTickerEditEl) return;
  document.removeEventListener('mousedown', _bbTickerOutside);
  var ta  = _bbTickerEditEl.querySelector('.bb-ticker-edit-ta');
  var inp = _bbTickerEditEl.querySelector('.bb-ticker-edit-inp');
  var q = ta ? ta.value.trim() : '', s = inp ? inp.value.trim() : '';
  if (!q) { bbTickerEditCancel(); return; }
  if (!_ed.reviews) _ed.reviews = [];
  if (_bbTickerEditIdx >= 0) {
    _ed.reviews[_bbTickerEditIdx] = {attr: q, quote: s};
  } else {
    _ed.reviews.push({attr: q, quote: s});
  }
  _bbTickerEditEl = null;
  bbInitReviewTicker();
}

function bbTickerEditDelete() {
  document.removeEventListener('mousedown', _bbTickerOutside);
  if (_bbTickerEditIdx < 0) { bbTickerEditCancel(); return; }
  if (!_ed.reviews) return;
  _ed.reviews.splice(_bbTickerEditIdx, 1);
  _bbTickerEditEl = null;
  bbInitReviewTicker();
}

function bbTickerEditCancel() {
  document.removeEventListener('mousedown', _bbTickerOutside);
  if (_bbTickerEditEl) {
    _bbTickerRestoreCard(_bbTickerEditIdx, _bbTickerEditEl);
  }
  _bbTickerPaused  = false;
  _bbTickerEditIdx = -1;
  _bbTickerEditEl  = null;
}

// Public page: start gallery auto-advance and ticker
(function() {
  if (!document.body.classList.contains('bb-admin-mode')) {
    var thumbs = document.querySelectorAll('#bbGalleryThumbs .bb-gallery-thumb');
    var featEl = document.getElementById('bbGalleryFeaturedImg');
    if (thumbs.length && featEl) {
      var pubGalleryIdx = 0;
      var pubUrls = Array.prototype.map.call(thumbs, function(t) { var i = t.querySelector('img'); return i ? i.getAttribute('src') : ''; }).filter(Boolean);
      // Set first image immediately
      if (pubUrls.length) { featEl.src = pubUrls[0]; featEl.style.display = ''; }
      function pubSelect(idx) {
        pubGalleryIdx = idx;
        featEl.classList.add('bb-gf-fade');
        setTimeout(function() {
          featEl.src = pubUrls[idx];
          featEl.onload = function() { featEl.classList.remove('bb-gf-fade'); featEl.onload = null; };
        }, 180);
        thumbs.forEach(function(tt, ii) { tt.classList.toggle('bb-gt-active', ii === idx); });
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
  var wrap  = document.getElementById('bbTickerWrap');
  var track = document.getElementById('bbTickerTrack');
  if (!track || !track.children.length) return;
  if (document.body.classList.contains('bb-admin-mode')) return; // admin handles its own init

  var halfWidth = null, last = null, speed = 80;
  if (wrap) {
    wrap.onmouseenter = function() { _bbTickerPaused = true; };
    wrap.onmouseleave = function() { _bbTickerPaused = false; };
  }
  function step(ts) {
    if (halfWidth === null) halfWidth = track.scrollWidth / 2;
    if (!_bbTickerPaused) {
      if (last !== null) {
        _bbTickerPos += speed * (ts - last) / 1000;
        if (_bbTickerPos >= halfWidth) _bbTickerPos -= halfWidth;
        track.style.transform = 'translateX(-' + _bbTickerPos.toFixed(2) + 'px)';
      }
      last = ts;
    } else {
      last = ts;
    }
    _bbTickerRaf = requestAnimationFrame(step);
  }
  _bbTickerRaf = requestAnimationFrame(step);
})();

// ── Shared footer ────────────────────────────────────────────────────────────
function bbClickSocial(btn) {
  var url = btn.dataset.url;
  if (url) window.open(url, '_blank', 'noopener');
}
// ── Subscribe (shared footer) ─────────────────────────────────────────────────
function bbSubscribeSubmit(e) {
  e.preventDefault();
  var email = (document.getElementById('bbSubscribeEmail').value || '').trim();
  if (!email) return;
  var btn = document.getElementById('bbSubscribeBtn');
  var msg = document.getElementById('bbSubscribeMsg');
  btn.disabled = true;
  btn.textContent = 'Subscribing…';
  msg.textContent = '';
  msg.className = 'bb-footer-sub-msg';
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
        msg.className = 'bb-footer-sub-msg ok';
        document.getElementById('bbSubscribeEmail').value = '';
        btn.textContent = 'Subscribed ✓';
      } else {
        msg.textContent = d.error || 'Something went wrong. Please try again.';
        msg.className = 'bb-footer-sub-msg err';
        btn.disabled = false;
        btn.textContent = 'Subscribe';
      }
    })
    .catch(function() {
      msg.textContent = 'Network error. Please try again.';
      msg.className = 'bb-footer-sub-msg err';
      btn.disabled = false;
      btn.textContent = 'Subscribe';
    });
}
</script>

<!-- ── Fetch Log dialog ── -->
<div class="bbed-overlay" id="fetchLogOverlay">
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

<?php if (!isset($_bbPublicBase)): ?>
<script>
// The GameBoard account: one sign-in for every app. Editing (admin mode) is for the sheet's owner — the
// account that set it up — and follows the account: the page reloads into admin mode once it's checked.
(function() {
  var KEY = 'billboard_owner_' + SHEET_ID;
  function had() { try { return !!sessionStorage.getItem(KEY); } catch (e) { return false; } }
  GBAccount.init({
    app: 'billboard', name: 'BillBoard', sheet: SHEET_ID, sheetName: <?= json_encode(gbSheetName((string) $_sheetId)) ?>, base: APP_ROOT, home: SHEET_ID + '/billboard', gate: false,
    icon: APP_ROOT + 'images/bb_icon_180.png', plans: [{ plan: 'pro', label: '', price: '$4.99', note: 'every feature' }],
    onChange: function(s) {
      // Signed in to the account (owner of this sheet or not): Sign Out
      var so = document.getElementById('bbMenuSignInOut'); if (so) { so.textContent = s.signedIn ? 'Sign Out' : 'Sign In'; so.classList.toggle('signout', s.signedIn); }
      var up = document.getElementById('bbUpgradeBtn');
      if (up) { up.textContent = s.signedIn ? 'Subscription' : 'Subscribe'; up.style.color = s.app && /subscribed|activated|code/.test(s.app.state) ? '#4caf50' : '#f5a623'; }
      if (s.signedIn && !s.checked) return;   // wait until the account is checked for this sheet
      var was = had();
      if (s.owner) {
        try { sessionStorage.setItem(KEY, JSON.stringify({ email: s.account.email, bio: { name: s.account.name || '' } })); } catch (e) {}
        if (!was) location.reload();
      } else {
        try { sessionStorage.removeItem(KEY); } catch (e) {}
        if (was) location.reload();
      }
    }
  });
  window.openSignIn = function() { GBAccount.signIn().catch(function() {}); };
  window.openBbUpgradeDialog = function() { GBAccount.openPlans(); };
  window._bbLoadSubStatus = function(cb) { if (cb) cb({}); };
  var menu = window.bbMenuAction;
  window.bbMenuAction = function(action) {
    if (action === 'signout') { if (typeof closeBbMenu === 'function') closeBbMenu(); if (GBAccount.account()) GBAccount.signOut(); else openSignIn(); return; }
    if (action === 'upgrade') { if (typeof closeBbMenu === 'function') closeBbMenu(); GBAccount.openPlans(); return; }
    menu(action);
  };
})();
</script>
<?php endif; ?>
</body>
</html>
