/**
 * gb-account.js — the GameBoard account in DevBoard, PitchBoard and BillBoard (CardBoard has its own copy of the same flow).
 *
 *   GBAccount.init({ app: 'devboard', name: 'DevBoard', sheet: SHEET_ID, base: APP_BASE, home: 'devboard',
 *                    plans: [{ plan: 'core', label: 'Core', price: '$2.99' }, { plan: 'pro', label: 'Pro', price: '$3.99' }],
 *                    intros: { pro: 'Text at the top of the plans for openPlans("pro")' }, icon: 'images/…png',
 *                    gate: false (anyone may look; sign in from the menu), onChange: function(state) {} })
 *
 * - Sign in with a GameBoard email and password (a code by email confirms a new account or a new password).
 *   The account is kept in this browser (localStorage cb_account, the same as CardBoard and the GameBoard home),
 *   so signing in once works in every app.
 * - Every request to push/*.php carries the account token (X-CB-Token) and the app (X-GB-App); the server
 *   (push/gbGate.php) lets changes through only for the sheet's owner with the app unlocked.
 * - Answers from the server open what's needed: sign in (need_auth), the plans (locked), or say it's view only.
 * - A bar at the top says when the sheet is view only, not set up yet ("Make it mine") or the free week is over.
 *
 * GBAccount.account()  — {email, name, state, until, apps, …} or null
 * GBAccount.token()    — the signed token or ''
 * GBAccount.canEdit()  — signed in, the sheet's owner, the app unlocked
 * GBAccount.require(fn)— true when canEdit(); otherwise opens sign-in or the plans (and calls fn after signing in)
 * GBAccount.signIn() / signOut() / openPlans(reason) / openCode() / refresh(force)
 * The page's account menu (O.menuButton / O.menu, or found by its usual id) gets the profile picture on its
 * button, a header with the name, email and plan, and "Enter a code" — the same as CardBoard's.
 */
