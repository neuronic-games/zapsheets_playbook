<?php
/**
 * PitchBoard setup landing page.
 * Shown when the user navigates to /pitchboard (no sheet ID yet).
 * Lets them paste a Google Sheet ID, runs setupSheet.php, streams a log,
 * and redirects to /{id}/dashboard on success.
 */

// Extract the app base (everything before /pitchboard) so that relative
// links in <base href> resolve correctly under any BASE_PATH.
$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_base = preg_replace('#/pitchboard/?$#', '/', $_raw);
if (!$_base || $_base === $_raw) { $_base = '/'; }
if (substr($_base, -1) !== '/') { $_base .= '/'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>PitchBoard – Connect Your Sheet</title>
<link rel="icon" type="image/png" href="images/pb_icon_180.png" />
<style>
@font-face { font-family:'DINBlack';   src:url('fonts/DINBlack.woff2')  format('woff2'),url('fonts/DINBlack.ttf')  format('truetype'); }
@font-face { font-family:'DINRegular'; src:url('fonts/DINMedium.woff2') format('woff2'),url('fonts/DINMedium.ttf') format('truetype'); }

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

body {
  background: #f3f0eb;
  font-family: 'DINRegular', Arial, sans-serif;
  color: #1a1a1a;
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
  box-shadow: 0 4px 20px rgba(0,0,0,.18);
}
.brand-name {
  font-family: 'DINBlack', sans-serif;
  font-size: 2.1rem;
  text-transform: uppercase;
  letter-spacing: .09em;
  color: #111;
  line-height: 1;
}
.brand-tagline {
  margin-top: .4rem;
  font-size: .88rem;
  color: #999;
  letter-spacing: .03em;
}

/* ── Card ─────────────────────────────────────────────────────── */
.card {
  background: #fff;
  border-radius: 16px;
  padding: 2rem 2rem 2.25rem;
  box-shadow: 0 2px 24px rgba(0,0,0,.08);
}
.card-title {
  font-family: 'DINBlack', sans-serif;
  font-size: 1.05rem;
  text-transform: uppercase;
  letter-spacing: .07em;
  color: #111;
  margin-bottom: .8rem;
}
.instructions {
  font-size: .88rem;
  color: #555;
  line-height: 1.65;
  margin-bottom: 1.5rem;
}
.instructions ol {
  padding-left: 1.35rem;
  margin-top: .5rem;
}
.instructions li { margin-bottom: .25rem; }
.instructions strong { color: #333; }
.copy-email-btn {
  display: inline-flex; align-items: center; gap: .28rem;
  font-family: 'DINRegular', Arial, sans-serif; font-size: .75rem;
  color: #c8860a; background: rgba(200,134,10,.10);
  border: 1px solid rgba(200,134,10,.30); border-radius: 999px;
  padding: .1rem .45rem; cursor: pointer;
  vertical-align: middle; white-space: nowrap;
  transition: background .15s, color .15s;
}
.copy-email-btn:hover { background: rgba(200,134,10,.22); }
.copy-email-btn.copied { color: #16a34a; background: rgba(22,163,74,.10); border-color: rgba(22,163,74,.30); }

/* ── Input ────────────────────────────────────────────────────── */
.sheet-input {
  display: block;
  width: 100%;
  padding: .7rem .9rem;
  font-family: 'DINRegular', Arial, sans-serif;
  font-size: .95rem;
  color: #111;
  border: 1.5px solid #d0ccc5;
  border-radius: 9px;
  outline: none;
  transition: border-color .15s;
  margin-bottom: 1rem;
  background: #fafaf8;
}
.sheet-input:focus { border-color: #c8860a; background: #fff; }
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
  background: #c8860a;
  color: #fff;
  font-family: 'DINBlack', sans-serif;
  font-size: .9rem;
  text-transform: uppercase;
  letter-spacing: .09em;
  border: none;
  border-radius: 9px;
  cursor: pointer;
  transition: background .15s, transform .1s, box-shadow .15s;
  box-shadow: 0 2px 8px rgba(200,134,10,.3);
}
.connect-btn:hover:not(:disabled) {
  background: #a06d08;
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(200,134,10,.35);
}
.connect-btn:active:not(:disabled) { transform: translateY(0); }
.connect-btn:disabled { opacity: .5; cursor: default; transform: none; box-shadow: none; }

/* ── Log panel ────────────────────────────────────────────────── */
.log-panel {
  margin-top: 1.25rem;
  background: #0d1117;
  border-radius: 9px;
  padding: .9rem 1rem .9rem 1rem;
  max-height: 220px;
  overflow-y: auto;
  font-family: 'Courier New', Courier, monospace;
  font-size: .79rem;
  line-height: 1.8;
  scrollbar-width: thin;
  scrollbar-color: #333 transparent;
}
.log-panel::-webkit-scrollbar { width: 4px; }
.log-panel::-webkit-scrollbar-thumb { background: #333; border-radius: 2px; }

.ll     { display: block; white-space: pre-wrap; }
.ll.inf { color: #8b9bba; }
.ll.ok  { color: #4ade80; }
.ll.err { color: #ff8a80; }
</style>
</head>
<body>

<div class="page">

  <!-- ── Branding ─────────────────────────────────────────────── -->
  <div class="brand">
    <img class="brand-logo" src="images/pb_icon_180.png" alt="PitchBoard logo" />
    <div class="brand-name">PitchBoard</div>
    <div class="brand-tagline">Your board game pitch tracker</div>
    <a href="pitchboard/help" style="display:inline-block;margin-top:.6rem;font-family:'DINBlack',sans-serif;font-size:.68rem;letter-spacing:.07em;text-transform:uppercase;color:#c8860a;text-decoration:none;opacity:.8;">How it works →</a>
  </div>

  <!-- ── Setup card ───────────────────────────────────────────── -->
  <div class="card">
    <div class="card-title">Connect Your Google Sheet</div>
    <div class="instructions">
      Create a blank Google Spreadsheet, share it with the PitchBoard service account, then paste the URL or ID below. PitchBoard will set up all the required tabs automatically.
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
      setTimeout(function() {
        btn.textContent = 'Copy';
        btn.classList.remove('copied');
      }, 2000);
    }).catch(function() {
      // Fallback for older browsers
      var ta = document.createElement('textarea');
      ta.value = email; ta.style.position = 'fixed'; ta.style.opacity = '0';
      document.body.appendChild(ta); ta.select();
      try { document.execCommand('copy'); btn.textContent = 'Copied!'; btn.classList.add('copied');
        setTimeout(function() { btn.textContent = 'Copy'; btn.classList.remove('copied'); }, 2000);
      } catch(e) {}
      document.body.removeChild(ta);
    });
  };

  // Extract the bare sheet ID from a full Google Sheets URL or a bare ID string.
  function extractSheetId(raw) {
    raw = (raw || '').trim();
    // Full URL pattern: /spreadsheets/d/{ID}
    var m = raw.match(/\/spreadsheets\/d\/([A-Za-z0-9_\-]+)/);
    if (m) return m[1];
    // Bare ID: alphanumeric + hyphen + underscore, at least 10 chars
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
      // Force reflow to restart animation if already shaking
      void inp.offsetWidth;
      inp.classList.add('shake');
      inp.focus();
      setTimeout(function() { inp.classList.remove('shake'); }, 400);
      return;
    }

    // Lock UI
    btn.disabled    = true;
    btn.textContent = 'Connecting…';
    panel.innerHTML = '';
    panel.style.display = '';

    // Anticipatory messages — let the user know something is happening
    // while we wait for the Python scripts to finish.
    addLog('Connecting to Google Sheet…', 'inf');
    scheduledLog(700,  'Initialising worksheet tabs (Pitches, Games, People, Settings)…', 'inf');
    scheduledLog(1500, 'Refreshing local data caches…', 'inf');

    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_BASE + 'push/setupSheet.php');
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

      // Sheet title (set by ginitsheet.py → passed through by setupSheet.php)
      if (result.title) {
        addLog('✓  Sheet: ' + result.title, 'ok');
      }

      // Per-tab results
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

      addLog('✓  Setup complete — opening your dashboard…', 'ok');

      setTimeout(function() {
        window.location.href = APP_BASE + sheetId + '/pitchboard';
      }, 1500);
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
