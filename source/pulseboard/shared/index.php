<?php
/**
 * PulseBoard shared view — read-only dashboard via share token.
 * URL: /share/{token}
 */
// Cache for 7 days — serves offline; location.reload() bypasses this for live refreshes
header('Cache-Control: max-age=604800');

$_bpFile = __DIR__ . '/../../../dotEnv.php';
if (file_exists($_bpFile)) { require_once $_bpFile; }

$_rp    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_bp    = rtrim($_ENV['BASE_PATH'] ?? '', '/');
$_rp_stripped = ($_bp !== '' && str_starts_with($_rp, $_bp))
    ? substr($_rp, strlen($_bp)) : $_rp;

preg_match('#^/share/([A-Za-z0-9]+)/?$#', $_rp_stripped, $_tm);
$_token = $_tm[1] ?? '';

$_share_file = __DIR__ . '/../../../shares/pulse-group-readonly/' . $_token . '.json';
if (!$_token || !file_exists($_share_file)) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>Not found</title></head><body style="font-family:sans-serif;padding:3rem;text-align:center"><h2>Share link not found or expired.</h2></body></html>';
    exit;
}

$_share     = json_decode(file_get_contents($_share_file), true) ?: [];
$_sheet_id  = $_share['sheet_id'] ?? '';
$_tab       = $_share['tab']      ?? '';
$_tab_safe  = $_share['tab_safe'] ?? '';
$_base      = $_bp . '/';

$_sheets_dir = __DIR__ . '/../../../sheets/' . $_sheet_id . '/';

$_groups = [];
$_cache_file = $_sheets_dir . 'pulseboard-' . $_tab_safe . '.json';
if (file_exists($_cache_file)) {
    $_cache = json_decode(file_get_contents($_cache_file), true) ?: [];
    $_groups[] = [
        'name'     => $_cache['tab'] ?? $_tab,
        'machines' => $_cache['machines'] ?? [],
    ];
}

$_total_machines = array_sum(array_map(fn($g) => count($g['machines']), $_groups));

$_app_name = trim(strtoupper($_tab) . ' Pulse');
$_icon_url = $_base . 'images/pb_icon_512.png';

function _pbs_status_class(string $s): string {
    $sl = strtolower(trim($s));
    if (in_array($sl, ['online','running','active','up','ok'])) return 'status-online';
    if (in_array($sl, ['offline','stopped','down','error','fail','failed'])) return 'status-offline';
    return 'status-unknown';
}

