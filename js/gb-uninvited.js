/**
 * gb-uninvited.js — the screen for an app the person hasn't been invited to from the Waiting Room yet.
 *
 *   GBUninvited.show({ name: 'DevBoard', icon: 'images/db_icon_180.png', home: 'dashboard', apps: ['PitchBoard'] })
 *   GBUninvited.hide()
 *
 * It covers the whole page (nothing underneath can be used) and points to the apps they do have.
 * Used by gb-account.js (PitchBoard, DevBoard, BillBoard) and CardBoard.
 */
(function() {
  if (window.GBUninvited) return;
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function(c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  function list(a) { return a.length <= 1 ? (a[0] || '') : a.slice(0, -1).join(', ') + ' and ' + a[a.length - 1]; }
  function css() {
    if (document.getElementById('gbuCss')) return;
    var st = document.createElement('style'); st.id = 'gbuCss';
    st.textContent =
      '.gbu-ov{position:fixed;inset:0;z-index:10080;display:flex;align-items:center;justify-content:center;padding:1.2rem;background:rgba(20,19,42,.72);-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;animation:gbuFade .2s ease}' +
      '.gbu{width:min(440px,94vw);background:#fff;border-radius:18px;padding:2rem 1.8rem 1.6rem;text-align:center;box-shadow:0 24px 70px rgba(0,0,0,.35);color:#1e293b;animation:gbuPop .22s cubic-bezier(.3,1.4,.5,1)}' +
      '.gbu img{width:64px;height:64px;border-radius:15px;box-shadow:0 6px 16px rgba(0,0,0,.18);margin-bottom:1rem;filter:grayscale(.35)}' +
      '.gbu h2{font-family:Fraunces,Georgia,serif;font-weight:600;font-size:1.45rem;line-height:1.2;letter-spacing:-.01em;color:#14132a;margin:0 0 .6rem}' +
      '.gbu p{font-size:.96rem;line-height:1.55;color:#475569;margin:0 0 1.4rem}' +
      '.gbu p b{color:#14132a}' +
      '.gbu a{display:inline-block;text-decoration:none;font-family:DINBlack,"Arial Black",sans-serif;font-size:.74rem;text-transform:uppercase;letter-spacing:.08em;padding:.8rem 1.4rem;border-radius:10px;background:#e5604f;color:#fff}' +
      '.gbu a:hover{background:#cc4e3e}' +
      'body.gbu-on{overflow:hidden}' +
      '@keyframes gbuFade{from{opacity:0}}@keyframes gbuPop{from{opacity:0;transform:scale(.96)}}';
    document.head.appendChild(st);
  }
  window.GBUninvited = {
    show: function(o) {
      css();
      var ov = document.getElementById('gbuOv');
      if (!ov) { ov = document.createElement('div'); ov.id = 'gbuOv'; ov.className = 'gbu-ov'; document.body.appendChild(ov); }
      var have = (o.apps || []).filter(Boolean);
      ov.innerHTML = '<div class="gbu" role="dialog" aria-modal="true" aria-labelledby="gbuT">' +
        (o.icon ? '<img src="' + esc(o.icon) + '" alt="">' : '') +
        '<h2 id="gbuT">You haven’t been invited to ' + esc(o.name) + ' yet</h2>' +
        '<p>' + (have.length ? 'Your account is open for <b>' + esc(list(have)) + '</b>. ' : '') + 'We’ll email you when you can use ' + esc(o.name) + '.</p>' +
        '<a href="' + esc(o.home || 'dashboard') + '">' + (have.length ? 'Go to your apps' : 'Back to GameBoard') + '</a></div>';
      document.body.classList.add('gbu-on');
    },
    hide: function() {
      var ov = document.getElementById('gbuOv'); if (ov) ov.remove();
      document.body.classList.remove('gbu-on');
    }
  };
})();
