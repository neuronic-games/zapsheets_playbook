<?php
/**
 * FitBoard setup landing page.
 * Shown when the user navigates to /fitboard (no sheet ID yet).
 * Lets them paste a Google Sheet ID, runs setupFitboard.php, streams a log,
 * and redirects to /{id}/fitboard on success.
 */

$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_base = preg_replace('#/fitboard(/setup)?/?$#', '/', $_raw);
if (!$_base || $_base === $_raw) { $_base = '/'; }
if (substr($_base, -1) !== '/') { $_base .= '/'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>FitBoard – Connect Your Sheet</title>
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
  align-items: center;
  justify-content: center;
  padding: 2rem 1rem;
}

.page {
  width: 100%;
  max-width: 480px;
}

/* ── Branding ─────────────────────────────────────────────────── */
.brand {
  text-align: center;
  margin-bottom: 2.25rem;
}
.brand-logo {
  width: 84px;
  height: 84px;
  object-fit: contain;
  margin-bottom: .9rem;
  border-radius: 20px;
  box-shadow: 0 4px 24px rgba(10,132,255,.35);
}
.brand-name {
  font-family: 'DINBlack', sans-serif;
  font-size: 2.1rem;
  text-transform: uppercase;
  letter-spacing: .09em;
  color: #fff;
  line-height: 1;
}
.brand-tagline {
  margin-top: .4rem;
  font-size: .88rem;
  color: rgba(255,255,255,.45);
  letter-spacing: .03em;
}

