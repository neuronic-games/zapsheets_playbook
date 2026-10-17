<?php
/**
 * T&MBoard owner dashboard.
 * URL: /{sheet_id}/tmboard
 */
error_reporting(0);

$_bpFile = dirname(__DIR__, 3) . '/dotEnv.php';
if (file_exists($_bpFile)) { require_once $_bpFile; }

$_rp      = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_bp      = rtrim($_ENV['BASE_PATH'] ?? '', '/');
$_rp_s    = ($_bp !== '' && str_starts_with($_rp, $_bp)) ? substr($_rp, strlen($_bp)) : $_rp;

preg_match('#^/([A-Za-z0-9_\-]+)/tmboard#', $_rp_s, $_m);
$_sheetId = $_m[1] ?? '';

if (!$_sheetId) { http_response_code(404); exit; }

$_root   = dirname(__DIR__, 3);
$_sDir   = $_root . '/sheets/' . $_sheetId;

function _tm_load(string $file): array {
    return file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
}

$_clients   = _tm_load($_sDir . '/clients.json');
$_projects  = _tm_load($_sDir . '/projects.json');
$_timeRows  = _tm_load($_sDir . '/time.json');
$_hwRows    = _tm_load($_sDir . '/hardware.json');
$_contracts = _tm_load($_sDir . '/contracts.json');
$_staff     = _tm_load($_sDir . '/staff.json');

// Staff names (from the Staff tab) for the Log Time staff picker
$_staffNames = [];
foreach ($_staff as $_st) { $_stn = trim(ltrim((string)($_st['Name'] ?? ''), "'")); if ($_stn !== '') $_staffNames[] = $_stn; }
$_staffNames = array_values(array_unique($_staffNames));
natcasesort($_staffNames); $_staffNames = array_values($_staffNames);

// Index time/hardware/contracts by project for fast lookup
$_timeByProject     = [];
foreach ($_timeRows  as $r) { $_timeByProject[$r['Project'] ?? ''][] = $r; }
$_hwByProject       = [];
foreach ($_hwRows    as $r) { $_hwByProject[$r['Project']   ?? ''][] = $r; }
$_contractsByProject = [];
foreach ($_contracts as $r) { $_contractsByProject[$r['Game'] ?? ''][] = $r; }

// Unique client names from projects (plus any in clients tab)
$_clientNames = array_unique(array_merge(
    array_column($_clients,  'Client'),
    array_column($_projects, 'Client')
));
sort($_clientNames);

// Projects indexed by client
$_projectsByClient = [];
foreach ($_projects as $p) { $_projectsByClient[$p['Client'] ?? ''][] = $p; }

$_basePath = $_bp;
$_appRoot  = $_bp . '/';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>T&amp;MBoard</title>
<style>
@font-face { font-family:'DINBlack';   src:url('<?= $_appRoot ?>fonts/DINBlack.woff2')  format('woff2'); }
@font-face { font-family:'DINRegular'; src:url('<?= $_appRoot ?>fonts/DINMedium.woff2') format('woff2'); }

*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }

:root {
  --accent:     #3b3baa;
  --accent-dk:  #2d2d88;
  --accent-lt:  #e8e8f8;
  --text:       #1a1a2e;
  --muted:      #666;
  --border:     #ddd;
  --surface:    #fff;
  --bg:         #f0f4f8;
  --danger:     #c0392b;
}

body { background:var(--bg); font-family:'DINRegular',Arial,sans-serif; color:var(--text); min-height:100vh; }

