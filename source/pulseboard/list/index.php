<?php
/**
 * PulseBoard dashboard — shows machine status grouped by tab.
 * URL: /{sheet_id}/pulseboard
 */

$_rp = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$_bpFile = __DIR__ . '/../../../dotEnv.php';
if (file_exists($_bpFile)) { require_once $_bpFile; }

preg_match('#^(.*?)(?:sheets/)?([A-Za-z0-9_\-]+)/pulseboard/?$#', $_rp, $_bm);
$_base     = (isset($_bm[1]) && $_bm[1] !== '') ? $_bm[1] : '/';
if (substr($_base, -1) !== '/') $_base .= '/';
$_sheet_id = $_bm[2] ?? '';

$_sheets_dir = __DIR__ . '/../../../sheets/' . $_sheet_id . '/';

$_pb_index_path = $_sheets_dir . 'pulseboard-index.json';
$_pb_index      = file_exists($_pb_index_path)
    ? (json_decode(file_get_contents($_pb_index_path), true) ?: [])
    : [];

$_groups = [];
foreach ($_pb_index as $_safe => $_display) {
    $_cache_file = $_sheets_dir . 'pulseboard-' . $_safe . '.json';
    if (!file_exists($_cache_file)) continue;
    $_cache = json_decode(file_get_contents($_cache_file), true) ?: [];
    $_groups[] = [
        'name'     => $_cache['tab'] ?? $_display,
        'safe'     => $_safe,
        'machines' => $_cache['machines'] ?? [],
    ];
}

$_total_machines = array_sum(array_map(fn($g) => count($g['machines']), $_groups));

$_app_name     = 'PulseBoard';
$_icon_url     = $_base . 'images/pb_icon_512.png';
$_manifest_url = $_base . $_sheet_id . '/pulseboard/manifest.json';

function _pb_status_class(string $s): string {
    $sl = strtolower(trim($s));
    if (in_array($sl, ['online','running','active','up','ok'])) return 'status-online';
    if (in_array($sl, ['offline','stopped','down','error','fail','failed'])) return 'status-offline';
    return 'status-unknown';
}

/** Parse "free/total GB" → [free_pct (int), bar_color (string)] */
function _pb_usage_bar(string $val): array {
    if (!preg_match('/^([\d.]+)\/([\d.]+)/', $val, $m)) return [0, ''];
    $free  = (float)$m[1];
    $total = (float)$m[2];
    if ($total <= 0) return [0, ''];
    $pct = (int)round(($free / $total) * 100);  // bar = free %
    if ($pct > 40)       $color = '#16a34a';   // plenty free → green
    elseif ($pct > 20)   $color = '#f59e0b';   // getting low → amber
    else                 $color = '#dc2626';   // critically low → red
    return [$pct, $color];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= htmlspecialchars($_app_name) ?></title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg width='180' height='180' viewBox='0 0 180 180' fill='none' xmlns='http://www.w3.org/2000/svg'><rect width='180' height='180' rx='36' fill='%231a1a1a'/><polyline points='8,90 42,90 52,38 68,138 82,58 98,90 132,90' stroke='%23ef4444' stroke-width='10' stroke-linecap='round' stroke-linejoin='round'/><line x1='132' y1='90' x2='148' y2='90' stroke='%23ef4444' stroke-width='10' stroke-linecap='round'/><circle cx='164' cy='90' r='16' fill='%2316a34a'/></svg>" />
<link rel="apple-touch-icon" href="<?= htmlspecialchars($_icon_url, ENT_QUOTES) ?>" />
<link rel="manifest" href="<?= htmlspecialchars($_manifest_url, ENT_QUOTES) ?>" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
<meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars($_app_name, ENT_QUOTES) ?>" />
<meta name="theme-color" content="#0a1118" />
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2')  format('woff2'),url('fonts/DINBlack.ttf')  format('truetype'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf') format('truetype'); }

