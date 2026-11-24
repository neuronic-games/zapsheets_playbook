<?php
$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_base = preg_replace('#/devboard/help/?$#', '/', $_raw);
if (!$_base || $_base === $_raw) { $_base = '/'; }
if (substr($_base, -1) !== '/') { $_base .= '/'; }
$IMG = 'images/help/devboard/';
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
<title>DevBoard – Help</title>
<meta name="description" content="Playtest notes for your board games — every session, observation and TODO, plus contracts, estimates and invoices, from your Google Sheet.">
<link rel="icon" type="image/png" sizes="32x32" href="images/db_icon_32.png" />
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2')  format('woff2'),url('fonts/DINBlack.ttf')  format('truetype'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf') format('truetype'); }
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root { --navy:#0f3640; --ink:#1b1d2b; --violet:#1a7f93; --lav:#48c4d2; --gold:#a8bcd7; --bg:#f2f6f7; --line:#d9e8ec; --mid:#4a5068; --muted:#8a90a6; --side:280px; }
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
.find { width:100%; padding:.55rem .75rem; border:1.5px solid var(--line); border-radius:9px; font:inherit; font-size:.88rem; margin-bottom:.9rem; background:#f8fcfd; }
.find:focus { outline:none; border-color:var(--violet); box-shadow:0 0 0 3px #dff3f6; background:#fff; }
.side h4 { font-family:'DINBlack',sans-serif; font-weight:400; font-size:.64rem; letter-spacing:.12em; text-transform:uppercase; color:var(--muted); margin:1.1rem .5rem .35rem; }
.side h4:first-of-type { margin-top:.2rem; }
.side a { display:flex; align-items:center; gap:.55rem; padding:.36rem .55rem; border-radius:8px; color:var(--mid); text-decoration:none; font-size:.9rem; line-height:1.3; }
.side a .n { font-family:'DINBlack',sans-serif; font-size:.62rem; color:#b4b8c9; width:1.3rem; text-align:right; flex-shrink:0; }
.side a:hover { background:#eef8fa; color:var(--navy); }
.side a.on { background:#dff3f6; color:var(--navy); font-family:'DINBlack',sans-serif; font-size:.86rem; }
.side a.on .n { color:var(--violet); }
.side a.hide, .side h4.hide { display:none; }
.main { padding:2rem clamp(1.2rem,4vw,3.5rem) 6rem; min-width:0; }
.hero { background:linear-gradient(135deg,#1c1a2b 0%,#0f3640 70%,#146878 100%); color:#fff; border-radius:18px; padding:2.2rem 2.2rem 2.2rem; display:grid; grid-template-columns:1fr 300px; gap:2rem; align-items:center; overflow:hidden; }
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
.topic > h2 .n { font-size:.8rem; color:var(--violet); background:#dff3f6; border-radius:6px; padding:.05rem .45rem; }
.topic > .lead { font-size:1.02rem; color:var(--mid); margin-bottom:1rem; }
.topic h3 { font-family:'DINBlack',sans-serif; font-weight:400; font-size:.8rem; letter-spacing:.09em; text-transform:uppercase; color:var(--violet); margin:1.5rem 0 .4rem; }
.topic p, .topic li { font-size:.95rem; color:#3c4052; }
.topic p + p { margin-top:.6rem; }
.topic ul, .topic ol { padding-left:1.3rem; margin-top:.4rem; }
.topic li { margin-bottom:.35rem; }
.topic li::marker { color:var(--violet); }
.sep { border:none; border-top:1px solid var(--line); margin:2.6rem 0 0; max-width:900px; }
kbd, code { font-family:ui-monospace,Menlo,monospace; font-size:.8em; background:#dff3f6; color:#146878; border-radius:4px; padding:.05rem .32rem; }
.btn-like { display:inline-block; font-family:'DINBlack',sans-serif; font-size:.68rem; letter-spacing:.06em; text-transform:uppercase; border:1.5px solid #b5dbe3; border-radius:6px; padding:0 .4rem; color:var(--navy); background:#fff; white-space:nowrap; }
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
table.keys td { padding:.5rem .9rem; border-bottom:1px solid #d9e8ec; color:#3c4052; vertical-align:top; }
table.keys tr:last-child td { border-bottom:none; }
table.keys td:first-child { white-space:nowrap; width:1%; }
table.keys tr.h td { background:#f8fcfd; font-family:'DINBlack',sans-serif; font-size:.68rem; letter-spacing:.1em; text-transform:uppercase; color:var(--muted); }
/* ── Screenshots ── */
.shot { margin:1rem 0 0; background:#fff; border:1px solid var(--line); border-radius:12px; overflow:hidden; box-shadow:0 6px 22px rgba(15,54,64,.08); }
.shot img { display:block; width:100%; height:auto; cursor:zoom-in; }
.shot figcaption { padding:.5rem .85rem .6rem; font-size:.8rem; color:var(--muted); border-top:1px solid #d9e8ec; }
.shot.small { max-width:360px; }
.shot.mid { max-width:560px; }
.two .shot { margin-top:0; }
/* ── Animated steps ── */
.steps { margin-top:1.1rem; background:#fff; border:1px solid var(--line); border-radius:14px; overflow:hidden; box-shadow:0 6px 22px rgba(15,54,64,.08); }
.steps-head { display:flex; align-items:center; gap:.6rem; padding:.6rem .9rem; border-bottom:1px solid #d9e8ec; }
.steps-head b { font-family:'DINBlack',sans-serif; font-weight:400; font-size:.88rem; color:var(--navy); flex:1; }
.steps-tag { font-family:'DINBlack',sans-serif; font-size:.6rem; letter-spacing:.1em; text-transform:uppercase; color:#fff; background:#e8623a; border-radius:5px; padding:.12rem .45rem; }
.steps-play { border:1.5px solid var(--line); background:#fff; border-radius:7px; width:2rem; height:1.7rem; font-size:.62rem; color:var(--navy); cursor:pointer; }
.steps-stage { position:relative; background:#eef0f6; }
.steps-stage img { display:block; width:100%; height:auto; opacity:0; transition:opacity .45s ease; position:absolute; inset:0; cursor:zoom-in; }
.steps-stage img.on { opacity:1; position:relative; }
.steps-stage img.on ~ img.on { position:absolute; }
.steps-bar { position:absolute; left:0; right:0; bottom:0; height:3px; background:rgba(0,0,0,.06); }
.steps-bar i { display:block; height:100%; width:0; background:#e8623a; }
.steps-list { list-style:none; padding:.4rem !important; margin:0 !important; display:grid; grid-auto-flow:column; grid-auto-columns:1fr; gap:.3rem; counter-reset:st; border-top:1px solid #d9e8ec; }
.steps-list li { margin:0 !important; }
.steps-list button { width:100%; height:100%; text-align:left; border:none; background:none; border-radius:9px; padding:.5rem .6rem .5rem 2.1rem; font:inherit; font-size:.82rem; line-height:1.35; color:var(--muted); cursor:pointer; position:relative; }
.steps-list li { counter-increment:st; }
.steps-list button::before { content:counter(st); position:absolute; left:.55rem; top:.5rem; width:1.15rem; height:1.15rem; border-radius:50%; background:#d9e8ec; color:var(--mid); font-family:'DINBlack',sans-serif; font-size:.62rem; display:flex; align-items:center; justify-content:center; }
.steps-list li.on button { background:#eef8fa; color:var(--ink); }
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
  <a class="top-home" href="dashboard" title="Your sheets"><img src="images/db_icon_180.png" alt=""><span class="top-name"><span class="brand"><span class="c">Dev</span><span class="b">Board</span></span><span class="top-lbl">Help</span></span></a>
  <span class="top-sp"></span>
  <a class="open" href="dashboard">Open DevBoard</a>
</div></header>

<div class="wrap">
<nav class="side" id="side" aria-label="Help topics">
  <input class="find" id="find" type="search" placeholder="Find a topic…" aria-label="Find a topic">
  <h4>Getting started</h4>
  <a href="devboard/help#welcome"><span class="n">1</span>What DevBoard does</a>
  <a href="devboard/help#sheet"><span class="n">2</span>Set up your sheet</a>
  <a href="devboard/help#account"><span class="n">3</span>Sign in</a>
  <a href="devboard/help#owner"><span class="n">4</span>Your sheets &amp; view only</a>
  <a href="devboard/help#screen"><span class="n">5</span>The screen</a>
  <h4>Playtesting</h4>
  <a href="devboard/help#board"><span class="n">6</span>The Summary</a>
  <a href="devboard/help#timeline"><span class="n">7</span>Timeline</a>
  <a href="devboard/help#games"><span class="n">8</span>Games</a>
  <a href="devboard/help#session"><span class="n">9</span>Logging a session</a>
  <a href="devboard/help#sessions"><span class="n">10</span>Reading &amp; editing sessions</a>
  <a href="devboard/help#todos"><span class="n">11</span>TODOs</a>
  <a href="devboard/help#share"><span class="n">12</span>Sharing &amp; reports</a>
  <h4>Contracts &amp; money</h4>
  <a href="devboard/help#contracts"><span class="n">13</span>The Contracts list</a>
  <a href="devboard/help#estimates"><span class="n">14</span>Contracts &amp; estimates</a>
  <a href="devboard/help#invoices"><span class="n">15</span>Invoices</a>
  <a href="devboard/help#payments"><span class="n">16</span>Payments</a>
  <a href="devboard/help#tm"><span class="n">17</span>Time &amp; materials</a>
  <a href="devboard/help#company"><span class="n">18</span>Your company details</a>
  <h4>Saving</h4>
  <a href="devboard/help#saving"><span class="n">19</span>Saving &amp; Fetch</a>
  <h4>Account</h4>
  <a href="devboard/help#plans"><span class="n">20</span>Plans &amp; codes</a>
  <a href="devboard/help#settings"><span class="n">21</span>Profile, settings &amp; menu</a>
  <a href="devboard/help#keys"><span class="n">22</span>Keyboard shortcuts</a>
  <a href="devboard/help#trouble"><span class="n">23</span>Troubleshooting</a>

</nav>

<main class="main" id="main">
<div class="hero">
  <div>
    <h1><span class="c">Dev</span><span class="b">Board</span> Help</h1>
    <p>Every playtest, remembered: who played, how long it took, what happened and what to change — and the contracts, estimates and invoices for your design work. All in your own Google Sheet.</p>
    <div class="quick"><a href="devboard/help#sheet">Set up a sheet</a><a href="devboard/help#session">Log a session</a><a href="devboard/help#todos">TODOs</a><a href="devboard/help#estimates">Estimates</a><a href="devboard/help#saving">Saving</a></div>
  </div>
  <img src="<?= $IMG ?>hero.jpg" alt="DevBoard">
</div>

<section class="topic" id="welcome">
  <h2><span class="n">1</span>What DevBoard does</h2>
  <p class="lead">Your Google Sheet holds the notes: a <b>[Game] dev</b> tab for each game you’re developing, with one block of rows per <b>session</b> — a playtest, a meeting, an idea. DevBoard turns them into a log you can read, search and share.</p>
  <div class="cards">
    <div class="card"><b>Sessions</b><p>Playtests, meetings, ideas, editing and reporting — with a timer and round counter.</p></div>
    <div class="card"><b>Observations</b><p>What happened, and what to do about it, side by side.</p></div>
    <div class="card"><b>TODOs</b><p>Turn any observation into a TODO with a priority, a person and a due date.</p></div>
    <div class="card"><b>Sharing</b><p>Links for playtesters and collaborators, a public game page, and printable reports.</p></div>
    <div class="card"><b>Contracts</b><p>Estimates and invoices as PDFs, payments, and time &amp; materials.</p></div>
  </div>
</section>
<section class="topic" id="sheet">
  <h2><span class="n">2</span>Set up your sheet</h2>
  <?= shot('newsheet', 'The DevBoard start page: open one of your sheets or start a blank one, then paste its link.') ?>
  <ol>
    <li><b>Open or make a sheet.</b> <b>Open a sheet</b> goes to your Google Sheets; <b>Start a blank sheet</b> makes a new one. Your PitchBoard sheet works too — your games are already there.</li>
    <li><b>Share it with DevBoard.</b> In Sheets, click <b>Share</b> and add <code>editor@zapsheets-480701.iam.gserviceaccount.com</code> (the <b>Copy</b> button copies it) as an <b>Editor</b>.</li>
    <li><b>Connect it.</b> Paste the sheet’s link and click <b>Connect &amp; Open</b>. DevBoard adds the tabs it needs and opens your board — bookmark that page to come straight back.</li>
  </ol>
  <p>DevBoard uses these tabs: <b>games</b>, <b>people</b>, <b>contracts</b>, <b>settings</b> and <b>bios</b>, plus a <b>[Game] dev</b> tab for each game you develop and a <b>todos</b> tab. Tabs that are already there are used as they are, and the same names are used by every GameBoard app.</p>
  <div class="tip"><b>Your sheets:</b> every sheet you’ve connected is listed on your GameBoard <a href="dashboard">dashboard</a>, with an Open link for each app.</div>
</section>
<section class="topic" id="account">
  <h2><span class="n">3</span>Sign in</h2>
  <p class="lead">DevBoard uses your <b>GameBoard account</b> — one email and password for DevBoard, PitchBoard, CardBoard and BillBoard.</p>
  <?= shot('signin', 'Start your free week, or log in.', 'mid') ?>
  <ul>
    <li><b>Start your free week</b> — your email and a password (8 or more characters, with a letter and a number). We email you a 6-digit code; type it in and you’re in, with every feature for 7 days.</li>
    <li><b>Log in</b> — your email and password. You stay signed in on that browser, in every GameBoard app.</li>
    <li><b>Forgot password?</b> — on the log-in screen. We email a code; enter it with your new password.</li>
  </ul>
</section>
<section class="topic" id="owner">
  <h2><span class="n">4</span>Your sheets &amp; view only</h2>
  <p class="lead">The account that connects a sheet owns it: only that account changes it. Anyone else who opens it can look, not change.</p>
  <?= shot('viewonly', 'A sheet that belongs to another account opens view only.') ?>
  <ul>
    <li>Connecting a sheet on the DevBoard start page makes it yours straight away.</li>
    <li><b>Make it mine</b> appears on a sheet nobody has set up — click it to take it on.</li>
    <li>To let playtesters or a co-designer add sessions, send them a <a href="devboard/help#share">Collaborate link</a> instead.</li>
  </ul>
</section>
<section class="topic" id="screen">
  <h2><span class="n">5</span>The screen</h2>
  <?= shot('games', 'The Games tab: search and + Game, then each game you’re developing.') ?>
  <ul>
    <li><b>Tabs</b> at the top: <b>Summary</b> (the overview), <b>Games</b> (your sessions) and <b>Contracts</b>, with how many games and contracts you have.</li>
    <li><b>Save</b> (top right) sends your changes to the sheet — see <a href="devboard/help#saving">Saving</a>. The round person button (your photo, once you’ve added one) opens the <a href="devboard/help#settings">menu</a>.</li>
    <li>Each tab has its own <b>search</b>: sessions, people and notes on Games; publishers, games and contracts on Contracts.</li>
  </ul>
</section>

<hr class="sep">
<section class="topic" id="board">
  <h2><span class="n">6</span>The Summary</h2>
  <p class="lead">Everything that needs doing, and where each game stands.</p>
  <?= shot('board', 'The Summary: the totals at the top, then TODOs, the timeline and your activity.') ?>
  <ul>
    <li><b>The totals</b> — games, games in active development, contracts and publishers.</li>
    <li><b>TODOs</b> — every open TODO from every game, with the number still open. See <a href="devboard/help#todos">TODOs</a>.</li>
    <li><b>Timeline</b> — your active projects, from start to due date. See <a href="devboard/help#timeline">Timeline</a>.</li>
    <li><b>Activity</b> — your contracts and playtest sessions together, newest first: estimates, invoices and payments with their amounts and status, each session with its type, where it was and how many played, and time &amp; materials — one line per game, person and day, with the time and costs added up. Click a contract to open it, or a session to open its game. <b>Show more</b> goes further back.</li>
  </ul>
</section>
<section class="topic" id="timeline">
  <h2><span class="n">7</span>Timeline</h2>
  <p class="lead">Every active project on one line of time: what’s late, what’s next, and what’s still only an estimate.</p>
  <?= shot('timeline', 'The timeline on the Summary: one bar per contract, from its start to its due date, with today marked.') ?>
  <h3>Reading it</h3>
  <ul>
    <li><b>Each bar is a contract</b>, from its start to its due date; the due date and how long is left are on the right. Projects due soonest are at the top.</li>
    <li><b>The pink line is today.</b> Month names run along the top.</li>
    <li><b>Colors:</b> teal — on track; <b>amber</b> — due within a week; <b>red</b> — late (“4 days late”).</li>
    <li><b>Striped</b> — an estimate that hasn’t been invoiced yet: an offer, not a go-ahead.</li>
    <li><b>≈ before a date</b> — the contract has no due date of its own, so it was worked out from its <b>Duration</b>.</li>
    <li><b>A game with several contracts</b> shows them together: the game’s name with “2 contracts”, then each contract on its own row, named by its summary.</li>
  </ul>
  <?= shot('timeline-hover', 'Hover over a row for the contract’s summary, its Ref Number and dates.') ?>
  <ul>
    <li><b>Hover</b> over a row for the contract’s summary, the game, publisher and Ref Number, and its dates.</li>
    <li><b>Click</b> a row to open the contract and change it.</li>
  </ul>
  <h3>What’s on it</h3>
  <ul>
    <li>Contracts with a <b>Target End Date</b>, or a <b>Duration</b> (“1 month”, “6 weeks”, “10 days”) to work one out from. Payments aren’t projects, so they never show.</li>
    <li><b>Where a bar starts:</b> the <b>Target Start Date</b>, or else the <b>Start Date</b>, or else the contract’s date.</li>
    <li><b>Estimates that were invoiced</b> — a deposit, say — count as going ahead: solid, and they stay (late, if need be) until they’re finished. An invoice is matched to its estimate by its note (“Invoice from estimate #…”), or by the same game and summary.</li>
  </ul>
  <h3>When a project leaves it</h3>
  <ul>
    <li>Give the contract an actual <b>End Date</b> — it’s finished.</li>
    <li>Or it’s <b>Paid</b> and its due date has gone by.</li>
    <li>An estimate that was never invoiced leaves once its due date has passed.</li>
  </ul>
  <div class="tip"><b>To put a project on the timeline:</b> open the contract and set its <b>Target End Date</b> (and a <b>Target Start Date</b>, for where the bar begins) — or give it a <b>Duration</b> in the sheet.</div>
</section>
<section class="topic" id="games">
  <h2><span class="n">8</span>Games</h2>
  <p class="lead">Each game you’re developing, with its sessions newest first.</p>
  <ul>
    <li><b>+ Game</b> starts development on a game: pick one from your games list or type a new name. DevBoard adds a <b>[Game] dev</b> tab for its sessions (and a row in <b>games</b> for a new game).</li>
    <li>Open a game to see its <b>session counts</b> (<em>3 Playtests</em>, <em>1 Meeting</em>…) — click one to show only that kind — and its buttons: <b>Share</b>, <b>Edit</b> (the game’s name, designers, status, dates and links), <b>+ Session</b>, <b>+ T&amp;M</b> and the round arrow, which reloads the game’s sessions from the sheet.</li>
    <li>The designers are listed on the game’s bar.</li>
  </ul>
  <?= shot('game', 'Edit: the game’s details, the same as in PitchBoard.', 'mid') ?>
</section>
<section class="topic" id="session">
  <h2><span class="n">9</span>Logging a session</h2>
  <p class="lead">A session is one sitting with the game — a playtest, a meeting, an idea, an editing pass or a report.</p>
  <?= steps([['log-1', 'Open the game and click <b>+ Session</b>.'], ['log-2', 'Fill in the date, type, where, who played — then what you saw and what to do about it.'], ['log-3', '<b>Add Session</b>: it’s at the top of the game’s list at once.']], 'Log a playtest') ?>
  <ul>
    <li><b>Date</b>, <b>Type</b> (Playtest, Meeting, Idea, Editing, Reporting) and <b>Location</b>. The <b>Session number</b> counts up by itself for each type.</li>
    <li><b>People</b> — who played. Type a name (or pick one from your people); a new box appears for the next. New names are added to your people list.</li>
    <li><b>Rounds</b> — the <b>−</b> and <b>+</b> at the top count rounds as you play. The <b>stopwatch</b> times the session; click it to start and stop, or set the minutes by hand.</li>
    <li><b>Observations</b> and <b>Solutions</b> — one pair per thing you noticed: what happened on the left, what to change on the right. A new pair appears as you type. The picture button adds a photo.</li>
    <li>The <b>light bulb</b> on an observation makes it a <a href="devboard/help#todos">TODO</a>.</li>
    <li><kbd>⌘</kbd>+arrow keys move between the boxes; <kbd>⌘</kbd>+<kbd>Enter</kbd> adds the session.</li>
  </ul>
  <?= shot('session', 'The session dialog, filled in: rounds and timer at the top, two testers, and two observations.') ?>
</section>
<section class="topic" id="sessions">
  <h2><span class="n">10</span>Reading &amp; editing sessions</h2>
  <?= shot('session-open', 'Click a session to open it: each observation beside its solution.') ?>
  <ul>
    <li>A session’s row shows its type and number, date, location, length and rounds, who played and how many notes it has. Click it to open it.</li>
    <li>Click an open session’s notes to <b>edit</b> it — the same dialog, with <b>Delete</b> to remove the session.</li>
    <li><b>Search</b> finds sessions by what was said, who played, or where.</li>
    <li>Each session is a block of rows in the game’s <b>[Game] dev</b> tab: the first row has the date, type and number, then one row per player, then one per observation.</li>
  </ul>
</section>
<section class="topic" id="todos">
  <h2><span class="n">11</span>TODOs</h2>
  <p class="lead">Turn what you noticed into a list of what to do.</p>
  <div class="two narrow-left">
    <?= shot('todo', 'A TODO: the observation, notes, priority, who and when.') ?>
    <div>
      <ul>
        <li>Click the <b>light bulb</b> on an observation in a session to make it a TODO. It shows on the game and on the <b>Summary</b>.</li>
        <li>Tick a TODO when it’s done; drag the <b>⋮⋮</b> handle to reorder.</li>
        <li>The three icons set the <b>priority</b> (high, medium, low), <b>who</b> it’s for and the <b>due date</b>.</li>
        <li>Click a TODO to open it — change its words, add notes.</li>
        <li>TODOs are kept in the <b>todos</b> tab, and marked in the session’s cell in the sheet.</li>
      </ul>
    </div>
  </div>
</section>
<section class="topic" id="share">
  <h2><span class="n">12</span>Sharing &amp; reports</h2>
  <p class="lead"><b>Share</b> on an open game gives you three links.</p>
  <div class="two narrow-left">
    <?= shot('share', 'Share: Collaborate, Public page and Report.') ?>
    <div>
      <ul>
        <li><b>Collaborate</b> — anyone with this link can see the game’s sessions and add new ones — playtesters, a co-designer — with no account. <b>Copy</b> puts it on the clipboard.</li>
        <li><b>Public page</b> — the game’s info page, view only.</li>
        <li><b>Report</b> — <b>Generate Report</b> makes a page of the session notes and time &amp; materials. Open it and print, or save as PDF, to send to a publisher.</li>
      </ul>
      <div class="note"><b>Anyone with a link</b> can use it — share it only with people you trust with that game.</div>
    </div>
  </div>
</section>

<hr class="sep">
<section class="topic" id="contracts">
  <h2><span class="n">13</span>The Contracts list</h2>
  <p class="lead">Paid design work — development, editing, playtesting for a publisher — by client and game.</p>
  <?= shot('contracts', 'Cobalt Games, opened: an estimate and an invoice for Harbor Lights, a payment, and the buttons.') ?>
  <ul>
    <li>Each <b>client</b> (publisher) is a card. Open it for each game you’ve worked on for them, with its estimates, invoices, contracts and payments.</li>
    <li>Click an estimate or invoice to see its number, amount and date, with <b>Share</b> (email the PDF), <b>Edit</b>, <b>+ Invoice</b> and <b>Delete</b>.</li>
    <li>Under each game: <b>Edit Game</b>, <b>+ Estimate</b>, <b>+ Invoice</b>, <b>+ Payment</b> and <b>+ Report</b>.</li>
    <li>Everything is kept in the <b>contracts</b> tab of your sheet; the PDFs are made on the server.</li>
  </ul>
</section>
<section class="topic" id="estimates">
  <h2><span class="n">14</span>Contracts &amp; estimates</h2>
  <p class="lead"><b>+ Contract</b> (or <b>+ Estimate</b> under a game) writes up the work and makes an estimate PDF.</p>
  <div class="two narrow-left">
    <?= shot('estimate', 'New Contract: the client, the game, the scope and the price.') ?>
    <div>
      <ul>
        <li><b>Type</b> — an <b>Estimate</b> to send first, or straight to an <b>Invoice</b>.</li>
        <li><b>Publisher / Client</b> and <b>Game</b> — pick or type. <b>Summary</b> — one line on what it’s for.</li>
        <li><b># of Tests</b>, <b># of Edit Rounds</b> and <b>Scope of Work</b> — what’s included.</li>
        <li><b>Unit Price</b>, <b>Printing</b> (optional), <b>Duration</b>, target start and end, and an optional <b>discount</b>.</li>
        <li><b>Create</b> makes the PDF with your <a href="devboard/help#company">company details</a> on it.</li>
        <li><b>Edit</b> later to change the dates, amounts or status (Estimate, Invoice, Paid) — the PDF is made again.</li>
      </ul>
    </div>
  </div>
</section>
<section class="topic" id="invoices">
  <h2><span class="n">15</span>Invoices</h2>
  <div class="two narrow-left">
    <?= shot('invoice', '+ Invoice from an estimate: a fixed amount or a share of it.') ?>
    <div>
      <ul>
        <li><b>+ Invoice</b> on an estimate bills against it: a <b>fixed amount</b>, or a <b>percentage</b> of the estimate — handy for milestones (<em>Milestone 1 of 2</em>).</li>
        <li>Add a <b>summary</b>, a <b>description</b>, a <b>due date</b> and notes, then <b>Create</b>.</li>
        <li>An invoice without an estimate: <b>+ Invoice</b> under a game, with just the amount and due date.</li>
        <li>Mark it <b>Paid</b> with <b>Edit</b> when the money arrives.</li>
      </ul>
    </div>
  </div>
</section>
<section class="topic" id="payments">
  <h2><span class="n">16</span>Payments</h2>
  <p class="lead">Money you paid out — to an artist, a playtest group, a co-designer.</p>
  <div class="two narrow-left">
    <?= shot('payment', '+ Payment: who was paid, for which contract, how much and when.') ?>
    <div>
      <ul>
        <li><b>Paid to</b> — pick someone from your people, or type a name.</li>
        <li><b>Contract</b> — the work it’s for (pick a contract’s summary), the <b>amount</b>, the <b>date paid</b> and notes.</li>
        <li>Payments show under the game as <em>paid out</em>, so you can see what a project cost you.</li>
      </ul>
    </div>
  </div>
</section>
<section class="topic" id="tm">
  <h2><span class="n">17</span>Time &amp; materials</h2>
  <p class="lead">For work billed by the hour, log the time and costs as you go.</p>
  <div class="two narrow-left">
    <?= shot('tm', '+ T&amp;M: the contract, what you did, the time, and any materials.') ?>
    <div>
      <ul>
        <li><b>+ T&amp;M</b> on an open game: pick the <b>contract</b>, say what you worked on, and the <b>time</b> (<em>1 hr</em>, <em>30 min</em>).</li>
        <li><b>Materials</b> — anything you bought for it, with its cost.</li>
        <li>Time and materials go in the game’s dev tab and into its <a href="devboard/help#share">report</a>.</li>
      </ul>
    </div>
  </div>
</section>
<section class="topic" id="company">
  <h2><span class="n">18</span>Your company details</h2>
  <p class="lead">What goes at the top of your estimates and invoices.</p>
  <?= shot('company', 'Menu → Company: your logo, name, address and how to pay you.', 'mid') ?>
  <ul>
    <li><b>Logo</b>, <b>Company Name</b> and <b>Address</b> head every estimate and invoice.</li>
    <li><b>Payment Info</b> — how clients pay you (bank details, PayPal…) — is printed at the bottom of invoices.</li>
  </ul>
</section>

<hr class="sep">
<section class="topic" id="saving">
  <h2><span class="n">19</span>Saving &amp; Fetch</h2>
  <p class="lead">Everything you change — sessions, TODOs, games, contracts — shows straight away and is kept in this browser, then sent to your Google Sheet in one go.</p>
  <?= shot('save-pending', '<b>Save</b> lights up when changes are waiting.', 'small') ?>
  <ul>
    <li><b>Save</b> (or <kbd>⌘S</kbd>) sends everything now. The button fills up as it goes, and you can keep working.</li>
    <li>DevBoard also <b>saves by itself</b> once you’ve stopped for about a minute and a half (never while you’re typing). Closing the page doesn’t lose anything — unsaved changes stay in this browser and are saved next time you open it.</li>
    <li>Anything that reads from the sheet or makes something from it — <b>Fetch</b>, reloading a game, an estimate PDF, a report — saves your changes first, so it always includes them.</li>
    <li>If the server can’t be reached, your changes wait for the next Save. If one can’t be made (the session was deleted in Sheets, say), DevBoard tells you.</li>
    <li><b>Fetch</b> (menu) reads everything from the sheet again — use it after editing the sheet in Google Sheets.</li>
  </ul>
  <div class="note"><b>One browser:</b> until they’re saved, changes don’t follow you to another computer — and clearing the browser’s site data removes them. Save before you switch.</div>
</section>

<hr class="sep">
<section class="topic" id="plans">
  <h2><span class="n">20</span>Plans &amp; codes</h2>
  <div class="two">
    <?= shot('plans', 'Menu → Subscription: Core, Pro, or the all-in-one.') ?>
    <div>
      <div class="cards" style="grid-template-columns:1fr;margin-top:0">
        <div class="card"><b>Free week</b><p>Every feature for 7 days from sign-up. No card needed.</p></div>
        <div class="card"><b>Core — $2.99/month</b><p>Sessions and notes, the timer and rounds, photos, TODOs, sharing and reports.</p></div>
        <div class="card"><b>Pro — $3.99/month</b><p>Everything in Core, plus contracts, estimates and invoices.</p></div>
        <div class="card"><b>All-in-one — $9.99/month</b><p>DevBoard Pro, PitchBoard, CardBoard and BillBoard together.</p></div>
        <div class="card"><b>Codes</b><p><b>Have a code?</b> in the plans (or menu → <b>Account</b>) unlocks time for every app, or just the one it was made for.</p></div>
      </div>
    </div>
  </div>
  <p style="margin-top:1rem">After the free week your sheet is view only until you subscribe — nothing in it is lost. Cancel any time from <b>Manage billing</b>.</p>
</section>
<section class="topic" id="settings">
  <h2><span class="n">21</span>Profile, settings &amp; menu</h2>
  <div class="two">
    <?= shot('menu', 'The menu, from the round person button.') ?>
    <?= shot('profile', 'Profile: your photo and details, for your shared pages and paperwork.') ?>
  </div>
  <ul>
    <li><b>Account</b> (first in the menu, or click your name at the top) — your picture, name and studio, the email you sign in with, your password, your plan (<b>Plans</b>) and codes.</li>
    <li><b>Profile</b> — your photo, name, email, a short description and skills, mailing address, messaging handle, phone and how you’d like to be paid. Your photo also shows on the menu button.</li>
    <li><b>Company</b> — see <a href="devboard/help#company">Your company details</a>. <b>Fetch</b> — read the latest from the sheet. <b>Releases</b> — what’s new. <b>Help</b> — this page.</li>
    <li><b>GameBoard</b> — back to the GameBoard home. <b>Sign out</b> — asks first, then signs you out of every app in this browser.</li>
  </ul>
</section>
<section class="topic" id="keys">
  <h2><span class="n">22</span>Keyboard shortcuts</h2>
  <p><kbd>⌘</kbd> is <kbd>Ctrl</kbd> on Windows.</p>
  <table class="keys">
    <tr class="h"><td colspan="2">Everywhere</td></tr>
    <tr><td><kbd>⌘S</kbd></td><td>Save everything to the sheet</td></tr>
    <tr><td><kbd>Esc</kbd></td><td>Close a dialog (it shakes instead if you’ve typed something — use Cancel to drop it)</td></tr>
    <tr class="h"><td colspan="2">Session dialog</td></tr>
    <tr><td><kbd>⌘</kbd>+arrow keys</td><td>Move between the boxes</td></tr>
    <tr><td><kbd>⌘</kbd>+<kbd>Enter</kbd></td><td>Add (or save) the session</td></tr>
  </table>
</section>
<section class="topic" id="trouble">
  <h2><span class="n">23</span>Troubleshooting</h2>
  <ul>
    <li><b>“Sheet not shared”</b> when connecting — check it’s shared with <code>editor@zapsheets-480701.iam.gserviceaccount.com</code> as an <b>Editor</b>.</li>
    <li><b>Changes made in Google Sheets don’t show</b> — the round arrow on the game, or menu → <b>Fetch</b>.</li>
    <li><b>It says View only</b> — another account set this sheet up, or nobody has (<b>Make it mine</b>), or your free week has ended (<a href="devboard/help#plans">Plans</a>). Contracts need <b>Pro</b>.</li>
    <li><b>Save stays lit</b> — you’re offline, or need to sign in again; your changes are kept until it works.</li>
    <li><b>A contract isn’t on the timeline</b> — it needs a Target End Date or a Duration; an estimate that wasn’t invoiced drops off once its date has passed, and a contract with an End Date is finished. See <a href="devboard/help#timeline">Timeline</a>.</li>
    <li><b>A game isn’t in the Games tab</b> — it has no dev tab yet: <b>+ Game</b> and pick it.</li>
    <li><b>The PDF has no logo or address</b> — fill in menu → <b>Company</b>, then <b>Edit</b> and save the estimate again.</li>
    <li><b>No code email</b> — check spam, and wait a minute before asking for another.</li>
  </ul>
</section>

<p class="foot">© <?= date('Y') ?> ZapSheets · <a href="dashboard">Your sheets</a> · <a href="devboard/features">Features</a> · <a href="gameboard">GameBoard</a></p>
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
