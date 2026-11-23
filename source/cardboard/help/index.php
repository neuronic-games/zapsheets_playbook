<?php
$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_base = preg_replace('#/cardboard/help/?$#', '/', $_raw);
if (!$_base || $_base === $_raw) { $_base = '/'; }
if (substr($_base, -1) !== '/') { $_base .= '/'; }
$IMG = 'images/help/cardboard/';
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
<title>CardBoard – Help</title>
<meta name="description" content="Design, print, and share your card deck — fast.">
<link rel="icon" type="image/png" sizes="32x32" href="images/cardboard_icon_32.png" />
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2')  format('woff2'),url('fonts/DINBlack.ttf')  format('truetype'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf') format('truetype'); }
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root { --navy:#2e2763; --ink:#1b1d2b; --violet:#5b4fd6; --lav:#a99cff; --gold:#f2c14e; --bg:#f4f3fa; --line:#e3e0f3; --mid:#4a5068; --muted:#8a90a6; --side:280px; }
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
.find { width:100%; padding:.55rem .75rem; border:1.5px solid var(--line); border-radius:9px; font:inherit; font-size:.88rem; margin-bottom:.9rem; background:#fafaff; }
.find:focus { outline:none; border-color:var(--violet); box-shadow:0 0 0 3px #ece9ff; background:#fff; }
.side h4 { font-family:'DINBlack',sans-serif; font-weight:400; font-size:.64rem; letter-spacing:.12em; text-transform:uppercase; color:var(--muted); margin:1.1rem .5rem .35rem; }
.side h4:first-of-type { margin-top:.2rem; }
.side a { display:flex; align-items:center; gap:.55rem; padding:.36rem .55rem; border-radius:8px; color:var(--mid); text-decoration:none; font-size:.9rem; line-height:1.3; }
.side a .n { font-family:'DINBlack',sans-serif; font-size:.62rem; color:#b4b8c9; width:1.3rem; text-align:right; flex-shrink:0; }
.side a:hover { background:#f3f1ff; color:var(--navy); }
.side a.on { background:#ece9ff; color:var(--navy); font-family:'DINBlack',sans-serif; font-size:.86rem; }
.side a.on .n { color:var(--violet); }
.side a.hide, .side h4.hide { display:none; }
.main { padding:2rem clamp(1.2rem,4vw,3.5rem) 6rem; min-width:0; }
.hero { background:linear-gradient(135deg,#1c1a2b 0%,#2e2763 70%,#4b3fa8 100%); color:#fff; border-radius:18px; padding:2.2rem 2.2rem 2.2rem; display:grid; grid-template-columns:1fr 300px; gap:2rem; align-items:center; overflow:hidden; }
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
.topic > h2 .n { font-size:.8rem; color:var(--violet); background:#ece9ff; border-radius:6px; padding:.05rem .45rem; }
.topic > .lead { font-size:1.02rem; color:var(--mid); margin-bottom:1rem; }
.topic h3 { font-family:'DINBlack',sans-serif; font-weight:400; font-size:.8rem; letter-spacing:.09em; text-transform:uppercase; color:var(--violet); margin:1.5rem 0 .4rem; }
.topic p, .topic li { font-size:.95rem; color:#3c4052; }
.topic p + p { margin-top:.6rem; }
.topic ul, .topic ol { padding-left:1.3rem; margin-top:.4rem; }
.topic li { margin-bottom:.35rem; }
.topic li::marker { color:var(--violet); }
.sep { border:none; border-top:1px solid var(--line); margin:2.6rem 0 0; max-width:900px; }
kbd, code { font-family:ui-monospace,Menlo,monospace; font-size:.8em; background:#ece9ff; color:#4b3fa8; border-radius:4px; padding:.05rem .32rem; }
.btn-like { display:inline-block; font-family:'DINBlack',sans-serif; font-size:.68rem; letter-spacing:.06em; text-transform:uppercase; border:1.5px solid #cfc9f3; border-radius:6px; padding:0 .4rem; color:var(--navy); background:#fff; white-space:nowrap; }
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
table.keys td { padding:.5rem .9rem; border-bottom:1px solid #efedf8; color:#3c4052; vertical-align:top; }
table.keys tr:last-child td { border-bottom:none; }
table.keys td:first-child { white-space:nowrap; width:1%; }
table.keys tr.h td { background:#f6f5fd; font-family:'DINBlack',sans-serif; font-size:.68rem; letter-spacing:.1em; text-transform:uppercase; color:var(--muted); }
/* ── Screenshots ── */
.shot { margin:1rem 0 0; background:#fff; border:1px solid var(--line); border-radius:12px; overflow:hidden; box-shadow:0 6px 22px rgba(46,39,99,.08); }
.shot img { display:block; width:100%; height:auto; cursor:zoom-in; }
.shot figcaption { padding:.5rem .85rem .6rem; font-size:.8rem; color:var(--muted); border-top:1px solid #f0eef9; }
.shot.small { max-width:360px; }
.shot.mid { max-width:560px; }
.two .shot { margin-top:0; }
/* ── Animated steps ── */
.steps { margin-top:1.1rem; background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; box-shadow:0 6px 22px rgba(46,39,99,.08); }
.steps-head { display:flex; align-items:center; gap:.6rem; padding:.6rem .9rem; border-bottom:1px solid #f0eef9; }
.steps-head b { font-family:'DINBlack',sans-serif; font-weight:400; font-size:.88rem; color:var(--navy); flex:1; }
.steps-tag { font-family:'DINBlack',sans-serif; font-size:.6rem; letter-spacing:.1em; text-transform:uppercase; color:#fff; background:#e8623a; border-radius:5px; padding:.12rem .45rem; }
.steps-play { border:1.5px solid var(--line); background:#fff; border-radius:7px; width:2rem; height:1.7rem; font-size:.62rem; color:var(--navy); cursor:pointer; }
.steps-stage { position:relative; background:#eef0f6; }
.steps-stage img { display:block; width:100%; height:auto; opacity:0; transition:opacity .45s ease; position:absolute; inset:0; cursor:zoom-in; }
.steps-stage img.on { opacity:1; position:relative; }
.steps-stage img.on ~ img.on { position:absolute; }
.steps-bar { position:absolute; left:0; right:0; bottom:0; height:3px; background:rgba(0,0,0,.06); }
.steps-bar i { display:block; height:100%; width:0; background:#e8623a; }
.steps-list { list-style:none; padding:.4rem !important; margin:0 !important; display:grid; grid-auto-flow:column; grid-auto-columns:1fr; gap:.3rem; counter-reset:st; border-top:1px solid #f0eef9; }
.steps-list li { margin:0 !important; }
.steps-list button { width:100%; height:100%; text-align:left; border:none; background:none; border-radius:9px; padding:.5rem .6rem .5rem 2.1rem; font:inherit; font-size:.82rem; line-height:1.35; color:var(--muted); cursor:pointer; position:relative; }
.steps-list li { counter-increment:st; }
.steps-list button::before { content:counter(st); position:absolute; left:.55rem; top:.5rem; width:1.15rem; height:1.15rem; border-radius:50%; background:#e7e5f3; color:var(--mid); font-family:'DINBlack',sans-serif; font-size:.62rem; display:flex; align-items:center; justify-content:center; }
.steps-list li.on button { background:#f6f4ff; color:var(--ink); }
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
  <a class="top-home" href="cardboard" title="Your designs"><img src="images/cardboard_icon_180.png" alt=""><span class="top-name"><span class="brand"><span class="c">Card</span><span class="b">Board</span></span><span class="top-lbl">Help</span></span></a>
  <span class="top-sp"></span>
  <a class="open" href="cardboard">Open CardBoard</a>
</div></header>

<div class="wrap">
<nav class="side" id="side" aria-label="Help topics">
  <input class="find" id="find" type="search" placeholder="Find a topic…" aria-label="Find a topic">
  <h4>Getting started</h4>
  <a href="cardboard/help#welcome"><span class="n">1</span>What CardBoard does</a>
  <a href="cardboard/help#sheet"><span class="n">2</span>Set up your sheet</a>
  <a href="cardboard/help#account"><span class="n">3</span>Sign in</a>
  <a href="cardboard/help#owner"><span class="n">4</span>Your sheets &amp; view only</a>
  <a href="cardboard/help#screen"><span class="n">5</span>The screen</a>
  <h4>Your cards</h4>
  <a href="cardboard/help#decks"><span class="n">6</span>Decks</a>
  <a href="cardboard/help#cards"><span class="n">7</span>The Cards view</a>
  <a href="cardboard/help#addcard"><span class="n">8</span>Stepping through &amp; adding cards</a>
  <h4>Designing</h4>
  <a href="cardboard/help#elements"><span class="n">9</span>Adding elements</a>
  <a href="cardboard/help#fields"><span class="n">10</span>Card fields</a>
  <a href="cardboard/help#text"><span class="n">11</span>Text style</a>
  <a href="cardboard/help#pictures"><span class="n">12</span>Pictures</a>
  <a href="cardboard/help#mapping"><span class="n">13</span>Mappings</a>
  <a href="cardboard/help#generated"><span class="n">14</span>Generated values</a>
  <a href="cardboard/help#arrange"><span class="n">15</span>Moving &amp; lining up</a>
  <a href="cardboard/help#layers"><span class="n">16</span>Layers, groups &amp; lists</a>
  <a href="cardboard/help#backs"><span class="n">17</span>Card backs</a>
  <a href="cardboard/help#tags"><span class="n">18</span>Tags</a>
  <h4>Printing &amp; sharing</h4>
  <a href="cardboard/help#print"><span class="n">19</span>Print</a>
  <a href="cardboard/help#export"><span class="n">20</span>Export &amp; links</a>
  <h4>Saving</h4>
  <a href="cardboard/help#saving"><span class="n">21</span>Saving &amp; working offline</a>
  <a href="cardboard/help#undo"><span class="n">22</span>Undo, copy &amp; paste</a>
  <h4>Account</h4>
  <a href="cardboard/help#plans"><span class="n">23</span>Plans &amp; codes</a>
  <a href="cardboard/help#settings"><span class="n">24</span>Settings &amp; menu</a>
  <a href="cardboard/help#keys"><span class="n">25</span>Keyboard shortcuts</a>
  <a href="cardboard/help#trouble"><span class="n">26</span>Troubleshooting</a>
</nav>

<main class="main" id="main">

<div class="hero">
  <div>
    <h1><span class="c">Card</span><span class="b">Board</span> Help</h1>
    <p>Design board game cards from a Google Sheet — one row per card — then print them or send them to Tabletop Simulator and Screentop.</p>
    <div class="quick"><a href="cardboard/help#sheet">Set up a sheet</a><a href="cardboard/help#elements">Design a card</a><a href="cardboard/help#mapping">Mappings</a><a href="cardboard/help#print">Print</a><a href="cardboard/help#saving">Saving</a></div>
  </div>
  <img src="<?= $IMG ?>layout.jpg" alt="The CardBoard designer">
</div>

<!-- ───────────────────────── Getting started ───────────────────────── -->
<section class="topic" id="welcome">
  <h2><span class="n">1</span>What CardBoard does</h2>
  <p class="lead">Your Google Sheet holds the cards: each tab is a <b>deck</b>, each row a <b>card</b>, each column a <b>field</b> (Name, Cost, Text, Art…). CardBoard turns those rows into designed cards.</p>
  <div class="cards">
    <div class="card"><b>Cards</b><p>Edit the deck as a grid, right in the app.</p></div>
    <div class="card"><b>Tags</b><p>Words like <code>[mana]</code> that become icons or text on every card.</p></div>
    <div class="card"><b>Layout</b><p>Design the card once — every row fills it in.</p></div>
    <div class="card"><b>Print</b><p>Sheets of cards at true size, with crop marks and backs.</p></div>
    <div class="card"><b>Export</b><p>Card sheets for Tabletop Simulator and Screentop, and hosted links.</p></div>
  </div>
</section>

<section class="topic" id="sheet">
  <h2><span class="n">2</span>Set up your sheet</h2>
  <ol>
    <li><b>Headings in row 1.</b> Put the field names in the first row (<em>Name</em>, <em>Type</em>, <em>Cost</em>, <em>Text</em>, <em>Art</em>…) and one card per row below. Each tab is a deck.</li>
    <li><b>Share it with CardBoard.</b> In Sheets, click <b>Share</b> and add <code>editor@zapsheets-480701.iam.gserviceaccount.com</code> as an <b>Editor</b>. CardBoard reads and writes your sheet through that address.</li>
    <li><b>Connect it.</b> Open <a href="cardboard">CardBoard</a>, sign in, paste the spreadsheet's link and continue. The designer opens on your first deck — bookmark that page, or add it to your Home Screen, to come straight back.</li>
  </ol>
  <p>CardBoard adds two tabs to your spreadsheet: <b>layouts</b> (every deck's design and your settings) and <b>tags</b>. Leave them in place. Hidden tabs aren't listed as decks, so hide any tab you don't want to design from.</p>
  <?= shot('cards', 'The sample deck used on this page: 8 spells, with a picture link in <b>Art</b> and a formula in <b>Value</b>.') ?>
  <div class="tip"><b>Picture columns:</b> a column of picture links (Dropbox, Google Drive, any web address) is recognised automatically — it shows thumbnails in the Cards view and is added to a card as a picture.</div>
</section>

<section class="topic" id="account">
  <h2><span class="n">3</span>Sign in</h2>
  <p class="lead">CardBoard uses your <b>GameBoard account</b> — one email and password for CardBoard, DevBoard, PitchBoard and BillBoard.</p>
  <div class="two">
    <?= shot('signin', 'Start your free week, or log in.') ?>
    <?= shot('signup', 'New accounts: the checklist ticks off each password rule.') ?>
  </div>
  <ul>
    <li><b>Start your free week</b> — enter your email and a password (8 or more characters, with a letter and a number). We email you a 6-digit code; type it in and you're in, with every feature for 7 days.</li>
    <li><b>Log in</b> — your email and password. You stay signed in on that browser.</li>
    <li><b>Forgot password?</b> — on the log-in screen. We email a code; enter it with your new password.</li>
    <li><b>Profile</b> (menu) — your name and studio. Signing in on any of the four apps signs you in to all of them.</li>
  </ul>
</section>

<section class="topic" id="owner">
  <h2><span class="n">4</span>Your sheets &amp; view only</h2>
  <p class="lead">The account that sets a sheet up in CardBoard owns it: only that account changes its cards and layouts. Anyone else you share the link with can look, not change.</p>
  <?= shot('viewonly', 'A sheet nobody has set up yet opens view only, with <b>Make it mine</b>.') ?>
  <ul>
    <li><b>Make it mine</b> appears when nobody has set the sheet up — click it to take it on. A sheet that belongs to another account just says <b>View only</b>.</li>
    <li>In view only you can browse every card, deck, layout and setting; the editing tools are greyed out and <b>Paste</b> is hidden.</li>
    <li>Connecting a sheet on the CardBoard start page also makes it yours.</li>
  </ul>
</section>

<section class="topic" id="screen">
  <h2><span class="n">5</span>The screen</h2>
  <?= shot('layout', 'The <b>Layout</b> view: elements on the left, the card in the middle, its settings on the right.') ?>
  <ul>
    <li><b>Tabs</b> at the top: <b>Cards</b>, <b>Tags</b>, <b>Layout</b>, <b>Print</b>, <b>Export</b>.</li>
    <li><b>Save</b> (top right) sends your changes to the sheet — see <a href="cardboard/help#saving">Saving</a>. The round person button opens the <a href="cardboard/help#settings">menu</a>.</li>
    <li><b>Left panel</b> — the <b>Deck</b> list, then everything you can put on a card: Text, Card fields, Image, Shapes and Generated values. Fold a section by clicking its heading.</li>
    <li><b>Middle</b> — the card, with the card navigator, zoom (<b>−</b>, <b>+</b>, <b>Fit</b>) and undo/redo above it. The round button at its top left flips to the <a href="cardboard/help#backs">back</a>.</li>
    <li><b>Right panel</b> — the settings of whatever is selected; with nothing selected, the card's size, background, corners and border. The layers button at its top opens <a href="cardboard/help#layers">Layers</a>.</li>
  </ul>
</section>

<hr class="sep">

<!-- ───────────────────────── Your cards ───────────────────────── -->
<section class="topic" id="decks">
  <h2><span class="n">6</span>Decks</h2>
  <div class="two narrow-left">
    <?= shot('deck-combo', 'The Deck list: type to filter, or add a new deck.') ?>
    <div>
      <p>The <b>Deck</b> box (top of the left panel, and in the Cards, Print and Export views) switches decks. Type to filter, then pick one. Decks that already have a layout show a small card icon, and the number beside the name is how many cards it has.</p>
      <p><b>New deck…</b> at the bottom of the list makes a new tab. Type its name and press <kbd>Enter</kbd> — it's kept in this browser and added to your spreadsheet with your next Save.</p>
      <p>Each deck has its own layout, print and export settings.</p>
    </div>
  </div>
</section>

<section class="topic" id="cards">
  <h2><span class="n">7</span>The Cards view</h2>
  <p class="lead">The deck as a grid — one row per card — so you can write and fix cards without leaving CardBoard.</p>
  <?= shot('cards', 'Picture links show a thumbnail; tags like <code>[mana]</code> show their icon; formulas show their results.') ?>
  <h3>Editing cells</h3>
  <ul>
    <li>Click a cell and start typing to replace it, or double-click (or <kbd>Enter</kbd> / <kbd>F2</kbd>) to edit what's there.</li>
    <li><kbd>Enter</kbd> keeps it and moves down; <kbd>Shift</kbd>+<kbd>Enter</kbd> adds a new line; <kbd>Tab</kbd> moves right; <kbd>Esc</kbd> cancels. Arrow keys move around, <kbd>Delete</kbd> clears.</li>
    <li><b>Formulas</b> show their result, as Sheets does; editing the cell shows the formula.</li>
    <li>A field shown through a <a href="cardboard/help#mapping">mapping</a> shows what you typed and what the card shows: <em>Fire → 🔥</em>.</li>
    <li><b>↶ ↷</b> undo and redo your edits here (<kbd>⌘Z</kbd>, <kbd>⇧⌘Z</kbd>). <b>Reload</b> reads this deck from the sheet again.</li>
  </ul>
  <h3>Find and replace</h3>
  <?= shot('find', 'Find marks every match; <b>Replace with</b> slides out beside it.') ?>
  <ul>
    <li><b>Find</b> shows only the cards containing what you type (upper or lower case), with every match marked in yellow. The <b>×</b> in the box (or <kbd>Esc</kbd>) clears it.</li>
    <li>Once there's something to find, <b>Replace with</b> slides out to the right. Type the new text and click <b>Replace N</b> (or press <kbd>Enter</kbd>) — the button counts the cells it will change.</li>
    <li>Afterwards Find looks for the new text, so the changed cells stay in view and marked. Cells holding a formula are left alone.</li>
    <li>Replacements are kept in this browser until you save, like any edit, and one <kbd>⌘Z</kbd> undoes the whole replace.</li>
  </ul>
  <h3>Rows and columns</h3>
  <div class="two">
    <?= shot('cards-colmenu', 'Click a heading: Rename, Hide field or Delete column.') ?>
    <?= shot('cards-rowmenu', 'Click a card number: a preview of that card, and Delete card.') ?>
  </div>
  <ul>
    <li><b>New card:</b> type in the empty row at the bottom — it becomes a card, with a fresh empty row below. <b>New column:</b> type a name in the empty heading at the end, or just type in one of its cells.</li>
    <li><b>Move a card:</b> drag its number up or down and drop it where the line shows. The card order changes everywhere — Layout, Print and Export — and the row moves in your sheet when you save (formulas move with it). <kbd>⌘Z</kbd> undoes it.</li>
    <li><b>Move a column:</b> drag its heading and drop it where the line shows. <b>Resize:</b> drag a heading's right edge (double-click the edge to fit). This order is also the order of the fields in the Layout view's Card list — your sheet's columns don't move.</li>
    <li><b>Rename</b> changes the heading in your sheet, and the layout follows: Card fields, <code>{Column}</code> in text, mappings and hidden fields all switch to the new name.</li>
    <li><b>Hide field</b> tucks a column away here and in the Card list; it stays in the sheet and on any card that uses it. <b>Show all</b> brings hidden ones back.</li>
    <li><b>Delete</b> a card or column: it disappears straight away and is deleted from the sheet when you save.</li>
  </ul>
  <?= steps([['move-1', 'Press on a card’s number…'], ['move-2', '…drag it up or down; the line shows where it will go.'], ['move-3', 'Let go. The card moves here, in Layout, Print and Export — and in your sheet when you save.']], 'Move a card') ?>
  <div class="note"><b>Someone else edited the sheet?</b> If a cell changed in Google Sheets after you loaded it, CardBoard won't overwrite it: the cell turns red and shows their value.</div>
</section>

<section class="topic" id="addcard">
  <h2><span class="n">8</span>Stepping through &amp; adding cards</h2>
  <p>Above the card in the Layout view: <b>first</b>, <b>previous</b>, the card number, <b>next</b> and <b>last</b> — check your design on every card. Click the card number to type another and press <kbd>Enter</kbd> to go straight to it. The dashed <b>+</b> adds a card right after the one you're on.</p>
  <?= steps([['cardno-1', 'Click the card number and type the card you want.'], ['cardno-2', 'Press <kbd>Enter</kbd> — there it is. (<kbd>Esc</kbd> stays put.)']], 'Go to a card by number') ?>
  <?= steps([['card-nav', 'Click <b>+</b> beside the card navigator.'], ['card-added', 'A blank card appears after this one. Double-click a field on the card to fill it in.']], 'Add a card from the Layout view') ?>
  <p>New cards are kept in this browser until you save; they take their place in the sheet right below the card you added them after.</p>
</section>

<hr class="sep">

<!-- ───────────────────────── Designing ───────────────────────── -->
<section class="topic" id="elements">
  <h2><span class="n">9</span>Adding elements</h2>
  <p class="lead">Drag anything from the left panel onto the card. You can also drop a picture file straight onto the card.</p>
  <?= steps([['add-field-1', 'Find the field in the <b>Card</b> list — it shows this card’s value.'], ['add-field-mid', 'Drag it onto the card.'], ['add-field-2', 'Drop it. It’s selected, ready to style in the right panel.']], 'Put a field on the card') ?>
  <div class="cards">
    <div class="card"><b>Text</b><p>Fixed wording, like a title or rules. Put a field name in braces — <code>{Cost}</code> — to fill it in from each card: <em>“Costs {Cost} gold”</em>.</p></div>
    <div class="card"><b>Card</b><p>One field of your deck (Name, Cost, Art…) — shown as text, a picture, or through a mapping.</p></div>
    <div class="card"><b>Image</b><p>The same picture on every card: a logo, a frame, an icon.</p></div>
    <div class="card"><b>Shape</b><p>Rectangle, rounded, circle, line, triangle, diamond, hexagon or star, with fill and outline.</p></div>
    <div class="card"><b>Generated</b><p>A number sequence, a random number, or a pick from a list.</p></div>
  </div>
</section>

<section class="topic" id="fields">
  <h2><span class="n">10</span>Card fields</h2>
  <div class="two">
    <?= shot('text-selected', 'A Card field selected: its column, how it shows, this card’s value and its style.') ?>
    <div>
      <ul>
        <li><b>Column</b> — which field it shows.</li>
        <li><b>Show as</b> — <b>Text</b>, <b>Image</b> (for a field of picture links) or <b>Mapping</b> (see <a href="cardboard/help#mapping">Mappings</a>).</li>
        <li><b>Value for row N</b> — this card's value, exactly as typed in the sheet (tags still in brackets). Edit it here, or double-click the field on the card to edit it in place: <kbd>Enter</kbd> keeps it, <kbd>Shift</kbd>+<kbd>Enter</kbd> adds a line, <kbd>Esc</kbd> cancels. It goes to the sheet with your next save.</li>
      </ul>
      <h3>The Card list</h3>
      <ul>
        <li>Each field shows its value for the card you're on.</li>
        <li><b>Drag a field up or down</b> to reorder the list (the Cards view's columns follow).</li>
        <li><b>+ New</b> adds a field, ready to drag onto the card; it's added to the sheet when you save.</li>
        <li>Click a field → <b>Hide field</b> to tuck away fields you don't use. The eye button in the Card heading shows them again.</li>
      </ul>
    </div>
  </div>
  <?= shot('field-menu', 'Click a field in the Card list to hide it.', 'small') ?>
</section>

<section class="topic" id="text">
  <h2><span class="n">11</span>Text style</h2>
  <p>Text, Card fields shown as text, and Generated values share these settings in the right panel:</p>
  <ul>
    <li><b>Font</b> — DIN Black, DIN Medium, Bangers, Cinzel, Lilita One, Merriweather, Roboto Condensed, and standard fonts.</li>
    <li><b>Size</b> (pt), <b>Line height</b> and <b>Color</b> (recent colors are remembered).</li>
    <li><b>Style</b> — <b>B</b> bold, <b>I</b> italic, <b>AA</b> all capitals. <b>Align</b> left, centre or right; <b>Vertical</b> top, middle or bottom.</li>
    <li><b>Background</b> — a fill, border, corner radius and padding around the text: handy for badges and keyword chips.</li>
  </ul>
  <p>Text wraps to fit its box. Make the box taller, or the font smaller, if words get cut off.</p>
</section>

<section class="topic" id="pictures">
  <h2><span class="n">12</span>Pictures</h2>
  <div class="two">
    <?= shot('picture', 'A picture field: each card has its own picture, saved in its Art cell.') ?>
    <div>
      <h3>Adding a picture</h3>
      <ul>
        <li>Click the picture box in the right panel (or double-click the picture on the card) to <b>upload</b> a file or paste a <b>link</b>. You can also drop a file or link onto it.</li>
        <li><b>Dropbox</b> and <b>Google Drive</b> links show straight from the source, so changing the file there changes your cards. In Drive, share the file as “Anyone with the link”.</li>
        <li><b>Fit</b> — <b>Fill</b> covers the box (cropping the edges), <b>Fit</b> shows the whole picture, <b>Stretch</b> fills it exactly. <b>Frame</b> adds a fill, border and rounded corners.</li>
      </ul>
      <h3>Pictures from your sheet</h3>
      <p>A field of picture links shows each card's own picture. Drop or choose a new one in the right panel and it's saved into that card's cell.</p>
    </div>
  </div>
</section>

<section class="topic" id="mapping">
  <h2><span class="n">13</span>Mappings</h2>
  <p class="lead">A mapping shows something different for each value of a field: a flame for <em>Fire</em> cards, “❄ FROST” for <em>Ice</em> cards. Each value can become text <em>or</em> a picture, so one element can be a word on one card and an icon on the next.</p>
  <?= steps([['mapping-0', 'Select a Card field and choose <b>Show as → Mapping</b>.'], ['mapping', 'Add a row for each value: type the text it becomes, or click the picture button to use a picture or a tag. <em>Fire</em> becomes a flame.'], ['mapping-2', 'Step to the next card: <em>Ice</em> becomes the text “❄ FROST”.']], 'Map a field’s values to text and pictures') ?>
  <ul>
    <li>The list suggests the values already in your sheet; a new empty row appears as you fill each one. The row matching the current card is highlighted.</li>
    <li>A value with no row shows as it is (or as its picture, if it's a picture link or a tag).</li>
    <li>Results can be <a href="cardboard/help#tags">tags</a> like <code>[fire]</code>, so changing the tag changes every card.</li>
    <li>Double-click the element on the card to change that card's value.</li>
    <li><b>Mappings are remembered for the field</b>, even if you delete the element or set it back to Text: choose <b>Mapping</b> again on any element showing that field and they come back. The Cards view keeps showing <em>value → result</em> for the field either way.</li>
  </ul>
</section>

<section class="topic" id="generated">
  <h2><span class="n">14</span>Generated values</h2>
  <div class="two">
    <?= shot('generated', 'A number sequence numbering the cards #01, #02…') ?>
    <div>
      <ul>
        <li><b>123 Number sequence</b> — counts across your cards. <b>Start at</b>, <b>Step</b>, <b>Digits</b> (pad with zeros) and text <b>Before</b> / <b>After</b>: <code>#01</code>, <code>#02</code>…</li>
        <li><b>Random number</b> — a number <b>From</b> … <b>To</b> for each card.</li>
        <li><b>Random from list</b> — type the values one per line; <b>Pick</b> them at random or in order.</li>
        <li><b>Counts</b> — <b>Each card printed</b> gives every printed copy its own value; <b>Each row</b> gives all copies of a card the same value.</li>
      </ul>
      <p>Values stay the same in the designer, the printout and exports. <b>Re-roll</b> draws new random values.</p>
    </div>
  </div>
</section>

<section class="topic" id="arrange">
  <h2><span class="n">15</span>Moving &amp; lining up</h2>
  <ul>
    <li><b>Select</b> by clicking; <kbd>Shift</kbd> or <kbd>⌘</kbd>-click adds to the selection; <kbd>⌘A</kbd> selects everything; <kbd>Esc</kbd> selects nothing.</li>
    <li><b>Move</b> by dragging, or nudge with the arrow keys (1 pt; with <kbd>Shift</kbd>, 10 pt). Hold <kbd>Alt</kbd> while dragging for fine moves.</li>
    <li><b>Resize</b> with the square handles (<kbd>Shift</kbd> on a corner keeps a shape's proportions). <b>Rotate</b> with the round handle above (<kbd>Shift</kbd> snaps to 15°).</li>
    <li><b>Full width / height:</b> the round ↔ button on the card's top edge and ↕ on its left edge stretch the selection across the card.</li>
    <li><b>Exact numbers:</b> X, Y, W, H in the right panel, in points (72 pt = 1 inch). <b>Opacity</b> fades an element.</li>
  </ul>
  <h3>Lining up</h3>
  <p>With something selected, round dots appear past the card's right and bottom edges. With <b>one</b> element (or group) selected they line it up with the card — top, middle, bottom, left, centre, right. With <b>several</b>, they line them up with each other.</p>
  <?= shot('align', 'Three elements selected: the dots line them up with each other.') ?>
  <h3>Lining up to a key object</h3>
  <p>To line things up with one particular element — without moving it — make it the <b>key object</b>:</p>
  <?= steps([['align', 'Select the elements, including the one to line up to.'], ['key', 'Click that one again, without dragging. It gets a solid outline and the dots move around it.'], ['key-dot', 'Click a dot — here, left edges.'], ['key-done', 'The others line up with the key object; the key stays put.']], 'Line up to a key object') ?>
  <ul>
    <li>Click a different element in the selection to change the key; click the key again to go back to lining them up with each other.</li>
    <li>Dragging still moves everything selected together — only a click sets the key.</li>
  </ul>
</section>

<section class="topic" id="layers">
  <h2><span class="n">16</span>Layers, groups &amp; lists</h2>
  <div class="two">
    <?= shot('layers', 'The Layers list: front-most first. Drag rows to restack.') ?>
    <div>
      <h3>Layers</h3>
      <p>The layers button at the top of the right panel lists everything on this side of the card. Click a row to select it, tick boxes (or <kbd>Shift</kbd>/<kbd>⌘</kbd>-click) to select several, and drag rows to change what's in front.</p>
      <h3>Groups</h3>
      <ul>
        <li>Select several elements and press <kbd>⌘G</kbd> (or <b>Group</b>). Clicking any member selects the whole group. <kbd>⇧⌘G</kbd> ungroups.</li>
        <li>Rename a group in its <b>Name</b> box or by double-clicking it in Layers; drag elements in or out of it there. Tick just one row in Layers to change one member.</li>
      </ul>
    </div>
  </div>
  <h3>Lists</h3>
  <p>With a group selected, <b>Arrange</b> sets how its members sit: <b>Free</b> (where you put them), a <b>horizontal list</b> (side by side) or a <b>vertical list</b> (one under another), with a <b>Gap</b> between them. In a list each text takes only the room its words need, and an empty text or picture takes no room — so keyword lines and icon rows close up on every card.</p>
  <?= steps([['list', 'Three keyword chips grouped as a horizontal list: each is only as wide as its words.'], ['list-hold', 'Press and hold a list button to line the members up — top, middle or bottom (left, centre or right for a vertical list). Slide to one and let go.']], 'Make a keyword row') ?>
</section>

<section class="topic" id="backs">
  <h2><span class="n">17</span>Card backs</h2>
  <div class="two">
    <?= shot('back', 'The back: its own background and elements.') ?>
    <div>
      <p>Click the round flip button at the top left of the card to design the back. It works just like the front, with its own background; the size, corners and border are shared by both sides.</p>
      <ul>
        <li>If nothing on the back changes from card to card, every card shares one back.</li>
        <li>Add a Card field, a <code>{Field}</code> in text, a mapping or a Generated value, and each card gets its own back.</li>
      </ul>
    </div>
  </div>
</section>

<section class="topic" id="tags">
  <h2><span class="n">18</span>Tags</h2>
  <p class="lead">Write a word in square brackets — <code>[mana]</code> — in any text or cell, and every card shows a picture or text in its place: <em>“Gain 1 [mana]”</em> becomes “Gain 1 💎”.</p>
  <?= shot('tags', 'The Tags view: each tag becomes an icon, a picture or text. The preview shows how it looks.') ?>
  <ol>
    <li>Open <b>Tags</b> and click <b>+ Add tag</b>, then give it a name (with or without brackets).</li>
    <li>Choose <b>Text</b> to replace it with words, or <b>Image</b> for a picture: <b>Choose</b> an icon from the library (with a color), upload one, or paste a link.</li>
  </ol>
  <p>Tags live in your spreadsheet's <b>tags</b> tab, so you can edit them there too and click <b>Reload</b>. Curly braces stay for fields: <em>“Gain {Amount} [mana]”</em> uses both.</p>
</section>

<hr class="sep">

<!-- ───────────────────────── Printing & sharing ───────────────────────── -->
<section class="topic" id="print">
  <h2><span class="n">19</span>Print</h2>
  <?= shot('print', 'Copies from the Count field: 16 cards, 9 per page, with crop marks.') ?>
  <ul>
    <li><b>Paper</b> Letter or A4, <b>Orientation</b>, and the <b>Gap</b> between cards (none, 1/16 in, 1/8 in).</li>
    <li><b>Cut lines</b> — crop marks, card outlines, or none.</li>
    <li><b>Copies</b> — one of each card, or the number in a field such as <b>Count</b> or <b>Qty</b> (picked automatically when there is one).</li>
    <li><b>Backs</b> — fronts only, or double-sided flipping on the long or short edge to match your printer; each page of backs lines up behind its fronts.</li>
  </ul>
  <p>Click <b>Print</b> and choose your printer or <b>Save as PDF</b>. Print at 100% (no “fit to page”) so cards come out the right size.</p>
</section>

<section class="topic" id="export">
  <h2><span class="n">20</span>Export &amp; links</h2>
  <?= shot('export', 'A Tabletop Simulator sheet: 10 × 7 cards at most, with the back in the last slot.') ?>
  <ul>
    <li><b>For</b> — <b>Tabletop Simulator</b>, <b>Screentop</b>, or <b>Separate images</b> (one picture per card). <b>Size</b> — Standard (up to 4096 px), Large or Small. <b>Copies</b> as in Print.</li>
    <li><b>Download</b> (the arrow button) makes a zip of the images. The panel explains exactly what to enter in Tabletop Simulator's <b>Custom Deck</b> dialog.</li>
    <li><b>Get links</b> hosts the sheets at links that never change — paste them into Tabletop Simulator. After that the button reads <b>Update links</b>: the pictures behind the links are replaced with your latest cards (this also happens by itself after you save). The links carry a share code, not your spreadsheet's ID.</li>
  </ul>
  <div class="note"><b>Tabletop Simulator</b> keeps its own copy of downloaded pictures, so a game may show old cards until it loads them again. If you add cards, raise <b>Number</b> in the Custom Deck settings to match.</div>
</section>

<hr class="sep">

<!-- ───────────────────────── Saving ───────────────────────── -->
<section class="topic" id="saving">
  <h2><span class="n">21</span>Saving &amp; working offline</h2>
  <p class="lead">Everything you change — layouts, cells, new cards, new fields and decks, column order and widths, settings — is kept in this browser as you work, and sent to your Google Sheet when you save.</p>
  <?= steps([['save-pending', '<b>Save</b> turns yellow when changes are waiting.'], ['save-saving', 'Saving runs in the background: the button fills as each part is saved, and you can keep working.']], 'Save') ?>
  <ul>
    <li><b>Save</b> (or <kbd>⌘S</kbd>) sends everything at once.</li>
    <li>CardBoard <b>saves by itself</b> as rarely as it can: your changes pile up in this browser and go to the sheet together once you've stopped for a minute and a half (never while you're typing). Changes that have waited 15 minutes go at a shorter pause, and it also saves when this browser's storage starts to fill up. Closing the page or switching apps doesn't lose anything — the changes stay here until they're saved.</li>
    <li>Until it's saved, a change is only in this browser — other people, exports and hosted links see the last saved version.</li>
    <li><b>Fetch</b> (menu) reads everything from the sheet again; with unsaved changes it asks whether to save first.</li>
  </ul>
  <h3>Offline</h3>
  <p>CardBoard keeps the page, its fonts, your decks and the card pictures in this browser, so it opens and works without a connection. Keep designing — your changes are saved once you're back online and take a break.</p>
  <div class="note"><b>One browser:</b> unsaved changes don't follow you to another computer, and clearing the browser's site data (or closing a private window) removes them. Save before you switch.</div>
</section>

<section class="topic" id="undo">
  <h2><span class="n">22</span>Undo, copy &amp; paste</h2>
  <ul>
    <li><b>Undo / Redo</b> — the curved arrows above the card (and above the Cards grid), or <kbd>⌘Z</kbd> and <kbd>⇧⌘Z</kbd>.</li>
    <li><b>Copy, Cut, Paste</b> and <b>Duplicate</b> — the buttons under <b>Actions</b> in the right panel, or <kbd>⌘C</kbd>, <kbd>⌘X</kbd>, <kbd>⌘V</kbd>, <kbd>⌘D</kbd>. Paste works between the front and back, between decks, between spreadsheets — even in another CardBoard window. Groups and their list settings come along.</li>
    <li>If a pasted Card field uses a field this deck doesn't have, CardBoard says so, so you can pick another.</li>
    <li><b>To front / To back</b> restack the selection; <b>Delete</b> (or <kbd>Delete</kbd>) removes it.</li>
  </ul>
</section>

<hr class="sep">

<!-- ───────────────────────── Account ───────────────────────── -->
<section class="topic" id="plans">
  <h2><span class="n">23</span>Plans &amp; codes</h2>
  <div class="two">
    <?= shot('plans', 'Menu → Subscription: your plan, and how to keep going.') ?>
    <div>
      <div class="cards" style="grid-template-columns:1fr;margin-top:0">
        <div class="card"><b>Free week</b><p>Every feature for 7 days from sign-up. No card needed.</p></div>
        <div class="card"><b>CardBoard — $4.99/month</b><p>Keep designing after the free week. Cancel any time from <b>Manage billing</b>.</p></div>
        <div class="card"><b>All-in-one — $9.99/month</b><p>CardBoard, DevBoard, PitchBoard and BillBoard together, from the GameBoard home page.</p></div>
        <div class="card"><b>Codes</b><p><b>Have a code?</b> in the plans (or menu → <b>Enter a code</b>). A code unlocks time — a week, a month, a year or for good — for every app, or just the app it was made for. Each works once per account.</p></div>
      </div>
    </div>
  </div>
  <?= shot('locked', 'After the free week CardBoard is view only until you subscribe — your sheet, cards and layouts stay exactly as they are.') ?>
</section>

<section class="topic" id="settings">
  <h2><span class="n">24</span>Settings &amp; menu</h2>
  <div class="two">
    <?= shot('menu', 'The menu, from the round person button.') ?>
    <?= shot('settings', 'Settings: the spreadsheet, measurements and your plan.') ?>
  </div>
  <ul>
    <li><b>Profile</b> — your name and studio. <b>Settings</b> — open the spreadsheet in Google Sheets, choose inches or millimetres, see your plan.</li>
    <li><b>Fetch</b> — read the latest from the sheet, including tabs you've added, renamed or removed in Sheets.</li>
    <li><b>Subscription</b> / <b>Enter a code</b> — see <a href="cardboard/help#plans">Plans</a>. <b>Help</b> — this page. <b>Sign out</b> — of this browser.</li>
  </ul>
</section>

<section class="topic" id="keys">
  <h2><span class="n">25</span>Keyboard shortcuts</h2>
  <p><kbd>⌘</kbd> is <kbd>Ctrl</kbd> on Windows.</p>
  <table class="keys">
    <tr class="h"><td colspan="2">Everywhere</td></tr>
    <tr><td><kbd>⌘S</kbd></td><td>Save everything to the sheet</td></tr>
    <tr><td><kbd>⌘Z</kbd> / <kbd>⇧⌘Z</kbd></td><td>Undo / redo</td></tr>
    <tr class="h"><td colspan="2">Layout</td></tr>
    <tr><td><kbd>⌘C</kbd> <kbd>⌘X</kbd> <kbd>⌘V</kbd></td><td>Copy / cut / paste elements — into any side, deck or spreadsheet</td></tr>
    <tr><td><kbd>⌘D</kbd></td><td>Duplicate</td></tr>
    <tr><td><kbd>⌘G</kbd> / <kbd>⇧⌘G</kbd></td><td>Group / ungroup</td></tr>
    <tr><td><kbd>⌘A</kbd></td><td>Select everything on this side</td></tr>
    <tr><td><kbd>Shift</kbd> or <kbd>⌘</kbd>-click</td><td>Add to (or take from) the selection</td></tr>
    <tr><td>Click inside a selection</td><td>Make that element the key object (click again to clear)</td></tr>
    <tr><td>Arrow keys</td><td>Nudge 1 pt — with <kbd>Shift</kbd>, 10 pt</td></tr>
    <tr><td><kbd>Alt</kbd> while dragging</td><td>Fine moves</td></tr>
    <tr><td><kbd>Shift</kbd> on a corner / while rotating</td><td>Keep proportions / snap to 15°</td></tr>
    <tr><td><kbd>Delete</kbd> · <kbd>Esc</kbd></td><td>Delete the selection · select nothing, or cancel an edit</td></tr>
    <tr><td>Double-click</td><td>Edit text or a field on the card · choose a picture · rename a group in Layers</td></tr>
    <tr><td>Click the card number</td><td>Type a card number and press <kbd>Enter</kbd> to go to it</td></tr>
    <tr class="h"><td colspan="2">Cards view</td></tr>
    <tr><td>Drag a card's number</td><td>Move the card up or down (the row moves in the sheet on Save)</td></tr>
    <tr><td><kbd>Enter</kbd> / <kbd>F2</kbd></td><td>Edit the cell · <kbd>Enter</kbd> again keeps it and moves down</td></tr>
    <tr><td><kbd>Shift</kbd>+<kbd>Enter</kbd> · <kbd>Tab</kbd> · <kbd>Esc</kbd></td><td>New line · keep and move right · cancel</td></tr>
  </table>
</section>

<section class="topic" id="trouble">
  <h2><span class="n">26</span>Troubleshooting</h2>
  <ul>
    <li><b>“Couldn't open the sheet”</b> — check it's shared with <code>editor@zapsheets-480701.iam.gserviceaccount.com</code> as an <b>Editor</b>.</li>
    <li><b>Changes made in Google Sheets don't show</b> — menu → <b>Fetch</b>.</li>
    <li><b>It says View only</b> — another account set this sheet up, or nobody has (<b>Make it mine</b>), or your free week has ended (<a href="cardboard/help#plans">Plans</a>).</li>
    <li><b>A picture says “Picture didn't load”</b> — open the link in a private window. Drive files must be shared as “Anyone with the link”; Dropbox links must be shared links.</li>
    <li><b>Text is cut off</b> — make the box bigger, the font smaller, or the padding less.</li>
    <li><b>Printed cards are the wrong size</b> — print at 100% scale, with “fit to page” off.</li>
    <li><b>No code email</b> — check spam, and wait a minute before asking for another.</li>
    <li><b>A cell turned red</b> — someone changed it in Google Sheets since you loaded it; your edit wasn't written. Fetch, then edit again.</li>
  </ul>
</section>

<p class="foot">© <?= date('Y') ?> ZapSheets · <a href="cardboard">CardBoard</a> · <a href="cardboard/features">Features</a> · <a href="gameboard">GameBoard</a></p>
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
