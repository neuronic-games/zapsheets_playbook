<?php
/**
 * Compendium Publish Page — /pitchboard/compendium
 *
 * Lets you point PitchBoard at the Cardboard Edison Compendium Google Sheet,
 * publish it to /data/publishers.json, and see a live progress log.
 * The sheet URL and last-published time are remembered between sessions.
 */
error_reporting(0);

$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_base = preg_replace('#/pitchboard/compendium/?$#', '/', $_raw);
if (!$_base || $_base === $_raw) { $_base = '/'; }
if (substr($_base, -1) !== '/') { $_base .= '/'; }

// Load saved config
$_configFile    = __DIR__ . '/../../../data/compendium_config.json';
$_config        = file_exists($_configFile) ? (json_decode(file_get_contents($_configFile), true) ?: []) : [];
$_savedUrl      = htmlspecialchars($_config['sheet_url']      ?? '', ENT_QUOTES);
$_lastPublished = $_config['last_published'] ?? null;

function fmtDate($iso) {
    if (!$iso) return null;
    $ts = strtotime($iso);
    if (!$ts) return null;
    return date('M j, Y — g:i a', $ts);
}
$_lastFmt = fmtDate($_lastPublished);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>The Compendium — Publish to PitchBoard</title>
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
}

/* ── Hero ─────────────────────────────────────────────────────────── */
.hero {
  position: relative;
  width: 100%;
  overflow: hidden;
  background: #1c1108;
  /* Colorful spine-of-books gradient — evokes the CE hero image */
  background: linear-gradient(135deg,
    #7b2d00 0%, #c8500a 10%, #e8a020 18%, #4a8a2a 26%,
    #1a5ca8 34%, #7a2d8a 42%, #c8500a 50%,
    #2a7a4a 58%, #1a4a8a 66%, #8a2020 74%,
    #c8860a 82%, #2a5a1a 90%, #1c1108 100%
  );
  padding: 3.5rem 1.5rem 3rem;
  text-align: center;
}
.hero::after {
  content: '';
  position: absolute;
  inset: 0;
  background: rgba(20, 12, 2, 0.62);
  pointer-events: none;
}
.hero-inner {
  position: relative;
  z-index: 1;
  max-width: 700px;
  margin: 0 auto;
}
.hero-eyebrow {
  display: inline-flex;
  align-items: center;
  gap: .55rem;
  font-family: 'DINBlack', sans-serif;
  font-size: .68rem;
  letter-spacing: .13em;
  text-transform: uppercase;
  color: #c8860a;
  margin-bottom: 1.1rem;
}
.hero-eyebrow img {
  width: 22px; height: 22px;
  border-radius: 6px;
  opacity: .9;
}
.hero-title {
  font-family: Georgia, 'Times New Roman', serif;
  font-size: clamp(2.6rem, 8vw, 4.2rem);
  font-weight: 700;
  color: #fff;
  letter-spacing: .01em;
  line-height: 1.05;
  margin-bottom: .6rem;
}
.hero-subtitle {
  font-family: Georgia, serif;
  font-style: italic;
  font-size: clamp(.95rem, 2.5vw, 1.2rem);
  color: #e8c87a;
  letter-spacing: .02em;
  opacity: .92;
}
.hero-rule {
  width: 48px;
  height: 3px;
  background: #c8860a;
  border: none;
  margin: 1.4rem auto .8rem;
  border-radius: 2px;
}
.hero-desc {
  font-family: 'DINRegular', Arial, sans-serif;
  font-size: .84rem;
  color: rgba(255,255,255,.55);
  letter-spacing: .03em;
}

/* ── Page body ────────────────────────────────────────────────────── */
.page {
  max-width: 540px;
  margin: 0 auto;
  padding: 2.5rem 1.25rem 4rem;
}

/* ── Card ─────────────────────────────────────────────────────────── */
.card {
  background: #fff;
  border-radius: 16px;
  padding: 1.8rem 2rem 2.1rem;
  box-shadow: 0 2px 24px rgba(0,0,0,.08);
}
.card + .card { margin-top: 1rem; }

.card-title {
  font-family: 'DINBlack', sans-serif;
  font-size: .88rem;
  text-transform: uppercase;
  letter-spacing: .09em;
  color: #888;
  margin-bottom: 1.1rem;
}

