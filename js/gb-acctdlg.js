/**
 * gb-acctdlg.js — the Account dialog, the same in every GameBoard app (menu → Account):
 * your picture, name and studio, the email you sign in with, your password, your plan, and codes.
 *
 *   GBAccountDialog.open({
 *     base: 'https://…/',            // where push/ is
 *     app: 'pitchboard', sheet: id,  // the app asking (the account's plan is that app's)
 *     account: fn → account, token: fn → token, store: fn(response),   // the app's own account keeping
 *     plan: fn → html (optional),   plans: fn (optional, opens the plans)
 *     toast: fn(msg) (optional),    signOut: fn (optional)
 *   })
 */
(function() {
  if (window.GBAccountDialog) return;
  var O = {}, built = false;
  function $(id) { return document.getElementById(id); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function(c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function acc() { try { return (O.account && O.account()) || {}; } catch (e) { return {}; } }
  function toast(m) { if (O.toast) try { O.toast(m); return; } catch (e) {} }
  function post(action, fields, file) {
    var fd = new FormData(); fd.append('action', action); fd.append('app', O.app || ''); fd.append('sheet', O.sheet || '');
    fd.append('cbt', (O.token && O.token()) || '');
    Object.keys(fields || {}).forEach(function(k) { fd.append(k, fields[k]); });
    if (file) fd.append('file', file, 'picture.jpg');
    return fetch((O.base || '/') + 'push/gameboardAccount.php', { method: 'POST', body: fd })
      .then(function(r) { return r.json(); }, function() { throw new Error('Couldn’t reach the server'); })
      .then(function(r) { if (!r || r.error) throw new Error(r && r.error ? r.error : 'No response'); return r; });
  }
  function keep(r) { if (r && r.token && O.store) O.store(r); fill(); }

  var CSS =
    '.gbd-ov{position:fixed;inset:0;z-index:10000;display:none;align-items:center;justify-content:center;padding:1rem;background:rgba(20,19,42,.55);-webkit-backdrop-filter:blur(2px);backdrop-filter:blur(2px)}.gbd-ov.open{display:flex}' +
    '.gbd-ov{overscroll-behavior:contain}.gbd{overscroll-behavior:contain}.gbd.shake{animation:gbdShake .35s}@keyframes gbdShake{0%,100%{transform:translateX(0)}20%,60%{transform:translateX(-7px)}40%,80%{transform:translateX(7px)}}' +
    '.gbd{position:relative;background:#fff;color:#1b1d2b;border-radius:16px;box-shadow:0 18px 50px rgba(20,19,42,.3);width:100%;max-width:460px;max-height:92vh;overflow:auto;padding:1.5rem 1.5rem 1.2rem;font:15px/1.4 var(--gb-text,"DINRegular",Arial,sans-serif);text-align:left;animation:gbdPop .18s ease-out}' +
    '@keyframes gbdPop{from{opacity:0;transform:scale(.96)}}' +
    '.gbd *{box-sizing:border-box}.gbd [hidden]{display:none!important}' +
    '.gbd-x{position:absolute;top:.55rem;right:.7rem;background:none;border:none;font-size:1.45rem;line-height:1;color:#8a90a6;cursor:pointer}' +
    '.gbd h2{margin:0 0 1rem;font:normal 1.05rem var(--gb-black,"DINBlack",Arial,sans-serif);text-transform:uppercase;letter-spacing:.08em;color:#1b1d2b}' +
    '.gbd-me{display:flex;align-items:center;gap:1rem;margin:0 0 1.1rem}' +
    '.gbd-pic{position:relative;flex:none;width:76px;height:76px;border-radius:50%;background:var(--gb-accent,#5b4fd6);color:#fff;display:flex;align-items:center;justify-content:center;font:normal 1.7rem var(--gb-black,"DINBlack",Arial,sans-serif);cursor:pointer;overflow:hidden;box-shadow:0 0 0 3px #fff,0 0 0 5px #e7e9f1}' +
    '.gbd-pic #gbdIni{font:normal 2rem/1 var(--gb-black,"DINBlack",Arial,sans-serif)!important;color:#fff!important;letter-spacing:0;text-transform:uppercase;margin:0}' +
    '.gbd-pic img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}' +
    '.gbd-pic .cam{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45);opacity:0;transition:opacity .15s}.gbd-pic:hover .cam,.gbd-pic.drop .cam,.gbd-pic.busy .cam{opacity:1}' +
    '.gbd-pic .cam svg{width:26px;height:26px}' +
    '.gbd-me b{display:block;font:normal 1.1rem var(--gb-black,"DINBlack",Arial,sans-serif)}.gbd-me span{display:block;color:#6b7090;font-size:.85rem;word-break:break-all}' +
    '.gbd-me button{margin-top:.25rem;padding:0;background:none;border:none;color:#6b7090;font:inherit;font-size:.78rem;text-decoration:underline;text-underline-offset:2px;cursor:pointer}' +
    '.gbd-sec{border-top:1px solid #eceef4;padding:.85rem 0}' +
    '.gbd-row{display:flex;align-items:center;gap:.6rem}.gbd-row>div{flex:1;min-width:0}' +
    '.gbd-lbl{display:block;font:normal .68rem var(--gb-black,"DINBlack",Arial,sans-serif);text-transform:uppercase;letter-spacing:.09em;color:#8a90a6;margin:0 0 .25rem}' +
    '.gbd-val{display:block;font-size:.92rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}' +
    '.gbd input{display:block;width:100%;margin:0 0 .5rem;padding:.55rem .7rem;border:1.5px solid #d6dbe5;border-radius:8px;font:inherit;font-size:.92rem;background:#fff;color:#1b1d2b}' +
    '.gbd input:focus{outline:none;border-color:var(--gb-accent,#5b4fd6);box-shadow:0 0 0 3px var(--gb-accent-lt,#ece9ff)}' +
    '.gbd-two{display:grid;grid-template-columns:1fr 1fr;gap:.5rem}.gbd-two input{margin:0}' +
    '.gbd-btn{flex:none;padding:.45rem .85rem;border:none;border-radius:8px;background:var(--gb-accent,#5b4fd6);color:#fff;font:normal .72rem var(--gb-black,"DINBlack",Arial,sans-serif);text-transform:uppercase;letter-spacing:.07em;cursor:pointer;white-space:nowrap}' +
    '.gbd-btn:hover:not(:disabled){background:var(--gb-accent-dk,#4b3fa8)}.gbd-btn:disabled{opacity:.5;cursor:default}' +
    '.gbd-btn.ghost{background:#fff;color:#4a5068;border:1.5px solid #d6dbe5}.gbd-btn.ghost:hover:not(:disabled){background:#fff;border-color:var(--gb-accent,#5b4fd6);color:var(--gb-accent-dk,#4b3fa8)}' +
    '.gbd-panel{margin:.7rem 0 0;padding:.8rem;border-radius:10px;background:#f6f7fb}' +
    '.gbd-panel p{margin:0 0 .6rem;font-size:.82rem;color:#4a5068}' +
    '.gbd-acts{display:flex;justify-content:flex-end;gap:.45rem;margin-top:.15rem}' +
    '.gbd-err{min-height:0;margin:.4rem 0 0;color:#c0392b;font-size:.8rem}.gbd-err:empty{display:none}' +
    '.gbd-rules{list-style:none;margin:-.1rem 0 .5rem;padding:0;font-size:.74rem;color:#8a90a6;display:grid;grid-template-columns:1fr 1fr;gap:.1rem .6rem}' +
    '.gbd-rules li::before{content:"○ "}.gbd-rules li.ok{color:#2e9d4c}.gbd-rules li.ok::before{content:"✓ "}' +
    '.gbd-plan{font-size:.92rem}.gbd-plan b{font-family:var(--gb-black,"DINBlack",Arial,sans-serif);font-weight:normal}' +
    '.gbd-code{display:flex;gap:.45rem;margin-top:.5rem}.gbd-code input{margin:0;flex:1}' +
    '.gbd-foot{display:flex;justify-content:space-between;align-items:center;border-top:1px solid #eceef4;padding-top:.9rem}' +
    '.gbd-link{padding:0;background:none;border:none;color:#6b7090;font:inherit;font-size:.82rem;text-decoration:underline;text-underline-offset:2px;cursor:pointer}' +
    '@media (max-width:480px){.gbd-two{grid-template-columns:1fr}.gbd-two input{margin:0 0 .5rem}}';

  var CAM = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M4 8h3l2-2.5h6L17 8h3v11H4z"/><circle cx="12" cy="13" r="3.5"/></svg>';
  function build() {
    if (built) return; built = true;
    var st = document.createElement('style'); st.textContent = CSS; document.head.appendChild(st);
    var ov = document.createElement('div'); ov.className = 'gbd-ov'; ov.id = 'gbdOv';
    ov.innerHTML =
      '<div class="gbd" role="dialog" aria-modal="true" aria-labelledby="gbdTitle">' +
        '<button type="button" class="gbd-x" id="gbdX" aria-label="Close">×</button>' +
        '<h2 id="gbdTitle">Account</h2>' +
        '<div class="gbd-me"><label class="gbd-pic" id="gbdPic" title="Change your picture — click, or drop one here"><span id="gbdIni"></span><img id="gbdImg" alt="" hidden><span class="cam">' + CAM + '</span><input type="file" accept="image/*" hidden id="gbdFile"></label>' +
          '<div><b id="gbdName"></b><span id="gbdMail"></span><button type="button" id="gbdPicRm" hidden>Remove picture</button><div class="gbd-err" id="gbdPicErr"></div></div></div>' +
        // Name and studio
        '<div class="gbd-sec"><div class="gbd-two"><div><label class="gbd-lbl" for="gbdNameIn">Name</label><input id="gbdNameIn" maxlength="80" autocomplete="name"></div>' +
          '<div><label class="gbd-lbl" for="gbdStudioIn">Studio or company</label><input id="gbdStudioIn" maxlength="80" autocomplete="organization"></div></div>' +
          '<div class="gbd-acts" style="margin-top:.55rem"><button type="button" class="gbd-btn" id="gbdProfSave" disabled>Save</button></div><div class="gbd-err" id="gbdProfErr"></div></div>' +
        // Email
        '<div class="gbd-sec"><div class="gbd-row"><div><span class="gbd-lbl">Email</span><span class="gbd-val" id="gbdMailVal"></span></div><button type="button" class="gbd-btn ghost" id="gbdMailBtn">Change</button></div>' +
          '<form class="gbd-panel" id="gbdMailForm" hidden><p id="gbdMailMsg">We’ll send a code to the new address to make sure it’s yours. Your sheets, plans and codes stay with your account.</p>' +
            '<input type="email" id="gbdMailNew" placeholder="New email" autocomplete="email" autocapitalize="off" spellcheck="false">' +
            '<input type="password" id="gbdMailPw" placeholder="Your password" autocomplete="current-password">' +
            '<input type="text" id="gbdMailCode" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="6-digit code" hidden style="text-align:center;letter-spacing:.3em;font-size:1.1rem">' +
            '<div class="gbd-acts"><button type="button" class="gbd-btn ghost" id="gbdMailCancel">Cancel</button><button type="submit" class="gbd-btn" id="gbdMailGo">Send code</button></div><div class="gbd-err" id="gbdMailErr"></div></form></div>' +
        // Password
        '<div class="gbd-sec"><div class="gbd-row"><div><span class="gbd-lbl">Password</span><span class="gbd-val">••••••••</span></div><button type="button" class="gbd-btn ghost" id="gbdPwBtn">Change</button></div>' +
          '<form class="gbd-panel" id="gbdPwForm" hidden>' +
            '<input type="password" id="gbdPwCur" placeholder="Current password" autocomplete="current-password">' +
            '<input type="password" id="gbdPw1" placeholder="New password" autocomplete="new-password"><input type="password" id="gbdPw2" placeholder="New password again" autocomplete="new-password">' +
            '<ul class="gbd-rules" id="gbdRules"><li data-rule="len">At least 8 characters</li><li data-rule="letter">A letter</li><li data-rule="num">A number</li><li data-rule="match">Both match</li></ul>' +
            '<div class="gbd-acts"><button type="button" class="gbd-btn ghost" id="gbdPwCancel">Cancel</button><button type="submit" class="gbd-btn" id="gbdPwGo" disabled>Save password</button></div><div class="gbd-err" id="gbdPwErr"></div></form></div>' +
        // Plan and codes
        '<div class="gbd-sec"><div class="gbd-row"><div><span class="gbd-lbl">Plan</span><div class="gbd-plan" id="gbdPlan"></div></div><button type="button" class="gbd-btn" id="gbdPlans">Plans</button></div>' +
          '<form class="gbd-code" id="gbdCodeForm"><input type="text" id="gbdCode" placeholder="Have a code? Enter it here" autocomplete="off" autocapitalize="off" spellcheck="false"><button type="submit" class="gbd-btn ghost" id="gbdCodeGo">Use code</button></form><div class="gbd-err" id="gbdCodeErr"></div></div>' +
        '<div class="gbd-foot"><button type="button" class="gbd-link" id="gbdOut">Sign out</button><button type="button" class="gbd-btn" id="gbdDone">Done</button></div>' +
      '</div>';
    document.body.appendChild(ov);
    ov.addEventListener('click', function(e) { if (e.target === ov) close(); });
    ov.addEventListener('keydown', function(e) { e.stopPropagation(); if (e.key === 'Escape') { e.preventDefault(); escClose(); } });
    $('gbdX').onclick = close; $('gbdDone').onclick = close;
    $('gbdOut').onclick = function() { close(); if (O.signOut) O.signOut(); };
    // Picture
    $('gbdFile').onchange = function() { var f = this.files && this.files[0]; this.value = ''; if (f) pic(f); };
    var p = $('gbdPic');
    p.addEventListener('dragover', function(e) { e.preventDefault(); p.classList.add('drop'); });
    p.addEventListener('dragleave', function() { p.classList.remove('drop'); });
    p.addEventListener('drop', function(e) { e.preventDefault(); p.classList.remove('drop'); var f = e.dataTransfer.files && e.dataTransfer.files[0]; if (f) pic(f); });
    $('gbdPicRm').onclick = function() { pic(null); };
    // Name and studio
    ['gbdNameIn', 'gbdStudioIn'].forEach(function(id) { $(id).addEventListener('input', profDirty); $(id).addEventListener('keydown', function(e) { if (e.key === 'Enter') saveProf(); }); });
    $('gbdProfSave').onclick = saveProf;
    // Email
    $('gbdMailBtn').onclick = function() { mailOpen(true); };
    $('gbdMailCancel').onclick = function() { mailOpen(false); };
    $('gbdMailForm').addEventListener('submit', function(e) { e.preventDefault(); mailGo(); });
    // Password
    $('gbdPwBtn').onclick = function() { pwOpen(true); };
    $('gbdPwCancel').onclick = function() { pwOpen(false); };
    ['gbdPw1', 'gbdPw2', 'gbdPwCur'].forEach(function(id) { $(id).addEventListener('input', rules); });
    $('gbdPwForm').addEventListener('submit', function(e) { e.preventDefault(); pwGo(); });
    // Plan and codes
    $('gbdPlans').onclick = function() { close(); if (O.plans) O.plans(); };
    $('gbdCodeForm').addEventListener('submit', function(e) { e.preventDefault(); codeGo(); });
  }

  function fill() {
    var a = acc(), nm = String(a.name || '').trim() || String(a.email || '').split('@')[0];
    $('gbdName').textContent = nm || 'Your account'; $('gbdMail').textContent = a.email || ''; $('gbdMailVal').textContent = a.email || '';
    $('gbdIni').textContent = (nm || '?').trim().charAt(0).toUpperCase();
    var img = a.image ? String(a.image) : '';
    if (img && $('gbdImg').getAttribute('src') !== img) $('gbdImg').src = img;
    $('gbdImg').hidden = !img; $('gbdPicRm').hidden = !img;
    var plan = ''; try { plan = O.plan ? O.plan() : ''; } catch (e) {}
    $('gbdPlan').innerHTML = plan || '—';
    $('gbdPlans').hidden = !O.plans; $('gbdOut').hidden = !O.signOut;
  }
  function open(opts) {
    O = opts || {}; build();
    var a = acc();
    $('gbdNameIn').value = a.name || ''; $('gbdStudioIn').value = a.studio || '';
    $('gbdProfSave').disabled = true; $('gbdCode').value = '';
    ['gbdPicErr', 'gbdProfErr', 'gbdCodeErr'].forEach(function(id) { $(id).textContent = ''; });
    mailOpen(false); pwOpen(false); fill();
    if (!$('gbdOv').classList.contains('open')) {
      // Keep the page underneath still while the dialog is open
      _scroll = { html: document.documentElement.style.overflow, body: document.body.style.overflow };
      document.documentElement.style.overflow = 'hidden'; document.body.style.overflow = 'hidden';
      document.addEventListener('keydown', onKey, true);
    }
    $('gbdOv').classList.add('open');
  }
  function close() {
    if (!built || !$('gbdOv').classList.contains('open')) return;
    $('gbdOv').classList.remove('open');
    document.removeEventListener('keydown', onKey, true);
    // The page underneath scrolls again
    document.documentElement.style.overflow = _scroll.html; document.body.style.overflow = _scroll.body;
  }
  // Esc: closes when nothing's been typed; with changes not saved, a shake instead
  var _scroll = { html: '', body: '' };
  function onKey(e) { if (e.key === 'Escape' && $('gbdOv').classList.contains('open')) { e.preventDefault(); e.stopPropagation(); escClose(); } }
  function edited() {
    var v = function(id) { var el = $(id); return el && !el.closest('[hidden]') && String(el.value || '').trim() !== ''; };
    return !$('gbdProfSave').disabled || v('gbdMailNew') || v('gbdMailPw') || v('gbdMailCode') || v('gbdPw1') || v('gbdPw2') || v('gbdPwCur') || v('gbdCode');
  }
  function escClose() {
    if (!edited()) { close(); return; }
    var d = document.querySelector('#gbdOv .gbd'); d.classList.remove('shake'); void d.offsetWidth; d.classList.add('shake');
  }

  // ── Picture: made small here (a square, 512 px), squared again on the server ──
  function shrink(f) {
    return new Promise(function(res, rej) {
      if (!/^image\//.test(f.type)) return rej(new Error('Pick a picture (JPEG, PNG, GIF or WebP)'));
      var u = URL.createObjectURL(f), im = new Image();
      im.onload = function() {
        var s = Math.min(im.naturalWidth, im.naturalHeight), N = Math.min(512, s), c = document.createElement('canvas'); c.width = c.height = N;
        var x = c.getContext('2d'); x.fillStyle = '#fff'; x.fillRect(0, 0, N, N);
        x.drawImage(im, (im.naturalWidth - s) / 2, (im.naturalHeight - s) / 2, s, s, 0, 0, N, N);
        URL.revokeObjectURL(u);
        c.toBlob(function(b) { b ? res(b) : rej(new Error('Couldn’t read that picture')); }, 'image/jpeg', .88);
      };
      im.onerror = function() { URL.revokeObjectURL(u); rej(new Error('Couldn’t read that picture')); };
      im.src = u;
    });
  }
  function pic(f) {
    var p = $('gbdPic'); p.classList.add('busy'); $('gbdPicErr').textContent = '';
    (f ? shrink(f) : Promise.resolve(null)).then(function(b) { return post('image', {}, b); })
      .then(function(r) { keep(r); toast(f ? '✓ Picture changed' : 'Picture removed'); })
      .catch(function(e) { $('gbdPicErr').textContent = e.message; })
      .then(function() { p.classList.remove('busy'); });
  }

  // ── Name and studio ──
  function profDirty() { var a = acc(); $('gbdProfSave').disabled = $('gbdNameIn').value.trim() === (a.name || '') && $('gbdStudioIn').value.trim() === (a.studio || ''); }
  function saveProf() {
    var b = $('gbdProfSave'); if (b.disabled) return;
    b.disabled = true; b.textContent = 'Saving…'; $('gbdProfErr').textContent = '';
    post('profile', { name: $('gbdNameIn').value.trim(), studio: $('gbdStudioIn').value.trim() })
      .then(function(r) { keep(r); toast('✓ Saved'); })
      .catch(function(e) { $('gbdProfErr').textContent = e.message; b.disabled = false; })
      .then(function() { b.textContent = 'Save'; profDirty(); });
  }

  // ── Email: a code to the new address, and your password ──
  var _mailStep = 0;
  function mailOpen(on) {
    $('gbdMailForm').hidden = !on; $('gbdMailBtn').hidden = on; _mailStep = 0;
    $('gbdMailNew').value = ''; $('gbdMailPw').value = ''; $('gbdMailCode').value = ''; $('gbdMailErr').textContent = '';
    $('gbdMailNew').disabled = $('gbdMailPw').disabled = false; $('gbdMailCode').hidden = true; $('gbdMailGo').textContent = 'Send code'; $('gbdMailGo').disabled = false;
    $('gbdMailMsg').textContent = 'We’ll send a code to the new address to make sure it’s yours. Your sheets, plans and codes stay with your account.';
    if (on) setTimeout(function() { $('gbdMailNew').focus(); }, 30);
  }
  function mailGo() {
    var em = $('gbdMailNew').value.trim(), pw = $('gbdMailPw').value, b = $('gbdMailGo'); $('gbdMailErr').textContent = '';
    if (!_mailStep) {
      if (!/^\S+@\S+\.\S+$/.test(em)) { $('gbdMailErr').textContent = 'Enter the new email address'; $('gbdMailNew').focus(); return; }
      if (!pw) { $('gbdMailErr').textContent = 'Enter your password'; $('gbdMailPw').focus(); return; }
      b.disabled = true; b.textContent = 'Sending…';
      post('send', { email: em, mode: 'change' }).then(function() {
        _mailStep = 1; $('gbdMailNew').disabled = $('gbdMailPw').disabled = true; $('gbdMailCode').hidden = false;
        $('gbdMailMsg').innerHTML = 'We sent a 6-digit code to <b>' + esc(em) + '</b>. Enter it to finish.';
        b.textContent = 'Change email'; setTimeout(function() { $('gbdMailCode').focus(); }, 30);
      }).catch(function(e) { $('gbdMailErr').textContent = e.message; b.textContent = 'Send code'; }).then(function() { b.disabled = false; });
      return;
    }
    var code = $('gbdMailCode').value.replace(/\D/g, '');
    if (code.length !== 6) { $('gbdMailErr').textContent = 'Enter the 6-digit code'; $('gbdMailCode').focus(); return; }
    b.disabled = true; b.textContent = 'Changing…';
    post('email', { email: em, code: code, current: pw }).then(function(r) {
      keep(r); mailOpen(false); toast('✓ You sign in with ' + em + ' now');
    }).catch(function(e) { $('gbdMailErr').textContent = e.message; b.textContent = 'Change email'; b.disabled = false; });
  }

  // ── Password ──
  function rules() {
    var p = $('gbdPw1').value, q = $('gbdPw2').value, ok = { len: p.length >= 8, letter: /\p{L}/u.test(p), num: /\d/.test(p), match: !!p && p === q };
    $('gbdRules').querySelectorAll('li').forEach(function(li) { li.classList.toggle('ok', !!ok[li.getAttribute('data-rule')]); });
    $('gbdPwGo').disabled = !(ok.len && ok.letter && ok.num && ok.match && $('gbdPwCur').value);
  }
  function pwOpen(on) {
    $('gbdPwForm').hidden = !on; $('gbdPwBtn').hidden = on;
    ['gbdPwCur', 'gbdPw1', 'gbdPw2'].forEach(function(id) { $(id).value = ''; }); $('gbdPwErr').textContent = ''; rules();
    if (on) setTimeout(function() { $('gbdPwCur').focus(); }, 30);
  }
  function pwGo() {
    var b = $('gbdPwGo'); if (b.disabled) return;
    b.disabled = true; b.textContent = 'Saving…'; $('gbdPwErr').textContent = '';
    post('password', { current: $('gbdPwCur').value, password: $('gbdPw1').value })
      .then(function(r) { keep(r); pwOpen(false); toast('✓ Password changed'); })
      .catch(function(e) { $('gbdPwErr').textContent = e.message; b.disabled = false; })
      .then(function() { b.textContent = 'Save password'; });
  }

  // ── Codes ──
  function codeGo() {
    var c = $('gbdCode').value.trim(), b = $('gbdCodeGo'); if (!c) { $('gbdCode').focus(); return; }
    b.disabled = true; b.textContent = 'Checking…'; $('gbdCodeErr').textContent = '';
    post('redeem', { code: c }).then(function(r) {
      keep(r); $('gbdCode').value = '';
      toast(r.apps && r.apps.length ? '✓ Code added for ' + r.apps.join(', ') : r.days > 0 ? '✓ Code added — ' + r.days + ' days' : '✓ Unlocked for good');
    }).catch(function(e) { $('gbdCodeErr').textContent = e.message; }).then(function() { b.disabled = false; b.textContent = 'Use code'; });
  }

  window.GBAccountDialog = { open: open, close: close, refresh: function() { if (built) fill(); } };
})();
