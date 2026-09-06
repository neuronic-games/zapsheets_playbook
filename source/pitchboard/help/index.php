<?php
// Extract app base (everything before /pitchboard/help) so relative links resolve correctly.
$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_self = rtrim($_raw, '/');   // current page path without trailing slash, for anchor hrefs
$_base = preg_replace('#/pitchboard/help/?$#', '/', $_raw);
if (!$_base || $_base === $_raw) { $_base = '/'; }
if (substr($_base, -1) !== '/') { $_base .= '/'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>PitchBoard – Help</title>
<link rel="icon" type="image/png" href="images/pb_icon_180.png" />
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2')  format('woff2'),url('fonts/DINBlack.ttf')  format('truetype'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf') format('truetype'); }

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --navy:   #1a1a2e;
  --amber:  #c8860a;
  --cream:  #f3f0eb;
  --blue:   #A8C8F0;
  --coral:  #FF8A80;
  --green:  #16a34a;
  --red:    #dc2626;
  --purple: #7c3aed;
  --sky:    #0369a1;
  --slate:  #64748b;
  --border: #e5e2dd;
}

html { scroll-behavior: smooth; }

body {
  background: var(--cream);
  font-family: 'DINRegular', Arial, sans-serif;
  color: #1a1a1a;
  line-height: 1.6;
}

/* ── Top bar ──────────────────────────────────────────── */
.top-bar {
  background: var(--navy);
  color: #fff;
  padding: .85rem 1.5rem;
  position: sticky;
  top: 0;
  z-index: 100;
  box-shadow: 0 2px 12px rgba(0,0,0,.25);
}
.top-bar-inner {
  max-width: 860px;
  margin: 0 auto;
  display: flex;
  align-items: center;
  gap: 1rem;
}
.brand-name {
  font-family: 'DINBlack', sans-serif;
  font-size: 1.1rem;
  letter-spacing: .06em;
  text-transform: uppercase;
  line-height: 1;
}
.brand-name .pitch { color: var(--blue); }
.brand-name .board { color: var(--coral); }
.top-bar-divider { opacity: .3; font-size: 1.1rem; }
.top-bar-label {
  font-family: 'DINBlack', sans-serif;
  font-size: .8rem;
  letter-spacing: .1em;
  text-transform: uppercase;
  opacity: .75;
}
.top-bar-back {
  margin-left: auto;
  font-family: 'DINBlack', sans-serif;
  font-size: .7rem;
  letter-spacing: .07em;
  text-transform: uppercase;
  color: rgba(255,255,255,.55);
  text-decoration: none;
  border: 1px solid rgba(255,255,255,.2);
  border-radius: 6px;
  padding: .28rem .65rem;
  transition: color .15s, border-color .15s;
}
.top-bar-back:hover { color: #fff; border-color: rgba(255,255,255,.5); }

/* ── Hero ─────────────────────────────────────────────── */
.hero {
  background: var(--navy);
  color: #fff;
  padding: 4rem 1.5rem 5rem;
  text-align: center;
  position: relative;
  overflow: hidden;
}
.hero::before {
  content: '';
  position: absolute;
  inset: 0;
  background: radial-gradient(ellipse 80% 60% at 50% 110%, rgba(168,200,240,.18) 0%, transparent 70%);
  pointer-events: none;
}
.hero-icon {
  width: 72px;
  height: 72px;
  border-radius: 18px;
  box-shadow: 0 6px 28px rgba(0,0,0,.35);
  margin-bottom: 1.4rem;
}
.hero-title {
  font-family: 'DINBlack', sans-serif;
  font-size: clamp(2rem, 6vw, 3rem);
  letter-spacing: .06em;
  text-transform: uppercase;
  line-height: 1;
  margin-bottom: .7rem;
}
.hero-title .pitch { color: var(--blue); }
.hero-title .board { color: var(--coral); }
.hero-sub {
  font-size: clamp(.95rem, 2.5vw, 1.15rem);
  color: rgba(255,255,255,.65);
  max-width: 520px;
  margin: 0 auto 2rem;
  line-height: 1.55;
}
.hero-chips {
  display: flex;
  flex-wrap: wrap;
  gap: .5rem;
  justify-content: center;
}
.hero-chip {
  background: rgba(255,255,255,.1);
  border: 1px solid rgba(255,255,255,.18);
  border-radius: 999px;
  padding: .3rem .85rem;
  font-family: 'DINBlack', sans-serif;
  font-size: .7rem;
  letter-spacing: .07em;
  text-transform: uppercase;
  color: rgba(255,255,255,.8);
}

/* ── Nav pills ────────────────────────────────────────── */
.toc-wrap {
  background: #fff;
  border-bottom: 1px solid var(--border);
  position: sticky;
  top: 54px;
  z-index: 90;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none;
}
.toc-wrap::-webkit-scrollbar { display: none; }
.toc {
  display: flex;
  gap: .1rem;
  padding: .6rem 1.25rem;
  max-width: 860px;
  margin: 0 auto;
  white-space: nowrap;
}
.toc a {
  font-family: 'DINBlack', sans-serif;
  font-size: .67rem;
  letter-spacing: .07em;
  text-transform: uppercase;
  color: var(--slate);
  text-decoration: none;
  padding: .3rem .6rem;
  border-radius: 6px;
  transition: background .15s, color .15s;
  flex-shrink: 0;
}
.toc a:hover { background: var(--cream); color: var(--navy); }

/* ── Page body ────────────────────────────────────────── */
.page-body {
  max-width: 860px;
  margin: 0 auto;
  padding: 0 1.25rem 5rem;
}

/* ── Section ──────────────────────────────────────────── */
.section {
  padding-top: 3rem;
}
.section-header {
  display: flex;
  align-items: center;
  gap: .75rem;
  margin-bottom: 1.25rem;
  padding-bottom: .75rem;
  border-bottom: 2px solid var(--navy);
}
.section-num {
  font-family: 'DINBlack', sans-serif;
  font-size: .65rem;
  letter-spacing: .12em;
  text-transform: uppercase;
  color: var(--amber);
  background: rgba(200,134,10,.1);
  border: 1px solid rgba(200,134,10,.25);
  border-radius: 6px;
  padding: .2rem .5rem;
  flex-shrink: 0;
}
.section-title {
  font-family: 'DINBlack', sans-serif;
  font-size: 1.3rem;
  letter-spacing: .04em;
  text-transform: uppercase;
  color: var(--navy);
  line-height: 1.1;
}

.section p, .section li {
  font-size: .93rem;
  color: #444;
  line-height: 1.7;
}
.section p + p { margin-top: .75rem; }

/* ── Cards grid ───────────────────────────────────────── */
.cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: .85rem;
  margin-top: 1.25rem;
}
.card {
  background: #fff;
  border-radius: 12px;
  padding: 1.1rem 1.25rem;
  box-shadow: 0 1px 6px rgba(0,0,0,.07);
  border: 1px solid var(--border);
}
.card-icon {
  font-size: 1.5rem;
  margin-bottom: .5rem;
  display: block;
}
.card-title {
  font-family: 'DINBlack', sans-serif;
  font-size: .82rem;
  text-transform: uppercase;
  letter-spacing: .06em;
  color: var(--navy);
  margin-bottom: .35rem;
}
.card p {
  font-size: .82rem;
  color: #666;
  line-height: 1.55;
}

