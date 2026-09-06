<?php
$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_self = rtrim($_raw, '/');
$_base = preg_replace('#/devboard/help/?$#', '/', $_raw);
if (!$_base || $_base === $_raw) { $_base = '/'; }
if (substr($_base, -1) !== '/') { $_base .= '/'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>DevBoard – Help</title>
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2')  format('woff2'),url('fonts/DINBlack.ttf')  format('truetype'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf') format('truetype'); }

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --navy:   #1a1a2e;
  --teal:   #1a5f7a;
  --lteal:  #A8D8EA;
  --cream:  #f0f4f8;
  --border: #d8eaf2;
  --amber:  #c8860a;
  --slate:  #64748b;
  --green:  #2e7a52;
  --purple: #6b3fa8;
}

html { scroll-behavior: smooth; }
body { background: var(--cream); font-family: 'DINRegular', Arial, sans-serif; color: #1a1a1a; line-height: 1.6; }

/* ── Top bar ── */
.top-bar { background: var(--teal); color: #fff; padding: .85rem 1.5rem; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 12px rgba(0,0,0,.25); }
.top-bar-inner { max-width: 860px; margin: 0 auto; display: flex; align-items: center; gap: 1rem; }
.brand-name { font-family: 'DINBlack', sans-serif; font-size: 1.1rem; letter-spacing: .06em; text-transform: uppercase; line-height: 1; color: #fff; }
.brand-name .dev { color: var(--lteal); }
.top-bar-divider { opacity: .3; font-size: 1.1rem; }
.top-bar-label { font-family: 'DINBlack', sans-serif; font-size: .8rem; letter-spacing: .1em; text-transform: uppercase; opacity: .75; }
.top-bar-back { margin-left: auto; font-family: 'DINBlack', sans-serif; font-size: .7rem; letter-spacing: .07em; text-transform: uppercase; color: rgba(255,255,255,.55); text-decoration: none; border: 1px solid rgba(255,255,255,.2); border-radius: 6px; padding: .28rem .65rem; transition: color .15s, border-color .15s; }
.top-bar-back:hover { color: #fff; border-color: rgba(255,255,255,.5); }

/* ── Hero ── */
.hero { background: var(--navy); color: #fff; padding: 4rem 1.5rem 5rem; text-align: center; position: relative; overflow: hidden; }
.hero::before { content: ''; position: absolute; inset: 0; background: radial-gradient(ellipse 80% 60% at 50% 110%, rgba(26,95,122,.35) 0%, transparent 70%); pointer-events: none; }
.hero-title { font-family: 'DINBlack', sans-serif; font-size: clamp(2rem, 6vw, 3rem); letter-spacing: .06em; text-transform: uppercase; line-height: 1; margin-bottom: .7rem; }
.hero-title .dev { color: var(--lteal); }
.hero-sub { font-size: clamp(.95rem, 2.5vw, 1.15rem); color: rgba(255,255,255,.65); max-width: 520px; margin: 0 auto 2rem; line-height: 1.55; }
.hero-chips { display: flex; flex-wrap: wrap; gap: .5rem; justify-content: center; }
.hero-chip { background: rgba(255,255,255,.1); border: 1px solid rgba(255,255,255,.18); border-radius: 999px; padding: .3rem .85rem; font-family: 'DINBlack', sans-serif; font-size: .7rem; letter-spacing: .07em; text-transform: uppercase; color: rgba(255,255,255,.8); }

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
.section-header { display: flex; align-items: center; gap: .75rem; margin-bottom: 1.25rem; padding-bottom: .75rem; border-bottom: 2px solid var(--teal); }
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

/* ── Step list ── */
.steps { counter-reset: step; margin-top: 1.1rem; display: flex; flex-direction: column; gap: .7rem; }
.step { display: flex; gap: .9rem; align-items: flex-start; background: #fff; border-radius: 10px; padding: .9rem 1.1rem; box-shadow: 0 1px 4px rgba(0,0,0,.06); border: 1px solid var(--border); }
.step::before { counter-increment: step; content: counter(step); font-family: 'DINBlack', sans-serif; font-size: .8rem; background: var(--teal); color: #fff; border-radius: 50%; width: 1.6rem; height: 1.6rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: .05rem; }
.step-body { flex: 1; min-width: 0; }
.step-title { font-family: 'DINBlack', sans-serif; font-size: .83rem; text-transform: uppercase; letter-spacing: .05em; color: var(--navy); margin-bottom: .2rem; }
.step p { font-size: .83rem; color: #555; line-height: 1.6; margin: 0; }

/* ── Tip box ── */
.tip { background: rgba(200,134,10,.08); border: 1px solid rgba(200,134,10,.25); border-left: 3px solid var(--amber); border-radius: 8px; padding: .8rem 1rem; margin-top: 1.1rem; font-size: .84rem; color: #555; line-height: 1.6; }
.tip strong { color: var(--amber); }

/* ── Mock session UI ── */
.mock-board { background: var(--navy); border-radius: 14px; overflow: hidden; margin-top: 1.4rem; box-shadow: 0 4px 24px rgba(0,0,0,.18); }
.mock-game-bar { background: var(--teal); padding: .6rem 1rem; font-family: 'DINBlack', sans-serif; font-size: .85rem; color: #fff; letter-spacing: .03em; display: flex; align-items: center; justify-content: space-between; }
.mock-game-bar span { font-family: 'DINRegular', sans-serif; font-size: .7rem; opacity: .6; }
.mock-chips { padding: .5rem .8rem; display: flex; gap: .4rem; background: rgba(255,255,255,.04); border-bottom: 1px solid rgba(255,255,255,.07); }
.mock-chip { font-family: 'DINBlack', sans-serif; font-size: .6rem; letter-spacing: .07em; text-transform: uppercase; border-radius: 999px; padding: .22rem .6rem; }
.mock-chip.play { background: rgba(26,95,122,.5); color: #a8d8ea; }
.mock-chip.meet { background: rgba(107,63,168,.5); color: #d0b8f5; }
.mock-chip.idea { background: rgba(46,122,82,.4); color: #86efac; }
.mock-session { padding: .55rem 1rem; border-bottom: 1px solid rgba(255,255,255,.05); display: flex; flex-direction: column; gap: .2rem; }
.mock-session-row { display: flex; align-items: center; gap: .5rem; }
.mock-session-type { font-family: 'DINBlack', sans-serif; font-size: .68rem; text-transform: uppercase; letter-spacing: .06em; }
.mock-session-type.play { color: #7dd3fc; }
.mock-session-type.meet { color: #c084fc; }
.mock-session-date { font-size: .68rem; color: rgba(255,255,255,.4); }
.mock-session-loc  { font-size: .68rem; color: rgba(255,255,255,.5); }
.mock-session-testers { font-size: .67rem; color: rgba(255,255,255,.35); font-style: italic; }

/* ── Session type badges ── */
.type-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: .7rem; margin-top: 1.25rem; }
.type-item { background: #fff; border-radius: 10px; padding: .85rem 1rem; border: 1px solid var(--border); }
.type-badge { font-family: 'DINBlack', sans-serif; font-size: .72rem; text-transform: uppercase; letter-spacing: .07em; padding: .2rem .55rem; border-radius: 4px; display: inline-block; margin-bottom: .45rem; }
.type-badge.play { background: #cff3fc; color: #0c6880; }
.type-badge.meet { background: #ede9fe; color: #5b21b6; }
.type-badge.idea { background: #dcfce7; color: #166534; }
.type-item p { font-size: .8rem; color: #666; line-height: 1.5; margin: 0; }

/* ── Inline code ── */
code { font-family: 'Courier New', monospace; font-size: .82em; background: rgba(0,0,0,.06); border-radius: 4px; padding: .1em .35em; color: #333; }

/* ── Footer ── */
.footer { text-align: center; padding: 3rem 1.5rem 2.5rem; border-top: 1px solid var(--border); margin-top: 2rem; }
.footer-brand { font-family: 'DINBlack', sans-serif; font-size: 1.4rem; letter-spacing: .08em; text-transform: uppercase; margin-bottom: .4rem; color: var(--teal); }
.footer-brand .dev { color: var(--lteal); }
.footer p { font-size: .82rem; color: #999; }

@media (max-width: 540px) {
  .hero { padding: 2.8rem 1.25rem 3.5rem; }
  .cards { grid-template-columns: 1fr; }
  .type-grid { grid-template-columns: 1fr; }
  .section-title { font-size: 1.1rem; }
}
</style>
</head>
<body>

<!-- Top bar -->
<div class="top-bar">
  <div class="top-bar-inner">
    <div class="brand-name"><span class="dev">Dev</span>Board</div>
    <span class="top-bar-divider">/</span>
    <span class="top-bar-label">Help</span>
    <a class="top-bar-back" href="devboard" id="helpBackBtn">← Back</a>
  </div>
</div>

<!-- Hero -->
<div class="hero">
  <div class="hero-title"><span class="dev">Dev</span>Board</div>
  <p class="hero-sub">Your playtest journal. Log every session, track what was tested, who played, and what you learned — all stored in your Google Sheet.</p>
  <div class="hero-chips">
    <span class="hero-chip">Google Sheets–powered</span>
    <span class="hero-chip">Playtests, Meetings &amp; Ideas</span>
    <span class="hero-chip">Observation tracking</span>
    <span class="hero-chip">Per-game history</span>
    <span class="hero-chip">Edit sessions</span>
    <span class="hero-chip">Works on iPad &amp; mobile</span>
  </div>
</div>

<!-- TOC -->
<div class="toc-wrap">
  <nav class="toc">
    <a href="<?= $_self ?>#what-is">What is DevBoard?</a>
    <a href="<?= $_self ?>#getting-started">Getting Started</a>
    <a href="<?= $_self ?>#sessions">Session Types</a>
    <a href="<?= $_self ?>#logging">Logging a Session</a>
    <a href="<?= $_self ?>#editing">Editing Sessions</a>
    <a href="<?= $_self ?>#sheet">The Sheet</a>
    <a href="<?= $_self ?>#tips">Tips</a>
  </nav>
</div>

<div class="page-body">

  <!-- 01 What is DevBoard? -->
  <div class="section" id="what-is">
    <div class="section-header">
      <span class="section-num">01</span>
      <h2 class="section-title">What is DevBoard?</h2>
    </div>
    <p>DevBoard is a playtest journal for tabletop game designers. For each game you're developing, it keeps a chronological log of every playtest session, meeting, and idea — who was in the room, what you observed, and how you resolved it.</p>
    <p>All data is stored in a dedicated tab in your Google Sheet, so you always have a permanent record you can search, export, or share.</p>
    <div class="cards">
      <div class="card">
        <span class="card-icon">📋</span>
        <div class="card-title">Lives in your Sheet</div>
        <p>Each game gets its own tab (<code>[Game Name] dev</code>). DevBoard reads and writes to it — no separate database.</p>
      </div>
      <div class="card">
        <span class="card-icon">🎲</span>
        <div class="card-title">Per-game history</div>
        <p>All sessions for a game are grouped under that game's card — expand it to browse the full history.</p>
      </div>
      <div class="card">
        <span class="card-icon">✏️</span>
        <div class="card-title">Edit any session</div>
        <p>Made a typo or forgot a tester? Open the edit dialog and save — the sheet is updated in place.</p>
      </div>
      <div class="card">
        <span class="card-icon">📱</span>
        <div class="card-title">Works everywhere</div>
        <p>Designed for phone, tablet, and desktop. Log notes during a playtest from your iPhone.</p>
      </div>
    </div>
  </div>

  <!-- 02 Getting Started -->
  <div class="section" id="getting-started">
    <div class="section-header">
      <span class="section-num">02</span>
      <h2 class="section-title">Getting Started</h2>
    </div>
    <p>DevBoard uses the same Google Sheet as PitchBoard. If PitchBoard is already set up, you just need to add games to DevBoard.</p>
    <div class="steps">
      <div class="step">
        <div class="step-body">
          <div class="step-title">Open DevBoard</div>
          <p>Navigate to <code>/&lt;sheet-id&gt;/devboard</code>. DevBoard lists every game that has a dev tab in your sheet.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Add a game</div>
          <p>Tap <strong>+ Game</strong> in the top-right. Select a game from your sheet or type a new name. DevBoard creates a <code>[Game Name] dev</code> tab in your sheet automatically.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Log your first session</div>
          <p>Expand the game card and tap <strong>+ Session</strong>. Fill in the date, session type, location, who played, and your observations.</p>
        </div>
      </div>
    </div>
    <div class="tip"><strong>Tip:</strong> Bookmark your DevBoard URL or add it to your home screen as a web app for quick access during playtests.</div>
  </div>

  <!-- 03 Session Types -->
  <div class="section" id="sessions">
    <div class="section-header">
      <span class="section-num">03</span>
      <h2 class="section-title">Session Types</h2>
    </div>
    <p>Every session has a type. The type controls the colour coding in DevBoard and in the Google Sheet, and lets you filter sessions per game.</p>
    <div class="type-grid">
      <div class="type-item">
        <span class="type-badge play">Playtest</span>
        <p>An actual play session — at home, a con, a game café, or online. The most common type.</p>
      </div>
      <div class="type-item">
        <span class="type-badge meet">Meeting</span>
        <p>A design discussion, co-design call, or publisher conversation about the game's direction.</p>
      </div>
      <div class="type-item">
        <span class="type-badge idea">Idea</span>
        <p>A solo brainstorm, rule variant, or sudden insight worth capturing before it's forgotten.</p>
      </div>
    </div>
    <p style="margin-top:1.1rem">The session count chips at the top of each game card (e.g. <strong>4 Playtests · 2 Meetings</strong>) are tappable — tap one to filter the session list to that type. Tap again to clear the filter.</p>

    <!-- Mock board -->
    <div class="mock-board">
      <div class="mock-game-bar">Thornwick Abbey <span>3 Playtests · 1 Meeting · 1 Idea</span></div>
      <div class="mock-chips">
        <span class="mock-chip play">3 Playtests</span>
        <span class="mock-chip meet">1 Meeting</span>
        <span class="mock-chip idea">1 Idea</span>
      </div>
      <div class="mock-session">
        <div class="mock-session-row">
          <span class="mock-session-type play">Playtest 3</span>
          <span class="mock-session-date">· Sep 6, 2026</span>
          <span class="mock-session-loc">· Game Café</span>
        </div>
        <div class="mock-session-testers">Alex Schmidt, Kristina Hedbacker</div>
      </div>
      <div class="mock-session">
        <div class="mock-session-row">
          <span class="mock-session-type play">Playtest 2</span>
          <span class="mock-session-date">· Aug 31, 2026</span>
          <span class="mock-session-loc">· Home</span>
        </div>
        <div class="mock-session-testers">John Doe, Andre Bierth</div>
      </div>
      <div class="mock-session">
        <div class="mock-session-row">
          <span class="mock-session-type meet">Meeting 1</span>
          <span class="mock-session-date">· Aug 31, 2026</span>
        </div>
      </div>
    </div>
  </div>

  <!-- 04 Logging a Session -->
  <div class="section" id="logging">
    <div class="section-header">
      <span class="section-num">04</span>
      <h2 class="section-title">Logging a Session</h2>
    </div>
    <p>Tap <strong>+ Session</strong> on a game card to open the session dialog.</p>
    <div class="steps">
      <div class="step">
        <div class="step-body">
          <div class="step-title">Set Date, Type &amp; Location</div>
          <p>Date defaults to today. Choose Playtest, Meeting, or Idea. Location is optional — use it for the venue, platform (Tabletopia, TTS), or context.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Add People</div>
          <p>Type names in the People field. Start typing to search your saved contacts. Each name goes on a separate row — just type in the last field to add another.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Log Observations &amp; Solutions</div>
          <p>Each observation row has two fields side by side: <strong>Observation</strong> (what happened or what was said) and <strong>Solution</strong> (how you plan to address it). Both are optional per row — log as many as you need. Typing in the last row adds another automatically.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Tap Add Session</div>
          <p>DevBoard writes the session to your Google Sheet and updates the card immediately — no page reload needed.</p>
        </div>
      </div>
    </div>
    <div class="tip"><strong>Keyboard shortcut:</strong> while in the Observation/Solution grid, use <strong>⌘ + Arrow</strong> (Mac) or <strong>Ctrl + Arrow</strong> (Windows) to move between fields without reaching for the mouse.</div>
    <div class="tip" style="margin-top:.6rem"><strong>Auto-growing fields:</strong> each text field starts at one line and expands as you type — no scrolling inside tiny boxes.</div>
  </div>

  <!-- 05 Editing Sessions -->
  <div class="section" id="editing">
    <div class="section-header">
      <span class="section-num">05</span>
      <h2 class="section-title">Editing Sessions</h2>
    </div>
    <p>You can edit any session after it's been saved — useful for fixing typos, adding forgotten testers, or updating observations after reflection.</p>
    <div class="cards">
      <div class="card">
        <span class="card-icon">🖱</span>
        <div class="card-title">Desktop — hover</div>
        <p>Hover over a session header to reveal the <strong>Edit</strong> button on the right side of the row. Click it to open the edit dialog.</p>
      </div>
      <div class="card">
        <span class="card-icon">👆</span>
        <div class="card-title">Touch — long press</div>
        <p>On iPhone or iPad, press and hold a session header for about half a second. The edit dialog opens when you release.</p>
      </div>
    </div>
    <p style="margin-top:1.1rem">The edit dialog is identical to the add dialog, pre-filled with the session's existing data. Change what you need and tap <strong>Save Changes</strong>. DevBoard replaces the session rows in the sheet in place — the session number and position are preserved.</p>
    <div class="tip"><strong>Unsaved changes protection:</strong> if you try to close the edit dialog (Escape or tapping outside) while there are unsaved changes, the dialog shakes instead of closing. Use the Cancel button to discard intentionally.</div>
  </div>

  <!-- 06 The Sheet -->
  <div class="section" id="sheet">
    <div class="section-header">
      <span class="section-num">06</span>
      <h2 class="section-title">The Sheet</h2>
    </div>
    <p>Each game's dev tab uses a five-column format that's readable directly in Google Sheets:</p>
    <div class="cards">
      <div class="card">
        <div class="card-title">Date</div>
        <p>The session date. Only filled on the session header row — blank for tester and observation rows.</p>
      </div>
      <div class="card">
        <div class="card-title">Event</div>
        <p>The session label — e.g. <code>Playtest 3</code> or <code>Meeting 1</code>. Only on the header row.</p>
      </div>
      <div class="card">
        <div class="card-title">People</div>
        <p>One tester per row, formatted as <code>Name email@address.com</code>. Blank on header and observation rows.</p>
      </div>
      <div class="card">
        <div class="card-title">Observation</div>
        <p>What happened or what was noted. Also used for Location on the session header row.</p>
      </div>
      <div class="card">
        <div class="card-title">Solution</div>
        <p>How the observation was or will be addressed. Paired with each Observation row.</p>
      </div>
    </div>
    <div class="tip"><strong>Colour coding:</strong> Playtest rows are teal, Meeting rows are purple, and Idea rows are green — applied automatically by conditional formatting when a tab is created.</div>
  </div>

  <!-- 07 Tips -->
  <div class="section" id="tips">
    <div class="section-header">
      <span class="section-num">07</span>
      <h2 class="section-title">Tips</h2>
    </div>
    <div class="cards">
      <div class="card">
        <span class="card-icon">🔄</span>
        <div class="card-title">Fetch to sync</div>
        <p>Use the <strong>Fetch</strong> button (person icon → Fetch) after editing the sheet directly to pull in the latest data.</p>
      </div>
      <div class="card">
        <span class="card-icon">🔍</span>
        <div class="card-title">Search games</div>
        <p>The search bar at the top filters game cards by name — useful when you have many games in DevBoard.</p>
      </div>
      <div class="card">
        <span class="card-icon">📊</span>
        <div class="card-title">Filter by type</div>
        <p>Tap a session-type chip on any game card to filter to just Playtests, Meetings, or Ideas for that game.</p>
      </div>
      <div class="card">
        <span class="card-icon">📝</span>
        <div class="card-title">Idea sessions</div>
        <p>Use the Idea type for a quick solo brainstorm — no testers needed, just observations and solutions.</p>
      </div>
      <div class="card">
        <span class="card-icon">👥</span>
        <div class="card-title">Save contacts first</div>
        <p>Add frequent testers to your People sheet in advance — their names then appear in the autocomplete when logging sessions.</p>
      </div>
      <div class="card">
        <span class="card-icon">📱</span>
        <div class="card-title">Home screen shortcut</div>
        <p>Add DevBoard to your iPhone or iPad home screen as a web app for instant one-tap access during playtests.</p>
      </div>
    </div>
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
  <div class="footer-brand"><span class="dev">Dev</span>Board</div>
  <p>Part of the ZapSheets toolkit for game designers.</p>
</div>

</body>
</html>
