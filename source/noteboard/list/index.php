<?php
/**
 * NoteBoard list view — shows all games with note counts.
 * URL: /{sheet_id}/noteboard
 */

$_rp = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip BASE_PATH if set
$_bp = '';
$_bpFile = __DIR__ . '/../../../dotEnv.php';
if (file_exists($_bpFile)) {
    require_once $_bpFile;
    $_bp = rtrim($_ENV['BASE_PATH'] ?? '', '/');
    if ($_bp !== '' && str_starts_with($_rp, $_bp)) {
        $_rp = substr($_rp, strlen($_bp)) ?: '/';
    }
}

preg_match('#^(.*?)(?:sheets/)?([A-Za-z0-9_\-]+)/noteboard/?$#', $_rp, $_bm);
$_base     = (isset($_bm[1]) && $_bm[1] !== '') ? $_bm[1] : '/';
if (substr($_base, -1) !== '/') $_base .= '/';
$_sheet_id = $_bm[2] ?? '';

$_sheets_dir = __DIR__ . '/../../../sheets/' . $_sheet_id . '/';

// Load noteboard-index.json  (hash → game_name)
$_nb_index_path = $_sheets_dir . 'noteboard-index.json';
$_nb_index      = file_exists($_nb_index_path)
    ? (json_decode(file_get_contents($_nb_index_path), true) ?: [])
    : [];

// Build game list — only include games that have a notes file (tab exists)
$_games = [];
foreach ($_nb_index as $_hash => $_name) {
    $_safe       = str_replace(['/', '\\'], '-', $_name);
    $_notes_file = $_sheets_dir . 'notes-' . $_safe . '-en.json';
    if (!file_exists($_notes_file)) continue;   // skip — notes tab not set up yet
    $_notes   = json_decode(file_get_contents($_notes_file), true) ?: [];
    $_games[] = [
        'name'  => $_name,
        'hash'  => $_hash,
        'safe'  => $_safe,
        'notes' => $_notes,
    ];
}

// Sort alphabetically
usort($_games, fn($a, $b) => strcasecmp($a['name'], $b['name']));

$_total_notes = array_sum(array_map(fn($g) => count($g['notes']), $_games));

// Suggestions for Add Topic: names in the index that have NO notes file yet
$_suggestions = [];
foreach ($_nb_index as $_hash => $_sname) {
    $_ssafe = str_replace(['/', '\\'], '-', $_sname);
    if (!file_exists($_sheets_dir . 'notes-' . $_ssafe . '-en.json')) {
        $_suggestions[] = $_sname;
    }
}
sort($_suggestions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>NoteBoard</title>
<link rel="icon" type="image/png" href="images/nb_icon_180.png" />
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2')  format('woff2'),url('fonts/DINBlack.ttf')  format('truetype'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf') format('truetype'); }

