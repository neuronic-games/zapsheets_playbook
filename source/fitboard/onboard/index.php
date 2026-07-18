<?php
/**
 * FitBoard onboarding page.
 * Shown after a new sheet is set up — collects fitness goals & stats,
 * generates 3 months of workout data, then opens FitBoard.
 *
 * URL pattern: /{sheet_id}/fitboard/onboard
 */

$_raw = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Extract sheet ID and base path from URL
$_sheetId = '';
$_base    = '/';
if (preg_match('#^(.*)/([A-Za-z0-9_\-]+)/fitboard/onboard/?$#', $_raw, $_m)) {
    $_base    = ($_m[1] !== '') ? rtrim($_m[1], '/') . '/' : '/';
    $_sheetId = $_m[2];
}
if (substr($_base, -1) !== '/') { $_base .= '/'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>FitBoard – Build Your Plan</title>
<link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars($_base) ?>images/fb_icon_180.png" />
<link rel="icon" type="image/png" sizes="192x192" href="<?= htmlspecialchars($_base) ?>images/fb_icon_192.png" />
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2')  format('woff2'),url('fonts/DINBlack.ttf')  format('truetype'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf') format('truetype'); }

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  background: #0f0f14;
  font-family: 'DINRegular', Arial, sans-serif;
  color: #fff;
  min-height: 100vh;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 2rem 1rem 4rem;
}

.page {
  width: 100%;
  max-width: 520px;
}

/* ── Branding ─────────────────────────────────────────────────── */
.brand {
  text-align: center;
  margin-bottom: 2rem;
}
.brand-logo {
  width: 72px;
  height: 72px;
  object-fit: contain;
  margin-bottom: .75rem;
  border-radius: 18px;
  box-shadow: 0 4px 24px rgba(10,132,255,.35);
}
.brand-name {
  font-family: 'DINBlack', sans-serif;
  font-size: 1.8rem;
  text-transform: uppercase;
  letter-spacing: .09em;
  color: #fff;
  line-height: 1;
}
.brand-tagline {
  margin-top: .35rem;
  font-size: .85rem;
  color: rgba(255,255,255,.40);
  letter-spacing: .03em;
}

/* ── Card ─────────────────────────────────────────────────────── */
.card {
  background: #1c1c28;
  border: 1px solid #2a2a3a;
  border-radius: 16px;
  padding: 1.75rem 1.75rem 2rem;
  box-shadow: 0 4px 32px rgba(0,0,0,.45);
  margin-bottom: 1rem;
}
.card-title {
  font-family: 'DINBlack', sans-serif;
  font-size: 1.35rem;
  color: #fff;
  margin-bottom: .35rem;
  letter-spacing: .02em;
}
.card-sub {
  font-size: .85rem;
  color: rgba(255,255,255,.42);
  margin-bottom: 1.75rem;
  line-height: 1.55;
}

/* ── Section label ────────────────────────────────────────────── */
.section-label {
  font-family: 'DINBlack', sans-serif;
  font-size: .72rem;
  text-transform: uppercase;
  letter-spacing: .10em;
  color: rgba(255,255,255,.40);
  margin-bottom: .55rem;
}

/* ── Pill button groups ───────────────────────────────────────── */
.pill-group {
  display: flex;
  flex-wrap: wrap;
  gap: .5rem;
  margin-bottom: 1.5rem;
}

.pill {
  display: flex;
  align-items: center;
  gap: .4rem;
  padding: .55rem 1rem;
  border-radius: 999px;
  border: 1.5px solid #2e2e40;
  background: #12121c;
  color: rgba(255,255,255,.60);
  font-family: 'DINRegular', Arial, sans-serif;
  font-size: .88rem;
  cursor: pointer;
  transition: border-color .15s, background .15s, color .15s;
  user-select: none;
  white-space: nowrap;
}
.pill:hover {
  border-color: rgba(10,132,255,.5);
  color: rgba(255,255,255,.85);
}
.pill.selected {
  border-color: #0a84ff;
  background: rgba(10,132,255,.15);
  color: #fff;
}
.pill-icon {
  font-size: 1rem;
  line-height: 1;
}

