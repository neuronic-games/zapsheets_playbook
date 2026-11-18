// CardBoard — card designer. Sizes and positions are in points (1/72 in) so cards print at true size.
'use strict';

// ── State ─────────────────────────────────────────────────────────────────────
var TABS = [], LAYOUTS = {}, ROWS = [], FIELDS = [];
var curTab = '', layout = null, rowIdx = 0, selId = null, zoom = 1, dirty = false;
var undoStack = [], redoStack = [];
var CARD_SIZES = [
  { id: 'poker',  name: 'Poker — 2.5 × 3.5 in',   w: 180, h: 252 },
  { id: 'bridge', name: 'Bridge — 2.25 × 3.5 in', w: 162, h: 252 },
  { id: 'tarot',  name: 'Tarot — 2.75 × 4.75 in', w: 198, h: 342 },
  { id: 'mini',   name: 'Mini — 1.75 × 2.5 in',   w: 126, h: 180 },
  { id: 'square', name: 'Square — 2.5 × 2.5 in',  w: 180, h: 180 },
  { id: 'euro',   name: 'Euro — 59 × 92 mm',      w: 167, h: 261 },
  { id: 'custom', name: 'Custom…' }
];
var FONTS = [
  ['DINBlack', 'DIN Black'], ['DINRegular', 'DIN Medium'], ['Bangers', 'Bangers'], ['Cinzel', 'Cinzel'],
  ['Lilita One', 'Lilita One'], ['Merriweather', 'Merriweather'], ['Roboto Condensed', 'Roboto Condensed'],
  ['Arial', 'Arial'], ['Georgia', 'Georgia'], ['Times New Roman', 'Times New Roman'], ['Verdana', 'Verdana'],
  ['Courier New', 'Courier New'], ['Impact', 'Impact']
];
var PX = 96 / 72;   // CSS px per pt
var _activeTab = 'design';

function newLayout() {
  return { v: 1, card: { size: 'poker', w: 180, h: 252, bg: '#ffffff', radius: 9, borderColor: '#222222', borderW: 0 }, els: [] };
}
function uid() { return 'e' + Math.random().toString(36).slice(2, 9); }
function esc(s) { return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
function $(id) { return document.getElementById(id); }
function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
function r1(v) { return Math.round(v * 10) / 10; }

// ── Server ────────────────────────────────────────────────────────────────────
function api(action, extra) {
  var fd = new FormData();
  fd.append('id', SHEET_ID); fd.append('action', action);
  Object.keys(extra || {}).forEach(function(k) { fd.append(k, extra[k]); });
  return fetch(APP_BASE + 'push/cardboardData.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(res) { if (!res || res.error) throw new Error(res && res.error ? res.error : 'No response'); return res; });
}
function toast(msg, err) {
  var t = $('toast'); t.textContent = msg; t.className = 'toast show' + (err ? ' err' : '');
  clearTimeout(t._h); t._h = setTimeout(function() { t.className = 'toast' + (err ? ' err' : ''); }, err ? 4200 : 1800);
}

// ── Values from the sheet ─────────────────────────────────────────────────────
function cleanVal(v) {
  v = String(v == null ? '' : v);
  if (v.charAt(0) === "'") v = v.slice(1);
  return v;
}
function imageUrl(v) {
  v = cleanVal(v).trim();
  var m = v.match(/^=IMAGE\(\s*"([^"]+)"/i); if (m) v = m[1];
  m = v.match(/^\[[^\]]*\]\(([^)]+)\)$/); if (m) v = m[1];
  m = v.match(/drive\.google\.com\/(?:file\/d\/|open\?id=|uc\?(?:export=\w+&)?id=)([A-Za-z0-9_-]+)/);
  if (m) v = 'https://drive.google.com/thumbnail?id=' + m[1] + '&sz=w1600';
  return /^(https?:)?\/\//i.test(v) || /^data:image\//.test(v) ? v : '';
}
function textVal(v) {
  v = cleanVal(v);
  if (/^=IMAGE\(/i.test(v)) return '';
  return v.replace(/\[([^\]]*)\]\(([^)]+)\)/g, '$1');
}
function looksLikeImage(field) {
  var n = 0, img = 0;
  ROWS.slice(0, 12).forEach(function(r) {
    var v = cleanVal(r[field]).trim();
    if (v) { n++; if (imageUrl(v) && /\.(png|jpe?g|gif|webp|svg)(\?|$)|=IMAGE|drive\.google|googleusercontent/i.test(v)) img++; }
  });
  return n > 0 && img / n >= .5;
}
// "{Name} costs {Cost}" → values from the row
function fill(tpl, row) {
  return String(tpl || '').replace(/\{([^{}]+)\}/g, function(_, k) {
    if (!row) return '{' + k + '}';
    return Object.prototype.hasOwnProperty.call(row, k) ? textVal(row[k]) : '{' + k + '}';
  });
}
function fillImage(tpl, row) {
  var t = String(tpl || '').trim(), m = t.match(/^\{([^{}]+)\}$/);
  if (m) return row ? imageUrl(row[m[1]]) : '';
  return imageUrl(fill(t, row));
}

