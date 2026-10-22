<?php
$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_base = preg_replace('#/gameboard/features/?$#', '/', $_raw);
if (!$_base || $_base === $_raw) { $_base = '/'; }
if (substr($_base, -1) !== '/') { $_base .= '/'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>GameBoard — Features</title>
<link rel="icon" type="image/png" href="images/gb_logo.png" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Fraunces:ital,opsz,wght@0,9..144,600;1,9..144,400&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
  --dark:  #1a1c30; --dark-mid: #22254a; --dark-light: #30346a;
  --green: #4caa4b; --green-light: #7dcf7c; --green-pale: #edf7ed;
  --amber: #e8a030; --amber-light: #f5c06e; --amber-pale: #fdf3e3;
  --slate: #64748b; --slate-pale: #f1f5f9;
  --text: #1e293b; --text-mid: #475569; --text-muted: #94a3b8;
  --border: #e2e8f0; --white: #ffffff; --radius: 12px; --radius-sm: 8px;
  --app-bg: #1a1c30; --app-bg2: #22254a; --app-bg3: #2a2e60;
}
body { font-family:'Inter',system-ui,sans-serif; color:var(--text); background:var(--white); line-height:1.6; font-size:16px; }

nav { position:sticky; top:0; z-index:100; background:rgba(255,255,255,.94); backdrop-filter:blur(12px); border-bottom:1px solid var(--border); padding:0 2rem; display:flex; align-items:center; justify-content:space-between; height:60px; }
.nav-logo { display:flex; align-items:center; gap:.5rem; text-decoration:none; }
.nav-logo img { height:30px; width:auto; }
.nav-logo-name { font-family:'Fraunces',Georgia,serif; font-size:1.18rem; font-weight:600; color:var(--dark); letter-spacing:-0.02em; }
nav a { font-size:14px; color:var(--text-mid); text-decoration:none; }
.nav-links { display:flex; gap:2rem; }
.nav-cta { background:var(--dark); color:var(--white)!important; padding:8px 18px; border-radius:999px; font-size:14px; font-weight:500; text-decoration:none; }

.hero { background:var(--app-bg); color:var(--white); padding:7rem 2rem 5rem; text-align:center; position:relative; overflow:hidden; }
.hero::before { content:''; position:absolute; top:-120px; left:50%; transform:translateX(-50%); width:800px; height:800px; background:radial-gradient(circle,rgba(76,170,75,.14) 0%,transparent 70%); pointer-events:none; }
.hero::after  { content:''; position:absolute; bottom:-80px; right:10%; width:500px; height:500px; background:radial-gradient(circle,rgba(232,160,48,.08) 0%,transparent 70%); pointer-events:none; }
.hero-tag { display:inline-block; font-size:12px; font-weight:500; letter-spacing:.08em; text-transform:uppercase; color:var(--green-light); border:1px solid rgba(125,207,124,.3); border-radius:999px; padding:4px 14px; margin-bottom:1.75rem; }
.hero h1 { font-family:'Fraunces',Georgia,serif; font-size:clamp(2.4rem,5vw,3.75rem); font-weight:600; line-height:1.1; letter-spacing:-0.03em; max-width:800px; margin:0 auto 1.5rem; }
.hero h1 em { font-style:italic; color:var(--amber-light); font-weight:400; }
.hero p { font-size:1.15rem; color:rgba(255,255,255,.72); max-width:540px; margin:0 auto 2.5rem; line-height:1.7; }
.hero-btns { display:flex; gap:1rem; justify-content:center; flex-wrap:wrap; }
.btn-primary { background:var(--green); color:var(--white); font-weight:600; font-size:15px; padding:13px 28px; border-radius:999px; text-decoration:none; transition:background .15s; display:inline-block; }
.btn-primary:hover { background:var(--green-light); }
.btn-ghost { background:transparent; color:rgba(255,255,255,.8); font-weight:500; font-size:15px; padding:13px 28px; border-radius:999px; border:1px solid rgba(255,255,255,.25); text-decoration:none; display:inline-block; }
.btn-ghost:hover { border-color:rgba(255,255,255,.6); color:var(--white); }