function _pbs_usage_bar(string $val): array {
    if (!preg_match('/^([\d.]+)\/([\d.]+)/', $val, $m)) return [0, ''];
    $free  = (float)$m[1]; $total = (float)$m[2];
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
.top-bar h1 { font-family:'DINBlack',sans-serif; font-size:1rem; margin:0; letter-spacing:.03em; }
.pb-pulse { color:#ef4444; }
.pb-board { color:#22c55e; }
.top-bar-stat {
  font-family:'DINBlack',sans-serif; font-size:.72rem;
  text-transform:uppercase; letter-spacing:.06em;
  background:rgba(22,163,74,.15); border:1px solid rgba(22,163,74,.25);
  border-radius:6px; padding:.3rem .75rem; white-space:nowrap; flex-shrink:0; color:#4ade80;
}
.shared-badge {
  font-family:'DINBlack',sans-serif; font-size:.65rem;
  text-transform:uppercase; letter-spacing:.06em;
  background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.15);
  border-radius:6px; padding:.25rem .6rem; color:rgba(255,255,255,.45);
  white-space:nowrap; flex-shrink:0;
}

/* ── Search bar ──────────────────────────────────────── */
.search-bar {
  background:#0a1118; padding:.55rem 1.25rem;
  position:sticky; top:52px; z-index:99;
  border-bottom:1px solid rgba(22,163,74,.1);
}
.search-bar-inner { max-width:1100px; margin:0 auto; }
.search-input {
  width:100%; padding:.5rem .85rem;
  font-family:'DINRegular',Arial,sans-serif; font-size:.88rem; color:#e0e6f0;
  background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1);
  border-radius:7px; outline:none; transition:background .15s, border-color .15s;
}
.search-input::placeholder { color:rgba(255,255,255,.25); }
.search-input:focus { background:rgba(255,255,255,.1); border-color:rgba(22,163,74,.4); }

/* ── Content ─────────────────────────────────────────── */
.content { max-width:1100px; margin:0 auto; padding:1.25rem 1rem 3rem; }
.empty-state { text-align:center; padding:4rem 1rem; color:#4a5568; font-size:.9rem; }

/* ── Group ───────────────────────────────────────────── */
.group-section { margin-bottom:1.5rem; }
.group-header {
  display:flex; align-items:center; gap:.75rem;
  padding:.55rem 0 .55rem; margin-bottom:.65rem;
  border-bottom:1px solid rgba(22,163,74,.15);
}
.group-name { font-family:'DINBlack',sans-serif; font-size:.82rem; text-transform:uppercase; letter-spacing:.07em; color:#4ade80; flex:1; }
.group-count { font-family:'DINBlack',sans-serif; font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:#4a5568; white-space:nowrap; }

/* ── Machine grid ────────────────────────────────────── */
.machine-grid {
  display:grid; grid-template-columns:repeat(auto-fill,minmax(300px,1fr));
  gap:.65rem; align-items:start;
}

/* ── Machine card ────────────────────────────────────── */
.machine-card {
  background:#141f2c; border:1px solid rgba(255,255,255,.07);
  border-radius:10px; overflow:hidden; cursor:pointer;
  transition:border-color .15s, background .15s;
}
.machine-card:hover { background:#172030; border-color:rgba(22,163,74,.25); }
.machine-card.expanded { border-color:rgba(22,163,74,.35); }

.machine-header { display:flex; align-items:center; gap:.6rem; padding:.65rem .85rem; }
.machine-header.status-online  { background:rgba(22,163,74,.22); }
.machine-header.status-offline { background:rgba(220,38,38,.18); }
.machine-header.status-unknown { background:rgba(107,114,128,.12); }

.status-dot { width:9px; height:9px; border-radius:50%; flex-shrink:0; }
.status-online  .status-dot { background:#16a34a; box-shadow:0 0 6px rgba(22,163,74,.7); }
.status-offline .status-dot { background:#dc2626; box-shadow:0 0 6px rgba(220,38,38,.5); }
.status-unknown .status-dot { background:#6b7280; }

.machine-exhibit { font-family:'DINBlack',sans-serif; font-size:.88rem; color:#e0e6f0; flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.status-label { font-family:'DINBlack',sans-serif; font-size:.62rem; text-transform:uppercase; letter-spacing:.05em; border-radius:4px; padding:.1rem .4rem; flex-shrink:0; }
.status-online  .status-label { color:#4ade80; background:rgba(22,163,74,.2); }
.status-offline .status-label { color:#f87171; background:rgba(220,38,38,.15); }
.status-unknown .status-label { color:#9ca3af; background:rgba(107,114,128,.2); }
.card-chevron { color:rgba(255,255,255,.3); font-size:.7rem; flex-shrink:0; transition:transform .22s ease; }
.machine-card.expanded .card-chevron { transform:rotate(180deg); color:rgba(255,255,255,.6); }

/* ── Card summary ────────────────────────────────────── */
.card-summary { padding:.65rem .85rem; }
.quick-stats { display:flex; gap:.5rem; margin-bottom:.55rem; justify-content:space-between; }
.qs-pill { display:flex; flex-direction:column; background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08); border-radius:5px; overflow:hidden; min-width:0; font-size:.72rem; }
.qs-pill-top { display:flex; align-items:center; gap:.35rem; padding:.22rem .55rem .2rem; white-space:nowrap; }
.qs-bar-track { height:3px; background:rgba(255,255,255,.06); }
.qs-bar-fill  { height:3px; transition:width .4s ease; }
.qs-label { font-family:'DINBlack',sans-serif; font-size:.58rem; text-transform:uppercase; letter-spacing:.05em; color:#3f4f5e; }
.qs-value { color:#94a3b8; }
.card-footer { display:flex; justify-content:space-between; align-items:center; font-size:.68rem; color:#3f4f5e; }
.crash-badge { font-family:'DINBlack',sans-serif; font-size:.62rem; background:rgba(220,38,38,.15); color:#f87171; border-radius:4px; padding:.1rem .45rem; }
.crash-badge.zero { background:rgba(22,163,74,.08); color:#2d6a40; }

/* ── Card detail ─────────────────────────────────────── */
.card-detail { max-height:0; overflow:hidden; transition:max-height .25s ease; }
.machine-card.expanded .card-detail { max-height:400px; }
.card-detail-inner { padding:.1rem .85rem .75rem; border-top:1px solid rgba(255,255,255,.05); }
.detail-grid { display:grid; grid-template-columns:1fr 1fr; gap:.35rem .85rem; margin-top:.55rem; }
.detail-item { display:flex; flex-direction:column; gap:.05rem; }
.detail-item.full { grid-column:1/-1; }
.detail-label { font-family:'DINBlack',sans-serif; font-size:.58rem; text-transform:uppercase; letter-spacing:.05em; color:#3f4f5e; }
.detail-value { font-size:.75rem; color:#94a3b8; }
.detail-value.mono { font-family:monospace; }

/* ── Notes (read-only) ───────────────────────────────── */
.notes-text { font-size:.75rem; color:#94a3b8; font-style:italic; padding:.35rem .55rem; background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.07); border-radius:5px; min-height:36px; }

.no-results { text-align:center; padding:3rem 1rem; color:#3f4f5e; font-size:.88rem; display:none; }
</style>
</head>
<body>

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
  </div>
</div>

<div class="search-bar">
  <div class="search-bar-inner">
    <input type="search" id="searchInput" class="search-input"
      placeholder="Search machines, hosts, IPs…"
      oninput="filterMachines(this.value)"
      autocomplete="off" spellcheck="false" />
  </div>
</div>

<div class="content">

<?php if (empty($_groups)): ?>
  <div class="empty-state"><p>No machine data found for this share link.</p></div>
<?php else: ?>

  <?php foreach ($_groups as $_g): ?>
  <div class="group-section" data-group="<?= htmlspecialchars(strtolower($_g['name'])) ?>">
    <div class="group-header">
      <span class="group-name"><?= htmlspecialchars($_g['name']) ?></span>
      <span class="group-count"><?= count($_g['machines']) ?> machine<?= count($_g['machines']) !== 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($_g['machines'])): ?>
      <p style="font-size:.82rem;color:#3f4f5e;font-style:italic;padding:.5rem 0">No machines in this group.</p>
    <?php else: ?>
    <div class="machine-grid">
      <?php foreach ($_g['machines'] as $_m): ?>
      <?php
        $_sc          = _pbs_status_class($_m['status'] ?? '');
        $_exhibit     = htmlspecialchars($_m['exhibit']      ?? '');
        $_host        = htmlspecialchars($_m['host']         ?? '');
        $_ip          = htmlspecialchars($_m['ip']           ?? '');
        $_os          = htmlspecialchars($_m['os']           ?? '');
        $_memory      = htmlspecialchars($_m['memory']       ?? '');
        $_disk        = htmlspecialchars($_m['disk']         ?? '');
        $_uptime      = htmlspecialchars($_m['uptime']       ?? '');
        $_last_reboot = htmlspecialchars($_m['last_reboot']  ?? '');
        $_status_txt  = htmlspecialchars($_m['status']       ?? '');
        $_time        = htmlspecialchars($_m['time']         ?? '');
        $_crashes     = (int)($_m['crashes'] ?? 0);
        $_crash_times = htmlspecialchars($_m['crash_times']  ?? '');
        $_user        = htmlspecialchars($_m['user']          ?? '');
        $_tv_id       = htmlspecialchars($_m['teamviewer_id'] ?? '');
        $_notes       = htmlspecialchars($_m['notes']        ?? '');

        [$_mem_pct,  $_mem_color]  = _pbs_usage_bar($_memory);
        [$_disk_pct, $_disk_color] = _pbs_usage_bar($_disk);
        $_search_blob = strtolower(implode(' ', array_values($_m)));
      ?>
      <div class="machine-card" data-search="<?= htmlspecialchars($_search_blob) ?>" onclick="toggleCard(this)">

        <div class="machine-header <?= $_sc ?>">
          <span class="status-dot"></span>
          <span class="machine-exhibit"><?= $_exhibit ?: 'Unknown' ?></span>
          <?php if ($_status_txt): ?><span class="status-label"><?= $_status_txt ?></span><?php endif; ?>
          <span class="card-chevron">▾</span>
        </div>

        <div class="card-summary">
          <div class="quick-stats">
            <?php if ($_memory): ?>
            <div class="qs-pill">
              <div class="qs-pill-top"><span class="qs-label">Mem</span><span class="qs-value"><?= $_memory ?></span></div>
              <div class="qs-bar-track"><div class="qs-bar-fill" style="width:<?= $_mem_pct ?>%;background:<?= $_mem_color ?>"></div></div>
            </div>
            <?php endif; ?>
            <?php if ($_disk): ?>
            <div class="qs-pill">
              <div class="qs-pill-top"><span class="qs-label">Disk</span><span class="qs-value"><?= $_disk ?></span></div>
              <div class="qs-bar-track"><div class="qs-bar-fill" style="width:<?= $_disk_pct ?>%;background:<?= $_disk_color ?>"></div></div>
            </div>
            <?php endif; ?>
            <?php if ($_uptime): ?>
            <div class="qs-pill">
              <div class="qs-pill-top"><span class="qs-label">Up</span><span class="qs-value"><?= preg_replace('/^(\d+:\d+):\d+$/', '$1', $_uptime) ?> HRS</span></div>
            </div>
            <?php endif; ?>
          </div>
          <div class="card-footer">
            <span><?= $_time ? 'Last seen: ' . $_time : '' ?></span>
            <span class="crash-badge <?= $_crashes === 0 ? 'zero' : '' ?>"><?= $_crashes ?> crash<?= $_crashes !== 1 ? 'es' : '' ?></span>
          </div>
        </div>

        <div class="card-detail">
          <div class="card-detail-inner">
            <div class="detail-grid">
              <?php if ($_host): ?><div class="detail-item"><span class="detail-label">Host</span><span class="detail-value"><?= $_host ?></span></div><?php endif; ?>
              <?php if ($_ip): ?><div class="detail-item"><span class="detail-label">IP</span><span class="detail-value mono"><?= $_ip ?></span></div><?php endif; ?>
              <?php if ($_user): ?><div class="detail-item"><span class="detail-label">User</span><span class="detail-value"><?= $_user ?></span></div><?php endif; ?>
              <?php if ($_os): ?><div class="detail-item full"><span class="detail-label">OS</span><span class="detail-value"><?= $_os ?></span></div><?php endif; ?>
              <?php if ($_last_reboot): ?><div class="detail-item"><span class="detail-label">Last Reboot</span><span class="detail-value"><?= $_last_reboot ?></span></div><?php endif; ?>
              <?php if ($_tv_id): ?><div class="detail-item"><span class="detail-label">TeamViewer ID</span><span class="detail-value mono"><?= $_tv_id ?></span></div><?php endif; ?>
              <?php if ($_crashes > 0): ?><div class="detail-item"><span class="detail-label">Crashes</span><span class="detail-value"><?= $_crashes ?></span></div><?php endif; ?>
              <?php if ($_crash_times): ?><div class="detail-item full"><span class="detail-label">Crash Times</span><span class="detail-value mono"><?= $_crash_times ?></span></div><?php endif; ?>
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
</div>

<script>
(function() {
  'use strict';

  window.toggleCard = function(card) {
    var wasExpanded = card.classList.contains('expanded');
    document.querySelectorAll('.machine-card.expanded').forEach(function(c) { c.classList.remove('expanded'); });
    if (!wasExpanded) card.classList.add('expanded');
  };

  window.filterMachines = function(q) {
    var term  = q.trim().toLowerCase();
    var cards = document.querySelectorAll('.machine-card');
    var shown = 0;
    cards.forEach(function(card) {
      var visible = !term || (card.getAttribute('data-search') || '').toLowerCase().indexOf(term) !== -1;
      card.style.display = visible ? '' : 'none';
      if (visible) shown++;
    });
    document.querySelectorAll('.group-section').forEach(function(s) {
      var vis = Array.from(s.querySelectorAll('.machine-card')).some(function(c) { return c.style.display !== 'none'; });
      s.style.display = (!term || vis) ? '' : 'none';
    });
    var nr = document.getElementById('noResults');
    if (nr) nr.style.display = (cards.length > 0 && shown === 0) ? 'block' : 'none';
  };

  // Online/offline cache strategy:
  // - Page has a PHP-generated timestamp baked in at render time.
  // - If the page is more than 60 s old and we're online, it came from HTTP cache → reload for fresh data.
  // - If offline, do nothing — the cached page is all we have.
  // - When connectivity returns, reload immediately.
  // - Also reload every hour so data stays current during long sessions.
  var _pageAge = Math.floor(Date.now() / 1000) - <?= time() ?>;
  if (navigator.onLine && _pageAge > 60) { location.reload(); }
  window.addEventListener('online', function() { location.reload(); });
  setTimeout(function() { location.reload(); }, 60 * 60 * 1000);
})();
</script>
</body>
</html>
