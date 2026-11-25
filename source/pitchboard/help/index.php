<?php
$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_base = preg_replace('#/pitchboard/help/?$#', '/', $_raw);
if (!$_base || $_base === $_raw) { $_base = '/'; }
if (substr($_base, -1) !== '/') { $_base .= '/'; }
$IMG = 'images/help/pitchboard/';
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
<title>PitchBoard – Help</title>
<meta name="description" content="Track your board game pitches to publishers — every game, publisher and follow-up, from your Google Sheet.">
<link rel="icon" type="image/png" sizes="32x32" href="images/pitchboard_icon_32.png" />
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2')  format('woff2'),url('fonts/DINBlack.ttf')  format('truetype'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf') format('truetype'); }
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root { --navy:#4a1e1a; --ink:#1b1d2b; --violet:#d9574a; --lav:#16a34a; --gold:#ef4444; --bg:#f7f3f2; --line:#efdfdc; --mid:#4a5068; --muted:#8a90a6; --side:280px; }
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
.find { width:100%; padding:.55rem .75rem; border:1.5px solid var(--line); border-radius:9px; font:inherit; font-size:.88rem; margin-bottom:.9rem; background:#fffafa; }
.find:focus { outline:none; border-color:var(--violet); box-shadow:0 0 0 3px #fdeceb; background:#fff; }
.side h4 { font-family:'DINBlack',sans-serif; font-weight:400; font-size:.64rem; letter-spacing:.12em; text-transform:uppercase; color:var(--muted); margin:1.1rem .5rem .35rem; }
.side h4:first-of-type { margin-top:.2rem; }
.side a { display:flex; align-items:center; gap:.55rem; padding:.36rem .55rem; border-radius:8px; color:var(--mid); text-decoration:none; font-size:.9rem; line-height:1.3; }
.side a .n { font-family:'DINBlack',sans-serif; font-size:.62rem; color:#b4b8c9; width:1.3rem; text-align:right; flex-shrink:0; }
.side a:hover { background:#fef3f2; color:var(--navy); }
.side a.on { background:#fdeceb; color:var(--navy); font-family:'DINBlack',sans-serif; font-size:.86rem; }
.side a.on .n { color:var(--violet); }
.side a.hide, .side h4.hide { display:none; }
.main { padding:2rem clamp(1.2rem,4vw,3.5rem) 6rem; min-width:0; }
.hero { background:linear-gradient(135deg,#1c1a2b 0%,#4a1e1a 70%,#bc4437 100%); color:#fff; border-radius:18px; padding:2.2rem 2.2rem 2.2rem; display:grid; grid-template-columns:1fr 300px; gap:2rem; align-items:center; overflow:hidden; }
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
.topic > h2 .n { font-size:.8rem; color:var(--violet); background:#fdeceb; border-radius:6px; padding:.05rem .45rem; }
.topic > .lead { font-size:1.02rem; color:var(--mid); margin-bottom:1rem; }
.topic h3 { font-family:'DINBlack',sans-serif; font-weight:400; font-size:.8rem; letter-spacing:.09em; text-transform:uppercase; color:var(--violet); margin:1.5rem 0 .4rem; }
.topic p, .topic li { font-size:.95rem; color:#3c4052; }
.topic p + p { margin-top:.6rem; }
.topic ul, .topic ol { padding-left:1.3rem; margin-top:.4rem; }
.topic li { margin-bottom:.35rem; }
.topic li::marker { color:var(--violet); }
.sep { border:none; border-top:1px solid var(--line); margin:2.6rem 0 0; max-width:900px; }
kbd, code { font-family:ui-monospace,Menlo,monospace; font-size:.8em; background:#fdeceb; color:#bc4437; border-radius:4px; padding:.05rem .32rem; }
.btn-like { display:inline-block; font-family:'DINBlack',sans-serif; font-size:.68rem; letter-spacing:.06em; text-transform:uppercase; border:1.5px solid #f2c6c0; border-radius:6px; padding:0 .4rem; color:var(--navy); background:#fff; white-space:nowrap; }
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
table.keys td { padding:.5rem .9rem; border-bottom:1px solid #efdfdc; color:#3c4052; vertical-align:top; }
table.keys tr:last-child td { border-bottom:none; }
table.keys td:first-child { white-space:nowrap; width:1%; }
table.keys tr.h td { background:#fffafa; font-family:'DINBlack',sans-serif; font-size:.68rem; letter-spacing:.1em; text-transform:uppercase; color:var(--muted); }
/* ── Screenshots ── */
.shot { margin:1rem 0 0; background:#fff; border:1px solid var(--line); border-radius:12px; overflow:hidden; box-shadow:0 6px 22px rgba(74,30,26,.08); }
.shot img { display:block; width:100%; height:auto; cursor:zoom-in; }
.shot figcaption { padding:.5rem .85rem .6rem; font-size:.8rem; color:var(--muted); border-top:1px solid #efdfdc; }
.shot.small { max-width:360px; }
.shot.mid { max-width:560px; }
.two .shot { margin-top:0; }
/* ── Animated steps ── */
.steps { margin-top:1.1rem; background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; box-shadow:0 6px 22px rgba(74,30,26,.08); }
.steps-head { display:flex; align-items:center; gap:.6rem; padding:.6rem .9rem; border-bottom:1px solid #efdfdc; }
.steps-head b { font-family:'DINBlack',sans-serif; font-weight:400; font-size:.88rem; color:var(--navy); flex:1; }
.steps-tag { font-family:'DINBlack',sans-serif; font-size:.6rem; letter-spacing:.1em; text-transform:uppercase; color:#fff; background:#e8623a; border-radius:5px; padding:.12rem .45rem; }
.steps-play { border:1.5px solid var(--line); background:#fff; border-radius:7px; width:2rem; height:1.7rem; font-size:.62rem; color:var(--navy); cursor:pointer; }
.steps-stage { position:relative; background:#eef0f6; }
.steps-stage img { display:block; width:100%; height:auto; opacity:0; transition:opacity .45s ease; position:absolute; inset:0; cursor:zoom-in; }
.steps-stage img.on { opacity:1; position:relative; }
.steps-stage img.on ~ img.on { position:absolute; }
.steps-bar { position:absolute; left:0; right:0; bottom:0; height:3px; background:rgba(0,0,0,.06); }
.steps-bar i { display:block; height:100%; width:0; background:#e8623a; }
.steps-list { list-style:none; padding:.4rem !important; margin:0 !important; display:grid; grid-auto-flow:column; grid-auto-columns:1fr; gap:.3rem; counter-reset:st; border-top:1px solid #efdfdc; }
.steps-list li { margin:0 !important; }
.steps-list button { width:100%; height:100%; text-align:left; border:none; background:none; border-radius:9px; padding:.5rem .6rem .5rem 2.1rem; font:inherit; font-size:.82rem; line-height:1.35; color:var(--muted); cursor:pointer; position:relative; }
.steps-list li { counter-increment:st; }
.steps-list button::before { content:counter(st); position:absolute; left:.55rem; top:.5rem; width:1.15rem; height:1.15rem; border-radius:50%; background:#efdfdc; color:var(--mid); font-family:'DINBlack',sans-serif; font-size:.62rem; display:flex; align-items:center; justify-content:center; }
.steps-list li.on button { background:#fef3f2; color:var(--ink); }
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
  <a class="top-home" href="dashboard" title="Your sheets"><img src="images/pitchboard_icon_180.png" alt=""><span class="top-name"><span class="brand"><span class="c">Pitch</span><span class="b">Board</span></span><span class="top-lbl">Help</span></span></a>
  <span class="top-sp"></span>
  <a class="open" href="dashboard">Open PitchBoard</a>
</div></header>

<div class="wrap">
<nav class="side" id="side" aria-label="Help topics">
  <input class="find" id="find" type="search" placeholder="Find a topic…" aria-label="Find a topic">
  <h4>Getting started</h4>
  <a href="pitchboard/help#welcome"><span class="n">1</span>What PitchBoard does</a>
  <a href="pitchboard/help#sheet"><span class="n">2</span>Set up your sheet</a>
  <a href="pitchboard/help#account"><span class="n">3</span>Sign in</a>
  <a href="pitchboard/help#owner"><span class="n">4</span>Your sheets &amp; view only</a>
  <a href="pitchboard/help#screen"><span class="n">5</span>The screen</a>
  <h4>Your games</h4>
  <a href="pitchboard/help#board"><span class="n">6</span>The Board</a>
  <a href="pitchboard/help#games"><span class="n">7</span>The Games list</a>
  <a href="pitchboard/help#editgame"><span class="n">8</span>Adding &amp; editing games</a>
  <a href="pitchboard/help#stages"><span class="n">9</span>Stages &amp; shelving</a>
  <h4>Pitches</h4>
  <a href="pitchboard/help#pitch"><span class="n">10</span>Logging a pitch</a>
  <a href="pitchboard/help#statuses"><span class="n">11</span>Pitch statuses</a>
  <a href="pitchboard/help#entry"><span class="n">12</span>Editing a pitch</a>
  <a href="pitchboard/help#followup"><span class="n">13</span>Follow-ups</a>
  <h4>Publishers &amp; people</h4>
  <a href="pitchboard/help#publishers"><span class="n">14</span>The Publishers list</a>
  <a href="pitchboard/help#people"><span class="n">15</span>Contacts &amp; designers</a>
  <h4>Sharing</h4>
  <a href="pitchboard/help#page"><span class="n">16</span>Game pages</a>
  <a href="pitchboard/help#share"><span class="n">17</span>Share links</a>
  <a href="pitchboard/help#notes"><span class="n">18</span>Playtester notes</a>
  <a href="pitchboard/help#import"><span class="n">19</span>Import &amp; slideshow</a>
  <h4>Saving</h4>
  <a href="pitchboard/help#saving"><span class="n">20</span>Saving &amp; Fetch</a>
  <h4>Account</h4>
  <a href="pitchboard/help#plans"><span class="n">21</span>Plans &amp; codes</a>
  <a href="pitchboard/help#settings"><span class="n">22</span>Settings &amp; menu</a>
  <a href="pitchboard/help#trouble"><span class="n">23</span>Troubleshooting</a>

</nav>

<main class="main" id="main">
<div class="hero">
  <div>
    <h1><span class="c">Pitch</span><span class="b">Board</span> Help</h1>
    <p>Keep every pitch in one place — which game went to which publisher, who you spoke to, what they said, and what to follow up — all in your own Google Sheet.</p>
    <div class="quick"><a href="pitchboard/help#sheet">Set up a sheet</a><a href="pitchboard/help#pitch">Log a pitch</a><a href="pitchboard/help#statuses">Statuses</a><a href="pitchboard/help#share">Share</a><a href="pitchboard/help#saving">Saving</a></div>
  </div>
  <img src="<?= $IMG ?>hero.jpg" alt="PitchBoard">
</div>

<section class="topic" id="welcome">
  <h2><span class="n">1</span>What PitchBoard does</h2>
  <p class="lead">Your Google Sheet holds the list: every <b>game</b> you’re pitching, every <b>publisher</b> and contact, and one row per <b>pitch</b> — a meeting, an email, a convention chat. PitchBoard turns it into a dashboard you can work from.</p>
  <div class="cards">
    <div class="card"><b>Overview</b><p>Totals, charts and every game by stage at a glance.</p></div>
    <div class="card"><b>Games</b><p>Each game with the publishers it’s been pitched to and where each one stands.</p></div>
    <div class="card"><b>Publishers</b><p>Each publisher with its contacts and the games they’ve seen.</p></div>
    <div class="card"><b>Game pages</b><p>A public page for a game — art, player count, how to play — to send with a pitch.</p></div>
    <div class="card"><b>Sharing</b><p>Links for a co-designer to log pitches too, and feedback forms for playtesters.</p></div>
  </div>
</section>
<section class="topic" id="sheet">
  <h2><span class="n">2</span>Set up your sheet</h2>
  <?= shot('newsheet', 'The PitchBoard start page: open one of your sheets or start a blank one, then paste its link.') ?>
  <ol>
    <li><b>Open or make a sheet.</b> <b>Open a sheet</b> goes to your Google Sheets; <b>Start a blank sheet</b> makes a new one. A blank sheet is all it takes — a DevBoard or BillBoard sheet works too, so all your apps can share one.</li>
    <li><b>Share it with PitchBoard.</b> In Sheets, click <b>Share</b> and add <code>editor@zapsheets-480701.iam.gserviceaccount.com</code> (the <b>Copy</b> button copies it) as an <b>Editor</b>.</li>
    <li><b>Connect it.</b> Paste the sheet’s link and click <b>Connect &amp; Open</b>. PitchBoard adds the tabs it needs and opens your dashboard — bookmark that page to come straight back.</li>
  </ol>
  <p>PitchBoard keeps its lists in these tabs: <b>games</b>, <b>pitches</b>, <b>people</b> and <b>settings</b> (and <b>bios</b> for profiles). Tabs that are already there are used as they are — your data isn’t touched — and the same tab names are used by every GameBoard app.</p>
  <div class="tip"><b>Your sheets:</b> every sheet you’ve connected is listed on your GameBoard <a href="dashboard">dashboard</a>, with an Open link for each app.</div>
</section>
<section class="topic" id="account">
  <h2><span class="n">3</span>Sign in</h2>
  <p class="lead">PitchBoard uses your <b>GameBoard account</b> — one email and password for PitchBoard, DevBoard, CardBoard and BillBoard.</p>
  <div class="two">
    <?= shot('signin', 'Start your free week, or log in.') ?>
    <?= shot('signup', 'New accounts: the checklist ticks off each password rule.') ?>
  </div>
  <ul>
    <li><b>Start your free week</b> — your email and a password (8 or more characters, with a letter and a number). We email you a 6-digit code; type it in and you’re in, with every feature for 7 days.</li>
    <li><b>Log in</b> — your email and password. You stay signed in on that browser, in every GameBoard app; signing out of one signs you out of them all.</li>
    <li><b>Forgot password?</b> — on the log-in screen. We email a code; enter it with your new password.</li>
  </ul>
</section>
<section class="topic" id="owner">
  <h2><span class="n">4</span>Your sheets &amp; view only</h2>
  <p class="lead">The account that connects a sheet owns it: only that account changes it. Anyone else who opens it can look, not change.</p>
  <?= shot('viewonly', 'A sheet nobody has set up yet opens view only, with <b>Make it mine</b>.') ?>
  <ul>
    <li>Connecting a sheet on the PitchBoard start page makes it yours straight away.</li>
    <li><b>Make it mine</b> appears on a sheet nobody has set up — click it to take it on. A sheet that belongs to another account just says <b>View only</b>.</li>
    <li>To let someone else log pitches, send them a <a href="pitchboard/help#share">share link</a> instead.</li>
  </ul>
</section>
<section class="topic" id="screen">
  <h2><span class="n">5</span>The screen</h2>
  <?= shot('games', 'The Games list: the status counts, search and sort, and a card for each game.') ?>
  <ul>
    <li><b>Tabs</b> at the top: <b>Overview</b> (totals and charts), <b>Games</b> and <b>Publishers</b>, with how many of each.</li>
    <li><b>Save</b> (top right) sends your changes to the sheet — see <a href="pitchboard/help#saving">Saving</a>. The round person button (your picture, once you’ve added one) opens the <a href="pitchboard/help#settings">menu</a>.</li>
    <li>Under the tabs: the <b>status counts</b>, the <b>search</b> box, <b>Date</b> / <b>A–Z</b> sort and <b>+ Game</b>.</li>
  </ul>
</section>

<hr class="sep">
<section class="topic" id="board">
  <h2><span class="n">6</span>The Board</h2>
  <p class="lead">A snapshot of everything you’re pitching.</p>
  <?= shot('board', 'The Board: totals along the top, then the charts.') ?>
  <ul>
    <li><b>The totals</b> — games, published, signed, in pitching, not pitched yet, and publishers.</li>
    <li><b>Games by status</b> — how many games are at each status. <b>Top publishers</b> — who’s seen the most of your games. <b>Top games</b> — which games you’ve pitched most. <b>Pitches over time</b> — month by month.</li>
    <li><b>Games by stage</b> — each game as a card in Design, Pitch, Signed or Published (see <a href="pitchboard/help#stages">Stages</a>).</li>
    <li><b>Analytics</b> — visits to your shared pages, and how much this sheet keeps on the server.</li>
  </ul>
</section>
<section class="topic" id="games">
  <h2><span class="n">7</span>The Games list</h2>
  <p class="lead">Every game as a card. Open one to see each publisher it’s been pitched to.</p>
  <?= shot('game', 'Harbor Lights, opened: its designers and buttons, the publishers it’s with, and its links.') ?>
  <ul>
    <li><b>The status counts</b> above the list (<em>4 Pitched</em>, <em>2 Interested</em>…) count each game–publisher pair by its latest pitch. Click one to show only those; click again to show everything.</li>
    <li><b>Search</b> finds games, publishers, contacts and notes. <b>Date</b> puts the most recent pitches first; <b>A–Z</b> sorts by name.</li>
    <li>The badge on a game is its latest status (<b>INT</b> is Interested). Open the game to see its <b>designers</b>, its buttons — <b>+ Pitch</b>, <b>Edit Game</b>, <b>Enable Page</b>, <b>Share</b>, <b>Enable Notes</b> — and one row per <b>publisher</b>.</li>
    <li>Open a publisher row to see each pitch: date, contact, event, status and notes. <b>View</b>, <b>+ Pitch</b> and <b>Email</b> on the row act on that publisher.</li>
    <li>Publishers that passed or went cold are tucked under <b>Show … passed / gone cold</b>.</li>
    <li>The yellow strip at the bottom has the game’s links — <b>Rules</b>, <b>Play</b>, <b>Print</b>, <b>Sellsheet</b>, <b>Video</b> — whichever you’ve filled in.</li>
    <li>The round arrow reloads this game’s rows from the sheet.</li>
  </ul>
</section>
<section class="topic" id="editgame">
  <h2><span class="n">8</span>Adding &amp; editing games</h2>
  <div class="two narrow-left">
    <?= shot('gameedit', 'Edit Game: name, tagline, designers, status, dates and links.') ?>
    <div>
      <p><b>+ Game</b> adds a game; <b>Edit Game</b> (on an open game) changes one. Each is a row of the <b>games</b> tab.</p>
      <ul>
        <li><b>Tagline</b> and <b>Description</b> — a line and a short paragraph about the game.</li>
        <li><b>Designers 1–4</b> — type to pick from your <a href="pitchboard/help#people">people</a>, or a new name (it’s added to people for you).</li>
        <li><b>Status</b> — Pitching, Interested, Contract Sent, Signed, In Development, In Production, Published, Shelved or Cancelled — and <b>Date Started</b>, <b>Signed</b> and <b>Published</b>.</li>
        <li><b>Links</b> — Rules, Play, Print, Sellsheet, BGG / View, Video and Image. They show on the game’s card and its <a href="pitchboard/help#page">page</a>.</li>
        <li><b>Renaming</b> a game asks first: every pitch of it, and its tabs, follow the new name.</li>
        <li><b>Delete</b> removes the game and its pitches.</li>
      </ul>
    </div>
  </div>
</section>
<section class="topic" id="stages">
  <h2><span class="n">9</span>Stages &amp; shelving</h2>
  <p class="lead">The Board’s <b>Games by stage</b> puts each game in a column: Design, Pitch, Signed or Published.</p>
  <?= shot('stages', 'Click a game for <b>View</b> (open it in the Games list) and <b>Shelf</b>.') ?>
  <ul>
    <li>A game’s column comes from its <b>Status</b> (Pitching, Signed, Published, Design); with no status, from its dates and pitches — a game with no pitches is in <b>Design</b>.</li>
    <li>The number on a card is how many publishers it’s been pitched to.</li>
    <li><b>Shelf</b> puts a game aside: it leaves the stages for the <b>Shelved</b> list at the bottom, and its pitches stay as they are.</li>
  </ul>
</section>

<hr class="sep">
<section class="topic" id="pitch">
  <h2><span class="n">10</span>Logging a pitch</h2>
  <p class="lead">A pitch is one contact with a publisher about a game — an email, a meeting at a convention, a prototype sent.</p>
  <?= steps([['pitch-1', 'Open the game and click <b>+ Pitch</b> (or <b>+ Pitch</b> on a publisher’s row).'], ['pitch-2', 'Pick the publisher and contact, the date, the status, the event and any notes.'], ['pitch-3', '<b>Add</b>: the pitch is on the list at once, under its publisher.']], 'Log a pitch') ?>
  <ul>
    <li><b>Publisher</b> and <b>Contact</b> — type to pick one you’ve used before, or type a new name.</li>
    <li><b>Date</b>, <b>Status</b> (see <a href="pitchboard/help#statuses">statuses</a>), <b>Event</b> (where or how: <em>Gen Con</em>, <em>Email</em>, <em>Prototype sent</em>) and <b>Notes</b>.</li>
    <li>From the Publishers list, <b>New pitch</b> on a contact starts the pitch with that publisher and contact filled in — just pick the game.</li>
    <li>Each pitch is a row of the <b>pitches</b> tab. It’s kept in this browser and saved with your next <a href="pitchboard/help#saving">Save</a>.</li>
  </ul>
  <?= shot('addentry', 'The Add Entry dialog.', 'small') ?>
</section>
<section class="topic" id="statuses">
  <h2><span class="n">11</span>Pitch statuses</h2>
  <p class="lead">Give each pitch the status it left things at. A publisher’s latest pitch is where things stand with them.</p>
  <div class="cards">
    <div class="card"><b>Planned</b><p>You mean to pitch it. Ignored once a real pitch follows.</p></div>
    <div class="card"><b>Pitched</b><p>They’ve seen it; no answer yet.</p></div>
    <div class="card"><b>Interested</b><p>They want to know more, play it, or see a prototype.</p></div>
    <div class="card"><b>Passed</b><p>They said no.</p></div>
    <div class="card"><b>Gone Cold</b><p>No reply for a long time.</p></div>
    <div class="card"><b>Returned</b><p>The prototype came back.</p></div>
    <div class="card"><b>Signed</b><p>A contract — the game counts as signed.</p></div>
    <div class="card"><b>Published</b><p>It’s out — the game counts as published.</p></div>
  </div>
  <p style="margin-top:1rem">A game counts as signed or published when a pitch says so, or when its own Status or dates do.</p>
</section>
<section class="topic" id="entry">
  <h2><span class="n">12</span>Editing a pitch</h2>
  <div class="two narrow-left">
    <?= shot('entry', 'Click a pitch to change it.') ?>
    <div>
      <ul>
        <li>Click any pitch in an open game (or publisher) to change its <b>date</b>, <b>status</b>, <b>contact</b>, <b>event</b> or <b>notes</b>, then <b>Update</b>.</li>
        <li><b>Delete</b> asks first, then removes the pitch.</li>
        <li>To move things along, it’s usually better to <b>add</b> a pitch (<em>Interested</em> after <em>Pitched</em>) than to change the old one — the history stays.</li>
      </ul>
    </div>
  </div>
</section>
<section class="topic" id="followup">
  <h2><span class="n">13</span>Follow-ups</h2>
  <p class="lead">PitchBoard marks what’s waiting on an answer.</p>
  <ul>
    <li><b>3mo+</b> and <b>6mo+</b> badges mark a publisher whose latest pitch is still <em>Pitched</em> or <em>Interested</em> after three or six months — time to follow up, or mark it <em>Gone Cold</em>.</li>
    <li>On a game, the badge shows when any of its publishers is waiting that long.</li>
    <li><b>Email</b> on a publisher’s row starts an email to that contact.</li>
  </ul>
</section>

<hr class="sep">
<section class="topic" id="publishers">
  <h2><span class="n">14</span>The Publishers list</h2>
  <p class="lead">The same pitches, by publisher: who they are, who you talk to there, and every game they’ve seen.</p>
  <?= shot('pubs', 'Cobalt Games, opened: its contact, the buttons, and the games pitched to it.') ?>
  <ul>
    <li>Open a publisher to see its <b>contacts</b> and each <b>game</b> you’ve pitched them, with its latest status — open a game row for the pitches.</li>
    <li><b>Add Contact</b> adds a person at that publisher; <b>Edit Contact</b> changes one. <b>New Pitch</b> logs a pitch to that contact; <b>Email</b> writes to them.</li>
    <li>Publishers come from your pitches — there’s no separate list to keep.</li>
  </ul>
</section>
<section class="topic" id="people">
  <h2><span class="n">15</span>Contacts &amp; designers</h2>
  <p class="lead">Contacts and designers live in the <b>people</b> tab: name, email, company, role and notes.</p>
  <ul>
    <li>Click a <b>designer’s name</b> on a game (or <b>Edit Contact</b> on a publisher) to see or change that person. Someone not in the list yet is added when you save.</li>
    <li><b>Renaming</b> a person updates the games they designed.</li>
    <li>Designers typed into a game are added to people for you, so they’re there to pick next time.</li>
  </ul>
</section>

<hr class="sep">
<section class="topic" id="page">
  <h2><span class="n">16</span>Game pages</h2>
  <p class="lead">A public page for a game — to link from an email or a pitch deck.</p>
  <ul>
    <li><b>Enable Page</b> on an open game makes the page and opens its editor; afterwards the button says <b>Edit Page</b>, and a <b>Page</b> button opens it.</li>
    <li>The editor has the game’s picture and BoardGameGeek ID, players and playing time, price and links, components, and a pitch: picture, description, features and three <b>How to play</b> steps.</li>
    <li>The page uses the game’s tagline, description and links from your sheet, so it stays up to date. Anyone with the link can see it — no account needed.</li>
  </ul>
</section>
<section class="topic" id="share">
  <h2><span class="n">17</span>Share links</h2>
  <div class="two narrow-left">
    <?= shot('share', 'Share: a link to collaborate, and a link to show the game.') ?>
    <div>
      <ul>
        <li><b>Collaborate With Others</b> — <b>Generate Link</b> makes a link for a co-designer or agent. Anyone with it can see the game’s pitches, add new ones and edit them — no account needed — but can’t change the game itself. It’s always up to date.</li>
        <li><b>Show Your Game</b> — a public link to the game’s page (see <a href="pitchboard/help#page">Game pages</a>), view only.</li>
        <li><b>Copy</b> puts the link on the clipboard.</li>
      </ul>
      <div class="note"><b>Anyone with a link</b> can use it — share it only with people you trust with that game.</div>
    </div>
  </div>
</section>
<section class="topic" id="notes">
  <h2><span class="n">18</span>Playtester notes</h2>
  <p class="lead">Collect notes from playtesters with a simple form — no account needed to fill it in.</p>
  <ul>
    <li><b>Enable Notes</b> on an open game adds a <b>[Game] notes</b> tab to your sheet and gives you a form link — <b>Share</b> copies it.</li>
    <li>Each note sent through the form is a row of that tab, with the date, the person and what they wrote.</li>
    <li>Once notes are on, the game has a <b>View Notes</b> button: it shows what’s come in, with <b>Fetch</b> to read the newest and <b>Share</b> to copy the form link again.</li>
  </ul>
</section>
<section class="topic" id="import">
  <h2><span class="n">19</span>Import &amp; slideshow</h2>
  <ul>
    <li><b>Import</b> (menu) — paste a link to a PitchBoard share file and <b>Load</b>. PitchBoard lists its pitches and contacts, marking which are new, which have changed and which you already have, and <b>Import</b> adds them to this sheet. Handy for bringing in pitches a co-designer kept on their own.</li>
    <li><b>Slideshow</b> (menu) — your games one by one, full screen, for showing at a meeting or a booth.</li>
    <li><b>Pick the slides</b> — pull down the small bar at the top edge of the slideshow (or swipe down from the top on a phone, or press <b>G</b>). Tap a game to show or hide it and drag <b>⋮⋮</b> to change the order; <b>Reset</b> goes back to your Pitching games. Your choice is kept on that device.</li>
    <li><b>Stats and components</b> — tap a game’s name (or drag it up, or press <b>I</b>) to slide up its players, play time, complexity and components; tap again, drag it down or change slides to put it away. The ✉ in the box starts an email with the game’s details and links.</li>
  </ul>
</section>

<hr class="sep">
<section class="topic" id="saving">
  <h2><span class="n">20</span>Saving &amp; Fetch</h2>
  <p class="lead">Everything you change — games, pitches, people, settings — shows straight away and is kept in this browser, then sent to your Google Sheet in one go.</p>
  <?= shot('save-pending', '<b>Save</b> lights up when changes are waiting.', 'small') ?>
  <ul>
    <li><b>Save</b> (or <kbd>⌘S</kbd>) sends everything now. The button fills up as it goes, and you can keep working.</li>
    <li>PitchBoard also <b>saves by itself</b> once you’ve stopped for about a minute and a half (never while you’re typing). Closing the page doesn’t lose anything — unsaved changes stay in this browser and are saved next time you open it.</li>
    <li>Anything that reads from the sheet — <b>Fetch</b>, a share link, a game page — saves your changes first, so it always includes them.</li>
    <li>If the server can’t be reached, your changes wait for the next Save. If one can’t be made (the row was deleted in Sheets, say), PitchBoard tells you.</li>
    <li><b>Fetch</b> (menu) reads everything from the sheet again — use it after editing the sheet in Google Sheets.</li>
  </ul>
  <div class="note"><b>One browser:</b> until they’re saved, changes don’t follow you to another computer — and clearing the browser’s site data removes them. Save before you switch.</div>
</section>

<hr class="sep">
<section class="topic" id="plans">
  <h2><span class="n">21</span>Plans &amp; codes</h2>
  <div class="two">
    <?= shot('plans', 'Menu → Subscription: your plan and the ways to keep going.') ?>
    <div>
      <div class="cards" style="grid-template-columns:1fr;margin-top:0">
        <div class="card"><b>Free week</b><p>Every feature for 7 days from sign-up. No card needed.</p></div>
        <div class="card"><b>PitchBoard — $1.99/month</b><p>Keep tracking after the free week. Cancel any time from <b>Manage billing</b>.</p></div>
        <div class="card"><b>All-in-one — $9.99/month</b><p>PitchBoard, DevBoard, CardBoard and BillBoard together, from the GameBoard home page.</p></div>
        <div class="card"><b>Codes</b><p><b>Have a code?</b> in the plans (or menu → <b>Account</b>) unlocks time for every app, or just the one it was made for.</p></div>
      </div>
    </div>
  </div>
  <p style="margin-top:1rem">After the free week your sheet is view only until you subscribe — nothing in it is lost.</p>
</section>
<section class="topic" id="settings">
  <h2><span class="n">22</span>Settings &amp; menu</h2>
  <div class="two">
    <?= shot('menu', 'The menu, from the round person button.') ?>
    <?= shot('settings', 'Settings: your name, email, phone and Compendium code.') ?>
  </div>
  <ul>
    <li>At the top: who’s signed in, and the plan.</li>
    <li><b>Account</b> (first in the menu, or click your name at the top) — your picture, name and studio, the email you sign in with, your password, your plan (<b>Plans</b>) and codes.</li>
    <li><b>Profile</b> — your public profile (name, location, a short bio) for the people you share with. <b>Settings</b> — your name, email and phone, and your <b>Compendium</b> code to see publisher details from The Compendium by Cardboard Edison.</li>
    <li><b>Fetch</b> — read the latest from the sheet. <b>Import</b> and <b>Slideshow</b> — see <a href="pitchboard/help#import">Import &amp; slideshow</a>.</li>
    <li><b>Releases</b> — what’s new. <b>Feedback</b> — tell us what you think. <b>Help</b> — this page.</li>
    <li><b>GameBoard</b> — back to the GameBoard home. <b>Sign out</b> — asks first, then signs you out of every app in this browser.</li>
  </ul>
</section>
<section class="topic" id="trouble">
  <h2><span class="n">23</span>Troubleshooting</h2>
  <ul>
    <li><b>“Could not open the sheet”</b> when connecting — check it’s shared with <code>editor@zapsheets-480701.iam.gserviceaccount.com</code> as an <b>Editor</b>.</li>
    <li><b>Changes made in Google Sheets don’t show</b> — menu → <b>Fetch</b>.</li>
    <li><b>It says View only</b> — another account set this sheet up, or nobody has (<b>Make it mine</b>), or your free week has ended (<a href="pitchboard/help#plans">Plans</a>).</li>
    <li><b>Save stays lit</b> — you’re offline, or need to sign in again; your changes are kept until it works.</li>
    <li><b>A publisher shows twice</b> — the name is spelled two ways in the <b>pitches</b> tab. Fix the spelling in Sheets, then Fetch.</li>
    <li><b>No code email</b> — check spam, and wait a minute before asking for another.</li>
  </ul>
</section>

<p class="foot">© <?= date('Y') ?> ZapSheets · <a href="dashboard">Your sheets</a> · <a href="pitchboard/features">Features</a> · <a href="gameboard">GameBoard</a></p>
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
