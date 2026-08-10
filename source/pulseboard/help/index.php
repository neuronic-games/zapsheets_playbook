<?php
$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_self = rtrim($_raw, '/');
$_base = preg_replace('#/pulseboard/help/?$#', '/', $_raw);
if (!$_base || $_base === $_raw) { $_base = '/'; }
if (substr($_base, -1) !== '/') { $_base .= '/'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>PulseBoard – Help</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg width='180' height='180' viewBox='0 0 180 180' fill='none' xmlns='http://www.w3.org/2000/svg'><rect width='180' height='180' rx='36' fill='%231a1a1a'/><polyline points='8,90 42,90 52,38 68,138 82,58 98,90 132,90' stroke='%23ef4444' stroke-width='10' stroke-linecap='round' stroke-linejoin='round'/><line x1='132' y1='90' x2='148' y2='90' stroke='%23ef4444' stroke-width='10' stroke-linecap='round'/><circle cx='164' cy='90' r='16' fill='%2316a34a'/></svg>" />
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2')  format('woff2'),url('fonts/DINBlack.ttf')  format('truetype'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf') format('truetype'); }

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --dark:   #0f1923;
  --navy:   #1a1a1a;
  --red:    #ef4444;
  --green:  #16a34a;
  --green2: #22c55e;
  --cream:  #f3f0eb;
  --border: #e5e2dd;
  --slate:  #64748b;
  --amber:  #c8860a;
}