*, *::before, *::after { box-sizing:border-box; }
body { margin:0; background:#0f1923; font-family:'DINRegular',Arial,sans-serif; color:#e0e6f0; }

/* ── Top bar ─────────────────────────────────────────── */
.top-bar {
  background:#0a1118; color:#fff;
  padding:.75rem 1.25rem;
  position:sticky; top:0; z-index:100;
  border-bottom:1px solid rgba(22,163,74,.2);
}
.top-bar-inner {
  max-width:1100px; margin:0 auto;
  display:flex; align-items:center; gap:.75rem;
}
.top-bar-left { flex:1; min-width:0; display:flex; align-items:center; gap:.75rem; }
.top-bar-logo { width:32px; height:32px; flex-shrink:0; display:flex; align-items:center; }
.top-bar h1 {
  font-family:'DINBlack',sans-serif; font-size:1rem;
  margin:0; letter-spacing:.03em;
}
.pb-pulse { color:#ef4444; }
.pb-board { color:#22c55e; }
.top-bar-stat {
  font-family:'DINBlack',sans-serif; font-size:.72rem;
  text-transform:uppercase; letter-spacing:.06em;
  background:rgba(22,163,74,.15); border:1px solid rgba(22,163,74,.25);
  border-radius:6px; padding:.3rem .75rem; white-space:nowrap; flex-shrink:0;
  color:#4ade80;
}

/* ── Group share button ───────────────────────────────── */
.pb-group-share-btn {
  display:inline-flex; align-items:center; gap:.3rem;
  font-family:'DINBlack',sans-serif; font-size:.62rem;
  text-transform:uppercase; letter-spacing:.06em;
  background:rgba(22,163,74,.12); color:#4ade80;
  border:1px solid rgba(22,163,74,.25); border-radius:5px;
  padding:.22rem .55rem; cursor:pointer; transition:background .15s;
  flex-shrink:0;
}
.pb-group-share-btn:hover { background:rgba(22,163,74,.25); }

/* ── Account menu ────────────────────────────────────── */
.account-menu-wrap { position:relative; flex-shrink:0; }
.pb-menu-btn {
  display:inline-flex; align-items:center; justify-content:center;
  background:rgba(255,255,255,.1); color:#fff;
  border:1px solid rgba(255,255,255,.2); border-radius:6px;
  padding:.38rem .5rem; cursor:pointer; transition:background .15s;
}
.pb-menu-btn:hover { background:rgba(255,255,255,.2); }
.account-menu {
  display:none; position:absolute; top:calc(100% + .4rem); right:0;
  background:#0a1118; border:1px solid rgba(22,163,74,.3);
  border-radius:8px; min-width:120px; z-index:300;
  box-shadow:0 6px 20px rgba(0,0,0,.5); overflow:hidden;
}
.account-menu.open { display:block; }
.account-menu-item {
  display:block; width:100%; background:none; border:none;
  color:rgba(255,255,255,.8); text-align:left; cursor:pointer;
  font-family:'DINBlack',sans-serif; font-size:.72rem;
  text-transform:uppercase; letter-spacing:.07em;
  padding:.6rem 1rem; transition:background .12s;
}
.account-menu-item:hover { background:rgba(22,163,74,.15); color:#fff; }

/* ── Fetch dialog ────────────────────────────────────── */
.pb-fetch-overlay {
  display:none; position:fixed; inset:0;
  background:rgba(0,0,0,.6); z-index:1000;
  align-items:center; justify-content:center;
}
.pb-fetch-overlay.open { display:flex; }
.pb-fetch-dialog {
  background:#0f1923; border:1px solid rgba(22,163,74,.3); border-radius:10px;
  padding:1.4rem; width:min(420px,92vw);
  box-shadow:0 8px 32px rgba(0,0,0,.5);
  display:flex; flex-direction:column; gap:.75rem;
}
.pb-fetch-dialog h2 {
  font-family:'DINBlack',sans-serif; font-size:.95rem; margin:0; color:#e0e6f0;
}
.pb-fetch-log {
  background:#060d13; border-radius:6px;
  padding:.75rem 1rem; min-height:5rem; max-height:12rem;
  overflow-y:auto; font-family:monospace; font-size:.75rem; line-height:1.6;
}
.pb-log-line        { display:block; color:#64748b; }
.pb-log-line.ok     { color:#4ade80; }
.pb-log-line.error  { color:#f87171; }
.pb-log-line.info   { color:#60a5fa; }
.pb-dialog-actions  { display:flex; align-items:center; justify-content:flex-end; gap:.5rem; }
.pb-close-btn {
  font-family:'DINBlack',sans-serif; font-size:.7rem;
  text-transform:uppercase; letter-spacing:.05em;
  background:rgba(255,255,255,.08); color:#aaa; border:1px solid rgba(255,255,255,.15);
  border-radius:6px; padding:.42rem .9rem; cursor:pointer;
}
.pb-close-btn:hover { background:rgba(255,255,255,.14); color:#fff; }
.pb-close-btn:disabled { opacity:.4; cursor:default; }

/* ── Share dialog ────────────────────────────────────── */
.pb-share-overlay {
  display:none; position:fixed; inset:0;
  background:rgba(0,0,0,.6); z-index:1000;
  align-items:center; justify-content:center;
}
.pb-share-overlay.open { display:flex; }
.pb-share-dialog {
  background:#0f1923; border:1px solid rgba(22,163,74,.3); border-radius:10px;
  padding:1.4rem; width:min(440px,92vw);
  box-shadow:0 8px 32px rgba(0,0,0,.5);
  display:flex; flex-direction:column; gap:.85rem;
}
.pb-share-dialog h2 { font-family:'DINBlack',sans-serif; font-size:.95rem; margin:0; color:#e0e6f0; }
.pb-share-url-row {
  display:flex; gap:.5rem; align-items:center;
}
.pb-share-url {
  flex:1; background:#060d13; border:1px solid rgba(22,163,74,.2);
  border-radius:6px; padding:.55rem .75rem;
  font-family:monospace; font-size:.78rem; color:#4ade80;
  word-break:break-all; min-height:2.4rem;
}
.pb-copy-btn {
  font-family:'DINBlack',sans-serif; font-size:.7rem;
  text-transform:uppercase; letter-spacing:.05em;
  background:rgba(22,163,74,.2); color:#4ade80;
  border:1px solid rgba(22,163,74,.3); border-radius:6px;
  padding:.45rem .9rem; cursor:pointer; transition:background .15s; white-space:nowrap;
}
.pb-copy-btn:hover { background:rgba(22,163,74,.35); }
.pb-share-note { font-size:.75rem; color:#3f4f5e; }

/* ── Content ─────────────────────────────────────────── */
.content { max-width:1100px; margin:0 auto; padding:1.25rem 1rem 3rem; }

.empty-state {
  text-align:center; padding:4rem 1rem; color:#4a5568; font-size:.9rem;
}
.empty-state a {
  color:#16a34a; text-decoration:none; font-family:'DINBlack',sans-serif;
  font-size:.8rem; text-transform:uppercase; letter-spacing:.06em;
}

/* ── Group section ───────────────────────────────────── */
.group-section { margin-bottom:1.5rem; }
.group-header {
  display:flex; align-items:center; gap:.75rem;
  padding:.55rem 0 .55rem;
  margin-bottom:.65rem;
  border-bottom:1px solid rgba(22,163,74,.15);
}
.group-name {
  font-family:'DINBlack',sans-serif; font-size:.82rem;
  text-transform:uppercase; letter-spacing:.07em; color:#4ade80; flex:1;
}
.group-count {
  font-family:'DINBlack',sans-serif; font-size:.68rem;
  text-transform:uppercase; letter-spacing:.05em;
  color:#4a5568; white-space:nowrap;
}

/* ── Machine grid ────────────────────────────────────── */
.machine-grid {
  display:grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap:.65rem;
  align-items: start;
}

/* ── Machine card ────────────────────────────────────── */
.machine-card {
  background:#141f2c; border:1px solid rgba(255,255,255,.07);
  border-radius:10px; overflow:hidden;
  cursor:pointer;
  transition:border-color .15s, background .15s;
}
.machine-card:hover { background:#172030; border-color:rgba(22,163,74,.25); }
.machine-card.expanded { border-color:rgba(22,163,74,.35); }

/* ── Card header ─────────────────────────────────────── */
.machine-header {
  display:flex; align-items:center; gap:.6rem;
  padding:.65rem .85rem;
}
.machine-header.status-online  { background:rgba(22,163,74,.22); }
.machine-header.status-offline { background:rgba(220,38,38,.18); }
.machine-header.status-unknown { background:rgba(107,114,128,.12); }

.status-dot {
  width:9px; height:9px; border-radius:50%; flex-shrink:0;
}
.status-online  .status-dot { background:#16a34a; box-shadow:0 0 6px rgba(22,163,74,.7); }
.status-offline .status-dot { background:#dc2626; box-shadow:0 0 6px rgba(220,38,38,.5); }
.status-unknown .status-dot { background:#6b7280; }

.machine-exhibit {
  font-family:'DINBlack',sans-serif; font-size:.88rem;
  color:#e0e6f0; flex:1; min-width:0;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.status-label {
  font-family:'DINBlack',sans-serif; font-size:.62rem;
  text-transform:uppercase; letter-spacing:.05em;
  border-radius:4px; padding:.1rem .4rem; flex-shrink:0;
}
.status-online  .status-label { color:#4ade80; background:rgba(22,163,74,.2); }
.status-offline .status-label { color:#f87171; background:rgba(220,38,38,.15); }
.status-unknown .status-label { color:#9ca3af; background:rgba(107,114,128,.2); }

.card-chevron {
  color:rgba(255,255,255,.3); font-size:.7rem; flex-shrink:0;
  transition:transform .22s ease;
}
.machine-card.expanded .card-chevron { transform:rotate(180deg); color:rgba(255,255,255,.6); }

/* ── Card summary (always visible) ──────────────────── */
.card-summary { padding:.65rem .85rem; }

.quick-stats {
  display:flex; gap:.5rem; margin-bottom:.55rem; justify-content:space-between;
}
.qs-pill {
  display:flex; flex-direction:column;
  background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08);
  border-radius:5px; overflow:hidden; min-width:0;
  font-size:.72rem;
}
.qs-pill-top {
  display:flex; align-items:center; gap:.35rem;
  padding:.22rem .55rem .2rem; white-space:nowrap;
}
.qs-bar-track {
  height:3px; background:rgba(255,255,255,.06);
}
.qs-bar-fill {
  height:3px; transition:width .4s ease;
}
.qs-label {
  font-family:'DINBlack',sans-serif; font-size:.58rem;
  text-transform:uppercase; letter-spacing:.05em; color:#3f4f5e;
}
.qs-value { color:#94a3b8; }
.qs-value.warn { color:#fb923c; }

.card-footer {
  display:flex; justify-content:space-between; align-items:center;
  font-size:.68rem; color:#3f4f5e;
}
.crash-badge {
  font-family:'DINBlack',sans-serif; font-size:.62rem;
  background:rgba(220,38,38,.15); color:#f87171;
  border-radius:4px; padding:.1rem .45rem;
}
.crash-badge.zero { background:rgba(22,163,74,.08); color:#2d6a40; }

/* ── Card detail (expanded) ──────────────────────────── */
.card-detail {
  max-height:0; overflow:hidden;
  transition:max-height .25s ease;
}
.machine-card.expanded .card-detail { max-height:500px; }

.card-detail-inner {
  padding:.1rem .85rem .75rem;
  border-top:1px solid rgba(255,255,255,.05);
}
.detail-grid {
  display:grid; grid-template-columns:1fr 1fr; gap:.35rem .85rem;
  margin-top:.55rem;
}
.detail-item { display:flex; flex-direction:column; gap:.05rem; }
.detail-item.full { grid-column:1/-1; }
.detail-label {
  font-family:'DINBlack',sans-serif; font-size:.58rem;
  text-transform:uppercase; letter-spacing:.05em; color:#3f4f5e;
}
.detail-value { font-size:.75rem; color:#94a3b8; }
.detail-value.mono { font-family:monospace; }

/* ── Notes editor ───────────────────────────────────── */
.notes-wrap { margin-top:.5rem; }
.notes-textarea {
  width:100%; box-sizing:border-box;
  background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.1);
  border-radius:5px; color:#94a3b8; font-size:.75rem;
  font-family:'DINRegular',Arial,sans-serif;
  padding:.4rem .55rem; resize:vertical; min-height:52px;
  outline:none; transition:border-color .15s;
}
.notes-textarea:focus { border-color:rgba(22,163,74,.5); color:#e0e6f0; }
.notes-footer {
  display:flex; align-items:center; justify-content:flex-end;
  gap:.5rem; margin-top:.3rem;
}
.notes-status { font-size:.65rem; color:#3f4f5e; }
.notes-status.ok   { color:#4ade80; }
.notes-status.err  { color:#f87171; }
.notes-save-btn {
  font-family:'DINBlack',sans-serif; font-size:.65rem;
  background:rgba(22,163,74,.2); color:#4ade80;
  border:1px solid rgba(22,163,74,.3); border-radius:4px;
  padding:.2rem .6rem; cursor:pointer; transition:background .15s;
}
.notes-save-btn:hover { background:rgba(22,163,74,.35); }
.notes-save-btn:disabled { opacity:.4; cursor:default; }

/* ── No machines / search ────────────────────────────── */
.no-machines {
  font-size:.82rem; color:#3f4f5e; font-style:italic; padding:.5rem 0;
}
.search-bar {
  background:#0a1118;
  padding:.55rem 1.25rem;
  position:sticky; top:52px; z-index:99;
  border-bottom:1px solid rgba(22,163,74,.1);
}
.search-bar-inner { max-width:1100px; margin:0 auto; }
.search-input {
  width:100%; padding:.5rem .85rem;
  font-family:'DINRegular',Arial,sans-serif; font-size:.88rem; color:#e0e6f0;
  background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1);
  border-radius:7px; outline:none;
  transition:background .15s, border-color .15s;
}
.search-input::placeholder { color:rgba(255,255,255,.25); }
.search-input:focus { background:rgba(255,255,255,.1); border-color:rgba(22,163,74,.4); }
.no-results {
  text-align:center; padding:3rem 1rem; color:#3f4f5e; font-size:.88rem; display:none;
}
</style>
</head>
<body>

<!-- Top bar -->
<div class="top-bar">
  <div class="top-bar-inner">
    <div class="top-bar-left">
      <div class="top-bar-logo">
        <svg width="32" height="32" viewBox="0 0 180 180" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="180" height="180" rx="36" fill="#1a1a1a"/>
          <polyline points="8,90 42,90 52,38 68,138 82,58 98,90 132,90" stroke="#ef4444" stroke-width="10" stroke-linecap="round" stroke-linejoin="round"/>
          <line x1="132" y1="90" x2="148" y2="90" stroke="#ef4444" stroke-width="10" stroke-linecap="round"/>
          <circle cx="164" cy="90" r="16" fill="#16a34a"/>
        </svg>
      </div>
      <h1><span class="pb-pulse">Pulse</span><span class="pb-board">Board</span></h1>
    </div>
    <div class="top-bar-stat"><?= $_total_machines ?> machine<?= $_total_machines !== 1 ? 's' : '' ?></div>
    <div class="account-menu-wrap">
      <button class="pb-menu-btn" id="pbAccountBtn" onclick="toggleAccountMenu()" title="Menu">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
          <circle cx="12" cy="7.5" r="4.5"/>
          <path d="M3.5 21c0-4.14 3.81-7.5 8.5-7.5s8.5 3.36 8.5 7.5"/>
        </svg>
      </button>
      <div class="account-menu" id="pbAccountMenu">
        <button class="account-menu-item" onclick="accountMenuFetch()">Fetch</button>
        <!-- Share moved to per-group headers -->
        <a class="account-menu-item" href="pulseboard/help" style="text-decoration:none">Help</a>
      </div>
    </div>
  </div>
</div>

<!-- Search bar -->
<div class="search-bar">
  <div class="search-bar-inner">
    <input type="search" id="searchInput" class="search-input"
      placeholder="Search machines, hosts, IPs…"
      oninput="filterMachines(this.value)"
      autocomplete="off" spellcheck="false" />
  </div>
</div>

<!-- Fetch dialog -->
<div class="pb-fetch-overlay" id="pbFetchOverlay">
  <div class="pb-fetch-dialog">
    <h2>Fetch Machine Data</h2>
    <div class="pb-fetch-log" id="pbFetchLog"></div>
    <div class="pb-dialog-actions" style="margin-top:.25rem">
      <button class="pb-close-btn" id="pbFetchCloseBtn"
        onclick="document.getElementById('pbFetchOverlay').classList.remove('open')">Close</button>
    </div>
  </div>
</div>

<!-- Share dialog -->
<div class="pb-share-overlay" id="pbShareOverlay" onclick="if(event.target===this)this.classList.remove('open')">
  <div class="pb-share-dialog">
    <h2>Share This Dashboard</h2>
    <div class="pb-share-url-row">
      <div class="pb-share-url" id="pbShareUrl">Generating link…</div>
      <button class="pb-copy-btn" id="pbCopyBtn" onclick="copyShareUrl()">Copy</button>
    </div>
    <div class="pb-share-note">Anyone with this link can view machine statuses in read-only mode. No login required.</div>
    <div class="pb-dialog-actions" style="margin-top:.25rem">
      <button class="pb-close-btn" onclick="document.getElementById('pbShareOverlay').classList.remove('open')">Close</button>
    </div>
  </div>
</div>

<!-- Content -->
<div class="content">

<?php if (empty($_groups)): ?>
  <div class="empty-state">
    <p>No machine groups found.</p>
    <p style="margin-top:.5rem;"><a href="pulseboard">Set up PulseBoard →</a></p>
  </div>
<?php else: ?>

  <?php foreach ($_groups as $_g): ?>
  <div class="group-section" data-group="<?= htmlspecialchars(strtolower($_g['name'])) ?>">
    <div class="group-header">
      <span class="group-name"><?= htmlspecialchars($_g['name']) ?></span>
      <span class="group-count"><?= count($_g['machines']) ?> machine<?= count($_g['machines']) !== 1 ? 's' : '' ?></span>
      <button class="pb-group-share-btn" onclick="shareGroup(<?= htmlspecialchars(json_encode($_g['safe']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($_g['name']), ENT_QUOTES) ?>)">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/></svg>
        Share
      </button>
    </div>

    <?php if (empty($_g['machines'])): ?>
      <p class="no-machines">No machines in this group.</p>
    <?php else: ?>
    <div class="machine-grid">
      <?php foreach ($_g['machines'] as $_m): ?>
      <?php
        $_sc          = _pb_status_class($_m['status'] ?? '');
        $_exhibit     = htmlspecialchars($_m['exhibit']     ?? '');
        $_host        = htmlspecialchars($_m['host']        ?? '');
        $_ip          = htmlspecialchars($_m['ip']          ?? '');
        $_os          = htmlspecialchars($_m['os']          ?? '');
        $_memory      = htmlspecialchars($_m['memory']      ?? '');
        $_disk        = htmlspecialchars($_m['disk']        ?? '');
        $_uptime      = htmlspecialchars($_m['uptime']      ?? '');
        $_last_reboot = htmlspecialchars($_m['last_reboot'] ?? '');
        $_status_txt  = htmlspecialchars($_m['status']      ?? '');
        $_time        = htmlspecialchars($_m['time']        ?? '');
        $_crashes     = (int)($_m['crashes'] ?? 0);
        $_crash_times = htmlspecialchars($_m['crash_times'] ?? '');
        $_notes         = htmlspecialchars($_m['notes']         ?? '');
        $_teamviewer_id = htmlspecialchars($_m['teamviewer_id'] ?? '');

        [$_mem_pct,  $_mem_color]  = _pb_usage_bar($_memory);
        [$_disk_pct, $_disk_color] = _pb_usage_bar($_disk);

        $_search_blob = strtolower(implode(' ', array_values($_m)));
      ?>
      <div class="machine-card" data-search="<?= htmlspecialchars($_search_blob) ?>" data-tab="<?= htmlspecialchars($_g['name']) ?>" data-exhibit="<?= $_exhibit ?>" onclick="toggleCard(this)">

        <!-- Header -->
        <div class="machine-header <?= $_sc ?>">
          <span class="status-dot"></span>
          <span class="machine-exhibit"><?= $_exhibit ?: 'Unknown' ?></span>
          <?php if ($_status_txt): ?>
          <span class="status-label"><?= $_status_txt ?></span>
          <?php endif; ?>
          <span class="card-chevron">▾</span>
        </div>

        <!-- Summary (always visible) -->
        <div class="card-summary">
          <div class="quick-stats">
            <?php if ($_memory): ?>
            <div class="qs-pill">
              <div class="qs-pill-top">
                <span class="qs-label">Mem</span>
                <span class="qs-value"><?= $_memory ?></span>
              </div>
              <div class="qs-bar-track">
                <div class="qs-bar-fill" style="width:<?= $_mem_pct ?>%;background:<?= $_mem_color ?>"></div>
              </div>
            </div>
            <?php endif; ?>
            <?php if ($_disk): ?>
            <div class="qs-pill">
              <div class="qs-pill-top">
                <span class="qs-label">Disk</span>
                <span class="qs-value"><?= $_disk ?></span>
              </div>
              <div class="qs-bar-track">
                <div class="qs-bar-fill" style="width:<?= $_disk_pct ?>%;background:<?= $_disk_color ?>"></div>
              </div>
            </div>
            <?php endif; ?>
            <?php if ($_uptime): ?>
            <div class="qs-pill">
              <div class="qs-pill-top">
                <span class="qs-label">Up</span>
                <span class="qs-value"><?= $_uptime ?> HRS</span>
              </div>
            </div>
            <?php endif; ?>
          </div>
          <div class="card-footer">
            <span><?= $_time ? 'Last seen: ' . $_time : '' ?></span>
            <span class="crash-badge <?= $_crashes === 0 ? 'zero' : '' ?>">
              <?= $_crashes ?> crash<?= $_crashes !== 1 ? 'es' : '' ?>
            </span>
          </div>
        </div>

        <!-- Detail (expanded on click) -->
        <div class="card-detail">
          <div class="card-detail-inner">
            <div class="detail-grid">
              <?php if ($_host): ?>
              <div class="detail-item">
                <span class="detail-label">Host</span>
                <span class="detail-value"><?= $_host ?></span>
              </div>
              <?php endif; ?>
              <?php if ($_ip): ?>
              <div class="detail-item">
                <span class="detail-label">IP</span>
                <span class="detail-value mono"><?= $_ip ?></span>
              </div>
              <?php endif; ?>
              <?php if ($_os): ?>
              <div class="detail-item full">
                <span class="detail-label">OS</span>
                <span class="detail-value"><?= $_os ?></span>
              </div>
              <?php endif; ?>
              <?php if ($_last_reboot): ?>
              <div class="detail-item">
                <span class="detail-label">Last Reboot</span>
                <span class="detail-value"><?= $_last_reboot ?></span>
              </div>
              <?php endif; ?>
              <?php if ($_teamviewer_id): ?>
              <div class="detail-item">
                <span class="detail-label">TeamViewer ID</span>
                <span class="detail-value mono"><?= $_teamviewer_id ?></span>
              </div>
              <?php endif; ?>
              <?php if ($_crash_times): ?>
              <div class="detail-item full">
                <span class="detail-label">Crash Times</span>
                <span class="detail-value mono"><?= $_crash_times ?></span>
              </div>
              <?php endif; ?>
              <div class="detail-item full notes-wrap" onclick="event.stopPropagation()">
                <span class="detail-label">Notes</span>
                <textarea class="notes-textarea" rows="2" placeholder="Add a note..."><?= $_notes ?></textarea>
                <div class="notes-footer">
                  <span class="notes-status"></span>
                  <button class="notes-save-btn" onclick="saveNote(this)">Save</button>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

<?php endif; ?>

  <div class="no-results" id="noResults">No machines match your search.</div>

</div><!-- /.content -->

<script>
(function() {
  'use strict';

  var APP_BASE = (function() {
    var b = document.querySelector('base');
    return b ? b.getAttribute('href') : '/';
  })();

  var SHEET_ID = <?= json_encode($_sheet_id) ?>;

  // ── Card expand / collapse (accordion) ───────────────
  window.toggleCard = function(card) {
    var wasExpanded = card.classList.contains('expanded');
    document.querySelectorAll('.machine-card.expanded').forEach(function(c) {
      c.classList.remove('expanded');
    });
    if (!wasExpanded) card.classList.add('expanded');
  };

  // ── Save note ─────────────────────────────────────────
  window.saveNote = function(btn) {
    var wrap    = btn.closest('.machine-card');
    var tab     = wrap.dataset.tab;
    var exhibit = wrap.dataset.exhibit;
    var ta      = btn.closest('.notes-wrap').querySelector('.notes-textarea');
    var status  = btn.closest('.notes-wrap').querySelector('.notes-status');
    btn.disabled = true;
    status.className = 'notes-status';
    status.textContent = 'Saving...';
    var body = 'tab='     + encodeURIComponent(tab)
             + '&exhibit=' + encodeURIComponent(exhibit)
             + '&notes='   + encodeURIComponent(ta.value);
    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_BASE + SHEET_ID + '/pulseboard/note');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
      var res; try { res = JSON.parse(xhr.responseText); } catch(e) { res = null; }
      btn.disabled = false;
      if (res && res.ok) {
        status.className = 'notes-status ok';
        status.textContent = 'Saved';
        setTimeout(function() { status.textContent = ''; }, 2500);
      } else {
        status.className = 'notes-status err';
        status.textContent = (res && res.error) ? res.error : 'Error';
      }
    };
    xhr.onerror = function() {
      btn.disabled = false;
      status.className = 'notes-status err';
      status.textContent = 'Network error';
    };
    xhr.send(body);
  };

  // ── Account menu ─────────────────────────────────────
  window.toggleAccountMenu = function() {
    document.getElementById('pbAccountMenu').classList.toggle('open');
  };
  function closeAccountMenu() {
    document.getElementById('pbAccountMenu').classList.remove('open');
  }
  document.addEventListener('click', function(e) {
    var wrap = document.querySelector('.account-menu-wrap');
    if (wrap && !wrap.contains(e.target)) closeAccountMenu();
  });

  window.shareGroup = function(tabSafe, tabName) {
    var urlEl   = document.getElementById('pbShareUrl');
    var copyBtn = document.getElementById('pbCopyBtn');
    urlEl.textContent   = 'Generating link…';
    copyBtn.textContent = 'Copy';
    document.getElementById('pbShareOverlay').classList.add('open');

    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_BASE + 'push/createSharePulseboard.php');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
      var res; try { res = JSON.parse(xhr.responseText); } catch(e) { res = null; }
      if (res && res.ok) {
        urlEl.textContent = window.location.origin + APP_BASE.replace(/\/$/, '') + '/share/' + res.token;
      } else {
        urlEl.textContent = 'Error generating link.';
      }
    };
    xhr.onerror = function() { urlEl.textContent = 'Network error.'; };
    xhr.send(
      'sheet_id='  + encodeURIComponent(SHEET_ID) +
      '&tab='      + encodeURIComponent(tabName) +
      '&tab_safe=' + encodeURIComponent(tabSafe)
    );
  };

  window.copyShareUrl = function() {
    var url = document.getElementById('pbShareUrl').textContent;
    var btn = document.getElementById('pbCopyBtn');
    navigator.clipboard.writeText(url).then(function() {
      btn.textContent = 'Copied!';
      setTimeout(function() { btn.textContent = 'Copy'; }, 2000);
    }).catch(function() {
      var ta = document.createElement('textarea');
      ta.value = url; ta.style.cssText = 'position:fixed;opacity:0';
      document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); btn.textContent = 'Copied!'; setTimeout(function() { btn.textContent = 'Copy'; }, 2000); } catch(e) {}
      document.body.removeChild(ta);
    });
  };

  window.accountMenuFetch = function() {
    closeAccountMenu();
    var logEl    = document.getElementById('pbFetchLog');
    var closeBtn = document.getElementById('pbFetchCloseBtn');
    logEl.innerHTML = '';
    closeBtn.disabled = true;
    document.getElementById('pbFetchOverlay').classList.add('open');
    _fetchLog('Connecting to spreadsheet…', 'info');

    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_BASE + 'push/fetchPulseboard.php');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
      var res; try { res = JSON.parse(xhr.responseText); } catch(e) { res = null; }
      closeBtn.disabled = false;
      if (!res) { _fetchLog('Invalid server response.', 'error'); return; }
      logEl.innerHTML = '';
      var lines = res.logs || [];
      var delay = 0;
      lines.forEach(function(line) {
        setTimeout(function() { _fetchLog(line.msg, line.type || 'info'); }, delay);
        delay += 80;
      });
      if (res.error && !res.ok) {
        setTimeout(function() { _fetchLog('Error: ' + res.error, 'error'); }, delay);
      }
      if (res.ok) {
        setTimeout(function() { window.location.reload(); }, delay + 600);
      }
    };
    xhr.onerror = function() {
      closeBtn.disabled = false;
      _fetchLog('Network error — could not reach server.', 'error');
    };
    xhr.send('id=' + encodeURIComponent(SHEET_ID));
  };

  function _fetchLog(msg, type) {
    var logEl = document.getElementById('pbFetchLog');
    var span  = document.createElement('span');
    span.className   = 'pb-log-line ' + (type || 'info');
    span.textContent = msg;
    logEl.appendChild(span);
    logEl.appendChild(document.createElement('br'));
    logEl.scrollTop  = logEl.scrollHeight;
  }

  // ── Search ────────────────────────────────────────────
  window.filterMachines = function(q) {
    var term  = q.trim().toLowerCase();
    var cards = document.querySelectorAll('.machine-card');
    var shown = 0;

    cards.forEach(function(card) {
      var blob    = (card.getAttribute('data-search') || '').toLowerCase();
      var visible = !term || blob.indexOf(term) !== -1;
      card.style.display = visible ? '' : 'none';
      if (visible) shown++;
    });

    document.querySelectorAll('.group-section').forEach(function(section) {
      var visibleInGroup = Array.from(section.querySelectorAll('.machine-card'))
        .some(function(c) { return c.style.display !== 'none'; });
      section.style.display = (!term || visibleInGroup) ? '' : 'none';
    });

    var nr = document.getElementById('noResults');
    if (nr) nr.style.display = (cards.length > 0 && shown === 0) ? 'block' : 'none';
  };

  // Auto-refresh every hour
  setTimeout(function() { location.reload(); }, 60 * 60 * 1000);
})();
</script>
</body>
</html>
