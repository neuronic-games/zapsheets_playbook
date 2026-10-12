<?php
/**
 * T&MBoard staff collab view — per-project shared link.
 * URL: /tmboard/share/{24-char-hex-token}
 * Staff log time and materials for the project; they can edit their own entries.
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

$_hwFile = $_sDir . '/hardware.json';
$_allHw  = file_exists($_hwFile) ? (json_decode(file_get_contents($_hwFile), true) ?: []) : [];
$_hwRows = array_values(array_filter($_allHw, function($r) use ($_project) {
    return ($r['Project'] ?? '') === $_project;
}));
$_totalHrs = array_sum(array_map(function($r){ return (float)ltrim((string)($r['Hours']  ?? 0), "'"); }, $_timeRows));
$_totalHw  = array_sum(array_map(function($r){ return (float)ltrim((string)($r['Amount'] ?? 0), "'"); }, $_hwRows));

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

/* ── Header (matches owner view) ── */
.tm-header {
  background:#1a1a2e; color:#fff;
  display:flex; align-items:center; justify-content:space-between; gap:1rem;
  padding:.6rem 1.5rem; position:sticky; top:0; z-index:100;
  box-shadow:0 2px 12px rgba(0,0,0,.35);
}
.tm-header-left { display:flex; flex-direction:column; gap:.05rem; min-width:0; }
.tm-logo { font-family:'DINBlack',sans-serif; font-size:1.2rem; letter-spacing:.07em; color:#7c9eff; }
.tm-logo-sub { font-size:.7rem; opacity:.65; letter-spacing:.02em; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.tm-header-right { display:flex; align-items:center; gap:.5rem; flex-shrink:0; }
.tm-tabs { display:flex; gap:.2rem; }
.tm-tab {
  font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase;
  letter-spacing:.07em; padding:.4rem .9rem; border-radius:6px; cursor:pointer;
  border:none; color:rgba(255,255,255,.7); background:rgba(255,255,255,.1);
  transition:all .15s; white-space:nowrap;
}
.tm-tab:hover { background:rgba(255,255,255,.2); color:#fff; }
.tm-tab.active { background:#fff; color:#1a1a2e; }
.btn-user-icon {
  width:2rem; height:2rem; border-radius:50%;
  border:1.5px solid rgba(255,255,255,.45); background:transparent;
  color:rgba(255,255,255,.8); cursor:pointer; display:inline-flex;
  align-items:center; justify-content:center; flex-shrink:0;
  transition:background .15s, border-color .15s;
}
.btn-user-icon:hover { background:rgba(255,255,255,.18); border-color:#fff; }
.btn-user-icon.signed-in { background:rgba(255,255,255,.2); border-color:#fff; color:#fff; }

/* ── User dropdown menu ── */
.user-menu-wrap { position:relative; }
.user-menu {
  position:absolute; right:0; top:calc(100% + .4rem);
  background:#1a1a2e; border-radius:10px; min-width:190px;
  box-shadow:0 8px 32px rgba(0,0,0,.5); padding:.35rem 0;
  z-index:400; display:none;
}
.user-menu.open { display:block; }
.user-menu-who { padding:.5rem 1.15rem .4rem; font-size:.75rem; color:rgba(255,255,255,.55); word-break:break-all; }
.user-menu-item {
  display:block; width:100%; padding:.65rem 1.15rem;
  font-family:'DINBlack',sans-serif; font-size:.78rem;
  text-transform:uppercase; letter-spacing:.08em;
  color:#fff; background:transparent; border:none;
  cursor:pointer; text-align:left; transition:background .12s;
}
.user-menu-item:hover { background:rgba(255,255,255,.1); }
.user-menu-item.signout { color:#ff8080; }
.user-menu-item.signin  { color:#90c8ff; }
.user-menu-divider { border:none; border-top:1px solid rgba(255,255,255,.1); margin:.3rem 0; }

/* ── Main ── */
.tm-main { max-width:860px; margin:0 auto; padding:1.5rem 1rem 3rem; }
.tm-view { display:none; }
.tm-view.active { display:block; }

/* ── Signed-in gating ── */
body:not(.tm-signed-in) .auth-only { display:none !important; }
body.tm-signed-in .signed-out-only { display:none !important; }
tr:not(.mine) .own-only { visibility:hidden; }

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

/* ── Sections / tables ── */
.section-head { display:flex; align-items:center; justify-content:space-between; gap:.6rem; margin-bottom:.65rem; }
.section-label { font-family:'DINBlack',sans-serif; font-size:.78rem; text-transform:uppercase; letter-spacing:.07em; color:var(--muted); }
.section-total { font-size:.8rem; color:var(--muted); }
.section-right { display:flex; align-items:center; gap:.6rem; }

.tm-table-wrap { overflow-x:auto; background:var(--surface); border-radius:8px; box-shadow:0 1px 8px rgba(0,0,0,.07); }
.tm-table { width:100%; border-collapse:collapse; font-size:.84rem; }
.tm-table th { font-family:'DINBlack',sans-serif; font-size:.7rem; text-transform:uppercase;
  letter-spacing:.05em; color:var(--muted); padding:.5rem .7rem;
  border-bottom:1px solid var(--border); text-align:left; background:#fafafe; white-space:nowrap; }
.tm-table td { padding:.5rem .7rem; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
.tm-table tr:last-child td { border-bottom:none; }
.tm-table .num { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
.tm-table .actions { text-align:right; white-space:nowrap; }
.tm-table a { color:var(--accent); }
.empty-msg { background:var(--surface); border-radius:8px; padding:1.5rem;
  text-align:center; color:#999; font-size:.88rem; box-shadow:0 1px 8px rgba(0,0,0,.07); }

.badge-you {
  font-size:.65rem; font-family:'DINBlack',sans-serif; text-transform:uppercase;
  letter-spacing:.04em; background:var(--accent-lt); color:var(--accent);
  padding:.1rem .35rem; border-radius:999px; margin-left:.4rem; display:none;
}
tr.mine .badge-you { display:inline; }

/* ── Buttons ── */
.btn-primary {
  font-family:'DINBlack',sans-serif; font-size:.75rem; text-transform:uppercase;
  letter-spacing:.06em; padding:.4rem 1rem; border-radius:6px; cursor:pointer;
  border:none; background:var(--accent); color:#fff;
}
.btn-primary:hover { background:var(--accent-dk); }
.btn-primary:disabled { opacity:.5; cursor:default; }
.btn-sm {
  font-family:'DINBlack',sans-serif; font-size:.67rem; text-transform:uppercase;
  letter-spacing:.05em; padding:.22rem .5rem; border-radius:4px; cursor:pointer;
  border:1px solid var(--border); background:#fff; color:var(--text); text-decoration:none;
}
.btn-sm:hover { background:var(--accent-lt); border-color:var(--accent); color:var(--accent); }
.btn-sm.primary { background:var(--accent); border-color:var(--accent); color:#fff; }
.btn-sm.primary:hover { background:var(--accent-dk); color:#fff; }
.btn-sm.danger { color:var(--danger); border-color:var(--danger); }
.btn-sm.danger:hover { background:#fff0ef; }

/* ── Overlays ── */
.overlay {
  display:none; position:fixed; inset:0; z-index:200;
  background:rgba(0,0,0,.45); align-items:center; justify-content:center;
}
.overlay.open { display:flex; }
.tm-dialog {
  background:#fff; border-radius:12px; width:min(460px,95vw);
  max-height:92vh; overflow-y:auto; padding:1.4rem 1.5rem;
  box-shadow:0 8px 40px rgba(0,0,0,.22);
}
.dlg-title { font-family:'DINBlack',sans-serif; font-size:1rem; text-transform:uppercase; letter-spacing:.06em; color:var(--accent); margin-bottom:1rem; }
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
select.field-input {
  -webkit-appearance:none; appearance:none;
  background-image:url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMiIgaGVpZ2h0PSI4IiB2aWV3Qm94PSIwIDAgMTIgOCI+PHBhdGggZD0iTTEgMWw1IDUgNS01IiBzdHJva2U9IiM2NjYiIHN0cm9rZS13aWR0aD0iMS41IiBmaWxsPSJub25lIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiLz48L3N2Zz4=");
  background-repeat:no-repeat; background-position:right .7rem center; background-size:10px 7px; padding-right:2rem;
}
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
.dlg-err { color:var(--danger); font-size:.78rem; margin-top:.5rem; }
.auth-notice { background:#fff8e8; border:1px solid #f0d898; color:#5a4a1a; border-radius:6px;
  padding:.7rem .85rem; font-size:.9rem; line-height:1.4; margin-bottom:.5rem; }

@media (max-width:600px) {
  .tm-header { padding:.6rem 1rem; }
  .tm-tab { padding:.4rem .6rem; }
  .field-row { grid-template-columns:1fr; }
  .hide-sm { display:none; }
}
</style>
</head>
<body>

<div class="tm-header">
  <div class="tm-header-left">
    <div class="tm-logo">T&amp;MBoard</div>
    <div class="tm-logo-sub"><?= _tmsh_e($_project) ?><?= $_client ? ' · ' . _tmsh_e($_client) : '' ?></div>
  </div>
  <div class="tm-header-right">
    <nav class="tm-tabs">
      <button class="tm-tab active" id="tabTime" onclick="switchView('time')">Time</button>
      <button class="tm-tab" id="tabMaterials" onclick="switchView('materials')">Materials</button>
    </nav>
    <div class="user-menu-wrap">
      <button class="btn-user-icon" id="userBtn" onclick="toggleUserMenu(event)" title="Menu">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
        </svg>
      </button>
      <div class="user-menu" id="userMenu" onclick="event.stopPropagation()">
        <div class="user-menu-who auth-only" id="menuWho"></div>
        <button class="user-menu-item auth-only" onclick="userMenuAction('profile')">Profile</button>
        <button class="user-menu-item" onclick="userMenuAction('fetch')">Fetch</button>
        <hr class="user-menu-divider" />
        <button class="user-menu-item signin" id="menuSignInOut" onclick="userMenuAction('signinout')">Sign In</button>
      </div>
    </div>
  </div>
</div>

<div class="tm-main">

  <div class="signin-banner signed-out-only">
    Sign in to log time and materials for this project.
    <button onclick="openAuthDialog()">Sign In</button>
  </div>

  <!-- ══ TIME VIEW ══ -->
  <div class="tm-view active" id="viewTime">
    <div class="section-head">
      <div class="section-label">Time Entries</div>
      <div class="section-right">
        <div class="section-total"><?= rtrim(rtrim(number_format($_totalHrs, 2), '0'), '.') ?>h total</div>
        <button class="btn-sm primary auth-only" onclick="openAddTimeDialog()">+ Log Time</button>
      </div>
    </div>
    <?php if ($_timeRows): ?>
    <div class="tm-table-wrap">
    <table class="tm-table">
      <thead><tr>
        <th>Date</th><th>Staff</th><th class="num">Hours</th><th>Description</th><th class="actions"></th>
      </tr></thead>
      <tbody>
      <?php foreach ($_timeRows as $t): ?>
      <tr data-email="<?= _tmsh_e(ltrim($t['Staff Email'] ?? '', "'")) ?>">
        <td><?= _tmsh_e($t['Date'] ?? '') ?></td>
        <td><?= _tmsh_e($t['Staff Name'] ?? '') ?><span class="badge-you">You</span></td>
        <td class="num"><?= _tmsh_e($t['Hours'] ?? '') ?></td>
        <td><?= _tmsh_e($t['Description'] ?? '') ?></td>
        <td class="actions">
          <button class="btn-sm own-only" onclick="openEditTimeDialog(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)">Edit</button>
          <button class="btn-sm danger own-only" onclick="deleteEntry('time', <?= htmlspecialchars(json_encode($t['Ref Number'] ?? ''), ENT_QUOTES) ?>)">Del</button>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php else: ?>
    <div class="empty-msg">No time entries yet for this project.</div>
    <?php endif; ?>
  </div>

  <!-- ══ MATERIALS VIEW ══ -->
  <div class="tm-view" id="viewMaterials">
    <div class="section-head">
      <div class="section-label">Hardware &amp; Materials</div>
      <div class="section-right">
        <div class="section-total">$<?= number_format($_totalHw, 2) ?> total</div>
        <button class="btn-sm primary auth-only" onclick="openAddHwDialog()">+ Add Cost</button>
      </div>
    </div>
    <?php if ($_hwRows): ?>
    <div class="tm-table-wrap">
    <table class="tm-table">
      <thead><tr>
        <th>Date</th><th>Description</th><th class="num">Amount</th><th>Status</th><th>Added By</th><th class="hide-sm">Notes</th><th class="actions"></th>
      </tr></thead>
      <tbody>
      <?php foreach ($_hwRows as $h): $buy = ltrim(trim($h['Buy URL'] ?? ''), "'"); ?>
      <tr data-name="<?= _tmsh_e(ltrim($h['Added By'] ?? '', "'")) ?>">
        <td><?= _tmsh_e($h['Date'] ?? '') ?></td>
        <td><?= ($buy && preg_match('#^https?://#i', $buy))
              ? '<a href="' . _tmsh_e($buy) . '" target="_blank" rel="noopener">' . _tmsh_e($h['Description'] ?? '') . ' ↗</a>'
              : _tmsh_e($h['Description'] ?? '') ?><span class="badge-you">You</span></td>
        <td class="num">$<?= number_format((float)ltrim((string)($h['Amount'] ?? 0), "'"), 2) ?></td>
        <td><?= _tmsh_e($h['Status'] ?? '') ?></td>
        <td><?= _tmsh_e(ltrim($h['Added By'] ?? '', "'")) ?></td>
        <td class="hide-sm"><?= _tmsh_e($h['Notes'] ?? '') ?></td>
        <td class="actions">
          <button class="btn-sm own-only" onclick="openEditHwDialog(<?= htmlspecialchars(json_encode($h), ENT_QUOTES) ?>)">Edit</button>
          <button class="btn-sm danger own-only" onclick="deleteEntry('hw', <?= htmlspecialchars(json_encode($h['Ref Number'] ?? ''), ENT_QUOTES) ?>)">Del</button>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <?php else: ?>
    <div class="empty-msg">No costs recorded yet for this project.</div>
    <?php endif; ?>
  </div>

</div><!-- /tm-main -->

<!-- ── Sign In / Profile dialog (action-only, never dirty) ── -->
<div class="overlay" id="authOverlay" onclick="if(event.target===this)forceCloseAuthDialog()">
  <div class="tm-dialog" style="max-width:380px" onclick="event.stopPropagation()">

    <div id="authForm" onkeydown="if(event.key==='Enter'){event.preventDefault();submitAuth(false);}">
      <div class="dlg-title">Sign In</div>
      <div class="field-group"><label>Email</label>
        <input class="field-input" id="authEmail" type="email" placeholder="you@example.com" autocomplete="email" />
      </div>
      <div class="field-group"><label>Password</label>
        <input class="field-input" id="authPass" type="password" placeholder="At least 4 characters" autocomplete="current-password" />
      </div>
      <div class="dlg-err" id="authErr"></div>
      <div class="dialog-actions">
        <button class="btn-cancel" onclick="forceCloseAuthDialog()">Cancel</button>
        <button class="btn-primary" id="authSubmitBtn" onclick="submitAuth(false)">Sign In</button>
      </div>
    </div>

    <div id="authConfirm" style="display:none" onkeydown="if(event.key==='Enter'){event.preventDefault();submitAuth(true);}">
      <div class="dlg-title">Create Account</div>
      <p class="auth-notice">No account found for <strong id="authConfirmEmail"></strong>. Would you like to create one?</p>
      <div class="dlg-err" id="authConfirmErr"></div>
      <div class="dialog-actions">
        <button class="btn-cancel" onclick="_authConfirmBack()">Back</button>
        <button class="btn-primary" id="authCreateBtn" onclick="submitAuth(true)">Create Account</button>
      </div>
    </div>

    <div id="profilePanel" style="display:none" onkeydown="if(event.key==='Enter'){event.preventDefault();saveName();}">
      <div class="dlg-title">Profile</div>
      <div class="field-group"><label>Name</label>
        <input class="field-input" id="profileName" type="text" placeholder="Shown on your time and cost entries" />
      </div>
      <div style="font-size:.8rem;color:var(--muted);" id="profileEmailLabel"></div>
      <p class="auth-notice" id="profileNote" style="display:none;margin-top:.75rem"></p>
      <div class="dlg-err" id="profileErr"></div>
      <div class="dialog-actions">
        <button class="btn-cancel" onclick="forceCloseAuthDialog()">Cancel</button>
        <button class="btn-primary" id="profileSaveBtn" onclick="saveName()">Save</button>
      </div>
    </div>

  </div>
</div>

<!-- ── Log / Edit Time dialog ── -->
<div class="overlay" id="timeOverlay" onclick="if(event.target===this)forceCloseTimeDialog()">
  <div class="tm-dialog" onclick="event.stopPropagation()"
       onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitTime();}">
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

<!-- ── Add / Edit Cost dialog ── -->
<div class="overlay" id="hwOverlay" onclick="if(event.target===this)forceCloseHwDialog()">
  <div class="tm-dialog" onclick="event.stopPropagation()"
       onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitHw();}">
    <div class="dlg-title" id="hwDlgTitle">Add Cost</div>
    <div class="field-row">
      <div class="field-group"><label>Date</label><div class="date-wrap"><input class="field-input" id="hwDate" type="date" /></div></div>
      <div class="field-group"><label>Amount ($) *</label><input class="field-input" id="hwAmount" type="number" min="0" step="0.01" placeholder="0.00" /></div>
    </div>
    <div class="field-row">
      <div class="field-group"><label>Description *</label><input class="field-input" id="hwDesc" type="text" placeholder="Arduino kit, filament, etc." /></div>
      <div class="field-group"><label>Status</label>
        <select class="field-input" id="hwStatus">
          <option value="">—</option>
          <option>Planned</option>
          <option>Ordered</option>
          <option>Received</option>
          <option>Shipped</option>
          <option>Delivered</option>
          <option>Returned</option>
          <option>Lost</option>
        </select>
      </div>
    </div>
    <div class="field-group"><label>Buy URL</label><input class="field-input" id="hwBuyUrl" type="url" placeholder="https://…" /></div>
    <div class="field-group"><label>Notes</label><textarea class="field-input" id="hwNotes" placeholder="Optional notes…"></textarea></div>
    <div class="dlg-err" id="hwErr"></div>
    <div class="dialog-actions">
      <button class="btn-cancel" onclick="forceCloseHwDialog()">Cancel</button>
      <button class="btn-primary" id="hwSubmitBtn" onclick="submitHw()">Add Cost</button>
    </div>
  </div>
</div>

<script>
var SHEET_ID = <?= json_encode($_sheetId) ?>;
var PROJECT  = <?= json_encode($_project) ?>;
var CLIENT   = <?= json_encode($_client) ?>;
var APP_ROOT = <?= json_encode(rtrim($_basePath, '/') . '/') ?>;
var VIEW_KEY = 'tmboard_share_view_' + PROJECT;

// ── Auth state ────────────────────────────────────────────────────────────────
var _user = null;  // { email, name }

function _loadUser() {
  try { _user = JSON.parse(sessionStorage.getItem('tmboard_user') || 'null'); } catch(e) { _user = null; }
}
function _saveUser()  { try { sessionStorage.setItem('tmboard_user', JSON.stringify(_user)); } catch(e) {} }
function _clearUser() { _user = null; try { sessionStorage.removeItem('tmboard_user'); } catch(e) {} }

function _updateSignedInState() {
  var signed = !!(_user && _user.email);
  document.body.classList.toggle('tm-signed-in', signed);
  var btn = document.getElementById('userBtn');
  btn.classList.toggle('signed-in', signed);
  btn.title = signed ? 'Signed in as ' + _user.email : 'Menu';
  document.getElementById('menuWho').textContent = signed ? (_user.name ? _user.name + ' · ' : '') + _user.email : '';
  var so = document.getElementById('menuSignInOut');
  so.textContent = signed ? 'Sign Out' : 'Sign In';
  so.className   = 'user-menu-item ' + (signed ? 'signout' : 'signin');

  // Mark rows this user added — only those get Edit / Del.
  // Time rows match on Staff Email; material rows match on Added By (name).
  var me     = signed ? _user.email.toLowerCase() : '';
  var myName = signed ? (_user.name || '').trim().toLowerCase() : '';
  document.querySelectorAll('tr[data-email]').forEach(function(tr) {
    var email = (tr.getAttribute('data-email') || '').toLowerCase();
    tr.classList.toggle('mine', !!me && email === me);
  });
  document.querySelectorAll('tr[data-name]').forEach(function(tr) {
    var name = (tr.getAttribute('data-name') || '').trim().toLowerCase();
    tr.classList.toggle('mine', !!myName && name === myName);
  });
}

// ── Tabs ──────────────────────────────────────────────────────────────────────
function switchView(name) {
  document.getElementById('viewTime').classList.toggle('active', name === 'time');
  document.getElementById('viewMaterials').classList.toggle('active', name === 'materials');
  document.getElementById('tabTime').classList.toggle('active', name === 'time');
  document.getElementById('tabMaterials').classList.toggle('active', name === 'materials');
  try { sessionStorage.setItem(VIEW_KEY, name); } catch(e) {}
}

// ── User menu ─────────────────────────────────────────────────────────────────
function toggleUserMenu(e) { e.stopPropagation(); document.getElementById('userMenu').classList.toggle('open'); }
function closeUserMenu()   { document.getElementById('userMenu').classList.remove('open'); }
document.addEventListener('click', closeUserMenu);

function userMenuAction(action) {
  closeUserMenu();
  if (action === 'profile') {
    openAuthDialog();
  } else if (action === 'fetch') {
    document.getElementById('userBtn').style.opacity = '.5';
    post('push/tmRefresh.php', {}, function() { location.reload(); });
  } else if (action === 'signinout') {
    if (_user) { if (confirm('Sign out?')) { _clearUser(); _updateSignedInState(); } }
    else openAuthDialog();
  }
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function post(url, data, cb) {
  var fd = new FormData();
  fd.append('id', SHEET_ID);
  for (var k in data) fd.append(k, data[k]);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + url);
  xhr.onload = function() {
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = {error: xhr.responseText || 'Bad response'}; }
    cb(r);
  };
  xhr.onerror = function() { cb({error:'Network error'}); };
  xhr.send(fd);
}
function today() {
  var d = new Date(); d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
  return d.toISOString().slice(0,10);
}
function _isOpen(id) { return document.getElementById(id).classList.contains('open'); }

// ── Esc ───────────────────────────────────────────────────────────────────────
document.addEventListener('keydown', function(e) {
  if (e.key !== 'Escape') return;
  if (_isOpen('timeOverlay')) { forceCloseTimeDialog(); return; }
  if (_isOpen('hwOverlay'))   { forceCloseHwDialog();   return; }
  if (_isOpen('authOverlay')) { forceCloseAuthDialog(); return; }
  closeUserMenu();
});

// ── Sign In / Profile ─────────────────────────────────────────────────────────
var _authPendingEmail = '', _authPendingPass = '';

function _showAuthPanel(which) {
  document.getElementById('authForm').style.display     = which === 'form'    ? '' : 'none';
  document.getElementById('authConfirm').style.display  = which === 'confirm' ? '' : 'none';
  document.getElementById('profilePanel').style.display = which === 'profile' ? '' : 'none';
}
function openAuthDialog() {
  document.getElementById('authErr').textContent = '';
  document.getElementById('authConfirmErr').textContent = '';
  document.getElementById('profileErr').textContent = '';
  document.getElementById('profileNote').style.display = 'none';
  document.getElementById('profileSaveBtn').textContent = 'Save';
  document.getElementById('profileSaveBtn').disabled = false;
  document.getElementById('authSubmitBtn').disabled = false;
  document.getElementById('authCreateBtn').disabled = false;
  if (_user) {
    _showAuthPanel('profile');
    document.getElementById('profileName').value = _user.name || '';
    document.getElementById('profileEmailLabel').textContent = 'Signed in as ' + _user.email;
  } else {
    _showAuthPanel('form');
    document.getElementById('authEmail').value = '';
    document.getElementById('authPass').value  = '';
  }
  document.getElementById('authOverlay').classList.add('open');
  setTimeout(function(){ document.getElementById(_user ? 'profileName' : 'authEmail').focus(); }, 60);
}
function forceCloseAuthDialog() { document.getElementById('authOverlay').classList.remove('open'); _needNameFor = ''; }

function submitAuth(confirmNew) {
  var email = confirmNew ? _authPendingEmail : document.getElementById('authEmail').value.trim();
  var pass  = confirmNew ? _authPendingPass  : document.getElementById('authPass').value;
  var err   = document.getElementById(confirmNew ? 'authConfirmErr' : 'authErr');
  err.textContent = '';
  if (!email || !pass) { err.textContent = 'Email and password are required.'; return; }
  var btn = document.getElementById(confirmNew ? 'authCreateBtn' : 'authSubmitBtn');
  btn.disabled = true;
  var data = {email: email, password: pass};
  if (confirmNew) data.confirm_new = '1';
  post('push/collabAuth.php', data, function(r) {
    btn.disabled = false;
    if (r.error) { err.textContent = r.error; return; }
    if (r.prompt_create) {
      // Unknown email — offer to create an account (same flow as DevBoard)
      _authPendingEmail = email; _authPendingPass = pass;
      document.getElementById('authConfirmEmail').textContent = email;
      _showAuthPanel('confirm');
      setTimeout(function(){ document.getElementById('authCreateBtn').focus(); }, 30);
      return;
    }
    if (!r.ok) { err.textContent = 'Sign in failed. Please try again.'; return; }
    _user = { email: r.email || email, name: (r.bio && (r.bio.name || r.bio.Name)) || '' };
    _authPendingEmail = ''; _authPendingPass = '';
    _saveUser();
    _updateSignedInState();
    if (r['new'] || !_user.name) {
      // New account, or no name on file — ask for the name shown on their entries
      _showAuthPanel('profile');
      document.getElementById('profileName').value = _user.name || '';
      document.getElementById('profileEmailLabel').textContent = 'Signed in as ' + _user.email;
      document.getElementById('profileNote').style.display = 'none';
      document.getElementById('profileErr').textContent = '';
      document.getElementById('profileSaveBtn').textContent = 'Save';
      setTimeout(function(){ document.getElementById('profileName').focus(); }, 30);
    } else {
      forceCloseAuthDialog();
    }
  });
}
function _authConfirmBack() {
  _showAuthPanel('form');
  setTimeout(function(){ document.getElementById('authPass').focus(); }, 30);
}
var _needNameFor = '';
function saveName() {
  if (!_user) return;
  var input = document.getElementById('profileName');
  var err   = document.getElementById('profileErr');
  var note  = document.getElementById('profileNote');
  var name  = input.value.trim().replace(/\s+/g, ' ');
  err.textContent = '';
  if (!name) { err.textContent = 'Please enter your name — it\u2019s shown on your time and cost entries.'; return; }
  if (name === (_user.name || '')) { _finishName(); return; }   // unchanged
  var btn = document.getElementById('profileSaveBtn');
  btn.disabled = true;
  // Server keeps names unique: a taken name comes back with a number suffix
  post('push/tmSetName.php', {email: _user.email, name: name}, function(r) {
    btn.disabled = false;
    if (r.error) { err.textContent = r.error; return; }
    _user.name = r.name;
    _saveUser();
    _updateSignedInState();
    if (r.adjusted) {
      input.value = r.name;
      note.innerHTML = '';
      note.appendChild(document.createTextNode('\u201C' + name + '\u201D is already in use, so you\u2019ve been saved as '));
      var b = document.createElement('strong'); b.textContent = r.name; note.appendChild(b);
      note.appendChild(document.createTextNode('. Click Done to continue.'));
      note.style.display = '';
      btn.textContent = 'Done';
      return;
    }
    _finishName();
  });
}
function _finishName() {
  var then = _needNameFor;
  forceCloseAuthDialog();
  if (then === 'hw') openAddHwDialog();
}

// ── Time ──────────────────────────────────────────────────────────────────────
var _editingTime = null;
function openAddTimeDialog() {
  if (!_user) { openAuthDialog(); return; }
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
  if (!_user) { openAuthDialog(); return; }
  _editingTime = t;
  document.getElementById('timeDlgTitle').textContent = 'Edit Time · ' + PROJECT;
  document.getElementById('timeDate').value  = t['Date']        || '';
  document.getElementById('timeHours').value = String(t['Hours'] || '').replace(/^'/, '');
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
  if (_editingTime) data.row_id = _editingTime['Ref Number'] || '';
  post(_editingTime ? 'push/tmUpdateTime.php' : 'push/tmAddTime.php', data, function(r) {
    if (r.error) { err.textContent = r.error; btn.disabled = false; return; }
    forceCloseTimeDialog(); location.reload();
  });
}

// ── Hardware & Materials ──────────────────────────────────────────────────────
var _editingHw = null;
function openAddHwDialog() {
  if (!_user) { openAuthDialog(); return; }
  if (!(_user.name || '').trim()) { _needNameFor = 'hw'; openAuthDialog(); return; }
  _editingHw = null;
  document.getElementById('hwDlgTitle').textContent = 'Add Cost · ' + PROJECT;
  document.getElementById('hwDate').value   = today();
  document.getElementById('hwAmount').value = '';
  document.getElementById('hwStatus').value = '';
  document.getElementById('hwDesc').value   = '';
  document.getElementById('hwBuyUrl').value = '';
  document.getElementById('hwNotes').value  = '';
  document.getElementById('hwErr').textContent = '';
  document.getElementById('hwSubmitBtn').disabled = false;
  document.getElementById('hwSubmitBtn').textContent = 'Add Cost';
  document.getElementById('hwOverlay').classList.add('open');
  setTimeout(function(){ document.getElementById('hwDesc').focus(); }, 60);
}
function openEditHwDialog(h) {
  if (!_user) { openAuthDialog(); return; }
  _editingHw = h;
  document.getElementById('hwDlgTitle').textContent = 'Edit Cost · ' + PROJECT;
  document.getElementById('hwDate').value   = h['Date']        || '';
  document.getElementById('hwAmount').value = String(h['Amount'] || '').replace(/^'/, '');
  document.getElementById('hwStatus').value = h['Status']      || '';
  document.getElementById('hwDesc').value   = h['Description'] || '';
  document.getElementById('hwBuyUrl').value = h['Buy URL']     || '';
  document.getElementById('hwNotes').value  = h['Notes']       || '';
  document.getElementById('hwErr').textContent = '';
  document.getElementById('hwSubmitBtn').disabled = false;
  document.getElementById('hwSubmitBtn').textContent = 'Update';
  document.getElementById('hwOverlay').classList.add('open');
}
function forceCloseHwDialog() { document.getElementById('hwOverlay').classList.remove('open'); _editingHw = null; }
function submitHw() {
  var desc   = document.getElementById('hwDesc').value.trim();
  var amount = document.getElementById('hwAmount').value.trim();
  var err    = document.getElementById('hwErr');
  if (!desc || !amount) { err.textContent = 'Description and amount are required.'; return; }
  var btn = document.getElementById('hwSubmitBtn');
  btn.disabled = true;
  var data = {
    date:        document.getElementById('hwDate').value.trim(),
    project:     PROJECT,
    client:      CLIENT,
    description: desc,
    amount:      amount,
    status:      document.getElementById('hwStatus').value,
    buy_url:     document.getElementById('hwBuyUrl').value.trim(),
    notes:       document.getElementById('hwNotes').value.trim(),
  };
  if (_editingHw) data.row_id   = _editingHw['Ref Number'] || '';
  else            data.added_by = _user.name || '';
  post(_editingHw ? 'push/tmUpdateHardware.php' : 'push/tmAddHardware.php', data, function(r) {
    if (r.error) { err.textContent = r.error; btn.disabled = false; return; }
    forceCloseHwDialog(); location.reload();
  });
}

// ── Delete (own entries only) ─────────────────────────────────────────────────
function deleteEntry(kind, rowId) {
  if (!rowId) return;
  if (!confirm(kind === 'hw' ? 'Delete this cost entry?' : 'Delete this time entry?')) return;
  post(kind === 'hw' ? 'push/tmDeleteHardware.php' : 'push/tmDeleteTime.php', {row_id: rowId}, function(r) {
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
(function() {
  var v = null; try { v = sessionStorage.getItem(VIEW_KEY); } catch(e) {}
  if (v === 'materials') switchView('materials');
})();
</script>
</body>
</html>