(function() {
  'use strict';
  var KEY = 'cb_account', MAX_AGE = 4 * 3600 * 1000;
  var O = {}, ACCT = null, _refreshing = null, _signIn = null, _mode = '', _pending = null, _listeners = [];
  try { ACCT = JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) { ACCT = null; }
  // Older pages put a different shape here: only an object with a token counts
  if (ACCT && !ACCT.token) ACCT = null;

  function $(id) { return document.getElementById(id); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function info() { return (ACCT && ACCT.account) || null; }
  function appInfo() { var a = info(); if (!a) return null; return (a.apps && a.apps[O.app]) || { state: a.state, until: a.until, period_end: a.period_end, plan: '' }; }
  function checked() { return !!(ACCT && ACCT.sheet === O.sheet && ACCT.app === O.app); }
  function isOwner() { var a = info(); return !!a && checked() && a.owner === true; }
  function unlocked() { var a = info(); return !!a && !!a.sheet_unlocked; }
  function hasAccess() {
    var a = info(), x = appInfo(); if (!a || !x || !isOwner()) return false;
    if (unlocked() || a.sheet_paid) return true;
    return x.state !== 'locked' && new Date(x.until).getTime() > Date.now();
  }
  function daysLeft() { var x = appInfo(); return x ? Math.max(0, Math.ceil((new Date(x.until).getTime() - Date.now()) / 864e5)) : 0; }
  function when(v) { if (!v) return ''; var d = new Date(String(v).length === 10 ? v + 'T12:00:00Z' : v); return isNaN(d) ? String(v) : d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }); }

  function store(r) {
    ACCT = { token: r.token, account: r.account, sheet: O.sheet, app: O.app, checked: Date.now() };
    try { localStorage.setItem(KEY, JSON.stringify(ACCT)); } catch (e) {}
    apply();
  }
  function forget() { ACCT = null; try { localStorage.removeItem(KEY); } catch (e) {} apply(); }

  // ── Talking to the server ──────────────────────────────────────────────────
  var _fetch = window.fetch ? window.fetch.bind(window) : null;
  function accountPost(action, extra) {
    var fd = new FormData(); fd.append('action', action); fd.append('sheet', O.sheet || ''); fd.append('app', O.app);
    Object.keys(extra || {}).forEach(function(k) { fd.append(k, extra[k]); });
    if (ACCT && ACCT.token && !fd.has('cbt')) fd.append('cbt', ACCT.token);
    return _fetch(O.base + 'push/gameboardAccount.php', { method: 'POST', body: fd }).then(function(r) { return r.json(); })
      .then(function(r) { if (!r || r.error) { var e = new Error(r && r.error ? r.error : 'No response'); e.needAuth = !!(r && r.need_auth); throw e; } return r; });
  }
  function refresh(force) {
    if (!ACCT) return Promise.reject(new Error('Not signed in'));
    if (!force && checked() && Date.now() - (ACCT.checked || 0) < MAX_AGE) return Promise.resolve(ACCT);
    if (_refreshing) return _refreshing;
    _refreshing = accountPost('refresh').then(function(r) { store(r); return ACCT; })
      .catch(function(e) { if (e.needAuth) forget(); throw e; })
      .then(function(a) { _refreshing = null; return a; }, function(e) { _refreshing = null; throw e; });
    return _refreshing;
  }

  // Every request to push/ carries the token and the app; the answers that need the person are handled here
  function ours(url) {
    try { var u = new URL(url, document.baseURI); return u.origin === location.origin && /\/push\/[^/]+\.php$/.test(u.pathname) && !/gameboardAccount\.php$/.test(u.pathname); }
    catch (e) { return false; }
  }
  function onAnswer(r) {
    if (!r || typeof r !== 'object') return;
    if (r.need_auth) { if (r.refresh && ACCT) refresh(true).catch(function() { signIn().catch(function() {}); }); else signIn().catch(function() {}); }
    else if (r.locked) { refresh(true).then(apply, apply); openPlans('locked'); }
    else if (r.view_only) { toast('View only — this sheet belongs to another GameBoard account'); refresh(true).then(apply, apply); }
  }
  if (_fetch) {
    window.fetch = function(input, init) {
      var url = typeof input === 'string' ? input : (input && input.url) || '';
      if (!ours(url)) return _fetch(input, init);
      init = init || {};
      var h = new Headers(init.headers || (typeof input !== 'string' && input.headers) || {});
      if (ACCT && ACCT.token) h.set('X-CB-Token', ACCT.token);
      if (O.app) h.set('X-GB-App', O.app);
      init.headers = h;
      return _fetch(input, init).then(function(res) {
        res.clone().json().then(onAnswer, function() {});
        return res;
      });
    };
  }
  var _open = XMLHttpRequest.prototype.open, _send = XMLHttpRequest.prototype.send;
  XMLHttpRequest.prototype.open = function(method, url) { this._gbOurs = ours(url); return _open.apply(this, arguments); };
  XMLHttpRequest.prototype.send = function() {
    if (this._gbOurs) {
      try { if (ACCT && ACCT.token) this.setRequestHeader('X-CB-Token', ACCT.token); if (O.app) this.setRequestHeader('X-GB-App', O.app); } catch (e) {}
      this.addEventListener('load', function() { var r; try { r = JSON.parse(this.responseText); } catch (e) { return; } onAnswer(r); });
    }
    return _send.apply(this, arguments);
  };
  // Forms posted the old way (navigator.sendBeacon) can't carry headers: nothing to do there.

  // ── The page: styles, the bar, the dialogs ─────────────────────────────────
  var CSS =
    '.gba-bar{position:sticky;top:0;z-index:900;display:flex;align-items:center;justify-content:center;gap:.9rem;flex-wrap:wrap;padding:.55rem 1rem;background:#fff8e6;color:#5c4400;border-bottom:1px solid #f0dca0;font:500 .86rem/1.35 var(--gb-text,"DINRegular",Arial,sans-serif);text-align:center}' +
    '.gba-bar[hidden]{display:none}.gba-bar b{font-family:var(--gb-black,"DINBlack",Arial,sans-serif);font-weight:normal}' +
    '.gba-bar button{background:var(--gb-accent,#5b4fd6);color:#fff;border:none;border-radius:8px;padding:.38rem .95rem;font:inherit;font-family:var(--gb-black,"DINBlack",Arial,sans-serif);text-transform:uppercase;letter-spacing:.06em;font-size:.72rem;cursor:pointer}' +
    '.gba-ov{position:fixed;inset:0;z-index:10000;display:none;align-items:center;justify-content:center;padding:1rem;background:rgba(20,19,42,.55);-webkit-backdrop-filter:blur(2px);backdrop-filter:blur(2px)}' +
    '.gba-ov.open{display:flex}body.gba-gate .gba-ov#gbaSignIn{background:#f2f3f7}' +
    '.gba-dlg{background:#fff;border-radius:14px;box-shadow:0 18px 50px rgba(20,19,42,.28);width:100%;max-width:400px;max-height:92vh;overflow:auto;padding:1.6rem 1.5rem 1.3rem;color:#1b1d2b;font-family:var(--gb-text,"DINRegular",Arial,sans-serif);position:relative}' +
    '.gba-dlg.wide{max-width:520px}' +
    '.gba-x{position:absolute;top:.6rem;right:.7rem;background:none;border:none;font-size:1.4rem;line-height:1;color:#8a90a6;cursor:pointer}' +
    '.gba-logo{display:block;width:52px;height:52px;border-radius:12px;margin:0 auto .7rem}' +
    '.gba-dlg h2{margin:0 0 .3rem;text-align:center;font:normal 1.15rem var(--gb-black,"DINBlack",Arial,sans-serif);color:#1b1d2b;text-transform:none;letter-spacing:.02em}' +
    '.gba-sub{margin:0 0 1rem;text-align:center;font-size:.86rem;color:#4a5068}' +
    '.gba-dlg input{display:block;width:100%;box-sizing:border-box;margin:0 0 .6rem;padding:.62rem .75rem;border:1.5px solid #d6dbe5;border-radius:8px;font:inherit;font-size:.95rem;background:#fff;color:#1b1d2b}' +
    '.gba-dlg input:focus{outline:none;border-color:var(--gb-accent,#5b4fd6);box-shadow:0 0 0 3px var(--gb-accent-lt,#ece9ff)}' +
    '.gba-go{display:block;width:100%;margin:.3rem 0 0;padding:.7rem;border:none;border-radius:8px;background:var(--gb-accent,#5b4fd6);color:#fff;font:normal .8rem var(--gb-black,"DINBlack",Arial,sans-serif);text-transform:uppercase;letter-spacing:.07em;cursor:pointer}' +
    '.gba-go:hover:not(:disabled){background:var(--gb-accent-dk,#4b3fa8)}.gba-go:disabled{opacity:.6;cursor:default}' +
    '.gba-go.ghost{background:#fff;color:#4a5068;border:1.5px solid #d6dbe5}.gba-go.ghost:hover{border-color:var(--gb-accent,#5b4fd6);color:var(--gb-accent-dk,#4b3fa8);background:#fff}' +
    '.gba-link{display:block;margin:.7rem auto 0;background:none;border:none;color:#6b7090;font:inherit;font-size:.8rem;text-decoration:underline;text-underline-offset:2px;cursor:pointer}' +
    '.gba-link[hidden],.gba-dlg [hidden]{display:none!important}' +
    '.gba-err{min-height:1.1em;margin:.5rem 0 0;color:#c0392b;font-size:.82rem;text-align:center}' +
    '.gba-rules{list-style:none;margin:-.1rem 0 .6rem;padding:0;font-size:.76rem;color:#8a90a6;display:grid;grid-template-columns:1fr 1fr;gap:.15rem .6rem}' +
    '.gba-rules li::before{content:"○ "}.gba-rules li.ok{color:#2e9d4c}.gba-rules li.ok::before{content:"✓ "}' +
    '.gba-cur{margin:0 0 1rem;padding:.6rem .8rem;border-radius:8px;background:#f2f3f7;font-size:.86rem;text-align:center}.gba-cur.paid{background:#e3f3ea;color:#246f45}' +
    '.gba-plans{display:grid;gap:.6rem;margin:0 0 .4rem}' +
    '.gba-plan{display:flex;align-items:center;gap:.8rem;border:1.5px solid #e7eaf0;border-radius:10px;padding:.75rem .9rem}' +
    '.gba-plan.cur{border-color:var(--gb-accent,#5b4fd6);background:var(--gb-accent-lt,#ece9ff)}' +
    '.gba-plan div{flex:1;text-align:left}.gba-plan b{display:block;font:normal .92rem var(--gb-black,"DINBlack",Arial,sans-serif)}.gba-plan small{display:block;color:#4a5068;font-size:.78rem;margin-top:.1rem}' +
    '.gba-plan .gba-go{width:auto;margin:0;padding:.5rem .9rem;white-space:nowrap}' +
    '.gba-plan.all{background:linear-gradient(160deg,#fff4f1,#fff);border-color:#f3c3b8}.gba-plan.all .gba-go{background:#d9574a}.gba-plan.all .gba-go:hover:not(:disabled){background:#bc4437}' +
    '.gba-who{margin:.8rem 0 0;text-align:center;font-size:.78rem;color:#8a90a6}' +
    '.gba-code{display:flex;gap:.4rem;margin:.6rem 0 0}.gba-code input{margin:0;flex:1}.gba-code .gba-go{width:auto;margin:0;padding:.5rem .9rem}' +
    '.gba-toast{position:fixed;left:50%;bottom:24px;transform:translateX(-50%) translateY(20px);z-index:10001;background:#1a1a2e;color:#fff;padding:.6rem 1rem;border-radius:8px;font:500 .86rem var(--gb-text,"DINRegular",Arial,sans-serif);box-shadow:0 8px 24px rgba(20,19,42,.3);opacity:0;pointer-events:none;transition:opacity .2s,transform .2s}' +
    '.gba-toast.show{opacity:1;transform:translateX(-50%)}' +
    // The page's menu button shows the profile picture once signed in (like CardBoard); the menu names who's signed in
    '.gba-has-pic{padding:0!important;overflow:hidden!important}' +
    '.gba-has-pic>:not(.gba-pic){display:none!important}' +
    '.gba-pic{display:none;width:100%;height:100%;object-fit:cover;border-radius:50%}' +
    '.gba-has-pic>.gba-pic{display:block}' +
    '.gba-mwho{padding:.55rem 1rem .5rem;font-size:.75rem;line-height:1.35;color:rgba(255,255,255,.55);border-bottom:1px solid rgba(255,255,255,.12);margin-bottom:.2rem;word-break:break-all;text-align:left}' +
    '.gba-mwho b{display:block;color:#fff;font-size:.86rem;word-break:normal}' +
    '.gba-mwho small{display:block;margin-top:.15rem;color:rgba(255,255,255,.45)}';

  function build() {
    if ($('gbaSignIn')) return;
    var st = document.createElement('style'); st.textContent = CSS; document.head.appendChild(st);
    var bar = document.createElement('div'); bar.className = 'gba-bar'; bar.id = 'gbaBar'; bar.hidden = true;
    bar.innerHTML = '<span id="gbaBarMsg"></span><button type="button" id="gbaBarBtn"></button>';
    document.body.insertBefore(bar, document.body.firstChild);
    var icon = O.icon ? '<img class="gba-logo" src="' + esc(O.icon) + '" alt="">' : '';
    var wrap = document.createElement('div');
    wrap.innerHTML =
      '<div class="gba-ov" id="gbaSignIn"><div class="gba-dlg" role="dialog" aria-modal="true" aria-labelledby="gbaTitle">' +
        '<button type="button" class="gba-x" id="gbaSiX" aria-label="Close">×</button>' + icon +
        '<h2 id="gbaTitle"></h2><p class="gba-sub" id="gbaSub"></p>' +
        '<div id="gbaChoose"><button type="button" class="gba-go" data-m="signup">Start your free week</button><button type="button" class="gba-go ghost" data-m="login" style="margin-top:.5rem">Log in</button></div>' +
        '<form id="gbaStep1" hidden>' +
          '<input type="email" id="gbaEmail" placeholder="Email" autocomplete="email" autocapitalize="off" spellcheck="false">' +
          '<input type="password" id="gbaPass" placeholder="Password">' +
          '<input type="password" id="gbaPass2" placeholder="Password again" autocomplete="new-password">' +
          '<ul class="gba-rules" id="gbaRules"><li data-rule="len">At least 8 characters</li><li data-rule="letter">A letter</li><li data-rule="num">A number</li><li data-rule="match">Both passwords match</li></ul>' +
          '<button type="submit" class="gba-go" id="gbaSend"></button>' +
          '<button type="button" class="gba-link" id="gbaForgot">Forgot password?</button>' +
        '</form>' +
        '<form id="gbaStep2" hidden><p class="gba-sub" id="gbaSent"></p>' +
          '<input type="text" id="gbaCode" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="6-digit code" style="text-align:center;letter-spacing:.3em;font-size:1.2rem">' +
          '<button type="submit" class="gba-go" id="gbaVerify"></button><button type="button" class="gba-link" id="gbaBack">Back</button></form>' +
        '<div class="gba-err" id="gbaErr" role="alert"></div>' +
        '<button type="button" class="gba-link" id="gbaSwitch"></button>' +
        '<button type="button" class="gba-link" id="gbaLeave"></button>' +
      '</div></div>' +
      '<div class="gba-ov" id="gbaPlans"><div class="gba-dlg wide" role="dialog" aria-modal="true" aria-labelledby="gbaPlTitle">' +
        '<button type="button" class="gba-x" id="gbaPlX" aria-label="Close">×</button>' + icon +
        '<h2 id="gbaPlTitle">' + esc(O.name) + ' plans</h2><p class="gba-sub" id="gbaPlIntro"></p>' +
        '<div class="gba-cur" id="gbaPlCur"></div><div class="gba-plans" id="gbaPlList"></div>' +
        '<div class="gba-err" id="gbaPlErr" role="alert"></div>' +
        '<button type="button" class="gba-link" id="gbaManage" hidden>Manage billing</button>' +
        '<button type="button" class="gba-link" id="gbaHaveCode">Have a code?</button>' +
        '<form class="gba-code" id="gbaCodeForm" hidden><input type="text" id="gbaCodeIn" placeholder="Code" autocomplete="off" autocapitalize="off" spellcheck="false"><button type="submit" class="gba-go" id="gbaCodeBtn">Use code</button></form>' +
        '<p class="gba-who" id="gbaWho"></p>' +
        '<button type="button" class="gba-link" id="gbaSignOut">Sign out</button>' +
      '</div></div>' +
      '<div class="gba-toast" id="gbaToast" role="status"></div>';
    while (wrap.firstChild) document.body.appendChild(wrap.firstChild);

    // Keys typed in the dialogs belong to them, not to the page's own shortcuts
    ['gbaSignIn', 'gbaPlans'].forEach(function(id) {
      $(id).addEventListener('keydown', function(e) { e.stopPropagation(); if (e.key === 'Escape') { if (id === 'gbaPlans') closePlans(); else closeSignIn(); } });
    });
    $('gbaPlans').addEventListener('click', function(e) { if (e.target.id === 'gbaPlans') closePlans(); });
    $('gbaChoose').addEventListener('click', function(e) { var m = e.target.getAttribute('data-m'); if (m) step(m); });
    $('gbaStep1').addEventListener('submit', function(e) { e.preventDefault(); submit(); });
    $('gbaStep2').addEventListener('submit', function(e) { e.preventDefault(); verify(); });
    $('gbaForgot').onclick = function() { step('reset'); };
    $('gbaBack').onclick = function() { var em = $('gbaEmail').value; step(_mode || 'signup'); $('gbaEmail').value = em; };
    $('gbaSwitch').onclick = function() { step(_mode === 'login' ? 'signup' : 'login'); };
    $('gbaSiX').onclick = closeSignIn; $('gbaLeave').onclick = leave;
    $('gbaPass').addEventListener('input', rules); $('gbaPass2').addEventListener('input', rules);
    $('gbaPlX').onclick = closePlans;
    $('gbaManage').onclick = function() { var a = info(); if (a && a.portal_url) window.open(a.portal_url, '_blank', 'noopener'); };
    $('gbaHaveCode').onclick = function() { $('gbaHaveCode').hidden = true; $('gbaCodeForm').hidden = false; $('gbaCodeIn').focus(); };
    $('gbaCodeForm').addEventListener('submit', function(e) { e.preventDefault(); redeem(); });
    $('gbaSignOut').onclick = function() { closePlans(); signOut(); };
  }
  function toast(msg) {
    var t = $('gbaToast'); if (!t) return;
    t.textContent = msg; t.classList.add('show'); clearTimeout(t._h); t._h = setTimeout(function() { t.classList.remove('show'); }, 2600);
  }

  // ── The bar, and the page's own hook ───────────────────────────────────────
  function apply() {
    var a = info(), ok = hasAccess(), bar = $('gbaBar');
    document.body.classList.toggle('gba-locked', !ok);
    if (bar) {
      var btn = $('gbaBarBtn'), msg = $('gbaBarMsg'); btn.hidden = false;
      if (!a) { msg.innerHTML = '<b>Sign in to make changes</b> — your first week of ' + esc(O.name) + ' is free, with every feature.'; btn.textContent = 'Sign in'; btn.onclick = function() { signIn().catch(function() {}); }; }
      else if (!checked()) { msg.textContent = 'Checking your account…'; btn.hidden = true; }
      else if (!isOwner() && a.unclaimed) { msg.innerHTML = '<b>View only.</b> Nobody has set this sheet up with a GameBoard account yet — if it’s yours, make it yours to change it.'; btn.textContent = 'Make it mine'; btn.onclick = claim; }
      else if (!isOwner()) { msg.innerHTML = '<b>View only.</b> This sheet belongs to another GameBoard account — you can look, not change it.'; btn.hidden = true; }
      else { msg.innerHTML = '<b>Your free week has ended.</b> Everything is safe in your sheet — subscribe to keep using ' + esc(O.name) + '.'; btn.textContent = 'See plans'; btn.onclick = function() { openPlans('locked'); }; }
      bar.hidden = ok || (!a && O.gate === false);   // pages anyone may look at: no bar until someone signs in
    }
    menu();
    _listeners.forEach(function(fn) { try { fn(api.state()); } catch (e) {} });
  }
  // The page's own account menu, set up the same way in every app (CardBoard's is the model):
  //   - the round menu button shows the profile picture when there is one, otherwise the person icon
  //   - when signed in, the menu starts with the name, email and plan, and has "Enter a code" below the subscription
  // Found by O.menuButton / O.menu (element ids), or the usual ids and classes of the apps.
  function menu() {
    var btn = O.menuButton ? $(O.menuButton) : document.querySelector('#accountBtn, #menuBtn, #bbUserBtn, .account-menu-wrap > button, .user-menu-wrap > button');
    var list = O.menu ? $(O.menu) : document.querySelector('#accountMenu, #bbMenu, .account-menu, .user-menu');
    var a = info(), img = a && a.image ? String(a.image) : '';
    if (btn) {
      var pic = btn.querySelector('.gba-pic');
      if (!pic) { pic = document.createElement('img'); pic.className = 'gba-pic'; pic.alt = ''; btn.insertBefore(pic, btn.firstChild); pic.onerror = function() { btn.classList.remove('gba-has-pic'); }; }
      if (img && pic.getAttribute('src') !== img) pic.src = img;
      // A round button the size the icon button already had, filled by the picture
      if (img && !btn.classList.contains('gba-has-pic')) {
        var sz = Math.max(btn.offsetWidth, btn.offsetHeight, 32);
        btn.style.width = btn.style.height = sz + 'px'; btn.style.borderRadius = '50%';
      } else if (!img && btn.classList.contains('gba-has-pic')) btn.style.width = btn.style.height = btn.style.borderRadius = '';
      btn.classList.toggle('gba-has-pic', !!img);
      if (a) btn.title = (a.name || a.email || 'Menu');
    }
    if (!list) return;
    var who = list.querySelector('.gba-mwho');
    if (!who) { who = document.createElement('div'); who.className = 'gba-mwho'; list.insertBefore(who, list.firstChild); }
    who.style.display = a ? '' : 'none';   // (not hidden: the apps' menu styles can override it)
    if (a) {
      var nm = String(a.name || '').trim() || String(a.email || '').split('@')[0];
      who.innerHTML = '<b>' + esc(nm) + '</b>' + esc(a.email || '') + (checked() ? '<small>' + planLabel().replace(/<[^>]+>/g, '') + '</small>' : '');
    }
    // "Enter a code" under the subscription item (any item whose text is Subscribe / Subscription)
    var code = list.querySelector('.gba-mcode');
    if (!code) {
      var sub = Array.prototype.filter.call(list.querySelectorAll('button, a'), function(b) { return /^\s*subscri(be|ption)\s*$/i.test(b.textContent); })[0];
      if (sub) {
        code = document.createElement('button'); code.type = 'button'; code.className = sub.className + ' gba-mcode'; code.textContent = 'Enter a code';
        code.onclick = function(e) { e.stopPropagation(); list.classList.remove('open'); openCode(); };
        sub.parentNode.insertBefore(code, sub.nextSibling);
      }
    }
    if (code) code.style.display = !a || (appInfo() || {}).state === 'activated' || unlocked() ? 'none' : '';
    // "GameBoard" — back to the GameBoard home, just above Sign in / Sign out (the menu's last item)
    if (!list.querySelector('.gba-mhome')) {
      var items = list.querySelectorAll('button, a'), last = items[items.length - 1], ref = last;
      if (last && last.previousElementSibling && last.previousElementSibling.tagName === 'HR') ref = last.previousElementSibling;
      var home = document.createElement('a'); home.className = ((last && last.className) || '').replace(/\b(signout|active)\b/g, '') + ' gba-mhome';
      home.href = (O.base || '/') + 'gameboard'; home.textContent = 'GameBoard';
      home.style.textDecoration = 'none'; home.style.display = 'block'; home.style.boxSizing = 'border-box';
      if (ref) list.insertBefore(home, ref); else list.appendChild(home);
    }
  }
  function openCode() {   // the plans, with the code box already open
    if (!ACCT) { signIn().then(openCode, function() {}); return; }
    openPlans(''); $('gbaHaveCode').hidden = true; $('gbaCodeForm').hidden = false; setTimeout(function() { $('gbaCodeIn').focus(); }, 60);
  }
  function claim() {
    accountPost('claim').then(function(r) { store(r); toast(isOwner() ? '✓ This sheet is yours now' : 'Someone else has set this sheet up'); })
      .catch(function(e) { toast('Couldn’t make it yours — ' + e.message); });
  }

  // ── Sign in ────────────────────────────────────────────────────────────────
  //   login  — email + password;  signup / reset — email + (new) password, then a code from the email
  function signIn(gate) {
    build();
    if (_signIn) { $('gbaSignIn').classList.add('open'); return _signIn.promise; }
    var d = {}; d.promise = new Promise(function(res, rej) { d.res = res; d.rej = rej; }); _signIn = d;
    document.body.classList.toggle('gba-gate', !!gate);
    $('gbaSiX').hidden = !!gate; $('gbaLeave').hidden = !gate; $('gbaLeave').textContent = 'Not now';
    $('gbaEmail').value = (info() && info().email) || '';
    step('');
    $('gbaSignIn').classList.add('open');
    return d.promise;
  }
  function closeSignIn() {
    if (document.body.classList.contains('gba-gate')) return;
    $('gbaSignIn').classList.remove('open'); if (_signIn) { var d = _signIn; _signIn = null; d.rej(new Error('Not signed in')); }
  }
  function leave() { location.href = O.base + (O.home || ''); }
  function step(m) {
    _mode = m; _pending = null;
    $('gbaChoose').hidden = !!m; $('gbaStep1').hidden = !m; $('gbaStep2').hidden = true; $('gbaErr').textContent = '';
    $('gbaTitle').textContent = { login: 'Log in', signup: 'Start your free week', reset: 'Reset your password' }[m] || 'Welcome to ' + O.name;
    $('gbaSub').textContent = { login: 'Use your GameBoard email and password — the same one for every app.',
      signup: 'Every feature for 7 days — no card needed. Choose a password; we’ll email you a code to confirm.',
      reset: 'Choose a new password; we’ll email you a code to confirm it’s you.' }[m] || 'Sign in with your GameBoard account — one account for CardBoard, DevBoard, PitchBoard and BillBoard.';
    $('gbaPass').value = ''; $('gbaPass2').value = '';
    $('gbaPass').placeholder = m === 'login' ? 'Password' : 'Password (8 or more characters)';
    $('gbaPass').setAttribute('autocomplete', m === 'login' ? 'current-password' : 'new-password');
    $('gbaPass2').hidden = $('gbaRules').hidden = m === 'login'; rules();
    $('gbaForgot').hidden = m !== 'login';
    $('gbaSend').disabled = false; $('gbaSend').textContent = { login: 'Log in', signup: 'Create account', reset: 'Email me a code' }[m] || '';
    $('gbaSwitch').textContent = m === 'login' ? 'New here? Start your free week' : m ? 'Already have an account? Log in' : '';
    $('gbaSwitch').hidden = !m;
    if (m) setTimeout(function() { ($('gbaEmail').value ? $('gbaPass') : $('gbaEmail')).focus(); }, 40);
  }
  function rules() {
    var pw = $('gbaPass').value, all = true, ok = { len: pw.length >= 8, letter: /\p{L}/u.test(pw), num: /\d/.test(pw), match: pw !== '' && pw === $('gbaPass2').value };
    $('gbaRules').querySelectorAll('li').forEach(function(li) { var y = ok[li.getAttribute('data-rule')]; li.classList.toggle('ok', y); if (!y) all = false; });
    return all;
  }
  function signedIn(r) {
    store(r); $('gbaSignIn').classList.remove('open'); document.body.classList.remove('gba-gate');
    var x = appInfo(), d = daysLeft();
    toast(x && x.state === 'trial' ? '✓ Signed in — ' + d + (d === 1 ? ' day' : ' days') + ' of your free week left' : '✓ Signed in');
    if (_signIn) { var p = _signIn; _signIn = null; p.res(ACCT); }
  }
  function submit() {
    var em = $('gbaEmail').value.trim(), pw = $('gbaPass').value, err = $('gbaErr');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)) { err.textContent = 'Enter your email address.'; return; }
    if (_mode === 'login') { if (!pw) { err.textContent = 'Enter your password.'; return; } }
    else if (!rules()) { err.textContent = 'Your password needs everything on the list.'; return; }
    var b = $('gbaSend'), label = b.textContent; b.disabled = true; b.textContent = _mode === 'login' ? 'Logging in…' : 'Sending…'; err.textContent = '';
    var done = function() { b.disabled = false; b.textContent = label; };
    if (_mode === 'login') { accountPost('login', { email: em, password: pw }).then(signedIn).catch(function(e) { err.textContent = e.message; }).then(done); return; }
    accountPost('send', { email: em, mode: _mode }).then(function(r) {
      _pending = { email: em, password: pw };
      $('gbaSent').innerHTML = 'We sent a 6-digit code to <b>' + esc(em) + '</b>. Enter it to ' + (_mode === 'reset' ? 'set your new password.' : 'confirm your email and start your free week.') + ' It works for 15 minutes.';
      $('gbaTitle').textContent = 'Check your email'; $('gbaSub').textContent = '';
      $('gbaStep1').hidden = true; $('gbaStep2').hidden = false; $('gbaSwitch').hidden = true; $('gbaCode').value = r.debug_code || '';
      $('gbaVerify').textContent = _mode === 'reset' ? 'Set password' : 'Start my free week';
      setTimeout(function() { $('gbaCode').focus(); }, 40);
    }).catch(function(e) { err.textContent = e.message; }).then(done);
  }
  function verify() {
    var code = $('gbaCode').value.replace(/\D/g, '');
    if (!_pending) { step(_mode || 'signup'); return; }
    if (code.length !== 6) { $('gbaErr').textContent = 'Enter the 6-digit code from the email.'; return; }
    var b = $('gbaVerify'), label = b.textContent; b.disabled = true; b.textContent = 'Checking…'; $('gbaErr').textContent = '';
    accountPost('verify', { email: _pending.email, password: _pending.password, code: code }).then(function(r) { _pending = null; signedIn(r); })
      .catch(function(e) { $('gbaErr').textContent = e.message; }).then(function() { b.disabled = false; b.textContent = label; });
  }
  function signOut() {
    if (!window.confirm('Sign out of your GameBoard account? You can sign back in any time.')) return;
    forget(); location.href = O.base + (O.home || '');
  }

  // ── Plans ──────────────────────────────────────────────────────────────────
  var INTRO = { locked: 'Your free week has ended. Subscribe to keep going — everything is just as you left it in your sheet.' };
  function openPlans(reason) {
    build();
    if (!ACCT) { signIn().then(function() { openPlans(reason); }, function() {}); return; }
    $('gbaPlIntro').textContent = (O.intros || {})[reason] || INTRO[reason] || 'Every feature, free for a week. Then pick ' + O.name + ' on its own, or all four apps together.';
    $('gbaPlErr').textContent = ''; $('gbaHaveCode').hidden = false; $('gbaCodeForm').hidden = true; $('gbaCodeIn').value = '';
    renderPlans(); $('gbaPlans').classList.add('open');
    refresh(false).then(renderPlans, function() {});
  }
  function closePlans() { $('gbaPlans').classList.remove('open'); }
  function planLabel() {
    var a = info(), x = appInfo();
    if (!a || !x) return 'Not signed in';
    if (unlocked()) return '<b>Unlocked</b> · activation key';
    if (x.state === 'activated') return '<b>Unlocked</b> · every app, no time limit';
    if (x.state === 'subscribed') return '<b>' + (a.all_in_one ? 'All-in-one' : esc(O.name) + (x.plan === 'core' ? ' Core' : '')) + '</b>' + (x.period_end ? ' · renews ' + esc(when(x.period_end)) : '');
    if (x.state === 'code') return '<b>Unlocked with a code</b> · until ' + esc(when(x.until));
    if (x.state === 'trial') { var d = daysLeft(); return '<b>Free week</b> · ' + d + (d === 1 ? ' day' : ' days') + ' left'; }
    if (a.sheet_paid) return '<b>Paid on this sheet</b>';
    return '<b>Free week ended</b>';
  }
  function renderPlans() {
    var a = info() || {}, x = appInfo() || {}, sub = x.state === 'subscribed', forever = x.state === 'activated' || unlocked();
    $('gbaPlCur').innerHTML = planLabel(); $('gbaPlCur').className = 'gba-cur' + (sub || forever ? ' paid' : '');
    var rows = (O.plans || [{ plan: 'pro', label: '', price: '' }]).map(function(p) {
      var mine = sub && !a.all_in_one && (x.plan || 'pro') === p.plan;
      return '<div class="gba-plan' + (mine ? ' cur' : '') + '"><div><b>' + esc(O.name + (p.label ? ' ' + p.label : '')) + '</b><small>' + esc(p.price) + ' / month' + (p.note ? ' · ' + esc(p.note) : '') + '</small></div>' +
        (forever || a.all_in_one || mine ? '' : '<button type="button" class="gba-go" data-plan="' + esc(p.plan) + '">' + (sub ? 'Switch' : 'Subscribe') + '</button>') + '</div>';
    });
    rows.push('<div class="gba-plan all' + (a.all_in_one ? ' cur' : '') + '"><div><b>All-in-one</b><small>$9.99 / month · CardBoard, DevBoard, PitchBoard and BillBoard</small></div>' +
      (forever || a.all_in_one ? '' : '<button type="button" class="gba-go" data-plan="all">Get all four</button>') + '</div>');
    $('gbaPlList').innerHTML = rows.join('');
    $('gbaPlList').querySelectorAll('[data-plan]').forEach(function(b) { b.onclick = function() { checkout(b.getAttribute('data-plan'), b); }; });
    $('gbaManage').hidden = !(sub && a.portal_url); $('gbaManage').textContent = a.status === 'past_due' ? 'Update payment' : 'Manage billing';
    $('gbaHaveCode').hidden = $('gbaHaveCode').hidden || forever;
    $('gbaWho').innerHTML = a.email ? 'Signed in as <b>' + esc(a.email) + '</b>' : '';
  }
  function backUrl(flag) {
    var u = new URL(location.href); u.searchParams.delete('gbsub'); u.hash = '';
    if (flag) u.searchParams.set('gbsub', flag);
    return u.toString();
  }
  function checkout(plan, btn) {
    btn.disabled = true; btn.textContent = 'Opening…'; $('gbaPlErr').textContent = '';
    var fd = new FormData();
    fd.append('type', 'subscription'); fd.append('id', O.sheet || ''); fd.append('cbt', ACCT.token);
    fd.append('app', plan === 'all' ? 'all' : O.app); fd.append('plan', plan === 'all' ? 'pro' : plan);
    if (info() && info().email) fd.append('email', info().email);
    fd.append('success_url', backUrl(plan === 'all' ? 'all' : O.app)); fd.append('cancel_url', backUrl(''));
    _fetch(O.base + 'push/stripeCheckout.php', { method: 'POST', body: fd }).then(function(r) { return r.json(); })
      .then(function(r) { if (!r || r.error || !r.url) throw new Error(r && r.error ? r.error : 'Could not start checkout'); location.href = r.url; })
      .catch(function(e) { $('gbaPlErr').textContent = e.message; renderPlans(); });
  }
  function redeem() {
    var code = $('gbaCodeIn').value.trim(), b = $('gbaCodeBtn'); if (!code) { $('gbaCodeIn').focus(); return; }
    b.disabled = true; b.textContent = 'Checking…'; $('gbaPlErr').textContent = '';
    accountPost('redeem', { code: code }).then(function(r) {
      store(r); $('gbaCodeForm').hidden = true; $('gbaHaveCode').hidden = false; renderPlans();
      var mine = !r.apps || !r.apps.length || r.apps.indexOf(O.name) >= 0;
      toast(!mine ? '✓ Code added for ' + r.apps.join(', ') : r.days > 0 ? '✓ Unlocked until ' + when(appInfo().until) : '✓ Unlocked for good');
    }).catch(function(e) { $('gbaPlErr').textContent = e.message; }).then(function() { b.disabled = false; b.textContent = 'Use code'; });
  }

  // ── Start ──────────────────────────────────────────────────────────────────
  var api = {
    init: function(opts) {
      O = opts || {}; O.base = O.base || (document.querySelector('base') || {}).href || '/';
      if (typeof O.onChange === 'function') _listeners.push(O.onChange);
      var go = function() {
        build();
        // Coming from the GameBoard home on another domain: the account comes along in the link (#gbt=…)
        var m = /[#&]gbt=([^&]+)/.exec(location.hash || '');
        if (m) { history.replaceState(null, '', location.pathname + location.search); ACCT = { token: decodeURIComponent(m[1]), account: null }; }
        // Back from Stripe: the payment reaches the account a moment later
        var u = new URL(location.href), paid = u.searchParams.get('gbsub');
        if (paid) { u.searchParams.delete('gbsub'); history.replaceState(null, '', u.pathname + u.search + u.hash); setTimeout(function() { toast('✓ Thanks for subscribing!'); }, 400); }
        apply();
        if (!ACCT) { if (O.gate !== false) signIn(true).catch(function() {}); return; }
        refresh(true).catch(function(e) { if (e.needAuth && O.gate !== false) signIn(true).catch(function() {}); });
        if (paid) [3000, 9000].forEach(function(ms) { setTimeout(function() { if (ACCT) refresh(true).catch(function() {}); }, ms); });
      };
      if (document.body) go(); else document.addEventListener('DOMContentLoaded', go);
      return api;
    },
    account: info, appAccount: appInfo, token: function() { return (ACCT && ACCT.token) || ''; },
    canEdit: hasAccess, isOwner: isOwner,
    state: function() { var a = info(); return { signedIn: !!a, checked: checked(), owner: isOwner(), canEdit: hasAccess(), account: a, app: appInfo() }; },
    require: function(retry) {
      if (hasAccess()) return true;
      if (!ACCT) signIn().then(function() { if (hasAccess() && retry) retry(); }, function() {});
      else if (checked() && !isOwner()) toast(info() && info().unclaimed ? 'View only — make this sheet yours first' : 'View only — this sheet belongs to another GameBoard account');
      else openPlans('locked');
      return false;
    },
    signIn: function() { return signIn(false); }, signOut: signOut, openPlans: openPlans, openCode: openCode, refresh: refresh, onChange: function(fn) { _listeners.push(fn); },
    planLabel: function() { return planLabel().replace(/<[^>]+>/g, ''); }
  };
  window.GBAccount = api;
})();
