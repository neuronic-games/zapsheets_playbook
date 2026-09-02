<?php
/**
 * DevBoard setup landing page.
 * Paste a Google Sheet ID/URL — DevBoard creates Games + Settings tabs
 * (or reuses them if linking to an existing PitchBoard sheet).
 */

$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_base = preg_replace('#/devboard/?$#', '/', $_raw);
if (!$_base || $_base === $_raw) { $_base = '/'; }
if (substr($_base, -1) !== '/') { $_base .= '/'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>DevBoard – Playtest Notes</title>
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2')  format('woff2'),url('fonts/DINBlack.ttf')  format('truetype'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf') format('truetype'); }

*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }

body {
  background:#f0f4f8;
  font-family:'DINRegular',Arial,sans-serif;
  color:#1a1a1a;
  min-height:100vh;
  display:flex;
  align-items:flex-start;
  justify-content:center;
  padding:2.5rem 1rem 3rem;
}
.page { width:100%; max-width:520px; }

/* Branding */
.brand { text-align:center; margin-bottom:2.25rem; }
.brand-icon {
  width:84px; height:84px; border-radius:20px;
  background:#1a5f7a; display:inline-flex;
  align-items:center; justify-content:center;
  margin-bottom:.9rem;
  box-shadow:0 4px 20px rgba(0,0,0,.18);
}
.brand-icon svg { width:44px; height:44px; }
.brand-name {
  font-family:'DINBlack',sans-serif; font-size:2.1rem;
  text-transform:uppercase; letter-spacing:.09em; color:#111; line-height:1;
}
.brand-tagline { margin-top:.4rem; font-size:.88rem; color:#999; letter-spacing:.03em; }

/* Card */
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
  color:#1a5f7a; background:rgba(26,95,122,.10);
  border:1px solid rgba(26,95,122,.30); border-radius:999px;
  padding:.1rem .45rem; cursor:pointer; vertical-align:middle;
  white-space:nowrap; transition:background .15s;
}
.copy-email-btn:hover { background:rgba(26,95,122,.22); }
.copy-email-btn.copied { color:#16a34a; background:rgba(22,163,74,.10); border-color:rgba(22,163,74,.30); }

/* Input */
.sheet-input {
  display:block; width:100%; padding:.7rem .9rem;
  font-family:'DINRegular',Arial,sans-serif; font-size:.95rem; color:#111;
  border:1.5px solid #d0ccc5; border-radius:9px; outline:none;
  transition:border-color .15s; margin-bottom:1rem; background:#fafaf8;
}
.sheet-input:focus { border-color:#1a5f7a; background:#fff; }
.sheet-input.shake { animation:shake .3s ease-in-out; }
@keyframes shake {
  0%,100% { transform:translateX(0); }
  25%      { transform:translateX(-7px); }
  75%      { transform:translateX( 7px); }
}

/* Button */
.connect-btn {
  width:100%; height:2.85rem;
  background:#1a5f7a; color:#fff;
  font-family:'DINBlack',sans-serif; font-size:.9rem;
  text-transform:uppercase; letter-spacing:.09em;
  border:none; border-radius:9px; cursor:pointer;
  transition:background .15s, transform .1s, box-shadow .15s;
  box-shadow:0 2px 8px rgba(26,95,122,.3);
}
.connect-btn:hover:not(:disabled) { background:#145070; transform:translateY(-1px); box-shadow:0 4px 14px rgba(26,95,122,.35); }
.connect-btn:active:not(:disabled) { transform:translateY(0); }
.connect-btn:disabled { opacity:.5; cursor:default; transform:none; box-shadow:none; }

/* Log */
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

  <div class="brand">
    <div class="brand-icon">
      <!-- clipboard + magnifier icon -->
      <svg viewBox="0 0 44 44" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="8" y="6" width="24" height="30" rx="3" fill="none" stroke="#fff" stroke-width="2.2"/>
        <rect x="15" y="3" width="10" height="5" rx="2" fill="#1a5f7a" stroke="#fff" stroke-width="1.8"/>
        <line x1="13" y1="16" x2="27" y2="16" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>
        <line x1="13" y1="21" x2="27" y2="21" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>
        <line x1="13" y1="26" x2="21" y2="26" stroke="#fff" stroke-width="1.8" stroke-linecap="round"/>
        <circle cx="33" cy="33" r="6" fill="none" stroke="#fff" stroke-width="2"/>
        <line x1="37.2" y1="37.2" x2="40" y2="40" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/>
      </svg>
    </div>
    <div class="brand-name">DevBoard</div>
    <div class="brand-tagline">Playtest notes for your games</div>
  </div>

  <div class="card">
    <div class="card-title">Connect Your Google Sheet</div>
    <div class="instructions">
      Link an existing PitchBoard sheet or create a new blank spreadsheet. DevBoard will set up Games and Settings tabs — per-game dev tabs are added in Google Sheets as you playtest.
      <ol>
        <li>Open (or create) a Google Spreadsheet and share it with the DevBoard service account.</li>
        <li>Click <strong>Share</strong> and add <code>editor@zapsheets-480701.iam.gserviceaccount.com</code> <button class="copy-email-btn" id="copyEmailBtn" onclick="copyEmail()">Copy</button> with <strong>Editor</strong> rights.</li>
        <li>Paste the spreadsheet URL or ID below.</li>
      </ol>
    </div>

    <input type="text" id="sheetInput" class="sheet-input"
      placeholder="https://docs.google.com/spreadsheets/d/… or sheet ID"
      onkeydown="if(event.key==='Enter')startSetup()"
      autocomplete="off" spellcheck="false" />

    <button id="connectBtn" class="connect-btn" onclick="startSetup()">
      Connect &amp; Set Up
    </button>

    <div id="logPanel" class="log-panel" style="display:none"></div>
  </div>

</div>

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

  var _pending = [];
  function cancelPending() { _pending.forEach(clearTimeout); _pending = []; }

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
    btn.textContent = 'Connecting…';
    panel.innerHTML = '';
    panel.style.display = '';

    addLog('Connecting to Google Sheet…', 'inf');
    _pending.push(setTimeout(function() { addLog('Setting up Games and Settings tabs…', 'inf'); }, 700));

    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_BASE + 'push/setupDevboard.php');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onload = function() {
      cancelPending();
      var result;
      try { result = JSON.parse(xhr.responseText); } catch(e) { result = null; }

      if (!result || result.error) {
        var errMsg = (result && result.error) || xhr.responseText || 'Unknown error';
        var isPermErr = errMsg.indexOf('403') !== -1
                     || errMsg.toLowerCase().indexOf('permission') !== -1
                     || errMsg.toLowerCase().indexOf('could not open') !== -1;
        if (isPermErr) {
          addLog('✕  Sheet not shared with the DevBoard service account.', 'err');
          addLog('   Add editor@zapsheets-480701.iam.gserviceaccount.com (Editor) and try again.', 'err');
        } else {
          addLog('✕  ' + errMsg, 'err');
        }
        btn.disabled    = false;
        btn.textContent = 'Connect & Set Up';
        return;
      }

      if (result.title) addLog('✓  Sheet: ' + result.title, 'ok');
      var created = result.tabs_created || [];
      created.forEach(function(t) { addLog('✓  "' + t + '" tab created', 'ok'); });
      if (!created.length) addLog('✓  Tabs already in place', 'ok');
      addLog('✓  Ready — opening DevBoard…', 'ok');

      setTimeout(function() {
        window.location.href = APP_BASE + sheetId + '/devboard';
      }, 1000);
    };

    xhr.onerror = function() {
      cancelPending();
      addLog('✕  Network error — could not reach the server.', 'err');
      btn.disabled    = false;
      btn.textContent = 'Connect & Set Up';
    };

    xhr.send('id=' + encodeURIComponent(sheetId));
  };
})();
</script>
</body>
</html>
