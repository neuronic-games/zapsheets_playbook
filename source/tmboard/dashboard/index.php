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

$_clients  = _tm_load($_sDir . '/clients.json');
$_projects = _tm_load($_sDir . '/projects.json');
$_timeRows = _tm_load($_sDir . '/time.json');
$_hwRows   = _tm_load($_sDir . '/hardware.json');

// Index time/hardware by project for fast lookup
$_timeByProject = [];
foreach ($_timeRows as $r) { $_timeByProject[$r['Project'] ?? ''][] = $r; }
$_hwByProject   = [];
foreach ($_hwRows   as $r) { $_hwByProject[$r['Project']   ?? ''][] = $r; }

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
  background:var(--accent); color:#fff;
  display:flex; align-items:center; justify-content:space-between;
  padding:.75rem 1.5rem; position:sticky; top:0; z-index:100;
  box-shadow:0 2px 12px rgba(59,59,170,.35);
}
.tm-logo { font-family:'DINBlack',sans-serif; font-size:1.25rem; letter-spacing:.07em; }
.tm-logo span { opacity:.7; font-size:.8rem; margin-left:.5rem; font-family:'DINRegular',sans-serif; }
.header-actions { display:flex; gap:.6rem; }
.btn-header {
  font-family:'DINBlack',sans-serif; font-size:.72rem; text-transform:uppercase;
  letter-spacing:.07em; padding:.4rem .85rem; border-radius:6px; cursor:pointer;
  border:1.5px solid rgba(255,255,255,.55); color:#fff; background:transparent;
  transition:background .15s;
}
.btn-header:hover { background:rgba(255,255,255,.18); }
.btn-header.primary { background:#fff; color:var(--accent); border-color:#fff; }
.btn-header.primary:hover { background:#e8e8f8; }

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
  <div class="tm-logo">T&amp;MBoard <span>Time &amp; Materials</span></div>
  <div class="header-actions">
    <button class="btn-header" onclick="openAddClientDialog()">+ Client</button>
    <button class="btn-header primary" onclick="openAddProjectDialog()">+ Project</button>
  </div>
</div>

<div class="tm-main">

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
    $pRowId   = $_p['Row ID']         ?? '';
    $pStatus  = $_p['Status']         ?? 'Active';
    $pEst     = (float)($_p['Estimate Hours'] ?? 0);
    $pStart   = $_p['Start Date']     ?? '';
    $pEnd     = $_p['End Date']       ?? '';
    $pNotes   = $_p['Notes']          ?? '';
    $tRows    = $_timeByProject[$pName] ?? [];
    $hwRows   = $_hwByProject[$pName]   ?? [];
    $totalHrs = array_sum(array_column($tRows, 'Hours'));
    $totalHw  = array_sum(array_column($hwRows, 'Amount'));
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
        <button class="btn-sm" onclick="openShareDialog(<?= htmlspecialchars(json_encode($pName), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($_cn), ENT_QUOTES) ?>)">Share</button>
        <button class="btn-sm" onclick="openEditProjectDialog(<?= htmlspecialchars(json_encode($_p), ENT_QUOTES) ?>)">Edit</button>
        <button class="btn-sm danger" onclick="deleteProject(<?= htmlspecialchars(json_encode($pRowId), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($pName), ENT_QUOTES) ?>)">Delete</button>
      </div>
    </div>

    <div class="project-body" id="<?= htmlspecialchars($cardId) ?>">

      <!-- TIME ENTRIES -->
      <div class="section-head">
        <div class="section-label">Time Entries</div>
        <div style="display:flex;align-items:center;gap:.6rem;">
          <?php if ($pEst > 0): ?><div class="section-total"><?= $totalHrs ?>h / <?= $pEst ?>h estimated</div><?php endif; ?>
          <button class="btn-sm primary" onclick="openAddTimeDialog(<?= htmlspecialchars(json_encode($pName), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($_cn), ENT_QUOTES) ?>)">+ Log Time</button>
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
            <button class="btn-sm" onclick="openEditTimeDialog(<?= htmlspecialchars(json_encode($t), ENT_QUOTES) ?>)">Edit</button>
            <button class="btn-sm danger" onclick="deleteTime(<?= htmlspecialchars(json_encode($t['Row ID'] ?? ''), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($pName), ENT_QUOTES) ?>)">Del</button>
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
          <button class="btn-sm primary" onclick="openAddHwDialog(<?= htmlspecialchars(json_encode($pName), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($_cn), ENT_QUOTES) ?>)">+ Add Cost</button>
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
            <button class="btn-sm" onclick="openEditHwDialog(<?= htmlspecialchars(json_encode($h), ENT_QUOTES) ?>)">Edit</button>
            <button class="btn-sm danger" onclick="deleteHw(<?= htmlspecialchars(json_encode($h['Row ID'] ?? ''), ENT_QUOTES) ?>,<?= htmlspecialchars(json_encode($pName), ENT_QUOTES) ?>)">Del</button>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?><div class="empty-msg">No costs recorded yet.</div><?php endif; ?>

    </div><!-- /project-body -->
  </div><!-- /project-card -->
  <?php endforeach; ?>
</div><!-- /client-section -->
<?php endforeach; ?>

<?php endif; ?>
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
    <div class="field-group"><label>Staff Name</label><input class="field-input" id="timeStaffName" type="text" placeholder="Jane Smith" /></div>
    <div class="field-group"><label>Staff Email</label><input class="field-input" id="timeStaffEmail" type="email" placeholder="jane@example.com" /></div>
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
    <div class="field-group"><label>Notes</label><textarea class="field-input" id="hwNotes" placeholder="Optional notes…"></textarea></div>
    <div class="tm-err" id="hwErr"></div>
    <div class="dialog-actions">
      <button class="btn-cancel" onclick="forceCloseHwDialog()">Cancel</button>
      <button class="btn-primary" id="hwSubmitBtn" onclick="submitHw()">Save</button>
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

<script>
var SHEET_ID = <?= json_encode($_sheetId) ?>;
var APP_ROOT = <?= json_encode(rtrim($_basePath, '/') . '/') ?>;

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
    data.row_id = _editingProject['Row ID'] || '';
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
  document.getElementById('timeStaffName').value  = '';
  document.getElementById('timeStaffEmail').value = '';
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
  document.getElementById('timeStaffName').value  = t['Staff Name']  || '';
  document.getElementById('timeStaffEmail').value = t['Staff Email'] || '';
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
    staff_email: document.getElementById('timeStaffEmail').value.trim(),
    hours:       hours,
    description: document.getElementById('timeDesc').value.trim(),
  };
  if (_editingTime) {
    data.row_id = _editingTime['Row ID'] || '';
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
function deleteTime(rowId, proj) {
  if (!rowId || !confirm('Delete this time entry?')) return;
  post('push/tmDeleteTime.php', {row_id: rowId}, function(r) {
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
    notes:       document.getElementById('hwNotes').value.trim(),
  };
  if (_editingHw) {
    data.row_id = _editingHw['Row ID'] || '';
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
function deleteHw(rowId, proj) {
  if (!rowId || !confirm('Delete this cost entry?')) return;
  post('push/tmDeleteHardware.php', {row_id: rowId}, function(r) {
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
