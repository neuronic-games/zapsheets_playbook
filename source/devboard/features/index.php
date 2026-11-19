<?php
$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_base = preg_replace('#/devboard/features/?$#', '/', $_raw);
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
$_pageUrl = htmlspecialchars($_abs . 'devboard/features', ENT_QUOTES);
$_ogImg   = htmlspecialchars($_abs . 'images/og/devboard-og.png?v=2', ENT_QUOTES);
$_icon    = htmlspecialchars($_abs . 'images/db_icon_180.png', ENT_QUOTES);
?>
<title>DevBoard — Features</title>
<meta name="description" content="DevBoard — playtest notes for game designers and their teams, plus estimates, invoices and reports for development studios. Powered by your Google Sheet.">
<link rel="canonical" href="<?= $_pageUrl ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Zapsheets">
<meta property="og:title" content="DevBoard — Features">
<meta property="og:description" content="DevBoard — playtest notes for game designers and their teams, plus estimates, invoices and reports for development studios. Powered by your Google Sheet.">
<meta property="og:url" content="<?= $_pageUrl ?>">
<meta property="og:image" content="<?= $_ogImg ?>">
<meta property="og:image:secure_url" content="<?= $_ogImg ?>">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="DevBoard — features">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="DevBoard — Features">
<meta name="twitter:description" content="DevBoard — playtest notes for game designers and their teams, plus estimates, invoices and reports for development studios. Powered by your Google Sheet.">
<meta name="twitter:image" content="<?= $_ogImg ?>">
<link rel="apple-touch-icon" href="<?= $_icon ?>">
<link rel="icon" type="image/png" href="images/db_icon_180.png" />
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

/* ── Live demo ── */
.live-demo-section { background:var(--dark); padding:4rem 2rem; }
.live-demo-inner { max-width:1080px; margin:0 auto; display:grid; grid-template-columns:1fr 1fr; gap:3.5rem; align-items:center; }
.live-demo-text .section-label { color:var(--green-light); }
.live-demo-text .section-title { color:var(--white); }
.live-demo-text p { font-size:15px; color:rgba(255,255,255,.65); line-height:1.7; margin-bottom:1.75rem; }
.live-demo-links { display:flex; flex-direction:column; gap:.75rem; }
.live-link { display:flex; align-items:center; justify-content:space-between; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); border-radius:10px; padding:14px 18px; text-decoration:none; transition:all .15s; }
.live-link:hover { background:rgba(255,255,255,.1); border-color:rgba(255,255,255,.2); }
.live-link-info { display:flex; align-items:center; gap:12px; }
.live-link-logo { width:34px; height:34px; border-radius:8px; object-fit:contain; background:rgba(255,255,255,.08); }
.live-link-name { font-size:13px; font-weight:600; color:var(--white); }
.live-link-desc { font-size:11px; color:rgba(255,255,255,.45); margin-top:1px; }
.live-link-arrow { font-size:14px; color:rgba(255,255,255,.35); }
.live-link.featured { border-color:rgba(76,170,75,.4); background:rgba(76,170,75,.08); }
.live-link.featured:hover { background:rgba(76,170,75,.14); }
.live-link.featured .live-link-arrow { color:var(--green-light); }