/* ── Header ── */
.tm-header {
  background:#1a1a2e; color:#fff;
  display:flex; align-items:center; justify-content:space-between;
  padding:.6rem 1.5rem; position:sticky; top:0; z-index:100;
  box-shadow:0 2px 12px rgba(0,0,0,.35);
}
.tm-header-left { display:flex; flex-direction:column; gap:.05rem; }
.tm-logo { font-family:'DINBlack',sans-serif; font-size:1.2rem; letter-spacing:.07em; color:#7c9eff; }
.tm-logo-sub { font-size:.7rem; opacity:.65; letter-spacing:.02em; font-family:'DINRegular',sans-serif; }
.tm-header-right { display:flex; align-items:center; gap:.5rem; }
/* Nav tabs */
.tm-tabs { display:flex; gap:.2rem; }
.tm-tab {
  font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase;
  letter-spacing:.07em; padding:.4rem .9rem; border-radius:6px; cursor:pointer;
  border:none; color:rgba(255,255,255,.7); background:rgba(255,255,255,.1);
  transition:all .15s; white-space:nowrap;
}
.tm-tab:hover { background:rgba(255,255,255,.2); color:#fff; }
.tm-tab.active { background:#fff; color:#1a1a2e; }
/* User icon */
.btn-user-icon {
  width:2rem; height:2rem; border-radius:50%;
  border:1.5px solid rgba(255,255,255,.45); background:transparent;
  color:rgba(255,255,255,.8); cursor:pointer; display:inline-flex;
  align-items:center; justify-content:center; flex-shrink:0;
  transition:background .15s, border-color .15s;
}
.btn-user-icon:hover { background:rgba(255,255,255,.18); border-color:#fff; }
.btn-user-icon.signed-in { background:rgba(255,255,255,.2); border-color:#fff; color:#fff; }
/* Views */
.tm-view { display:none; }
.tm-view.active { display:block; }
/* Board toolbar */
.board-toolbar { display:flex; justify-content:flex-end; gap:.5rem; margin-bottom:1.25rem; }
.btn-toolbar {
  font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase;
  letter-spacing:.06em; padding:.38rem .85rem; border-radius:6px; cursor:pointer;
  border:1.5px solid var(--border); background:#fff; color:var(--text);
  transition:background .12s;
}
.btn-toolbar:hover { background:var(--accent-lt); border-color:var(--accent); color:var(--accent); }
.btn-toolbar.primary { background:var(--accent); color:#fff; border-color:var(--accent); }
.btn-toolbar.primary:hover { background:var(--accent-dk); }
/* Projects view */
.projects-toolbar { display:flex; align-items:center; gap:.6rem; margin-bottom:1.2rem; }
.projects-search {
  flex:1; padding:.5rem .9rem;
  font-family:'DINRegular',Arial,sans-serif; font-size:.88rem; color:var(--text);
  border:1px solid var(--border); border-radius:8px; outline:none;
  background:#fff; transition:border-color .15s;
}
.projects-search:focus { border-color:var(--accent); }
/* Flat project list */
.proj-list-item {
  background:var(--surface); border-radius:8px;
  box-shadow:0 1px 6px rgba(0,0,0,.07);
  padding:.65rem 1rem; margin-bottom:.5rem;
  display:flex; align-items:center; gap:1rem; cursor:pointer;
  transition:box-shadow .15s, background .12s;
}
.proj-list-item:hover { box-shadow:0 2px 12px rgba(59,59,170,.13); background:#fafafe; }
.proj-list-item[hidden] { display:none; }
.proj-list-name { font-family:'DINBlack',sans-serif; font-size:.9rem; color:var(--accent); flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.proj-list-client { font-size:.8rem; color:var(--muted); min-width:120px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.proj-list-stats { display:flex; gap:1.2rem; font-size:.78rem; color:var(--muted); flex-shrink:0; }
.proj-list-stats strong { font-family:'DINBlack',sans-serif; color:var(--text); }
.proj-list-status {
  font-size:.68rem; font-family:'DINBlack',sans-serif; letter-spacing:.05em;
  text-transform:uppercase; padding:.18rem .5rem; border-radius:999px;
  background:var(--accent); color:#fff; flex-shrink:0;
}
.proj-list-status.on-hold  { background:#e67e22; }
.proj-list-status.completed{ background:#27ae60; }
.proj-list-status.cancelled{ background:#999; }
.proj-list-empty { text-align:center; padding:3rem 1rem; color:var(--muted); font-size:.9rem; }

/* ── Layout ── */
.tm-main { max-width:1100px; margin:0 auto; padding:1.5rem 1rem 3rem; }

/* ── Client section ── */
.client-section { margin-bottom:2rem; }
.client-header {
  display:flex; align-items:center; gap:.75rem;
  padding:.6rem 0 .55rem; border-bottom:2px solid var(--accent);
  margin-bottom:.9rem;
}
.client-name {
  font-family:'DINBlack',sans-serif; font-size:1rem;
  text-transform:uppercase; letter-spacing:.06em; color:var(--accent);
}

/* ── Project card ── */
.project-card {
  background:var(--surface); border-radius:10px;
  box-shadow:0 1px 8px rgba(0,0,0,.08); margin-bottom:1rem;
  overflow:hidden;
}
.project-bar {
  display:flex; align-items:center; gap:.75rem;
  padding:.75rem 1rem; background:var(--accent-lt);
  border-bottom:1px solid #d0d0ee; cursor:pointer;
}
.project-title { font-family:'DINBlack',sans-serif; font-size:.92rem; color:var(--accent); flex:1; }
.project-status {
  font-size:.72rem; font-family:'DINBlack',sans-serif; letter-spacing:.05em;
  text-transform:uppercase; padding:.2rem .55rem; border-radius:999px;
  background:var(--accent); color:#fff;
}
.project-status.on-hold  { background:#e67e22; }
.project-status.completed{ background:#27ae60; }
.project-status.cancelled{ background:#999; }
.project-meta { font-size:.78rem; color:var(--muted); display:flex; gap:1rem; }
.project-actions { display:flex; gap:.4rem; }
.btn-sm {
  font-family:'DINBlack',sans-serif; font-size:.68rem; text-transform:uppercase;
  letter-spacing:.05em; padding:.28rem .6rem; border-radius:5px; cursor:pointer;
  border:1px solid var(--border); background:#fff; color:var(--text); transition:background .12s;
}
.btn-sm:hover { background:var(--accent-lt); border-color:var(--accent); color:var(--accent); }
.btn-sm.danger { color:var(--danger); border-color:var(--danger); }
.btn-sm.danger:hover { background:#fff0ef; }
.btn-sm.primary { background:var(--accent); color:#fff; border-color:var(--accent); }
.btn-sm.primary:hover { background:var(--accent-dk); }
.btn-reload {
  width:1.85rem; height:1.85rem; border-radius:50%; border:1.5px solid var(--border);
  background:var(--surface); color:var(--muted); cursor:pointer; display:inline-flex;
  align-items:center; justify-content:center; flex-shrink:0; transition:color .15s, border-color .15s, transform .35s;
}
.btn-reload:hover { color:var(--accent); border-color:var(--accent); }
.btn-reload.spinning svg { animation:spin .75s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }

/* ── Project body ── */
.project-body { padding:.85rem 1rem 1rem; display:none; }
.project-body.open { display:block; }

.section-head {
  display:flex; align-items:center; justify-content:space-between;
  margin-bottom:.5rem; margin-top:.8rem;
}
.section-head:first-child { margin-top:0; }
.section-label {
  font-family:'DINBlack',sans-serif; font-size:.73rem;
  text-transform:uppercase; letter-spacing:.07em; color:var(--muted);
}
.section-total {
  font-family:'DINBlack',sans-serif; font-size:.78rem; color:var(--accent);
}

/* ── Tables ── */
.tm-table { width:100%; border-collapse:collapse; font-size:.82rem; margin-bottom:.25rem; }
.tm-table th { font-family:'DINBlack',sans-serif; font-size:.7rem; text-transform:uppercase;
  letter-spacing:.05em; color:var(--muted); padding:.3rem .5rem;
  border-bottom:1px solid var(--border); text-align:left; }
.tm-table td { padding:.42rem .5rem; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
.tm-table tr:last-child td { border-bottom:none; }
.tm-table tr:hover td { background:#fafafe; }
.tm-table .num { text-align:right; font-variant-numeric:tabular-nums; }
.tm-table .actions { text-align:right; white-space:nowrap; }
.empty-msg { font-size:.82rem; color:#bbb; padding:.5rem 0; }

/* ── Summary bar ── */
.summary-bar {
  display:flex; gap:1.5rem; padding:.6rem .5rem .2rem;
  border-top:1px solid var(--border); margin-top:.5rem; flex-wrap:wrap;
}
.summary-stat { font-size:.8rem; }
.summary-stat span { font-family:'DINBlack',sans-serif; color:var(--accent); }

/* ── Share chip ── */
.share-chip {
  display:inline-flex; align-items:center; gap:.3rem;
  font-size:.74rem; background:var(--accent-lt); color:var(--accent);
  border:1px solid #c0c0ee; border-radius:999px; padding:.2rem .55rem;
  cursor:pointer; transition:background .12s;
}
.share-chip:hover { background:#d8d8f5; }

/* ── No-client section ── */
.no-projects { color:var(--muted); font-size:.88rem; padding:.5rem 0; }

/* ── Overlays ── */
.tm-overlay {
  display:none; position:fixed; inset:0; z-index:200;
  background:rgba(0,0,0,.45); align-items:center; justify-content:center;
}
.tm-overlay.open { display:flex; }
.tm-dialog {
  background:#fff; border-radius:12px; width:min(480px,95vw);
  max-height:92vh; overflow-y:auto; padding:1.4rem 1.5rem;
  box-shadow:0 8px 40px rgba(0,0,0,.22);
}
.tm-dialog-title {
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
select.field-input {
  -webkit-appearance:none !important; appearance:none !important;
  background-image:url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMiIgaGVpZ2h0PSI4IiB2aWV3Qm94PSIwIDAgMTIgOCI+PHBhdGggZD0iTTEgMWw1IDUgNS01IiBzdHJva2U9IiM2NjYiIHN0cm9rZS13aWR0aD0iMS41IiBmaWxsPSJub25lIiBzdHJva2UtbGluZWNhcD0icm91bmQiIHN0cm9rZS1saW5lam9pbj0icm91bmQiLz48L3N2Zz4=") !important;
  background-repeat:no-repeat !important;
  background-position:right .7rem center !important;
  background-size:10px 7px !important;
  padding-right:2rem !important;
}
textarea.field-input { resize:vertical; min-height:4rem; }
.field-row { display:grid; grid-template-columns:1fr 1fr; gap:.6rem; }
.date-wrap { position:relative; }
.date-wrap input[type="date"] { -webkit-appearance:none; appearance:none; width:100%; box-sizing:border-box; background:#fff; padding-right:2.2rem; cursor:pointer; }
.date-wrap input[type="date"]::-webkit-calendar-picker-indicator { position:absolute; right:0; top:0; bottom:0; width:2.2rem; opacity:0; cursor:pointer; }
.date-wrap::after { content:''; position:absolute; right:.65rem; top:50%; transform:translateY(-50%); width:15px; height:15px; pointer-events:none; background-image:url("data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxNSIgaGVpZ2h0PSIxNSIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiM4ODgiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48cmVjdCB4PSIzIiB5PSI0IiB3aWR0aD0iMTgiIGhlaWdodD0iMTgiIHJ4PSIyIi8+PGxpbmUgeDE9IjE2IiB5MT0iMiIgeDI9IjE2IiB5Mj0iNiIvPjxsaW5lIHgxPSI4IiB5MT0iMiIgeDI9IjgiIHkyPSI2Ii8+PGxpbmUgeDE9IjMiIHkxPSIxMCIgeDI9IjIxIiB5Mj0iMTAiLz48L3N2Zz4="); background-repeat:no-repeat; background-size:contain; }
.dialog-actions { display:flex; justify-content:flex-end; gap:.6rem; margin-top:1.1rem; }
.btn-cancel { font-family:'DINBlack',sans-serif; font-size:.78rem; text-transform:uppercase;
  letter-spacing:.06em; padding:.45rem 1rem; border-radius:6px; cursor:pointer;
  border:1px solid var(--border); background:#fff; color:var(--muted); }
.btn-cancel:hover { background:#f5f5f5; }
.btn-primary { font-family:'DINBlack',sans-serif; font-size:.78rem; text-transform:uppercase;
  letter-spacing:.06em; padding:.45rem 1.1rem; border-radius:6px; cursor:pointer;
  border:none; background:var(--accent); color:#fff; }
.btn-primary:hover { background:var(--accent-dk); }
.btn-primary:disabled { opacity:.5; cursor:default; }
.tm-err { color:var(--danger); font-size:.78rem; margin-top:.5rem; }

/* ── Share dialog ── */
.share-url-box {
  background:#f5f5ff; border:1px solid #c0c0ee; border-radius:6px;
  padding:.6rem .8rem; font-family:'Courier New',monospace; font-size:.8rem;
  word-break:break-all; color:var(--accent); margin:.6rem 0;
}
.btn-copy-share {
  font-family:'DINBlack',sans-serif; font-size:.75rem; text-transform:uppercase;
  letter-spacing:.06em; padding:.35rem .8rem; border-radius:5px; cursor:pointer;
  border:1px solid var(--accent); color:var(--accent); background:#fff;
  transition:background .12s;
}
.btn-copy-share:hover { background:var(--accent-lt); }
.btn-copy-share.copied { background:var(--accent); color:#fff; }

/* ── User dropdown menu ── */
.user-menu-wrap { position:relative; }
.user-menu {
  position:absolute; right:0; top:calc(100% + .4rem);
  background:#1a1a2e; border-radius:10px; min-width:190px;
  box-shadow:0 8px 32px rgba(0,0,0,.5); padding:.35rem 0;
  z-index:400; display:none;
}
.user-menu.open { display:block; }
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

/* ── Auth-gated elements — hidden until JS sets display:'' ── */
body:not(.tm-signed-in) .auth-only { display:none !important; }

@media (max-width:600px) {
  .project-bar { flex-wrap:wrap; }
  .project-meta { display:none; }
  .field-row { grid-template-columns:1fr; }
  .tm-header { padding:.65rem 1rem; }
}
</style>
</head>
<body>

<div class="tm-header">
  <div class="tm-header-left">
    <div class="tm-logo">T&amp;MBoard</div>
    <div class="tm-logo-sub" id="headerMeta">Time &amp; Materials Tracker</div>
  </div>
  <div class="tm-header-right">
    <nav class="tm-tabs">
      <button class="tm-tab active" id="tabBoard" onclick="switchView('board')">Board</button>
      <button class="tm-tab" id="tabProjects" onclick="switchView('projects')"><?= count($_projects) ?> Projects</button>
    </nav>
    <div class="user-menu-wrap">
      <button class="btn-user-icon" id="signInBtn" onclick="toggleUserMenu(event)" title="Menu">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
        </svg>
      </button>
      <div class="user-menu" id="userMenu">
        <button class="user-menu-item" id="menuProfile" onclick="userMenuAction('profile')">Profile</button>
        <button class="user-menu-item" onclick="userMenuAction('settings')">Settings</button>
        <hr class="user-menu-divider" />
        <button class="user-menu-item" onclick="userMenuAction('fetch')">Fetch</button>
        <hr class="user-menu-divider" />
        <button class="user-menu-item signin" id="menuSignInOut" onclick="userMenuAction('signout')">Sign In</button>
      </div>
    </div>
  </div>
</div>

<div class="tm-main">

<!-- ══ BOARD VIEW ══ -->
<div class="tm-view active" id="viewBoard">
<div class="board-toolbar auth-only" id="boardToolbar">
  <button class="btn-toolbar" onclick="openAddClientDialog()">+ Client</button>
  <button class="btn-toolbar primary" onclick="openAddProjectDialog()">+ Project</button>
</div>

<?php if (empty($_projects)): ?>
  <div style="text-align:center;padding:4rem 1rem;color:var(--muted);">
    <div style="font-family:'DINBlack',sans-serif;font-size:1.1rem;margin-bottom:.6rem;">No projects yet</div>
    <div style="font-size:.88rem;">Add a client and project to get started.</div>
  </div>
<?php else: ?>

<?php foreach ($_clientNames as $_cn): ?>
<?php $_cProjects = $_projectsByClient[$_cn] ?? []; if (empty($_cProjects)) continue; ?>
<div class="client-section">
  <div class="client-header">
    <div class="client-name"><?= htmlspecialchars($_cn) ?></div>
  </div>

  <?php foreach ($_cProjects as $_p):
    $pName    = $_p['Project']        ?? '';
    $pRowId   = $_p['Ref Number']      ?? '';
    $pStatus  = $_p['Status']         ?? 'Active';
    $pEst     = (float)($_p['Estimate Hours'] ?? 0);
    $pStart   = $_p['Start Date']     ?? '';
    $pEnd     = $_p['End Date']       ?? '';
    $pNotes   = $_p['Notes']          ?? '';
    $tRows      = $_timeByProject[$pName]      ?? [];
    $hwRows     = $_hwByProject[$pName]        ?? [];
    $conRows    = $_contractsByProject[$pName] ?? [];
    $totalHrs   = array_sum(array_column($tRows, 'Hours'));
    $totalHw    = array_sum(array_column($hwRows, 'Amount'));
    $statusClass = strtolower(str_replace(' ','-',$pStatus));
    $cardId   = 'proj_' . md5($pRowId ?: $pName);
  ?>
  <div class="project-card">
    <div class="project-bar" onclick="toggleProject('<?= htmlspecialchars($cardId) ?>')">
      <div class="project-title"><?= htmlspecialchars($pName) ?></div>
      <div class="project-meta">
        <?php if ($pStart): ?><span><?= htmlspecialchars($pStart) ?><?= $pEnd ? ' → ' . htmlspecialchars($pEnd) : '' ?></span><?php endif; ?>
        <?php if ($pEst > 0): ?><span><?= $totalHrs ?>/<?= $pEst ?>h</span><?php endif; ?>
        <?php if ($totalHw > 0): ?><span>$<?= number_format($totalHw,2) ?> hw</span><?php endif; ?>
      </div>
      <div class="project-status <?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars($pStatus) ?></div>
      <div class="project-actions" onclick="event.stopPropagation()">
        <button class="btn-reload" title="Reload" onclick="reloadCard(this)">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
            <path d="M23 4v6h-6"/><path d="M1 20v-6h6"/>
            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
          </svg>
        </button>
        <button class="btn-sm auth-only" onclick="openShareDialog(<?= htmlspecialchars(json_encode($pName), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($_cn), ENT_QUOTES) ?>)">Share</button>
        <button class="btn-sm auth-only" onclick="openContractDialog(<?= htmlspecialchars(json_encode($pName), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($_cn), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode((float)$totalHrs), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode((float)$totalHw), ENT_QUOTES) ?>)">Contract</button>
        <button class="btn-sm auth-only" onclick="openEditProjectDialog(<?= htmlspecialchars(json_encode($_p), ENT_QUOTES) ?>)">Edit</button>
        <button class="btn-sm danger auth-only" onclick="deleteProject(<?= htmlspecialchars(json_encode($pRowId), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($pName), ENT_QUOTES) ?>)">Delete</button>
      </div>
    </div>

    <div class="project-body" id="<?= htmlspecialchars($cardId) ?>">

      <!-- TIME ENTRIES -->
      <div class="section-head">
        <div class="section-label">Time Entries</div>
        <div style="display:flex;align-items:center;gap:.6rem;">
          <?php if ($pEst > 0): ?><div class="section-total"><?= $totalHrs ?>h / <?= $pEst ?>h estimated</div><?php endif; ?>
          <button class="btn-sm primary auth-only" onclick="openAddTimeDialog(<?= htmlspecialchars(json_encode($pName), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($_cn), ENT_QUOTES) ?>)">+ Log Time</button>
        </div>
      </div>
      <?php if ($tRows): ?>
      <table class="tm-table">
        <thead><tr>
          <th>Date</th><th>Staff</th><th class="num">Hours</th><th>Description</th><th class="actions"></th>
        </tr></thead>
        <tbody>
        <?php foreach ($tRows as $t): ?>
        <tr>
          <td><?= htmlspecialchars($t['Date'] ?? '') ?></td>
          <td><?= htmlspecialchars($t['Staff Name'] ?? '') ?></td>
          <td class="num"><?= htmlspecialchars($t['Hours'] ?? '') ?></td>
          <td><?= htmlspecialchars($t['Description'] ?? '') ?></td>
          <td class="actions">
            <button class="btn-sm auth-only" onclick="openEditTimeDialog(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)">Edit</button>
            <button class="btn-sm danger auth-only" onclick="deleteTime(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)">Del</button>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?><div class="empty-msg">No time entries yet.</div><?php endif; ?>

      <!-- HARDWARE COSTS -->
      <div class="section-head" style="margin-top:1rem;">
        <div class="section-label">Hardware &amp; Materials</div>
        <div style="display:flex;align-items:center;gap:.6rem;">
          <?php if ($totalHw > 0): ?><div class="section-total">$<?= number_format($totalHw,2) ?> total</div><?php endif; ?>
          <button class="btn-sm primary auth-only" onclick="openAddHwDialog(<?= htmlspecialchars(json_encode($pName), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($_cn), ENT_QUOTES) ?>)">+ Add Cost</button>
        </div>
      </div>
      <?php if ($hwRows): ?>
      <table class="tm-table">
        <thead><tr>
          <th>Date</th><th>Description</th><th class="num">Amount</th><th>Status</th><th>Notes</th><th class="actions"></th>
        </tr></thead>
        <tbody>
        <?php foreach ($hwRows as $h): ?>
        <tr>
          <td><?= htmlspecialchars($h['Date'] ?? '') ?></td>
          <td><?= htmlspecialchars($h['Description'] ?? '') ?></td>
          <td class="num">$<?= number_format((float)($h['Amount'] ?? 0), 2) ?></td>
          <td><?= htmlspecialchars($h['Status'] ?? '') ?></td>
          <td><?= htmlspecialchars($h['Notes'] ?? '') ?></td>
          <td class="actions">
            <button class="btn-sm auth-only" onclick="openEditHwDialog(<?= htmlspecialchars(json_encode($h), ENT_QUOTES) ?>)">Edit</button>
            <button class="btn-sm danger auth-only" onclick="deleteHw(<?= htmlspecialchars(json_encode($h), ENT_QUOTES) ?>)">Del</button>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?><div class="empty-msg">No costs recorded yet.</div><?php endif; ?>

      <!-- CONTRACTS -->
      <?php if ($conRows): ?>
      <div class="section-head" style="margin-top:1rem;">
        <div class="section-label">Contracts</div>
      </div>
      <table class="tm-table">
        <thead><tr>
          <th>Ref #</th><th>Type</th><th>Date</th><th class="num">Total</th><th>Payment</th><th class="actions"></th>
        </tr></thead>
        <tbody>
        <?php foreach ($conRows as $con): ?>
        <tr>
          <td style="font-family:'DINBlack',sans-serif;font-size:.75rem;letter-spacing:.04em"><?= htmlspecialchars($con['Ref Number'] ?? '') ?></td>
          <td><?= htmlspecialchars($con['Type'] ?? '') ?></td>
          <td><?= htmlspecialchars($con['Date'] ?? '') ?></td>
          <td class="num"><?= ($con['Quote'] ?? '') !== '' ? '$' . number_format((float)$con['Quote'], 2) : '—' ?></td>
          <td><?= htmlspecialchars($con['Payment'] ?? '') ?></td>
          <td class="actions">
            <?php if (!empty($con['Files'])): ?>
            <a class="btn-sm" href="<?= htmlspecialchars($con['Files']) ?>" target="_blank">PDF ↗</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>

    </div><!-- /project-body -->
  </div><!-- /project-card -->
  <?php endforeach; ?>
</div><!-- /client-section -->
<?php endforeach; ?>

<?php endif; ?>
</div><!-- /viewBoard -->

<!-- ══ PROJECTS VIEW ══ -->
<div class="tm-view" id="viewProjects">
  <div class="projects-toolbar">
    <input type="text" id="projectSearch" class="projects-search" placeholder="Search projects, clients…" oninput="filterProjects(this.value)" />
    <button class="btn-toolbar auth-only" id="projViewAddClient" onclick="openAddClientDialog()">+ Client</button>
    <button class="btn-toolbar primary auth-only" id="projViewAddProject" onclick="openAddProjectDialog()">+ Project</button>
  </div>
  <div id="projectsList">
  <?php if (empty($_projects)): ?>
    <div class="proj-list-empty">No projects yet.</div>
  <?php else: ?>
  <?php foreach ($_projects as $_p):
    $pName2   = $_p['Project']        ?? '';
    $pCli2    = $_p['Client']         ?? '';
    $pSt2     = $_p['Status']         ?? 'Active';
    $pEst2    = (float)($_p['Estimate Hours'] ?? 0);
    $tRows2   = $_timeByProject[$pName2] ?? [];
    $hwRows2  = $_hwByProject[$pName2]  ?? [];
    $conRows2 = $_contractsByProject[$pName2] ?? [];
    $hrs2     = array_sum(array_column($tRows2, 'Hours'));
    $hw2      = array_sum(array_column($hwRows2, 'Amount'));
    $stCls2   = strtolower(str_replace(' ','-',$pSt2));
    $cardId2  = 'proj_' . md5(($_p['Ref Number'] ?? '') ?: $pName2);
    $search2  = strtolower($pName2 . ' ' . $pCli2 . ' ' . $pSt2);
  ?>
  <div class="proj-list-item" data-search="<?= htmlspecialchars($search2) ?>"
       onclick="switchView('board');setTimeout(function(){var el=document.getElementById(<?= htmlspecialchars(json_encode($cardId2), ENT_QUOTES) ?>);if(el){el.classList.add('open');el.scrollIntoView({behavior:'smooth',block:'center'});}},80);">
    <div class="proj-list-name"><?= htmlspecialchars($pName2) ?></div>
    <div class="proj-list-client"><?= htmlspecialchars($pCli2) ?></div>
    <div class="proj-list-stats">
      <?php if ($hrs2 > 0 || $pEst2 > 0): ?>
      <span><strong><?= $hrs2 ?><?= $pEst2 > 0 ? '/'.$pEst2 : '' ?>h</strong> time</span>
      <?php endif; ?>
      <?php if ($hw2 > 0): ?>
      <span><strong>$<?= number_format($hw2,0) ?></strong> hw</span>
      <?php endif; ?>
      <?php if (count($conRows2) > 0): ?>
      <span><strong><?= count($conRows2) ?></strong> contract<?= count($conRows2) > 1 ? 's' : '' ?></span>
      <?php endif; ?>
    </div>
    <div class="proj-list-status <?= htmlspecialchars($stCls2) ?>"><?= htmlspecialchars($pSt2) ?></div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
  </div><!-- /projectsList -->
</div><!-- /viewProjects -->

</div><!-- /tm-main -->

<!-- ── Add Client Dialog ── -->
<div class="tm-overlay" id="addClientOverlay" onclick="if(event.target===this)forceCloseAddClientDialog()">
  <div class="tm-dialog" onclick="event.stopPropagation()"
       onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitAddClient();}">
    <div class="tm-dialog-title">Add Client</div>
    <div class="field-group"><label>Client Name *</label><input class="field-input" id="acName" type="text" placeholder="ACME Corp" /></div>
    <div class="field-group"><label>Contact Email</label><input class="field-input" id="acEmail" type="email" placeholder="contact@example.com" /></div>
    <div class="field-row">
      <div class="field-group"><label>Phone</label><input class="field-input" id="acPhone" type="text" placeholder="+1 555…" /></div>
    </div>
    <div class="field-group"><label>Notes</label><textarea class="field-input" id="acNotes" placeholder="Optional notes…"></textarea></div>
    <div class="tm-err" id="acErr"></div>
    <div class="dialog-actions">
      <button class="btn-cancel" onclick="forceCloseAddClientDialog()">Cancel</button>
      <button class="btn-primary" id="acSubmitBtn" onclick="submitAddClient()">Add Client</button>
    </div>
  </div>
</div>

<!-- ── Add / Edit Project Dialog ── -->
<div class="tm-overlay" id="projectOverlay" onclick="if(event.target===this)forceCloseProjectDialog()">
  <div class="tm-dialog" onclick="event.stopPropagation()"
       onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitProject();}">
    <div class="tm-dialog-title" id="projectDlgTitle">Add Project</div>
    <div class="field-group"><label>Client</label>
      <select class="field-input" id="projClient">
        <option value="">— select client —</option>
        <?php foreach ($_clientNames as $_cn): ?>
        <option><?= htmlspecialchars($_cn) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field-group"><label>Project Name *</label><input class="field-input" id="projName" type="text" placeholder="Website Redesign" /></div>
    <div class="field-row">
      <div class="field-group"><label>Status</label>
        <select class="field-input" id="projStatus">
          <option>Active</option><option>On Hold</option><option>Completed</option><option>Cancelled</option>
        </select>
      </div>
      <div class="field-group"><label>Estimate (hours)</label><input class="field-input" id="projEst" type="number" min="0" step="0.5" placeholder="0" /></div>
    </div>
    <div class="field-row">
      <div class="field-group"><label>Start Date</label><div class="date-wrap"><input class="field-input" id="projStart" type="date" /></div></div>
      <div class="field-group"><label>End Date</label><div class="date-wrap"><input class="field-input" id="projEnd" type="date" /></div></div>
    </div>
    <div class="field-group"><label>Notes</label><textarea class="field-input" id="projNotes" placeholder="Optional notes…"></textarea></div>
    <div class="tm-err" id="projErr"></div>
    <div class="dialog-actions">
      <button class="btn-cancel" onclick="forceCloseProjectDialog()">Cancel</button>
      <button class="btn-primary" id="projSubmitBtn" onclick="submitProject()">Save</button>
    </div>
  </div>
</div>

<!-- ── Add / Edit Time Entry Dialog ── -->
<div class="tm-overlay" id="timeOverlay" onclick="if(event.target===this)forceCloseTimeDialog()">
  <div class="tm-dialog" onclick="event.stopPropagation()"
       onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitTime();}">
    <div class="tm-dialog-title" id="timeDlgTitle">Log Time</div>
    <div class="field-row">
      <div class="field-group"><label>Date *</label><div class="date-wrap"><input class="field-input" id="timeDate" type="date" /></div></div>
      <div class="field-group"><label>Hours *</label><input class="field-input" id="timeHours" type="number" min="0" step="0.25" placeholder="1.5" /></div>
    </div>
    <div class="field-group"><label>Staff</label><select class="field-input" id="timeStaffName"></select></div>
    <div class="field-group"><label>Description</label><textarea class="field-input" id="timeDesc" placeholder="What was done…"></textarea></div>
    <div class="tm-err" id="timeErr"></div>
    <div class="dialog-actions">
      <button class="btn-cancel" onclick="forceCloseTimeDialog()">Cancel</button>
      <button class="btn-primary" id="timeSubmitBtn" onclick="submitTime()">Save</button>
    </div>
  </div>
</div>

<!-- ── Add / Edit Hardware Dialog ── -->
<div class="tm-overlay" id="hwOverlay" onclick="if(event.target===this)forceCloseHwDialog()">
  <div class="tm-dialog" onclick="event.stopPropagation()"
       onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitHw();}">
    <div class="tm-dialog-title" id="hwDlgTitle">Add Cost</div>
    <div class="field-row">
      <div class="field-group"><label>Date</label><div class="date-wrap"><input class="field-input" id="hwDate" type="date" /></div></div>
      <div class="field-group"><label>Amount ($) *</label><input class="field-input" id="hwAmount" type="number" min="0" step="0.01" placeholder="0.00" /></div>
    </div>
    <div class="field-row">
      <div class="field-group" style="flex:2"><label>Description *</label><input class="field-input" id="hwDesc" type="text" placeholder="AWS EC2 instance, etc." /></div>
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
    <div class="tm-err" id="hwErr"></div>
    <div class="dialog-actions">
      <button class="btn-cancel" onclick="forceCloseHwDialog()">Cancel</button>
      <button class="btn-primary" id="hwSubmitBtn" onclick="submitHw()">Save</button>
    </div>
  </div>
</div>

<!-- ── Contract Dialog ── -->
<div class="tm-overlay" id="contractOverlay" onclick="if(event.target===this)forceCloseContractDialog()">
  <div class="tm-dialog" onclick="event.stopPropagation()"
       onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitContract();}">
    <div class="tm-dialog-title" id="conDlgTitle">New Contract</div>
    <div class="field-row">
      <div class="field-group">
        <label>Type</label>
        <select class="field-input" id="conType">
          <option value="Estimate">Estimate</option>
          <option value="Invoice">Invoice</option>
        </select>
      </div>
      <div class="field-group"><label>Due Date</label><div class="date-wrap"><input class="field-input" id="conDueDate" type="date" /></div></div>
    </div>
    <div style="background:var(--bg);border-radius:8px;padding:.65rem .85rem;margin-bottom:.5rem;font-size:.8rem;color:var(--muted);">
      <strong id="conProjDisplay" style="color:var(--text)"></strong> · <span id="conClientDisplay"></span>
    </div>
    <div class="field-row">
      <div class="field-group"><label>Hours Logged</label><input class="field-input" id="conHours" type="number" min="0" step="0.25" placeholder="0" /></div>
      <div class="field-group"><label>Hourly Rate ($)</label><input class="field-input" id="conRate" type="number" min="0" step="1" placeholder="0" oninput="recalcContract()" /></div>
      <div class="field-group"><label>Time Cost ($)</label><input class="field-input" id="conTimeCost" type="number" min="0" step="0.01" placeholder="0.00" oninput="conTimeCostEdited=true" /></div>
    </div>
    <div class="field-row">
      <div class="field-group"><label>Material Cost ($) <span style="font-size:.7rem;font-weight:normal;opacity:.6">from hardware</span></label><input class="field-input" id="conMatCost" type="number" min="0" step="0.01" placeholder="0.00" oninput="recalcTotal()" /></div>
      <div class="field-group"><label>Total ($)</label><input class="field-input" id="conTotal" type="number" min="0" step="0.01" placeholder="0.00" readonly style="background:var(--bg);font-family:'DINBlack',sans-serif;" /></div>
    </div>
    <div class="field-group"><label>Notes</label><textarea class="field-input" id="conNotes" placeholder="Scope, terms, etc.…"></textarea></div>
    <div class="tm-err" id="conErr"></div>
    <div id="conLog" style="display:none;margin-bottom:.5rem;font-size:.78rem;color:var(--muted);"></div>
    <div class="dialog-actions">
      <button class="btn-cancel" onclick="forceCloseContractDialog()">Cancel</button>
      <button class="btn-primary" id="conSubmitBtn" onclick="submitContract()">Create &amp; Open PDF</button>
    </div>
  </div>
</div>

<!-- ── Share Dialog ── -->
<div class="tm-overlay" id="shareOverlay" onclick="if(event.target===this)forceCloseShareDialog()">
  <div class="tm-dialog" onclick="event.stopPropagation()">
    <div class="tm-dialog-title">Staff Link</div>
    <p style="font-size:.84rem;color:var(--muted);margin-bottom:.5rem;">Share this link with staff so they can log time for <strong id="shareProjName"></strong>.</p>
    <div class="share-url-box" id="shareUrlBox"></div>
    <div style="display:flex;gap:.6rem;margin-bottom:1rem;">
      <button class="btn-copy-share" id="shareCopyBtn" onclick="copyShareUrl()">Copy Link</button>
      <a id="shareOpenBtn" href="#" target="_blank" style="font-size:.78rem;align-self:center;color:var(--accent);">Open ↗</a>
    </div>
    <div class="tm-err" id="shareErr" style="margin-bottom:.5rem;"></div>
    <div class="dialog-actions">
      <button class="btn-primary" onclick="forceCloseShareDialog()">Done</button>
    </div>
  </div>
</div>

<!-- ── Settings Dialog ── -->
<div class="tm-overlay" id="settingsOverlay" onclick="if(event.target===this)forceCloseSettingsDialog()">
  <div class="tm-dialog" onclick="event.stopPropagation()"
       onkeydown="if((event.metaKey||event.ctrlKey)&&event.key==='Enter'){event.preventDefault();submitSettings();}">
    <div class="tm-dialog-title">Settings</div>
    <p style="font-size:.8rem;color:var(--muted);margin-bottom:.9rem;">Company info used in contract PDFs. Saved to <code>settings.json</code> for this sheet.</p>
    <div class="field-row">
      <div class="field-group"><label>Your Name</label><input class="field-input" id="setName" type="text" placeholder="Jane Smith" /></div>
      <div class="field-group"><label>Company</label><input class="field-input" id="setCompany" type="text" placeholder="Acme LLC" /></div>
    </div>
    <div class="field-row">
      <div class="field-group"><label>Phone</label><input class="field-input" id="setPhone" type="text" placeholder="+1 555…" /></div>
      <div class="field-group"><label>Logo URL</label><input class="field-input" id="setLogo" type="url" placeholder="https://…" /></div>
    </div>
    <div class="field-group"><label>Address</label><input class="field-input" id="setAddress" type="text" placeholder="123 Main St, City, ST 00000" /></div>
    <div class="field-group"><label>Payment Info</label><textarea class="field-input" id="setPayment" placeholder="Bank transfer, PayPal, etc.…" style="min-height:3rem"></textarea></div>
    <div class="field-group">
      <label>GameBoard Template</label>
      <select class="field-input" id="setGbTemplate">
        <option value="">Classic (default)</option>
        <option value="opensea">OpenSea</option>
      </select>
    </div>
    <div class="tm-err" id="setErr"></div>
    <div class="dialog-actions">
      <button class="btn-cancel" onclick="forceCloseSettingsDialog()">Cancel</button>
      <button class="btn-primary" id="setSubmitBtn" onclick="submitSettings()">Save Settings</button>
    </div>
  </div>
</div>

<!-- ── Sign In Dialog ── -->
<div class="tm-overlay" id="signInOverlay" onclick="if(event.target===this)forceCloseSignInDialog()">
  <div class="tm-dialog" style="max-width:360px" onclick="event.stopPropagation()"
       onkeydown="if(event.key==='Enter'){event.preventDefault();submitSignIn(document.getElementById('siConfirm').style.display!=='none');}">
    <div class="tm-dialog-title" id="signInDlgTitle">Sign In</div>
    <div id="siFields">
      <div class="field-group"><label>Email</label><input class="field-input" id="siEmail" type="email" autocomplete="email" placeholder="you@example.com" /></div>
      <div class="field-group"><label>Password</label><input class="field-input" id="siPassword" type="password" autocomplete="current-password" placeholder="••••••••" /></div>
      <div class="tm-err" id="siErr"></div>
      <div class="dialog-actions">
        <button class="btn-cancel" onclick="forceCloseSignInDialog()">Cancel</button>
        <button class="btn-primary" id="siSubmitBtn" onclick="submitSignIn()">Sign In</button>
      </div>
    </div>
    <!-- Create-account confirmation (shown when email not found) -->
    <div id="siConfirm" style="display:none">
      <p style="background:#fff8e8;border:1px solid #f0d898;color:#5a4a1a;border-radius:6px;padding:.7rem .85rem;font-size:.9rem;line-height:1.4;margin:0 0 .5rem">
        No account found for <strong id="siConfirmEmail"></strong>. Would you like to create one?
      </p>
      <div class="tm-err" id="siConfirmErr"></div>
      <div class="dialog-actions">
        <button class="btn-cancel" onclick="_siConfirmBack()">Back</button>
        <button class="btn-primary" id="siConfirmBtn" onclick="submitSignIn(true)">Create Account</button>
      </div>
    </div>
  </div>
</div>

<script>
var SHEET_ID = <?= json_encode($_sheetId) ?>;
var APP_ROOT = <?= json_encode(rtrim($_basePath, '/') . '/') ?>;
var STAFF_NAMES = <?= json_encode($_staffNames, JSON_UNESCAPED_UNICODE) ?>;

// Fill the Log Time staff picker from the Staff tab; keep an existing value
// that isn't on the tab so editing an old entry doesn't lose its name.
function _fillStaffSelect(selected) {
  var sel = document.getElementById('timeStaffName');
  var names = STAFF_NAMES.slice();
  selected = (selected || '').replace(/^'/, '');
  if (selected && names.indexOf(selected) < 0) names.unshift(selected);
  sel.innerHTML = '';
  var blank = document.createElement('option'); blank.value = ''; blank.textContent = names.length ? '— Select staff —' : '— No staff on the Staff tab —';
  sel.appendChild(blank);
  names.forEach(function(n) { var o = document.createElement('option'); o.value = n; o.textContent = n; sel.appendChild(o); });
  sel.value = selected;
}

// ── Auth (owner sign-in) ─────────────────────────────────────────────────────
var _ownerUser = null; // { email, bio: { name, email, ... } }

(function() {
  try {
    var raw = sessionStorage.getItem('tmboard_owner_' + SHEET_ID);
    if (raw) { _ownerUser = JSON.parse(raw); _updateAuthState(); }
  } catch(e) {}
})();

function _updateAuthState() {
  var btn  = document.getElementById('signInBtn');
  var meta = document.getElementById('headerMeta');
  var menuSignInOut = document.getElementById('menuSignInOut');
  var menuProfile   = document.getElementById('menuProfile');

  // Show/hide all auth-gated elements
  // Edit controls (.auth-only) are shown via CSS when <body> has .tm-signed-in,
  // so this also covers any rows rendered later.
  document.body.classList.toggle('tm-signed-in', !!_ownerUser);

  if (!btn) return;
  if (_ownerUser) {
    var name = (_ownerUser.bio && (_ownerUser.bio['name'] || _ownerUser.bio['Name'])) || _ownerUser.email || 'Me';
    btn.classList.add('signed-in');
    btn.title = 'Signed in as ' + (_ownerUser.email || '');
    if (meta) meta.textContent = name + ' · Time & Materials Tracker';
    if (menuSignInOut) { menuSignInOut.textContent = 'Sign Out'; menuSignInOut.className = 'user-menu-item signout'; }
    if (menuProfile)   { menuProfile.style.display = ''; }
  } else {
    btn.classList.remove('signed-in');
    btn.title = 'Menu';
    if (meta) meta.textContent = 'Time & Materials Tracker';
    if (menuSignInOut) { menuSignInOut.textContent = 'Sign In'; menuSignInOut.className = 'user-menu-item signin'; }
    if (menuProfile)   { menuProfile.style.display = 'none'; }
  }
}
// alias used by old call sites
var _updateOwnerHeader = _updateAuthState;

// ── User dropdown menu ────────────────────────────────────────────────────────
function toggleUserMenu(e) {
  e.stopPropagation();
  document.getElementById('userMenu').classList.toggle('open');
}
function closeUserMenu() { document.getElementById('userMenu').classList.remove('open'); }
document.addEventListener('click', closeUserMenu);

function userMenuAction(action) {
  closeUserMenu();
  if (action === 'profile') {
    if (_ownerUser) {
      alert('Signed in as: ' + (_ownerUser.email || '') + '\nName: ' + ((_ownerUser.bio && (_ownerUser.bio['name'] || _ownerUser.bio['Name'])) || '—'));
    } else {
      openSignInDialog();
    }
  } else if (action === 'settings') {
    openSettingsDialog();
  } else if (action === 'fetch') {
    var btn = document.getElementById('signInBtn');
    if (btn) btn.style.opacity = '.5';
    var fd = new FormData(); fd.append('id', SHEET_ID);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_ROOT + 'push/tmRefresh.php');
    xhr.onload = function() { saveOpenCards(); location.reload(); };
    xhr.onerror = function() { location.reload(); };
    xhr.send(fd);
  } else if (action === 'signout') {
    if (_ownerUser) {
      if (confirm('Sign out?')) {
        sessionStorage.removeItem('tmboard_owner_' + SHEET_ID);
        _ownerUser = null; _updateAuthState();
      }
    } else {
      openSignInDialog();
    }
  }
}

// ── View switching ────────────────────────────────────────────────────────────
var _currentView = 'board';
function switchView(name) {
  _currentView = name;
  document.getElementById('viewBoard').classList.toggle('active', name === 'board');
  document.getElementById('viewProjects').classList.toggle('active', name === 'projects');
  document.getElementById('tabBoard').classList.toggle('active', name === 'board');
  document.getElementById('tabProjects').classList.toggle('active', name === 'projects');
  if (name === 'projects') {
    var s = document.getElementById('projectSearch');
    if (s) { filterProjects(s.value); s.focus(); }
  }
}
function filterProjects(q) {
  var lower = (q || '').toLowerCase().trim();
  var items = document.querySelectorAll('#projectsList .proj-list-item');
  var any = false;
  items.forEach(function(el) {
    var match = !lower || (el.dataset.search || '').indexOf(lower) !== -1;
    el.hidden = !match;
    if (match) any = true;
  });
  var empty = document.querySelector('#projectsList .proj-list-empty');
  if (empty) empty.hidden = any || items.length > 0;
}

function openSignInDialog() {
  document.getElementById('siEmail').value     = '';
  document.getElementById('siPassword').value  = '';
  document.getElementById('siErr').textContent = '';
  document.getElementById('siSubmitBtn').disabled = false;
  document.getElementById('siFields').style.display  = '';
  document.getElementById('siConfirm').style.display = 'none';
  document.getElementById('signInDlgTitle').textContent = 'Sign In';
  _siPendingEmail = ''; _siPendingPassword = '';
  document.getElementById('signInOverlay').classList.add('open');
  setTimeout(function(){ document.getElementById('siEmail').focus(); }, 60);
}
function forceCloseSignInDialog() { document.getElementById('signInOverlay').classList.remove('open'); }

// ── Settings ──────────────────────────────────────────────────────────────────
var _settingsCache = null;
function openSettingsDialog() {
  // Load existing settings.json via a quick fetch
  document.getElementById('setErr').textContent = '';
  document.getElementById('setSubmitBtn').disabled = false;
  var xhr = new XMLHttpRequest();
  xhr.open('GET', APP_ROOT + 'push/tmGetSettings.php?id=' + encodeURIComponent(SHEET_ID));
  xhr.onload = function() {
    var s = {}; try { s = JSON.parse(xhr.responseText); } catch(e) {}
    _settingsCache = s;
    document.getElementById('setName').value    = s['Name']         || '';
    document.getElementById('setCompany').value = s['Company']      || '';
    document.getElementById('setPhone').value   = s['Phone']        || '';
    document.getElementById('setLogo').value    = s['Logo URL']     || '';
    document.getElementById('setAddress').value = s['Address']      || '';
    document.getElementById('setPayment').value      = s['Payment Info']       || '';
    document.getElementById('setGbTemplate').value   = s['Gameboard Template'] || '';
  };
  xhr.onerror = function() {};
  xhr.send();
  document.getElementById('settingsOverlay').classList.add('open');
  setTimeout(function(){ document.getElementById('setName').focus(); }, 60);
}
function forceCloseSettingsDialog() { document.getElementById('settingsOverlay').classList.remove('open'); }
function submitSettings() {
  var btn = document.getElementById('setSubmitBtn');
  btn.disabled = true;
  var fd = new FormData();
  fd.append('id', SHEET_ID);
  fd.append('name',    document.getElementById('setName').value.trim());
  fd.append('company', document.getElementById('setCompany').value.trim());
  fd.append('phone',   document.getElementById('setPhone').value.trim());
  fd.append('logo',    document.getElementById('setLogo').value.trim());
  fd.append('address', document.getElementById('setAddress').value.trim());
  fd.append('payment',     document.getElementById('setPayment').value.trim());
  fd.append('gb_template', document.getElementById('setGbTemplate').value);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/tmSaveSettings.php');
  xhr.onload = function() {
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = {error: xhr.responseText}; }
    btn.disabled = false;
    if (r.error) { document.getElementById('setErr').textContent = r.error; return; }
    forceCloseSettingsDialog();
  };
  xhr.onerror = function() { btn.disabled = false; document.getElementById('setErr').textContent = 'Network error.'; };
  xhr.send(fd);
}
var _siPendingEmail = '', _siPendingPassword = '';

function submitSignIn(confirmNew) {
  var email = confirmNew ? _siPendingEmail    : document.getElementById('siEmail').value.trim();
  var pass  = confirmNew ? _siPendingPassword : document.getElementById('siPassword').value;
  var errEl = document.getElementById(confirmNew ? 'siConfirmErr' : 'siErr');
  errEl.textContent = '';
  if (!email || !pass) { errEl.textContent = 'Email and password required.'; return; }
  var btn = document.getElementById(confirmNew ? 'siConfirmBtn' : 'siSubmitBtn');
  btn.disabled = true;
  var fd = new FormData();
  fd.append('email', email); fd.append('password', pass); fd.append('id', SHEET_ID);
  if (confirmNew) fd.append('confirm_new', '1');
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/collabAuth.php');
  xhr.onload = function() {
    var r; try { r = JSON.parse(xhr.responseText); } catch(e) { r = {error: 'Bad response'}; }
    btn.disabled = false;
    if (r.error) { errEl.textContent = r.error; return; }
    if (r.prompt_create) {
      // Unknown email — offer to create an account (same flow as DevBoard)
      _siPendingEmail = email; _siPendingPassword = pass;
      document.getElementById('siConfirmEmail').textContent = email;
      document.getElementById('siConfirmErr').textContent = '';
      document.getElementById('siFields').style.display  = 'none';
      document.getElementById('siConfirm').style.display = '';
      document.getElementById('signInDlgTitle').textContent = 'Create Account';
      setTimeout(function(){ document.getElementById('siConfirmBtn').focus(); }, 30);
      return;
    }
    _ownerUser = { email: r.email || email, bio: r.bio || {} };
    try { sessionStorage.setItem('tmboard_owner_' + SHEET_ID, JSON.stringify(_ownerUser)); } catch(e) {}
    _siPendingEmail = ''; _siPendingPassword = '';
    forceCloseSignInDialog(); _updateAuthState();
  };
  xhr.onerror = function() { btn.disabled = false; errEl.textContent = 'Network error.'; };
  xhr.send(fd);
}

function _siConfirmBack() {
  document.getElementById('siConfirm').style.display = 'none';
  document.getElementById('siFields').style.display  = '';
  document.getElementById('signInDlgTitle').textContent = 'Sign In';
  setTimeout(function(){ document.getElementById('siPassword').focus(); }, 30);
}

// ── Helpers ──────────────────────────────────────────────────────────────────
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

function today() {
  return new Date().toISOString().slice(0,10);
}

function toggleProject(id) {
  var el = document.getElementById(id);
  if (el) el.classList.toggle('open');
}

function reloadCard(btn) {
  btn.classList.add('spinning');
  saveOpenCards();
  var fd = new FormData();
  fd.append('id', SHEET_ID);
  var xhr = new XMLHttpRequest();
  xhr.open('POST', APP_ROOT + 'push/tmRefresh.php');
  xhr.onload = function() { location.reload(); };
  xhr.onerror = function() { location.reload(); };
  xhr.send(fd);
}

// ── Preserve open cards across reload ────────────────────────────────────────
var _TM_OPEN_KEY = 'tm_open_cards_' + SHEET_ID;
function saveOpenCards() {
  var ids = [].map.call(document.querySelectorAll('.project-body.open'), function(el) { return el.id; });
  sessionStorage.setItem(_TM_OPEN_KEY, JSON.stringify(ids));
}
function restoreOpenCards() {
  var raw = sessionStorage.getItem(_TM_OPEN_KEY);
  if (!raw) return;
  sessionStorage.removeItem(_TM_OPEN_KEY);
  try {
    var ids = JSON.parse(raw);
    ids.forEach(function(id) {
      var el = document.getElementById(id);
      if (el) el.classList.add('open');
    });
  } catch(e) {}
}
document.addEventListener('DOMContentLoaded', restoreOpenCards);

// ── Esc key ───────────────────────────────────────────────────────────────────
document.addEventListener('keydown', function(e) {
  if (e.key !== 'Escape') return;
  if (document.getElementById('settingsOverlay').classList.contains('open')) { forceCloseSettingsDialog(); return; }
  if (document.getElementById('signInOverlay').classList.contains('open'))  { forceCloseSignInDialog();   return; }
  if (document.getElementById('contractOverlay').classList.contains('open')){ forceCloseContractDialog(); return; }
  if (document.getElementById('shareOverlay').classList.contains('open'))   { forceCloseShareDialog();    return; }
  if (document.getElementById('timeOverlay').classList.contains('open'))    { forceCloseTimeDialog();     return; }
  if (document.getElementById('hwOverlay').classList.contains('open'))      { forceCloseHwDialog();       return; }
  if (document.getElementById('projectOverlay').classList.contains('open')) { forceCloseProjectDialog();  return; }
  if (document.getElementById('addClientOverlay').classList.contains('open')) { forceCloseAddClientDialog(); return; }
});

// ── Add Client ────────────────────────────────────────────────────────────────
function openAddClientDialog() {
  document.getElementById('acName').value  = '';
  document.getElementById('acEmail').value = '';
  document.getElementById('acPhone').value = '';
  document.getElementById('acNotes').value = '';
  document.getElementById('acErr').textContent = '';
  document.getElementById('acSubmitBtn').disabled = false;
  document.getElementById('addClientOverlay').classList.add('open');
  setTimeout(function(){ document.getElementById('acName').focus(); }, 60);
}
function forceCloseAddClientDialog() { document.getElementById('addClientOverlay').classList.remove('open'); }
function submitAddClient() {
  var name = document.getElementById('acName').value.trim();
  if (!name) { document.getElementById('acErr').textContent = 'Client name is required.'; return; }
  var btn = document.getElementById('acSubmitBtn');
  btn.disabled = true;
  post('push/tmAddClient.php', {
    client: name,
    contact_email: document.getElementById('acEmail').value.trim(),
    phone: document.getElementById('acPhone').value.trim(),
    notes: document.getElementById('acNotes').value.trim(),
  }, function(r) {
    if (r.error) { document.getElementById('acErr').textContent = r.error; btn.disabled = false; return; }
    forceCloseAddClientDialog();
    saveOpenCards(); location.reload();
  });
}

// ── Add / Edit Project ────────────────────────────────────────────────────────
var _editingProject = null;
function openAddProjectDialog() {
  _editingProject = null;
  document.getElementById('projectDlgTitle').textContent = 'Add Project';
  document.getElementById('projClient').value  = '';
  document.getElementById('projName').value    = '';
  document.getElementById('projStatus').value  = 'Active';
  document.getElementById('projEst').value     = '';
  document.getElementById('projStart').value   = '';
  document.getElementById('projEnd').value     = '';
  document.getElementById('projNotes').value   = '';
  document.getElementById('projErr').textContent = '';
  document.getElementById('projSubmitBtn').disabled = false;
  document.getElementById('projSubmitBtn').textContent = 'Add Project';
  document.getElementById('projectOverlay').classList.add('open');
  setTimeout(function(){ document.getElementById('projName').focus(); }, 60);
}
function openEditProjectDialog(p) {
  _editingProject = p;
  document.getElementById('projectDlgTitle').textContent = 'Edit Project';
  document.getElementById('projClient').value  = p['Client']         || '';
  document.getElementById('projName').value    = p['Project']        || '';
  document.getElementById('projStatus').value  = p['Status']         || 'Active';
  document.getElementById('projEst').value     = p['Estimate Hours'] || '';
  document.getElementById('projStart').value   = p['Start Date']     || '';
  document.getElementById('projEnd').value     = p['End Date']       || '';
  document.getElementById('projNotes').value   = p['Notes']          || '';
  document.getElementById('projErr').textContent = '';
  document.getElementById('projSubmitBtn').disabled = false;
  document.getElementById('projSubmitBtn').textContent = 'Update';
  document.getElementById('projectOverlay').classList.add('open');
  setTimeout(function(){ document.getElementById('projName').focus(); }, 60);
}
function forceCloseProjectDialog() { document.getElementById('projectOverlay').classList.remove('open'); _editingProject = null; }
function submitProject() {
  var name = document.getElementById('projName').value.trim();
  if (!name) { document.getElementById('projErr').textContent = 'Project name is required.'; return; }
  var btn = document.getElementById('projSubmitBtn');
  btn.disabled = true;
  var data = {
    client:         document.getElementById('projClient').value.trim(),
    project:        name,
    status:         document.getElementById('projStatus').value,
    estimate_hours: document.getElementById('projEst').value.trim(),
    start_date:     document.getElementById('projStart').value,
    end_date:       document.getElementById('projEnd').value,
    notes:          document.getElementById('projNotes').value.trim(),
  };
  if (_editingProject) {
    data.row_id = _editingProject['Ref Number'] || '';
    post('push/tmUpdateProject.php', data, function(r) {
      if (r.error) { document.getElementById('projErr').textContent = r.error; btn.disabled = false; return; }
      forceCloseProjectDialog(); saveOpenCards(); location.reload();
    });
  } else {
    post('push/tmAddProject.php', data, function(r) {
      if (r.error) { document.getElementById('projErr').textContent = r.error; btn.disabled = false; return; }
      forceCloseProjectDialog(); saveOpenCards(); location.reload();
    });
  }
}
function deleteProject(rowId, name) {
  if (!confirm('Delete project "' + name + '"? This will not delete associated time or hardware entries.')) return;
  post('push/tmDeleteProject.php', {row_id: rowId}, function(r) {
    if (r.error) { alert('Error: ' + r.error); return; }
    saveOpenCards(); location.reload();
  });
}

// ── Time Entries ──────────────────────────────────────────────────────────────
var _timeProjName = '';
var _timeProjClient = '';
var _editingTime = null;
function openAddTimeDialog(proj, client) {
  _timeProjName = proj; _timeProjClient = client; _editingTime = null;
  document.getElementById('timeDlgTitle').textContent = 'Log Time · ' + proj;
  document.getElementById('timeDate').value       = today();
  document.getElementById('timeHours').value      = '';
  // Pre-select the signed-in owner if they're on the Staff tab
  var _sn = (_ownerUser && _ownerUser.bio && (_ownerUser.bio['name'] || _ownerUser.bio['Name'])) || '';
  _fillStaffSelect(STAFF_NAMES.indexOf(_sn) >= 0 ? _sn : '');
  document.getElementById('timeDesc').value       = '';
  document.getElementById('timeErr').textContent  = '';
  document.getElementById('timeSubmitBtn').disabled = false;
  document.getElementById('timeSubmitBtn').textContent = 'Log Time';
  document.getElementById('timeOverlay').classList.add('open');
  setTimeout(function(){ document.getElementById('timeHours').focus(); }, 60);
}
function openEditTimeDialog(t) {
  _timeProjName = t['Project'] || ''; _timeProjClient = t['Client'] || ''; _editingTime = t;
  document.getElementById('timeDlgTitle').textContent = 'Edit Time · ' + _timeProjName;
  document.getElementById('timeDate').value       = t['Date']        || '';
  document.getElementById('timeHours').value      = t['Hours']       || '';
  _fillStaffSelect(t['Staff Name'] || '');
  document.getElementById('timeDesc').value       = t['Description'] || '';
  document.getElementById('timeErr').textContent  = '';
  document.getElementById('timeSubmitBtn').disabled = false;
  document.getElementById('timeSubmitBtn').textContent = 'Update';
  document.getElementById('timeOverlay').classList.add('open');
}
function forceCloseTimeDialog() { document.getElementById('timeOverlay').classList.remove('open'); _editingTime = null; }
function submitTime() {
  var hours = document.getElementById('timeHours').value.trim();
  var date  = document.getElementById('timeDate').value.trim();
  if (!date || !hours) { document.getElementById('timeErr').textContent = 'Date and hours are required.'; return; }
  var btn = document.getElementById('timeSubmitBtn');
  btn.disabled = true;
  var data = {
    date:        date,
    project:     _timeProjName,
    client:      _timeProjClient,
    staff_name:  document.getElementById('timeStaffName').value.trim(),
    hours:       hours,
    description: document.getElementById('timeDesc').value.trim(),
  };
  if (_editingTime) {
    data.row_id = _editingTime['Ref Number'] || '';
    // If no Ref Number, pass original field values so PHP can fingerprint-locate the row
    if (!data.row_id) {
      data.orig_date        = _editingTime['Date']        || '';
      data.orig_project     = _editingTime['Project']     || '';
      data.orig_client      = _editingTime['Client']      || '';
      data.orig_staff_name  = _editingTime['Staff Name']  || '';
      data.orig_hours       = _editingTime['Hours']       || '';
      data.orig_description = _editingTime['Description'] || '';
    }
    post('push/tmUpdateTime.php', data, function(r) {
      if (r.error) { document.getElementById('timeErr').textContent = r.error; btn.disabled = false; return; }
      forceCloseTimeDialog(); saveOpenCards(); location.reload();
    });
  } else {
    post('push/tmAddTime.php', data, function(r) {
      if (r.error) { document.getElementById('timeErr').textContent = r.error; btn.disabled = false; return; }
      forceCloseTimeDialog(); saveOpenCards(); location.reload();
    });
  }
}
function deleteTime(row) {
  if (!confirm('Delete this time entry?')) return;
  var rowId = (row['Ref Number'] || '').trim();
  var data  = {row_id: rowId};
  if (!rowId) {
    data.date        = row['Date']        || '';
    data.project     = row['Project']     || '';
    data.client      = row['Client']      || '';
    data.staff_name  = row['Staff Name']  || '';
    data.hours       = row['Hours']       || '';
    data.description = row['Description'] || '';
  }
  post('push/tmDeleteTime.php', data, function(r) {
    if (r.error) { alert('Error: ' + r.error); return; }
    saveOpenCards(); location.reload();
  });
}

// ── Hardware / Material Costs ─────────────────────────────────────────────────
var _hwProjName = '';
var _hwProjClient = '';
var _editingHw = null;
function openAddHwDialog(proj, client) {
  _hwProjName = proj; _hwProjClient = client; _editingHw = null;
  document.getElementById('hwDlgTitle').textContent = 'Add Cost · ' + proj;
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
  _hwProjName = h['Project'] || ''; _hwProjClient = h['Client'] || ''; _editingHw = h;
  document.getElementById('hwDlgTitle').textContent = 'Edit Cost · ' + _hwProjName;
  document.getElementById('hwDate').value   = h['Date']        || '';
  document.getElementById('hwAmount').value = h['Amount']      || '';
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
  if (!desc || !amount) { document.getElementById('hwErr').textContent = 'Description and amount are required.'; return; }
  var btn = document.getElementById('hwSubmitBtn');
  btn.disabled = true;
  var data = {
    date:        document.getElementById('hwDate').value.trim(),
    project:     _hwProjName,
    client:      _hwProjClient,
    description: desc,
    amount:      amount,
    status:      document.getElementById('hwStatus').value,
    buy_url:     document.getElementById('hwBuyUrl').value.trim(),
    notes:       document.getElementById('hwNotes').value.trim(),
  };
  if (_editingHw) {
    data.row_id = _editingHw['Ref Number'] || '';
    if (!data.row_id) {
      data.orig_date        = _editingHw['Date']        || '';
      data.orig_project     = _editingHw['Project']     || '';
      data.orig_client      = _editingHw['Client']      || '';
      data.orig_description = _editingHw['Description'] || '';
      data.orig_amount      = _editingHw['Amount']      || '';
    }
    post('push/tmUpdateHardware.php', data, function(r) {
      if (r.error) { document.getElementById('hwErr').textContent = r.error; btn.disabled = false; return; }
      forceCloseHwDialog(); saveOpenCards(); location.reload();
    });
  } else {
    post('push/tmAddHardware.php', data, function(r) {
      if (r.error) { document.getElementById('hwErr').textContent = r.error; btn.disabled = false; return; }
      forceCloseHwDialog(); saveOpenCards(); location.reload();
    });
  }
}
function deleteHw(row) {
  if (!confirm('Delete this cost entry?')) return;
  var rowId = (row['Ref Number'] || '').trim();
  var data  = {row_id: rowId};
  if (!rowId) {
    data.date        = row['Date']        || '';
    data.project     = row['Project']     || '';
    data.client      = row['Client']      || '';
    data.description = row['Description'] || '';
    data.amount      = row['Amount']      || '';
  }
  post('push/tmDeleteHardware.php', data, function(r) {
    if (r.error) { alert('Error: ' + r.error); return; }
    saveOpenCards(); location.reload();
  });
}

// ── Share Dialog ──────────────────────────────────────────────────────────────
var _shareUrl = '';
function openShareDialog(proj, client) {
  document.getElementById('shareProjName').textContent = proj;
  document.getElementById('shareUrlBox').textContent   = 'Generating…';
  document.getElementById('shareErr').textContent      = '';
  document.getElementById('shareOverlay').classList.add('open');

  post('push/createTmboardShare.php', {project: proj, client: client}, function(r) {
    if (r.error) { document.getElementById('shareErr').textContent = r.error; return; }
    _shareUrl = r.viewUrl || '';
    document.getElementById('shareUrlBox').textContent = _shareUrl;
    document.getElementById('shareOpenBtn').href = _shareUrl;
  });
}
function forceCloseShareDialog() { document.getElementById('shareOverlay').classList.remove('open'); }

// ── Contract ──────────────────────────────────────────────────────────────────
var _conProjName = '', _conClient = '';
var conTimeCostEdited = false;

function recalcContract() {
  if (conTimeCostEdited) return; // user overrode it, don't clobber
  var h = parseFloat(document.getElementById('conHours').value)   || 0;
  var r = parseFloat(document.getElementById('conRate').value)    || 0;
  document.getElementById('conTimeCost').value = (h * r).toFixed(2);
  recalcTotal();
}
function recalcTotal() {
  var tc = parseFloat(document.getElementById('conTimeCost').value) || 0;
  var mc = parseFloat(document.getElementById('conMatCost').value)  || 0;
  document.getElementById('conTotal').value = (tc + mc).toFixed(2);
}
function openContractDialog(proj, client, totalHrs, totalHw) {
  _conProjName = proj; _conClient = client;
  conTimeCostEdited = false;
  document.getElementById('conDlgTitle').textContent  = 'New Contract';
  document.getElementById('conProjDisplay').textContent   = proj;
  document.getElementById('conClientDisplay').textContent = client;
  document.getElementById('conType').value    = 'Estimate';
  document.getElementById('conDueDate').value = '';
  document.getElementById('conHours').value   = totalHrs || '';
  document.getElementById('conRate').value    = '';
  document.getElementById('conTimeCost').value= '';
  document.getElementById('conMatCost').value = (totalHw || 0).toFixed(2);
  document.getElementById('conTotal').value   = (totalHw || 0).toFixed(2);
  document.getElementById('conNotes').value   = '';
  document.getElementById('conErr').textContent = '';
  document.getElementById('conLog').style.display = 'none';
  document.getElementById('conSubmitBtn').disabled = false;
  document.getElementById('contractOverlay').classList.add('open');
  setTimeout(function(){ document.getElementById('conRate').focus(); }, 60);
}
function forceCloseContractDialog() {
  document.getElementById('contractOverlay').classList.remove('open');
}
function submitContract() {
  var btn = document.getElementById('conSubmitBtn');
  var tc  = document.getElementById('conTimeCost').value.trim();
  var mc  = document.getElementById('conMatCost').value.trim();
  if (!tc && !mc) { document.getElementById('conErr').textContent = 'Enter at least a time or material cost.'; return; }
  btn.disabled = true;
  document.getElementById('conErr').textContent = '';
  var log = document.getElementById('conLog');
  log.style.display = ''; log.textContent = 'Generating PDF…';
  var data = {
    project:     _conProjName,
    client:      _conClient,
    doc_type:    document.getElementById('conType').value,
    time_hours:  document.getElementById('conHours').value.trim(),
    hourly_rate: document.getElementById('conRate').value.trim(),
    time_cost:   tc || '0',
    mat_cost:    mc || '0',
    notes:       document.getElementById('conNotes').value.trim(),
    due_date:    document.getElementById('conDueDate').value,
  };
  post('push/tmCreateContract.php', data, function(r) {
    btn.disabled = false;
    if (r.error) { log.style.display = 'none'; document.getElementById('conErr').textContent = r.error; return; }
    log.textContent = '✓ Contract created!';
    if (r.url) window.open(r.url, '_blank');
    setTimeout(function() { forceCloseContractDialog(); saveOpenCards(); location.reload(); }, 800);
  });
}
function copyShareUrl() {
  if (!_shareUrl) return;
  var btn = document.getElementById('shareCopyBtn');
  navigator.clipboard.writeText(_shareUrl).then(function() {
    btn.textContent = 'Copied!'; btn.classList.add('copied');
    setTimeout(function() { btn.textContent = 'Copy Link'; btn.classList.remove('copied'); }, 2000);
  });
}

// ── Date auto-wrap ────────────────────────────────────────────────────────────
document.querySelectorAll('input[type="date"]').forEach(function(inp) {
  if (inp.closest('.date-wrap')) return;
  var w = document.createElement('div'); w.className = 'date-wrap';
  inp.parentNode.insertBefore(w, inp); w.appendChild(inp);
});
</script>
</body>
</html>
