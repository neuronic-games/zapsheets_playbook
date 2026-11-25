<?php
/**
 * The Waiting Room — {base}waiting
 *
 * While the GameBoard Admin sheet's settings tab has WaitingRoomOn = TRUE, a new account waits here
 * (Users.Status = waiting) instead of starting its free week. The apps send people here when signing in
 * answers {waiting: true}. They're emailed when they're let in.
 *
 * An admin (Role = admin in the Admin sheet) who opens this page while signed in also sees who's waiting
 * (oldest first, and people let in to only some apps), picks people and apps, and clicks Invite: each gets a
 * free week of those apps (the others stay closed) and an email saying they can log in.
 */
$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$_base = preg_replace('#/waiting/?$#', '/', $_raw);
if ($_base === $_raw) $_base = '/';
if (substr($_base, -1) !== '/') $_base .= '/';
require_once dirname(__DIR__, 2) . '/dotEnv.php';
function e($s) { return htmlspecialchars((string) $s, ENT_QUOTES); }
$APPS = [
  ['PitchBoard', 'pitchboard_icon_180.png', 'Pitch to publishers', 'pitchboard', '#e7a35a'],
  ['DevBoard',   'db_icon_180.png',         'Playtest & develop',  'devboard',   '#49c4d4'],
  ['CardBoard',  'cardboard_icon_180.png',  'Design & print', 'cardboard', '#7b6cf6'],
  ['BillBoard',  'bb_icon_180.png',         'Show & sell',          'billboard', '#9ed29f'],
];
?><!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= e($_base) ?>" />
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>The Waiting Room — GameBoard</title>
<link rel="icon" type="image/png" sizes="32x32" href="images/gameboard_icon_32.png" />
<link rel="apple-touch-icon" sizes="180x180" href="images/gameboard_icon_180.png" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Fraunces:ital,opsz,wght@0,9..144,600;1,9..144,400&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
:root { --navy:#14132a; --red:#e5604f; --red-dark:#cc4e3e; --text:#1e293b; --mid:#475569; --muted:#94a3b8; --line:#e8e4dc; --paper:#fbf8f2; }
@font-face { font-family:'DINBlack'; src:url('fonts/DINBlack.woff2') format('woff2'), url('fonts/DINBlack.ttf') format('truetype'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'), url('fonts/DINMedium.ttf') format('truetype'); }
body { font-family:'Inter',system-ui,sans-serif; color:var(--text); background:var(--paper); line-height:1.6; min-height:100vh; display:flex; flex-direction:column; }
a { color:inherit; }
[hidden] { display:none !important; }
nav.gbn { background:#1a1a2e; color:#fff; padding:.75rem 1.25rem; box-shadow:inset 0 -3px 0 var(--red); }
.brand { display:inline-flex; align-items:center; gap:.65rem; text-decoration:none; color:#fff; }
.brand img { width:2.2rem; height:2.2rem; border-radius:7px; }
.brand b { display:block; font-family:'DINBlack',sans-serif; font-weight:400; font-size:1rem; letter-spacing:.03em; line-height:1.15; }
.brand b span { color:var(--red); }
.brand small { display:block; font-family:'DINRegular',sans-serif; font-size:.73rem; opacity:.6; }

.hero { position:relative; overflow:hidden; background:linear-gradient(180deg, #2a2148 0%, #221b3e 100%); color:#fff; text-align:center; padding:4.5rem 1.5rem 6.5rem; }
.hero::before { content:''; position:absolute; top:-200px; left:50%; transform:translateX(-50%); width:760px; height:760px; background:radial-gradient(circle closest-side, rgba(229,96,79,.22) 0%, transparent 65%); pointer-events:none; }
.hero > * { position:relative; }
.tag { display:inline-block; font-size:12px; font-weight:500; letter-spacing:.08em; text-transform:uppercase; color:#f3b4aa; border:1px solid rgba(243,180,170,.35); border-radius:999px; padding:5px 14px; margin-bottom:1.4rem; }
.hero h1 { font-family:'Fraunces',Georgia,serif; font-weight:600; font-size:clamp(2.1rem,5vw,3.2rem); line-height:1.12; letter-spacing:-.03em; margin:0 auto 1.1rem; }
.hero h1 em { font-style:italic; font-weight:400; color:var(--red); }
.hero p { color:rgba(255,255,255,.75); font-size:1.08rem; max-width:560px; margin:0 auto; }
.hourglass { width:64px; height:64px; margin:0 auto 1.3rem; display:block; color:#f3b4aa; animation:flip 4s ease-in-out infinite; }
@keyframes flip { 0%,40% { transform:rotate(0); } 50%,90% { transform:rotate(180deg); } 100% { transform:rotate(360deg); } }
@media (prefers-reduced-motion:reduce) { .hourglass { animation:none; } }

main { flex:1; width:100%; max-width:960px; margin:-3.5rem auto 0; padding:0 1.25rem 3rem; position:relative; }
.card { background:#fff; border:1px solid var(--line); border-radius:16px; box-shadow:0 10px 30px rgba(20,19,42,.08); padding:1.6rem 1.6rem 1.4rem; }
.card h2 { font-family:'Fraunces',Georgia,serif; font-weight:600; font-size:1.35rem; letter-spacing:-.01em; color:var(--navy); margin-bottom:.25rem; }
.card > p { color:var(--mid); font-size:.95rem; margin-bottom:1.2rem; }
.apps { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:.9rem; }
@media (max-width:820px) { .apps { grid-template-columns:repeat(2, minmax(0,1fr)); } }
@media (max-width:420px) { .apps { grid-template-columns:1fr; } }
.app { border:1px solid var(--line); border-top:3px solid var(--c); border-radius:12px; padding:1rem; display:flex; flex-direction:column; gap:.7rem; background:#fff; }
.app-hd { display:flex; align-items:center; gap:.7rem; }
.app-hd img { width:44px; height:44px; border-radius:11px; box-shadow:0 3px 8px rgba(0,0,0,.15); }
.app-hd b { display:block; font-family:'DINBlack',sans-serif; font-weight:400; font-size:1rem; letter-spacing:.02em; color:var(--navy); line-height:1.2; }
.app-hd small { display:block; font-size:.8rem; color:var(--muted); }
.app-links { display:flex; gap:.5rem; margin-top:auto; }
.app-links a { flex:1; text-align:center; text-decoration:none; font-family:'DINBlack',sans-serif; font-size:.68rem; text-transform:uppercase; letter-spacing:.07em; padding:.55rem .4rem; border-radius:8px; border:1.5px solid var(--line); color:var(--mid); transition:border-color .15s, color .15s, background .15s; }
.app-links a:hover { border-color:var(--c); color:var(--navy); }
.app-links a.primary { background:var(--navy); border-color:var(--navy); color:#fff; }
.app-links a.primary:hover { background:#2a2850; }
.note { margin-top:1.2rem; font-size:.88rem; color:var(--muted); text-align:center; }
.note a { color:var(--red-dark); font-weight:600; text-decoration:none; }

/* Admins: who's waiting */
.admin { margin-top:1.4rem; }
.admin table { width:100%; border-collapse:collapse; font-size:.9rem; }
.admin th { text-align:left; font-family:'DINBlack',sans-serif; font-weight:400; font-size:.66rem; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); padding:.4rem .5rem; border-bottom:1px solid var(--line); }
.admin td { padding:.55rem .5rem; border-bottom:1px solid #f1eee8; vertical-align:middle; }
.admin td.when { color:var(--muted); white-space:nowrap; font-size:.82rem; }
.admin td.ck, .admin th.ck { width:2rem; text-align:center; }
.admin input[type=checkbox] { width:16px; height:16px; accent-color:var(--red); cursor:pointer; vertical-align:middle; }
.admin tbody tr { cursor:pointer; }
.admin tbody tr:hover td { background:#fdfbf7; }
.admin tbody tr.on td { background:#fff5f2; }
.chip { display:inline-block; font-family:'DINBlack',sans-serif; font-size:.6rem; text-transform:uppercase; letter-spacing:.06em; padding:.18rem .45rem; border-radius:5px; margin:0 .25rem .15rem 0; color:#fff; background:var(--c); }
.wait { color:var(--muted); font-size:.82rem; }
.invite { display:flex; flex-wrap:wrap; align-items:center; gap:.6rem 1rem; margin-top:1rem; padding-top:1rem; border-top:1px solid var(--line); }
.invite .lab { font-family:'DINBlack',sans-serif; font-size:.66rem; text-transform:uppercase; letter-spacing:.08em; color:var(--muted); }
.appck { display:inline-flex; align-items:center; gap:.4rem; font-size:.88rem; border:1.5px solid var(--line); border-radius:8px; padding:.35rem .65rem .35rem .45rem; cursor:pointer; user-select:none; }
.appck img { width:20px; height:20px; border-radius:5px; }
.appck:has(input:checked) { border-color:var(--c); background:#fff; box-shadow:inset 0 0 0 1px var(--c); }
.invite .btn { margin-left:auto; }
.admin .empty { color:var(--muted); font-size:.92rem; padding:.6rem 0; }
.btn { font-family:'DINBlack',sans-serif; font-size:.68rem; text-transform:uppercase; letter-spacing:.07em; padding:.5rem .95rem; border-radius:8px; border:none; background:var(--red); color:#fff; cursor:pointer; }
.btn:hover { background:var(--red-dark); }
.btn:disabled { opacity:.55; cursor:default; }
.btn.ghost { background:none; border:1.5px solid var(--line); color:var(--mid); }
.admin-hd { display:flex; align-items:center; justify-content:space-between; gap:1rem; margin-bottom:.4rem; }
.admin-msg { font-size:.85rem; color:var(--mid); min-height:1.3em; margin-top:.5rem; }
.admin-msg.err { color:#b91c1c; }
footer { text-align:center; font-size:.8rem; color:var(--muted); padding:1.2rem; }
</style>
</head>
<body>
<nav class="gbn">
  <a class="brand" href="gameboard?home">
    <img src="images/gameboard_icon_180.png" alt="">
    <span><b>GAME<span>BOARD</span></b><small>Tools for board game makers</small></span>
  </a>
</nav>

<section class="hero">
  <svg class="hourglass" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    <path d="M16 6h32M16 58h32"/>
    <path d="M20 6v6c0 8 9 13 12 20 3-7 12-12 12-20V6M20 58v-6c0-8 9-13 12-20 3 7 12 12 12 20v6"/>
    <path d="M24 56c1-6 6-9 8-11 2 2 7 5 8 11z" fill="currentColor" stroke="none"/>
  </svg>
  <div class="tag">The Waiting Room</div>
  <h1>You’re <em>on the list</em></h1>
  <p>Thank you for creating your account. During this period we have limited new accounts on our site. You are added to our waitlist and will be notified when you may log in.</p>
</section>

<main>
  <div class="card">
    <h2>While you wait, look around</h2>
    <p>One GameBoard account opens all four apps. See what each one does, and how it works.</p>
    <div class="apps">
<?php foreach ($APPS as [$name, $icon, $what, $slug, $c]): ?>
      <div class="app" style="--c:<?= e($c) ?>">
        <div class="app-hd"><img src="images/<?= e($icon) ?>" alt=""><div><b><?= e($name) ?></b><small><?= e($what) ?></small></div></div>
        <div class="app-links"><a class="primary" href="<?= e($slug) ?>/features">Features</a><a href="<?= e($slug) ?>/help">Help</a></div>
      </div>
<?php endforeach; ?>
    </div>
    <div class="note">Already been let in? <a href="gameboard">Log in</a></div>
  </div>

  <div class="card admin" id="admin" hidden>
    <div class="admin-hd"><h2>Who’s waiting</h2><button type="button" class="btn ghost" id="adminReload">Refresh</button></div>
    <div id="adminList"></div>
    <div class="invite" id="adminInvite">
      <span class="lab">Invite to</span>
<?php foreach ($APPS as [$name, $icon, $what, $slug, $c]): ?>
      <label class="appck" style="--c:<?= e($c) ?>"><input type="checkbox" name="app" value="<?= e($slug) ?>"><img src="images/<?= e($icon) ?>" alt=""><?= e($name) ?></label>
<?php endforeach; ?>
      <button type="button" class="btn" id="adminGo" disabled>Invite</button>
    </div>
    <div class="admin-msg" id="adminMsg"></div>
  </div>
</main>
<footer>© <?= gmdate('Y') ?> ZapSheets</footer>

<script>
// Admins (Role = admin in the GameBoard Admin sheet) signed in on this browser see who's waiting, oldest first,
// pick people and apps, and invite them: a free week of those apps, and an email
(function() {
  var BASE = document.querySelector('base').getAttribute('href'), tok = '';
  try { tok = (JSON.parse(localStorage.getItem('cb_account') || 'null') || {}).token || ''; } catch (e) {}
  if (!tok) return;
  var COLORS = <?= json_encode(array_combine(array_column($APPS, 0), array_column($APPS, 4))) ?>;
  function $(id) { return document.getElementById(id); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function(c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function when(v) { var d = new Date(String(v).replace(' ', 'T') + 'Z'); return isNaN(d) ? esc(v) : d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }); }
  function post(f) {
    var fd = new FormData(); fd.append('cbt', tok); Object.keys(f).forEach(function(k) { fd.append(k, f[k]); });
    return fetch(BASE + 'push/gameboardAccount.php', { method: 'POST', body: fd }).then(function(r) { return r.json(); });
  }
  function msg(t, bad) { var m = $('adminMsg'); m.innerHTML = t || ''; m.classList.toggle('err', !!bad); }
  function picked() { return [].slice.call(document.querySelectorAll('#adminList tbody input:checked')).map(function(c) { return c.value; }); }
  function apps() { return [].slice.call(document.querySelectorAll('#adminInvite input:checked')).map(function(c) { return c.value; }); }
  function sync() {
    document.querySelectorAll('#adminList tbody tr').forEach(function(tr) { tr.classList.toggle('on', tr.querySelector('input').checked); });
    var all = $('ckAll'), n = picked().length, rows = document.querySelectorAll('#adminList tbody input').length;
    if (all) { all.checked = n > 0 && n === rows; all.indeterminate = n > 0 && n < rows; }
    $('adminGo').disabled = !n || !apps().length;
    $('adminGo').textContent = n > 1 ? 'Invite ' + n : 'Invite';
  }
  function show(users) {
    $('admin').hidden = false;
    $('adminInvite').hidden = !users.length;
    $('adminList').innerHTML = !users.length ? '<div class="empty">Nobody’s waiting.</div>' :
      '<table><thead><tr><th class="ck"><input type="checkbox" id="ckAll" title="Everyone"></th><th>Name</th><th>Email</th><th>Signed up</th><th>Invited to</th></tr></thead><tbody>' +
      users.map(function(u) {
        var has = (u.apps || []).map(function(a) { return '<span class="chip" style="--c:' + esc(COLORS[a] || '#888') + '">' + esc(a) + '</span>'; }).join('');
        return '<tr><td class="ck"><input type="checkbox" value="' + esc(u.email) + '"></td><td>' + (esc(u.name) || '—') + '</td><td>' + esc(u.email) +
          '</td><td class="when">' + when(u.created) + '</td><td>' + (has || '<span class="wait">Waiting</span>') + '</td></tr>';
      }).join('') + '</tbody></table>';
    sync();
  }
  function load() {
    post({ action: 'waitlist' }).then(function(r) { if (r && r.ok) show(r.users || []); else if (r && !r.not_admin && !r.need_auth) { $('admin').hidden = false; msg(esc(r.error || 'Couldn’t read the Waiting Room'), true); } })
      .catch(function() {});
  }
  $('adminReload').onclick = function() { msg(''); load(); };
  $('adminList').addEventListener('click', function(ev) {
    if (ev.target.id === 'ckAll') { document.querySelectorAll('#adminList tbody input').forEach(function(c) { c.checked = ev.target.checked; }); sync(); return; }
    var tr = ev.target.closest('tbody tr'); if (!tr) return;
    if (ev.target.tagName !== 'INPUT') { var c = tr.querySelector('input'); c.checked = !c.checked; }
    sync();
  });
  $('adminInvite').addEventListener('change', sync);
  $('adminGo').onclick = function() {
    var who = picked(), which = apps(), b = $('adminGo'); if (!who.length || !which.length) return;
    b.disabled = true; b.textContent = 'Inviting…'; msg('');
    post({ action: 'letin', emails: JSON.stringify(who), apps: JSON.stringify(which) }).then(function(r) {
      if (!r || !r.ok) throw new Error(r && r.error ? r.error : 'No response');
      show(r.users || []);
      var done = r.done || [], unmailed = done.filter(function(d) { return !d.mailed; });
      var t = done.length ? '✓ Invited ' + done.length + (done.length === 1 ? ' person' : ' people') + ' — we emailed ' + (unmailed.length ? 'all but ' + unmailed.map(function(d) { return esc(d.email); }).join(', ') + ' (let them know yourself)' : 'them') + '.' : 'Nobody new to invite — they already had those apps.';
      if ((r.errors || []).length) t += '<br>' + r.errors.map(esc).join('<br>');
      msg(t, unmailed.length > 0 || (r.errors || []).length > 0);
    }).catch(function(e) { msg(esc(e.message), true); sync(); });
  };
  load();
})();
</script>
</body>
</html>