/* Demo browser */
.demo-browser { background:#e8e8e8; border-radius:14px; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,.4); }
.demo-chrome { background:#d4d4d4; padding:10px 14px; display:flex; align-items:center; gap:10px; }
.demo-dots { display:flex; gap:6px; }
.demo-dots span { width:12px; height:12px; border-radius:50%; }
.demo-dots .d1 { background:#f87171; } .demo-dots .d2 { background:#fbbf24; } .demo-dots .d3 { background:#4ade80; }
.demo-url { flex:1; background:var(--white); border-radius:6px; padding:4px 10px; font-size:11px; color:#666; font-family:monospace; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.demo-body { background:var(--app-bg); }
.demo-banner { padding:11px 14px 10px; display:flex; align-items:center; gap:10px; border-bottom:1px solid rgba(255,255,255,.06); }
.demo-logo { width:28px; height:28px; border-radius:7px; object-fit:contain; }
.demo-co-name { font-size:13px; font-weight:700; color:var(--white); letter-spacing:-.01em; }
.demo-co-tag  { font-size:8.5px; color:rgba(255,255,255,.4); margin-top:1px; }
.demo-carousel { height:70px; position:relative; overflow:hidden; }
.demo-carousel img { width:100%; height:100%; object-fit:cover; }
.demo-carousel-overlay { position:absolute; inset:0; background:linear-gradient(to top,rgba(26,28,48,.85),transparent 60%); }
.demo-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:6px; padding:8px 10px 10px; }
.demo-card { background:var(--app-bg2); border-radius:7px; overflow:hidden; }
.demo-card-cover { height:50px; overflow:hidden; position:relative; }
.demo-card-cover img { width:100%; height:100%; object-fit:cover; }
.demo-card-cover-ph { height:50px; display:flex; align-items:center; justify-content:center; font-size:18px; }
.demo-card-body { padding:5px 7px 6px; }
.demo-card-name { font-size:8.5px; font-weight:600; color:var(--white); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.demo-card-meta { font-size:7.5px; color:rgba(255,255,255,.4); margin-top:1px; }
.demo-card-badge { margin-top:3px; display:inline-block; font-size:7px; font-weight:600; padding:1px 6px; border-radius:999px; background:rgba(76,170,75,.2); color:#7dcf7c; border:1px solid rgba(76,170,75,.25); }

@media(max-width:820px){ .live-demo-inner { grid-template-columns:1fr; } }

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

/* ── DevBoard colour overrides (teal) ── */
:root { --green:#1a5f7a; --green-light:#6cc3dc; --green-pale:#e7f2f6; }
.stage-brand { background:rgba(108,195,220,.18); color:#8fd3e6; }
.compare-table td.ours { background:rgba(231,242,246,.6); }
.cta-section { border-top:1px solid rgba(26,95,122,.15); }
.feat-icon.teal { background:#e7f2f6; }
.aud-grid { display:grid; grid-template-columns:1fr 1fr; gap:1.25rem; }
.aud-card { border:1px solid var(--border); border-radius:var(--radius); padding:2rem; background:var(--white); }
.aud-card .aud-tag { display:inline-block; font-size:11px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; color:var(--green); background:var(--green-pale); border-radius:999px; padding:3px 10px; margin-bottom:.9rem; }
.aud-card h3 { font-family:'Fraunces',Georgia,serif; font-size:1.45rem; font-weight:600; color:var(--dark); margin-bottom:.6rem; letter-spacing:-.01em; }
.aud-card p { font-size:15px; color:var(--text-mid); line-height:1.7; }
.aud-card ul { margin:.9rem 0 0 1.1rem; } .aud-card li { font-size:14px; color:var(--text-mid); margin-bottom:.35rem; }
@media(max-width:720px){ .aud-grid { grid-template-columns:1fr; } }

/* ── DevBoard UI mock-ups ── */
@font-face { font-family:'DBDINBlack'; src:url('fonts/DINBlack.woff2') format('woff2'), url('fonts/DINBlack.ttf') format('truetype'); }
@font-face { font-family:'DBDIN';      src:url('fonts/DINMedium.woff2') format('woff2'), url('fonts/DINMedium.ttf') format('truetype'); }
.dbx { background:#eef2f6; font-family:'DBDIN','Inter',sans-serif; color:#1e293b; padding:14px; text-align:left; }
.dbx-dlg { background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(15,23,42,.14); padding:14px 14px 12px; }
.dbx-h { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
.dbx-title { font-family:'DBDINBlack','Inter',sans-serif; font-size:12px; letter-spacing:.06em; text-transform:uppercase; }
.dbx-title em { font-style:normal; color:#1a5f7a; }
.dbx-ctl { margin-left:auto; display:flex; align-items:center; gap:4px; }
.dbx-sq { width:22px; height:22px; border:1.5px solid #d0d8e0; border-radius:5px; display:flex; align-items:center; justify-content:center; color:#999; font-size:12px; }
.dbx-n { font-family:'DBDINBlack','Inter',sans-serif; color:#1a5f7a; font-size:12px; min-width:14px; text-align:center; }
.dbx-lbl { font-size:8px; color:#999; letter-spacing:.06em; text-transform:uppercase; margin-right:5px; }
.dbx-timer { border:1.5px solid #e67e22; background:#fff8f2; color:#e67e22; border-radius:6px; font-family:'DBDINBlack','Inter',sans-serif; font-size:10px; padding:4px 7px; }
.dbx-row3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px; margin-bottom:10px; }
.dbx-f label { display:block; font-family:'DBDINBlack','Inter',sans-serif; font-size:7.5px; letter-spacing:.07em; text-transform:uppercase; color:#888; margin-bottom:3px; }
.dbx-in { border:1px solid #d1dde6; border-radius:6px; font-size:10px; padding:5px 7px; background:#fff; }
.dbx-in + .dbx-in { margin-top:4px; }
.dbx-grid-h { display:grid; grid-template-columns:1fr 1fr; gap:0 10px; font-family:'DBDINBlack','Inter',sans-serif; font-size:7.5px; letter-spacing:.07em; text-transform:uppercase; color:#888; border-top:1px solid #edf1f5; padding-top:9px; margin-bottom:4px; }
.dbx-pair { display:grid; grid-template-columns:1fr 1fr; border:1px solid #d1dde6; border-radius:7px; overflow:hidden; margin-bottom:6px; font-size:9.5px; line-height:1.45; }
.dbx-pair > div { padding:6px 8px; } .dbx-pair > div + div { background:#f2f6fa; border-left:1px solid #d1dde6; }
.dbx-pair.focus { border-color:#1a5f7a; box-shadow:0 0 0 2px rgba(26,95,122,.12); }
.dbx-ph { color:#a8b3c0; }
.dbx-photo { display:block; width:100%; height:62px; object-fit:cover; border-radius:5px; }
.dbx-acts { display:flex; justify-content:flex-end; gap:6px; margin-top:8px; }
.dbx-btn { font-family:'DBDINBlack','Inter',sans-serif; font-size:8.5px; letter-spacing:.06em; text-transform:uppercase; border:1.5px solid #d0d8e0; color:#888; border-radius:6px; padding:5px 10px; }
.dbx-btn.go { background:#1a5f7a; border-color:#1a5f7a; color:#fff; }
/* contracts mock */
.dbx-client { background:#1a1a2e; color:#fff; border-radius:10px 10px 0 0; padding:10px 12px; display:flex; justify-content:space-between; align-items:baseline; }
.dbx-client b { font-family:'DBDINBlack','Inter',sans-serif; font-size:12px; font-weight:normal; }
.dbx-client span { font-size:9px; color:rgba(255,255,255,.55); }
.dbx-cbody { background:#fff; border-radius:0 0 10px 10px; overflow:hidden; box-shadow:0 6px 20px rgba(15,23,42,.08); }
.dbx-game { font-family:'DBDINBlack','Inter',sans-serif; font-size:11px; color:#1a5f7a; padding:9px 12px 6px; display:flex; justify-content:space-between; }
.dbx-game span { font-family:'DBDIN','Inter',sans-serif; font-size:9px; color:#94a3b8; }
.dbx-doc { display:grid; grid-template-columns:auto auto 1fr auto; gap:8px; align-items:baseline; padding:7px 12px; border-top:1px solid #edf1f5; font-size:9.5px; }
.dbx-pill { font-family:'DBDINBlack','Inter',sans-serif; font-size:7.5px; letter-spacing:.05em; text-transform:uppercase; border-radius:4px; padding:3px 6px 2px; }
.dbx-pill.est { color:#2e7d9e; background:#e8f4f8; } .dbx-pill.inv { color:#a16207; background:#fef3cd; } .dbx-pill.paid { color:#15803d; background:#dcfce7; }
.dbx-ref { color:#999; } .dbx-date { color:#bbb; }
.dbx-amt { font-family:'DBDINBlack','Inter',sans-serif; font-size:11px; color:#111; }
.dbx-cbtns { display:flex; gap:5px; justify-content:flex-end; padding:7px 12px 10px; border-top:1px solid #edf1f5; background:#f8fafc; }
.dbx-cb { font-family:'DBDINBlack','Inter',sans-serif; font-size:8px; letter-spacing:.06em; text-transform:uppercase; border:1.5px solid #1a5f7a; color:#1a5f7a; border-radius:6px; padding:4px 8px; }
.dbx-cb.inv { border-color:#a16207; color:#a16207; }
.dbx-pdf { background:#fff; border-radius:8px; box-shadow:0 10px 26px rgba(15,23,42,.16); padding:12px 13px; margin:12px 0 0 auto; width:68%; font-size:8.5px; }
.dbx-pdf-h { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px; }
.dbx-pdf-h b { font-family:'DBDINBlack','Inter',sans-serif; font-weight:normal; font-size:13px; letter-spacing:.04em; }
.dbx-pdf-h span { color:#94a3b8; text-align:right; line-height:1.4; }
.dbx-li { display:flex; justify-content:space-between; padding:4px 0; border-top:1px solid #edf1f5; }
.dbx-li.sub { color:#64748b; } .dbx-li.disc { color:#64748b; }
.dbx-total { display:flex; justify-content:space-between; margin-top:5px; font-family:'DBDINBlack','Inter',sans-serif; font-size:13px; color:#E8623A; }
.ie-toast { position:absolute; left:50%; transform:translate(-50%,8px); background:#15803d; color:#fff; font-family:'Inter',system-ui,sans-serif; font-size:11px; font-weight:600; padding:6px 12px; border-radius:999px; box-shadow:0 6px 18px rgba(21,128,61,.35); opacity:0; transition:opacity .2s, transform .2s; white-space:nowrap; z-index:5; }
.ie-toast.show { opacity:1; transform:translate(-50%,0); }
.ie-cursor { position:absolute; left:0; top:0; z-index:7; filter:drop-shadow(0 2px 3px rgba(0,0,0,.3)); pointer-events:none; }
.ie-cursor.down { animation:ie-click .18s ease; }
@keyframes ie-click { 50% { scale:.82; } }
/* ── Session entry animation ── */
.sa-stage { position:relative; overflow:hidden; user-select:none; height:460px; }   /* fixed so the page doesn't jump as rows are added */
@media (max-width:560px) { .sa-stage { height:auto; min-height:540px; } }
.sa-dlg { transition:opacity .35s, transform .35s; }
.sa-dlg.gone { opacity:0; transform:translateY(-8px) scale(.98); }
.sa-timer { color:#bbb; border-color:#d0d8e0; background:#fff; transition:all .2s; }
.sa-timer.run { color:#e67e22; border-color:#e67e22; background:#fff8f2; }
.sa-timer.hover, .sa-plus.hover { box-shadow:0 0 0 2px rgba(26,95,122,.25); }
.sa-people .dbx-in { min-height:22px; position:relative; }
.sa-people .dbx-in.ph { color:#a8b3c0; }
.sa-people .dbx-in.focus, .sa-cell.focus { border-color:#1a5f7a; box-shadow:0 0 0 2px rgba(26,95,122,.12); }
.sa-drop { position:absolute; left:-1px; right:-1px; top:100%; margin-top:2px; background:#fff; border:1px solid #d1dde6; border-radius:6px; box-shadow:0 6px 16px rgba(0,0,0,.12); font-size:9.5px; z-index:3; overflow:hidden; }
.sa-drop div { padding:4px 7px; } .sa-drop div.on { background:#e7f2f6; color:#1a5f7a; }
.sa-rows .dbx-pair { min-height:30px; transition:border-color .15s, box-shadow .15s; }
.sa-rows .dbx-pair > div { position:relative; min-height:28px; }
.sa-rows .dbx-pair.focus { border-color:#1a5f7a; box-shadow:0 0 0 2px rgba(26,95,122,.12); }
.sa-ph { color:#a8b3c0; }
.sa-caret::after { content:''; display:inline-block; width:1.5px; height:1em; background:#1a5f7a; margin-left:1px; vertical-align:-.15em; animation:ie-blink 1s steps(1) infinite; }
.sa-upl { display:flex; align-items:center; gap:6px; color:#94a3b8; font-size:9px; height:62px; justify-content:center; }
.sa-spin { width:13px; height:13px; border:2px solid #d0d8e0; border-top-color:#1a5f7a; border-radius:50%; animation:sa-spin .8s linear infinite; }
@keyframes sa-spin { to { transform:rotate(360deg); } }
@keyframes ie-blink { 50% { opacity:0; } }
.sa-photo { animation:sa-pop .35s ease; }
@keyframes sa-pop { from { opacity:0; transform:scale(.96); } }
.sa-save.busy { opacity:.6; }
.sa-key { position:absolute; z-index:6; background:#1e293b; color:#fff; font-family:'Inter',system-ui,sans-serif; font-size:11px; font-weight:600; padding:4px 8px; border-radius:6px; box-shadow:0 4px 10px rgba(0,0,0,.25); opacity:0; transform:translateY(4px); transition:opacity .15s, transform .15s; pointer-events:none; white-space:nowrap; }
.sa-key.show { opacity:1; transform:none; }
.sa-list { position:absolute; inset:14px; opacity:0; transform:translateY(10px); transition:opacity .4s, transform .4s; pointer-events:none; }
.sa-list.show { opacity:1; transform:none; }
.sa-game { background:#1a1a2e; color:#fff; border-radius:10px 10px 0 0; padding:10px 12px; display:flex; justify-content:space-between; align-items:baseline; }
.sa-game b { font-family:'DBDINBlack','Inter',sans-serif; font-weight:normal; font-size:13px; }
.sa-chips { font-size:9px; color:rgba(255,255,255,.6); }
.sa-sess { background:#fff; border-top:1px solid #edf1f5; padding:9px 12px; font-size:9.5px; }
.sa-sess:last-child { border-radius:0 0 10px 10px; }
.sa-sess-h { display:flex; gap:8px; align-items:baseline; color:#64748b; }
.sa-sess-p { color:#94a3b8; margin-top:3px; }
.sa-new { background:#f2fbf4; box-shadow:inset 3px 0 0 #15803d; }
.sa-toast { bottom:18px; }
.sa-cursor { transform:translate(330px,380px); }
@media (prefers-reduced-motion: reduce) { .sa-cursor, .sa-key { display:none; } }
.pro-tag { display:inline-block; margin-left:4px; background:var(--amber-pale); color:#a35f12; font-size:10.5px; font-weight:600; letter-spacing:.05em; text-transform:uppercase; padding:2px 7px; border-radius:999px; vertical-align:2px; }
.tdx { padding:16px 14px 18px; }
.tdx-cap { font-family:'DBDINBlack','Inter',sans-serif; font-size:7.5px; letter-spacing:.08em; text-transform:uppercase; color:#94a3b8; margin:0 0 5px 2px; }
.tdx-flag { background:#fff; border-color:#f5c518; box-shadow:0 0 0 2px rgba(245,197,24,.3); }
.tdx-flag > div:last-child { position:relative; padding-right:26px; }
.tdx-bulb { position:absolute; top:5px; right:5px; width:17px; height:17px; border-radius:4px; border:1px solid #d0d8e4; background:#fff; color:#a0a8b4; display:flex; align-items:center; justify-content:center; }
.tdx-bulb.on { color:#b8860b; background:#fff8db; border-color:#f5c518; } .tdx-bulb.on .g { fill:#ffd43b; }
.tdx-list { background:#fff; border:1px solid #e3e8ef; border-radius:8px; }
.tdx-row { position:relative; display:flex; align-items:center; gap:6px; padding:6px 8px 6px 4px; border-top:1px solid #eef1f5; font-size:9.5px; }
.tdx-row:first-child { border-top:none; }
.tdx { position:relative; overflow:hidden; padding-bottom:24px; }
.tdx-row { transition:transform .22s ease, box-shadow .18s, background .18s; background:#fff; }
.tdx-row.lift { background:#fffbe6; box-shadow:0 8px 20px rgba(15,23,42,.18); z-index:3; transition:box-shadow .18s, background .18s; border-radius:6px; }
.tdx-say { transition:color .2s; min-height:10px; }
.tdx-say.step { color:#b8860b; }
.tdx-h { color:#c3cad4; font-size:8px; letter-spacing:-.15em; width:10px; }
.tdx-cb { width:11px; height:11px; border:1.4px solid #9aa4b1; border-radius:3px; flex-shrink:0; font-size:8px; line-height:9px; text-align:center; color:#fff; }
.tdx-cb.on { background:#b8860b; border-color:#b8860b; }
.tdx-t { flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:#334155; }
.tdx-t b { font-family:'DBDINBlack','Inter',sans-serif; font-weight:normal; color:#1a5f7a; margin-right:4px; }
.tdx-t i { font-style:normal; color:#94a3b8; font-size:8.5px; margin-left:4px; }
.tdx-row.done .tdx-t b, .tdx-row.done .tdx-t { text-decoration:line-through; color:#aab2bd; }
.tdx-row.done .tdx-t i { text-decoration:none; }
.tdx-row.done .tdx-ic { opacity:.5; }
.tdx-ic { width:17px; height:17px; display:flex; align-items:center; justify-content:center; color:#c3cad4; font-family:'DBDINBlack','Inter',sans-serif; font-size:10px; letter-spacing:-.05em; border-radius:4px; flex-shrink:0; }
.tdx-ic.p3 { color:#d92d20; } .tdx-ic.p2 { color:#e08a00; } .tdx-ic.p1 { color:#2e7d32; }
.tdx-ic { transition:color .25s, background .15s, border-color .15s; border:1px solid transparent; }
.tdx-ic.on { color:#1a5f7a; } .tdx-ic.due { color:#7c4dbd; }
.tdx-ic.act { background:#f3f5f8; border-color:#dde3ea; }
.tdx-h.act { color:#64748b; }
.tdx-toast { bottom:6px; }
.tdx-menu { position:absolute; right:0; top:0; opacity:0; transform:translateY(-3px); transition:opacity .15s, transform .15s; pointer-events:none; z-index:3; background:#fff; border:1px solid #e1e6ed; border-radius:7px; box-shadow:0 8px 22px rgba(15,23,42,.16); padding:3px; display:flex; flex-direction:column; min-width:88px; }
.tdx-menu span { padding:4px 7px; border-radius:4px; font-size:9px; color:#334155; }
.tdx-menu span { transition:background .12s; }
.tdx-menu.show { opacity:1; transform:none; }
.tdx-menu span.cur { background:#eef6fa; color:#1a5f7a; font-family:'DBDINBlack','Inter',sans-serif; }
</style>
</head>
<body>

<nav>
  <a class="nav-logo" href="devboard">
    <img src="images/db_icon_180.png" alt="DevBoard" />
    <span class="nav-logo-name">DevBoard</span>
  </a>
  <div class="nav-links">
    <a href="#who">Who it's for</a>
    <a href="#features">Features</a>
    <a href="#screenshots">See it in action</a>
    <a href="#compare">Compare</a>
  </div>
  <a href="devboard" class="nav-cta">Get started</a>
</nav>

<section class="hero">
  <div class="hero-tag">For game designers &amp; dev studios</div>
  <h1>Every playtest, <em>remembered</em>.</h1>
  <p>DevBoard is a playtest journal your whole team can use — and, for studios, a simple way to turn the work into estimates, invoices and reports. All kept in your own Google Sheet.</p>
  <div class="hero-btns">
    <a href="devboard" class="btn-primary">Get DevBoard</a>
    <a href="devboard/features#screenshots" class="btn-ghost">See it in action</a>
  </div>
</section>

<div class="pipeline">
  <span class="stage-pill stage-sheet">Playtest</span>
  <span class="stage-arrow">→</span>
  <span class="stage-pill stage-brand">Notes &amp; fixes</span>
  <span class="stage-arrow">→</span>
  <span class="stage-pill stage-catalog">Report</span>
  <span class="stage-arrow">→</span>
  <span class="stage-pill stage-share">Invoice</span>
</div>

<section id="who">
  <div class="container">
    <div class="section-label">Who it's for</div>
    <h2 class="section-title">One board, two kinds of teams</h2>
    <p class="section-sub">Use the playtest journal on its own, or add contracts when you develop games for clients.</p>
    <div class="aud-grid">
      <div class="aud-card">
        <span class="aud-tag">Designers &amp; co-designers</span>
        <h3>Keep every playtest in one place</h3>
        <p>Log each playtest, meeting and idea as it happens — who played, what you saw, and what you'll change. Your co-designers and playtesters can add their own notes through a share link.</p>
        <ul>
          <li>Observations side by side with fixes</li>
          <li>Rounds counter and session timer</li>
          <li>Paste photos straight into your notes</li>
          <li>Full history for every game</li>
        </ul>
      </div>
      <div class="aud-card">
        <span class="aud-tag">Small studios &amp; freelancers · Pro</span>
        <h3>Get paid for development work</h3>
        <p>Developing or playtesting games for publishers? Send a professional estimate, turn it into an invoice, attach a playtest report, and see what's been paid — without a separate invoicing app.</p>
        <ul>
          <li>Estimates and invoices as shareable PDFs</li>
          <li>Printing costs, discounts and partial invoices</li>
          <li>Estimate → Invoiced → Paid status at a glance</li>
          <li>Playtest reports generated from your sessions</li>
        </ul>
      </div>
    </div>
  </div>
</section>

<section id="features" style="background:var(--slate-pale);">
  <div class="container">
    <div class="section-label">Features</div>
    <h2 class="section-title">Everything between the first playtest and the final invoice</h2>
    <p class="section-sub">Built by game designers for the way playtests actually happen — fast, messy, and with a table full of people.</p>
    <div class="features-grid">
      <div class="feat-card">
        <div class="feat-icon teal">📋</div>
        <h3>Session log</h3>
        <p>Record playtests, meetings and ideas with the date, location and everyone who was there. Each game keeps its own history, filterable by type.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon green">💡</div>
        <h3>Observations &amp; solutions</h3>
        <p>Write what happened on the left and what you'll do about it on the right. Bullets continue automatically and arrow keys move between cells.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon amber">⏱</div>
        <h3>Rounds &amp; timer</h3>
        <p>Count rounds and time the session with one tap. The timer keeps running if you close the dialog or reload the page — only the button stops it.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon purple">📷</div>
        <h3>Photos in your notes</h3>
        <p>Paste a photo of the table, a scoring sheet or a prototype card straight into an observation. It's uploaded and saved with the session.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon slate">🤝</div>
        <h3>Team &amp; collaborators</h3>
        <p>Share a private link for a game. Co-designers and playtesters sign in with a quick profile to read the history and add sessions — no access to your spreadsheet needed.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon rose">📄</div>
        <h3>Playtest reports</h3>
        <p>Turn a game's sessions into a tidy report document for a publisher or client in one click.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon teal">🧾</div>
        <h3>Estimates &amp; invoices <span class="pro-tag">Pro</span></h3>
        <p>Create estimates and invoices as clean PDFs with your logo, line items, printing costs and discounts. Share them by email with a link.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon green">✅</div>
        <h3>Edit &amp; track payment <span class="pro-tag">Pro</span></h3>
        <p>Change an amount and the PDF updates at the same link. Mark each contract Estimate, Invoiced or Paid and see it colour-coded in your client list.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon amber">📌</div>
        <h3>TODOs from your notes</h3>
        <p>Tap the light bulb on any note to make it a TODO. Every game card lists its open TODOs at the top — tick one off and it's crossed out.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon rose">❗</div>
        <h3>Priority, owner &amp; due date</h3>
        <p>All TODOs come together on the Board. Give each one a priority, a person and a due date, and drag them into the order you'll tackle them.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon purple">🗂️</div>
        <h3>Board overview</h3>
        <p>See every game by stage — design, prototype, playtesting, signed — next to your TODOs and latest contracts, on one screen.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon dark">📊</div>
        <h3>Lives in your Sheet</h3>
        <p>Sessions, contracts and people are rows in a Google Sheet you own — the same one PitchBoard and GameBoard use. Works on phone, tablet and desktop.</p>
      </div>
    </div>
  </div>
</section>

<section class="shot-section" id="screenshots">
  <div class="container">

    <!-- Screenshot 1: Session -->
    <div class="shot-grid" style="margin-bottom:5rem;">
      <div class="shot-text">
        <div class="section-label">Playtest notes</div>
        <h2 class="section-title" style="margin-bottom:1rem;">Take notes while the game is on the table</h2>
        <p>Start the timer, add who's playing and type as you watch — each row pairs what happened with what you'll change. Paste a photo of the table straight into a note. Save, and it's in your Sheet.</p>
        <ul>
          <li>Playtest, Meeting and Idea sessions</li>
          <li>Testers picked from your People list</li>
          <li>Rounds counter and a timer that survives reloads</li>
          <li>Paste photos into any observation</li>
          <li>Works on an iPad at the table</li>
        </ul>
      </div>
      <div class="browser-frame">
        <div class="browser-chrome">
          <div class="browser-dots"><span class="d1"></span><span class="d2"></span><span class="d3"></span></div>
          <div class="browser-url">zapsheets.com/app/…/devboard</div>
        </div>
        <div class="dbx sa-stage" id="saDemo" aria-hidden="true">
          <div class="dbx-dlg sa-dlg">
            <div class="dbx-h">
              <span class="dbx-title">+ Session <em>—</em> Club K9</span>
              <span class="dbx-ctl"><span class="dbx-sq">−</span><span class="dbx-n sa-rounds">0</span><span class="dbx-sq sa-plus">+</span><span class="dbx-lbl">Rounds</span><span class="dbx-timer sa-timer">⏱ 0</span></span>
            </div>
            <div class="dbx-row3">
              <div class="dbx-f"><label>Date</label><div class="dbx-in">11/12/2026</div></div>
              <div class="dbx-f"><label>Type</label><div class="dbx-in">Playtest</div></div>
              <div class="dbx-f sa-people"><label>People</label></div>
            </div>
            <div class="dbx-grid-h"><span>Observations</span><span>Solutions</span></div>
            <div class="sa-rows"></div>
            <div class="dbx-acts"><span class="dbx-btn">Cancel</span><span class="dbx-btn go sa-save">Add Session</span></div>
          </div>
          <div class="sa-list">
            <div class="sa-game"><b>Club K9</b><span class="sa-chips">4 Playtests · 1 Meeting</span></div>
            <div class="sa-sess sa-new"><div class="sa-sess-h"><span class="dbx-pill est">Playtest 4</span><span class="sa-sum"></span></div><div class="sa-sess-p">Maya Chen, Leo Park, Sam Rivera</div></div>
            <div class="sa-sess"><div class="sa-sess-h"><span class="dbx-pill est">Playtest 3</span><span>Nov 5 · Game Café · 52 min</span></div><div class="sa-sess-p">Ana Torres, Leo Park</div></div>
            <div class="sa-sess"><div class="sa-sess-h"><span class="dbx-pill inv">Meeting 1</span><span>Oct 30 · Video call</span></div><div class="sa-sess-p">Co-design call</div></div>
          </div>
          <div class="sa-key"></div>
          <div class="ie-toast sa-toast">✓ Session saved to your Sheet</div>
          <svg class="ie-cursor sa-cursor" width="22" height="22" viewBox="0 0 24 24"><path d="M4 2l15 11-7 1.2L8.6 21 4 2z" fill="#fff" stroke="#111" stroke-width="1.5" stroke-linejoin="round"/></svg>
        </div>
      </div>
    </div>

    <!-- Screenshot 2: Contracts -->
    <div class="shot-grid flip">
      <div class="shot-text">
        <div class="section-label">Contracts</div>
        <h2 class="section-title" style="margin-bottom:1rem;">Estimate, invoice, get paid</h2>
        <p>Keep every client and game together. Create an estimate in a few fields, turn it into an invoice — in full or as a percentage — and share the PDF link by email. When the money arrives, mark it Paid.</p>
        <ul>
          <li>PDF estimates and invoices with your company logo</li>
          <li>Separate printing line item and optional discounts</li>
          <li>Edit later — the PDF updates at the same link</li>
          <li>Estimate · Invoiced · Paid status for each contract</li>
        </ul>
      </div>
      <div class="browser-frame">
        <div class="browser-chrome">
          <div class="browser-dots"><span class="d1"></span><span class="d2"></span><span class="d3"></span></div>
          <div class="browser-url">zapsheets.com/app/…/devboard</div>
        </div>
        <div class="dbx">
          <div class="dbx-client"><b>Riverbend Games</b><span>3 contracts</span></div>
          <div class="dbx-cbody">
            <div class="dbx-game">Break of Dawn <span>1 estimate · 2 invoices</span></div>
            <div class="dbx-doc"><span class="dbx-pill paid">Paid</span><span class="dbx-ref">K4Q7ZR21</span><span class="dbx-date">11/10/2026</span><span class="dbx-amt">$260.00</span></div>
            <div class="dbx-doc"><span class="dbx-pill inv">Invoiced</span><span class="dbx-ref">P8XW3M5C</span><span class="dbx-date">11/24/2026</span><span class="dbx-amt">$130.00</span></div>
            <div class="dbx-doc"><span class="dbx-pill est">Estimate</span><span class="dbx-ref">CA7N2E4S</span><span class="dbx-date">11/04/2026</span><span class="dbx-amt">$390.00</span></div>
            <div class="dbx-cbtns"><span class="dbx-cb">✉ Share</span><span class="dbx-cb">Edit</span><span class="dbx-cb inv">+ Invoice</span></div>
          </div>
          <div class="dbx-pdf">
            <div class="dbx-pdf-h"><b>INVOICE</b><span>K4Q7ZR21<br>Riverbend Games</span></div>
            <div class="dbx-li"><span>Break of Dawn development · 2 playtests, test reports</span><span>$210.00</span></div>
            <div class="dbx-li"><span>Printing</span><span>$50.00</span></div>
            <div class="dbx-li sub"><span>Subtotal</span><span>$260.00</span></div>
            <div class="dbx-total"><span>Total</span><span>$260.00</span></div>
          </div>
        </div>
      </div>
    </div>

    <!-- Screenshot 3: TODOs -->
    <div class="shot-grid" style="margin-top:5rem;">
      <div class="shot-text">
        <div class="section-label">TODOs</div>
        <h2 class="section-title" style="margin-bottom:1rem;">Turn notes into a to-do list</h2>
        <p>When a playtest note needs work, tap the light bulb. It shows up on the game's card and on your Board, where you can set who's on it, how urgent it is and when it's due — then drag your list into order.</p>
        <ul>
          <li>One tap to flag a note as a TODO</li>
          <li>Priority from ! to !!!, an owner and a due date</li>
          <li>Drag and drop to reorder your list</li>
          <li>Tick it off — it's crossed out everywhere</li>
          <li>Kept in a "todos" tab in your Sheet</li>
        </ul>
      </div>
      <div class="browser-frame">
        <div class="browser-chrome">
          <div class="browser-dots"><span class="d1"></span><span class="d2"></span><span class="d3"></span></div>
          <div class="browser-url">zapsheets.com/app/…/devboard</div>
        </div>
        <div class="dbx tdx" id="tdDemo" aria-hidden="true">
          <div class="tdx-cap tdx-say">Your TODOs, on the Board</div>
          <div class="tdx-list">
            <div class="tdx-row"><span class="tdx-h">⋮⋮</span><span class="tdx-cb"></span><span class="tdx-t"><b>Components</b> Show the component list before setup… <i>Ghost Party · Playtest 1</i></span><span class="tdx-ic p3">!!!</span><span class="tdx-ic tdx-who on"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg></span><span class="tdx-ic due"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4.5" width="18" height="17" rx="2"/><path d="M16 2.5v4M8 2.5v4M3 10h18"/></svg></span></div>
            <div class="tdx-row tdx-bid"><span class="tdx-h">⋮⋮</span><span class="tdx-cb"></span><span class="tdx-t"><b>Bidding</b> Paying to go last is too strong. Cap bids… <i>Club K9 · Playtest 4</i></span><span class="tdx-ic p2">!!</span><span class="tdx-ic tdx-who"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg></span><span class="tdx-ic"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4.5" width="18" height="17" rx="2"/><path d="M16 2.5v4M8 2.5v4M3 10h18"/></svg></span></div>
            <div class="tdx-row"><span class="tdx-h">⋮⋮</span><span class="tdx-cb"></span><span class="tdx-t"><b>Fase names</b> Stage 3 shouldn't have a number… <i>Ghost Party · Editing 1</i></span><span class="tdx-ic p1">!</span><span class="tdx-ic tdx-who"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg></span><span class="tdx-ic"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4.5" width="18" height="17" rx="2"/><path d="M16 2.5v4M8 2.5v4M3 10h18"/></svg></span></div>
            <div class="tdx-row done"><span class="tdx-h">⋮⋮</span><span class="tdx-cb on">✓</span><span class="tdx-t"><b>Blockers</b> Add blockers for unused player spaces. <i>Club K9 · Playtest 3</i></span><span class="tdx-ic ">!</span><span class="tdx-ic tdx-who on"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/></svg></span><span class="tdx-ic"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4.5" width="18" height="17" rx="2"/><path d="M16 2.5v4M8 2.5v4M3 10h18"/></svg></span></div>
          </div>
          <div class="tdx-menu"><span>Maya Chen</span><span class="tdx-leo">Leo Park</span><span>Sam Rivera</span></div>
          <div class="ie-toast tdx-toast">✓ Saved to your Sheet</div>
          <svg class="ie-cursor tdx-cursor" width="22" height="22" viewBox="0 0 24 24"><path d="M4 2l15 11-7 1.2L8.6 21 4 2z" fill="#fff" stroke="#111" stroke-width="1.5" stroke-linejoin="round"/></svg>
        </div>
      </div>
    </div>

  </div>
</section>

<section class="ownership-section">
  <div class="container">
    <div class="section-label">Data ownership</div>
    <h2 class="section-title">Your notes live in your Google Sheet</h2>
    <p class="section-sub">No new database to trust. Every session, observation and contract is a row in a spreadsheet you own — search it, export it, share it, back it up.</p>
    <div class="own-grid">
      <div class="own-card">
        <div class="num">$2.99</div>
        <p>Per month for <strong>Core</strong>: your whole playtest journal, team notes and reports. <strong>Pro</strong>, at $3.99 a month, adds contracts, estimates and invoices — no separate invoicing app to pay for.</p>
      </div>
      <div class="own-card">
        <div class="num">100%</div>
        <p>Your data. Every playtest, tester, note and invoice — in a spreadsheet only you control.</p>
      </div>
      <div class="own-card">
        <div class="num">∞</div>
        <p>Games and sessions. Log as many playtests as your sheet holds — no tier limits.</p>
      </div>
    </div>
  </div>
</section>

<section id="compare">
  <div class="container">
    <div class="section-label">How we compare</div>
    <h2 class="section-title">Notes and invoicing, in one place</h2>
    <p class="section-sub">Most teams juggle a notes app for playtests and a separate tool for invoices. DevBoard does both, built around how games are developed.</p>
    <div class="compare-wrap">
      <table class="compare-table">
        <thead>
          <tr>
            <th>Feature</th>
            <th class="ours">DevBoard</th>
            <th>Docs / Notion</th>
            <th>Invoicing app</th>
          </tr>
        </thead>
        <tbody>
          <tr><td>Playtest session log per game</td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span> <span style="font-size:12px;color:var(--text-muted)">by hand</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Observations paired with solutions</td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Rounds counter &amp; session timer</td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Collaborators add notes without sheet access</td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Playtest reports for clients</td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span> <span style="font-size:12px;color:var(--text-muted)">by hand</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Estimates &amp; invoices (PDF)</td><td class="ours"><span class="check">✓</span> <span class="pro-tag">Pro</span></td><td><span class="cross">—</span></td><td><span class="check">✓</span></td></tr>
          <tr><td>Paid / unpaid tracking</td><td class="ours"><span class="check">✓</span> <span class="pro-tag">Pro</span></td><td><span class="cross">—</span></td><td><span class="check">✓</span></td></tr>
          <tr><td>TODO list from your notes</td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Data in your own Google Sheet</td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Works with <a href="https://zapsheets.com/app/pitchboard/features" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline;text-underline-offset:2px">PitchBoard</a> &amp; <a href="https://zapsheets.com/app/gameboard/features" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline;text-underline-offset:2px">GameBoard</a></td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Monthly cost</td><td class="ours" style="font-weight:600;color:var(--dark);">$2.99 / mo Core<br>$3.99 / mo Pro</td><td><span style="font-size:12px;color:var(--text-muted)">varies</span></td><td><span style="font-size:12px;color:var(--text-muted)">varies</span></td></tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<section class="cta-section" id="cta">
  <div class="container">
    <div class="section-label">Ready to start?</div>
    <h2 class="section-title" style="text-align:center;max-width:560px;margin:0 auto 1rem;">Your next playtest deserves better notes</h2>
    <p>Connect your Google Sheet and log your first session in minutes.</p>
    <a href="devboard" class="btn-primary" style="font-size:16px;padding:15px 36px;">Get DevBoard</a>
  </div>
</section>

<footer>
  <a href="devboard">DevBoard</a> &mdash; built for board game makers &nbsp;·&nbsp; <a href="devboard/help">Help</a> &nbsp;·&nbsp; <a href="/">ZapSheets</a>
</footer>

<script>
// ── Session entry animation: a playtest is logged from start to save, then loops ──
(function() {
  var stage = document.getElementById('saDemo');
  if (!stage) return;
  var q = function(s) { return stage.querySelector(s); };
  var cursor = q('.sa-cursor'), key = q('.sa-key'), toast = q('.sa-toast');
  var dlg = q('.sa-dlg'), list = q('.sa-list'), timer = q('.sa-timer'), plus = q('.sa-plus'), roundsEl = q('.sa-rounds');
  var people = q('.sa-people'), rows = q('.sa-rows'), save = q('.sa-save'), summary = q('.sa-sum');
  var PHOTO = (document.querySelector('.dbx-photo-src') || {}).value || 'images/gameboard-features/carousel-pieces.webp';
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var visible = false, tick = null, secs = 0, rounds = 0;
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
  function click() { cursor.classList.remove('down'); void cursor.offsetWidth; cursor.classList.add('down'); return sleep(200); }
  async function press(label, el) {
    var p = at(el, .5, 0);
    key.textContent = label; key.style.left = (p[0] - 14) + 'px'; key.style.top = (p[1] - 26) + 'px';
    key.classList.add('show'); await sleep(650); key.classList.remove('show'); await sleep(120);
  }
  async function type(el, text) {
    el.classList.add('sa-caret'); el.textContent = '';
    for (var i = 0; i < text.length; i++) { el.textContent += text[i]; await sleep(38 + Math.random() * 35); }
  }
  function fmt(s) { var m = Math.floor(s / 60); return m + ':' + String(s % 60).padStart(2, '0'); }
  function showTimer() { timer.textContent = '⏱ ' + (tick ? fmt(secs) : Math.floor(secs / 60)); }
  function startTimer() { timer.classList.add('run'); tick = setInterval(function() { secs += 7; showTimer(); }, 100); showTimer(); }
  function stopTimer() { clearInterval(tick); tick = null; }

  function addPerson(ph) {
    var d = document.createElement('div'); d.className = 'dbx-in ph'; d.textContent = ph || 'Add tester…';
    people.appendChild(d); return d;
  }
  function addRow() {
    var r = document.createElement('div'); r.className = 'dbx-pair';
    r.innerHTML = '<div><span class="sa-ph">What happened…</span></div><div><span class="sa-ph">Thoughts or solutions…</span></div>';
    rows.appendChild(r); return r;
  }
  function clearCarets() { stage.querySelectorAll('.sa-caret').forEach(function(e) { e.classList.remove('sa-caret'); }); stage.querySelectorAll('.focus').forEach(function(e) { e.classList.remove('focus'); }); }

  async function tester(name) {
    var f = people.querySelector('.dbx-in.ph:last-child');
    await moveTo(f, .3, .5, 450); await click();
    f.classList.remove('ph'); f.classList.add('focus');
    await type(f, name.slice(0, 2));
    var dd = document.createElement('div'); dd.className = 'sa-drop';
    dd.innerHTML = '<div class="on">' + name + '</div><div>' + (name === 'Leo Park' ? 'Leah Gold' : 'Mark Adams') + '</div>';
    f.appendChild(dd); await sleep(450);
    await press('↵ Enter', f);
    f.textContent = name; f.classList.remove('focus', 'sa-caret');
    addPerson();
  }
  async function cell(row, col, text) {
    clearCarets(); row.classList.add('focus');
    var c = row.children[col]; c.innerHTML = '<span></span>';
    await type(c.firstChild, text);
  }
  async function roundUp() {
    await moveTo(plus, .5, .5, 450); plus.classList.add('hover'); await click(); plus.classList.remove('hover');
    rounds++; roundsEl.textContent = rounds; await sleep(200);
  }

  function reset() {
    stopTimer(); secs = 0; rounds = 0; roundsEl.textContent = '0';
    timer.classList.remove('run'); showTimer();
    people.querySelectorAll('.dbx-in').forEach(function(e) { e.remove(); }); addPerson();
    rows.innerHTML = ''; addRow();
    save.textContent = 'Add Session'; save.classList.remove('busy');
    dlg.classList.remove('gone'); list.classList.remove('show');
  }

  async function play() {
    reset(); await sleep(800);
    // Start the timer
    await moveTo(timer); timer.classList.add('hover'); await sleep(250); await click(); timer.classList.remove('hover');
    startTimer(); await sleep(500);
    // Testers
    await tester('Maya Chen'); await tester('Leo Park'); await tester('Sam Rivera');
    // Round 1 notes
    await roundUp();
    var r1 = rows.lastChild;
    await moveTo(r1.children[0], .3, .5, 500); await click();
    await cell(r1, 0, 'Round 1 — Maya 2, Leo 10, Sam 2');
    await press('→', r1.children[0]);
    await cell(r1, 1, 'Paying to go last is too strong. Cap bids at 6?');
    var r2 = addRow(); await sleep(300);
    // Round 2: paste a photo
    await roundUp();
    await moveTo(r2.children[0], .3, .5, 500); await click(); clearCarets(); r2.classList.add('focus');
    await press('⌘ V  paste photo', r2.children[0]);
    r2.children[0].innerHTML = '<div class="sa-upl"><span class="sa-spin"></span>Uploading image…</div>';
    await sleep(1100);
    r2.children[0].innerHTML = '<img class="dbx-photo sa-photo" src="' + PHOTO + '" alt="">';
    await sleep(500);
    await press('→', r2.children[0]);
    await cell(r2, 1, 'Pieces crowd the board. Add blockers for unused player spaces.');
    addRow(); await sleep(400); clearCarets();
    await roundUp();
    // Save
    await moveTo(save, .5, .5, 600); await click();
    save.textContent = 'Saving…'; save.classList.add('busy'); await sleep(800);
    summary.textContent = 'Nov 12 · ' + rounds + ' rounds · ' + Math.round(secs / 60) + ' min · 2 notes · 📷';
    stopTimer(); dlg.classList.add('gone'); await sleep(300);
    list.classList.add('show'); toast.classList.add('show');
    await moveTo(q('.sa-new'), .7, .6, 800);
    await sleep(1600); toast.classList.remove('show');
    await sleep(2200);
  }

  async function loop() { for (;;) { if (!visible) { await sleep(400); continue; } await play(); } }

  if (reduce) { reset(); list.classList.add('show'); dlg.classList.add('gone'); summary.textContent = 'Nov 12 · 3 rounds · 42 min · 2 notes · 📷'; return; }
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function(es) { visible = es[0].isIntersecting; }, { threshold: .35 }).observe(stage);
  } else { visible = true; }
  loop();
})();
// ── TODO list animation: assign a person, then drag the TODO to the top, then loop ──
(function() {
  var stage = document.getElementById('tdDemo');
  if (!stage) return;
  var q = function(s) { return stage.querySelector(s); };
  var cursor = q('.tdx-cursor'), menu = q('.tdx-menu'), leo = q('.tdx-leo'), toast = q('.tdx-toast'), say = q('.tdx-say');
  var list = q('.tdx-list'), sayDefault = say.textContent;
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var visible = false;
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
  function click() { cursor.classList.remove('down'); void cursor.offsetWidth; cursor.classList.add('down'); return sleep(200); }
  function caption(t) { say.textContent = t || sayDefault; say.classList.toggle('step', !!t); }
  async function saved() { toast.classList.add('show'); await sleep(1000); toast.classList.remove('show'); }
  function bid() { return q('.tdx-bid'); }

  function reset() {
    var b = bid(), rows = list.querySelectorAll('.tdx-row');
    if (rows[0] === b) list.insertBefore(b, rows[2]);   // back to second place
    list.querySelectorAll('.tdx-row').forEach(function(r) { r.style.transition = 'none'; r.style.transform = ''; r.classList.remove('lift'); });
    void list.offsetHeight;
    list.querySelectorAll('.tdx-row').forEach(function(r) { r.style.transition = ''; });
    b.querySelector('.tdx-who').classList.remove('on', 'act');
    b.querySelector('.tdx-h').classList.remove('act');
    menu.classList.remove('show'); leo.classList.remove('cur');
    caption('');
  }

  async function play() {
    reset(); await sleep(900);
    var b = bid(), who = b.querySelector('.tdx-who');
    // 1. Assign someone
    caption('Click the person icon to assign it');
    await moveTo(who, .5, .55, 800); who.classList.add('act'); await sleep(250); await click();
    var p = at(who, 1, 1);
    menu.style.left = (p[0] - menu.offsetWidth) + 'px'; menu.style.top = (p[1] + 4) + 'px';
    menu.classList.add('show'); await sleep(450);
    await moveTo(leo, .35, .55, 450); leo.classList.add('cur'); await sleep(250); await click();
    menu.classList.remove('show'); who.classList.remove('act'); who.classList.add('on');
    await saved();
    // 2. Drag it to the top
    caption('Drag it up — the list order is the order in your Sheet');
    var handle = b.querySelector('.tdx-h'), first = list.querySelector('.tdx-row');
    await moveTo(handle, .5, .55, 700); handle.classList.add('act'); await sleep(200); await click();
    b.classList.add('lift'); b.style.transform = 'scale(1.03)'; await sleep(300);
    var dy = first.getBoundingClientRect().top - b.getBoundingClientRect().top;
    var steps = 14;
    for (var i = 1; i <= steps; i++) {
      var y = dy * i / steps;
      b.style.transform = 'translateY(' + y + 'px) scale(1.03)';
      var c = at(handle, .5, .55); cursor.style.transition = 'none'; cursor.style.transform = 'translate(' + (c[0] - 3) + 'px,' + (c[1] - 2) + 'px)';
      if (i === Math.round(steps / 2)) first.style.transform = 'translateY(' + b.offsetHeight + 'px)';
      await sleep(45);
    }
    await sleep(150);
    b.classList.remove('lift'); b.style.transform = 'translateY(' + dy + 'px)'; handle.classList.remove('act');
    await sleep(240);
    list.querySelectorAll('.tdx-row').forEach(function(r) { r.style.transition = 'none'; r.style.transform = ''; });
    list.insertBefore(b, first);
    void list.offsetHeight;
    list.querySelectorAll('.tdx-row').forEach(function(r) { r.style.transition = ''; });
    await saved();
    caption('Your list, in your order');
    await moveTo(list, .55, 1.25, 900);
    await sleep(2600);
  }
  async function loop() { for (;;) { if (!visible) { await sleep(400); continue; } await play(); } }

  if (reduce) {   // finished state, no motion
    var b0 = bid(); b0.querySelector('.tdx-who').classList.add('on'); list.insertBefore(b0, list.firstChild);
    cursor.style.display = 'none'; return;
  }
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function(es) { visible = es[0].isIntersecting; }, { threshold: .35 }).observe(stage);
  } else { visible = true; }
  loop();
})();
</script>
</body>
</html>
