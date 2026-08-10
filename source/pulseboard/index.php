<?php
/**
 * PulseBoard setup landing page.
 * Owner enters their Google Sheet ID/URL, runs setupPulseboard.php,
 * and is redirected to the machine status dashboard.
 */

$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_base = preg_replace('#/pulseboard/?$#', '/', $_raw);
if (!$_base || $_base === $_raw) { $_base = '/'; }
if (substr($_base, -1) !== '/') { $_base .= '/'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>PulseBoard – Set Up Machine Monitoring</title>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg width='180' height='180' viewBox='0 0 180 180' fill='none' xmlns='http://www.w3.org/2000/svg'><rect width='180' height='180' rx='36' fill='%231a1a1a'/><polyline points='8,90 42,90 52,38 68,138 82,58 98,90 132,90' stroke='%23ef4444' stroke-width='10' stroke-linecap='round' stroke-linejoin='round'/><line x1='132' y1='90' x2='148' y2='90' stroke='%23ef4444' stroke-width='10' stroke-linecap='round'/><circle cx='164' cy='90' r='16' fill='%2316a34a'/></svg>" />
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2')  format('woff2'),url('fonts/DINBlack.ttf')  format('truetype'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf') format('truetype'); }

*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }

body {
  background: #f3f0eb;
  font-family: 'DINRegular', Arial, sans-serif;
  color: #1a1a1a;
  min-height: 100vh;
  display: flex;
  align-items: flex-start;
  justify-content: center;
  padding: 2.5rem 1rem 3rem;
}

.page { width:100%; max-width:520px; }

