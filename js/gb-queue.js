/**
 * gb-queue.js — save the way CardBoard does, in PitchBoard and DevBoard: changes to the sheet are kept in
 * this browser and sent to the server together — on Save (⌘S), or by themselves once you've paused.
 *
 *   GBQueue.init({ app: 'pitchboard', sheet: SHEET_ID, base: APP_BASE,
 *                  queue: ['updateGame', 'addRow', …],      // push/<name>.php calls that change the sheet: kept here
 *                  free:  ['getAnalytics', …],               // push/ calls that never need the changes saved first
 *                  reloadAfterPending: true,                 // saved changes left from last time: reload once they're in
 *                  button: 'id', label: 'Save',              // use the page's own Save button (else one is added to the top bar)
 *                  extraPending: fn, beforeSave: fn })       // a change not handed over yet (counts for the button) / hand it over
 *   GBQueue.put(url, formData, key) — keep a change made by the page itself; a newer one with the same key replaces it
 *   GBQueue.remove(key)             — drop a kept change (Cancel)
 *   GBQueue.items('updateGame')     — the changes kept for an endpoint, oldest first, as {field: value} — so a page
 *                                     that loads data from the server can show them on top of it before they're saved
 *   saveBeforeLeaving: true         — a link to a page the server builds (a game page, a sell sheet…) saves first
 *
 * How it works, without changing the page's own code:
 *  - A call to one of the `queue` endpoints isn't sent: it's kept (localStorage, per sheet) and answered at once
 *    with {ok: true, queued: true}, so the page updates itself the way it does after a save.
 *  - Save sends the kept calls one by one, in order, to the same endpoints (with the account's token, through
 *    gb-account.js). A call the server turns down is dropped and reported; if the server can't be reached, or
 *    the account needs signing in, the rest stay for the next Save.
 *  - Before any other call to the server (reading data, Fetch, making a PDF, a share link…) the kept changes are
 *    saved first, so what comes back includes them.
 *  - The Save button (CardBoard's gold pill) goes in the top bar; it fills up while saving.
 * Load after gb-account.js.
 */
