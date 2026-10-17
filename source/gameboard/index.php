<?php
/**
 * GameBoard — polished public product page for a single board game.
 * URL: /{sheet_id}/gameboard/{game-slug}
 * Data: games.json (main info) + {game_name}.json from Playbook Sheet (rich content)
 * Public / no sign-in required.
 */
error_reporting(0);

$_bpFile = dirname(__DIR__, 2) . '/dotEnv.php';
if (file_exists($_bpFile)) { require_once $_bpFile; }

$_rp   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_bp   = rtrim($_ENV['BASE_PATH'] ?? '', '/');
$_rps  = ($_bp !== '' && str_starts_with($_rp, $_bp)) ? substr($_rp, strlen($_bp)) : $_rp;

preg_match('#^/([A-Za-z0-9_\-]+)/gameboard(?:/([^/?]*))?#', $_rps, $_m);
$_sheetId  = $_m[1] ?? '';
$_gameSlug = urldecode(trim($_m[2] ?? ''));

if (!$_sheetId) { http_response_code(404); exit; }

$_root    = dirname(__DIR__, 2);
$_sDir    = $_root . '/sheets/' . $_sheetId;
$_appRoot = $_bp . '/';

function _gb_load(string $f): array {
    return file_exists($f) ? (json_decode(file_get_contents($f), true) ?: []) : [];
}

function _gb_slug(string $s): string {
    return strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
}

