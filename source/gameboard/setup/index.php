<?php
/**
 * GameBoard setup landing page.
 * Shown when the user navigates to /gameboard (no sheet ID yet).
 * Accepts a Google Sheet URL or ID, runs setupGameboard.php,
 * streams a status log, and redirects to /{id}/gameboard/ on success.
 *
 * Works for both:
 *   - New blank sheets  (tabs get created automatically)
 *   - Existing PitchBoard sheets  (tabs already exist, cache refreshed)
 */

$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_base = preg_replace('#/gameboard(/setup)?/?$#', '/', $_raw);
if (!$_base || $_base === $_raw) { $_base = '/'; }
if (substr($_base, -1) !== '/') { $_base .= '/'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>GameBoard — Connect Your Sheet</title>
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2')  format('woff2'),url('fonts/DINBlack.ttf')  format('truetype'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf') format('truetype'); }

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  background: #060d14;
  font-family: 'DINRegular', Arial, sans-serif;
  color: #fff;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem 1rem;
}

.page { width: 100%; max-width: 480px; }

/* ── Branding ── */
.brand { text-align: center; margin-bottom: 2.25rem; }
.brand-icon {
  margin: 0 auto 1rem;
  display: flex; align-items: center; justify-content: center;
}
.brand-icon img { height: 88px; width: auto; }
.brand-name {
  font-family: 'DINBlack', sans-serif;
  font-size: 2rem; text-transform: uppercase; letter-spacing: .09em;
  color: #fff; line-height: 1;
}
.brand-tagline { margin-top: .4rem; font-size: .88rem; color: rgba(255,255,255,.42); letter-spacing: .03em; }