// ── Rendering one card (designer and print share this) ───────────────────────
function cardStyle(c) {
  return 'width:' + c.w + 'pt;height:' + c.h + 'pt;background:' + (c.bg || '#fff') + ';border-radius:' + (c.radius || 0) + 'pt;' +
         (c.borderW ? 'box-shadow:inset 0 0 0 ' + c.borderW + 'pt ' + (c.borderColor || '#000') + ';' : '');
}
function elStyle(e) {
  var s = 'left:' + e.x + 'pt;top:' + e.y + 'pt;width:' + e.w + 'pt;height:' + e.h + 'pt;z-index:' + (e.z || 1) + ';';
  if (e.bg) s += 'background:' + e.bg + ';';
  if (e.borderW) s += 'border:' + e.borderW + 'pt solid ' + (e.borderColor || '#000') + ';';
  if (e.radius) s += 'border-radius:' + e.radius + 'pt;';
  if (e.kind === 'text') {
    s += 'font-family:"' + (e.font || 'DINRegular') + '",Arial,sans-serif;font-size:' + (e.size || 12) + 'pt;color:' + (e.color || '#111') + ';' +
         'text-align:' + (e.align || 'left') + ';padding:' + (e.pad == null ? 2 : e.pad) + 'pt;line-height:' + (e.lh || 1.15) + ';' +
         (e.bold ? 'font-weight:700;' : 'font-weight:400;') + (e.italic ? 'font-style:italic;' : '') + (e.upper ? 'text-transform:uppercase;' : '');
  }
  return s;
}
function elInner(e, row) {
  if (e.kind === 'text') {
    var t = fill(e.text, row);
    return t.trim() ? esc(t) : (row ? '' : '<span class="el-empty">' + esc(e.text || 'Text') + '</span>');
  }
  if (e.kind === 'image') {
    var u = fillImage(e.src, row);
    if (!u) return '<div class="el-img" style="background:#eef0f6;display:flex;align-items:center;justify-content:center;color:#a0a4bb;font-size:8pt">' + esc(e.src || 'Image') + '</div>';
    var fit = e.fit === 'stretch' ? '100% 100%' : (e.fit || 'cover');
    return '<div class="el-img" style="background-image:url(&quot;' + esc(u) + '&quot;);background-size:' + fit + '"></div>';
  }
  return '';
}
function renderCardHTML(lay, row, designing) {
  var h = '<div class="card' + (designing ? ' designing' : '') + '" style="' + cardStyle(lay.card) + '">';
  lay.els.slice().sort(function(a, b) { return (a.z || 1) - (b.z || 1); }).forEach(function(e) {
    var cls = 'el' + (e.valign === 'middle' ? ' v-middle' : e.valign === 'bottom' ? ' v-bottom' : '') + (designing && e.id === selId ? ' sel' : '');
    h += '<div class="' + cls + '" data-id="' + e.id + '" style="' + elStyle(e) + '">';
    h += designing
      ? '<div class="el-inner">' + elInner(e, row) + '</div>' +
        ['nw', 'n', 'ne', 'e', 'se', 's', 'sw', 'w'].map(function(d) { return '<span class="hdl ' + d + '" data-h="' + d + '"></span>'; }).join('')
      : elInner(e, row);
    h += '</div>';
  });
  if (designing && !lay.els.length) h += '<div class="card-empty-hint">Drag fields here</div>';
  return h + '</div>';
}

// ── Loading ───────────────────────────────────────────────────────────────────
function init() {
  $('cardHolder').innerHTML = '<div class="loading" style="width:240px;height:200px"><span class="spin"></span>Loading sheet…</div>';
  Promise.all([api('tabs'), api('layouts').catch(function() { return { layouts: {} }; })])
    .then(function(res) {
      TABS = res[0].tabs || []; LAYOUTS = res[1].layouts || {};
      if (res[0].title) { $('subTitle').textContent = res[0].title; document.title = 'CardBoard — ' + res[0].title; }
      var sel = $('sheetSel');
      sel.innerHTML = TABS.map(function(t) { return '<option>' + esc(t.title) + '</option>'; }).join('');
      var start = '';
      try { start = localStorage.getItem('cb_tab:' + SHEET_ID) || ''; } catch (e) {}
      if (!TABS.some(function(t) { return t.title === start; })) {
        // Prefer a tab that already has a layout, then the first one
        start = (TABS.filter(function(t) { return LAYOUTS[t.title]; })[0] || TABS[0] || {}).title || '';
      }
      if (!start) { $('cardHolder').innerHTML = '<div class="muted">This spreadsheet has no tabs to design from.</div>'; return; }
      sel.value = start;
      selectTab(start, true);
    })
    .catch(function(e) {
      $('cardHolder').innerHTML = '<div class="muted" style="max-width:360px;text-align:center">Couldn\'t open the sheet — ' + esc(e.message) +
        '<br><br>Make sure it\'s shared with <b>editor@zapsheets-480701.iam.gserviceaccount.com</b>.</div>';
    });
}

