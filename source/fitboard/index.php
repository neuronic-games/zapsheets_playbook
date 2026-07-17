<?php
$_rp = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('#^(.*?)(?:sheets/)?([A-Za-z0-9_\-]+)/fitboard/?$#', $_rp, $_bm);
$_base     = (isset($_bm[1]) && $_bm[1] !== '') ? $_bm[1] : '/';
if (substr($_base, -1) !== '/') $_base .= '/';
$_sheet_id = $_bm[2] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8" />
<!-- maximum-scale=1 stops iOS from zooming on input focus without disabling user pinch -->
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover" />
<title>FitBoard</title>
<link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars($_base) ?>images/fb_icon_180.png" />
<link rel="icon" type="image/png" sizes="192x192" href="<?= htmlspecialchars($_base) ?>images/fb_icon_192.png" />
<link rel="manifest" href="<?= htmlspecialchars($_base) ?>manifest.php?id=<?= urlencode($_sheet_id) ?>&amp;app=fitboard&amp;base=<?= urlencode($_base) ?>" />
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Caveat:wght@700&display=swap" rel="stylesheet" />
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2') format('woff2'),url('fonts/DINBlack.ttf'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf'); }

:root {
  --bg:       #0f0f14;
  --card:     #1c1c28;
  --border:   #2a2a3a;
  --green:    #30d158;
  --blue:     #0a84ff;
  --yellow:   #ffd60a;
  --pink:     #ff375f;
  --text:     #ffffff;
  --text2:    rgba(255,255,255,.62);
  --text3:    rgba(255,255,255,.32);
  --input-bg: #12121c;
  --line-h:   62px;
}

*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }

/* ── Page layout: body is the outer flex column ─────────── */
html {
  background:var(--bg);
  color:var(--text);
  font-family:'DINRegular',-apple-system,sans-serif;
  -webkit-font-smoothing:antialiased;
}
body {
  display:flex; flex-direction:column;
  height:100dvh;
  overflow:hidden;
  background:var(--bg);
}

/* ── Top bar — direct body child, never scrolls away ────── */
.top-bar {
  flex-shrink:0;
  display:flex; align-items:center; justify-content:space-between;
  padding:.95rem 1rem;
  padding-top:calc(.95rem + env(safe-area-inset-top));
  background:rgba(15,15,20,.92);
  backdrop-filter:blur(20px); -webkit-backdrop-filter:blur(20px);
  border-bottom:1px solid var(--border);
  z-index:50;
  touch-action:manipulation;
}
.top-bar-brand {
  display:flex; align-items:center;
  font-family:'DINBlack',sans-serif; font-size:1.35rem;
  letter-spacing:.04em; line-height:1;
}
.brand-fit   { color:var(--text); }
.brand-board { color:var(--blue); }
.top-bar-right { display:flex; align-items:center; gap:.55rem; }

/* lbs / kg toggle — single tap anywhere toggles the unit */
.unit-toggle {
  display:flex; flex-direction:column; align-items:stretch;
  background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.13);
  border-radius:14px; padding:2px; gap:1px; flex-shrink:0;
  cursor:pointer; -webkit-tap-highlight-color:transparent;
}
.unit-btn {
  font-family:'DINBlack',sans-serif; font-size:.65rem; letter-spacing:.07em;
  text-transform:uppercase; color:var(--text3);
  padding:.18rem .42rem; border-radius:11px;
  transition:background .15s, color .15s; line-height:1;
  text-align:center; pointer-events:none; /* parent handles the click */
}
.unit-btn.active { background:rgba(255,255,255,.18); color:var(--text); }

/* Inline save status */
.save-status-inline {
  font-family:'DINBlack',sans-serif; font-size:.72rem;
  letter-spacing:.1em; text-transform:uppercase;
  color:var(--text3); transition:color .2s; white-space:nowrap;
}
.save-status-inline.ok    { color:var(--green); }
.save-status-inline.error { color:var(--pink); }

/* Sync button */
.sync-btn {
  position:relative; z-index:1;
  width:38px; height:38px; border-radius:50%;
  background:rgba(255,255,255,.09); color:var(--text2);
  border:1px solid rgba(255,255,255,.18);
  display:flex; align-items:center; justify-content:center;
  cursor:pointer; font-size:1.25rem; line-height:1;
  transition:background .15s, color .15s;
  flex-shrink:0; touch-action:manipulation;
}
.sync-btn:hover  { background:rgba(255,255,255,.18); color:var(--text); }
.sync-btn:disabled { opacity:.35; cursor:default; }
.sync-btn.spinning { animation:spin .75s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }

/* Save button */
.save-btn {
  position:relative; z-index:1;
  font-family:'DINBlack',sans-serif; font-size:.9rem;
  letter-spacing:.1em; text-transform:uppercase;
  background:var(--green); color:#000;
  border:none; border-radius:20px; padding:.6rem 1.4rem;
  cursor:pointer; transition:opacity .15s, background .15s;
  display:flex; align-items:center; gap:.3rem;
  touch-action:manipulation;
}
.save-btn:disabled { opacity:.35; cursor:default; background:rgba(255,255,255,.12); color:var(--text3); }
.save-btn.saving   { background:var(--blue); color:#fff; }

/* ── Add-to-Home-Screen bottom sheet ────────────────────── */
.a2hs-sheet {
  position:fixed; bottom:0; left:0; right:0; z-index:9000;
  background:var(--card);
  border-top:1px solid var(--border);
  border-radius:18px 18px 0 0;
  padding:20px 20px;
  padding-bottom:max(28px, env(safe-area-inset-bottom, 28px));
  box-shadow:0 -6px 32px rgba(0,0,0,.6);
  transform:translateY(110%);
  transition:transform .38s cubic-bezier(.32,1,.24,1);
}
.a2hs-sheet.visible { transform:translateY(0); }
.a2hs-header {
  display:flex; align-items:center; justify-content:space-between;
  margin-bottom:10px;
}
.a2hs-title {
  font-family:'DINBlack',sans-serif;
  font-size:.82rem; letter-spacing:.1em; text-transform:uppercase;
  color:var(--text);
}
.a2hs-close {
  width:26px; height:26px; border-radius:50%;
  background:rgba(255,255,255,.1); border:none; cursor:pointer;
  color:var(--text2); font-size:.85rem; line-height:26px; text-align:center;
  flex-shrink:0; touch-action:manipulation;
}
.a2hs-msg {
  font-size:.93rem; color:var(--text2); line-height:1.55; margin-bottom:12px;
}
.a2hs-steps {
  font-size:.86rem; color:var(--text3); line-height:1.7;
}
.a2hs-steps strong { color:var(--text2); }
.a2hs-steps svg { vertical-align:middle; display:inline-block; }

/* ── App shell — fills remaining height ─────────────────── */
.app {
  flex:1; min-height:0;
  display:flex; flex-direction:column;
  overflow:hidden;
}

/* ── Day header ─────────────────────────────────────────── */
.day-header {
  flex-shrink:0;
  display:flex; align-items:center; justify-content:space-between;
  padding:.85rem 1rem .8rem;
  background:linear-gradient(160deg,#1a1a2e 0%,#12121c 100%);
  border-bottom:1px solid var(--border);
}
.day-meta { flex:1; min-width:0; }
.day-label {
  font-family:'DINBlack',sans-serif; font-size:.72rem;
  letter-spacing:.14em; text-transform:uppercase;
  color:var(--text3); margin-bottom:.15rem;
}
.day-title-row {
  display:inline-flex; align-items:center; gap:.4rem;
  cursor:pointer; user-select:none; touch-action:manipulation;
}
.day-title {
  font-family:'DINBlack',sans-serif;
  font-size:clamp(.95rem,4vw,1.25rem);
  letter-spacing:.04em; text-transform:uppercase;
  color:var(--text); line-height:1.1;
}
.day-chevron { color:var(--blue); flex-shrink:0; transition:transform .2s; }
.day-title-row:active .day-chevron { color:#fff; }

/* ── Calendar date button (top bar) ─────────────────────── */
/*
  The visible icon is the SVG. Overlaid on top is a transparent
  <input type="date"> so tapping the icon opens the native date picker.
*/
.cal-btn {
  position:relative;
  overflow:hidden; /* clip date input's iOS tap bleed */
  width:38px; height:38px; border-radius:50%;
  background:rgba(255,255,255,.09); color:var(--text2);
  border:1px solid rgba(255,255,255,.18);
  display:flex; align-items:center; justify-content:center;
  cursor:pointer; flex-shrink:0;
  transition:background .15s, color .15s;
}
.cal-btn:hover, .cal-btn.dated { background:rgba(255,255,255,.18); color:var(--text); }
.cal-btn svg { width:18px; height:18px; pointer-events:none; }
/* The input is invisible but receives tap events.
   Explicit positions — inset shorthand not supported on iOS Safari < 14.5 */
.cal-btn input[type="date"] {
  position:absolute; top:0; right:0; bottom:0; left:0;
  opacity:0; width:100%; height:100%;
  cursor:pointer; font-size:16px; /* ≥16px — no iOS zoom */
  color-scheme:dark; border:none; background:none;
}

/* ── Progress ring ──────────────────────────────────────── */
.ring-wrap {
  position:relative; flex-shrink:0;
  width:80px; height:80px;
  display:flex; align-items:center; justify-content:center;
}
.ring { width:80px; height:80px; transform:rotate(-90deg); }
.ring-bg   { fill:none; stroke:var(--border); stroke-width:8; }
.ring-fill {
  fill:none; stroke:var(--blue); stroke-width:8; stroke-linecap:round;
  stroke-dasharray:201; stroke-dashoffset:201;
  transition:stroke-dashoffset .5s ease, stroke .3s;
}
.ring-center {
  position:absolute; inset:0;
  display:flex; flex-direction:column;
  align-items:center; justify-content:center;
}
.ring-count { font-family:'DINBlack',sans-serif; font-size:.85rem; line-height:1; }
.ring-sub   { font-size:.44rem; letter-spacing:.08em; text-transform:uppercase; color:var(--text3); margin-top:.1rem; }

/* ── Rolodex ────────────────────────────────────────────── */
.rolodex-wrap { flex:1; min-height:0; position:relative; overflow:hidden; }
.rolodex-viewport {
  height:100%;
  overflow-y:scroll; overflow-x:hidden;
  scroll-snap-type:y mandatory;
  -webkit-overflow-scrolling:touch;
  scrollbar-width:none;
}
.rolodex-viewport::-webkit-scrollbar { display:none; }

.ex-slide {
  height:100%;
  scroll-snap-align:start;
  scroll-snap-stop:always;
  overflow-y:auto; overflow-x:hidden;
  padding:.65rem .75rem;
  display:flex; flex-direction:column;
  scrollbar-width:none;
}
.ex-slide::-webkit-scrollbar { display:none; }

/* Dot indicator */
.rolodex-dots {
  position:absolute; right:7px; top:50%; transform:translateY(-50%);
  display:flex; flex-direction:column; align-items:center; gap:5px;
  z-index:10; pointer-events:none;
  max-height:80%; overflow:hidden;
}
.rdot {
  width:4px; height:4px; border-radius:2px;
  background:rgba(255,255,255,.22);
  transition:height .2s, background .2s; flex-shrink:0;
}
.rdot.active { height:14px; background:#fff; }

/* ── Exercise card ──────────────────────────────────────── */
.ex-card {
  background:var(--card); border:1px solid var(--border);
  border-radius:16px; overflow:hidden;
  display:flex; flex-direction:column;
  flex:1; min-height:0; transition:border-color .2s;
  position:relative;
}
.ex-card.is-done { border-color:rgba(48,209,88,.4); }

/* ── Video section — slide-down open / slide-up close ────── */
/*
  Uses clip-path so layout is unaffected while animating.
  inset(0 0 100%) = clip entire bottom → invisible (height still consumed by flex).
  inset(0 0 0%)   = no clip → fully visible.
  Sweeping the bottom boundary downward looks like a slide-down reveal.
*/
.ex-video-section {
  background:#000; flex-shrink:0; overflow:hidden;
  clip-path:inset(0 0 100% 0);
  transition:clip-path .38s cubic-bezier(.32,.72,0,1);
}
.ex-video-section.open {
  clip-path:inset(0 0 0% 0);
}
.ex-video-section.closing {
  clip-path:inset(0 0 100% 0);
  transition:clip-path .26s cubic-bezier(.4,0,1,1); /* faster on close */
}

.video-iframe-wrap { position:relative; width:100%; }
.video-iframe-wrap.wide  { padding-top:56.25%; }
.video-iframe-wrap.short { padding-top:min(100%,75vw); }
.video-iframe-wrap iframe { position:absolute; inset:0; width:100%; height:100%; border:none; }
.video-close-btn {
  position:absolute; top:8px; right:8px; z-index:5;
  width:28px; height:28px; border-radius:50%;
  background:rgba(0,0,0,.7); color:#fff;
  border:none; font-size:1rem; line-height:1;
  cursor:pointer; display:flex; align-items:center; justify-content:center;
  touch-action:manipulation;
}

/* ── Video takeover mode ────────────────────────────────── */
.ex-card.video-mode .ex-weight-row,
.ex-card.video-mode .ex-sets-row,
.ex-card.video-mode .notebook-area { display:none !important; }
.ex-card.video-mode .ex-note    { display:none; }
.ex-card.video-mode .done-toggle { display:none; }
/* In takeover mode the video fills remaining flex space; clip-path still handles visibility */
.ex-card.video-mode .ex-video-section {
  flex:1; min-height:0;
  max-height:none;
  display:flex; flex-direction:column;
}
.ex-card.video-mode .video-iframe-wrap {
  flex:1; min-height:0; padding-top:0 !important; position:relative;
}

/* ── Card top ───────────────────────────────────────────── */
.ex-card-top {
  display:flex; align-items:flex-start; justify-content:space-between;
  gap:.4rem; padding:.8rem .8rem .55rem; flex-shrink:0;
}
.ex-info { flex:1; min-width:0; }
.ex-name {
  font-family:'DINBlack',sans-serif; font-size:1.05rem;
  letter-spacing:.02em; text-transform:uppercase;
  color:var(--text); line-height:1.2;
}
.ex-meta-row {
  display:flex; align-items:center; flex-wrap:wrap; gap:.35rem; margin-top:.35rem;
}
.ex-target {
  font-family:'DINBlack',sans-serif; font-size:.7rem;
  letter-spacing:.07em; text-transform:uppercase;
  background:rgba(10,132,255,.18); color:var(--blue);
  border:1px solid rgba(10,132,255,.3); padding:.2rem .55rem; border-radius:20px;
}
/* Circular FAB — play / close video */
.yt-fab {
  position:absolute; bottom:.85rem; right:.85rem; z-index:10;
  width:64px; height:64px; border-radius:50%;
  background:rgba(10,132,255,.88); border:none; color:#fff;
  display:flex; align-items:center; justify-content:center;
  cursor:pointer; touch-action:manipulation;
  box-shadow:0 4px 18px rgba(0,0,0,.5);
  transition:background .15s, transform .1s;
}
.yt-fab:active { transform:scale(.9); }
.yt-fab.open   { background:rgba(255,59,48,.85); }
.yt-fab svg    { width:22px; height:22px; flex-shrink:0; }
/* Reps pill inline with exercise name */
.ex-name-row { display:flex; align-items:baseline; gap:.45rem; flex-wrap:wrap; }
.ex-reps-pill {
  font-family:'DINBlack',sans-serif; font-size:.72rem; line-height:1;
  color:var(--blue); background:rgba(10,132,255,.12);
  border:1px solid rgba(10,132,255,.3);
  border-radius:20px; padding:.14rem .48rem;
  white-space:nowrap; flex-shrink:0;
}
.ex-note { margin-top:.3rem; font-size:.7rem; color:var(--text3); line-height:1.4; }
.done-toggle {
  width:64px; height:64px; border-radius:50%; flex-shrink:0;
  display:flex; align-items:center; justify-content:center;
  border:2px solid var(--border); background:none;
  cursor:pointer; transition:all .18s; touch-action:manipulation;
}
.done-toggle svg { width:26px; height:26px; }
.done-toggle .ck { stroke:var(--text3); stroke-width:2.5; fill:none; transition:stroke .18s; }
.done-toggle.active { background:var(--green); border-color:var(--green); }
.done-toggle.active .ck { stroke:#000; }

/* ── Weight row ─────────────────────────────────────────── */
.ex-weight-row {
  display:flex; align-items:center; gap:.45rem;
  padding:.28rem .8rem .45rem;
  border-top:1px solid var(--border);
  background:rgba(0,0,0,.15); flex-shrink:0;
}
/* Weight drag box */
.weight-box {
  width:160px; height:5.8rem; position:relative; flex-shrink:0;
  background:var(--input-bg);
  border:8px solid rgba(255,255,255,.22); border-radius:8px;
  transition:border-color .12s, background .12s; cursor:ew-resize;
}
.weight-box.changed          { border-color:rgba(10,132,255,.8); }
.weight-box.changed .weight-num { color:var(--blue); }
.weight-box.dragging         { border-color:var(--blue); background:rgba(10,132,255,.1); }
/* "WEIGHT (lbs)" label — pinned to top */
.weight-header {
  position:absolute; top:4px; left:0; right:0; text-align:center;
  font-family:'DINBlack',sans-serif; font-size:.58rem; letter-spacing:.1em;
  text-transform:uppercase; color:var(--text3); pointer-events:none;
  white-space:nowrap; line-height:1.4;
}
/* Number — absolutely positioned, adjust top to move up/down */
.weight-num  {
  position:absolute; top:10px; left:0; right:0; text-align:center;
  font-family:'DINBlack',sans-serif; font-size:3rem; color:var(--text);
  pointer-events:none; line-height:1;
}
/* transparent overlay — captures drag events */
.weight-input {
  position:absolute; top:0; left:0; right:0; bottom:0;
  opacity:0; border:none; background:none; outline:none; cursor:ew-resize;
  touch-action:none; user-select:none; -webkit-user-select:none;
}
.weight-note {
  flex:1; min-width:0; margin-left:.4rem;
  font-size:1.2rem; color:var(--text2); line-height:1.35;
  display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical;
  overflow:hidden;
}

/* ── Sets row ───────────────────────────────────────────── */
.ex-sets-row {
  display:grid; grid-template-columns:repeat(4,1fr); gap:.35rem;
  padding:.5rem .8rem; flex-shrink:0;
}
.set-field { display:flex; flex-direction:column; align-items:center; gap:.28rem; }
.set-label { font-family:'DINBlack',sans-serif; font-size:.62rem; letter-spacing:.1em; text-transform:uppercase; color:var(--text3); }

/* ── Set box ────────────────────────────────────────────── */
/* Flex on the box itself — single source of truth for centering.
   No absolute trickery on the number span; .set-input stays absolute
   for pointer events and doesn't affect layout. */
.set-box {
  width:100%; height:5.8rem; position:relative;
  display:flex; align-items:center; justify-content:center;
  background:var(--input-bg);
  border:8px solid rgba(255,255,255,.22); border-radius:10px;
  transition:border-color .12s, background .12s; cursor:ew-resize;
}
.set-box.changed       { border-color:rgba(10,132,255,.8); }
.set-box.changed .set-num { color:var(--blue); }
.set-box.dragging      { border-color:var(--blue); background:rgba(10,132,255,.1); }
/* Invisible input overlays the box for drag events only — out of flex flow */
.set-input {
  position:absolute; top:0; left:0; right:0; bottom:0;
  opacity:0; border:none; background:none; outline:none; cursor:ew-resize;
  touch-action:none; user-select:none; -webkit-user-select:none;
}
/* Number span is a normal flex child — centered by parent.
   margin-bottom biases the flex center upward to optically center DINBlack digits. */
.set-num {
  font-family:'DINBlack',sans-serif; font-size:3rem; color:var(--text);
  pointer-events:none; line-height:1; margin-bottom:26px;
}
.set-sug {
  font-family:'DINBlack',sans-serif; font-size:3rem; color:var(--text);
  pointer-events:none; line-height:1; margin-bottom:26px;
}
.set-placeholder {
  font-size:1.3rem; color:rgba(255,255,255,.18); letter-spacing:.15em;
  pointer-events:none;
}

/* ── Notebook area ──────────────────────────────────────── */
.notebook-area {
  flex:1; min-height:4rem; max-height:33dvh;
  border-top:1px solid var(--border);
  position:relative; overflow:hidden; display:flex; flex-direction:column;
  background:transparent;
}
.notebook-label {
  font-family:'DINBlack',sans-serif; font-size:.6rem; letter-spacing:.12em;
  text-transform:uppercase; color:var(--text3);
  padding:.35rem .9rem .1rem; flex-shrink:0;
}
/* textarea — Caveat handwriting font, ≥16px prevents iOS zoom */
.notebook-area textarea {
  flex:1; display:block; width:100%; min-height:0;
  border:none; outline:none; resize:none;
  color:var(--text); caret-color:var(--blue);
  font-family:'Caveat', cursive;
  font-size:2.7rem;          /* doubled — Caveat reads well at large sizes */
  font-weight:700;
  line-height:var(--line-h);
  padding:2px 3.2rem 3rem .9rem; /* right+bottom clear space for .yt-fab */
  /* Dot lines on textarea directly so they always align with text lines */
  background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='8' height='28'%3E%3Ccircle cx='4' cy='27' r='1' fill='rgba(255%2C255%2C255%2C0.13)'/%3E%3C/svg%3E");
  background-size:8px var(--line-h);
  background-repeat:repeat;
  background-position:0 0;
  background-color:transparent;
}
.notebook-area textarea::placeholder { color:rgba(255,255,255,.15); font-family:'Caveat',cursive; font-weight:700; font-size:2.7rem; }

/* ── Empty state ────────────────────────────────────────── */
.empty-slide {
  height:100%; display:flex; align-items:center; justify-content:center;
  flex-direction:column; gap:.5rem;
  font-family:'DINBlack',sans-serif; font-size:.72rem;
  letter-spacing:.1em; text-transform:uppercase; color:var(--text3);
}

/* ── Drag pill ──────────────────────────────────────────── */
#dragPill {
  position:fixed; z-index:9999;
  display:none; align-items:center; gap:.5rem;
  background:var(--blue); color:#fff;
  border-radius:30px; padding:.4rem 1.1rem;
  pointer-events:none;
  transform:translateX(-50%);
  box-shadow:0 6px 24px rgba(10,132,255,.5);
  white-space:nowrap;
  transition:top .05s;
}
#dragPill.show { display:flex; }
#dpVal {
  font-family:'DINBlack',sans-serif; font-size:1.75rem; line-height:1;
  min-width:1.8rem; text-align:center;
}
.dp-arr {
  font-family:'DINBlack',sans-serif; font-size:1rem; opacity:.3;
  transition:opacity .08s;
}
.dp-arr.hi { opacity:1; }

/* ── Day picker ─────────────────────────────────────────── */
.picker-overlay {
  position:fixed; inset:0; z-index:200;
  background:rgba(0,0,0,.55);
  display:flex; align-items:flex-end;
  opacity:0; pointer-events:none; transition:opacity .22s;
}
.picker-overlay.open { opacity:1; pointer-events:all; }
.picker-sheet {
  width:100%; max-height:70dvh;
  background:#1a1a2a; border-radius:18px 18px 0 0;
  border-top:1px solid var(--border);
  display:flex; flex-direction:column;
  transform:translateY(100%);
  transition:transform .28s cubic-bezier(.32,.72,0,1);
  padding-bottom:env(safe-area-inset-bottom);
}
.picker-overlay.open .picker-sheet { transform:translateY(0); }
.picker-handle { flex-shrink:0; display:flex; justify-content:center; padding:.75rem 0 .35rem; }
.picker-handle-bar { width:36px; height:4px; border-radius:2px; background:rgba(255,255,255,.2); }
.picker-header {
  flex-shrink:0; display:flex; align-items:center; justify-content:space-between;
  padding:.1rem 1rem .7rem; border-bottom:1px solid var(--border);
}
.picker-title {
  font-family:'DINBlack',sans-serif; font-size:.78rem;
  letter-spacing:.08em; text-transform:uppercase; color:var(--text);
}
.picker-close {
  width:26px; height:26px; border-radius:50%;
  background:rgba(255,255,255,.1); color:var(--text2);
  border:none; font-size:1rem; cursor:pointer;
  display:flex; align-items:center; justify-content:center;
  touch-action:manipulation;
}
.picker-list { flex:1; overflow-y:auto; padding:.5rem 0 1rem; -webkit-overflow-scrolling:touch; }
.picker-week-label {
  font-family:'DINBlack',sans-serif; font-size:.58rem;
  letter-spacing:.14em; text-transform:uppercase;
  color:var(--text3); padding:.65rem 1rem .3rem;
}
.picker-day-item {
  width:100%; display:flex; align-items:center; gap:.65rem;
  padding:.65rem 1rem; background:none; border:none;
  cursor:pointer; text-align:left; transition:background .1s;
  touch-action:manipulation;
}
.picker-day-item:active { background:rgba(255,255,255,.06); }
.picker-day-item.current { background:rgba(10,132,255,.12); }
.pdi-num {
  font-family:'DINBlack',sans-serif; font-size:.65rem;
  letter-spacing:.08em; text-transform:uppercase;
  color:var(--blue); flex-shrink:0; min-width:2.5rem;
}
.picker-day-item.current .pdi-num { color:var(--text); }
.pdi-type { font-family:'DINRegular',sans-serif; font-size:.8rem; color:var(--text); flex:1; }
.pdi-done { font-family:'DINBlack',sans-serif; font-size:.65rem; color:var(--green); flex-shrink:0; }

/* ── Desktop centering ──────────────────────────────────── */
@media (min-width:520px) {
  body { align-items:center; background:#06060a; }
  .top-bar, .app { max-width:480px; width:100%; }
  .picker-sheet   { max-width:480px; margin:0 auto; }
}

/* ── Portrait-only overlay ──────────────────────────────── */
/* Shown on phones in landscape (short viewport height = mobile landscape).
   Desktop landscape has tall enough viewports to stay hidden. */
#portraitGuard {
  display:none;
  position:fixed; inset:0; z-index:99999;
  background:var(--bg);
  flex-direction:column; align-items:center; justify-content:center;
  gap:1.2rem; text-align:center; padding:2rem;
}
#portraitGuard svg {
  opacity:.55;
  animation:rotateHint 2s ease-in-out infinite;
}
@keyframes rotateHint {
  0%,100% { transform:rotate(0deg); }
  40%,60%  { transform:rotate(-90deg); }
}
#portraitGuard p {
  font-family:'DINBlack',sans-serif; font-size:.85rem;
  letter-spacing:.1em; text-transform:uppercase; color:var(--text3);
}
@media (orientation:landscape) and (max-height:600px) {
  #portraitGuard { display:flex; }
}
</style>
</head>
<body>

<!-- Portrait-only guard — CSS shows this in landscape on phones -->
<div id="portraitGuard" aria-hidden="true">
  <svg width="52" height="52" viewBox="0 0 24 24" fill="none" stroke="var(--text2)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
    <rect x="5" y="2" width="14" height="20" rx="2"/>
    <line x1="9" y1="21" x2="15" y2="21"/>
  </svg>
  <p>Rotate to portrait</p>
</div>

<!-- Top bar — outside .app so it's never affected by app overflow/resize -->
<header class="top-bar">
  <div class="top-bar-brand">
    <span class="brand-fit">Fit</span><span class="brand-board">Board</span>
  </div>
  <div class="top-bar-right">
    <span id="saveStatus" class="save-status-inline"></span>
    <!-- lbs / kg unit toggle -->
    <div class="unit-toggle" onclick="setUnit(weightUnit==='lbs'?'kg':'lbs')" role="button" aria-label="Toggle weight unit">
      <span id="unitLbs" class="unit-btn active">lbs</span>
      <span id="unitKg"  class="unit-btn">kg</span>
    </div>
    <!-- Calendar icon — tapping opens native date picker via the hidden input -->
    <div class="cal-btn" id="calBtn" title="Workout date">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="18" rx="2"/>
        <line x1="16" y1="2" x2="16" y2="6"/>
        <line x1="8"  y1="2" x2="8"  y2="6"/>
        <line x1="3"  y1="10" x2="21" y2="10"/>
      </svg>
      <input type="date" id="dayDateInput" />
    </div>
    <button class="sync-btn" id="syncBtn" onclick="syncData()" title="Sync from sheet">&#8635;</button>
    <button class="save-btn" id="topSaveBtn" onclick="saveWorkout()" disabled>Save</button>
  </div>
</header>

<!-- App shell fills remaining viewport height -->
<div class="app">

  <!-- Day header -->
  <div class="day-header">
    <div class="day-meta">
      <div class="day-label" id="dayLabel">Tap to select a day</div>
      <div class="day-title-row" onclick="openDayPicker()" role="button" aria-haspopup="true">
        <div class="day-title" id="dayTitle">—</div>
        <svg class="day-chevron" width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
          <polyline points="2,4.5 7,9.5 12,4.5"/>
        </svg>
      </div>
    </div>
    <div class="ring-wrap">
      <svg class="ring" viewBox="0 0 100 100">
        <circle class="ring-bg" cx="50" cy="50" r="32"/>
        <circle class="ring-fill" id="ringFill" cx="50" cy="50" r="32"
          style="stroke-dasharray:201;stroke-dashoffset:201"/>
      </svg>
      <div class="ring-center">
        <div class="ring-count" id="ringCount">0/0</div>
        <div class="ring-sub">done</div>
      </div>
    </div>
  </div>

  <!-- Rolodex -->
  <div class="rolodex-wrap">
    <div class="rolodex-viewport" id="rolodexViewport">
      <div class="empty-slide">
        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" opacity=".4">
          <rect x="2" y="10" width="4" height="4" rx="1"/><rect x="18" y="10" width="4" height="4" rx="1"/>
          <rect x="6" y="8" width="12" height="8" rx="2"/>
          <line x1="0" y1="12" x2="2" y2="12"/><line x1="22" y1="12" x2="24" y2="12"/>
        </svg>
        Tap the day title to select a workout
      </div>
    </div>
    <div class="rolodex-dots" id="rolodexDots"></div>
  </div>

</div><!-- .app -->

<!-- Day picker bottom sheet -->
<div class="picker-overlay" id="pickerOverlay" onclick="if(event.target===this)closeDayPicker()">
  <div class="picker-sheet">
    <div class="picker-handle"><div class="picker-handle-bar"></div></div>
    <div class="picker-header">
      <span class="picker-title">Select Workout Day</span>
      <button class="picker-close" onclick="closeDayPicker()">×</button>
    </div>
    <div class="picker-list" id="pickerList"></div>
  </div>
</div>

<!-- Drag pill — shows live rep count while sliding set inputs -->
<div id="dragPill">
  <span id="dpLeft"  class="dp-arr">‹</span>
  <span id="dpVal">0</span>
  <span id="dpRight" class="dp-arr">›</span>
</div>

<script>
'use strict';

// ── Lock to portrait (Android PWA / Chrome) ────────────────
// Falls back silently to the CSS overlay on iOS and plain browser.
if (screen.orientation && typeof screen.orientation.lock === 'function') {
  screen.orientation.lock('portrait').catch(function(){});
}

var sheetId  = getSheetId();
var APP_BASE = document.querySelector('base').getAttribute('href');
var BASE     = APP_BASE + 'sheets/' + sheetId + '/';

var weekData     = [];
var dayGroups    = {};
var dayKeys      = [];
var weekMap      = {};
var curWeek      = 1;
var curDayIdx    = 0;
var curRows      = [];
var isDirty      = false;
var openVideoIdx = -1;
var weightUnit   = localStorage.getItem('fitWeightUnit') || 'lbs';

// ── Sheet ID ───────────────────────────────────────────────
function getSheetId() {
  var parts = window.location.pathname.split('/').filter(Boolean);
  var idx = parts.indexOf('sheets');
  if (idx >= 0 && parts[idx+1]) return parts[idx+1];
  var fi = parts.lastIndexOf('fitboard');
  if (fi > 0) return parts[fi-1];
  var m = window.location.search.match(/[?&]id=([^&]+)/);
  return m ? m[1] : '';
}

// ── Parse day key ──────────────────────────────────────────
function parseWeek(dk)  { var m=dk.match(/Week\s+(\d+)/i);    return m?parseInt(m[1]):0; }
function parseDayNum(dk){ var m=dk.match(/Day\s+(\d+)/i);     return m?parseInt(m[1]):0; }
function parseType(dk)  { var m=dk.match(/[–-]\s*([^–-]+)$/); return m?m[1].trim():''; }
function curDayKey()    { return (weekMap[curWeek]||[])[curDayIdx]||null; }

// ── Date helpers ───────────────────────────────────────────
function pad2(n){ return n<10?'0'+n:String(n); }
function toDateInput(s) {
  if (!s) return '';
  var c=s.replace(/^\s*0\s*/,'').trim();
  var m=c.match(/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/);
  if(m){ var y=parseInt(m[3]); if(y<100) y+=2000; return y+'-'+pad2(parseInt(m[1]))+'-'+pad2(parseInt(m[2])); }
  m=c.match(/^(\d{1,2})\/(\d{1,2})$/);
  if(m) return new Date().getFullYear()+'-'+pad2(parseInt(m[1]))+'-'+pad2(parseInt(m[2]));
  return '';
}
function fromDateInput(v) {
  if(!v) return '';
  var m=v.match(/^(\d{4})-(\d{2})-(\d{2})$/);
  return m ? parseInt(m[2])+'/'+parseInt(m[3]) : v;
}
function todayIso() {
  var d=new Date();
  return d.getFullYear()+'-'+pad2(d.getMonth()+1)+'-'+pad2(d.getDate());
}

// ── YouTube embed ──────────────────────────────────────────
function getYTEmbed(url) {
  if(!url) return null;
  var id=null, isShort=false;
  var m=url.match(/shorts\/([A-Za-z0-9_\-]+)/);
  if(m){ id=m[1]; isShort=true; }
  if(!id){ m=url.match(/youtu\.be\/([A-Za-z0-9_\-]+)/); if(m) id=m[1]; }
  if(!id){ m=url.match(/[?&]v=([A-Za-z0-9_\-]+)/);     if(m) id=m[1]; }
  if(!id) return null;
  return { url:'https://www.youtube.com/embed/'+id+'?autoplay=1&mute=1&rel=0&playsinline=1', isShort:isShort };
}

// ── Misc ───────────────────────────────────────────────────
function fmtNum(n){ return Number(n).toLocaleString(); }
function kgFromLbs(v){ var n=parseFloat(v); return isNaN(n)?'':+(n/2.2046).toFixed(1)+' kg'; }

// ── lbs / kg toggle ────────────────────────────────────────
function setUnit(u){
  weightUnit=u;
  localStorage.setItem('fitWeightUnit',u);
  document.getElementById('unitLbs').classList.toggle('active',u==='lbs');
  document.getElementById('unitKg' ).classList.toggle('active',u==='kg');
  // Update all rendered exercise cards
  curRows.forEach(function(row,i){
    var inp =document.getElementById('w'+i);
    var unit=document.getElementById('wunit'+i);
    var sp  =document.getElementById('wdisp'+i);
    var box =document.getElementById('wbox'+i);
    if(!inp) return;
    var wLbs=row['Weight (lbs)']||'';
    var wKg =row['Weight (kg)'] ||'';
    var val = u==='kg' ? wKg : wLbs;
    inp.value = val;
    if(unit) unit.textContent = u==='kg'?'kg':'lbs';
    if(sp)   sp.textContent   = val || '—';
    // Don't touch 'changed' state when only switching units
  });
}
// Apply saved preference once DOM is ready
document.addEventListener('DOMContentLoaded',function(){
  document.getElementById('unitLbs').classList.toggle('active',weightUnit==='lbs');
  document.getElementById('unitKg' ).classList.toggle('active',weightUnit==='kg');
});
function escH(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }
function calcReps(s1,s2,s3,s4){
  var t=0,has=false;
  [s1,s2,s3,s4].forEach(function(v){ var n=parseInt(v); if(!isNaN(n)){t+=n;has=true;} });
  return has?t:null;
}
function calcVol(w,reps){
  if(reps===null) return null;
  var wt=parseFloat(w); if(isNaN(wt)||wt===0) return null;
  return Math.round(wt*reps);
}

// ── Parse target reps from "3x8", "4x6-8", "3 x 10–12" ───
function parseTargetSets(str) {
  if(!str) return 4;
  // "3x10", "3 x 10", "3×10-12" — first number before the x is sets
  var m=str.match(/^(\d+)\s*[xX×]/);
  if(m) return Math.min(parseInt(m[1]),4);
  return 4; // default all four
}
function parseTargetRep(str) {
  if(!str) return 0;
  // Match the reps part after "x" or "×": "3x8" → 8, "4x6-8" → 7 (midpoint)
  var m=str.match(/[xX×]\s*(\d+)(?:\s*[-–]\s*(\d+))?/);
  if(m){
    var lo=parseInt(m[1]), hi=m[2]?parseInt(m[2]):lo;
    return Math.round((lo+hi)/2);
  }
  // Fallback: take the last number in the string
  var nums=str.match(/\d+/g);
  return nums ? parseInt(nums[nums.length-1]) : 0;
}

// ── Load & build ───────────────────────────────────────────
var WEEK_CACHE_KEY = 'fitboard_week_' + sheetId;

function cacheWeekData(data){
  try { localStorage.setItem(WEEK_CACHE_KEY, JSON.stringify(data)); } catch(e){}
}

function loadData(){
  fetch(BASE+'week.json?v='+Date.now())
    .then(function(r){
      if(!r.ok) throw new Error('HTTP '+r.status);
      return r.json();
    })
    .then(function(data){
      weekData=data;
      cacheWeekData(data);   // keep cache fresh
      buildGroups();
      autoSelectActive();
    })
    .catch(function(){
      // Offline or server error — try local cache
      var cached=null;
      try { cached=JSON.parse(localStorage.getItem(WEEK_CACHE_KEY)); } catch(e){}
      if(cached && cached.length){
        weekData=cached;
        buildGroups();
        autoSelectActive();
      } else {
        document.getElementById('rolodexViewport').innerHTML=
          '<div class="empty-slide">No data available offline. Tap &#8635; when connected.</div>';
      }
    });
}

function buildGroups(){
  dayGroups={}; dayKeys=[]; weekMap={};
  weekData.forEach(function(row){
    var dk=row['Day']||''; if(!dk) return;
    if(!dayGroups[dk]){ dayGroups[dk]=[]; dayKeys.push(dk); }
    dayGroups[dk].push(row);
  });
  dayKeys.forEach(function(dk){
    var wn=parseWeek(dk), dn=parseDayNum(dk);
    if(!weekMap[wn]) weekMap[wn]=[null,null,null,null];
    if(dn>=1&&dn<=4) weekMap[wn][dn-1]=dk;
  });
}

function autoSelectActive(){
  var weeks=Object.keys(weekMap).map(Number).sort(function(a,b){return a-b;});
  var best=weeks[0]||1;
  weeks.forEach(function(wn){
    var hasData=(weekMap[wn]||[]).some(function(dk){
      if(!dk) return false;
      return (dayGroups[dk]||[]).some(function(r){ return r['Done']==='Yes'||r['Set 1']; });
    });
    if(hasData) best=wn;
  });
  curWeek=best;
  var slots=weekMap[curWeek]||[];
  curDayIdx=0;
  for(var i=0;i<slots.length;i++){
    var dk=slots[i]; if(!dk) continue;
    var allDone=(dayGroups[dk]||[]).every(function(r){return r['Done']==='Yes';});
    if(!(dayGroups[dk]||[]).length||!allDone){ curDayIdx=i; break; }
    if(i===slots.length-1) curDayIdx=i;
  }
  selectDay(curWeek,curDayIdx);
}

// ── Select day ─────────────────────────────────────────────
function selectDay(wn,di){
  curWeek=wn; curDayIdx=di;
  openVideoIdx=-1;
  var dk=(weekMap[wn]||[])[di]||null;
  if(!dk){ curRows=[]; renderDayHeader(null); renderRolodex(); return; }
  curRows=(dayGroups[dk]||[]).map(function(r){return Object.assign({},r);});
  renderDayHeader(dk);
  renderRolodex();
  updateRing();
  setDirty(false);
}

// ── Day header ─────────────────────────────────────────────
function renderDayHeader(dk){
  if(!dk){
    document.getElementById('dayLabel').textContent='Tap to select a day';
    document.getElementById('dayTitle').textContent='—';
    document.getElementById('dayDateInput').value='';
    return;
  }
  var wn=parseWeek(dk), dn=parseDayNum(dk), type=parseType(dk);
  document.getElementById('dayLabel').textContent='WEEK '+wn+' · DAY '+dn;
  document.getElementById('dayTitle').textContent=type.toUpperCase();
  var date=''; curRows.forEach(function(r){if(!date&&r['Date']) date=r['Date'];});
  document.getElementById('dayDateInput').value=date?toDateInput(date):todayIso();
}

// ── Progress ring ──────────────────────────────────────────
function updateRing(){
  var total=curRows.length;
  var done=curRows.filter(function(r){return r['Done']==='Yes';}).length;
  var pct=total>0?done/total:0;
  var fill=document.getElementById('ringFill');
  fill.style.strokeDashoffset=2*Math.PI*32*(1-pct);
  fill.style.stroke=pct>=1?'var(--green)':pct>0?'var(--blue)':'var(--border)';
  document.getElementById('ringCount').textContent=done+'/'+total;
}

// ── Rolodex ────────────────────────────────────────────────
function renderRolodex(){
  var vp=document.getElementById('rolodexViewport');
  if(!curRows.length){
    vp.innerHTML='<div class="empty-slide">No exercises for this day</div>';
    document.getElementById('rolodexDots').innerHTML='';
    return;
  }
  var html='';
  curRows.forEach(function(row,i){ html+=buildSlide(row,i); });
  vp.innerHTML=html;
  renderDots();
  attachObserver();
  attachSetSliders();    // ← wire up slide-to-set after DOM is built
  attachWeightSliders(); // ← wire up slide-to-weight
}

function buildSlide(row,i){
  var done=row['Done']==='Yes';
  var hasYT=!!(row['YT Video Link']&&row['YT Video Link'].startsWith('http'));
  var wLbs=row['Weight (lbs)']||'';
  var wKg =row['Weight (kg)'] ||'';
  var w   =weightUnit==='kg'?wKg:wLbs;   // display value in current unit
  var s=[row['Set 1']||'',row['Set 2']||'',row['Set 3']||'',row['Set 4']||''];
  var sugRep =parseTargetRep(row['Target Sets/Reps']||''); // faded rep hint
  var sugSets=parseTargetSets(row['Target Sets/Reps']||''); // how many boxes get the hint
  var notes=row['My Notes']||'';

  var h='<div class="ex-slide" data-idx="'+i+'">';
  h+='<div class="ex-card'+(done?' is-done':'')+'" id="card'+i+'">';

  // Video section
  h+='<div class="ex-video-section" id="vidSec'+i+'"></div>';

  // Card top
  h+='<div class="ex-card-top">';
  h+='<div class="ex-info">';
  h+='<div class="ex-name-row">'
    +'<div class="ex-name">'+escH(row['Exercise']||'')+'</div>'
    +(row['Target Sets/Reps']?'<span class="ex-target">'+escH(row['Target Sets/Reps'])+'</span>':'')
    +'</div>';
  h+='</div>';
  h+='<button class="done-toggle'+(done?' active':'')+'" id="done'+i+'" onclick="toggleDone('+i+')">'
    +'<svg viewBox="0 0 16 16"><path class="ck" d="M2.5 8.5L6 12 13.5 4"/></svg></button>';
  h+='</div>'; // .ex-card-top

  // Weight + Notes
  var wHasVal=w!=='';
  h+='<div class="ex-weight-row">'
    +'<div class="weight-box" id="wbox'+i+'">'
    +'<span class="weight-header">WEIGHT (<span id="wunit'+i+'">'+(weightUnit==='kg'?'kg':'lbs')+'</span>)</span>'
    +'<span class="weight-num" id="wdisp'+i+'">'+(wHasVal?escH(w):'—')+'</span>'
    +'<input type="text" inputmode="none" readonly class="weight-input" id="w'+i+'" value="'+escH(w)+'" data-wi="'+i+'" data-orig="'+escH(w)+'">'
    +'</div>'
    +(row['Notes']?'<span class="weight-note">'+escH(row['Notes'])+'</span>':'')
    +'</div>';

  // Sets — flex-centered wrapper + transparent overlay input for drag events
  h+='<div class="ex-sets-row">';
  ['SET 1','SET 2','SET 3','SET 4'].forEach(function(lbl,si){
    var val=s[si];
    var hasVal=val!=='';
    h+='<div class="set-field"><span class="set-label">'+lbl+'</span>'
      +'<div class="set-box" id="sbox'+i+'_'+si+'">'
      +'<span id="s'+i+'_'+si+'_disp" class="'+(hasVal?'set-num':(si<sugSets&&sugRep>0?'set-sug':'set-placeholder'))+'">'+(hasVal?escH(val):(si<sugSets&&sugRep>0?sugRep:'‹ ›'))+'</span>'
      +'<input type="text" inputmode="none" readonly class="set-input"'
      +' id="s'+i+'_'+si+'"'
      +' value="'+escH(val)+'"'
      +' data-ci="'+i+'" data-si="'+si+'"></div></div>';
  });
  h+='</div>';

  // Notebook notes — "My Notes" label + textarea (≥16px font = no iOS zoom)
  h+='<div class="notebook-area">'
    +'<div class="notebook-label">My Notes</div>'
    +'<textarea id="n'+i+'" placeholder="Tap to add notes…" oninput="onNotesChange('+i+')">'+escH(notes)+'</textarea>'
    +'</div>';

  // Circular FAB — play or close video (only when YT link exists)
  if(hasYT){
    h+='<button class="yt-fab" id="ytBtn'+i+'" onclick="toggleVideo('+i+')" aria-label="Play video">'
      +'<svg viewBox="0 0 9 9" fill="white" id="ytBtnIcon'+i+'"><polygon points="1.5,0.5 8.5,4.5 1.5,8.5"/></svg>'
      +'</button>';
  }

  h+='</div></div>';
  return h;
}

// ── Dot indicator ──────────────────────────────────────────
function renderDots(){
  var html='';
  for(var i=0;i<Math.min(curRows.length,14);i++) html+='<div class="rdot" id="dot'+i+'"></div>';
  document.getElementById('rolodexDots').innerHTML=html;
  updateDot(0);
}
function updateDot(idx){
  document.querySelectorAll('.rdot').forEach(function(d,i){ d.classList.toggle('active',i===idx); });
}
var cardObserver=null;
function attachObserver(){
  if(cardObserver) cardObserver.disconnect();
  var vp=document.getElementById('rolodexViewport');
  cardObserver=new IntersectionObserver(function(entries){
    entries.forEach(function(e){
      if(e.isIntersecting&&e.intersectionRatio>=0.5)
        updateDot(parseInt(e.target.dataset.idx||'0'));
    });
  },{threshold:0.5,root:vp});
  document.querySelectorAll('.ex-slide').forEach(function(s){ cardObserver.observe(s); });
}

// ── Slide-to-set reps ──────────────────────────────────────
var DRAG = null;
var PX_PER_REP = 12; // horizontal pixels per 1-rep change

// ── Long-press to reset ─────────────────────────────────────
var LPRESS_TIMER     = null;
var LPRESS_DELAY     = 600;  // ms hold before reset fires
var LPRESS_THRESHOLD = 8;    // px of movement that cancels the hold
var LPRESS_MOVED     = false;
var LPRESS_START_X   = 0;
var LPRESS_START_Y   = 0;

function resetSet(ci, si) {
  var inp = document.getElementById('s'+ci+'_'+si);
  var box = document.getElementById('sbox'+ci+'_'+si);
  if(!inp || !box) return;
  inp.value = '';
  box.classList.remove('changed');
  updateSetDisp(inp, 0);
  applySetVal(ci, si, 0);
  setDirty(true);
  // Brief pink flash — visual confirmation of reset
  box.style.borderColor = 'var(--pink)';
  setTimeout(function(){ box.style.borderColor = ''; }, 400);
}

function resetWeight(wi) {
  var inp = document.getElementById('w'+wi);
  if(!inp) return;
  inp.value = inp.dataset.orig || '';
  onWeightChange(wi);
  var box = document.getElementById('wbox'+wi);
  if(box){
    box.style.borderColor = 'var(--pink)';
    setTimeout(function(){ box.style.borderColor = ''; }, 400);
  }
}

function attachSetSliders(){
  document.querySelectorAll('.set-input').forEach(function(inp){
    inp.addEventListener('touchstart', onSetTouchStart, {passive:false});
    inp.addEventListener('mousedown',  onSetMouseDown);
  });
}

// ── Slide-to-set weight ────────────────────────────────────
var WDRAG = null;
var PX_PER_WEIGHT = 6; // px per one step (2.5 lbs or 1 kg)

function attachWeightSliders(){
  document.querySelectorAll('.weight-input').forEach(function(inp){
    inp.addEventListener('touchstart', onWeightTouchStart, {passive:false});
    inp.addEventListener('mousedown',  onWeightMouseDown);
  });
}

function startWeightDrag(inp, clientX){
  var wi  = parseInt(inp.dataset.wi);
  var box = inp.parentElement;
  var cur = parseFloat(inp.value) || 0;
  WDRAG={inp:inp, box:box, wi:wi, startX:clientX, startVal:cur};
  box.classList.add('dragging');
  showPill(box, cur, 0);
}

function moveWeightDrag(clientX){
  if(!WDRAG) return;
  var step  = weightUnit==='kg' ? 1 : 2.5;
  var delta = clientX - WDRAG.startX;
  var steps = Math.round(delta / PX_PER_WEIGHT);
  var raw   = WDRAG.startVal + steps * step;
  var newVal= Math.max(0, Math.round(raw / step) * step);
  var dispVal = newVal > 0 ? (Number.isInteger(newVal) ? String(newVal) : newVal.toFixed(1)) : '';
  WDRAG.inp.value = dispVal;
  WDRAG.box.classList.toggle('changed', newVal > 0);
  var sp = document.getElementById(WDRAG.inp.id + '_disp') ||
           document.getElementById('wdisp' + WDRAG.wi);
  if(sp) sp.textContent = newVal > 0 ? (dispVal || newVal) : '—';
  showPill(WDRAG.box, newVal, delta);
}

function endWeightDrag(){
  if(!WDRAG) return;
  WDRAG.box.classList.remove('dragging');
  hidePill();
  onWeightChange(WDRAG.wi);
  WDRAG = null;
}

function onWeightTouchStart(e){
  e.preventDefault();
  var inp=e.currentTarget, t=e.touches[0];
  LPRESS_MOVED=false; LPRESS_START_X=t.clientX; LPRESS_START_Y=t.clientY;
  LPRESS_TIMER=setTimeout(function(){
    LPRESS_TIMER=null;
    if(LPRESS_MOVED) return;
    if(WDRAG){ WDRAG.box.classList.remove('dragging'); hidePill(); WDRAG=null; }
    document.removeEventListener('touchmove', onWeightTouchMove);
    resetWeight(parseInt(inp.dataset.wi));
  }, LPRESS_DELAY);
  startWeightDrag(inp, t.clientX);
  document.addEventListener('touchmove', onWeightTouchMove, {passive:false});
  document.addEventListener('touchend',  onWeightTouchEnd,  {once:true});
}
function onWeightTouchMove(e){
  e.preventDefault();
  var dx=e.touches[0].clientX-LPRESS_START_X, dy=e.touches[0].clientY-LPRESS_START_Y;
  if(!LPRESS_MOVED && (Math.abs(dx)>LPRESS_THRESHOLD || Math.abs(dy)>LPRESS_THRESHOLD)){
    LPRESS_MOVED=true;
    if(LPRESS_TIMER){ clearTimeout(LPRESS_TIMER); LPRESS_TIMER=null; }
  }
  moveWeightDrag(e.touches[0].clientX);
}
function onWeightTouchEnd(){
  if(LPRESS_TIMER){ clearTimeout(LPRESS_TIMER); LPRESS_TIMER=null; }
  endWeightDrag();
  document.removeEventListener('touchmove', onWeightTouchMove);
}
function onWeightMouseDown(e){
  var inp=e.currentTarget;
  LPRESS_MOVED=false; LPRESS_START_X=e.clientX; LPRESS_START_Y=e.clientY;
  LPRESS_TIMER=setTimeout(function(){
    LPRESS_TIMER=null;
    if(LPRESS_MOVED) return;
    if(WDRAG){ WDRAG.box.classList.remove('dragging'); hidePill(); WDRAG=null; }
    document.removeEventListener('mousemove', onWeightMouseMove);
    resetWeight(parseInt(inp.dataset.wi));
  }, LPRESS_DELAY);
  startWeightDrag(inp, e.clientX);
  document.addEventListener('mousemove', onWeightMouseMove);
  document.addEventListener('mouseup',   onWeightMouseUp, {once:true});
}
function onWeightMouseMove(e){
  var dx=e.clientX-LPRESS_START_X, dy=e.clientY-LPRESS_START_Y;
  if(!LPRESS_MOVED && (Math.abs(dx)>LPRESS_THRESHOLD || Math.abs(dy)>LPRESS_THRESHOLD)){
    LPRESS_MOVED=true;
    if(LPRESS_TIMER){ clearTimeout(LPRESS_TIMER); LPRESS_TIMER=null; }
  }
  moveWeightDrag(e.clientX);
}
function onWeightMouseUp(){
  if(LPRESS_TIMER){ clearTimeout(LPRESS_TIMER); LPRESS_TIMER=null; }
  endWeightDrag();
  document.removeEventListener('mousemove', onWeightMouseMove);
}

function updateSetDisp(inp, val){
  var sp=document.getElementById(inp.id+'_disp');
  if(!sp) return;
  if(val>0){
    sp.className='set-num'; sp.textContent=val;
  } else {
    var ci =parseInt(inp.dataset.ci);
    var si =parseInt(inp.dataset.si);
    var tr  =(curRows[ci]||{})['Target Sets/Reps']||'';
    var sug =parseTargetRep(tr);
    var sets=parseTargetSets(tr);
    if(sug>0 && si<sets){ sp.className='set-sug'; sp.textContent=sug; }
    else                 { sp.className='set-placeholder'; sp.textContent='‹ ›'; }
  }
}

function startDrag(inp, clientX){
  var ci = parseInt(inp.dataset.ci);
  var si = parseInt(inp.dataset.si);
  var box= inp.parentElement;
  var cur = parseInt(inp.value) || 0;
  if(cur===0){
    var target=parseTargetRep((curRows[ci]||{})['Target Sets/Reps']||'');
    if(target>0){
      cur=target;
      inp.value=cur;
      box.classList.add('changed');
      updateSetDisp(inp, cur);
      applySetVal(ci,si,cur);
    }
  }
  DRAG={inp:inp, box:box, ci:ci, si:si, startX:clientX, startVal:cur};
  box.classList.add('dragging');
  showPill(box, cur, 0);
}

function moveDrag(clientX){
  if(!DRAG) return;
  var delta=clientX-DRAG.startX;
  var newVal=Math.max(0, Math.round(DRAG.startVal + delta/PX_PER_REP));
  DRAG.inp.value = newVal>0 ? String(newVal) : '';
  DRAG.box.classList.toggle('changed', newVal>0);
  updateSetDisp(DRAG.inp, newVal);
  applySetVal(DRAG.ci, DRAG.si, newVal);
  showPill(DRAG.box, newVal, delta);
}

function endDrag(){
  if(!DRAG) return;
  DRAG.box.classList.remove('dragging');
  hidePill();
  setDirty(true);
  DRAG=null;
}

function onSetTouchStart(e){
  e.preventDefault();
  var inp=e.currentTarget, t=e.touches[0];
  LPRESS_MOVED=false; LPRESS_START_X=t.clientX; LPRESS_START_Y=t.clientY;
  LPRESS_TIMER=setTimeout(function(){
    LPRESS_TIMER=null;
    if(LPRESS_MOVED) return;
    if(DRAG){ DRAG.box.classList.remove('dragging'); hidePill(); DRAG=null; }
    document.removeEventListener('touchmove', onSetTouchMove);
    resetSet(parseInt(inp.dataset.ci), parseInt(inp.dataset.si));
  }, LPRESS_DELAY);
  startDrag(inp, t.clientX);
  document.addEventListener('touchmove', onSetTouchMove, {passive:false});
  document.addEventListener('touchend',  onSetTouchEnd,  {once:true});
}
function onSetTouchMove(e){
  e.preventDefault();
  var dx=e.touches[0].clientX-LPRESS_START_X, dy=e.touches[0].clientY-LPRESS_START_Y;
  if(!LPRESS_MOVED && (Math.abs(dx)>LPRESS_THRESHOLD || Math.abs(dy)>LPRESS_THRESHOLD)){
    LPRESS_MOVED=true;
    if(LPRESS_TIMER){ clearTimeout(LPRESS_TIMER); LPRESS_TIMER=null; }
  }
  moveDrag(e.touches[0].clientX);
}
function onSetTouchEnd(){
  if(LPRESS_TIMER){ clearTimeout(LPRESS_TIMER); LPRESS_TIMER=null; }
  endDrag();
  document.removeEventListener('touchmove', onSetTouchMove);
}

function onSetMouseDown(e){
  var inp=e.currentTarget;
  LPRESS_MOVED=false; LPRESS_START_X=e.clientX; LPRESS_START_Y=e.clientY;
  LPRESS_TIMER=setTimeout(function(){
    LPRESS_TIMER=null;
    if(LPRESS_MOVED) return;
    if(DRAG){ DRAG.box.classList.remove('dragging'); hidePill(); DRAG=null; }
    document.removeEventListener('mousemove', onSetMouseMove);
    resetSet(parseInt(inp.dataset.ci), parseInt(inp.dataset.si));
  }, LPRESS_DELAY);
  startDrag(inp, e.clientX);
  document.addEventListener('mousemove', onSetMouseMove);
  document.addEventListener('mouseup',   onSetMouseUp, {once:true});
}
function onSetMouseMove(e){
  var dx=e.clientX-LPRESS_START_X, dy=e.clientY-LPRESS_START_Y;
  if(!LPRESS_MOVED && (Math.abs(dx)>LPRESS_THRESHOLD || Math.abs(dy)>LPRESS_THRESHOLD)){
    LPRESS_MOVED=true;
    if(LPRESS_TIMER){ clearTimeout(LPRESS_TIMER); LPRESS_TIMER=null; }
  }
  moveDrag(e.clientX);
}
function onSetMouseUp(){
  if(LPRESS_TIMER){ clearTimeout(LPRESS_TIMER); LPRESS_TIMER=null; }
  endDrag();
  document.removeEventListener('mousemove', onSetMouseMove);
}

function applySetVal(ci, si, val){
  curRows[ci]['Set '+(si+1)] = val>0 ? String(val) : '';
  recalcTotals(ci);
}

// ── Drag pill ──────────────────────────────────────────────
function showPill(inp, val, delta){
  var pill=document.getElementById('dragPill');
  var rect=inp.getBoundingClientRect();
  pill.style.left=(rect.left+rect.width/2)+'px';
  pill.style.top =Math.max(80, rect.top-72)+'px';
  document.getElementById('dpVal').textContent = val>0 ? val : '0';
  document.getElementById('dpLeft').className  ='dp-arr'+(delta<-8?' hi':'');
  document.getElementById('dpRight').className ='dp-arr'+(delta> 8?' hi':'');
  pill.classList.add('show');
}
function hidePill(){ document.getElementById('dragPill').classList.remove('show'); }

// ── Video toggle ───────────────────────────────────────────
function toggleVideo(i){
  var sec=document.getElementById('vidSec'+i);
  var btn=document.getElementById('ytBtn'+i);
  var icon=document.getElementById('ytBtnIcon'+i);
  var card=document.getElementById('card'+i);

  // ── Closing: this card's video is already open — slide up ──
  if(openVideoIdx===i){
    openVideoIdx=-1;
    sec.classList.remove('open');
    sec.classList.add('closing');
    if(btn)  btn.classList.remove('open');
    if(icon) icon.innerHTML='<polygon points="1.5,0.5 8.5,4.5 1.5,8.5" fill="white"/>';
    sec.addEventListener('transitionend', function cleanup(){
      sec.removeEventListener('transitionend', cleanup);
      sec.innerHTML='';
      sec.classList.remove('closing');
      card.classList.remove('video-mode');
    }, false);
    return;
  }

  // ── Snap-close any other open card instantly (no animation) ──
  if(openVideoIdx>=0){
    var ps=document.getElementById('vidSec'+openVideoIdx);
    var pc=document.getElementById('card'+openVideoIdx);
    var pb=document.getElementById('ytBtn'+openVideoIdx);
    var pi=document.getElementById('ytBtnIcon'+openVideoIdx);
    if(ps){ ps.innerHTML=''; ps.classList.remove('open','closing'); }
    if(pc) pc.classList.remove('video-mode');
    if(pb) pb.classList.remove('open');
    if(pi) pi.innerHTML='<polygon points="1.5,0.5 8.5,4.5 1.5,8.5" fill="white"/>';
  }

  // ── Opening: add video-mode, inject iframe, force reflow, slide down ──
  var embed=getYTEmbed(curRows[i]['YT Video Link']||'');
  if(!embed) return;
  card.classList.add('video-mode');
  sec.innerHTML='<div class="video-iframe-wrap '+(embed.isShort?'short':'wide')+'">'
    +'<iframe src="'+embed.url+'" allow="autoplay;accelerometer;clipboard-write;encrypted-media;gyroscope;picture-in-picture" allowfullscreen></iframe></div>';
  void sec.offsetWidth; // force reflow so transition fires from closed state
  sec.classList.add('open');
  if(btn)  btn.classList.add('open');
  if(icon) icon.innerHTML='<line x1="1.5" y1="1.5" x2="7.5" y2="7.5" stroke="white" stroke-width="1.8" stroke-linecap="round"/><line x1="7.5" y1="1.5" x2="1.5" y2="7.5" stroke="white" stroke-width="1.8" stroke-linecap="round"/>';
  openVideoIdx=i;
}

// ── Field changes ──────────────────────────────────────────
function toggleDone(i){
  var row=curRows[i];
  row['Done']=(row['Done']==='Yes')?'No':'Yes';
  document.getElementById('done'+i).classList.toggle('active',row['Done']==='Yes');
  document.getElementById('card'+i).classList.toggle('is-done',row['Done']==='Yes');
  updateRing(); setDirty(true);
}
function onWeightChange(i){
  var inp=document.getElementById('w'+i);
  var v=inp?inp.value:'';
  var sp=document.getElementById('wdisp'+i);
  if(sp) sp.textContent=v||'—';
  var box=document.getElementById('wbox'+i);
  if(box) box.classList.toggle('changed', !!v && v!==(inp.dataset.orig||''));
  if(weightUnit==='kg'){
    curRows[i]['Weight (kg)']=v;
    curRows[i]['Weight (lbs)']=v?(parseFloat(v)*2.2046).toFixed(1):'';
  } else {
    curRows[i]['Weight (lbs)']=v;
    curRows[i]['Weight (kg)']=v?(parseFloat(v)/2.2046).toFixed(1):'';
  }
  recalcTotals(i); setDirty(true);
}
function onNotesChange(i){
  curRows[i]['My Notes']=document.getElementById('n'+i).value;
  setDirty(true);
}
function recalcTotals(i){
  var r=curRows[i];
  var reps=calcReps(r['Set 1'],r['Set 2'],r['Set 3'],r['Set 4']);
  var vol=calcVol(r['Weight (lbs)'],reps);
  r['Total Reps']        =reps!==null?String(reps):'';
  r['Total Volume (lbs)']=vol!==null?String(vol):'';
}

// ── Day picker ─────────────────────────────────────────────
function openDayPicker(){
  var weeks=Object.keys(weekMap).map(Number).sort(function(a,b){return a-b;});
  var html='', cur=curDayKey();
  weeks.forEach(function(wn){
    html+='<div class="picker-week-label">Week '+wn+'</div>';
    (weekMap[wn]||[]).forEach(function(dk){
      if(!dk) return;
      var dn=parseDayNum(dk), type=parseType(dk);
      var rows=dayGroups[dk]||[];
      var allDone=rows.length>0&&rows.every(function(r){return r['Done']==='Yes';});
      html+='<button class="picker-day-item'+(dk===cur?' current':'')+(allDone?' done':'')+'" '
        +'onclick="selectDayFromPicker('+wn+','+(dn-1)+')">'
        +'<span class="pdi-num">Day '+dn+'</span>'
        +'<span class="pdi-type">'+escH(type)+'</span>'
        +(allDone?'<span class="pdi-done">✓</span>':'')
        +'</button>';
    });
  });
  document.getElementById('pickerList').innerHTML=html;
  document.getElementById('pickerOverlay').classList.add('open');
}
function selectDayFromPicker(wn,di){ closeDayPicker(); selectDay(wn,di); }
function closeDayPicker(){ document.getElementById('pickerOverlay').classList.remove('open'); }

// ── Sync from Google Sheet ─────────────────────────────────
function syncData(){
  var btn=document.getElementById('syncBtn');
  btn.classList.add('spinning'); btn.disabled=true;
  fetch(APP_BASE+'push/syncFitboard.php',{
    method:'POST', headers:{'Content-Type':'application/json'},
    body:JSON.stringify({id:sheetId})
  })
  .then(function(r){return r.json();})
  .then(function(result){
    btn.classList.remove('spinning'); btn.disabled=false;
    if(result.ok){ loadData(); }
    else alert(result.error||'Sync failed');
  })
  .catch(function(){
    btn.classList.remove('spinning'); btn.disabled=false;
    alert('Network error during sync');
  });
}

// ── Save ───────────────────────────────────────────────────
function setDirty(v){
  isDirty=v;
  document.getElementById('topSaveBtn').disabled=!v;
  if(!v){
    var s=document.getElementById('saveStatus');
    if(s){ s.textContent=''; s.className='save-status-inline'; }
  }
}

function saveWorkout(){
  if(!isDirty) return;
  var topBtn=document.getElementById('topSaveBtn');
  var stat=document.getElementById('saveStatus');
  topBtn.disabled=true; topBtn.classList.add('saving');
  stat.textContent=''; stat.className='save-status-inline';

  var dateVal=fromDateInput(document.getElementById('dayDateInput').value)||fromDateInput(todayIso());
  curRows.forEach(function(r){r['Date']=dateVal;});

  fetch(APP_BASE+'push/saveFitboard.php',{
    method:'POST', headers:{'Content-Type':'application/json'},
    body:JSON.stringify({id:sheetId,exercises:curRows})
  })
  .then(function(res){return res.json();})
  .then(function(result){
    topBtn.classList.remove('saving');
    if(result.ok){
      curRows.forEach(function(r){
        weekData.forEach(function(wr,wi){
          if(wr['Day']===r['Day']&&wr['Exercise']===r['Exercise']) weekData[wi]=Object.assign({},r);
        });
        (dayGroups[r['Day']]||[]).forEach(function(_,gi,arr){
          if(arr[gi]['Exercise']===r['Exercise']) arr[gi]=Object.assign({},r);
        });
      });
      cacheWeekData(weekData);  // keep offline cache in sync with saved data
      stat.textContent='Saved ✓'; stat.className='save-status-inline ok';
      setDirty(false);
      setTimeout(function(){
        stat.textContent=''; stat.className='save-status-inline';
      }, 2500);
    } else {
      stat.textContent='Error'; stat.className='save-status-inline error';
      topBtn.disabled=false;
    }
  })
  .catch(function(){
    topBtn.classList.remove('saving');
    stat.textContent='Network error'; stat.className='save-status-inline error';
    topBtn.disabled=false;
  });
}

document.getElementById('dayDateInput').addEventListener('input',function(){setDirty(true);});
loadData();

// ── First-launch auto-sync (standalone / home-screen mode) ──
(function(){
  var standalone = navigator.standalone === true ||
                   matchMedia('(display-mode: standalone)').matches;
  var initKey = 'fb_initialized_' + sheetId;
  if (!standalone || localStorage.getItem(initKey)) return;
  localStorage.setItem(initKey, '1');

  // Full-screen loading overlay
  var overlay = document.createElement('div');
  overlay.style.cssText = [
    'position:fixed;inset:0;z-index:99999',
    'background:#0f0f14',
    'display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1.5rem',
    'padding:2rem;text-align:center'
  ].join(';');
  overlay.innerHTML = [
    '<div style="font-family:DINBlack,sans-serif;font-size:2.4rem;line-height:1;letter-spacing:-.01em">',
      '<span style="color:#ffffff">Fit</span><span style="color:#0a84ff">Board</span>',
    '</div>',
    '<p style="font-family:DINRegular,Arial,sans-serif;font-size:.95rem;color:#8899aa;',
       'max-width:280px;line-height:1.6;margin:0">',
      'Loading your data locally so that you can run when you are not connected to the Internet.',
    '</p>',
    '<div id="_fbLoadStatus" style="font-family:DINBlack,sans-serif;font-size:.7rem;',
         'text-transform:uppercase;letter-spacing:.08em;color:#ffffff;opacity:.5">Syncing…</div>'
  ].join('');
  document.body.appendChild(overlay);

  var status = overlay.querySelector('#_fbLoadStatus');

  function dismiss() {
    overlay.style.transition = 'opacity .6s';
    overlay.style.opacity = '0';
    setTimeout(function() { overlay.remove(); }, 650);
  }

  fetch(APP_BASE+'push/syncFitboard.php',{
    method:'POST', headers:{'Content-Type':'application/json'},
    body:JSON.stringify({id:sheetId})
  })
  .then(function(r){return r.json();})
  .then(function(result){
    if(result.ok){
      loadData();
      // Proactively cache the app shell so offline launch finds it
      if('caches' in window){
        caches.open('fitboard-sw-v1').then(function(cache){
          return cache.add(location.pathname);
        }).catch(function(){});
      }
      status.textContent = 'Done';
      setTimeout(dismiss, 800);
    } else {
      status.textContent = '⚠  ' + (result.error||'Sync failed');
      setTimeout(dismiss, 2500);
    }
  })
  .catch(function(){
    status.textContent = '⚠  No connection — will retry on next launch';
    setTimeout(dismiss, 2500);
    // Clear flag so it retries next time
    localStorage.removeItem(initKey);
  });
})();

// ── Service Worker (offline shell caching) ─────────────────
// Scope is narrowed to /fitboard so the fitboard SW doesn't
// intercept navigations to /{id}/dashboard (they share the same sheet root).
if('serviceWorker' in navigator){
  var swScope = APP_BASE + sheetId + '/fitboard';
  navigator.serviceWorker.register(APP_BASE + 'fitboard-sw.js', { scope: swScope })
    .catch(function(){});
}

// ── Add-to-Home-Screen prompt ──────────────────────────────
(function(){
  // Skip if already running as installed app
  if(window.navigator.standalone || window.matchMedia('(display-mode:standalone)').matches) return;

  var ua = navigator.userAgent;
  var isIOS     = /iphone|ipad|ipod/i.test(ua) && !window.MSStream;
  var isAndroid = /android/i.test(ua);

  // iOS: Safari only (not Chrome/Firefox/etc on iOS)
  var isIOSSafari     = isIOS && /safari/i.test(ua) && !/crios|fxios|opios|mercury/i.test(ua);
  // Android: Chrome-based
  var isAndroidChrome = isAndroid && /chrome/i.test(ua) && !/edge/i.test(ua);

  if(!isIOSSafari && !isAndroidChrome) return;

  // Populate + show after DOM is fully parsed and page has settled
  setTimeout(function(){
    var sheet   = document.getElementById('a2hsSheet');
    var stepsEl = document.getElementById('a2hsSteps');
    if(!sheet || !stepsEl) return;

    stepsEl.innerHTML = isIOSSafari
      ? 'Tap <strong>Share</strong> '
        +'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">'
        +'<path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>'
        +'<polyline points="16 6 12 2 8 6"/>'
        +'<line x1="12" y1="2" x2="12" y2="15"/>'
        +'</svg>'
        +' then <strong>"Add to Home Screen"</strong>.'
      : 'Tap the <strong>⋮ menu</strong> in your browser then tap <strong>"Add to Home Screen"</strong>.';

    sheet.classList.add('visible');
  }, 1800);
})();

function a2hsDismiss(){
  var s = document.getElementById('a2hsSheet');
  if(s) s.classList.remove('visible');
}
</script>

<!-- Add-to-Home-Screen bottom sheet -->
<div class="a2hs-sheet" id="a2hsSheet" role="dialog" aria-modal="true" aria-label="Add to Home Screen">
  <div class="a2hs-header">
    <span class="a2hs-title">Add to Home Screen</span>
    <button class="a2hs-close" onclick="a2hsDismiss()" aria-label="Dismiss">✕</button>
  </div>
  <p class="a2hs-msg">To use FitBoard when you are not connected to the Internet, add it to your Home Screen.</p>
  <p class="a2hs-steps" id="a2hsSteps"></p>
</div>
</body>
</html>