/* ── Step list ────────────────────────────────────────── */
.steps {
  counter-reset: step;
  margin-top: 1.1rem;
  display: flex;
  flex-direction: column;
  gap: .7rem;
}
.step {
  display: flex;
  gap: .9rem;
  align-items: flex-start;
  background: #fff;
  border-radius: 10px;
  padding: .9rem 1.1rem;
  box-shadow: 0 1px 4px rgba(0,0,0,.06);
  border: 1px solid var(--border);
}
.step::before {
  counter-increment: step;
  content: counter(step);
  font-family: 'DINBlack', sans-serif;
  font-size: .8rem;
  background: var(--navy);
  color: #fff;
  border-radius: 50%;
  width: 1.6rem;
  height: 1.6rem;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-top: .05rem;
}
.step-body { flex: 1; min-width: 0; }
.step-title {
  font-family: 'DINBlack', sans-serif;
  font-size: .83rem;
  text-transform: uppercase;
  letter-spacing: .05em;
  color: var(--navy);
  margin-bottom: .2rem;
}
.step p {
  font-size: .83rem;
  color: #555;
  line-height: 1.6;
  margin: 0;
}

/* ── Mock UI elements ─────────────────────────────────── */
.mock-game-card {
  background: var(--navy);
  border-radius: 14px;
  overflow: hidden;
  margin-top: 1.4rem;
  box-shadow: 0 4px 24px rgba(0,0,0,.18);
}
.mgc-header {
  padding: .9rem 1rem .7rem;
  border-bottom: 1px solid rgba(255,255,255,.1);
}
.mgc-title {
  font-family: 'DINBlack', sans-serif;
  font-size: 1rem;
  color: #fff;
  letter-spacing: .03em;
  margin-bottom: .3rem;
}
.mgc-designers {
  font-size: .75rem;
  color: rgba(255,255,255,.5);
}
.mgc-links {
  padding: .6rem 1rem;
  display: flex;
  flex-wrap: wrap;
  gap: .4rem;
  border-bottom: 1px solid rgba(255,255,255,.1);
}
.mgc-pill {
  font-family: 'DINBlack', sans-serif;
  font-size: .62rem;
  letter-spacing: .06em;
  text-transform: uppercase;
  background: rgba(255,255,255,.12);
  color: rgba(255,255,255,.85);
  border: 1px solid rgba(255,255,255,.18);
  border-radius: 999px;
  padding: .22rem .65rem;
}
.mgc-actions {
  padding: .55rem 1rem;
  display: flex;
  gap: .4rem;
  flex-wrap: wrap;
}
.mgc-action {
  font-family: 'DINBlack', sans-serif;
  font-size: .62rem;
  letter-spacing: .06em;
  text-transform: uppercase;
  background: rgba(255,255,255,.08);
  color: rgba(255,255,255,.7);
  border: 1px solid rgba(255,255,255,.15);
  border-radius: 6px;
  padding: .3rem .75rem;
}
.mgc-entries {
  background: rgba(255,255,255,.04);
}
.mgc-entry {
  padding: .6rem 1rem;
  border-bottom: 1px solid rgba(255,255,255,.06);
  display: flex;
  align-items: center;
  gap: .6rem;
  font-size: .78rem;
  color: rgba(255,255,255,.7);
}
.mgc-entry:last-child { border-bottom: none; }
.mgc-pub { flex: 1; font-family: 'DINBlack', sans-serif; font-size: .75rem; color: rgba(255,255,255,.85); }
.mgc-date { font-size: .68rem; color: rgba(255,255,255,.4); white-space: nowrap; }