function selectTab(name, first, force) {
  if (!first && dirty && !confirm('You have unsaved changes to the "' + curTab + '" layout. Switch sheets anyway?')) { $('sheetSel').value = curTab; return; }
  curTab = name; selId = null; rowIdx = 0; undoStack = []; redoStack = [];
  try { localStorage.setItem('cb_tab:' + SHEET_ID, name); } catch (e) {}
  layout = LAYOUTS[name] ? JSON.parse(JSON.stringify(LAYOUTS[name])) : newLayout();
  if (!layout.card) layout.card = newLayout().card;
  if (!layout.els) layout.els = [];
  setDirty(false);
  // An unsaved draft from this browser wins (restored, still marked unsaved)
  try {
    var d = localStorage.getItem('cb_draft:' + SHEET_ID + ':' + name);
    if (d && d !== JSON.stringify(layout)) { layout = JSON.parse(d); setDirty(true, true); toast('Restored your unsaved changes'); }
  } catch (e) {}
  $('fieldList').innerHTML = '<div class="muted">Loading…</div>';
  $('cardHolder').innerHTML = '<div class="loading" style="width:240px;height:200px"><span class="spin"></span>Loading rows…</div>';
  api('rows', { tab: name, force: force ? '1' : '' })
    .then(function(res) {
      if (curTab !== name) return;
      ROWS = (res.rows || []).filter(function(r) { return Object.keys(r).some(function(k) { return k.charAt(0) !== '_' && String(r[k]).trim(); }); });
      FIELDS = [];
      ROWS.forEach(function(r) { Object.keys(r).forEach(function(k) { if (k.charAt(0) !== '_' && FIELDS.indexOf(k) < 0) FIELDS.push(k); }); });
      renderFields(); fitZoom(); renderAttrs();
      if (_activeTab === 'print') renderPrint();
      if (force) toast('✓ Reloaded from the sheet');
    })
    .catch(function(e) {
      ROWS = []; FIELDS = []; renderFields(); render(); renderAttrs();
      toast('Couldn\'t read "' + name + '" — ' + e.message, true);
    });
}
function reloadSheet() { if (curTab) selectTab(curTab, true, true); }
function openInSheets() { window.open('https://docs.google.com/spreadsheets/d/' + SHEET_ID + '/edit', '_blank', 'noopener'); }

// ── Fields panel ──────────────────────────────────────────────────────────────
function renderFields() {
  var el = $('fieldList');
  if (!FIELDS.length) { el.innerHTML = '<div class="muted">No columns found. The first row of the sheet should hold the field names, with one card per row below.</div>'; return; }
  var sample = ROWS[rowIdx] || ROWS[0] || {};
  el.innerHTML = FIELDS.map(function(f) {
    var img = looksLikeImage(f);
    return '<div class="field-chip" draggable="true" data-field="' + esc(f) + '" ondragstart="chipDragStart(event)" onclick="addField(this.dataset.field)" title="Drag onto the card">' +
           '<span class="ico">' + (img ? '🖼' : '≡') + '</span><span class="nm">' + esc(f) + '</span>' +
           (img ? '' : '<span class="sample">' + esc(textVal(sample[f])) + '</span>') + '</div>';
  }).join('');
}
function chipDragStart(ev) {
  var c = ev.currentTarget;
  ev.dataTransfer.setData('text/plain', c.dataset.field ? 'field:' + c.dataset.field : 'add:' + c.dataset.add);
  ev.dataTransfer.effectAllowed = 'copy';
}
function newFieldEl(field, x, y) {
  var c = layout.card;
  if (looksLikeImage(field)) {
    var w = Math.round(c.w * .7), h = Math.round(c.w * .55);
    return { kind: 'image', src: '{' + field + '}', fit: 'cover', w: w, h: h, x: x - w / 2, y: y - h / 2 };
  }
  // Size the box from the longest value in this column (rough DIN Medium metrics: ~0.55 em per character)
  var size = 11, longest = 0, lines = 1;
  ROWS.forEach(function(r) {
    var v = textVal(r[field]);
    v.split('\n').forEach(function(l) { longest = Math.max(longest, l.length); });
    lines = Math.max(lines, v.split('\n').length);
  });
  var maxW = c.w - 24, natural = longest * size * .55 + 8;
  var w2 = Math.round(clamp(natural, 28, maxW));
  var wrapped = 0;
  ROWS.forEach(function(r) {
    var n = 0;
    textVal(r[field]).split('\n').forEach(function(l) { n += Math.max(1, Math.ceil((l.length * size * .55 + 4) / w2)); });
    wrapped = Math.max(wrapped, n);
  });
  var h2 = Math.round(clamp(Math.max(lines, wrapped) * size * 1.15 + 6, 18, c.h - 24));
  return { kind: 'text', text: '{' + field + '}', font: 'DINRegular', size: size, color: '#1b1b2b', align: 'left', w: w2, h: h2, x: x - w2 / 2, y: y - h2 / 2 };
}
function staticEl(kind) {
  var c = layout.card;
  if (kind === 'text') return { kind: 'text', text: 'Text', font: 'DINBlack', size: 14, color: '#1b1b2b', align: 'center', w: c.w - 24, h: 26 };
  return { kind: 'box', bg: '#ece9ff', radius: 6, w: c.w - 24, h: 60 };
}
function addField(field) { addElementAt(newFieldEl(field, layout.card.w / 2, layout.card.h / 2)); }
function addElement(kind) {
  if (!layout) return;
  var c = layout.card, e = staticEl(kind);
  e.x = (c.w - e.w) / 2; e.y = (c.h - e.h) / 2;
  addElementAt(e);
}
function addElementAt(e) {
  var c = layout.card;
  e.id = uid(); e.z = layout.els.reduce(function(m, x) { return Math.max(m, x.z || 1); }, 0) + 1;
  e.w = clamp(e.w, 6, c.w); e.h = clamp(e.h, 6, c.h);
  e.x = r1(clamp(e.x, 0, c.w - e.w)); e.y = r1(clamp(e.y, 0, c.h - e.h));
  snapshot(); layout.els.push(e); selId = e.id; changed();
}