.pipeline { background:var(--dark-mid); border-top:1px solid rgba(255,255,255,.06); padding:1.5rem 2rem; display:flex; align-items:center; justify-content:center; gap:0; flex-wrap:wrap; }
.stage-pill { font-size:13px; font-weight:500; padding:7px 18px; border-radius:999px; white-space:nowrap; }
.stage-sheet  { background:rgba(100,116,139,.25); color:#94a3b8; }
.stage-brand  { background:rgba(76,170,75,.2);   color:#7dcf7c; }
.stage-catalog{ background:rgba(232,160,48,.2);   color:var(--amber-light); }
.stage-share  { background:rgba(99,102,241,.2);   color:#a5b4fc; }
.stage-arrow  { color:rgba(255,255,255,.2); font-size:18px; padding:0 8px; }

section { padding:5.5rem 2rem; }
.container { max-width:1080px; margin:0 auto; }
.section-label { font-size:12px; font-weight:500; letter-spacing:.1em; text-transform:uppercase; color:var(--green); margin-bottom:.75rem; }
.section-title { font-family:'Fraunces',Georgia,serif; font-size:clamp(1.8rem,3vw,2.5rem); font-weight:600; letter-spacing:-0.025em; line-height:1.2; color:var(--dark); margin-bottom:1rem; }
.section-sub { font-size:1.05rem; color:var(--text-mid); max-width:560px; line-height:1.7; margin-bottom:3.5rem; }

.features-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:1.25rem; }
.feat-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:1.75rem; }
.feat-icon { width:42px; height:42px; border-radius:var(--radius-sm); display:flex; align-items:center; justify-content:center; font-size:20px; margin-bottom:1rem; }
.feat-icon.green  { background:var(--green-pale); }
.feat-icon.amber  { background:var(--amber-pale); }
.feat-icon.dark   { background:#eef1f8; }
.feat-icon.purple { background:#f4f0fd; }
.feat-icon.slate  { background:var(--slate-pale); }
.feat-icon.rose   { background:#fff1f2; }
.feat-card h3 { font-size:1rem; font-weight:600; color:var(--dark); margin-bottom:.5rem; }
.feat-card p { font-size:14px; color:var(--text-mid); line-height:1.65; }

/* ── Browser frame ── */
.browser-frame { background:#e8e8e8; border-radius:14px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,.18); }
.browser-chrome { background:#d4d4d4; padding:10px 14px; display:flex; align-items:center; gap:10px; }
.browser-dots { display:flex; gap:6px; }
.browser-dots span { width:12px; height:12px; border-radius:50%; }
.browser-dots .d1 { background:#f87171; } .browser-dots .d2 { background:#fbbf24; } .browser-dots .d3 { background:#4ade80; }
.browser-url { flex:1; background:var(--white); border-radius:6px; padding:4px 10px; font-size:11px; color:#666; font-family:monospace; }

/* ── App shell ── */
.app-banner { background:var(--app-bg); padding:14px 16px 12px; display:flex; align-items:center; gap:12px; }
.app-banner-logo { width:32px; height:32px; border-radius:8px; object-fit:contain; }
.app-banner-name { font-size:15px; font-weight:700; color:var(--white); font-family:'Inter',sans-serif; letter-spacing:-.01em; }
.app-banner-tag  { font-size:9px; color:rgba(255,255,255,.45); }
.app-banner-nav  { margin-left:auto; display:flex; gap:5px; }
.app-banner-btn  { font-size:9px; font-weight:500; padding:4px 9px; border-radius:5px; border:1px solid rgba(255,255,255,.15); color:rgba(255,255,255,.55); background:transparent; }
.app-banner-btn.active { background:rgba(255,255,255,.1); color:var(--white); border-color:rgba(255,255,255,.25); }

/* Catalog carousel strip */
.app-carousel { background:var(--app-bg2); height:80px; display:flex; align-items:center; justify-content:center; position:relative; overflow:hidden; }
.app-carousel-img { position:absolute; inset:0; background:linear-gradient(135deg,#1f2d5a 0%,#0d1829 100%); display:flex; align-items:center; justify-content:center; }
.app-carousel-overlay { position:absolute; inset:0; background:linear-gradient(to top,rgba(26,28,48,.85),transparent); }
.carousel-caption { position:relative; text-align:center; }
.carousel-caption .brand-name { font-size:16px; font-weight:700; color:var(--white); letter-spacing:.08em; text-transform:uppercase; font-family:'Inter',sans-serif; }
.carousel-caption .brand-tag  { font-size:9px; color:rgba(255,255,255,.55); }

/* Game cards grid */
.app-games { padding:10px 12px 14px; display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.app-game-card { background:var(--app-bg2); border-radius:8px; overflow:hidden; }
.app-game-cover { height:64px; background:linear-gradient(135deg,#2a3d6b,#162240); display:flex; align-items:center; justify-content:center; font-size:22px; position:relative; }
.app-game-cover img { width:100%; height:100%; object-fit:cover; }
.app-game-body  { padding:7px 9px 8px; }
.app-game-name  { font-size:10px; font-weight:600; color:var(--white); }
.app-game-meta  { font-size:9px; color:rgba(255,255,255,.4); margin-top:1px; }
.app-game-badge { display:inline-block; margin-top:4px; font-size:8px; font-weight:600; padding:2px 7px; border-radius:999px; background:rgba(76,170,75,.25); color:#7dcf7c; border:1px solid rgba(76,170,75,.3); }

/* Game detail page mockup */
.app-hero-img { height:90px; background:linear-gradient(135deg,#0d2a4a,#1a4a7a); display:flex; align-items:flex-end; padding:8px 12px; position:relative; overflow:hidden; }
.app-hero-img::after { content:''; position:absolute; inset:0; background:linear-gradient(to top,rgba(13,26,48,.9) 0%,transparent 60%); }
.app-hero-title { position:relative; font-size:14px; font-weight:700; color:var(--white); z-index:1; }
.app-hero-sub   { position:relative; font-size:9px; color:rgba(255,255,255,.55); z-index:1; margin-top:1px; }
.app-game-info  { background:var(--app-bg); padding:10px 12px; display:flex; gap:8px; flex-wrap:wrap; }
.app-stat-pill  { font-size:9px; padding:4px 10px; border-radius:999px; background:rgba(255,255,255,.07); color:rgba(255,255,255,.6); }
.app-buy-row    { background:var(--app-bg2); padding:10px 12px; display:flex; align-items:center; justify-content:space-between; }
.app-price      { font-size:14px; font-weight:700; color:var(--white); }
.app-buy-btn    { font-size:10px; font-weight:600; padding:6px 14px; border-radius:999px; background:var(--green); color:var(--white); }
.app-desc-area  { background:var(--app-bg); padding:10px 12px; }
.app-desc-lbl   { font-size:8px; font-weight:600; letter-spacing:.07em; text-transform:uppercase; color:rgba(255,255,255,.35); margin-bottom:5px; }
.app-desc-text  { font-size:9px; color:rgba(255,255,255,.5); line-height:1.6; }

/* Settings mockup */
.app-settings { background:var(--app-bg); padding:0; }
.app-settings-hdr { padding:10px 12px; border-bottom:1px solid rgba(255,255,255,.07); display:flex; align-items:center; justify-content:space-between; }
.app-settings-title { font-size:11px; font-weight:600; color:var(--white); }
.app-save-btn { font-size:9px; padding:4px 12px; border-radius:4px; background:var(--green); color:var(--white); font-weight:600; }
.app-field-group { padding:8px 12px; border-bottom:1px solid rgba(255,255,255,.05); }
.app-field-label { font-size:8px; font-weight:500; letter-spacing:.06em; text-transform:uppercase; color:rgba(255,255,255,.35); margin-bottom:4px; }
.app-field-input { background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.1); border-radius:5px; padding:5px 8px; font-size:10px; color:rgba(255,255,255,.7); width:100%; }
.app-field-input.green-focus { border-color:rgba(76,170,75,.5); }
.app-social-row { padding:8px 12px; display:flex; gap:6px; flex-wrap:wrap; }
.app-social-btn { font-size:9px; padding:4px 10px; border-radius:999px; border:1px solid rgba(255,255,255,.12); color:rgba(255,255,255,.45); display:flex; align-items:center; gap:4px; }
.app-social-btn.set { border-color:rgba(76,170,75,.4); color:#7dcf7c; }

.shot-section { background:var(--slate-pale); }
.shot-grid { display:grid; grid-template-columns:1fr 1fr; gap:3rem; align-items:center; }
.shot-grid.flip { direction:rtl; }
.shot-grid.flip > * { direction:ltr; }
.shot-text p { font-size:15px; color:var(--text-mid); line-height:1.7; }
.shot-text ul { margin:.75rem 0 0 1.1rem; }
.shot-text ul li { font-size:14px; color:var(--text-mid); margin-bottom:.35rem; line-height:1.5; }

.ownership-section { background:var(--app-bg); color:var(--white); }
.ownership-section .section-label { color:var(--green-light); }
.ownership-section .section-title { color:var(--white); }
.ownership-section .section-sub   { color:rgba(255,255,255,.65); }
.own-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem; }
.own-card { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); border-radius:var(--radius); padding:1.5rem; }
.own-card .num { font-family:'Fraunces',Georgia,serif; font-size:2.25rem; font-weight:600; color:var(--green-light); line-height:1; margin-bottom:.5rem; }
.own-card p { font-size:14px; color:rgba(255,255,255,.65); line-height:1.6; }

.compare-wrap { border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; }
.compare-table { width:100%; border-collapse:collapse; font-size:14px; }
.compare-table th { padding:12px 16px; text-align:left; font-weight:600; font-size:13px; color:var(--dark); border-bottom:2px solid var(--border); background:var(--slate-pale); }
.compare-table th.ours { background:var(--green-pale); border-bottom:2px solid var(--green); }
.compare-table td { padding:12px 16px; border-bottom:1px solid var(--border); color:var(--text-mid); vertical-align:middle; }
.compare-table td:first-child { color:var(--text); font-weight:500; }
.compare-table td.ours { background:rgba(237,247,237,.5); }
.compare-table tr:last-child td { border-bottom:none; }
.check { color:var(--green); } .cross { color:var(--text-muted); }

.cta-section { background:var(--green-pale); text-align:center; padding:5rem 2rem; border-top:1px solid rgba(76,170,75,.15); }
.cta-section .section-label { display:flex; justify-content:center; }
.cta-section p { font-size:1.05rem; color:var(--text-mid); margin-bottom:2rem; }
footer { background:var(--app-bg); color:rgba(255,255,255,.45); text-align:center; padding:2rem; font-size:13px; }
footer a { color:rgba(255,255,255,.45); text-decoration:none; }
footer a:hover { color:rgba(255,255,255,.7); }

@media(max-width:720px){
  .shot-grid,.shot-grid.flip { grid-template-columns:1fr; direction:ltr; }
  .nav-links { display:none; }
  .app-games { grid-template-columns:1fr 1fr; }
}
</style>
</head>
<body>

<nav>
  <a class="nav-logo" href="gameboard">
    <img src="images/gb_logo.png" alt="GameBoard" />
    <span class="nav-logo-name">GameBoard</span>
  </a>
  <div class="nav-links">
    <a href="#features">Features</a>
    <a href="#screenshots">See it in action</a>
    <a href="#compare">Compare</a>
  </div>
  <a href="gameboard" class="nav-cta">Get started</a>
</nav>

<section class="hero">
  <div class="hero-tag">For board game publishers</div>
  <h1>Your games. A <em>polished page</em> — instantly.</h1>
  <p>GameBoard turns your Google Sheet into a beautiful product catalog and individual game pages — no web developer, no monthly subscription.</p>
  <div class="hero-btns">
    <a href="gameboard" class="btn-primary">Get GameBoard</a>
    <a href="gameboard/features#screenshots" class="btn-ghost">See it in action</a>
  </div>
</section>

<div class="pipeline">
  <span class="stage-pill stage-sheet">Connect Sheet</span>
  <span class="stage-arrow">→</span>
  <span class="stage-pill stage-brand">Brand it</span>
  <span class="stage-arrow">→</span>
  <span class="stage-pill stage-catalog">Publish catalog</span>
  <span class="stage-arrow">→</span>
  <span class="stage-pill stage-share">Share</span>
</div>

<section id="features">
  <div class="container">
    <div class="section-label">Features</div>
    <h2 class="section-title">Everything a publisher needs to showcase their games</h2>
    <p class="section-sub">From a branded catalog to individual game pages with buy links — GameBoard covers the full product presence without a complicated CMS or a monthly bill.</p>
    <div class="features-grid">
      <div class="feat-card">
        <div class="feat-icon green">🗂️</div>
        <h3>Game catalog</h3>
        <p>A beautiful, filterable catalog page showing all your published games — with cover images, player count, duration, and a summary. Visitors can browse and click through to each game.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon amber">📄</div>
        <h3>Individual game pages</h3>
        <p>Each game gets its own polished page with a hero image, full description, stats, sell sheet link, rules PDF, and a buy button — ready to share with press or players.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon dark">✏️</div>
        <h3>Inline brand editor</h3>
        <p>Edit your company name, logo, banner color, tagline, about text, social links, and footer directly on the live page — click to edit, blur to save. No separate settings panel.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon purple">🖼️</div>
        <h3>Carousel hero images</h3>
        <p>Showcase up to six rotating banner images on your catalog — lifestyle shots, convention photos, or game spreads. Click any slide to swap the image in-place.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon rose">💰</div>
        <h3>Price & buy links</h3>
        <p>Set a price and buy URL per game. The buy button appears automatically on each game page, linking players directly to your store or preferred retailer.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon slate">🔗</div>
        <h3>Shareable public links</h3>
        <p>Generate a secure token link to share your GameBoard publicly — without exposing your Sheet ID. Give it to press, convention attendees, or put it in your email signature.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon green">📱</div>
        <h3>Social media links</h3>
        <p>Add your Instagram, X, Facebook, YouTube, and BoardGameGeek links to your brand footer. Each icon lights up when set and links out in a new tab.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon amber">🤝</div>
        <h3>Collaborator accounts</h3>
        <p>Team members can sign in to edit the catalog and game pages. Collaborators get edit access without touching the Google Sheet directly.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon dark">📊</div>
        <h3>Powered by your Sheet</h3>
        <p>GameBoard reads directly from your Google Sheet — the same one PitchBoard uses. Mark a game Published and it appears in your catalog automatically.</p>
      </div>
    </div>
  </div>
</section>

<section class="shot-section" id="screenshots">
  <div class="container">

    <!-- Screenshot 1: Catalog -->
    <div class="shot-grid" style="margin-bottom:5rem;">
      <div class="shot-text">
        <div class="section-label">Game catalog</div>
        <h2 class="section-title" style="margin-bottom:1rem;">All your games, one beautiful page</h2>
        <p>Your catalog opens with a branded hero banner and a grid of every published game. Each card shows the cover, name, player count, play time, and a one-line summary.</p>
        <ul>
          <li>Rotating carousel with up to 6 brand images</li>
          <li>Game cards with cover image, stats, and summary</li>
          <li>Click any cover to swap the image in-place</li>
          <li>Drag cards to reorder (coming soon)</li>
          <li>About section editable inline</li>
        </ul>
      </div>
      <div class="browser-frame">
        <div class="browser-chrome">
          <div class="browser-dots"><span class="d1"></span><span class="d2"></span><span class="d3"></span></div>
          <div class="browser-url">zapsheets.com/gameboard/…</div>
        </div>
        <div style="background:var(--app-bg);">
          <div class="app-banner">
            <img class="app-banner-logo" src="images/gb_logo.png" alt="Gb" />
            <div>
              <div class="app-banner-name">UP Games</div>
              <div class="app-banner-tag">Common Spaces. Uncommon Games.</div>
            </div>
          </div>
          <div class="app-carousel">
            <div class="app-carousel-img"></div>
            <div class="app-carousel-overlay"></div>
          </div>
          <div class="app-games">
            <div class="app-game-card">
              <div class="app-game-cover">🎲</div>
              <div class="app-game-body">
                <div class="app-game-name">Scurry Up!</div>
                <div class="app-game-meta">👥 2–4 · ⏱ 20 min</div>
                <div class="app-game-badge">Published</div>
              </div>
            </div>
            <div class="app-game-card">
              <div class="app-game-cover">🃏</div>
              <div class="app-game-body">
                <div class="app-game-name">Floristry</div>
                <div class="app-game-meta">👥 2–6 · ⏱ 45 min</div>
                <div class="app-game-badge">Published</div>
              </div>
            </div>
            <div class="app-game-card">
              <div class="app-game-cover">🏔️</div>
              <div class="app-game-body">
                <div class="app-game-name">Summit Run</div>
                <div class="app-game-meta">👥 1–4 · ⏱ 60 min</div>
                <div class="app-game-badge">Published</div>
              </div>
            </div>
            <div class="app-game-card">
              <div class="app-game-cover">🌊</div>
              <div class="app-game-body">
                <div class="app-game-name">Tide & Ruin</div>
                <div class="app-game-meta">👥 2–5 · ⏱ 90 min</div>
                <div class="app-game-badge">Published</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Screenshot 2: Game page -->
    <div class="shot-grid flip" style="margin-bottom:5rem;">
      <div class="shot-text">
        <div class="section-label">Game pages</div>
        <h2 class="section-title" style="margin-bottom:1rem;">Every game gets its own page</h2>
        <p>Click any catalog card to open a dedicated game page — hero image, description, stats, and action buttons. Share the direct link with press, retailers, or convention organizers.</p>
        <ul>
          <li>Hero image with game title overlay</li>
          <li>Full description, player count, and play time</li>
          <li>Price and Buy Now button for direct sales</li>
          <li>Sell sheet, rules PDF, and page URL buttons</li>
          <li>Inline image editing for the hero and cover</li>
        </ul>
      </div>
      <div class="browser-frame">
        <div class="browser-chrome">
          <div class="browser-dots"><span class="d1"></span><span class="d2"></span><span class="d3"></span></div>
          <div class="browser-url">zapsheets.com/gameboard/…/scurry-up</div>
        </div>
        <div style="background:var(--app-bg);">
          <div class="app-banner">
            <img class="app-banner-logo" src="images/gb_logo.png" alt="Gb" />
            <div>
              <div class="app-banner-name">UP Games</div>
            </div>
            <div class="app-banner-nav">
              <div class="app-banner-btn active">← All games</div>
            </div>
          </div>
          <div class="app-hero-img">
            <div style="position:absolute;inset:0;background:linear-gradient(135deg,#1a3a6b 0%,#0d1829 100%);"></div>
            <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(13,24,41,.95) 0%,transparent 60%);"></div>
            <div style="position:relative;z-index:1;">
              <div class="app-hero-title">Scurry Up!</div>
              <div class="app-hero-sub">A fast-paced family race game · UP Games</div>
            </div>
          </div>
          <div class="app-game-info">
            <span class="app-stat-pill">👥 2–4 players</span>
            <span class="app-stat-pill">⏱ 20 min</span>
            <span class="app-stat-pill">🎯 Ages 6+</span>
          </div>
          <div class="app-buy-row">
            <div class="app-price">$24.99</div>
            <div style="display:flex;gap:6px;">
              <div style="font-size:9px;padding:5px 10px;border-radius:999px;border:1px solid rgba(255,255,255,.15);color:rgba(255,255,255,.5);">Sellsheet</div>
              <div class="app-buy-btn">Buy Now</div>
            </div>
          </div>
          <div class="app-desc-area">
            <div class="app-desc-lbl">About the game</div>
            <div class="app-desc-text">Race your crew of woodland critters through the forest before the others! Scurry Up! is a delightfully chaotic race game where every turn brings a new twist. Perfect for families and new gamers.</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Screenshot 3: Brand editor -->
    <div class="shot-grid">
      <div class="shot-text">
        <div class="section-label">Brand editor</div>
        <h2 class="section-title" style="margin-bottom:1rem;">Edit everything right on the page</h2>
        <p>Sign in and your catalog becomes editable. Click the company name to rename it. Click the logo to upload a new one. Adjust social links, footer text, and copyright — all saved instantly to your Sheet.</p>
        <ul>
          <li>Click-to-edit company name, tagline, and about text</li>
          <li>Upload logo and banner images in-place</li>
          <li>Color picker for the banner accent color</li>
          <li>Social icons light up when a link is set</li>
          <li>All changes sync back to the Settings tab in your Sheet</li>
        </ul>
      </div>
      <div class="browser-frame">
        <div class="browser-chrome">
          <div class="browser-dots"><span class="d1"></span><span class="d2"></span><span class="d3"></span></div>
          <div class="browser-url">zapsheets.com/app/…/gameboard</div>
        </div>
        <div class="app-settings">
          <div class="app-settings-hdr">
            <span class="app-settings-title">Brand Settings</span>
            <span class="app-save-btn">Save All</span>
          </div>
          <div class="app-field-group">
            <div class="app-field-label">Company Name</div>
            <div class="app-field-input green-focus">UP Games</div>
          </div>
          <div class="app-field-group">
            <div class="app-field-label">Tagline</div>
            <div class="app-field-input">Common Spaces. Uncommon Games.</div>
          </div>
          <div class="app-field-group">
            <div class="app-field-label">Logo URL</div>
            <div class="app-field-input">https://dropbox.com/…/UP-Games-Logo.png</div>
          </div>
          <div class="app-field-group">
            <div class="app-field-label">About</div>
            <div class="app-field-input" style="height:36px;">Independent publisher of approachable games…</div>
          </div>
          <div class="app-field-group">
            <div class="app-field-label">Social Links</div>
          </div>
          <div class="app-social-row">
            <div class="app-social-btn set">📸 Instagram</div>
            <div class="app-social-btn set">🐦 X</div>
            <div class="app-social-btn set">🎲 BGG</div>
            <div class="app-social-btn">📘 Facebook</div>
          </div>
          <div class="app-field-group">
            <div class="app-field-label">Carousel Images (1–6)</div>
            <div class="app-field-input" style="margin-bottom:4px;">https://dropbox.com/…/hero-1.jpg</div>
            <div class="app-field-input" style="opacity:.5;">https://dropbox.com/…/hero-2.jpg</div>
          </div>
        </div>
      </div>
    </div>

  </div>
</section>

<section class="ownership-section">
  <div class="container">
    <div class="section-label">Data ownership</div>
    <h2 class="section-title">Your catalog lives in your Google Sheet</h2>
    <p class="section-sub">No CMS, no hosting bill, no vendor lock-in. GameBoard reads from a Google Sheet you own — every game, image URL, and brand setting, in a spreadsheet you control.</p>
    <div class="own-grid">
      <div class="own-card">
        <div class="num">$0</div>
        <p>No monthly fee. GameBoard runs on Google infrastructure you already have — no extra hosting required.</p>
      </div>
      <div class="own-card">
        <div class="num">100%</div>
        <p>Your data. Every game title, image, description, and price — in a spreadsheet only you control.</p>
      </div>
      <div class="own-card">
        <div class="num">∞</div>
        <p>Games in your catalog. Publish as many titles as your sheet holds — no tier limits, no upgrade wall.</p>
      </div>
    </div>
  </div>
</section>

<section id="compare">
  <div class="container">
    <div class="section-label">How we compare</div>
    <h2 class="section-title">Built for publishers, not web designers</h2>
    <p class="section-sub">GameBoard gives you a polished product presence without Squarespace's learning curve or a developer's retainer.</p>
    <div class="compare-wrap">
      <table class="compare-table">
        <thead>
          <tr>
            <th>Feature</th>
            <th class="ours">GameBoard</th>
            <th>Squarespace / Wix</th>
            <th>BGG Publisher Page</th>
          </tr>
        </thead>
        <tbody>
          <tr><td>Game catalog page</td><td class="ours"><span class="check">✓</span></td><td><span class="check">✓</span></td><td><span class="check">✓</span></td></tr>
          <tr><td>Per-game detail pages</td><td class="ours"><span class="check">✓</span></td><td><span class="check">✓</span></td><td><span class="check">✓</span></td></tr>
          <tr><td>Inline editing (no CMS)</td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Powered by your Google Sheet</td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Works with PitchBoard</td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Shareable token links</td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Price &amp; buy links</td><td class="ours"><span class="check">✓</span></td><td><span class="check">✓</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Monthly cost</td><td class="ours" style="font-weight:600;color:var(--dark);">Free</td><td>$16–49 / mo</td><td>Free</td></tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<section class="cta-section" id="cta">
  <div class="container">
    <div class="section-label">Ready to launch?</div>
    <h2 class="section-title" style="text-align:center;max-width:540px;margin:0 auto 1rem;">Your games deserve a page as polished as they are</h2>
    <p>Connect your Google Sheet and get a full product catalog and individual game pages — in minutes.</p>
    <a href="gameboard" class="btn-primary" style="font-size:16px;padding:15px 36px;">Get GameBoard</a>
  </div>
</section>

<footer>
  <a href="gameboard">GameBoard</a> &mdash; built for board game publishers &nbsp;·&nbsp; <a href="/">ZapSheets</a>
</footer>

</body>
</html>
