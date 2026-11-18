/**
 * activationKey.js — "Have an activation key?" for the plans dialog of every app.
 *
 * zsActivationKey({ el, base, sheetId, activated, onActivated })
 *   el          — element in the plans dialog to fill
 *   base        — app root URL (for push/activateKey.php)
 *   sheetId     — the sheet to unlock
 *   activated   — true when this sheet is already unlocked by a key
 *   onActivated — called after a key works (reload the plan status there)
 *
 * The key is checked on the server (push/activateKey.php); nothing about valid keys is here.
 */
(function() {
  if (!document.getElementById('zsActKeyStyle')) {
    var st = document.createElement('style');
    st.id = 'zsActKeyStyle';
    st.textContent =
      '.zs-ak{margin:.9rem auto 0;max-width:340px;text-align:center;font-family:inherit}' +
      '.zs-ak-link{background:none;border:none;padding:0;color:#6b7090;font-size:.78rem;text-decoration:underline;text-underline-offset:2px;cursor:pointer}' +
      '.zs-ak-link:hover{color:#2e2763}' +
      '.zs-ak-row{display:flex;gap:.4rem;margin-top:.15rem}' +
      '.zs-ak-row input{flex:1;min-width:0;height:2.1rem;border:1.5px solid #d6d9e6;border-radius:7px;padding:0 .6rem;font:inherit;font-size:.85rem;letter-spacing:.04em}' +
      '.zs-ak-row input:focus{outline:none;border-color:#7b6cf6;box-shadow:0 0 0 3px rgba(123,108,246,.15)}' +
      '.zs-ak-row button{height:2.1rem;padding:0 1rem;border:none;border-radius:7px;background:#2e2763;color:#fff;font-size:.78rem;font-weight:700;letter-spacing:.04em;cursor:pointer;white-space:nowrap}' +
      '.zs-ak-row button:disabled{opacity:.55;cursor:default}' +
      '.zs-ak-lbl{display:block;text-align:left;font-size:.68rem;letter-spacing:.06em;text-transform:uppercase;color:#8a8fa8;margin-bottom:.25rem}' +
      '.zs-ak-msg{min-height:1rem;margin-top:.3rem;font-size:.76rem;color:#c0392b;text-align:left}' +
      '.zs-ak-ok{display:inline-block;font-size:.8rem;color:#2e7d32;background:#edf7ed;border-radius:999px;padding:.2rem .8rem}';
    document.head.appendChild(st);
  }

  window.zsActivationKey = function(o) {
    var el = o.el; if (!el) return;
    if (o.activated) { el.innerHTML = '<div class="zs-ak"><span class="zs-ak-ok">✓ Pro unlocked with an activation key</span></div>'; return; }
    el.innerHTML = '<div class="zs-ak"><button type="button" class="zs-ak-link">Have an activation key?</button></div>';
    el.querySelector('.zs-ak-link').onclick = function() {
      var box = el.querySelector('.zs-ak');
      box.innerHTML = '<label class="zs-ak-lbl">Activation key</label>' +
        '<form class="zs-ak-row"><input type="text" autocomplete="off" autocapitalize="off" spellcheck="false" aria-label="Activation key"><button type="submit">Activate</button></form>' +
        '<div class="zs-ak-msg" role="status"></div>';
      var form = box.querySelector('form'), inp = box.querySelector('input'), btn = box.querySelector('button'), msg = box.querySelector('.zs-ak-msg');
      inp.focus();
      inp.addEventListener('keydown', function(e) { e.stopPropagation(); });   // keep app shortcuts (Esc, Enter) away while typing
      form.onsubmit = function(e) {
        e.preventDefault();
        var key = inp.value.trim(); if (!key) { inp.focus(); return; }
        btn.disabled = true; btn.textContent = 'Checking…'; msg.textContent = '';
        var fd = new FormData(); fd.append('id', o.sheetId); fd.append('key', key);
        fetch((o.base || '') + 'push/activateKey.php', { method: 'POST', body: fd })
          .then(function(r) { return r.json(); })
          .then(function(r) {
            if (!r || r.error) throw new Error(r && r.error ? r.error : 'Could not check the key');
            box.innerHTML = '<span class="zs-ak-ok">✓ Pro unlocked — every Pro feature is now on</span>';
            if (o.onActivated) o.onActivated();
          })
          .catch(function(err) { msg.textContent = err.message; btn.disabled = false; btn.textContent = 'Activate'; inp.select(); });
      };
    };
  };
})();