/* ── Branding ─────────────────────────────────────────────────── */
.brand { text-align:center; margin-bottom:2.25rem; }
.brand-icon {
  width:84px; height:84px; border-radius:20px;
  background:#1a1a1a; display:inline-flex;
  align-items:center; justify-content:center;
  margin-bottom:.9rem;
  box-shadow: 0 4px 20px rgba(0,0,0,.18);
}
.brand-icon svg { width:48px; height:48px; }
.brand-name {
  font-family:'DINBlack',sans-serif; font-size:2.1rem;
  text-transform:uppercase; letter-spacing:.09em; color:#111; line-height:1;
}
.brand-tagline { margin-top:.4rem; font-size:.88rem; color:#999; letter-spacing:.03em; }

/* ── Card ─────────────────────────────────────────────────────── */
.card {
  background:#fff; border-radius:16px;
  padding:2rem 2rem 2.25rem;
  box-shadow:0 2px 24px rgba(0,0,0,.08);
  margin-bottom:1rem;
}
.card-title {
  font-family:'DINBlack',sans-serif; font-size:1.05rem;
  text-transform:uppercase; letter-spacing:.07em; color:#111; margin-bottom:.8rem;
}
.instructions { font-size:.88rem; color:#555; line-height:1.65; margin-bottom:1.5rem; }
.instructions ol { padding-left:1.35rem; margin-top:.5rem; }
.instructions li { margin-bottom:.25rem; }
.instructions strong { color:#333; }

.copy-email-btn {
  display:inline-flex; align-items:center; gap:.28rem;
  font-family:'DINRegular',Arial,sans-serif; font-size:.75rem;
  color:#16a34a; background:rgba(22,163,74,.10);
  border:1px solid rgba(22,163,74,.30); border-radius:999px;
  padding:.1rem .45rem; cursor:pointer; vertical-align:middle;
  white-space:nowrap; transition:background .15s, color .15s;
}
.copy-email-btn:hover { background:rgba(22,163,74,.22); }
.copy-email-btn.copied { color:#16a34a; background:rgba(22,163,74,.18); border-color:rgba(22,163,74,.4); }

/* ── Input ────────────────────────────────────────────────────── */
.sheet-input {
  display:block; width:100%; padding:.7rem .9rem;
  font-family:'DINRegular',Arial,sans-serif; font-size:.95rem; color:#111;
  border:1.5px solid #d0ccc5; border-radius:9px; outline:none;
  transition:border-color .15s; margin-bottom:1rem; background:#fafaf8;
}
.sheet-input:focus { border-color:#16a34a; background:#fff; }
.sheet-input.shake { animation:shake .3s ease-in-out; }

@keyframes shake {
  0%,100% { transform:translateX(0); }
  25%      { transform:translateX(-7px); }
  75%      { transform:translateX( 7px); }
}

/* ── Buttons ──────────────────────────────────────────────────── */
.connect-btn {
  width:100%; height:2.85rem;
  background:#16a34a; color:#fff;
  font-family:'DINBlack',sans-serif; font-size:.9rem;
  text-transform:uppercase; letter-spacing:.09em;
  border:none; border-radius:9px; cursor:pointer;
  transition:background .15s, transform .1s, box-shadow .15s;
  box-shadow:0 2px 8px rgba(22,163,74,.3);
}
.connect-btn:hover:not(:disabled) { background:#15803d; transform:translateY(-1px); box-shadow:0 4px 14px rgba(22,163,74,.35); }
.connect-btn:active:not(:disabled) { transform:translateY(0); }
.connect-btn:disabled { opacity:.5; cursor:default; transform:none; box-shadow:none; }

/* ── Log ──────────────────────────────────────────────────────── */
.log-panel {
  margin-top:1.25rem; background:#0d1117; border-radius:9px;
  padding:.9rem 1rem; max-height:220px; overflow-y:auto;
  font-family:'Courier New',Courier,monospace; font-size:.79rem; line-height:1.8;
  scrollbar-width:thin; scrollbar-color:#333 transparent;
}
.log-panel::-webkit-scrollbar { width:4px; }
.log-panel::-webkit-scrollbar-thumb { background:#333; border-radius:2px; }
.ll     { display:block; white-space:pre-wrap; }
.ll.inf { color:#8b9bba; }
.ll.ok  { color:#4ade80; }
.ll.err { color:#ff8a80; }

</style>
</head>
<body>
<div class="page">

  <!-- Branding -->
  <div class="brand">
    <div class="brand-icon">
      <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
        <polyline points="2,24 11,24 14,10 18,36 22,16 26,24 35,24" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
        <line x1="35" y1="24" x2="38" y2="24" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round"/>
        <circle cx="42" cy="24" r="4.5" fill="#22c55e"/>
      </svg>
    </div>
    <div class="brand-name"><span style="color:#ef4444">Pulse</span><span style="color:#22c55e">Board</span></div>
    <div class="brand-tagline">Monitor your machines in real time</div>
  </div>

  <!-- Setup card -->
  <div class="card">
    <div class="card-title">Connect Your Google Sheet</div>
    <div class="instructions">
      Create a Google Spreadsheet with one tab per machine group. Each row is a machine with columns for Exhibit, Host, IP, Status, Memory, Storage, Time, and more. Share the sheet with the PulseBoard service account, then paste the URL or ID below.
      <ol>
        <li>Open <strong>Google Sheets</strong> and set up your machine group tabs.</li>
        <li>Click <strong>Share</strong> and add <code>editor@zapsheets-480701.iam.gserviceaccount.com</code> <button class="copy-email-btn" id="copyEmailBtn" onclick="copyEmail()">Copy</button> with <strong>Editor</strong> rights.</li>
        <li>Copy the spreadsheet URL (or just the ID) and paste it below.</li>
      </ol>
    </div>

    <input type="text" id="sheetInput" class="sheet-input"
      placeholder="https://docs.google.com/spreadsheets/d/… or sheet ID"
      onkeydown="if(event.key==='Enter')startSetup()"
      autocomplete="off" spellcheck="false" />

    <button id="connectBtn" class="connect-btn" onclick="startSetup()">
      Set Up PulseBoard
    </button>

    <div id="logPanel" class="log-panel" style="display:none"></div>
  </div>

</div><!-- /.page -->

<script>
(function() {
  'use strict';

  var APP_BASE = (function() {
    var b = document.querySelector('base');
    return b ? b.getAttribute('href') : '/';
  })();

  window.copyEmail = function() {
    var email = 'editor@zapsheets-480701.iam.gserviceaccount.com';
    var btn   = document.getElementById('copyEmailBtn');
    navigator.clipboard.writeText(email).then(function() {
      btn.textContent = 'Copied!'; btn.classList.add('copied');
      setTimeout(function() { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 2000);
    }).catch(function() {
      var ta = document.createElement('textarea');
      ta.value = email; ta.style.position = 'fixed'; ta.style.opacity = '0';
      document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); btn.textContent = 'Copied!'; btn.classList.add('copied');
        setTimeout(function() { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 2000);
      } catch(e) {}
      document.body.removeChild(ta);
    });
  };

  function extractSheetId(raw) {
    raw = (raw || '').trim();
    var m = raw.match(/\/spreadsheets\/d\/([A-Za-z0-9_\-]+)/);
    if (m) return m[1];
    if (/^[A-Za-z0-9_\-]{10,}$/.test(raw)) return raw;
    return null;
  }

  function addLog(msg, cls) {
    var panel = document.getElementById('logPanel');
    var el    = document.createElement('span');
    el.className   = 'll ' + (cls || 'inf');
    el.textContent = msg;
    panel.appendChild(el);
    panel.scrollTop = panel.scrollHeight;
  }

  window.startSetup = function() {
    var inp     = document.getElementById('sheetInput');
    var btn     = document.getElementById('connectBtn');
    var panel   = document.getElementById('logPanel');
    var sheetId = extractSheetId(inp.value);

    if (!sheetId) {
      inp.classList.remove('shake');
      void inp.offsetWidth;
      inp.classList.add('shake');
      inp.focus();
      setTimeout(function() { inp.classList.remove('shake'); }, 400);
      return;
    }

    btn.disabled    = true;
    btn.textContent = 'Setting up…';
    panel.innerHTML = '';
    panel.style.display = '';

    addLog('Connecting to Google Sheet…', 'inf');

    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_BASE + 'push/setupPulseboard.php');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onload = function() {
      var result;
      try { result = JSON.parse(xhr.responseText); } catch(e) { result = null; }

      if (!result || result.error) {
        var errMsg = (result && result.error) || xhr.responseText || 'Unknown error';
        var isPermErr = errMsg.indexOf('403') !== -1
                     || errMsg.toLowerCase().indexOf('permission') !== -1
                     || errMsg.toLowerCase().indexOf('could not open') !== -1;
        if (isPermErr) {
          addLog('✕  This sheet has not been shared with the PulseBoard service account.', 'err');
          addLog('   Please share it with editor@zapsheets-480701.iam.gserviceaccount.com (Editor access) and try again.', 'err');
        } else {
          addLog('✕  ' + errMsg, 'err');
        }
        btn.disabled    = false;
        btn.textContent = 'Set Up PulseBoard';
        return;
      }

      var tabs = result.tabs || [];
      tabs.forEach(function(t) { addLog('✓  "' + t + '" tab loaded', 'ok'); });
      addLog('✓  PulseBoard ready — opening…', 'ok');
      setTimeout(function() {
        window.location.href = APP_BASE + sheetId + '/pulseboard';
      }, 1000);
    };

    xhr.onerror = function() {
      addLog('✕  Network error — could not reach the server.', 'err');
      btn.disabled    = false;
      btn.textContent = 'Set Up PulseBoard';
    };

    xhr.send('id=' + encodeURIComponent(sheetId));
  };
})();
</script>
</body>
</html>
