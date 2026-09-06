<?php
$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_base = preg_replace('#/pitchboard/features/?$#', '/', $_raw);
if (!$_base || $_base === $_raw) { $_base = '/'; }
if (substr($_base, -1) !== '/') { $_base .= '/'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>PitchBoard — Features</title>
<link rel="icon" type="image/png" href="images/pb_icon_180.png" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Fraunces:ital,opsz,wght@0,9..144,600;1,9..144,400&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --navy: #1a2744; --navy-mid: #243358; --navy-light: #2e4170;
  --amber: #e8993a; --amber-light: #f5c06e; --amber-pale: #fdf3e3;
  --teal: #1d8c70; --teal-pale: #e8f7f3;
  --slate: #64748b; --slate-pale: #f1f5f9;
  --text: #1e293b; --text-mid: #475569; --text-muted: #94a3b8;
  --border: #e2e8f0; --white: #ffffff; --radius: 12px; --radius-sm: 8px;
  --app-bg: #12203b; --app-bg2: #1a2e50; --app-bg3: #1f3560;
  --app-beige: #f0ede8; --app-red: #e8503a;
}
body { font-family:'Inter',system-ui,sans-serif; color:var(--text); background:var(--white); line-height:1.6; font-size:16px; }

nav { position:sticky; top:0; z-index:100; background:rgba(255,255,255,.94); backdrop-filter:blur(12px); border-bottom:1px solid var(--border); padding:0 2rem; display:flex; align-items:center; justify-content:space-between; height:60px; }
.nav-logo { font-family:'Fraunces',Georgia,serif; font-size:1.25rem; font-weight:600; color:var(--navy); letter-spacing:-0.02em; text-decoration:none; }
.nav-logo span { color:var(--amber); }
nav a { font-size:14px; color:var(--text-mid); text-decoration:none; }
.nav-links { display:flex; gap:2rem; }
.nav-back { font-size:13px; color:var(--text-muted); }
.nav-back:hover { color:var(--navy); }
.nav-cta { background:var(--navy); color:var(--white)!important; padding:8px 18px; border-radius:999px; font-size:14px; font-weight:500; text-decoration:none; }

.hero { background:var(--navy); color:var(--white); padding:7rem 2rem 5rem; text-align:center; position:relative; overflow:hidden; }
.hero::before { content:''; position:absolute; top:-120px; left:50%; transform:translateX(-50%); width:800px; height:800px; background:radial-gradient(circle,rgba(232,153,58,.12) 0%,transparent 70%); pointer-events:none; }
.hero-tag { display:inline-block; font-size:12px; font-weight:500; letter-spacing:.08em; text-transform:uppercase; color:var(--amber); border:1px solid rgba(232,153,58,.35); border-radius:999px; padding:4px 14px; margin-bottom:1.75rem; }
.hero h1 { font-family:'Fraunces',Georgia,serif; font-size:clamp(2.4rem,5vw,3.75rem); font-weight:600; line-height:1.1; letter-spacing:-0.03em; max-width:800px; margin:0 auto 1.5rem; }
.hero h1 em { font-style:italic; color:var(--amber-light); font-weight:400; }
.hero p { font-size:1.15rem; color:rgba(255,255,255,.72); max-width:540px; margin:0 auto 2.5rem; line-height:1.7; }
.hero-btns { display:flex; gap:1rem; justify-content:center; flex-wrap:wrap; }
.btn-primary { background:var(--amber); color:var(--navy); font-weight:600; font-size:15px; padding:13px 28px; border-radius:999px; text-decoration:none; transition:background .15s; display:inline-block; }
.btn-primary:hover { background:var(--amber-light); }
.btn-ghost { background:transparent; color:rgba(255,255,255,.8); font-weight:500; font-size:15px; padding:13px 28px; border-radius:999px; border:1px solid rgba(255,255,255,.25); text-decoration:none; display:inline-block; }
.btn-ghost:hover { border-color:rgba(255,255,255,.6); color:var(--white); }

