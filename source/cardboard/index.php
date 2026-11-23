<?php
/**
 * CardBoard setup landing page.
 * Paste a Google Sheet ID/URL — CardBoard checks it can read the sheet, then opens the card designer.
 */

$_raw  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$_base = preg_replace('#/cardboard/?$#', '/', $_raw);
if (!$_base || $_base === $_raw) { $_base = '/'; }
if (substr($_base, -1) !== '/') { $_base .= '/'; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<base href="<?= htmlspecialchars($_base, ENT_QUOTES) ?>" />
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>CardBoard – Card Designer</title>
<?php require_once dirname(__DIR__, 2) . '/push/linkPreview.php'; echo gbLinkPreview('cardboard', '', ['title' => 'CardBoard — Card Designer', 'description' => 'Design and print board game cards from a Google Sheet. Part of GameBoard — tools for board game makers.']); ?>
<link rel="icon" type="image/png" sizes="32x32" href="images/cardboard_icon_32.png" />
<link rel="apple-touch-icon" sizes="180x180" href="images/cardboard_icon_180.png" />
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
.brand-logo { width:84px; height:84px; border-radius:20px; display:block; margin:0 auto .9rem; box-shadow:0 4px 20px rgba(0,0,0,.18); }
.brand-icon {
  width:84px; height:84px; border-radius:20px;
  background:#4b3fa8; display:inline-flex;
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
.why { margin:.75rem 0 .2rem; border:1px solid #e3e0f5; border-radius:9px; background:#f8f7fe; padding:.45rem .8rem; }
.why summary { cursor:pointer; font-family:'DINBlack',sans-serif; font-size:.78rem; letter-spacing:.05em; text-transform:uppercase; color:#4b3fa8; }
.why[open] summary { margin-bottom:.35rem; }
.why p { margin:.35rem 0; font-size:.84rem; }
.why ul { margin:.3rem 0 .4rem; padding-left:1.15rem; font-size:.84rem; }
.why li { margin-bottom:.25rem; }
.why b { color:#333; }

.copy-email-btn {
  display:inline-flex; align-items:center; gap:.28rem;
  font-family:'DINRegular',Arial,sans-serif; font-size:.75rem;
  color:#4b3fa8; background:rgba(75,63,168,.10);
  border:1px solid rgba(75,63,168,.30); border-radius:999px;
  padding:.1rem .45rem; cursor:pointer; vertical-align:middle;
  white-space:nowrap; transition:background .15s;
}
.copy-email-btn:hover { background:rgba(75,63,168,.22); }
.copy-email-btn.copied { color:#16a34a; background:rgba(22,163,74,.10); border-color:rgba(22,163,74,.30); }

/* Input */
.sheet-input {
  display:block; width:100%; padding:.7rem .9rem;
  font-family:'DINRegular',Arial,sans-serif; font-size:.95rem; color:#111;
  border:1.5px solid #d0ccc5; border-radius:9px; outline:none;
  transition:border-color .15s; margin-bottom:1rem; background:#fafaf8;
}
.sheet-input:focus { border-color:#4b3fa8; background:#fff; }
.sheet-input.shake { animation:shake .3s ease-in-out; }
@keyframes shake {
  0%,100% { transform:translateX(0); }
  25%      { transform:translateX(-7px); }
  75%      { transform:translateX( 7px); }
}

/* Button */
.connect-btn {
  width:100%; height:2.85rem;
  background:#4b3fa8; color:#fff;
  font-family:'DINBlack',sans-serif; font-size:.9rem;
  text-transform:uppercase; letter-spacing:.09em;
  border:none; border-radius:9px; cursor:pointer;
  transition:background .15s, transform .1s, box-shadow .15s;
  box-shadow:0 2px 8px rgba(75,63,168,.3);
}
.connect-btn:hover:not(:disabled) { background:#3b3190; transform:translateY(-1px); box-shadow:0 4px 14px rgba(75,63,168,.35); }
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

/* Welcome: start the free week or log in, before anything else */
.choose { display:flex; flex-direction:column; gap:.7rem; }
.opt { display:flex; flex-direction:column; align-items:center; gap:.2rem; padding:1rem; border:1.5px solid #d6d9e6; border-radius:11px; background:#fff; color:#2e2763; cursor:pointer; font:inherit; transition:border-color .12s, background .12s, transform .1s; }
.opt b { font-family:'DINBlack',sans-serif; font-weight:400; font-size:1rem; letter-spacing:.04em; text-transform:uppercase; }
.opt span { font-size:.8rem; color:#8a8fa8; }
.opt:hover { border-color:#a99cff; background:#f7f5ff; }
.opt.primary { background:#4b3fa8; border-color:#4b3fa8; color:#fff; box-shadow:0 2px 8px rgba(75,63,168,.3); }
.opt.primary span { color:rgba(255,255,255,.78); }
.opt.primary:hover { background:#3b3190; transform:translateY(-1px); }
.auth-row { display:flex; gap:.5rem; }
.auth-col { display:flex; flex-direction:column; gap:.6rem; }
.auth-col .sheet-input { margin:0; }
.auth-col .connect-btn { width:100%; margin:0; }
.pw-rules { list-style:none; margin:.1rem 0 .2rem; padding:0; display:grid; grid-template-columns:1fr 1fr; gap:.25rem .9rem; font-size:.8rem; color:#9aa0b8; }
.pw-rules li { display:flex; align-items:center; gap:.45rem; transition:color .15s; }
.pw-rules li::before { content:''; width:1rem; height:1rem; flex-shrink:0; border-radius:50%; border:1.5px solid #c9cce0; box-sizing:border-box; transition:background .15s, border-color .15s; }
.pw-rules li.ok { color:#2e7d32; }
.pw-rules li.ok::before { border-color:#2e9d4c; background:#2e9d4c url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='3.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M5 12.5l4.5 4.5L19 7.5'/%3E%3C/svg%3E") center/70% no-repeat; }
/* Signed out: CardBoard's purple, like the designer's sign-in page */
body.signed-out { background:#2e2763; }
body.signed-out .brand-name { color:#fff; }
body.signed-out .brand-tagline { color:rgba(255,255,255,.65); }
body.signed-out .card { box-shadow:0 18px 50px rgba(0,0,0,.35); }
.auth-row .sheet-input { margin:0; flex:1; }
.auth-row .connect-btn { width:auto; padding:0 1.1rem; white-space:nowrap; }
.code-input { letter-spacing:.35em; text-align:center; font-family:ui-monospace,Menlo,monospace; }
.auth-msg { font-size:.85rem; color:#666; margin:0 0 .9rem; line-height:1.5; }
.auth-err { color:#c0392b; font-size:.82rem; min-height:1.1rem; margin-top:.6rem; }
.link-btn { background:none; border:none; color:#6b7090; font:inherit; font-size:.82rem; text-decoration:underline; text-underline-offset:2px; cursor:pointer; padding:0; }
.link-btn:hover { color:#2e2763; }
.auth-foot { margin-top:1rem; text-align:center; }
.who { text-align:center; font-size:.82rem; color:#8a8fa8; margin:-.4rem 0 1rem; }
.who b { color:#2e2763; }
[hidden] { display:none !important; }
</style>
</head>
<body>
<div class="page">

  <div class="brand">
    <img class="brand-logo" src="images/cardboard_icon_180.png" alt="CardBoard">
    <div class="brand-name">CardBoard</div>
    <div class="brand-tagline">Design and print cards from your Google Sheet</div>
  </div>

  <!-- First visit: start the free week or log in. Nothing else happens until then. -->
  <div class="card" id="authCard" hidden>
    <div id="authChoose">
      <div class="card-title" style="text-align:center">Welcome</div>
      <p class="auth-msg" style="text-align:center">Sign in to design your cards. New to CardBoard? Your first week is free, with every feature.</p>
      <div class="choose">
        <button type="button" class="opt primary" onclick="authMode('signup')"><b>Start your free week</b><span>Every feature for 7 days · no card needed</span></button>
        <button type="button" class="opt" onclick="authMode('login')"><b>Log in</b><span>I already have a GameBoard account</span></button>
      </div>
    </div>
    <form id="authEmail" hidden onsubmit="event.preventDefault();authSubmit()">
      <div class="card-title" id="authTitle">Log in</div>
      <p class="auth-msg" id="authMsg"></p>
      <div class="auth-col">
        <input class="sheet-input" id="authAddr" type="email" autocomplete="email" placeholder="Email" aria-label="Email">
        <input class="sheet-input" id="authPass" type="password" autocomplete="current-password" placeholder="Password" aria-label="Password">
        <input class="sheet-input" id="authPass2" type="password" autocomplete="new-password" placeholder="Password again" aria-label="Password again">
        <ul class="pw-rules" id="authRules" aria-live="polite">
          <li data-rule="len">At least 8 characters</li><li data-rule="letter">A letter</li><li data-rule="num">A number</li><li data-rule="match">Both passwords match</li>
        </ul>
        <button type="submit" class="connect-btn" id="authSendBtn">Log in</button>
      </div>
      <div class="auth-err" id="authErr1"></div>
      <div class="auth-foot"><button type="button" class="link-btn" id="authForgot" onclick="authMode('reset')">Forgot password?</button><span id="authForgotSep"> &nbsp;·&nbsp; </span><button type="button" class="link-btn" id="authSwitch" onclick="authMode(_mode === 'login' ? 'signup' : 'login')"></button> &nbsp;·&nbsp; <button type="button" class="link-btn" onclick="authMode('')">Back</button></div>
    </form>
    <form id="authCode" hidden onsubmit="event.preventDefault();authVerify()">
      <div class="card-title">Check your email</div>
      <p class="auth-msg" id="authSent"></p>
      <div class="auth-col"><input class="sheet-input code-input" id="authCodeIn" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" aria-label="Code" oninput="var v=this.value.replace(/\D/g,'').slice(0,6);if(v!==this.value)this.value=v">
        <button type="submit" class="connect-btn" id="authGo">Confirm</button></div>
      <div class="auth-err" id="authErr2"></div>
      <div class="auth-foot"><button type="button" class="link-btn" onclick="authMode(_mode)">Back</button></div>
    </form>
  </div>

  <div class="who" id="whoLine" hidden></div>

  <div class="card" id="connectCard" hidden>
    <div class="card-title">Connect Your Google Sheet</div>
    <div class="instructions">
      Keep your cards in a Google Sheet — one tab per deck, one row per card, one column per field (Name, Cost, Text, Image…). CardBoard turns each row into a card using a layout you design, then lays them out on print sheets.
      <details class="why">
        <summary>Why a Google Sheet?</summary>
        <p><b>It’s where your cards live.</b> Your cards, your layouts and your tags are all saved in your sheet (CardBoard adds a <i>layouts</i> and a <i>tags</i> tab for its part) — CardBoard is the designer, the sheet is the home. That’s why it needs one to start.</p>
        <ul>
          <li><b>Yours to keep.</b> The sheet stays in your Google Drive. Stop using CardBoard any time and everything is still there.</li>
          <li><b>Edit cards the fast way.</b> Change numbers across a whole deck, sort, filter, use formulas or paste in a list — then Fetch, and every card follows.</li>
          <li><b>Work with others.</b> Share the sheet with co-designers or playtesters the usual Google way.</li>
          <li><b>Nothing gets lost.</b> Google keeps the sheet’s version history, so you can always go back.</li>
        </ul>
        <p><b>Why share it with that address?</b> It’s CardBoard’s own Google account — it lets CardBoard read your cards and save your designs back. It only sees the sheets you share with it, and you can remove it whenever you like.</p>
      </details>
      <ol>
        <li>Open (or create) a Google Spreadsheet and share it with the CardBoard service account.</li>
        <li>Click <strong>Share</strong> and add <code>editor@zapsheets-480701.iam.gserviceaccount.com</code> <button class="copy-email-btn" id="copyEmailBtn" onclick="copyEmail()">Copy</button> with <strong>Editor</strong> rights.</li>
        <li>Paste the spreadsheet URL or ID below.</li>
      </ol>
    </div>

    <input type="text" id="sheetInput" class="sheet-input"
      placeholder="https://docs.google.com/spreadsheets/d/… or sheet ID"
      onkeydown="if(event.key==='Enter')startSetup()"
      autocomplete="off" spellcheck="false" />

    <button id="connectBtn" class="connect-btn" onclick="startSetup()">
      Connect &amp; Open
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

  // ── Account (the same one the designer keeps in this browser) ──────────────
  var acct = null;
  try { acct = JSON.parse(localStorage.getItem('cb_account') || 'null'); } catch (e) {}
  window._mode = '';
  function $(id) { return document.getElementById(id); }
  function esc(s) { return String(s).replace(/[&<>"]/g, function(c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function post(fields) {
    var fd = new FormData(); Object.keys(fields).forEach(function(k) { fd.append(k, fields[k]); });
    return fetch(APP_BASE + 'push/gameboardAccount.php', { method: 'POST', body: fd }).then(function(r) { return r.json(); })
      .then(function(r) { if (!r || r.error) throw new Error(r && r.error ? r.error : 'No response'); return r; });
  }
  function show() {
    document.body.classList.toggle('signed-out', !acct);
    $('authCard').hidden = !!acct; $('connectCard').hidden = !acct; $('whoLine').hidden = !acct;
    if (acct) {
      var a = acct.account || {}, left = Math.max(0, Math.ceil((new Date(a.until).getTime() - Date.now()) / 864e5));
      $('whoLine').innerHTML = 'Signed in as <b>' + esc(a.email || '') + '</b>' +
        (a.state === 'trial' ? ' · ' + left + (left === 1 ? ' day' : ' days') + ' of your free week left' : a.state === 'locked' ? ' · free week ended' : '') +
        ' · <button type="button" class="link-btn" onclick="authSignOut()">Sign out</button>';
      setTimeout(function() { $('sheetInput').focus(); }, 30);
    } else authMode('');
  }
  // '' = the two choices; login = email + password; signup / reset = email + password, then a code from the email
  var pending = null;
  window.authMode = function(m) {
    window._mode = m; pending = null;
    $('authChoose').hidden = !!m; $('authEmail').hidden = !m; $('authCode').hidden = true;
    $('authErr1').textContent = ''; $('authErr2').textContent = '';
    $('authTitle').textContent = { login: 'Log in', signup: 'Start your free week', reset: 'Reset your password' }[m] || '';
    $('authMsg').textContent = { login: 'Use your GameBoard email and password.',
      signup: 'Every feature for 7 days — no card needed. Choose a password; we’ll email you a code to confirm.',
      reset: 'Choose a new password; we’ll email you a code to confirm it’s you.' }[m] || '';
    $('authPass').value = ''; $('authPass2').value = '';
    $('authPass').placeholder = m === 'login' ? 'Password' : 'Password (8 or more characters)';
    $('authPass').setAttribute('autocomplete', m === 'login' ? 'current-password' : 'new-password');
    $('authPass2').hidden = $('authRules').hidden = m === 'login'; checkRules();
    $('authForgot').hidden = $('authForgotSep').hidden = m !== 'login';
    $('authSendBtn').textContent = { login: 'Log in', signup: 'Create account', reset: 'Email me a code' }[m] || '';
    $('authSwitch').textContent = m === 'login' ? 'New here? Start your free week' : 'Already have an account? Log in';
    if (m) setTimeout(function() { ($('authAddr').value ? $('authPass') : $('authAddr')).focus(); }, 30);
  };
  function signedIn(r) {
    acct = { token: r.token, account: r.account, sheet: '', checked: Date.now() };
    try { localStorage.setItem('cb_account', JSON.stringify(acct)); } catch (e) {}
    show();
  }
  window.authSubmit = function() {
    var m = window._mode, em = $('authAddr').value.trim(), pw = $('authPass').value, err = $('authErr1');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) { err.textContent = 'Enter your email address.'; return; }
    if (m === 'login') { if (!pw) { err.textContent = 'Enter your password.'; return; } }
    else if (!checkRules()) { err.textContent = 'Your password needs everything on the list.'; return; }
    var b = $('authSendBtn'), label = b.textContent; b.disabled = true; b.textContent = m === 'login' ? 'Logging in…' : 'Sending…'; err.textContent = '';
    var done = function() { b.disabled = false; b.textContent = label; };
    if (m === 'login') {
      post({ action: 'login', email: em, password: pw }).then(signedIn).catch(function(e) { err.textContent = e.message; }).then(done);
      return;
    }
    post({ action: 'send', email: em, mode: m }).then(function(r) {
      pending = { email: em, password: pw };
      $('authEmail').hidden = true; $('authCode').hidden = false; $('authCodeIn').value = r.debug_code || '';
      $('authSent').innerHTML = 'We sent a 6-digit code to <b>' + esc(em) + '</b>. Enter it to ' + (m === 'reset' ? 'set your new password.' : 'confirm your email and start your free week.') + ' It works for 15 minutes.';
      $('authGo').textContent = m === 'reset' ? 'Set password' : 'Start my free week';
      setTimeout(function() { $('authCodeIn').focus(); }, 30);
    }).catch(function(e) { err.textContent = e.message; }).then(done);
  };
  // New password: tick off each rule as it's met (the server checks the same rules)
  function checkRules() {
    var pw = $('authPass').value, all = true, ok = {
      len: pw.length >= 8, letter: /\p{L}/u.test(pw), num: /\d/.test(pw), match: pw !== '' && pw === $('authPass2').value };
    $('authRules').querySelectorAll('li').forEach(function(li) { var y = ok[li.dataset.rule]; li.classList.toggle('ok', y); if (!y) all = false; });
    return all;
  }
  function onPw() { if (checkRules()) $('authErr1').textContent = ''; }
  $('authPass').addEventListener('input', onPw); $('authPass2').addEventListener('input', onPw);
  window.authVerify = function() {
    var code = $('authCodeIn').value.replace(/\D/g, '');
    if (!pending) { authMode(window._mode); return; }
    if (code.length !== 6) { $('authErr2').textContent = 'Enter the 6-digit code from the email.'; return; }
    var b = $('authGo'), label = b.textContent; b.disabled = true; b.textContent = 'Checking…'; $('authErr2').textContent = '';
    post({ action: 'verify', email: pending.email, password: pending.password, code: code }).then(function(r) { pending = null; signedIn(r); })
      .catch(function(e) { $('authErr2').textContent = e.message; }).then(function() { b.disabled = false; b.textContent = label; });
  };
  window.authSignOut = function() { acct = null; try { localStorage.removeItem('cb_account'); } catch (e) {} show(); };
  show();
  // Coming from the GameBoard home on mygameboard.app: the account comes along in the link (#gbt=…)
  (function() {
    var m = /[#&]gbt=([^&]+)/.exec(location.hash || ''); if (!m) return;
    history.replaceState(null, '', location.pathname + location.search);
    post({ action: 'refresh', cbt: decodeURIComponent(m[1]) }).then(signedIn).catch(function() {});
  })();

  window.startSetup = function() {
    if (!acct) { show(); return; }
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
    _pending.push(setTimeout(function() { addLog('Reading the decks…', 'inf'); }, 700));

    var xhr = new XMLHttpRequest();
    xhr.open('POST', APP_BASE + 'push/cardboardData.php');
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

    xhr.onload = function() {
      cancelPending();
      var result;
      try { result = JSON.parse(xhr.responseText); } catch(e) { result = null; }

      if (result && result.need_auth) {   // the saved sign-in is too old: sign in again
        if (result.refresh && acct) {
          post({ action: 'refresh', cbt: acct.token }).then(function(r) {
            acct = { token: r.token, account: r.account, sheet: '', checked: Date.now() };
            try { localStorage.setItem('cb_account', JSON.stringify(acct)); } catch (e) {}
            btn.disabled = false; btn.textContent = 'Connect & Open'; startSetup();
          }).catch(function() { authSignOut(); });
        } else authSignOut();
        return;
      }
      if (!result || result.error) {
        var errMsg = (result && result.error) || xhr.responseText || 'Unknown error';
        var isPermErr = errMsg.indexOf('403') !== -1
                     || errMsg.toLowerCase().indexOf('permission') !== -1
                     || errMsg.toLowerCase().indexOf('could not open') !== -1;
        if (isPermErr) {
          addLog('✕  Sheet not shared with the CardBoard service account.', 'err');
          addLog('   Add editor@zapsheets-480701.iam.gserviceaccount.com (Editor) and try again.', 'err');
        } else {
          addLog('✕  ' + errMsg, 'err');
        }
        btn.disabled    = false;
        btn.textContent = 'Connect & Open';
        return;
      }

      if (result.title) addLog('✓  Sheet: ' + result.title, 'ok');
      var tabs = result.tabs || [];
      addLog('✓  ' + tabs.length + (tabs.length === 1 ? ' deck: ' : ' decks: ') + tabs.map(function(t) { return t.title; }).join(', '), 'ok');
      // Connecting sets the sheet up as yours (unless someone else already has): you design it, others view it
      post({ action: 'claim', cbt: acct.token, sheet: sheetId }).then(function(r) {
        acct = { token: r.token, account: r.account, sheet: sheetId, checked: Date.now() };
        try { localStorage.setItem('cb_account', JSON.stringify(acct)); } catch (e) {}
        addLog(r.account && r.account.owner ? '✓  Set up as yours' : '✓  Someone else set this sheet up — it opens view only', 'ok');
      }).catch(function(e) { addLog('!  ' + e.message, 'err'); }).then(function() {
        addLog('✓  Ready — opening CardBoard…', 'ok');
        setTimeout(function() { window.location.href = APP_BASE + sheetId + '/cardboard'; }, 800);
      });
    };

    xhr.onerror = function() {
      cancelPending();
      addLog('✕  Network error — could not reach the server.', 'err');
      btn.disabled    = false;
      btn.textContent = 'Connect & Open';
    };

    xhr.send('action=tabs&id=' + encodeURIComponent(sheetId) + '&cbt=' + encodeURIComponent(acct ? acct.token : ''));
  };
})();
</script>
</body>
</html>
