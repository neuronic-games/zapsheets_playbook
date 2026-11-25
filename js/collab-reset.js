/**
 * collab-reset.js — "Forgot password?" for the collaborator sign-in (DevBoard, PitchBoard, T&MBoard pages):
 * a code is emailed, then a new password is chosen. Uses push/collabAuth.php (action reset_send / reset).
 *
 *   CollabReset.attach({ pass: 'authPassword', email: 'authEmail', sheet: SHEET_ID, base: APP_BASE })
 * adds the link under the password box; after a reset the new password is put in that box, ready to sign in.
 */
(function() {
  if (window.CollabReset) return;
  var CSS =
    '.crs-link{display:inline-block;margin:.35rem 0 0;padding:0;border:none;background:none;color:#6b7280;font:inherit;font-size:.78rem;text-decoration:underline;text-underline-offset:2px;cursor:pointer}' +
    '.crs-link:hover{color:#111}' +
    '.crs-ov{position:fixed;inset:0;z-index:100000;display:none;align-items:center;justify-content:center;padding:1rem;background:rgba(15,20,30,.55)}.crs-ov.open{display:flex}' +
    '.crs{position:relative;width:100%;max-width:380px;background:#fff;color:#1b1d2b;border-radius:14px;box-shadow:0 18px 50px rgba(0,0,0,.3);padding:1.4rem 1.4rem 1.2rem;font-family:inherit;text-align:left}' +
    '.crs *{box-sizing:border-box}.crs [hidden]{display:none!important}' +
    '.crs h3{margin:0 0 .35rem;font-size:1.05rem}.crs p{margin:0 0 .8rem;font-size:.85rem;line-height:1.45;color:#4a5068}' +
    '.crs input{display:block;width:100%;margin:0 0 .55rem;padding:.6rem .7rem;border:1.5px solid #d6dbe5;border-radius:8px;font:inherit;font-size:.95rem;background:#fff;color:#111}' +
    '.crs input:focus{outline:none;border-color:#1a5f7a;box-shadow:0 0 0 3px rgba(26,95,122,.12)}' +
    '.crs .crs-code{text-align:center;letter-spacing:.3em;font-size:1.15rem}' +
    '.crs-acts{display:flex;justify-content:flex-end;gap:.5rem;margin-top:.3rem}' +
    '.crs-acts button{padding:.55rem 1rem;border-radius:8px;border:1.5px solid #d6dbe5;background:#fff;color:#4a5068;font:inherit;font-size:.85rem;cursor:pointer}' +
    '.crs-acts .go{background:#1a5f7a;border-color:#1a5f7a;color:#fff}.crs-acts .go:disabled{opacity:.6;cursor:default}' +
    '.crs-err{min-height:0;margin:.2rem 0 .3rem;color:#c0392b;font-size:.8rem}.crs-err:empty{display:none}' +
    '.crs-ok{margin:.2rem 0 .5rem;padding:.55rem .7rem;border-radius:8px;background:#e8f5ec;color:#226b3a;font-size:.84rem}';
  var built = false, O = null, step = 0;
  function $(id) { return document.getElementById(id); }
  function build() {
    if (built) return; built = true;
    var st = document.createElement('style'); st.textContent = CSS; document.head.appendChild(st);
    var ov = document.createElement('div'); ov.className = 'crs-ov'; ov.id = 'crsOv';
    ov.innerHTML = '<form class="crs" id="crsForm" novalidate><h3>Reset your password</h3>' +
      '<p id="crsMsg">We’ll email you a 6-digit code. Then choose a new password.</p>' +
      '<input type="email" id="crsEmail" placeholder="Email" autocomplete="email" autocapitalize="off" spellcheck="false">' +
      '<input type="text" id="crsCode" class="crs-code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="6-digit code" hidden>' +
      '<input type="password" id="crsPass" placeholder="New password (at least 4 characters)" autocomplete="new-password" hidden>' +
      '<input type="password" id="crsPass2" placeholder="New password again" autocomplete="new-password" hidden>' +
      '<div class="crs-ok" id="crsOk" hidden></div><div class="crs-err" id="crsErr"></div>' +
      '<div class="crs-acts"><button type="button" id="crsCancel">Cancel</button><button type="submit" class="go" id="crsGo">Send code</button></div></form>';
    document.body.appendChild(ov);
    ov.addEventListener('mousedown', function(e) { if (e.target === ov) close(); });
    ov.addEventListener('keydown', function(e) { e.stopPropagation(); if (e.key === 'Escape') close(); });
    $('crsCancel').onclick = close;
    $('crsForm').addEventListener('submit', function(e) { e.preventDefault(); go(); });
  }
  function close() { $('crsOv').classList.remove('open'); }
  function open(o) {
    O = o; build(); step = 0;
    var em = o.email && $(o.email) ? $(o.email).value.trim() : '';
    $('crsEmail').value = em; $('crsEmail').disabled = false;
    ['crsCode', 'crsPass', 'crsPass2'].forEach(function(id) { $(id).value = ''; $(id).hidden = true; });
    $('crsOk').hidden = true; $('crsErr').textContent = '';
    $('crsMsg').textContent = 'We’ll email you a 6-digit code. Then choose a new password.';
    $('crsGo').textContent = 'Send code'; $('crsGo').disabled = false; $('crsCancel').textContent = 'Cancel'; $('crsCancel').hidden = false; $('crsEmail').hidden = false;
    $('crsOv').classList.add('open');
    setTimeout(function() { (em ? $('crsGo') : $('crsEmail')).focus(); }, 40);
  }
  function post(fields) {
    var fd = new FormData(); fd.append('id', O.sheet || '');
    Object.keys(fields).forEach(function(k) { fd.append(k, fields[k]); });
    return fetch(String(O.base || '/').replace(/\/?$/, '/') + 'push/collabAuth.php', { method: 'POST', body: fd })
      .then(function(r) { return r.json(); }, function() { throw new Error('Couldn’t reach the server.'); })
      .then(function(r) { if (!r || r.error) throw new Error((r && r.error) || 'Something went wrong.'); return r; });
  }
  function go() {
    var err = $('crsErr'), btn = $('crsGo'), em = $('crsEmail').value.trim();
    err.textContent = '';
    if (step === 2) { close(); return; }
    if (step === 0) {
      if (!/^\S+@\S+\.\S+$/.test(em)) { err.textContent = 'Enter your email address.'; $('crsEmail').focus(); return; }
      btn.disabled = true; btn.textContent = 'Sending…';
      post({ action: 'reset_send', email: em }).then(function() {
        step = 1; $('crsEmail').disabled = true;
        $('crsMsg').textContent = 'We sent a code to ' + em + '. Enter it, and your new password.';
        ['crsCode', 'crsPass', 'crsPass2'].forEach(function(id) { $(id).hidden = false; });
        btn.textContent = 'Set password'; setTimeout(function() { $('crsCode').focus(); }, 30);
      }).catch(function(e) { err.textContent = e.message; btn.textContent = 'Send code'; }).then(function() { btn.disabled = false; });
      return;
    }
    var code = $('crsCode').value.replace(/\D/g, ''), p1 = $('crsPass').value, p2 = $('crsPass2').value;
    if (code.length !== 6) { err.textContent = 'Enter the 6-digit code from the email.'; $('crsCode').focus(); return; }
    if (p1.length < 4) { err.textContent = 'Use at least 4 characters.'; $('crsPass').focus(); return; }
    if (p1 !== p2) { err.textContent = 'The two passwords don’t match.'; $('crsPass2').focus(); return; }
    btn.disabled = true; btn.textContent = 'Saving…';
    post({ action: 'reset', email: em, code: code, password: p1 }).then(function() {
      step = 2;
      ['crsCode', 'crsPass', 'crsPass2', 'crsEmail'].forEach(function(id) { $(id).hidden = true; });
      $('crsMsg').textContent = '';
      $('crsOk').textContent = '✓ Your password is changed. Sign in with it now.'; $('crsOk').hidden = false;
      btn.textContent = 'Sign in'; $('crsCancel').hidden = true;
      if (O.email && $(O.email)) $(O.email).value = em;
      if (O.pass && $(O.pass)) $(O.pass).value = p1;
    }).catch(function(e) { err.textContent = e.message; btn.textContent = 'Set password'; }).then(function() { btn.disabled = false; });
  }
  window.CollabReset = {
    attach: function(o) {
      var go = function() {
        var p = $(o.pass); if (!p || p._crs) return; p._crs = true;
        var b = document.createElement('button'); b.type = 'button'; b.className = 'crs-link'; b.textContent = 'Forgot password?';
        b.onclick = function(e) { e.preventDefault(); e.stopPropagation(); open(o); };
        build();
        // under the password box (after its label/field wrapper when it has one)
        var host = p.closest('label, .field-group, .auth-field') || p;
        host.parentNode.insertBefore(b, host.nextSibling);
      };
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go); else go();
    },
    open: function(o) { open(o); }
  };
})();
