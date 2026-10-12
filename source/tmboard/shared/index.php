<?php
/**
 * T&MBoard staff collab view — per-project shared link.
 * URL: /tmboard/share/{24-char-hex-token}
 * Staff log time entries and see all time for the project.
 * Auth via accounts.json (same pattern as DevBoard collab).
 */
error_reporting(0);

$_bpFile = __DIR__ . '/../../../dotEnv.php';
if (file_exists($_bpFile)) { require_once $_bpFile; }

$_rp = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_bp = rtrim($_ENV['BASE_PATH'] ?? '', '/');
$_rp_s = ($_bp !== '' && str_starts_with($_rp, $_bp)) ? substr($_rp, strlen($_bp)) : $_rp;

preg_match('#^/tmboard/share/([a-f0-9]{24})/?$#', $_rp_s, $_m);
$_token = $_m[1] ?? '';

$_viewFile = __DIR__ . '/../../../shares/tmboard/' . $_token . '.json';
if (!$_token || !file_exists($_viewFile)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Not found</title></head>'
       . '<body style="font-family:sans-serif;padding:3rem;text-align:center">'
       . '<h2>Share link not found or expired.</h2></body></html>';
    exit;
}

$_meta    = json_decode(file_get_contents($_viewFile), true) ?: [];
$_sheetId = $_meta['sheet_id'] ?? '';
$_project = $_meta['project']  ?? '';
$_client  = $_meta['client']   ?? '';

if (!$_sheetId || !$_project) { http_response_code(404); exit; }

$_sDir    = __DIR__ . '/../../../sheets/' . $_sheetId;
$_timeFile = $_sDir . '/time.json';
$_allTime  = file_exists($_timeFile) ? (json_decode(file_get_contents($_timeFile), true) ?: []) : [];
$_timeRows = array_values(array_filter($_allTime, function($r) use ($_project) {
    return ($r['Project'] ?? '') === $_project;
}));

$_basePath = $_bp;
$_appRoot  = $_bp . '/';

function _tmsh_e($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title><?= _tmsh_e($_project) ?> · T&amp;MBoard</title>
<style>
@font-face { font-family:'DINBlack';   src:url('<?= $_appRoot ?>fonts/DINBlack.woff2')  format('woff2'); }
@font-face { font-family:'DINRegular'; src:url('<?= $_appRoot ?>fonts/DINMedium.woff2') format('woff2'); }

*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }

:root {
  --accent:    #3b3baa;
  --accent-dk: #2d2d88;
  --accent-lt: #e8e8f8;
  --text:      #1a1a2e;
  --muted:     #666;
  --border:    #ddd;
  --danger:    #c0392b;
  --surface:   #fff;
  --bg:        #f0f4f8;
}

body { background:var(--bg); font-family:'DINRegular',Arial,sans-serif; color:var(--text); min-height:100vh; }

/* ── Header ── */
.tm-header {
  background:var(--accent); color:#fff;
  display:flex; align-items:center; justify-content:space-between;
  padding:.75rem 1.5rem; position:sticky; top:0; z-index:100;
  box-shadow:0 2px 12px rgba(59,59,170,.35);
}
.tm-logo  { font-family:'DINBlack',sans-serif; font-size:1rem; letter-spacing:.07em; }
.tm-proj  { font-size:.8rem; opacity:.8; margin-top:.1rem; }
.menu-btn {
  font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase;
  letter-spacing:.07em; padding:.4rem .85rem; border-radius:6px; cursor:pointer;
  border:1.5px solid rgba(255,255,255,.55); color:#fff; background:transparent;
  transition:background .15s;
}
.menu-btn:hover { background:rgba(255,255,255,.18); }

/* ── Main ── */
.tm-main { max-width:760px; margin:0 auto; padding:1.5rem 1rem 3rem; }

