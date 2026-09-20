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

/* ── CE inline logo ── */
.ce-inline-logo { width: 20px; height: 20px; border-radius: 50%; vertical-align: middle; display: inline-block; }

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
    <img src="images/help_compendium/ce-logo-72.png" alt="Cardboard Edison Compendium" />
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
    <p>The integration is entirely opt-in and code-gated: without a code, PitchBoard works exactly as before. With a code, a Cardboard Edison logo <img class="ce-inline-logo" src="images/help_compendium/ce-logo.png" alt="CE logo"> appears wherever Compendium data is available.</p>
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
        <p>Toggle "Include Compendium" to display every publisher in the Compendium, even ones you haven't pitched yet — great for prospecting.</p>
      </div>
    </div>
  </div>

  <!-- ── 02 Getting Access ── -->
  <div class="section" id="access">
    <div class="section-header">
      <span class="section-num">02</span>
      <h2 class="section-title">Getting Access</h2>
    </div>
    <p>Compendium features are unlocked by entering a valid access code in your Profile. Codes are distributed by Cardboard Edison.</p>
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
    <img src="images/help_compendium/ce-profile-dialog.png" alt="Profile dialog showing the Compendium Code field" style="width:100%;border-radius:12px;display:block;max-width:560px" />

    <div class="tip"><strong>Tip:</strong> your code is saved to your Google Sheet's Settings tab. It persists across sessions and devices — you only need to enter it once per Sheet.</div>
    <div class="tip"><strong>Removing access:</strong> clear the Compendium Code field in Profile and save. All CE features disappear immediately.</div>
  </div>

  <!-- ── 03 Publisher View ── -->
  <div class="section" id="publisher-view">
    <div class="section-header">
      <span class="section-num">03</span>
      <h2 class="section-title">Publisher View — CE Buttons</h2>
    </div>
    <p>In Publisher view, every publisher card that has a matching Compendium entry shows a <img class="ce-inline-logo" src="images/help_compendium/ce-logo.png" alt="CE logo"> button in its header. Tapping it opens the full publisher profile from the Compendium.</p>

    <!-- Diagram: publisher card -->
    <p class="diagram-label">Publisher card with Compendium button</p>
    <img src="images/help_compendium/ce-publisher-card.png" alt="Publisher card showing the CE Compendium info button" style="width:100%;border-radius:12px;display:block;max-width:560px" />

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
    <img src="images/help_compendium/ce-summary-bar.png" alt="Status pill bar showing the CE Compendium toggle button" style="width:100%;border-radius:10px;display:block;max-width:560px" />

    <!-- Diagram: filter panel -->
    <p class="diagram-label">Expanded Compendium filter panel</p>
    <img src="images/help_compendium/ce-filter-panel.png" alt="Expanded Compendium filter panel with Categories and Conventions columns" style="width:100%;border-radius:12px;display:block;max-width:560px" />

    <div class="cards" style="margin-top:1.25rem">
      <div class="card">
        <div class="card-title">Accepting</div>
        <p>Shows only publishers that are currently marked as accepting submissions in the Compendium.</p>
      </div>
      <div class="card">
        <div class="card-title">Include Compendium</div>
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
    <div class="tip"><strong>How filters combine:</strong> within each list, selections use OR (e.g. "card games" or "dice games" shows publishers interested in either). Between the two lists, it's AND — selecting "card games" and "Gen Con" shows publishers interested in card games who also attend Gen Con. "Accepting" always narrows results further. Active filters are shown as a summary line below the lists.</div>
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
    <img src="images/help_compendium/ce-publisher-dialog.png" alt="The Compendium publisher profile dialog with rainbow brand strip" style="width:100%;border-radius:12px;display:block;max-width:560px" />

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
    <img src="images/help_compendium/ce-add-pitch.png" alt="Add Pitch dialog publisher dropdown showing CE logo badges" style="width:100%;border-radius:12px;display:block;max-width:560px" />

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
          <p>Navigate to <a href="https://zapsheets.com/app/pitchboard/compendium" target="_blank" style="color:var(--sky)">zapsheets.com/app/pitchboard/compendium</a>. This page is separate from a user's PitchBoard — it's the admin publish tool.</p>
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

    <!-- Diagram: Compendium admin page -->
    <p class="diagram-label">Compendium admin page — Codes tab structure</p>
    <a href="https://zapsheets.com/app/pitchboard/compendium" target="_blank">
      <img src="images/help_compendium/ce-compendium-admin.png" alt="Compendium admin page showing Codes tab" style="width:100%;border-radius:12px;display:block;max-width:700px" />
    </a>

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