/* ── Status badges ────────────────────────────────────── */
.badge {
  font-family: 'DINBlack', sans-serif;
  font-size: .6rem;
  letter-spacing: .07em;
  text-transform: uppercase;
  padding: .15rem .5rem;
  border-radius: 4px;
  white-space: nowrap;
}
.badge-pitched    { background: #e2e8f0; color: #475569; }
.badge-interested { background: #dcfce7; color: #166534; }
.badge-passed     { background: #fee2e2; color: #991b1b; }
.badge-signed     { background: #ede9fe; color: #5b21b6; }
.badge-published  { background: #e0f2fe; color: #075985; }
.badge-returned   { background: #fff7ed; color: #c2410c; }

/* ── Status grid ──────────────────────────────────────── */
.status-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: .7rem;
  margin-top: 1.25rem;
}
.status-item {
  background: #fff;
  border-radius: 10px;
  padding: .85rem 1rem;
  border: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  gap: .35rem;
}
.status-item p {
  font-size: .78rem;
  color: #666;
  line-height: 1.5;
  margin: 0;
}

/* ── Tip box ──────────────────────────────────────────── */
.tip {
  background: rgba(200,134,10,.08);
  border: 1px solid rgba(200,134,10,.25);
  border-left: 3px solid var(--amber);
  border-radius: 8px;
  padding: .8rem 1rem;
  margin-top: 1.1rem;
  font-size: .84rem;
  color: #555;
  line-height: 1.6;
}
.tip strong { color: var(--amber); }

/* ── View comparison ──────────────────────────────────── */
.view-tabs {
  display: flex;
  gap: .5rem;
  margin-top: 1.25rem;
  flex-wrap: wrap;
}
.view-tab {
  background: #fff;
  border: 1px solid var(--border);
  border-radius: 10px;
  padding: .85rem 1.15rem;
  flex: 1;
  min-width: 160px;
}
.view-tab-label {
  font-family: 'DINBlack', sans-serif;
  font-size: .78rem;
  text-transform: uppercase;
  letter-spacing: .07em;
  color: var(--navy);
  margin-bottom: .3rem;
}
.view-tab p {
  font-size: .8rem;
  color: #666;
  line-height: 1.5;
  margin: 0;
}

/* ── Mock kanban ──────────────────────────────────────── */
.mock-kb {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: .5rem;
  margin-top: 1.4rem;
  background: var(--navy);
  border-radius: 14px;
  padding: 1rem;
  overflow-x: auto;
  box-shadow: 0 4px 24px rgba(0,0,0,.18);
}
.mock-kb-col {
  background: rgba(255,255,255,.05);
  border-radius: 8px;
  overflow: hidden;
  min-width: 0;
}
.mock-kb-col-head {
  padding: .45rem .65rem;
  font-family: 'DINBlack', sans-serif;
  font-size: .62rem;
  letter-spacing: .1em;
  text-transform: uppercase;
  color: rgba(255,255,255,.45);
  border-bottom: 1px solid rgba(255,255,255,.07);
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.mock-kb-col.signed   .mock-kb-col-head { color: #b197fc; }
.mock-kb-col.published .mock-kb-col-head { color: #7dd3fc; }
.mock-kb-count {
  background: rgba(255,255,255,.1);
  border-radius: 999px;
  padding: .05rem .4rem;
  font-size: .58rem;
  color: rgba(255,255,255,.35);
}
.mock-kb-cards {
  padding: .4rem .35rem;
  display: flex;
  flex-direction: column;
  gap: .3rem;
}
.mock-kb-card {
  background: rgba(255,255,255,.08);
  border: 1px solid rgba(255,255,255,.09);
  border-radius: 5px;
  padding: .4rem .5rem;
}
.mock-kb-card-name {
  font-family: 'DINBlack', sans-serif;
  font-size: .67rem;
  color: rgba(255,255,255,.82);
  letter-spacing: .02em;
  margin-bottom: .1rem;
}
.mock-kb-card-sub { font-size: .58rem; color: rgba(255,255,255,.35); }

@media (max-width: 540px) {
  .mock-kb { grid-template-columns: repeat(2, 1fr); }
}

/* ── Inline code ──────────────────────────────────────── */
code {
  font-family: 'Courier New', monospace;
  font-size: .82em;
  background: rgba(0,0,0,.06);
  border-radius: 4px;
  padding: .1em .35em;
  color: #333;
}

/* ── Footer ───────────────────────────────────────────── */
.footer {
  text-align: center;
  padding: 3rem 1.5rem 2.5rem;
  border-top: 1px solid var(--border);
  margin-top: 2rem;
}
.footer-brand {
  font-family: 'DINBlack', sans-serif;
  font-size: 1.4rem;
  letter-spacing: .08em;
  text-transform: uppercase;
  margin-bottom: .4rem;
}
.footer-brand .pitch { color: var(--blue); }
.footer-brand .board { color: var(--coral); }
.footer p {
  font-size: .82rem;
  color: #999;
}

@media (max-width: 540px) {
  .hero { padding: 2.8rem 1.25rem 3.5rem; }
  .cards { grid-template-columns: 1fr; }
  .status-grid { grid-template-columns: 1fr 1fr; }
  .view-tabs { flex-direction: column; }
  .section-title { font-size: 1.1rem; }
}
</style>
</head>
<body>

<!-- Top bar -->
<div class="top-bar">
  <div class="top-bar-inner">
    <div class="brand-name"><span class="pitch">Pitch</span><span class="board">Board</span></div>
    <span class="top-bar-divider">/</span>
    <span class="top-bar-label">Help</span>
    <a class="top-bar-back" href="pitchboard" id="helpBackBtn">← Back</a>
  </div>
</div>

<!-- Hero -->
<div class="hero">
  <img class="hero-icon" src="images/pb_icon_180.png" alt="PitchBoard" />
  <div class="hero-title"><span class="pitch">Pitch</span><span class="board">Board</span></div>
  <p class="hero-sub">Your game pitching command center. Track every submission, publisher, and status — all from your Google Sheet.</p>
  <div class="hero-chips">
    <span class="hero-chip">Google Sheets–powered</span>
    <span class="hero-chip">Board view</span>
    <span class="hero-chip">Status tracking</span>
    <span class="hero-chip">Email integration</span>
    <span class="hero-chip">Shareable game pages</span>
    <span class="hero-chip">Works on iPad &amp; mobile</span>
  </div>
</div>

<!-- Table of contents -->
<div class="toc-wrap">
  <nav class="toc">
    <a href="<?= $_self ?>#what-is">What is PitchBoard?</a>
    <a href="<?= $_self ?>#getting-started">Getting Started</a>
    <a href="<?= $_self ?>#dashboard">The Dashboard</a>
    <a href="<?= $_self ?>#board">Board View</a>
    <a href="<?= $_self ?>#game-cards">Game Cards</a>
    <a href="<?= $_self ?>#adding-pitches">Adding Pitches</a>
    <a href="<?= $_self ?>#statuses">Status Tracking</a>
    <a href="<?= $_self ?>#view-page">View Page</a>
    <a href="<?= $_self ?>#sharing">Sharing &amp; Importing</a>
    <a href="<?= $_self ?>#email">Email</a>
  </nav>
</div>

<div class="page-body">

  <!-- ── What is PitchBoard? ── -->
  <div class="section" id="what-is">
    <div class="section-header">
      <span class="section-num">01</span>
      <h2 class="section-title">What is PitchBoard?</h2>
    </div>
    <p>PitchBoard is a pitch-tracking dashboard for tabletop game designers. It connects to your Google Sheet — the same spreadsheet where you keep your game info, designers, and publisher contacts — and turns it into a fast, mobile-friendly interface for managing your submission pipeline.</p>
    <p>Every pitch you log is stored back in your sheet. PitchBoard just makes it easier to see what's active, what's stale, and where each game stands.</p>
    <div class="cards">
      <div class="card">
        <span class="card-icon">📋</span>
        <div class="card-title">Lives in your Sheet</div>
        <p>All your data stays in Google Sheets. PitchBoard reads and writes to it directly — no separate database.</p>
      </div>
      <div class="card">
        <span class="card-icon">📱</span>
        <div class="card-title">Works everywhere</div>
        <p>Designed for phone, tablet, and desktop. Add a pitch on the go from your iPhone during a con.</p>
      </div>
      <div class="card">
        <span class="card-icon">🔗</span>
        <div class="card-title">Shareable pages</div>
        <p>Publish a public-facing game page with your sellsheet, rules link, video, and contact info.</p>
      </div>
      <div class="card">
        <span class="card-icon">📧</span>
        <div class="card-title">Email ready</div>
        <p>Open a pre-filled email with your game's title, description, links, and view page — one tap.</p>
      </div>
    </div>
  </div>

  <!-- ── Getting Started ── -->
  <div class="section" id="getting-started">
    <div class="section-header">
      <span class="section-num">02</span>
      <h2 class="section-title">Getting Started</h2>
    </div>
    <p>PitchBoard is powered by a Google Sheet in a specific format. Setup takes about two minutes.</p>
    <div class="steps">
      <div class="step">
        <div class="step-body">
          <div class="step-title">Create a blank Google Sheet</div>
          <p>Open Google Sheets and create a new blank spreadsheet. PitchBoard will set up all the required tabs automatically when you connect it.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Share the sheet with the ZapSheets service account</div>
          <p>Grant editor access to the service account email so PitchBoard can read and write your data. You can copy the address from the setup page.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Paste your Sheet ID on the setup page</div>
          <p>Go to <code>/pitchboard</code> and paste the long ID from your Google Sheet URL. PitchBoard will connect and redirect you to your personal dashboard.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Set your Profile</div>
          <p>Tap the person icon in the top-right corner → <strong>Profile</strong>. Enter your name and email so they're pre-filled when you send pitch emails.</p>
        </div>
      </div>
    </div>
    <div class="tip"><strong>Tip:</strong> Bookmark your dashboard URL (<code>/&lt;your-sheet-id&gt;/pitchboard</code>) or add it to your iPhone home screen as a web app for instant access.</div>
  </div>

  <!-- ── The Dashboard ── -->
  <div class="section" id="dashboard">
    <div class="section-header">
      <span class="section-num">03</span>
      <h2 class="section-title">The Dashboard</h2>
    </div>
    <p>The top bar has four views. Switch between them with the toggle in the upper-right area.</p>
    <div class="view-tabs">
      <div class="view-tab">
        <div class="view-tab-label">📊 Dashboard</div>
        <p>A summary overview — total games, active pitches, and status counts at a glance.</p>
      </div>
      <div class="view-tab">
        <div class="view-tab-label">🎲 Games</div>
        <p>All your games, each with their full pitch history. The default view.</p>
      </div>
      <div class="view-tab">
        <div class="view-tab-label">🏢 Publishers</div>
        <p>Everything grouped by publisher, so you can see your entire relationship with each company at once.</p>
      </div>
      <div class="view-tab">
        <div class="view-tab-label">🗂 Board</div>
        <p>Kanban-style view of all your games organised into Design, Pitch, Signed, and Published columns. Drag cards to move them.</p>
      </div>
    </div>
    <p style="margin-top:1.1rem">Below the view toggle you'll find a <strong>search bar</strong> — it searches game names, publisher names, and contacts simultaneously. Use the <strong>Date / A–Z</strong> toggle to sort by most recent activity or alphabetically.</p>
    <p>The coloured status pills at the top of the feed act as quick filters — tap one to show only games with that status.</p>
    <div class="tip"><strong>Tip:</strong> Use the <strong>Fetch</strong> button (person icon → Fetch) after editing your Google Sheet to pull in the latest data. PitchBoard doesn't auto-sync — you control when it refreshes.</div>
  </div>

  <!-- ── Board View ── -->
  <div class="section" id="board">
    <div class="section-header">
      <span class="section-num">04</span>
      <h2 class="section-title">Board View</h2>
    </div>
    <p>Board view shows all your games as cards arranged in four columns by design stage: <strong>Design</strong> (still in development), <strong>Pitch</strong> (actively submitting), <strong>Signed</strong> (contract in place), and <strong>Published</strong> (out in the world). It gives you a pipeline-level picture of where every game sits at a glance.</p>

    <!-- Mock kanban board -->
    <div class="mock-kb">
      <div class="mock-kb-col">
        <div class="mock-kb-col-head">Design <span class="mock-kb-count">3</span></div>
        <div class="mock-kb-cards">
          <div class="mock-kb-card">
            <div class="mock-kb-card-name">Ironveil Pass</div>
            <div class="mock-kb-card-sub">Started Apr 2025</div>
          </div>
          <div class="mock-kb-card">
            <div class="mock-kb-card-name">The Glass Engine</div>
            <div class="mock-kb-card-sub">Started Jan 2026</div>
          </div>
          <div class="mock-kb-card">
            <div class="mock-kb-card-name">Nocturne Market</div>
            <div class="mock-kb-card-sub">Started Jun 2026</div>
          </div>
        </div>
      </div>
      <div class="mock-kb-col">
        <div class="mock-kb-col-head">Pitch <span class="mock-kb-count">2</span></div>
        <div class="mock-kb-cards">
          <div class="mock-kb-card">
            <div class="mock-kb-card-name">Stormveil</div>
            <div class="mock-kb-card-sub">3 active pitches</div>
          </div>
          <div class="mock-kb-card">
            <div class="mock-kb-card-name">Deepwater Run</div>
            <div class="mock-kb-card-sub">1 active pitch</div>
          </div>
        </div>
      </div>
      <div class="mock-kb-col signed">
        <div class="mock-kb-col-head">Signed <span class="mock-kb-count">1</span></div>
        <div class="mock-kb-cards">
          <div class="mock-kb-card">
            <div class="mock-kb-card-name">Thornwood</div>
            <div class="mock-kb-card-sub">Signed Mar 2026</div>
          </div>
        </div>
      </div>
      <div class="mock-kb-col published">
        <div class="mock-kb-col-head">Published <span class="mock-kb-count">1</span></div>
        <div class="mock-kb-cards">
          <div class="mock-kb-card">
            <div class="mock-kb-card-name">The Cartographer</div>
            <div class="mock-kb-card-sub">Published Jan 2026</div>
          </div>
        </div>
      </div>
    </div>

    <div class="cards" style="margin-top:1.25rem">
      <div class="card">
        <span class="card-icon">↔️</span>
        <div class="card-title">Drag to move</div>
        <p>Drag a card to a different column to change the game's stage. The sheet updates automatically — no manual editing needed.</p>
      </div>
      <div class="card">
        <span class="card-icon">📅</span>
        <div class="card-title">Date auto-fill</div>
        <p>Moving a game to <strong>Signed</strong> or <strong>Published</strong> fills the corresponding date in your sheet if it's blank.</p>
      </div>
      <div class="card">
        <span class="card-icon">🔽</span>
        <div class="card-title">Card expansion</div>
        <p>Tap a card to expand it and see Started, Signed, and Published dates inline, plus a View action.</p>
      </div>
      <div class="card">
        <span class="card-icon">👁</span>
        <div class="card-title">View action</div>
        <p>The expanded card has a View button that jumps straight to that game in the Games view with the card already open.</p>
      </div>
      <div class="card">
        <span class="card-icon">📦</span>
        <div class="card-title">Shelf / Unshelf</div>
        <p>Hide a game from the board without deleting it using the Shelf button. Shelved games appear in a collapsible section at the bottom of the board — tap to restore.</p>
      </div>
      <div class="card">
        <span class="card-icon">📱</span>
        <div class="card-title">Touch drag on iPad</div>
        <p>Drag-and-drop works on iPad and iPhone too. Press and hold a card, then drag it to the target column.</p>
      </div>
    </div>
    <div class="tip"><strong>Stage vs. status:</strong> the Board column represents a game's overall design stage (Design → Pitch → Signed → Published), not the status of individual pitch entries. A game in the Pitch column can have pitches with any status — Pitched, Interested, Passed, and so on.</div>
  </div>

  <!-- ── Game Cards ── -->
  <div class="section" id="game-cards">
    <div class="section-header">
      <span class="section-num">05</span>
      <h2 class="section-title">Game Cards</h2>
    </div>
    <p>Each game gets a card in the Games view. Here's what you're looking at:</p>

    <!-- Mock card -->
    <div class="mock-game-card">
      <div class="mgc-header">
        <div class="mgc-title">Thornwick Abbey</div>
        <div class="mgc-designers">Simon · Garfunkel</div>
      </div>
      <div class="mgc-links">
        <span class="mgc-pill">Rules</span>
        <span class="mgc-pill">Play</span>
        <span class="mgc-pill">Print</span>
        <span class="mgc-pill">Video</span>
        <span class="mgc-pill">Info</span>
      </div>
      <div class="mgc-actions">
        <span class="mgc-action">+ New Pitch</span>
        <span class="mgc-action">View Page</span>
      </div>
      <div class="mgc-entries">
        <div class="mgc-entry">
          <span class="mgc-pub">Stonemaier Games</span>
          <span class="badge badge-interested">INT</span>
          <span class="mgc-date">Jun 2026</span>
        </div>
        <div class="mgc-entry">
          <span class="mgc-pub">Osprey Games</span>
          <span class="badge badge-pitched">Pitched</span>
          <span class="mgc-date">May 2026</span>
        </div>
        <div class="mgc-entry">
          <span class="mgc-pub">Bezier Games</span>
          <span class="badge badge-passed">Passed</span>
          <span class="mgc-date">Mar 2026</span>
        </div>
      </div>
    </div>

    <div class="cards" style="margin-top:1.25rem">
      <div class="card">
        <div class="card-title">Quick-link pills</div>
        <p>Tap <strong>Rules, Play, Print, Video</strong>, or <strong>Sellsheet</strong> to open those files directly. <strong>Info</strong> opens the game's published view page.</p>
      </div>
      <div class="card">
        <div class="card-title">+ New Pitch</div>
        <p>Opens the Add Entry dialog pre-filled with this game's name so you can log a new submission fast.</p>
      </div>
      <div class="card">
        <div class="card-title">View Page button</div>
        <p>Publishes a shareable game page with your sellsheet, links, and description. More in section 07.</p>
      </div>
      <div class="card">
        <div class="card-title">Pitch rows</div>
        <p>Each row is one pitch entry: publisher name, status badge, and date. Tap a row to edit it.</p>
      </div>
    </div>
    <div class="tip"><strong>Red date on a pitch row?</strong> That means the entry is over 6 months old with no follow-up — time to check in or close it out.</div>
  </div>

  <!-- ── Adding Pitches ── -->
  <div class="section" id="adding-pitches">
    <div class="section-header">
      <span class="section-num">06</span>
      <h2 class="section-title">Adding Pitches</h2>
    </div>
    <p>Tap <strong>+ New Pitch</strong> on a game card (or <strong>New Pitch</strong> on a publisher in Publishers view) to open the Add Entry dialog.</p>
    <div class="steps">
      <div class="step">
        <div class="step-body">
          <div class="step-title">Choose Publisher &amp; Contact</div>
          <p>Type to search your existing publisher list, or enter a new name. The Contact field is optional but useful when you have a specific editor's name.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Set the Date &amp; Status</div>
          <p>Date defaults to today. Status defaults to <strong>Pitched</strong>. Change either if you're logging something that already happened.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Add an Event (optional)</div>
          <p>Note where the pitch happened — a convention, an email intro, a cold call. Helpful context later.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Add Notes (optional)</div>
          <p>Anything you want to remember — who you talked to, what they said, next steps.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Send Email or just Add</div>
          <p>Tap <strong>✉ Send Email</strong> to open a pre-filled email to the publisher before submitting, or tap <strong>Add</strong> to save the entry immediately.</p>
        </div>
      </div>
    </div>
    <div class="tip"><strong>Editing an entry:</strong> tap any pitch row on a game card to open the edit dialog. You can update the status, date, notes, or delete the entry entirely.</div>
    <div class="tip" style="margin-top:.6rem"><strong>Unsaved changes protection:</strong> if you click outside a dialog or press Escape while there are unsaved changes, the dialog shakes instead of closing. This prevents accidental data loss — use the Cancel button to discard intentionally.</div>
  </div>

  <!-- ── Status Tracking ── -->
  <div class="section" id="statuses">
    <div class="section-header">
      <span class="section-num">07</span>
      <h2 class="section-title">Status Tracking</h2>
    </div>
    <p>Every pitch entry has a status. The status controls the badge color on the card and the filter pills at the top of the feed.</p>
    <div class="status-grid">
      <div class="status-item">
        <span class="badge badge-pitched">Pitched</span>
        <p>Submitted and awaiting a response. The default.</p>
      </div>
      <div class="status-item">
        <span class="badge badge-interested">INT</span>
        <p>Publisher has expressed interest — keep the conversation going.</p>
      </div>
      <div class="status-item">
        <span class="badge badge-passed">Passed</span>
        <p>Publisher declined. The pitch is closed for this publisher.</p>
      </div>
      <div class="status-item">
        <span class="badge badge-returned">Returned</span>
        <p>Game was under contract but the publisher returned rights to you.</p>
      </div>
      <div class="status-item">
        <span class="badge badge-signed">Signed</span>
        <p>Contract in place — congratulations! The game card gets a signed indicator.</p>
      </div>
      <div class="status-item">
        <span class="badge badge-published">Published</span>
        <p>The game is out in the world. Full circle.</p>
      </div>
    </div>
    <div class="tip"><strong>Signed vs. Returned:</strong> if a game was signed but the publisher later returns it, log a new entry with status <strong>Returned</strong>. PitchBoard checks the latest status per publisher to determine whether a game is currently signed.</div>
  </div>

  <!-- ── View Page ── -->
  <div class="section" id="view-page">
    <div class="section-header">
      <span class="section-num">08</span>
      <h2 class="section-title">View Page</h2>
    </div>
    <p>Every game can have a shareable public page at <code>/&lt;sheet-id&gt;/view/?game=GameName</code>. This is what you send to publishers so they can read your pitch, watch your video, download your rules, and open your sellsheet — all in one place.</p>
    <div class="steps">
      <div class="step">
        <div class="step-body">
          <div class="step-title">Tap the View Page button on a game card</div>
          <p>This opens the View Page publish dialog, which shows a summary of the game's metadata pulled from your sheet.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Tap "View Page Fetch" to publish</div>
          <p>PitchBoard exports the game's data, caches any images, and deploys the view page to the server. The log shows each step — green means success, red means something needs attention.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Open or share the page</div>
          <p>Use the <strong>Open View Page</strong> button to preview it, or copy the URL from your browser and send it to a publisher. The <strong>Info</strong> pill on the game card also links directly to it.</p>
        </div>
      </div>
    </div>
    <div class="tip"><strong>Keep it fresh:</strong> run View Page Fetch again after updating your game's description, cover image, or links in the Google Sheet.</div>
  </div>

  <!-- ── Sharing & Importing ── -->
  <div class="section" id="sharing">
    <div class="section-header">
      <span class="section-num">09</span>
      <h2 class="section-title">Sharing &amp; Importing</h2>
    </div>
    <p>If you co-design games, you can give collaborators access to add and edit pitches in your PitchBoard — without sharing your Google Sheet directly.</p>
    <div class="cards">
      <div class="card">
        <span class="card-icon">🔗</span>
        <div class="card-title">Share access</div>
        <p>Person icon → <strong>Share</strong>. Copy the generated link and send it to your co-designer. Anyone with the link can add and edit pitch entries on your behalf.</p>
      </div>
    </div>
    <div class="tip"><strong>What collaborators can do:</strong> add new pitches, edit existing entries (status, date, notes), and see your full pitch history. Your Google Sheet remains private — only PitchBoard data is accessible through the share link.</div>
  </div>

  <!-- ── Email ── -->
  <div class="section" id="email">
    <div class="section-header">
      <span class="section-num">10</span>
      <h2 class="section-title">Email Integration</h2>
    </div>
    <p>PitchBoard can open your email client with a pre-filled pitch email — no copy-pasting links one by one.</p>
    <div class="steps">
      <div class="step">
        <div class="step-body">
          <div class="step-title">From the Add Entry dialog</div>
          <p>Tap <strong>✉ Send Email</strong>. PitchBoard opens a draft addressed to the selected publisher's contact, with the game title as the subject, and a body that includes the game description, designer credits, and links to the view page, sellsheet, rules, video, and play link.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">From a publisher sub-header</div>
          <p>In the Publishers view, tap the <strong>✉ Email</strong> button next to a contact name to send an email about a specific game they're already reviewing.</p>
        </div>
      </div>
    </div>
    <div class="tip"><strong>Set your Profile first.</strong> Person icon → Profile. Your name and email are included in the email from line so publishers know who they're hearing from.</div>
  </div>

</div><!-- /page-body -->

<script>
// Use history.back() when navigated here from within the app.
// Falls back to the pitchboard setup page href if loaded directly.
(function() {
  var btn = document.getElementById('helpBackBtn');
  if (document.referrer && document.referrer.indexOf(window.location.origin) === 0) {
    btn.addEventListener('click', function(e) { e.preventDefault(); history.back(); });
  }
})();
</script>

<!-- Footer -->
<div class="footer">
  <div class="footer-brand"><span class="pitch">Pitch</span><span class="board">Board</span></div>
  <p>Part of the ZapSheets toolkit for game designers.</p>
</div>

</body>
</html>