// ── Canvas ────────────────────────────────────────────────────────────────────
function render() {
  if (!layout) return;
  var holder = $('cardHolder'), c = layout.card;
  holder.style.width = (c.w * PX * zoom) + 'px'; holder.style.height = (c.h * PX * zoom) + 'px';
  holder.innerHTML = renderCardHTML(layout, ROWS[rowIdx] || null, true);
  var card = holder.firstChild;
  card.style.transform = 'scale(' + zoom + ')';
  card.style.setProperty('--z', zoom * PX);
  $('rowInfo').textContent = ROWS.length ? 'Row ' + (rowIdx + 1) + ' of ' + ROWS.length : 'No rows';
  $('rowPrev').disabled = rowIdx <= 0; $('rowNext').disabled = rowIdx >= ROWS.length - 1;
  $('zoomInfo').textContent = Math.round(zoom * 100) + '%';
  $('undoBtn').disabled = !undoStack.length; $('redoBtn').disabled = !redoStack.length;
}
function stepRow(d) { rowIdx = clamp(rowIdx + d, 0, Math.max(0, ROWS.length - 1)); render(); renderFields(); }
function zoomBy(d) {
  var steps = [.5, .75, 1, 1.25, 1.5, 2, 2.5, 3, 4];
  var i = steps.findIndex(function(s) { return s >= zoom - .01; });
  zoom = steps[clamp((i < 0 ? 2 : i) + d, 0, steps.length - 1)]; render();
}
function fitZoom() {
  if (!layout) return;
  var w = $('canvasWrap'), c = layout.card;
  var z = Math.min((w.clientWidth - 80) / (c.w * PX), (w.clientHeight - 80) / (c.h * PX));
  zoom = clamp(Math.floor(z * 4) / 4, .5, 4) || 1; render();
}
window.addEventListener('resize', function() { if (_activeTab === 'design') fitZoom(); });

function ptFromEvent(ev) {
  var r = $('cardHolder').getBoundingClientRect();
  return { x: (ev.clientX - r.left) / (PX * zoom), y: (ev.clientY - r.top) / (PX * zoom) };
}
// Drop a field (or Text / Box) from the left panel
(function() {
  var wrap = $('canvasWrap'), holder = $('cardHolder');
  wrap.addEventListener('dragover', function(ev) { if (!layout) return; ev.preventDefault(); ev.dataTransfer.dropEffect = 'copy'; holder.classList.add('drop-target'); });
  wrap.addEventListener('dragleave', function(ev) { if (ev.target === wrap) holder.classList.remove('drop-target'); });
  wrap.addEventListener('drop', function(ev) {
    ev.preventDefault(); holder.classList.remove('drop-target');
    if (!layout) return;
    var d = ev.dataTransfer.getData('text/plain') || '', p = ptFromEvent(ev), c = layout.card;
    p.x = clamp(p.x, 0, c.w); p.y = clamp(p.y, 0, c.h);
    if (d.indexOf('field:') === 0) return addElementAt(newFieldEl(d.slice(6), p.x, p.y));
    if (d.indexOf('add:') === 0) { var e = staticEl(d.slice(4)); e.x = p.x - e.w / 2; e.y = p.y - e.h / 2; addElementAt(e); }
  });
})();

// Move / resize elements on the card
$('cardHolder').addEventListener('pointerdown', function(ev) {
  var elDom = ev.target.closest('.el'); if (!elDom || ev.button > 0) return;
  var e = elById(elDom.dataset.id); if (!e) return;
  ev.preventDefault();
  var handle = ev.target.dataset.h || '';
  if (selId !== e.id) { selId = e.id; render(); renderAttrs(); }
  var start = ptFromEvent(ev), o = { x: e.x, y: e.y, w: e.w, h: e.h }, c = layout.card, moved = false;
  function mv(m) {
    var p = ptFromEvent(m), dx = p.x - start.x, dy = p.y - start.y;
    if (!moved && Math.abs(dx) + Math.abs(dy) < .8) return;
    if (!moved) { snapshot(); moved = true; }
    var snap = m.altKey ? .1 : 1;
    function sn(v) { return Math.round(v / snap) * snap; }
    if (!handle) {
      e.x = r1(sn(clamp(o.x + dx, -o.w + 6, c.w - 6))); e.y = r1(sn(clamp(o.y + dy, -o.h + 6, c.h - 6)));
    } else {
      var x1 = o.x, y1 = o.y, x2 = o.x + o.w, y2 = o.y + o.h;
      if (handle.indexOf('w') >= 0) x1 = Math.min(sn(o.x + dx), x2 - 4);
      if (handle.indexOf('e') >= 0) x2 = Math.max(sn(o.x + o.w + dx), x1 + 4);
      if (handle.indexOf('n') >= 0) y1 = Math.min(sn(o.y + dy), y2 - 4);
      if (handle.indexOf('s') >= 0) y2 = Math.max(sn(o.y + o.h + dy), y1 + 4);
      e.x = r1(x1); e.y = r1(y1); e.w = r1(x2 - x1); e.h = r1(y2 - y1);
    }
    var node = $('cardHolder').querySelector('.el[data-id="' + e.id + '"]');
    if (node) { node.style.left = e.x + 'pt'; node.style.top = e.y + 'pt'; node.style.width = e.w + 'pt'; node.style.height = e.h + 'pt'; }
    syncGeomInputs(e);
  }
  function up() {
    document.removeEventListener('pointermove', mv); document.removeEventListener('pointerup', up);
    if (moved) changed(true);
  }
  document.addEventListener('pointermove', mv); document.addEventListener('pointerup', up);
});