function _gb_img(string $raw): string {
    if (preg_match('/^=IMAGE\("([^"]*)"\)/i', $raw, $m)) return $m[1];
    return $raw;
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function _gb_embed(string $url): string {
    if (!$url) return '';
    if (preg_match('/youtube\.com\/embed\//', $url)) return $url;
    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([A-Za-z0-9_\-]+)/', $url, $m))
        return 'https://www.youtube.com/embed/' . $m[1];
    if (preg_match('/youtube\.com\/shorts\/([A-Za-z0-9_\-]+)/', $url, $m))
        return 'https://www.youtube.com/embed/' . $m[1];
    return '';  // skip non-YouTube for embed safety
}

// ── Load settings and determine template ────────────────────────────────────
$_settings = _gb_load($_sDir . '/settings.json');
$_template = strtolower(trim($_settings['Gameboard Template'] ?? ''));
// Supported templates: '' / 'classic' = Classic (default), 'opensea' = OpenSea

// ── Load games list ──────────────────────────────────────────────────────────
$_allGames = _gb_load($_sDir . '/games.json');
// Catalog only shows Published games; individual pages work for any game
$_games = array_values(array_filter($_allGames, fn($g) => strtolower(trim($g['Status'] ?? '')) === 'published'));

if (!$_gameSlug) {
    // ── No slug: render the admin catalog ────────────────────────────────────────
    $_pubName  = trim($_settings['Company'] ?? $_settings['Name'] ?? '');
    $_pubLogo  = trim($_settings['Logo URL'] ?? '');
    $_catUrl   = $_appRoot . $_sheetId . '/gameboard/';
    $_logoColor = $_template === 'opensea' ? '#00e5ff' : '#7c9eff';
    $_accentHex = $_template === 'opensea' ? '#0097a7' : '#3b3baa';
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= h($_pubName ?: 'GameBoard') ?></title>
<style>
@font-face { font-family:'DINBlack';   src:url('<?= h($_appRoot) ?>fonts/DINBlack.woff2')  format('woff2'); }
@font-face { font-family:'DINRegular'; src:url('<?= h($_appRoot) ?>fonts/DINMedium.woff2') format('woff2'); }
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --ink:#1a1a2e; --ink-lt:#4a4a6a; --muted:#888; --border:#e2e2ec;
  --surface:#fff; --bg:#f6f5f2; --accent:<?= h($_accentHex) ?>; --nav-bg:#1a1a2e;
<?php if ($_template === 'opensea'): ?>
  --ink:#0d1b2a; --ink-lt:#2c4a6e; --muted:#6b8fae; --border:#c8dcea;
  --surface:#fff; --bg:#f0f6fb; --nav-bg:#0d1b2a;
<?php endif; ?>
}
body { background:var(--bg); font-family:'DINRegular',Arial,sans-serif; color:var(--ink); min-height:100vh; }
a { color:inherit; text-decoration:none; }

/* ── Header ── */
.gb-header { background:var(--nav-bg); color:#fff; display:flex; align-items:center; justify-content:space-between; padding:.6rem 1.25rem; gap:1rem; position:sticky; top:0; z-index:100; }
.gb-header-left { display:flex; align-items:center; gap:.6rem; min-width:0; }
.gb-logo { font-family:'DINBlack',sans-serif; font-size:1.1rem; letter-spacing:.07em; color:<?= h($_logoColor) ?>; flex-shrink:0; }
.gb-meta { font-size:.72rem; opacity:.6; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.gb-header-right { display:flex; align-items:center; gap:.5rem; flex-shrink:0; }

/* User icon + menu (matches T&MBoard) */
.btn-user-icon {
  width:32px; height:32px; border-radius:50%; border:1.5px solid rgba(255,255,255,.3);
  background:transparent; color:#fff; cursor:pointer; display:flex; align-items:center;
  justify-content:center; transition:background .14s, border-color .14s;
}
.btn-user-icon:hover, .btn-user-icon.signed-in { background:rgba(255,255,255,.15); border-color:#fff; }
.btn-user-icon.signed-in { border-color:<?= h($_logoColor) ?>; color:<?= h($_logoColor) ?>; }
.user-menu-wrap { position:relative; }
.user-menu {
  display:none; position:absolute; top:calc(100% + 6px); right:0;
  background:#1e1e30; border:1px solid rgba(255,255,255,.12); border-radius:8px;
  min-width:140px; box-shadow:0 6px 24px rgba(0,0,0,.4); overflow:hidden; z-index:200;
}
.user-menu.open { display:block; }
.user-menu-item {
  display:block; width:100%; text-align:left; padding:.55rem 1rem;
  font-family:'DINRegular',sans-serif; font-size:.82rem; color:rgba(255,255,255,.8);
  background:none; border:none; cursor:pointer; transition:background .12s;
}
.user-menu-item:hover { background:rgba(255,255,255,.08); }
.user-menu-item.signout { color:#ff6b6b; }
.user-menu-divider { border:none; border-top:1px solid rgba(255,255,255,.1); margin:2px 0; }

/* ── Hero ── */
.cat-hero { background:var(--nav-bg); color:#fff; padding:2.5rem 1.5rem 2rem; text-align:center; }
.cat-hero-title { font-family:'DINBlack',sans-serif; font-size:clamp(1.8rem,6vw,3rem); letter-spacing:.04em; margin-bottom:.5rem; }
.cat-hero-sub { font-size:.92rem; opacity:.55; }

/* ── Grid ── */
.cat-grid { max-width:960px; margin:2rem auto 5rem; padding:0 1.25rem; display:grid; grid-template-columns:repeat(auto-fill,minmax(230px,1fr)); gap:1.1rem; }
.cat-card { background:var(--surface); border-radius:12px; box-shadow:0 2px 14px rgba(0,0,0,.08); overflow:hidden; transition:box-shadow .15s, transform .15s; cursor:pointer; display:flex; flex-direction:column; }
.cat-card:hover { box-shadow:0 6px 24px rgba(0,0,0,.14); transform:translateY(-3px); }
.cat-card-img { width:100%; aspect-ratio:1; object-fit:cover; display:block; }
.cat-card-ph { width:100%; aspect-ratio:1; display:flex; align-items:center; justify-content:center; font-size:3.5rem; opacity:.28; background:linear-gradient(135deg,#dde,#c8c8e8); }
.cat-card-body { padding:.75rem .9rem 1rem; flex:1; display:flex; flex-direction:column; gap:.2rem; }
.cat-card-name { font-family:'DINBlack',sans-serif; font-size:.95rem; color:var(--accent); line-height:1.2; }
.cat-card-summary { font-size:.8rem; color:var(--ink-lt); line-height:1.45; flex:1; margin-top:.1rem; }
.cat-card-meta { display:flex; flex-wrap:wrap; gap:.3rem .65rem; margin-top:.35rem; }
.cat-card-stat { font-size:.72rem; color:var(--muted); }

/* ── Empty ── */
.cat-empty { grid-column:1/-1; padding:4rem 1rem; text-align:center; color:var(--muted); }
.cat-empty-icon { font-size:3rem; margin-bottom:.75rem; }
.cat-empty-title { font-family:'DINBlack',sans-serif; font-size:1rem; color:var(--ink); margin-bottom:.4rem; }
.cat-empty-body { font-size:.85rem; line-height:1.6; }

/* ── Footer ── */
.gb-footer { background:var(--nav-bg); color:rgba(255,255,255,.45); text-align:center; padding:1.4rem; font-size:.75rem; }

/* ── Overlay / Dialog (sign-in + settings) ── */
.gb-overlay {
  display:none; position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:500;
  align-items:center; justify-content:center;
}
.gb-overlay.open { display:flex; }
.gb-dialog {
  background:#fff; border-radius:12px; padding:1.6rem 1.75rem 1.5rem;
  width:90%; max-width:360px; box-shadow:0 8px 40px rgba(0,0,0,.28);
}
.gb-dialog-title { font-family:'DINBlack',sans-serif; font-size:1rem; text-transform:uppercase; letter-spacing:.07em; color:var(--ink); margin-bottom:1rem; }
.gb-field-group { margin-bottom:.7rem; }
.gb-field-group label { display:block; font-size:.75rem; color:var(--muted); margin-bottom:.25rem; }
.gb-field { display:block; width:100%; padding:.55rem .75rem; border:1.5px solid var(--border); border-radius:7px; font-family:'DINRegular',sans-serif; font-size:.9rem; color:var(--ink); outline:none; transition:border-color .14s; }
.gb-field:focus { border-color:var(--accent); }
.gb-err { font-size:.78rem; color:#e53935; min-height:1.1rem; margin:.3rem 0; }
.gb-dialog-actions { display:flex; gap:.5rem; justify-content:flex-end; margin-top:.9rem; }
.btn-cancel { font-family:'DINBlack',sans-serif; font-size:.75rem; text-transform:uppercase; letter-spacing:.07em; padding:.42rem .9rem; border-radius:6px; border:1.5px solid var(--border); background:var(--surface); color:var(--ink-lt); cursor:pointer; }
.btn-cancel:hover { background:#f0f0f8; }
.btn-primary { font-family:'DINBlack',sans-serif; font-size:.75rem; text-transform:uppercase; letter-spacing:.07em; padding:.42rem .9rem; border-radius:6px; border:none; background:var(--accent); color:#fff; cursor:pointer; transition:opacity .13s; }
.btn-primary:disabled { opacity:.45; cursor:default; }
.btn-primary:not(:disabled):hover { opacity:.88; }
#siConfirmPanel { display:none; }

@media(max-width:520px) { .cat-grid { grid-template-columns:1fr 1fr; } }
@media(max-width:360px) { .cat-grid { grid-column:1; } }
</style>
</head>
<body>

<!-- ── Header ── -->
<header class="gb-header">
  <div class="gb-header-left">
    <?php if ($_pubLogo): ?><img src="<?= h($_pubLogo) ?>" alt="" style="height:26px;border-radius:4px;flex-shrink:0;" /><?php endif; ?>
    <span class="gb-logo">GameBoard</span>
    <span class="gb-meta" id="gbMeta"><?= h($_pubName ?: 'Games') ?></span>
  </div>
  <div class="gb-header-right">
    <div class="user-menu-wrap">
      <button class="btn-user-icon" id="gbUserBtn" onclick="toggleGbMenu(event)" title="Menu">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
        </svg>
      </button>
      <div class="user-menu" id="gbMenu">
        <button class="user-menu-item" id="gbMenuProfile" onclick="gbMenuAction('profile')">Profile</button>
        <button class="user-menu-item" onclick="gbMenuAction('settings')">Settings</button>
        <hr class="user-menu-divider" />
        <button class="user-menu-item" onclick="gbMenuAction('fetch')">Fetch</button>
        <hr class="user-menu-divider" />
        <button class="user-menu-item" id="gbMenuSignInOut" onclick="gbMenuAction('signout')">Sign In</button>
      </div>
    </div>
  </div>
</header>

<!-- ── Hero ── -->
<div class="cat-hero">
  <div class="cat-hero-title"><?= h($_pubName ?: 'Our Games') ?></div>
  <?php if (!empty($_games)): ?><div class="cat-hero-sub"><?= count($_games) ?> game<?= count($_games) !== 1 ? 's' : '' ?></div><?php endif; ?>
</div>

<!-- ── Game grid ── -->
<div class="cat-grid" id="catGrid">
<?php if (empty($_games)): ?>
  <div class="cat-empty">
    <div class="cat-empty-icon">🎲</div>
    <div class="cat-empty-title">No published games yet</div>
    <div class="cat-empty-body">Sign in and use Fetch to sync your Games sheet, then mark games as Published to display them here.</div>
  </div>
<?php else:
  foreach ($_games as $g):
    $gName    = trim($g['Name']       ?? '');
    $gSummary = trim($g['Summary']    ?? '');
    $gCount   = trim($g['Count']      ?? '');
    $gDur     = trim($g['Duration']   ?? '');
    $gCover   = _gb_img(trim($g['Cover URL'] ?? $g['Image URL'] ?? ''));
    $gSlug    = _gb_slug($gName);
    if (!$gName) continue;
?>
  <a class="cat-card" href="<?= h($_catUrl . $gSlug) ?>">
    <?php if ($gCover): ?>
    <img class="cat-card-img" src="<?= h($gCover) ?>" alt="<?= h($gName) ?>" loading="lazy" />
    <?php else: ?>
    <div class="cat-card-ph">🎲</div>
    <?php endif; ?>
    <div class="cat-card-body">
      <div class="cat-card-name"><?= h($gName) ?></div>
      <?php if ($gSummary): ?><div class="cat-card-summary"><?= h($gSummary) ?></div><?php endif; ?>
      <div class="cat-card-meta">
        <?php if ($gCount): ?><span class="cat-card-stat">👥 <?= h($gCount) ?></span><?php endif; ?>
        <?php if ($gDur):   ?><span class="cat-card-stat">⏱ <?= h($gDur) ?></span><?php endif; ?>
      </div>
    </div>
  </a>
<?php endforeach; endif; ?>
</div>

<footer class="gb-footer">
  <?= h($_pubName ?: 'GameBoard') ?> &nbsp;·&nbsp; Powered by GameBoard
</footer>

<!-- ── Sign-in dialog ── -->
<div class="gb-overlay" id="signInOverlay" onclick="if(event.target===this)forceCloseSignIn()">
  <div class="gb-dialog" onclick="event.stopPropagation()"
       onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitSignIn(false);}">
    <div class="gb-dialog-title" id="siTitle">Sign In</div>
    <div id="siMainPanel">
      <div class="gb-field-group"><label>Email</label><input class="gb-field" id="siEmail" type="email" placeholder="you@example.com" autocomplete="email" /></div>
      <div class="gb-field-group"><label>Password</label><input class="gb-field" id="siPassword" type="password" placeholder="••••••••" autocomplete="current-password" /></div>
    </div>
    <div id="siConfirmPanel">
      <p style="font-size:.85rem;color:var(--ink-lt);line-height:1.55;margin-bottom:.75rem;">No account found for this email. Create one now?</p>
    </div>
    <div class="gb-err" id="siErr"></div>
    <div class="gb-dialog-actions">
      <button class="btn-cancel" onclick="forceCloseSignIn()">Cancel</button>
      <button class="btn-primary" id="siBtn" onclick="submitSignIn(false)">Sign In</button>
    </div>
  </div>
</div>

<!-- ── Settings dialog ── -->
<div class="gb-overlay" id="settingsOverlay" onclick="if(event.target===this)forceCloseSettings()">
  <div class="gb-dialog" style="max-width:420px" onclick="event.stopPropagation()"
       onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitSettings();}">
    <div class="gb-dialog-title">Settings</div>
    <div class="gb-field-group"><label>Company Name</label><input class="gb-field" id="setCompany" type="text" placeholder="Acme Games LLC" /></div>
    <div class="gb-field-group"><label>Logo URL</label><input class="gb-field" id="setLogo" type="url" placeholder="https://…" /></div>
    <div class="gb-field-group">
      <label>GameBoard Template</label>
      <select class="gb-field" id="setTemplate">
        <option value="">Classic (default)</option>
        <option value="opensea">OpenSea</option>
      </select>
    </div>
    <div class="gb-err" id="setErr"></div>
    <div class="gb-dialog-actions">
      <button class="btn-cancel" onclick="forceCloseSettings()">Cancel</button>
      <button class="btn-primary" id="setBtn" onclick="submitSettings()">Save</button>
    </div>
  </div>
</div>

<script>
var SHEET_ID = <?= json_encode($_sheetId) ?>;
var APP_ROOT = <?= json_encode($_appRoot) ?>;

// ── Auth state ──────────────────────────────────────────────────────────────
var _gbUser = null;
var _siPendingEmail = '', _siPendingPass = '';

(function() {
  try {
    var raw = sessionStorage.getItem('gameboard_owner_' + SHEET_ID);
    if (raw) { _gbUser = JSON.parse(raw); }
  } catch(e) {}
  _updateGbAuth();
})();

function _updateGbAuth() {
  var btn = document.getElementById('gbUserBtn');
  var metaEl = document.getElementById('gbMeta');
  var siOut = document.getElementById('gbMenuSignInOut');
  var profile = document.getElementById('gbMenuProfile');
  if (_gbUser) {
    var name = (_gbUser.bio && (_gbUser.bio['name'] || _gbUser.bio['Name'])) || _gbUser.email || '';
    btn.classList.add('signed-in');
    btn.title = 'Signed in as ' + (_gbUser.email || '');
    if (metaEl) metaEl.textContent = (name || <?= json_encode($_pubName ?: 'Games') ?>) + ' · GameBoard';
    if (siOut)  { siOut.textContent = 'Sign Out'; siOut.className = 'user-menu-item signout'; }
    if (profile) profile.style.display = '';
  } else {
    btn.classList.remove('signed-in');
    btn.title = 'Menu';
    if (metaEl) metaEl.textContent = <?= json_encode($_pubName ?: 'Games') ?>;
    if (siOut)  { siOut.textContent = 'Sign In'; siOut.className = 'user-menu-item'; }
    if (profile) profile.style.display = 'none';
  }
}

// ── Menu ────────────────────────────────────────────────────────────────────
function toggleGbMenu(e) { e.stopPropagation(); document.getElementById('gbMenu').classList.toggle('open'); }
function closeGbMenu() { document.getElementById('gbMenu').classList.remove('open'); }
document.addEventListener('click', closeGbMenu);

function gbMenuAction(action) {
  closeGbMenu();
  if (action === 'profile') {
    if (_gbUser) {
      var n = (_gbUser.bio && (_gbUser.bio['name'] || _gbUser.bio['Name'])) || '—';
      alert('Signed in as: ' + (_gbUser.email || '') + '\nName: ' + n);
    } else { openSignIn(); }
  } else if (action === 'settings') {
    openSettings();
  } else if (action === 'fetch') {
    if (!_gbUser) { openSignIn(); return; }
    var btn = document.getElementById('gbUserBtn');
    btn.style.opacity = '.4';
    var fd = new FormData(); fd.append('id', SHEET_ID);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_ROOT + 'push/setupGameboard.php');
    xhr.onload = function() { location.reload(); };
    xhr.onerror = function() { btn.style.opacity = ''; alert('Fetch failed — network error.'); };
    xhr.send(fd);
  } else if (action === 'signout') {
    if (_gbUser) {
      if (confirm('Sign out?')) {
        try { sessionStorage.removeItem('gameboard_owner_' + SHEET_ID); } catch(e) {}
        _gbUser = null; _updateGbAuth();
      }
    } else { openSignIn(); }
  }
}

// ── Sign-in dialog ──────────────────────────────────────────────────────────
function openSignIn() {
  document.getElementById('siErr').textContent = '';
  document.getElementById('siEmail').value = '';
  document.getElementById('siPassword').value = '';
  document.getElementById('siTitle').textContent = 'Sign In';
  document.getElementById('siBtn').textContent = 'Sign In';
  document.getElementById('siBtn').onclick = function(){ submitSignIn(false); };
  document.getElementById('siMainPanel').style.display = '';
  document.getElementById('siConfirmPanel').style.display = 'none';
  _siPendingEmail = ''; _siPendingPass = '';
  document.getElementById('signInOverlay').classList.add('open');
  setTimeout(function(){ document.getElementById('siEmail').focus(); }, 60);
}
function forceCloseSignIn() { document.getElementById('signInOverlay').classList.remove('open'); }

function submitSignIn(confirmNew) {
  var email = confirmNew ? _siPendingEmail : document.getElementById('siEmail').value.trim();
  var pass  = confirmNew ? _siPendingPass  : document.getElementById('siPassword').value;
  if (!email || !pass) { document.getElementById('siErr').textContent = 'Email and password required.'; return; }
  var btn = document.getElementById('siBtn');
  btn.disabled = true;
  document.getElementById('siErr').textContent = '';
  var fd = new FormData();
  fd.append('email', email); fd.append('password', pass); fd.append('id', SHEET_ID);
  if (confirmNew) fd.append('confirm_new', '1');
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/collabAuth.php');
  xhr.onload = function() {
    btn.disabled = false;
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = {error: xhr.responseText}; }
    if (r.error) { document.getElementById('siErr').textContent = r.error; return; }
    if (r.prompt_create) {
      _siPendingEmail = email; _siPendingPass = pass;
      document.getElementById('siTitle').textContent = 'Create Account';
      document.getElementById('siMainPanel').style.display = 'none';
      document.getElementById('siConfirmPanel').style.display = '';
      document.getElementById('siBtn').textContent = 'Create Account';
      document.getElementById('siBtn').onclick = function(){ submitSignIn(true); };
      return;
    }
    _gbUser = { email: r.email || email, bio: r.bio || {} };
    try { sessionStorage.setItem('gameboard_owner_' + SHEET_ID, JSON.stringify(_gbUser)); } catch(e) {}
    _updateGbAuth();
    forceCloseSignIn();
  };
  xhr.onerror = function() { btn.disabled = false; document.getElementById('siErr').textContent = 'Network error.'; };
  xhr.send(fd);
}

// ── Settings dialog ──────────────────────────────────────────────────────────
function openSettings() {
  document.getElementById('setErr').textContent = '';
  var xhr = new XMLHttpRequest();
  xhr.open('GET', APP_ROOT + 'push/tmGetSettings.php?id=' + encodeURIComponent(SHEET_ID));
  xhr.onload = function() {
    var s = {}; try { s = JSON.parse(xhr.responseText); } catch(e) {}
    document.getElementById('setCompany').value  = s['Company']            || s['Name'] || '';
    document.getElementById('setLogo').value     = s['Logo URL']           || '';
    document.getElementById('setTemplate').value = s['Gameboard Template'] || '';
  };
  xhr.send();
  document.getElementById('settingsOverlay').classList.add('open');
  setTimeout(function(){ document.getElementById('setCompany').focus(); }, 60);
}
function forceCloseSettings() { document.getElementById('settingsOverlay').classList.remove('open'); }
function submitSettings() {
  var btn = document.getElementById('setBtn');
  btn.disabled = true;
  var fd = new FormData();
  fd.append('id',          SHEET_ID);
  fd.append('company',     document.getElementById('setCompany').value.trim());
  fd.append('logo',        document.getElementById('setLogo').value.trim());
  fd.append('gb_template', document.getElementById('setTemplate').value);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/tmSaveSettings.php');
  xhr.onload = function() {
    btn.disabled = false;
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = {error: xhr.responseText}; }
    if (r.error) { document.getElementById('setErr').textContent = r.error; return; }
    forceCloseSettings();
    location.reload();
  };
  xhr.onerror = function() { btn.disabled = false; document.getElementById('setErr').textContent = 'Network error.'; };
  xhr.send(fd);
}

// ── Esc closes open dialogs ──────────────────────────────────────────────────
document.addEventListener('keydown', function(e) {
  if (e.key !== 'Escape') return;
  if (document.getElementById('signInOverlay').classList.contains('open'))  { forceCloseSignIn(); return; }
  if (document.getElementById('settingsOverlay').classList.contains('open')){ forceCloseSettings(); return; }
});
</script>
</body>
</html>
<?php
    exit;
}
// ── END CATALOG ──────────────────────────────────────────────────────────────

$_game = null;
foreach ($_games as $g) {
    if (_gb_slug($g['Name'] ?? '') === _gb_slug($_gameSlug)) { $_game = $g; break; }
}

if (!$_game) { http_response_code(404); exit; }

// ── Core fields from games.json ──────────────────────────────────────────────
$_name        = trim($_game['Name']           ?? '');
$_summary     = trim($_game['Summary']        ?? '');
$_description = trim($_game['Description']    ?? '');
$_count       = trim($_game['Count']          ?? '');
$_duration    = trim($_game['Duration']       ?? '');
$_availability= trim($_game['Availability']   ?? '');
$_status      = trim($_game['Status']         ?? '');
$_coverUrl    = _gb_img(trim($_game['Cover URL']    ?? ''));
$_imageUrl    = _gb_img(trim($_game['Image URL']    ?? ''));
$_videoUrl    = trim($_game['Video URL']      ?? '');
$_pageUrl     = trim($_game['Page URL']       ?? '');
$_sellsheet   = trim($_game['Sellsheet URL']  ?? '');
$_playUrl     = trim($_game['Play URL']       ?? '');
$_printUrl    = trim($_game['Print URL']      ?? '');
$_rulesUrl    = trim($_game['Rules URL']      ?? '');
$_pbSheetId   = trim($_game['Playbook Sheet ID'] ?? '');
$_dateSigned  = trim($_game['Date Signed']    ?? '');
$_datePub     = trim($_game['Date Published'] ?? '');

$_designers = [];
for ($i = 1; $i <= 4; $i++) {
    $d = trim($_game["Designer$i"] ?? '');
    if ($d) $_designers[] = $d;
}

// ── Load rich game-specific data from Playbook Sheet ─────────────────────────
// File: sheets/{playbook_sheet_id}/{lowercase_game_name}.json
// Format: [{Name, Value, "Value 1", "Value 2"}, ...]
$_rich = [];
if ($_pbSheetId) {
    $_richFile = $_root . '/sheets/' . $_pbSheetId . '/' . strtolower($_name) . '.json';
    $_rich = _gb_load($_richFile);
}

// Helper: get all rows of a given Name
function _gb_rows(array $rich, string $name): array {
    return array_values(array_filter($rich, fn($r) => strcasecmp(trim($r['Name'] ?? ''), $name) === 0));
}

// Helper: first Value for a given Name
function _gb_val(array $rich, string $name): string {
    foreach ($rich as $r) { if (strcasecmp(trim($r['Name'] ?? ''), $name) === 0) return trim($r['Value'] ?? ''); }
    return '';
}

$_components  = _gb_rows($_rich, 'Component');
$_reviews     = _gb_rows($_rich, 'Review');
$_faqs        = _gb_rows($_rich, 'FAQ');
$_buyUrls     = _gb_rows($_rich, 'BuyUrl');
$_richVideos  = _gb_rows($_rich, 'Video');
$_mediaImgs   = array_merge(_gb_rows($_rich, 'Media'), _gb_rows($_rich, 'ProductImage'));
$_richPrice   = _gb_val($_rich, 'Price');
$_richRules   = _gb_val($_rich, 'Rules PDF Url') ?: $_rulesUrl;

// ── Gallery: cover + image + rich media images ─────────────────────────────
$_gallery = [];
foreach (array_filter([$_coverUrl, $_imageUrl]) as $img) {
    if (!in_array($img, $_gallery)) $_gallery[] = $img;
}
foreach ($_mediaImgs as $r) {
    $u = trim($r['Value'] ?? '');
    if ($u && !in_array($u, $_gallery)) $_gallery[] = $u;
}

$_heroImg = $_gallery[0] ?? '';

// Primary video embed: first rich Video with embeddable URL, else games.json Video URL
$_videoEmbed = '';
foreach ($_richVideos as $rv) {
    $e = _gb_embed(trim($rv['Value 1'] ?? $rv['Value'] ?? ''));
    if ($e) { $_videoEmbed = $e; break; }
}
if (!$_videoEmbed) $_videoEmbed = _gb_embed($_videoUrl);

// Additional video list (all rich videos that are embeddable)
$_videoList = [];
foreach ($_richVideos as $rv) {
    $e = _gb_embed(trim($rv['Value 1'] ?? $rv['Value'] ?? ''));
    if ($e) $_videoList[] = ['label' => trim($rv['Value'] ?? ''), 'embed' => $e];
}

// Other Published games for "More Games" section
$_otherGames = [];
foreach ($_allGames as $g) {
    if (strtolower(trim($g['Status'] ?? '')) === 'published' && _gb_slug($g['Name'] ?? '') !== _gb_slug($_name)) {
        $_otherGames[] = $g;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= h($_name) ?><?= $_summary ? ' — ' . h($_summary) : '' ?></title>
<meta name="description" content="<?= h(substr($_description ?: $_summary, 0, 160)) ?>" />
<?php if ($_heroImg): ?><meta property="og:image" content="<?= h($_heroImg) ?>" /><?php endif; ?>
<style>
@font-face { font-family:'DINBlack';   src:url('<?= $_appRoot ?>fonts/DINBlack.woff2')  format('woff2'); }
@font-face { font-family:'DINRegular'; src:url('<?= $_appRoot ?>fonts/DINMedium.woff2') format('woff2'); }
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root {
  --ink:#1a1a2e; --ink-lt:#4a4a6a; --muted:#888; --border:#e2e2ec;
  --surface:#fff; --bg:#f6f5f2; --accent:#3b3baa; --accent-dk:#2d2d88; --accent-lt:#eeeeff;
  --nav-bg:#1a1a2e; --hero-gradient:linear-gradient(to top, rgba(10,10,28,.95) 0%, rgba(10,10,28,.55) 55%, rgba(10,10,28,.05) 100%);
}
<?php if ($_template === 'opensea'): ?>
/* ── OpenSea template overrides ── */
:root {
  --ink:#0d1b2a; --ink-lt:#2c4a6e; --muted:#6b8fae; --border:#c8dcea;
  --surface:#fff; --bg:#f0f6fb; --accent:#0097a7; --accent-dk:#00838f; --accent-lt:#e0f7fa;
  --nav-bg:#0d1b2a; --hero-gradient:linear-gradient(to top, rgba(13,27,42,.96) 0%, rgba(13,27,42,.5) 50%, rgba(13,27,42,.04) 100%);
}
.gb-nav-logo { color:#00e5ff; }
.gb-hero-title { color:#fff; }
.gb-status-badge { border-color:rgba(0,229,255,.4); color:rgba(255,255,255,.9); }
.btn-nav.filled { background:#00e5ff; color:#0d1b2a; border-color:#00e5ff; }
.btn-nav.filled:hover { background:#b2fff9; }
.gb-section-label { color:#0097a7; border-color:#c8dcea; }
.gb-comp-qty { color:#0097a7; }
.gb-review-quote::before { color:#0097a7; }
.gb-faq-item { border-color:#c8dcea; }
.gb-vtab.active, .gb-vtab:hover { background:#0097a7; border-color:#0097a7; }
.btn-buy { background:#0097a7; }
.btn-buy:hover { background:#00838f; }
.btn-link { border-color:#0097a7; color:#0097a7; }
.btn-link:hover { background:#0097a7; color:#fff; }
.gb-more-card:hover { box-shadow:0 4px 18px rgba(0,151,167,.18); }
.gb-more-name { color:#0097a7; }
<?php endif; ?>
body { background:var(--bg); font-family:'DINRegular',Arial,sans-serif; color:var(--ink); min-height:100vh; }
a { color:inherit; text-decoration:none; }

/* ── Nav ── */
.gb-nav {
  background:var(--nav-bg); color:#fff;
  display:flex; align-items:center; justify-content:space-between;
  padding:.7rem 1.5rem; position:sticky; top:0; z-index:100;
  gap:1rem;
}
.gb-nav-left { display:flex; align-items:center; gap:.65rem; min-width:0; }
.gb-nav-logo { font-family:'DINBlack',sans-serif; font-size:1rem; letter-spacing:.06em; color:#7c9eff; flex-shrink:0; }
.gb-nav-game { font-size:.78rem; opacity:.6; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.gb-nav-links { display:flex; gap:.4rem; flex-shrink:0; }
.btn-nav {
  font-family:'DINBlack',sans-serif; font-size:.68rem; text-transform:uppercase;
  letter-spacing:.07em; padding:.33rem .75rem; border-radius:5px;
  border:1.5px solid rgba(255,255,255,.3); color:#fff; background:transparent;
  transition:background .14s; white-space:nowrap; cursor:pointer;
}
.btn-nav:hover { background:rgba(255,255,255,.14); border-color:#fff; }
.btn-nav.filled { background:#fff; color:var(--ink); border-color:#fff; }
.btn-nav.filled:hover { background:#e8e8ff; }

/* ── Hero ── */
.gb-hero {
  position:relative; min-height:70vh; display:flex; align-items:flex-end;
  overflow:hidden; background:var(--ink);
}
.gb-hero-bg { position:absolute; inset:0; background-size:cover; background-position:center; }
.gb-hero-overlay {
  position:absolute; inset:0;
  background:var(--hero-gradient);
}
.gb-hero-content { position:relative; z-index:2; max-width:860px; padding:2.5rem 2rem 3.5rem; }
.gb-status-badge {
  display:inline-block; font-family:'DINBlack',sans-serif; font-size:.66rem;
  text-transform:uppercase; letter-spacing:.1em; padding:.22rem .65rem;
  border-radius:999px; border:1.5px solid rgba(255,255,255,.38); color:rgba(255,255,255,.8);
  margin-bottom:.8rem;
}
.gb-hero-title {
  font-family:'DINBlack',sans-serif; font-size:clamp(2rem,7vw,3.8rem);
  line-height:1.04; letter-spacing:.02em; color:#fff;
  text-shadow:0 2px 20px rgba(0,0,0,.55); margin-bottom:.65rem;
}
.gb-hero-summary {
  font-size:clamp(.92rem,2.5vw,1.12rem); color:rgba(255,255,255,.82);
  max-width:560px; line-height:1.55; margin-bottom:1.3rem;
}
.gb-stats {
  display:flex; flex-wrap:wrap; gap:.45rem 1.3rem; margin-bottom:1.5rem;
}
.gb-stat { font-size:.82rem; color:rgba(255,255,255,.75); }
.gb-stat strong { font-family:'DINBlack',sans-serif; color:#fff; margin-right:.25rem; }
.gb-hero-ctas { display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; }
.gb-price-tag {
  font-family:'DINBlack',sans-serif; font-size:1.5rem; color:#fff;
  margin-right:.25rem; letter-spacing:-.01em;
}

/* ── Main ── */
.gb-main { max-width:940px; margin:0 auto; padding:3rem 1.25rem 5rem; }
.gb-section { margin-bottom:3.2rem; }
.gb-section-label {
  font-family:'DINBlack',sans-serif; font-size:.7rem; text-transform:uppercase;
  letter-spacing:.1em; color:var(--muted); margin-bottom:.85rem;
  padding-bottom:.45rem; border-bottom:1px solid var(--border);
}
.gb-description { font-size:.98rem; line-height:1.75; color:var(--ink-lt); white-space:pre-wrap; }

/* ── Gallery ── */
.gb-gallery { display:flex; gap:.55rem; overflow-x:auto; scroll-snap-type:x mandatory; padding-bottom:.4rem; }
.gb-gallery::-webkit-scrollbar { height:4px; }
.gb-gallery::-webkit-scrollbar-thumb { background:var(--border); border-radius:2px; }
.gb-gallery img {
  height:210px; border-radius:8px; object-fit:cover; flex-shrink:0;
  scroll-snap-align:start; cursor:pointer; transition:transform .18s;
  box-shadow:0 2px 10px rgba(0,0,0,.1);
}
.gb-gallery img:hover { transform:scale(1.012); }

/* ── Video ── */
.gb-video-wrap { position:relative; padding-top:56.25%; border-radius:10px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,.13); }
.gb-video-wrap iframe { position:absolute; inset:0; width:100%; height:100%; border:none; }
.gb-video-tabs { display:flex; gap:.4rem; flex-wrap:wrap; margin-top:.7rem; }
.gb-vtab {
  font-family:'DINBlack',sans-serif; font-size:.68rem; text-transform:uppercase;
  letter-spacing:.06em; padding:.28rem .65rem; border-radius:4px; cursor:pointer;
  border:1px solid var(--border); background:var(--surface); color:var(--ink-lt);
  transition:background .12s;
}
.gb-vtab:hover, .gb-vtab.active { background:var(--accent); color:#fff; border-color:var(--accent); }

/* ── Components ── */
.gb-components { display:grid; grid-template-columns:repeat(auto-fill, minmax(195px,1fr)); gap:.5rem; }
.gb-comp-item {
  display:flex; align-items:baseline; gap:.55rem;
  background:var(--surface); border:1px solid var(--border);
  border-radius:7px; padding:.52rem .7rem; font-size:.86rem;
}
.gb-comp-qty { font-family:'DINBlack',sans-serif; font-size:1rem; color:var(--accent); flex-shrink:0; min-width:1.5rem; }
.gb-comp-name { color:var(--ink-lt); }

/* ── Reviews ── */
.gb-reviews { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px,1fr)); gap:1rem; }
.gb-review {
  background:var(--surface); border:1px solid var(--border); border-radius:10px;
  padding:1rem 1.1rem; position:relative;
}
.gb-review-quote { font-size:.9rem; line-height:1.6; color:var(--ink-lt); font-style:italic; margin-bottom:.6rem; }
.gb-review-quote::before { content:'\201C'; font-size:1.8rem; color:var(--accent); line-height:0; vertical-align:-.45em; margin-right:.1rem; }
.gb-review-author { font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:var(--muted); }

/* ── FAQs ── */
.gb-faq-item { border-bottom:1px solid var(--border); padding:.85rem 0; }
.gb-faq-item:last-child { border-bottom:none; }
.gb-faq-q { font-family:'DINBlack',sans-serif; font-size:.9rem; color:var(--ink); cursor:pointer; display:flex; justify-content:space-between; align-items:center; gap:.5rem; }
.gb-faq-q::after { content:'＋'; font-size:.9rem; color:var(--muted); flex-shrink:0; }
.gb-faq-item.open .gb-faq-q::after { content:'－'; }
.gb-faq-a { display:none; font-size:.87rem; line-height:1.65; color:var(--ink-lt); margin-top:.6rem; }
.gb-faq-item.open .gb-faq-a { display:block; }
.gb-faq-a a { color:var(--accent); text-decoration:underline; }
.gb-faq-img { max-width:100%; border-radius:6px; margin-top:.5rem; }

/* ── Buy links ── */
.gb-buy-links { display:flex; flex-wrap:wrap; gap:.6rem; }
.btn-buy {
  font-family:'DINBlack',sans-serif; font-size:.78rem; text-transform:uppercase;
  letter-spacing:.07em; padding:.55rem 1.2rem; border-radius:7px;
  background:var(--accent); color:#fff; border:none; cursor:pointer;
  transition:background .13s;
}
.btn-buy:hover { background:var(--accent-dk); }

/* ── Resource links ── */
.gb-links { display:flex; flex-wrap:wrap; gap:.6rem; }
.btn-link {
  font-family:'DINBlack',sans-serif; font-size:.74rem; text-transform:uppercase;
  letter-spacing:.07em; padding:.48rem 1rem; border-radius:6px;
  border:2px solid var(--accent); color:var(--accent); background:var(--surface);
  transition:background .12s, color .12s; cursor:pointer;
}
.btn-link:hover { background:var(--accent); color:#fff; }

/* ── More games ── */
.gb-more-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(160px,1fr)); gap:.9rem; }
.gb-more-card {
  background:var(--surface); border-radius:10px; box-shadow:0 2px 10px rgba(0,0,0,.07);
  overflow:hidden; transition:box-shadow .15s, transform .15s; cursor:pointer;
}
.gb-more-card:hover { box-shadow:0 4px 18px rgba(59,59,170,.15); transform:translateY(-2px); }
.gb-more-card img { width:100%; aspect-ratio:1; object-fit:cover; display:block; }
.gb-more-card-ph { width:100%; aspect-ratio:1; background:linear-gradient(135deg,#dde 0%,#c8c8e8 100%); display:flex; align-items:center; justify-content:center; font-size:2.2rem; opacity:.45; }
.gb-more-body { padding:.55rem .7rem .7rem; }
.gb-more-name { font-family:'DINBlack',sans-serif; font-size:.8rem; color:var(--accent); margin-bottom:.1rem; }
.gb-more-meta { font-size:.72rem; color:var(--muted); }

/* ── Footer ── */
.gb-footer { background:var(--ink); color:rgba(255,255,255,.55); text-align:center; padding:1.8rem 1.5rem; font-size:.76rem; }
.gb-footer strong { color:#fff; font-family:'DINBlack',sans-serif; }

/* ── Lightbox ── */
.gb-lightbox { display:none; position:fixed; inset:0; z-index:500; background:rgba(0,0,0,.92); align-items:center; justify-content:center; cursor:zoom-out; }
.gb-lightbox.open { display:flex; }
.gb-lightbox img { max-width:92vw; max-height:92vh; border-radius:8px; object-fit:contain; }

@media(max-width:640px) {
  .gb-hero-content { padding:1.8rem 1rem 2.5rem; }
  .gb-nav-game { display:none; }
  .gb-main { padding:2rem .9rem 4rem; }
  .gb-components { grid-template-columns:1fr 1fr; }
  .gb-more-grid { grid-template-columns:repeat(2,1fr); }
  .gb-reviews { grid-template-columns:1fr; }
}

/* ── Admin edit mode ── */
.gb-admin-bar {
  display:none; background:#0f0f1f; border-bottom:1px solid rgba(124,158,255,.2);
  padding:.45rem 1.5rem; align-items:center; gap:.75rem; font-size:.8rem; flex-wrap:wrap;
}
.gb-admin-bar.visible { display:flex; }
.gb-admin-label {
  font-family:'DINBlack',sans-serif; font-size:.66rem; text-transform:uppercase;
  letter-spacing:.1em; color:#7c9eff; margin-right:.25rem;
}
.gb-admin-hint { font-size:.72rem; color:rgba(255,255,255,.4); flex:1; }
.btn-adm {
  font-family:'DINBlack',sans-serif; font-size:.68rem; text-transform:uppercase;
  letter-spacing:.07em; padding:.3rem .75rem; border-radius:5px; cursor:pointer;
  transition:background .13s, opacity .13s;
}
.btn-adm-primary { background:#7c9eff; color:#0d1b2a; border:none; }
.btn-adm-primary:hover:not(:disabled) { background:#9eb8ff; }
.btn-adm-primary:disabled { opacity:.45; cursor:default; }
.btn-adm-secondary { background:transparent; border:1.5px solid rgba(255,255,255,.25); color:rgba(255,255,255,.75); }
.btn-adm-secondary:hover { background:rgba(255,255,255,.08); border-color:#fff; }
.btn-adm-details { background:rgba(124,158,255,.15); border:1.5px solid rgba(124,158,255,.4); color:#7c9eff; }
.btn-adm-details:hover { background:rgba(124,158,255,.25); }
/* Editable field highlights in admin mode */
body.gb-admin-mode #gbEditTitle { outline:2px dashed rgba(124,158,255,.5); border-radius:3px; cursor:text; padding:2px 4px; }
body.gb-admin-mode #gbEditTitle:focus,
body.gb-admin-mode #gbEditTitle:hover { outline:2px solid #7c9eff; }
body.gb-admin-mode #gbEditSummary { outline:2px dashed rgba(255,255,255,.3); border-radius:3px; cursor:text; padding:2px 4px; }
body.gb-admin-mode #gbEditSummary:focus,
body.gb-admin-mode #gbEditSummary:hover { outline:2px solid rgba(255,255,255,.7); }
body.gb-admin-mode #gbEditDesc { outline:2px dashed rgba(59,59,170,.4); border-radius:4px; cursor:text; padding:4px 6px; }
body.gb-admin-mode #gbEditDesc:focus,
body.gb-admin-mode #gbEditDesc:hover { outline:2px solid var(--accent); background:rgba(59,59,170,.04); }
/* Edit details overlay */
.gbed-overlay {
  display:none; position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:600;
  align-items:flex-start; justify-content:center; padding:2rem 1rem; overflow-y:auto;
}
.gbed-overlay.open { display:flex; }
.gbed-dialog {
  background:#fff; border-radius:14px; width:100%; max-width:520px;
  box-shadow:0 12px 50px rgba(0,0,0,.32); overflow:hidden; margin:auto;
}
.gbed-header {
  background:var(--nav-bg); color:#fff; padding:1rem 1.25rem;
  display:flex; align-items:center; justify-content:space-between;
}
.gbed-title { font-family:'DINBlack',sans-serif; font-size:.9rem; text-transform:uppercase; letter-spacing:.07em; }
.gbed-close { background:none; border:none; color:rgba(255,255,255,.6); font-size:1.2rem; cursor:pointer; padding:0 .25rem; line-height:1; }
.gbed-close:hover { color:#fff; }
.gbed-body { padding:1.25rem 1.5rem 1.5rem; max-height:calc(80vh - 120px); overflow-y:auto; }
.gbed-section { margin-bottom:1.1rem; }
.gbed-section-title {
  font-family:'DINBlack',sans-serif; font-size:.65rem; text-transform:uppercase;
  letter-spacing:.1em; color:var(--muted); border-bottom:1px solid var(--border);
  padding-bottom:.35rem; margin-bottom:.65rem;
}
.gbed-row { display:flex; gap:.6rem; margin-bottom:.55rem; }
.gbed-row > * { flex:1; min-width:0; }
.gbed-field-group { margin-bottom:.55rem; }
.gbed-label { display:block; font-size:.73rem; color:var(--muted); margin-bottom:.2rem; }
.gbed-field {
  display:block; width:100%; padding:.45rem .7rem;
  border:1.5px solid var(--border); border-radius:6px;
  font-family:'DINRegular',Arial,sans-serif; font-size:.87rem; color:var(--ink);
  outline:none; transition:border-color .14s;
}
.gbed-field:focus { border-color:var(--accent); }
.gbed-footer {
  padding:.9rem 1.5rem; background:#f8f8fc; border-top:1px solid var(--border);
  display:flex; gap:.5rem; justify-content:flex-end;
}
/* ── Placeholder content (admin only) ── */
/* CSS placeholder for empty contenteditable fields */
body.gb-admin-mode [contenteditable][data-ph]:empty::before {
  content: attr(data-ph);
  opacity: .35; font-style: italic; pointer-events: none; font-family:'DINRegular',Arial,sans-serif;
}
/* Sections that only show in admin mode when empty */
.gb-ph-section { display: none; }
body.gb-admin-mode .gb-ph-section { display: block; }
/* Placeholder stat chips */
.gb-ph-stat { display: none; }
body.gb-admin-mode .gb-ph-stat { display: flex; }
.gb-ph-stat strong { opacity: .5; }
.gb-ph-stat span { opacity: .35; font-style: italic; }
/* Cover hint overlay when no hero image */
.gb-cover-hint {
  display: none; position: absolute; inset: 0; z-index: 1;
  align-items: center; justify-content: center; flex-direction: column; gap: .6rem;
  border: 3px dashed rgba(124,158,255,.25); pointer-events: none;
}
body.gb-admin-mode .gb-hero.no-cover .gb-cover-hint { display: flex; }
.gb-cover-hint-icon { font-size: 2.5rem; opacity: .25; }
.gb-cover-hint-text {
  font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase;
  letter-spacing:.1em; color:rgba(255,255,255,.3);
}
/* Placeholder lorem sections */
.gb-ph-lorem {
  font-size:.93rem; line-height:1.75; color:var(--ink-lt); opacity:.35;
  font-style:italic; white-space:pre-wrap;
  border:2px dashed rgba(59,59,170,.2); border-radius:6px; padding:.75rem 1rem;
}
/* Placeholder gallery thumbs */
.gb-ph-gallery { display: none; }
body.gb-admin-mode .gb-ph-gallery { display: flex; gap:.55rem; }
.gb-ph-thumb {
  height:210px; width:200px; border-radius:8px; flex-shrink:0;
  background:linear-gradient(135deg,#dde 0%,#c8c8e8 100%);
  display:flex; align-items:center; justify-content:center;
  font-size:2.5rem; opacity:.3; border:2px dashed rgba(59,59,170,.2);
}
</style>
</head>
<body>

<!-- ── Nav ── -->
<nav class="gb-nav">
  <div class="gb-nav-left">
    <a class="gb-nav-logo" href="<?= h($_appRoot . $_sheetId . '/gameboard/') ?>">GameBoard</a>
    <span class="gb-nav-game" id="gbNavGame"><?= h($_name) ?></span>
  </div>
  <div class="gb-nav-links" id="gbNavLinks">
    <?php if ($_richRules): ?><a class="btn-nav" href="<?= h($_richRules) ?>" target="_blank">Rules</a><?php endif; ?>
    <?php if ($_sellsheet): ?><a class="btn-nav" href="<?= h($_sellsheet) ?>" target="_blank">Sellsheet</a><?php endif; ?>
    <?php if ($_pageUrl): ?><a class="btn-nav filled" href="<?= h($_pageUrl) ?>" target="_blank">View Page ↗</a><?php endif; ?>
  </div>
</nav>

<!-- ── Admin bar (shown only when signed in as owner) ── -->
<div class="gb-admin-bar" id="gbAdminBar">
  <span class="gb-admin-label">✏ Admin</span>
  <span class="gb-admin-hint">Click title, summary, or description to edit inline</span>
  <button class="btn-adm btn-adm-details" onclick="openEditDetails()">Edit Details</button>
  <button class="btn-adm btn-adm-primary" id="gbSaveBtn" onclick="saveGame()">Save Changes</button>
  <button class="btn-adm btn-adm-secondary" onclick="cancelEdit()">Cancel</button>
</div>

<!-- ── Hero ── -->
<div class="gb-hero<?= $_heroImg ? '' : ' no-cover' ?>">
  <?php if ($_heroImg): ?>
  <div class="gb-hero-bg" style="background-image:url('<?= h($_heroImg) ?>')"></div>
  <?php else: ?>
  <div class="gb-hero-bg" style="background:linear-gradient(135deg,#1a1a2e 0%,#2d2d88 100%)"></div>
  <?php endif; ?>
  <!-- Cover image hint (admin mode only, when no cover set) -->
  <div class="gb-cover-hint">
    <div class="gb-cover-hint-icon">🖼</div>
    <div class="gb-cover-hint-text">Add a cover image via Edit Details</div>
  </div>
  <div class="gb-hero-overlay"></div>
  <div class="gb-hero-content">
    <?php if ($_status): ?><div class="gb-status-badge" id="gbStatusBadge"><?= h(ucfirst($_status)) ?></div><?php endif; ?>
    <h1 class="gb-hero-title" id="gbEditTitle" data-ph="Game Title"><?= h($_name) ?></h1>
    <p class="gb-hero-summary" id="gbEditSummary" data-ph="Click to add a one-line tagline for this game…"><?= h($_summary) ?></p>
    <div class="gb-stats">
      <?php if ($_count):     ?><div class="gb-stat"><strong>👥</strong><?= h($_count) ?> players</div><?php endif; ?>
      <?php if ($_duration):  ?><div class="gb-stat"><strong>⏱</strong><?= h($_duration) ?></div><?php endif; ?>
      <?php if ($_designers): ?><div class="gb-stat"><strong>✏️</strong><?= h(implode(', ', $_designers)) ?></div><?php endif; ?>
      <?php if ($_availability): ?><div class="gb-stat"><strong>📦</strong><?= h($_availability) ?></div><?php endif; ?>
      <?php if (!$_count):    ?><div class="gb-ph-stat"><strong>👥</strong><span>2–4 players</span></div><?php endif; ?>
      <?php if (!$_duration): ?><div class="gb-ph-stat"><strong>⏱</strong><span>30–60 min</span></div><?php endif; ?>
    </div>
    <div class="gb-hero-ctas">
      <?php if ($_richPrice): ?><span class="gb-price-tag"><?= h($_richPrice) ?></span><?php endif; ?>
      <?php foreach ($_buyUrls as $b):
        $bUrl   = trim($b['Value']   ?? '');
        $bLabel = trim($b['Value 1'] ?? '') ?: 'Buy Now';
        if (!$bUrl) continue;
      ?><a class="btn-nav filled" href="<?= h($bUrl) ?>" target="_blank"><?= h($bLabel) ?></a><?php endforeach; ?>
      <?php if ($_richRules): ?><a class="btn-nav" href="<?= h($_richRules) ?>" target="_blank">📖 Rules</a><?php endif; ?>
      <?php if ($_playUrl):   ?><a class="btn-nav" href="<?= h($_playUrl) ?>" target="_blank">🎮 Play Online</a><?php endif; ?>
      <?php if ($_printUrl):  ?><a class="btn-nav" href="<?= h($_printUrl) ?>" target="_blank">🖨 Print &amp; Play</a><?php endif; ?>
    </div>
  </div>
</div>

<!-- ── Main ── -->
<div class="gb-main">

  <!-- Description (always rendered; hidden in public when empty via .gb-ph-section) -->
  <div class="gb-section<?= $_description ? '' : ' gb-ph-section' ?>">
    <div class="gb-section-label">About the Game</div>
    <?php if ($_description): ?>
    <p class="gb-description" id="gbEditDesc"><?= h($_description) ?></p>
    <?php else: ?>
    <p class="gb-description gb-ph-lorem" id="gbEditDesc" data-ph="Click to add a game description…">Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.</p>
    <?php endif; ?>
  </div>

  <!-- Gallery -->
  <?php if (count($_gallery) > 1): ?>
  <div class="gb-section">
    <div class="gb-section-label">Gallery</div>
    <div class="gb-gallery">
      <?php foreach ($_gallery as $img): ?>
      <img src="<?= h($img) ?>" alt="<?= h($_name) ?>" loading="lazy" onclick="openLightbox('<?= h($img) ?>')" />
      <?php endforeach; ?>
    </div>
  </div>
  <?php else: ?>
  <!-- Placeholder gallery (admin only) -->
  <div class="gb-section gb-ph-section">
    <div class="gb-section-label">Gallery</div>
    <div class="gb-ph-gallery">
      <div class="gb-ph-thumb">🖼</div>
      <div class="gb-ph-thumb">🖼</div>
      <div class="gb-ph-thumb">🖼</div>
    </div>
    <p style="font-size:.75rem;color:var(--muted);margin-top:.5rem;font-style:italic">Add image URLs via Edit Details (Cover URL, Image URL) or via the per-game data sheet.</p>
  </div>
  <?php endif; ?>

  <!-- Video(s) -->
  <?php if (!$_videoEmbed): ?>
  <!-- Placeholder video (admin only) -->
  <div class="gb-section gb-ph-section">
    <div class="gb-section-label">Watch</div>
    <div style="background:linear-gradient(135deg,#1a1a2e,#2d2d88);border-radius:10px;aspect-ratio:16/9;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:.5rem;opacity:.3;">
      <div style="font-size:3rem;">▶</div>
      <div style="font-family:'DINBlack',sans-serif;font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;color:#fff;">Add video URL via Edit Details</div>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($_videoEmbed): ?>
  <div class="gb-section">
    <div class="gb-section-label">Watch</div>
    <div class="gb-video-wrap" id="videoWrap">
      <iframe id="videoFrame" src="<?= h($_videoEmbed) ?>" allow="autoplay; encrypted-media" allowfullscreen loading="lazy"></iframe>
    </div>
    <?php if (count($_videoList) > 1): ?>
    <div class="gb-video-tabs">
      <?php foreach ($_videoList as $vi => $v): ?>
      <button class="gb-vtab<?= $vi === 0 ? ' active' : '' ?>"
              onclick="switchVideo(this, '<?= h($v['embed']) ?>')"><?= h($v['label'] ?: ('Video ' . ($vi + 1))) ?></button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Components -->
  <?php if (empty($_components)): ?>
  <!-- Placeholder components (admin only) -->
  <div class="gb-section gb-ph-section">
    <div class="gb-section-label">What's in the Box</div>
    <div class="gb-components">
      <?php foreach (['50 Cards', '1 Game Board', '4 Player Tokens', '30 Resource Tiles', '1 Rulebook'] as $_phC): ?>
      <div class="gb-comp-item" style="opacity:.3">
        <span class="gb-comp-qty"><?= explode(' ', $_phC)[0] ?></span>
        <span class="gb-comp-name"><?= implode(' ', array_slice(explode(' ', $_phC), 1)) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <p style="font-size:.75rem;color:var(--muted);margin-top:.6rem;font-style:italic">Component list comes from the game's data sheet (rows with Name = "Component").</p>
  </div>
  <?php endif; ?>
  <?php if (!empty($_components)): ?>
  <div class="gb-section">
    <div class="gb-section-label">What's in the Box</div>
    <div class="gb-components">
      <?php foreach ($_components as $c):
        $cVal = trim($c['Value'] ?? '');
        if (!$cVal) continue;
        // Try to parse "42 Flower Tiles" → qty + name, or just show as-is
        $cQty  = '';
        $cName = $cVal;
        if (preg_match('/^(\d+)\s+(.+)$/', $cVal, $pm)) { $cQty = $pm[1]; $cName = $pm[2]; }
      ?>
      <div class="gb-comp-item">
        <?php if ($cQty): ?><span class="gb-comp-qty"><?= h($cQty) ?></span><?php endif; ?>
        <span class="gb-comp-name"><?= h($cName) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Reviews / Quotes -->
  <?php if (!empty($_reviews)): ?>
  <div class="gb-section">
    <div class="gb-section-label">What People Are Saying</div>
    <div class="gb-reviews">
      <?php foreach ($_reviews as $rv):
        $rQuote  = trim($rv['Value']   ?? '');
        $rAuthor = trim($rv['Value 1'] ?? '');
        if (!$rQuote) continue;
      ?>
      <div class="gb-review">
        <div class="gb-review-quote"><?= h($rQuote) ?></div>
        <?php if ($rAuthor): ?><div class="gb-review-author">— <?= h($rAuthor) ?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- FAQs -->
  <?php if (!empty($_faqs)): ?>
  <div class="gb-section">
    <div class="gb-section-label">FAQ</div>
    <?php foreach ($_faqs as $fi => $fq):
      $fqQ = trim($fq['Value']   ?? '');
      $fqA = trim($fq['Value 1'] ?? '');
      $fqI = trim($fq['Value 2'] ?? '');
      if (!$fqQ || !$fqA) continue;
    ?>
    <div class="gb-faq-item" id="faq<?= $fi ?>">
      <div class="gb-faq-q" onclick="toggleFaq('faq<?= $fi ?>')"><?= h($fqQ) ?></div>
      <div class="gb-faq-a"><?= nl2br(h($fqA)) ?>
        <?php if ($fqI && preg_match('/^https?:\/\//', $fqI)): ?>
        <br><img class="gb-faq-img" src="<?= h($fqI) ?>" loading="lazy" alt="" />
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- Resources -->
  <?php
  $_hasLinks = $_richRules || $_sellsheet || $_playUrl || $_printUrl || $_pageUrl;
  if ($_hasLinks): ?>
  <div class="gb-section">
    <div class="gb-section-label">Resources</div>
    <div class="gb-links">
      <?php if ($_richRules): ?><a class="btn-link" href="<?= h($_richRules) ?>" target="_blank">📖 Rules PDF</a><?php endif; ?>
      <?php if ($_sellsheet): ?><a class="btn-link" href="<?= h($_sellsheet) ?>" target="_blank">📄 Sellsheet</a><?php endif; ?>
      <?php if ($_playUrl):   ?><a class="btn-link" href="<?= h($_playUrl) ?>" target="_blank">🎮 Play Online</a><?php endif; ?>
      <?php if ($_printUrl):  ?><a class="btn-link" href="<?= h($_printUrl) ?>" target="_blank">🖨 Print &amp; Play</a><?php endif; ?>
      <?php if ($_pageUrl):   ?><a class="btn-link" href="<?= h($_pageUrl) ?>" target="_blank">🔗 Product Page</a><?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- More Games -->
  <?php if (!empty($_otherGames)): ?>
  <div class="gb-section">
    <div class="gb-section-label">More Games</div>
    <div class="gb-more-grid">
      <?php foreach (array_slice($_otherGames, 0, 8) as $og):
        $ogName = trim($og['Name'] ?? '');
        $ogImg  = _gb_img(trim($og['Cover URL'] ?? $og['Image URL'] ?? ''));
        $ogMeta = array_filter([trim($og['Count'] ?? '') ? trim($og['Count']).' players' : '', trim($og['Duration'] ?? '')]);
      ?>
      <a class="gb-more-card" href="<?= h($_appRoot . $_sheetId . '/gameboard/' . _gb_slug($ogName)) ?>">
        <?php if ($ogImg): ?>
        <img src="<?= h($ogImg) ?>" alt="<?= h($ogName) ?>" loading="lazy" />
        <?php else: ?>
        <div class="gb-more-card-ph">🎲</div>
        <?php endif; ?>
        <div class="gb-more-body">
          <div class="gb-more-name"><?= h($ogName) ?></div>
          <?php if ($ogMeta): ?><div class="gb-more-meta"><?= h(implode(' · ', $ogMeta)) ?></div><?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

</div><!-- /gb-main -->

<footer class="gb-footer">
  <strong><?= h($_name) ?></strong>
  <?php if ($_designers): ?>&nbsp;·&nbsp; Designed by <?= h(implode(' & ', $_designers)) ?><?php endif; ?>
  <?php if ($_datePub): ?>&nbsp;·&nbsp; Published <?= h($_datePub) ?><?php elseif ($_dateSigned): ?>&nbsp;·&nbsp; Signed <?= h($_dateSigned) ?><?php endif; ?>
</footer>

<div class="gb-lightbox" id="lightbox" onclick="closeLightbox()">
  <img id="lightboxImg" src="" alt="" />
</div>

<!-- ── Edit Details modal ── -->
<div class="gbed-overlay" id="gbedOverlay" onclick="if(event.target===this)closeEditDetails()">
  <div class="gbed-dialog" onclick="event.stopPropagation()">
    <div class="gbed-header">
      <span class="gbed-title">Edit Details</span>
      <button class="gbed-close" onclick="closeEditDetails()">✕</button>
    </div>
    <div class="gbed-body">
      <div class="gbed-section">
        <div class="gbed-section-title">Game Info</div>
        <div class="gbed-row">
          <div class="gbed-field-group">
            <label class="gbed-label">Status</label>
            <select class="gbed-field" id="gbedStatus">
              <option value="">—</option>
              <option value="Concept">Concept</option>
              <option value="Developing">Developing</option>
              <option value="Playtesting">Playtesting</option>
              <option value="Pitching">Pitching</option>
              <option value="Signed">Signed</option>
              <option value="Published">Published</option>
              <option value="Shelved">Shelved</option>
            </select>
          </div>
          <div class="gbed-field-group">
            <label class="gbed-label">Availability</label>
            <input class="gbed-field" id="gbedAvailability" type="text" placeholder="e.g. Kickstarter" />
          </div>
        </div>
        <div class="gbed-row">
          <div class="gbed-field-group">
            <label class="gbed-label">Player Count</label>
            <input class="gbed-field" id="gbedCount" type="text" placeholder="e.g. 2–4" />
          </div>
          <div class="gbed-field-group">
            <label class="gbed-label">Play Time</label>
            <input class="gbed-field" id="gbedDuration" type="text" placeholder="e.g. 30–60 min" />
          </div>
        </div>
        <div class="gbed-row">
          <div class="gbed-field-group">
            <label class="gbed-label">Designer 1</label>
            <input class="gbed-field" id="gbedDesigner1" type="text" />
          </div>
          <div class="gbed-field-group">
            <label class="gbed-label">Designer 2</label>
            <input class="gbed-field" id="gbedDesigner2" type="text" />
          </div>
        </div>
        <div class="gbed-row">
          <div class="gbed-field-group">
            <label class="gbed-label">Designer 3</label>
            <input class="gbed-field" id="gbedDesigner3" type="text" />
          </div>
          <div class="gbed-field-group">
            <label class="gbed-label">Designer 4</label>
            <input class="gbed-field" id="gbedDesigner4" type="text" />
          </div>
        </div>
      </div>
      <div class="gbed-section">
        <div class="gbed-section-title">Dates</div>
        <div class="gbed-row">
          <div class="gbed-field-group">
            <label class="gbed-label">Date Started</label>
            <input class="gbed-field" id="gbedDateStarted" type="text" placeholder="e.g. Jan 2024" />
          </div>
          <div class="gbed-field-group">
            <label class="gbed-label">Date Signed</label>
            <input class="gbed-field" id="gbedDateSigned" type="text" placeholder="e.g. Mar 2024" />
          </div>
        </div>
        <div class="gbed-field-group" style="max-width:50%;padding-right:.3rem">
          <label class="gbed-label">Date Published</label>
          <input class="gbed-field" id="gbedDatePublished" type="text" placeholder="e.g. Nov 2024" />
        </div>
      </div>
      <div class="gbed-section">
        <div class="gbed-section-title">Images &amp; Media</div>
        <div class="gbed-field-group">
          <label class="gbed-label">Cover URL (background image)</label>
          <input class="gbed-field" id="gbedCoverUrl" type="url" placeholder="https://…" />
        </div>
        <div class="gbed-field-group">
          <label class="gbed-label">Image URL (product photo)</label>
          <input class="gbed-field" id="gbedImageUrl" type="url" placeholder="https://…" />
        </div>
        <div class="gbed-field-group">
          <label class="gbed-label">Video URL (YouTube)</label>
          <input class="gbed-field" id="gbedVideoUrl" type="url" placeholder="https://youtube.com/watch?v=…" />
        </div>
      </div>
      <div class="gbed-section">
        <div class="gbed-section-title">Links</div>
        <div class="gbed-field-group">
          <label class="gbed-label">Page URL</label>
          <input class="gbed-field" id="gbedPageUrl" type="url" placeholder="https://…" />
        </div>
        <div class="gbed-field-group">
          <label class="gbed-label">Sellsheet URL</label>
          <input class="gbed-field" id="gbedSellsheet" type="url" placeholder="https://…" />
        </div>
        <div class="gbed-field-group">
          <label class="gbed-label">Rules URL</label>
          <input class="gbed-field" id="gbedRulesUrl" type="url" placeholder="https://…" />
        </div>
        <div class="gbed-row">
          <div class="gbed-field-group">
            <label class="gbed-label">Play Online URL</label>
            <input class="gbed-field" id="gbedPlayUrl" type="url" placeholder="https://…" />
          </div>
          <div class="gbed-field-group">
            <label class="gbed-label">Print &amp; Play URL</label>
            <input class="gbed-field" id="gbedPrintUrl" type="url" placeholder="https://…" />
          </div>
        </div>
      </div>
    </div>
    <div class="gbed-footer">
      <button class="btn-adm btn-adm-secondary" style="color:var(--ink-lt);border-color:var(--border)" onclick="closeEditDetails()">Cancel</button>
      <button class="btn-adm btn-adm-primary" style="background:var(--accent);color:#fff" onclick="applyEditDetails()">Apply</button>
    </div>
  </div>
</div>

<script>
var SHEET_ID      = <?= json_encode($_sheetId) ?>;
var APP_ROOT      = <?= json_encode($_appRoot) ?>;
var ORIG_GAME_NAME = <?= json_encode($_name) ?>;
var GAME_DATA = <?= json_encode([
    'status'         => $_status,
    'cover_url'      => $_coverUrl,
    'image_url'      => $_imageUrl,
    'video_url'      => $_videoUrl,
    'page_url'       => $_pageUrl,
    'sellsheet'      => $_sellsheet,
    'play_url'       => $_playUrl,
    'print_url'      => $_printUrl,
    'rules_url'      => $_rulesUrl,
    'count'          => $_count,
    'duration'       => $_duration,
    'availability'   => $_availability,
    'designer1'      => $_designers[0] ?? '',
    'designer2'      => $_designers[1] ?? '',
    'designer3'      => $_designers[2] ?? '',
    'designer4'      => $_designers[3] ?? '',
    'date_started'   => trim($_game['Date Started'] ?? ''),
    'date_signed'    => $_dateSigned,
    'date_published' => $_datePub,
]) ?>;

// Mutable copy that tracks edits from the details modal
var _ed = Object.assign({}, GAME_DATA);

// ── Admin mode ───────────────────────────────────────────────────────────────
(function() {
  try {
    var raw = sessionStorage.getItem('gameboard_owner_' + SHEET_ID);
    if (!raw) return;
    var u = JSON.parse(raw);
    if (!u || !u.email) return;
    // Activate admin mode
    document.body.classList.add('gb-admin-mode');
    document.getElementById('gbAdminBar').classList.add('visible');
    // Make text fields contenteditable
    ['gbEditTitle', 'gbEditSummary', 'gbEditDesc'].forEach(function(id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.contentEditable = 'true';
      // If the element has lorem ipsum placeholder content (class gb-ph-lorem),
      // clear it on first focus and restore if left empty
      if (el.classList.contains('gb-ph-lorem')) {
        el.dataset.phContent = el.textContent;
        el.textContent = '';
        el.classList.remove('gb-ph-lorem');
      }
    });
  } catch(e) {}
})();

function cancelEdit() { location.reload(); }

// ── Edit Details modal ───────────────────────────────────────────────────────
function openEditDetails() {
  // Populate from _ed (live-edited values)
  document.getElementById('gbedStatus').value       = _ed.status       || '';
  document.getElementById('gbedCount').value        = _ed.count        || '';
  document.getElementById('gbedDuration').value     = _ed.duration     || '';
  document.getElementById('gbedAvailability').value = _ed.availability || '';
  document.getElementById('gbedDesigner1').value    = _ed.designer1    || '';
  document.getElementById('gbedDesigner2').value    = _ed.designer2    || '';
  document.getElementById('gbedDesigner3').value    = _ed.designer3    || '';
  document.getElementById('gbedDesigner4').value    = _ed.designer4    || '';
  document.getElementById('gbedDateStarted').value   = _ed.date_started   || '';
  document.getElementById('gbedDateSigned').value    = _ed.date_signed    || '';
  document.getElementById('gbedDatePublished').value = _ed.date_published || '';
  document.getElementById('gbedCoverUrl').value  = _ed.cover_url  || '';
  document.getElementById('gbedImageUrl').value  = _ed.image_url  || '';
  document.getElementById('gbedVideoUrl').value  = _ed.video_url  || '';
  document.getElementById('gbedPageUrl').value   = _ed.page_url   || '';
  document.getElementById('gbedSellsheet').value = _ed.sellsheet  || '';
  document.getElementById('gbedRulesUrl').value  = _ed.rules_url  || '';
  document.getElementById('gbedPlayUrl').value   = _ed.play_url   || '';
  document.getElementById('gbedPrintUrl').value  = _ed.print_url  || '';
  document.getElementById('gbedOverlay').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeEditDetails() {
  document.getElementById('gbedOverlay').classList.remove('open');
  document.body.style.overflow = '';
}
function applyEditDetails() {
  _ed.status         = document.getElementById('gbedStatus').value.trim();
  _ed.count          = document.getElementById('gbedCount').value.trim();
  _ed.duration       = document.getElementById('gbedDuration').value.trim();
  _ed.availability   = document.getElementById('gbedAvailability').value.trim();
  _ed.designer1      = document.getElementById('gbedDesigner1').value.trim();
  _ed.designer2      = document.getElementById('gbedDesigner2').value.trim();
  _ed.designer3      = document.getElementById('gbedDesigner3').value.trim();
  _ed.designer4      = document.getElementById('gbedDesigner4').value.trim();
  _ed.date_started   = document.getElementById('gbedDateStarted').value.trim();
  _ed.date_signed    = document.getElementById('gbedDateSigned').value.trim();
  _ed.date_published = document.getElementById('gbedDatePublished').value.trim();
  _ed.cover_url      = document.getElementById('gbedCoverUrl').value.trim();
  _ed.image_url      = document.getElementById('gbedImageUrl').value.trim();
  _ed.video_url      = document.getElementById('gbedVideoUrl').value.trim();
  _ed.page_url       = document.getElementById('gbedPageUrl').value.trim();
  _ed.sellsheet      = document.getElementById('gbedSellsheet').value.trim();
  _ed.rules_url      = document.getElementById('gbedRulesUrl').value.trim();
  _ed.play_url       = document.getElementById('gbedPlayUrl').value.trim();
  _ed.print_url      = document.getElementById('gbedPrintUrl').value.trim();
  closeEditDetails();
  // Update status badge live
  var badge = document.getElementById('gbStatusBadge');
  if (badge) {
    if (_ed.status) {
      badge.textContent = _ed.status.charAt(0).toUpperCase() + _ed.status.slice(1).toLowerCase();
      badge.style.display = '';
    } else {
      badge.style.display = 'none';
    }
  }
}

// ── Save game ────────────────────────────────────────────────────────────────
function saveGame() {
  var saveBtn = document.getElementById('gbSaveBtn');
  saveBtn.disabled = true;
  saveBtn.textContent = 'Saving…';

  var titleEl = document.getElementById('gbEditTitle');
  var summaryEl = document.getElementById('gbEditSummary');
  var descEl = document.getElementById('gbEditDesc');

  var fd = new FormData();
  fd.append('id',             SHEET_ID);
  fd.append('orig_name',      ORIG_GAME_NAME);
  fd.append('name',           titleEl   ? titleEl.textContent.trim()   : ORIG_GAME_NAME);
  fd.append('tagline',        summaryEl ? summaryEl.textContent.trim() : '');
  fd.append('description',    descEl    ? descEl.textContent.trim()    : '');
  fd.append('status',         _ed.status         || '');
  fd.append('count',          _ed.count          || '');
  fd.append('duration',       _ed.duration       || '');
  fd.append('availability',   _ed.availability   || '');
  fd.append('designer1',      _ed.designer1      || '');
  fd.append('designer2',      _ed.designer2      || '');
  fd.append('designer3',      _ed.designer3      || '');
  fd.append('designer4',      _ed.designer4      || '');
  fd.append('date_started',   _ed.date_started   || '');
  fd.append('date_signed',    _ed.date_signed    || '');
  fd.append('date_published', _ed.date_published || '');
  fd.append('cover_url',      _ed.cover_url      || '');
  fd.append('image',          _ed.image_url      || '');
  fd.append('video',          _ed.video_url      || '');
  fd.append('view',           _ed.page_url       || '');
  fd.append('sellsheet',      _ed.sellsheet      || '');
  fd.append('rules',          _ed.rules_url      || '');
  fd.append('play',           _ed.play_url       || '');
  fd.append('print',          _ed.print_url      || '');

  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/updateGame.php');
  xhr.onload = function() {
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = {error: xhr.responseText}; }
    if (r && r.error) {
      saveBtn.disabled = false;
      saveBtn.textContent = 'Save Changes';
      alert('Save failed: ' + r.error);
      return;
    }
    // Redirect to updated slug if name changed
    var newName = titleEl ? titleEl.textContent.trim() : ORIG_GAME_NAME;
    var newSlug = newName.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
    var catUrl  = APP_ROOT + SHEET_ID + '/gameboard/';
    location.href = catUrl + newSlug;
  };
  xhr.onerror = function() {
    saveBtn.disabled = false;
    saveBtn.textContent = 'Save Changes';
    alert('Save failed: network error');
  };
  xhr.send(fd);
}

// ── Standard page JS ─────────────────────────────────────────────────────────
function openLightbox(src) {
  document.getElementById('lightboxImg').src = src;
  document.getElementById('lightbox').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeLightbox() {
  document.getElementById('lightbox').classList.remove('open');
  if (!document.getElementById('gbedOverlay').classList.contains('open')) {
    document.body.style.overflow = '';
  }
}
function switchVideo(btn, embed) {
  document.getElementById('videoFrame').src = embed;
  document.querySelectorAll('.gb-vtab').forEach(function(t){ t.classList.remove('active'); });
  btn.classList.add('active');
}
function toggleFaq(id) {
  document.getElementById(id).classList.toggle('open');
}
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape') {
    if (document.getElementById('gbedOverlay').classList.contains('open')) { closeEditDetails(); return; }
    closeLightbox();
  }
});
</script>
</body>
</html>