html { scroll-behavior: smooth; }
body { background: var(--cream); font-family: 'DINRegular', Arial, sans-serif; color: #1a1a1a; line-height: 1.6; }

/* ── Top bar ──────────────────────────────────────────── */
.top-bar {
  background: var(--navy); color: #fff;
  padding: .85rem 1.5rem;
  position: sticky; top: 0; z-index: 100;
  box-shadow: 0 2px 12px rgba(0,0,0,.25);
}
.top-bar-inner {
  max-width: 860px; margin: 0 auto;
  display: flex; align-items: center; gap: 1rem;
}
.brand-name {
  font-family: 'DINBlack', sans-serif;
  font-size: 1.1rem; letter-spacing: .04em; line-height: 1;
}
.brand-name .pb-pulse { color: var(--red); }
.brand-name .pb-board { color: var(--green2); }
.top-bar-divider { opacity: .3; font-size: 1.1rem; }
.top-bar-label {
  font-family: 'DINBlack', sans-serif;
  font-size: .8rem; letter-spacing: .1em; text-transform: uppercase; opacity: .75;
}
.top-bar-back {
  margin-left: auto;
  font-family: 'DINBlack', sans-serif; font-size: .7rem;
  letter-spacing: .07em; text-transform: uppercase;
  color: rgba(255,255,255,.55); text-decoration: none;
  border: 1px solid rgba(255,255,255,.2); border-radius: 6px;
  padding: .28rem .65rem; transition: color .15s, border-color .15s;
}
.top-bar-back:hover { color: #fff; border-color: rgba(255,255,255,.5); }

/* ── Hero ─────────────────────────────────────────────── */
.hero {
  background: var(--dark); color: #fff;
  padding: 4rem 1.5rem 5rem;
  text-align: center; position: relative; overflow: hidden;
}
.hero::before {
  content: '';
  position: absolute; inset: 0;
  background: radial-gradient(ellipse 80% 60% at 50% 110%, rgba(22,163,74,.18) 0%, transparent 70%);
  pointer-events: none;
}
.hero-icon {
  width: 80px; height: 80px; border-radius: 20px;
  background: #1a1a1a; display: inline-flex;
  align-items: center; justify-content: center;
  margin-bottom: 1.4rem;
  box-shadow: 0 6px 28px rgba(0,0,0,.45);
}
.hero-icon svg { width: 48px; height: 48px; }
.hero-title {
  font-family: 'DINBlack', sans-serif;
  font-size: clamp(2rem, 6vw, 3rem);
  letter-spacing: .05em; line-height: 1; margin-bottom: .7rem;
}
.hero-title .pb-pulse { color: var(--red); }
.hero-title .pb-board { color: var(--green2); }
.hero-sub {
  font-size: clamp(.95rem, 2.5vw, 1.15rem);
  color: rgba(255,255,255,.65); max-width: 540px;
  margin: 0 auto 2rem; line-height: 1.55;
}
.hero-chips { display: flex; flex-wrap: wrap; gap: .5rem; justify-content: center; }
.hero-chip {
  background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18);
  border-radius: 999px; padding: .3rem .85rem;
  font-family: 'DINBlack', sans-serif; font-size: .7rem;
  letter-spacing: .07em; text-transform: uppercase; color: rgba(255,255,255,.8);
}

/* ── Nav pills ────────────────────────────────────────── */
.toc-wrap {
  background: #fff; border-bottom: 1px solid var(--border);
  position: sticky; top: 54px; z-index: 90;
  overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none;
}
.toc-wrap::-webkit-scrollbar { display: none; }
.toc {
  display: flex; gap: .1rem; padding: .6rem 1.25rem;
  max-width: 860px; margin: 0 auto; white-space: nowrap;
}
.toc a {
  font-family: 'DINBlack', sans-serif; font-size: .67rem;
  letter-spacing: .07em; text-transform: uppercase;
  color: var(--slate); text-decoration: none;
  padding: .3rem .6rem; border-radius: 6px;
  transition: background .15s, color .15s; flex-shrink: 0;
}
.toc a:hover { background: var(--cream); color: #1a1a1a; }

/* ── Page body ────────────────────────────────────────── */
.page-body { max-width: 860px; margin: 0 auto; padding: 0 1.25rem 5rem; }

/* ── Section ──────────────────────────────────────────── */
.section { padding-top: 3rem; }
.section-header {
  display: flex; align-items: center; gap: .75rem;
  margin-bottom: 1.25rem; padding-bottom: .75rem;
  border-bottom: 2px solid var(--navy);
}
.section-num {
  font-family: 'DINBlack', sans-serif; font-size: .65rem;
  letter-spacing: .12em; text-transform: uppercase;
  color: var(--green); background: rgba(22,163,74,.1);
  border: 1px solid rgba(22,163,74,.25);
  border-radius: 6px; padding: .2rem .5rem; flex-shrink: 0;
}
.section-title {
  font-family: 'DINBlack', sans-serif; font-size: 1.3rem;
  letter-spacing: .04em; text-transform: uppercase;
  color: var(--navy); line-height: 1.1;
}
.section p, .section li { font-size: .93rem; color: #444; line-height: 1.7; }
.section p + p { margin-top: .75rem; }

/* ── Feature cards ────────────────────────────────────── */
.cards {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: .85rem; margin-top: 1.25rem;
}
.card {
  background: #fff; border-radius: 12px; padding: 1.1rem 1.25rem;
  box-shadow: 0 1px 6px rgba(0,0,0,.07); border: 1px solid var(--border);
}
.card-icon { font-size: 1.5rem; margin-bottom: .5rem; display: block; }
.card-title {
  font-family: 'DINBlack', sans-serif; font-size: .82rem;
  text-transform: uppercase; letter-spacing: .06em;
  color: var(--navy); margin-bottom: .35rem;
}
.card p { font-size: .82rem; color: #666; line-height: 1.55; }

/* ── Step list ────────────────────────────────────────── */
.steps {
  counter-reset: step; margin-top: 1.1rem;
  display: flex; flex-direction: column; gap: .7rem;
}
.step {
  display: flex; gap: .9rem; align-items: flex-start;
  background: #fff; border-radius: 10px; padding: .9rem 1.1rem;
  box-shadow: 0 1px 4px rgba(0,0,0,.06); border: 1px solid var(--border);
}
.step::before {
  counter-increment: step; content: counter(step);
  font-family: 'DINBlack', sans-serif; font-size: .8rem;
  background: var(--navy); color: #fff; border-radius: 50%;
  width: 1.6rem; height: 1.6rem; display: flex;
  align-items: center; justify-content: center;
  flex-shrink: 0; margin-top: .05rem;
}
.step-body { flex: 1; min-width: 0; }
.step-title {
  font-family: 'DINBlack', sans-serif; font-size: .83rem;
  text-transform: uppercase; letter-spacing: .05em;
  color: var(--navy); margin-bottom: .2rem;
}
.step p { font-size: .83rem; color: #555; line-height: 1.6; margin: 0; }

/* ── Mock PulseBoard card ─────────────────────────────── */
.mock-pb {
  background: #0f1923; border-radius: 14px; overflow: hidden;
  margin-top: 1.4rem; box-shadow: 0 4px 24px rgba(0,0,0,.25);
  border: 1px solid rgba(255,255,255,.08); max-width: 420px;
}
.mock-pb-header {
  background: rgba(22,163,74,.22); display: flex; align-items: center; gap: .6rem;
  padding: .65rem .85rem;
}
.mock-dot { width: 9px; height: 9px; border-radius: 50%; background: #16a34a; box-shadow: 0 0 6px rgba(22,163,74,.7); flex-shrink: 0; }
.mock-pb-name { font-family: 'DINBlack', sans-serif; font-size: .88rem; color: #e0e6f0; flex: 1; }
.mock-ok { font-family: 'DINBlack', sans-serif; font-size: .62rem; text-transform: uppercase; letter-spacing: .05em; color: #4ade80; background: rgba(22,163,74,.2); border-radius: 4px; padding: .1rem .4rem; }
.mock-pb-body { padding: .65rem .85rem; }
.mock-pills { display: flex; gap: .5rem; justify-content: space-between; margin-bottom: .55rem; }
.mock-pill {
  background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.08);
  border-radius: 5px; padding: .22rem .55rem; font-size: .72rem; white-space: nowrap;
}
.mock-pill-label { font-family: 'DINBlack', sans-serif; font-size: .58rem; color: #3f4f5e; text-transform: uppercase; letter-spacing: .05em; }
.mock-pill-val { color: #94a3b8; }
.mock-bar-track { height: 3px; background: rgba(255,255,255,.06); margin-top: 2px; }
.mock-bar-fill { height: 3px; background: #16a34a; }
.mock-pb-footer { display: flex; justify-content: space-between; align-items: center; font-size: .68rem; color: #3f4f5e; }
.mock-crash { font-family: 'DINBlack', sans-serif; font-size: .62rem; background: rgba(22,163,74,.08); color: #2d6a40; border-radius: 4px; padding: .1rem .45rem; }

/* ── Callout ──────────────────────────────────────────── */
.callout {
  background: rgba(22,163,74,.07); border-left: 3px solid var(--green);
  border-radius: 0 8px 8px 0; padding: .8rem 1rem; margin-top: 1rem;
  font-size: .88rem; color: #555;
}
.callout strong { color: var(--green); }

.callout-warn {
  background: rgba(200,134,10,.07); border-left: 3px solid var(--amber);
  border-radius: 0 8px 8px 0; padding: .8rem 1rem; margin-top: 1rem;
  font-size: .88rem; color: #555;
}
.callout-warn strong { color: var(--amber); }

/* ── Code block ───────────────────────────────────────── */
code {
  background: #f0ede8; border: 1px solid #ddd; border-radius: 4px;
  padding: .1em .35em; font-size: .88em; color: #555;
}
.code-block {
  background: #0d1117; border-radius: 8px; padding: .85rem 1rem;
  margin-top: .85rem; font-family: monospace; font-size: .8rem;
  line-height: 1.7; color: #94a3b8; overflow-x: auto;
}
.code-block .key   { color: #4ade80; }
.code-block .val   { color: #93c5fd; }
.code-block .cmt   { color: #3f4f5e; }

/* ── Field table ──────────────────────────────────────── */
.field-table { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: .85rem; }
.field-table th {
  font-family: 'DINBlack', sans-serif; font-size: .65rem;
  text-transform: uppercase; letter-spacing: .07em;
  color: var(--slate); text-align: left; padding: .45rem .75rem;
  background: #f9f7f4; border-bottom: 2px solid var(--border);
}
.field-table td { padding: .5rem .75rem; border-bottom: 1px solid var(--border); color: #444; vertical-align: top; }
.field-table tr:last-child td { border-bottom: none; }
.field-table td:first-child { font-family: monospace; color: #555; font-size: .82rem; white-space: nowrap; }

/* ── Footer ───────────────────────────────────────────── */
.footer {
  background: var(--navy); color: rgba(255,255,255,.5);
  padding: 2.5rem 1.5rem; text-align: center; margin-top: 4rem;
}
.footer-brand {
  font-family: 'DINBlack', sans-serif; font-size: 1.1rem;
  letter-spacing: .04em; margin-bottom: .5rem;
}
.footer-brand .pb-pulse { color: var(--red); }
.footer-brand .pb-board { color: var(--green2); }
.footer p { font-size: .82rem; color: #999; }

@media (max-width: 540px) {
  .hero { padding: 2.8rem 1.25rem 3.5rem; }
  .cards { grid-template-columns: 1fr; }
  .section-title { font-size: 1.1rem; }
}
</style>
</head>
<body>

<!-- Top bar -->
<div class="top-bar">
  <div class="top-bar-inner">
    <div class="brand-name"><span class="pb-pulse">Pulse</span><span class="pb-board">Board</span></div>
    <span class="top-bar-divider">/</span>
    <span class="top-bar-label">Help</span>
    <a class="top-bar-back" href="pulseboard">← Setup</a>
  </div>
</div>

<!-- Hero -->
<div class="hero">
  <div class="hero-icon">
    <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
      <polyline points="2,24 11,24 14,10 18,36 22,16 26,24 35,24" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
      <line x1="35" y1="24" x2="38" y2="24" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/>
      <circle cx="42" cy="24" r="4.5" fill="#22c55e"/>
    </svg>
  </div>
  <div class="hero-title"><span class="pb-pulse">Pulse</span><span class="pb-board">Board</span></div>
  <p class="hero-sub">Monitor every machine in your facility in real time — memory, disk, uptime, crashes, and more, straight into your Google Sheet.</p>
  <div class="hero-chips">
    <span class="hero-chip">Real-time heartbeats</span>
    <span class="hero-chip">Google Sheets–powered</span>
    <span class="hero-chip">Crash detection</span>
    <span class="hero-chip">Windows · Linux · macOS</span>
    <span class="hero-chip">TeamViewer ID</span>
    <span class="hero-chip">Editable notes</span>
  </div>
</div>

<!-- Table of contents -->
<div class="toc-wrap">
  <nav class="toc">
    <a href="<?= $_self ?>#what-is">What is PulseBoard?</a>
    <a href="<?= $_self ?>#getting-started">Getting Started</a>
    <a href="<?= $_self ?>#dashboard">The Dashboard</a>
    <a href="<?= $_self ?>#agent">The Python Agent</a>
    <a href="<?= $_self ?>#crash-tracking">Crash Tracking</a>
    <a href="<?= $_self ?>#notes-tv">Notes &amp; TeamViewer</a>
  </nav>
</div>

<div class="page-body">

  <!-- ── What is PulseBoard? ── -->
  <div class="section" id="what-is">
    <div class="section-header">
      <span class="section-num">01</span>
      <h2 class="section-title">What is PulseBoard?</h2>
    </div>
    <p>PulseBoard is a lightweight machine monitoring system. A small Python agent runs on each machine and sends a heartbeat — called a <strong>pulse</strong> — to your Google Sheet every few seconds. The PulseBoard dashboard reads that data and shows you the live status of every machine at a glance.</p>
    <p>It was designed for kiosk and exhibit environments where you need to know at a glance which machines are online, how much memory and disk they have left, how long they've been running, and whether any crashes have occurred — without logging in to each machine individually.</p>
    <div class="cards">
      <div class="card">
        <span class="card-icon">💓</span>
        <div class="card-title">Live heartbeats</div>
        <p>Each machine sends a pulse every 5 seconds when its app is running. The dashboard shows the last-seen timestamp so you can spot a stale machine instantly.</p>
      </div>
      <div class="card">
        <span class="card-icon">📊</span>
        <div class="card-title">System stats</div>
        <p>Memory free/total, disk free/total, uptime, OS version, hostname, and IP address — all collected automatically from the machine.</p>
      </div>
      <div class="card">
        <span class="card-icon">💥</span>
        <div class="card-title">Crash detection</div>
        <p>When <code>guard.py</code> restarts a crashed app it logs the event. The next pulse reports the crash count and times to the sheet.</p>
      </div>
      <div class="card">
        <span class="card-icon">🖥️</span>
        <div class="card-title">Cross-platform</div>
        <p>The Python agent runs on Windows, Linux, and macOS. All platform-specific calls are handled transparently — one codebase for every machine type.</p>
      </div>
      <div class="card">
        <span class="card-icon">📋</span>
        <div class="card-title">Google Sheets backend</div>
        <p>All data lives in your own Google Sheet. Each group of machines gets its own tab. Data is yours — export, filter, or chart it however you like.</p>
      </div>
      <div class="card">
        <span class="card-icon">📝</span>
        <div class="card-title">Editable notes</div>
        <p>Add a note to any machine card directly from the dashboard. Notes are saved to the sheet and persist across refreshes.</p>
      </div>
    </div>
  </div>

  <!-- ── Getting Started ── -->
  <div class="section" id="getting-started">
    <div class="section-header">
      <span class="section-num">02</span>
      <h2 class="section-title">Getting Started</h2>
    </div>
    <p>Setup takes about two minutes. You need a Google Sheet, the PulseBoard service account access, and the Python agent running on at least one machine.</p>
    <div class="steps">
      <div class="step">
        <div class="step-body">
          <div class="step-title">Create a Google Sheet</div>
          <p>Open Google Sheets and create a new blank spreadsheet. You can use one sheet per facility or one sheet per machine group — tabs separate the machines.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Share it with the service account</div>
          <p>Click <strong>Share</strong> and add <code>editor@zapsheets-480701.iam.gserviceaccount.com</code> as an <strong>Editor</strong>. This is how PulseBoard reads and writes machine data.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Connect on the setup page</div>
          <p>Go to <code>/pulseboard</code>, paste your Sheet URL or ID, and click <strong>Set Up PulseBoard</strong>. PulseBoard reads your existing tabs (or notes that none exist yet) and redirects you to the dashboard.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Configure and run the Python agent</div>
          <p>Copy <code>neuron-scripts</code> to each machine. Edit <code>settings.py</code> with your Sheet ID, tab name (machine group), and exhibit name. Then run <code>launch.cmd</code> (Windows) or <code>python report_status.py</code> and <code>python guard.py</code> directly on Linux/macOS.</p>
        </div>
      </div>
    </div>
    <div class="callout"><strong>Tip:</strong> Tabs are created automatically the first time a pulse arrives from a machine. You don't need to pre-create them in the sheet.</div>
  </div>

  <!-- ── The Dashboard ── -->
  <div class="section" id="dashboard">
    <div class="section-header">
      <span class="section-num">03</span>
      <h2 class="section-title">The Dashboard</h2>
    </div>
    <p>After setup, PulseBoard opens at <code>/{sheet-id}/pulseboard</code>. Machines are grouped by their sheet tab and displayed as cards. The page auto-refreshes when you click <strong>Fetch</strong> from the menu.</p>

    <div class="mock-pb">
      <div class="mock-pb-header">
        <div class="mock-dot"></div>
        <div class="mock-pb-name">EXHIBIT-1</div>
        <div class="mock-ok">OK</div>
      </div>
      <div class="mock-pb-body">
        <div class="mock-pills">
          <div class="mock-pill">
            <div style="display:flex;align-items:center;gap:.3rem">
              <span class="mock-pill-label">Mem</span>
              <span class="mock-pill-val">10.2/16 GB</span>
            </div>
            <div class="mock-bar-track"><div class="mock-bar-fill" style="width:36%"></div></div>
          </div>
          <div class="mock-pill">
            <div style="display:flex;align-items:center;gap:.3rem">
              <span class="mock-pill-label">Disk</span>
              <span class="mock-pill-val">440/488 GB</span>
            </div>
            <div class="mock-bar-track"><div class="mock-bar-fill" style="width:10%;background:#16a34a"></div></div>
          </div>
          <div class="mock-pill">
            <div style="display:flex;align-items:center;gap:.3rem">
              <span class="mock-pill-label">Up</span>
              <span class="mock-pill-val">18:46:54 <span style="opacity:.4;font-size:.85em">HRS</span></span>
            </div>
          </div>
        </div>
        <div class="mock-pb-footer">
          <span>Last seen: 08/10/2026 10:17:04</span>
          <span class="mock-crash">0 crashes</span>
        </div>
      </div>
    </div>

    <p style="margin-top:1.25rem">Each card shows the machine's <strong>status indicator</strong> (green = online, red = offline, grey = unknown), its <strong>exhibit name</strong>, and three stat pills — memory, disk, and uptime. The coloured bar under each pill turns amber above 60% usage and red above 80%.</p>
    <p>The footer shows the <strong>last seen</strong> timestamp and a <strong>crash count</strong> badge. Click a card to expand it and see the full detail — host, IP, OS, last reboot, TeamViewer ID, and a notes field. Clicking another card collapses the open one.</p>
    <p>Use the <strong>search bar</strong> at the top to filter machines by name, IP, host, OS, or any other field.</p>
    <div class="callout"><strong>Fetch:</strong> The dashboard reads from a local JSON cache for speed. Use the <strong>Fetch</strong> button (top-right menu) to pull the latest data from the Google Sheet and refresh the cache.</div>
  </div>

  <!-- ── The Python Agent ── -->
  <div class="section" id="agent">
    <div class="section-header">
      <span class="section-num">04</span>
      <h2 class="section-title">The Python Agent</h2>
    </div>
    <p>The agent is made up of three scripts in <code>neuron-scripts/</code>. They are designed to run together and should be started via <code>launch.cmd</code> on Windows or equivalent startup scripts on other platforms.</p>

    <div class="steps" style="counter-reset:none">
      <div class="step" style="counter-increment:none">
        <div class="step-body" style="padding-left:0">
          <div class="step-title">settings.py — configuration</div>
          <p>Edit this file on each machine. Key fields:</p>
          <div class="code-block">
            <span class="key">sheetID</span>     = <span class="val">"your-google-sheet-id"</span><br>
            <span class="key">sheetName</span>   = <span class="val">"Floor 1"</span>  <span class="cmt"># tab name (machine group)</span><br>
            <span class="key">exhibitName</span> = <span class="val">"KIOSK-3"</span>   <span class="cmt"># row identifier on the tab</span><br>
            <span class="key">appEXEPath</span>  = <span class="val">r"C:\MyApp"</span><br>
            <span class="key">appEXEName</span>  = <span class="val">"MyApp.exe"</span><br>
            <span class="key">appPath</span>     = <span class="val">r"C:\MyApp"</span>  <span class="cmt"># used for disk usage check</span>
          </div>
        </div>
      </div>
      <div class="step" style="counter-increment:none">
        <div class="step-body">
          <div class="step-title">report_status.py — heartbeat sender</div>
          <p>Collects system stats and POSTs them to the PulseBoard pulse endpoint every 5 seconds while the monitored app is running. On startup and at midnight it also reads and clears the crash log, sending accumulated crash data with the pulse.</p>
        </div>
      </div>
      <div class="step" style="counter-increment:none">
        <div class="step-body">
          <div class="step-title">guard.py — app watchdog</div>
          <p>Checks every 5–25 seconds whether the monitored app is in the process list. If it has disappeared or is not responding, guard restarts it and logs the event to <code>crash.log</code>. On Windows it also handles kiosk setup — hiding the taskbar, setting the desktop wallpaper, and maximising the app window. Press <strong>Ctrl+Shift+S</strong> to quit guard and restore the desktop.</p>
        </div>
      </div>
    </div>

    <p style="margin-top:1.25rem">Every pulse sends the following fields to the sheet:</p>
    <table class="field-table">
      <tr><th>Field</th><th>Description</th></tr>
      <tr><td>exhibit</td><td>Machine identifier — matches the row in the sheet tab</td></tr>
      <tr><td>host</td><td>Hostname from <code>socket.gethostname()</code></td></tr>
      <tr><td>ip</td><td>Local IP address</td></tr>
      <tr><td>os</td><td>OS name and version (e.g. Windows 11 build 26200)</td></tr>
      <tr><td>memory</td><td>Free / total RAM in GB</td></tr>
      <tr><td>disk</td><td>Free / total disk on the app drive in GB</td></tr>
      <tr><td>uptime</td><td>System uptime in HH:MM:SS</td></tr>
      <tr><td>last_reboot</td><td>Date and time of the last system boot</td></tr>
      <tr><td>teamviewer_id</td><td>TeamViewer client ID (read from registry on Windows, CLI on Linux/macOS)</td></tr>
      <tr><td>status</td><td>"Ok" when the app is running normally</td></tr>
      <tr><td>time</td><td>Timestamp of the pulse</td></tr>
      <tr><td>crashes</td><td>Number of app restarts since last report (sent on startup and midnight)</td></tr>
      <tr><td>crash_times</td><td>Comma-separated list of crash times</td></tr>
    </table>

    <div class="callout"><strong>Cross-platform:</strong> All system calls have Windows, macOS, and Linux branches. The same <code>settings.py</code> format works on every platform — only the paths and executable names need to change.</div>
  </div>

  <!-- ── Crash Tracking ── -->
  <div class="section" id="crash-tracking">
    <div class="section-header">
      <span class="section-num">05</span>
      <h2 class="section-title">Crash Tracking</h2>
    </div>
    <p>Crashes are detected by <code>guard.py</code> when the monitored app disappears from the process list. Guard restarts it immediately and writes a timestamped line to <code>crash.log</code> in the <code>neuron-scripts</code> directory.</p>
    <div class="steps">
      <div class="step">
        <div class="step-body">
          <div class="step-title">Crash detected</div>
          <p><code>guard.py</code> polls the process list every 5–25 seconds. When the app is missing it calls <code>kill_app()</code> (to clean up any zombie processes) then restarts the app and logs <code>App Restarted</code> with a timestamp to <code>crash.log</code>.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">App comes back online</div>
          <p><code>report_status.py</code> detects the app is running again. On the first pulse after a restart it reads <code>crash.log</code>, counts the lines as the crash total, extracts the timestamps, then clears the file.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Crash data sent to the sheet</div>
          <p>The pulse includes <code>crashes</code> (count) and <code>crash_times</code> (comma-separated times). The sheet is updated and the dashboard crash badge changes from green to red.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Daily reset at midnight</div>
          <p>At 00:00:00 <code>report_status.py</code> sends another pulse with crash data and clears the log again, giving you a per-day crash count in the sheet history.</p>
        </div>
      </div>
    </div>
    <div class="callout-warn"><strong>Note:</strong> The crash log is also cleared when <code>guard.py</code> starts (it opens the log file in write mode). If guard is restarted manually, any crashes logged in the previous session are lost. The count in the sheet reflects only crashes since the last guard or report_status startup.</div>
  </div>

  <!-- ── Notes & TeamViewer ── -->
  <div class="section" id="notes-tv">
    <div class="section-header">
      <span class="section-num">06</span>
      <h2 class="section-title">Notes &amp; TeamViewer</h2>
    </div>
    <p>Each machine card has a <strong>Notes</strong> field at the bottom of its expanded detail view. You can type any text — maintenance notes, known issues, scheduled downtime — and click <strong>Save</strong>. The note is written directly to the Google Sheet (column N on the tab) and updated in the local cache.</p>
    <p>Notes are <strong>never overwritten by a pulse</strong>. The heartbeat script deliberately skips the Notes column so that anything you type in the dashboard or directly in the sheet is preserved.</p>
    <p>The <strong>TeamViewer ID</strong> is collected automatically by <code>report_status.py</code> on each pulse. On Windows it reads the <code>ClientID</code> value from the registry (<code>HKLM\SOFTWARE\TeamViewer</code> or the WOW6432Node equivalent). On Linux and macOS it runs <code>teamviewer info</code> or reads the config file. The ID appears in the expanded card detail and in column M of the sheet, making it easy to connect to a machine remotely without having to log in first.</p>
    <div class="callout"><strong>Tip:</strong> You can also edit notes directly in Google Sheets column N. The next time the dashboard fetches data, the updated note will appear on the card.</div>
  </div>

</div><!-- /.page-body -->

<div class="footer">
  <div class="footer-brand"><span class="pb-pulse">Pulse</span><span class="pb-board">Board</span></div>
  <p>Real-time machine monitoring, powered by your Google Sheet.</p>
</div>

</body>
</html>