/* ── Phase legend ─────────────────────────────────────────────── */
.phase-legend {
  display: flex;
  gap: .5rem;
  margin-bottom: 1.75rem;
  flex-wrap: wrap;
}
.phase-pill {
  display: flex;
  align-items: center;
  gap: .4rem;
  padding: .35rem .8rem;
  border-radius: 999px;
  font-size: .78rem;
  color: rgba(255,255,255,.55);
  background: rgba(255,255,255,.05);
  border: 1px solid rgba(255,255,255,.08);
}
.phase-dot {
  width: 7px;
  height: 7px;
  border-radius: 50%;
  flex-shrink: 0;
}

/* ── Generate button ──────────────────────────────────────────── */
.gen-btn {
  width: 100%;
  height: 3rem;
  background: #0a84ff;
  color: #fff;
  font-family: 'DINBlack', sans-serif;
  font-size: .92rem;
  text-transform: uppercase;
  letter-spacing: .09em;
  border: none;
  border-radius: 10px;
  cursor: pointer;
  transition: background .15s, transform .1s, box-shadow .15s;
  box-shadow: 0 2px 14px rgba(10,132,255,.4);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .5rem;
}
.gen-btn:hover:not(:disabled) {
  background: #0070d8;
  transform: translateY(-1px);
  box-shadow: 0 4px 20px rgba(10,132,255,.5);
}
.gen-btn:active:not(:disabled) { transform: translateY(0); }
.gen-btn:disabled {
  opacity: .45;
  cursor: default;
  transform: none;
  box-shadow: none;
}

/* ── Skip link ────────────────────────────────────────────────── */
.skip-link {
  text-align: center;
  margin-top: 1rem;
  font-size: .82rem;
  color: rgba(255,255,255,.30);
}
.skip-link a {
  color: rgba(255,255,255,.40);
  text-decoration: none;
  border-bottom: 1px solid rgba(255,255,255,.15);
  transition: color .15s;
}
.skip-link a:hover { color: rgba(255,255,255,.65); }