// ── Keyboard ──────────────────────────────────────────────────────────────────
document.addEventListener('keydown', function(ev) {
  var typing = /^(INPUT|TEXTAREA|SELECT)$/.test((document.activeElement || {}).tagName || '');
  var mod = ev.metaKey || ev.ctrlKey;
  if (mod && ev.key.toLowerCase() === 's') { ev.preventDefault(); saveLayout(); return; }
  if (typing || _activeTab !== 'design' || !layout) return;
  if (mod && ev.key.toLowerCase() === 'z') { ev.preventDefault(); if (ev.shiftKey) redo(); else undo(); return; }
  if (mod && ev.key.toLowerCase() === 'd') { ev.preventDefault(); duplicateSel(); return; }
  var e = elById(selId); if (!e) return;
  if (ev.key === 'Delete' || ev.key === 'Backspace') { ev.preventDefault(); deleteSel(); return; }
  if (ev.key === 'Escape') { selectEl(null); return; }
  var step = ev.shiftKey ? 10 : 1, d = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[ev.key];
  if (d) { ev.preventDefault(); snapshot(); e.x = r1(e.x + d[0]); e.y = r1(e.y + d[1]); changed(true); syncGeomInputs(e); }
});

// ── Selection, undo, saving ───────────────────────────────────────────────────
function elById(id) { return layout && layout.els.filter(function(e) { return e.id === id; })[0]; }
function selectEl(id) { selId = id; render(); renderAttrs(); }
function snapshot() { undoStack.push(JSON.stringify(layout)); if (undoStack.length > 80) undoStack.shift(); redoStack = []; }
function undo() { if (!undoStack.length) return; redoStack.push(JSON.stringify(layout)); layout = JSON.parse(undoStack.pop()); if (!elById(selId)) selId = null; changed(); }
function redo() { if (!redoStack.length) return; undoStack.push(JSON.stringify(layout)); layout = JSON.parse(redoStack.pop()); if (!elById(selId)) selId = null; changed(); }
function changed(keepAttrs) {
  setDirty(true);
  try { localStorage.setItem('cb_draft:' + SHEET_ID + ':' + curTab, JSON.stringify(layout)); } catch (e) {}
  render();
  if (!keepAttrs) renderAttrs();
}
function setDirty(d, restored) {
  dirty = d;
  $('saveBtn').disabled = !d;
  var st = $('saveStatus');
  st.textContent = d ? (restored ? 'Unsaved changes (restored)' : 'Unsaved changes') : (LAYOUTS[curTab] ? 'Saved' : '');
  st.className = 'tb-status' + (d ? ' dirty' : '');
}
function saveLayout() {
  if (!dirty || !curTab) return;
  var btn = $('saveBtn'), snap = JSON.stringify(layout), tab = curTab;
  btn.disabled = true; btn.textContent = 'Saving…';
  api('save', { tab: tab, layout: snap })
    .then(function() {
      LAYOUTS[tab] = JSON.parse(snap);
      try { localStorage.removeItem('cb_draft:' + SHEET_ID + ':' + tab); } catch (e) {}
      if (curTab === tab && JSON.stringify(layout) === snap) setDirty(false);
      toast('✓ Layout saved to your Sheet');
    })
    .catch(function(e) { toast('Couldn\'t save — ' + e.message, true); setDirty(true); })
    .then(function() { btn.textContent = 'Save'; btn.disabled = !dirty; });
}
window.addEventListener('beforeunload', function(ev) { if (dirty) { ev.preventDefault(); ev.returnValue = ''; } });

function deleteSel() { var e = elById(selId); if (!e) return; snapshot(); layout.els = layout.els.filter(function(x) { return x !== e; }); selId = null; changed(); }
function duplicateSel() {
  var e = elById(selId); if (!e) return;
  var c = JSON.parse(JSON.stringify(e)); c.x = r1(c.x + 8); c.y = r1(c.y + 8);
  addElementAt(c);
}
function reorderSel(dir) {
  var e = elById(selId); if (!e) return; snapshot();
  var zs = layout.els.map(function(x) { return x.z || 1; });
  e.z = dir > 0 ? Math.max.apply(null, zs) + 1 : Math.min.apply(null, zs) - 1;
  changed(true);
}

