<?php
$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_self = rtrim($_raw, '/');
$_base = preg_replace('#/noteboard/help/?$#', '/', $_raw);
if (!$_base || $_base === $_raw) { $_base = '/'; }
if (substr($_base, -1) !== '/') { $_base .= '/'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>NoteBoard – Help</title>
<link rel="icon" type="image/png" href="images/nb_icon_180.png" />
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2')  format('woff2'),url('fonts/DINBlack.ttf')  format('truetype'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf') format('truetype'); }

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
  --navy:   #1a1a2e;
  --amber:  #c8860a;
  --blue:   #A8C8F0;
  --board:  #FFB36B;
  --cream:  #f3f0eb;
  --green:  #16a34a;
  --border: #e5e2dd;
  --slate:  #64748b;
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
.brand-name .nb-note { color: var(--blue); }
.brand-name .nb-board { color: var(--board); }
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
  background: var(--navy); color: #fff;
  padding: 4rem 1.5rem 5rem;
  text-align: center; position: relative; overflow: hidden;
}
.hero::before {
  content: '';
  position: absolute; inset: 0;
  background: radial-gradient(ellipse 80% 60% at 50% 110%, rgba(168,200,240,.15) 0%, transparent 70%);
  pointer-events: none;
}
.hero-icon {
  width: 72px; height: 72px; border-radius: 18px;
  box-shadow: 0 6px 28px rgba(0,0,0,.35); margin-bottom: 1.4rem;
}
.hero-title {
  font-family: 'DINBlack', sans-serif;
  font-size: clamp(2rem, 6vw, 3rem);
  letter-spacing: .05em; line-height: 1; margin-bottom: .7rem;
}
.hero-title .nb-note { color: var(--blue); }
.hero-title .nb-board { color: var(--board); }
.hero-sub {
  font-size: clamp(.95rem, 2.5vw, 1.15rem);
  color: rgba(255,255,255,.65); max-width: 520px;
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
.toc a:hover { background: var(--cream); color: var(--navy); }

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
  color: var(--amber); background: rgba(200,134,10,.1);
  border: 1px solid rgba(200,134,10,.25);
  border-radius: 6px; padding: .2rem .5rem; flex-shrink: 0;
}
.section-title {
  font-family: 'DINBlack', sans-serif; font-size: 1.3rem;
  letter-spacing: .04em; text-transform: uppercase;
  color: var(--navy); line-height: 1.1;
}
.section p, .section li { font-size: .93rem; color: #444; line-height: 1.7; }
.section p + p { margin-top: .75rem; }

/* ── Cards grid ───────────────────────────────────────── */
.cards {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
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

/* ── Mock NoteBoard UI ────────────────────────────────── */
.mock-nb {
  background: #f3f2ef; border-radius: 14px; overflow: hidden;
  margin-top: 1.4rem; box-shadow: 0 4px 24px rgba(0,0,0,.12);
  border: 1px solid var(--border);
}
.mock-bar {
  background: var(--navy); padding: .6rem 1rem;
  display: flex; align-items: center; gap: .6rem;
}
.mock-bar-title {
  font-family: 'DINBlack', sans-serif; font-size: .85rem;
  flex: 1;
}
.mock-bar-title .nb-note { color: var(--blue); }
.mock-bar-title .nb-board { color: var(--board); }
.mock-search {
  background: #22223a; padding: .4rem .8rem;
  display: flex; align-items: center; gap: .5rem;
}
.mock-search-pill {
  flex: 1; background: rgba(255,255,255,.1); border-radius: 6px;
  padding: .28rem .7rem; font-size: .72rem; color: rgba(255,255,255,.4);
  font-family: 'DINRegular', sans-serif;
}
.mock-search-btn {
  font-family: 'DINBlack', sans-serif; font-size: .62rem;
  text-transform: uppercase; letter-spacing: .06em;
  background: rgba(255,255,255,.12); color: rgba(255,255,255,.8);
  border-radius: 6px; padding: .28rem .7rem;
}
.mock-card {
  background: #fff; margin: .75rem; border-radius: 10px;
  box-shadow: 0 1px 5px rgba(0,0,0,.09);
}
.mock-card-header {
  display: flex; align-items: center; gap: .6rem;
  padding: .7rem .85rem; border-bottom: 1px solid var(--border);
}
.mock-card-name {
  font-family: 'DINBlack', sans-serif; font-size: .82rem;
  text-transform: uppercase; letter-spacing: .04em; flex: 1;
}
.mock-badge {
  font-family: 'DINBlack', sans-serif; font-size: .65rem;
  text-transform: uppercase; letter-spacing: .04em;
  background: var(--amber); color: #fff;
  border-radius: 20px; padding: .12rem .55rem;
}
.mock-card-sub {
  background: #1a1a1a; display: flex; gap: .4rem;
  padding: .4rem .85rem;
}
.mock-sub-btn {
  font-family: 'DINBlack', sans-serif; font-size: .62rem;
  text-transform: uppercase; letter-spacing: .06em;
  background: rgba(255,255,255,.12); color: rgba(255,255,255,.8);
  border-radius: 6px; padding: .25rem .6rem;
}

/* ── Callout ──────────────────────────────────────────── */
.callout {
  background: rgba(200,134,10,.07); border-left: 3px solid var(--amber);
  border-radius: 0 8px 8px 0; padding: .8rem 1rem; margin-top: 1rem;
  font-size: .88rem; color: #555;
}
.callout strong { color: var(--amber); }

/* ── Inline code ──────────────────────────────────────── */
code {
  background: #f0ede8; border: 1px solid #ddd; border-radius: 4px;
  padding: .1em .35em; font-size: .88em; color: #555;
}

/* ── Footer ───────────────────────────────────────────── */
.footer {
  background: var(--navy); color: rgba(255,255,255,.5);
  padding: 2.5rem 1.5rem; text-align: center; margin-top: 4rem;
}
.footer-brand {
  font-family: 'DINBlack', sans-serif; font-size: 1.1rem;
  letter-spacing: .04em; margin-bottom: .5rem;
}
.footer-brand .nb-note { color: var(--blue); }
.footer-brand .nb-board { color: var(--board); }
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
    <div class="brand-name"><span class="nb-note">Note</span><span class="nb-board">Board</span></div>
    <span class="top-bar-divider">/</span>
    <span class="top-bar-label">Help</span>
    <a class="top-bar-back" href="noteboard">← Setup</a>
  </div>
</div>

<!-- Hero -->
<div class="hero">
  <img class="hero-icon" src="images/nb_icon_180.png" alt="NoteBoard" />
  <div class="hero-title"><span class="nb-note">Note</span><span class="nb-board">Board</span></div>
  <p class="hero-sub">Collect playtester feedback for every game — via a simple link, straight into your Google Sheet.</p>
  <div class="hero-chips">
    <span class="hero-chip">Google Sheets–powered</span>
    <span class="hero-chip">No accounts needed</span>
    <span class="hero-chip">Per-game feedback links</span>
    <span class="hero-chip">Works on any device</span>
    <span class="hero-chip">Instant setup</span>
  </div>
</div>

<!-- Table of contents -->
<div class="toc-wrap">
  <nav class="toc">
    <a href="<?= $_self ?>#what-is">What is NoteBoard?</a>
    <a href="<?= $_self ?>#getting-started">Getting Started</a>
    <a href="<?= $_self ?>#list-view">The List View</a>
    <a href="<?= $_self ?>#collecting">Collecting Feedback</a>
    <a href="<?= $_self ?>#topics">Topics</a>
    <a href="<?= $_self ?>#profile">Profile</a>
  </nav>
</div>

<div class="page-body">

  <!-- ── What is NoteBoard? ── -->
  <div class="section" id="what-is">
    <div class="section-header">
      <span class="section-num">01</span>
      <h2 class="section-title">What is NoteBoard?</h2>
    </div>
    <p>NoteBoard turns your Google Sheet into a lightweight feedback collector for your games. You get a unique shareable link for each game — send it to playtesters, post it at your table, or add it to a QR code. Responses appear in your sheet instantly and are visible in the NoteBoard list view.</p>
    <p>No login required for playtesters. No forms to configure. One setup, then just share the link.</p>
    <div class="cards">
      <div class="card">
        <span class="card-icon">📋</span>
        <div class="card-title">Lives in your Sheet</div>
        <p>Every note is written back to a <code>[Game] notes</code> tab in your Google Sheet. Your data, your spreadsheet.</p>
      </div>
      <div class="card">
        <span class="card-icon">🔗</span>
        <div class="card-title">Shareable links</div>
        <p>Each game gets its own URL. Share it anywhere — QR codes, email, convention table tents.</p>
      </div>
      <div class="card">
        <span class="card-icon">📱</span>
        <div class="card-title">No account needed</div>
        <p>Playtesters just open the link and type. No sign-in, no app to install, no friction.</p>
      </div>
      <div class="card">
        <span class="card-icon">🗂️</span>
        <div class="card-title">Organised by topic</div>
        <p>Each game or topic gets its own card in the list view with a note count and inline reader.</p>
      </div>
    </div>
  </div>

  <!-- ── Getting Started ── -->
  <div class="section" id="getting-started">
    <div class="section-header">
      <span class="section-num">02</span>
      <h2 class="section-title">Getting Started</h2>
    </div>
    <p>NoteBoard connects to a Google Sheet and sets it up automatically. It works with a brand-new blank sheet or an existing PitchBoard sheet.</p>
    <div class="steps">
      <div class="step">
        <div class="step-body">
          <div class="step-title">Create a Google Sheet</div>
          <p>Open Google Sheets and create a new blank spreadsheet. If you already use PitchBoard you can connect the same sheet — NoteBoard only adds its own tabs.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Share it with the service account</div>
          <p>Click <strong>Share</strong> on your sheet and add <code>editor@zapsheets-480701.iam.gserviceaccount.com</code> as an <strong>Editor</strong>. This is how NoteBoard reads and writes your data.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Connect on the setup page</div>
          <p>Go to <code>/noteboard</code> and paste your Sheet URL or ID. NoteBoard creates a <strong>Settings</strong> tab and a <strong>Games</strong> tab, reads your game list, and opens the list view.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Add your games to the Games tab</div>
          <p>If you started with a blank sheet, open it in Google Sheets and add one game name per row in column A of the Games tab. Come back and run setup again — NoteBoard will pick them up.</p>
        </div>
      </div>
    </div>
    <div class="callout"><strong>Tip:</strong> If you use PitchBoard, your games are already in the Games tab. Just connect the same sheet to NoteBoard and everything is ready.</div>
  </div>

  <!-- ── The List View ── -->
  <div class="section" id="list-view">
    <div class="section-header">
      <span class="section-num">03</span>
      <h2 class="section-title">The List View</h2>
    </div>
    <p>After setup, NoteBoard opens at <code>/{sheet-id}/noteboard</code>. This is your personal dashboard — it shows every game that has had at least one note submitted (or that you created a topic for).</p>

    <div class="mock-nb">
      <div class="mock-bar">
        <div class="mock-bar-title"><span class="nb-note">Note</span><span class="nb-board">Board</span></div>
        <div style="font-family:'DINBlack',sans-serif;font-size:.65rem;text-transform:uppercase;letter-spacing:.06em;background:rgba(255,255,255,.12);border-radius:6px;padding:.25rem .6rem;color:rgba(255,255,255,.8);">6 notes</div>
      </div>
      <div class="mock-search">
        <div class="mock-search-pill">Search games or notes…</div>
        <div class="mock-search-btn">+ Topic</div>
      </div>
      <div class="mock-card">
        <div class="mock-card-header">
          <div class="mock-card-name">Cascade</div>
          <div class="mock-badge">4 notes</div>
          <div style="color:#ccc;font-size:.7rem;">▼</div>
        </div>
        <div class="mock-card-sub">
          <div class="mock-sub-btn">Copy Feedback Link</div>
        </div>
      </div>
      <div class="mock-card" style="margin-top:0">
        <div class="mock-card-header">
          <div class="mock-card-name">Verdant</div>
          <div class="mock-badge" style="background:#e8e5e0;color:#999;">0 notes</div>
          <div style="color:#ccc;font-size:.7rem;">▼</div>
        </div>
        <div class="mock-card-sub">
          <div class="mock-sub-btn">Copy Feedback Link</div>
        </div>
      </div>
    </div>

    <p style="margin-top:1.25rem">Each card shows the game name, a note count badge, and a <strong>Copy Feedback Link</strong> button in the dark sub-bar. Tap the card header to expand it and read the notes inline — newest first.</p>
    <p>The <strong>search box</strong> filters cards by game name or by the text content of any note — useful when you have many games or want to find feedback mentioning a specific rule.</p>
  </div>

  <!-- ── Collecting Feedback ── -->
  <div class="section" id="collecting">
    <div class="section-header">
      <span class="section-num">04</span>
      <h2 class="section-title">Collecting Feedback</h2>
    </div>
    <p>Each game has a unique feedback URL in the format <code>/{sheet-id}/noteboard/{hash}</code>. The hash is a short fingerprint of the game name — it never changes, so a QR code you print today will still work after you add more games.</p>
    <div class="steps">
      <div class="step">
        <div class="step-body">
          <div class="step-title">Copy the link</div>
          <p>On any game card, tap <strong>Copy Feedback Link</strong>. The URL is copied to your clipboard.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Share it with playtesters</div>
          <p>Paste it in an email or message group, print it as a QR code for the table, or add it to your game's rules sheet. Playtesters need no account — they just open the link.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Playtesters fill in the form</div>
          <p>The form asks for a note (required) and optionally a name and email. On submit they see a thank-you message. Their response is saved to a <code>[Game] notes</code> tab in your sheet immediately.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <div class="step-title">Read notes in the list view</div>
          <p>Expand any game card to read all notes inline — newest first. The note count badge updates automatically each time someone submits.</p>
        </div>
      </div>
    </div>
    <div class="callout"><strong>Note on the tab:</strong> The <code>[Game] notes</code> tab is created in your sheet the first time someone submits feedback for that game. Until then, the link is already live and working.</div>
  </div>

  <!-- ── Topics ── -->
  <div class="section" id="topics">
    <div class="section-header">
      <span class="section-num">05</span>
      <h2 class="section-title">Topics</h2>
    </div>
    <p>A <strong>topic</strong> is any named item you want to collect feedback on — a game, a rulebook, a mechanic, an event. Topics that were set up at initialisation come from the Games tab. You can add more at any time from the list view.</p>
    <p>Tap <strong>+ Topic</strong> next to the search box to open the Add Topic dialog. Start typing to filter games that don't have a feedback tab yet, or type a brand-new topic name. The combo box suggests games that are in your sheet but haven't collected any notes yet.</p>
    <p>When you add a topic NoteBoard:</p>
    <div class="steps" style="counter-reset:step">
      <div class="step">
        <div class="step-body">
          <p>Appends the name to your Games tab so it stays in sync.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <p>Creates a formatted <code>[Topic] notes</code> tab ready to receive responses.</p>
        </div>
      </div>
      <div class="step">
        <div class="step-body">
          <p>Adds the topic to the list view immediately — no reload needed.</p>
        </div>
      </div>
    </div>
    <div class="callout"><strong>Tip:</strong> The unique link for a topic is based on a short hash of its name. If you rename a game in the Games tab directly, the old link will stop working. Use the <strong>+ Topic</strong> button to add new names and keep links stable.</div>
  </div>

  <!-- ── Profile ── -->
  <div class="section" id="profile">
    <div class="section-header">
      <span class="section-num">06</span>
      <h2 class="section-title">Profile</h2>
    </div>
    <p>Your profile stores your name, email, and phone number. This information is read from the <strong>Settings</strong> tab in your Google Sheet — the same tab PitchBoard uses, so you only ever need to set it once.</p>
    <p>To update it, tap the <strong>person icon</strong> in the top-right corner of the list view and choose <strong>Profile</strong>. Enter your details and tap <strong>Save</strong>. The subtitle under the NoteBoard title updates immediately to show your name and contact info.</p>
    <div class="callout"><strong>Shared with PitchBoard:</strong> If you use PitchBoard with the same sheet, changes made here appear there too (and vice versa), because both apps read from the same Settings tab.</div>
  </div>

</div><!-- /.page-body -->

<div class="footer">
  <div class="footer-brand"><span class="nb-note">Note</span><span class="nb-board">Board</span></div>
  <p>Collect playtester feedback, effortlessly.</p>
</div>

</body>
</html>