*, *::before, *::after { box-sizing:border-box; }
body { margin:0; background:#f3f2ef; font-family:'DINRegular',Arial,sans-serif; color:#111; }

/* ── Top bar ─────────────────────────────────────────── */
.top-bar {
  background:#1a1a2e; color:#fff;
  padding:.75rem 1.25rem;
  position:sticky; top:0; z-index:100;
}
.top-bar-inner {
  max-width:760px; margin:0 auto;
  display:flex; align-items:center; gap:.75rem;
}
.top-bar-left { flex:1; min-width:0; }
.top-bar h1 {
  font-family:'DINBlack',sans-serif; font-size:1rem;
  margin:0; letter-spacing:.03em;
}
.top-bar .sub { font-size:.72rem; opacity:.55; display:block; margin-top:.05rem; letter-spacing:.01em; }
.nb-note { color:#A8C8F0; }
.nb-board { color:#FFB36B; }
.top-bar-stat {
  font-family:'DINBlack',sans-serif; font-size:.72rem;
  text-transform:uppercase; letter-spacing:.06em;
  background:rgba(255,255,255,.12); border-radius:6px;
  padding:.3rem .75rem; white-space:nowrap; flex-shrink:0;
}

/* ── Account menu ────────────────────────────────────── */
.account-menu-wrap { position:relative; flex-shrink:0; }
.nb-menu-btn {
  display:inline-flex; align-items:center; justify-content:center;
  background:rgba(255,255,255,.15); color:#fff;
  border:1px solid rgba(255,255,255,.3); border-radius:6px;
  padding:.38rem .5rem; cursor:pointer; transition:background .15s;
}
.nb-menu-btn:hover { background:rgba(255,255,255,.25); }
.account-menu {
  display:none; position:absolute; top:calc(100% + .4rem); right:0;
  background:#1a1a2e; border:1px solid rgba(255,255,255,.2);
  border-radius:8px; min-width:130px; z-index:300;
  box-shadow:0 6px 20px rgba(0,0,0,.4); overflow:hidden;
}
.account-menu.open { display:block; }
.account-menu-item {
  display:block; width:100%; background:none; border:none;
  color:rgba(255,255,255,.85); text-align:left; cursor:pointer;
  font-family:'DINBlack',sans-serif; font-size:.72rem;
  text-transform:uppercase; letter-spacing:.07em;
  padding:.6rem 1rem; transition:background .12s;
}
.account-menu-item:hover { background:rgba(255,255,255,.1); color:#fff; }

/* ── Profile dialog ──────────────────────────────────── */
.nb-profile-overlay {
  display:none; position:fixed; inset:0;
  background:rgba(0,0,0,.45); z-index:1000;
  align-items:center; justify-content:center;
}
.nb-profile-overlay.open { display:flex; }
.nb-profile-dialog {
  background:#fff; border-radius:10px;
  padding:1.4rem; width:min(380px,92vw);
  box-shadow:0 8px 32px rgba(0,0,0,.22);
  display:flex; flex-direction:column; gap:.75rem;
}
.nb-profile-dialog h2 {
  font-family:'DINBlack',sans-serif; font-size:.95rem; margin:0; color:#111;
}
.nb-plabel {
  display:flex; flex-direction:column; gap:.28rem;
  font-family:'DINBlack',sans-serif; font-size:.62rem;
  text-transform:uppercase; letter-spacing:.05em; color:#999;
}
.nb-pinput {
  font-family:'DINRegular',sans-serif; font-size:.82rem; color:#222;
  border:1px solid #ddd; border-radius:6px; padding:.38rem .55rem;
  outline:none; width:100%; box-sizing:border-box; background:#fff;
  transition:border-color .15s;
}
.nb-pinput:focus { border-color:#1a1a2e; }
.nb-plog {
  background:#0f172a; border-radius:6px;
  padding:.75rem 1rem; max-height:7rem;
  overflow-y:auto; font-family:monospace; font-size:.75rem;
  line-height:1.6; display:none;
}
.nb-plog-line         { display:block; color:#94a3b8; }
.nb-plog-line.ok      { color:#4ade80; }
.nb-plog-line.error   { color:#f87171; }
.nb-plog-line.info    { color:#60a5fa; }
.nb-pdialog-actions   { display:flex; align-items:center; justify-content:flex-end; gap:.5rem; }
.nb-pclose {
  font-family:'DINBlack',sans-serif; font-size:.7rem;
  text-transform:uppercase; letter-spacing:.05em;
  background:none; color:#999; border:1px solid #ddd;
  border-radius:6px; padding:.42rem .9rem; cursor:pointer;
}
.nb-pclose:hover { background:#f5f5f5; color:#333; }
.nb-pclose:disabled { opacity:.4; cursor:default; }
.nb-psave {
  font-family:'DINBlack',sans-serif; font-size:.72rem;
  text-transform:uppercase; letter-spacing:.05em;
  background:#16a34a; color:#fff; border:none;
  border-radius:6px; padding:.42rem .9rem;
  cursor:pointer; transition:background .15s;
}
.nb-psave:hover:not(:disabled) { background:#15803d; }
.nb-psave:disabled { opacity:.45; cursor:default; }

/* ── Content ─────────────────────────────────────────── */
.content { max-width:760px; margin:0 auto; padding:1.25rem 1rem 3rem; }

.empty-state {
  text-align:center; padding:4rem 1rem; color:#aaa; font-size:.9rem;
}
.empty-state a {
  color:#c8860a; text-decoration:none; font-family:'DINBlack',sans-serif;
  font-size:.8rem; text-transform:uppercase; letter-spacing:.06em;
}

/* ── Game card ───────────────────────────────────────── */
.game-card {
  background:#fff; border-radius:10px;
  box-shadow:0 1px 5px rgba(0,0,0,.09);
  margin-bottom:.75rem; overflow:hidden;
}

.game-header {
  display:flex; align-items:center; gap:.75rem;
  padding:.8rem 1rem;
  cursor:pointer;
  user-select:none;
  transition:background .13s;
}
.game-header:hover { background:#f9f8f6; }

.game-name {
  font-family:'DINBlack',sans-serif; font-size:.92rem;
  text-transform:uppercase; letter-spacing:.05em; color:#1a1a1a; flex:1;
}
.note-count {
  font-family:'DINBlack',sans-serif; font-size:.7rem;
  text-transform:uppercase; letter-spacing:.05em;
  border-radius:20px; padding:.15rem .6rem; white-space:nowrap;
}
.note-count.has-notes  { background:#c8860a; color:#fff; }
.note-count.zero-notes { background:#e8e5e0; color:#999; }

.chevron {
  color:#ccc; font-size:.7rem; transition:transform .22s ease; flex-shrink:0;
}
.game-card.open .chevron { transform:rotate(180deg); }

/* ── Sub-bar ─────────────────────────────────────────── */
.game-sub-bar {
  background:#1a1a1a;
  display:flex; align-items:center;
  padding:.45rem 1rem;
  gap:.5rem;
}
.action-btn {
  font-family:'DINBlack',sans-serif; font-size:.68rem;
  text-transform:uppercase; letter-spacing:.07em;
  background:rgba(255,255,255,.12); color:rgba(255,255,255,.8);
  border:none; border-radius:6px; padding:.3rem .75rem;
  cursor:pointer; transition:background .13s, color .13s;
}
.action-btn:hover { background:rgba(255,255,255,.25); color:#fff; }
.action-btn.copied { background:#16a34a; color:#fff; }
.action-btn-notes { background:rgba(200,134,10,.25); color:#FFB36B; }
.action-btn-notes:hover { background:rgba(200,134,10,.45); color:#FFD09A; }

/* ── Notes list ──────────────────────────────────────── */
.notes-body {
  display:none; padding:.65rem .85rem .85rem;
  border-top:1px solid #f0ede8;
}
.game-card.open .notes-body { display:block; }

.note-item {
  padding:.6rem .7rem; border-radius:7px;
  background:#fafaf8; border:1px solid #ede9e3;
  margin-bottom:.5rem;
}
.note-item:last-child { margin-bottom:0; }
.note-meta {
  display:flex; justify-content:space-between; align-items:baseline;
  margin-bottom:.28rem; gap:.5rem;
}
.note-author {
  font-family:'DINBlack',sans-serif; font-size:.75rem;
  color:#333; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.note-date { font-size:.68rem; color:#bbb; white-space:nowrap; flex-shrink:0; }
.note-text { font-size:.86rem; color:#444; line-height:1.55; white-space:pre-wrap; }

.no-notes-msg {
  font-size:.82rem; color:#bbb; font-style:italic; padding:.25rem 0;
}

/* ── Search ──────────────────────────────────────────── */
.search-bar {
  background:#22223a;
  padding:.55rem 1.25rem;
  position:sticky; top:52px; z-index:99;
}
.search-bar-inner { max-width:760px; margin:0 auto; }
.search-input {
  width:100%; padding:.5rem .85rem;
  font-family:'DINRegular',Arial,sans-serif; font-size:.88rem; color:#fff;
  background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.18);
  border-radius:7px; outline:none;
  transition:background .15s, border-color .15s;
}
.search-input::placeholder { color:rgba(255,255,255,.4); }
.search-input:focus { background:rgba(255,255,255,.16); border-color:rgba(255,255,255,.35); }

.no-results {
  text-align:center; padding:3rem 1rem; color:#bbb; font-size:.88rem; display:none;
}

/* ── Add Topic dialog ────────────────────────────────── */
.nb-overlay {
  display:none; position:fixed; inset:0; z-index:500;
  background:rgba(0,0,0,.45); align-items:center; justify-content:center;
  padding:1rem;
}
.nb-overlay.open { display:flex; }
.nb-dialog {
  background:#fff; border-radius:14px; padding:1.75rem 1.75rem 1.5rem;
  width:100%; max-width:400px;
  box-shadow:0 8px 40px rgba(0,0,0,.22);
}
.nb-dialog h3 {
  font-family:'DINBlack',sans-serif; font-size:1rem;
  text-transform:uppercase; letter-spacing:.07em; margin:0 0 1rem; color:#111;
}
@keyframes nbshake {
  0%,100%{transform:translateX(0)} 30%{transform:translateX(-6px)} 70%{transform:translateX(6px)}
}
.nb-dialog-actions { display:flex; gap:.55rem; justify-content:flex-end; margin-top:.85rem; }
.nb-btn {
  font-family:'DINBlack',sans-serif; font-size:.75rem;
  text-transform:uppercase; letter-spacing:.07em;
  border:none; border-radius:7px; padding:.5rem 1.1rem; cursor:pointer;
  transition:background .13s;
}
.nb-btn-cancel { background:#f0ede8; color:#555; }
.nb-btn-cancel:hover { background:#e4e0da; }
.nb-btn-add { background:#c8860a; color:#fff; }
.nb-btn-add:hover:not(:disabled) { background:#a06d08; }
.nb-btn-add:disabled { opacity:.5; cursor:default; }
.nb-error {
  font-size:.78rem; color:#b91c1c; margin-top:.45rem; display:none;
}

/* ── Combobox (Add Topic) ─────────────────────────────── */
.nb-combo-wrap { position:relative; }
.nb-combo-wrap input {
  display:block; width:100%; padding:.65rem .85rem;
  font-family:'DINRegular',Arial,sans-serif; font-size:.95rem; color:#111;
  border:1.5px solid #d0ccc5; border-radius:8px; outline:none;
  transition:border-color .15s; background:#fafaf8; margin:0;
}
.nb-combo-wrap input:focus { border-color:#c8860a; background:#fff; }
.nb-combo-wrap input.shake { animation:nbshake .28s ease-in-out; }
.nb-combo-drop {
  display:none; position:absolute; top:calc(100% + 3px); left:0; right:0; z-index:600;
  background:#fff; border:1.5px solid #d0ccc5; border-radius:8px;
  box-shadow:0 4px 18px rgba(0,0,0,.14); max-height:190px; overflow-y:auto;
}
.nb-combo-drop.open { display:block; }
.nb-combo-opt {
  padding:.52rem .85rem; font-size:.9rem; color:#111; cursor:pointer;
  transition:background .1s, color .1s;
}
.nb-combo-opt:hover, .nb-combo-opt.active { background:#1a1a1a; color:#fff; }
</style>
</head>
<body>

<!-- Top bar -->
<div class="top-bar">
  <div class="top-bar-inner">
    <div class="top-bar-left">
      <h1><span class="nb-note">Note</span><span class="nb-board">Board</span></h1>
      <span class="sub" id="nbSubTitle"><?= htmlspecialchars($_sheet_id) ?></span>
    </div>
    <div class="top-bar-stat"><?= $_total_notes ?> note<?= $_total_notes !== 1 ? 's' : '' ?></div>
    <div class="account-menu-wrap">
      <button class="nb-menu-btn" id="nbAccountBtn" onclick="toggleAccountMenu()" title="Menu">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <circle cx="12" cy="7.5" r="4.5"/>
          <path d="M3.5 21c0-4.14 3.81-7.5 8.5-7.5s8.5 3.36 8.5 7.5"/>
        </svg>
      </button>
      <div class="account-menu" id="nbAccountMenu">
        <button class="account-menu-item" onclick="accountMenuProfile()">Profile</button>
        <button class="account-menu-item" onclick="accountMenuHelp()">Help</button>
      </div>
    </div>
  </div>
</div>

<!-- Profile dialog -->
<div class="nb-profile-overlay" id="nbProfileOverlay">
  <div class="nb-profile-dialog">
    <h2>Profile</h2>
    <div style="display:flex;flex-direction:column;gap:.65rem">
      <label class="nb-plabel">Name<input  type="text"  id="nbProfileName"  class="nb-pinput" /></label>
      <label class="nb-plabel">Email<input type="email" id="nbProfileEmail" class="nb-pinput" /></label>
      <label class="nb-plabel">Phone<input type="tel"   id="nbProfilePhone" class="nb-pinput" /></label>
    </div>
    <div class="nb-plog" id="nbProfileLog"></div>
    <div class="nb-pdialog-actions">
      <button class="nb-pclose" id="nbProfileCancelBtn" onclick="closeProfileDialog()">Cancel</button>
      <button class="nb-psave"  id="nbProfileSaveBtn"  onclick="submitProfile()">Save</button>
    </div>
  </div>
</div>

<!-- Search bar -->
<div class="search-bar">
  <div class="search-bar-inner" style="display:flex;gap:.6rem;align-items:center;">
    <input type="search" id="searchInput" class="search-input"
      placeholder="Search games or notes…"
      oninput="filterCards(this.value)"
      autocomplete="off" spellcheck="false"
      style="flex:1;width:auto;" />
    <button class="action-btn" onclick="openAddTopic()"
      style="white-space:nowrap;flex-shrink:0;padding:.5rem .9rem;">+ Topic</button>
  </div>
</div>

<!-- Add Topic dialog -->
<div class="nb-overlay" id="nbOverlay" onclick="if(event.target===this)closeAddTopic()">
  <div class="nb-dialog">
    <h3>New Topic</h3>
    <div class="nb-combo-wrap">
      <input type="text" id="nbTopicInput" placeholder="Choose a game or type a new topic…"
        autocomplete="off" spellcheck="false" />
      <div class="nb-combo-drop" id="nbTopicDrop"></div>
    </div>
    <div class="nb-error" id="nbError"></div>
    <div class="nb-dialog-actions">
      <button class="nb-btn nb-btn-cancel" onclick="closeAddTopic()">Cancel</button>
      <button class="nb-btn nb-btn-add"    id="nbAddBtn" onclick="submitTopic()">Add</button>
    </div>
  </div>
</div>

<!-- Content -->
<div class="content">

<?php if (empty($_games)): ?>
  <div class="empty-state">
    <p>No games found.</p>
    <p style="margin-top:.5rem;"><a href="noteboard">Set up NoteBoard →</a></p>
  </div>
<?php else: ?>

  <?php foreach ($_games as $_g): ?>
  <?php
    $_count     = count($_g['notes']);
    $_game_html = htmlspecialchars($_g['name']);
    $_hash      = htmlspecialchars($_g['hash']);
    $_card_id   = 'card-' . htmlspecialchars($_g['hash']);
  ?>
  <?php
    // Build a searchable text blob: game name + all note texts + authors
    $_search_blob = strtolower($_g['name']);
    foreach ($_g['notes'] as $_sn) {
        $_search_blob .= ' ' . strtolower($_sn['note'] ?? '') . ' ' . strtolower($_sn['name'] ?? '');
    }
  ?>
  <div class="game-card" id="<?= $_card_id ?>" data-search="<?= htmlspecialchars($_search_blob) ?>">

    <div class="game-header" onclick="toggleCard('<?= $_card_id ?>')">
      <span class="game-name"><?= $_game_html ?></span>
      <span class="note-count <?= $_count > 0 ? 'has-notes' : 'zero-notes' ?>">
        <?= $_count ?> note<?= $_count !== 1 ? 's' : '' ?>
      </span>
      <span class="chevron">▼</span>
    </div>

    <div class="game-sub-bar">
      <button class="action-btn"
        onclick="copyFeedbackLink(this,'<?= $_hash ?>','<?= htmlspecialchars($_sheet_id) ?>')">
        Copy Feedback Link
      </button>
      <?php if ($_count > 0): ?>
      <button class="action-btn action-btn-notes"
        onclick="viewNotes('<?= $_card_id ?>')">
        View Notes
      </button>
      <?php endif; ?>
    </div>

    <div class="notes-body">
      <?php if (empty($_g['notes'])): ?>
        <p class="no-notes-msg">No notes yet — share the feedback link to collect responses.</p>
      <?php else: ?>
        <?php foreach (array_reverse($_g['notes']) as $_note): ?>
        <div class="note-item">
          <div class="note-meta">
            <span class="note-author"><?= $_note['name'] ? htmlspecialchars($_note['name']) : 'Anonymous' ?></span>
            <span class="note-date"><?= htmlspecialchars($_note['date'] ?? '') ?></span>
          </div>
          <div class="note-text"><?= htmlspecialchars($_note['note'] ?? '') ?></div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
  <?php endforeach; ?>

<?php endif; ?>

  <div class="no-results" id="noResults">No games match your search.</div>

</div><!-- /.content -->

<script>
(function() {
  'use strict';

  var APP_BASE = (function() {
    var b = document.querySelector('base');
    return b ? b.getAttribute('href') : '/';
  })();

  var SHEET_ID       = <?= json_encode($_sheet_id) ?>;
  var NB_SUGGESTIONS = <?= json_encode(array_values($_suggestions)) ?>;

  var myName = '', myEmail = '', myPhone = '';

  // ── Load profile from settings.json ──────────────────
  (function() {
    var url = APP_BASE + 'sheets/' + SHEET_ID + '/settings.json';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url);
    xhr.onload = function() {
      if (xhr.status !== 200) return;
      var settings;
      try { settings = JSON.parse(xhr.responseText); } catch(e) { return; }
      if (!Array.isArray(settings) || !settings.length) return;
      var keys   = Object.keys(settings[0] || {});
      var valCol = '';
      for (var ki = 0; ki < keys.length; ki++) {
        if (keys[ki] !== 'My Name') { valCol = keys[ki]; break; }
      }
      if (valCol) myName = valCol;
      settings.forEach(function(r) {
        var label = (r['My Name'] || '').trim();
        var val   = valCol ? (r[valCol] || '').trim() : '';
        if (label === 'My Email') myEmail = val;
        if (label === 'My Phone') myPhone = val;
      });
      var parts = [myName, myEmail, myPhone].filter(Boolean);
      if (parts.length) {
        document.getElementById('nbSubTitle').textContent = parts.join('  ·  ');
      }
    };
    xhr.send();
  })();

  // ── Account menu ─────────────────────────────────────
  window.toggleAccountMenu = function() {
    var menu = document.getElementById('nbAccountMenu');
    menu.classList.toggle('open');
  };
  function closeAccountMenu() {
    document.getElementById('nbAccountMenu').classList.remove('open');
  }
  window.accountMenuProfile = function() {
    closeAccountMenu();
    openProfileDialog();
  };
  window.accountMenuHelp = function() {
    closeAccountMenu();
    window.open(APP_BASE + 'noteboard/help', '_blank');
  };
  document.addEventListener('click', function(e) {
    var wrap = document.querySelector('.account-menu-wrap');
    if (wrap && !wrap.contains(e.target)) closeAccountMenu();
  });

  // ── Profile dialog ────────────────────────────────────
  window.openProfileDialog = function() {
    document.getElementById('nbProfileName').value    = myName  || '';
    document.getElementById('nbProfileEmail').value   = myEmail || '';
    document.getElementById('nbProfilePhone').value   = myPhone || '';
    document.getElementById('nbProfileLog').innerHTML = '';
    document.getElementById('nbProfileLog').style.display    = 'none';
    document.getElementById('nbProfileSaveBtn').disabled     = false;
    document.getElementById('nbProfileCancelBtn').disabled   = false;
    document.getElementById('nbProfileCancelBtn').textContent = 'Cancel';
    document.getElementById('nbProfileOverlay').classList.add('open');
  };
  window.closeProfileDialog = function() {
    document.getElementById('nbProfileOverlay').classList.remove('open');
  };
  function _profileLog(msg, type) {
    var log  = document.getElementById('nbProfileLog');
    log.style.display = '';
    var span = document.createElement('span');
    span.className   = 'nb-plog-line ' + (type || 'info');
    span.textContent = msg;
    log.appendChild(span);
    log.scrollTop = log.scrollHeight;
  }
  window.submitProfile = function() {
    var name  = document.getElementById('nbProfileName').value.trim();
    var email = document.getElementById('nbProfileEmail').value.trim();
    var phone = document.getElementById('nbProfilePhone').value.trim();
    if (!name) { _profileLog('Name is required.', 'error'); return; }
    document.getElementById('nbProfileSaveBtn').disabled   = true;
    document.getElementById('nbProfileCancelBtn').disabled = true;
    _profileLog('Saving…', 'info');
    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_BASE + 'push/updateProfile.php');
    var fd = new FormData();
    fd.append('id',    SHEET_ID);
    fd.append('name',  name);
    fd.append('email', email);
    fd.append('phone', phone);
    xhr.onload = function() {
      var result;
      try { result = JSON.parse(xhr.responseText); } catch(e) { result = null; }
      if (!result || result.error) {
        _profileLog('✕  ' + ((result && result.error) || 'Unknown error'), 'error');
        document.getElementById('nbProfileSaveBtn').disabled   = false;
        document.getElementById('nbProfileCancelBtn').disabled = false;
        return;
      }
      myName  = name;
      myEmail = email;
      myPhone = phone;
      var parts = [myName, myEmail, myPhone].filter(Boolean);
      document.getElementById('nbSubTitle').textContent = parts.join('  ·  ');
      _profileLog('✓  Saved', 'ok');
      document.getElementById('nbProfileCancelBtn').disabled    = false;
      document.getElementById('nbProfileCancelBtn').textContent = 'Close';
    };
    xhr.onerror = function() {
      _profileLog('✕  Network error', 'error');
      document.getElementById('nbProfileSaveBtn').disabled   = false;
      document.getElementById('nbProfileCancelBtn').disabled = false;
    };
    xhr.send(fd);
  };

  window.filterCards = function(q) {
    var term  = q.trim().toLowerCase();
    var cards = document.querySelectorAll('.game-card');
    var shown = 0;
    cards.forEach(function(card) {
      var blob    = (card.getAttribute('data-search') || '').toLowerCase();
      var visible = !term || blob.indexOf(term) !== -1;
      card.style.display = visible ? '' : 'none';
      if (visible) shown++;
    });
    var nr = document.getElementById('noResults');
    if (nr) nr.style.display = (cards.length > 0 && shown === 0) ? 'block' : 'none';
  };

  window.toggleCard = function(id) {
    var card = document.getElementById(id);
    if (card) card.classList.toggle('open');
  };

  window.viewNotes = function(id) {
    var card = document.getElementById(id);
    if (!card) return;
    if (!card.classList.contains('open')) card.classList.add('open');
    card.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  window.copyFeedbackLink = function(btn, hash, sheetId) {
    var url = window.location.origin + APP_BASE + sheetId + '/noteboard/' + hash;
    navigator.clipboard.writeText(url).then(function() {
      var orig = btn.textContent;
      btn.textContent = 'Copied!';
      btn.classList.add('copied');
      setTimeout(function() {
        btn.textContent = orig;
        btn.classList.remove('copied');
      }, 2000);
    }).catch(function() {
      window.prompt('Copy this link:', url);
    });
  };

  // ── Add Topic combobox ────────────────────────────────
  (function() {
    var inp   = document.getElementById('nbTopicInput');
    var drop  = document.getElementById('nbTopicDrop');
    var _activeIdx = -1;
    var _blurTimer = null;

    function _getItems(q) {
      var term = (q || '').trim().toLowerCase();
      if (!term) return NB_SUGGESTIONS.slice();
      return NB_SUGGESTIONS.filter(function(s) {
        return s.toLowerCase().indexOf(term) !== -1;
      });
    }

    function _renderDrop(items) {
      drop.innerHTML = '';
      _activeIdx = -1;
      if (!items.length) { drop.classList.remove('open'); return; }
      items.forEach(function(label, i) {
        var div = document.createElement('div');
        div.className   = 'nb-combo-opt';
        div.textContent = label;
        div.addEventListener('mousedown', function(e) {
          e.preventDefault();   // don't blur the input
          inp.value = label;
          drop.classList.remove('open');
        });
        drop.appendChild(div);
      });
      drop.classList.add('open');
    }

    function _setActive(idx) {
      var opts = drop.querySelectorAll('.nb-combo-opt');
      if (_activeIdx >= 0 && _activeIdx < opts.length) opts[_activeIdx].classList.remove('active');
      _activeIdx = idx;
      if (_activeIdx >= 0 && _activeIdx < opts.length) {
        opts[_activeIdx].classList.add('active');
        opts[_activeIdx].scrollIntoView({block:'nearest'});
      }
    }

    inp.addEventListener('focus', function() {
      clearTimeout(_blurTimer);
      _renderDrop(_getItems(inp.value));
    });

    inp.addEventListener('input', function() {
      _renderDrop(_getItems(inp.value));
    });

    inp.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        if (drop.classList.contains('open')) { drop.classList.remove('open'); e.stopPropagation(); }
        else { closeAddTopic(); }
        return;
      }
      if (e.key === 'Enter') {
        e.preventDefault();
        var opts = drop.querySelectorAll('.nb-combo-opt');
        if (_activeIdx >= 0 && _activeIdx < opts.length) {
          inp.value = opts[_activeIdx].textContent;
          drop.classList.remove('open');
        } else {
          submitTopic();
        }
        return;
      }
      if (e.key === 'ArrowDown') {
        e.preventDefault();
        var opts2 = drop.querySelectorAll('.nb-combo-opt');
        _setActive(Math.min(_activeIdx + 1, opts2.length - 1));
        return;
      }
      if (e.key === 'ArrowUp') {
        e.preventDefault();
        _setActive(Math.max(_activeIdx - 1, 0));
        return;
      }
    });

    inp.addEventListener('blur', function() {
      _blurTimer = setTimeout(function() { drop.classList.remove('open'); }, 150);
    });

    // Expose a reset helper for openAddTopic
    window._nbComboReset = function() {
      inp.value = '';
      drop.classList.remove('open');
      drop.innerHTML = '';
      _activeIdx = -1;
    };
  })();

  // ── Add Topic ─────────────────────────────────────────
  window.openAddTopic = function() {
    window._nbComboReset();
    document.getElementById('nbError').style.display = 'none';
    document.getElementById('nbError').textContent   = '';
    document.getElementById('nbAddBtn').disabled     = false;
    document.getElementById('nbAddBtn').textContent  = 'Add';
    document.getElementById('nbOverlay').classList.add('open');
    setTimeout(function() { document.getElementById('nbTopicInput').focus(); }, 50);
  };

  window.closeAddTopic = function() {
    document.getElementById('nbOverlay').classList.remove('open');
  };

  window.submitTopic = function() {
    var inp  = document.getElementById('nbTopicInput');
    var name = inp.value.trim();
    if (!name) {
      inp.classList.remove('shake');
      void inp.offsetWidth;
      inp.classList.add('shake');
      setTimeout(function() { inp.classList.remove('shake'); }, 320);
      inp.focus();
      return;
    }

    var btn = document.getElementById('nbAddBtn');
    var err = document.getElementById('nbError');
    btn.disabled    = true;
    btn.textContent = 'Adding…';
    err.style.display = 'none';

    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_BASE + 'push/addTopic.php');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
      var result;
      try { result = JSON.parse(xhr.responseText); } catch(e) { result = null; }
      if (!result || result.error) {
        err.textContent   = (result && result.error) || 'Something went wrong.';
        err.style.display = 'block';
        btn.disabled      = false;
        btn.textContent   = 'Add';
        return;
      }
      // Success — reload to show the new card
      window.location.reload();
    };
    xhr.onerror = function() {
      err.textContent   = 'Network error.';
      err.style.display = 'block';
      btn.disabled      = false;
      btn.textContent   = 'Add';
    };
    xhr.send('id=' + encodeURIComponent(SHEET_ID) + '&name=' + encodeURIComponent(name));
  };
})();
</script>
</body>
</html>
