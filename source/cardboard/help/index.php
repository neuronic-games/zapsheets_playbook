<?php
$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_base = preg_replace('#/cardboard/help/?$#', '/', $_raw);
if (!$_base || $_base === $_raw) { $_base = '/'; }
if (substr($_base, -1) !== '/') { $_base .= '/'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>CardBoard – Help</title>
<link rel="icon" type="image/png" sizes="32x32" href="images/cardboard_icon_32.png" />
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2')  format('woff2'),url('fonts/DINBlack.ttf')  format('truetype'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf') format('truetype'); }
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root { --navy:#2e2763; --violet:#7b6cf6; --lav:#a99cff; --gold:#f2c14e; --cream:#f3f2fa; --border:#e1def5; --slate:#64748b; }
html { scroll-behavior:smooth; }
body { background:var(--cream); font-family:'DINRegular',Arial,sans-serif; color:#1a1a1a; line-height:1.6; }
.top-bar { background:var(--navy); color:#fff; padding:.85rem 1.5rem; position:sticky; top:0; z-index:100; box-shadow:0 2px 12px rgba(0,0,0,.25); }
.top-bar-inner { max-width:860px; margin:0 auto; display:flex; align-items:center; gap:.8rem; }
.top-bar img { width:28px; height:28px; border-radius:7px; }
.brand-name { font-family:'DINBlack',sans-serif; font-size:1.1rem; letter-spacing:.04em; line-height:1; }
.brand-name .c { color:var(--gold); } .brand-name .b { color:var(--lav); }
.top-bar-label { font-family:'DINBlack',sans-serif; font-size:.8rem; letter-spacing:.1em; text-transform:uppercase; opacity:.7; }
.hero { background:#1c1a2b; color:#fff; padding:3.5rem 1.5rem 4rem; text-align:center; }
.hero h1 { font-family:'DINBlack',sans-serif; font-size:clamp(1.8rem,6vw,2.6rem); letter-spacing:.04em; line-height:1.05; margin-bottom:.7rem; font-weight:400; }
.hero h1 .c { color:var(--gold); } .hero h1 .b { color:var(--lav); }
.hero p { color:rgba(255,255,255,.7); max-width:540px; margin:0 auto; }
.toc-wrap { background:#fff; border-bottom:1px solid var(--border); position:sticky; top:56px; z-index:90; overflow-x:auto; scrollbar-width:none; }
.toc { display:flex; gap:.1rem; padding:.6rem 1.25rem; max-width:860px; margin:0 auto; white-space:nowrap; }
.toc a { font-family:'DINBlack',sans-serif; font-size:.67rem; letter-spacing:.07em; text-transform:uppercase; color:var(--slate); text-decoration:none; padding:.3rem .6rem; border-radius:6px; }
.toc a:hover { background:var(--cream); color:var(--navy); }
.page { max-width:860px; margin:0 auto; padding:0 1.25rem 5rem; }
.section { padding-top:2.6rem; scroll-margin-top:110px; }
.section h2 { font-family:'DINBlack',sans-serif; font-weight:400; font-size:1.25rem; letter-spacing:.04em; text-transform:uppercase; color:var(--navy); padding-bottom:.6rem; margin-bottom:1rem; border-bottom:2px solid var(--violet); }
.section p, .section li { font-size:.93rem; color:#444; }
.section p + p { margin-top:.7rem; }
.section ul, .section ol { padding-left:1.3rem; margin-top:.5rem; }
.section li { margin-bottom:.35rem; }
.cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:.8rem; margin-top:1rem; }
.card { background:#fff; border:1px solid var(--border); border-radius:12px; padding:1rem 1.15rem; box-shadow:0 1px 6px rgba(0,0,0,.05); }
.card b { display:block; font-family:'DINBlack',sans-serif; font-weight:400; font-size:.82rem; letter-spacing:.06em; text-transform:uppercase; color:var(--navy); margin-bottom:.3rem; }
.card p { font-size:.84rem; color:#666; }
kbd, code { font-family:ui-monospace,Menlo,monospace; font-size:.8em; background:#ece9ff; color:#4b3fa8; border-radius:4px; padding:.05rem .3rem; }
.pro { display:inline-block; padding:0 .35rem; border-radius:4px; background:#f5a623; color:#fff; font-family:'DINBlack',sans-serif; font-size:.6rem; letter-spacing:.06em; vertical-align:middle; }
.note { background:#fff; border-left:4px solid var(--violet); border-radius:8px; padding:.8rem 1rem; margin-top:1rem; font-size:.88rem; color:#444; }
</style>
</head>
<body>
<div class="top-bar"><div class="top-bar-inner">
  <img src="images/cardboard_icon_180.png" alt="">
  <span class="brand-name"><span class="c">Card</span><span class="b">Board</span></span>
  <span class="top-bar-label">Help</span>
</div></div>

<div class="hero">
  <h1><span class="c">Card</span><span class="b">Board</span></h1>
  <p>Design board game cards from a Google Sheet — one row per card — then print them or send them to Tabletop Simulator and Screentop.</p>
</div>

<div class="toc-wrap"><nav class="toc">
  <a href="#start">Getting started</a><a href="#data">Data</a><a href="#design">Design</a><a href="#elements">Elements</a>
  <a href="#tags">Tags</a><a href="#backs">Card backs</a><a href="#print">Print</a><a href="#export">Export</a><a href="#pictures">Pictures</a><a href="#menu">Menu</a><a href="#plans">Plans</a>
</nav></div>

<div class="page">
  <section class="section" id="start">
    <h2>Getting started</h2>
    <ol>
      <li>Put your cards in a Google Sheet: column names in the first row, one card per row below.</li>
      <li>Share the spreadsheet with <code>editor@zapsheets-480701.iam.gserviceaccount.com</code> as an <b>Editor</b>.</li>
      <li>Open <a href="cardboard">CardBoard</a>, paste the sheet's link, and pick the tab with your cards.</li>
    </ol>
    <p>CardBoard adds a tab called <b>layouts</b> to the spreadsheet. It holds your layouts, profile and settings — leave it in place. Hidden tabs aren’t listed, so hide any tab you don’t want to design from.</p>
  </section>

  <section class="section" id="data">
    <h2>Data</h2>
    <p>The <b>Data</b> tab shows the sheet as a grid. Click a cell and type, or double-click to edit. <kbd>Enter</kbd> saves (<kbd>Shift</kbd>+<kbd>Enter</kbd> for a new line), <kbd>Tab</kbd> moves right and <kbd>Esc</kbd> cancels. Each change goes straight to the Google Sheet.</p>
    <p>If someone changed a cell in Sheets since you loaded it, CardBoard won't overwrite it — the cell turns red and shows their value. <b>+ Add row</b> adds a card; it's written once you type in it.</p>
    <p>In the Design tab, double-click a Data field on the card to edit that card's value in place. <kbd>Enter</kbd> saves it to the sheet (<kbd>Shift</kbd>+<kbd>Enter</kbd> for a new line) and <kbd>Esc</kbd> cancels.</p>
  </section>

  <section class="section" id="design">
    <h2>Design</h2>
    <div class="cards">
      <div class="card"><b>Add</b><p>Drag anything from the left panel onto the card. Drop a picture file on the card to add it as an image.</p></div>
      <div class="card"><b>Arrange</b><p>Drag to move, pull the handles to resize. Arrow keys nudge 1 pt (<kbd>Shift</kbd>: 10). Hold <kbd>Alt</kbd> for fine moves.</p></div>
      <div class="card"><b>Style</b><p>Select something to change its font, colors, alignment, rotation and more on the right.</p></div>
      <div class="card"><b>Rows</b><p>Step through your rows with the arrows above the card to see every card.</p></div>
      <div class="card"><b>Save</b><p><kbd>⌘</kbd>/<kbd>Ctrl</kbd>+<kbd>S</kbd> saves the layout to the sheet. Undo with <kbd>⌘Z</kbd>; duplicate with <kbd>⌘D</kbd>.</p></div>
      <div class="card"><b>Fields</b><p><b>+ New</b> adds a column to the sheet. Click a Data field to hide it here and in the Data tab.</p></div>
    </div>
  </section>

  <section class="section" id="elements">
    <h2>Elements</h2>
    <ul>
      <li><b>Text</b> — fixed wording. Put a column name in braces, like <code>{Cost}</code>, to fill it from each row.</li>
      <li><b>Data</b> — one column of the sheet, shown as text, or switched to <b>Image</b> when the column holds picture links.</li>
      <li><b>Image</b> — the same picture on every card: a logo, frame or icon. Press <b>[ ]</b> on its picture to choose the picture by a column's value instead: pick the column, then give each value its picture (<em>Market</em> → a basket, <em>Action</em> → a boot). Values without a picture use the picture link in that column, or the default picture. Values and pictures can be tags like <code>[fire]</code>.</li>
      <li><b>Shape</b> — rectangle, rounded, circle, line, triangle, diamond, hexagon or star, with fill and outline.</li>
      <li><b>Generated</b> — a number sequence (<code>#001</code>…), a random number, or a pick from a list. Values stay the same between the preview and the printout; <b>Re-roll</b> draws new ones.</li>
    </ul>
  </section>

  <section class="section" id="tags">
    <h2>Tags <span class="pro">PRO</span></h2>
    <p>Write a tag in square brackets, like <code>[energy]</code>, in any text or data, and every card shows a word or a picture in its place — <em>“Gain 1 [energy]”</em> becomes “Gain 1 ⚡”. Curly braces stay for columns, so <em>“Gain {Amount} [energy]”</em> uses both. Set tags up in the <b>Tags</b> view: give each one a name (with or without the brackets) and either text or a picture. Pictures can be an icon from the library, a link, or an upload. Tags are kept in your spreadsheet's <b>tags</b> tab, with the names in brackets.</p>
  </section>

  <section class="section" id="backs">
    <h2>Card backs</h2>
    <p>The round button at the top left of the card flips it over so you can design the back. If the back has nothing that changes from card to card, every card shares one back. Add a Data field, a <code>{Column}</code> in text, or a Generated value, and each card gets its own back (<b>Unique backs</b>).</p>
  </section>

  <section class="section" id="print">
    <h2>Print</h2>
    <p>Choose the paper, gap and cut lines, and how many copies (one of each, or from a number column such as <b>Count</b>). For double-sided printing, set <b>Backs</b> to flip on the long or short edge to match your printer — the backs line up with their fronts.</p>
  </section>

  <section class="section" id="export">
    <h2>Export</h2>
    <p><b>Download</b> makes a zip of card sheets: for <b>Tabletop Simulator</b> (up to 10 × 7 per sheet, with the back in the last slot), <b>Screentop</b> (sheets up to 4096 px), or one picture per card.</p>
    <p><b>Get links</b> <span class="pro">PRO</span> hosts the sheets on zapsheets.com at links that never change. Paste them into Tabletop Simulator's Custom Deck dialog. When you save the layout, fetch new data or edit the Data tab, the pictures behind the links are replaced with your latest cards. The links carry a share code, not your spreadsheet's ID.</p>
    <div class="note">Tabletop Simulator keeps its own copy of downloaded pictures, so a game may show the old cards until it downloads them again. If you add cards, raise <b>Number</b> in TTS to match.</div>
  </section>

  <section class="section" id="pictures">
    <h2>Pictures</h2>
    <p>Paste a <b>Dropbox</b>, <b>Google Drive</b> or web link — CardBoard shows it straight from the source, so changing the picture there changes your cards. In Drive, share the file as “Anyone with the link”. Uploading a file from your computer <span class="pro">PRO</span> stores it on zapsheets.com.</p>
  </section>

  <section class="section" id="menu">
    <h2>Menu</h2>
    <ul>
      <li><b>Profile</b> — your name, email and studio. The email is used for receipts when you subscribe.</li>
      <li><b>Settings</b> — open the spreadsheet, choose inches or millimetres, and see your plan.</li>
      <li><b>Fetch</b> — read the latest data from the Google Sheet.</li>
      <li><b>Subscribe</b> — CardBoard plans.</li>
    </ul>
  </section>

  <section class="section" id="plans">
    <h2>Plans</h2>
    <div class="cards">
      <div class="card"><b>Free</b><p>The designer with fronts and backs, the Data tab, printing, downloads for Tabletop Simulator and Screentop, and pictures from Dropbox, Drive or the web.</p></div>
      <div class="card"><b>Pro — $4.99/month</b><p>Everything in Free, plus tags (<code>[energy]</code> → an icon or word) and anything that keeps files on our server: hosted links for every export (Tabletop Simulator, Screentop and card images) that update when your cards change, and picture uploads.</p></div>
    </div>
  </section>
</div>
</body>
</html>