/* ── Not-signed-in banner ── */
.signin-banner {
  background:#fff3cd; border:1px solid #ffc107; border-radius:8px;
  padding:.75rem 1rem; margin-bottom:1rem; font-size:.85rem; color:#856404;
  display:flex; align-items:center; justify-content:space-between; gap:.5rem;
}
.signin-banner button {
  font-family:'DINBlack',sans-serif; font-size:.75rem; text-transform:uppercase;
  letter-spacing:.05em; padding:.3rem .7rem; border-radius:5px; cursor:pointer;
  border:1px solid #856404; background:#fff; color:#856404;
}
.signin-banner button:hover { background:#fff3cd; }

/* ── Time table ── */
.section-head {
  display:flex; align-items:center; justify-content:space-between;
  margin-bottom:.65rem;
}
.section-label {
  font-family:'DINBlack',sans-serif; font-size:.78rem;
  text-transform:uppercase; letter-spacing:.07em; color:var(--muted);
}
.btn-primary {
  font-family:'DINBlack',sans-serif; font-size:.75rem; text-transform:uppercase;
  letter-spacing:.06em; padding:.38rem .9rem; border-radius:6px; cursor:pointer;
  border:none; background:var(--accent); color:#fff;
}
.btn-primary:hover { background:var(--accent-dk); }
.btn-primary:disabled { opacity:.5; cursor:default; }

.tm-table { width:100%; border-collapse:collapse; font-size:.84rem; background:var(--surface);
  border-radius:8px; overflow:hidden; box-shadow:0 1px 8px rgba(0,0,0,.07); }
.tm-table th { font-family:'DINBlack',sans-serif; font-size:.7rem; text-transform:uppercase;
  letter-spacing:.05em; color:var(--muted); padding:.5rem .7rem;
  border-bottom:1px solid var(--border); text-align:left; background:#fafafe; }
.tm-table td { padding:.5rem .7rem; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
.tm-table tr:last-child td { border-bottom:none; }
.tm-table .num { text-align:right; font-variant-numeric:tabular-nums; }
.tm-table .actions { text-align:right; white-space:nowrap; }
.empty-msg { background:var(--surface); border-radius:8px; padding:1.5rem;
  text-align:center; color:#bbb; font-size:.88rem;
  box-shadow:0 1px 8px rgba(0,0,0,.07); }

.badge-you {
  font-size:.65rem; font-family:'DINBlack',sans-serif; text-transform:uppercase;
  letter-spacing:.04em; background:var(--accent-lt); color:var(--accent);
  padding:.1rem .35rem; border-radius:999px; margin-left:.4rem;
}

/* ── Small action buttons ── */
.btn-sm {
  font-family:'DINBlack',sans-serif; font-size:.67rem; text-transform:uppercase;
  letter-spacing:.05em; padding:.22rem .5rem; border-radius:4px; cursor:pointer;
  border:1px solid var(--border); background:#fff; color:var(--text);
}
.btn-sm:hover { background:var(--accent-lt); border-color:var(--accent); color:var(--accent); }
.btn-sm.danger { color:var(--danger); border-color:var(--danger); }
.btn-sm.danger:hover { background:#fff0ef; }

/* ── Total bar ── */
.total-bar {
  display:flex; gap:2rem; padding:.6rem .7rem;
  background:var(--accent-lt); border-radius:0 0 8px 8px;
  font-size:.82rem; margin-top:-1px;
}
.total-bar span { font-family:'DINBlack',sans-serif; color:var(--accent); }

/* ── Overlays ── */
.overlay {
  display:none; position:fixed; inset:0; z-index:200;
  background:rgba(0,0,0,.45); align-items:center; justify-content:center;
}
.overlay.open { display:flex; }
.auth-dialog, .tm-dialog {
  background:#fff; border-radius:12px; width:min(420px,95vw);
  max-height:92vh; overflow-y:auto; padding:1.4rem 1.5rem;
  box-shadow:0 8px 40px rgba(0,0,0,.22);
}
.dlg-title {
  font-family:'DINBlack',sans-serif; font-size:1rem;
  text-transform:uppercase; letter-spacing:.06em; color:var(--accent);
  margin-bottom:1rem;
}
.field-group { margin-bottom:.75rem; }
.field-group label {
  display:block; font-family:'DINBlack',sans-serif; font-size:.7rem;
  text-transform:uppercase; letter-spacing:.05em; color:var(--muted); margin-bottom:.25rem;
}
.field-input {
  display:block; width:100%; padding:.45rem .7rem;
  font-family:'DINRegular',Arial,sans-serif; font-size:.85rem; color:var(--text);
  border:1px solid var(--border); border-radius:6px; outline:none;
  transition:border-color .15s; background:#fff;
}
.field-input:focus { border-color:var(--accent); }
.field-row { display:grid; grid-template-columns:1fr 1fr; gap:.6rem; }
.date-wrap { position:relative; }
.date-wrap input[type="date"] { -webkit-appearance:none; appearance:none; width:100%; box-sizing:border-box; background:#fff; padding-right:2.2rem; cursor:pointer; }
.date-wrap input[type="date"]::-webkit-calendar-picker-indicator { position:absolute; right:0; top:0; bottom:0; width:2.2rem; opacity:0; cursor:pointer; }
.date-wrap::after { content:''; position:absolute; right:.65rem; top:50%; transform:translateY(-50%); width:15px; height:15px; pointer-events:none; background-image:url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxNSIgaGVpZ2h0PSIxNSIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiM4ODgiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48cmVjdCB4PSIzIiB5PSI0IiB3aWR0aD0iMTgiIGhlaWdodD0iMTgiIHJ4PSIyIi8+PGxpbmUgeDE9IjE2IiB5MT0iMiIgeDI9IjE2IiB5Mj0iNiIvPjxsaW5lIHgxPSI4IiB5MT0iMiIgeDI9IjgiIHkyPSI2Ii8+PGxpbmUgeDE9IjMiIHkxPSIxMCIgeDI9IjIxIiB5Mj0iMTAiLz48L3N2Zz4="); background-repeat:no-repeat; background-size:contain; }
textarea.field-input { resize:vertical; min-height:3.5rem; }
.dialog-actions { display:flex; justify-content:flex-end; gap:.6rem; margin-top:1rem; }
.btn-cancel { font-family:'DINBlack',sans-serif; font-size:.75rem; text-transform:uppercase;
  letter-spacing:.06em; padding:.4rem 1rem; border-radius:6px; cursor:pointer;
  border:1px solid var(--border); background:#fff; color:var(--muted); }
.btn-cancel:hover { background:#f5f5f5; }
.auth-err, .dlg-err { color:var(--danger); font-size:.78rem; margin-top:.5rem; }
.auth-notice { background:#fef9e7; border:1px solid #f1c40f; border-radius:6px;
  padding:.55rem .7rem; font-size:.82rem; margin-bottom:.75rem; }
</style>
</head>
<body>

<div class="tm-header">
  <div>
    <div class="tm-logo">T&amp;MBoard</div>
    <div class="tm-proj"><?= _tmsh_e($_project) ?><?= $_client ? ' · ' . _tmsh_e($_client) : '' ?></div>
  </div>
  <button class="menu-btn" onclick="openProfileDialog()">
    <span id="menuLabel">Sign In</span>
  </button>
</div>

<div class="tm-main">

  <div class="signin-banner" id="signinBanner" style="display:none">
    Sign in to log your time for this project.
    <button onclick="openProfileDialog()">Sign In</button>
  </div>

  <!-- Time Entries -->
  <div class="section-head">
    <div class="section-label">Time Entries</div>
    <button class="btn-primary" id="logTimeBtn" onclick="openLogTimeDialog()" style="display:none">+ Log My Time</button>
  </div>

  <?php if ($_timeRows): ?>
  <table class="tm-table" id="timeTable">
    <thead><tr>
      <th>Date</th><th>Staff</th><th class="num">Hours</th><th>Description</th><th class="actions"></th>
    </tr></thead>
    <tbody>
    <?php foreach ($_timeRows as $t): ?>
    <tr data-email="<?= _tmsh_e($t['Staff Email'] ?? '') ?>">
      <td><?= _tmsh_e($t['Date'] ?? '') ?></td>
      <td><?= _tmsh_e($t['Staff Name'] ?? '') ?><span class="badge-you" style="display:none">You</span></td>
      <td class="num"><?= _tmsh_e($t['Hours'] ?? '') ?></td>
      <td><?= _tmsh_e($t['Description'] ?? '') ?></td>
      <td class="actions" style="display:none">
        <button class="btn-sm" onclick='openEditTimeDialog(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)'>Edit</button>
        <button class="btn-sm danger" onclick="deleteMyTime(<?= htmlspecialchars(json_encode($t['Ref Number'] ?? ''), ENT_QUOTES) ?>)">Del</button>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="total-bar">
    Total: <span id="totalHours"><?= array_sum(array_column($_timeRows,'Hours')) ?></span>h
  </div>
  <?php else: ?>
  <div class="empty-msg" id="emptyMsg">No time entries yet for this project.</div>
  <?php endif; ?>

</div><!-- /tm-main -->

<!-- ── Auth overlay ── -->
<div class="overlay" id="profileOverlay" onclick="if(event.target===this)closeProfileDialog()">
  <div class="auth-dialog" onclick="event.stopPropagation()">

    <!-- Sign-in form -->
    <div id="authForm">
      <div class="dlg-title">Sign In</div>
      <div class="auth-notice" id="authConfirmNotice" style="display:none">
        No account found for <strong id="authConfirmEmail"></strong>. Create one?
      </div>
      <div class="field-group"><label>Email</label>
        <input class="field-input" id="authEmail" type="email" placeholder="you@example.com" autocomplete="email" />
      </div>
      <div class="field-group"><label>Password</label>
        <input class="field-input" id="authPass" type="password" placeholder="Password" autocomplete="current-password" />
      </div>
      <div class="auth-err" id="authErr"></div>
      <div class="dialog-actions">
        <button class="btn-cancel" onclick="closeProfileDialog()">Cancel</button>
        <button class="btn-primary" id="authSubmitBtn" onclick="submitAuth(false)">Sign In</button>
        <button class="btn-primary" id="authCreateBtn" style="display:none" onclick="submitAuth(true)">Create Account</button>
      </div>
    </div>

    <!-- Profile panel (signed in) -->
    <div id="profilePanel" style="display:none">
      <div class="dlg-title">Profile</div>
      <div class="field-group"><label>Name</label>
        <input class="field-input" id="profileName" type="text" placeholder="Your name" />
      </div>
      <div style="font-size:.8rem;color:var(--muted);margin-bottom:1rem;" id="profileEmailLabel"></div>
      <div class="auth-err" id="profileErr"></div>
      <div class="dialog-actions">
        <button class="btn-cancel" onclick="signOut()">Sign Out</button>
        <button class="btn-primary" onclick="saveName()">Save Name</button>
      </div>
    </div>

  </div>
</div>

<!-- ── Log / Edit Time dialog ── -->
<div class="overlay" id="timeOverlay" onclick="if(event.target===this)forceCloseTimeDialog()"
     onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitTime();}">
  <div class="tm-dialog" onclick="event.stopPropagation()">
    <div class="dlg-title" id="timeDlgTitle">Log Time</div>
    <div class="field-row">
      <div class="field-group"><label>Date *</label><div class="date-wrap"><input class="field-input" id="timeDate" type="date" /></div></div>
      <div class="field-group"><label>Hours *</label><input class="field-input" id="timeHours" type="number" min="0" step="0.25" placeholder="1.5" /></div>
    </div>
    <div class="field-group"><label>Description</label><textarea class="field-input" id="timeDesc" placeholder="What did you work on?"></textarea></div>
    <div class="dlg-err" id="timeErr"></div>
    <div class="dialog-actions">
      <button class="btn-cancel" onclick="forceCloseTimeDialog()">Cancel</button>
      <button class="btn-primary" id="timeSubmitBtn" onclick="submitTime()">Log Time</button>
    </div>
  </div>
</div>

<script>
var SHEET_ID = <?= json_encode($_sheetId) ?>;
var PROJECT  = <?= json_encode($_project) ?>;
var CLIENT   = <?= json_encode($_client) ?>;
var APP_ROOT = <?= json_encode(rtrim($_basePath, '/') . '/') ?>;

// ── Auth state ────────────────────────────────────────────────────────────────
var _user = null;  // { email, name }

function _loadUser() {
  try { _user = JSON.parse(sessionStorage.getItem('tmboard_user') || 'null'); } catch(e) { _user = null; }
}
function _saveUser() { try { sessionStorage.setItem('tmboard_user', JSON.stringify(_user)); } catch(e) {} }
function _clearUser() { _user = null; try { sessionStorage.removeItem('tmboard_user'); } catch(e) {} }

function _updateSignedInState() {
  var signed = !!(_user && _user.email);
  document.getElementById('signinBanner').style.display = signed ? 'none' : 'flex';
  document.getElementById('logTimeBtn').style.display   = signed ? '' : 'none';
  document.getElementById('menuLabel').textContent = signed ? (_user.name || _user.email) : 'Sign In';

  // Mark rows belonging to this user
  document.querySelectorAll('#timeTable tbody tr').forEach(function(tr) {
    var email = tr.getAttribute('data-email') || '';
    var isMe  = signed && email && email.toLowerCase() === (_user.email || '').toLowerCase();
    var badge = tr.querySelector('.badge-you');
    var acts  = tr.querySelector('.actions');
    if (badge) badge.style.display = isMe ? '' : 'none';
    if (acts)  acts.style.display  = isMe ? '' : 'none';
  });
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function post(url, data, cb) {
  var fd = new FormData();
  fd.append('id', SHEET_ID);
  for (var k in data) fd.append(k, data[k]);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + url);
  xhr.onload = function() {
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = {error: xhr.responseText}; }
    cb(r);
  };
  xhr.onerror = function() { cb({error:'Network error'}); };
  xhr.send(fd);
}
function today() { return new Date().toISOString().slice(0,10); }

// ── Esc ───────────────────────────────────────────────────────────────────────
document.addEventListener('keydown', function(e) {
  if (e.key !== 'Escape') return;
  if (document.getElementById('timeOverlay').classList.contains('open'))   { forceCloseTimeDialog();  return; }
  if (document.getElementById('profileOverlay').classList.contains('open')){ closeProfileDialog();     return; }
});

// ── Profile / Auth ────────────────────────────────────────────────────────────
function openProfileDialog() {
  document.getElementById('authErr').textContent  = '';
  document.getElementById('authConfirmNotice').style.display = 'none';
  document.getElementById('authSubmitBtn').style.display   = '';
  document.getElementById('authCreateBtn').style.display   = 'none';
  if (_user) {
    document.getElementById('authForm').style.display    = 'none';
    document.getElementById('profilePanel').style.display = '';
    document.getElementById('profileName').value          = _user.name || '';
    document.getElementById('profileEmailLabel').textContent = _user.email;
    document.getElementById('profileErr').textContent     = '';
  } else {
    document.getElementById('authForm').style.display    = '';
    document.getElementById('profilePanel').style.display = 'none';
    document.getElementById('authEmail').value = '';
    document.getElementById('authPass').value  = '';
  }
  document.getElementById('profileOverlay').classList.add('open');
  setTimeout(function(){
    if (!_user) document.getElementById('authEmail').focus();
    else        document.getElementById('profileName').focus();
  }, 60);
}
function closeProfileDialog() { document.getElementById('profileOverlay').classList.remove('open'); }

function submitAuth(confirmNew) {
  var email = document.getElementById('authEmail').value.trim();
  var pass  = document.getElementById('authPass').value;
  var err   = document.getElementById('authErr');
  if (!email || !pass) { err.textContent = 'Email and password are required.'; return; }
  var btn = confirmNew ? document.getElementById('authCreateBtn') : document.getElementById('authSubmitBtn');
  btn.disabled = true;
  var data = {email: email, password: pass};
  if (confirmNew) data.confirm_new = '1';
  post('push/collabAuth.php', data, function(r) {
    btn.disabled = false;
    if (r.error) { err.textContent = r.error; return; }
    if (r.prompt_create) {
      document.getElementById('authConfirmEmail').textContent = email;
      document.getElementById('authConfirmNotice').style.display = '';
      document.getElementById('authSubmitBtn').style.display  = 'none';
      document.getElementById('authCreateBtn').style.display  = '';
      return;
    }
    if (r.ok) {
      _user = { email: r.email || email, name: (r.bio && r.bio.Name) || '' };
      _saveUser();
      closeProfileDialog();
      _updateSignedInState();
    } else {
      err.textContent = r.message || 'Authentication failed.';
    }
  });
}

function saveName() {
  var name = document.getElementById('profileName').value.trim();
  var err  = document.getElementById('profileErr');
  _user.name = name;
  _saveUser();
  _updateSignedInState();
  closeProfileDialog();
}

function signOut() { _clearUser(); closeProfileDialog(); _updateSignedInState(); }

// ── Log Time ──────────────────────────────────────────────────────────────────
var _editingTime = null;
function openLogTimeDialog() {
  if (!_user) { openProfileDialog(); return; }
  _editingTime = null;
  document.getElementById('timeDlgTitle').textContent = 'Log Time · ' + PROJECT;
  document.getElementById('timeDate').value  = today();
  document.getElementById('timeHours').value = '';
  document.getElementById('timeDesc').value  = '';
  document.getElementById('timeErr').textContent = '';
  document.getElementById('timeSubmitBtn').textContent = 'Log Time';
  document.getElementById('timeSubmitBtn').disabled = false;
  document.getElementById('timeOverlay').classList.add('open');
  setTimeout(function(){ document.getElementById('timeHours').focus(); }, 60);
}
function openEditTimeDialog(t) {
  if (!_user) { openProfileDialog(); return; }
  _editingTime = t;
  document.getElementById('timeDlgTitle').textContent = 'Edit Time · ' + PROJECT;
  document.getElementById('timeDate').value  = t['Date']        || '';
  document.getElementById('timeHours').value = t['Hours']       || '';
  document.getElementById('timeDesc').value  = t['Description'] || '';
  document.getElementById('timeErr').textContent = '';
  document.getElementById('timeSubmitBtn').textContent = 'Update';
  document.getElementById('timeSubmitBtn').disabled = false;
  document.getElementById('timeOverlay').classList.add('open');
}
function forceCloseTimeDialog() { document.getElementById('timeOverlay').classList.remove('open'); _editingTime = null; }
function submitTime() {
  var date  = document.getElementById('timeDate').value.trim();
  var hours = document.getElementById('timeHours').value.trim();
  var err   = document.getElementById('timeErr');
  if (!date || !hours) { err.textContent = 'Date and hours are required.'; return; }
  var btn = document.getElementById('timeSubmitBtn');
  btn.disabled = true;
  var data = {
    date:        date,
    project:     PROJECT,
    client:      CLIENT,
    staff_name:  _user.name  || '',
    staff_email: _user.email || '',
    hours:       hours,
    description: document.getElementById('timeDesc').value.trim(),
  };
  if (_editingTime) {
    data.row_id = _editingTime['Ref Number'] || '';
    post('push/tmUpdateTime.php', data, function(r) {
      if (r.error) { err.textContent = r.error; btn.disabled = false; return; }
      forceCloseTimeDialog(); location.reload();
    });
  } else {
    post('push/tmAddTime.php', data, function(r) {
      if (r.error) { err.textContent = r.error; btn.disabled = false; return; }
      forceCloseTimeDialog(); location.reload();
    });
  }
}
function deleteMyTime(rowId) {
  if (!rowId || !confirm('Delete this time entry?')) return;
  post('push/tmDeleteTime.php', {row_id: rowId}, function(r) {
    if (r.error) { alert('Error: ' + r.error); return; }
    location.reload();
  });
}

// ── Date auto-wrap ────────────────────────────────────────────────────────────
document.querySelectorAll('input[type="date"]').forEach(function(inp) {
  if (inp.closest('.date-wrap')) return;
  var w = document.createElement('div'); w.className = 'date-wrap';
  inp.parentNode.insertBefore(w, inp); w.appendChild(inp);
});

// ── Boot ──────────────────────────────────────────────────────────────────────
_loadUser();
_updateSignedInState();
</script>
</body>
</html>