// ── Attributes panel ──────────────────────────────────────────────────────────
function setProp(key, val, num) {
  var e = elById(selId); if (!e) return;
  if (num) { val = parseFloat(val); if (isNaN(val)) return; }
  if (e[key] === val) return;
  snapshot(); e[key] = val; changed(true);
}
function setCard(key, val, num) {
  if (num) { val = parseFloat(val); if (isNaN(val)) return; }
  snapshot(); layout.card[key] = val;
  if (key === 'size') { var s = CARD_SIZES.filter(function(x) { return x.id === val; })[0]; if (s && s.w) { layout.card.w = s.w; layout.card.h = s.h; } }
  if (key === 'w' || key === 'h') layout.card.size = 'custom';
  changed(); if (key === 'size' || key === 'w' || key === 'h') fitZoom();
}
function syncGeomInputs(e) {
  ['x', 'y', 'w', 'h'].forEach(function(k) { var i = $('a_' + k); if (i && document.activeElement !== i) i.value = r1(e[k]); });
}
function colorRow(label, key, val, none, onSet) {
  var hexv = /^#[0-9a-f]{6}$/i.test(val || '') ? val : '#ffffff';
  return '<span class="mini">' + label + '</span><div class="color-row">' +
    '<input type="color" value="' + hexv + '" onchange="' + onSet + '(\'' + key + '\',this.value)" oninput="this.nextElementSibling.value=this.value">' +
    '<input class="inp" value="' + esc(val || '') + '" placeholder="' + (none ? 'none' : '#000000') + '" onchange="' + onSet + '(\'' + key + '\',this.value.trim())"></div>';
}
function numInp(id, label, val, onSet, key, step) {
  return '<div><span class="mini">' + label + '</span><input class="inp" type="number" step="' + (step || 1) + '" id="' + id + '" value="' + r1(val || 0) + '" onchange="' + onSet + '(\'' + key + '\',this.value,true)"></div>';
}
function seg(key, opts, cur) {
  return '<div class="seg">' + opts.map(function(o) {
    return '<button type="button" class="' + (o[0] === cur ? 'on' : '') + '" title="' + o[2] + '" onclick="setProp(\'' + key + '\',\'' + o[0] + '\');renderAttrs()">' + o[1] + '</button>';
  }).join('') + '</div>';
}
function renderAttrs() {
  var p = $('attrPanel'); if (!layout) { p.innerHTML = ''; return; }
  var e = elById(selId), h = '';
  if (!e) {
    var c = layout.card;
    h += '<p class="attr-title">Card</p><p class="muted" style="margin:0 0 .4rem">Select something on the card to change it.</p>';
    h += '<span class="lbl">Size</span><select class="sel" onchange="setCard(\'size\',this.value)">' +
         CARD_SIZES.map(function(s) { return '<option value="' + s.id + '"' + (s.id === (c.size || 'custom') ? ' selected' : '') + '>' + s.name + '</option>'; }).join('') + '</select>';
    h += '<div class="row2" style="margin-top:.45rem">' +
         '<div><span class="mini">Width (in)</span><input class="inp" type="number" step="0.05" value="' + Math.round(c.w / 72 * 100) / 100 + '" onchange="setCard(\'w\',this.value*72,true)"></div>' +
         '<div><span class="mini">Height (in)</span><input class="inp" type="number" step="0.05" value="' + Math.round(c.h / 72 * 100) / 100 + '" onchange="setCard(\'h\',this.value*72,true)"></div></div>';
    h += '<span class="lbl">Look</span>' + colorRow('Background', 'bg', c.bg, false, 'setCard');
    h += '<div class="row2" style="margin-top:.45rem">' + numInp('c_r', 'Corner radius (pt)', c.radius, 'setCard', 'radius') + numInp('c_bw', 'Border (pt)', c.borderW, 'setCard', 'borderW', .5) + '</div>';
    h += '<div style="margin-top:.45rem">' + colorRow('Border colour', 'borderColor', c.borderColor, false, 'setCard') + '</div>';
    h += '<span class="lbl">Shortcuts</span><p class="muted" style="margin:0">Arrows nudge 1 pt (Shift: 10) · Alt while dragging: fine moves · ⌘D duplicate · Delete removes · ⌘Z undo · ⌘S save</p>';
    p.innerHTML = h; return;
  }
  var kindName = { text: 'Text', image: 'Image', box: 'Box' }[e.kind];
  h += '<p class="attr-title">' + esc(e.kind === 'text' && /^\{[^{}]+\}$/.test(e.text || '') ? e.text.slice(1, -1) : kindName) + ' <span class="kind">' + kindName + '</span></p>';
  if (e.kind === 'text') {
    h += '<span class="lbl">Text</span><textarea class="ta" id="a_text" oninput="liveText(this.value)" onchange="setProp(\'text\',this.value)">' + esc(e.text) + '</textarea>';
    h += '<div class="insert-row"><select class="sel" id="a_ins"><option value="">Insert a field…</option>' + FIELDS.map(function(f) { return '<option>' + esc(f) + '</option>'; }).join('') + '</select>' +
         '<button class="attr-btn" style="flex:0 0 auto;padding:0 .7rem" onclick="insertField()">Insert</button></div>';
    h += '<span class="lbl">Font</span><select class="sel" onchange="setProp(\'font\',this.value)">' +
         FONTS.map(function(f) { return '<option value="' + f[0] + '"' + (f[0] === (e.font || 'DINRegular') ? ' selected' : '') + '>' + f[1] + '</option>'; }).join('') + '</select>';
    h += '<div class="row2" style="margin-top:.45rem">' + numInp('a_size', 'Size (pt)', e.size || 12, 'setProp', 'size', .5) + numInp('a_lh', 'Line height', e.lh || 1.15, 'setProp', 'lh', .05) + '</div>';
    h += '<div style="margin-top:.45rem">' + colorRow('Colour', 'color', e.color, false, 'setProp') + '</div>';
    h += '<div class="row2" style="margin-top:.45rem"><div><span class="mini">Style</span><div class="seg">' +
         '<button type="button" class="' + (e.bold ? 'on' : '') + '" onclick="setProp(\'bold\',' + !e.bold + ');renderAttrs()" title="Bold"><b>B</b></button>' +
         '<button type="button" class="' + (e.italic ? 'on' : '') + '" onclick="setProp(\'italic\',' + !e.italic + ');renderAttrs()" title="Italic"><i>I</i></button>' +
         '<button type="button" class="' + (e.upper ? 'on' : '') + '" onclick="setProp(\'upper\',' + !e.upper + ');renderAttrs()" title="Uppercase">AA</button></div></div>' +
         '<div><span class="mini">Align</span>' + seg('align', [['left', '⇤', 'Left'], ['center', '↔', 'Centre'], ['right', '⇥', 'Right']], e.align || 'left') + '</div></div>';
    h += '<div style="margin-top:.45rem"><span class="mini">Vertical</span>' + seg('valign', [['top', 'Top', 'Top'], ['middle', 'Middle', 'Middle'], ['bottom', 'Bottom', 'Bottom']], e.valign || 'top') + '</div>';
  }
  if (e.kind === 'image') {
    var known = FIELDS.some(function(f) { return '{' + f + '}' === e.src; });
    h += '<span class="lbl">Image field</span><select class="sel" onchange="setProp(\'src\',this.value)">' +
         FIELDS.map(function(f) { var v = '{' + f + '}'; return '<option value="' + esc(v) + '"' + (v === e.src ? ' selected' : '') + '>' + esc(f) + '</option>'; }).join('') +
         (known ? '' : '<option selected value="' + esc(e.src) + '">' + esc(e.src || '—') + '</option>') + '</select>';
    h += '<div style="margin-top:.45rem"><span class="mini">Fit</span>' + seg('fit', [['cover', 'Fill', 'Fill the box (crops)'], ['contain', 'Fit', 'Show the whole image'], ['stretch', 'Stretch', 'Stretch to the box']], e.fit || 'cover') + '</div>';
  }
  h += '<span class="lbl">' + (e.kind === 'box' ? 'Box' : 'Background') + '</span>' + colorRow('Fill', 'bg', e.bg, true, 'setProp');
  h += '<div class="row2" style="margin-top:.45rem">' + numInp('a_bw', 'Border (pt)', e.borderW, 'setProp', 'borderW', .5) + numInp('a_r', 'Corner radius', e.radius, 'setProp', 'radius') + '</div>';
  h += '<div style="margin-top:.45rem">' + colorRow('Border colour', 'borderColor', e.borderColor, false, 'setProp') + '</div>';
  if (e.kind === 'text') h += '<div style="margin-top:.45rem">' + numInp('a_pad', 'Padding (pt)', e.pad == null ? 2 : e.pad, 'setProp', 'pad', .5) + '</div>';
  h += '<span class="lbl">Position (pt)</span><div class="row4">' + numInp('a_x', 'X', e.x, 'setProp', 'x', .5) + numInp('a_y', 'Y', e.y, 'setProp', 'y', .5) + numInp('a_w', 'W', e.w, 'setProp', 'w', .5) + numInp('a_h', 'H', e.h, 'setProp', 'h', .5) + '</div>';
  h += '<div class="attr-btns"><button class="attr-btn" onclick="centerSel(\'x\')">Centre ↔</button><button class="attr-btn" onclick="centerSel(\'y\')">Centre ↕</button></div>';
  h += '<div class="attr-btns"><button class="attr-btn" onclick="reorderSel(1)">To front</button><button class="attr-btn" onclick="reorderSel(-1)">To back</button></div>';
  h += '<div class="attr-btns"><button class="attr-btn" onclick="duplicateSel()">Duplicate</button><button class="attr-btn danger" onclick="deleteSel()">Delete</button></div>';
  p.innerHTML = h;
}
function liveText(v) {   // show typing on the card right away; one undo step when the box loses focus
  var e = elById(selId); if (!e) return;
  var node = $('cardHolder').querySelector('.el[data-id="' + e.id + '"] .el-inner');
  if (node) { var t = fill(v, ROWS[rowIdx]); node.innerHTML = t.trim() ? esc(t) : ''; }
}
function insertField() {
  var f = $('a_ins').value, ta = $('a_text'); if (!f || !ta) return;
  var s = ta.selectionStart == null ? ta.value.length : ta.selectionStart, en = ta.selectionEnd == null ? s : ta.selectionEnd;
  ta.value = ta.value.slice(0, s) + '{' + f + '}' + ta.value.slice(en);
  setProp('text', ta.value); renderAttrs();
}
function centerSel(axis) {
  var e = elById(selId); if (!e) return; snapshot();
  if (axis === 'x') e.x = r1((layout.card.w - e.w) / 2); else e.y = r1((layout.card.h - e.h) / 2);
  changed(true); syncGeomInputs(e);
}