(function() {
  'use strict';
  var O = {}, KEY = '', Q = [], _saving = null, _done = 0, _steps = 0, _btn = null, _sent = [], _handled = false;
  var IDLE_SAVE_MS = 90000, LONG_WAIT_MS = 15 * 60000, LONG_IDLE_MS = 20000, _lastActivity = Date.now(), _pendingSince = 0;

  function load() { try { Q = JSON.parse(localStorage.getItem(KEY) || '[]') || []; } catch (e) { Q = []; } }
  function keep() { try { if (Q.length) localStorage.setItem(KEY, JSON.stringify(Q)); else localStorage.removeItem(KEY); } catch (e) { toast('This browser couldn’t keep your change — saving now'); save(true); } updateBtn(); }
  function toast(m) { if (window.GBAccount && GBAccount.toast) GBAccount.toast(m); }
  function endpoint(url) {
    try { var u = new URL(url, document.baseURI); if (u.origin !== location.origin) return null; var m = /\/push\/([A-Za-z0-9_]+)\.php$/.exec(u.pathname); return m ? m[1] : (/\/sheets\//.test(u.pathname) ? '#sheets' : null); }
    catch (e) { return null; }
  }
  // Kept only when the account may change this sheet; otherwise it goes now and the server says why not (sign in, plans, view only)
  function canKeep() { return !window.GBAccount || !GBAccount.canEdit || GBAccount.canEdit(); }
  function queued(ep) { return ep && (O.queue || []).indexOf(ep) >= 0; }
  function needsSaveFirst(ep) { return ep && !queued(ep) && (O.free || []).indexOf(ep) < 0; }

  // A request body, kept as plain data (and made again for sending)
  function pack(body, ctype) {
    if (body == null) return { t: 'none' };
    if (typeof body === 'string') return { t: 'text', d: body, c: ctype || 'application/x-www-form-urlencoded' };
    if (window.URLSearchParams && body instanceof URLSearchParams) return { t: 'text', d: body.toString(), c: 'application/x-www-form-urlencoded' };
    if (window.FormData && body instanceof FormData) {
      var d = [], ok = true;
      body.forEach(function(v, k) { if (typeof v !== 'string') ok = false; d.push([k, v]); });
      return ok ? { t: 'form', d: d } : null;   // a file can't be kept: send it now
    }
    return null;
  }
  function unpack(b) {
    if (b.t === 'form') { var fd = new FormData(); b.d.forEach(function(p) { fd.append(p[0], p[1]); }); return { body: fd }; }
    if (b.t === 'text') return { body: b.d, headers: { 'Content-Type': b.c } };
    return {};
  }
  function add(url, body, key) {
    if (key) { load(); for (var i = 0; i < Q.length; i++) if (Q[i].k === key) { Q[i].b = body; Q[i].t = Date.now(); keep(); return; } }
    Q.push({ u: url, b: body, t: Date.now(), k: key || undefined });
    if (!_pendingSince) _pendingSince = Date.now();
    keep();
  }
  var OK = JSON.stringify({ ok: true, queued: true, updated: 0, created: false });

  // ── fetch ────────────────────────────────────────────────────────────────
  var _fetch = window.fetch ? window.fetch.bind(window) : null;   // gb-account.js's (adds the account token)
  if (_fetch) window.fetch = function(input, init) {
    var url = typeof input === 'string' ? input : (input && input.url) || '', ep = endpoint(url), method = ((init && init.method) || (input && input.method) || 'GET').toUpperCase();
    if (KEY && queued(ep) && method === 'POST' && typeof input === 'string' && canKeep()) {
      var h = init && init.headers, ct = h && (h['Content-Type'] || h['content-type'] || (h.get && h.get('Content-Type')));
      var b = pack(init && init.body, ct);
      if (b) { add(url, b); return Promise.resolve(new Response(OK, { status: 200, headers: { 'Content-Type': 'application/json' } })); }
    }
    if (KEY && Q.length && needsSaveFirst(ep)) return save(true).then(function() { return _fetch(input, init); }, function() { return _fetch(input, init); });
    return _fetch(input, init);
  };

  // ── XMLHttpRequest ───────────────────────────────────────────────────────
  var X = XMLHttpRequest.prototype, _open = X.open, _send = X.send, _seth = X.setRequestHeader;
  X.open = function(method, url) { this._gq = { m: String(method || 'GET').toUpperCase(), u: String(url), ep: endpoint(url), h: {} }; return _open.apply(this, arguments); };
  X.setRequestHeader = function(k, v) { if (this._gq) this._gq.h[String(k).toLowerCase()] = v; return _seth.apply(this, arguments); };
  X.send = function(body) {
    var g = this._gq, xhr = this, args = arguments;
    if (KEY && g && g.m === 'POST' && queued(g.ep) && canKeep()) {
      var b = pack(body, g.h['content-type']);
      if (b) {
        add(g.u, b);
        // Answer as if the server had: the page carries on exactly as after a save
        setTimeout(function() {
          ['readyState', 'status', 'statusText', 'responseText', 'response', 'responseURL'].forEach(function(k, i) {
            Object.defineProperty(xhr, k, { configurable: true, value: [4, 200, 'OK', OK, xhr.responseType === 'json' ? JSON.parse(OK) : OK, g.u][i] });
          });
          // dispatchEvent also runs the page's onload / onreadystatechange — once each
          ['readystatechange', 'load', 'loadend'].forEach(function(t) { xhr.dispatchEvent(new Event(t)); });
        }, 0);
        return;
      }
    }
    if (KEY && Q.length && g && needsSaveFirst(g.ep)) { var go = function() { _send.apply(xhr, args); }; save(true).then(go, go); return; }
    return _send.apply(this, arguments);
  };

  // ── Save ─────────────────────────────────────────────────────────────────
  // Sends the kept calls in order. Resolves when done (the ones that couldn't be sent stay kept).
  function save(quiet) {
    if (_saving) return _saving;
    if (typeof O.beforeSave === 'function') try { O.beforeSave(); } catch (e) {}
    load();
    if (!Q.length) { if (quiet !== true) toast('Everything is already saved'); return Promise.resolve(); }
    if (!navigator.onLine) { if (quiet !== true) toast('You’re offline — your changes are kept in this browser. Save again when you’re back online.'); return Promise.resolve(); }
    _done = 0; _steps = Q.length; _sent = []; var errors = [], stopped = '';
    _saving = new Promise(function(resolve) {
      var next = function() {
        load();
        if (!Q.length || stopped) return resolve();
        var it = Q[0], r = unpack(it.b);
        _fetch(it.u, { method: 'POST', body: r.body, headers: r.headers }).then(function(res) {
          if (!res.ok && res.status >= 500) throw new Error('The server had a problem (' + res.status + ')');
          return res.text();
        }).then(function(txt) {
          var j = null; try { j = JSON.parse(txt); } catch (e) {}
          if (j && (j.need_auth || j.locked)) { stopped = j.error || 'Sign in to save'; return; }   // gb-account.js asks; keep it for later
          load(); if (Q.length && Q[0].u === it.u && Q[0].t === it.t) Q.shift(); keep();   // changed again meanwhile: it stays for next time
          it.ok = !(j && j.error); (_sent = _sent || []).push(it);
          if (!j) errors.push('an unexpected answer from the server'); else if (j.error) errors.push(j.error);
          _done++; updateBtn();
        }).catch(function(e) { stopped = e.message || 'Couldn’t reach the server'; })
          .then(next);
      };
      updateBtn(); next();
    }).then(function() {
      _saving = null; updateBtn();
      var left = Q.length;
      if (stopped) toast('Couldn’t save everything — ' + stopped + '. ' + left + ' change' + (left === 1 ? ' is' : 's are') + ' kept in this browser; try Save again.');
      else if (errors.length) toast('Saved, but ' + errors.length + ' change' + (errors.length === 1 ? ' was' : 's were') + ' turned down: ' + errors[0]);
      else if (quiet !== true) toast('✓ Saved to your Google Sheet');
      _handled = false;
      if (typeof O.onSaved === 'function') try {
        _handled = O.onSaved({ left: left, errors: errors, sent: _sent.map(function(x) {
          var d = {}; if (x.b && x.b.t === 'form') x.b.d.forEach(function(p) { d[p[0]] = p[1]; });
          return { url: x.u, endpoint: endpoint(x.u), key: x.k, ok: x.ok, data: d };
        }) }) === true;   // true: the page goes somewhere itself
      } catch (e) {}
    });
    return _saving;
  }

  // ── The Save button: CardBoard's gold pill in the top bar ───────────────
  function extra() { try { return typeof O.extraPending === 'function' ? (O.extraPending() || 0) : 0; } catch (e) { return 0; } }
  function placeBtn() {
    if (_btn || !document.body) return;
    if (O.button) { _btn = document.getElementById(O.button); if (_btn) { if (!_btn.onclick) _btn.onclick = function(e) { e.preventDefault(); save(); }; updateBtn(); } return; }   // its own onclick (if any) calls GBQueue.save()
    var wrap = document.querySelector(O.place || '.top-bar .account-menu-wrap'); if (!wrap) return;
    _btn = document.createElement('button'); _btn.type = 'button'; _btn.className = 'top-save'; _btn.id = 'gbSaveBtn';
    _btn.onclick = function() { save(); };
    wrap.insertBefore(_btn, wrap.firstChild);
    updateBtn();
  }
  function updateBtn() {
    if (!_btn) return;
    var n = Q.length + extra(), busy = !!_saving;
    _btn.disabled = busy || !n;
    _btn.classList.toggle('saving', busy);
    _btn.style.setProperty('--sv', Math.min(100, Math.max(8, Math.round(100 * (_done + .35) / Math.max(1, _steps)))) + '%');
    if (_btn.classList.contains('top-save')) _btn.innerHTML = busy ? '<span class="sv-fill"></span><span class="sv-txt"><span class="sv-spin"></span>Saving</span>' : (O.label || 'Save');
    else _btn.textContent = busy ? 'Saving…' : (O.label || 'Save');
    _btn.title = busy ? 'Saving to your Google Sheet — keep working' : n ? n + ' change' + (n === 1 ? '' : 's') + ' to save (⌘S) — otherwise they’re saved together after you’ve stopped for a minute or two' : 'Everything is saved';
  }

  // ── Saving by itself once you've paused (as in CardBoard) ───────────────
  ['keydown', 'pointerdown', 'input', 'drop', 'wheel'].forEach(function(t) { document.addEventListener(t, function() { _lastActivity = Date.now(); }, true); });
  function busyEditing() { var a = document.activeElement; return !!a && (/^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName) || a.isContentEditable); }
  function idleSave() {
    if (KEY && extra() && !busyEditing() && typeof O.beforeSave === 'function') try { O.beforeSave(); } catch (e) {}
    if (KEY) { load(); updateBtn(); }
    if (!KEY || !Q.length) { _pendingSince = 0; return; }
    if (!_pendingSince) _pendingSince = Date.now();
    if (_saving || !navigator.onLine) return;
    var idle = Date.now() - _lastActivity, waited = Date.now() - _pendingSince;
    if (busyEditing() || idle < (waited > LONG_WAIT_MS ? LONG_IDLE_MS : IDLE_SAVE_MS)) return;
    _pendingSince = 0; save(true);
  }
  document.addEventListener('keydown', function(e) {
    if ((e.metaKey || e.ctrlKey) && !e.shiftKey && !e.altKey && (e.key === 's' || e.key === 'S') && KEY) { e.preventDefault(); save(); }
  });
  window.addEventListener('beforeunload', function(ev) { if (_saving) { ev.preventDefault(); ev.returnValue = ''; } });   // only while a save is on its way
  window.addEventListener('online', function() { if (Q.length) toast('Back online — your changes will be saved once you take a break'); });
  // Another tab of the same sheet saved or kept changes: follow it
  window.addEventListener('storage', function(e) { if (KEY && e.key === KEY) { load(); updateBtn(); } });

  // A link to another page of this site (the game page, a sell sheet, the slideshow…) shows what the server has:
  // with changes kept here, save them first, then go
  document.addEventListener('click', function(e) {
    if (!O.saveBeforeLeaving || !KEY || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var a = e.target.closest && e.target.closest('a[href]'); if (!a || a.hasAttribute('download')) return;
    var u; try { u = new URL(a.getAttribute('href'), document.baseURI); } catch (x) { return; }
    if (u.origin !== location.origin || /\/push\//.test(u.pathname) || (u.pathname === location.pathname && u.hash)) return;
    load(); if (!Q.length) return;
    e.preventDefault();
    var w = a.target === '_blank' ? window.open('', '_blank') : null;   // opened now, while it's a click (else it's blocked)
    toast('Saving your changes first…');
    save(true).then(function() { if (w) w.location.href = u.href; else location.href = u.href; });
  }, false);
  window.GBQueue = {
    init: function(opts) {
      O = opts || {}; if (!O.sheet || !O.app) return window.GBQueue;
      KEY = 'gb_queue:' + O.app + ':' + O.sheet; load();
      var go = function() {
        placeBtn();
        setInterval(idleSave, 5000);
        // Changes kept from last time: save them now; pages that show data from when they were built reload
        if (Q.length) save(true).then(function() { if (O.reloadAfterPending && !Q.length && !_handled) location.reload(); });
      };
      if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', go); else go();
      return window.GBQueue;
    },
    save: function(quiet) { return save(quiet === true ? true : false); },
    put: function(url, fd, key) { if (!KEY) return false; var b = pack(fd); if (!b) return false; add(url, b, key); return true; },
    remove: function(key) { load(); var n = Q.length; Q = Q.filter(function(x) { return x.k !== key; }); if (Q.length !== n) keep(); },
    has: function(key) { load(); return Q.some(function(x) { return x.k === key; }); },
    refresh: function() { load(); updateBtn(); },
    active: function() { return !!KEY && canKeep(); },   // changes are being kept (signed in, may edit)
    pending: function() { load(); return Q.length; },
    items: function(ep) {
      load();
      return Q.filter(function(x) { return endpoint(x.u) === ep; }).map(function(x) {
        var d = {};
        if (x.b && x.b.t === 'form') x.b.d.forEach(function(p) { d[p[0]] = p[1]; });
        else if (x.b && x.b.t === 'text' && window.URLSearchParams) new URLSearchParams(x.b.d).forEach(function(v, k) { d[k] = v; });
        return d;
      });
    },
    saving: function() { return !!_saving; }
  };
})();
