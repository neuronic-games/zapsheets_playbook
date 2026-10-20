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

// ── Load games list ──────────────────────────────────────────────────────────
$_allGames = _gb_load($_sDir . '/games.json');
// Catalog only shows Published games; individual pages work for any game
$_games = array_values(array_filter($_allGames, fn($g) => strtolower(trim($g['Status'] ?? '')) === 'published'));

// Shared image-file SVG icon used on every image-change button
$_imgBtnIcon = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>';

if (!$_gameSlug) {
    // ── No slug: render the admin catalog ────────────────────────────────────────
    $_pubName  = trim($_settings['Company'] ?? $_settings['Name'] ?? '');
    $_pubLogo  = trim($_settings['Logo URL'] ?? '');
    $_catUrl   = $_gbBase;
    $_logoColor = $_template === 'opensea' ? '#00e5ff' : '#7c9eff';
    $_accentHex = $_template === 'opensea' ? '#0097a7' : '#3b3baa';
    // Carousel images
    $_carousel = [];
    for ($i = 1; $i <= 6; $i++) {
        $u = trim($_settings['Carousel ' . $i] ?? '');
        if ($u) $_carousel[] = $u;
    }
    // Company copy
    $_about   = trim($_settings['About']   ?? '');
    $_tagline = trim($_settings['Tagline'] ?? '');
    // Social links
    $_insta   = trim($_settings['Instagram'] ?? '');
    $_twitter = trim($_settings['Twitter']   ?? '');
    $_fb      = trim($_settings['Facebook']  ?? '');
    $_yt      = trim($_settings['YouTube']   ?? '');
    $_bgg     = trim($_settings['BGG URL']   ?? '');
    // Banner color
    $_bannerColor = trim($_settings['Banner Color'] ?? '') ?: '#1a1a2e';
    // Footer
    $_copyright  = trim($_settings['Copyright']   ?? '') ?: '© ' . date('Y') . ' ' . ($_pubName ?: 'GameBoard');
    $_footerNote = trim($_settings['Footer Note']  ?? '');
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
.gb-banner-name { font-family:'DINBlack',sans-serif; font-size:1.1rem; letter-spacing:.06em; opacity:.75; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
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

/* ── About strip ── */
.cat-about { max-width:720px; margin:2.5rem auto 0; padding:0 1.5rem; text-align:center; }
.cat-about-text { font-size:.93rem; line-height:1.7; color:var(--ink-lt); }

/* ── Section heading ── */
.cat-section-head { max-width:1100px; margin:2.5rem auto .75rem; padding:0 1.25rem; display:flex; align-items:baseline; gap:.75rem; }
.cat-section-title { font-family:'DINBlack',sans-serif; font-size:1rem; text-transform:uppercase; letter-spacing:.06em; color:var(--ink); }
.cat-section-count { font-size:.78rem; color:var(--muted); }

/* ── Grid ── */
.cat-grid { max-width:1100px; margin:0 auto 5rem; padding:0 1.25rem; display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:1.1rem; }
.cat-card { background:var(--surface); border-radius:12px; box-shadow:0 2px 14px rgba(0,0,0,.08); overflow:hidden; transition:box-shadow .15s, transform .15s; cursor:pointer; display:flex; flex-direction:column; }
.cat-card:hover { box-shadow:0 6px 24px rgba(0,0,0,.14); transform:translateY(-3px); }
.cat-card-img { width:100%; aspect-ratio:1; object-fit:cover; display:block; }
.cat-card-ph { width:100%; aspect-ratio:1; display:flex; align-items:center; justify-content:center; font-size:3.5rem; opacity:.28; background:linear-gradient(135deg,#dde,#c8c8e8); }
.cat-card-body { padding:.75rem .9rem 1rem; flex:1; display:flex; flex-direction:column; gap:.2rem; }
.cat-card-name { font-family:'DINBlack',sans-serif; font-size:.95rem; color:var(--accent); line-height:1.2; }
.cat-card-summary { font-size:.8rem; color:var(--ink-lt); line-height:1.45; flex:1; margin-top:.1rem; }
.cat-card-meta { display:flex; flex-wrap:wrap; gap:.3rem .65rem; margin-top:.35rem; }
.cat-card-stat { font-size:.72rem; color:var(--muted); }

/* ── Shared image-change button (admin mode) ── */
.gb-img-btn { width:2rem; height:2rem; border-radius:50%; background:rgba(0,0,0,.52); color:#fff; display:none; align-items:center; justify-content:center; cursor:pointer; transition:background .15s; line-height:1; padding:0; user-select:none; border:none; flex-shrink:0; }
.gb-img-btn:hover { background:rgba(0,0,0,.82); }
.gb-img-btn svg { pointer-events:none; }
body.gb-admin-mode .gb-img-btn { display:flex; }

/* ── Cat card image upload (admin mode) ── */
.cat-img-wrap { position:relative; display:block; width:100%; }
.cat-img-upload-btn { position:absolute; top:.4rem; right:.4rem; z-index:2; }
.cat-img-wrap.drag-over::after { content:'Drop to set image'; position:absolute; inset:0; background:rgba(55,110,200,.75); color:#fff; font-family:'DINBlack',sans-serif; font-size:.8rem; text-transform:uppercase; letter-spacing:.07em; display:flex; align-items:center; justify-content:center; z-index:3; pointer-events:none; }

/* ── Empty ── */
.cat-empty { grid-column:1/-1; padding:4rem 1rem; text-align:center; color:var(--muted); }
.cat-empty-icon { font-size:3rem; margin-bottom:.75rem; }
.cat-empty-title { font-family:'DINBlack',sans-serif; font-size:1rem; color:var(--ink); margin-bottom:.4rem; }
.cat-empty-body { font-size:.85rem; line-height:1.6; }

/* ── Footer ── */
.gb-footer { background:#1c1c1e; color:rgba(255,255,255,.5); margin-top:4rem; }
.gb-footer-main { max-width:1100px; margin:0 auto; padding:3rem 2rem 2.5rem; display:flex; align-items:flex-start; justify-content:space-between; gap:2rem; flex-wrap:wrap; }
.gb-footer-brand { display:flex; flex-direction:column; gap:1rem; min-width:140px; }
.gb-footer-logo-img { height:44px; object-fit:contain; }
.gb-footer-logo-ph { font-family:'DINBlack',sans-serif; font-size:1.1rem; color:rgba(255,255,255,.75); letter-spacing:.05em; }
.gb-footer-social { display:flex; gap:.6rem; align-items:center; flex-wrap:wrap; }
.gb-footer-social a { width:2.1rem; height:2.1rem; border-radius:50%; background:rgba(255,255,255,.1); display:flex; align-items:center; justify-content:center; transition:background .15s; }
.gb-footer-social a:hover { background:rgba(255,255,255,.22); }
.gb-footer-social svg { fill:rgba(255,255,255,.7); }
.gb-footer-bar { border-top:1px solid rgba(255,255,255,.08); }
.gb-footer-bar-inner { max-width:1100px; margin:0 auto; padding:.9rem 2rem; display:flex; align-items:center; justify-content:space-between; gap:1.5rem; flex-wrap:wrap; font-size:.72rem; color:rgba(255,255,255,.38); }
.gb-footer-bar-left { display:flex; flex-direction:column; gap:.2rem; }
.gb-footer-bar-icons { display:flex; gap:.5rem; align-items:center; flex-shrink:0; }
.gb-social-btn { width:1.9rem; height:1.9rem; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:border-color .14s,background .14s; cursor:pointer; padding:0; }
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
      <button class="btn-add-game" id="gbAddGameBtn" onclick="openAddGame()" style="display:none">+ Game</button>
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
      <?php if ($_pubName): ?><span class="gb-banner-name"><?= h($_pubName) ?></span><?php endif; ?>
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
<div class="cat-carousel" id="catCarousel"
  ondragover="carouselDragOver(event)" ondragleave="carouselDragLeave(event)" ondrop="carouselDrop(event)">
<?php if (!empty($_carousel)): ?>
  <?php foreach ($_carousel as $_ci => $_cUrl): ?>
  <div class="cat-slide<?= $_ci === 0 ? ' active' : '' ?>" style="background-image:url('<?= h($_cUrl) ?>')"></div>
  <?php endforeach; ?>
  <button class="cat-slide-btn cat-slide-prev" onclick="carouselPrev()" title="Previous">&#8249;</button>
  <button class="cat-slide-btn cat-slide-next" onclick="carouselNext()" title="Next">&#8250;</button>
  <div class="cat-slide-dots" id="catSlideDots">
    <?php foreach ($_carousel as $_ci => $_cUrl): ?>
    <button class="cat-slide-dot<?= $_ci === 0 ? ' active' : '' ?>" onclick="carouselGo(<?= $_ci ?>)"></button>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="cat-carousel-ph">
    <svg class="cat-carousel-ph-icon" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.6)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
    <div class="cat-carousel-ph-text">Add carousel images in Settings</div>
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
    <div class="cat-empty-icon">🎲</div>
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
  <a class="cat-card" href="<?= h($_catUrl . $gSlug) ?>">
    <div class="cat-img-wrap" data-game="<?= h($gName) ?>"
      ondragover="catDragOver(event,this)" ondragleave="catDragLeave(event,this)" ondrop="catDrop(event,this)">
      <?php if ($gCover): ?>
      <img class="cat-card-img" src="<?= h($gCover) ?>" alt="<?= h($gName) ?>" loading="lazy" />
      <?php else: ?>
      <div class="cat-card-ph">🎲</div>
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
<?php endforeach; endif; ?>
</div>

<footer class="gb-footer">
  <?php
  // All social platforms — always rendered in admin; public shows only configured ones
  $_socialDefs = [
    ['key'=>'instagram','label'=>'Instagram',    'url'=>$_insta,   'svg'=>'<svg width="16" height="16" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>'],
    ['key'=>'twitter',  'label'=>'X / Twitter', 'url'=>$_twitter, 'svg'=>'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.73-8.835L1.254 2.25H8.08l4.253 5.622L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>'],
    ['key'=>'facebook', 'label'=>'Facebook',    'url'=>$_fb,      'svg'=>'<svg width="14" height="14" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>'],
    ['key'=>'youtube',  'label'=>'YouTube',     'url'=>$_yt,      'svg'=>'<svg width="16" height="12" viewBox="0 0 24 17"><path d="M23.495 2.205a3.02 3.02 0 0 0-2.122-2.136C19.545 0 12 0 12 0S4.455 0 2.627.07a3.02 3.02 0 0 0-2.122 2.135C0 4.046 0 8.5 0 8.5s0 4.455.505 6.295a3.02 3.02 0 0 0 2.122 2.136C4.455 17 12 17 12 17s7.545 0 9.373-.07a3.02 3.02 0 0 0 2.122-2.135C24 12.954 24 8.5 24 8.5s0-4.454-.505-6.295zM9.545 12.273V4.727L15.818 8.5l-6.273 3.773z"/></svg>'],
    ['key'=>'bgg_url',  'label'=>'BoardGameGeek','url'=>$_bgg,    'svg'=>'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2v-4M9 21H5a2 2 0 0 1-2-2v-4m0 0h18"/></svg>'],
  ];
  ?>
  <div class="gb-footer-main">
    <div class="gb-footer-brand">
      <?php if ($_pubLogo): ?>
      <img src="<?= h($_pubLogo) ?>" alt="<?= h($_pubName) ?>" class="gb-footer-logo-img" />
      <?php elseif ($_pubName): ?>
      <div class="gb-footer-logo-ph"><?= h($_pubName) ?></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="gb-footer-bar">
    <div class="gb-footer-bar-inner">
      <div class="gb-footer-bar-left">
        <span class="gb-footer-editable"
              contenteditable="false"
              id="gbFooterCopyright"
              data-setting="copyright"
              data-placeholder="© <?= date('Y') ?> <?= h($_pubName ?: 'Your Company') ?>"
              onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}"
              onblur="gbSaveFooterField(this)"><?= h($_copyright) ?></span>
        <?php if ($_footerNote || !isset($_gbPublicBase)): ?>
        <span class="gb-footer-editable"
              contenteditable="false"
              id="gbFooterNote"
              data-setting="footer_note"
              data-placeholder="Click to add contact info or tagline…"
              onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}"
              onblur="gbSaveFooterField(this)"><?= h($_footerNote) ?></span>
        <?php endif; ?>
      </div>
      <!-- Social icons: always visible in admin; public hides unconfigured ones -->
      <div class="gb-footer-bar-icons" id="gbFooterIcons">
        <?php foreach ($_socialDefs as $_sd):
          $_isSet = !empty($_sd['url']);
          $_showInPublic = $_isSet;
          $_showInAdmin = true;
          if (!isset($_gbPublicBase) || $_isSet): ?>
        <button class="gb-social-btn<?= $_isSet ? ' gb-social-set' : ' gb-social-empty' ?>"
                data-key="<?= h($_sd['key']) ?>"
                data-label="<?= h($_sd['label']) ?>"
                data-url="<?= h($_sd['url']) ?>"
                onclick="gbClickSocial(this)"
                title="<?= h($_sd['label']) . ($_isSet ? '' : ' — click to add') ?>"><?= $_sd['svg'] ?></button>
        <?php endif; endforeach; ?>
      </div>
    </div>
  </div>
</footer>


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
        <div class="gbed-section-title">Carousel Images <span style="font-size:.68rem;font-weight:400;color:var(--muted)">(paste image URLs)</span></div>
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

function _updateGbAuth() {
  var btn = document.getElementById('gbUserBtn');
  var metaEl = document.getElementById('gbMeta');
  var siOut = document.getElementById('gbMenuSignInOut');
  var profile = document.getElementById('gbMenuProfile');
  var publishBtn = document.getElementById('gbMenuPublish');
  var addGameBtn = document.getElementById('gbAddGameBtn');
  if (_gbUser) {
    var name = (_gbUser.bio && (_gbUser.bio['name'] || _gbUser.bio['Name'])) || _gbUser.email || '';
    if (btn) { btn.classList.add('signed-in'); btn.title = 'Signed in as ' + (_gbUser.email || ''); }
    if (metaEl) metaEl.textContent = (name || <?= json_encode($_pubName ?: 'Games') ?>) + ' · GameBoard';
    if (siOut)  { siOut.textContent = 'Sign Out'; siOut.className = 'user-menu-item signout'; }
    if (profile) profile.style.display = '';
    if (publishBtn) publishBtn.style.display = '';
    if (addGameBtn) addGameBtn.style.display = '';
    var saveBtn = document.getElementById('gbSavePageBtn');
    if (saveBtn) saveBtn.style.display = '';
    document.body.classList.add('gb-admin-mode');
    document.querySelectorAll('.gb-footer-editable').forEach(function(el){ el.contentEditable = 'true'; });
  } else {
    document.querySelectorAll('.gb-footer-editable').forEach(function(el){ el.contentEditable = 'false'; });
    var saveBtn = document.getElementById('gbSavePageBtn');
    if (saveBtn) saveBtn.style.display = 'none';
    if (btn) { btn.classList.remove('signed-in'); btn.title = 'Menu'; }
    if (metaEl) metaEl.textContent = <?= json_encode($_pubName ?: 'Games') ?>;
    if (siOut)  { siOut.textContent = 'Sign In'; siOut.className = 'user-menu-item'; }
    if (profile) profile.style.display = 'none';
    if (publishBtn) publishBtn.style.display = 'none';
    if (addGameBtn) addGameBtn.style.display = 'none';
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
    var btn = document.getElementById('gbUserBtn');
    btn.style.opacity = '.4';
    var fd = new FormData(); fd.append('id', SHEET_ID);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_ROOT + 'push/setupGameboard.php');
    xhr.onload = function() {
      btn.style.opacity = '';
      var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = null; }
      if (r && !r.ok && r.refresh_errors) {
        var msgs = Object.entries(r.refresh_errors).map(function(e){ return e[0]+': '+e[1]; }).join('\n');
        alert('Fetch error — could not read sheet data:\n' + msgs);
      } else if (r && r.error) {
        alert('Fetch error: ' + r.error);
      } else {
        location.reload();
      }
    };
    xhr.onerror = function() { btn.style.opacity = ''; alert('Fetch failed — network error.'); };
    xhr.send(fd);
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
  if (btn) { btn._orig = btn.innerHTML; btn.textContent = '⏳'; btn.disabled = true; }

  var formData = new FormData();
  formData.append('file', file);
  formData.append('id', SHEET_ID);

  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/uploadImage.php');
  xhr.onload = function() {
    var r; try { r = JSON.parse(xhr.responseText); } catch(ex) { r = {error:'parse error'}; }
    if (!r || r.error) {
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
        alert('Sheet update failed: ' + (r2 && r2.error || 'unknown'));
        return;
      }
      // Swap image in DOM immediately
      var existing = wrap.querySelector('.cat-card-img');
      var ph = wrap.querySelector('.cat-card-ph');
      if (existing) {
        existing.src = imageUrl;
      } else {
        var img = document.createElement('img');
        img.className = 'cat-card-img';
        img.src = imageUrl;
        img.alt = gameName;
        img.loading = 'lazy';
        if (ph) ph.parentNode.replaceChild(img, ph);
        else wrap.insertBefore(img, wrap.firstChild);
      }
    };
    xhr2.send(fd2);
  };
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
  xhr.send(fd);
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
(function() {
  var slides = document.querySelectorAll('#catCarousel .cat-slide');
  var dots   = document.querySelectorAll('#catSlideDots .cat-slide-dot');
  if (!slides.length) return;
  var cur = 0, timer = null;
  function go(n) {
    slides[cur].classList.remove('active');
    if (dots[cur]) dots[cur].classList.remove('active');
    cur = (n + slides.length) % slides.length;
    slides[cur].classList.add('active');
    if (dots[cur]) dots[cur].classList.add('active');
  }
  function start() { timer = setInterval(function(){ go(cur + 1); }, 5000); }
  function reset()  { clearInterval(timer); start(); }
  window.carouselPrev = function() { go(cur - 1); reset(); };
  window.carouselNext = function() { go(cur + 1); reset(); };
  window.carouselGo   = function(n) { go(n); reset(); };
  // Drag-drop for replacing carousel images (admin mode)
  window.carouselDragOver = function(e) {
    if (!document.body.classList.contains('gb-admin-mode')) return;
    e.preventDefault();
  };
  window.carouselDragLeave = function(e) { e.preventDefault(); };
  window.carouselDrop = function(e) {
    e.preventDefault();
    if (!document.body.classList.contains('gb-admin-mode')) return;
    // not yet wired — placeholder for future upload
  };
  start();
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
    for (var i = 1; i <= 6; i++) {
      var el = document.getElementById('setCarousel' + i);
      if (el) el.value = s['Carousel ' + i] || '';
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
  fd.append('gb_template',   document.getElementById('setTemplate').value);
  fd.append('banner_color', document.getElementById('setBannerColor').value);
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
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = {error: xhr.responseText}; }
    if (r.error) { document.getElementById('setErr').textContent = r.error; return; }
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

// ── Strip HTML on paste — always insert plain text ────────────────────────────
document.addEventListener('paste', function(e) {
  var el = e.target;
  if (el.tagName !== 'TEXTAREA' && !(el.tagName === 'INPUT' && el.type === 'text')) return;
  var text = (e.clipboardData || window.clipboardData).getData('text/plain');
  if (!text) return;
  e.preventDefault();
  var start = el.selectionStart, end = el.selectionEnd, val = el.value;
  el.value = val.substring(0, start) + text + val.substring(end);
  el.selectionStart = el.selectionEnd = start + text.length;
  el.dispatchEvent(new Event('input', { bubbles: true }));
}, true);
</script>
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
$_coverUrl    = _gb_img(trim($_game['Cover URL']    ?? $_game['Image'] ?? ''));
$_imageUrl    = _gb_img(trim($_game['Image URL']    ?? $_game['Image'] ?? ''));
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
$_artists = [];
for ($i = 1; $i <= 4; $i++) {
    $a = trim($_game["Artist$i"] ?? '');
    if ($a) $_artists[] = $a;
}

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

$_components  = _gb_rows($_rich, 'Component');
$_reviews     = _gb_rows($_rich, 'Review');
$_faqs        = _gb_rows($_rich, 'FAQ');
$_buyUrls     = _gb_rows($_rich, 'BuyUrl');
$_richVideos  = _gb_rows($_rich, 'Video');
$_mediaImgs   = array_merge(_gb_rows($_rich, 'Media'), _gb_rows($_rich, 'ProductImage'));
$_richPrice   = _gb_val($_rich, 'Price');
$_richRules   = _gb_val($_rich, 'Rules PDF Url') ?: $_rulesUrl;

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
.gb-inline-hint { display:none; font-size:.73rem; color:var(--muted); margin:.3rem 0 0; font-style:italic; }
body.gb-admin-mode .gb-inline-hint { display:block; }

/* ── FAQ editor (dynamic Q+A pairs) ── */
.gb-faq-editor { display:none; flex-direction:column; gap:.6rem; min-height:0; padding:0; border:none; background:none; resize:none; }
body.gb-admin-mode .gb-faq-editor { display:flex; }
.gb-faq-pair { border:1.5px dashed var(--border); border-radius:8px; overflow:hidden; }
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
.gb-cover-btn { position: absolute; top: .75rem; right: .75rem; z-index: 6; }
body.gb-admin-mode .gb-hero.no-cover .gb-cover-btn { display: flex; }
body.gb-admin-mode .gb-hero:not(.no-cover) .gb-cover-btn { display: none; }
body.gb-admin-mode .gb-hero:not(.no-cover):hover .gb-cover-btn,
body.gb-admin-mode .gb-hero.gb-drag-over .gb-cover-btn { display: flex; }
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
/* Placeholder gallery thumbs */
.gb-ph-gallery { display: none; }
body.gb-admin-mode .gb-ph-gallery { display: flex; gap:.55rem; }
.gb-ph-thumb {
  height:210px; width:200px; border-radius:8px; flex-shrink:0;
  background:linear-gradient(135deg,#dde 0%,#c8c8e8 100%);
  display:flex; align-items:center; justify-content:center;
  font-size:2.5rem; opacity:.3; border:2px dashed rgba(59,59,170,.2);
}
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

<!-- ── Hidden file input for image uploads ── -->
<input type="file" id="gbFileInput" accept="image/*" style="display:none" onchange="gbHandleFileInput(event)">

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
        <svg class="gb-stat-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
        <input class="gb-stat-input" id="gbsiWeight" type="text" placeholder="Weight" oninput="_ed.weight=this.value.trim()" />
      </div>
    </div>
    <!-- Designer row (admin) -->
    <div class="gb-stat-designers" id="gbStatDesigners">
      <svg class="gb-role-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
      <span id="gbDesignerInputs"></span>
    </div>
    <!-- Artist row (admin) -->
    <div class="gb-stat-designers" id="gbStatArtists">
      <svg class="gb-role-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/></svg>
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
      <?php if ($_mechanic):    ?><div class="gb-stat"><?= h($_mechanic) ?></div><?php endif; ?>
      <?php if ($_ages):        ?><div class="gb-stat">Ages <?= h($_ages) ?></div><?php endif; ?>
      <?php if ($_series):      ?><div class="gb-stat"><?= h($_series) ?></div><?php endif; ?>
      <?php if ($_boxSize):     ?><div class="gb-stat"><?= h($_boxSize) ?></div><?php endif; ?>
      <?php if ($_availability):?><div class="gb-stat"><?= h($_availability) ?></div><?php endif; ?>
    </div>
    <?php if ($_designers || $_artists): ?>
    <div class="gb-stat-credits">
      <?php if ($_designers): ?><span class="gb-credit-item"><svg class="gb-role-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg><?= h(implode(', ', $_designers)) ?></span><?php endif; ?>
      <?php if ($_artists): ?><span class="gb-credit-item"><svg class="gb-role-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/></svg><?= h(implode(', ', $_artists)) ?></span><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
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
  <?php if (count($_gallery) > 1): ?>
  <div class="gb-section">
    <div class="gb-section-label">Gallery</div>
    <div class="gb-gallery">
      <?php foreach ($_gallery as $img): ?>
      <img src="<?= h($img) ?>" alt="<?= h($_name) ?>" loading="lazy" onclick="openLightbox('<?= h($img) ?>')" />
      <?php endforeach; ?>
    </div>
  </div>
  <?php else: ?>
  <!-- Placeholder gallery (admin only) — each thumb is an upload zone -->
  <div class="gb-section gb-ph-section">
    <div class="gb-section-label">Gallery</div>
    <div class="gb-ph-gallery" id="gbPhGallery">
      <div class="gb-ph-thumb gb-upload-zone" onclick="gbPickGalleryImage(this)" title="Click or drag to add image"><button class="gb-img-btn" style="position:absolute;top:.4rem;right:.4rem" onclick="event.stopPropagation();gbPickGalleryImage(this.closest('.gb-upload-zone'))" title="Add image"><?= $_imgBtnIcon ?></button></div>
      <div class="gb-ph-thumb gb-upload-zone" onclick="gbPickGalleryImage(this)" title="Click or drag to add image"><button class="gb-img-btn" style="position:absolute;top:.4rem;right:.4rem" onclick="event.stopPropagation();gbPickGalleryImage(this.closest('.gb-upload-zone'))" title="Add image"><?= $_imgBtnIcon ?></button></div>
      <div class="gb-ph-thumb gb-upload-zone" onclick="gbPickGalleryImage(this)" title="Click or drag to add image"><button class="gb-img-btn" style="position:absolute;top:.4rem;right:.4rem" onclick="event.stopPropagation();gbPickGalleryImage(this.closest('.gb-upload-zone'))" title="Add image"><?= $_imgBtnIcon ?></button></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Video(s) -->
  <?php if (!$_videoEmbed): ?>
  <!-- Placeholder video (admin only) -->
  <div class="gb-section gb-ph-section">
    <div class="gb-section-label">Watch</div>
    <div style="background:linear-gradient(135deg,#1a1a2e,#2d2d88);border-radius:10px;aspect-ratio:16/9;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:.5rem;opacity:.3;">
      <div style="font-size:3rem;">▶</div>
      <div style="font-family:'DINBlack',sans-serif;font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;color:#fff;">Add video URL via Edit Details</div>
    </div>
  </div>
  <?php endif; ?>
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

  <!-- What People Are Saying -->
  <div class="gb-section<?= empty($_reviews) ? ' gb-sect-empty' : '' ?>">
    <div class="gb-section-label">What People Are Saying</div>
    <div class="gb-faq-editor gb-inline-edit" id="gbReviewsList"></div>
    <div class="gb-reviews gb-display-content">
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

  <!-- FAQs -->
  <div class="gb-section<?= empty($_faqs) ? ' gb-sect-empty' : '' ?>">
    <div class="gb-section-label">FAQ</div>
    <div class="gb-faq-editor gb-inline-edit" id="gbFaqsList"></div>
    <?php foreach ($_faqs as $fi => $fq):
      $fqQ = trim($fq['Value']   ?? '');
      $fqA = trim($fq['Value 1'] ?? '');
      if (!$fqQ || !$fqA) continue;
    ?>
    <div class="gb-faq-item gb-display-content" id="faq<?= $fi ?>">
      <div class="gb-faq-q" onclick="toggleFaq('faq<?= $fi ?>')"><?= h($fqQ) ?></div>
      <div class="gb-faq-a"><?= nl2br(h($fqA)) ?></div>
    </div>
    <?php endforeach; ?>
  </div>

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
        $ogImg  = _gb_img(trim($og['Cover URL'] ?? $og['Image URL'] ?? $og['Image'] ?? ''));
        $ogMeta = array_filter([trim($og['Count'] ?? '') ? trim($og['Count']).' players' : '', trim($og['Duration'] ?? '')]);
      ?>
      <a class="gb-more-card" href="<?= h($_gbBase . _gb_slug($ogName)) ?>">
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
        <div class="gbed-section-title">Carousel Images</div>
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
          <label class="gbed-label">Cover URL (background image)</label>
          <input class="gbed-field" id="gbedCoverUrl" type="url" placeholder="https://…" />
        </div>
        <div class="gbed-field-group">
          <label class="gbed-label">Image URL (product photo)</label>
          <input class="gbed-field" id="gbedImageUrl" type="url" placeholder="https://…" />
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
    'components'     => array_values(array_map(function($c) { return trim($c['Value'] ?? ''); }, $_components)),
    'reviews'        => array_values(array_map(function($r) { return ['attr' => trim($r['Value'] ?? ''), 'quote' => trim($r['Value 1'] ?? '')]; }, $_reviews)),
    'faqs'           => array_values(array_map(function($f) { return ['q' => trim($f['Value'] ?? ''), 'a' => trim($f['Value 1'] ?? '')]; }, $_faqs)),
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
    ['gbEditTitle', 'gbEditSummary', 'gbEditDesc'].forEach(function(id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.contentEditable = 'true';
      if (el.classList.contains('gb-ph-lorem')) {
        el.dataset.phContent = el.textContent;
        el.textContent = '';
        el.classList.remove('gb-ph-lorem');
      }
    });
    gbInitInlineEdits();
    _updateGbAuth();
  } catch(e) {}
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
    var btn = document.getElementById('gbUserBtn');
    if (btn) btn.style.opacity = '.4';
    var fd = new FormData(); fd.append('id', SHEET_ID);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_ROOT + 'push/setupGameboard.php');
    xhr.onload = function() {
      if (btn) btn.style.opacity = '';
      var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = null; }
      alert(r && r.ok ? 'Fetch complete — reload to see changes.' : 'Fetch failed: ' + (r && r.error || 'unknown'));
    };
    xhr.onerror = function() { if (btn) btn.style.opacity = ''; alert('Fetch failed — network error.'); };
    xhr.send(fd);
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
    for (var i = 1; i <= 6; i++) {
      var el = document.getElementById('setCarousel' + i);
      if (el) el.value = s['Carousel ' + i] || '';
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
  _ed.cover_url      = document.getElementById('gbedCoverUrl').value.trim();
  _ed.image_url      = document.getElementById('gbedImageUrl').value.trim();
  _ed.video_url      = document.getElementById('gbedVideoUrl').value.trim();
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
  gbBuildReviewEditor(_ed.reviews || []);
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

  var ata = document.createElement('textarea');
  ata.className = 'faq-a';
  ata.placeholder = 'Answer';
  ata.rows = 2;
  ata.value = a || '';

  function syncFaqs() {
    _ed.faqs = Array.prototype.slice.call(
      document.querySelectorAll('#gbFaqsList .gb-faq-pair')
    ).map(function(p) {
      return {
        q: p.querySelector('.faq-q').value.trim(),
        a: p.querySelector('.faq-a').value.trim()
      };
    }).filter(function(f){ return f.q || f.a; });
  }

  function maybeAddPair() {
    // Add a new blank pair if this is the last one and has any content
    var pairs = document.querySelectorAll('#gbFaqsList .gb-faq-pair');
    var last  = pairs[pairs.length - 1];
    if (pair === last && (qta.value.trim() || ata.value.trim())) {
      gbAddFaqPair(list, '', '', true);
    }
  }

  qta.oninput = function() { gbAutoResize(this); syncFaqs(); maybeAddPair(); };
  ata.oninput = function() { gbAutoResize(this); syncFaqs(); maybeAddPair(); };

  pair.appendChild(qta);
  pair.appendChild(ata);
  list.appendChild(pair);

  // Size to content after render
  setTimeout(function() { gbAutoResize(qta); gbAutoResize(ata); }, 0);
}

// ── Save game ────────────────────────────────────────────────────────────────
// ── Image upload helpers ──────────────────────────────────────────────────────
var _gbFileCallback = null;
var _gbGalleryUploads = [];   // URLs of gallery images uploaded this session

function gbPickImage(callback) {
  _gbFileCallback = callback;
  var inp = document.getElementById('gbFileInput');
  inp.value = '';
  inp.click();
}

function gbHandleFileInput(e) {
  var file = e.target.files[0];
  if (!file || !_gbFileCallback) return;
  gbUploadImage(file, _gbFileCallback);
  _gbFileCallback = null;
}

function gbUploadImage(file, callback, zoneEl) {
  if (zoneEl) zoneEl.classList.add('gb-uploading');
  var fd = new FormData();
  fd.append('id', SHEET_ID);
  fd.append('file', file);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/uploadImage.php');
  xhr.onload = function() {
    if (zoneEl) zoneEl.classList.remove('gb-uploading');
    var r;
    try { r = JSON.parse(xhr.responseText); } catch(e) { r = {error: 'parse error'}; }
    if (r && r.ok && r.url) {
      callback(r.url, zoneEl);
    } else {
      alert('Image upload failed: ' + (r && r.error || 'unknown'));
    }
  };
  xhr.onerror = function() {
    if (zoneEl) zoneEl.classList.remove('gb-uploading');
    alert('Image upload failed: network error');
  };
  xhr.send(fd);
}

// ── Cover image ───────────────────────────────────────────────────────────────
function gbSetCover(url) {
  _ed.cover_url = url;
  var hero = document.getElementById('gbHero');
  var bg   = document.getElementById('gbHeroBg');
  if (bg)   bg.style.backgroundImage = 'url(' + url + ')';
  if (hero) hero.classList.remove('no-cover');
  gbShowUploadToast('Cover image ready');
}

// ── Gallery images ────────────────────────────────────────────────────────────
function gbPickGalleryImage(zoneEl) {
  gbPickImage(function(url) { gbSetGalleryImage(url, zoneEl); });
}

function gbSetGalleryImage(url, zoneEl) {
  _gbGalleryUploads.push(url);
  // Show preview in the zone
  if (zoneEl) {
    zoneEl.classList.add('gb-uploaded');
    zoneEl.innerHTML = '<img src="' + url + '" alt="gallery image">';
    zoneEl.onclick = null;
  } else {
    // Append a new thumb to the gallery
    var gallery = document.getElementById('gbPhGallery');
    if (gallery) {
      var div = document.createElement('div');
      div.className = 'gb-ph-thumb gb-upload-zone gb-uploaded';
      div.innerHTML = '<img src="' + url + '" alt="gallery image">'
        + '<button class="gb-img-btn" style="position:absolute;top:.4rem;right:.4rem"'
        + ' onclick="event.stopPropagation();gbPickGalleryImage(this.closest(\'.gb-upload-zone\'))"'
        + ' title="Change image">' + IMG_BTN_ICON + '</button>';
      gallery.appendChild(div);
    }
  }
  gbShowUploadToast('Image added — click Save Changes');
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
    if (file && file.type.startsWith('image/')) gbUploadImage(file, gbSetCover);
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
  fd.append('artist1',        _ed.artist1        || '');
  fd.append('artist2',        _ed.artist2        || '');
  fd.append('artist3',        _ed.artist3        || '');
  fd.append('artist4',        _ed.artist4        || '');
  fd.append('date_started',   _ed.date_started   || '');
  fd.append('date_signed',    _ed.date_signed    || '');
  fd.append('date_published', _ed.date_published || '');
  fd.append('cover_url',      _ed.cover_url      || '');
  fd.append('image',          _ed.image_url      || '');
  fd.append('video',          _ed.video_url      || '');
  fd.append('view',           _ed.page_url       || '');
  fd.append('sellsheet',      _ed.sellsheet      || '');
  fd.append('rules',          _ed.rules_url      || '');
  fd.append('play',           _ed.play_url       || '');
  fd.append('print',          _ed.print_url      || '');
  if (_ed.components !== undefined) fd.append('components', JSON.stringify(_ed.components || []));
  if (_ed.reviews    !== undefined) fd.append('reviews',    JSON.stringify(_ed.reviews    || []));
  if (_ed.faqs       !== undefined) fd.append('faqs',       JSON.stringify(_ed.faqs       || []));
  if (_gbGalleryUploads.length) {
    fd.append('gallery_add', JSON.stringify(_gbGalleryUploads));
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
    // Success — show toast, force-refresh JSON cache, then redirect
    saveBtn.textContent = '✓ Saved!';
    var toast = document.getElementById('gbSaveToast');
    if (toast) { toast.textContent = '✓ Saved — refreshing…'; toast.classList.add('visible'); }
    var newName = titleEl ? titleEl.textContent.trim() : ORIG_GAME_NAME;
    var newSlug = newName.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    var destUrl = APP_ROOT + SHEET_ID + '/gameboard/' + newSlug;
    // Force-refresh the games.json cache before redirecting so the page shows fresh data
    var rfFd = new FormData(); rfFd.append('id', SHEET_ID);
    var rfXhr = new XMLHttpRequest();
    rfXhr.open('POST', APP_ROOT + 'push/setupGameboard.php');
    rfXhr.onload  = function() { location.href = destUrl; };
    rfXhr.onerror = function() { location.href = destUrl; };
    rfXhr.send(rfFd);
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
    if (document.getElementById('settingsOverlay').classList.contains('open')) { forceCloseSettings(); return; }
    if (document.getElementById('signInOverlay').classList.contains('open'))   { forceCloseSignIn(); return; }
    if (document.getElementById('gbPublishOverlay').classList.contains('open')){ closePublishDialog(); return; }
    if (document.getElementById('gbedOverlay').classList.contains('open'))     { closeEditDetails(); return; }
    closeLightbox();
  }
});

// ── Strip HTML on paste — always insert plain text ────────────────────────────
document.addEventListener('paste', function(e) {
  var el = e.target;
  if (el.tagName !== 'TEXTAREA' && !(el.tagName === 'INPUT' && el.type === 'text')) return;
  var text = (e.clipboardData || window.clipboardData).getData('text/plain');
  if (!text) return;
  e.preventDefault();
  var start = el.selectionStart, end = el.selectionEnd, val = el.value;
  el.value = val.substring(0, start) + text + val.substring(end);
  el.selectionStart = el.selectionEnd = start + text.length;
  el.dispatchEvent(new Event('input', { bubbles: true }));
}, true);
</script>
</body>
</html>
