<?php
$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_base = preg_replace('#/cardboard/features/?$#', '/', $_raw);
if (!$_base || $_base === $_raw) { $_base = '/'; }
if (substr($_base, -1) !== '/') { $_base .= '/'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php
$_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
$_origin = $_scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'zapsheets.com');
$_abs    = $_origin . $_base;
$_pageUrl = htmlspecialchars($_abs . 'cardboard/features', ENT_QUOTES);
$_ogImg   = htmlspecialchars($_abs . 'images/og/cardboard-og.png', ENT_QUOTES);
$_icon    = htmlspecialchars($_abs . 'images/cardboard_icon_180.png', ENT_QUOTES);
$_desc    = 'CardBoard — design board game cards from your Google Sheet. Lay out the front and back once, then print them or send them to Tabletop Simulator and Screentop.';
?>
<title>CardBoard — Features</title>
<meta name="description" content="<?= $_desc ?>">
<link rel="canonical" href="<?= $_pageUrl ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Zapsheets">
<meta property="og:title" content="CardBoard — Features">
<meta property="og:description" content="<?= $_desc ?>">
<meta property="og:url" content="<?= $_pageUrl ?>">
<meta property="og:image" content="<?= $_ogImg ?>">
<meta property="og:image:secure_url" content="<?= $_ogImg ?>">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="CardBoard — features">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="CardBoard — Features">
<meta name="twitter:description" content="<?= $_desc ?>">
<meta name="twitter:image" content="<?= $_ogImg ?>">
<link rel="apple-touch-icon" href="<?= $_icon ?>">
<link rel="icon" type="image/png" href="images/cardboard_icon_180.png" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Fraunces:ital,opsz,wght@0,9..144,600;1,9..144,400&family=Cinzel:wght@700&display=swap" rel="stylesheet">
<style>
@font-face { font-family:'CBDINBlack'; src:url('fonts/DINBlack.woff2') format('woff2'),url('fonts/DINBlack.ttf') format('truetype'); }
@font-face { font-family:'CBDIN'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf') format('truetype'); }
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --dark:#1c1a2b; --dark-mid:#2e2763; --dark-light:#3b3480;
  --violet:#7b6cf6; --violet-light:#a99cff; --violet-pale:#f3f1ff;
  --gold:#f2c14e; --gold-light:#f7d57f; --gold-pale:#fdf6e3;
  --slate:#64748b; --slate-pale:#f4f3fa;
  --text:#1e293b; --text-mid:#475569; --text-muted:#94a3b8;
  --border:#e5e3f0; --white:#fff; --radius:12px; --radius-sm:8px;
}
html { scroll-behavior:smooth; }
body { font-family:'Inter',system-ui,sans-serif; color:var(--text); background:var(--white); line-height:1.6; font-size:16px; }

nav { position:sticky; top:0; z-index:100; background:rgba(255,255,255,.94); backdrop-filter:blur(12px); border-bottom:1px solid var(--border); padding:0 2rem; display:flex; align-items:center; justify-content:space-between; height:60px; }
.nav-logo { display:flex; align-items:center; gap:.5rem; text-decoration:none; }
.nav-logo img { height:30px; width:auto; border-radius:7px; }
.nav-logo-name { font-family:'Fraunces',Georgia,serif; font-size:1.18rem; font-weight:600; color:var(--dark); letter-spacing:-0.02em; }
nav a { font-size:14px; color:var(--text-mid); text-decoration:none; }
.nav-links { display:flex; gap:2rem; }
.nav-cta { background:var(--dark-mid); color:var(--white)!important; padding:8px 18px; border-radius:999px; font-size:14px; font-weight:500; text-decoration:none; }
@media(max-width:760px){ .nav-links { display:none; } }

