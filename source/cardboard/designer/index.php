<?php
/**
 * CardBoard — card designer.
 * URL: /{id}/cardboard  or  /sheets/{id}/cardboard
 *
 * Pick a tab of the sheet (one row per card, one column per field), drag fields onto the card,
 * set their attributes, then print sheets of cards. Layouts are saved in the sheet's "cardboard" tab.
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
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link rel="icon" type="image/png" sizes="32x32" href="images/cardboard_icon_32.png" />
<link rel="icon" type="image/png" sizes="16x16" href="images/cardboard_icon_16.png" />
<link rel="apple-touch-icon" sizes="180x180" href="images/cardboard_icon_180.png" />
<meta name="apple-mobile-web-app-title" content="CardBoard" />
<title>CardBoard</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bangers&family=Cinzel:wght@500;700&family=Lilita+One&family=Merriweather:ital,wght@0,400;0,700;1,400&family=Roboto+Condensed:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
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
.panel-right { width:280px; flex-shrink:0; border-right:none; border-left:1px solid #e1e4ee; }
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
.el-img { width:100%; height:100%; background-repeat:no-repeat; background-position:center; }
.el-empty { color:#b8bccc; font-style:italic; }
/* designer-only: selection + handles (sized against the zoom with --z) */
.designing .el { cursor:move; outline:calc(1px / var(--z)) dashed transparent; overflow:visible; }
.designing .el > .el-inner { overflow:hidden; width:100%; height:100%; display:flex; flex-direction:column; justify-content:inherit; }
.designing .el:hover { outline-color:rgba(75,63,168,.45); }
.designing .el.sel { outline:calc(1.5px / var(--z)) solid #4b3fa8; z-index:50; }
.hdl { position:absolute; width:calc(9px / var(--z)); height:calc(9px / var(--z)); background:#fff; border:calc(1.5px / var(--z)) solid #4b3fa8; border-radius:calc(2px / var(--z)); display:none; z-index:60; }
.el.sel .hdl { display:block; }
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
#view-print { flex-direction:column; }
.print-bar { display:flex; align-items:flex-end; gap:.8rem; flex-wrap:wrap; padding:.8rem 1.1rem; background:#fff; border-bottom:1px solid #e1e4ee; }
.print-bar .grp { display:flex; flex-direction:column; gap:.2rem; }
.print-bar .sel { width:auto; min-width:7.5rem; }
.print-go { height:2.05rem; border:none; border-radius:7px; background:#4b3fa8; color:#fff; font-family:'DINBlack',sans-serif; font-size:.72rem; letter-spacing:.08em; text-transform:uppercase; padding:0 1.2rem; cursor:pointer; margin-left:auto; }
.print-info { font-size:.8rem; color:#6b7090; align-self:center; }
.pages { flex:1; overflow:auto; padding:1.5rem; display:flex; flex-direction:column; align-items:center; gap:1.5rem; background:#e4e6ef; }
.page-holder { position:relative; flex-shrink:0; box-shadow:0 6px 24px rgba(20,20,60,.18); background:#fff; }
.page { position:absolute; left:0; top:0; background:#fff; transform-origin:0 0; overflow:hidden; }
.page .card { box-shadow:none; }
.cut { position:absolute; background:#9aa0b8; }

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
  .top-bar, .print-bar, #view-design, .toast { display:none !important; }
  #view-print { display:block !important; }
  .pages { display:block; padding:0; background:#fff; overflow:visible; }
  .page-holder { box-shadow:none; width:auto !important; height:auto !important; break-after:page; page-break-after:always; }
  .page-holder:last-child { break-after:auto; page-break-after:auto; }
  .page { position:relative; transform:none !important; }
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
      <button class="top-tab active" id="tabDesign" onclick="switchTab('design')">Design</button>
      <button class="top-tab" id="tabPrint" onclick="switchTab('print')">Print</button>
    </div>
    <div class="account-menu-wrap">
      <button class="top-btn" onclick="toggleAccountMenu(event)" title="Menu">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="7.5" r="4.5"/><path d="M3.5 21c0-4.14 3.81-7.5 8.5-7.5s8.5 3.36 8.5 7.5"/></svg>
      </button>
      <div class="account-menu" id="accountMenu">
        <button class="account-menu-item" onclick="closeAccountMenu();reloadSheet()">Reload Sheet</button>
        <button class="account-menu-item" onclick="closeAccountMenu();openInSheets()">Open in Sheets</button>
        <button class="account-menu-item" onclick="closeAccountMenu();switchTab('print')">Print Cards</button>
        <hr class="account-menu-divider" />
        <button class="account-menu-item" onclick="closeAccountMenu();startDonation()" style="color:#e2557a">Donate</button>
      </div>
    </div>
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
    <span class="print-info" id="printInfo"></span>
    <button class="print-go" onclick="window.print()">Print</button>
  </div>
  <div class="pages" id="pages"></div>
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