/* ── Last published ───────────────────────────────────────────────── */
.last-pub {
  font-size: .82rem;
  color: #999;
  margin-bottom: 1.2rem;
  display: flex;
  align-items: center;
  gap: .4rem;
}
.last-pub-dot {
  width: 7px; height: 7px;
  border-radius: 50%;
  background: <?= $_lastFmt ? '#22c55e' : '#d0ccc5' ?>;
  flex-shrink: 0;
}
.last-pub strong {
  color: <?= $_lastFmt ? '#16a34a' : '#bbb' ?>;
  font-family: 'DINBlack', sans-serif;
  font-size: .78rem;
  letter-spacing: .04em;
  text-transform: uppercase;
}

/* ── Input ────────────────────────────────────────────────────────── */
.field-label {
  font-family: 'DINBlack', sans-serif;
  font-size: .72rem;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: #888;
  margin-bottom: .35rem;
  display: block;
}
.sheet-input {
  display: block;
  width: 100%;
  padding: .72rem .9rem;
  font-family: 'DINRegular', Arial, sans-serif;
  font-size: .9rem;
  color: #111;
  border: 1.5px solid #d0ccc5;
  border-radius: 9px;
  outline: none;
  transition: border-color .15s;
  margin-bottom: 1.25rem;
  background: #fafaf8;
}
.sheet-input:focus { border-color: #c8860a; background: #fff; }
.sheet-input.shake { animation: shake .3s ease-in-out; }
@keyframes shake {
  0%,100% { transform: translateX(0); }
  25%      { transform: translateX(-7px); }
  75%      { transform: translateX( 7px); }
}

/* ── Publish button ───────────────────────────────────────────────── */
.publish-btn {
  width: 100%;
  height: 3rem;
  background: #c8860a;
  color: #fff;
  font-family: 'DINBlack', sans-serif;
  font-size: .92rem;
  text-transform: uppercase;
  letter-spacing: .1em;
  border: none;
  border-radius: 9px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: .55rem;
  transition: background .15s, transform .1s, box-shadow .15s;
  box-shadow: 0 2px 8px rgba(200,134,10,.3);
}
.publish-btn:hover:not(:disabled) {
  background: #a06d08;
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(200,134,10,.38);
}
.publish-btn:active:not(:disabled) { transform: translateY(0); }
.publish-btn:disabled { opacity: .5; cursor: default; transform: none; box-shadow: none; }

.publish-btn svg { flex-shrink: 0; }

/* ── Log panel ────────────────────────────────────────────────────── */
.log-panel {
  margin-top: 1.25rem;
  background: #0d1117;
  border-radius: 9px;
  padding: .9rem 1rem;
  max-height: 240px;
  overflow-y: auto;
  font-family: 'Courier New', Courier, monospace;
  font-size: .79rem;
  line-height: 1.85;
  scrollbar-width: thin;
  scrollbar-color: #333 transparent;
}
.log-panel::-webkit-scrollbar { width: 4px; }
.log-panel::-webkit-scrollbar-thumb { background: #333; border-radius: 2px; }

.ll     { display: block; white-space: pre-wrap; }
.ll.inf { color: #8b9bba; }
.ll.ok  { color: #4ade80; }
.ll.err { color: #ff8a80; }

/* ── Permission error ─────────────────────────────────────────────── */
.perm-error {
  display: none;
  margin-top: 1rem;
  background: #fff8f2;
  border: 1.5px solid #e8b87a;
  border-radius: 9px;
  padding: 1rem 1.1rem;
}
.perm-error-title {
  font-family: 'DINBlack', sans-serif;
  font-size: .78rem;
  text-transform: uppercase;
  letter-spacing: .07em;
  color: #c8600a;
  margin-bottom: .5rem;
}
.perm-error p {
  font-size: .82rem;
  color: #666;
  line-height: 1.6;
  margin-bottom: .6rem;
}
.copy-sa-btn {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  font-family: 'DINRegular', monospace;
  font-size: .78rem;
  color: #c8860a;
  background: rgba(200,134,10,.10);
  border: 1px solid rgba(200,134,10,.30);
  border-radius: 999px;
  padding: .22rem .65rem;
  cursor: pointer;
  white-space: nowrap;
  transition: background .15s;
}
.copy-sa-btn:hover { background: rgba(200,134,10,.2); }
.copy-sa-btn.copied { color: #16a34a; background: rgba(22,163,74,.10); border-color: rgba(22,163,74,.30); }

/* ── Permission error steps ───────────────────────────────────────── */
.perm-steps {
  margin: .65rem 0 .75rem 1.2rem;
  display: flex;
  flex-direction: column;
  gap: .3rem;
}
.perm-steps li {
  font-size: .82rem;
  color: #555;
  line-height: 1.55;
}
.perm-steps li strong { color: #333; }
</style>
</head>
<body>

<!-- ── Hero ─────────────────────────────────────────────────────────── -->
<div class="hero">
  <div class="hero-inner">
    <div class="hero-eyebrow">
      <img src="images/pb_icon_180.png" alt="" />
      PitchBoard
    </div>
    <div class="hero-title">The Compendium</div>
    <hr class="hero-rule" />
    <div class="hero-subtitle">the board game publisher directory</div>
    <p class="hero-desc" style="margin-top:.9rem">Publish Compendium data to PitchBoard</p>
  </div>
</div>

<!-- ── Body ─────────────────────────────────────────────────────────── -->
<div class="page">
  <div class="card">
    <div class="card-title">Compendium Sheet</div>

    <!-- Last published status -->
    <div class="last-pub">
      <div class="last-pub-dot"></div>
      <?php if ($_lastFmt): ?>
        <span>Last published: <strong><?= htmlspecialchars($_lastFmt) ?></strong></span>
      <?php else: ?>
        <span><strong>Never published</strong></span>
      <?php endif; ?>
    </div>

    <label class="field-label" for="sheetInput">Google Sheet URL</label>
    <input
      type="text"
      id="sheetInput"
      class="sheet-input"
      value="<?= $_savedUrl ?>"
      placeholder="https://docs.google.com/spreadsheets/d/…"
      onkeydown="if(event.key==='Enter')startPublish()"
      autocomplete="off"
      spellcheck="false"
    />

    <button id="publishBtn" class="publish-btn" onclick="startPublish()">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
        <path d="M8 1v10M4 7l4-4 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        <path d="M2 13h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
      </svg>
      Publish
    </button>

    <div id="logPanel" class="log-panel" style="display:none"></div>

    <div class="perm-error" id="schemaError" style="display:none">
      <div class="perm-error-title">Unexpected sheet structure</div>
      <p>This doesn't look like a Cardboard Edison Compendium sheet. Make sure you're using the correct Google Sheet URL.</p>
      <p id="schemaMissing" style="font-size:.78rem;color:#aaa;margin-top:.3rem"></p>
    </div>

    <div class="perm-error" id="permError">
      <div class="perm-error-title">Sheet not shared</div>
      <p>The sheet needs to be shared with the PitchBoard service account before it can be published. Follow these steps:</p>
      <ol class="perm-steps">
        <li>Open the Google Sheet and click <strong>Share</strong> (top right)</li>
        <li>Copy the address below and add it as a contact</li>
      </ol>
      <button class="copy-sa-btn" id="copySaBtn" onclick="copySA()">
        <svg width="12" height="12" viewBox="0 0 16 16" fill="none"><rect x="5" y="5" width="9" height="9" rx="1.5" stroke="currentColor" stroke-width="1.6"/><path d="M11 5V3.5A1.5 1.5 0 009.5 2h-6A1.5 1.5 0 002 3.5v6A1.5 1.5 0 003.5 11H5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        editor@zapsheets-480701.iam.gserviceaccount.com
      </button>
      <ol class="perm-steps" start="3">
        <li>Set the role to <strong>Viewer</strong> (or higher) and click <strong>Send</strong></li>
        <li>Click <strong>Publish</strong> again on this page</li>
      </ol>
    </div>

  </div>
</div>

<script>
(function() {
  'use strict';

  var APP_BASE = (function() {
    var b = document.querySelector('base');
    return b ? b.getAttribute('href') : '/';
  })();

  function addLog(msg, cls) {
    var panel = document.getElementById('logPanel');
    panel.style.display = '';
    var el = document.createElement('span');
    el.className   = 'll ' + (cls || 'inf');
    el.textContent = msg;
    panel.appendChild(el);
    panel.scrollTop = panel.scrollHeight;
  }

  var SA_EMAIL = 'editor@zapsheets-480701.iam.gserviceaccount.com';

  window.copySA = function() {
    var btn = document.getElementById('copySaBtn');
    navigator.clipboard.writeText(SA_EMAIL).then(function() {
      btn.textContent = '✓ Copied!';
      btn.classList.add('copied');
      setTimeout(function() {
        btn.innerHTML = '<svg width="12" height="12" viewBox="0 0 16 16" fill="none"><rect x="5" y="5" width="9" height="9" rx="1.5" stroke="currentColor" stroke-width="1.6"/><path d="M11 5V3.5A1.5 1.5 0 009.5 2h-6A1.5 1.5 0 002 3.5v6A1.5 1.5 0 003.5 11H5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg> ' + SA_EMAIL;
        btn.classList.remove('copied');
      }, 2000);
    }).catch(function() {});
  };

  window.startPublish = function() {
    var inp = document.getElementById('sheetInput');
    var btn = document.getElementById('publishBtn');
    var url = inp.value.trim();

    if (!url) {
      inp.classList.remove('shake');
      void inp.offsetWidth;
      inp.classList.add('shake');
      inp.focus();
      setTimeout(function() { inp.classList.remove('shake'); }, 400);
      return;
    }

    // Lock UI
    btn.disabled    = true;
    btn.textContent = 'Publishing…';
    var panel = document.getElementById('logPanel');
    panel.innerHTML = '';
    panel.style.display = '';
    document.getElementById('permError').style.display = 'none';
    document.getElementById('schemaError').style.display = 'none';
    addLog('Starting publish…', 'inf');

    var fd = new FormData();
    fd.append('sheet_url', url);

    fetch(APP_BASE + 'push/publishCompendium.php', { method: 'POST', body: fd })
      .then(function(resp) {
        if (!resp.ok) throw new Error('HTTP ' + resp.status);
        var reader  = resp.body.getReader();
        var decoder = new TextDecoder();
        var buf     = '';
        var done    = false;

        function read() {
          reader.read().then(function(chunk) {
            if (chunk.done) { finish(done); return; }
            buf += decoder.decode(chunk.value, { stream: true });
            var lines = buf.split('\n');
            buf = lines.pop(); // keep incomplete line
            lines.forEach(function(line) {
              line = line.trim();
              if (!line) return;
              var data;
              try { data = JSON.parse(line); } catch(e) { addLog(line, 'inf'); return; }
              if (data.status === 'close') { done = true; return; }
              if (data.status === 'done') { done = true; }
              if (data.status === 'error' && data.code === 'permission_denied') {
                document.getElementById('permError').style.display = '';
              }
              if (data.status === 'error' && data.code === 'invalid_schema') {
                var box = document.getElementById('schemaError');
                box.style.display = '';
                if (data.missing && data.missing.length) {
                  document.getElementById('schemaMissing').textContent =
                    'Missing columns: ' + data.missing.join(', ');
                }
              }
              var cls = data.status === 'ok' ? 'ok' : data.status === 'error' ? 'err' : 'inf';
              addLog(data.msg, cls);
            });
            read();
          }).catch(function(e) { finish(false); });
        }
        read();

        function finish(ok) {
          btn.disabled  = false;
          btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 1v10M4 7l4-4 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 13h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg> Publish';
          if (ok) {
            // Update "Last published" in place — keep the log visible
            var now  = new Date();
            var mo   = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            var h    = now.getHours(), m = now.getMinutes(), ap = h >= 12 ? 'pm' : 'am';
            h = h % 12 || 12;
            var ts   = mo[now.getMonth()] + ' ' + now.getDate() + ', ' + now.getFullYear()
                     + ' — ' + h + ':' + (m < 10 ? '0' : '') + m + ' ' + ap;
            var lpEl = document.querySelector('.last-pub');
            if (lpEl) {
              lpEl.innerHTML = '<div class="last-pub-dot" style="background:#22c55e;flex-shrink:0"></div>'
                             + '<span>Last published: <strong style="color:#16a34a;font-family:\'DINBlack\',sans-serif;'
                             + 'font-size:.78rem;letter-spacing:.04em;text-transform:uppercase">' + ts + '</strong></span>';
            }
          }
        }
      })
      .catch(function(e) {
        addLog('✕  Network error: ' + e.message, 'err');
        btn.disabled    = false;
        btn.innerHTML   = '<svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 1v10M4 7l4-4 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><path d="M2 13h12" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg> Publish';
      });
  };

})();
</script>
</body>
</html>
