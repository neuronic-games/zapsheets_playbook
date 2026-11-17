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
<?php
$_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
$_origin = $_scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'zapsheets.com');
$_abs    = $_origin . $_base;
$_pageUrl = htmlspecialchars($_abs . 'gameboard/features', ENT_QUOTES);
$_ogImg   = htmlspecialchars($_abs . 'images/og/gameboard-og.png', ENT_QUOTES);
$_icon    = htmlspecialchars($_abs . 'images/gb_icon_180.png', ENT_QUOTES);
?>
<title>GameBoard — Features</title>
<meta name="description" content="GameBoard turns your Google Sheet into a polished catalog and game pages for your board games — no web developer, no web hosting.">
<link rel="canonical" href="<?= $_pageUrl ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Zapsheets">
<meta property="og:title" content="GameBoard — Features">
<meta property="og:description" content="GameBoard turns your Google Sheet into a polished catalog and game pages for your board games — no web developer, no web hosting.">
<meta property="og:url" content="<?= $_pageUrl ?>">
<meta property="og:image" content="<?= $_ogImg ?>">
<meta property="og:image:secure_url" content="<?= $_ogImg ?>">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="GameBoard — features">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="GameBoard — Features">
<meta name="twitter:description" content="GameBoard turns your Google Sheet into a polished catalog and game pages for your board games — no web developer, no web hosting.">
<meta name="twitter:image" content="<?= $_ogImg ?>">
<link rel="apple-touch-icon" href="<?= $_icon ?>">
<link rel="icon" type="image/png" href="images/gb_icon_180.png" />
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
/* ── Mini GameBoard site (mock-ups that look like a real UP Games page) ── */
@font-face { font-family:'GBDINBlack'; src:url('fonts/DINBlack.woff2') format('woff2'), url('fonts/DINBlack.ttf') format('truetype'); }
@font-face { font-family:'GBDIN';      src:url('fonts/DINMedium.woff2') format('woff2'), url('fonts/DINMedium.ttf') format('truetype'); }
.gbx { --gbx-brand:#693f99; background:#f2f0ed; font-family:'GBDIN','Inter',system-ui,sans-serif; color:#222; text-align:left; }
.gbx-banner { position:relative; background:var(--gbx-brand); transition:background .6s ease; display:flex; align-items:center; gap:11px; padding:11px 16px; min-height:56px; }
.gbx-logo { width:34px; height:34px; object-fit:contain; flex-shrink:0; }
.gbx-name { font-family:'GBDINBlack','Inter',sans-serif; font-size:15px; color:#fff; letter-spacing:.02em; white-space:nowrap; }
.gbx-back { margin-left:auto; font-family:'GBDIN','Inter',sans-serif; font-size:10px; color:rgba(255,255,255,.8); border:1px solid rgba(255,255,255,.4); border-radius:6px; padding:3px 8px; }
.gbx-carousel { position:relative; aspect-ratio:2.2/1; background:#fff; overflow:hidden; }
.gbx-carousel img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; transition:opacity .7s ease; }
.gbx-arrow { position:absolute; top:50%; transform:translateY(-50%); width:22px; height:22px; border-radius:50%; background:rgba(110,110,110,.7); color:#fff; font-size:15px; line-height:21px; text-align:center; z-index:1; }
.gbx-arrow.l { left:6px; } .gbx-arrow.r { right:6px; }
.gbx-dots { position:absolute; left:50%; bottom:6px; transform:translateX(-50%); display:flex; gap:4px; z-index:1; }
.gbx-dots i { width:6px; height:6px; border-radius:50%; background:rgba(150,150,150,.6); }
.gbx-dots i.on { background:#fff; box-shadow:0 0 0 1px rgba(0,0,0,.15); }
.gbx-body { padding:12px 12px 14px; }
.gbx-h { font-family:'GBDINBlack','Inter',sans-serif; font-size:12px; letter-spacing:.06em; text-transform:uppercase; margin:2px 2px 9px; }
.gbx-games { display:grid; grid-template-columns:repeat(3,1fr); gap:9px; }
.gbx-card { background:#fff; border-radius:8px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.07); }
.gbx-card img { display:block; width:100%; aspect-ratio:1/1; object-fit:cover; }
.gbx-card-t { font-family:'GBDINBlack','Inter',sans-serif; font-size:11px; color:#2d2a8e; padding:7px 8px 1px; }
.gbx-card-s { font-size:9.5px; line-height:1.35; padding:0 8px 9px; color:#222; }
.gbx-foot { display:flex; justify-content:space-between; align-items:center; padding:8px 14px; background:#fff; border-top:1px solid #e6e2dc; font-size:9.5px; color:#666; }
.gbx-socs { display:flex; gap:5px; }
.gbx-soc { width:17px; height:17px; border-radius:50%; border:1px solid #d6d1ca; display:flex; align-items:center; justify-content:center; }
.gbx-soc svg { fill:#666; display:block; }
/* game page */
.gbx-gp { background:#fff; padding:14px 16px 16px; }
.gbx-gp-title { font-family:'GBDINBlack','Inter',sans-serif; font-size:21px; letter-spacing:.02em; text-transform:uppercase; line-height:1.05; }
.gbx-gp-sub { font-size:11px; color:#555; margin-top:3px; }
.gbx-gp-row { display:grid; grid-template-columns:42% 1fr; gap:12px; margin-top:12px; }
.gbx-gp-img { width:100%; aspect-ratio:1/1; object-fit:cover; border-radius:8px; border:1px solid #e3e3e3; }
.gbx-thumbs { display:flex; gap:5px; margin-top:5px; }
.gbx-thumbs img { width:34px; height:34px; object-fit:cover; border-radius:4px; border:1px solid #e3e3e3; }
.gbx-thumbs img.on { border:1.5px solid #c8963e; }
.gbx-stats { display:grid; grid-template-columns:1fr 1fr; gap:6px; }
.gbx-stat { border:1px solid #e3e3e3; border-radius:7px; text-align:center; padding:6px 4px 5px; }
.gbx-stat b { display:block; font-family:'GBDINBlack','Inter',sans-serif; font-size:13px; font-weight:normal; }
.gbx-stat span { font-size:8px; letter-spacing:.08em; text-transform:uppercase; color:#777; }
.gbx-desc { border-left:2px solid #e3dcd2; padding-left:8px; margin-top:8px; font-size:9.5px; line-height:1.5; }
.gbx-des { font-size:9px; margin-top:7px; } .gbx-des b { font-family:'GBDINBlack','Inter',sans-serif; font-weight:normal; letter-spacing:.06em; color:#777; margin-right:6px; }
.gbx-btns { display:flex; gap:5px; margin-top:9px; flex-wrap:wrap; }
.gbx-btn { font-family:'GBDINBlack','Inter',sans-serif; font-size:9px; letter-spacing:.06em; text-transform:uppercase; border:1px solid #d6d6d6; border-radius:6px; padding:5px 9px; }
.gbx-btn.dark { background:#1a1c30; border-color:#1a1c30; color:#fff; }

/* product page mock (matches the live GameBoard game page) */
.gbp { background:#f6f5f2; }
.gbp-nav { background:#1a1a2e; color:#fff; display:flex; align-items:center; gap:7px; padding:9px 12px; }
.gbp-back { font-family:'GBDINBlack','Inter',sans-serif; font-size:9.5px; letter-spacing:.06em; text-transform:uppercase; color:rgba(255,255,255,.78); }
.gbp-sep { color:rgba(255,255,255,.25); font-size:11px; } .gbp-game { font-size:10.5px; opacity:.55; }
.gbp-navbtn { margin-left:auto; font-family:'GBDINBlack','Inter',sans-serif; font-size:8.5px; letter-spacing:.07em; text-transform:uppercase; border:1.5px solid rgba(255,255,255,.3); border-radius:4px; padding:3px 8px; }
.gbp-hero { position:relative; aspect-ratio:1.45/1; background-size:cover; background-position:center top; display:flex; align-items:flex-end; }
.gbp-hero::before { content:''; position:absolute; inset:0; background:linear-gradient(to top, rgba(10,10,28,.95) 0%, rgba(10,10,28,.6) 45%, rgba(10,10,28,.05) 100%); }
.gbp-hero-in { position:relative; padding:0 16px 16px; color:#fff; }
.gbp-title { font-family:'GBDINBlack','Inter',sans-serif; font-size:30px; line-height:1.04; letter-spacing:.02em; text-shadow:0 2px 14px rgba(0,0,0,.55); margin-bottom:5px; }
.gbp-sum { font-size:11.5px; color:rgba(255,255,255,.85); margin-bottom:8px; }
.gbp-stats { display:flex; flex-wrap:wrap; gap:3px 12px; font-size:10px; color:rgba(255,255,255,.8); }
.gbp-stats span, .gbp-cred { display:inline-flex; align-items:center; gap:4px; }
.gbp-stats svg, .gbp-cred svg { width:11px; height:11px; }
.gbp-cred { font-size:10px; color:rgba(255,255,255,.75); margin-top:4px; }
.gbp-ctas { display:flex; flex-wrap:wrap; align-items:center; gap:6px; margin-top:10px; }
.gbp-price { font-family:'GBDINBlack','Inter',sans-serif; font-size:15px; border:2px solid rgba(255,255,255,.35); border-radius:5px; padding:4px 8px 3px; }
.gbp-buy { font-family:'GBDINBlack','Inter',sans-serif; font-size:8.5px; letter-spacing:.07em; text-transform:uppercase; background:#fff; color:#1a3a5c; border:2px solid #fff; border-radius:5px; padding:7px 9px 6px; }
.gbp-ticker { background:#f5a623; color:#fff; text-align:center; padding:11px 14px; }
.gbp-q { font-family:'GBDINBlack','Inter',sans-serif; font-style:italic; font-size:12px; }
.gbp-a { font-size:8.5px; letter-spacing:.07em; text-transform:uppercase; color:rgba(255,255,255,.8); margin-top:3px; }
.gbp-main { padding:14px 16px 16px; }
.gbp-label { font-family:'GBDINBlack','Inter',sans-serif; font-size:8.5px; letter-spacing:.1em; text-transform:uppercase; color:#8a8a99; border-bottom:1px solid #e3e1dc; padding-bottom:4px; margin-bottom:7px; }
.gbp-desc { font-size:10.5px; line-height:1.6; color:#3d3d55; }
/* ── Brand editor animation ── */
/* add-a-game step */
.ie-addcard { transition:box-shadow .2s; }
.ie-cover-slot { position:relative; aspect-ratio:1/1; background:#ece8e2; }
.ie-cover-slot img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; opacity:0; transform:scale(.96); transition:opacity .4s, transform .4s; }
.ie-addcard.has-cover .ie-cover-slot img { opacity:1; transform:none; }
.ie-cover-ph { position:absolute; inset:6px; border:1.5px dashed #b9b1a6; border-radius:6px; display:flex; align-items:center; justify-content:center; font-family:'GBDINBlack','Inter',sans-serif; font-size:10px; color:#8a8073; letter-spacing:.03em; transition:opacity .25s, background .15s, border-color .15s; }
.ie-cover-ph.hover { border-color:#2d2a8e; color:#2d2a8e; background:rgba(45,42,142,.05); }
.ie-ph-cover { display:none; }
.ie-addcard.open .ie-ph-add { display:none; } .ie-addcard.open .ie-ph-cover { display:inline; }
.ie-addcard.has-cover .ie-cover-ph { opacity:0; }
.ie-addcard:not(.open) .gbx-card-t, .ie-addcard:not(.open) .gbx-card-s { visibility:hidden; }
.gbx-card .ie-edit { display:block; outline-offset:1px; min-height:1.35em; }
.gbx-card .gbx-card-s.ie-edit { min-height:2.7em; }
.gbx-card .ie-edit.hover { outline-color:rgba(45,42,142,.45); }
.gbx-card .ie-edit.editing { outline:1.5px solid #4caa4b; }
.gbx-card .ie-edit.editing::after { background:#4caa4b; }
.ie-gname:empty::before { content:'Game name'; color:#b8b3c8; }
.ie-gsum:empty::before { content:'One-line summary'; color:#bbb; }
.ie-gname.editing:empty::before, .ie-gsum.editing:empty::before { content:''; }
.ie-stage { position:relative; overflow:hidden; user-select:none; }
.ie-logo-slot { position:relative; width:34px; height:34px; flex-shrink:0; }
.ie-logo-slot .gbx-logo { position:absolute; inset:0; opacity:0; transform:scale(.6); transition:opacity .35s, transform .35s cubic-bezier(.3,1.6,.5,1); }
.ie-logo-slot.has .gbx-logo { opacity:1; transform:none; }
.ie-logo-ph { position:absolute; inset:0; border:1.5px dashed rgba(255,255,255,.55); border-radius:8px; color:rgba(255,255,255,.8); font-size:8px; line-height:1.1; display:flex; align-items:center; justify-content:center; text-align:center; transition:opacity .25s, background .15s, border-color .15s; }
.ie-logo-slot.has .ie-logo-ph { opacity:0; }
.ie-logo-ph.hover { border-color:#fff; background:rgba(255,255,255,.15); }
.ie-edit { position:relative; border-radius:4px; outline:1.5px dashed transparent; outline-offset:3px; transition:outline-color .15s; min-height:1.1em; }
.ie-edit.hover   { outline-color:rgba(255,255,255,.7); }
.ie-edit.editing { outline:1.5px solid #9be29a; }
.ie-edit.editing::after { content:''; display:inline-block; width:1.5px; height:1em; background:#9be29a; margin-left:1px; vertical-align:-.12em; animation:ie-blink 1s steps(1) infinite; }
.ie-edit.selected { background:rgba(155,226,154,.35); }
@keyframes ie-blink { 50% { opacity:0; } }
.ie-paint { margin-left:auto; width:28px; height:28px; border-radius:7px; border:1.5px dashed rgba(255,255,255,.45); display:flex; align-items:center; justify-content:center; font-size:13px; flex-shrink:0; transition:border-color .15s, background .15s; }
.ie-paint.hover { border-color:#fff; background:rgba(255,255,255,.15); }
.ie-pop { position:absolute; z-index:3; background:#fff; border-radius:9px; box-shadow:0 8px 24px rgba(0,0,0,.25); opacity:0; transform:translateY(-4px) scale(.97); transition:opacity .18s, transform .18s; pointer-events:none; }
.ie-pop.show { opacity:1; transform:none; }
.ie-colors { right:10px; top:50px; padding:7px; display:flex; gap:6px; }
.ie-colors span { width:20px; height:20px; border-radius:50%; display:block; box-shadow:inset 0 0 0 1px rgba(0,0,0,.1); transition:transform .15s; }
.ie-colors span.pick { transform:scale(1.25); box-shadow:0 0 0 2px #fff, 0 0 0 4px #4caa4b; }
.ie-mode { background:#fdf3e3; color:#a35f12; font-family:'Inter',system-ui,sans-serif; font-size:10.5px; font-weight:600; text-align:center; padding:5px; transition:background .25s; }
.ie-mode.step { background:#fde9c4; }
.ie-replace { position:absolute; left:50%; top:50%; transform:translate(-50%,-50%); z-index:2; background:rgba(17,17,17,.78); color:#fff; font-family:'Inter',system-ui,sans-serif; font-size:10.5px; font-weight:600; padding:6px 11px; border-radius:999px; opacity:0; transition:opacity .2s; white-space:nowrap; }
.ie-carousel.hover .ie-replace { opacity:1; }
.ie-carousel.hover::after { content:''; position:absolute; inset:0; box-shadow:inset 0 0 0 2px #4caa4b; z-index:2; }
.ie-file { position:absolute; z-index:6; display:flex; align-items:center; gap:6px; background:#fff; border-radius:8px; padding:5px 9px 5px 5px; box-shadow:0 8px 22px rgba(0,0,0,.28); font-family:'Inter',system-ui,sans-serif; font-size:10px; color:#1e293b; opacity:0; transform:translateY(10px) scale(.95); transition:opacity .25s, transform .35s ease, left .6s ease, top .6s ease; pointer-events:none; white-space:nowrap; }
.ie-file img { width:26px; height:26px; object-fit:cover; border-radius:4px; background:#eee; }
.ie-file.show { opacity:1; transform:none; }
.ie-toast { position:absolute; left:50%; bottom:46px; transform:translate(-50%,8px); background:#15803d; color:#fff; font-family:'Inter',system-ui,sans-serif; font-size:11px; font-weight:600; padding:6px 12px; border-radius:999px; box-shadow:0 6px 18px rgba(21,128,61,.35); opacity:0; transition:opacity .2s, transform .2s; white-space:nowrap; z-index:5; }
.ie-toast.show { opacity:1; transform:translate(-50%,0); }
.ie-cursor { position:absolute; left:0; top:0; z-index:7; transform:translate(300px,330px); filter:drop-shadow(0 2px 3px rgba(0,0,0,.3)); pointer-events:none; }
.ie-cursor.down { animation:ie-click .18s ease; }
@keyframes ie-click { 50% { scale:.82; } }
@media (prefers-reduced-motion: reduce) { .ie-cursor { display:none; } }
/* Product page editing demo */
.gp-hero { overflow:hidden; }
.gp-bg { position:absolute; inset:0; background-size:cover; background-position:center top; opacity:0; transition:opacity .6s; }
.gp-hero::before { z-index:1; }
.gp-hero .gbp-hero-in { z-index:2; }
.gp-hero .ie-replace { z-index:3; top:32%; }
.gp-hero.hover .ie-replace { opacity:1; }
.gp-hero.hover::after { content:''; position:absolute; inset:0; box-shadow:inset 0 0 0 2px #4caa4b; z-index:3; }
#gpDemo em { font-style:normal; }
#gpDemo .gbp-buy.ie-edit.hover { outline-color:#9be29a; }
.gbp-ticker .ie-edit { display:inline-block; }
.gbp-ticker .gbp-a { display:block; width:max-content; margin-left:auto; margin-right:auto; }
.gp-linkpop { width:200px; padding:9px 10px 10px; font-family:'Inter',system-ui,sans-serif; }
.gp-ll { font-size:8.5px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.05em; margin:0 0 3px; }
.gp-f { font-size:10.5px; color:#1e293b; border:1px solid #cbd5e1; border-radius:5px; padding:5px 7px; margin-bottom:7px; min-height:24px; white-space:nowrap; overflow:hidden; }
.gp-f.editing { border-color:#4caa4b; box-shadow:0 0 0 2px rgba(76,170,75,.18); }
.gp-f.editing::after { content:''; display:inline-block; width:1.5px; height:1em; background:#4caa4b; margin-left:1px; vertical-align:-.12em; animation:ie-blink 1s steps(1) infinite; }
.gp-f.selected { background:rgba(76,170,75,.18); }
.gp-save { display:inline-block; background:#4caa4b; color:#fff; font-size:10px; font-weight:600; border-radius:5px; padding:4px 11px; transition:background .15s; }
.gp-save.hover { background:#3d8f3c; }
</style>
</head>
<body>

<nav>
  <a class="nav-logo" href="gameboard">
    <img src="images/gb_icon_180.png" alt="GameBoard" />
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
  <div class="hero-tag">For board game makers</div>
  <h1>Your games. A <em>polished page</em> — instantly.</h1>
  <p>GameBoard turns your Google Sheet into a beautiful product catalog and individual game pages — no web developer, no web hosting.</p>
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
  <span class="stage-pill stage-catalog">Game catalog</span>
  <span class="stage-arrow">→</span>
  <span class="stage-pill stage-share">Share</span>
</div>

<div class="live-demo-section">
  <div class="live-demo-inner">
    <div class="live-demo-text">
      <div class="section-label">Live example</div>
      <h2 class="section-title">See a real GameBoard</h2>
      <p>UP Games — independent publisher of <em style="color:rgba(255,255,255,.8);font-style:normal;">Floristry</em>, <em style="color:rgba(255,255,255,.8);font-style:normal;">Scurry Up!</em>, and <em style="color:rgba(255,255,255,.8);font-style:normal;">Pressure</em> — uses GameBoard to showcase their catalog. Click through to browse their live pages.</p>
      <div class="live-demo-links">
        <a href="https://zapsheets.com/app/gameboard/a9e4ef63bb33371151169a6e/" target="_blank" class="live-link featured">
          <div class="live-link-info">
            <img class="live-link-logo" src="https://zapsheets.com/app/images/1Aa9L2PX4uteoJNy4kyS3QPwWhn9plAPkc-1y4XUQDmQ/68db6d2fe1c4ff0767a9.png" alt="UP Games" />
            <div>
              <div class="live-link-name">UP Games — Full catalog</div>
              <div class="live-link-desc">zapsheets.com/app/gameboard/…</div>
            </div>
          </div>
          <span class="live-link-arrow">↗</span>
        </a>
        <a href="https://zapsheets.com/app/gameboard/a9e4ef63bb33371151169a6e/floristry" target="_blank" class="live-link">
          <div class="live-link-info">
            <img class="live-link-logo" src="https://zapsheets.com/app/images/1Aa9L2PX4uteoJNy4kyS3QPwWhn9plAPkc-1y4XUQDmQ/e25ee32f4d226062e11c.webp" alt="Floristry" style="object-fit:cover;" />
            <div>
              <div class="live-link-name">Floristry — Game page</div>
              <div class="live-link-desc">Buy links · press quotes · gallery · FAQ</div>
            </div>
          </div>
          <span class="live-link-arrow">↗</span>
        </a>
      </div>
    </div>
    <div class="demo-browser">
      <div class="demo-chrome">
        <div class="demo-dots"><span class="d1"></span><span class="d2"></span><span class="d3"></span></div>
        <div class="demo-url">zapsheets.com/app/gameboard/a9e4ef63bb33371151169a6e/</div>
      </div>
      <div class="demo-body" style="background:#f2f0ed">
        <div class="gbx">
          <div class="gbx-banner"><img class="gbx-logo" src="images/gameboard-features/up-logo.webp" alt="UP Games"><span class="gbx-name">UP Games</span></div>
          <div class="gbx-carousel"><img src="images/gameboard-features/carousel-spread.webp" alt="Scurry Up! game spread" loading="lazy"><span class="gbx-arrow l">‹</span><span class="gbx-arrow r">›</span><div class="gbx-dots"><i class="on"></i><i></i><i></i></div></div>
          <div class="gbx-body">
            <div class="gbx-h">Our Games</div>
            <div class="gbx-games">
              <div class="gbx-card"><img src="images/gameboard-features/cover-floristry.webp" alt="Floristry" loading="lazy"><div class="gbx-card-t">Floristry</div><div class="gbx-card-s">A quick, real time, Dutch auction game for two players.</div></div>
              <div class="gbx-card"><img src="images/gameboard-features/cover-scurry.webp" alt="Scurry Up!" loading="lazy"><div class="gbx-card-t">Scurry Up!</div><div class="gbx-card-s">Take on the role of a squirrel racing for nuts and berries!</div></div>
              <div class="gbx-card"><img src="images/gameboard-features/cover-pressure.webp" alt="Pressure" loading="lazy"><div class="gbx-card-t">Pressure</div><div class="gbx-card-s">Will you dive the deepest or crack under the pressure!</div></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<section id="features">
  <div class="container">
    <div class="section-label">Features</div>
    <h2 class="section-title">Everything you need to showcase your games</h2>
    <p class="section-sub">From a branded catalog to individual game pages with buy links — GameBoard covers the full product presence without a complicated CMS.</p>
    <div class="features-grid">
      <div class="feat-card" style="grid-column:1/-1">
        <div class="feat-icon green">🌐</div>
        <h3>No website to host</h3>
        <p>You don't need to build, host or maintain your own website. GameBoard gives you a live catalog and game pages at a shareable link — no hosting plan, no web server, no WordPress updates. A typical GoDaddy site runs $10–35 a month for hosting, and you still need to buy your own domain; with GameBoard there's nothing to host.</p>
      </div>
      <div class="feat-card">
        <div class="feat-icon green">🗂️</div>
        <h3>Game catalog</h3>
        <p>A beautiful, filterable catalog page showing all your games — with cover images, player count, duration, and a summary. Visitors can browse and click through to each game.</p>
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
        <p>GameBoard reads directly from your Google Sheet — the same one PitchBoard uses. You edit the page directly as you see it.</p>
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
        <p>Your catalog opens with a branded hero banner and a grid of every game. Each card shows the cover, name, player count, play time, and a one-line summary.</p>
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
          <div class="browser-url">zapsheets.com/app/gameboard/…</div>
        </div>
        <div class="gbx">
          <div class="gbx-banner"><img class="gbx-logo" src="images/gameboard-features/up-logo.webp" alt="UP Games"><span class="gbx-name">UP Games</span></div>
          <div class="gbx-carousel"><img src="images/gameboard-features/carousel-pieces.webp" alt="Scurry Up! pieces" loading="lazy"><span class="gbx-arrow l">‹</span><span class="gbx-arrow r">›</span><div class="gbx-dots"><i></i><i class="on"></i><i></i></div></div>
          <div class="gbx-body">
            <div class="gbx-h">Our Games</div>
            <div class="gbx-games">
              <div class="gbx-card"><img src="images/gameboard-features/cover-floristry.webp" alt="Floristry" loading="lazy"><div class="gbx-card-t">Floristry</div><div class="gbx-card-s">A quick, real time, Dutch auction game for two players.</div></div>
              <div class="gbx-card"><img src="images/gameboard-features/cover-scurry.webp" alt="Scurry Up!" loading="lazy"><div class="gbx-card-t">Scurry Up!</div><div class="gbx-card-s">Take on the role of a squirrel racing for nuts and berries!</div></div>
              <div class="gbx-card"><img src="images/gameboard-features/cover-pressure.webp" alt="Pressure" loading="lazy"><div class="gbx-card-t">Pressure</div><div class="gbx-card-s">Will you dive the deepest or crack under the pressure!</div></div>
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
          <li>Full-width hero image with the title, stats and buy buttons</li>
          <li>Full description, player count, and play time</li>
          <li>Price and Buy Now button for direct sales</li>
          <li>Sell sheet, rules PDF, and page URL buttons</li>
          <li>Inline image editing for the hero and cover</li>
        </ul>
      </div>
      <div class="browser-frame">
        <div class="browser-chrome">
          <div class="browser-dots"><span class="d1"></span><span class="d2"></span><span class="d3"></span></div>
          <div class="browser-url">zapsheets.com/app/gameboard/…/floristry</div>
        </div>
        <div class="gbx gbp ie-stage" id="gpDemo" aria-hidden="true">
          <div class="gbp-nav"><span class="gbp-back">‹ All Games</span><span class="gbp-sep">|</span><span class="gbp-game">Floristry</span><span class="gbp-navbtn">Rules</span></div>
          <div class="ie-mode">✎ Editing — click anything to change it</div>
          <div class="gbp-hero gp-hero" style="background-image:url('images/gameboard-features/hero-floristry.webp')">
            <div class="gp-bg" style="background-image:url('images/gameboard-features/floristry-board.webp')"></div>
            <div class="ie-replace">⟳ Replace image</div>
            <div class="gbp-hero-in">
              <div class="gbp-title">Floristry</div>
              <div class="gbp-sum">A quick, real time, Dutch auction game for two players.</div>
              <div class="gbp-stats"><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>2 players</span><span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><em class="ie-edit gp-time">20 min</em></span><em class="ie-edit gp-genre">Family, Casual</em></div>
              <div class="gbp-cred"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>David Gordon, TAM</div>
              <div class="gbp-ctas"><span class="gbp-price">$20</span><span class="gbp-buy">Buy on Amazon</span><span class="gbp-buy ie-edit gp-buy">Buy at Barnes &amp; Noble</span></div>
            </div>
          </div>
          <div class="gbp-ticker"><div class="gbp-q ie-edit gp-q">“This game is just delightful.”</div><div class="gbp-a ie-edit gp-a">— Shut Up &amp; Sit Down</div></div>
          <div class="gbp-main"><div class="gbp-label">About the Game</div><div class="gbp-desc">Players compete at the historic Aalsmeer Dutch Auction to win the best flowers and then use them to create the most charming window display.</div></div>
          <div class="ie-pop gp-linkpop"><div class="gp-ll">Button text</div><div class="gp-f gp-ltext">Buy at Barnes &amp; Noble</div><div class="gp-ll">Link</div><div class="gp-f gp-lurl">barnesandnoble.com/w/floristry</div><div class="gp-save">Save</div></div>
          <div class="ie-file"><img alt=""><span></span></div>
          <div class="ie-toast">✓ Saved to your Sheet</div>
          <svg class="ie-cursor" width="22" height="22" viewBox="0 0 24 24"><path d="M4 2l15 11-7 1.2L8.6 21 4 2z" fill="#fff" stroke="#111" stroke-width="1.5" stroke-linejoin="round"/></svg>
        </div>
      </div>
    </div>

    <!-- Screenshot 3: Brand editor -->
    <div class="shot-grid">
      <div class="shot-text">
        <div class="section-label">Brand editor</div>
        <h2 class="section-title" style="margin-bottom:1rem;">Edit everything right on the page</h2>
        <p>Sign in and your catalog becomes editable. Drop in your logo, click the company name and type, pick your brand color, add a game with its cover, click any image to replace it — right on the live page, with no settings screens or forms. Every change saves to your Sheet.</p>
        <ul>
          <li>Click-to-edit company name, tagline, and about text</li>
          <li>Upload logo and banner images in-place</li>
          <li>Color picker for the banner accent color</li>
          <li>Social icons light up when a link is set</li>
          <li>All changes sync back to the Settings tab in your Sheet</li>
        </ul>
      </div>
      <div class="browser-frame ie-frame" aria-label="Animated demo: setting up and editing a GameBoard catalog directly on the page">
        <div class="browser-chrome">
          <div class="browser-dots"><span class="d1"></span><span class="d2"></span><span class="d3"></span></div>
          <div class="browser-url">zapsheets.com/app/gameboard/…</div>
        </div>
        <div class="gbx ie-stage" id="ieDemo" aria-hidden="true">
          <div class="gbx-banner ie-banner">
            <div class="ie-logo-slot"><div class="ie-logo-ph">+ Logo</div><img class="gbx-logo" src="images/gameboard-features/up-logo.webp" alt=""></div>
            <span class="gbx-name ie-edit ie-name">Your Company</span>
            <div class="ie-paint">🎨</div>
            <div class="ie-pop ie-colors"><span style="background:#2b3a55"></span><span style="background:#7a2e3b"></span><span class="ie-purple" style="background:#693f99"></span><span style="background:#13756f"></span><span style="background:#a35f12"></span></div>
          </div>
          <div class="ie-mode">✎ Editing — click anything to change it</div>
          <div class="gbx-carousel ie-carousel">
            <img class="ie-img-a" src="images/gameboard-features/carousel-spread.webp" alt="">
            <img class="ie-img-b" src="images/gameboard-features/carousel-pieces.webp" alt="" style="opacity:0">
            <span class="gbx-arrow l">‹</span><span class="gbx-arrow r">›</span><div class="gbx-dots"><i class="on"></i><i></i><i></i></div>
            <div class="ie-replace">⟳ Replace image</div>
          </div>
          <div class="gbx-body">
            <div class="gbx-h">Our Games</div>
            <div class="gbx-games">
              <div class="gbx-card"><img src="images/gameboard-features/cover-floristry.webp" alt="Floristry" loading="lazy"><div class="gbx-card-t">Floristry</div><div class="gbx-card-s">A quick, real time, Dutch auction game for two players.</div></div>
              <div class="gbx-card"><img src="images/gameboard-features/cover-scurry.webp" alt="Scurry Up!" loading="lazy"><div class="gbx-card-t">Scurry Up!</div><div class="gbx-card-s">Take on the role of a squirrel racing for nuts and berries!</div></div>
              <div class="gbx-card ie-addcard"><div class="ie-cover-slot"><img src="images/gameboard-features/cover-pressure.webp" alt=""><div class="ie-cover-ph"><span class="ie-ph-add">+ Add a game</span><span class="ie-ph-cover">+ Cover image</span></div></div><div class="gbx-card-t ie-edit ie-gname"></div><div class="gbx-card-s ie-edit ie-gsum"></div></div>
            </div>
          </div>
          <div class="gbx-foot"><span class="gbx-socs"><span class="gbx-soc" title="Instagram"><svg width="9" height="9" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></span><span class="gbx-soc" title="X"><svg width="9" height="9" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.73-8.835L1.254 2.25H8.08l4.253 5.622L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></span><span class="gbx-soc" title="Facebook"><svg width="9" height="9" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></span></span><span>© 2026 <span class="ie-copyname">Your Company</span></span></div>
          <div class="ie-file"><img alt=""><span></span></div>
          <div class="ie-toast">✓ Saved to your Sheet</div>
          <svg class="ie-cursor" width="22" height="22" viewBox="0 0 24 24"><path d="M4 2l15 11-7 1.2L8.6 21 4 2z" fill="#fff" stroke="#111" stroke-width="1.5" stroke-linejoin="round"/></svg>
        </div>
      </div>
    </div>

  </div>
</section>

<section class="ownership-section">
  <div class="container">
    <div class="section-label">Data ownership</div>
    <h2 class="section-title">Your catalog lives in your Google Sheet</h2>
    <p class="section-sub">No CMS, no vendor lock-in. GameBoard reads from a Google Sheet you own — every game, image URL, and brand setting, in a spreadsheet you control.</p>
    <div class="own-grid">
      <div class="own-card">
        <div class="num">$4.99</div>
        <p>Per month, with no web hosting to pay for — GameBoard runs on Google infrastructure you already have. A typical GoDaddy site runs $10–35 a month for hosting, and you still need to buy your own domain.</p>
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
    <p class="section-sub">GameBoard gives you a polished product presence without Squarespace's learning curve, a GoDaddy hosting bill, or a developer's retainer.</p>
    <div class="compare-wrap">
      <table class="compare-table">
        <thead>
          <tr>
            <th>Feature</th>
            <th class="ours">GameBoard</th>
            <th>Squarespace / Wix</th>
            <th>GoDaddy</th>
            <th>BGG Publisher Page</th>
          </tr>
        </thead>
        <tbody>
          <tr><td>Web hosting included</td><td class="ours"><span class="check">✓</span></td><td><span class="check">✓</span></td><td><span class="check">✓</span></td><td><span class="check">✓</span></td></tr>
          <tr><td>Game catalog page</td><td class="ours"><span class="check">✓</span></td><td><span class="check">✓</span></td><td><span class="check">✓</span></td><td><span class="check">✓</span></td></tr>
          <tr><td>Per-game detail pages</td><td class="ours"><span class="check">✓</span></td><td><span class="check">✓</span></td><td><span class="check">✓</span></td><td><span class="check">✓</span></td></tr>
          <tr><td>Inline editing (no CMS)</td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Powered by your Google Sheet</td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Works with <a href="https://zapsheets.com/app/pitchboard/features" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline;text-underline-offset:2px">PitchBoard</a></td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Shareable token links</td><td class="ours"><span class="check">✓</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Price &amp; buy links</td><td class="ours"><span class="check">✓</span></td><td><span class="check">✓</span></td><td><span class="check">✓</span></td><td><span class="cross">—</span></td></tr>
          <tr><td>Monthly cost</td><td class="ours" style="font-weight:600;color:var(--dark);">$4.99 / mo</td><td>$16–49 / mo</td><td>$10–35 / mo</td><td>Free</td></tr>
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
  <a href="gameboard">GameBoard</a> &mdash; built for board game makers &nbsp;·&nbsp; <a href="/">ZapSheets</a>
</footer>

<script>
// ── Brand editor animation: a cursor sets up the UP Games catalog in place, then loops ──
(function() {
  var stage = document.getElementById('ieDemo');
  if (!stage) return;
  var q = function(s) { return stage.querySelector(s); };
  var cursor = q('.ie-cursor'), toast = q('.ie-toast'), mode = q('.ie-mode'), modeDefault = mode.textContent;
  var banner = q('.ie-banner'), logoSlot = q('.ie-logo-slot'), logoPh = q('.ie-logo-ph');
  var name = q('.ie-name'), copyName = q('.ie-copyname');
  var paint = q('.ie-paint'), colors = q('.ie-colors'), purple = q('.ie-purple');
  var car = q('.ie-carousel'), imgA = q('.ie-img-a'), imgB = q('.ie-img-b');
  var file = q('.ie-file'), fileImg = file.querySelector('img'), fileName = file.querySelector('span');
  var addCard = q('.ie-addcard'), coverPh = q('.ie-cover-ph'), gName = q('.ie-gname'), gSum = q('.ie-gsum');
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var visible = false;
  var sleep = function(ms) { return new Promise(function(r) { setTimeout(r, ms); }); };

  function at(el, fx, fy) {
    var s = stage.getBoundingClientRect(), r = el.getBoundingClientRect();
    return [r.left - s.left + r.width * (fx == null ? .5 : fx), r.top - s.top + r.height * (fy == null ? .5 : fy)];
  }
  function moveTo(el, fx, fy, ms) {
    ms = ms || 750;
    var p = at(el, fx, fy);
    cursor.style.transition = 'transform ' + ms + 'ms cubic-bezier(.45,.05,.25,1)';
    cursor.style.transform = 'translate(' + (p[0] - 3) + 'px,' + (p[1] - 2) + 'px)';
    return sleep(ms + 60);
  }
  function click() { cursor.classList.remove('down'); void cursor.offsetWidth; cursor.classList.add('down'); return sleep(220); }
  async function type(el, text) { for (var i = 0; i < text.length; i++) { el.textContent += text[i]; await sleep(60 + Math.random() * 45); } }
  function say(t) { mode.textContent = t ? '✎ ' + t : modeDefault; mode.classList.toggle('step', !!t); }
  async function saved() { toast.classList.add('show'); await sleep(1250); toast.classList.remove('show'); }
  // "Upload": a file card appears near the target, then drops into it
  async function upload(target, src, label) {
    var p = at(target, .5, .5);
    fileImg.src = src; fileName.textContent = label;
    file.style.transition = 'none';
    file.style.left = Math.max(6, Math.min(stage.clientWidth - 190, p[0] + 30)) + 'px';
    file.style.top = (p[1] + 34) + 'px';
    void file.offsetWidth; file.style.transition = '';
    file.classList.add('show'); await sleep(900);
    file.style.left = Math.max(6, p[0] - 40) + 'px'; file.style.top = (p[1] - 14) + 'px';
    await sleep(450); file.classList.remove('show'); await sleep(150);
  }

  function reset() {
    banner.style.setProperty('--gbx-brand', '#2b3a55');
    logoSlot.classList.remove('has'); logoPh.classList.remove('hover');
    name.textContent = 'Your Company'; copyName.textContent = 'Your Company';
    name.className = name.className.replace(/\s*(hover|editing|selected)/g, '');
    paint.classList.remove('hover'); purple.classList.remove('pick'); colors.classList.remove('show');
    car.classList.remove('hover'); imgA.style.opacity = 1; imgB.style.opacity = 0;
    file.classList.remove('show'); say('');
    addCard.classList.remove('open', 'has-cover'); coverPh.classList.remove('hover');
    [gName, gSum].forEach(function(e) { e.textContent = ''; e.className = e.className.replace(/\s*(hover|editing|selected)/g, ''); });
  }

  async function play() {
    reset();
    await sleep(900);
    // 1. Logo
    say('Click the logo spot to upload your logo');
    await moveTo(logoPh); logoPh.classList.add('hover'); await sleep(350); await click();
    await upload(logoPh, imgSrc('up-logo.webp'), 'UP-Games-Logo.png');
    logoSlot.classList.add('has'); logoPh.classList.remove('hover');
    await saved();
    // 2. Company name
    say('Click the name and type');
    await moveTo(name, .4, .55); name.classList.add('hover'); await sleep(400); await click();
    name.classList.remove('hover'); name.classList.add('editing', 'selected'); await sleep(450);
    name.classList.remove('selected'); name.textContent = '';
    await type(name, 'UP Games'); copyName.textContent = 'UP Games';
    await sleep(350);
    say('Click away — it’s saved');
    await moveTo(q('.gbx-h'), .5, .5, 600); await click(); name.classList.remove('editing');
    await saved();
    // 3. Banner color
    say('Pick your brand color');
    await moveTo(paint); paint.classList.add('hover'); await sleep(300); await click();
    colors.classList.add('show'); await sleep(550);
    await moveTo(purple, .5, .5, 500); purple.classList.add('pick'); await click();
    banner.style.setProperty('--gbx-brand', '#693f99'); paint.classList.remove('hover');
    await sleep(450); colors.classList.remove('show');
    await saved();
    // 4. Add a new game
    say('Add a game right on the page');
    await moveTo(coverPh, .5, .5, 800); coverPh.classList.add('hover'); await sleep(350); await click();
    coverPh.classList.remove('hover'); addCard.classList.add('open');
    gName.classList.add('editing'); await sleep(350);
    await type(gName, 'Pressure'); await sleep(250);
    say('Drop in the cover');
    await moveTo(coverPh, .5, .5, 450); coverPh.classList.add('hover'); await sleep(250); await click();
    gName.classList.remove('editing');
    await upload(coverPh, imgSrc('cover-pressure.webp'), 'Pressure-Cover.png');
    coverPh.classList.remove('hover'); addCard.classList.add('has-cover'); await sleep(350);
    say('Add a one-line summary');
    await moveTo(gSum, .3, .3, 450); await click(); gSum.classList.add('editing');
    await type(gSum, 'Will you dive the deepest or crack under the pressure!');
    await sleep(300); await moveTo(q('.gbx-h'), .6, .5, 600); await click(); gSum.classList.remove('editing');
    await saved();
    // 5. Replace the carousel image
    say('Click any image to replace it');
    await moveTo(car, .5, .45); car.classList.add('hover'); await sleep(550); await click();
    await upload(car, imgSrc('carousel-pieces.webp'), 'scurry-up-pieces.jpg');
    car.classList.remove('hover'); imgB.style.opacity = 1; imgA.style.opacity = 0;
    await saved();
    say('That’s it — no settings screens');
    await moveTo(q('.gbx-games'), .5, .4, 900);
    await sleep(2800);
  }
  function imgSrc(f) { var base = imgA.getAttribute('src').replace(/[^/]*$/, ''); return base + f; }

  async function loop() { for (;;) { if (!visible) { await sleep(400); continue; } await play(); } }

  if (reduce) { // finished result, no motion
    banner.style.setProperty('--gbx-brand', '#693f99'); logoSlot.classList.add('has');
    name.textContent = copyName.textContent = 'UP Games';
    imgB.style.opacity = 1; imgA.style.opacity = 0;
    addCard.classList.add('open', 'has-cover'); gName.textContent = 'Pressure'; gSum.textContent = 'Will you dive the deepest or crack under the pressure!';
    return;
  }
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function(es) { visible = es[0].isIntersecting; }, { threshold: .35 }).observe(stage);
  } else { visible = true; }
  loop();
})();

// ── Product page animation: replace the hero, edit stats, a buy link and the review ──
(function() {
  var stage = document.getElementById('gpDemo');
  if (!stage) return;
  var q = function(s) { return stage.querySelector(s); };
  var cursor = q('.ie-cursor'), toast = q('.ie-toast'), mode = q('.ie-mode'), modeDefault = mode.textContent;
  var hero = q('.gp-hero'), bg = q('.gp-bg'), time = q('.gp-time'), genre = q('.gp-genre'), buy = q('.gp-buy');
  var pop = q('.gp-linkpop'), lText = q('.gp-ltext'), lUrl = q('.gp-lurl'), save = q('.gp-save');
  var quote = q('.gp-q'), author = q('.gp-a'), label = q('.gbp-label');
  var file = q('.ie-file'), fileImg = file.querySelector('img'), fileName = file.querySelector('span');
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var visible = false;
  var sleep = function(ms) { return new Promise(function(r) { setTimeout(r, ms); }); };
  var ORIG = { time: '20 min', genre: 'Family, Casual', buy: 'Buy at Barnes & Noble', url: 'barnesandnoble.com/w/floristry', q: '“This game is just delightful.”', a: '— Shut Up & Sit Down' };
  var NEW  = { time: '20–30 min', genre: 'Family, Casual, Auction', buy: 'Buy direct', url: 'upgames.com/shop/floristry', q: '“Quick to learn, and the bidding is so tense!”', a: '— Club K9 playtest group' };

  function at(el, fx, fy) {
    var s = stage.getBoundingClientRect(), r = el.getBoundingClientRect();
    return [r.left - s.left + r.width * (fx == null ? .5 : fx), r.top - s.top + r.height * (fy == null ? .5 : fy)];
  }
  function moveTo(el, fx, fy, ms) {
    ms = ms || 750; var p = at(el, fx, fy);
    cursor.style.transition = 'transform ' + ms + 'ms cubic-bezier(.45,.05,.25,1)';
    cursor.style.transform = 'translate(' + (p[0] - 3) + 'px,' + (p[1] - 2) + 'px)';
    return sleep(ms + 60);
  }
  function click() { cursor.classList.remove('down'); void cursor.offsetWidth; cursor.classList.add('down'); return sleep(220); }
  async function type(el, text) { for (var i = 0; i < text.length; i++) { el.textContent += text[i]; await sleep(55 + Math.random() * 40); } }
  function say(t) { mode.textContent = t ? '✎ ' + t : modeDefault; mode.classList.toggle('step', !!t); }
  async function saved() { toast.classList.add('show'); await sleep(1150); toast.classList.remove('show'); }
  async function upload(target, src, name) {
    var p = at(target, .5, .32);
    fileImg.src = src; fileName.textContent = name;
    file.style.transition = 'none';
    file.style.left = Math.max(6, Math.min(stage.clientWidth - 190, p[0] + 30)) + 'px';
    file.style.top = (p[1] + 34) + 'px';
    void file.offsetWidth; file.style.transition = '';
    file.classList.add('show'); await sleep(900);
    file.style.left = Math.max(6, p[0] - 40) + 'px'; file.style.top = (p[1] - 14) + 'px';
    await sleep(450); file.classList.remove('show'); await sleep(150);
  }
  function clean(el) { el.className = el.className.replace(/\s*(hover|editing|selected)/g, ''); }
  // click a text, select it, retype it
  async function retype(el, text, fx) {
    await moveTo(el, fx == null ? .5 : fx, .55, 650); el.classList.add('hover'); await sleep(300); await click();
    el.classList.remove('hover'); el.classList.add('editing', 'selected'); await sleep(400);
    el.classList.remove('selected'); el.textContent = ''; await type(el, text); await sleep(250);
  }
  async function done(el) { await moveTo(label, .5, .5, 550); await click(); clean(el); await saved(); }

  function reset() {
    bg.style.opacity = 0; hero.classList.remove('hover'); file.classList.remove('show'); pop.classList.remove('show');
    time.textContent = ORIG.time; genre.textContent = ORIG.genre; buy.textContent = ORIG.buy;
    lText.textContent = ORIG.buy; lUrl.textContent = ORIG.url; quote.textContent = ORIG.q; author.textContent = ORIG.a;
    [time, genre, buy, quote, author, lText, lUrl, save].forEach(clean); say('');
  }

  async function play() {
    reset(); await sleep(900);
    // 1. Hero image
    say('Click the hero image to replace it');
    await moveTo(hero, .5, .3); hero.classList.add('hover'); await sleep(550); await click();
    await upload(hero, imgSrc('floristry-board.webp'), 'floristry-table.jpg');
    hero.classList.remove('hover'); bg.style.opacity = 1;
    await saved();
    // 2. Stats
    say('Click a stat to edit it');
    await retype(time, NEW.time, .6);
    await moveTo(genre, .98, .55, 500); await click(); clean(time); genre.classList.add('editing');
    await sleep(300); await type(genre, ', Auction'); await sleep(250);
    await done(genre); clean(time);
    // 3. Buy link
    say('Change a buy button and its link');
    await moveTo(buy, .5, .5); buy.classList.add('hover'); await sleep(350); await click(); buy.classList.remove('hover');
    var p = at(buy, 0, 0), sw = stage.clientWidth;
    pop.style.left = Math.max(8, Math.min(sw - 210, p[0] - 30)) + 'px'; pop.style.top = Math.max(8, p[1] - 158) + 'px';
    pop.classList.add('show'); await sleep(450);
    await retype(lText, NEW.buy, .5); clean(lText); buy.textContent = NEW.buy;
    await retype(lUrl, NEW.url, .5); clean(lUrl);
    await moveTo(save, .5, .5, 450); save.classList.add('hover'); await click(); await sleep(150);
    pop.classList.remove('show'); clean(save);
    await saved();
    // 4. Review
    say('Swap in a new review');
    await retype(quote, NEW.q, .5); clean(quote);
    await retype(author, NEW.a, .5);
    await done(author); clean(quote);
    say('Every change saves to your Sheet');
    await moveTo(label, .2, .5, 900);
    await sleep(2800);
  }
  function imgSrc(f) { return 'images/gameboard-features/' + f; }
  async function loop() { for (;;) { if (!visible) { await sleep(400); continue; } await play(); } }

  if (reduce) {
    bg.style.opacity = 1; time.textContent = NEW.time; genre.textContent = NEW.genre; buy.textContent = NEW.buy;
    quote.textContent = NEW.q; author.textContent = NEW.a; return;
  }
  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function(es) { visible = es[0].isIntersecting; }, { threshold: .35 }).observe(stage);
  } else { visible = true; }
  loop();
})();
</script>
</body>
</html>
