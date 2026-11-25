<?php
/**
 * GameBoard — the home of the board game apps (PitchBoard, DevBoard, CardBoard, BillBoard),
 * and of the GameBoard account that signs people in to them.
 * URL: {base}gameboard, and the root of mygameboard.app.
 *
 * The apps run on mygameboard.app too (its document root is this folder); zapsheets.com/app/… still works.
 * From zapsheets.com the links go to mygameboard.app, and the account is handed over in the link
 * (#gbt=…, read by the app and never sent to the server in the URL).
 */
$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$_host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
$_onDomain = (bool) preg_match('/(^|\.)mygameboard\.app$/', $_host);
$_dash = (bool) preg_match('#(^|/)dashboard/?$#', $_raw);   // /dashboard — the signed-in account's apps and sheets
$_base = preg_replace('#/(gameboard|dashboard)/?$#', '/', $_raw);
if ($_base === $_raw) $_base = '/';
if (substr($_base, -1) !== '/') $_base .= '/';
$_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
$_abs  = $_scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'zapsheets.com') . $_base;
// The apps live on mygameboard.app (this same folder is its document root): links point there — on
// mygameboard.app itself they're just this site; from zapsheets.com (old links) they go over to it.
require_once dirname(__DIR__, 2) . '/dotEnv.php';
$_apps = $_onDomain ? $_base : gbLinkBase();
$_page = gbLinkBase() === $_base ? $_abs . 'gameboard' : 'https://' . $_ENV['GAMEBOARD_HOST'] . '/';
$_home = $_onDomain ? $_base : $_base . 'gameboard';   // the GameBoard home page (relative to <base>)
$_hp = $_dash ? $_home : '';   // the home page's sections, from the dashboard
$_desc = 'Built for game makers, from the first prototype to the store shelf.';
function e($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
$APPS = [
  ['card', 'CardBoard',  'cardboard_icon_180.png', 'Design & print cards', 'Design, print, and share your card deck — fast.', '#7b6cf6', 'cardboard',
        ['Drag-and-drop card designer, fronts and backs', 'Print-and-play sheets, ready to cut', 'Tabletop Simulator and Screentop export'],
        ['$4.99', 'after a free week']],
  ['dev',  'DevBoard',   'db_icon_180.png',        'Playtest & develop', 'Every playtest, remembered.', '#49c4d4', 'devboard',
        ['Session log with observations and solutions', 'TODOs from your notes, with owner and due date', 'Estimates, invoices and reports for studios*'],
        ['$2.99', '*$3.99 / month (Pro)', 'Core']],
  ['pitch','PitchBoard', 'pitchboard_icon_180.png',        'Pitch to publishers', 'From first sketch to signed deal — tracked.', '#e7a35a', 'pitchboard',
        ['A pitch log for every game, and a board view', 'Sell sheet generator', 'Game pages and links for collaborators'],
        ['$1.99', 'every feature']],
  ['bill', 'BillBoard',  'bb_icon_180.png',        'Show & sell', 'Your games. A polished page — instantly.', '#9ed29f', 'billboard',
        ['A catalog of your games, and a page for each', 'Edit pages in place — images, stats, reviews, prices and buy links', 'Shareable public links — no website to host'],
        ['$4.99', 'every feature']],
];
?><!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= e($_base) ?>" />
<?php if (!$_dash): ?>
<script>
// Already signed in: straight to your dashboard. The home page still shows with ?home (the logo on the
// dashboard), or with a link to one of its sections (#pricing…), or when signing out (#gbsignout).
(function() {
  try {
    if (location.hash || /[?&]home\b/.test(location.search)) return;
    var a = JSON.parse(localStorage.getItem('cb_account') || 'null');
    if (!a || !a.token) return;
    var apps = <?= json_encode($_apps) ?>, base = <?= json_encode($_base) ?>;
    var cross = new URL(apps, location.href).origin !== location.origin;
    location.replace(cross ? apps + 'dashboard#gbt=' + encodeURIComponent(a.token) : base + 'dashboard');
  } catch (e) {}
})();
</script>
<?php endif; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $_dash ? 'Your dashboard — GameBoard' : 'GameBoard — Tools for Board Game Makers' ?></title>
<meta name="description" content="<?= e($_desc) ?>">
<link rel="canonical" href="<?= e($_page) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="GameBoard">
<meta property="og:title" content="GameBoard — Tools for Board Game Makers">
<meta property="og:description" content="<?= e($_desc) ?>">
<meta property="og:url" content="<?= e($_page) ?>">
<meta property="og:image" content="<?= e($_abs . 'images/gameboard_icon_512.png') ?>">
<meta name="twitter:card" content="summary">
<link rel="icon" type="image/png" sizes="32x32" href="images/gameboard_icon_32.png" />
<link rel="apple-touch-icon" sizes="180x180" href="images/gameboard_icon_180.png" />
<meta name="apple-mobile-web-app-title" content="GameBoard" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Fraunces:ital,opsz,wght@0,9..144,600;1,9..144,400&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root { --navy:#14132a; --navy-2:#1f1d3d; --cream:#f6efe2; --red:#e5604f; --red-dark:#cc4e3e; --text:#1e293b; --mid:#475569; --muted:#94a3b8; --line:#e8e4dc; --paper:#fbf8f2; }
html { scroll-behavior:smooth; }
body { font-family:'Inter',system-ui,sans-serif; color:var(--text); background:#fff; line-height:1.6; }
a { color:inherit; }
[hidden] { display:none !important; }

/* Top bar: the same layout as every GameBoard app (CardBoard's) — icon and name left, sections in the
   middle, the round account button (profile picture) with its menu on the right */
@font-face { font-family:'DINBlack'; src:url('fonts/DINBlack.woff2') format('woff2'), url('fonts/DINBlack.ttf') format('truetype'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'), url('fonts/DINMedium.ttf') format('truetype'); }
nav.gbn { position:sticky; top:0; z-index:50; background:#1a1a2e; color:#fff; padding:.75rem 1.25rem; box-shadow:inset 0 -3px 0 var(--red); }
.gbn-in { display:grid; grid-template-columns:minmax(0,1fr) auto minmax(0,1fr); align-items:center; gap:.75rem; }
.brand { display:flex; align-items:center; gap:.65rem; text-decoration:none; color:#fff; min-width:0; }
.brand img { width:2.2rem; height:2.2rem; border-radius:7px; flex-shrink:0; }
.brand b { display:block; font-family:'DINBlack',sans-serif; font-weight:400; font-size:1rem; letter-spacing:.03em; line-height:1.15; }
.brand b span { color:var(--red); }
.brand small { display:block; font-family:'DINRegular',sans-serif; font-size:.73rem; opacity:.6; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.nav-links { display:flex; align-items:center; background:rgba(255,255,255,.12); border-radius:8px; padding:3px; }
.nav-links a { font-family:'DINBlack',sans-serif; font-size:.62rem; text-transform:uppercase; letter-spacing:.07em; padding:.3rem .9rem; border-radius:6px; color:rgba(255,255,255,.62); text-decoration:none; white-space:nowrap; }
.nav-links a:hover { color:#fff; }
.gbn-right { justify-self:end; position:relative; }
.acct-btn { width:2.1rem; height:2.1rem; padding:0; border:none; border-radius:50%; background:rgba(255,255,255,.14); color:#fff; display:flex; align-items:center; justify-content:center; overflow:hidden; cursor:pointer; }
.acct-btn:hover { background:rgba(255,255,255,.24); }
.acct-btn img { display:none; width:100%; height:100%; object-fit:cover; }
.acct-btn.has-pic img { display:block; } .acct-btn.has-pic svg { display:none; }
.gbn-menu { display:none; position:absolute; top:calc(100% + .4rem); right:0; min-width:200px; background:#1a1a2e; border:1px solid rgba(255,255,255,.18); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,.4); padding:.25rem 0; z-index:300; }
.gbn-menu.open { display:block; }
.gbn-who { padding:.55rem 1rem .5rem; font-size:.75rem; line-height:1.35; color:rgba(255,255,255,.55); border-bottom:1px solid rgba(255,255,255,.12); margin-bottom:.2rem; word-break:break-all; }
.gbn-who b { display:block; color:#fff; font-size:.86rem; word-break:normal; }
.gbn-menu button { display:block; width:100%; background:none; border:none; text-align:left; cursor:pointer; padding:.6rem 1rem; font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase; letter-spacing:.07em; color:rgba(255,255,255,.85); }
.gbn-menu button:hover { background:rgba(255,255,255,.1); color:#fff; }
@media (max-width:720px) { nav.gbn { padding:.5rem 1rem; } .gbn-in { display:flex; justify-content:space-between; } .nav-links { display:none; } }

.hero { position:relative; overflow:hidden; background:linear-gradient(180deg, #2a2148 0%, #221b3e 100%); border-bottom:1px solid rgba(255,255,255,.08); color:#fff; text-align:center; padding:6.5rem 1.5rem 5rem; }
.hero::before { content:''; position:absolute; top:-160px; left:50%; transform:translateX(-50%); width:820px; height:820px; background:radial-gradient(circle closest-side, rgba(229,96,79,.22) 0%, transparent 65%); pointer-events:none; }
.hero::after { content:''; position:absolute; bottom:-120px; right:8%; width:520px; height:520px; background:radial-gradient(circle closest-side, rgba(246,239,226,.08) 0%, transparent 70%); pointer-events:none; }
.hero > * { position:relative; }
/* The hero fills the first screen (under the 64px menu bar), its content centred, with a cue to scroll on */
.hero { min-height:calc(100vh - 64px); min-height:calc(100svh - 64px); display:flex; flex-direction:column; align-items:center; justify-content:center; padding-top:4rem; padding-bottom:7rem; box-sizing:border-box; }
.scroll-cue { position:absolute !important; left:50%; bottom:22px; transform:translateX(-50%); display:flex; flex-direction:column; align-items:center; gap:8px; color:rgba(255,255,255,.6); text-decoration:none; font-size:11.5px; font-weight:600; letter-spacing:.12em; text-transform:uppercase; z-index:2; transition:color .15s; }
.scroll-cue:hover { color:#fff; }
.scroll-cue .mouse { width:24px; height:38px; border:2px solid currentColor; border-radius:14px; position:relative; }
.scroll-cue .mouse::after { content:''; position:absolute; left:50%; top:7px; width:4px; height:8px; margin-left:-2px; border-radius:2px; background:currentColor; animation:wheel 1.8s ease-in-out infinite; }
@keyframes wheel { 0% { opacity:0; transform:translateY(-2px); } 30% { opacity:1; } 70% { opacity:1; transform:translateY(10px); } 100% { opacity:0; transform:translateY(12px); } }
.scroll-cue .chev { width:12px; height:12px; border-right:2px solid currentColor; border-bottom:2px solid currentColor; transform:rotate(45deg); margin-top:-4px; animation:chev 1.8s ease-in-out infinite; }
@keyframes chev { 0%,100% { transform:translateY(0) rotate(45deg); opacity:.5; } 50% { transform:translateY(6px) rotate(45deg); opacity:1; } }
@media (max-height:620px) { .scroll-cue { display:none; } }
.tag { display:inline-block; font-size:12px; font-weight:500; letter-spacing:.08em; text-transform:uppercase; color:#f3b4aa; border:1px solid rgba(243,180,170,.35); border-radius:999px; padding:5px 14px; margin-bottom:1.6rem; }
.hero h1 { font-family:'Fraunces',Georgia,serif; font-weight:600; font-size:clamp(2.3rem,5vw,3.7rem); line-height:1.1; letter-spacing:-.03em; max-width:820px; margin:0 auto 1.4rem; }
.hero h1 em { font-style:italic; font-weight:400; color:var(--red); }
.hero p { color:rgba(255,255,255,.72); font-size:1.12rem; max-width:600px; margin:0 auto 1.6rem; }
/* A little game-board path: each app's space lights up in turn */
.path { position:relative; display:grid; grid-template-columns:repeat(4, 92px); justify-content:center; gap:0 34px; margin:0 auto 1.6rem; padding-bottom:52px; }
.path-line { position:absolute; left:calc(50% - 189px); right:calc(50% - 189px); top:26px; height:3px; border-radius:2px; background:repeating-linear-gradient(90deg, rgba(255,255,255,.28) 0 8px, transparent 8px 16px); }
.space { position:relative; display:flex; flex-direction:column; align-items:center; gap:8px; }
.space img { width:52px; height:52px; border-radius:13px; box-shadow:0 6px 16px rgba(0,0,0,.35); animation:spaceOn 6s infinite; animation-delay:var(--d); }
.sp-lab { position:absolute; top:62px; left:50%; transform:translate(-50%,6px); white-space:nowrap; text-align:center; opacity:0; animation:spaceTxt 6s infinite; animation-delay:var(--d); pointer-events:none; }
.sp-lab b { display:block; font-family:'Fraunces',Georgia,serif; font-size:16px; font-weight:600; color:var(--c); }
.sp-lab small { display:block; font-size:12.5px; color:rgba(255,255,255,.7); margin-top:1px; }
@keyframes spaceOn { 0% { transform:none; box-shadow:0 6px 16px rgba(0,0,0,.35); } 3%,22% { transform:translateY(-3px) scale(1.08); box-shadow:0 0 0 3px var(--c), 0 0 26px var(--c); } 26%,100% { transform:none; box-shadow:0 6px 16px rgba(0,0,0,.35); } }
@keyframes spaceTxt { 0% { opacity:0; transform:translate(-50%,6px); } 4%,21% { opacity:1; transform:translate(-50%,0); } 25%,100% { opacity:0; transform:translate(-50%,6px); } }
@media (max-width:520px) { .path { grid-template-columns:repeat(4, 64px); gap:0 14px; } .path-line { display:none; } .space img { width:44px; height:44px; } .sp-lab { top:54px; } }
.btns { display:flex; gap:.9rem; justify-content:center; flex-wrap:wrap; }
.btn { display:inline-block; text-decoration:none; font:600 15px 'Inter',sans-serif; padding:13px 26px; border-radius:999px; cursor:pointer; border:none; }
.btn.primary { background:var(--red); color:#fff; }
.btn.primary:hover { background:var(--red-dark); }
.btn.ghost { background:transparent; color:rgba(255,255,255,.85); border:1px solid rgba(255,255,255,.28); }
.btn.ghost:hover { border-color:rgba(255,255,255,.6); color:#fff; }

/* The journey: four steps, one app each */
.journey { display:flex; justify-content:center; align-items:center; gap:.6rem; flex-wrap:wrap; margin-top:3.2rem; }
.step { display:flex; align-items:center; gap:.55rem; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12); border-radius:999px; padding:.35rem .9rem .35rem .4rem; font-size:13px; color:rgba(255,255,255,.85); text-decoration:none; }
.step img { width:26px; height:26px; border-radius:6px; }
.step:hover { background:rgba(255,255,255,.1); }
.arrow { color:rgba(255,255,255,.3); }

section { padding:5rem 1.5rem; }
.wrap { max-width:1120px; margin:0 auto; }
.kicker { font-size:12px; font-weight:600; letter-spacing:.1em; text-transform:uppercase; color:var(--red); margin-bottom:.6rem; text-align:center; }
h2 { font-family:'Fraunces',Georgia,serif; font-weight:600; font-size:clamp(1.8rem,3.4vw,2.5rem); letter-spacing:-.02em; line-height:1.15; text-align:center; color:var(--navy); margin-bottom:.8rem; }
.sub { text-align:center; color:var(--mid); max-width:620px; margin:0 auto 3rem; }

.apps { display:grid; grid-template-columns:repeat(auto-fit,minmax(250px,1fr)); gap:1.3rem; }
.app { display:flex; flex-direction:column; border:1px solid var(--line); border-radius:16px; padding:1.6rem 1.5rem 1.4rem; background:#fff; transition:box-shadow .15s, transform .15s; position:relative; overflow:hidden; }
.app::before { content:''; position:absolute; left:0; right:0; top:0; height:4px; background:var(--c); }
.app:hover { box-shadow:0 14px 36px rgba(20,19,42,.10); transform:translateY(-2px); }
.app-head { display:flex; align-items:center; gap:.75rem; margin-bottom:.9rem; }
.app-head img { width:48px; height:48px; border-radius:11px; }
.app-name { font-family:'Fraunces',Georgia,serif; font-size:1.3rem; font-weight:600; color:var(--navy); line-height:1.1; }
.app-stage { font-size:12px; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:var(--muted); }
.app-line { font-family:'Fraunces',Georgia,serif; font-style:italic; font-size:1.05rem; color:var(--text); margin-bottom:.9rem; }
.app ul { list-style:none; margin-bottom:1.4rem; flex:1; }
.app li { position:relative; padding-left:1.25rem; font-size:14px; color:var(--mid); margin-bottom:.45rem; }
.app li::before { content:''; position:absolute; left:0; top:.55em; width:7px; height:7px; border-radius:2px; background:var(--c); }
.app-price { display:flex; align-items:baseline; gap:.35rem; margin:-.4rem 0 1rem; padding-top:.8rem; border-top:1px dashed var(--line); }
.app-price b { font-family:'Fraunces',Georgia,serif; font-size:1.45rem; color:var(--navy); }
.app-price span { font-size:12.5px; color:var(--muted); }
.app-btns { display:flex; gap:.5rem; }
/* Pricing: one app at a time, or all four */
.pricing { background:var(--navy); color:#fff; position:relative; overflow:hidden; }
.pricing::before { content:''; position:absolute; right:-10%; top:-20%; width:640px; height:640px; background:radial-gradient(circle closest-side, rgba(229,96,79,.2), transparent 65%); pointer-events:none; }
.pricing h2 { color:#fff; } .pricing .sub { color:rgba(255,255,255,.65); }
.price-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:1rem; align-items:stretch; position:relative; }
.pc.all { grid-column:1 / -1; margin-top:1.2rem; }
@media (min-width:861px) { .pc.all { display:grid; grid-template-columns:1fr 1.3fr auto; gap:2rem; align-items:center; padding:1.8rem 2rem; } .pc.all ul { margin:0; } .pc.all button { padding:13px 30px; } .pc.all .pc-was { margin-bottom:0; } }
@media (max-width:1000px) { .price-grid { grid-template-columns:repeat(2,1fr); } }
@media (max-width:520px) { .price-grid { grid-template-columns:1fr; } }
.pc { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.12); border-radius:16px; padding:1.3rem 1.2rem; display:flex; flex-direction:column; }
.pc-head { display:flex; align-items:center; gap:.6rem; margin-bottom:.9rem; }
.pc-head img { width:40px; height:40px; border-radius:10px; }
.pc-head small { display:block; font-size:10.5px; font-weight:600; letter-spacing:.07em; text-transform:uppercase; color:rgba(255,255,255,.5); margin-top:2px; }
.pc { border-top:3px solid var(--c, rgba(255,255,255,.12)); }
.pc-line { font-family:'Fraunces',Georgia,serif; font-style:italic; font-size:1rem; color:rgba(255,255,255,.92); margin:-.2rem 0 .8rem; line-height:1.35; }
.pc-feat { list-style:none; margin:0 0 1rem; flex:1; }
.pc-feat li { position:relative; padding-left:1.1rem; font-size:13.5px; color:rgba(255,255,255,.72); margin-bottom:.45rem; line-height:1.45; }
.pc-feat li::before { content:''; position:absolute; left:0; top:.55em; width:6px; height:6px; border-radius:2px; background:var(--c); }
.pc-btns { display:flex; gap:.45rem; }
.pc-btns a { flex:1; }
.pc-btns a:first-child { background:#fff; color:var(--navy); border-color:#fff; }
.pc-btns a:first-child:hover { background:#f1eee8; }
.pc-was { font-size:13px; color:rgba(255,255,255,.9); margin:.35rem 0 .9rem; } .pc-was s { opacity:.75; }
.pc-head b { font-family:'Fraunces',Georgia,serif; font-size:1.1rem; }
.pc-price { font-family:'Fraunces',Georgia,serif; font-size:2rem; font-weight:600; line-height:1; white-space:nowrap; padding-top:.9rem; border-top:1px dashed rgba(255,255,255,.15); }
.pc-price small { font-family:'Inter',sans-serif; font-size:13px; font-weight:400; color:rgba(255,255,255,.6); margin-left:.2rem; }
.pc-note { font-size:13px; color:rgba(255,255,255,.65); margin:.45rem 0 1rem; min-height:2.9em; }
.pro-mark { color:var(--c, #49c4d4); font-size:.85em; margin-left:.25em; vertical-align:.08em; cursor:help; }
.pc-note .pro-mark { margin:0; }
.pc a, .pc button { display:block; text-align:center; text-decoration:none; font:600 14px 'Inter',sans-serif; color:#fff; border:1px solid rgba(255,255,255,.3); border-radius:10px; padding:10px; background:transparent; cursor:pointer; }
.pc a:hover { border-color:#fff; }
.pc.all { background:linear-gradient(160deg, #f0766a 0%, #e5604f 45%, #c94a3a 100%); border:none; box-shadow:0 24px 60px rgba(229,96,79,.35); position:relative; }
.pc.all .ribbon { position:absolute; top:-12px; left:50%; transform:translateX(-50%); white-space:nowrap; box-shadow:0 4px 12px rgba(0,0,0,.2); background:#fff; color:#c94a3a; font-size:11px; font-weight:800; letter-spacing:.06em; text-transform:uppercase; border-radius:999px; padding:4px 10px; }
.pc.all .pc-price { font-size:2.6rem; border-top:none; padding-top:0; } .pc.all { border-top:none; }
.pc.all .pc-price small, .pc.all .pc-note { color:rgba(255,255,255,.88); }
.pc.all .icons { display:flex; gap:6px; margin-bottom:.9rem; } .pc.all .icons img { width:34px; height:34px; border-radius:9px; box-shadow:0 4px 10px rgba(0,0,0,.25); }
.pc.all ul { list-style:none; margin:.2rem 0 1.1rem; flex:1; }
.pc.all li { padding-left:1.4rem; position:relative; font-size:14px; margin-bottom:.4rem; }
.pc.all li::before { content:'✓'; position:absolute; left:0; font-weight:800; }
.pc.all button { background:#fff; color:#c94a3a; border:none; }
.pc.all button:hover { background:#fff3ef; }
.save { text-decoration:line-through; opacity:.7; font-size:15px; margin-left:.4rem; font-family:'Inter',sans-serif; font-weight:500; }
.app-btns a { flex:1; display:flex; align-items:center; justify-content:center; text-align:center; text-decoration:none; font:600 14px 'Inter',sans-serif; padding:10px 12px; border-radius:10px; }
.app-btns .f { background:var(--navy); color:#fff; }
.app-btns .f:hover { background:var(--navy-2); }
.app-btns .o { border:1px solid var(--line); color:var(--navy); }
.app-btns .o:hover { border-color:var(--navy); }

.account { background:var(--paper); border-top:1px solid var(--line); border-bottom:1px solid var(--line); }
.acct-grid { display:grid; grid-template-columns:1.1fr .9fr; gap:2.5rem; align-items:center; }
@media (max-width:820px) { .acct-grid { grid-template-columns:1fr; } }
.acct-grid h2, .acct-grid .kicker { text-align:left; }
.points { list-style:none; margin-top:1.2rem; }
.points li { padding-left:1.7rem; position:relative; margin-bottom:.7rem; color:var(--mid); }
.points li::before { content:'✓'; position:absolute; left:0; color:var(--red); font-weight:700; }
.card { background:#fff; border:1px solid var(--line); border-radius:16px; padding:1.6rem; box-shadow:0 10px 30px rgba(20,19,42,.06); }
.card h3 { font-family:'Fraunces',Georgia,serif; font-size:1.25rem; color:var(--navy); margin-bottom:.4rem; }
.card p { font-size:14px; color:var(--mid); margin-bottom:1rem; }
.me { display:flex; align-items:center; gap:.8rem; margin-bottom:1rem; }
.me-dot { width:44px; height:44px; border-radius:50%; background:var(--navy); color:var(--cream); display:flex; align-items:center; justify-content:center; font:600 18px 'Inter',sans-serif; flex-shrink:0; }
/* Your picture: click or drop one on it to change it */
label.me-dot { position:relative; overflow:hidden; cursor:pointer; }
.me-dot img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; }
.me-dot .cam { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(22,20,40,.55); color:#fff; opacity:0; transition:opacity .15s; }
.me-dot .cam svg { width:18px; height:18px; }
.me-dot:hover .cam, .me-dot.drop .cam, .me-dot.busy .cam { opacity:1; }
.me-dot.drop { outline:3px dashed var(--navy); outline-offset:3px; }
.me-dot.busy .cam svg { display:none; }
.me-dot.busy .cam::after { content:''; width:16px; height:16px; border:2.5px solid rgba(255,255,255,.35); border-top-color:#fff; border-radius:50%; animation:mespin .7s linear infinite; }
@keyframes mespin { to { transform:rotate(360deg); } }
.me-msg { display:block; font-size:12px; color:var(--muted); min-height:0; }
.me-msg.err { color:#c0392b; }
.me b { display:block; color:var(--navy); }
.me span { font-size:13px; color:var(--muted); }
.plan { font-size:13px; background:#fdf0ed; color:#a8463a; border-radius:8px; padding:.55rem .75rem; margin-bottom:1rem; }
.plan.ok { background:#edf7ee; color:#2e7d32; }
.my-apps { list-style:none; margin:-.4rem 0 1rem; padding:0; }
.my-apps li { display:flex; align-items:center; gap:.6rem; padding:.42rem 0; border-bottom:1px solid #f0ece4; font-size:13px; }
.my-apps li:last-child { border-bottom:none; }
.my-apps img { width:26px; height:26px; border-radius:7px; flex-shrink:0; }
.my-apps b { color:var(--navy); font-weight:600; min-width:84px; }
.my-apps span { color:var(--muted); margin-left:auto; text-align:right; }
.my-apps span.paid { color:#2e7d32; font-weight:600; }
/* Your sheets, by app: each opens in its app */
.my-sheets { margin:-.2rem 0 1rem; }
.my-sheets h4 { display:flex; align-items:center; gap:.45rem; margin:.9rem 0 .2rem; font:600 12px 'Inter',sans-serif; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); }
.my-sheets h4:first-child { margin-top:0; }
.my-sheets h4 img { width:20px; height:20px; border-radius:5px; }
.my-sheets a.sh { display:flex; align-items:baseline; gap:.6rem; padding:.45rem .5rem; margin:0 -.5rem; border-radius:8px; text-decoration:none; color:var(--navy); font-size:13px; }
.my-sheets a.sh:hover { background:#f6f2ea; }
.my-sheets a.sh { align-items:center; }
.my-sheets .sh-nm { display:flex; flex-direction:column; min-width:0; }
.my-sheets .sh-nm b { font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.my-sheets .sh-nm small { font-size:12px; color:var(--muted); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.my-sheets .sh-id { display:none; margin-top:.2rem; font:12px ui-monospace,Menlo,monospace; color:var(--mid); background:#f6f2ea; border-radius:5px; padding:.1rem .4rem; width:max-content; max-width:100%; overflow-wrap:anywhere; user-select:all; cursor:text; }
.my-sheets a.sh.show-id .sh-id { display:block; }
.my-sheets .sh-eye { flex-shrink:0; width:28px; height:28px; border:none; border-radius:50%; background:transparent; color:var(--muted); display:flex; align-items:center; justify-content:center; cursor:pointer; padding:0; }
.my-sheets .sh-eye svg { width:16px; height:16px; }
.my-sheets .sh-eye:hover, .my-sheets a.sh.show-id .sh-eye { background:#efe9de; color:var(--navy); }
.my-sheets .sh-when { margin-left:auto; color:var(--muted); font-size:12px; white-space:nowrap; flex-shrink:0; }
.my-sheets .sh-new { display:flex; flex-wrap:wrap; gap:.3rem; margin:.15rem 0 .4rem; }
.my-sheets .sh-new a { font-size:12px; color:var(--mid); border:1px solid var(--line); border-radius:999px; padding:.15rem .6rem; text-decoration:none; }
.my-sheets .sh-new a:hover { border-color:var(--navy); color:var(--navy); }
.my-sheets a.app-row { display:flex; align-items:center; gap:.6rem; padding:.5rem .5rem; margin:.7rem -.5rem .1rem; border-radius:10px; text-decoration:none; color:var(--navy); }
.my-sheets a.app-row:first-of-type { margin-top:0; }
.my-sheets a.app-row:hover { background:#f6f2ea; }
.my-sheets a.app-row img { width:30px; height:30px; border-radius:8px; flex-shrink:0; }
.my-sheets a.app-row b { font:600 14px 'Inter',sans-serif; display:flex; flex-direction:column; }
.my-sheets a.app-row b small { font:500 11.5px 'Inter',sans-serif; color:#2e7d32; }
.my-sheets a.app-row span { margin-left:auto; font:600 12px 'Inter',sans-serif; color:#fff; background:var(--navy); border-radius:999px; padding:.3rem .85rem; }
.my-sheets a.sh { padding-left:2.9rem; }
.account.dash { min-height:calc(100vh - 140px); }
.account.dash .acct-grid { grid-template-columns:minmax(0,1fr); max-width:760px; }
/* Long sheet names never make the page wider than the phone: the grid's columns may shrink, and so may each row's text */
.acct-grid > * { min-width:0; }
.my-sheets a.sh .sh-nm { flex:1 1 auto; }
@media (max-width:560px) {
  .account.dash .card { padding:1.1rem 1rem; border-radius:14px; }
  .my-sheets a.sh { padding-left:2.4rem; gap:.4rem; }
  .my-sheets a.app-row { margin-left:-.35rem; margin-right:-.35rem; }
  .my-sheets .sh-when { font-size:11px; }
}
.account.dash .dash-sub { color:var(--mid); margin:.4rem 0 0; }
.my-sheets .sh-none { font-size:13px; color:var(--muted); margin:0; }
.pc.all button:disabled { opacity:.85; cursor:default; background:#fff; }
.link { background:none; border:none; color:var(--mid); text-decoration:underline; text-underline-offset:2px; font:inherit; font-size:13px; cursor:pointer; }

.own { text-align:center; }
.own-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1.2rem; margin-top:2.4rem; text-align:left; }
.own-grid div { border:1px solid var(--line); border-radius:14px; padding:1.2rem 1.3rem; }
.own-grid b { display:block; color:var(--navy); margin-bottom:.3rem; }
.own-grid span { font-size:14px; color:var(--mid); }

footer { text-align:center; font-size:13px; color:var(--muted); padding:2.2rem 1rem 3rem; }
footer a { text-decoration:none; margin:0 .4rem; }
footer a:hover { color:var(--navy); }

/* Sign in */
.overlay { position:fixed; inset:0; z-index:200; background:rgba(20,19,42,.55); display:none; align-items:center; justify-content:center; padding:1rem; }
.overlay.open { display:flex; }
.dlg { width:min(420px,100%); background:#fff; border-radius:18px; padding:1.6rem 1.6rem 1.3rem; box-shadow:0 30px 70px rgba(0,0,0,.35); position:relative; }
.dlg-x { position:absolute; top:.7rem; right:.8rem; border:none; background:none; font-size:1.4rem; color:var(--muted); cursor:pointer; }
.dlg h3 { font-family:'Fraunces',Georgia,serif; font-size:1.35rem; color:var(--navy); margin-bottom:.3rem; }
.dlg p.m { font-size:14px; color:var(--mid); margin-bottom:1rem; }
.choose { display:flex; flex-direction:column; gap:.6rem; }
.opt { display:flex; flex-direction:column; align-items:center; gap:.15rem; padding:.95rem; border:1.5px solid var(--line); border-radius:12px; background:#fff; cursor:pointer; font:inherit; color:var(--navy); }
.opt b { font-weight:600; }
.opt span { font-size:13px; color:var(--muted); }
.opt.primary { background:var(--red); border-color:var(--red); color:#fff; }
.opt.primary span { color:rgba(255,255,255,.85); }
.opt:hover { border-color:var(--navy); }
.opt.primary:hover { background:var(--red-dark); border-color:var(--red-dark); }
.inp { display:block; width:100%; padding:.7rem .85rem; border:1.5px solid var(--line); border-radius:10px; font:15px 'Inter',sans-serif; margin-bottom:.6rem; outline:none; background:#fff; }
.inp:focus { border-color:var(--navy); }
.code { letter-spacing:.35em; text-align:center; font-family:ui-monospace,Menlo,monospace; font-size:18px; }
.go { width:100%; border:none; border-radius:10px; background:var(--navy); color:#fff; font:600 15px 'Inter',sans-serif; padding:.8rem; cursor:pointer; margin-top:.2rem; }
.go:disabled { opacity:.55; cursor:default; }
.rules { list-style:none; display:grid; grid-template-columns:1fr 1fr; gap:.2rem .8rem; font-size:12.5px; color:var(--muted); margin:.1rem 0 .7rem; }
.rules li.ok { color:#2e7d32; }
.rules li::before { content:'○ '; }
.rules li.ok::before { content:'● '; }
.err { color:#c0392b; font-size:13px; min-height:1.1rem; margin-top:.5rem; }
.foot { text-align:center; margin-top:.8rem; }

/* ── Hero: the four app icons float around the headline ─────────────────── */
.floaters { position:absolute; inset:0; pointer-events:none; }
.floaters img { position:absolute; width:74px; height:74px; border-radius:18px; box-shadow:0 18px 40px rgba(0,0,0,.45); animation:bob 7s ease-in-out infinite; opacity:.95; }
.floaters img:nth-child(1) { left:7%;  top:24%; transform:rotate(-10deg); animation-delay:0s; }
.floaters img:nth-child(2) { left:12%; top:64%; transform:rotate(7deg);  animation-delay:-2s; width:62px; height:62px; }
.floaters img:nth-child(3) { right:8%; top:22%; transform:rotate(9deg);  animation-delay:-4s; }
.floaters img:nth-child(4) { right:12%; top:62%; transform:rotate(-6deg); animation-delay:-1s; width:62px; height:62px; }
@keyframes bob { 0%,100% { translate:0 0; } 50% { translate:0 -16px; } }
@media (max-width:980px) { .floaters { display:none; } }
.hero h1 em { background:linear-gradient(90deg,#f0766a,#f7b267,#9ed29f,#7b6cf6,#49c4d4,#f0766a); background-size:300% 100%; -webkit-background-clip:text; background-clip:text; color:transparent; animation:shine 10s linear infinite; }
@keyframes shine { to { background-position:300% 0; } }

/* ── See them in action: one animated window per app ─────────────────────── */
.action { background:var(--navy); color:#fff; position:relative; overflow:hidden; }
.action::before { content:''; position:absolute; top:10%; left:-10%; width:600px; height:600px; background:radial-gradient(circle closest-side, rgba(123,108,246,.18), transparent 65%); pointer-events:none; }
.action h2 { color:#fff; }
.action .sub { color:rgba(255,255,255,.65); }
.show { display:grid; grid-template-columns:1fr 1.15fr; gap:3.5rem; align-items:center; margin:0 auto 5.5rem; max-width:1040px; position:relative; }
.show:last-child { margin-bottom:0; }
.show.flip { grid-template-columns:1.15fr 1fr; }
.show.flip .show-text { order:2; }
@media (max-width:860px) { .show, .show.flip { grid-template-columns:1fr; gap:1.8rem; } .show.flip .show-text { order:0; } }
.show-text .badge { display:inline-flex; align-items:center; gap:.55rem; font-size:12px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; color:var(--c); margin-bottom:.8rem; }
.show-text .badge img { width:34px; height:34px; border-radius:8px; }
.show-text h3 { font-family:'Fraunces',Georgia,serif; font-size:clamp(1.6rem,2.8vw,2.1rem); line-height:1.15; letter-spacing:-.02em; margin-bottom:.8rem; }
.show-text p { color:rgba(255,255,255,.7); margin-bottom:1.2rem; }
.show-text a { color:var(--c); font-weight:600; text-decoration:none; }
.show-text a:hover { text-decoration:underline; }

.win { background:#fff; color:var(--text); border-radius:14px; overflow:hidden; box-shadow:0 30px 80px rgba(0,0,0,.45), 0 0 0 1px rgba(255,255,255,.06); position:relative; }
.win-bar { display:flex; align-items:center; gap:6px; height:34px; padding:0 12px; background:#f1eff6; border-bottom:1px solid #e6e3ee; }
.win-bar i { width:10px; height:10px; border-radius:50%; background:#e2dfea; }
.win-bar i:nth-child(1) { background:#f0766a; } .win-bar i:nth-child(2) { background:#f7c65a; } .win-bar i:nth-child(3) { background:#7fd08a; }
.win-bar span { margin-left:10px; flex:1; font-size:11.5px; color:#8a8fa8; background:#fff; border-radius:6px; padding:3px 10px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.win-body { height:340px; position:relative; overflow:hidden; }
@keyframes appear { 0% { opacity:0; transform:translateY(10px); } 6%,82% { opacity:1; transform:none; } 90%,100% { opacity:0; transform:none; } }

/* DevBoard: the "+ Session" dialog — rounds and timer, who's playing (a new tester's email goes to
   People), observations beside solutions, the light bulb making a TODO — then the saved session */
.dvs { height:410px; background:#e9eef3; position:relative; font-size:11px; color:#1e2b38; }
.dvs-dlg { position:absolute; inset:10px; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(20,40,60,.18); padding:10px 12px; animation:dvsDlg 18s infinite; }
@keyframes dvsDlg { 0%,74% { opacity:1; transform:none; } 77%,95% { opacity:0; transform:scale(.97); } 98%,100% { opacity:1; transform:none; } }
.dvs-h { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
.dvs-h b { font-size:14px; color:#16324f; } .dvs-h b em { font-style:normal; color:#1a5f7a; }
.dvs-ctl { display:flex; align-items:center; gap:8px; }
.dvs-rounds { display:flex; align-items:center; gap:4px; font-size:9px; letter-spacing:.06em; text-transform:uppercase; color:#7a8a99; }
.dvs-rb { width:18px; height:18px; border-radius:5px; border:1.5px solid #c9d3dc; display:flex; align-items:center; justify-content:center; color:#1a5f7a; font-weight:800; }
.dvs-rb.plus { animation:dvsPress 18s infinite; animation-delay:.9s; }
@keyframes dvsPress { 0%,1% { background:#fff; transform:none; } 2%,3% { background:#e3f1f5; transform:scale(.88); } 4%,100% { background:#fff; transform:none; } }
.dvs-rc { min-width:18px; text-align:center; font:800 13px 'Inter',sans-serif; color:#16324f; }
.dvs-sw { display:flex; align-items:center; gap:5px; background:#1a5f7a; color:#fff; border-radius:999px; padding:3px 9px; font:700 11px ui-monospace,Menlo,monospace; }
.dvs-sw i { width:7px; height:7px; border-radius:50%; background:#ff8f7d; animation:blink 1.2s steps(2) infinite; }
.dvs-meta { display:grid; grid-template-columns:1fr 1fr 1.15fr; gap:6px 8px; }
.dvs-fg label, .dvs-ol label { display:block; font-size:8.5px; font-weight:800; letter-spacing:.07em; text-transform:uppercase; color:#8796a5; margin-bottom:2px; }
.dvs-in { border:1.5px solid #d0d8e0; border-radius:6px; padding:3px 6px; min-height:22px; white-space:nowrap; overflow:hidden; position:relative; }
.dvs-in.ro { background:#f0f4f8; color:#888; }
.dvs-people { grid-row:span 2; grid-column:3; }
.dvs-people .dvs-in { margin-bottom:4px; }
.dvs-new { animation:dvsFocus 18s infinite; }
@keyframes dvsFocus { 0%,7% { border-color:#d0d8e0; box-shadow:none; } 8%,22% { border-color:#1a5f7a; box-shadow:0 0 0 2px rgba(26,95,122,.15); } 24%,100% { border-color:#d0d8e0; box-shadow:none; } }
.dvs-mail { color:#1a5f7a; }
.sw2 { display:inline-grid; } .sw2 > * { grid-area:1/1; }
.dvs-new .full { clip-path:inset(0 100% 0 0); animation:dvsTypeMail 18s infinite steps(30); }
@keyframes dvsTypeMail { 0%,8% { clip-path:inset(0 100% 0 0); opacity:1; } 21%,69% { clip-path:inset(0 0 0 0); opacity:1; } 70%,100% { clip-path:inset(0 0 0 0); opacity:0; } }
.dvs-new .clean { opacity:0; animation:dvsClean 18s infinite; }
@keyframes dvsClean { 0%,69% { opacity:0; } 70%,96% { opacity:1; } 99%,100% { opacity:0; } }
.dvs-rcs .a { animation:dvsA 18s infinite; } .dvs-rcs .b { animation:dvsB 18s infinite; }
@keyframes dvsA { 0%,5% { opacity:1; } 6%,96% { opacity:0; } 99%,100% { opacity:1; } } @keyframes dvsB { 0%,5% { opacity:0; } 6%,96% { opacity:1; } 99%,100% { opacity:0; } }
.dvs-hr { border:none; border-top:1px solid #e6ebf0; margin:8px 0 6px; }
.dvs-ol { display:grid; grid-template-columns:1fr 1fr; }
.dvs-pair { display:grid; grid-template-columns:1fr 1fr; border:1.5px solid #d0d8e0; border-radius:8px; overflow:hidden; margin-bottom:5px; position:relative; }
.dvs-pair > div { padding:6px 8px; min-height:30px; white-space:nowrap; overflow:hidden; }
.dvs-pair > div:first-child { border-right:1px solid #d0d8e0; background:#fff; position:relative; }
.dvs-pair > div:nth-child(2) { background:#f5f7fa; }
.dvs-pair.p1 { animation:dvsTodo 18s infinite; }
@keyframes dvsTodo { 0%,50% { border-color:#d0d8e0; box-shadow:none; } 51%,96% { border-color:#f5c518; box-shadow:0 0 0 2px rgba(245,197,24,.35); } 99%,100% { border-color:#d0d8e0; box-shadow:none; } }
.dvs-bulb { position:absolute; right:5px; top:5px; width:20px; height:20px; border-radius:5px; border:1px solid #d0d8e4; background:rgba(255,255,255,.9); color:#a0a8b4; display:flex; align-items:center; justify-content:center; animation:dvsBulb 18s infinite; opacity:0; }
.dvs-bulb svg { width:13px; height:13px; }
@keyframes dvsBulb { 0%,26% { opacity:0; } 28%,49% { opacity:1; color:#a0a8b4; background:#fff; border-color:#d0d8e4; transform:none; } 50% { transform:scale(.85); } 51%,96% { opacity:1; color:#b8860b; background:#fff8db; border-color:#f5c518; transform:none; } 99%,100% { opacity:0; } }
.dvs-bulb .glass { fill:none; animation:dvsGlass 18s infinite; } @keyframes dvsGlass { 0%,50% { fill:none; } 51%,96% { fill:#ffd43b; } 99%,100% { fill:none; } }
.ty { display:inline-block; clip-path:inset(0 100% 0 0); }
.ty.o1 { animation:dvsT1 18s infinite steps(28); } .ty.s1 { animation:dvsT2 18s infinite steps(26); } .ty.o2 { animation:dvsT3 18s infinite steps(24); }
@keyframes dvsT1 { 0%,24% { clip-path:inset(0 100% 0 0); } 34%,96% { clip-path:inset(0 0 0 0); } 99%,100% { clip-path:inset(0 100% 0 0); } }
@keyframes dvsT2 { 0%,36% { clip-path:inset(0 100% 0 0); } 46%,96% { clip-path:inset(0 0 0 0); } 99%,100% { clip-path:inset(0 100% 0 0); } }
@keyframes dvsT3 { 0%,52% { clip-path:inset(0 100% 0 0); } 58%,96% { clip-path:inset(0 0 0 0); } 99%,100% { clip-path:inset(0 100% 0 0); } }
.dvs-hint { font-size:8.5px; color:#9aa7b3; }
/* A photo in an observation: the image button (empty cells only), uploading, then the picture with its trash button */
.dvs-pair.p3 > div:first-child { position:relative; padding:0; min-height:30px; animation:dvsRowH 18s infinite; }
@keyframes dvsRowH { 0%,60% { height:30px; } 65%,96% { height:74px; } 99%,100% { height:30px; } }
.dvs-ib { position:absolute; right:5px; top:5px; width:20px; height:20px; border-radius:5px; border:1px solid #d0d8e4; background:#fff; color:#99a; display:flex; align-items:center; justify-content:center; animation:dvsIb 18s infinite; }
.dvs-ib svg { width:12px; height:12px; }
/* In the app the image button sits in every empty observation, and goes as soon as you start typing in it */
.dvs-ib.ib1 { animation-name:dvsIb1; } .dvs-ib.ib2 { animation-name:dvsIb2; }
@keyframes dvsIb1 { 0%,24% { opacity:1; } 24.5%,98% { opacity:0; } 99%,100% { opacity:1; } }
@keyframes dvsIb2 { 0%,52% { opacity:1; } 52.5%,98% { opacity:0; } 99%,100% { opacity:1; } }
@keyframes dvsIb { 0%,58% { opacity:1; transform:none; color:#99a; } 59.5% { transform:scale(.82); color:#1a5f7a; border-color:#a0b8c8; } 60.5%,97% { opacity:0; } 99%,100% { opacity:1; } }
.dvs-upl { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; gap:6px; color:#999; font-size:10px; opacity:0; animation:dvsUpl 18s infinite; }
.dvs-upl i { width:13px; height:13px; border:2px solid #d0d8e0; border-top-color:#1a5f7a; border-radius:50%; animation:spin .8s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
@keyframes dvsUpl { 0%,60% { opacity:0; } 61%,64% { opacity:1; } 65%,100% { opacity:0; } }
.dvs-photo { position:absolute; inset:0; background:url(images/billboard-features/floristry-board.webp) center/cover; opacity:0; animation:dvsPhoto 18s infinite; }
@keyframes dvsPhoto { 0%,64% { opacity:0; } 66%,96% { opacity:1; } 99%,100% { opacity:0; } }
.dvs-trash { position:absolute; right:5px; bottom:5px; width:20px; height:20px; border-radius:5px; border:1px solid #e8c0c0; background:rgba(255,255,255,.92); color:#c0392b; display:flex; align-items:center; justify-content:center; }
.dvs-trash svg { width:12px; height:12px; }
.ty.s3 { animation:dvsT4 18s infinite steps(22); }
@keyframes dvsT4 { 0%,65% { clip-path:inset(0 100% 0 0); } 68%,96% { clip-path:inset(0 0 0 0); } 99%,100% { clip-path:inset(0 100% 0 0); } }
.dvs-sess .thumb { float:right; width:46px; height:34px; border-radius:5px; background:url(images/billboard-features/floristry-board.webp) center/cover; margin-left:8px; box-shadow:0 1px 3px rgba(0,0,0,.15); }
.dvs-act { display:flex; justify-content:flex-end; gap:6px; margin-top:4px; }
.dvs-act span { font-weight:700; font-size:10.5px; border-radius:7px; padding:5px 11px; background:#eef2f5; color:#555; }
.dvs-act span.add { background:#1a5f7a; color:#fff; animation:dvsAdd 18s infinite; }
@keyframes dvsAdd { 0%,68% { transform:none; } 69% { transform:scale(.9); } 70%,100% { transform:none; } }
.dvs-pop { position:absolute; right:14px; top:212px; background:#16324f; color:#fff; font-size:10.5px; border-radius:8px; padding:6px 10px; opacity:0; animation:dvsPop 18s infinite; z-index:3; }
.dvs-pop b { color:#9fe0ee; font-weight:600; }
@keyframes dvsPop { 0%,69% { opacity:0; transform:translateY(6px); } 71%,75% { opacity:1; transform:none; } 77%,100% { opacity:0; } }
/* after Save: the game's card, with the new session and its TODO at the top */
.dvs-card { position:absolute; inset:10px; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(20,40,60,.18); padding:12px 14px; opacity:0; animation:dvsCard 18s infinite; }
@keyframes dvsCard { 0%,76% { opacity:0; transform:translateY(10px); } 79%,95% { opacity:1; transform:none; } 97%,100% { opacity:0; } }
.dvs-card h4 { font-size:15px; color:#16324f; margin-bottom:2px; } .dvs-card small { color:#8796a5; }
.dvs-todos { background:#fff8db; border:1.5px solid #f5c518; border-radius:8px; padding:7px 9px; margin:9px 0; }
.dvs-todos b { font-size:9px; letter-spacing:.07em; text-transform:uppercase; color:#b8860b; display:flex; align-items:center; gap:5px; margin-bottom:4px; }
.dvs-todos div { display:flex; align-items:center; gap:7px; color:#3a3000; }
.dvs-todos i { width:13px; height:13px; border:1.5px solid #c9a227; border-radius:3px; display:inline-block; }
.dvs-sess { border-top:1px solid #eef1f4; padding:7px 0; }
.dvs-sess b { color:#16324f; } .dvs-sess span { display:block; color:#7a8a99; font-size:10.5px; }
.dvs-sess.new { background:linear-gradient(90deg, rgba(73,196,212,.14), transparent); border-radius:6px; padding-left:6px; }
.dvs-toast { position:absolute; left:50%; bottom:20px; transform:translateX(-50%); background:#16324f; color:#fff; font-size:11px; font-weight:600; border-radius:999px; padding:6px 13px; white-space:nowrap; opacity:0; animation:dvsToast 18s infinite; }
@keyframes dvsToast { 0%,79% { opacity:0; } 82%,93% { opacity:1; } 93%,100% { opacity:0; } }

/* CardBoard: the Layout view — a poker card built from the sheet's columns, flipped to its back —
   then the Print view, where every card on the sheet is the same design with its own row */
.cbv { height:390px; background:#eef0f6; font-size:11px; position:relative; }
.cbv-top { height:38px; background:#2e2763; color:#fff; display:flex; align-items:center; gap:8px; padding:0 10px; }
.cbv-logo { font-weight:800; letter-spacing:.03em; font-size:12px; white-space:nowrap; } .cbv-logo i { font-style:normal; color:#f2c14e; } .cbv-logo u { text-decoration:none; color:#a99cff; }
.cbv-tabs { display:flex; background:rgba(255,255,255,.12); border-radius:7px; padding:2px; margin:0 auto; }
.cbv-tabs span { font-size:9px; font-weight:800; letter-spacing:.07em; text-transform:uppercase; padding:4px 7px; border-radius:5px; color:rgba(255,255,255,.6); }
.cbv-tabs .t-lay { animation:tabLay 16s infinite; } .cbv-tabs .t-pr { animation:tabPr 16s infinite; }
@keyframes tabLay { 0%,52% { background:#fff; color:#2e2763; } 53%,96% { background:transparent; color:rgba(255,255,255,.6); } 97%,100% { background:#fff; color:#2e2763; } }
@keyframes tabPr  { 0%,52% { background:transparent; color:rgba(255,255,255,.6); } 53%,96% { background:#fff; color:#2e2763; } 97%,100% { background:transparent; color:rgba(255,255,255,.6); } }
.cbv-save { font-size:9px; font-weight:800; letter-spacing:.08em; background:#f2c14e; color:#2e2763; border-radius:999px; padding:4px 10px; }
.cbv-lay, .cbv-pr { position:absolute; left:0; right:0; top:38px; bottom:0; }
.cbv-lay { display:grid; grid-template-columns:112px 1fr 118px; animation:viewLay 16s infinite; }
.cbv-pr { display:grid; grid-template-columns:104px 1fr; opacity:0; animation:viewPr 16s infinite; }
@keyframes viewLay { 0%,51% { opacity:1; visibility:visible; } 54%,95% { opacity:0; visibility:hidden; } 98%,100% { opacity:1; visibility:visible; } }
@keyframes viewPr  { 0%,51% { opacity:0; visibility:hidden; } 54%,95% { opacity:1; visibility:visible; } 98%,100% { opacity:0; visibility:hidden; } }
.cbv-pal, .cbv-attr, .cbv-pset { background:#fff; padding:8px; overflow:hidden; }
.cbv-pal { border-right:1px solid #e1e4ee; } .cbv-attr { border-left:1px solid #e1e4ee; } .cbv-pset { border-right:1px solid #e1e4ee; background:#fbfbfe; }
.cbv-l { display:block; font-size:8.5px; font-weight:800; letter-spacing:.09em; text-transform:uppercase; color:#8a8fa8; margin:7px 0 4px; }
.cbv-l:first-child { margin-top:0; }
.cbv-deck { display:flex; justify-content:space-between; border:1.5px solid #d6d9e6; border-radius:6px; padding:4px 6px; font-weight:800; font-size:9.5px; letter-spacing:.04em; color:#2e2763; }
.cbv-deck em { font-style:normal; font-weight:500; color:#9aa0b8; }
.fc { display:flex; align-items:center; gap:4px; border:1.5px solid #d6d9e6; border-radius:6px; padding:3px 5px; margin-bottom:4px; font-weight:700; color:#2e2763; font-size:10px; white-space:nowrap; overflow:hidden; animation:fcHi 16s infinite; }
.fc small { margin-left:auto; font-weight:400; color:#9aa0b8; font-size:9px; overflow:hidden; text-overflow:ellipsis; max-width:42px; }
@keyframes fcHi { 0%,1% { border-color:#d6d9e6; background:#fff; transform:none; } 2%,5% { border-color:#7b6cf6; background:#f3f1ff; transform:translateX(4px); } 7%,100% { border-color:#d6d9e6; background:#fff; transform:none; } }
.cbv-stage { display:flex; flex-direction:column; min-width:0; }
.cbv-tool { height:26px; display:flex; align-items:center; justify-content:center; gap:5px; background:#fff; border-bottom:1px solid #e1e4ee; color:#3b3f5c; font-size:9.5px; }
.cbv-tool b { border:1.5px solid #4b3fa8; color:#4b3fa8; border-radius:3px; padding:0 4px; font-size:9px; }
.cbv-tool span { border:1.5px solid #d6d9e6; border-radius:5px; padding:0 5px; line-height:15px; }
.cbv-canvas { flex:1; position:relative; display:flex; align-items:center; justify-content:center; background:#eef0f6 radial-gradient(#d3d6e4 1px, transparent 1.2px) 0 0/14px 14px; perspective:900px; }
.cbv-front { position:absolute; left:12px; top:8px; font-size:8.5px; font-weight:800; letter-spacing:.1em; color:#4b3fa8; }
.cbv-flipb { position:absolute; left:10px; top:24px; width:26px; height:26px; border-radius:50%; background:#fff; box-shadow:0 2px 6px rgba(20,20,60,.15); color:#4b3fa8; display:flex; align-items:center; justify-content:center; font-size:13px; animation:flipBtn 16s infinite; }
@keyframes flipBtn { 0%,41% { transform:none; } 42% { transform:scale(.85); background:#ece9ff; } 44%,100% { transform:none; background:#fff; } }
.cbv-flip { width:150px; height:210px; position:relative; transform-style:preserve-3d; animation:pkFlip 16s infinite; }
@keyframes pkFlip { 0%,42% { transform:rotateY(0); } 45%,48% { transform:rotateY(180deg); } 51%,100% { transform:rotateY(0); } }
.cbv-flip > .pk { position:absolute; inset:0; backface-visibility:hidden; -webkit-backface-visibility:hidden; box-shadow:0 12px 30px rgba(30,25,80,.28); }
.cbv-flip > .pk-back { transform:rotateY(180deg); }
.cbv-attr .at { position:relative; height:16px; margin-bottom:6px; }
.cbv-attr .at span { position:absolute; left:0; top:0; font-weight:800; color:#2e2763; font-size:11px; opacity:0; white-space:nowrap; animation:atName 16s infinite; }
.cbv-attr .at span em { font-style:normal; font-size:8px; letter-spacing:.06em; color:#9aa0b8; margin-left:4px; text-transform:uppercase; }
@keyframes atName { 0%,1% { opacity:0; } 2%,7% { opacity:1; } 8%,100% { opacity:0; } }
.cbv-attr .at span.last { animation:atLast 16s infinite; }
@keyframes atLast { 0%,1% { opacity:0; } 2%,46% { opacity:1; } 47%,100% { opacity:0; } }
.af { border:1.5px solid #d6d9e6; border-radius:5px; padding:2px 5px; margin-bottom:5px; color:#1b1b2b; font-size:9.5px; display:flex; justify-content:space-between; }
.sws { display:flex; gap:3px; margin-bottom:6px; } .sws i { width:14px; height:14px; border-radius:4px; border:1px solid rgba(0,0,0,.1); }
.segm { display:flex; border:1.5px solid #d6d9e6; border-radius:5px; overflow:hidden; } .segm span { flex:1; text-align:center; font-size:9px; padding:1px 0; color:#666; } .segm span.on { background:#ece9ff; color:#4b3fa8; font-weight:700; }

/* The poker card (2.5 × 3.5 in), drawn the same way in the Layout view and on the print sheet */
.pk { width:150px; height:210px; border-radius:9px; background:#1f4e3d; padding:6px; font-family:'Inter',sans-serif; position:relative; overflow:hidden; }
.pk-in { position:absolute; inset:6px; background:#fbf4e4; border-radius:5px; overflow:hidden; }
.pk-name { position:absolute; left:28px; right:6px; top:6px; height:22px; background:#1f4e3d; color:#fbf4e4; border-radius:0 11px 11px 0; font-family:'Fraunces',Georgia,serif; font-weight:600; font-size:13px; line-height:22px; padding-left:10px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.pk-cost { position:absolute; left:4px; top:3px; width:30px; height:30px; border-radius:50%; background:radial-gradient(circle at 35% 30%, #ffe08a, #e0a526 70%); border:2px solid #fbf4e4; box-shadow:0 2px 5px rgba(0,0,0,.3); color:#3a2a06; font-weight:800; font-size:15px; display:flex; align-items:center; justify-content:center; z-index:2; }
.pk-art { position:absolute; left:6px; right:6px; top:34px; height:86px; border-radius:6px; background:#ddd center/cover; box-shadow:inset 0 0 0 2px #1f4e3d; }
.pk-type { position:absolute; left:14px; right:14px; top:112px; height:16px; background:#c9a24a; color:#2b1f05; border-radius:3px; font-size:7.5px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; text-align:center; line-height:16px; box-shadow:0 2px 4px rgba(0,0,0,.18); }
.pk-text { position:absolute; left:8px; right:8px; top:134px; bottom:24px; background:#f2e6cc; border-radius:5px; padding:6px 7px; font-size:8.6px; line-height:1.35; color:#3b3020; text-align:center; display:flex; align-items:center; justify-content:center; }
.pk-pts { position:absolute; right:6px; bottom:4px; width:20px; height:20px; border-radius:50%; background:#1f4e3d; color:#f7d57f; font-weight:800; font-size:10px; display:flex; align-items:center; justify-content:center; }
.pk-set { position:absolute; left:9px; bottom:7px; font-size:6.5px; letter-spacing:.08em; color:#8a7a58; }
.pk-back { background:#1f4e3d radial-gradient(circle at 50% 50%, #2e6b54 0, #1f4e3d 60%); display:flex; align-items:center; justify-content:center; }
.pk-back::before { content:''; position:absolute; inset:10px; border:1.5px solid #c9a24a; border-radius:6px; }
.pk-back b { font-family:'Fraunces',Georgia,serif; font-size:19px; color:#f7d57f; letter-spacing:.06em; }
.pk-back i { position:absolute; width:30px; height:30px; border-radius:50% 0; background:#c9a24a; opacity:.85; }
.pk-back i:nth-of-type(1) { top:24px; left:50%; margin-left:-15px; transform:rotate(45deg); } .pk-back i:nth-of-type(2) { bottom:24px; left:50%; margin-left:-15px; transform:rotate(45deg); }
/* built one field at a time in the Layout view (each selected as it lands) */
.bld .pk-name, .bld .pk-cost, .bld .pk-art, .bld .pk-type, .bld .pk-text, .bld .pk-pts, .bld .pk-set { opacity:0; animation:pkPop 16s infinite; }
@keyframes pkPop { 0% { opacity:0; transform:scale(.7); box-shadow:none; } 2% { opacity:1; transform:none; box-shadow:0 0 0 2px #7b6cf6; } 6% { box-shadow:0 0 0 2px #7b6cf6; } 7%,88% { opacity:1; box-shadow:none; } 92%,100% { opacity:0; box-shadow:none; } }
.bld .pk-art { animation-name:pkPopArt; }
@keyframes pkPopArt { 0% { opacity:0; transform:scale(.7); box-shadow:inset 0 0 0 2px #1f4e3d; } 2% { opacity:1; transform:none; box-shadow:inset 0 0 0 2px #1f4e3d, 0 0 0 2px #7b6cf6; } 6% { box-shadow:inset 0 0 0 2px #1f4e3d, 0 0 0 2px #7b6cf6; } 7%,88% { opacity:1; box-shadow:inset 0 0 0 2px #1f4e3d; } 92%,100% { opacity:0; box-shadow:inset 0 0 0 2px #1f4e3d; } }
.bld .pk-name { animation-delay:.6s; } .bld .pk-cost { animation-delay:1.6s; } .bld .pk-art { animation-delay:2.6s; }
.bld .pk-type { animation-delay:3.6s; } .bld .pk-text { animation-delay:4.6s; } .bld .pk-pts, .bld .pk-set { animation-delay:5.6s; }

/* The Print view: settings, then a letter page of nine cards with crop marks */
.cbv-pset .af { font-size:9px; }
.cbv-pset .go { display:block; margin-top:8px; text-align:center; background:#4b3fa8; color:#fff; font-weight:800; font-size:9px; letter-spacing:.08em; border-radius:5px; padding:5px 0; }
.cbv-pages { display:flex; align-items:center; justify-content:center; background:#e6e8f0; position:relative; }
.cbv-page { width:238px; height:308px; background:#fff; box-shadow:0 6px 22px rgba(20,20,60,.2); position:relative; }
.cbv-page-tag { position:absolute; top:-13px; left:0; font-size:8px; font-weight:800; letter-spacing:.08em; color:#8a8fa8; }
.cbv-grid { position:absolute; left:20px; top:16px; display:grid; grid-template-columns:repeat(3,66px); grid-auto-rows:92px; }
.cbv-cell { width:66px; height:92px; overflow:hidden; opacity:0; animation:cellIn 16s infinite; }
.cbv-cell > .pk { transform:scale(.44); transform-origin:0 0; border-radius:0; }
@keyframes cellIn { 0%,53% { opacity:0; transform:scale(.9); } 56%,95% { opacity:1; transform:none; } 98%,100% { opacity:0; } }
.cut { position:absolute; background:#1b1b2b; }

/* PitchBoard: the board view — a game dragged from Pitch to Signed, its date filled in, saved to the sheet */
.kbw { background:#fff; font-size:12px; }
.kb-nav { display:flex; justify-content:space-between; align-items:center; padding:10px 14px; border-bottom:1px solid #eef0f4; }
.kb-nav b { font-family:'Fraunces',Georgia,serif; font-size:15px; color:#1a1a2e; display:block; line-height:1.1; }
.kb-nav small { font-size:10px; color:#94a3b8; }
.kb-tabs { display:flex; gap:4px; }
.kb-tabs span { font-size:9.5px; font-weight:700; letter-spacing:.06em; color:#64748b; border:1px solid #e2e8f0; border-radius:6px; padding:4px 7px; }
.kb-tabs span.on { background:#1a1a2e; border-color:#1a1a2e; color:#fff; }
.kb-title { padding:10px 14px 2px; font-size:11px; font-weight:600; color:#475569; }
.kb-cols { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; padding:8px 14px 14px; align-items:start; }
.kb-col { border-radius:9px; padding:8px; min-height:200px; transition:none; }
.kb-col.design { background:#f8fafc; border:1px solid #e2e8f0; }
.kb-col.pitch  { background:#f0faf6; border:1px solid #c6ead9; }
.kb-col.signed { background:#f7f3ff; border:1px solid #ddd6fe; animation:droptarget 12s infinite; }
.kb-col.pub    { background:#eff6ff; border:1px solid #bfdbfe; }
@keyframes droptarget { 0%,20% { box-shadow:none; } 24%,36% { box-shadow:inset 0 0 0 2px #a78bfa; background:#efe8ff; } 40%,100% { box-shadow:none; background:#f7f3ff; } }
.kb-h { font-size:9.5px; font-weight:700; letter-spacing:.07em; text-transform:uppercase; margin-bottom:7px; }
.design .kb-h { color:#64748b; } .pitch .kb-h { color:#1d8c70; } .signed .kb-h { color:#7c3aed; } .pub .kb-h { color:#1d4ed8; }
.kb-c { background:#fff; border:1px solid #e5e7eb; border-radius:7px; padding:7px 8px; margin-bottom:6px; position:relative; }
.kb-n { font-size:11px; font-weight:600; color:#1a1a2e; display:flex; justify-content:space-between; align-items:center; gap:4px; }
.kb-m { font-size:10px; color:#94a3b8; margin-top:2px; }
.kb-pill { font-size:9.5px; font-weight:600; background:#f1f5f9; color:#64748b; border-radius:999px; padding:1px 6px; flex-shrink:0; }
.kb-cnt { font-size:9.5px; color:#94a3b8; text-align:center; position:relative; height:14px; }
.kb-cnt span { position:absolute; left:0; right:0; }
.kb-cnt .a { animation:cntA 12s infinite; } .kb-cnt .b { animation:cntB 12s infinite; }
@keyframes cntA { 0%,36% { opacity:1; } 38%,94% { opacity:0; } 98%,100% { opacity:1; } }
@keyframes cntB { 0%,36% { opacity:0; } 38%,94% { opacity:1; } 98%,100% { opacity:0; } }
/* the card being moved: lifted in Pitch, carried one column right, then it lands in Signed's open slot */
.kb-mover { border-left:2px solid #f59e0b; z-index:4; animation:carry 12s infinite; }
@keyframes carry {
  0%,16% { transform:none; box-shadow:none; opacity:1; }
  20% { transform:translateY(-3px) rotate(2deg) scale(1.04); box-shadow:0 14px 28px rgba(15,23,42,.22); }
  34% { transform:translateX(calc(100% + 26px)) translateY(-3px) rotate(2deg) scale(1.04); box-shadow:0 14px 28px rgba(15,23,42,.22); opacity:1; }
  35%,100% { transform:translateX(calc(100% + 26px)); opacity:0; } }
.kb-slot-from { animation:closeslot 12s infinite; overflow:visible; }
@keyframes closeslot { 0%,35% { max-height:80px; margin-bottom:6px; } 40%,94% { max-height:0; margin-bottom:0; } 98%,100% { max-height:80px; margin-bottom:6px; } }
.kb-slot-to { overflow:hidden; animation:openslot 12s infinite; }
@keyframes openslot { 0%,24% { max-height:0; } 30%,94% { max-height:140px; } 98%,100% { max-height:0; } }
.kb-landed { opacity:0; animation:landed 12s infinite; }
@keyframes landed { 0%,34% { opacity:0; } 35%,92% { opacity:1; } 96%,100% { opacity:0; } }
.kb-date { display:flex; justify-content:space-between; border-top:1px solid #eef0f4; margin-top:6px; padding-top:5px; font-size:9.5px; color:#94a3b8; }
.kb-date b { color:#7c3aed; font-weight:700; animation:datefill 12s infinite; border-radius:4px; padding:0 3px; }
@keyframes datefill { 0%,38% { background:transparent; } 42%,56% { background:#ede9fe; } 62%,100% { background:transparent; } }
.kb-toast { position:absolute; left:50%; top:58px; transform:translateX(-50%); background:#1a1a2e; color:#fff; font-size:11.5px; font-weight:600; border-radius:999px; padding:6px 14px; opacity:0; animation:toast 12s infinite; white-space:nowrap; }
@keyframes toast { 0%,40% { opacity:0; transform:translate(-50%,-8px); } 44%,62% { opacity:1; transform:translate(-50%,0); } 68%,100% { opacity:0; } }
.kb-cursor { position:absolute; width:18px; height:18px; z-index:6; left:85%; top:300px; animation:kbcur 12s infinite; }
@keyframes kbcur { 0% { left:85%; top:300px; opacity:0; } 6% { opacity:1; } 16%,20% { left:calc(37.5% + 10px); top:168px; } 34% { left:calc(62.5% + 14px); top:165px; } 38% { left:calc(62.5% + 14px); top:168px; } 52% { left:80%; top:290px; opacity:1; } 58%,100% { left:80%; top:290px; opacity:0; } }

/* BillBoard: editing a game page — add the hero image, set the stats, add a review, Save */
.bbe { height:360px; background:#fff; font-size:12px; }
.bbe-nav { height:36px; display:flex; align-items:center; gap:8px; padding:0 12px; border-bottom:1px solid #eee; font-size:12px; color:#555; }
.bbe-nav b { color:#1b1b2b; }
.bbe-mode { margin-left:auto; font-size:10.5px; font-weight:600; color:#693f99; background:#f3ecfb; border-radius:999px; padding:3px 9px; }
.bbe-save { font-size:10.5px; font-weight:800; letter-spacing:.08em; color:#fff; background:#693f99; border-radius:999px; padding:5px 12px; animation:bbsave 16s infinite; }
@keyframes bbsave { 0%,77% { transform:none; box-shadow:none; } 78% { transform:scale(.92); } 80%,90% { transform:none; box-shadow:0 0 0 4px rgba(105,63,153,.25); } 94%,100% { box-shadow:none; } }
.bbe-hero { position:relative; height:158px; background:#2b2440; overflow:hidden; }
.bbe-ph { position:absolute; top:10px; right:10px; width:44%; height:70px; z-index:3; border:2px dashed rgba(255,255,255,.35); border-radius:10px; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:4px; color:rgba(255,255,255,.75); font-weight:600; animation:bbph 16s infinite; }
.bbe-ph svg { width:22px; height:22px; }
@keyframes bbph { 0%,11% { opacity:1; background:rgba(20,15,35,.35); } 12%,20% { opacity:1; background:rgba(255,255,255,.14); } 23%,96% { opacity:0; } 100% { opacity:1; } }
.bbe-up { position:absolute; left:20%; right:20%; bottom:6px; height:5px; border-radius:3px; background:rgba(255,255,255,.2); overflow:hidden; opacity:0; animation:bbupw 16s infinite; }
.bbe-up i { display:block; height:100%; width:0; background:#9ed29f; animation:bbup 16s infinite; }
@keyframes bbupw { 0%,12% { opacity:0; } 13%,20% { opacity:1; } 22%,100% { opacity:0; } }
@keyframes bbup { 0%,13% { width:0; } 20%,100% { width:100%; } }
.bbe-img { position:absolute; inset:0; background:url(images/billboard-features/hero-floristry.webp) center/cover; opacity:0; animation:bbimg 16s infinite; }
.bbe-img::after { content:''; position:absolute; inset:0; background:linear-gradient(90deg, rgba(20,15,35,.9) 0%, rgba(20,15,35,.62) 55%, rgba(20,15,35,.3) 100%); }
@keyframes bbimg { 0%,20% { opacity:0; transform:scale(1.06); } 24%,94% { opacity:1; transform:none; } 98%,100% { opacity:0; } }
.bbe-in { position:absolute; left:14px; right:14px; bottom:12px; color:#fff; z-index:2; }
.bbe-title { font-family:'Fraunces',Georgia,serif; font-size:22px; font-weight:600; line-height:1.1; }
.bbe-tag { font-size:11.5px; color:rgba(255,255,255,.8); margin:2px 0 8px; }
.bbe-stats { display:flex; gap:7px; flex-wrap:wrap; align-items:center; }
.bbe-f { display:inline-flex; align-items:center; gap:4px; background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.28); border-radius:7px; padding:3px 7px; font-size:11.5px; }
.bbe-f svg { width:12px; height:12px; opacity:.8; }
.bbe-n { min-width:16px; text-align:center; background:rgba(255,255,255,.16); border-radius:4px; padding:0 3px; position:relative; }
.bbe-n.ed1 { animation:bbfocus1 16s infinite; } .bbe-n.ed2 { animation:bbfocus2 16s infinite; }
@keyframes bbfocus1 { 0%,30% { box-shadow:none; background:rgba(255,255,255,.16); } 31%,38% { box-shadow:0 0 0 2px #f7d57f; background:rgba(255,255,255,.3); } 40%,100% { box-shadow:none; background:rgba(255,255,255,.16); } }
@keyframes bbfocus2 { 0%,39% { box-shadow:none; background:rgba(255,255,255,.16); } 40%,46% { box-shadow:0 0 0 2px #f7d57f; background:rgba(255,255,255,.3); } 48%,100% { box-shadow:none; background:rgba(255,255,255,.16); } }
.sw { display:inline-grid; } .sw > span { grid-area:1/1; }
.sw .o1 { animation:old1 16s infinite; } .sw .n1 { animation:new1 16s infinite; }
.sw .o2 { animation:old2 16s infinite; } .sw .n2 { animation:new2 16s infinite; }
@keyframes old1 { 0%,33% { opacity:1; } 34%,96% { opacity:0; } 100% { opacity:1; } } @keyframes new1 { 0%,33% { opacity:0; } 34%,96% { opacity:1; } 100% { opacity:0; } }
@keyframes old2 { 0%,42% { opacity:1; } 43%,96% { opacity:0; } 100% { opacity:1; } } @keyframes new2 { 0%,42% { opacity:0; } 43%,96% { opacity:1; } 100% { opacity:0; } }
.bbe-buy { position:absolute; right:14px; bottom:12px; z-index:2; display:flex; gap:6px; align-items:center; }
.bbe-buy em { font-style:normal; font-weight:800; color:#fff; font-size:13px; }
.bbe-buy span { background:#2e9d4c; color:#fff; font-weight:700; font-size:11px; border-radius:999px; padding:5px 11px; }
.bbe-tick { height:44px; background:#faf7fd; border-bottom:1px solid #efe9f6; display:flex; align-items:center; gap:10px; padding:0 14px; overflow:hidden; white-space:nowrap; }
.bbe-tick > * { flex-shrink:0; }
.bbe-q { font-family:'Fraunces',Georgia,serif; font-style:italic; color:#3b2a55; }
.bbe-q small { font-family:'Inter',sans-serif; font-style:normal; color:#8b7aa6; margin-left:6px; }
.bbe-dot { width:4px; height:4px; border-radius:50%; background:#c9b8e0; flex-shrink:0; }
.bbe-newq { opacity:0; max-width:0; overflow:hidden; display:inline-flex; align-items:center; gap:10px; animation:bbnewq 16s infinite; }
@keyframes bbnewq { 0%,66% { opacity:0; max-width:0; } 70%,94% { opacity:1; max-width:400px; } 98%,100% { opacity:0; max-width:0; } }
.bbe-addrev { border:1.5px dashed #c9b8e0; color:#693f99; font-weight:600; border-radius:7px; padding:3px 9px; flex-shrink:0; animation:bbadd 16s infinite; }
@keyframes bbadd { 0%,48% { background:transparent; } 49%,51% { background:#efe6fa; } 52%,100% { background:transparent; } }
.bbe-about { padding:10px 14px; }
.bbe-about b { font-size:10px; letter-spacing:.09em; text-transform:uppercase; color:#999; display:block; margin-bottom:3px; }
.bbe-about p { color:#555; line-height:1.45; font-size:11.5px; }
.bbe-pop { position:absolute; left:14px; top:232px; width:min(300px, calc(100% - 28px)); background:#fff; border:1px solid #e4daf0; border-radius:10px; box-shadow:0 16px 36px rgba(40,20,70,.22); padding:10px; z-index:4; opacity:0; animation:bbpop 16s infinite; }
@keyframes bbpop { 0%,50% { opacity:0; transform:translateY(6px); } 52%,65% { opacity:1; transform:none; } 67%,100% { opacity:0; transform:translateY(6px); } }
.bbe-ta, .bbe-src { border:1.5px solid #e4daf0; border-radius:7px; padding:6px 8px; color:#3b2a55; margin-bottom:6px; white-space:nowrap; overflow:hidden; }
.bbe-ta { font-family:'Fraunces',Georgia,serif; font-style:italic; min-height:30px; border-color:#b89be0; }
.type { display:inline-block; clip-path:inset(0 100% 0 0); }
.bbe-ta .type { animation:typeq 16s infinite steps(30); } .bbe-src .type { animation:types 16s infinite steps(20); }
@keyframes typeq { 0%,53% { clip-path:inset(0 100% 0 0); } 59%,100% { clip-path:inset(0 0 0 0); } }
@keyframes types { 0%,59% { clip-path:inset(0 100% 0 0); } 63%,100% { clip-path:inset(0 0 0 0); } }
.bbe-pbtns { display:flex; gap:6px; }
.bbe-pbtns span { font-size:10.5px; font-weight:700; border-radius:6px; padding:4px 10px; background:#f1edf6; color:#666; }
.bbe-pbtns span:first-child { background:#693f99; color:#fff; animation:bbpsave 16s infinite; }
@keyframes bbpsave { 0%,64% { transform:none; } 65% { transform:scale(.9); } 66%,100% { transform:none; } }
.bbe-toast { position:absolute; left:50%; top:44px; transform:translateX(-50%); background:#1b1b2b; color:#fff; font-size:11.5px; font-weight:600; border-radius:999px; padding:6px 14px; z-index:5; opacity:0; white-space:nowrap; animation:bbtoast 16s infinite; }
@keyframes bbtoast { 0%,78% { opacity:0; transform:translate(-50%,-6px); } 81%,92% { opacity:1; transform:translate(-50%,0); } 95%,100% { opacity:0; } }
.bbe-cur { position:absolute; width:18px; height:18px; z-index:6; left:80%; top:330px; animation:bbcur 16s infinite; }
@keyframes bbcur {
  0% { left:80%; top:330px; opacity:0; transform:none; } 4% { opacity:1; }
  10% { left:calc(77% - 10px); top:78px; transform:none; } 11.5% { left:calc(77% - 10px); top:78px; transform:scale(.8); } 13% { left:calc(77% - 10px); top:78px; transform:none; }
  29% { left:78px; top:169px; } 30.5% { left:78px; top:169px; transform:scale(.8); } 32% { left:78px; top:169px; transform:none; }
  38% { left:212px; top:169px; } 39.5% { left:212px; top:169px; transform:scale(.8); } 41% { left:212px; top:169px; transform:none; }
  47% { left:63px; top:216px; } 48.5% { left:63px; top:216px; transform:scale(.8); } 50% { left:63px; top:216px; transform:none; }
  63% { left:40px; top:322px; } 64.5% { left:40px; top:322px; transform:scale(.8); } 66% { left:40px; top:322px; transform:none; }
  75% { left:calc(100% - 44px); top:18px; } 77.5% { left:calc(100% - 44px); top:18px; transform:scale(.8); } 79% { left:calc(100% - 44px); top:18px; transform:none; opacity:1; }
  90%,100% { left:calc(100% - 44px); top:18px; opacity:0; } }

/* ── Who it's for: makers × apps ─────────────────────────────────────────── */
.who { background:linear-gradient(180deg,#fff, var(--paper)); }
.matrix { max-width:960px; margin:0 auto; border:1px solid var(--line); border-radius:18px; background:#fff; overflow:hidden; box-shadow:0 18px 50px rgba(20,19,42,.07); }
.mx-row { display:grid; grid-template-columns:minmax(0,1fr) repeat(4,92px); align-items:center; border-bottom:1px solid #f0ece4; transition:background .15s; }
.mx-row:last-child { border-bottom:none; }
.mx-row:not(.mx-head):hover { background:#fcfaf6; }
.mx-head { background:var(--navy); color:#fff; }
.mx-head div { text-align:center; padding:14px 4px 12px; font-size:12px; font-weight:600; }
.mx-head img { width:40px; height:40px; border-radius:10px; display:block; margin:0 auto 5px; }
.mx-head .mx-lead { text-align:left; padding-left:22px; font-family:'Fraunces',Georgia,serif; font-size:1.15rem; font-weight:600; }
.mx-who { display:flex; align-items:center; gap:14px; padding:14px 22px; }
.mx-ico { width:46px; height:46px; border-radius:14px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.mx-ico svg { width:24px; height:24px; }
.mx-who b { display:block; color:var(--navy); font-size:15px; }
.mx-who span { font-size:13px; color:var(--mid); }
.mx-dot { display:flex; justify-content:center; }
.mx-dot i { width:22px; height:22px; border-radius:50%; background:var(--c); box-shadow:0 0 0 5px color-mix(in srgb, var(--c) 22%, transparent); transition:transform .45s cubic-bezier(.3,1.6,.5,1); }
.matrix.anim .mx-dot i { transform:scale(0); }
.mx-dot i.half { background:transparent; border:3px solid var(--c); box-shadow:none; width:18px; height:18px; }
.matrix.anim.in .mx-dot i { transform:scale(1); }
.mx-key { max-width:960px; margin:1rem auto 0; display:flex; gap:1.4rem; justify-content:center; font-size:13px; color:var(--mid); flex-wrap:wrap; }
.mx-key span { display:inline-flex; align-items:center; gap:.45rem; }
.mx-key i { width:14px; height:14px; border-radius:50%; background:var(--navy); display:inline-block; }
.mx-key i.half { background:transparent; border:2.5px solid var(--navy); width:12px; height:12px; }
@media (max-width:700px) {
  .mx-row { grid-template-columns:minmax(0,1fr) repeat(4,44px); }
  .mx-head img { width:30px; height:30px; } .mx-head div span { display:none; }
  .mx-who { padding:12px 12px; gap:10px; } .mx-ico { width:36px; height:36px; } .mx-who div span { display:none; }
}
@media (prefers-reduced-motion: reduce) { *, *::before, *::after { animation:none !important; } .dv-note, .dv-todo div, .cb-art, .cb-name, .cb-cost, .cb-text, .cb-type, .bb-g { opacity:1 !important; } .mx-dot i { transform:scale(1); } }
</style>
</head>
<body>

<nav class="gbn"><div class="gbn-in">
  <a class="brand" href="<?= e(($_onDomain ? '/' : 'gameboard') . ($_dash ? '?home' : '')) ?>"><img src="images/gameboard_icon_180.png" alt=""><span><b>Game<span>Board</span></b><small>Tools for Board Game Makers</small></span></a>
  <div class="nav-links">
    <a href="<?= e($_hp) ?>#action">In action</a>
    <a href="<?= e($_hp) ?>#who">Who it’s for</a>
    <a href="<?= e($_hp) ?>#pricing">Apps &amp; pricing</a>
    <a href="<?= e($_hp) ?>#account">Your account</a>
  </div>
  <div class="gbn-right">
    <button type="button" class="acct-btn" id="navAcct" onclick="toggleNavMenu(event)" title="Menu" aria-label="Menu"><img id="navPic" alt=""><svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="7.5" r="4.5"/><path d="M3.5 21c0-4.14 3.81-7.5 8.5-7.5s8.5 3.36 8.5 7.5"/></svg></button>
    <div class="gbn-menu" id="navMenu">
      <div class="gbn-who" id="navWho" hidden></div>
      <button type="button" id="navMineBtn" onclick="closeNavMenu();goDash()">Your dashboard</button>
      <button type="button" id="navAcctBtn" onclick="closeNavMenu();openAccount()" hidden>Account</button>
      <button type="button" onclick="closeNavMenu();location.href='<?= e($_hp) ?>#pricing'">Apps &amp; pricing</button>
      <button type="button" id="navStartBtn" onclick="closeNavMenu();acctClick('signup')">Start your free week</button>
      <button type="button" id="navAuthBtn" onclick="navAuth()">Sign in</button>
    </div>
  </div>
</div></nav>

<?php if (!$_dash): ?>
<header class="hero">
  <div class="floaters" aria-hidden="true"><img src="images/cardboard_icon_180.png" alt=""><img src="images/db_icon_180.png" alt=""><img src="images/pitchboard_icon_180.png" alt=""><img src="images/bb_icon_180.png" alt=""></div>
  <span class="tag">For board game makers</span>
  <h1>Tools for every step of <em>your game</em>.</h1>
  <p>Built for game makers, from the first prototype to the store shelf.</p>
  <div class="path" aria-label="Design, playtest, pitch, sell">
    <span class="path-line"></span>
    <?php foreach ([['cardboard_icon_180.png','CardBoard','Design &amp; print cards','#a99cff'],['db_icon_180.png','DevBoard','Playtest &amp; develop','#49c4d4'],['pitchboard_icon_180.png','PitchBoard','Pitch to publishers','#f0766a'],['bb_icon_180.png','BillBoard','Show &amp; sell your games','#9ed29f']] as $k => $sp): ?>
    <span class="space" style="--c:<?= $sp[3] ?>;--d:<?= $k * 1.5 ?>s"><img src="images/<?= $sp[0] ?>" alt="<?= $sp[1] ?>"><span class="sp-lab"><b><?= $sp[1] ?></b><small><?= $sp[2] ?></small></span></span>
    <?php endforeach; ?>
  </div>
  <div class="btns">
    <button type="button" class="btn primary" id="heroCta" onclick="acctClick('signup')">Start your free week</button>
    <a class="btn ghost" href="#pricing">Pricing</a>
  </div>
  <a class="scroll-cue" href="#action" aria-label="Scroll down to see the apps in action"><span class="mouse"></span><span>See them in action</span><span class="chev"></span></a>
</header>

<section class="action" id="action">
  <div class="wrap">
    <div class="kicker" style="color:#f3b4aa">See them in action</div>
    <h2>Built for the way games get made</h2>
    <p class="sub">Each app works right on the page — type, drag, click — and saves to your Google Sheet as you go.</p>

    <div class="show" style="--c:#a99cff">
      <div class="show-text">
        <div class="badge"><img src="images/cardboard_icon_180.png" alt="">CardBoard · Design &amp; print cards</div>
        <h3>Design, print, and share your card deck — fast.</h3>
        <p>Drag your sheet’s columns onto a poker card — name, cost, art, type, rules — and style it once. Every row becomes a card, fronts and backs. Print sheets with crop marks, or send the deck to Tabletop Simulator.</p>
        <a href="<?= e($_apps . 'cardboard/features') ?>">CardBoard features →</a>
      </div>
      <?php
      // One design: the card in the Layout view and every card on the print sheet are drawn by pk()
      $PKROWS = [
        ['Tulip Stall',    '3', 'images/billboard-features/cover-floristry.webp', 'Market',  'Draw 2 flower cards. Keep 1, discard the other.', '2'],
        ['Window Box',     '5', 'images/billboard-features/hero-floristry.webp',  'Display', 'Score 1 point for each tulip in your window.',   '4'],
        ['Auction Hall',   '2', 'images/billboard-features/floristry-board.webp', 'Event',   'Each player bids once, starting with you.',      '1'],
      ];
      function pk($r, $n, $build = false) {
        return '<div class="pk' . ($build ? ' bld' : '') . '"><div class="pk-in">'
          . '<div class="pk-name">' . e($r[0]) . '</div><div class="pk-cost">' . e($r[1]) . '</div>'
          . '<div class="pk-art" style="background-image:url(' . e($r[2]) . ')"></div><div class="pk-type">' . e($r[3]) . '</div>'
          . '<div class="pk-text">' . e($r[4]) . '</div><div class="pk-set">FLORISTRY · ' . sprintf('%02d', $n) . '/40</div><div class="pk-pts">' . e($r[5]) . '</div>'
          . '</div></div>';
      }
      ?>
      <div class="win" aria-hidden="true"><div class="win-bar"><i></i><i></i><i></i><span>mygameboard.app/…/cardboard</span></div>
        <div class="win-body cbv">
          <div class="cbv-top"><span class="cbv-logo"><i>Card</i><u>Board</u></span>
            <div class="cbv-tabs"><span>Cards</span><span>Tags</span><span class="t-lay">Layout</span><span class="t-pr">Print</span><span>Export</span></div>
            <span class="cbv-save">SAVE</span></div>

          <div class="cbv-lay">
            <div class="cbv-pal">
              <span class="cbv-l">Deck</span><div class="cbv-deck">MARKET <em>40</em></div>
              <span class="cbv-l">Card</span>
              <div class="fc" style="animation-delay:.6s">Name <small>Tulip Stall</small></div>
              <div class="fc" style="animation-delay:1.6s">Cost <small>3</small></div>
              <div class="fc" style="animation-delay:2.6s">Art <small>▣</small></div>
              <div class="fc" style="animation-delay:3.6s">Type <small>Market</small></div>
              <div class="fc" style="animation-delay:4.6s">Rules <small>Draw 2…</small></div>
              <div class="fc" style="animation-delay:5.6s">Points <small>2</small></div>
              <span class="cbv-l">Shape</span><div class="fc" style="animation:none">◯ ▭ ⬡</div>
            </div>
            <div class="cbv-stage">
              <div class="cbv-tool"><span>◀</span><b>1</b><span>▶</span>&nbsp;<span>−</span>100%<span>+</span>&nbsp;<span>↶</span><span>↷</span></div>
              <div class="cbv-canvas">
                <span class="cbv-front">FRONT</span><span class="cbv-flipb">⟲</span>
                <div class="cbv-flip"><?= pk($PKROWS[0], 1, true) ?><div class="pk pk-back"><i></i><b>FLORISTRY</b><i></i></div></div>
              </div>
            </div>
            <div class="cbv-attr">
              <div class="at"><span style="animation-delay:.6s">Name<em>Card</em></span><span style="animation-delay:1.6s">Cost<em>Card</em></span><span style="animation-delay:2.6s">Art<em>Image</em></span><span style="animation-delay:3.6s">Type<em>Card</em></span><span style="animation-delay:4.6s">Rules<em>Card</em></span><span class="last" style="animation-delay:5.6s">Points<em>Card</em></span></div>
              <span class="cbv-l">Font</span><div class="af">Fraunces <span>▾</span></div>
              <span class="cbv-l">Size · Line</span><div class="af"><span>13 pt</span><span>1.15</span></div>
              <span class="cbv-l">Color</span><div class="sws"><i style="background:#1f4e3d"></i><i style="background:#c9a24a"></i><i style="background:#fbf4e4"></i><i style="background:#3b3020"></i></div>
              <span class="cbv-l">Align</span><div class="segm"><span>L</span><span class="on">C</span><span>R</span></div>
              <span class="cbv-l">Position (pt)</span><div class="af"><span>X 18</span><span>Y 9</span></div>
            </div>
          </div>

          <div class="cbv-pr">
            <div class="cbv-pset">
              <span class="cbv-l">Deck</span><div class="cbv-deck">MARKET <em>40</em></div>
              <span class="cbv-l">Paper</span><div class="af">Letter <span>▾</span></div>
              <span class="cbv-l">Cut lines</span><div class="af">Crop marks <span>▾</span></div>
              <span class="cbv-l">Copies</span><div class="af">From “Count” <span>▾</span></div>
              <span class="cbv-l">Backs</span><div class="af">Double-sided <span>▾</span></div>
              <span class="go">PRINT</span>
            </div>
            <div class="cbv-pages">
              <div class="cbv-page"><span class="cbv-page-tag">PAGE 1 OF 5 · FRONT</span>
                <div class="cbv-grid"><?php for ($k = 0; $k < 9; $k++): ?><div class="cbv-cell" style="animation-delay:<?= number_format($k * 0.07, 2) ?>s"><?= pk($PKROWS[$k % 3], $k + 1) ?></div><?php endfor; ?></div>
                <?php // crop marks: short lines outside the grid at every card edge
                  foreach ([20, 86, 152, 218] as $x) { echo '<span class="cut" style="left:' . ($x - .5) . 'px;top:4px;width:1px;height:9px"></span><span class="cut" style="left:' . ($x - .5) . 'px;top:295px;width:1px;height:9px"></span>'; }
                  foreach ([16, 108, 200, 292] as $y) { echo '<span class="cut" style="top:' . ($y - .5) . 'px;left:7px;width:9px;height:1px"></span><span class="cut" style="top:' . ($y - .5) . 'px;left:222px;width:9px;height:1px"></span>'; } ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="show flip" style="--c:#49c4d4">
      <div class="show-text">
        <div class="badge"><img src="images/db_icon_180.png" alt="">DevBoard · Playtest &amp; develop</div>
        <h3>Every playtest, remembered.</h3>
        <p>Open a session, start the timer and count the rounds. Add who’s playing — type a new tester’s email after their name and they’re added to your People. Write each observation beside its solution, paste or add a photo of the table right into a note, and tap the light bulb to make it a TODO on the game’s card.</p>
        <a href="<?= e($_apps . 'devboard/features') ?>">DevBoard features →</a>
      </div>
      <div class="win" aria-hidden="true"><div class="win-bar"><i></i><i></i><i></i><span>mygameboard.app/…/devboard</span></div>
        <div class="win-body dvs">
          <div class="dvs-dlg">
            <div class="dvs-h"><b>+ Session <em>— Floristry</em></b>
              <div class="dvs-ctl"><div class="dvs-rounds"><span class="dvs-rb">−</span><span class="dvs-rc sw2 dvs-rcs"><span class="a">1</span><span class="b">2</span></span><span class="dvs-rb plus">+</span>Rounds</div><span class="dvs-sw"><i></i><span id="dvTime">14</span></span></div></div>
            <div class="dvs-meta">
              <div class="dvs-fg"><label>Date</label><div class="dvs-in">11/22/2026</div></div>
              <div class="dvs-fg"><label>Type</label><div class="dvs-in">Playtest ▾</div></div>
              <div class="dvs-fg dvs-people"><label>People</label><div class="dvs-in">Maya Chen</div><div class="dvs-in">Leo Park</div>
                <div class="dvs-in dvs-new"><span class="sw2"><span class="full">Sam Rivera <span class="dvs-mail">sam@rivera.games</span></span><span class="clean">Sam Rivera</span></span></div></div>
              <div class="dvs-fg"><label>Location</label><div class="dvs-in">Lantern Café</div></div>
              <div class="dvs-fg"><label>Session number</label><div class="dvs-in ro">12</div></div>
            </div>
            <hr class="dvs-hr">
            <div class="dvs-ol"><label>Observations</label><label>Solutions</label></div>
            <div class="dvs-pair p1"><div><span class="dvs-ib ib1"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></span><span class="ty o1">Auctions in round 2 drag on</span></div><div><span class="ty s1">Cap every auction at 3 bids</span></div><span class="dvs-bulb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path class="glass" d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.1V17h6v-.2c0-.8.4-1.6 1-2.1A7 7 0 0 0 12 2z"/></svg></span></div>
            <div class="dvs-pair"><div><span class="dvs-ib ib2"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></span><span class="ty o2">Everyone loves the tulips</span></div><div></div></div>
            <div class="dvs-pair p3"><div><span class="dvs-ib"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></span><span class="dvs-upl"><i></i>Uploading…</span><span class="dvs-photo"><span class="dvs-trash"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg></span></span></div><div><span class="ty s3">Shorter bid track</span></div></div>
            <div class="dvs-hint">⌘ / Ctrl + Arrow — move between fields · ⌘ / Ctrl + Enter — save</div>
            <div class="dvs-act"><span>Cancel</span><span class="add">Add Session</span></div>
          </div>
          <div class="dvs-pop">Added <b>Sam Rivera</b> to People · sam@rivera.games</div>
          <div class="dvs-card">
            <h4>Floristry</h4><small>Playtesting · 12 playtests · 2 meetings</small>
            <div class="dvs-todos"><b><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path class="glass" style="fill:#ffd43b" d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.1V17h6v-.2c0-.8.4-1.6 1-2.1A7 7 0 0 0 12 2z"/></svg>TODOs (1)</b><div><i></i>Cap every auction at 3 bids</div></div>
            <div class="dvs-sess new"><span class="thumb"></span><b>Playtest 12</b><span>Nov 22 · Lantern Café · 14 min · 2 rounds</span><span>Maya Chen, Leo Park, Sam Rivera</span></div>
            <div class="dvs-sess"><b>Playtest 11</b><span>Nov 15 · Game Night at Leo’s · 38 min</span><span>Ana Torres, Leo Park</span></div>
            <div class="dvs-toast">✓ Session saved to your Sheet</div>
          </div>
        </div>
      </div>
    </div>

    <div class="show" style="--c:#f7b267">
      <div class="show-text">
        <div class="badge"><img src="images/pitchboard_icon_180.png" alt="">PitchBoard · Pitch to publishers</div>
        <h3>From first sketch to signed deal — tracked.</h3>
        <p>Every game sits on a board by stage — Design, Pitch, Signed, Published — with how many publishers you’ve pitched it to. Drag a game to its next stage and its dates fill in, saved to your Sheet.</p>
        <a href="<?= e($_apps . 'pitchboard/features') ?>">PitchBoard features →</a>
      </div>
      <div class="win" aria-hidden="true"><div class="win-bar"><i></i><i></i><i></i><span>mygameboard.app/…/pitchboard</span></div>
        <div class="win-body kbw">
          <div class="kb-nav"><div><b>PitchBoard</b><small>Your Studio</small></div><div class="kb-tabs"><span class="on">BOARD</span><span>12 GAMES</span><span>31 PUBLISHERS</span></div></div>
          <div class="kb-title">Games by stage</div>
          <div class="kb-cols">
            <div class="kb-col design"><div class="kb-h">Design</div>
              <div class="kb-c"><div class="kb-n">Verdant Depths</div><div class="kb-m">Alex, Jordan</div></div>
              <div class="kb-c"><div class="kb-n">The Glass Engine</div><div class="kb-m">—</div></div>
              <div class="kb-cnt"><span>2 games</span></div></div>
            <div class="kb-col pitch"><div class="kb-h">Pitch</div>
              <div class="kb-c"><div class="kb-n">Stormveil <span class="kb-pill">9</span></div><div class="kb-m">Jordan</div></div>
              <div class="kb-slot-from"><div class="kb-c kb-mover"><div class="kb-n">Deepwater Run <span class="kb-pill">6</span></div><div class="kb-m">Alex</div></div></div>
              <div class="kb-c"><div class="kb-n">Ironveil Pass <span class="kb-pill">4</span></div><div class="kb-m">—</div></div>
              <div class="kb-cnt"><span class="a">3 games</span><span class="b">2 games</span></div></div>
            <div class="kb-col signed"><div class="kb-h">Signed</div>
              <div class="kb-c"><div class="kb-n">The Cartographer <span class="kb-pill">5</span></div><div class="kb-m">Jordan, Alex</div></div>
              <div class="kb-slot-to"><div class="kb-c kb-landed" style="border-left:2px solid #f59e0b"><div class="kb-n">Deepwater Run <span class="kb-pill">6</span></div><div class="kb-m">Alex</div><div class="kb-date">Signed <b>Mar 2026</b></div></div></div>
              <div class="kb-cnt"><span class="a">1 game</span><span class="b">2 games</span></div></div>
            <div class="kb-col pub"><div class="kb-h">Published</div>
              <div class="kb-c"><div class="kb-n">Nocturne Market</div><div class="kb-m">Jordan</div></div>
              <div class="kb-c"><div class="kb-n">Thornwood</div><div class="kb-m">—</div></div>
              <div class="kb-cnt"><span>2 games</span></div></div>
          </div>
          <div class="kb-toast">✓ Saved to your Sheet</div>
          <svg class="kb-cursor" viewBox="0 0 24 24"><path d="M4 2l16 9-7 2-3 7z" fill="#14132a" stroke="#fff" stroke-width="1.5" stroke-linejoin="round"/></svg>
        </div>
      </div>
    </div>

    <div class="show flip" style="--c:#9ed29f">
      <div class="show-text">
        <div class="badge"><img src="images/bb_icon_180.png" alt="">BillBoard · Show &amp; sell</div>
        <h3>Your games. A polished page — instantly.</h3>
        <p>Sign in and each game’s page turns editable, right where it is. Drop in a hero image, set the player count and play time, add press quotes, prices and buy links — then Save puts it all in your Sheet. No website to build or host.</p>
        <a href="<?= e($_apps . 'billboard/features') ?>">BillBoard features →</a>
      </div>
      <div class="win" aria-hidden="true"><div class="win-bar"><i></i><i></i><i></i><span>mygameboard.app/billboard/…/floristry</span></div>
        <div class="win-body bbe">
          <div class="bbe-nav"><span>‹ All Games</span><span>|</span><b>Floristry</b><span class="bbe-mode">✎ Editing</span><span class="bbe-save">SAVE</span></div>
          <div class="bbe-hero">
            <div class="bbe-img"></div>
            <div class="bbe-ph"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>+ Add image<div class="bbe-up"><i></i></div></div>
            <div class="bbe-in">
              <div class="bbe-title">Floristry</div>
              <div class="bbe-tag">A quick, real time, Dutch auction game.</div>
              <div class="bbe-stats">
                <span class="bbe-f"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg><span class="bbe-n">2</span>–<span class="bbe-n ed1 sw"><span class="o1">2</span><span class="n1">4</span></span> players</span>
                <span class="bbe-f"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><span class="bbe-n">20</span>–<span class="bbe-n ed2 sw"><span class="o2">20</span><span class="n2">30</span></span> min</span>
                <span class="bbe-f"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 3v18M7 21h10M3 7h18M6 7l-3 7a3 3 0 0 0 6 0zM18 7l-3 7a3 3 0 0 0 6 0z"/></svg>Light</span>
              </div>
            </div>
            <div class="bbe-buy"><em>$20</em><span>Buy now</span></div>
          </div>
          <div class="bbe-tick">
            <span class="bbe-addrev">+ Add review</span>
            <span class="bbe-newq"><span class="bbe-q">“This game is just delightful.”<small>— Shut Up &amp; Sit Down</small></span><span class="bbe-dot"></span></span>
            <span class="bbe-q">“Fast, clever and gorgeous.”<small>— Board Game Breakfast</small></span>
          </div>
          <div class="bbe-about"><b>About the game</b><p>Players compete at the historic Aalsmeer Dutch Auction to win the best flowers, then use them to create the most charming window display.</p></div>
          <div class="bbe-pop">
            <div class="bbe-ta"><span class="type">“This game is just delightful.”</span></div>
            <div class="bbe-src"><span class="type">— Shut Up &amp; Sit Down</span></div>
            <div class="bbe-pbtns"><span>Save</span><span>Cancel</span></div>
          </div>
          <div class="bbe-toast">✓ Saved to your Sheet</div>
          <svg class="bbe-cur" viewBox="0 0 24 24"><path d="M4 2l16 9-7 2-3 7z" fill="#14132a" stroke="#fff" stroke-width="1.5" stroke-linejoin="round"/></svg>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="who" id="who">
  <div class="wrap">
    <div class="kicker">Who it’s for</div>
    <h2>Find the apps that fit your part in the game</h2>
    <p class="sub">Designer, studio, artist or publisher — start with the app you need today and add the others as your game moves along.</p>
    <div class="matrix" id="matrix"><div class="mx-row mx-head"><div class="mx-lead">You’re one of…</div><div><img src="images/cardboard_icon_180.png" alt=""><span>CardBoard</span></div><div><img src="images/db_icon_180.png" alt=""><span>DevBoard</span></div><div><img src="images/pitchboard_icon_180.png" alt=""><span>PitchBoard</span></div><div><img src="images/bb_icon_180.png" alt=""><span>BillBoard</span></div></div><div class="mx-row"><div class="mx-who"><span class="mx-ico" style="background:#fdeceb;color:#14132a"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.1V17h6v-.2c0-.8.4-1.6 1-2.1A7 7 0 0 0 12 2z"/></svg></span><div><b>Game designers</b><span>Prototype, playtest and pitch your own games</span></div></div><div class="mx-dot" style="--c:#7b6cf6"><i style="transition-delay:0.00s"></i></div><div class="mx-dot" style="--c:#49c4d4"><i style="transition-delay:0.05s"></i></div><div class="mx-dot" style="--c:#e7a35a"><i style="transition-delay:0.10s"></i></div><div class="mx-dot" style="--c:#7fcf8a"><i style="transition-delay:0.15s" class="half"></i></div></div>
<div class="mx-row"><div class="mx-who"><span class="mx-ico" style="background:#e6f7f9;color:#14132a"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="7" r="3.5"/><path d="M2.5 21a6.5 6.5 0 0 1 13 0"/><circle cx="17.5" cy="8.5" r="2.8"/><path d="M16 14.2a5 5 0 0 1 6 4.8"/></svg></span><div><b>Co-designers &amp; playtesters</b><span>Share sessions, notes and cards with the team</span></div></div><div class="mx-dot" style="--c:#7b6cf6"><i style="transition-delay:0.08s" class="half"></i></div><div class="mx-dot" style="--c:#49c4d4"><i style="transition-delay:0.13s"></i></div><div class="mx-dot" style="--c:#e7a35a"></div><div class="mx-dot" style="--c:#7fcf8a"></div></div>
<div class="mx-row"><div class="mx-who"><span class="mx-ico" style="background:#eef3fb;color:#14132a"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 12h18"/></svg></span><div><b>Development studios</b><span>Develop games for clients — estimates, invoices, reports</span></div></div><div class="mx-dot" style="--c:#7b6cf6"><i style="transition-delay:0.16s" class="half"></i></div><div class="mx-dot" style="--c:#49c4d4"><i style="transition-delay:0.21s"></i></div><div class="mx-dot" style="--c:#e7a35a"></div><div class="mx-dot" style="--c:#7fcf8a"></div></div>
<div class="mx-row"><div class="mx-who"><span class="mx-ico" style="background:#f1effe;color:#14132a"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.4 2.6a2 2 0 0 1 2.9 2.9L11 15.8 8.2 13z"/><path d="M8 14c-2.2 0-4 1.8-4 4 0 1.3-.7 2.3-2 3 4 1 9 0 9-4"/></svg></span><div><b>Artists &amp; graphic designers</b><span>Lay out cards and print-ready sheets</span></div></div><div class="mx-dot" style="--c:#7b6cf6"><i style="transition-delay:0.29s"></i></div><div class="mx-dot" style="--c:#49c4d4"></div><div class="mx-dot" style="--c:#e7a35a"></div><div class="mx-dot" style="--c:#7fcf8a"></div></div>
<div class="mx-row"><div class="mx-who"><span class="mx-ico" style="background:#f1effe;color:#14132a"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/><rect x="7" y="7.5" width="4" height="6" rx=".8"/><rect x="13" y="7.5" width="4" height="6" rx=".8"/></svg></span><div><b>Digital prototypers</b><span>Send decks to Tabletop Simulator and Screentop</span></div></div><div class="mx-dot" style="--c:#7b6cf6"><i style="transition-delay:0.37s"></i></div><div class="mx-dot" style="--c:#49c4d4"></div><div class="mx-dot" style="--c:#e7a35a"></div><div class="mx-dot" style="--c:#7fcf8a"></div></div>
<div class="mx-row"><div class="mx-who"><span class="mx-ico" style="background:#fff3e6;color:#14132a"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/></svg></span><div><b>Self-publishers</b><span>Make the game, then put it on sale</span></div></div><div class="mx-dot" style="--c:#7b6cf6"><i style="transition-delay:0.40s"></i></div><div class="mx-dot" style="--c:#49c4d4"><i style="transition-delay:0.45s" class="half"></i></div><div class="mx-dot" style="--c:#e7a35a"><i style="transition-delay:0.50s" class="half"></i></div><div class="mx-dot" style="--c:#7fcf8a"><i style="transition-delay:0.55s"></i></div></div>
<div class="mx-row"><div class="mx-who"><span class="mx-ico" style="background:#edf7ee;color:#14132a"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l1.5-5h15L21 9M3 9h18v2a3 3 0 0 1-6 0 3 3 0 0 1-6 0 3 3 0 0 1-6 0z"/><path d="M5 13v8h14v-8M10 21v-5h4v5"/></svg></span><div><b>Publishers</b><span>A catalog and a page for every title</span></div></div><div class="mx-dot" style="--c:#7b6cf6"></div><div class="mx-dot" style="--c:#49c4d4"><i style="transition-delay:0.48s" class="half"></i></div><div class="mx-dot" style="--c:#e7a35a"></div><div class="mx-dot" style="--c:#7fcf8a"><i style="transition-delay:0.63s"></i></div></div>
</div>
    <div class="mx-key"><span><i></i>Made for you</span><span><i class="half"></i>Handy too</span></div>
  </div>
</section>

<section class="pricing" id="pricing">
  <div class="wrap">
    <div class="kicker" style="color:#f3b4aa">The apps &amp; pricing</div>
    <h2>Pay for the apps you use — or get them all</h2>
    <p class="sub">Use one or all four — they work the same way, with your data in your own Google Sheet, edited right on the page. Each app has its own monthly plan, or take all four with the all-in-one. Cancel any time — your games stay in your Sheet.</p>
    <div class="price-grid">
      <?php foreach ($APPS as $a): ?>
      <div class="pc" id="<?= e($a[0]) ?>" style="--c:<?= e($a[5]) ?>">
        <div class="pc-head"><img src="images/<?= e($a[2]) ?>" alt=""><div><b><?= e($a[1]) ?></b><small><?= e($a[3]) ?></small></div></div>
        <p class="pc-line"><?= e($a[4]) ?></p>
        <ul class="pc-feat"><?php foreach ($a[7] as $li): ?><li><?= substr($li, -1) === '*' ? e(substr($li, 0, -1)) . '<span class="pro-mark" title="Pro plan">✦</span>' : e($li) ?></li><?php endforeach; ?></ul>
        <div class="pc-price"><?= e($a[8][0]) ?><small>/ month<?= !empty($a[8][2]) ? ' (' . e($a[8][2]) . ')' : '' ?></small></div>
        <div class="pc-note"><?= $a[8][1] === 'every feature' ? 'Every feature.' : (substr($a[8][1], 0, 1) === '*' ? '<span class="pro-mark" title="Pro plan">✦</span> ' . substr($a[8][1], 1) : ucfirst($a[8][1]) . '.') ?></div>
        <div class="pc-btns"><a href="<?= e($_apps . $a[6] . '/features') ?>">Features</a><a class="app-open" data-app="<?= e($a[6]) ?>" href="<?= e($_apps . $a[6]) ?>" title="Open <?= e($a[1]) ?>">Open</a></div>
      </div>
      <?php endforeach; ?>
      <div class="pc all">
        <span class="ribbon">Best value</span>
        <div class="all-a">
          <div class="icons"><?php foreach ($APPS as $a): ?><img src="images/<?= e($a[2]) ?>" alt=""><?php endforeach; ?></div>
          <b style="font-family:'Fraunces',Georgia,serif;font-size:1.3rem">All-in-one</b>
          <div class="pc-price" style="margin-top:.4rem">$9.99<small>/ month</small></div>
          <div class="pc-was">instead of <s>$15.96</s> for all four</div>
        </div>
        <ul>
          <li>DevBoard, CardBoard, PitchBoard and BillBoard</li>
          <li>Every Pro feature, including DevBoard estimates &amp; invoices</li>
          <li>One GameBoard account for all four</li>
          <li>Save $5.97 every month</li>
        </ul>
        <button type="button" id="allBtn" onclick="buyAll()">Get all four</button>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>
<section class="account<?= $_dash ? ' dash' : '' ?>" id="account">
  <div class="wrap acct-grid">
    <?php if ($_dash): ?>
    <div>
      <div class="kicker">Your dashboard</div>
      <h2>Your apps &amp; sheets</h2>
      <p class="dash-sub">Everything your GameBoard account unlocks — open an app, or go straight to a sheet.</p>
    </div>
    <?php else: ?>
    <div>
      <div class="kicker">One account</div>
      <h2>Your GameBoard account</h2>
      <ul class="points">
        <li>One email and password for the board game apps.</li>
        <li>Your first week is free, with every feature — no card needed.</li>
        <li>Your sheets stay yours: the sheets you set up, you edit; people you share them with can view them.</li>
      </ul>
    </div>
    <?php endif; ?>
    <div class="card" id="acctCard">
      <div id="acctOut">
        <h3>Sign in or start free</h3>
        <p>Create your GameBoard account in a minute — we’ll email you a code to confirm it’s you.</p>
        <button type="button" class="go" onclick="acctClick('signup')">Start your free week</button>
        <div class="foot"><button type="button" class="link" onclick="acctClick('login')">I already have an account — log in</button></div>
      </div>
      <div id="acctIn" hidden>
        <div class="me"><label class="me-dot" id="meDot" title="Change your picture — click, or drop one here" ondragover="event.preventDefault();this.classList.add('drop')" ondragleave="this.classList.remove('drop')" ondrop="mePicDrop(event)"><span id="meIni"></span><img id="meImg" alt="" hidden><span class="cam"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M4 8h3l2-2.5h6L17 8h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg></span><input type="file" accept="image/*" hidden onchange="mePicPick(this)"></label><div><b id="meName"></b><span id="meEmail"></span><span class="me-msg" id="meMsg"></span></div></div>
        <div class="plan" id="mePlan"></div>
        <ul class="my-apps" id="meApps" hidden></ul>
        <div class="my-sheets" id="meSheets" hidden></div>
        <div class="my-sheets" id="meShared" hidden></div>
        <button type="button" class="go" id="meGo" onclick="location.href='<?= e($_hp) ?>#pricing'">See apps &amp; pricing</button>
        <div class="foot"><button type="button" class="link" onclick="signOut()">Sign out</button></div>
      </div>
    </div>
  </div>
</section>

<?php if (!$_dash): ?>
<section class="own">
  <div class="wrap">
    <div class="kicker">Yours to keep</div>
    <h2>Your games live in your Google Sheet</h2>
    <p class="sub" style="margin-bottom:0">No database of ours to get stuck in. The apps read and write a sheet in your own Google Drive.</p>
    <div class="own-grid">
      <div><b>Edit the fast way</b><span>Sort, filter, use formulas or paste a list in Sheets — the apps follow.</span></div>
      <div><b>Work with your team</b><span>Share the sheet with co-designers and playtesters the usual Google way.</span></div>
      <div><b>Nothing gets lost</b><span>Google keeps the sheet’s version history, so you can always go back.</span></div>
      <div><b>Works anywhere</b><span>Phone, tablet or desktop — in the browser, nothing to install.</span></div>
    </div>
  </div>
</section>
<?php endif; ?>

<footer>Powered by <a href="https://zapsheets.com" style="margin:0">ZapSheets</a> ·
  <?php foreach ($APPS as $a): ?><a href="<?= e($_apps . $a[6] . '/features') ?>"><?= e($a[1]) ?></a><?php endforeach; ?>
</footer>

<!-- Sign in / start free / reset: the same GameBoard account as the apps -->
<div class="overlay" id="authOverlay" onclick="if(event.target===this)closeAuth()">
  <div class="dlg" role="dialog" aria-modal="true" aria-labelledby="authTitle">
    <button type="button" class="dlg-x" onclick="closeAuth()" aria-label="Close">×</button>
    <div id="authChoose">
      <h3>Welcome to GameBoard</h3>
      <p class="m">One account for the board game apps. New here? Your first week is free, with every feature.</p>
      <div class="choose">
        <button type="button" class="opt primary" onclick="authMode('signup')"><b>Start your free week</b><span>Every feature for 7 days · no card needed</span></button>
        <button type="button" class="opt" onclick="authMode('login')"><b>Log in</b><span>I already have a GameBoard account</span></button>
      </div>
    </div>
    <form id="authEmail" hidden onsubmit="event.preventDefault();authSubmit()">
      <h3 id="authTitle">Log in</h3>
      <p class="m" id="authMsg"></p>
      <input class="inp" id="authAddr" type="email" autocomplete="email" placeholder="Email" aria-label="Email">
      <input class="inp" id="authPass" type="password" autocomplete="current-password" placeholder="Password" aria-label="Password">
      <input class="inp" id="authPass2" type="password" autocomplete="new-password" placeholder="Password again" aria-label="Password again">
      <ul class="rules" id="authRules"><li data-rule="len">8 or more characters</li><li data-rule="letter">A letter</li><li data-rule="num">A number</li><li data-rule="match">Both match</li></ul>
      <button type="submit" class="go" id="authSendBtn">Log in</button>
      <div class="err" id="authErr1"></div>
      <div class="foot"><button type="button" class="link" id="authForgot" onclick="authMode('reset')">Forgot password?</button> &nbsp; <button type="button" class="link" id="authSwitch" onclick="authMode(_mode === 'login' ? 'signup' : 'login')"></button></div>
    </form>
    <form id="authCode" hidden onsubmit="event.preventDefault();authVerify()">
      <h3>Check your email</h3>
      <p class="m" id="authSent"></p>
      <input class="inp code" id="authCodeIn" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" aria-label="Code" oninput="var v=this.value.replace(/\D/g,'').slice(0,6);if(v!==this.value)this.value=v">
      <button type="submit" class="go" id="authGo">Confirm</button>
      <div class="err" id="authErr2"></div>
      <div class="foot"><button type="button" class="link" onclick="authMode(_mode)">Back</button></div>
    </form>
  </div>
</div>

<script>
(function() {
  'use strict';
  var APP_BASE = document.querySelector('base').getAttribute('href');
  var APPS_URL = <?= json_encode($_apps) ?>;
  var DASH = <?= $_dash ? 'true' : 'false' ?>, HOME = <?= json_encode($_home) ?>;
  var KEY = 'cb_account';   // the account kept in this browser — the same key the apps read
  var APPS = <?= json_encode(array_map(function ($a) { return ['key' => $a[6], 'name' => $a[1], 'icon' => 'images/' . $a[2]]; }, $APPS)) ?>;
  var wantAll = false;   // "Get all four" before signing in: go on to checkout after
  var acct = null;
  try { acct = JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) {}
  // Sent over from the GameBoard home on the other domain: the sign-in comes along in the link (#gbt=…)
  (function() {
    var m = /[#&]gbt=([^&]+)/.exec(location.hash || ''); if (!m) return;
    history.replaceState(null, '', location.pathname + location.search);
    acct = { token: decodeURIComponent(m[1]), account: null, sheet: '', checked: 0 };
    try { localStorage.setItem(KEY, JSON.stringify(acct)); } catch (e) {}
  })();
  window._mode = '';
  function $(id) { return document.getElementById(id); }
  function esc(s) { return String(s).replace(/[&<>"]/g, function(c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function post(fields) {
    var fd = new FormData(), ep = fields._url || 'gameboardAccount'; Object.keys(fields).forEach(function(k) { if (k !== '_url') fd.append(k, fields[k]); });
    return fetch(APP_BASE + 'push/' + ep + '.php', { method: 'POST', body: fd }).then(function(r) { return r.json(); })
      .then(function(r) { if (!r || r.error) throw new Error(r && r.error ? r.error : 'No response'); return r; });
  }
  function store(r) {
    acct = { token: r.token, account: r.account, sheet: '', checked: Date.now() };
    try { localStorage.setItem(KEY, JSON.stringify(acct)); } catch (e) {}
    show();
    if (wantAll) { wantAll = false; setTimeout(buyAll, 50); }
  }
  // The apps run on zapsheets.com: on mygameboard.app, the "Open" links carry the account over
  // The dashboard: on the apps' site (handing the sign-in over when that's another domain)
  function dashUrl() {
    if (crossOrigin()) return APPS_URL + 'dashboard' + (acct && acct.token ? '#gbt=' + encodeURIComponent(acct.token) : '');
    return APP_BASE + 'dashboard';
  }
  window.goDash = function() { location.href = dashUrl(); };
  // Account: name, picture, email, password, plan and codes — the same dialog as in the apps
  var _dlg = null;
  window.openAccount = function() {
    if (!acct) { acctClick('login'); return; }
    if (!_dlg) _dlg = window.GBAccountDialog ? Promise.resolve() : new Promise(function(res, rej) { var s = document.createElement('script'); s.src = APP_BASE + 'js/gb-acctdlg.js?v=1'; s.onload = res; s.onerror = function() { _dlg = null; rej(); }; document.head.appendChild(s); });
    _dlg.then(function() {
      GBAccountDialog.open({ base: APP_BASE, app: '', sheet: '', account: function() { return (acct && acct.account) || {}; }, token: function() { return (acct && acct.token) || ''; },
        store: store, plan: function() { return esc($('mePlan') && $('mePlan').textContent || ''); },
        plans: function() { location.href = '<?= e($_hp) ?>#pricing'; }, signOut: function() { window.signOut(); } });
    });
  };
  // Just signed in: off to the dashboard (unless checkout is next)
  function signedIn() { closeAuth(); if (!DASH && !wantAll) goDash(); }
  function crossOrigin() { try { return new URL(APPS_URL, location.href).origin !== location.origin; } catch (e) { return false; } }
  function linkApps() {
    document.querySelectorAll('.app-open').forEach(function(a) {
      var u = APPS_URL + a.getAttribute('data-app');
      a.href = acct && acct.token && crossOrigin() ? u + '#gbt=' + encodeURIComponent(acct.token) : u;
    });
  }
  function show() {
    var a = acct && acct.account;
    // The account button: profile picture (or the person icon); the menu says who's signed in
    var pic = $('navPic'), im = a && a.image ? String(a.image) : '';
    if (im && pic.getAttribute('src') !== im) pic.src = im;
    $('navAcct').classList.toggle('has-pic', !!im); $('navAcct').title = a ? (a.name || a.email || 'Menu') : 'Menu';
    $('navWho').hidden = !a;
    if (a) $('navWho').innerHTML = '<b>' + esc(a.name || (a.email || '').split('@')[0]) + '</b>' + esc(a.email || '');
    $('navMineBtn').hidden = !a; $('navAcctBtn').hidden = !a; $('navStartBtn').hidden = !!a; $('navAuthBtn').textContent = a ? 'Sign out' : 'Sign in';
    if ($('heroCta')) $('heroCta').textContent = a ? 'Your dashboard' : 'Start your free week';
    // The home page's account section stays the same; the dashboard shows the account
    $('acctOut').hidden = DASH && !!a; $('acctIn').hidden = !(DASH && a);
    if (a) {
      var nm = a.name || (a.email || '').split('@')[0];
      $('meIni').textContent = (nm || '?').charAt(0).toUpperCase();
      var mi = $('meImg'), mu = a.image ? String(a.image) : '';
      if (mu && mi.getAttribute('src') !== mu) mi.src = mu;
      mi.hidden = !mu; $('meIni').hidden = !!mu;
      $('meName').textContent = nm; $('meEmail').textContent = a.email || '';
      var apps = a.apps || {}, days = function(t) { return Math.max(0, Math.ceil((new Date(t).getTime() - Date.now()) / 864e5)); };
      var when = function(d) { return d ? new Date(d + (d.length === 10 ? 'T12:00:00Z' : '')).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) : ''; };
      var paid = APPS.filter(function(x) { return (apps[x.key] || {}).state === 'subscribed'; }).map(function(x) { return x.name; });
      var left = days(a.trial_end || a.until);
      var txt = a.state === 'activated' ? 'Activated — every app, every feature, no time limit.'
              : a.all_in_one ? 'All-in-one — every app, every feature. Thank you!'
              : paid.length ? 'Subscribed to ' + paid.join(', ').replace(/, ([^,]*)$/, ' and $1') + ' — thank you!'
              : a.state === 'trial' ? 'Free week — ' + left + (left === 1 ? ' day' : ' days') + ' left, with every app and feature.'
              : a.state === 'code' ? 'Unlocked with a code until ' + new Date(a.until).toLocaleDateString() + '.'
              : 'Your free week has ended — subscribe to an app, or get all four.';
      $('mePlan').textContent = txt;
      $('mePlan').classList.toggle('ok', a.state !== 'locked' || paid.length > 0);
      // Each app: what this account has
      $('meApps').innerHTML = a.state === 'activated' || !a.apps ? '' : APPS.map(function(x) {
        var p = apps[x.key] || {}, st = p.state, d;
        var label = st === 'subscribed' ? (a.all_in_one ? 'All-in-one' : (p.plan === 'core' ? 'Core' : 'Subscribed')) + (p.period_end ? ' · renews ' + when(p.period_end) : '')
                  : st === 'code' ? 'Code · until ' + when(p.until)
                  : st === 'trial' ? 'Free week · ' + (d = days(p.until)) + (d === 1 ? ' day' : ' days') + ' left'
                  : 'Not subscribed';
        return '<li><img src="' + esc(x.icon) + '" alt=""><b>' + esc(x.name) + '</b><span class="' + (st === 'subscribed' ? 'paid' : '') + '">' + esc(label) + '</span></li>';
      }).join('');
    }
    if (a && DASH) { renderSheets(_sheets); loadSheets(); loadShared(); } else { _sheetsFor = ''; _sheets = []; $('meSheets').hidden = true; $('meShared').hidden = true; _sharedFor = ''; }
    var ab = $('allBtn'), have = a && (a.all_in_one || a.state === 'activated');
    if (ab) { ab.disabled = !!have; ab.textContent = have ? '✓ You have all four' : 'Get all four'; }
    linkApps();
  }
  // Your sheets, grouped by app — each link opens the sheet in its app
  var _sheetsFor = '', _sheets = [];
  function loadSheets() {
    if (!acct || !acct.token || _sheetsFor === acct.token) return;
    _sheetsFor = acct.token;
    post({ action: 'sheets', cbt: acct.token }).then(function(r) { _sheets = r.sheets || []; renderSheets(_sheets); }).catch(function() { _sheetsFor = ''; });
  }
  // DevBoard games others have shared with you (the share links you've opened while signed in)
  var _sharedFor = '';
  function loadShared() {
    if (!acct || !acct.token || _sharedFor === acct.token) return;
    _sharedFor = acct.token;
    post({ action: 'list', cbt: acct.token, _url: 'devSharedGames' }).then(function(r) {
      var g = r.games || [], box = $('meShared');
      box.innerHTML = g.length ? '<h4 style="margin:0 0 .5rem;color:var(--navy)"><img src="images/db_icon_180.png" alt="">Shared with you</h4>' + g.map(function(x) {
        return '<a class="sh" href="' + esc(x.url) + '"><span class="sh-nm"><b>' + esc(x.g) + '</b>' + (x.s ? '<small>DevBoard · shared by ' + esc(x.s) + '</small>' : '<small>DevBoard</small>') + '</span><span class="sh-when">Open</span></a>';
      }).join('') : '';
      box.hidden = !g.length;
    }).catch(function() { _sharedFor = ''; });
  }
  function ago(t) {
    if (!t) return '';
    var s = Math.max(0, Date.now() / 1000 - t), d = Math.floor(s / 86400);
    return s < 3600 ? 'just now' : s < 86400 ? Math.floor(s / 3600) + ' h ago' : d === 1 ? 'yesterday' : d < 30 ? d + ' days ago' : new Date(t * 1000).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
  }
  function sheetUrl(id, app) {
    var u = APPS_URL + encodeURIComponent(id) + '/' + app;
    return acct && acct.token && crossOrigin() ? u + '#gbt=' + encodeURIComponent(acct.token) : u;
  }
  // The apps this account can use now: subscribed (or all-in-one), activated, a code, or the free week
  function myApps() {
    var a = acct && acct.account; if (!a) return [];
    if (a.state === 'activated' || a.all_in_one) return APPS;
    var apps = a.apps || {};
    return APPS.filter(function(x) {
      var p = apps[x.key] || {};
      return /^(subscribed|code|trial)$/.test(p.state || '') && (p.state === 'subscribed' || !p.until || new Date(p.until).getTime() > Date.now());
    });
  }
  // What the account has for an app, in a few words (nothing when it's activated — the line above says so)
  function planOf(x) {
    var a = acct && acct.account; if (!a || a.state === 'activated') return '';
    var p = (a.apps || {})[x.key] || {}, d = function(t) { return t ? new Date(t + (String(t).length === 10 ? 'T12:00:00Z' : '')).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) : ''; };
    if (a.all_in_one) return 'All-in-one' + (p.period_end ? ' · renews ' + d(p.period_end) : '');
    if (p.state === 'subscribed') return (p.plan === 'core' ? 'Core' : 'Subscribed') + (p.period_end ? ' · renews ' + d(p.period_end) : '');
    if (p.state === 'code') return 'Code · until ' + d(p.until);
    if (p.state === 'trial') { var n = Math.max(0, Math.ceil((new Date(p.until).getTime() - Date.now()) / 864e5)); return 'Free week · ' + n + (n === 1 ? ' day' : ' days') + ' left'; }
    return '';
  }
  function appUrl(key) {
    var u = APPS_URL + key;
    return acct && acct.token && crossOrigin() ? u + '#gbt=' + encodeURIComponent(acct.token) : u;
  }
  // Your apps: each app's logo and an Open link, with your sheets in it underneath
  document.addEventListener('click', function(ev) {
    if (ev.target.closest && ev.target.closest('.sh-id')) { ev.preventDefault(); return; }   // select the ID to copy it, without opening the sheet
    var eye = ev.target.closest && ev.target.closest('.sh-eye'); if (!eye) return;
    ev.preventDefault(); ev.stopPropagation();
    var row = eye.closest('.sh'), on = row.classList.toggle('show-id');
    eye.title = on ? 'Hide the sheet ID' : 'Show the sheet ID'; eye.setAttribute('aria-label', eye.title);
  }, true);
  function renderSheets(list) {
    var box = $('meSheets'), h = '', have = myApps(), names = {};
    try { names = JSON.parse(localStorage.getItem('gb_sheet_names') || '{}') || {}; } catch (e) {}
    have.forEach(function(x) {
      var mine = list.filter(function(s) { return s.app === x.key; });
      h += '<a class="app-row" href="' + esc(appUrl(x.key)) + '"><img src="' + esc(x.icon) + '" alt=""><b>' + esc(x.name) + (planOf(x) ? '<small>' + esc(planOf(x)) + '</small>' : '') + '</b><span>Open</span></a>' + mine.map(function(s) {
        // The sheet's name — kept in this browser by the apps when they open it — what's in it underneath; the eye shows its ID
        var name = names[s.id] || (x.key === 'cardboard' ? s.title : '') || s.summary || s.title || ('Sheet ' + s.id.slice(0, 8) + '…');
        var what = s.summary && s.summary !== name ? s.summary : '';
        return '<a class="sh" href="' + esc(sheetUrl(s.id, x.key)) + '"><span class="sh-nm"><b>' + esc(name) + '</b>' + (what ? '<small>' + esc(what) + '</small>' : '') +
          '<code class="sh-id">' + esc(s.id) + '</code></span>' +
          '<button type="button" class="sh-eye" title="Show the sheet ID" aria-label="Show the sheet ID"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></button>' +
          '<span class="sh-when">' + esc(ago(s.opened)) + '</span></a>';
      }).join('');
    });
    var loose = have.length ? list.filter(function(s) { return !s.app; }) : [];
    if (loose.length) h += '<h4>Not opened in an app yet</h4>' + loose.map(function(s) {
      return '<div class="sh-new"><b style="font-size:13px;color:var(--navy);margin-right:.3rem" title="' + esc(s.id) + '">' + esc('Sheet ' + s.id.slice(0, 8) + '…') + '</b>' +
        have.map(function(x) { return '<a href="' + esc(sheetUrl(s.id, x.key)) + '">' + esc(x.name) + '</a>'; }).join('') + '</div>';
    }).join('');
    box.innerHTML = h ? '<h4 style="margin:0 0 .5rem;color:var(--navy)">Your apps</h4>' + h : '';
    box.hidden = !h;
    // With apps to open, the big button isn't needed; without, it points to the plans
    $('meGo').hidden = have.length > 0;
  }
  window.acctClick = function(mode) {
    if (acct) { goDash(); return; }
    openAuth(mode || '');
  };
  // The all-in-one: Stripe checkout for the signed-in account (sign in or start the free week first)
  window.buyAll = function() {
    if (!acct || !acct.token) { wantAll = true; openAuth('signup'); return; }
    var b = $('allBtn'), back = location.origin + location.pathname;
    b.disabled = true; b.textContent = 'Opening checkout…';
    var fd = new FormData();
    fd.append('type', 'subscription'); fd.append('app', 'all'); fd.append('id', ''); fd.append('cbt', acct.token);
    if (acct.account && acct.account.email) fd.append('email', acct.account.email);
    fd.append('success_url', back + '?bought=all#account'); fd.append('cancel_url', back + '#pricing');
    fetch(APP_BASE + 'push/stripeCheckout.php', { method: 'POST', body: fd }).then(function(r) { return r.json(); })
      .then(function(r) { if (!r || !r.url) throw new Error(r && r.error ? r.error : 'Checkout isn’t available right now'); location.href = r.url; })
      .catch(function(e) { alert(e.message); show(); });
  };
  // Your picture (the same as in CardBoard): made smaller here, then kept with the account
  function shrinkPic(file) {
    return new Promise(function(ok) {
      var url = URL.createObjectURL(file), im = new Image();
      im.onload = function() {
        var k = Math.min(1, 1024 / Math.max(im.width, im.height)), c = document.createElement('canvas');
        c.width = Math.round(im.width * k); c.height = Math.round(im.height * k);
        c.getContext('2d').drawImage(im, 0, 0, c.width, c.height); URL.revokeObjectURL(url);
        c.toBlob(function(b) { ok(b || file); }, 'image/jpeg', .9);
      };
      im.onerror = function() { URL.revokeObjectURL(url); ok(file); };
      im.src = url;
    });
  }
  function mePicMsg(m, err) { var el = $('meMsg'); el.textContent = m || ''; el.classList.toggle('err', !!err); clearTimeout(el._t); if (m && !err) el._t = setTimeout(function() { el.textContent = ''; }, 2500); }
  function mePicUpload(file) {
    if (!acct || !acct.token) return;
    if (!file || !/^image\//.test(file.type)) { mePicMsg('Use a picture (JPEG, PNG, GIF or WebP)', true); return; }
    var el = $('meDot'); el.classList.add('busy'); mePicMsg('');
    shrinkPic(file).then(function(b) {
      var fd = new FormData(); fd.append('action', 'image'); fd.append('cbt', acct.token); fd.append('file', b, 'picture.jpg');
      return fetch(APP_BASE + 'push/gameboardAccount.php', { method: 'POST', body: fd }).then(function(r) { return r.json(); });
    }).then(function(r) {
      if (!r || r.error) throw new Error(r && r.error ? r.error : 'No response');
      store(r); mePicMsg('✓ Picture updated');
    }).catch(function(e) { mePicMsg('Couldn’t change your picture — ' + e.message, true); })
      .then(function() { el.classList.remove('busy'); });
  }
  window.mePicPick = function(inp) { var f = inp.files && inp.files[0]; inp.value = ''; mePicUpload(f); };
  window.mePicDrop = function(e) { e.preventDefault(); $('meDot').classList.remove('drop'); mePicUpload(e.dataTransfer.files && e.dataTransfer.files[0]); };
  window.toggleNavMenu = function(ev) { ev.stopPropagation(); $('navMenu').classList.toggle('open'); };
  window.closeNavMenu = function() { $('navMenu').classList.remove('open'); };
  document.addEventListener('click', function(ev) { if (!ev.target.closest('#navMenu')) window.closeNavMenu(); });
  window.navAuth = function() { window.closeNavMenu(); if (acct) window.signOut(); else acctClick('login'); };
  window.signOut = function() {
    var away = crossOrigin() && acct;
    acct = null; try { localStorage.removeItem(KEY); } catch (e) {} show();
    // The apps keep the sign-in on their own site (mygameboard.app): sign out there too
    if (away) location.href = APPS_URL + '#gbsignout';
  };
  // Signed out here from the GameBoard home on the other domain
  if (/[#&]gbsignout\b/.test(location.hash)) { acct = null; try { localStorage.removeItem(KEY); } catch (e) {} history.replaceState(null, '', location.pathname + location.search); }
  // Signed in or out in another tab or app: follow it
  function follow() {
    var s = null; try { s = JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) {}
    if ((s && s.token || '') === (acct && acct.token || '')) return;
    var em = function(x) { return String((x && x.account && x.account.email) || '').toLowerCase(); };
    if (s && s.token && acct && (!s.account || em(s) === em(acct))) { acct.token = s.token; return; }   // the same account, just re-checked
    acct = s && s.token ? s : null; show();
  }
  window.addEventListener('storage', function(ev) { if (ev.key === KEY || ev.key === null) follow(); });
  document.addEventListener('visibilitychange', function() { if (!document.hidden) follow(); });
  function openAuth(m) { $('authOverlay').classList.add('open'); authMode(m); }
  window.closeAuth = function() { $('authOverlay').classList.remove('open'); wantAll = false; };
  document.addEventListener('keydown', function(k) { if (k.key === 'Escape') closeAuth(); });

  var pending = null;
  window.authMode = function(m) {
    window._mode = m; pending = null;
    $('authChoose').hidden = !!m; $('authEmail').hidden = !m; $('authCode').hidden = true;
    $('authErr1').textContent = ''; $('authErr2').textContent = '';
    $('authTitle').textContent = { login: 'Log in', signup: 'Start your free week', reset: 'Reset your password' }[m] || '';
    $('authMsg').textContent = { login: 'Use your GameBoard email and password.',
      signup: 'Every feature for 7 days — no card needed. Choose a password; we’ll email you a code to confirm.',
      reset: 'Choose a new password; we’ll email you a code to confirm it’s you.' }[m] || '';
    $('authPass').value = ''; $('authPass2').value = '';
    $('authPass').placeholder = m === 'login' ? 'Password' : 'Password (8 or more characters)';
    $('authPass').setAttribute('autocomplete', m === 'login' ? 'current-password' : 'new-password');
    $('authPass2').hidden = $('authRules').hidden = m === 'login'; checkRules();
    $('authForgot').hidden = m !== 'login';
    $('authSendBtn').textContent = { login: 'Log in', signup: 'Create account', reset: 'Email me a code' }[m] || '';
    $('authSwitch').textContent = m === 'login' ? 'New here? Start your free week' : 'Already have an account? Log in';
    if (m) setTimeout(function() { ($('authAddr').value ? $('authPass') : $('authAddr')).focus(); }, 30);
  };
  function checkRules() {
    var pw = $('authPass').value, all = true, ok = { len: pw.length >= 8, letter: /\p{L}/u.test(pw), num: /\d/.test(pw), match: pw !== '' && pw === $('authPass2').value };
    $('authRules').querySelectorAll('li').forEach(function(li) { var y = ok[li.dataset.rule]; li.classList.toggle('ok', y); if (!y) all = false; });
    return all;
  }
  $('authPass').addEventListener('input', checkRules); $('authPass2').addEventListener('input', checkRules);
  window.authSubmit = function() {
    var m = window._mode, em = $('authAddr').value.trim(), pw = $('authPass').value, err = $('authErr1');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) { err.textContent = 'Enter your email address.'; return; }
    if (m === 'login') { if (!pw) { err.textContent = 'Enter your password.'; return; } }
    else if (!checkRules()) { err.textContent = 'Your password needs everything on the list.'; return; }
    var b = $('authSendBtn'), label = b.textContent; b.disabled = true; b.textContent = m === 'login' ? 'Logging in…' : 'Sending…'; err.textContent = '';
    var done = function() { b.disabled = false; b.textContent = label; };
    if (m === 'login') { post({ action: 'login', email: em, password: pw }).then(function(r) { var w = wantAll; store(r); if (!w) signedIn(); else closeAuth(); }).catch(function(e) { err.textContent = e.message; }).then(done); return; }
    post({ action: 'send', email: em, mode: m }).then(function(r) {
      pending = { email: em, password: pw };
      $('authEmail').hidden = true; $('authCode').hidden = false; $('authCodeIn').value = r.debug_code || '';
      $('authSent').innerHTML = 'We sent a 6-digit code to <b>' + esc(em) + '</b>. Enter it to ' + (m === 'reset' ? 'set your new password.' : 'confirm your email and start your free week.') + ' It works for 15 minutes.';
      $('authGo').textContent = m === 'reset' ? 'Set password' : 'Start my free week';
      setTimeout(function() { $('authCodeIn').focus(); }, 30);
    }).catch(function(e) { err.textContent = e.message; }).then(done);
  };
  window.authVerify = function() {
    var code = $('authCodeIn').value.replace(/\D/g, '');
    if (!pending) { authMode(window._mode); return; }
    if (code.length !== 6) { $('authErr2').textContent = 'Enter the 6-digit code from the email.'; return; }
    var b = $('authGo'), label = b.textContent; b.disabled = true; b.textContent = 'Checking…'; $('authErr2').textContent = '';
    post({ action: 'verify', email: pending.email, password: pending.password, code: code }).then(function(r) { pending = null; var w = wantAll; store(r); if (!w) signedIn(); else closeAuth(); })
      .catch(function(e) { $('authErr2').textContent = e.message; }).then(function() { b.disabled = false; b.textContent = label; });
  };

  // Keep the account current (plan, days left) when it was last checked a while ago
  // Back from checkout: the payment reaches the account a moment later, so check now and again shortly
  var bought = /[?&]bought=/.test(location.search);
  function refresh() { post({ action: 'refresh', cbt: acct.token }).then(store).catch(function(e) { if (/sign in/i.test(e.message)) signOut(); }); }
  if (acct && acct.token && (bought || Date.now() - (acct.checked || 0) > 3600e3 || !(acct.account && acct.account.apps))) refresh();
  if (bought && acct && acct.token) { setTimeout(refresh, 4000); setTimeout(refresh, 12000); history.replaceState(null, '', location.pathname + location.hash); }
  show();

  // The DevBoard window's playtest timer keeps running; the who-it's-for dots pop in when scrolled to
  var t0 = 14 * 60, tEl = $('dvTime');   // the session stopwatch counts minutes, like DevBoard's
  setInterval(function() { t0 += 3; if (tEl) tEl.textContent = Math.floor(t0 / 60); }, 1000);
  var mx = $('matrix');
  if (mx && 'IntersectionObserver' in window) {   // without it the dots simply show
    mx.classList.add('anim');
    new IntersectionObserver(function(es, o) { es.forEach(function(en) { if (en.isIntersecting) { mx.classList.add('in'); o.disconnect(); } }); }, { threshold: .2 }).observe(mx);
  }
})();
</script>
</body>
</html>