.hero { background:var(--dark); color:var(--white); padding:7rem 2rem 5rem; text-align:center; position:relative; overflow:hidden; }
.hero::before { content:''; position:absolute; top:-120px; left:50%; transform:translateX(-50%); width:800px; height:800px; background:radial-gradient(circle,rgba(123,108,246,.2) 0%,transparent 70%); pointer-events:none; }
.hero::after  { content:''; position:absolute; bottom:-80px; right:10%; width:500px; height:500px; background:radial-gradient(circle,rgba(242,193,78,.1) 0%,transparent 70%); pointer-events:none; }
.hero-tag { display:inline-block; font-size:12px; font-weight:500; letter-spacing:.08em; text-transform:uppercase; color:var(--violet-light); border:1px solid rgba(169,156,255,.35); border-radius:999px; padding:4px 14px; margin-bottom:1.75rem; }
.hero h1 { font-family:'Fraunces',Georgia,serif; font-size:clamp(2.4rem,5vw,3.75rem); font-weight:600; line-height:1.1; letter-spacing:-0.03em; max-width:820px; margin:0 auto 1.5rem; position:relative; }
.hero h1 em { font-style:italic; color:var(--gold); font-weight:400; }
.hero p { font-size:1.15rem; color:rgba(255,255,255,.72); max-width:560px; margin:0 auto 2.5rem; line-height:1.7; position:relative; }
.hero-btns { display:flex; gap:1rem; justify-content:center; flex-wrap:wrap; position:relative; }
.btn-primary { background:var(--violet); color:var(--white); font-weight:600; font-size:15px; padding:13px 28px; border-radius:999px; text-decoration:none; transition:background .15s; display:inline-block; }
.btn-primary:hover { background:#6a5be8; }
.btn-ghost { background:transparent; color:rgba(255,255,255,.8); font-weight:500; font-size:15px; padding:13px 28px; border-radius:999px; border:1px solid rgba(255,255,255,.25); text-decoration:none; display:inline-block; }
.btn-ghost:hover { border-color:rgba(255,255,255,.6); color:var(--white); }

.pipeline { background:var(--dark-mid); border-top:1px solid rgba(255,255,255,.06); padding:1.5rem 2rem; display:flex; align-items:center; justify-content:center; flex-wrap:wrap; }
.stage-pill { font-size:13px; font-weight:500; padding:7px 18px; border-radius:999px; white-space:nowrap; }
.st1 { background:rgba(148,163,184,.2); color:#cbd5e1; } .st2 { background:rgba(123,108,246,.25); color:#c4bbff; }
.st3 { background:rgba(242,193,78,.2); color:var(--gold-light); } .st4 { background:rgba(76,170,75,.22); color:#8fd88e; }
.stage-arrow { color:rgba(255,255,255,.25); font-size:18px; padding:0 8px; }

section { padding:5.5rem 2rem; }
.container { max-width:1080px; margin:0 auto; }
.section-label { font-size:12px; font-weight:500; letter-spacing:.1em; text-transform:uppercase; color:var(--violet); margin-bottom:.75rem; }
.section-title { font-family:'Fraunces',Georgia,serif; font-size:clamp(1.8rem,3vw,2.5rem); font-weight:600; letter-spacing:-0.025em; line-height:1.2; color:var(--dark); margin-bottom:1rem; }
.section-sub { font-size:1.05rem; color:var(--text-mid); max-width:580px; line-height:1.7; margin-bottom:3.5rem; }

.features-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:1.25rem; }
.feat-card { background:var(--white); border:1px solid var(--border); border-radius:var(--radius); padding:1.75rem; }
.feat-icon { width:42px; height:42px; border-radius:var(--radius-sm); display:flex; align-items:center; justify-content:center; font-size:20px; margin-bottom:1rem; background:var(--violet-pale); }
.feat-icon.gold { background:var(--gold-pale); } .feat-icon.green { background:#edf7ed; } .feat-icon.rose { background:#fff1f2; } .feat-icon.slate { background:#eef1f8; }
.feat-card h3 { font-size:1rem; font-weight:600; color:var(--dark); margin-bottom:.5rem; }
.feat-card p { font-size:14px; color:var(--text-mid); line-height:1.65; }
.pro-tag { display:inline-block; font-size:10px; font-weight:600; letter-spacing:.05em; text-transform:uppercase; padding:1px 7px; border-radius:999px; background:var(--gold-pale); color:#b07d0a; border:1px solid rgba(242,193,78,.5); vertical-align:middle; }

.browser-frame { background:#e8e8e8; border-radius:14px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,.18); }
.browser-chrome { background:#d4d4d4; padding:10px 14px; display:flex; align-items:center; gap:10px; }
.browser-dots { display:flex; gap:6px; }
.browser-dots span { width:12px; height:12px; border-radius:50%; }
.browser-dots .d1 { background:#f87171; } .browser-dots .d2 { background:#fbbf24; } .browser-dots .d3 { background:#4ade80; }
.browser-url { flex:1; background:var(--white); border-radius:6px; padding:4px 10px; font-size:11px; color:#666; font-family:monospace; }

.shot-section { background:var(--slate-pale); }
.shot-grid { display:grid; grid-template-columns:1fr 1.15fr; gap:3rem; align-items:center; }
.shot-text p { font-size:15px; color:var(--text-mid); line-height:1.7; }
.shot-text ol, .shot-text ul { margin:.85rem 0 0 1.2rem; }
.shot-text li { font-size:14px; color:var(--text-mid); margin-bottom:.4rem; line-height:1.5; }
.shot-text li b { color:var(--dark); }
@media(max-width:860px){ .shot-grid { grid-template-columns:1fr; } }

/* ── Animated designer mock-up ── */
.cbx { position:relative; background:#eef0f6; font-family:'CBDIN','Inter',sans-serif; user-select:none; overflow:hidden; }
.cbx-top { background:#2e2763; height:38px; display:flex; align-items:center; padding:0 12px; gap:8px; }
.cbx-top img { width:20px; height:20px; border-radius:5px; }
.cbx-name { font-family:'CBDINBlack',sans-serif; font-size:13px; } .cbx-name .c { color:#f2c14e; } .cbx-name .b { color:#a99cff; }
.cbx-tabs { margin-left:auto; display:flex; gap:2px; background:rgba(255,255,255,.12); border-radius:6px; padding:2px; }
.cbx-tabs span { font-family:'CBDINBlack',sans-serif; font-size:8px; letter-spacing:.08em; text-transform:uppercase; color:rgba(255,255,255,.6); padding:4px 8px; border-radius:4px; }
.cbx-tabs span.on { background:#fff; color:#2e2763; }
.cbx-body { display:grid; grid-template-columns:118px 1fr; height:372px; }
.cbx-left { background:#fff; border-right:1px solid #e1e4ee; padding:10px 9px; }
.cbx-lbl { font-family:'CBDINBlack',sans-serif; font-size:7.5px; letter-spacing:.1em; text-transform:uppercase; color:#8a8fa8; margin:2px 0 5px; }
.cbx-lbl + .cbx-lbl, .cbx-chip + .cbx-lbl, .cbx-shapes + .cbx-lbl { margin-top:10px; }
.cbx-chip { display:flex; align-items:center; gap:5px; border:1.3px solid #d6d9e6; border-radius:6px; padding:5px 6px; margin-bottom:4px; background:#fff; font-family:'CBDINBlack',sans-serif; font-size:9px; color:#2e2763; transition:border-color .15s, background .15s; }
.cbx-chip i { font-style:normal; color:#a0a4bb; font-family:'CBDIN',sans-serif; width:9px; text-align:center; }
.cbx-chip em { margin-left:auto; font-style:normal; font-family:'CBDIN',sans-serif; font-size:8px; color:#9aa0b8; }
.cbx-chip.hot { border-color:#a99cff; background:#f7f5ff; }
.cbx-shapes { display:grid; grid-template-columns:repeat(3,1fr); gap:4px; }
.cbx-shape { border:1.3px solid #d6d9e6; border-radius:6px; height:26px; display:flex; align-items:center; justify-content:center; background:#fff; transition:border-color .15s, background .15s; }
.cbx-shape.hot { border-color:#a99cff; background:#f7f5ff; }
.cbx-canvas { position:relative; display:flex; align-items:center; justify-content:center; background-image:radial-gradient(#cfd2df 1px,transparent 1px); background-size:14px 14px; }
.cbx-side { position:absolute; top:22px; left:0; right:0; text-align:center; font-family:'CBDINBlack',sans-serif; font-size:8.5px; letter-spacing:.16em; text-transform:uppercase; color:#6b62b8; }
.cbx-holder { position:relative; width:180px; height:252px; margin-top:14px; perspective:900px; }
.cbx-flip { position:absolute; top:0; left:-38px; width:28px; height:28px; border-radius:50%; background:#fff; border:1.3px solid #d6d9e6; color:#4b3fa8; display:flex; align-items:center; justify-content:center; box-shadow:0 3px 8px rgba(20,20,60,.12); transition:background .2s, color .2s; z-index:3; }
.cbx-flip.on { background:#4b3fa8; color:#fff; border-color:#4b3fa8; }
.cbx-flip.press { transform:scale(.88); }
.cbx-card3d { position:absolute; inset:0; transform-style:preserve-3d; transition:transform .75s cubic-bezier(.4,.1,.2,1); }
.cbx-card3d.flipped { transform:rotateY(180deg); }
.cbx-face { position:absolute; inset:0; border-radius:9px; overflow:hidden; backface-visibility:hidden; -webkit-backface-visibility:hidden; box-shadow:0 10px 26px rgba(20,20,60,.22); }
.cbx-front { background:#fff8e8; }
.cbx-back { background:#2e2763; transform:rotateY(180deg); }
.cbx-hint { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:9px; color:#b0b4c8; text-align:center; padding:20px; transition:opacity .25s; }
.cbx-back .cbx-hint { color:rgba(255,255,255,.35); }
.pop { opacity:0; transform:scale(.6); transition:opacity .25s, transform .35s cubic-bezier(.3,1.6,.5,1); }
.pop.in { opacity:1; transform:none; }
.pop.sel { outline:1.3px solid #4b3fa8; outline-offset:1px; }
.cbx-art { position:absolute; left:9px; top:40px; width:162px; height:122px; border-radius:5px; overflow:hidden; background:#ddd; }
.cbx-art img { width:100%; height:100%; object-fit:cover; display:block; transition:opacity .25s; }
.cbx-title { position:absolute; left:10px; top:10px; width:124px; height:24px; font-family:'Cinzel',Georgia,serif; font-size:14.5px; color:#2b2140; line-height:24px; white-space:nowrap; overflow:hidden; }
.cbx-coin { position:absolute; right:9px; top:8px; width:28px; height:28px; border-radius:50%; background:#f2c14e; box-shadow:inset 0 0 0 2px #b07d0a; display:flex; align-items:center; justify-content:center; font-family:'CBDINBlack',sans-serif; font-size:14px; color:#2b2140; }
.cbx-coin b { opacity:0; transition:opacity .2s; font-weight:400; } .cbx-coin b.in { opacity:1; }
.cbx-text { position:absolute; left:12px; top:170px; width:156px; height:60px; font-size:10px; line-height:1.35; color:#3a3350; }
.cbx-num { position:absolute; right:11px; bottom:8px; font-family:'CBDINBlack',sans-serif; font-size:8px; color:#9a8f6a; }
.cbx-star { position:absolute; left:40px; top:58px; width:100px; height:100px; }
.cbx-type { position:absolute; left:10px; top:190px; width:160px; text-align:center; font-family:'CBDINBlack',sans-serif; font-size:13px; letter-spacing:.18em; color:#fff; }
.cbx-logo { position:absolute; left:0; right:0; top:18px; text-align:center; font-family:'CBDINBlack',sans-serif; font-size:9px; letter-spacing:.2em; color:rgba(255,255,255,.55); }
.cbx-rows { position:absolute; left:12px; top:10px; display:flex; align-items:center; gap:5px; font-size:9px; color:#6b7090; }
.cbx-rows span.btn { width:18px; height:18px; border:1.3px solid #d6d9e6; border-radius:5px; background:#fff; display:flex; align-items:center; justify-content:center; font-size:8px; color:#3b3f5c; }
.cbx-rows span.btn.press { background:#ece9ff; }
.cbx-ghost { position:absolute; z-index:20; pointer-events:none; opacity:0; transition:opacity .15s; transform-origin:0 0; box-shadow:0 8px 20px rgba(20,20,60,.25); border-radius:6px; }
.cbx-ghost.show { opacity:.95; }
.cbx-cap { position:absolute; left:50%; bottom:12px; transform:translateX(-50%); background:rgba(28,26,43,.9); color:#fff; font-family:'Inter',sans-serif; font-size:11px; font-weight:500; padding:6px 13px; border-radius:999px; white-space:nowrap; transition:opacity .3s; z-index:15; }
.cbx-cap b { color:#f2c14e; font-weight:600; margin-right:4px; }
.cbx-toast { position:absolute; left:50%; bottom:46px; transform:translate(-50%,6px); background:#15803d; color:#fff; font-family:'CBDINBlack',sans-serif; font-size:9px; letter-spacing:.03em; padding:5px 11px; border-radius:999px; opacity:0; transition:opacity .25s, transform .25s; z-index:16; }
.cbx-toast.show { opacity:1; transform:translate(-50%,0); }
.cbx-cursor { position:absolute; left:0; top:0; z-index:30; pointer-events:none; filter:drop-shadow(0 2px 3px rgba(0,0,0,.3)); transform:translate(330px,300px); }
.cbx-cursor.down { animation:cbxClick .22s ease; }
@keyframes cbxClick { 50% { scale:.82; } }
@media (prefers-reduced-motion: reduce) { .pop, .cbx-card3d { transition:none; } }

.sheet-mock { background:#fff; font-family:'Inter',sans-serif; font-size:11px; }
.sheet-mock table { width:100%; border-collapse:collapse; }
.sheet-mock th, .sheet-mock td { border:1px solid #e6e8f0; padding:5px 8px; text-align:left; white-space:nowrap; }
.sheet-mock th { background:#f3f1ff; color:#2e2763; font-weight:600; font-size:10px; }
.sheet-mock td.rn, .sheet-mock th.rn { background:#f7f8fc; color:#9aa0b8; text-align:center; width:28px; }
.sheet-mock td.sel { box-shadow:inset 0 0 0 2px #4b3fa8; }
.tts-mock { background:#1d2230; padding:16px; display:grid; grid-template-columns:repeat(5,1fr); gap:4px; }
.tts-mock img { width:100%; aspect-ratio:5/7; object-fit:cover; border-radius:3px; }
.tts-mock .bk { background:#2e2763; border-radius:3px; aspect-ratio:5/7; display:flex; align-items:center; justify-content:center; color:#f2c14e; font-size:18px; outline:2px dashed #a99cff; outline-offset:-3px; }
.link-row { display:flex; gap:6px; align-items:center; background:#fff; padding:10px 14px; border-top:1px solid #e5e3f0; font-family:monospace; font-size:10px; color:#4a4e68; }
.link-row span { flex:1; background:#f7f8fc; border:1px solid #e1e4ee; border-radius:5px; padding:4px 7px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.link-row b { font-family:'Inter',sans-serif; font-weight:600; font-size:10px; color:#2e2763; border:1px solid #d6d9e6; border-radius:5px; padding:3px 9px; }

.tags-mock { background:#fff; font-family:'Inter',sans-serif; padding:14px; }
.tags-mock .tm-row { display:grid; grid-template-columns:96px 1fr; gap:8px; align-items:center; border:1px solid #e5e3f0; border-radius:8px; padding:7px 10px; margin-bottom:6px; font-size:11px; color:#2a2d3e; }
.tags-mock .tm-row code { font-family:ui-monospace,Menlo,monospace; font-size:11px; color:#4b3fa8; background:#f3f1ff; border-radius:4px; padding:1px 6px; justify-self:start; }
.tags-mock .tm-row span { display:flex; align-items:center; gap:6px; color:#4a4e68; }
.tags-mock .ti { width:1.15em; height:1.15em; vertical-align:-.22em; display:inline-block; }
.tm-card { margin-top:12px; background:#2e2763; border-radius:10px; padding:16px 18px; color:#fff; font-size:15px; font-weight:500; display:grid; }
.tm-card > div { grid-area:1/1; transition:opacity .4s; }
.tm-card .raw { font-family:ui-monospace,Menlo,monospace; font-size:13px; color:#cfc8ff; animation:tmRaw 6s infinite; }
.tm-card .out { animation:tmOut 6s infinite; }
.tm-card .lbl { display:block; font-family:'Inter',sans-serif; font-size:9px; letter-spacing:.1em; text-transform:uppercase; color:#a99cff; margin-bottom:6px; font-weight:600; }
@keyframes tmRaw { 0%,40% { opacity:1; } 50%,90% { opacity:0; } 100% { opacity:1; } }
@keyframes tmOut { 0%,40% { opacity:0; } 50%,90% { opacity:1; } 100% { opacity:0; } }
@media (prefers-reduced-motion:reduce) { .tm-card .raw { animation:none; opacity:0; } .tm-card .out { animation:none; opacity:1; } }

.ownership-section { background:var(--dark); color:var(--white); }
.ownership-section .section-label { color:var(--violet-light); }
.ownership-section .section-title { color:var(--white); }
.ownership-section .section-sub { color:rgba(255,255,255,.65); }
.own-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:1rem; }
.own-card { background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); border-radius:var(--radius); padding:1.5rem; }
.own-card .num { font-family:'Fraunces',Georgia,serif; font-size:2.25rem; font-weight:600; color:var(--gold); line-height:1; margin-bottom:.5rem; }
.own-card p { font-size:14px; color:rgba(255,255,255,.65); line-height:1.6; }

.compare-wrap { border:1px solid var(--border); border-radius:var(--radius); overflow:auto; }
.compare-table { width:100%; border-collapse:collapse; font-size:14px; }
.compare-table th { padding:12px 16px; text-align:left; font-weight:600; font-size:13px; color:var(--dark); border-bottom:2px solid var(--border); background:var(--slate-pale); }
.compare-table th.ours { background:var(--violet-pale); border-bottom:2px solid var(--violet); }
.compare-table td { padding:12px 16px; border-bottom:1px solid var(--border); color:var(--text-mid); vertical-align:middle; }
.compare-table td:first-child { color:var(--text); font-weight:500; }
.compare-table td.ours { background:rgba(243,241,255,.6); }
.compare-table tr:last-child td { border-bottom:none; }
.check { color:#4caa4b; } .cross { color:var(--text-muted); }
.price-note { margin-top:.9rem; font-size:12px; color:var(--text-muted); }
.compare-table .nl { font-size:12px; color:var(--text-muted); font-weight:400; margin-left:2px; }
.compare-table tr.cost td { color:var(--text-mid); line-height:1.5; }
.compare-table tr.cost b { color:var(--dark); font-weight:600; }

.cta-section { background:var(--violet-pale); text-align:center; padding:5rem 2rem; border-top:1px solid rgba(123,108,246,.15); }
.cta-section .section-label { display:flex; justify-content:center; }
.cta-section p { font-size:1.05rem; color:var(--text-mid); margin-bottom:2rem; }
footer { padding:2rem; text-align:center; font-size:13px; color:var(--text-muted); border-top:1px solid var(--border); }
footer a { color:var(--text-mid); text-decoration:none; }
</style>
</head>
<body>

<nav>
  <a class="nav-logo" href="cardboard">
    <img src="images/cardboard_icon_180.png" alt="CardBoard" />
    <span class="nav-logo-name">CardBoard</span>
  </a>
  <div class="nav-links">
    <a href="cardboard/features#features">Features</a>
    <a href="cardboard/features#see">See it in action</a>
    <a href="cardboard/features#tags">Tags</a>
    <a href="cardboard/features#export">Tabletop Simulator</a>
    <a href="cardboard/features#compare">Compare</a>
  </div>
  <a href="cardboard" class="nav-cta">Get started</a>
</nav>

<section class="hero">
  <div class="hero-tag">For board game designers</div>
  <h1>Your cards, <em>straight from your sheet</em>.</h1>
  <p>Keep your cards in a Google Sheet — one row per card. Lay out the front and back once, and CardBoard makes every card: ready to print, or to play in Tabletop Simulator and Screentop.</p>
  <div class="hero-btns">
    <a href="cardboard" class="btn-primary">Get CardBoard</a>
    <a href="cardboard/features#see" class="btn-ghost">See it in action</a>
  </div>
</section>

<div class="pipeline">
  <span class="stage-pill st1">Google Sheet</span><span class="stage-arrow">→</span>
  <span class="stage-pill st2">Front &amp; back layout</span><span class="stage-arrow">→</span>
  <span class="stage-pill st3">Print sheets</span><span class="stage-arrow">→</span>
  <span class="stage-pill st4">Tabletop Simulator</span>
</div>

<section id="features" style="background:var(--slate-pale);">
  <div class="container">
    <div class="section-label">Features</div>
    <h2 class="section-title">From spreadsheet to card deck</h2>
    <p class="section-sub">Change a word in your sheet and every card that uses it changes too. No copying text into a drawing app, card by card.</p>
    <div class="features-grid">
      <div class="feat-card"><div class="feat-icon">🃏</div><h3>Drag-and-drop card designer</h3><p>Drag your sheet's columns onto the card and see the real data right away. Step through rows to check every card.</p></div>
      <div class="feat-card"><div class="feat-icon gold">🔄</div><h3>Fronts and backs</h3><p>Flip the card to design its back. One shared back, or a different back for each card when it shows data from the row.</p></div>
      <div class="feat-card"><div class="feat-icon slate">🔤</div><h3>Text, pictures, shapes</h3><p>Fixed text with <code>{Column}</code> placeholders, pictures from a column or a link, and stars, circles, hexagons and more.</p></div>
      <div class="feat-card"><div class="feat-icon green">🎲</div><h3>Generated values</h3><p>Number your cards, roll a random value, or pick from a list — the same in the preview and on the printout.</p></div>
      <div class="feat-card"><div class="feat-icon rose">📋</div><h3>Edit your data in place</h3><p>The Data tab shows your sheet as a grid. Type into a cell and it's saved to Google Sheets — without overwriting someone else's change.</p></div>
      <div class="feat-card"><div class="feat-icon gold">🖨️</div><h3>Print-and-play sheets</h3><p>Letter or A4, crop marks, copies from a Count column, and backs that line up when you print double-sided.</p></div>
      <div class="feat-card"><div class="feat-icon">🧩</div><h3>Tabletop Simulator &amp; Screentop</h3><p>Card sheets in the exact grid each one expects — 10 × 7 for TTS, with the back in the hidden-card slot.</p></div>
      <div class="feat-card"><div class="feat-icon slate">🔗</div><h3>Links that stay up to date <span class="pro-tag">Pro</span></h3><p>Every export — Tabletop Simulator sheets, Screentop sheets or separate card images — can be hosted at links that never change. Save your layout and everything using those links gets the new cards.</p></div>
      <div class="feat-card"><div class="feat-icon gold">⚡</div><h3>Tags for icons <span class="pro-tag">Pro</span></h3><p>Write <code>[energy]</code> in your sheet and every card shows its icon. Pick from the icon library, upload a picture, or have a tag become words.</p></div>
      <div class="feat-card"><div class="feat-icon rose">🗂️</div><h3>Layers and groups</h3><p>See everything on the card in one list. Drag to change what's on top, tick several to move them together, and group them.</p></div>
      <div class="feat-card"><div class="feat-icon slate">🎨</div><h3>Fonts, colors and effects</h3><p>Choose fonts and colors — with your recent colors and transparent a click away — and rotate, fade or round the corners of anything on the card.</p></div>
      <div class="feat-card"><div class="feat-icon green">☁️</div><h3>Pictures from anywhere</h3><p>Dropbox, Google Drive or web links are shown straight from the source — replace a picture there and your cards follow. With Pro, upload pictures from your computer.</p></div>
    </div>
  </div>
</section>

<section class="shot-section" id="see" style="background:#fff">
  <div class="container">
    <div class="shot-grid">
      <div class="shot-text">
        <div class="section-label">See it in action</div>
        <h2 class="section-title" style="margin-bottom:1rem;">Lay out the front and back of a card</h2>
        <p>Your sheet's columns are on the left. Drag them onto the card — the art, the name, the cost — then flip it over and design the back the same way.</p>
        <ol>
          <li><b>Drag columns onto the front.</b> Each shows the value from the current row.</li>
          <li><b>Add shapes</b> behind them — a coin for the cost, a frame, a banner.</li>
          <li><b>Flip the card</b> with the button at its top right to design the back.</li>
          <li><b>Save.</b> Every row in your sheet is now a finished card.</li>
        </ol>
      </div>
      <div class="browser-frame">
        <div class="browser-chrome">
          <div class="browser-dots"><span class="d1"></span><span class="d2"></span><span class="d3"></span></div>
          <div class="browser-url">zapsheets.com/app/…/cardboard</div>
        </div>
        <div class="cbx" id="cbDemo" aria-hidden="true">
          <div class="cbx-top"><img src="images/cardboard_icon_180.png" alt=""><span class="cbx-name"><span class="c">Card</span><span class="b">Board</span></span>
            <div class="cbx-tabs"><span>Data</span><span class="on">Design</span><span>Print</span><span>Export</span></div></div>
          <div class="cbx-body">
            <div class="cbx-left">
              <div class="cbx-lbl">Data</div>
              <div class="cbx-chip" data-k="art"><i>≡</i>Art</div>
              <div class="cbx-chip" data-k="name"><i>≡</i>Name<em class="cbx-sample-name">Floristry</em></div>
              <div class="cbx-chip" data-k="cost"><i>≡</i>Cost<em class="cbx-sample-cost">3</em></div>
              <div class="cbx-chip" data-k="type"><i>≡</i>Type<em class="cbx-sample-type">Market</em></div>
              <div class="cbx-chip" data-k="text"><i>≡</i>Text<em class="cbx-sample-text">Draw 2…</em></div>
              <div class="cbx-lbl">Shape</div>
              <div class="cbx-shapes">
                <div class="cbx-shape" data-k="circle"><svg width="14" height="14" viewBox="0 0 14 14"><circle cx="7" cy="7" r="6" fill="#ece9ff" stroke="#4b3fa8" stroke-width="1.3"/></svg></div>
                <div class="cbx-shape" data-k="star"><svg width="15" height="15" viewBox="0 0 24 24"><path d="M12 2l2.9 6.9 7.1.6-5.4 4.7 1.7 7.3L12 17.8 5.7 21.5l1.7-7.3L2 9.5l7.1-.6z" fill="#ece9ff" stroke="#4b3fa8" stroke-width="1.6" stroke-linejoin="round"/></svg></div>
                <div class="cbx-shape"><svg width="16" height="14" viewBox="0 0 16 14"><path d="M4 1h8l3.5 6L12 13H4L.5 7z" fill="#ece9ff" stroke="#4b3fa8" stroke-width="1.3" stroke-linejoin="round"/></svg></div>
              </div>
              <div class="cbx-lbl">Generated</div>
              <div class="cbx-chip"><i>#</i>Sequence</div>
            </div>
            <div class="cbx-canvas">
              <div class="cbx-rows"><span class="btn cbx-prev">◀</span><span class="cbx-rowinfo">Row 1 of 3</span><span class="btn cbx-next">▶</span></div>
              <div class="cbx-side">Front</div>
              <div class="cbx-holder">
                <div class="cbx-card3d">
                  <div class="cbx-face cbx-front">
                    <div class="cbx-hint">Drag elements here</div>
                    <div class="cbx-art pop" data-el="art"><img src="images/gameboard-features/cover-floristry.webp" alt=""></div>
                    <div class="cbx-title pop" data-el="name">Floristry</div>
                    <div class="cbx-coin pop" data-el="circle"><b>3</b></div>
                    <div class="cbx-text pop" data-el="text">Draw 2 flower cards. Discard 1.</div>
                  </div>
                  <div class="cbx-face cbx-back">
                    <div class="cbx-hint">The back of the card — drag elements here</div>
                    <div class="cbx-logo pop" data-el="logo">UP GAMES</div>
                    <svg class="cbx-star pop" data-el="star" viewBox="0 0 24 24"><path d="M12 2l2.9 6.9 7.1.6-5.4 4.7 1.7 7.3L12 17.8 5.7 21.5l1.7-7.3L2 9.5l7.1-.6z" fill="#f2c14e" stroke="#b07d0a" stroke-width=".6" stroke-linejoin="round"/></svg>
                    <div class="cbx-type pop" data-el="type">MARKET</div>
                  </div>
                </div>
                <div class="cbx-flip"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 15.5-6.2L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-15.5 6.2L3 16"/><path d="M3 21v-5h5"/></svg></div>
              </div>
            </div>
          </div>
          <div class="cbx-ghost"></div>
          <div class="cbx-cap"><b>1</b>Drag columns onto the card</div>
          <div class="cbx-toast">✓ Layout saved to your Sheet</div>
          <svg class="cbx-cursor" width="22" height="22" viewBox="0 0 24 24"><path d="M4 2l15 11-7 1.2L8.6 21 4 2z" fill="#fff" stroke="#111" stroke-width="1.5" stroke-linejoin="round"/></svg>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="shot-section" id="data">
  <div class="container">
    <div class="shot-grid">
      <div class="browser-frame">
        <div class="browser-chrome"><div class="browser-dots"><span class="d1"></span><span class="d2"></span><span class="d3"></span></div><div class="browser-url">zapsheets.com/app/…/cardboard — Data</div></div>
        <div class="sheet-mock">
          <table>
            <tr><th class="rn"></th><th>Name</th><th>Cost</th><th>Type</th><th>Text</th><th>Count</th></tr>
            <tr><td class="rn">2</td><td>Floristry</td><td>3</td><td>Market</td><td>Draw 2 flower cards…</td><td>2</td></tr>
            <tr><td class="rn">3</td><td>Scurry Up!</td><td class="sel">1</td><td>Action</td><td>Move a squirrel two…</td><td>3</td></tr>
            <tr><td class="rn">4</td><td>Pressure</td><td>5</td><td>Event</td><td>Every player dives…</td><td>1</td></tr>
            <tr><td class="rn">5</td><td>Deep Dive</td><td>4</td><td>Event</td><td>Look at the top 3…</td><td>2</td></tr>
          </table>
        </div>
      </div>
      <div class="shot-text">
        <div class="section-label">Your data</div>
        <h2 class="section-title" style="margin-bottom:1rem;">Balance your cards without leaving the app</h2>
        <p>The Data tab is your Google Sheet as a grid. Tweak a cost or reword a card, and it's saved straight to the sheet — the designer, the print sheets and your Tabletop Simulator deck all pick it up.</p>
        <ul>
          <li>Edit like a spreadsheet: type, Enter, Tab, Esc</li>
          <li>Add rows and columns from CardBoard</li>
          <li>Never overwrites a change someone made in Sheets</li>
          <li>Hide the columns you don't need while designing</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<section class="shot-section" id="tags" style="background:#fff">
  <div class="container">
    <div class="shot-grid">
      <div class="shot-text">
        <div class="section-label">Tags <span class="pro-tag">Pro</span></div>
        <h2 class="section-title" style="margin-bottom:1rem;">Write [energy], see the icon</h2>
        <p>Give a tag a word or a picture once, then type it anywhere in your sheet. Every card shows the icon in place of the tag — change the picture and the whole deck follows.</p>
        <ul>
          <li>Pick from the icon library, upload a picture or paste a link</li>
          <li>Or have a tag become words, like <b>[VP]</b> → victory points</li>
          <li>Works in text and in data — <b>{Columns}</b> still fill in from your sheet</li>
          <li>Kept in a <b>tags</b> tab in your Google Sheet</li>
        </ul>
      </div>
      <div class="browser-frame">
        <div class="browser-chrome"><div class="browser-dots"><span class="d1"></span><span class="d2"></span><span class="d3"></span></div><div class="browser-url">zapsheets.com/app/…/cardboard — Tags</div></div>
        <div class="tags-mock">
          <div class="tm-row"><code>[energy]</code><span><svg class="ti" viewBox="0 0 24 24"><path d="M13.5 2 4.5 13.5h6.5L10 22l9-11.5h-6.5z" fill="#f2b705"/></svg> Icon</span></div>
          <div class="tm-row"><code>[coin]</code><span><svg class="ti" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#e9a81c"/><circle cx="12" cy="12" r="7" fill="#f7c948" stroke="#c98a0c" stroke-width="1.2"/></svg> Icon</span></div>
          <div class="tm-row"><code>[VP]</code><span>victory points</span></div>
          <div class="tm-card">
            <div class="raw"><span class="lbl">In your sheet</span>Gain {Amount} [energy]. Pay 2 [coin] to score 1 [VP].</div>
            <div class="out"><span class="lbl">On the card</span>Gain 2 <svg class="ti" viewBox="0 0 24 24"><path d="M13.5 2 4.5 13.5h6.5L10 22l9-11.5h-6.5z" fill="#f2b705"/></svg>. Pay 2 <svg class="ti" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="#e9a81c"/><circle cx="12" cy="12" r="7" fill="#f7c948" stroke="#c98a0c" stroke-width="1.2"/></svg> to score 1 victory points.</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="shot-section" id="export">
  <div class="container">
    <div class="shot-grid">
      <div class="browser-frame">
        <div class="browser-chrome"><div class="browser-dots"><span class="d1"></span><span class="d2"></span><span class="d3"></span></div><div class="browser-url">zapsheets.com/app/…/cardboard — Export</div></div>
        <div class="tts-mock">
          <img src="images/gameboard-features/cover-floristry.webp" alt=""><img src="images/gameboard-features/cover-floristry.webp" alt=""><img src="images/gameboard-features/cover-scurry.webp" alt=""><img src="images/gameboard-features/cover-scurry.webp" alt=""><img src="images/gameboard-features/cover-scurry.webp" alt="">
          <img src="images/gameboard-features/cover-pressure.webp" alt=""><img src="images/gameboard-features/cover-floristry.webp" alt=""><img src="images/gameboard-features/cover-pressure.webp" alt=""><img src="images/gameboard-features/cover-scurry.webp" alt=""><div class="bk">★</div>
        </div>
        <div class="link-row"><span>https://zapsheets.com/app/shares/cardboard/206e3c…/faces-1.jpg</span><b>Copy</b></div>
      </div>
      <div class="shot-text">
        <div class="section-label">Tabletop Simulator &amp; Screentop</div>
        <h2 class="section-title" style="margin-bottom:1rem;">Play-test online the moment you save</h2>
        <p>Download card sheets in the exact grid Tabletop Simulator and Screentop expect — or, with Pro, get links you paste into TTS once. Every time you save your layout, the pictures behind those links are updated.</p>
        <ul>
          <li>10 × 7 sheets with the back in TTS's hidden-card slot</li>
          <li>Unique backs when the back shows data from the row</li>
          <li>Copies from a Count column</li>
          <li>Links carry a share code, never your spreadsheet's ID</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<section class="ownership-section">
  <div class="container">
    <div class="section-label">Your sheet, your cards</div>
    <h2 class="section-title">Your cards live in your Google Sheet</h2>
    <p class="section-sub">Your card text, stats and layouts are all in a spreadsheet you own. Share it with co-designers, sort it, chart it, back it up.</p>
    <div class="own-grid">
      <div class="own-card"><div class="num">Free</div><p>The designer, fronts and backs, the Data tab, printing and downloads for Tabletop Simulator and Screentop.</p></div>
      <div class="own-card"><div class="num">$4.99</div><p>Per month for <strong>Pro</strong>: hosted links for every export — Tabletop Simulator, Screentop and card images — that stay up to date, picture uploads and tags.</p></div>
      <div class="own-card"><div class="num">∞</div><p>Cards and decks. Every tab in your spreadsheet can be its own deck with its own layout.</p></div>
    </div>
  </div>
</section>

<section id="compare">
  <div class="container">
    <div class="section-label">How we compare</div>
    <h2 class="section-title">How CardBoard compares</h2>
    <p class="section-sub">Dextrous and Component.Studio are popular card design tools. CardBoard works straight from your Google Sheet, designs both sides, and starts free.</p>
    <div class="compare-wrap">
      <table class="compare-table">
        <thead><tr><th>Feature</th><th class="ours">CardBoard</th><th>Dextrous</th><th>Component.Studio</th></tr></thead>
        <tbody>
          <tr><td>Cards made from spreadsheet rows</td><td class="ours"><span class="check">✓</span> <span class="nl">Google Sheets</span></td><td><span class="check">✓</span> <span class="nl">Google Sheets or CSV</span></td><td><span class="check">✓</span> <span class="nl">spreadsheet upload</span></td></tr>
          <tr><td>Visual drag-and-drop layout</td><td class="ours"><span class="check">✓</span></td><td><span class="check">✓</span></td><td><span class="check">✓</span></td></tr>
          <tr><td>Front and back designer</td><td class="ours"><span class="check">✓</span></td><td><span class="check">✓</span></td><td><span class="check">✓</span></td></tr>
          <tr><td>Edit card data in the app</td><td class="ours"><span class="check">✓</span> <span class="nl">saved to your Sheet</span></td><td><span class="check">✓</span> <span class="nl">in its own tables</span></td><td><span class="check">✓</span> <span class="nl">kept in Component.Studio</span></td></tr>
          <tr><td>Edits saved to your Google Sheet automatically</td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Print-and-play sheets</td><td class="ours"><span class="check">✓</span> <span class="nl">double-sided</span></td><td><span class="check">✓</span> <span class="nl">PDF</span></td><td><span class="check">✓</span> <span class="nl">PDF</span></td></tr>
          <tr><td>Tabletop Simulator &amp; Screentop sheets</td><td class="ours"><span class="check">✓</span></td><td><span class="check">✓</span></td><td><span class="check">✓</span></td></tr>
          <tr><td>Hosted export links that stay up to date<br><span class="nl" style="margin:0">Tabletop Simulator, Screentop and card images</span></td><td class="ours"><span class="check">✓</span> <span class="pro-tag">Pro</span></td><td><span class="check">✓</span> <span class="nl">Tabletop Simulator</span></td><td><span class="check">✓</span> <span class="nl">Tabletop Simulator</span></td></tr>
          <tr><td><code>[tags]</code> into an icon or words</td><td class="ours"><span class="check">✓</span> <span class="pro-tag">Pro</span></td><td><span class="check">✓</span></td><td><span class="check">✓</span></td></tr>
          <tr><td>Pictures from Dropbox, Drive or the web</td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span> <span class="nl">not listed</span></td><td><span class="check">✓</span></td></tr>
          <tr><td>Works in the browser, nothing to install</td><td class="ours"><span class="check">✓</span></td><td><span class="check">✓</span></td><td><span class="check">✓</span></td></tr>
          <tr><td>Works with <a href="https://zapsheets.com/app/devboard/features" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline;text-underline-offset:2px">DevBoard</a> &amp; <a href="https://zapsheets.com/app/gameboard/features" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline;text-underline-offset:2px">GameBoard</a></td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td></tr>
          <tr class="cost"><td>Monthly cost</td>
            <td class="ours"><b>Free</b><br><b>$4.99</b> / mo Pro</td>
            <td><b>Free</b> <span class="nl">2 projects, 10 layouts</span><br><b>$6 – $24</b> / mo<span class="nl" style="display:block;margin:0">Prototyper $6 · Designer $12 · Designer Pro $24</span></td>
            <td><b>$9.99</b> / mo<span class="nl" style="display:block;margin:0">free trial, no free plan</span></td></tr>
        </tbody>
      </table>
    </div>
    <p class="price-note">Dextrous and Component.Studio features and prices as published by them and in reviews, November 2026; “not listed” means we couldn’t find it in their published features. Check their websites for current details.</p>
  </div>
</section>

<section class="cta-section" id="cta">
  <div class="container">
    <div class="section-label">Ready to start?</div>
    <h2 class="section-title" style="text-align:center;max-width:560px;margin:0 auto 1rem;">Your next prototype deck is one sheet away</h2>
    <p>Connect your Google Sheet and lay out your first card in minutes.</p>
    <a href="cardboard" class="btn-primary" style="font-size:16px;padding:15px 36px;">Get CardBoard</a>
  </div>
</section>

<footer>
  <a href="cardboard">CardBoard</a> &mdash; built for board game makers &nbsp;·&nbsp; <a href="cardboard/help">Help</a> &nbsp;·&nbsp; <a href="/">ZapSheets</a>
</footer>

<script>
// ── Front-and-back layout animation: columns are dragged onto the card, it flips, the back is laid out, rows step through, loops ──
(function() {
  var stage = document.getElementById('cbDemo');
  if (!stage) return;
  var q = function(s) { return stage.querySelector(s); }, qa = function(s) { return stage.querySelectorAll(s); };
  var cursor = q('.cbx-cursor'), ghost = q('.cbx-ghost'), cap = q('.cbx-cap'), toast = q('.cbx-toast');
  var card = q('.cbx-card3d'), flipBtn = q('.cbx-flip'), side = q('.cbx-side'), rowInfo = q('.cbx-rowinfo'), next = q('.cbx-next');
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var ROWS = [
    { name: 'Floristry', cost: '3', type: 'MARKET', art: 'images/gameboard-features/cover-floristry.webp', text: 'Draw 2 flower cards. Discard 1.' },
    { name: 'Scurry Up!', cost: '1', type: 'ACTION', art: 'images/gameboard-features/cover-scurry.webp', text: 'Move a squirrel two spaces.' },
    { name: 'Pressure', cost: '5', type: 'EVENT', art: 'images/gameboard-features/cover-pressure.webp', text: 'Every player dives one level deeper.' }
  ];
  var visible = false, running = false;
  var sleep = function(ms) { return new Promise(function(r) { setTimeout(r, ms); }); };
  function at(el, fx, fy) {
    var s = stage.getBoundingClientRect(), r = el.getBoundingClientRect();
    return [r.left - s.left + r.width * (fx == null ? .5 : fx), r.top - s.top + r.height * (fy == null ? .5 : fy)];
  }
  function moveTo(el, fx, fy, ms) {
    ms = ms || 650; var p = at(el, fx, fy);
    cursor.style.transition = 'transform ' + ms + 'ms cubic-bezier(.45,.05,.25,1)';
    cursor.style.transform = 'translate(' + (p[0] - 3) + 'px,' + (p[1] - 2) + 'px)';
    return sleep(ms + 50);
  }
  function click() { cursor.classList.remove('down'); void cursor.offsetWidth; cursor.classList.add('down'); return sleep(220); }
  function caption(n, text) { cap.style.opacity = 0; return sleep(250).then(function() { cap.innerHTML = '<b>' + n + '</b>' + text; cap.style.opacity = 1; }); }
  function el(k) { return q('[data-el="' + k + '"]'); }
  function selectOnly(node) { qa('.pop.sel').forEach(function(x) { x.classList.remove('sel'); }); if (node) node.classList.add('sel'); }
  // Drag a palette item to a spot on the card; the element pops in where it lands
  async function drag(src, target, fx, fy, show) {
    await moveTo(src, .35, .5, 600);
    src.classList.add('hot'); await click();
    var r = src.getBoundingClientRect(), s = stage.getBoundingClientRect();
    ghost.innerHTML = src.outerHTML; ghost.firstChild.style.margin = 0;
    ghost.style.width = r.width + 'px'; ghost.style.transition = 'none';
    ghost.style.transform = 'translate(' + (r.left - s.left) + 'px,' + (r.top - s.top) + 'px)';
    void ghost.offsetWidth; ghost.classList.add('show');
    var p = at(target, fx, fy), ms = 750;
    ghost.style.transition = 'transform ' + ms + 'ms cubic-bezier(.45,.05,.25,1), opacity .15s';
    ghost.style.transform = 'translate(' + (p[0] - r.width * .35) + 'px,' + (p[1] - r.height / 2) + 'px)';
    await moveTo(target, fx, fy, ms);
    ghost.classList.remove('show'); src.classList.remove('hot');
    show(); await sleep(450);
  }
  function hideHint(face) { var h = q(face + ' .cbx-hint'); if (h) h.style.opacity = 0; }
  function fill(i) {
    var r = ROWS[i];
    el('name').textContent = r.name; el('type').textContent = r.type;
    q('.cbx-coin b').textContent = r.cost; el('art').querySelector('img').src = r.art; el('text').textContent = r.text;
    q('.cbx-sample-text').textContent = r.text.slice(0, 7) + '…';
    q('.cbx-sample-name').textContent = r.name; q('.cbx-sample-cost').textContent = r.cost; q('.cbx-sample-type').textContent = r.type.charAt(0) + r.type.slice(1).toLowerCase();
    rowInfo.textContent = 'Row ' + (i + 1) + ' of ' + ROWS.length;
  }
  async function flip(toBack) {
    await moveTo(flipBtn, .5, .5, 600); await click();
    flipBtn.classList.add('press'); setTimeout(function() { flipBtn.classList.remove('press'); }, 160);
    selectOnly(null);
    card.classList.toggle('flipped', toBack); flipBtn.classList.toggle('on', toBack);
    setTimeout(function() { side.textContent = toBack ? 'Back' : 'Front'; }, 350);
    await sleep(900);
  }
  function reset() {
    qa('.pop').forEach(function(x) { x.classList.remove('in', 'sel'); });
    q('.cbx-coin b').classList.remove('in');
    qa('.cbx-hint').forEach(function(h) { h.style.opacity = 1; });
    card.classList.remove('flipped'); flipBtn.classList.remove('on'); side.textContent = 'Front';
    fill(0); toast.classList.remove('show');
  }
  function showAll() {   // the finished front for reduced motion
    qa('.cbx-front .pop').forEach(function(x) { x.classList.add('in'); }); q('.cbx-coin b').classList.add('in');
    qa('.cbx-hint').forEach(function(h) { h.style.opacity = 0; }); qa('.cbx-back .pop').forEach(function(x) { x.classList.add('in'); });
    cap.style.opacity = 0; cursor.style.display = 'none';
  }
  async function loop() {
    if (running) return; running = true;
    while (visible) {
      reset(); await caption(1, 'Drag columns onto the card'); await sleep(500);
      var front = q('.cbx-front');
      await drag(q('.cbx-chip[data-k="art"]'), front, .5, .4, function() { hideHint('.cbx-front'); el('art').classList.add('in'); selectOnly(el('art')); });
      await drag(q('.cbx-chip[data-k="name"]'), front, .4, .1, function() { el('name').classList.add('in'); selectOnly(el('name')); });
      await drag(q('.cbx-chip[data-k="text"]'), front, .5, .78, function() { el('text').classList.add('in'); selectOnly(el('text')); });
      await caption(2, 'Add a shape, then a column on top');
      await drag(q('.cbx-shape[data-k="circle"]'), front, .88, .1, function() { el('circle').classList.add('in'); selectOnly(el('circle')); });
      await drag(q('.cbx-chip[data-k="cost"]'), front, .88, .1, function() { q('.cbx-coin b').classList.add('in'); });
      await sleep(400);
      await caption(3, 'Flip it over to design the back');
      await flip(true);
      var back = q('.cbx-back');
      await drag(q('.cbx-shape[data-k="star"]'), back, .5, .45, function() { hideHint('.cbx-back'); el('star').classList.add('in'); el('logo').classList.add('in'); selectOnly(el('star')); });
      await drag(q('.cbx-chip[data-k="type"]'), back, .5, .78, function() { el('type').classList.add('in'); selectOnly(el('type')); });
      await sleep(500);
      selectOnly(null); toast.classList.add('show'); await sleep(1300); toast.classList.remove('show');
      await caption(4, 'Every row in your sheet is a card');
      await flip(false);
      for (var i = 1; i < ROWS.length && visible; i++) {
        await moveTo(next, .5, .5, 500); await click();
        next.classList.add('press'); setTimeout(function() { next.classList.remove('press'); }, 160);
        fill(i); await sleep(1400);
      }
      await moveTo(next, 3.5, 4, 700);
      await sleep(2200);
    }
    running = false;
  }
  fill(0);
  if (reduce) { showAll(); return; }
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function(es) { visible = es[0].isIntersecting; if (visible) loop(); }, { threshold: .35 }).observe(stage);
  } else { visible = true; loop(); }
})();
</script>
</body>
</html>
