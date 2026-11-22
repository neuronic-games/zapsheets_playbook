<?php
/**
 * GameBoard — the home of the board game apps (PitchBoard, DevBoard, CardBoard, BillBoard),
 * and of the GameBoard account that signs people in to them.
 * URL: {base}gameboard, and the root of mygameboard.app.
 *
 * The apps themselves run at zapsheets.com/app/…; on mygameboard.app the links go there, and the
 * account is handed over in the link (#gbt=…, read by the app and never sent to the server in the URL).
 */
$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$_host = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
$_onDomain = (bool) preg_match('/(^|\.)mygameboard\.app$/', $_host);
$_base = preg_replace('#/gameboard/?$#', '/', $_raw);
if ($_base === $_raw) $_base = '/';
if (substr($_base, -1) !== '/') $_base .= '/';
$_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
$_abs  = $_scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'zapsheets.com') . $_base;
// Where the apps live: here on zapsheets.com, or zapsheets.com/app/ when this is mygameboard.app
$_apps = $_onDomain ? 'https://zapsheets.com/app/' : $_base;
$_page = $_onDomain ? 'https://mygameboard.app/' : $_abs . 'gameboard';
$_desc = 'Apps for board game makers: playtest notes, card design, publisher pitches and a catalog for your games — each on a Google Sheet you own, with one GameBoard account.';
function e($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
$APPS = [
  ['dev',  'DevBoard',   'db_icon_180.png',        'Playtest & develop', 'Every playtest, remembered.', '#49c4d4', 'devboard',
        ['Session log with observations and solutions', 'TODOs from your notes, with owner and due date', 'Estimates, invoices and reports for studios']],
  ['card', 'CardBoard',  'cardboard_icon_180.png', 'Design & print cards', 'Your cards, straight from your sheet.', '#7b6cf6', 'cardboard',
        ['Drag-and-drop card designer, fronts and backs', 'Print-and-play sheets, ready to cut', 'Tabletop Simulator and Screentop export']],
  ['pitch','PitchBoard', 'pb_icon_180.png',        'Pitch to publishers', 'From first sketch to signed deal — tracked.', '#e7a35a', 'pitchboard',
        ['A pitch log for every game, and a board view', 'Sell sheet generator', 'Game pages and links for collaborators']],
  ['bill', 'BillBoard',  'bb_icon_180.png',        'Show & sell', 'Your games. A polished page — instantly.', '#9ed29f', 'billboard',
        ['A catalog of your games, and a page for each', 'Prices and buy links', 'Shareable public links — no website to host']],
];
?><!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= e($_base) ?>" />
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GameBoard — Apps for Board Game Makers</title>
<meta name="description" content="<?= e($_desc) ?>">
<link rel="canonical" href="<?= e($_page) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="GameBoard">
<meta property="og:title" content="GameBoard — Apps for Board Game Makers">
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

nav { position:sticky; top:0; z-index:50; display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:0 2rem; height:64px; background:rgba(255,255,255,.94); backdrop-filter:blur(12px); border-bottom:1px solid var(--line); }
.brand { display:flex; align-items:center; gap:.55rem; text-decoration:none; }
.brand img { width:32px; height:32px; border-radius:8px; }
.brand b { font-family:'Fraunces',Georgia,serif; font-size:1.2rem; font-weight:600; letter-spacing:-.02em; color:var(--navy); }
.nav-links { display:flex; align-items:center; gap:1.6rem; font-size:14px; }
.nav-links a { text-decoration:none; color:var(--mid); }
.nav-links a:hover { color:var(--navy); }
.acct-btn { background:var(--navy); color:#fff; border:none; border-radius:999px; padding:8px 18px; font:500 14px 'Inter',sans-serif; cursor:pointer; white-space:nowrap; }
.acct-btn:hover { background:var(--navy-2); }
@media (max-width:720px) { .nav-links a { display:none; } nav { padding:0 1rem; } }

.hero { position:relative; overflow:hidden; background:var(--navy); color:#fff; text-align:center; padding:6.5rem 1.5rem 5rem; }
.hero::before { content:''; position:absolute; top:-160px; left:50%; transform:translateX(-50%); width:820px; height:820px; background:radial-gradient(circle, rgba(229,96,79,.22) 0%, transparent 65%); pointer-events:none; }
.hero::after { content:''; position:absolute; bottom:-120px; right:8%; width:520px; height:520px; background:radial-gradient(circle, rgba(246,239,226,.08) 0%, transparent 70%); pointer-events:none; }
.hero > * { position:relative; }
.tag { display:inline-block; font-size:12px; font-weight:500; letter-spacing:.08em; text-transform:uppercase; color:#f3b4aa; border:1px solid rgba(243,180,170,.35); border-radius:999px; padding:5px 14px; margin-bottom:1.6rem; }
.hero h1 { font-family:'Fraunces',Georgia,serif; font-weight:600; font-size:clamp(2.3rem,5vw,3.7rem); line-height:1.1; letter-spacing:-.03em; max-width:820px; margin:0 auto 1.4rem; }
.hero h1 em { font-style:italic; font-weight:400; color:var(--red); }
.hero p { color:rgba(255,255,255,.72); font-size:1.12rem; max-width:600px; margin:0 auto 2.3rem; }
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
.app-btns { display:flex; gap:.5rem; }
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
.me b { display:block; color:var(--navy); }
.me span { font-size:13px; color:var(--muted); }
.plan { font-size:13px; background:#fdf0ed; color:#a8463a; border-radius:8px; padding:.55rem .75rem; margin-bottom:1rem; }
.plan.ok { background:#edf7ee; color:#2e7d32; }
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
.action::before { content:''; position:absolute; top:10%; left:-10%; width:600px; height:600px; background:radial-gradient(circle, rgba(123,108,246,.18), transparent 65%); pointer-events:none; }
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

/* DevBoard: a playtest being logged */
.dv { padding:16px 18px; font-size:13px; }
.win-body.dv { height:auto; min-height:340px; }
.dv-head { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; }
.dv-head b { display:block; font-size:15px; color:#16324f; }
.dv-head span { color:#8a93a6; font-size:12px; }
.dv-timer { display:flex; align-items:center; gap:7px; font:600 15px ui-monospace,Menlo,monospace; color:#16324f; background:#eaf8fa; border-radius:8px; padding:5px 10px; }
.rec { width:9px; height:9px; border-radius:50%; background:#e5604f; animation:blink 1.2s steps(2) infinite; }
@keyframes blink { 50% { opacity:.2; } }
.dv-round { font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; color:#49c4d4; margin:4px 0 8px; }
.dv-note { display:flex; gap:9px; align-items:flex-start; background:#f6fafb; border:1px solid #e3eef0; border-radius:9px; padding:8px 10px; margin-bottom:7px; opacity:0; animation:appear 12s infinite; color:#2b3a4a; }
.dv-note.n2 { animation-delay:1.6s; margin-left:22px; } .dv-note.n3 { animation-delay:3.2s; }
.dvt { flex-shrink:0; font-style:normal; font-size:10px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; border-radius:5px; padding:2px 6px; margin-top:1px; }
.dvt.obs { background:#fff1dc; color:#b26a00; } .dvt.sol { background:#e2f6e5; color:#2e7d32; }
.dv-todo { margin-top:12px; border-top:1px dashed #dde6ea; padding-top:10px; }
.dv-todo b { font-size:11px; letter-spacing:.08em; text-transform:uppercase; color:#8a93a6; display:block; margin-bottom:6px; }
.dv-todo div { display:flex; align-items:center; gap:8px; margin-bottom:6px; color:#2b3a4a; opacity:0; animation:appear 12s infinite 4.4s; }
.dv-todo div.t2 { animation-delay:5s; }
.cbx { width:16px; height:16px; border-radius:4px; border:1.5px solid #b8c6cc; position:relative; flex-shrink:0; }
.cbx::after { content:'✓'; position:absolute; inset:-2px 0 0 2px; font-size:13px; font-weight:800; color:#fff; opacity:0; }
.t1 .cbx { animation:tick 12s infinite; } .t1 span { animation:strike 12s infinite; }
@keyframes tick { 0%,58% { background:#fff; border-color:#b8c6cc; } 62%,88% { background:#49c4d4; border-color:#49c4d4; } 92%,100% { background:#fff; } }
@keyframes strike { 0%,58% { text-decoration:none; color:#2b3a4a; } 62%,88% { text-decoration:line-through; color:#9aa5b1; } }
.t1 .cbx::after { animation:tickmark 12s infinite; }
@keyframes tickmark { 0%,58% { opacity:0; } 62%,88% { opacity:1; } 92%,100% { opacity:0; } }

/* CardBoard: a card being built from the sheet, flipped, then laid out to print */
.cbm { display:grid; grid-template-columns:96px 1fr; background:#eef0f6; }
.cb-pal { background:#fff; border-right:1px solid #e1e4ee; padding:12px 9px; display:flex; flex-direction:column; gap:7px; }
.cb-pal small { font-size:9.5px; font-weight:700; letter-spacing:.09em; text-transform:uppercase; color:#8a8fa8; }
.chip { font-size:11.5px; font-weight:600; color:#2e2763; border:1.5px solid #d6d9e6; border-radius:7px; padding:5px 7px; background:#fff; animation:chip 12s infinite; }
.chip.c2 { animation-delay:1s; } .chip.c3 { animation-delay:2s; } .chip.c4 { animation-delay:3s; }
@keyframes chip { 0%,2% { border-color:#d6d9e6; background:#fff; } 4%,8% { border-color:#7b6cf6; background:#f3f1ff; transform:translateX(6px); } 12%,100% { border-color:#d6d9e6; background:#fff; transform:none; } }
.cb-stage { position:relative; display:flex; align-items:center; justify-content:center; perspective:900px; }
.cb-flip { width:168px; height:236px; position:relative; transform-style:preserve-3d; animation:flip 12s infinite; }
@keyframes flip { 0%,48% { transform:rotateY(0); } 54%,66% { transform:rotateY(180deg); } 72%,100% { transform:rotateY(360deg); } }
.cb-card { position:absolute; inset:0; border-radius:10px; backface-visibility:hidden; -webkit-backface-visibility:hidden; box-shadow:0 10px 26px rgba(30,25,80,.25); overflow:hidden; }
.cb-card.front { background:#fffaf0; border:5px solid #2e2763; padding:8px; display:flex; flex-direction:column; }
.cb-card.back { transform:rotateY(180deg); background:#2e2763 radial-gradient(circle at 50% 45%, #4b3fa8, #2e2763 70%); display:flex; align-items:center; justify-content:center; }
.cb-card.back b { font-family:'Fraunces',Georgia,serif; font-size:24px; color:#f2c14e; letter-spacing:.04em; }
.cb-art, .cb-name, .cb-cost, .cb-text, .cb-type { opacity:0; animation:pop 12s infinite; }
.cb-art { height:108px; border-radius:6px; overflow:hidden; animation-delay:2.1s; } .cb-art img { width:100%; height:100%; object-fit:cover; display:block; }
.cb-name { font-family:'Fraunces',Georgia,serif; font-weight:600; font-size:17px; color:#2e2763; margin:7px 0 0; animation-delay:.1s; }
.cb-type { font-size:9px; font-weight:800; letter-spacing:.12em; color:#b0802a; animation-delay:.1s; }
.cb-cost { position:absolute; top:6px; right:6px; width:30px; height:30px; border-radius:50%; background:#f2c14e; color:#2e2763; font-weight:800; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(0,0,0,.25); z-index:2; animation-delay:1.1s; }
.cb-text { font-size:11.5px; color:#444; line-height:1.35; margin-top:5px; animation-delay:3.1s; }
@keyframes pop { 0% { opacity:0; transform:scale(.6); } 4%,90% { opacity:1; transform:none; } 96%,100% { opacity:0; } }
.cb-sheet { position:absolute; inset:14px; background:#fff; border-radius:6px; box-shadow:0 8px 24px rgba(0,0,0,.18); display:grid; grid-template-columns:repeat(3,1fr); gap:6px; padding:12px; opacity:0; animation:sheet 12s infinite; }
@keyframes sheet { 0%,74% { opacity:0; transform:scale(.9); } 78%,94% { opacity:1; transform:none; } 98%,100% { opacity:0; } }
.cb-sheet div { border:1px dashed #c9cce0; border-radius:4px; background:#fffaf0 center/cover; }
.cb-sheet-tag { position:absolute; right:14px; bottom:14px; font-size:11px; font-weight:700; color:#fff; background:#7b6cf6; border-radius:999px; padding:3px 10px; opacity:0; animation:sheet 12s infinite; }

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

/* BillBoard: a catalog with a rolling banner and games rising in */
.bbm { background:#fff; }
.bb-banner { height:128px; position:relative; overflow:hidden; background:#693f99; }
.bb-slides { display:flex; width:300%; height:100%; animation:slides 12s infinite; }
.bb-slides div { flex:1; background:center/cover; }
@keyframes slides { 0%,28% { transform:translateX(0); } 33%,61% { transform:translateX(-33.333%); } 66%,94% { transform:translateX(-66.666%); } 100% { transform:translateX(0); } }
.bb-brand { position:absolute; left:12px; bottom:10px; display:flex; align-items:center; gap:8px; background:rgba(20,19,42,.72); color:#fff; border-radius:10px; padding:5px 10px 5px 5px; font-weight:600; font-size:13px; }
.bb-brand img { width:26px; height:26px; border-radius:6px; }
.bb-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; padding:12px; }
.bb-g { border:1px solid #eee; border-radius:9px; overflow:hidden; font-size:11.5px; opacity:0; animation:appear 12s infinite; }
.bb-g:nth-child(2) { animation-delay:.4s; } .bb-g:nth-child(3) { animation-delay:.8s; }
.bb-g img { width:100%; height:86px; object-fit:cover; display:block; }
.bb-g div { padding:6px 7px; }
.bb-g b { display:block; color:#1b1b2b; font-size:12.5px; }
.bb-g span { color:#888; }
.bb-buy { position:absolute; right:14px; bottom:14px; background:#2e9d4c; color:#fff; font-weight:700; font-size:12px; border-radius:999px; padding:7px 14px; box-shadow:0 6px 16px rgba(46,157,76,.4); animation:buy 2.4s ease-in-out infinite; }
@keyframes buy { 0%,100% { transform:scale(1); } 50% { transform:scale(1.07); } }
.cursor { position:absolute; width:18px; height:18px; z-index:5; left:50%; top:300px; animation:cur 12s infinite; }
@keyframes cur { 0%,20% { left:50%; top:300px; opacity:0; } 26% { opacity:1; } 40%,60% { left:17%; top:200px; } 47% { transform:scale(.8); } 49% { transform:none; } 72%,86% { left:calc(100% - 52px); top:314px; } 87% { transform:scale(.8); } 89% { transform:none; } 92%,100% { left:calc(100% - 52px); top:314px; opacity:0; } }

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

<nav>
  <a class="brand" href="<?= e($_onDomain ? '/' : 'gameboard') ?>"><img src="images/gameboard_icon_180.png" alt=""><b>GameBoard</b></a>
  <div class="nav-links">
    <a href="#apps">Apps</a>
    <a href="#action">In action</a>
    <a href="#who">Who it’s for</a>
    <a href="#account">Your account</a>
    <button type="button" class="acct-btn" id="navAcct" onclick="acctClick()">Sign in</button>
  </div>
</nav>

<header class="hero">
  <div class="floaters" aria-hidden="true"><img src="images/db_icon_180.png" alt=""><img src="images/cardboard_icon_180.png" alt=""><img src="images/pb_icon_180.png" alt=""><img src="images/bb_icon_180.png" alt=""></div>
  <span class="tag">For board game makers</span>
  <h1>Tools for every step of <em>your game</em>.</h1>
  <p>Playtest it, make its cards, pitch it to publishers and put it on show. Each app runs on a Google Sheet you own — and one GameBoard account signs you in.</p>
  <div class="btns">
    <button type="button" class="btn primary" id="heroCta" onclick="acctClick('signup')">Start your free week</button>
    <a class="btn ghost" href="#apps">See the apps</a>
  </div>
  <div class="journey" aria-label="From playtest to shelf">
    <?php foreach ($APPS as $i => $a): ?>
      <?php if ($i): ?><span class="arrow">→</span><?php endif; ?>
      <a class="step" href="#<?= e($a[0]) ?>"><img src="images/<?= e($a[2]) ?>" alt=""><?= e($a[3]) ?></a>
    <?php endforeach; ?>
  </div>
</header>

<section id="apps">
  <div class="wrap">
    <div class="kicker">The apps</div>
    <h2>From the first playtest to the shelf</h2>
    <p class="sub">Use one or all four. They share the way they work — your data in your own Google Sheet, edited right on the page — so moving from one to the next feels familiar.</p>
    <div class="apps">
      <?php foreach ($APPS as $a): ?>
      <article class="app" id="<?= e($a[0]) ?>" style="--c:<?= e($a[5]) ?>">
        <div class="app-head"><img src="images/<?= e($a[2]) ?>" alt=""><div><div class="app-name"><?= e($a[1]) ?></div><div class="app-stage"><?= e($a[3]) ?></div></div></div>
        <p class="app-line"><?= e($a[4]) ?></p>
        <ul><?php foreach ($a[7] as $li): ?><li><?= e($li) ?></li><?php endforeach; ?></ul>
        <div class="app-btns">
          <a class="f" href="<?= e($_apps . $a[6] . '/features') ?>">Features</a>
          <a class="o app-open" data-app="<?= e($a[6]) ?>" href="<?= e($_apps . $a[6]) ?>" title="Open <?= e($a[1]) ?>">Open</a>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>


<section class="action" id="action">
  <div class="wrap">
    <div class="kicker" style="color:#f3b4aa">See them in action</div>
    <h2>Built for the way games get made</h2>
    <p class="sub">Each app works right on the page — type, drag, click — and saves to your Google Sheet as you go.</p>

    <div class="show" style="--c:#49c4d4">
      <div class="show-text">
        <div class="badge"><img src="images/db_icon_180.png" alt="">DevBoard · Playtest &amp; develop</div>
        <h3>Every playtest, remembered.</h3>
        <p>Start the timer, then jot what you see, round by round. Pair each observation with a fix, turn notes into TODOs, and hand the team a clean report afterwards.</p>
        <a href="<?= e($_apps . 'devboard/features') ?>">DevBoard features →</a>
      </div>
      <div class="win" aria-hidden="true"><div class="win-bar"><i></i><i></i><i></i><span>DevBoard · Floristry · Playtest #12</span></div>
        <div class="win-body dv">
          <div class="dv-head"><div><b>Playtest #12</b><span>4 players · Lantern Café</span></div><div class="dv-timer"><span class="rec"></span><span id="dvTime">00:14:32</span></div></div>
          <div class="dv-round">Round 2</div>
          <div class="dv-note n1"><em class="dvt obs">Seen</em>Auctions in round 2 drag on — 4 bids or more each time</div>
          <div class="dv-note n2"><em class="dvt sol">Fix</em>Cap every auction at 3 bids</div>
          <div class="dv-note n3"><em class="dvt obs">Seen</em>Everyone loves the tulip combo 🌷</div>
          <div class="dv-todo"><b>TODOs</b>
            <div class="t1"><i class="cbx"></i><span>Test the 3-bid cap with 2 players</span></div>
            <div class="t2"><i class="cbx"></i><span>Reprint the bid tokens</span></div>
          </div>
        </div>
      </div>
    </div>

    <div class="show flip" style="--c:#a99cff">
      <div class="show-text">
        <div class="badge"><img src="images/cardboard_icon_180.png" alt="">CardBoard · Design &amp; print cards</div>
        <h3>Your cards, straight from your sheet.</h3>
        <p>Drag your sheet’s columns onto a card, style it once, and every row becomes a card — fronts and backs. Print sheets with crop marks, or send the deck to Tabletop Simulator.</p>
        <a href="<?= e($_apps . 'cardboard/features') ?>">CardBoard features →</a>
      </div>
      <div class="win" aria-hidden="true"><div class="win-bar"><i></i><i></i><i></i><span>CardBoard · Market deck · Layout</span></div>
        <div class="win-body cbm">
          <div class="cb-pal"><small>Card</small><span class="chip c1">Name</span><span class="chip c2">Cost</span><span class="chip c3">Art</span><span class="chip c4">Text</span></div>
          <div class="cb-stage">
            <div class="cb-flip">
              <div class="cb-card front"><div class="cb-cost">3</div><div class="cb-art"><img src="images/billboard-features/cover-floristry.webp" alt=""></div><div class="cb-name">Floristry</div><div class="cb-type">MARKET</div><div class="cb-text">Draw 2 flower cards. Discard 1.</div></div>
              <div class="cb-card back"><b>MARKET</b></div>
            </div>
            <div class="cb-sheet"><div style="background-image:url(images/billboard-features/cover-floristry.webp)"></div><div style="background-image:url(images/billboard-features/cover-scurry.webp)"></div><div style="background-image:url(images/billboard-features/cover-pressure.webp)"></div><div style="background-image:url(images/billboard-features/cover-scurry.webp)"></div><div style="background-image:url(images/billboard-features/cover-pressure.webp)"></div><div style="background-image:url(images/billboard-features/cover-floristry.webp)"></div></div>
            <span class="cb-sheet-tag">Print sheet · 9 per page</span>
          </div>
        </div>
      </div>
    </div>

    <div class="show" style="--c:#f7b267">
      <div class="show-text">
        <div class="badge"><img src="images/pb_icon_180.png" alt="">PitchBoard · Pitch to publishers</div>
        <h3>From first sketch to signed deal — tracked.</h3>
        <p>Every game sits on a board by stage — Design, Pitch, Signed, Published — with how many publishers you’ve pitched it to. Drag a game to its next stage and its dates fill in, saved to your Sheet.</p>
        <a href="<?= e($_apps . 'pitchboard/features') ?>">PitchBoard features →</a>
      </div>
      <div class="win" aria-hidden="true"><div class="win-bar"><i></i><i></i><i></i><span>zapsheets.com/app/…/pitchboard</span></div>
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
        <p>A branded catalog of your games and a page for each one, with prices and buy links. Edit it right on the page — no website to build or host.</p>
        <a href="<?= e($_apps . 'billboard/features') ?>">BillBoard features →</a>
      </div>
      <div class="win" aria-hidden="true"><div class="win-bar"><i></i><i></i><i></i><span>billboard · UP Games</span></div>
        <div class="win-body bbm">
          <div class="bb-banner"><div class="bb-slides"><div style="background-image:url(images/billboard-features/hero-floristry.webp)"></div><div style="background-image:url(images/billboard-features/carousel-pieces.webp)"></div><div style="background-image:url(images/billboard-features/carousel-spread.webp)"></div></div>
            <div class="bb-brand"><img src="images/billboard-features/up-logo.webp" alt="">UP Games</div></div>
          <div class="bb-grid">
            <div class="bb-g"><img src="images/billboard-features/cover-floristry.webp" alt=""><div><b>Floristry</b><span>2 players · 20 min</span></div></div>
            <div class="bb-g"><img src="images/billboard-features/cover-scurry.webp" alt=""><div><b>Scurry Up!</b><span>2–4 · 30 min</span></div></div>
            <div class="bb-g"><img src="images/billboard-features/cover-pressure.webp" alt=""><div><b>Pressure</b><span>3–6 · 25 min</span></div></div>
          </div>
          <span class="bb-buy">Buy now · $20</span>
          <svg class="cursor" viewBox="0 0 24 24"><path d="M4 2l16 9-7 2-3 7z" fill="#14132a" stroke="#fff" stroke-width="1.5" stroke-linejoin="round"/></svg>
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
    <div class="matrix" id="matrix"><div class="mx-row mx-head"><div class="mx-lead">You’re one of…</div><div><img src="images/db_icon_180.png" alt=""><span>DevBoard</span></div><div><img src="images/cardboard_icon_180.png" alt=""><span>CardBoard</span></div><div><img src="images/pb_icon_180.png" alt=""><span>PitchBoard</span></div><div><img src="images/bb_icon_180.png" alt=""><span>BillBoard</span></div></div><div class="mx-row"><div class="mx-who"><span class="mx-ico" style="background:#fdeceb;color:#14132a"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.1V17h6v-.2c0-.8.4-1.6 1-2.1A7 7 0 0 0 12 2z"/></svg></span><div><b>Game designers</b><span>Prototype, playtest and pitch your own games</span></div></div><div class="mx-dot" style="--c:#49c4d4"><i style="transition-delay:0.00s"></i></div><div class="mx-dot" style="--c:#7b6cf6"><i style="transition-delay:0.05s"></i></div><div class="mx-dot" style="--c:#e7a35a"><i style="transition-delay:0.10s"></i></div><div class="mx-dot" style="--c:#7fcf8a"><i style="transition-delay:0.15s" class="half"></i></div></div>
<div class="mx-row"><div class="mx-who"><span class="mx-ico" style="background:#e6f7f9;color:#14132a"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="7" r="3.5"/><path d="M2.5 21a6.5 6.5 0 0 1 13 0"/><circle cx="17.5" cy="8.5" r="2.8"/><path d="M16 14.2a5 5 0 0 1 6 4.8"/></svg></span><div><b>Co-designers &amp; playtesters</b><span>Share sessions, notes and cards with the team</span></div></div><div class="mx-dot" style="--c:#49c4d4"><i style="transition-delay:0.08s"></i></div><div class="mx-dot" style="--c:#7b6cf6"><i style="transition-delay:0.13s" class="half"></i></div><div class="mx-dot" style="--c:#e7a35a"></div><div class="mx-dot" style="--c:#7fcf8a"></div></div>
<div class="mx-row"><div class="mx-who"><span class="mx-ico" style="background:#eef3fb;color:#14132a"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2M3 12h18"/></svg></span><div><b>Development studios</b><span>Develop games for clients — estimates, invoices, reports</span></div></div><div class="mx-dot" style="--c:#49c4d4"><i style="transition-delay:0.16s"></i></div><div class="mx-dot" style="--c:#7b6cf6"><i style="transition-delay:0.21s" class="half"></i></div><div class="mx-dot" style="--c:#e7a35a"></div><div class="mx-dot" style="--c:#7fcf8a"></div></div>
<div class="mx-row"><div class="mx-who"><span class="mx-ico" style="background:#f1effe;color:#14132a"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18.4 2.6a2 2 0 0 1 2.9 2.9L11 15.8 8.2 13z"/><path d="M8 14c-2.2 0-4 1.8-4 4 0 1.3-.7 2.3-2 3 4 1 9 0 9-4"/></svg></span><div><b>Artists &amp; graphic designers</b><span>Lay out cards and print-ready sheets</span></div></div><div class="mx-dot" style="--c:#49c4d4"></div><div class="mx-dot" style="--c:#7b6cf6"><i style="transition-delay:0.29s"></i></div><div class="mx-dot" style="--c:#e7a35a"></div><div class="mx-dot" style="--c:#7fcf8a"></div></div>
<div class="mx-row"><div class="mx-who"><span class="mx-ico" style="background:#f1effe;color:#14132a"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/><rect x="7" y="7.5" width="4" height="6" rx=".8"/><rect x="13" y="7.5" width="4" height="6" rx=".8"/></svg></span><div><b>Digital prototypers</b><span>Send decks to Tabletop Simulator and Screentop</span></div></div><div class="mx-dot" style="--c:#49c4d4"></div><div class="mx-dot" style="--c:#7b6cf6"><i style="transition-delay:0.37s"></i></div><div class="mx-dot" style="--c:#e7a35a"></div><div class="mx-dot" style="--c:#7fcf8a"></div></div>
<div class="mx-row"><div class="mx-who"><span class="mx-ico" style="background:#fff3e6;color:#14132a"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8M12 13v8"/></svg></span><div><b>Self-publishers</b><span>Make the game, then put it on sale</span></div></div><div class="mx-dot" style="--c:#49c4d4"><i style="transition-delay:0.40s" class="half"></i></div><div class="mx-dot" style="--c:#7b6cf6"><i style="transition-delay:0.45s"></i></div><div class="mx-dot" style="--c:#e7a35a"><i style="transition-delay:0.50s" class="half"></i></div><div class="mx-dot" style="--c:#7fcf8a"><i style="transition-delay:0.55s"></i></div></div>
<div class="mx-row"><div class="mx-who"><span class="mx-ico" style="background:#edf7ee;color:#14132a"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l1.5-5h15L21 9M3 9h18v2a3 3 0 0 1-6 0 3 3 0 0 1-6 0 3 3 0 0 1-6 0z"/><path d="M5 13v8h14v-8M10 21v-5h4v5"/></svg></span><div><b>Publishers</b><span>A catalog and a page for every title</span></div></div><div class="mx-dot" style="--c:#49c4d4"><i style="transition-delay:0.48s" class="half"></i></div><div class="mx-dot" style="--c:#7b6cf6"></div><div class="mx-dot" style="--c:#e7a35a"></div><div class="mx-dot" style="--c:#7fcf8a"><i style="transition-delay:0.63s"></i></div></div>
</div>
    <div class="mx-key"><span><i></i>Made for you</span><span><i class="half"></i>Handy too</span></div>
  </div>
</section>
<section class="account" id="account">
  <div class="wrap acct-grid">
    <div>
      <div class="kicker">One account</div>
      <h2>Your GameBoard account</h2>
      <ul class="points">
        <li>One email and password for the board game apps.</li>
        <li>Your first week is free, with every feature — no card needed.</li>
        <li>Your sheets stay yours: the sheets you set up, you edit; people you share them with can view them.</li>
        <li>CardBoard signs in with it today; PitchBoard, DevBoard and BillBoard are moving to it next.</li>
      </ul>
    </div>
    <div class="card" id="acctCard">
      <div id="acctOut">
        <h3>Sign in or start free</h3>
        <p>Create your GameBoard account in a minute — we’ll email you a code to confirm it’s you.</p>
        <button type="button" class="go" onclick="acctClick('signup')">Start your free week</button>
        <div class="foot"><button type="button" class="link" onclick="acctClick('login')">I already have an account — log in</button></div>
      </div>
      <div id="acctIn" hidden>
        <div class="me"><div class="me-dot" id="meDot"></div><div><b id="meName"></b><span id="meEmail"></span></div></div>
        <div class="plan" id="mePlan"></div>
        <button type="button" class="go" onclick="location.hash='apps'">Open an app</button>
        <div class="foot"><button type="button" class="link" onclick="signOut()">Sign out</button></div>
      </div>
    </div>
  </div>
</section>

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

<footer>© <?= date('Y') ?> ZapSheets ·
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
  var KEY = 'cb_account';   // the account kept in this browser — the same key the apps read
  var acct = null;
  try { acct = JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) {}
  window._mode = '';
  function $(id) { return document.getElementById(id); }
  function esc(s) { return String(s).replace(/[&<>"]/g, function(c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function post(fields) {
    var fd = new FormData(); Object.keys(fields).forEach(function(k) { fd.append(k, fields[k]); });
    return fetch(APP_BASE + 'push/gameboardAccount.php', { method: 'POST', body: fd }).then(function(r) { return r.json(); })
      .then(function(r) { if (!r || r.error) throw new Error(r && r.error ? r.error : 'No response'); return r; });
  }
  function store(r) {
    acct = { token: r.token, account: r.account, sheet: '', checked: Date.now() };
    try { localStorage.setItem(KEY, JSON.stringify(acct)); } catch (e) {}
    show();
  }
  // The apps run on zapsheets.com: on mygameboard.app, the "Open" links carry the account over
  function crossOrigin() { try { return new URL(APPS_URL, location.href).origin !== location.origin; } catch (e) { return false; } }
  function linkApps() {
    document.querySelectorAll('.app-open').forEach(function(a) {
      var u = APPS_URL + a.getAttribute('data-app');
      a.href = acct && acct.token && crossOrigin() ? u + '#gbt=' + encodeURIComponent(acct.token) : u;
    });
  }
  function show() {
    var a = acct && acct.account;
    $('navAcct').textContent = a ? (a.name || a.email || 'Account') : 'Sign in';
    $('heroCta').textContent = a ? 'Your account' : 'Start your free week';
    $('acctOut').hidden = !!a; $('acctIn').hidden = !a;
    if (a) {
      var nm = a.name || (a.email || '').split('@')[0];
      $('meDot').textContent = (nm || '?').charAt(0).toUpperCase();
      $('meName').textContent = nm; $('meEmail').textContent = a.email || '';
      var left = Math.max(0, Math.ceil((new Date(a.until).getTime() - Date.now()) / 864e5));
      var txt = a.state === 'trial' ? 'Free week — ' + left + (left === 1 ? ' day' : ' days') + ' left, with every feature.'
              : a.state === 'subscribed' ? 'Subscribed — thank you!' : a.state === 'activated' ? 'Activated — every feature, no time limit.'
              : a.state === 'code' ? 'Unlocked with a code until ' + new Date(a.until).toLocaleDateString() + '.'
              : 'Your free week has ended — subscribe in any app to keep designing.';
      $('mePlan').textContent = txt; $('mePlan').classList.toggle('ok', a.state !== 'locked');
    }
    linkApps();
  }
  window.acctClick = function(mode) {
    if (acct) { location.hash = 'account'; return; }
    openAuth(mode || '');
  };
  window.signOut = function() { acct = null; try { localStorage.removeItem(KEY); } catch (e) {} show(); };
  function openAuth(m) { $('authOverlay').classList.add('open'); authMode(m); }
  window.closeAuth = function() { $('authOverlay').classList.remove('open'); };
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
    if (m === 'login') { post({ action: 'login', email: em, password: pw }).then(function(r) { store(r); closeAuth(); }).catch(function(e) { err.textContent = e.message; }).then(done); return; }
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
    post({ action: 'verify', email: pending.email, password: pending.password, code: code }).then(function(r) { pending = null; store(r); closeAuth(); })
      .catch(function(e) { $('authErr2').textContent = e.message; }).then(function() { b.disabled = false; b.textContent = label; });
  };

  // Keep the account current (plan, days left) when it was last checked a while ago
  if (acct && acct.token && Date.now() - (acct.checked || 0) > 3600e3) {
    post({ action: 'refresh', cbt: acct.token }).then(store).catch(function(e) { if (/sign in/i.test(e.message)) signOut(); });
  }
  show();

  // The DevBoard window's playtest timer keeps running; the who-it's-for dots pop in when scrolled to
  var t0 = 14 * 60 + 32, tEl = $('dvTime');
  setInterval(function() { t0++; var m = Math.floor(t0 / 60), sec = t0 % 60; if (tEl) tEl.textContent = '00:' + (m < 10 ? '0' : '') + m + ':' + (sec < 10 ? '0' : '') + sec; }, 1000);
  var mx = $('matrix');
  if (mx && 'IntersectionObserver' in window) {   // without it the dots simply show
    mx.classList.add('anim');
    new IntersectionObserver(function(es, o) { es.forEach(function(en) { if (en.isIntersecting) { mx.classList.add('in'); o.disconnect(); } }); }, { threshold: .2 }).observe(mx);
  }
})();
</script>
</body>
</html>
