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
// The spreadsheet's name (kept by push/cardboardData.php): the page title and the Home Screen name
$_cb_title = $_sheet_id !== '' ? trim((string) @file_get_contents(dirname(__DIR__, 3) . '/sheets/' . preg_replace('/[^A-Za-z0-9_\-]/', '', $_sheet_id) . '/_cardboard_title.txt')) : '';
$_cb_name  = $_cb_title !== '' ? $_cb_title : 'CardBoard';
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
<meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($_cb_name, ENT_QUOTES) ?>" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="mobile-web-app-capable" content="yes" />
<link rel="manifest" id="cbManifest" href="manifest.php?app=cardboard&amp;id=<?= urlencode($_sheet_id) ?>&amp;base=<?= urlencode($_base) ?>" />
<title><?= htmlspecialchars($_cb_title !== '' ? $_cb_title : 'Loading…', ENT_QUOTES) ?></title>
<?php require_once dirname(__DIR__, 3) . '/push/linkPreview.php'; echo gbLinkPreview('cardboard', $_sheet_id); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bangers&family=Cinzel:wght@500;700&family=Lilita+One&family=Merriweather:ital,wght@0,400;0,700;1,400&family=Roboto+Condensed:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet" crossorigin="anonymous">
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2') format('woff2'),url('fonts/DINBlack.ttf'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf'); }
*, *::before, *::after { box-sizing:border-box; }
html, body { height:100%; }
/* footer */
.zs-footer { text-align:center; font-size:.72rem; line-height:1.6; color:#9aa0b8; padding:1.4rem 1rem 2.4rem; }
.zs-footer a { color:inherit; text-decoration:none; } .zs-footer a:hover { color:#555; text-decoration:underline; }
.zs-footer .sep { margin:0 .45rem; opacity:.6; }
@media print { .zs-footer { display:none; } }
.zs-footer.cb-footer { flex-shrink:0; padding:.35rem 1rem .45rem; font-size:.68rem; background:#fff; border-top:1px solid #e1e4ee; }
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
.account-menu-wrap { position:relative; flex-shrink:0; display:flex; align-items:center; gap:.55rem; }
/* Save (top right): everything kept in this browser goes to the sheet in the background */
.top-save { display:inline-flex; align-items:center; gap:.4rem; height:2.1rem; padding:0 .95rem; border:none; border-radius:999px; background:#f2c14e; color:#2e2763; font-family:'DINBlack',sans-serif; font-size:.7rem; letter-spacing:.09em; text-transform:uppercase; cursor:pointer; box-shadow:0 2px 8px rgba(0,0,0,.18); transition:background .15s, opacity .15s; }
.top-save:hover:not(:disabled) { background:#ffd36b; }
.top-save:disabled { background:rgba(255,255,255,.14); color:rgba(255,255,255,.55); box-shadow:none; cursor:default; }
.top-save { position:relative; overflow:hidden; width:6.6rem; padding:0; justify-content:center; }   /* the same size saving or not */
.top-save .sv-txt { position:relative; display:inline-flex; align-items:center; gap:.4rem; }
.top-save .sv-spin { width:.8rem; height:.8rem; border:2px solid rgba(46,39,99,.25); border-top-color:#2e2763; border-radius:50%; animation:spin .7s linear infinite; }
/* Saving in the background: the button fills from left to right, with moving stripes so it's clearly busy */
.top-save.saving:disabled { background:#fbeec4; color:#2e2763; box-shadow:0 2px 8px rgba(0,0,0,.18); }
.top-save .sv-fill { position:absolute; left:0; top:0; bottom:0; width:var(--sv, 8%); background:#f2c14e linear-gradient(-45deg, rgba(255,255,255,.28) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.28) 50%, rgba(255,255,255,.28) 75%, transparent 75%) 0 0 / 14px 14px; animation:svStripes .6s linear infinite; transition:width .35s ease; }
.top-save.saving .sv-txt { color:#2e2763; }
@keyframes svStripes { to { background-position:14px 0; } }
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
.ly-head { display:flex; align-items:center; gap:.45rem; padding:0 2.4rem 0 .2rem; margin-bottom:.45rem; font-size:.72rem; color:#8a8fa8; min-height:1.9rem; }
/* The heading (with Group / Ungroup) stays at the top while the list scrolls, like the layers button */
.layers-panel .ly-head { position:sticky; top:-.75rem; z-index:4; background:#fbfbfe; margin:-.75rem -.7rem .45rem; padding:.75rem 3.1rem .45rem .9rem; box-shadow:0 1px 0 #eceef5; }
.ly-head b { position:relative; top:-.2em; font-family:'DINBlack',sans-serif; font-weight:400; letter-spacing:.09em; text-transform:uppercase; color:#2e2763; font-size:.66rem; }
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
/* Our own chevron, centred and inset like the sheet picker's, instead of the browser's */
select.sel { -webkit-appearance:none; appearance:none; padding-right:2rem; background:#fff url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%236b7090' stroke-width='2.4' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E") no-repeat right .6rem center / 14px 14px; cursor:pointer; text-overflow:ellipsis; }
.sel:focus, .inp:focus, .ta:focus { outline:none; border-color:#4b3fa8; box-shadow:0 0 0 2px rgba(75,63,168,.15); }
.sel-sheet { font-family:'DINBlack',sans-serif; text-transform:uppercase; letter-spacing:.05em; font-size:.78rem; }
.sheet-combo { position:relative; }
.sheet-combo.compact { width:15rem; }
.sheet-combo .inp { width:100%; padding-right:2rem; cursor:pointer; }
.sheet-combo.has-layout .inp { padding-left:2rem; }
.sheet-combo .inp { padding-right:3.6rem; }
.sc-count { position:absolute; right:2.1rem; top:50%; transform:translateY(calc(-50% + .14em));   /* DINBlack in the box sits low: line the count up with it */ pointer-events:none; font-family:'DINRegular',Arial,sans-serif; font-size:.72rem; letter-spacing:0; text-transform:none; color:#9aa0b8; white-space:nowrap; }
.sheet-combo.open .sc-count { display:none; }
.sheet-combo.open .inp { cursor:text; border-color:#7b6cf6; box-shadow:0 0 0 3px rgba(123,108,246,.15); }
.sc-ico { position:absolute; left:.6rem; top:50%; transform:translateY(-50%); color:#7b6cf6; display:flex; pointer-events:none; }
.sc-caret { position:absolute; right:.25rem; top:50%; transform:translateY(-50%); width:1.6rem; height:1.6rem; border:none; background:none; color:#6b7090; display:flex; align-items:center; justify-content:center; cursor:pointer; padding:0; }
.sheet-combo.open .sc-caret svg { transform:rotate(180deg); }
.sc-list { display:none; position:absolute; left:0; right:0; top:calc(100% + 4px); z-index:300; max-height:320px; overflow:auto; background:#fff; border:1px solid #e1e4ee; border-radius:9px; box-shadow:0 12px 32px rgba(20,20,60,.18); padding:.3rem; }
.sheet-combo.compact .sc-list { min-width:16rem; }
.sheet-combo.open .sc-list { display:block; }
.sc-item { display:flex; align-items:center; gap:.5rem; padding:.42rem .55rem; border-radius:6px; cursor:pointer; font-family:'DINBlack',sans-serif; text-transform:uppercase; letter-spacing:.05em; font-size:.74rem; color:#2e2763; }
.sc-item.hi { background:#f3f1ff; }
.sc-item.cur { color:#4b3fa8; }
.sc-mark { width:16px; flex-shrink:0; display:flex; color:#7b6cf6; }
.sc-name { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.sc-check { color:#7b6cf6; }
.sc-none { padding:.5rem .6rem; font-size:.78rem; color:#9aa0b8; }
/* New deck: the last entry in the list, then naming it in the box */
.sc-item.sc-new { color:#4b3fa8; border-top:1px solid #eceef5; margin-top:.2rem; padding-top:.5rem; border-radius:0 0 6px 6px; }
.sc-item.sc-new .sc-mark { justify-content:center; font-size:1rem; line-height:1; }
.sc-pend { font-family:'DINRegular',Arial,sans-serif; font-size:.62rem; text-transform:none; letter-spacing:0; color:#c07a00; background:#fff4dc; border-radius:999px; padding:0 .4rem; }
.sc-newhint { text-transform:none; letter-spacing:0; line-height:1.45; }
.sc-newhint.bad { color:#c0392b; }
.sheet-combo.naming .inp { border-color:#7b6cf6; }
.sheet-combo.naming .sc-ico, .sheet-combo.naming .sc-count { display:none; }
.sheet-combo.naming.has-layout .inp { padding-left:.5rem; }
.field-chip { display:flex; align-items:center; gap:.45rem; border:1.5px solid #d6d9e6; border-radius:7px; padding:.38rem .55rem; margin-bottom:.35rem; background:#fff; cursor:grab; font-family:'DINBlack',sans-serif; font-size:.74rem; letter-spacing:.03em; color:#2e2763; user-select:none; transition:border-color .12s, background .12s; }
.field-chip:hover { border-color:#a99cff; background:#f7f5ff; }
.field-chip .ico { width:16px; text-align:center; color:#a0a4bb; font-family:'DINRegular',sans-serif; flex-shrink:0; }
.field-chip .nm { position:relative; top:-.22em; line-height:1.5;   /* DINBlack draws low; lift it level with the icon and the value */
  flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.field-chip .sample { font-family:'DINRegular',sans-serif; font-size:.68rem; color:#9aa0b8; max-width:70px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.field-chip.static { color:#555; }
/* Reordering the Data fields: a line where the dragged one will go */
.field-chip[data-field] { position:relative; }
.field-chip.dragging { opacity:.4; }
.field-chip.drop-before::before, .field-chip.drop-after::after { content:''; position:absolute; left:-2px; right:-2px; height:2px; background:#7b6cf6; border-radius:2px; pointer-events:none; }
.field-chip.drop-before::before { top:calc(-.175rem - 2.5px); }
.field-chip.drop-after::after { bottom:calc(-.175rem - 2.5px); }
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
.th-rename { width:100%; height:1.55rem; border:1.5px solid #7b6cf6; border-radius:5px; padding:0 .35rem; font:inherit; letter-spacing:inherit; color:#2e2763; background:#fff; outline:none; text-transform:none; }
table.grid thead th.renaming { padding:0 .25rem; background:#e6e1ff; }
table.grid thead th.renaming .th-caret, table.grid thead th.renaming .col-rs { display:none; }
table.grid tbody td.rn { cursor:pointer; }
table.grid tbody td.rn:hover, table.grid tbody td.rn.menu-open { background:#e6e1ff; color:#4b3fa8; }
.col-menu button.danger { color:#d4373d; }
table.grid thead th.ghost-col { cursor:text; }
#rowMenu .row-prev { margin:.15rem auto .5rem; overflow:hidden; border-radius:6px; box-shadow:0 2px 8px rgba(20,20,60,.18); }
/* A little card with its number (Cards view rows, Layout view card picker) */
.card-no { display:inline-flex; align-items:center; justify-content:center; min-width:1.15rem; height:1.55rem; padding:0 .2rem; box-sizing:border-box; border:1.5px solid currentColor; border-radius:3px; background:#fff; font-family:'DINBlack',sans-serif; font-size:.62rem; line-height:1; letter-spacing:0; color:inherit; vertical-align:middle; }
table.grid tbody td.rn .card-no { display:flex; margin:0 auto; width:fit-content; }
table.grid tbody td.rn:hover .card-no, table.grid tbody td.rn.menu-open .card-no { background:#f7f5ff; }
#rowInfo { display:inline-flex; align-items:center; justify-content:center; min-width:0; color:#4b3fa8; }
.col-menu-name {font-family:'DINBlack',sans-serif; font-size:.68rem; letter-spacing:.06em; text-transform:uppercase; color:#8a8fa8; padding:.1rem .2rem .4rem; }
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
/* Picture button on image elements (like BillBoard's) */
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
.map-list { display:flex; flex-direction:column; gap:.8rem; margin-top:.6rem; }
.map-item .map-val { margin-top:.35rem; }
.map-item.new .map-val { border-style:dashed; }
.map-item.new .map-slot:not(:has(.el-img)) { opacity:.75; }
.map-slot.cur { border-style:solid; border-color:#7b6cf6; box-shadow:0 0 0 3px rgba(123,108,246,.15); }
.map-x { position:absolute; top:6px; left:6px; z-index:2; width:28px; height:28px; padding:0; border:none; border-radius:50%; background:rgba(0,0,0,.55); color:#fff; font-size:1.05rem; line-height:28px; text-align:center; cursor:pointer; }
.map-x:hover { background:#c0392b; }
/* Data mapping to text: value → text rows */
.map-list.map-text { gap:.35rem; margin-top:0; }
.map-trow { display:grid; grid-template-columns:minmax(0,1fr) auto minmax(0,1fr) 1.5rem; align-items:center; gap:.3rem; border-radius:8px; }
.map-trow .inp { min-width:0; }
.map-trow.cur .map-val { border-color:#a99cff; background:#f7f5ff; }
.map-trow.new .inp { border-style:dashed; }
.map-arrow { color:#a0a4bb; font-size:.8rem; }
.map-tx { width:1.5rem; height:1.5rem; padding:0; border:none; border-radius:50%; background:none; color:#a0a4bb; font-size:1rem; line-height:1.5rem; cursor:pointer; }
.map-tx:hover { background:#fdecea; color:#c0392b; }
.map-out-wrap { position:relative; min-width:0; }
.map-out-wrap .map-out { width:100%; padding-right:1.9rem; }
.map-img-btn { position:absolute; right:3px; top:50%; transform:translateY(-50%); width:1.6rem; height:1.6rem; padding:0; border:none; border-radius:5px; background:none; color:#a0a4bb; display:flex; align-items:center; justify-content:center; cursor:pointer; }
.map-img-btn:hover, .map-img-btn.over { background:#ece9ff; color:#4b3fa8; }
.map-pic { display:flex; align-items:center; gap:.35rem; min-width:0; height:2.05rem; padding:0 .25rem 0 .2rem; border:1.5px solid #d6d9e6; border-radius:7px; background:#fff; cursor:pointer; }
.map-pic:hover, .map-pic.over { border-color:#a99cff; background:#f7f5ff; }
.map-thumb { width:1.6rem; height:1.6rem; flex-shrink:0; border-radius:4px; background:#eef0f6 center/contain no-repeat; display:flex; align-items:center; justify-content:center; color:#a0a4bb; }
.map-pic-name { flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-size:.74rem; color:#6b7090; }
.map-pic-x { width:1.3rem; height:1.3rem; padding:0; border:none; border-radius:50%; background:none; color:#a0a4bb; cursor:pointer; line-height:1.3rem; }
.map-pic-x:hover { background:#fdecea; color:#c0392b; }
.map-trow.cur .map-pic { border-color:#a99cff; }
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
.tb-btn.tb-add { color:#4b3fa8; }
#rowInfo .card-no { cursor:text; } #rowInfo .card-no:hover { background:#f7f5ff; }
.card-no-in { height:1.55rem; box-sizing:border-box; border:1.5px solid #5b4fd6; border-radius:5px; padding:0 .2rem; text-align:center; font:inherit; font-family:'DINBlack',sans-serif; font-size:.78rem; color:#2e2763; outline:none; box-shadow:0 0 0 3px #ece9ff; }
.card-no.card-add { font-size:.9rem; font-family:'DINRegular',Arial,sans-serif; border-style:dashed; background:none; }
.tb-btn.tb-add:hover .card-no.card-add { border-style:solid; background:#f7f5ff; }
.tb-txt { font-size:.78rem; color:#6b7090; min-width:2.6rem; text-align:center; }
.tb-sep { width:1px; height:1.3rem; background:#dde0ea; margin:0 .2rem; }
.tb-save { margin-left:auto; height:1.9rem; border:none; border-radius:7px; background:#4b3fa8; color:#fff; font-family:'DINBlack',sans-serif; font-size:.68rem; letter-spacing:.08em; text-transform:uppercase; padding:0 1rem; cursor:pointer; }
.tb-save:disabled { background:#c9c4ea; cursor:default; }
.tb-status { font-size:.74rem; color:#9aa0b8; }
.tb-status.dirty { color:#c2570c; }
.tb-statuses { margin-left:auto; display:flex; align-items:center; gap:.9rem; }
.tb-loading { display:inline-flex; align-items:center; gap:.4rem; color:#6b62b8; }
.tb-loading[hidden] { display:none; }
.tb-loading::before { content:''; width:.75rem; height:.75rem; border:2px solid #d9d3ff; border-top-color:#7b6cf6; border-radius:50%; animation:spin .7s linear infinite; }
.canvas-wrap { flex:1; min-height:0; overflow:auto; display:flex; align-items:flex-start; justify-content:center; padding:2.2rem;
  background-color:#e4e6ef; background-image:radial-gradient(#cfd2df 1px, transparent 1px); background-size:16px 16px; }
.card-holder { position:relative; flex-shrink:0; }
.card-holder.drop-target .card { box-shadow:0 0 0 3px #a99cff, 0 10px 30px rgba(20,20,60,.22); }

/* ── Cards (shared by designer + print) — all sizes in pt ── */
.card { position:absolute; left:0; top:0; transform-origin:0 0; overflow:hidden; background:#fff; box-shadow:0 10px 30px rgba(20,20,60,.22); }
.el { position:absolute; overflow:hidden; display:flex; flex-direction:column; white-space:pre-wrap; word-break:break-word; line-height:1.15; }
.el.v-middle { justify-content:center; } .el.v-bottom { justify-content:flex-end; }
/* A group set out as a list: its members flow side by side (or one under another), each as big as its contents need */
.el-list { position:absolute; display:flex; }
.el-list.list-h { flex-direction:row; align-items:center; }
.el-list.list-v { flex-direction:column; align-items:flex-start; }
.el-list > .el.in-list { position:relative; left:auto !important; top:auto !important; flex:0 0 auto; }
.designing .el-list.picked { outline:calc(1.5px / var(--z)) dashed #4b3fa8; outline-offset:calc(2px / var(--z)); z-index:50 !important; }
.arr-row { display:flex; gap:.45rem; align-items:stretch; }
.arr-row .seg button { display:flex; align-items:center; justify-content:center; height:2.1rem; padding:0 .55rem; }
.arr-free { flex:0 0 auto; }
.arr-list { flex:1; display:flex; align-items:center; border:1.5px solid #d6d9e6; border-radius:7px; overflow:hidden; background:#fff; }
.arr-list.on { border-color:#a99cff; }
.arr-list .seg { border:none; border-radius:0; flex:0 0 auto; }
.arr-list .seg button { border-left:1px solid #e3e5ee; }
.arr-list .seg button:first-child { border-left:none; }
.arr-gap { flex:1; display:flex; align-items:center; gap:.35rem; padding:0 .45rem; border-left:1px solid #e3e5ee; height:100%; min-width:0; }
.arr-gap span { font-family:'DINBlack',sans-serif; font-size:.62rem; letter-spacing:.08em; text-transform:uppercase; color:#8a8fa8; }
.arr-gap .inp { height:1.6rem; padding:0 .35rem; min-width:0; width:100%; }
.arr-gap .inp:disabled { opacity:.45; }
.arr-list:not(.on) .arr-gap span { opacity:.5; }
/* List buttons: press and hold for the alignments */
.arr-hold { position:relative; touch-action:none; -webkit-user-select:none; user-select:none; }
.arr-hold::after { content:''; position:absolute; right:3px; bottom:3px; border:3px solid transparent; border-right-color:#a0a4bb; border-bottom-color:#a0a4bb; }
.arr-hold.holding { background:#ece9ff; }
.arr-fly { position:fixed; z-index:800; display:flex; flex-direction:column; gap:2px; padding:.3rem; background:#fff; border:1px solid #d6d1f5; border-radius:9px; box-shadow:0 10px 28px rgba(20,20,60,.2); }
.arr-fly button { display:flex; align-items:center; gap:.5rem; padding:.35rem .7rem .35rem .45rem; border:none; border-radius:6px; background:none; color:#2e2763; font-size:.78rem; cursor:pointer; text-align:left; }
.arr-fly button.on { color:#4b3fa8; background:#f3f1ff; }
.arr-fly button.hi, .arr-fly button:hover { background:#ece9ff; }
.el-img { display:block; width:100%; height:100%; object-position:center; background-repeat:no-repeat; background-position:center; }
/* Picture still loading in the designer: a spinner over it (shown after a moment, so cached pictures don't flash) */
.img-spin { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; background:rgba(238,240,246,.85); pointer-events:none; opacity:0; animation:imgSpinIn .15s .2s forwards; }
.img-spin::after { content:''; width:calc(22px / var(--z, 1)); height:calc(22px / var(--z, 1)); border:calc(2.5px / var(--z, 1)) solid #d6d9e6; border-top-color:#7b6cf6; border-radius:50%; animation:spin .7s linear infinite; }
.img-spin.failed { background:#f6f1f1; animation:none; opacity:1; }
.img-spin.failed::after { content:'Picture didn’t load'; width:auto; height:auto; border:none; animation:none; color:#b0605a; font:600 calc(9px / var(--z, 1)) Inter,system-ui,sans-serif; text-align:center; }
@keyframes imgSpinIn { to { opacity:1; } }
img.el-img { -webkit-user-drag:none; user-select:none; }
/* Print card colors and fills even when "Background graphics" is off */
.card, .card * { -webkit-print-color-adjust:exact; print-color-adjust:exact; }
/* Flip button (top right of the card) */
.flip-btn { position:absolute; top:0; left:-3.1rem; width:2.5rem; height:2.5rem; border-radius:50%; border:1.5px solid #d6d9e6; background:#fff; color:#4b3fa8; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:0; cursor:pointer; box-shadow:0 3px 10px rgba(20,20,60,.12); padding:0; z-index:5; }
.flip-btn svg { width:18px; height:18px; }
.side-label { position:absolute; left:0; right:0; bottom:100%; margin-bottom:.45rem; text-align:left; font-family:'DINBlack',sans-serif; font-size:.68rem; letter-spacing:.14em; text-transform:uppercase; color:#6b62b8; pointer-events:none; }
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
/* The key object: the others line up with it */
.designing .el.picked.multi.key { outline:calc(2.5px / var(--z)) solid #7b6cf6; outline-offset:calc(1.5px / var(--z)); }
/* Align dots: shown past the card's right and bottom edges when several things are selected */
.al-guide { position:absolute; pointer-events:none; z-index:4; }
.al-h { height:0; border-top:1.5px dotted #4a4e68; }
.al-v { width:0; border-left:1.5px dotted #4a4e68; }
.al-guide.in { z-index:60; border-color:#7b6cf6; opacity:0; transition:opacity .12s; }
.al-dot { position:absolute; width:14px; height:14px; margin:-7px 0 0 -7px; padding:0; border-radius:50%; border:2px solid #fff; background:#2a2d3e; box-shadow:0 1px 4px rgba(20,20,60,.3); cursor:pointer; z-index:6; transition:transform .12s, background .12s; }
.al-dot:hover, .al-dot:focus-visible { background:#7b6cf6; transform:scale(1.3); outline:none; }
.al-dot:hover + .al-guide.in, .al-dot:focus-visible + .al-guide.in { opacity:1; }
.fill-btn { position:absolute; width:24px; height:24px; margin:-12px 0 0 -12px; padding:0; border-radius:50%; border:2px solid #fff; background:#2a2d3e; color:#fff; display:flex; align-items:center; justify-content:center; box-shadow:0 1px 5px rgba(20,20,60,.3); cursor:pointer; z-index:6; transition:transform .12s, background .12s; }
.fill-btn:hover, .fill-btn:focus-visible { background:#7b6cf6; transform:scale(1.15); outline:none; }
.moving .fill-btn { display:none; }
.moving .al-dot, .moving .al-guide { display:none; }
.el.editing { z-index:60 !important; }
.el.editing .el-inner { cursor:text; user-select:text; -webkit-user-select:text; white-space:pre-wrap; outline:calc(2px / var(--z, 1)) solid #7b6cf6; outline-offset:calc(1px / var(--z, 1)); background:rgba(255,255,255,.9); }
.el.editing .hdl { display:none; }
.el.as-image.editing .el-inner { font-family:'DINRegular',Arial,sans-serif; font-size:10pt; color:#1b1b2b; display:flex; align-items:center; justify-content:center; text-align:center; }
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
.hdl.rot { left:calc(50% - 5.5px / var(--z)); top:calc(-28px / var(--z)); width:calc(11px / var(--z)); height:calc(11px / var(--z)); border-radius:50%; cursor:grab; }
.hdl.rot::after { content:''; position:absolute; left:50%; top:100%; width:calc(1.5px / var(--z)); height:calc(16px / var(--z)); margin-left:calc(-.75px / var(--z)); background:#4b3fa8; }
.hdl.rot:active { cursor:grabbing; background:#7b6cf6; }
.el[data-angle]::before { content:attr(data-angle); position:absolute; left:50%; top:calc(-52px / var(--z)); transform:translateX(-50%); background:#2a2d3e; color:#fff; font:600 calc(11px / var(--z))/1.6 Inter,system-ui,sans-serif; padding:0 calc(6px / var(--z)); border-radius:calc(4px / var(--z)); z-index:70; white-space:nowrap; pointer-events:none; }
.moving .el.picked .hdl:not(.rot) { visibility:hidden; }
.card-empty-hint { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; text-align:center; padding:12pt; color:#b0b4c8; font-size:10pt; pointer-events:none; }

/* ── Attributes ────────────────────────────────────────── */
.attr-title { font-family:'DINBlack',sans-serif; font-size:.85rem; color:#2e2763; margin:0 0 .2rem; display:flex; align-items:center; gap:.4rem; }
.attr-title .kind { position:relative; top:.15rem; display:inline-flex; align-items:center; height:1.2rem; font-family:'DINRegular',sans-serif; font-size:.62rem; line-height:1; color:#fff; background:#a99cff; border-radius:999px; padding:.1rem .55rem 0; text-transform:uppercase; letter-spacing:.07em; }
.row2 { display:grid; grid-template-columns:1fr 1fr; gap:.45rem; }
.row4 { display:grid; grid-template-columns:repeat(4,1fr); gap:.35rem; }
.mini { display:block; font-size:.62rem; color:#9aa0b8; margin-bottom:.15rem; text-transform:uppercase; letter-spacing:.06em; }
.ta { width:100%; min-height:4.2rem; border:1.5px solid #d6d9e6; border-radius:7px; padding:.4rem .5rem; font-size:.82rem; resize:vertical; }
.seg { display:flex; border:1.5px solid #d6d9e6; border-radius:7px; overflow:hidden; }
.seg button { flex:1; height:1.9rem; border:none; background:#fff; color:#6b7090; cursor:pointer; font-size:.8rem; border-left:1px solid #e3e5ee; }
.seg button:first-child { border-left:none; }
.seg button.on { background:#ece9ff; color:#2e2763; font-weight:700; -webkit-text-stroke:.35px currentColor; }   /* DINBlack sits lower than DINMedium, so bold the same font instead */
.color-row { display:flex; gap:.35rem; align-items:center; }
.color-row input[type=color] { width:2.2rem; height:2.05rem; border:1.5px solid #d6d9e6; border-radius:7px; padding:2px; background:#fff; cursor:pointer; flex-shrink:0; }
.color-row .inp { flex:1; }
.swatch { width:2.2rem; height:2.05rem; flex-shrink:0; border:1.5px solid #d6d9e6; border-radius:7px; padding:3px; cursor:pointer; background:#fff; position:relative; }
.swatch::before { content:''; position:absolute; inset:3px; border-radius:4px; background:var(--c,#fff); box-shadow:inset 0 0 0 1px rgba(0,0,0,.12); }
.swatch:hover { border-color:#a99cff; }
.swatch.clear::before, .cp-tile.clear { background:repeating-conic-gradient(#e3e5ee 0 25%, #fff 0 50%) 0 0/8px 8px; }
.swatch.clear::after, .cp-tile.clear::after { content:''; position:absolute; left:-10%; right:-10%; top:50%; height:2px; background:#e5484d; transform:rotate(-45deg); }
.color-pop { position:fixed; z-index:760; width:232px; background:#fff; border:1px solid #e1e4ee; border-radius:12px; box-shadow:0 14px 40px rgba(20,20,60,.22); padding:.6rem; }
.color-pop .mini { margin:.55rem 0 .3rem; }
.cp-clear { display:flex; align-items:center; gap:.5rem; width:100%; border:1.5px solid #e5e3f0; border-radius:8px; background:#fff; padding:.3rem .45rem; margin-bottom:.5rem; font-size:.78rem; color:#2e2763; cursor:pointer; }
.cp-clear:hover { border-color:#a99cff; background:#f7f5ff; }
.cp-clear.on { border-color:#4b3fa8; background:#f3f1ff; }
.cp-grid { display:grid; grid-template-columns:repeat(6,1fr); gap:5px; }
.cp-tile { position:relative; overflow:hidden; display:block; width:100%; aspect-ratio:1; min-width:22px; border-radius:6px; border:none; padding:0; cursor:pointer; background:var(--c); box-shadow:inset 0 0 0 1px rgba(0,0,0,.14); }
.cp-clear .cp-tile { width:22px; height:22px; aspect-ratio:auto; flex-shrink:0; }
.cp-tile:hover { transform:scale(1.08); }
.cp-tile.on { box-shadow:0 0 0 2px #fff, 0 0 0 4px #4b3fa8; }
.cp-custom { margin-top:.55rem; }
.cp-more { display:flex; align-items:center; gap:.45rem; font-size:.78rem; color:#4b3fa8; cursor:pointer; }
.cp-more input { width:1.9rem; height:1.6rem; border:1.5px solid #d6d9e6; border-radius:6px; padding:1px; background:#fff; cursor:pointer; }
.attr-btns { display:flex; gap:.4rem; margin-top:1.1rem; flex-wrap:wrap; }
.act-grid { display:grid; grid-template-columns:1fr 1fr; gap:.4rem; }
.act-grid .wide { grid-column:1 / -1; }
.attr-btn:disabled { opacity:.45; cursor:default; }
.attr-btn { flex:1; height:2rem; border:1.5px solid #d6d9e6; border-radius:7px; background:#fff; color:#3b3f5c; font-size:.75rem; cursor:pointer; white-space:nowrap; }
.attr-btn:hover { border-color:#a99cff; }
.attr-btn.danger { color:#c0392b; border-color:#f0c7c2; }
.attr-btn.danger:hover { background:#fff3f2; }
.insert-row { display:flex; gap:.35rem; margin-top:.35rem; }

/* ── Print view ────────────────────────────────────────── */
#view-print { flex-direction:row; }
/* Print: settings on the left, pages on the right */
#view-print .print-bar { width:220px; flex-shrink:0; flex-direction:column; align-items:stretch; gap:.65rem; overflow:auto; border-bottom:none; border-right:1px solid #e1e4ee; background:#fbfbfe; }
#view-print .print-bar .sel { width:100%; }
#view-print .print-info { align-self:flex-start; margin-top:.2rem; line-height:1.45; }
#view-print .print-go { margin-left:0; width:100%; }
#view-print .pages { min-width:0; }
@media (max-width:900px) { #view-print { flex-direction:column; } #view-print .print-bar { width:auto; border-right:none; border-bottom:1px solid #e1e4ee; } }
#view-export { flex-direction:row; }
#view-data { flex-direction:column; }
/* Tags on cards: an inline picture the height of the text */
.el .tx { display:block; }
/* A tag picture is as tall as the letters reach — from the top of the capitals to the bottom of descenders like y.
   It's placed from the baseline (font files disagree wildly about line boxes), and the negative margins keep it
   from pushing the lines apart. */
.tag-img { display:inline-block; height:1.05em; width:auto; vertical-align:baseline; margin:-.83em .04em -.22em; }
/* Tags view */
#view-tags { overflow:auto; background:#f4f3fa; scroll-padding-top:130px; }
.tags-wrap { width:100%; max-width:1000px; margin:0 auto; padding:0 1.4rem 3rem; }
.tags-stick { position:sticky; top:0; z-index:5; background:#f4f3fa; margin:0 -.6rem; padding:1rem .6rem 0; }
.tags-head { display:flex; align-items:center; gap:1rem; margin-bottom:.8rem; }
.tags-head > div:first-child { min-width:0; }
.tags-head .e-actions { align-items:center; flex-shrink:0; }
.tags-title { margin:0 0 .3rem; font-family:'DINBlack',sans-serif; font-weight:400; font-size:1.15rem; color:#2e2763; display:flex; align-items:center; gap:.5rem; }
.tags-sub { margin:0; font-size:.82rem; color:#6b7090; line-height:1.5; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
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
.export-side { width:280px; flex-shrink:0; display:flex; flex-direction:column; min-height:0; overflow:auto; background:#fbfbfe; border-right:1px solid #e1e4ee; }
#view-export .print-bar { flex-direction:column; align-items:stretch; gap:.65rem; border-bottom:1px solid #e1e4ee; }
#view-export .print-bar .grp .sel, #view-export .print-bar .grp .inp { width:100%; }
/* Get / Update links takes the room; Download is an icon */
#view-export .e-actions { display:flex; gap:.5rem; }
#view-export #publishGo { flex:1; white-space:nowrap; }
#view-export .dl-go { flex:0 0 auto; display:flex; align-items:center; justify-content:center; padding-left:.9rem; padding-right:.9rem; }
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
table.grid { border-collapse:separate; border-spacing:0; font-size:.8rem; color:#1b1b2b; table-layout:fixed; }
table.grid thead th[data-c] { position:sticky; padding-right:1.1rem; }
table.grid .th-name { overflow:hidden; text-overflow:ellipsis; }
table.grid thead th .th-caret { position:absolute; right:.55rem; top:50%; transform:translateY(-50%); margin:0; }
.col-rs { position:absolute; top:0; right:0; width:7px; height:100%; cursor:col-resize; z-index:2; }
.col-rs:hover, body.col-resizing .col-rs:hover { background:linear-gradient(90deg, transparent 4px, #7b6cf6 4px, #7b6cf6 6px, transparent 6px); }
body.col-resizing, body.col-resizing * { cursor:col-resize !important; user-select:none; }
body.col-moving, body.col-moving * { cursor:grabbing !important; user-select:none; }
/* Moving a card: drag its number; a line shows where it goes */
body.row-moving, body.row-moving * { cursor:grabbing !important; user-select:none; }
table.grid tbody td.rn { cursor:grab; }
table.grid tr.row-src td { opacity:.45; }
.row-marker { position:absolute; height:3px; background:#7b6cf6; border-radius:2px; z-index:5; pointer-events:none; box-shadow:0 0 0 2px rgba(123,108,246,.2); }
table.grid thead th.col-src { background:#ddd6ff; color:#7b6cf6; }
.col-marker { position:absolute; width:3px; background:#7b6cf6; border-radius:2px; z-index:6; pointer-events:none; box-shadow:0 0 0 2px rgba(123,108,246,.18); }
.col-ghost { position:fixed; z-index:9999; pointer-events:none; padding:.3rem .6rem; border-radius:6px; background:#2e2763; color:#fff; font-family:'DINBlack',sans-serif; font-size:.72rem; letter-spacing:.03em; text-transform:uppercase; box-shadow:0 6px 18px rgba(30,20,80,.3); }
table.grid th, table.grid td { border-right:1px solid #e6e8f0; border-bottom:1px solid #e6e8f0; padding:0 .5rem; height:2rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; text-align:left; vertical-align:middle; }
table.grid thead th { position:sticky; top:0; z-index:3; background:#f3f1ff; color:#2e2763; font-family:'DINBlack',sans-serif; font-weight:400; font-size:.72rem; letter-spacing:.03em; border-bottom:1.5px solid #d6d1f5; }
table.grid .rn { position:sticky; left:0; z-index:2; min-width:2.6rem; width:2.6rem; text-align:center; background:#f7f8fc; color:#9aa0b8; font-size:.7rem; }
table.grid thead .rn { z-index:4; background:#ece9ff; }
table.grid td { cursor:cell; background:#fff; }
table.grid td > span { vertical-align:middle; }
table.grid td .tag-img { height:1.15em; width:auto; vertical-align:-.22em; margin:0 .05em; }
/* Find: the found text is marked; Replace slides out to the right of Find */
table.grid td img.g-hit-img { background:#ffe37a; border-radius:3px; box-shadow:0 0 0 2px #ffe37a, 0 0 0 3px #f2c14e; }
table.grid td mark.g-hit { background:#ffe37a; color:inherit; border-radius:2px; padding:0 1px; box-shadow:0 0 0 1px #f2c14e; }
.grid-replace { max-width:0; opacity:0; overflow:hidden; transform:translateX(-14px); pointer-events:none; transition:max-width .28s ease, opacity .2s ease, transform .28s ease; white-space:nowrap; }
.grid-replace.open { max-width:26rem; opacity:1; transform:none; pointer-events:auto; }
.grid-replace .gr-row { display:flex; gap:.4rem; align-items:center; }
/* A text box with an × to clear it, shown once something is typed */
.clr-wrap { position:relative; display:inline-block; }
.clr-wrap .inp { padding-right:1.9rem; }
.clr-x { position:absolute; right:.35rem; top:50%; transform:translateY(-50%); width:1.3rem; height:1.3rem; border:none; border-radius:50%; background:#e4e6ef; color:#5a607a; font-size:.95rem; line-height:1; cursor:pointer; display:none; align-items:center; justify-content:center; padding:0; }
.clr-x:hover { background:#d3d6e4; color:#1b1d2b; }
.clr-wrap.has .clr-x { display:flex; }
.grid-replace .inp { width:12rem; }
.grid-replace .print-go { white-space:nowrap; }
table.grid td .g-formula { color:#4b3fa8; font-family:ui-monospace,Menlo,monospace; font-size:.74rem; }
table.grid td.sel { box-shadow:inset 0 0 0 2px #4b3fa8; }
table.grid td.st-saving { background:#fffbea; }
table.grid td.st-saved { background:#ecfdf3; transition:background .8s; }
table.grid td.st-error { background:#fef2f2; box-shadow:inset 0 0 0 1.5px #e5484d; }
table.grid tbody tr:hover td:not(.sel) { background-color:#fafaff; }
.g-mapv { color:#6b7090; }
.g-arrow { color:#a99cff; margin:0 .35rem; }
.g-thumb.g-mapimg { margin-right:0; }
.g-thumb { width:22px; height:22px; object-fit:cover; border-radius:4px; vertical-align:middle; margin-right:.4rem; background:#eef0f6; }
/* While a picture loads: a spinner in its place; one that doesn't load: a crossed-out picture */
.g-thumb.ld { background:#eef0f6 url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Ccircle cx='12' cy='12' r='8' fill='none' stroke='%23c9c5ef' stroke-width='3'/%3E%3Cpath d='M12 4a8 8 0 0 1 8 8' fill='none' stroke='%235b4fd6' stroke-width='3' stroke-linecap='round'%3E%3CanimateTransform attributeName='transform' type='rotate' from='0 12 12' to='360 12 12' dur='.8s' repeatCount='indefinite'/%3E%3C/path%3E%3C/svg%3E") center / 16px no-repeat; }
.g-thumb.bad { background:#fdeceb url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23c0392b' stroke-width='2'%3E%3Crect x='4' y='4' width='16' height='16' rx='2'/%3E%3Cpath d='M4 20 20 4'/%3E%3C/svg%3E") center / 14px no-repeat; }
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
.lock-bar { display:flex; align-items:center; justify-content:center; gap:1rem; flex-wrap:wrap; padding:.55rem 1rem; background:#fff7e6; border-bottom:1px solid #f5d98c; color:#5c4a12; font-size:.82rem; flex-shrink:0; }
.lock-bar[hidden] { display:none; }
.lock-bar .plan-btn { width:auto; padding:.45rem 1rem; }
body.cb-locked .tags-stick .print-go:not(.ghost), body.cb-locked #exportGo, body.cb-locked #publishGo { opacity:.5; pointer-events:none; }
body.cb-locked #cardHolder .el { cursor:default; }
body.cb-locked #cardHolder [data-h] { display:none; }
/* read only: the attributes show, but nothing in them can be clicked or typed into (the panel still scrolls) */
body.cb-locked #attrPanel * { pointer-events:none !important; }
body.cb-locked #attrPanel :is(input,select,textarea,button):disabled { opacity:1; color:inherit; }
body.cb-locked #attrPanel .act-grid, body.cb-locked #attrPanel .lbl:has(+ .act-grid) { display:none; }
body.cb-locked #saveBtn { display:none; }
.pf-email { display:flex; gap:.6rem; align-items:center; }
.pf-email .inp { flex:1; background:#f6f7fb; color:#555a70; cursor:default; }
.pf-email .si-link { margin:0; white-space:nowrap; }
.pf-note { margin:.25rem 0 0; font-size:.72rem; color:#9aa0b8; }
.plan-who { font-size:.76rem; color:#8a8fa8; margin:-.2rem 0 .5rem; }
.plan-who:empty { display:none; }
.signin-dlg { width:min(440px,94vw); text-align:center; }
.si-choose { display:flex; flex-direction:column; gap:.6rem; margin-top:1rem; }
.si-opt { display:flex; flex-direction:column; align-items:center; gap:.15rem; padding:.85rem 1rem; border:1.5px solid #d6d9e6; border-radius:10px; background:#fff; color:#2e2763; cursor:pointer; font:inherit; transition:border-color .12s, background .12s; }
.si-opt b { font-family:'DINBlack',sans-serif; font-weight:400; font-size:.95rem; letter-spacing:.03em; }
.si-opt span { font-size:.76rem; color:#8a8fa8; }
.si-opt:hover { border-color:#a99cff; background:#f7f5ff; }
.si-opt.primary { background:#4b3fa8; border-color:#4b3fa8; color:#fff; }
.si-opt.primary span { color:rgba(255,255,255,.75); }
.si-opt.primary:hover { background:#3b3190; }
.si-guest { display:none; border-style:dashed; }
body.cb-gate .si-guest { display:flex; }
#siSwitch:empty { display:none; }
#signinOverlay [hidden] { display:none !important; }   /* .si-row is a flex row: keep hidden steps hidden */
/* First visit: nothing behind the sign-in panel until they're in */
body.cb-gate > *:not(#signinOverlay):not(.toast) { visibility:hidden; }
body.cb-gate #signinOverlay { background:#2e2763; }
.si-row { display:flex; gap:.5rem; margin-top:.8rem; }
.si-form { display:flex; flex-direction:column; gap:.55rem; margin-top:.9rem; text-align:left; }
.si-form .inp { height:2.5rem; font-size:.95rem; }
.si-form .si-wide { height:2.5rem; width:100%; margin-top:.15rem; }
.si-form .si-link { align-self:center; margin-top:.2rem; }
.si-form .dlg-sub { text-align:center; margin:0; }
#siSub:empty { display:none; }
.pw-rules { list-style:none; margin:.1rem 0 0; padding:0; display:grid; grid-template-columns:1fr 1fr; gap:.2rem .8rem; font-size:.76rem; color:#9aa0b8; }
.pw-rules li { display:flex; align-items:center; gap:.4rem; transition:color .15s; }
.pw-rules li::before { content:''; width:.95rem; height:.95rem; flex-shrink:0; border-radius:50%; border:1.5px solid #c9cce0; box-sizing:border-box; transition:background .15s, border-color .15s; }
.pw-rules li.ok { color:#2e7d32; }
.pw-rules li.ok::before { border-color:#2e9d4c; background:#2e9d4c url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 12.5l4.5 4.5L19 7.5'/%3E%3C/svg%3E") center/70% no-repeat; }
.si-row .inp { flex:1; height:2.4rem; font-size:.95rem; }
.si-row .print-go { white-space:nowrap; }
.si-code { letter-spacing:.35em; text-align:center; font-family:ui-monospace,Menlo,monospace; }
.si-link { margin-top:.6rem; background:none; border:none; color:#6b7090; font-size:.78rem; text-decoration:underline; cursor:pointer; }
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
<link rel="stylesheet" href="css/gameboard-ui.css?v=1" />
</head>
<body class="gb-app gb-page gb-card">

<div class="top-bar">
  <div class="top-bar-inner">
    <div class="top-bar-left">
      <img src="images/cardboard_icon_180.png" alt="Your designs" title="Your designs" style="cursor:pointer" onclick="window.location.href=APP_BASE+'cardboard'">
      <div style="min-width:0">
        <h1 title="Your designs" onclick="window.location.href=APP_BASE+'cardboard'"><span class="cb-card">Card</span><span class="cb-board">Board</span></h1>
        <p class="sub" id="subTitle">Card Designer</p>
      </div>
    </div>
    <div class="top-tab-btns">
      <button class="top-tab" id="tabData" onclick="switchTab('data')">Cards</button>
      <button class="top-tab" id="tabTags" onclick="switchTab('tags')">Tags</button>
      <button class="top-tab active" id="tabDesign" onclick="switchTab('design')">Layout</button>
      <button class="top-tab" id="tabPrint" onclick="switchTab('print')">Print</button>
      <button class="top-tab" id="tabExport" onclick="switchTab('export')">Export</button>
    </div>
    <div class="account-menu-wrap">
      <button class="top-save" id="saveBtn" onclick="saveAll()" disabled title="Everything is saved">Save</button>
      <button class="top-btn" onclick="toggleAccountMenu(event)" title="Menu">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="7.5" r="4.5"/><path d="M3.5 21c0-4.14 3.81-7.5 8.5-7.5s8.5 3.36 8.5 7.5"/></svg>
      </button>
      <div class="account-menu" id="accountMenu">
        <button class="account-menu-item" onclick="closeAccountMenu();openProfileDialog()">Profile</button>
        <button class="account-menu-item" onclick="closeAccountMenu();openSettingsDialog()">Settings</button>
        <button class="account-menu-item" onclick="closeAccountMenu();reloadSheet()" title="Read the latest data from the Google Sheet">Fetch</button>
        <button class="account-menu-item" id="cbSubBtn" onclick="closeAccountMenu();openPlansDialog()" style="color:#f5a623">Subscribe</button>
        <button class="account-menu-item" id="cbCodeBtn" onclick="closeAccountMenu();openCodeDialog()" hidden>Enter a code</button>
        <button class="account-menu-item" onclick="closeAccountMenu();window.open(APP_BASE + 'cardboard/help', '_blank')">Help</button>
        <button class="account-menu-item" id="cbSignOut" onclick="closeAccountMenu();signOut()" hidden>Sign out</button>
      </div>
    </div>
  </div>
</div>

<!-- Shown when not signed in, or when the free week has ended: the designer is read-only -->
<div class="lock-bar" id="lockBar" hidden><span id="lockMsg"></span><button type="button" class="plan-btn" id="lockBtn">Subscribe</button></div>

<!-- Data view -->
<div class="view" id="view-data">
  <div class="print-bar grid-bar">
    <div class="grp"><span class="mini">Deck</span>
      <select class="sel sel-sheet" id="gridSheet" onchange="selectTab(this.value)"></select></div>
    <div class="grp"><span class="mini">Find</span>
      <div class="clr-wrap"><input class="inp" id="gridFilter" placeholder="Search the cards" oninput="filterGrid(this.value)" onkeydown="if(event.key==='Escape'){this.value='';filterGrid('');}" style="width:14rem">
      <button type="button" class="clr-x" tabindex="-1" aria-label="Clear" title="Clear" onclick="var i=$('gridFilter');i.value='';filterGrid('');i.focus()">×</button></div></div>
    <!-- Replace: slides out once there's something to find -->
    <div class="grp grid-replace" id="gridReplace" aria-hidden="true"><span class="mini">Replace with</span>
      <div class="gr-row"><div class="clr-wrap"><input class="inp" id="gridReplaceIn" placeholder="New text" tabindex="-1" oninput="clrSync(this)" onkeydown="if(event.key==='Enter'){event.preventDefault();replaceInGrid();}">
      <button type="button" class="clr-x" tabindex="-1" aria-label="Clear" title="Clear" onclick="var i=$('gridReplaceIn');i.value='';clrSync(i);i.focus()">×</button></div>
      <button type="button" class="print-go" id="gridReplaceBtn" tabindex="-1" onclick="replaceInGrid()">Replace all</button></div></div>
    <span class="print-info" id="gridInfo"></span>
    <div class="e-actions">
      <button class="tb-btn" id="gridUndo" onclick="dataUndo(false)" title="Undo (⌘Z)" disabled>↶</button>
      <button class="tb-btn" id="gridRedo" onclick="dataUndo(true)" title="Redo (⇧⌘Z)" disabled>↷</button>
      <button class="print-go ghost" onclick="loadGrid(true)" title="Read the sheet again">Reload</button>
</div>
  </div>
  <p class="grid-hint">Click a cell and type, or double-click to edit · Enter saves (Shift+Enter for a new line) · Tab moves right · Esc cancels · Drag a heading to move it, or its edge to resize · Type in the empty row or column at the end to add one · Save sends your changes to the Google Sheet.</p>
  <div class="grid-wrap" id="gridWrap"></div>
</div>

<!-- Tags view -->
<div class="view" id="view-tags">
  <div class="tags-wrap">
    <div class="tags-stick">
    <div class="tags-head">
      <div>
        <h2 class="tags-title">Tags</h2>
        <p class="tags-sub">Type <code>[energy]</code> in any text and cards show its word or picture.</p>
      </div>
      <div class="e-actions"><span class="print-info" id="tagsInfo"></span>
        <button class="print-go ghost" onclick="loadTags()">Reload</button>
        <button class="print-go" onclick="addTag()">+ Add tag</button></div>
    </div>
    
    <div class="tag-cols"><span>Tag</span><span>Type</span><span>Becomes</span><span>Preview</span><span></span></div>
    </div>
    <div id="tagList"><div class="loading"><span class="spin"></span>Loading tags…</div></div>
  </div>
</div>

<!-- Design view -->
<div class="view active" id="view-design">
  <div class="panel panel-left">
    <div class="panel-scroll">
      <span class="lbl">Deck</span>
      <select class="sel sel-sheet" id="sheetSel" onchange="selectTab(this.value)"></select>
      <div id="palette"></div>
    </div>
  </div>

  <div class="stage">
    <div class="toolbar">
      <button class="tb-btn" id="rowFirst" onclick="firstCard()" title="First card"><svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" aria-hidden="true"><rect x="4" y="5" width="2.6" height="14" rx="1"/><path d="M19 5.5v13L8.5 12z"/></svg></button>
      <button class="tb-btn" id="rowPrev" onclick="stepRow(-1)" title="Previous card">◀</button>
      <span class="tb-txt row-info" id="rowInfo" onclick="editCardNo()">—</span>
      <button class="tb-btn" id="rowNext" onclick="stepRow(1)" title="Next card">▶</button>
      <button class="tb-btn" id="rowLast" onclick="lastCard()" title="Last card"><svg viewBox="0 0 24 24" width="13" height="13" fill="currentColor" aria-hidden="true"><rect x="17.4" y="5" width="2.6" height="14" rx="1"/><path d="M5 5.5v13L15.5 12z"/></svg></button>
      <button class="tb-btn tb-add" id="addCardBtn" onclick="addCard()" title="Add a card after this one" aria-label="Add a card after this one"><span class="card-no card-add">+</span></button>
      <span class="tb-sep"></span>
      <button class="tb-btn" onclick="zoomBy(-1)" title="Zoom out">−</button>
      <span class="tb-txt" id="zoomInfo">100%</span>
      <button class="tb-btn" onclick="zoomBy(1)" title="Zoom in">+</button>
      <button class="tb-btn" onclick="fitZoom()" title="Fit">Fit</button>
      <span class="tb-sep"></span>
      <button class="tb-btn" id="undoBtn" onclick="undo()" title="Undo (⌘Z)">↶</button>
      <button class="tb-btn" id="redoBtn" onclick="redo()" title="Redo (⇧⌘Z)">↷</button>
      <span class="tb-statuses"><span class="tb-status tb-loading" id="loadStatus" hidden></span><span class="tb-status" id="saveStatus"></span></span>
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
    <div class="grp"><span class="mini">Deck</span>
      <select id="pSheet"></select></div>
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
    <button class="print-go" onclick="if (requirePro('print', function() { window.print(); })) window.print()">Print</button>
  </div>
  <div class="pages" id="pages"></div>
</div>

<!-- Export view -->
<div class="view" id="view-export">
  <div class="export-side">
  <div class="print-bar">
    <div class="grp"><span class="mini">Deck</span>
      <select id="eSheet"></select></div>
    <div class="grp"><span class="mini">For</span>
      <select class="sel" id="eTarget" onchange="renderExport()"><option value="tts">Tabletop Simulator</option><option value="screentop">Screentop</option><option value="images">Separate images</option></select></div>
    <div class="grp"><span class="mini">Size</span>
      <select class="sel" id="eQuality" onchange="renderExport()"><option value="4096">Standard (up to 4096 px)</option><option value="8192">Large (up to 8192 px)</option><option value="2048">Small (up to 2048 px)</option></select></div>
    <div class="grp"><span class="mini">Copies</span>
      <select class="sel" id="eCopies"></select></div>
    <div class="grp"><span class="mini">Card back</span><span class="e-note" id="eBackNote"></span></div>
    <div class="e-actions"><button class="print-go ghost" id="publishGo" onclick="runPublish()" title="Host the sheets on zapsheets.com at links that stay the same (Pro)">Get links</button>
    <button class="print-go dl-go" id="exportGo" onclick="runExport()" title="Download the card images (zip)" aria-label="Download"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11"/><path d="m7 10.5 5 5 5-5"/><path d="M5 20h14"/></svg></button></div>
  </div>
  <div class="export-info"><span id="exportInfo"></span><div class="export-help" id="exportHelp"></div>
    <div class="export-progress" id="exportProgress" style="display:none"><div></div></div><span class="muted" id="exportStatus"></span>
    <div class="export-links" id="exportLinks" style="display:none"></div></div>
  </div>
  <div class="pages" id="exportPreview"></div>
  <div id="exportStage" aria-hidden="true"></div>
</div>

<!-- Footer: copyright, and a little room at the bottom of the app -->
<footer class="zs-footer cb-footer">© <?= date('Y') ?> ZapSheets. All rights reserved.<span class="sep">·</span><a href="cardboard/features" target="_blank" rel="noopener">CardBoard</a><span class="sep">·</span><a href="cardboard/help" target="_blank" rel="noopener">Help</a></footer>

<!-- Profile -->
<div class="overlay" id="profileOverlay" onclick="if(event.target===this)closeDialog('profileOverlay')">
  <div class="dlg" onclick="event.stopPropagation()">
    <h2 class="dlg-title">Profile</h2>
    <p class="dlg-sub">Who's designing these cards. Saved with your GameBoard account.</p>
    <label class="mini" for="pfName">Name</label><input class="inp" id="pfName" maxlength="80">
    <label class="mini" for="pfEmail">Email</label><div class="pf-email"><input class="inp" id="pfEmail" type="email" readonly tabindex="-1" aria-describedby="pfEmailNote"><button type="button" class="si-link" onclick="changeSignInEmail()">Change</button></div>
    <p class="pf-note" id="pfEmailNote">The email you sign in with — codes and receipts go here.</p>
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
    <div class="plan-who" id="planWho"></div>
    <div class="plan-current" id="planCurrent"></div>
    <p class="dlg-sub" id="planIntro" style="margin:.3rem 0 .9rem">Pick the plan that fits how you work.</p>
    <div class="plan-grid">
      <div class="plan-card" id="planFree">
        <div class="plan-name">Free week</div>
        <div class="plan-price">$0<span>for 7 days</span></div>
        <ul>
          <li>Every feature, from the day you sign in</li>
          <li>Designer, fronts &amp; backs, tags</li>
          <li>Print, Tabletop Simulator &amp; Screentop</li>
          <li>Hosted links and picture uploads</li>
          <li>No card needed</li>
        </ul>
      </div>
      <div class="plan-card plan-pro" id="planPro">
        <div class="plan-name">CardBoard</div>
        <div class="plan-price">$4.99<span>/month</span></div>
        <ul>
          <li>Everything, after your free week</li>
          <li>Hosted links that update when your cards change</li>
          <li>Picture uploads</li>
          <li>Cancel any time — your sheet and layouts stay yours</li>
        </ul>
        <button class="plan-btn" id="planProBtn" onclick="startProCheckout()">Subscribe</button>
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

<!-- Sign in / start the free week: an action-only panel (no dirty check) -->
<div class="overlay" id="signinOverlay" onclick="if(event.target===this&&!document.body.classList.contains('cb-gate'))closeSignIn()">
  <div class="dlg signin-dlg" onclick="event.stopPropagation()">
    <h2 class="plan-title"><img src="images/cardboard_icon_180.png" alt=""><span id="siTitle">Welcome to CardBoard</span></h2>
    <p class="dlg-sub" id="siSub">Design and print cards from your Google Sheet.</p>
    <div id="siChoose" class="si-choose">
      <button type="button" class="si-opt primary" onclick="signInChoose('trial')"><b>Start your free week</b><span>Every feature for 7 days · no card needed</span></button>
      <button type="button" class="si-opt" onclick="signInChoose('login')"><b>Log in</b><span>I already have a GameBoard account</span></button>
      <button type="button" class="si-opt si-guest" onclick="viewAsGuest()"><b>View read only</b><span>Look through the cards without signing in</span></button>
    </div>
    <form id="siStep1" class="si-form" hidden onsubmit="event.preventDefault();signInSubmit()">
      <input class="inp" id="siEmail" type="email" autocomplete="email" placeholder="Email" aria-label="Email">
      <input class="inp" id="siPass" type="password" autocomplete="current-password" placeholder="Password" aria-label="Password">
      <input class="inp" id="siPass2" type="password" autocomplete="new-password" placeholder="Password again" aria-label="Password again">
      <ul class="pw-rules" id="siRules" aria-live="polite">
        <li data-rule="len">At least 8 characters</li><li data-rule="letter">A letter</li><li data-rule="num">A number</li><li data-rule="match">Both passwords match</li>
      </ul>
      <button type="submit" class="print-go si-wide" id="siSend">Log in</button>
      <button type="button" class="si-link" id="siForgot" onclick="signInStep('reset')">Forgot password?</button>
    </form>
    <form id="siStep2" class="si-form" hidden onsubmit="event.preventDefault();signInVerify()">
      <p class="dlg-sub" id="siSent">We sent a 6-digit code.</p>
      <input class="inp si-code" id="siCode" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" aria-label="Code" oninput="var v=this.value.replace(/\D/g,'').slice(0,6);if(v!==this.value)this.value=v">
      <button type="submit" class="print-go si-wide" id="siGo">Confirm</button>
      <button type="button" class="si-link" onclick="signInBack()">Back</button>
    </form>
    <div class="dlg-err" id="siErr"></div>
    <div class="dlg-actions" style="justify-content:center;flex-wrap:wrap">
      <button type="button" class="si-link" id="siSwitch" onclick="signInSwitch()" style="margin:0 auto .3rem;flex-basis:100%"></button>
      <button type="button" class="attr-btn" id="siClose" onclick="closeSignIn()">Not now</button>
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
