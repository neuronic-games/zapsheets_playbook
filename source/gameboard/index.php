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
</style>
</head>
<body>

<nav>
  <a class="brand" href="<?= e($_onDomain ? '/' : 'gameboard') ?>"><img src="images/gameboard_icon_180.png" alt=""><b>GameBoard</b></a>
  <div class="nav-links">
    <a href="#apps">Apps</a>
    <a href="#account">Your account</a>
    <button type="button" class="acct-btn" id="navAcct" onclick="acctClick()">Sign in</button>
  </div>
</nav>

<header class="hero">
  <span class="tag">For board game makers</span>
  <h1>Four apps for every step of <em>your game</em>.</h1>
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
})();
</script>
</body>
</html>