/* ── Log panel ────────────────────────────────────────────────── */
.log-panel {
  background: #080810;
  border: 1px solid #1e1e2e;
  border-radius: 10px;
  padding: .9rem 1rem;
  max-height: 200px;
  overflow-y: auto;
  font-family: 'Courier New', Courier, monospace;
  font-size: .78rem;
  line-height: 1.9;
  margin-top: 1rem;
  scrollbar-width: thin;
  scrollbar-color: #2a2a3a transparent;
}
.log-panel::-webkit-scrollbar { width: 4px; }
.log-panel::-webkit-scrollbar-thumb { background: #2a2a3a; border-radius: 2px; }
.ll       { display: block; white-space: pre-wrap; }
.ll.inf   { color: rgba(255,255,255,.45); }
.ll.ok    { color: #30d158; }
.ll.err   { color: #ff375f; }
.ll.warn  { color: #ffd60a; }

/* ── Body stats (weight + age) ────────────────────────────────── */
.stats-row {
  display: flex;
  gap: .65rem;
  margin-bottom: 1.75rem;
}
.stat-group { flex: 1; }
.stat-label {
  font-size: .7rem;
  color: rgba(255,255,255,.35);
  margin-bottom: .3rem;
  font-family: 'DINBlack', sans-serif;
  text-transform: uppercase;
  letter-spacing: .09em;
}
.stat-input-row {
  display: flex;
  border: 1.5px solid #2a2a3a;
  border-radius: 9px;
  overflow: hidden;
  background: #12121c;
  transition: border-color .15s;
}
.stat-input-row:focus-within { border-color: #0a84ff; }
.stat-input {
  flex: 1;
  min-width: 0;
  padding: .62rem .85rem;
  font-family: 'DINRegular', Arial, sans-serif;
  font-size: .95rem;
  color: #fff;
  background: transparent;
  border: none;
  outline: none;
  -moz-appearance: textfield;
}
.stat-input::-webkit-inner-spin-button,
.stat-input::-webkit-outer-spin-button { -webkit-appearance: none; }
.unit-toggle-inner {
  display: flex;
  border-left: 1.5px solid #2a2a3a;
}
.unit-btn {
  padding: .3rem .55rem;
  font-family: 'DINBlack', sans-serif;
  font-size: .68rem;
  text-transform: uppercase;
  letter-spacing: .07em;
  background: transparent;
  color: rgba(255,255,255,.3);
  border: none;
  cursor: pointer;
  transition: background .12s, color .12s;
  white-space: nowrap;
}
.unit-btn + .unit-btn { border-left: 1px solid rgba(255,255,255,.08); }
.unit-btn.active {
  background: rgba(10,132,255,.18);
  color: #0a84ff;
}

/* ── Spinner ──────────────────────────────────────────────────── */
.spinner {
  width: 16px; height: 16px;
  border: 2px solid rgba(255,255,255,.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin .7s linear infinite;
  flex-shrink: 0;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>
</head>
<body>

<div class="page">

  <!-- Branding -->
  <div class="brand">
    <img class="brand-logo" src="images/fb_icon_180.png" alt="FitBoard" />
    <div class="brand-name">FitBoard</div>
    <div class="brand-tagline">Let's build your 3-month plan</div>
  </div>

  <!-- Form card -->
  <div class="card" id="formCard">
    <div class="card-title">What are your goals?</div>
    <div class="card-sub">Answer a few quick questions and we'll generate a personalised 12-week workout plan in your Google Sheet.</div>

    <!-- Goal -->
    <div class="section-label">Primary goal</div>
    <div class="pill-group" id="goalGroup">
      <div class="pill selected" data-value="general">
        <span class="pill-icon">⚡</span> Stay Active
      </div>
      <div class="pill" data-value="hypertrophy">
        <span class="pill-icon">💪</span> Build Muscle
      </div>
      <div class="pill" data-value="strength">
        <span class="pill-icon">🏋️</span> Get Stronger
      </div>
      <div class="pill" data-value="weight_loss">
        <span class="pill-icon">🔥</span> Lose Weight
      </div>
    </div>

    <!-- Days per week -->
    <div class="section-label">Days per week</div>
    <div class="pill-group" id="daysGroup">
      <div class="pill" data-value="3">
        <span class="pill-icon">📅</span> 3 Days
      </div>
      <div class="pill selected" data-value="4">
        <span class="pill-icon">📅</span> 4 Days
      </div>
      <div class="pill" data-value="5">
        <span class="pill-icon">📅</span> 5 Days
      </div>
    </div>

    <!-- Experience level -->
    <div class="section-label">Experience level</div>
    <div class="pill-group" id="levelGroup">
      <div class="pill selected" data-value="beginner">
        🌱 Beginner
      </div>
      <div class="pill" data-value="intermediate">
        🏃 Intermediate
      </div>
      <div class="pill" data-value="advanced">
        🔥 Advanced
      </div>
    </div>

    <!-- Body stats -->
    <div class="section-label">Your stats <span style="font-family:'DINRegular',Arial,sans-serif;text-transform:none;letter-spacing:0;font-size:.8rem;color:rgba(255,255,255,.25);font-weight:normal">(optional — used to set target weights)</span></div>
    <div class="stats-row">
      <div class="stat-group">
        <div class="stat-label">Body weight</div>
        <div class="stat-input-row">
          <input type="number" id="weightInput" class="stat-input" placeholder="170" min="50" max="700" step="1" />
          <div class="unit-toggle-inner">
            <button class="unit-btn active" id="unitLbs" onclick="setUnit('lbs')">lbs</button>
            <button class="unit-btn"        id="unitKg"  onclick="setUnit('kg')">kg</button>
          </div>
        </div>
      </div>
      <div class="stat-group">
        <div class="stat-label">Age</div>
        <div class="stat-input-row">
          <input type="number" id="ageInput" class="stat-input" placeholder="30" min="13" max="99" step="1" />
        </div>
      </div>
    </div>

    <!-- Phase legend -->
    <div class="phase-legend">
      <div class="phase-pill"><div class="phase-dot" style="background:#0a84ff"></div> Weeks 1–4: Foundation</div>
      <div class="phase-pill"><div class="phase-dot" style="background:#30d158"></div> Weeks 5–8: Build</div>
      <div class="phase-pill"><div class="phase-dot" style="background:#ffd60a"></div> Weeks 9–12: Peak</div>
    </div>

    <!-- Generate -->
    <button id="genBtn" class="gen-btn" onclick="generate()">
      Generate My 3-Month Plan →
    </button>

    <div id="logPanel" class="log-panel" style="display:none"></div>
  </div>

  <!-- Skip link -->
  <div class="skip-link">
    Already have a plan? <a href="#" id="skipLink">Skip and open FitBoard →</a>
  </div>

</div><!-- /.page -->

<script>
(function () {
  'use strict';

  var APP_BASE = (function () {
    var b = document.querySelector('base');
    return b ? b.getAttribute('href') : '/';
  })();

  var SHEET_ID = <?= json_encode($_sheetId) ?>;

  // ── Skip link ─────────────────────────────────────────────────────────────
  document.getElementById('skipLink').addEventListener('click', function (e) {
    e.preventDefault();
    window.location.href = APP_BASE + SHEET_ID + '/fitboard?manual=1';
  });

  // ── Pill group selection ──────────────────────────────────────────────────
  function initPillGroup(groupId) {
    var group = document.getElementById(groupId);
    group.addEventListener('click', function (e) {
      var pill = e.target.closest('.pill');
      if (!pill) return;
      group.querySelectorAll('.pill').forEach(function (p) { p.classList.remove('selected'); });
      pill.classList.add('selected');
    });
  }
  initPillGroup('goalGroup');
  initPillGroup('daysGroup');
  initPillGroup('levelGroup');

  function getSelected(groupId) {
    var sel = document.querySelector('#' + groupId + ' .pill.selected');
    return sel ? sel.getAttribute('data-value') : null;
  }

  // ── Unit toggle ───────────────────────────────────────────────────────────
  var currentUnit = 'lbs';
  window.setUnit = function (unit) {
    currentUnit = unit;
    document.getElementById('unitLbs').classList.toggle('active', unit === 'lbs');
    document.getElementById('unitKg').classList.toggle('active',  unit === 'kg');
  };

  // ── Log helpers ───────────────────────────────────────────────────────────
  function addLog(msg, cls) {
    var panel = document.getElementById('logPanel');
    panel.style.display = '';
    var el = document.createElement('span');
    el.className   = 'll ' + (cls || 'inf');
    el.textContent = msg;
    panel.appendChild(el);
    panel.scrollTop = panel.scrollHeight;
  }

  // ── Generate ──────────────────────────────────────────────────────────────
  window.generate = function () {
    var goal  = getSelected('goalGroup');
    var days  = getSelected('daysGroup');
    var level = getSelected('levelGroup');
    var btn   = document.getElementById('genBtn');

    if (!goal || !days || !level) {
      addLog('⚠  Please complete all selections above.', 'warn');
      return;
    }

    btn.disabled = true;
    btn.innerHTML = '<div class="spinner"></div> Generating…';
    document.getElementById('logPanel').innerHTML = '';

    addLog('Building your ' + days + '-day-a-week ' + goal.replace('_', ' ') + ' plan…', 'inf');

    var weightRaw = parseFloat(document.getElementById('weightInput').value) || 0;
    var age       = parseInt(document.getElementById('ageInput').value, 10) || 0;
    var weightLbs = (currentUnit === 'kg') ? (weightRaw * 2.20462) : weightRaw;

    var body = 'id='          + encodeURIComponent(SHEET_ID)
             + '&goal='       + encodeURIComponent(goal)
             + '&days='       + encodeURIComponent(days)
             + '&level='      + encodeURIComponent(level)
             + '&weight_lbs=' + encodeURIComponent(weightLbs.toFixed(1))
             + '&age='        + encodeURIComponent(age);

    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_BASE + 'push/generateFitboardPlan.php');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onload = function () {
      var result;
      try { result = JSON.parse(xhr.responseText); } catch (e) { result = null; }

      if (!result || result.error) {
        addLog('✕  ' + ((result && result.error) || xhr.responseText || 'Unknown error'), 'err');
        btn.disabled = false;
        btn.innerHTML = 'Generate My 3-Month Plan →';
        return;
      }

      var sessions = result.sessions || (result.rows_written ? Math.round(result.rows_written / 6) : '?');
      var rows     = result.rows_written || '?';
      addLog('✓  ' + result.weeks + ' weeks generated (' + sessions + ' sessions, ' + rows + ' exercises)', 'ok');
      addLog('✓  Workout data saved to Google Sheet', 'ok');
      addLog('✓  Opening FitBoard…', 'ok');

      setTimeout(function () {
        window.location.href = APP_BASE + SHEET_ID + '/fitboard';
      }, 1400);
    };

    xhr.onerror = function () {
      addLog('✕  Network error — could not reach the server.', 'err');
      btn.disabled = false;
      btn.innerHTML = 'Generate My 3-Month Plan →';
    };

    xhr.send(body);
  };
})();
</script>

</body>
</html>