/* ── Card ── */
.card {
  background: #0d1b24; border: 1px solid #1a3040;
  border-radius: 16px; padding: 2rem 2rem 2.25rem;
  box-shadow: 0 4px 40px rgba(0,0,0,.6);
}
.card-title {
  font-family: 'DINBlack', sans-serif; font-size: 1.02rem;
  text-transform: uppercase; letter-spacing: .07em; color: #fff; margin-bottom: .75rem;
}
.instructions { font-size: .88rem; color: rgba(255,255,255,.5); line-height: 1.65; margin-bottom: 1.5rem; }
.instructions ol { padding-left: 1.35rem; margin-top: .5rem; }
.instructions li { margin-bottom: .28rem; }
.instructions strong { color: rgba(255,255,255,.82); }
.instructions code { font-size: .8rem; background: rgba(0,151,167,.15); color: #4dd0e1; padding: .05rem .35rem; border-radius: 4px; }

.copy-email-btn {
  display: inline-flex; align-items: center; gap: .28rem;
  font-family: 'DINRegular', Arial, sans-serif; font-size: .75rem;
  color: #0097a7; background: rgba(0,151,167,.12);
  border: 1px solid rgba(0,151,167,.3); border-radius: 999px;
  padding: .1rem .45rem; cursor: pointer; vertical-align: middle; white-space: nowrap;
  transition: background .15s;
}
.copy-email-btn:hover { background: rgba(0,151,167,.22); }
.copy-email-btn.copied { color: #26c6da; background: rgba(38,198,218,.12); border-color: rgba(38,198,218,.3); }

/* ── Input ── */
.sheet-input {
  display: block; width: 100%;
  padding: .72rem .9rem;
  font-family: 'DINRegular', Arial, sans-serif; font-size: .95rem;
  color: #fff; background: #060d14;
  border: 1.5px solid #1a3040; border-radius: 9px;
  outline: none; transition: border-color .15s; margin-bottom: 1rem;
}
.sheet-input:focus { border-color: #0097a7; }
.sheet-input::placeholder { color: rgba(255,255,255,.25); }
.sheet-input.shake { animation: shake .3s ease-in-out; }
@keyframes shake {
  0%,100% { transform: translateX(0); }
  25%      { transform: translateX(-7px); }
  75%      { transform: translateX(7px); }
}

/* ── Button ── */
.connect-btn {
  width: 100%; height: 2.85rem;
  background: #0097a7; color: #fff;
  font-family: 'DINBlack', sans-serif; font-size: .88rem;
  text-transform: uppercase; letter-spacing: .09em;
  border: none; border-radius: 9px; cursor: pointer;
  transition: background .15s, transform .1s, box-shadow .15s;
  box-shadow: 0 2px 16px rgba(0,151,167,.45);
}
.connect-btn:hover:not(:disabled) { background: #00838f; transform: translateY(-1px); box-shadow: 0 4px 22px rgba(0,151,167,.55); }
.connect-btn:active:not(:disabled) { transform: translateY(0); }
.connect-btn:disabled { opacity: .4; cursor: default; transform: none; box-shadow: none; }

/* ── Log panel ── */
.log-panel {
  margin-top: 1.25rem; background: #030a10;
  border: 1px solid #0d2030; border-radius: 9px;
  padding: .9rem 1rem; max-height: 220px; overflow-y: auto;
  font-family: 'Courier New', Courier, monospace; font-size: .79rem; line-height: 1.8;
  scrollbar-width: thin; scrollbar-color: #1a3040 transparent;
}
.log-panel::-webkit-scrollbar { width: 4px; }
.log-panel::-webkit-scrollbar-thumb { background: #1a3040; border-radius: 2px; }
.ll     { display: block; white-space: pre-wrap; }
.ll.inf { color: rgba(255,255,255,.4); }
.ll.ok  { color: #26c6da; }
.ll.err { color: #ef5350; }
</style>
</head>
<body>

<div class="page">

  <!-- Branding -->
  <div class="brand">
    <div class="brand-icon"><img src="images/gb_logo.png" alt="GameBoard" /></div>
    <div class="brand-name">GameBoard</div>
    <div class="brand-tagline">Polished product pages for your board games</div>
  </div>

  <!-- Setup card -->
  <div class="card">
    <div class="card-title">Connect Your Google Sheet</div>
    <div class="instructions">
      Use your existing <strong>PitchBoard</strong> sheet, or start with a new blank Google Spreadsheet. Either way, share it with the GameBoard service account and paste the URL below.
      <ol>
        <li>Open your spreadsheet in Google Sheets (new or existing).</li>
        <li>Click <strong>Share</strong> and add <code>editor@zapsheets-480701.iam.gserviceaccount.com</code> <button class="copy-email-btn" id="copyEmailBtn" onclick="copyEmail()">Copy</button> with <strong>Editor</strong> rights.</li>
        <li>Paste the spreadsheet URL or ID below and click Connect.</li>
      </ol>
    </div>

    <input
      type="text"
      id="sheetInput"
      class="sheet-input"
      placeholder="https://docs.google.com/spreadsheets/d/… or sheet ID"
      onkeydown="if(event.key==='Enter')startSetup()"
      autocomplete="off"
      spellcheck="false"
    />

    <button id="connectBtn" class="connect-btn" onclick="startSetup()">
      Connect &amp; Launch GameBoard
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
      try {
        document.execCommand('copy');
        btn.textContent = 'Copied!'; btn.classList.add('copied');
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
  function scheduledLog(ms, msg, cls) {
    _pending.push(setTimeout(function() { addLog(msg, cls); }, ms));
  }
  function cancelPending() { _pending.forEach(clearTimeout); _pending = []; }

  function addLog(msg, cls) {
    var panel = document.getElementById('logPanel');
    var el    = document.createElement('span');
    el.className   = 'll ' + (cls || 'inf');
    el.textContent = msg;
    panel.appendChild(el);
    panel.scrollTop = panel.scrollHeight;
    return el;
  }

  window.startSetup = function() {
    var inp     = document.getElementById('sheetInput');
    var btn     = document.getElementById('connectBtn');
    var panel   = document.getElementById('logPanel');
    var sheetId = extractSheetId(inp.value);

    if (!sheetId) {
      inp.classList.remove('shake'); void inp.offsetWidth; inp.classList.add('shake');
      inp.focus();
      setTimeout(function() { inp.classList.remove('shake'); }, 400);
      return;
    }

    btn.disabled    = true;
    btn.textContent = 'Connecting…';
    panel.innerHTML = '';
    panel.style.display = '';

    addLog('Connecting to Google Sheet…', 'inf');
    scheduledLog(700,  'Verifying access and preparing tabs…', 'inf');
    scheduledLog(2000, 'Caching games data…', 'inf');

    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_BASE + 'push/setupGameboard.php');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onload = function() {
      cancelPending();
      var result;
      try { result = JSON.parse(xhr.responseText); } catch(e) { result = null; }

      if (!result || result.error) {
        addLog('✕  ' + ((result && result.error) || xhr.responseText || 'Unknown error'), 'err');
        btn.disabled = false; btn.textContent = 'Connect & Launch GameBoard';
        return;
      }

      if (result.title) addLog('✓  Sheet: ' + result.title, 'ok');

      var tabs = result.tabs || {};
      var allGood = true;
      Object.keys(tabs).forEach(function(tab) {
        var s = tabs[tab];
        if      (s === 'created') addLog('✓  ' + tab + ' tab created', 'ok');
        else if (s === 'ok')      addLog('✓  ' + tab + ' tab ready', 'ok');
        else { addLog('✕  ' + tab + ': ' + s, 'err'); allGood = false; }
      });

      if (!allGood) {
        btn.disabled = false; btn.textContent = 'Connect & Launch GameBoard';
        return;
      }

      if ((result.json_refreshed || []).length) {
        addLog('✓  Games data cached (' + result.json_refreshed.join(', ') + ')', 'ok');
      }
      addLog('✓  All done — launching GameBoard…', 'ok');

      setTimeout(function() {
        window.location.href = APP_BASE + sheetId + '/gameboard/';
      }, 900);
    };

    xhr.onerror = function() {
      cancelPending();
      addLog('✕  Network error — could not reach the server.', 'err');
      btn.disabled = false; btn.textContent = 'Connect & Launch GameBoard';
    };

    xhr.send('id=' + encodeURIComponent(sheetId));
  };
})();
</script>

</body>
</html>
