<?php
/**
 * CardBoard — card designer.
 * URL: /{id}/cardboard  or  /sheets/{id}/cardboard
 *
 * Pick a tab of the sheet (one row per card, one column per field), drag fields onto the card,
 * set their attributes, then print sheets of cards. Layouts are saved in the sheet's "layouts" tab.
 */
$_rp = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
preg_match('#^(.*?)(?:sheets/)?([A-Za-z0-9_\-]+)/cardboard/?$#', $_rp, $_bm);
$_base = (isset($_bm[1]) && $_bm[1] !== '') ? $_bm[1] : '/';
if (substr($_base, -1) !== '/') $_base .= '/';
$_sheet_id = $_bm[2] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<script src="js/activationKey.js" defer></script>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link rel="icon" type="image/png" sizes="32x32" href="images/cardboard_icon_32.png" />
<link rel="icon" type="image/png" sizes="16x16" href="images/cardboard_icon_16.png" />
<link rel="apple-touch-icon" sizes="180x180" href="images/cardboard_icon_180.png" />
<meta name="apple-mobile-web-app-title" content="CardBoard" />
<title>Loading…</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bangers&family=Cinzel:wght@500;700&family=Lilita+One&family=Merriweather:ital,wght@0,400;0,700;1,400&family=Roboto+Condensed:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet" crossorigin="anonymous">
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2') format('woff2'),url('fonts/DINBlack.ttf'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf'); }
*, *::before, *::after { box-sizing:border-box; }
html, body { height:100%; }
body { margin:0; background:#eef0f6; font-family:'DINRegular',Arial,sans-serif; color:#1b1b2b; display:flex; flex-direction:column; }
button, input, select, textarea { font-family:inherit; }

/* ── Top bar (same layout as DevBoard) ─────────────────── */
.top-bar { background:#2e2763; color:#fff; padding:.75rem 1.25rem; position:sticky; top:0; z-index:100; flex-shrink:0; }
.top-bar-inner { display:flex; align-items:center; gap:.75rem; }
/* Tabs centred in the bar (like the other apps): name on the left, menu on the right */
@media (min-width:561px) {
  .top-bar-inner { display:grid; grid-template-columns:minmax(0,1fr) auto minmax(0,1fr); }
  .top-bar-inner > .account-menu-wrap { justify-self:end; }
}
.top-bar-left  { flex:1; min-width:0; display:flex; align-items:center; gap:.65rem; }
.top-bar-left img { width:2.2rem; height:2.2rem; border-radius:7px; flex-shrink:0; }
.top-bar h1    { font-family:'DINBlack',sans-serif; font-size:1rem; margin:0; letter-spacing:.03em; cursor:pointer; }
.cb-card  { color:#f2c14e; }
.cb-board { color:#a99cff; }
.top-bar .sub  { font-size:.73rem; opacity:.6; margin:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.top-tab-btns { display:flex; align-items:center; background:rgba(255,255,255,.12); border-radius:8px; padding:3px; }
.top-tab { font-family:'DINBlack',sans-serif; font-size:.62rem; text-transform:uppercase; letter-spacing:.07em; padding:.3rem .9rem; border-radius:6px; border:none; color:rgba(255,255,255,.6); background:transparent; cursor:pointer; transition:all .15s; white-space:nowrap; }
.top-tab.active { background:#fff; color:#2e2763; }
.top-tab:not(.active):hover { color:#fff; }
.top-btn { background:rgba(255,255,255,.15); border:none; color:#fff; width:2.1rem; height:2.1rem; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; }
.top-btn:hover { background:rgba(255,255,255,.25); }

/* ── Account menu (same style as the other apps) ───────── */
.account-menu-wrap { position:relative; flex-shrink:0; }
.account-menu { display:none; position:absolute; top:calc(100% + .4rem); right:0; background:#1a1a2e; border:1px solid rgba(255,255,255,.2); border-radius:8px; min-width:160px; z-index:300; box-shadow:0 6px 20px rgba(0,0,0,.4); overflow:hidden; }
.account-menu.open { display:block; }
.account-menu-item { display:block; width:100%; background:none; border:none; color:rgba(255,255,255,.85); text-align:left; cursor:pointer; font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase; letter-spacing:.07em; padding:.6rem 1rem; transition:background .12s; }
.account-menu-item:hover { background:rgba(255,255,255,.1); color:#fff; }
.account-menu-divider { border:none; border-top:1px solid rgba(255,255,255,.12); margin:.2rem 0; }

/* ── Views ─────────────────────────────────────────────── */
.view { display:none; flex:1; min-height:0; }
.view.active { display:flex; }

/* ── Designer: three columns ───────────────────────────── */
#view-design { gap:0; }
.panel { background:#fff; border-right:1px solid #e1e4ee; display:flex; flex-direction:column; min-height:0; }
.panel-left  { width:230px; flex-shrink:0; }
.panel-right { width:280px; flex-shrink:0; border-right:none; border-left:1px solid #e1e4ee; position:relative; }
#attrPanel .attr-title { padding-right:2.4rem; }
.layers-btn { position:absolute; top:.6rem; right:.7rem; z-index:5; width:2rem; height:2rem; border-radius:8px; border:1.5px solid #d6d9e6; background:#fff; color:#4b3fa8; display:flex; align-items:center; justify-content:center; cursor:pointer; padding:0; }
.layers-btn:hover { border-color:#a99cff; background:#f7f5ff; }
.layers-btn.on { background:#4b3fa8; border-color:#4b3fa8; color:#fff; }
.layers-panel { flex:0 1 auto; max-height:46%; overflow:auto; padding:.75rem .7rem .55rem; border-bottom:1px solid #e1e4ee; background:#fbfbfe; }
.ly-head { display:flex; align-items:center; gap:.45rem; padding:0 2.4rem .45rem .2rem; font-size:.72rem; color:#8a8fa8; min-height:1.9rem; }
.ly-head b { font-family:'DINBlack',sans-serif; font-weight:400; letter-spacing:.09em; text-transform:uppercase; color:#2e2763; font-size:.66rem; }
.ly-act { margin-left:auto; border:1.5px solid #a99cff; background:#f3f1ff; color:#4b3fa8; border-radius:6px; font-family:'DINBlack',sans-serif; font-size:.62rem; letter-spacing:.06em; text-transform:uppercase; padding:.2rem .55rem; cursor:pointer; }
.ly-act:hover { background:#ece9ff; }
.ly-list { display:flex; flex-direction:column; gap:2px; }
.ly-row { display:flex; align-items:center; gap:.4rem; padding:.32rem .45rem; border-radius:6px; background:#fff; border:1px solid #eceef5; font-size:.76rem; color:#2e2763; cursor:pointer; user-select:none; position:relative; }
.ly-row:hover { border-color:#d6d1f5; }
.ly-row.on { background:#ece9ff; border-color:#a99cff; }
.ly-row.dragging { opacity:.35; }
.ly-row.drop-before::before, .ly-row.drop-after::after { content:''; position:absolute; left:0; right:0; height:2px; background:#7b6cf6; border-radius:2px; }
.ly-row.drop-before::before { top:-3px; } .ly-row.drop-after::after { bottom:-3px; }
.ly-child { margin-left:1.1rem; }
.ly-cb { width:15px; height:15px; flex-shrink:0; border:1.5px solid #c9cce0; border-radius:4px; background:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; }
.ly-cb:hover { border-color:#7b6cf6; }
.ly-cb.on { background:#4b3fa8; border-color:#4b3fa8; }
.ly-cb.on::after { content:''; width:7px; height:4px; border-left:2px solid #fff; border-bottom:2px solid #fff; transform:translateY(-1px) rotate(-45deg); }
.ly-group { background:#f7f5ff; }
.ly-chev { width:.8rem; text-align:center; color:#8a8fa8; font-size:.65rem; transition:transform .15s; }
.ly-chev.open { transform:rotate(90deg); }
.ly-ico { width:16px; height:16px; flex-shrink:0; display:flex; align-items:center; justify-content:center; color:#8a8fa8; font-size:.7rem; }
.ly-ico svg { width:14px; height:14px; }
.ly-name { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ly-kind, .ly-n { font-size:.62rem; color:#a0a4bb; text-transform:uppercase; letter-spacing:.05em; }
.ly-n { background:#ece9ff; color:#6b62b8; border-radius:999px; padding:0 .4rem; }
.ly-rename { flex:1; min-width:0; font:inherit; border:1.5px solid #7b6cf6; border-radius:4px; padding:0 .3rem; }
.ly-tip { margin:.45rem .2rem 0; font-size:.64rem; color:#a0a4bb; }
.panel-scroll { overflow:auto; padding:.9rem .9rem 1.2rem; flex:1; }
.lbl { display:block; font-family:'DINBlack',sans-serif; font-size:.62rem; letter-spacing:.09em; text-transform:uppercase; color:#8a8fa8; margin:.9rem 0 .35rem; }
.lbl:first-child { margin-top:0; }
.sel, .inp { width:100%; height:2.05rem; border:1.5px solid #d6d9e6; border-radius:7px; padding:0 .5rem; font-size:.82rem; color:#1b1b2b; background:#fff; }
.sel:focus, .inp:focus, .ta:focus { outline:none; border-color:#4b3fa8; box-shadow:0 0 0 2px rgba(75,63,168,.15); }
.sel-sheet { font-family:'DINBlack',sans-serif; text-transform:uppercase; letter-spacing:.05em; font-size:.78rem; }
.field-chip { display:flex; align-items:center; gap:.45rem; border:1.5px solid #d6d9e6; border-radius:7px; padding:.38rem .55rem; margin-bottom:.35rem; background:#fff; cursor:grab; font-family:'DINBlack',sans-serif; font-size:.74rem; letter-spacing:.03em; color:#2e2763; user-select:none; transition:border-color .12s, background .12s; }
.field-chip:hover { border-color:#a99cff; background:#f7f5ff; }
.field-chip .ico { width:16px; text-align:center; color:#a0a4bb; font-family:'DINRegular',sans-serif; flex-shrink:0; }
.field-chip .nm { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.field-chip .sample { font-family:'DINRegular',sans-serif; font-size:.68rem; color:#9aa0b8; max-width:70px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.field-chip.static { color:#555; }
.shape-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:.3rem; margin-bottom:.35rem; }
.shape-chip { display:flex; flex-direction:column; align-items:center; gap:.15rem; border:1.5px solid #d6d9e6; border-radius:7px; padding:.35rem .1rem .25rem; background:#fff; cursor:grab; font-size:.6rem; color:#6b6f86; user-select:none; }
.shape-chip:hover { border-color:#a99cff; background:#f7f5ff; }
.shape-ico { width:24px; height:20px; display:block; }
.el.k-shape { overflow:visible; }
/* Left panel sections (fold away with the header) */
.pal-sec { margin-top:.75rem; }
.pal-head { display:flex; align-items:center; gap:.3rem; width:100%; background:none; border:none; padding:.15rem 0; margin:0 0 .35rem; cursor:pointer; font-family:'DINBlack',sans-serif; font-size:.62rem; letter-spacing:.09em; text-transform:uppercase; color:#8a8fa8; text-align:left; }
.pal-head:hover { color:#4b3fa8; }
.pal-head:focus-visible { outline:2px solid #a99cff; outline-offset:2px; border-radius:4px; }
.eye-btn { margin-left:.35rem; width:1.5rem; height:1.3rem; display:inline-flex; align-items:center; justify-content:center; border:none; border-radius:5px; background:none; color:#a0a4bb; cursor:pointer; padding:0; }
.eye-btn:hover { background:#ece9ff; color:#4b3fa8; }
.eye-btn.on { color:#4b3fa8; background:#ece9ff; }
.pal-head .chev { display:inline-block; width:.8rem; transition:transform .15s; }
.pal-sec.closed .chev { transform:rotate(-90deg); }
.pal-sec.closed .pal-body { display:none; }
.pal-count { margin-left:auto; font-family:'DINRegular',sans-serif; letter-spacing:0; background:#eceaf9; color:#6b62b8; border-radius:999px; padding:0 .45rem; font-size:.62rem; }
.pal-count:empty { display:none; }
.field-chip .ico svg { display:block; margin:0 auto; }
/* New column */
.new-field { margin-bottom:.35rem; }
.new-btn { display:flex; align-items:center; gap:.45rem; width:100%; border:1.5px dashed #a99cff; border-radius:7px; padding:.38rem .55rem; margin:0; background:#f7f5ff; color:#4b3fa8; font-family:'DINBlack',sans-serif; font-size:.74rem; letter-spacing:.03em; line-height:inherit; text-align:left; text-transform:none; cursor:pointer; }
.new-btn::before { content:'+'; width:16px; text-align:center; flex-shrink:0; font-size:.9rem; line-height:1; }
.new-btn:hover { background:#ece9ff; border-style:solid; }
.new-form { display:none; gap:.3rem; }
.new-form .inp { flex:1; min-width:0; height:2.26rem; }
.new-form { position:relative; }
.new-form.busy .inp { color:#9aa0b8; background:#f7f5ff; }
.new-form.busy::after { content:''; position:absolute; right:.6rem; top:50%; width:14px; height:14px; margin-top:-7px; border:2px solid #d6d9e6; border-top-color:#4b3fa8; border-radius:50%; animation:spin .7s linear infinite; }
.new-field.open .new-btn { display:none; }
.new-field.open .new-form { display:flex; }
.new-msg { font-size:.68rem; color:#c0392b; margin-top:.2rem; min-height:0; }
.new-msg:empty { display:none; }
.field-chip.menu-open { border-color:#7b6cf6; background:#f3f1ff; }
.field-chip.is-hidden { opacity:.6; border-style:dashed; }
.field-chip.nudge { animation:nudge .35s ease; }
@keyframes nudge { 25% { transform:translateX(-3px); } 50% { transform:translateX(3px); } 75% { transform:translateX(-2px); } }
.field-menu { margin:-.15rem 0 .45rem; padding:.45rem; background:#fff; border:1.5px solid #d6d1f5; border-radius:8px; box-shadow:0 6px 18px rgba(20,20,60,.12); }
.field-menu button { width:100%; display:flex; align-items:center; gap:.4rem; border:none; background:#f7f5ff; color:#2e2763; border-radius:6px; padding:.4rem .5rem; font-size:.78rem; cursor:pointer; text-align:left; }
.field-menu button:hover { background:#ece9ff; }
.field-menu .fm-ico { width:16px; text-align:center; }
.col-menu { position:fixed; z-index:700; width:230px; margin:0; }
.col-menu-name { font-family:'DINBlack',sans-serif; font-size:.68rem; letter-spacing:.06em; text-transform:uppercase; color:#8a8fa8; padding:.1rem .2rem .4rem; }
table.grid thead th[data-c] { cursor:pointer; }
table.grid thead th[data-c]:hover, table.grid thead th.menu-open { background:#e6e1ff; }
.th-caret { margin-left:.35rem; font-size:.6rem; color:#a99cff; }
.field-menu p { margin:.35rem .1rem 0; font-size:.68rem; color:#8a8fa8; line-height:1.4; }
.hidden-toggle { display:flex; align-items:center; gap:.3rem; border:none; background:none; color:#8a8fa8; font-size:.72rem; cursor:pointer; padding:.3rem 0; }
.hidden-toggle .chev { display:inline-block; transform:rotate(-90deg); transition:transform .15s; }
.hidden-toggle.open .chev { transform:none; }
.hidden-toggle:hover { color:#4b3fa8; }
.hidden-list .lk-act { font-size:.72rem; }
.field-chip.just-added { border-color:#7b6cf6; background:#ece9ff; box-shadow:0 0 0 3px rgba(123,108,246,.18); }
/* Picture button on image elements (like GameBoard's) */
.img-btn { position:absolute; top:calc(6px / var(--z, 1)); right:calc(6px / var(--z, 1)); width:calc(30px / var(--z, 1)); height:calc(30px / var(--z, 1)); border-radius:50%; border:none; padding:0; background:rgba(0,0,0,.55); color:#fff; display:none; align-items:center; justify-content:center; cursor:pointer; z-index:55; }
.img-btn:hover { background:rgba(0,0,0,.82); }
.img-btn svg { width:52%; height:52%; pointer-events:none; }
.designing .el.k-image:hover .img-btn, .designing .el.k-image.picked .img-btn,
.designing .el.as-image:hover .img-btn, .designing .el.as-image.picked .img-btn { display:flex; }
.attr-btn svg { width:13px; height:13px; vertical-align:-2px; margin-right:.25rem; }
.ip-row { margin:.5rem 0 0 !important; padding:.4rem .5rem; background:#f3f1ff; border-radius:6px; color:#4a4e68 !important; }
.el.img-loading .el-inner::after, .img-slot.img-loading::after { content:''; position:absolute; inset:0; background:rgba(0,0,0,.4); }
.el.img-loading .el-inner::before, .img-slot.img-loading::before { content:''; position:absolute; top:50%; left:50%; width:calc(26px / var(--z, 1)); height:calc(26px / var(--z, 1)); margin:calc(-13px / var(--z, 1)) 0 0 calc(-13px / var(--z, 1)); border:calc(3px / var(--z, 1)) solid rgba(255,255,255,.3); border-top-color:#fff; border-radius:50%; animation:spin .7s linear infinite; z-index:2; }
.el.img-loading .el-inner { position:relative; }
.el.k-image .el-img { color:#a0a4bb; }
/* Picture slot in the attributes panel */
.img-slot { position:relative; height:130px; border:1.5px dashed #c9cce0; border-radius:8px; background:#f6f7fb; cursor:pointer; overflow:hidden; }
.img-slot:hover, .img-slot.over { border-color:#7b6cf6; background:#f3f1ff; }
.img-slot .img-btn { display:flex; top:6px; right:6px; width:28px; height:28px; }
.img-slot-empty { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:.35rem; color:#9aa0b8; font-size:.72rem; }
.img-slot-empty svg { width:26px; height:26px; }
.img-slot.over::before { content:'Drop to set the picture'; position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(75,63,168,.78); color:#fff; font-family:'DINBlack',sans-serif; font-size:.72rem; letter-spacing:.06em; text-transform:uppercase; z-index:3; }
/* Picture chooser */
.img-picker { position:fixed; z-index:400; width:280px; background:#fff; border-radius:12px; box-shadow:0 14px 40px rgba(20,20,60,.28); padding:.8rem; }
.img-picker .muted { font-size:.68rem; margin:.5rem 0 0; }
.ip-drop { display:flex; flex-direction:column; align-items:center; gap:.25rem; border:1.5px dashed #c9cce0; border-radius:9px; padding:.9rem .5rem; color:#8a8fa8; font-size:.72rem; margin-bottom:.6rem; text-align:center; }
.ip-drop svg { width:26px; height:26px; }
.ip-drop b { color:#2e2763; font-family:'DINBlack',sans-serif; font-weight:400; font-size:.78rem; }
.ip-drop.over { border-color:#7b6cf6; background:#f3f1ff; }
.ip-choose { margin-top:.15rem; height:1.9rem; padding:0 .9rem; border:none; border-radius:7px; background:#4b3fa8; color:#fff; font-family:'DINBlack',sans-serif; font-size:.68rem; letter-spacing:.06em; text-transform:uppercase; cursor:pointer; }
.ip-choose:hover { background:#3b3192; }
.ip-url { display:flex; gap:.35rem; }
.ip-url .inp { flex:1; min-width:0; }
.ip-url .attr-btn { flex:0 0 auto; padding:0 .8rem; }
.ip-remove { width:100%; margin-top:.6rem; }
@media print { .img-btn { display:none !important; } }
.muted { font-size:.78rem; color:#9aa0b8; line-height:1.5; }

/* ── Canvas ────────────────────────────────────────────── */
.stage { flex:1; min-width:0; display:flex; flex-direction:column; }
.toolbar { display:flex; align-items:center; gap:.5rem; padding:.55rem .9rem; background:#f7f8fc; border-bottom:1px solid #e1e4ee; flex-wrap:wrap; }
.tb-btn { height:1.9rem; min-width:1.9rem; border:1.5px solid #d6d9e6; border-radius:7px; background:#fff; color:#3b3f5c; cursor:pointer; font-size:.8rem; padding:0 .5rem; display:inline-flex; align-items:center; justify-content:center; gap:.3rem; }
.tb-btn:hover:not(:disabled) { border-color:#a99cff; color:#2e2763; }
.tb-btn:disabled { opacity:.4; cursor:default; }
.tb-txt { font-size:.78rem; color:#6b7090; min-width:2.6rem; text-align:center; }
.tb-sep { width:1px; height:1.3rem; background:#dde0ea; margin:0 .2rem; }
.tb-save { margin-left:auto; height:1.9rem; border:none; border-radius:7px; background:#4b3fa8; color:#fff; font-family:'DINBlack',sans-serif; font-size:.68rem; letter-spacing:.08em; text-transform:uppercase; padding:0 1rem; cursor:pointer; }
.tb-save:disabled { background:#c9c4ea; cursor:default; }
.tb-status { font-size:.74rem; color:#9aa0b8; }
.tb-status.dirty { color:#c2570c; }
.canvas-wrap { flex:1; min-height:0; overflow:auto; display:flex; align-items:flex-start; justify-content:center; padding:2.2rem;
  background-color:#e4e6ef; background-image:radial-gradient(#cfd2df 1px, transparent 1px); background-size:16px 16px; }
.card-holder { position:relative; flex-shrink:0; }
.card-holder.drop-target .card { box-shadow:0 0 0 3px #a99cff, 0 10px 30px rgba(20,20,60,.22); }

/* ── Cards (shared by designer + print) — all sizes in pt ── */
.card { position:absolute; left:0; top:0; transform-origin:0 0; overflow:hidden; background:#fff; box-shadow:0 10px 30px rgba(20,20,60,.22); }
.el { position:absolute; overflow:hidden; display:flex; flex-direction:column; white-space:pre-wrap; word-break:break-word; line-height:1.15; }
.el.v-middle { justify-content:center; } .el.v-bottom { justify-content:flex-end; }
.el-img { display:block; width:100%; height:100%; object-position:center; background-repeat:no-repeat; background-position:center; }
img.el-img { -webkit-user-drag:none; user-select:none; }
/* Print card colours and fills even when "Background graphics" is off */
.card, .card * { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
/* Flip button (top right of the card) */
.flip-btn { position:absolute; top:0; right:-3.1rem; width:2.5rem; height:2.5rem; border-radius:50%; border:1.5px solid #d6d9e6; background:#fff; color:#4b3fa8; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:0; cursor:pointer; box-shadow:0 3px 10px rgba(20,20,60,.12); padding:0; z-index:5; }
.flip-btn svg { width:18px; height:18px; }
.side-label { position:absolute; left:0; right:0; bottom:100%; margin-bottom:.45rem; text-align:center; font-family:'DINBlack',sans-serif; font-size:.68rem; letter-spacing:.14em; text-transform:uppercase; color:#6b62b8; pointer-events:none; }
.flip-btn:hover { border-color:#7b6cf6; }
.flip-btn.on { background:#4b3fa8; color:#fff; border-color:#4b3fa8; }
.card-holder { transform-style:preserve-3d; }
.page-tag { position:absolute; top:-1.15rem; left:0; font-family:'DINBlack',sans-serif; font-size:.62rem; letter-spacing:.08em; text-transform:uppercase; color:#8a8fa8; }
.el-empty { color:#b8bccc; font-style:italic; }
/* designer-only: selection + handles (sized against the zoom with --z) */
.designing .el { cursor:move; outline:calc(1px / var(--z)) dashed transparent; overflow:visible; }
.designing .el > .el-inner { overflow:hidden; width:100%; height:100%; display:flex; flex-direction:column; justify-content:inherit; }
.designing .el:hover { outline-color:rgba(75,63,168,.45); }
.designing .el.picked { outline:calc(1.5px / var(--z)) solid #4b3fa8; z-index:50; }
.hdl { position:absolute; width:calc(9px / var(--z)); height:calc(9px / var(--z)); background:#fff; border:calc(1.5px / var(--z)) solid #4b3fa8; border-radius:calc(2px / var(--z)); display:none; z-index:60; }
.el.picked .hdl { display:block; }
.el.picked.multi .hdl { display:none; }
.designing .el.picked.multi { outline-style:dashed; }
.sel-box { position:absolute; z-index:49; pointer-events:none; outline:calc(1.5px / var(--z)) solid #7b6cf6; outline-offset:calc(2px / var(--z)); }
.sel-box.group { outline-color:#4b3fa8; }
.hdl.nw { left:calc(-4.5px / var(--z)); top:calc(-4.5px / var(--z)); cursor:nwse-resize; }
.hdl.ne { right:calc(-4.5px / var(--z)); top:calc(-4.5px / var(--z)); cursor:nesw-resize; }
.hdl.sw { left:calc(-4.5px / var(--z)); bottom:calc(-4.5px / var(--z)); cursor:nesw-resize; }
.hdl.se { right:calc(-4.5px / var(--z)); bottom:calc(-4.5px / var(--z)); cursor:nwse-resize; }
.hdl.n  { left:calc(50% - 4.5px / var(--z)); top:calc(-4.5px / var(--z)); cursor:ns-resize; }
.hdl.s  { left:calc(50% - 4.5px / var(--z)); bottom:calc(-4.5px / var(--z)); cursor:ns-resize; }
.hdl.w  { top:calc(50% - 4.5px / var(--z)); left:calc(-4.5px / var(--z)); cursor:ew-resize; }
.hdl.e  { top:calc(50% - 4.5px / var(--z)); right:calc(-4.5px / var(--z)); cursor:ew-resize; }
.card-empty-hint { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; text-align:center; padding:12pt; color:#b0b4c8; font-size:10pt; pointer-events:none; }

/* ── Attributes ────────────────────────────────────────── */
.attr-title { font-family:'DINBlack',sans-serif; font-size:.85rem; color:#2e2763; margin:0 0 .2rem; display:flex; align-items:center; gap:.4rem; }
.attr-title .kind { font-family:'DINRegular',sans-serif; font-size:.68rem; color:#fff; background:#a99cff; border-radius:999px; padding:.05rem .45rem; text-transform:uppercase; letter-spacing:.06em; }
.row2 { display:grid; grid-template-columns:1fr 1fr; gap:.45rem; }
.row4 { display:grid; grid-template-columns:repeat(4,1fr); gap:.35rem; }
.mini { display:block; font-size:.62rem; color:#9aa0b8; margin-bottom:.15rem; text-transform:uppercase; letter-spacing:.06em; }
.ta { width:100%; min-height:4.2rem; border:1.5px solid #d6d9e6; border-radius:7px; padding:.4rem .5rem; font-size:.82rem; resize:vertical; }
.seg { display:flex; border:1.5px solid #d6d9e6; border-radius:7px; overflow:hidden; }
.seg button { flex:1; height:1.9rem; border:none; background:#fff; color:#6b7090; cursor:pointer; font-size:.8rem; border-left:1px solid #e3e5ee; }
.seg button:first-child { border-left:none; }
.seg button.on { background:#ece9ff; color:#2e2763; font-family:'DINBlack',sans-serif; }
.color-row { display:flex; gap:.35rem; align-items:center; }
.color-row input[type=color] { width:2.2rem; height:2.05rem; border:1.5px solid #d6d9e6; border-radius:7px; padding:2px; background:#fff; cursor:pointer; flex-shrink:0; }
.color-row .inp { flex:1; }
.attr-btns { display:flex; gap:.4rem; margin-top:1.1rem; flex-wrap:wrap; }
.attr-btn { flex:1; height:2rem; border:1.5px solid #d6d9e6; border-radius:7px; background:#fff; color:#3b3f5c; font-size:.75rem; cursor:pointer; white-space:nowrap; }
.attr-btn:hover { border-color:#a99cff; }
.attr-btn.danger { color:#c0392b; border-color:#f0c7c2; }
.attr-btn.danger:hover { background:#fff3f2; }
.insert-row { display:flex; gap:.35rem; margin-top:.35rem; }

/* ── Print view ────────────────────────────────────────── */
#view-print { flex-direction:row; }
/* Print: settings on the left, pages on the right */
#view-print .print-bar { width:300px; flex-shrink:0; flex-direction:column; align-items:stretch; gap:.65rem; overflow:auto; border-bottom:none; border-right:1px solid #e1e4ee; background:#fbfbfe; }
#view-print .print-bar .sel { width:100%; }
#view-print .print-info { align-self:flex-start; margin-top:.2rem; line-height:1.45; }
#view-print .print-go { margin-left:0; width:100%; }
#view-print .pages { min-width:0; }
@media (max-width:900px) { #view-print { flex-direction:column; } #view-print .print-bar { width:auto; border-right:none; border-bottom:1px solid #e1e4ee; } }
#view-export { flex-direction:row; }
#view-data { flex-direction:column; }
/* Tags on cards: an inline picture the height of the text */
.el .tx { display:block; }
.tag-img { display:inline-block; height:1.15em; width:auto; vertical-align:-.22em; margin:0 .04em; }
/* Tags view */
#view-tags { overflow:auto; background:#f4f3fa; }
.tags-wrap { width:100%; max-width:1000px; margin:0 auto; padding:1.4rem 1.4rem 3rem; }
.tags-head { display:flex; align-items:flex-start; gap:1rem; margin-bottom:1rem; }
.tags-head .e-actions { align-items:center; flex-shrink:0; }
.tags-title { margin:0 0 .3rem; font-family:'DINBlack',sans-serif; font-weight:400; font-size:1.15rem; color:#2e2763; display:flex; align-items:center; gap:.5rem; }
.tags-sub { margin:0; font-size:.82rem; color:#6b7090; max-width:560px; line-height:1.5; }
.tags-sub code, .tag-empty code { background:#ece9ff; color:#4b3fa8; border-radius:4px; padding:0 .3rem; font-size:.78rem; }
.tags-pro { display:flex; align-items:center; gap:.8rem; background:#fdf6e3; border:1px solid #f5d98c; border-radius:10px; padding:.6rem .8rem; font-size:.8rem; color:#5c4a12; margin-bottom:.9rem; }
.tags-pro .plan-btn { margin-left:auto; flex-shrink:0; }
.tag-cols, .tag-row { display:grid; grid-template-columns:170px 132px minmax(0,1fr) 150px 2rem; gap:.6rem; align-items:center; }
.tag-cols { padding:0 .8rem .35rem; font-family:'DINBlack',sans-serif; font-size:.6rem; letter-spacing:.09em; text-transform:uppercase; color:#8a8fa8; }
.tag-row { background:#fff; border:1px solid #e5e3f0; border-radius:10px; padding:.55rem .8rem; margin-bottom:.45rem; }
.tag-row.draft { border-color:#a99cff; box-shadow:0 0 0 3px rgba(123,108,246,.12); }
.tag-name { font-family:ui-monospace,Menlo,monospace; font-size:.8rem; color:#4b3fa8; }
.tag-kind button { height:1.9rem; }
.tag-val .inp { width:100%; }
.tag-img-box { display:flex; align-items:center; gap:.45rem; }
.tag-img-box > img { width:30px; height:30px; object-fit:contain; border-radius:5px; background:#f4f3fa; flex-shrink:0; }
.tag-img-box .inp { flex:1; min-width:0; }
.tag-pick { flex-shrink:0; display:inline-flex; align-items:center; gap:.3rem; height:1.9rem; padding:0 .6rem; border:1.5px solid #d6d9e6; border-radius:7px; background:#fff; color:#2e2763; font-size:.74rem; cursor:pointer; }
.tag-pick:hover:not(:disabled) { border-color:#a99cff; background:#f7f5ff; }
.tag-pick svg { width:14px; height:14px; }
.tag-prev { font-family:'DINRegular',sans-serif; font-size:.9rem; color:#2b2140; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.tag-prev img { height:1.2em; vertical-align:-.25em; }
.tag-prev i { color:#b0b4c8; }
.tag-del { width:2rem; height:2rem; border:none; background:none; color:#b0b4c8; border-radius:6px; cursor:pointer; }
.tag-del:hover:not(:disabled) { background:#fff1f2; color:#c0392b; }
.tag-row :disabled { opacity:.6; cursor:default; }
.tag-empty { background:#fff; border:1.5px dashed #d6d1f5; border-radius:12px; padding:1.6rem; text-align:center; font-size:.86rem; color:#6b7090; line-height:1.6; }
.tag-dlg { width:min(470px,94vw); }
.tag-tabs { margin:.6rem 0 .2rem; }
.icon-color { display:flex; align-items:center; gap:.5rem; margin:.7rem 0 .5rem; }
.icon-color .mini { margin:0; }
.icon-color input { width:2.2rem; height:1.8rem; border:1.5px solid #d6d9e6; border-radius:6px; padding:2px; background:#fff; cursor:pointer; }
.icon-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(44px,1fr)); gap:.35rem; max-height:260px; overflow:auto; }
.icon-cell { height:44px; border:1.5px solid #e5e3f0; border-radius:8px; background:#fff; cursor:pointer; display:flex; align-items:center; justify-content:center; }
.icon-cell img { width:24px; height:24px; }
.icon-cell:hover { border-color:#a99cff; background:#f7f5ff; }
.icon-cell.on { border-color:#4b3fa8; box-shadow:0 0 0 2px rgba(75,63,168,.2); }
.tag-drop { margin:.8rem 0 .3rem; cursor:pointer; }
@media (max-width:900px) { .tag-cols { display:none; } .tag-row { grid-template-columns:1fr 1fr; } .tag-val, .tag-prev { grid-column:1 / -1; } }
.add-col { position:relative; display:flex; }
.add-col-form { display:none; position:relative; }
.add-col.open .add-col-btn { display:none; }
.add-col.open .add-col-form { display:block; }
.add-col-form .inp { width:12rem; height:2.05rem; }
.add-col.busy .add-col-form::after { content:''; position:absolute; right:.6rem; top:50%; width:14px; height:14px; margin-top:-7px; border:2px solid #d6d9e6; border-top-color:#4b3fa8; border-radius:50%; animation:spin .7s linear infinite; }
.add-col-msg { position:absolute; top:100%; left:0; margin-top:.25rem; white-space:nowrap; font-size:.7rem; color:#c0392b; }
.add-col-msg:empty { display:none; }
table.grid thead th.just-added { animation:colFlash 1.6s ease; }
@keyframes colFlash { 0%,40% { background:#d9d2ff; } }
/* Export: the info and links panel takes at most half the height; both halves scroll */
/* Export: settings and links on the left, the preview on the right; each scrolls on its own */
.export-side { width:440px; flex-shrink:0; display:flex; flex-direction:column; min-height:0; overflow:auto; background:#fbfbfe; border-right:1px solid #e1e4ee; }
#view-export .print-bar { flex-direction:column; align-items:stretch; gap:.65rem; border-bottom:1px solid #e1e4ee; }
#view-export .print-bar .grp .sel, #view-export .print-bar .grp .inp { width:100%; }
#view-export .e-actions { margin-left:0; }
#view-export .e-actions .print-go { flex:1; }
#view-export .export-info { border-bottom:none; }
#view-export .export-links { padding:.6rem .65rem; }
#view-export .lk-row { grid-template-columns:minmax(0,1fr) auto; row-gap:.2rem; margin-bottom:.6rem; }
#view-export .lk-name { grid-column:1 / -1; }
#view-export .lk-meta { grid-column:1 / -1; justify-self:start; }
#view-export .lk-meta.empty { display:none; }
#view-export #exportPreview { flex:1; min-width:0; min-height:0; }
@media (max-width:900px) { #view-export { flex-direction:column; } .export-side { width:auto; max-height:50%; border-right:none; border-bottom:1px solid #e1e4ee; } }
.grid-hint { margin:0; padding:.4rem 1.1rem; font-size:.72rem; color:#8a8fa8; background:#fbfbfe; border-bottom:1px solid #e1e4ee; }
.grid-wrap { position:relative; flex:1; min-height:0; overflow:auto; background:#fff; }
table.grid { border-collapse:separate; border-spacing:0; font-size:.8rem; color:#1b1b2b; min-width:100%; }
table.grid th, table.grid td { border-right:1px solid #e6e8f0; border-bottom:1px solid #e6e8f0; padding:0 .5rem; height:2rem; max-width:22rem; min-width:7rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; text-align:left; vertical-align:middle; }
table.grid thead th { position:sticky; top:0; z-index:3; background:#f3f1ff; color:#2e2763; font-family:'DINBlack',sans-serif; font-weight:400; font-size:.72rem; letter-spacing:.03em; border-bottom:1.5px solid #d6d1f5; }
table.grid .rn { position:sticky; left:0; z-index:2; min-width:2.6rem; width:2.6rem; text-align:center; background:#f7f8fc; color:#9aa0b8; font-size:.7rem; }
table.grid thead .rn { z-index:4; background:#ece9ff; }
table.grid td { cursor:cell; background:#fff; }
table.grid td > span { vertical-align:middle; }
table.grid td.formula > span { color:#4b3fa8; font-family:ui-monospace,Menlo,monospace; font-size:.74rem; }
table.grid td.sel { box-shadow:inset 0 0 0 2px #4b3fa8; }
table.grid td.st-saving { background:#fffbea; }
table.grid td.st-saved { background:#ecfdf3; transition:background .8s; }
table.grid td.st-error { background:#fef2f2; box-shadow:inset 0 0 0 1.5px #e5484d; }
table.grid tr.draft td { background:#faf9ff; }
table.grid tbody tr:hover td:not(.sel) { background-color:#fafaff; }
.g-thumb { width:22px; height:22px; object-fit:cover; border-radius:4px; vertical-align:middle; margin-right:.4rem; background:#eef0f6; }
.g-editor { position:absolute; z-index:20; border:2px solid #4b3fa8; border-radius:2px; padding:.4rem .5rem; font:inherit; font-size:.8rem; line-height:1.35; resize:none; background:#fff; box-shadow:0 6px 20px rgba(20,20,60,.18); outline:none; max-width:32rem; }
.e-note { font-size:.8rem; color:#3b3f5c; height:2.05rem; display:flex; align-items:center; }
.e-back { display:flex; gap:.3rem; align-items:center; }
.e-back input[type=color] { width:2.05rem; height:2.05rem; border:1.5px solid #d6d9e6; border-radius:7px; padding:2px; background:#fff; cursor:pointer; }
.e-back .inp { width:15rem; }
.e-upload { flex:0 0 auto; width:2.05rem; height:2.05rem; display:flex; align-items:center; justify-content:center; padding:0; font-size:1rem; }
.e-check { display:flex; align-items:center; gap:.35rem; font-size:.8rem; color:#3b3f5c; height:2.05rem; cursor:pointer; }
.export-info { padding:.6rem 1.1rem .7rem; background:#fbfbfe; border-bottom:1px solid #e1e4ee; font-size:.8rem; color:#6b7090; }
.export-help { margin-top:.3rem; font-size:.76rem; line-height:1.45; color:#4a4e68; max-width:70rem; }
.export-help code { background:#eceaf9; color:#4b3fa8; border-radius:4px; padding:0 .3rem; font-size:.72rem; }
.e-actions { display:flex; gap:.5rem; margin-left:auto; }
.e-actions .print-go { margin-left:0; }
.print-go.ghost { background:#fff; color:#4b3fa8; border:1.5px solid #a99cff; }
.print-go.ghost:hover { background:#f7f5ff; }
.print-go:disabled { opacity:.5; cursor:default; }
.export-links { margin-top:.7rem; padding:.7rem .8rem; background:#fff; border:1.5px solid #e1e4ee; border-radius:10px; max-width:70rem; }
.lk-head { font-size:.78rem; color:#6b7090; margin-bottom:.5rem; display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
.lk-head b { color:#2e2763; font-family:'DINBlack',sans-serif; font-weight:400; letter-spacing:.05em; text-transform:uppercase; font-size:.7rem; }
.lk-act { border:none; background:none; color:#4b3fa8; font-size:.76rem; cursor:pointer; padding:0; text-decoration:underline; }
.lk-act.danger { color:#c0392b; }
.lk-row { display:grid; grid-template-columns:6.5rem minmax(0,1fr) auto 14rem; align-items:center; gap:.4rem; margin-bottom:.35rem; }
.lk-name { font-size:.72rem; color:#3b3f5c; font-family:'DINBlack',sans-serif; letter-spacing:.03em; }
.lk-row .inp { width:100%; min-width:0; height:1.9rem; font-size:.74rem; color:#4a4e68; background:#f7f8fc; }
.lk-row .attr-btn { flex:0 0 auto; height:1.9rem; padding:0 .8rem; }
.lk-meta { font-size:.72rem; color:#6b62b8; background:#f3f1ff; border-radius:6px; padding:.2rem .5rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.lk-meta.empty { visibility:hidden; }
.lk-backs { font-size:.76rem; color:#4a4e68; border-radius:8px; padding:.45rem .6rem; margin-bottom:.55rem; line-height:1.45; }
.lk-backs.on { background:#f3f1ff; }
.lk-backs.off { background:#f6f7fb; }
.lk-backs > b:first-child { color:#2e2763; }
.lk-warn { display:block; color:#b45309; margin-top:.2rem; }
@media (max-width:900px) { .lk-row { grid-template-columns:1fr auto; } .lk-name, .lk-meta { grid-column:1 / -1; } }
.lk-note { margin:.4rem 0 0; font-size:.72rem; }
.export-progress { height:6px; background:#e4e6ef; border-radius:999px; overflow:hidden; margin:.5rem 0 .25rem; max-width:30rem; }
.export-progress > div { height:100%; width:0; background:#7b6cf6; transition:width .2s; }
.x-label { font-family:'DINBlack',sans-serif; font-size:.68rem; letter-spacing:.08em; text-transform:uppercase; color:#8a8fa8; margin-bottom:-1rem; }
.x-sheet { position:relative; flex-shrink:0; background:#fff; box-shadow:0 6px 24px rgba(20,20,60,.18); overflow:hidden; }
.x-grid { display:grid; transform-origin:0 0; }
.x-cell { position:relative; overflow:hidden; background:repeating-conic-gradient(#f1f2f7 0 25%, #fff 0 50%) 0 0/16px 16px; }
.x-cell .card { box-shadow:none; }
.x-back { outline:3px dashed #7b6cf6; outline-offset:-3px; }
.x-flow { display:flex; flex-wrap:wrap; gap:10px; padding:12px; max-width:900px; background:transparent; box-shadow:none; }
.x-thumb { position:relative; overflow:hidden; box-shadow:0 2px 8px rgba(20,20,60,.15); }
#exportStage { position:fixed; left:-20000px; top:0; width:2000px; height:2000px; overflow:hidden; pointer-events:none; }
#exportStage .card { position:absolute; left:0; top:0; }
.print-bar { display:flex; align-items:flex-end; gap:.8rem; flex-wrap:wrap; padding:.8rem 1.1rem; background:#fff; border-bottom:1px solid #e1e4ee; }
.print-bar .grp { display:flex; flex-direction:column; gap:.2rem; }
.print-bar .sel { width:auto; min-width:7.5rem; }
.print-go { height:2.05rem; border:none; border-radius:7px; background:#4b3fa8; color:#fff; font-family:'DINBlack',sans-serif; font-size:.72rem; letter-spacing:.08em; text-transform:uppercase; padding:0 1.2rem; cursor:pointer; margin-left:auto; }
.print-info { font-size:.8rem; color:#6b7090; align-self:center; }
.pages { flex:1; overflow:auto; padding:1.5rem; display:flex; flex-direction:column; align-items:center; gap:2.2rem; background:#e4e6ef; }
.page-holder { position:relative; flex-shrink:0; box-shadow:0 6px 24px rgba(20,20,60,.18); background:#fff; }
.page { position:absolute; left:0; top:0; background:#fff; transform-origin:0 0; overflow:hidden; }
.page .card { box-shadow:none; }
.cut { position:absolute; background:#9aa0b8; }
.page-ref { position:absolute; top:7pt; font-family:'DINRegular',Arial,sans-serif; font-size:6pt; line-height:1; color:#9aa0b8; white-space:nowrap; max-width:60%; overflow:hidden; text-overflow:ellipsis; }
.page-ref.left { left:18pt; } .page-ref.right { right:18pt; text-align:right; }

/* Dialogs */
.overlay { display:none; position:fixed; inset:0; z-index:800; background:rgba(20,18,45,.45); align-items:center; justify-content:center; padding:1rem; }
.overlay.open { display:flex; }
.dlg { width:min(420px,94vw); max-height:90vh; overflow:auto; background:#fff; border-radius:14px; padding:1.2rem 1.3rem 1.1rem; box-shadow:0 20px 60px rgba(20,20,60,.3); }
.dlg .mini { margin-top:.6rem; }
.dlg-title { margin:0 0 .25rem; font-family:'DINBlack',sans-serif; font-weight:400; font-size:1.05rem; color:#2e2763; letter-spacing:.02em; }
.dlg-sub { margin:0; font-size:.76rem; color:#7a7f99; line-height:1.45; }
.dlg-sub code { font-size:.7rem; background:#f3f1ff; color:#4b3fa8; padding:0 .25rem; border-radius:4px; word-break:break-all; }
.dlg-err { color:#c0392b; font-size:.76rem; min-height:1rem; margin-top:.5rem; }
.dlg-actions { display:flex; justify-content:flex-end; gap:.5rem; margin-top:.6rem; }
.dlg-actions .attr-btn { flex:0 0 auto; padding:0 1rem; height:2.05rem; }
.dlg-actions .print-go { margin-left:0; }
.dlg.shake { animation:nudge .35s ease; }
.ask-overlay { z-index:950; animation:askFade .15s ease; }
.dlg.ask { width:min(400px,92vw); animation:askPop .18s cubic-bezier(.3,1.4,.5,1); }
.ask-msg { margin:.35rem 0 .2rem; font-size:.86rem; line-height:1.5; color:#4a4e68; }
.dlg.ask .dlg-actions { margin-top:1.1rem; }
.dlg.ask .print-go { padding:0 1.2rem; }
.print-go.danger { background:#c0392b; }
.print-go.danger:hover { background:#a93226; }
.dlg.ask button:focus-visible { outline:2px solid #7b6cf6; outline-offset:2px; }
@keyframes askFade { from { opacity:0; } }
@keyframes askPop { from { transform:scale(.94); opacity:0; } }
.set-sheet, .set-plan { display:flex; align-items:center; justify-content:space-between; gap:.6rem; font-size:.84rem; color:#2e2763; }
.set-sheet .attr-btn, .set-plan .attr-btn { flex:0 0 auto; padding:0 .8rem; }
.plan-title { display:flex; align-items:center; justify-content:center; gap:.5rem; margin:0 0 .35rem; font-family:'DINBlack',sans-serif; font-weight:400; font-size:1.1rem; color:#2e2763; }
.plan-title img { width:28px; height:28px; border-radius:7px; }
.plan-current { display:block; width:fit-content; margin:0 auto .2rem; font-size:.82rem; color:#555; background:#f1f2f8; border-radius:999px; padding:.22rem .8rem; }
.plan-current:empty { display:none; }
.plan-current b { font-family:'DINBlack',sans-serif; font-weight:400; color:#333; }
.plan-current span { color:#888; }
.plan-current.paid { background:#edf7ed; } .plan-current.paid b { color:#2e7d32; }
.plan-grid { display:grid; grid-template-columns:1fr 1fr; gap:.7rem; text-align:left; }
@media (max-width:480px) { .plan-grid { grid-template-columns:1fr; } }
.plan-card { border:1.5px solid #e2e2ea; border-radius:10px; padding:.85rem .9rem .9rem; display:flex; flex-direction:column; position:relative; }
.plan-card.plan-pro { border-color:#f5a623; }
.plan-card.current { border-color:#4caf50; background:#f3faf3; }
.plan-card.current::before { content:'Current plan'; position:absolute; top:-.6rem; left:.8rem; background:#4caf50; color:#fff; font-family:'DINBlack',sans-serif; font-size:.6rem; letter-spacing:.06em; text-transform:uppercase; padding:.12rem .45rem; border-radius:999px; }
.plan-name { font-family:'DINBlack',sans-serif; font-size:.78rem; letter-spacing:.07em; text-transform:uppercase; color:#555; }
.plan-price { font-family:'DINBlack',sans-serif; font-size:1.45rem; color:#222; margin:.15rem 0 .4rem; }
.plan-price span { font-family:'DINRegular',sans-serif; font-size:.78rem; color:#888; margin-left:.15rem; }
.plan-card ul { list-style:none; margin:0 0 .8rem; padding:0; font-size:.78rem; color:#444; line-height:1.5; flex:1; }
.plan-card li { padding-left:1.1rem; text-indent:-1.1rem; margin-bottom:.2rem; }
.plan-card li::before { content:'✓ '; color:#4caf50; font-weight:700; }
.plan-btn { background:#f5a623; color:#fff; border:none; border-radius:6px; padding:.5rem .6rem; font-family:'DINBlack',sans-serif; font-size:.7rem; letter-spacing:.06em; text-transform:uppercase; cursor:pointer; }
.plan-btn:disabled { opacity:.5; cursor:default; }
.plan-manage { background:#f5a623; color:#fff; font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; padding:0 1.1rem; height:2.05rem; border-radius:7px; border:none; cursor:pointer; }
.pro-tag { display:inline-block; margin-left:.35rem; padding:0 .35rem; border-radius:4px; background:#f5a623; color:#fff; font-family:'DINBlack',sans-serif; font-size:.56rem; letter-spacing:.06em; vertical-align:middle; }
/* Toast */
.toast { position:fixed; left:50%; bottom:1.4rem; transform:translate(-50%,10px); background:#15803d; color:#fff; font-family:'DINBlack',sans-serif; font-size:.78rem; letter-spacing:.03em; padding:.55rem 1rem; border-radius:999px; opacity:0; pointer-events:none; transition:opacity .2s, transform .2s; z-index:900; box-shadow:0 6px 18px rgba(0,0,0,.2); }
.toast.show { opacity:1; transform:translate(-50%,0); }
.toast.err { background:#b42318; }
.loading { display:flex; align-items:center; justify-content:center; gap:.6rem; flex:1; color:#8a8fa8; font-size:.9rem; }
.spin { width:20px; height:20px; border:2.5px solid #d6d9e6; border-top-color:#4b3fa8; border-radius:50%; animation:spin .8s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }

@media (max-width:900px) {
  #view-design { flex-direction:column; overflow:auto; }
  .panel-left, .panel-right { width:auto; border:none; border-bottom:1px solid #e1e4ee; }
  .stage { min-height:70vh; }
}
@media (max-width:560px) {
  .top-bar { padding:.5rem 1rem; }
  .top-bar-inner { flex-wrap:wrap; gap:.4rem; padding-bottom:.4rem; }
  .top-tab-btns { order:3; width:100%; } .top-tab { flex:1; }
}

/* ── Printing: only the pages, at true size ────────────── */
@media print {
  body { background:#fff; display:block; }
  .top-bar, .print-bar, #view-design, #view-export, #view-data, #view-tags, .toast { display:none !important; }
  #view-print { display:block !important; }
  .pages { display:block; padding:0; background:#fff; overflow:visible; }
  .page-holder { box-shadow:none; width:auto !important; height:auto !important; break-after:page; page-break-after:always; }
  .page-holder:last-child { break-after:auto; page-break-after:auto; }
  .page { position:relative; transform:none !important; }
  .page-tag { display:none; }
}
</style>
<style id="pageSizeStyle">@page { size: letter portrait; margin: 0; }</style>
</head>
<body>

<div class="top-bar">
  <div class="top-bar-inner">
    <div class="top-bar-left">
      <img src="images/cardboard_icon_180.png" alt="">
      <div style="min-width:0">
        <h1 onclick="window.location.href=APP_BASE+'cardboard'"><span class="cb-card">Card</span><span class="cb-board">Board</span></h1>
        <p class="sub" id="subTitle">Card Designer</p>
      </div>
    </div>
    <div class="top-tab-btns">
      <button class="top-tab" id="tabData" onclick="switchTab('data')">Data</button>
      <button class="top-tab" id="tabTags" onclick="switchTab('tags')">Tags</button>
      <button class="top-tab active" id="tabDesign" onclick="switchTab('design')">Design</button>
      <button class="top-tab" id="tabPrint" onclick="switchTab('print')">Print</button>
      <button class="top-tab" id="tabExport" onclick="switchTab('export')">Export</button>
    </div>
    <div class="account-menu-wrap">
      <button class="top-btn" onclick="toggleAccountMenu(event)" title="Menu">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="7.5" r="4.5"/><path d="M3.5 21c0-4.14 3.81-7.5 8.5-7.5s8.5 3.36 8.5 7.5"/></svg>
      </button>
      <div class="account-menu" id="accountMenu">
        <button class="account-menu-item" onclick="closeAccountMenu();openProfileDialog()">Profile</button>
        <button class="account-menu-item" onclick="closeAccountMenu();openSettingsDialog()">Settings</button>
        <button class="account-menu-item" onclick="closeAccountMenu();reloadSheet()" title="Read the latest data from the Google Sheet">Fetch</button>
        <button class="account-menu-item" id="cbSubBtn" onclick="closeAccountMenu();openPlansDialog()" style="color:#f5a623">Subscribe</button>
        <button class="account-menu-item" onclick="closeAccountMenu();window.open(APP_BASE + 'cardboard/help', '_blank')">Help</button>
      </div>
    </div>
  </div>
</div>

<!-- Data view -->
<div class="view" id="view-data">
  <div class="print-bar grid-bar">
    <div class="grp"><span class="mini">Sheet</span>
      <select class="sel sel-sheet" id="gridSheet" onchange="selectTab(this.value)"></select></div>
    <div class="grp"><span class="mini">Find</span>
      <input class="inp" id="gridFilter" placeholder="Search the rows" oninput="filterGrid(this.value)" style="width:14rem"></div>
    <span class="print-info" id="gridInfo"></span>
    <div class="e-actions">
      <div class="add-col" id="gridAddCol">
        <button class="print-go ghost add-col-btn" onclick="showAddColumn()" title="Add a column to the sheet">+ Add column</button>
        <form class="add-col-form" onsubmit="addColumn(event)"><input class="inp" id="gridAddColName" maxlength="60" placeholder="New column name" autocomplete="off" title="Enter or click away to add · Esc to cancel"
          oninput="$('gridAddColMsg').textContent=''" onblur="addColumn()" onkeydown="if(event.key==='Escape'){this.value='';hideAddColumn()}"></form>
        <div class="add-col-msg" id="gridAddColMsg"></div>
      </div>
      <button class="print-go ghost" onclick="loadGrid(true)" title="Read the sheet again">Reload</button>
      <button class="print-go" onclick="addGridRow()">+ Add row</button></div>
  </div>
  <p class="grid-hint">Click a cell and type, or double-click to edit · Enter saves (Shift+Enter for a new line) · Tab moves right · Esc cancels. Changes go straight to the Google Sheet.</p>
  <div class="grid-wrap" id="gridWrap"></div>
</div>

<!-- Tags view -->
<div class="view" id="view-tags">
  <div class="tags-wrap">
    <div class="tags-head">
      <div>
        <h2 class="tags-title">Tags <span class="pro-tag">PRO</span></h2>
        <p class="tags-sub">Write a tag like <code>{energy}</code> in any text or data, and every card shows its word or picture instead. Tags are kept in your spreadsheet's <b>tags</b> tab.</p>
      </div>
      <div class="e-actions"><span class="print-info" id="tagsInfo"></span>
        <button class="print-go ghost" onclick="loadTags()">Reload</button>
        <button class="print-go" onclick="addTag()">+ Add tag</button></div>
    </div>
    <div class="tags-pro" id="tagsPro" style="display:none">Tags are part of <b>CardBoard Pro</b>. You can see the tags in this sheet; with Pro you can change them and they show on your cards. <button class="plan-btn" onclick="openPlansDialog('tags')">See plans</button></div>
    <div class="tag-cols"><span>Tag</span><span>Type</span><span>Becomes</span><span>Preview</span><span></span></div>
    <div id="tagList"><div class="loading"><span class="spin"></span>Loading tags…</div></div>
  </div>
</div>

<!-- Design view -->
<div class="view active" id="view-design">
  <div class="panel panel-left">
    <div class="panel-scroll">
      <span class="lbl">Sheet</span>
      <select class="sel sel-sheet" id="sheetSel" onchange="selectTab(this.value)"></select>
      <div id="palette"></div>
    </div>
  </div>

  <div class="stage">
    <div class="toolbar">
      <button class="tb-btn" id="rowPrev" onclick="stepRow(-1)" title="Previous row">◀</button>
      <span class="tb-txt" id="rowInfo">—</span>
      <button class="tb-btn" id="rowNext" onclick="stepRow(1)" title="Next row">▶</button>
      <span class="tb-sep"></span>
      <button class="tb-btn" onclick="zoomBy(-1)" title="Zoom out">−</button>
      <span class="tb-txt" id="zoomInfo">100%</span>
      <button class="tb-btn" onclick="zoomBy(1)" title="Zoom in">+</button>
      <button class="tb-btn" onclick="fitZoom()" title="Fit">Fit</button>
      <span class="tb-sep"></span>
      <button class="tb-btn" id="undoBtn" onclick="undo()" title="Undo (⌘Z)">↶</button>
      <button class="tb-btn" id="redoBtn" onclick="redo()" title="Redo (⇧⌘Z)">↷</button>
      <span class="tb-status" id="saveStatus" style="margin-left:auto"></span>
      <button class="tb-save" id="saveBtn" onclick="saveLayout()" disabled style="margin-left:.5rem">Save</button>
    </div>
    <div class="canvas-wrap" id="canvasWrap" onpointerdown="if(event.target===this)selectEl(null)">
      <div class="card-holder" id="cardHolder"></div>
    </div>
  </div>

  <div class="panel panel-right">
    <button type="button" class="layers-btn" id="layersBtn" onclick="toggleLayers()" title="Layers — every element on the card" aria-label="Layers">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2 8l10 5 10-5-10-5z"/><path d="m2 13 10 5 10-5"/><path d="m2 17.5 10 5 10-5" opacity=".55"/></svg></button>
    <div class="layers-panel" id="layersPanel" style="display:none"></div>
    <div class="panel-scroll" id="attrPanel"></div>
  </div>
</div>

<!-- Print view -->
<div class="view" id="view-print">
  <div class="print-bar">
    <div class="grp"><span class="mini">Paper</span>
      <select class="sel" id="pPaper" onchange="renderPrint()"><option value="letter">Letter (8.5 × 11 in)</option><option value="a4">A4 (210 × 297 mm)</option></select></div>
    <div class="grp"><span class="mini">Orientation</span>
      <select class="sel" id="pOrient" onchange="renderPrint()"><option value="portrait">Portrait</option><option value="landscape">Landscape</option></select></div>
    <div class="grp"><span class="mini">Gap</span>
      <select class="sel" id="pGap" onchange="renderPrint()"><option value="0">None</option><option value="4.5">1/16 in</option><option value="9">1/8 in</option></select></div>
    <div class="grp"><span class="mini">Cut lines</span>
      <select class="sel" id="pCut" onchange="renderPrint()"><option value="marks">Crop marks</option><option value="outline">Card outlines</option><option value="none">None</option></select></div>
    <div class="grp"><span class="mini">Copies</span>
      <select class="sel" id="pCopies" onchange="renderPrint()"></select></div>
    <div class="grp"><span class="mini">Backs</span>
      <select class="sel" id="pBacks" onchange="renderPrint()"><option value="none">Fronts only</option><option value="long">Double-sided (flip on long edge)</option><option value="short">Double-sided (flip on short edge)</option></select></div>
    <span class="print-info" id="printInfo"></span>
    <button class="print-go" onclick="window.print()">Print</button>
  </div>
  <div class="pages" id="pages"></div>
</div>

<!-- Export view -->
<div class="view" id="view-export">
  <div class="export-side">
  <div class="print-bar">
    <div class="grp"><span class="mini">For</span>
      <select class="sel" id="eTarget" onchange="renderExport()"><option value="tts">Tabletop Simulator</option><option value="screentop">Screentop</option><option value="images">Separate images</option></select></div>
    <div class="grp"><span class="mini">Size</span>
      <select class="sel" id="eQuality" onchange="renderExport()"><option value="4096">Standard (up to 4096 px)</option><option value="8192">Large (up to 8192 px)</option><option value="2048">Small (up to 2048 px)</option></select></div>
    <div class="grp"><span class="mini">Copies</span>
      <select class="sel" id="eCopies"></select></div>
    <div class="grp"><span class="mini">Card back</span><span class="e-note" id="eBackNote"></span></div>
    <label class="e-check"><input type="checkbox" id="eCorners" onchange="renderExport()"> Rounded corners</label>
    <div class="e-actions"><button class="print-go ghost" id="publishGo" onclick="runPublish()" title="Host the sheets on zapsheets.com at links that stay the same (Pro)">Get links</button>
    <button class="print-go" id="exportGo" onclick="runExport()">Download</button></div>
  </div>
  <div class="export-info"><span id="exportInfo"></span><div class="export-help" id="exportHelp"></div>
    <div class="export-progress" id="exportProgress" style="display:none"><div></div></div><span class="muted" id="exportStatus"></span>
    <div class="export-links" id="exportLinks" style="display:none"></div></div>
  </div>
  <div class="pages" id="exportPreview"></div>
  <div id="exportStage" aria-hidden="true"></div>
</div>

<!-- Profile -->
<div class="overlay" id="profileOverlay" onclick="if(event.target===this)closeDialog('profileOverlay')">
  <div class="dlg" onclick="event.stopPropagation()">
    <h2 class="dlg-title">Profile</h2>
    <p class="dlg-sub">Who's designing these cards. Kept in this spreadsheet's <b>layouts</b> tab.</p>
    <label class="mini" for="pfName">Name</label><input class="inp" id="pfName" maxlength="80">
    <label class="mini" for="pfEmail">Email</label><input class="inp" id="pfEmail" type="email" maxlength="120" placeholder="Used for your receipts when you subscribe">
    <label class="mini" for="pfStudio">Studio or company</label><input class="inp" id="pfStudio" maxlength="80">
    <div class="dlg-err" id="pfErr"></div>
    <div class="dlg-actions"><button class="attr-btn" onclick="closeDialog('profileOverlay')">Cancel</button><button class="print-go" id="pfSave" onclick="saveProfile()">Save</button></div>
  </div>
</div>

<!-- Settings -->
<div class="overlay" id="settingsOverlay" onclick="if(event.target===this)closeDialog('settingsOverlay')">
  <div class="dlg" onclick="event.stopPropagation()">
    <h2 class="dlg-title">Settings</h2>
    <span class="lbl">Spreadsheet</span>
    <div class="set-sheet"><b id="setSheetTitle"></b><button class="attr-btn" onclick="openInSheets()">Open in Sheets ↗</button></div>
    <p class="dlg-sub" style="margin-top:.35rem">CardBoard reads and writes it as <code>editor@zapsheets-480701.iam.gserviceaccount.com</code> — keep it shared with that address as an Editor.</p>
    <span class="lbl">Measurements</span>
    <div class="seg" id="setUnits"><button type="button" data-u="in" onclick="setUnitsChoice('in')">Inches</button><button type="button" data-u="mm" onclick="setUnitsChoice('mm')">Millimetres</button></div>
    <p class="dlg-sub" style="margin-top:.35rem">Used for the card size. Positions on the card stay in points (1/72 in).</p>
    <span class="lbl">Plan</span>
    <div class="set-plan"><span id="setPlan">—</span><button class="attr-btn" onclick="closeDialog('settingsOverlay');openPlansDialog()">Plans</button></div>
    <div class="dlg-err" id="setErr"></div>
    <div class="dlg-actions"><button class="attr-btn" onclick="closeDialog('settingsOverlay')">Close</button></div>
  </div>
</div>

<!-- Plans -->
<div class="overlay" id="plansOverlay" onclick="if(event.target===this)closeDialog('plansOverlay')">
  <div class="dlg" style="width:min(540px,94vw);text-align:center" onclick="event.stopPropagation()">
    <h2 class="plan-title"><img src="images/cardboard_icon_180.png" alt="">CardBoard plans</h2>
    <div class="plan-current" id="planCurrent"></div>
    <p class="dlg-sub" id="planIntro" style="margin:.3rem 0 .9rem">Pick the plan that fits how you work.</p>
    <div class="plan-grid">
      <div class="plan-card" id="planFree">
        <div class="plan-name">Free</div>
        <div class="plan-price">$0</div>
        <ul>
          <li>Card designer, fronts &amp; backs</li>
          <li>Edit your sheet's data</li>
          <li>Print sheets &amp; downloads for TTS and Screentop</li>
          <li>Pictures from Dropbox, Drive or the web</li>
        </ul>
      </div>
      <div class="plan-card plan-pro" id="planPro">
        <div class="plan-name">Pro</div>
        <div class="plan-price">$4.99<span>/month</span></div>
        <ul>
          <li>Everything in Free</li>
          <li>Hosted links for every export — Tabletop Simulator, Screentop and card images — that update when your cards change</li>
          <li>Upload pictures to CardBoard</li>
          <li>Tags: <code>{energy}</code> becomes an icon or word on every card</li>
        </ul>
        <button class="plan-btn" id="planProBtn" onclick="startProCheckout()">Choose Pro</button>
      </div>
    </div>
    <div class="dlg-sub" id="planStatus" style="margin:.8rem 0 .2rem"></div>
    <div id="planActKey"></div>
    <div class="dlg-actions" style="justify-content:center">
      <button class="attr-btn" onclick="closeDialog('plansOverlay')">Close</button>
      <button class="plan-manage" id="planManage" style="display:none" onclick="openBillingPortal()">Manage Billing</button>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
var APP_BASE = document.querySelector('base').getAttribute('href');
var SHEET_ID = <?= json_encode($_sheet_id) ?>;
</script>
<script>
<?php include __DIR__ . '/cardboard.js'; ?>
</script>
</body>
</html>
