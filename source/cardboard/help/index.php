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
table.keys { width:100%; border-collapse:collapse; margin-top:.8rem; background:#fff; border:1px solid var(--border); border-radius:12px; overflow:hidden; font-size:.88rem; }
table.keys td { padding:.5rem .9rem; border-bottom:1px solid #eeecf8; color:#444; vertical-align:top; }
table.keys tr:last-child td { border-bottom:none; }
table.keys td:first-child { white-space:nowrap; width:1%; }
.section h3 { font-family:'DINBlack',sans-serif; font-weight:400; font-size:.86rem; letter-spacing:.06em; text-transform:uppercase; color:var(--violet); margin:1.5rem 0 .4rem; }
.tip { background:#fffaf0; border-left:4px solid var(--gold); border-radius:8px; padding:.7rem 1rem; margin-top:.9rem; font-size:.86rem; color:#5a4a1f; }
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
  <h1><span class="c">Card</span><span class="b">Board</span> Help</h1>
  <p>Design board game cards from a Google Sheet — one row per card — then print them or send them to Tabletop Simulator and Screentop. Here's how every part works.</p>
</div>

<div class="toc-wrap"><nav class="toc">
  <a href="cardboard/help#start">Getting started</a><a href="cardboard/help#account">Account</a><a href="cardboard/help#tour">The screen</a><a href="cardboard/help#saving">Saving</a><a href="cardboard/help#data">Data</a>
  <a href="cardboard/help#card">The card</a><a href="cardboard/help#elements">Elements</a><a href="cardboard/help#editing">Moving &amp; sizing</a><a href="cardboard/help#text">Text style</a>
  <a href="cardboard/help#pictures">Pictures</a><a href="cardboard/help#generated">Generated</a><a href="cardboard/help#layers">Layers &amp; groups</a><a href="cardboard/help#tags">Tags</a>
  <a href="cardboard/help#backs">Backs</a><a href="cardboard/help#print">Print</a><a href="cardboard/help#export">Export</a><a href="cardboard/help#keys">Shortcuts</a><a href="cardboard/help#plans">Plans</a><a href="cardboard/help#trouble">Troubleshooting</a>
</nav></div>

<div class="page">

  <section class="section" id="start">
    <h2>Getting started</h2>
    <ol>
      <li><b>Set up your sheet.</b> In Google Sheets, put the column names in the first row (<em>Name</em>, <em>Cost</em>, <em>Text</em>, <em>Art</em>…) and one card per row below. Each tab can be its own deck.</li>
      <li><b>Share it with CardBoard.</b> Click <b>Share</b> in Sheets and add <code>editor@zapsheets-480701.iam.gserviceaccount.com</code> as an <b>Editor</b>. CardBoard reads and writes your sheet through that address.</li>
      <li><b>Sign in.</b> Open <a href="cardboard">CardBoard</a> and choose <b>Start your free week</b> (or <b>Log in</b> if you already have an account).</li>
      <li><b>Connect the sheet.</b> Paste the spreadsheet's link (or its ID) and continue. The designer opens on your first tab — bookmark that page to come straight back.</li>
      <li><b>Pick a tab.</b> Use the <b>Sheet</b> list at the top left of the Design view. Tabs that already have a layout show a small layout icon.</li>
    </ol>
    <p>CardBoard adds a tab called <b>layouts</b> to your spreadsheet. It holds every tab's card design and your settings for that spreadsheet — leave it in place. Hidden tabs aren't listed, so hide any tab you don't want to design from.</p>
    <div class="tip">To start fast, the designer shows your card as soon as the first few rows arrive and loads the rest in the background — the status above the card shows when everything is in.</div>
  </section>

  <section class="section" id="account">
    <h2>Your account</h2>
    <div class="cards">
      <div class="card"><b>Sign up</b><p>Choose <b>Start your free week</b>, enter your email and a password, and type the 6-digit code we email you. The checklist ticks off each password rule as you type: at least 8 characters, a letter and a number.</p></div>
      <div class="card"><b>Log in</b><p>Your email and password. You stay signed in on that browser, so you won't be asked every visit.</p></div>
      <div class="card"><b>Forgot password?</b><p>On the log-in screen, choose <b>Forgot password?</b>. We email a code; enter it with your new password.</p></div>
      <div class="card"><b>Profile</b><p>Menu → <b>Profile</b>: your name and studio. The email is the one you sign in with — <b>Change</b> it to sign in with a different address.</p></div>
    </div>
  </section>

  <section class="section" id="tour">
    <h2>The screen</h2>
    <p>The tabs across the top switch between the five views:</p>
    <ul>
      <li><b>Data</b> — your sheet as an editable grid.</li>
      <li><b>Tags</b> — words and icons that <code>[tags]</code> turn into.</li>
      <li><b>Design</b> — the card designer: elements on the left, the card in the middle, settings on the right.</li>
      <li><b>Print</b> — sheets of cards ready for your printer.</li>
      <li><b>Export</b> — card sheets for Tabletop Simulator and Screentop, and hosted links.</li>
    </ul>
    <p>The round person button at the top right opens the menu:</p>
    <ul>
      <li><b>Profile</b> — your name, email and studio.</li>
      <li><b>Settings</b> — open the spreadsheet in Google Sheets, choose inches or millimetres for measurements, and see your plan.</li>
      <li><b>Fetch</b> — re-read everything from the Google Sheet, including tabs you’ve added, renamed or removed (use it after editing in Sheets).</li>
      <li><b>Subscription</b> — your free week or subscription, billing, and codes.</li>
      <li><b>Enter a code</b> — unlock time with a free code.</li>
      <li><b>Help</b> — this page. <b>Sign out</b> — sign out of this browser.</li>
    </ul>
  </section>

  <section class="section" id="saving">
    <h2>Saving</h2>
    <p>Everything you change — layouts on every tab, cell edits and new rows, column widths and settings — is kept in this browser as you work. It stays there if you switch sheets, reload the page or close the window by accident.</p>
    <ul>
      <li><b>Save</b> (top right, beside the menu, or <kbd>⌘S</kbd>) sends all of it to your Google Sheet in one go. The number on the button is how many changes are waiting.</li>
      <li>Saving happens in the background: keep working while it runs. Anything you change meanwhile waits for the next Save.</li>
      <li>Until you save, your changes are only in this browser — other people, exports and hosted links see the last saved version. Hosted links update after you save.</li>
      <li>If someone changed a cell in Google Sheets after you loaded it, your edit to that cell isn't written: the cell turns red and shows their value.</li>
      <li><b>Fetch</b> with unsaved changes asks first: <b>Save, then fetch</b>, or <b>Fetch without saving</b> — your changes then stay in this browser, shown on top of the latest data.</li>
      <li>Renaming or deleting a column, deleting a row and editing tags go to the sheet straight away.</li>
    </ul>
    <div class="note">Changes kept in a browser stay in that browser only — they don't follow you to another computer, and clearing the browser's site data (or closing a private window) removes them. Save before you switch computers.</div>
  </section>

  <section class="section" id="data">
    <h2>Data</h2>
    <p>The <b>Data</b> view shows the chosen tab as a grid. Your edits are kept in this browser and go to the Google Sheet when you press <b>Save</b> (see <a href="cardboard/help#saving">Saving</a>).</p>
    <h3>Editing cells</h3>
    <ul>
      <li>Click a cell and start typing to replace it, or double-click (or press <kbd>Enter</kbd> / <kbd>F2</kbd>) to edit what's there.</li>
      <li><kbd>Enter</kbd> saves and moves down; <kbd>Shift</kbd>+<kbd>Enter</kbd> adds a new line; <kbd>Tab</kbd> saves and moves right; <kbd>Esc</kbd> cancels.</li>
      <li>Arrow keys move around. <kbd>Delete</kbd> clears a cell. <kbd>⌘C</kbd> copies a cell and <kbd>⌘V</kbd> pastes into it.</li>
      <li>Formulas show in purple; their results appear on the cards after saving.</li>
    </ul>
    <h3>Rows and columns</h3>
    <ul>
      <li><b>New rows:</b> there's always an empty row at the bottom. Type in it and it becomes a card (added to the sheet when you save), with a fresh empty row below.</li>
      <li><b>New columns:</b> there's always an empty column at the end. Click its <b>New column</b> heading to name it, or just type in one of its cells — the column is added to the sheet straight away, named after its letter (like <b>Column H</b>) until you rename it.</li>
      <li><b>Delete a row:</b> click its row number → <b>Delete row</b>. <b>Delete a column:</b> click its heading → <b>Delete column</b>. CardBoard asks first, then deletes it from your Google Sheet (undo it there with Edit → Undo or version history). If the row changed in Sheets since you loaded it, nothing is deleted and the latest is shown instead.</li>
      <li><b>Search the rows</b> shows only rows containing what you type.</li>
      <li><b>Reload</b> re-reads this tab from the sheet.</li>
      <li><b>Move a column:</b> drag its heading sideways and drop it where the purple line shows. <b>Resize:</b> drag the right edge of a heading; double-click the edge to fit its contents again. Column widths stay put while you work. This order and these widths are only for this view — your sheet's column order doesn't change.</li>
      <li><b>Rename a column:</b> double-click its heading (or click it → <b>Rename</b>), type the new name and press <kbd>Enter</kbd> (<kbd>Esc</kbd> cancels). The heading changes in your Google Sheet, and the layout follows automatically: Data fields, <code>{Column}</code> in text, pictures chosen by that column, hidden fields and column widths all switch to the new name.</li>
      <li><b>Hide a column:</b> click its heading → <b>Hide field</b>. It's hidden here and in the Design list, but stays in the sheet (and on any card that uses it). <b>Show all</b> above the grid brings hidden ones back.</li>
    </ul>
    <div class="note">If someone changed a cell in Google Sheets since you loaded it, CardBoard won't overwrite their work: the cell turns red and shows their value.</div>
  </section>

  <section class="section" id="card">
    <h2>The card</h2>
    <p>With nothing selected, the right panel shows the card itself:</p>
    <ul>
      <li><b>Size</b> — Poker (2.5 × 3.5 in), Bridge, Tarot, Mini, Square, Euro, or <b>Custom</b> with your own width and height.</li>
      <li><b>Background</b>, <b>Corner radius</b> and <b>Border</b> (width and color) for the card.</li>
    </ul>
    <h3>Around the card</h3>
    <ul>
      <li><b>Rows</b> — the arrows above the card step through your rows, so you can check every card.</li>
      <li><b>Zoom</b> — <b>−</b> and <b>+</b> zoom out and in; <b>Fit</b> fits the card to the window.</li>
      <li><b>Undo / Redo</b> — the curved arrows, or <kbd>⌘Z</kbd> and <kbd>⇧⌘Z</kbd>.</li>
      <li><b>Save</b> — top right, beside the menu: see <a href="cardboard/help#saving">Saving</a>.</li>
      <li><b>Flip</b> — the round button at the top left of the card turns it over to design the back (see <a href="cardboard/help#backs">Backs</a>).</li>
    </ul>
  </section>

  <section class="section" id="elements">
    <h2>Elements</h2>
    <p>Drag anything from the left panel onto the card. You can also drop a picture file from your computer onto the card to add it as an image.</p>
    <div class="cards">
      <div class="card"><b>Text</b><p>Fixed wording, like a title or rules. Put a column name in braces — <code>{Cost}</code> — to fill it from each row: <em>“Costs {Cost} gold”</em>. <b>Insert a column</b> under the text box adds one for you. Double-click it on the card to edit the wording right there.</p></div>
      <div class="card"><b>Data</b><p>One column of your sheet. The list shows each column with its value for the current row. Choose <b>Show as</b> Text or Image (for columns of picture links).</p></div>
      <div class="card"><b>Image</b><p>A picture: the same on every card (a logo, frame or icon), or chosen by a column's value with <b>[ ]</b> (see <a href="cardboard/help#pictures">Pictures</a>).</p></div>
      <div class="card"><b>Shape</b><p>Rectangle, rounded rectangle, circle, line, triangle, diamond, hexagon or star, with a fill, outline color and outline width.</p></div>
      <div class="card"><b>Generated</b><p>A number sequence, a random number, or a pick from a list — see <a href="cardboard/help#generated">Generated</a>.</p></div>
    </div>
    <h3>Data fields</h3>
    <ul>
      <li><b>Column</b> switches which column the field shows.</li>
      <li><b>Value for row N</b> shows that row's value exactly as typed in the sheet (tags still in brackets) — handy when it's empty and invisible on the card. Edit it and click away; it goes to the sheet with your next Save.</li>
      <li>Or double-click a Data field on the card to edit it in place: <kbd>Enter</kbd> keeps it (it goes to the sheet when you save), <kbd>Shift</kbd>+<kbd>Enter</kbd> adds a line, <kbd>Esc</kbd> cancels.</li>
      <li><b>+ New</b> at the bottom of the Data list adds a column to the sheet, ready to drag onto the card.</li>
      <li>Click a field in the list → <b>Hide field</b> to tuck away columns you don't use. The eye button in the Data heading shows hidden fields again (greyed) so you can unhide them.</li>
    </ul>
  </section>

  <section class="section" id="editing">
    <h2>Moving, sizing and lining up</h2>
    <ul>
      <li><b>Select</b> an element by clicking it. <kbd>Shift</kbd> or <kbd>⌘</kbd>-click adds to the selection; <kbd>⌘A</kbd> selects everything; <kbd>Esc</kbd> selects nothing.</li>
      <li><b>Move</b> by dragging, or nudge with the arrow keys (1 pt; <kbd>Shift</kbd>: 10 pt). Hold <kbd>Alt</kbd> while dragging for fine moves.</li>
      <li><b>Resize</b> with the square handles. Hold <kbd>Shift</kbd> on a shape's corner to keep its proportions.</li>
      <li><b>Full width / full height:</b> with anything selected, a round ↔ button appears at the middle of the card's top edge and a ↕ button at the middle of its left edge. Click one to stretch everything selected across the whole width or height of the card.</li>
      <li><b>Rotate</b> with the round handle above the element (<kbd>Shift</kbd> snaps to 15° steps), or type an angle in <b>Rotate</b>.</li>
      <li><b>Exact numbers:</b> X, Y, W and H in the right panel (in points; 72 pt = 1 inch). <b>Opacity</b> fades an element.</li>
      <li><b>Stacking:</b> <b>To front</b> / <b>To back</b>, or drag in the Layers panel.</li>
      <li><b>Duplicate</b> (<kbd>⌘D</kbd>) and <b>Delete</b> (<kbd>Delete</kbd>).</li>
      <li><b>Copy, cut and paste</b> (<kbd>⌘C</kbd>, <kbd>⌘X</kbd>, <kbd>⌘V</kbd>, or the <b>Copy</b> / <b>Cut</b> buttons) work between the front and back, between tabs, and between spreadsheets — even in another CardBoard window. Groups (and their list settings) come along. Pasted elements keep their position, nudged over if they'd land right on top of the originals. If a pasted Data field uses a column this sheet doesn't have, CardBoard tells you so you can pick another column.</li>
    </ul>
    <h3>Lining up</h3>
    <p>When something is selected, round dots appear past the card's right and bottom edges, with dotted lines leading back to the card. Click a dot to line up with that line:</p>
    <ul>
      <li>With <b>one element or one group</b> selected, the dots line it up with the card — its top, middle, bottom, left edge, center or right edge.</li>
      <li>With <b>several</b> selected, the dots line them up with each other.</li>
    </ul>
    <h3>Lining up to a key object</h3>
    <p>To line several things up with one particular element — without moving that element — make it the <b>key object</b>:</p>
    <ol>
      <li>Select the elements (<kbd>Shift</kbd> or <kbd>⌘</kbd>-click, or tick them in Layers), including the one to line up to.</li>
      <li>Click that one again, without dragging. It gets a <b>solid purple outline</b>, and the alignment dots move around it — to its right for top, middle and bottom, and below it for left, center and right.</li>
      <li>Click a dot. The others line up with the key object's edge or middle; the key stays where it is.</li>
    </ol>
    <ul>
      <li>To change the key, click a different element in the selection. Click the key again to go back to lining them up with each other.</li>
      <li>If you click part of a group that's fully selected, the whole group becomes the key.</li>
      <li>Dragging still moves everything selected together — only a click sets the key.</li>
      <li>The right panel names the current key object.</li>
    </ul>
  </section>

  <section class="section" id="text">
    <h2>Text style</h2>
    <p>Text, Data (as text) and Generated elements share these settings:</p>
    <ul>
      <li><b>Font</b> — DIN Black, DIN Medium, Bangers, Cinzel, Lilita One, Merriweather, Roboto Condensed and more.</li>
      <li><b>Size</b> (pt), <b>Line height</b> and <b>Color</b>.</li>
      <li><b>Style</b> — <b>B</b> bold, <b>I</b> italic, <b>AA</b> all capitals.</li>
      <li><b>Align</b> left, centre or right, and <b>Vertical</b> top, middle or bottom within the box.</li>
      <li><b>Background</b> — a fill color, a border (width and color), corner radius and padding around the text.</li>
    </ul>
    <p>Text wraps to fit its box. Make the box taller or the font smaller if words get cut off.</p>
  </section>

  <section class="section" id="pictures">
    <h2>Pictures</h2>
    <h3>Adding a picture</h3>
    <ul>
      <li>Click the picture box in the right panel (or double-click the image on the card) to <b>upload</b> a file or paste a <b>link</b>. You can also drop a file or link onto the picture box.</li>
      <li><b>Dropbox</b> and <b>Google Drive</b> links are shown straight from the source, so updating the file there updates your cards. In Drive, share the file as “Anyone with the link”.</li>
      <li>Uploaded files are stored on zapsheets.com.</li>
      <li><b>Fit</b> — <b>Fill</b> covers the box (cropping the edges), <b>Fit</b> shows the whole picture, <b>Stretch</b> fills the box exactly. <b>Frame</b> adds a fill, border and rounded corners.</li>
    </ul>
    <h3>Pictures from your sheet</h3>
    <p>For a column that holds picture links, add it as a <b>Data</b> field and set <b>Show as</b> → <b>Image</b>. Each row's picture appears on its card. The right panel shows the current row's picture: drop or choose a new one and it's saved into that row's cell.</p>
    <h3>Choosing a picture by value <code>[ ]</code></h3>
    <p>On an Image element, the round <b>[ ]</b> button opens a drawer for choosing the picture by a column's value — for example a basket for <em>Market</em> cards and a boot for <em>Action</em> cards:</p>
    <ol>
      <li>Pick the <b>Field</b> to look at.</li>
      <li>In each row of the drawer, choose a <b>value</b> (the list shows the values in your sheet, or type one) and give it a picture. A new empty row appears as you fill each one.</li>
      <li>Cards whose value has no picture use the picture link in that column, or the default picture.</li>
    </ol>
    <p>Values and pictures can also be <a href="cardboard/help#tags">tags</a> like <code>[fire]</code>. The row matching the current card is highlighted.</p>
  </section>

  <section class="section" id="generated">
    <h2>Generated values</h2>
    <ul>
      <li><b>Number sequence</b> — counts up across your cards. Set <b>Start at</b>, <b>Step</b>, <b>Digits</b> (pad with zeros) and text <b>Before</b> / <b>After</b>, e.g. <code>#001</code>, <code>#002</code>…</li>
      <li><b>Random number</b> — a number <b>From</b> … <b>To</b> for each card.</li>
      <li><b>Random from a list</b> — type the <b>Values</b> one per line, and <b>Pick</b> them at random or in order.</li>
      <li><b>Counts</b> — <b>Each card printed</b> gives every copy its own value; <b>Each row</b> gives copies of a row the same value.</li>
    </ul>
    <p>Values stay the same between the designer, the printout and exports. <b>Re-roll</b> draws new random values.</p>
  </section>

  <section class="section" id="layers">
    <h2>Layers, groups and lists</h2>
    <h3>Layers</h3>
    <p>The layers button at the top of the right panel opens the <b>Layers</b> list: everything on this side of the card, front-most first. Click a row to select it, tick the boxes (or <kbd>Shift</kbd>/<kbd>⌘</kbd>-click) to select several, and drag rows to change what's in front.</p>
    <h3>Groups</h3>
    <ul>
      <li>Select several elements and press <kbd>⌘G</kbd> (or <b>Group</b>). Clicking any member then selects the whole group, and it moves as one. <kbd>⇧⌘G</kbd> or <b>Ungroup</b> splits it again.</li>
      <li>Double-click a group in Layers (or edit <b>Name</b>) to rename it. Drag elements into or out of a group in Layers.</li>
      <li>To change one member, tick just that row in Layers.</li>
    </ul>
    <h3>Lists</h3>
    <p>With a group selected, <b>Arrange</b> sets how its members sit:</p>
    <ul>
      <li><b>Free</b> — members stay exactly where you put them.</li>
      <li><b>Horizontal list</b> — side by side; <b>Vertical list</b> — one under another. Both start at the group's top-left corner and follow the Layers order, top first.</li>
      <li>In a list, each text takes only the room its words need on that card, and a text or picture with nothing to show takes no room at all — so the rest close up.</li>
      <li><b>Gap</b> sets the space between members.</li>
    </ul>
    <div class="tip">Lists are great for icon rows and keyword lines: a cost icon, then the cost, then a type — and cards without a type simply don't show the gap.</div>
  </section>

  <section class="section" id="tags">
    <h2>Tags</h2>
    <p>Write a tag in square brackets, like <code>[energy]</code>, in any text or data, and every card shows a word or picture in its place — <em>“Gain 1 [energy]”</em> becomes “Gain 1 ⚡”. Curly braces stay for columns, so <em>“Gain {Amount} [energy]”</em> uses both.</p>
    <ol>
      <li>Open the <b>Tags</b> view and click <b>+ Add tag</b>.</li>
      <li>Give it a name (with or without the brackets).</li>
      <li>Choose <b>Text</b> to replace it with words, or <b>Image</b> to replace it with a picture: an <b>Icon</b> from the library (with a color), a <b>Link</b>, or an <b>Upload</b>.</li>
    </ol>
    <p>The preview shows how each tag looks. Tags are stored in your spreadsheet's <b>tags</b> tab, so you can also edit them there and click <b>Reload</b>.</p>
  </section>

  <section class="section" id="backs">
    <h2>Card backs</h2>
    <p>Click the round flip button at the top left of the card to design the back — it works just like the front, with its own background color.</p>
    <ul>
      <li>If nothing on the back changes from card to card, every card shares one back.</li>
      <li>Add a Data field, a <code>{Column}</code> in text, a Generated value, or a picture chosen by value, and each card gets its own back.</li>
    </ul>
  </section>

  <section class="section" id="print">
    <h2>Print</h2>
    <p>The <b>Print</b> view lays out your cards on pages:</p>
    <ul>
      <li><b>Paper</b> — Letter or A4; <b>Orientation</b> — portrait or landscape.</li>
      <li><b>Gap</b> between cards — none, 1/16 in or 1/8 in.</li>
      <li><b>Cut lines</b> — crop marks, card outlines, or none.</li>
      <li><b>Copies</b> — one of each card, or the number in a column such as <b>Count</b> or <b>Qty</b>.</li>
      <li><b>Backs</b> — fronts only, or double-sided, flipping on the long or short edge to match your printer. Each page of backs lines up behind its fronts.</li>
    </ul>
    <p>Click <b>Print</b> and choose your printer, or <b>Save as PDF</b>. Print at 100% (no “fit to page”) so cards come out the right size.</p>
  </section>

  <section class="section" id="export">
    <h2>Export</h2>
    <ul>
      <li><b>For</b> — <b>Tabletop Simulator</b> (sheets of up to 10 × 7 cards, with the back in the last slot), <b>Screentop</b>, or <b>Separate images</b> (one picture per card).</li>
      <li><b>Size</b> — Standard (up to 4096 px), Large (8192 px) or Small (2048 px).</li>
      <li><b>Copies</b> — one of each, or from a number column.</li>
      <li><b>Rounded corners</b> — cut the corners like your card's corner radius.</li>
    </ul>
    <p><b>Download</b> makes a zip of the images. <b>Get links</b> hosts them on zapsheets.com at links that never change — paste them into Tabletop Simulator's <b>Custom Deck</b> dialog. When you save the layout, fetch new data or edit the Data view, the pictures behind the links are replaced with your latest cards. The links carry a share code, not your spreadsheet's ID.</p>
    <div class="note">Tabletop Simulator keeps its own copy of downloaded pictures, so a game may show old cards until it downloads them again. If you add cards, raise <b>Number</b> in the Custom Deck settings to match.</div>
  </section>

  <section class="section" id="keys">
    <h2>Keyboard shortcuts</h2>
    <p><kbd>⌘</kbd> is <kbd>Ctrl</kbd> on Windows.</p>
    <table class="keys">
      <tr><td><kbd>⌘S</kbd></td><td>Save everything to the sheet</td></tr>
      <tr><td><kbd>⌘Z</kbd> / <kbd>⇧⌘Z</kbd></td><td>Undo / redo</td></tr>
      <tr><td><kbd>⌘C</kbd> / <kbd>⌘X</kbd> / <kbd>⌘V</kbd></td><td>Copy / cut / paste elements — into any side, tab or spreadsheet</td></tr>
      <tr><td><kbd>⌘D</kbd></td><td>Duplicate the selection</td></tr>
      <tr><td><kbd>⌘G</kbd> / <kbd>⇧⌘G</kbd></td><td>Group / ungroup</td></tr>
      <tr><td><kbd>⌘A</kbd></td><td>Select everything on this side</td></tr>
      <tr><td><kbd>Shift</kbd> or <kbd>⌘</kbd>-click</td><td>Add to (or remove from) the selection</td></tr>
      <tr><td>Click inside a selection</td><td>Make that element the key object for lining up (click it again to clear)</td></tr>
      <tr><td>Arrow keys</td><td>Nudge 1 pt — with <kbd>Shift</kbd>, 10 pt</td></tr>
      <tr><td><kbd>Alt</kbd> while dragging</td><td>Fine moves</td></tr>
      <tr><td><kbd>Shift</kbd> on a corner</td><td>Keep a shape's proportions</td></tr>
      <tr><td><kbd>Shift</kbd> while rotating</td><td>Snap to 15° steps</td></tr>
      <tr><td><kbd>Delete</kbd></td><td>Delete the selection</td></tr>
      <tr><td><kbd>Esc</kbd></td><td>Deselect, or cancel an edit</td></tr>
      <tr><td>Double-click</td><td>Edit a Text element or Data field in place · choose an image · rename a group in Layers</td></tr>
    </table>
  </section>

  <section class="section" id="plans">
    <h2>Plans</h2>
    <div class="cards">
      <div class="card"><b>Free week</b><p>Every feature for 7 days from sign-up. No card needed.</p></div>
      <div class="card"><b>$4.99/month</b><p>Keep designing after the free week: the designer, tags, printing, exports, hosted links and picture uploads. Menu → <b>Subscription</b> → <b>Subscribe</b>. Cancel any time from <b>Manage Billing</b>.</p></div>
      <div class="card"><b>After the free week</b><p>Without a subscription, CardBoard opens read-only: you can look through your cards and data, and your sheet and layouts stay exactly as they are. Hosted links keep working.</p></div>
      <div class="card"><b>Codes</b><p>Got a code from us? Menu → <b>Enter a code</b>. It unlocks every feature for its time — a week, a month, a year or for good — starting when your current time runs out. Each code works once per account.</p></div>
    </div>
  </section>

  <section class="section" id="trouble">
    <h2>Troubleshooting</h2>
    <ul>
      <li><b>“Couldn't read the sheet”</b> — check the spreadsheet is shared with <code>editor@zapsheets-480701.iam.gserviceaccount.com</code> as an <b>Editor</b>.</li>
      <li><b>Changes made in Google Sheets don't show</b> — menu → <b>Fetch</b>.</li>
      <li><b>A cell won't save from the card</b> — it holds a formula; change it in Google Sheets instead.</li>
      <li><b>A picture says “Picture didn't load”</b> — open the link in a private browser window. Drive files must be shared as “Anyone with the link”; Dropbox links must be shared links.</li>
      <li><b>Text is cut off</b> — make the box bigger, the font smaller, or reduce the padding.</li>
      <li><b>Printed cards are the wrong size</b> — print at 100% scale, with “fit to page” off.</li>
      <li><b>No code email</b> — check spam, and wait a minute before asking for another.</li>
      <li><b>The app is read-only</b> — your free week or code time has ended; subscribe or enter a code.</li>
    </ul>
  </section>
</div>
</body>
</html>