// ── Print sheets ──────────────────────────────────────────────────────────────
var PAPER = { letter: [612, 792], a4: [595.3, 841.9] };
function renderPrint() {
  var pages = $('pages');
  if (!layout) { pages.innerHTML = ''; return; }
  // Copies: 1 each, or a numeric column (Count, Qty…)
  var cs = $('pCopies'), cur = cs.value;
  var numeric = FIELDS.filter(function(f) { return ROWS.length && ROWS.every(function(r) { var v = cleanVal(r[f]).trim(); return v === '' || /^\d{1,3}$/.test(v); }); });
  cs.innerHTML = '<option value="">1 of each card</option>' + numeric.map(function(f) { return '<option value="' + esc(f) + '">From "' + esc(f) + '"</option>'; }).join('');
  if (!cur && !cs.dataset.touched) cur = numeric.filter(function(f) { return /^(count|qty|quantity|copies|#)$/i.test(f); })[0] || '';
  cs.value = numeric.indexOf(cur) >= 0 ? cur : '';
  cs.onchange = function() { cs.dataset.touched = '1'; renderPrint(); };
  var list = [];
  ROWS.forEach(function(r) {
    var n = cs.value ? parseInt(cleanVal(r[cs.value]), 10) : 1;
    if (isNaN(n)) n = 0;
    for (var i = 0; i < n; i++) list.push(r);
  });

  var paper = PAPER[$('pPaper').value] || PAPER.letter, land = $('pOrient').value === 'landscape';
  var pw = land ? paper[1] : paper[0], ph = land ? paper[0] : paper[1];
  $('pageSizeStyle').textContent = '@page { size: ' + ($('pPaper').value === 'a4' ? 'A4' : 'letter') + ' ' + (land ? 'landscape' : 'portrait') + '; margin: 0; }';
  var gap = parseFloat($('pGap').value) || 0, cut = $('pCut').value, margin = 18;
  var c = layout.card;
  var cols = Math.max(1, Math.floor((pw - 2 * margin + gap) / (c.w + gap)));
  var rowsN = Math.max(1, Math.floor((ph - 2 * margin + gap) / (c.h + gap)));
  var per = cols * rowsN, nPages = Math.max(1, Math.ceil(list.length / per));
  var gw = cols * c.w + (cols - 1) * gap, gh = rowsN * c.h + (rowsN - 1) * gap;
  var ox = (pw - gw) / 2, oy = (ph - gh) / 2;
  $('printInfo').textContent = list.length + (list.length === 1 ? ' card' : ' cards') + ' · ' + per + ' per page · ' + nPages + (nPages === 1 ? ' page' : ' pages');

  var scale = Math.min(1, (pages.clientWidth - 60) / (pw * PX)) || 1;
  var html = '';
  for (var p = 0; p < nPages; p++) {
    var cards = list.slice(p * per, (p + 1) * per);
    html += '<div class="page-holder" style="width:' + (pw * PX * scale) + 'px;height:' + (ph * PX * scale) + 'px">' +
            '<div class="page" style="width:' + pw + 'pt;height:' + ph + 'pt;transform:scale(' + scale + ')">';
    cards.forEach(function(row, i) {
      var cx = ox + (i % cols) * (c.w + gap), cy = oy + Math.floor(i / cols) * (c.h + gap);
      html += '<div style="position:absolute;left:' + cx + 'pt;top:' + cy + 'pt;width:' + c.w + 'pt;height:' + c.h + 'pt' + (cut === 'outline' ? ';outline:.5pt solid #9aa0b8' : '') + '">' + renderCardHTML(layout, row, false) + '</div>';
    });
    if (cut === 'marks' && cards.length) {
      var used = Math.ceil(cards.length / cols), len = 10, xs = [], ys = [];
      for (var ci = 0; ci < Math.min(cols, cards.length); ci++) { xs.push(ox + ci * (c.w + gap)); xs.push(ox + ci * (c.w + gap) + c.w); }
      for (var ri = 0; ri < used; ri++) { ys.push(oy + ri * (c.h + gap)); ys.push(oy + ri * (c.h + gap) + c.h); }
      var top = ys[0], bottom = ys[ys.length - 1], left = xs[0], right = xs[xs.length - 1];
      xs.forEach(function(x) {
        html += '<div class="cut" style="left:' + (x - .25) + 'pt;top:' + (top - len - 2) + 'pt;width:.5pt;height:' + len + 'pt"></div>';
        html += '<div class="cut" style="left:' + (x - .25) + 'pt;top:' + (bottom + 2) + 'pt;width:.5pt;height:' + len + 'pt"></div>';
      });
      ys.forEach(function(y) {
        html += '<div class="cut" style="top:' + (y - .25) + 'pt;left:' + (left - len - 2) + 'pt;height:.5pt;width:' + len + 'pt"></div>';
        html += '<div class="cut" style="top:' + (y - .25) + 'pt;left:' + (right + 2) + 'pt;height:.5pt;width:' + len + 'pt"></div>';
      });
    }
    html += '</div></div>';
  }
  pages.innerHTML = list.length ? html : '<div class="muted">No cards to print.</div>';
}

// ── Tabs, menu, donation ──────────────────────────────────────────────────────
function switchTab(t) {
  _activeTab = t;
  $('tabDesign').classList.toggle('active', t === 'design'); $('tabPrint').classList.toggle('active', t === 'print');
  $('view-design').classList.toggle('active', t === 'design'); $('view-print').classList.toggle('active', t === 'print');
  if (t === 'print') renderPrint(); else fitZoom();
}
function toggleAccountMenu(ev) { if (ev) ev.stopPropagation(); $('accountMenu').classList.toggle('open'); }
function closeAccountMenu() { $('accountMenu').classList.remove('open'); }
document.addEventListener('click', function(ev) { if (!ev.target.closest('.account-menu-wrap')) closeAccountMenu(); });
function startDonation() {
  toast('Opening secure checkout…');
  var u = new URL(window.location.href); u.searchParams.delete('donate');
  var cancel = u.toString(); u.searchParams.set('donate', 'thanks');
  var fd = new FormData();
  fd.append('type', 'donation'); fd.append('id', SHEET_ID); fd.append('app', 'cardboard');
  fd.append('success_url', u.toString()); fd.append('cancel_url', cancel);
  fetch(APP_BASE + 'push/stripeCheckout.php', { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(r) { if (!r || r.error || !r.url) throw new Error(r && r.error ? r.error : 'Could not start checkout'); window.location.href = r.url; })
    .catch(function(e) { toast(e.message, true); });
}
(function() {
  var u = new URL(window.location.href);
  if (u.searchParams.get('donate') === 'thanks') {
    u.searchParams.delete('donate'); window.history.replaceState(null, '', u.pathname + u.search + u.hash);
    setTimeout(function() { toast('♥ Thank you for your donation!'); }, 400);
  }
})();

init();
