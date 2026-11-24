<?php
$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_base = preg_replace('#/billboard/help/?$#', '/', $_raw);
if (!$_base || $_base === $_raw) { $_base = '/'; }
if (substr($_base, -1) !== '/') { $_base .= '/'; }
$IMG = 'images/help/billboard/';
// A screenshot from the app: click to see it bigger
function shot(string $file, string $caption, string $cls = ''): string {
    global $IMG;
    return '<figure class="shot ' . $cls . '"><img loading="lazy" src="' . $IMG . $file . '.jpg" alt="' . htmlspecialchars(strip_tags($caption), ENT_QUOTES) . '">' .
           '<figcaption>' . $caption . '</figcaption></figure>';
}
// Animated steps: the frames play in turn; each step's text lights up with its frame
function steps(array $frames, string $title): string {
    global $IMG;
    $h = '<div class="steps" data-steps><div class="steps-head"><span class="steps-tag">Watch</span><b>' . $title . '</b>' .
         '<button type="button" class="steps-play" aria-label="Pause">❚❚</button></div><div class="steps-stage">';
    foreach ($frames as $i => $f) $h .= '<img loading="lazy" src="' . $IMG . $f[0] . '.jpg" alt="Step ' . ($i + 1) . '"' . ($i ? '' : ' class="on"') . '>';
    $h .= '<div class="steps-bar"><i></i></div></div><ol class="steps-list">';
    foreach ($frames as $i => $f) $h .= '<li' . ($i ? '' : ' class="on"') . '><button type="button">' . $f[1] . '</button></li>';
    return $h . '</ol></div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>BillBoard – Help</title>
<meta name="description" content="Put your board games on show — a catalog and a page for every game, from your Google Sheet.">
<link rel="icon" type="image/png" sizes="32x32" href="images/bb_icon_180.png" />
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2')  format('woff2'),url('fonts/DINBlack.ttf')  format('truetype'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf') format('truetype'); }
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root { --navy:#173a27; --ink:#1b1d2b; --violet:#2e8b57; --lav:#9fe0b8; --gold:#9fe0b8; --bg:#f3f6f4; --line:#dbe9e0; --mid:#4a5068; --muted:#8a90a6; --side:280px; }
html { scroll-behavior:smooth; }
body { background:var(--bg); font-family:'DINRegular',Arial,sans-serif; color:var(--ink); line-height:1.6; }
a { color:var(--violet); }
/* ── Top bar ── */
.top { position:sticky; top:0; z-index:50; background:#1a1a2e; color:#fff; box-shadow:inset 0 -3px 0 var(--violet); }
.top-in { display:flex; align-items:center; gap:.75rem; padding:.75rem 1.25rem; }
/* the same logo as the designer and the Designs page; it goes back to the Designs page */
.top-home { display:flex; align-items:center; gap:.65rem; color:#fff; text-decoration:none; min-width:0; }
.top img { width:2.2rem; height:2.2rem; border-radius:7px; flex-shrink:0; }
.top-name { display:flex; flex-direction:column; min-width:0; }
.brand { font-family:'DINBlack',sans-serif; font-size:1rem; letter-spacing:.03em; line-height:1.25; }
.brand .c { color:var(--gold); } .brand .b { color:var(--lav); }
.top-lbl { font-size:.73rem; opacity:.6; white-space:nowrap; }
.top-sp { flex:1; }
.top a.open { font-family:'DINBlack',sans-serif; font-size:.7rem; letter-spacing:.08em; text-transform:uppercase; color:#fff; text-decoration:none; background:var(--violet); padding:.45rem .9rem; border-radius:8px; }
.menu-btn { display:none; background:rgba(255,255,255,.14); color:#fff; border:none; border-radius:8px; padding:.45rem .7rem; font:inherit; font-size:.8rem; cursor:pointer; }
/* ── Layout: topics on the left, details on the right ── */
.wrap { display:grid; grid-template-columns:var(--side) minmax(0,1fr); max-width:1320px; margin:0 auto; }
.side { position:sticky; top:59px; height:calc(100vh - 59px); overflow-y:auto; padding:1.2rem .9rem 2rem 1.25rem; border-right:1px solid var(--line); background:#fff; scrollbar-width:thin; }
.find { width:100%; padding:.55rem .75rem; border:1.5px solid var(--line); border-radius:9px; font:inherit; font-size:.88rem; margin-bottom:.9rem; background:#f8fcf9; }
.find:focus { outline:none; border-color:var(--violet); box-shadow:0 0 0 3px #e3f3ea; background:#fff; }
.side h4 { font-family:'DINBlack',sans-serif; font-weight:400; font-size:.64rem; letter-spacing:.12em; text-transform:uppercase; color:var(--muted); margin:1.1rem .5rem .35rem; }
.side h4:first-of-type { margin-top:.2rem; }
.side a { display:flex; align-items:center; gap:.55rem; padding:.36rem .55rem; border-radius:8px; color:var(--mid); text-decoration:none; font-size:.9rem; line-height:1.3; }
.side a .n { font-family:'DINBlack',sans-serif; font-size:.62rem; color:#b4b8c9; width:1.3rem; text-align:right; flex-shrink:0; }
.side a:hover { background:#eef8f2; color:var(--navy); }
.side a.on { background:#e3f3ea; color:var(--navy); font-family:'DINBlack',sans-serif; font-size:.86rem; }
.side a.on .n { color:var(--violet); }
.side a.hide, .side h4.hide { display:none; }
.main { padding:2rem clamp(1.2rem,4vw,3.5rem) 6rem; min-width:0; }
.hero { background:linear-gradient(135deg,#1c1a2b 0%,#173a27 70%,#246f45 100%); color:#fff; border-radius:18px; padding:2.2rem 2.2rem 2.2rem; display:grid; grid-template-columns:1fr 300px; gap:2rem; align-items:center; overflow:hidden; }
.hero h1 { font-family:'DINBlack',sans-serif; font-weight:400; font-size:clamp(1.6rem,3.4vw,2.3rem); line-height:1.1; letter-spacing:.02em; }
.hero h1 .c { color:var(--gold); } .hero h1 .b { color:var(--lav); }
.hero p { color:rgba(255,255,255,.78); margin-top:.7rem; max-width:520px; }
.hero .quick { display:flex; flex-wrap:wrap; gap:.45rem; margin-top:1.2rem; }
.hero .quick a { color:#fff; text-decoration:none; font-size:.8rem; border:1px solid rgba(255,255,255,.3); border-radius:999px; padding:.3rem .8rem; }
.hero .quick a:hover { background:rgba(255,255,255,.12); }
.hero img { width:100%; border-radius:12px; box-shadow:0 20px 50px rgba(0,0,0,.45); transform:rotate(2deg); }
/* ── Topics ── */
.topic { padding-top:2.6rem; scroll-margin-top:70px; max-width:900px; }
.topic > h2 { display:flex; align-items:baseline; gap:.7rem; font-family:'DINBlack',sans-serif; font-weight:400; font-size:1.45rem; letter-spacing:.02em; color:var(--navy); margin-bottom:.4rem; }
.topic > h2 .n { font-size:.8rem; color:var(--violet); background:#e3f3ea; border-radius:6px; padding:.05rem .45rem; }
.topic > .lead { font-size:1.02rem; color:var(--mid); margin-bottom:1rem; }
.topic h3 { font-family:'DINBlack',sans-serif; font-weight:400; font-size:.8rem; letter-spacing:.09em; text-transform:uppercase; color:var(--violet); margin:1.5rem 0 .4rem; }
.topic p, .topic li { font-size:.95rem; color:#3c4052; }
.topic p + p { margin-top:.6rem; }
.topic ul, .topic ol { padding-left:1.3rem; margin-top:.4rem; }
.topic li { margin-bottom:.35rem; }
.topic li::marker { color:var(--violet); }
.sep { border:none; border-top:1px solid var(--line); margin:2.6rem 0 0; max-width:900px; }
kbd, code { font-family:ui-monospace,Menlo,monospace; font-size:.8em; background:#e3f3ea; color:#246f45; border-radius:4px; padding:.05rem .32rem; }
.btn-like { display:inline-block; font-family:'DINBlack',sans-serif; font-size:.68rem; letter-spacing:.06em; text-transform:uppercase; border:1.5px solid #b9dcc7; border-radius:6px; padding:0 .4rem; color:var(--navy); background:#fff; white-space:nowrap; }
.tip, .note { border-radius:10px; padding:.75rem 1rem; margin-top:1rem; font-size:.9rem; }
.tip  { background:#fff9e8; border-left:4px solid var(--gold); color:#5a4a1f; }
.note { background:#fff; border-left:4px solid var(--violet); color:#3c4052; box-shadow:0 1px 4px rgba(0,0,0,.04); }
.tip b:first-child, .note b:first-child { font-family:'DINBlack',sans-serif; font-weight:400; }
.two { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1fr); gap:1.2rem; align-items:start; margin-top:.6rem; }
.two.narrow-left { grid-template-columns:minmax(0,.8fr) minmax(0,1.2fr); }
.cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(210px,1fr)); gap:.75rem; margin-top:.9rem; }
.card { background:#fff; border:1px solid var(--line); border-radius:12px; padding:.9rem 1rem; }
.card b { display:block; font-family:'DINBlack',sans-serif; font-weight:400; font-size:.78rem; letter-spacing:.06em; text-transform:uppercase; color:var(--navy); margin-bottom:.25rem; }
.card p { font-size:.86rem; color:#5a5f74; }
table.keys { width:100%; border-collapse:collapse; margin-top:.8rem; background:#fff; border:1px solid var(--line); border-radius:12px; overflow:hidden; font-size:.9rem; }
table.keys td { padding:.5rem .9rem; border-bottom:1px solid #dbe9e0; color:#3c4052; vertical-align:top; }
table.keys tr:last-child td { border-bottom:none; }
table.keys td:first-child { white-space:nowrap; width:1%; }
table.keys tr.h td { background:#f8fcf9; font-family:'DINBlack',sans-serif; font-size:.68rem; letter-spacing:.1em; text-transform:uppercase; color:var(--muted); }
/* ── Screenshots ── */
.shot { margin:1rem 0 0; background:#fff; border:1px solid var(--line); border-radius:12px; overflow:hidden; box-shadow:0 6px 22px rgba(23,58,39,.08); }
.shot img { display:block; width:100%; height:auto; cursor:zoom-in; }
.shot figcaption { padding:.5rem .85rem .6rem; font-size:.8rem; color:var(--muted); border-top:1px solid #dbe9e0; }
.shot.small { max-width:360px; }
.shot.mid { max-width:560px; }
.two .shot { margin-top:0; }
/* ── Animated steps ── */
.steps { margin-top:1.1rem; background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; box-shadow:0 6px 22px rgba(23,58,39,.08); }
.steps-head { display:flex; align-items:center; gap:.6rem; padding:.6rem .9rem; border-bottom:1px solid #dbe9e0; }
.steps-head b { font-family:'DINBlack',sans-serif; font-weight:400; font-size:.88rem; color:var(--navy); flex:1; }
.steps-tag { font-family:'DINBlack',sans-serif; font-size:.6rem; letter-spacing:.1em; text-transform:uppercase; color:#fff; background:#e8623a; border-radius:5px; padding:.12rem .45rem; }
.steps-play { border:1.5px solid var(--line); background:#fff; border-radius:7px; width:2rem; height:1.7rem; font-size:.62rem; color:var(--navy); cursor:pointer; }
.steps-stage { position:relative; background:#eef0f6; }
.steps-stage img { display:block; width:100%; height:auto; opacity:0; transition:opacity .45s ease; position:absolute; inset:0; cursor:zoom-in; }
.steps-stage img.on { opacity:1; position:relative; }
.steps-stage img.on ~ img.on { position:absolute; }
.steps-bar { position:absolute; left:0; right:0; bottom:0; height:3px; background:rgba(0,0,0,.06); }
.steps-bar i { display:block; height:100%; width:0; background:#e8623a; }
.steps-list { list-style:none; padding:.4rem !important; margin:0 !important; display:grid; grid-auto-flow:column; grid-auto-columns:1fr; gap:.3rem; counter-reset:st; border-top:1px solid #dbe9e0; }
.steps-list li { margin:0 !important; }
.steps-list button { width:100%; height:100%; text-align:left; border:none; background:none; border-radius:9px; padding:.5rem .6rem .5rem 2.1rem; font:inherit; font-size:.82rem; line-height:1.35; color:var(--muted); cursor:pointer; position:relative; }
.steps-list li { counter-increment:st; }
.steps-list button::before { content:counter(st); position:absolute; left:.55rem; top:.5rem; width:1.15rem; height:1.15rem; border-radius:50%; background:#dbe9e0; color:var(--mid); font-family:'DINBlack',sans-serif; font-size:.62rem; display:flex; align-items:center; justify-content:center; }
.steps-list li.on button { background:#eef8f2; color:var(--ink); }
.steps-list li.on button::before { background:#e8623a; color:#fff; }
/* ── Zoomed picture ── */
.lb { position:fixed; inset:0; z-index:200; background:rgba(20,19,42,.82); display:none; align-items:center; justify-content:center; padding:2rem; cursor:zoom-out; }
.lb.open { display:flex; }
.lb img { max-width:100%; max-height:100%; border-radius:10px; box-shadow:0 30px 80px rgba(0,0,0,.5); background:#fff; }
.foot { text-align:center; font-size:.78rem; color:var(--muted); padding:2.5rem 1rem 3rem; }
@media (max-width:980px) {
  .wrap { grid-template-columns:1fr; }
  .side { position:fixed; top:59px; left:0; bottom:0; width:min(320px,86vw); z-index:60; transform:translateX(-105%); transition:transform .2s; box-shadow:10px 0 40px rgba(0,0,0,.2); height:auto; }
  body.nav-open .side { transform:none; }
  .menu-btn { display:block; }
  .hero { grid-template-columns:1fr; } .hero img { display:none; }
  .two, .two.narrow-left { grid-template-columns:1fr; }
  .steps-list { grid-auto-flow:row; }
}
@media (prefers-reduced-motion:reduce) { html { scroll-behavior:auto; } .steps-stage img { transition:none; } }
</style>
</head>
<body>
<header class="top"><div class="top-in">
  <button type="button" class="menu-btn" onclick="document.body.classList.toggle('nav-open')" aria-label="Topics">☰ Topics</button>
  <a class="top-home" href="dashboard" title="Your sheets"><img src="images/bb_icon_180.png" alt=""><span class="top-name"><span class="brand"><span class="c">Bill</span><span class="b">Board</span></span><span class="top-lbl">Help</span></span></a>
  <span class="top-sp"></span>
  <a class="open" href="dashboard">Open BillBoard</a>
</div></header>

<div class="wrap">
<nav class="side" id="side" aria-label="Help topics">
  <input class="find" id="find" type="search" placeholder="Find a topic…" aria-label="Find a topic">
  <h4>Getting started</h4>
  <a href="billboard/help#welcome"><span class="n">1</span>What BillBoard does</a>
  <a href="billboard/help#sheet"><span class="n">2</span>Set up your sheet</a>
  <a href="billboard/help#account"><span class="n">3</span>Sign in</a>
  <a href="billboard/help#owner"><span class="n">4</span>Editing &amp; view only</a>
  <a href="billboard/help#screen"><span class="n">5</span>The screen</a>
  <h4>Your catalog</h4>
  <a href="billboard/help#catalog"><span class="n">6</span>The catalog page</a>
  <a href="billboard/help#brand"><span class="n">7</span>Name, logo &amp; colors</a>
  <a href="billboard/help#carousel"><span class="n">8</span>Banner pictures</a>
  <a href="billboard/help#addgame"><span class="n">9</span>Adding games</a>
  <a href="billboard/help#cards"><span class="n">10</span>Covers, order &amp; removing</a>
  <a href="billboard/help#footer"><span class="n">11</span>Footer, links &amp; sign-ups</a>
  <h4>Game pages</h4>
  <a href="billboard/help#gamepage"><span class="n">12</span>A game’s page</a>
  <a href="billboard/help#stats"><span class="n">13</span>Title, stats, price &amp; buy links</a>
  <a href="billboard/help#media"><span class="n">14</span>Pictures &amp; video</a>
  <a href="billboard/help#content"><span class="n">15</span>Reviews, box contents, resources &amp; FAQ</a>
  <a href="billboard/help#details"><span class="n">16</span>Edit Details</a>
  <h4>Sharing</h4>
  <a href="billboard/help#publish"><span class="n">17</span>Publish your site</a>
  <a href="billboard/help#analytics"><span class="n">18</span>Analytics</a>
  <h4>Saving</h4>
  <a href="billboard/help#saving"><span class="n">19</span>Saving &amp; Fetch</a>
  <h4>Account</h4>
  <a href="billboard/help#plans"><span class="n">20</span>Plans &amp; codes</a>
  <a href="billboard/help#settings"><span class="n">21</span>Settings &amp; menu</a>
  <a href="billboard/help#trouble"><span class="n">22</span>Troubleshooting</a>

</nav>

<main class="main" id="main">
<div class="hero">
  <div>
    <h1><span class="c">Bill</span><span class="b">Board</span> Help</h1>
    <p>Your games, on show: a branded catalog of everything you’ve published and a polished page for each game — buy links, reviews, gallery and FAQ — straight from your Google Sheet. No website to build or host.</p>
    <div class="quick"><a href="billboard/help#sheet">Set up a sheet</a><a href="billboard/help#addgame">Add a game</a><a href="billboard/help#gamepage">Game pages</a><a href="billboard/help#publish">Publish</a><a href="billboard/help#saving">Saving</a></div>
  </div>
  <img src="<?= $IMG ?>hero.jpg" alt="BillBoard">
</div>

<section class="topic" id="welcome">
  <h2><span class="n">1</span>What BillBoard does</h2>
  <p class="lead">Your Google Sheet holds your games; BillBoard turns them into a website. You edit it right on the page — click a word or a picture to change it.</p>
  <div class="cards">
    <div class="card"><b>Catalog</b><p>Your studio’s name, logo and banner, an about line, and a card for every published game.</p></div>
    <div class="card"><b>Game pages</b><p>A page per game: hero picture, stats, price and buy links, reviews, gallery, video and FAQ.</p></div>
    <div class="card"><b>Edit in place</b><p>No separate admin site — change things where you see them.</p></div>
    <div class="card"><b>Publish</b><p>A public link to share with players, press and shops.</p></div>
    <div class="card"><b>Sign-ups</b><p>Visitors can leave their email to hear about new games.</p></div>
  </div>
</section>
<section class="topic" id="sheet">
  <h2><span class="n">2</span>Set up your sheet</h2>
  <?= shot('newsheet', 'The BillBoard start page: open one of your sheets or start a blank one, then paste its link.') ?>
  <ol>
    <li><b>Open or make a sheet.</b> <b>Open a sheet</b> goes to your Google Sheets; <b>Start a blank sheet</b> makes a new one. Your PitchBoard sheet works too — its games are already there.</li>
    <li><b>Share it with BillBoard.</b> In Sheets, click <b>Share</b> and add <code>editor@zapsheets-480701.iam.gserviceaccount.com</code> (the <b>Copy</b> button copies it) as an <b>Editor</b>.</li>
    <li><b>Connect it.</b> Paste the sheet’s link and click <b>Connect &amp; Open</b>. BillBoard adds the tabs it needs and opens your catalog — bookmark it to come straight back.</li>
  </ol>
  <p>BillBoard uses the <b>games</b> tab (one row per game), <b>settings</b> (your studio’s name, logo, colors and links) and a <b>[Game]</b> tab for each game’s page (stats, price, buy links, pictures, reviews, FAQ). The same tab names are used by every GameBoard app.</p>
  <div class="tip"><b>Only Published games</b> appear in the catalog. A game’s page works whatever its status, so you can build it before launch.</div>
</section>
<section class="topic" id="account">
  <h2><span class="n">3</span>Sign in</h2>
  <p class="lead">BillBoard uses your <b>GameBoard account</b> — one email and password for BillBoard, PitchBoard, DevBoard and CardBoard.</p>
  <?= shot('signin', 'Start your free week, or log in.', 'mid') ?>
  <ul>
    <li><b>Start your free week</b> — your email and a password (8 or more characters, with a letter and a number). We email you a 6-digit code; type it in and you’re in, with every feature for 7 days.</li>
    <li><b>Log in</b> — your email and password. You stay signed in on that browser, in every GameBoard app.</li>
    <li><b>Forgot password?</b> — on the log-in screen. We email a code; enter it with your new password.</li>
  </ul>
</section>
<section class="topic" id="owner">
  <h2><span class="n">4</span>Editing &amp; view only</h2>
  <p class="lead">The account that connects a sheet owns it. Signed in as the owner, the page is editable; for everyone else it’s the finished website.</p>
  <?= shot('public', 'What visitors see: the same page, without the editing tools.') ?>
  <ul>
    <li>As the owner you see the editing tools: picture buttons, the banner color button, <b>+</b> cards and <b>Save</b>.</li>
    <li>Signed out — or signed in as someone else — there’s nothing to edit, just the site. A sheet that belongs to another account says <b>View only</b>.</li>
    <li>A sheet nobody has set up yet has <b>Make it mine</b>: click it to take it on.</li>
  </ul>
</section>
<section class="topic" id="screen">
  <h2><span class="n">5</span>The screen</h2>
  <?= shot('catalog', 'The catalog as its owner sees it: the BillBoard bar, then your page with its editing buttons.') ?>
  <ul>
    <li><b>The BillBoard bar</b> (only for you): the logo and your studio’s name on the left; <b>Save</b> and the round person button (the <a href="billboard/help#settings">menu</a>) on the right.</li>
    <li>Under it, <b>your website</b>: your banner with name and logo, the banner pictures, the about line, your games and the footer.</li>
    <li>Every editable thing shows a small button or a dotted outline when you’re the owner.</li>
  </ul>
</section>

<hr class="sep">
<section class="topic" id="catalog">
  <h2><span class="n">6</span>The catalog page</h2>
  <p class="lead">Your home page: everything you’ve published, under your studio’s name.</p>
  <ul>
    <li><b>The banner</b> — your logo and name, in your color.</li>
    <li><b>Banner pictures</b> — up to six, turning over by themselves (see <a href="billboard/help#carousel">Banner pictures</a>).</li>
    <li><b>About</b> — a few lines about your studio.</li>
    <li><b>Our Games</b> — a card per published game: cover, name and tagline. Click one to open its page.</li>
    <li><b>The footer</b> — your logo, tagline and social links, the copyright line, and a sign-up form.</li>
  </ul>
</section>
<section class="topic" id="brand">
  <h2><span class="n">7</span>Name, logo &amp; colors</h2>
  <?= shot('brand', 'Your banner: the logo button, your name, and the color button on the right.') ?>
  <ul>
    <li><b>Name</b> — click it and type. It’s kept when you click away and saved with your next <a href="billboard/help#saving">Save</a>.</li>
    <li><b>Logo</b> — the picture button on the logo: pick an image from your computer. It’s uploaded and used at once — in the banner, the footer and on shared links.</li>
    <li><b>Banner color</b> — the palette button at the right of the banner. The color changes as you pick and is kept when you close the picker, for your next Save.</li>
    <li>More in menu → <b>Settings</b>: the tagline, the template (<b>Classic</b> or <b>OpenSea</b>) and the rest — see <a href="billboard/help#settings">Settings</a>.</li>
  </ul>
</section>
<section class="topic" id="carousel">
  <h2><span class="n">8</span>Banner pictures</h2>
  <p class="lead">The big pictures at the top of the catalog — convention photos, game spreads, lifestyle shots.</p>
  <ul>
    <li><b>+</b> in the middle adds a picture; the picture button (top right) replaces the one showing; <b>×</b> (bottom right) removes it.</li>
    <li>You can also drag an image file from your computer onto the banner.</li>
    <li>Up to six pictures. The arrows and dots move between them; visitors see them turn over by themselves.</li>
    <li>Pictures are uploaded as soon as you pick them. Wide pictures (about 3:1) look best.</li>
  </ul>
</section>
<section class="topic" id="addgame">
  <h2><span class="n">9</span>Adding games</h2>
  <p class="lead">Add a game right in the catalog — then fill in its page.</p>
  <?= steps([['add-1', 'The last card in <b>Our Games</b> is an empty one, with a <b>+</b>.'], ['add-2', 'Click <b>New Game</b> and type the name; add a one-line description under it. <b>Enter</b> adds another.'], ['add-3', '<b>Save</b> (or <kbd>⌘S</kbd>): the game is added to your sheet, and its card is in the catalog.']], 'Add a game') ?>
  <ul>
    <li>Games added here are set to <b>Published</b>, so they show straight away. Each gets a row in <b>games</b> and its own <b>[Game]</b> tab for its page.</li>
    <li>New games show as <em>Unsaved</em> cards until they’re saved. Leaving the page doesn’t lose them — they’re kept in this browser and saved the next time you open BillBoard.</li>
    <li>A game from your sheet appears once its <b>Status</b> is <em>Published</em> — set it in PitchBoard, in the sheet, or with <a href="billboard/help#details">Edit Details</a> on its page.</li>
  </ul>
</section>
<section class="topic" id="cards">
  <h2><span class="n">10</span>Covers, order &amp; removing</h2>
  <?= shot('games', 'Our Games: each card has a picture button and a remove button; the dotted card adds a game.') ?>
  <ul>
    <li><b>Cover</b> — the picture button on a card picks a new cover from your computer (or drag an image onto the card). Square pictures fit best.</li>
    <li><b>×</b> on a card <b>deletes the game</b>: its row and its tab are removed from your sheet. It asks first, and it can’t be undone — to just hide a game, change its status from Published instead.</li>
    <li>Games are shown in the order of the <b>games</b> tab — move the rows in Google Sheets to reorder them, then <b>Fetch</b>.</li>
  </ul>
</section>
<section class="topic" id="footer">
  <h2><span class="n">11</span>Footer, links &amp; sign-ups</h2>
  <ul>
    <li><b>Copyright line</b> and <b>footer note</b> — click and type; kept when you click away and saved with your next Save.</li>
    <li><b>Social links</b> — Instagram, X, Facebook, YouTube and BoardGameGeek. Click an icon to add or change its link; icons without a link only show while you’re editing.</li>
    <li><b>Sign-up form</b> — visitors leave their email to hear about new games. Its heading and text are in menu → <b>Settings</b> → Newsletter Subscribe. Each sign-up is added to a <b>[subscribe] notes</b> tab in your sheet.</li>
  </ul>
</section>

<hr class="sep">
<section class="topic" id="gamepage">
  <h2><span class="n">12</span>A game’s page</h2>
  <p class="lead">Each game has its own page — click a card in the catalog. As the owner, the whole page is editable.</p>
  <?= shot('gamepage', 'Sky Kites: the hero picture, title, stats, price and buttons, and the reviews strip.') ?>
  <ul>
    <li><b>The top bar</b>: <b>All Games</b> back to the catalog; on the right <b>Edit Details</b>, <b>Save Changes</b> and <b>Cancel</b>.</li>
    <li>Then, from the top: the <b>hero</b> (picture, title, tagline, stats, designers and artists, price and buttons), <b>reviews</b>, <b>About the game</b>, <b>Gallery</b>, <b>Watch</b>, <b>What’s in the box</b>, <b>Video reviews</b>, <b>Resources</b>, <b>FAQ</b> and <b>More games</b>.</li>
    <li>Sections you haven’t filled in only show while you’re editing — visitors never see empty boxes.</li>
    <li>Changes on a game page are kept in this browser and saved with <b>Save Changes</b> (or <kbd>⌘S</kbd>, or by themselves after a pause); <b>Cancel</b> drops the ones not saved yet.</li>
  </ul>
</section>
<section class="topic" id="stats">
  <h2><span class="n">13</span>Title, stats, price &amp; buy links</h2>
  <ul>
    <li><b>Title</b> and <b>tagline</b> — click and type.</li>
    <li><b>Players</b>, <b>playing time</b> and <b>weight</b> — the small boxes under the tagline.</li>
    <li><b>Designers</b> and <b>artists</b> — type a name in <b>+ name</b>.</li>
    <li><b>Price</b> — click it to set it (<em>$25</em>).</li>
    <li><b>Buy links</b> — <b>Add buy link</b> adds a button to a shop (your store, a retailer, Kickstarter); give it a label and a link. <b>Rules</b> appears when the game has a rules link.</li>
    <li><b>Reviews</b> — the colored strip under the hero. <b>+ Add review</b>: a quote and who said it.</li>
  </ul>
</section>
<section class="topic" id="media">
  <h2><span class="n">14</span>Pictures &amp; video</h2>
  <?= shot('gallery', 'The gallery: a big picture, thumbnails under it, and + to add another.') ?>
  <ul>
    <li><b>Hero picture</b> — the picture button on the hero picks a new one. Wide pictures work best.</li>
    <li><b>Gallery</b> — <b>+</b> adds pictures (or drag images onto it); <b>×</b> on a thumbnail removes it. Visitors click through them.</li>
    <li><b>Watch</b> — drop a video file, or click to paste a YouTube link: the game’s main video.</li>
    <li><b>Video reviews</b> — up to three more videos, from reviewers or playthroughs.</li>
    <li>Pictures upload as you add them; they’re on the page once you <b>Save Changes</b>.</li>
  </ul>
</section>
<section class="topic" id="content">
  <h2><span class="n">15</span>Reviews, box contents, resources &amp; FAQ</h2>
  <div class="two">
    <?= shot('box', 'What’s in the box: one item per line.') ?>
    <?= shot('faq', 'FAQ: a question and its answer; an empty pair waits at the end.') ?>
  </div>
  <ul>
    <li><b>About the game</b> — the description; click and type.</li>
    <li><b>What’s in the box</b> — the components, one per line (<em>60 wind cards</em>).</li>
    <li><b>Resources</b> — <b>Add resource</b> links to rules, print-and-play files, a sell sheet, or anything else.</li>
    <li><b>FAQ</b> — type a question and its answer; a new empty pair appears for the next. Empty pairs aren’t shown to visitors.</li>
    <li><b>More games</b> — your other published games, so visitors keep browsing.</li>
  </ul>
</section>
<section class="topic" id="details">
  <h2><span class="n">16</span>Edit Details</h2>
  <p class="lead">The game’s information that isn’t written on the page itself.</p>
  <div class="two narrow-left">
    <?= shot('details', 'Edit Details: status, availability, designers and artists, dates, pictures and links.') ?>
    <div>
      <ul>
        <li><b>Status</b> — Concept, Developing, Playtesting, Pitching, Signed, <b>Published</b> or Shelved. Only Published games are in the catalog.</li>
        <li><b>Availability</b> — where to get it (<em>Kickstarter</em>, <em>In stores</em>).</li>
        <li><b>Designers</b> and <b>artists</b> (up to four each), and the <b>dates</b> started, signed and published.</li>
        <li><b>Pictures</b> — the header (hero), cover and product image, as links or uploaded (📷). <b>Video</b> — a YouTube link.</li>
        <li><b>Links</b> — the game’s page, sell sheet, rules and play-online links.</li>
        <li><b>Apply</b> puts them on the page; <b>Save Changes</b> saves them to your sheet.</li>
      </ul>
    </div>
  </div>
</section>

<hr class="sep">
<section class="topic" id="publish">
  <h2><span class="n">17</span>Publish your site</h2>
  <p class="lead">Share your BillBoard with anyone — players, press, shops — with a link that doesn’t show your sheet.</p>
  <div class="two narrow-left">
    <?= shot('publish', 'Menu → Publish Site: your public link, to copy or open.') ?>
    <div>
      <ul>
        <li>Menu → <b>Publish Site</b> gives you the public link to your catalog (<b>Catalog</b>), and on a game page a link straight to that game (<b>This Game</b>). <b>Copy</b> it, or <b>Open</b> it to see it as visitors do.</li>
        <li>The link stays the same: publishing again updates what’s at it.</li>
        <li>Visitors don’t need an account, and can’t change anything.</li>
        <li>Only games with the status <b>Published</b> are in the public catalog.</li>
      </ul>
    </div>
  </div>
</section>
<section class="topic" id="analytics">
  <h2><span class="n">18</span>Analytics</h2>
  <ul>
    <li>Menu → <b>Analytics</b> shows the visits to your catalog and your game pages.</li>
  </ul>
</section>

<hr class="sep">
<section class="topic" id="saving">
  <h2><span class="n">19</span>Saving &amp; Fetch</h2>
  <?= shot('save', '<b>Save</b> in the BillBoard bar.', 'small') ?>
  <ul>
    <li>As in CardBoard, your changes are <b>kept in this browser</b> as you make them — your name, the footer, the banner color, new and deleted games, pictures on cards, and everything on a game page — and sent to your sheet together.</li>
    <li><b>Save</b> (or <kbd>⌘S</kbd>) sends them now; on a game page the button is <b>Save Changes</b>. It’s lit while there’s something to save, fills up as it goes, and you can keep working.</li>
    <li>BillBoard also <b>saves by itself</b> once you’ve stopped for about a minute and a half (never while you’re typing). Closing the page doesn’t lose anything — unsaved changes are saved the next time you open BillBoard.</li>
    <li><b>Cancel</b> on a game page drops the changes to that game that aren’t saved yet.</li>
    <li>Pictures upload as soon as you pick them; <b>Settings</b> and <b>Add Game</b> (the dialog) save when you click their button. Anything that reads from the sheet — Settings, Publish — saves your changes first.</li>
    <li>If the server can’t be reached, your changes wait for the next Save.</li>
    <li><b>Fetch</b> (menu) reads your games and settings from the sheet again — use it after editing the sheet in Google Sheets or in PitchBoard.</li>
  </ul>
</section>

<hr class="sep">
<section class="topic" id="plans">
  <h2><span class="n">20</span>Plans &amp; codes</h2>
  <div class="two">
    <?= shot('plans', 'Menu → Subscription: your plan and the ways to keep going.') ?>
    <div>
      <div class="cards" style="grid-template-columns:1fr;margin-top:0">
        <div class="card"><b>Free week</b><p>Every feature for 7 days from sign-up. No card needed.</p></div>
        <div class="card"><b>BillBoard — $4.99/month</b><p>Keep editing after the free week. Cancel any time from <b>Manage billing</b>.</p></div>
        <div class="card"><b>All-in-one — $9.99/month</b><p>BillBoard, PitchBoard, DevBoard and CardBoard together.</p></div>
        <div class="card"><b>Codes</b><p><b>Have a code?</b> in the plans (or menu → <b>Enter a code</b>) unlocks time for every app, or just the one it was made for.</p></div>
      </div>
    </div>
  </div>
  <p style="margin-top:1rem">After the free week your sheet is view only until you subscribe — nothing in it is lost.</p>
</section>
<section class="topic" id="settings">
  <h2><span class="n">21</span>Settings &amp; menu</h2>
  <div class="two">
    <?= shot('menu', 'The menu, from the round person button.') ?>
    <?= shot('settings', 'Settings: your company, banner pictures, social links and display.') ?>
  </div>
  <ul>
    <li><b>Settings</b> — your <b>company</b> (name, logo link, tagline, about), up to six <b>banner pictures</b> (as links), <b>social links</b>, and <b>display</b>: the banner color, the template (<b>Classic</b> or <b>OpenSea</b>), the copyright line and footer note, and the <b>newsletter</b> sign-up’s heading and text.</li>
    <li><b>Profile</b> — who’s signed in. <b>Analytics</b>, <b>Fetch</b> and <b>Publish Site</b> — see above.</li>
    <li><b>Help</b> — this page. <b>Donate</b> — support BillBoard. <b>Subscription</b> / <b>Enter a code</b> — see <a href="billboard/help#plans">Plans</a>.</li>
    <li><b>GameBoard</b> — back to the GameBoard home. <b>Sign out</b> — asks first, then signs you out of every app in this browser.</li>
  </ul>
</section>
<section class="topic" id="trouble">
  <h2><span class="n">22</span>Troubleshooting</h2>
  <ul>
    <li><b>“Sheet not shared”</b> when connecting — check it’s shared with <code>editor@zapsheets-480701.iam.gserviceaccount.com</code> as an <b>Editor</b>.</li>
    <li><b>A game isn’t in the catalog</b> — its Status isn’t <em>Published</em>. Change it in <a href="billboard/help#details">Edit Details</a>, then Save Changes.</li>
    <li><b>Changes made in Google Sheets don’t show</b> — menu → <b>Fetch</b>.</li>
    <li><b>No editing buttons</b> — you’re not signed in as the sheet’s owner, or your free week has ended (<a href="billboard/help#plans">Plans</a>).</li>
    <li><b>A picture won’t upload</b> — use a JPEG, PNG, GIF or WebP image.</li>
    <li><b>No code email</b> — check spam, and wait a minute before asking for another.</li>
  </ul>
</section>

<p class="foot">© <?= date('Y') ?> ZapSheets · <a href="dashboard">Your sheets</a> · <a href="billboard/features">Features</a> · <a href="gameboard">GameBoard</a></p>
</main>
</div>

<div class="lb" id="lb" onclick="this.classList.remove('open')"><img alt=""></div>

<script>
(function() {
  'use strict';
  var side = document.getElementById('side'), links = Array.prototype.slice.call(side.querySelectorAll('a'));
  var topics = links.map(function(a) { return document.getElementById(a.getAttribute('href').split('#')[1]); });

  // The topic in view is marked in the index
  function mark() {
    var y = window.scrollY + 120, cur = 0;
    topics.forEach(function(t, i) { if (t && t.offsetTop <= y) cur = i; });
    links.forEach(function(a, i) { a.classList.toggle('on', i === cur); });
    var on = links[cur]; if (on && (on.offsetTop < side.scrollTop + 60 || on.offsetTop > side.scrollTop + side.clientHeight - 60)) side.scrollTop = on.offsetTop - side.clientHeight / 2;
  }
  window.addEventListener('scroll', mark, { passive: true }); mark();
  links.forEach(function(a) { a.addEventListener('click', function() { document.body.classList.remove('nav-open'); }); });

  // Find a topic: matches the index names and each topic's words
  var words = topics.map(function(t) { return t ? t.textContent.toLowerCase() : ''; });
  document.getElementById('find').addEventListener('input', function() {
    var q = this.value.trim().toLowerCase();
    links.forEach(function(a, i) { a.classList.toggle('hide', !!q && a.textContent.toLowerCase().indexOf(q) < 0 && words[i].indexOf(q) < 0); });
    side.querySelectorAll('h4').forEach(function(h) {
      var n = h.nextElementSibling, any = false;
      while (n && n.tagName === 'A') { if (!n.classList.contains('hide')) any = true; n = n.nextElementSibling; }
      h.classList.toggle('hide', !any);
    });
  });

  // Click a picture to see it bigger
  var lb = document.getElementById('lb');
  document.addEventListener('click', function(e) {
    var img = e.target.closest('.shot img, .steps-stage img, .hero img'); if (!img) return;
    lb.querySelector('img').src = img.src; lb.classList.add('open');
  });
  document.addEventListener('keydown', function(e) { if (e.key === 'Escape') lb.classList.remove('open'); });

  // Animated steps: play in turn while on screen; click a step to jump to it; pause with the button
  var STEP_MS = 3200, reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  document.querySelectorAll('[data-steps]').forEach(function(box) {
    var imgs = box.querySelectorAll('.steps-stage img'), items = box.querySelectorAll('.steps-list li'), bar = box.querySelector('.steps-bar i');
    var btn = box.querySelector('.steps-play'), i = 0, playing = !reduce, visible = false, t0 = 0, raf = 0;
    function show(n) {
      i = (n + imgs.length) % imgs.length;
      imgs.forEach(function(im, k) { im.classList.toggle('on', k === i); });
      items.forEach(function(li, k) { li.classList.toggle('on', k === i); });
      t0 = performance.now();
    }
    function tick(now) {
      raf = 0;
      if (!playing || !visible) { bar.style.width = '0'; return; }
      var p = (now - t0) / STEP_MS;
      if (p >= 1) { show(i + 1); p = 0; }
      bar.style.width = (p * 100) + '%';
      raf = requestAnimationFrame(tick);
    }
    function run() { if (!raf && playing && visible) { t0 = performance.now(); raf = requestAnimationFrame(tick); } }
    function setPlaying(p) { playing = p; btn.textContent = p ? '❚❚' : '▶'; btn.setAttribute('aria-label', p ? 'Pause' : 'Play'); if (p) run(); }
    btn.addEventListener('click', function() { setPlaying(!playing); });
    items.forEach(function(li, k) { li.querySelector('button').addEventListener('click', function() { show(k); setPlaying(false); bar.style.width = '0'; }); });
    if ('IntersectionObserver' in window) new IntersectionObserver(function(es) { visible = es[0].isIntersecting; run(); }, { threshold: .4 }).observe(box);
    else { visible = true; run(); }
    setPlaying(playing);
  });
})();
</script>
</body>
</html>
