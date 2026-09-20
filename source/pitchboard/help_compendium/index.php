<?php
$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_self = rtrim($_raw, '/');
$_base = preg_replace('#/pitchboard/help_compendium/?$#', '/', $_raw);
if (!$_base || $_base === $_raw) { $_base = '/'; }
if (substr($_base, -1) !== '/') { $_base .= '/'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>PitchBoard – Compendium Help</title>
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
  --ce-orange: #e8691c;
}

html { scroll-behavior: smooth; }
body { background: var(--cream); font-family: 'DINRegular', Arial, sans-serif; color: #1a1a1a; line-height: 1.6; }

/* ── Top bar ── */
.top-bar { background: var(--navy); color: #fff; padding: .85rem 1.5rem; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 12px rgba(0,0,0,.25); }
.top-bar-inner { max-width: 860px; margin: 0 auto; display: flex; align-items: center; gap: 1rem; }
.brand-name { font-family: 'DINBlack', sans-serif; font-size: 1.1rem; letter-spacing: .06em; text-transform: uppercase; line-height: 1; }
.brand-name .pitch { color: var(--blue); }
.brand-name .board { color: var(--coral); }
.top-bar-divider { opacity: .3; font-size: 1.1rem; }
.top-bar-label { font-family: 'DINBlack', sans-serif; font-size: .8rem; letter-spacing: .1em; text-transform: uppercase; opacity: .75; }
.top-bar-back { margin-left: auto; font-family: 'DINBlack', sans-serif; font-size: .7rem; letter-spacing: .07em; text-transform: uppercase; color: rgba(255,255,255,.55); text-decoration: none; border: 1px solid rgba(255,255,255,.2); border-radius: 6px; padding: .28rem .65rem; transition: color .15s, border-color .15s; }
.top-bar-back:hover { color: #fff; border-color: rgba(255,255,255,.5); }

/* ── Hero ── */
.hero { background: var(--navy); color: #fff; padding: 4rem 1.5rem 5rem; text-align: center; position: relative; overflow: hidden; }
.hero::before { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse 80% 60% at 50% 110%, rgba(232,105,28,.15) 0%, transparent 70%); pointer-events: none; }
.hero-icon { width: 72px; height: 72px; border-radius: 18px; box-shadow: 0 6px 28px rgba(0,0,0,.35); margin-bottom: 1.4rem; }
.hero-ce-logo { width: 72px; height: 72px; border-radius: 50%; background: #fff; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.4rem; box-shadow: 0 6px 28px rgba(0,0,0,.35); overflow: hidden; }
.hero-ce-logo img { width: 100%; height: 100%; object-fit: contain; }
.hero-title { font-family: 'DINBlack', sans-serif; font-size: clamp(2rem, 6vw, 3rem); letter-spacing: .06em; text-transform: uppercase; line-height: 1; margin-bottom: .7rem; }
.hero-title .pitch { color: var(--blue); }
.hero-title .board { color: var(--coral); }
.hero-title .ce { color: #f5a263; }
.hero-sub { font-size: clamp(.95rem, 2.5vw, 1.15rem); color: rgba(255,255,255,.65); max-width: 540px; margin: 0 auto 2rem; line-height: 1.55; }
.hero-chips { display: flex; flex-wrap: wrap; gap: .5rem; justify-content: center; }
.hero-chip { background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18); border-radius: 999px; padding: .3rem .85rem; font-family: 'DINBlack', sans-serif; font-size: .7rem; letter-spacing: .07em; text-transform: uppercase; color: rgba(255,255,255,.8); }
.hero-chip.ce { background: rgba(232,105,28,.2); border-color: rgba(232,105,28,.4); color: #f5a263; }

/* ── Nav pills ── */
.toc-wrap { background: #fff; border-bottom: 1px solid var(--border); position: sticky; top: 54px; z-index: 90; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
.toc-wrap::-webkit-scrollbar { display: none; }
.toc { display: flex; gap: .1rem; padding: .6rem 1.25rem; max-width: 860px; margin: 0 auto; white-space: nowrap; }
.toc a { font-family: 'DINBlack', sans-serif; font-size: .67rem; letter-spacing: .07em; text-transform: uppercase; color: var(--slate); text-decoration: none; padding: .3rem .6rem; border-radius: 6px; transition: background .15s, color .15s; flex-shrink: 0; }
.toc a:hover { background: var(--cream); color: var(--navy); }

/* ── Page body ── */
.page-body { max-width: 860px; margin: 0 auto; padding: 0 1.25rem 5rem; }

/* ── Section ── */
.section { padding-top: 3rem; }
.section-header { display: flex; align-items: center; gap: .75rem; margin-bottom: 1.25rem; padding-bottom: .75rem; border-bottom: 2px solid var(--navy); }
.section-num { font-family: 'DINBlack', sans-serif; font-size: .65rem; letter-spacing: .12em; text-transform: uppercase; color: var(--amber); background: rgba(200,134,10,.1); border: 1px solid rgba(200,134,10,.25); border-radius: 6px; padding: .2rem .5rem; flex-shrink: 0; }
.section-title { font-family: 'DINBlack', sans-serif; font-size: 1.3rem; letter-spacing: .04em; text-transform: uppercase; color: var(--navy); line-height: 1.1; }
.section p, .section li { font-size: .93rem; color: #444; line-height: 1.7; }
.section p + p { margin-top: .75rem; }

/* ── Cards grid ── */
.cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: .85rem; margin-top: 1.25rem; }
.card { background: #fff; border-radius: 12px; padding: 1.1rem 1.25rem; box-shadow: 0 1px 6px rgba(0,0,0,.07); border: 1px solid var(--border); }
.card-icon { font-size: 1.5rem; margin-bottom: .5rem; display: block; }
.card-title { font-family: 'DINBlack', sans-serif; font-size: .82rem; text-transform: uppercase; letter-spacing: .06em; color: var(--navy); margin-bottom: .35rem; }
.card p { font-size: .82rem; color: #666; line-height: 1.55; }

/* ── Steps ── */
.steps { counter-reset: step; margin-top: 1.1rem; display: flex; flex-direction: column; gap: .7rem; }
.step { display: flex; gap: .9rem; align-items: flex-start; background: #fff; border-radius: 10px; padding: .9rem 1.1rem; box-shadow: 0 1px 4px rgba(0,0,0,.06); border: 1px solid var(--border); }
.step::before { counter-increment: step; content: counter(step); font-family: 'DINBlack', sans-serif; font-size: .8rem; background: var(--navy); color: #fff; border-radius: 50%; width: 1.6rem; height: 1.6rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: .05rem; }
.step-body { flex: 1; min-width: 0; }
.step-title { font-family: 'DINBlack', sans-serif; font-size: .83rem; text-transform: uppercase; letter-spacing: .05em; color: var(--navy); margin-bottom: .2rem; }
.step p { font-size: .83rem; color: #555; line-height: 1.6; margin: 0; }

/* ── Tip ── */
.tip { background: rgba(200,134,10,.08); border: 1px solid rgba(200,134,10,.25); border-left: 3px solid var(--amber); border-radius: 8px; padding: .8rem 1rem; margin-top: 1.1rem; font-size: .84rem; color: #555; line-height: 1.6; }
.tip strong { color: var(--amber); }
.tip + .tip { margin-top: .6rem; }

/* ── Inline code ── */
code { font-family: 'Courier New', monospace; font-size: .82em; background: rgba(0,0,0,.06); border-radius: 4px; padding: .1em .35em; color: #333; }

/* ── Diagram label ── */
.diagram-label { font-family: 'DINBlack', sans-serif; font-size: .7rem; letter-spacing: .08em; text-transform: uppercase; color: var(--slate); margin: 1.5rem 0 .5rem; }

/* ── CE inline badge ── */
.ce-badge-inline { display: inline-flex; align-items: center; gap: .3rem; background: rgba(232,105,28,.1); border: 1px solid rgba(232,105,28,.3); border-radius: 6px; padding: .15rem .45rem; font-family: 'DINBlack', sans-serif; font-size: .68rem; letter-spacing: .06em; text-transform: uppercase; color: var(--ce-orange); vertical-align: middle; }
.ce-badge-inline .ce-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--ce-orange); flex-shrink: 0; }

/* ── Footer ── */
.footer { text-align: center; padding: 3rem 1.5rem 2.5rem; border-top: 1px solid var(--border); margin-top: 2rem; }
.footer-brand { font-family: 'DINBlack', sans-serif; font-size: 1.4rem; letter-spacing: .08em; text-transform: uppercase; margin-bottom: .4rem; }
.footer-brand .pitch { color: var(--blue); }
.footer-brand .board { color: var(--coral); }
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
    <div class="brand-name"><span class="pitch">Pitch</span><span class="board">Board</span></div>
    <span class="top-bar-divider">/</span>
    <span class="top-bar-label">Compendium Help</span>
    <a class="top-bar-back" href="pitchboard/help" id="helpBackBtn">← Help</a>
  </div>
</div>

<!-- Hero -->
<div class="hero">
  <div class="hero-ce-logo">
    <img src="https://images.squarespace-cdn.com/content/v1/55fc10b1e4b0347ac88a7992/3669060e-f00e-4113-8fc5-ed87690796cb/CE+logo+-+circle+transparent+background.png?format=100w"
         alt="Cardboard Edison"
         onerror="this.parentNode.innerHTML='<span style=\'font-size:2rem\'>🃏</span>'" />
  </div>
  <div class="hero-title"><span class="ce">Cardboard Edison</span><br><span class="pitch">Com</span><span class="board">pendium</span></div>
  <p class="hero-sub">Publisher data from the Cardboard Edison Compendium — right inside PitchBoard. Find who's accepting, filter by category, and pull up publisher details in seconds.</p>
  <div class="hero-chips">
    <span class="hero-chip ce">Access-code gated</span>
    <span class="hero-chip">Publisher profiles</span>
    <span class="hero-chip">Category filters</span>
    <span class="hero-chip">Convention filters</span>
    <span class="hero-chip">Contact auto-fill</span>
  </div>
</div>

<!-- TOC -->
<div class="toc-wrap">
  <nav class="toc">
    <a href="<?= $_self ?>#overview">Overview</a>
    <a href="<?= $_self ?>#access">Getting Access</a>
    <a href="<?= $_self ?>#publisher-view">Publisher View</a>
    <a href="<?= $_self ?>#filters">Compendium Filters</a>
    <a href="<?= $_self ?>#publisher-dialog">Publisher Dialog</a>
    <a href="<?= $_self ?>#add-pitch">Adding a Pitch</a>
    <a href="<?= $_self ?>#publishing">Publishing (Admin)</a>
  </nav>
</div>

<div class="page-body">

  <!-- ── 01 Overview ── -->
  <div class="section" id="overview">
    <div class="section-header">
      <span class="section-num">01</span>
      <h2 class="section-title">What Is the Compendium Integration?</h2>
    </div>
    <p>The <strong>Cardboard Edison Compendium</strong> is a curated database of tabletop game publishers — their categories of interest, conventions they attend, representative games, preferred contact methods, and more. When you have a valid Compendium access code, PitchBoard pulls this data in and surfaces it at every relevant point in your workflow.</p>
    <p>The integration is entirely opt-in and code-gated: without a code, PitchBoard works exactly as before. With a code, a Cardboard Edison logo <span class="ce-badge-inline"><span class="ce-dot"></span>CE</span> appears wherever Compendium data is available.</p>
    <div class="cards">
      <div class="card">
        <span class="card-icon">🏢</span>
        <div class="card-title">Publisher profiles</div>
        <p>See categories of interest, conventions, representative games, and contact info for hundreds of publishers — without leaving PitchBoard.</p>
      </div>
      <div class="card">
        <span class="card-icon">🔍</span>
        <div class="card-title">Filter publishers</div>
        <p>Filter the publisher list by who's currently accepting submissions, by category (card games, co-op, etc.), or by convention.</p>
      </div>
      <div class="card">
        <span class="card-icon">✉️</span>
        <div class="card-title">Auto-fill contact</div>
        <p>When you pick a Compendium publisher from the dropdown while adding a pitch, the contact email fills in automatically.</p>
      </div>
      <div class="card">
        <span class="card-icon">📋</span>
        <div class="card-title">Show all publishers</div>
        <p>Toggle "Show All" to display every publisher in the Compendium, even ones you haven't pitched yet — great for prospecting.</p>
      </div>
    </div>
  </div>

  <!-- ── 02 Getting Access ── -->
  <div class="section" id="access">
    <div class="section-header">
      <span class="section-num">02</span>
      <h2 class="section-title">Getting Access</h2>
    </div>
    <p>Compendium features are unlocked by entering a valid access code in your Profile. Codes are distributed by whoever administers the Compendium for your community.</p>
    <div class="steps">
      <div class="step">
        <div class="step-body">
          <div class="step-title">Open your Profile</div>
          <p>Tap the person icon in the top-right corner of PitchBoard, then tap <strong>Profile</strong>.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Enter your Compendium Code</div>
          <p>Paste or type your code into the <strong>Compendium Code</strong> field and tap <strong>Save</strong>. The Compendium features activate immediately — no page refresh needed.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Switch to Publisher view</div>
          <p>Tap the <strong>Publishers</strong> button in the view toggle. You'll see the Cardboard Edison logo button appear in the filter bar, and a CE logo on every publisher card that has Compendium data.</p>
        </div>
      </div>
    </div>

    <!-- Diagram: profile dialog -->
    <p class="diagram-label">Profile dialog — Compendium Code field</p>
    <svg viewBox="0 0 560 180" xmlns="http://www.w3.org/2000/svg" style="width:100%;border-radius:12px;display:block;max-width:560px">
      <rect width="560" height="180" rx="14" fill="#1a1a2e"/>
      <!-- Dialog box -->
      <rect x="60" y="18" width="440" height="145" rx="10" fill="#242440"/>
      <text x="80" y="42" font-family="Arial" font-weight="bold" font-size="11" fill="rgba(255,255,255,.45)" letter-spacing="1">PROFILE</text>
      <!-- Name row -->
      <text x="80" y="68" font-family="Arial" font-size="10" fill="rgba(255,255,255,.4)">Name</text>
      <rect x="80" y="74" width="180" height="22" rx="5" fill="rgba(255,255,255,.08)" stroke="rgba(255,255,255,.15)" stroke-width="1"/>
      <text x="90" y="89" font-family="Arial" font-size="10" fill="rgba(255,255,255,.7)">Jane Designer</text>
      <!-- Email row -->
      <text x="280" y="68" font-family="Arial" font-size="10" fill="rgba(255,255,255,.4)">Email</text>
      <rect x="280" y="74" width="200" height="22" rx="5" fill="rgba(255,255,255,.08)" stroke="rgba(255,255,255,.15)" stroke-width="1"/>
      <text x="290" y="89" font-family="Arial" font-size="10" fill="rgba(255,255,255,.7)">jane@example.com</text>
      <!-- Compendium Code row — highlighted -->
      <text x="80" y="114" font-family="Arial" font-size="10" fill="#f5a263">Compendium Code</text>
      <rect x="80" y="120" width="280" height="22" rx="5" fill="rgba(232,105,28,.12)" stroke="#e8691c" stroke-width="1.5"/>
      <text x="90" y="135" font-family="Arial" font-size="10" fill="#f5a263">CE-MYCODE-2026</text>
      <!-- Arrow pointing to code field -->
      <text x="378" y="133" font-family="Arial" font-size="10" fill="#f5a263">← Enter your code here</text>
      <!-- Save button -->
      <rect x="430" y="120" width="50" height="22" rx="5" fill="#e8691c"/>
      <text x="455" y="134" font-family="Arial" font-size="10" fill="#fff" text-anchor="middle" font-weight="bold">Save</text>
    </svg>

    <div class="tip"><strong>Tip:</strong> your code is saved to your Google Sheet's Settings tab. It persists across sessions and devices — you only need to enter it once per Sheet.</div>
    <div class="tip"><strong>Removing access:</strong> clear the Compendium Code field in Profile and save. All CE features disappear immediately.</div>
  </div>

  <!-- ── 03 Publisher View ── -->
  <div class="section" id="publisher-view">
    <div class="section-header">
      <span class="section-num">03</span>
      <h2 class="section-title">Publisher View — CE Buttons</h2>
    </div>
    <p>In Publisher view, every publisher card that has a matching Compendium entry shows a <span class="ce-badge-inline"><span class="ce-dot"></span>CE</span> button in its header. Tapping it opens the full publisher profile from the Compendium.</p>

    <!-- Diagram: publisher card -->
    <p class="diagram-label">Publisher card with Compendium button</p>
    <svg viewBox="0 0 560 110" xmlns="http://www.w3.org/2000/svg" style="width:100%;border-radius:12px;display:block;max-width:560px">
      <rect width="560" height="110" rx="14" fill="#1a1a2e"/>
      <!-- Card -->
      <rect x="20" y="15" width="520" height="80" rx="10" fill="#242440" stroke="rgba(255,255,255,.08)" stroke-width="1"/>
      <!-- Card header -->
      <rect x="20" y="15" width="520" height="40" rx="10" fill="#2d2d50"/>
      <rect x="20" y="40" width="520" height="15" fill="#2d2d50"/>
      <!-- Publisher name -->
      <text x="40" y="40" font-family="Arial" font-weight="bold" font-size="13" fill="rgba(255,255,255,.9)">Stonemaier Games</text>
      <!-- INT badge -->
      <rect x="330" y="24" width="32" height="16" rx="4" fill="#dcfce7"/>
      <text x="346" y="35" font-family="Arial" font-weight="bold" font-size="8" fill="#166534" text-anchor="middle">INT</text>
      <!-- CE button -->
      <rect x="370" y="20" width="30" height="28" rx="7" fill="rgba(232,105,28,.2)" stroke="#e8691c" stroke-width="1.5"/>
      <circle cx="385" cy="34" r="9" fill="#fff"/>
      <text x="385" y="38" font-family="Arial" font-weight="bold" font-size="9" fill="#e8691c" text-anchor="middle">CE</text>
      <!-- Arrow -->
      <text x="408" y="32" font-family="Arial" font-size="10" fill="#f5a263">← tap for Compendium profile</text>
      <!-- Chevron -->
      <text x="506" y="38" font-family="Arial" font-size="12" fill="rgba(255,255,255,.25)">▼</text>
      <!-- Body rows -->
      <rect x="40" y="63" width="130" height="8" rx="3" fill="rgba(255,255,255,.1)"/>
      <rect x="180" y="63" width="80" height="8" rx="3" fill="rgba(255,255,255,.06)"/>
      <rect x="40" y="78" width="100" height="8" rx="3" fill="rgba(255,255,255,.07)"/>
      <rect x="150" y="78" width="60" height="8" rx="3" fill="rgba(255,255,255,.05)"/>
    </svg>

    <p style="margin-top:1.25rem">The CE button only appears when all of the following are true: the Compendium data has been published, your access code is valid, and that publisher's name matches a Compendium entry.</p>
    <div class="tip"><strong>No CE button on a publisher?</strong> Either they're not in the Compendium yet, or the name in your sheet doesn't closely match the Compendium's entry. Partial matches are attempted (e.g. "Osprey" matches "Osprey Games"), but exact names work best.</div>
  </div>

  <!-- ── 04 Compendium Filters ── -->
  <div class="section" id="filters">
    <div class="section-header">
      <span class="section-num">04</span>
      <h2 class="section-title">Compendium Filters</h2>
    </div>
    <p>In Publisher view, a CE logo button appears in the status pill bar. Tapping it expands a filter panel with three kinds of filters — all drawn live from the Compendium data.</p>

    <!-- Diagram: summary bar with CE button -->
    <p class="diagram-label">Status bar with Compendium filter toggle</p>
    <svg viewBox="0 0 560 52" xmlns="http://www.w3.org/2000/svg" style="width:100%;border-radius:10px;display:block;max-width:560px">
      <rect width="560" height="52" rx="10" fill="#f3f0eb"/>
      <!-- Pills -->
      <rect x="12" y="12" width="78" height="28" rx="14" fill="#e2e8f0"/>
      <text x="51" y="30" font-family="Arial" font-weight="bold" font-size="10" fill="#475569" text-anchor="middle">58 PITCHED</text>
      <rect x="96" y="12" width="98" height="28" rx="14" fill="#dcfce7"/>
      <text x="145" y="30" font-family="Arial" font-weight="bold" font-size="10" fill="#166534" text-anchor="middle">67 INTERESTED</text>
      <rect x="200" y="12" width="78" height="28" rx="14" fill="#fee2e2"/>
      <text x="239" y="30" font-family="Arial" font-weight="bold" font-size="10" fill="#991b1b" text-anchor="middle">77 PASSED</text>
      <rect x="284" y="12" width="66" height="28" rx="14" fill="#7c3aed"/>
      <text x="317" y="30" font-family="Arial" font-weight="bold" font-size="10" fill="#fff" text-anchor="middle">7 SIGNED</text>
      <rect x="356" y="12" width="88" height="28" rx="14" fill="#075985"/>
      <text x="400" y="30" font-family="Arial" font-weight="bold" font-size="10" fill="#fff" text-anchor="middle">7 PUBLISHED</text>
      <!-- CE toggle button -->
      <rect x="452" y="8" width="36" height="36" rx="18" fill="rgba(232,105,28,.15)" stroke="#e8691c" stroke-width="1.5"/>
      <circle cx="470" cy="26" r="12" fill="#fff"/>
      <text x="470" y="30" font-family="Arial" font-weight="bold" font-size="10" fill="#e8691c" text-anchor="middle">CE</text>
      <!-- Arrow -->
      <text x="494" y="24" font-family="Arial" font-size="9" fill="#e8691c">←</text>
    </svg>

    <!-- Diagram: filter panel -->
    <p class="diagram-label">Expanded Compendium filter panel</p>
    <svg viewBox="0 0 560 220" xmlns="http://www.w3.org/2000/svg" style="width:100%;border-radius:12px;display:block;max-width:560px">
      <rect width="560" height="220" rx="12" fill="#fff" stroke="#e5e2dd" stroke-width="1"/>
      <!-- Header bar -->
      <rect width="560" height="36" rx="12" fill="#1a1a2e"/>
      <rect y="24" width="560" height="12" fill="#1a1a2e"/>
      <!-- Header chips -->
      <rect x="14" y="8" width="62" height="20" rx="10" fill="rgba(255,255,255,.12)" stroke="rgba(255,255,255,.2)" stroke-width="1"/>
      <text x="45" y="21" font-family="Arial" font-weight="bold" font-size="9" fill="rgba(255,255,255,.85)" text-anchor="middle">ACCEPTING</text>
      <rect x="82" y="8" width="60" height="20" rx="10" fill="rgba(232,105,28,.3)" stroke="#e8691c" stroke-width="1"/>
      <text x="112" y="21" font-family="Arial" font-weight="bold" font-size="9" fill="#f5a263" text-anchor="middle">SHOW ALL</text>
      <!-- Section label: Categories -->
      <text x="14" y="58" font-family="Arial" font-weight="bold" font-size="9" fill="#64748b" letter-spacing="1">CATEGORIES OF INTEREST</text>
      <!-- Category list -->
      <rect x="14" y="64" width="252" height="140" rx="6" fill="#f8f7f5" stroke="#e5e2dd" stroke-width="1"/>
      <!-- List items -->
      <text x="22" y="83" font-family="Arial" font-size="10" fill="#e8691c">✓</text>
      <text x="36" y="83" font-family="Arial" font-weight="bold" font-size="10" fill="#1a1a2e">Card Games</text>
      <line x1="14" y1="90" x2="266" y2="90" stroke="#e5e2dd" stroke-width="1"/>
      <text x="22" y="105" font-family="Arial" font-size="10" fill="#aaa">✓</text>
      <text x="36" y="105" font-family="Arial" font-size="10" fill="#555">Co-operative Games</text>
      <line x1="14" y1="112" x2="266" y2="112" stroke="#e5e2dd" stroke-width="1"/>
      <text x="22" y="127" font-family="Arial" font-size="10" fill="#aaa">✓</text>
      <text x="36" y="127" font-family="Arial" font-size="10" fill="#555">Deduction</text>
      <line x1="14" y1="134" x2="266" y2="134" stroke="#e5e2dd" stroke-width="1"/>
      <text x="22" y="149" font-family="Arial" font-size="10" fill="#aaa">✓</text>
      <text x="36" y="149" font-family="Arial" font-size="10" fill="#555">Drafting</text>
      <line x1="14" y1="156" x2="266" y2="156" stroke="#e5e2dd" stroke-width="1"/>
      <text x="22" y="171" font-family="Arial" font-size="10" fill="#aaa">✓</text>
      <text x="36" y="171" font-family="Arial" font-size="10" fill="#555">Engine Building</text>
      <line x1="14" y1="178" x2="266" y2="178" stroke="#e5e2dd" stroke-width="1"/>
      <text x="22" y="193" font-family="Arial" font-size="10" fill="#aaa">✓</text>
      <text x="36" y="193" font-family="Arial" font-size="10" fill="#555">Family Games</text>
      <!-- Section label: Conventions -->
      <text x="280" y="58" font-family="Arial" font-weight="bold" font-size="9" fill="#64748b" letter-spacing="1">CONVENTIONS</text>
      <!-- Convention list -->
      <rect x="280" y="64" width="266" height="140" rx="6" fill="#f8f7f5" stroke="#e5e2dd" stroke-width="1"/>
      <text x="288" y="83" font-family="Arial" font-size="10" fill="#aaa">✓</text>
      <text x="302" y="83" font-family="Arial" font-size="10" fill="#555">AIGA</text>
      <line x1="280" y1="90" x2="546" y2="90" stroke="#e5e2dd" stroke-width="1"/>
      <text x="288" y="105" font-family="Arial" font-size="10" fill="#e8691c">✓</text>
      <text x="302" y="105" font-family="Arial" font-weight="bold" font-size="10" fill="#1a1a2e">Gen Con</text>
      <line x1="280" y1="112" x2="546" y2="112" stroke="#e5e2dd" stroke-width="1"/>
      <text x="288" y="127" font-family="Arial" font-size="10" fill="#aaa">✓</text>
      <text x="302" y="127" font-family="Arial" font-size="10" fill="#555">Origins</text>
      <line x1="280" y1="134" x2="546" y2="134" stroke="#e5e2dd" stroke-width="1"/>
      <text x="288" y="149" font-family="Arial" font-size="10" fill="#aaa">✓</text>
      <text x="302" y="149" font-family="Arial" font-size="10" fill="#555">PAX Unplugged</text>
      <line x1="280" y1="156" x2="546" y2="156" stroke="#e5e2dd" stroke-width="1"/>
      <text x="288" y="171" font-family="Arial" font-size="10" fill="#aaa">✓</text>
      <text x="302" y="171" font-family="Arial" font-size="10" fill="#555">SPIEL Essen</text>
      <line x1="280" y1="178" x2="546" y2="178" stroke="#e5e2dd" stroke-width="1"/>
      <text x="288" y="193" font-family="Arial" font-size="10" fill="#aaa">✓</text>
      <text x="302" y="193" font-family="Arial" font-size="10" fill="#555">UK Games Expo</text>
      <!-- Active filter summary -->
      <rect x="14" y="208" width="532" height="0" rx="0" fill="none"/>
    </svg>

    <div class="cards" style="margin-top:1.25rem">
      <div class="card">
        <div class="card-title">Accepting</div>
        <p>Shows only publishers that are currently marked as accepting submissions in the Compendium.</p>
      </div>
      <div class="card">
        <div class="card-title">Show All</div>
        <p>Includes publishers that are <em>only</em> in the Compendium (not yet in your pitch history). Great for finding new prospects.</p>
      </div>
      <div class="card">
        <div class="card-title">Categories</div>
        <p>Multi-select list. Shows only publishers interested in the chosen category or categories (e.g. Card Games, Co-op).</p>
      </div>
      <div class="card">
        <div class="card-title">Conventions</div>
        <p>Multi-select list. Shows publishers that attend the chosen conventions — useful for con prep.</p>
      </div>
    </div>
    <div class="tip"><strong>Filters combine:</strong> selecting "Accepting" plus "Gen Con" shows only publishers who are accepting submissions AND regularly attend Gen Con. Active filters are shown as a summary line below the lists.</div>
    <div class="tip"><strong>Closing the filter panel:</strong> tap the CE logo button again to collapse the panel and clear all active Compendium filters.</div>
  </div>

  <!-- ── 05 Publisher Dialog ── -->
  <div class="section" id="publisher-dialog">
    <div class="section-header">
      <span class="section-num">05</span>
      <h2 class="section-title">Publisher Profile Dialog</h2>
    </div>
    <p>Tapping the CE button on a publisher card opens a full-screen profile pulled from the Compendium. Here's what you'll find:</p>

    <!-- Diagram: publisher dialog -->
    <p class="diagram-label">Publisher profile dialog</p>
    <svg viewBox="0 0 560 390" xmlns="http://www.w3.org/2000/svg" style="width:100%;border-radius:12px;display:block;max-width:560px">
      <rect width="560" height="390" rx="14" fill="#1a1a2e"/>
      <!-- Dialog -->
      <rect x="20" y="15" width="520" height="360" rx="12" fill="#242440"/>
      <!-- Header band -->
      <rect x="20" y="15" width="520" height="64" rx="12" fill="#2d2d50"/>
      <rect x="20" y="60" width="520" height="19" fill="#2d2d50"/>
      <!-- Logo circle -->
      <circle cx="60" cy="47" r="22" fill="rgba(255,255,255,.1)" stroke="rgba(255,255,255,.15)" stroke-width="1"/>
      <text x="60" y="52" font-family="Arial" font-size="14" fill="rgba(255,255,255,.3)" text-anchor="middle">🏢</text>
      <!-- Name & country -->
      <text x="92" y="38" font-family="Arial" font-weight="bold" font-size="14" fill="#fff">Stonemaier Games</text>
      <text x="92" y="56" font-family="Arial" font-size="10" fill="rgba(255,255,255,.4)">United States  ·  Updated Jan 2026</text>
      <!-- Accepting chip -->
      <rect x="402" y="22" width="115" height="20" rx="10" fill="rgba(22,163,74,.2)" stroke="#16a34a" stroke-width="1"/>
      <text x="459" y="35" font-family="Arial" font-weight="bold" font-size="9" fill="#4ade80" text-anchor="middle">✓ ACCEPTING</text>

      <!-- Row: Looking for -->
      <text x="40" y="100" font-family="Arial" font-weight="bold" font-size="9" fill="rgba(255,255,255,.35)" letter-spacing="1">LOOKING FOR</text>
      <rect x="40" y="107" width="90" height="18" rx="9" fill="rgba(255,255,255,.08)" stroke="rgba(255,255,255,.1)" stroke-width="1"/>
      <text x="85" y="119" font-family="Arial" font-size="9" fill="rgba(255,255,255,.7)" text-anchor="middle">Euro-style</text>
      <rect x="136" y="107" width="70" height="18" rx="9" fill="rgba(255,255,255,.08)" stroke="rgba(255,255,255,.1)" stroke-width="1"/>
      <text x="171" y="119" font-family="Arial" font-size="9" fill="rgba(255,255,255,.7)" text-anchor="middle">Engine Build</text>
      <rect x="212" y="107" width="76" height="18" rx="9" fill="rgba(255,255,255,.08)" stroke="rgba(255,255,255,.1)" stroke-width="1"/>
      <text x="250" y="119" font-family="Arial" font-size="9" fill="rgba(255,255,255,.7)" text-anchor="middle">Worker Place</text>

      <!-- Row: Representative Games -->
      <line x1="40" y1="135" x2="520" y2="135" stroke="rgba(255,255,255,.06)" stroke-width="1"/>
      <text x="40" y="153" font-family="Arial" font-weight="bold" font-size="9" fill="rgba(255,255,255,.35)" letter-spacing="1">REPRESENTATIVE GAMES</text>
      <rect x="40" y="160" width="72" height="18" rx="9" fill="rgba(255,255,255,.08)" stroke="rgba(255,255,255,.1)" stroke-width="1"/>
      <text x="76" y="172" font-family="Arial" font-size="9" fill="rgba(255,255,255,.7)" text-anchor="middle">Wingspan</text>
      <rect x="118" y="160" width="74" height="18" rx="9" fill="rgba(255,255,255,.08)" stroke="rgba(255,255,255,.1)" stroke-width="1"/>
      <text x="155" y="172" font-family="Arial" font-size="9" fill="rgba(255,255,255,.7)" text-anchor="middle">Viticulture</text>
      <rect x="198" y="160" width="80" height="18" rx="9" fill="rgba(255,255,255,.08)" stroke="rgba(255,255,255,.1)" stroke-width="1"/>
      <text x="238" y="172" font-family="Arial" font-size="9" fill="rgba(255,255,255,.7)" text-anchor="middle">Scythe</text>

      <!-- Row: Conventions -->
      <line x1="40" y1="188" x2="520" y2="188" stroke="rgba(255,255,255,.06)" stroke-width="1"/>
      <text x="40" y="206" font-family="Arial" font-weight="bold" font-size="9" fill="rgba(255,255,255,.35)" letter-spacing="1">CONVENTIONS</text>
      <rect x="40" y="213" width="62" height="18" rx="9" fill="rgba(255,255,255,.08)" stroke="rgba(255,255,255,.1)" stroke-width="1"/>
      <text x="71" y="225" font-family="Arial" font-size="9" fill="rgba(255,255,255,.7)" text-anchor="middle">Gen Con</text>
      <rect x="108" y="213" width="64" height="18" rx="9" fill="rgba(255,255,255,.08)" stroke="rgba(255,255,255,.1)" stroke-width="1"/>
      <text x="140" y="225" font-family="Arial" font-size="9" fill="rgba(255,255,255,.7)" text-anchor="middle">Origins</text>
      <rect x="178" y="213" width="96" height="18" rx="9" fill="rgba(255,255,255,.08)" stroke="rgba(255,255,255,.1)" stroke-width="1"/>
      <text x="226" y="225" font-family="Arial" font-size="9" fill="rgba(255,255,255,.7)" text-anchor="middle">PAX Unplugged</text>

      <!-- Row: Contact -->
      <line x1="40" y1="241" x2="520" y2="241" stroke="rgba(255,255,255,.06)" stroke-width="1"/>
      <text x="40" y="259" font-family="Arial" font-weight="bold" font-size="9" fill="rgba(255,255,255,.35)" letter-spacing="1">CONTACT</text>
      <text x="40" y="276" font-family="Arial" font-size="10" fill="rgba(255,255,255,.6)">submissions@stonemaier.com  ·  Preferred: Email</text>

      <!-- Row: Social links -->
      <line x1="40" y1="290" x2="520" y2="290" stroke="rgba(255,255,255,.06)" stroke-width="1"/>
      <text x="40" y="308" font-family="Arial" font-weight="bold" font-size="9" fill="rgba(255,255,255,.35)" letter-spacing="1">LINKS</text>
      <rect x="40" y="316" width="56" height="18" rx="5" fill="rgba(255,255,255,.07)" stroke="rgba(255,255,255,.1)" stroke-width="1"/>
      <text x="68" y="328" font-family="Arial" font-size="9" fill="rgba(255,255,255,.6)" text-anchor="middle">Website</text>
      <rect x="102" y="316" width="36" height="18" rx="5" fill="rgba(255,255,255,.07)" stroke="rgba(255,255,255,.1)" stroke-width="1"/>
      <text x="120" y="328" font-family="Arial" font-size="9" fill="rgba(255,255,255,.6)" text-anchor="middle">BGG</text>
      <rect x="144" y="316" width="60" height="18" rx="5" fill="rgba(255,255,255,.07)" stroke="rgba(255,255,255,.1)" stroke-width="1"/>
      <text x="174" y="328" font-family="Arial" font-size="9" fill="rgba(255,255,255,.6)" text-anchor="middle">Facebook</text>
      <rect x="210" y="316" width="56" height="18" rx="5" fill="rgba(255,255,255,.07)" stroke="rgba(255,255,255,.1)" stroke-width="1"/>
      <text x="238" y="328" font-family="Arial" font-size="9" fill="rgba(255,255,255,.6)" text-anchor="middle">Twitter</text>

      <!-- Close button -->
      <rect x="220" y="350" width="120" height="14" rx="0" fill="none"/>
      <rect x="234" y="347" width="92" height="20" rx="8" fill="rgba(255,255,255,.06)" stroke="rgba(255,255,255,.12)" stroke-width="1"/>
      <text x="280" y="360" font-family="Arial" font-size="9" fill="rgba(255,255,255,.45)" text-anchor="middle">Close</text>
    </svg>

    <div class="cards" style="margin-top:1.25rem">
      <div class="card"><div class="card-title">Accepting status</div><p>Green badge when the publisher is currently accepting. No badge when closed or unknown.</p></div>
      <div class="card"><div class="card-title">Looking for</div><p>The game types and mechanics this publisher is interested in, shown as chips.</p></div>
      <div class="card"><div class="card-title">Representative Games</div><p>A few titles from their catalog to help you understand their style and positioning.</p></div>
      <div class="card"><div class="card-title">Conventions</div><p>Conventions they regularly attend — handy for planning in-person pitches.</p></div>
      <div class="card"><div class="card-title">Contact</div><p>Their preferred contact method and email or link, pulled directly from the Compendium.</p></div>
      <div class="card"><div class="card-title">Social links</div><p>Website, BGG, Facebook, Twitter/X, Bluesky, Instagram, and more — whatever is in the Compendium entry.</p></div>
    </div>
  </div>

  <!-- ── 06 Adding a Pitch ── -->
  <div class="section" id="add-pitch">
    <div class="section-header">
      <span class="section-num">06</span>
      <h2 class="section-title">Adding a Pitch — Compendium Dropdown</h2>
    </div>
    <p>When your Compendium code is active, the Publisher dropdown in the Add Pitch dialog is enhanced in two ways: Compendium publishers appear in the list with a CE logo badge, and selecting one auto-fills the Contact email from the Compendium entry.</p>

    <!-- Diagram: publisher dropdown -->
    <p class="diagram-label">Publisher dropdown with CE badges</p>
    <svg viewBox="0 0 560 210" xmlns="http://www.w3.org/2000/svg" style="width:100%;border-radius:12px;display:block;max-width:560px">
      <rect width="560" height="210" rx="14" fill="#1a1a2e"/>
      <!-- Input field -->
      <rect x="20" y="18" width="320" height="30" rx="7" fill="#2d2d50" stroke="#4a4a70" stroke-width="1"/>
      <text x="36" y="37" font-family="Arial" font-size="11" fill="rgba(255,255,255,.6)">Sto…</text>
      <text x="324" y="37" font-family="Arial" font-size="10" fill="rgba(255,255,255,.25)" text-anchor="end">▼</text>
      <!-- Dropdown list -->
      <rect x="20" y="52" width="320" height="145" rx="8" fill="#2d2d50" stroke="#4a4a70" stroke-width="1"/>
      <!-- Row 1: Stonemaier -->
      <rect x="20" y="52" width="320" height="36" rx="8" fill="#3d3d60"/>
      <rect x="20" y="72" width="320" height="16" fill="#3d3d60"/>
      <text x="36" y="74" font-family="Arial" font-size="11" fill="#fff">Stonemaier Games</text>
      <!-- CE badge row 1 -->
      <circle cx="290" cy="70" r="11" fill="#fff"/>
      <text x="290" y="74" font-family="Arial" font-weight="bold" font-size="9" fill="#e8691c" text-anchor="middle">CE</text>
      <text x="310" y="74" font-family="Arial" font-size="9" fill="#f5a263">← CE badge</text>
      <!-- Row 2: Stronghold -->
      <line x1="28" y1="88" x2="332" y2="88" stroke="rgba(255,255,255,.06)" stroke-width="1"/>
      <text x="36" y="107" font-family="Arial" font-size="11" fill="rgba(255,255,255,.75)">Stronghold Games</text>
      <circle cx="290" cy="103" r="11" fill="#fff"/>
      <text x="290" y="107" font-family="Arial" font-weight="bold" font-size="9" fill="#e8691c" text-anchor="middle">CE</text>
      <!-- Row 3: no CE -->
      <line x1="28" y1="120" x2="332" y2="120" stroke="rgba(255,255,255,.06)" stroke-width="1"/>
      <text x="36" y="139" font-family="Arial" font-size="11" fill="rgba(255,255,255,.75)">Studio Broc</text>
      <!-- Row 4: Swan Panasia -->
      <line x1="28" y1="152" x2="332" y2="152" stroke="rgba(255,255,255,.06)" stroke-width="1"/>
      <text x="36" y="171" font-family="Arial" font-size="11" fill="rgba(255,255,255,.75)">Swan Panasia</text>
      <circle cx="290" cy="167" r="11" fill="#fff"/>
      <text x="290" y="171" font-family="Arial" font-weight="bold" font-size="9" fill="#e8691c" text-anchor="middle">CE</text>
      <!-- Row 5 -->
      <line x1="28" y1="184" x2="332" y2="184" stroke="rgba(255,255,255,.06)" stroke-width="1"/>
      <text x="36" y="203" font-family="Arial" font-size="11" fill="rgba(255,255,255,.75)">Synapses Games</text>
      <circle cx="290" cy="199" r="11" fill="#fff"/>
      <text x="290" y="203" font-family="Arial" font-weight="bold" font-size="9" fill="#e8691c" text-anchor="middle">CE</text>
      <!-- Right panel: auto-fill -->
      <rect x="360" y="18" width="180" height="78" rx="8" fill="#242440" stroke="#4a4a70" stroke-width="1"/>
      <text x="376" y="36" font-family="Arial" font-weight="bold" font-size="9" fill="rgba(255,255,255,.35)" letter-spacing="1">CONTACT (AUTO-FILLED)</text>
      <rect x="376" y="44" width="148" height="22" rx="5" fill="rgba(232,105,28,.12)" stroke="#e8691c" stroke-width="1"/>
      <text x="384" y="58" font-family="Arial" font-size="9" fill="#f5a263">submissions@stone…</text>
      <text x="376" y="84" font-family="Arial" font-size="9" fill="rgba(255,255,255,.35)">Filled from Compendium</text>
    </svg>

    <div class="cards" style="margin-top:1.25rem">
      <div class="card">
        <div class="card-title">CE logo badge</div>
        <p>A white CE circle appears next to any publisher name that has a Compendium entry. Publishers without Compendium data show no badge.</p>
      </div>
      <div class="card">
        <div class="card-title">Contact auto-fill</div>
        <p>When you select a Compendium publisher, PitchBoard extracts the email address from their Compendium Contact Info and pre-fills the Contact field. You can always edit it before saving.</p>
      </div>
    </div>
    <div class="tip"><strong>New publishers:</strong> you can still type any publisher name — it doesn't have to be in the Compendium. The CE badge just indicates that Compendium data is available for that name.</div>
  </div>

  <!-- ── 07 Publishing (Admin) ── -->
  <div class="section" id="publishing">
    <div class="section-header">
      <span class="section-num">07</span>
      <h2 class="section-title">Publishing the Compendium (Admin)</h2>
    </div>
    <p>If you're the person who manages the Compendium data — maintaining the Google Sheet and pushing updates to PitchBoard — this section is for you.</p>
    <div class="steps">
      <div class="step">
        <div class="step-body">
          <div class="step-title">Open the Compendium admin page</div>
          <p>Navigate to <code>/pitchboard/compendium</code>. This page is separate from a user's PitchBoard — it's the admin publish tool.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Paste the Compendium Sheet URL</div>
          <p>Enter the full Google Sheets URL for the Cardboard Edison Compendium sheet. The sheet must be shared with the ZapSheets service account.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Click Publish</div>
          <p>PitchBoard reads all rows from the main publisher tab and the <strong>Codes</strong> tab, then writes <code>publishers.json</code> and <code>compendium_codes.json</code> to the server. A live log shows each step.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Republish whenever the sheet changes</div>
          <p>The cached JSON is used by every PitchBoard user. Republish after adding publishers, updating entries, or changing the access codes.</p>
        </div>
      </div>
    </div>

    <div class="tip"><strong>Codes tab:</strong> add a <code>Codes</code> worksheet to the Compendium sheet. Put one access code per row in column A (add a header row like "Code" if you like — it's automatically skipped). Each code in that list can unlock the Compendium in PitchBoard.</div>
    <div class="tip"><strong>Empty Codes tab:</strong> if the Codes tab is missing or empty, <em>any</em> non-empty string will pass as a valid code. This is useful while testing, but you should add real codes before distributing access.</div>
  </div>

</div><!-- /page-body -->

<script>
(function() {
  var btn = document.getElementById('helpBackBtn');
  if (document.referrer && document.referrer.indexOf(window.location.origin) === 0) {
    btn.addEventListener('click', function(e) { e.preventDefault(); history.back(); });
  }
})();
</script>

<div class="footer">
  <div class="footer-brand"><span class="pitch">Pitch</span><span class="board">Board</span></div>
  <p>Part of the ZapSheets toolkit for game designers.</p>
</div>

</body>
</html>