/* ── Card ─────────────────────────────────────────────────────── */
.card {
  background: #1c1c28;
  border: 1px solid #2a2a3a;
  border-radius: 16px;
  padding: 2rem 2rem 2.25rem;
  box-shadow: 0 4px 32px rgba(0,0,0,.45);
}
.card-title {
  font-family: 'DINBlack', sans-serif;
  font-size: 1.05rem;
  text-transform: uppercase;
  letter-spacing: .07em;
  color: #fff;
  margin-bottom: .8rem;
}
.instructions {
  font-size: .88rem;
  color: rgba(255,255,255,.55);
  line-height: 1.65;
  margin-bottom: 1.5rem;
}
.instructions ol {
  padding-left: 1.35rem;
  margin-top: .5rem;
}
.instructions li { margin-bottom: .25rem; }
.instructions strong { color: rgba(255,255,255,.85); }
.copy-email-btn {
  display: inline-flex; align-items: center; gap: .28rem;
  font-family: 'DINRegular', Arial, sans-serif; font-size: .75rem;
  color: #0a84ff; background: rgba(10,132,255,.12);
  border: 1px solid rgba(10,132,255,.30); border-radius: 999px;
  padding: .1rem .45rem; cursor: pointer;
  vertical-align: middle; white-space: nowrap;
  transition: background .15s, color .15s;
}
.copy-email-btn:hover { background: rgba(10,132,255,.22); }
.copy-email-btn.copied { color: #30d158; background: rgba(48,209,88,.10); border-color: rgba(48,209,88,.30); }

/* ── Input ────────────────────────────────────────────────────── */
.sheet-input {
  display: block;
  width: 100%;
  padding: .7rem .9rem;
  font-family: 'DINRegular', Arial, sans-serif;
  font-size: .95rem;
  color: #fff;
  border: 1.5px solid #2a2a3a;
  border-radius: 9px;
  outline: none;
  transition: border-color .15s;
  margin-bottom: 1rem;
  background: #12121c;
}
.sheet-input:focus { border-color: #0a84ff; }
.sheet-input::placeholder { color: rgba(255,255,255,.28); }
.sheet-input.shake { animation: shake .3s ease-in-out; }

@keyframes shake {
  0%,100% { transform: translateX(0); }
  25%      { transform: translateX(-7px); }
  75%      { transform: translateX( 7px); }
}

/* ── Button ───────────────────────────────────────────────────── */
.connect-btn {
  width: 100%;
  height: 2.85rem;
  background: #0a84ff;
  color: #fff;
  font-family: 'DINBlack', sans-serif;
  font-size: .9rem;
  text-transform: uppercase;
  letter-spacing: .09em;
  border: none;
  border-radius: 9px;
  cursor: pointer;
  transition: background .15s, transform .1s, box-shadow .15s;
  box-shadow: 0 2px 12px rgba(10,132,255,.4);
}
.connect-btn:hover:not(:disabled) {
  background: #0070d8;
  transform: translateY(-1px);
  box-shadow: 0 4px 18px rgba(10,132,255,.5);
}
.connect-btn:active:not(:disabled) { transform: translateY(0); }
.connect-btn:disabled { opacity: .45; cursor: default; transform: none; box-shadow: none; }

/* ── Log panel ────────────────────────────────────────────────── */
.log-panel {
  margin-top: 1.25rem;
  background: #080810;
  border: 1px solid #1e1e2e;
  border-radius: 9px;
  padding: .9rem 1rem;
  max-height: 220px;
  overflow-y: auto;
  font-family: 'Courier New', Courier, monospace;
  font-size: .79rem;
  line-height: 1.8;
  scrollbar-width: thin;
  scrollbar-color: #2a2a3a transparent;
}
.log-panel::-webkit-scrollbar { width: 4px; }
.log-panel::-webkit-scrollbar-thumb { background: #2a2a3a; border-radius: 2px; }

.ll     { display: block; white-space: pre-wrap; }
.ll.inf { color: rgba(255,255,255,.45); }
.ll.ok  { color: #30d158; }
.ll.err { color: #ff375f; }
</style>
</head>
<body>

<div class="page">

  <!-- ── Branding ─────────────────────────────────────────────── -->
  <div class="brand">
    <img class="brand-logo" src="images/fb_icon_180.png" alt="FitBoard logo" />
    <div class="brand-name">FitBoard</div>
    <div class="brand-tagline">Your workout tracker, powered by Google Sheets</div>
  </div>

  <!-- ── Setup card ───────────────────────────────────────────── -->
  <div class="card">
    <div class="card-title">Connect Your Google Sheet</div>
    <div class="instructions">
      Create a blank Google Spreadsheet, share it with the FitBoard service account, then paste the URL or ID below. FitBoard will set up the Week tab automatically.
      <ol>
        <li>Open <strong>Google Sheets</strong> and create a new blank spreadsheet.</li>
        <li>Click <strong>Share</strong> and add <code>editor@zapsheets-480701.iam.gserviceaccount.com</code> <button class="copy-email-btn" id="copyEmailBtn" onclick="copyEmail()">Copy</button> with <strong>Editor</strong> rights.</li>
        <li>Copy the spreadsheet URL (or just the ID) and paste it below.</li>
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
      Connect &amp; Set Up
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
      btn.textContent = 'Copied!';
      btn.classList.add('copied');
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
  function scheduledLog(delayMs, msg, cls) {
    _pending.push(setTimeout(function() { addLog(msg, cls); }, delayMs));
  }
  function cancelPending() {
    _pending.forEach(clearTimeout);
    _pending = [];
  }

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
    scheduledLog(700,  'Setting up Week worksheet…', 'inf');
    scheduledLog(1500, 'Refreshing local data cache…', 'inf');

    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_BASE + 'push/setupFitboard.php');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onload = function() {
      cancelPending();

      var result;
      try { result = JSON.parse(xhr.responseText); }
      catch(e) { result = null; }

      if (!result || result.error) {
        addLog('✕  ' + ((result && result.error) || xhr.responseText || 'Unknown error'), 'err');
        btn.disabled    = false;
        btn.textContent = 'Connect & Set Up';
        return;
      }

      if (result.title) {
        addLog('✓  Sheet: ' + result.title, 'ok');
      }

      var tabs    = result.tabs || {};
      var allGood = true;
      Object.keys(tabs).forEach(function(tab) {
        var s = tabs[tab];
        if (s === 'created') {
          addLog('✓  ' + tab + ' tab created', 'ok');
        } else if (s === 'ok') {
          addLog('✓  ' + tab + ' tab already exists', 'ok');
        } else {
          addLog('✕  ' + tab + ': ' + s, 'err');
          allGood = false;
        }
      });

      if (!allGood) {
        btn.disabled    = false;
        btn.textContent = 'Connect & Set Up';
        return;
      }

      if (result.week_json) {
        addLog('✓  Local data cache ready', 'ok');
      }

      addLog('✓  Setup complete! Building your plan…', 'ok');

      setTimeout(function () {
        window.location.href = APP_BASE + sheetId + '/fitboard/onboard';
      }, 900);
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