.pipeline { background:var(--navy-mid); border-top:1px solid rgba(255,255,255,.06); padding:1.5rem 2rem; display:flex; align-items:center; justify-content:center; gap:0; flex-wrap:wrap; }
.stage-pill { font-size:13px; font-weight:500; padding:7px 18px; border-radius:999px; white-space:nowrap; }
.stage-design  { background:rgba(100,116,139,.25); color:#94a3b8; }
.stage-pitch   { background:rgba(29,140,112,.25);  color:#5ecfb0; }
.stage-signed  { background:rgba(124,58,237,.2);   color:#c4b5fd; }
.stage-pub     { background:rgba(37,99,235,.2);    color:#93c5fd; }
.stage-arrow   { color:rgba(255,255,255,.2); font-size:18px; padding:0 8px; }

section { padding:5.5rem 2rem; }
.container { max-width:1080px; margin:0 auto; }
.section-label { font-size:12px; font-weight:500; letter-spacing:.1em; text-transform:uppercase; color:var(--teal); margin-bottom:.75rem; }
.section-title { font-family:'Fraunces',Georgia,serif; font-size:clamp(1.8rem,3vw,2.5rem); font-weight:600; letter-spacing:-0.025em; line-height:1.2; color:var(--navy); margin-bottom:1rem; }
.section-sub { font-size:1.05rem; color:var(--text-mid); max-width:560px; line-height:1.7; margin-bottom:3.5rem; }

.features-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:1.25rem; }
.feat-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:1.75rem; }
.feat-icon { width:42px; height:42px; border-radius:var(--radius-sm); display:flex; align-items:center; justify-content:center; font-size:20px; margin-bottom:1rem; }
.feat-icon.teal   { background:var(--teal-pale); }
.feat-icon.amber  { background:var(--amber-pale); }
.feat-icon.navy   { background:#eef1f8; }
.feat-icon.purple { background:#f4f0fd; }
.feat-icon.slate  { background:var(--slate-pale); }
.feat-icon.green  { background:#edf7ed; }
.feat-card h3 { font-size:1rem; font-weight:600; color:var(--navy); margin-bottom:.5rem; }
.feat-card p { font-size:14px; color:var(--text-mid); line-height:1.65; }

.browser-frame { background:#e8e8e8; border-radius:14px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,.18); }
.browser-chrome { background:#d4d4d4; padding:10px 14px; display:flex; align-items:center; gap:10px; }
.browser-dots { display:flex; gap:6px; }
.browser-dots span { width:12px; height:12px; border-radius:50%; }
.browser-dots .d1 { background:#f87171; } .browser-dots .d2 { background:#fbbf24; } .browser-dots .d3 { background:#4ade80; }
.browser-url { flex:1; background:var(--white); border-radius:6px; padding:4px 10px; font-size:11px; color:#666; font-family:monospace; }
.browser-body { background:var(--app-beige); }

.app-nav { background:var(--app-bg); display:flex; align-items:center; justify-content:space-between; padding:0 16px; height:44px; }
.app-nav-brand { font-family:'Fraunces',Georgia,serif; font-size:15px; font-weight:700; color:var(--app-red); letter-spacing:-0.01em; }
.app-nav-sub { font-size:9px; color:rgba(255,255,255,.4); margin-top:1px; }
.app-nav-btns { display:flex; gap:4px; }
.app-nav-btn { font-size:10px; font-weight:500; padding:5px 10px; border-radius:6px; border:1px solid rgba(255,255,255,.15); color:rgba(255,255,255,.6); background:transparent; }
.app-nav-btn.active { background:rgba(255,255,255,.12); color:var(--white); border-color:rgba(255,255,255,.25); }

.app-filter-bar { background:var(--app-bg); padding:0 16px 10px; display:flex; gap:6px; flex-wrap:wrap; }
.filter-pill { font-size:9px; font-weight:600; letter-spacing:.04em; padding:4px 10px; border-radius:999px; }
.fp-pitched   { background:rgba(232,153,58,.2); color:#f5c06e; }
.fp-interested{ background:rgba(29,158,117,.2); color:#5ecfb0; }
.fp-passed    { background:rgba(148,163,184,.15); color:#94a3b8; }
.fp-cold      { background:rgba(71,85,105,.25); color:#94a3b8; }
.fp-signed    { background:rgba(124,58,237,.25); color:#c4b5fd; }
.fp-pub       { background:rgba(37,99,235,.2); color:#93c5fd; }
.app-search { background:var(--app-bg); padding:0 16px 12px; }
.app-search input { width:100%; background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.12); border-radius:8px; padding:7px 12px; font-size:11px; color:rgba(255,255,255,.5); outline:none; font-family:inherit; }
.game-list { padding:10px 16px 16px; display:flex; flex-direction:column; gap:6px; }
.game-row { background:var(--app-bg2); border-radius:8px; padding:11px 14px; display:flex; align-items:center; justify-content:space-between; }
.game-row-name { font-size:12px; font-weight:500; color:var(--white); }
.game-row-meta { font-size:10px; color:rgba(255,255,255,.4); margin-top:2px; }
.game-row-badges { display:flex; gap:5px; align-items:center; }
.badge { font-size:9px; font-weight:600; padding:3px 9px; border-radius:999px; }
.badge-signed    { background:rgba(124,58,237,.3); color:#c4b5fd; border:1px solid rgba(124,58,237,.4); }
.badge-interested{ background:rgba(29,158,117,.25); color:#5ecfb0; border:1px solid rgba(29,158,117,.35); }
.badge-old       { background:rgba(232,153,58,.2); color:#f5c06e; border:1px solid rgba(232,153,58,.3); }
.badge-pub       { background:rgba(37,99,235,.2); color:#93c5fd; border:1px solid rgba(37,99,235,.3); }
.row-arrow { color:rgba(255,255,255,.25); font-size:11px; margin-left:6px; }

.stats-bar { background:var(--app-beige); padding:12px 16px; display:flex; gap:8px; flex-wrap:wrap; }
.stat-tile { background:var(--white); border:1px solid var(--border); border-radius:8px; padding:10px 14px; min-width:80px; text-align:center; }
.stat-num { font-size:18px; font-weight:600; color:var(--navy); line-height:1; }
.stat-tile.highlight .stat-num { color:#7c3aed; }
.stat-lbl { font-size:8px; font-weight:500; letter-spacing:.06em; text-transform:uppercase; color:var(--text-muted); margin-top:3px; }
.charts-row { display:grid; grid-template-columns:1fr 1.4fr; gap:10px; padding:0 16px 16px; }
.chart-card { background:var(--white); border:1px solid var(--border); border-radius:8px; padding:12px; }
.chart-title { font-size:9px; font-weight:600; letter-spacing:.07em; text-transform:uppercase; color:var(--text-muted); margin-bottom:10px; }
.donut-wrap { display:flex; align-items:center; gap:14px; }
.donut { position:relative; width:80px; height:80px; }
.donut svg { width:80px; height:80px; transform:rotate(-90deg); }
.donut-legend { display:flex; flex-direction:column; gap:5px; }
.legend-row { display:flex; align-items:center; gap:6px; font-size:9px; color:var(--text-mid); }
.legend-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
.bar-list { display:flex; flex-direction:column; gap:6px; }
.bar-row { display:flex; align-items:center; gap:8px; font-size:9px; }
.bar-label { color:var(--text-mid); width:90px; text-align:right; flex-shrink:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.bar-track { flex:1; background:#f1f5f9; border-radius:3px; height:10px; overflow:hidden; }
.bar-fill { height:100%; border-radius:3px; background:var(--navy); }
.bar-count { color:var(--text-muted); width:16px; text-align:right; flex-shrink:0; }

.kanban-header { padding:12px 16px 4px; }
.kanban-header h4 { font-size:11px; font-weight:600; color:var(--text-mid); }
.kanban-cols { display:grid; grid-template-columns:repeat(4,1fr); gap:8px; padding:8px 16px 16px; }
.kb-col { border-radius:8px; padding:8px; }
.kb-col.design  { background:#f8fafc; border:1px solid #e2e8f0; }
.kb-col.pitch   { background:#f0faf6; border:1px solid #c6ead9; }
.kb-col.signed  { background:#f7f3ff; border:1px solid #ddd6fe; }
.kb-col.pub     { background:#eff6ff; border:1px solid #bfdbfe; }
.kb-col-hdr { font-size:9px; font-weight:600; letter-spacing:.07em; text-transform:uppercase; margin-bottom:6px; }
.kb-col.design .kb-col-hdr  { color:#64748b; }
.kb-col.pitch  .kb-col-hdr  { color:#1d8c70; }
.kb-col.signed .kb-col-hdr  { color:#7c3aed; }
.kb-col.pub    .kb-col-hdr  { color:#1d4ed8; }
.kb-card { background:var(--white); border:1px solid var(--border); border-radius:6px; padding:7px 9px; margin-bottom:5px; }
.kb-card-name { font-size:10px; font-weight:500; color:var(--navy); display:flex; justify-content:space-between; align-items:center; }
.kb-card-meta { font-size:9px; color:var(--text-muted); margin-top:2px; }
.kb-pill { font-size:9px; font-weight:500; background:var(--slate-pale); color:var(--slate); border-radius:999px; padding:1px 6px; flex-shrink:0; }
.kb-card.open { border-left:2px solid var(--amber); }
.kb-card-dates { margin-top:6px; border-top:1px solid var(--border); padding-top:6px; }
.kb-date-row { display:flex; justify-content:space-between; font-size:9px; margin-bottom:2px; }
.kb-date-lbl { color:var(--text-muted); } .kb-date-val { color:var(--text); font-weight:500; }
.kb-btns { display:flex; gap:4px; margin-top:6px; }
.kb-btn { font-size:9px; padding:3px 9px; border-radius:4px; border:1px solid var(--border); background:var(--white); color:var(--text-mid); }
.kb-btn.view  { color:var(--teal); border-color:rgba(29,140,112,.3); }
.kb-col-count { font-size:9px; color:var(--text-muted); text-align:center; padding-top:3px; }

.shot-section { background:var(--slate-pale); }
.shot-grid { display:grid; grid-template-columns:1fr 1fr; gap:3rem; align-items:center; }
.shot-grid.flip { direction:rtl; }
.shot-grid.flip > * { direction:ltr; }
.shot-text p { font-size:15px; color:var(--text-mid); line-height:1.7; }
.shot-text ul { margin:.75rem 0 0 1.1rem; }
.shot-text ul li { font-size:14px; color:var(--text-mid); margin-bottom:.35rem; line-height:1.5; }

.ownership-section { background:var(--navy); color:var(--white); }
.ownership-section .section-label { color:var(--amber-light); }
.ownership-section .section-title { color:var(--white); }
.ownership-section .section-sub   { color:rgba(255,255,255,.65); }
.own-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem; }
.own-card { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); border-radius:var(--radius); padding:1.5rem; }
.own-card .num { font-family:'Fraunces',Georgia,serif; font-size:2.25rem; font-weight:600; color:var(--amber-light); line-height:1; margin-bottom:.5rem; }
.own-card p { font-size:14px; color:rgba(255,255,255,.65); line-height:1.6; }

.compare-wrap { border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
.compare-table { width:100%; border-collapse:collapse; font-size:14px; }
.compare-table th { padding:12px 16px; text-align:left; font-weight:600; font-size:13px; color:var(--navy); border-bottom:2px solid var(--border); background:var(--slate-pale); }
.compare-table th.ours { background:var(--amber-pale); border-bottom:2px solid var(--amber); }
.compare-table td { padding:12px 16px; border-bottom:1px solid var(--border); color:var(--text-mid); vertical-align:middle; }
.compare-table td:first-child { color:var(--text); font-weight:500; }
.compare-table td.ours { background:rgba(253,243,227,.35); }
.compare-table tr:last-child td { border-bottom:none; }
.check { color:var(--teal); } .cross { color:var(--text-muted); }

.cta-section { background:var(--amber-pale); text-align:center; padding:5rem 2rem; border-top:1px solid rgba(232,153,58,.2); }
.cta-section p { font-size:1.05rem; color:var(--text-mid); margin-bottom:2rem; }
footer { background:var(--navy); color:rgba(255,255,255,.45); text-align:center; padding:2rem; font-size:13px; }
footer a { color:rgba(255,255,255,.45); text-decoration:none; }
footer a:hover { color:rgba(255,255,255,.7); }

@media(max-width:720px){
  .shot-grid,.shot-grid.flip { grid-template-columns:1fr; direction:ltr; }
  .nav-links { display:none; }
  .kanban-cols { grid-template-columns:repeat(2,1fr); }
  .charts-row { grid-template-columns:1fr; }
}
</style>
</head>
<body>

<nav>
  <a class="nav-logo" href="pitchboard">Pitch<span>Board</span></a>
  <div class="nav-links">
    <a href="#features">Features</a>
    <a href="#screenshots">See it in action</a>
    <a href="#compare">Compare</a>
  </div>
  <a href="pitchboard" class="nav-cta">Get started</a>
</nav>

<section class="hero">
  <div class="hero-tag">For tabletop game designers</div>
  <h1>From first sketch to <em>signed deal</em> — tracked.</h1>
  <p>PitchBoard keeps every game, every publisher, and every pitch in one place. Built on your Google Sheet, so your data stays yours.</p>
  <div class="hero-btns">
    <a href="pitchboard" class="btn-primary">Get PitchBoard</a>
    <a href="#screenshots" class="btn-ghost">See it in action</a>
  </div>
</section>

<div class="pipeline">
  <span class="stage-pill stage-design">Design</span>
  <span class="stage-arrow">→</span>
  <span class="stage-pill stage-pitch">Pitching</span>
  <span class="stage-arrow">→</span>
  <span class="stage-pill stage-signed">Signed</span>
  <span class="stage-arrow">→</span>
  <span class="stage-pill stage-pub">Published</span>
</div>

<section id="features">
  <div class="container">
    <div class="section-label">Features</div>
    <h2 class="section-title">Everything a designer needs to stay on top of pitches</h2>
    <p class="section-sub">From tracking contacts to sharing a game page — PitchBoard covers the full lifecycle without a complicated tool or a monthly subscription.</p>
    <div class="features-grid">
      <div class="feat-card">
        <div class="feat-icon teal">📋</div>
        <h3>Per-game pitch log</h3>
        <p>Log every publisher you've approached — contact, date, outcome, and notes. Every conversation on record, linked to the game.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon amber">🗂</div>
        <h3>Board view</h3>
        <p>See all your games as a kanban across Design, Pitching, Signed, and Published. Drag a card to move its stage — dates update automatically.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon navy">📄</div>
        <h3>Sell sheet generator</h3>
        <p>Create a publisher-ready sell sheet directly from the app. Your game's data powers the layout — no separate design tool needed.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon purple">🔗</div>
        <h3>Collaborator links</h3>
        <p>Share a game's board with a co-designer or agent so they can add and edit pitches — without creating an account.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon green">🌐</div>
        <h3>Game pages</h3>
        <p>Publish a public-facing page for any game via a secure token link. Share with publishers or press without exposing your sheet ID.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon slate">📅</div>
        <h3>Smart date tracking</h3>
        <p>Moving a game to Signed or Published auto-fills the date if blank. Started, signed, and published dates tracked per game.</p>
      </div>
    </div>
  </div>
</section>

<section class="shot-section" id="screenshots">
  <div class="container">

    <div class="shot-grid" style="margin-bottom:5rem;">
      <div class="shot-text">
        <div class="section-label">Pitch tracker</div>
        <h2 class="section-title" style="margin-bottom:1rem;">Every game, every pitch, one view</h2>
        <p>Filter by outcome to see what's interested, what passed, and what's gone cold. Each row expands to show the full pitch history for that game.</p>
        <ul>
          <li>Filter by Pitched, Interested, Passed, Gone Cold, Signed, Published</li>
          <li>Search across games, publishers, and contacts</li>
          <li>Sort by date or alphabetically</li>
          <li>Add a new game in one click</li>
        </ul>
      </div>
      <div class="browser-frame">
        <div class="browser-chrome">
          <div class="browser-dots"><span class="d1"></span><span class="d2"></span><span class="d3"></span></div>
          <div class="browser-url">zapsheets.com/app/…/pitchboard</div>
        </div>
        <div class="browser-body">
          <div class="app-nav">
            <div><div class="app-nav-brand">PitchBoard</div><div class="app-nav-sub">Your Studio · studio@example.com</div></div>
            <div class="app-nav-btns">
              <div class="app-nav-btn active">BOARD</div>
              <div class="app-nav-btn">38 GAMES</div>
              <div class="app-nav-btn">61 PUBLISHERS</div>
            </div>
          </div>
          <div class="app-filter-bar">
            <span class="filter-pill fp-pitched">28 PITCHED</span>
            <span class="filter-pill fp-interested">11 INT</span>
            <span class="filter-pill fp-passed">74 PASSED</span>
            <span class="filter-pill fp-cold">19 GONE COLD</span>
            <span class="filter-pill fp-signed">5 SIGNED</span>
            <span class="filter-pill fp-pub">6 PUBLISHED</span>
          </div>
          <div class="app-search"><input placeholder="Search games, publishers, contacts…" readonly></div>
          <div class="game-list">
            <div class="game-row">
              <div><div class="game-row-name">Stormveil</div><div class="game-row-meta">Jordan, Alex</div></div>
              <div class="game-row-badges"><span class="badge badge-signed">SIGNED</span><span class="row-arrow">▶</span></div>
            </div>
            <div class="game-row">
              <div><div class="game-row-name">Deepwater Run</div><div class="game-row-meta">Jordan</div></div>
              <div class="game-row-badges"><span class="badge badge-old">6MO+</span><span class="badge badge-interested">INT</span><span class="row-arrow">▶</span></div>
            </div>
            <div class="game-row">
              <div><div class="game-row-name">The Cartographer</div><div class="game-row-meta">Alex</div></div>
              <div class="game-row-badges"><span class="badge badge-old">6MO+</span><span class="badge badge-interested">INT</span><span class="row-arrow">▶</span></div>
            </div>
            <div class="game-row">
              <div><div class="game-row-name">Ironveil Pass</div><div class="game-row-meta">—</div></div>
              <div class="game-row-badges"><span class="badge badge-interested">INT</span><span class="row-arrow">▶</span></div>
            </div>
            <div class="game-row">
              <div><div class="game-row-name">Nocturne Market</div><div class="game-row-meta">Jordan, Alex</div></div>
              <div class="game-row-badges"><span class="badge badge-pub">PUBLISHED</span><span class="row-arrow">▶</span></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="shot-grid flip" style="margin-bottom:5rem;">
      <div class="shot-text">
        <div class="section-label">Dashboard</div>
        <h2 class="section-title" style="margin-bottom:1rem;">Understand your pipeline at a glance</h2>
        <p>The board view surfaces the numbers that matter — how many games are active, how many publishers you're reaching, and how long it's taking to close deals.</p>
        <ul>
          <li>Games by status — at-a-glance donut chart</li>
          <li>Top publishers by games pitched</li>
          <li>Average months from pitch to signed and to published</li>
          <li>Live totals: games, publishers, pitches</li>
        </ul>
      </div>
      <div class="browser-frame">
        <div class="browser-chrome">
          <div class="browser-dots"><span class="d1"></span><span class="d2"></span><span class="d3"></span></div>
          <div class="browser-url">zapsheets.com/app/…/pitchboard</div>
        </div>
        <div class="browser-body">
          <div class="app-nav">
            <div><div class="app-nav-brand">PitchBoard</div><div class="app-nav-sub">Your Studio · studio@example.com</div></div>
            <div class="app-nav-btns">
              <div class="app-nav-btn active">BOARD</div>
              <div class="app-nav-btn">38 GAMES</div>
              <div class="app-nav-btn">61 PUBLISHERS</div>
            </div>
          </div>
          <div class="stats-bar">
            <div class="stat-tile"><div class="stat-num">38</div><div class="stat-lbl">Total games</div></div>
            <div class="stat-tile"><div class="stat-num">6</div><div class="stat-lbl">Published</div></div>
            <div class="stat-tile highlight"><div class="stat-num">5</div><div class="stat-lbl">Signed</div></div>
            <div class="stat-tile"><div class="stat-num">21</div><div class="stat-lbl">In pitching</div></div>
            <div class="stat-tile"><div class="stat-num">6</div><div class="stat-lbl">Not pitched</div></div>
            <div class="stat-tile"><div class="stat-num">61</div><div class="stat-lbl">Publishers</div></div>
            <div class="stat-tile"><div class="stat-num">18.4<br><span style="font-size:10px;font-weight:400">mo</span></div><div class="stat-lbl">Avg to sign</div></div>
          </div>
          <div class="charts-row">
            <div class="chart-card">
              <div class="chart-title">Games by status</div>
              <div class="donut-wrap">
                <div class="donut">
                  <svg viewBox="0 0 80 80">
                    <circle cx="40" cy="40" r="28" fill="none" stroke="#e2e8f0" stroke-width="14" stroke-dasharray="176 0"/>
                    <circle cx="40" cy="40" r="28" fill="none" stroke="#ddd6fe" stroke-width="14" stroke-dasharray="28 148"/>
                    <circle cx="40" cy="40" r="28" fill="none" stroke="#7c3aed" stroke-width="14" stroke-dasharray="23 153" stroke-dashoffset="-28"/>
                    <circle cx="40" cy="40" r="28" fill="none" stroke="#5ecfb0" stroke-width="14" stroke-dasharray="97 79" stroke-dashoffset="-51"/>
                    <circle cx="40" cy="40" r="28" fill="none" stroke="#2563eb" stroke-width="14" stroke-dasharray="28 148" stroke-dashoffset="-148"/>
                  </svg>
                </div>
                <div class="donut-legend">
                  <div class="legend-row"><div class="legend-dot" style="background:#e2e8f0"></div>Not pitched</div>
                  <div class="legend-row"><div class="legend-dot" style="background:#5ecfb0"></div>Pitching</div>
                  <div class="legend-row"><div class="legend-dot" style="background:#7c3aed"></div>Signed</div>
                  <div class="legend-row"><div class="legend-dot" style="background:#2563eb"></div>Published</div>
                </div>
              </div>
            </div>
            <div class="chart-card">
              <div class="chart-title">Top publishers by games pitched</div>
              <div class="bar-list">
                <div class="bar-row"><div class="bar-label">Ravensburger</div><div class="bar-track"><div class="bar-fill" style="width:90%"></div></div><div class="bar-count">9</div></div>
                <div class="bar-row"><div class="bar-label">Osprey</div><div class="bar-track"><div class="bar-fill" style="width:70%"></div></div><div class="bar-count">7</div></div>
                <div class="bar-row"><div class="bar-label">Pandasaurus</div><div class="bar-track"><div class="bar-fill" style="width:60%"></div></div><div class="bar-count">6</div></div>
                <div class="bar-row"><div class="bar-label">Brotherwise</div><div class="bar-track"><div class="bar-fill" style="width:50%"></div></div><div class="bar-count">5</div></div>
                <div class="bar-row"><div class="bar-label">Renegade</div><div class="bar-track"><div class="bar-fill" style="width:40%"></div></div><div class="bar-count">4</div></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="shot-grid">
      <div class="shot-text">
        <div class="section-label">Board view</div>
        <h2 class="section-title" style="margin-bottom:1rem;">Drag games through the pipeline</h2>
        <p>The kanban board shows every game as a card sorted by stage. Drag to move — dates fill in automatically. Click a card to see dates and actions. Shelf games you're not actively pitching to keep the board clean.</p>
        <ul>
          <li>Four columns: Design, Pitch, Signed, Published</li>
          <li>Publisher count shown as a pill on each card</li>
          <li>Click to expand — see Started, Signed, Published dates</li>
          <li>View in game list or shelf for later</li>
          <li>Works with drag-and-drop on iPad too</li>
        </ul>
      </div>
      <div class="browser-frame">
        <div class="browser-chrome">
          <div class="browser-dots"><span class="d1"></span><span class="d2"></span><span class="d3"></span></div>
          <div class="browser-url">zapsheets.com/app/…/pitchboard</div>
        </div>
        <div class="browser-body">
          <div class="app-nav">
            <div><div class="app-nav-brand">PitchBoard</div><div class="app-nav-sub">Your Studio · studio@example.com</div></div>
            <div class="app-nav-btns">
              <div class="app-nav-btn active">BOARD</div>
              <div class="app-nav-btn">38 GAMES</div>
              <div class="app-nav-btn">61 PUBLISHERS</div>
            </div>
          </div>
          <div class="kanban-header"><h4>Games by stage</h4></div>
          <div class="kanban-cols">
            <div class="kb-col design">
              <div class="kb-col-hdr">Design</div>
              <div class="kb-card"><div class="kb-card-name">Verdant Depths</div><div class="kb-card-meta">Alex, Jordan</div></div>
              <div class="kb-card"><div class="kb-card-name">The Glass Engine</div><div class="kb-card-meta">—</div></div>
              <div class="kb-col-count">2 games</div>
            </div>
            <div class="kb-col pitch">
              <div class="kb-col-hdr">Pitch</div>
              <div class="kb-card"><div class="kb-card-name">Stormveil <span class="kb-pill">9</span></div><div class="kb-card-meta">Jordan</div></div>
              <div class="kb-card open">
                <div class="kb-card-name">Deepwater Run <span class="kb-pill">6</span></div>
                <div class="kb-card-meta">Alex</div>
                <div class="kb-card-dates">
                  <div class="kb-date-row"><span class="kb-date-lbl">Started</span><span class="kb-date-val">Jan 2024</span></div>
                  <div class="kb-date-row"><span class="kb-date-lbl">Signed</span><span class="kb-date-val">—</span></div>
                </div>
                <div class="kb-btns"><button class="kb-btn view">View</button><button class="kb-btn">Shelf</button></div>
              </div>
              <div class="kb-card"><div class="kb-card-name">Ironveil Pass <span class="kb-pill">4</span></div><div class="kb-card-meta">—</div></div>
              <div class="kb-col-count">3 games</div>
            </div>
            <div class="kb-col signed">
              <div class="kb-col-hdr">Signed</div>
              <div class="kb-card"><div class="kb-card-name">The Cartographer <span class="kb-pill">5</span></div><div class="kb-card-meta">Jordan, Alex</div></div>
              <div class="kb-col-count">1 game</div>
            </div>
            <div class="kb-col pub">
              <div class="kb-col-hdr">Published</div>
              <div class="kb-card"><div class="kb-card-name">Nocturne Market</div><div class="kb-card-meta">Jordan</div></div>
              <div class="kb-card"><div class="kb-card-name">Thornwood</div><div class="kb-card-meta">—</div></div>
              <div class="kb-col-count">2 games</div>
            </div>
          </div>
        </div>
      </div>
    </div>

  </div>
</section>

<section class="ownership-section">
  <div class="container">
    <div class="section-label">Data ownership</div>
    <h2 class="section-title">Your data lives in your Google Sheet</h2>
    <p class="section-sub">No subscription, no vendor lock-in. PitchBoard reads and writes to a Google Sheet you own. Export it, share it, back it up — it's always yours.</p>
    <div class="own-grid">
      <div class="own-card">
        <div class="num">$0</div>
        <p>No monthly fee. PitchBoard runs on infrastructure you already have — your Google account.</p>
      </div>
      <div class="own-card">
        <div class="num">100%</div>
        <p>Data ownership. Every pitch, every note, every date — in a spreadsheet you control completely.</p>
      </div>
      <div class="own-card">
        <div class="num">0</div>
        <p>Accounts required for collaborators. Share a link and co-designers can add pitches without signing up.</p>
      </div>
    </div>
  </div>
</section>

<section id="compare">
  <div class="container">
    <div class="section-label">How we compare</div>
    <h2 class="section-title">Built for designers, not enterprise sales teams</h2>
    <p class="section-sub">PitchBoard focuses on what tabletop game designers actually need — and skips the overhead.</p>
    <div class="compare-wrap">
      <table class="compare-table">
        <thead>
          <tr>
            <th>Feature</th>
            <th class="ours">PitchBoard</th>
            <th>Tabletop Publishers</th>
            <th>Boardssey</th>
          </tr>
        </thead>
        <tbody>
          <tr><td>Per-pitch entry tracking</td><td class="ours"><span class="check">✓</span></td><td><span class="check">✓</span></td><td><span class="check">✓</span></td></tr>
          <tr><td>Kanban pipeline view</td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Sell sheet generator</td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span></td><td><span class="check">✓</span></td></tr>
          <tr><td>Public game pages</td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Collaborator access (no account)</td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>You own the data</td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Publisher discovery database</td><td class="ours"><span class="cross">—</span></td><td><span class="check">✓</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Monthly cost</td><td class="ours" style="font-weight:600;color:#1a2744;">Free</td><td>$10 / mo</td><td>$5–35 / mo</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<section class="cta-section" id="cta">
  <div class="container">
    <div class="section-label" style="display:flex;justify-content:center;">Ready to start?</div>
    <h2 class="section-title" style="text-align:center;max-width:540px;margin:0 auto 1rem;">Stop managing pitches in a messy spreadsheet</h2>
    <p>PitchBoard turns your Google Sheet into a proper pitch-tracking system — kanban board, sell sheets, game pages, and all.</p>
    <a href="pitchboard" class="btn-primary" style="font-size:16px;padding:15px 36px;">Get PitchBoard</a>
  </div>
</section>

<footer>
  <a href="pitchboard">PitchBoard</a> &mdash; built for board game designers &nbsp;·&nbsp; <a href="/">ZapSheets</a>
</footer>

</body>
</html>
